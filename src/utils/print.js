import { PDFDocument } from 'pdf-lib'

/**
 * 打印后端生成的 PDF（与「下载 docx」同款模板样式）：
 * fetch 取回 PDF blob → 新窗口内嵌 iframe（blob URL 同源可调用 print）→ 自动调起打印
 */
export async function printPdfUrl(url, title) {
  return printPdfUrls([url], title)
}

/**
 * 批量打印多份 PDF：逐份 fetch（onProgress 汇报进度）→ pdf-lib 合并为一个 PDF
 * → 新窗口内嵌 iframe 自动调起打印。任一份失败即整体报错。
 */
export async function printPdfUrls(urls, title, onProgress) {
  // 先同步开窗口（用户手势内调用，避免被弹窗拦截），转换期间窗口内显示进度
  const win = window.open('', '_blank')
  if (!win) {
    throw new Error('浏览器拦截了打印窗口，请允许弹出窗口后重试')
  }
  win.document.write(`<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>${title}</title>
<style>body{margin:0;font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;color:#666}</style>
</head><body><p id="st">正在生成打印内容，请稍候…</p></body></html>`)
  win.document.close()
  const setTip = (t) => {
    try { win.document.getElementById('st').textContent = t } catch (e) { /* 窗口已关闭 */ }
  }

  try {
    const merged = await PDFDocument.create()
    let done = 0
    for (const url of urls) {
      setTip(`正在生成打印内容 ${done + 1}/${urls.length}，请稍候…`)
      onProgress && onProgress(done, urls.length)
      const res = await fetch(url)
      const ct = res.headers.get('content-type') || ''
      if (!res.ok || !ct.includes('application/pdf')) {
        throw new Error(`打印内容生成失败（第 ${done + 1} 份），请稍后重试`)
      }
      const buf = await res.arrayBuffer()
      const doc = await PDFDocument.load(buf)
      const pages = await merged.copyPages(doc, doc.getPageIndices())
      pages.forEach((p) => merged.addPage(p))
      done++
    }
    const bytes = await merged.save()
    const objectUrl = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }))
    win.document.write(`<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>${title}</title>
<style>html,body{margin:0;height:100%}iframe{border:0;width:100%;height:100%}</style>
</head><body><iframe src="${objectUrl}" onload="setTimeout(function(){try{this.contentWindow.focus();this.contentWindow.print()}catch(e){}}.bind(this),500)"></iframe></body></html>`)
    win.document.close()
    setTimeout(() => URL.revokeObjectURL(objectUrl), 60000)
  } catch (e) {
    try { win.close() } catch (e2) { /* 忽略 */ }
    throw e
  }
}

/**
 * 通用打印：把 HTML 写入新窗口并调起打印
 */
export function printHtml(title, bodyHtml) {  const win = window.open('', '_blank', 'width=900,height=650')
  if (!win) {
    throw new Error('浏览器拦截了打印窗口，请允许弹出窗口后重试')
  }
  win.document.write(`<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>${title}</title>
  <style>
    body { font-family: 'SimSun', 'Microsoft YaHei', serif; padding: 24px; color: #111; }
    h1 { text-align: center; font-size: 20px; margin: 8px 0 20px; }
    h2 { font-size: 15px; margin: 16px 0 8px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 12px; }
    th, td { border: 1px solid #444; padding: 6px 8px; font-size: 13px; }
    th { background: #f0f0f0; }
    .meta { text-align: right; font-size: 13px; color: #333; margin-bottom: 12px; }
    .stamp { margin-top: 28px; display: flex; justify-content: space-between; font-size: 13px; }
  </style>
</head>
<body>
  ${bodyHtml}
  <script>window.onload = function () { setTimeout(function () { window.print() }, 300) }<\/script>
</body>
</html>`)
  win.document.close()
}

/** 导出 txt 文件下载 */
export function downloadTxt(filename, content) {
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

/** 把报告/记录 HTML 作为文件下载（对应参考站的“下载”按钮） */
export function downloadHtml(filename, title, bodyHtml) {
  const html = `<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>${title}</title>
<style>
  body { font-family: 'SimSun', 'Microsoft YaHei', serif; padding: 24px; color: #111; }
  h1 { text-align: center; font-size: 20px; margin: 8px 0 20px; }
  h2 { font-size: 15px; margin: 16px 0 8px; }
  table { border-collapse: collapse; width: 100%; margin-bottom: 12px; }
  th, td { border: 1px solid #444; padding: 6px 8px; font-size: 13px; }
  th { background: #f0f0f0; }
  .meta { text-align: right; font-size: 13px; color: #333; margin-bottom: 12px; }
  .stamp { margin-top: 28px; display: flex; justify-content: space-between; font-size: 13px; }
  .cover { height: 520px; display: flex; flex-direction: column; justify-content: center; align-items: center; }
  .cover h1 { font-size: 28px; }
  .page-break { page-break-after: always; }
</style></head><body>${bodyHtml}</body></html>`
  const blob = new Blob(['\ufeff' + html], { type: 'text/html;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename.endsWith('.html') ? filename : `${filename}.html`
  a.click()
  URL.revokeObjectURL(url)
}

/** 报告封面 */
export function buildCoverHtml(title, w) {
  return `<div class="cover">
    <h1>${title}</h1>
    <p>使用单位：${w?.custName || ''}</p>
    <p>检验编号：${w?.orderCode || ''}</p>
    <p>校验日期：${(w?.inspectorTime || '').slice(0, 10) || ''}</p>
  </div><div class="page-break"></div>`
}

/** 检验报告 HTML */
export function buildReportHtml(w, opts = {}) {
  const r = w.result || {}
  const chap = opts.noChapter ? '<span style="float:right;font-size:12px;color:#666">（无章）</span>' : ''
  return `
    <h1>安全阀校验报告${chap}</h1>
    <div class="meta">报告编号：${w.orderCode}</div>
    <table>
      <tr><th>委托单号</th><td>${w.code}</td><th>检验编号</th><td>${w.orderCode}</td></tr>
      <tr><th>使用单位</th><td>${w.custName}</td><th>使用单位联系人</th><td>${w.client} / ${w.clientTel}</td></tr>
      <tr><th>设备代码</th><td>${w.idNo || '-'}</td><th>出厂编号</th><td>${w.manuNo}</td></tr>
      <tr><th>安全阀型号</th><td>${w.vavleModel}</td><th>制造单位</th><td>${w.manufacturer}</td></tr>
      <tr><th>工作介质</th><td>${w.workingMedia}</td><th>安装位置</th><td>${w.tagNo || '-'}</td></tr>
      <tr><th>执行标准</th><td colspan="3">${(r.standardNumber || []).join('；') || '-'}</td></tr>
    </table>
    <h2>校验数据</h2>
    <table>
      <tr><th>要求整定压力MPa</th><td>${r.setPress ?? w.setPress ?? '-'}</td><th>要求密封压力MPa</th><td>${r.tightnessPress ?? w.tightnessPress ?? '-'}</td></tr>
      <tr><th>校验介质</th><td>${r.testMedia || '-'}</td><th>校验介质温度</th><td>${r.testTemprature || '-'}</td></tr>
      <tr><th>整定压力实测</th><td>${[r.setPressure1, r.setPressure2, r.setPressure3].filter((v) => v !== undefined && v !== '').join(' / ') || '-'}</td>
          <th>密封试验压力</th><td>${[r.testingPressure1, r.testingPressure2, r.testingPressure3].filter((v) => v !== undefined && v !== '').join(' / ') || '-'}</td></tr>
      <tr><th>气泡数/min</th><td>${r.bubbleCount ?? '-'}</td><th>校验结果</th><td><b>${r.testStatus || '-'}</b></td></tr>
      <tr><th>校验方式</th><td>${r.parity || '-'}</td><th>外观检查</th><td>${r.visualExamination || '-'}</td></tr>
      <tr><th>拆卸检查</th><td>${r.disassemblyInspection || '-'}</td><th>备注</th><td>${r.note || '-'}</td></tr>
      <tr><th>维护检修情况</th><td colspan="3">${(r.maintenance || []).join('、') || r.qita || '-'}</td></tr>
    </table>
    <h2>校验设备与压力表</h2>
    <table>
      <tr><th>校验设备型号</th><td>${r.deviceModel || '-'}</td><th>校验设备编号</th><td>${r.deviceSn || '-'}</td></tr>
      <tr><th>压力表编号</th><td>${r.pressureGaugeNum || '-'}</td><th>压力表有效期</th><td>${r.pressureGaugeLifespan || '-'}</td></tr>
      <tr><th>压力表精度</th><td>${r.pressureGaugeAccuracy || '-'}</td><th>压力表量程MPa</th><td>${r.pressureGaugeRange || '-'}</td></tr>
    </table>
    <div class="stamp">
      <span>校验员：${w.inspector || r.inspector || '-'}</span>
      <span>校验日期：${(w.inspectorTime || '').slice(0, 10) || '-'}</span>
      <span>（检验机构盖章）</span>
    </div>`
}

/** 校验记录 HTML */
export function buildRecordHtml(w) {
  const r = w.result || {}
  return `
    <h1>安全阀校验记录</h1>
    <div class="meta">记录编号：${w.orderCode}</div>
    <table>
      <tr><th>使用单位</th><td>${w.custName}</td><th>委托单号</th><td>${w.code}</td></tr>
      <tr><th>设备代码</th><td>${w.idNo || '-'}</td><th>出厂编号</th><td>${w.manuNo}</td></tr>
      <tr><th>安全阀型号</th><td>${w.vavleModel}</td><th>公称通径mm</th><td>${w.diameterNominal}</td></tr>
      <tr><th>流道直径mm</th><td>${w.seatSize || '-'}</td><th>公称压力MPa</th><td>${w.pressNominal}</td></tr>
      <tr><th>工作压力MPa</th><td>${w.workingPress}</td><th>要求整定压力MPa</th><td>${w.setPress}</td></tr>
      <tr><th>密封试验压力MPa</th><td>${w.tightnessPress}</td><th>工作介质</th><td>${w.workingMedia}</td></tr>
      <tr><th>安装位置</th><td>${w.tagNo || '-'}</td><th>出厂日期</th><td>${w.manuDate || '-'}</td></tr>
      <tr><th>整定压力实测</th><td>${r.testingPressure1 ?? '-'}</td><th>校验结果</th><td><b>${r.testStatus || '-'}</b></td></tr>
      <tr><th>校验员</th><td>${w.inspector || '-'}</td><th>校验日期</th><td>${(w.inspectorTime || '').slice(0, 10) || '-'}</td></tr>
    </table>`
}
