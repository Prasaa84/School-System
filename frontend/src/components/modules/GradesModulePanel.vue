<template>
  <section v-if="activeTab === 'view'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <h2 class="font-display text-xl font-bold">{{ text.grades }} ({{ text.latestYear }} {{ latestYear ?? '-' }})</h2>
      <div v-if="isPrincipal" class="flex items-center gap-2">
        <input :value="targetYear" type="number" min="2000" max="2100" class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm" @input="onYearInput" />
        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('initialize-year')">{{ text.initializeYear }}</button>
      </div>
    </div>

    <div class="overflow-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-3 py-2 text-left">{{ text.year }}</th>
            <th v-if="isAdmin" class="px-3 py-2 text-left">{{ text.school }}</th>
            <th class="px-3 py-2 text-left">{{ text.grade }}</th>
            <th class="px-3 py-2 text-left">{{ text.gradeHead }}</th>
            <th v-if="canManage" class="px-3 py-2 text-left">{{ text.action }}</th>
            <th v-if="canManage" class="px-3 py-2 text-left">{{ text.delete }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="grade in grades" :key="grade.sch_grd_id ?? `${grade.census_id}-${grade.grade_id}-${grade.year}`">
            <td class="px-3 py-2">{{ grade.year ?? '-' }}</td>
            <td v-if="isAdmin" class="px-3 py-2">{{ grade.school_name || grade.census_id || '-' }}</td>
            <td class="px-3 py-2">{{ grade.grade || '-' }}</td>
            <td class="px-3 py-2">
              <template v-if="canManage && grade.sch_grd_id">
                <select :value="gradeEdits[grade.sch_grd_id] ?? 0" class="rounded border border-slate-300 px-2 py-1 text-sm" @change="onGradeHeadChange(grade.sch_grd_id, $event)">
                  <option :value="0">{{ text.none }}</option>
                  <option v-for="staff in staffOptions" :key="staff.stf_id" :value="staff.stf_id">{{ staff.name_with_ini }}</option>
                </select>
              </template>
              <template v-else>
                {{ grade.grade_head || '-' }}
              </template>
            </td>
            <td v-if="canManage" class="px-3 py-2">
              <button v-if="grade.sch_grd_id" class="rounded bg-cyan-600 px-3 py-1 text-xs font-semibold text-white" @click="$emit('save-grade', grade.sch_grd_id)">{{ text.save }}</button>
            </td>
            <td v-if="canManage" class="px-3 py-2">
              <button v-if="grade.sch_grd_id" class="rounded bg-rose-600 px-3 py-1 text-xs font-semibold text-white" @click="$emit('delete-grade', grade.sch_grd_id)">{{ text.delete }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section v-if="activeTab === 'reports'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <h2 class="font-display text-xl font-bold">{{ text.gradeReports }}</h2>
      <div class="flex gap-2">
        <select :value="reportYear" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onReportYearChange">
          <option :value="0">{{ text.allYears }}</option>
          <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
        </select>
        <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('load-report')">{{ text.view }}</button>
      </div>
    </div>
    <div class="overflow-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">{{ text.year }}</th><th class="px-3 py-2 text-left">{{ text.grade }}</th><th class="px-3 py-2 text-left">{{ text.studentCount }}</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in gradeReport" :key="`${row.year}-${row.grade_id}`"><td class="px-3 py-2">{{ row.year }}</td><td class="px-3 py-2">{{ row.grade }}</td><td class="px-3 py-2">{{ row.student_count }}</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup lang="ts">
import { useLocalizedText } from '../../utils/uiText'

interface Grade { sch_grd_id: number | null; census_id: number | null; school_name: string | null; grade_id: number | null; grade: string | null; year: number | null; stf_id: number | null; grade_head: string | null }
interface GradeReportRow { grade_id: number; grade: string; year: number; student_count: number }
interface StaffOption { stf_id: number; name_with_ini: string }

const props = defineProps<{
  activeTab: 'view' | 'reports'
  isAdmin: boolean
  isPrincipal: boolean
  canManage: boolean
  latestYear: number | null
  targetYear: number
  grades: Grade[]
  gradeEdits: Record<number, number>
  staffOptions: StaffOption[]
  reportYear: number
  yearOptions: number[]
  gradeReport: GradeReportRow[]
}>()

const emit = defineEmits<{
  (e: 'update:target-year', year: number): void
  (e: 'update:report-year', year: number): void
  (e: 'update-grade-head', gradeRowId: number, stfId: number): void
  (e: 'initialize-year'): void
  (e: 'save-grade', gradeRowId: number): void
  (e: 'delete-grade', gradeRowId: number): void
  (e: 'load-report'): void
}>()

const text = useLocalizedText({
  en: {
    grades: 'Grades',
    latestYear: 'Latest Year',
    initializeYear: 'Initialize Year',
    year: 'Year',
    school: 'School',
    grade: 'Grade',
    gradeHead: 'Grade Head',
    action: 'Action',
    delete: 'Delete',
    none: '-- None --',
    save: 'Save',
    gradeReports: 'Grade Reports',
    allYears: 'All Years',
    view: 'View',
    studentCount: 'Student Count',
  },
  si: {
    grades: 'ශ්‍රේණි',
    latestYear: 'නවතම වසර',
    initializeYear: 'වසර ආරම්භ කරන්න',
    year: 'වසර',
    school: 'පාසල',
    grade: 'ශ්‍රේණිය',
    gradeHead: 'ශ්‍රේණි ප්‍රධානියා',
    action: 'ක්‍රියාව',
    delete: 'මකන්න',
    none: '-- නැත --',
    save: 'සුරකින්න',
    gradeReports: 'ශ්‍රේණි වාර්තා',
    allYears: 'සියලු වසර',
    view: 'දර්ශනය',
    studentCount: 'සිසුන් ගණන',
  },
  ta: {
    grades: 'தரங்கள்',
    latestYear: 'சமீபத்திய ஆண்டு',
    initializeYear: 'ஆண்டை தொடங்கு',
    year: 'ஆண்டு',
    school: 'பள்ளி',
    grade: 'தரம்',
    gradeHead: 'தரத் தலைவர்',
    action: 'செயல்',
    delete: 'நீக்கு',
    none: '-- இல்லை --',
    save: 'சேமி',
    gradeReports: 'தர அறிக்கைகள்',
    allYears: 'அனைத்து ஆண்டுகள்',
    view: 'பார்வை',
    studentCount: 'மாணவர் எண்ணிக்கை',
  },
})

const onYearInput = (event: Event): void => {
  const value = Number((event.target as HTMLInputElement).value)
  emit('update:target-year', Number.isFinite(value) ? value : props.targetYear)
}

const onReportYearChange = (event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:report-year', Number.isFinite(value) ? value : 0)
}

const onGradeHeadChange = (gradeRowId: number, event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update-grade-head', gradeRowId, Number.isFinite(value) ? value : 0)
}
</script>

