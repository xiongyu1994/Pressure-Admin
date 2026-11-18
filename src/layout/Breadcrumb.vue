<script setup>
/**
 * 面包屑：首页 / 目录 / 当前页
 * 叶子路由是扁平注册的（route.matched 无父级目录），因此从
 * asyncRoutes 静态树中回溯当前路径的父级链取目录标题。
 */
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NBreadcrumb, NBreadcrumbItem } from 'naive-ui'
import { asyncRoutes } from '@/router'

const route = useRoute()
const router = useRouter()

const items = computed(() => {
  const list = []
  // 深度优先找到目标节点，沿途收集父级目录
  const walk = (nodes, ancestors) => {
    for (const n of nodes) {
      if (!n.meta?.title) continue
      const chain = [...ancestors, n]
      if (n.path === route.path) return chain
      if (n.children) {
        const found = walk(n.children, chain)
        if (found) return found
      }
    }
    return null
  }
  const chain = walk(asyncRoutes, []) || []
  for (const n of chain) {
    list.push({ title: n.meta.title, path: n.path, current: n.path === route.path })
  }
  return list
})

// function go(item) {
//   if (!item.current && item.path && item.path !== route.path) router.push(item.path)
// }
</script>

<template>
  <div class="app-breadcrumb">
    <n-breadcrumb>
      <n-breadcrumb-item @click="router.push('/dashboard')">首页</n-breadcrumb-item>
      <n-breadcrumb-item
        v-for="item in items"
        :key="item.path"
        :clickable="!item.current"
      >
        {{ item.title }}
      </n-breadcrumb-item>
    </n-breadcrumb>
  </div>
</template>

<style lang="scss" scoped>
.app-breadcrumb {
  flex-shrink: 0;
  padding: 10px 14px;
  background: #fff;
  border-bottom: 1px solid $border-color;
  font-size: 13px;
}
</style>
