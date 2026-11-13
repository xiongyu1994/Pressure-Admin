<script setup>
import { ref, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMessage } from 'naive-ui'
import { useUserStore } from '@/store/user'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const userStore = useUserStore()

const loading = ref(false)
const form = reactive({ username: 'admin', password: 'admin123' })
const rules = {
  username: { required: true, message: '请输入账号', trigger: 'blur' },
  password: { required: true, message: '请输入密码', trigger: 'blur' }
}

async function handleLogin() {
  if (!form.username || !form.password) {
    message.warning('请输入账号和密码')
    return
  }
  loading.value = true
  try {
    const user = await userStore.login(form.username, form.password)
    message.success(`欢迎回来，${user.name}`)
    router.push(route.query.redirect || '/dashboard')
  } catch (e) {
    message.error(e.message || '登录失败')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <div class="login-card">
      <div class="lc-title">检验后台</div>
      <div class="lc-sub">安全阀校验管理平台</div>
      <n-form :model="form" :rules="rules" label-placement="top">
        <n-form-item label="账号" path="username">
          <n-input v-model:value="form.username" placeholder="请输入账号" @keyup.enter="handleLogin" />
        </n-form-item>
        <n-form-item label="密码" path="password">
          <n-input
            v-model:value="form.password"
            type="password"
            show-password-on="click"
            placeholder="请输入密码"
            @keyup.enter="handleLogin"
          />
        </n-form-item>
        <n-button type="primary" block :loading="loading" @click="handleLogin">登 录</n-button>
      </n-form>
      <div class="lc-tips">
        演示账号：<b>admin / admin123</b>（全部权限）<br />
        演示账号：<b>zhoukuan / 123456</b>（周宽·业务权限）
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.login-page {
  width: 100%;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0d2b52 0%, #123a6d 55%, #0d2b52 100%);
}

.login-card {
  width: 380px;
  background: #fff;
  border-radius: 8px;
  padding: 36px 34px 28px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);

  .lc-title {
    font-size: 22px;
    font-weight: 700;
    color: $sidebar-bg;
    text-align: center;
  }

  .lc-sub {
    font-size: 13px;
    color: $text-secondary;
    text-align: center;
    margin: 6px 0 22px;
  }

  .lc-tips {
    margin-top: 16px;
    padding: 10px 12px;
    background: #f4f8f8;
    border-radius: 4px;
    font-size: 12px;
    color: $text-secondary;
    line-height: 1.8;
  }
}
</style>
