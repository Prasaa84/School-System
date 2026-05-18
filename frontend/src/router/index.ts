import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import SchoolView from '../views/SchoolView.vue'
import LoginView from '../views/LoginView.vue'
import ModulePlaceholderView from '../views/ModulePlaceholderView.vue'
import PaymentsView from '../views/PaymentsView.vue'
import StudentProfileView from '../views/StudentProfileView.vue'
import StudentsInClassesView from '../views/StudentsInClassesView.vue'
import StudentsView from '../views/StudentsView.vue'
import { isAuthenticated } from '../services/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { layout: 'auth', guestOnly: true },
    },
    {
      path: '/',
      name: 'dashboard',
      component: DashboardView,
      meta: { requiresAuth: true },
    },
    {
      path: '/school',
      name: 'school',
      component: SchoolView,
      meta: { requiresAuth: true },
    },
    {
      path: '/school/add',
      redirect: '/school',
    },
    {
      path: '/school/delete',
      redirect: '/school',
    },

    {
      path: '/students',
      name: 'students',
      component: StudentsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/students/report',
      name: 'students-report',
      component: StudentsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/students/profile/:studentId',
      name: 'student-profile',
      component: StudentProfileView,
      meta: { requiresAuth: true },
    },
    {
      path: '/students/in-classes',
      name: 'students-in-classes',
      component: StudentsInClassesView,
      meta: { requiresAuth: true },
    },
    {
      path: '/students/class-assignment',
      redirect: '/students/in-classes',
    },
    {
      path: '/module/payments',
      name: 'payments',
      component: PaymentsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/module/payments/fee-types',
      name: 'payments-fee-types',
      component: PaymentsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/module/:moduleKey',
      name: 'module',
      component: ModulePlaceholderView,
      props: true,
      meta: { requiresAuth: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

router.beforeEach((to) => {
  const authed = isAuthenticated()

  if (to.meta.requiresAuth && !authed) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
    }
  }

  if (to.meta.guestOnly && authed) {
    return { name: 'dashboard' }
  }

  return true
})

export default router



