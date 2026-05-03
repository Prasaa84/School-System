<template>
  <section v-if="activeTab === 'view'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><h2 class="font-display text-xl font-bold">{{ text.staff }}</h2><div class="flex gap-2"><input :value="staffSearch" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" :placeholder="text.searchPlaceholder" @input="onSearchInput" @keyup.enter="$emit('load-staff', 1)" /><button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('load-staff', 1)">{{ text.view }}</button></div></div>
    <div class="overflow-auto rounded-xl border border-slate-200"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">{{ text.id }}</th><th class="px-3 py-2 text-left">{{ text.name }}</th><th class="px-3 py-2 text-left">{{ text.nic }}</th><th class="px-3 py-2 text-left">{{ text.phone }}</th><th class="px-3 py-2 text-left">{{ text.designation }}</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="row in staffRows" :key="row.stf_id"><td class="px-3 py-2">{{ row.stf_id }}</td><td class="px-3 py-2">{{ row.name_with_ini }}</td><td class="px-3 py-2">{{ row.nic_no || '-' }}</td><td class="px-3 py-2">{{ row.phone_mobile1 || '-' }}</td><td class="px-3 py-2">{{ row.designation || '-' }}</td></tr></tbody></table></div>
    <div class="mt-4 flex items-center justify-between text-sm text-slate-600"><span>{{ text.total }}: {{ staffMeta.total }}</span><div class="flex gap-2"><button class="rounded border px-3 py-1" :disabled="staffMeta.current_page <= 1" @click="$emit('load-staff', staffMeta.current_page - 1)">{{ text.prev }}</button><span>{{ text.page }} {{ staffMeta.current_page }} / {{ staffMeta.last_page }}</span><button class="rounded border px-3 py-1" :disabled="staffMeta.current_page >= staffMeta.last_page" @click="$emit('load-staff', staffMeta.current_page + 1)">{{ text.next }}</button></div></div>
  </section>

  <section v-if="activeTab === 'reports'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><h2 class="font-display text-xl font-bold">{{ text.staffReports }}</h2><div class="flex gap-2"><select :value="reportYear" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onReportYearChange"><option :value="0">{{ text.allYears }}</option><option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option></select><select :value="reportMonth" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onReportMonthChange"><option :value="0">{{ text.allMonths }}</option><option v-for="month in 12" :key="month" :value="month">{{ month }}</option></select><button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('load-report')">{{ text.view }}</button></div></div>
    <div class="grid gap-4 md:grid-cols-3"><article class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">{{ text.totalStaff }}</p><p class="mt-2 text-2xl font-bold">{{ staffSummary.total_staff }}</p></article><article class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">{{ text.updated }}</p><p class="mt-2 text-2xl font-bold text-emerald-700">{{ staffSummary.updated_staff }}</p></article><article class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs text-slate-500">{{ text.notUpdated }}</p><p class="mt-2 text-2xl font-bold text-rose-700">{{ staffSummary.not_updated_staff }}</p></article></div>
  </section>
</template>

<script setup lang="ts">
import { useLocalizedText } from '../../utils/uiText'

interface StaffRow { stf_id: number; name_with_ini: string; nic_no: string | null; phone_mobile1: string | null; designation: string | null }
interface StaffMeta { current_page: number; per_page: number; total: number; last_page: number }
interface StaffSummary { total_staff: number; updated_staff: number; not_updated_staff: number }

const props = defineProps<{
  activeTab: 'view' | 'reports'
  staffSearch: string
  staffRows: StaffRow[]
  staffMeta: StaffMeta
  reportYear: number
  reportMonth: number
  yearOptions: number[]
  staffSummary: StaffSummary
}>()

const emit = defineEmits<{
  (e: 'update:staff-search', value: string): void
  (e: 'update:report-year', year: number): void
  (e: 'update:report-month', month: number): void
  (e: 'load-staff', page: number): void
  (e: 'load-report'): void
}>()

const text = useLocalizedText({
  en: {
    staff: 'Staff',
    searchPlaceholder: 'Search by NIC or Name',
    view: 'View',
    id: 'ID',
    name: 'Name',
    nic: 'NIC',
    phone: 'Phone',
    designation: 'Designation',
    total: 'Total',
    prev: 'Prev',
    page: 'Page',
    next: 'Next',
    staffReports: 'Staff Reports',
    allYears: 'All Years',
    allMonths: 'All Months',
    totalStaff: 'Total Staff',
    updated: 'Updated',
    notUpdated: 'Not Updated',
  },
  si: {
    staff: 'කාර්ය මණ්ඩලය',
    searchPlaceholder: 'NIC හෝ නම අනුව සොයන්න',
    view: 'දර්ශනය',
    id: 'අංකය',
    name: 'නම',
    nic: 'NIC',
    phone: 'දුරකථන',
    designation: 'තනතුර',
    total: 'එකතුව',
    prev: 'පෙර',
    page: 'පිටුව',
    next: 'ඊළඟ',
    staffReports: 'කාර්ය මණ්ඩල වාර්තා',
    allYears: 'සියලු වසර',
    allMonths: 'සියලු මාස',
    totalStaff: 'මුළු කාර්ය මණ්ඩලය',
    updated: 'යාවත්කාලීන',
    notUpdated: 'යාවත්කාලීන නොකළ',
  },
  ta: {
    staff: 'பணியாளர்கள்',
    searchPlaceholder: 'NIC அல்லது பெயரால் தேடவும்',
    view: 'பார்வை',
    id: 'ஐடி',
    name: 'பெயர்',
    nic: 'NIC',
    phone: 'தொலைபேசி',
    designation: 'பதவி',
    total: 'மொத்தம்',
    prev: 'முந்தைய',
    page: 'பக்கம்',
    next: 'அடுத்து',
    staffReports: 'பணியாளர் அறிக்கைகள்',
    allYears: 'அனைத்து ஆண்டுகள்',
    allMonths: 'அனைத்து மாதங்கள்',
    totalStaff: 'மொத்த பணியாளர்கள்',
    updated: 'புதுப்பிக்கப்பட்டது',
    notUpdated: 'புதுப்பிக்கப்படவில்லை',
  },
})

const onSearchInput = (event: Event): void => {
  emit('update:staff-search', (event.target as HTMLInputElement).value)
}

const onReportYearChange = (event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:report-year', Number.isFinite(value) ? value : 0)
}

const onReportMonthChange = (event: Event): void => {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:report-month', Number.isFinite(value) ? value : 0)
}
</script>
