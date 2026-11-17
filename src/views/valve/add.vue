<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useBusinessStore } from '@/store/business'
import ValveForm from '@/components/commission/ValveForm.vue'

/**
 * 安全阀管理 → 新增安全阀（独立页面）
 * 表单与弹窗共用 ValveForm 组件；保存成功后回到安全阀列表
 */
const router = useRouter()
const store = useBusinessStore()

onMounted(() => {
  store.fetchUnits().catch(() => {})
})

async function handleConfirm() {
  await store.fetchValves().catch(() => {})
  router.push('/valve/list')
}
</script>

<template>
  <div class="page-card">
    <ValveForm @confirm="handleConfirm" />
  </div>
</template>
