<?php
/** 安全阀字段：camelCase（前端） ↔ snake_case（数据库）映射 */

$VALVE_FIELDS = [
    'equipType'             => 'equip_type',
    'protectedEquipType'    => 'protected_equip_type',
    'workingMedia'          => 'working_media',
    'setPress'              => 'set_press',
    'workingPress'          => 'working_press',
    'designPress'           => 'design_press',
    'workingTemp'           => 'working_temp',
    'tagNo'                 => 'tag_no',
    'idNo'                  => 'id_no',
    'vavleModel'            => 'vavle_model',
    'vavleType'             => 'vavle_type',
    'manufacturer'          => 'manufacturer',
    'licenseNo'             => 'license_no',
    'diameterNominal'       => 'diameter_nominal',
    'seatSize'              => 'seat_size',
    'pressNominal'          => 'press_nominal',
    'pressClassRangeMin'    => 'press_class_range_min',
    'pressClassRangeMax'    => 'press_class_range_max',
    'manuDate'              => 'manu_date',
    'manuNo'                => 'manu_no',
    'tightnessPress'        => 'tightness_press',
    'pressCorrection'       => 'press_correction',
    'isNew'                 => 'is_new',
    'isRemovedCleaning'     => 'is_removed_cleaning',
    'isComplete'            => 'is_complete',
    'isNameplate'           => 'is_nameplate',
    'parity'                => 'parity',
];

/** 把前端传来的阀门对象转成数据库行 */
function valve_to_row($valve)
{
    global $VALVE_FIELDS;
    $row = [];
    foreach ($VALVE_FIELDS as $camel => $snake) {
        if (isset($valve[$camel])) {
            $row[$snake] = $valve[$camel];
        } elseif (isset($valve[$snake])) {
            $row[$snake] = $valve[$snake];
        }
    }
    return $row;
}

/** 数据库行转前端驼峰结构 */
function valve_row($row)
{
    global $VALVE_FIELDS;
    $out = ['id' => isset($row['id']) ? (int) $row['id'] : null];
    foreach ($VALVE_FIELDS as $camel => $snake) {
        if (array_key_exists($snake, $row)) {
            $out[$camel] = $row[$snake];
        }
    }
    return $out;
}

/** 工单行输出（含阀门快照字段驼峰化） */
function workorder_row($row)
{
    global $VALVE_FIELDS;
    $out = [
        'id'           => (int) $row['id'],
        'commissionId' => (int) $row['commission_id'],
        'code'         => $row['code'],
        'orderCode'    => $row['order_code'],
        'custName'     => $row['cust_name'],
        'client'       => $row['client'],
        'clientTel'    => $row['client_tel'],
        'status'       => $row['status'],
        'rejectReason' => $row['reject_reason'],
        'inspector'    => $row['inspector'],
        'inspectorTime'=> $row['inspector_time'],
        'updateTime'   => $row['update_time'],
        'createTime'   => $row['create_time'],
    ];
    foreach ($VALVE_FIELDS as $camel => $snake) {
        if (array_key_exists($snake, $row)) {
            $out[$camel] = $row[$snake];
        }
    }
    // 检验结果
    if (isset($row['r_id']) && $row['r_id']) {
        $out['result'] = result_row($row);
    } elseif (isset($row['r_test_status']) && $row['r_test_status'] !== null) {
        $out['result'] = result_row($row);
    }
    return $out;
}

/** 检验结果行输出 */
function result_row($row)
{
    return [
        'orderCode'              => $row['r_order_code'] ?? null,
        'tightnessPress'         => $row['r_tightness_press'] ?? null,
        'setPress'               => $row['r_set_press'] ?? null,
        'testMedia'              => $row['r_test_media'] ?? null,
        'testTemprature'         => $row['r_test_temprature'] ?? null,
        'parity'                 => $row['r_parity'] ?? null,
        'visualExamination'      => $row['r_visual_examination'] ?? null,
        'disassemblyInspection'  => $row['r_disassembly_inspection'] ?? null,
        'note'                   => $row['r_note'] ?? null,
        'pressureGaugeNum'       => $row['r_pressure_gauge_num'] ?? null,
        'pressureGaugeLifespan'  => $row['r_pressure_gauge_lifespan'] ?? null,
        'pressureGaugeNum2'      => $row['r_pressure_gauge_num2'] ?? null,
        'pressureGaugeLifespan2' => $row['r_pressure_gauge_lifespan2'] ?? null,
        'pressureGaugeAccuracy'  => $row['r_pressure_gauge_accuracy'] ?? null,
        'pressureGaugeRange'     => $row['r_pressure_gauge_range'] ?? null,
        'deviceModel'            => $row['r_device_model'] ?? null,
        'deviceSn'               => $row['r_device_sn'] ?? null,
        'testingPressure1'       => $row['r_testing_pressure1'] ?? null,
        'testingPressure2'       => $row['r_testing_pressure2'] ?? null,
        'testingPressure3'       => $row['r_testing_pressure3'] ?? null,
        'testStatus'             => $row['r_test_status'] ?? null,
    ];
}

/** 委托单行输出 */
function commission_row($row)
{
    return [
        'id'              => (int) $row['id'],
        'code'            => $row['code'],
        'custId'          => $row['cust_id'] ? (int) $row['cust_id'] : null,
        'custName'        => $row['cust_name'],
        'address'         => $row['address'],
        'client'          => $row['client'],
        'clientTel'       => $row['client_tel'],
        'applicant'       => $row['applicant'],
        'custAddress'     => $row['cust_address'],
        'contact'         => $row['contact'],
        'contactTel'      => $row['contact_tel'],
        'examName'        => $row['exam_name'],
        'planReceiverTime'=> $row['plan_receiver_time'],
        'deliverer'       => $row['deliverer'],
        'deliveryTime'    => $row['delivery_time'],
        'status'          => $row['status'],
        'rejectReason'    => $row['reject_reason'],
        'precheck'        => (bool) $row['precheck'],
        'paid'            => (bool) $row['paid'],
        'signed'          => !empty($row['sign_img']),
        'signImg'         => isset($row['sign_img']) ? (string) $row['sign_img'] : '',
        'signTime'        => isset($row['sign_time']) ? (string) $row['sign_time'] : '',
        'serialNo'        => isset($row['serial_no']) ? (string) $row['serial_no'] : '',
        'createBy'        => $row['create_by'],
        'createTime'      => $row['create_time'],
    ];
}

/** 委托单详情：带安全阀清单 */
function commission_with_valves($row)
{
    $out = commission_row($row);
    // 关联工单取每台阀门的校验编号与校验状态（workorder.valve_id 关联）
    $valves = q_all(
        'SELECT cv.*, w.order_code AS wo_order_code, w.status AS wo_status
         FROM commission_valve cv
         LEFT JOIN workorder w ON w.valve_id = cv.id AND w.is_del = 0
         WHERE cv.commission_id = ?',
        [$row['id']]
    );
    $out['valves'] = array_map('valve_row', $valves);
    foreach ($out['valves'] as $i => $v) {
        $src = $valves[$i];
        $out['valves'][$i]['orderCode'] = isset($src['wo_order_code']) ? (string) $src['wo_order_code'] : '';
        $out['valves'][$i]['checkStatus'] = isset($src['wo_status']) ? (string) $src['wo_status'] : '';
    }
    return $out;
}
