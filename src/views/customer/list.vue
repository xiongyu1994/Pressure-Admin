<script setup>
import { ref, reactive, h, onMounted } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSpace } from 'naive-ui'
import { Search as SearchIcon, Refresh as RefreshIcon } from '@vicons/ionicons5'
import request from '@/utils/request'
import { hasPerm } from '@/utils/permission'

const message = useMessage()

onMounted(() => load())

/* ---------------- 搜索 ---------------- */
const loading = ref(false)
const list = ref([])

const filter = reactive({ userName: '', clientTel: '', entrustTel: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filter.userName) params.userName = filter.userName
    if (filter.clientTel) params.clientTel = filter.clientTel
    if (filter.entrustTel) params.entrustTel = filter.entrustTel
    const data = await request.get('/unit/list', { limit: 500, ...params })
    list.value = data.list || data || []
  } catch (e) {
    message.error(e.message || '加载客户列表失败')
  } finally {
    loading.value = false
  }
}

function search() {
  load()
}

function reset() {
  filter.userName = ''
  filter.clientTel = ''
  filter.entrustTel = ''
  load()
}

/* ---------------- 表格 ---------------- */
const fmt = (v) => (v === null || v === undefined || v === '' ? '/' : v)

const columns = [
  {
    title: '操作',
    key: 'ops',
    width: 110,
    fixed: 'left',
    render(row) {
      if (!hasPerm('customer:resetPwd')) return null
      return h(NSpace, { size: 4 }, {
        default: () => [
          h(NButton, { size: 'tiny', type: 'primary', secondary: true, onClick: () => resetPwd(row) }, { default: () => '重置密码' })
        ]
      })
    }
  },
  { title: '姓名', key: 'userName', width: 100, render: (r) => fmt(r.userName) },
  { title: '手机号', key: 'mobile', width: 130, render: (r) => fmt(r.mobile) },
  { title: '单位名称', key: 'name', minWidth: 200, ellipsis: { tooltip: true } },
  { title: '身份', key: 'identity', width: 100 },
  { title: '联系人', key: 'client', width: 100, render: (r) => fmt(r.client) },
  { title: '联系人电话', key: 'clientTel', width: 130, render: (r) => fmt(r.clientTel) },
  { title: '开户行', key: 'bankName', width: 140, ellipsis: { tooltip: true }, render: (r) => fmt(r.bankName) },
  { title: '申请单位', key: 'applyUnit', width: 140, ellipsis: { tooltip: true }, render: (r) => fmt(r.applyUnit) },
  { title: '地址', key: 'address', minWidth: 160, ellipsis: { tooltip: true }, render: (r) => fmt(r.address) },
  { title: '纳税人识别号', key: 'taxNo', width: 160, render: (r) => fmt(r.taxNo) },
  { title: '最后登录时间', key: 'lastLoginTime', width: 160, render: (r) => fmt(r.lastLoginTime) },
  { title: '最后登录IP', key: 'lastLoginIp', width: 140, render: (r) => fmt(r.lastLoginIp) }
]

/* ---------------- 重置密码 ---------------- */
async function resetPwd(row) {
  try {
    const data = await request.post('/unit/reset_password', { id: row.id })
    message.success(`客户「${row.name}」密码已重置为 ${data.password}`)
  } catch (e) {
    message.error(e.message || '重置密码失败')
  }
}
</script>

<template>
  <div class="page-card">
    <n-form inline size="small" style="margin-bottom: 12px" label-placement="left">
      <n-space :size="12" align="center">
        <n-input v-model:value="filter.userName" placeholder="请输入姓名" clearable style="width: 180px" @keyup.enter="search" />
        <n-input v-model:value="filter.clientTel" placeholder="请输入联系人手机号" clearable style="width: 180px" @keyup.enter="search" />
        <n-input v-model:value="filter.entrustTel" placeholder="请输入委托人手机号" clearable style="width: 180px" @keyup.enter="search" />
        <n-button type="primary" @click="search">
          <template #icon><n-icon><SearchIcon /></n-icon></template>
          查询
        </n-button>
        <n-button @click="reset">
          <template #icon><n-icon><RefreshIcon /></n-icon></template>
          重置
        </n-button>
      </n-space>
    </n-form>

    <div class="toolbar">
      <span class="total-tip">共 {{ list.length }} 家</span>
    </div>

    <n-data-table
      size="small"
      :columns="columns"
      :data="list"
      :loading="loading"
      :row-key="(row) => row.id"
      :scroll-x="1800"
      max-height="560"
      :pagination="{ pageSize: 20, showSizePicker: true, pageSizes: [20, 50, 100] }"
    />
  </div>
</template>

<style lang="scss" scoped>
.toolbar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  margin-bottom: 10px;

  .total-tip {
    font-size: 13px;
    color: #888;
  }
}
</style>
