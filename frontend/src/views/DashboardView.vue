<template>
  <div class="space-y-6">
    <header class="rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-900 via-cyan-900 to-emerald-700 p-7 text-white shadow-xl">
      <p class="font-brand text-xs uppercase tracking-[0.2em] text-cyan-200">School Management Dashboard</p>
      <h1 class="mt-2 font-display text-3xl font-bold md:text-4xl">Welcome to the Control Center</h1>
      <p class="mt-2 max-w-3xl text-sm text-cyan-100 md:text-base"></p>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">Students</p>
        <p class="mt-1 text-xs text-slate-400">Year: {{ summary.students_latest_year ?? 'N/A' }}</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.students_total }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">Academic Staff</p>
        <p class="mt-1 text-xs text-slate-400">All active</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.staff_total }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">Grades</p>
        <p class="mt-1 text-xs text-slate-400">Year: {{ summary.grades_latest_year ?? 'N/A' }}</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.grades_total }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">Classes</p>
        <p class="mt-1 text-xs text-slate-400">Year: {{ summary.classes_latest_year ?? 'N/A' }}</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.classes_total }}</p>
      </article>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-display text-xl font-bold">Data Status</h2>
        <div class="mt-4 space-y-2 text-sm text-slate-700">
          <p><strong>Students last updated:</strong> {{ formatDate(summary.students_last_updated) }}</p>
          <p><strong>Staff last updated:</strong> {{ formatDate(summary.staff_last_updated) }}</p>
        </div>
      </article>

      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-display text-xl font-bold">Available Modules</h2>
        <ul class="mt-4 space-y-2 text-sm text-slate-700">
          <li>1. Students management (connected)</li>
          <li>2. Grades and Classes lookup (connected)</li>
          <li>3. Staff, Payments, Reports (next API rollout)</li>
        </ul>
      </article>
    </section>

    <p v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import api from '../services/api'

interface Summary {
  students_total: number
  staff_total: number
  grades_total: number
  classes_total: number
  students_last_updated: string | null
  staff_last_updated: string | null
  students_latest_year: number | null
  grades_latest_year: number | null
  classes_latest_year: number | null
}

interface DashboardSummaryResponse {
  summary: Summary
}

const error = ref('')
const summary = reactive<Summary>({
  students_total: 0,
  staff_total: 0,
  grades_total: 0,
  classes_total: 0,
  students_last_updated: null,
  staff_last_updated: null,
  students_latest_year: null,
  grades_latest_year: null,
  classes_latest_year: null,
})

const loadSummary = async (): Promise<void> => {
  try {
    const { data } = await api.get<DashboardSummaryResponse>('/dashboard/summary')
    Object.assign(summary, data.summary)
  } catch {
    error.value = 'Unable to load dashboard summary right now.'
  }
}

const formatDate = (value: string | null): string => {
  if (!value) return 'N/A'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return date.toLocaleString()
}

onMounted(async () => {
  await loadSummary()
})
</script>
