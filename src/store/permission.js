import { defineStore } from 'pinia'
import router, { asyncRoutes } from '@/router'

function checkNode(node, permissions) {
  if (!node.meta || !node.meta.permission) return true
  return permissions.includes('*') || permissions.includes(node.meta.permission)
}

/** 递归过滤路由树：目录节点只有存在可见子节点时才保留 */
export function filterRoutes(routes, permissions) {
  const res = []
  for (const route of routes) {
    if (route.children && route.children.length) {
      const children = filterRoutes(route.children, permissions)
      if (children.length) {
        res.push({ ...route, children })
      }
    } else if (checkNode(route, permissions)) {
      res.push({ ...route })
    }
  }
  return res
}

/** 展开路由树，取出所有叶子页面路由，扁平注册到 Layout 下 */
function flattenLeafRoutes(routes, res = []) {
  for (const r of routes) {
    if (r.children && r.children.length) {
      flattenLeafRoutes(r.children, res)
    } else if (r.component) {
      res.push(r)
    }
  }
  return res
}

export const usePermissionStore = defineStore('permission', {
  state: () => ({
    /** 过滤后的菜单路由树（用于侧边栏渲染） */
    menuRoutes: [],
    isGenerated: false
  }),
  actions: {
    generateRoutes(permissions) {
      const tree = filterRoutes(asyncRoutes, permissions)
      this.menuRoutes = tree
      flattenLeafRoutes(tree).forEach((r) => {
        router.addRoute('Layout', { ...r })
      })
      this.isGenerated = true
    },
    reset() {
      this.menuRoutes = []
      this.isGenerated = false
    }
  }
})
