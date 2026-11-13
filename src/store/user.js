import { defineStore } from 'pinia'
import router from '@/router'

/** 模拟账号数据（真实项目替换为接口请求） */
const mockUsers = {
  admin: {
    password: 'admin123',
    name: '系统管理员',
    roles: ['admin'],
    permissions: ['*']
  },
  zhoukuan: {
    password: '123456',
    name: '周宽',
    roles: ['inspector'],
    permissions: [
      'dashboard',
      'commission', 'commission:add', 'commission:list',
      'workorder', 'workorder:reject', 'workorder:wait', 'workorder:inspection', 'workorder:print', 'workorder:all',
      'system', 'system:user'
    ]
  }
}

function persist(key, value) {
  if (value === null || value === undefined) {
    localStorage.removeItem(key)
  } else {
    localStorage.setItem(key, typeof value === 'string' ? value : JSON.stringify(value))
  }
}

export const useUserStore = defineStore('user', {
  state: () => ({
    token: localStorage.getItem('admin_token') || '',
    name: localStorage.getItem('admin_name') || '',
    roles: JSON.parse(localStorage.getItem('admin_roles') || '[]'),
    permissions: JSON.parse(localStorage.getItem('admin_perms') || '[]')
  }),
  getters: {
    isLoggedIn: (s) => !!s.token
  },
  actions: {
    /** 登录（模拟接口） */
    login(username, password) {
      return new Promise((resolve, reject) => {
        setTimeout(() => {
          const user = mockUsers[String(username || '').trim().toLowerCase()]
          if (!user || user.password !== password) {
            reject(new Error('账号或密码错误'))
            return
          }
          this.token = 'mock-token-' + Date.now()
          this.name = user.name
          this.roles = user.roles
          this.permissions = user.permissions
          persist('admin_token', this.token)
          persist('admin_name', this.name)
          persist('admin_roles', this.roles)
          persist('admin_perms', this.permissions)
          resolve(user)
        }, 400)
      })
    },
    logout() {
      this.token = ''
      this.name = ''
      this.roles = []
      this.permissions = []
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
      router.push('/login')
    }
  }
})
