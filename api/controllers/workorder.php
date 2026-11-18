<?php
/** 检验工单：按状态列表 / 详情 / 提交 / 检验结果 / 打印 */

/** 工单查询基语句（联结果表） */
function workorder_select_sql()
{
    return 'SELECT w.*,
        r.id AS r_id, r.order_code AS r_order_code, r.tightness_press AS r_tightness_press,
        r.set_press AS r_set_press, r.test_media AS r_test_media, r.test_temprature AS r_test_temprature,
        r.parity AS r_parity, r.visual_examination AS r_visual_examination,
        r.disassembly_inspection AS r_disassembly_inspection, r.note AS r_note,
        r.pressure_gauge_num AS r_pressure_gauge_num, r.pressure_gauge_lifespan AS r_pressure_gauge_lifespan,
        r.pressure_gauge_num2 AS r_pressure_gauge_num2, r.pressure_gauge_lifespan2 AS r_pressure_gauge_lifespan2,
        r.pressure_gauge_accuracy AS r_pressure_gauge_accuracy, r.pressure_gauge_range AS r_pressure_gauge_range,
        r.device_model AS r_device_model, r.device_sn AS r_device_sn,
        r.testing_pressure1 AS r_testing_pressure1, r.testing_pressure2 AS r_testing_pressure2,
        r.testing_pressure3 AS r_testing_pressure3, r.test_status AS r_test_status
        FROM workorder w
        LEFT JOIN workorder_result r ON r.workorder_id = w.id ';
}

/** GET /workorder/list?status=wait&code=&orderCode=&custName=&manuNo=&dateStart=&dateEnd=&page=&limit= */
function api_workorder_list()
{
    [$page, $limit, $offset] = page_params();

    $status = trim((string) param('status', ''));
    $where = 'w.is_del = 0';
    $params = [];

    if ($status !== '' && $status !== 'all') {
        $where .= ' AND w.status = ?';
        $params[] = $status;
    }
    $code = trim((string) param('code', ''));
    if ($code !== '') {
        $where .= ' AND w.code LIKE ?';
        $params[] = "%$code%";
    }
    $orderCode = trim((string) param('orderCode', ''));
    if ($orderCode !== '') {
        $where .= ' AND w.order_code LIKE ?';
        $params[] = "%$orderCode%";
    }
    $custName = trim((string) param('custName', ''));
    if ($custName !== '') {
        $where .= ' AND w.cust_name LIKE ?';
        $params[] = "%$custName%";
    }
    $manuNo = trim((string) param('manuNo', ''));
    if ($manuNo !== '') {
        $where .= ' AND w.manu_no LIKE ?';
        $params[] = "%$manuNo%";
    }
    $idNo = trim((string) param('idNo', ''));
    if ($idNo !== '') {
        $where .= ' AND w.id_no LIKE ?';
        $params[] = "%$idNo%";
    }
    $dateStart = trim((string) param('dateStart', ''));
    $dateEnd = trim((string) param('dateEnd', ''));
    if ($dateStart !== '') {
        $where .= ' AND w.create_time >= ?';
        $params[] = $dateStart . ' 00:00:00';
    }
    if ($dateEnd !== '') {
        $where .= ' AND w.create_time <= ?';
        $params[] = $dateEnd . ' 23:59:59';
    }

    // 排序（白名单）
    $orderby = 'w.update_time DESC';
    $sortField = trim((string) param('sortField', ''));
    $sortOrder = strtolower((string) param('sortOrder', 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $sortMap = [
        'orderCode' => 'w.order_code', 'setPress' => 'w.set_press', 'updateTime' => 'w.update_time',
        'code' => 'w.code', 'manuNo' => 'w.manu_no', 'createTime' => 'w.create_time',
    ];
    if ($sortField && isset($sortMap[$sortField])) {
        $orderby = $sortMap[$sortField] . ' ' . $sortOrder;
    }

    $total = q_scalar("SELECT COUNT(*) FROM workorder w WHERE $where", $params);
    $rows = q_all(
        workorder_select_sql() . "WHERE $where ORDER BY $orderby LIMIT $limit OFFSET $offset",
        $params
    );

    ok(page_data(array_map('workorder_row', $rows), $total, $page, $limit));
}

function api_workorder_detail()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one(workorder_select_sql() . 'WHERE w.id = ? AND w.is_del = 0', [$id]);
    if (!$row) err('工单不存在');
    ok(workorder_row($row));
}

/** 待检验 → 检验中 */
function api_workorder_submit()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM workorder WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('工单不存在');
    if ($row['status'] !== 'wait') err('仅待检验状态的工单可以提交');
    q_update('workorder', ['status' => 'inspection', 'update_time' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    ok(null);
}

/** 修改校验编号（含校验编号2/SN/sn_code）POST /workorder/update_code { id, orderCode?, orderCode2?, sn?, snCode? } */
function api_workorder_update_code()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT id FROM workorder WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('工单不存在');

    $data = [];
    foreach (['orderCode' => 'order_code', 'orderCode2' => 'order_code2', 'sn' => 'sn', 'snCode' => 'sn_code'] as $camel => $snake) {
        if (param($camel) !== null) $data[$snake] = trim((string) param($camel));
    }
    if (!$data) err('没有需要修改的字段');
    if (isset($data['order_code']) && $data['order_code'] === '') err('校验编号不能为空');
    q_update('workorder', $data, 'id = ?', [$id]);
    ok(null);
}

/** 校验退回 → 重新提交到待检验 */
function api_workorder_resubmit()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM workorder WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('工单不存在');
    if ($row['status'] !== 'rejected') err('仅校验退回的工单可以重新提交');
    q_update('workorder', ['status' => 'wait', 'reject_reason' => '', 'update_time' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    ok(null);
}

/** 检验中 → 提交检验结果，进入待审核 */
function api_workorder_submit_result()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM workorder WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('工单不存在');
    if ($row['status'] !== 'inspection') err('仅检验中的工单可以提交结果');

    $in = input();
    $resultFields = [
        'order_code', 'tightness_press', 'set_press', 'test_media', 'test_temprature',
        'parity', 'visual_examination', 'disassembly_inspection', 'note',
        'pressure_gauge_num', 'pressure_gauge_lifespan', 'pressure_gauge_num2',
        'pressure_gauge_lifespan2', 'pressure_gauge_accuracy', 'pressure_gauge_range',
        'device_model', 'device_sn', 'testing_pressure1', 'testing_pressure2',
        'testing_pressure3', 'test_status',
    ];

    $data = [];
    foreach ($resultFields as $snake) {
        $camel = lcfirst(str_replace('_', '', ucwords($snake, '_')));
        if (isset($in[$camel])) $data[$snake] = $in[$camel];
        elseif (isset($in[$snake])) $data[$snake] = $in[$snake];
    }
    if (empty($data['test_status'])) err('请选择校验结果');
    $data['workorder_id'] = $id;
    $data['order_code'] = $row['order_code'];

    $inspector = (string) param('inspector', '');
    $inspectorTime = (string) param('inspectorTime', '') ?: date('Y-m-d H:i:s');
    if ($inspector === '') err('请选择校验员');

    db()->beginTransaction();
    try {
        $exists = q_scalar('SELECT id FROM workorder_result WHERE workorder_id = ?', [$id]);
        if ($exists) {
            unset($data['workorder_id']);
            q_update('workorder_result', $data, 'workorder_id = ?', [$id]);
        } else {
            q_insert('workorder_result', $data);
        }
        q_update('workorder', [
            'status'         => 'audit',
            'inspector'      => $inspector,
            'inspector_time' => $inspectorTime,
            'update_time'    => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        db()->commit();
    } catch (Exception $e) {
        db()->rollBack();
        throw $e;
    }
    ok(null);
}

/** 待打印 → 打印后已完成；支持批量 { ids: [..] } */
function api_workorder_print()
{
    $in = input();
    $ids = isset($in['ids']) && is_array($in['ids']) ? array_map('intval', $in['ids']) : [];
    if (!$ids && (int) param('id', 0)) $ids = [(int) param('id')];
    if (!$ids) err('缺少参数 ids');

    $marks = implode(',', array_fill(0, count($ids), '?'));
    $count = q_exec(
        "UPDATE workorder SET status='done', update_time=? WHERE id IN ($marks) AND status='print' AND is_del=0",
        array_merge([date('Y-m-d H:i:s')], $ids)
    );
    ok(['updated' => (int) $count]);
}

/**
 * 审核接口（待审核页）：通过 → 待审批；退回 → 校验退回。
 * POST /workorder/audit { ids: [..], pass: true|false, reason: '退回原因' }
 */
function api_workorder_audit()
{
    workorder_gate('audit', 'approval');
}

/**
 * 审批接口（待审批页）：审批通过 → 待打印；退回 → 校验退回。
 * POST /workorder/approve { ids: [..], pass: true|false, reason: '退回原因' }
 */
function api_workorder_approve()
{
    workorder_gate('approval', 'print');
}

/** 通用状态闸门：$from 状态 → 通过 $to / 退回 rejected */
function workorder_gate($from, $to)
{
    $in  = input();
    $ids = isset($in['ids']) && is_array($in['ids']) ? array_map('intval', $in['ids']) : [];
    if (!$ids && (int) param('id', 0)) $ids = [(int) param('id')];
    if (!$ids) err('缺少参数 ids');

    $pass   = param('pass', null);
    $pass   = $pass === null ? true : (bool) $pass;
    $reason = trim((string) param('reason', ''));

    if ($pass) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $count = q_exec(
            "UPDATE workorder SET status=?, reject_reason='', update_time=? WHERE id IN ($marks) AND status=? AND is_del=0",
            array_merge([$to, date('Y-m-d H:i:s')], $ids, [$from])
        );
        ok(['updated' => (int) $count]);
    } else {
        if ($reason === '') err('请填写退回原因');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $count = q_exec(
            "UPDATE workorder SET status='rejected', reject_reason=?, update_time=? WHERE id IN ($marks) AND status=? AND is_del=0",
            array_merge([$reason, date('Y-m-d H:i:s')], $ids, [$from])
        );
        ok(['updated' => (int) $count]);
    }
}
