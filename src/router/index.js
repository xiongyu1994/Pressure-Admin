import { createRouter, createWebHashHistory } from 'vue-router'

/** 静态路由：任何人可访问 */
export const constantRoutes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/index.vue'),
    meta: { hidden: true }
  }
]

/**
 * 动态权限路由树（与参考系统菜单一致）：
 * meta.permission 为访问该页面所需的权限标识；
 * meta.buttons 为该页面下的按钮级权限（在「权限设置」中可勾选，前端 v-perm / hasPerm 消费）；
 * 目录节点仅当至少一个子节点通过权限过滤时才显示。
 */
export const asyncRoutes = [
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/dashboard/index.vue'),
    meta: { title: '工作台', icon: 'SpeedometerOutline', permission: 'dashboard' }
  },
  {
    path: '/commission',
    name: 'Commission',
    meta: { title: '委托单管理', icon: 'DocumentTextOutline', permission: 'commission' },
    children: [
      {
        path: '/commission/add',
        name: 'CommissionAdd',
        component: () => import('@/views/commission/add.vue'),
        meta: {
          title: '现场送检',
          icon: 'CreateOutline',
          permission: 'commission:add',
          buttons: [
            { perm: 'commission:add:valve', label: '添加 / 新增安全阀' },
            { perm: 'commission:add:submit', label: '确认提交' }
          ]
        }
      },
      {
        path: '/commission/list',
        name: 'CommissionList',
        component: () => import('@/views/commission/list.vue'),
        meta: {
          title: '送检列表',
          icon: 'ListOutline',
          permission: 'commission:list',
          buttons: [
            { perm: 'commission:list:add', label: '新增委托单' },
            { perm: 'commission:list:view', label: '查看详情' },
            { perm: 'commission:list:edit', label: '编辑' },
            { perm: 'commission:list:sign', label: '手机签名' },
            { perm: 'commission:list:approve', label: '审核通过' },
            { perm: 'commission:list:reject', label: '退回' },
            { perm: 'commission:list:precheck', label: '通过预检' },
            { perm: 'commission:list:pay', label: '付费' },
            { perm: 'commission:list:delete', label: '删除' },
            { perm: 'commission:list:print', label: '打印委托单' },
            { perm: 'commission:list:export', label: '批量导出' },
            { perm: 'commission:list:pending', label: '待检阀门' }
          ]
        }
      }
    ]
  },
  {
    path: '/workorder',
    name: 'Workorder',
    meta: { title: '检验管理', icon: 'CheckboxOutline', permission: 'workorder' },
    children: [
      {
        path: '/workorder/reject',
        name: 'WorkorderReject',
        component: () => import('@/views/workorder/reject.vue'),
        meta: {
          title: '校验退回',
          permission: 'workorder:reject',
          buttons: [{ perm: 'workorder:reject:resubmit', label: '编辑并重新提交' }]
        }
      },
      {
        path: '/workorder/wait',
        name: 'WorkorderWait',
        component: () => import('@/views/workorder/wait.vue'),
        meta: {
          title: '待检验',
          permission: 'workorder:wait',
          buttons: [{ perm: 'workorder:wait:submit', label: '提交检验' }]
        }
      },
      {
        path: '/workorder/inspection',
        name: 'WorkorderInspection',
        component: () => import('@/views/workorder/inspection.vue'),
        meta: {
          title: '检验中',
          permission: 'workorder:inspection',
          buttons: [{ perm: 'workorder:inspection:result', label: '提交检验结果' }]
        }
      },
      {
        path: '/workorder/print',
        name: 'WorkorderPrint',
        component: () => import('@/views/workorder/print.vue'),
        meta: {
          title: '待打印',
          permission: 'workorder:print',
          buttons: [
            { perm: 'workorder:print:print', label: '打印报告 / 记录 / 台账' },
            { perm: 'workorder:print:export', label: '导出打标记录' }
          ]
        }
      },
      {
        path: '/workorder/all',
        name: 'WorkorderAll',
        component: () => import('@/views/workorder/all.vue'),
        meta: {
          title: '已完成',
          permission: 'workorder:all',
          buttons: [
            { perm: 'workorder:all:print', label: '打印报告 / 记录 / 台账' },
            { perm: 'workorder:all:export', label: '导出打标记录' }
          ]
        }
      }
    ]
  },
  {
    path: '/valve',
    name: 'Valve',
    meta: { title: '安全阀管理', icon: 'CubeOutline', permission: 'valve' },
    children: [
      {
        path: '/valve/add',
        name: 'ValveAdd',
        component: () => import('@/views/valve/add.vue'),
        meta: {
          title: '新增安全阀',
          icon: 'AddCircleOutline',
          permission: 'valve:add',
          buttons: [{ perm: 'valve:add:save', label: '保存安全阀' }]
        }
      },
      {
        path: '/valve/list',
        name: 'ValveList',
        component: () => import('@/views/valve/list.vue'),
        meta: {
          title: '安全阀列表',
          icon: 'GridOutline',
          permission: 'valve:list',
          buttons: [
            { perm: 'valve:add', label: '新增安全阀' },
            { perm: 'valve:list:edit', label: '编辑安全阀' },
            { perm: 'valve:list:delete', label: '删除安全阀' },
            { perm: 'valve:list:export', label: '导出' }
          ]
        }
      }
    ]
  },
  {
    path: '/customer',
    name: 'Customer',
    component: () => import('@/views/customer/list.vue'),
    meta: {
      title: '客户管理',
      icon: 'PeopleOutline',
      permission: 'customer',
      buttons: [
        { perm: 'customer:add', label: '新增客户' },
        { perm: 'customer:edit', label: '编辑客户' },
        { perm: 'customer:delete', label: '删除客户' }
      ]
    }
  },
  {
    path: '/system',
    name: 'System',
    meta: { title: '系统管理', icon: 'SettingsOutline', permission: 'system' },
    children: [
      {
        path: '/system/site',
        name: 'SystemSite',
        component: () => import('@/views/system/site.vue'),
        meta: {
          title: '站点管理',
          icon: 'ServerOutline',
          permission: 'system:site',
          buttons: [
            { perm: 'system:site:add', label: '新增站点' },
            { perm: 'system:site:edit', label: '编辑站点' },
            { perm: 'system:site:delete', label: '删除站点' },
            { perm: 'system:site:status', label: '站点启停' }
          ]
        }
      },
      {
        path: '/system/tpl',
        name: 'SystemTpl',
        component: () => import('@/views/system/tpl.vue'),
        meta: {
          title: '模版管理',
          icon: 'DocumentOutline',
          permission: 'system:tpl',
          buttons: [
            { perm: 'system:tpl:add', label: '新增模版' },
            { perm: 'system:tpl:edit', label: '编辑模版' },
            { perm: 'system:tpl:delete', label: '删除模版' },
            { perm: 'system:tpl:status', label: '模版启停' }
          ]
        }
      },
      {
        path: '/system/user',
        name: 'SystemUser',
        component: () => import('@/views/system/user.vue'),
        meta: {
          title: '系统账号',
          icon: 'PersonOutline',
          permission: 'system:user',
          buttons: [
            { perm: 'system:user:add', label: '新增账号' },
            { perm: 'system:user:edit', label: '编辑账号' },
            { perm: 'system:user:delete', label: '删除账号' },
            { perm: 'system:user:status', label: '启用 / 停用' }
          ]
        }
      },
      {
        path: '/system/role',
        name: 'SystemRole',
        component: () => import('@/views/system/role.vue'),
        meta: {
          title: '权限设置',
          icon: 'ShieldCheckmarkOutline',
          permission: 'system:role',
          buttons: [
            { perm: 'system:role:add', label: '新增角色' },
            { perm: 'system:role:edit', label: '编辑角色' },
            { perm: 'system:role:perm', label: '保存权限' },
            { perm: 'system:role:delete', label: '删除角色' }
          ]
        }
      },
      {
        path: '/system/menu',
        name: 'SystemMenu',
        component: () => import('@/views/system/menu.vue'),
        meta: {
          title: '菜单访问设置',
          icon: 'ListOutline',
          permission: 'system:menu',
          buttons: [
            { perm: 'system:menu:add', label: '新增菜单' },
            { perm: 'system:menu:edit', label: '编辑菜单' },
            { perm: 'system:menu:visible', label: '菜单显隐' },
            { perm: 'system:menu:delete', label: '删除菜单' }
          ]
        }
      }
    ]
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    ...constantRoutes,
    {
      path: '/',
      name: 'Layout',
      component: () => import('@/layout/index.vue'),
      redirect: '/dashboard',
      children: []
    }
  ]
})

router.beforeEach(async (to) => {
  const { useUserStore } = await import('@/store/user')
  const { usePermissionStore } = await import('@/store/permission')
  const userStore = useUserStore()
  const permissionStore = usePermissionStore()

  if (to.path === '/login') {
    return userStore.token ? '/dashboard' : true
  }
  if (!userStore.token) {
    return { path: '/login', query: to.path === '/' ? {} : { redirect: to.fullPath } }
  }
  // 首次进入或刷新后：先同步服务端最新权限（含按钮权限），再按权限动态注册路由
  if (!permissionStore.isGenerated) {
    await userStore.syncPermissions().catch(() => {})
    permissionStore.generateRoutes(userStore.permissions)
    // 菜单显隐配置只在首次进入 / 刷新后拉取一次（侧边栏共用），切换页面不再重复请求
    // 业务数据不在这里预加载，由各页面自己按需调用所需接口
    const { useMenuStore } = await import('@/store/menu')
    await useMenuStore()
      .fetchMenus()
      .catch(() => {})
    return { ...to, replace: true }
  }
  return true
})

export default router
