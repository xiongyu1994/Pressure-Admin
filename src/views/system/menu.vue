<script setup>
import { ref, reactive, computed, h } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton, NSwitch, NSpace, NTag, NPopconfirm } from 'naive-ui'
import { useMenuStore } from '@/store/menu'
import { iconMap } from '@/utils/icons'

const message = useMessage()
const menuStore = useMenuStore()

const columns = [
  { title: '菜单名称', key: 'title', width: 160 },
  {
    title: '类型',
    key: 'type',
    width: 80,
    align: 'center',
    render(row) {
      return h(
        NTag,
        { size: 'small', bordered: false, type: row.type === 'dir' ? 'info' : 'success' },
        { default: () => (row.type === 'dir' ? '目录' : '菜单') }
      )
    }
  },
  {
    title: '图标',
    key: 'icon',
    width: 80,
    align: 'center',
    render(row) {
      return row.icon && iconMap[row.icon]
        ? h('span', { style: 'color:#1fc2b4' }, '已设置')
        : h('span', { style: 'color:#bbb' }, '—')
    }
  },
  { title: '路由路径', key: 'path' },
  { title: '权限标识', key: 'permission' },
  { title: '排序', key: 'sort', width: 60, align: 'center' },
  {
    title: '可见',
    key: 'visible',
    width: 70,
    align: 'center',
    render(row) {
      return h(NSwitch, {
        value: row.visible,
        size: 'small',
        onUpdateValue: () => {
          menuStore.toggleVisible(row.id)
          message.success(`菜单「${row.title}」已${row.visible ? '隐藏' : '显示'}`)
        }
      })
    }
  },
  {
    title: '操作',
    key: 'actions',
    width: 180,
    render(row) {
      const btns = []
      if (row.type === 'dir') {
        btns.push(
          h(
            NButton,
            { size: 'tiny', type: 'info', quaternary: true, onClick: () => openModal(null, row) },
            { default: () => '新增子级' }
          )
        )
      }
      btns.push(
        h(
          NButton,
          { size: 'tiny', type: 'primary', quaternary: true, onClick: () => openModal(row, null) },
          { default: () => '编辑' }
        ),
        h(
          NPopconfirm,
          { onPositiveClick: () => removeMenu(row) },
          {
            trigger: () =>
              h(NButton, { size: 'tiny', type: 'error', quaternary: true }, { default: () => '删除' }),
            default: () => `确定删除菜单「${row.title}」吗？`
          }
        )
      )
      return h(NSpace, { size: 4 }, { default: () => btns })
    }
  }
]

/* ---------- 新增 / 编辑弹窗 ---------- */
const showModal = ref(false)
const editingId = ref(null)
const parentId = ref(0)
const form = reactive({ title: '', path: '', permission: '', icon: '', sort: 1, visible: true })

function openModal(row, parent) {
  if (row) {
    editingId.value = row.id
    parentId.value = row.parentId
    Object.assign(form, { title: row.title, path: row.path, permission: row.permission, icon: row.icon, sort: row.sort, visible: row.visible })
  } else {
    editingId.value = null
    parentId.value = parent ? parent.id : 0
    Object.assign(form, { title: '', path: '', permission: '', icon: '', sort: 1, visible: true })
  }
  showModal.value = true
}

function save() {
  if (!form.title || !form.path) {
    message.warning('请填写菜单名称和路由路径')
    return
  }
  if (editingId.value) {
    menuStore.updateMenu(editingId.value, { ...form })
    message.success('修改成功')
  } else {
    const node = { ...form, parentId: parentId.value, type: 'menu', id: Date.now(), children: undefined }
    if (parentId.value) {
      menuStore.addChild(parentId.value, node)
    } else {
      menuStore.menus.push(node)
    }
    message.success('新增成功')
  }
  showModal.value = false
}

function removeMenu(row) {
  menuStore.removeMenu(row.id)
  message.success('删除成功')
}

const iconOptions = Object.keys(iconMap).map((k) => ({ label: k, value: k }))
</script>

<template>
  <div class="page-card">
    <div class="toolbar">
      <n-alert type="info" :show-icon="false" style="flex: 1">
        通过「可见」开关控制菜单是否在侧边栏显示，实时生效；删除菜单将同时移除其子菜单。
      </n-alert>
      <n-button type="primary" @click="openModal(null, null)">新增顶级菜单</n-button>
    </div>

    <n-data-table
      :columns="columns"
      :data="menuStore.menus"
      row-key="id"
      default-expand-all
      children-key="children"
    />

    <n-modal
      v-model:show="showModal"
      preset="card"
      :title="editingId ? '编辑菜单' : '新增菜单'"
      style="width: 460px"
    >
      <n-form label-placement="left" label-width="90">
        <n-form-item label="菜单名称" required>
          <n-input v-model:value="form.title" placeholder="菜单名称" />
        </n-form-item>
        <n-form-item label="路由路径" required>
          <n-input v-model:value="form.path" placeholder="如 /system/user" />
        </n-form-item>
        <n-form-item label="权限标识">
          <n-input v-model:value="form.permission" placeholder="如 system:user" />
        </n-form-item>
        <n-form-item label="图标">
          <n-select v-model:value="form.icon" :options="iconOptions" clearable placeholder="选择图标" />
        </n-form-item>
        <n-form-item label="排序">
          <n-input-number v-model:value="form.sort" :min="1" />
        </n-form-item>
        <n-form-item label="是否可见">
          <n-switch v-model:value="form.visible" />
        </n-form-item>
      </n-form>
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="showModal = false">取消</n-button>
          <n-button type="primary" @click="save">确定</n-button>
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
