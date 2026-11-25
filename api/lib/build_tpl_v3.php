<?php
/** CLI：v2 → v3 模板改造（新增「审批」签名占位图 + 签名标签放大加粗）
 *
 *  v2 已有：image1.png=二维码、image3.png=校验员签名（运行时覆盖 media）
 *  「审批」格的 ${rev_sec_sign:...} 占位符跨段落，Word Find 找不到，运行时也只置空，
 *  所以这里直接清空该单元格文本，原位插入第三张预置占位图（运行时覆盖为审核员签名）。
 *
 *  产物：uploads/tpl/tpl_check_report_v3.docx + 新增 media 文件名（stdout，供 report.php 用）
 */

error_reporting(E_ERROR | E_PARSE);

$base = dirname(__DIR__);
$src  = $base . '/uploads/tpl/tpl_check_report_v2.docx';
$dst  = $base . '/uploads/tpl/tpl_check_report_v3.docx';
$chk  = $base . '/uploads/tpl/_v3_check.pdf';
$ph   = $base . '/uploads/tpl/_ph_sec.png';

if (!is_file($src)) die("v2 模板不存在\n");

/* ---------- 1. 审批占位图（黄色，内容与签名占位图不同 → Word 会存成新 media 文件） ---------- */
$im = imagecreatetruecolor(240, 120);
imagefilledrectangle($im, 0, 0, 239, 119, imagecolorallocate($im, 245, 190, 0));
imagestring($im, 5, 85, 50, 'SEC', imagecolorallocate($im, 120, 90, 0));
imagepng($im, $ph);
imagedestroy($im);

/* ---------- 2. 记录改造前的 media 列表 ---------- */
function media_list($file)
{
    $z = new ZipArchive();
    $z->open($file);
    $out = [];
    for ($i = 0; $i < $z->numFiles; $i++) {
        $n = $z->getNameIndex($i);
        if (strpos($n, 'word/media/') === 0) $out[] = $n;
    }
    $z->close();
    return $out;
}
$before = media_list($src);

/* ---------- 3. Word COM 改造 ---------- */
$word = new COM('Word.Application');
$word->Visible = false;
$word->DisplayAlerts = 0;
$doc = $word->Documents->Open($src);
if (!$doc) die("Word 打开失败\n");

$pt = 2.834645;                     // mm → pt
$inserted = 0;
echo "改造前 InlineShapes: ", $doc->InlineShapes->Count, "\n";

// 3.1 审批签名：${rev_sec_sign} 在浮动文本框（wps:txbx）内，Word 不渲染其中的内嵌图 →
//     删掉该文本框，改为在「审批」右侧单元格插入内嵌占位图
$kw = 'rev_sec_sign';
$removedShape = 0;
for ($i = $doc->Shapes->Count; $i >= 1; $i--) {
    try {
        $sh = $doc->Shapes->Item($i);
        if (!$sh->TextFrame->HasText) continue;
        if (strpos((string) $sh->TextFrame->TextRange->Text, $kw) === false) continue;
        echo "删除审批签名文本框 #$i (w=", round((float) $sh->Width, 1), "pt)\n";
        $sh->Delete();
        $removedShape++;
    } catch (Exception $e) { /* 忽略 */ }
}
if (!$removedShape) echo "!! 未找到含 rev_sec_sign 的文本框\n";
echo "删文本框后 InlineShapes: ", $doc->InlineShapes->Count, "\n";

// 定位「审批」标签单元格，在右侧单元格插入占位图（collapse 到单元格起点，避免插到结束标记外）
$kwApprove = iconv('UTF-8', 'GBK//IGNORE', '审批');
$tables = $doc->Tables;
for ($t = 1; $t <= $tables->Count && !$inserted; $t++) {
    $tbl = $tables->Item($t);
    $rows = $tbl->Rows->Count;
    for ($r = 1; $r <= $rows && !$inserted; $r++) {
        for ($c = 1; $c <= 8 && !$inserted; $c++) {
            try {
                $cell = $tbl->Cell($r, $c);
                if (strpos((string) $cell->Range->Text, $kwApprove) === false) continue;
                $rng = $tbl->Cell($r, $c + 1)->Range;
                $rng->Collapse(1);                   // wdCollapseStart：插到单元格开头
                $pic = $rng->InlineShapes->AddPicture($ph, false, true, $rng);
                $pic->LockAspectRatio = 0;
                $pic->Width  = 24 * $pt;
                $pic->Height = 12 * $pt;
                // 该格段落带负左缩进（字符单位 leftChars=-872 → -3.7cm），图片会被推到页面外 → 归零
                $pf = $tbl->Cell($r, $c + 1)->Range->ParagraphFormat;
                try {
                    $pf->CharacterUnitLeftIndent = 0;      // 字符单位缩进（leftChars）优先，必须单独设
                    $pf->CharacterUnitFirstLineIndent = 0;
                    $pf->LeftIndent = 0;
                    $pf->RightIndent = 0;
                    $pf->FirstLineIndent = 0;
                } catch (Exception $e) {}
                echo "审批签名图：表 $t 行 $r 列 ", ($c + 1), " 已插入 (w=", round($pic->Width, 1), "pt)\n";
                $inserted++;
            } catch (Exception $e) { /* 合并单元格无此坐标，跳过 */ }
        }
    }
}
if (!$inserted) echo "!! 未找到「审批」单元格\n";
echo "插图片后 InlineShapes: ", $doc->InlineShapes->Count, "\n";

/* ---------- 3.2 「校验：」「审批：」标签放大加粗 ---------- */
/** 查找 $needle 并设置字体加粗 + 字号（$sizePt 磅），返回处理次数 */
function bold_enlarge($doc, $needle, $sizePt)
{
    $n = 0;
    $rng = $doc->Content;
    $gbk = iconv('UTF-8', 'GBK//IGNORE', $needle);   // COM 按 ANSI(GBK) 编组
    for ($i = 0; $i < 6; $i++) {
        $f = $rng->Find;
        $f->ClearFormatting();
        $f->Text = $gbk;
        $f->Forward = true;
        $f->Wrap = 0;               // wdFindStop
        if (!$f->Execute()) break;
        $rng->Font->Bold = 1;
        $rng->Font->Size = $sizePt;
        $n++;
        $rng->Collapse(0);          // wdCollapseEnd，从匹配末尾继续找
    }
    return $n;
}
// 12pt（原正文 10.5pt）：14pt 加粗在 2.75cm 窄格里会换行
echo "校验：标签加粗放大 → " . bold_enlarge($doc, '校验：', 12) . " 处\n";
echo "审批：标签加粗放大 → " . bold_enlarge($doc, '审批：', 12) . " 处\n";

$doc->SaveAs2($dst, 16);            // wdFormatXMLDocument
try { $doc->ExportAsFixedFormat($chk, 17); } catch (Exception $e) { echo "导出核对 PDF 失败: " . $e->getMessage() . "\n"; }
$doc->Close(false);
$word->Quit();

/* ---------- 4. 新增 media 文件 & 其 rId ---------- */
$after = media_list($dst);
$newMedia = array_values(array_diff($after, $before));
echo "NEW_MEDIA=" . json_encode($newMedia) . "\n";

if ($newMedia) {
    $z = new ZipArchive();
    $z->open($dst);
    $rels = $z->getFromName('word/_rels/document.xml.rels');
    $xml  = $z->getFromName('word/document.xml');
    $z->close();
    foreach ($newMedia as $m) {
        if (preg_match('/Id="(rId\d+)"[^>]*Target="[^"]*' . preg_quote(basename($m), '/') . '"/', $rels, $mm)) {
            echo "RID[$m]=" . $mm[1] . " 引用次数=" . substr_count($xml, 'r:embed="' . $mm[1] . '"') . "\n";
        } else {
            echo "RID[$m]=未匹配\n";
        }
    }
}

echo "v3 大小: " . filesize($dst) . " bytes\n";
