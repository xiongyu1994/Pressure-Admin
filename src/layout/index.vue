<script setup>
import { useAppStore } from '@/store/app'
import Sidebar from './Sidebar.vue'
import HeaderBar from './Header.vue'
import TagsView from './TagsView.vue'

const appStore = useAppStore()
</script>

<template>
  <div class="app-layout">
    <aside class="app-sider" :class="{ collapsed: appStore.collapsed }">
      <Sidebar />
    </aside>
    <section class="app-main">
      <header class="app-header">
        <HeaderBar />
      </header>
      <div class="app-tags">
        <TagsView />
      </div>
      <main class="app-content">
        <router-view v-slot="{ Component, route }">
          <component :is="Component" :key="route.fullPath" />
        </router-view>
      </main>
    </section>
  </div>
</template>

<style lang="scss" scoped>
.app-layout {
  display: flex;
  width: 100%;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
}

.app-sider {
  width: 220px;
  height: 100%;
  flex-shrink: 0;
  background: $sidebar-bg;
  transition: width 0.25s;
  overflow: hidden;

  &.collapsed {
    width: 64px;
  }
}

.app-main {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.app-header {
  height: 50px;
  flex-shrink: 0;
  background: $header-bg;
}

.app-tags {
  flex-shrink: 0;
  background: #fff;
  border-bottom: 1px solid $border-color;
}

.app-content {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: 14px;
}
</style>
