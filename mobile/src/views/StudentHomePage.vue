<template>
  <ion-page>
    <ion-header translucent>
      <ion-toolbar>
        <ion-title>Student Home</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="openStudentProfile">Profile</ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true" class="dashboard-page">
      <section class="masthead student">
        <p class="kicker">Student account</p>
        <h1>{{ user?.username }}</h1>
        <p>Marks, payments, and profile access can live here in focused mobile flows.</p>
      </section>

      <ion-grid fixed>
        <ion-row>
          <ion-col size="12">
            <ion-card>
              <ion-card-header>
                <ion-card-title>Phase 1 mobile scope</ion-card-title>
                <ion-card-subtitle>Ready for the next build step</ion-card-subtitle>
              </ion-card-header>
              <ion-card-content>
                <ion-list lines="full">
                  <ion-item button detail @click="openStudentProfile">My profile</ion-item>
                  <ion-item button detail @click="openStudentMarks">My marks</ion-item>
                  <ion-item button detail @click="showComingSoon('My payments')">My payments</ion-item>
                  <ion-item button detail @click="showComingSoon('School notices')">School notices later</ion-item>
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
  toastController,
  IonTitle,
  IonToolbar,
} from '@ionic/vue'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { clearAuthSession, getUser } from '../services/auth'

const router = useRouter()
const user = computed(() => getUser())

const logout = async (): Promise<void> => {
  clearAuthSession()
  await router.replace('/login')
}

const openStudentProfile = async (): Promise<void> => {
  await router.push('/student/profile')
}

const openStudentMarks = async (): Promise<void> => {
  await router.push('/student/marks')
}

const showComingSoon = async (featureName: string): Promise<void> => {
  const toast = await toastController.create({
    message: `${featureName} is the next mobile feature to connect.`,
    duration: 1800,
    position: 'bottom',
  })

  await toast.present()
}
</script>

<style scoped>
.dashboard-page {
  --background: linear-gradient(180deg, #fff7ec 0%, #eef5ff 100%);
}

.masthead {
  padding: 28px 24px 16px;
}

.masthead.student h1 {
  color: #17335c;
}

.kicker {
  margin: 0 0 8px;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  font-size: 0.72rem;
  color: #8f5d24;
}

h1 {
  margin: 0;
  font-size: 2rem;
}

p {
  line-height: 1.6;
}
</style>
