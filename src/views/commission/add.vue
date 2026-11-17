<script setup>
import { ref, reactive, computed, h, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useMessage } from 'naive-ui'
import { NButton, NSpace, NPopconfirm } from 'naive-ui'
import { useBusinessStore } from '@/store/business'
import ValveFormModal from '@/components/commission/ValveFormModal.vue'
import ValvePoolModal from '@/components/commission/ValvePoolModal.vue'
const router = useRouter()
const message = useMessage()
const store = useBusinessStore()

/* 本页需要：使用单位下拉 + 安全阀库（从库中选择阀门） */
onMounted(() => {
  store.fetchUnits().catch(() => {})
  store.fetchValves().catch(() => {})
})

const formRef = ref(null)
const submitting = ref(false)

const form = reactive({
  custId: null,
  address: '',
  client: '',
  clientTel: '',
  applicant: '',
  custAddress: '',
  contact: '',
  contactTel: '',
  examName: store.examUnits[0],
  planReceiverTime: null,
  deliverer: '',
  deliveryTime: Date.now()
})

const rules = {
  custId: { required: true, message: '请选择使用单位', trigger: ['change','blur'],type:'number'},
  address: { required: true, message: '请输入使用单位地址', trigger: 'blur' },
  client: { required: true, message: '请输入使用单位联系人', trigger: 'blur' },
  clientTel: { required: true, message: '请输入使用单位联系电话', trigger: 'blur' },
  examName: { required: true, message: '请选择检验单位', trigger: 'change' }
}

/* 选择使用单位后自动带出委托单位信息 */
function onCustChange(v) {
  const u = store.units.find((x) => x.id === v)
  console.log("u",u)
  if (u) {
    if (!form.applicant) form.applicant = u.name
    if (!form.address) form.address = u.address || ''
    if (!form.client) form.client = u.client || ''
    if (!form.clientTel) form.clientTel = u.clientTel || ''
    if (!form.custAddress) form.custAddress = u.address || ''
    if (!form.contact) form.contact = u.client || ''
    if (!form.contactTel) form.contactTel = u.clientTel || ''
  }
}

/* ---------------- 安全阀清单 ---------------- */
const valves = ref([])

const valveColumns = [
  {
    title: '操作',
    key: 'actions',
    width: 130,
    render(_, i) {
      return h(NSpace, { size: 4 }, {
        default: () => [
          h(
            NButton,
            { size: 'tiny', type: 'primary', quaternary: true, onClick: () => copyValve(i) },
            { default: () => '复制' }
          ),
          h(
            NPopconfirm,
            { onPositiveClick: () => removeValve(i) },
            {
              trigger: () =>
                h(NButton, { size: 'tiny', type: 'error', quaternary: true }, { default: () => '删除' }),
              default: () => '确定删除该安全阀吗？'
            }
          )
        ]
      })
    }
  },
  { title: '#', key: 'idx', width: 50, render: (_, i) => i + 1 },
  { title: '安全阀型号', key: 'vavleModel', width: 140 },
  { title: '公称通径mm', key: 'diameterNominal', width: 95 },
  { title: '出厂编号', key: 'manuNo', width: 100 },
  { title: '工作压力MPa', key: 'workingPress', width: 105 },
  { title: '要求整定压力MPa', key: 'setPress', width: 125 },
  { title: '安装位置', key: 'tagNo', width: 90 },
  { title: '工作介质', key: 'workingMedia', width: 85 },
  { title: '制造单位', key: 'manufacturer', ellipsis: { tooltip: true } },
  { title: '整定压力范围MPa', width: 130, render: (r) => `${r.pressClassRangeMin ?? '-'} - ${r.pressClassRangeMax ?? '-'}` },
  { title: '制造许可证号', key: 'licenseNo', width: 130 },
  { title: '密封试验压力MPa', key: 'tightnessPress', width: 125 },
  { title: '设备代码', key: 'idNo', width: 110 },
  { title: '设计压力MPa', key: 'designPress', width: 100 }
]

function addFromLibrary(picked) {
  // 行内复制传单条，批量复制传数组；剔除池数据的业务字段，保留字段供后端入库
  // 复制（copy 事件）不带 libId → 后端生成新的安全阀库记录；确认提交带 libId → 复用原库记录
  const arr = Array.isArray(picked) ? picked : [picked]
  const rows = arr.map((v) => {
    const {
      id: _i,
      custName: _cn,
      checkCode: _cc,
      checkStatus: _cs,
      createTime: _ct,
      updateTime: _ut,
      ...fields
    } = v
    return fields
  })
  valves.value.push(...rows)
}

function addNewValves(list) {
  valves.value.push(...list)
}

function copyValve(i) {
  // 复制 = 再复制一条数据：去掉 libId，委托单提交时后端会为副本生成新的安全阀库记录
  const { libId: _lib, ...rest } = valves.value[i]
  valves.value.splice(i + 1, 0, rest)
}

function removeValve(i) {
  valves.value.splice(i, 1)
}

/* ---------------- 提交 ---------------- */
const pickerShow = ref(false)
const valveFormShow = ref(false)

async function handleSubmit() {
  try {
    await formRef.value?.validate()
  } catch(err) {
    console.log("errrr",err)
    return
  }
  if (!valves.value.length) {
    message.warning('请至少添加一台安全阀')
    return
  }
  submitting.value = true
  try {
    const res = await store.createCommission({
      ...form,
      planReceiverTime: form.planReceiverTime ? new Date(form.planReceiverTime).toLocaleString('zh-CN', { hour12: false }) : '',
      deliveryTime: form.deliveryTime ? new Date(form.deliveryTime).toLocaleString('zh-CN', { hour12: false }) : '',
      valves: valves.value
    })
    message.success(res?.code ? `委托单 ${res.code} 提交成功，等待手机签名` : '委托单提交成功，等待手机签名')
    router.push('/commission/list')
  } catch (e) {
    message.error(e.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

const unitOptions = computed(() => store.units.map((u) => ({ label: u.name, value: u.id })))
</script>

<template>
  <div class="page-card">
    <n-card embedded title="基本信息" size="small" style="margin-bottom: 14px">
      <template #header-extra>
        <n-space align="center" :size="10">
          <n-text depth="3" style="font-size: 12px">
            委托单号由系统自动生成：WTD + 年月 + 递增序号（如 WTD20261101）
          </n-text>
          <n-tag type="info" size="small" :bordered="false">新增委托单</n-tag>
        </n-space>
      </template>
      <n-form ref="formRef" :model="form" :rules="rules" label-placement="left" label-width="130" size="small">
        <n-grid :cols="3" :x-gap="14">
          <n-form-item-gi label="使用单位" path="custId">
            <n-select
              v-model:value="form.custId"
              :options="unitOptions"
              filterable
              placeholder="请选择使用单位"
              @update:value="onCustChange"
            />
          </n-form-item-gi>
          <n-form-item-gi label="使用单位地址" path="address">
            <n-input v-model:value="form.address" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="使用单位联系人" path="client">
            <n-input v-model:value="form.client" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="使用单位联系电话" path="clientTel">
            <n-input v-model:value="form.clientTel" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="委托单位">
            <n-input v-model:value="form.applicant" placeholder="默认同使用单位" />
          </n-form-item-gi>
          <n-form-item-gi label="委托单位地址">
            <n-input v-model:value="form.custAddress" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="委托人">
            <n-input v-model:value="form.contact" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="委托人联系电话">
            <n-input v-model:value="form.contactTel" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="检验单位" path="examName">
            <n-select v-model:value="form.examName" :options="store.examUnits.map((v) => ({ label: v, value: v }))" />
          </n-form-item-gi>
          <n-form-item-gi label="预约取阀时间">
            <n-date-picker v-model:value="form.planReceiverTime" type="datetime" style="width: 100%" clearable />
          </n-form-item-gi>
          <n-form-item-gi label="送阀人">
            <n-input v-model:value="form.deliverer" placeholder="请输入" />
          </n-form-item-gi>
          <n-form-item-gi label="送阀时间">
            <n-date-picker v-model:value="form.deliveryTime" type="datetime" style="width: 100%" />
          </n-form-item-gi>
        </n-grid>
      </n-form>
    </n-card>

    <div class="valve-head">
      <span class="valve-title">安全阀清单（{{ valves.length }} 台）</span>
      <n-space>
        <n-button size="small" type="info" v-perm="'commission:add:valve'" @click="valveFormShow = true">新增安全阀</n-button>
        <n-button size="small" type="primary" v-perm="'commission:add:valve'" @click="pickerShow = true">选择已有阀门</n-button>
      </n-space>
    </div>
    <n-data-table
      size="small"
      :columns="valveColumns"
      :data="valves"
      :scroll-x="1500"
      max-height="320"
      :pagination="false"
    />

    <div class="submit-bar">
      <n-button type="primary" size="large" v-perm="'commission:add:submit'" :loading="submitting" @click="handleSubmit">确认提交</n-button>
    </div>

    <ValvePoolModal v-model:show="pickerShow" @confirm="addFromLibrary" @copy="addFromLibrary" />
    <ValveFormModal v-model:show="valveFormShow" @confirm="addNewValves" />
  </div>
</template>

<style lang="scss" scoped>
.valve-head {
  display: flex;
  align-items: center;
  margin-bottom: 10px;

  .valve-title {
    font-size: 14px;
    font-weight: 600;
    padding-left: 8px;
    border-left: 3px solid $primary;
  }
}

.submit-bar {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}
</style>
