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
            <option v-for="row in schools" :key="`marks-school-${row.id}`" :value="Number(row.id)">{{ row.label }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.year }}
          <select v-model.number="selectedYear" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="lockedYear !== null">
            <option :value="0">{{ text.selectYear }}</option>
            <option v-for="year in yearOptions" :key="`marks-year-${year}`" :value="year">{{ year }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.grade }}
          <select v-model.number="selectedGradeId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="lockedGradeId !== null">
            <option :value="0">{{ text.selectGrade }}</option>
            <option v-for="row in grades" :key="`marks-grade-${row.grade_id}`" :value="row.grade_id">{{ row.label }}</option>
          </select>
        </label>

        <div class="flex items-end">
          <button class="w-full rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingSubjects" @click="loadSubjects">
            {{ loadingSubjects ? text.loading : text.load }}
          </button>
        </div>

        <div class="flex items-end">
          <button class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="resetFilters">
            {{ text.reset }}
          </button>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.subjectList }}</p>
          <h2 class="mt-1 font-display text-2xl font-bold text-slate-900">{{ selectedGradeLabel || text.noGradeLoaded }}</h2>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ subjectRows.length }}</span>
      </div>

      <p v-if="message" ref="messageRef" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</p>
      <p v-if="errorMessage" ref="errorMessageRef" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>

      <div class="overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">#</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.subject }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.selected }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.category }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.year }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.grade }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="!loadingSubjects && subjectRows.length === 0">
              <td colspan="6" class="px-3 py-6 text-center text-slate-500">{{ text.noSubjects }}</td>
            </tr>
            <tr
              v-for="(row, index) in subjectRows"
              :key="`marks-subject-${row.subject_id}`"
              class="hover:bg-slate-50"
              :class="subjectRowClass(row.category)"
            >
              <td class="px-3 py-2 font-semibold text-slate-900 whitespace-nowrap">{{ index + 1 }}</td>
              <td class="px-3 py-2 text-slate-700">{{ row.subject }}</td>
              <td class="px-3 py-2 text-slate-700 whitespace-nowrap">
                <input v-model="selectedSubjectIds" type="checkbox" :value="row.subject_id" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" :disabled="!canManage" />
              </td>
              <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.category }}</td>
              <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.year }}</td>
              <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.grade }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="canManage" class="mt-5 flex justify-end">
        <button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving || subjectRows.length === 0" @click="saveSubjects">
          {{ saving ? text.saving : text.save }}
        </button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
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

interface SubjectRow {
  subject_id: number
  subject: string
  category: string
  selected: boolean
  year: number
  grade_id: number
  grade: string
}

interface SubjectsOptionsResponse {
  schools?: OptionRow[]
  grades?: GradeOption[]
  years?: number[]
  selected_school_census_id?: string | null
  can_manage?: boolean
  locked_grade_id?: number | null
  locked_year?: number | null
}

interface SubjectsListResponse {
  filters?: {
    year?: number
    grade_id?: number
  }
  data?: SubjectRow[]
  can_manage?: boolean
}

const currentUser = getUser()
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')
const canManage = ref(false)
const lockedGradeId = ref<number | null>(null)
const lockedYear = ref<number | null>(null)
const ui = useUiStore()

const text = computed(() => {
  if (ui.language === 'si') {
    return {
      title: 'විෂයයන්',
      subtitle: 'එක් එක් ශ්‍රේණිය සහ වර්ෂය සඳහා භාවිතා කරන විෂයයන් තෝරන්න.',
      school: 'පාසල',
      selectSchool: 'පාසල තෝරන්න',
      year: 'වර්ෂය',
      selectYear: 'වර්ෂය තෝරන්න',
      grade: 'ශ්‍රේණිය',
      selectGrade: 'ශ්‍රේණිය තෝරන්න',
      load: 'පෙන්වන්න',
      loading: 'පූරණය වෙමින්...',
      reset: 'යළි සකසන්න',
      subjectList: 'ශ්‍රේණි විෂයයන්',
      noGradeLoaded: 'විෂයයන්',
      subject: 'විෂයය',
      selected: 'තෝරා ඇත',
      category: 'කාණ්ඩය',
      noSubjects: 'තෝරාගත් වර්ෂය සහ ශ්‍රේණිය සඳහා විෂයයන් හමු නොවීය.',
      save: 'සුරකින්න',
      saving: 'සුරකිමින්...',
      selectSchoolFirst: 'පළමුව පාසලක් තෝරන්න.',
      selectYearGradeFirst: 'පළමුව වර්ෂය සහ ශ්‍රේණිය තෝරන්න.',
      unableToLoadSubjects: 'විෂයයන් පූරණය කළ නොහැකි විය.',
      subjectsSaved: 'විෂයයන් සාර්ථකව සුරකින ලදී.',
      unableToSaveSubjects: 'විෂයයන් සුරැකීමට නොහැකි විය.',
      unableToLoadSubjectOptions: 'විෂය විකල්ප පූරණය කළ නොහැකි විය.',
    }
  }

  if (ui.language === 'ta') {
    return {
      title: 'பாடங்கள்',
      subtitle: 'ஒவ்வொரு தரத்திற்கும் ஆண்டிற்கும் பயன்படுத்தப்படும் பாடங்களைத் தேர்ந்தெடுக்கவும்.',
      school: 'பாடசாலை',
      selectSchool: 'பாடசாலையைத் தேர்ந்தெடுக்கவும்',
      year: 'ஆண்டு',
      selectYear: 'ஆண்டைத் தேர்ந்தெடுக்கவும்',
      grade: 'தரம்',
      selectGrade: 'தரத்தைத் தேர்ந்தெடுக்கவும்',
      load: 'காட்டு',
      loading: 'ஏற்றப்படுகிறது...',
      reset: 'மீட்டமை',
      subjectList: 'தர பாடங்கள்',
      noGradeLoaded: 'பாடங்கள்',
      subject: 'பாடம்',
      selected: 'தேர்ந்தெடுக்கப்பட்டது',
      category: 'வகை',
      noSubjects: 'தேர்ந்தெடுக்கப்பட்ட ஆண்டு மற்றும் தரத்திற்கு பாடங்கள் இல்லை.',
      save: 'சேமிக்கவும்',
      saving: 'சேமிக்கப்படுகிறது...',
      selectSchoolFirst: 'முதலில் ஒரு பாடசாலையைத் தேர்ந்தெடுக்கவும்.',
      selectYearGradeFirst: 'முதலில் ஆண்டு மற்றும் தரத்தைத் தேர்ந்தெடுக்கவும்.',
      unableToLoadSubjects: 'பாடங்களை ஏற்ற முடியவில்லை.',
      subjectsSaved: 'பாடங்கள் வெற்றிகரமாக சேமிக்கப்பட்டன.',
      unableToSaveSubjects: 'பாடங்களை சேமிக்க முடியவில்லை.',
      unableToLoadSubjectOptions: 'பாட விருப்பங்களை ஏற்ற முடியவில்லை.',
    }
  }

  return {
    title: 'Subjects',
    subtitle: 'Select the subjects used for each grade and year.',
    school: 'School',
    selectSchool: 'Select school',
    year: 'Year',
    selectYear: 'Select year',
    grade: 'Grade',
    selectGrade: 'Select grade',
    load: 'Load',
    loading: 'Loading...',
    reset: 'Reset',
    subjectList: 'Grade Subjects',
    noGradeLoaded: 'Subjects',
    subject: 'Subject',
    selected: 'Selected',
    category: 'Category',
    noSubjects: 'No subjects found for the selected year and grade.',
    save: 'Save',
    saving: 'Saving...',
    selectSchoolFirst: 'Select a school first.',
    selectYearGradeFirst: 'Select year and grade first.',
    unableToLoadSubjects: 'Unable to load subjects.',
    subjectsSaved: 'Subjects saved successfully.',
    unableToSaveSubjects: 'Unable to save subjects.',
    unableToLoadSubjectOptions: 'Unable to load subject options.',
  }
})

const schools = ref<OptionRow[]>([])
const grades = ref<GradeOption[]>([])
const yearOptions = ref<number[]>([])
const subjectRows = ref<SubjectRow[]>([])
const selectedSubjectIds = ref<number[]>([])
const selectedSchoolCensusId = ref<number>(getSchoolContextCensusId() ?? 0)
const selectedYear = ref<number>(new Date().getFullYear())
const selectedGradeId = ref<number>(0)
const loadingSubjects = ref(false)
const saving = ref(false)
const message = ref('')
const errorMessage = ref('')
const messageRef = ref<HTMLElement | null>(null)
const errorMessageRef = ref<HTMLElement | null>(null)

const selectedGradeLabel = computed(() => {
  const selected = subjectRows.value[0]
  if (selected) {
    return selected.grade
  }

  return grades.value.find((row) => row.grade_id === selectedGradeId.value)?.label ?? text.value.noGradeLoaded
})

const buildSchoolHeaders = (): Record<string, string> | undefined => {
  if (!isAdmin.value) {
    return undefined
  }

  if (selectedSchoolCensusId.value <= 0) {
    return undefined
  }

  return {
    'X-School-Census-Id': String(selectedSchoolCensusId.value),
  }
}

const clearStatus = (): void => {
  message.value = ''
  errorMessage.value = ''
}

const subjectRowClass = (category: string): string => {
  switch (String(category).trim().toUpperCase()) {
    case 'MAIN':
      return 'bg-sky-100/80'
    case 'OP1':
      return 'bg-emerald-100/80'
    case 'OP2':
      return 'bg-amber-100/80'
    case 'OP3':
      return 'bg-rose-100/80'
    default:
      return ''
  }
}

const scrollToStatusMessage = async (kind: 'success' | 'error'): Promise<void> => {
  await nextTick()

  const target = kind === 'success' ? messageRef.value : errorMessageRef.value
  if (!target) {
    return
  }

  target.scrollIntoView({
    behavior: 'smooth',
    block: 'center',
  })
}

const loadOptions = async (): Promise<void> => {
  const { data } = await api.get<SubjectsOptionsResponse>('/subjects/options', {
    headers: buildSchoolHeaders(),
  })

  schools.value = Array.isArray(data.schools) ? data.schools : []
  grades.value = Array.isArray(data.grades) ? data.grades : []
  yearOptions.value = Array.isArray(data.years) ? data.years : []
  canManage.value = Boolean(data.can_manage)
  lockedGradeId.value = typeof data.locked_grade_id === 'number' && data.locked_grade_id > 0 ? data.locked_grade_id : null
  lockedYear.value = typeof data.locked_year === 'number' && data.locked_year > 0 ? data.locked_year : null

  if (lockedGradeId.value !== null) {
    selectedGradeId.value = lockedGradeId.value
  }

  if (lockedYear.value !== null) {
    selectedYear.value = lockedYear.value
  }

  if (selectedSchoolCensusId.value <= 0 && typeof data.selected_school_census_id === 'string' && data.selected_school_census_id.trim() !== '') {
    const parsed = Number(data.selected_school_census_id)
    if (Number.isFinite(parsed) && parsed > 0) {
      selectedSchoolCensusId.value = parsed
      setSchoolContextCensusId(parsed)
    }
  }
}

const loadSubjects = async (options?: { keepStatus?: boolean }): Promise<void> => {
  if (!options?.keepStatus) {
    clearStatus()
  }

  if (isAdmin.value && selectedSchoolCensusId.value <= 0) {
    errorMessage.value = text.value.selectSchoolFirst
    return
  }

  if (selectedYear.value <= 0 || selectedGradeId.value <= 0) {
    errorMessage.value = text.value.selectYearGradeFirst
    return
  }

  loadingSubjects.value = true

  try {
    const { data } = await api.get<SubjectsListResponse>('/subjects/grade-subjects', {
      headers: buildSchoolHeaders(),
      params: {
        year: selectedYear.value,
        grade_id: selectedGradeId.value,
      },
    })

    subjectRows.value = Array.isArray(data.data) ? data.data : []
    canManage.value = Boolean(data.can_manage)
    selectedSubjectIds.value = subjectRows.value.filter((row) => row.selected).map((row) => row.subject_id)

    if (data.filters) {
      if (typeof data.filters.year === 'number' && data.filters.year > 0) {
        selectedYear.value = data.filters.year
      }

      if (typeof data.filters.grade_id === 'number' && data.filters.grade_id > 0) {
        selectedGradeId.value = data.filters.grade_id
      }
    }
  } catch (error: any) {
    subjectRows.value = []
    selectedSubjectIds.value = []
    errorMessage.value = error?.response?.data?.message ?? text.value.unableToLoadSubjects
  } finally {
    loadingSubjects.value = false
  }
}

const saveSubjects = async (): Promise<void> => {
  clearStatus()

  if (selectedYear.value <= 0 || selectedGradeId.value <= 0) {
    errorMessage.value = text.value.selectYearGradeFirst
    return
  }

  saving.value = true

  try {
    const { data } = await api.post<{ message?: string }>('/subjects/grade-subjects', {
      year: selectedYear.value,
      grade_id: selectedGradeId.value,
      subject_ids: selectedSubjectIds.value,
    }, {
      headers: buildSchoolHeaders(),
    })

    message.value = data.message ?? text.value.subjectsSaved
    await loadSubjects({ keepStatus: true })
    await scrollToStatusMessage('success')
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? text.value.unableToSaveSubjects
    await scrollToStatusMessage('error')
  } finally {
    saving.value = false
  }
}

const resetFilters = async (): Promise<void> => {
  clearStatus()
  selectedYear.value = lockedYear.value ?? new Date().getFullYear()
  selectedGradeId.value = lockedGradeId.value ?? 0
  subjectRows.value = []
  selectedSubjectIds.value = []
  await loadOptions()
}

const onSchoolChange = async (): Promise<void> => {
  setSchoolContextCensusId(selectedSchoolCensusId.value > 0 ? selectedSchoolCensusId.value : null)
  selectedGradeId.value = 0
  subjectRows.value = []
  selectedSubjectIds.value = []
  await loadOptions()
}

onMounted(async () => {
  try {
    await loadOptions()
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? text.value.unableToLoadSubjectOptions
  }
})

watch(
  () => ui.language,
  async () => {
    try {
      await loadOptions()
      if (subjectRows.value.length > 0 || (selectedYear.value > 0 && selectedGradeId.value > 0)) {
        await loadSubjects({ keepStatus: true })
      }
    } catch (error: any) {
      errorMessage.value = error?.response?.data?.message ?? text.value.unableToLoadSubjectOptions
    }
  },
)
</script>
