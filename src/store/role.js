import { defineStore } from 'pinia'
import { asyncRoutes } from '@/router'

/** 角色数据（模拟，可被「权限设置」页面编辑） */
export const useRoleStore = defineStore('role', {
  state: () => ({
    roles: [
      {
        id: 1,
        name: 'admin',
        label: '系统管理员',
        remark: '拥有系统全部权限',
        permissions: ['*'],
        builtIn: true
      },
      {
        id: 2,
        name: 'inspector',
        label: '检验员',
        remark: '业务操作权限，含系统账号查看',
        permissions: ['dashboard', 'entrust', 'examine', 'system', 'system:user'],
        builtIn: false
      },
      {
        id: 3,
        name: 'customer',
        label: '客户代表',
        remark: '仅可查看工作台与委托单',
        permissions: ['dashboard', 'entrust'],
        builtIn: false
      }
    ]
  }),
  actions: {
    updateRolePermissions(id, permissions) {
      const role = this.roles.find((r) => r.id === id)
      if (role) role.permissions = [...permissions]
    },
    updateRole(id, patch) {
      const role = this.roles.find((r) => r.id === id)
      if (role) Object.assign(role, patch)
    },
    removeRole(id) {
      this.roles = this.roles.filter((r) => r.id !== id)
    },
    addRole(role) {
      this.roles.push({ ...role, id: Date.now(), builtIn: false })
    }
  }
})
