<template>
  <div class="space-y-5 bg-slate-100 print:bg-white">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
      <h1 class="font-display text-2xl font-bold text-slate-900">{{ text.title }}</h1>
      <p class="mt-2 text-sm text-slate-500">{{ pageHelpText }}</p>
    </header>

    <p v-if="assignmentWarning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 print:hidden">
      {{ assignmentWarning }}
    </p>
    <p v-if="attendanceEditNotice" class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 print:hidden">
      {{ attendanceEditNotice }}
    </p>

    <div v-if="isClassTeacher" class="flex gap-2 print:hidden">
      <button
        class="rounded-xl px-4 py-2 text-sm font-semibold transition"
        :class="isDailyMarkingMode ? 'bg-teal-500 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
        @click="switchToDailyMarking"
      >
        {{ text.dailyMarking }}
      </button>
      <button
        class="rounded-xl px-4 py-2 text-sm font-semibold transition"
        :class="isReportViewer ? 'bg-teal-500 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
        @click="switchToAttendanceReport"
      >
        {{ text.attendanceReport }}
      </button>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none">
      <div class="grid gap-3 md:grid-cols-5 print:hidden">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.attendanceDate }}</p>
          <p class="mt-2 text-sm font-semibold text-slate-900">{{ formattedDate }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ scopeTitle }}</p>
          <p class="mt-2 text-sm font-semibold text-slate-900">{{ scopeLabel }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-emerald-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-600">{{ text.present }}</p>
          <p class="mt-2 text-sm font-semibold text-emerald-900">{{ summary.present_students }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-rose-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-600">{{ text.absent }}</p>
          <p class="mt-2 text-sm font-semibold text-rose-900">{{ summary.absent_students }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.totalStudents }}</p>
          <p class="mt-2 text-sm font-semibold text-slate-900">{{ summary.total_students }}</p>
        </div>
      </div>

      <div class="mt-4 print:hidden">
        <div
          class="grid gap-2 md:grid-cols-2"
          :class="isReportViewer ? (isPrincipal ? 'lg:grid-cols-8' : 'lg:grid-cols-5') : 'lg:grid-cols-[220px_minmax(220px,1fr)_90px_90px_90px]'"
        >
        <label v-if="!isReportViewer" class="block text-sm text-slate-700">
          {{ text.filterDate }}
          <input v-model="selectedDate" type="date" :max="todayDate" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onDateChange" />
        </label>
        <label v-if="isReportViewer" class="block min-w-0 text-[11px] text-slate-700">
          {{ text.fromDate }}
          <input v-model="reportDateFrom" type="date" :max="todayDate" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" />
        </label>
        <label v-if="isReportViewer" class="block min-w-0 text-[11px] text-slate-700">
          {{ text.toDate }}
          <input v-model="reportDateTo" type="date" :max="todayDate" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" />
        </label>
        <label v-if="isPrincipal" class="block min-w-0 text-[11px] text-slate-700">
          {{ text.grade }}
          <select v-model.number="reportFilters.grade_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" :disabled="reportSelectedYear <= 0" @change="onGradeChange">
            <option :value="0">{{ text.allGrades }}</option>
            <option v-for="row in grades" :key="`attendance-grade-${row.grade_id}`" :value="row.grade_id">{{ row.grade }}</option>
          </select>
        </label>
        <label v-if="isPrincipal" class="block min-w-0 text-[11px] text-slate-700">
          {{ text.class }}
          <select
            v-model.number="reportFilters.class_id"
            class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]"
            :disabled="reportSelectedYear <= 0 || reportFilters.grade_id <= 0"
            @change="onClassChange"
          >
            <option :value="0">{{ text.allClasses }}</option>
            <option v-for="row in classes" :key="`attendance-class-${row.class_id}`" :value="row.class_id">{{ row.class }}</option>
          </select>
        </label>
        <label v-if="isPrincipal" class="block min-w-0 text-[11px] text-slate-700">
          {{ text.gender }}
          <select v-model.number="reportFilters.gender_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onGenderChange">
            <option v-for="row in genderOptions" :key="`attendance-gender-${row.id}`" :value="row.id">{{ row.label }}</option>
          </select>
        </label>
        <label :class="isReportViewer ? 'block min-w-0 text-[11px] text-slate-700' : 'block min-w-0 text-sm text-slate-700'">
          {{ text.search }}
          <input
            v-model="search"
            type="text"
            :class="[ 'mt-1 w-full rounded-lg border border-slate-300', isReportViewer ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm' ]"
            :placeholder="text.searchPlaceholder"
            @keydown.enter.prevent="isReportViewer ? submitAttendanceReportSearch() : null"
          />
        </label>
        <button
          :class="[ 'self-end rounded-xl border border-slate-300 bg-white font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50', isReportViewer ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm' ]"
          :disabled="loading"
          @click="resetFilters"
        >
          {{ text.reset }}
        </button>
        <button
          :class="[ 'self-end rounded-xl border border-slate-300 bg-white font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50', isReportViewer ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm' ]"
          :disabled="loading"
          @click="isReportViewer ? submitAttendanceReportSearch() : loadAttendance()"
        >
          {{ loading ? text.loading : (isReportViewer ? text.searchAction : text.refresh) }}
        </button>
        <button
          :class="[ 'self-end rounded-xl bg-teal-500 font-semibold text-white hover:bg-teal-600', isReportViewer ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm' ]"
          @click="isReportViewer ? exportAttendanceExcel() : printAttendance()"
        >
          {{ isReportViewer ? text.exportExcel : text.print }}
        </button>
        </div>
      </div>

      <p v-if="pageMessage" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 print:hidden">{{ pageMessage }}</p>
      <p v-if="pageError" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 print:hidden">{{ pageError }}</p>

      <div class="mt-4 overflow-auto rounded-xl border border-slate-200 print:mt-0 print:overflow-visible print:rounded-none print:border-0">
        <div class="hidden print:mb-4 print:block">
          <h2 class="font-display text-xl font-bold text-slate-900">{{ text.title }}</h2>
          <p class="mt-1 text-sm text-slate-600">{{ scopeLabel }}</p>
          <p class="mt-1 text-sm text-slate-600">{{ formattedDate }}</p>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.indexNo }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">
                <SortableHeader
                  v-if="isReportViewer"
                  :label="text.admissionNo"
                  field="admission_no"
                  :arrow="sortArrow('admission_no')"
                  :active-class="sortArrowClass('admission_no')"
                  @toggle="toggleSort"
                />
                <span v-else>{{ text.admissionNo }}</span>
              </th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.nameWithInitials }}</th>
              <template v-if="isReportViewer">
                <th class="px-3 py-2 text-left font-semibold text-slate-600">
                  <SortableHeader
                    :label="text.gradeClass"
                    field="grade_class"
                    :arrow="sortArrow('grade_class')"
                    :active-class="sortArrowClass('grade_class')"
                    @toggle="toggleSort"
                  />
                </th>
                <th v-for="dateHeader in reportDateHeaders" :key="`date-header-${dateHeader}`" class="px-3 py-2 text-center font-semibold text-slate-600">
                  <SortableHeader
                    :label="formatShortDate(dateHeader)"
                    :field="`date:${dateHeader}`"
                    :arrow="sortArrow(`date:${dateHeader}`)"
                    :active-class="sortArrowClass(`date:${dateHeader}`)"
                    align="center"
                    @toggle="toggleSort"
                  />
                </th>
                <th class="px-3 py-2 text-center font-semibold text-slate-600">{{ text.totalAttendance }}</th>
              </template>
              <th v-else class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.status }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="!loading && filteredStudents.length === 0">
              <td :colspan="isReportViewer ? 4 + reportDateHeaders.length : 4" class="px-3 py-6 text-center text-slate-500">{{ text.noStudents }}</td>
            </tr>
            <tr
              v-for="(student, index) in filteredStudents"
              :key="student.std_id"
              :class="!isReportViewer && student.status === 1 ? 'bg-emerald-50/50' : ''"
            >
              <td class="px-3 py-2 font-medium text-slate-800">{{ index + 1 }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.admission_no }}</td>
              <td class="px-3 py-2">
                <button
                  v-if="canEditAttendance"
                  class="w-full rounded-lg px-3 py-2 text-left font-semibold transition"
                  :class="student.status === 1 ? 'bg-emerald-100 text-emerald-900 hover:bg-emerald-200' : 'bg-slate-100 text-slate-800 hover:bg-slate-200'"
                  :disabled="savingStudentId === student.std_id || loading"
                  @click="toggleAttendance(student)"
                >
                  {{ savingStudentId === student.std_id ? text.saving : student.name_with_initials }}
                </button>
                <span v-else class="block px-3 py-2 text-slate-800">{{ student.name_with_initials }}</span>
              </td>
              <template v-if="isReportViewer">
                <td class="px-3 py-2 text-slate-700">{{ student.grade_class || text.notAvailable }}</td>
                <td v-for="dateHeader in reportDateHeaders" :key="`${student.std_id}-${dateHeader}`" class="px-3 py-2 text-center text-slate-700">
                  {{ renderAttendanceCell(student.attendance_map?.[dateHeader] ?? null) }}
                </td>
                <td class="px-3 py-2 text-center font-semibold text-slate-800">
                  {{ reportStudentTotal(student) }}
                </td>
              </template>
              <td v-else class="px-3 py-2">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="student.status === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                >
                  {{ student.status }}
                </span>
              </td>
            </tr>
            <tr v-if="isReportViewer && filteredStudents.length > 0" class="bg-slate-100 font-semibold">
              <td class="px-3 py-2 text-slate-800"></td>
              <td class="px-3 py-2 text-slate-800"></td>
              <td class="px-3 py-2 text-slate-800">{{ text.totalAttendance }}</td>
              <td class="px-3 py-2 text-slate-800"></td>
              <td v-for="dateHeader in reportDateHeaders" :key="`total-${dateHeader}`" class="px-3 py-2 text-center text-slate-800">
                {{ reportDateTotals[dateHeader] ?? 0 }}
              </td>
              <td class="px-3 py-2 text-center text-slate-900">
                {{ reportGrandTotal }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="isReportViewer && pagination.total > 0" class="mt-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 print:hidden md:flex-row md:items-center md:justify-between">
        <p>{{ text.showing }} {{ pagination.from }}-{{ pagination.to }} {{ text.of }} {{ pagination.total }}</p>
        <div class="flex items-center gap-2">
          <button
            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="loading || pagination.page <= 1"
            @click="goToPrincipalPage(pagination.page - 1)"
          >
            {{ text.previous }}
          </button>
          <span>{{ text.page }} {{ pagination.page }} {{ text.of }} {{ pagination.last_page }}</span>
          <button
            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="loading || pagination.page >= pagination.last_page"
            @click="goToPrincipalPage(pagination.page + 1)"
          >
            {{ text.next }}
          </button>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import SortableHeader from '../components/SortableHeader.vue'
import api from '../services/api'
import { getToken, getUser, setAuthSession, type AuthUser } from '../services/auth'
import { useUiStore } from '../stores/ui'
import { useTableSort } from '../utils/useTableSort'
import { useLocalizedText } from '../utils/uiText'

interface AttendanceStudent {
  std_id: number
  index_no: string
  admission_no: string
  name_with_initials: string
  status: number
  attendance_date: string
  attendance_map?: Record<string, number | null>
  gender_id: number
  gender_label: string
  year: number
  grade_id: number
  class_id: number
  grade: string
  class: string
  grade_class: string
}

interface AttendanceClassInfo {
  sch_grd_cls_id: number
  year: number
  grade_id: number
  class_id: number
  grade: string
  class: string
  grade_class: string
  student_count: number
}

interface AttendanceSummary {
  total_students: number
  present_students: number
  absent_students: number
}

interface AttendanceFilters {
  date_from?: string
  date_to?: string
  grade_id: number
  class_id: number
  gender_id: number
  search?: string
}

interface AttendancePagination {
  page: number
  per_page: number
  total: number
  last_page: number
  from: number
  to: number
}

interface AttendanceResponse {
  date: string
  class_info: AttendanceClassInfo | null
  summary?: AttendanceSummary
  attendance_edit_window?: AttendanceEditWindow | null
  filters?: AttendanceFilters | null
  pagination?: AttendancePagination | null
  date_headers?: string[]
  data: AttendanceStudent[]
  message?: string
}

interface AttendanceEditWindow {
  can_edit: boolean
  cutoff_time?: string | null
  reason?: string | null
}

interface GradeRow {
  grade_id: number
  grade: string
}

interface ClassRow {
  class_id: number
  class: string
}

interface GradeResponse {
  year?: number
  years?: number[]
  data?: GradeRow[]
}

interface ClassResponse {
  data?: ClassRow[]
}

interface OptionRow {
  id: number
  label: string
}

type ClassTeacherViewMode = 'marking' | 'report'
type AttendanceSortField = 'admission_no' | 'grade_class' | `date:${string}`

const currentUser = ref<AuthUser | null>(getUser())
const ui = useUiStore()
const todayDate = new Date().toISOString().slice(0, 10)
const loading = ref(false)
const savingStudentId = ref<number | null>(null)
const classTeacherViewMode = ref<ClassTeacherViewMode>('marking')
const search = ref('')
const pageMessage = ref('')
const pageError = ref('')
const attendanceDate = ref('')
const selectedDate = ref('')
const reportDateFrom = ref('')
const reportDateTo = ref('')
const students = ref<AttendanceStudent[]>([])
const reportDateHeaders = ref<string[]>([])
const classInfo = ref<AttendanceClassInfo | null>(null)
const attendanceEditWindow = ref<AttendanceEditWindow | null>(null)
const academicYears = ref<number[]>([])
const grades = ref<GradeRow[]>([])
const classes = ref<ClassRow[]>([])
const pagination = reactive<AttendancePagination>({
  page: 1,
  per_page: 100,
  total: 0,
  last_page: 1,
  from: 0,
  to: 0,
})
const summary = reactive<AttendanceSummary>({
  total_students: 0,
  present_students: 0,
  absent_students: 0,
})
const reportFilters = reactive<AttendanceFilters>({
  grade_id: 0,
  class_id: 0,
  gender_id: 0,
})

const roleName = computed(() => String(currentUser.value?.role_name ?? '').trim().toLowerCase())
const isClassTeacher = computed(() => ['class teacher', 'class_teacher', 'classteacher'].includes(roleName.value))
const isPrincipal = computed(() => (currentUser.value?.role_id ?? 0) === 2 || roleName.value === 'principal')
const isReportViewer = computed(() => isPrincipal.value || (isClassTeacher.value && classTeacherViewMode.value === 'report'))
const isDailyMarkingMode = computed(() => isClassTeacher.value && classTeacherViewMode.value === 'marking')
const canEditAttendance = computed(() => isDailyMarkingMode.value && attendanceEditWindow.value?.can_edit === true)
const assignmentWarning = computed(() => {
  if (!isClassTeacher.value) return ''

  const status = currentUser.value?.class_teacher_assignment_status ?? null
  if (status && status.is_assigned === false && typeof status.message === 'string') {
    return status.message
  }

  const message = currentUser.value?.class_teacher_assignment_message
  return typeof message === 'string' ? message : ''
})
const attendanceEditNotice = computed(() => {
  if (!isDailyMarkingMode.value) return ''
  return typeof attendanceEditWindow.value?.reason === 'string' ? attendanceEditWindow.value.reason : ''
})

const text = useLocalizedText({
  en: {
    title: 'Daily Attendance',
    dailyMarking: 'Daily Marking',
    attendanceReport: 'Attendance Report',
    helpClassTeacher: 'Tap a student name to toggle attendance between 0 and 1 for the selected date.',
    helpPrincipal: 'Review school attendance by date, year, grade, class, and gender.',
    attendanceDate: 'Attendance Date',
    filterDate: 'Select Date',
    fromDate: 'From Date',
    toDate: 'To Date',
    assignedClass: 'Assigned Class',
    scope: 'Attendance Scope',
    present: 'Present',
    absent: 'Absent',
    totalStudents: 'Total Students',
    search: 'Search',
    searchPlaceholder: 'Search by admission no, name, grade, or class',
    reset: 'Reset',
    searchAction: 'Search',
    refresh: 'Refresh',
    print: 'Print',
    exportExcel: 'Excel',
    loading: 'Loading...',
    saving: 'Saving...',
    indexNo: 'No.',
    date: 'Date',
    gradeClass: 'Grade/Class',
    totalAttendance: 'Total Attendance',
    admissionNo: 'Admission No',
    nameWithInitials: 'Name With Initials',
    status: 'Status',
    noStudents: 'No students found for the selected filters.',
    attendanceRecorded: 'Attendance recorded.',
    attendanceCleared: 'Attendance cleared.',
    unableToLoad: 'Unable to load daily attendance.',
    selectGradeBeforeSearch: 'Select a grade before searching attendance.',
    selectGradeBeforeExport: 'Select a grade before exporting attendance.',
    unableToToggle: 'Unable to update attendance.',
    notAssigned: 'No class assignment found for this class teacher.',
    attendanceReadOnlyToday: 'Attendance can only be marked for today.',
    notAvailable: 'N/A',
    year: 'Academic Year',
    grade: 'Grade',
    allGrades: 'Select Grade',
    class: 'Class',
    allClasses: 'Select Class',
    gender: 'Gender',
    allGenders: 'Select Gender',
    male: 'Male',
    female: 'Female',
    wholeSchool: 'Whole School',
    showing: 'Showing',
    of: 'of',
    page: 'Page',
    previous: 'Previous',
    next: 'Next',
  },
  si: {
    title: 'දෛනික පැමිණීම',
    dailyMarking: 'දෛනික සටහන් කිරීම',
    attendanceReport: 'පැමිණීමේ වාර්තාව',
    helpClassTeacher: 'තෝරාගත් දිනය සඳහා පැමිණීම 0 සහ 1 අතර මාරු කිරීමට සිසු නාමය මත තට්ටු කරන්න.',
    helpPrincipal: 'දිනය, වර්ෂය, ශ්‍රේණිය, පන්තිය සහ ස්ත්‍රී/පුරුෂ භාවය අනුව පාසල් පැමිණීම බලන්න.',
    attendanceDate: 'පැමිණීමේ දිනය',
    filterDate: 'දිනය තෝරන්න',
    fromDate: 'ආරම්භක දිනය',
    toDate: 'අවසාන දිනය',
    assignedClass: 'නියම කළ පන්තිය',
    scope: 'පැමිණීමේ පරාසය',
    present: 'පැමිණි',
    absent: 'නොපැමිණි',
    totalStudents: 'මුළු සිසුන්',
    search: 'සොයන්න',
    searchPlaceholder: 'ඇතුළත් අංකය, නම, ශ්‍රේණිය හෝ පන්තිය සොයන්න',
    reset: 'යළි සකසන්න',
    searchAction: 'සොයන්න',
    refresh: 'නැවත පූරණය',
    print: 'මුද්‍රණය',
    exportExcel: 'Excel',
    loading: 'පූරණය වෙමින්...',
    saving: 'සුරකිමින්...',
    indexNo: 'අංකය',
    date: 'දිනය',
    gradeClass: 'ශ්‍රේණිය/පන්තිය',
    totalAttendance: 'මුළු පැමිණීම',
    admissionNo: 'ඇතුළත් අංකය',
    nameWithInitials: 'මුලකුරු සමග නම',
    status: 'තත්ත්වය',
    noStudents: 'තෝරාගත් පෙරහන් සඳහා සිසුන් නොමැත.',
    attendanceRecorded: 'පැමිණීම සටහන් කරන ලදී.',
    attendanceCleared: 'පැමිණීම ඉවත් කරන ලදී.',
    unableToLoad: 'දෛනික පැමිණීම පූරණය කළ නොහැක.',
    selectGradeBeforeSearch: 'පැමිණීම සෙවීමට පෙර ශ්‍රේණියක් තෝරන්න.',
    selectGradeBeforeExport: 'පැමිණීම අපනයනය කිරීමට පෙර ශ්‍රේණියක් තෝරන්න.',
    unableToToggle: 'පැමිණීම යාවත්කාලීන කළ නොහැක.',
    notAssigned: 'මෙම පන්ති ගුරුවරයාට පන්තියක් නියම කර නොමැත.',
    attendanceReadOnlyToday: 'පැමිණීම සටහන් කළ හැක්කේ අද දිනය සඳහා පමණි.',
    notAvailable: 'නොමැත',
    year: 'අධ්‍යයන වර්ෂය',
    grade: 'ශ්‍රේණිය',
    allGrades: 'ශ්‍රේණිය තෝරන්න',
    class: 'පන්තිය',
    allClasses: 'පන්තිය තෝරන්න',
    gender: 'ස්ත්‍රී/පුරුෂ භාවය',
    allGenders: 'ස්ත්‍රී/පුරුෂ භාවය තෝරන්න',
    male: 'පිරිමි',
    female: 'ගැහැණු',
    wholeSchool: 'මුළු පාසල',
    showing: 'පෙන්වන්නේ',
    of: 'යින්',
    page: 'පිටුව',
    previous: 'පෙර',
    next: 'ඊළඟ',
  },
  ta: {
    title: 'தினசரி வருகை',
    dailyMarking: 'தினசரி பதிவு',
    attendanceReport: 'வருகை அறிக்கை',
    helpClassTeacher: 'தேர்ந்தெடுத்த தேதிக்கான வருகையை 0 மற்றும் 1 இடையில் மாற்ற மாணவர் பெயரைத் தொடவும்.',
    helpPrincipal: 'தேதி, ஆண்டு, தரம், வகுப்பு மற்றும் பாலினம் அடிப்படையில் பள்ளி வருகையை பார்க்கவும்.',
    attendanceDate: 'வருகை தேதி',
    filterDate: 'தேதி தேர்வு',
    fromDate: 'தொடக்க தேதி',
    toDate: 'முடிவு தேதி',
    assignedClass: 'ஒதுக்கப்பட்ட வகுப்பு',
    scope: 'வருகை வரம்பு',
    present: 'வருகையினர்',
    absent: 'வராதவர்கள்',
    totalStudents: 'மொத்த மாணவர்கள்',
    search: 'தேடல்',
    searchPlaceholder: 'அனுமதி இலக்கம், பெயர், தரம் அல்லது வகுப்பு தேடவும்',
    reset: 'மீட்டமை',
    searchAction: 'தேடு',
    refresh: 'மீண்டும் ஏற்று',
    print: 'அச்சிடு',
    exportExcel: 'Excel',
    loading: 'ஏற்றப்படுகிறது...',
    saving: 'சேமிக்கிறது...',
    indexNo: 'எண்',
    date: 'தேதி',
    gradeClass: 'தரம்/வகுப்பு',
    totalAttendance: 'மொத்த வருகை',
    admissionNo: 'அனுமதி இலக்கம்',
    nameWithInitials: 'முதற் எழுத்துகளுடன் பெயர்',
    status: 'நிலை',
    noStudents: 'தேர்ந்தெடுத்த வடிகட்டலுக்கு மாணவர்கள் இல்லை.',
    attendanceRecorded: 'வருகை பதிவு செய்யப்பட்டது.',
    attendanceCleared: 'வருகை நீக்கப்பட்டது.',
    unableToLoad: 'தினசரி வருகையை ஏற்ற முடியவில்லை.',
    selectGradeBeforeSearch: 'வருகையை தேடுவதற்கு முன் ஒரு தரத்தைத் தேர்ந்தெடுக்கவும்.',
    selectGradeBeforeExport: 'வருகையை ஏற்றுமதி செய்வதற்கு முன் ஒரு தரத்தைத் தேர்ந்தெடுக்கவும்.',
    unableToToggle: 'வருகையை புதுப்பிக்க முடியவில்லை.',
    notAssigned: 'இந்த வகுப்பு ஆசிரியருக்கு வகுப்பு ஒதுக்கப்படவில்லை.',
    attendanceReadOnlyToday: 'வருகையை இன்று தேதிக்காக மட்டும் பதிவு செய்யலாம்.',
    notAvailable: 'கிடைக்கவில்லை',
    year: 'கல்வி ஆண்டு',
    grade: 'தரம்',
    allGrades: 'தரத்தைத் தேர்ந்தெடுக்கவும்',
    class: 'வகுப்பு',
    allClasses: 'வகுப்பைத் தேர்ந்தெடுக்கவும்',
    gender: 'பால்',
    allGenders: 'பாலினத்தைத் தேர்ந்தெடுக்கவும்',
    male: 'ஆண்',
    female: 'பெண்',
    wholeSchool: 'முழு பள்ளி',
    showing: 'காட்டுவது',
    of: 'இல்',
    page: 'பக்கம்',
    previous: 'முன்',
    next: 'அடுத்து',
  },
})

const genderOptions = computed<OptionRow[]>(() => [
  { id: 0, label: text.value.allGenders },
  { id: 1, label: text.value.male },
  { id: 2, label: text.value.female },
])

const pageHelpText = computed(() => (isReportViewer.value ? text.value.helpPrincipal : text.value.helpClassTeacher))
const scopeTitle = computed(() => (isReportViewer.value ? text.value.scope : text.value.assignedClass))

const selectedGradeLabel = computed(() => {
  const selected = grades.value.find((row) => row.grade_id === reportFilters.grade_id)
  return selected?.grade ?? ''
})

const selectedClassLabel = computed(() => {
  const selected = classes.value.find((row) => row.class_id === reportFilters.class_id)
  return selected?.class ?? ''
})

const compareText = (left: string, right: string): number => (
  left.localeCompare(right, undefined, { numeric: true, sensitivity: 'base' })
)

const compareAttendanceValue = (left: number | null | undefined, right: number | null | undefined): number => {
  const normalize = (value: number | null | undefined): number => {
    if (value === 1) return 2
    if (value === 0) return 1
    return 0
  }

  return normalize(left) - normalize(right)
}

const {
  toggleSort,
  resetSort,
  sortArrow,
  sortArrowClass,
  sortItems,
} = useTableSort<AttendanceStudent, AttendanceSortField>({
  compare: (field, left, right) => {
    if (field === 'admission_no') {
      return compareText(String(left.admission_no ?? ''), String(right.admission_no ?? ''))
    }

    if (field === 'grade_class') {
      return compareText(String(left.grade_class ?? ''), String(right.grade_class ?? ''))
    }

    const dateHeader = field.slice(5)
    return compareAttendanceValue(left.attendance_map?.[dateHeader], right.attendance_map?.[dateHeader])
  },
  fallbackCompare: (left, right) => compareText(String(left.name_with_initials ?? ''), String(right.name_with_initials ?? '')),
})

const searchKeyword = computed(() => search.value.trim())
const reportSelectedYear = computed(() => {
  const value = reportDateFrom.value || reportDateTo.value
  const year = Number(String(value || '').slice(0, 4))
  return Number.isFinite(year) && year >= 2000 && year <= 2100 ? year : 0
})

const scopeLabel = computed(() => {
  if (isClassTeacher.value) {
    return classInfo.value?.grade_class || text.value.notAssigned
  }

  const parts: string[] = []
  if (reportSelectedYear.value > 0) {
    parts.push(String(reportSelectedYear.value))
  }
  if (selectedGradeLabel.value) {
    parts.push(selectedGradeLabel.value)
  }
  if (selectedClassLabel.value) {
    parts.push(selectedClassLabel.value)
  }
  if (reportFilters.gender_id > 0) {
    parts.push(genderOptions.value.find((row) => row.id === reportFilters.gender_id)?.label ?? '')
  }

  return parts.filter((value) => value.trim() !== '').join(' / ') || text.value.wholeSchool
})

const filteredStudents = computed(() => {
  if (isReportViewer.value) {
    return sortItems(students.value)
  }

  const keyword = searchKeyword.value.toLowerCase()
  if (keyword === '') {
    return students.value
  }

  return students.value.filter((student) =>
    student.admission_no.toLowerCase().includes(keyword)
      || student.name_with_initials.toLowerCase().includes(keyword)
      || student.grade.toLowerCase().includes(keyword)
      || student.class.toLowerCase().includes(keyword)
  )
})

const reportDateRangeLabel = computed(() => {
  if (!isReportViewer.value) {
    return ''
  }

  const from = reportDateFrom.value.trim()
  const to = reportDateTo.value.trim()
  if (from === '' && to === '') {
    return text.value.notAvailable
  }

  if (from !== '' && to !== '' && from !== to) {
    return `${formatShortDate(from)} - ${formatShortDate(to)}`
  }

  return formatShortDate(to || from)
})

const reportStudentTotal = (student: AttendanceStudent): number => {
  if (!student.attendance_map) {
    return 0
  }

  return reportDateHeaders.value.reduce((total, dateHeader) => (
    total + (student.attendance_map?.[dateHeader] === 1 ? 1 : 0)
  ), 0)
}

const reportDateTotals = computed<Record<string, number>>(() => {
  const totals: Record<string, number> = {}
  for (const dateHeader of reportDateHeaders.value) {
    totals[dateHeader] = students.value.reduce((total, student) => (
      total + (student.attendance_map?.[dateHeader] === 1 ? 1 : 0)
    ), 0)
  }

  return totals
})

const reportGrandTotal = computed(() => (
  reportDateHeaders.value.reduce((total, dateHeader) => total + (reportDateTotals.value[dateHeader] ?? 0), 0)
))

const formattedDate = computed(() => {
  if (isReportViewer.value) {
    return reportDateRangeLabel.value
  }

  if (!attendanceDate.value) {
    return text.value.notAvailable
  }

  const parsed = new Date(attendanceDate.value)
  return Number.isNaN(parsed.getTime())
    ? attendanceDate.value
    : parsed.toLocaleDateString(ui.language === 'si' ? 'si-LK' : ui.language === 'ta' ? 'ta-LK' : 'en-GB', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      })
})

const formatShortDate = (value: string): string => {
  const raw = String(value ?? '').trim()
  if (raw === '') {
    return text.value.notAvailable
  }

  const parsed = new Date(raw)
  return Number.isNaN(parsed.getTime())
    ? raw
    : parsed.toLocaleDateString(ui.language === 'si' ? 'si-LK' : ui.language === 'ta' ? 'ta-LK' : 'en-GB', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      })
}

const renderAttendanceCell = (value: number | null): string => {
  return value === 0 || value === 1 ? String(value) : ''
}

const refreshCurrentUser = async (): Promise<void> => {
  const token = getToken()
  if (!token) {
    return
  }

  try {
    const { data } = await api.get<{ user: AuthUser }>('/auth/me')
    if (data.user) {
      setAuthSession(token, data.user)
      currentUser.value = data.user
    }
  } catch {
    currentUser.value = getUser()
  }
}

const extractApiMessage = (error: unknown): string => {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { data?: { message?: string } } }).response
    if (typeof response?.data?.message === 'string' && response.data.message.trim() !== '') {
      return response.data.message
    }
  }

  return ''
}

const syncSummary = (nextSummary?: AttendanceSummary): void => {
  summary.total_students = Number(nextSummary?.total_students ?? 0)
  summary.present_students = Number(nextSummary?.present_students ?? 0)
  summary.absent_students = Number(nextSummary?.absent_students ?? 0)
}

const syncPagination = (nextPagination?: AttendancePagination | null): void => {
  pagination.page = Number(nextPagination?.page ?? 1)
  pagination.per_page = Number(nextPagination?.per_page ?? 100)
  pagination.total = Number(nextPagination?.total ?? 0)
  pagination.last_page = Math.max(Number(nextPagination?.last_page ?? 1), 1)
  pagination.from = Number(nextPagination?.from ?? 0)
  pagination.to = Number(nextPagination?.to ?? 0)
}

const clearReportResults = (): void => {
  students.value = []
  reportDateHeaders.value = []
  resetSort()
  classInfo.value = null
  attendanceEditWindow.value = null
  attendanceDate.value = ''
  pageMessage.value = ''
  pageError.value = ''
  syncSummary()
  syncPagination()
}

const loadGradeYears = async (): Promise<void> => {
  if (!isPrincipal.value) {
    return
  }

  try {
    const { data } = await api.get<GradeResponse>('/grades')
    academicYears.value = Array.isArray(data.years)
      ? data.years
          .map((value) => Number(value))
          .filter((value) => Number.isFinite(value) && value >= 2000 && value <= 2100)
      : []
  } catch {
    academicYears.value = []
  }
}

const loadGrades = async (year?: number): Promise<void> => {
  if (!isPrincipal.value) {
    return
  }

  const parsedYear = Number(year)
  if (!Number.isFinite(parsedYear) || parsedYear < 2000 || parsedYear > 2100) {
    grades.value = []
    reportFilters.grade_id = 0
    classes.value = []
    reportFilters.class_id = 0
    return
  }

  try {
    const { data } = await api.get<GradeResponse>('/grades', {
      params: { year: parsedYear },
    })

    grades.value = Array.isArray(data.data) ? data.data : []
    if (!grades.value.some((row) => row.grade_id === reportFilters.grade_id)) {
      reportFilters.grade_id = 0
    }
  } catch {
    grades.value = []
    reportFilters.grade_id = 0
  }
}

const loadClasses = async (gradeId: number, year: number): Promise<void> => {
  if (!isPrincipal.value) {
    return
  }

  const parsedGradeId = Number(gradeId)
  const parsedYear = Number(year)
  if (!Number.isFinite(parsedGradeId) || parsedGradeId <= 0 || !Number.isFinite(parsedYear) || parsedYear < 2000 || parsedYear > 2100) {
    classes.value = []
    reportFilters.class_id = 0
    return
  }

  try {
    const { data } = await api.get<ClassResponse>(`/classes/by-grade/${parsedGradeId}`, {
      params: { year: parsedYear },
    })

    classes.value = Array.isArray(data.data) ? data.data : []
    if (!classes.value.some((row) => row.class_id === reportFilters.class_id)) {
      reportFilters.class_id = 0
    }
  } catch {
    classes.value = []
    reportFilters.class_id = 0
  }
}

const loadAttendance = async (): Promise<void> => {
  if (isReportViewer.value && isPrincipal.value && reportFilters.grade_id <= 0) {
    students.value = []
    classInfo.value = null
    attendanceEditWindow.value = null
    reportDateHeaders.value = []
    syncSummary()
    syncPagination()
    pageError.value = text.value.selectGradeBeforeSearch
    pageMessage.value = ''
    return
  }

  loading.value = true
  pageError.value = ''

  try {
    const params: Record<string, number | string> = {}
    if (!isReportViewer.value && selectedDate.value) {
      params.date = selectedDate.value
    }
    if (isReportViewer.value) {
      if (reportDateFrom.value) params.date_from = reportDateFrom.value
      if (reportDateTo.value) params.date_to = reportDateTo.value
      if (isClassTeacher.value) params.report_mode = 1
      if (reportFilters.grade_id > 0) params.grade_id = reportFilters.grade_id
      if (reportFilters.class_id > 0) params.class_id = reportFilters.class_id
      if (reportFilters.gender_id > 0) params.gender_id = reportFilters.gender_id
      if (searchKeyword.value !== '') params.search = searchKeyword.value
      params.page = pagination.page
      params.per_page = pagination.per_page
    }

    const { data } = await api.get<AttendanceResponse>('/students/daily-attendance', Object.keys(params).length > 0 ? { params } : undefined)
    if (isReportViewer.value) {
      attendanceDate.value = reportDateTo.value || data.date || ''
      reportDateHeaders.value = Array.isArray(data.date_headers) ? data.date_headers : []
    } else {
      attendanceDate.value = data.date ?? ''
      selectedDate.value = data.date ?? selectedDate.value
      reportDateHeaders.value = []
    }
    classInfo.value = data.class_info ?? null
    attendanceEditWindow.value = data.attendance_edit_window ?? null
    students.value = Array.isArray(data.data) ? data.data : []
    syncSummary(data.summary)
    syncPagination(data.pagination)
    pageMessage.value = typeof data.message === 'string' ? data.message : ''

    if (isReportViewer.value && data.filters) {
      reportDateFrom.value = String(data.filters.date_from ?? reportDateFrom.value)
      reportDateTo.value = String(data.filters.date_to ?? reportDateTo.value)
      if (isPrincipal.value) {
        reportFilters.grade_id = Number(data.filters.grade_id ?? reportFilters.grade_id)
        reportFilters.class_id = Number(data.filters.class_id ?? reportFilters.class_id)
        reportFilters.gender_id = Number(data.filters.gender_id ?? reportFilters.gender_id)
      }
    }
  } catch (error) {
    students.value = []
    classInfo.value = null
    attendanceEditWindow.value = null
    syncSummary()
    syncPagination()
    pageError.value = extractApiMessage(error) || text.value.unableToLoad
  } finally {
    loading.value = false
  }
}

const toggleAttendance = async (student: AttendanceStudent): Promise<void> => {
  if (!canEditAttendance.value) {
    pageError.value = attendanceEditNotice.value || text.value.attendanceReadOnlyToday
    return
  }

  savingStudentId.value = student.std_id
  pageError.value = ''
  pageMessage.value = ''

  try {
    const { data } = await api.post<{ message?: string; data?: { student_id: number; status: number } }>('/students/daily-attendance/toggle', {
      student_id: student.std_id,
      date: attendanceDate.value || undefined,
    })

    const updatedStatus = Number(data.data?.status ?? student.status)
    students.value = students.value.map((row) =>
      row.std_id === student.std_id
        ? { ...row, status: updatedStatus }
        : row
    )
    syncSummary({
      total_students: students.value.length,
      present_students: students.value.filter((row) => row.status === 1).length,
      absent_students: students.value.filter((row) => row.status !== 1).length,
    })
    pageMessage.value = typeof data.message === 'string'
      ? data.message
      : (updatedStatus === 1 ? text.value.attendanceRecorded : text.value.attendanceCleared)
  } catch (error) {
    pageError.value = extractApiMessage(error) || text.value.unableToToggle
  } finally {
    savingStudentId.value = null
  }
}

const onDateChange = async (): Promise<void> => {
  if (isReportViewer.value) {
    pagination.page = 1
  }
  await loadAttendance()
}

const onGradeChange = async (): Promise<void> => {
  reportFilters.class_id = 0
  pagination.page = 1
  if (reportFilters.grade_id > 0 && reportSelectedYear.value > 0) {
    await loadClasses(reportFilters.grade_id, reportSelectedYear.value)
  } else {
    classes.value = []
  }
  await loadAttendance()
}

const onClassChange = async (): Promise<void> => {
  pagination.page = 1
  await loadAttendance()
}

const onGenderChange = async (): Promise<void> => {
  pagination.page = 1
  await loadAttendance()
}

const resetFilters = async (): Promise<void> => {
  search.value = ''
  selectedDate.value = new Date().toISOString().slice(0, 10)
  pagination.page = 1

  if (isReportViewer.value) {
    reportDateFrom.value = selectedDate.value
    reportDateTo.value = selectedDate.value
    if (isPrincipal.value) {
      reportFilters.grade_id = 0
      reportFilters.class_id = 0
      reportFilters.gender_id = 0
      classes.value = []
      await loadGrades(reportSelectedYear.value)
    }
    clearReportResults()
    return
  }

  await loadAttendance()
}

const submitAttendanceReportSearch = async (): Promise<void> => {
  if (!isReportViewer.value) {
    return
  }

  pagination.page = 1
  if (isPrincipal.value) {
    await loadGrades(reportSelectedYear.value)
    if (reportFilters.grade_id > 0) {
      await loadClasses(reportFilters.grade_id, reportSelectedYear.value)
    } else {
      classes.value = []
      reportFilters.class_id = 0
    }
  }
  await loadAttendance()
}

const goToPrincipalPage = async (page: number): Promise<void> => {
  if (!isReportViewer.value || loading.value) {
    return
  }

  const nextPage = Math.min(Math.max(page, 1), pagination.last_page)
  if (nextPage === pagination.page) {
    return
  }

  pagination.page = nextPage
  await loadAttendance()
}

const exportAttendanceExcel = async (): Promise<void> => {
  pageError.value = ''

  if (isReportViewer.value && isPrincipal.value && reportFilters.grade_id <= 0) {
    pageError.value = text.value.selectGradeBeforeExport
    return
  }

  try {
    const params: Record<string, number | string> = {}
    if (reportDateFrom.value) params.date_from = reportDateFrom.value
    if (reportDateTo.value) params.date_to = reportDateTo.value
    if (isClassTeacher.value) params.report_mode = 1
    if (reportFilters.grade_id > 0) params.grade_id = reportFilters.grade_id
    if (reportFilters.class_id > 0) params.class_id = reportFilters.class_id
    if (reportFilters.gender_id > 0) params.gender_id = reportFilters.gender_id
    if (searchKeyword.value !== '') params.search = searchKeyword.value

    const response = await api.get('/students/daily-attendance/export', {
      params,
      responseType: 'blob',
    })

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    const disposition = String(response.headers?.['content-disposition'] ?? '')
    const fileNameMatch = disposition.match(/filename="?([^"]+)"?/i)
    link.href = url
    link.download = fileNameMatch?.[1] || 'student-attendance-report.xlsx'
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    pageError.value = extractApiMessage(error) || text.value.unableToLoad
  }
}

const printAttendance = (): void => {
  window.print()
}

const switchToDailyMarking = async (): Promise<void> => {
  classTeacherViewMode.value = 'marking'
  pagination.page = 1
  await loadAttendance()
}

const switchToAttendanceReport = async (): Promise<void> => {
  classTeacherViewMode.value = 'report'
  search.value = ''
  pagination.page = 1
  clearReportResults()
}

onMounted(async () => {
  selectedDate.value = todayDate
  reportDateFrom.value = selectedDate.value
  reportDateTo.value = selectedDate.value
  await refreshCurrentUser()

  if (isPrincipal.value) {
    await loadGradeYears()
    await loadGrades(reportSelectedYear.value)
    clearReportResults()
    return
  }

  await loadAttendance()
})
</script>

<style scoped>
@media print {
  @page {
    size: A4 portrait;
    margin: 6mm;
  }

  table {
    width: 100%;
    table-layout: fixed;
  }

  thead th,
  tbody td {
    padding: 2px 5px !important;
    font-size: 9px !important;
    line-height: 1.05 !important;
  }

  thead th {
    white-space: nowrap;
  }

  .print\:mb-4 {
    margin-bottom: 6px !important;
  }

  .print\:mt-0 {
    margin-top: 0 !important;
  }

  tbody tr {
    break-inside: avoid;
  }

  button {
    background: transparent !important;
    color: inherit !important;
    padding: 0 !important;
  }
}
</style>
