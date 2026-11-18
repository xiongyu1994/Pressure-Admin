<script setup>
import { ref, reactive, h, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useMessage } from 'naive-ui'
import { NButton, NSpace, NTag } from 'naive-ui'
import * as XLSX from 'xlsx'
import { useBusinessStore } from '@/store/business'
import request from '@/utils/request'
import ValveFormModal from '@/components/commission/ValveFormModal.vue'

/** 安全阀列表：搜索条件 / 列展示对齐参考系统 */
const router = useRouter()
const message = useMessage()
const store = useBusinessStore()

onMounted(() => load())

/* ---------------- 搜索 ---------------- */
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const checkedKeys = ref([])

const EQUIP_TYPES = ['安全阀', '爆破片装置']
const MEDIA = ['蒸汽', '压缩空气', '氮气', '水', '天然气']

const filter = reactive({
  manuNo: '',
  equipType: null,
  idNo: '',
  vavleModel: '',
  workingMedia: null,
  setPress: null,
  checkTimeRange: null,   // [起, 止] 时间戳
  nextCheckTimeRange: null,
  custName: ''
})

function fmtDate(ts) {
  if (!ts) return ''
  const d = new Date(ts)
  const p = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

function buildParams() {
  const params = { page: page.value, limit: pageSize.value }
  if (filter.manuNo) params.manuNo = filter.manuNo
  if (filter.equipType) params.equipType = filter.equipType
  if (filter.idNo) params.idNo = filter.idNo
  if (filter.vavleModel) params.vavleModel = filter.vavleModel
  if (filter.workingMedia) params.workingMedia = filter.workingMedia
  if (filter.setPress !== null && filter.setPress !== undefined && filter.setPress !== '') params.setPress = filter.setPress
  if (Array.isArray(filter.checkTimeRange) && filter.checkTimeRange.length === 2) {
    params.checkTimeStart = fmtDate(filter.checkTimeRange[0])
    params.checkTimeEnd = fmtDate(filter.checkTimeRange[1])
  }
  if (Array.isArray(filter.nextCheckTimeRange) && filter.nextCheckTimeRange.length === 2) {
    params.nextCheckTimeStart = fmtDate(filter.nextCheckTimeRange[0])
    params.nextCheckTimeEnd = fmtDate(filter.nextCheckTimeRange[1])
  }
  if (filter.custName) params.custName = filter.custName
  return params
}

async function load() {
  loading.value = true
  try {
    const data = await request.get('/valve/list', buildParams())
    list.value = data.list || []
    total.value = data.total || 0
    checkedKeys.value = []
  } catch (e) {
    message.error(e.message || '加载安全阀列表失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.value = 1
  load()
}

function reset() {
  Object.assign(filter, {
    manuNo: '',
    equipType: null,
    idNo: '',
    vavleModel: '',
    workingMedia: null,
    setPress: null,
    checkTimeRange: null,
    nextCheckTimeRange: null,
    custName: ''
  })
  page.value = 1
  load()
}

function onPageChange(p) {
  page.value = p
  load()
}

function onPageSizeChange(s) {
  pageSize.value = s
  page.value = 1
  load()
}

/* ---------------- 表格 ---------------- */
const fmt = (v) => (v === null || v === undefined || v === '' ? '' : v)

const columns = computed(() => [
  {
    title: '操作',
    key: 'ops',
    width: 110,
    render(row) {
      return h(NSpace, { size: 4 }, {
        default: () => [
          h(NButton, { size: 'tiny', type: 'primary', quaternary: true, onClick: () => openEdit(row) }, { default: () => '编辑' }),
          h(NButton, { size: 'tiny', type: 'info', quaternary: true, onClick: () => openView(row) }, { default: () => '查看' })
        ]
      })
    }
  },
  { type: 'selection' },
  {
    title: '状态',
    key: 'status',
    width: 70,
    render: (r) => h(NTag, { size: 'small', type: r.status === '正常' || !r.status ? 'success' : 'warning', bordered: false }, { default: () => r.status || '正常' })
  },
  { title: '检验时间', key: 'checkTime', width: 110, render: (r) => fmt(r.checkTime) },
  { title: '下次检验时间', key: 'nextCheckTime', width: 120, render: (r) => fmt(r.nextCheckTime) },
  { title: '唯一识别', key: 'uniqueCode', width: 200, ellipsis: { tooltip: true }, render: (r) => fmt(r.uniqueCode) },
  { title: '出厂编号', key: 'manuNo', width: 110, render: (r) => fmt(r.manuNo) },
  { title: '送检单位名称', key: 'custName', width: 200, ellipsis: { tooltip: true }, render: (r) => fmt(r.custName) },
  { title: '安装位置', key: 'tagNo', width: 130, ellipsis: { tooltip: true }, render: (r) => fmt(r.tagNo) },
  { title: '周期(年)', key: 'periodYears', width: 80, render: (r) => (r.periodYears ? `${r.periodYears}` : '1') }
])

/* ---------------- 新增 / 编辑 / 查看 ---------------- */
const formShow = ref(false)
const editing = ref(null)
const viewShow = ref(false)
const viewing = ref(null)

function openAdd() {
  router.push('/valve/add')
}

function openEdit(row) {
  editing.value = row
  formShow.value = true
}

function openView(row) {
  viewing.value = row
  viewShow.value = true
}

const viewItems = computed(() => {
  const v = viewing.value || {}
  return [
    { label: '状态', value: v.status || '正常' },
    { label: '唯一识别', value: v.uniqueCode },
    { label: '出厂编号', value: v.manuNo },
    { label: '送检单位名称', value: v.custName },
    { label: '安装位置', value: v.tagNo },
    { label: '设备代码', value: v.idNo },
    { label: '设备类型', value: v.equipType },
    { label: '安全阀型号', value: v.vavleModel },
    { label: '安全阀类型', value: v.vavleType },
    { label: '制造单位', value: v.manufacturer },
    { label: '制造许可证号', value: v.licenseNo },
    { label: '工作介质', value: v.workingMedia },
    { label: '工作压力MPa', value: v.workingPress },
    { label: '要求整定压力MPa', value: v.setPress },
    { label: '密封试验压力MPa', value: v.tightnessPress },
    { label: '公称通径mm', value: v.diameterNominal },
    { label: '公称压力MPa', value: v.pressNominal },
    { label: '流道直径mm', value: v.seatSize },
    { label: '出厂日期', value: v.manuDate },
    { label: '校验方式', value: v.parity },
    { label: '校验方法', value: v.checkMethod },
    { label: '检验时间', value: v.checkTime },
    { label: '下次检验时间', value: v.nextCheckTime },
    { label: '周期(年)', value: v.periodYears }
  ].filter((i) => i.value !== null && i.value !== undefined && i.value !== '')
})

/** 编辑模式保存 */
async function handleConfirm(payload) {
  if (!Array.isArray(payload)) {
    try {
      await store.updateValve({ id: editing.value.id, ...payload })
      message.success('保存成功')
      load()
    } catch (e) {
      message.error(e.message || '保存失败')
    }
  }
}

/* ---------------- 批量操作 ---------------- */
const checkedRows = () => list.value.filter((r) => checkedKeys.value.includes(r.id))

async function batchDelete() {
  const picked = checkedRows()
  if (!picked.length) {
    message.warning('请先勾选要删除的安全阀')
    return
  }
  try {
    const res = await request.post('/valve/delete', { ids: picked.map((r) => r.id) })
    message.success(`已删除 ${res?.count ?? picked.length} 台安全阀`)
    load()
  } catch (e) {
    message.error(e.message || '删除失败')
  }
}

function batchInspect() {
  const picked = checkedRows()
  if (!picked.length) {
    message.warning('请先勾选要送检的安全阀')
    return
  }
  // 暂存勾选阀门，跳转现场送检页预填清单（带 libId 复用库记录）
  store.batchInspectValves = picked
  router.push('/commission/add')
}

/* ---------------- 导出 / 模板 / 导入 ---------------- */
const IMPORT_HEADERS = ['出厂编号', '送检单位名称', '安装位置', '设备代码', '安全阀型号', '工作介质', '要求整定压力', '密封试验压力', '公称通径', '检验时间', '下次检验时间', '周期(年)', '状态']

function downloadCsvFile(filename, rows) {
  const csv = '\ufeff' + rows.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = filename
  a.click()
  URL.revokeObjectURL(a.href)
}

function downloadTemplate() {
  // 下载 public 目录下的 92 新导入模板（xlsx）
  const a = document.createElement('a')
  a.href = `${import.meta.env.BASE_URL || '/'}92安全阀导入模板.xlsx`
  a.download = '92安全阀导入模板.xlsx'
  document.body.appendChild(a)
  a.click()
  a.remove()
  message.info('模板说明：带 * 为必填列；校验方式选「在线校验」时「方法」列必填')
}

function exportExcel() {
  const rows = [
    IMPORT_HEADERS,
    ...list.value.map((r) => [
      fmt(r.manuNo), fmt(r.custName), fmt(r.tagNo), fmt(r.idNo), fmt(r.vavleModel), fmt(r.workingMedia),
      fmt(r.setPress), fmt(r.tightnessPress), fmt(r.diameterNominal), fmt(r.checkTime), fmt(r.nextCheckTime),
      r.periodYears || 1, r.status || '正常'
    ])
  ]
  downloadCsvFile(`安全阀列表_${new Date().toISOString().slice(0, 10)}.csv`, rows)
}

const fileInput = ref(null)

function triggerImport() {
  fileInput.value?.click()
}

async function onFileChange(e) {
  const file = e.target.files && e.target.files[0]
  e.target.value = ''
  if (!file) return
  try {
    // 支持 xlsx / xls / csv，统一用 SheetJS 解析成二维数组
    const buf = await file.arrayBuffer()
    const wb = XLSX.read(buf, { type: 'array', raw: false, defval: '' })
    const ws = wb.Sheets[wb.SheetNames[0]]
    const rows = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' })
    if (rows.length < 2) {
      message.warning('文件中没有数据行')
      return
    }
    // 表头归一化：去换行/空格/尾部*，与后端 $VALVE_IMPORT_HEADERS 的键对应
    const headers = rows[0].map((h) => String(h).replace(/[\s\n]/g, '').replace(/\*+$/, ''))
    const data = rows.slice(1).map((r) => {
      const obj = {}
      headers.forEach((h, i) => {
        if (h) obj[h] = r[i] ?? ''
      })
      return obj
    })
    const res = await request.post('/valve/import', { rows: data })
    message.success(`成功导入 ${res?.count ?? 0} 台安全阀${res?.errors?.length ? `，${res.errors.length} 行被跳过` : ''}`)
    if (res?.errors?.length) res.errors.forEach((m) => message.warning(m))
    load()
  } catch (err) {
    message.error(err.message || '导入失败')
  }
}
</script>

<template>
  <div class="page-card">
    <n-form inline size="small" label-placement="left" :label-width="82" class="search-form">
      <n-grid :cols="24" :x-gap="12" :y-gap="8">
        <n-form-item-gi :span="5" label="出厂编号">
          <n-input v-model:value="filter.manuNo" placeholder="请输入" clearable @keyup.enter="search" />
        </n-form-item-gi>
        <n-form-item-gi :span="5" label="设备类型">
          <n-select v-model:value="filter.equipType" placeholder="请选择" clearable :options="EQUIP_TYPES.map((v) => ({ label: v, value: v }))" />
        </n-form-item-gi>
        <n-form-item-gi :span="5" label="设备代码">
          <n-input v-model:value="filter.idNo" placeholder="请输入" clearable @keyup.enter="search" />
        </n-form-item-gi>
        <n-form-item-gi :span="5" label="安全阀型号">
          <n-input v-model:value="filter.vavleModel" placeholder="请输入" clearable @keyup.enter="search" />
        </n-form-item-gi>
        <n-form-item-gi :span="4" label="工作介质">
          <n-select v-model:value="filter.workingMedia" placeholder="请选择" clearable :options="MEDIA.map((v) => ({ label: v, value: v }))" />
        </n-form-item-gi>
        <n-form-item-gi :span="5" label="要求整定压力">
          <n-input-number v-model:value="filter.setPress" placeholder="请输入" style="width: 100%">
            <template #suffix>MPa</template>
          </n-input-number>
        </n-form-item-gi>
        <n-form-item-gi :span="7" label="检验日期">
          <n-date-picker v-model:value="filter.checkTimeRange" type="daterange" clearable style="width: 100%" />
        </n-form-item-gi>
        <n-form-item-gi :span="7" label="下次检验日期">
          <n-date-picker v-model:value="filter.nextCheckTimeRange" type="daterange" clearable style="width: 100%" />
        </n-form-item-gi>
        <n-form-item-gi :span="5" label="送检单位名称">
          <n-input v-model:value="filter.custName" placeholder="请输入" clearable @keyup.enter="search" />
        </n-form-item-gi>
        <n-gi :span="24" class="search-btns">
          <n-button type="primary" size="small" @click="search">查询</n-button>
          <n-button size="small" @click="reset">重置</n-button>
        </n-gi>
      </n-grid>
    </n-form>

    <div class="toolbar">
      <n-space>
        <n-button type="success" size="small" v-perm="'valve:add'" @click="openAdd">新增</n-button>
        <n-button type="info" size="small" ghost @click="downloadTemplate">下载92新导入模板</n-button>
        <n-button type="info" size="small" ghost v-perm="'valve:add'" @click="triggerImport">Excel导入</n-button>
        <n-button size="small" v-perm="'valve:list:export'" @click="exportExcel">导出</n-button>
        <n-button type="error" size="small" v-perm="'valve:list:delete'" @click="batchDelete">批量删除</n-button>
        <n-button type="warning" size="small" v-perm="'valve:add'" @click="batchInspect">批量送检</n-button>
        <span v-if="checkedKeys.length" class="picked-tip">已选中 {{ checkedKeys.length }} 条数据</span>
      </n-space>
      <span class="total-tip">共 {{ total }} 台</span>
    </div>

    <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" style="display: none" @change="onFileChange" />

    <n-data-table
      size="small"
      :columns="columns"
      :data="list"
      :loading="loading"
      :row-key="(r) => r.id"
      v-model:checked-row-keys="checkedKeys"
      :scroll-x="1340"
      max-height="560"
      :pagination="{ page, pageSize, itemCount: total, showSizePicker: true, pageSizes: [20, 50, 100], onChange: onPageChange, onUpdatePageSize: onPageSizeChange }"
    />

    <ValveFormModal v-model:show="formShow" :valve="editing" :submit-perm="editing ? 'valve:list:edit' : 'valve:add:save'" @confirm="handleConfirm" />

    <!-- 查看 -->
    <n-modal v-model:show="viewShow" preset="card" title="查看安全阀" style="width: 860px">
      <n-descriptions v-if="viewing" bordered :column="2" size="small" label-placement="left">
        <n-descriptions-item v-for="item in viewItems" :key="item.label" :label="item.label">
          {{ item.value }}
        </n-descriptions-item>
      </n-descriptions>
    </n-modal>
  </div>
</template>

<style lang="scss" scoped>
.search-form {
  padding-bottom: 4px;
  border-bottom: 1px dashed #e5e5e5;
  margin-bottom: 4px;
}

.search-btns {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 4px;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 10px 0;

  .picked-tip {
    font-size: 13px;
    color: $primary;
  }

  .total-tip {
    font-size: 13px;
    color: #888;
  }
}
</style>
