<template>
  <div class="space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h1 class="font-display text-2xl font-bold text-slate-900">{{ text.title }}</h1>
      <p class="mt-2 text-sm text-slate-600">{{ text.subtitle }}</p>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="grid gap-4 md:grid-cols-4">
        <label v-if="isAdmin" class="text-sm text-slate-700 md:col-span-4">
          {{ text.school }}
          <select v-model.number="selectedSchoolCensusId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onSchoolChange">
            <option :value="0">{{ text.selectSchool }}</option>
            <option v-for="row in schools" :key="`subject-report-school-${row.id}`" :value="Number(row.id)">{{ row.label }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.year }}
          <select v-model.number="selectedYear" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option :value="0">{{ text.selectYear }}</option>
            <option v-for="year in yearOptions" :key="`subject-report-year-${year}`" :value="year">{{ year }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.grade }}
          <select v-model.number="selectedGradeId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option :value="0">{{ text.selectGrade }}</option>
            <option v-for="row in grades" :key="`subject-report-grade-${row.grade_id}`" :value="row.grade_id">{{ row.label }}</option>
          </select>
        </label>

        <div class="flex items-end">
          <button class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="loadingRows" @click="resetFilters">
            {{ text.reset }}
          </button>
        </div>

        <div class="flex items-end">
          <button class="w-full rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingRows" @click="searchReport">
            {{ loadingRows ? text.loading : text.search }}
          </button>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.reportSection }}</p>
          <h2 class="mt-1 font-display text-2xl font-bold text-slate-900">{{ selectedGradeLabel }}</h2>
        </div>
        <div class="flex items-center gap-3">
          <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ reportRows.length }}</span>
          <button class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60" :disabled="!canExport || loadingRows || reportRows.length === 0" @click="exportExcel">
            {{ text.exportExcel }}
          </button>
        </div>
      </div>

      <p v-if="message" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</p>
      <p v-if="errorMessage" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>

      <div class="overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">#</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.subject }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.category }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.year }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.grade }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="!loadingRows && reportRows.length === 0">
              <td colspan="5" class="px-3 py-6 text-center text-slate-500">{{ text.noRows }}</td>
            </tr>
            <tr v-for="(row, index) in reportRows" :key="`subject-report-row-${row.grade_id}-${row.subject_id}-${index}`" class="hover:bg-slate-50" :class="subjectRowClass(row.category)">
              <td class="px-3 py-2 font-semibold text-slate-900 whitespace-nowrap">{{ index + 1 }}</td>
              <td class="px-3 py-2 text-slate-700">{{ row.subject }}</td>
              <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.category }}</td>
              <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.year }}</td>
              <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.grade }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'
import { useUiStore } from '../stores/ui'

interface OptionRow {
  id: string
  label: string
}

interface GradeOption {
  grade_id: number
  label: string
}

interface SubjectReportRow {
  subject_id: number
  subject: string
  category: string
  year: number
  grade_id: number
  grade: string
}

interface SubjectsOptionsResponse {
  schools?: OptionRow[]
  grades?: GradeOption[]
  years?: number[]
  selected_school_census_id?: string | null
  can_report?: boolean
}

interface SubjectReportResponse {
  filters?: {
    year?: number
    grade_id?: number | null
  }
  data?: SubjectReportRow[]
  can_export?: boolean
}

const ui = useUiStore()
const currentUser = getUser()
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')

const text = computed(() => {
  if (ui.language === 'si') {
    return {
      title: 'විෂය වාර්තාව',
      subtitle: 'ශ්‍රේණියකට පවරා ඇති විෂයයන් සොයා බලා Excel ලෙස ලබාගන්න.',
      school: 'පාසල',
      selectSchool: 'පාසල තෝරන්න',
      year: 'වර්ෂය',
      selectYear: 'වර්ෂය තෝරන්න',
      grade: 'ශ්‍රේණිය',
      selectGrade: 'ශ්‍රේණිය තෝරන්න',
      search: 'සොයන්න',
      loading: 'පූරණය වෙමින්...',
      reset: 'යළි සකසන්න',
      reportSection: 'පවරා ඇති විෂයයන්',
      subject: 'විෂයය',
      category: 'කාණ්ඩය',
      noRows: 'තෝරාගත් පෙරණ සඳහා විෂයයන් හමු නොවීය.',
      exportExcel: 'Excel',
      selectSchoolFirst: 'පළමුව පාසලක් තෝරන්න.',
      selectYearFirst: 'පළමුව වර්ෂය තෝරන්න.',
      selectYearGradeFirst: 'පළමුව වර්ෂය සහ ශ්‍රේණිය තෝරන්න.',
      unableToLoadOptions: 'වාර්තා විකල්ප පූරණය කළ නොහැකි විය.',
      unableToLoadReport: 'විෂය වාර්තාව පූරණය කළ නොහැකි විය.',
      unableToExportReport: 'විෂය වාර්තාව ලබාගත නොහැකි විය.',
    }
  }

  if (ui.language === 'ta') {
    return {
      title: 'பாட அறிக்கை',
      subtitle: 'ஒரு தரத்திற்கு ஒதுக்கப்பட்ட பாடங்களை தேடி Excel ஆகப் பெறவும்.',
      school: 'பாடசாலை',
      selectSchool: 'பாடசாலையைத் தேர்ந்தெடுக்கவும்',
      year: 'ஆண்டு',
      selectYear: 'ஆண்டைத் தேர்ந்தெடுக்கவும்',
      grade: 'தரம்',
      selectGrade: 'தரத்தைத் தேர்ந்தெடுக்கவும்',
      search: 'தேடு',
      loading: 'ஏற்றப்படுகிறது...',
      reset: 'மீட்டமை',
      reportSection: 'ஒதுக்கப்பட்ட பாடங்கள்',
      subject: 'பாடம்',
      category: 'வகை',
      noRows: 'தேர்ந்தெடுக்கப்பட்ட வடிகட்டலுக்கு பாடங்கள் இல்லை.',
      exportExcel: 'Excel',
      selectSchoolFirst: 'முதலில் ஒரு பாடசாலையைத் தேர்ந்தெடுக்கவும்.',
      selectYearFirst: 'முதலில் ஆண்டைத் தேர்ந்தெடுக்கவும்.',
      selectYearGradeFirst: 'முதலில் ஆண்டும் தரமும் தேர்ந்தெடுக்கவும்.',
      unableToLoadOptions: 'அறிக்கை விருப்பங்களை ஏற்ற முடியவில்லை.',
      unableToLoadReport: 'பாட அறிக்கையை ஏற்ற முடியவில்லை.',
      unableToExportReport: 'பாட அறிக்கையைப் பெற முடியவில்லை.',
    }
  }

  return {
    title: 'Subjects Report',
    subtitle: 'Search the subjects assigned to each grade and export them to Excel.',
    school: 'School',
    selectSchool: 'Select school',
    year: 'Year',
    selectYear: 'Select year',
    grade: 'Grade',
    selectGrade: 'Select grade',
    search: 'Search',
    loading: 'Loading...',
    reset: 'Reset',
    reportSection: 'Assigned Subjects',
    subject: 'Subject',
    category: 'Category',
    noRows: 'No subjects found for the selected filters.',
    exportExcel: 'Excel',
    selectSchoolFirst: 'Select a school first.',
    selectYearFirst: 'Select year first.',
    selectYearGradeFirst: 'Select year and grade first.',
    unableToLoadOptions: 'Unable to load report options.',
    unableToLoadReport: 'Unable to load subjects report.',
    unableToExportReport: 'Unable to export subjects report.',
  }
})

const schools = ref<OptionRow[]>([])
const grades = ref<GradeOption[]>([])
const yearOptions = ref<number[]>([])
const reportRows = ref<SubjectReportRow[]>([])
const selectedSchoolCensusId = ref<number>(getSchoolContextCensusId() ?? 0)
const selectedYear = ref<number>(new Date().getFullYear())
const selectedGradeId = ref<number>(0)
const loadingRows = ref(false)
const canExport = ref(false)
const message = ref('')
const errorMessage = ref('')

const selectedGradeLabel = computed(() => {
  return grades.value.find((row) => row.grade_id === selectedGradeId.value)?.label ?? text.value.reportSection
})

const buildSchoolHeaders = (): Record<string, string> | undefined => {
  if (!isAdmin.value || selectedSchoolCensusId.value <= 0) {
    return undefined
  }

  return {
    'X-School-Census-Id': String(selectedSchoolCensusId.value),
  }
}

const subjectRowClass = (category: string): string => {
  switch (String(category).trim().toUpperCase()) {
    case 'MAIN':
      return 'bg-cyan-50/80'
    case 'OP1':
      return 'bg-emerald-50/80'
    case 'OP2':
      return 'bg-amber-50/80'
    case 'OP3':
      return 'bg-rose-50/80'
    default:
      return ''
  }
}

const clearStatus = (): void => {
  message.value = ''
  errorMessage.value = ''
}

const loadOptions = async (): Promise<void> => {
  const { data } = await api.get<SubjectsOptionsResponse>('/subjects/options', {
    headers: buildSchoolHeaders(),
  })

  schools.value = Array.isArray(data.schools) ? data.schools : []
  grades.value = Array.isArray(data.grades) ? data.grades : []
  yearOptions.value = Array.isArray(data.years) ? data.years : []

  if (selectedSchoolCensusId.value <= 0 && typeof data.selected_school_census_id === 'string' && data.selected_school_census_id.trim() !== '') {
    const parsed = Number(data.selected_school_census_id)
    if (Number.isFinite(parsed) && parsed > 0) {
      selectedSchoolCensusId.value = parsed
      setSchoolContextCensusId(parsed)
    }
  }
}

const searchReport = async (): Promise<void> => {
  clearStatus()

  if (isAdmin.value && selectedSchoolCensusId.value <= 0) {
    errorMessage.value = text.value.selectSchoolFirst
    return
  }

  if (selectedYear.value <= 0) {
    errorMessage.value = text.value.selectYearFirst
    return
  }

  if (selectedGradeId.value <= 0) {
    errorMessage.value = text.value.selectYearGradeFirst
    return
  }

  loadingRows.value = true

  try {
    const { data } = await api.get<SubjectReportResponse>('/subjects/report', {
      headers: buildSchoolHeaders(),
      params: {
        year: selectedYear.value,
        grade_id: selectedGradeId.value,
      },
    })

    reportRows.value = Array.isArray(data.data) ? data.data : []
    canExport.value = Boolean(data.can_export)

    if (data.filters) {
      if (typeof data.filters.year === 'number' && data.filters.year > 0) {
        selectedYear.value = data.filters.year
      }

      if (typeof data.filters.grade_id === 'number' && data.filters.grade_id > 0) {
        selectedGradeId.value = data.filters.grade_id
      }
    }
  } catch (error: any) {
    reportRows.value = []
    errorMessage.value = error?.response?.data?.message ?? text.value.unableToLoadReport
  } finally {
    loadingRows.value = false
  }
}

const exportExcel = async (): Promise<void> => {
  clearStatus()

  if (reportRows.value.length === 0) {
    return
  }

  try {
    const response = await api.get('/subjects/report/export', {
      headers: buildSchoolHeaders(),
      params: {
        year: selectedYear.value,
        grade_id: selectedGradeId.value,
      },
      responseType: 'blob',
    })

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    const disposition = String(response.headers?.['content-disposition'] ?? '')
    const fileNameMatch = disposition.match(/filename=\"?([^\"]+)\"?/i)
    link.href = url
    link.download = fileNameMatch?.[1] || 'subjects-report.xlsx'
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? text.value.unableToExportReport
  }
}

const resetFilters = async (): Promise<void> => {
  clearStatus()
  selectedYear.value = new Date().getFullYear()
  selectedGradeId.value = 0
  reportRows.value = []
  await loadOptions()
}

const onSchoolChange = async (): Promise<void> => {
  setSchoolContextCensusId(selectedSchoolCensusId.value > 0 ? selectedSchoolCensusId.value : null)
  selectedGradeId.value = 0
  reportRows.value = []
  await loadOptions()
}

onMounted(async () => {
  try {
    await loadOptions()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? text.value.unableToLoadOptions
  }
})

watch(
  () => ui.language,
  async () => {
    try {
      await loadOptions()
      if (selectedYear.value > 0) {
        await searchReport()
      }
    } catch (error: any) {
      errorMessage.value = error?.response?.data?.message ?? text.value.unableToLoadOptions
    }
  },
)
</script>
