<?php
/** 校验记录 docx 生成库（纯 PHP，无 COM；被 record.php 与 controllers/capi.php 共用）
 *
 *  模板：uploads/tpl/tpl_check_record.docx（由 lib/build_tpl_record.php 从源模板一次性改造：
 *        签名文本框已替换为预置占位图 descr=_ph_sign（校验员 24×12mm）/ _ph_sec（审核员 18×10mm））
 *  流程：复制模板 → ZipArchive 覆盖两个签名 media + 替换文本占位符 → 输出 docx（无需转 PDF）
 *  缓存：uploads/report/{校验年月}/{检验编号}_record.docx（与报告 PDF 同目录、按年月分目录留存）
 */

require_once __DIR__ . '/docx_tpl.php';   // media_by_descr / 占位符替换 / 签名路径等共用工具

/** 校验记录 docx 缓存路径：uploads/report/{YYYYMM}/{编号}_record.docx */
function record_docx_path($code, $insTime)
{
    $m = $insTime ? date('Ym', strtotime($insTime)) : date('Ym');
    return __DIR__ . '/../uploads/report/' . $m . '/' . $code . '_record.docx';
}

/** 校验记录字段映射（模板占位符 → workorder / workorder_result） */
function build_record_field_map($wo, $res)
{
    $insTime = !empty($wo['inspector_time']) ? $wo['inspector_time'] : (!empty($res['create_time']) ? $res['create_time'] : '');

    // 备注：兜底剥离历史脏数据（"执行标准：…；备注：xxx"），只保留备注原文
    $note = (string) ($res['note'] ?? '');
    if (strpos($note, '执行标准：') === 0) {
        $note = preg_match('/；备注：(.*)$/u', $note, $mm) ? trim($mm[1]) : '';
    }

    $str = function ($arr, $key) {
        return isset($arr[$key]) && $arr[$key] !== null ? (string) $arr[$key] : '';
    };
    $num = function ($arr, $key) {
        return isset($arr[$key]) && $arr[$key] !== null && $arr[$key] !== '' ? (string) $arr[$key] : '';
    };
    // 泄漏量：C 端评论表单「泄漏量」（气泡/min）写入的 leak_rate，去空白；空值走默认样式
    $lr = trim($str($res, 'leak_rate'));
    // 执行标准：C 端评论表单多选确认后的拼接文本（exec_standard）。
    // 旧数据未存时兜底用模板原固定文本，保持记录外观不变
    $execStd = trim($str($res, 'exec_standard'));
    if ($execStd === '') {
        $execStd = 'TSG 92-2026、GB/T12242-2021、GB/T12243-2021';
    }

    return [
        // 记录编号：WHYH-LX-{order_code}（"WHYH-LX-" 为模板固定文本）
        'order_code'             => $str($wo, 'order_code'),
        'cust_name'              => $str($wo, 'cust_name'),
        'manufacturer'           => $str($wo, 'manufacturer'),
        'vavle_model'            => $str($wo, 'vavle_model'),
        'vavle_type'             => $str($wo, 'vavle_type'),
        'manu_no'                => $str($wo, 'manu_no'),
        'manu_date'              => $str($wo, 'manu_date'),
        'diameter_nominal'       => $num($wo, 'diameter_nominal'),
        'press_nominal'          => $num($wo, 'press_nominal'),
        'set_press'              => ($res['set_press'] ?? $wo['set_press'] ?? null) !== null && ($res['set_press'] ?? $wo['set_press'] ?? '') !== '' ? (string) ($res['set_press'] ?? $wo['set_press']) : '',
        'working_media'          => $str($wo, 'working_media'),
        'protected_equip_type'   => $str($wo, 'protected_equip_type') !== '' ? $str($wo, 'protected_equip_type') : $str($wo, 'equip_type'),
        'working_press'          => $num($wo, 'working_press'),
        'seat_size'              => $num($wo, 'seat_size'),
        'press_class_range_min'  => $num($wo, 'press_class_range_min'),
        'press_class_range_max'  => $num($wo, 'press_class_range_max'),
        // 校验方式：送检列表「安全阀信息」填写的 workorder.parity（离线校验/在线校验），result.parity 兜底
        'parity'                 => ($str($wo, 'parity') !== '' ? $str($wo, 'parity') : $str($res, 'parity')),
        // 模板预留的校验方式补充位，暂无数据来源，留空
        'parity_fun'             => '',
        'visual_examination'     => $str($res, 'visual_examination'),
        'disassembly_inspection' => $str($res, 'disassembly_inspection'),
        'test_media'             => $str($res, 'test_media'),
        'test_temprature'        => $str($res, 'test_temprature'),
        // 实际整定压力（第 1/2/3 次）：C 端抓取峰值写入的 testing_pressure1..3
        'set_pressure1'          => $num($res, 'testing_pressure1'),
        'set_pressure2'          => $num($res, 'testing_pressure2'),
        'set_pressure3'          => $num($res, 'testing_pressure3'),
        // 密封试验压力（试验压力）：结果优先，工单兜底
        'testing_pressure'       => ($res['tightness_press'] ?? $wo['tightness_press'] ?? null) !== null && ($res['tightness_press'] ?? $wo['tightness_press'] ?? '') !== '' ? (string) ($res['tightness_press'] ?? $wo['tightness_press']) : '',
        // 执行标准（原「检测依据」行）：${exec_standard}
        'exec_standard'          => $execStd,
        // 密封试验泄露率：模板行「密封试验泄露率：${bubble_count}； □ cm³/min」。
        // 未填写（旧数据 leak_rate 空）按默认样式展示「■ 0 泡/min」；
        // 有值填「■ {值} 泡/min」（■/□ 与模板固定文本的未选框同字符集）。
        'bubble_count'           => $lr !== '' ? ('■ ' . $lr . ' 泡/min') : '■ 0 泡/min',
        // 校验结论：合格 / 不合格（存盘时写入的 test_status，与报告同源）
        'test_status_text'       => $str($res, 'test_status'),
        'note'                   => $note,
        'inspector_time'         => $insTime ? date('Y-m-d', strtotime($insTime)) : '',
        // 审批日期与校验日期保持一致（与校验报告口径相同）
        'first_rev_sec_time'     => $insTime ? date('Y-m-d', strtotime($insTime)) : '',
    ];
}

/** 生成校验记录 docx（临时文件）；失败返回 ''，原因写 uploads/report/_error.log */
function build_record_docx($wo, $res)
{
    $tpl = __DIR__ . '/../uploads/tpl/tpl_check_record.docx';
    if (!is_file($tpl)) { record_log('记录模板不存在: ' . $tpl); return ''; }

    $fields = build_record_field_map($wo, $res);

    $tmpDir = __DIR__ . '/../uploads/report';
    @mkdir($tmpDir, 0777, true);

    // 签名 PNG（无签名用透明占位图盖掉模板预置占位图）
    $blank = file_get_contents(__DIR__ . '/../uploads/tpl/_blank.png');
    $signPath = inspector_sign_path(isset($wo['inspector']) ? $wo['inspector'] : '');
    $signBin = $signPath && is_file($signPath) ? file_get_contents($signPath) : $blank;
    $secPath = approver_sign_path();
    $secBin = $secPath && is_file($secPath) ? file_get_contents($secPath) : $blank;

    $tmpDocx = $tmpDir . '/_tmp_rec_' . md5((string) ($wo['order_code'] ?? '') . microtime()) . '.docx';
    if (!copy($tpl, $tmpDocx)) { record_log('记录模板复制失败'); return ''; }

    $zip = new ZipArchive();
    if ($zip->open($tmpDocx) !== true) { record_log('记录模板临时副本打开失败'); @unlink($tmpDocx); return ''; }

    // media 按 docPr descr 反查（不依赖 imageN 编号）
    $mediaSign = media_by_descr($zip, '_ph_sign');
    $mediaSec  = media_by_descr($zip, '_ph_sec');
    if (!$mediaSign) { record_log('记录模板缺 _ph_sign 占位图'); $zip->close(); @unlink($tmpDocx); return ''; }
    zip_replace_media($zip, $mediaSign, $signBin);
    if ($mediaSec) zip_replace_media($zip, $mediaSec, $secBin);
    else record_log('记录模板缺 _ph_sec 占位图，审核员签名将为空');

    $docXml = $zip->getFromName('word/document.xml');
    if (!$docXml) { record_log('记录模板 document.xml 读取失败'); $zip->close(); @unlink($tmpDocx); return ''; }

    // 替换文本占位符（签名图占位符名清除——图已由 media 覆盖承担）
    $resolve = function ($name) use ($fields) {
        if (preg_match('/^(inspector_sign|rev_sec_sign):width/', $name)) return '';
        if (array_key_exists($name, $fields)) return $fields[$name];
        return null;
    };
    $newXml = apply_placeholders(normalize_placeholders($docXml), $resolve);
    $dom = new DOMDocument();
    if (@$dom->loadXML($newXml) !== true) {
        record_log('记录模板替换后 XML 非法');
        $zip->close(); @unlink($tmpDocx); return '';
    }
    $zip->addFromString('word/document.xml', $newXml);
    $zip->close();
    return $tmpDocx;
}

/** 生成并落到缓存路径（C 端存盘时同步生成用）；失败返回 ''（不抛异常，不影响存盘） */
function generate_record_docx_cached($wo, $res)
{
    try {
        $code = (string) ($wo['order_code'] ?? '');
        if ($code === '') return '';
        $insTime = !empty($wo['inspector_time']) ? $wo['inspector_time'] : (!empty($res['create_time']) ? $res['create_time'] : '');
        $path = record_docx_path($code, $insTime);
        @mkdir(dirname($path), 0777, true);
        // 已有且比数据新则跳过
        $latest = max(strtotime($wo['update_time'] ?? '0'), strtotime($res['create_time'] ?? '0'));
        if (is_file($path) && filemtime($path) >= $latest) return $path;

        $tmp = build_record_docx($wo, $res);
        if ($tmp === '' || !is_file($tmp)) return '';
        if (!@rename($tmp, $path)) {
            if (!@copy($tmp, $path)) { @unlink($tmp); record_log('记录 docx 写入失败: ' . $path); return ''; }
            @unlink($tmp);
        }
        return $path;
    } catch (Exception $e) {
        record_log('校验记录生成异常: ' . $e->getMessage());
        return '';
    }
}

/** 记录生成失败原因（写 uploads/report/_error.log，与报告共用排查文件） */
function record_log($msg)
{
    @file_put_contents(__DIR__ . '/../uploads/report/_error.log', '[' . date('m-d H:i:s') . '] [record] ' . $msg . "\n", FILE_APPEND);
}
