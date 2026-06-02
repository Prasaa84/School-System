<template>
  <div class="space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h1 class="font-display text-2xl font-bold text-slate-900">{{ text.title }}</h1>
      <p class="mt-2 text-sm text-slate-600">{{ text.subtitle }}</p>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="grid gap-4 md:grid-cols-6">
        <label v-if="isAdmin" class="text-sm text-slate-700 md:col-span-5">
          {{ text.school }}
          <select v-model.number="selectedSchoolCensusId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onSchoolChange">
            <option :value="0">{{ text.selectSchool }}</option>
            <option v-for="row in schools" :key="`marks-school-${row.id}`" :value="Number(row.id)">{{ row.label }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.year }}
          <select v-model.number="selectedYear" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="lockedYear !== null || loadingOptions">
            <option :value="0">{{ text.selectYear }}</option>
            <option v-for="year in years" :key="`marks-year-${year}`" :value="year">{{ year }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.term }}
          <select v-model.number="selectedTerm" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="loadingOptions">
            <option :value="0">{{ text.selectTerm }}</option>
            <option v-for="row in terms" :key="`marks-term-${row.id}`" :value="row.id">{{ row.label }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.grade }}
          <select v-model.number="selectedGradeId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="lockedGradeId !== null || loadingOptions">
            <option :value="0">{{ text.selectGrade }}</option>
            <option v-for="row in grades" :key="`marks-grade-${row.grade_id}`" :value="row.grade_id">{{ row.label }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.class }}
          <select v-model.number="selectedClassId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="lockedClassId !== null || loadingOptions">
            <option :value="0">{{ text.selectClass }}</option>
            <option v-for="row in classes" :key="`marks-class-${row.class_id}`" :value="row.class_id">{{ row.label }}</option>
          </select>
        </label>

        <div class="flex items-end">
          <button class="w-full rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingMarks || loadingOptions" @click="loadMarks">
            {{ loadingMarks ? text.loading : text.show }}
          </button>
        </div>

        <div class="md:col-span-6">
          <div class="flex flex-nowrap items-end gap-2 overflow-x-auto pb-1">
            <button class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-cyan-300 bg-cyan-50 px-4 py-2 text-sm font-semibold text-cyan-700 hover:bg-cyan-100 disabled:cursor-not-allowed disabled:opacity-60" :disabled="exportingMarks || loadingOptions" @click="exportMarks">
              <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10 2a1 1 0 0 1 1 1v7.59l2.3-2.29a1 1 0 1 1 1.4 1.41l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.41L9 10.59V3a1 1 0 0 1 1-1Z" />
                <path d="M4 14a1 1 0 0 1 1 1v1h10v-1a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1Z" />
              </svg>
              {{ exportingMarks ? text.exporting : text.export }}
            </button>

            <button class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="downloadingTemplate || loadingOptions" @click="downloadImportTemplate">
              <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M5 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7.41A2 2 0 0 0 16.41 6L13 2.59A2 2 0 0 0 11.59 2H5Zm6 1.5V7a1 1 0 0 0 1 1h3.5V16a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h6Z" />
                <path d="M8 10a1 1 0 0 1 1 1v1h2v-1a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1Z" />
              </svg>
              {{ downloadingTemplate ? text.downloadingTemplate : text.template }}
            </button>

            <template v-if="canManageMarksUi">
              <input ref="marksFileInputRef" type="file" accept=".xlsx,.xls" class="hidden" @change="onMarksFileSelected" />
              <button class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="openMarksFilePicker">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path d="M10 3a1 1 0 0 1 1 1v6.59l1.3-1.29a1 1 0 1 1 1.4 1.41l-3 3a1 1 0 0 1-1.4 0l-3-3a1 1 0 1 1 1.4-1.41L9 10.59V4a1 1 0 0 1 1-1Z" />
                  <path d="M4 13a1 1 0 0 1 1 1v1h10v-1a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1Z" />
                </svg>
                {{ text.file }}
              </button>

              <span class="my-auto min-w-0 shrink text-sm text-slate-600">{{ selectedMarksFileName || text.noFileSelected }}</span>

              <button class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="uploadingMarks || !selectedMarksFile" @click="uploadMarksFile">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path d="M10 17a1 1 0 0 1-1-1V9.41L7.7 10.7a1 1 0 1 1-1.4-1.41l3-3a1 1 0 0 1 1.4 0l3 3a1 1 0 0 1-1.4 1.41L11 9.41V16a1 1 0 0 1-1 1Z" />
                  <path d="M4 14a1 1 0 0 1 1 1v1h10v-1a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1Z" />
                </svg>
                {{ uploadingMarks ? text.uploading : text.upload }}
              </button>
            </template>
          </div>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 space-y-3">
        <div>
          <h2 v-if="loadedTitle" class="font-display text-2xl font-bold text-slate-900">{{ loadedTitle }}</h2>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
            <span class="shrink-0 text-sm text-slate-700">{{ text.search }}</span>
            <input v-model.trim="searchQuery" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2 sm:w-80 lg:w-96" />
          </div>

          <div class="flex flex-wrap gap-3 text-sm">
            <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ text.totalStudents }}: {{ filteredRows.length }}</span>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ text.totalSubjects }}: {{ subjectRows.length }}</span>
            <span class="rounded-full px-3 py-1" :class="confirmation.is_completed ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
              {{ confirmation.is_completed ? text.completed : text.inProgress }}
            </span>
          </div>
        </div>
      </div>

      <p v-if="message" ref="messageRef" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</p>
      <p v-if="errorMessage" ref="errorMessageRef" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>

      <div class="overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="sticky left-0 z-20 bg-slate-50 px-3 py-2 text-left font-semibold text-slate-600">#</th>
              <th class="sticky left-[70px] z-20 min-w-[220px] bg-slate-50 px-3 py-2 text-left font-semibold text-slate-600">{{ text.student }}</th>
              <th v-for="subject in subjectRows" :key="`subject-col-${subject.subject_id}`" class="min-w-[120px] px-3 py-2 text-center font-semibold text-slate-600">
                {{ subject.subject }}
              </th>
              <th class="min-w-[90px] px-3 py-2 text-center font-semibold text-slate-600">{{ text.total }}</th>
              <th class="min-w-[90px] px-3 py-2 text-center font-semibold text-slate-600">{{ text.average }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="!loadingMarks && filteredRows.length === 0">
              <td :colspan="subjectRows.length + 4" class="px-3 py-8 text-center text-slate-500">{{ text.noRows }}</td>
            </tr>
            <tr v-for="row in filteredRows" :key="`marks-row-${row.index_no}`" class="hover:bg-slate-50">
              <td class="sticky left-0 z-10 bg-white px-3 py-2 font-semibold text-slate-900">{{ row.index_no }}</td>
              <td class="sticky left-[70px] z-10 bg-white px-3 py-2 text-slate-700">{{ row.name_with_initials }}</td>
              <td v-for="subject in subjectRows" :key="`marks-cell-${row.index_no}-${subject.subject_id}`" class="px-3 py-2 text-center">
                <input
                  v-if="canManageLoaded"
                  v-model="row.marks[String(subject.subject_id)]"
                  type="text"
                  maxlength="3"
                  class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-center text-sm outline-none ring-cyan-500 focus:ring-2"
                />
                <span v-else class="font-semibold text-slate-700">{{ row.marks[String(subject.subject_id)] || '-' }}</span>
              </td>
              <td class="px-3 py-2 text-center text-slate-700">{{ formatNumeric(row.total) }}</td>
              <td class="px-3 py-2 text-center text-slate-700">{{ formatAverage(row.average) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="canManageLoaded" class="mt-4 flex gap-2">
        <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="savingMarks || subjectRows.length === 0 || markRows.length === 0" @click="saveMarks">
          {{ savingMarks ? text.saving : text.save }}
        </button>
        <button class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60" :disabled="deletingMarks || subjectRows.length === 0 || markRows.length === 0" @click="openDeleteDialog">
          {{ deletingMarks ? text.deleting : text.delete }}
        </button>
      </div>
    </section>

    <ConfirmDialog
      :open="showDeleteDialog"
      :title="text.deleteDialogTitle"
      :message="text.deleteDialogMessage"
      :confirm-label="text.confirmDelete"
      :busy-confirm-label="text.deleting"
      :cancel-label="text.cancel"
      :close-label="text.cancel"
      :busy="deletingMarks"
      @close="closeDeleteDialog"
      @confirm="confirmDeleteMarks"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'

interface OptionRow {
  id: string
  label: string
}

interface GradeOption {
  grade_id: number
  label: string
}

interface ClassOption {
  class_id: number
  label: string
}

interface TermOption {
  id: number
  label: string
}

interface SubjectRow {
  subject_id: number
  subject: string
  order_id: number
}

interface MarkRow {
  std_id: number
  index_no: string
  name_with_initials: string
  marks: Record<string, string>
  total: number | null
  average: number | null
}

interface ScopeResponse {
  role?: string
  locked_year?: number | null
  locked_grade_id?: number | null
  locked_class_id?: number | null
}

interface MarksOptionsResponse {
  schools?: OptionRow[]
  years?: number[]
  terms?: TermOption[]
  grades?: GradeOption[]
  classes?: ClassOption[]
  selected_school_census_id?: string | null
  can_manage?: boolean
  scope?: ScopeResponse | null
  message?: string
}

interface MarksResponse {
  class?: {
    label?: string
  }
  subjects?: SubjectRow[]
  students?: MarkRow[]
  can_manage?: boolean
  confirmation?: {
    is_completed?: boolean
  }
}

const currentUser = getUser()
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')

const text = {
  title: 'Term Test Marks',
  subtitle: 'Class teachers can enter marks. Principals, clerks, grade heads, sectional heads, and students can search within their allowed scope.',
  school: 'School',
  selectSchool: 'Select school',
  year: 'Year',
  selectYear: 'Select year',
  term: 'Term',
  selectTerm: 'Select term',
  grade: 'Grade',
  selectGrade: 'Select grade',
  class: 'Class',
  selectClass: 'Select class',
  show: 'Show',
  loading: 'Loading...',
  marksSheet: 'Marks Sheet',
  save: 'Save',
  saving: 'Saving...',
  exportExcel: 'Export Excel',
  exporting: 'Exporting...',
  importTemplate: 'Import Template',
  downloadingTemplate: 'Downloading...',
  chooseFile: 'Choose File',
  noFileSelected: 'No file selected.',
  uploadExcel: 'Upload Excel',
  uploading: 'Uploading...',
  export: 'Export',
  template: 'Template',
  file: 'File',
  upload: 'Upload',
  delete: 'Delete Marks',
  confirmDelete: 'Delete',
  deleting: 'Deleting...',
  cancel: 'Cancel',
  search: 'Search',
  totalStudents: 'Students',
  totalSubjects: 'Subjects',
  student: 'Student',
  total: 'Total',
  average: 'Average',
  noRows: 'No marks found for the selected filters.',
  enterHint: 'Enter 0-100 or AB for absent.',
  completed: 'Completed',
  inProgress: 'In Progress',
  selectSchoolFirst: 'Select a school first.',
  selectFiltersFirst: 'Select year, term, grade, and class first.',
  loadOptionsError: 'Unable to load marks options.',
  loadMarksError: 'Unable to load term test marks.',
  saveMarksError: 'Unable to save term test marks.',
  deleteMarksError: 'Unable to delete term test marks.',
  exportMarksError: 'Unable to export term test marks.',
  templateMarksError: 'Unable to download marks import template.',
  uploadMarksError: 'Unable to upload term test marks.',
  deleteDialogTitle: 'Delete Marks',
  deleteDialogMessage: 'Do you want to delete all marks for the selected year, term, grade, and class?',
}

const schools = ref<OptionRow[]>([])
const years = ref<number[]>([])
const terms = ref<TermOption[]>([])
const grades = ref<GradeOption[]>([])
const classes = ref<ClassOption[]>([])
const subjectRows = ref<SubjectRow[]>([])
const markRows = ref<MarkRow[]>([])
const selectedSchoolCensusId = ref<number>(getSchoolContextCensusId() ?? 0)
const selectedYear = ref<number>(0)
const selectedTerm = ref<number>(1)
const selectedGradeId = ref<number>(0)
const selectedClassId = ref<number>(0)
const lockedYear = ref<number | null>(null)
const lockedGradeId = ref<number | null>(null)
const lockedClassId = ref<number | null>(null)
const loadingOptions = ref(false)
const loadingMarks = ref(false)
const savingMarks = ref(false)
const exportingMarks = ref(false)
const downloadingTemplate = ref(false)
const uploadingMarks = ref(false)
const deletingMarks = ref(false)
const showDeleteDialog = ref(false)
const canManageLoaded = ref(false)
const marksFileInputRef = ref<HTMLInputElement | null>(null)
const selectedMarksFile = ref<File | null>(null)
const searchQuery = ref('')
const loadedTitle = ref('')
const message = ref('')
const errorMessage = ref('')
const messageRef = ref<HTMLElement | null>(null)
const errorMessageRef = ref<HTMLElement | null>(null)
const confirmation = ref({ is_completed: false })
const canManageMarksUi = computed(() => ['class teacher', 'class_teacher', 'classteacher'].includes(roleName))
const selectedMarksFileName = computed(() => selectedMarksFile.value?.name ?? '')

const scopeLabel = computed(() => {
  if (roleName === 'class teacher') return 'Class teacher view'
  if (roleName === 'grade head') return 'Grade head view'
  if (roleName === 'sectional head') return 'Sectional head view'
  if (roleName === 'student') return 'Student view'
  if (roleName === 'clerk') return 'Clerk view'
  if (roleName === 'principal') return 'Principal view'
  return ''
})

const filteredRows = computed(() => {
  const search = searchQuery.value.trim().toLowerCase()
  if (search === '') return markRows.value

  return markRows.value.filter((row) => (
    row.index_no.toLowerCase().includes(search)
    || row.name_with_initials.toLowerCase().includes(search)
  ))
})

const syncScopeSelections = (scope?: ScopeResponse | null): void => {
  lockedYear.value = typeof scope?.locked_year === 'number' ? scope.locked_year : null
  lockedGradeId.value = typeof scope?.locked_grade_id === 'number' ? scope.locked_grade_id : null
  lockedClassId.value = typeof scope?.locked_class_id === 'number' ? scope.locked_class_id : null

  if (lockedYear.value !== null) selectedYear.value = lockedYear.value
  if (lockedGradeId.value !== null) selectedGradeId.value = lockedGradeId.value
  if (lockedClassId.value !== null) selectedClassId.value = lockedClassId.value
}

const loadOptions = async (): Promise<void> => {
  loadingOptions.value = true
  errorMessage.value = ''

  try {
    const { data } = await api.get<MarksOptionsResponse>('/marks/options', {
      params: {
        year: selectedYear.value > 0 ? selectedYear.value : undefined,
        grade_id: selectedGradeId.value > 0 ? selectedGradeId.value : undefined,
      },
    })

    schools.value = Array.isArray(data.schools) ? data.schools : []
    years.value = Array.isArray(data.years) ? data.years : []
    terms.value = Array.isArray(data.terms) ? data.terms : []
    grades.value = Array.isArray(data.grades) ? data.grades : []
    classes.value = Array.isArray(data.classes) ? data.classes : []

    if (typeof data.selected_school_census_id === 'string' && data.selected_school_census_id.trim() !== '') {
      selectedSchoolCensusId.value = Number(data.selected_school_census_id)
      setSchoolContextCensusId(selectedSchoolCensusId.value)
    }

    syncScopeSelections(data.scope)

    if (selectedYear.value <= 0 && years.value.length > 0) {
      selectedYear.value = years.value[0]
    }

    if (selectedTerm.value <= 0 && terms.value.length > 0) {
      selectedTerm.value = terms.value[0].id
    }

    if (selectedGradeId.value <= 0 && grades.value.length === 1) {
      selectedGradeId.value = grades.value[0].grade_id
    }

    if (selectedClassId.value <= 0 && classes.value.length === 1) {
      selectedClassId.value = classes.value[0].class_id
    }

    if (data.message) {
      message.value = data.message
      await scrollMessage(messageRef)
    }
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? text.loadOptionsError
    await scrollMessage(errorMessageRef)
  } finally {
    loadingOptions.value = false
  }
}

const loadMarks = async (options: { preserveMessage?: boolean } = {}): Promise<void> => {
  if (!options.preserveMessage) {
    message.value = ''
  }
  errorMessage.value = ''

  if (isAdmin.value && selectedSchoolCensusId.value <= 0) {
    errorMessage.value = text.selectSchoolFirst
    await scrollMessage(errorMessageRef)
    return
  }

  if (selectedYear.value <= 0 || selectedTerm.value <= 0 || selectedGradeId.value <= 0 || selectedClassId.value <= 0) {
    errorMessage.value = text.selectFiltersFirst
    await scrollMessage(errorMessageRef)
    return
  }

  loadingMarks.value = true

  try {
    const { data } = await api.get<MarksResponse>('/marks', {
      params: {
        year: selectedYear.value,
        term: selectedTerm.value,
        grade_id: selectedGradeId.value,
        class_id: selectedClassId.value,
      },
    })

    subjectRows.value = Array.isArray(data.subjects) ? data.subjects : []
    markRows.value = Array.isArray(data.students) ? data.students : []
    canManageLoaded.value = !!data.can_manage
    confirmation.value = {
      is_completed: !!data.confirmation?.is_completed,
    }

    loadedTitle.value = data.class?.label
      ? `${terms.value.find((row) => row.id === selectedTerm.value)?.label ?? 'Term Test'} ${selectedYear.value} - ${data.class.label}`
      : 'Term Test Marks'
  } catch (error: any) {
    subjectRows.value = []
    markRows.value = []
    canManageLoaded.value = false
    confirmation.value = { is_completed: false }
    errorMessage.value = error?.response?.data?.message ?? text.loadMarksError
    await scrollMessage(errorMessageRef)
  } finally {
    loadingMarks.value = false
  }
}

const saveMarks = async (): Promise<void> => {
  savingMarks.value = true
  message.value = ''
  errorMessage.value = ''

  try {
    const payload = {
      year: selectedYear.value,
      term: selectedTerm.value,
      grade_id: selectedGradeId.value,
      class_id: selectedClassId.value,
      entries: markRows.value.map((row) => ({
        index_no: row.index_no,
        marks: row.marks,
      })),
    }

    const successMessage = data.message ?? 'Term test marks saved successfully.'
    await loadMarks({ preserveMessage: true })
    message.value = successMessage
    await scrollMessage(messageRef)
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? text.saveMarksError
    await scrollMessage(errorMessageRef)
  } finally {
    savingMarks.value = false
  }
}

const exportMarks = async (): Promise<void> => {
  message.value = ''
  errorMessage.value = ''

  if (isAdmin.value && selectedSchoolCensusId.value <= 0) {
    errorMessage.value = text.selectSchoolFirst
    await scrollMessage(errorMessageRef)
    return
  }

  if (selectedYear.value <= 0 || selectedTerm.value <= 0 || selectedGradeId.value <= 0 || selectedClassId.value <= 0) {
    errorMessage.value = text.selectFiltersFirst
    await scrollMessage(errorMessageRef)
    return
  }

  exportingMarks.value = true

  try {
    const response = await api.get('/marks/export', {
      params: {
        year: selectedYear.value,
        term: selectedTerm.value,
        grade_id: selectedGradeId.value,
        class_id: selectedClassId.value,
      },
      responseType: 'blob',
    })

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    const fileNameMatch = /filename="?([^"]+)"?/i.exec(String(response.headers['content-disposition'] ?? ''))
    link.download = fileNameMatch?.[1] || 'marks-sheet.xlsx'
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? text.exportMarksError
    await scrollMessage(errorMessageRef)
  } finally {
    exportingMarks.value = false
  }
}

const downloadImportTemplate = async (): Promise<void> => {
  message.value = ''
  errorMessage.value = ''

  if (isAdmin.value && selectedSchoolCensusId.value <= 0) {
    errorMessage.value = text.selectSchoolFirst
    await scrollMessage(errorMessageRef)
    return
  }

  if (selectedYear.value <= 0 || selectedTerm.value <= 0 || selectedGradeId.value <= 0 || selectedClassId.value <= 0) {
    errorMessage.value = text.selectFiltersFirst
    await scrollMessage(errorMessageRef)
    return
  }

  downloadingTemplate.value = true

  try {
    const response = await api.get('/marks/template', {
      params: {
        year: selectedYear.value,
        term: selectedTerm.value,
        grade_id: selectedGradeId.value,
        class_id: selectedClassId.value,
      },
      responseType: 'blob',
    })

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    const fileNameMatch = /filename="?([^"]+)"?/i.exec(String(response.headers['content-disposition'] ?? ''))
    link.download = fileNameMatch?.[1] || 'marks-template.xlsx'
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? text.templateMarksError
    await scrollMessage(errorMessageRef)
  } finally {
    downloadingTemplate.value = false
  }
}

const openMarksFilePicker = (): void => {
  marksFileInputRef.value?.click()
}

const onMarksFileSelected = (event: Event): void => {
  const input = event.target as HTMLInputElement | null
  selectedMarksFile.value = input?.files?.[0] ?? null
}

const uploadMarksFile = async (): Promise<void> => {
  if (!selectedMarksFile.value) {
    return
  }

  message.value = ''
  errorMessage.value = ''
  uploadingMarks.value = true

  try {
    const formData = new FormData()
    formData.append('year', String(selectedYear.value))
    formData.append('term', String(selectedTerm.value))
    formData.append('grade_id', String(selectedGradeId.value))
    formData.append('class_id', String(selectedClassId.value))
    formData.append('file', selectedMarksFile.value)

    const { data } = await api.post<{ message?: string }>('/marks/import', formData)
    const successMessage = data.message ?? 'Term test marks uploaded successfully.'
    selectedMarksFile.value = null
    if (marksFileInputRef.value) {
      marksFileInputRef.value.value = ''
    }
    await loadMarks({ preserveMessage: true })
    message.value = successMessage
    await scrollMessage(messageRef)
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? text.uploadMarksError
    await scrollMessage(errorMessageRef)
  } finally {
    uploadingMarks.value = false
  }
}

const clearMarks = async (): Promise<void> => {
  deletingMarks.value = true
  message.value = ''
  errorMessage.value = ''

  try {
    const { data } = await api.delete<{ message?: string }>('/marks', {
      data: {
        year: selectedYear.value,
        term: selectedTerm.value,
        grade_id: selectedGradeId.value,
        class_id: selectedClassId.value,
      },
    })

    const successMessage = data.message ?? 'Term test marks deleted successfully.'
    showDeleteDialog.value = false
    await loadMarks({ preserveMessage: true })
    message.value = successMessage
    await scrollMessage(messageRef)
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message ?? text.deleteMarksError
    await scrollMessage(errorMessageRef)
  } finally {
    deletingMarks.value = false
  }
}

const openDeleteDialog = (): void => {
  showDeleteDialog.value = true
}

const closeDeleteDialog = (): void => {
  if (deletingMarks.value) {
    return
  }

  showDeleteDialog.value = false
}

const confirmDeleteMarks = async (): Promise<void> => {
  await clearMarks()
}

const onSchoolChange = async (): Promise<void> => {
  setSchoolContextCensusId(selectedSchoolCensusId.value > 0 ? selectedSchoolCensusId.value : null)
  selectedGradeId.value = 0
  selectedClassId.value = 0
  subjectRows.value = []
  markRows.value = []
  await loadOptions()
}

const scrollMessage = async (target: typeof messageRef): Promise<void> => {
  await nextTick()
  target.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

const formatNumeric = (value: number | null): string => {
  return typeof value === 'number' ? String(value) : '-'
}

const formatAverage = (value: number | null): string => {
  return typeof value === 'number' ? value.toFixed(2) : '-'
}

watch(selectedYear, async (next, previous) => {
  if (next === previous) return
  selectedGradeId.value = lockedGradeId.value ?? 0
  selectedClassId.value = lockedClassId.value ?? 0
  await loadOptions()
})

watch(selectedGradeId, async (next, previous) => {
  if (next === previous) return
  selectedClassId.value = lockedClassId.value ?? 0
  await loadOptions()
})

onMounted(async () => {
  await loadOptions()
})
</script>
