<template>
  <section v-if="activeTab === 'view'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <h2 class="font-display text-xl font-bold">{{ text.staff }}</h2>

      <div class="flex flex-wrap gap-2">
        <select
          v-if="isAdmin"
          :value="selectedSchoolCensusId"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
          @change="onSchoolChange"
        >
          <option :value="0">{{ text.allSchools }}</option>
          <option v-for="row in staffSchools" :key="row.id" :value="row.id">{{ row.label }}</option>
        </select>
        <input
          :value="staffSearch"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
          :placeholder="text.searchPlaceholder"
          @input="onSearchInput"
          @keyup.enter="$emit('load-staff', 1)"
        />
        <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('load-staff', 1)">
          {{ text.view }}
        </button>
        <button v-if="canManage" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('open-add-staff')">
          {{ text.addStaff }}
        </button>
      </div>
    </div>

    <div class="overflow-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-3 py-2 text-left">{{ text.id }}</th>
            <th class="px-3 py-2 text-left">{{ text.name }}</th>
            <th class="px-3 py-2 text-left">{{ text.nic }}</th>
            <th class="px-3 py-2 text-left">{{ text.gender }}</th>
            <th class="px-3 py-2 text-left">{{ text.phone }}</th>
            <th class="px-3 py-2 text-left">{{ text.designation }}</th>
            <th v-if="isAdmin" class="px-3 py-2 text-left">{{ text.school }}</th>
            <th v-if="canManage" class="px-3 py-2 text-left">{{ text.actions }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in staffRows" :key="row.stf_id">
            <td class="px-3 py-2">{{ row.stf_id }}</td>
            <td class="px-3 py-2">{{ row.name_with_ini }}</td>
            <td class="px-3 py-2">{{ row.nic_no || '-' }}</td>
            <td class="px-3 py-2">{{ row.gender || '-' }}</td>
            <td class="px-3 py-2">{{ row.phone_mobile1 || '-' }}</td>
            <td class="px-3 py-2">{{ row.designation || '-' }}</td>
            <td v-if="isAdmin" class="px-3 py-2">{{ row.school_name || '-' }}</td>
            <td v-if="canManage" class="px-3 py-2">
              <button
                class="rounded bg-cyan-600 px-3 py-1 text-xs font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="loadingEditStaffId === row.stf_id"
                @click="$emit('open-edit-staff', row)"
              >
                {{ text.edit }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
      <span>{{ text.total }}: {{ staffMeta.total }}</span>
      <div class="flex gap-2">
        <button class="rounded border px-3 py-1" :disabled="staffMeta.current_page <= 1" @click="$emit('load-staff', staffMeta.current_page - 1)">
          {{ text.prev }}
        </button>
        <span>{{ text.page }} {{ staffMeta.current_page }} / {{ staffMeta.last_page }}</span>
        <button class="rounded border px-3 py-1" :disabled="staffMeta.current_page >= staffMeta.last_page" @click="$emit('load-staff', staffMeta.current_page + 1)">
          {{ text.next }}
        </button>
      </div>
    </div>
  </section>

  <section v-if="activeTab === 'reports'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
      <div>
        <h2 class="font-display text-xl font-bold">{{ text.staffReports }}</h2>
        <p class="text-sm text-slate-500">{{ text.staffReportHelp }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('reset-report-filters')">
          {{ text.reset }}
        </button>
        <button class="rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white" @click="$emit('load-report')">
          {{ text.view }}
        </button>
      </div>
    </div>

    <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
      <label v-if="isAdmin && !isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.school }}
        <select :value="reportFilters.school_census_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('school_census_id', $event)">
          <option :value="0">{{ text.allSchools }}</option>
          <option v-for="row in staffSchools" :key="`report-school-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label class="min-w-0 text-[11px] text-slate-700">
        {{ text.search }}
        <input :value="reportFilters.q" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" :placeholder="text.searchPlaceholder" @input="onReportTextChange('q', $event)" />
      </label>

      <label class="min-w-0 text-[11px] text-slate-700">
        {{ text.grade }}
        <select :value="reportFilters.grade_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('grade_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in reportGrades" :key="`report-grade-filter-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label class="min-w-0 text-[11px] text-slate-700">
        {{ text.class }}
        <select :value="reportFilters.class_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('class_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in filteredReportClasses" :key="`report-class-filter-${row.grade_id}-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.gender }}
        <select :value="reportFilters.gender_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('gender_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in genders" :key="`report-gender-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.religion }}
        <select :value="reportFilters.religion_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('religion_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in religions" :key="`report-religion-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.civilStatus }}
        <select :value="reportFilters.civil_status_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('civil_status_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in civilStatuses" :key="`report-civil-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.ethnicGroup }}
        <select :value="reportFilters.ethnic_group_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('ethnic_group_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in ethnicGroups" :key="`report-ethnic-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.educationLevel }}
        <select :value="reportFilters.edu_q_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('edu_q_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in educationLevels" :key="`report-edu-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.professionalLevel }}
        <select :value="reportFilters.prof_q_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('prof_q_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in professionalLevels" :key="`report-prof-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.designation }}
        <select :value="reportFilters.desig_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('desig_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in designations" :key="`report-desig-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.staffType }}
        <select :value="reportFilters.stf_type_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('stf_type_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in staffTypes" :key="`report-type-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.staffStatus }}
        <select :value="reportFilters.stf_status_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('stf_status_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in staffStatuses" :key="`report-status-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.serviceGrade }}
        <select :value="reportFilters.serv_grd_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('serv_grd_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in serviceGrades" :key="`report-grade-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.serviceStatus }}
        <select :value="reportFilters.service_status_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('service_status_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in serviceStatuses" :key="`report-service-status-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.section }}
        <select :value="reportFilters.sec_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('sec_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in sections" :key="`report-section-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.sectionRole }}
        <select :value="reportFilters.sec_role_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('sec_role_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in sectionRoles" :key="`report-section-role-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>

      <label v-if="!isSdsUser" class="min-w-0 text-[11px] text-slate-700">
        {{ text.subjectMedium }}
        <select :value="reportFilters.subj_med_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" @change="onReportNumberChange('subj_med_id', $event)">
          <option :value="0">{{ text.all }}</option>
          <option v-for="row in subjectMediums" :key="`report-medium-${row.id}`" :value="row.id">{{ row.label }}</option>
        </select>
      </label>
    </div>

    <div class="mt-5 flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
      <p class="text-sm text-slate-600">{{ text.totalMatches }}</p>
      <p class="text-lg font-semibold text-slate-900">{{ reportRows.length }}</p>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600">
          <tr>
            <th class="px-4 py-3">{{ text.id }}</th>
            <th class="px-4 py-3">{{ text.name }}</th>
            <th v-if="!isSdsUser" class="px-4 py-3">{{ text.nic }}</th>
            <th class="px-4 py-3">{{ text.gender }}</th>
            <th class="px-4 py-3">{{ text.phone }}</th>
            <th class="px-4 py-3">{{ text.designation }}</th>
            <th v-if="isAdmin && !isSdsUser" class="px-4 py-3">{{ text.school }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="reportRows.length === 0">
            <td class="px-4 py-4 text-slate-500" :colspan="isAdmin && !isSdsUser ? 7 : isSdsUser ? 4 : 6">{{ text.noStaffFound }}</td>
          </tr>
          <tr v-for="row in reportRows" :key="`report-row-${row.stf_id}`">
            <td class="px-4 py-3 font-medium text-slate-700">{{ row.stf_id }}</td>
            <td class="px-4 py-3">{{ row.name_with_ini }}</td>
            <td v-if="!isSdsUser" class="px-4 py-3">{{ row.nic_no || '-' }}</td>
            <td class="px-4 py-3">{{ row.gender || '-' }}</td>
            <td class="px-4 py-3">{{ row.phone_mobile1 || '-' }}</td>
            <td class="px-4 py-3">{{ row.designation || '-' }}</td>
            <td v-if="isAdmin && !isSdsUser" class="px-4 py-3">{{ row.school_name || '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useLocalizedText } from '../../utils/uiText'

interface StaffRow {
  stf_id: number
  census_id: string | null
  name_with_ini: string
  nic_no: string | null
  gender: string | null
  phone_mobile1: string | null
  designation: string | null
  school_name: string | null
}
interface StaffMeta { current_page: number; per_page: number; total: number; last_page: number }
interface OptionRow { id: number; label: string; grade_id?: number }
interface StaffReportFilters {
  q: string
  school_census_id: number
  grade_id: number
  class_id: number
  gender_id: number
  civil_status_id: number
  ethnic_group_id: number
  religion_id: number
  edu_q_id: number
  prof_q_id: number
  desig_id: number
  serv_grd_id: number
  sec_id: number
  sec_role_id: number
  stf_type_id: number
  stf_status_id: number
  service_status_id: number
  subj_med_id: number
}

const props = defineProps<{
  activeTab: 'view' | 'reports'
  isAdmin: boolean
  isSdsUser: boolean
  canManage: boolean
  staffSearch: string
  selectedSchoolCensusId: number
  staffSchools: OptionRow[]
  staffRows: StaffRow[]
  staffMeta: StaffMeta
  reportFilters: StaffReportFilters
  reportRows: StaffRow[]
  genders: OptionRow[]
  civilStatuses: OptionRow[]
  ethnicGroups: OptionRow[]
  religions: OptionRow[]
  educationLevels: OptionRow[]
  professionalLevels: OptionRow[]
  designations: OptionRow[]
  serviceGrades: OptionRow[]
  reportGrades: OptionRow[]
  reportClasses: OptionRow[]
  sections: OptionRow[]
  sectionRoles: OptionRow[]
  staffTypes: OptionRow[]
  staffStatuses: OptionRow[]
  serviceStatuses: OptionRow[]
  subjectMediums: OptionRow[]
  loadingEditStaffId: number | null
}>()

const emit = defineEmits<{
  (e: 'update:staff-search', value: string): void
  (e: 'update:selected-school-census-id', value: number): void
  (e: 'update:report-filters', value: Partial<StaffReportFilters>): void
  (e: 'load-staff', page: number): void
  (e: 'load-report'): void
  (e: 'reset-report-filters'): void
  (e: 'open-add-staff'): void
  (e: 'open-edit-staff', row: StaffRow): void
}>()

const text = useLocalizedText({
  en: {
    staff: 'Staff',
    allSchools: 'All Schools',
    searchPlaceholder: 'Search by NIC or Name',
    view: 'View',
    addStaff: 'Add Staff',
    edit: 'Edit',
    actions: 'Actions',
    id: 'ID',
    name: 'Name',
    nic: 'NIC',
    gender: 'Male/Female',
    phone: 'Phone',
    designation: 'Designation',
    school: 'School',
    search: 'Search',
    grade: 'Grade',
    class: 'Class',
    all: 'All',
    total: 'Total',
    prev: 'Prev',
    page: 'Page',
    next: 'Next',
    staffReports: 'Staff Reports',
    staffReportHelp: 'Filter staff by school, profile details, and work attributes.',
    reset: 'Reset',
    religion: 'Religion',
    civilStatus: 'Civil Status',
    ethnicGroup: 'Ethnic Group',
    educationLevel: 'Educational Level',
    professionalLevel: 'Professional Level',
    staffType: 'Staff Type',
    staffStatus: 'Staff Status',
    serviceGrade: 'Service Grade',
    serviceStatus: 'Service Status',
    section: 'Section',
    sectionRole: 'Section Role',
    subjectMedium: 'Subject Medium',
    totalMatches: 'Matching Staff',
    noStaffFound: 'No staff matched the selected filters.',
  },
  si: {
    staff: 'කාර්ය මණ්ඩලය',
    allSchools: 'සියලු පාසල්',
    searchPlaceholder: 'NIC හෝ නම අනුව සොයන්න',
    view: 'දර්ශනය',
    addStaff: 'කාර්ය මණ්ඩලය එක් කරන්න',
    edit: 'සංස්කරණය',
    actions: 'ක්‍රියා',
    id: 'අංකය',
    name: 'නම',
    nic: 'NIC',
    gender: 'ස්ත්‍රී/පුරුෂ',
    phone: 'දුරකථන',
    designation: 'තනතුර',
    school: 'පාසල',
    search: 'සෙවීම',
    grade: 'ශ්‍රේණිය',
    class: 'පන්තිය',
    all: 'සියල්ල',
    total: 'එකතුව',
    prev: 'පෙර',
    page: 'පිටුව',
    next: 'ඊළඟ',
    staffReports: 'කාර්ය මණ්ඩල වාර්තා',
    staffReportHelp: 'පාසල, පුද්ගලික තොරතුරු සහ සේවා තොරතුරු අනුව කාර්ය මණ්ඩලය පෙරහන් කරන්න.',
    reset: 'යළි සකසන්න',
    religion: 'ආගම',
    civilStatus: 'විවාහක තත්ත්වය',
    ethnicGroup: 'ජාතික කණ්ඩායම',
    educationLevel: 'අධ්‍යාපන මට්ටම',
    professionalLevel: 'වෘත්තීය මට්ටම',
    staffType: 'කාර්ය මණ්ඩල වර්ගය',
    staffStatus: 'කාර්ය මණ්ඩල තත්ත්වය',
    serviceGrade: 'සේවා ශ්‍රේණිය',
    serviceStatus: 'සේවා තත්ත්වය',
    section: 'අංශය',
    sectionRole: 'අංශ භූමිකාව',
    subjectMedium: 'විෂය මාධ්‍යය',
    totalMatches: 'ගැළපෙන කාර්ය මණ්ඩලය',
    noStaffFound: 'තෝරාගත් පෙරහන් සඳහා කාර්ය මණ්ඩලයක් සොයාගත නොහැකි විය.',
  },
  ta: {
    staff: 'பணியாளர்கள்',
    allSchools: 'அனைத்து பாடசாலைகள்',
    searchPlaceholder: 'NIC அல்லது பெயரால் தேடவும்',
    view: 'பார்வை',
    addStaff: 'பணியாளர் சேர்க்கவும்',
    edit: 'திருத்து',
    actions: 'செயல்கள்',
    id: 'ஐடி',
    name: 'பெயர்',
    nic: 'NIC',
    gender: 'ஆண்/பெண்',
    phone: 'தொலைபேசி',
    designation: 'பதவி',
    school: 'பாடசாலை',
    search: 'தேடல்',
    grade: 'தரம்',
    class: 'வகுப்பு',
    all: 'அனைத்தும்',
    total: 'மொத்தம்',
    prev: 'முந்தைய',
    page: 'பக்கம்',
    next: 'அடுத்து',
    staffReports: 'பணியாளர் அறிக்கைகள்',
    staffReportHelp: 'பாடசாலை, தனிப்பட்ட விவரங்கள் மற்றும் சேவை விவரங்களின்படி பணியாளர்களை வடிகட்டவும்.',
    reset: 'மீட்டமை',
    religion: 'மதம்',
    civilStatus: 'திருமண நிலை',
    ethnicGroup: 'இனக்குழு',
    educationLevel: 'கல்வி நிலை',
    professionalLevel: 'தொழில்முறை நிலை',
    staffType: 'பணியாளர் வகை',
    staffStatus: 'பணியாளர் நிலை',
    serviceGrade: 'சேவை தரம்',
    serviceStatus: 'சேவை நிலை',
    section: 'பிரிவு',
    sectionRole: 'பிரிவு பங்கு',
    subjectMedium: 'பாட மொழிமூலம்',
    totalMatches: 'பொருந்திய பணியாளர்கள்',
    noStaffFound: 'தேர்ந்தெடுத்த வடிகட்டல்களுக்கு பொருந்தும் பணியாளர்கள் இல்லை.',
  },
})

const filteredReportClasses = computed(() => {
  const selectedGradeId = Number(props.reportFilters.grade_id)
  if (selectedGradeId <= 0) {
    return props.reportClasses
  }

  return props.reportClasses.filter((row) => Number(row.grade_id ?? 0) === selectedGradeId)
})

const onSearchInput = (event: Event): void => {
  emit('update:staff-search', (event.target as HTMLInputElement).value)
}

const onSchoolChange = (event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:selected-school-census-id', Number.isFinite(value) ? value : 0)
}

const onReportTextChange = (key: keyof StaffReportFilters, event: Event): void => {
  emit('update:report-filters', { [key]: (event.target as HTMLInputElement).value } as Partial<StaffReportFilters>)
}

const onReportNumberChange = (key: keyof StaffReportFilters, event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:report-filters', { [key]: Number.isFinite(value) ? value : 0 } as Partial<StaffReportFilters>)
}
</script>
