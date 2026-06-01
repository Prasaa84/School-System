import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import SchoolView from '../views/SchoolView.vue'
import LoginView from '../views/LoginView.vue'
import ModulePlaceholderView from '../views/ModulePlaceholderView.vue'
import PaymentsView from '../views/PaymentsView.vue'
import AccountView from '../views/AccountView.vue'
import StudentProfileView from '../views/StudentProfileView.vue'
import StudentsDailyAttendanceView from '../views/StudentsDailyAttendanceView.vue'
import StudentsInClassesView from '../views/StudentsInClassesView.vue'
import StudentsView from '../views/StudentsView.vue'
import SubjectsReportView from '../views/SubjectsReportView.vue'
import SubjectsView from '../views/SubjectsView.vue'
import { getUser, isAuthenticated } from '../services/auth'

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
      path: '/students/daily-attendance',
      name: 'students-daily-attendance',
      component: StudentsDailyAttendanceView,
      meta: { requiresAuth: true },
    },
    {
      path: '/students/profile/:studentId',
      name: 'student-profile',
      component: StudentProfileView,
      meta: { requiresAuth: true },
    },
    {
      path: '/students/me',
      name: 'student-profile-me',
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
      path: '/module/subjects',
      name: 'subjects',
      component: SubjectsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/module/subjects/report',
      name: 'subjects-report',
      component: SubjectsReportView,
      meta: { requiresAuth: true },
    },
    {
      path: '/account',
      name: 'account',
      component: AccountView,
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
  const currentUser = getUser()
  const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
  const isStudent = (currentUser?.role_id ?? 0) === 7 || roleName === 'student'
  const isClassTeacher = ['class teacher', 'class_teacher', 'classteacher'].includes(roleName)
  const isPrincipal = (currentUser?.role_id ?? 0) === 2 || roleName === 'principal'
  const isAdmin = (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator'
  const isClerk = (currentUser?.role_id ?? 0) === 6 || roleName === 'clerk'

  if (to.meta.requiresAuth && !authed) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
    }
  }

  if (authed && isStudent && to.name === 'dashboard') {
    return { name: 'student-profile-me' }
  }

  if (to.meta.guestOnly && authed) {
    return { name: isStudent ? 'student-profile-me' : 'dashboard' }
  }

  if (to.name === 'students-daily-attendance' && !isClassTeacher && !isPrincipal) {
    return { name: isStudent ? 'student-profile-me' : 'dashboard' }
  }

  if (to.name === 'subjects-report' && !isAdmin && !isPrincipal && !isClerk) {
    return { name: isStudent ? 'student-profile-me' : 'dashboard' }
  }

  return true
})

export default router



