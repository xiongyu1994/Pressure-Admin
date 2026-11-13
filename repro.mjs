import { spawn } from 'node:child_process'
import puppeteer from 'puppeteer-core'

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
const BASE = 'http://localhost:4199'

function startPreview() {
  const proc = spawn('npm.cmd', ['run', 'preview', '--', '--port', '4199', '--strictPort'], {
    shell: true,
    stdio: 'pipe'
  })
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('preview 启动超时')), 30000)
    const onData = (d) => {
      const s = d.toString()
      process.stdout.write(s)
      if (s.includes('4199')) {
        clearTimeout(timer)
        resolve(proc)
      }
    }
    proc.stdout.on('data', onData)
    proc.stderr.on('data', onData)
    proc.on('error', reject)
  })
}

const errors = []

async function main() {
  const preview = await startPreview()
  await new Promise((r) => setTimeout(r, 1000))

  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu']
  })
  const page = await browser.newPage()
  page.on('pageerror', (e) => errors.push('[pageerror] ' + e.message + '\n' + (e.stack || '')))
  page.on('console', (msg) => {
    const t = msg.text()
    if (msg.type() === 'error') errors.push('[console.error] ' + t)
  })

  // 预置登录态（admin 全权限），整页刷新使 Pinia 读到 token
  await page.goto(BASE + '/#/login', { waitUntil: 'networkidle2' })
  await page.evaluate(() => {
    localStorage.setItem('admin_token', 'mock-token-repro')
    localStorage.setItem('admin_name', JSON.stringify('系统管理员'))
    localStorage.setItem('admin_roles', JSON.stringify(['admin']))
    localStorage.setItem('admin_perms', JSON.stringify(['*']))
    location.hash = '#/workorder/wait'
    location.reload()
  })
  await page.waitForSelector('.app-content .n-data-table', { timeout: 10000 })
  await new Promise((r) => setTimeout(r, 1500))

  // 用 hash 导航先把各页面标签建立起来（不刷新页面）
  for (const p of ['/workorder/inspection', '/workorder/print', '/workorder/all', '/workorder/wait']) {
    await page.evaluate((h) => {
      location.hash = h
    }, p)
    await new Promise((r) => setTimeout(r, 1200))
    const st = await page.evaluate(() => ({
      hash: location.hash,
      len: document.querySelector('.app-content')?.innerHTML.length || 0,
      tags: [...document.querySelectorAll('.tag-item')].map((x) => x.textContent.trim())
    }))
    console.log(`hash 导航 ${p} -> 内容长度=${st.len} 标签=[${st.tags.join(' | ')}]`)
  }

  // 真实 DOM 点击标签页来回切换（含快速连点竞态）
  const clickTag = async (title, wait = 2000) => {
    const clicked = await page.evaluate((t) => {
      const els = [...document.querySelectorAll('.tag-item')]
      const el = els.find((x) => x.textContent.trim().startsWith(t))
      if (el) {
        el.click()
        return true
      }
      return false
    }, title)
    await new Promise((r) => setTimeout(r, wait))
    const state = await page.evaluate(() => ({
      hash: location.hash,
      tableCount: document.querySelectorAll('.app-content .n-data-table').length,
      contentLen: document.querySelector('.app-content')?.innerHTML.length || 0
    }))
    console.log(`点击标签[${title}] clicked=${clicked} -> hash=${state.hash} 表格数=${state.tableCount} 内容长度=${state.contentLen}`)
    return state
  }

  for (const t of ['检验中', '待检验', '检验中', '待打印', '待检验', '检验中', '待打印']) {
    await clickTag(t)
  }
  // 快速连点（竞态场景）
  console.log('--- 快速连点 ---')
  for (const t of ['待检验', '检验中', '待检验', '检验中', '待打印']) {
    await page.evaluate((t2) => {
      const els = [...document.querySelectorAll('.tag-item')]
      const el = els.find((x) => x.textContent.trim().startsWith(t2))
      if (el) el.click()
    }, t)
    await new Promise((r) => setTimeout(r, 60))
  }
  await new Promise((r) => setTimeout(r, 3000))
  const finalState = await page.evaluate(() => ({
    hash: location.hash,
    tableCount: document.querySelectorAll('.app-content .n-data-table').length,
    contentLen: document.querySelector('.app-content')?.innerHTML.length || 0
  }))
  console.log(`快速连点后 -> hash=${finalState.hash} 表格数=${finalState.tableCount} 内容长度=${finalState.contentLen}`)

  console.log('\n===== 页面报错 (' + errors.length + ') =====')
  errors.forEach((e) => console.log(e + '\n---'))

  await browser.close()
  preview.kill()
  process.exit(0)
}

main().catch((e) => {
  console.error('REPRO 脚本失败:', e)
  process.exit(1)
})
