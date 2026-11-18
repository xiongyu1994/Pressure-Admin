<?php
/** 模版管理（权限由 index.php 路由表统一校验：system:tpl / system:tpl:*） */

const TPL_TYPES = ['校验报告', '校验记录', '台账', '委托单', '收费单', '委托单-未确认', '报告封面', '脱脂报告', '脱脂记录'];

function tpl_row($r)
{
    return [
        'id'      => (int) $r['id'],
        'siteId'  => (int) $r['site_id'],
        'siteName'=> isset($r['site_name']) ? (string) $r['site_name'] : '',
        'type'    => (string) $r['type'],
        'name'    => $r['name'],
        'fileName'   => isset($r['file_name']) ? (string) $r['file_name'] : '',
        // 相对路径（如 uploads/tpl/tpl_check_report.docx），前端拼 API_BASE 直接下载
        'filePath'   => isset($r['file_path']) ? (string) $r['file_path'] : '',
        'status'  => (int) $r['status'] === 1,
        'createTime' => $r['create_time'],
    ];
}

/** GET /system/tpl/list {siteId?, type?, page?, limit?} */
function api_tpl_list()
{
    $where = ['t.is_del = 0'];
    $args = [];
    $siteId = (int) param('siteId', 0);
    $type = trim((string) param('type', ''));
    if ($siteId) {
        $where[] = 't.site_id = ?';
        $args[] = $siteId;
    }
    if ($type !== '') {
        $where[] = 't.type = ?';
        $args[] = $type;
    }
    $cond = implode(' AND ', $where);
    $sql = 'SELECT t.*, s.name AS site_name FROM tpl t LEFT JOIN site s ON s.id = t.site_id AND s.is_del = 0 WHERE ' . $cond;
    $total = (int) q_scalar('SELECT COUNT(*) FROM tpl t WHERE ' . $cond, $args);
    $page = max(1, (int) param('page', 1));
    $limit = max(1, min(200, (int) param('limit', 15)));
    $rows = q_all($sql . ' ORDER BY t.id LIMIT ' . (($page - 1) * $limit) . ',' . $limit, $args);
    ok(page_data(array_map('tpl_row', $rows), $total, $page, $limit));
}

/** GET /system/tpl/types —— 模版类型下拉选项 */
function api_tpl_types()
{
    ok(TPL_TYPES);
}

/** POST /system/tpl/add {siteId, type, name, status?} */
function api_tpl_add()
{
    $siteId = (int) param('siteId', 0);
    $type = trim((string) param('type', ''));
    $name = trim((string) param('name', ''));
    if (!$siteId) err('请选择站点');
    if (!in_array($type, TPL_TYPES, true)) err('模版类型不合法');
    if ($name === '') err('请输入模板名称');
    $id = q_insert('tpl', [
        'site_id' => $siteId,
        'type'    => $type,
        'name'    => $name,
        'file_name' => trim((string) param('fileName', '')),
        'file_path' => trim((string) param('filePath', '')),
        'status'  => (int) param('status', 1) === 0 ? 0 : 1,
    ]);
    ok(['id' => $id]);
}

/** POST /system/tpl/update {id, ...} */
function api_tpl_update()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    if (!q_scalar('SELECT id FROM tpl WHERE id = ? AND is_del = 0', [$id])) err('模版不存在');
    $data = [];
    foreach (['siteId' => 'site_id', 'type' => 'type', 'name' => 'name', 'status' => 'status', 'fileName' => 'file_name', 'filePath' => 'file_path'] as $camel => $snake) {
        $v = param($camel);
        if ($v !== null) $data[$snake] = $snake === 'site_id' ? (int) $v : $v;
    }
    if (isset($data['type']) && !in_array($data['type'], TPL_TYPES, true)) err('模版类型不合法');
    if (!$data) err('没有需要修改的字段');
    q_update('tpl', $data, 'id = ?', [$id]);
    ok(null);
}

/** POST /system/tpl/status {id, status} */
function api_tpl_status()
{
    $id = (int) param('id', 0);
    $status = (int) param('status', 1);
    if (!$id) err('缺少参数 id');
    q_update('tpl', ['status' => $status === 0 ? 0 : 1], 'id = ?', [$id]);
    ok(null);
}

/** POST /system/tpl/delete {id} */
function api_tpl_delete()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    q_update('tpl', ['is_del' => 1], 'id = ?', [$id]);
    ok(null);
}
