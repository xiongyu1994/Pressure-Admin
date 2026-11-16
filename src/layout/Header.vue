<script setup>
import { ref } from 'vue'
import { NIcon, NDropdown, NAvatar, NModal, NDescriptions, NDescriptionsItem, NSpin } from 'naive-ui'
import { useAppStore } from '@/store/app'
import { useUserStore } from '@/store/user'
import { MenuOutline, LogOutOutline, ChevronDownOutline } from '@/utils/icons'

const appStore = useAppStore()
const userStore = useUserStore()

const infoShow = ref(false)
const infoLoading = ref(false)
const info = ref(null)

async function onUserAction(key) {
  if (key === 'info') {
    infoShow.value = true
    infoLoading.value = true
    try {
      info.value = await userStore.fetchUserInfo()
    } catch {
      info.value = null
    } finally {
      infoLoading.value = false
    }
  } else if (key === 'logout') {
    userStore.logout()
  }
}
</script>

<template>
  <div class="header-bar">
    <div class="hb-left">
      <n-icon :size="20" :component="MenuOutline" class="hb-toggle" @click="appStore.toggleCollapsed()" />
    </div>
    <div class="hb-right">
      <n-dropdown
        trigger="click"
        :options="[
          { label: '个人信息', key: 'info' },
          { label: '退出登录', key: 'logout' }
        ]"
        @select="onUserAction"
      >
        <div class="hb-user">
          <n-avatar round :size="28" class="hb-avatar">{{ userStore.name.slice(0, 1) }}</n-avatar>
          <span class="hb-name">{{ userStore.name }}</span>
          <n-icon :size="14" :component="ChevronDownOutline" />
        </div>
      </n-dropdown>
    </div>

    <n-modal v-model:show="infoShow" preset="card" title="个人信息" style="width: 480px">
      <n-spin :show="infoLoading">
        <n-descriptions bordered :column="1" size="small" label-placement="left">
          <n-descriptions-item label="姓名">{{ info?.name || userStore.name }}</n-descriptions-item>
          <n-descriptions-item label="账号">{{ info?.account || '-' }}</n-descriptions-item>
          <n-descriptions-item label="手机号">{{ info?.phone || '-' }}</n-descriptions-item>
          <n-descriptions-item label="身份">{{ info?.identity || userStore.roles[0] || '-' }}</n-descriptions-item>
          <n-descriptions-item label="单位名称">{{ info?.unitName || '-' }}</n-descriptions-item>
          <n-descriptions-item label="最后登录时间">{{ info?.lastTime || '-' }}</n-descriptions-item>
          <n-descriptions-item label="最后登录IP">{{ info?.lastIp || '-' }}</n-descriptions-item>
        </n-descriptions>
      </n-spin>
    </n-modal>
  </div>
</template>

<style lang="scss" scoped>
.header-bar {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
}

.hb-toggle {
  color: #fff;
  cursor: pointer;

  &:hover {
    opacity: 0.8;
  }
}

.hb-user {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  color: #fff;

  .hb-avatar {
    background: $primary;
    color: #fff;
    font-size: 13px;
  }

  .hb-name {
    font-size: 14px;
  }
}
</style>
