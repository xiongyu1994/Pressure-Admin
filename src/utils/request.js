/**
 * 前端请求封装（fetch 版，无需额外依赖）
 * 配合 admin-system/api 后端使用，响应格式 { code, message, data }
 *
 * 用法示例：
 *   import request from '@/utils/request'
 *   const data = await request.post('/login', { account: 'admin', password: 'admin123' })
 *   const list = await request.get('/workorder/list', { status: 'wait', page: 1, limit: 15 })
 */

let TOKEN = localStorage.getItem('api_token') || ''

export function setToken(token) {
  TOKEN = token || ''
  if (token) localStorage.setItem('api_token', token)
  else localStorage.removeItem('api_token')
}

export function getToken() {
  return TOKEN
}

/**
 * 后端地址：phpstudy 站点目录 WWW/inspection-api（部署后无需修改）
 * 路由采用伪静态形式（.htaccess 已配置），如 http://localhost/inspection-api/system/menu/list
 * 如需换服务器/端口，可在 .env.local 中配置 VITE_API_BASE
 */
const API_BASE = (import.meta.env.VITE_API_BASE || 'http://localhost/inspection-api').replace(/\/+$/, '')

/** 供图片等静态资源拼接完整地址（如委托单签名图片） */
export { API_BASE }

/** 伪静态不可用（Apache 未开 mod_rewrite / AllowOverride 未生效）时的兜底地址 */
const LEGACY_BASE = API_BASE + '/index.php?s='

function buildUrl(base, path, queryString) {
  const url = base + path.replace(/^\//, '')
  if (!queryString) return url
  return url + (url.includes('?') ? '&' : '?') + queryString
}

async function request(method, path, params = {}) {
  let queryString = ''
  let body

  if (method === 'GET') {
    queryString = Object.entries(params)
      .filter(([, v]) => v !== undefined && v !== null && v !== '')
      .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
      .join('&')
  } else {
    body = JSON.stringify(params)
  }

  const headers = { 'Content-Type': 'application/json' }
  if (TOKEN) {
    // phpstudy 的 Apache（CGI 模式）会丢弃 Authorization 头，
    // 因此同时通过自定义 Token 头传 token（后端两种方式都支持）
    headers.Authorization = 'Bearer ' + TOKEN
    headers.Token = TOKEN
  }

  let res = await fetch(buildUrl(API_BASE + '/', path, queryString), { method, headers, body })
  // 404 且返回的不是 JSON，说明伪静态未生效，退回 index.php?s= 形式重试一次
  if (res.status === 404) {
    const contentType = res.headers.get('content-type') || ''
    if (!contentType.includes('application/json')) {
      res = await fetch(buildUrl(LEGACY_BASE, path, queryString), { method, headers, body })
    }
  }

  const json = await res.json().catch(() => ({ code: -1, message: '响应解析失败' }))

  if (json.code === 401) {
    // 登录过期：清空本地登录态（含 Pinia 内存态，否则路由守卫会弹回工作台），跳转登录页
    TOKEN = ''
    localStorage.removeItem('api_token')
    localStorage.removeItem('admin_token')
    // 动态引入避免循环依赖（user store 也引用了本模块）
    import('@/store/user').then(({ useUserStore }) => useUserStore().forceLogout())
  }
  if (json.code !== 0) {
    return Promise.reject(new Error(json.message || '请求失败'))
  }
  return json.data
}

export default {
  get: (path, params) => request('GET', path, params),
  post: (path, params) => request('POST', path, params),
}
