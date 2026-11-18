<script setup>
import { ref, reactive, computed, h, onMounted } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSpace, NTag, NInputNumber, NInput, NDatePicker, NIcon } from 'naive-ui'
import { Search as SearchIcon, Refresh as RefreshIcon, ChevronUpOutline, ChevronDownOutline } from '@vicons/ionicons5'
import request, { API_BASE } from '@/utils/request'
import { hasPerm } from '@/utils/permission'
import { COMMISSION_STATUS, COMMISSION_STATUS_OPTIONS } from '@/store/business'

const message = useMessage()

onMounted(() => load())

/* ---------------- 搜索 ---------------- */
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const sums = reactive({ totalPrice: 0, repairPrice: 0, payableAmount: 0, actualAmount: 0 })

const filter = reactive({
  code: '',
  custName: '',
  applicant: '',
  createBy: '',
  status: null,
  fufei: null,
  timeRange: null
})

async function load() {
  loading.value = true
  try {
    const params = { page: page.value, limit: pageSize.value }
    if (filter.code) params.code = filter.code
    if (filter.custName) params.custName = filter.custName
    if (filter.applicant) params.applicant = filter.applicant
    if (filter.createBy) params.createBy = filter.createBy
    if (filter.status) params.status = filter.status
    if (filter.fufei !== null) params.fufei = filter.fufei
    if (filter.timeRange?.length === 2) {
      params.timeStart = filter.timeRange[0]
      params.timeEnd = filter.timeRange[1]
    }
    const data = await request.get('/fee/list', params)
    list.value = data.list || []
    total.value = data.total || 0
    Object.assign(sums, data.sums || {})
  } catch (e) {
    message.error(e.message || '加载收费列表失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.value = 1
  load()
}

function resetFilter() {
  Object.assign(filter, { code: '', custName: '', applicant: '', createBy: '', status: null, fufei: null, timeRange: null })
  page.value = 1
  load()
}

const FUFEE_OPTIONS = [
  { label: '待收费', value: 0 },
  { label: '已收费', value: 1 }
]

/* ---------------- 表格 ---------------- */
const fmt = (v) => (v === null || v === undefined || v === '' ? '/' : v)
const money = (v) => `¥${Number(v || 0).toFixed(2)}`

const columns = [
  {
    title: '操作',
    key: 'ops',
    width: 130,
    fixed: 'left',
    render(row) {
      const btns = [
        h(NButton, { size: 'tiny', type: 'primary', secondary: true, onClick: () => openView(row) }, { default: () => '查看' })
      ]
      if (hasPerm('commission:charge:pay')) {
        btns.push(
          h(NButton, { size: 'tiny', type: 'warning', quaternary: true, onClick: () => openView(row) }, { default: () => (row.paid ? '改收费' : '收费') })
        )
      }
      return h(NSpace, { size: 4 }, { default: () => btns })
    }
  },
  {
    title: '委托单状态',
    key: 'status',
    width: 100,
    render(row) {
      const s = COMMISSION_STATUS[row.status]
      return s ? h(NTag, { size: 'small', bordered: false, type: s.type }, { default: () => s.label }) : row.status
    }
  },
  {
    title: '收费状态',
    key: 'paid',
    width: 90,
    render(row) {
      return h(
        NTag,
        { size: 'small', bordered: false, type: row.paid ? 'success' : 'warning' },
        { default: () => (row.paid ? '已收费' : '待收费') }
      )
    }
  },
  { title: '委托单号', key: 'code', width: 150 },
  { title: '使用单位', key: 'custName', width: 200, ellipsis: { tooltip: true } },
  { title: '使用单位地址', key: 'address', width: 200, ellipsis: { tooltip: true }, render: (r) => fmt(r.address) },
  { title: '单位联系人', key: 'client', width: 100, render: (r) => fmt(r.client) },
  { title: '委托单位', key: 'applicant', width: 180, ellipsis: { tooltip: true }, render: (r) => fmt(r.applicant) },
  { title: '委托单位地址', key: 'custAddress', width: 180, ellipsis: { tooltip: true }, render: (r) => fmt(r.custAddress) },
  { title: '委托单位联系人', key: 'contact', width: 120, render: (r) => fmt(r.contact) },
  { title: '委托单位电话', key: 'contactTel', width: 130, render: (r) => fmt(r.contactTel) },
  { title: '总校验费', key: 'totalPrice', width: 100, render: (r) => money(r.totalPrice) },
  { title: '总维修费', key: 'repairPrice', width: 100, render: (r) => money(r.repairPrice) },
  { title: '总计', key: 'sumPrice', width: 110, render: (r) => money(Number(r.totalPrice) + Number(r.repairPrice)) },
  {
    title: '报告完成状态',
    key: 'report',
    width: 110,
    render(row) {
      const pct = row.reportTotal ? Math.round((row.reportDone / row.reportTotal) * 100) : 0
      return h('div', { style: 'min-width:70px' }, [
        h('div', {
          style: 'height:6px;border-radius:3px;background:#e5e7eb;overflow:hidden'
        }, [
          h('div', { style: `height:100%;width:${pct}%;background:#18a058;border-radius:3px` })
        ]),
        h('div', { style: 'font-size:12px;color:#666;margin-top:2px' }, `${row.reportDone}/${row.reportTotal}`)
      ])
    }
  },
  { title: '应开金额', key: 'payableAmount', width: 110, render: (r) => money(r.payableAmount) },
  { title: '实开金额', key: 'actualAmount', width: 110, render: (r) => money(r.actualAmount) },
  { title: '收款方式', key: 'paymentMethod', width: 100, render: (r) => fmt(r.paymentMethod) },
  { title: '收款日期', key: 'paymentDate', width: 110, render: (r) => fmt(r.paymentDate) },
  { title: '开票单位', key: 'billingUnit', width: 160, ellipsis: { tooltip: true }, render: (r) => fmt(r.billingUnit) },
  { title: '发票号码', key: 'invoiceNumber', width: 140, render: (r) => fmt(r.invoiceNumber) },
  { title: '开票日期', key: 'invoiceDate', width: 110, render: (r) => fmt(r.invoiceDate) },
  { title: '销售', key: 'seller', width: 90, render: (r) => fmt(r.seller) },
  { title: '检验多开', key: 'overbilledInspection', width: 100, render: (r) => money(r.overbilledInspection) },
  { title: '销售多开', key: 'overbilledSeller', width: 100, render: (r) => money(r.overbilledSeller) },
  { title: '备注', key: 'feeNote', width: 160, ellipsis: { tooltip: true }, render: (r) => fmt(r.feeNote) }
]

/* ---------------- 查看 / 收费登记 ---------------- */
const showView = ref(false)
const viewLoading = ref(false)
const saving = ref(false)
const viewInfo = ref(null)
const valves = ref([])

const feeForm = reactive({
  id: null,
  code: '',
  totalPrice: 0,
  repairPrice: 0,
  sellerPrice: 0,
  payableAmount: 0,
  actualAmount: 0,
  paymentMethod: '',
  paymentDate: null,
  billingUnit: '',
  invoiceNumber: '',
  invoiceDate: null,
  seller: '',
  overbilledInspection: 0,
  overbilledSeller: 0,
  feeNote: '',
  paid: false,
  signImg: '',
  signTime: ''
})

function openView(row) {
  showView.value = true
  viewLoading.value = true
  viewInfo.value = null
  valves.value = []
  request
    .get('/fee/detail', { id: row.id })
    .then((data) => {
      viewInfo.value = data
      valves.value = data.valves || []
      Object.assign(feeForm, {
        id: data.id,
        code: data.code || '',
        totalPrice: data.totalPrice || 0,
        repairPrice: data.repairPrice || 0,
        sellerPrice: data.sellerPrice || 0,
        payableAmount: data.payableAmount || 0,
        actualAmount: data.actualAmount || 0,
        paymentMethod: data.paymentMethod || '',
        paymentDate: data.paymentDate || null,
        billingUnit: data.billingUnit || '',
        invoiceNumber: data.invoiceNumber || '',
        invoiceDate: data.invoiceDate || null,
        seller: data.seller || '',
        overbilledInspection: data.overbilledInspection || 0,
        overbilledSeller: data.overbilledSeller || 0,
        feeNote: data.feeNote || '',
        paid: data.paid,
        signImg: data.signImg || '',
        signTime: data.signTime || ''
      })
    })
    .catch((e) => {
      message.error(e.message || '加载收费详情失败')
      showView.value = false
    })
    .finally(() => {
      viewLoading.value = false
    })
}

async function saveFee(markPaid) {
  saving.value = true
  try {
    await request.post('/fee/save', {
      id: feeForm.id,
      totalPrice: feeForm.totalPrice,
      repairPrice: feeForm.repairPrice,
      sellerPrice: feeForm.sellerPrice,
      payableAmount: feeForm.payableAmount,
      actualAmount: feeForm.actualAmount,
      paymentMethod: feeForm.paymentMethod,
      paymentDate: feeForm.paymentDate,
      billingUnit: feeForm.billingUnit,
      invoiceNumber: feeForm.invoiceNumber,
      invoiceDate: feeForm.invoiceDate,
      seller: feeForm.seller,
      overbilledInspection: feeForm.overbilledInspection,
      overbilledSeller: feeForm.overbilledSeller,
      feeNote: feeForm.feeNote,
      markPaid
    })
    message.success(markPaid ? '收费登记成功，已标记为已收费' : '已保存收费信息（保持待收费）')
    showView.value = false
    await load()
  } catch (e) {
    message.error(e.message || '保存失败')
  } finally {
    saving.value = false
  }
}

async function resetFee() {
  try {
    await request.post('/fee/reset', { id: feeForm.id })
    message.success('已重置为待收费')
    showView.value = false
    await load()
  } catch (e) {
    message.error(e.message || '重置失败')
  }
}

const canPay = hasPerm('commission:charge:pay')

/** 基本信息折叠 */
const showInfo = ref(true)

/** 总计 = 总校验费 + 总维修费（与列表口径一致） */
const grandTotal = computed(() => Number(feeForm.totalPrice || 0) + Number(feeForm.repairPrice || 0))

/** 签名图片完整地址 */
const signImgUrl = computed(() => (feeForm.signImg ? API_BASE + '/' + feeForm.signImg : ''))

/** 阀门清单列（查看弹窗内，参考站样式） */
const valveColumns = [
  { title: '校验编号', key: 'woOrderCode', minWidth: 200, render: (r) => r.woOrderCode || '' },
  { title: '安全阀型号', key: 'vavle_model', width: 180, render: (r) => r.vavle_model || '' },
  {
    title: '校验状态',
    key: 'woStatus',
    width: 100,
    render: (r) => ({ wait: '待检验', inspection: '检验中', audit: '待审核', approval: '待审批', print: '待打印', done: '已完成', rejected: '已退回' }[r.woStatus] || '待检验')
  },
  { title: '要求整定压力MPa', key: 'set_press', width: 130, render: (r) => (r.set_press !== null && r.set_press !== undefined ? Number(r.set_press).toFixed(2) : '/') },
  { title: '公称通径(DN)', key: 'diameter_nominal', width: 110, render: (r) => (r.diameter_nominal ? r.diameter_nominal : '/') },
  { title: '公称压力', key: 'press_nominal', width: 100, render: (r) => (r.press_nominal ? r.press_nominal : '/') }
]

function handlePageChange(p) {
  page.value = p
  load()
}

function handlePageSizeChange(s) {
  pageSize.value = s
  page.value = 1
  load()
}
</script>

<template>
  <div class="page-card">
    <n-form inline size="small" label-placement="left" :show-feedback="false" style="margin-bottom: 12px">
      <n-space :size="12" align="center" wrap>
        <n-input v-model:value="filter.code" placeholder="申请单号" clearable style="width: 150px" @keyup.enter="search" />
        <n-input v-model:value="filter.custName" placeholder="请输入使用单位" clearable style="width: 170px" @keyup.enter="search" />
        <n-input v-model:value="filter.applicant" placeholder="委托单位" clearable style="width: 150px" @keyup.enter="search" />
        <n-input v-model:value="filter.createBy" placeholder="委托人" clearable style="width: 120px" @keyup.enter="search" />
        <n-select v-model:value="filter.status" placeholder="状态" clearable :options="COMMISSION_STATUS_OPTIONS" style="width: 130px" @update:value="search" />
        <n-select v-model:value="filter.fufei" placeholder="收费状态" clearable :options="FUFEE_OPTIONS" style="width: 120px" @update:value="search" />
        <n-date-picker v-model:value="filter.timeRange" type="daterange" clearable size="small" style="width: 240px" @update:value="search" />
        <n-space :size="8">
          <n-button type="primary" size="small" @click="search">
            <template #icon><n-icon><SearchIcon /></n-icon></template>
            查询
          </n-button>
          <n-button size="small" @click="resetFilter">
            <template #icon><n-icon><RefreshIcon /></n-icon></template>
            重置
          </n-button>
        </n-space>
      </n-space>
    </n-form>

    <div class="toolbar">
      <div class="sum-view">
        <span>总校验费合计：<b>¥{{ sums.totalPrice.toFixed(2) }}</b></span>
        <span>总维修费合计：<b>¥{{ sums.repairPrice.toFixed(2) }}</b></span>
        <span>应开金额合计：<b>¥{{ sums.payableAmount.toFixed(2) }}</b></span>
        <span>实开金额合计：<b>¥{{ sums.actualAmount.toFixed(2) }}</b></span>
        <span class="total-tip">共 {{ total }} 单</span>
      </div>
      <n-button size="small" quaternary title="刷新" @click="load">
        <template #icon><n-icon><RefreshIcon /></n-icon></template>
      </n-button>
    </div>

    <n-data-table
      size="small"
      :columns="columns"
      :data="list"
      :loading="loading"
      :row-key="(r) => r.id"
      :scroll-x="3350"
      max-height="520"
      :pagination="{
        page,
        itemCount: total,
        pageSize,
        showSizePicker: true,
        pageSizes: [20, 50, 100],
        onChange: handlePageChange,
        onUpdatePageSize: handlePageSizeChange,
        prefix: ({ itemCount }) => `${itemCount} 条`
      }"
    />

    <!-- 查看 / 收费登记（参考站样式：基本信息 + 合计条 + 阀门表 + 收费信息） -->
    <n-modal v-model:show="showView" preset="card" :title="feeForm.paid ? `查看 - ${feeForm.code}` : `收费查看 - ${feeForm.code}`" style="width: 900px">
      <n-spin :show="viewLoading">
        <!-- 基本信息 -->
        <div class="section-bar">
          <span class="bar-title">基本信息</span>
          <span class="bar-toggle" @click="showInfo = !showInfo">
            {{ showInfo ? '收起' : '展开' }}
            <n-icon size="14"><ChevronUpOutline v-if="showInfo" /><ChevronDownOutline v-else /></n-icon>
          </span>
        </div>
        <div v-show="showInfo" class="info-grid">
          <div class="info-item"><span class="info-label">使用单位</span><span class="info-value">{{ viewInfo?.custName }}</span></div>
          <div class="info-item"><span class="info-label">使用单位地址</span><span class="info-value">{{ fmt(viewInfo?.address) }}</span></div>
          <div class="info-item"><span class="info-label">使用单位联系人</span><span class="info-value">{{ fmt(viewInfo?.client) }}</span></div>
          <div class="info-item"><span class="info-label">使用单位联系电话</span><span class="info-value">{{ fmt(viewInfo?.clientTel) }}</span></div>
          <div class="info-item"><span class="info-label">委托单位</span><span class="info-value">{{ fmt(viewInfo?.applicant) }}</span></div>
          <div class="info-item"><span class="info-label">委托单位地址</span><span class="info-value">{{ fmt(viewInfo?.custAddress) }}</span></div>
          <div class="info-item"><span class="info-label">委托人</span><span class="info-value">{{ fmt(viewInfo?.createBy) }}</span></div>
          <div class="info-item"><span class="info-label">委托人联系电话</span><span class="info-value">{{ fmt(viewInfo?.contactTel) }}</span></div>
          <div class="info-item"><span class="info-label">委托单位联系人</span><span class="info-value">{{ fmt(viewInfo?.contact) }}</span></div>
          <div class="info-item">
            <span class="info-label">委托人签名</span>
            <span class="info-value">
              <img v-if="signImgUrl" :src="signImgUrl" class="sign-img" alt="委托人签名" />
              <span v-else>/</span>
            </span>
          </div>
          <div class="info-item"><span class="info-label">委托人签名时间</span><span class="info-value">{{ fmt(feeForm.signTime) }}</span></div>
        </div>

        <!-- 合计条 -->
        <div class="sum-bar">
          <div class="sum-item">
            <span class="sum-label">总计</span>
            <span class="sum-value c-blue">¥{{ grandTotal.toFixed(2) }}</span>
          </div>
          <div class="sum-item">
            <span class="sum-label">总校验费</span>
            <n-input-number v-if="canPay" v-model:value="feeForm.totalPrice" :min="0" :precision="2" size="small" class="sum-input" />
            <span v-else class="sum-value c-blue">¥{{ Number(feeForm.totalPrice || 0).toFixed(2) }}</span>
          </div>
          <div class="sum-item">
            <span class="sum-label">总销售</span>
            <n-input-number v-if="canPay" v-model:value="feeForm.sellerPrice" :min="0" :precision="2" size="small" class="sum-input" />
            <span v-else class="sum-value c-green">¥{{ Number(feeForm.sellerPrice || 0).toFixed(2) }}</span>
          </div>
          <div class="sum-item">
            <span class="sum-label">总维修费</span>
            <n-input-number v-if="canPay" v-model:value="feeForm.repairPrice" :min="0" :precision="2" size="small" class="sum-input" />
            <span v-else class="sum-value c-orange">¥{{ Number(feeForm.repairPrice || 0).toFixed(2) }}</span>
          </div>
        </div>

        <!-- 阀门清单 -->
        <n-data-table
          size="small"
          :data="valves"
          :row-key="(r) => r.id"
          :columns="valveColumns"
          :pagination="false"
          max-height="220"
          class="valve-table"
        />

        <!-- 收费信息 -->
        <div class="section-bar" style="margin-top: 16px">
          <span class="bar-title">收费信息</span>
          <n-tag size="small" :type="feeForm.paid ? 'success' : 'warning'" :bordered="false">{{ feeForm.paid ? '已收费' : '待收费' }}</n-tag>
        </div>
        <n-form label-placement="left" label-width="90" size="small" :disabled="!canPay">
          <n-grid :cols="3" :x-gap="10">
            <n-form-item-gi label="应开金额"><n-input-number v-model:value="feeForm.payableAmount" :min="0" :precision="2" style="width: 100%" /></n-form-item-gi>
            <n-form-item-gi label="实开金额"><n-input-number v-model:value="feeForm.actualAmount" :min="0" :precision="2" style="width: 100%" /></n-form-item-gi>
            <n-form-item-gi label="收款方式"><n-input v-model:value="feeForm.paymentMethod" placeholder="如：转账 / 现金" /></n-form-item-gi>
            <n-form-item-gi label="收款日期"><n-date-picker v-model:value="feeForm.paymentDate" type="date" clearable style="width: 100%" /></n-form-item-gi>
            <n-form-item-gi label="开票单位"><n-input v-model:value="feeForm.billingUnit" placeholder="请输入" /></n-form-item-gi>
            <n-form-item-gi label="发票号码"><n-input v-model:value="feeForm.invoiceNumber" placeholder="请输入" /></n-form-item-gi>
            <n-form-item-gi label="开票日期"><n-date-picker v-model:value="feeForm.invoiceDate" type="date" clearable style="width: 100%" /></n-form-item-gi>
            <n-form-item-gi label="销售"><n-input v-model:value="feeForm.seller" placeholder="请输入" /></n-form-item-gi>
            <n-form-item-gi label="检验多开"><n-input-number v-model:value="feeForm.overbilledInspection" :precision="2" style="width: 100%" /></n-form-item-gi>
            <n-form-item-gi label="销售多开"><n-input-number v-model:value="feeForm.overbilledSeller" :precision="2" style="width: 100%" /></n-form-item-gi>
            <n-gi span="2">
              <n-form-item label="备注"><n-input v-model:value="feeForm.feeNote" placeholder="请输入" /></n-form-item>
            </n-gi>
          </n-grid>
        </n-form>
      </n-spin>
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="showView = false">取消</n-button>
          <n-popconfirm v-if="canPay" @positive-click="resetFee">
            <template #trigger>
              <n-button type="error" secondary :disabled="!feeForm.paid">重置为待收费</n-button>
            </template>
            确定重置该委托单的收费状态吗？
          </n-popconfirm>
          <n-button v-if="canPay" :loading="saving" @click="saveFee(false)">保存（待收费）</n-button>
          <n-button v-if="canPay" type="primary" :loading="saving" @click="saveFee(true)">{{ feeForm.paid ? '保存收费信息' : '确认收费' }}</n-button>
        </div>
      </template>
    </n-modal>
  </div>
</template>

<style lang="scss" scoped>
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;

  .sum-view {
    display: flex;
    gap: 18px;
    font-size: 13px;
    color: #666;

    b {
      color: #d03050;
    }
  }

  .total-tip {
    color: #888;
  }
}

/* 分区标题：左侧蓝色竖条 */
.section-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 4px 0 12px;

  .bar-title {
    position: relative;
    padding-left: 10px;
    font-size: 15px;
    font-weight: 700;
    color: #333;

    &::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 4px;
      height: 16px;
      background: #2f6bb5;
      border-radius: 2px;
    }
  }

  .bar-toggle {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-size: 13px;
    color: #2f6bb5;
    cursor: pointer;
    user-select: none;

    &:hover {
      opacity: 0.8;
    }
  }
}

/* 基本信息两列布局，标签右对齐 */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  row-gap: 14px;
  padding: 6px 8px 14px;
  border: 1px solid #e8eaee;
  border-radius: 4px;
  margin-bottom: 14px;
}

.info-item {
  display: flex;
  align-items: center;
  min-width: 0;
}

.info-label {
  flex-shrink: 0;
  width: 130px;
  text-align: right;
  color: #666;
  font-size: 13px;
}

.info-value {
  margin-left: 12px;
  color: #333;
  font-size: 13px;
  word-break: break-all;
}

.sign-img {
  height: 44px;
  object-fit: contain;
}

/* 合计条 */
.sum-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f7f9fc;
  border: 1px solid #e8eaee;
  border-radius: 4px;
  padding: 10px 28px;
  margin-bottom: 14px;
  flex-wrap: wrap;
  gap: 8px;
}

.sum-item {
  display: flex;
  align-items: center;
  gap: 10px;

  .sum-label {
    font-size: 16px;
    font-weight: 700;
    color: #333;
  }

  .sum-value {
    font-size: 18px;
    font-weight: 700;
  }

  .sum-input {
    width: 120px;

    :deep(.n-input__input-el) {
      font-weight: 700;
    }
  }
}

.c-blue {
  color: #2f6bb5;
}

.c-green {
  color: #18a058;
}

.c-orange {
  color: #f0a020;
}

.valve-table {
  margin-bottom: 6px;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  margin: 4px 0 10px;
}

.valve-box {
  margin-bottom: 14px;
}
</style>
