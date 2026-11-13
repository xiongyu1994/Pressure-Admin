<script setup>
import { ref, reactive, computed, h } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSpace, NTag, NPopconfirm } from 'naive-ui'
import { useRoleStore } from '@/store/role'
import { asyncRoutes } from '@/router'

const message = useMessage()
const roleStore = useRoleStore()

/** 由动态路由树生成权限树（key 即权限标识） */
function toTreeData(routes) {
  return routes.map((r) => ({
    key: r.meta.permission || r.path,
    label: r.meta.title,
    children: r.children ? toTreeData(r.children) : undefined
  }))
}
const permTree = toTreeData(asyncRoutes)

function collectKeys(nodes, res = []) {
  for (const n of nodes) {
    res.push(n.key)
    if (n.children) collectKeys(n.children, res)
  }
  return res
}
const allPermKeys = collectKeys(permTree)

const columns = [
  { title: '角色名称', key: 'label', width: 140 },
  { title: '角色标识', key: 'name', width: 120 },
  {
    title: '权限数',
    key: 'permissions',
    width: 90,
    align: 'center',
    render(row) {
      return row.permissions.includes('*') ? '全部' : String(row.permissions.length)
    }
  },
  { title: '备注', key: 'remark', ellipsis: { tooltip: true } },
  {
    title: '内置',
    key: 'builtIn',
    width: 70,
    align: 'center',
    render(row) {
      return h(
        NTag,
        { size: 'small', bordered: false, type: row.builtIn ? 'default' : 'success' },
        { default: () => (row.builtIn ? '是' : '否') }
      )
    }
  },
  {
    title: '操作',
    key: 'actions',
    width: 170,
    render(row) {
      const btns = [
        h(
          NButton,
          { size: 'tiny', type: 'primary', quaternary: true, onClick: () => openPermModal(row) },
          { default: () => '权限设置' }
        )
      ]
      if (!row.builtIn) {
        btns.push(
          h(
            NPopconfirm,
            { onPositiveClick: () => removeRole(row) },
            {
              trigger: () =>
                h(NButton, { size: 'tiny', type: 'error', quaternary: true }, { default: () => '删除' }),
              default: () => `确定删除角色「${row.label}」吗？`
            }
          )
        )
      }
      return h(NSpace, null, { default: () => btns })
    }
  }
]

/* ---------- 权限设置弹窗 ---------- */
const showModal = ref(false)
const currentRole = ref(null)
const checkedKeys = ref([])

function openPermModal(role) {
  currentRole.value = role
  checkedKeys.value = role.permissions.includes('*') ? [...allPermKeys] : [...role.permissions]
  showModal.value = true
}

function savePerms() {
  roleStore.updateRolePermissions(currentRole.value.id, checkedKeys.value)
  message.success(`角色「${currentRole.value.label}」权限已更新`)
  showModal.value = false
}

/* ---------- 新增角色 ---------- */
const showAdd = ref(false)
const addForm = reactive({ label: '', name: '', remark: '' })

function addRole() {
  if (!addForm.label || !addForm.name) {
    message.warning('请填写角色名称和标识')
    return
  }
  roleStore.addRole({ ...addForm, permissions: ['dashboard'] })
  Object.assign(addForm, { label: '', name: '', remark: '' })
  showAdd.value = false
  message.success('新增角色成功')
}

function removeRole(row) {
  roleStore.removeRole(row.id)
  message.success('删除成功')
}

const roleList = computed(() => roleStore.roles)
</script>

<template>
  <div class="page-card">
    <div class="toolbar">
      <n-alert type="info" :show-icon="false" style="flex: 1">
        在「权限设置」中为角色勾选可访问的菜单与操作权限，保存后重新登录即可生效。
      </n-alert>
      <n-button type="primary" @click="showAdd = true">新增角色</n-button>
    </div>

    <n-data-table :columns="columns" :data="roleList" />

    <n-modal
      v-model:show="showModal"
      preset="card"
      :title="`权限设置 - ${currentRole?.label || ''}`"
      style="width: 480px"
    >
      <n-tree
        block-line
        checkable
        cascade
        :data="permTree"
        :checked-keys="checkedKeys"
        :default-expanded-keys="['system']"
        @update:checked-keys="(keys) => (checkedKeys = keys)"
      />
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="showModal = false">取消</n-button>
          <n-button type="primary" @click="savePerms">保存</n-button>
        </div>
      </template>
    </n-modal>

    <n-modal v-model:show="showAdd" preset="card" title="新增角色" style="width: 440px">
      <n-form label-placement="left" label-width="80">
        <n-form-item label="角色名称" required>
          <n-input v-model:value="addForm.label" placeholder="如：质检员" />
        </n-form-item>
        <n-form-item label="角色标识" required>
          <n-input v-model:value="addForm.name" placeholder="如：quality" />
        </n-form-item>
        <n-form-item label="备注">
          <n-input v-model:value="addForm.remark" type="textarea" placeholder="角色说明" />
        </n-form-item>
      </n-form>
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="showAdd = false">取消</n-button>
          <n-button type="primary" @click="addRole">确定</n-button>
        </div>
      </template>
    </n-modal>
  </div>
</template>

<style lang="scss" scoped>
.toolbar {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 14px;
}
</style>
