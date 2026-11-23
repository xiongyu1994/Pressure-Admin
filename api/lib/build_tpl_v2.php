<?php
/** CLI：用 Word COM 一次性改造校验报告模板 → uploads/tpl/tpl_check_report_v2.docx
 *
 *  改造内容（media 覆盖方案的预置图）：
 *    1. 跨 run 拆分的图片占位符 ${erweima:width=21mm:height=21mm} → 标记 #QRPH#
 *       ${inspector_sign:width=24mm:height=12mm} → #SIGNPH#
 *       ${rev_sec_sign:width=24mm:height=12mm}  → 删除（机构章模板已自带，留空）
 *    2. 在标记处插入占位图片（品红=二维码、青色=签名，运行时按文件名覆盖 media 内容）
 *    3. 另存为 v2 并导出核对用 PDF；输出新增图片的 media 文件映射（JSON）
 */

error_reporting(E_ERROR | E_PARSE);

$base = dirname(__DIR__);                       // api 根目录
$tplSrc = $base . '/uploads/tpl/tpl_check_report.docx';
$tplDst = $base . '/uploads/tpl/tpl_check_report_v2.docx';
$chkPdf = $base . '/uploads/tpl/_v2_check.pdf';
$qrPh   = $base . '/uploads/tpl/_ph_qr.png';
$signPh = $base . '/uploads/tpl/_ph_sign.png';

if (!is_file($tplSrc)) die("模板不存在\n");

/* ---------- 1. 生成占位图片（GD） ---------- */
if (!function_exists('imagecreatetruecolor')) die("GD 不可用\n");
$im = imagecreatetruecolor(210, 210);
imagefilledrectangle($im, 0, 0, 209, 209, imagecolorallocate($im, 230, 0, 230));
imagestring($im, 5, 80, 95, 'QR', imagecolorallocate($im, 255, 255, 255));
imagepng($im, $qrPh); imagedestroy($im);
$im = imagecreatetruecolor(240, 120);
imagefilledrectangle($im, 0, 0, 239, 119, imagecolorallocate($im, 0, 190, 200));
imagestring($im, 5, 80, 50, 'SIGN', imagecolorallocate($im, 255, 255, 255));
imagepng($im, $signPh); imagedestroy($im);

/* ---------- 2. Word COM 改造 ---------- */
$word = new COM('Word.Application');
$word->Visible = false;
$word->DisplayAlerts = 0;
$doc = $word->Documents->Open($tplSrc);
if (!$doc) die("Word 打开失败\n");

/** 在某个 Range 的故事里替换全部 $find → $repl，返回替换次数（先全量替换标记，无法直接计数，用标记数统计） */
function find_replace_all($doc, $findText, $replText)
{
    // 遍历所有能含文本的位置：正文 + 各 Shape 的文本框（含页眉页脚内的 Shape）
    $ranges = [$doc->Content];
    $shapes = $doc->Shapes;
    for ($i = 1; $i <= $shapes->Count; $i++) {
        $sh = $shapes->Item($i);
        try { if ($sh->TextFrame->HasText) $ranges[] = $sh->TextFrame->TextRange; } catch (Exception $e) {}
    }
    $sections = $doc->Sections;
    for ($s = 1; $s <= $sections->Count; $s++) {
        $sec = $sections->Item($s);
        foreach (['Headers', 'Footers'] as $hf) {
            $coll = $sec->{$hf};
            for ($k = 1; $k <= $coll->Count; $k++) {
                $hfItem = $coll->Item($k);
                $ranges[] = $hfItem->Range;
                $hShapes = $hfItem->Shapes;
                for ($i = 1; $i <= $hShapes->Count; $i++) {
                    try { if ($hShapes->Item($i)->TextFrame->HasText) $ranges[] = $hShapes->Item($i)->TextFrame->TextRange; } catch (Exception $e) {}
                }
            }
        }
    }
    $count = 0;
    foreach ($ranges as $rng) {
        $f = $rng->Find;
        $f->ClearFormatting();
        $f->Text = $findText;
        $f->Replacement->ClearFormatting();
        $f->Forward = true;
        $f->Wrap = 0;
        try {
            if ($f->Execute(false, false, false, false, false, false, true, 0, false, $replText, 2)) $count++;
        } catch (Exception $e) { /* 忽略该 Range */ }
    }
    return $count;
}

/** 在所有故事里查找标记 $marker，把每个出现处替换为 $img（内嵌图片，宽高 mm），返回插入数 */
function replace_marker_with_image($doc, $marker, $img, $wMm, $hMm)
{
    $pt = 2.834645;                              // mm → pt
    $inserted = 0;
    for ($pass = 0; $pass < 10; $pass++) {
        $ranges = [$doc->Content];
        $shapes = $doc->Shapes;
        for ($i = 1; $i <= $shapes->Count; $i++) {
            try { if ($shapes->Item($i)->TextFrame->HasText) $ranges[] = $shapes->Item($i)->TextFrame->TextRange; } catch (Exception $e) {}
        }
        $found = null;
        foreach ($ranges as $rng) {
            $f = $rng->Find;
            $f->ClearFormatting();
            $f->Text = $marker;
            $f->Forward = true;
            $f->Wrap = 0;
            if ($f->Execute()) { $found = $rng; break; }
        }
        if (!$found) break;
        // found 现在指向标记文本；删除标记后原位插入内嵌图片
        $found->Text = '';
        $pic = $found->InlineShapes->AddPicture($img, false, true, $found);
        $pic->LockAspectRatio = 0;
        $pic->Width  = $wMm * $pt;
        $pic->Height = $hMm * $pt;
        $inserted++;
    }
    return $inserted;
}

echo "1) erweima → 标记: " . find_replace_all($doc, '${erweima:width=21mm:height=21mm}', '#QRPH#') . "\n";
echo "2) inspector_sign → 标记: " . find_replace_all($doc, '${inspector_sign:width=24mm:height=12mm}', '#SIGNPH#') . "\n";
echo "3) rev_sec_sign → 删除: " . find_replace_all($doc, '${rev_sec_sign:width=24mm:height=12mm}', '') . "\n";

echo "4) 插入二维码占位图: " . replace_marker_with_image($doc, '#QRPH#', $qrPh, 21, 21) . "\n";
echo "5) 插入签名占位图: " . replace_marker_with_image($doc, '#SIGNPH#', $signPh, 24, 12) . "\n";

// 残留标记检查
$left = find_replace_all($doc, '#QRPH#', '#QRPH#') + find_replace_all($doc, '#SIGNPH#', '#SIGNPH#');
echo "6) 残留标记: " . $left . "\n";

$doc->SaveAs2($tplDst, 16);          // wdFormatXMLDocument
try { $doc->ExportAsFixedFormat($chkPdf, 17); } catch (Exception $e) { echo "导出核对 PDF 失败: " . $e->getMessage() . "\n"; }
$doc->Close(false);
$word->Quit();

/* ---------- 3. 解析 v2：占位图 media 文件映射 ---------- */
$zip = new ZipArchive();
$zip->open($tplDst);
$xml  = $zip->getFromName('word/document.xml');
$rels = $zip->getFromName('word/_rels/document.xml.rels');
$zip->close();

$relMap = [];
preg_match_all('/<Relationship Id="([^"]+)"[^>]*Target="([^"]+)"/', $rels, $m, PREG_SET_ORDER);
foreach ($m as $x) $relMap[$x[1]] = $x[2];

$qrFiles = []; $signFiles = [];
preg_match_all('/<wp:extent cx="(\d+)" cy="(\d+)"\/>.{0,4000}?r:embed="(rId\d+)"/s', $xml, $mm, PREG_SET_ORDER);
foreach ($mm as $x) {
    list(, $cx, $cy, $rid) = $x;
    $target = isset($relMap[$rid]) ? $relMap[$rid] : '?';
    if ($cx == (int) round(21 * 36000) && $cy == (int) round(21 * 36000)) $qrFiles[$target] = 1;
    if ($cx == (int) round(24 * 36000) && $cy == (int) round(12 * 36000)) $signFiles[$target] = 1;
}
echo "QR_MEDIA=" . json_encode(array_keys($qrFiles)) . "\n";
echo "SIGN_MEDIA=" . json_encode(array_keys($signFiles)) . "\n";
echo "v2 大小: " . filesize($tplDst) . " bytes\n";
