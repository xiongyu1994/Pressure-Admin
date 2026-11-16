<script setup>
import { ref, reactive, computed, h } from 'vue'
import { useRouter } from 'vue-router'
import { useMessage } from 'naive-ui'
import { NButton, NSpace, NTag, NPopconfirm } from 'naive-ui'
import { useBusinessStore, COMMISSION_STATUS } from '@/store/business'
import { printHtml } from '@/utils/print'
import CommissionDetail from '@/components/commission/CommissionDetail.vue'

const router = useRouter()
const message = useMessage()
const store = useBusinessStore()

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
  ...Object.entries(COMMISSION_STATUS).map(([k, v]) => ({ label: v.label, value: k }))
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
const columns = [
  {
    title: '操作',
    key: 'actions',
    fixed: 'left',
    width: 250,
    render(row) {
      const ops = [
        h(
          NButton,
          { size: 'tiny', type: 'primary', quaternary: true, onClick: () => editRow(row) },
          { default: () => '编辑' }
        ),
        h(
          NButton,
          { size: 'tiny', type: 'info', quaternary: true, onClick: () => viewRow(row) },
          { default: () => '查看' }
        )
      ]
      if (row.status === 'pending') {
        ops.push(
          h(
            NPopconfirm,
            { onPositiveClick: () => passRow(row) },
            {
              trigger: () =>
                h(NButton, { size: 'tiny', type: 'success', quaternary: true }, { default: () => '通过' }),
              default: () => `通过后将为委托单「${row.code}」生成检验工单，确定吗？`
            }
          ),
          h(
            NButton,
            { size: 'tiny', type: 'warning', quaternary: true, onClick: () => openReject(row) },
            { default: () => '退回' }
          ),
          h(
            NButton,
            { size: 'tiny', quaternary: true, onClick: () => precheckRow(row) },
            { default: () => (row.precheck ? '通过预检' : '通过预检') }
          ),
          h(
            NButton,
            { size: 'tiny', quaternary: true, onClick: () => payRow(row) },
            { default: () => (row.paid ? '已付费' : '付费') }
          )
        )
      }
      ops.push(
        h(
          NPopconfirm,
          { onPositiveClick: () => deleteRow(row) },
          {
            trigger: () =>
              h(NButton, { size: 'tiny', type: 'error', quaternary: true }, { default: () => '删除' }),
            default: () => `确定删除委托单「${row.code}」吗？`
          }
        ),
        h(
          NButton,
          { size: 'tiny', type: 'primary', quaternary: true, onClick: () => printCommission(row) },
          { default: () => '打印委托单' }
        )
      )
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
  { title: '委托单号', key: 'code', width: 110, sorter: 'default' },
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
    width: 110,
    render(row) {
      console.log("row...",row)
      if (row.completedNum.split('、').length > 0) {
         return row.completedNum  + '-' +  row.completedNum
      }else{
         return row.completedNum || '-'
      }
     
    }
  },
  {
    title: '报告完成状态',
    key: 'reportDone',
    width: 110,
    render(row) {
      return `${row.reportDone || 0} / ${row.valveCount || 0}`
    }
  },
  { title: '委托日期', key: 'createTime', width: 150, sorter: 'default' }
]

/* ---------------- 行操作 ---------------- */
const detailShow = ref(false)
const detailRow = ref(null)

const editShow = ref(false)
const editingId = ref(null)
const editForm = reactive({
  address: '',
  client: '',
  clientTel: '',
  applicant: '',
  custAddress: '',
  contact: '',
  contactTel: '',
  planReceiverTime: ''
})

function viewRow(row) {
  detailRow.value = row
  detailShow.value = true
}

function editRow(row) {
  editingId.value = row.id
  Object.assign(editForm, {
    address: row.address,
    client: row.client,
    clientTel: row.clientTel,
    applicant: row.applicant,
    custAddress: row.custAddress,
    contact: row.contact,
    contactTel: row.contactTel,
    planReceiverTime: row.planReceiverTime
  })
  editShow.value = true
}

async function saveEdit() {
  try {
    await store.updateCommission(editingId.value, { ...editForm })
    editShow.value = false
    message.success('修改成功')
  } catch (e) {
    message.error(e.message || '修改失败')
  }
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

async function precheckRow(row) {
  try {
    await store.setPrecheck(row.id)
    message.success(`委托单「${row.code}」已通过预检`)
  } catch (e) {
    message.error(e.message || '操作失败')
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
          <n-button size="small" type="primary" @click="router.push('/commission/add')">新增</n-button>
          <n-button size="small" @click="exportCommissions">批量导出委托单</n-button>
        </n-space>
      </n-form-item>
    </n-form>

    <n-data-table
      :columns="columns"
      :data="list"
      :scroll-x="1900"
      size="small"
      :pagination="{ pageSize: 15, showSizePicker: true, pageSizes: [15, 30, 60, 100] }"
    />

    <CommissionDetail v-model:show="detailShow" :row="detailRow" />

    <!-- 编辑 -->
    <n-modal v-model:show="editShow" preset="card" title="编辑委托单" style="width: 560px">
      <n-form label-placement="left" label-width="120" size="small">
        <n-form-item label="使用单位地址">
          <n-input v-model:value="editForm.address" />
        </n-form-item>
        <n-form-item label="使用单位联系人">
          <n-input v-model:value="editForm.client" />
        </n-form-item>
        <n-form-item label="使用单位电话">
          <n-input v-model:value="editForm.clientTel" />
        </n-form-item>
        <n-form-item label="委托单位">
          <n-input v-model:value="editForm.applicant" />
        </n-form-item>
        <n-form-item label="委托单位地址">
          <n-input v-model:value="editForm.custAddress" />
        </n-form-item>
        <n-form-item label="委托人">
          <n-input v-model:value="editForm.contact" />
        </n-form-item>
        <n-form-item label="委托人电话">
          <n-input v-model:value="editForm.contactTel" />
        </n-form-item>
        <n-form-item label="预约取阀时间">
          <n-input v-model:value="editForm.planReceiverTime" placeholder="如 2026-01-20 09:30" />
        </n-form-item>
      </n-form>
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="editShow = false">取消</n-button>
          <n-button type="primary" @click="saveEdit">保存</n-button>
        </div>
      </template>
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
  </div>
</template>

<style lang="scss" scoped>
.filter-bar {
  row-gap: 8px;
  margin-bottom: 10px;
}
</style>
