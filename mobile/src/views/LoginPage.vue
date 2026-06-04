<template>
  <ion-page>
    <ion-content :fullscreen="true" class="login-page">
      <div class="login-shell">
        <section class="hero-card">
          <p class="eyebrow">SDS Mobile</p>
          <h1>Student and teacher access, designed for Android first.</h1>
          <p class="hero-copy">
            This mobile app starts with student and class teacher accounts, while keeping the structure ready for more roles later.
          </p>
        </section>

        <ion-card class="form-card">
          <ion-card-header>
            <ion-card-title>Sign in</ion-card-title>
            <ion-card-subtitle>Use the same account you already use in SDS.</ion-card-subtitle>
          </ion-card-header>

          <ion-card-content>
            <ion-list lines="none">
              <ion-item>
                <ion-input v-model="username" label="Username" label-placement="stacked" autocomplete="username" />
              </ion-item>
              <ion-item>
                <ion-input
                  v-model="password"
                  label="Password"
                  label-placement="stacked"
                  type="password"
                  autocomplete="current-password"
                  @keyup.enter="submit"
                />
              </ion-item>
            </ion-list>

            <ion-text v-if="errorMessage" color="danger">
              <p class="feedback">{{ errorMessage }}</p>
            </ion-text>

            <ion-button expand="block" size="large" :disabled="loading" @click="submit">
              {{ loading ? 'Signing in...' : 'Sign in' }}
            </ion-button>
          </ion-card-content>
        </ion-card>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import {
  IonButton,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardSubtitle,
  IonCardTitle,
  IonContent,
  IonInput,
  IonItem,
  IonList,
  IonPage,
  IonText,
} from '@ionic/vue'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import { setAuthSession } from '../services/auth'
import { resolveHomeRoute } from '../services/roles'
import type { LoginResponse } from '../types/auth'

const router = useRouter()
const username = ref('')
const password = ref('')
const loading = ref(false)
const errorMessage = ref('')

const submit = async (): Promise<void> => {
  errorMessage.value = ''

  if (!username.value.trim() || !password.value) {
    errorMessage.value = 'Enter both username and password.'
    return
  }

  loading.value = true

  try {
    const { data } = await api.post<LoginResponse>('/auth/login', {
      username: username.value.trim(),
      password: password.value,
    })

    setAuthSession(data.access_token, data.user)
    await router.replace(resolveHomeRoute(data.user))
  } catch (error: any) {
    errorMessage.value = String(error?.response?.data?.message ?? 'Unable to sign in right now.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  --background: linear-gradient(180deg, #f4efe5 0%, #d7e7f5 100%);
}

.login-shell {
  min-height: 100%;
  display: grid;
  align-content: center;
  gap: 20px;
  padding: 24px;
}

.hero-card {
  padding: 12px 4px;
}

.eyebrow {
  margin: 0 0 10px;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  font-size: 0.75rem;
  color: #805b2d;
}

h1 {
  margin: 0;
  font-size: 2rem;
  line-height: 1.05;
  color: #14223b;
}

.hero-copy {
  margin: 14px 0 0;
  color: #36506c;
  line-height: 1.6;
}

.form-card {
  border-radius: 28px;
  overflow: hidden;
}

.feedback {
  margin: 12px 0;
}
</style>
