<template>
  <div class="min-h-screen bg-slate-100 text-slate-900">
    <div class="mx-auto flex min-h-screen max-w-[1600px]">
      <aside
        class="border-r border-slate-200 bg-white/95 backdrop-blur transition-all duration-300"
        :class="ui.sidebarOpen ? 'w-72' : 'w-20'"
      >
        <div class="flex h-20 items-center gap-3 border-b border-slate-200 px-4">
          <img src="/images/richmond_logo_28_32.png" alt="Richmond" class="h-10 w-10 rounded-lg object-contain" />
          <div v-if="ui.sidebarOpen" class="leading-tight">
            <p class="font-brand text-sm uppercase tracking-[0.2em] text-slate-500">SDS</p>
            <p class="font-display text-lg font-bold">Admin Platform</p>
          </div>
        </div>

        <nav class="p-3">
          <RouterLink
            v-for="item in menu"
            :key="item.key"
            :to="item.to"
            class="mb-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition"
            :class="isActive(item.to) ? 'bg-[#0f8ea8] text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100'"
          >
            <span class="inline-block h-2 w-2 rounded-full" :class="isActive(item.to) ? 'bg-white' : 'bg-[#0f8ea8]'" />
            <span v-if="ui.sidebarOpen">{{ item.label }}</span>
          </RouterLink>
        </nav>
      </aside>

      <main class="flex-1">
        <header class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-slate-200 bg-white/90 px-5 backdrop-blur">
          <div class="flex items-center gap-3">
            <button
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              @click="ui.toggleSidebar"
            >
              {{ ui.sidebarOpen ? 'Collapse' : 'Expand' }}
            </button>
            <p class="font-display text-xl">Richmond College SDS Records</p>
          </div>

          <div class="flex items-center gap-3">
            <div class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
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

        <section class="p-6 md:p-8">
          <slot />
        </section>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
  await loadMenu()
})
</script>
