<script setup>
import { ref, computed, h } from 'vue'
import { NButton, NSpace } from 'naive-ui'
import { useBusinessStore } from '@/store/business'

const props = defineProps({
  show: { type: Boolean, default: false }
})
const emit = defineEmits(['update:show', 'confirm'])

const store = useBusinessStore()
const keyword = ref('')
const checkedKeys = ref([])

const filtered = computed(() =>
  store.valveLibrary.filter(
    (v) =>
      !keyword.value ||
      v.manuNo.includes(keyword.value) ||
      v.vavleModel.includes(keyword.value) ||
      (v.idNo || '').includes(keyword.value)
  )
)

const columns = [
  { type: 'selection' },
  { title: '出厂编号', key: 'manuNo', width: 100 },
  { title: '安全阀型号', key: 'vavleModel', width: 150 },
  { title: '工作介质', key: 'workingMedia', width: 90 },
  { title: '要求整定压力MPa', key: 'setPress', width: 130 },
  { title: '工作压力MPa', key: 'workingPress', width: 110 },
  { title: '密封试验压力MPa', key: 'tightnessPress', width: 130 },
  { title: '安装位置', key: 'tagNo', width: 90 },
  { title: '设备代码', key: 'idNo', width: 110 },
  { title: '制造单位', key: 'manufacturer', ellipsis: { tooltip: true } }
]

function handleConfirm() {
  const picked = store.valveLibrary.filter((v) => checkedKeys.value.includes(v.id))
  if (!picked.length) return
  emit('confirm', picked.map((v) => ({ ...v })))
  emit('update:show', false)
  checkedKeys.value = []
}
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    title="添加阀门（安全阀库）"
    style="width: 980px"
    @update:show="(v) => emit('update:show', v)"
  >
    <div style="display: flex; justify-content: space-between; margin-bottom: 12px">
      <n-input v-model:value="keyword" placeholder="搜索出厂编号 / 型号 / 设备代码" style="width: 260px" clearable />
      <n-button type="primary" @click="handleConfirm">添加所选（{{ checkedKeys.length }}）</n-button>
    </div>
    <n-data-table
      v-model:checked-row-keys="checkedKeys"
      size="small"
      :columns="columns"
      :data="filtered"
      :row-key="(r) => r.id"
      max-height="380"
    />
  </n-modal>
</template>
