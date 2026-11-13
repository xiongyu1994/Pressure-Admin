<script setup>
import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NIcon } from 'naive-ui'
import { useAppStore } from '@/store/app'
import { CloseOutline } from '@/utils/icons'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()

watch(
  () => route.path,
  (p) => {
    if (route.meta && route.meta.title) {
      appStore.addTag({ path: p, title: route.meta.title })
    }
  },
  { immediate: true }
)

function closeTag(tag) {
  const next = appStore.removeTag(tag.path)
  if (next) router.push(next)
}
</script>

<template>
  <div class="tags-view">
    <div
      v-for="t in appStore.tags"
      :key="t.path"
      class="tag-item"
      :class="{ active: route.path === t.path }"
      @click="router.push(t.path)"
    >
      {{ t.title }}
      <n-icon
        v-if="t.path !== '/dashboard'"
        :size="12"
        :component="CloseOutline"
        class="tag-close"
        @click.stop="closeTag(t)"
      />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.tags-view {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  overflow-x: auto;

  .tag-item {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    font-size: 12px;
    color: $text-secondary;
    background: #f2f4f8;
    border: 1px solid $border-color;
    border-radius: 3px;
    cursor: pointer;
    white-space: nowrap;
    user-select: none;

    &:hover {
      color: $primary;
    }

    &.active {
      background: $sidebar-bg;
      color: #fff;
      border-color: $sidebar-bg;
    }

    .tag-close {
      border-radius: 50%;
      padding: 1px;

      &:hover {
        background: rgba(0, 0, 0, 0.15);
      }
    }
  }
}
</style>
