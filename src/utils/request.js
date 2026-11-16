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

/** 后端地址：phpstudy 站点目录 WWW/inspection-api（部署后无需修改） */
const BASE_URL = 'http://localhost/inspection-api/index.php?s='

async function request(method, path, params = {}) {
  let url = BASE_URL + path.replace(/^\//, '')
  let body

  if (method === 'GET') {
    const qs = Object.entries(params)
      .filter(([, v]) => v !== undefined && v !== null && v !== '')
      .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
      .join('&')
    if (qs) url += '&' + qs
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

  const res = await fetch(url, { method, headers, body })
  const json = await res.json().catch(() => ({ code: -1, message: '响应解析失败' }))

  if (json.code === 401) {
    // 登录过期：清空本地登录态，跳转登录页
    TOKEN = ''
    localStorage.removeItem('api_token')
    localStorage.removeItem('admin_token')
    if (location.hash !== '#/login') location.hash = '#/login'
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
