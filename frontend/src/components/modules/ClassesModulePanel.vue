<template>
  <section v-if="activeTab === 'view'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 space-y-4">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <h2 class="font-display text-xl font-bold">{{ text.classes }} ({{ text.year }} {{ selectedYear }})</h2>
        <div class="flex gap-2">
          <select :value="selectedYear" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onYearChange">
            <option :value="0">{{ text.selectYear }}</option>
            <option v-for="year in yearOptions" :key="`class-view-year-${year}`" :value="year">{{ year }}</option>
          </select>
          <select :value="selectedGradeId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onGradeFilterChange">
            <option :value="0">{{ text.selectGrade }}</option>
            <option v-for="option in classGradeOptions" :key="option.grade_id" :value="option.grade_id">{{ option.grade }}</option>
          </select>
        </div>
      </div>

      <div v-if="canManage && classes.length === 0" class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-4">
        <label class="text-sm text-slate-700">
          {{ text.grade }}
          <select :value="createGradeId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onCreateGradeChange">
            <option :value="0">{{ text.selectGrade }}</option>
            <option v-for="option in classGradeOptions" :key="`create-${option.grade_id}`" :value="option.grade_id">{{ option.grade }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.class }}
          <select :value="createClassId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onCreateClassChange">
            <option :value="0">{{ text.selectClass }}</option>
            <option v-for="option in createClassOptions" :key="option.class_id" :value="option.class_id">{{ option.class }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.approved }}
          <input :value="createApprovedCount" type="number" min="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @input="onCreateApprovedChange" />
        </label>

        <div class="flex items-end">
          <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('add-class')">{{ text.addClass }}</button>
        </div>
      </div>
    </div>

    <div class="overflow-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-3 py-2 text-left">{{ text.year }}</th>
            <th v-if="isAdmin" class="px-3 py-2 text-left">{{ text.school }}</th>
            <th class="px-3 py-2 text-left">{{ text.grade }}</th>
            <th class="px-3 py-2 text-left">{{ text.class }}</th>
            <th class="px-3 py-2 text-left">{{ text.approved }}</th>
            <th class="px-3 py-2 text-left">{{ text.current }}</th>
            <th class="px-3 py-2 text-left">{{ text.classTeacher }}</th>
            <th v-if="canManage" class="px-3 py-2 text-left">{{ text.action }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="item in classes" :key="item.sch_grd_cls_id ?? `${item.census_id}-${item.grade_id}-${item.class_id}-${item.year}`">
            <td class="px-3 py-2">{{ item.year ?? '-' }}</td>
            <td v-if="isAdmin" class="px-3 py-2">{{ item.school_name || item.census_id || '-' }}</td>
            <td class="px-3 py-2">{{ item.grade || '-' }}</td>
            <td class="px-3 py-2">{{ item.class || '-' }}</td>
            <td class="px-3 py-2">
              <input v-if="canManage && item.sch_grd_cls_id" :value="classApprovedEdits[item.sch_grd_cls_id] ?? 0" type="number" class="w-20 rounded border border-slate-300 px-2 py-1 text-sm" @input="onApprovedChange(item.sch_grd_cls_id, $event)" />
              <template v-else>{{ item.approved_std_count ?? '-' }}</template>
            </td>
            <td class="px-3 py-2">{{ item.std_count ?? 0 }}</td>
            <td class="px-3 py-2">
              <template v-if="canManage && item.sch_grd_cls_id">
                <select :value="classTeacherEdits[item.sch_grd_cls_id] ?? 0" class="rounded border border-slate-300 px-2 py-1 text-sm" @change="onTeacherChange(item.sch_grd_cls_id, $event)">
                  <option :value="0">{{ text.none }}</option>
                  <option v-for="staff in teacherOptionsForRow(item.sch_grd_cls_id)" :key="staff.stf_id" :value="staff.stf_id">{{ staff.name_with_ini }}</option>
                </select>
              </template>
              <template v-else>{{ item.class_teacher || '-' }}</template>
            </td>
            <td v-if="canManage" class="px-3 py-2">
              <div class="flex gap-2">
                <button v-if="item.sch_grd_cls_id" class="rounded bg-cyan-600 px-3 py-1 text-xs font-semibold text-white" @click="$emit('save-class', item.sch_grd_cls_id)">{{ text.save }}</button>
                <button v-if="item.sch_grd_cls_id" class="rounded bg-rose-600 px-3 py-1 text-xs font-semibold text-white" @click="$emit('delete-class', item.sch_grd_cls_id)">{{ text.delete }}</button>
                <button v-if="item.sch_grd_cls_id" class="rounded bg-emerald-600 px-3 py-1 text-xs font-semibold text-white" @click="$emit('quick-add-class', item.sch_grd_cls_id)">{{ text.addNext }}</button>
              </div>
            </td>
          </tr>
          <tr v-if="classes.length === 0">
            <td :colspan="isAdmin ? 8 : 7" class="px-3 py-6 text-center text-slate-500">{{ text.noData }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section v-if="activeTab === 'reports'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <h2 class="font-display text-xl font-bold">{{ text.classReports }}</h2>
      <div class="flex gap-2">
        <select :value="reportYear" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onReportYearChange"><option :value="0">{{ text.selectYear }}</option><option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option></select>
        <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('load-report')">{{ text.view }}</button>
      </div>
    </div>
    <div class="overflow-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">{{ text.year }}</th><th class="px-3 py-2 text-left">{{ text.grade }}</th><th class="px-3 py-2 text-left">{{ text.class }}</th><th class="px-3 py-2 text-left">{{ text.studentCount }}</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in classReport" :key="`${row.year}-${row.grade_id}-${row.class_id}`"><td class="px-3 py-2">{{ row.year }}</td><td class="px-3 py-2">{{ row.grade }}</td><td class="px-3 py-2">{{ row.class }}</td><td class="px-3 py-2">{{ row.student_count }}</td></tr>
          <tr v-if="classReport.length === 0"><td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ text.noData }}</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useLocalizedText } from '../../utils/uiText'

interface ClassItem { sch_grd_cls_id: number | null; census_id: number | null; school_name: string | null; grade_id: number | null; grade: string | null; class_id: number | null; class: string | null; year: number | null; stf_id: number | null; approved_std_count: number | null; std_count: number | null; class_teacher: string | null }
interface ClassReportRow { grade_id: number; grade: string; class_id: number; class: string; year: number; student_count: number }
interface StaffOption { stf_id: number; name_with_ini: string }
interface ClassGradeOption { grade_id: number; grade: string }
interface ClassOption { class_id: number; class: string }

const props = defineProps<{
  activeTab: 'view' | 'reports'
  isAdmin: boolean
  canManage: boolean
  latestYear: number | null
  selectedYear: number
  selectedGradeId: number
  classGradeOptions: ClassGradeOption[]
  classes: ClassItem[]
  classTeacherEdits: Record<number, number>
  classApprovedEdits: Record<number, number>
  staffOptions: StaffOption[]
  createGradeId: number
  createClassId: number
  createApprovedCount: number
  createClassOptions: ClassOption[]
  reportYear: number
  yearOptions: number[]
  classReport: ClassReportRow[]
}>()

const emit = defineEmits<{
  (e: 'update:selected-year', year: number): void
  (e: 'update:selected-grade', gradeId: number): void
  (e: 'update:create-grade', gradeId: number): void
  (e: 'update:create-class', classId: number): void
  (e: 'update:create-approved', value: number): void
  (e: 'update:report-year', year: number): void
  (e: 'update-class-teacher', classRowId: number, stfId: number): void
  (e: 'update-class-approved', classRowId: number, value: number): void
  (e: 'add-class'): void
  (e: 'quick-add-class', classRowId: number): void
  (e: 'save-class', classRowId: number): void
  (e: 'delete-class', classRowId: number): void
  (e: 'load-report'): void
}>()

const text = useLocalizedText({
  en: {
    classes: 'Classes',
    latestYear: 'Latest Year',
    year: 'Year',
    school: 'School',
    grade: 'Grade',
    class: 'Class',
    selectYear: 'Select Year',
    selectGrade: 'Select Grade',
    selectClass: 'Select Class',
    approved: 'Approved',
    current: 'Current',
    classTeacher: 'Class Teacher',
    action: 'Action',
    none: '-- None --',
    addClass: 'Add Class',
    addNext: '+ Class',
    save: 'Save',
    delete: 'Delete',
    classReports: 'Class Reports',
    view: 'View',
    studentCount: 'Student Count',
    noData: 'No data found for the selected year.',
  },
  si: {
    classes: 'පන්ති',
    latestYear: 'නවතම වසර',
    year: 'වසර',
    school: 'පාසල',
    grade: 'ශ්‍රේණිය',
    class: 'පන්තිය',
    selectYear: 'වසර තෝරන්න',
    selectGrade: 'ශ්‍රේණිය තෝරන්න',
    selectClass: 'පන්තිය තෝරන්න',
    approved: 'අනුමත',
    current: 'වර්තමාන',
    classTeacher: 'පන්ති භාර ගුරු',
    action: 'ක්‍රියාව',
    none: '-- නැත --',
    addClass: 'පන්තිය එක් කරන්න',
    addNext: '+ පන්තිය',
    save: 'සුරකින්න',
    delete: 'මකන්න',
    classReports: 'පන්ති වාර්තා',
    view: 'දර්ශනය',
    studentCount: 'සිසුන් ගණන',
    noData: 'තෝරාගත් වසර සඳහා දත්ත හමු නොවීය.',
  },
  ta: {
    classes: 'வகுப்புகள்',
    latestYear: 'சமீபத்திய ஆண்டு',
    year: 'ஆண்டு',
    school: 'பள்ளி',
    grade: 'தரம்',
    class: 'வகுப்பு',
    selectYear: 'ஆண்டை தேர்ந்தெடுக்கவும்',
    selectGrade: 'தரத்தை தேர்ந்தெடுக்கவும்',
    selectClass: 'வகுப்பை தேர்ந்தெடுக்கவும்',
    approved: 'அங்கீகரிக்கப்பட்டது',
    current: 'தற்போது',
    classTeacher: 'வகுப்பு ஆசிரியர்',
    action: 'செயல்',
    none: '-- இல்லை --',
    addClass: 'வகுப்பை சேர்',
    addNext: '+ வகுப்பு',
    save: 'சேமி',
    delete: 'நீக்கு',
    classReports: 'வகுப்பு அறிக்கைகள்',
    view: 'பார்வை',
    studentCount: 'மாணவர் எண்ணிக்கை',
    noData: 'தேர்ந்தெடுக்கப்பட்ட ஆண்டிற்கான தரவு இல்லை.',
  },
})

const assignedTeacherMap = computed(() => {
  const map = new Map<number, number>()

  for (const item of props.classes) {
    if (!item.sch_grd_cls_id) {
      continue
    }

    const teacherId = props.classTeacherEdits[item.sch_grd_cls_id] ?? 0
    if (teacherId > 0) {
      map.set(item.sch_grd_cls_id, teacherId)
    }
  }

  return map
})

const teacherOptionsForRow = (classRowId: number): StaffOption[] => {
  const selectedTeacherId = props.classTeacherEdits[classRowId] ?? 0

  return props.staffOptions.filter((staff) => {
    if (staff.stf_id === selectedTeacherId) {
      return true
    }

    for (const [rowId, teacherId] of assignedTeacherMap.value.entries()) {
      if (rowId !== classRowId && teacherId === staff.stf_id) {
        return false
      }
    }

    return true
  })
}

const onYearChange = (event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:selected-year', Number.isFinite(value) ? value : props.selectedYear)
}

const onGradeFilterChange = (event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:selected-grade', Number.isFinite(value) ? value : 0)
}

const onCreateGradeChange = (event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:create-grade', Number.isFinite(value) ? value : 0)
}

const onCreateClassChange = (event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:create-class', Number.isFinite(value) ? value : 0)
}

const onCreateApprovedChange = (event: Event): void => {
  const value = Number((event.target as HTMLInputElement).value)
  emit('update:create-approved', Number.isFinite(value) ? value : 0)
}

const onReportYearChange = (event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:report-year', Number.isFinite(value) ? value : 0)
}

const onTeacherChange = (classRowId: number, event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update-class-teacher', classRowId, Number.isFinite(value) ? value : 0)
}

const onApprovedChange = (classRowId: number, event: Event): void => {
  const value = Number((event.target as HTMLInputElement).value)
  emit('update-class-approved', classRowId, Number.isFinite(value) ? value : 0)
}

</script>

