<template>
  <div class="space-y-5">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p class="font-brand text-xs uppercase tracking-[0.2em] text-slate-500">Students Module</p>
      <h1 class="mt-2 font-display text-2xl font-bold text-slate-900">Students</h1>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <input
          v-model="search"
          type="text"
          placeholder="Search by admission number or name"
          :class="[
            'w-full rounded-xl border border-slate-300 px-4 py-2 text-sm outline-none ring-cyan-500 focus:ring-2',
            isAdmin ? 'md:max-w-xs lg:max-w-sm' : 'md:max-w-md',
          ]"
          @keyup.enter="loadStudents(1)"
        />
        <div class="flex flex-wrap items-center gap-2 md:justify-end">
          <select
            v-if="isAdmin"
            v-model.number="adminSchoolContextCensusId"
            class="rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none ring-cyan-500 focus:ring-2"
            @change="onAdminSchoolContextChange"
          >
            <option :value="0">All schools</option>
            <option v-for="row in schools" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
          <button class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700" @click="loadStudents(1)">
            Search
          </button>
          <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" @click="openAddDialog">
            Add Student
          </button>
        </div>
      </div>

      <p v-if="createSuccessMessage" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
        {{ createSuccessMessage }}
      </p>

      <div class="overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">Adm No</th>
              <th v-if="isAdmin" class="px-3 py-2 text-left font-semibold text-slate-600">School</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">Name with Initials</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">Grade/Class</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">Phone</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">DOB</th>
              <th v-if="showActionColumn" class="px-3 py-2 text-left font-semibold text-slate-600">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="student in students" :key="student.std_id" class="hover:bg-slate-50">
              <td class="px-3 py-2 font-medium text-slate-800">{{ student.index_no }}</td>
              <td v-if="isAdmin" class="px-3 py-2 text-slate-700">
                <span :title="'Census ID: ' + (student.census_id ?? 'N/A')">{{ student.school_name || '-' }}</span>
              </td>
              <td class="px-3 py-2 text-slate-700">{{ student.name_with_initials }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.grade_class }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.phone_no || '-' }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.dob || '-' }}</td>
              <td v-if="showActionColumn" class="px-3 py-2">
                <div class="flex gap-2">
                  <button
                    v-if="student.can_edit || canManageStudents"
                    class="rounded bg-cyan-600 px-3 py-1 text-xs font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="loadingEditStudentId === student.std_id || deletingStudentId === student.std_id"
                    @click="openEditDialog(student)"
                  >
                    {{ 'Edit' }}
                  </button>
                  <button
                    v-if="student.can_delete || canManageStudents"
                    class="rounded bg-rose-600 px-3 py-1 text-xs font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="deletingStudentId === student.std_id || loadingEditStudentId === student.std_id"
                    @click="deleteStudent(student)"
                  >
                    {{ deletingStudentId === student.std_id ? 'Deleting...' : 'Delete' }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && students.length === 0">
              <td :colspan="5 + (isAdmin ? 1 : 0) + (showActionColumn ? 1 : 0)" class="px-3 py-6 text-center text-slate-500">No students found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
        <p>Total: {{ meta.total }}</p>
        <div class="flex flex-wrap items-center gap-2 md:justify-end">
          <button
            class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="meta.current_page <= 1 || loading"
            @click="loadStudents(meta.current_page - 1)"
          >
            Prev
          </button>
          <span>Page {{ meta.current_page }} / {{ meta.last_page }}</span>
          <button
            class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="meta.current_page >= meta.last_page || loading"
            @click="loadStudents(meta.current_page + 1)"
          >
            Next
          </button>
        </div>
      </div>

      <p v-if="errorMessage" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ errorMessage }}
      </p>
    </section>

    <div v-if="showAddDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeAddDialog">
      <section class="max-h-[90vh] w-full max-w-6xl overflow-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-display text-xl font-bold text-slate-900">{{ isEditMode ? 'Edit Student (Manual)' : 'Add Student (Manual)' }}</h2>
          <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeAddDialog">Close</button>
        </div>

        <p
          v-if="createErrorMessage"
          ref="createErrorMessageRef"
          tabindex="-1"
          class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 focus:outline-none focus:ring-1 focus:ring-red-200"
        >
          {{ createErrorMessage }}
        </p>

        <form class="grid gap-4 md:grid-cols-3" @submit.prevent="submitAddStudent">
          <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">Core Details</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="text-sm text-slate-700">
                Admission No *
                <input v-model="createForm.index_no" type="text" :class="inputClass('index_no')" placeholder="e.g. 1234" required />
                <p v-if="fieldErrors.index_no" class="mt-1 text-xs text-red-600">{{ fieldErrors.index_no }}</p>
              </label>

              <label class="text-sm text-slate-700 md:col-span-2">
                Full Name *
                <input v-model="createForm.full_name" type="text" :class="inputClass('full_name')" required />
                <p v-if="fieldErrors.full_name" class="mt-1 text-xs text-red-600">{{ fieldErrors.full_name }}</p>
              </label>

              <label class="text-sm text-slate-700 md:col-span-2">
                Name with Initials *
                <input v-model="createForm.name_with_initials" type="text" :class="inputClass('name_with_initials')" required />
                <p v-if="fieldErrors.name_with_initials" class="mt-1 text-xs text-red-600">{{ fieldErrors.name_with_initials }}</p>
              </label>

              <label class="text-sm text-slate-700">
                Gender *
                <select v-model.number="createForm.gender_id" :class="inputClass('gender_id')" required>
                  <option :value="0" disabled>Select gender</option>
                  <option :value="2">Female</option>
                  <option :value="1">Male</option>
                </select>
                <p v-if="fieldErrors.gender_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.gender_id }}</p>
              </label>

              <label v-if="isAdmin" class="text-sm text-slate-700">
                School *
                <select v-model.number="createForm.census_id" :class="inputClass('census_id')" required>
                  <option :value="0" disabled>Select school</option>
                  <option v-for="row in schools" :key="row.id" :value="row.id">{{ row.label }}</option>
                </select>
                <p v-if="fieldErrors.census_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.census_id }}</p>
              </label>

              <label class="text-sm text-slate-700">
                Admission Date
                <input v-model="createForm.d_o_admission" type="date" :class="inputClass('d_o_admission')" />
                <p v-if="fieldErrors.d_o_admission" class="mt-1 text-xs text-red-600">{{ fieldErrors.d_o_admission }}</p>
              </label>

              <label class="text-sm text-slate-700">
                Academic Year *
                <select v-model.number="createForm.year" :class="inputClass('year')" :disabled="!isAdminSchoolSelected" required>
                  <option :value="0" disabled>Select academic year</option>
                  <option v-for="year in academicYears" :key="year" :value="year">{{ year }}</option>
                </select>
                <p v-if="fieldErrors.year" class="mt-1 text-xs text-red-600">{{ fieldErrors.year }}</p>
                <p v-else-if="isAdmin && !isAdminSchoolSelected" class="mt-1 text-xs text-slate-500">Select school first.</p>
              </label>

              <label class="text-sm text-slate-700">
                Grade
                <select v-model.number="createForm.grade_id" :class="inputClass('grade_id')" :disabled="!isAdminSchoolSelected || createForm.year <= 0">
                  <option :value="0">Select grade</option>
                  <option v-for="grade in grades" :key="grade.grade_id" :value="grade.grade_id">{{ grade.grade }}</option>
                </select>
                <p v-if="fieldErrors.grade_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.grade_id }}</p>
              </label>

              <label class="text-sm text-slate-700">
                Class
                <select v-model.number="createForm.class_id" :class="inputClass('class_id')" :disabled="!isAdminSchoolSelected || createForm.year <= 0 || createForm.grade_id <= 0">
                  <option :value="0">Select class</option>
                  <option v-for="row in classes" :key="row.class_id" :value="row.class_id">{{ row.class }}</option>
                </select>
                <p v-if="fieldErrors.class_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.class_id }}</p>
              </label>
            </div>
          </fieldset>

          <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">Contact & Address (Optional)</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="text-sm text-slate-700">
                Mobile
                <input v-model="createForm.phone_no" type="text" :class="inputClass('phone_no')" />
                <p v-if="fieldErrors.phone_no" class="mt-1 text-xs text-red-600">{{ fieldErrors.phone_no }}</p>
              </label>

              <label class="text-sm text-slate-700">
                WhatsApp
                <input v-model="createForm.whatsapp_no" type="text" :class="inputClass('whatsapp_no')" />
                <p v-if="fieldErrors.whatsapp_no" class="mt-1 text-xs text-red-600">{{ fieldErrors.whatsapp_no }}</p>
              </label>

              <label class="text-sm text-slate-700">
                Home Phone
                <input v-model="createForm.phone_home" type="text" :class="inputClass('phone_home')" />
                <p v-if="fieldErrors.phone_home" class="mt-1 text-xs text-red-600">{{ fieldErrors.phone_home }}</p>
              </label>

              <label class="text-sm text-slate-700">
                Address Line 1
                <input v-model="createForm.address1" type="text" :class="inputClass('address1')" />
                <p v-if="fieldErrors.address1" class="mt-1 text-xs text-red-600">{{ fieldErrors.address1 }}</p>
              </label>

              <label class="text-sm text-slate-700">
                Address Line 2
                <input v-model="createForm.address2" type="text" :class="inputClass('address2')" />
                <p v-if="fieldErrors.address2" class="mt-1 text-xs text-red-600">{{ fieldErrors.address2 }}</p>
              </label>

              <label class="text-sm text-slate-700">
                Email
                <input v-model="createForm.email" type="email" :class="inputClass('email')" />
                <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">{{ fieldErrors.email }}</p>
              </label>
            </div>
          </fieldset>

          <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">Demographics (Optional)</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="text-sm text-slate-700">
                Ethnic Group
                <select v-model.number="createForm.ethnic_group_id" :class="inputClass('ethnic_group_id')">
                  <option :value="0">Select ethnic group</option>
                  <option v-for="row in ethnicGroups" :key="row.id" :value="row.id">{{ row.label }}</option>
                </select>
              </label>

              <label class="text-sm text-slate-700">
                Religion
                <select v-model.number="createForm.religion_id" :class="inputClass('religion_id')">
                  <option :value="0">Select religion</option>
                  <option v-for="row in religions" :key="row.id" :value="row.id">{{ row.label }}</option>
                </select>
              </label>

              <label class="text-sm text-slate-700">
                DOB
                <input v-model="createForm.dob" type="date" :class="inputClass('dob')" />
                <p v-if="fieldErrors.dob" class="mt-1 text-xs text-red-600">{{ fieldErrors.dob }}</p>
              </label>
            </div>
          </fieldset>

          <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">Parents / Guardian (Optional)</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="text-sm text-slate-700">
                Father Name
                <input v-model="createForm.father_name" type="text" :class="inputClass('father_name')" />
              </label>

              <label class="text-sm text-slate-700">
                Father Mobile
                <input v-model="createForm.father_mobile" type="text" :class="inputClass('father_mobile')" />
              </label>

              <label class="text-sm text-slate-700">
                Father Job
                <input v-model="createForm.father_job" type="text" :class="inputClass('father_job')" />
              </label>

              <label class="text-sm text-slate-700">
                Mother Name
                <input v-model="createForm.mother_name" type="text" :class="inputClass('mother_name')" />
              </label>

              <label class="text-sm text-slate-700">
                Mother Mobile
                <input v-model="createForm.mother_mobile" type="text" :class="inputClass('mother_mobile')" />
              </label>

              <label class="text-sm text-slate-700">
                Mother Job
                <input v-model="createForm.mother_job" type="text" :class="inputClass('mother_job')" />
              </label>

              <label class="text-sm text-slate-700">
                Guardian Name
                <input v-model="createForm.guardian_name" type="text" :class="inputClass('guardian_name')" />
              </label>

              <label class="text-sm text-slate-700">
                Guardian Mobile
                <input v-model="createForm.guardian_mobile" type="text" :class="inputClass('guardian_mobile')" />
              </label>

              <label class="text-sm text-slate-700">
                Guardian Job
                <input v-model="createForm.guardian_job" type="text" :class="inputClass('guardian_job')" />
              </label>
            </div>
          </fieldset>

          <div class="md:col-span-3 flex justify-end gap-2">
            <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeAddDialog">
              Cancel
            </button>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="creating">
              {{ creating ? (isEditMode ? 'Updating...' : 'Saving...') : (isEditMode ? 'Update Student' : 'Save Student') }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'

interface Student {
  std_id: number
  index_no: string
  census_id?: number | null
  school_name?: string | null
  name_with_initials: string
  grade_class: string
  phone_no: string | null
  dob: string | null
  can_edit?: boolean
  can_delete?: boolean
}

interface StudentsMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

interface StudentsResponse {
  data: Student[]
  meta: StudentsMeta
}

interface StudentDetail {
  std_id: number
  census_id: number
  index_no: string
  full_name: string
  name_with_initials: string
  gender_id: number
  phone_no: string
  whatsapp_no: string
  phone_home: string
  address1: string
  address2: string
  email: string
  dob: string
  d_o_admission: string
  ethnic_group_id: number
  religion_id: number
  grade_id: number
  class_id: number
  year: number
  father_name: string
  father_job: string
  father_mobile: string
  mother_name: string
  mother_job: string
  mother_mobile: string
  guardian_name: string
  guardian_job: string
  guardian_mobile: string
}

interface StudentDetailResponse {
  data: StudentDetail
}


interface GradeRow {
  grade_id: number
  grade: string
}

interface GradeResponse {
  year?: number | null
  years?: number[]
  data: GradeRow[]
}

interface ClassRow {
  class_id: number
  class: string
}

interface ClassResponse {
  data: ClassRow[]
}

interface OptionRow {
  id: number
  label: string
}

interface StudentOptionsResponse {
  ethnic_groups: OptionRow[]
  religions: OptionRow[]
  schools: OptionRow[]
}

interface CreateStudentPayload {
  index_no: string
  full_name: string
  name_with_initials: string
  gender_id: number
  phone_no?: string
  whatsapp_no?: string
  phone_home?: string
  address1?: string
  address2?: string
  email?: string
  dob?: string
  d_o_admission?: string
  ethnic_group_id?: number
  religion_id?: number
  census_id?: number
  grade_id?: number
  class_id?: number
  year: number
  father_name?: string
  father_job?: string
  father_mobile?: string
  mother_name?: string
  mother_job?: string
  mother_mobile?: string
  guardian_name?: string
  guardian_job?: string
  guardian_mobile?: string
}

interface ValidationErrors {
  [key: string]: string
}

const search = ref('')
const loading = ref(false)
const creating = ref(false)
const showAddDialog = ref(false)
const errorMessage = ref('')
const createErrorMessage = ref('')
const createSuccessMessage = ref('')
const students = ref<Student[]>([])
const grades = ref<GradeRow[]>([])
const classes = ref<ClassRow[]>([])
const ethnicGroups = ref<OptionRow[]>([])
const religions = ref<OptionRow[]>([])
const schools = ref<OptionRow[]>([])
const academicYears = ref<number[]>([])
const fieldErrors = ref<ValidationErrors>({})
const createErrorMessageRef = ref<HTMLElement | null>(null)
const currentUser = getUser()
const initialSchoolContextCensusId = getSchoolContextCensusId()
const adminSchoolContextCensusId = ref<number>(initialSchoolContextCensusId ?? 0)
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')
const canManageStudents = computed(() => {
  const roleId = currentUser?.role_id ?? 0
  return roleId === 1 || roleId === 2 || roleId === 4 || roleName === 'admin' || roleName === 'administrator' || roleName === 'principal' || roleName === 'class teacher' || roleName === 'class_teacher'
})
const editStudentId = ref<number | null>(null)
const isEditMode = computed(() => editStudentId.value !== null)
const showActionColumn = computed(() => canManageStudents.value || students.value.some((row) => !!row.can_edit || !!row.can_delete))
const loadingEditStudentId = ref<number | null>(null)
const deletingStudentId = ref<number | null>(null)
const buildSchoolScopedRequestHeaders = (): Record<string, string> | undefined => {
  if (!isAdmin.value) {
    return undefined
  }

  const selectedCensusId = Number(createForm.value.census_id)
  return {
    'X-School-Census-Id': selectedCensusId > 0 ? String(selectedCensusId) : '',
  }
}
const scrollToCreateErrorMessage = async (): Promise<void> => {
  await nextTick()
  if (createErrorMessageRef.value) {
    createErrorMessageRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' })
    createErrorMessageRef.value.focus({ preventScroll: true })
  }
}

const inputClass = (field: string): string[] => {
  return [
    'mt-1 w-full rounded-lg border px-3 py-2',
    fieldErrors.value[field] ? 'border-red-300' : 'border-slate-300',
  ]
}

const createForm = ref({
  index_no: '',
  full_name: '',
  name_with_initials: '',
  gender_id: 0,
  phone_no: '',
  whatsapp_no: '',
  phone_home: '',
  address1: '',
  address2: '',
  email: '',
  dob: '',
  d_o_admission: '',
  ethnic_group_id: 0,
  religion_id: 0,
  census_id: 0,
  grade_id: 0,
  class_id: 0,
  year: 0,
  father_name: '',
  father_job: '',
  father_mobile: '',
  mother_name: '',
  mother_job: '',
  mother_mobile: '',
  guardian_name: '',
  guardian_job: '',
  guardian_mobile: '',
})

const isAdminSchoolSelected = computed(() => !isAdmin.value || Number(createForm.value.census_id) > 0)

const meta = ref<StudentsMeta>({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

const resetCreateForm = (): void => {
  createForm.value.index_no = ''
  createForm.value.full_name = ''
  createForm.value.name_with_initials = ''
  createForm.value.gender_id = 0
  createForm.value.phone_no = ''
  createForm.value.whatsapp_no = ''
  createForm.value.phone_home = ''
  createForm.value.address1 = ''
  createForm.value.address2 = ''
  createForm.value.email = ''
  createForm.value.dob = ''
  createForm.value.d_o_admission = ''
  createForm.value.ethnic_group_id = 0
  createForm.value.religion_id = 0
  createForm.value.census_id = 0
  createForm.value.grade_id = 0
  createForm.value.class_id = 0
  createForm.value.year = 0
  createForm.value.father_name = ''
  createForm.value.father_job = ''
  createForm.value.father_mobile = ''
  createForm.value.mother_name = ''
  createForm.value.mother_job = ''
  createForm.value.mother_mobile = ''
  createForm.value.guardian_name = ''
  createForm.value.guardian_job = ''
  createForm.value.guardian_mobile = ''
  classes.value = []
}

const applyStudentDetailToForm = async (detail: StudentDetail): Promise<void> => {
  createForm.value.index_no = detail.index_no ?? ''
  createForm.value.census_id = Number(detail.census_id ?? 0)
  createForm.value.full_name = detail.full_name ?? ''
  createForm.value.name_with_initials = detail.name_with_initials ?? ''
  createForm.value.gender_id = Number(detail.gender_id ?? 0)
  createForm.value.phone_no = detail.phone_no ?? ''
  createForm.value.whatsapp_no = detail.whatsapp_no ?? ''
  createForm.value.phone_home = detail.phone_home ?? ''
  createForm.value.address1 = detail.address1 ?? ''
  createForm.value.address2 = detail.address2 ?? ''
  createForm.value.email = detail.email ?? ''
  createForm.value.dob = detail.dob ?? ''
  createForm.value.d_o_admission = detail.d_o_admission ?? ''
  createForm.value.ethnic_group_id = Number(detail.ethnic_group_id ?? 0)
  createForm.value.religion_id = Number(detail.religion_id ?? 0)
  createForm.value.year = Number.isFinite(Number(detail.year)) ? Number(detail.year) : 0
  createForm.value.grade_id = 0
  createForm.value.class_id = 0
  createForm.value.father_name = detail.father_name ?? ''
  createForm.value.father_job = detail.father_job ?? ''
  createForm.value.father_mobile = detail.father_mobile ?? ''
  createForm.value.mother_name = detail.mother_name ?? ''
  createForm.value.mother_job = detail.mother_job ?? ''
  createForm.value.mother_mobile = detail.mother_mobile ?? ''
  createForm.value.guardian_name = detail.guardian_name ?? ''
  createForm.value.guardian_job = detail.guardian_job ?? ''
  createForm.value.guardian_mobile = detail.guardian_mobile ?? ''

  if (createForm.value.year > 0) {
    await loadGrades(createForm.value.year)
  }

  const detailGradeId = Number(detail.grade_id ?? 0)
  createForm.value.grade_id = detailGradeId

  if (createForm.value.year > 0 && detailGradeId > 0) {
    await loadClasses(detailGradeId, createForm.value.year)
    createForm.value.class_id = Number(detail.class_id ?? 0)
  } else {
    classes.value = []
    createForm.value.class_id = 0
  }
}
const openAddDialog = async (): Promise<void> => {
  editStudentId.value = null
  resetCreateForm()
  createErrorMessage.value = ''
  fieldErrors.value = {}

  if (isAdmin.value) {
    const scopedCensusId = Number(adminSchoolContextCensusId.value)
    createForm.value.census_id = scopedCensusId > 0 ? scopedCensusId : 0
  }

  if (isAdminSchoolSelected.value) {
    await loadGrades()
  } else {
    academicYears.value = []
    grades.value = []
    classes.value = []
  }

  showAddDialog.value = true
}
const closeAddDialog = (): void => {
  showAddDialog.value = false
  editStudentId.value = null
  createErrorMessage.value = ''
  fieldErrors.value = {}
}

const onAdminSchoolContextChange = async (): Promise<void> => {
  if (!isAdmin.value) {
    return
  }

  const censusId = Number(adminSchoolContextCensusId.value)
  setSchoolContextCensusId(censusId > 0 ? censusId : null)

  if (showAddDialog.value && !isEditMode.value) {
    createForm.value.census_id = censusId > 0 ? censusId : 0
    createForm.value.year = 0
    createForm.value.grade_id = 0
    createForm.value.class_id = 0
    grades.value = []
    classes.value = []
  }

  await Promise.all([loadStudents(1), loadGrades()])
}
const openEditDialog = async (student: Student): Promise<void> => {
  if (!(student.can_edit || canManageStudents.value)) {
    return
  }

  loadingEditStudentId.value = student.std_id
  createErrorMessage.value = ''
  fieldErrors.value = {}

  try {
    const { data } = await api.get<StudentDetailResponse>(`/students/${student.std_id}`)
    await applyStudentDetailToForm(data.data)
    editStudentId.value = student.std_id
    showAddDialog.value = true
  } catch (error) {
    errorMessage.value = extractApiMessage(error) || 'Unable to load student details.'
  } finally {
    loadingEditStudentId.value = null
  }
}

const deleteStudent = async (student: Student): Promise<void> => {
  if (!(student.can_delete || canManageStudents.value)) {
    return
  }

  if (!window.confirm(`Delete student ${student.index_no}?`)) {
    return
  }

  deletingStudentId.value = student.std_id
  errorMessage.value = ''

  try {
    const { data } = await api.delete(`/students/${student.std_id}`)
    createSuccessMessage.value = typeof data?.message === 'string' && data.message.trim() !== ''
      ? data.message
      : 'Student deleted successfully.'
    await loadStudents(meta.value.current_page)
  } catch (error) {
    errorMessage.value = extractApiMessage(error) || 'Unable to delete student.'
  } finally {
    deletingStudentId.value = null
  }
}

const loadStudents = async (page = 1): Promise<void> => {
  loading.value = true
  errorMessage.value = ''

  try {
    const { data } = await api.get<StudentsResponse>('/students', {
      params: {
        q: search.value,
        page,
        per_page: meta.value.per_page,
      },
    })

    students.value = data.data
    meta.value = data.meta
  } catch (error) {
    students.value = []
    errorMessage.value = extractApiMessage(error)
  } finally {
    loading.value = false
  }
}

const loadGrades = async (year?: number): Promise<void> => {
  const parsedYear = Number(year)
  const hasSelectedYear = Number.isFinite(parsedYear) && parsedYear >= 2000 && parsedYear <= 2100

  if (isAdmin.value && Number(createForm.value.census_id) <= 0) {
    academicYears.value = []
    grades.value = []
    return
  }

  try {
    const headers = buildSchoolScopedRequestHeaders()
    const requestConfig: { headers?: Record<string, string>; params?: { year: number } } = {}
    if (headers) {
      requestConfig.headers = headers
    }
    if (hasSelectedYear) {
      requestConfig.params = { year: parsedYear }
    }

    const { data } = await api.get<GradeResponse>('/grades', Object.keys(requestConfig).length > 0 ? requestConfig : undefined)

    const normalizedYears = Array.isArray(data.years)
      ? data.years
          .map((value) => Number(value))
          .filter((value) => Number.isFinite(value) && value >= 2000 && value <= 2100)
      : []

    if (normalizedYears.length > 0) {
      academicYears.value = normalizedYears
    } else if (typeof data.year === 'number' && Number.isFinite(data.year)) {
      academicYears.value = [Number(data.year)]
    } else {
      academicYears.value = []
    }

    grades.value = hasSelectedYear && Array.isArray(data.data) ? data.data : []
  } catch {
    grades.value = []
    if (academicYears.value.length === 0) {
      academicYears.value = []
    }
  }
}
const loadStudentOptions = async (): Promise<void> => {
  try {
    const { data } = await api.get<StudentOptionsResponse>('/students/options')
    ethnicGroups.value = Array.isArray(data.ethnic_groups) ? data.ethnic_groups : []
    religions.value = Array.isArray(data.religions) ? data.religions : []
    schools.value = Array.isArray(data.schools) ? data.schools : []

    if (isAdmin.value && adminSchoolContextCensusId.value > 0) {
      const hasSelectedSchool = schools.value.some((row) => row.id === adminSchoolContextCensusId.value)
      if (!hasSelectedSchool) {
        adminSchoolContextCensusId.value = 0
        setSchoolContextCensusId(null)
      }
    }
  } catch {
    ethnicGroups.value = []
    religions.value = []
    schools.value = []
  }
}

const loadClasses = async (gradeId: number, year = Number(createForm.value.year)): Promise<void> => {
  const parsedGradeId = Number(gradeId)
  const parsedYear = Number(year)

  if (isAdmin.value && Number(createForm.value.census_id) <= 0) {
    classes.value = []
    createForm.value.class_id = 0
    return
  }

  if (!Number.isFinite(parsedGradeId) || parsedGradeId <= 0 || !Number.isFinite(parsedYear) || parsedYear < 2000 || parsedYear > 2100) {
    classes.value = []
    createForm.value.class_id = 0
    return
  }

  try {
    const headers = buildSchoolScopedRequestHeaders()
    const requestConfig: { headers?: Record<string, string>; params: { year: number } } = {
      params: { year: parsedYear },
    }

    if (headers) {
      requestConfig.headers = headers
    }

    const { data } = await api.get<ClassResponse>(`/classes/by-grade/${parsedGradeId}`, requestConfig)
    classes.value = Array.isArray(data.data) ? data.data : []

    if (!classes.value.some((row) => row.class_id === createForm.value.class_id)) {
      createForm.value.class_id = 0
    }
  } catch {
    classes.value = []
    createForm.value.class_id = 0
  }
}
watch(
  () => createForm.value.year,
  async (year) => {
    const parsedYear = Number(year)

    createForm.value.grade_id = 0
    createForm.value.class_id = 0
    classes.value = []

    if (Number.isFinite(parsedYear) && parsedYear >= 2000 && parsedYear <= 2100) {
      await loadGrades(parsedYear)
    } else {
      grades.value = []
    }
  },
)

watch(
  () => createForm.value.grade_id,
  async (gradeId) => {
    await loadClasses(Number(gradeId), Number(createForm.value.year))
  },
)

watch(
  () => createForm.value.census_id,
  async (censusId) => {
    if (!isAdmin.value || isEditMode.value) {
      return
    }

    const parsed = Number(censusId)
    createForm.value.year = 0
    createForm.value.grade_id = 0
    createForm.value.class_id = 0
    academicYears.value = []
    grades.value = []
    classes.value = []

    if (parsed > 0) {
      adminSchoolContextCensusId.value = parsed
      setSchoolContextCensusId(parsed)
      await loadGrades()
      return
    }

    adminSchoolContextCensusId.value = 0
    setSchoolContextCensusId(null)
  },
)
const extractApiMessage = (error: unknown): string => {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { data?: { message?: string } } }).response
    if (typeof response?.data?.message === 'string' && response.data.message.trim() !== '') {
      return response.data.message
    }
  }

  return ''
}

const extractFieldErrors = (error: unknown): ValidationErrors => {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { data?: { errors?: Record<string, string[]> } } }).response
    const errors = response?.data?.errors
    if (errors && typeof errors === 'object') {
      const flattened: ValidationErrors = {}
      for (const [key, value] of Object.entries(errors)) {
        if (Array.isArray(value) && value.length > 0) {
          flattened[key] = value[0]
        }
      }
      return flattened
    }
  }

  return {}
}

const submitAddStudent = async (): Promise<void> => {
  createErrorMessage.value = ''
  createSuccessMessage.value = ''
  fieldErrors.value = {}

  const hasGrade = Number(createForm.value.grade_id) > 0
  const hasClass = Number(createForm.value.class_id) > 0

  if (hasGrade !== hasClass) {
    createErrorMessage.value = 'Please select both grade and class, or leave both empty.'
    fieldErrors.value = { ...fieldErrors.value, grade_id: createErrorMessage.value, class_id: createErrorMessage.value }
    await scrollToCreateErrorMessage()
    return
  }

  if (isAdmin.value && Number(createForm.value.census_id) <= 0) {
    createErrorMessage.value = 'Please select a school for this student.'
    fieldErrors.value = { ...fieldErrors.value, census_id: 'Please select a school.' }
    await scrollToCreateErrorMessage()
    return
  }

  const selectedYear = Number(createForm.value.year)
  if (!Number.isFinite(selectedYear) || selectedYear < 2000 || selectedYear > 2100) {
    createErrorMessage.value = 'Please select a valid academic year.'
    fieldErrors.value = { ...fieldErrors.value, year: 'Academic year is required.' }
    await scrollToCreateErrorMessage()
    return
  }

  creating.value = true

  const payload: CreateStudentPayload = {
    index_no: createForm.value.index_no.trim(),
    full_name: createForm.value.full_name.trim(),
    name_with_initials: createForm.value.name_with_initials.trim(),
    gender_id: Number(createForm.value.gender_id),
  }

  if (createForm.value.phone_no.trim() !== '') payload.phone_no = createForm.value.phone_no.trim()
  if (createForm.value.whatsapp_no.trim() !== '') payload.whatsapp_no = createForm.value.whatsapp_no.trim()
  if (createForm.value.phone_home.trim() !== '') payload.phone_home = createForm.value.phone_home.trim()
  if (createForm.value.address1.trim() !== '') payload.address1 = createForm.value.address1.trim()
  if (createForm.value.address2.trim() !== '') payload.address2 = createForm.value.address2.trim()
  if (createForm.value.email.trim() !== '') payload.email = createForm.value.email.trim()
  if (createForm.value.dob.trim() !== '') payload.dob = createForm.value.dob.trim()
  if (createForm.value.d_o_admission.trim() !== '') payload.d_o_admission = createForm.value.d_o_admission.trim()
  if (Number(createForm.value.ethnic_group_id) > 0) payload.ethnic_group_id = Number(createForm.value.ethnic_group_id)
  if (isAdmin.value && Number(createForm.value.census_id) > 0) payload.census_id = Number(createForm.value.census_id)
  if (hasGrade) payload.grade_id = Number(createForm.value.grade_id)
  if (hasClass) payload.class_id = Number(createForm.value.class_id)
  payload.year = selectedYear

  if (createForm.value.father_name.trim() !== '') payload.father_name = createForm.value.father_name.trim()
  if (createForm.value.father_job.trim() !== '') payload.father_job = createForm.value.father_job.trim()
  if (createForm.value.father_mobile.trim() !== '') payload.father_mobile = createForm.value.father_mobile.trim()
  if (createForm.value.mother_name.trim() !== '') payload.mother_name = createForm.value.mother_name.trim()
  if (createForm.value.mother_job.trim() !== '') payload.mother_job = createForm.value.mother_job.trim()
  if (createForm.value.mother_mobile.trim() !== '') payload.mother_mobile = createForm.value.mother_mobile.trim()
  if (createForm.value.guardian_name.trim() !== '') payload.guardian_name = createForm.value.guardian_name.trim()
  if (createForm.value.guardian_job.trim() !== '') payload.guardian_job = createForm.value.guardian_job.trim()
  if (createForm.value.guardian_mobile.trim() !== '') payload.guardian_mobile = createForm.value.guardian_mobile.trim()

  try {
    const { data } = isEditMode.value && editStudentId.value !== null
      ? await api.put(`/students/${editStudentId.value}`, payload)
      : await api.post('/students', payload)

    createSuccessMessage.value = typeof data?.message === 'string' ? data.message : ''
    if (isAdmin.value && !isEditMode.value && Number(createForm.value.census_id) > 0) {
      const censusId = Number(createForm.value.census_id)
      adminSchoolContextCensusId.value = censusId
      setSchoolContextCensusId(censusId)
    }
    resetCreateForm()
    showAddDialog.value = false
    editStudentId.value = null

    await loadStudents(meta.value.current_page)
  } catch (error) {
    const validationErrors = extractFieldErrors(error)
    const apiMessage = extractApiMessage(error)
    fieldErrors.value = validationErrors
    createErrorMessage.value =
      apiMessage ||
      (Object.keys(validationErrors).length > 0
        ? 'Please correct the highlighted fields and try again.'
        : 'Unable to save student. Please try again.')
    await scrollToCreateErrorMessage()
  } finally {
    creating.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadGrades(), loadStudentOptions()])
  await loadStudents(1)
})
</script>









