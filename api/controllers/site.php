<?php
/** 站点管理（权限由 index.php 路由表统一校验：system:site / system:site:*） */

function site_row($r)
{
    return [
        'id'        => (int) $r['id'],
        'parentId'  => (int) $r['parent_id'],
        'parentName'=> isset($r['parent_name']) ? (string) $r['parent_name'] : '',
        'name'      => $r['name'],
        'manager'   => (string) $r['manager'],
        'tel'       => (string) $r['tel'],
        'address'   => (string) $r['address'],
        'status'    => (int) $r['status'] === 1,
        'createTime'=> $r['create_time'],
    ];
}

/** GET /system/site/list {name?, manager?, tel?, status?, page?, limit?} */
function api_site_list()
{
    $where = ['s.is_del = 0'];
    $args = [];
    $name = trim((string) param('name', ''));
    $manager = trim((string) param('manager', ''));
    $tel = trim((string) param('tel', ''));
    $status = param('status');
    if ($name !== '') {
        $where[] = 's.name LIKE ?';
        $args[] = '%' . $name . '%';
    }
    if ($manager !== '') {
        $where[] = 's.manager LIKE ?';
        $args[] = '%' . $manager . '%';
    }
    if ($tel !== '') {
        $where[] = 's.tel LIKE ?';
        $args[] = '%' . $tel . '%';
    }
    if ($status !== null && $status !== '') {
        $where[] = 's.status = ?';
        $args[] = (int) $status;
    }
    $cond = implode(' AND ', $where);
    $sql = 'SELECT s.*, p.name AS parent_name FROM site s LEFT JOIN site p ON p.id = s.parent_id AND p.is_del = 0 WHERE ' . $cond;
    $total = (int) q_scalar('SELECT COUNT(*) FROM site s WHERE ' . $cond, $args);
    $page = max(1, (int) param('page', 1));
    $limit = max(1, min(200, (int) param('limit', 15)));
    $rows = q_all($sql . ' ORDER BY s.id LIMIT ' . (($page - 1) * $limit) . ',' . $limit, $args);
    ok(page_data(array_map('site_row', $rows), $total, $page, $limit));
}

/** POST /system/site/add {name, parentId?, manager?, tel?, address?, status?} */
function api_site_add()
{
    $name = trim((string) param('name', ''));
    if ($name === '') err('请输入站点名称');
    $id = q_insert('site', [
        'parent_id' => (int) param('parentId', 0),
        'name'      => $name,
        'manager'   => (string) param('manager', ''),
        'tel'       => (string) param('tel', ''),
        'address'   => (string) param('address', ''),
        'status'    => (int) param('status', 1) === 0 ? 0 : 1,
    ]);
    ok(['id' => $id]);
}

/** POST /system/site/update {id, ...} */
function api_site_update()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    if (!q_scalar('SELECT id FROM site WHERE id = ? AND is_del = 0', [$id])) err('站点不存在');
    $data = [];
    foreach (['parentId' => 'parent_id', 'name' => 'name', 'manager' => 'manager', 'tel' => 'tel', 'address' => 'address', 'status' => 'status'] as $camel => $snake) {
        $v = param($camel);
        if ($v !== null) $data[$snake] = $snake === 'parent_id' ? (int) $v : $v;
    }
    if (isset($data['parent_id']) && (int) $data['parent_id'] === $id) err('上级站点不能选自己');
    if (!$data) err('没有需要修改的字段');
    q_update('site', $data, 'id = ?', [$id]);
    ok(null);
}

/** POST /system/site/status {id, status} */
function api_site_status()
{
    $id = (int) param('id', 0);
    $status = (int) param('status', 1);
    if (!$id) err('缺少参数 id');
    q_update('site', ['status' => $status === 0 ? 0 : 1], 'id = ?', [$id]);
    ok(null);
}

/** POST /system/site/delete {id} */
function api_site_delete()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    if (q_scalar('SELECT COUNT(*) FROM site WHERE parent_id = ? AND is_del = 0', [$id])) {
        err('该站点存在下级站点，请先删除下级站点');
    }
    q_update('site', ['is_del' => 1], 'id = ?', [$id]);
    ok(null);
}
