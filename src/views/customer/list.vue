<script setup>
import { ref, reactive, computed, h, onMounted } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSpace, NPopconfirm } from 'naive-ui'
import { Search as SearchIcon, Refresh as RefreshIcon } from '@vicons/ionicons5'
import { useBusinessStore } from '@/store/business'
import request from '@/utils/request'

const message = useMessage()
const store = useBusinessStore()

onMounted(() => load())

/* ---------------- 搜索 ---------------- */
const loading = ref(false)
const list = ref([])

const filter = reactive({ client: '', clientTel: '', entrustTel: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filter.client) params.client = filter.client
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
  filter.client = ''
  filter.clientTel = ''
  filter.entrustTel = ''
  load()
}

/* ---------------- 表格 ---------------- */
const fmt = (v) => (v === null || v === undefined || v === '' ? '/' : v)

const columns = [
  { title: '#', key: 'idx', width: 55, render: (_, i) => i + 1 },
  { title: '客户名称', key: 'name', width: 220, ellipsis: { tooltip: true } },
  { title: '地址', key: 'address', minWidth: 200, ellipsis: { tooltip: true } },
  { title: '联系人', key: 'client', width: 110 },
  { title: '联系电话', key: 'clientTel', width: 130 },
  { title: '委托人', key: 'entrustClient', width: 110, render: (r) => fmt(r.entrustClient) },
  { title: '委托人电话', key: 'entrustTel', width: 130, render: (r) => fmt(r.entrustTel) },
  { title: '开户行', key: 'bankName', minWidth: 150, ellipsis: { tooltip: true }, render: (r) => fmt(r.bankName) },
  { title: '纳税人识别号', key: 'taxNo', width: 160, render: (r) => fmt(r.taxNo) },
  {
    title: '操作',
    key: 'ops',
    width: 130,
    render(row) {
      return h(NSpace, { size: 4 }, {
        default: () => [
          h(NButton, { size: 'tiny', type: 'primary', quaternary: true, onClick: () => openEdit(row) }, { default: () => '编辑' }),
          h(NPopconfirm, { onPositiveClick: () => removeRow(row) }, {
            trigger: () => h(NButton, { size: 'tiny', type: 'error', quaternary: true }, { default: () => '删除' }),
            default: () => `确定删除客户「${row.name}」吗？`
          })
        ]
      })
    }
  }
]

/* ---------------- 新增 / 编辑 / 删除 ---------------- */
const show = ref(false)
const editing = ref(null)
const saving = ref(false)
const formRef = ref(null)

const form = reactive({ id: null, name: '', address: '', client: '', clientTel: '', entrustClient: '', entrustTel: '', bankName: '', taxNo: '' })

const rules = {
  name: { required: true, message: '请输入客户名称', trigger: 'blur' }
}

function openAdd() {
  Object.assign(form, { id: null, name: '', address: '', client: '', clientTel: '', entrustClient: '', entrustTel: '', bankName: '', taxNo: '' })
  editing.value = null
  show.value = true
}

function openEdit(row) {
  Object.assign(form, {
    id: row.id,
    name: row.name || '',
    address: row.address || '',
    client: row.client || '',
    clientTel: row.clientTel || '',
    entrustClient: row.entrustClient || '',
    entrustTel: row.entrustTel || '',
    bankName: row.bankName || '',
    taxNo: row.taxNo || ''
  })
  editing.value = row
  show.value = true
}

async function handleSave() {
  try {
    await formRef.value?.validate()
  } catch {
    return
  }
  saving.value = true
  try {
    if (form.id) {
      await store.updateUnit({ ...form })
      message.success('保存成功')
    } else {
      await store.addUnit({ ...form })
      message.success('新增客户成功')
    }
    show.value = false
    load()
  } catch (e) {
    message.error(e.message || '保存失败')
  } finally {
    saving.value = false
  }
}

async function removeRow(row) {
  try {
    await store.deleteUnit(row.id)
    message.success('已删除')
    load()
  } catch (e) {
    message.error(e.message || '删除失败')
  }
}

const modalTitle = computed(() => (form.id ? '编辑客户' : '新增客户'))
</script>

<template>
  <div class="page-card">
    <n-form inline size="small" style="margin-bottom: 12px" label-placement="left">
      <n-space :size="12" align="center">
        <n-input v-model:value="filter.client" placeholder="请输入姓名" clearable style="width: 180px" @keyup.enter="search" />
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
      <n-button type="primary" size="small" v-perm="'customer:add'" @click="openAdd">新增客户</n-button>
      <span class="total-tip">共 {{ list.length }} 家</span>
    </div>

    <n-data-table
      size="small"
      :columns="columns"
      :data="list"
      :loading="loading"
      :row-key="(row) => row.id"
      :scroll-x="1100"
      max-height="560"
      :pagination="{ pageSize: 20, showSizePicker: true, pageSizes: [20, 50, 100] }"
    />

    <n-modal v-model:show="show" preset="card" :title="modalTitle" style="width: 620px">
      <n-form ref="formRef" :model="form" :rules="rules" label-placement="left" label-width="110" size="small">
        <n-grid :cols="2" :x-gap="12">
          <n-form-item-gi label="客户名称" path="name">
            <n-input v-model:value="form.name" placeholder="请输入单位名称" />
          </n-form-item-gi>
          <n-form-item-gi label="联系人">
            <n-input v-model:value="form.client" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="联系电话">
            <n-input v-model:value="form.clientTel" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="委托人">
            <n-input v-model:value="form.entrustClient" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="委托人电话">
            <n-input v-model:value="form.entrustTel" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="开户行">
            <n-input v-model:value="form.bankName" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="纳税人识别号">
            <n-input v-model:value="form.taxNo" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="地址">
            <n-input v-model:value="form.address" placeholder="请输入" />
          </n-form-item-gi>
        </n-grid>
      </n-form>
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="show = false">取消</n-button>
          <n-button type="primary" :loading="saving" @click="handleSave">保存</n-button>
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

  .total-tip {
    font-size: 13px;
    color: #888;
  }
}
</style>
