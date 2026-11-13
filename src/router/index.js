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
        meta: { title: '现场送检', icon: 'CreateOutline', permission: 'commission:add' }
      },
      {
        path: '/commission/list',
        name: 'CommissionList',
        component: () => import('@/views/commission/list.vue'),
        meta: { title: '送检列表', icon: 'ListOutline', permission: 'commission:list' }
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
        meta: { title: '校验退回', permission: 'workorder:reject' }
      },
      {
        path: '/workorder/wait',
        name: 'WorkorderWait',
        component: () => import('@/views/workorder/wait.vue'),
        meta: { title: '待检验', permission: 'workorder:wait' }
      },
      {
        path: '/workorder/inspection',
        name: 'WorkorderInspection',
        component: () => import('@/views/workorder/inspection.vue'),
        meta: { title: '检验中', permission: 'workorder:inspection' }
      },
      {
        path: '/workorder/print',
        name: 'WorkorderPrint',
        component: () => import('@/views/workorder/print.vue'),
        meta: { title: '待打印', permission: 'workorder:print' }
      },
      {
        path: '/workorder/all',
        name: 'WorkorderAll',
        component: () => import('@/views/workorder/all.vue'),
        meta: { title: '已完成', permission: 'workorder:all' }
      }
    ]
  },
  {
    path: '/system',
    name: 'System',
    meta: { title: '系统管理', icon: 'SettingsOutline', permission: 'system' },
    children: [
      {
        path: '/system/user',
        name: 'SystemUser',
        component: () => import('@/views/system/user.vue'),
        meta: { title: '系统账号', icon: 'PersonOutline', permission: 'system:user' }
      },
      {
        path: '/system/role',
        name: 'SystemRole',
        component: () => import('@/views/system/role.vue'),
        meta: { title: '权限设置', icon: 'ShieldCheckmarkOutline', permission: 'system:role' }
      },
      {
        path: '/system/menu',
        name: 'SystemMenu',
        component: () => import('@/views/system/menu.vue'),
        meta: { title: '菜单访问设置', icon: 'ListOutline', permission: 'system:menu' }
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
  // 首次进入或刷新后：按当前用户权限动态注册路由
  if (!permissionStore.isGenerated) {
    permissionStore.generateRoutes(userStore.permissions)
    return { ...to, replace: true }
  }
  return true
})

export default router
