import { defineStore } from 'pinia'

const collapsed = localStorage.getItem('sidebar_collapsed') === '1'

export const useAppStore = defineStore('app', {
  state: () => ({
    collapsed,
    activeTag: '/dashboard',
    tags: [{ path: '/dashboard', title: '工作台' }]
  }),
  actions: {
    toggleCollapsed() {
      this.collapsed = !this.collapsed
      localStorage.setItem('sidebar_collapsed', this.collapsed ? '1' : '0')
    },
    addTag(tag) {
      this.activeTag = tag.path
      if (!this.tags.some((t) => t.path === tag.path)) {
        this.tags.push(tag)
      }
    },
    /** 关闭标签；若关闭的是当前标签，返回应跳转的路径 */
    removeTag(path) {
      const idx = this.tags.findIndex((t) => t.path === path)
      if (idx === -1) return null
      this.tags.splice(idx, 1)
      if (this.activeTag !== path) return null
      const next = this.tags[Math.min(idx, this.tags.length - 1)]
      return next ? next.path : '/dashboard'
    },
    resetTags() {
      this.tags = [{ path: '/dashboard', title: '工作台' }]
      this.activeTag = '/dashboard'
    }
  }
})
