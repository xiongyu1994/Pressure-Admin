<script setup>
import { ref, nextTick, watch } from 'vue'
import ValveForm from './ValveForm.vue'

/** 安全阀表单弹窗：表单逻辑抽到 ValveForm.vue，这里只负责弹窗壳 */
const props = defineProps({
  show: { type: Boolean, default: false },
  /** 传入含 id 的阀门对象 = 编辑模式（回填数据、隐藏提交数量）；不传/为空 = 新增模式 */
  valve: { type: Object, default: null }
})
const emit = defineEmits(['update:show', 'confirm'])

const formRef = ref(null)

/** 每次打开弹窗时初始化表单（编辑回填 / 新增重置） */
watch(
  () => props.show,
  async (show) => {
    if (!show) return
    await nextTick()
    formRef.value?.init(props.valve)
  }
)

/** 提交成功：抛给父组件并关闭弹窗 */
function onConfirm(payload) {
  emit('confirm', payload)
  emit('update:show', false)
}
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    :title="valve && valve.id ? '编辑安全阀' : '新增安全阀'"
    style="width: 1200px"
    @update:show="(v) => emit('update:show', v)"
  >
    <ValveForm ref="formRef" :valve="valve" @confirm="onConfirm" />
  </n-modal>
</template>
