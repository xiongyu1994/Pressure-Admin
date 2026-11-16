<script setup>
import { ref, reactive, computed, h, onMounted } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSwitch, NSpace, NTag, NPopconfirm } from 'naive-ui'
import request from '@/utils/request'

const message = useMessage()

/* ---------------- 筛选 ---------------- */
const filters = reactive({
  name: '',
  account: '',
  phone: '',
  unitName: '',
  status: null
})

function resetSearch() {
  filters.name = ''
  filters.account = ''
  filters.phone = ''
  filters.unitName = ''
  filters.status = null
  load()
}

/* ---------------- 账号数据（来自 system/user/list 接口） ---------------- */
const list = ref([])
const listLoading = ref(false)

async function load() {
  listLoading.value = true
  try {
    const data = await request.get('/system/user/list', { page: 1, limit: 500 })
    list.value = data.list || []
  } catch (e) {
    message.error(e.message || '加载账号列表失败')
  } finally {
    listLoading.value = false
  }
}

onMounted(load)

const filtered = computed(() =>
  list.value.filter(
    (u) =>
      (!filters.name || (u.name || '').includes(filters.name)) &&
      (!filters.account || (u.account || '').includes(filters.account)) &&
      (!filters.phone || (u.phone || '').includes(filters.phone)) &&
      (!filters.unitName || (u.unitName || '').includes(filters.unitName)) &&
      (filters.status === null || u.status === filters.status)
  )
)

/* ---------------- 表格列 ---------------- */
const columns = [
  {
    title: '操作',
    key: 'actions',
    width: 130,
    render(row) {
      return h(
        NSpace,
        { size: 4 },
        {
          default: () => [
            h(
              NButton,
              { size: 'tiny', type: 'primary', quaternary: true, onClick: () => openModal(row) },
              { default: () => '编辑' }
            ),
            h(
              NPopconfirm,
              { onPositiveClick: () => removeRow(row) },
              {
                trigger: () =>
                  h(NButton, { size: 'tiny', type: 'error', quaternary: true }, { default: () => '删除' }),
                default: () => `确定删除账号「${row.name}」吗？`
              }
            )
          ]
        }
      )
    }
  },
  { title: 'ID', key: 'id', width: 70, sorter: 'default' },
  {
    title: '状态',
    key: 'status',
    width: 80,
    render(row) {
      return h(NSwitch, {
        value: row.status,
        size: 'small',
        loading: row._statusLoading,
        onUpdateValue: (v) => toggleStatus(row, v)
      })
    }
  },
  { title: '姓名', key: 'name', width: 110 },
  { title: '账号', key: 'account', width: 120 },
  { title: '手机号', key: 'phone', width: 120 },
  { title: '单位名称', key: 'unitName', minWidth: 200, ellipsis: { tooltip: true } },
  {
    title: '身份',
    key: 'identity',
    width: 100,
    render(row) {
      const typeMap = { 管理员: 'error', 检验员: 'info', 客户代表: 'success' }
      return h(
        NTag,
        { size: 'small', bordered: false, type: typeMap[row.identity] || 'default' },
        { default: () => row.identity }
      )
    }
  },
  {
    title: '签名',
    key: 'sign',
    width: 70,
    render(row) {
      return row.sign
        ? h(NTag, { size: 'small', bordered: false, type: 'success' }, { default: () => '已上传' })
        : h(NTag, { size: 'small', bordered: false }, { default: () => '未上传' })
    }
  },
  { title: '最后登录时间', key: 'lastTime', width: 160, sorter: 'default' },
  { title: '最后登录IP', key: 'lastIp', width: 140 }
]

/* ---------------- 状态启停（system/user/status 接口） ---------------- */
async function toggleStatus(row, v) {
  row._statusLoading = true
  try {
    await request.post('/system/user/status', { id: row.id, status: v ? 1 : 0 })
    row.status = v
    message.success(`账号「${row.name}」已${v ? '启用' : '停用'}`)
  } catch (e) {
    message.error(e.message || '操作失败')
  } finally {
    row._statusLoading = false
  }
}

/* ---------------- 新增 / 编辑弹窗 ---------------- */
const showModal = ref(false)
const saving = ref(false)
const editingId = ref(null)
const form = reactive({
  account: '',
  password: '',
  password2: '',
  name: '',
  phone: '',
  unitName: '',
  identity: '检验员'
})

function openModal(row) {
  if (row) {
    editingId.value = row.id
    Object.assign(form, { account: row.account, password: '', password2: '', name: row.name, phone: row.phone, unitName: row.unitName, identity: row.identity })
  } else {
    editingId.value = null
    Object.assign(form, { account: '', password: '', password2: '', name: '', phone: '', unitName: '', identity: '检验员' })
  }
  showModal.value = true
}

async function save() {
  if (!form.account || !form.name) {
    message.warning('请填写账号和姓名')
    return
  }
  saving.value = true
  try {
    if (!editingId.value) {
      if (!form.password) {
        message.warning('请填写密码')
        return
      }
      if (form.password !== form.password2) {
        message.warning('两次输入的密码不一致')
        return
      }
      await request.post('/system/user/add', {
        account: form.account,
        password: form.password,
        name: form.name,
        phone: form.phone,
        unitName: form.unitName,
        identity: form.identity
      })
      message.success('新增账号成功')
    } else {
      const payload = {
        id: editingId.value,
        name: form.name,
        phone: form.phone,
        unitName: form.unitName,
        identity: form.identity
      }
      if (form.password) payload.password = form.password
      await request.post('/system/user/update', payload)
      message.success('修改成功')
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
    await request.post('/system/user/delete', { id: row.id })
    message.success('删除成功')
    await load()
  } catch (e) {
    message.error(e.message || '删除失败')
  }
}
</script>

<template>
  <div class="page-card">
    <n-form inline :show-feedback="false" label-placement="left" class="filter-bar">
      <n-form-item label="姓名">
        <n-input v-model:value="filters.name" placeholder="请输入姓名" clearable style="width: 130px" />
      </n-form-item>
      <n-form-item label="账号">
        <n-input v-model:value="filters.account" placeholder="请输入账号" clearable style="width: 130px" />
      </n-form-item>
      <n-form-item label="手机号">
        <n-input v-model:value="filters.phone" placeholder="请输入手机号" clearable style="width: 140px" />
      </n-form-item>
      <n-form-item label="单位名称">
        <n-input v-model:value="filters.unitName" placeholder="请输入单位名称" clearable style="width: 180px" />
      </n-form-item>
      <n-form-item label="状态">
        <n-select
          v-model:value="filters.status"
          :options="[
            { label: '全部', value: null },
            { label: '启用', value: true },
            { label: '停用', value: false }
          ]"
          style="width: 100px"
        />
      </n-form-item>
      <n-form-item>
        <n-space>
          <n-button type="primary" size="small" @click="load">查询</n-button>
          <n-button size="small" @click="resetSearch">重置</n-button>
          <n-button size="small" type="primary" @click="openModal(null)">新增</n-button>
        </n-space>
      </n-form-item>
    </n-form>

    <n-data-table
      :columns="columns"
      :data="filtered"
      :loading="listLoading"
      :scroll-x="1400"
      :row-key="(r) => r.id"
      size="small"
      :pagination="{ pageSize: 15 }"
    />

    <n-modal
      v-model:show="showModal"
      preset="card"
      :title="editingId ? '编辑系统账号' : '新增系统账号'"
      style="width: 500px"
    >
      <n-form label-placement="left" label-width="90" size="small">
        <n-form-item label="账号" required>
          <n-input v-model:value="form.account" :disabled="!!editingId" placeholder="登录账号" />
        </n-form-item>
        <n-form-item v-if="!editingId" label="密码" required>
          <n-input v-model:value="form.password" type="password" show-password-on="click" placeholder="密码" />
        </n-form-item>
        <n-form-item v-if="!editingId" label="重复密码" required>
          <n-input v-model:value="form.password2" type="password" show-password-on="click" placeholder="重复密码" />
        </n-form-item>
        <n-form-item label="姓名" required>
          <n-input v-model:value="form.name" placeholder="姓名" />
        </n-form-item>
        <n-form-item label="手机号">
          <n-input v-model:value="form.phone" placeholder="手机号" />
        </n-form-item>
        <n-form-item label="单位名称">
          <n-input v-model:value="form.unitName" placeholder="单位名称" />
        </n-form-item>
        <n-form-item label="身份">
          <n-select
            v-model:value="form.identity"
            :options="['管理员', '检验员', '客户代表'].map((v) => ({ label: v, value: v }))"
          />
        </n-form-item>
        <n-form-item label="签名">
          <n-upload :max="1" accept="image/*" default-upload="false">
            <n-button size="small">上传签名</n-button>
          </n-upload>
        </n-form-item>
      </n-form>
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="showModal = false">取消</n-button>
          <n-button type="primary" :loading="saving" @click="save">确认提交</n-button>
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
