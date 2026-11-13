<script setup>
import { computed } from 'vue'
import { NDescriptions, NDescriptionsItem, NTag } from 'naive-ui'
import { COMMISSION_STATUS } from '@/store/business'

const props = defineProps({
  show: { type: Boolean, default: false },
  row: { type: Object, default: null }
})
const emit = defineEmits(['update:show'])

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
    <template v-if="row">
      <n-descriptions bordered :column="3" size="small" label-placement="left">
        <n-descriptions-item label="委托单号">{{ row.code }}</n-descriptions-item>
        <n-descriptions-item label="状态">
          <n-tag :type="statusInfo.type" size="small" :bordered="false">{{ statusInfo.label }}</n-tag>
        </n-descriptions-item>
        <n-descriptions-item label="委托日期">{{ row.createTime }}</n-descriptions-item>
        <n-descriptions-item label="使用单位" :span="2">{{ row.custName }}</n-descriptions-item>
        <n-descriptions-item label="使用单位地址">{{ row.address }}</n-descriptions-item>
        <n-descriptions-item label="使用单位联系人">{{ row.client }}</n-descriptions-item>
        <n-descriptions-item label="使用单位联系电话">{{ row.clientTel }}</n-descriptions-item>
        <n-descriptions-item label="检验单位">{{ row.examName }}</n-descriptions-item>
        <n-descriptions-item label="委托单位">{{ row.applicant }}</n-descriptions-item>
        <n-descriptions-item label="委托单位地址">{{ row.custAddress }}</n-descriptions-item>
        <n-descriptions-item label="委托人">{{ row.contact }}</n-descriptions-item>
        <n-descriptions-item label="委托人联系电话">{{ row.contactTel }}</n-descriptions-item>
        <n-descriptions-item label="预约取阀时间">{{ row.planReceiverTime }}</n-descriptions-item>
        <n-descriptions-item label="送阀人">{{ row.deliverer }}</n-descriptions-item>
        <n-descriptions-item label="送阀时间">{{ row.deliveryTime }}</n-descriptions-item>
      </n-descriptions>

      <n-alert v-if="row.rejectReason" type="error" :show-icon="false" style="margin-top: 12px">
        退回原因：{{ row.rejectReason }}
      </n-alert>

      <n-divider title-placement="left">安全阀清单（{{ row.valves.length }} 台）</n-divider>
      <n-data-table size="small" :columns="valveColumns" :data="row.valves" :row-key="(r) => r._seq || r.id" max-height="300" :scroll-x="1000" />
    </template>
  </n-modal>
</template>
