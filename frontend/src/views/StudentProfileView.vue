<template>
  <div class="space-y-6 bg-slate-100 print:bg-white">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 class="mt-2 font-display text-2xl font-bold text-slate-900">{{ text.studentDetails }}</h1>
        </div>

        <div class="flex gap-3">
          <button class="rounded-xl border border-cyan-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="exportPdf">
            {{ text.loginDetails }}
          </button>
          <button class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700" @click="exportPdf">
            {{ text.exportPdf }}
          </button>
        </div>
      </div>
    </header>

    <p v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ errorMessage }}
    </p>

    <div v-else-if="loading" class="rounded-[1.6rem] border border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">
      {{ text.loading }}
    </div>

    <template v-else>
      <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm print:rounded-none print:border-0 print:shadow-none">
        <div class="grid lg:grid-cols-[360px_minmax(0,1fr)]">
          <aside class="border-b border-slate-200 px-8 py-8 text-center lg:border-b-0 lg:border-r">
            <div class="mx-auto flex h-36 w-36 items-center justify-center overflow-hidden rounded-full bg-emerald-50">
              <img v-if="detail.photo_url" :src="detail.photo_url" alt="Student profile photo" class="h-full w-full object-cover" />
              <div v-else class="px-3 text-sm font-semibold text-slate-400">{{ text.noPhoto }}</div>
            </div>

            <h2 class="mt-6 text-3xl font-semibold tracking-tight text-slate-900">{{ displayName }}</h2>

            <div class="mt-6 space-y-2 text-base text-slate-500">
              <p>
                {{ text.admissionNo }}:
                <span class="font-semibold text-cyan-700">{{ detail.index_no || '-' }}</span>
              </p>
              <p>
                {{ text.classLabel }}:
                <span class="font-semibold text-slate-800">{{ detail.grade_class || '-' }}</span>
              </p>
            </div>

            <div class="mt-8 flex flex-wrap justify-center gap-4 print:hidden">
              <button class="rounded-xl border border-rose-300 bg-white px-6 py-3 text-sm font-semibold text-rose-500 hover:bg-rose-50" @click="goBackToReports">
                {{ text.back }}
              </button>
              <button class="rounded-xl bg-teal-500 px-6 py-3 text-sm font-semibold text-white hover:bg-teal-600" @click="exportPdf">
                {{ text.exportPdf }}
              </button>
            </div>
          </aside>

          <div class="px-8 py-8">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-5">
              <h3 class="font-display text-xl font-bold text-slate-900">{{ text.personalInfo }}</h3>
              <span class="rounded-lg px-4 py-2 text-sm font-semibold" :class="statusClass">{{ statusLabel }}</span>
            </div>

            <dl class="mt-7 space-y-4">
              <template v-for="item in personalInfoRows" :key="item.label">
                <div class="grid grid-cols-[180px_18px_minmax(0,1fr)] items-start text-sm">
                  <dt class="font-semibold text-slate-900">{{ item.label }}</dt>
                  <dd class="text-slate-400">:</dd>
                  <dd class="text-slate-600 break-words">{{ item.value || '-' }}</dd>
                </div>
              </template>
            </dl>
          </div>
        </div>
      </section>

      <div class="rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
          <div class="flex flex-wrap gap-8 text-sm font-semibold text-slate-500">
            <span class="border-b-4 border-cyan-600 pb-3 text-cyan-700">{{ text.studentDetails }}</span>
            <span>{{ text.contactDetails }}</span>
            <span>{{ text.address }}</span>
            <span>{{ text.parentGuardianDetail }}</span>
          </div>
        </div>

        <div class="grid gap-6 px-6 py-6 xl:grid-cols-2">
          <section class="rounded-[1.35rem] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4">
              <h4 class="font-display text-xl font-bold text-slate-900">{{ text.contactDetails }}</h4>
            </div>
            <div class="grid gap-6 px-5 py-5 md:grid-cols-2">
              <div v-for="item in contactInfo" :key="item.label">
                <p class="text-sm font-semibold text-slate-900">{{ item.label }}</p>
                <p class="mt-2 break-words text-base text-slate-600">{{ item.value || '-' }}</p>
              </div>
            </div>
          </section>

          <section class="rounded-[1.35rem] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4">
              <h4 class="font-display text-xl font-bold text-slate-900">{{ text.address }}</h4>
            </div>
            <div class="grid gap-6 px-5 py-5">
              <div v-for="item in addressInfo" :key="item.label">
                <p class="text-sm font-semibold text-slate-900">{{ item.label }}</p>
                <p class="mt-2 text-base text-slate-600">{{ item.value || '-' }}</p>
              </div>
            </div>
          </section>
        </div>

        <section class="border-t border-slate-200 px-6 py-6">
          <h4 class="font-display text-xl font-bold text-slate-900">{{ text.parentGuardianDetail }}</h4>
          <div class="mt-6 space-y-8">
            <article v-for="group in guardianGroups" :key="group.title" class="grid gap-5 md:grid-cols-[280px_minmax(0,1fr)_minmax(0,1fr)]">
              <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-base font-bold text-slate-500">
                  <svg v-if="group.role === 'father'" viewBox="0 0 24 24" fill="none" class="h-8 w-8 text-slate-500">
                    <circle cx="12" cy="8" r="4" fill="currentColor" fill-opacity="0.18" />
                    <path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    <circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="1.8" />
                  </svg>
                  <svg v-else-if="group.role === 'mother'" viewBox="0 0 24 24" fill="none" class="h-8 w-8 text-slate-500">
                    <circle cx="12" cy="7.5" r="3.5" stroke="currentColor" stroke-width="1.8" />
                    <path d="M8 11.5c1.2 1 2.5 1.5 4 1.5s2.8-.5 4-1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M7 20c.5-3.1 2.8-5 5-5s4.5 1.9 5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                  </svg>
                  <svg v-else viewBox="0 0 24 24" fill="none" class="h-8 w-8 text-slate-500">
                    <path d="M12 3.5 5 6.5v5.2c0 4.1 2.7 7.9 7 8.8 4.3-.9 7-4.7 7-8.8V6.5l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                    <path d="M9.5 11.8 11 13.3l3.5-3.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </div>
                <div>
                  <p class="text-sm text-slate-500">{{ group.title }}</p>
                  <p class="text-lg font-semibold text-slate-900">{{ group.name || '-' }}</p>
                </div>
              </div>

              <div>
                <p class="text-sm font-semibold text-slate-900">{{ text.phoneNo }}</p>
                <p class="mt-1 text-base text-slate-600">{{ group.mobile || '-' }}</p>
              </div>

              <div>
                <p class="text-sm font-semibold text-slate-900">{{ text.job }}</p>
                <p class="mt-1 text-base text-slate-600">{{ group.job || '-' }}</p>
              </div>
            </article>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { getUser } from '../services/auth'
import { useLocalizedText } from '../utils/uiText'

interface StudentProfileDetail {
  std_id: number
  is_active?: boolean
  census_id: string
  school_name?: string
  index_no: string
  full_name: string
  name_with_initials: string
  address1: string
  address2: string
  phone_no: string
  whatsapp_no: string
  phone_home: string
  email: string
  dob: string | null
  d_o_admission: string | null
  gender_id: number
  gender_label?: string
  ethnic_group_label?: string
  religion_label?: string
  grade_class?: string
  year?: number | null
  father_name: string
  father_job: string
  father_mobile: string
  mother_name: string
  mother_job: string
  mother_mobile: string
  guardian_name: string
  guardian_job: string
  guardian_mobile: string
  photo_url?: string
}

const route = useRoute()
const router = useRouter()
const currentUser = getUser()
const isAdmin = computed(() => Number((currentUser as { role_id?: number } | null)?.role_id ?? 0) === 1)

const text = useLocalizedText({
  en: {
    dashboard: 'Dashboard',
    student: 'Student',
    studentDetails: 'Student Details',
    loginDetails: 'Login Details',
    back: 'Back',
    exportPdf: 'Export PDF',
    loading: 'Loading student details...',
    noPhoto: 'NO PHOTO',
    admissionNo: 'Admission No',
    classLabel: 'Class',
    personalInfo: 'Personal Info',
    active: 'Active',
    inactive: 'Inactive',
    academicYear: 'Academic Year',
    gender: 'Gender',
    dob: 'Date Of Birth',
    ethnicGroup: 'Ethnic Group',
    religion: 'Religion',
    admissionDate: 'Admission Date',
    school: 'School',
    fullName: 'Full Name',
    nameWithInitials: 'Name With Initials',
    parentGuardianDetail: 'Parent Guardian Detail',
    father: 'Father',
    mother: 'Mother',
    guardian: 'Guardian',
    phoneNo: 'Phone Number',
    job: 'Job',
    contactDetails: 'Contact Details',
    whatsappNo: 'WhatsApp No',
    homePhone: 'Home Phone',
    email: 'Email',
    address: 'Address',
    addressLine1: 'Current Address',
    addressLine2: 'Permanent Address',
    unableToLoad: 'Unable to load student profile.',
  },
  si: {
    dashboard: 'පුවරුව',
    student: 'සිසුවා',
    studentDetails: 'සිසු විස්තර',
    loginDetails: 'Login Details',
    back: 'ආපසු',
    exportPdf: 'PDF Export',
    loading: 'සිසු විස්තර පූරණය වෙමින්...',
    noPhoto: 'ඡායාරූපයක් නැත',
    admissionNo: 'ඇතුළත් අංකය',
    classLabel: 'පංතිය',
    personalInfo: 'පෞද්ගලික තොරතුරු',
    active: 'සක්‍රීයයි',
    inactive: 'අක්‍රීයයි',
    academicYear: 'අධ්‍යයන වර්ෂය',
    gender: 'ස්ත්‍රී/පුරුෂ භාවය',
    dob: 'උපන්දිනය',
    ethnicGroup: 'ජාතික කණ්ඩායම',
    religion: 'ආගම',
    admissionDate: 'ඇතුළත් වූ දිනය',
    school: 'පාසල',
    fullName: 'සම්පූර්ණ නම',
    nameWithInitials: 'මුලකුරු සහිත නම',
    parentGuardianDetail: 'මව්පිය / භාරකරු විස්තර',
    father: 'පියා',
    mother: 'මව',
    guardian: 'භාරකරු',
    phoneNo: 'දුරකථන අංකය',
    job: 'රැකියාව',
    contactDetails: 'සම්බන්ධතා තොරතුරු',
    whatsappNo: 'WhatsApp අංකය',
    homePhone: 'නිවසේ දුරකථන',
    email: 'ඊමේල්',
    address: 'ලිපිනය',
    addressLine1: 'වත්මන් ලිපිනය',
    addressLine2: 'ස්ථිර ලිපිනය',
    unableToLoad: 'සිසු පැතිකඩ පූරණය කළ නොහැකි විය.',
  },
  ta: {
    dashboard: 'கட்டுப்பாட்டு பலகை',
    student: 'மாணவர்',
    studentDetails: 'மாணவர் விபரங்கள்',
    loginDetails: 'Login Details',
    back: 'திரும்ப',
    exportPdf: 'PDF Export',
    loading: 'மாணவர் விபரங்கள் ஏற்றப்படுகிறது...',
    noPhoto: 'புகைப்படம் இல்லை',
    admissionNo: 'அனுமதி இலக்கம்',
    classLabel: 'வகுப்பு',
    personalInfo: 'தனிப்பட்ட விவரங்கள்',
    active: 'செயலில்',
    inactive: 'செயலற்றது',
    academicYear: 'கல்வியாண்டு',
    gender: 'பால்',
    dob: 'பிறந்த தேதி',
    ethnicGroup: 'இனக்குழு',
    religion: 'மதம்',
    admissionDate: 'சேர்க்கை தேதி',
    school: 'பாடசாலை',
    fullName: 'முழு பெயர்',
    nameWithInitials: 'முதற் எழுத்துகளுடன் பெயர்',
    parentGuardianDetail: 'பெற்றோர் / பாதுகாவலர் விபரம்',
    father: 'தந்தை',
    mother: 'தாய்',
    guardian: 'பாதுகாவலர்',
    phoneNo: 'தொலைபேசி எண்',
    job: 'தொழில்',
    contactDetails: 'தொடர்பு விவரங்கள்',
    whatsappNo: 'WhatsApp எண்',
    homePhone: 'வீட்டு தொலைபேசி',
    email: 'மின்னஞ்சல்',
    address: 'முகவரி',
    addressLine1: 'தற்போதைய முகவரி',
    addressLine2: 'நிரந்தர முகவரி',
    unableToLoad: 'மாணவர் சுயவிவரத்தை ஏற்ற முடியவில்லை.',
  },
})

const detail = ref<StudentProfileDetail>({
  std_id: 0,
  is_active: true,
  census_id: '',
  school_name: '',
  index_no: '',
  full_name: '',
  name_with_initials: '',
  address1: '',
  address2: '',
  phone_no: '',
  whatsapp_no: '',
  phone_home: '',
  email: '',
  dob: '',
  d_o_admission: '',
  gender_id: 0,
  gender_label: '',
  ethnic_group_label: '',
  religion_label: '',
  grade_class: '',
  year: null,
  father_name: '',
  father_job: '',
  father_mobile: '',
  mother_name: '',
  mother_job: '',
  mother_mobile: '',
  guardian_name: '',
  guardian_job: '',
  guardian_mobile: '',
  photo_url: '',
})

const loading = ref(false)
const errorMessage = ref('')

const displayName = computed(() => detail.value.full_name || detail.value.name_with_initials || '-')
const statusLabel = computed(() => (detail.value.is_active === false ? text.value.inactive : text.value.active))
const statusClass = computed(() => (
  detail.value.is_active === false
    ? 'bg-rose-100 text-rose-600'
    : 'bg-emerald-100 text-emerald-600'
))

const personalInfoRows = computed(() => {
  const rows = [
    { label: text.value.classLabel, value: detail.value.grade_class },
    { label: text.value.academicYear, value: detail.value.year ? String(detail.value.year) : '' },
    { label: text.value.gender, value: detail.value.gender_label ?? '' },
    { label: text.value.dob, value: detail.value.dob ?? '' },
    { label: text.value.ethnicGroup, value: detail.value.ethnic_group_label ?? '' },
    { label: text.value.religion, value: detail.value.religion_label ?? '' },
    { label: text.value.admissionDate, value: detail.value.d_o_admission ?? '' },
    { label: text.value.phoneNo, value: detail.value.phone_no ?? '' },
    { label: text.value.email, value: detail.value.email ?? '' },
  ]

  if (isAdmin.value) {
    rows.splice(1, 0, { label: text.value.school, value: detail.value.school_name ?? '' })
  }

  return rows
})

const contactInfo = computed(() => [
  { label: text.value.phoneNo, value: detail.value.phone_no },
  { label: text.value.whatsappNo, value: detail.value.whatsapp_no },
  { label: text.value.homePhone, value: detail.value.phone_home },
  { label: text.value.email, value: detail.value.email },
])

const addressInfo = computed(() => [
  { label: text.value.addressLine1, value: detail.value.address1 },
  { label: text.value.addressLine2, value: detail.value.address2 },
])

const guardianGroups = computed(() => [
  {
    role: 'father',
    title: text.value.father,
    name: detail.value.father_name,
    mobile: detail.value.father_mobile,
    job: detail.value.father_job,
  },
  {
    role: 'mother',
    title: text.value.mother,
    name: detail.value.mother_name,
    mobile: detail.value.mother_mobile,
    job: detail.value.mother_job,
  },
  {
    role: 'guardian',
    title: text.value.guardian,
    name: detail.value.guardian_name,
    mobile: detail.value.guardian_mobile,
    job: detail.value.guardian_job,
  },
])

const loadProfile = async (): Promise<void> => {
  errorMessage.value = ''
  loading.value = true
  const studentId = Number(route.params.studentId)

  if (!Number.isFinite(studentId) || studentId <= 0) {
    errorMessage.value = text.value.unableToLoad
    loading.value = false
    return
  }

  try {
    const { data } = await api.get<{ data: StudentProfileDetail }>(`/students/${studentId}`)
    detail.value = data.data
  } catch {
    errorMessage.value = text.value.unableToLoad
  } finally {
    loading.value = false
  }
}

const exportPdf = (): void => {
  window.print()
}

const goBackToReports = async (): Promise<void> => {
  await router.push('/students/report')
}

onMounted(async () => {
  await loadProfile()
})
</script>
