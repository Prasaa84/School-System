import { defineStore } from 'pinia'

export type UiLanguage = 'en' | 'si' | 'ta'

const LANGUAGE_STORAGE_KEY = 'sds.ui.language'

const normalizeLanguage = (value: unknown): UiLanguage => {
  if (value === 'si') return 'si'
  if (value === 'ta') return 'ta'
  return 'en'
}

const readStoredLanguage = (): UiLanguage => {
  if (typeof window === 'undefined') {
    return 'en'
  }

  try {
    return normalizeLanguage(window.localStorage.getItem(LANGUAGE_STORAGE_KEY))
  } catch {
    return 'en'
  }
}

export const useUiStore = defineStore('ui', {
  state: () => ({
    sidebarOpen: true,
    mobileSidebarOpen: false,
    language: readStoredLanguage() as UiLanguage,
  }),
  actions: {
    toggleSidebar() {
      this.sidebarOpen = !this.sidebarOpen
    },
    openMobileSidebar() {
      this.mobileSidebarOpen = true
    },
    closeMobileSidebar() {
      this.mobileSidebarOpen = false
    },
    toggleMobileSidebar() {
      this.mobileSidebarOpen = !this.mobileSidebarOpen
    },
    setLanguage(language: UiLanguage) {
      const normalized = normalizeLanguage(language)
      this.language = normalized

      if (typeof window !== 'undefined') {
        try {
          window.localStorage.setItem(LANGUAGE_STORAGE_KEY, normalized)
        } catch {
          // Ignore storage failures and keep in-memory state.
        }
      }
    },
  },
})
