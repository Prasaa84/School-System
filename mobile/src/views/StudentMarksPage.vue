<template>
  <ion-page>
    <ion-header translucent>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/student" />
        </ion-buttons>
        <ion-title>My Marks</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true" class="marks-page">
      <div class="marks-shell">
        <ion-card>
          <ion-card-content>
            <div class="filter-grid">
              <ion-item>
                <ion-select v-model="selectedYear" label="Year" label-placement="stacked" :disabled="yearLocked || loadingOptions || years.length === 0">
                  <ion-select-option v-for="year in years" :key="`marks-year-${year}`" :value="year">{{ year }}</ion-select-option>
                </ion-select>
              </ion-item>

              <ion-item>
                <ion-select v-model="selectedTerm" label="Term" label-placement="stacked" :disabled="loadingOptions || terms.length === 0">
                  <ion-select-option v-for="term in terms" :key="`marks-term-${term.id}`" :value="term.id">{{ term.label }}</ion-select-option>
                </ion-select>
              </ion-item>
            </div>

            <ion-item>
              <ion-input v-model="searchQuery" label="Search subjects" label-placement="stacked" placeholder="Type a subject name" clear-input />
            </ion-item>

            <ion-text v-if="scopeLabel || classLabel" color="medium">
              <p class="scope-copy">
                <span v-if="scopeLabel">{{ scopeLabel }}</span>
                <span v-if="scopeLabel && classLabel"> · </span>
                <span v-if="classLabel">{{ classLabel }}</span>
              </p>
            </ion-text>
          </ion-card-content>
        </ion-card>

        <ion-card v-if="loadingMarks || loadingOptions">
          <ion-card-content>Loading marks...</ion-card-content>
        </ion-card>

        <ion-card v-else-if="errorMessage">
          <ion-card-content>
            <ion-text color="danger">{{ errorMessage }}</ion-text>
            <ion-button class="retry-button" expand="block" @click="loadMarks">Try again</ion-button>
          </ion-card-content>
        </ion-card>

        <template v-else>
          <ion-card>
            <ion-card-header>
              <ion-card-title>{{ studentRow?.name_with_initials || currentUsername }}</ion-card-title>
              <ion-card-subtitle>{{ currentTermLabel }}</ion-card-subtitle>
            </ion-card-header>
            <ion-card-content>
              <div class="summary-grid">
                <div class="summary-box">
                  <span class="summary-label">Total</span>
                  <strong>{{ studentRow?.total ?? '-' }}</strong>
                </div>
                <div class="summary-box">
                  <span class="summary-label">Average</span>
                  <strong>{{ formattedAverage }}</strong>
                </div>
                <div class="summary-box">
                  <span class="summary-label">Position</span>
                  <strong>{{ formattedPosition }}</strong>
                </div>
                <div class="summary-box">
                  <span class="summary-label">Subjects</span>
                  <strong>{{ filteredSubjectRows.length }}</strong>
                </div>
              </div>
            </ion-card-content>
          </ion-card>

          <ion-card v-if="filteredSubjectRows.length === 0">
            <ion-card-content>{{ searchQuery.trim() ? 'No subjects match your search.' : 'No marks found for this term yet.' }}</ion-card-content>
          </ion-card>

          <ion-card v-for="subject in filteredSubjectRows" :key="subject.subject_id" class="subject-card">
            <ion-card-content>
              <p class="subject-name">{{ subject.subject }}</p>
              <p class="subject-mark" :class="{ absent: resolveMarkValue(subject) === 'AB' }">{{ resolveMarkLabel(subject) }}</p>
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
  IonContent,
  IonHeader,
  IonInput,
  IonItem,
  IonPage,
  IonSelect,
  IonSelectOption,
  IonText,
  IonTitle,
  IonToolbar,
} from '@ionic/vue'
import { computed, onMounted, ref, watch } from 'vue'
import api from '../services/api'
import { getUser } from '../services/auth'
import type { ClassOption, GradeOption, MarkRow, MarksOptionsResponse, MarksResponse, ScopeResponse, SubjectRow, TermOption } from '../types/marks'

const currentUser = getUser()
const currentUsername = currentUser?.username ?? 'Student'

const years = ref<number[]>([])
const terms = ref<TermOption[]>([])
const grades = ref<GradeOption[]>([])
const classes = ref<ClassOption[]>([])
const scope = ref<ScopeResponse | null>(null)
const selectedYear = ref<number>(0)
const selectedTerm = ref<number>(1)
const selectedGradeId = ref<number>(0)
const selectedClassId = ref<number>(0)
const loadingOptions = ref(false)
const loadingMarks = ref(false)
const errorMessage = ref('')
const searchQuery = ref('')
const subjectRows = ref<SubjectRow[]>([])
const studentRow = ref<MarkRow | null>(null)
const classLabel = ref('')

const yearLocked = computed(() => (scope.value?.locked_year ?? null) !== null)
const scopeLabel = computed(() => {
  const role = String(scope.value?.role ?? '').trim().toLowerCase()
  if (role === 'student') return 'Student view'
  return ''
})
const currentTermLabel = computed(() => terms.value.find((term) => term.id === selectedTerm.value)?.label ?? `Term ${selectedTerm.value || '-'}`)
const formattedAverage = computed(() => {
  const value = studentRow.value?.average
  return typeof value === 'number' ? value.toFixed(2) : '-'
})
const formattedPosition = computed(() => {
  const value = studentRow.value?.position
  return typeof value === 'number' ? String(value) : '-'
})
const filteredSubjectRows = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  const sorted = [...subjectRows.value].sort((left, right) => left.order_id - right.order_id || left.subject.localeCompare(right.subject))

  if (!query) {
    return sorted
  }

  return sorted.filter((subject) => subject.subject.toLowerCase().includes(query))
})

const syncScopeSelections = (nextScope: ScopeResponse | null | undefined): void => {
  scope.value = nextScope ?? null

  if ((scope.value?.locked_year ?? null) !== null) {
    selectedYear.value = Number(scope.value?.locked_year ?? 0)
  }

  if ((scope.value?.locked_grade_id ?? null) !== null) {
    selectedGradeId.value = Number(scope.value?.locked_grade_id ?? 0)
  }

  if ((scope.value?.locked_class_id ?? null) !== null) {
    selectedClassId.value = Number(scope.value?.locked_class_id ?? 0)
  }
}

const loadOptions = async (): Promise<void> => {
  loadingOptions.value = true
  errorMessage.value = ''

  try {
    const { data } = await api.get<MarksOptionsResponse>('/marks/options', {
      params: {
        year: selectedYear.value > 0 ? selectedYear.value : undefined,
        grade_id: selectedGradeId.value > 0 ? selectedGradeId.value : undefined,
      },
    })

    years.value = Array.isArray(data.years) ? data.years : []
    terms.value = Array.isArray(data.terms) ? data.terms : []
    grades.value = Array.isArray(data.grades) ? data.grades : []
    classes.value = Array.isArray(data.classes) ? data.classes : []
    syncScopeSelections(data.scope)

    if (selectedYear.value <= 0 && years.value.length > 0) {
      selectedYear.value = years.value[0]
    }

    if (selectedTerm.value <= 0 && terms.value.length > 0) {
      selectedTerm.value = terms.value[0].id
    }

    if (selectedGradeId.value <= 0 && grades.value.length === 1) {
      selectedGradeId.value = grades.value[0].grade_id
    }

    if (selectedClassId.value <= 0 && classes.value.length === 1) {
      selectedClassId.value = classes.value[0].class_id
    }
  } catch (error: any) {
    errorMessage.value = String(error?.response?.data?.message ?? 'Unable to load marks options.')
  } finally {
    loadingOptions.value = false
  }
}

const loadMarks = async (): Promise<void> => {
  if (selectedYear.value <= 0 || selectedTerm.value <= 0 || selectedGradeId.value <= 0 || selectedClassId.value <= 0) {
    return
  }

  loadingMarks.value = true
  errorMessage.value = ''

  try {
    const { data } = await api.get<MarksResponse>('/marks', {
      params: {
        year: selectedYear.value,
        term: selectedTerm.value,
        grade_id: selectedGradeId.value,
        class_id: selectedClassId.value,
      },
    })

    subjectRows.value = Array.isArray(data.subjects) ? data.subjects : []
    studentRow.value = Array.isArray(data.students) && data.students.length > 0 ? data.students[0] : null
    classLabel.value = String(data.class?.label ?? '').trim()
  } catch (error: any) {
    subjectRows.value = []
    studentRow.value = null
    classLabel.value = ''
    errorMessage.value = String(error?.response?.data?.message ?? 'Unable to load marks.')
  } finally {
    loadingMarks.value = false
  }
}

const resolveMarkValue = (subject: SubjectRow): string => {
  return String(studentRow.value?.marks?.[String(subject.subject_id)] ?? '').trim()
}

const resolveMarkLabel = (subject: SubjectRow): string => {
  const value = resolveMarkValue(subject)
  if (!value) return 'No mark'
  if (value === 'AB') return 'Absent'
  return value
}

watch(selectedTerm, async (next, previous) => {
  if (next === previous) return
  await loadMarks()
})

watch(selectedYear, async (next, previous) => {
  if (next === previous) return
  await loadOptions()
  await loadMarks()
})

onMounted(async () => {
  await loadOptions()
  await loadMarks()
})
</script>

<style scoped>
.marks-page {
  --background: linear-gradient(180deg, #f6efe4 0%, #eef5fc 100%);
}

.marks-shell {
  padding: 16px;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.scope-copy {
  margin: 12px 0 0;
  font-size: 0.92rem;
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
  font-size: 1.2rem;
  color: #17365d;
}

.subject-card {
  overflow: hidden;
}

.subject-name {
  margin: 0 0 10px;
  font-size: 0.82rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #8a6534;
}

.subject-mark {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 700;
  color: #193d6b;
}

.subject-mark.absent {
  color: #b45309;
}
</style>
