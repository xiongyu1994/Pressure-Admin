import { defineStore } from 'pinia'
import request from '@/utils/request'

/** 角色权限（全部来自 system/role 接口） */
export const useRoleStore = defineStore('role', {
  state: () => ({
    roles: [],
    loaded: false,
    loading: false
  }),
  actions: {
    async fetchRoles() {
      if (this.loading) return
      this.loading = true
      try {
        this.roles = await request.get('/system/role/list')
        this.loaded = true
      } finally {
        this.loading = false
      }
    },
    async ensureLoaded() {
      if (!this.loaded) await this.fetchRoles()
    },
    /** 保存角色勾选的权限标识 */
    async updateRolePermissions(id, permissions) {
      await request.post('/system/role/permissions', { id, permissions })
      await this.fetchRoles()
    },
    async updateRole(id, patch) {
      await request.post('/system/role/update', { id, ...patch })
      await this.fetchRoles()
    },
    async removeRole(id) {
      await request.post('/system/role/delete', { id })
      await this.fetchRoles()
    },
    async addRole(role) {
      await request.post('/system/role/add', role)
      await this.fetchRoles()
    }
  }
})
