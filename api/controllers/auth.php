<?php
/** 登录 / 登出 / 用户信息 */

function api_login()
{
    $account  = trim((string) param('account', ''));
    $password = (string) param('password', '');

    if ($account === '' || $password === '') {
        err('请输入账号和密码');
    }

    $user = q_one('SELECT * FROM sys_user WHERE account = ? AND is_del = 0', [$account]);
    if (!$user || $user['password'] !== md5($password)) {
        err('账号或密码错误');
    }
    if ((int) $user['status'] !== 1) {
        err('账号已被停用，请联系管理员');
    }

    $token = generate_token();
    q_update('sys_user', [
        'login_token'  => $token,
        'token_expire' => time() + TOKEN_TTL,
        'last_time'    => date('Y-m-d H:i:s'),
        'last_ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
    ], 'id = ?', [$user['id']]);

    ok([
        'token'       => $token,
        'expiresIn'   => TOKEN_TTL,
        'id'          => (int) $user['id'],
        'name'        => $user['name'],
        'account'     => $user['account'],
        'unitName'    => $user['unit_name'],
        'identity'    => $user['identity'],
        'permissions' => user_permissions($user),
    ]);
}

function api_logout()
{
    $user = current_user(false);
    if ($user) {
        q_update('sys_user', ['login_token' => '', 'token_expire' => 0], 'id = ?', [$user['id']]);
    }
    ok(null);
}

function api_user_info()
{
    $user = current_user();
    ok([
        'id'          => (int) $user['id'],
        'name'        => $user['name'],
        'account'     => $user['account'],
        'phone'       => $user['phone'],
        'unitName'    => $user['unit_name'],
        'identity'    => $user['identity'],
        'sign'        => $user['sign'],
        'lastTime'    => $user['last_time'],
        'lastIp'      => $user['last_ip'],
        'permissions' => user_permissions($user),
    ]);
}
