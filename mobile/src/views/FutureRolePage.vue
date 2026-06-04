<template>
  <ion-page>
    <ion-content :fullscreen="true" class="future-page">
      <div class="future-shell">
        <ion-card>
          <ion-card-header>
            <ion-card-title>Mobile access is not enabled for this role yet</ion-card-title>
            <ion-card-subtitle>{{ roleLabel }}</ion-card-subtitle>
          </ion-card-header>
          <ion-card-content>
            <p>
              The mobile project is currently focused on student and class teacher accounts. This screen is here on purpose so future roles can be added without rebuilding the app structure.
            </p>
            <ion-button expand="block" @click="goProfile">View account</ion-button>
            <ion-button expand="block" fill="outline" @click="logout">Sign out</ion-button>
          </ion-card-content>
        </ion-card>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { IonButton, IonCard, IonCardContent, IonCardHeader, IonCardSubtitle, IonCardTitle, IonContent, IonPage } from '@ionic/vue'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { clearAuthSession, getUser } from '../services/auth'
import { resolveRoleLabel } from '../services/roles'

const router = useRouter()
const roleLabel = computed(() => resolveRoleLabel(getUser()))

const goProfile = async (): Promise<void> => {
  await router.push('/profile')
}

const logout = async (): Promise<void> => {
  clearAuthSession()
  await router.replace('/login')
}
</script>

<style scoped>
.future-page {
  --background: radial-gradient(circle at top, #f8ebd8 0%, #ecf2fa 55%, #ffffff 100%);
}

.future-shell {
  min-height: 100%;
  display: grid;
  align-items: center;
  padding: 24px;
}

p {
  line-height: 1.7;
}
</style>
