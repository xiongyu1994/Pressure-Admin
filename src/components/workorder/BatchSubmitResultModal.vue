<script setup>
import { ref, reactive, computed } from 'vue'
import { useMessage } from 'naive-ui'
import { useBusinessStore } from '@/store/business'

/** 检验中页面批量提交结果：填写一套公共结果数据，应用到所有勾选的工单 */
const props = defineProps({
  show: { type: Boolean, default: false },
  /** 勾选的工单行数组 */
  rows: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:show'])

const message = useMessage()
const store = useBusinessStore()

const formRef = ref(null)

function emptyForm() {
  return {
    testMedia: '氮气',
    testTemprature: '常温',
    parity: '离线校验',
    visualExamination: '合格',
    disassemblyInspection: '合格',
    note: '无',
    pressureGaugeNum: '',
    pressureGaugeLifespan: null,
    pressureGaugeNum2: '',
    pressureGaugeLifespan2: null,
    pressureGaugeAccuracy: '0.4级',
    pressureGaugeRange: '',
    deviceModel: 'SFA-150',
    deviceSn: '',
    testStatus: '合格',
    inspector: store.inspectors[0],
    inspectorTime: Date.now()
  }
}

const form = reactive(emptyForm())

const rules = {
  deviceSn: { required: true, message: '请输入校验设备编号', trigger: 'blur' }
}

const submitting = ref(false)

const codes = computed(() => props.rows.map((r) => r.orderCode).join('、'))

function fmtDateTime(ts) {
  if (!ts) return ''
  const d = new Date(ts)
  const p = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
}

function fmtDate(ts) {
  if (!ts) return ''
  const d = new Date(ts)
  const p = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

async function handleSubmit() {
  try {
    await formRef.value?.validate()
  } catch {
    return
  }
  submitting.value = true
  try {
    const common = {
      ...form,
      pressureGaugeLifespan: fmtDate(form.pressureGaugeLifespan),
      pressureGaugeLifespan2: fmtDate(form.pressureGaugeLifespan2),
      inspectorTime: fmtDateTime(form.inspectorTime)
    }
    await store.batchSubmitResults(
      props.rows.map((r) => r.id),
      common
    )
    emit('update:show', false)
    message.success(`已批量提交 ${props.rows.length} 条检验结果，进入待审核`)
  } catch (e) {
    message.error(e.message || '批量提交失败')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    title="批量提交检验结果"
    style="width: 860px"
    @update:show="(v) => emit('update:show', v)"
  >
    <n-alert type="info" :show-icon="false" style="margin-bottom: 14px">
      将为以下 {{ rows.length }} 条工单提交相同的检验结果：{{ codes }}
    </n-alert>

    <n-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-placement="left"
      label-width="120"
      size="small"
    >
      <n-divider title-placement="left">基本信息</n-divider>
      <n-grid :cols="3" :x-gap="12">
        <n-form-item-gi label="校验介质">
          <n-select
            v-model:value="form.testMedia"
            :options="['氮气', '空气', '水'].map((v) => ({ label: v, value: v }))"
          />
        </n-form-item-gi>
        <n-form-item-gi label="校验介质温度">
          <n-select
            v-model:value="form.testTemprature"
            :options="['常温', '高温'].map((v) => ({ label: v, value: v }))"
          />
        </n-form-item-gi>
        <n-form-item-gi label="校验方式">
          <n-select
            v-model:value="form.parity"
            :options="['离线校验', '在线校验'].map((v) => ({ label: v, value: v }))"
          />
        </n-form-item-gi>
        <n-form-item-gi label="外观检查">
          <n-select
            v-model:value="form.visualExamination"
            :options="['合格', '不合格'].map((v) => ({ label: v, value: v }))"
          />
        </n-form-item-gi>
        <n-form-item-gi label="拆卸检查">
          <n-select
            v-model:value="form.disassemblyInspection"
            :options="['合格', '不合格'].map((v) => ({ label: v, value: v }))"
          />
        </n-form-item-gi>
        <n-form-item-gi label="备注">
          <n-input v-model:value="form.note" />
        </n-form-item-gi>
      </n-grid>

      <n-divider title-placement="left">压力表信息</n-divider>
      <n-grid :cols="3" :x-gap="12">
        <n-form-item-gi label="压力表编号">
          <n-input v-model:value="form.pressureGaugeNum" placeholder="请输入" />
        </n-form-item-gi>
        <n-form-item-gi label="压力表有效期">
          <n-date-picker v-model:value="form.pressureGaugeLifespan" type="date" style="width: 100%" />
        </n-form-item-gi>
        <n-form-item-gi label="压力表精度">
          <n-select
            v-model:value="form.pressureGaugeAccuracy"
            :options="['0.25级', '0.4级', '1.6级'].map((v) => ({ label: v, value: v }))"
          />
        </n-form-item-gi>
        <n-form-item-gi label="压力表量程MPa">
          <n-input v-model:value="form.pressureGaugeRange" placeholder="如 0-2.5" />
        </n-form-item-gi>
        <n-form-item-gi label="压力表2编号">
          <n-input v-model:value="form.pressureGaugeNum2" placeholder="选填" />
        </n-form-item-gi>
        <n-form-item-gi label="压力表2有效期">
          <n-date-picker v-model:value="form.pressureGaugeLifespan2" type="date" style="width: 100%" clearable />
        </n-form-item-gi>
      </n-grid>

      <n-divider title-placement="left">校验设备与结果</n-divider>
      <n-grid :cols="3" :x-gap="12">
        <n-form-item-gi label="校验设备型号">
          <n-input v-model:value="form.deviceModel" />
        </n-form-item-gi>
        <n-form-item-gi label="校验设备编号" path="deviceSn">
          <n-input v-model:value="form.deviceSn" placeholder="请输入" />
        </n-form-item-gi>
        <n-form-item-gi label="校验结果">
          <n-select
            v-model:value="form.testStatus"
            :options="store.testStatusOptions.map((v) => ({ label: v, value: v }))"
          />
        </n-form-item-gi>
        <n-form-item-gi label="校验员" required>
          <n-select
            v-model:value="form.inspector"
            :options="store.inspectors.map((v) => ({ label: v, value: v }))"
          />
        </n-form-item-gi>
        <n-form-item-gi label="校验日期" required>
          <n-date-picker v-model:value="form.inspectorTime" type="datetime" style="width: 100%" />
        </n-form-item-gi>
      </n-grid>
    </n-form>

    <template #footer>
      <div style="display: flex; justify-content: flex-end; gap: 10px">
        <n-button @click="emit('update:show', false)">取消</n-button>
        <n-button type="primary" :loading="submitting" @click="handleSubmit">确认提交</n-button>
      </div>
    </template>
  </n-modal>
</template>
