<script setup>
import { ref, onMounted, onBeforeUnmount, h } from 'vue'
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

/* ---------- 统计卡片 ---------- */
const statList = [
  { label: '已校检总数（个）', value: store.stats.checkedTotal, icon: ClipboardOutline, color: '#1fc2b4' },
  { label: '已校验未审（个）', value: store.stats.unchecked, icon: CheckmarkDoneOutline, color: '#9b7ede' },
  { label: '待校检总数（个）', value: store.stats.waitTotal, icon: TimeOutline, color: '#f0a020' },
  { label: '委托单数', value: store.stats.commissionTotal, icon: ReaderOutline, color: '#4098fc' },
  { label: '总客户数', value: store.stats.customerTotal, icon: PeopleOutline, color: '#18a058' }
]

const companyOptions = store.units.slice(0, 8).map((u) => ({ label: u.name, value: u.id }))
const company = ref(store.units[0].id)

/* ---------- 校验统计图表 ---------- */
const statType = ref('year')
const rangeStart = ref(Date.parse('2026-01'))
const rangeEnd = ref(Date.parse('2026-12'))
const chartRef = ref(null)
let chart = null

const months = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月']

function buildOption() {
  return {
    color: ['#1fc2b4', '#9b7ede'],
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    legend: {
      data: [companyName.value + '-已审', companyName.value + '-未审'],
      top: 6,
      textStyle: { fontSize: 12, color: '#606266' }
    },
    grid: { left: 50, right: 24, top: 44, bottom: 30 },
    xAxis: { type: 'category', data: months, axisLabel: { color: '#606266' } },
    yAxis: {
      type: 'value',
      max: 400,
      axisLabel: { color: '#606266' },
      splitLine: { lineStyle: { color: '#eef0f4' } }
    },
    series: [
      {
        name: companyName.value + '-已审',
        type: 'bar',
        barWidth: 22,
        data: [370, 210, 268, 198, 240, 150, 180, 220, 160, 210, 175, 120]
      },
      {
        name: companyName.value + '-未审',
        type: 'bar',
        barWidth: 22,
        data: [12, 8, 15, 6, 10, 4, 9, 12, 5, 8, 6, 3]
      }
    ]
  }
}

const companyName = ref(companyOptions[0].label)

function refreshChart() {
  companyName.value = companyOptions.find((o) => o.value === company.value)?.label || companyName.value
  if (chart) chart.setOption(buildOption())
}

function handleResize() {
  chart && chart.resize()
}

onMounted(() => {
  chart = echarts.init(chartRef.value)
  chart.setOption(buildOption())
  window.addEventListener('resize', handleResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
  chart && chart.dispose()
  chart = null
})

/* ---------- 检验人员排名 ---------- */
const inspectorRanking = store.inspectorRanking

/* ---------- 送检单位排名 ---------- */
const medals = ['🥇', '🥈', '🥉']

function rankRender(medalsArr) {
  return (row) => {
    if (row.rank <= 3) {
      return h('span', { class: 'medal' }, medalsArr[row.rank - 1])
    }
    return h('span', { style: 'color:#606266' }, String(row.rank))
  }
}

const inspectorColumns = [
  { title: '排名', key: 'rank', width: 56, align: 'center', render: (r) => String(r.rank) },
  { title: '姓名', key: 'name', width: 80 },
  { title: '检验机构', key: 'org', ellipsis: { tooltip: true } },
  { title: '检验数量', key: 'count', width: 80, align: 'right' }
]

const unitColumns = [
  { title: '排名', key: 'rank', width: 56, align: 'center', render: rankRender(medals) },
  { title: '单位', key: 'unit', ellipsis: { tooltip: true } },
  { title: '送检数量', key: 'count', width: 84, align: 'right' }
]
</script>

<template>
  <div class="dashboard">
    <div class="dash-main">
      <!-- 筛选栏 -->
      <div class="page-card dash-header">
        <n-tag type="info" :bordered="false">总计</n-tag>
        <n-select v-model:value="company" :options="companyOptions" filterable style="width: 280px" size="small" @update:value="refreshChart" />
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
    </div>

    <!-- 右侧栏：检验人员排名 + 送检单位排名 -->
    <div class="dash-side">
      <div class="page-card rank-card">
        <div class="card-title">检验人员排名</div>
        <n-data-table size="small" :columns="inspectorColumns" :data="inspectorRanking" :bordered="false" :bottom-bordered="false" />
      </div>

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

.dash-side {
  width: 320px;
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
