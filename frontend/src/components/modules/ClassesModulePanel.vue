<template>
  <section v-if="activeTab === 'view'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <h2 class="font-display text-xl font-bold">Classes (Latest Year {{ latestYear ?? '-' }})</h2>
      <div class="flex gap-2">
        <select :value="selectedGradeId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onGradeFilterChange">
          <option :value="0">All Grades</option>
          <option v-for="option in classGradeOptions" :key="option.grade_id" :value="option.grade_id">{{ option.grade }}</option>
        </select>
      </div>
    </div>

    <div class="overflow-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-3 py-2 text-left">Year</th>
            <th v-if="isAdmin" class="px-3 py-2 text-left">School</th>
            <th class="px-3 py-2 text-left">Grade</th>
            <th class="px-3 py-2 text-left">Class</th>
            <th class="px-3 py-2 text-left">Approved</th>
            <th class="px-3 py-2 text-left">Current</th>
            <th class="px-3 py-2 text-left">Class Teacher</th>
            <th v-if="canManage" class="px-3 py-2 text-left">Action</th>
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
            <td class="px-3 py-2">
              <input v-if="canManage && item.sch_grd_cls_id" :value="classCountEdits[item.sch_grd_cls_id] ?? 0" type="number" class="w-20 rounded border border-slate-300 px-2 py-1 text-sm" @input="onCurrentChange(item.sch_grd_cls_id, $event)" />
              <template v-else>{{ item.std_count ?? '-' }}</template>
            </td>
            <td class="px-3 py-2">
              <template v-if="canManage && item.sch_grd_cls_id">
                <select :value="classTeacherEdits[item.sch_grd_cls_id] ?? 0" class="rounded border border-slate-300 px-2 py-1 text-sm" @change="onTeacherChange(item.sch_grd_cls_id, $event)">
                  <option :value="0">-- None --</option>
                  <option v-for="staff in staffOptions" :key="staff.stf_id" :value="staff.stf_id">{{ staff.name_with_ini }}</option>
                </select>
              </template>
              <template v-else>{{ item.class_teacher || '-' }}</template>
            </td>
            <td v-if="canManage" class="px-3 py-2">
              <button v-if="item.sch_grd_cls_id" class="rounded bg-cyan-600 px-3 py-1 text-xs font-semibold text-white" @click="$emit('save-class', item.sch_grd_cls_id)">Save</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section v-if="activeTab === 'reports'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <h2 class="font-display text-xl font-bold">Class Reports</h2>
      <div class="flex gap-2">
        <select :value="reportYear" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onReportYearChange"><option :value="0">All Years</option><option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option></select>
        <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('load-report')">View</button>
      </div>
    </div>
    <div class="overflow-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Year</th><th class="px-3 py-2 text-left">Grade</th><th class="px-3 py-2 text-left">Class</th><th class="px-3 py-2 text-left">Student Count</th></tr></thead>
        <tbody class="divide-y divide-slate-100"><tr v-for="row in classReport" :key="`${row.year}-${row.grade_id}-${row.class_id}`"><td class="px-3 py-2">{{ row.year }}</td><td class="px-3 py-2">{{ row.grade }}</td><td class="px-3 py-2">{{ row.class }}</td><td class="px-3 py-2">{{ row.student_count }}</td></tr></tbody>
      </table>
    </div>
  </section>
</template>

<script setup lang="ts">
interface ClassItem { sch_grd_cls_id: number | null; census_id: number | null; school_name: string | null; grade_id: number | null; grade: string | null; class_id: number | null; class: string | null; year: number | null; stf_id: number | null; approved_std_count: number | null; std_count: number | null; class_teacher: string | null }
interface ClassReportRow { grade_id: number; grade: string; class_id: number; class: string; year: number; student_count: number }
interface StaffOption { stf_id: number; name_with_ini: string }
interface ClassGradeOption { grade_id: number; grade: string }

const props = defineProps<{
  activeTab: 'view' | 'reports'
  isAdmin: boolean
  canManage: boolean
  latestYear: number | null
  selectedGradeId: number
  classGradeOptions: ClassGradeOption[]
  classes: ClassItem[]
  classTeacherEdits: Record<number, number>
  classApprovedEdits: Record<number, number>
  classCountEdits: Record<number, number>
  staffOptions: StaffOption[]
  reportYear: number
  yearOptions: number[]
  classReport: ClassReportRow[]
}>()

const emit = defineEmits<{
  (e: 'update:selected-grade', gradeId: number): void
  (e: 'update:report-year', year: number): void
  (e: 'update-class-teacher', classRowId: number, stfId: number): void
  (e: 'update-class-approved', classRowId: number, value: number): void
  (e: 'update-class-current', classRowId: number, value: number): void
  (e: 'save-class', classRowId: number): void
  (e: 'load-report'): void
}>()

const onGradeFilterChange = (event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:selected-grade', Number.isFinite(value) ? value : 0)
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

const onCurrentChange = (classRowId: number, event: Event): void => {
  const value = Number((event.target as HTMLInputElement).value)
  emit('update-class-current', classRowId, Number.isFinite(value) ? value : 0)
}
</script>

