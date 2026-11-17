import { defineStore } from 'pinia'
import request from '@/utils/request'
import { asyncRoutes } from '@/router'

/** 由前端路由树生成本地菜单树（后端未加载时的兜底） */
function buildTree(routes, parentId = 0, uidRef = { n: 1 }) {
  return routes.map((r, i) => {
    const node = {
      id: uidRef.n++,
      parentId,
      title: r.meta.title,
      path: r.path,
      permission: r.meta.permission || '',
      icon: r.meta.icon || '',
      type: r.children && r.children.length ? 'dir' : 'menu',
      sort: i + 1,
      visible: true
    }
    if (r.children && r.children.length) {
      node.children = buildTree(r.children, node.id, uidRef)
    }
    return node
  })
}

/** 后端平铺列表组树 */
function listToTree(rows, parentId = 0, uidRef = { n: 1000 }) {
  const nodes = rows
    .filter((m) => m.parentId === parentId)
    .sort((a, b) => a.sort - b.sort)
  return nodes.map((m) => {
    const node = { ...m }
    const children = listToTree(rows, m.id, uidRef)
    if (children.length) {
      node.children = children
      node.type = 'dir'
    }
    return node
  })
}

function flatten(nodes, res = []) {
  for (const n of nodes) {
    res.push(n)
    if (n.children) flatten(n.children, res)
  }
  return res
}

/** 菜单访问设置：数据来自 system/menu 接口，控制侧边栏显示/隐藏 */
export const useMenuStore = defineStore('menu', {
  state: () => ({
    menus: buildTree(asyncRoutes),
    loaded: false,
    loading: false
  }),
  getters: {
    flatMenus(state) {
      return flatten(state.menus)
    },
    /** 被隐藏的菜单路径集合，侧边栏据此过滤 */
    hiddenPaths() {
      return new Set(this.flatMenus.filter((m) => !m.visible).map((m) => m.path))
    }
  },
  actions: {
    /** 退出登录时清空缓存，避免下个账号沿用上一个账号的菜单配置 */
    reset() {
      this.menus = buildTree(asyncRoutes)
      this.loaded = false
      this.loading = false
    },
    /** 拉取菜单配置：首次加载后走缓存，切换页面不会重复请求；force=true 用于菜单设置页强制刷新 */
    async fetchMenus(force = false) {
      if (this.loading) return
      if (this.loaded && !force) return
      this.loading = true
      try {
        const rows = await request.get('/system/menu/list')
        if (Array.isArray(rows) && rows.length) {
          this.menus = listToTree(rows)
          this.loaded = true
        }
      } finally {
        this.loading = false
      }
    },
    async toggleVisible(id) {
      const m = this.flatMenus.find((x) => x.id === id)
      if (!m) return
      const next = !m.visible
      m.visible = next
      try {
        await request.post('/system/menu/visible', { id, visible: next ? 1 : 0 })
      } catch (e) {
        m.visible = !next
        throw e
      }
    },
    async updateMenu(id, patch) {
      await request.post('/system/menu/update', { id, ...patch })
      await this.fetchMenus(true)
    },
    async addChild(parentId, node) {
      const parent = this.flatMenus.find((x) => x.id === parentId)
      if (parent) parent.type = 'dir'
      await request.post('/system/menu/add', { ...node, parentId })
      await this.fetchMenus(true)
    },
    async addTopMenu(node) {
      await request.post('/system/menu/add', { ...node, parentId: 0 })
      await this.fetchMenus(true)
    },
    async removeMenu(id) {
      await request.post('/system/menu/delete', { id })
      await this.fetchMenus(true)
    }
  }
})
