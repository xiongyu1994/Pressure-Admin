import { h } from 'vue'
import { NIcon } from 'naive-ui'
import {
  SpeedometerOutline,
  DocumentTextOutline,
  CheckboxOutline,
  SettingsOutline,
  PersonOutline,
  ShieldCheckmarkOutline,
  ListOutline,
  MenuOutline,
  ChevronDownOutline,
  ChevronUpOutline,
  CloseOutline,
  ClipboardOutline,
  CheckmarkDoneOutline,
  TimeOutline,
  ReaderOutline,
  PeopleOutline,
  NotificationsOutline,
  SearchOutline,
  AddOutline,
  CreateOutline,
  TrashOutline,
  LogOutOutline,
  ServerOutline,
  DocumentOutline,
  CubeOutline
} from '@vicons/ionicons5'

export const iconMap = {
  SpeedometerOutline,
  DocumentTextOutline,
  CheckboxOutline,
  SettingsOutline,
  PersonOutline,
  ShieldCheckmarkOutline,
  ListOutline,
  ServerOutline,
  DocumentOutline,
  CreateOutline,
  ClipboardOutline,
  CheckmarkDoneOutline,
  TimeOutline,
  ReaderOutline,
  PeopleOutline,
  NotificationsOutline,
  CubeOutline
}

/** 渲染 naive-ui 菜单图标 */
export function renderIcon(name) {
  const comp = iconMap[name]
  if (!comp) return undefined
  return () => h(NIcon, null, { default: () => h(comp) })
}

export {
  ShieldCheckmarkOutline,
  MenuOutline,
  ChevronDownOutline,
  ChevronUpOutline,
  CloseOutline,
  SearchOutline,
  AddOutline,
  CreateOutline,
  TrashOutline,
  LogOutOutline,
  NotificationsOutline
}
