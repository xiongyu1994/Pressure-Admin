<script setup>
import { ref, reactive, computed, watch, h } from 'vue'
import { NSpace, useMessage } from 'naive-ui'
import { NButton, NTag, NPopconfirm } from 'naive-ui'
import { Search as SearchIcon, Refresh as RefreshIcon } from '@vicons/ionicons5'
import ValveFormModal from './ValveFormModal.vue'
import ValvePoolModal from './ValvePoolModal.vue'
import { WORKORDER_STATUS, useBusinessStore } from '@/store/business'
import { hasPerm } from '@/utils/permission'
import { API_BASE } from '@/utils/request'

const props = defineProps({
  show: { type: Boolean, default: false },
  row: { type: Object, default: null },
  /** view=查看（只读 + 打印标签）；edit=编辑（可维护阀门清单 + 确认提交） */
  mode: { type: String, default: 'view' }
})
const emit = defineEmits(['update:show'])

const message = useMessage()
const store = useBusinessStore()

/* 打开时从后端拉取完整详情（含安全阀清单） */
const detail = ref(null)
const detailLoading = ref(false)

watch(
  () => [props.show, props.row?.id],
  async ([show, id]) => {
    if (show && id) {
      detailLoading.value = true
      detail.value = null
      try {
        detail.value = await store.fetchCommissionDetail(id)
      } catch {
        detail.value = null
      } finally {
        detailLoading.value = false
      }
    } else if (!show) {
      detail.value = null
    }
  },
  { immediate: true }
)

const view = computed(() => detail.value || props.row)
const valves = computed(() => view.value?.valves || [])

/** 委托人手写签名图片完整地址（signImg 为后端相对路径 uploads/sign/xx.png） */
const signSrc = computed(() => (view.value?.signImg ? `${API_BASE}/${view.value.signImg.replace(/^\/+/, '')}` : ''))

/* ---------- 分区折叠 ---------- */
const basicOpen = ref(true)
const detailOpen = ref(true)

const canEditValve = hasPerm('commission:list:edit')

/** 修改校验编号按钮权限 */
const canEditCode = hasPerm('commission:list:editCode')

/** 编辑模式才展示：操作栏 + 新增/选择按钮 */
const canMaintain = computed(() => props.mode === 'edit' && canEditValve)

/* ---------- 详情信息表（对照参考布局：操作列 + 完整字段列） ---------- */
const checkStatusText = (s) => WORKORDER_STATUS[s]?.label || '待校验'
const checkStatusType = (s) => WORKORDER_STATUS[s]?.type || 'default'
const pressRangeText = (r) => {
  if (r.pressClassRangeMin === '' || r.pressClassRangeMin == null) return '/'
  return `${r.pressClassRangeMin} - ${r.pressClassRangeMax ?? ''}`
}
const dash = (v) => (v === '' || v == null ? '/' : v)

/** 基础列（查看 / 编辑模式共用） */
const codeColumn = {
  title: '校验编号',
  key: 'orderCode',
  width: 170,
  render(r) {
    const nodes = [h('span', { style: 'word-break: break-all' }, dash(r.orderCode))]
    if (canEditCode && r.woId) {
      nodes.push(
        h(
          NButton,
          { size: 'tiny', secondary: true, type: 'primary', onClick: () => openCodeEdit(r) },
          { default: () => '编辑' }
        )
      )
    }
    return h('div', { style: 'display:flex;align-items:center;gap:6px' }, nodes)
  }
}

const baseColumns = [
  { title: '#', key: 'idx', width: 50, render: (r, i) => i + 1 },
  codeColumn,
  { title: '安全阀型号', key: 'vavleModel', width: 130, ellipsis: { tooltip: true } },
  { title: '公称通径mm', key: 'diameterNominal', width: 95 },
  { title: '出厂编号', key: 'manuNo', width: 120, ellipsis: { tooltip: true } },
  { title: '工作压力MPa', key: 'workingPress', width: 105 },
  { title: '要求整定压力MPa', key: 'setPress', width: 135 },
  { title: '安装位置', key: 'tagNo', width: 120, ellipsis: { tooltip: true } },
  { title: '工作介质', key: 'workingMedia', width: 90 },
  { title: '制造单位', key: 'manufacturer', width: 130, ellipsis: { tooltip: true } },
  { title: '整定压力范围', key: 'pressRange', width: 120, render: (r) => pressRangeText(r) },
  { title: '制造许可证号', key: 'licenseNo', width: 130, ellipsis: { tooltip: true } },
  { title: '密封试验压力MPa', key: 'tightnessPress', width: 135 },
  { title: '设备代码', key: 'idNo', width: 120, render: (r) => dash(r.idNo) },
  { title: '设计压力MPa', key: 'designPress', width: 110, render: (r) => dash(r.designPress) }
]

const opsColumn = {
  title: '操作',
  key: 'ops',
  width: 170,
  render(row) {
    return h(NSpace, [
      h(NButton, { size: 'tiny', secondary: true, type: 'primary', onClick: () => openValveEdit(row) }, { default: () => '编辑' }),
      h(NButton, { size: 'tiny', secondary: true, type: 'success', onClick: () => copyValve(row) }, { default: () => '复制' }),
      h(
        NPopconfirm,
        { onPositiveClick: () => deleteValve(row) },
        {
          trigger: () =>
            h(NButton, { size: 'tiny', secondary: true, type: 'error' }, { default: () => '删除' }),
          default: () => '确定删除这台安全阀吗？'
        }
      )
    ])
  }
}

/** 查看模式：无操作列；编辑模式：带操作列 */
const valveColumns = computed(() => (canMaintain.value ? [opsColumn, ...baseColumns] : baseColumns))

/* ---------- 阀门新增 / 编辑（复用现场送检的 ValveFormModal 完整表单） ---------- */
const editShow = ref(false)
const editingValve = ref(null)

function openValveEdit(row) {
  editingValve.value = row && row.id ? row : null
  editShow.value = true
}

async function handleValveConfirm(payload) {
  try {
    if (Array.isArray(payload)) {
      // 新增模式：payload 为阀门数组（可能含提交数量复制出的多台）
      await store.addCommissionValves(props.row.id, payload)
    } else {
      // 编辑模式：payload 为单个阀门对象
      await store.updateCommissionValve({ id: editingValve.value.id, ...payload })
    }
    detail.value = await store.fetchCommissionDetail(props.row.id)
  } catch (e) {
    message.error(e.message || '保存失败')
  }
}

/* 复制：去掉 id/编号/状态，作为新阀门追加 */
async function copyValve(row) {
  const { id, orderCode, checkStatus, ...rest } = row
  try {
    await store.addCommissionValves(props.row.id, [rest])
    message.success('已复制一台安全阀')
    detail.value = await store.fetchCommissionDetail(props.row.id)
  } catch (e) {
    message.error(e.message || '复制失败')
  }
}

async function deleteValve(row) {
  try {
    await store.removeCommissionValve(row.id)
    message.success('已删除')
    detail.value = await store.fetchCommissionDetail(props.row.id)
  } catch (e) {
    message.error(e.message || '删除失败')
  }
}

/* 编辑模式底部：确认提交（关闭弹窗，清单改动已实时保存） */
function confirmSubmit() {
  message.success('提交成功')
  emit('update:show', false)
}

/* ---------- 选择已有安全阀（历次委托单阀门清单汇总） ---------- */
const selectShow = ref(false)
const poolRef = ref(null)

function openValveSelect() {
  selectShow.value = true
}

/* 批量确认：把勾选的阀门添加进当前委托单 */
async function onPoolConfirm(rows) {
  try {
    const payload = rows.map((v) => poolRef.value?.stripValve(v) || v)
    await store.addCommissionValves(props.row.id, payload)
    message.success(`已添加 ${payload.length} 台安全阀`)
    detail.value = await store.fetchCommissionDetail(props.row.id)
  } catch (e) {
    message.error(e.message || '添加失败')
  }
}

/* 复制：再复制一条新数据（行内复制传单条，批量复制传数组），不带 libId 由后端生成新库记录 */
async function onPoolCopyRow(payload) {
  const rows = Array.isArray(payload) ? payload : [payload]
  try {
    await store.addCommissionValves(props.row.id, rows)
    message.success(`已复制 ${rows.length} 台安全阀`)
    detail.value = await store.fetchCommissionDetail(props.row.id)
    poolRef.value?.clearChecked()
    poolRef.value?.reload()
  } catch (e) {
    message.error(e.message || '复制失败')
  }
}

/* ---------- 修改校验编号弹窗（校验编号 / 校验编号2 / SN / sn_code） ---------- */
const codeShow = ref(false)
const codeSaving = ref(false)
const codeForm = reactive({ id: 0, orderCode: '', orderCode2: '', sn: '', snCode: '' })

function openCodeEdit(r) {
  // SN/sn_code 初始值 = 流水号部分（校验编号去掉年月前缀，如 20261101 → 01）；历史数据为空时按校验编号推导
  const serialPart = (r.orderCode || '').length > 6 ? (r.orderCode || '').slice(6) : ''
  Object.assign(codeForm, {
    id: r.woId || 0,
    orderCode: r.orderCode || '',
    orderCode2: r.orderCode2 || '',
    sn: r.sn || serialPart,
    snCode: r.snCode || serialPart
  })
  codeShow.value = true
}

async function saveCode() {
  if (!codeForm.orderCode.trim()) {
    message.warning('校验编号不能为空')
    return
  }
  codeSaving.value = true
  try {
    await store.updateWorkorderCode({
      id: codeForm.id,
      orderCode: codeForm.orderCode.trim(),
      orderCode2: codeForm.orderCode2.trim(),
      sn: codeForm.sn.trim(),
      snCode: codeForm.snCode.trim()
    })
    message.success('校验编号已修改')
    codeShow.value = false
    detail.value = await store.fetchCommissionDetail(props.row.id)
  } catch (e) {
    message.error(e.message || '修改失败')
  } finally {
    codeSaving.value = false
  }
}

/* ---------- 打印标签 ---------- */
function printLabels() {
  const list = valves.value
  if (!list.length) {
    message.warning('该委托单没有安全阀，无法打印标签')
    return
  }
  const esc = (s) =>
    String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]))
  const labels = list
    .map(
      (v) => `
      <div class="label">
        <div class="cust">${esc(view.value?.custName)}</div>
        <table>
          <tr><td class="k">校验编号</td><td>${esc(v.orderCode || '-')}</td><td class="k">出厂编号</td><td>${esc(v.manuNo || '-')}</td></tr>
          <tr><td class="k">安全阀型号</td><td>${esc(v.vavleModel || '-')}</td><td class="k">整定压力</td><td>${esc(v.setPress || '-')} MPa</td></tr>
          <tr><td class="k">公称通径</td><td>${esc(v.diameterNominal || '-')} mm</td><td class="k">校验日期</td><td>${esc(view.value?.signTime || '').slice(0, 10)}</td></tr>
        </table>
      </div>`
    )
    .join('')
  const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>打印标签 - ${esc(view.value?.code)}</title>
    <style>
      body { font-family: 'Microsoft YaHei', sans-serif; padding: 12px; }
      .label { width: 320px; border: 1px solid #333; margin: 0 12px 12px 0; display: inline-block; page-break-inside: avoid; }
      .cust { text-align: center; font-size: 14px; font-weight: bold; padding: 6px 4px; border-bottom: 1px solid #333; }
      table { width: 100%; border-collapse: collapse; font-size: 12px; }
      td { border: 1px solid #333; padding: 4px 6px; }
      td.k { color: #555; width: 68px; background: #f5f5f5; }
      @media print { body { padding: 0 } }
    </style></head><body>${labels}
    <script>window.onload = () => window.print()<\/script></body></html>`
  const w = window.open('', '_blank')
  w.document.write(html)
  w.document.close()
}
</script>

<style lang="scss" scoped>
.section {
  margin-bottom: 18px;

  .sec-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;

    .sec-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .sec-title {
      position: relative;
      padding-left: 10px;
      font-size: 15px;
      font-weight: 600;
      color: #333;

      &::before {
        content: '';
        position: absolute;
        left: 0;
        top: 3px;
        bottom: 3px;
        width: 3px;
        background: #2b6de8;
        border-radius: 2px;
      }
    }
  }
}

.basic-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  row-gap: 20px;
  column-gap: 30px;
  padding: 4px 0 8px;

  .item {
    display: flex;
    align-items: flex-start;

    .label {
      flex: none;
      width: 118px;
      text-align: right;
      color: #888;
      font-size: 13px;
      line-height: 22px;
    }

    .value {
      flex: 1;
      min-width: 0;
      color: #333;
      font-size: 13px;
      line-height: 22px;
      word-break: break-all;
    }
  }
}

.sign-img {
  max-height: 64px;
  max-width: 220px;
  padding: 2px 4px;
  background: #fff;
  border: 1px dashed #dcdfe6;
  border-radius: 4px;
}

.sign-empty {
  color: #bbb;
  font-size: 12px;
}

.ops-cell {
  display: flex;
  align-items: center;
  gap: 6px;
}

.footer-bar {
  display: flex;
  justify-content: flex-start;
  margin-top: 4px;
}

/* ---------- 选择已有安全阀弹窗 ---------- */
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

<template>
  <n-modal
    :show="show"
    preset="card"
    :title="mode === 'edit' ? '编辑' : '查看'"
    style="width: 1200px"
    @update:show="(v) => emit('update:show', v)"
  >
    <template v-if="view">
      <n-spin :show="detailLoading">
        <!-- 基本信息 -->
        <div class="section">
          <div class="sec-head">
            <div class="sec-left">
              <div class="sec-title">基本信息</div>
            </div>
            <n-button text type="primary" size="small" @click="basicOpen = !basicOpen">
              {{ basicOpen ? '收起' : '展开' }}
            </n-button>
          </div>
          <div class="basic-grid">
            <div class="item">
              <span class="label">使用单位:</span>
              <span class="value">{{ view.custName }}</span>
            </div>
            <div class="item">
              <span class="label">使用单位地址:</span>
              <span class="value">{{ view.address }}</span>
            </div>
            <div class="item">
              <span class="label">使用单位联系人:</span>
              <span class="value">{{ view.client }}</span>
            </div>
            <div class="item">
              <span class="label">使用单位联系电话:</span>
              <span class="value">{{ view.clientTel }}</span>
            </div>
            <div v-show="basicOpen" class="item">
              <span class="label">委托单位:</span>
              <span class="value">{{ view.applicant }}</span>
            </div>
            <div v-show="basicOpen" class="item">
              <span class="label">委托单位地址:</span>
              <span class="value">{{ view.custAddress }}</span>
            </div>
            <div v-show="basicOpen" class="item">
              <span class="label">委托人:</span>
              <span class="value">{{ view.contact }}</span>
            </div>
            <div v-show="basicOpen" class="item">
              <span class="label">委托人联系电话:</span>
              <span class="value">{{ view.contactTel }}</span>
            </div>
            <div class="item">
              <span class="label">委托人签名:</span>
              <span class="value">
                <img v-if="signSrc" :src="signSrc" alt="委托人签名" class="sign-img" />
                <span v-else class="sign-empty">未签名</span>
              </span>
            </div>
            <div class="item">
              <span class="label">委托人签名时间:</span>
              <span class="value">{{ view.signTime || '' }}</span>
            </div>
          </div>
        </div>

        <n-alert v-if="view.rejectReason" type="error" :show-icon="false" style="margin-bottom: 16px">
          退回原因：{{ view.rejectReason }}
        </n-alert>

        <!-- 详情信息 -->
        <div class="section">
          <div class="sec-head">
            <div class="sec-left">
              <div class="sec-title">详情信息</div>
              <template v-if="canMaintain">
                <n-button size="small" color="#26448f" @click="openValveEdit({ id: 0 })">新增安全阀</n-button>
                <n-button size="small" color="#18a058" @click="openValveSelect">选择已有安全阀</n-button>
              </template>
            </div>
          </div>
          <div v-show="detailOpen">
            <n-data-table
              size="small"
              :columns="valveColumns"
              :data="valves"
              :row-key="(r) => r.id"
              max-height="360"
              :scroll-x="2000"
            />
          </div>
        </div>

        <div class="footer-bar">
          <!-- 查看：打印标签；编辑：确认提交 -->
          <n-button v-if="mode === 'view'" type="primary" @click="printLabels">打印标签</n-button>
          <n-button v-else type="primary" @click="confirmSubmit">确认提交</n-button>
        </div>
      </n-spin>
    </template>

    <!-- 阀门新增 / 编辑弹窗（与现场送检一致的完整表单） -->
    <ValveFormModal v-model:show="editShow" :valve="editingValve" @confirm="handleValveConfirm" />

    <!-- 修改校验编号弹窗 -->
    <n-modal v-model:show="codeShow" preset="card" title="修改校验编号" style="width: 460px">
      <n-form label-placement="left" label-width="90" size="small" style="padding: 6px 0">
        <n-form-item label="校验编号" required>
          <n-input v-model:value="codeForm.orderCode" placeholder="请输入校验编号" />
        </n-form-item>
        <n-form-item label="校验编号2">
          <n-input v-model:value="codeForm.orderCode2" placeholder="请输入" />
        </n-form-item>
        <n-form-item label="SN">
          <n-input v-model:value="codeForm.sn" placeholder="请输入" />
        </n-form-item>
        <n-form-item label="sn_code">
          <n-input v-model:value="codeForm.snCode" placeholder="请输入" />
        </n-form-item>
      </n-form>
      <template #footer>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <n-button @click="codeShow = false">取消</n-button>
          <n-button type="primary" :loading="codeSaving" @click="saveCode">确定</n-button>
        </div>
      </template>
    </n-modal>

    <!-- 选择已有安全阀弹窗（安全阀数据池） -->
    <ValvePoolModal
      ref="poolRef"
      v-model:show="selectShow"
      :show-ops="canMaintain"
      @confirm="onPoolConfirm"
      @copy="onPoolCopyRow"
    />
  </n-modal>
</template>
