<template>
  <div class="space-y-5">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h1 class="mt-2 font-display text-2xl font-bold text-slate-900">{{ isReportView ? text.studentReports : text.studentsTitle }}</h1>
    </header>

    <section v-if="isReportView" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 class="font-display text-xl font-bold">{{ text.studentReports }}</h2>
          <p class="text-sm text-slate-500">{{ text.studentReportFilterHelp }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="loadingReport" @click="resetReportFilters">
            {{ text.reset }}
          </button>
          <button class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="loadingReport" @click="loadStudentReport">
            {{ loadingReport ? text.loadingReport : text.viewReport }}
          </button>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <label v-if="isAdmin" class="text-sm text-slate-700">
          {{ text.school }}
          <select :value="reportFilters.school_census_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onReportSchoolChange">
            <option :value="0">{{ text.allSchools }}</option>
            <option v-for="row in schools" :key="`report-school-${row.id}`" :value="row.id">{{ row.label }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.search }}
          <input v-model="reportFilters.q" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :placeholder="text.searchPlaceholder" @keyup.enter="loadStudentReport" />
        </label>

        <label class="text-sm text-slate-700">
          {{ text.gender }}
          <select v-model.number="reportFilters.gender_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option :value="0">{{ text.all }}</option>
            <option v-for="row in genderOptions" :key="`report-gender-${row.id}`" :value="row.id">{{ row.label }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.ethnicGroup }}
          <select v-model.number="reportFilters.ethnic_group_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option :value="0">{{ text.all }}</option>
            <option v-for="row in ethnicGroups" :key="`report-ethnic-${row.id}`" :value="row.id">{{ row.label }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.religion }}
          <select v-model.number="reportFilters.religion_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option :value="0">{{ text.all }}</option>
            <option v-for="row in religions" :key="`report-religion-${row.id}`" :value="row.id">{{ row.label }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.academicYear }}
          <select v-model.number="reportFilters.year" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="isAdmin && reportFilters.school_census_id <= 0">
            <option :value="0">{{ text.allYears }}</option>
            <option v-for="year in reportAcademicYears" :key="`report-year-${year}`" :value="year">{{ year }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.grade }}
          <select v-model.number="reportFilters.grade_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="reportFilters.year <= 0">
            <option :value="0">{{ text.allGrades }}</option>
            <option v-for="row in reportGrades" :key="`report-grade-${row.grade_id}`" :value="row.grade_id">{{ row.grade }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700">
          {{ text.classLabel }}
          <select v-model.number="reportFilters.class_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="reportFilters.year <= 0 || reportFilters.grade_id <= 0">
            <option :value="0">{{ text.allClasses }}</option>
            <option v-for="row in reportClasses" :key="`report-class-${row.class_id}`" :value="row.class_id">{{ row.class }}</option>
          </select>
        </label>
      </div>

      <div class="mt-5 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-600">
          {{ text.totalMatches }}
          <span class="ml-2 text-lg font-semibold text-slate-900">{{ reportRows.length }}</span>
        </p>
        <button
          class="whitespace-nowrap rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="downloadingReport"
          @click="downloadStudentReport"
        >
          {{ downloadingReport ? text.downloadingTemplate : text.downloadReport }}
        </button>
      </div>

      <div class="mt-4 overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.admissionNoShort }}</th>
              <th v-if="isAdmin" class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.school }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.nameWithInitials }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.gender }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.gradeClass }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.year }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.phone }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.dob }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="!loadingReport && reportRows.length === 0">
              <td :colspan="isAdmin ? 8 : 7" class="px-3 py-6 text-center text-slate-500">{{ text.noReportStudentsFound }}</td>
            </tr>
            <tr v-for="student in reportRows" :key="`report-student-${student.std_id}`" class="hover:bg-slate-50">
              <td class="px-3 py-2 font-medium text-slate-800">{{ student.index_no }}</td>
              <td v-if="isAdmin" class="px-3 py-2 text-slate-700">
                <span :title="censusTooltip(student.census_id)">{{ student.school_name || '-' }}</span>
              </td>
              <td class="px-3 py-2 text-slate-700">{{ student.name_with_initials }}</td>
              <td class="px-3 py-2 text-slate-700">{{ studentGenderLabel(student.gender_id) }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.grade_class }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.current_year || '-' }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.phone_no || '-' }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.dob || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="reportErrorMessage" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ reportErrorMessage }}
      </p>
    </section>

    <section v-else class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <input
          v-model="search"
          type="text"
          :placeholder="text.searchPlaceholder"
          :class="[
            'w-full rounded-xl border border-slate-300 px-4 py-2 text-sm outline-none ring-cyan-500 focus:ring-2',
            isAdmin ? 'md:max-w-[150px] lg:max-w-[180px]' : 'md:max-w-[220px] lg:max-w-[260px]',
          ]"
          @keyup.enter="loadStudents(1)"
        />
        <div class="flex flex-wrap items-center gap-2 md:justify-end xl:flex-nowrap">
          <select
            v-if="isAdmin"
            v-model.number="adminSchoolContextCensusId"
            class="rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none ring-cyan-500 focus:ring-2 xl:max-w-[220px]"
            @change="onAdminSchoolContextChange"
          >
            <option :value="0">{{ text.allSchools }}</option>
            <option v-for="row in schools" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
          <button class="whitespace-nowrap rounded-xl bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-700" @click="loadStudents(1)">
            {{ text.search }}
          </button>
          <button class="whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="downloadingTemplate" @click="downloadTemplate">
            {{ downloadingTemplate ? text.downloadingTemplate : text.downloadTemplate }}
          </button>
          <button v-if="canCreateStudents" class="whitespace-nowrap rounded-xl border border-cyan-300 bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-700 hover:bg-cyan-100" @click="openImportDialog">
            {{ text.importStudents }}
          </button>
          <button v-if="canCreateStudents" class="whitespace-nowrap rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700" @click="openAddDialog">
            {{ text.addStudent }}
          </button>
        </div>
      </div>

      <p v-if="createSuccessMessage" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
        {{ createSuccessMessage }}
      </p>

      <div class="overflow-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.admissionNoShort }}</th>
              <th v-if="isAdmin" class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.school }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.nameWithInitials }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.gradeClass }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.year }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.phone }}</th>
              <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.dob }}</th>
              <th v-if="showActionColumn" class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.actions }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="student in students" :key="student.std_id" class="hover:bg-slate-50">
              <td class="px-3 py-2 font-medium text-slate-800">{{ student.index_no }}</td>
              <td v-if="isAdmin" class="px-3 py-2 text-slate-700">
                <span :title="censusTooltip(student.census_id)">{{ student.school_name || '-' }}</span>
              </td>
              <td class="px-3 py-2 text-slate-700">{{ student.name_with_initials }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.grade_class }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.current_year || '-' }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.phone_no || '-' }}</td>
              <td class="px-3 py-2 text-slate-700">{{ student.dob || '-' }}</td>
              <td v-if="showActionColumn" class="px-3 py-2">
                <div class="flex gap-2">
                  <button
                    v-if="student.can_edit || canEditStudents"
                    class="rounded bg-cyan-600 px-3 py-1 text-xs font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="loadingEditStudentId === student.std_id || deletingStudentId === student.std_id"
                    @click="openEditDialog(student)"
                  >
                    {{ text.edit }}
                  </button>
                  <button
                    v-if="student.can_delete || canDeleteStudents"
                    class="rounded bg-rose-600 px-3 py-1 text-xs font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="deletingStudentId === student.std_id || loadingEditStudentId === student.std_id"
                    @click="deleteStudent(student)"
                  >
                    {{ deletingStudentId === student.std_id ? text.deleting : text.delete }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && students.length === 0">
              <td :colspan="6 + (isAdmin ? 1 : 0) + (showActionColumn ? 1 : 0)" class="px-3 py-6 text-center text-slate-500">{{ text.noStudentsFound }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
        <p>{{ text.total }}: {{ meta.total }}</p>
        <div class="flex flex-wrap items-center gap-2 md:justify-end">
          <button
            class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="meta.current_page <= 1 || loading"
            @click="loadStudents(meta.current_page - 1)"
          >
            {{ text.prev }}
          </button>
          <span>{{ text.page }} {{ meta.current_page }} / {{ meta.last_page }}</span>
          <button
            class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="meta.current_page >= meta.last_page || loading"
            @click="loadStudents(meta.current_page + 1)"
          >
            {{ text.next }}
          </button>
        </div>
      </div>

      <p v-if="errorMessage" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ errorMessage }}
      </p>
    </section>

    <div v-if="showAddDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeAddDialog">
      <section class="max-h-[90vh] w-full max-w-6xl overflow-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-display text-xl font-bold text-slate-900">{{ isEditMode ? text.editStudentManual : text.addStudentManual }}</h2>
          <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeAddDialog">{{ text.close }}</button>
        </div>

        <p
          v-if="createErrorMessage"
          ref="createErrorMessageRef"
          tabindex="-1"
          class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 focus:outline-none focus:ring-1 focus:ring-red-200"
        >
          {{ createErrorMessage }}
        </p>

        <form class="grid gap-4 md:grid-cols-3" @submit.prevent="submitAddStudent">
          <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.coreDetails }}</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="text-sm text-slate-700">
                {{ text.admissionNo }} <span class="text-red-600">*</span>
                <input v-model="createForm.index_no" type="text" :class="inputClass('index_no')" :placeholder="text.admissionNoExample" required />
                <p v-if="fieldErrors.index_no" class="mt-1 text-xs text-red-600">{{ fieldErrors.index_no }}</p>
              </label>

              <label class="text-sm text-slate-700 md:col-span-2">
                {{ text.fullName }} <span class="text-red-600">*</span>
                <input v-model="createForm.full_name" type="text" :class="inputClass('full_name')" required />
                <p v-if="fieldErrors.full_name" class="mt-1 text-xs text-red-600">{{ fieldErrors.full_name }}</p>
              </label>

              <label class="text-sm text-slate-700 md:col-span-2">
                {{ text.nameWithInitials }} <span class="text-red-600">*</span>
                <input v-model="createForm.name_with_initials" type="text" :class="inputClass('name_with_initials')" required />
                <p v-if="fieldErrors.name_with_initials" class="mt-1 text-xs text-red-600">{{ fieldErrors.name_with_initials }}</p>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.gender }} <span class="text-red-600">*</span>
                <select v-model.number="createForm.gender_id" :class="inputClass('gender_id')" required>
                  <option :value="0" disabled>{{ text.selectGender }}</option>
                  <option :value="2">{{ text.female }}</option>
                  <option :value="1">{{ text.male }}</option>
                </select>
                <p v-if="fieldErrors.gender_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.gender_id }}</p>
              </label>

              <label v-if="isAdmin" class="text-sm text-slate-700">
                {{ text.school }} <span class="text-red-600">*</span>
                <select v-model.number="createForm.census_id" :class="inputClass('census_id')" required>
                  <option :value="0" disabled>{{ text.selectSchool }}</option>
                  <option v-for="row in schools" :key="row.id" :value="row.id">{{ row.label }}</option>
                </select>
                <p v-if="fieldErrors.census_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.census_id }}</p>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.admissionDate }}
                <input v-model="createForm.d_o_admission" type="date" :class="inputClass('d_o_admission')" />
                <p v-if="fieldErrors.d_o_admission" class="mt-1 text-xs text-red-600">{{ fieldErrors.d_o_admission }}</p>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.academicYear }}
                <select v-model.number="createForm.year" :class="inputClass('year')" :disabled="!isAdminSchoolSelected">
                  <option :value="0">{{ text.selectAcademicYear }}</option>
                  <option v-for="year in academicYears" :key="year" :value="year">{{ year }}</option>
                </select>
                <p v-if="fieldErrors.year" class="mt-1 text-xs text-red-600">{{ fieldErrors.year }}</p>
                <p v-else-if="isAdmin && !isAdminSchoolSelected" class="mt-1 text-xs text-slate-500">{{ text.selectSchoolFirst }}</p>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.grade }}
                <select v-model.number="createForm.grade_id" :class="inputClass('grade_id')" :disabled="!isAdminSchoolSelected || createForm.year <= 0">
                  <option :value="0">{{ text.selectGrade }}</option>
                  <option v-for="grade in grades" :key="grade.grade_id" :value="grade.grade_id">{{ grade.grade }}</option>
                </select>
                <p v-if="fieldErrors.grade_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.grade_id }}</p>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.classLabel }}
                <select v-model.number="createForm.class_id" :class="inputClass('class_id')" :disabled="!isAdminSchoolSelected || createForm.year <= 0 || createForm.grade_id <= 0">
                  <option :value="0">{{ text.selectClass }}</option>
                  <option v-for="row in classes" :key="row.class_id" :value="row.class_id">{{ row.class }}</option>
                </select>
                <p v-if="fieldErrors.class_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.class_id }}</p>
              </label>
            </div>
          </fieldset>

          <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.contactAddressOptional }}</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="text-sm text-slate-700">
                {{ text.mobile }}
                <input v-model="createForm.phone_no" type="text" :class="inputClass('phone_no')" />
                <p v-if="fieldErrors.phone_no" class="mt-1 text-xs text-red-600">{{ fieldErrors.phone_no }}</p>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.whatsapp }}
                <input v-model="createForm.whatsapp_no" type="text" :class="inputClass('whatsapp_no')" />
                <p v-if="fieldErrors.whatsapp_no" class="mt-1 text-xs text-red-600">{{ fieldErrors.whatsapp_no }}</p>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.homePhone }}
                <input v-model="createForm.phone_home" type="text" :class="inputClass('phone_home')" />
                <p v-if="fieldErrors.phone_home" class="mt-1 text-xs text-red-600">{{ fieldErrors.phone_home }}</p>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.addressLine1 }}
                <input v-model="createForm.address1" type="text" :class="inputClass('address1')" />
                <p v-if="fieldErrors.address1" class="mt-1 text-xs text-red-600">{{ fieldErrors.address1 }}</p>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.addressLine2 }}
                <input v-model="createForm.address2" type="text" :class="inputClass('address2')" />
                <p v-if="fieldErrors.address2" class="mt-1 text-xs text-red-600">{{ fieldErrors.address2 }}</p>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.email }}
                <input v-model="createForm.email" type="email" :class="inputClass('email')" />
                <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">{{ fieldErrors.email }}</p>
              </label>
            </div>
          </fieldset>

          <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.demographicsOptional }}</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="text-sm text-slate-700">
                {{ text.ethnicGroup }}
                <select v-model.number="createForm.ethnic_group_id" :class="inputClass('ethnic_group_id')">
                  <option :value="0">{{ text.selectEthnicGroup }}</option>
                  <option v-for="row in ethnicGroups" :key="row.id" :value="row.id">{{ row.label }}</option>
                </select>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.religion }}
                <select v-model.number="createForm.religion_id" :class="inputClass('religion_id')">
                  <option :value="0">{{ text.selectReligion }}</option>
                  <option v-for="row in religions" :key="row.id" :value="row.id">{{ row.label }}</option>
                </select>
              </label>

              <label class="text-sm text-slate-700">
                {{ text.dob }}
                <input v-model="createForm.dob" type="date" :class="inputClass('dob')" />
                <p v-if="fieldErrors.dob" class="mt-1 text-xs text-red-600">{{ fieldErrors.dob }}</p>
              </label>
            </div>
          </fieldset>

          <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.parentsGuardianOptional }}</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="text-sm text-slate-700">
                {{ text.fatherName }}
                <input v-model="createForm.father_name" type="text" :class="inputClass('father_name')" />
              </label>

              <label class="text-sm text-slate-700">
                {{ text.fatherMobile }}
                <input v-model="createForm.father_mobile" type="text" :class="inputClass('father_mobile')" />
              </label>

              <label class="text-sm text-slate-700">
                {{ text.fatherJob }}
                <input v-model="createForm.father_job" type="text" :class="inputClass('father_job')" />
              </label>

              <label class="text-sm text-slate-700">
                {{ text.motherName }}
                <input v-model="createForm.mother_name" type="text" :class="inputClass('mother_name')" />
              </label>

              <label class="text-sm text-slate-700">
                {{ text.motherMobile }}
                <input v-model="createForm.mother_mobile" type="text" :class="inputClass('mother_mobile')" />
              </label>

              <label class="text-sm text-slate-700">
                {{ text.motherJob }}
                <input v-model="createForm.mother_job" type="text" :class="inputClass('mother_job')" />
              </label>

              <label class="text-sm text-slate-700">
                {{ text.guardianName }}
                <input v-model="createForm.guardian_name" type="text" :class="inputClass('guardian_name')" />
              </label>

              <label class="text-sm text-slate-700">
                {{ text.guardianMobile }}
                <input v-model="createForm.guardian_mobile" type="text" :class="inputClass('guardian_mobile')" />
              </label>

              <label class="text-sm text-slate-700">
                {{ text.guardianJob }}
                <input v-model="createForm.guardian_job" type="text" :class="inputClass('guardian_job')" />
              </label>
            </div>
          </fieldset>

          <div class="md:col-span-3 flex justify-end gap-2">
            <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeAddDialog">
              {{ text.cancel }}
            </button>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="creating">
              {{ creating ? (isEditMode ? text.updating : text.saving) : (isEditMode ? text.updateStudent : text.saveStudent) }}
            </button>
          </div>
        </form>
      </section>
    </div>

    <div v-if="showImportDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeImportDialog">
      <section class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-display text-xl font-bold text-slate-900">{{ text.importStudents }}</h2>
          <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeImportDialog">{{ text.close }}</button>
        </div>

        <p class="text-sm text-slate-600">{{ text.importHelp }}</p>
        <label v-if="isAdmin" class="mt-4 block text-sm text-slate-700">
          {{ text.school }} <span class="text-red-600">*</span>
          <select v-model.number="importSchoolCensusId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            <option :value="0" disabled>{{ text.selectSchool }}</option>
            <option v-for="row in schools" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
        </label>
        <p v-if="isAdmin && importSchoolCensusId <= 0" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
          {{ text.selectSchoolBeforeImport }}
        </p>

        <label class="mt-4 block text-sm text-slate-700">
          {{ text.chooseFile }}
          <input
            class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            type="file"
            accept=".xlsx,.xls,.csv"
            @change="onImportFileChange"
          />
        </label>

        <p v-if="importFileName" class="mt-2 text-sm text-slate-600">{{ importFileName }}</p>

        <p v-if="importErrorMessage" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {{ importErrorMessage }}
        </p>

        <div v-if="importResult" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
          <p class="font-semibold text-slate-900">{{ text.importSummary }}</p>
          <p class="mt-2">{{ text.importedCount }}: {{ importResult.imported_count }}</p>
          <p>{{ text.skippedCount }}: {{ importResult.skipped_count }}</p>
          <p>{{ text.failedCount }}: {{ importResult.failed_count }}</p>
          <div v-if="importResult.failed_rows.length > 0" class="mt-3 space-y-1">
            <p class="font-semibold text-slate-900">{{ text.failedRows }}</p>
            <p v-for="row in importResult.failed_rows" :key="`${row.row}-${row.message}`">
              {{ text.row }} {{ row.row }}: {{ row.message }}
            </p>
          </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeImportDialog">
            {{ text.cancel }}
          </button>
          <button type="button" class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="importing || (isAdmin && importSchoolCensusId <= 0)" @click="submitImport">
            {{ importing ? text.uploading : text.upload }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'
import { useUiStore } from '../stores/ui'

interface Student {
  std_id: number
  index_no: string
  census_id?: string | null
  school_name?: string | null
  name_with_initials: string
  grade_class: string
  current_year?: number | null
  phone_no: string | null
  dob: string | null
  gender_id?: number
  can_edit?: boolean
  can_delete?: boolean
}

interface StudentsMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

interface StudentsResponse {
  data: Student[]
  meta: StudentsMeta
}

interface StudentDetail {
  std_id: number
  census_id: number
  index_no: string
  full_name: string
  name_with_initials: string
  gender_id: number
  phone_no: string
  whatsapp_no: string
  phone_home: string
  address1: string
  address2: string
  email: string
  dob: string
  d_o_admission: string
  ethnic_group_id: number
  religion_id: number
  grade_id: number
  class_id: number
  year?: number
  father_name: string
  father_job: string
  father_mobile: string
  mother_name: string
  mother_job: string
  mother_mobile: string
  guardian_name: string
  guardian_job: string
  guardian_mobile: string
}

interface StudentDetailResponse {
  data: StudentDetail
}


interface GradeRow {
  grade_id: number
  grade: string
}

interface GradeResponse {
  year?: number | null
  years?: number[]
  data: GradeRow[]
}

interface ClassRow {
  class_id: number
  class: string
}

interface ClassResponse {
  data: ClassRow[]
}

interface OptionRow {
  id: number
  label: string
}

interface StudentOptionsResponse {
  ethnic_groups: OptionRow[]
  religions: OptionRow[]
  schools: OptionRow[]
}

interface StudentReportResponse {
  data: Student[]
}

interface StudentReportFilters {
  q: string
  school_census_id: number
  gender_id: number
  ethnic_group_id: number
  religion_id: number
  year: number
  grade_id: number
  class_id: number
}

interface CreateStudentPayload {
  index_no: string
  full_name: string
  name_with_initials: string
  gender_id: number
  phone_no?: string
  whatsapp_no?: string
  phone_home?: string
  address1?: string
  address2?: string
  email?: string
  dob?: string
  d_o_admission?: string
  ethnic_group_id?: number
  religion_id?: number
  census_id?: string
  grade_id?: number
  class_id?: number
  year?: number
  father_name?: string
  father_job?: string
  father_mobile?: string
  mother_name?: string
  mother_job?: string
  mother_mobile?: string
  guardian_name?: string
  guardian_job?: string
  guardian_mobile?: string
}

interface ValidationErrors {
  [key: string]: string
}

interface ImportRow {
  row: number
  message: string
}

interface ImportResult {
  imported_count: number
  failed_count: number
  skipped_count: number
  failed_rows: ImportRow[]
}

interface StudentImportResponse {
  message?: string
  data?: ImportResult
}

const ui = useUiStore()
const route = useRoute()
const text = computed(() => {
  if (ui.language === 'si') {
    return {
      studentsTitle: 'සිසුන්',
      studentReports: 'සිසු වාර්තා',
      studentReportFilterHelp: 'Staff report එකේ වගේ පෙරහන් භාවිතා කර අවශ්‍ය සිසු වාර්තාව ලබාගන්න.',
      searchPlaceholder: 'ඇතුළත් අංකය හෝ නම අනුව සොයන්න',
      allSchools: 'සියලු පාසල්',
      all: 'සියල්ල',
      reset: 'යළි සකසන්න',
      search: 'සොයන්න',
      viewReport: 'වාර්තාව බලන්න',
      loadingReport: 'පූරණය වෙමින්...',
      downloadTemplate: 'Template බාගන්න',
      downloadingTemplate: 'බාගත කරමින්...',
      importStudents: 'Excel මගින් එක්කරන්න',
      addStudent: 'සිසුවෙකු එක්කරන්න',
      admissionNoShort: 'ඇතුළත් අංකය',
      school: 'පාසල',
      nameWithInitials: 'මුලකුරු සහිත නම',
      gradeClass: 'ශ්‍රේණිය/පංතිය',
      year: 'වර්ෂය',
      phone: 'දුරකථන',
      dob: 'උපන්දිනය',
      actions: 'ක්‍රියා',
      censusId: 'සංගණන අංකය',
      notAvailable: 'නැත',
      edit: 'සංස්කරණය',
      delete: 'මකන්න',
      deleting: 'මකමින්...',
      noStudentsFound: 'සිසුන් හමු නොවීය.',
      total: 'එකතුව',
      prev: 'පෙර',
      page: 'පිටුව',
      next: 'ඊළඟ',
      editStudentManual: 'සිසුවා සංස්කරණය (අතින්)',
      addStudentManual: 'සිසුවා එක්කරන්න (අතින්)',
      close: 'වසන්න',
      coreDetails: 'මූලික තොරතුරු',
      admissionNo: 'ඇතුළත් අංකය',
      admissionNoExample: 'උදා: 1234',
      fullName: 'සම්පූර්ණ නම',
      gender: 'ස්ත්‍රී/පුරුෂ භාවය',
      selectGender: 'ස්ත්‍රී/පුරුෂ භාවය තෝරන්න',
      female: 'ගැහැණු',
      male: 'පිරිමි',
      selectSchool: 'පාසල තෝරන්න',
      admissionDate: 'ඇතුළත් වූ දිනය',
      academicYear: 'අධ්‍යයන වර්ෂය',
      allYears: 'සියලු වර්ෂ',
      selectAcademicYear: 'අධ්‍යයන වර්ෂය තෝරන්න',
      selectSchoolFirst: 'පළමුව පාසල තෝරන්න.',
      grade: 'ශ්‍රේණිය',
      classLabel: 'පංතිය',
      allGrades: 'සියලු ශ්‍රේණි',
      allClasses: 'සියලු පංති',
      selectGrade: 'ශ්‍රේණිය තෝරන්න',
      selectClass: 'පංතිය තෝරන්න',
      contactAddressOptional: 'සම්බන්ධතා සහ ලිපිනය (විකල්ප)',
      mobile: 'ජංගම',
      whatsapp: 'වට්ස්ඇප්',
      homePhone: 'නිවසේ දුරකථන',
      addressLine1: 'ලිපිනය 1',
      addressLine2: 'ලිපිනය 2',
      email: 'ඊමේල්',
      demographicsOptional: 'ජනගහන තොරතුරු (විකල්ප)',
      ethnicGroup: 'ජාතික කණ්ඩායම',
      selectEthnicGroup: 'ජාතික කණ්ඩායම තෝරන්න',
      religion: 'ආගම',
      selectReligion: 'ආගම තෝරන්න',
      parentsGuardianOptional: 'මව්පියන් / භාරකරු (විකල්ප)',
      fatherName: 'පියාගේ නම',
      fatherMobile: 'පියාගේ ජංගම',
      fatherJob: 'පියාගේ රැකියාව',
      motherName: 'මවගේ නම',
      motherMobile: 'මවගේ ජංගම',
      motherJob: 'මවගේ රැකියාව',
      guardianName: 'භාරකරුගේ නම',
      guardianMobile: 'භාරකරුගේ ජංගම',
      guardianJob: 'භාරකරුගේ රැකියාව',
      cancel: 'අවලංගු කරන්න',
      updating: 'යාවත්කාලීන කරමින්...',
      saving: 'සුරකිමින්...',
      updateStudent: 'සිසුවා යාවත්කාලීන කරන්න',
      saveStudent: 'සිසුවා සුරකින්න',
      unableToLoadStudentDetails: 'සිසුවාගේ විස්තර පූරණය කළ නොහැකි විය.',
      studentDeletedSuccessfully: 'සිසුවා සාර්ථකව මකා දමන ලදී.',
      unableToDeleteStudent: 'සිසුවා මකා දැමිය නොහැකි විය.',
      selectGradeAndClassTogether: 'ශ්‍රේණිය සහ පංතිය දෙකම තෝරන්න, නැතිනම් දෙකම හිස් තබන්න.',
      selectSchoolForStudent: 'මෙම සිසුවා සඳහා පාසල තෝරන්න.',
      selectSchoolShort: 'පාසල තෝරන්න.',
      selectValidAcademicYear: 'වලංගු අධ්‍යයන වර්ෂයක් තෝරන්න.',
      academicYearRequired: 'අධ්‍යයන වර්ෂය අනිවාර්යය.',
      correctHighlightedFields: 'ඉස්මතු කළ ක්ෂේත්‍ර නිවැරදි කර නැවත උත්සාහ කරන්න.',
      unableToSaveStudent: 'සිසුවා සුරැකිය නොහැකි විය. නැවත උත්සාහ කරන්න.',
      noCreatePermission: 'ඔබට සිසුන් එක් කිරීමට අවසර නැත.',
      noEditPermission: 'ඔබට සිසුන් සංස්කරණය කිරීමට අවසර නැත.',
      noDeletePermission: 'ඔබට සිසුන් මකා දැමීමට අවසර නැත.',
      importHelp: 'සිසුන් එක් කිරීමට ලබා දුන් Excel සැකිල්ල භාවිතා කරන්න.',
      chooseFile: 'ගොනුව තෝරන්න',
      upload: 'උඩුගත කරන්න',
      uploading: 'උඩුගත කරමින්...',
      selectSchoolBeforeImport: 'Bulk upload කිරීමට පෙර පාසල තෝරන්න.',
      importSummary: 'ආයාත සාරාංශය',
      importedCount: 'සාර්ථකව එක් කළ ගණන',
      skippedCount: 'හිස් පේළි',
      failedCount: 'අසාර්ථක පේළි',
      failedRows: 'අසාර්ථක පේළි විස්තර',
      row: 'පේළිය',
      totalMatches: 'ගැළපෙන සිසුන්',
      downloadReport: 'වාර්තාව බාගන්න',
      noReportStudentsFound: 'තෝරාගත් පෙරහන් සඳහා සිසුන් හමු නොවීය.',
      unableToImportStudents: 'සිසුන් import කළ නොහැකි විය. නැවත උත්සාහ කරන්න.',
      unableToDownloadReport: 'සිසු වාර්තාව බාගත කළ නොහැකි විය.',
      unableToDownloadTemplate: 'Template ගොනුව බාගත කළ නොහැකි විය.',
    }
  }

  if (ui.language === 'ta') {
    return {
      studentsTitle: 'மாணவர்கள்',
      studentReports: 'மாணவர் அறிக்கைகள்',
      studentReportFilterHelp: 'பணியாளர் அறிக்கையைப் போல தேவையான மாணவர் அறிக்கையை வடிகட்டல்களுடன் பார்க்கவும்.',
      searchPlaceholder: 'அனுமதி இலக்கம் அல்லது பெயரால் தேடவும்',
      allSchools: 'அனைத்து பாடசாலைகள்',
      all: 'அனைத்தும்',
      reset: 'மீட்டமை',
      search: 'தேடுக',
      viewReport: 'அறிக்கையைப் பார்',
      loadingReport: 'ஏற்றப்படுகிறது...',
      downloadTemplate: 'Template பதிவிறக்கு',
      downloadingTemplate: 'பதிவிறக்கப்படுகிறது...',
      importStudents: 'Excel மூலம் சேர்க்கவும்',
      addStudent: 'மாணவரை சேர்க்கவும்',
      admissionNoShort: 'அனுமதி இல.',
      school: 'பாடசாலை',
      nameWithInitials: 'முதற் எழுத்துகளுடன் பெயர்',
      gradeClass: 'தரம்/வகுப்பு',
      year: 'ஆண்டு',
      phone: 'தொலைபேசி',
      dob: 'பிறந்த தேதி',
      actions: 'செயல்கள்',
      censusId: 'கணக்கெடுப்பு இலக்கம்',
      notAvailable: 'இல்லை',
      edit: 'திருத்து',
      delete: 'நீக்கு',
      deleting: 'நீக்கப்படுகிறது...',
      noStudentsFound: 'மாணவர்கள் எவரும் கிடைக்கவில்லை.',
      total: 'மொத்தம்',
      prev: 'முந்தைய',
      page: 'பக்கம்',
      next: 'அடுத்தது',
      editStudentManual: 'மாணவரை திருத்து (கைமுறை)',
      addStudentManual: 'மாணவரை சேர்க்கவும் (கைமுறை)',
      close: 'மூடு',
      coreDetails: 'அடிப்படை விவரங்கள்',
      admissionNo: 'அனுமதி இலக்கம்',
      admissionNoExample: 'உதா: 1234',
      fullName: 'முழு பெயர்',
      gender: 'பால்',
      selectGender: 'பாலினத்தைத் தேர்ந்தெடுக்கவும்',
      female: 'பெண்',
      male: 'ஆண்',
      selectSchool: 'பாடசாலையைத் தேர்ந்தெடுக்கவும்',
      admissionDate: 'சேர்க்கை தேதி',
      academicYear: 'கல்வியாண்டு',
      allYears: 'அனைத்து ஆண்டுகள்',
      selectAcademicYear: 'கல்வியாண்டைத் தேர்ந்தெடுக்கவும்',
      selectSchoolFirst: 'முதலில் பாடசாலையைத் தேர்ந்தெடுக்கவும்.',
      grade: 'தரம்',
      classLabel: 'வகுப்பு',
      allGrades: 'அனைத்து தரங்கள்',
      allClasses: 'அனைத்து வகுப்புகள்',
      selectGrade: 'தரத்தைத் தேர்ந்தெடுக்கவும்',
      selectClass: 'வகுப்பைத் தேர்ந்தெடுக்கவும்',
      contactAddressOptional: 'தொடர்பு மற்றும் முகவரி (விருப்பம்)',
      mobile: 'கைபேசி',
      whatsapp: 'WhatsApp',
      homePhone: 'வீட்டு தொலைபேசி',
      addressLine1: 'முகவரி 1',
      addressLine2: 'முகவரி 2',
      email: 'மின்னஞ்சல்',
      demographicsOptional: 'மக்கள்தொகை விவரங்கள் (விருப்பம்)',
      ethnicGroup: 'இனக்குழு',
      selectEthnicGroup: 'இனக்குழுவைத் தேர்ந்தெடுக்கவும்',
      religion: 'மதம்',
      selectReligion: 'மதத்தைத் தேர்ந்தெடுக்கவும்',
      parentsGuardianOptional: 'பெற்றோர் / பாதுகாவலர் (விருப்பம்)',
      fatherName: 'தந்தையின் பெயர்',
      fatherMobile: 'தந்தையின் கைபேசி',
      fatherJob: 'தந்தையின் தொழில்',
      motherName: 'தாயின் பெயர்',
      motherMobile: 'தாயின் கைபேசி',
      motherJob: 'தாயின் தொழில்',
      guardianName: 'பாதுகாவலரின் பெயர்',
      guardianMobile: 'பாதுகாவலரின் கைபேசி',
      guardianJob: 'பாதுகாவலரின் தொழில்',
      cancel: 'ரத்து செய்',
      updating: 'புதுப்பிக்கப்படுகிறது...',
      saving: 'சேமிக்கப்படுகிறது...',
      updateStudent: 'மாணவரை புதுப்பிக்கவும்',
      saveStudent: 'மாணவரை சேமிக்கவும்',
      unableToLoadStudentDetails: 'மாணவர் விவரங்களை ஏற்ற முடியவில்லை.',
      studentDeletedSuccessfully: 'மாணவர் வெற்றிகரமாக நீக்கப்பட்டார்.',
      unableToDeleteStudent: 'மாணவரை நீக்க முடியவில்லை.',
      selectGradeAndClassTogether: 'தரமும் வகுப்பும் இரண்டையும் தேர்ந்தெடுக்கவும், இல்லையெனில் இரண்டையும் காலியாக விடவும்.',
      selectSchoolForStudent: 'இந்த மாணவருக்கான பாடசாலையைத் தேர்ந்தெடுக்கவும்.',
      selectSchoolShort: 'பாடசாலையைத் தேர்ந்தெடுக்கவும்.',
      selectValidAcademicYear: 'செல்லுபடியாகும் கல்வியாண்டைத் தேர்ந்தெடுக்கவும்.',
      academicYearRequired: 'கல்வியாண்டு அவசியம்.',
      correctHighlightedFields: 'குறிப்பிடப்பட்ட புலங்களைச் சரிசெய்து மீண்டும் முயற்சிக்கவும்.',
      unableToSaveStudent: 'மாணவரை சேமிக்க முடியவில்லை. மீண்டும் முயற்சிக்கவும்.',
      noCreatePermission: 'மாணவர்களைச் சேர்க்க உங்களுக்கான அனுமதி இல்லை.',
      noEditPermission: 'மாணவர்களைத் திருத்த உங்களுக்கான அனுமதி இல்லை.',
      noDeletePermission: 'மாணவர்களை நீக்க உங்களுக்கான அனுமதி இல்லை.',
      importHelp: 'மாணவர்களை தொகுதியாகச் சேர்க்க வழங்கப்பட்ட Excel template ஐ பயன்படுத்தவும்.',
      chooseFile: 'கோப்பைத் தேர்ந்தெடுக்கவும்',
      upload: 'பதிவேற்று',
      uploading: 'பதிவேற்றப்படுகிறது...',
      selectSchoolBeforeImport: 'Bulk upload செய்வதற்கு முன் பாடசாலையைத் தேர்ந்தெடுக்கவும்.',
      importSummary: 'இறக்குமதி சுருக்கம்',
      importedCount: 'வெற்றிகரமாக சேர்க்கப்பட்டது',
      skippedCount: 'தவிர்க்கப்பட்ட காலி வரிகள்',
      failedCount: 'தோல்வியுற்ற வரிகள்',
      failedRows: 'தோல்வியுற்ற வரி விவரங்கள்',
      row: 'வரி',
      totalMatches: 'பொருந்திய மாணவர்கள்',
      downloadReport: 'அறிக்கையை பதிவிறக்கு',
      noReportStudentsFound: 'தேர்ந்தெடுத்த வடிகட்டல்களுக்கு பொருந்தும் மாணவர்கள் இல்லை.',
      unableToImportStudents: 'மாணவர்களை import செய்ய முடியவில்லை. மீண்டும் முயற்சிக்கவும்.',
      unableToDownloadReport: 'மாணவர் அறிக்கையை பதிவிறக்க முடியவில்லை.',
      unableToDownloadTemplate: 'Template கோப்பை பதிவிறக்க முடியவில்லை.',
    }
  }

  return {
    studentsTitle: 'Students',
    studentReports: 'Student Reports',
    studentReportFilterHelp: 'Use the same kind of filters as staff reports to narrow the result set.',
    searchPlaceholder: 'Search by admission number or name',
    allSchools: 'All schools',
    all: 'All',
    reset: 'Reset',
    search: 'Search',
    viewReport: 'View Report',
    loadingReport: 'Loading...',
    downloadTemplate: 'Download Template',
    downloadingTemplate: 'Downloading...',
    importStudents: 'Import Students',
    addStudent: 'Add Student',
    admissionNoShort: 'Adm No',
    school: 'School',
    nameWithInitials: 'Name with Initials',
    gradeClass: 'Grade/Class',
    year: 'Year',
    phone: 'Phone',
    dob: 'DOB',
    actions: 'Actions',
    censusId: 'Census ID',
    notAvailable: 'N/A',
    edit: 'Edit',
    delete: 'Delete',
    deleting: 'Deleting...',
    noStudentsFound: 'No students found.',
    total: 'Total',
    prev: 'Prev',
    page: 'Page',
    next: 'Next',
    editStudentManual: 'Edit Student (Manual)',
    addStudentManual: 'Add Student (Manual)',
    close: 'Close',
    coreDetails: 'Core Details',
    admissionNo: 'Admission No',
    admissionNoExample: 'e.g. 1234',
    fullName: 'Full Name',
    gender: 'Gender',
    selectGender: 'Select gender',
    female: 'Female',
    male: 'Male',
    selectSchool: 'Select school',
    admissionDate: 'Admission Date',
    academicYear: 'Academic Year',
    allYears: 'All Years',
    selectAcademicYear: 'Select academic year',
    selectSchoolFirst: 'Select school first.',
    grade: 'Grade',
    classLabel: 'Class',
    allGrades: 'All Grades',
    allClasses: 'All Classes',
    selectGrade: 'Select grade',
    selectClass: 'Select class',
    contactAddressOptional: 'Contact & Address (Optional)',
    mobile: 'Mobile',
    whatsapp: 'WhatsApp',
    homePhone: 'Home Phone',
    addressLine1: 'Address Line 1',
    addressLine2: 'Address Line 2',
    email: 'Email',
    demographicsOptional: 'Demographics (Optional)',
    ethnicGroup: 'Ethnic Group',
    selectEthnicGroup: 'Select ethnic group',
    religion: 'Religion',
    selectReligion: 'Select religion',
    parentsGuardianOptional: 'Parents / Guardian (Optional)',
    fatherName: 'Father Name',
    fatherMobile: 'Father Mobile',
    fatherJob: 'Father Job',
    motherName: 'Mother Name',
    motherMobile: 'Mother Mobile',
    motherJob: 'Mother Job',
    guardianName: 'Guardian Name',
    guardianMobile: 'Guardian Mobile',
    guardianJob: 'Guardian Job',
    cancel: 'Cancel',
    updating: 'Updating...',
    saving: 'Saving...',
    updateStudent: 'Update Student',
    saveStudent: 'Save Student',
    unableToLoadStudentDetails: 'Unable to load student details.',
    studentDeletedSuccessfully: 'Student deleted successfully.',
    unableToDeleteStudent: 'Unable to delete student.',
    selectGradeAndClassTogether: 'Please select both grade and class, or leave both empty.',
    selectSchoolForStudent: 'Please select a school for this student.',
    selectSchoolShort: 'Please select a school.',
    selectValidAcademicYear: 'Please select a valid academic year.',
    academicYearRequired: 'Academic year is required.',
    correctHighlightedFields: 'Please correct the highlighted fields and try again.',
    unableToSaveStudent: 'Unable to save student. Please try again.',
    noCreatePermission: 'You do not have permission to add students.',
    noEditPermission: 'You do not have permission to edit students.',
    noDeletePermission: 'You do not have permission to delete students.',
    importHelp: 'Use the provided Excel template to add students in bulk.',
    chooseFile: 'Choose File',
    upload: 'Upload',
    uploading: 'Uploading...',
    selectSchoolBeforeImport: 'Please select a school before bulk upload.',
    importSummary: 'Import Summary',
    importedCount: 'Imported',
    skippedCount: 'Skipped Empty Rows',
    failedCount: 'Failed Rows',
    failedRows: 'Failed Row Details',
    row: 'Row',
    totalMatches: 'Matching Students',
    downloadReport: 'Download Report',
    noReportStudentsFound: 'No students matched the selected filters.',
    unableToImportStudents: 'Unable to import students. Please try again.',
    unableToDownloadReport: 'Unable to download student report.',
    unableToDownloadTemplate: 'Unable to download template.',
  }
})

const censusTooltip = (censusId: number | null | undefined): string => {
  return `${text.value.censusId}: ${censusId ?? text.value.notAvailable}`
}

const deleteStudentConfirmText = (admissionNo: string): string => {
  if (ui.language === 'si') {
    return `ඇතුළත් අංකය ${admissionNo} සහිත සිසුවා මකා දමන්නද?`
  }

  if (ui.language === 'ta') {
    return `அனுமதி இலக்கம் ${admissionNo} கொண்ட மாணவரை நீக்க வேண்டுமா?`
  }

  return `Delete student ${admissionNo}?`
}

const createDefaultReportFilters = (): StudentReportFilters => ({
  q: '',
  school_census_id: initialSchoolContextCensusId ?? 0,
  gender_id: 0,
  ethnic_group_id: 0,
  religion_id: 0,
  year: 0,
  grade_id: 0,
  class_id: 0,
})

const search = ref('')
const loading = ref(false)
const loadingReport = ref(false)
const downloadingReport = ref(false)
const creating = ref(false)
const importing = ref(false)
const downloadingTemplate = ref(false)
const showAddDialog = ref(false)
const showImportDialog = ref(false)
const errorMessage = ref('')
const reportErrorMessage = ref('')
const createErrorMessage = ref('')
const createSuccessMessage = ref('')
const importErrorMessage = ref('')
const students = ref<Student[]>([])
const reportRows = ref<Student[]>([])
const grades = ref<GradeRow[]>([])
const classes = ref<ClassRow[]>([])
const reportAcademicYears = ref<number[]>([])
const reportGrades = ref<GradeRow[]>([])
const reportClasses = ref<ClassRow[]>([])
const ethnicGroups = ref<OptionRow[]>([])
const religions = ref<OptionRow[]>([])
const schools = ref<OptionRow[]>([])
const importSchoolCensusId = ref(0)
const academicYears = ref<number[]>([])
const fieldErrors = ref<ValidationErrors>({})
const importFile = ref<File | null>(null)
const importFileName = ref('')
const importResult = ref<ImportResult | null>(null)
const createErrorMessageRef = ref<HTMLElement | null>(null)
const currentUser = getUser()
const initialSchoolContextCensusId = getSchoolContextCensusId()
const adminSchoolContextCensusId = ref<number>(initialSchoolContextCensusId ?? 0)
const reportFilters = ref<StudentReportFilters>(createDefaultReportFilters())
const roleName = String(currentUser?.role_name ?? '').trim().toLowerCase()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1 || roleName === 'admin' || roleName === 'administrator')
const isReportView = computed(() => route.name === 'students-report')
const fallbackStudentPermissions = computed<Record<string, boolean>>(() => {
  const roleId = currentUser?.role_id ?? 0
  const canManageByRole = roleId === 1 || roleId === 2 || roleId === 4 || roleName === 'admin' || roleName === 'administrator' || roleName === 'principal' || roleName === 'class teacher' || roleName === 'class_teacher'

  return {
    'student.create': canManageByRole,
    'student.update': canManageByRole,
    'student.delete': canManageByRole,
  }
})

const sessionStudentPermissions = computed<Record<string, boolean>>(() => {
  const raw = currentUser?.feature_permissions
  if (!raw || typeof raw !== 'object') {
    return fallbackStudentPermissions.value
  }

  return {
    'student.create': Boolean(raw['student.create']),
    'student.update': Boolean(raw['student.update']),
    'student.delete': Boolean(raw['student.delete']),
  }
})

const canCreateStudents = computed(() => sessionStudentPermissions.value['student.create'] ?? false)
const canEditStudents = computed(() => sessionStudentPermissions.value['student.update'] ?? false)
const canDeleteStudents = computed(() => sessionStudentPermissions.value['student.delete'] ?? false)
const genderOptions = computed<OptionRow[]>(() => [
  { id: 1, label: text.value.male },
  { id: 2, label: text.value.female },
])

const editStudentId = ref<number | null>(null)
const isEditMode = computed(() => editStudentId.value !== null)
const showActionColumn = computed(() => canEditStudents.value || canDeleteStudents.value || students.value.some((row) => !!row.can_edit || !!row.can_delete))
const loadingEditStudentId = ref<number | null>(null)
const deletingStudentId = ref<number | null>(null)
const buildSchoolScopedRequestHeaders = (): Record<string, string> | undefined => {
  if (!isAdmin.value) {
    return undefined
  }

  const selectedCensusId = Number(createForm.value.census_id)
  return {
    'X-School-Census-Id': selectedCensusId > 0 ? String(selectedCensusId) : '',
  }
}
const scrollToCreateErrorMessage = async (): Promise<void> => {
  await nextTick()
  if (createErrorMessageRef.value) {
    createErrorMessageRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' })
    createErrorMessageRef.value.focus({ preventScroll: true })
  }
}

const inputClass = (field: string): string[] => {
  return [
    'mt-1 w-full rounded-lg border px-3 py-2',
    fieldErrors.value[field] ? 'border-red-300' : 'border-slate-300',
  ]
}

const studentGenderLabel = (genderId: number | undefined): string => {
  if (Number(genderId) === 1) {
    return text.value.male
  }

  if (Number(genderId) === 2) {
    return text.value.female
  }

  return text.value.notAvailable
}

const createForm = ref({
  index_no: '',
  full_name: '',
  name_with_initials: '',
  gender_id: 0,
  phone_no: '',
  whatsapp_no: '',
  phone_home: '',
  address1: '',
  address2: '',
  email: '',
  dob: '',
  d_o_admission: '',
  ethnic_group_id: 0,
  religion_id: 0,
  census_id: 0,
  grade_id: 0,
  class_id: 0,
  year: 0,
  father_name: '',
  father_job: '',
  father_mobile: '',
  mother_name: '',
  mother_job: '',
  mother_mobile: '',
  guardian_name: '',
  guardian_job: '',
  guardian_mobile: '',
})

const isAdminSchoolSelected = computed(() => !isAdmin.value || Number(createForm.value.census_id) > 0)

const meta = ref<StudentsMeta>({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

const resetCreateForm = (): void => {
  createForm.value.index_no = ''
  createForm.value.full_name = ''
  createForm.value.name_with_initials = ''
  createForm.value.gender_id = 0
  createForm.value.phone_no = ''
  createForm.value.whatsapp_no = ''
  createForm.value.phone_home = ''
  createForm.value.address1 = ''
  createForm.value.address2 = ''
  createForm.value.email = ''
  createForm.value.dob = ''
  createForm.value.d_o_admission = ''
  createForm.value.ethnic_group_id = 0
  createForm.value.religion_id = 0
  createForm.value.census_id = 0
  createForm.value.grade_id = 0
  createForm.value.class_id = 0
  createForm.value.year = 0
  createForm.value.father_name = ''
  createForm.value.father_job = ''
  createForm.value.father_mobile = ''
  createForm.value.mother_name = ''
  createForm.value.mother_job = ''
  createForm.value.mother_mobile = ''
  createForm.value.guardian_name = ''
  createForm.value.guardian_job = ''
  createForm.value.guardian_mobile = ''
  classes.value = []
}

const applyStudentDetailToForm = async (detail: StudentDetail): Promise<void> => {
  createForm.value.index_no = detail.index_no ?? ''
  createForm.value.census_id = Number(detail.census_id ?? 0)
  createForm.value.full_name = detail.full_name ?? ''
  createForm.value.name_with_initials = detail.name_with_initials ?? ''
  createForm.value.gender_id = Number(detail.gender_id ?? 0)
  createForm.value.phone_no = detail.phone_no ?? ''
  createForm.value.whatsapp_no = detail.whatsapp_no ?? ''
  createForm.value.phone_home = detail.phone_home ?? ''
  createForm.value.address1 = detail.address1 ?? ''
  createForm.value.address2 = detail.address2 ?? ''
  createForm.value.email = detail.email ?? ''
  createForm.value.dob = detail.dob ?? ''
  createForm.value.d_o_admission = detail.d_o_admission ?? ''
  createForm.value.ethnic_group_id = Number(detail.ethnic_group_id ?? 0)
  createForm.value.religion_id = Number(detail.religion_id ?? 0)
  createForm.value.year = Number.isFinite(Number(detail.year)) ? Number(detail.year) : 0
  createForm.value.grade_id = 0
  createForm.value.class_id = 0
  createForm.value.father_name = detail.father_name ?? ''
  createForm.value.father_job = detail.father_job ?? ''
  createForm.value.father_mobile = detail.father_mobile ?? ''
  createForm.value.mother_name = detail.mother_name ?? ''
  createForm.value.mother_job = detail.mother_job ?? ''
  createForm.value.mother_mobile = detail.mother_mobile ?? ''
  createForm.value.guardian_name = detail.guardian_name ?? ''
  createForm.value.guardian_job = detail.guardian_job ?? ''
  createForm.value.guardian_mobile = detail.guardian_mobile ?? ''

  if (createForm.value.year > 0) {
    await loadGrades(createForm.value.year)
  }

  const detailGradeId = Number(detail.grade_id ?? 0)
  createForm.value.grade_id = detailGradeId

  if (createForm.value.year > 0 && detailGradeId > 0) {
    await loadClasses(detailGradeId, createForm.value.year)
    createForm.value.class_id = Number(detail.class_id ?? 0)
  } else {
    classes.value = []
    createForm.value.class_id = 0
  }
}
const openAddDialog = async (): Promise<void> => {
  if (!canCreateStudents.value) {
    errorMessage.value = text.value.noCreatePermission
    return
  }

  editStudentId.value = null
  resetCreateForm()
  createErrorMessage.value = ''
  fieldErrors.value = {}

  if (isAdmin.value) {
    const scopedCensusId = Number(adminSchoolContextCensusId.value)
    createForm.value.census_id = scopedCensusId > 0 ? scopedCensusId : 0
  }

  if (isAdminSchoolSelected.value) {
    await loadGrades()
  } else {
    academicYears.value = []
    grades.value = []
    classes.value = []
  }

  showAddDialog.value = true
}

const openImportDialog = (): void => {
  if (!canCreateStudents.value) {
    errorMessage.value = text.value.noCreatePermission
    return
  }

  importErrorMessage.value = ''
  importFile.value = null
  importFileName.value = ''
  importResult.value = null
  importSchoolCensusId.value = isAdmin.value && adminSchoolContextCensusId.value > 0 ? Number(adminSchoolContextCensusId.value) : 0
  showImportDialog.value = true
}

const closeAddDialog = (): void => {
  showAddDialog.value = false
  editStudentId.value = null
  createErrorMessage.value = ''
  fieldErrors.value = {}
}

const closeImportDialog = (): void => {
  showImportDialog.value = false
  importErrorMessage.value = ''
  importFile.value = null
  importFileName.value = ''
  importResult.value = null
  importSchoolCensusId.value = 0
}

const onImportFileChange = (event: Event): void => {
  const target = event.target as HTMLInputElement | null
  const file = target?.files?.[0] ?? null
  importFile.value = file
  importFileName.value = file?.name ?? ''
  importErrorMessage.value = ''
}

const onAdminSchoolContextChange = async (): Promise<void> => {
  if (!isAdmin.value) {
    return
  }

  const censusId = Number(adminSchoolContextCensusId.value)
  setSchoolContextCensusId(censusId > 0 ? censusId : null)

  if (showAddDialog.value && !isEditMode.value) {
    createForm.value.census_id = censusId > 0 ? censusId : 0
    createForm.value.year = 0
    createForm.value.grade_id = 0
    createForm.value.class_id = 0
    grades.value = []
    classes.value = []
  }

  if (showImportDialog.value) {
    importSchoolCensusId.value = censusId > 0 ? censusId : 0
  }

  await Promise.all([loadStudents(1), loadGrades()])
}
const openEditDialog = async (student: Student): Promise<void> => {
  if (!(student.can_edit || canEditStudents.value)) {
    errorMessage.value = text.value.noEditPermission
    return
  }

  loadingEditStudentId.value = student.std_id
  createErrorMessage.value = ''
  fieldErrors.value = {}

  try {
    const { data } = await api.get<StudentDetailResponse>(`/students/${student.std_id}`)
    await applyStudentDetailToForm(data.data)
    editStudentId.value = student.std_id
    showAddDialog.value = true
  } catch (error) {
    errorMessage.value = extractApiMessage(error) || text.value.unableToLoadStudentDetails
  } finally {
    loadingEditStudentId.value = null
  }
}

const deleteStudent = async (student: Student): Promise<void> => {
  if (!(student.can_delete || canDeleteStudents.value)) {
    errorMessage.value = text.value.noDeletePermission
    return
  }

  if (!window.confirm(deleteStudentConfirmText(student.index_no))) {
    return
  }

  deletingStudentId.value = student.std_id
  errorMessage.value = ''

  try {
    const { data } = await api.delete(`/students/${student.std_id}`)
    createSuccessMessage.value = typeof data?.message === 'string' && data.message.trim() !== ''
      ? data.message
      : text.value.studentDeletedSuccessfully
    await loadStudents(meta.value.current_page)
  } catch (error) {
    errorMessage.value = extractApiMessage(error) || text.value.unableToDeleteStudent
  } finally {
    deletingStudentId.value = null
  }
}

const loadStudents = async (page = 1): Promise<void> => {
  loading.value = true
  errorMessage.value = ''

  try {
    const params: Record<string, string | number> = {
      q: search.value,
      page,
      per_page: meta.value.per_page,
    }

    const requestConfig: {
      params: Record<string, string | number>
      headers?: Record<string, string>
    } = { params }

    if (isAdmin.value && adminSchoolContextCensusId.value > 0) {
      const selectedSchoolCensusId = String(adminSchoolContextCensusId.value)
      params.school_census_id = selectedSchoolCensusId
      requestConfig.headers = {
        'X-School-Census-Id': selectedSchoolCensusId,
      }
    }

    const { data } = await api.get<StudentsResponse>('/students', requestConfig)

    students.value = data.data
    meta.value = data.meta
  } catch (error) {
    students.value = []
    errorMessage.value = extractApiMessage(error)
  } finally {
    loading.value = false
  }
}

const reportRequestHeaders = (): Record<string, string> | undefined => {
  if (!isAdmin.value) {
    return undefined
  }

  const selectedCensusId = Number(reportFilters.value.school_census_id)
  if (selectedCensusId <= 0) {
    return undefined
  }

  return {
    'X-School-Census-Id': String(selectedCensusId),
  }
}

const loadStudentReport = async (): Promise<void> => {
  loadingReport.value = true
  reportErrorMessage.value = ''

  try {
    const params: Record<string, string | number> = {}
    const filters = reportFilters.value

    if (filters.q.trim() !== '') params.q = filters.q.trim()
    if (filters.school_census_id > 0) params.school_census_id = String(filters.school_census_id)
    if (filters.gender_id > 0) params.gender_id = filters.gender_id
    if (filters.ethnic_group_id > 0) params.ethnic_group_id = filters.ethnic_group_id
    if (filters.religion_id > 0) params.religion_id = filters.religion_id
    if (filters.year > 0) params.year = filters.year
    if (filters.grade_id > 0) params.grade_id = filters.grade_id
    if (filters.class_id > 0) params.class_id = filters.class_id

    const requestConfig: {
      params: Record<string, string | number>
      headers?: Record<string, string>
    } = { params }
    const headers = reportRequestHeaders()
    if (headers) {
      requestConfig.headers = headers
    }

    const { data } = await api.get<StudentReportResponse>('/students/report', requestConfig)
    reportRows.value = Array.isArray(data.data) ? data.data : []
  } catch (error) {
    reportRows.value = []
    reportErrorMessage.value = extractApiMessage(error) || text.value.noReportStudentsFound
  } finally {
    loadingReport.value = false
  }
}

const downloadStudentReport = async (): Promise<void> => {
  downloadingReport.value = true
  reportErrorMessage.value = ''

  try {
    const params: Record<string, string | number> = {}
    const filters = reportFilters.value

    if (filters.q.trim() !== '') params.q = filters.q.trim()
    if (filters.school_census_id > 0) params.school_census_id = String(filters.school_census_id)
    if (filters.gender_id > 0) params.gender_id = filters.gender_id
    if (filters.ethnic_group_id > 0) params.ethnic_group_id = filters.ethnic_group_id
    if (filters.religion_id > 0) params.religion_id = filters.religion_id
    if (filters.year > 0) params.year = filters.year
    if (filters.grade_id > 0) params.grade_id = filters.grade_id
    if (filters.class_id > 0) params.class_id = filters.class_id

    const response = await api.get('/students/report/download', {
      headers: reportRequestHeaders(),
      params,
      responseType: 'blob',
    })

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'student-report.xlsx'
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    reportErrorMessage.value = extractApiMessage(error) || text.value.unableToDownloadReport
  } finally {
    downloadingReport.value = false
  }
}

const loadGrades = async (year?: number): Promise<void> => {
  const parsedYear = Number(year)
  const hasSelectedYear = Number.isFinite(parsedYear) && parsedYear >= 2000 && parsedYear <= 2100

  if (isAdmin.value && Number(createForm.value.census_id) <= 0) {
    academicYears.value = []
    grades.value = []
    return
  }

  try {
    const headers = buildSchoolScopedRequestHeaders()
    const requestConfig: { headers?: Record<string, string>; params?: { year: number } } = {}
    if (headers) {
      requestConfig.headers = headers
    }
    if (hasSelectedYear) {
      requestConfig.params = { year: parsedYear }
    }

    const { data } = await api.get<GradeResponse>('/grades', Object.keys(requestConfig).length > 0 ? requestConfig : undefined)

    const normalizedYears = Array.isArray(data.years)
      ? data.years
          .map((value) => Number(value))
          .filter((value) => Number.isFinite(value) && value >= 2000 && value <= 2100)
      : []

    if (normalizedYears.length > 0) {
      academicYears.value = normalizedYears
    } else if (typeof data.year === 'number' && Number.isFinite(data.year)) {
      academicYears.value = [Number(data.year)]
    } else {
      academicYears.value = []
    }

    grades.value = hasSelectedYear && Array.isArray(data.data) ? data.data : []
  } catch {
    grades.value = []
    if (academicYears.value.length === 0) {
      academicYears.value = []
    }
  }
}

const loadReportGrades = async (year?: number): Promise<void> => {
  const parsedYear = Number(year)
  const hasSelectedYear = Number.isFinite(parsedYear) && parsedYear >= 2000 && parsedYear <= 2100

  if (isAdmin.value && Number(reportFilters.value.school_census_id) <= 0) {
    reportAcademicYears.value = []
    reportGrades.value = []
    return
  }

  try {
    const headers = reportRequestHeaders()
    const requestConfig: { headers?: Record<string, string>; params?: { year: number } } = {}
    if (headers) {
      requestConfig.headers = headers
    }
    if (hasSelectedYear) {
      requestConfig.params = { year: parsedYear }
    }

    const { data } = await api.get<GradeResponse>('/grades', Object.keys(requestConfig).length > 0 ? requestConfig : undefined)

    const normalizedYears = Array.isArray(data.years)
      ? data.years
          .map((value) => Number(value))
          .filter((value) => Number.isFinite(value) && value >= 2000 && value <= 2100)
      : []

    if (normalizedYears.length > 0) {
      reportAcademicYears.value = normalizedYears
    } else if (typeof data.year === 'number' && Number.isFinite(data.year)) {
      reportAcademicYears.value = [Number(data.year)]
    } else {
      reportAcademicYears.value = []
    }

    reportGrades.value = hasSelectedYear && Array.isArray(data.data) ? data.data : []
  } catch {
    reportAcademicYears.value = []
    reportGrades.value = []
  }
}
const loadStudentOptions = async (): Promise<void> => {
  try {
    const { data } = await api.get<StudentOptionsResponse>('/students/options')
    ethnicGroups.value = Array.isArray(data.ethnic_groups) ? data.ethnic_groups : []
    religions.value = Array.isArray(data.religions) ? data.religions : []
    schools.value = Array.isArray(data.schools) ? data.schools : []

    if (isAdmin.value && adminSchoolContextCensusId.value > 0) {
      const hasSelectedSchool = schools.value.some((row) => row.id === adminSchoolContextCensusId.value)
      if (!hasSelectedSchool) {
        adminSchoolContextCensusId.value = 0
        setSchoolContextCensusId(null)
      }
    }
  } catch {
    ethnicGroups.value = []
    religions.value = []
    schools.value = []
  }
}

const loadClasses = async (gradeId: number, year = Number(createForm.value.year)): Promise<void> => {
  const parsedGradeId = Number(gradeId)
  const parsedYear = Number(year)

  if (isAdmin.value && Number(createForm.value.census_id) <= 0) {
    classes.value = []
    createForm.value.class_id = 0
    return
  }

  if (!Number.isFinite(parsedGradeId) || parsedGradeId <= 0 || !Number.isFinite(parsedYear) || parsedYear < 2000 || parsedYear > 2100) {
    classes.value = []
    createForm.value.class_id = 0
    return
  }

  try {
    const headers = buildSchoolScopedRequestHeaders()
    const requestConfig: { headers?: Record<string, string>; params: { year: number } } = {
      params: { year: parsedYear },
    }

    if (headers) {
      requestConfig.headers = headers
    }

    const { data } = await api.get<ClassResponse>(`/classes/by-grade/${parsedGradeId}`, requestConfig)
    classes.value = Array.isArray(data.data) ? data.data : []

    if (!classes.value.some((row) => row.class_id === createForm.value.class_id)) {
      createForm.value.class_id = 0
    }
  } catch {
    classes.value = []
    createForm.value.class_id = 0
  }
}

const loadReportClasses = async (gradeId: number, year = Number(reportFilters.value.year)): Promise<void> => {
  const parsedGradeId = Number(gradeId)
  const parsedYear = Number(year)

  if (isAdmin.value && Number(reportFilters.value.school_census_id) <= 0) {
    reportClasses.value = []
    reportFilters.value.class_id = 0
    return
  }

  if (!Number.isFinite(parsedGradeId) || parsedGradeId <= 0 || !Number.isFinite(parsedYear) || parsedYear < 2000 || parsedYear > 2100) {
    reportClasses.value = []
    reportFilters.value.class_id = 0
    return
  }

  try {
    const headers = reportRequestHeaders()
    const requestConfig: { headers?: Record<string, string>; params: { year: number } } = {
      params: { year: parsedYear },
    }

    if (headers) {
      requestConfig.headers = headers
    }

    const { data } = await api.get<ClassResponse>(`/classes/by-grade/${parsedGradeId}`, requestConfig)
    reportClasses.value = Array.isArray(data.data) ? data.data : []

    if (!reportClasses.value.some((row) => row.class_id === reportFilters.value.class_id)) {
      reportFilters.value.class_id = 0
    }
  } catch {
    reportClasses.value = []
    reportFilters.value.class_id = 0
  }
}

const resetReportFilters = async (): Promise<void> => {
  reportFilters.value = createDefaultReportFilters()
  reportClasses.value = []
  await loadReportGrades()
  await loadStudentReport()
}

const onReportSchoolChange = async (event: Event): Promise<void> => {
  const value = Number((event.target as HTMLSelectElement).value)
  reportFilters.value.school_census_id = Number.isFinite(value) ? value : 0
  reportFilters.value.year = 0
  reportFilters.value.grade_id = 0
  reportFilters.value.class_id = 0
  reportAcademicYears.value = []
  reportGrades.value = []
  reportClasses.value = []
  await loadReportGrades()
}
watch(
  () => createForm.value.year,
  async (year) => {
    const parsedYear = Number(year)

    createForm.value.grade_id = 0
    createForm.value.class_id = 0
    classes.value = []

    if (Number.isFinite(parsedYear) && parsedYear >= 2000 && parsedYear <= 2100) {
      await loadGrades(parsedYear)
    } else {
      grades.value = []
    }
  },
)

watch(
  () => reportFilters.value.year,
  async (year) => {
    reportFilters.value.grade_id = 0
    reportFilters.value.class_id = 0
    reportClasses.value = []

    const parsedYear = Number(year)
    if (Number.isFinite(parsedYear) && parsedYear >= 2000 && parsedYear <= 2100) {
      await loadReportGrades(parsedYear)
    } else {
      await loadReportGrades()
    }
  },
)

watch(
  () => createForm.value.grade_id,
  async (gradeId) => {
    await loadClasses(Number(gradeId), Number(createForm.value.year))
  },
)

watch(
  () => reportFilters.value.grade_id,
  async (gradeId) => {
    await loadReportClasses(Number(gradeId), Number(reportFilters.value.year))
  },
)

watch(
  () => createForm.value.census_id,
  async (censusId) => {
    if (!isAdmin.value || isEditMode.value) {
      return
    }

    const parsed = Number(censusId)
    createForm.value.year = 0
    createForm.value.grade_id = 0
    createForm.value.class_id = 0
    academicYears.value = []
    grades.value = []
    classes.value = []

    if (parsed > 0) {
      adminSchoolContextCensusId.value = parsed
      setSchoolContextCensusId(parsed)
      await loadGrades()
      return
    }

    adminSchoolContextCensusId.value = 0
    setSchoolContextCensusId(null)
  },
)

watch(
  () => ui.language,
  async () => {
    await loadStudentOptions()

    if (isReportView.value) {
      const selectedReportYear = Number(reportFilters.value.year)
      const selectedReportGradeId = Number(reportFilters.value.grade_id)

      if (Number.isFinite(selectedReportYear) && selectedReportYear >= 2000 && selectedReportYear <= 2100) {
        await loadReportGrades(selectedReportYear)
      } else {
        await loadReportGrades()
      }

      if (selectedReportGradeId > 0) {
        await loadReportClasses(selectedReportGradeId, selectedReportYear)
      }

      await loadStudentReport()
      return
    }

    const selectedYear = Number(createForm.value.year)
    const selectedGradeId = Number(createForm.value.grade_id)

    if (Number.isFinite(selectedYear) && selectedYear >= 2000 && selectedYear <= 2100) {
      await loadGrades(selectedYear)
    }

    if (selectedGradeId > 0) {
      await loadClasses(selectedGradeId, selectedYear)
    }
  },
)

watch(
  () => route.name,
  async () => {
    errorMessage.value = ''
    reportErrorMessage.value = ''
    createSuccessMessage.value = ''

    if (isReportView.value) {
      reportRows.value = []
      await loadReportGrades(Number(reportFilters.value.year) > 0 ? Number(reportFilters.value.year) : undefined)
      await loadStudentReport()
      return
    }

    await loadStudents(1)
  },
)

const extractApiMessage = (error: unknown): string => {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { data?: { message?: string } } }).response
    if (typeof response?.data?.message === 'string' && response.data.message.trim() !== '') {
      return response.data.message
    }
  }

  return ''
}

const extractFieldErrors = (error: unknown): ValidationErrors => {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { data?: { errors?: Record<string, string[]> } } }).response
    const errors = response?.data?.errors
    if (errors && typeof errors === 'object') {
      const flattened: ValidationErrors = {}
      for (const [key, value] of Object.entries(errors)) {
        if (Array.isArray(value) && value.length > 0) {
          flattened[key] = value[0]
        }
      }
      return flattened
    }
  }

  return {}
}

const submitAddStudent = async (): Promise<void> => {
  createErrorMessage.value = ''
  createSuccessMessage.value = ''
  fieldErrors.value = {}

  if (isEditMode.value && !canEditStudents.value) {
    createErrorMessage.value = text.value.noEditPermission
    await scrollToCreateErrorMessage()
    return
  }

  if (!isEditMode.value && !canCreateStudents.value) {
    createErrorMessage.value = text.value.noCreatePermission
    await scrollToCreateErrorMessage()
    return
  }
  const hasGrade = Number(createForm.value.grade_id) > 0
  const hasClass = Number(createForm.value.class_id) > 0

  if (hasGrade !== hasClass) {
    createErrorMessage.value = text.value.selectGradeAndClassTogether
    fieldErrors.value = { ...fieldErrors.value, grade_id: createErrorMessage.value, class_id: createErrorMessage.value }
    await scrollToCreateErrorMessage()
    return
  }

  if (isAdmin.value && Number(createForm.value.census_id) <= 0) {
    createErrorMessage.value = text.value.selectSchoolForStudent
    fieldErrors.value = { ...fieldErrors.value, census_id: text.value.selectSchoolShort }
    await scrollToCreateErrorMessage()
    return
  }

  const selectedYear = Number(createForm.value.year)
  const hasSelectedYear = Number.isFinite(selectedYear) && selectedYear >= 2000 && selectedYear <= 2100

  if (selectedYear > 0 && !hasSelectedYear) {
    createErrorMessage.value = text.value.selectValidAcademicYear
    fieldErrors.value = { ...fieldErrors.value, year: text.value.academicYearRequired }
    await scrollToCreateErrorMessage()
    return
  }
  creating.value = true

  const payload: CreateStudentPayload = {
    index_no: createForm.value.index_no.trim(),
    full_name: createForm.value.full_name.trim(),
    name_with_initials: createForm.value.name_with_initials.trim(),
    gender_id: Number(createForm.value.gender_id),
  }

  if (createForm.value.phone_no.trim() !== '') payload.phone_no = createForm.value.phone_no.trim()
  if (createForm.value.whatsapp_no.trim() !== '') payload.whatsapp_no = createForm.value.whatsapp_no.trim()
  if (createForm.value.phone_home.trim() !== '') payload.phone_home = createForm.value.phone_home.trim()
  if (createForm.value.address1.trim() !== '') payload.address1 = createForm.value.address1.trim()
  if (createForm.value.address2.trim() !== '') payload.address2 = createForm.value.address2.trim()
  if (createForm.value.email.trim() !== '') payload.email = createForm.value.email.trim()
  if (createForm.value.dob.trim() !== '') payload.dob = createForm.value.dob.trim()
  if (createForm.value.d_o_admission.trim() !== '') payload.d_o_admission = createForm.value.d_o_admission.trim()
  if (Number(createForm.value.ethnic_group_id) > 0) payload.ethnic_group_id = Number(createForm.value.ethnic_group_id)
  if (isAdmin.value && Number(createForm.value.census_id) > 0) payload.census_id = String(createForm.value.census_id)
  if (hasGrade) payload.grade_id = Number(createForm.value.grade_id)
  if (hasClass) payload.class_id = Number(createForm.value.class_id)
  if (hasSelectedYear) payload.year = selectedYear

  if (createForm.value.father_name.trim() !== '') payload.father_name = createForm.value.father_name.trim()
  if (createForm.value.father_job.trim() !== '') payload.father_job = createForm.value.father_job.trim()
  if (createForm.value.father_mobile.trim() !== '') payload.father_mobile = createForm.value.father_mobile.trim()
  if (createForm.value.mother_name.trim() !== '') payload.mother_name = createForm.value.mother_name.trim()
  if (createForm.value.mother_job.trim() !== '') payload.mother_job = createForm.value.mother_job.trim()
  if (createForm.value.mother_mobile.trim() !== '') payload.mother_mobile = createForm.value.mother_mobile.trim()
  if (createForm.value.guardian_name.trim() !== '') payload.guardian_name = createForm.value.guardian_name.trim()
  if (createForm.value.guardian_job.trim() !== '') payload.guardian_job = createForm.value.guardian_job.trim()
  if (createForm.value.guardian_mobile.trim() !== '') payload.guardian_mobile = createForm.value.guardian_mobile.trim()

  try {
    const { data } = isEditMode.value && editStudentId.value !== null
      ? await api.put(`/students/${editStudentId.value}`, payload)
      : await api.post('/students', payload)

    createSuccessMessage.value = typeof data?.message === 'string' ? data.message : ''
    if (isAdmin.value && !isEditMode.value && Number(createForm.value.census_id) > 0) {
      const censusId = Number(createForm.value.census_id)
      adminSchoolContextCensusId.value = censusId
      setSchoolContextCensusId(censusId)
    }
    resetCreateForm()
    showAddDialog.value = false
    editStudentId.value = null

    await loadStudents(meta.value.current_page)
  } catch (error) {
    const validationErrors = extractFieldErrors(error)
    const apiMessage = extractApiMessage(error)
    fieldErrors.value = validationErrors
    createErrorMessage.value =
      apiMessage ||
      (Object.keys(validationErrors).length > 0
        ? text.value.correctHighlightedFields
        : text.value.unableToSaveStudent)
    await scrollToCreateErrorMessage()
  } finally {
    creating.value = false
  }
}

const submitImport = async (): Promise<void> => {
  importErrorMessage.value = ''
  importResult.value = null
  createSuccessMessage.value = ''

  if (!canCreateStudents.value) {
    importErrorMessage.value = text.value.noCreatePermission
    return
  }

  if (isAdmin.value && importSchoolCensusId.value <= 0) {
    importErrorMessage.value = text.value.selectSchoolBeforeImport
    return
  }

  if (!importFile.value) {
    importErrorMessage.value = text.value.chooseFile
    return
  }

  importing.value = true

  try {
    const formData = new FormData()
    formData.append('file', importFile.value)

    if (isAdmin.value && importSchoolCensusId.value > 0) {
      const selectedSchoolCensusId = Number(importSchoolCensusId.value)
      formData.append('census_id', String(selectedSchoolCensusId))
      adminSchoolContextCensusId.value = selectedSchoolCensusId
      setSchoolContextCensusId(selectedSchoolCensusId)
    }

    const { data } = await api.post<StudentImportResponse>('/students/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    importResult.value = data.data ?? {
      imported_count: 0,
      failed_count: 0,
      skipped_count: 0,
      failed_rows: [],
    }

    createSuccessMessage.value = typeof data.message === 'string' && data.message.trim() !== ''
      ? `${data.message} ${text.value.importedCount}: ${importResult.value.imported_count}, ${text.value.failedCount}: ${importResult.value.failed_count}.`
      : ''

    await loadStudents(meta.value.current_page)

    if (importResult.value.failed_count === 0) {
      closeImportDialog()
    }
  } catch (error) {
    importErrorMessage.value = extractApiMessage(error) || text.value.unableToImportStudents
  } finally {
    importing.value = false
  }
}

const downloadTemplate = async (): Promise<void> => {
  errorMessage.value = ''
  downloadingTemplate.value = true

  try {
    const response = await api.get('/students/template', {
      responseType: 'blob',
    })

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'students-template.xlsx'
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    errorMessage.value = extractApiMessage(error) || text.value.unableToDownloadTemplate
  } finally {
    downloadingTemplate.value = false
  }
}

onMounted(async () => {
  await loadStudentOptions()

  if (isReportView.value) {
    await loadReportGrades()
    await loadStudentReport()
    return
  }

  await loadGrades()
  await loadStudents(1)
})
</script>







