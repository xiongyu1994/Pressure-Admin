<?php
/** PDF 转换队列 worker（由计划任务在交互会话中触发，隐藏窗口运行）
 *
 *  为什么不直接在 Web 进程里转：Apache(php-cgi) 会话下 WPS/Word 的 COM
 *  激活失败（0x80040154），而计划任务（仅当用户登录时运行）所在交互
 *  会话中 COM 可用 —— 已实测验证。
 *
 *  流程：扫描 uploads/report/_queue/*.job（JSON: {docx, pdf}）
 *        → COM(KWPS/Word) 打开 docx → ExportAsFixedFormat 转 PDF
 *        → 成功删 job；失败写 docx 同名 .err（保留 job 供排查）
 *
 *  注意：服务器需保持桌面登录状态（计划任务"仅在用户登录时运行"）。
 */

error_reporting(E_ERROR | E_PARSE);

$base = dirname(__DIR__);
$queueDir = $base . '/uploads/report/_queue';
@mkdir($queueDir, 0777, true);

/* 单实例锁：已有一个 worker 在跑则直接退出（它会循环清空队列） */
$lockFp = fopen($queueDir . '/_worker.lock', 'c');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) exit(0);

/* COM 实例：优先 WPS 文字（本机实际安装的），兼容 MS Word */
$com = null;
foreach (['KWPS.Application', 'Word.Application'] as $progId) {
    try {
        $com = new COM($progId);
        break;
    } catch (Exception $e) { /* 尝试下一个 */ }
}
if (!$com) {
    foreach (glob($queueDir . '/*.job') as $jf) {
        file_put_contents($jf . '.err', '[' . date('H:i:s') . '] COM 创建失败（KWPS/Word 均不可用）');
    }
    exit(1);
}
$com->Visible = false;
$com->DisplayAlerts = 0;

/** 转换单个 job；返回 [ok, err]。$title 写入 PDF Title 元数据（浏览器标签页标题） */
function convert_one($com, $src, $dst, $title = '')
{
    $doc = null;
    try {
        $srcAbs = realpath($src);
        if (!$srcAbs) return [false, 'docx 不存在: ' . $src];
        $dst = str_replace('/', DIRECTORY_SEPARATOR, $dst);
        $doc = $com->Documents->Open($srcAbs, false, true);
        if (!$doc) return [false, 'WPS/Word 打开文档失败（返回 null）'];
        if ($title !== '') {
            try {
                // PHP COM 字符串按系统 ANSI 代码页（GBK）编组，UTF-8 须先转 GBK
                $titleGbk = iconv('UTF-8', 'GB2312', $title);
                $doc->BuiltInDocumentProperties->Item('Title')->Value = $titleGbk;
            } catch (Exception $e) { /* 标题写入失败不影响转换 */ }
        }
        try {
            $doc->ExportAsFixedFormat($dst, 17);   // wdExportFormatPDF
        } catch (Exception $e) {
            $doc->SaveAs2($dst, 17);               // 备用：wdFormatPDF
        }
        if (!is_file($dst)) return [false, '导出后 PDF 未生成'];
        return [true, ''];
    } catch (Exception $e) {
        return [false, $e->getMessage()];
    } finally {
        if ($doc) { try { $doc->Close(false); } catch (Exception $e) { /* 忽略 */ } }
    }
}

/* 主循环：处理队列直到连续两轮为空（防止与写 job 的瞬间错开） */
$emptyChecks = 0;
while (true) {
    clearstatcache();
    $jobs = glob($queueDir . '/*.job');
    if (!$jobs) {
        $emptyChecks++;
        if ($emptyChecks >= 2) break;
        sleep(1);
        continue;
    }
    $emptyChecks = 0;
    foreach ($jobs as $jf) {
        $job = json_decode((string) file_get_contents($jf), true);
        if (!$job || empty($job['docx']) || empty($job['pdf'])) {
            @unlink($jf);
            continue;
        }
        list($ok, $err) = convert_one($com, $job['docx'], $job['pdf'], (string) ($job['title'] ?? ''));
        if (!$ok) {
            file_put_contents($jf . '.err', '[' . date('H:i:s') . '] ' . $err);
            /* 失败时保留 job，避免 report.php 一直轮询等待 */
            rename($jf, $jf . '.fail');
        } else {
            @unlink($jf);
        }
    }
}

try { $com->Quit(); } catch (Exception $e) { /* 忽略 */ }
