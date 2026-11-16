<?php
/** 系统账号管理（仅管理员可操作） */

function require_admin()
{
    $user = current_user();
    require_permission($user, 'system:user');
    return $user;
}

function api_sysuser_list()
{
    require_admin();
    [$page, $limit, $offset] = page_params();

    $where = 'is_del = 0';
    $params = [];
    foreach (['name' => 'name', 'account' => 'account', 'phone' => 'phone', 'unitName' => 'unit_name'] as $key => $col) {
        $v = trim((string) param($key, ''));
        if ($v !== '') {
            $where .= " AND $col LIKE ?";
            $params[] = "%$v%";
        }
    }
    $status = param('status', null);
    if ($status !== null && $status !== '') {
        $where .= ' AND status = ?';
        $params[] = (int) $status;
    }

    $total = q_scalar("SELECT COUNT(*) FROM sys_user WHERE $where", $params);
    $rows = q_all(
        "SELECT id, account, name, phone, unit_name, identity, sign, status, last_time, last_ip, create_time
         FROM sys_user WHERE $where ORDER BY id LIMIT $limit OFFSET $offset",
        $params
    );

    $list = array_map(function ($r) {
        return [
            'id'        => (int) $r['id'],
            'account'   => $r['account'],
            'name'      => $r['name'],
            'phone'     => $r['phone'],
            'unitName'  => $r['unit_name'],
            'identity'  => $r['identity'],
            'sign'      => $r['sign'] ? true : false,
            'status'    => (int) $r['status'] === 1,
            'lastTime'  => $r['last_time'],
            'lastIp'    => $r['last_ip'],
            'createTime'=> $r['create_time'],
        ];
    }, $rows);

    ok(page_data($list, $total, $page, $limit));
}

function api_sysuser_add()
{
    require_admin();
    $account = trim((string) param('account', ''));
    $password = (string) param('password', '');
    $name = trim((string) param('name', ''));
    if (!$account || !$password || !$name) err('账号、密码、姓名为必填');

    if (q_scalar('SELECT COUNT(*) FROM sys_user WHERE account = ? AND is_del = 0', [$account])) {
        err('账号已存在');
    }

    $id = q_insert('sys_user', [
        'account'    => $account,
        'password'   => md5($password),
        'name'       => $name,
        'phone'      => (string) param('phone', ''),
        'unit_name'  => (string) param('unitName', ''),
        'identity'   => (string) param('identity', '检验员'),
        'permissions'=> (string) param('permissions', 'dashboard'),
        'sign'       => (string) param('sign', ''),
        'status'     => 1,
    ]);
    ok(['id' => $id]);
}

function api_sysuser_update()
{
    require_admin();
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    if (!q_scalar('SELECT id FROM sys_user WHERE id = ? AND is_del = 0', [$id])) err('账号不存在');

    $data = [];
    foreach (['name', 'phone', 'unit_name', 'identity', 'permissions', 'sign'] as $snake) {
        $camel = lcfirst(str_replace('_', '', ucwords($snake, '_')));
        $v = param($camel, param($snake));
        if ($v !== null) $data[$snake] = $v;
    }
    // 可选改密
    $password = (string) param('password', '');
    if ($password !== '') $data['password'] = md5($password);
    // 签名图片（base64 dataurl 存路径或直接存字符串，演示存字符串）
    if ($data) {
        q_update('sys_user', $data, 'id = ?', [$id]);
    }
    ok(null);
}

function api_sysuser_delete()
{
    require_admin();
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    if ((int) $id === 1) err('内置管理员不可删除');
    q_update('sys_user', ['is_del' => 1, 'login_token' => '', 'token_expire' => 0], 'id = ?', [$id]);
    ok(null);
}

/** 启用 / 停用 */
function api_sysuser_status()
{
    require_admin();
    $id = (int) param('id', 0);
    $status = (int) param('status', 1);
    if (!$id) err('缺少参数 id');
    if ((int) $id === 1) err('内置管理员不可停用');
    q_update('sys_user', [
        'status' => $status ? 1 : 0,
        'login_token' => '',
        'token_expire' => 0,
    ], 'id = ?', [$id]);
    ok(null);
}
