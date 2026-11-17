/**
 * 按钮级权限工具
 *
 * 权限标识规范：模块:页面:按钮，例如 commission:list:approve
 * 支持通配符：*（全部权限）、commission:*（该模块全部权限）
 *
 * 用法：
 *   1. 模板指令：<n-button v-perm="'commission:list:approve'">通过</n-button>
 *   2. 渲染函数 / 逻辑判断：import { hasPerm } from '@/utils/permission'
 *      if (hasPerm('commission:list:delete')) { ... }
 *      数组表示「任意一个满足即可」：hasPerm(['system:user:add', 'system:user:edit'])
 */
import { useUserStore } from '@/store/user'

function matchOne(code, perms) {
  return perms.some((p) => {
    if (p === code) return true
    // 前缀通配：commission:list:* → 匹配 commission:list 及其子权限
    if (p.endsWith(':*')) {
      const prefix = p.slice(0, -1) // 'commission:list:'
      return code === p.slice(0, -2) || code.startsWith(prefix)
    }
    return false
  })
}

/** 是否拥有权限（code 可为字符串或数组） */
export function hasPerm(code) {
  if (!code || (Array.isArray(code) && !code.length)) return true
  const perms = useUserStore().permissions || []
  if (perms.includes('*')) return true
  const codes = Array.isArray(code) ? code : [code]
  return codes.some((c) => !!c && matchOne(c, perms))
}

function apply(el, binding) {
  const allowed = hasPerm(binding.value)
  if (allowed) {
    if (el.getAttribute('data-perm-hidden') === '1') {
      el.removeAttribute('data-perm-hidden')
      el.style.display = ''
    }
  } else {
    el.setAttribute('data-perm-hidden', '1')
    el.style.display = 'none'
  }
}

/** v-perm 指令：无权限时隐藏元素 */
export const permissionDirective = {
  mounted: apply,
  updated: apply
}

export default { hasPerm, permissionDirective }
