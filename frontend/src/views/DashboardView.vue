<template>
  <div class="space-y-6">
    <header class="rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-900 via-cyan-900 to-emerald-700 p-7 text-white shadow-xl">
      <p class="font-brand text-xs uppercase tracking-[0.2em] text-cyan-200">{{ heroEyebrow }}</p>
      <h1 class="mt-2 font-display text-3xl font-bold md:text-4xl">{{ heroTitle }}</h1>
      <p class="mt-2 max-w-3xl text-sm text-cyan-100 md:text-base"></p>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">{{ text.students }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ text.yearLabel }}: {{ summary.students_latest_year ?? text.notAvailable }}</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.students_total }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">{{ text.academicStaff }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ text.allActive }}</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.staff_total }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">{{ text.grades }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ text.yearLabel }}: {{ summary.grades_latest_year ?? text.notAvailable }}</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.grades_total }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-slate-500">{{ text.classes }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ text.yearLabel }}: {{ summary.classes_latest_year ?? text.notAvailable }}</p>
        <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ summary.classes_total }}</p>
      </article>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-display text-xl font-bold">{{ text.dataStatus }}</h2>
        <div class="mt-4 space-y-2 text-sm text-slate-700">
          <p><strong>{{ text.studentsLastUpdated }}:</strong> {{ formatDate(summary.students_last_updated) }}</p>
          <p><strong>{{ text.staffLastUpdated }}:</strong> {{ formatDate(summary.staff_last_updated) }}</p>
        </div>
      </article>

      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-display text-xl font-bold">{{ text.availableModules }}</h2>
        <ul class="mt-4 space-y-2 text-sm text-slate-700">
          <li v-for="(moduleLabel, index) in availableModuleLabels" :key="moduleLabel">{{ index + 1 }}. {{ moduleLabel }}</li>
          <li v-if="availableModuleLabels.length === 0">{{ text.noModulesAvailable }}</li>
        </ul>
      </article>
    </section>

    <section v-if="isAdmin" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <h2 class="font-display text-xl font-bold">{{ text.roleFeatureAccess }}</h2>
          <p class="mt-1 text-sm text-slate-600">{{ text.roleFeatureAccessHelp }}</p>
        </div>
        <label class="text-sm text-slate-700">
          {{ text.school }}
          <select v-model.number="selectedPermissionSchoolCensusId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2 md:min-w-[240px]" @change="onPermissionSchoolChange">
            <option :value="0">{{ text.selectSchool }}</option>
            <option v-for="schoolOption in permissionSchools" :key="schoolOption.id" :value="schoolOption.id">{{ schoolOption.label }}</option>
          </select>
        </label>
      </div>
      <p v-if="permissionNotice" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ permissionNotice }}</p>
      <p v-if="permissionError" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ permissionError }}</p>
      <p v-if="!permissionStorageReady" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">{{ text.permissionStorageMissing }}</p>
      <p v-else-if="selectedPermissionSchoolCensusId <= 0" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ text.selectSchoolToLoadRoles }}</p>
      <p v-else-if="permissionLoading" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ text.loadingRoles }}</p>
      <p v-else-if="permissionRoles.length === 0" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ text.noEditableRoles }}</p>

      <div v-if="permissionStorageReady && selectedPermissionSchoolCensusId > 0 && !permissionLoading && permissionRoles.length > 0" class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
        <table class="w-full table-fixed divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="w-[180px] px-3 py-3 text-left font-semibold text-slate-600">{{ text.feature }}</th>
              <th v-for="role in permissionRoles" :key="role.role_id" class="w-[112px] px-2 py-3 text-left align-top">
                <p class="text-xs font-semibold leading-tight text-slate-900">{{ role.role_name || text.roleNotSet }}</p>
                <p class="text-xs text-slate-500">{{ text.roleId }}: {{ role.role_id }}</p>
                <button class="mt-2 w-full rounded-lg bg-cyan-600 px-2 py-1.5 text-[11px] font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="permissionSavingRoleId === role.role_id" @click="saveRolePermissions(role)">
                  {{ permissionSavingRoleId === role.role_id ? text.saving : text.save }}
                </button>
              </th>
            </tr>
          </thead>
          <tbody v-for="group in permissionFeatureGroups" :key="group.key" class="divide-y divide-slate-100 bg-white">
            <tr class="bg-slate-100/80">
              <th :colspan="permissionRoles.length + 1" class="px-3 py-2 text-left text-xs font-extrabold uppercase tracking-[0.16em] text-slate-600">
                {{ group.label }}
              </th>
            </tr>
            <tr v-for="feature in group.features" :key="feature.key" class="hover:bg-slate-50">
              <td class="px-3 py-2.5">
                <p class="text-xs font-semibold leading-tight text-slate-900">{{ feature.label }}</p>
                <p class="text-xs text-slate-500">{{ feature.description }}</p>
              </td>
              <td v-for="role in permissionRoles" :key="`${feature.key}-${role.role_id}`" class="px-2 py-2.5 text-center">
                <label class="inline-flex items-center justify-center text-sm text-slate-700">
                  <input v-model="role.permissions[feature.key]" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" />
                </label>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <p v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'
import { loadModuleCatalog, type ModuleCatalogItem } from '../services/modules'
import { useLocalizedText } from '../utils/uiText'

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

interface PermissionFeatureGroup {
  key: string
  label: string
  features: FeatureDefinition[]
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
const isPrincipal = computed(() => (currentUser?.role_id ?? 0) === 2 || roleName === 'principal')
const isSdsUser = computed(() => (currentUser?.role_id ?? 0) === 4 || roleName === 'sds user')
const availableModules = ref<ModuleCatalogItem[]>([])

const selectedPermissionSchoolCensusId = ref<number>(getSchoolContextCensusId() ?? 0)
const permissionSchools = ref<OptionRow[]>([])
const permissionFeatures = ref<FeatureDefinition[]>([])
const permissionRoles = ref<PermissionRoleRow[]>([])
const permissionLoading = ref(false)
const permissionSavingRoleId = ref<number | null>(null)
const permissionStorageReady = ref(true)
const permissionError = ref('')
const permissionNotice = ref('')
const text = useLocalizedText({
  en: {
    heroEyebrow: 'School Management Dashboard',
    heroTitle: 'Welcome to the Control Center',
    heroEyebrowAdmin: 'Administration Dashboard',
    heroTitleAdmin: 'System Overview and Controls',
    heroEyebrowPrincipal: 'Principal Dashboard',
    heroTitlePrincipal: 'School Overview and Daily Operations',
    heroEyebrowSdsUser: 'SDS User Dashboard',
    heroTitleSdsUser: 'Student Data and School Operations',
    students: 'Students',
    academicStaff: 'Academic Staff',
    grades: 'Grades',
    classes: 'Classes',
    payments: 'Payments',
    reports: 'Reports',
    yearLabel: 'Year',
    notAvailable: 'N/A',
    allActive: 'All active',
    dataStatus: 'Data Status',
    studentsLastUpdated: 'Students last updated',
    staffLastUpdated: 'Staff last updated',
    availableModules: 'Available Modules',
    noModulesAvailable: 'No modules available.',
    roleFeatureAccess: 'Role Feature Access',
    roleFeatureAccessHelp: 'Select a school first, then set feature access for each role. Users inherit role permissions automatically.',
    school: 'School',
    selectSchool: 'Select school',
    permissionStorageMissing: 'Role permission table is missing. Run backend migrations to save feature permissions.',
    selectSchoolToLoadRoles: 'Select a school to load roles.',
    loadingRoles: 'Loading roles and permissions...',
    noEditableRoles: 'No editable roles found.',
    role: 'Role',
    feature: 'Feature',
    save: 'Save',
    saving: 'Saving...',
    roleNotSet: 'Role not set',
    roleId: 'Role ID',
    dashboardSummaryError: 'Unable to load dashboard summary right now.',
    permissionLoadError: 'Unable to load role feature permissions right now.',
    selectSchoolBeforeSaving: 'Select a school before saving permissions.',
    permissionsUpdatedFor: 'Permissions updated for',
    permissionUpdateError: 'Unable to update role feature permissions.',
  },
  si: {
    heroEyebrow: 'පාසල් කළමනාකරණ පුවරුව',
    heroTitle: 'පාලන මධ්‍යස්ථානයට සාදරයෙන් පිළිගනිමු',
    heroEyebrowAdmin: 'පරිපාලන පුවරුව',
    heroTitleAdmin: 'පද්ධති සාරාංශය සහ පාලන',
    heroEyebrowPrincipal: 'විදුහල්පති පුවරුව',
    heroTitlePrincipal: 'පාසල් සාරාංශය සහ දෛනික මෙහෙයුම්',
    heroEyebrowSdsUser: 'SDS පරිශීලක පුවරුව',
    heroTitleSdsUser: 'සිසු දත්ත සහ පාසල් මෙහෙයුම්',
    students: 'සිසුන්',
    academicStaff: 'ශාස්ත්‍රීය කාර්ය මණ්ඩලය',
    grades: 'ශ්‍රේණි',
    classes: 'පන්ති',
    payments: 'ගෙවීම්',
    reports: 'වාර්තා',
    yearLabel: 'වසර',
    notAvailable: 'නොමැත',
    allActive: 'සියල්ල සක්‍රියයි',
    dataStatus: 'දත්ත තත්ත්වය',
    studentsLastUpdated: 'සිසුන් අවසන් වරට යාවත්කාලීන කළේ',
    staffLastUpdated: 'කාර්ය මණ්ඩලය අවසන් වරට යාවත්කාලීන කළේ',
    availableModules: 'ලභ්‍ය මොඩියුල',
    noModulesAvailable: 'ලභ්‍ය මොඩියුල නොමැත.',
    roleFeatureAccess: 'භූමිකා විශේෂාංග ප්‍රවේශය',
    roleFeatureAccessHelp: 'පළමුව පාසලක් තෝරන්න, ඉන්පසු එක් එක් භූමිකාව සඳහා විශේෂාංග ප්‍රවේශය සකසන්න. පරිශීලකයන්ට භූමිකා අවසර ස්වයංක්‍රීයව හිමිවේ.',
    school: 'පාසල',
    selectSchool: 'පාසල තෝරන්න',
    permissionStorageMissing: 'භූමිකා අවසර වගුව නොමැත. විශේෂාංග අවසර සුරැකීමට backend migrations ධාවනය කරන්න.',
    selectSchoolToLoadRoles: 'භූමිකා පූරණය කිරීමට පාසලක් තෝරන්න.',
    loadingRoles: 'භූමිකා සහ අවසර පූරණය වෙමින් පවතී...',
    noEditableRoles: 'සංස්කරණය කළ හැකි භූමිකා හමු නොවීය.',
    role: 'භූමිකාව',
    feature: 'විශේෂාංගය',
    save: 'සුරකින්න',
    saving: 'සුරකිමින්...',
    roleNotSet: 'භූමිකාව සකසා නැත',
    roleId: 'භූමිකා අංකය',
    dashboardSummaryError: 'දැනට පුවරු සාරාංශය පූරණය කළ නොහැක.',
    permissionLoadError: 'දැනට භූමිකා විශේෂාංග අවසර පූරණය කළ නොහැක.',
    selectSchoolBeforeSaving: 'අවසර සුරැකීමට පෙර පාසලක් තෝරන්න.',
    permissionsUpdatedFor: 'අවසර යාවත්කාලීන කරන ලදී',
    permissionUpdateError: 'භූමිකා විශේෂාංග අවසර යාවත්කාලීන කළ නොහැක.',
  },
  ta: {
    heroEyebrow: 'பள்ளி மேலாண்மை டாஷ்போர்ட்',
    heroTitle: 'கட்டுப்பாட்டு மையத்திற்கு வரவேற்கிறோம்',
    heroEyebrowAdmin: 'நிர்வாக டாஷ்போர்ட்',
    heroTitleAdmin: 'அமைப்பு சுருக்கம் மற்றும் கட்டுப்பாடுகள்',
    heroEyebrowPrincipal: 'அதிபர் டாஷ்போர்ட்',
    heroTitlePrincipal: 'பள்ளி சுருக்கம் மற்றும் தினசரி செயல்பாடுகள்',
    heroEyebrowSdsUser: 'SDS பயனர் டாஷ்போர்ட்',
    heroTitleSdsUser: 'மாணவர் தரவு மற்றும் பள்ளி செயல்பாடுகள்',
    students: 'மாணவர்கள்',
    academicStaff: 'கல்வி பணியாளர்கள்',
    grades: 'தரங்கள்',
    classes: 'வகுப்புகள்',
    payments: 'கட்டணங்கள்',
    reports: 'அறிக்கைகள்',
    yearLabel: 'ஆண்டு',
    notAvailable: 'இல்லை',
    allActive: 'அனைத்தும் செயலில் உள்ளது',
    dataStatus: 'தரவு நிலை',
    studentsLastUpdated: 'மாணவர்கள் கடைசியாக புதுப்பிக்கப்பட்டது',
    staffLastUpdated: 'பணியாளர்கள் கடைசியாக புதுப்பிக்கப்பட்டது',
    availableModules: 'கிடைக்கும் தொகுதிகள்',
    noModulesAvailable: 'கிடைக்கும் தொகுதிகள் இல்லை.',
    roleFeatureAccess: 'பங்கு அம்ச அணுகல்',
    roleFeatureAccessHelp: 'முதலில் ஒரு பள்ளியைத் தேர்ந்தெடுத்து, பின்னர் ஒவ்வொரு பங்கிற்கும் அம்ச அணுகலை அமைக்கவும். பயனர்கள் பங்கு அனுமதிகளை தானாக பெறுவார்கள்.',
    school: 'பள்ளி',
    selectSchool: 'பள்ளியைத் தேர்ந்தெடுக்கவும்',
    permissionStorageMissing: 'பங்கு அனுமதி அட்டவணை இல்லை. அம்ச அனுமதிகளை சேமிக்க backend migrations இயக்கவும்.',
    selectSchoolToLoadRoles: 'பங்குகளை ஏற்ற பள்ளியைத் தேர்ந்தெடுக்கவும்.',
    loadingRoles: 'பங்குகள் மற்றும் அனுமதிகள் ஏற்றப்படுகின்றன...',
    noEditableRoles: 'திருத்தக்கூடிய பங்குகள் எதுவும் இல்லை.',
    role: 'பங்கு',
    feature: 'அம்சம்',
    save: 'சேமி',
    saving: 'சேமிக்கப்படுகிறது...',
    roleNotSet: 'பங்கு அமைக்கப்படவில்லை',
    roleId: 'பங்கு ஐடி',
    dashboardSummaryError: 'இப்போது டாஷ்போர்ட் சுருக்கத்தை ஏற்ற முடியவில்லை.',
    permissionLoadError: 'இப்போது பங்கு அம்ச அனுமதிகளை ஏற்ற முடியவில்லை.',
    selectSchoolBeforeSaving: 'அனுமதிகளை சேமிப்பதற்கு முன் ஒரு பள்ளியைத் தேர்ந்தெடுக்கவும்.',
    permissionsUpdatedFor: 'அனுமதிகள் புதுப்பிக்கப்பட்டது',
    permissionUpdateError: 'பங்கு அம்ச அனுமதிகளை புதுப்பிக்க முடியவில்லை.',
  },
})

const heroEyebrow = computed(() => {
  if (isAdmin.value) return text.value.heroEyebrowAdmin
  if (isPrincipal.value) return text.value.heroEyebrowPrincipal
  if (isSdsUser.value) return text.value.heroEyebrowSdsUser
  return text.value.heroEyebrow
})

const heroTitle = computed(() => {
  if (isAdmin.value) return text.value.heroTitleAdmin
  if (isPrincipal.value) return text.value.heroTitlePrincipal
  if (isSdsUser.value) return text.value.heroTitleSdsUser
  return text.value.heroTitle
})

const moduleLabelMap = computed<Record<string, string>>(() => ({
  students: text.value.students,
  grades: text.value.grades,
  classes: text.value.classes,
  staff: text.value.academicStaff,
  payments: text.value.payments,
  reports: text.value.reports,
}))

const availableModuleLabels = computed(() => {
  return availableModules.value.map((module) => moduleLabelMap.value[module.key] ?? module.label)
})

const permissionFeatureGroups = computed<PermissionFeatureGroup[]>(() => {
  const labels: Record<string, string> = {
    student: text.value.students,
    grade: text.value.grades,
    class: text.value.classes,
    staff: text.value.academicStaff,
    payment: text.value.payments,
    report: text.value.reports,
  }

  const groups = new Map<string, PermissionFeatureGroup>()

  for (const feature of permissionFeatures.value) {
    const groupKey = feature.key.split('.')[0] ?? 'other'
    if (!groups.has(groupKey)) {
      groups.set(groupKey, {
        key: groupKey,
        label: labels[groupKey] ?? groupKey,
        features: [],
      })
    }

    groups.get(groupKey)!.features.push(feature)
  }

  return Array.from(groups.values())
})

const extractApiMessage = (reason: unknown): string => {
  if (typeof reason === 'object' && reason !== null && 'response' in reason) {
    const response = (reason as { response?: { data?: { message?: string } } }).response
    if (typeof response?.data?.message === 'string' && response.data.message.trim() !== '') {
      return response.data.message
    }
  }

  return ''
}

const buildPermissionContextRequestConfig = (): { params?: Record<string, string>; headers?: Record<string, string> } | undefined => {
  const selected = Number(selectedPermissionSchoolCensusId.value)
  if (!Number.isFinite(selected) || selected <= 0) {
    return undefined
  }

  const censusValue = String(selected)
  return {
    params: { school_census_id: censusValue },
    headers: { 'X-School-Census-Id': censusValue },
  }
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
    error.value = text.value.dashboardSummaryError ?? 'Unable to load dashboard summary right now.'
  }
}

const loadDashboardModules = async (): Promise<void> => {
  availableModules.value = await loadModuleCatalog()
}

const loadPermissionMatrix = async (): Promise<void> => {
  if (!isAdmin.value) {
    return
  }

  permissionLoading.value = true
  permissionError.value = ''
  permissionNotice.value = ''

  try {
    const contextConfig = buildPermissionContextRequestConfig()
    const { data } = await api.get<PermissionResponse>('/admin/feature-permissions', contextConfig)

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

    const selected = Number(selectedPermissionSchoolCensusId.value)
    if (selected > 0) {
      const hasSchoolInList = permissionSchools.value.some((schoolOption) => schoolOption.id === selected)
      if (!hasSchoolInList) {
        selectedPermissionSchoolCensusId.value = 0
        setSchoolContextCensusId(null)
        permissionRoles.value = []
      }
    }
  } catch (reason) {
    permissionRoles.value = []
    permissionStorageReady.value = false
    permissionError.value = extractApiMessage(reason) || text.value.permissionLoadError
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
    permissionError.value = text.value.selectSchoolBeforeSaving
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
      : `${text.value.permissionsUpdatedFor} ${role.role_name || `${text.value.role.toLowerCase()} ${role.role_id}`}.`
  } catch (reason) {
    permissionError.value = extractApiMessage(reason) || text.value.permissionUpdateError
  } finally {
    permissionSavingRoleId.value = null
  }
}

const formatDate = (value: string | null): string => {
  if (!value) return text.value.notAvailable

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return date.toLocaleString()
}

onMounted(async () => {
  await loadSummary()
  await loadDashboardModules()

  if (isAdmin.value) {
    await loadPermissionMatrix()
  }
})
</script>

