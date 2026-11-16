<script setup>
import { ref, computed, watch } from 'vue'
import { NDescriptions, NDescriptionsItem, NTag } from 'naive-ui'
import { COMMISSION_STATUS, useBusinessStore } from '@/store/business'

const props = defineProps({
  show: { type: Boolean, default: false },
  row: { type: Object, default: null }
})
const emit = defineEmits(['update:show'])

const store = useBusinessStore()

/* 打开时从后端拉取完整详情（含安全阀清单） */
const detail = ref(null)
const detailLoading = ref(false)

watch(
  () => [props.show, props.row?.id],
  async ([show, id]) => {
    if (show && id) {
      detailLoading.value = true
      detail.value = null
      try {
        detail.value = await store.fetchCommissionDetail(id)
      } catch {
        detail.value = null
      } finally {
        detailLoading.value = false
      }
    } else if (!show) {
      detail.value = null
    }
  },
  { immediate: true }
)

const view = computed(() => detail.value || props.row)
const valves = computed(() => view.value?.valves || [])

const valveColumns = [
  { title: '#', key: 'idx', width: 50, render: (r, i) => i + 1 },
  { title: '安全阀型号', key: 'vavleModel' },
  { title: '公称通径mm', key: 'diameterNominal', width: 100 },
  { title: '出厂编号', key: 'manuNo', width: 110 },
  { title: '工作压力MPa', key: 'workingPress', width: 110 },
  { title: '要求整定压力MPa', key: 'setPress', width: 130 },
  { title: '安装位置', key: 'tagNo', width: 100 },
  { title: '工作介质', key: 'workingMedia', width: 90 },
  { title: '制造单位', key: 'manufacturer', ellipsis: { tooltip: true } },
  { title: '设备代码', key: 'idNo', width: 120 }
]

const statusInfo = computed(() => (props.row ? COMMISSION_STATUS[props.row.status] : null))
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    :title="`委托单详情 - ${row?.code || ''}`"
    style="width: 900px"
    @update:show="(v) => emit('update:show', v)"
  >
    <template v-if="view">
      <n-spin :show="detailLoading">
        <n-descriptions bordered :column="3" size="small" label-placement="left">
          <n-descriptions-item label="委托单号">{{ view.code }}</n-descriptions-item>
          <n-descriptions-item label="状态">
            <n-tag :type="statusInfo?.type || 'default'" size="small" :bordered="false">{{ statusInfo?.label || view.status }}</n-tag>
          </n-descriptions-item>
          <n-descriptions-item label="委托日期">{{ view.createTime }}</n-descriptions-item>
          <n-descriptions-item label="使用单位" :span="2">{{ view.custName }}</n-descriptions-item>
          <n-descriptions-item label="使用单位地址">{{ view.address }}</n-descriptions-item>
          <n-descriptions-item label="使用单位联系人">{{ view.client }}</n-descriptions-item>
          <n-descriptions-item label="使用单位联系电话">{{ view.clientTel }}</n-descriptions-item>
          <n-descriptions-item label="检验单位">{{ view.examName }}</n-descriptions-item>
          <n-descriptions-item label="委托单位">{{ view.applicant }}</n-descriptions-item>
          <n-descriptions-item label="委托单位地址">{{ view.custAddress }}</n-descriptions-item>
          <n-descriptions-item label="委托人">{{ view.contact }}</n-descriptions-item>
          <n-descriptions-item label="委托人联系电话">{{ view.contactTel }}</n-descriptions-item>
          <n-descriptions-item label="预约取阀时间">{{ view.planReceiverTime }}</n-descriptions-item>
          <n-descriptions-item label="送阀人">{{ view.deliverer }}</n-descriptions-item>
          <n-descriptions-item label="送阀时间">{{ view.deliveryTime }}</n-descriptions-item>
        </n-descriptions>

        <n-alert v-if="view.rejectReason" type="error" :show-icon="false" style="margin-top: 12px">
          退回原因：{{ view.rejectReason }}
        </n-alert>

        <n-divider title-placement="left">安全阀清单（{{ valves.length }} 台）</n-divider>
        <n-data-table size="small" :columns="valveColumns" :data="valves" :row-key="(r) => r.id" max-height="300" :scroll-x="1000" />
      </n-spin>
    </template>
  </n-modal>
</template>
