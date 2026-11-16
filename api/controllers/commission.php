<?php
/** 委托单：列表 / 详情 / 新增 / 编辑 / 审核操作 */

function api_commission_list()
{
    [$page, $limit, $offset] = page_params();

    $where = 'is_del = 0';
    $params = [];

    $code = trim((string) param('code', ''));
    if ($code !== '') {
        $where .= ' AND code LIKE ?';
        $params[] = "%$code%";
    }
    $custId = (int) param('custId', 0);
    if ($custId) {
        $where .= ' AND cust_id = ?';
        $params[] = $custId;
    }
    $contact = trim((string) param('contact', ''));
    if ($contact !== '') {
        $where .= ' AND contact LIKE ?';
        $params[] = "%$contact%";
    }
    $status = trim((string) param('status', ''));
    if ($status !== '' && $status !== 'all') {
        $where .= ' AND status = ?';
        $params[] = $status;
    }
    $dateStart = trim((string) param('dateStart', ''));
    $dateEnd = trim((string) param('dateEnd', ''));
    if ($dateStart !== '') {
        $where .= ' AND create_time >= ?';
        $params[] = $dateStart . ' 00:00:00';
    }
    if ($dateEnd !== '') {
        $where .= ' AND create_time <= ?';
        $params[] = $dateEnd . ' 23:59:59';
    }

    $total = q_scalar("SELECT COUNT(*) FROM commission WHERE $where", $params);
    $rows = q_all(
        "SELECT * FROM commission WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset",
        $params
    );

    $list = [];
    foreach ($rows as $row) {
        $out = commission_row($row);
        $out['completedNum'] = q_scalar(
            'SELECT GROUP_CONCAT(order_code SEPARATOR "、") FROM workorder WHERE commission_id = ? AND is_del = 0',
            [$row['id']]
        ) ?: '';
        $out['valveCount'] = (int) q_scalar('SELECT COUNT(*) FROM commission_valve WHERE commission_id = ?', [$row['id']]);
        $out['reportDone'] = (int) q_scalar(
            "SELECT COUNT(*) FROM workorder WHERE commission_id = ? AND is_del = 0 AND status = 'done'",
            [$row['id']]
        );
        $list[] = $out;
    }

    ok(page_data($list, $total, $page, $limit));
}

function api_commission_detail()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM commission WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('委托单不存在');
    ok(commission_with_valves($row));
}

/** 新增委托单（含安全阀清单） */
function api_commission_add()
{
    $custId = (int) param('custId', 0);
    if (!$custId) err('请选择使用单位');
    $cust = q_one('SELECT * FROM unit WHERE id = ? AND is_del = 0', [$custId]);
    if (!$cust) err('使用单位不存在');

    $valves = param('valves', []);
    if (!is_array($valves) || !count($valves)) err('请至少添加一台安全阀');

    $user = current_user();
    $custName = trim((string) param('custName', '')) ?: $cust['name'];

    // 先生成委托单号（WT + 年份 + 序号）
    $nextId = (int) q_scalar('SELECT IFNULL(MAX(id), 0) + 1 FROM commission');
    $code = 'WT' . date('Y') . str_pad((string) ($nextId + 300), 4, '0', STR_PAD_LEFT);

    $data = [
        'code'              => $code,
        'cust_id'           => $custId,
        'cust_name'         => $custName,
        'address'           => (string) param('address', ''),
        'client'            => (string) param('client', ''),
        'client_tel'        => (string) param('clientTel', ''),
        'applicant'         => (string) param('applicant', '') ?: $custName,
        'cust_address'      => (string) param('custAddress', ''),
        'contact'           => (string) param('contact', ''),
        'contact_tel'       => (string) param('contactTel', ''),
        'exam_name'         => (string) param('examName', ''),
        'plan_receiver_time'=> (string) param('planReceiverTime', ''),
        'deliverer'         => (string) param('deliverer', ''),
        'delivery_time'     => (string) param('deliveryTime', ''),
        'status'            => 'pending',
        'create_by'         => $user['name'],
    ];

    $id = q_insert('commission', $data);

    foreach ($valves as $v) {
        q_insert('commission_valve', array_merge(['commission_id' => $id], valve_to_row($v)));
    }

    ok(['id' => $id, 'code' => $code]);
}

/** 编辑委托单 */
function api_commission_update()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM commission WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('委托单不存在');

    $allow = ['address', 'client', 'client_tel', 'applicant', 'cust_address', 'contact',
        'contact_tel', 'exam_name', 'plan_receiver_time', 'deliverer', 'delivery_time'];
    $data = [];
    foreach ($allow as $snake) {
        $camel = lcfirst(str_replace('_', '', ucwords($snake, '_')));
        if (param($snake) !== null) $data[$snake] = param($snake);
        if (param($camel) !== null) $data[$snake] = param($camel);
    }
    if ($data) {
        q_update('commission', $data, 'id = ?', [$id]);
    }

    // 若携带了新的阀门清单则整体替换
    $valves = param('valves', null);
    if (is_array($valves)) {
        db()->beginTransaction();
        try {
            q_exec('DELETE FROM commission_valve WHERE commission_id = ?', [$id]);
            foreach ($valves as $v) {
                q_insert('commission_valve', array_merge(['commission_id' => $id], valve_to_row($v)));
            }
            db()->commit();
        } catch (Exception $e) {
            db()->rollBack();
            throw $e;
        }
    }
    ok(null);
}

/**
 * 委托单操作
 * POST /commission/action  { id, action: approve|reject|precheck|pay|delete, rejectReason? }
 */
function api_commission_action()
{
    $id = (int) param('id', 0);
    $action = (string) param('action', '');
    if (!$id) err('缺少参数 id');

    $row = q_one('SELECT * FROM commission WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('委托单不存在');

    switch ($action) {
        case 'approve':
            if ($row['status'] !== 'pending') err('仅待审核的委托单可以通过');
            q_update('commission', ['status' => 'approved', 'reject_reason' => ''], 'id = ?', [$id]);
            // 为每台安全阀生成待检验工单（快照阀门数据）
            $valves = q_all('SELECT * FROM commission_valve WHERE commission_id = ?', [$id]);
            foreach ($valves as $v) {
                $woData = $v;
                unset($woData['id']);
                $woData['commission_id'] = $id;
                $woData['code'] = $row['code'];
                $woData['cust_name'] = $row['cust_name'];
                $woData['client'] = $row['client'];
                $woData['client_tel'] = $row['client_tel'];
                $woData['status'] = 'wait';
                $woData['update_time'] = date('Y-m-d H:i:s');
                $nextWo = (int) q_scalar('SELECT IFNULL(MAX(id), 0) + 1 FROM workorder');
                $woData['order_code'] = 'JY' . date('Y') . str_pad((string) $nextWo, 5, '0', STR_PAD_LEFT);
                q_insert('workorder', $woData);
            }
            ok(['workorderCount' => count($valves)]);
            break;

        case 'reject':
            $reason = trim((string) param('rejectReason', ''));
            if ($reason === '') err('请填写退回原因');
            q_update('commission', ['status' => 'rejected', 'reject_reason' => $reason], 'id = ?', [$id]);
            ok(null);
            break;

        case 'precheck':
            q_update('commission', ['precheck' => 1], 'id = ?', [$id]);
            ok(null);
            break;

        case 'pay':
            q_update('commission', ['paid' => 1], 'id = ?', [$id]);
            ok(null);
            break;

        case 'delete':
            q_update('commission', ['is_del' => 1], 'id = ?', [$id]);
            q_update('workorder', ['is_del' => 1], 'commission_id = ?', [$id]);
            ok(null);
            break;

        default:
            err('不支持的操作：' . $action);
    }
}
