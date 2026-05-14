<template>
  <div class="min-h-screen bg-slate-100 text-slate-900">
    <div v-if="isMobile && ui.mobileSidebarOpen" class="fixed inset-0 z-30 bg-slate-900/40" @click="ui.closeMobileSidebar" />

    <div class="mx-auto flex min-h-screen max-w-[1600px]">
      <aside
        class="border-r border-slate-200 bg-white/95 backdrop-blur transition-all duration-300"
        :class="sidebarClasses"
      >
        <div class="flex h-20 items-center gap-3 border-b border-slate-200 px-4">
          <img src="/images/richmond_logo_28_32.png" alt="Richmond" class="h-10 w-10 rounded-lg object-contain" />
          <div v-if="showSidebarText" class="leading-tight">
            <p class="font-brand text-sm uppercase tracking-[0.2em] text-slate-500">SDS</p>
            <p class="font-display text-lg font-bold">{{ shellText.adminPlatform }}</p>
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
                <svg v-else-if="item.key === 'students'" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
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
                <svg v-else-if="item.key === 'payments'" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
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

      <main class="min-w-0 flex-1" :class="isMobile ? '' : 'overflow-hidden'">
        <header class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-slate-200 bg-white/90 px-3 sm:px-5 backdrop-blur">
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
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              @click="ui.toggleSidebar"
            >
              {{ ui.sidebarOpen ? shellText.collapse : shellText.expand }}
            </button>
            <p class="font-display text-sm sm:text-xl truncate">{{ shellText.headerTitle }}</p>
          </div>

          <div class="flex items-center gap-2 sm:gap-3">
            <div class="hidden items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1.5 md:flex">
              <span class="text-xs font-semibold text-slate-600">{{ shellText.language }}</span>
              <select v-model="selectedLanguage" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 outline-none ring-cyan-500 focus:ring-2">
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

        <section class="p-4 sm:p-6 md:p-8">
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

const ui = useUiStore()
const route = useRoute()
const router = useRouter()

const shellText = computed(() => {
  if (ui.language === 'si') {
    return {
      adminPlatform: 'පරිපාලන වේදිකාව',
      menu: 'මෙනු',
      collapse: 'සඟවන්න',
      expand: 'විහිදුවන්න',
      headerTitle: 'රිච්මන්ඩ් විද්‍යාල SDS වාර්තා',
      logout: 'ඉවත්වන්න',
      language: 'භාෂාව',
      authenticatedUser: 'සත්‍යාපිත පරිශීලකයා',
      dashboard: 'පුවරුව',
    }
  }

  if (ui.language === 'ta') {
    return {
      adminPlatform: 'நிர்வாக தளம்',
      menu: 'பட்டியல்',
      collapse: 'சுருக்கு',
      expand: 'விரிவு',
      headerTitle: 'ரிச்மண்ட் கல்லூரி SDS பதிவுகள்',
      logout: 'வெளியேறு',
      language: 'மொழி',
      authenticatedUser: 'உறுதிப்படுத்தப்பட்ட பயனர்',
      dashboard: 'கட்டுப்பாட்டு பலகை',
    }
  }

  return {
    adminPlatform: 'Admin Platform',
    menu: 'Menu',
    collapse: 'Collapse',
    expand: 'Expand',
    headerTitle: 'Richmond College SDS Records',
    logout: 'Logout',
    language: 'Language',
    authenticatedUser: 'Authenticated User',
    dashboard: 'Dashboard',
  }
})

const selectedLanguage = computed<UiLanguage>({
  get: () => ui.language,
  set: (value) => ui.setLanguage(value),
})

const currentUser = getUser()
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
          grades: 'Grades',
          classes: 'Classes',
          staff: 'Staff',
          payments: 'Payments',
          reports: 'Reports',
        }[item.key] ?? item.label,
        si: {
          dashboard: 'පුවරුව',
          school: 'පාසල',
          students: 'සිසුන්',
          grades: 'ශ්‍රේණි',
          classes: 'පන්ති',
          staff: 'කාර්ය මණ්ඩලය',
          payments: 'ගෙවීම්',
          reports: 'වාර්තා',
        }[item.key] ?? item.label,
        ta: {
          dashboard: 'கட்டுப்பாட்டு பலகை',
          school: 'பள்ளி',
          students: 'மாணவர்கள்',
          grades: 'தரங்கள்',
          classes: 'வகுப்புகள்',
          staff: 'பணியாளர்கள்',
          payments: 'கட்டணங்கள்',
          reports: 'அறிக்கைகள்',
        }[item.key] ?? item.label,
      }),
      children: item.children?.map((child) => ({
        ...child,
        label: pickLocalizedText(ui.language, {
          en: {
            'student-class-assignment': 'Students in Classes',
          }[child.key] ?? child.label,
          si: {
            'student-class-assignment': 'පන්තිවල සිසුන්',
          }[child.key] ?? child.label,
          ta: {
            'student-class-assignment': 'வகுப்புகளில் மாணவர்கள்',
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
      key: module.key,
      label: module.label,
      to: resolveModulePath(module),
      children: module.key === 'students'
        ? [{ key: 'student-class-assignment', label: 'Students in Classes', to: '/students/in-classes' }]
        : undefined,
    }))
    .filter((item) => !['school', 'school-details', 'school_detail'].includes(item.key))

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
})

onUnmounted(() => {
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




