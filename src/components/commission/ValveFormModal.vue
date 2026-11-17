<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useMessage } from 'naive-ui'
import { useBusinessStore } from '@/store/business'

const props = defineProps({
  show: { type: Boolean, default: false },
  /** 传入含 id 的阀门对象 = 编辑模式（回填数据、隐藏提交数量）；不传/为空 = 新增模式 */
  valve: { type: Object, default: null }
})
const emit = defineEmits(['update:show', 'confirm'])

const message = useMessage()
const store = useBusinessStore()
const formRef = ref(null)
const submitting = ref(false)

const isEdit = computed(() => !!(props.valve && props.valve.id))

function emptyForm() {
  return {
    equipType: '安全阀',
    workingMedia: '蒸汽',
    setPress: null,
    workingPress: null,
    tagNo: '',
    idNo: '',
    designPress: null,
    workingTemp: '常温',
    protectedEquipType: '压力容器',
    vavleModel: '',
    manufacturer: '',
    vavleType: '弹簧全启式安全阀',
    diameterNominal: null,
    licenseNo: '',
    seatSize: null,
    pressNominal: 1.6,
    pressClassRangeMin: null,
    pressClassRangeMax: null,
    manuDate: null,
    manuNo: '',
    tightnessPress: null,
    pressCorrection: '无',
    isNew: '是',
    isRemovedCleaning: '是',
    isComplete: '是',
    isNameplate: '是',
    parity: '离线校验',
    checkMethod: null,
    count: 1
  }
}

/** 在线校验方法（TSG 92-2026 三种在线校验方法） */
const ONLINE_CHECK_METHODS = [
  '采用系统压力源升压开启进行校验',
  '采用其他压力源升压开启进行校验',
  '采用在线校验装置辅助开启进行校验'
]

const form = reactive(emptyForm())

const rules = {
  setPress: { required: true, type: 'number', message: '请输入要求整定压力', trigger: ['blur', 'change'] },
  diameterNominal: { required: true, type: 'number', message: '请输入公称通径', trigger: ['blur', 'change'] },
  tightnessPress: { required: true, type: 'number', message: '请输入密封试验压力', trigger: ['blur', 'change'] },
  protectedEquipType: { required: true, message: '请选择被保护设备种类', trigger: 'change' },
  equipType: { required: true, message: '请选择设备类型', trigger: 'change' }
}

/** 打开时：编辑模式回填数据（数字字段转数值、日期转时间戳），新增模式重置 */
watch(
  () => props.show,
  (show) => {
    if (!show) return
    Object.assign(form, emptyForm())
    const v = props.valve
    if (isEdit.value && v) {
      const num = (s) => {
        const n = parseFloat(s)
        return Number.isFinite(n) ? n : null
      }
      Object.assign(form, {
        equipType: v.equipType || '安全阀',
        workingMedia: v.workingMedia || '蒸汽',
        setPress: num(v.setPress),
        workingPress: num(v.workingPress),
        tagNo: v.tagNo || '',
        idNo: v.idNo || '',
        designPress: num(v.designPress),
        workingTemp: v.workingTemp || '常温',
        protectedEquipType: v.protectedEquipType || '压力容器',
        vavleModel: v.vavleModel || '',
        manufacturer: v.manufacturer || '',
        vavleType: v.vavleType || '弹簧全启式安全阀',
        diameterNominal: num(v.diameterNominal),
        licenseNo: v.licenseNo || '',
        seatSize: num(v.seatSize),
        pressNominal: num(v.pressNominal),
        pressClassRangeMin: num(v.pressClassRangeMin),
        pressClassRangeMax: num(v.pressClassRangeMax),
        manuDate: v.manuDate ? new Date(v.manuDate).getTime() : null,
        manuNo: v.manuNo || '',
        tightnessPress: num(v.tightnessPress),
        pressCorrection: v.pressCorrection || '无',
        isNew: v.isNew || '是',
        isRemovedCleaning: v.isRemovedCleaning || '是',
        isComplete: v.isComplete || '是',
        isNameplate: v.isNameplate || '是',
        parity: v.parity || '离线校验',
        checkMethod: v.checkMethod || null,
        count: 1
      })
    }
  }
)

function buildValve() {
  return {
    ...form,
    // 离线校验没有方法字段
    checkMethod: form.parity === '在线校验' ? form.checkMethod : null,
    manuDate: form.manuDate ? new Date(form.manuDate).toISOString().slice(0, 10) : ''
  }
}

/** 切回离线校验时清空方法 */
function onParityChange(v) {
  if (v !== '在线校验') form.checkMethod = null
}

/** 清空内容：全部字段清成空白（下拉恢复「请选择」、文本框全空），不回默认值；
 *  校验方式（离线/在线）保持当前选择不清空；提交数量保持 1 */
function clearedForm() {
  const base = emptyForm()
  const out = {}
  Object.keys(base).forEach((k) => {
    if (k === 'count') {
      out[k] = 1
    } else if (typeof base[k] === 'string') {
      out[k] = ''
    } else {
      out[k] = null
    }
  })
  out.parity = form.parity // 校验方式保留当前选择
  return out
}

/** 清空内容：恢复表单空白状态 */
function handleClear() {
  Object.assign(form, clearedForm())
  formRef.value?.restoreValidation()
}

/** 连续添加：功能待确认，暂不处理 */
function handleKeepAdd() {
  // TODO: 连续添加功能待确认后实现
}

async function handleSubmit() {
  try {
    await formRef.value?.validate()
  } catch {
    return
  }
  submitting.value = true
  try {
    if (isEdit.value) {
      emit('confirm', buildValve())
      message.success('保存成功')
    } else {
      // 新增模式：立即写入安全阀库（已有阀门列表即时可查），返回记录带 libId
      const count = Math.max(1, Math.min(form.count || 1, 20))
      const valves = Array.from({ length: count }, (_, i) => buildValve())
      const saved = await store.addLibValves(valves)
      emit('confirm', saved)
      message.success(`已添加 ${saved.length} 台安全阀，并同步至已有阀门库`)
    }
    emit('update:show', false)
  } catch (e) {
    message.error(e.message || '保存失败')
  } finally {
    submitting.value = false
  }
}

defineExpose({
  reset() {
    Object.assign(form, emptyForm())
  }
})
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    :title="isEdit ? '编辑安全阀' : '新增安全阀'"
    style="width: 1200px"
    @update:show="(v) => emit('update:show', v)"
  >
    <n-form ref="formRef" :model="form" :rules="rules" label-placement="left" label-width="130" size="small">
      <n-divider title-placement="left">基本信息</n-divider>
      <n-grid :cols="3" :x-gap="12">
        <n-form-item-gi label="设备类型" path="equipType">
          <n-select v-model:value="form.equipType" :options="['安全阀', '爆破片装置'].map((v) => ({ label: v, value: v }))" />
        </n-form-item-gi>
        <n-form-item-gi label="被保护设备种类" path="protectedEquipType">
          <n-select v-model:value="form.protectedEquipType" :options="['锅炉', '压力容器', '压力管道'].map((v) => ({ label: v, value: v }))" />
        </n-form-item-gi>
        <n-form-item-gi label="工作介质">
          <n-select v-model:value="form.workingMedia" :options="['蒸汽', '压缩空气', '氮气', '水', '天然气'].map((v) => ({ label: v, value: v }))" />
        </n-form-item-gi>
        <n-form-item-gi label="要求整定压力MPa" path="setPress">
          <n-input-number v-model:value="form.setPress" style="width: 100%" :step="0.1" :min="0" />
        </n-form-item-gi>
        <n-form-item-gi label="工作压力MPa">
          <n-input-number v-model:value="form.workingPress" style="width: 100%" :step="0.1" :min="0" />
        </n-form-item-gi>
        <n-form-item-gi label="设计压力MPa">
          <n-input-number v-model:value="form.designPress" style="width: 100%" :step="0.1" :min="0" />
        </n-form-item-gi>
        <n-form-item-gi label="密封试验压力MPa" path="tightnessPress">
          <n-input-number v-model:value="form.tightnessPress" style="width: 100%" :step="0.1" :min="0" />
        </n-form-item-gi>
        <n-form-item-gi label="公称通径mm" path="diameterNominal">
          <n-input-number v-model:value="form.diameterNominal" style="width: 100%" />
        </n-form-item-gi>
        <n-form-item-gi label="流道直径mm">
          <n-input-number v-model:value="form.seatSize" style="width: 100%" />
        </n-form-item-gi>
        <n-form-item-gi label="公称压力MPa">
          <n-input-number v-model:value="form.pressNominal" style="width: 100%" :step="0.1" />
        </n-form-item-gi>
        <n-form-item-gi label="压力级别范围起">
          <n-input-number v-model:value="form.pressClassRangeMin" style="width: 100%" :step="0.1" />
        </n-form-item-gi>
        <n-form-item-gi label="压力级别范围止">
          <n-input-number v-model:value="form.pressClassRangeMax" style="width: 100%" :step="0.1" />
        </n-form-item-gi>
        <n-form-item-gi label="工作温度">
          <n-input v-model:value="form.workingTemp" placeholder="如 常温 / 180℃" />
        </n-form-item-gi>
        <n-form-item-gi label="安装位置">
          <n-input v-model:value="form.tagNo" placeholder="请输入" />
        </n-form-item-gi>
        <n-form-item-gi label="设备代码">
          <n-input v-model:value="form.idNo" placeholder="请输入" />
        </n-form-item-gi>
      </n-grid>

      <n-divider title-placement="left">安全阀信息</n-divider>
      <n-grid :cols="3" :x-gap="12">
        <n-form-item-gi label="安全阀型号">
          <n-input v-model:value="form.vavleModel" placeholder="如 A48Y-16C DN50" />
        </n-form-item-gi>
        <n-form-item-gi label="安全阀类型">
          <n-select v-model:value="form.vavleType" :options="['弹簧全启式安全阀', '弹簧微启式安全阀', '先导式安全阀', '全启式安全阀'].map((v) => ({ label: v, value: v }))" />
        </n-form-item-gi>
        <n-form-item-gi label="制造单位">
          <n-input v-model:value="form.manufacturer" placeholder="请输入" />
        </n-form-item-gi>
        <n-form-item-gi label="制造许可证号">
          <n-input v-model:value="form.licenseNo" placeholder="请输入" />
        </n-form-item-gi>
        <n-form-item-gi label="出厂编号">
          <n-input v-model:value="form.manuNo" placeholder="请输入" />
        </n-form-item-gi>
        <n-form-item-gi label="出厂日期">
          <n-date-picker v-model:value="form.manuDate" type="date" style="width: 100%" />
        </n-form-item-gi>
        <n-form-item-gi label="背压修正">
          <n-input v-model:value="form.pressCorrection" />
        </n-form-item-gi>
        <n-form-item-gi label="是否新阀">
          <n-select v-model:value="form.isNew" :options="['是', '否'].map((v) => ({ label: v, value: v }))" />
        </n-form-item-gi>
        <n-form-item-gi label="旧阀是否拆卸清洗">
          <n-select v-model:value="form.isRemovedCleaning" :options="['是', '否'].map((v) => ({ label: v, value: v }))" />
        </n-form-item-gi>
        <n-form-item-gi label="阀体及零部件完整">
          <n-select v-model:value="form.isComplete" :options="['是', '否'].map((v) => ({ label: v, value: v }))" />
        </n-form-item-gi>
        <n-form-item-gi label="是否有金属铭牌">
          <n-select v-model:value="form.isNameplate" :options="['是', '否'].map((v) => ({ label: v, value: v }))" />
        </n-form-item-gi>
        <n-form-item-gi label="校验方式">
          <n-radio-group v-model:value="form.parity" @update:value="onParityChange">
            <n-space align="center" :size="24">
              <n-radio value="离线校验">离线校验</n-radio>
              <n-radio value="在线校验">在线校验</n-radio>
            </n-space>
          </n-radio-group>
        </n-form-item-gi>
        <n-form-item-gi v-if="form.parity === '在线校验'" label="方法">
          <n-select
            v-model:value="form.checkMethod"
            placeholder="请选择"
            clearable
            :options="ONLINE_CHECK_METHODS.map((v) => ({ label: v, value: v }))"
          />
        </n-form-item-gi>
      </n-grid>
    </n-form>

    <template #footer>
      <div class="footer-wrap">
        <div v-if="!isEdit" class="count-row">
          <span class="count-label">提交数量</span>
          <n-input-number v-model:value="form.count" :min="1" :max="20" style="width: 200px" />
        </div>
        <div class="btn-row">
          <n-button v-if="!isEdit" type="primary" ghost @click="handleKeepAdd">连续添加</n-button>
          <n-button type="primary" :loading="submitting" @click="handleSubmit">确认提交</n-button>
          <n-button @click="handleClear">清空内容</n-button>
        </div>
      </div>
    </template>
  </n-modal>
</template>

<style lang="scss" scoped>
.footer-wrap {
  .count-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 16px;

    .count-label {
      font-size: 14px;
      color: #333;
    }
  }

  .btn-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
  }
}
</style>
