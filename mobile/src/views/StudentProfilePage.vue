<template>
  <ion-page>
    <ion-header translucent>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/student" />
        </ion-buttons>
        <ion-title>My Profile</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true" class="profile-page">
      <div class="profile-shell">
        <ion-card v-if="loading">
          <ion-card-content>Loading student profile...</ion-card-content>
        </ion-card>

        <ion-card v-else-if="errorMessage">
          <ion-card-content>
            <ion-text color="danger">{{ errorMessage }}</ion-text>
            <ion-button class="retry-button" expand="block" @click="loadProfile">Try again</ion-button>
          </ion-card-content>
        </ion-card>

        <template v-else>
          <ion-card class="hero-card">
            <ion-card-content>
              <div class="hero-row">
                <ion-avatar class="profile-avatar">
                  <img v-if="detail.photo_url" :src="detail.photo_url" alt="Student profile photo" />
                  <div v-else class="avatar-fallback">{{ initials }}</div>
                </ion-avatar>
                <div>
                  <p class="eyebrow">Student profile</p>
                  <h1>{{ displayName }}</h1>
                  <p class="muted">{{ detail.grade_class || '-' }}<span v-if="detail.year"> · {{ detail.year }}</span></p>
                  <ion-chip :color="detail.is_active === false ? 'danger' : 'success'">
                    <ion-label>{{ detail.is_active === false ? 'Inactive' : 'Active' }}</ion-label>
                  </ion-chip>
                </div>
              </div>
            </ion-card-content>
          </ion-card>

          <ion-card>
            <ion-card-header>
              <ion-card-title>Academic details</ion-card-title>
            </ion-card-header>
            <ion-list lines="full">
              <ion-item>
                <ion-label>
                  <h2>Admission No</h2>
                  <p>{{ detail.index_no || '-' }}</p>
                </ion-label>
              </ion-item>
              <ion-item>
                <ion-label>
                  <h2>School</h2>
                  <p>{{ detail.school_name || '-' }}</p>
                </ion-label>
              </ion-item>
              <ion-item>
                <ion-label>
                  <h2>Gender</h2>
                  <p>{{ detail.gender_label || '-' }}</p>
                </ion-label>
              </ion-item>
              <ion-item>
                <ion-label>
                  <h2>Date of Birth</h2>
                  <p>{{ detail.dob || '-' }}</p>
                </ion-label>
              </ion-item>
              <ion-item>
                <ion-label>
                  <h2>Religion</h2>
                  <p>{{ detail.religion_label || '-' }}</p>
                </ion-label>
              </ion-item>
            </ion-list>
          </ion-card>

          <ion-card>
            <ion-card-header>
              <ion-card-title>Contact details</ion-card-title>
            </ion-card-header>
            <ion-list lines="full">
              <ion-item>
                <ion-label>
                  <h2>Mobile</h2>
                  <p>{{ detail.phone_no || '-' }}</p>
                </ion-label>
              </ion-item>
              <ion-item>
                <ion-label>
                  <h2>WhatsApp</h2>
                  <p>{{ detail.whatsapp_no || '-' }}</p>
                </ion-label>
              </ion-item>
              <ion-item>
                <ion-label>
                  <h2>Email</h2>
                  <p>{{ detail.email || '-' }}</p>
                </ion-label>
              </ion-item>
              <ion-item>
                <ion-label>
                  <h2>Address</h2>
                  <p>{{ fullAddress }}</p>
                </ion-label>
              </ion-item>
            </ion-list>
          </ion-card>

          <ion-card>
            <ion-card-header>
              <ion-card-title>Parent and guardian</ion-card-title>
            </ion-card-header>
            <ion-list lines="full">
              <ion-item v-for="person in guardians" :key="person.label">
                <ion-label>
                  <h2>{{ person.label }}</h2>
                  <p>{{ person.name || '-' }}</p>
                  <p>{{ person.mobile || '-' }}</p>
                  <p>{{ person.job || '-' }}</p>
                </ion-label>
              </ion-item>
            </ion-list>
          </ion-card>
        </template>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import {
  IonAvatar,
  IonBackButton,
  IonButton,
  IonButtons,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardTitle,
  IonChip,
  IonContent,
  IonHeader,
  IonItem,
  IonLabel,
  IonList,
  IonPage,
  IonText,
  IonTitle,
  IonToolbar,
} from '@ionic/vue'
import { computed, onMounted, ref } from 'vue'
import api from '../services/api'
import type { StudentProfileDetail } from '../types/student'

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
  login_username: '',
  login_is_enabled: null,
})

const loading = ref(false)
const errorMessage = ref('')

const displayName = computed(() => detail.value.full_name || detail.value.name_with_initials || 'Student')
const initials = computed(() => {
  const source = displayName.value.trim()
  if (!source) return 'S'
  return source
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('') || 'S'
})
const fullAddress = computed(() => {
  const parts = [detail.value.address1, detail.value.address2].map((value) => String(value ?? '').trim()).filter(Boolean)
  return parts.length > 0 ? parts.join(', ') : '-'
})
const guardians = computed(() => [
  { label: 'Father', name: detail.value.father_name, mobile: detail.value.father_mobile, job: detail.value.father_job },
  { label: 'Mother', name: detail.value.mother_name, mobile: detail.value.mother_mobile, job: detail.value.mother_job },
  { label: 'Guardian', name: detail.value.guardian_name, mobile: detail.value.guardian_mobile, job: detail.value.guardian_job },
])

const loadProfile = async (): Promise<void> => {
  errorMessage.value = ''
  loading.value = true

  try {
    const { data } = await api.get<{ data: StudentProfileDetail }>('/students/me')
    detail.value = data.data
  } catch (error: any) {
    errorMessage.value = String(error?.response?.data?.message ?? 'Unable to load student profile.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void loadProfile()
})
</script>

<style scoped>
.profile-page {
  --background: linear-gradient(180deg, #f7f0e6 0%, #eef4fb 100%);
}

.profile-shell {
  padding: 16px;
}

.hero-card {
  overflow: hidden;
}

.hero-row {
  display: grid;
  grid-template-columns: 84px minmax(0, 1fr);
  gap: 16px;
  align-items: center;
}

.profile-avatar {
  width: 84px;
  height: 84px;
  border: 4px solid rgba(255, 255, 255, 0.8);
  box-shadow: 0 12px 28px rgba(20, 34, 59, 0.12);
}

.avatar-fallback {
  width: 100%;
  height: 100%;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, #1a6e5c, #b57929);
  color: #fff;
  font-weight: 700;
  font-size: 1.4rem;
}

.eyebrow {
  margin: 0 0 6px;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  font-size: 0.7rem;
  color: #8d6332;
}

h1 {
  margin: 0;
  font-size: 1.6rem;
  line-height: 1.1;
  color: #183155;
}

.muted {
  margin: 8px 0 10px;
  color: #4a617e;
}

.retry-button {
  margin-top: 16px;
}
</style>
