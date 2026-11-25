<?php
/** 校验记录模板一次性改造（CLI 运行，纯 PHP，无 COM）：
 *
 *  源模板（1795076704585.docx）里 ${inspector_sign:width=24mm:height=12mm} 与
 *  ${rev_sec_sign:width=18mm:height=10mm} 两个占位符各位于一个 mc:AlternateContent
 *  签名文本框（wps 主分支 + VML 兼容分支，所以文本出现两次）中。
 *  本脚本把整个形状块替换为「预置占位图」（inline drawing，docPr descr=_ph_sign/_ph_sec），
 *  运行时 record.php / lib/record_docx.php 只需 ZipArchive 覆盖 media + 替换文本占位符——
 *  与校验报告模板 tpl_check_report_v3.docx 的 media 覆盖方案完全一致。
 *
 *  用法：php lib/build_tpl_record.php <src.docx> <dst.docx>
 */

error_reporting(E_ERROR | E_PARSE);

$src = isset($argv[1]) ? $argv[1] : '';
$dst = isset($argv[2]) ? $argv[2] : '';
if ($src === '' || $dst === '' || !is_file($src)) {
    fwrite(STDERR, "usage: php build_tpl_record.php <src.docx> <dst.docx>\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($src) !== true) { fwrite(STDERR, "源模板打开失败\n"); exit(1); }
$xml = $zip->getFromName('word/document.xml');
$rels = $zip->getFromName('word/_rels/document.xml.rels');
$ct = $zip->getFromName('[Content_Types].xml');
if (!$xml || !$rels || !$ct) { fwrite(STDERR, "document.xml / rels / content-types 读取失败\n"); exit(1); }

/* ---------- 1. 两个签名形状块 → 预置占位图（inline drawing） ---------- */

// 占位图：直接用透明图（运行时会被真实签名/透明图覆盖，仅占位保证descr可反查）
$blankBin = file_get_contents(__DIR__ . '/../uploads/tpl/_blank.png');
if (!$blankBin) { fwrite(STDERR, "uploads/tpl/_blank.png 读取失败\n"); exit(1); }

/**
 * inline 图片 drawing XML。EMU：1mm = 36000。
 * descr 放在 docPr 上，供运行时 media_by_descr() 反查 media 条目（Word 另存会重排 imageN 编号）。
 */
$signDrawing = function ($descr, $name, $rid, $wMm, $hMm) {
    $cx = $wMm * 36000;
    $cy = $hMm * 36000;
    return '<w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
        . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:effectExtent l="0" t="0" r="0" b="0"/>'
        . '<wp:docPr id="901" name="' . $name . '" descr="' . $descr . '"/><wp:cNvGraphicFramePr/>'
        . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
        . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:nvPicPr><pic:cNvPr id="901" name="' . $name . '"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="' . $rid . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
        . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing>';
};

// 现有 rId 最大号，新占位图关系追加在后面
$rmax = 0;
if (preg_match_all('/Id="rId(\d+)"/', $rels, $m)) $rmax = max(array_map('intval', $m[1]));
$ridSign = 'rId' . ($rmax + 1);
$ridSec  = 'rId' . ($rmax + 2);

$blocks = [];
if (!preg_match_all('/<mc:AlternateContent>.*?<\/mc:AlternateContent>/s', $xml, $blocks)) {
    fwrite(STDERR, "未找到签名形状块（mc:AlternateContent）\n"); exit(1);
}
$replaced = 0;
foreach ($blocks[0] as $blk) {
    if (strpos($blk, '${inspector_sign') !== false) {
        $xml = str_replace($blk, $signDrawing('_ph_sign', 'phSign', $ridSign, 24, 12), $xml);
        $replaced++;
    } elseif (strpos($blk, '${rev_sec_sign') !== false) {
        $xml = str_replace($blk, $signDrawing('_ph_sec', 'phSec', $ridSec, 18, 10), $xml);
        $replaced++;
    }
}
if ($replaced !== 2) { fwrite(STDERR, "签名形状块替换数量异常：$replaced（应为 2）\n"); exit(1); }

/* ---------- 2. 关系与内容类型 ---------- */

$imgRel = '<Relationship Id="%s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/%s"/>';
if (strpos($rels, '</Relationships>') !== false) {
    $rels = str_replace('</Relationships>',
        sprintf($imgRel, $ridSign, 'ph_sign.png') . sprintf($imgRel, $ridSec, 'ph_sec.png') . '</Relationships>', $rels);
}
if (strpos($ct, 'Extension="png"') === false) {
    $ct = str_replace('<Default Extension="xml"',
        '<Default Extension="png" ContentType="image/png"/><Default Extension="xml"', $ct);
}

/* ---------- 3. XML 合法性自检 ---------- */

foreach ([['document.xml', $xml], ['document.xml.rels', $rels], ['[Content_Types].xml', $ct]] as $t) {
    $dom = new DOMDocument();
    if (@$dom->loadXML($t[1]) !== true) { fwrite(STDERR, "{$t[0]} 不是合法 XML\n"); exit(1); }
}

/* ---------- 4. 写出目标模板 ---------- */

$out = new ZipArchive();
if ($out->open($dst, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "目标模板创建失败: $dst\n"); exit(1);
}
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    $bin = $zip->getFromIndex($i);
    if ($name === 'word/document.xml') $bin = $xml;
    elseif ($name === 'word/_rels/document.xml.rels') $bin = $rels;
    elseif ($name === '[Content_Types].xml') $bin = $ct;
    $out->addFromString($name, $bin);
}
$zip->close();
$out->addFromString('word/media/ph_sign.png', $blankBin);
$out->addFromString('word/media/ph_sec.png', $blankBin);
$out->close();

echo "OK: $dst (sign=$ridSign sec=$ridSec)\n";
