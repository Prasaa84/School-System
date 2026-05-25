<template>
  <div class="min-h-screen bg-slate-100 text-slate-900 print:bg-white">
    <div v-if="isMobile && ui.mobileSidebarOpen" class="fixed inset-0 z-30 bg-slate-900/40 print:hidden" @click="ui.closeMobileSidebar" />

    <div class="mx-auto flex min-h-screen max-w-[1600px] print:block print:max-w-none">
      <aside
        class="border-r border-slate-200 bg-white/95 backdrop-blur transition-all duration-300 print:hidden"
        :class="sidebarClasses"
      >
        <div class="flex h-20 items-center gap-3 border-b border-slate-200 px-4">
          <img :src="schoolCrestUrl || '/images/default_school_crest.svg'" :alt="schoolName ? `${schoolName} crest` : 'School crest'" class="h-10 w-10 rounded-lg object-contain" />
          <div v-if="showSidebarText" class="leading-tight">
            <p class="font-brand text-sm uppercase tracking-[0.2em] text-slate-500">{{ shellText.recordsBadge }}</p>
            <p class="font-display text-lg font-bold">{{ platformTitle }}</p>
          </div>
        </div>

                <nav class="p-3">
          <div v-for="item in localizedMenu" :key="item.key" class="mb-1">
            <RouterLink
              :to="item.to"
              class="flex items-center rounded-xl px-3 py-3 text-sm font-medium transition"
              :class="[
                showSidebarText ? 'gap-3 justify-start' : 'justify-center',
                isItemActive(item) ? 'bg-[#0f8ea8] text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100',
              ]"
              @click="onMenuClick"
            >
              <span class="inline-flex h-5 w-5 items-center justify-center">
                <svg v-if="item.key === 'dashboard'" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                  <rect x="2" y="2" width="7" height="7" rx="1.5" />
                  <rect x="11" y="2" width="7" height="4.5" rx="1.5" />
                  <rect x="11" y="8" width="7" height="10" rx="1.5" />
                  <rect x="2" y="11" width="7" height="7" rx="1.5" />
                </svg>
                <svg v-else-if="item.key === 'school'" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                  <rect x="2" y="4" width="16" height="13" rx="2" />
                  <rect x="5" y="7" width="2" height="2" fill="white" />
                  <rect x="9" y="7" width="2" height="2" fill="white" />
                  <rect x="13" y="7" width="2" height="2" fill="white" />
                  <rect x="9" y="12" width="2" height="5" fill="white" />
                </svg>
                <svg v-else-if="item.key === 'students' || item.key === 'student-details'" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                  <circle cx="10" cy="6" r="3" />
                  <path d="M3 17c0-3.1 3.1-5 7-5s7 1.9 7 5" />
                </svg>
                <svg v-else-if="item.key === 'grades'" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                  <rect x="3" y="2" width="14" height="16" rx="2" />
                  <rect x="6" y="6" width="8" height="1.5" rx="0.7" fill="white" />
                  <rect x="6" y="9" width="8" height="1.5" rx="0.7" fill="white" />
                  <rect x="6" y="12" width="5" height="1.5" rx="0.7" fill="white" />
                </svg>
                <svg v-else-if="item.key === 'classes'" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                  <rect x="2" y="4" width="16" height="12" rx="2" />
                  <rect x="5" y="7" width="10" height="1.5" rx="0.7" fill="white" />
                  <rect x="5" y="10" width="10" height="1.5" rx="0.7" fill="white" />
                </svg>
                <svg v-else-if="item.key === 'staff'" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                  <circle cx="7" cy="7" r="2.5" />
                  <circle cx="13" cy="7" r="2.5" />
                  <path d="M2.5 16c0-2.5 2.2-4 4.5-4s4.5 1.5 4.5 4" />
                  <path d="M8.5 16c.2-2.2 2.1-3.5 4.2-3.5 2.3 0 4.3 1.4 4.8 3.5" />
                </svg>
                <svg v-else-if="item.key === 'payments' || item.key === 'payments-history'" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                  <rect x="2" y="4" width="16" height="12" rx="2" />
                  <rect x="4.5" y="7" width="11" height="1.8" rx="0.8" fill="white" />
                  <circle cx="14" cy="12.5" r="1.5" fill="white" />
                </svg>
                <svg v-else-if="item.key === 'reports'" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                  <rect x="3" y="2" width="14" height="16" rx="2" />
                  <rect x="6" y="11" width="2" height="4" fill="white" />
                  <rect x="9" y="8" width="2" height="7" fill="white" />
                  <rect x="12" y="6" width="2" height="9" fill="white" />
                </svg>
                <svg v-else viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                  <circle cx="10" cy="10" r="7" />
                </svg>
              </span>
              <span v-if="showSidebarText" class="truncate">{{ item.label }}</span>
            </RouterLink>

            <div v-if="showSidebarText && item.children && item.children.length > 0" class="ml-10 mt-1 space-y-1">
              <RouterLink
                v-for="child in item.children"
                :key="child.key"
                :to="child.to"
                class="block rounded-lg px-3 py-2 text-xs font-semibold transition"
                :class="isActive(child.to) ? 'bg-cyan-50 text-cyan-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'"
                @click="onMenuClick"
              >
                {{ child.label }}
              </RouterLink>
            </div>
          </div>
        </nav>
      </aside>

      <main class="min-w-0 flex-1 print:min-w-full print:overflow-visible" :class="isMobile ? '' : 'overflow-hidden'">
        <header class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-slate-200 bg-white/90 px-3 sm:px-5 backdrop-blur print:hidden">
          <div class="min-w-0 flex items-center gap-2 sm:gap-3">
            <button
              v-if="isMobile"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              @click="ui.toggleMobileSidebar"
            >
              {{ shellText.menu }}
            </button>
            <button
              v-else
              class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
              :aria-label="ui.sidebarOpen ? shellText.collapse : shellText.expand"
              :title="ui.sidebarOpen ? shellText.collapse : shellText.expand"
              @click="ui.toggleSidebar"
            >
              <svg
                v-if="ui.sidebarOpen"
                viewBox="0 0 20 20"
                fill="currentColor"
                class="h-5 w-5"
                aria-hidden="true"
              >
                <path
                  fill-rule="evenodd"
                  d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z"
                  clip-rule="evenodd"
                />
              </svg>
              <svg
                v-else
                viewBox="0 0 20 20"
                fill="currentColor"
                class="h-5 w-5"
                aria-hidden="true"
              >
                <path
                  fill-rule="evenodd"
                  d="M7.22 4.22a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 1 1-1.06-1.06L11.94 10 7.22 5.28a.75.75 0 0 1 0-1.06Z"
                  clip-rule="evenodd"
                />
              </svg>
            </button>
            <p class="font-display text-sm sm:text-xl truncate">{{ headerTitle }}</p>
          </div>

          <div class="flex items-center gap-2 sm:gap-3">
            <div class="hidden items-center gap-2 md:flex">
              <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500" :title="shellText.language">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                  <path fill-rule="evenodd" d="M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15ZM6.84 5.2A10.9 10.9 0 0 0 5.9 9.25h2.24c.08-1.45.36-2.84.82-4.05H6.84Zm3.16 0c-.5 1.16-.81 2.57-.9 4.05h1.8c-.08-1.48-.4-2.89-.9-4.05Zm1.94 0c.46 1.21.74 2.6.82 4.05H15.1a10.9 10.9 0 0 0-.94-4.05h-2.22ZM15.1 10.75h-2.34a12.8 12.8 0 0 1-.82 4.05h2.22c.5-1.18.82-2.57.94-4.05ZM10.94 14.8c.5-1.16.81-2.57.9-4.05h-1.8c.08 1.48.4 2.89.9 4.05Zm-1.98 0a12.8 12.8 0 0 1-.82-4.05H5.9c.12 1.48.44 2.87.94 4.05h2.12Z" clip-rule="evenodd" />
                </svg>
              </span>
              <select v-model="selectedLanguage" :aria-label="shellText.language" :title="shellText.language" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 outline-none ring-cyan-500 focus:ring-2">
                <option value="en">English</option>
                <option value="si">සිංහල</option>
                <option value="ta">தமிழ்</option>
              </select>
            </div>
            <div class="hidden rounded-full bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 sm:block sm:text-sm">
              {{ userLabel }}
            </div>
            <button
              class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              @click="logout"
            >
              {{ shellText.logout }}
            </button>
          </div>
        </header>

        <section class="p-4 sm:p-6 md:p-8 print:p-0">
          <slot />
        </section>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { clearAuthSession, getUser } from '../services/auth'
import { loadModuleCatalog, resolveModulePath } from '../services/modules'
import { useUiStore, type UiLanguage } from '../stores/ui'
import { pickLocalizedText } from '../utils/uiText'

interface MenuItem {
  key: string
  label: string
  to: string
  children?: MenuItem[]
}

interface SchoolDetailsResponse {
  school?: {
    sch_name?: string | null
    crest_url?: string | null
  } | null
}

const ui = useUiStore()
const route = useRoute()
const router = useRouter()
const currentUser = getUser()
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')
const isPrincipal = computed(() => (currentUser?.role_id ?? 0) === 2 || roleName === 'principal')
const isSdsUser = computed(() => (currentUser?.role_id ?? 0) === 4 || roleName === 'sds user')
const isStudent = computed(() => (currentUser?.role_id ?? 0) === 7 || roleName === 'student')
const schoolName = ref('')
const schoolCrestUrl = ref('')
type SchoolIdentityDetail = {
  schoolName?: string | null
  crestUrl?: string | null
}

const shellText = computed(() => {
  if (ui.language === 'si') {
    return {
      adminPlatform: 'පරිපාලන වේදිකාව',
      userPlatform: 'පරිශීලක වේදිකාව',
      recordsBadge: 'වාර්තා',
      menu: 'මෙනු',
      collapse: 'සඟවන්න',
      expand: 'විහිදුවන්න',
      headerTitle: 'වාර්තා',
      adminHeaderTitle: 'පාසල් වාර්තා',
      logout: 'ඉවත්වන්න',
      language: 'භාෂාව',
      authenticatedUser: 'සත්‍යාපිත පරිශීලකයා',
      dashboard: 'පුවරුව',
    }
  }

  if (ui.language === 'ta') {
    return {
      adminPlatform: 'நிர்வாக தளம்',
      userPlatform: 'பயனர் தளம்',
      recordsBadge: 'பதிவுகள்',
      menu: 'பட்டியல்',
      collapse: 'சுருக்கு',
      expand: 'விரிவு',
      headerTitle: 'பதிவுகள்',
      adminHeaderTitle: 'பள்ளி பதிவுகள்',
      logout: 'வெளியேறு',
      language: 'மொழி',
      authenticatedUser: 'உறுதிப்படுத்தப்பட்ட பயனர்',
      dashboard: 'கட்டுப்பாட்டு பலகை',
    }
  }

  return {
    adminPlatform: 'Admin Platform',
    userPlatform: 'User Platform',
    recordsBadge: 'Records',
    menu: 'Menu',
    collapse: 'Collapse',
    expand: 'Expand',
    headerTitle: 'Records',
    adminHeaderTitle: 'School Records',
    logout: 'Logout',
    language: 'Language',
    authenticatedUser: 'Authenticated User',
    dashboard: 'Dashboard',
  }
})

const headerTitle = computed(() => {
  if (isAdmin.value) {
    return shellText.value.adminHeaderTitle
  }

  const normalizedSchoolName = schoolName.value.trim()
  if (normalizedSchoolName === '') {
    return shellText.value.headerTitle
  }

  return `${normalizedSchoolName} ${shellText.value.headerTitle}`
})

const platformTitle = computed(() => (isAdmin.value ? shellText.value.adminPlatform : shellText.value.userPlatform))

const selectedLanguage = computed<UiLanguage>({
  get: () => ui.language,
  set: (value) => ui.setLanguage(value),
})

const menu = ref<MenuItem[]>([
  { key: 'dashboard', label: 'Dashboard', to: '/' },
])

const localizedMenu = computed(() => {
  return menu.value.map((item) => {
    return {
      ...item,
      label: pickLocalizedText(ui.language, {
        en: {
          dashboard: 'Dashboard',
          school: 'School',
          students: 'Students',
          'student-details': 'Student Details',
          grades: 'Grades',
          classes: 'Classes',
          staff: 'Staff',
          payments: 'Payments',
          'payments-history': 'Payments History',
          reports: 'Reports',
        }[item.key] ?? item.label,
        si: {
          dashboard: 'පුවරුව',
          school: 'පාසල',
          students: 'සිසුන්',
          'student-details': 'සිසු විස්තර',
          grades: 'ශ්‍රේණි',
          classes: 'පන්ති',
          staff: 'කාර්ය මණ්ඩලය',
          payments: 'ගෙවීම්',
          'payments-history': 'ගෙවීම් ඉතිහාසය',
          reports: 'වාර්තා',
        }[item.key] ?? item.label,
        ta: {
          dashboard: 'கட்டுப்பாட்டு பலகை',
          school: 'பள்ளி',
          students: 'மாணவர்கள்',
          'student-details': 'மாணவர் விவரங்கள்',
          grades: 'தரங்கள்',
          classes: 'வகுப்புகள்',
          staff: 'பணியாளர்கள்',
          payments: 'கட்டணங்கள்',
          'payments-history': 'கட்டண வரலாறு',
          reports: 'அறிக்கைகள்',
        }[item.key] ?? item.label,
      }),
      children: item.children?.map((child) => ({
        ...child,
        label: pickLocalizedText(ui.language, {
          en: {
            'students-in-classes': 'Students in Classes',
            'students-report': 'Student Reports',
            'payments-fee-types': 'Fee Types',
          }[child.key] ?? child.label,
          si: {
            'students-in-classes': 'පන්තිවල සිසුන්',
            'students-report': 'සිසු වාර්තා',
            'payments-fee-types': 'ගාස්තු වර්ග',
          }[child.key] ?? child.label,
          ta: {
            'students-in-classes': 'வகுப்புகளில் மாணவர்கள்',
            'students-report': 'மாணவர் அறிக்கைகள்',
            'payments-fee-types': 'கட்டண வகைகள்',
          }[child.key] ?? child.label,
        }),
      })),
    }
  })
})

const isMobile = ref(false)
let mediaQuery: MediaQueryList | null = null

const syncViewport = (): void => {
  isMobile.value = mediaQuery?.matches ?? false

  if (!isMobile.value) {
    ui.closeMobileSidebar()
  }
}

const sidebarClasses = computed(() => {
  if (isMobile.value) {
    return ui.mobileSidebarOpen
      ? 'fixed inset-y-0 left-0 z-40 w-72'
      : 'fixed inset-y-0 -left-80 z-40 w-72'
  }

  return ui.sidebarOpen ? 'w-72' : 'w-20'
})

const showSidebarText = computed(() => (isMobile.value ? ui.mobileSidebarOpen : ui.sidebarOpen))

const userLabel = computed(() => {
  if (!currentUser) {
    return shellText.value.authenticatedUser
  }

  return currentUser.role_name ? `${currentUser.username} (${currentUser.role_name})` : currentUser.username
})

const isActive = (to: string): boolean => {
  if (to === '/') {
    return route.path === '/'
  }

  return route.path.startsWith(to)
}

const isItemActive = (item: MenuItem): boolean => {
  if (isActive(item.to)) {
    return true
  }

  return Array.isArray(item.children) && item.children.some((child) => isActive(child.to))
}

const loadMenu = async (): Promise<void> => {
  const modules = await loadModuleCatalog()

  const mappedModules: MenuItem[] = modules
    .map((module) => ({
      key: isStudent.value && module.key === 'students'
        ? 'student-details'
        : (isStudent.value && module.key === 'payments' ? 'payments-history' : module.key),
      label: isStudent.value && module.key === 'students'
        ? 'Student Details'
        : (isStudent.value && module.key === 'payments' ? 'Payments History' : module.label),
      to: isStudent.value && module.key === 'students'
        ? '/students/me'
        : resolveModulePath(module),
      children:
        module.key === 'students' && !isStudent.value
          ? [
              { key: 'students-in-classes', label: 'Students in Classes', to: '/students/in-classes' },
              { key: 'students-report', label: 'Student Reports', to: '/students/report' },
            ]
          : module.key === 'payments' && (isAdmin.value || isPrincipal.value || isSdsUser.value)
            ? [
                { key: 'payments-fee-types', label: 'Fee Types', to: '/module/payments/fee-types' },
              ]
            : undefined,
    }))
    .filter((item) => !['school', 'school-details', 'school_detail'].includes(item.key))
    .filter((item) => !(isSdsUser.value && item.key === 'students'))
    .filter((item) => !isStudent.value || ['student-details', 'payments-history'].includes(item.key))

  const schoolMenu: MenuItem = {
    key: 'school',
    label: 'School',
    to: '/school',
  }

  const gradesIndex = mappedModules.findIndex((item) => item.key === 'grades')

  const orderedModules = gradesIndex >= 0
    ? [...mappedModules.slice(0, gradesIndex), schoolMenu, ...mappedModules.slice(gradesIndex)]
    : [...mappedModules, schoolMenu]

  menu.value = [
    { key: 'dashboard', label: 'Dashboard', to: '/' },
    ...orderedModules,
  ]
}

const loadHeaderSchoolName = async (): Promise<void> => {
  if (isAdmin.value) {
    schoolName.value = ''
    schoolCrestUrl.value = ''
    return
  }

  try {
    const { data } = await api.get<SchoolDetailsResponse>('/school/details')
    schoolName.value = String(data.school?.sch_name ?? '').trim()
    schoolCrestUrl.value = String(data.school?.crest_url ?? '').trim()
  } catch {
    schoolName.value = ''
    schoolCrestUrl.value = ''
  }
}

const handleSchoolIdentityUpdated = (event: Event): void => {
  const detail = (event as CustomEvent<SchoolIdentityDetail>).detail
  schoolName.value = String(detail?.schoolName ?? '').trim()
  schoolCrestUrl.value = String(detail?.crestUrl ?? '').trim()
}

const onMenuClick = (): void => {
  if (isMobile.value) {
    ui.closeMobileSidebar()
  }
}

const logout = async (): Promise<void> => {
  try {
    await api.post('/auth/logout')
  } catch {
    // Ignore logout API errors and clear local state anyway.
  }

  clearAuthSession()
  await router.push('/login')
}

onMounted(async () => {
  mediaQuery = window.matchMedia('(max-width: 767px)')
  syncViewport()

  if (mediaQuery.addEventListener) {
    mediaQuery.addEventListener('change', syncViewport)
  } else {
    mediaQuery.addListener(syncViewport)
  }

  await loadMenu()
  await loadHeaderSchoolName()
  window.addEventListener('sds:school-identity-updated', handleSchoolIdentityUpdated as EventListener)
})

onUnmounted(() => {
  window.removeEventListener('sds:school-identity-updated', handleSchoolIdentityUpdated as EventListener)

  if (!mediaQuery) {
    return
  }

  if (mediaQuery.removeEventListener) {
    mediaQuery.removeEventListener('change', syncViewport)
  } else {
    mediaQuery.removeListener(syncViewport)
  }
})
</script>




