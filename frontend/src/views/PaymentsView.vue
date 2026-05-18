<template>
  <div class="space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h1 class="font-display text-2xl font-bold text-slate-900">{{ text.title }}</h1>
      <p class="mt-2 text-sm text-slate-600">{{ text.subtitle }}</p>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="grid gap-4 md:grid-cols-3">
          <label v-if="isAdmin" class="text-sm text-slate-700 md:col-span-3">
            {{ text.school }}
            <select v-model.number="selectedSchoolCensusId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onSchoolChange">
              <option :value="0">{{ text.selectSchool }}</option>
              <option v-for="row in schools" :key="row.id" :value="row.id">{{ row.label }}</option>
            </select>
          </label>

          <label class="text-sm text-slate-700 md:col-span-2">
            {{ text.studentAdmissionNo }}
            <input
              v-model="studentIndexNo"
              type="text"
              maxlength="5"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2"
              :placeholder="text.studentAdmissionPlaceholder"
              :disabled="lookupDisabled"
              @keyup.enter="lookupStudent"
            />
          </label>

          <div class="flex items-end">
            <button
              class="w-full rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="lookupDisabled || lookingUpStudent"
              @click="lookupStudent"
            >
              {{ lookingUpStudent ? text.searching : text.searchStudent }}
            </button>
          </div>
      </div>

      <p v-if="!student && message" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</p>
      <p v-if="!student && errorMessage" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>
    </section>

    <section v-if="student" class="space-y-6">
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p v-if="message" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</p>
        <p v-if="errorMessage" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>

        <div class="mb-4 flex items-center justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.paymentHistory }}</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-slate-900">{{ text.paymentHistoryTitle }}</h2>
          </div>
          <div class="flex items-center gap-3">
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ paymentRows.length }}</span>
            <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" @click="openAddPaymentDialog">
              {{ text.addPayment }}
            </button>
          </div>
        </div>

        <div class="overflow-auto rounded-xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.numberLabel }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.admissionLabel }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.nameLabel }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.invoiceNo }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.year }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.annualFee }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.memberFee }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.total }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.paidDate }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-if="paymentRows.length === 0">
                <td colspan="9" class="px-3 py-6 text-center text-slate-500">{{ text.noPayments }}</td>
              </tr>
              <tr v-for="(row, index) in paymentRows" :key="`payment-row-${row.id}`" class="hover:bg-slate-50">
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ index + 1 }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ student.index_no }}</td>
                <td class="px-3 py-2 text-slate-700 max-w-[220px] truncate" :title="student.name_with_initials || student.fullname">{{ student.name_with_initials || student.fullname }}</td>
                <td class="px-3 py-2 font-medium text-slate-900 whitespace-nowrap">{{ row.invoice_no }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.year }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ formatAmount(row.annual_fee) }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">
                  {{ row.include_member_fee ? formatAmount(row.member_fee) : text.notIncluded }}
                </td>
                <td class="px-3 py-2 font-semibold text-slate-900 whitespace-nowrap">{{ formatAmount(row.total) }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ formatDate(row.paid_date) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </section>

    <div v-if="showAddPaymentDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeAddPaymentDialog">
      <section class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-display text-xl font-bold text-slate-900">{{ text.addPaymentDialogTitle }}</h2>
          <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeAddPaymentDialog">{{ text.close }}</button>
        </div>

        <p v-if="paymentDialogError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {{ paymentDialogError }}
        </p>

        <div class="grid gap-4 md:grid-cols-2">
          <label class="text-sm text-slate-700">
            {{ text.studentAdmissionNo }}
            <input :value="student?.index_no ?? ''" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
          </label>

          <label class="text-sm text-slate-700">
            {{ text.studentName }}
            <input :value="student ? (student.name_with_initials || student.fullname) : ''" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
          </label>

          <label class="text-sm text-slate-700">
            {{ text.invoiceNo }}
            <input v-model="paymentForm.invoice_no" type="text" maxlength="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
          </label>

          <label class="text-sm text-slate-700">
            {{ text.year }}
            <select v-model.number="paymentForm.year" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
              <option :value="0">{{ text.selectYear }}</option>
              <option v-for="year in yearOptions" :key="`payment-year-dialog-${year}`" :value="year">{{ year }}</option>
            </select>
          </label>

          <label class="text-sm text-slate-700">
            {{ text.annualFee }}
            <input :value="formatAmount(feeDetails?.annual_fee ?? 0)" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
          </label>

          <label class="text-sm text-slate-700">
            {{ text.memberFee }}
            <input :value="formatAmount(feeDetails?.member_fee ?? 0)" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
          </label>

          <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 md:col-span-2">
            <input v-model="paymentForm.include_member_fee" type="checkbox" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" />
            <span>{{ text.includeMemberFee }}</span>
          </label>

          <label class="text-sm text-slate-700 md:col-span-2">
            {{ text.total }}
            <input :value="formatAmount(calculatedTotal)" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-900" readonly />
          </label>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeAddPaymentDialog">
            {{ text.cancel }}
          </button>
          <button
            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="savingPayment || !canSubmitPayment"
            @click="savePayment"
          >
            {{ savingPayment ? text.saving : text.addPayment }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'
import { useUiStore } from '../stores/ui'

interface OptionRow {
  id: number
  label: string
}

interface PaymentStudent {
  std_id: number
  index_no: string
  fullname: string
  name_with_initials: string
  census_id: string
  school_name: string
}

interface PaymentRow {
  id: number
  invoice_no: string
  year: number
  include_member_fee: boolean
  total: number
  paid_date: string
  annual_fee: number
  member_fee: number
}

interface FeeDetails {
  year: number
  annual_fee: number
  member_fee: number
}

interface PaymentOptionsResponse {
  schools?: OptionRow[]
  years?: number[]
}

interface PaymentStudentResponse {
  student?: PaymentStudent | null
  payments?: PaymentRow[]
}

const ui = useUiStore()
const currentUser = getUser()
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')

const text = computed(() => {
  if (ui.language === 'si') {
    return {
      title: 'SDS ගෙවීම්',
      subtitle: 'සිසුන්ගේ SDS ගෙවීම් සොයන්න, වාර්ෂික ගාස්තු පරීක්ෂා කරන්න, සහ නව ගෙවීම් සටහන් කරන්න.',
      school: 'පාසල',
      selectSchool: 'පාසල තෝරන්න',
      studentAdmissionNo: 'ඇතුළත් අංකය',
      studentAdmissionPlaceholder: 'ඇතුළත් අංකය ඇතුළත් කරන්න',
      searchStudent: 'සිසුවා සොයන්න',
      searching: 'සොයමින්...',
      studentDetails: 'සිසු විස්තර',
      numberLabel: '#',
      nameLabel: 'නම',
      admissionLabel: 'ඇතුළත් අංකය',
      studentName: 'සිසුවාගේ නම',
      invoiceNo: 'ඉන්වොයිස් අංකය',
      year: 'වර්ෂය',
      selectYear: 'වර්ෂය තෝරන්න',
      annualFee: 'වාර්ෂික ගාස්තුව',
      memberFee: 'සාමාජික ගාස්තුව',
      includeMemberFee: 'සාමාජික ගාස්තුව එකතු කරන්න',
      total: 'මුළු මුදල',
      addPayment: 'ගෙවීම එක් කරන්න',
      saving: 'සුරකිමින්...',
      paymentHistory: 'ගෙවීම් ඉතිහාසය',
      paymentHistoryTitle: 'සිසුවාගේ ගෙවීම්',
      addPaymentDialogTitle: 'ගෙවීම එක් කරන්න',
      paidDate: 'ගෙවූ දිනය',
      noPayments: 'මෙම සිසුවා සඳහා ගෙවීම් හමු නොවීය.',
      notIncluded: 'එකතු කර නැත',
      paymentAdded: 'ගෙවීම සාර්ථකව එක් කරන ලදී.',
      selectStudentFirst: 'පළමුව සිසුවෙකු තෝරන්න.',
      unableToLoadPayments: 'ගෙවීම් දත්ත පූරණය කළ නොහැක.',
      unableToLoadFee: 'මෙම වර්ෂය සඳහා ගාස්තු හමු නොවීය.',
      invoiceRequired: 'ඉන්වොයිස් අංකය අවශ්‍යය.',
      yearRequired: 'වර්ෂය අවශ්‍යය.',
      selectSchoolFirst: 'පළමුව පාසලක් තෝරන්න.',
      close: 'වසන්න',
      cancel: 'අවලංගු කරන්න',
    }
  }

  if (ui.language === 'ta') {
    return {
      title: 'SDS கட்டணங்கள்',
      subtitle: 'மாணவர் SDS கட்டணங்களைத் தேடவும், வருடாந்திர கட்டணங்களை பார்க்கவும், புதிய கட்டணங்களை பதிவு செய்யவும்.',
      school: 'பாடசாலை',
      selectSchool: 'பாடசாலையைத் தேர்ந்தெடுக்கவும்',
      studentAdmissionNo: 'அனுமதி இலக்கம்',
      studentAdmissionPlaceholder: 'அனுமதி இலக்கத்தை உள்ளிடவும்',
      searchStudent: 'மாணவரை தேடுக',
      searching: 'தேடப்படுகிறது...',
      studentDetails: 'மாணவர் விவரங்கள்',
      numberLabel: '#',
      nameLabel: 'பெயர்',
      admissionLabel: 'அனுமதி இலக்கம்',
      studentName: 'மாணவர் பெயர்',
      invoiceNo: 'விலைப்பட்டியல் எண்',
      year: 'ஆண்டு',
      selectYear: 'ஆண்டைத் தேர்ந்தெடுக்கவும்',
      annualFee: 'வருடாந்திர கட்டணம்',
      memberFee: 'உறுப்பினர் கட்டணம்',
      includeMemberFee: 'உறுப்பினர் கட்டணத்தை சேர்க்கவும்',
      total: 'மொத்தம்',
      addPayment: 'கட்டணம் சேர்க்கவும்',
      saving: 'சேமிக்கப்படுகிறது...',
      paymentHistory: 'கட்டண வரலாறு',
      paymentHistoryTitle: 'மாணவர் கட்டணங்கள்',
      addPaymentDialogTitle: 'கட்டணம் சேர்க்கவும்',
      paidDate: 'செலுத்திய தேதி',
      noPayments: 'இந்த மாணவருக்கான கட்டணங்கள் இல்லை.',
      notIncluded: 'சேர்க்கப்படவில்லை',
      paymentAdded: 'கட்டணம் வெற்றிகரமாக சேர்க்கப்பட்டது.',
      selectStudentFirst: 'முதலில் ஒரு மாணவரைத் தேர்ந்தெடுக்கவும்.',
      unableToLoadPayments: 'கட்டண தரவை ஏற்ற முடியவில்லை.',
      unableToLoadFee: 'இந்த ஆண்டிற்கு கட்டணங்கள் இல்லை.',
      invoiceRequired: 'விலைப்பட்டியல் எண் தேவை.',
      yearRequired: 'ஆண்டு தேவை.',
      selectSchoolFirst: 'முதலில் ஒரு பாடசாலையைத் தேர்ந்தெடுக்கவும்.',
      close: 'மூடு',
      cancel: 'ரத்து செய்',
    }
  }

  return {
    title: 'SDS Payments',
    subtitle: 'Search student SDS payments, review yearly fee amounts, and record new payments.',
    school: 'School',
    selectSchool: 'Select school',
    studentAdmissionNo: 'Admission No',
    studentAdmissionPlaceholder: 'Enter admission number',
    searchStudent: 'Search Student',
    searching: 'Searching...',
    studentDetails: 'Student Details',
    numberLabel: '#',
    nameLabel: 'Name',
    admissionLabel: 'Admission No',
    studentName: 'Student Name',
    invoiceNo: 'Invoice No',
    year: 'Year',
    selectYear: 'Select year',
    annualFee: 'Annual Fee',
    memberFee: 'Member Fee',
    includeMemberFee: 'Include member fee',
    total: 'Total',
    addPayment: 'Add Payment',
    saving: 'Saving...',
    paymentHistory: 'Payment History',
    paymentHistoryTitle: 'Student Payments',
    addPaymentDialogTitle: 'Add Payment',
    paidDate: 'Paid Date',
    noPayments: 'No payments found for this student.',
    notIncluded: 'Not included',
    paymentAdded: 'Payment added successfully.',
    selectStudentFirst: 'Select a student first.',
    unableToLoadPayments: 'Unable to load payment data.',
    unableToLoadFee: 'No fees assigned to this year.',
    invoiceRequired: 'Invoice number is required.',
    yearRequired: 'Year is required.',
    selectSchoolFirst: 'Select a school first.',
    close: 'Close',
    cancel: 'Cancel',
  }
})

const schools = ref<OptionRow[]>([])
const yearOptions = ref<number[]>([])
const selectedSchoolCensusId = ref<number>(getSchoolContextCensusId() ?? 0)
const studentIndexNo = ref('')
const student = ref<PaymentStudent | null>(null)
const paymentRows = ref<PaymentRow[]>([])
const feeDetails = ref<FeeDetails | null>(null)
const message = ref('')
const errorMessage = ref('')
const lookingUpStudent = ref(false)
const savingPayment = ref(false)
const showAddPaymentDialog = ref(false)
const paymentDialogError = ref('')

const paymentForm = ref({
  invoice_no: '',
  year: 0,
  include_member_fee: true,
})

const lookupDisabled = computed(() => isAdmin.value && selectedSchoolCensusId.value <= 0)
const calculatedTotal = computed(() => {
  const annual = Number(feeDetails.value?.annual_fee ?? 0)
  const member = paymentForm.value.include_member_fee ? Number(feeDetails.value?.member_fee ?? 0) : 0
  return annual + member
})
const canSubmitPayment = computed(() => {
  return student.value !== null
    && paymentForm.value.invoice_no.trim() !== ''
    && paymentForm.value.year > 0
    && feeDetails.value !== null
})

const clearStatus = (): void => {
  message.value = ''
  errorMessage.value = ''
}

const resetStudentState = (): void => {
  student.value = null
  paymentRows.value = []
  feeDetails.value = null
  paymentForm.value.invoice_no = ''
  paymentForm.value.year = 0
  paymentForm.value.include_member_fee = true
  showAddPaymentDialog.value = false
}

const resetPaymentForm = (): void => {
  paymentForm.value.invoice_no = ''
  paymentForm.value.year = 0
  paymentForm.value.include_member_fee = true
  feeDetails.value = null
  paymentDialogError.value = ''
}

const buildSchoolHeaders = (): Record<string, string> | undefined => {
  if (!isAdmin.value) {
    return undefined
  }

  const selected = Number(selectedSchoolCensusId.value)
  if (!Number.isFinite(selected) || selected <= 0) {
    return undefined
  }

  return { 'X-School-Census-Id': String(selected) }
}

const formatAmount = (value: number): string => {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const formatDate = (value: string): string => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  })
}

const openAddPaymentDialog = (): void => {
  resetPaymentForm()
  clearStatus()
  showAddPaymentDialog.value = true
}

const closeAddPaymentDialog = (): void => {
  showAddPaymentDialog.value = false
  resetPaymentForm()
}

const extractApiMessage = (reason: unknown): string => {
  if (typeof reason === 'object' && reason !== null && 'response' in reason) {
    const response = (reason as { response?: { data?: { message?: string } } }).response
    if (typeof response?.data?.message === 'string' && response.data.message.trim() !== '') {
      return response.data.message
    }
  }

  return ''
}

const loadOptions = async (): Promise<void> => {
  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.get<PaymentOptionsResponse>('/payments/options', headers ? { headers } : undefined)
    schools.value = Array.isArray(data.schools) ? data.schools : []
    yearOptions.value = Array.isArray(data.years) ? data.years.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0) : []
  } catch (reason) {
    schools.value = []
    yearOptions.value = []
    errorMessage.value = extractApiMessage(reason) || text.value.unableToLoadPayments
  }
}

const lookupStudent = async (keepStatus: boolean = false): Promise<void> => {
  if (lookupDisabled.value) {
    errorMessage.value = text.value.selectSchoolFirst
    return
  }

  if (!keepStatus) {
    clearStatus()
  }
  lookingUpStudent.value = true

  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.get<PaymentStudentResponse>('/payments/student', {
      headers,
      params: {
        index_no: studentIndexNo.value.trim(),
      },
    })

    student.value = data.student ?? null
    paymentRows.value = Array.isArray(data.payments) ? data.payments : []
    resetPaymentForm()
  } catch (reason) {
    resetStudentState()
    errorMessage.value = extractApiMessage(reason) || text.value.unableToLoadPayments
  } finally {
    lookingUpStudent.value = false
  }
}

const loadFeeDetails = async (year: number): Promise<void> => {
  feeDetails.value = null

  if (year <= 0) {
    return
  }

  clearStatus()

  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.get<FeeDetails>('/payments/fee', {
      headers,
      params: { year },
    })

    feeDetails.value = data
  } catch (reason) {
    feeDetails.value = null
    errorMessage.value = extractApiMessage(reason) || text.value.unableToLoadFee
  }
}

const savePayment = async (): Promise<void> => {
  if (student.value === null) {
    paymentDialogError.value = text.value.selectStudentFirst
    return
  }

  if (paymentForm.value.invoice_no.trim() === '') {
    paymentDialogError.value = text.value.invoiceRequired
    return
  }

  if (paymentForm.value.year <= 0) {
    paymentDialogError.value = text.value.yearRequired
    return
  }

  clearStatus()
  paymentDialogError.value = ''
  savingPayment.value = true

  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.post('/payments', {
      index_no: student.value.index_no,
      invoice_no: paymentForm.value.invoice_no.trim(),
      year: paymentForm.value.year,
      include_member_fee: paymentForm.value.include_member_fee,
    }, headers ? { headers } : undefined)

    message.value = typeof data?.message === 'string' && data.message.trim() !== ''
      ? data.message
      : text.value.paymentAdded

    closeAddPaymentDialog()
    await lookupStudent(true)
  } catch (reason) {
    paymentDialogError.value = extractApiMessage(reason) || text.value.unableToLoadPayments
  } finally {
    savingPayment.value = false
  }
}

const onSchoolChange = async (): Promise<void> => {
  clearStatus()
  setSchoolContextCensusId(selectedSchoolCensusId.value > 0 ? selectedSchoolCensusId.value : null)
  studentIndexNo.value = ''
  resetStudentState()
}

watch(() => paymentForm.value.year, async (year) => {
  await loadFeeDetails(Number(year))
})

onMounted(async () => {
  await loadOptions()
})
</script>
