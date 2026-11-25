<?php
/** 校验记录 docx 下载服务（公开访问，无鉴权）
 *
 *  入口（与 index.php 平级，部署在 /inspection-api/record.php）：
 *    record.php?code={检验编号}  → 下载填充后的「安全阀校验记录」docx（附件下载）
 *
 *  C 端提交校验结果时（capi.php api_c_task_result）已同步生成缓存；此处缓存命中直接
 *  输出，缺失/过期则现场生成。模板：uploads/tpl/tpl_check_record.docx，
 *  生成逻辑见 lib/record_docx.php，缓存：uploads/report/{校验年月}/{编号}_record.docx。
 */

error_reporting(E_ERROR | E_PARSE);
require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/docx_tpl.php';
require_once __DIR__ . '/lib/record_docx.php';

/** HTML 错误页（下载页给浏览器看，不用 JSON） */
function record_html_error($msg)
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="zh"><head><meta charset="utf-8"><title>校验记录</title>'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>body{font-family:sans-serif;background:#f5f6f8;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}'
        . '.box{background:#fff;padding:40px 48px;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.08);text-align:center;color:#555}'
        . 'h2{margin:0 0 12px;color:#d03050;font-size:18px}p{margin:0;font-size:14px}</style></head>'
        . '<body><div class="box"><h2>无法下载校验记录</h2><p>' . htmlspecialchars($msg) . '</p></div></body></html>';
    exit;
}

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if ($code === '') record_html_error('缺少校验编号参数 code');

$wo = q_one('SELECT * FROM workorder WHERE order_code = ? AND is_del = 0 ORDER BY id DESC LIMIT 1', [$code]);
if (!$wo) record_html_error('未找到校验编号「' . htmlspecialchars($code) . '」对应的校验单');
$res = q_one('SELECT * FROM workorder_result WHERE workorder_id = ?', [$wo['id']]);
if (!$res) record_html_error('校验编号「' . htmlspecialchars($code) . '」尚未提交校验结果');

$insTime = $wo['inspector_time'] ?: ($res['create_time'] ?? '');
$path = record_docx_path($code, $insTime);
@mkdir(dirname($path), 0777, true);

// 缓存命中（文件存在且不比数据旧）直接输出，否则按模板现场生成
$latest = max(strtotime($wo['update_time'] ?: '0'), strtotime($res['create_time'] ?: '0'));
if (!is_file($path) || filemtime($path) < $latest) {
    $tmp = build_record_docx($wo, $res);
    if ($tmp === '' || !is_file($tmp)) {
        record_html_error('校验记录生成失败，请检查模板文件 uploads/tpl/tpl_check_record.docx 是否存在');
    }
    if (!@rename($tmp, $path)) {
        if (!@copy($tmp, $path)) {
            @unlink($tmp);
            record_html_error('校验记录文件写入失败，请检查 uploads/report 目录权限');
        }
        @unlink($tmp);
    }
}

$fname = '安全阀校验记录_' . $code . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header("Content-Disposition: attachment; filename=\"record_" . $code . ".docx\"; filename*=UTF-8''" . rawurlencode($fname));
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
readfile($path);
exit;
