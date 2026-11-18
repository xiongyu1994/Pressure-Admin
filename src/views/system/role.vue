<script setup>
import { ref, reactive, computed, h, onMounted } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSpace, NTag, NPopconfirm } from 'naive-ui'
import { useRoleStore } from '@/store/role'
import { asyncRoutes } from '@/router'
import { hasPerm } from '@/utils/permission'

const message = useMessage()
const roleStore = useRoleStore()

onMounted(() => {
  roleStore.fetchRoles().catch((e) => message.error(e.message || '加载角色列表失败'))
})

/** 由动态路由树生成权限树（菜单节点 + 其下按钮节点，key 即权限标识） */
function toTreeData(routes) {
  return routes.map((r) => {
    const children = []
    if (r.children) children.push(...toTreeData(r.children))
    if (r.meta.buttons) {
      children.push(
        ...r.meta.buttons.map((b) => ({ key: b.perm, label: `〔按钮〕${b.label}`, isButton: true }))
      )
    }
    return {
      key: r.meta.permission || r.path,
      label: r.meta.title,
      children: children.length ? children : undefined
    }
  })
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

/** 子权限 → 祖先权限映射（保存时自动补齐父级，避免只勾按钮导致菜单不显示） */
const parentMap = {}
;(function buildParentMap(nodes, ancestors = []) {
  for (const n of nodes) {
    parentMap[n.key] = ancestors
    if (n.children) buildParentMap(n.children, [...ancestors, n.key])
  }
})(permTree)

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
      const btns = []
      if (hasPerm('system:role:perm')) {
        btns.push(
          h(
            NButton,
            { size: 'tiny', type: 'primary', quaternary: true, onClick: () => openPermModal(row) },
            { default: () => '权限设置' }
          )
        )
      }
      if (!row.builtIn && hasPerm('system:role:delete')) {
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
/** 树展开状态受控：只随用户点击折叠/展开图标变化，勾选操作不影响展开状态 */
const expandedKeys = ref(['commission', 'workorder', 'system'])

function openPermModal(role) {
  currentRole.value = role
  checkedKeys.value = role.permissions.includes('*') ? [...allPermKeys] : [...role.permissions]
  showModal.value = true
}

const savingPerms = ref(false)

/**
 * 权限树勾选逻辑（非级联，手动联动）：
 * - 勾选按钮 → 自动补勾其父级菜单（保证菜单可见）
 * - 取消按钮 → 不影响父级菜单（父菜单保持勾选，仅页面内该按钮无权限）
 * - 取消菜单 → 级联取消其下所有子菜单和按钮（避免保存时因按钮勾选回补父级）
 */
function onPermChecked(keys) {
  const prev = new Set(checkedKeys.value)
  const next = new Set(keys)

  const keyNode = {}
  ;(function walk(nodes) {
    for (const n of nodes) {
      keyNode[n.key] = n
      if (n.children) walk(n.children)
    }
  })(permTree)

  // 新勾选：自动补齐父级菜单
  for (const k of [...next]) {
    if (!prev.has(k)) {
      for (const p of parentMap[k] || []) next.add(p)
    }
  }

  // 新取消：菜单节点级联取消所有后代
  for (const k of [...prev]) {
    if (!next.has(k)) {
      const node = keyNode[k]
      if (node?.children?.length) {
        const stack = [...node.children]
        while (stack.length) {
          const n = stack.pop()
          next.delete(n.key)
          if (n.children) stack.push(...n.children)
        }
      }
    }
  }
  checkedKeys.value = [...next]
}

async function savePerms() {
  // 勾选按钮权限时自动补齐其父级菜单权限，保证菜单可见
  const keys = new Set(checkedKeys.value)
  for (const k of [...keys]) {
    for (const p of parentMap[k] || []) keys.add(p)
  }

  savingPerms.value = true
  try {
    await roleStore.updateRolePermissions(currentRole.value.id, [...keys])
    message.success(`角色「${currentRole.value.label}」权限已更新，相关账号重新登录后生效`)
    showModal.value = false
  } catch (e) {
    message.error(e.message || '保存失败')
  } finally {
    savingPerms.value = false
  }
}

/* ---------- 新增角色 ---------- */
const showAdd = ref(false)
const addForm = reactive({ label: '', name: '', remark: '' })
const savingAdd = ref(false)

async function addRole() {
  if (!addForm.label || !addForm.name) {
    message.warning('请填写角色名称和标识')
    return
  }
  savingAdd.value = true
  try {
    await roleStore.addRole({ ...addForm })
    Object.assign(addForm, { label: '', name: '', remark: '' })
    showAdd.value = false
    message.success('新增角色成功')
  } catch (e) {
    message.error(e.message || '新增失败')
  } finally {
    savingAdd.value = false
  }
}

async function removeRole(row) {
  try {
    await roleStore.removeRole(row.id)
    message.success('删除成功')
  } catch (e) {
    message.error(e.message || '删除失败')
  }
}

const roleList = computed(() => roleStore.roles)
</script>

<template>
  <div class="page-card">
    <div class="toolbar">
      <n-alert type="info" :show-icon="false" style="flex: 1">
        在「权限设置」中为角色勾选可访问的菜单与操作权限，保存后重新登录即可生效。
      </n-alert>
      <n-button type="primary" v-perm="'system:role:add'" @click="showAdd = true">新增角色</n-button>
    </div>

    <n-data-table :columns="columns" :data="roleList" :loading="roleStore.loading" />

    <n-modal
      v-model:show="showModal"
      preset="card"
      :title="`权限设置 - ${currentRole?.label || ''}`"
      style="width: 520px"
    >
      <n-alert type="info" :show-icon="false" style="margin-bottom: 10px">
        勾选菜单控制页面访问，展开后可勾选页面内的按钮操作权限；保存后重新登录生效。
      </n-alert>
      <n-tree
        block-line
        checkable
        :data="permTree"
        :checked-keys="checkedKeys"
        :expanded-keys="expandedKeys"
        @update:checked-keys="onPermChecked"
        @update:expanded-keys="(keys) => (expandedKeys = keys)"
      />
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="showModal = false">取消</n-button>
          <n-button type="primary" :loading="savingPerms" @click="savePerms">保存</n-button>
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
          <n-button type="primary" :loading="savingAdd" @click="addRole">确定</n-button>
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
