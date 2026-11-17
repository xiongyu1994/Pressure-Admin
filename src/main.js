import { createApp } from 'vue'
import { createPinia } from 'pinia'
import naive from 'naive-ui'
import App from './App.vue'
import router from './router'
import { permissionDirective } from '@/utils/permission'
import '@/styles/index.scss'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(naive)
// 按钮级权限指令：v-perm="'commission:list:approve'"
app.directive('perm', permissionDirective)
app.mount('#app')
