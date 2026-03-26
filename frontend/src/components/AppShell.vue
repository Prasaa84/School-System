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
            <p class="font-display text-lg font-bold">Admin Platform</p>
          </div>
        </div>

        <nav class="p-3">
          <RouterLink
            v-for="item in menu"
            :key="item.key"
            :to="item.to"
            class="mb-1 flex items-center rounded-xl px-3 py-3 text-sm font-medium transition"
            :class="[
              showSidebarText ? 'gap-3 justify-start' : 'justify-center',
              isActive(item.to) ? 'bg-[#0f8ea8] text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100',
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
              Menu
            </button>
            <button
              v-else
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              @click="ui.toggleSidebar"
            >
              {{ ui.sidebarOpen ? 'Collapse' : 'Expand' }}
            </button>
            <p class="font-display text-sm sm:text-xl truncate">Richmond College SDS Records</p>
          </div>

          <div class="flex items-center gap-2 sm:gap-3">
            <div class="hidden rounded-full bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 sm:block sm:text-sm">
              {{ userLabel }}
            </div>
            <button
              class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              @click="logout"
            >
              Logout
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
import { useUiStore } from '../stores/ui'

interface MenuItem {
  key: string
  label: string
  to: string
}

const ui = useUiStore()
const route = useRoute()
const router = useRouter()

const currentUser = getUser()
const menu = ref<MenuItem[]>([
  { key: 'dashboard', label: 'Dashboard', to: '/' },
])

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
    return 'Authenticated User'
  }

  return currentUser.role_name ? `${currentUser.username} (${currentUser.role_name})` : currentUser.username
})

const isActive = (to: string): boolean => {
  if (to === '/') {
    return route.path === '/'
  }

  return route.path.startsWith(to)
}

const loadMenu = async (): Promise<void> => {
  const modules = await loadModuleCatalog()

  menu.value = [
    { key: 'dashboard', label: 'Dashboard', to: '/' },
    ...modules.map((module) => ({
      key: module.key,
      label: module.label,
      to: resolveModulePath(module),
    })),
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
