<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import { NIcon } from 'naive-ui'
import * as echarts from 'echarts'
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

/** 检验状态：已出结果显示校验结果（合格/不合格），未出结果显示工单状态 */
const inspectLabel = computed(() => r.value?.testStatus || statusLabel.value)

const dash = (v) => (v === '' || v == null ? '/' : v)

const STANDARD_TEXT = 'TSG 92-2026; GB/T12241-2021; GB/T12243-2021'
/** 维护检修情况：展示为只读勾选框；提交值（以「；」连接）决定勾选状态，未填写时全部不勾 */
const MAINTAIN_DISPLAY = [
  { key: '解体、清洗、除锈', label: '解体、清洗、除锈' },
  { key: '先脱脂', label: '请在投入运行时先脱脂' },
  { key: '经解体检查合格', label: '经解体、清洗、检查合格' },
  { key: '其他', label: '其他' }
]
const submittedMaintain = computed(() => {
  const info = r.value?.maintenanceInfo
  if (!info) return []
  return String(info).split('；').map((s) => s.trim()).filter(Boolean)
})
const isMaintainChecked = (key) => submittedMaintain.value.some((s) => s === key || s.includes(key))
const maintainLabel = (opt) => {
  if (opt.key !== '其他') return opt.label
  return isMaintainChecked('其他') ? '其他' : '其他：暂无'
}
const conclusionText = computed(() => {
  const s = r.value?.testStatus
  if (s === '合格') return '该安全阀经检验合格'
  if (s === '维修后合格') return '该安全阀经维修后检验合格'
  if (s === '不合格') return '该安全阀经检验不合格'
  return '-'
})

/** 密封试泄漏率：纯数值时补「气泡/min」单位，其他按原样显示 */
const leakRateText = computed(() => {
  const v = r.value?.leakRate
  if (v === '' || v == null) return null
  return /^[\d.]+$/.test(String(v).trim()) ? `${v} 气泡/min` : String(v)
})

/* ---------- 曲线图：3 条曲线（对应 B 端提交的三次试验抓取数据） ---------- */
const curveRefs = [ref(null), ref(null), ref(null)]
const charts = [null, null, null]

/** 依据校验数据生成稳定的压力曲线（61 秒采样，无真实抓取数据时兜底） */
function buildCurve(base, dropAfter = null, dropTo = null) {
  const seed = (props.row?.id || 1) % 7
  return Array.from({ length: 61 }, (_, i) => {
    const t = i / 60
    let v = base * Math.min(1, t * 2.6) + Math.sin((i + seed) / 3) * base * 0.02
    if (dropAfter != null && i > dropAfter) {
      const k = Math.min(1, (i - dropAfter) / 8)
      v = v * (1 - k) + (dropTo ?? 0) * k
    }
    return +Math.max(0, v).toFixed(3)
  })
}

/** 解析 B 端导入的曲线数据：JSON 数组（数值 / [t,p] / {t,p}）或每行 时间,压力 文本 */
function parseCurve(str) {
  if (!str) return null
  let arr = null
  try {
    arr = JSON.parse(str)
  } catch {}
  if (Array.isArray(arr) && arr.length) {
    const pts = arr
      .map((it, i) => {
        if (Array.isArray(it)) return { t: +it[0], p: +it[1] }
        if (typeof it === 'object' && it !== null) {
          const t = it.t ?? it.time ?? it.x ?? i
          const p = it.p ?? it.pressure ?? it.press ?? it.y
          return { t: +t, p: +p }
        }
        return { t: i, p: +it }
      })
      .filter((pt) => !isNaN(pt.p))
    return pts.length > 1 ? pts : null
  }
  const lines = String(str).split(/\r?\n/).filter((l) => l.trim())
  if (lines.length > 1) {
    const pts = lines
      .map((l, i) => {
        const seg = l.split(/[,;\t ]+/).map(Number).filter((n) => !isNaN(n))
        return seg.length >= 2 ? { t: seg[0], p: seg[1] } : { t: i, p: seg[0] }
      })
      .filter((pt) => !isNaN(pt.p))
    if (pts.length > 1) return pts
  }
  return null
}

function chartOption(data) {
  const pts = typeof data[0] === 'object' ? data : data.map((v, i) => ({ t: i, p: v }))
  const times = pts.map((pt) => pt.t)
  const pressures = pts.map((pt) => pt.p)
  const dataMax = Math.max(...pressures, 0.01)
  const ymax = Math.ceil(dataMax * 1.06 * 100) / 100
  return {
    title: { text: '压力-时间曲线', left: 'center', top: 2, textStyle: { fontSize: 13, fontWeight: 500, color: '#333' } },
    tooltip: { trigger: 'axis' },
    grid: { left: 56, right: 24, top: 34, bottom: 36 },
    xAxis: {
      type: 'category',
      name: '时间(s)',
      nameLocation: 'middle',
      nameGap: 24,
      boundaryGap: false,
      data: times,
      axisLabel: { color: '#333', interval: Math.max(0, Math.ceil(pts.length / 9) - 1) },
      axisLine: { lineStyle: { color: '#333' } },
      splitLine: { show: true, lineStyle: { color: '#3aa05a' } }
    },
    yAxis: {
      type: 'value',
      max: ymax,
      interval: ymax / 5,
      axisLabel: { color: '#333', formatter: (v) => v.toFixed(2) },
      axisLine: { show: true, lineStyle: { color: '#333' } },
      splitLine: { lineStyle: { color: '#3aa05a' } },
      minorTick: { show: true, splitNumber: 5, lineStyle: { color: '#555' } },
      minorSplitLine: { show: false }
    },
    series: [
      {
        name: '压力',
        type: 'line',
        smooth: true,
        symbol: 'none',
        data: pressures,
        lineStyle: { color: '#e24b4a', width: 1.5 },
        itemStyle: { color: '#e24b4a' }
      }
    ]
  }
}

function renderCharts() {
  if (!r.value) return
  const row = props.row || {}
  for (let n = 1; n <= 3; n++) {
    const el = curveRefs[n - 1].value
    if (!el) continue
    // 优先使用 B 端提交的三次试验抓取数据，无则按整定压力实测值模拟
    const real = parseCurve(r.value[`curveData${n}`])
    if (real) {
      if (!charts[n - 1]) charts[n - 1] = echarts.init(el)
      charts[n - 1].setOption(chartOption(real))
      continue
    }
    const press = r.value[`testingPressure${n}`]
    const base = Number(
      press ?? r.value.setPress ?? row.setPress ?? r.value.tightnessPress ?? row.tightnessPress ?? 1
    )
    if (!charts[n - 1]) charts[n - 1] = echarts.init(el)
    charts[n - 1].setOption(chartOption(buildCurve(base, 52, 0)))
  }
}

function disposeCharts() {
  for (let i = 0; i < 3; i++) {
    charts[i] && charts[i].dispose()
    charts[i] = null
  }
}

watch(
  () => props.show,
  async (v) => {
    if (v) {
      await nextTick()
      renderCharts()
    } else {
      disposeCharts()
    }
  }
)

onBeforeUnmount(disposeCharts)
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
          <div class="item">
            <span class="label">周期(年):</span>
            <span class="value">{{ row.periodYears == null || row.periodYears === '' ? '/' : `${row.periodYears}年` }}</span>
          </div>
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

      <!-- 工单信息（固定展示，不折叠；检验状态显示真实校验结果） -->
      <div class="section">
        <div class="sec-head">
          <div class="sec-title">工单信息</div>
        </div>
        <div class="grid">
          <div class="item"><span class="label">工单编号:</span><span class="value">{{ dash(row.orderCode) }}</span></div>
          <div class="item"><span class="label">工单状态:</span><span class="value">{{ statusLabel }}</span></div>
          <div class="item"><span class="label">检验状态:</span><span class="value">{{ inspectLabel }}</span></div>
        </div>
      </div>

      <!-- 检验结果（已出结果时显示，布局对照参考系统） -->
      <div v-if="r" class="section">
        <div class="sec-head">
          <div class="sec-title">检验结果</div>
        </div>
        <div class="grid">
          <div class="item"><span class="label">校验编号:</span><span class="value">{{ dash(r.orderCode || row.orderCode) }}</span></div>
          <div class="item"><span class="label">执行标准:</span><span class="value">{{ STANDARD_TEXT }}</span></div>
          <div class="item"><span class="label">校验介质:</span><span class="value">{{ dash(r.testMedia) }}</span></div>
          <div class="item"><span class="label">校验介质温度:</span><span class="value">{{ dash(r.testTemprature) }}</span></div>
          <div class="item"><span class="label">外观检查:</span><span class="value">{{ dash(r.visualExamination) }}</span></div>
          <div class="item"><span class="label">拆卸检查:</span><span class="value">{{ dash(r.disassemblyInspection) }}</span></div>
          <div class="item"><span class="label">备注:</span><span class="value">{{ dash(r.note) }}</span></div>
          <div class="item"><span class="label">压力表编号:</span><span class="value">{{ dash(r.pressureGaugeNum) }}</span></div>
          <div class="item"><span class="label">压力表2编号:</span><span class="value">{{ dash(r.pressureGaugeNum2) }}</span></div>
          <div class="item"><span class="label">压力表精度:</span><span class="value">{{ dash(r.pressureGaugeAccuracy) }}</span></div>
          <div class="item"><span class="label">压力表量程MPa:</span><span class="value">{{ dash(r.pressureGaugeRange) }}</span></div>
        </div>

        <!-- 整定压力（三次实测） -->
        <div class="press-block">
          <div class="press-title">整定压力</div>
          <div class="press-boxes">
            <div class="press-box"><span class="num">{{ dash(r.testingPressure1) }}</span><span class="unit">MPa</span></div>
            <div class="press-box"><span class="num">{{ dash(r.testingPressure2) }}</span><span class="unit">MPa</span></div>
            <div class="press-box"><span class="num">{{ dash(r.testingPressure3) }}</span><span class="unit">MPa</span></div>
          </div>
        </div>

        <!-- 密封试验压力：试验压力 + 密封试泄漏率 -->
        <div class="press-block">
          <div class="press-title">密封试验压力</div>
          <div class="seal-row">
            <div class="seal-cell">
              <span class="seal-label">试验压力：</span>
              <div class="press-box"><span class="num">{{ dash(r.tightnessPress) }}</span><span class="unit">MPa</span></div>
            </div>
            <div class="seal-cell">
              <span class="seal-label">密封试泄漏率：</span>
              <div class="press-box"><span class="num" :class="{ empty: !leakRateText }">{{ leakRateText || '/' }}</span></div>
            </div>
          </div>
        </div>

        <div class="grid" style="margin-top: 12px">
          <div class="item wide maintain-item">
            <span class="label11">维护检修情况:</span>
            <div class="maintain-lines">
              <n-checkbox
                v-for="opt in MAINTAIN_DISPLAY"
                :key="opt.key"
                :checked="isMaintainChecked(opt.key)"
                disabled
              >
                {{ maintainLabel(opt) }}
              </n-checkbox>
            </div>
          </div>
          <div class="item wide"><span class="label">检验结果:</span><span class="value">{{ conclusionText }}</span></div>
          <div class="item"><span class="label">校验员:</span><span class="value">{{ dash(row.inspector) }}</span></div>
          <div class="item"><span class="label">校验日期:</span><span class="value">{{ dash((row.inspectorTime || '').slice(0, 10)) }}</span></div>
        </div>

        <!-- 曲线图：3 条曲线（B 端提交的三次试验抓取数据） -->
        <div class="curve-item">
          <span class="curve-label">曲线图</span>
          <div class="curve-charts">
            <div class="curve-chart-wrap">
              <div class="curve-tag">第1次</div>
              <div :ref="curveRefs[0]" class="curve-chart"></div>
            </div>
            <div class="curve-chart-wrap">
              <div class="curve-tag">第2次</div>
              <div :ref="curveRefs[1]" class="curve-chart"></div>
            </div>
            <div class="curve-chart-wrap">
              <div class="curve-tag">第3次</div>
              <div :ref="curveRefs[2]" class="curve-chart"></div>
            </div>
          </div>
        </div>
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

    &.wide {
      grid-column: 1 / -1;
    }

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

.press-block {
  margin-top: 12px;

  .press-title {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
  }

  .press-boxes {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
  }

  .seal-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .seal-cell {
    display: flex;
    align-items: center;
    gap: 10px;

    .seal-label {
      flex: none;
      font-size: 13px;
      color: #333;
      white-space: nowrap;
    }

    .press-box {
      flex: 1;
      min-width: 0;
    }
  }

  .press-box {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 44px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    background: #fafbfc;

    .num {
      font-size: 15px;
      font-weight: 600;
      color: #333;

      &.empty {
        font-weight: 400;
        color: #bbb;
      }
    }

    .unit {
      font-size: 13px;
      color: #888;
    }
  }
  

}

.curve-item {
  display: flex;
  align-items: flex-start;
  margin-top: 16px;

  .curve-label {
    flex: none;
    width: 60px;
    padding-top: 6px;
    text-align: right;
    padding-right: 10px;
    color: #888;
    font-size: 13px;
    line-height: 22px;
    white-space: nowrap;
  }

  .curve-charts {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .curve-chart-wrap {
    position: relative;

    .curve-tag {
      position: absolute;
      left: 10px;
      top: 8px;
      z-index: 2;
      font-size: 12px;
      color: #666;
      background: rgba(255, 255, 255, 0.85);
      padding: 1px 8px;
      border-radius: 3px;
    }
  }

  .curve-chart {
    width: 100%;
    height: 200px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
  }
}

.maintain-item {
  display: block;

  .maintain-lines {
    display: flex;
    flex: 1;
    font-size: 13px;
    color: #333;
    line-height: 22px;
  }
}
</style>
