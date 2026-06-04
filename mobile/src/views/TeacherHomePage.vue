<template>
  <ion-page>
    <ion-header translucent>
      <ion-toolbar>
        <ion-title>Teacher Home</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="goProfile">Profile</ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true" class="dashboard-page">
      <section class="masthead teacher">
        <p class="kicker">Class teacher account</p>
        <h1>{{ user?.username }}</h1>
        <p>{{ assignmentSummary }}</p>
      </section>

      <ion-grid fixed>
        <ion-row>
          <ion-col size="12">
            <ion-card>
              <ion-card-header>
                <ion-card-title>Teaching Tools</ion-card-title>
                <ion-card-subtitle>Core areas for your class work</ion-card-subtitle>
              </ion-card-header>
              <ion-card-content>
                <ion-list lines="full">
                  <ion-item>My class dashboard</ion-item>
                  <ion-item>Attendance</ion-item>
                  <ion-item>Marks entry</ion-item>
                  <ion-item>Student quick lookup</ion-item>
                </ion-list>
              </ion-card-content>
            </ion-card>
          </ion-col>
        </ion-row>

        <ion-row>
          <ion-col size="12">
            <ion-button expand="block" fill="solid" @click="logout">Sign out</ion-button>
          </ion-col>
        </ion-row>
      </ion-grid>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import {
  IonButton,
  IonButtons,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardSubtitle,
  IonCardTitle,
  IonCol,
  IonContent,
  IonGrid,
  IonHeader,
  IonItem,
  IonList,
  IonPage,
  IonRow,
  IonTitle,
  IonToolbar,
} from '@ionic/vue'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { clearAuthSession, getUser } from '../services/auth'

const router = useRouter()
const user = computed(() => getUser())

const assignmentSummary = computed(() => {
  const assignment = user.value?.class_teacher_assignment_status

  if (!assignment?.is_assigned) {
    return user.value?.class_teacher_assignment_message || 'No class assignment found yet.'
  }

  return assignment.grade_class
    ? `Assigned class: ${assignment.grade_class} (${assignment.year})`
    : `Assigned year: ${assignment.year}`
})

const logout = async (): Promise<void> => {
  clearAuthSession()
  await router.replace('/login')
}

const goProfile = async (): Promise<void> => {
  await router.push('/profile')
}
</script>

<style scoped>
.dashboard-page {
  --background: linear-gradient(180deg, #eef8ef 0%, #f9f3e8 100%);
}

.masthead {
  padding: 28px 24px 16px;
}

.masthead.teacher h1 {
  color: #194936;
}

.kicker {
  margin: 0 0 8px;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  font-size: 0.72rem;
  color: #7d5a26;
}

h1 {
  margin: 0;
  font-size: 2rem;
}

p {
  line-height: 1.6;
}
</style>
