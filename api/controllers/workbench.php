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

/** 使用单位下拉 */
function api_unit_list()
{
    $keyword = trim((string) param('keyword', ''));
    if ($keyword !== '') {
        $rows = q_all('SELECT id, name, address, client, client_tel FROM unit WHERE is_del=0 AND name LIKE ? ORDER BY id', ['%' . $keyword . '%']);
    } else {
        $rows = q_all('SELECT id, name, address, client, client_tel FROM unit WHERE is_del=0 ORDER BY id');
    }
    ok(array_map(function ($r) {
        return [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'address' => $r['address'],
            'client' => $r['client'],
            'clientTel' => $r['client_tel'],
        ];
    }, $rows));
}
