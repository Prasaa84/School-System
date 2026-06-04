<template>
  <ion-page>
    <ion-header translucent>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/student" />
        </ion-buttons>
        <ion-title>My Payments</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true" class="payments-page">
      <div class="payments-shell">
        <ion-card v-if="loading">
          <ion-card-content>Loading payment history...</ion-card-content>
        </ion-card>

        <ion-card v-else-if="errorMessage">
          <ion-card-content>
            <ion-text color="danger">{{ errorMessage }}</ion-text>
            <ion-button class="retry-button" expand="block" @click="loadPayments">Try again</ion-button>
          </ion-card-content>
        </ion-card>

        <template v-else>
          <ion-card>
            <ion-card-header>
              <ion-card-title>{{ student?.name_with_initials || currentUsername }}</ion-card-title>
              <ion-card-subtitle>{{ student?.school_name || 'Payments history' }}</ion-card-subtitle>
            </ion-card-header>
            <ion-card-content>
              <div class="summary-grid">
                <div class="summary-box">
                  <span class="summary-label">Admission No</span>
                  <strong>{{ student?.index_no || '-' }}</strong>
                </div>
                <div class="summary-box">
                  <span class="summary-label">Payments</span>
                  <strong>{{ paymentRows.length }}</strong>
                </div>
                <div class="summary-box">
                  <span class="summary-label">Total Paid</span>
                  <strong>{{ formattedTotalPaid }}</strong>
                </div>
                <div class="summary-box">
                  <span class="summary-label">Latest Year</span>
                  <strong>{{ latestYear }}</strong>
                </div>
              </div>
            </ion-card-content>
          </ion-card>

          <ion-card v-if="paymentRows.length === 0">
            <ion-card-content>No payments found yet.</ion-card-content>
          </ion-card>

          <ion-card v-for="row in paymentRows" :key="row.id" class="payment-card">
            <ion-card-content>
              <div class="payment-head">
                <div>
                  <p class="eyebrow">Invoice</p>
                  <h2>{{ row.invoice_no }}</h2>
                </div>
                <ion-chip color="success">
                  <ion-label>{{ row.year }}</ion-label>
                </ion-chip>
              </div>

              <div class="detail-grid">
                <div>
                  <p class="detail-label">Annual Fee</p>
                  <p class="detail-value">{{ formatAmount(row.annual_fee) }}</p>
                </div>
                <div>
                  <p class="detail-label">Member Fee</p>
                  <p class="detail-value">{{ row.include_member_fee ? formatAmount(row.member_fee) : 'Not included' }}</p>
                </div>
                <div>
                  <p class="detail-label">Total</p>
                  <p class="detail-value strong">{{ formatAmount(row.total) }}</p>
                </div>
                <div>
                  <p class="detail-label">Paid Date</p>
                  <p class="detail-value">{{ formatDate(row.paid_date) }}</p>
                </div>
              </div>
            </ion-card-content>
          </ion-card>
        </template>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardSubtitle,
  IonCardTitle,
  IonChip,
  IonContent,
  IonHeader,
  IonLabel,
  IonPage,
  IonText,
  IonTitle,
  IonToolbar,
} from '@ionic/vue'
import { computed, onMounted, ref } from 'vue'
import api from '../services/api'
import { getUser } from '../services/auth'
import type { PaymentRow, PaymentStudent, PaymentStudentResponse } from '../types/payments'

const currentUsername = getUser()?.username ?? 'Student'
const loading = ref(false)
const errorMessage = ref('')
const student = ref<PaymentStudent | null>(null)
const paymentRows = ref<PaymentRow[]>([])

const formattedTotalPaid = computed(() => formatAmount(paymentRows.value.reduce((total, row) => total + Number(row.total ?? 0), 0)))
const latestYear = computed(() => {
  const value = paymentRows.value[0]?.year
  return value ? String(value) : '-'
})

const formatAmount = (value: number | string | null | undefined): string => {
  const amount = Number(value ?? 0)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}

const formatDate = (value: string | null | undefined): string => {
  const text = String(value ?? '').trim()
  if (!text) return '-'

  const date = new Date(text)
  if (Number.isNaN(date.getTime())) return text

  return date.toLocaleDateString()
}

const loadPayments = async (): Promise<void> => {
  loading.value = true
  errorMessage.value = ''

  try {
    const me = await api.get<{ data?: { index_no?: string } }>('/students/me')
    const indexNo = String(me.data.data?.index_no ?? '').trim()

    if (!indexNo) {
      throw new Error('Unable to resolve the student admission number.')
    }

    const { data } = await api.get<PaymentStudentResponse>('/payments/student', {
      params: {
        index_no: indexNo,
      },
    })

    student.value = data.student ?? null
    paymentRows.value = Array.isArray(data.payments) ? data.payments : []
  } catch (error: any) {
    student.value = null
    paymentRows.value = []
    errorMessage.value = String(error?.response?.data?.message ?? error?.message ?? 'Unable to load payment history.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void loadPayments()
})
</script>

<style scoped>
.payments-page {
  --background: linear-gradient(180deg, #f4efe5 0%, #eef5fb 100%);
}

.payments-shell {
  padding: 16px;
}

.retry-button {
  margin-top: 16px;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.summary-box {
  border-radius: 18px;
  padding: 14px 12px;
  background: linear-gradient(135deg, rgba(26, 110, 92, 0.1), rgba(181, 121, 41, 0.12));
}

.summary-label {
  display: block;
  margin-bottom: 6px;
  font-size: 0.78rem;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.summary-box strong {
  font-size: 1.05rem;
  color: #17365d;
}

.payment-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.eyebrow {
  margin: 0 0 6px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  font-size: 0.7rem;
  color: #8a6534;
}

h2 {
  margin: 0;
  font-size: 1.2rem;
  color: #183155;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 12px;
}

.detail-label {
  margin: 0 0 4px;
  font-size: 0.8rem;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.detail-value {
  margin: 0;
  color: #223a57;
}

.detail-value.strong {
  font-weight: 700;
}
</style>
