<script setup>
import { ref, reactive, computed, watch, h } from 'vue'
import { useMessage } from 'naive-ui'
import { NButton } from 'naive-ui'
import { Search as SearchIcon, Refresh as RefreshIcon } from '@vicons/ionicons5'
import { useBusinessStore } from '@/store/business'

const props = defineProps({
  show: { type: Boolean, default: false },
  /** 弹窗标题 */
  title: { type: String, default: '安全阀信息' },
  /** 是否显示操作列（行内复制） */
  showOps: { type: Boolean, default: true }
})
const emit = defineEmits(['update:show', 'confirm', 'copy'])

const message = useMessage()
const store = useBusinessStore()

/* ---------- 搜索 / 分页状态 ---------- */
const loading = ref(false)
const saving = ref(false)
const checkedKeys = ref([])

const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const facets = ref({ equipTypes: [], mediaList: [], models: [] })

const filter = reactive({
  custId: null,
  checkCode: '',
  equipType: null,
  idNo: '',
  manuNo: '',
  vavleModel: null,
  workingMedia: null,
  setPress: null
})

const unitOptions = computed(() =>
  (store.units || []).map((u) => ({ label: u.custName || u.name, value: u.id }))
)

async function load() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      limit: pageSize.value
    }
    if (filter.custId) params.custId = filter.custId
    if (filter.checkCode) params.checkCode = filter.checkCode
    if (filter.equipType) params.equipType = filter.equipType
    if (filter.idNo) params.idNo = filter.idNo
    if (filter.manuNo) params.manuNo = filter.manuNo
    if (filter.vavleModel) params.vavleModel = filter.vavleModel
    if (filter.workingMedia) params.workingMedia = filter.workingMedia
    if (filter.setPress !== null && filter.setPress !== undefined && filter.setPress !== '') {
      params.setPress = filter.setPress
    }
    const data = await store.fetchValvePool(params)
    list.value = data.list || []
    total.value = data.total || 0
    facets.value = data.facets || { equipTypes: [], mediaList: [], models: [] }
  } catch (e) {
    message.error(e.message || '加载已有安全阀失败')
  } finally {
    loading.value = false
  }
}

function open() {
  checkedKeys.value = []
  Object.assign(filter, {
    custId: null,
    checkCode: '',
    equipType: null,
    idNo: '',
    manuNo: '',
    vavleModel: null,
    workingMedia: null,
    setPress: null
  })
  page.value = 1
  load()
}

function search() {
  page.value = 1
  load()
}

function reset() {
  Object.assign(filter, {
    custId: null,
    checkCode: '',
    equipType: null,
    idNo: '',
    manuNo: '',
    vavleModel: null,
    workingMedia: null,
    setPress: null
  })
  page.value = 1
  load()
}

function onPageChange(p) {
  page.value = p
  load()
}

function onPageSizeChange(s) {
  pageSize.value = s
  page.value = 1
  load()
}

watch(
  () => props.show,
  (v) => {
    if (v) open()
  }
)

/* ---------- 表格列 ---------- */
const WORKORDER_STATUS_MAP = {
  wait: '待检验',
  inspection: '检验中',
  rejected: '校验退回',
  audit: '待审核',
  approval: '待审批',
  print: '待打印',
  done: '已完成'
}

const columns = computed(() => {
  const cols = []
  // 复选框选择列（批量复制 / 确认提交按勾选行执行）
  cols.push({ type: 'selection' })
  if (props.showOps) {
    cols.push({
      title: '操作',
      key: 'ops',
      width: 80,
      render(row) {
        return h(
          NButton,
          { size: 'tiny', ghost: true, type: 'success', onClick: () => emit('copy', copyValve(row)) },
          { default: () => '复制' }
        )
      }
    })
  }
  cols.push(
    { title: '状态', key: 'statusText', width: 80, render: (r) => (r.checkStatus ? WORKORDER_STATUS_MAP[r.checkStatus] || r.checkStatus : '正常') },
    { title: '设备类型', key: 'protectedEquipType', width: 100, ellipsis: { tooltip: true }, render: (r) => r.protectedEquipType || '/' },
    { title: '要求整定压力MPa', key: 'setPress', width: 130 },
    { title: '密封试验压力MPa', key: 'tightnessPress', width: 130 },
    { title: '设备代码', key: 'idNo', width: 110, ellipsis: { tooltip: true }, render: (r) => r.idNo || '/' },
    { title: '安装位置', key: 'tagNo', width: 110, ellipsis: { tooltip: true }, render: (r) => r.tagNo || '/' },
    { title: '安全阀型号', key: 'vavleModel', width: 130, ellipsis: { tooltip: true } },
    { title: '制造单位', key: 'manufacturer', width: 150, ellipsis: { tooltip: true } },
    { title: '制造许可证号', key: 'licenseNo', width: 130, ellipsis: { tooltip: true }, render: (r) => r.licenseNo || '/' },
    { title: '出厂编号', key: 'manuNo', width: 110, ellipsis: { tooltip: true } },
    { title: '工作介质', key: 'workingMedia', width: 90 },
    { title: '使用单位', key: 'custName', width: 150, ellipsis: { tooltip: true } },
    { title: '校验编号', key: 'checkCode', width: 110, render: (r) => r.checkCode || '/' }
  )
  return cols
})

/* ---------- 提交 ---------- */
function checkedRows() {
  return list.value.filter((v) => checkedKeys.value.includes(v.libId))
}

/**
 * 复制 = 再复制一条数据：去掉 libId，后端会生成一条新的安全阀库记录
 * （而不是引用原库记录），勾选多条时逐条复制
 */
function copyValve(row) {
  const {
    libId: _lib,
    id: _i,
    custName: _cn,
    checkCode: _cc,
    checkStatus: _cs,
    createTime: _ct,
    updateTime: _ut,
    ...fields
  } = row
  return fields
}

function batchCopy() {
  const picked = checkedRows()
  if (!picked.length) {
    message.warning('请先勾选要复制的安全阀')
    return
  }
  saving.value = true
  // 批量复制：勾选的每条数据各复制一条新数据
  setTimeout(() => {
    emit('copy', picked.map(copyValve))
    checkedKeys.value = []
    saving.value = false
  }, 100)
}

function confirmSubmit() {
  const picked = checkedRows()
  if (!picked.length) {
    message.warning('请先勾选要添加的安全阀')
    return
  }
  saving.value = true
  setTimeout(() => {
    emit('confirm', picked.map((v) => ({ ...v })))
    emit('update:show', false)
    saving.value = false
  }, 100)
}

/** 剔除业务字段，只保留阀门字段（libId 保留：复制时后端复用库记录，不重复入库） */
function stripValve(row) {
  const {
    id: _i,
    custName: _cn,
    checkCode: _cc,
    checkStatus: _cs,
    createTime: _ct,
    updateTime: _ut,
    ...fields
  } = row
  return fields
}

/** 行内复制后刷新列表并去掉勾选 */
function afterCopy(rowId) {
  checkedKeys.value = checkedKeys.value.filter((k) => k !== rowId)
  load()
}

/** 清空勾选 */
function clearChecked() {
  checkedKeys.value = []
}

defineExpose({ reload: load, stripValve, afterCopy, clearChecked })
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    :title="title"
    style="width: 1180px"
    @update:show="(v) => emit('update:show', v)"
  >
    <!-- 搜索区 -->
    <div class="pool-search">
      <div class="pool-grid">
        <div class="field">
          <span class="flabel">使用单位</span>
          <n-select
            v-model:value="filter.custId"
            placeholder="请选择"
            clearable
            filterable
            :options="unitOptions"
            size="small"
            style="width: 300px"
          />
        </div>
        <div class="field">
          <span class="flabel">校验编号</span>
          <n-input v-model:value="filter.checkCode" placeholder="" size="small" clearable style="width: 180px" />
        </div>
        <div class="field">
          <span class="flabel">设备类型</span>
          <n-select
            v-model:value="filter.equipType"
            placeholder="请选择"
            clearable
            filterable
            :options="(facets.equipTypes || []).map((v) => ({ label: v, value: v }))"
            size="small"
            style="width: 180px"
          />
        </div>
        <div class="field">
          <span class="flabel">设备代码</span>
          <n-input v-model:value="filter.idNo" placeholder="" size="small" clearable style="width: 180px" />
        </div>
        <div class="field">
          <span class="flabel">出厂编号</span>
          <n-input v-model:value="filter.manuNo" placeholder="" size="small" clearable style="width: 180px" />
        </div>
        <div class="field">
          <span class="flabel">安全阀型号</span>
          <n-select
            v-model:value="filter.vavleModel"
            placeholder="请选择"
            clearable
            filterable
            :options="(facets.models || []).map((v) => ({ label: v, value: v }))"
            size="small"
            style="width: 180px"
          />
        </div>
        <div class="field">
          <span class="flabel">工作介质</span>
          <n-select
            v-model:value="filter.workingMedia"
            placeholder="请选择"
            clearable
            filterable
            :options="(facets.mediaList || []).map((v) => ({ label: v, value: v }))"
            size="small"
            style="width: 180px"
          />
        </div>
        <div class="field">
          <span class="flabel">要求整定压力MPa</span>
          <n-input-number
            v-model:value="filter.setPress"
            placeholder=""
            size="small"
            clearable
            :precision="2"
            style="width: 180px"
          />
        </div>
      </div>
      <div class="pool-btns">
        <n-button size="small" type="primary" @click="search">
          <template #icon><n-icon><SearchIcon /></n-icon></template>
          查询
        </n-button>
        <n-button size="small" @click="reset">
          <template #icon><n-icon><RefreshIcon /></n-icon></template>
          重置
        </n-button>
      </div>
    </div>

    <!-- 表格工具栏 -->
    <div class="pool-toolbar">
      <n-button size="small" color="#18a058" :loading="saving" @click="batchCopy">批量复制</n-button>
      <span class="pool-count">已选中<b>{{ checkedKeys.length }}</b>条数据</span>
    </div>

    <n-data-table
      size="small"
      :columns="columns"
      :data="list"
      :loading="loading"
      :row-key="(r) => r.libId"
      :scroll-x="1500"
      max-height="330"
      v-model:checked-row-keys="checkedKeys"
    />

    <div class="pool-page">
      <n-pagination
        :page="page"
        :page-size="pageSize"
        :item-count="total"
        show-quick-jumper
        show-size-picker
        :page-sizes="[20, 50, 100, 200, 500]"
        @update:page="onPageChange"
        @update:page-size="onPageSizeChange"
      />
      <span class="pool-total">共 {{ total }} 条</span>
    </div>

    <template #footer>
      <div class="pool-footer">
        <n-button type="primary" size="large" style="width: 220px" :loading="saving" @click="confirmSubmit">
          确认提交
        </n-button>
      </div>
    </template>
  </n-modal>
</template>

<style lang="scss" scoped>
.pool-search {
  margin-bottom: 14px;
  padding: 14px 16px 10px;
  background: #fafbfc;
  border: 1px solid #eef0f4;
  border-radius: 6px;

  .pool-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    row-gap: 12px;
    column-gap: 20px;

    .field {
      display: flex;
      align-items: center;

      .flabel {
        flex: none;
        width: 110px;
        text-align: right;
        padding-right: 10px;
        color: #666;
        font-size: 13px;
        white-space: nowrap;
      }
    }
  }

  .pool-btns {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 12px;
  }
}

.pool-toolbar {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 10px;

  .pool-count {
    color: #666;
    font-size: 13px;

    b {
      margin: 0 2px;
      color: #2b6de8;
    }
  }
}

.pool-page {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;

  .pool-total {
    color: #888;
    font-size: 13px;
  }
}

.pool-footer {
  display: flex;
  justify-content: center;
}
</style>
