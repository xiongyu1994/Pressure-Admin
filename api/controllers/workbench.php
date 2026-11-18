<?php
/** 工作台：统计 / 图表 / 排名 / 单位列表 */

function api_workbench_stats()
{
    $checkedTotal = (int) q_scalar(
        "SELECT COUNT(*) FROM workorder WHERE is_del=0 AND status IN ('print','done')"
    );
    $unchecked = (int) q_scalar(
        "SELECT COUNT(*) FROM workorder WHERE is_del=0 AND status='print'"
    );
    $waitTotal = (int) q_scalar("SELECT COUNT(*) FROM workorder WHERE is_del=0 AND status='wait'");
    $commissionTotal = (int) q_scalar("SELECT COUNT(*) FROM commission WHERE is_del=0");
    $customerTotal = (int) q_scalar("SELECT COUNT(*) FROM unit WHERE is_del=0");

    ok([
        'checkedTotal'    => $checkedTotal,
        'unchecked'       => $unchecked,
        'waitTotal'       => $waitTotal,
        'commissionTotal' => $commissionTotal,
        'customerTotal'   => $customerTotal,
    ]);
}

/** 安全阀校验数统计（按年/按月聚合已审/未审）
 *  GET /workbench/chart?statType=year&start=2026-01&end=2026-12&custName=xxx
 */
function api_workbench_chart()
{
    $start = param('start', date('Y') . '-01');
    $end   = param('end', date('Y') . '-12');

    $startYear = (int) substr($start, 0, 4);
    $endYear   = (int) substr($end, 0, 4);
    $startMonth = $startYear === $endYear ? (int) substr($start, 5, 2) : 1;
    $endMonth   = $startYear === $endYear ? (int) substr($end, 5, 2) : 12;

    $params = [$startYear . '-01-01', $endYear . '-12-31'];
    $custFilter = '';
    $custName = trim((string) param('custName', ''));
    if ($custName !== '') {
        $custFilter = ' AND w.cust_name LIKE ? ';
        $params[] = '%' . $custName . '%';
    }

    // 已审 = 已有检验结果；未审 = 待打印（有结果未打印台账）之外按参考口径取 result 缺失
    $rows = q_all(
        "SELECT DATE_FORMAT(w.inspector_time, '%m') AS m,
                SUM(CASE WHEN w.status = 'done' THEN 1 ELSE 0 END) AS audited,
                SUM(CASE WHEN w.status = 'print' THEN 1 ELSE 0 END) AS unaudited
         FROM workorder w
         WHERE w.is_del = 0 AND w.inspector_time IS NOT NULL
           AND w.inspector_time BETWEEN ? AND ? $custFilter
         GROUP BY DATE_FORMAT(w.inspector_time, '%m')",
        $params
    );

    $months = [];
    $audited = [];
    $unaudited = [];
    for ($m = $startMonth; $m <= $endMonth; $m++) {
        $key = sprintf('%02d', $m);
        $months[] = $m . '月';
        $audited[] = 0;
        $unaudited[] = 0;
        foreach ($rows as $r) {
            if (ltrim($r['m'], '0') === (string) $m) {
                $audited[count($months) - 1] = (int) $r['audited'];
                $unaudited[count($months) - 1] = (int) $r['unaudited'];
            }
        }
    }

    ok(['months' => $months, 'audited' => $audited, 'unaudited' => $unaudited]);
}

/** 检验人员排名 */
function api_rank_inspector()
{
    $rows = q_all(
        "SELECT w.inspector AS name, w.cust_name AS org, COUNT(*) AS count
         FROM workorder w
         WHERE w.is_del = 0 AND w.inspector <> ''
         GROUP BY w.inspector, w.cust_name
         ORDER BY count DESC
         LIMIT 20"
    );
    $list = [];
    foreach ($rows as $i => $r) {
        $list[] = ['rank' => $i + 1, 'name' => $r['name'], 'org' => $r['org'], 'count' => (int) $r['count']];
    }
    ok($list);
}

/** 送检单位排名 */
function api_rank_unit()
{
    $start = param('start', '');
    $end = param('end', '');
    $where = 'w.is_del = 0';
    $params = [];
    if ($start) {
        $where .= ' AND w.create_time >= ?';
        $params[] = $start . ' 00:00:00';
    }
    if ($end) {
        $where .= ' AND w.create_time <= ?';
        $params[] = $end . ' 23:59:59';
    }
    $rows = q_all(
        "SELECT w.cust_name AS unit, COUNT(*) AS count
         FROM workorder w
         WHERE $where
         GROUP BY w.cust_name
         ORDER BY count DESC
         LIMIT 50",
        $params
    );
    $list = [];
    foreach ($rows as $i => $r) {
        $list[] = ['rank' => $i + 1, 'unit' => $r['unit'], 'count' => (int) $r['count']];
    }
    ok($list);
}

/** 使用单位下拉 / 客户管理列表 */
function api_unit_list()
{
    $keyword   = trim((string) param('keyword', ''));
    $userName  = trim((string) param('userName', ''));    // 姓名
    $client    = trim((string) param('client', ''));      // 联系人
    $clientTel = trim((string) param('clientTel', ''));   // 联系人电话
    $entrustTel = trim((string) param('entrustTel', '')); // 委托人电话

    $where = ['is_del=0'];
    $args = [];
    if ($keyword !== '') {
        $where[] = '(name LIKE ? OR client LIKE ?)';
        $args[] = '%' . $keyword . '%';
        $args[] = '%' . $keyword . '%';
    }
    if ($userName !== '') {
        $where[] = 'user_name LIKE ?';
        $args[] = '%' . $userName . '%';
    }
    if ($client !== '') {
        $where[] = 'client LIKE ?';
        $args[] = '%' . $client . '%';
    }
    if ($clientTel !== '') {
        $where[] = 'client_tel LIKE ?';
        $args[] = '%' . $clientTel . '%';
    }
    if ($entrustTel !== '') {
        $where[] = 'entrust_tel LIKE ?';
        $args[] = '%' . $entrustTel . '%';
    }
    $sql = 'SELECT id, user_name, mobile, name, identity, address, client, client_tel, entrust_client, entrust_tel, bank_name, apply_unit, tax_no, last_login_time, last_login_ip FROM unit WHERE ' . implode(' AND ', $where) . ' ORDER BY id';
    $rows = $args ? q_all($sql, $args) : q_all($sql);
    ok(array_map(function ($r) {
        return [
            'id' => (int) $r['id'],
            'userName' => isset($r['user_name']) ? (string) $r['user_name'] : '',
            'mobile' => isset($r['mobile']) ? (string) $r['mobile'] : '',
            'name' => $r['name'],
            'identity' => isset($r['identity']) && $r['identity'] !== '' ? $r['identity'] : '送检单位',
            'address' => $r['address'],
            'client' => $r['client'],
            'clientTel' => $r['client_tel'],
            'entrustClient' => isset($r['entrust_client']) ? (string) $r['entrust_client'] : '',
            'entrustTel' => isset($r['entrust_tel']) ? (string) $r['entrust_tel'] : '',
            'bankName' => isset($r['bank_name']) ? (string) $r['bank_name'] : '',
            'applyUnit' => isset($r['apply_unit']) ? (string) $r['apply_unit'] : '',
            'taxNo' => isset($r['tax_no']) ? (string) $r['tax_no'] : '',
            'lastLoginTime' => isset($r['last_login_time']) ? (string) $r['last_login_time'] : '',
            'lastLoginIp' => isset($r['last_login_ip']) ? (string) $r['last_login_ip'] : '',
        ];
    }, $rows));
}

/** 新增客户（使用单位）POST /unit/add */
function api_unit_add()
{
    $name = trim((string) param('name', ''));
    if ($name === '') err('请输入单位名称');
    $id = q_insert('unit', [
        'user_name' => (string) param('userName', ''),
        'mobile'    => (string) param('mobile', ''),
        'name'      => $name,
        'identity'  => '送检单位',
        'address'   => (string) param('address', ''),
        'client'    => (string) param('client', ''),
        'client_tel'=> (string) param('clientTel', ''),
        'entrust_client' => (string) param('entrustClient', ''),
        'entrust_tel'    => (string) param('entrustTel', ''),
        'bank_name' => (string) param('bankName', ''),
        'apply_unit'=> (string) param('applyUnit', ''),
        'tax_no'    => (string) param('taxNo', ''),
    ]);
    ok(['id' => (int) $id]);
}

/** 编辑客户 POST /unit/update */
function api_unit_update()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT id FROM unit WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('客户不存在');

    $data = [];
    foreach (['userName' => 'user_name', 'mobile' => 'mobile', 'name' => 'name', 'address' => 'address', 'client' => 'client', 'clientTel' => 'client_tel', 'entrustClient' => 'entrust_client', 'entrustTel' => 'entrust_tel', 'bankName' => 'bank_name', 'applyUnit' => 'apply_unit', 'taxNo' => 'tax_no'] as $camel => $snake) {
        if (param($camel) !== null) $data[$snake] = param($camel);
    }
    if (!$data) err('没有需要修改的字段');
    q_update('unit', $data, 'id = ?', [$id]);
    ok(null);
}

/** 重置客户密码 POST /unit/reset_password */
function api_unit_reset_password()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT id, name FROM unit WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('客户不存在');
    $newPwd = trim((string) param('password', ''));
    if ($newPwd === '') $newPwd = '123456';
    q_update('unit', ['password' => md5($newPwd)], 'id = ?', [$id]);
    ok(['password' => $newPwd]);
}

/** 删除客户 POST /unit/delete */
function api_unit_delete()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    q_update('unit', ['is_del' => 1], 'id = ?', [$id]);
    ok(null);
}
