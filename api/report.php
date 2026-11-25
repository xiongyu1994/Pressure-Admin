<?php
/** 校验报告二维码 / PDF 预览服务（公开访问，无鉴权）
 *
 *  入口（与 index.php 平级，部署在 /inspection-api/report.php）：
 *    report.php?code={检验编号}        → 浏览器内直接预览校验报告 PDF（首次访问现场生成并缓存）
 *    report.php?qr=1&code={检验编号}   → 返回报告链接二维码 PNG（C 端存盘成功后展示给操作员扫码）
 *
 *  流程：uploads/tpl/tpl_check_report_v2.docx 模板（预置二维码/签名占位图）
 *        → ZipArchive 覆盖 media 内容 + 替换文本占位符
 *        → 写转换 job 到 _queue → 计划任务 worker（交互会话 COM 可用）转 PDF
 *        → uploads/report/{校验年月}/{检验编号}.pdf 缓存（按年月分目录、永久留存）→ inline 输出
 *
 *  二维码地址使用局域网 IP（SERVER_ADDR，回环时取本机 hostname 解析），
 *  手机扫码后即可在同一局域网内打开 PDF 报告。
 */

error_reporting(E_ERROR | E_PARSE);
require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/phpqrcode.php';
require_once __DIR__ . '/lib/docx_tpl.php';   // docx 模板填充共用工具（校验记录 record.php 也用）

/** php.exe 路径（worker/VBS 用）：Web 下 PHP_BINARY 通常是 php-cgi.exe，取同目录的 php.exe */
if (!defined('PHP_CLI_PATH')) {
    $cli = PHP_BINARY;
    if (stripos(basename($cli), 'php.exe') === false) {
        $alt = dirname($cli) . '/php.exe';
        if (is_file($alt)) $cli = $alt;
    }
    define('PHP_CLI_PATH', $cli);
}

/* ============================ 工具函数 ============================ */

/** HTML 错误页（扫码页给浏览器看，不用 JSON） */
function html_error($msg)
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="zh"><head><meta charset="utf-8"><title>校验报告</title>'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>body{font-family:sans-serif;background:#f5f6f8;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}'
        . '.box{background:#fff;padding:40px 48px;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.08);text-align:center;color:#555}'
        . 'h2{margin:0 0 12px;color:#d03050;font-size:18px}p{margin:0;font-size:14px}</style></head>'
        . '<body><div class="box"><h2>无法查看报告</h2><p>' . htmlspecialchars($msg) . '</p></div></body></html>';
    exit;
}

/** 报告访问基地址（手机扫码可达）：优先 SERVER_ADDR（非回环），否则解析本机 hostname */
function report_base_url()
{
    $ip = (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1' && $_SERVER['SERVER_ADDR'] !== '::1')
        ? $_SERVER['SERVER_ADDR']
        : gethostbyname(gethostname());
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/inspection-api/report.php')), '/');
    return 'http://' . $ip . $base;
}

/** 按检验编号查工单（取最新一条） */
function find_workorder($code)
{
    return q_one(
        'SELECT * FROM workorder WHERE order_code = ? AND is_del = 0 ORDER BY id DESC LIMIT 1',
        [$code]
    );
}

/* ============================ 模板填充 ============================ */

/*
 * 模板采用 media 覆盖方案：tpl_check_report_v3.docx 由 Word COM 预先改造
 * （lib/build_tpl_v2.php 生成 v2，lib/build_tpl_v3.php 在其上加审批签名位）——
 * 图片占位符处已预置固定占位图，运行时只需覆盖 media 内容 + 替换文本占位符。
 *
 * 模板内三张占位图通过图片描述符（docPr descr）标识，不依赖 imageN 编号
 * （Word 另存会重排编号，实测 v3 中审批图变成了 image3.png）：
 *   descr="_ph_qr"    二维码（21mm×21mm，2 处）
 *   descr="_ph_sign"  校验员签名（24mm×12mm）
 *   descr="_ph_sec"   审核员（审批）签名（24mm×12mm）
 *   image2.png        机构章（模板自带，不动）
 */
define('TPL_REPORT', __DIR__ . '/uploads/tpl/tpl_check_report_v3.docx');
define('WORKER_TASK', 'InspectionPdfWorker');   // PDF 转换计划任务名（worker 见 lib/convert_worker.php）

/** 组装填充数据（占位符名 → 值） */
function build_field_map($wo, $res, $qrUrl)
{
    $status = isset($res['test_status']) ? $res['test_status'] : '';
    $statusText = $status === '合格' ? '该安全阀经校验合格'
        : ($status === '不合格' ? '该安全阀经校验不合格，不能投入使用。' : '');

    $insTime = !empty($wo['inspector_time']) ? $wo['inspector_time'] : (!empty($res['create_time']) ? $res['create_time'] : '');
    $period = max(1, (int) ($wo['period_years'] ?: 1));
    // 下次校验 = 校验日期 + 周期年 − 1 天（有效期满为上一日，与铭牌打标口径一致：
    // 2026-04-21 → 2027-04-20、2026-09-23 → 2027-09-22）
    $nextTime = $insTime ? date('Y-m-d', strtotime($insTime . ' +' . $period . ' year -1 day')) : '';

    // 使用单位地址：unit 表按单位名称匹配
    $address = '';
    try {
        $address = (string) q_scalar('SELECT address FROM unit WHERE name = ? LIMIT 1', [$wo['cust_name']]);
    } catch (Exception $e) { /* 无 unit 匹配则留空 */ }

    // 备注：备注字段只应存备注原文。历史数据曾把执行标准拼进来
    //（"执行标准：…；备注：xxx"），此处兜底剥离，只保留备注内容。
    $note = (string) ($res['note'] ?? '');
    if (strpos($note, '执行标准：') === 0) {
        $note = preg_match('/；备注：(.*)$/u', $note, $mm) ? trim($mm[1]) : '';
    }

    return [
        'order_code'            => (string) ($wo['order_code'] ?: ''),
        'cust_name'             => (string) ($wo['cust_name'] ?: ''),
        'cust_address'          => $address,
        'cust_client'           => (string) ($wo['client'] ?: ''),
        'cust_client_tel'       => (string) ($wo['client_tel'] ?: ''),
        'manufacturer'          => (string) ($wo['manufacturer'] ?: ''),
        'vavle_type'            => (string) ($wo['vavle_type'] ?: ''),
        'vavle_model'           => (string) ($wo['vavle_model'] ?: ''),
        'manu_no'               => (string) ($wo['manu_no'] ?: ''),
        'protected_equip_type'  => (string) ($wo['protected_equip_type'] ?: $wo['equip_type'] ?: ''),
        'working_media'         => (string) ($wo['working_media'] ?: ''),
        'set_press'             => ($res['set_press'] ?? $wo['set_press']) !== null ? (string) ($res['set_press'] ?? $wo['set_press']) : '',
        'tightness_press'       => ($res['tightness_press'] ?? $wo['tightness_press']) !== null ? (string) ($res['tightness_press'] ?? $wo['tightness_press']) : '',
        'press_class_range_min' => $wo['press_class_range_min'] !== null ? (string) $wo['press_class_range_min'] : '',
        'press_class_range_max' => $wo['press_class_range_max'] !== null ? (string) $wo['press_class_range_max'] : '',
        'test_media'            => (string) ($res['test_media'] ?? ''),
        // 校验方式：取送检列表「安全阀信息」填写的 workorder.parity（离线校验/在线校验），
        // result.parity 只在工单未填时兜底（C 端不再上传该字段）
        'parity'                => (string) (($wo['parity'] ?? '') !== '' ? $wo['parity'] : ($res['parity'] ?? '')),
        'is_rbi'                => '否',
        'test_status_text'      => $statusText,
        'jianxiuqingkuang'      => (string) ($res['maintenance_info'] ?? ''),
        'note'                  => $note,
        'inspector_time'        => $insTime ? date('Y-m-d', strtotime($insTime)) : '',
        'last_inspector_time'   => $nextTime,
        // 审批日期与校验日期保持一致（取同一天，不单独记审批时间）
        'first_rev_sec_time'    => $insTime ? date('Y-m-d', strtotime($insTime)) : '',
        '__qr_url'              => $qrUrl,
        '__inspector_sign'      => inspector_sign_path($wo['inspector']),
    ];
}

/** 生成失败时记录原因（uploads/report/_error.log），便于排查 */
function gen_log($msg)
{
    file_put_contents(__DIR__ . '/uploads/report/_error.log', '[' . date('H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

/**
 * 按模板生成填充后的校验报告 docx（二维码/签名已嵌入、占位符已替换）。
 * 成功返回临时 docx 的绝对路径；失败返回 ''（失败时会清理临时文件并写日志）。
 * B 端「校验报告 → 下载」直接输出该文件，PDF 流程也复用它。
 */
function build_report_docx($wo, $res)
{
    $tpl = TPL_REPORT;
    if (!is_file($tpl)) { gen_log('模板不存在: ' . $tpl); return ''; }

    $url = report_base_url() . '/report.php?code=' . urlencode($wo['order_code']);
    $fields = build_field_map($wo, $res, $url);

    $tmpDir = __DIR__ . '/uploads/report';
    @mkdir($tmpDir, 0777, true);

    // 1. 生成二维码 PNG
    $qrPng = $tmpDir . '/_qr_' . md5($url) . '.png';
    try {
        QRcode::png($fields['__qr_url'], $qrPng, QR_ECLEVEL_M, 7, 2);
    } catch (Exception $e) {
        gen_log('二维码生成失败: ' . $e->getMessage());
        return '';
    }
    $qrBin = is_file($qrPng) ? file_get_contents($qrPng) : null;
    @unlink($qrPng);
    if (!$qrBin) { gen_log('二维码 PNG 为空'); return ''; }

    // 2. 签名 PNG（无签名用透明占位图盖掉模板预置占位图）
    $blank = file_get_contents(__DIR__ . '/uploads/tpl/_blank.png');
    $signBin = $fields['__inspector_sign'] && is_file($fields['__inspector_sign'])
        ? file_get_contents($fields['__inspector_sign']) : $blank;
    // 审批（审核员）签名：取 sys_user 中身份=审核员、有签名图的最新账号
    $secSign = approver_sign_path();
    $secSignBin = $secSign && is_file($secSign) ? file_get_contents($secSign) : $blank;

    // 3. 复制模板为临时 docx（不碰模板原件）
    $tmpDocx = $tmpDir . '/_tmp_' . md5($wo['order_code'] . microtime()) . '.docx';
    if (!copy($tpl, $tmpDocx)) { gen_log('模板复制失败'); return ''; }

    // 4. 打开临时副本：覆盖 media（二维码 + 两个签名）+ 替换文本占位符
    $zip = new ZipArchive();
    if ($zip->open($tmpDocx) !== true) { gen_log('临时 docx 打开失败'); @unlink($tmpDocx); return ''; }

    // media 文件名由模板内图片描述符（docPr descr）反查：Word 另存会重排 imageN 编号，
    // 写死文件名不可靠。descr 约定：_ph_qr=二维码、_ph_sign=校验员签名、_ph_sec=审核员签名
    $mediaQr   = media_by_descr($zip, '_ph_qr');
    $mediaSign = media_by_descr($zip, '_ph_sign');
    $mediaSec  = media_by_descr($zip, '_ph_sec');
    if (!$mediaQr || !$mediaSign) {
        gen_log('模板占位图缺失 qr=' . $mediaQr . ' sign=' . $mediaSign);
        $zip->close(); @unlink($tmpDocx); return '';
    }
    if (!zip_replace_media($zip, $mediaQr, $qrBin)) {
        gen_log('二维码 media 条目缺失: ' . $mediaQr);
        $zip->close(); @unlink($tmpDocx); return '';
    }
    zip_replace_media($zip, $mediaSign, $signBin);
    if ($mediaSec) zip_replace_media($zip, $mediaSec, $secSignBin);
    else gen_log('模板无审批签名占位图（_ph_sec），审批栏将为空');

    $docXml = $zip->getFromName('word/document.xml');
    if (!$docXml) { gen_log('document.xml 读取失败'); $zip->close(); @unlink($tmpDocx); return ''; }

    // 5. 替换文本占位符（含被 Word 拆分进多个 run 的占位符；图片占位符名替换为空）
    // 模板里 ${first_rev_sec_time} 有两处：审批格内（应显示日期）+ 印章格下方遗留副本（应留空，
    // 否则 PDF 里会多出一个孤立的日期），故只填第一处、其余置空。
    $revSecCnt = 0;
    $resolve = function ($name) use ($fields, &$revSecCnt) {
        if (preg_match('/^(erweima|inspector_sign|rev_sec_sign):width/', $name)) {
            return '';   // 图片占位符已由模板预置图承担，文字清除
        }
        if ($name === 'first_rev_sec_time') {
            $revSecCnt++;
            return $revSecCnt === 1 ? (string) ($fields['first_rev_sec_time'] ?? '') : '';
        }
        if (array_key_exists($name, $fields) && strpos($name, '__') !== 0) {
            return $fields[$name];
        }
        return null;
    };
    $newXml = apply_placeholders(normalize_placeholders($docXml), $resolve);
    // XML 合法性自检
    $dom = new DOMDocument();
    if (@$dom->loadXML($newXml) !== true) {
        gen_log('替换后的 document.xml 不是合法 XML');
        $zip->close(); @unlink($tmpDocx); return '';
    }
    $zip->addFromString('word/document.xml', $newXml);
    $zip->close();

    return $tmpDocx;
}

/** 生成报告 PDF；成功返回 true */
function generate_pdf($wo, $res, $pdfPath)
{
    $tmpDocx = build_report_docx($wo, $res);
    if ($tmpDocx === '' || !is_file($tmpDocx)) return false;
    $tmpDir = dirname($tmpDocx);

    // 6. 队列 + 计划任务 worker 转 PDF
    //    Apache(php-cgi) 会话下 WPS/Word COM 激活失败（0x80040154），
    //    实测计划任务（交互会话）里 COM 可用 —— 转换放到 worker 队列做：
    //    写 job 文件 → schtasks /Run 触发 worker → 轮询等待 PDF 产出。
    $queueDir = $tmpDir . '/_queue';
    @mkdir($queueDir, 0777, true);
    $jobFile = $queueDir . '/' . md5($wo['order_code'] . microtime()) . '.job';
    file_put_contents($jobFile, json_encode(['docx' => $tmpDocx, 'pdf' => $pdfPath, 'title' => '武汉翼华校验报告']));

    ensure_worker_task();
    unset($out);
    exec('schtasks /Run /TN "' . WORKER_TASK . '" 2>&1', $out, $rc);
    if ($rc !== 0) {
        // 任务实例已在运行时 /Run 会失败：worker 会循环清空队列，稍等重试一次
        sleep(2);
        unset($out);
        exec('schtasks /Run /TN "' . WORKER_TASK . '" 2>&1', $out, $rc);
    }

    $deadline = time() + 40;
    while (!is_file($pdfPath) && time() < $deadline) sleep(1);

    if (!is_file($pdfPath)) {
        $errFile = $jobFile . '.err';
        $reason = is_file($errFile) ? substr((string) file_get_contents($errFile), 0, 300) : 'worker 未在期限内产出 PDF';
        gen_log('worker 转换失败: ' . $reason . ' | schtasks rc=' . $rc);
        @unlink($tmpDocx);
        return false;
    }

    @unlink($tmpDocx);
    return true;
}

/** 确保 PDF 转换计划任务存在（首次访问时自动创建；VBS 隐藏窗口启动 worker） */
function ensure_worker_task()
{
    $vbs = __DIR__ . '/lib/convert_task.vbs';
    if (!is_file($vbs)) {
        $phpExe = str_replace('/', '\\', PHP_CLI_PATH);
        $worker = str_replace('/', '\\', __DIR__ . '/lib/convert_worker.php');
        file_put_contents($vbs,
            'CreateObject("WScript.Shell").Run """' . $phpExe . '"" -f ""' . $worker . '""", 0, False'
        );
    }
    exec('schtasks /Query /TN "' . WORKER_TASK . '" 2>nul', $o, $rc);
    if ($rc !== 0) {
        $tr = '\\"C:\\Windows\\System32\\wscript.exe\\" \\"' . str_replace('/', '\\', $vbs) . '\\"';
        $cmd = 'schtasks /Create /TN "' . WORKER_TASK . '" /TR "' . $tr . '" /SC ONCE /ST 23:59 /F';
        exec($cmd, $o2, $rc2);
        if ($rc2 !== 0) gen_log('创建计划任务失败: rc=' . $rc2 . ' ' . implode(' ', $o2));
    }
}

/* ============================ 入口 ============================ */

/**
 * 报告存储路径：按校验年月分目录 uploads/report/{YYYYMM}/{编号}.pdf。
 * 月份取校验时间（缺失回退结果提交时间）；报告生成后永久留存、系统不做任何自动删除，
 * 磁盘按 ~0.23MB/份 估算，两年 10 万份约 23GB，按月目录便于整月归档/备份。
 */
function report_pdf_path($code, $insTime)
{
    $m = $insTime ? date('Ym', strtotime($insTime)) : date('Ym');
    return __DIR__ . '/uploads/report/' . $m . '/' . $code . '.pdf';
}

/** 报告 docx 缓存路径（B 端「下载」用）：uploads/report/{YYYYMM}/{编号}.docx */
function report_docx_path($code, $insTime)
{
    $m = $insTime ? date('Ym', strtotime($insTime)) : date('Ym');
    return __DIR__ . '/uploads/report/' . $m . '/' . $code . '.docx';
}

/**
 * 查找已缓存的报告 PDF（供扫码直接读取，命中即不再重新生成）：
 * 校验年月目录 → 旧版根目录（历史文件兼容）→ 兜底扫全部年月子目录
 * （如手动迁移/补抓导致文件不在校验月目录下也能找到）。
 * 返回绝对路径；未找到返回 ''。
 */
function find_cached_pdf($code, $insTime)
{
    $base = __DIR__ . '/uploads/report';
    if ($insTime) {
        $p = $base . '/' . date('Ym', strtotime($insTime)) . '/' . $code . '.pdf';
        if (is_file($p)) return $p;
    }
    $p = $base . '/' . $code . '.pdf';          // 旧版（不分目录）缓存兼容
    if (is_file($p)) return $p;
    foreach (glob($base . '/20*', GLOB_ONLYDIR) as $d) {
        $p = $d . '/' . $code . '.pdf';
        if (is_file($p)) return $p;
    }
    return '';
}

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if ($code === '') html_error('缺少报告编号参数 code');

if (!empty($_GET['qr'])) {
    header('Content-Type: image/png');
    QRcode::png(report_base_url() . '/report.php?code=' . urlencode($code), false, QR_ECLEVEL_M, 7, 2);
    exit;
}

$wo = find_workorder($code);
if (!$wo) html_error('未找到校验编号「' . htmlspecialchars($code) . '」对应的校验单');
$res = q_one('SELECT * FROM workorder_result WHERE workorder_id = ?', [$wo['id']]);
if (!$res) html_error('校验编号「' . htmlspecialchars($code) . '」尚未提交校验结果');

$insTime = $wo['inspector_time'] ?: ($res['create_time'] ?? '');

/* ---------------- 下载 docx：report.php?code=xxx&fmt=docx ---------------- */
if (isset($_GET['fmt']) && $_GET['fmt'] === 'docx') {
    $docxPath = report_docx_path($code, $insTime);
    @mkdir(dirname($docxPath), 0777, true);
    $latestData = max(strtotime($wo['update_time'] ?: '0'), strtotime($res['create_time'] ?: '0'));

    // 缓存命中（文件存在且不比数据旧）直接输出，否则按模板现场生成
    if (!is_file($docxPath) || filemtime($docxPath) < $latestData) {
        $tmp = build_report_docx($wo, $res);
        if ($tmp === '' || !is_file($tmp)) {
            html_error('报告生成失败，请检查模板文件 uploads/tpl/tpl_check_report_v3.docx 是否存在');
        }
        if (!@rename($tmp, $docxPath)) {
            // 跨盘/被占用时退化为复制
            if (!@copy($tmp, $docxPath)) {
                @unlink($tmp);
                html_error('报告文件写入失败，请检查 uploads/report 目录权限');
            }
            @unlink($tmp);
        }
    }

    $fname = '安全阀校验报告_' . $code . '.docx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header("Content-Disposition: attachment; filename=\"report_" . $code . ".docx\"; filename*=UTF-8''" . rawurlencode($fname));
    header('Content-Length: ' . filesize($docxPath));
    header('Cache-Control: no-store');
    readfile($docxPath);
    exit;
}

$pdfPath = report_pdf_path($code, $insTime);
@mkdir(dirname($pdfPath), 0777, true);

// 缓存：已有 PDF 且比数据新（存盘未更新过）直接读缓存；否则重新生成到校验年月目录
$latest = max(strtotime($wo['update_time'] ?: '0'), strtotime($res['create_time'] ?: '0'));
$cached = find_cached_pdf($code, $insTime);
if ($cached !== '' && filemtime($cached) >= $latest) {
    $pdfPath = $cached;
} else {
    // 过期/不存在的旧缓存删掉，统一收敛到年月目录，避免新旧两份并存
    if ($cached !== '') @unlink($cached);
    if (!generate_pdf($wo, $res, $pdfPath)) {
        html_error('报告生成失败，请联系管理员检查服务器 Word COM 环境');
    }
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="check_report_' . $code . '.pdf"');
header('Content-Length: ' . filesize($pdfPath));
readfile($pdfPath);
exit;
