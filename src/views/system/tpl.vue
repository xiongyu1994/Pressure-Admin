<script setup>
import { ref, reactive, h, onMounted } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSwitch, NSpace, NPopconfirm, NDescriptions, NDescriptionsItem } from 'naive-ui'
import { Search as SearchIcon, Refresh as RefreshIcon, Add as AddIcon } from '@vicons/ionicons5'
import request, { API_BASE } from '@/utils/request'
import { hasPerm } from '@/utils/permission'

const message = useMessage()

/* ---------------- 状态 ---------------- */
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(15)

const filter = reactive({ siteId: null, type: null })

const siteOptions = ref([])
const TPL_TYPES = ['校验报告', '校验记录', '台账', '委托单']

async function loadSiteOptions() {
  try {
    const data = await request.get('/system/site/list', { page: 1, limit: 200 })
    siteOptions.value = (data.list || []).map((s) => ({ label: s.name, value: s.id }))
  } catch {}
}

async function load() {
  loading.value = true
  try {
    const params = { page: page.value, limit: pageSize.value }
    if (filter.siteId) params.siteId = filter.siteId
    if (filter.type) params.type = filter.type
    const data = await request.get('/system/tpl/list', params)
    list.value = data.list || []
    total.value = data.total || 0
  } catch (e) {
    message.error(e.message || '加载模版列表失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.value = 1
  load()
}

function reset() {
  filter.siteId = null
  filter.type = null
  page.value = 1
  load()
}

/* ---------------- 表格 ---------------- */
const columns = [
  {
    title: '操作',
    key: 'ops',
    width: 130,
    render(row) {
      const btns = [
        h(NButton, { size: 'tiny', type: 'primary', secondary: true, onClick: () => openView(row) }, { default: () => '查看' })
      ]
      if (hasPerm('system:tpl:delete')) {
        btns.push(
          h(NPopconfirm, { onPositiveClick: () => removeRow(row) }, {
            trigger: () => h(NButton, { size: 'tiny', type: 'error', quaternary: true }, { default: () => '删除' }),
            default: () => `确定删除模版「${row.name}」吗？`
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
        disabled: !hasPerm('system:tpl:status'),
        onUpdateValue: (v) => toggleStatus(row, v)
      })
    }
  },
  { title: '站点', key: 'siteName', width: 220, ellipsis: { tooltip: true } },
  { title: '类型', key: 'type', width: 130 },
  { title: '名称', key: 'name', minWidth: 200, ellipsis: { tooltip: true } }
]

async function toggleStatus(row, v) {
  row._statusLoading = true
  try {
    await request.post('/system/tpl/status', { id: row.id, status: v ? 1 : 0 })
    row.status = v
    message.success(`模版「${row.name}」已${v ? '开启' : '关闭'}`)
  } catch (e) {
    message.error(e.message || '操作失败')
  } finally {
    row._statusLoading = false
  }
}

/* ---------------- 查看 / 新增 / 删除 ---------------- */
const showView = ref(false)
const viewRow = ref(null)

function openView(row) {
  viewRow.value = row
  showView.value = true
}

/**
 * 点击 Word 图标下载模板文件（uploads 目录由 Apache 静态直出，无需鉴权）
 * 用 <a> 直接触发下载而非 fetch 拉取：静态文件没有 CORS 头，
 * 前端源与 API 源不同源时 fetch 会被浏览器拦截（报 CORS 错误）
 */
function downloadTpl(row) {
  if (!row.filePath) {
    message.warning('该模板暂未上传文件')
    return
  }
  const a = document.createElement('a')
  a.href = API_BASE + '/' + row.filePath
  a.download = row.fileName || row.name + '.docx'
  document.body.appendChild(a)
  a.click()
  a.remove()
}

const showModal = ref(false)
const saving = ref(false)
const editingId = ref(null)
const form = reactive({ id: null, siteId: null, type: null, name: '', status: true })

function openAdd() {
  editingId.value = null
  Object.assign(form, { id: null, siteId: null, type: null, name: '', status: true })
  showModal.value = true
}

function openEdit(row) {
  editingId.value = row.id
  Object.assign(form, { id: row.id, siteId: row.siteId, type: row.type, name: row.name, status: row.status })
  showModal.value = true
}

async function save() {
  if (!form.siteId) {
    message.warning('请选择站点')
    return
  }
  if (!form.type) {
    message.warning('请选择类型')
    return
  }
  if (!form.name) {
    message.warning('请输入名称')
    return
  }
  saving.value = true
  try {
    const payload = {
      siteId: form.siteId,
      type: form.type,
      name: form.name,
      status: form.status ? 1 : 0
    }
    if (form.id) {
      await request.post('/system/tpl/update', { id: form.id, ...payload })
      message.success('修改成功')
    } else {
      await request.post('/system/tpl/add', payload)
      message.success('新增模版成功')
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
    await request.post('/system/tpl/delete', { id: row.id })
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

onMounted(() => {
  loadSiteOptions()
  load()
})
</script>

<template>
  <div class="page-card">
    <n-form inline size="small" label-placement="left" :show-feedback="false" style="margin-bottom: 12px">
      <n-space :size="16" align="center">
        <n-select
          v-model:value="filter.siteId"
          placeholder="站点"
          clearable
          :options="siteOptions"
          style="width: 220px"
          @update:value="search"
        />
        <n-select
          v-model:value="filter.type"
          placeholder="类型"
          clearable
          :options="TPL_TYPES.map((v) => ({ label: v, value: v }))"
          style="width: 180px"
          @update:value="search"
        />
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
      <n-button type="primary" size="small" v-perm="'system:tpl:add'" @click="openAdd">
        <template #icon><n-icon><AddIcon /></n-icon></template>
        新增
      </n-button>
      <n-space :size="8">
        <n-button size="small" quaternary title="刷新" @click="load">
          <template #icon><n-icon><RefreshIcon /></n-icon></template>
        </n-button>
      </n-space>
    </div>

    <n-data-table
      size="small"
      :columns="columns"
      :data="list"
      :loading="loading"
      :row-key="(r) => r.id"
      :scroll-x="900"
      max-height="540"
      :pagination="{
        page,
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
    <n-modal v-model:show="showView" preset="card" title="查看" style="width: 680px">
      <div v-if="viewRow" class="view-grid">
        <div class="view-item">
          <span class="view-label">站点</span>
          <span class="view-value">{{ viewRow.siteName }}</span>
        </div>
        <div class="view-item">
          <span class="view-label">类型</span>
          <span class="view-value">{{ viewRow.type }}</span>
        </div>
        <div class="view-item">
          <span class="view-label">名称</span>
          <span class="view-value">{{ viewRow.name }}</span>
        </div>
        <div class="view-item">
          <span class="view-label">模板</span>
          <span class="view-value">
            <span
              class="tpl-file"
              :class="{ disabled: !viewRow.filePath }"
              :title="viewRow.filePath ? '点击下载：' + (viewRow.fileName || '模板文件') : '暂未上传文件'"
              @click="downloadTpl(viewRow)"
            >
              <svg width="76" height="76" viewBox="0 0 80 80" aria-hidden="true">
                <rect width="80" height="80" rx="16" fill="#2B579A" />
                <rect x="42" y="16" width="26" height="48" rx="2" fill="#FFFFFF" />
                <rect x="46" y="24" width="18" height="2.6" fill="#2B579A" />
                <rect x="46" y="31" width="18" height="2.6" fill="#2B579A" />
                <rect x="46" y="38" width="18" height="2.6" fill="#2B579A" />
                <rect x="46" y="45" width="18" height="2.6" fill="#2B579A" />
                <rect x="46" y="52" width="13" height="2.6" fill="#2B579A" />
                <text x="8" y="55" font-family="Arial, sans-serif" font-size="34" font-weight="bold" fill="#FFFFFF">W</text>
              </svg>
            </span>
          </span>
        </div>
      </div>
    </n-modal>

    <!-- 新增 / 编辑 -->
    <n-modal v-model:show="showModal" preset="card" :title="form.id ? '编辑模版' : '新增模版'" style="width: 520px">
      <n-form label-placement="left" label-width="90" size="small">
        <n-form-item label="站点" required>
          <n-select v-model:value="form.siteId" placeholder="请选择" :options="siteOptions" />
        </n-form-item>
        <n-form-item label="类型" required>
          <n-select v-model:value="form.type" placeholder="请选择" :options="TPL_TYPES.map((v) => ({ label: v, value: v }))" />
        </n-form-item>
        <n-form-item label="名称" required>
          <n-input v-model:value="form.name" placeholder="请输入" />
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

/* 查看弹窗布局：站点/类型 一行，名称/模板 一行 */
.view-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  row-gap: 22px;
  padding: 8px 4px 16px;
}

.view-item {
  display: flex;
  align-items: flex-start;
  min-width: 0;
}

.view-label {
  flex-shrink: 0;
  width: 90px;
  color: #999;
  font-size: 14px;
}

.view-value {
  color: #333;
  font-size: 14px;
  word-break: break-all;
}

.tpl-file {
  display: inline-block;
  cursor: pointer;
  line-height: 0;
  border-radius: 16px;
  transition: box-shadow 0.2s;

  &:hover {
    box-shadow: 0 4px 12px rgba(43, 87, 154, 0.35);
  }

  &.disabled {
    cursor: not-allowed;
    opacity: 0.4;

    &:hover {
      box-shadow: none;
    }
  }
}
</style>
