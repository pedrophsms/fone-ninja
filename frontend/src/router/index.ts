import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue') },
    { path: '/produtos', name: 'produtos', component: () => import('@/views/ProductsView.vue') },
    { path: '/compras', name: 'compras', component: () => import('@/views/PurchasesView.vue') },
    { path: '/vendas', name: 'vendas', component: () => import('@/views/SalesView.vue') },
    { path: '/', redirect: '/produtos' },
  ],
})

router.beforeEach((to) => {
  const authStore = useAuthStore()
  if (to.name !== 'login' && !authStore.token) {
    return { name: 'login' }
  }
})

export default router
