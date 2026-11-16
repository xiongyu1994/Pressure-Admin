<?php
/** 安全阀库 */

function api_valve_list()
{
    $keyword = trim((string) param('keyword', ''));
    [$page, $limit, $offset] = page_params();

    $where = 'is_del = 0';
    $params = [];
    if ($keyword !== '') {
        $where .= ' AND (manu_no LIKE ? OR vavle_model LIKE ? OR id_no LIKE ?)';
        $params = ["%$keyword%", "%$keyword%", "%$keyword%"];
    }

    $total = q_scalar("SELECT COUNT(*) FROM safety_valve WHERE $where", $params);
    $rows = q_all(
        "SELECT * FROM safety_valve WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset",
        $params
    );

    ok(page_data(array_map('valve_row', $rows), $total, $page, $limit));
}

function api_valve_add()
{
    $row = valve_to_row(input());
    if (empty($row['set_press']) || empty($row['diameter_nominal']) || empty($row['tightness_press'])) {
        err('要求整定压力、公称通径、密封试验压力为必填');
    }
    // 支持批量提交：count
    $count = max(1, min(20, (int) param('count', 1)));
    $ids = [];
    for ($i = 0; $i < $count; $i++) {
        $ids[] = q_insert('safety_valve', $row);
    }
    ok(['ids' => $ids, 'count' => count($ids)]);
}

function api_valve_delete()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    q_update('safety_valve', ['is_del' => 1], 'id = ?', [$id]);
    ok(null);
}
