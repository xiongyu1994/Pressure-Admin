import { defineStore } from 'pinia'
import request from '@/utils/request'

/** 委托单状态（文案与参考站一致） */
export const COMMISSION_STATUS = {
  sign: { label: '等待签名', type: 'warning', code: 10 },
  pending: { label: '等待预检', type: 'info', code: 20 },
  approved: { label: '等待检验', type: 'success', code: 40 },
  rejected: { label: '已退回', type: 'error', code: 30 }
}

/** 委托单状态下拉（含参考站的完整取值） */
export const COMMISSION_STATUS_OPTIONS = [
  { label: '等待签名', value: 'sign' },
  { label: '等待预检', value: 'pending' },
  { label: '已退回', value: 'rejected' },
  { label: '等待检验', value: 'approved' },
  { label: '检验中', value: 'inspection' },
  { label: '检验完成', value: 'done' },
  { label: '退回预检', value: 'rejectPre' }
]

/** 检验工单状态 */
export const WORKORDER_STATUS = {
  rejected: { label: '校验退回', type: 'error', code: 0 },
  wait: { label: '待检验', type: 'warning', code: 1 },
  inspection: { label: '检验中', type: 'info', code: 2 },
  audit: { label: '待审核', type: 'warning', code: 3 },
  approval: { label: '待审批', type: 'info', code: 4 },
  print: { label: '待打印', type: 'default', code: 5 },
  done: { label: '已完成', type: 'success', code: 6 }
}

/** 检验状态下拉（已完成页面） */
export const TEST_STATUS_FILTER = [
  { label: '待检验', value: 'none' },
  { label: '合格', value: 'pass' },
  { label: '不合格', value: 'fail' }
]

/** 检验结果单选项（提交结果弹窗） */
export const TEST_RESULT_RADIOS = [
  { label: '该安全阀经检验合格', value: '合格' },
  { label: '该安全阀经检验不合格，不能投入使用', value: '不合格' }
]

/** 执行标准（可多选） */
export const STANDARDS = ['TSG 92-2026', 'GB/T12241-2021', 'GB/T12243-2021', 'TSGZF001-2006及修改单']

/** 维护检修情况（批量提交结果用） */
export const MAINTENANCE_OPTIONS = ['解体、清洗、除锈', '请在投入运行时先脱脂', '经解体、清洗、检查合格', '其他']

/** 校验方式 */
export const PARITY_OPTIONS = ['离线校验', '在线校验']

/** 检验人员（与参考站一致） */
export const INSPECTORS = ['周宽', '周红超']

/** 账号身份（参考站 adminType） */
export const ADMIN_TYPES = ['管理员', '录入人员', '检验人员', '审核员', '打印人员', '录入审核']

export const TEST_STATUS_OPTIONS = ['合格', '维修后合格', '不合格']

/** 检验单位（固定字典） */
const EXAM_UNITS = ['武汉翼华技术设备有限公司', '湖北欣瑞祥油品管理有限公司孝感分公司']

/* ------------------------------ Store ------------------------------ */

export const useBusinessStore = defineStore('business', {
  state: () => ({
    /* 字典 */
    units: [],
    examUnits: EXAM_UNITS,
    inspectors: INSPECTORS,
    testStatusOptions: TEST_STATUS_OPTIONS,

    /* 业务数据（全部来自后端接口） */
    commissions: [],
    workorders: [],
    valveLibrary: [],

    /* 安全阀列表「批量送检」暂存：跳转到现场送检页时预填阀门清单 */
    batchInspectValves: [],

    /* 工作台（后端统计） */
    stats: { checkedTotal: 0, unchecked: 0, waitTotal: 0, commissionTotal: 0, customerTotal: 0 },
    inspectorRanking: [],
    unitRanking: [],
    chartData: { months: [], audited: [], unaudited: [] },
    sites: [],

    /* 各数据源独立的加载状态：页面按需调用对应接口，不做全局预加载 */
    loading: {
      units: false,
      commissions: false,
      workorders: false,
      valves: false,
      stats: false,
      ranks: false
    }
  }),
  actions: {
    reset() {
      this.$patch({
        units: [],
        commissions: [],
        workorders: [],
        valveLibrary: [],
        stats: { checkedTotal: 0, unchecked: 0, waitTotal: 0, commissionTotal: 0, customerTotal: 0 },
        inspectorRanking: [],
        unitRanking: [],
        chartData: { months: [], audited: [], unaudited: [] },
        sites: [],
        loading: {
          units: false,
          commissions: false,
          workorders: false,
          valves: false,
          stats: false,
          ranks: false
        }
      })
    },

    /* ---------- 数据加载（按页面需要调用，不再全局预加载） ---------- */
    async fetchUnits() {
      this.loading.units = true
      try {
        const data = await request.get('/unit/list', { limit: 500 })
        this.units = data.list || data || []
      } finally {
        this.loading.units = false
      }
    },
    async fetchSites() {
      try {
        const data = await request.get('/workbench/sites')
        this.sites = data.list || data || []
      } catch {
        this.sites = []
      }
    },
    async fetchCommissions() {
      this.loading.commissions = true
      try {
        const data = await request.get('/commission/list', { page: 1, limit: 1000 })
        this.commissions = data.list || []
      } finally {
        this.loading.commissions = false
      }
    },
    async fetchWorkorders() {
      this.loading.workorders = true
      try {
        const data = await request.get('/workorder/list', { page: 1, limit: 2000 })
        this.workorders = data.list || []
      } finally {
        this.loading.workorders = false
      }
    },
    async fetchValves() {
      this.loading.valves = true
      try {
        const data = await request.get('/valve/list', { page: 1, limit: 500 })
        this.valveLibrary = data.list || []
      } finally {
        this.loading.valves = false
      }
    },
    async fetchStats() {
      this.loading.stats = true
      try {
        this.stats = await request.get('/workbench/stats')
      } finally {
        this.loading.stats = false
      }
    },
    async fetchRanks() {
      this.loading.ranks = true
      try {
        const [inspector, unit] = await Promise.all([
          request.get('/workbench/rank/inspector'),
          request.get('/workbench/rank/unit')
        ])
        this.inspectorRanking = inspector || []
        this.unitRanking = unit || []
      } finally {
        this.loading.ranks = false
      }
    },
    /** 安全阀校验数统计（按年/按月）
     *  @param {{start?: string, end?: string, custName?: string}} params  start/end 形如 2026-01 */
    async fetchChart(params = {}) {
      this.chartData = await request.get('/workbench/chart', params)
    },
    /** 委托单详情（含安全阀清单） */
    fetchCommissionDetail(id) {
      return request.get('/commission/detail', { id })
    },
    /** 编辑委托单阀门信息（查看弹窗） */
    async updateCommissionValve(payload) {
      await request.post('/commission/valve_update', payload)
      await this.fetchCommissions()
    },
    /** 委托单新增阀门（手动新增/选择已有安全阀/复制） */
    async addCommissionValves(commissionId, valves) {
      const data = await request.post('/commission/valve_add', { id: commissionId, valves })
      await this.fetchCommissions()
      return data
    },
    /** 选择已有安全阀：历次委托单阀门清单汇总（筛选/分页） */
    fetchValvePool(params) {
      return request.get('/commission/valve_pool', params)
    },
    /** 新增安全阀：确认提交即写入安全阀库（已有阀门列表立即可查），返回带 libId 的记录 */
    async addLibValves(valves) {
      const data = await request.post('/commission/lib_add', { valves })
      return data.list || []
    },
    /** 删除委托单阀门 */
    async removeCommissionValve(valveId) {
      await request.post('/commission/valve_delete', { id: valveId })
      await this.fetchCommissions()
    },
    /* ---------- 委托单 ---------- */
    /** 新增委托单：只提交，不连带拉取其它数据（列表页进入时会自行查询） */
    createCommission(payload) {
      return request.post('/commission/add', payload)
    },
    async approveCommission(id) {
      await request.post('/commission/action', { id, action: 'approve' })
      await this.fetchCommissions()
    },
    async rejectCommission(id, reason) {
      await request.post('/commission/action', { id, action: 'reject', rejectReason: reason || '' })
      await this.fetchCommissions()
    },
    async updateCommission(id, patch) {
      await request.post('/commission/update', { id, ...patch })
      await this.fetchCommissions()
    },
    async removeCommission(id) {
      await request.post('/commission/action', { id, action: 'delete' })
      await this.fetchCommissions()
    },
    /** 预检流水号默认值（本月已用最大流水号 + 1） */
    async fetchNextSerial() {
      const data = await request.get('/commission/next_serial')
      return data?.next ?? ''
    },
    /** 预检通过：录入开抽流水号（后端校验唯一），状态变为「等待检验」 */
    async precheckCommission(id, serialNo) {
      const data = await request.post('/commission/action', { id, action: 'precheck', serialNo })
      await this.fetchCommissions()
      return data
    },
    /** 获取手机签名二维码地址 */
    signQrcode(id) {
      return request.get('/commission/sign_qrcode', { id })
    },
    /** 系统账号手机扫码签名：二维码地址 / 签名状态轮询 */
    sysUserSignQrcode(id) {
      return request.get('/system/user/sign_qrcode', { id })
    },
    sysUserSignStatus(id) {
      return request.get('/system/user/sign_status', { id })
    },
    /** 轮询手机签名状态 */
    signStatus(id) {
      return request.get('/commission/sign_status', { id })
    },
    async setPaid(id) {
      await request.post('/commission/action', { id, action: 'pay' })
      await this.fetchCommissions()
    },

    /* ---------- 检验工单 ---------- */
    /** 校验退回 → 重新提交，回到待检验 */
    async resubmitWorkorder(id) {
      await request.post('/workorder/resubmit', { id })
      await this.fetchWorkorders()
    },
    /** 待检验 → 提交进入检验中 */
    async submitWorkorder(id) {
      await request.post('/workorder/submit', { id })
      await this.fetchWorkorders()
    },
    /** 检验中 → 提交检验结果，进入待审核 */
    async submitResult(id, result) {
      await request.post('/workorder/submit_result', { id, ...result })
      await this.fetchWorkorders()
    },
    /** 修改工单校验编号（含校验编号2/SN/sn_code） */
    async updateWorkorderCode(payload) {
      await request.post('/workorder/update_code', payload)
    },
    /** 待审核 → 审核通过进入待审批 / 退回到校验退回（支持批量） */
    async auditWorkorders(ids, pass, reason = '') {
      const list = Array.isArray(ids) ? ids : [ids]
      await request.post('/workorder/audit', { ids: list, pass, reason })
      await this.fetchWorkorders()
    },
    /** 待审批 → 审批通过进入待打印 / 退回到校验退回（支持批量） */
    async approveWorkorders(ids, pass, reason = '') {
      const list = Array.isArray(ids) ? ids : [ids]
      await request.post('/workorder/approve', { ids: list, pass, reason })
      await this.fetchWorkorders()
    },
    /** 待打印 → 打印后已完成（支持批量） */
    async markPrinted(ids) {
      await request.post('/workorder/print', { ids: Array.isArray(ids) ? ids : [ids] })
      await this.fetchWorkorders()
    },
    /** 待检验 → 批量提交，进入检验中 */
    async batchSubmitWorkorders(ids) {
      const list = Array.isArray(ids) ? ids : [ids]
      await Promise.all(list.map((id) => request.post('/workorder/submit', { id })))
      await this.fetchWorkorders()
    },
    /** 检验中 → 批量提交检验结果，进入待审核 */
    async batchSubmitResults(ids, common, rows = {}) {
      const list = Array.isArray(ids) ? ids : [ids]
      await Promise.all(
        list.map((id) => request.post('/workorder/submit_result', { id, ...common, ...(rows[id] || {}) }))
      )
      await this.fetchWorkorders()
    },

    /* ---------- 安全阀库 ---------- */
    async addValve(payload) {
      await request.post('/valve/add', payload)
      await this.fetchValves()
    },
    async updateValve(payload) {
      await request.post('/valve/update', payload)
      await this.fetchValves()
    },
    async deleteValve(id) {
      await request.post('/valve/delete', { id })
      await this.fetchValves()
    },

    /* ---------- 客户管理（使用单位） ---------- */
    async addUnit(payload) {
      await request.post('/unit/add', payload)
      await this.fetchUnits()
    },
    async updateUnit(payload) {
      await request.post('/unit/update', payload)
      await this.fetchUnits()
    },
    async deleteUnit(id) {
      await request.post('/unit/delete', { id })
      await this.fetchUnits()
    }
  }
})

/** 可读状态名 */
export function statusLabel(status, map = WORKORDER_STATUS) {
  return map[status]?.label || status
}
