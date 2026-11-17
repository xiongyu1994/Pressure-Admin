<?php
/** 系统账号管理（权限由 index.php 路由表统一校验：system:user / system:user:*） */

function api_sysuser_list()
{
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
        "SELECT id, account, name, phone, unit_name, site, identity, sign, status, last_time, last_ip, create_time
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
            'site'      => isset($r['site']) ? (string) $r['site'] : '',
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
        'site'       => (string) param('site', ''),
        'identity'   => (string) param('identity', '检验人员'),
        'permissions'=> identity_default_perms((string) param('identity', '检验人员')),
        'sign'       => (string) param('sign', ''),
        'status'     => (int) param('status', 1) === 0 ? 0 : 1,
    ]);
    ok(['id' => $id]);
}

/** 身份 → 默认权限集合（新建账号按身份授予，可在权限设置中再调整） */
function identity_default_perms($identity)
{
    $map = [
        '管理员'   => '*',
        '录入人员' => 'dashboard,commission,commission:add,commission:add:submit,commission:add:valve,commission:list,commission:list:view,commission:list:add,commission:list:edit,commission:list:sign,commission:list:precheck',
        '检验人员' => 'dashboard,workorder,workorder:wait,workorder:wait:submit,workorder:inspection,workorder:inspection:result,workorder:all',
        '审核员'   => 'dashboard,commission,commission:list,commission:list:view,commission:list:approve,commission:list:reject,workorder,workorder:all',
        '打印人员' => 'dashboard,workorder,workorder:print,workorder:print:print,workorder:print:export,workorder:all,workorder:all:print,workorder:all:export',
        '录入审核' => 'dashboard,commission,commission:add,commission:add:submit,commission:add:valve,commission:list,commission:list:view,commission:list:add,commission:list:edit,commission:list:approve,commission:list:reject,workorder,workorder:wait,workorder:inspection',
    ];
    return isset($map[$identity]) ? $map[$identity] : 'dashboard';
}

function api_sysuser_update()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    if (!q_scalar('SELECT id FROM sys_user WHERE id = ? AND is_del = 0', [$id])) err('账号不存在');

    $data = [];
    foreach (['name', 'phone', 'unit_name', 'site', 'identity', 'permissions', 'sign', 'status'] as $snake) {
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
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    if ((int) $id === 1) err('内置管理员不可删除');
    q_update('sys_user', ['is_del' => 1, 'login_token' => '', 'token_expire' => 0], 'id = ?', [$id]);
    ok(null);
}

/** 启用 / 停用 */
function api_sysuser_status()
{
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
