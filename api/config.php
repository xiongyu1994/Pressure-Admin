<?php
/**
 * 数据库配置 —— 按你的 phpstudy 实际情况修改
 * 默认：MySQL root / root，端口 3306
 * 账号密码可在 phpstudy「数据库」页面查看/修改
 */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'inspection');
define('DB_USER', 'root');
define('DB_PASS', '123456');
define('DB_CHARSET', 'utf8mb4');

/** 登录 token 有效期（秒）：7 天 */
define('TOKEN_TTL', 7 * 86400);

/** 调试模式：true 时接口异常会返回详细错误，联调结束后改为 false */
define('DEBUG', true);
