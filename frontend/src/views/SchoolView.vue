<template>
  <div class="space-y-6">
    <header class="rounded-3xl border border-slate-200 bg-gradient-to-r from-cyan-900 via-cyan-700 to-emerald-600 p-7 text-white shadow-xl">
      <h1 class="mt-2 font-display text-3xl font-bold md:text-4xl">{{ text.schoolTitle }}</h1>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="space-y-4">
        <p v-if="noticeText" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
          {{ noticeText }}
        </p>
        <p v-if="errorText" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {{ errorText }}
        </p>

        <div v-if="isAdmin" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-700">{{ text.schoolListTitle }}</p>
            <div class="flex flex-wrap items-center gap-2">
              <input
                v-model="searchKeyword"
                type="text"
                class="w-72 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2"
                :placeholder="text.searchPlaceholder"
              />
              <button
                type="button"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                @click="startCreateSchool"
              >
                {{ text.addSchool }}
              </button>
            </div>
          </div>

          <div class="overflow-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.censusId }}</th>
                  <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.schoolName }}</th>
                  <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.status }}</th>
                  <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.actions }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-for="schoolItem in filteredSchools" :key="schoolItem.id" class="hover:bg-slate-50">
                  <td class="px-3 py-2 text-slate-700">{{ schoolItem.id }}</td>
                  <td class="px-3 py-2 font-medium text-slate-800">{{ schoolItem.label }}</td>
                  <td class="px-3 py-2">
                    <span :class="(schoolItem.is_deleted ?? 0) === 1 ? 'text-red-600' : 'text-emerald-700'">
                      {{ (schoolItem.is_deleted ?? 0) === 1 ? text.disabled : text.active }}
                    </span>
                  </td>
                  <td class="px-3 py-2">
                    <div class="flex flex-wrap items-center gap-2">
                      <button
                        type="button"
                        class="rounded bg-cyan-600 px-3 py-1 text-xs font-semibold text-white hover:bg-cyan-700"
                        @click="openEditSchoolDialog(schoolItem.id)"
                      >
                        {{ text.edit }}
                      </button>
                      <button
                        type="button"
                        class="rounded bg-rose-600 px-3 py-1 text-xs font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="deletingSchoolId === schoolItem.id"
                        @click="deleteSchool(schoolItem)"
                      >
                        {{ deletingSchoolId === schoolItem.id ? text.deleting : text.delete }}
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!isLoading && filteredSchools.length === 0">
                  <td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ text.noSchoolsFound }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <p v-if="isLoading" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
          {{ text.loadingSchoolDetails }}
        </p>

        <p v-else-if="isAdmin && selectedSchoolCensusId <= 0" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
          {{ text.selectSchoolToShow }}
        </p>

        <p v-else-if="!school" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
          {{ text.schoolDetailsNotAvailable }}
        </p>

        <div v-else-if="canEdit && !isAdmin" class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm text-slate-700">
              {{ text.censusId }}
              <input :value="schoolForm.census_id" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" disabled />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.examNumber }}
              <input v-model="schoolForm.exam_no" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.schoolName }}
              <input v-model="schoolForm.sch_name" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.addressLine1 }}
              <input v-model="schoolForm.address1" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.addressLine2 }}
              <input v-model="schoolForm.address2" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.contactNumber }}
              <input v-model="schoolForm.contact_no" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.email }}
              <input v-model="schoolForm.email" type="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.website }}
              <input v-model="schoolForm.web_address" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <div class="text-sm text-slate-700 md:col-span-2">
              <span class="font-medium">{{ text.schoolCrest }}</span>
              <div class="mt-2 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-start">
                <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white">
                  <img :src="crestPreviewUrl || '/images/default_school_crest.svg'" :alt="text.schoolCrestPreviewAlt" class="h-full w-full object-contain" />
                </div>
                <div class="min-w-0 flex-1">
                  <input :key="crestInputKey" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-cyan-600 file:px-3 file:py-2 file:font-semibold file:text-white hover:file:bg-cyan-700" @change="onCrestFileChange" />
                  <p class="mt-2 text-xs text-slate-500">{{ text.schoolCrestHint }}</p>
                  <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-100" :disabled="!crestPreviewUrl" @click="removeSelectedCrest">
                      {{ text.removeCrest }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <label v-if="canToggleStatus" class="text-sm text-slate-700 md:col-span-2">
              {{ text.schoolStatus }}
              <select v-model="statusValue" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option value="active">{{ text.active }}</option>
                <option value="disabled">{{ text.disabled }}</option>
              </select>
              <span class="mt-1 block text-xs text-slate-500">{{ text.setDisabledHint }}</span>
            </label>
          </div>

          <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <label class="text-sm text-slate-700">
              {{ text.province }}
              <select v-model.number="schoolForm.pro_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" @change="onProvinceChange">
                <option :value="0">{{ text.selectProvince }}</option>
                <option v-for="row in schoolOptions.provinces" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.district }}
              <select v-model.number="schoolForm.dis_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" @change="onDistrictChange">
                <option :value="0">{{ text.selectDistrict }}</option>
                <option v-for="row in schoolOptions.districts" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.educationZone }}
              <select v-model.number="schoolForm.zone_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" @change="onZoneChange">
                <option :value="0">{{ text.selectZone }}</option>
                <option v-for="row in schoolOptions.education_zones" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.educationDivision }}
              <select v-model.number="schoolForm.div_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option :value="0">{{ text.selectDivision }}</option>
                <option v-for="row in schoolOptions.education_divisions" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.divisionalSecretariat }}
              <select v-model.number="schoolForm.div_sec_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" @change="onDivisionalSecretariatChange">
                <option :value="0">{{ text.selectDivisionalSecretariat }}</option>
                <option v-for="row in schoolOptions.divisional_secretariats" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.gramaNiladhariDivision }}
              <select v-model.number="schoolForm.gs_div_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option :value="0">{{ text.selectGnDivision }}</option>
                <option v-for="row in schoolOptions.grama_niladhari_divisions" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.schoolType }}
              <select v-model.number="schoolForm.sch_type_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option :value="0">{{ text.selectSchoolType }}</option>
                <option v-for="row in schoolOptions.school_types" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.belongsTo }}
              <select v-model.number="schoolForm.belongs_to_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option :value="0">{{ text.selectCategory }}</option>
                <option v-for="row in schoolOptions.school_belongs_to" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.gradeSpan }}
              <select v-model.number="schoolForm.grd_span_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option :value="0">{{ text.selectGradeSpan }}</option>
                <option v-for="row in schoolOptions.grade_spans" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>
          </div>

          <div class="flex items-center justify-between">
            <p v-if="isPrincipal" class="text-xs text-slate-500">{{ text.principalEditHint }}</p>
            <span v-else></span>
            <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="isSaving" @click="saveSchoolDetails">
              {{ isSaving ? text.saving : text.saveSchoolDetails }}
            </button>
          </div>
        </div>

        <div v-else-if="!isAdmin" class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 md:grid-cols-2">
          <p><strong>{{ text.censusId }}:</strong> {{ school.census_id }}</p>
          <p><strong>{{ text.examNumber }}:</strong> {{ school.exam_no || text.notAvailable }}</p>
          <p class="md:col-span-2"><strong>{{ text.schoolName }}:</strong> {{ school.sch_name || text.notAvailable }}</p>
          <div class="md:col-span-2">
            <strong>{{ text.schoolCrest }}:</strong>
            <div class="mt-2 flex h-20 w-20 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white">
              <img :src="school.crest_url || '/images/default_school_crest.svg'" :alt="text.schoolCrestPreviewAlt" class="h-full w-full object-contain" />
            </div>
          </div>
          <p class="md:col-span-2"><strong>{{ text.address }}:</strong> {{ [school.address1, school.address2].filter(Boolean).join(', ') || text.notAvailable }}</p>
          <p><strong>{{ text.contactNumber }}:</strong> {{ school.contact_no || text.notAvailable }}</p>
          <p><strong>{{ text.email }}:</strong> {{ school.email || text.notAvailable }}</p>
          <p class="md:col-span-2"><strong>{{ text.website }}:</strong> {{ school.web_address || text.notAvailable }}</p>
          <p><strong>{{ text.province }}:</strong> {{ school.province_name || text.notAvailable }}</p>
          <p><strong>{{ text.district }}:</strong> {{ school.district_name || text.notAvailable }}</p>
          <p><strong>{{ text.educationZone }}:</strong> {{ school.education_zone_name || text.notAvailable }}</p>
          <p><strong>{{ text.educationDivision }}:</strong> {{ school.education_division_name || text.notAvailable }}</p>
          <p><strong>{{ text.divisionalSecretariat }}:</strong> {{ school.divisional_secretariat_name || text.notAvailable }}</p>
          <p><strong>{{ text.gnDivision }}:</strong> {{ school.grama_niladhari_division_name || text.notAvailable }}</p>
          <p><strong>{{ text.schoolType }}:</strong> {{ school.school_type_name || text.notAvailable }}</p>
          <p><strong>{{ text.belongsTo }}:</strong> {{ school.belongs_to_name || text.notAvailable }}</p>
          <p class="md:col-span-2"><strong>{{ text.gradeSpan }}:</strong> {{ school.grade_span_name || text.notAvailable }}</p>
          <p class="md:col-span-2"><strong>{{ text.status }}:</strong> {{ school.is_deleted === 1 ? text.disabled : text.active }}</p>
        </div>
      </div>
    </section>

    <div v-if="showSchoolDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeSchoolDialog">
      <section class="max-h-[90vh] w-full max-w-6xl overflow-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-display text-xl font-bold text-slate-900">{{ isEditDialog ? text.editSchoolDialogTitle : text.addSchoolDialogTitle }}</h2>
          <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeSchoolDialog">{{ text.close }}</button>
        </div>

        <p
          v-if="dialogErrorMessage"
          ref="dialogErrorMessageRef"
          tabindex="-1"
          class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 focus:outline-none focus:ring-1 focus:ring-red-200"
        >
          {{ dialogErrorMessage }}
        </p>

        <form class="space-y-4" @submit.prevent="submitSchoolDialog">
          <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm text-slate-700">
              {{ text.censusId }}
              <input v-model="schoolForm.census_id" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.examNumber }}
              <input v-model="schoolForm.exam_no" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.schoolName }}
              <input v-model="schoolForm.sch_name" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.addressLine1 }}
              <input v-model="schoolForm.address1" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.addressLine2 }}
              <input v-model="schoolForm.address2" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.contactNumber }}
              <input v-model="schoolForm.contact_no" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.email }}
              <input v-model="schoolForm.email" type="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.website }}
              <input v-model="schoolForm.web_address" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
            </label>

            <label v-if="isEditDialog && canToggleStatus" class="text-sm text-slate-700 md:col-span-2">
              {{ text.schoolStatus }}
              <select v-model="statusValue" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option value="active">{{ text.active }}</option>
                <option value="disabled">{{ text.disabled }}</option>
              </select>
              <span class="mt-1 block text-xs text-slate-500">{{ text.setDisabledHint }}</span>
            </label>
          </div>

          <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <label class="text-sm text-slate-700">
              {{ text.province }}
              <select v-model.number="schoolForm.pro_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" @change="onProvinceChange">
                <option :value="0">{{ text.selectProvince }}</option>
                <option v-for="row in schoolOptions.provinces" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.district }}
              <select v-model.number="schoolForm.dis_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" @change="onDistrictChange">
                <option :value="0">{{ text.selectDistrict }}</option>
                <option v-for="row in schoolOptions.districts" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.educationZone }}
              <select v-model.number="schoolForm.zone_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" @change="onZoneChange">
                <option :value="0">{{ text.selectZone }}</option>
                <option v-for="row in schoolOptions.education_zones" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.educationDivision }}
              <select v-model.number="schoolForm.div_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option :value="0">{{ text.selectDivision }}</option>
                <option v-for="row in schoolOptions.education_divisions" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.divisionalSecretariat }}
              <select v-model.number="schoolForm.div_sec_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" @change="onDivisionalSecretariatChange">
                <option :value="0">{{ text.selectDivisionalSecretariat }}</option>
                <option v-for="row in schoolOptions.divisional_secretariats" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.gramaNiladhariDivision }}
              <select v-model.number="schoolForm.gs_div_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option :value="0">{{ text.selectGnDivision }}</option>
                <option v-for="row in schoolOptions.grama_niladhari_divisions" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.schoolType }}
              <select v-model.number="schoolForm.sch_type_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option :value="0">{{ text.selectSchoolType }}</option>
                <option v-for="row in schoolOptions.school_types" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.belongsTo }}
              <select v-model.number="schoolForm.belongs_to_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option :value="0">{{ text.selectCategory }}</option>
                <option v-for="row in schoolOptions.school_belongs_to" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.gradeSpan }}
              <select v-model.number="schoolForm.grd_span_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
                <option :value="0">{{ text.selectGradeSpan }}</option>
                <option v-for="row in schoolOptions.grade_spans" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>
          </div>

          <div class="flex justify-end gap-2">
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              :disabled="isSaving"
              @click="closeSchoolDialog"
            >
              {{ text.cancel }}
            </button>
            <button
              type="submit"
              class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isSaving"
            >
              {{ isSaving ? text.saving : (isEditDialog ? text.saveSchoolDetails : text.createSchool) }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'
import { useUiStore } from '../stores/ui'

interface SchoolListItem {
  id: number
  label: string
  census_id?: string
  is_deleted?: number
}

interface SchoolDetails {
  census_id: string
  exam_no: string | null
  sch_name: string | null
  address1: string | null
  address2: string | null
  contact_no: string | null
  email: string | null
  web_address: string | null
  crest_url?: string | null
  pro_id: number
  dis_id: number
  zone_id: number
  div_id: number
  div_sec_id: number
  gs_div_id: number
  sch_type_id: number
  belongs_to_id: number
  grd_span_id: number
  is_deleted: number
  province_name?: string | null
  district_name?: string | null
  education_zone_name?: string | null
  education_division_name?: string | null
  divisional_secretariat_name?: string | null
  grama_niladhari_division_name?: string | null
  school_type_name?: string | null
  belongs_to_name?: string | null
  grade_span_name?: string | null
}

interface SchoolDetailsResponse {
  school?: SchoolDetails | null
  schools?: SchoolListItem[]
  selected_school_census_id?: string | null
  can_edit?: boolean
  can_toggle_status?: boolean
  status_supported?: boolean
  message?: string
}

interface OptionRow {
  id: number
  label: string
}

interface SchoolLookupOptionsResponse {
  provinces?: OptionRow[]
  districts?: OptionRow[]
  education_zones?: OptionRow[]
  education_divisions?: OptionRow[]
  divisional_secretariats?: OptionRow[]
  grama_niladhari_divisions?: OptionRow[]
  school_types?: OptionRow[]
  school_belongs_to?: OptionRow[]
  grade_spans?: OptionRow[]
}

interface SchoolForm {
  census_id: string
  exam_no: string
  sch_name: string
  address1: string
  address2: string
  contact_no: string
  email: string
  web_address: string
  pro_id: number
  dis_id: number
  zone_id: number
  div_id: number
  div_sec_id: number
  gs_div_id: number
  sch_type_id: number
  belongs_to_id: number
  grd_span_id: number
}

type SchoolIdentityDetail = {
  schoolName: string
  crestUrl: string
}

const currentUser = getUser()
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')
const isPrincipal = computed(() => (currentUser?.role_id ?? 0) === 2 || roleName === 'principal')
const ui = useUiStore()
const text = computed(() => {
  if (ui.language === 'si') {
    return {
      schoolModule: 'පාසල',
      schoolTitle: 'පාසල් විස්තර',
      schoolSubtitle: 'ඔබගේ භූමිකාව අනුව පාසල් වාර්තා බලන්න සහ කළමනාකරණය කරන්න.',
      searchSchool: 'පාසල සොයන්න',
      searchPlaceholder: 'පාසල් නම හෝ ජනගහණ අංකය අනුව සොයන්න',
      typeToSearch: 'සෙවීමට පාසල් නම හෝ ජනගහණ අංකය ටයිප් කරන්න.',
      censusId: 'ජනගහණ අංකය',
      disabled: 'අක්‍රිය',
      active: 'සක්‍රීය',
      noSchoolsFound: 'ඔබගේ සෙවුමට ගැළපෙන පාසල් නොමැත.',
      schoolListTitle: 'පාසල් ලැයිස්තුව',
      loadingSchoolDetails: 'පාසල් විස්තර පූරණය වෙමින් පවතී...',
      selectSchoolToShow: 'දත්ත පෝරමය පෙන්වීමට සෙවුම් ලැයිස්තුවෙන් පාසලක් තෝරන්න.',
      schoolDetailsNotAvailable: 'පාසල් විස්තර ලබා ගත නොහැක.',
      examNumber: 'විභාග අංකය',
      schoolName: 'පාසල් නම',
      addressLine1: 'ලිපිනය පේළිය 1',
      addressLine2: 'ලිපිනය පේළිය 2',
      contactNumber: 'සම්බන්ධතා අංකය',
      email: 'විද්‍යුත් තැපෑල',
      website: 'වෙබ් අඩවිය',
      schoolStatus: 'පාසල් තත්ත්වය',
      schoolCrest: 'පාසල් ලාංඡනය',
      schoolCrestHint: 'JPG, PNG හෝ WebP ගොනුවක් තෝරන්න. උපරිම ප්‍රමාණය 2MB.',
      schoolCrestPreviewAlt: 'පාසල් ලාංඡන පෙරදසුන',
      noCrestSelected: 'ලාංඡනයක් නැත',
      removeCrest: 'ලාංඡනය ඉවත් කරන්න',
      setDisabledHint: 'මෙම පාසල මකා දමනවා වෙනුවට අක්‍රිය කරන්න.',
      province: 'පළාත',
      selectProvince: 'පළාත තෝරන්න',
      district: 'දිස්ත්‍රික්කය',
      selectDistrict: 'දිස්ත්‍රික්කය තෝරන්න',
      educationZone: 'අධ්‍යාපන කලාපය',
      selectZone: 'කලාපය තෝරන්න',
      educationDivision: 'අධ්‍යාපන කොට්ඨාශය',
      selectDivision: 'කොට්ඨාශය තෝරන්න',
      divisionalSecretariat: 'ප්‍රාදේශීය ලේකම් කොට්ඨාශය',
      selectDivisionalSecretariat: 'ප්‍රාදේශීය ලේකම් කොට්ඨාශය තෝරන්න',
      gramaNiladhariDivision: 'ග්‍රාම නිලධාරී කොට්ඨාශය',
      selectGnDivision: 'GN කොට්ඨාශය තෝරන්න',
      schoolType: 'පාසල් වර්ගය',
      selectSchoolType: 'පාසල් වර්ගය තෝරන්න',
      belongsTo: 'අයත් කාණ්ඩය',
      selectCategory: 'කාණ්ඩය තෝරන්න',
      gradeSpan: 'ශ්‍රේණි පරාසය',
      selectGradeSpan: 'ශ්‍රේණි පරාසය තෝරන්න',
      principalEditHint: 'විදුහල්පතිට පාසල් විස්තර සංස්කරණය කළ හැකි නමුත් පාසල අක්‍රිය කළ නොහැක.',
      saving: 'සුරකිමින්...',
      saveSchoolDetails: 'පාසල් විස්තර සුරකින්න',
      addSchool: 'පාසලක් එක් කරන්න',
      createSchool: 'පාසල එක් කරන්න',
      createSchoolHint: 'නව පාසලක් සෑදීමට ජනගහණ අංකය සහ පාසල් නම ඇතුල් කරන්න.',
      cancel: 'අවලංගු කරන්න',
      close: 'වසන්න',
      addSchoolDialogTitle: 'නව පාසල එක් කරන්න',
      editSchoolDialogTitle: 'පාසල සංස්කරණය කරන්න',
      confirmDeleteSchool: 'මෙම පාසල මැකීමට අවශ්‍යද?',
      address: 'ලිපිනය',
      gnDivision: 'GN කොට්ඨාශය',
      status: 'තත්ත්වය',
      actions: 'ක්‍රියා',
      edit: 'සංස්කරණය',
      delete: 'මකන්න',
      deleting: 'මකමින්...',
      notAvailable: 'N/A',
      unableToLoadSchoolDetails: 'දැනට පාසල් විස්තර පූරණය කළ නොහැක.',
      selectSchoolBeforeSave: 'විස්තර සුරැකීමට පෙර පාසලක් තෝරන්න.',
      schoolDetailsUpdatedSuccessfully: 'පාසල් විස්තර සාර්ථකව යාවත්කාලීන කරන ලදී.',
      unableToUpdateSchoolDetails: 'දැනට පාසල් විස්තර යාවත්කාලීන කළ නොහැක.',
      schoolAddedSuccessfully: 'පාසල සාර්ථකව එක් කරන ලදී.',
      selectCensusIdBeforeCreate: 'නව පාසලක් සෑදීමට පෙර ජනගහණ අංකයක් ඇතුල් කරන්න.',
      selectSchoolNameBeforeCreate: 'නව පාසලක් සෑදීමට පෙර පාසල් නමක් ඇතුල් කරන්න.',
      unableToAddSchool: 'දැනට පාසල එක් කළ නොහැක.',
      schoolDeletedSuccessfully: 'පාසල සාර්ථකව මකා දමන ලදී.',
      unableToDeleteSchool: 'දැනට පාසල මකා දැමිය නොහැක.',
    }
  }

  if (ui.language === 'ta') {
    return {
      schoolModule: 'பள்ளி',
      schoolTitle: 'பள்ளி விவரங்கள்',
      schoolSubtitle: 'உங்கள் பாத்திரத்தின் அடிப்படையில் பள்ளி பதிவுகளை பார்க்கவும் மற்றும் நிர்வகிக்கவும்.',
      searchSchool: 'பள்ளியை தேடுக',
      searchPlaceholder: 'பள்ளி பெயர் அல்லது கணக்கெடுப்பு எண்ணால் தேடுக',
      typeToSearch: 'தேட பள்ளி பெயர் அல்லது கணக்கெடுப்பு எண்ணை உள்ளிடவும்.',
      censusId: 'கணக்கெடுப்பு எண்',
      disabled: 'முடக்கப்பட்டது',
      active: 'செயலில்',
      noSchoolsFound: 'உங்கள் தேடலுக்கு பொருந்தும் பள்ளிகள் இல்லை.',
      schoolListTitle: 'பள்ளி பட்டியல்',
      loadingSchoolDetails: 'பள்ளி விவரங்கள் ஏற்றப்படுகிறது...',
      selectSchoolToShow: 'படிவத் தரவை காட்ட தேடல் பட்டியலில் இருந்து ஒரு பள்ளியை தேர்ந்தெடுக்கவும்.',
      schoolDetailsNotAvailable: 'பள்ளி விவரங்கள் கிடைக்கவில்லை.',
      examNumber: 'தேர்வு எண்',
      schoolName: 'பள்ளி பெயர்',
      addressLine1: 'முகவரி வரி 1',
      addressLine2: 'முகவரி வரி 2',
      contactNumber: 'தொடர்பு எண்',
      email: 'மின்னஞ்சல்',
      website: 'இணையதளம்',
      schoolStatus: 'பள்ளி நிலை',
      schoolCrest: 'பள்ளி சின்னம்',
      schoolCrestHint: 'JPG, PNG அல்லது WebP கோப்பை தேர்ந்தெடுக்கவும். அதிகபட்சம் 2MB.',
      schoolCrestPreviewAlt: 'பள்ளி சின்ன முன்பார்வு',
      noCrestSelected: 'சின்னம் இல்லை',
      removeCrest: 'சின்னத்தை அகற்று',
      setDisabledHint: 'இந்த பள்ளியை நீக்குவதற்குப் பதிலாக முடக்கமாக அமைக்கவும்.',
      province: 'மாகாணம்',
      selectProvince: 'மாகாணத்தை தேர்ந்தெடுக்கவும்',
      district: 'மாவட்டம்',
      selectDistrict: 'மாவட்டத்தை தேர்ந்தெடுக்கவும்',
      educationZone: 'கல்வி வலயம்',
      selectZone: 'வலயத்தை தேர்ந்தெடுக்கவும்',
      educationDivision: 'கல்வி பிரிவு',
      selectDivision: 'பிரிவை தேர்ந்தெடுக்கவும்',
      divisionalSecretariat: 'பிரதேச செயலாளர் பிரிவு',
      selectDivisionalSecretariat: 'பிரதேச செயலாளர் பிரிவை தேர்ந்தெடுக்கவும்',
      gramaNiladhariDivision: 'கிராம சேவகர் பிரிவு',
      selectGnDivision: 'GN பிரிவை தேர்ந்தெடுக்கவும்',
      schoolType: 'பள்ளி வகை',
      selectSchoolType: 'பள்ளி வகையை தேர்ந்தெடுக்கவும்',
      belongsTo: 'சேர்ந்தது',
      selectCategory: 'வகையை தேர்ந்தெடுக்கவும்',
      gradeSpan: 'தர நிலை வரம்பு',
      selectGradeSpan: 'தர நிலை வரம்பை தேர்ந்தெடுக்கவும்',
      principalEditHint: 'அதிபர் பள்ளி விவரங்களை திருத்தலாம், ஆனால் பள்ளியை முடக்க முடியாது.',
      saving: 'சேமிக்கப்படுகிறது...',
      saveSchoolDetails: 'பள்ளி விவரங்களை சேமிக்கவும்',
      addSchool: 'பள்ளி சேர்க்கவும்',
      createSchool: 'பள்ளியை உருவாக்கவும்',
      createSchoolHint: 'புதிய பள்ளி உருவாக்க கணக்கெடுப்பு எண் மற்றும் பள்ளி பெயரை உள்ளிடவும்.',
      cancel: 'ரத்து செய்',
      close: 'மூடு',
      addSchoolDialogTitle: 'புதிய பள்ளியை சேர்க்கவும்',
      editSchoolDialogTitle: 'பள்ளியை திருத்தவும்',
      confirmDeleteSchool: 'இந்த பள்ளியை நீக்க விரும்புகிறீர்களா?',
      address: 'முகவரி',
      gnDivision: 'GN பிரிவு',
      status: 'நிலை',
      actions: 'செயல்கள்',
      edit: 'திருத்து',
      delete: 'நீக்கு',
      deleting: 'நீக்குகிறது...',
      notAvailable: 'N/A',
      unableToLoadSchoolDetails: 'தற்போது பள்ளி விவரங்களை ஏற்ற முடியவில்லை.',
      selectSchoolBeforeSave: 'விவரங்களை சேமிப்பதற்கு முன் ஒரு பள்ளியை தேர்ந்தெடுக்கவும்.',
      schoolDetailsUpdatedSuccessfully: 'பள்ளி விவரங்கள் வெற்றிகரமாக புதுப்பிக்கப்பட்டன.',
      unableToUpdateSchoolDetails: 'தற்போது பள்ளி விவரங்களை புதுப்பிக்க முடியவில்லை.',
      schoolAddedSuccessfully: 'பள்ளி வெற்றிகரமாக சேர்க்கப்பட்டது.',
      selectCensusIdBeforeCreate: 'புதிய பள்ளியை உருவாக்கும் முன் கணக்கெடுப்பு எண்ணை உள்ளிடவும்.',
      selectSchoolNameBeforeCreate: 'புதிய பள்ளியை உருவாக்கும் முன் பள்ளி பெயரை உள்ளிடவும்.',
      unableToAddSchool: 'தற்போது பள்ளியை சேர்க்க முடியவில்லை.',
      schoolDeletedSuccessfully: 'பள்ளி வெற்றிகரமாக நீக்கப்பட்டது.',
      unableToDeleteSchool: 'தற்போது பள்ளியை நீக்க முடியவில்லை.',
    }
  }

  return {
    schoolTitle: 'School Details',
    searchSchool: 'Search School',
    searchPlaceholder: 'Search by school name or census ID',
    typeToSearch: 'Type school name or census ID to search.',
    censusId: 'Census ID',
    disabled: 'Disabled',
    active: 'Active',
    noSchoolsFound: 'No schools match your search.',
    schoolListTitle: 'School List',
    loadingSchoolDetails: 'Loading school details...',
    selectSchoolToShow: 'Select a school from the search list to show form data.',
    schoolDetailsNotAvailable: 'School details are not available.',
    examNumber: 'Exam Number',
    schoolName: 'School Name',
    addressLine1: 'Address Line 1',
    addressLine2: 'Address Line 2',
    contactNumber: 'Contact Number',
    email: 'Email',
    website: 'Website',
    schoolStatus: 'School Status',
    schoolCrest: 'School Crest',
    schoolCrestHint: 'Choose a JPG, PNG, or WebP file. Maximum size 2MB.',
    schoolCrestPreviewAlt: 'School crest preview',
    noCrestSelected: 'No crest',
    removeCrest: 'Remove Crest',
    setDisabledHint: 'Set to Disabled instead of deleting this school.',
    province: 'Province',
    selectProvince: 'Select province',
    district: 'District',
    selectDistrict: 'Select district',
    educationZone: 'Education Zone',
    selectZone: 'Select zone',
    educationDivision: 'Education Division',
    selectDivision: 'Select division',
    divisionalSecretariat: 'Divisional Secretariat',
    selectDivisionalSecretariat: 'Select divisional secretariat',
    gramaNiladhariDivision: 'Grama Niladhari Division',
    selectGnDivision: 'Select GN division',
    schoolType: 'School Type',
    selectSchoolType: 'Select school type',
    belongsTo: 'Belongs To',
    selectCategory: 'Select category',
    gradeSpan: 'Grade Span',
    selectGradeSpan: 'Select grade span',
    principalEditHint: 'Principal can edit school details but cannot disable the school.',
    saving: 'Saving...',
    saveSchoolDetails: 'Save School Details',
    addSchool: 'Add School',
    createSchool: 'Create School',
    createSchoolHint: 'Enter census ID and school name to create a new school.',
    cancel: 'Cancel',
    close: 'Close',
    addSchoolDialogTitle: 'Add New School',
    editSchoolDialogTitle: 'Edit School',
    confirmDeleteSchool: 'Do you want to delete this school?',
    address: 'Address',
    gnDivision: 'GN Division',
    status: 'Status',
    actions: 'Actions',
    edit: 'Edit',
    delete: 'Delete',
    deleting: 'Deleting...',
    notAvailable: 'N/A',
    unableToLoadSchoolDetails: 'Unable to load school details right now.',
    selectSchoolBeforeSave: 'Select a school before saving details.',
    schoolDetailsUpdatedSuccessfully: 'School details updated successfully.',
    unableToUpdateSchoolDetails: 'Unable to update school details right now.',
    schoolAddedSuccessfully: 'School added successfully.',
    selectCensusIdBeforeCreate: 'Enter a census ID before creating a school.',
    selectSchoolNameBeforeCreate: 'Enter a school name before creating a school.',
    unableToAddSchool: 'Unable to add school right now.',
    schoolDeletedSuccessfully: 'School deleted successfully.',
    unableToDeleteSchool: 'Unable to delete school right now.',
  }
})

const selectedSchoolCensusId = ref<number>(getSchoolContextCensusId() ?? 0)
const searchKeyword = ref('')

const isLoading = ref(false)
const isSaving = ref(false)
const errorText = ref('')
const noticeText = ref('')

const canEdit = ref(false)
const canToggleStatus = ref(false)
const school = ref<SchoolDetails | null>(null)
const schoolList = ref<SchoolListItem[]>([])
const statusValue = ref<'active' | 'disabled'>('active')
const isCreatingSchool = ref(false)
const isEditDialog = ref(false)
const showSchoolDialog = computed(() => isCreatingSchool.value || isEditDialog.value)
const dialogErrorMessage = ref('')
const dialogErrorMessageRef = ref<HTMLElement | null>(null)
const deletingSchoolId = ref<number | null>(null)
const crestFile = ref<File | null>(null)
const crestPreviewUrl = ref('')
const removeCrest = ref(false)
const crestInputKey = ref(0)

const schoolForm = reactive<SchoolForm>({
  census_id: '',
  exam_no: '',
  sch_name: '',
  address1: '',
  address2: '',
  contact_no: '',
  email: '',
  web_address: '',
  pro_id: 0,
  dis_id: 0,
  zone_id: 0,
  div_id: 0,
  div_sec_id: 0,
  gs_div_id: 0,
  sch_type_id: 0,
  belongs_to_id: 0,
  grd_span_id: 0,
})

const schoolOptions = reactive({
  provinces: [] as OptionRow[],
  districts: [] as OptionRow[],
  education_zones: [] as OptionRow[],
  education_divisions: [] as OptionRow[],
  divisional_secretariats: [] as OptionRow[],
  grama_niladhari_divisions: [] as OptionRow[],
  school_types: [] as OptionRow[],
  school_belongs_to: [] as OptionRow[],
  grade_spans: [] as OptionRow[],
})

const filteredSchools = computed(() => {
  if (!isAdmin.value) {
    return schoolList.value
  }

  const keyword = searchKeyword.value.trim().toLowerCase()
  if (keyword === '') {
    return schoolList.value
  }

  return schoolList.value.filter((item) => {
    const label = String(item.label ?? '').toLowerCase()
    const censusId = String(item.id)
    return label.includes(keyword) || censusId.includes(keyword)
  })
})

const localizeApiMessage = (message: string): string => {
  const normalizedMessage = message.trim()

  if (normalizedMessage === '') {
    return ''
  }

  if (ui.language === 'ta') {
    if (normalizedMessage === 'Unauthorized.') return 'அனுமதி இல்லை.'
    if (normalizedMessage === 'Forbidden.') return 'இந்த செயலை செய்ய உங்களுக்கு அனுமதி இல்லை.'
    if (normalizedMessage === 'Select a school first.') return 'முதலில் ஒரு பள்ளியை தேர்ந்தெடுக்கவும்.'
    if (normalizedMessage === 'School is not assigned for this user.') return 'இந்த பயனருக்கு பள்ளி ஒதுக்கப்படவில்லை.'
    if (normalizedMessage === 'School details not found.') return 'பள்ளி விவரங்கள் கிடைக்கவில்லை.'
    if (normalizedMessage === 'Validation failed.') return 'சரிபார்ப்பு தோல்வியடைந்தது.'
    if (normalizedMessage === 'No update fields provided.') return 'புதுப்பிப்பதற்கு புலங்கள் வழங்கப்படவில்லை.'
    if (normalizedMessage === 'Only admin can change school status.') return 'பள்ளி நிலையை மாற்ற நிர்வாகிக்கு மட்டும் அனுமதி உண்டு.'
    if (normalizedMessage === 'School status flag is not available.') return 'பள்ளி நிலை குறியீடு இல்லை.'
    if (normalizedMessage === 'School details table is missing.') return 'பள்ளி விவர அட்டவணை இல்லை.'
    if (normalizedMessage === 'School details updated successfully.') return text.value.schoolDetailsUpdatedSuccessfully
    if (normalizedMessage === 'School added successfully.') return text.value.schoolAddedSuccessfully
    if (normalizedMessage === 'School deleted successfully.') return text.value.schoolDeletedSuccessfully
    if (normalizedMessage === 'A school already exists with this census ID. You can change its status to Active.') return 'இந்த கணக்கெடுப்பு எண்ணுடன் ஏற்கனவே ஒரு பள்ளி உள்ளது.'
  }

  if (ui.language === 'si') {
    if (normalizedMessage === 'Unauthorized.') return 'අවසර නොමැත.'
    if (normalizedMessage === 'Forbidden.') return 'මෙම ක්‍රියාවට ඔබට අවසර නැත.'
    if (normalizedMessage === 'Select a school first.') return 'පළමුව පාසලක් තෝරන්න.'
    if (normalizedMessage === 'School is not assigned for this user.') return 'මෙම පරිශීලකයාට පාසලක් පවරා නොමැත.'
    if (normalizedMessage === 'School details not found.') return 'පාසල් විස්තර සොයාගත නොහැක.'
    if (normalizedMessage === 'Validation failed.') return 'වලංගු කිරීම අසාර්ථක විය.'
    if (normalizedMessage === 'No update fields provided.') return 'යාවත්කාලීන කිරීමට ක්ෂේත්‍ර ලබා දී නොමැත.'
    if (normalizedMessage === 'Only admin can change school status.') return 'පාසල් තත්ත්වය වෙනස් කළ හැක්කේ පරිපාලකයාට පමණි.'
    if (normalizedMessage === 'School status flag is not available.') return 'පාසල් තත්ත්ව ධජය නොමැත.'
    if (normalizedMessage === 'School details table is missing.') return 'පාසල් විස්තර වගුව නොමැත.'
    if (normalizedMessage === 'School details updated successfully.') return text.value.schoolDetailsUpdatedSuccessfully
    if (normalizedMessage === 'School added successfully.') return text.value.schoolAddedSuccessfully
    if (normalizedMessage === 'School deleted successfully.') return text.value.schoolDeletedSuccessfully
    if (normalizedMessage === 'A school already exists with this census ID. You can change its status to Active.') return 'මෙම ජනගහණ අංකය සමඟ පාසලක් දැනටමත් ඇත.'
  }

  return normalizedMessage
}

const extractApiMessage = (reason: unknown): string => {
  if (typeof reason === 'object' && reason !== null && 'response' in reason) {
    const response = (reason as { response?: { data?: { message?: string } } }).response
    if (typeof response?.data?.message === 'string' && response.data.message.trim() !== '') {
      return localizeApiMessage(response.data.message)
    }
  }

  return ''
}

const focusDialogError = async (): Promise<void> => {
  await nextTick()
  if (dialogErrorMessageRef.value) {
    dialogErrorMessageRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' })
    dialogErrorMessageRef.value.focus({ preventScroll: true })
  }
}

const setDialogError = async (message: string): Promise<void> => {
  dialogErrorMessage.value = message
  await focusDialogError()
}

const clearDialogError = (): void => {
  dialogErrorMessage.value = ''
}
const buildSchoolContextRequestConfig = (): { params?: Record<string, string>; headers?: Record<string, string> } | undefined => {
  if (!isAdmin.value) {
    return undefined
  }

  const selected = Number(selectedSchoolCensusId.value)
  if (!Number.isFinite(selected) || selected <= 0) {
    return undefined
  }

  const censusValue = String(selected)
  return {
    params: { school_census_id: censusValue },
    headers: { 'X-School-Census-Id': censusValue },
  }
}

const resetCrestSelection = (): void => {
  crestFile.value = null
  removeCrest.value = false
  crestInputKey.value += 1
}

const syncCrestPreview = (url: string | null | undefined): void => {
  crestPreviewUrl.value = String(url ?? '').trim()
}

const removeSelectedCrest = (): void => {
  crestFile.value = null
  removeCrest.value = true
  crestPreviewUrl.value = ''
  crestInputKey.value += 1
}

const onCrestFileChange = (event: Event): void => {
  const input = event.target as HTMLInputElement | null
  const file = input?.files?.[0] ?? null

  if (!file) {
    return
  }

  crestFile.value = file
  removeCrest.value = false
  crestPreviewUrl.value = URL.createObjectURL(file)
}

const notifySchoolIdentityUpdated = (details: SchoolDetails | null): void => {
  window.dispatchEvent(new CustomEvent<SchoolIdentityDetail>('sds:school-identity-updated', {
    detail: {
      schoolName: String(details?.sch_name ?? '').trim(),
      crestUrl: String(details?.crest_url ?? '').trim(),
    },
  }))
}

const resetSchoolForm = (): void => {
  schoolForm.census_id = ''
  schoolForm.exam_no = ''
  schoolForm.sch_name = ''
  schoolForm.address1 = ''
  schoolForm.address2 = ''
  schoolForm.contact_no = ''
  schoolForm.email = ''
  schoolForm.web_address = ''
  schoolForm.pro_id = 0
  schoolForm.dis_id = 0
  schoolForm.zone_id = 0
  schoolForm.div_id = 0
  schoolForm.div_sec_id = 0
  schoolForm.gs_div_id = 0
  schoolForm.sch_type_id = 0
  schoolForm.belongs_to_id = 0
  schoolForm.grd_span_id = 0
  statusValue.value = 'active'
  crestPreviewUrl.value = ''
  resetCrestSelection()
}

const applySchoolToForm = (details: SchoolDetails): void => {
  schoolForm.census_id = details.census_id ?? ''
  schoolForm.exam_no = details.exam_no ?? ''
  schoolForm.sch_name = details.sch_name ?? ''
  schoolForm.address1 = details.address1 ?? ''
  schoolForm.address2 = details.address2 ?? ''
  schoolForm.contact_no = details.contact_no ?? ''
  schoolForm.email = details.email ?? ''
  schoolForm.web_address = details.web_address ?? ''
  schoolForm.pro_id = Number(details.pro_id ?? 0)
  schoolForm.dis_id = Number(details.dis_id ?? 0)
  schoolForm.zone_id = Number(details.zone_id ?? 0)
  schoolForm.div_id = Number(details.div_id ?? 0)
  schoolForm.div_sec_id = Number(details.div_sec_id ?? 0)
  schoolForm.gs_div_id = Number(details.gs_div_id ?? 0)
  schoolForm.sch_type_id = Number(details.sch_type_id ?? 0)
  schoolForm.belongs_to_id = Number(details.belongs_to_id ?? 0)
  schoolForm.grd_span_id = Number(details.grd_span_id ?? 0)
  statusValue.value = Number(details.is_deleted ?? 0) === 1 ? 'disabled' : 'active'
  syncCrestPreview(details.crest_url)
  resetCrestSelection()
}

const ensureOptionValue = (value: number, options: OptionRow[]): number => {
  return options.some((row) => row.id === value) ? value : 0
}

const syncFormWithOptions = (): void => {
  schoolForm.pro_id = ensureOptionValue(schoolForm.pro_id, schoolOptions.provinces)
  schoolForm.dis_id = ensureOptionValue(schoolForm.dis_id, schoolOptions.districts)
  schoolForm.zone_id = ensureOptionValue(schoolForm.zone_id, schoolOptions.education_zones)
  schoolForm.div_id = ensureOptionValue(schoolForm.div_id, schoolOptions.education_divisions)
  schoolForm.div_sec_id = ensureOptionValue(schoolForm.div_sec_id, schoolOptions.divisional_secretariats)
  schoolForm.gs_div_id = ensureOptionValue(schoolForm.gs_div_id, schoolOptions.grama_niladhari_divisions)
  schoolForm.sch_type_id = ensureOptionValue(schoolForm.sch_type_id, schoolOptions.school_types)
  schoolForm.belongs_to_id = ensureOptionValue(schoolForm.belongs_to_id, schoolOptions.school_belongs_to)
  schoolForm.grd_span_id = ensureOptionValue(schoolForm.grd_span_id, schoolOptions.grade_spans)
}

const clearSchoolOptions = (): void => {
  schoolOptions.provinces = []
  schoolOptions.districts = []
  schoolOptions.education_zones = []
  schoolOptions.education_divisions = []
  schoolOptions.divisional_secretariats = []
  schoolOptions.grama_niladhari_divisions = []
  schoolOptions.school_types = []
  schoolOptions.school_belongs_to = []
  schoolOptions.grade_spans = []
}

const loadSchoolLookupOptions = async (): Promise<void> => {
  if (!canEdit.value || (!isCreatingSchool.value && !isEditDialog.value && school.value === null)) {
    clearSchoolOptions()
    return
  }

  try {
    const params: Record<string, string> = {}
    if (schoolForm.pro_id > 0) params.province_id = String(schoolForm.pro_id)
    if (schoolForm.dis_id > 0) params.district_id = String(schoolForm.dis_id)
    if (schoolForm.zone_id > 0) params.zone_id = String(schoolForm.zone_id)
    if (schoolForm.div_sec_id > 0) params.divisional_secretariat_id = String(schoolForm.div_sec_id)

    const { data } = await api.get<SchoolLookupOptionsResponse>('/school/details/options', {
      params,
    })

    schoolOptions.provinces = Array.isArray(data.provinces) ? data.provinces : []
    schoolOptions.districts = Array.isArray(data.districts) ? data.districts : []
    schoolOptions.education_zones = Array.isArray(data.education_zones) ? data.education_zones : []
    schoolOptions.education_divisions = Array.isArray(data.education_divisions) ? data.education_divisions : []
    schoolOptions.divisional_secretariats = Array.isArray(data.divisional_secretariats) ? data.divisional_secretariats : []
    schoolOptions.grama_niladhari_divisions = Array.isArray(data.grama_niladhari_divisions) ? data.grama_niladhari_divisions : []
    schoolOptions.school_types = Array.isArray(data.school_types) ? data.school_types : []
    schoolOptions.school_belongs_to = Array.isArray(data.school_belongs_to) ? data.school_belongs_to : []
    schoolOptions.grade_spans = Array.isArray(data.grade_spans) ? data.grade_spans : []

    syncFormWithOptions()
  } catch {
    clearSchoolOptions()
  }
}

const loadSchoolDetails = async (keepMessages: boolean = false): Promise<void> => {
  isLoading.value = true
  if (!keepMessages) {
    errorText.value = ''
    noticeText.value = ''
  }

  try {
    const contextConfig = buildSchoolContextRequestConfig()
    const { data } = await api.get<SchoolDetailsResponse>('/school/details', contextConfig)

    canEdit.value = data.can_edit === true
    canToggleStatus.value = data.can_toggle_status === true && data.status_supported !== false
    schoolList.value = Array.isArray(data.schools) ? data.schools : []

    if (isAdmin.value) {
      const selected = Number(selectedSchoolCensusId.value)
      if (selected > 0) {
        const existsInList = schoolList.value.some((item) => item.id === selected)
        if (!existsInList) {
          selectedSchoolCensusId.value = 0
          setSchoolContextCensusId(null)
        }
      }

      if (selectedSchoolCensusId.value <= 0 && typeof data.selected_school_census_id === 'string' && data.selected_school_census_id.trim() !== '') {
        const parsed = Number(data.selected_school_census_id)
        if (Number.isFinite(parsed) && parsed > 0) {
          selectedSchoolCensusId.value = parsed
          setSchoolContextCensusId(parsed)
        }
      }
    }

    if (typeof data.message === 'string' && data.message.trim() !== '' && !keepMessages) {
      const localizedMessage = localizeApiMessage(data.message)
      if (!(isAdmin.value && Number(selectedSchoolCensusId.value) <= 0)) {
        noticeText.value = localizedMessage
      }
    }

    school.value = data.school ?? null

    if (school.value) {
      applySchoolToForm(school.value)
      notifySchoolIdentityUpdated(school.value)
      await loadSchoolLookupOptions()
    } else {
      resetSchoolForm()
      clearSchoolOptions()
      notifySchoolIdentityUpdated(null)
    }
  } catch (reason) {
    school.value = null
    canEdit.value = false
    canToggleStatus.value = false
    resetSchoolForm()
    clearSchoolOptions()
    notifySchoolIdentityUpdated(null)
    errorText.value = extractApiMessage(reason) || text.value.unableToLoadSchoolDetails
  } finally {
    isLoading.value = false
  }
}

const selectSchool = async (schoolId: number): Promise<void> => {
  isCreatingSchool.value = false
  isEditDialog.value = false
  selectedSchoolCensusId.value = schoolId
  setSchoolContextCensusId(schoolId)
  await loadSchoolDetails()
}

const openEditSchoolDialog = async (schoolId: number): Promise<void> => {
  isCreatingSchool.value = false
  isEditDialog.value = true
  clearDialogError()
  selectedSchoolCensusId.value = schoolId
  setSchoolContextCensusId(schoolId)
  await loadSchoolDetails()

  if (school.value === null) {
    isEditDialog.value = false
  }
}

const startCreateSchool = async (): Promise<void> => {
  if (!isAdmin.value || !canEdit.value) {
    return
  }

  isCreatingSchool.value = true
  isEditDialog.value = false
  statusValue.value = 'active'
  clearDialogError()
  errorText.value = ''
  noticeText.value = ''
  resetSchoolForm()
  clearSchoolOptions()
  await loadSchoolLookupOptions()
}

const closeSchoolDialog = async (): Promise<void> => {
  if (!isCreatingSchool.value && !isEditDialog.value) {
    return
  }

  isCreatingSchool.value = false
  isEditDialog.value = false
  clearDialogError()

  if (school.value) {
    applySchoolToForm(school.value)
    await loadSchoolLookupOptions()
  } else {
    resetSchoolForm()
    clearSchoolOptions()
  }
}

const createSchool = async (): Promise<void> => {
  const censusId = schoolForm.census_id.trim()
  const schoolName = schoolForm.sch_name.trim()

  if (censusId === '') {
    await setDialogError(text.value.selectCensusIdBeforeCreate)
    return
  }

  if (schoolName === '') {
    await setDialogError(text.value.selectSchoolNameBeforeCreate)
    return
  }

  isSaving.value = true
  clearDialogError()
  noticeText.value = ''

  try {
    const payload: Record<string, string | number> = {
      census_id: censusId,
      sch_name: schoolName,
      exam_no: schoolForm.exam_no,
      address1: schoolForm.address1,
      address2: schoolForm.address2,
      contact_no: schoolForm.contact_no,
      email: schoolForm.email,
      web_address: schoolForm.web_address,
      pro_id: schoolForm.pro_id,
      dis_id: schoolForm.dis_id,
      zone_id: schoolForm.zone_id,
      div_id: schoolForm.div_id,
      div_sec_id: schoolForm.div_sec_id,
      gs_div_id: schoolForm.gs_div_id,
      sch_type_id: schoolForm.sch_type_id,
      belongs_to_id: schoolForm.belongs_to_id,
      grd_span_id: schoolForm.grd_span_id,
    }

    const { data } = await api.post<{ message?: string; school?: SchoolDetails | null }>('/school/details', payload)

    const createdCensusId = Number(data.school?.census_id ?? censusId)
    if (Number.isFinite(createdCensusId) && createdCensusId > 0) {
      selectedSchoolCensusId.value = createdCensusId
      setSchoolContextCensusId(createdCensusId)
    } else {
      selectedSchoolCensusId.value = 0
      setSchoolContextCensusId(null)
    }

    isCreatingSchool.value = false
    isEditDialog.value = false
    noticeText.value = typeof data.message === 'string' && data.message.trim() !== ''
      ? localizeApiMessage(data.message)
      : text.value.schoolAddedSuccessfully

    await loadSchoolDetails(true)
  } catch (reason) {
    await setDialogError(extractApiMessage(reason) || text.value.unableToAddSchool)
  } finally {
    isSaving.value = false
  }
}

const submitSchoolDialog = async (): Promise<void> => {
  if (isCreatingSchool.value) {
    await createSchool()
    return
  }

  if (isEditDialog.value) {
    await saveSchoolDetails(true, true)
  }
}
const deleteSchool = async (schoolItem: SchoolListItem): Promise<void> => {
  if (!isAdmin.value) {
    return
  }

  const schoolId = Number(schoolItem.id)
  if (!Number.isFinite(schoolId) || schoolId <= 0) {
    return
  }

  if (!window.confirm(text.value.confirmDeleteSchool)) {
    return
  }

  deletingSchoolId.value = schoolId
  errorText.value = ''

  try {
    const { data } = await api.delete<{ message?: string }>('/school/details', {
      params: { school_census_id: String(schoolId) },
    })

    noticeText.value = typeof data.message === 'string' && data.message.trim() !== ''
      ? localizeApiMessage(data.message)
      : text.value.schoolDeletedSuccessfully

    if (Number(selectedSchoolCensusId.value) === schoolId) {
      selectedSchoolCensusId.value = 0
      setSchoolContextCensusId(null)
      school.value = null
      isEditDialog.value = false
      clearDialogError()
      resetSchoolForm()
      clearSchoolOptions()
    }

    await loadSchoolDetails(true)
  } catch (reason) {
    errorText.value = extractApiMessage(reason) || text.value.unableToDeleteSchool
  } finally {
    deletingSchoolId.value = null
  }
}
const onProvinceChange = async (): Promise<void> => {
  schoolForm.dis_id = 0
  schoolForm.zone_id = 0
  schoolForm.div_id = 0
  schoolForm.div_sec_id = 0
  schoolForm.gs_div_id = 0
  await loadSchoolLookupOptions()
}

const onDistrictChange = async (): Promise<void> => {
  schoolForm.zone_id = 0
  schoolForm.div_id = 0
  schoolForm.div_sec_id = 0
  schoolForm.gs_div_id = 0
  await loadSchoolLookupOptions()
}

const onZoneChange = async (): Promise<void> => {
  schoolForm.div_id = 0
  await loadSchoolLookupOptions()
}

const onDivisionalSecretariatChange = async (): Promise<void> => {
  schoolForm.gs_div_id = 0
  await loadSchoolLookupOptions()
}

const saveSchoolDetails = async (useDialogError: boolean = false, closeDialogAfterSave: boolean = false): Promise<void> => {
  if (!canEdit.value) {
    return
  }

  if (school.value === null) {
    return
  }

  if (isAdmin.value && Number(selectedSchoolCensusId.value) <= 0) {
    if (useDialogError) {
      await setDialogError(text.value.selectSchoolBeforeSave)
    } else {
      errorText.value = text.value.selectSchoolBeforeSave
    }
    return
  }

  isSaving.value = true
  if (useDialogError) {
    clearDialogError()
  } else {
    errorText.value = ''
  }
  noticeText.value = ''

  try {
    const payload = new FormData()
    payload.append('exam_no', schoolForm.exam_no)
    payload.append('sch_name', schoolForm.sch_name)
    payload.append('address1', schoolForm.address1)
    payload.append('address2', schoolForm.address2)
    payload.append('contact_no', schoolForm.contact_no)
    payload.append('email', schoolForm.email)
    payload.append('web_address', schoolForm.web_address)
    payload.append('pro_id', String(schoolForm.pro_id))
    payload.append('dis_id', String(schoolForm.dis_id))
    payload.append('zone_id', String(schoolForm.zone_id))
    payload.append('div_id', String(schoolForm.div_id))
    payload.append('div_sec_id', String(schoolForm.div_sec_id))
    payload.append('gs_div_id', String(schoolForm.gs_div_id))
    payload.append('sch_type_id', String(schoolForm.sch_type_id))
    payload.append('belongs_to_id', String(schoolForm.belongs_to_id))
    payload.append('grd_span_id', String(schoolForm.grd_span_id))

    if (canToggleStatus.value) {
      payload.append('is_deleted', statusValue.value === 'disabled' ? '1' : '0')
    }

    if (removeCrest.value) {
      payload.append('remove_crest', '1')
    }

    if (crestFile.value) {
      payload.append('crest_image', crestFile.value)
    }

    const contextConfig = buildSchoolContextRequestConfig()
    const { data } = await api.put<{ message?: string; school?: SchoolDetails | null }>('/school/details', payload, {
      ...contextConfig,
      headers: {
        ...(contextConfig?.headers ?? {}),
        'Content-Type': 'multipart/form-data',
      },
    })

    if (data.school) {
      school.value = data.school
      applySchoolToForm(data.school)
    }

    noticeText.value = typeof data.message === 'string' && data.message.trim() !== ''
      ? localizeApiMessage(data.message)
      : text.value.schoolDetailsUpdatedSuccessfully

    if (useDialogError && closeDialogAfterSave) {
      isEditDialog.value = false
      clearDialogError()
    }

    await loadSchoolDetails(true)
  } catch (reason) {
    const message = extractApiMessage(reason) || text.value.unableToUpdateSchoolDetails
    if (useDialogError) {
      await setDialogError(message)
    } else {
      errorText.value = message
    }
  } finally {
    isSaving.value = false
  }
}
onMounted(async () => {
  await loadSchoolDetails()
})
</script>



























