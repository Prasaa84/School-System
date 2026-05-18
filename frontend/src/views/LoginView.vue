<template>
  <div class="min-h-screen bg-slate-100">
    <header class="bg-gradient-to-r from-cyan-700 via-cyan-600 to-teal-600 py-4 text-center text-white shadow-sm">
      <h1 class="font-display text-2xl font-bold tracking-wide">SCHOOL RECORDS</h1>
      <p class="mt-1 text-sm">Multi-School Management Portal</p>
    </header>

    <main class="mx-auto grid w-full max-w-6xl gap-6 px-4 py-10 md:grid-cols-[1.1fr_1fr] md:items-center">
      <section class="hidden items-center justify-center md:flex">
        <div class="max-w-md rounded-2xl border border-slate-200 bg-white/80 p-8 text-slate-700 shadow-lg">
          <h3 class="font-display text-2xl font-bold text-slate-900">Welcome to School Records</h3>
          <p class="mt-3 text-sm leading-6">
            Manage student records, staff information, classes, and reports in one place.
          </p>
          <p class="mt-2 text-sm leading-6">
            Sign in with your account to continue.
          </p>
        </div>
      </section>

      <section class="mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-lg">
        <div class="mb-4 text-center">
          <img src="/images/login_page_user.png" alt="User icon" class="mx-auto mb-3 h-20 w-20 rounded-full" />
          <h2 class="font-display text-2xl font-bold text-slate-900">Sign in to continue</h2>
          <p class="mt-1 text-sm text-slate-600">Use your account credentials</p>
        </div>

        <form class="space-y-4" @submit.prevent="submitLogin">
          <div>
            <label for="username" class="mb-1 block text-sm font-semibold text-slate-700">Username</label>
            <input
              id="username"
              v-model="form.username"
              type="text"
              autocomplete="username"
              required
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-600 focus:ring-2"
            />
          </div>

          <div>
            <label for="password" class="mb-1 block text-sm font-semibold text-slate-700">Password</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              required
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-600 focus:ring-2"
            />
          </div>

          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600" />
            Remember me
          </label>

          <p v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ errorMessage }}
          </p>

          <button
            type="submit"
            :disabled="loading"
            class="w-full rounded-lg bg-[#0f8ea8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#0d7c93] disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ loading ? 'Signing in...' : 'Sign in' }}
          </button>
        </form>
      </section>
    </main>

    <footer class="border-t border-slate-800 bg-slate-900 py-5 text-center text-xs text-slate-300">
      © {{ year }} School Records
    </footer>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { setAuthSession, type AuthUser } from '../services/auth'

interface LoginResponse {
  access_token: string
  token_type: string
  user: AuthUser
}

const router = useRouter()
const route = useRoute()

const year = new Date().getFullYear()
const loading = ref(false)
const errorMessage = ref('')

const form = reactive({
  username: '',
  password: '',
  remember: false,
})

const extractApiMessage = (error: unknown): string | null => {
  const payload = (error as { response?: { data?: { message?: unknown } } })?.response?.data
  const message = payload?.message

  return typeof message === 'string' && message.trim() !== '' ? message : null
}

const submitLogin = async (): Promise<void> => {
  loading.value = true
  errorMessage.value = ''

  try {
    const { data } = await api.post<LoginResponse>('/auth/login', {
      username: form.username,
      password: form.password,
      remember: form.remember,
    })

    setAuthSession(data.access_token, data.user)

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    await router.push(redirect)
  } catch (error: unknown) {
    errorMessage.value = extractApiMessage(error) || 'Invalid username or password.'
  } finally {
    loading.value = false
  }
}
</script>


