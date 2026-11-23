<script setup>
import { ref, reactive, computed, h, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'
import { NProgress, useMessage } from 'naive-ui'
import { NButton, NSpace, NTag, NPopconfirm } from 'naive-ui'
import QRCode from 'qrcode'
import { useBusinessStore, COMMISSION_STATUS, COMMISSION_STATUS_OPTIONS } from '@/store/business'
import request from '@/utils/request'
import { hasPerm } from '@/utils/permission'
import { printHtml } from '@/utils/print'
import CommissionDetail from '@/components/commission/CommissionDetail.vue'

const router = useRouter()
const message = useMessage()
const store = useBusinessStore()

/* 进入本页只查本页需要的接口：委托单列表 + 使用单位下拉 */
onMounted(() => {
  store.fetchCommissions().catch(() => {})
  store.fetchUnits().catch(() => {})
})

/* ---------------- 筛选 ---------------- */
const filters = reactive({
  code: '',
  custId: null,
  contact: '',
  status: null,
  dateRange: null
})

const statusOptions = [
  { label: '全部', value: null },
  ...COMMISSION_STATUS_OPTIONS
]

function resetSearch() {
  filters.code = ''
  filters.custId = null
  filters.contact = ''
  filters.status = null
  filters.dateRange = null
}

const list = computed(() =>
  store.commissions.filter((c) => {
    if (filters.code && !c.code.includes(filters.code)) return false
    if (filters.custId && c.custId !== filters.custId) return false
    if (filters.contact && !c.contact.includes(filters.contact)) return false
    if (filters.status && c.status !== filters.status) return false
    if (filters.dateRange && filters.dateRange.length === 2) {
      const day = c.createTime.slice(0, 10).replace(/-/g, '')
      const s = new Date(filters.dateRange[0]).toISOString().slice(0, 10).replace(/-/g, '')
      const e = new Date(filters.dateRange[1]).toISOString().slice(0, 10).replace(/-/g, '')
      if (day < s || day > e) return false
    }
    return true
  })
)

/* ---------------- 表格 ---------------- */
const baseColumns = [
  {
    title: '操作',
    key: 'actions',
    fixed: 'left',
    width: 250,
    render(row) {
      const ops = []
      if (hasPerm('commission:list:edit')) {
        ops.push(
          h(
            NButton,
            { size: 'tiny', type: 'primary', quaternary: true, onClick: () => editRow(row) },
            { default: () => '编辑' }
          )
        )
      }
      if (hasPerm('commission:list:view')) {
        ops.push(
          h(
            NButton,
            { size: 'tiny', type: 'info', quaternary: true, onClick: () => viewRow(row) },
            { default: () => '查看' }
          )
        )
      }
      // 等待签名/等待预检：未签名显示「签名」按钮，签名完成后显示「预检」按钮
      if (row.status === 'sign' || row.status === 'pending') {
        // if (hasPerm('commission:list:approve')) {
        //   ops.push(
        //     h(
        //       NPopconfirm,
        //       { onPositiveClick: () => passRow(row) },
        //       {
        //         trigger: () =>
        //           h(NButton, { size: 'tiny', type: 'success', quaternary: true }, { default: () => '通过' }),
        //         default: () => `通过后将为委托单「${row.code}」生成检验工单，确定吗？`
        //       }
        //     )
        //   )
        // }
        // if (hasPerm('commission:list:reject')) {
        //   ops.push(
        //     h(
        //       NButton,
        //       { size: 'tiny', type: 'warning', quaternary: true, onClick: () => openReject(row) },
        //       { default: () => '退回' }
        //     )
        //   )
        // }
        // 手机签名：等待预检且未签名时显示；签名完成后隐藏，出现「预检」按钮
        if (!row.signed && hasPerm('commission:list:sign')) {
          ops.push(
            h(
              NButton,
              { size: 'tiny', type: 'primary', quaternary: true, onClick: () => openSign(row) },
              { default: () => '签名' }
            )
          )
        }
        if (row.signed && hasPerm('commission:list:precheck')) {
          ops.push(
            h(
              NButton,
              { size: 'tiny', type: 'success', quaternary: true, onClick: () => openPrecheck(row) },
              { default: () => '预检' }
            )
          )
        }
        // if (hasPerm('commission:list:pay')) {
        //   ops.push(
        //     h(
        //       NButton,
        //       { size: 'tiny', quaternary: true, onClick: () => payRow(row) },
        //       { default: () => (row.paid ? '已付费' : '付费') }
        //     )
        //   )
        // }
      }
      if (hasPerm('commission:list:delete')) {
        ops.push(
          h(
            NPopconfirm,
            { onPositiveClick: () => deleteRow(row) },
            {
              trigger: () =>
                h(NButton, { size: 'tiny', type: 'error', quaternary: true }, { default: () => '删除' }),
              default: () => `确定删除委托单「${row.code}」吗？`
            }
          )
        )
      }
      if (hasPerm('commission:list:print')) {
        ops.push(
          h(
            NButton,
            { size: 'tiny', type: 'primary', quaternary: true, onClick: () => printCommission(row) },
            { default: () => '打印委托单' }
          )
        )
      }
      return h(NSpace, { size: 4 }, { default: () => ops })
    }
  },
  {
    title: '委托单状态',
    key: 'status',
    width: 100,
    render(row) {
      const s = COMMISSION_STATUS[row.status]
      return h(NTag, { type: s.type, size: 'small', bordered: false }, { default: () => s.label })
    }
  },
  {
    title: '预检',
    key: 'precheck',
    width: 70,
    render(row) {
      return row.precheck
        ? h(NTag, { type: 'success', size: 'small', bordered: false }, { default: () => '已预检' })
        : h(NTag, { size: 'small', bordered: false }, { default: () => '未预检' })
    }
  },
  { title: '委托单号', key: 'code', width: 132, sorter: 'default' },
  { title: '使用单位', key: 'custName', minWidth: 180, ellipsis: { tooltip: true } },
  { title: '使用单位地址', key: 'address', minWidth: 150, ellipsis: { tooltip: true } },
  { title: '单位联系人', key: 'client', width: 100 },
  { title: '单位电话', key: 'clientTel', width: 120 },
  { title: '委托人', key: 'contact', width: 90 },
  { title: '委托人电话', key: 'contactTel', width: 120 },
  { title: '检验单位', key: 'examName', width: 150, ellipsis: { tooltip: true } },
  {
    title: '校验编号',
    key: 'completedNum',
    width: 210,
    render(row) {
      return row.completedNum || '-'
    }
  },
  {
    title: '报告完成状态',
    key: 'reportDone',
    width: 110,
    render(row) {
      // 进度 = 已完成数量 / 当前阀门总数量；条上不显示百分比，下方展示 已完成/总数
      const done = row.reportDone || 0
      const total = row.valveCount || 0
      const pct = total ? Math.round((done / total) * 100) : 0
      return h('div', { class: 'progress-cell' }, [
        h(NProgress, {
          type: 'line',
          percentage: pct,
          showIndicator: false,
          color: '#26448f',
          railColor: '#e5e8ef'
        }),
        h('span', { class: 'progress-text' }, `${done}/${total}`)
      ])
    }
  },
  {
    title: '待检阀门',
    key: 'pendingValveCount',
    width: 100,
    align: 'center',
    render(row) {
      const n = row.pendingValveCount || 0
      return h(
        NTag,
        { size: 'small', bordered: false, type: n > 0 ? 'warning' : 'success' },
        { default: () => (n > 0 ? `${n} 台` : '无') }
      )
    }
  },
  // 固定在右侧：列较多需横向滚动时，委托日期始终完整可见
  { title: '委托日期', key: 'createTime', width: 150, fixed: 'right', sorter: 'default' }
]

/* ---------------- 列宽拖拽伸缩 ---------------- */
/** 各列当前宽度（拖动表头右边缘实时调整） */
const colWidths = reactive({
  actions: 250,
  status: 100,
  precheck: 70,
  code: 132,
  custName: 180,
  address: 150,
  client: 100,
  clientTel: 120,
  contact: 90,
  contactTel: 120,
  examName: 150,
  completedNum: 210,
  reportDone: 110,
  pendingValveCount: 100,
  createTime: 150
})

/** 表格总宽 = 各列宽之和，拖宽某列时表格同步加宽 */
const tableScrollX = computed(() => Object.values(colWidths).reduce((a, b) => a + b, 0))

/** 鼠标按下拖拽条：按横向位移实时改列宽（最小 60px） */
function startResize(e, key) {
  e.preventDefault()
  e.stopPropagation()
  const th = e.target.closest('th')
  const startX = e.clientX
  const startW = th ? th.offsetWidth : colWidths[key] || 100
  const move = (ev) => {
    colWidths[key] = Math.max(60, Math.round(startW + ev.clientX - startX))
  }
  const up = () => {
    document.removeEventListener('mousemove', move)
    document.removeEventListener('mouseup', up)
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
  }
  document.addEventListener('mousemove', move)
  document.addEventListener('mouseup', up)
  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
}

/** 包一列表头：标题 + 右边缘拖拽条 */
function withResizer(col) {
  return {
    ...col,
    minWidth: undefined,
    width: colWidths[col.key],
    title: () =>
      h('div', { class: 'th-resize-wrap' }, [
        h('span', { class: 'th-resize-title' }, col.title),
        h('span', { class: 'th-resizer', onMousedown: (e) => startResize(e, col.key) })
      ])
  }
}

const columns = computed(() => baseColumns.map(withResizer))

/* ---------------- 行操作 ---------------- */
const detailShow = ref(false)
const detailRow = ref(null)
const detailMode = ref('view')

function viewRow(row) {
  detailRow.value = row
  detailMode.value = 'view'
  detailShow.value = true
}

function editRow(row) {
  detailRow.value = row
  detailMode.value = 'edit'
  detailShow.value = true
}

async function passRow(row) {
  try {
    await store.approveCommission(row.id)
    message.success(`委托单「${row.code}」审核通过，已生成检验工单`)
  } catch (e) {
    message.error(e.message || '审核失败')
  }
}

async function deleteRow(row) {
  try {
    await store.removeCommission(row.id)
    message.success('删除成功')
  } catch (e) {
    message.error(e.message || '删除失败')
  }
}

/* ---------------- 手机扫码签名 ---------------- */
const signShow = ref(false)
const signRow = ref(null)
const signUrl = ref('')
const qrData = ref('')
const signLoading = ref(false)
let signTimer = null

async function openSign(row) {
  signRow.value = row
  signUrl.value = ''
  qrData.value = ''
  signShow.value = true
  signLoading.value = true
  try {
    const data = await store.signQrcode(row.id)
    signUrl.value = data.url
    await refreshQr()
    // 手机完成签名后自动检测并关闭弹窗
    stopSignPoll()
    signTimer = setInterval(() => checkSign(false), 3000)
  } catch (e) {
    message.error(e.message || '获取签名二维码失败')
    signShow.value = false
  } finally {
    signLoading.value = false
  }
}

/** 二维码内容可手改（如把 localhost 换成电脑局域网 IP），改完重新生成 */
async function refreshQr() {
  if (!signUrl.value) return
  try {
    qrData.value = await QRCode.toDataURL(signUrl.value, { width: 260, margin: 1 })
  } catch (e) {
    qrData.value = ''
  }
}

async function checkSign(manual) {
  if (!signRow.value) return
  try {
    const data = await store.signStatus(signRow.value.id)
    if (data.signed) {
      stopSignPoll()
      message.success(`委托单「${signRow.value.code}」已完成手机签名，可进行预检`)
      signShow.value = false
      store.fetchCommissions().catch(() => {})
    } else if (manual) {
      message.info('尚未收到手机签名，请在手机上完成签名提交')
    }
  } catch (e) {
    if (manual) message.error(e.message || '查询签名状态失败')
  }
}

function stopSignPoll() {
  if (signTimer) {
    clearInterval(signTimer)
    signTimer = null
  }
}
watch(signShow, (v) => {
  if (!v) stopSignPoll()
})
onBeforeUnmount(stopSignPoll)

/* ---------------- 预检（录入开抽流水号） ---------------- */
const precheckShow = ref(false)
const precheckRowData = ref(null)
const serialNo = ref('')
const precheckLoading = ref(false)

function openPrecheck(row) {
  precheckRowData.value = row
  serialNo.value = ''
  precheckShow.value = true
  // 默认填充：本月已用最大流水号 + 1
  store
    .fetchNextSerial()
    .then((next) => {
      if (next && precheckShow.value) serialNo.value = String(next)
    })
    .catch(() => {})
}

async function confirmPrecheck() {
  const row = precheckRowData.value
  const serial = serialNo.value.trim()
  if (!serial) {
    message.warning('请输入开抽流水号')
    return
  }
  precheckLoading.value = true
  try {
    const res = await store.precheckCommission(row.id, serial)
    precheckShow.value = false
    const start = res?.checkCodeStart || ''
    const end = res?.checkCodeEnd || ''
    const codeText = start && start !== end ? `${start} - ${end}` : start || ''
    message.success(`委托单「${row.code}」预检通过，已进入待检验${codeText ? `，校验编号 ${codeText}` : ''}`)
  } catch (e) {
    // 流水号重复 / 未完成签名等后端校验失败在此提示
    message.error(e.message || '提交失败')
  } finally {
    precheckLoading.value = false
  }
}

async function payRow(row) {
  try {
    await store.setPaid(row.id)
    message.success(`委托单「${row.code}」已登记付费`)
  } catch (e) {
    message.error(e.message || '操作失败')
  }
}

async function printCommission(row) {
  try {
    const detail = await store.fetchCommissionDetail(row.id)
    const valveRows = (detail.valves || [])
      .map(
        (v, i) =>
          `<tr><td>${i + 1}</td><td>${v.vavleModel}</td><td>${v.diameterNominal}</td><td>${v.manuNo}</td><td>${v.workingPress}</td><td>${v.setPress}</td><td>${v.tagNo || '-'}</td><td>${v.workingMedia}</td></tr>`
      )
      .join('')
    printHtml(
      '委托单',
      `<h1>安全阀校验委托单</h1>
       <div class="meta">委托单号：${row.code}</div>
       <table>
         <tr><th>使用单位</th><td>${row.custName}</td><th>使用单位地址</th><td>${row.address}</td></tr>
         <tr><th>联系人</th><td>${row.client} / ${row.clientTel}</td><th>委托人</th><td>${row.contact} / ${row.contactTel}</td></tr>
         <tr><th>检验单位</th><td>${row.examName}</td><th>预约取阀时间</th><td>${row.planReceiverTime || '-'}</td></tr>
         <tr><th>送阀人</th><td>${row.deliverer || '-'}</td><th>送阀时间</th><td>${row.deliveryTime || '-'}</td></tr>
       </table>
       <h2>安全阀清单</h2>
       <table>
         <tr><th>#</th><th>安全阀型号</th><th>公称通径mm</th><th>出厂编号</th><th>工作压力MPa</th><th>要求整定压力MPa</th><th>安装位置</th><th>工作介质</th></tr>
         ${valveRows}
       </table>
       <div class="stamp"><span>委托单位（章）：</span><span>日期：${(row.createTime || '').slice(0, 10)}</span></div>`
    )
  } catch (e) {
    message.error(e.message || '获取委托单详情失败')
  }
}

/* ---------------- 退回弹窗 ---------------- */
const rejectShow = ref(false)
const rejectRow = ref(null)
const rejectReason = ref('')

function openReject(row) {
  rejectRow.value = row
  rejectReason.value = ''
  rejectShow.value = true
}

async function confirmReject() {
  if (!rejectReason.value.trim()) {
    message.warning('请填写退回原因')
    return
  }
  try {
    await store.rejectCommission(rejectRow.value.id, rejectReason.value.trim())
    rejectShow.value = false
    message.success('已退回')
  } catch (e) {
    message.error(e.message || '退回失败')
  }
}

/* ---------------- 批量导出 ---------------- */
function exportCommissions() {
  const rows = list.value
  const content = rows
    .map((c) =>
      [
        c.code,
        c.custName,
        c.address,
        c.client,
        c.clientTel,
        c.contact,
        c.contactTel,
        c.examName,
        COMMISSION_STATUS[c.status].label,
        c.createTime
      ].join(',')
    )
    .join('\n')
  const header = '委托单号,使用单位,使用单位地址,单位联系人,单位电话,委托人,委托人电话,检验单位,状态,委托日期\n'
  const blob = new Blob(['\ufeff' + header + content], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `委托单列表-${Date.now()}.csv`
  a.click()
  URL.revokeObjectURL(url)
  message.success(`已导出 ${rows.length} 条委托单`)
}

const unitOptions = computed(() => store.units.map((u) => ({ label: u.name, value: u.id })))

/* ---------------- 待检阀门（所有委托单位下未完成检测的阀门） ---------------- */
const pendingShow = ref(false)
const pendingLoading = ref(false)
const pendingList = ref([])
const pendingSummary = ref([])
const pendingTotal = ref(0)
const pendingFilter = reactive({ custId: null, scope: 'undone', keyword: '' })

const pendingScopeOptions = [
  { label: '未完成检测（默认）', value: 'undone' },
  { label: '待审核 + 待检验', value: 'waiting' },
  { label: '全部阀门', value: 'all' }
]

async function openPending() {
  pendingShow.value = true
  await loadPending()
}

async function loadPending() {
  pendingLoading.value = true
  try {
    // 数据量小，一次取全量后在前端做单位/关键字过滤
    const data = await request.get('/commission/pending_valves', {
      limit: 2000,
      scope: pendingFilter.scope
    })
    pendingList.value = data.list || []
    pendingSummary.value = data.summary || []
    pendingTotal.value = data.totalPending || 0
  } catch (e) {
    message.error(e.message || '加载待检阀门失败')
  } finally {
    pendingLoading.value = false
  }
}

const pendingFiltered = computed(() => {
  const kw = (pendingFilter.keyword || '').trim()
  return pendingList.value.filter((v) => {
    if (pendingFilter.custId && v.custId !== pendingFilter.custId) return false
    if (kw) {
      const text = `${v.custName || ''}${v.commissionCode || ''}${v.vavleModel || ''}${v.manuNo || ''}${v.orderCode || ''}`
      if (!text.includes(kw)) return false
    }
    return true
  })
})

function valveStatusType(s) {
  const map = {
    pending: 'warning',
    wait: 'warning',
    inspection: 'info',
    rejected: 'error',
    print: 'default',
    done: 'success'
  }
  return map[s] || 'default'
}

const pendingColumns = [
  {
    title: '使用单位',
    key: 'custName',
    minWidth: 200,
    ellipsis: { tooltip: true }
  },
  { title: '委托单号', key: 'commissionCode', width: 110 },
  { title: '安全阀型号', key: 'vavleModel', width: 150, ellipsis: { tooltip: true } },
  { title: '公称通径mm', key: 'diameterNominal', width: 100, align: 'center' },
  { title: '出厂编号', key: 'manuNo', width: 110 },
  { title: '工作介质', key: 'workingMedia', width: 100 },
  { title: '要求整定压力MPa', key: 'setPress', width: 130, align: 'center' },
  {
    title: '检验编号',
    key: 'orderCode',
    width: 110,
    render: (row) => row.orderCode || '-'
  },
  {
    title: '阀门状态',
    key: 'valveStatusText',
    width: 100,
    align: 'center',
    render(row) {
      return h(
        NTag,
        { size: 'small', bordered: false, type: valveStatusType(row.valveStatus) },
        { default: () => row.valveStatusText }
      )
    }
  },
  { title: '委托日期', key: 'commissionTime', width: 150 }
]
</script>

<template>
  <div class="page-card">
    <n-form inline :show-feedback="false" label-placement="left" class="filter-bar">
      <n-form-item label="申请单号">
        <n-input v-model:value="filters.code" placeholder="请输入委托单号" clearable style="width: 150px" />
      </n-form-item>
      <n-form-item label="使用单位">
        <n-select v-model:value="filters.custId" :options="unitOptions" filterable clearable placeholder="全部" style="width: 200px" />
      </n-form-item>
      <n-form-item label="委托人">
        <n-input v-model:value="filters.contact" placeholder="请输入委托人" clearable style="width: 130px" />
      </n-form-item>
      <n-form-item label="状态">
        <n-select v-model:value="filters.status" :options="statusOptions" style="width: 110px" />
      </n-form-item>
      <n-form-item label="委托日期">
        <n-date-picker v-model:value="filters.dateRange" type="daterange" clearable style="width: 240px" />
      </n-form-item>
      <n-form-item>
        <n-space>
          <n-button type="primary" size="small" @click="message.success('查询完成')">查询</n-button>
          <n-button size="small" @click="resetSearch">重置</n-button>
          <n-button size="small" type="primary" v-perm="'commission:list:add'" @click="router.push('/commission/add')">新增</n-button>
          <n-button size="small" type="warning" v-perm="'commission:list:pending'" @click="openPending">待检阀门</n-button>
          <n-button size="small" v-perm="'commission:list:export'" @click="exportCommissions">批量导出委托单</n-button>
        </n-space>
      </n-form-item>
    </n-form>

    <n-data-table
      :columns="columns"
      :data="list"
      :loading="store.loading.commissions"
      :scroll-x="tableScrollX"
      size="small"
      :pagination="{ pageSize: 15, showSizePicker: true, pageSizes: [15, 30, 60, 100] }"
    />

    <CommissionDetail v-model:show="detailShow" :row="detailRow" :mode="detailMode" />

    <!-- 待检阀门（所有委托单位） -->
    <n-modal
      v-model:show="pendingShow"
      preset="card"
      title="待检阀门汇总"
      style="width: 1180px"
      :bordered="false"
    >
      <div class="pending-bar">
        <div class="pending-total">
          所有委托单位待检阀门共 <b>{{ pendingTotal }}</b> 台
        </div>
        <n-space size="small" style="row-gap: 6px">
          <n-tag
            v-for="s in pendingSummary"
            :key="s.custId || s.unitName"
            size="small"
            :bordered="false"
            :type="s.pendingCount > 0 ? 'warning' : 'default'"
          >
            {{ s.unitName }}　{{ s.pendingCount }} / {{ s.totalCount }}
          </n-tag>
        </n-space>
      </div>

      <n-form inline :show-feedback="false" label-placement="left" class="filter-bar" style="margin-top: 12px">
        <n-form-item label="使用单位">
          <n-select
            v-model:value="pendingFilter.custId"
            :options="unitOptions"
            clearable
            filterable
            placeholder="全部"
            style="width: 210px"
          />
        </n-form-item>
        <n-form-item label="范围">
          <n-select
            v-model:value="pendingFilter.scope"
            :options="pendingScopeOptions"
            style="width: 180px"
            @update:value="loadPending"
          />
        </n-form-item>
        <n-form-item label="关键字">
          <n-input
            v-model:value="pendingFilter.keyword"
            placeholder="单位 / 单号 / 型号 / 出厂编号"
            clearable
            style="width: 230px"
          />
        </n-form-item>
        <n-form-item>
          <n-button size="small" @click="loadPending">刷新</n-button>
        </n-form-item>
      </n-form>

      <n-data-table
        :columns="pendingColumns"
        :data="pendingFiltered"
        :loading="pendingLoading"
        size="small"
        :scroll-x="1800"
        :row-key="(r) => r.valveId"
        :pagination="{ pageSize: 15, showSizePicker: true, pageSizes: [15, 30, 60, 100] }"
      />
    </n-modal>

    <!-- 退回 -->
    <n-modal v-model:show="rejectShow" preset="card" title="退回委托单" style="width: 440px">
      <n-input v-model:value="rejectReason" type="textarea" placeholder="请填写退回原因" :rows="3" />
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="rejectShow = false">取消</n-button>
          <n-button type="error" @click="confirmReject">确认退回</n-button>
        </div>
      </template>
    </n-modal>

    <!-- 手机扫码签名 -->
    <n-modal v-model:show="signShow" preset="card" title="手机扫码签名" style="width: 420px">
      <n-spin :show="signLoading">
        <div class="sign-body">
          <div class="sign-tip">
            委托单：<b>{{ signRow?.code }}</b>
            <span v-if="signRow?.custName">（{{ signRow.custName }}）</span>
          </div>
          <div class="qr-wrap">
            <img v-if="qrData" :src="qrData" alt="签名二维码" class="qr-img" />
            <n-empty v-else description="二维码生成中…" style="padding: 40px 0" />
          </div>
          <div class="sign-tip">二维码地址（手机无法访问时可把 localhost 改成电脑局域网 IP，改完自动重新生成）</div>
          <n-input v-model:value="signUrl" size="small" @update:value="refreshQr" />
          <div class="sign-tip sign-hint">
            请使用手机微信「扫一扫」扫描二维码，在手机上完成手写签名并提交；签名完成后本弹窗自动关闭，
            操作栏的「签名」按钮将变为「预检」。
          </div>
        </div>
      </n-spin>
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="signShow = false">关闭</n-button>
          <n-button type="primary" @click="checkSign(true)">刷新签名状态</n-button>
        </div>
      </template>
    </n-modal>

    <!-- 预检：录入开抽流水号 -->
    <n-modal v-model:show="precheckShow" preset="dialog" type="info" :show-icon="false"
      title="确认通过预检吗？请输入开抽流水号" style="width: 380px">
      <n-input v-model:value="serialNo" placeholder="请输入开抽流水号" @keyup.enter="confirmPrecheck" />
      <template #action>
        <n-space>
          <n-button @click="precheckShow = false">取消</n-button>
          <n-button type="primary" :loading="precheckLoading" @click="confirmPrecheck">确定</n-button>
        </n-space>
      </template>
    </n-modal>
  </div>
</template>

<style lang="scss" scoped>
.filter-bar {
  row-gap: 8px;
  margin-bottom: 10px;
}

.pending-bar {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  background: #f7f9fc;
  border-radius: 6px;

  .pending-total {
    font-size: 14px;
    color: #333;

    b {
      margin: 0 3px;
      font-size: 18px;
      color: #e8890c;
    }
  }
}

.sign-body {
  display: flex;
  flex-direction: column;
  gap: 10px;

  .qr-wrap {
    display: flex;
    justify-content: center;
    padding: 6px 0;

    .qr-img {
      width: 240px;
      height: 240px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
    }
  }

  .sign-tip {
    font-size: 12px;
    color: #888;
    line-height: 1.6;

    b {
      color: #333;
    }
  }

  .sign-hint {
    color: #b0b3ba;
  }
}

.progress-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-right: 8px;

  .progress-text {
    font-size: 12px;
    color: #666;
    line-height: 16px;
  }
}

/* 列宽拖拽：表头右边缘悬停出现蓝色竖条，按住左右拖动伸缩列宽 */
:deep(.n-data-table-th) {
  position: relative;
}

:deep(.th-resize-wrap) {
  display: flex;
  align-items: center;
  width: 100%;

  .th-resize-title {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .th-resizer {
    position: absolute;
    top: 0;
    bottom: 0;
    right: -2px;
    width: 8px;
    cursor: col-resize;
    z-index: 5;

    &::after {
      content: '';
      position: absolute;
      right: 3px;
      top: 20%;
      bottom: 20%;
      width: 2px;
      background: transparent;
      border-radius: 1px;
    }

    &:hover::after {
      background: #2b6de8;
    }
  }
}
</style>
