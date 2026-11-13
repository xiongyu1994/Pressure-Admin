<script setup>
import { ref, reactive, watch, computed } from 'vue'
import { useMessage } from 'naive-ui'
import { useBusinessStore } from '@/store/business'

const props = defineProps({
  show: { type: Boolean, default: false },
  row: { type: Object, default: null }
})
const emit = defineEmits(['update:show'])

const message = useMessage()
const store = useBusinessStore()

const formRef = ref(null)

function emptyForm() {
  return {
    orderCode: '',
    tightnessPress: null,
    setPress: null,
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
    testingPressure1: null,
    testingPressure2: null,
    testingPressure3: null,
    testStatus: '合格',
    inspector: store.inspectors[0],
    inspectorTime: Date.now()
  }
}

const form = reactive(emptyForm())

watch(
  () => props.row,
  (row) => {
    Object.assign(form, emptyForm())
    if (row) {
      form.orderCode = row.orderCode
      form.tightnessPress = row.tightnessPress
      form.setPress = row.setPress
    }
  }
)

const rules = {
  deviceSn: { required: true, message: '请输入校验设备编号', trigger: 'blur' },
  testingPressure1: {
    required: true,
    type: 'number',
    message: '请输入整定压力1',
    trigger: ['blur', 'change']
  }
}

const submitting = ref(false)

async function handleSubmit() {
  try {
    await formRef.value?.validate()
  } catch {
    return
  }
  submitting.value = true
  setTimeout(() => {
    store.submitResult(props.row.id, {
      ...form,
      inspectorTime: new Date(form.inspectorTime).toLocaleString('zh-CN', { hour12: false })
    })
    submitting.value = false
    emit('update:show', false)
    message.success(`「${form.orderCode}」检验结果已提交，进入待打印`)
  }, 300)
}
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    title="提交检验结果"
    style="width: 860px"
    @update:show="(v) => emit('update:show', v)"
  >
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
        <n-form-item-gi label="校验编号">
          <n-input v-model:value="form.orderCode" disabled />
        </n-form-item-gi>
        <n-form-item-gi label="要求密封压力">
          <n-input-number v-model:value="form.tightnessPress" style="width: 100%" :step="0.1" />
        </n-form-item-gi>
        <n-form-item-gi label="要求整定压力MPa">
          <n-input-number v-model:value="form.setPress" style="width: 100%" :step="0.1" />
        </n-form-item-gi>
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

      <n-divider title-placement="left">校验设备与整定压力</n-divider>
      <n-grid :cols="3" :x-gap="12">
        <n-form-item-gi label="校验设备型号">
          <n-input v-model:value="form.deviceModel" />
        </n-form-item-gi>
        <n-form-item-gi label="校验设备编号" path="deviceSn">
          <n-input v-model:value="form.deviceSn" placeholder="请输入" />
        </n-form-item-gi>
        <n-form-item-gi label="整定压力1" path="testingPressure1">
          <n-input-number v-model:value="form.testingPressure1" style="width: 100%" :step="0.01" />
        </n-form-item-gi>
        <n-form-item-gi label="整定压力2">
          <n-input-number v-model:value="form.testingPressure2" style="width: 100%" :step="0.01" />
        </n-form-item-gi>
        <n-form-item-gi label="整定压力3">
          <n-input-number v-model:value="form.testingPressure3" style="width: 100%" :step="0.01" />
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
