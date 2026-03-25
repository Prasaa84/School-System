<template>
  <div class="min-h-screen bg-slate-100">
    <header class="bg-[#550100] py-4 text-center text-slate-200 shadow-sm">
      <h1 class="font-display text-2xl font-bold tracking-wide">RICHMOND COLLEGE - GALLE</h1>
      <p class="mt-1 text-sm">SDS Payments Management System</p>
    </header>

    <main class="mx-auto grid w-full max-w-6xl gap-6 px-4 py-10 md:grid-cols-[1.1fr_1fr] md:items-center">
      <section class="hidden justify-center md:flex">
        <img src="/images/richmond_logo.png" alt="Richmond College logo" class="max-h-[360px] w-auto drop-shadow-lg" />
      </section>

      <section class="mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-lg">
        <div class="mb-4 text-center">
          <img src="/images/login_page_user.png" alt="User icon" class="mx-auto mb-3 h-20 w-20 rounded-full" />
          <h2 class="font-display text-2xl font-bold text-slate-900">Sign in to continue</h2>
          <p class="mt-1 text-sm text-slate-600">Use your SDS account credentials</p>
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
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-[#550100] focus:ring-2"
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
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-[#550100] focus:ring-2"
            />
          </div>

          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-[#550100] focus:ring-[#550100]" />
            Remember me
          </label>

          <p v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ errorMessage }}
          </p>

          <button
            type="submit"
            :disabled="loading"
            class="w-full rounded-lg bg-[#0b5ed7] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#0a53be] disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ loading ? 'Signing in...' : 'Sign in' }}
          </button>
        </form>
      </section>
    </main>

    <footer class="bg-[#03084D] py-5 text-center text-xs text-slate-300">
      © {{ year }} Richmond College, Galle / Coded by Richmond College IT Society
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
    errorMessage.value = 'Invalid username or password.'
  } finally {
    loading.value = false
  }
}
</script>
