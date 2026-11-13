<script setup>
import { ref, watch, nextTick, onBeforeUnmount, computed } from 'vue'
import * as echarts from 'echarts'
import { NIcon } from 'naive-ui'
import { PrintOutline } from '@vicons/ionicons5'

const props = defineProps({
  show: { type: Boolean, default: false },
  row: { type: Object, default: null }
})
const emit = defineEmits(['update:show'])

const chartRef = ref(null)
let chart = null

/** 依据校验数据生成一条稳定的压力曲线（演示环境） */
const points = computed(() => {
  const base = Number(props.row?.result?.setPress ?? props.row?.setPress ?? 1.0)
  const seed = (props.row?.id || 1) % 7
  const second = Array.from({ length: 61 }, (_, i) => {
    const t = i / 60
    const ramp = base * Math.min(1, t * 2.6)
    const ripple = Math.sin((i + seed) / 3) * base * 0.02
    return +(ramp + ripple).toFixed(3)
  })
  const seal = second.map((v, i) => {
    const keep = i > 42 ? v * (0.72 + Math.sin(i / 4) * 0.01) : v
    return +keep.toFixed(3)
  })
  return { second, seal, base }
})

function render() {
  if (!chartRef.value) return
  if (!chart) chart = echarts.init(chartRef.value)
  const { second, seal, base } = points.value
  chart.setOption({
    color: ['#1fc2b4', '#9b7ede'],
    tooltip: { trigger: 'axis' },
    legend: { data: ['整定压力实测', '密封试验压力'], top: 4, textStyle: { fontSize: 12, color: '#606266' } },
    grid: { left: 56, right: 24, top: 44, bottom: 34 },
    xAxis: {
      type: 'category',
      name: '时间/s',
      data: Array.from({ length: 61 }, (_, i) => i),
      axisLabel: { color: '#606266' }
    },
    yAxis: {
      type: 'value',
      name: '压力/MPa',
      axisLabel: { color: '#606266' },
      splitLine: { lineStyle: { color: '#eef0f4' } }
    },
    series: [
      { name: '整定压力实测', type: 'line', smooth: true, symbol: 'none', data: second },
      { name: '密封试验压力', type: 'line', smooth: true, symbol: 'none', data: seal },
      {
        name: '要求整定压力',
        type: 'line',
        symbol: 'none',
        lineStyle: { type: 'dashed', color: '#f0a020' },
        data: Array.from({ length: 61 }, () => base),
        tooltip: { show: false }
      }
    ]
  })
}

watch(
  () => props.show,
  async (v) => {
    if (v) {
      await nextTick()
      render()
    } else if (chart) {
      chart.dispose()
      chart = null
    }
  }
)

onBeforeUnmount(() => {
  chart && chart.dispose()
  chart = null
})

function printCurve() {
  if (!chart) return
  const img = chart.getDataURL({ pixelRatio: 2, backgroundColor: '#fff' })
  const win = window.open('', '_blank', 'width=900,height=700')
  if (!win) return
  win.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>校验曲线</title>
  <style>@page{size:A4} body{font-family:'Microsoft YaHei',serif;padding:24px}
  h1{text-align:center;font-size:18px} img{width:100%}</style></head>
  <body><h1>安全阀校验曲线（${props.row?.orderCode || ''}）</h1><img src="${img}" />
  <script>window.onload=function(){setTimeout(function(){window.print()},300)}<\/script></body></html>`)
  win.document.close()
}
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    :title="`校验曲线 ${row?.orderCode || ''}`"
    style="width: 900px"
    @update:show="(v) => emit('update:show', v)"
  >
    <div ref="chartRef" class="curve-chart"></div>
    <template #footer>
      <div class="curve-footer">
        <n-button @click="emit('update:show', false)">关闭</n-button>
        <n-button type="primary" @click="printCurve">
          <template #icon>
            <n-icon :component="PrintOutline" />
          </template>
          打印
        </n-button>
      </div>
    </template>
  </n-modal>
</template>

<style lang="scss" scoped>
.curve-chart {
  width: 100%;
  height: 380px;
}

.curve-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
