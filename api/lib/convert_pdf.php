<?php
/** CLI：docx → PDF 转换（Word COM）
 *  用法：php.exe -f convert_pdf.php -- <src.docx> <dst.pdf>
 *  供 report.php 通过 proc_open 调用（Web 进程未加载 com_dotnet，转换放到 CLI 进程做）。
 *  注意：路径可为相对路径（相对调用方 cwd），本脚本先转为绝对路径 ——
 *  Word 是独立进程，工作目录与调用方不同，必须用绝对路径打开文件。
 */

error_reporting(E_ERROR | E_PARSE);

if ($argc < 3) {
    echo json_encode(['ok' => false, 'msg' => '参数不足']);
    exit(1);
}

$src = $argv[1];
$dst = $argv[2];

// 相对路径 → 绝对路径
if (!preg_match('/^[A-Za-z]:[\\\\\/]/', $src)) {
    $src = getcwd() . DIRECTORY_SEPARATOR . ltrim($src, '\\/');
}
if (!preg_match('/^[A-Za-z]:[\\\\\/]/', $dst)) {
    $dst = getcwd() . DIRECTORY_SEPARATOR . ltrim($dst, '\\/');
}
$src = str_replace('/', DIRECTORY_SEPARATOR, $src);
$dst = str_replace('/', DIRECTORY_SEPARATOR, $dst);

if (!is_file($src)) {
    echo json_encode(['ok' => false, 'msg' => '源文件不存在: ' . $src]);
    exit(1);
}

$word = null;
$doc = null;
try {
    $word = new COM('Word.Application');
    $word->Visible = false;
    $word->DisplayAlerts = 0;
    // 参数2 ConfirmConversions=false，参数3 ReadOnly=true
    $doc = $word->Documents->Open($src, false, true);
    if (!$doc) throw new Exception('Word 打开文档失败（返回 null）');
    // 17 = wdExportFormatPDF
    $doc->ExportAsFixedFormat($dst, 17);
    $doc->Close(false);
    $doc = null;
    echo json_encode(['ok' => is_file($dst)]);
} catch (Exception $e) {
    if ($doc) { try { $doc->Close(false); } catch (Exception $e2) { /* 忽略 */ } }
    if ($word) { try { $word->Quit(); } catch (Exception $e2) { /* 忽略 */ } }
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    exit(1);
}
if ($word) { try { $word->Quit(); } catch (Exception $e2) { /* 忽略 */ } }
