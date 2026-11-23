<?php
/**
 * 安全阀检验数字管理平台 —— API 统一入口
 *
 * 部署（phpstudy）：
 * 1. 将本 api 目录放到 phpstudy 的 WWW 目录下（如 D:\phpstudy_pro\WWW\inspection-api）
 * 2. 在 phpMyAdmin 导入 sql/init.sql（或执行 mysql -uroot -proot < sql/init.sql）
 * 3. 按实际环境修改 config.php 中的数据库账号密码
 * 4. 访问方式（伪静态，需 Apache 开启 mod_rewrite 且站点 AllowOverride All）：
 *    http://localhost/inspection-api/commission/list
 *    http://localhost/inspection-api/system/menu/list?page=1&limit=10
 *    （伪静态不可用时退回：http://localhost/inspection-api/index.php?s=commission/list）
 *
 * 响应统一格式：{ "code": 0, "message": "ok", "data": ... }（code=0 成功）
 * 登录后接口通过请求头 Authorization: Bearer <token> 或参数 token 鉴权
 */

require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/resp.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/mapper.php';

require_once __DIR__ . '/controllers/auth.php';
require_once __DIR__ . '/controllers/workbench.php';
require_once __DIR__ . '/controllers/valve.php';
require_once __DIR__ . '/controllers/commission.php';
require_once __DIR__ . '/controllers/fee.php';
require_once __DIR__ . '/controllers/workorder.php';
require_once __DIR__ . '/controllers/capi.php';
require_once __DIR__ . '/controllers/sysuser.php';
require_once __DIR__ . '/controllers/sysrole.php';
require_once __DIR__ . '/controllers/site.php';
require_once __DIR__ . '/controllers/template.php';
require_once __DIR__ . '/controllers/sysmenu.php';

cors();

try {
    $route = resolve_route();

    /**
     * 路由表：路由名 => [请求方法, 是否需要登录, 处理函数, 所需权限标识]
     * 权限标识支持按钮级粒度（模块:页面:按钮），为空表示登录即可访问。
     * 权限变更后旧 token 会失效，重新登录即按新权限生效。
     */
    $routes = [
        // 登录相关
        'login'               => ['POST', false, 'api_login',            ''],
        'logout'              => ['POST', true,  'api_logout',            ''],
        'user/info'           => ['GET',  true,  'api_user_info',         ''],
        // 工作台
        'workbench/stats'     => ['GET',  true,  'api_workbench_stats',   'dashboard'],
        'workbench/chart'     => ['GET',  true,  'api_workbench_chart',   'dashboard'],
        'workbench/rank/inspector' => ['GET', true, 'api_rank_inspector', 'dashboard'],
        'workbench/rank/unit'      => ['GET', true, 'api_rank_unit',      'dashboard'],
        // 使用单位 / 客户管理
        'unit/list'           => ['GET',  true,  'api_unit_list',         ['commission', 'commission:add', 'customer']],
        'unit/add'            => ['POST', true,  'api_unit_add',          'customer'],
        'unit/update'         => ['POST', true,  'api_unit_update',       'customer'],
        'unit/delete'         => ['POST', true,  'api_unit_delete',       'customer'],
        'unit/reset_password' => ['POST', true,  'api_unit_reset_password', 'customer:resetPwd'],
        // 安全阀库
        'valve/list'          => ['GET',  true,  'api_valve_list',        ['commission', 'commission:add', 'valve', 'valve:list']],
        'valve/add'           => ['POST', true,  'api_valve_add',         ['valve', 'valve:add', 'commission:add:valve']],
        'valve/update'        => ['POST', true,  'api_valve_update',      ['valve', 'valve:list:edit', 'commission:add:valve']],
        'valve/delete'        => ['POST', true,  'api_valve_delete',      ['valve', 'valve:list:delete', 'commission:add:valve']],
        'valve/import'        => ['POST', true,  'api_valve_import',      ['valve', 'valve:add']],
        // 委托单
        'commission/list'     => ['GET',  true,  'api_commission_list',   'commission:list'],
        'commission/detail'   => ['GET',  true,  'api_commission_detail', ['commission', 'commission:list']],
        // 待检阀门汇总（所有委托单位下未完成检测的阀门）
        'commission/pending_valves' => ['GET', true, 'api_commission_pending_valves', 'commission:list:pending'],
        // 手机扫码签名（签名图片与委托单绑定）
        'commission/sign_qrcode' => ['GET', true, 'api_commission_sign_qrcode', 'commission:list:sign'],
        'commission/sign_status' => ['GET', true, 'api_commission_sign_status', 'commission:list:sign'],
        'commission/add'      => ['POST', true,  'api_commission_add',    'commission:add'],
        // 收费列表（委托单管理）
        'fee/list'            => ['GET',  true,  'api_fee_list',   'commission:charge'],
        'fee/detail'          => ['GET',  true,  'api_fee_detail', 'commission:charge'],
        'fee/save'            => ['POST', true,  'api_fee_save',   'commission:charge:pay'],
        'fee/reset'           => ['POST', true,  'api_fee_reset',  'commission:charge:reset'],
        'commission/update'   => ['POST', true,  'api_commission_update', 'commission:list:edit'],
        // 编辑委托单阀门信息（查看弹窗「编辑」按钮）
        'commission/valve_update' => ['POST', true, 'api_commission_valve_update', 'commission:list:edit'],
        // 新增安全阀：立即写入安全阀库（现场送检 / 委托单编辑弹窗共用，不等委托单提交）
        'commission/lib_add'      => ['POST', true, 'api_commission_lib_add', ['commission:add:valve', 'commission:list:edit']],
        // 委托单新增 / 删除阀门（查看弹窗：新增安全阀、选择已有安全阀、复制、删除）
        'commission/valve_add'    => ['POST', true, 'api_commission_valve_add', 'commission:list:edit'],
        // 选择已有安全阀：历次委托单阀门清单汇总（筛选/分页）
        'commission/valve_pool'   => ['GET', true, 'api_commission_valve_pool', 'commission:list:edit'],
        'commission/valve_delete' => ['POST', true, 'api_commission_valve_delete', 'commission:list:edit'],
        // action 的子权限（通过/退回/预检/付费/删除）在控制器内按 action 细分校验
        'commission/next_serial' => ['GET', true,  'api_commission_next_serial', 'commission:list'],
        'commission/action'   => ['POST', true,  'api_commission_action', 'commission:list'],
        // 检验工单
        'workorder/list'      => ['GET',  true,  'api_workorder_list',    'workorder'],
        'workorder/detail'    => ['GET',  true,  'api_workorder_detail',  'workorder'],
        'workorder/update_code' => ['POST', true, 'api_workorder_update_code', 'commission:list:editCode'],
        'workorder/submit'    => ['POST', true,  'api_workorder_submit',  'workorder:wait:submit'],
        'workorder/resubmit'  => ['POST', true,  'api_workorder_resubmit','workorder:reject:resubmit'],
        'workorder/submit_result' => ['POST', true, 'api_workorder_submit_result', 'workorder:inspection:result'],
        'workorder/audit'     => ['POST', true,  'api_workorder_audit',   ['workorder:audit:pass', 'workorder:audit:reject']],
        'workorder/approve'   => ['POST', true,  'api_workorder_approve', ['workorder:approval:pass', 'workorder:approval:reject']],
        'workorder/print'     => ['POST', true,  'api_workorder_print',   ['workorder:print:print', 'workorder:all:print']],
        // 系统账号
        'system/user/list'    => ['GET',  true,  'api_sysuser_list',      'system:user'],
        'system/user/add'     => ['POST', true,  'api_sysuser_add',       'system:user:add'],
        'system/user/update'  => ['POST', true,  'api_sysuser_update',    'system:user:edit'],
        'system/user/delete'  => ['POST', true,  'api_sysuser_delete',    'system:user:delete'],
        'system/user/status'  => ['POST', true,  'api_sysuser_status',    'system:user:status'],
        // 角色权限设置
        'system/role/list'        => ['GET',  true, 'api_sysrole_list',        'system:role'],
        'system/role/add'         => ['POST', true, 'api_sysrole_add',         'system:role:add'],
        'system/role/update'      => ['POST', true, 'api_sysrole_update',      'system:role:edit'],
        'system/role/permissions' => ['POST', true, 'api_sysrole_permissions', 'system:role:perm'],
        'system/role/delete'      => ['POST', true, 'api_sysrole_delete',      'system:role:delete'],
        // 菜单访问设置
        // 站点管理（list 为基础数据下拉，站点/模版/系统账号多处复用，登录即可读）
        'system/site/list'    => ['GET',  true, 'api_site_list',    ''],
        'system/site/add'     => ['POST', true, 'api_site_add',     'system:site:add'],
        'system/site/update'  => ['POST', true, 'api_site_update',  'system:site:edit'],
        'system/site/status'  => ['POST', true, 'api_site_status',  'system:site:status'],
        'system/site/delete'  => ['POST', true, 'api_site_delete',  'system:site:delete'],
        // 模版管理
        'system/tpl/list'     => ['GET',  true, 'api_tpl_list',    'system:tpl'],
        'system/tpl/types'    => ['GET',  true, 'api_tpl_types',   'system:tpl'],
        'system/tpl/add'      => ['POST', true, 'api_tpl_add',     'system:tpl:add'],
        'system/tpl/update'   => ['POST', true, 'api_tpl_update',  'system:tpl:edit'],
        'system/tpl/status'   => ['POST', true, 'api_tpl_status',  'system:tpl:status'],
        'system/tpl/delete'   => ['POST', true, 'api_tpl_delete',  'system:tpl:delete'],
        'system/menu/list'    => ['GET',  true, 'api_sysmenu_list',        'system:menu'],
        'system/menu/add'     => ['POST', true, 'api_sysmenu_add',         'system:menu:add'],
        'system/menu/update'  => ['POST', true, 'api_sysmenu_update',      'system:menu:edit'],
        'system/menu/visible' => ['POST', true, 'api_sysmenu_visible',     'system:menu:visible'],
        'system/menu/delete'  => ['POST', true, 'api_sysmenu_delete',      'system:menu:delete'],
        // C端（校验设备操作员）：needAuth=false，由 capi.php 的 current_cuser() 自行鉴权
        'c/login'             => ['POST', false, 'api_c_login',     ''],
        'c/logout'            => ['POST', false, 'api_c_logout',    ''],
        'c/task/list'         => ['GET',  false, 'api_c_task_list',   ''],
        'c/task/detail'       => ['GET',  false, 'api_c_task_detail', ''],
        'c/task/claim'        => ['POST', false, 'api_c_task_claim',  ''],
        'c/task/result'       => ['POST', false, 'api_c_task_result', ''],
        'c/task/done'         => ['GET',  false, 'api_c_task_done',   ''],
    ];

    if (!isset($routes[$route])) {
        err('接口不存在：' . $route, 404);
    }

    [$method, $needAuth, $func, $perm] = $routes[$route];
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        err('请求方法错误，应为 ' . $method, 405);
    }

    if ($needAuth) {
        $user = current_user(true);
        // 统一按钮级权限校验（$perm 为空表示登录即可）
        if ($perm) {
            require_permission($user, $perm);
        }
    }

    if (!function_exists($func)) {
        err('接口处理函数缺失：' . $func, 500);
    }
    $func();
} catch (PDOException $e) {
    err(DEBUG ? ('数据库错误：' . $e->getMessage()) : '数据库异常，请稍后重试', 500);
} catch (Throwable $e) {
    err(DEBUG ? ('服务器错误：' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine()) : '服务器异常，请稍后重试', 500);
}

/** 解析路由：优先 ?s=xxx，否则从 REQUEST_URI 中取 index.php 之后的路径 */
function resolve_route()
{
    if (!empty($_GET['s'])) {
        $route = $_GET['s'];
    } else {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $script = $_SERVER['SCRIPT_NAME'];
        if (strpos($uri, $script) === 0) {
            $route = substr($uri, strlen($script));
        } elseif (strpos($uri, dirname($script)) === 0) {
            $route = substr($uri, strlen(rtrim(dirname($script), '/')));
        } else {
            $route = $uri;
        }
    }
    // 容错：去掉误带入的查询串部分
    $route = explode('?', $route)[0];
    return trim($route, '/');
}
