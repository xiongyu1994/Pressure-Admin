<?php
/** 角色权限设置（权限由 index.php 路由表统一校验：system:role / system:role:*） */

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
    // 内置角色按固定顺序展示：管理员、录入人员、检验人员、审核员、打印人员、录入审核
    $order = "FIELD(label,'管理员','录入人员','检验人员','审核员','打印人员','录入审核')";
    $rows = q_all('SELECT * FROM sys_role WHERE is_del = 0 ORDER BY built_in DESC, IF(' . $order . ' = 0, 99, ' . $order . '), id');
    ok(array_map('role_row', $rows));
}

/** POST /system/role/add {label, name, remark} */
function api_sysrole_add()
{
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
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM sys_role WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('角色不存在');

    $perms = param('permissions', null);
    if (!is_array($perms)) err('权限参数格式错误');
    $perms = array_values(array_unique(array_filter(array_map('trim', $perms))));

    // 按前端勾选结果保存（内置角色也允许取消勾选部分按钮权限，不再强制 '*'）
    $value = implode(',', $perms);
    q_update('sys_role', ['permissions' => $value], 'id = ?', [$id]);

    // 权限变更后使该角色其它关联账号的登录态失效，重新登录生效；
    // 排除当前操作人自己，否则保存成功的同时会把自己的 token 清空，后续请求全部 401
    $me = current_user(false);
    $meId = $me ? (int) $me['id'] : 0;
    q_update('sys_user', ['login_token' => '', 'token_expire' => 0], 'role_id = ? AND id <> ?', [$id, $meId]);
    ok(null);
}

/** POST /system/role/delete {id} */
function api_sysrole_delete()
{
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
