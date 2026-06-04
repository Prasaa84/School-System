<template>
  <ion-page>
    <ion-header translucent>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/login" />
        </ion-buttons>
        <ion-title>Account</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true" class="profile-page">
      <ion-card>
        <ion-card-header>
          <ion-card-title>{{ user?.username }}</ion-card-title>
          <ion-card-subtitle>{{ user?.role_name || 'Role not set' }}</ion-card-subtitle>
        </ion-card-header>
        <ion-card-content>
          <ion-list lines="full">
            <ion-item>
              <ion-label>
                <h2>User ID</h2>
                <p>{{ user?.user_id ?? '-' }}</p>
              </ion-label>
            </ion-item>
            <ion-item>
              <ion-label>
                <h2>School Census ID</h2>
                <p>{{ user?.school_census_id || '-' }}</p>
              </ion-label>
            </ion-item>
          </ion-list>
        </ion-card-content>
      </ion-card>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import {
  IonBackButton,
  IonButtons,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardSubtitle,
  IonCardTitle,
  IonContent,
  IonHeader,
  IonItem,
  IonLabel,
  IonList,
  IonPage,
  IonTitle,
  IonToolbar,
} from '@ionic/vue'
import { computed } from 'vue'
import { getUser } from '../services/auth'

const user = computed(() => getUser())
</script>

<style scoped>
.profile-page {
  --background: linear-gradient(180deg, #f6f8fc 0%, #eef1eb 100%);
}
</style>
