<template>
  <ModuleShell
    :title="title"
    :subtitle="subtitle"
    :supports-reports="supportsReports"
    :active-tab="activeTab"
    :message="message"
    :error="error"
    @change-tab="activeTab = $event"
  >
    <GradesModulePanel
      v-if="isGrades"
      :active-tab="activeTab"
      :is-admin="isAdmin"
      :is-principal="isPrincipal"
      :can-manage="canManage"
      :latest-year="latestGradeYear"
      :target-year="targetYear"
      :grades="grades"
      :grade-edits="gradeEdits"
      :staff-options="staffOptions"
      :report-year="reportYear"
      :year-options="yearOptions"
      :grade-report="gradeReport"
      @update:target-year="targetYear = $event"
      @update:report-year="reportYear = $event"
      @update-grade-head="onUpdateGradeHead"
      @initialize-year="initializeYear"
      @save-grade="saveGrade"
      @delete-grade="deleteGrade"
      @load-report="loadGradeReport"
    />

    <ClassesModulePanel
      v-if="isClasses"
      :active-tab="activeTab"
      :is-admin="isAdmin"
      :can-manage="canManage"
      :latest-year="latestClassYear"
      :selected-grade-id="selectedGradeId"
      :class-grade-options="classGradeOptions"
      :classes="classes"
      :class-teacher-edits="classTeacherEdits"
      :class-approved-edits="classApprovedEdits"
      :class-count-edits="classCountEdits"
      :staff-options="staffOptions"
      :report-year="reportYear"
      :year-options="yearOptions"
      :class-report="classReport"
      @update:selected-grade="onUpdateSelectedGrade"
      @update:report-year="reportYear = $event"
      @update-class-teacher="onUpdateClassTeacher"
      @update-class-approved="onUpdateClassApproved"
      @update-class-current="onUpdateClassCurrent"
      @save-class="saveClass"
      @load-report="loadClassReport"
    />

    <StaffModulePanel
      v-if="isStaff"
      :active-tab="activeTab"
      :staff-search="staffSearch"
      :staff-rows="staffRows"
      :staff-meta="staffMeta"
      :report-year="reportYear"
      :report-month="reportMonth"
      :year-options="yearOptions"
      :staff-summary="staffSummary"
      @update:staff-search="staffSearch = $event"
      @update:report-year="reportYear = $event"
      @update:report-month="reportMonth = $event"
      @load-staff="loadStaff"
      @load-report="loadStaffReport"
    />

    <ModuleQueuedNotice
      v-if="!supportsReports"
      :message="text.queuedModuleMessage"
    />
  </ModuleShell>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import ClassesModulePanel from '../components/modules/ClassesModulePanel.vue'
import GradesModulePanel from '../components/modules/GradesModulePanel.vue'
import ModuleQueuedNotice from '../components/modules/ModuleQueuedNotice.vue'
import ModuleShell from '../components/modules/ModuleShell.vue'
import StaffModulePanel from '../components/modules/StaffModulePanel.vue'
import api from '../services/api'
import { getUser } from '../services/auth'
import { useLocalizedText } from '../utils/uiText'

const props = defineProps<{ moduleKey: string }>()

type TabKey = 'view' | 'reports'

interface Grade { sch_grd_id: number | null; census_id: number | null; school_name: string | null; grade_id: number | null; grade: string | null; year: number | null; stf_id: number | null; grade_head: string | null; date_updated: string | null }
interface GradeReportRow { grade_id: number; grade: string; year: number; student_count: number }
interface ClassItem { sch_grd_cls_id: number | null; census_id: number | null; school_name: string | null; grade_id: number | null; grade: string | null; class_id: number | null; class: string | null; year: number | null; stf_id: number | null; approved_std_count: number | null; std_count: number | null; class_teacher: string | null }
interface ClassReportRow { grade_id: number; grade: string; class_id: number; class: string; year: number; student_count: number }
interface StaffRow { stf_id: number; name_with_ini: string; nic_no: string | null; phone_mobile1: string | null; designation: string | null }
interface StaffMeta { current_page: number; per_page: number; total: number; last_page: number }
interface StaffOption { stf_id: number; name_with_ini: string }

const currentUser = getUser()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1)
const isPrincipal = computed(() => (currentUser?.role_id ?? 0) === 2)
const canManage = computed(() => isAdmin.value || isPrincipal.value)

const activeTab = ref<TabKey>('view')
const loading = ref(false)
const error = ref('')
const message = ref('')

const targetYear = ref(new Date().getFullYear())
const staffOptions = ref<StaffOption[]>([])
const gradeEdits = reactive<Record<number, number>>({})
const classTeacherEdits = reactive<Record<number, number>>({})
const classApprovedEdits = reactive<Record<number, number>>({})
const classCountEdits = reactive<Record<number, number>>({})

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
const text = useLocalizedText({
  en: {
    moduleFallbackTitle: 'Module',
    moduleFallbackSubtitle: 'This area is ready for the next rollout.',
    gradesTitle: 'Grades Management',
    gradesSubtitle: 'Manage grade assignments and view grade-based reports.',
    classesTitle: 'Classes Management',
    classesSubtitle: 'Manage class counts, class teachers, and class reports.',
    staffTitle: 'Staff Management',
    staffSubtitle: 'Search staff records and review update summaries.',
    paymentsTitle: 'SDS Payments',
    paymentsSubtitle: 'Payments screens will be connected in the next API rollout.',
    reportsTitle: 'Reporting & Exports',
    reportsSubtitle: 'Reporting tools are being prepared for a future release.',
    queuedModuleMessage: 'This module is queued for API rollout. Functional screens are enabled for Grades, Classes, and Staff first.',
    yearInitialized: 'Year initialized successfully.',
    initializeYearError: 'Unable to initialize year data.',
    gradeRowUpdated: 'Grade row updated.',
    updateGradeError: 'Unable to update grade row.',
    deleteGradeConfirm: 'Delete this grade row? Related classes for this grade/year will also be deleted.',
    gradeRowDeleted: 'Grade row deleted.',
    deleteGradeError: 'Unable to delete grade row.',
    classRowUpdated: 'Class row updated.',
    updateClassError: 'Unable to update class row.',
    moduleLoadError: 'Unable to load module data right now.',
  },
  si: {
    moduleFallbackTitle: 'මොඩියුලය',
    moduleFallbackSubtitle: 'මෙම කොටස ඊළඟ නිකුතුව සඳහා සූදානම්ය.',
    gradesTitle: 'ශ්‍රේණි කළමනාකරණය',
    gradesSubtitle: 'ශ්‍රේණි පැවරීම් කළමනාකරණය කර ශ්‍රේණි පදනම් වූ වාර්තා බලන්න.',
    classesTitle: 'පන්ති කළමනාකරණය',
    classesSubtitle: 'පන්ති සංඛ්‍යාව, පන්ති භාර ගුරුවරු සහ පන්ති වාර්තා කළමනාකරණය කරන්න.',
    staffTitle: 'කාර්ය මණ්ඩල කළමනාකරණය',
    staffSubtitle: 'කාර්ය මණ්ඩල වාර්තා සොයා යාවත්කාලීන සාරාංශ බලන්න.',
    paymentsTitle: 'SDS ගෙවීම්',
    paymentsSubtitle: 'ගෙවීම් තිර ඊළඟ API නිකුතුවේ සම්බන්ධ වේ.',
    reportsTitle: 'වාර්තා සහ අපනයන',
    reportsSubtitle: 'අනාගත නිකුතුවක් සඳහා වාර්තා මෙවලම් සූදානම් වෙමින් ඇත.',
    queuedModuleMessage: 'මෙම මොඩියුලය API නිකුතුව සඳහා පෝලිමේ ඇත. දැනට Grades, Classes, සහ Staff තිර පමණක් ක්‍රියාත්මක වේ.',
    yearInitialized: 'වසර සාර්ථකව ආරම්භ කරන ලදී.',
    initializeYearError: 'වසර දත්ත ආරම්භ කළ නොහැක.',
    gradeRowUpdated: 'ශ්‍රේණි පේළිය යාවත්කාලීන කරන ලදී.',
    updateGradeError: 'ශ්‍රේණි පේළිය යාවත්කාලීන කළ නොහැක.',
    deleteGradeConfirm: 'මෙම ශ්‍රේණි පේළිය මකන්නද? මෙම ශ්‍රේණිය/වසරට අදාළ පන්තිද මකා දමනු ලැබේ.',
    gradeRowDeleted: 'ශ්‍රේණි පේළිය මකා දමන ලදී.',
    deleteGradeError: 'ශ්‍රේණි පේළිය මකා දැමිය නොහැක.',
    classRowUpdated: 'පන්ති පේළිය යාවත්කාලීන කරන ලදී.',
    updateClassError: 'පන්ති පේළිය යාවත්කාලීන කළ නොහැක.',
    moduleLoadError: 'දැනට මොඩියුල දත්ත පූරණය කළ නොහැක.',
  },
  ta: {
    moduleFallbackTitle: 'தொகுதி',
    moduleFallbackSubtitle: 'இந்த பகுதி அடுத்த வெளியீட்டுக்கு தயாராக உள்ளது.',
    gradesTitle: 'தர மேலாண்மை',
    gradesSubtitle: 'தர ஒதுக்கீடுகளை நிர்வகித்து தர அடிப்படையிலான அறிக்கைகளை பாருங்கள்.',
    classesTitle: 'வகுப்பு மேலாண்மை',
    classesSubtitle: 'வகுப்பு எண்ணிக்கைகள், வகுப்பு ஆசிரியர்கள் மற்றும் வகுப்பு அறிக்கைகளை நிர்வகிக்கவும்.',
    staffTitle: 'பணியாளர் மேலாண்மை',
    staffSubtitle: 'பணியாளர் பதிவுகளை தேடி புதுப்பிப்பு சுருக்கங்களை பாருங்கள்.',
    paymentsTitle: 'SDS கட்டணங்கள்',
    paymentsSubtitle: 'கட்டண திரைகள் அடுத்த API வெளியீட்டில் இணைக்கப்படும்.',
    reportsTitle: 'அறிக்கைகள் மற்றும் ஏற்றுமதிகள்',
    reportsSubtitle: 'எதிர்கால வெளியீட்டிற்காக அறிக்கை கருவிகள் தயாராகின்றன.',
    queuedModuleMessage: 'இந்த தொகுதி API வெளியீட்டுக்காக வரிசையில் உள்ளது. தற்போது Grades, Classes மற்றும் Staff திரைகள் மட்டுமே செயல்படுகின்றன.',
    yearInitialized: 'ஆண்டு வெற்றிகரமாக தொடங்கப்பட்டது.',
    initializeYearError: 'ஆண்டு தரவை தொடங்க முடியவில்லை.',
    gradeRowUpdated: 'தர வரிசை புதுப்பிக்கப்பட்டது.',
    updateGradeError: 'தர வரிசையை புதுப்பிக்க முடியவில்லை.',
    deleteGradeConfirm: 'இந்த தர வரிசையை நீக்கவா? இந்த தரம்/ஆண்டுக்கான தொடர்புடைய வகுப்புகளும் நீக்கப்படும்.',
    gradeRowDeleted: 'தர வரிசை நீக்கப்பட்டது.',
    deleteGradeError: 'தர வரிசையை நீக்க முடியவில்லை.',
    classRowUpdated: 'வகுப்பு வரிசை புதுப்பிக்கப்பட்டது.',
    updateClassError: 'வகுப்பு வரிசையை புதுப்பிக்க முடியவில்லை.',
    moduleLoadError: 'இப்போது தொகுதி தரவை ஏற்ற முடியவில்லை.',
  },
})

const title = computed(() => ({
  grades: text.value.gradesTitle,
  classes: text.value.classesTitle,
  staff: text.value.staffTitle,
  payments: text.value.paymentsTitle,
  reports: text.value.reportsTitle,
}[props.moduleKey] ?? text.value.moduleFallbackTitle))

const subtitle = computed(() => ({
  grades: text.value.gradesSubtitle,
  classes: text.value.classesSubtitle,
  staff: text.value.staffSubtitle,
  payments: text.value.paymentsSubtitle,
  reports: text.value.reportsSubtitle,
}[props.moduleKey] ?? text.value.moduleFallbackSubtitle))

const onUpdateGradeHead = (gradeRowId: number, stfId: number): void => {
  gradeEdits[gradeRowId] = stfId
}

const onUpdateSelectedGrade = (gradeId: number): void => {
  selectedGradeId.value = gradeId
}

const onUpdateClassTeacher = (classRowId: number, stfId: number): void => {
  classTeacherEdits[classRowId] = stfId
}

const onUpdateClassApproved = (classRowId: number, value: number): void => {
  classApprovedEdits[classRowId] = value
}

const onUpdateClassCurrent = (classRowId: number, value: number): void => {
  classCountEdits[classRowId] = value
}

const loadStaffOptions = async (): Promise<void> => {
  const { data } = await api.get<{ data: StaffOption[] }>('/staff/options')
  staffOptions.value = data.data
}

const hydrateGradeEdits = (): void => {
  Object.keys(gradeEdits).forEach((k) => delete gradeEdits[Number(k)])
  for (const g of grades.value) {
    if (g.sch_grd_id) {
      gradeEdits[g.sch_grd_id] = g.stf_id ?? 0
    }
  }
}

const hydrateClassEdits = (): void => {
  Object.keys(classTeacherEdits).forEach((k) => delete classTeacherEdits[Number(k)])
  Object.keys(classApprovedEdits).forEach((k) => delete classApprovedEdits[Number(k)])
  Object.keys(classCountEdits).forEach((k) => delete classCountEdits[Number(k)])

  for (const c of classes.value) {
    if (c.sch_grd_cls_id) {
      classTeacherEdits[c.sch_grd_cls_id] = c.stf_id ?? 0
      classApprovedEdits[c.sch_grd_cls_id] = c.approved_std_count ?? 0
      classCountEdits[c.sch_grd_cls_id] = c.std_count ?? 0
    }
  }
}

const loadGrades = async (): Promise<void> => {
  const { data } = await api.get<{ year?: number; data: Grade[] }>('/grades')
  grades.value = data.data
  latestGradeYear.value = typeof data.year === 'number' ? data.year : null
  hydrateGradeEdits()
}

const loadClassesView = async (): Promise<void> => {
  const params: Record<string, number> = {}
  if (selectedGradeId.value) params.grade_id = selectedGradeId.value
  const { data } = await api.get<{ year?: number; data: ClassItem[] }>('/classes', { params })
  classes.value = data.data
  latestClassYear.value = typeof data.year === 'number' ? data.year : null
  hydrateClassEdits()
}

const initializeYear = async (): Promise<void> => {
  message.value = ''
  error.value = ''

  try {
    const { data } = await api.post('/grades/initialize-year', { year: targetYear.value })
    message.value = data?.message ?? text.value.yearInitialized
    await Promise.all([loadGrades(), loadClassesView()])
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.initializeYearError
  }
}

const saveGrade = async (gradeRowId: number): Promise<void> => {
  message.value = ''
  error.value = ''

  try {
    const stfId = gradeEdits[gradeRowId] && gradeEdits[gradeRowId] > 0 ? gradeEdits[gradeRowId] : null
    await api.put(`/grades/${gradeRowId}`, { stf_id: stfId })
    message.value = text.value.gradeRowUpdated
    await loadGrades()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.updateGradeError
  }
}

const deleteGrade = async (gradeRowId: number): Promise<void> => {
  if (!window.confirm(text.value.deleteGradeConfirm)) {
    return
  }

  message.value = ''
  error.value = ''

  try {
    await api.delete(`/grades/${gradeRowId}`)
    message.value = text.value.gradeRowDeleted
    await Promise.all([loadGrades(), loadClassesView()])
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.deleteGradeError
  }
}

const saveClass = async (classRowId: number): Promise<void> => {
  message.value = ''
  error.value = ''

  try {
    const stfId = classTeacherEdits[classRowId] && classTeacherEdits[classRowId] > 0 ? classTeacherEdits[classRowId] : null
    await api.put(`/classes/${classRowId}`, {
      stf_id: stfId,
      approved_std_count: classApprovedEdits[classRowId],
      std_count: classCountEdits[classRowId],
    })
    message.value = text.value.classRowUpdated
    await loadClassesView()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.updateClassError
  }
}

const loadGradeReport = async (): Promise<void> => {
  message.value = ''
  error.value = ''
  const params = reportYear.value ? { year: reportYear.value } : {}
  const { data } = await api.get<{ data: GradeReportRow[] }>('/grades/report', { params })
  gradeReport.value = data.data
}

const loadClassReport = async (): Promise<void> => {
  message.value = ''
  error.value = ''
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
  message.value = ''
  error.value = ''
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
      if (canManage.value && (isGrades.value || isClasses.value)) {
        await loadStaffOptions()
      }

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
      error.value = text.value.moduleLoadError
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
  message.value = ''
  error.value = ''
})

watch(() => activeTab.value, () => {
  message.value = ''
  error.value = ''
})
</script>

