<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NIcon } from 'naive-ui'
import { useAppStore } from '@/store/app'
import { usePermissionStore } from '@/store/permission'
import { useMenuStore } from '@/store/menu'
import { iconMap, ShieldCheckmarkOutline } from '@/utils/icons'
import MenuNode from './MenuNode.vue'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const permStore = usePermissionStore()
const menuStore = useMenuStore()

function filterItems(routes) {
  return routes
    .filter((r) => !menuStore.hiddenPaths.has(r.path))
    .map((r) => ({
      title: r.meta.title,
      path: r.path,
      icon: r.meta.icon,
      children: r.children ? filterItems(r.children) : null
    }))
}

const items = computed(() => filterItems(permStore.menuRoutes))
</script>

<template>
  <div class="sider-wrap">
    <div class="sider-logo" @click="router.push('/dashboard')">
      <n-icon :size="20" :component="ShieldCheckmarkOutline" class="logo-icon" />
      <span v-if="!appStore.collapsed" class="logo-text">检验后台</span>
    </div>
    <div class="sider-menu">
      <MenuNode
        v-for="item in items"
        :key="item.path"
        :item="item"
        :collapsed="appStore.collapsed"
      />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.sider-wrap {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.sider-logo {
  height: 50px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 16px;
  cursor: pointer;
  flex-shrink: 0;

  .logo-icon {
    color: $primary;
    flex-shrink: 0;
  }

  .logo-text {
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    white-space: nowrap;
  }
}

.sider-menu {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 6px 0;
}
</style>
