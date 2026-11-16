<?php
/** 统一响应、输入读取、CORS */

function cors()
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function json_out($arr)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

/** 成功响应 */
function ok($data = null)
{
    json_out(['code' => 0, 'message' => 'ok', 'data' => $data]);
}

/** 失败响应 */
function err($message, $code = 1)
{
    json_out(['code' => $code, 'message' => $message, 'data' => null]);
}

/** 合并读取输入：GET + POST + JSON body（前端 axios/fetch 传 JSON 均可） */
function input()
{
    static $in = null;
    if ($in === null) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) $json = [];
        $in = array_merge($_GET, $_POST, $json);
    }
    return $in;
}

/** 取参数，支持 camelCase 与 snake_case，默认值 */
function param($key, $default = null)
{
    $in = input();
    $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
    if (array_key_exists($key, $in)) return $in[$key];
    if (array_key_exists($snake, $in)) return $in[$snake];
    return $default;
}

/** 分页参数 */
function page_params()
{
    $page  = max(1, (int) param('page', 1));
    $limit = min(2000, max(1, (int) param('limit', 15)));
    return [$page, $limit, ($page - 1) * $limit];
}

/** 分页响应结构 { list, total, page, limit } */
function page_data($list, $total, $page, $limit)
{
    return ['list' => $list, 'total' => (int) $total, 'page' => $page, 'limit' => $limit];
}
