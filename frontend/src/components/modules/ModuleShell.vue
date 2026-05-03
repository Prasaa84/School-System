<template>
  <div class="space-y-5">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p class="font-brand text-xs uppercase tracking-[0.2em] text-slate-500">{{ text.workspace }}</p>
      <h1 class="mt-2 font-display text-2xl font-bold text-slate-900">{{ title }}</h1>
      <p class="mt-2 text-sm text-slate-600">{{ subtitle }}</p>
    </header>

    <section v-if="supportsReports" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
      <div class="flex gap-2">
        <button class="rounded-lg px-4 py-2 text-sm font-semibold" :class="activeTab === 'view' ? 'bg-cyan-600 text-white' : 'bg-slate-100 text-slate-700'" @click="$emit('change-tab', 'view')">{{ text.view }}</button>
        <button class="rounded-lg px-4 py-2 text-sm font-semibold" :class="activeTab === 'reports' ? 'bg-cyan-600 text-white' : 'bg-slate-100 text-slate-700'" @click="$emit('change-tab', 'reports')">{{ text.reports }}</button>
      </div>
    </section>

    <slot />

    <p v-if="message" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ message }}</p>
    <p v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { useLocalizedText } from '../../utils/uiText'

defineProps<{
  title: string
  subtitle: string
  supportsReports: boolean
  activeTab: 'view' | 'reports'
  message: string
  error: string
}>()

defineEmits<{
  (e: 'change-tab', tab: 'view' | 'reports'): void
}>()

const text = useLocalizedText({
  en: {
    workspace: 'Module Workspace',
    view: 'View',
    reports: 'Reports',
  },
  si: {
    workspace: 'මොඩියුල වැඩබිම',
    view: 'දර්ශනය',
    reports: 'වාර්තා',
  },
  ta: {
    workspace: 'தொகுதி பணியிடம்',
    view: 'பார்வை',
    reports: 'அறிக்கைகள்',
  },
})
</script>
