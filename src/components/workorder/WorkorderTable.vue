<script setup>
import { ref, reactive, computed, h, onMounted } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSpace, NTag, NPopconfirm } from 'naive-ui'
import { useBusinessStore, WORKORDER_STATUS } from '@/store/business'
import { hasPerm } from '@/utils/permission'
import { buildReportHtml, buildRecordHtml, printHtml, downloadTxt } from '@/utils/print'
import SubmitResultModal from './SubmitResultModal.vue'
import BatchSubmitResultModal from './BatchSubmitResultModal.vue'
import WorkorderDetail from './WorkorderDetail.vue'

const props = defineProps({
  /** 当前页面状态：rejected / wait / inspection / audit / approval / print / done */
  status: { type: String, required: true },
  /** 是否显示退回原因列 */
  showRejectReason: { type: Boolean, default: false },
  /** 是否显示校验人/校验时间列 */
  showInspector: { type: Boolean, default: false },
  /** 是否显示批量打印工具栏 */
  batchPrint: { type: Boolean, default: false }
})

const message = useMessage()
const store = useBusinessStore()

/* 工单相关页面共用本组件：进入页面时只查工单列表这一个接口 */
onMounted(() => {
  store.fetchWorkorders().catch(() => {})
})

/* ---------------- 筛选 ---------------- */
const filters = reactive({
  code: '',
  orderCode: '',
  custName: '',
  manuNo: '',
  dateRange: null
})

function doSearch() {
  // 数据已在 computed 中按 filters 过滤，这里仅提示
  message.success('查询完成')
}

function resetSearch() {
  filters.code = ''
  filters.orderCode = ''
  filters.custName = ''
  filters.manuNo = ''
  filters.dateRange = null
}

const list = computed(() =>
  store.workorders.filter((w) => {
    if (w.status !== props.status) return false
    if (filters.code && !w.code.includes(filters.code)) return false
    if (filters.orderCode && !w.orderCode.includes(filters.orderCode)) return false
    if (filters.custName && !w.custName.includes(filters.custName)) return false
    if (filters.manuNo && !w.manuNo.includes(filters.manuNo)) return false
    if (filters.dateRange && filters.dateRange.length === 2) {
      const day = w.createTime.slice(0, 10).replace(/-/g, '')
      const s = new Date(filters.dateRange[0]).toISOString().slice(0, 10).replace(/-/g, '')
      const e = new Date(filters.dateRange[1]).toISOString().slice(0, 10).replace(/-/g, '')
      if (day < s || day > e) return false
    }
    return true
  })
)

/* ---------------- 列定义 ---------------- */
/** 可选项（默认隐藏，可从“可选项”中勾选显示） */
const extraCols = [
  { label: '设备类型', value: 'equipType' },
  { label: '密封试验压力MPa', value: 'tightnessPress' },
  { label: '安装位置', value: 'tagNo' },
  { label: '安全阀型号', value: 'vavleModel' },
  { label: '制造单位', value: 'manufacturer' },
  { label: '制造许可证号', value: 'licenseNo' },
  { label: '工作介质', value: 'workingMedia' },
  { label: '公称通径mm', value: 'diameterNominal' },
  { label: '流道直径mm', value: 'seatSize' },
  { label: '公称压力MPa', value: 'pressNominal' },
  { label: '工作压力MPa', value: 'workingPress' },
  { label: '安全阀类型', value: 'vavleType' },
  { label: '出厂日期', value: 'manuDate' },
  { label: '压力级别范围MPa', value: 'pressClassRange' },
  { label: '被保护设备种类', value: 'protectedEquipType' },
  { label: '使用单位联系人', value: 'client' },
  { label: '使用单位联系电话', value: 'clientTel' },
  { label: '设备代码', value: 'idNo' }
]
const visibleExtra = ref(['idNo', 'client', 'clientTel'])

const extraFieldMap = {
  equipType: { key: 'equipType', title: '设备类型' },
  tightnessPress: { key: 'tightnessPress', title: '密封试验压力MPa' },
  tagNo: { key: 'tagNo', title: '安装位置' },
  vavleModel: { key: 'vavleModel', title: '安全阀型号' },
  manufacturer: { key: 'manufacturer', title: '制造单位' },
  licenseNo: { key: 'licenseNo', title: '制造许可证号' },
  workingMedia: { key: 'workingMedia', title: '工作介质' },
  diameterNominal: { key: 'diameterNominal', title: '公称通径mm' },
  seatSize: { key: 'seatSize', title: '流道直径mm' },
  pressNominal: { key: 'pressNominal', title: '公称压力MPa' },
  workingPress: { key: 'workingPress', title: '工作压力MPa' },
  vavleType: { key: 'vavleType', title: '安全阀类型' },
  manuDate: { key: 'manuDate', title: '出厂日期' },
  pressClassRange: {
    title: '压力级别范围MPa',
    render(row) {
      return `${row.pressClassRangeMin ?? '-'} - ${row.pressClassRangeMax ?? '-'}`
    }
  },
  protectedEquipType: { key: 'protectedEquipType', title: '被保护设备种类' },
  client: { key: 'client', title: '使用单位联系人' },
  clientTel: { key: 'clientTel', title: '使用单位联系电话' },
  idNo: { key: 'idNo', title: '设备代码' }
}

/* ---------------- 行操作 ---------------- */
const checkedKeys = ref([])

const columns = computed(() => {
  const cols = [
    {
      title: '操作',
      key: 'actions',
      fixed: 'left',
      width: opWidth.value,
      render(row) {
        return h(NSpace, { size: 4 }, { default: () => renderRowOps(row) })
      }
    },
    { type: 'selection' },
    {
      title: '状态',
      key: 'status',
      fixed: 'left',
      width: 90,
      render(row) {
        const s = WORKORDER_STATUS[row.status]
        return h(NTag, { type: s.type, size: 'small', bordered: false }, { default: () => s.label })
      }
    }
  ]
  if (props.showRejectReason) {
    cols.push({ title: '退回原因', key: 'rejectReason', minWidth: 160, ellipsis: { tooltip: true } })
  }
  cols.push(
    { title: '检验编号', key: 'orderCode', width: 110, fixed: 'left', sorter: 'default' },
    { title: '要求整定压力MPa', key: 'setPress', width: 130, sorter: 'default' }
  )
  if (props.showInspector) {
    cols.push(
      { title: '校验人', key: 'inspector', width: 90 },
      { title: '校验时间', key: 'inspectorTime', width: 150 }
    )
  }
  cols.push(
    { title: '状态时间', key: 'updateTime', width: 150, sorter: 'default' },
    { title: '委托单号', key: 'code', width: 132, sorter: 'default' },
    { title: '出厂编号', key: 'manuNo', width: 100 }
  )
  visibleExtra.value.forEach((k) => {
    const def = extraFieldMap[k]
    if (def) cols.push({ key: k, title: def.title, minWidth: 110, render: def.render })
  })
  if (props.status === 'all') {
    cols.push({ title: '使用单位', key: 'custName', minWidth: 180, ellipsis: { tooltip: true } })
  } else {
    cols.push({ title: '使用单位', key: 'custName', minWidth: 180, ellipsis: { tooltip: true } })
  }
  return cols
})

const opWidth = computed(() =>
  props.status === 'audit' || props.status === 'approval'
    ? 170
    : props.status === 'print' || props.status === 'done'
      ? 200
      : 150
)

/** 审核 / 审批页：通过 / 退回权限 */
function gatePerms() {
  return props.status === 'audit'
    ? { pass: 'workorder:audit:pass', reject: 'workorder:audit:reject' }
    : { pass: 'workorder:approval:pass', reject: 'workorder:approval:reject' }
}
function gateStore() {
  return props.status === 'audit' ? 'auditWorkorders' : 'approveWorkorders'
}
const passLabel = computed(() => (props.status === 'approval' ? '审批通过' : '通过'))

/** 打印 / 导出权限：待打印页与已完成页分别授权 */
function printPerm() {
  return props.status === 'done' ? 'workorder:all:print' : 'workorder:print:print'
}
function exportPerm() {
  return props.status === 'done' ? 'workorder:all:export' : 'workorder:print:export'
}

function renderRowOps(row) {
  const ops = [
    h(
      NButton,
      { size: 'tiny', type: 'primary', quaternary: true, onClick: () => viewRow(row) },
      { default: () => '查看' }
    )
  ]
  if (props.status === 'wait' && hasPerm('workorder:wait:submit')) {
    ops.push(
      h(
        NPopconfirm,
        { onPositiveClick: () => handleSubmit(row) },
        {
          trigger: () =>
            h(NButton, { size: 'tiny', type: 'info', quaternary: true }, { default: () => '提交' }),
          default: () => `确认要提交「${row.orderCode}」进入检验中吗？`
        }
      )
    )
  }
  if (props.status === 'inspection' && hasPerm('workorder:inspection:result')) {
    ops.push(
      h(
        NButton,
        { size: 'tiny', type: 'info', quaternary: true, onClick: () => openSubmitResult(row) },
        { default: () => '提交结果' }
      )
    )
  }
  if ((props.status === 'audit' || props.status === 'approval') && hasPerm(gatePerms().pass)) {
    ops.push(
      h(
        NPopconfirm,
        { onPositiveClick: () => handleGate([row.id], true) },
        {
          trigger: () =>
            h(NButton, { size: 'tiny', type: 'success', quaternary: true }, { default: () => passLabel.value }),
          default: () => `确认${passLabel.value}「${row.orderCode}」吗？`
        }
      )
    )
  }
  if ((props.status === 'audit' || props.status === 'approval') && hasPerm(gatePerms().reject)) {
    ops.push(
      h(
        NButton,
        { size: 'tiny', type: 'error', quaternary: true, onClick: () => openReject([row.id]) },
        { default: () => '退回' }
      )
    )
  }
  if (props.status === 'rejected' && hasPerm('workorder:reject:resubmit')) {
    ops.push(
      h(
        NPopconfirm,
        { onPositiveClick: () => handleResubmit(row) },
        {
          trigger: () =>
            h(NButton, { size: 'tiny', type: 'warning', quaternary: true }, { default: () => '编辑' }),
          default: () => `确认修改后重新提交「${row.orderCode}」到待检验吗？`
        }
      )
    )
  }
  if ((props.status === 'print' || props.status === 'done') && hasPerm(printPerm())) {
    ops.push(
      h(
        NButton,
        { size: 'tiny', type: 'primary', quaternary: true, onClick: () => printReport(row) },
        { default: () => '校验报告' }
      ),
      h(
        NButton,
        { size: 'tiny', type: 'primary', quaternary: true, onClick: () => printRecord(row) },
        { default: () => '校验记录' }
      )
    )
  }
  return ops
}

/* ---------------- 弹窗 ---------------- */
const detailShow = ref(false)
const detailRow = ref(null)

function viewRow(row) {
  detailRow.value = row
  detailShow.value = true
}

const submitShow = ref(false)
const submitRow = ref(null)

function openSubmitResult(row) {
  submitRow.value = row
  submitShow.value = true
}

async function handleSubmit(row) {
  try {
    await store.submitWorkorder(row.id)
    message.success(`「${row.orderCode}」已提交进入检验中`)
  } catch (e) {
    message.error(e.message || '提交失败')
  }
}

async function handleResubmit(row) {
  try {
    await store.resubmitWorkorder(row.id)
    message.success(`「${row.orderCode}」已重新提交到待检验`)
  } catch (e) {
    message.error(e.message || '重新提交失败')
  }
}

/* ---------------- 审核 / 审批 ---------------- */
const gateLoading = ref(false)

async function handleGate(ids, pass, reason = '') {
  if (gateLoading.value) return
  gateLoading.value = true
  const codes = list.value
    .filter((w) => ids.includes(w.id))
    .map((r) => r.orderCode)
    .join('、')
  try {
    await store[gateStore()](ids, pass, reason)
    message.success(pass ? `已${passLabel.value}：${codes}` : `已退回：${codes}`)
    checkedKeys.value = checkedKeys.value.filter((k) => !ids.includes(k))
  } catch (e) {
    message.error(e.message || '操作失败')
  } finally {
    gateLoading.value = false
  }
}

/** 退回原因弹窗 */
const rejectShow = ref(false)
const rejectIds = ref([])
const rejectReason = ref('')

function openReject(ids) {
  rejectIds.value = ids
  rejectReason.value = ''
  rejectShow.value = true
}

async function confirmReject() {
  if (!rejectReason.value.trim()) {
    message.warning('请填写退回原因')
    return
  }
  await handleGate(rejectIds.value, false, rejectReason.value.trim())
  if (rejectIds.value.length) rejectShow.value = false
}

function batchGate(pass) {
  const ids = checkedKeys.value.filter((k) => list.value.some((w) => w.id === k))
  if (!ids.length) {
    message.warning('请先勾选工单')
    return
  }
  if (pass) {
    handleGate(ids, true)
  } else {
    openReject(ids)
  }
}

/* ---------------- 检验中批量提交结果 ---------------- */
const batchResultShow = ref(false)

function openBatchSubmitResult() {
  const rows = list.value.filter((w) => checkedKeys.value.includes(w.id))
  if (!rows.length) {
    message.warning('请先勾选工单')
    return
  }
  batchResultShow.value = true
}

/* ---------------- 待检验批量提交 ---------------- */
const batchSubmitLoading = ref(false)

async function batchSubmit() {
  const ids = checkedKeys.value.filter((k) => list.value.some((w) => w.id === k))
  if (!ids.length) {
    message.warning('请先勾选工单')
    return
  }
  if (batchSubmitLoading.value) return
  batchSubmitLoading.value = true
  const codes = list.value
    .filter((w) => ids.includes(w.id))
    .map((r) => r.orderCode)
    .join('、')
  try {
    await store.batchSubmitWorkorders(ids)
    message.success(`已批量提交：${codes}`)
    checkedKeys.value = []
  } catch (e) {
    message.error(e.message || '批量提交失败')
  } finally {
    batchSubmitLoading.value = false
  }
}

async function markPrintedSafe(ids) {
  try {
    await store.markPrinted(ids)
  } catch (e) {
    message.error(e.message || '打印状态更新失败')
  }
}

function printReport(row) {
  try {
    printHtml('安全阀校验报告', buildReportHtml(row))
    if (props.status === 'print') {
      markPrintedSafe(row.id)
    }
  } catch (e) {
    message.error(e.message)
  }
}

function printRecord(row) {
  try {
    printHtml('安全阀校验记录', buildRecordHtml(row))
    if (props.status === 'print') {
      markPrintedSafe(row.id)
    }
  } catch (e) {
    message.error(e.message)
  }
}

/* ---------------- 批量工具栏 ---------------- */
function batchPrintReport() {
  const rows = list.value.filter((w) => checkedKeys.value.includes(w.id))
  if (!rows.length) {
    message.warning('请先勾选需要打印的工单')
    return
  }
  const body = rows.map(buildReportHtml).join('<div style="page-break-after:always"></div>')
  try {
    printHtml('批量校验报告', body)
    if (props.status === 'print') markPrintedSafe(rows.map((r) => r.id))
  } catch (e) {
    message.error(e.message)
  }
}

function batchPrintRecord() {
  const rows = list.value.filter((w) => checkedKeys.value.includes(w.id))
  if (!rows.length) {
    message.warning('请先勾选需要打印的工单')
    return
  }
  const body = rows.map(buildRecordHtml).join('<div style="page-break-after:always"></div>')
  try {
    printHtml('批量校验记录', body)
    if (props.status === 'print') markPrintedSafe(rows.map((r) => r.id))
  } catch (e) {
    message.error(e.message)
  }
}

function printLedger() {
  const rows = list.value
  const trs = rows
    .map(
      (w) =>
        `<tr><td>${w.orderCode}</td><td>${w.custName}</td><td>${w.vavleModel}</td><td>${w.manuNo}</td><td>${w.setPress}</td><td>${w.result?.testStatus || '-'}</td><td>${w.inspector || '-'}</td><td>${(w.inspectorTime || '').slice(0, 10)}</td></tr>`
    )
    .join('')
  printHtml(
    '校验台账',
    `<h1>安全阀校验台账</h1><table><tr><th>检验编号</th><th>使用单位</th><th>安全阀型号</th><th>出厂编号</th><th>要求整定压力MPa</th><th>校验结果</th><th>校验员</th><th>校验日期</th></tr>${trs}</table>`
  )
}

function exportMark() {
  const rows = list.value.filter((w) => checkedKeys.value.includes(w.id))
  if (!rows.length) {
    message.warning('请先勾选需要导出的工单')
    return
  }
  const content = rows
    .map((w) => `${w.orderCode},${w.idNo || ''},${w.manuNo},${w.vavleModel},${w.setPress},${w.result?.testStatus || ''}`)
    .join('\n')
  downloadTxt(`打标记录-${Date.now()}.txt`, content)
  message.success(`已导出 ${rows.length} 条打标记录`)
}

function rowProps(row) {
  return { style: 'cursor:pointer' }
}
</script>

<template>
  <div class="page-card">
    <!-- 筛选区 -->
    <n-form inline :show-feedback="false" label-placement="left" class="filter-bar">
      <n-form-item label="委托单号">
        <n-input v-model:value="filters.code" placeholder="请输入委托单号" clearable style="width: 150px" />
      </n-form-item>
      <n-form-item label="检验编号">
        <n-input v-model:value="filters.orderCode" placeholder="请输入检验编号" clearable style="width: 150px" />
      </n-form-item>
      <n-form-item label="使用单位">
        <n-input v-model:value="filters.custName" placeholder="请输入使用单位" clearable style="width: 160px" />
      </n-form-item>
      <n-form-item label="出厂编号">
        <n-input v-model:value="filters.manuNo" placeholder="请输入出厂编号" clearable style="width: 140px" />
      </n-form-item>
      <n-form-item label="委托日期">
        <n-date-picker v-model:value="filters.dateRange" type="daterange" clearable style="width: 240px" />
      </n-form-item>
      <n-form-item>
        <n-space>
          <n-button type="primary" size="small" @click="doSearch">查询</n-button>
          <n-button size="small" @click="resetSearch">重置</n-button>
          <n-popselect v-model:value="visibleExtra" multiple :options="extraCols" trigger="click" scrollable>
            <n-button size="small">可选项</n-button>
          </n-popselect>
        </n-space>
      </n-form-item>
    </n-form>

    <!-- 检验中批量提交结果工具栏 -->
    <div v-if="status === 'inspection'" class="toolbar inline">
      <n-button
        size="small"
        type="success"
        ghost
        v-perm="'workorder:inspection:result'"
        @click="openBatchSubmitResult"
      >批量提交结果</n-button>
      <span class="selected-tip">已选中<span class="sel-count">{{ checkedKeys.length }}</span>条数据</span>
    </div>

    <!-- 待检验批量提交工具栏 -->
    <div v-if="status === 'wait'" class="toolbar inline">
      <n-button
        size="small"
        type="warning"
        :loading="batchSubmitLoading"
        v-perm="'workorder:wait:submit'"
        @click="batchSubmit"
      >批量提交</n-button>
      <span class="selected-tip">已选中<span class="sel-count">{{ checkedKeys.length }}</span>条数据</span>
    </div>

    <!-- 审核 / 审批批量工具栏 -->
    <div v-if="status === 'audit' || status === 'approval'" class="toolbar">
      <n-space>
        <n-button
          size="small"
          type="primary"
          :loading="gateLoading"
          v-perm="gatePerms().pass"
          @click="batchGate(true)"
        >批量{{ passLabel }}</n-button>
        <n-button size="small" type="error" secondary v-perm="gatePerms().reject" @click="batchGate(false)">批量退回</n-button>
      </n-space>
      <span class="selected-tip">已选 {{ checkedKeys.length }} 条</span>
    </div>

    <!-- 批量工具栏 -->
    <div v-if="batchPrint" class="toolbar">
      <n-space>
        <n-button size="small" type="primary" v-perm="printPerm()" @click="batchPrintReport">批量打印校验报告</n-button>
        <n-button size="small" type="primary" v-perm="printPerm()" @click="batchPrintRecord">批量打印校验记录</n-button>
        <n-button size="small" v-perm="printPerm()" @click="printLedger">打印台账</n-button>
        <n-button size="small" v-perm="exportPerm()" @click="exportMark">导出打标记录-txt</n-button>
      </n-space>
      <span class="selected-tip">已选 {{ checkedKeys.length }} 条</span>
    </div>

    <n-data-table
      v-model:checked-row-keys="checkedKeys"
      :columns="columns"
      :data="list"
      :loading="store.loading.workorders"
      :row-key="(r) => r.id"
      :row-props="rowProps"
      :scroll-x="1600"
      :pagination="{ pageSize: 20, showSizePicker: true, pageSizes: [15, 30, 60, 100] }"
      size="small"
    />

    <WorkorderDetail v-model:show="detailShow" :row="detailRow" />
    <SubmitResultModal v-model:show="submitShow" :row="submitRow" />
    <BatchSubmitResultModal v-model:show="batchResultShow" :rows="list.filter((w) => checkedKeys.includes(w.id))" />

    <!-- 退回原因 -->
    <n-modal v-model:show="rejectShow" preset="card" :title="status === 'approval' ? '审批退回' : '审核退回'" style="width: 460px">
      <n-input
        v-model:value="rejectReason"
        type="textarea"
        placeholder="请填写退回原因（必填）"
        :autosize="{ minRows: 3, maxRows: 6 }"
      />
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="rejectShow = false">取消</n-button>
          <n-button type="error" :loading="gateLoading" @click="confirmReject">确认退回</n-button>
        </div>
      </template>
    </n-modal>
  </div>
</template>

<style lang="scss" scoped>
.filter-bar {
  row-gap: 8px;
  margin-bottom: 6px;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;

  &.inline {
    justify-content: flex-start;
    gap: 12px;
  }

  .selected-tip {
    font-size: 13px;
    color: $text-secondary;

    .sel-count {
      color: #2b6de8;
      padding: 0 2px;
    }
  }
}
</style>
