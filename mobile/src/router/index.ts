import { createRouter, createWebHistory } from '@ionic/vue-router'
import { getUser, isAuthenticated } from '../services/auth'
import { isClassTeacherRole, isMobileEnabledRole, isStudentRole, resolveHomeRoute } from '../services/roles'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/login',
    },
    {
      path: '/login',
      component: () => import('../views/LoginPage.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/student',
      component: () => import('../views/StudentHomePage.vue'),
      meta: { requiresAuth: true, role: 'student' },
    },
    {
      path: '/student/profile',
      component: () => import('../views/StudentProfilePage.vue'),
      meta: { requiresAuth: true, role: 'student' },
    },
    {
      path: '/student/marks',
      component: () => import('../views/StudentMarksPage.vue'),
      meta: { requiresAuth: true, role: 'student' },
    },
    {
      path: '/teacher',
      component: () => import('../views/TeacherHomePage.vue'),
      meta: { requiresAuth: true, role: 'teacher' },
    },
    {
      path: '/profile',
      component: () => import('../views/ProfilePage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/future-role',
      component: () => import('../views/FutureRolePage.vue'),
      meta: { requiresAuth: true },
    },
  ],
})

router.beforeEach((to) => {
  const authed = isAuthenticated()
  const user = getUser()

  if (to.meta.requiresAuth && !authed) {
    return '/login'
  }

  if (to.meta.guestOnly && authed) {
    return resolveHomeRoute(user)
  }

  if (!authed) {
    return true
  }

  if (!isMobileEnabledRole(user) && to.path !== '/future-role' && to.path !== '/profile') {
    return '/future-role'
  }

  if (to.meta.role === 'student' && !isStudentRole(user)) {
    return resolveHomeRoute(user)
  }

  if (to.meta.role === 'teacher' && !isClassTeacherRole(user)) {
    return resolveHomeRoute(user)
  }

  return true
})

export default router
