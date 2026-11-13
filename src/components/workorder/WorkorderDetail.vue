<script setup>
import { computed } from 'vue'
import { NDescriptions, NDescriptionsItem } from 'naive-ui'

const props = defineProps({
  show: { type: Boolean, default: false },
  row: { type: Object, default: null }
})
const emit = defineEmits(['update:show'])

const r = computed(() => props.row?.result || null)
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    title="工单详情"
    style="width: 760px"
    @update:show="(v) => emit('update:show', v)"
  >
    <n-descriptions v-if="row" bordered :column="2" size="small" label-placement="left">
      <n-descriptions-item label="检验编号">{{ row.orderCode }}</n-descriptions-item>
      <n-descriptions-item label="委托单号">{{ row.code }}</n-descriptions-item>
      <n-descriptions-item label="使用单位">{{ row.custName }}</n-descriptions-item>
      <n-descriptions-item label="使用单位联系人">{{ row.client }} / {{ row.clientTel }}</n-descriptions-item>
      <n-descriptions-item label="设备代码">{{ row.idNo || '-' }}</n-descriptions-item>
      <n-descriptions-item label="出厂编号">{{ row.manuNo }}</n-descriptions-item>
      <n-descriptions-item label="安全阀型号">{{ row.vavleModel }}</n-descriptions-item>
      <n-descriptions-item label="安全阀类型">{{ row.vavleType }}</n-descriptions-item>
      <n-descriptions-item label="设备类型">{{ row.equipType }}</n-descriptions-item>
      <n-descriptions-item label="被保护设备种类">{{ row.protectedEquipType }}</n-descriptions-item>
      <n-descriptions-item label="工作介质">{{ row.workingMedia }}</n-descriptions-item>
      <n-descriptions-item label="工作温度">{{ row.workingTemp }}</n-descriptions-item>
      <n-descriptions-item label="安装位置">{{ row.tagNo || '-' }}</n-descriptions-item>
      <n-descriptions-item label="制造单位">{{ row.manufacturer }}</n-descriptions-item>
      <n-descriptions-item label="制造许可证号">{{ row.licenseNo }}</n-descriptions-item>
      <n-descriptions-item label="出厂日期">{{ row.manuDate }}</n-descriptions-item>
      <n-descriptions-item label="公称通径mm">{{ row.diameterNominal }}</n-descriptions-item>
      <n-descriptions-item label="流道直径mm">{{ row.seatSize || '-' }}</n-descriptions-item>
      <n-descriptions-item label="公称压力MPa">{{ row.pressNominal }}</n-descriptions-item>
      <n-descriptions-item label="设计压力MPa">{{ row.designPress }}</n-descriptions-item>
      <n-descriptions-item label="工作压力MPa">{{ row.workingPress }}</n-descriptions-item>
      <n-descriptions-item label="要求整定压力MPa">{{ row.setPress }}</n-descriptions-item>
      <n-descriptions-item label="密封试验压力MPa">{{ row.tightnessPress }}</n-descriptions-item>
      <n-descriptions-item label="压力级别范围MPa">
        {{ row.pressClassRangeMin }} - {{ row.pressClassRangeMax }}
      </n-descriptions-item>
    </n-descriptions>

    <template v-if="r">
      <n-divider title-placement="left">检验结果</n-divider>
      <n-descriptions bordered :column="2" size="small" label-placement="left">
        <n-descriptions-item label="校验结果">
          <n-tag :type="r.testStatus === '合格' ? 'success' : r.testStatus === '不合格' ? 'error' : 'warning'" size="small" :bordered="false">
            {{ r.testStatus }}
          </n-tag>
        </n-descriptions-item>
        <n-descriptions-item label="整定压力实测">{{ r.testingPressure1 }} / {{ r.testingPressure2 }} / {{ r.testingPressure3 }}</n-descriptions-item>
        <n-descriptions-item label="校验介质">{{ r.testMedia }}（{{ r.testTemprature }}）</n-descriptions-item>
        <n-descriptions-item label="校验方式">{{ r.parity }}</n-descriptions-item>
        <n-descriptions-item label="外观检查">{{ r.visualExamination }}</n-descriptions-item>
        <n-descriptions-item label="拆卸检查">{{ r.disassemblyInspection }}</n-descriptions-item>
        <n-descriptions-item label="校验设备">{{ r.deviceModel }}（{{ r.deviceSn }}）</n-descriptions-item>
        <n-descriptions-item label="压力表">{{ r.pressureGaugeNum }}（{{ r.pressureGaugeAccuracy }} / {{ r.pressureGaugeRange }}MPa）</n-descriptions-item>
        <n-descriptions-item label="校验员">{{ row.inspector }}</n-descriptions-item>
        <n-descriptions-item label="校验时间">{{ row.inspectorTime }}</n-descriptions-item>
        <n-descriptions-item label="备注" :span="2">{{ r.note || '-' }}</n-descriptions-item>
      </n-descriptions>
    </template>

    <template v-if="row && row.rejectReason">
      <n-divider title-placement="left">退回原因</n-divider>
      <n-alert type="error" :show-icon="false">{{ row.rejectReason }}</n-alert>
    </template>
  </n-modal>
</template>
