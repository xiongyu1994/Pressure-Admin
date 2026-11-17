import { defineStore } from 'pinia'
import router from '@/router'
import request, { setToken, getToken } from '@/utils/request'

function persist(key, value) {
  if (value === null || value === undefined) {
    localStorage.removeItem(key)
  } else {
    localStorage.setItem(key, typeof value === 'string' ? value : JSON.stringify(value))
  }
}

export const useUserStore = defineStore('user', {
  state: () => ({
    token: getToken() || '',
    name: localStorage.getItem('admin_name') || '',
    roles: JSON.parse(localStorage.getItem('admin_roles') || '[]'),
    permissions: JSON.parse(localStorage.getItem('admin_perms') || '[]')
  }),
  getters: {
    isLoggedIn: (s) => !!s.token
  },
  actions: {
    /** 登录（POST /login，后端校验账号密码并返回 token + 权限） */
    async login(username, password) {
      const data = await request.post('/login', {
        account: String(username || '').trim(),
        password: String(password || '')
      })
      this.token = data.token
      this.name = data.name
      this.roles = [data.identity || 'user']
      this.permissions = data.permissions || []
      setToken(data.token)
      persist('admin_token', this.token)
      persist('admin_name', this.name)
      persist('admin_roles', this.roles)
      persist('admin_perms', this.permissions)
      return data
    },
    /** 拉取当前用户信息（个人信息弹窗用） */
    fetchUserInfo() {
      return request.get('/user/info')
    },
    /** 刷新页面时同步服务端最新权限（避免本地缓存过期导致菜单/按钮缺失） */
    async syncPermissions() {
      const data = await this.fetchUserInfo()
      if (data && data.permissions) {
        this.permissions = data.permissions
        persist('admin_perms', this.permissions)
      }
      return data
    },
    async logout() {
      // 后端销毁 token（失败不阻塞登出流程）
      request.post('/logout').catch(() => {})
      this.token = ''
      this.name = ''
      this.roles = []
      this.permissions = []
      setToken('')
      persist('admin_token', null)
      persist('admin_name', null)
      persist('admin_roles', null)
      persist('admin_perms', null)

      import('@/store/permission').then(({ usePermissionStore }) => {
        usePermissionStore().reset()
      })
      import('@/store/app').then(({ useAppStore }) => {
        useAppStore().resetTags()
      })
      import('@/store/business').then(({ useBusinessStore }) => {
        useBusinessStore().reset()
      })
      import('@/store/menu').then(({ useMenuStore }) => {
        useMenuStore().reset()
      })
      router.push('/login')
    }
  }
})
