<?php
/** PDO 数据库封装 */

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** 查询多行 */
function q_all($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** 查询单行，无结果返回 null */
function q_one($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** 查询单个值 */
function q_scalar($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

/** 执行写语句，返回受影响行数 */
function q_exec($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** 插入，返回自增ID */
function q_insert($table, $data)
{
    $cols = array_keys($data);
    $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" .
        implode(',', array_fill(0, count($cols), '?')) . ")";
    $st = db()->prepare($sql);
    $st->execute(array_values($data));
    return (int) db()->lastInsertId();
}

/** 更新，返回受影响行数 */
function q_update($table, $data, $whereSql, $whereParams = [])
{
    $sets = [];
    $params = [];
    foreach ($data as $k => $v) {
        $sets[] = "`$k` = ?";
        $params[] = $v;
    }
    $params = array_merge($params, $whereParams);
    $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE $whereSql";
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}
