<template>
  <div class="space-y-5">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p class="font-brand text-xs uppercase tracking-[0.2em] text-slate-500">{{ text.studentsModule }}</p>
      <h1 class="mt-2 font-display text-2xl font-bold text-slate-900">{{ text.title }}</h1>
      <p class="mt-2 text-sm text-slate-600">{{ text.subtitle }}</p>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <fieldset class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
        <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.classSelection }}</legend>
        <div class="grid gap-3 md:grid-cols-4">
          <label v-if="isAdmin" class="text-sm text-slate-700 md:col-span-3">
            {{ text.school }}
            <select v-model.number="adminSchoolContextCensusId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onSchoolChange">
              <option :value="0">{{ text.selectSchool }}</option>
              <option v-for="row in schools" :key="row.id" :value="row.id">{{ row.label }}</option>
            </select>
          </label>

          <label class="text-sm text-slate-700">
            {{ text.selectedYear }}
            <select v-model.number="selectedYear" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="isAdmin && adminSchoolContextCensusId <= 0">
              <option :value="0">{{ text.selectYear }}</option>
              <option v-for="year in academicYears" :key="`selected-year-${year}`" :value="year">{{ year }}</option>
            </select>
          </label>

          <label class="text-sm text-slate-700">
            {{ text.selectedGrade }}
            <select v-model.number="selectedGradeId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="selectedYear <= 0">
              <option :value="0">{{ text.selectGrade }}</option>
              <option v-for="row in gradesForYear" :key="`selected-grade-${row.grade_id}`" :value="row.grade_id">{{ row.grade }}</option>
            </select>
          </label>

          <div class="text-sm text-slate-700">
            <p>{{ text.classCount }}</p>
            <div class="mt-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900">{{ classBoxes.length }}</div>
          </div>

          <div class="flex items-end">
            <button
              class="w-full whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="downloadingTemplate"
              @click="downloadTemplate"
            >
              {{ downloadingTemplate ? text.downloadingTemplate : text.downloadTemplate }}
            </button>
          </div>
        </div>
      </fieldset>

      <p v-if="pageMessage" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ pageMessage }}</p>
      <p v-if="pageError" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ pageError }}</p>
    </section>

    <section v-if="selectedYear > 0 && selectedGradeId > 0" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between">
        <div>
          <h2 class="font-display text-xl font-bold text-slate-900">{{ text.studentsInClassesHeading }}</h2>
          <p class="text-sm text-slate-500">{{ text.studentsInClassesHelp }}</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ text.totalStudents }}: {{ totalClassStudents }}</span>
      </div>

      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <article v-for="classBox in classBoxes" :key="`class-box-${classBox.sch_grd_cls_id}`" class="rounded-xl border border-slate-200 bg-slate-50 p-3 shadow-sm">
          <div class="flex items-start gap-3">
            <div class="flex items-center gap-2">
              <h3 class="font-display text-sm font-bold tracking-tight text-slate-900">{{ classBox.class }}</h3>
              <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-500">{{ text.studentsCount }}: {{ classBox.student_count }}</span>
            </div>
          </div>

          <div class="mt-3 flex items-center gap-2">
            <input :ref="(el) => setFileInputRef(classBox.sch_grd_cls_id, el)" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFileSelected(classBox.sch_grd_cls_id, $event)" />
            <button
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 transition hover:border-slate-400 hover:bg-slate-100 hover:text-slate-800 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
              :title="downloadingClassId === classBox.sch_grd_cls_id ? text.downloadingClass : text.downloadClass"
              :aria-label="downloadingClassId === classBox.sch_grd_cls_id ? text.downloadingClass : text.downloadClass"
              :disabled="downloadingClassId === classBox.sch_grd_cls_id"
              @click="downloadClassStudents(classBox)"
            >
              <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path d="M10 2.5a.75.75 0 0 1 .75.75v7.2l2.22-2.22a.75.75 0 1 1 1.06 1.06l-3.5 3.5a.75.75 0 0 1-1.06 0l-3.5-3.5a.75.75 0 1 1 1.06-1.06l2.22 2.22v-7.2A.75.75 0 0 1 10 2.5Z" />
                <path d="M4 13.5a.75.75 0 0 1 .75.75v1h10.5v-1a.75.75 0 0 1 1.5 0v1.5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-1.5A.75.75 0 0 1 4 13.5Z" />
              </svg>
            </button>
            <button
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 transition hover:border-cyan-300 hover:bg-cyan-50 hover:text-cyan-700"
              :title="text.chooseFile"
              :aria-label="text.chooseFile"
              @click="openFileDialog(classBox.sch_grd_cls_id)"
            >
              <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path d="M10 2.5 3 6v8l7 3.5 7-3.5V6l-7-3.5Zm0 1.7 4.8 2.4L10 9 5.2 6.6 10 4.2Zm-5 3.7 4.2 2.1v5.1L5 13V7.9Zm5.8 7.2V10l4.2-2.1V13l-4.2 2.1Z" />
              </svg>
            </button>
            <button
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-[#0f8ea8] text-white transition hover:bg-[#0c7990] disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400"
              :title="uploadingClassId === classBox.sch_grd_cls_id ? text.uploading : text.upload"
              :aria-label="uploadingClassId === classBox.sch_grd_cls_id ? text.uploading : text.upload"
              :disabled="uploadingClassId === classBox.sch_grd_cls_id || !selectedFilesByClass[classBox.sch_grd_cls_id]"
              @click="uploadClassFile(classBox)"
            >
              <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path d="M10 17.5a.75.75 0 0 1-.75-.75v-7.2l-2.22 2.22a.75.75 0 1 1-1.06-1.06l3.5-3.5a.75.75 0 0 1 1.06 0l3.5 3.5a.75.75 0 1 1-1.06 1.06l-2.22-2.22v7.2A.75.75 0 0 1 10 17.5Z" />
                <path d="M4 4.25a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1.5a.75.75 0 0 1-1.5 0v-1H5.5v1a.75.75 0 0 1-1.5 0v-1.5Z" />
              </svg>
            </button>
            <button
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-rose-600 text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400"
              :title="clearingClassId === classBox.sch_grd_cls_id ? text.clearing : text.clearClass"
              :aria-label="clearingClassId === classBox.sch_grd_cls_id ? text.clearing : text.clearClass"
              :disabled="clearingClassId === classBox.sch_grd_cls_id"
              @click="openClearClassDialog(classBox)"
            >
              <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path d="M7 2.5h6a1 1 0 0 1 1 1V5h2.25a.75.75 0 0 1 0 1.5h-.56l-.6 8.18A2 2 0 0 1 13.1 16.5H6.9a2 2 0 0 1-1.99-1.82L4.31 6.5h-.56a.75.75 0 0 1 0-1.5H6V3.5a1 1 0 0 1 1-1Zm1 2.5h4V4h-4v1Zm-1.59 1.5.57 7.96a.5.5 0 0 0 .5.46h6.04a.5.5 0 0 0 .5-.46l.57-7.96H6.41Z" />
              </svg>
            </button>
          </div>

          <p v-if="selectedFilesByClass[classBox.sch_grd_cls_id]" class="mt-2 inline-flex max-w-full items-center gap-1 rounded-full bg-cyan-50 px-2 py-1 text-xs text-cyan-700">
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 shrink-0">
              <path d="M5.5 4A2.5 2.5 0 0 1 8 1.5h5A2.5 2.5 0 0 1 15.5 4v8.25a4.25 4.25 0 1 1-8.5 0V5.5a2.75 2.75 0 1 1 5.5 0v6.25a1.25 1.25 0 1 1-2.5 0V6.5a.75.75 0 0 1 1.5 0v5.25a.25.25 0 1 0 .5 0V5.5a1.25 1.25 0 1 0-2.5 0v6.75a2.75 2.75 0 1 0 5.5 0V4A1 1 0 0 0 13 3h-5a1 1 0 0 0-1 1v8.25a.75.75 0 0 1-1.5 0V4Z" />
            </svg>
            <span class="truncate">{{ selectedFilesByClass[classBox.sch_grd_cls_id]?.name }}</span>
          </p>

            <div v-if="hasUploadErrors(uploadResultsByClass[classBox.sch_grd_cls_id])" class="mt-3 rounded-lg border border-emerald-200 bg-white p-2.5 text-xs">
              <p class="font-semibold text-emerald-700">{{ text.lastUpload }}</p>
              <p class="mt-1 text-slate-700">{{ text.failed }}: {{ uploadResultsByClass[classBox.sch_grd_cls_id]?.failed_count ?? 0 }}</p>
              <button
                class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                @click="openUploadErrorsModal(classBox)"
              >
                {{ text.viewErrors }}
              </button>
            </div>

          <div class="mt-3 max-h-64 overflow-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-2 py-1.5 text-left text-xs font-semibold text-slate-600">{{ text.indexNo }}</th>
                  <th class="px-2 py-1.5 text-left text-xs font-semibold text-slate-600">{{ text.name }}</th>
                  <th v-if="isPrincipal" class="w-8 px-1.5 py-1.5 text-right text-xs font-semibold text-slate-600"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="classBox.students.length === 0">
                  <td :colspan="isPrincipal ? 3 : 2" class="px-2 py-4 text-center text-xs text-slate-500">{{ text.emptyClass }}</td>
                </tr>
                <tr v-for="student in classBox.students" :key="`class-student-${classBox.sch_grd_cls_id}-${student.std_id}`">
                  <td class="px-2 py-1.5 text-xs font-medium text-slate-800">{{ student.index_no }}</td>
                  <td class="px-2 py-1.5 text-xs text-slate-700">{{ student.name_with_initials }}</td>
                  <td v-if="isPrincipal" class="px-1.5 py-1 text-right">
                    <button
                      class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                      :title="removingStudentAssignmentKey === `${classBox.sch_grd_cls_id}:${student.std_id}` ? text.removingStudent : text.removeStudent"
                      :aria-label="removingStudentAssignmentKey === `${classBox.sch_grd_cls_id}:${student.std_id}` ? text.removingStudent : text.removeStudent"
                      :disabled="removingStudentAssignmentKey === `${classBox.sch_grd_cls_id}:${student.std_id}`"
                      @click="openRemoveStudentDialog(classBox, student)"
                    >
                      <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                        <path d="M7 2.5h6a1 1 0 0 1 1 1V5h2.25a.75.75 0 0 1 0 1.5h-.56l-.6 8.18A2 2 0 0 1 13.1 16.5H6.9a2 2 0 0 1-1.99-1.82L4.31 6.5h-.56a.75.75 0 0 1 0-1.5H6V3.5a1 1 0 0 1 1-1Zm1 2.5h4V4h-4v1Zm-1.59 1.5.57 7.96a.5.5 0 0 0 .5.46h6.04a.5.5 0 0 0 .5-.46l.57-7.96H6.41Z" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>
      </div>
    </section>

    <div v-if="activeUploadErrorsClass && activeUploadErrorsSummary" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/45 p-4" @click="closeUploadErrorsModal">
      <div class="max-h-[85vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" @click.stop>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.uploadErrorsTitle }}</p>
            <h3 class="mt-1 font-display text-lg font-bold text-slate-900">{{ uploadErrorsContextLabel }}</h3>
          </div>
          <button
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50"
            :title="text.close"
            :aria-label="text.close"
            @click="closeUploadErrorsModal"
          >
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
              <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
            </svg>
          </button>
        </div>

        <div class="max-h-[calc(85vh-84px)] overflow-auto px-5 py-4 text-sm">
          <div v-if="activeUploadErrorsSummary.missing_indexes.length" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="font-semibold text-amber-900">{{ text.missingStudents }}</p>
            <div class="mt-2 space-y-1 text-amber-900">
              <p v-for="item in sortedMissingIndexes" :key="`modal-missing-${item.row}-${item.index_no}`">
                {{ text.rowLabel }} {{ item.row }}: {{ item.index_no }}
              </p>
            </div>
          </div>

          <div v-if="activeUploadErrorsSummary.duplicate_indexes.length" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4">
            <p class="font-semibold text-rose-900">{{ text.duplicateIndexes }}</p>
            <div class="mt-2 space-y-1 text-rose-900">
              <p v-for="item in sortedDuplicateIndexes" :key="`modal-duplicate-${item.row}-${item.index_no}`">
                {{ text.rowLabel }} {{ item.row }}: {{ item.index_no }}
              </p>
            </div>
          </div>

          <div v-if="activeUploadErrorsSummary.conflicting_assignments.length" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
            <p class="font-semibold text-red-900">{{ text.conflictingAssignments }}</p>
            <div class="mt-2 space-y-1 text-red-900">
              <p v-for="item in sortedConflictingAssignments" :key="`modal-conflict-${item.row}-${item.index_no}`">
                {{ text.rowLabel }} {{ item.row }}: {{ item.index_no }} - {{ item.current_grade }} {{ item.current_class }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="activeConfirmDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 p-4" @click="closeConfirmDialog">
      <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" @click.stop>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-100 text-rose-700">
              <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path d="M7 2.5h6a1 1 0 0 1 1 1V5h2.25a.75.75 0 0 1 0 1.5h-.56l-.6 8.18A2 2 0 0 1 13.1 16.5H6.9a2 2 0 0 1-1.99-1.82L4.31 6.5h-.56a.75.75 0 0 1 0-1.5H6V3.5a1 1 0 0 1 1-1Zm1 2.5h4V4h-4v1Zm-1.59 1.5.57 7.96a.5.5 0 0 0 .5.46h6.04a.5.5 0 0 0 .5-.46l.57-7.96H6.41Z" />
              </svg>
            </div>
            <div>
              <h3 class="font-display text-lg font-bold text-slate-900">{{ confirmDialogHeading }}</h3>
            </div>
          </div>
          <button
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50"
            :title="text.close"
            :aria-label="text.close"
            :disabled="confirmDialogBusy"
            @click="closeConfirmDialog"
          >
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
              <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
            </svg>
          </button>
        </div>

        <div class="px-5 py-4">
          <p class="text-sm leading-6 text-slate-600">{{ confirmDialogMessage }}</p>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
          <button
            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="confirmDialogBusy"
            @click="closeConfirmDialog"
          >
            {{ text.cancel }}
          </button>
          <button
            class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-rose-300"
            :disabled="confirmDialogBusy"
            @click="confirmDialogAction"
          >
            {{ confirmDialogBusy ? text.deleting : text.delete }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'
import { useUiStore } from '../stores/ui'

interface OptionRow { id: number; label: string }
interface GradeRow { grade_id: number; grade: string }
interface GradeResponse { year?: number | null; years?: number[]; data: GradeRow[] }
interface StudentRow { std_id: number; index_no: string; name_with_initials: string }
interface ClassBox { sch_grd_cls_id: number; class_id: number; class: string; student_count: number; students: StudentRow[] }
interface OverviewResponse { class_boxes: ClassBox[] }
interface ConfirmDialogState {
  kind: 'clearClass' | 'removeStudent'
  classBox: ClassBox
  student?: StudentRow
}
interface UploadSummary {
  cleared_count: number
  successful_count: number
  failed_count: number
  missing_indexes: Array<{ row: number; index_no: string }>
  duplicate_indexes: Array<{ row: number; index_no: string }>
  conflicting_assignments: Array<{ row: number; index_no: string; current_grade: string; current_class: string }>
  attempted_at: number
}
interface StudentOptionsResponse { schools: OptionRow[] }

const ui = useUiStore()
const currentUser = getUser()
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')
const isPrincipal = computed(() => (currentUser?.role_id ?? 0) === 2 || roleName === 'principal')
const rawPermissions = currentUser?.feature_permissions
const canAssign = computed(() => {
  if (!rawPermissions || typeof rawPermissions !== 'object') {
    const roleId = currentUser?.role_id ?? 0
    return [1, 2, 4, 5, 6].includes(roleId)
  }

  return Boolean(rawPermissions['student.update']) || Boolean(rawPermissions['student.create'])
})

const text = computed(() => ({
  studentsModule: ui.language === 'si' ? 'සිසුන් මොඩියුලය' : ui.language === 'ta' ? 'மாணவர்கள் தொகுதி' : 'Students Module',
  title: ui.language === 'si' ? 'පන්තිවල සිසුන්' : ui.language === 'ta' ? 'வகுப்புகளில் மாணவர்கள்' : 'Students in Classes',
  subtitle: ui.language === 'si'
    ? 'තෝරාගත් වර්ෂය සහ ශ්‍රේණියට අදාළ පන්තිවල සිසුන් මෙහි කළමනාකරණය කරන්න.'
    : ui.language === 'ta'
      ? 'தேர்ந்தெடுக்கப்பட்ட ஆண்டு மற்றும் தரத்திற்கான வகுப்புகளில் மாணவர்களை இங்கே நிர்வகிக்கவும்.'
      : 'Manage students in the classes for the selected year and grade.',
  classSelection: ui.language === 'si' ? 'පන්ති තේරීම' : ui.language === 'ta' ? 'வகுப்பு தேர்வு' : 'Class Selection',
  school: ui.language === 'si' ? 'පාසල' : ui.language === 'ta' ? 'பாடசாலை' : 'School',
  selectSchool: ui.language === 'si' ? 'පාසල තෝරන්න' : ui.language === 'ta' ? 'பாடசாலையைத் தேர்ந்தெடுக்கவும்' : 'Select school',
  selectedYear: ui.language === 'si' ? 'වර්ෂය' : ui.language === 'ta' ? 'ஆண்டு' : 'Year',
  selectedGrade: ui.language === 'si' ? 'ශ්‍රේණිය' : ui.language === 'ta' ? 'தரம்' : 'Grade',
  selectYear: ui.language === 'si' ? 'වර්ෂය තෝරන්න' : ui.language === 'ta' ? 'ஆண்டைத் தேர்ந்தெடுக்கவும்' : 'Select year',
  selectGrade: ui.language === 'si' ? 'ශ්‍රේණිය තෝරන්න' : ui.language === 'ta' ? 'தரத்தைத் தேர்ந்தெடுக்கவும்' : 'Select grade',
  classCount: ui.language === 'si' ? 'පංති ගණන' : ui.language === 'ta' ? 'வகுப்பு எண்ணிக்கை' : 'Class Count',
  studentsInClassesHeading: ui.language === 'si' ? 'පන්තිවල සිසුන්' : ui.language === 'ta' ? 'வகுப்புகளில் மாணவர்கள்' : 'Students in Classes',
  studentsInClassesHelp: ui.language === 'si'
    ? 'තෝරාගත් ශ්‍රේණියේ සියලුම පන්ති මෙහි පෙන්වයි. එක් එක් පන්තියට අදාළ සිසුන් ලැයිස්තු කළමනාකරණය කරන්න.'
    : ui.language === 'ta'
      ? 'தேர்ந்தெடுக்கப்பட்ட தரத்தின் அனைத்து வகுப்புகளும் இங்கே காட்டப்படும். ஒவ்வொரு வகுப்பிற்குமான மாணவர் பட்டியலை நிர்வகிக்கவும்.'
      : 'All classes for the selected grade are shown here. Manage the student list for each class.',
  totalStudents: ui.language === 'si' ? 'මුළු සිසුන්' : ui.language === 'ta' ? 'மொத்த மாணவர்கள்' : 'Total Students',
  studentsCount: ui.language === 'si' ? 'සිසුන්' : ui.language === 'ta' ? 'மாணவர்கள்' : 'Students',
  chooseFile: ui.language === 'si' ? 'ගොනුව තෝරන්න' : ui.language === 'ta' ? 'கோப்பை தேர்ந்தெடுக்கவும்' : 'Choose File',
  downloadClass: ui.language === 'si' ? 'පන්තියේ සිසුන් බාගන්න' : ui.language === 'ta' ? 'வகுப்பு மாணவர்களை பதிவிறக்கு' : 'Download Class Students',
  downloadingClass: ui.language === 'si' ? 'පන්ති ලැයිස්තුව බාගත කරමින්...' : ui.language === 'ta' ? 'வகுப்பு பட்டியல் பதிவிறக்கப்படுகிறது...' : 'Downloading Class List...',
  downloadTemplate: ui.language === 'si' ? 'Template බාගන්න' : ui.language === 'ta' ? 'Template பதிவிறக்கு' : 'Download Template',
  downloadingTemplate: ui.language === 'si' ? 'බාගත කරමින්...' : ui.language === 'ta' ? 'பதிவிறக்கப்படுகிறது...' : 'Downloading...',
  upload: ui.language === 'si' ? 'උඩුගත කරන්න' : ui.language === 'ta' ? 'பதிவேற்று' : 'Upload',
  uploading: ui.language === 'si' ? 'උඩුගත කරමින්...' : ui.language === 'ta' ? 'பதிவேற்றப்படுகிறது...' : 'Uploading...',
  clearClass: ui.language === 'si' ? 'පංතිය හිස් කරන්න' : ui.language === 'ta' ? 'வகுப்பை காலி செய்' : 'Clear Class',
  clearing: ui.language === 'si' ? 'හිස් කරමින්...' : ui.language === 'ta' ? 'காலி செய்கிறது...' : 'Clearing...',
  clearClassConfirm: ui.language === 'si'
    ? 'මෙම පන්තියේ සියලුම සිසුන් ඉවත් කිරීමට ඔබට විශ්වාසද?'
    : ui.language === 'ta'
      ? 'இந்த வகுப்பில் உள்ள அனைத்து மாணவர்களையும் நீக்க விரும்புகிறீர்களா?'
      : 'Are you sure you want to remove all students from this class?',
  lastUpload: ui.language === 'si' ? 'අවසන් උඩුගත කිරීම' : ui.language === 'ta' ? 'கடைசி பதிவேற்றம்' : 'Last Upload',
  validationFailed: ui.language === 'si' ? 'වලංගු කිරීම අසාර්ථක විය.' : ui.language === 'ta' ? 'சரிபார்ப்பு தோல்வியடைந்தது.' : 'Validation failed.',
  successful: ui.language === 'si' ? 'සාර්ථක' : ui.language === 'ta' ? 'வெற்றி' : 'Successful',
  failed: ui.language === 'si' ? 'අසාර්ථක' : ui.language === 'ta' ? 'தோல்வி' : 'Failed',
  cleared: ui.language === 'si' ? 'මකා දැමූ' : ui.language === 'ta' ? 'நீக்கப்பட்டது' : 'Cleared',
  missingStudents: ui.language === 'si' ? 'සොයාගත නොහැකි සිසුන්' : ui.language === 'ta' ? 'காணாத மாணவர்கள்' : 'Students Not Found',
  duplicateIndexes: ui.language === 'si' ? 'අනුපිටපත් ඇතුළත් අංක' : ui.language === 'ta' ? 'மறுநகல் அனுமதி இலக்கங்கள்' : 'Duplicate Index Numbers',
  conflictingAssignments: ui.language === 'si' ? 'වෙනත් පන්තිවල දැනට පවතින සිසුන්' : ui.language === 'ta' ? 'வேறு வகுப்புகளில் ஏற்கனவே உள்ள மாணவர்கள்' : 'Students Already In Other Classes',
  uploadErrorsTitle: ui.language === 'si' ? 'උඩුගත කිරීමේ දෝෂ' : ui.language === 'ta' ? 'பதிவேற்ற பிழைகள்' : 'Upload Errors',
  viewErrors: ui.language === 'si' ? 'දෝෂ බලන්න' : ui.language === 'ta' ? 'பிழைகளை காண்க' : 'View Errors',
  yearLabel: ui.language === 'si' ? 'වර්ෂය' : ui.language === 'ta' ? 'ஆண்டு' : 'Year',
  gradeLabel: ui.language === 'si' ? 'ශ්‍රේණිය' : ui.language === 'ta' ? 'தரம்' : 'Grade',
  rowLabel: ui.language === 'si' ? 'පේළිය' : ui.language === 'ta' ? 'வரி' : 'Row',
  indexNo: ui.language === 'si' ? 'ඇතුළත් අංකය' : ui.language === 'ta' ? 'அனுமதி இலக்கம்' : 'Index No',
  name: ui.language === 'si' ? 'නම' : ui.language === 'ta' ? 'பெயர்' : 'Name',
  emptyClass: ui.language === 'si' ? 'තවම සිසුන් නැත.' : ui.language === 'ta' ? 'இன்னும் மாணவர்கள் இல்லை.' : 'No students yet.',
  removeStudent: ui.language === 'si' ? 'සිසුවා ඉවත් කරන්න' : ui.language === 'ta' ? 'மாணவரை நீக்கு' : 'Remove Student',
  removingStudent: ui.language === 'si' ? 'ඉවත් කරමින්...' : ui.language === 'ta' ? 'நீக்கப்படுகிறது...' : 'Removing...',
  removeStudentConfirm: ui.language === 'si'
    ? 'මෙම සිසුවා මෙම පන්තියෙන් ඉවත් කිරීමට ඔබට විශ්වාසද?'
    : ui.language === 'ta'
      ? 'இந்த மாணவரை இந்த வகுப்பிலிருந்து நீக்க விரும்புகிறீர்களா?'
      : 'Are you sure you want to remove this student from this class?',
  removeStudentSuccess: ui.language === 'si' ? 'සිසුවා පන්තියෙන් ඉවත් කරන ලදී.' : ui.language === 'ta' ? 'மாணவர் வகுப்பிலிருந்து நீக்கப்பட்டார்.' : 'Student removed from class successfully.',
  delete: ui.language === 'si' ? 'මකන්න' : ui.language === 'ta' ? 'நீக்கு' : 'Delete',
  deleting: ui.language === 'si' ? 'මකමින්...' : ui.language === 'ta' ? 'நீக்குகிறது...' : 'Deleting...',
  cancel: ui.language === 'si' ? 'අවලංගු කරන්න' : ui.language === 'ta' ? 'ரத்து செய்' : 'Cancel',
  close: ui.language === 'si' ? 'වසන්න' : ui.language === 'ta' ? 'மூடு' : 'Close',
  clearClassDialogHeading: ui.language === 'si' ? 'පන්තිය හිස් කිරීම' : ui.language === 'ta' ? 'வகுப்பை காலி செய்தல்' : 'Clear Class',
  removeStudentDialogHeading: ui.language === 'si' ? 'සිසුවා ඉවත් කිරීම' : ui.language === 'ta' ? 'மாணவரை நீக்குதல்' : 'Remove Student',
  noPermission: ui.language === 'si' ? 'ඔබට මෙම පංති පැවරීම් කළමනාකරණය කිරීමට අවසර නැත.' : ui.language === 'ta' ? 'இந்த வகுப்பு ஒதுக்கீட்டை நிர்வகிக்க உங்களுக்கு அனுமதி இல்லை.' : 'You do not have permission to manage class assignments.',
  selectTargetFirst: ui.language === 'si' ? 'පළමුව නව වර්ෂය සහ ශ්‍රේණිය තෝරන්න.' : ui.language === 'ta' ? 'முதலில் புதிய ஆண்டு மற்றும் தரத்தைத் தேர்ந்தெடுக்கவும்.' : 'Select the target year and grade first.',
  saveSuccess: ui.language === 'si' ? 'පංති ලැයිස්තුව සාර්ථකව යාවත්කාලීන විය.' : ui.language === 'ta' ? 'வகுப்பு பட்டியல் வெற்றிகரமாக புதுப்பிக்கப்பட்டது.' : 'Class list updated successfully.',
  clearSuccess: ui.language === 'si' ? 'පංති ලැයිස්තුව හිස් කරන ලදී.' : ui.language === 'ta' ? 'வகுப்பு பட்டியல் காலி செய்யப்பட்டது.' : 'Class list cleared successfully.',
  unableToDownloadTemplate: ui.language === 'si' ? 'Template ගොනුව බාගත කළ නොහැකි විය.' : ui.language === 'ta' ? 'Template கோப்பை பதிவிறக்க முடியவில்லை.' : 'Unable to download template.',
}))

const schools = ref<OptionRow[]>([])
const academicYears = ref<number[]>([])
const gradesForYear = ref<GradeRow[]>([])
const classBoxes = ref<ClassBox[]>([])
const pageMessage = ref('')
const pageError = ref('')
const downloadingClassId = ref<number | null>(null)
const downloadingTemplate = ref(false)
const uploadingClassId = ref<number | null>(null)
const clearingClassId = ref<number | null>(null)
const removingStudentAssignmentKey = ref('')
const selectedFilesByClass = ref<Record<number, File | null>>({})
const uploadResultsByClass = ref<Record<number, UploadSummary>>({})
const fileInputsByClass = ref<Record<number, HTMLInputElement | null>>({})
const activeUploadErrorsClass = ref<ClassBox | null>(null)
const activeUploadErrorsSummary = ref<UploadSummary | null>(null)
const activeConfirmDialog = ref<ConfirmDialogState | null>(null)

const initialSchoolContextCensusId = getSchoolContextCensusId()
const adminSchoolContextCensusId = ref<number>(initialSchoolContextCensusId ?? 0)
const selectedYear = ref(0)
const selectedGradeId = ref(0)
const totalClassStudents = computed(() => classBoxes.value.reduce((sum, row) => sum + row.students.length, 0))
const selectedGradeLabel = computed(() => gradesForYear.value.find((row) => row.grade_id === selectedGradeId.value)?.grade ?? '-')
const uploadErrorsContextLabel = computed(() => {
  if (!activeUploadErrorsClass.value) {
    return ''
  }

  return `${selectedGradeLabel.value}${activeUploadErrorsClass.value.class} - ${selectedYear.value}`
})
const confirmDialogBusy = computed(() => {
  if (!activeConfirmDialog.value) {
    return false
  }

  if (activeConfirmDialog.value.kind === 'clearClass') {
    return clearingClassId.value === activeConfirmDialog.value.classBox.sch_grd_cls_id
  }

  const studentId = activeConfirmDialog.value.student?.std_id ?? 0
  return removingStudentAssignmentKey.value === `${activeConfirmDialog.value.classBox.sch_grd_cls_id}:${studentId}`
})
const confirmDialogHeading = computed(() => {
  if (!activeConfirmDialog.value) {
    return ''
  }

  return activeConfirmDialog.value.kind === 'clearClass'
    ? text.value.clearClassDialogHeading
    : text.value.removeStudentDialogHeading
})
const confirmDialogMessage = computed(() => {
  if (!activeConfirmDialog.value) {
    return ''
  }

  if (activeConfirmDialog.value.kind === 'clearClass') {
    return text.value.clearClassConfirm
  }

  return text.value.removeStudentConfirm
})

const buildSchoolHeaders = (): Record<string, string> | undefined => {
  if (!isAdmin.value) {
    return undefined
  }

  return adminSchoolContextCensusId.value > 0 ? { 'X-School-Census-Id': String(adminSchoolContextCensusId.value) } : undefined
}

const extractApiMessage = (error: any): string => {
  return error?.response?.data?.message ?? ''
}

const normalizeUploadSummary = (payload: any): UploadSummary => ({
  cleared_count: Number(payload?.cleared_count ?? 0),
  successful_count: Number(payload?.successful_count ?? 0),
  failed_count: Number(payload?.failed_count ?? 0),
  missing_indexes: Array.isArray(payload?.missing_indexes) ? payload.missing_indexes : [],
  duplicate_indexes: Array.isArray(payload?.duplicate_indexes) ? payload.duplicate_indexes : [],
  conflicting_assignments: Array.isArray(payload?.conflicting_assignments) ? payload.conflicting_assignments : [],
  attempted_at: Date.now(),
})

const compareIndexNumbers = (left: string, right: string): number => {
  const leftValue = Number(left)
  const rightValue = Number(right)

  if (Number.isFinite(leftValue) && Number.isFinite(rightValue) && leftValue !== rightValue) {
    return leftValue - rightValue
  }

  return left.localeCompare(right, undefined, { numeric: true, sensitivity: 'base' })
}

const sortedMissingIndexes = computed(() => {
  return [...(activeUploadErrorsSummary.value?.missing_indexes ?? [])]
    .sort((left, right) => compareIndexNumbers(String(left.index_no ?? ''), String(right.index_no ?? '')))
})

const sortedDuplicateIndexes = computed(() => {
  return [...(activeUploadErrorsSummary.value?.duplicate_indexes ?? [])]
    .sort((left, right) => compareIndexNumbers(String(left.index_no ?? ''), String(right.index_no ?? '')))
})

const sortedConflictingAssignments = computed(() => {
  return [...(activeUploadErrorsSummary.value?.conflicting_assignments ?? [])]
    .sort((left, right) => compareIndexNumbers(String(left.index_no ?? ''), String(right.index_no ?? '')))
})

const hasUploadErrors = (summary: UploadSummary | undefined): boolean => {
  if (!summary) {
    return false
  }

  return summary.missing_indexes.length > 0 || summary.duplicate_indexes.length > 0 || summary.conflicting_assignments.length > 0
}

const openUploadErrorsModal = (classBox: ClassBox): void => {
  const summary = uploadResultsByClass.value[classBox.sch_grd_cls_id]
  if (!summary || !hasUploadErrors(summary)) {
    return
  }

  activeUploadErrorsClass.value = classBox
  activeUploadErrorsSummary.value = summary
}

const closeUploadErrorsModal = (): void => {
  activeUploadErrorsClass.value = null
  activeUploadErrorsSummary.value = null
}

const openClearClassDialog = (classBox: ClassBox): void => {
  activeConfirmDialog.value = {
    kind: 'clearClass',
    classBox,
  }
}

const openRemoveStudentDialog = (classBox: ClassBox, student: StudentRow): void => {
  activeConfirmDialog.value = {
    kind: 'removeStudent',
    classBox,
    student,
  }
}

const closeConfirmDialog = (): void => {
  if (confirmDialogBusy.value) {
    return
  }

  activeConfirmDialog.value = null
}

const setFileInputRef = (classId: number, element: Element | null): void => {
  fileInputsByClass.value[classId] = element instanceof HTMLInputElement ? element : null
}

const openFileDialog = (classId: number): void => {
  fileInputsByClass.value[classId]?.click()
}

const onFileSelected = (classId: number, event: Event): void => {
  const target = event.target as HTMLInputElement | null
  selectedFilesByClass.value[classId] = target?.files?.[0] ?? null
  pageMessage.value = ''
  pageError.value = ''
}

const loadOptions = async (): Promise<void> => {
  try {
    const { data } = await api.get<StudentOptionsResponse>('/students/options')
    schools.value = Array.isArray(data.schools) ? data.schools : []
  } catch {
    schools.value = []
  }
}

const loadAcademicYears = async (): Promise<void> => {
  if (isAdmin.value && adminSchoolContextCensusId.value <= 0) {
    academicYears.value = []
    return
  }

  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.get<GradeResponse>('/grades', headers ? { headers } : undefined)
    academicYears.value = Array.isArray(data.years)
      ? data.years.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value >= 2000 && value <= 2100)
      : []
  } catch {
    academicYears.value = []
  }
}

const loadGradesForYear = async (year: number): Promise<GradeRow[]> => {
  if (year <= 0) {
    return []
  }

  try {
    const headers = buildSchoolHeaders()
    const config: { headers?: Record<string, string>; params: { year: number } } = { params: { year } }
    if (headers) config.headers = headers
    const { data } = await api.get<GradeResponse>('/grades', config)
    return Array.isArray(data.data) ? data.data : []
  } catch {
    return []
  }
}

const loadOverview = async (): Promise<void> => {
  if (!canAssign.value) {
    pageError.value = text.value.noPermission
    return
  }

  if (selectedYear.value <= 0 || selectedGradeId.value <= 0) {
    classBoxes.value = []
    return
  }

  pageError.value = ''

  try {
    const headers = buildSchoolHeaders()
    const config: { headers?: Record<string, string>; params: Record<string, number> } = {
      params: {
        year: selectedYear.value,
        grade_id: selectedGradeId.value,
      },
    }
    if (headers) config.headers = headers

    const { data } = await api.get<OverviewResponse>('/students/in-classes/overview', config)
    classBoxes.value = Array.isArray(data.class_boxes) ? data.class_boxes : []
  } catch (error: any) {
    classBoxes.value = []
    pageError.value = extractApiMessage(error)
  }
}

const downloadTemplate = async (): Promise<void> => {
  pageError.value = ''
  downloadingTemplate.value = true

  try {
    const response = await api.get('/students/in-classes/template', {
      responseType: 'blob',
    })

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'students-class-template.xlsx'
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error: any) {
    pageError.value = extractApiMessage(error) || text.value.unableToDownloadTemplate
  } finally {
    downloadingTemplate.value = false
  }
}

const downloadClassStudents = async (classBox: ClassBox): Promise<void> => {
  pageError.value = ''
  downloadingClassId.value = classBox.sch_grd_cls_id

  try {
    const headers = buildSchoolHeaders()
    const response = await api.get('/students/in-classes/download', {
      headers,
      params: {
        year: selectedYear.value,
        grade_id: selectedGradeId.value,
        class_id: classBox.class_id,
      },
      responseType: 'blob',
    })

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    const className = String(classBox.class || 'Class').replace(/[^a-z0-9]+/gi, '')
    link.href = url
    link.download = `Grade_${selectedGradeId.value}${className}_${selectedYear.value}.xlsx`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error: any) {
    pageError.value = extractApiMessage(error) || text.value.unableToDownloadTemplate
  } finally {
    downloadingClassId.value = null
  }
}

const uploadClassFile = async (classBox: ClassBox): Promise<void> => {
  if (!selectedFilesByClass.value[classBox.sch_grd_cls_id]) {
    return
  }

  uploadingClassId.value = classBox.sch_grd_cls_id
  pageMessage.value = ''
  pageError.value = ''

  try {
    const formData = new FormData()
    formData.append('year', String(selectedYear.value))
    formData.append('grade_id', String(selectedGradeId.value))
    formData.append('class_id', String(classBox.class_id))
    formData.append('file', selectedFilesByClass.value[classBox.sch_grd_cls_id] as File)

    const headers = buildSchoolHeaders()
    const config: { headers?: Record<string, string> } = {}
    if (headers) config.headers = headers

    const { data } = await api.post('/students/in-classes/upload', formData, config)
    uploadResultsByClass.value[classBox.sch_grd_cls_id] = normalizeUploadSummary(data?.data)

    pageMessage.value = `${classBox.class}: ${text.value.saveSuccess} ${text.value.successful}: ${uploadResultsByClass.value[classBox.sch_grd_cls_id].successful_count}, ${text.value.failed}: ${uploadResultsByClass.value[classBox.sch_grd_cls_id].failed_count}.`
    selectedFilesByClass.value[classBox.sch_grd_cls_id] = null
    if (fileInputsByClass.value[classBox.sch_grd_cls_id]) {
      fileInputsByClass.value[classBox.sch_grd_cls_id]!.value = ''
    }
    await loadOverview()
  } catch (error: any) {
    if (error?.response?.status === 422 && error?.response?.data?.data) {
      uploadResultsByClass.value[classBox.sch_grd_cls_id] = normalizeUploadSummary(error.response.data.data)
      pageError.value = `${classBox.class}: ${extractApiMessage(error) || text.value.validationFailed}`
      openUploadErrorsModal(classBox)
    } else {
      pageError.value = extractApiMessage(error)
    }
  } finally {
    uploadingClassId.value = null
  }
}

const clearClassList = async (classBox: ClassBox): Promise<void> => {
  clearingClassId.value = classBox.sch_grd_cls_id
  pageMessage.value = ''
  pageError.value = ''

  try {
    const headers = buildSchoolHeaders()
    const config: { headers?: Record<string, string> } = {}
    if (headers) config.headers = headers

    await api.post('/students/in-classes/clear', {
      year: selectedYear.value,
      grade_id: selectedGradeId.value,
      class_id: classBox.class_id,
    }, config)

    pageMessage.value = text.value.clearSuccess
    await loadOverview()
    activeConfirmDialog.value = null
  } catch (error: any) {
    pageError.value = extractApiMessage(error)
  } finally {
    clearingClassId.value = null
  }
}

const removeStudentFromClass = async (classBox: ClassBox, student: StudentRow): Promise<void> => {
  if (!isPrincipal.value) {
    return
  }

  const assignmentKey = `${classBox.sch_grd_cls_id}:${student.std_id}`
  removingStudentAssignmentKey.value = assignmentKey
  pageMessage.value = ''
  pageError.value = ''

  try {
    const headers = buildSchoolHeaders()
    const config: { headers?: Record<string, string> } = {}
    if (headers) config.headers = headers

    await api.post('/students/in-classes/remove-student', {
      year: selectedYear.value,
      grade_id: selectedGradeId.value,
      class_id: classBox.class_id,
      student_id: student.std_id,
    }, config)

    pageMessage.value = `${classBox.class}: ${student.index_no} - ${text.value.removeStudentSuccess}`
    await loadOverview()
    activeConfirmDialog.value = null
  } catch (error: any) {
    pageError.value = extractApiMessage(error)
  } finally {
    removingStudentAssignmentKey.value = ''
  }
}

const confirmDialogAction = async (): Promise<void> => {
  if (!activeConfirmDialog.value) {
    return
  }

  if (activeConfirmDialog.value.kind === 'clearClass') {
    await clearClassList(activeConfirmDialog.value.classBox)
    return
  }

  if (activeConfirmDialog.value.student) {
    await removeStudentFromClass(activeConfirmDialog.value.classBox, activeConfirmDialog.value.student)
  }
}

const onSchoolChange = async (): Promise<void> => {
  if (!isAdmin.value) {
    return
  }

  const censusId = Number(adminSchoolContextCensusId.value)
  setSchoolContextCensusId(censusId > 0 ? censusId : null)
  selectedYear.value = 0
  selectedGradeId.value = 0
  gradesForYear.value = []
  classBoxes.value = []
  await loadAcademicYears()
}

watch(() => selectedYear.value, async (year) => {
  selectedGradeId.value = 0
  classBoxes.value = []
  gradesForYear.value = year > 0 ? await loadGradesForYear(year) : []
})

watch(() => selectedGradeId.value, async () => {
  await loadOverview()
})

onMounted(async () => {
  await loadOptions()
  await loadAcademicYears()
})
</script>
