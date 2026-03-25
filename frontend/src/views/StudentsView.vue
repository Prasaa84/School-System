<template>
  <div class="space-y-5">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p class="font-brand text-xs uppercase tracking-[0.2em] text-slate-500">Students Module</p>
      <h1 class="mt-2 font-display text-2xl font-bold text-slate-900">Students</h1>
      <p class="mt-2 text-sm text-slate-600">
        Student list from `student_tbl` with current grade/class computed using the old business rule.
      </p>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <input
          v-model="search"
          type="text"
          placeholder="Search by admission number or name"
          class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm outline-none ring-cyan-500 focus:ring-2 md:max-w-md"
          @keyup.enter="loadStudents(1)"
        />
        <button class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700" @click="loadStudents(1)">
          Search
        </button>
      </div>

      <div class="overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">Adm No</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">Name with Initials</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">Grade/Class</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">Phone</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">DOB</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="student in students" :key="student.std_id" class="hover:bg-slate-50">
              <td class="px-3 py-2 font-medium text-slate-800">{{ student.index_no }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.name_with_initials }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.grade_class }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.phone_no || '-' }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.dob || '-' }}</td>
            </tr>
            <tr v-if="!loading && students.length === 0">
              <td colspan="5" class="px-3 py-6 text-center text-slate-500">No students found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
        <p>Total: {{ meta.total }}</p>
        <div class="flex items-center gap-2">
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
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '../services/api'

interface Student {
  std_id: number
  index_no: string
  name_with_initials: string
  grade_class: string
  phone_no: string | null
  dob: string | null
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

const search = ref('')
const loading = ref(false)
const errorMessage = ref('')
const students = ref<Student[]>([])
const meta = ref<StudentsMeta>({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

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
  } catch {
    students.value = []
    errorMessage.value = 'Unable to load students list.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadStudents(1)
})
</script>
