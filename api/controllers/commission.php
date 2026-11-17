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

    // 各委托单的待检阀门数（未出检验结果的阀门，含待审核/待检验/检验中/退回）
    $pendingMap = [];
    $pendingRows = q_all(
        'SELECT t.commission_id, COUNT(*) AS cnt FROM ' . pending_valve_subquery() . "
         WHERE t.valve_status IN ('pending','wait','inspection','rejected')
         GROUP BY t.commission_id"
    );
    foreach ($pendingRows as $pr) {
        $pendingMap[(int) $pr['commission_id']] = (int) $pr['cnt'];
    }

    $list = [];
    foreach ($rows as $row) {
        $out = commission_row($row);
        // 校验编号：取该委托单全部工单编号的最小-最大区间，如「20261106 - 20261107」；仅一台阀门时显示单个编号
        $codeRange = q_one(
            "SELECT MIN(CAST(order_code AS UNSIGNED)) mn, MAX(CAST(order_code AS UNSIGNED)) mx
             FROM workorder WHERE commission_id = ? AND is_del = 0 AND order_code <> ''",
            [$row['id']]
        );
        if ($codeRange && $codeRange['mn'] !== null) {
            $out['completedNum'] = $codeRange['mn'] == $codeRange['mx']
                ? (string) $codeRange['mn']
                : $codeRange['mn'] . ' - ' . $codeRange['mx'];
        } else {
            $out['completedNum'] = '';
        }
        $out['valveCount'] = (int) q_scalar('SELECT COUNT(*) FROM commission_valve WHERE commission_id = ?', [$row['id']]);
        $out['reportDone'] = (int) q_scalar(
            "SELECT COUNT(*) FROM workorder WHERE commission_id = ? AND is_del = 0 AND status = 'done'",
            [$row['id']]
        );
        $out['pendingValveCount'] = isset($pendingMap[(int) $row['id']]) ? $pendingMap[(int) $row['id']] : 0;
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

/**
 * 生成委托单号：WTD + 当前年月(YYYYMM) + 递增序号
 * 序号从 01 起，按月连续递增，超过 99 自然进位为三位（不截断）
 * 例：WTD20261101、WTD20261102 …… WTD20261199、WTD202611100、WTD202611202
 * 取当月最大序号 +1，已软删除的单据同样参与取号，保证单号永不复用
 */
function next_commission_code()
{
    $prefix = 'WTD' . date('Ym');
    $len    = strlen($prefix) + 1;
    $max    = (int) q_scalar(
        'SELECT IFNULL(MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)), 0) FROM commission
         WHERE code REGEXP ?',
        [$len, '^' . $prefix . '[0-9]+$']
    );
    return $prefix . str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
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

    // 生成委托单号（WTD + 年月 + 递增序号）；并发撞号由唯一索引拦截后重新取号
    $data = [
        'code'              => '',
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

    $id = 0;
    for ($try = 0; $try < 5; $try++) {
        $code = next_commission_code();
        $data['code'] = $code;
        try {
            $id = q_insert('commission', $data);
            break;
        } catch (PDOException $e) {
            // 23000 = 唯一键冲突，说明被并发请求抢占，重新取号
            if ((string) $e->getCode() !== '23000') throw $e;
            $id = 0;
        }
    }
    if (!$id) err('委托单号生成失败，请稍后重试');

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
 * 校验编号基准值：当前年月 + 开抽流水号（流水号不足两位补零，如流水号 6 → 20261106）
 */
function base_check_code($serial)
{
    $serial = trim((string) $serial);
    if (strlen($serial) < 2) $serial = str_pad($serial, 2, '0', STR_PAD_LEFT);
    return date('Ym') . $serial;
}

/**
 * 为委托单生成待检验工单（快照阀门清单），返回生成数量
 * $baseCode 传值时按「每台阀门一个连续编号」分配：第 1 台 = 基准编号，之后依次 +1
 * 如流水号 6、两台阀门 → 20261106、20261107；未传则回退旧的 JY+序号 规则
 */
function generate_workorders($row, $baseCode = '')
{
    $valves = q_all('SELECT * FROM commission_valve WHERE commission_id = ?', [$row['id']]);
    $base = $baseCode !== '' ? (int) $baseCode : 0;
    foreach ($valves as $i => $v) {
        $woData = $v;
        unset($woData['id']);
        $woData['valve_id'] = (int) $v['id'];
        $woData['commission_id'] = (int) $row['id'];
        $woData['code'] = $row['code'];
        $woData['cust_name'] = $row['cust_name'];
        $woData['client'] = $row['client'];
        $woData['client_tel'] = $row['client_tel'];
        $woData['status'] = 'wait';
        $woData['update_time'] = date('Y-m-d H:i:s');
        if ($baseCode !== '') {
            $woData['order_code'] = (string) ($base + $i);
        } else {
            $nextWo = (int) q_scalar('SELECT IFNULL(MAX(id), 0) + 1 FROM workorder');
            $woData['order_code'] = 'JY' . date('Y') . str_pad((string) $nextWo, 5, '0', STR_PAD_LEFT);
        }
        q_insert('workorder', $woData);
    }
    return count($valves);
}

/**
 * 获取手机签名二维码地址
 * GET /commission/sign_qrcode?id=xx
 * 二维码指向 sign.php?t=<token>，手机打开后手写签名并提交，签名图片与本委托单绑定
 */
function api_commission_sign_qrcode()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM commission WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('委托单不存在');

    // 首次获取时生成签名令牌
    $token = (string) $row['sign_token'];
    if ($token === '') {
        $token = bin2hex(random_bytes(16));
        q_update('commission', ['sign_token' => $token], 'id = ?', [$id]);
    }

    // 二维码地址：优先用 config.php 里配置的 SIGN_BASE_URL（手机需要能访问到电脑，不能用 localhost）
    if (defined('SIGN_BASE_URL') && SIGN_BASE_URL) {
        $host = rtrim(SIGN_BASE_URL, '/');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $url = $host . $dir . '/sign.php?t=' . $token;

    ok([
        'url'      => $url,
        'token'    => $token,
        'code'     => $row['code'],
        'signed'   => !empty($row['sign_img']),
        'signTime' => (string) $row['sign_time'],
    ]);
}

/** 查询手机签名状态（PC 端轮询用） GET /commission/sign_status?id=xx */
function api_commission_sign_status()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT sign_img, sign_time, serial_no FROM commission WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('委托单不存在');
    ok([
        'signed'   => !empty($row['sign_img']),
        'signTime' => (string) $row['sign_time'],
        'serialNo' => (string) $row['serial_no'],
    ]);
}

/**
 * 委托单新增阀门（手动新增 / 选择已有安全阀 / 复制行均可）
 * POST /commission/valve_add  { id: 委托单id, valves: [阀门对象...] }
 */
function api_commission_valve_add()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT id FROM commission WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('委托单不存在');

    $valves = param('valves', []);
    if (!is_array($valves) || !count($valves)) err('请提供阀门数据');
    $n = 0;
    foreach ($valves as $v) {
        if (!is_array($v)) continue;
        $data = valve_to_row($v);
        if (!$data) continue;
        q_insert('commission_valve', array_merge(['commission_id' => $id], $data));
        $n++;
    }
    if (!$n) err('没有可保存的阀门数据');
    ok(['count' => $n]);
}

/** 删除委托单阀门 POST /commission/valve_delete  { id: 阀门明细id } */
function api_commission_valve_delete()
{
    $vid = (int) param('id', 0);
    if (!$vid) err('缺少参数 id');
    $row = q_one('SELECT id FROM commission_valve WHERE id = ?', [$vid]);
    if (!$row) err('阀门记录不存在');
    q_exec('DELETE FROM commission_valve WHERE id = ?', [$vid]);
    ok(null);
}

/**
 * 编辑委托单阀门信息
 * POST /commission/valve_update  { id, vavleModel?, manuNo?, diameterNominal?, pressNominal?, setPress?, tightnessPress?, idNo?, ... }
 */
function api_commission_valve_update()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM commission_valve WHERE id = ?', [$id]);
    if (!$row) err('阀门记录不存在');

    global $VALVE_FIELDS;
    $data = [];
    foreach ($VALVE_FIELDS as $camel => $snake) {
        if (param($camel) !== null) $data[$snake] = param($camel);
    }
    if (!$data) err('没有需要修改的字段');
    q_update('commission_valve', $data, 'id = ?', [$id]);
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

    // 按钮级权限：不同操作对应不同权限标识
    $actionPerm = [
        'approve'  => 'commission:list:approve',
        'reject'   => 'commission:list:reject',
        'precheck' => 'commission:list:precheck',
        'pay'      => 'commission:list:pay',
        'delete'   => 'commission:list:delete',
    ];
    $user = current_user();
    if (isset($actionPerm[$action])) {
        require_permission($user, $actionPerm[$action]);
    }

    $row = q_one('SELECT * FROM commission WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('委托单不存在');

    switch ($action) {
        case 'approve':
            if ($row['status'] !== 'pending') err('仅待审核的委托单可以通过');
            q_update('commission', ['status' => 'approved', 'reject_reason' => ''], 'id = ?', [$id]);
            // 为每台安全阀生成待检验工单（快照阀门数据，valve_id 用于回查原阀门）
            $count = generate_workorders($row);
            ok(['workorderCount' => $count]);
            break;

        case 'reject':
            $reason = trim((string) param('rejectReason', ''));
            if ($reason === '') err('请填写退回原因');
            q_update('commission', ['status' => 'rejected', 'reject_reason' => $reason], 'id = ?', [$id]);
            ok(null);
            break;

        case 'precheck':
            // 预检通过：必须先完成手机签名，并录入全局唯一的开抽流水号 → 状态变为「等待检验」
            if ($row['status'] !== 'pending') err('仅等待预检的委托单可以通过预检');
            if (empty($row['sign_img'])) err('该委托单尚未完成手机签名，无法通过预检');
            $serial = trim((string) param('serialNo', param('serial_no', '')));
            if ($serial === '') err('请输入开抽流水号');
            if (!ctype_digit($serial)) err('开抽流水号必须是数字');

            // 本单阀门数 → 占用的编号区间 [serial, serial+数量-1]，流水号与其它委托单的区间不能重叠
            $valveCount = (int) q_scalar('SELECT COUNT(*) FROM commission_valve WHERE commission_id = ?', [$id]);
            if ($valveCount < 1) $valveCount = 1;
            $serialN = (int) $serial;
            $endN = $serialN + $valveCount - 1;
            $others = q_all(
                "SELECT id, code, serial_no FROM commission
                 WHERE is_del = 0 AND id <> ? AND serial_no IS NOT NULL AND serial_no <> ''",
                [$id]
            );
            foreach ($others as $o) {
                if (!ctype_digit((string) $o['serial_no'])) continue;
                $os = (int) $o['serial_no'];
                $oc = (int) q_scalar('SELECT COUNT(*) FROM commission_valve WHERE commission_id = ?', [$o['id']]);
                if ($oc < 1) $oc = 1;
                if ($serialN <= $os + $oc - 1 && $os <= $endN) {
                    err(
                        '流水号 ' . $serial . ($valveCount > 1 ? '~' . $endN : '')
                        . ' 与委托单 ' . $o['code'] . ' 的流水号「' . $o['serial_no']
                        . '」区间重叠，请重新输入'
                    );
                }
            }

            q_update(
                'commission',
                ['status' => 'approved', 'precheck' => 1, 'serial_no' => $serial, 'reject_reason' => ''],
                'id = ?',
                [$id]
            );
            // 进入待检验：校验编号 = 当前年月+流水号 起始，每台阀门连续递增（流水号 6、2 台 → 20261106、20261107）
            $baseCode = base_check_code($serial);
            $count = (int) q_scalar('SELECT COUNT(*) FROM workorder WHERE commission_id = ? AND is_del = 0', [$id]);
            if (!$count) {
                $count = generate_workorders($row, $baseCode);
            } else {
                // 已有工单（如曾走「通过」按钮生成）也按阀门顺序重新连续编号
                $base = (int) $baseCode;
                $wos = q_all('SELECT id FROM workorder WHERE commission_id = ? AND is_del = 0 ORDER BY id', [$id]);
                foreach ($wos as $i => $w) {
                    q_update('workorder', ['order_code' => (string) ($base + $i)], 'id = ?', [(int) $w['id']]);
                }
            }
            ok([
                'serialNo'       => $serial,
                'checkCodeStart' => $baseCode,
                'checkCodeEnd'   => (string) ((int) $baseCode + $valveCount - 1),
                'workorderCount' => $count,
            ]);
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

/* =====================================================================
 * 待检阀门列表（按委托单位汇总）
 * 口径：以委托单安全阀清单（commission_valve）为准，关联其检验工单判断检测状态
 *   pending    委托单待审核，阀门尚未进入检验
 *   wait       工单待检验
 *   inspection 工单检验中
 *   rejected   工单校验退回
 *   print/done 已出检验结果（不算待检）
 * scope：undone（默认，未出检验结果：pending/wait/inspection/rejected）
 *        waiting（待审核 + 待检验）
 *        all（全部阀门）
 * ===================================================================== */

/** 待检阀门子查询（一行 = 一台阀门） */
function pending_valve_subquery()
{
    return "(
        SELECT cv.id            AS valve_id,
               cv.commission_id AS commission_id,
               cv.manu_no       AS manu_no,
               cv.vavle_model   AS vavle_model,
               cv.diameter_nominal AS diameter_nominal,
               cv.working_media AS working_media,
               cv.working_press AS working_press,
               cv.set_press     AS set_press,
               cv.equip_type    AS equip_type,
               cv.tag_no        AS tag_no,
               cv.manufacturer  AS manufacturer,
               c.code           AS commission_code,
               c.status         AS commission_status,
               c.cust_id        AS cust_id,
               c.cust_name      AS cust_name,
               c.create_time    AS commission_time,
               w.id             AS workorder_id,
               w.order_code     AS order_code,
               w.status         AS workorder_status,
               CASE
                   WHEN w.id IS NULL AND c.status = 'rejected' THEN 'rejected'
                   WHEN w.id IS NULL THEN 'pending'
                   ELSE w.status
               END AS valve_status
        FROM commission_valve cv
        INNER JOIN commission c ON c.id = cv.commission_id AND c.is_del = 0
        LEFT JOIN workorder w ON w.id = (
            SELECT w2.id FROM workorder w2
            WHERE w2.is_del = 0
              AND (w2.valve_id = cv.id
                   OR (w2.valve_id = 0 AND cv.manu_no <> ''
                       AND w2.commission_id = cv.commission_id
                       AND w2.manu_no = cv.manu_no))
            ORDER BY w2.id
            LIMIT 1
        )
    ) t";
}

/** GET /commission/pending_valves —— 所有委托单位下的待检阀门明细 + 按单位汇总 */
function api_commission_pending_valves()
{
    [$page, $limit, $offset] = page_params();

    $scope = trim((string) param('scope', 'undone'));
    if (!in_array($scope, ['undone', 'waiting', 'all'], true)) $scope = 'undone';

    $custId = (int) param('custId', 0);
    $keyword = trim((string) param('keyword', ''));

    $sub = pending_valve_subquery();

    /* ---------- 明细（scope + keyword + custId 过滤） ---------- */
    $where = [];
    $params = [];

    if ($custId) {
        $where[] = 't.cust_id = ?';
        $params[] = $custId;
    }
    if ($keyword !== '') {
        $where[] = '(t.cust_name LIKE ? OR t.commission_code LIKE ?
                     OR t.vavle_model LIKE ? OR t.manu_no LIKE ? OR t.order_code LIKE ?)';
        $kw = '%' . $keyword . '%';
        for ($i = 0; $i < 5; $i++) $params[] = $kw;
    }
    if ($scope === 'waiting') {
        $where[] = "t.valve_status IN ('pending','wait')";
    } elseif ($scope === 'undone') {
        $where[] = "t.valve_status IN ('pending','wait','inspection','rejected')";
    }
    $whereSql = $where ? implode(' AND ', $where) : '1 = 1';

    $total = (int) q_scalar("SELECT COUNT(*) FROM $sub WHERE $whereSql", $params);
    $rows = q_all(
        "SELECT * FROM $sub WHERE $whereSql
         ORDER BY t.cust_id, t.commission_id, t.valve_id
         LIMIT $limit OFFSET $offset",
        $params
    );

    /* ---------- 按单位汇总（不受 keyword/scope 影响，仅受 custId 限制） ---------- */
    $sumWhere = $custId ? 't.cust_id = ?' : '1 = 1';
    $sumParams = $custId ? [$custId] : [];
    $sumRows = q_all(
        "SELECT t.cust_id,
                t.cust_name,
                SUM(CASE WHEN t.valve_status IN ('pending','wait','inspection','rejected') THEN 1 ELSE 0 END) AS undone,
                COUNT(*) AS total
         FROM $sub WHERE $sumWhere
         GROUP BY t.cust_id, t.cust_name
         ORDER BY undone DESC, total DESC",
        $sumParams
    );

    $statusText = [
        'pending'    => '待审核',
        'wait'       => '待检验',
        'inspection' => '检验中',
        'rejected'   => '校验退回',
        'print'      => '待打印',
        'done'       => '已完成',
    ];

    $summary = [];
    $totalPending = 0;
    foreach ($sumRows as $s) {
        $pending = (int) $s['undone'];
        $totalPending += $pending;
        $summary[] = [
            'custId'       => $s['cust_id'] ? (int) $s['cust_id'] : null,
            'unitName'     => $s['cust_name'],
            'pendingCount' => $pending,
            'totalCount'   => (int) $s['total'],
        ];
    }

    $list = [];
    foreach ($rows as $r) {
        $list[] = [
            'valveId'          => (int) $r['valve_id'],
            'commissionId'     => (int) $r['commission_id'],
            'commissionCode'   => $r['commission_code'],
            'commissionStatus' => $r['commission_status'],
            'commissionTime'   => $r['commission_time'],
            'custId'           => $r['cust_id'] ? (int) $r['cust_id'] : null,
            'custName'         => $r['cust_name'],
            'vavleModel'       => $r['vavle_model'],
            'manuNo'           => $r['manu_no'],
            'diameterNominal'  => $r['diameter_nominal'] !== null ? (int) $r['diameter_nominal'] : null,
            'workingMedia'     => $r['working_media'],
            'workingPress'     => $r['working_press'],
            'setPress'         => $r['set_press'],
            'equipType'        => $r['equip_type'],
            'tagNo'            => $r['tag_no'],
            'manufacturer'     => $r['manufacturer'],
            'workorderId'      => $r['workorder_id'] ? (int) $r['workorder_id'] : null,
            'orderCode'        => $r['order_code'] ?: '',
            'workorderStatus'  => $r['workorder_status'] ?: '',
            'valveStatus'      => $r['valve_status'],
            'valveStatusText'  => isset($statusText[$r['valve_status']]) ? $statusText[$r['valve_status']] : $r['valve_status'],
        ];
    }

    ok([
        'list'         => $list,
        'summary'      => $summary,
        'totalPending' => $totalPending,
        'total'        => $total,
        'scope'        => $scope,
        'page'         => $page,
        'limit'        => $limit,
    ]);
}
