<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, h } from 'vue'
import * as echarts from 'echarts'
import { NIcon } from 'naive-ui'
import {
  ClipboardOutline,
  CheckmarkDoneOutline,
  TimeOutline,
  ReaderOutline,
  PeopleOutline
} from '@vicons/ionicons5'
import { useBusinessStore } from '@/store/business'

const store = useBusinessStore()

/* ---------- 统计卡片（来自 workbench/stats 接口） ---------- */
const statList = computed(() => [
  { label: '已校检总数（个）', value: store.stats.checkedTotal, icon: ClipboardOutline, color: '#1fc2b4' },
  { label: '已校验未审（个）', value: store.stats.unchecked, icon: CheckmarkDoneOutline, color: '#9b7ede' },
  { label: '待校检总数（个）', value: store.stats.waitTotal, icon: TimeOutline, color: '#f0a020' },
  { label: '委托单数', value: store.stats.commissionTotal, icon: ReaderOutline, color: '#4098fc' },
  { label: '总客户数', value: store.stats.customerTotal, icon: PeopleOutline, color: '#18a058' }
])

/** 图表筛选下拉：站点（校验人员所属站点），非送检单位 */
const companyOptions = computed(() => store.sites.map((s) => ({ label: s.name, value: s.name })))
const company = ref(null)

/* ---------- 校验统计图表（来自 workbench/chart 接口） ---------- */
const statType = ref('year')
const rangeStart = ref(Date.parse('2026-01'))
const rangeEnd = ref(Date.parse('2026-12'))
const chartRef = ref(null)
let chart = null

function fmtMonth(ts) {
  const d = new Date(ts)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

const companyName = ref('总计')

function buildOption() {
  const cd = store.chartData
  return {
    color: ['#1fc2b4', '#9b7ede'],
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    legend: {
      data: [companyName.value + '-已审', companyName.value + '-未审'],
      top: 6,
      textStyle: { fontSize: 12, color: '#606266' }
    },
    grid: { left: 50, right: 24, top: 44, bottom: 30 },
    xAxis: { type: 'category', data: cd.months.length ? cd.months : ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'], axisLabel: { color: '#606266' } },
    yAxis: {
      type: 'value',
      axisLabel: { color: '#606266' },
      splitLine: { lineStyle: { color: '#eef0f4' } }
    },
    series: [
      { name: companyName.value + '-已审', type: 'bar', barWidth: 22, data: cd.audited },
      { name: companyName.value + '-未审', type: 'bar', barWidth: 22, data: cd.unaudited }
    ]
  }
}

async function refreshChart() {
  companyName.value = companyOptions.value.find((o) => o.value === company.value)?.label || '总计'
  try {
    await store.fetchChart({
      start: fmtMonth(rangeStart.value),
      end: fmtMonth(rangeEnd.value),
      site: company.value ? companyName.value : ''
    })
  } catch {
    /* 接口异常时保留旧数据 */
  }
  if (chart) chart.setOption(buildOption())
}

function handleResize() {
  chart && chart.resize()
  pieChart && pieChart.resize()
}

/* ---------- 安全阀检验统计饼图（待检验 / 已检验，来自 workbench/stats） ---------- */
const pieRef = ref(null)
let pieChart = null

const pieOption = computed(() => {
  const s = store.stats
  return {
    color: ['#1fc2b4', '#9b7ede'],
    tooltip: { trigger: 'item', formatter: '{b}: {c}（{d}%）' },
    legend: { bottom: 0, icon: 'circle', itemWidth: 10, textStyle: { fontSize: 12, color: '#606266' } },
    series: [
      {
        type: 'pie',
        radius: '62%',
        center: ['50%', '44%'],
        label: { formatter: '{b}', color: '#606266', fontSize: 12 },
        labelLine: { length: 12, length2: 8 },
        data: [
          { name: '待检验', value: Number(s.waitTotal) || 0 },
          { name: '已检验', value: Number(s.checkedTotal) || 0 }
        ]
      }
    ]
  }
})

/* stats 接口返回后刷新饼图 */
watch(
  () => [store.stats.waitTotal, store.stats.checkedTotal],
  () => pieChart && pieChart.setOption(pieOption.value)
)

onMounted(() => {
  chart = echarts.init(chartRef.value)
  chart.setOption(buildOption())
  pieChart = echarts.init(pieRef.value)
  pieChart.setOption(pieOption.value)
  window.addEventListener('resize', handleResize)
  // 工作台只加载本页需要的接口：统计卡片 + 两个排名 + 站点下拉 + 图表数据
  store.fetchStats().catch(() => {})
  store.fetchRanks().catch(() => {})
  store.fetchSites().catch(() => {})
  refreshChart()
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
  chart && chart.dispose()
  chart = null
})

/* ---------- 检验人员排名 / 送检单位排名（workbench/rank 接口） ---------- */
const inspectorRanking = computed(() => store.inspectorRanking)

/** 前三名绶带奖牌序号，其余纯数字 */
function rankRender() {
  return (row) => {
    if (row.rank <= 3) {
      return h('span', { class: 'medal-badge m' + row.rank }, String(row.rank))
    }
    return h('span', { style: 'color:#606266' }, String(row.rank))
  }
}

const inspectorColumns = [
  { title: '排名', key: 'rank', width: 56, align: 'center', render: rankRender() },
  { title: '姓名', key: 'name', width: 80 },
  { title: '检验机构', key: 'org', ellipsis: { tooltip: true } },
  { title: '检验数量', key: 'count', width: 80, align: 'right' }
]

const unitColumns = [
  { title: '排名', key: 'rank', width: 56, align: 'center', render: rankRender() },
  {
    title: '单位',
    key: 'unit',
    align: 'center',
    render(r) {
      // 超长换行显示，不用省略号
      return h(
        'span',
        { style: 'display:inline-block;word-break:break-all;white-space:normal;line-height:1.5' },
        r.unit
      )
    }
  },
  { title: '送检数量', key: 'count', width: 84, align: 'right' }
]
</script>

<template>
  <div class="dashboard">
    <div class="dash-main">
      <!-- 筛选栏 -->
      <div class="page-card dash-header">
        <n-tag type="info" :bordered="false">总计</n-tag>
        <n-select
          v-model:value="company"
          :options="companyOptions"
          filterable
          clearable
          placeholder="全部站点"
          style="width: 280px"
          size="small"
          @update:value="refreshChart"
        />
      </div>

      <!-- 统计卡片 -->
      <div class="stat-grid">
        <div v-for="s in statList" :key="s.label" class="page-card stat-card">
          <div class="sc-icon" :style="{ background: s.color + '1a', color: s.color }">
            <n-icon :size="28" :component="s.icon" />
          </div>
          <div>
            <div class="sc-label">{{ s.label }}</div>
            <div class="sc-value" :style="{ color: s.color }">{{ s.value }}</div>
          </div>
        </div>
      </div>

      <!-- 图表 -->
      <div class="page-card chart-card">
        <div class="cc-head">
          <span class="cc-title">安全阀校验数统计</span>
          <div class="cc-controls">
            <n-select
              v-model:value="statType"
              size="small"
              style="width: 90px"
              :options="[
                { label: '按年', value: 'year' },
                { label: '按月', value: 'month' }
              ]"
            />
            <n-date-picker
              v-model:value="rangeStart"
              type="month"
              size="small"
              format="yyyy-MM"
              :clearable="false"
              style="width: 128px"
            />
            <n-date-picker
              v-model:value="rangeEnd"
              type="month"
              size="small"
              format="yyyy-MM"
              :clearable="false"
              style="width: 128px"
            />
            <n-button size="small" type="primary" @click="refreshChart">搜索</n-button>
          </div>
        </div>
        <div ref="chartRef" class="cc-chart"></div>
      </div>

      <!-- 饼图 + 检验人员排名 -->
      <div class="bottom-row">
        <div class="page-card pie-card">
          <div class="card-title">安全阀检验统计</div>
          <div ref="pieRef" class="pie-chart"></div>
        </div>
        <div class="page-card rank-inline">
          <div class="card-title">检验人员排名</div>
          <n-data-table size="small" :columns="inspectorColumns" :data="inspectorRanking" :bordered="false" :bottom-bordered="false" />
        </div>
      </div>
    </div>

    <!-- 右侧栏：送检单位排名 -->
    <div class="dash-side">
      <div class="page-card rank-card">
        <div class="card-title">送检单位排名</div>
        <div class="rc-search">
          <n-date-picker type="date" size="small" placeholder="开始日" style="width: 110px" />
          <n-date-picker type="date" size="small" placeholder="截止日" style="width: 110px" />
          <n-button size="small" type="primary">搜索</n-button>
        </div>
        <n-data-table
          size="small"
          :columns="unitColumns"
          :data="store.unitRanking"
          :bordered="false"
          :bottom-bordered="false"
        />
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.dashboard {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.dash-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.dash-header {
  display: flex;
  align-items: center;
  gap: 10px;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 14px;

  .sc-icon {
    width: 56px;
    height: 56px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .sc-label {
    font-size: 13px;
    color: $text-secondary;
  }

  .sc-value {
    font-size: 26px;
    font-weight: 700;
    line-height: 1.3;
  }
}

.chart-card {
  .cc-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    flex-wrap: wrap;
    gap: 10px;

    .cc-title {
      font-size: 15px;
      font-weight: 600;
      padding-left: 8px;
      border-left: 3px solid $primary;
    }

    .cc-controls {
      display: flex;
      align-items: center;
      gap: 8px;
    }
  }

  .cc-chart {
    height: 340px;
    width: 100%;
  }
}

/* 饼图 + 检验人员排名 并排一行 */
.bottom-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  align-items: stretch;

  .pie-card,
  .rank-inline {
    display: flex;
    flex-direction: column;
  }

  .pie-chart {
    flex: 1;
    min-height: 260px;
    width: 100%;
  }
}

.dash-side {
  width: 400px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.card-title {
  font-size: 14px;
  font-weight: 600;
  padding-left: 8px;
  border-left: 3px solid $primary;
  margin-bottom: 12px;
}

.rc-search {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 10px;
}
</style>

<!-- 排名列奖牌样式必须全局：span 在 n-data-table 内部渲染，scoped 选择器匹配不到 -->
<style lang="scss">
/* 排名列前三名奖牌（蓝色绶带 + 金/银/铜圆牌） */
.medal-badge {
  position: relative;
  z-index: 0;
  display: inline-flex;
  width: 24px;
  height: 24px;
  margin-top: 9px;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  line-height: 1;

  &::before,
  &::after {
    content: '';
    position: absolute;
    top: -8px;
    width: 6px;
    height: 13px;
    background: linear-gradient(180deg, #5aa0f2, #3c7fe0);
    z-index: -1;
  }

  &::before {
    left: 5px;
    transform: skewX(-14deg);
  }

  &::after {
    right: 5px;
    transform: skewX(14deg);
  }

  &.m1 {
    background: radial-gradient(circle at 35% 30%, #ffd76e, #f5a623 60%, #e08e0b);
    box-shadow: 0 1px 3px rgba(224, 142, 11, 0.5);
  }

  &.m2 {
    background: radial-gradient(circle at 35% 30%, #e6dcf8, #c0abe6 60%, #a68fd2);
    box-shadow: 0 1px 3px rgba(166, 143, 210, 0.5);
  }

  &.m3 {
    background: radial-gradient(circle at 35% 30%, #f5c6a6, #e09a70 60%, #cb8052);
    box-shadow: 0 1px 3px rgba(203, 128, 82, 0.5);
  }
}
</style>
