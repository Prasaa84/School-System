<template>
  <section v-if="activeTab === 'view'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <h2 class="font-display text-xl font-bold">Grades (Latest Year {{ latestYear ?? '-' }})</h2>
      <div v-if="isPrincipal" class="flex items-center gap-2">
        <input :value="targetYear" type="number" min="2000" max="2100" class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm" @input="onYearInput" />
        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('initialize-year')">Initialize Year</button>
      </div>
    </div>

    <div class="overflow-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-3 py-2 text-left">Year</th>
            <th v-if="isAdmin" class="px-3 py-2 text-left">School</th>
            <th class="px-3 py-2 text-left">Grade</th>
            <th class="px-3 py-2 text-left">Grade Head</th>
            <th v-if="canManage" class="px-3 py-2 text-left">Action</th>
            <th v-if="canManage" class="px-3 py-2 text-left">Delete</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="grade in grades" :key="grade.sch_grd_id ?? `${grade.school_id}-${grade.grade_id}-${grade.year}`">
            <td class="px-3 py-2">{{ grade.year ?? '-' }}</td>
            <td v-if="isAdmin" class="px-3 py-2">{{ grade.school_name || grade.school_id || '-' }}</td>
            <td class="px-3 py-2">{{ grade.grade || '-' }}</td>
            <td class="px-3 py-2">
              <template v-if="canManage && grade.sch_grd_id">
                <select :value="gradeEdits[grade.sch_grd_id] ?? 0" class="rounded border border-slate-300 px-2 py-1 text-sm" @change="onGradeHeadChange(grade.sch_grd_id, $event)">
                  <option :value="0">-- None --</option>
                  <option v-for="staff in staffOptions" :key="staff.stf_id" :value="staff.stf_id">{{ staff.name_with_ini }}</option>
                </select>
              </template>
              <template v-else>
                {{ grade.grade_head || '-' }}
              </template>
            </td>
            <td v-if="canManage" class="px-3 py-2">
              <button v-if="grade.sch_grd_id" class="rounded bg-cyan-600 px-3 py-1 text-xs font-semibold text-white" @click="$emit('save-grade', grade.sch_grd_id)">Save</button>
            </td>
            <td v-if="canManage" class="px-3 py-2">
              <button v-if="grade.sch_grd_id" class="rounded bg-rose-600 px-3 py-1 text-xs font-semibold text-white" @click="$emit('delete-grade', grade.sch_grd_id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section v-if="activeTab === 'reports'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <h2 class="font-display text-xl font-bold">Grade Reports</h2>
      <div class="flex gap-2">
        <select :value="reportYear" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onReportYearChange">
          <option :value="0">All Years</option>
          <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
        </select>
        <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('load-report')">View</button>
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
</template>

<script setup lang="ts">
interface Grade { sch_grd_id: number | null; school_id: number | null; school_name: string | null; grade_id: number | null; grade: string | null; year: number | null; stf_id: number | null; grade_head: string | null }
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
