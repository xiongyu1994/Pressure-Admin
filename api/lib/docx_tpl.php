<?php
/** docx 模板填充共用工具（校验报告 report.php 与 校验记录 lib/record_docx.php 共用） */

/**
 * 按模板内图片描述符（docPr descr）反查它引用的 media 条目路径（如 word/media/image1.png）。
 * 找不到返回 ''。
 */
function media_by_descr($zip, $descr)
{
    $xml  = $zip->getFromName('word/document.xml');
    $rels = $zip->getFromName('word/_rels/document.xml.rels');
    if (!$xml || !$rels) return '';
    if (!preg_match('/descr="' . preg_quote($descr, '/') . '"[^>]*\/>.{0,900}?r:embed="(rId\d+)"/s', $xml, $m)) return '';
    $rid = $m[1];
    if (!preg_match('/Id="' . $rid . '"[^>]*Target="([^"]+)"/', $rels, $m2)) return '';
    return 'word/' . ltrim($m2[1], '/');
}

/**
 * 阶段 1：把被 Word 拆分进多个 run 的占位符合并成完整纯文本。
 * 例：`${erweima:width=2</w:t></w:r>...<w:t>1</w:t>...<w:t>mm}` → `${erweima:width=21mm:height=21mm}`
 * 合并结果放回第一个文本位置（XML 标签全部丢弃）。
 */
function normalize_placeholders($xml)
{
    $out = '';
    $i = 0;
    $len = strlen($xml);
    while (($pos = strpos($xml, '${', $i)) !== false) {
        $j = $pos + 2;
        $text = '';
        $end = -1;
        while ($j < $len) {
            $ch = $xml[$j];
            if ($ch === '<') {
                $close = strpos($xml, '>', $j);
                if ($close === false) break;
                $j = $close + 1;
                continue;
            }
            if ($ch === '}') { $end = $j; break; }
            if ($ch === '$') break;
            $text .= $ch;
            $j++;
        }
        if ($end === -1) {
            $out .= substr($xml, $i, $pos - $i + 2);
            $i = $pos + 2;
            continue;
        }
        // 整个区间（含中间标签）替换为纯占位符文本
        $out .= substr($xml, $i, $pos - $i) . '${' . $text . '}';
        $i = $end + 1;
    }
    $out .= substr($xml, $i);
    return $out;
}

/** 判断 $pos 是否处于某个 <w:t> 文本节点内部 */
function inside_text_node($xml, $pos)
{
    $open = strrpos(substr($xml, 0, $pos), '<w:t');
    if ($open === false) return false;
    $close = strrpos(substr($xml, 0, $pos), '</w:t>');
    return $close === false || $close < $open;
}

/**
 * 阶段 2：替换规整后的占位符（文本替换；模板内已无图片占位符，
 * rev_sec_sign 等图片名替换为空字符串）。
 */
function apply_placeholders($xml, $resolve)
{
    $i = 0;
    while (($pos = strpos($xml, '${', $i)) !== false) {
        $close = strpos($xml, '}', $pos);
        if ($close === false) break;
        $name = trim(substr($xml, $pos + 2, $close - $pos - 2));
        $val = $resolve($name);
        if ($val === null) {
            $i = $close + 1;
            continue;
        }
        $val = htmlspecialchars((string) $val, ENT_XML1);
        $xml = substr($xml, 0, $pos) . $val . substr($xml, $close + 1);
        $i = $pos + strlen($val);
    }
    return $xml;
}

/** 覆盖 zip 内指定 media 条目（先删后加，避免重复条目） */
function zip_replace_media($zip, $name, $bin)
{
    if ($zip->locateName($name) === false) return false;
    $zip->deleteName($name);
    $zip->addFromString($name, $bin);
    return true;
}

/** 校验员签名图片绝对路径（sys_user.sign，无签名返回 ''） */
function inspector_sign_path($inspectorName)
{
    if (!$inspectorName) return '';
    try {
        $u = q_one('SELECT sign FROM sys_user WHERE name = ? AND is_del = 0 AND status = 1 ORDER BY id DESC LIMIT 1', [$inspectorName]);
        if ($u && !empty($u['sign'])) {
            $p = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $u['sign']), '/');
            if (is_file($p)) return $p;
        }
    } catch (Exception $e) { /* 忽略 */ }
    return '';
}

/**
 * 审核员（审批栏）签名图片绝对路径。
 * 优先取身份=「审核员」且有签名图的最新账号；没有再退到身份含「审核」的（如录入审核）。
 * 无签名图返回 ''（运行时用透明图覆盖，审批栏留空）。
 */
function approver_sign_path()
{
    $rows = [];
    try {
        $rows = q_all(
            "SELECT sign FROM sys_user
              WHERE is_del = 0 AND status = 1 AND sign <> '' AND (identity = '审核员' OR identity LIKE '%审核%')
              ORDER BY (identity = '审核员') DESC, id DESC LIMIT 5"
        );
    } catch (Exception $e) { return ''; }
    foreach ($rows as $u) {
        if (empty($u['sign'])) continue;
        $p = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $u['sign']), '/');
        if (is_file($p)) return $p;
    }
    return '';
}
