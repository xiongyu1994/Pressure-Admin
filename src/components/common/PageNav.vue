<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { NIcon } from 'naive-ui'
import { RefreshOutline } from '@vicons/ionicons5'

/** 与参考站一致的页面导航条：首页 / 模块 / 当前页 + 右侧刷新按钮 */
const props = defineProps({
  module: { type: String, default: '' },
  title: { type: String, default: '' }
})

const route = useRoute()

const moduleName = computed(() => props.module || route.matched?.[1]?.meta?.title || '')
const pageName = computed(() => props.title || route.meta?.title || '')

function refresh() {
  window.location.reload()
}
</script>

<template>
  <div class="page-nav">
    <div class="pn-crumb">
      <span class="pn-home">首页</span>
      <template v-if="moduleName">
        <span class="pn-sep">/</span>
        <span>{{ moduleName }}</span>
      </template>
      <template v-if="pageName">
        <span class="pn-sep">/</span>
        <cite>{{ pageName }}</cite>
      </template>
    </div>
    <n-icon class="pn-refresh" :size="16" :component="RefreshOutline" title="刷新" @click="refresh" />
  </div>
</template>

<style lang="scss" scoped>
.page-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 34px;
  padding: 0 12px;
  margin-bottom: 10px;
  background: #fff;
  border-radius: 3px;
  border-left: 3px solid $primary;

  .pn-crumb {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: $text-secondary;

    .pn-home {
      color: $primary;
    }

    .pn-sep {
      color: #c2c8d2;
    }

    cite {
      font-style: normal;
      color: $text-primary;
      font-weight: 600;
    }
  }

  .pn-refresh {
    color: $text-secondary;
    cursor: pointer;

    &:hover {
      color: $primary;
    }
  }
}
</style>
