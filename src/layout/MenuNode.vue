<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NIcon } from 'naive-ui'
import { iconMap, ChevronDownOutline, ChevronUpOutline } from '@/utils/icons'

defineOptions({ name: 'MenuNode' })

const props = defineProps({
  item: { type: Object, required: true },
  collapsed: { type: Boolean, default: false }
})

const route = useRoute()
const router = useRouter()

const hasChildren = computed(
  () => Array.isArray(props.item.children) && props.item.children.length > 0
)

function hasActiveChild(item) {
  if (route.path === item.path) return true
  return (item.children || []).some((c) => hasActiveChild(c))
}

const expanded = ref(hasActiveChild(props.item))

watch(
  () => route.path,
  () => {
    if (hasChildren.value && hasActiveChild(props.item)) {
      expanded.value = true
    }
  }
)

const iconComp = computed(() => iconMap[props.item.icon])

function handleClick() {
  if (hasChildren.value) {
    expanded.value = !expanded.value
    return
  }
  router.push(props.item.path)
}
</script>

<template>
  <div class="menu-node">
    <div
      class="menu-item"
      :class="{ active: route.path === item.path }"
      :title="collapsed ? item.title : undefined"
      @click="handleClick"
    >
      <n-icon v-if="iconComp" :size="17" :component="iconComp" class="mi-icon" />
      <span v-if="!collapsed" class="mi-label">{{ item.title }}</span>
      <n-icon
        v-if="!collapsed && hasChildren"
        :size="13"
        :component="expanded ? ChevronUpOutline : ChevronDownOutline"
        class="mi-arrow"
      />
    </div>
    <div v-if="hasChildren && expanded" class="submenu">
      <MenuNode
        v-for="c in item.children"
        :key="c.path"
        :item="c"
        :collapsed="collapsed"
        class="submenu-node"
      />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 42px;
  padding: 0 18px;
  margin: 2px 8px;
  border-radius: 4px;
  color: #b7c6dd;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.2s, color 0.2s;

  &:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
  }

  &.active {
    background: $sidebar-active;
    color: #fff;

    .mi-icon {
      color: $primary;
    }
  }

  .mi-icon {
    flex-shrink: 0;
  }

  .mi-label {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 14px;
  }

  .mi-arrow {
    flex-shrink: 0;
    opacity: 0.7;
  }
}

.submenu {
  .submenu-node :deep(.menu-item) {
    padding-left: 43px;
    height: 38px;
    font-size: 13px;
  }
}
</style>
