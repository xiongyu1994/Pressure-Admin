import { defineStore } from 'pinia'
import request from '@/utils/request'

/** 委托单状态（文案与参考站一致） */
export const COMMISSION_STATUS = {
  pending: { label: '等待预检', type: 'warning', code: 20 },
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

    /* 工作台（后端统计） */
    stats: { checkedTotal: 0, unchecked: 0, waitTotal: 0, commissionTotal: 0, customerTotal: 0 },
    inspectorRanking: [],
    unitRanking: [],
    chartData: { months: [], audited: [], unaudited: [] },

    /* 加载状态 */
    loaded: false,
    loading: false
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
        loaded: false,
        loading: false
      })
    },

    /* ---------- 数据加载 ---------- */
    /** 登录后首次进入 / 手动刷新：并行拉取全部基础数据 */
    async ensureLoaded(force = false) {
      if (this.loaded && !force) return
      if (this.loading) return
      this.loading = true
      try {
        await Promise.all([
          this.fetchUnits(),
          this.fetchCommissions(),
          this.fetchWorkorders(),
          this.fetchValves(),
          this.fetchStats(),
          this.fetchRanks()
        ])
        this.loaded = true
      } finally {
        this.loading = false
      }
    },
    async fetchUnits() {
      const data = await request.get('/unit/list', { limit: 500 })
      this.units = data.list || data || []
    },
    async fetchCommissions() {
      const data = await request.get('/commission/list', { page: 1, limit: 1000 })
      this.commissions = data.list || []
    },
    async fetchWorkorders() {
      const data = await request.get('/workorder/list', { page: 1, limit: 2000 })
      this.workorders = data.list || []
    },
    async fetchValves() {
      const data = await request.get('/valve/list', { page: 1, limit: 500 })
      this.valveLibrary = data.list || []
    },
    async fetchStats() {
      this.stats = await request.get('/workbench/stats')
    },
    async fetchRanks() {
      const [inspector, unit] = await Promise.all([
        request.get('/workbench/rank/inspector'),
        request.get('/workbench/rank/unit')
      ])
      this.inspectorRanking = inspector || []
      this.unitRanking = unit || []
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
    /** 变更后刷新核心数据（委托单/工单/统计） */
    async reloadCore() {
      await Promise.all([this.fetchCommissions(), this.fetchWorkorders(), this.fetchStats()])
    },

    /* ---------- 委托单 ---------- */
    async createCommission(payload) {
      const data = await request.post('/commission/add', payload)
      await this.reloadCore()
      return data
    },
    async approveCommission(id) {
      await request.post('/commission/action', { id, action: 'approve' })
      await this.reloadCore()
    },
    async rejectCommission(id, reason) {
      await request.post('/commission/action', { id, action: 'reject', rejectReason: reason || '' })
      await this.reloadCore()
    },
    async updateCommission(id, patch) {
      await request.post('/commission/update', { id, ...patch })
      await this.reloadCore()
    },
    async removeCommission(id) {
      await request.post('/commission/action', { id, action: 'delete' })
      await this.reloadCore()
    },
    async setPrecheck(id) {
      await request.post('/commission/action', { id, action: 'precheck' })
      await this.reloadCore()
    },
    async setPaid(id) {
      await request.post('/commission/action', { id, action: 'pay' })
      await this.reloadCore()
    },

    /* ---------- 检验工单 ---------- */
    /** 校验退回 → 重新提交，回到待检验 */
    async resubmitWorkorder(id) {
      await request.post('/workorder/resubmit', { id })
      await this.reloadCore()
    },
    /** 待检验 → 提交进入检验中 */
    async submitWorkorder(id) {
      await request.post('/workorder/submit', { id })
      await this.reloadCore()
    },
    /** 检验中 → 提交检验结果，进入待打印 */
    async submitResult(id, result) {
      await request.post('/workorder/submit_result', { id, ...result })
      await this.reloadCore()
    },
    /** 待打印 → 打印后已完成（支持批量） */
    async markPrinted(ids) {
      await request.post('/workorder/print', { ids: Array.isArray(ids) ? ids : [ids] })
      await this.reloadCore()
    },
    /** 待检验 → 批量提交，进入检验中 */
    async batchSubmitWorkorders(ids) {
      const list = Array.isArray(ids) ? ids : [ids]
      await Promise.all(list.map((id) => request.post('/workorder/submit', { id })))
      await this.reloadCore()
    },
    /** 检验中 → 批量提交检验结果，进入待打印 */
    async batchSubmitResults(ids, common, rows = {}) {
      const list = Array.isArray(ids) ? ids : [ids]
      await Promise.all(
        list.map((id) => request.post('/workorder/submit_result', { id, ...common, ...(rows[id] || {}) }))
      )
      await this.reloadCore()
    },

    /* ---------- 安全阀库 ---------- */
    async addValve(payload) {
      await request.post('/valve/add', payload)
      await this.fetchValves()
    },
    async deleteValve(id) {
      await request.post('/valve/delete', { id })
      await this.fetchValves()
    }
  }
})

/** 可读状态名 */
export function statusLabel(status, map = WORKORDER_STATUS) {
  return map[status]?.label || status
}
