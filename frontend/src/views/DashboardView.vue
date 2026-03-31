<template>
  <div class="space-y-6">
    <header class="rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-900 via-cyan-900 to-emerald-700 p-7 text-white shadow-xl">
      <p class="font-brand text-xs uppercase tracking-[0.2em] text-cyan-200">School Management Dashboard</p>
      <h1 class="mt-2 font-display text-3xl font-bold md:text-4xl">Welcome to the Control Center</h1>
      <p class="mt-2 max-w-3xl text-sm text-cyan-100 md:text-base"></p>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">Students</p>
        <p class="mt-1 text-xs text-slate-400">Year: {{ summary.students_latest_year ?? 'N/A' }}</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.students_total }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">Academic Staff</p>
        <p class="mt-1 text-xs text-slate-400">All active</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.staff_total }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">Grades</p>
        <p class="mt-1 text-xs text-slate-400">Year: {{ summary.grades_latest_year ?? 'N/A' }}</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.grades_total }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">Classes</p>
        <p class="mt-1 text-xs text-slate-400">Year: {{ summary.classes_latest_year ?? 'N/A' }}</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.classes_total }}</p>
      </article>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-display text-xl font-bold">Data Status</h2>
        <div class="mt-4 space-y-2 text-sm text-slate-700">
          <p><strong>Students last updated:</strong> {{ formatDate(summary.students_last_updated) }}</p>
          <p><strong>Staff last updated:</strong> {{ formatDate(summary.staff_last_updated) }}</p>
        </div>
      </article>

      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-display text-xl font-bold">Available Modules</h2>
        <ul class="mt-4 space-y-2 text-sm text-slate-700">
          <li>1. Students management (connected)</li>
          <li>2. Grades and Classes lookup (connected)</li>
          <li>3. Staff, Payments, Reports (next API rollout)</li>
        </ul>
      </article>
    </section>

    <section v-if="isAdmin" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <h2 class="font-display text-xl font-bold">Role Feature Access</h2>
          <p class="mt-1 text-sm text-slate-600">Select a school first, then set feature access for each role. Users inherit role permissions automatically.</p>
        </div>

        <label class="text-sm text-slate-700">
          School
          <select
            v-model.number="selectedPermissionSchoolCensusId"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2 md:min-w-[240px]"
            @change="onPermissionSchoolChange"
          >
            <option :value="0">Select school</option>
            <option v-for="school in permissionSchools" :key="school.id" :value="school.id">{{ school.label }}</option>
          </select>
        </label>
      </div>

      <p v-if="permissionNotice" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
        {{ permissionNotice }}
      </p>
      <p v-if="permissionError" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ permissionError }}
      </p>

      <p v-if="!permissionStorageReady" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
        Role permission table is missing. Run backend migrations to save feature permissions.
      </p>
      <p v-else-if="selectedPermissionSchoolCensusId <= 0" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
        Select a school to load roles.
      </p>
      <p v-else-if="permissionLoading" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
        Loading roles and permissions...
      </p>
      <p v-else-if="permissionRoles.length === 0" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
        No editable roles found.
      </p>

      <div v-if="permissionStorageReady && selectedPermissionSchoolCensusId > 0 && !permissionLoading && permissionRoles.length > 0" class="mt-4 overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">Role</th>
              <th v-for="feature in permissionFeatures" :key="feature.key" class="px-3 py-2 text-left font-semibold text-slate-600" :title="feature.description">
                {{ feature.label }}
              </th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">Save</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="role in permissionRoles" :key="role.role_id" class="hover:bg-slate-50">
              <td class="px-3 py-2">
                <p class="font-semibold text-slate-900">{{ role.role_name || 'Role not set' }}</p>
                <p class="text-xs text-slate-500">Role ID: {{ role.role_id }}</p>
              </td>
              <td v-for="feature in permissionFeatures" :key="`${role.role_id}-${feature.key}`" class="px-3 py-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                  <input v-model="role.permissions[feature.key]" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" />
                </label>
              </td>
              <td class="px-3 py-2">
                <button
                  class="rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="permissionSavingRoleId === role.role_id"
                  @click="saveRolePermissions(role)"
                >
                  {{ permissionSavingRoleId === role.role_id ? 'Saving...' : 'Save' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <p v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'

interface Summary {
  students_total: number
  staff_total: number
  grades_total: number
  classes_total: number
  students_last_updated: string | null
  staff_last_updated: string | null
  students_latest_year: number | null
  grades_latest_year: number | null
  classes_latest_year: number | null
}

interface DashboardSummaryResponse {
  summary: Summary
}

interface OptionRow {
  id: number
  label: string
}

interface FeatureDefinition {
  key: string
  label: string
  description: string
}

interface PermissionRoleRow {
  role_id: number
  role_name: string | null
  permissions: Record<string, boolean>
}

interface PermissionResponse {
  schools?: OptionRow[]
  selected_school_census_id?: string | null
  features?: FeatureDefinition[]
  roles?: PermissionRoleRow[]
  storage_ready?: boolean
  message?: string
}

interface PermissionUpdateResponse {
  message?: string
  data?: {
    permissions?: Record<string, boolean>
  }
}

const error = ref('')
const summary = reactive<Summary>({
  students_total: 0,
  staff_total: 0,
  grades_total: 0,
  classes_total: 0,
  students_last_updated: null,
  staff_last_updated: null,
  students_latest_year: null,
  grades_latest_year: null,
  classes_latest_year: null,
})

const currentUser = getUser()
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')

const selectedPermissionSchoolCensusId = ref<number>(getSchoolContextCensusId() ?? 0)
const permissionSchools = ref<OptionRow[]>([])
const permissionFeatures = ref<FeatureDefinition[]>([])
const permissionRoles = ref<PermissionRoleRow[]>([])
const permissionLoading = ref(false)
const permissionSavingRoleId = ref<number | null>(null)
const permissionStorageReady = ref(true)
const permissionError = ref('')
const permissionNotice = ref('')

const extractApiMessage = (reason: unknown): string => {
  if (typeof reason === 'object' && reason !== null && 'response' in reason) {
    const response = (reason as { response?: { data?: { message?: string } } }).response
    if (typeof response?.data?.message === 'string' && response.data.message.trim() !== '') {
      return response.data.message
    }
  }

  return ''
}

const normalizePermissions = (raw: Record<string, boolean> | undefined): Record<string, boolean> => {
  const normalized: Record<string, boolean> = {}
  for (const feature of permissionFeatures.value) {
    normalized[feature.key] = Boolean(raw?.[feature.key])
  }
  return normalized
}

const loadSummary = async (): Promise<void> => {
  try {
    const { data } = await api.get<DashboardSummaryResponse>('/dashboard/summary')
    Object.assign(summary, data.summary)
  } catch {
    error.value = 'Unable to load dashboard summary right now.'
  }
}

const loadPermissionMatrix = async (): Promise<void> => {
  if (!isAdmin.value) {
    return
  }

  permissionLoading.value = true
  permissionError.value = ''
  permissionNotice.value = ''

  try {
    const selected = Number(selectedPermissionSchoolCensusId.value)
    const hasSelectedSchool = selected > 0

    const requestConfig: {
      params?: Record<string, string>
      headers?: Record<string, string>
    } = {}

    if (hasSelectedSchool) {
      const censusValue = String(selected)
      requestConfig.params = { school_census_id: censusValue }
      requestConfig.headers = { 'X-School-Census-Id': censusValue }
    }

    const { data } = await api.get<PermissionResponse>('/admin/feature-permissions', Object.keys(requestConfig).length > 0 ? requestConfig : undefined)

    permissionSchools.value = Array.isArray(data.schools) ? data.schools : []
    permissionFeatures.value = Array.isArray(data.features) ? data.features : []
    permissionStorageReady.value = data.storage_ready !== false

    if (typeof data.message === 'string' && data.message.trim() !== '') {
      permissionNotice.value = data.message
    }

    const roles = Array.isArray(data.roles) ? data.roles : []
    permissionRoles.value = roles.map((role) => ({
      ...role,
      permissions: normalizePermissions(role.permissions),
    }))

    if (hasSelectedSchool) {
      const hasSchoolInList = permissionSchools.value.some((school) => school.id === selected)
      if (!hasSchoolInList) {
        selectedPermissionSchoolCensusId.value = 0
        setSchoolContextCensusId(null)
        permissionRoles.value = []
      }
    }
  } catch (reason) {
    permissionRoles.value = []
    permissionStorageReady.value = false
    permissionError.value = extractApiMessage(reason) || 'Unable to load role feature permissions right now.'
  } finally {
    permissionLoading.value = false
  }
}

const onPermissionSchoolChange = async (): Promise<void> => {
  const selected = Number(selectedPermissionSchoolCensusId.value)
  setSchoolContextCensusId(selected > 0 ? selected : null)

  await loadPermissionMatrix()
}

const saveRolePermissions = async (role: PermissionRoleRow): Promise<void> => {
  const selected = Number(selectedPermissionSchoolCensusId.value)
  if (selected <= 0) {
    permissionError.value = 'Select a school before saving permissions.'
    return
  }

  permissionSavingRoleId.value = role.role_id
  permissionError.value = ''
  permissionNotice.value = ''

  try {
    const payload = {
      school_census_id: String(selected),
      permissions: role.permissions,
    }

    const { data } = await api.put<PermissionUpdateResponse>(`/admin/feature-permissions/roles/${role.role_id}`, payload)

    if (data.data?.permissions) {
      role.permissions = normalizePermissions(data.data.permissions)
    }

    permissionNotice.value = typeof data.message === 'string' && data.message.trim() !== ''
      ? data.message
      : `Permissions updated for ${role.role_name || `role ${role.role_id}`}.`
  } catch (reason) {
    permissionError.value = extractApiMessage(reason) || 'Unable to update role feature permissions.'
  } finally {
    permissionSavingRoleId.value = null
  }
}

const formatDate = (value: string | null): string => {
  if (!value) return 'N/A'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return date.toLocaleString()
}

onMounted(async () => {
  await loadSummary()

  if (isAdmin.value) {
    await loadPermissionMatrix()
  }
})
</script>
