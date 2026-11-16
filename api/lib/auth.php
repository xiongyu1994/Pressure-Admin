<?php
/** 登录鉴权：token 存于 sys_user.login_token，前端通过
 *  请求头 Authorization: Bearer <token> 或参数 token 传递 */

function generate_token()
{
    return md5(uniqid(mt_rand(), true) . microtime());
}

/**
 * 获取当前登录用户
 * @param bool $required 是否强制登录
 * @return array|null
 */
function current_user($required = true)
{
    $token = '';

    // 1. Authorization: Bearer xxx
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = $m[1];
        }
    }
    // 2. 自定义 Token 头
    if (!$token && !empty($_SERVER['HTTP_TOKEN'])) {
        $token = trim($_SERVER['HTTP_TOKEN']);
    }
    // 3. 参数 token
    if (!$token) {
        $t = param('token', '');
        if ($t) $token = $t;
    }
    // 4. Cookie
    if (!$token && !empty($_COOKIE['exam_token'])) {
        $token = $_COOKIE['exam_token'];
    }

    if (!$token) {
        if ($required) err('未登录或登录已过期', 401);
        return null;
    }

    $user = q_one(
        'SELECT * FROM sys_user WHERE login_token = ? AND is_del = 0 AND status = 1',
        [$token]
    );
    if (!$user || (int) $user['token_expire'] < time()) {
        if ($required) err('登录已过期，请重新登录', 401);
        return null;
    }
    unset($user['password']);
    return $user;
}

/** 权限校验：permissions 含 * 或指定标识 */
function check_permission($user, $perm)
{
    if (!$perm) return true;
    $perms = array_map('trim', explode(',', $user['permissions'] ?: ''));
    return in_array('*', $perms, true) || in_array($perm, $perms, true);
}

/** 要求具备某权限，否则 403 */
function require_permission($user, $perm)
{
    if (!check_permission($user, $perm)) {
        err('没有操作权限：' . $perm, 403);
    }
}

/** 用户权限数组：优先取关联角色的权限，未关联角色时用账号自身 permissions */
function user_permissions($user)
{
    if (trim($user['permissions']) === '*') return ['*'];

    if (!empty($user['role_id'])) {
        $role = q_one('SELECT permissions FROM sys_role WHERE id = ? AND is_del = 0', [$user['role_id']]);
        if ($role && trim($role['permissions']) !== '') {
            if (trim($role['permissions']) === '*') return ['*'];
            return array_values(array_filter(array_map('trim', explode(',', $role['permissions']))));
        }
    }

    return array_values(array_filter(array_map('trim', explode(',', $user['permissions'] ?: ''))));
}
