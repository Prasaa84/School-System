<template>
  <div class="space-y-5">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p class="font-brand text-xs uppercase tracking-[0.2em] text-slate-500">Module Workspace</p>
      <h1 class="mt-2 font-display text-2xl font-bold text-slate-900">{{ title }}</h1>
      <p class="mt-2 text-sm text-slate-600">{{ subtitle }}</p>
    </header>

    <section v-if="supportsReports" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
      <div class="flex gap-2">
        <button class="rounded-lg px-4 py-2 text-sm font-semibold" :class="activeTab === 'view' ? 'bg-cyan-600 text-white' : 'bg-slate-100 text-slate-700'" @click="activeTab = 'view'">View</button>
        <button class="rounded-lg px-4 py-2 text-sm font-semibold" :class="activeTab === 'reports' ? 'bg-cyan-600 text-white' : 'bg-slate-100 text-slate-700'" @click="activeTab = 'reports'">Reports</button>
      </div>
    </section>

    <section v-if="isGrades && activeTab === 'view'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="font-display text-xl font-bold">Grades (Latest Year {{ latestGradeYear ?? '-' }})</h2>
        <p class="text-sm text-slate-600">Total: {{ grades.length }}</p>
      </div>
      <div class="overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left">Year</th>
              <th class="px-3 py-2 text-left">School</th>
              <th class="px-3 py-2 text-left">Grade</th>
              <th class="px-3 py-2 text-left">Grade Head</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="grade in grades" :key="grade.sch_grd_id ?? `${grade.school_id}-${grade.grade_id}-${grade.year}`">
              <td class="px-3 py-2">{{ grade.year ?? '-' }}</td>
              <td class="px-3 py-2">{{ grade.school_name || grade.school_id || '-' }}</td>
              <td class="px-3 py-2">{{ grade.grade || '-' }}</td>
              <td class="px-3 py-2">{{ grade.grade_head || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="isGrades && activeTab === 'reports'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <h2 class="font-display text-xl font-bold">Grade Reports</h2>
        <div class="flex gap-2">
          <select v-model.number="reportYear" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option :value="0">All Years</option>
            <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
          </select>
          <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="loadGradeReport">View</button>
        </div>
      </div>
      <div class="overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Year</th><th class="px-3 py-2 text-left">Grade</th><th class="px-3 py-2 text-left">Student Count</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="row in gradeReport" :key="`${row.year}-${row.grade_id}`"><td class="px-3 py-2">{{ row.year }}</td><td class="px-3 py-2">{{ row.grade }}</td><td class="px-3 py-2">{{ row.student_count }}</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="isClasses && activeTab === 'view'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <h2 class="font-display text-xl font-bold">Classes (Latest Year {{ latestClassYear ?? '-' }})</h2>
        <div class="flex gap-2">
          <select v-model.number="selectedGradeId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="loadClassesView">
            <option :value="0">All Grades</option>
            <option v-for="option in classGradeOptions" :key="option.grade_id" :value="option.grade_id">{{ option.grade }}</option>
          </select>
        </div>
      </div>
      <div class="overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left">Year</th>
              <th class="px-3 py-2 text-left">School</th>
              <th class="px-3 py-2 text-left">Grade</th>
              <th class="px-3 py-2 text-left">Class</th>
              <th class="px-3 py-2 text-left">Approved</th>
              <th class="px-3 py-2 text-left">Current</th>
              <th class="px-3 py-2 text-left">Class Teacher</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="item in classes" :key="item.sch_grd_cls_id ?? `${item.school_id}-${item.grade_id}-${item.class_id}-${item.year}`">
              <td class="px-3 py-2">{{ item.year ?? '-' }}</td>
              <td class="px-3 py-2">{{ item.school_name || item.school_id || '-' }}</td>
              <td class="px-3 py-2">{{ item.grade || '-' }}</td>
              <td class="px-3 py-2">{{ item.class || '-' }}</td>
              <td class="px-3 py-2">{{ item.approved_std_count ?? '-' }}</td>
              <td class="px-3 py-2">{{ item.std_count ?? '-' }}</td>
              <td class="px-3 py-2">{{ item.class_teacher || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="isClasses && activeTab === 'reports'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <h2 class="font-display text-xl font-bold">Class Reports</h2>
        <div class="flex gap-2">
          <select v-model.number="reportYear" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option :value="0">All Years</option><option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option></select>
          <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="loadClassReport">View</button>
        </div>
      </div>
      <div class="overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Year</th><th class="px-3 py-2 text-left">Grade</th><th class="px-3 py-2 text-left">Class</th><th class="px-3 py-2 text-left">Student Count</th></tr></thead>
          <tbody class="divide-y divide-slate-100"><tr v-for="row in classReport" :key="`${row.year}-${row.grade_id}-${row.class_id}`"><td class="px-3 py-2">{{ row.year }}</td><td class="px-3 py-2">{{ row.grade }}</td><td class="px-3 py-2">{{ row.class }}</td><td class="px-3 py-2">{{ row.student_count }}</td></tr></tbody>
        </table>
      </div>
    </section>

    <section v-if="isStaff && activeTab === 'view'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><h2 class="font-display text-xl font-bold">Staff</h2><div class="flex gap-2"><input v-model="staffSearch" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Search by NIC or Name" @keyup.enter="loadStaff(1)" /><button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="loadStaff(1)">View</button></div></div>
      <div class="overflow-auto rounded-xl border border-slate-200"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">NIC</th><th class="px-3 py-2 text-left">Phone</th><th class="px-3 py-2 text-left">Designation</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="row in staffRows" :key="row.stf_id"><td class="px-3 py-2">{{ row.stf_id }}</td><td class="px-3 py-2">{{ row.name_with_ini }}</td><td class="px-3 py-2">{{ row.nic_no || '-' }}</td><td class="px-3 py-2">{{ row.phone_mobile1 || '-' }}</td><td class="px-3 py-2">{{ row.designation || '-' }}</td></tr></tbody></table></div>
      <div class="mt-4 flex items-center justify-between text-sm text-slate-600"><span>Total: {{ staffMeta.total }}</span><div class="flex gap-2"><button class="rounded border px-3 py-1" :disabled="staffMeta.current_page <= 1" @click="loadStaff(staffMeta.current_page - 1)">Prev</button><span>Page {{ staffMeta.current_page }} / {{ staffMeta.last_page }}</span><button class="rounded border px-3 py-1" :disabled="staffMeta.current_page >= staffMeta.last_page" @click="loadStaff(staffMeta.current_page + 1)">Next</button></div></div>
    </section>

    <section v-if="isStaff && activeTab === 'reports'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><h2 class="font-display text-xl font-bold">Staff Reports</h2><div class="flex gap-2"><select v-model.number="reportYear" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option :value="0">All Years</option><option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option></select><select v-model.number="reportMonth" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option :value="0">All Months</option><option v-for="month in 12" :key="month" :value="month">{{ month }}</option></select><button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="loadStaffReport">View</button></div></div>
      <div class="grid gap-4 md:grid-cols-3"><article class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Total Staff</p><p class="mt-2 text-2xl font-bold">{{ staffSummary.total_staff }}</p></article><article class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Updated</p><p class="mt-2 text-2xl font-bold text-emerald-700">{{ staffSummary.updated_staff }}</p></article><article class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">Not Updated</p><p class="mt-2 text-2xl font-bold text-rose-700">{{ staffSummary.not_updated_staff }}</p></article></div>
    </section>

    <section v-if="!supportsReports" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-600">This module is queued for API rollout. Functional screens are enabled for Grades, Classes, and Staff first.</p></section>
    <p v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import api from '../services/api'

const props = defineProps<{ moduleKey: string }>()

type TabKey = 'view' | 'reports'

interface Grade {
  sch_grd_id: number | null
  school_id: number | null
  school_name: string | null
  grade_id: number | null
  grade: string | null
  year: number | null
  grade_head: string | null
}

interface GradeReportRow { grade_id: number; grade: string; year: number; student_count: number }

interface ClassItem {
  sch_grd_cls_id: number | null
  school_id: number | null
  school_name: string | null
  grade_id: number | null
  grade: string | null
  class_id: number | null
  class: string | null
  year: number | null
  approved_std_count: number | null
  std_count: number | null
  class_teacher: string | null
}

interface ClassReportRow { grade_id: number; grade: string; class_id: number; class: string; year: number; student_count: number }
interface StaffRow { stf_id: number; name_with_ini: string; nic_no: string | null; phone_mobile1: string | null; designation: string | null }
interface StaffMeta { current_page: number; per_page: number; total: number; last_page: number }

const activeTab = ref<TabKey>('view')
const loading = ref(false)
const error = ref('')

const grades = ref<Grade[]>([])
const latestGradeYear = ref<number | null>(null)
const gradeReport = ref<GradeReportRow[]>([])
const classes = ref<ClassItem[]>([])
const latestClassYear = ref<number | null>(null)
const classReport = ref<ClassReportRow[]>([])
const selectedGradeId = ref(0)
const reportYear = ref(0)
const reportMonth = ref(0)

const staffSearch = ref('')
const staffRows = ref<StaffRow[]>([])
const staffMeta = reactive<StaffMeta>({ current_page: 1, per_page: 20, total: 0, last_page: 1 })
const staffSummary = reactive({ total_staff: 0, updated_staff: 0, not_updated_staff: 0 })

const yearOptions = computed(() => { const now = new Date().getFullYear(); return Array.from({ length: 8 }, (_, i) => now - i) })
const classGradeOptions = computed(() => {
  const seen = new Set<number>()
  return grades.value
    .filter((g): g is Grade & { grade_id: number; grade: string } => typeof g.grade_id === 'number' && typeof g.grade === 'string' && g.grade.length > 0)
    .filter((g) => {
      if (seen.has(g.grade_id)) return false
      seen.add(g.grade_id)
      return true
    })
})

const isGrades = computed(() => props.moduleKey === 'grades')
const isClasses = computed(() => props.moduleKey === 'classes')
const isStaff = computed(() => props.moduleKey === 'staff')
const supportsReports = computed(() => isGrades.value || isClasses.value || isStaff.value)

const title = computed(() => ({ grades: 'Grades Management', classes: 'Classes Management', staff: 'Staff Management', payments: 'SDS Payments', reports: 'Reporting & Exports' }[props.moduleKey] ?? 'Module'))
const subtitle = computed(() => {
  if (isGrades.value) return 'Loaded from school_grade_tbl (latest year), matching old system behavior.'
  if (isClasses.value) return 'Loaded from school_grade_class_tbl (latest year), matching old system behavior.'
  if (isStaff.value) return 'Old-system functions: staff listing and report summary.'
  return 'Module is available and will be expanded with business APIs.'
})

const loadGrades = async (): Promise<void> => {
  const { data } = await api.get<{ year?: number; data: Grade[] }>('/grades')
  grades.value = data.data
  latestGradeYear.value = typeof data.year === 'number' ? data.year : null
}

const loadClassesView = async (): Promise<void> => {
  const params: Record<string, number> = {}
  if (selectedGradeId.value) params.grade_id = selectedGradeId.value
  const { data } = await api.get<{ year?: number; data: ClassItem[] }>('/classes', { params })
  classes.value = data.data
  latestClassYear.value = typeof data.year === 'number' ? data.year : null
}

const loadGradeReport = async (): Promise<void> => {
  const params = reportYear.value ? { year: reportYear.value } : {}
  const { data } = await api.get<{ data: GradeReportRow[] }>('/grades/report', { params })
  gradeReport.value = data.data
}

const loadClassReport = async (): Promise<void> => {
  const params = reportYear.value ? { year: reportYear.value } : {}
  const { data } = await api.get<{ data: ClassReportRow[] }>('/classes/report', { params })
  classReport.value = data.data
}

const loadStaff = async (page = 1): Promise<void> => {
  const { data } = await api.get<{ data: StaffRow[]; meta: StaffMeta }>('/staff', { params: { q: staffSearch.value, page, per_page: staffMeta.per_page } })
  staffRows.value = data.data
  Object.assign(staffMeta, data.meta)
}

const loadStaffReport = async (): Promise<void> => {
  const params: Record<string, number> = {}
  if (reportYear.value) params.year = reportYear.value
  if (reportMonth.value) params.month = reportMonth.value
  const { data } = await api.get<{ summary: { total_staff: number; updated_staff: number; not_updated_staff: number } }>('/staff/report-summary', { params })
  Object.assign(staffSummary, data.summary)
}

watch(
  () => [props.moduleKey, activeTab.value, selectedGradeId.value],
  async () => {
    loading.value = true
    error.value = ''
    try {
      if (isGrades.value) {
        await loadGrades()
        if (activeTab.value === 'reports') await loadGradeReport()
      }

      if (isClasses.value) {
        if (activeTab.value === 'view') {
          await loadClassesView()
        } else {
          await loadClassReport()
        }
      }

      if (isStaff.value) {
        if (activeTab.value === 'view') {
          await loadStaff(1)
        } else {
          await loadStaffReport()
        }
      }
    } catch {
      error.value = 'Unable to load module data right now.'
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

watch(() => props.moduleKey, () => {
  activeTab.value = 'view'
  reportYear.value = 0
  reportMonth.value = 0
  selectedGradeId.value = 0
})
</script>
