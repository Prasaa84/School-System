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
      message="This module is queued for API rollout. Functional screens are enabled for Grades, Classes, and Staff first."
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

const props = defineProps<{ moduleKey: string }>()

type TabKey = 'view' | 'reports'

interface Grade { sch_grd_id: number | null; census_id: number | null; school_name: string | null; grade_id: number | null; grade: string | null; year: number | null; stf_id: number | null; grade_head: string | null }
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

const title = computed(() => ({ grades: 'Grades Management', classes: 'Classes Management', staff: 'Staff Management', payments: 'SDS Payments', reports: 'Reporting & Exports' }[props.moduleKey] ?? 'Module'))

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
    message.value = data?.message ?? 'Year initialized successfully.'
    await Promise.all([loadGrades(), loadClassesView()])
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Unable to initialize year data.'
  }
}

const saveGrade = async (gradeRowId: number): Promise<void> => {
  message.value = ''
  error.value = ''

  try {
    const stfId = gradeEdits[gradeRowId] && gradeEdits[gradeRowId] > 0 ? gradeEdits[gradeRowId] : null
    await api.put(`/grades/${gradeRowId}`, { stf_id: stfId })
    message.value = 'Grade row updated.'
    await loadGrades()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Unable to update grade row.'
  }
}

const deleteGrade = async (gradeRowId: number): Promise<void> => {
  if (!window.confirm('Delete this grade row? Related classes for this grade/year will also be deleted.')) {
    return
  }

  message.value = ''
  error.value = ''

  try {
    await api.delete(`/grades/${gradeRowId}`)
    message.value = 'Grade row deleted.'
    await Promise.all([loadGrades(), loadClassesView()])
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Unable to delete grade row.'
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
    message.value = 'Class row updated.'
    await loadClassesView()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Unable to update class row.'
  }
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
  message.value = ''
  error.value = ''
})
</script>

