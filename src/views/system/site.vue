<script setup>
import { ref, reactive, h, onMounted } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSwitch, NSpace, NPopconfirm, NDescriptions, NDescriptionsItem } from 'naive-ui'
import { Search as SearchIcon, Refresh as RefreshIcon } from '@vicons/ionicons5'
import request from '@/utils/request'
import { hasPerm } from '@/utils/permission'

const message = useMessage()

/* ---------------- 状态 ---------------- */
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)

const filter = reactive({ name: '', manager: '', tel: '', status: null })

async function load() {
  loading.value = true
  try {
    const params = { page: page.value, limit: pageSize.value }
    if (filter.name) params.name = filter.name
    if (filter.manager) params.manager = filter.manager
    if (filter.tel) params.tel = filter.tel
    if (filter.status !== null) params.status = filter.status
    const data = await request.get('/system/site/list', params)
    list.value = data.list || []
    total.value = data.total || 0
  } catch (e) {
    message.error(e.message || '加载站点列表失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.value = 1
  load()
}

function reset() {
  filter.name = ''
  filter.manager = ''
  filter.tel = ''
  filter.status = null
  page.value = 1
  load()
}

/* ---------------- 表格 ---------------- */
const fmt = (v) => (v === null || v === undefined || v === '' ? '/' : v)

const columns = [
  {
    title: '操作',
    key: 'ops',
    width: 150,
    render(row) {
      const btns = [
        h(NButton, { size: 'tiny', type: 'primary', secondary: true, onClick: () => openView(row) }, { default: () => '查看' })
      ]
      if (hasPerm('system:site:edit')) {
        btns.push(
          h(NButton, { size: 'tiny', type: 'info', quaternary: true, onClick: () => openEdit(row) }, { default: () => '编辑' })
        )
      }
      if (hasPerm('system:site:delete')) {
        btns.push(
          h(NPopconfirm, { onPositiveClick: () => removeRow(row) }, {
            trigger: () => h(NButton, { size: 'tiny', type: 'error', quaternary: true }, { default: () => '删除' }),
            default: () => `确定删除站点「${row.name}」吗？`
          })
        )
      }
      return h(NSpace, { size: 4 }, { default: () => btns })
    }
  },
  {
    title: '状态',
    key: 'status',
    width: 100,
    render(row) {
      return h(NSwitch, {
        value: row.status,
        size: 'medium',
        loading: row._statusLoading,
        disabled: !hasPerm('system:site:status'),
        onUpdateValue: (v) => toggleStatus(row, v)
      })
    }
  },
  { title: '上级站点', key: 'parentName', width: 140, render: (r) => fmt(r.parentName) },
  { title: '站点', key: 'name', width: 200, ellipsis: { tooltip: true } },
  { title: '负责人', key: 'manager', width: 110, render: (r) => fmt(r.manager) },
  { title: '联系电话', key: 'tel', width: 130, render: (r) => fmt(r.tel) },
  { title: '地址', key: 'address', minWidth: 240, ellipsis: { tooltip: true }, render: (r) => fmt(r.address) }
]

async function toggleStatus(row, v) {
  row._statusLoading = true
  try {
    await request.post('/system/site/status', { id: row.id, status: v ? 1 : 0 })
    row.status = v
    message.success(`站点「${row.name}」已${v ? '开启' : '关闭'}`)
  } catch (e) {
    message.error(e.message || '操作失败')
  } finally {
    row._statusLoading = false
  }
}

/* ---------------- 查看 / 新增 / 编辑 / 删除 ---------------- */
const showView = ref(false)
const viewRow = ref(null)

function openView(row) {
  viewRow.value = row
  showView.value = true
}

const showModal = ref(false)
const saving = ref(false)
const editingId = ref(null)
const form = reactive({ id: null, parentId: null, name: '', manager: '', tel: '', address: '', status: true })

const siteOptions = ref([])

async function loadParentOptions() {
  try {
    const data = await request.get('/system/site/list', { page: 1, limit: 200 })
    siteOptions.value = (data.list || [])
      .filter((s) => s.id !== editingId.value)
      .map((s) => ({ label: s.name, value: s.id }))
  } catch {}
}

function openAdd() {
  editingId.value = null
  Object.assign(form, { id: null, parentId: null, name: '', manager: '', tel: '', address: '', status: true })
  loadParentOptions()
  showModal.value = true
}

function openEdit(row) {
  editingId.value = row.id
  Object.assign(form, {
    id: row.id,
    parentId: row.parentId || null,
    name: row.name,
    manager: row.manager,
    tel: row.tel,
    address: row.address,
    status: row.status
  })
  loadParentOptions()
  showModal.value = true
}

async function save() {
  if (!form.name) {
    message.warning('请输入站点名称')
    return
  }
  saving.value = true
  try {
    const payload = {
      name: form.name,
      parentId: form.parentId || 0,
      manager: form.manager,
      tel: form.tel,
      address: form.address,
      status: form.status ? 1 : 0
    }
    if (form.id) {
      await request.post('/system/site/update', { id: form.id, ...payload })
      message.success('修改成功')
    } else {
      await request.post('/system/site/add', payload)
      message.success('新增站点成功')
    }
    showModal.value = false
    await load()
  } catch (e) {
    message.error(e.message || '保存失败')
  } finally {
    saving.value = false
  }
}

async function removeRow(row) {
  try {
    await request.post('/system/site/delete', { id: row.id })
    message.success('已删除')
    await load()
  } catch (e) {
    message.error(e.message || '删除失败')
  }
}

function handlePageChange(p) {
  page.value = p
  load()
}

function handlePageSizeChange(s) {
  pageSize.value = s
  page.value = 1
  load()
}

onMounted(load)
</script>

<template>
  <div class="page-card">
    <n-form inline size="small" label-placement="left" style="margin-bottom: 12px" :show-feedback="false">
      <n-space :size="16" align="center">
        <n-form-item label="站点名称">
          <n-input v-model:value="filter.name" placeholder="请输入" clearable style="width: 180px" @keyup.enter="search" />
        </n-form-item>
        <n-form-item label="负责人">
          <n-input v-model:value="filter.manager" placeholder="请输入" clearable style="width: 180px" @keyup.enter="search" />
        </n-form-item>
        <n-form-item label="联系电话">
          <n-input v-model:value="filter.tel" placeholder="请输入" clearable style="width: 180px" @keyup.enter="search" />
        </n-form-item>
        <n-form-item label="激活状态">
          <n-select
            v-model:value="filter.status"
            placeholder="请选择"
            clearable
            :options="[{ label: '开启', value: 1 }, { label: '关闭', value: 0 }]"
            style="width: 150px"
          />
        </n-form-item>
        <n-space :size="8">
          <n-button type="primary" size="small" @click="search">
            <template #icon><n-icon><SearchIcon /></n-icon></template>
            查询
          </n-button>
          <n-button size="small" @click="reset">
            <template #icon><n-icon><RefreshIcon /></n-icon></template>
            重置
          </n-button>
        </n-space>
      </n-space>
    </n-form>

    <div class="toolbar">
      <span></span>
      <n-space :size="8">
        <n-button size="small" quaternary title="刷新" @click="load"><template #icon><n-icon><RefreshIcon /></n-icon></template></n-button>
        <n-button size="small" type="primary" v-perm="'system:site:add'" @click="openAdd">新增站点</n-button>
      </n-space>
    </div>

    <n-data-table
      size="small"
      :columns="columns"
      :data="list"
      :loading="loading"
      :row-key="(r) => r.id"
      :scroll-x="1100"
      max-height="540"
      :pagination="{
        page,
        pageCount: Math.ceil(total / pageSize),
        itemCount: total,
        pageSize,
        showSizePicker: true,
        pageSizes: [15, 30, 50],
        onChange: handlePageChange,
        onUpdatePageSize: handlePageSizeChange,
        prefix: ({ itemCount }) => `共 ${itemCount} 条  ${pageSize} 条/页`
      }"
    />

    <!-- 查看 -->
    <n-modal v-model:show="showView" preset="card" title="站点详情" style="width: 560px">
      <n-descriptions v-if="viewRow" bordered :column="1" size="small" label-placement="left">
        <n-descriptions-item label="站点名称">{{ viewRow.name }}</n-descriptions-item>
        <n-descriptions-item label="上级站点">{{ viewRow.parentName || '无' }}</n-descriptions-item>
        <n-descriptions-item label="负责人">{{ viewRow.manager || '无' }}</n-descriptions-item>
        <n-descriptions-item label="联系电话">{{ viewRow.tel || '无' }}</n-descriptions-item>
        <n-descriptions-item label="地址">{{ viewRow.address || '无' }}</n-descriptions-item>
        <n-descriptions-item label="状态">{{ viewRow.status ? '开启' : '关闭' }}</n-descriptions-item>
        <n-descriptions-item label="创建时间">{{ viewRow.createTime || '无' }}</n-descriptions-item>
      </n-descriptions>
    </n-modal>

    <!-- 新增 / 编辑 -->
    <n-modal v-model:show="showModal" preset="card" :title="form.id ? '编辑站点' : '新增站点'" style="width: 560px">
      <n-form label-placement="left" label-width="90" size="small">
        <n-form-item label="站点名称" required>
          <n-input v-model:value="form.name" placeholder="请输入" />
        </n-form-item>
        <n-form-item label="上级站点">
          <n-select v-model:value="form.parentId" placeholder="请选择" clearable :options="siteOptions" />
        </n-form-item>
        <n-form-item label="负责人">
          <n-input v-model:value="form.manager" placeholder="请输入" />
        </n-form-item>
        <n-form-item label="联系电话">
          <n-input v-model:value="form.tel" placeholder="请输入" />
        </n-form-item>
        <n-form-item label="地址">
          <n-input v-model:value="form.address" type="textarea" :rows="2" placeholder="请输入" />
        </n-form-item>
        <n-form-item label="状态">
          <n-switch v-model:value="form.status" size="medium" />
        </n-form-item>
      </n-form>
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="showModal = false">取消</n-button>
          <n-button type="primary" :loading="saving" @click="save">保存</n-button>
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
}
</style>
