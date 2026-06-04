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

            <button v-if="canDownloadTemplate" class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="downloadingTemplate || loadingOptions" @click="downloadImportTemplate">
              <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M5 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7.41A2 2 0 0 0 16.41 6L13 2.59A2 2 0 0 0 11.59 2H5Zm6 1.5V7a1 1 0 0 0 1 1h3.5V16a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h6Z" />
                <path d="M8 10a1 1 0 0 1 1 1v1h2v-1a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1Z" />
              </svg>
              {{ downloadingTemplate ? text.downloadingTemplate : text.template }}
            </button>

            <template v-if="canManageMarksUi">
              <input ref="marksFileInputRef" type="file" accept=".xlsx,.xls" class="hidden" @change="onMarksFileSelected" />
              <button class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="handleOpenMarksFilePicker">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path d="M10 3a1 1 0 0 1 1 1v6.59l1.3-1.29a1 1 0 1 1 1.4 1.41l-3 3a1 1 0 0 1-1.4 0l-3-3a1 1 0 1 1 1.4-1.41L9 10.59V4a1 1 0 0 1 1-1Z" />
                  <path d="M4 13a1 1 0 0 1 1 1v1h10v-1a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1Z" />
                </svg>
                {{ text.file }}
              </button>

              <span class="my-auto min-w-0 shrink text-sm text-slate-600">{{ selectedMarksFileName || text.noFileSelected }}</span>

              <button class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="uploadingMarks || !selectedMarksFile" @click="handleUploadMarksFile">
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
      <p v-if="canManageLoaded" class="mb-4 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-800">
        {{ entryRuleHint }}
      </p>

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
              <td
                v-for="subject in subjectRows"
                :key="`marks-cell-${row.index_no}-${subject.subject_id}`"
                class="px-3 py-2 text-center"
              >
                <input
                  v-if="canEditLoaded"
                  v-model="row.marks[String(subject.subject_id)]"
                  type="text"
                  maxlength="3"
                  :title="getCellErrorMessage(row.index_no, subject.subject_id)"
                  :class="[
                    'w-20 rounded-lg border px-2 py-1 text-center text-sm outline-none focus:ring-2',
                    getCellErrorMessage(row.index_no, subject.subject_id)
                      ? 'border-red-400 bg-red-50 text-red-900 ring-red-300 focus:border-red-500 focus:ring-red-200'
                      : 'border-slate-300 ring-cyan-500 focus:ring-cyan-200',
                  ]"
                  @input="clearFieldError(row.index_no, subject.subject_id); recalculateRow(row)"
                />
                <span v-else class="font-semibold text-slate-700">{{ row.marks[String(subject.subject_id)] || '-' }}</span>
              </td>
              <td class="px-3 py-2 text-center text-slate-700">{{ formatNumeric(row.total) }}</td>
              <td class="px-3 py-2 text-center text-slate-700">{{ formatAverage(row.average) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="canManageLoaded || canConfirmLoaded" class="mt-4 flex flex-wrap gap-2">
        <button v-if="canManageLoaded" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="savingDraft || subjectRows.length === 0 || markRows.length === 0" @click="handleSaveDraft">
          {{ savingDraft ? text.savingDraft : text.draft }}
        </button>
        <button v-if="canManageLoaded" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="savingMarks || subjectRows.length === 0 || markRows.length === 0" @click="handleSaveMarks">
          {{ savingMarks ? text.saving : text.save }}
        </button>
        <button v-if="canManageLoaded" class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60" :disabled="deletingMarks || subjectRows.length === 0 || markRows.length === 0" @click="handleDeleteMarks">
          {{ deletingMarks ? text.deleting : text.delete }}
        </button>
        <button
          v-if="canConfirmLoaded && confirmation.is_completed"
          class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="updatingConfirmation"
          @click="updateConfirmation(false)"
        >
          {{ updatingConfirmation ? text.reopening : text.reopen }}
        </button>
      </div>
    </section>

    <ConfirmDialog
      :open="showDeleteDialog"
      :title="text.deleteDialogTitle"
      :message="deleteDialogMessage"
      :confirm-label="text.confirmDelete"
      :busy-confirm-label="text.deleting"
      :cancel-label="text.cancel"
      :close-label="text.cancel"
      :busy="deletingMarks"
      @close="closeDeleteDialog"
      @confirm="confirmDeleteMarks"
    />

    <div v-if="showLockedDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 p-4 print:hidden" @click="closeLockedDialog">
      <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" @click.stop>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <h3 class="font-display text-lg font-bold text-slate-900">{{ text.lockedDialogTitle }}</h3>
          <button class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50" :title="text.ok" :aria-label="text.ok" @click="closeLockedDialog">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
              <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
            </svg>
          </button>
        </div>
        <div class="px-5 py-4">
          <p class="text-sm leading-6 text-slate-600">{{ lockedDialogMessage }}</p>
        </div>
        <div class="flex items-center justify-end border-t border-slate-200 bg-slate-50 px-5 py-4">
          <button class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700" @click="closeLockedDialog">
            {{ text.ok }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'
import { useUiStore } from '../stores/ui'

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
  sub_cat_id?: number
}

interface ErrorStateOptions {
  summaryMessage?: string
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
  can_confirm?: boolean
  confirmation?: {
    is_completed?: boolean
  }
}

const currentUser = getUser()
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')
const ui = useUiStore()

const text = computed(() => {
  if (ui.language === 'si') {
    return {
      title: 'වාර පරීක්ෂණ ලකුණු',
      subtitle: 'පන්ති භාර ගුරුවරුන්ට ලකුණු ඇතුළත් කළ හැක. විදුහල්පති, ලිපිකරු, ශ්‍රේණි ප්‍රධාන, අංශ ප්‍රධාන සහ ශිෂ්‍යයන්ට තමන්ට අවසර ලත් පරාසය තුළ සෙවිය හැක.',
      school: 'පාසල',
      selectSchool: 'පාසල තෝරන්න',
      year: 'වර්ෂය',
      selectYear: 'වර්ෂය තෝරන්න',
      term: 'වාරය',
      selectTerm: 'වාරය තෝරන්න',
      grade: 'ශ්‍රේණිය',
      selectGrade: 'ශ්‍රේණිය තෝරන්න',
      class: 'පන්තිය',
      selectClass: 'පන්තිය තෝරන්න',
      show: 'පෙන්වන්න',
      loading: 'පූරණය වෙමින්...',
      marksSheet: 'ලකුණු පත්‍රය',
      draft: 'කටුපත්',
      savingDraft: 'කටුපත් සුරකිමින්...',
      save: 'සුරකින්න',
      saving: 'සුරකිමින්...',
      exportExcel: 'Excel ලෙස පිටත් කරන්න',
      exporting: 'පිටත් කරමින්...',
      importTemplate: 'ආයාත ආකෘතිය',
      downloadingTemplate: 'බාගත වෙමින්...',
      chooseFile: 'ගොනුව තෝරන්න',
      noFileSelected: 'ගොනුවක් තෝරා නැත.',
      uploadExcel: 'Excel උඩුගත කරන්න',
      uploading: 'උඩුගත කරමින්...',
      export: 'පිටත් කරන්න',
      template: 'ආකෘතිය',
      file: 'ගොනුව',
      upload: 'උඩුගත කරන්න',
      delete: 'ලකුණු මකන්න',
      confirmDelete: 'මකන්න',
      deleting: 'මකමින්...',
      cancel: 'අවලංගු',
      search: 'සොයන්න',
      totalStudents: 'ශිෂ්‍යයන්',
      totalSubjects: 'විෂයයන්',
      student: 'ශිෂ්‍යයා',
      total: 'එකතුව',
      average: 'සාමාන්‍යය',
      noRows: 'තෝරාගත් පෙරහන් සඳහා ලකුණු හමු නොවීය.',
      enterHint: 'අනुपस्थित නම් 0-100 හෝ AB ඇතුළත් කරන්න.',
      entryRuleHint: "ශිෂ්‍යයා නොපැමිණියේ නම් 'AB' භාවිතා කරන්න.",
      optionalEntryRuleHint: "ශිෂ්‍යයා නොපැමිණියේ නම් 'AB' භාවිතා කරන්න. එම විෂයය ශිෂ්‍යයා තෝරා නොගත් විකල්ප විෂයයක් නම් පමණක් කොටුව හිස්ව තබන්න.",
      completed: 'සම්පූර්ණයි',
      inProgress: 'ක්‍රියාත්මකයි',
      reopen: 'නැවත විවෘත කරන්න',
      reopening: 'නැවත විවෘත කරමින්...',
      selectSchoolFirst: 'පළමුව පාසලක් තෝරන්න.',
      selectFiltersFirst: 'පළමුව වර්ෂය, වාරය, ශ්‍රේණිය සහ පන්තිය තෝරන්න.',
      loadOptionsError: 'ලකුණු විකල්ප පූරණය කළ නොහැකි විය.',
      loadMarksError: 'වාර පරීක්ෂණ ලකුණු පූරණය කළ නොහැකි විය.',
      saveMarksError: 'වාර පරීක්ෂණ ලකුණු අවසන් කිරීමට නොහැකි විය.',
      saveMarksValidationSummary: 'ලකුණු පත්‍රය සුරැකීමට පෙර රතු පැහැයෙන් සලකුණු කළ කොටු පරීක්ෂා කරන්න.',
      saveDraftError: 'වාර පරීක්ෂණ ලකුණු කටුපත් සුරැකීමට නොහැකි විය.',
      deleteMarksError: 'වාර පරීක්ෂණ ලකුණු මැකීමට නොහැකි විය.',
      exportMarksError: 'වාර පරීක්ෂණ ලකුණු පිටත් කිරීමට නොහැකි විය.',
      templateMarksError: 'ලකුණු ආයාත ආකෘතිය බාගත කිරීමට නොහැකි විය.',
      uploadMarksError: 'වාර පරීක්ෂණ ලකුණු කටුපත් උඩුගත කිරීමට නොහැකි විය.',
      lockedDialogTitle: 'ලකුණු පත්‍රය අගුලු දමා ඇත',
      lockedEditMessage: 'මෙම ලකුණු පත්‍රය දැනටමත් සම්පූර්ණ කර ඇත. සංස්කරණයට පෙර නැවත විවෘත කරන්න.',
      lockedDeleteMessage: 'මෙම ලකුණු පත්‍රය දැනටමත් සම්පූර්ණ කර ඇත. මකා දැමීමට පෙර නැවත විවෘත කරන්න.',
      lockedUploadMessage: 'මෙම ලකුණු පත්‍රය දැනටමත් සම්පූර්ණ කර ඇත. වෙනස්කම් උඩුගත කිරීමට පෙර නැවත විවෘත කරන්න.',
      ok: 'හරි',
      deleteDialogTitle: 'ලකුණු මකන්න',
      deleteDialogMessage: 'තෝරාගත් වර්ෂය, වාරය, ශ්‍රේණිය සහ පන්තිය සඳහා සියලු ලකුණු මකා දැමීමට ඔබට අවශ්‍යද?',
    }
  }

  if (ui.language === 'ta') {
    return {
      title: 'காலாண்டு தேர்வு மதிப்பெண்கள்',
      subtitle: 'வகுப்பு ஆசிரியர்கள் மதிப்பெண்களை உள்ளிடலாம். அதிபர், எழுத்தர், தரத் தலைவர், பிரிவு தலைவர் மற்றும் மாணவர்கள் தங்களுக்கு அனுமதிக்கப்பட்ட வரம்பில் தேடலாம்.',
      school: 'பாடசாலை',
      selectSchool: 'பாடசாலையைத் தேர்ந்தெடுக்கவும்',
      year: 'ஆண்டு',
      selectYear: 'ஆண்டைத் தேர்ந்தெடுக்கவும்',
      term: 'தவணை',
      selectTerm: 'தவணையைத் தேர்ந்தெடுக்கவும்',
      grade: 'தரம்',
      selectGrade: 'தரத்தைத் தேர்ந்தெடுக்கவும்',
      class: 'வகுப்பு',
      selectClass: 'வகுப்பைத் தேர்ந்தெடுக்கவும்',
      show: 'காட்டு',
      loading: 'ஏற்றப்படுகிறது...',
      marksSheet: 'மதிப்பெண் தாள்',
      draft: 'வரைவு',
      savingDraft: 'வரைவு சேமிக்கப்படுகிறது...',
      save: 'சேமிக்கவும்',
      saving: 'சேமிக்கப்படுகிறது...',
      exportExcel: 'Excel ஏற்றுமதி',
      exporting: 'ஏற்றுமதி செய்கிறது...',
      importTemplate: 'இறக்குமதி வடிவம்',
      downloadingTemplate: 'பதிவிறக்கப்படுகிறது...',
      chooseFile: 'கோப்பைத் தேர்ந்தெடுக்கவும்',
      noFileSelected: 'கோப்பு தேர்ந்தெடுக்கப்படவில்லை.',
      uploadExcel: 'Excel பதிவேற்று',
      uploading: 'பதிவேற்றப்படுகிறது...',
      export: 'ஏற்றுமதி',
      template: 'வடிவம்',
      file: 'கோப்பு',
      upload: 'பதிவேற்று',
      delete: 'மதிப்பெண்களை நீக்கு',
      confirmDelete: 'நீக்கு',
      deleting: 'நீக்கப்படுகிறது...',
      cancel: 'ரத்து செய்',
      search: 'தேடு',
      totalStudents: 'மாணவர்கள்',
      totalSubjects: 'பாடங்கள்',
      student: 'மாணவர்',
      total: 'மொத்தம்',
      average: 'சராசரி',
      noRows: 'தேர்ந்தெடுக்கப்பட்ட வடிகட்டல்களுக்கு மதிப்பெண்கள் கிடைக்கவில்லை.',
      enterHint: 'இல்லாதிருந்தால் 0-100 அல்லது AB உள்ளிடவும்.',
      entryRuleHint: "மாணவர் வராதிருந்தால் 'AB' பயன்படுத்தவும்.",
      optionalEntryRuleHint: "மாணவர் வராதிருந்தால் 'AB' பயன்படுத்தவும். அந்தப் பாடம் மாணவர் தேர்வு செய்யாத விருப்பப் பாடமாக இருந்தால் மட்டும் செல்லை காலியாக விடவும்.",
      completed: 'முடிந்தது',
      inProgress: 'நடந்து கொண்டிருக்கிறது',
      reopen: 'மீண்டும் திறக்கவும்',
      reopening: 'மீண்டும் திறக்கப்படுகிறது...',
      selectSchoolFirst: 'முதலில் ஒரு பாடசாலையைத் தேர்ந்தெடுக்கவும்.',
      selectFiltersFirst: 'முதலில் ஆண்டு, தவணை, தரம் மற்றும் வகுப்பைத் தேர்ந்தெடுக்கவும்.',
      loadOptionsError: 'மதிப்பெண் விருப்பங்களை ஏற்ற முடியவில்லை.',
      loadMarksError: 'காலாண்டு தேர்வு மதிப்பெண்களை ஏற்ற முடியவில்லை.',
      saveMarksError: 'காலாண்டு தேர்வு மதிப்பெண்களை இறுதிப்படுத்த முடியவில்லை.',
      saveMarksValidationSummary: 'சேமிப்பதற்கு முன் சிவப்பாக குறிக்கப்பட்ட செல்லுகளைச் சரிபார்க்கவும்.',
      saveDraftError: 'காலாண்டு தேர்வு மதிப்பெண் வரைவைக் சேமிக்க முடியவில்லை.',
      deleteMarksError: 'காலாண்டு தேர்வு மதிப்பெண்களை நீக்க முடியவில்லை.',
      exportMarksError: 'காலாண்டு தேர்வு மதிப்பெண்களை ஏற்றுமதி செய்ய முடியவில்லை.',
      templateMarksError: 'மதிப்பெண் இறக்குமதி வடிவத்தைப் பதிவிறக்க முடியவில்லை.',
      uploadMarksError: 'காலாண்டு தேர்வு மதிப்பெண் வரைவைக் பதிவேற்ற முடியவில்லை.',
      lockedDialogTitle: 'மதிப்பெண் தாள் பூட்டப்பட்டுள்ளது',
      lockedEditMessage: 'இந்த மதிப்பெண் தாள் ஏற்கனவே முடிக்கப்பட்டுள்ளது. திருத்துவதற்கு முன் மீண்டும் திறக்கவும்.',
      lockedDeleteMessage: 'இந்த மதிப்பெண் தாள் ஏற்கனவே முடிக்கப்பட்டுள்ளது. நீக்குவதற்கு முன் மீண்டும் திறக்கவும்.',
      lockedUploadMessage: 'இந்த மதிப்பெண் தாள் ஏற்கனவே முடிக்கப்பட்டுள்ளது. மாற்றங்களைப் பதிவேற்றுவதற்கு முன் மீண்டும் திறக்கவும்.',
      ok: 'சரி',
      deleteDialogTitle: 'மதிப்பெண்களை நீக்கு',
      deleteDialogMessage: 'தேர்ந்தெடுக்கப்பட்ட ஆண்டு, தவணை, தரம் மற்றும் வகுப்பிற்கான அனைத்து மதிப்பெண்களையும் நீக்க விரும்புகிறீர்களா?',
    }
  }

  return {
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
    draft: 'Draft',
    savingDraft: 'Saving Draft...',
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
    entryRuleHint: "Use 'AB' when a student was absent.",
    optionalEntryRuleHint: "Use 'AB' when a student was absent. Leave a cell blank only if that subject is an unselected optional subject for that student.",
    completed: 'Completed',
    inProgress: 'In Progress',
    reopen: 'Reopen',
    reopening: 'Reopening...',
    selectSchoolFirst: 'Select a school first.',
    selectFiltersFirst: 'Select year, term, grade, and class first.',
    loadOptionsError: 'Unable to load marks options.',
    loadMarksError: 'Unable to load term test marks.',
    saveMarksError: 'Unable to finalize term test marks.',
    saveMarksValidationSummary: 'Please check the highlighted cells before saving the marks sheet.',
    saveDraftError: 'Unable to save draft term test marks.',
    deleteMarksError: 'Unable to delete term test marks.',
    exportMarksError: 'Unable to export term test marks.',
    templateMarksError: 'Unable to download marks import template.',
    uploadMarksError: 'Unable to upload term test marks draft.',
    lockedDialogTitle: 'Marks Sheet Locked',
    lockedEditMessage: 'This marks sheet has already been completed. Reopen it before editing.',
    lockedDeleteMessage: 'This marks sheet has already been completed. Reopen it before deleting.',
    lockedUploadMessage: 'This marks sheet has already been completed. Reopen it before uploading changes.',
    ok: 'OK',
    deleteDialogTitle: 'Delete Marks',
    deleteDialogMessage: 'Do you want to delete all marks for the selected year, term, grade, and class?',
  }
})

const schools = ref<OptionRow[]>([])
const years = ref<number[]>([])
const terms = ref<TermOption[]>([])
const grades = ref<GradeOption[]>([])
const classes = ref<ClassOption[]>([])
const subjectRows = ref<SubjectRow[]>([])
const markRows = ref<MarkRow[]>([])
const cellErrors = ref<Record<string, string>>({})
const rowErrors = ref<Record<string, string>>({})
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
const savingDraft = ref(false)
const savingMarks = ref(false)
const exportingMarks = ref(false)
const downloadingTemplate = ref(false)
const uploadingMarks = ref(false)
const deletingMarks = ref(false)
const updatingConfirmation = ref(false)
const showDeleteDialog = ref(false)
const showLockedDialog = ref(false)
const canManageLoaded = ref(false)
const canConfirmLoaded = ref(false)
const marksFileInputRef = ref<HTMLInputElement | null>(null)
const selectedMarksFile = ref<File | null>(null)
const searchQuery = ref('')
const loadedTitle = ref('')
const message = ref('')
const errorMessage = ref('')
const messageRef = ref<HTMLElement | null>(null)
const errorMessageRef = ref<HTMLElement | null>(null)
const lockedDialogMessage = ref('')
const confirmation = ref({ is_completed: false })
const canManageMarksUi = computed(() => ['class teacher', 'class_teacher', 'classteacher'].includes(roleName))
const isPrincipal = computed(() => (currentUser?.role_id ?? 0) === 2 || roleName === 'principal')
const canDownloadTemplate = computed(() => isAdmin.value || canManageMarksUi.value || isPrincipal.value)
const canEditLoaded = computed(() => canManageLoaded.value && !confirmation.value.is_completed)
const selectedMarksFileName = computed(() => selectedMarksFile.value?.name ?? '')
const entryRuleHint = computed(() => (
  selectedGradeId.value >= 6
    ? text.value.optionalEntryRuleHint
    : text.value.entryRuleHint
))
const deleteDialogMessage = computed(() => {
  const termLabel = terms.value.find((row) => row.id === selectedTerm.value)?.label ?? `Term ${selectedTerm.value || '-'}`
  const gradeLabel = grades.value.find((row) => row.grade_id === selectedGradeId.value)?.label ?? `Grade ${selectedGradeId.value || '-'}`
  const classLabel = classes.value.find((row) => row.class_id === selectedClassId.value)?.label ?? `Class ${selectedClassId.value || '-'}`

  if (ui.language === 'si') {
    return `${selectedYear.value || '-'} , ${termLabel}, ${gradeLabel}, ${classLabel} සඳහා සියලු ලකුණු මකා දැමීමට ඔබට අවශ්‍යද?`
  }

  if (ui.language === 'ta') {
    return `${selectedYear.value || '-'}, ${termLabel}, ${gradeLabel}, ${classLabel} ஆகியவற்றிற்கான அனைத்து மதிப்பெண்களையும் நீக்க விரும்புகிறீர்களா?`
  }

  return `Do you want to delete all marks for ${selectedYear.value || '-'}, ${termLabel}, ${gradeLabel}, and ${classLabel}?`
})

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

const RELIGION_SUBJECT_IDS = [5, 6, 7, 8, 9]
const REQUIRED_MAIN_SUBJECT_IDS = [10, 12, 13, 14, 15]

const getCellErrorKey = (indexNo: string, subjectId: number): string => `${indexNo}::${subjectId}`

const clearValidationHighlights = (): void => {
  cellErrors.value = {}
  rowErrors.value = {}
}

const clearErrorState = (): void => {
  errorMessage.value = ''
}

const setCellError = (indexNo: string, subjectId: number, messageText: string): void => {
  cellErrors.value[getCellErrorKey(indexNo, subjectId)] = messageText
}

const setRowError = (indexNo: string, messageText: string): void => {
  rowErrors.value[indexNo] = messageText
}

const getSubjectIdsByCategory = (categoryId: number): number[] => (
  subjectRows.value
    .filter((subject) => Number(subject.sub_cat_id ?? 0) === categoryId)
    .map((subject) => subject.subject_id)
)

const highlightSubjectIdsForStudent = (indexNo: string, subjectIds: number[], messageText: string): void => {
  const row = markRows.value.find((entry) => entry.index_no === indexNo)
  if (!row) {
    return
  }

  let didHighlight = false
  subjectIds.forEach((subjectId) => {
    if (!subjectRows.value.some((subject) => subject.subject_id === subjectId)) {
      return
    }

    setCellError(indexNo, subjectId, messageText)
    didHighlight = true
  })

  if (!didHighlight) {
    setRowError(indexNo, messageText)
  }
}

const highlightFilledSubjectsForStudent = (indexNo: string, subjectIds: number[], messageText: string): void => {
  const row = markRows.value.find((entry) => entry.index_no === indexNo)
  if (!row) {
    return
  }

  const filledSubjectIds = subjectIds.filter((subjectId) => String(row.marks[String(subjectId)] ?? '').trim() !== '')
  if (filledSubjectIds.length === 0) {
    setRowError(indexNo, messageText)
    return
  }

  filledSubjectIds.forEach((subjectId) => setCellError(indexNo, subjectId, messageText))
}

const highlightEmptySubjectsForStudent = (indexNo: string, subjectIds: number[], messageText: string): void => {
  const row = markRows.value.find((entry) => entry.index_no === indexNo)
  if (!row) {
    return
  }

  const emptySubjectIds = subjectIds.filter((subjectId) => String(row.marks[String(subjectId)] ?? '').trim() === '')
  if (emptySubjectIds.length === 0) {
    setRowError(indexNo, messageText)
    return
  }

  emptySubjectIds.forEach((subjectId) => setCellError(indexNo, subjectId, messageText))
}

const applyValidationHighlights = (messages: string[]): void => {
  clearValidationHighlights()

  messages.forEach((messageText) => {
    const trimmedMessage = String(messageText ?? '').trim()
    if (trimmedMessage === '') {
      return
    }

    const missingCellMatch = /^Student\s+(.+?)\s+must have a mark or AB for\s+(.+?)\.$/u.exec(trimmedMessage)
    if (missingCellMatch) {
      const [, indexNo, subjectLabel] = missingCellMatch
      const subject = subjectRows.value.find((entry) => entry.subject === subjectLabel)
      if (subject) {
        setCellError(indexNo, subject.subject_id, trimmedMessage)
      } else {
        setRowError(indexNo, trimmedMessage)
      }
      return
    }

    const invalidCellMatch = /^Invalid mark for student\s+(.+?)\s+and subject\s+(\d+)\.\s+Use 0-100 or AB\.$/u.exec(trimmedMessage)
    if (invalidCellMatch) {
      const [, indexNo, subjectId] = invalidCellMatch
      setCellError(indexNo, Number(subjectId), trimmedMessage)
      return
    }

    const studentRuleMatch = /^Student\s+(.+?)(?::\s+|\s+)(.+)$/u.exec(trimmedMessage)
    if (!studentRuleMatch) {
      return
    }

    const [, indexNo, detail] = studentRuleMatch
    const normalizedDetail = detail.trim()

    if (/^can have only one OP1 subject\.$/u.test(normalizedDetail)) {
      highlightFilledSubjectsForStudent(indexNo, getSubjectIdsByCategory(2), trimmedMessage)
      return
    }

    if (/^can have only one OP2 subject\.$/u.test(normalizedDetail)) {
      highlightFilledSubjectsForStudent(indexNo, getSubjectIdsByCategory(3), trimmedMessage)
      return
    }

    if (/^can have only one OP3 subject\.$/u.test(normalizedDetail)) {
      highlightFilledSubjectsForStudent(indexNo, getSubjectIdsByCategory(4), trimmedMessage)
      return
    }

    if (/^must have marks or AB for exactly \d+ subjects\.$/u.test(normalizedDetail)) {
      highlightEmptySubjectsForStudent(
        indexNo,
        subjectRows.value.map((subject) => subject.subject_id),
        trimmedMessage,
      )
      return
    }

    if (/^Minimum \d+ main subjects needed\.$/u.test(normalizedDetail)) {
      highlightEmptySubjectsForStudent(indexNo, getSubjectIdsByCategory(1), trimmedMessage)
      return
    }

    if (/^Minimum \d+ OP1 subject needed\.$/u.test(normalizedDetail)) {
      highlightSubjectIdsForStudent(indexNo, getSubjectIdsByCategory(2), trimmedMessage)
      return
    }

    if (/^Minimum \d+ OP2 subjects needed\.$/u.test(normalizedDetail)) {
      highlightSubjectIdsForStudent(indexNo, getSubjectIdsByCategory(3), trimmedMessage)
      return
    }

    if (/^Minimum \d+ OP3 subjects needed\.$/u.test(normalizedDetail)) {
      highlightSubjectIdsForStudent(indexNo, getSubjectIdsByCategory(4), trimmedMessage)
      return
    }

    if (/^Please enter marks or AB for at least one religion subject\.$/u.test(normalizedDetail)) {
      highlightEmptySubjectsForStudent(indexNo, RELIGION_SUBJECT_IDS, trimmedMessage)
      return
    }

    if (/^Please enter marks or AB for the required main subjects\.$/u.test(normalizedDetail)) {
      highlightEmptySubjectsForStudent(indexNo, REQUIRED_MAIN_SUBJECT_IDS, trimmedMessage)
      return
    }

    setRowError(indexNo, trimmedMessage)
  })
}

const clearFieldError = (indexNo: string, subjectId: number): void => {
  const nextCellErrors = { ...cellErrors.value }
  delete nextCellErrors[getCellErrorKey(indexNo, subjectId)]
  cellErrors.value = nextCellErrors

  if (rowErrors.value[indexNo]) {
    const nextRowErrors = { ...rowErrors.value }
    delete nextRowErrors[indexNo]
    rowErrors.value = nextRowErrors
  }
}

const getCellErrorMessage = (indexNo: string, subjectId: number): string => {
  return cellErrors.value[getCellErrorKey(indexNo, subjectId)] ?? rowErrors.value[indexNo] ?? ''
}

const extractErrorMessages = (error: any, fallbackMessage: string): string[] => {
  const responseErrors = error?.response?.data?.errors
  if (Array.isArray(responseErrors)) {
    const normalized = responseErrors
      .map((item) => String(item ?? '').trim())
      .filter((item) => item !== '')

    if (normalized.length > 0) {
      return normalized
    }
  }

  const messageText = String(error?.response?.data?.message ?? fallbackMessage ?? '').trim()
  return messageText !== '' ? [messageText] : []
}

const setErrorMessagesState = (messages: string[], fallbackMessage: string, options: ErrorStateOptions = {}): void => {
  const normalized = messages
    .map((item) => String(item ?? '').trim())
    .filter((item) => item !== '')

  errorMessage.value = options.summaryMessage ?? normalized[0] ?? fallbackMessage
  applyValidationHighlights(normalized)
}

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
  clearErrorState()
  clearValidationHighlights()

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
    setErrorMessagesState(extractErrorMessages(error, text.value.loadOptionsError), text.value.loadOptionsError)
    await scrollMessage(errorMessageRef)
  } finally {
    loadingOptions.value = false
  }
}

const loadMarks = async (options: { preserveMessage?: boolean } = {}): Promise<void> => {
  if (!options.preserveMessage) {
    message.value = ''
  }
  clearErrorState()
  clearValidationHighlights()

  if (isAdmin.value && selectedSchoolCensusId.value <= 0) {
    setErrorMessagesState([text.value.selectSchoolFirst], text.value.selectSchoolFirst)
    await scrollMessage(errorMessageRef)
    return
  }

  if (selectedYear.value <= 0 || selectedTerm.value <= 0 || selectedGradeId.value <= 0 || selectedClassId.value <= 0) {
    setErrorMessagesState([text.value.selectFiltersFirst], text.value.selectFiltersFirst)
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
    canConfirmLoaded.value = isPrincipal.value && !!data.can_confirm
    confirmation.value = {
      is_completed: !!data.confirmation?.is_completed,
    }

    loadedTitle.value = data.class?.label
      ? `${terms.value.find((row) => row.id === selectedTerm.value)?.label ?? text.value.marksSheet} ${selectedYear.value} - ${data.class.label}`
      : text.value.marksSheet
  } catch (error: any) {
    subjectRows.value = []
    markRows.value = []
    canManageLoaded.value = false
    canConfirmLoaded.value = false
    confirmation.value = { is_completed: false }
    setErrorMessagesState(extractErrorMessages(error, text.value.loadMarksError), text.value.loadMarksError)
    await scrollMessage(errorMessageRef)
  } finally {
    loadingMarks.value = false
  }
}

const saveMarks = async (): Promise<void> => {
  savingMarks.value = true
  message.value = ''
  clearErrorState()
  clearValidationHighlights()

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

    const { data } = await api.post<{ message?: string }>('/marks', payload)
    const successMessage = data.message ?? 'Term test marks finalized successfully.'
    await loadMarks({ preserveMessage: true })
    clearValidationHighlights()
    message.value = successMessage
    await scrollMessage(messageRef)
  } catch (error: any) {
    const messages = extractErrorMessages(error, text.value.saveMarksError)
    setErrorMessagesState(
      messages,
      text.value.saveMarksError,
      { summaryMessage: messages.length > 1 ? text.value.saveMarksValidationSummary : undefined },
    )
    await scrollMessage(errorMessageRef)
  } finally {
    savingMarks.value = false
  }
}

const saveDraft = async (): Promise<void> => {
  savingDraft.value = true
  message.value = ''
  clearErrorState()
  clearValidationHighlights()

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

    const { data } = await api.post<{ message?: string }>('/marks/draft', payload)
    const successMessage = data.message ?? 'Term test marks draft saved successfully.'
    await loadMarks({ preserveMessage: true })
    clearValidationHighlights()
    message.value = successMessage
    await scrollMessage(messageRef)
  } catch (error: any) {
    setErrorMessagesState(extractErrorMessages(error, text.value.saveDraftError), text.value.saveDraftError)
    await scrollMessage(errorMessageRef)
  } finally {
    savingDraft.value = false
  }
}

const openLockedDialog = (messageText: string): void => {
  lockedDialogMessage.value = messageText
  showLockedDialog.value = true
}

const closeLockedDialog = (): void => {
  showLockedDialog.value = false
}

const handleSaveMarks = async (): Promise<void> => {
  if (confirmation.value.is_completed) {
    openLockedDialog(text.value.lockedEditMessage)
    return
  }

  await saveMarks()
}

const handleSaveDraft = async (): Promise<void> => {
  if (confirmation.value.is_completed) {
    openLockedDialog(text.value.lockedEditMessage)
    return
  }

  await saveDraft()
}

const updateConfirmation = async (isCompleted: boolean): Promise<void> => {
  updatingConfirmation.value = true
  message.value = ''
  clearErrorState()
  clearValidationHighlights()

  try {
    const { data } = await api.post<{
      message?: string
      confirmation?: {
        is_completed?: boolean
      }
    }>('/marks/confirmation', {
      year: selectedYear.value,
      term: selectedTerm.value,
      grade_id: selectedGradeId.value,
      class_id: selectedClassId.value,
      is_completed: isCompleted,
    })

    confirmation.value = {
      is_completed: !!data.confirmation?.is_completed,
    }
    message.value = data.message ?? (isCompleted ? 'Marks sheet confirmed successfully.' : 'Marks sheet reopened successfully.')
    await scrollMessage(messageRef)
  } catch (error: any) {
    setErrorMessagesState(extractErrorMessages(error, text.value.loadMarksError), text.value.loadMarksError)
    await scrollMessage(errorMessageRef)
  } finally {
    updatingConfirmation.value = false
  }
}

const exportMarks = async (): Promise<void> => {
  message.value = ''
  clearErrorState()

  if (isAdmin.value && selectedSchoolCensusId.value <= 0) {
    setErrorMessagesState([text.value.selectSchoolFirst], text.value.selectSchoolFirst)
    await scrollMessage(errorMessageRef)
    return
  }

  if (selectedYear.value <= 0 || selectedTerm.value <= 0 || selectedGradeId.value <= 0 || selectedClassId.value <= 0) {
    setErrorMessagesState([text.value.selectFiltersFirst], text.value.selectFiltersFirst)
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
    setErrorMessagesState(extractErrorMessages(error, text.value.exportMarksError), text.value.exportMarksError)
    await scrollMessage(errorMessageRef)
  } finally {
    exportingMarks.value = false
  }
}

const downloadImportTemplate = async (): Promise<void> => {
  message.value = ''
  clearErrorState()

  if (!canDownloadTemplate.value) {
    return
  }

  if (isAdmin.value && selectedSchoolCensusId.value <= 0) {
    setErrorMessagesState([text.value.selectSchoolFirst], text.value.selectSchoolFirst)
    await scrollMessage(errorMessageRef)
    return
  }

  if (selectedYear.value <= 0 || selectedTerm.value <= 0 || selectedGradeId.value <= 0 || selectedClassId.value <= 0) {
    setErrorMessagesState([text.value.selectFiltersFirst], text.value.selectFiltersFirst)
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
    setErrorMessagesState(extractErrorMessages(error, text.value.templateMarksError), text.value.templateMarksError)
    await scrollMessage(errorMessageRef)
  } finally {
    downloadingTemplate.value = false
  }
}

const openMarksFilePicker = (): void => {
  marksFileInputRef.value?.click()
}

const handleOpenMarksFilePicker = (): void => {
  if (confirmation.value.is_completed) {
    openLockedDialog(text.value.lockedUploadMessage)
    return
  }

  openMarksFilePicker()
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
  clearErrorState()
  clearValidationHighlights()
  uploadingMarks.value = true

  try {
    const formData = new FormData()
    formData.append('year', String(selectedYear.value))
    formData.append('term', String(selectedTerm.value))
    formData.append('grade_id', String(selectedGradeId.value))
    formData.append('class_id', String(selectedClassId.value))
    formData.append('file', selectedMarksFile.value)

    const { data } = await api.post<{ message?: string }>('/marks/import', formData)
    const successMessage = data.message ?? 'Term test marks draft uploaded successfully.'
    selectedMarksFile.value = null
    if (marksFileInputRef.value) {
      marksFileInputRef.value.value = ''
    }
    await loadMarks({ preserveMessage: true })
    clearValidationHighlights()
    message.value = successMessage
    await scrollMessage(messageRef)
  } catch (error: any) {
    setErrorMessagesState(extractErrorMessages(error, text.value.uploadMarksError), text.value.uploadMarksError)
    await scrollMessage(errorMessageRef)
  } finally {
    uploadingMarks.value = false
  }
}

const handleUploadMarksFile = async (): Promise<void> => {
  if (confirmation.value.is_completed) {
    openLockedDialog(text.value.lockedUploadMessage)
    return
  }

  await uploadMarksFile()
}

const clearMarks = async (): Promise<void> => {
  deletingMarks.value = true
  message.value = ''
  clearErrorState()
  clearValidationHighlights()

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
    clearValidationHighlights()
    message.value = successMessage
    await scrollMessage(messageRef)
  } catch (error: any) {
    setErrorMessagesState(extractErrorMessages(error, text.value.deleteMarksError), text.value.deleteMarksError)
    await scrollMessage(errorMessageRef)
  } finally {
    deletingMarks.value = false
  }
}

const openDeleteDialog = (): void => {
  showDeleteDialog.value = true
}

const handleDeleteMarks = (): void => {
  if (confirmation.value.is_completed) {
    openLockedDialog(text.value.lockedDeleteMessage)
    return
  }

  openDeleteDialog()
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
  clearValidationHighlights()
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

const recalculateRow = (row: MarkRow): void => {
  let total = 0
  let divisor = 0

  Object.values(row.marks).forEach((rawValue) => {
    const value = String(rawValue ?? '').trim().toUpperCase()
    if (value === '') {
      return
    }

    if (value === 'AB') {
      divisor += 1
      return
    }

    if (!/^\d{1,3}$/.test(value)) {
      return
    }

    const numeric = Number(value)
    if (numeric < 0 || numeric > 100) {
      return
    }

    total += numeric
    divisor += 1
  })

  row.total = divisor > 0 ? total : null
  row.average = divisor > 0 ? Number((total / divisor).toFixed(2)) : null
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
