import { defineStore } from 'pinia'
import { asyncRoutes } from '@/router'

let uid = 1

/** 由动态路由树初始化可编辑的菜单配置树 */
function buildTree(routes, parentId = 0) {
  return routes.map((r, i) => {
    const node = {
      id: uid++,
      parentId,
      title: r.meta.title,
      path: r.path,
      permission: r.meta.permission || '',
      icon: r.meta.icon || '',
      type: r.children && r.children.length ? 'dir' : 'menu',
      sort: i + 1,
      visible: true,
      children: undefined
    }
    if (r.children && r.children.length) {
      node.children = buildTree(r.children, node.id)
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

/** 菜单访问设置：控制菜单的显示/隐藏与基础配置 */
export const useMenuStore = defineStore('menu', {
  state: () => ({
    menus: buildTree(asyncRoutes)
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
    toggleVisible(id) {
      const m = this.flatMenus.find((x) => x.id === id)
      if (m) m.visible = !m.visible
    },
    updateMenu(id, patch) {
      const m = this.flatMenus.find((x) => x.id === id)
      if (m) Object.assign(m, patch)
    },
    addChild(parentId, node) {
      const parent = this.flatMenus.find((x) => x.id === parentId)
      if (!parent) return
      parent.type = 'dir'
      if (!parent.children) parent.children = []
      parent.children.push({
        id: uid++,
        parentId: parent.id,
        visible: true,
        icon: '',
        permission: '',
        ...node
      })
    },
    removeMenu(id) {
      const walk = (list) => {
        const idx = list.findIndex((x) => x.id === id)
        if (idx !== -1) {
          list.splice(idx, 1)
          return true
        }
        return list.some((x) => x.children && walk(x.children))
      }
      walk(this.menus)
    }
  }
})
