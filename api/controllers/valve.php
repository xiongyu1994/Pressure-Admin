<?php
/** 安全阀库 */

/** 生成唯一识别码：YmdHis + 6 位随机数，查库保证唯一 */
function gen_unique_code()
{
    for ($i = 0; $i < 10; $i++) {
        $code = date('YmdHis') . str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $exists = q_one('SELECT id FROM safety_valve WHERE unique_code = ?', [$code]);
        if (!$exists) {
            return $code;
        }
    }
    return uniqid('SV');
}

function api_valve_list()
{
    [$page, $limit, $offset] = page_params();

    $where = 'is_del = 0';
    $params = [];

    // 文本模糊：出厂编号 / 设备代码 / 安全阀型号 / 送检单位名称 / 唯一识别
    foreach (['manu_no' => 'manuNo', 'id_no' => 'idNo', 'vavle_model' => 'vavleModel', 'cust_name' => 'custName', 'unique_code' => 'uniqueCode'] as $col => $key) {
        $v = trim((string) param($key, ''));
        if ($v !== '') {
            $where .= " AND $col LIKE ?";
            $params[] = "%$v%";
        }
    }
    // 精确：设备类型 / 工作介质
    foreach (['equip_type' => 'equipType', 'working_media' => 'workingMedia'] as $col => $key) {
        $v = trim((string) param($key, ''));
        if ($v !== '') {
            $where .= " AND $col = ?";
            $params[] = $v;
        }
    }
    // 数值：要求整定压力
    $setPress = param('setPress', null);
    if ($setPress !== null && $setPress !== '') {
        $where .= ' AND set_press = ?';
        $params[] = (float) $setPress;
    }
    // 日期范围：检验时间 / 下次检验时间（VARCHAR YYYY-MM-DD，字符串比较即可）
    foreach ([['checkTimeStart', 'check_time >= ?'], ['checkTimeEnd', 'check_time <= ?'], ['nextCheckTimeStart', 'next_check_time >= ?'], ['nextCheckTimeEnd', 'next_check_time <= ?']] as [$key, $cond]) {
        $v = trim((string) param($key, ''));
        if ($v !== '') {
            $where .= " AND $cond";
            $params[] = $v;
        }
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
    $row = valve_to_row(input(), true);
    if (empty($row['set_press']) || empty($row['diameter_nominal']) || empty($row['tightness_press'])) {
        err('要求整定压力、公称通径、密封试验压力为必填');
    }
    // 支持批量提交：count（每台各生成一个唯一识别码）
    $count = max(1, min(20, (int) param('count', 1)));
    $ids = [];
    for ($i = 0; $i < $count; $i++) {
        $data = $row;
        if (empty($data['unique_code'])) {
            $data['unique_code'] = gen_unique_code();
        }
        $ids[] = q_insert('safety_valve', $data);
    }
    ok(['ids' => $ids, 'count' => count($ids)]);
}

/** 删除安全阀：支持单个 id 或批量 ids POST /valve/delete */
function api_valve_delete()
{
    $ids = param('ids', []);
    if (is_array($ids) && count($ids)) {
        foreach ($ids as $id) {
            q_update('safety_valve', ['is_del' => 1], 'id = ?', [(int) $id]);
        }
        ok(['count' => count($ids)]);
    }
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    q_update('safety_valve', ['is_del' => 1], 'id = ?', [$id]);
    ok(null);
}

/**
 * Excel(CSV) 导入 POST /valve/import  { rows: [ {出厂编号, 送检单位名称, ...} ] }
 * 表头（下载92新导入模板同款）：出厂编号,送检单位名称,安装位置,设备代码,安全阀型号,工作介质,
 * 要求整定压力,密封试验压力,公称通径,检验时间,下次检验时间,周期(年),状态
 */
$VALVE_IMPORT_HEADERS = [
    // 92 新导入模板（TSG 92-2026，24 列，表头已去换行/空格/尾部*）
    '被保护设备种类'     => 'protected_equip_type',
    '工作介质'           => 'working_media',
    '要求整定压力MPa'    => 'set_press',
    '工作压力MPa'        => 'working_press',
    '安全阀类型'         => 'vavle_type',
    '安全阀型号'         => 'vavle_model',
    '公称压力MPa'        => 'press_nominal',
    '公称通径mm'         => 'diameter_nominal',
    '流道直径mm'         => 'seat_size',
    '压力级别范围小MPa'  => 'press_class_range_min',
    '压力级别范围大MPa'  => 'press_class_range_max',
    '出厂日期'           => 'manu_date',
    '出厂编号'           => 'manu_no',
    '制造单位'           => 'manufacturer',
    '冷差试验压力MPa'    => 'tightness_press',
    '校验方式'           => 'parity',
    '方法(在线必填)'     => 'check_method',
    '周期(年)'           => 'period_years',
    '设备类型/设备名称'  => 'equip_type',
    '安装位置'           => 'tag_no',
    '设备代码'           => 'id_no',
    '制造许可证号'       => 'license_no',
    '区域'               => 'region',
    '设计压力MPa'        => 'design_press',
    // 兼容旧 CSV 模板表头
    '送检单位名称'       => 'cust_name',
    '要求整定压力'       => 'set_press',
    '密封试验压力'       => 'tightness_press',
    '公称通径'           => 'diameter_nominal',
    '检验时间'           => 'check_time',
    '下次检验时间'       => 'next_check_time',
    '状态'               => 'status',
];

function api_valve_import()
{
    global $VALVE_IMPORT_HEADERS;
    $rows = param('rows', []);
    if (!is_array($rows) || !count($rows)) err('没有可导入的数据');

    $inserted = 0;
    $errors = [];
    foreach (array_values($rows) as $i => $r) {
        $data = [];
        foreach ($VALVE_IMPORT_HEADERS as $h => $col) {
            $v = isset($r[$h]) ? trim((string) $r[$h]) : (isset($r[$col]) ? trim((string) $r[$col]) : '');
            if ($v !== '') $data[$col] = $v;
        }
        if (empty($data['manu_no']) && empty($data['vavle_model'])) {
            $errors[] = '第' . ($i + 1) . '行缺少出厂编号和安全阀型号，已跳过';
            continue;
        }
        if (empty($data['status'])) $data['status'] = '正常';
        if (empty($data['period_years'])) $data['period_years'] = 1;
        if (empty($data['unique_code'])) $data['unique_code'] = gen_unique_code();
        q_insert('safety_valve', $data);
        $inserted++;
    }
    ok(['count' => $inserted, 'errors' => $errors]);
}

/** 编辑安全阀 POST /valve/update { id, ...字段 } */
function api_valve_update()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT id FROM safety_valve WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('安全阀记录不存在');

    global $VALVE_LIB_FIELDS;
    $data = [];
    foreach ($VALVE_LIB_FIELDS as $camel => $snake) {
        if (param($camel) !== null) $data[$snake] = param($camel);
    }
    if (!$data) err('没有需要修改的字段');
    q_update('safety_valve', $data, 'id = ?', [$id]);
    ok(null);
}
