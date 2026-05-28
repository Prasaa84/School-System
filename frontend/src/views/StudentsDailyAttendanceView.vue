<template>
  <div class="space-y-5 bg-slate-100 print:bg-white">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
      <h1 class="font-display text-2xl font-bold text-slate-900">{{ text.title }}</h1>
      <p class="mt-2 text-sm text-slate-500">{{ text.help }}</p>
    </header>

    <p v-if="assignmentWarning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 print:hidden">
      {{ assignmentWarning }}
    </p>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none">
      <div class="grid gap-3 md:grid-cols-4 print:hidden">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.attendanceDate }}</p>
          <p class="mt-2 text-sm font-semibold text-slate-900">{{ formattedDate }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.assignedClass }}</p>
          <p class="mt-2 text-sm font-semibold text-slate-900">{{ classLabel }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-emerald-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-600">{{ text.present }}</p>
          <p class="mt-2 text-sm font-semibold text-emerald-900">{{ presentCount }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.totalStudents }}</p>
          <p class="mt-2 text-sm font-semibold text-slate-900">{{ filteredStudents.length }}</p>
        </div>
      </div>

      <div class="mt-4 flex flex-col gap-3 xl:flex-row xl:items-end xl:gap-4 print:hidden">
        <label class="block text-sm text-slate-700 xl:w-[220px] xl:shrink-0">
          {{ text.filterDate }}
          <input v-model="selectedDate" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="loadAttendance" />
        </label>
        <label class="block text-sm text-slate-700 xl:min-w-0 xl:flex-1">
          {{ text.search }}
          <input v-model="search" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :placeholder="text.searchPlaceholder" />
        </label>
        <div class="flex flex-wrap gap-3 xl:shrink-0 xl:self-end">
          <button
            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 print:hidden"
            :disabled="loading"
            @click="loadAttendance"
          >
            {{ loading ? text.loading : text.refresh }}
          </button>
          <button
            class="rounded-xl bg-teal-500 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-600 print:hidden"
            @click="printAttendance"
          >
            {{ text.print }}
          </button>
        </div>
      </div>

      <p v-if="pageMessage" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 print:hidden">{{ pageMessage }}</p>
      <p v-if="pageError" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 print:hidden">{{ pageError }}</p>

      <div class="mt-4 overflow-auto rounded-xl border border-slate-200 print:mt-0 print:overflow-visible print:rounded-none print:border-0">
        <div class="hidden print:mb-4 print:block">
          <h2 class="font-display text-xl font-bold text-slate-900">{{ text.title }}</h2>
          <p class="mt-1 text-sm text-slate-600">{{ classLabel }}</p>
          <p class="mt-1 text-sm text-slate-600">{{ formattedDate }}</p>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.indexNo }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.admissionNo }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.nameWithInitials }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.status }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="!loading && filteredStudents.length === 0">
              <td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ text.noStudents }}</td>
            </tr>
            <tr
              v-for="(student, index) in filteredStudents"
              :key="student.std_id"
              :class="student.status === 1 ? 'bg-emerald-50/50' : ''"
            >
              <td class="px-3 py-2 font-medium text-slate-800">{{ index + 1 }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.admission_no }}</td>
              <td class="px-3 py-2">
                <button
                  class="w-full rounded-lg px-3 py-2 text-left font-semibold transition"
                  :class="student.status === 1 ? 'bg-emerald-100 text-emerald-900 hover:bg-emerald-200' : 'bg-slate-100 text-slate-800 hover:bg-slate-200'"
                  :disabled="savingStudentId === student.std_id || loading"
                  @click="toggleAttendance(student)"
                >
                  {{ savingStudentId === student.std_id ? text.saving : student.name_with_initials }}
                </button>
              </td>
              <td class="px-3 py-2">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="student.status === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                >
                  {{ student.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import api from '../services/api'
import { getToken, getUser, setAuthSession, type AuthUser } from '../services/auth'
import { useUiStore } from '../stores/ui'
import { useLocalizedText } from '../utils/uiText'

interface AttendanceStudent {
  std_id: number
  index_no: string
  admission_no: string
  name_with_initials: string
  status: number
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

interface AttendanceResponse {
  date: string
  class_info: AttendanceClassInfo | null
  data: AttendanceStudent[]
  message?: string
}

const currentUser = ref<AuthUser | null>(getUser())
const ui = useUiStore()
const loading = ref(false)
const savingStudentId = ref<number | null>(null)
const search = ref('')
const pageMessage = ref('')
const pageError = ref('')
const attendanceDate = ref('')
const selectedDate = ref('')
const students = ref<AttendanceStudent[]>([])
const classInfo = ref<AttendanceClassInfo | null>(null)

const roleName = computed(() => String(currentUser.value?.role_name ?? '').trim().toLowerCase())
const isClassTeacher = computed(() => ['class teacher', 'class_teacher', 'classteacher'].includes(roleName.value))
const assignmentWarning = computed(() => {
  if (!isClassTeacher.value) return ''

  const status = currentUser.value?.class_teacher_assignment_status ?? null
  if (status && status.is_assigned === false && typeof status.message === 'string') {
    return status.message
  }

  const message = currentUser.value?.class_teacher_assignment_message
  return typeof message === 'string' ? message : ''
})

const text = useLocalizedText({
  en: {
    title: 'Daily Attendance',
    help: 'Tap a student name to toggle today\'s attendance between 0 and 1.',
    attendanceDate: 'Attendance Date',
    filterDate: 'Select Date',
    assignedClass: 'Assigned Class',
    present: 'Present',
    totalStudents: 'Total Students',
    search: 'Search',
    searchPlaceholder: 'Search by admission no or name',
    refresh: 'Refresh',
    print: 'Print',
    loading: 'Loading...',
    saving: 'Saving...',
    indexNo: 'No.',
    admissionNo: 'Admission No',
    nameWithInitials: 'Name With Initials',
    status: 'Status',
    noStudents: 'No students found for this class.',
    attendanceRecorded: 'Attendance recorded.',
    attendanceCleared: 'Attendance cleared.',
    unableToLoad: 'Unable to load daily attendance.',
    unableToToggle: 'Unable to update attendance.',
    notAssigned: 'No class assignment found for this class teacher.',
    notAvailable: 'N/A',
  },
  si: {
    title: 'දෛනික පැමිණීම',
    help: 'අද දින පැමිණීම 0 සහ 1 අතර මාරු කිරීමට සිසු නාමය මත තට්ටු කරන්න.',
    attendanceDate: 'පැමිණීමේ දිනය',
    filterDate: 'දිනය තෝරන්න',
    assignedClass: 'නියම කළ පන්තිය',
    present: 'පැමිණි',
    totalStudents: 'මුළු සිසුන්',
    search: 'සොයන්න',
    searchPlaceholder: 'ඇතුළත් අංකය හෝ නම සොයන්න',
    refresh: 'නැවත පූරණය',
    print: 'මුද්‍රණය',
    loading: 'පූරණය වෙමින්...',
    saving: 'සුරකිමින්...',
    indexNo: 'අංකය',
    admissionNo: 'ඇතුළත් අංකය',
    nameWithInitials: 'මුලකුරු සමග නම',
    status: 'තත්ත්වය',
    noStudents: 'මෙම පන්තිය සඳහා සිසුන් නොමැත.',
    attendanceRecorded: 'පැමිණීම සටහන් කරන ලදී.',
    attendanceCleared: 'පැමිණීම ඉවත් කරන ලදී.',
    unableToLoad: 'දෛනික පැමිණීම පූරණය කළ නොහැක.',
    unableToToggle: 'පැමිණීම යාවත්කාලීන කළ නොහැක.',
    notAssigned: 'මෙම පන්ති ගුරුවරයාට පන්තියක් නියම කර නොමැත.',
    notAvailable: 'නොමැත',
  },
  ta: {
    title: 'தினசரி வருகை',
    help: 'இன்றைய வருகையை 0 மற்றும் 1 இடையில் மாற்ற மாணவர் பெயரைத் தொடவும்.',
    attendanceDate: 'வருகை தேதி',
    filterDate: 'தேதி தேர்வு',
    assignedClass: 'ஒதுக்கப்பட்ட வகுப்பு',
    present: 'வருகையினர்',
    totalStudents: 'மொத்த மாணவர்கள்',
    search: 'தேடல்',
    searchPlaceholder: 'அனுமதி இலக்கம் அல்லது பெயர் தேடவும்',
    refresh: 'மீண்டும் ஏற்று',
    print: 'அச்சிடு',
    loading: 'ஏற்றப்படுகிறது...',
    saving: 'சேமிக்கிறது...',
    indexNo: 'எண்',
    admissionNo: 'அனுமதி இலக்கம்',
    nameWithInitials: 'முதற் எழுத்துகளுடன் பெயர்',
    status: 'நிலை',
    noStudents: 'இந்த வகுப்பிற்கு மாணவர்கள் இல்லை.',
    attendanceRecorded: 'வருகை பதிவு செய்யப்பட்டது.',
    attendanceCleared: 'வருகை நீக்கப்பட்டது.',
    unableToLoad: 'தினசரி வருகையை ஏற்ற முடியவில்லை.',
    unableToToggle: 'வருகையை புதுப்பிக்க முடியவில்லை.',
    notAssigned: 'இந்த வகுப்பு ஆசிரியருக்கு வகுப்பு ஒதுக்கப்படவில்லை.',
    notAvailable: 'கிடைக்கவில்லை',
  },
})

const filteredStudents = computed(() => {
  const keyword = search.value.trim().toLowerCase()
  if (keyword === '') {
    return students.value
  }

  return students.value.filter((student) =>
    student.admission_no.toLowerCase().includes(keyword)
      || student.name_with_initials.toLowerCase().includes(keyword)
  )
})

const presentCount = computed(() => students.value.filter((student) => student.status === 1).length)
const classLabel = computed(() => {
  if (!classInfo.value) {
    return text.value.notAssigned
  }

  return classInfo.value.grade_class || text.value.notAvailable
})

const formattedDate = computed(() => {
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

const loadAttendance = async (): Promise<void> => {
  loading.value = true
  pageError.value = ''

  try {
    const requestConfig = selectedDate.value
      ? { params: { date: selectedDate.value } }
      : undefined
    const { data } = await api.get<AttendanceResponse>('/students/daily-attendance', requestConfig)
    attendanceDate.value = data.date ?? ''
    selectedDate.value = data.date ?? selectedDate.value
    classInfo.value = data.class_info ?? null
    students.value = Array.isArray(data.data) ? data.data : []
    pageMessage.value = typeof data.message === 'string' ? data.message : ''
  } catch (error) {
    students.value = []
    classInfo.value = null
    pageError.value = extractApiMessage(error) || text.value.unableToLoad
  } finally {
    loading.value = false
  }
}

const toggleAttendance = async (student: AttendanceStudent): Promise<void> => {
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
    pageMessage.value = typeof data.message === 'string'
      ? data.message
      : (updatedStatus === 1 ? text.value.attendanceRecorded : text.value.attendanceCleared)
  } catch (error) {
    pageError.value = extractApiMessage(error) || text.value.unableToToggle
  } finally {
    savingStudentId.value = null
  }
}

const printAttendance = (): void => {
  window.print()
}

onMounted(async () => {
  selectedDate.value = new Date().toISOString().slice(0, 10)
  await refreshCurrentUser()
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
