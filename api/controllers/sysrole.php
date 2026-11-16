<?php
/** 角色权限设置（需 system:role 权限） */

function require_role_admin()
{
    $user = current_user();
    require_permission($user, 'system:role');
    return $user;
}

function role_row($r)
{
    return [
        'id'          => (int) $r['id'],
        'name'        => $r['name'],
        'label'       => $r['label'],
        'remark'      => $r['remark'],
        'permissions' => $r['permissions'] === '*'
            ? ['*']
            : array_values(array_filter(array_map('trim', explode(',', $r['permissions'] ?: '')))),
        'builtIn'     => (int) $r['built_in'] === 1,
        'userCount'   => (int) q_scalar('SELECT COUNT(*) FROM sys_user WHERE role_id = ? AND is_del = 0', [$r['id']]),
    ];
}

/** GET /system/role/list */
function api_sysrole_list()
{
    require_role_admin();
    $rows = q_all('SELECT * FROM sys_role WHERE is_del = 0 ORDER BY built_in DESC, id');
    ok(array_map('role_row', $rows));
}

/** POST /system/role/add {label, name, remark} */
function api_sysrole_add()
{
    require_role_admin();
    $label = trim((string) param('label', ''));
    $name = trim((string) param('name', ''));
    if ($label === '' || $name === '') err('角色名称和角色标识为必填');
    if (q_scalar('SELECT COUNT(*) FROM sys_role WHERE name = ? AND is_del = 0', [$name])) {
        err('角色标识已存在');
    }
    $id = q_insert('sys_role', [
        'name'        => $name,
        'label'       => $label,
        'remark'      => (string) param('remark', ''),
        'permissions' => 'dashboard',
        'built_in'    => 0,
    ]);
    ok(['id' => $id]);
}

/** POST /system/role/update {id, label?, name?, remark?} */
function api_sysrole_update()
{
    require_role_admin();
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM sys_role WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('角色不存在');

    $data = [];
    foreach (['label', 'name', 'remark'] as $key) {
        $v = param($key);
        if ($v !== null && trim((string) $v) !== '') $data[$key] = trim((string) $v);
    }
    if (isset($data['name']) && $data['name'] !== $row['name']) {
        if (q_scalar('SELECT COUNT(*) FROM sys_role WHERE name = ? AND id <> ? AND is_del = 0', [$data['name'], $id])) {
            err('角色标识已存在');
        }
    }
    if ($data) q_update('sys_role', $data, 'id = ?', [$id]);
    ok(null);
}

/** POST /system/role/permissions {id, permissions: [..] } 保存勾选的权限标识 */
function api_sysrole_permissions()
{
    require_role_admin();
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM sys_role WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('角色不存在');

    $perms = param('permissions', null);
    if (!is_array($perms)) err('权限参数格式错误');
    $perms = array_values(array_unique(array_filter(array_map('trim', $perms))));

    if ((int) $row['built_in'] === 1) {
        // 内置管理员角色固定为全部权限
        $value = '*';
    } else {
        $value = implode(',', $perms);
    }
    q_update('sys_role', ['permissions' => $value], 'id = ?', [$id]);

    // 权限变更后使关联账号的登录态失效，重新登录生效
    q_update('sys_user', ['login_token' => '', 'token_expire' => 0], 'role_id = ?', [$id]);
    ok(null);
}

/** POST /system/role/delete {id} */
function api_sysrole_delete()
{
    require_role_admin();
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM sys_role WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('角色不存在');
    if ((int) $row['built_in'] === 1) err('内置角色不可删除');

    q_update('sys_role', ['is_del' => 1], 'id = ?', [$id]);
    // 关联账号回退为使用自身 permissions
    q_update('sys_user', ['role_id' => 0], 'role_id = ?', [$id]);
    ok(null);
}
