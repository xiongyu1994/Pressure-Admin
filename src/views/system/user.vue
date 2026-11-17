<script setup>
import { ref, reactive, computed, h, onMounted } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSwitch, NSpace, NTag, NPopconfirm } from 'naive-ui'
import request from '@/utils/request'
import { hasPerm } from '@/utils/permission'

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
      const btns = []
      if (hasPerm('system:user:edit')) {
        btns.push(
          h(
            NButton,
            { size: 'tiny', type: 'primary', quaternary: true, onClick: () => openModal(row) },
            { default: () => '编辑' }
          )
        )
      }
      if (hasPerm('system:user:delete')) {
        btns.push(
          h(
            NPopconfirm,
            { onPositiveClick: () => removeRow(row) },
            {
              trigger: () =>
                h(NButton, { size: 'tiny', type: 'error', quaternary: true }, { default: () => '删除' }),
              default: () => `确定删除账号「${row.name}」吗？`
            }
          )
        )
      }
      return h(NSpace, { size: 4 }, { default: () => btns })
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
        disabled: !hasPerm('system:user:status'),
        onUpdateValue: (v) => toggleStatus(row, v)
      })
    }
  },
  { title: '姓名', key: 'name', width: 110 },
  { title: '账号', key: 'account', width: 120 },
  { title: '手机号', key: 'phone', width: 120 },
  { title: '单位名称', key: 'unitName', minWidth: 180, ellipsis: { tooltip: true } },
  { title: '站点', key: 'site', width: 170, ellipsis: { tooltip: true }, render: (r) => r.site || '/' },
  {
    title: '身份',
    key: 'identity',
    width: 100,
    render(row) {
      const typeMap = { 管理员: 'error', 录入人员: 'info', 检验人员: 'success', 审核员: 'warning', 打印人员: 'default', 录入审核: 'primary' }
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

/** 身份选项（对齐参考系统） */
const IDENTITIES = ['管理员', '录入人员', '检验人员', '审核员', '打印人员', '录入审核']
/** 站点选项：从站点管理读取开启状态的站点，读取失败回退默认 */
const SITES_DEFAULT = ['武汉翼华技术有限公司']
const siteOptions = ref([...SITES_DEFAULT])

async function loadSites() {
  try {
    const data = await request.get('/system/site/list', { page: 1, limit: 200, status: 1 })
    const names = (data.list || []).map((s) => s.name).filter(Boolean)
    if (names.length) siteOptions.value = names
  } catch {}
}

loadSites()

const form = reactive({
  account: '',
  password: '',
  password2: '',
  name: '',
  phone: '',
  unitName: '',
  site: [],
  identity: '检验人员',
  status: false
})

function openModal(row) {
  if (row) {
    editingId.value = row.id
    Object.assign(form, {
      account: row.account,
      password: '',
      password2: '',
      name: row.name,
      phone: row.phone,
      unitName: row.unitName,
      site: row.site ? String(row.site).split(',').filter(Boolean) : [],
      identity: row.identity,
      status: !!row.status
    })
  } else {
    editingId.value = null
    Object.assign(form, { account: '', password: '', password2: '', name: '', phone: '', unitName: '', site: [], identity: '检验人员', status: false })
  }
  showModal.value = true
}

async function save() {
  if (!form.account || !form.name) {
    message.warning('请填写账号和姓名')
    return
  }
  if (!form.identity) {
    message.warning('请选择身份')
    return
  }
  if (!form.site.length) {
    message.warning('请选择站点')
    return
  }
  const common = {
    name: form.name,
    phone: form.phone,
    unitName: form.unitName,
    site: form.site.join(','),
    identity: form.identity,
    status: form.status ? 1 : 0
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
        ...common
      })
      message.success('新增账号成功')
    } else {
      const payload = { id: editingId.value, ...common }
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
          <n-button size="small" type="primary" v-perm="'system:user:add'" @click="openModal(null)">新增</n-button>
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
      style="width: 640px"
    >
      <n-form label-placement="left" label-width="90" size="small">
        <n-grid :cols="2" :x-gap="16">
          <n-form-item-gi label="账号" required>
            <n-input v-model:value="form.account" :disabled="!!editingId" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi v-if="!editingId" label="密码" required>
            <n-input v-model:value="form.password" type="password" show-password-on="click" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi v-if="!editingId" label="重复密码" required>
            <n-input v-model:value="form.password2" type="password" show-password-on="click" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="姓名" required>
            <n-input v-model:value="form.name" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="手机号">
            <n-input v-model:value="form.phone" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="单位名称">
            <n-input v-model:value="form.unitName" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="身份" required>
            <n-select v-model:value="form.identity" placeholder="请选择" :options="IDENTITIES.map((v) => ({ label: v, value: v }))" />
          </n-form-item-gi>
          <n-form-item-gi label="站点" required>
            <n-checkbox-group v-model:value="form.site">
              <n-checkbox v-for="s in siteOptions" :key="s" :value="s" :label="s" />
            </n-checkbox-group>
          </n-form-item-gi>
          <n-form-item-gi label="状态">
            <n-switch v-model:value="form.status" size="medium">
              <template #unchecked>OFF</template>
            </n-switch>
          </n-form-item-gi>
          <n-form-item-gi label="签名">
            <n-upload :max="1" accept="image/*" default-upload="false">
              <n-button size="small">上传签名</n-button>
            </n-upload>
          </n-form-item-gi>
        </n-grid>
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
