import { defineStore } from 'pinia'

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

/* ------------------------------ 基础数据 ------------------------------ */

const units = [
  '武汉翼华技术设备有限公司', '武汉经发粮食物流产业投资有限公司', '武汉欣达康源装备有限公司',
  '河北首衡商业管理有限公司孝感分公司', '湖北东霖新材料有限公司', '武汉环投千子山环境产业有限公司',
  '华润武钢总医院', '公安县兴成燃气有限公司', '武汉联德化学品有限公司', '武汉中车长客轨道车辆有限公司',
  '武汉东风鸿泰汽车资源循环利用有限公司', '武汉市千子山循环经济产业园', '杭州华电华源环境工程有限公司武汉设备分公司',
  '国药集团动物保健股份有限公司', '武汉三源特种建材有限责任公司', '武汉伊士曼有机化工有限公司',
  '武汉动车段', '北湖污水处理厂', '天门市第一人民医院竟陵院区', '武汉宝聚炭材料有限公司',
  '武汉宏海科技股份有限公司', '武汉博达石化工程公司', '中氢绿能湖北科技有限公司', '武汉乐祥包装新材料有限公司'
].map((name, i) => ({ id: i + 1, name }))

const examUnits = ['武汉翼华技术设备有限公司', '湖北欣瑞祥油品管理有限公司孝感分公司']

const medias = ['蒸汽', '压缩空气', '氮气', '水', '天然气']
const valveTypes = ['弹簧全启式安全阀', '弹簧微启式安全阀', '先导式安全阀', '全启式安全阀']
const manufacturers = ['上海阀门厂股份有限公司', '浙江超达阀门股份有限公司', '苏州纽威阀门股份有限公司', '上海上螺阀门有限公司']
const protectTypes = ['锅炉', '压力容器', '压力管道']

function pick(arr, i) {
  return arr[i % arr.length]
}

function makeValve(seq, custName) {
  const press = [1.0, 1.27, 1.6, 0.8, 2.2, 1.1, 0.4, 1.9][seq % 8]
  return {
    equipType: '安全阀',
    protectedEquipType: pick(protectTypes, seq),
    workingMedia: pick(medias, seq),
    setPress: press,
    workingPress: +(press - 0.3).toFixed(2),
    tagNo: `${custName ? custName.slice(0, 2) : 'XX'}-${String(seq % 20 + 1).padStart(2, '0')}`,
    idNo: `RQ${String(21000000 + seq * 137).slice(0, 8)}`,
    designPress: +(press + 0.4).toFixed(2),
    workingTemp: pick(['常温', '180℃', '220℃', '常温'], seq),
    vavleModel: pick(['A48Y-16C DN50', 'A47H-16C DN40', 'A42Y-25C DN32', 'DA22Y-25 DN25'], seq),
    manufacturer: pick(manufacturers, seq),
    vavleType: pick(valveTypes, seq),
    diameterNominal: pick([50, 40, 32, 25, 80], seq),
    licenseNo: `TS${String(2710000 + seq * 31)}-2024`,
    seatSize: pick([32, 26, 20, 40], seq),
    pressNominal: pick([1.6, 2.5, 4.0], seq),
    pressClassRangeMin: +(press - 0.3).toFixed(2),
    pressClassRangeMax: +(press + 0.2).toFixed(2),
    manuDate: `202${3 + (seq % 2)}-0${1 + (seq % 9)}-15`,
    manuNo: `CC${String(240000 + seq * 53)}`,
    tightnessPress: +(press * 0.9).toFixed(2),
    pressCorrection: '无',
    isNew: seq % 3 === 0 ? '是' : '否',
    isRemovedCleaning: '是',
    isComplete: '是',
    isNameplate: '是',
    parity: pick(['离线校验', '在线校验'], seq)
  }
}

/* ------------------------------ 种子数据 ------------------------------ */

function seed() {
  const commissions = []
  const workorders = []
  const statusPlan = ['approved', 'approved', 'pending', 'approved', 'rejected', 'approved', 'pending', 'approved', 'rejected', 'approved', 'approved', 'approved']
  let orderSeq = 1001
  let commissionSeq = 301

  for (let i = 0; i < statusPlan.length; i++) {
    const cust = pick(units, i * 5 + 3)
    const status = statusPlan[i]
    const valveCount = (i % 4) + 1
    const valves = Array.from({ length: valveCount }, (_, j) => makeValve(i * 7 + j * 3, cust.name))
    const commission = {
      id: i + 1,
      code: `WT2026${commissionSeq++}`,
      custId: cust.id,
      custName: cust.name,
      address: `${cust.name.slice(0, 5)}工业园区${i + 1}号`,
      client: ['刘建国', '陈明华', '张丽', '王海涛', '赵国庆'][i % 5],
      clientTel: `139${String(70000000 + i * 13571).slice(0, 8)}`,
      applicant: cust.name,
      custAddress: `${cust.name.slice(0, 5)}工业园区${i + 1}号`,
      contact: ['刘建国', '陈明华', '张丽', '王海涛', '赵国庆'][i % 5],
      contactTel: `137${String(50000000 + i * 22457).slice(0, 8)}`,
      examName: pick(examUnits, i),
      planReceiverTime: `2026-01-${String(10 + i).padStart(2, '0')}`,
      deliverer: ['刘建国', '陈明华', '张丽'][i % 3],
      deliveryTime: `2026-01-${String(10 + i).padStart(2, '0')} 09:30`,
      status,
      signed: status !== 'pending',
      precheckNo: status === 'approved' ? '1001' : '',
      rejectReason: status === 'rejected' ? '委托单信息填写不完整，请补充设备代码后重新提交' : '',
      precheck: status !== 'pending',
      paid: status !== 'pending',
      createTime: `2026-01-${String(8 + i).padStart(2, '0')} 10:${String(10 + i * 3).padStart(2, '0')}`,
      createBy: '周宽',
      reportDone: 0,
      valves
    }
    commissions.push(commission)

    if (status === 'approved') {
      commission.valves.forEach((v, j) => {
        const ws = ['wait', 'inspection', 'print', 'done', 'wait', 'print', 'done'][(i + j) % 7]
        workorders.push(makeWorkorder(commission, v, ws, orderSeq++))
      })
    } else if (status === 'rejected') {
      commission.valves.forEach((v) => {
        workorders.push(makeWorkorder(commission, v, 'rejected', orderSeq++))
      })
    }
  }
  return { commissions, workorders }
}

function makeWorkorder(commission, valve, status, seq) {
  const wo = {
    id: seq,
    commissionId: commission.id,
    code: commission.code,
    custName: commission.custName,
    client: commission.client,
    clientTel: commission.clientTel,
    orderCode: `JY2026${seq}`,
    snCode: `SN${seq}`,
    applicant: commission.applicant || commission.custName,
    status,
    rejectReason: status === 'rejected' ? '整定压力参数与铭牌不符，请核实后重新送检' : '',
    createTime: commission.createTime,
    updateTime: `2026-01-${String(9 + (seq % 20)).padStart(2, '0')} 14:${String(seq % 60).padStart(2, '0')}`,
    inspector: '',
    inspectorTime: '',
    result: null,
    ...valve
  }
  if (status === 'print' || status === 'done') {
    wo.inspector = pick(INSPECTORS, seq)
    wo.inspectorTime = wo.updateTime
    wo.result = makeResult(wo, seq)
  }
  return wo
}

function makeResult(wo, seq) {
  const base = Number(wo.setPress) || 1.0
  return {
    tightnessPress: wo.tightnessPress,
    setPress: wo.setPress,
    testMedia: pick(['氮气', '空气'], seq),
    testTemprature: '常温',
    parity: wo.parity || '离线校验',
    visualExamination: '合格',
    disassemblyInspection: '合格',
    note: '无',
    standardNumber: STANDARDS.slice(0, 3),
    pressureGaugeNum: `YB-${String(10000 + seq).slice(-5)}`,
    pressureGaugeLifespan: '2027-06-30',
    pressureGaugeNum2: '',
    pressureGaugeLifespan2: '',
    pressureGaugeAccuracy: '0.4级',
    pressureGaugeRange: '0-2.5',
    deviceModel: 'SFA-150',
    deviceSn: `JC${String(88000 + seq)}`,
    testingPressure1: base,
    testingPressure2: +base.toFixed(2),
    testingPressure3: +base.toFixed(2),
    setPressure1: base,
    setPressure2: +base.toFixed(2),
    setPressure3: +base.toFixed(2),
    bubbleCount: 0,
    qita: '无',
    maintenance: ['经解体、清洗、检查合格'],
    testStatus: pick(TEST_STATUS_OPTIONS, seq) === '不合格' ? '不合格' : '合格'
  }
}

/* ------------------------------ Store ------------------------------ */

export const useBusinessStore = defineStore('business', {
  state: () => {
    const { commissions, workorders } = seed()
    return {
      units,
      examUnits,
      inspectors: INSPECTORS,
      testStatusOptions: TEST_STATUS_OPTIONS,
      commissions,
      workorders,
      valveLibrary: Array.from({ length: 18 }, (_, i) => ({
        id: i + 1,
        ...makeValve(i * 3 + 1, '库存')
      })),
      nextCommissionId: 100,
      nextOrderSeq: 2000,
      workorderSeq: 2000
    }
  },
  getters: {
    /** 工作台统计（口径与参考站一致） */
    stats(state) {
      return {
        checkedTotal: 1388,
        unchecked: 4,
        waitTotal: state.workorders.filter((w) => w.status === 'wait').length,
        commissionTotal: 296 + state.commissions.length,
        customerTotal: 195 + state.units.length
      }
    },
    /** 站点（工作台顶部 tab） */
    sites() {
      return [
        { id: 0, name: '总计' },
        { id: 30, name: '武汉翼华技术设备有限公司' }
      ]
    },
    inspectorRanking(state) {
      return INSPECTORS.map((name, i) => ({
        rank: i + 1,
        name,
        org: '武汉翼华技术设备有限公司',
        count: 1376 - i * 1356
      }))
    },
    unitRanking(state) {
      const map = {}
      state.workorders.forEach((w) => {
        map[w.custName] = (map[w.custName] || 0) + 1
      })
      return Object.entries(map)
        .sort((a, b) => b[1] - a[1])
        .map(([name, count], i) => ({ rank: i + 1, unit: name, count }))
    }
  },
  actions: {
    /* ---------- 委托单 ---------- */
    createCommission(payload) {
      const id = this.nextCommissionId++
      const cust = this.units.find((u) => u.id === payload.custId)
      const commission = {
        id,
        code: `WT2026${300 + id}`,
        custId: payload.custId,
        custName: cust ? cust.name : payload.custName,
        address: payload.address,
        client: payload.client,
        clientTel: payload.clientTel,
        applicant: payload.applicant || cust?.name || '',
        custAddress: payload.custAddress || '',
        contact: payload.contact || '',
        contactTel: payload.contactTel || '',
        examName: payload.examName || this.examUnits[0],
        planReceiverTime: payload.planReceiverTime || '',
        deliverer: payload.deliverer || '',
        deliveryTime: payload.deliveryTime || '',
        status: 'pending',
        rejectReason: '',
        precheck: false,
        paid: false,
        createTime: new Date().toLocaleString('zh-CN', { hour12: false }),
        createBy: '周宽',
        reportDone: 0,
        valves: (payload.valves || []).map((v, i) => ({ ...v, _seq: Date.now() + i }))
      }
      this.commissions.unshift(commission)
      return commission
    },
    approveCommission(id) {
      const c = this.commissions.find((x) => x.id === id)
      if (!c) return
      c.status = 'approved'
      c.rejectReason = ''
      c.valves.forEach((v) => {
        const seq = ++this.workorderSeq
        this.workorders.unshift(makeWorkorder(c, v, 'wait', seq))
      })
    },
    rejectCommission(id, reason) {
      const c = this.commissions.find((x) => x.id === id)
      if (!c) return
      c.status = 'rejected'
      c.rejectReason = reason || '委托单信息填写不完整'
      c.valves.forEach((v) => {
        const seq = ++this.workorderSeq
        this.workorders.unshift(makeWorkorder(c, v, 'rejected', seq))
      })
    },
    updateCommission(id, patch) {
      const c = this.commissions.find((x) => x.id === id)
      if (c) Object.assign(c, patch)
    },
    removeCommission(id) {
      this.commissions = this.commissions.filter((x) => x.id !== id)
    },
    setPrecheck(id, pass) {
      const c = this.commissions.find((x) => x.id === id)
      if (c) {
        c.precheck = pass
      }
    },
    setPaid(id) {
      const c = this.commissions.find((x) => x.id === id)
      if (c) c.paid = true
    },
    /** 委托单签名（二维码/PDF 签名后进入等待预检） */
    signCommission(id) {
      const c = this.commissions.find((x) => x.id === id)
      if (c) {
        c.signed = true
        c.signTime = new Date().toLocaleString('zh-CN', { hour12: false })
      }
    },
    /** 通过预检：记录开始流水号 */
    setPrecheckNo(id, payload) {
      const c = this.commissions.find((x) => x.id === id)
      if (!c) return
      c.precheck = true
      c.precheckNo = payload?.offline || payload?.online || ''
      c.precheckOnline = payload?.online || ''
      c.precheckOffline = payload?.offline || ''
    },
    /** 退回预检：记录原因，委托单回到已退回 */
    rejectPrecheck(id, reason) {
      const c = this.commissions.find((x) => x.id === id)
      if (!c) return
      c.status = 'rejected'
      c.precheck = false
      c.rejectReason = reason || '预检未通过'
    },

    /* ---------- 检验工单 ---------- */
    /** 校验退回 → 重新提交，回到待检验 */
    resubmitWorkorder(id) {
      const w = this.workorders.find((x) => x.id === id)
      if (w) {
        w.status = 'wait'
        w.rejectReason = ''
        w.updateTime = new Date().toLocaleString('zh-CN', { hour12: false })
      }
    },
    /** 待检验 → 提交进入检验中 */
    submitWorkorder(id) {
      const w = this.workorders.find((x) => x.id === id)
      if (w) {
        w.status = 'inspection'
        w.updateTime = new Date().toLocaleString('zh-CN', { hour12: false })
      }
    },
    /** 检验中 → 提交检验结果，进入待打印 */
    submitResult(id, result) {
      const w = this.workorders.find((x) => x.id === id)
      if (w) {
        w.status = 'print'
        w.result = { ...result }
        w.inspector = result.inspector
        w.inspectorTime = result.inspectorTime
        w.updateTime = new Date().toLocaleString('zh-CN', { hour12: false })
      }
    },
    /** 待打印 → 打印后已完成 */
    markPrinted(ids) {
      const list = Array.isArray(ids) ? ids : [ids]
      list.forEach((id) => {
        const w = this.workorders.find((x) => x.id === id)
        if (w && w.status === 'print') {
          w.status = 'done'
          w.updateTime = new Date().toLocaleString('zh-CN', { hour12: false })
          const c = this.commissions.find((x) => x.id === w.commissionId)
          if (c) c.reportDone = c.valves.length
        }
      })
    },
    /** 待检验 → 批量提交，进入检验中 */
    batchSubmitWorkorders(ids) {
      const list = Array.isArray(ids) ? ids : [ids]
      const now = new Date().toLocaleString('zh-CN', { hour12: false })
      list.forEach((id) => {
        const w = this.workorders.find((x) => x.id === id)
        if (w && w.status === 'wait') {
          w.status = 'inspection'
          w.updateTime = now
        }
      })
    },
    /** 检验中 → 批量提交检验结果，进入待打印 */
    batchSubmitResults(ids, common, rows = {}) {
      const list = Array.isArray(ids) ? ids : [ids]
      const now = new Date().toLocaleString('zh-CN', { hour12: false })
      list.forEach((id) => {
        const w = this.workorders.find((x) => x.id === id)
        if (!w || w.status !== 'inspection') return
        const extra = rows[id] || {}
        w.status = 'print'
        w.result = { ...common, ...extra }
        w.inspector = common.inspector
        w.inspectorTime = common.inspectorTime
        w.updateTime = now
      })
    },
    /** 待打印 → 退回预检 */
    returnToPrecheck(ids) {
      const list = Array.isArray(ids) ? ids : [ids]
      list.forEach((id) => {
        const w = this.workorders.find((x) => x.id === id)
        if (!w) return
        w.status = 'rejected'
        w.rejectReason = '打印前退回，需重新预检'
        w.updateTime = new Date().toLocaleString('zh-CN', { hour12: false })
      })
    }
  }
})

/** 可读状态名 */
export function statusLabel(status, map = WORKORDER_STATUS) {
  return map[status]?.label || status
}
