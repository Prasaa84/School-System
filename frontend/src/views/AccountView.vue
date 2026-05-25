<template>
  <div class="space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h1 class="font-display text-2xl font-bold text-slate-900">{{ text.title }}</h1>
      <p class="mt-2 text-sm text-slate-600">{{ text.subtitle }}</p>
    </header>

    <p v-if="message" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
      {{ message }}
    </p>

    <p v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ errorMessage }}
    </p>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div v-if="loading" class="py-6 text-sm text-slate-500">{{ text.loading }}</div>

      <div v-else class="grid gap-5 md:grid-cols-2">
        <div v-for="item in accountInfoRows" :key="item.label" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ item.label }}</p>
          <p class="mt-2 text-base font-semibold text-slate-900">{{ item.value }}</p>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="font-display text-xl font-bold text-slate-900">{{ text.changePassword }}</h2>
      <p class="mt-2 text-sm text-slate-600">{{ text.passwordHint }}</p>

      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitPasswordChange">
        <label class="text-sm text-slate-700 md:col-span-2">
          {{ text.currentPassword }}
          <input v-model="form.current_password" type="password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" required />
        </label>

        <label class="text-sm text-slate-700">
          {{ text.newPassword }}
          <input v-model="form.password" type="password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" required />
        </label>

        <label class="text-sm text-slate-700">
          {{ text.confirmPassword }}
          <input v-model="form.password_confirmation" type="password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" required />
        </label>

        <div class="md:col-span-2">
          <button class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
            {{ saving ? text.saving : text.save }}
          </button>
        </div>
      </form>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import api from '../services/api'
import { useLocalizedText } from '../utils/uiText'

interface AccountDetail {
  username: string
  role_name: string | null
  created_at: string | null
  is_enabled: boolean
}

const text = useLocalizedText({
  en: {
    title: 'Account',
    subtitle: 'View your login details and change your password.',
    loading: 'Loading account details...',
    username: 'Username',
    status: 'Account Status',
    enabled: 'Enabled',
    disabled: 'Disabled',
    createdDate: 'Created Date',
    role: 'Role',
    changePassword: 'Change Password',
    passwordHint: 'Your username cannot be changed here. Only update your password.',
    currentPassword: 'Current Password',
    newPassword: 'New Password',
    confirmPassword: 'Confirm Password',
    save: 'Save Password',
    saving: 'Saving...',
    unknownDate: 'Not available',
    loadError: 'Unable to load account details.',
    saveError: 'Unable to update password.',
    saveSuccess: 'Password updated successfully.',
  },
  si: {
    title: 'ගිණුම',
    subtitle: 'ඔබගේ පිවිසුම් විස්තර බලන්න සහ මුරපදය වෙනස් කරන්න.',
    loading: 'ගිණුම් විස්තර පූරණය වෙමින්...',
    username: 'පරිශීලක නාමය',
    status: 'ගිණුම් තත්ත්වය',
    enabled: 'සක්‍රීය',
    disabled: 'අක්‍රීය',
    createdDate: 'සාදන ලද දිනය',
    role: 'භූමිකාව',
    changePassword: 'මුරපදය වෙනස් කරන්න',
    passwordHint: 'මෙහි පරිශීලක නාමය වෙනස් කළ නොහැක. වෙනස් කළ හැක්කේ මුරපදය පමණි.',
    currentPassword: 'දැනට ඇති මුරපදය',
    newPassword: 'නව මුරපදය',
    confirmPassword: 'මුරපදය තහවුරු කරන්න',
    save: 'මුරපදය සුරකින්න',
    saving: 'සුරකිනවා...',
    unknownDate: 'නොමැත',
    loadError: 'ගිණුම් විස්තර පූරණය කළ නොහැක.',
    saveError: 'මුරපදය යාවත්කාලීන කළ නොහැක.',
    saveSuccess: 'මුරපදය සාර්ථකව යාවත්කාලීන කරන ලදී.',
  },
  ta: {
    title: 'கணக்கு',
    subtitle: 'உங்கள் உள்நுழைவு விவரங்களைப் பார்த்து கடவுச்சொல்லை மாற்றுங்கள்.',
    loading: 'கணக்கு விவரங்கள் ஏற்றப்படுகிறது...',
    username: 'பயனர் பெயர்',
    status: 'கணக்கு நிலை',
    enabled: 'செயலில்',
    disabled: 'செயலற்றது',
    createdDate: 'உருவாக்கப்பட்ட தேதி',
    role: 'பங்கு',
    changePassword: 'கடவுச்சொல்லை மாற்றவும்',
    passwordHint: 'இங்கே பயனர் பெயரை மாற்ற முடியாது. கடவுச்சொல்லை மட்டும் மாற்றலாம்.',
    currentPassword: 'தற்போதைய கடவுச்சொல்',
    newPassword: 'புதிய கடவுச்சொல்',
    confirmPassword: 'கடவுச்சொல்லை உறுதிப்படுத்தவும்',
    save: 'கடவுச்சொல்லை சேமிக்கவும்',
    saving: 'சேமிக்கப்படுகிறது...',
    unknownDate: 'கிடைக்கவில்லை',
    loadError: 'கணக்கு விவரங்களை ஏற்ற முடியவில்லை.',
    saveError: 'கடவுச்சொல்லை புதுப்பிக்க முடியவில்லை.',
    saveSuccess: 'கடவுச்சொல் வெற்றிகரமாக புதுப்பிக்கப்பட்டது.',
  },
})

const loading = ref(true)
const saving = ref(false)
const message = ref('')
const errorMessage = ref('')
const account = reactive<AccountDetail>({
  username: '',
  role_name: '',
  created_at: null,
  is_enabled: false,
})
const form = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const formattedCreatedAt = computed(() => {
  if (!account.created_at) {
    return text.value.unknownDate
  }

  const parsed = new Date(account.created_at)
  if (Number.isNaN(parsed.getTime())) {
    return account.created_at
  }

  return parsed.toLocaleString()
})

const accountStatusLabel = computed(() => (account.is_enabled ? text.value.enabled : text.value.disabled))

const accountInfoRows = computed(() => [
  { label: text.value.username, value: account.username || '-' },
  { label: text.value.status, value: accountStatusLabel.value },
  { label: text.value.createdDate, value: formattedCreatedAt.value },
  { label: text.value.role, value: account.role_name || '-' },
])

const extractApiMessage = (error: unknown): string | null => {
  const payload = (error as { response?: { data?: { message?: unknown } } })?.response?.data
  const value = payload?.message
  return typeof value === 'string' && value.trim() !== '' ? value : null
}

const loadAccount = async (): Promise<void> => {
  loading.value = true
  errorMessage.value = ''

  try {
    const { data } = await api.get<{ data?: AccountDetail }>('/auth/account')
    account.username = String(data.data?.username ?? '')
    account.role_name = data.data?.role_name ?? ''
    account.created_at = data.data?.created_at ?? null
    account.is_enabled = Boolean(data.data?.is_enabled)
  } catch (error: unknown) {
    errorMessage.value = extractApiMessage(error) || text.value.loadError
  } finally {
    loading.value = false
  }
}

const submitPasswordChange = async (): Promise<void> => {
  saving.value = true
  message.value = ''
  errorMessage.value = ''

  try {
    const { data } = await api.put<{ message?: string }>('/auth/password', {
      current_password: form.current_password,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })

    message.value = data.message || text.value.saveSuccess
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
  } catch (error: unknown) {
    errorMessage.value = extractApiMessage(error) || text.value.saveError
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadAccount()
})
</script>
