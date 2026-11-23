<?php
/** C端（校验设备操作员）接口：登录 / 任务列表 / 领取 / 上传校验结果
 *
 *  账号来源两处：
 *  - c_user 表（设备操作员专用账号），token 存 c_user.login_token
 *  - sys_user 表中身份为「检验员/检验人员」的 B 端账号也可登录（回退），token 存 sys_user.login_token
 *    （current_cuser 按 token 先查 c_user、再查 sys_user，返回结构归一化，业务无感知）
 *  - 路由需在 index.php 中将 needAuth 置 false，由本文件 current_cuser() 自行鉴权
 *  - 任务 = workorder 中 status IN (wait, inspection) 的数据（后台待检验 + 检验中）
 */

const C_TOKEN_TTL     = 7 * 86400;   // token 有效期 7 天
const C_MAX_FAIL      = 5;           // 连续密码错误上限
const C_LOCK_SECONDS  = 600;         // 锁定时长（秒）

// 允许登录 C 端的 B 端身份（校验人员/检验员，sys_user.identity）
const B_INSPECTOR_IDENTITIES = "('检验员','检验人员')";

/** 获取当前 C 端登录用户（token 只查 c_user 表） */
function current_cuser($required = true)
{
    $token = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = $m[1];
        }
    }
    if (!$token && !empty($_SERVER['HTTP_TOKEN'])) {
        $token = trim($_SERVER['HTTP_TOKEN']);
    }
    if (!$token) {
        $t = param('token', '');
        if ($t) $token = $t;
    }

    if (!$token) {
        if ($required) err('未登录或登录已过期', 401);
        return null;
    }

    $user = q_one('SELECT * FROM c_user WHERE login_token = ? AND is_del = 0 AND status = 1', [$token]);
    if ($user && (int) $user['token_expire'] >= time()) {
        unset($user['password']);
        return $user;
    }

    // 回退：B 端检验人员账号（sys_user）也可能登录 C 端。
    // C 端 token 存在独立的 c_login_token 列（登录时不覆盖 B 端 login_token），
    // 同一账号可以同时在线两端互不踢下线。
    $su = q_one(
        'SELECT * FROM sys_user WHERE c_login_token = ? AND is_del = 0 AND status = 1 AND identity IN ' . B_INSPECTOR_IDENTITIES,
        [$token]
    );
    if ($su && (int) $su['c_token_expire'] >= time()) {
        // 归一化成 c_user 的返回结构（B 端账号无设备绑定，device_sn 置空）
        return [
            'id'          => (int) $su['id'],
            'account'     => $su['account'],
            'name'        => $su['name'],
            'device_sn'   => '',
            'from_sys'    => true,
        ];
    }

    if ($required) err('登录已过期，请重新登录', 401);
    return null;
}

/** POST /c/login {account, password, deviceSn?} */
function api_c_login()
{
    $account  = trim((string) param('account', ''));
    $password = (string) param('password', '');
    $deviceSn = trim((string) param('deviceSn', ''));

    if ($account === '' || $password === '') err('请输入账号和密码');

    $user = q_one('SELECT * FROM c_user WHERE account = ? AND is_del = 0', [$account]);

    // C 端账号不存在时，回退尝试 B 端检验人员账号（sys_user，身份=检验员/检验人员）。
    // 命中即发 token 走 C 端流程；密码/状态不对则继续走下方 c_user 的报错逻辑。
    if (!$user) {
        $su = q_one(
            'SELECT * FROM sys_user WHERE account = ? AND is_del = 0 AND identity IN ' . B_INSPECTOR_IDENTITIES,
            [$account]
        );
        if ($su && $su['password'] === md5($password)) {
            if ((int) $su['status'] !== 1) err('账号已被停用，请联系管理员');
            $token = generate_token();
            // C 端 token 写独立列，不覆盖 B 端 login_token —— 同一账号可两端同时在线
            q_update('sys_user', [
                'c_login_token'  => $token,
                'c_token_expire' => time() + C_TOKEN_TTL,
                'last_time'      => date('Y-m-d H:i:s'),
                'last_ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
            ], 'id = ?', [$su['id']]);
            ok([
                'token'     => $token,
                'expiresIn' => C_TOKEN_TTL,
                'id'        => (int) $su['id'],
                'name'      => $su['name'],
                'account'   => $su['account'],
                'deviceSn'  => '',
            ]);
        }
    }

    // 锁定检查
    if ($user && (int) $user['fail_count'] >= C_MAX_FAIL && (int) $user['lock_until'] > time()) {
        $left = (int) ceil(((int) $user['lock_until'] - time()) / 60);
        err('密码错误次数过多，账号已锁定，请 ' . $left . ' 分钟后再试');
    }

    if (!$user || $user['password'] !== md5($password)) {
        if ($user) {
            $fail = (int) $user['fail_count'] + 1;
            $upd = ['fail_count' => $fail];
            if ($fail >= C_MAX_FAIL) {
                $upd['lock_until'] = time() + C_LOCK_SECONDS;
                $upd['fail_count'] = 0; // 锁定期满后重新计数
                q_update('c_user', $upd, 'id = ?', [$user['id']]);
                err('密码错误次数过多，账号已锁定 10 分钟');
            }
            q_update('c_user', $upd, 'id = ?', [$user['id']]);
        }
        err('账号或密码错误');
    }

    if ((int) $user['status'] !== 1) err('账号已被停用，请联系管理员');

    // 绑定了设备编号时校验一致性（两者都传才强校验）
    if ($user['device_sn'] !== '' && $deviceSn !== '' && $user['device_sn'] !== $deviceSn) {
        err('设备编号与账号绑定不一致');
    }

    $token = generate_token();
    q_update('c_user', [
        'login_token'  => $token,
        'token_expire' => time() + C_TOKEN_TTL,
        'fail_count'   => 0,
        'lock_until'   => 0,
        'last_time'    => date('Y-m-d H:i:s'),
        'last_ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
    ], 'id = ?', [$user['id']]);

    ok([
        'token'     => $token,
        'expiresIn' => C_TOKEN_TTL,
        'id'        => (int) $user['id'],
        'name'      => $user['name'],
        'account'   => $user['account'],
        'deviceSn'  => $user['device_sn'],
    ]);
}

/** POST /c/logout */
function api_c_logout()
{
    $user = current_cuser(false);
    if ($user) {
        if (!empty($user['from_sys'])) {
            // B 端检验人员账号：清独立的 C 端 token 列（不影响 B 端登录态）
            q_update('sys_user', ['c_login_token' => '', 'c_token_expire' => 0], 'id = ?', [$user['id']]);
        } else {
            q_update('c_user', ['login_token' => '', 'token_expire' => 0], 'id = ?', [$user['id']]);
        }
    }
    ok(null);
}

/** GET /c/task/list?custName=&idNo=&orderCode=&page=&limit=
 *  任务列表 = 后台「待检验 + 检验中」工单（只读视图） */
function api_c_task_list()
{
    current_cuser();
    [$page, $limit, $offset] = page_params();

    $where = "w.is_del = 0 AND w.status IN ('wait','inspection')";
    $params = [];

    $custName = trim((string) param('custName', ''));
    if ($custName !== '') {
        $where .= ' AND w.cust_name LIKE ?';
        $params[] = "%$custName%";
    }
    $idNo = trim((string) param('idNo', ''));
    if ($idNo !== '') {
        $where .= ' AND w.id_no LIKE ?';
        $params[] = "%$idNo%";
    }
    $orderCode = trim((string) param('orderCode', ''));
    if ($orderCode !== '') {
        $where .= ' AND w.order_code LIKE ?';
        $params[] = "%$orderCode%";
    }

    $total = q_scalar("SELECT COUNT(*) FROM workorder w WHERE $where", $params);
    $rows = q_all(
        "SELECT w.* FROM workorder w WHERE $where ORDER BY w.order_code DESC LIMIT $limit OFFSET $offset",
        $params
    );

    // 精简输出（与校验设备软件任务列表列一致）
    $list = array_map(function ($w) {
        return [
            'id'              => (int) $w['id'],
            'orderCode'       => $w['order_code'],
            'status'          => $w['status'],
            'custName'        => $w['cust_name'],
            'vavleModel'      => $w['vavle_model'],
            'vavleType'       => $w['vavle_type'],
            'equipType'       => $w['equip_type'],          // 设备类型（基本信息面板展示）
            'setPress'        => $w['set_press'],
            'tightnessPress'  => $w['tightness_press'],
            'diameterNominal' => $w['diameter_nominal'],
            'seatSize'        => $w['seat_size'],
            'workingPress'    => $w['working_press'],
            'pressNominal'    => $w['press_nominal'],       // 公称压力（基本信息面板展示）
            'pressClassRangeMin' => $w['press_class_range_min'], // 压力级别范围起（校验设备自动量程选档用）
            'pressClassRangeMax' => $w['press_class_range_max'], // 压力级别范围止
            'manuNo'          => $w['manu_no'],
            'licenseNo'       => $w['license_no'],          // 制造许可证编号（基本信息面板展示）
            'manufacturer'    => $w['manufacturer'],        // 制造单位（基本信息面板展示）
            'idNo'            => $w['id_no'],
            'inspector'       => $w['inspector'],
        ];
    }, $rows);

    ok(page_data($list, $total, $page, $limit));
}

/** GET /c/task/detail?id= 单条任务完整信息（仅待检验/检验中可见） */
function api_c_task_detail()
{
    current_cuser();
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one(workorder_select_sql() . "WHERE w.id = ? AND w.is_del = 0 AND w.status IN ('wait','inspection')", [$id]);
    if (!$row) err('任务不存在或不可校验');
    $out = workorder_row($row);
    unset($out['result']); // 未出结果，剔除
    ok($out);
}

/** POST /c/task/claim {id} 领取任务：wait → inspection，inspector 记为操作员姓名 */
function api_c_task_claim()
{
    $cuser = current_cuser();
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');

    $row = q_one('SELECT id, status, inspector, order_code FROM workorder WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('任务不存在');

    if ($row['status'] === 'wait') {
        q_update('workorder', [
            'status'      => 'inspection',
            'inspector'   => $cuser['name'],
            'update_time' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        ok(['orderCode' => $row['order_code'], 'status' => 'inspection']);
    }

    // 已是检验中：本人重复领取视为幂等成功，他人领取则拒绝
    if ($row['status'] === 'inspection') {
        if ($row['inspector'] === '' || $row['inspector'] === $cuser['name']) {
            ok(['orderCode' => $row['order_code'], 'status' => 'inspection']);
        }
        err('该任务已被 ' . $row['inspector'] . ' 领取');
    }

    err('仅待检验状态的任务可以领取');
}

/** POST /c/task/result {id, testStatus, testingPressure1..3, ...} 上传校验结果，进入后台待审核 */
function api_c_task_result()
{
    $cuser = current_cuser();
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');

    $row = q_one('SELECT * FROM workorder WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('任务不存在');
    if ($row['status'] !== 'inspection') err('仅检验中的任务可以上传结果');
    if ($row['inspector'] !== '' && $row['inspector'] !== $cuser['name']) {
        err('该任务由 ' . $row['inspector'] . ' 领取，无权上传结果');
    }

    $in = input();
    $resultFields = [
        'tightness_press', 'set_press', 'test_media', 'test_temprature',
        'parity', 'visual_examination', 'disassembly_inspection', 'note',
        'pressure_gauge_num', 'pressure_gauge_lifespan', 'pressure_gauge_num2',
        'pressure_gauge_lifespan2', 'pressure_gauge_accuracy', 'pressure_gauge_range',
        'device_model', 'device_sn', 'testing_pressure1', 'testing_pressure2',
        'testing_pressure3', 'test_status', 'leak_rate',
        'maintenance_info', 'curve_data1', 'curve_data2', 'curve_data3',
    ];

    $data = [];
    foreach ($resultFields as $snake) {
        $camel = lcfirst(str_replace('_', '', ucwords($snake, '_')));
        if (isset($in[$camel])) $data[$snake] = $in[$camel];
        elseif (isset($in[$snake])) $data[$snake] = $in[$snake];
    }
    if (empty($data['test_status'])) err('请上传校验结果（合格/不合格）');
    if (!in_array($data['test_status'], ['合格', '不合格'], true)) err('校验结果只能是 合格 或 不合格');

    // 校验设备编号：未传时回填 C 端账号绑定的设备编号
    if (empty($data['device_sn'])) {
        $data['device_sn'] = $cuser['device_sn'];
    }

    $data['workorder_id'] = $id;
    $data['order_code'] = $row['order_code'];

    db()->beginTransaction();
    try {
        $exists = q_scalar('SELECT id FROM workorder_result WHERE workorder_id = ?', [$id]);
        if ($exists) {
            unset($data['workorder_id']);
            q_update('workorder_result', $data, 'workorder_id = ?', [$id]);
        } else {
            q_insert('workorder_result', $data);
        }
        q_update('workorder', [
            'status'         => 'audit',
            'inspector'      => $cuser['name'],
            'inspector_time' => date('Y-m-d H:i:s'),
            'update_time'    => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        db()->commit();
    } catch (Exception $e) {
        db()->rollBack();
        throw $e;
    }

    // 报告链接（扫码/浏览器打开即 PDF 预览；地址取服务端局域网 IP，保证手机扫码可达）
    $ip = (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1' && $_SERVER['SERVER_ADDR'] !== '::1')
        ? $_SERVER['SERVER_ADDR']
        : gethostbyname(gethostname());
    $apiBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/inspection-api/index.php')), '/');
    $reportUrl = 'http://' . $ip . $apiBase . '/report.php?code=' . urlencode($row['order_code']);

    ok(['orderCode' => $row['order_code'], 'status' => 'audit', 'reportUrl' => $reportUrl]);
}

/** GET /c/task/done 当前操作员已完成（结果已提交，工单离开待检验/检验中）的工单数量 */
function api_c_task_done()
{
    $cuser = current_cuser();
    $cnt = q_scalar(
        "SELECT COUNT(*) FROM workorder WHERE is_del = 0 AND inspector = ? AND status NOT IN ('wait','inspection')",
        [$cuser['name']]
    );
    ok(['doneCount' => (int) $cnt]);
}
