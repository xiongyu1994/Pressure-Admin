<?php
/** 收费列表（委托单管理 → 收费列表）：委托单收费信息查看与登记 */

/** 委托单行 → 收费列表输出（含工单报告完成进度） */
function fee_row($r)
{
    return [
        'id'            => (int) $r['id'],
        'code'          => $r['code'],
        'custName'      => $r['cust_name'],
        'address'       => $r['address'],
        'client'        => $r['client'],
        'clientTel'     => $r['client_tel'],
        'applicant'     => $r['applicant'],
        'custAddress'   => $r['cust_address'],
        'contact'       => $r['contact'],
        'contactTel'    => $r['contact_tel'],
        'status'        => $r['status'],
        'paid'          => (int) $r['paid'] === 1,
        'totalPrice'    => (float) $r['total_price'],
        'repairPrice'   => (float) $r['repair_price'],
        'sellerPrice'   => (float) $r['seller_price'],
        'payableAmount' => (float) $r['payable_amount'],
        'actualAmount'  => (float) $r['actual_amount'],
        'paymentMethod' => $r['payment_method'],
        'paymentDate'   => $r['payment_date'],
        'billingUnit'   => $r['billing_unit'],
        'invoiceNumber' => $r['invoice_number'],
        'invoiceDate'   => $r['invoice_date'],
        'seller'        => $r['seller'],
        'overbilledInspection' => (float) $r['overbilled_inspection'],
        'overbilledSeller'     => (float) $r['overbilled_seller'],
        'feeNote'       => $r['fee_note'],
        'createBy'      => $r['create_by'],
        'createTime'    => $r['create_time'],
        'signImg'       => isset($r['sign_img']) ? (string) $r['sign_img'] : '',
        'signTime'      => isset($r['sign_time']) ? (string) $r['sign_time'] : '',
        // 报告完成状态：已完成工单数 / 总工单数
        'reportDone'    => isset($r['done_count']) ? (int) $r['done_count'] : 0,
        'reportTotal'   => isset($r['total_count']) ? (int) $r['total_count'] : 0,
    ];
}

const FEE_SELECT = 'c.*, (SELECT COUNT(*) FROM workorder w WHERE w.commission_id = c.id AND w.is_del = 0) AS total_count,
    (SELECT COUNT(*) FROM workorder w WHERE w.commission_id = c.id AND w.is_del = 0 AND w.status = "done") AS done_count';

/** GET /fee/list {code?, custName?, applicant?, createBy?, status?, fufei?, timeStart?, timeEnd?, page?, limit?} */
function api_fee_list()
{
    $where = ['c.is_del = 0'];
    $args = [];

    $code = trim((string) param('code', ''));
    if ($code !== '') { $where[] = 'c.code LIKE ?'; $args[] = '%' . $code . '%'; }

    $custName = trim((string) param('custName', ''));
    if ($custName !== '') { $where[] = 'c.cust_name LIKE ?'; $args[] = '%' . $custName . '%'; }

    $applicant = trim((string) param('applicant', ''));
    if ($applicant !== '') { $where[] = 'c.applicant LIKE ?'; $args[] = '%' . $applicant . '%'; }

    $createBy = trim((string) param('createBy', ''));
    if ($createBy !== '') { $where[] = 'c.create_by LIKE ?'; $args[] = '%' . $createBy . '%'; }

    $status = trim((string) param('status', ''));
    if ($status !== '') { $where[] = 'c.status = ?'; $args[] = $status; }

    $fufei = param('fufei');
    if ($fufei !== null && $fufei !== '') { $where[] = 'c.paid = ?'; $args[] = (int) $fufei; }

    $timeStart = trim((string) param('timeStart', ''));
    if ($timeStart !== '') { $where[] = 'c.create_time >= ?'; $args[] = $timeStart . ' 00:00:00'; }

    $timeEnd = trim((string) param('timeEnd', ''));
    if ($timeEnd !== '') { $where[] = 'c.create_time <= ?'; $args[] = $timeEnd . ' 23:59:59'; }

    $cond = implode(' AND ', $where);
    $total = (int) q_scalar("SELECT COUNT(*) FROM commission c WHERE $cond", $args);
    [$page, $limit, $offset] = page_params();

    $rows = q_all(
        "SELECT " . FEE_SELECT . " FROM commission c WHERE $cond ORDER BY c.id DESC LIMIT $offset,$limit",
        $args
    );
    $list = array_map('fee_row', $rows);

    // 合计：总校验费 / 总维修费 / 应开 / 实开
    $sums = q_one("SELECT COALESCE(SUM(total_price),0) AS st, COALESCE(SUM(repair_price),0) AS sr,
        COALESCE(SUM(payable_amount),0) AS sp, COALESCE(SUM(actual_amount),0) AS sa
        FROM commission c WHERE $cond", $args);

    ok([
        'list'  => $list,
        'total' => $total,
        'page'  => $page,
        'limit' => $limit,
        'sums'  => [
            'totalPrice'    => (float) $sums['st'],
            'repairPrice'   => (float) $sums['sr'],
            'payableAmount' => (float) $sums['sp'],
            'actualAmount'  => (float) $sums['sa'],
        ],
    ]);
}

/** GET /fee/detail?id= —— 收费查看（委托单信息 + 阀门清单 + 收费字段） */
function api_fee_detail()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT ' . FEE_SELECT . ' FROM commission c WHERE c.id = ? AND c.is_del = 0', [$id]);
    if (!$row) err('委托单不存在');
    $out = fee_row($row);
    // 阀门清单（含每台工单状态与型号参数）
    $out['valves'] = q_all(
        'SELECT cv.id, cv.tag_no, cv.id_no, cv.vavle_model, cv.set_press, cv.diameter_nominal, cv.press_nominal,
                w.order_code AS woOrderCode, w.status AS woStatus
         FROM commission_valve cv
         LEFT JOIN workorder w ON w.valve_id = cv.id AND w.is_del = 0
         WHERE cv.commission_id = ?',
        [$id]
    );
    ok($out);
}

/** POST /fee/save {id, ...收费字段, markPaid?} —— 登记收费 */
function api_fee_save()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    if (!q_scalar('SELECT id FROM commission WHERE id = ? AND is_del = 0', [$id])) err('委托单不存在');

    $data = [];
    $map = [
        'totalPrice' => 'total_price', 'repairPrice' => 'repair_price', 'sellerPrice' => 'seller_price',
        'payableAmount' => 'payable_amount', 'actualAmount' => 'actual_amount',
        'paymentMethod' => 'payment_method', 'paymentDate' => 'payment_date',
        'billingUnit' => 'billing_unit', 'invoiceNumber' => 'invoice_number', 'invoiceDate' => 'invoice_date',
        'seller' => 'seller', 'overbilledInspection' => 'overbilled_inspection',
        'overbilledSeller' => 'overbilled_seller', 'feeNote' => 'fee_note',
    ];
    foreach ($map as $camel => $snake) {
        $v = param($camel);
        if ($v === null) continue;
        if (in_array($snake, ['payment_date', 'invoice_date'], true)) {
            $data[$snake] = $v !== '' ? $v : null;
        } elseif (in_array($snake, ['total_price', 'repair_price', 'seller_price', 'payable_amount', 'actual_amount', 'overbilled_inspection', 'overbilled_seller'], true)) {
            $data[$snake] = (float) $v;
        } else {
            $data[$snake] = (string) $v;
        }
    }
    if (param('markPaid') !== null) {
        $data['paid'] = param('markPaid') ? 1 : 0;
    }
    if (!$data) err('没有需要保存的字段');
    q_update('commission', $data, 'id = ?', [$id]);
    ok(null);
}

/** POST /fee/reset {id} —— 重置为待收费 */
function api_fee_reset()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    q_update('commission', ['paid' => 0], 'id = ?', [$id]);
    ok(null);
}
