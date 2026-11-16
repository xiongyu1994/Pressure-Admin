<?php
/**
 * 安全阀检验数字管理平台 —— API 统一入口
 *
 * 部署（phpstudy）：
 * 1. 将本 api 目录放到 phpstudy 的 WWW 目录下（如 D:\phpstudy_pro\WWW\inspection-api）
 * 2. 在 phpMyAdmin 导入 sql/init.sql（或执行 mysql -uroot -proot < sql/init.sql）
 * 3. 按实际环境修改 config.php 中的数据库账号密码
 * 4. 访问方式：
 *    - 带伪静态（.htaccess，Apache）：http://localhost/inspection-api/commission/list
 *    - 无伪静态：http://localhost/inspection-api/index.php?s=commission/list
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
require_once __DIR__ . '/controllers/workorder.php';
require_once __DIR__ . '/controllers/sysuser.php';
require_once __DIR__ . '/controllers/sysrole.php';
require_once __DIR__ . '/controllers/sysmenu.php';

cors();

try {
    $route = resolve_route();

    // 路由表：路由名 => [请求方法, 是否需要登录, 处理函数]
    $routes = [
        // 登录相关
        'login'               => ['POST', false, 'api_login'],
        'logout'              => ['POST', true,  'api_logout'],
        'user/info'           => ['GET',  true,  'api_user_info'],
        // 工作台
        'workbench/stats'     => ['GET',  true,  'api_workbench_stats'],
        'workbench/chart'     => ['GET',  true,  'api_workbench_chart'],
        'workbench/rank/inspector' => ['GET', true, 'api_rank_inspector'],
        'workbench/rank/unit'      => ['GET', true, 'api_rank_unit'],
        'unit/list'           => ['GET',  true,  'api_unit_list'],
        // 安全阀库
        'valve/list'          => ['GET',  true,  'api_valve_list'],
        'valve/add'           => ['POST', true,  'api_valve_add'],
        'valve/delete'        => ['POST', true,  'api_valve_delete'],
        // 委托单
        'commission/list'     => ['GET',  true,  'api_commission_list'],
        'commission/detail'   => ['GET',  true,  'api_commission_detail'],
        'commission/add'      => ['POST', true,  'api_commission_add'],
        'commission/update'   => ['POST', true,  'api_commission_update'],
        'commission/action'   => ['POST', true,  'api_commission_action'],
        // 检验工单
        'workorder/list'      => ['GET',  true,  'api_workorder_list'],
        'workorder/detail'    => ['GET',  true,  'api_workorder_detail'],
        'workorder/submit'    => ['POST', true,  'api_workorder_submit'],
        'workorder/resubmit'  => ['POST', true,  'api_workorder_resubmit'],
        'workorder/submit_result' => ['POST', true, 'api_workorder_submit_result'],
        'workorder/print'     => ['POST', true,  'api_workorder_print'],
        // 系统账号
        'system/user/list'    => ['GET',  true,  'api_sysuser_list'],
        'system/user/add'     => ['POST', true,  'api_sysuser_add'],
        'system/user/update'  => ['POST', true,  'api_sysuser_update'],
        'system/user/delete'  => ['POST', true,  'api_sysuser_delete'],
        'system/user/status'  => ['POST', true,  'api_sysuser_status'],
        // 角色权限设置
        'system/role/list'        => ['GET',  true, 'api_sysrole_list'],
        'system/role/add'         => ['POST', true, 'api_sysrole_add'],
        'system/role/update'      => ['POST', true, 'api_sysrole_update'],
        'system/role/permissions' => ['POST', true, 'api_sysrole_permissions'],
        'system/role/delete'      => ['POST', true, 'api_sysrole_delete'],
        // 菜单访问设置
        'system/menu/list'    => ['GET',  true, 'api_sysmenu_list'],
        'system/menu/add'     => ['POST', true, 'api_sysmenu_add'],
        'system/menu/update'  => ['POST', true, 'api_sysmenu_update'],
        'system/menu/visible' => ['POST', true, 'api_sysmenu_visible'],
        'system/menu/delete'  => ['POST', true, 'api_sysmenu_delete'],
    ];

    if (!isset($routes[$route])) {
        err('接口不存在：' . $route, 404);
    }

    [$method, $needAuth, $func] = $routes[$route];
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        err('请求方法错误，应为 ' . $method, 405);
    }

    if ($needAuth) {
        current_user(true);
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
