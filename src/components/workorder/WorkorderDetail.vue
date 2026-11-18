<script setup>
import { ref, computed } from 'vue'
import { NDescriptions, NDescriptionsItem, NIcon } from 'naive-ui'
import { ChevronUpOutline, ChevronDownOutline } from '@/utils/icons'
import { WORKORDER_STATUS } from '@/store/business'

const props = defineProps({
  show: { type: Boolean, default: false },
  row: { type: Object, default: null }
})
const emit = defineEmits(['update:show'])

const r = computed(() => props.row?.result || null)

/* ---------- 分区折叠 ---------- */
const deviceOpen = ref(true)
const valveOpen = ref(true)

const statusLabel = computed(() => WORKORDER_STATUS[props.row?.status]?.label || props.row?.status || '-')

const dash = (v) => (v === '' || v == null ? '/' : v)
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    title="查看"
    style="width: 900px"
    @update:show="(v) => emit('update:show', v)"
  >
    <template v-if="row">
      <!-- 设备信息（收起时隐藏 设备代码/设计压力/工作温度） -->
      <div class="section">
        <div class="sec-head">
          <div class="sec-title">设备信息</div>
          <div class="fold-btn" @click="deviceOpen = !deviceOpen">
            {{ deviceOpen ? '收起' : '展开' }}
            <n-icon size="14" :depth="3">
              <component :is="deviceOpen ? ChevronUpOutline : ChevronDownOutline" />
            </n-icon>
          </div>
        </div>
        <div class="grid">
          <div class="item"><span class="label">设备类型:</span><span class="value">{{ dash(row.equipType) }}</span></div>
          <div class="item"><span class="label">工作介质:</span><span class="value">{{ dash(row.workingMedia) }}</span></div>
          <div class="item"><span class="label">要求整定压力MPa:</span><span class="value">{{ dash(row.setPress) }}</span></div>
          <div class="item"><span class="label">工作压力MPa:</span><span class="value">{{ dash(row.workingPress) }}</span></div>
          <div class="item"><span class="label">安装位置:</span><span class="value">{{ dash(row.tagNo) }}</span></div>
          <template v-if="deviceOpen">
            <div class="item"><span class="label">设备代码:</span><span class="value">{{ dash(row.idNo) }}</span></div>
            <div class="item"><span class="label">设计压力MPa:</span><span class="value">{{ dash(row.designPress) }}</span></div>
            <div class="item"><span class="label">工作温度℃:</span><span class="value">{{ dash(row.workingTemp) }}</span></div>
          </template>
        </div>
      </div>

      <!-- 安全阀信息（收起时隐藏 密封试验压力/背压修正/是否新阀/旧阀是否拆卸清洗/阀体及零部件是否完整/是否有金属铭牌） -->
      <div class="section">
        <div class="sec-head">
          <div class="sec-title">安全阀信息</div>
          <div class="fold-btn" @click="valveOpen = !valveOpen">
            {{ valveOpen ? '收起' : '展开' }}
            <n-icon size="14" :depth="3">
              <component :is="valveOpen ? ChevronUpOutline : ChevronDownOutline" />
            </n-icon>
          </div>
        </div>
        <div class="grid">
          <div class="item"><span class="label">安全阀型号:</span><span class="value">{{ dash(row.vavleModel) }}</span></div>
          <div class="item"><span class="label">制造单位:</span><span class="value">{{ dash(row.manufacturer) }}</span></div>
          <div class="item"><span class="label">安全阀类型:</span><span class="value">{{ dash(row.vavleType) }}</span></div>
          <div class="item"><span class="label">公称通径mm:</span><span class="value">{{ dash(row.diameterNominal) }}</span></div>
          <div class="item"><span class="label">制造许可证号:</span><span class="value">{{ dash(row.licenseNo) }}</span></div>
          <div class="item"><span class="label">流道直径mm:</span><span class="value">{{ dash(row.seatSize) }}</span></div>
          <div class="item"><span class="label">公称压力MPa:</span><span class="value">{{ dash(row.pressNominal) }}</span></div>
          <div class="item">
            <span class="label">压力级别范围MPa:</span>
            <span class="value">{{ row.pressClassRangeMin == null || row.pressClassRangeMin === '' ? '/' : `${row.pressClassRangeMin}~${row.pressClassRangeMax ?? ''}` }}</span>
          </div>
          <div class="item"><span class="label">出厂日期:</span><span class="value">{{ dash(row.manuDate) }}</span></div>
          <div class="item"><span class="label">出厂编号:</span><span class="value">{{ dash(row.manuNo) }}</span></div>
          <div class="item"><span class="label">周期(年):</span><span class="value">{{ dash(row.periodYears ?? 1) }}</span></div>
          <div class="item"><span class="label">冷差试验压力MPa:</span><span class="value">{{ dash(row.tightnessPress) }}</span></div>
          <template v-if="valveOpen">
            <div class="item"><span class="label">密封试验压力MPa:</span><span class="value">{{ dash(row.tightnessPress) }}</span></div>
            <div class="item"><span class="label">背压修正:</span><span class="value">{{ dash(row.pressCorrection) }}</span></div>
            <div class="item"><span class="label">是否新阀:</span><span class="value">{{ dash(row.isNew) }}</span></div>
            <div class="item"><span class="label">旧阀是否拆卸清洗:</span><span class="value">{{ dash(row.isRemovedCleaning) }}</span></div>
            <div class="item"><span class="label">阀体及零部件是否完整:</span><span class="value">{{ dash(row.isComplete) }}</span></div>
            <div class="item"><span class="label">是否有金属铭牌:</span><span class="value">{{ dash(row.isNameplate) }}</span></div>
          </template>
        </div>
      </div>

      <!-- 工单信息（固定展示，不折叠） -->
      <div class="section">
        <div class="sec-head">
          <div class="sec-title">工单信息</div>
        </div>
        <div class="grid">
          <div class="item"><span class="label">工单编号:</span><span class="value">{{ dash(row.orderCode) }}</span></div>
          <div class="item"><span class="label">工单状态:</span><span class="value">{{ statusLabel }}</span></div>
          <div class="item"><span class="label">检验状态:</span><span class="value">{{ statusLabel }}</span></div>
        </div>
      </div>

      <!-- 检验结果（已出结果时显示） -->
      <div v-if="r" class="section">
        <div class="sec-head">
          <div class="sec-title">检验结果</div>
        </div>
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
          <n-descriptions-item label="备注" :span="2">{{ r.note || '-' }}</n-descriptions-item>
        </n-descriptions>
      </div>

      <n-alert v-if="row.rejectReason" type="error" :show-icon="false" style="margin-top: 14px">
        退回原因：{{ row.rejectReason }}
      </n-alert>
    </template>
  </n-modal>
</template>

<style lang="scss" scoped>
.section {
  margin-bottom: 16px;

  .sec-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
  }

  .sec-title {
    position: relative;
    padding-left: 10px;
    font-size: 15px;
    font-weight: 600;
    color: #333;

    &::before {
      content: '';
      position: absolute;
      left: 0;
      top: 3px;
      bottom: 3px;
      width: 3px;
      background: #2b6de8;
      border-radius: 2px;
    }
  }

  .fold-btn {
    display: flex;
    align-items: center;
    gap: 2px;
    font-size: 13px;
    color: #666;
    cursor: pointer;
    user-select: none;

    &:hover {
      color: #2b6de8;
    }
  }
}

.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  row-gap: 16px;
  column-gap: 30px;
  padding: 4px 0;

  .item {
    display: flex;
    align-items: flex-start;

    .label {
      flex: none;
      flex: 1;
      text-align: right;
      padding-right: 10px;
      color: #888;
      font-size: 13px;
      line-height: 22px;
      white-space: nowrap;
    }

    .value {
      flex: 1.6;
      min-width: 0;
      color: #333;
      font-size: 13px;
      line-height: 22px;
      word-break: break-all;
    }
  }
}
</style>
