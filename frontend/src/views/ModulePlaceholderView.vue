<template>
  <ModuleShell
    :title="title"
    :subtitle="subtitle"
    :supports-reports="supportsReports"
    :active-tab="activeTab"
    :message="message"
    :error="error"
    @change-tab="activeTab = $event"
  >
    <GradesModulePanel
      v-if="isGrades"
      :active-tab="activeTab"
      :is-admin="isAdmin"
      :is-principal="isPrincipal"
      :can-manage="canManage"
      :latest-year="latestGradeYear"
      :target-year="targetYear"
      :grades="grades"
      :grade-edits="gradeEdits"
      :staff-options="staffOptions"
      :report-year="reportYear"
      :year-options="yearOptions"
      :grade-report="gradeReport"
      @update:target-year="targetYear = $event"
      @update:report-year="reportYear = $event"
      @update-grade-head="onUpdateGradeHead"
      @initialize-year="initializeYear"
      @save-grade="saveGrade"
      @delete-grade="deleteGrade"
      @load-report="loadGradeReport"
    />

    <ClassesModulePanel
      v-if="isClasses"
      :active-tab="activeTab"
      :is-admin="isAdmin"
      :can-manage="canManage"
      :latest-year="latestClassYear"
      :selected-year="classYear"
      :selected-grade-id="selectedGradeId"
      :class-grade-options="classCreateGradeOptions"
      :classes="classes"
      :class-teacher-edits="classTeacherEdits"
      :class-approved-edits="classApprovedEdits"
      :staff-options="staffOptions"
      :create-grade-id="createClassGradeId"
      :create-class-id="createClassId"
      :create-approved-count="createApprovedCount"
      :create-class-options="createClassOptions"
      :report-year="reportYear"
      :year-options="yearOptions"
      :class-report="classReport"
      @update:selected-year="onUpdateClassYear"
      @update:selected-grade="onUpdateSelectedGrade"
      @update:create-grade="createClassGradeId = $event"
      @update:create-class="createClassId = $event"
      @update:create-approved="createApprovedCount = $event"
      @update:report-year="reportYear = $event"
      @update-class-teacher="onUpdateClassTeacher"
      @update-class-approved="onUpdateClassApproved"
      @add-class="addClass"
      @quick-add-class="quickAddClass"
      @save-class="saveClass"
      @delete-class="deleteClass"
      @load-report="loadClassReport"
    />

    <StaffModulePanel
      v-if="isStaff"
      :active-tab="activeTab"
      :is-admin="isAdmin"
      :can-manage="canManage"
      :staff-search="staffSearch"
      :selected-school-census-id="selectedStaffSchoolCensusId"
      :staff-schools="staffSchools"
      :staff-rows="staffRows"
      :staff-meta="staffMeta"
      :report-filters="staffReportFilters"
      :report-rows="staffReportRows"
      :genders="staffGenders"
      :civil-statuses="staffCivilStatuses"
      :ethnic-groups="staffEthnicGroups"
      :religions="staffReligions"
      :education-levels="educationLevels"
      :professional-levels="professionalLevels"
      :designations="staffDesignations"
      :service-grades="serviceGrades"
      :sections="sections"
      :section-roles="sectionRoles"
      :staff-types="staffTypes"
      :staff-statuses="staffStatuses"
      :service-statuses="serviceStatuses"
      :subject-mediums="subjectMediums"
      :loading-edit-staff-id="loadingEditStaffId"
      @update:staff-search="staffSearch = $event"
      @update:selected-school-census-id="selectedStaffSchoolCensusId = $event"
      @update:report-filters="updateStaffReportFilters"
      @load-staff="loadStaff"
      @load-report="loadStaffReport"
      @reset-report-filters="resetStaffReportFilters"
      @open-add-staff="openAddStaffDialog"
      @open-edit-staff="openEditStaffDialog"
    />

    <ModuleQueuedNotice
      v-if="!supportsReports"
      :message="text.queuedModuleMessage"
    />
  </ModuleShell>

  <div v-if="showStaffCredentialsDialog" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeStaffCredentialsDialog">
    <section class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="font-display text-xl font-bold text-slate-900">{{ text.staffLoginCreatedTitle }}</h2>
        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeStaffCredentialsDialog">{{ text.close }}</button>
      </div>

      <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
        {{ text.staffLoginCreatedHelp }}
      </p>

      <div class="grid gap-3 md:grid-cols-2">
        <label class="text-sm text-slate-700">
          {{ text.loginUsername }}
          <input :value="staffCredentials.username" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
        </label>

        <label class="text-sm text-slate-700">
          {{ text.temporaryPassword }}
          <input :value="staffCredentials.temporaryPassword" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
        </label>
      </div>

      <div class="mt-5 flex justify-end gap-2">
        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeStaffCredentialsDialog">
          {{ text.close }}
        </button>
      </div>
    </section>
  </div>

  <div v-if="showAddStaffDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeAddStaffDialog">
    <section class="max-h-[90vh] w-full max-w-6xl overflow-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="font-display text-xl font-bold text-slate-900">{{ isStaffEditMode ? text.editStaffTitle : text.addStaffTitle }}</h2>
        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeAddStaffDialog">{{ text.close }}</button>
      </div>

      <p
        v-if="staffError"
        ref="staffErrorRef"
        tabindex="-1"
        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 focus:outline-none focus:ring-1 focus:ring-red-200"
      >
        {{ staffError }}
      </p>

      <form class="grid gap-4 md:grid-cols-3" @submit.prevent="saveStaff">
        <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
          <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.staffCore }}</legend>
          <div class="grid gap-3 md:grid-cols-3">
            <label class="text-sm text-slate-700">
              {{ text.titleLabel }} <span class="text-red-600">*</span>
              <select v-model="staffForm.title" :class="staffInputClass('title')">
                <option value="">{{ text.selectTitle }}</option>
                <option v-for="row in titleOptions" :key="row.value" :value="row.value">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.title" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.title }}</p>
            </label>

            <label v-if="isAdmin" class="text-sm text-slate-700">
              {{ text.school }} <span class="text-red-600">*</span>
              <select v-model.number="staffForm.census_id" :class="staffInputClass('census_id')">
                <option :value="0">{{ text.selectSchool }}</option>
                <option v-for="row in staffSchools" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.census_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.census_id }}</p>
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.fullName }} <span class="text-red-600">*</span>
              <input v-model="staffForm.full_name" type="text" :class="staffInputClass('full_name')" />
              <p v-if="staffFieldErrors.full_name" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.full_name }}</p>
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.nameWithInitials }} <span class="text-red-600">*</span>
              <input v-model="staffForm.name_with_ini" type="text" :class="staffInputClass('name_with_ini')" />
              <p v-if="staffFieldErrors.name_with_ini" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.name_with_ini }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.nickName }}
              <input v-model="staffForm.nick_name" type="text" :class="staffInputClass('nick_name')" />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.gender }} <span class="text-red-600">*</span>
              <select v-model.number="staffForm.gender_id" :class="staffInputClass('gender_id')">
                <option :value="0">{{ text.selectGender }}</option>
                <option v-for="row in staffGenders" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.gender_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.gender_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.civilStatus }}
              <select v-model.number="staffForm.civil_status_id" :class="staffInputClass('civil_status_id')">
                <option :value="0">{{ text.selectCivilStatus }}</option>
                <option v-for="row in staffCivilStatuses" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.nic }} <span class="text-red-600">*</span>
              <input v-model="staffForm.nic_no" type="text" :class="staffInputClass('nic_no')" />
              <p v-if="staffFieldErrors.nic_no" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.nic_no }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.dob }}
              <input v-model="staffForm.dob" type="date" :class="staffInputClass('dob')" />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.ethnicGroup }}
              <select v-model.number="staffForm.ethnic_group_id" :class="staffInputClass('ethnic_group_id')">
                <option :value="0">{{ text.selectEthnicGroup }}</option>
                <option v-for="row in staffEthnicGroups" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.religion }}
              <select v-model.number="staffForm.religion_id" :class="staffInputClass('religion_id')">
                <option :value="0">{{ text.selectReligion }}</option>
                <option v-for="row in staffReligions" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.educationLevel }}
              <select v-model.number="staffForm.edu_q_id" :class="staffInputClass('edu_q_id')">
                <option :value="0">{{ text.selectEducationLevel }}</option>
                <option v-for="row in educationLevels" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.edu_q_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.edu_q_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.professionalLevel }}
              <select v-model.number="staffForm.prof_q_id" :class="staffInputClass('prof_q_id')">
                <option :value="0">{{ text.selectProfessionalLevel }}</option>
                <option v-for="row in professionalLevels" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.prof_q_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.prof_q_id }}</p>
            </label>
          </div>
        </fieldset>

        <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
          <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.staffContact }}</legend>
          <div class="grid gap-3 md:grid-cols-3">
            <label class="text-sm text-slate-700">
              {{ text.mobile }}
              <input v-model="staffForm.phone_mobile1" type="text" :class="staffInputClass('phone_mobile1')" />
            </label>
            <label class="text-sm text-slate-700">
              {{ text.mobileTwo }}
              <input v-model="staffForm.phone_mobile2" type="text" :class="staffInputClass('phone_mobile2')" />
            </label>
            <label class="text-sm text-slate-700">
              {{ text.homePhone }}
              <input v-model="staffForm.phone_home" type="text" :class="staffInputClass('phone_home')" />
            </label>
            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.addressLine1 }}
              <input v-model="staffForm.address1" type="text" :class="staffInputClass('address1')" />
            </label>
            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.addressLine2 }}
              <input v-model="staffForm.address2" type="text" :class="staffInputClass('address2')" />
            </label>
            <label class="text-sm text-slate-700">
              {{ text.email }}
              <input v-model="staffForm.email" type="email" :class="staffInputClass('email')" />
            </label>
            <label class="text-sm text-slate-700">
              {{ text.vehicleOne }}
              <input v-model="staffForm.vehicle_no1" type="text" :class="staffInputClass('vehicle_no1')" />
            </label>
            <label class="text-sm text-slate-700">
              {{ text.vehicleTwo }}
              <input v-model="staffForm.vehicle_no2" type="text" :class="staffInputClass('vehicle_no2')" />
            </label>
          </div>
        </fieldset>

        <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
          <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.staffWork }}</legend>
          <div class="grid gap-3 md:grid-cols-3">
            <label class="text-sm text-slate-700">
              {{ text.designation }} <span class="text-red-600">*</span>
              <select v-model.number="staffForm.desig_id" :class="staffInputClass('desig_id')">
                <option :value="0">{{ text.selectDesignation }}</option>
                <option v-for="row in staffDesignations" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.desig_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.desig_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.staffType }}
              <select v-model.number="staffForm.stf_type_id" :class="staffInputClass('stf_type_id')">
                <option :value="0">{{ text.selectStaffType }}</option>
                <option v-for="row in staffTypes" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.staffStatus }}
              <select v-model.number="staffForm.stf_status_id" :class="staffInputClass('stf_status_id')">
                <option :value="0">{{ text.selectStaffStatus }}</option>
                <option v-for="row in staffStatuses" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.serviceGrade }}
              <select v-model.number="staffForm.serv_grd_id" :class="staffInputClass('serv_grd_id')">
                <option :value="0">{{ text.selectServiceGrade }}</option>
                <option v-for="row in serviceGrades" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.serv_grd_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.serv_grd_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.serviceStatus }}
              <select v-model.number="staffForm.service_status_id" :class="staffInputClass('service_status_id')">
                <option :value="0">{{ text.selectServiceStatus }}</option>
                <option v-for="row in serviceStatuses" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.subjectMedium }}
              <select v-model.number="staffForm.subj_med_id" :class="staffInputClass('subj_med_id')">
                <option :value="0">{{ text.selectSubjectMedium }}</option>
                <option v-for="row in subjectMediums" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.appointmentType }}
              <select v-model.number="staffForm.app_type_id" :class="staffInputClass('app_type_id')">
                <option :value="0">{{ text.selectAppointmentType }}</option>
                <option v-for="row in appointmentTypes" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.appointmentSubject }}
              <select v-model.number="staffForm.app_subj_id" :class="staffInputClass('app_subj_id')" :disabled="filteredAppointmentSubjects.length === 0">
                <option :value="0">{{ text.selectAppointmentSubject }}</option>
                <option v-for="row in filteredAppointmentSubjects" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.firstAppDate }}
              <input v-model="staffForm.first_app_dt" type="date" :class="staffInputClass('first_app_dt')" />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.serviceGradeDate }}
              <input v-model="staffForm.serv_grd_effective_dt" type="date" :class="staffInputClass('serv_grd_effective_dt')" />
              <p v-if="staffFieldErrors.serv_grd_effective_dt" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.serv_grd_effective_dt }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.salaryIncreaseDate }}
              <input v-model="staffForm.sal_incr_dt" type="date" :class="staffInputClass('sal_incr_dt')" />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.staffNo }}
              <input v-model="staffForm.stf_no" type="number" min="1" :class="staffInputClass('stf_no')" />
            </label>

            <label class="text-sm text-slate-700">
              {{ text.salaryNo }}
              <input v-model="staffForm.salary_no" type="number" min="1" :class="staffInputClass('salary_no')" />
            </label>
          </div>
        </fieldset>

        <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
          <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.currentSchoolDetails }}</legend>
          <div class="grid gap-3 md:grid-cols-3">
            <label class="text-sm text-slate-700">
              {{ text.section }}
              <select v-model.number="staffForm.sec_id" :class="staffInputClass('sec_id')">
                <option :value="0">{{ text.selectSection }}</option>
                <option v-for="row in sections" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.sec_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.sec_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.sectionRole }}
              <select v-model.number="staffForm.sec_role_id" :class="staffInputClass('sec_role_id')">
                <option :value="0">{{ text.selectSectionRole }}</option>
                <option v-for="row in sectionRoles" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.sec_role_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.sec_role_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.startDateSchool }}
              <input v-model="staffForm.start_dt_this_sch" type="date" :class="staffInputClass('start_dt_this_sch')" />
            </label>
          </div>
        </fieldset>

        <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
          <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.involvedTasks }}</legend>
          <div class="grid gap-3 md:grid-cols-3">
            <label class="text-sm text-slate-700">
              {{ text.mainTask }}
              <select v-model.number="staffForm.main_task_id" :class="staffInputClass('main_task_id')">
                <option :value="0">{{ text.selectMainTask }}</option>
                <option v-for="row in involvedTasks" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.main_task_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.main_task_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.mainTaskSection }}
              <select v-model.number="staffForm.main_task_section_id" :class="staffInputClass('main_task_section_id')">
                <option :value="0">{{ text.selectTaskSection }}</option>
                <option v-for="row in sections" :key="`main-${row.id}`" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.main_task_section_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.main_task_section_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.mainTaskSubject }}
              <select v-model.number="staffForm.main_task_subject_id" :class="staffInputClass('main_task_subject_id')">
                <option :value="0">{{ text.selectTaskSubject }}</option>
                <option v-for="row in mainTaskSubjects" :key="`main-subj-${row.id}`" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.main_task_subject_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.main_task_subject_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.secondTask }}
              <select v-model.number="staffForm.second_task_id" :class="staffInputClass('second_task_id')">
                <option :value="0">{{ text.selectSecondTask }}</option>
                <option v-for="row in involvedTasks" :key="`second-${row.id}`" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.second_task_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.second_task_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.secondTaskSection }}
              <select v-model.number="staffForm.second_task_section_id" :class="staffInputClass('second_task_section_id')">
                <option :value="0">{{ text.selectTaskSection }}</option>
                <option v-for="row in sections" :key="`second-sec-${row.id}`" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.second_task_section_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.second_task_section_id }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.secondTaskSubject }}
              <select v-model.number="staffForm.second_task_subject_id" :class="staffInputClass('second_task_subject_id')">
                <option :value="0">{{ text.selectTaskSubject }}</option>
                <option v-for="row in secondTaskSubjects" :key="`second-subj-${row.id}`" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.second_task_subject_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.second_task_subject_id }}</p>
            </label>
          </div>
        </fieldset>

        <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
          <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.serviceStatusDetails }}</legend>
          <div class="grid gap-3 md:grid-cols-3">
            <label class="text-sm text-slate-700">
              {{ text.attachedProvince }}
              <select v-model.number="staffForm.service_status_province_id" :class="staffInputClass('service_status_province_id')">
                <option :value="0">{{ text.selectAttachedProvince }}</option>
                <option v-for="row in provinces" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.attachedZone }}
              <select v-model.number="staffForm.service_status_zone_id" :class="staffInputClass('service_status_zone_id')">
                <option :value="0">{{ text.selectAttachedZone }}</option>
                <option v-for="row in zones" :key="row.id" :value="row.id">{{ row.label }}</option>
              </select>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.attachedSchool }}
              <select v-model="staffForm.service_status_school_census_id" :class="staffInputClass('service_status_school_census_id')">
                <option value="">{{ text.selectAttachedSchool }}</option>
                <option v-for="row in serviceStatusSchools" :key="row.id" :value="String(row.id)">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.service_status_school_census_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.service_status_school_census_id }}</p>
            </label>

            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.customInstitute }}
              <input v-model="staffForm.service_status_custom_institute" type="text" :class="staffInputClass('service_status_custom_institute')" />
              <p v-if="staffFieldErrors.service_status_institute" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.service_status_institute }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.serviceStatusEffectiveDate }}
              <input v-model="staffForm.service_status_effective_date" type="date" :class="staffInputClass('service_status_effective_date')" />
              <p v-if="staffFieldErrors.service_status_effective_date" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.service_status_effective_date }}</p>
            </label>

            <label class="text-sm text-slate-700">
              {{ text.serviceStatusPeriod }}
              <input v-model="staffForm.service_status_period" type="text" :class="staffInputClass('service_status_period')" />
              <p v-if="staffFieldErrors.service_status_period" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.service_status_period }}</p>
            </label>

            <label class="flex items-center gap-2 pt-7 text-sm text-slate-700">
              <input v-model="staffForm.service_status_is_current" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
              {{ text.serviceStatusCurrent }}
            </label>
          </div>
        </fieldset>

        <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
          <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.userLogin }}</legend>
          <div class="grid gap-3 md:grid-cols-3">
            <label class="flex items-center gap-2 pt-7 text-sm text-slate-700">
              <input v-model="staffForm.create_user_login" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
              {{ text.enableUserLogin }}
            </label>

            <label class="text-sm text-slate-700">
              {{ text.userRole }}
              <select v-model.number="staffForm.login_role_id" :class="staffInputClass('login_role_id')" :disabled="!staffForm.create_user_login">
                <option :value="0">{{ text.selectUserRole }}</option>
                <option v-for="row in loginRoles" :key="`login-role-${row.id}`" :value="row.id">{{ row.label }}</option>
              </select>
              <p v-if="staffFieldErrors.login_role_id" class="mt-1 text-xs text-red-600">{{ staffFieldErrors.login_role_id }}</p>
            </label>

            <label v-if="staffForm.login_username" class="text-sm text-slate-700">
              {{ text.loginUsername }}
              <input :value="staffForm.login_username" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
            </label>
            <p v-else-if="staffForm.create_user_login" class="pt-7 text-sm text-slate-500">
              {{ staffForm.nic_no.trim() || '-' }}
            </p>
          </div>
        </fieldset>

        <fieldset class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
          <legend class="px-1 text-xs font-extrabold uppercase tracking-[0.2em] text-slate-500">{{ text.photo }}</legend>
          <div class="grid gap-3 md:grid-cols-3">
            <label class="text-sm text-slate-700 md:col-span-2">
              {{ text.selectPhoto }}
              <input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" type="file" accept=".jpg,.jpeg,.png,.webp" @change="onStaffPhotoChange" />
              <p class="mt-1 text-xs text-slate-500">{{ text.photoHelp }}</p>
            </label>

            <div class="text-sm text-slate-700">
              <p>{{ text.photoPreview }}</p>
              <img v-if="staffPhotoPreview" :src="staffPhotoPreview" alt="Staff photo preview" class="mt-2 h-28 w-24 rounded-lg border border-slate-200 object-cover" />
            </div>
          </div>
        </fieldset>

        <div class="md:col-span-3 flex justify-end gap-2">
          <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeAddStaffDialog">
            {{ text.cancel }}
          </button>
          <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="savingStaff">
            {{ savingStaff ? (isStaffEditMode ? text.updating : text.saving) : (isStaffEditMode ? text.updateStaff : text.saveStaff) }}
          </button>
        </div>
      </form>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import ClassesModulePanel from '../components/modules/ClassesModulePanel.vue'
import GradesModulePanel from '../components/modules/GradesModulePanel.vue'
import ModuleQueuedNotice from '../components/modules/ModuleQueuedNotice.vue'
import ModuleShell from '../components/modules/ModuleShell.vue'
import StaffModulePanel from '../components/modules/StaffModulePanel.vue'
import api from '../services/api'
import { getSchoolContextCensusId, getUser, setSchoolContextCensusId } from '../services/auth'
import { useUiStore } from '../stores/ui'
import { useLocalizedText } from '../utils/uiText'

const props = defineProps<{ moduleKey: string }>()
const ui = useUiStore()

type TabKey = 'view' | 'reports'

interface Grade { sch_grd_id: number | null; census_id: number | null; school_name: string | null; grade_id: number | null; grade: string | null; year: number | null; stf_id: number | null; grade_head: string | null; date_updated: string | null }
interface GradeReportRow { grade_id: number; grade: string; year: number; student_count: number }
interface ClassItem { sch_grd_cls_id: number | null; census_id: number | null; school_name: string | null; grade_id: number | null; grade: string | null; class_id: number | null; class: string | null; year: number | null; stf_id: number | null; approved_std_count: number | null; std_count: number | null; class_teacher: string | null }
interface ClassReportRow { grade_id: number; grade: string; class_id: number; class: string; year: number; student_count: number }
interface StaffRow { stf_id: number; census_id: string | null; name_with_ini: string; nic_no: string | null; gender: string | null; phone_mobile1: string | null; designation: string | null; school_name: string | null; can_edit?: boolean }
interface StaffMeta { current_page: number; per_page: number; total: number; last_page: number }
interface StaffReportFilters {
  q: string
  school_census_id: number
  gender_id: number
  civil_status_id: number
  ethnic_group_id: number
  religion_id: number
  edu_q_id: number
  prof_q_id: number
  desig_id: number
  serv_grd_id: number
  sec_id: number
  sec_role_id: number
  stf_type_id: number
  stf_status_id: number
  service_status_id: number
  subj_med_id: number
}
interface StaffOption { stf_id: number; name_with_ini: string }
interface OptionRow { id: number; label: string; app_type_id?: number; section_id?: number }
interface ClassGradeOption { grade_id: number; grade: string }
interface ClassOption { class_id: number; class: string }
interface StaffOptionsResponse {
  data: StaffOption[]
  schools?: OptionRow[]
  login_roles?: OptionRow[]
  genders?: OptionRow[]
  civil_statuses?: OptionRow[]
  ethnic_groups?: OptionRow[]
  religions?: OptionRow[]
  education_levels?: OptionRow[]
  professional_levels?: OptionRow[]
  designations?: OptionRow[]
  service_grades?: OptionRow[]
  sections?: OptionRow[]
  section_roles?: OptionRow[]
  staff_types?: OptionRow[]
  staff_statuses?: OptionRow[]
  service_statuses?: OptionRow[]
  provinces?: OptionRow[]
  zones?: OptionRow[]
  all_schools?: OptionRow[]
  subject_mediums?: OptionRow[]
  appointment_types?: OptionRow[]
  appointment_subjects?: OptionRow[]
  involved_tasks?: OptionRow[]
  subjects?: OptionRow[]
}

const createDefaultStaffReportFilters = (): StaffReportFilters => ({
  q: '',
  school_census_id: 0,
  gender_id: 0,
  civil_status_id: 0,
  ethnic_group_id: 0,
  religion_id: 0,
  edu_q_id: 0,
  prof_q_id: 0,
  desig_id: 0,
  serv_grd_id: 0,
  sec_id: 0,
  sec_role_id: 0,
  stf_type_id: 0,
  stf_status_id: 0,
  service_status_id: 0,
  subj_med_id: 0,
})
interface StaffCreatePayload {
  title: string
  full_name: string
  name_with_ini: string
  gender_id: number
  desig_id: number
  census_id?: string
  nick_name?: string
  address1?: string
  address2?: string
  nic_no?: string
  dob?: string
  civil_status_id?: number
  ethnic_group_id?: number
  religion_id?: number
  phone_home?: string
  phone_mobile1?: string
  phone_mobile2?: string
  vehicle_no1?: string
  vehicle_no2?: string
  email?: string
  edu_q_id?: number
  prof_q_id?: number
  stf_type_id?: number
  stf_status_id?: number
  service_status_id?: number
  serv_grd_id?: number
  sec_id?: number
  sec_role_id?: number
  subj_med_id?: number
  app_type_id?: number
  app_subj_id?: number
  first_app_dt?: string
  start_dt_this_sch?: string
  serv_grd_effective_dt?: string
  sal_incr_dt?: string
  stf_no?: number
  salary_no?: number
  main_task_id?: number
  main_task_section_id?: number
  main_task_subject_id?: number
  second_task_id?: number
  second_task_section_id?: number
  second_task_subject_id?: number
  service_status_institute?: string
  service_status_province_id?: number
  service_status_zone_id?: number
  service_status_school_census_id?: string
  service_status_custom_institute?: string
  service_status_effective_date?: string
  service_status_period?: string
  service_status_is_current?: boolean
  create_user_login?: boolean
  login_role_id?: number
}
interface StaffDetailResponse {
  data: Record<string, string | number | boolean | null>
}
interface StaffLoginAccountResult {
  created?: boolean
  updated?: boolean
  disabled?: boolean
  username?: string | null
  temporary_password?: string | null
}
interface StaffSaveResponse {
  message?: string
  data?: {
    stf_id?: number
    name_with_ini?: string
    school_name?: string | null
    login_account?: StaffLoginAccountResult
  }
}
type ValidationErrors = Record<string, string>

const currentUser = getUser()
const isAdmin = computed(() => (currentUser?.role_id ?? 0) === 1)
const isPrincipal = computed(() => (currentUser?.role_id ?? 0) === 2)
const canManage = computed(() => isAdmin.value || isPrincipal.value)

const activeTab = ref<TabKey>('view')
const loading = ref(false)
const error = ref('')
const message = ref('')

const targetYear = ref(new Date().getFullYear())
const staffOptions = ref<StaffOption[]>([])
const gradeEdits = reactive<Record<number, number>>({})
const classTeacherEdits = reactive<Record<number, number>>({})
const classApprovedEdits = reactive<Record<number, number>>({})
const grades = ref<Grade[]>([])
const latestGradeYear = ref<number | null>(null)
const gradeReport = ref<GradeReportRow[]>([])
const classes = ref<ClassItem[]>([])
const latestClassYear = ref<number | null>(null)
const classReport = ref<ClassReportRow[]>([])
const classYear = ref(new Date().getFullYear())
const selectedGradeId = ref(0)
const classCreateGradeOptions = ref<ClassGradeOption[]>([])
const createClassGradeId = ref(0)
const createClassId = ref(0)
const createApprovedCount = ref(35)
const createClassOptions = ref<ClassOption[]>([])
const reportYear = ref(0)
const staffSearch = ref('')
const selectedStaffSchoolCensusId = ref(getSchoolContextCensusId() ?? 0)
const staffRows = ref<StaffRow[]>([])
const staffMeta = reactive<StaffMeta>({ current_page: 1, per_page: 20, total: 0, last_page: 1 })
const staffReportRows = ref<StaffRow[]>([])
const staffReportFilters = reactive<StaffReportFilters>(createDefaultStaffReportFilters())
const showAddStaffDialog = ref(false)
const showStaffCredentialsDialog = ref(false)
const editStaffId = ref<number | null>(null)
const isStaffEditMode = computed(() => editStaffId.value !== null)
const loadingEditStaffId = ref<number | null>(null)
const savingStaff = ref(false)
const staffError = ref('')
const staffFieldErrors = ref<ValidationErrors>({})
const staffErrorRef = ref<HTMLElement | null>(null)
const staffSchools = ref<OptionRow[]>([])
const loginRoles = ref<OptionRow[]>([])
const staffGenders = ref<OptionRow[]>([])
const staffCivilStatuses = ref<OptionRow[]>([])
const staffEthnicGroups = ref<OptionRow[]>([])
const staffReligions = ref<OptionRow[]>([])
const educationLevels = ref<OptionRow[]>([])
const professionalLevels = ref<OptionRow[]>([])
const staffDesignations = ref<OptionRow[]>([])
const serviceGrades = ref<OptionRow[]>([])
const sections = ref<OptionRow[]>([])
const sectionRoles = ref<OptionRow[]>([])
const staffTypes = ref<OptionRow[]>([])
const staffStatuses = ref<OptionRow[]>([])
const serviceStatuses = ref<OptionRow[]>([])
const provinces = ref<OptionRow[]>([])
const zones = ref<OptionRow[]>([])
const serviceStatusSchools = ref<OptionRow[]>([])
const subjectMediums = ref<OptionRow[]>([])
const appointmentTypes = ref<OptionRow[]>([])
const appointmentSubjects = ref<OptionRow[]>([])
const involvedTasks = ref<OptionRow[]>([])
const subjects = ref<OptionRow[]>([])
const staffPhotoFile = ref<File | null>(null)
const staffPhotoPreview = ref('')
const staffCredentials = reactive({
  username: '',
  temporaryPassword: '',
})
const staffForm = reactive({
  title: '',
  census_id: getSchoolContextCensusId() ?? 0,
  full_name: '',
  name_with_ini: '',
  nick_name: '',
  nic_no: '',
  dob: '',
  gender_id: 0,
  civil_status_id: 0,
  ethnic_group_id: 0,
  religion_id: 0,
  phone_home: '',
  phone_mobile1: '',
  phone_mobile2: '',
  address1: '',
  address2: '',
  email: '',
  vehicle_no1: '',
  vehicle_no2: '',
  edu_q_id: 0,
  prof_q_id: 0,
  desig_id: 0,
  serv_grd_id: 0,
  sec_id: 0,
  sec_role_id: 0,
  stf_type_id: 0,
  stf_status_id: 0,
  service_status_id: 0,
  subj_med_id: 0,
  app_type_id: 0,
  app_subj_id: 0,
  first_app_dt: '',
  start_dt_this_sch: '',
  serv_grd_effective_dt: '',
  sal_incr_dt: '',
  stf_no: '',
  salary_no: '',
  main_task_id: 0,
  main_task_section_id: 0,
  main_task_subject_id: 0,
  second_task_id: 0,
  second_task_section_id: 0,
  second_task_subject_id: 0,
  service_status_institute: '',
  service_status_province_id: 0,
  service_status_zone_id: 0,
  service_status_school_census_id: '',
  service_status_custom_institute: '',
  service_status_effective_date: '',
  service_status_period: '',
  service_status_is_current: true,
  create_user_login: false,
  login_role_id: 0,
  login_username: '',
})

const yearOptions = computed(() => { const now = new Date().getFullYear(); return Array.from({ length: 8 }, (_, i) => now - i) })
const isGrades = computed(() => props.moduleKey === 'grades')
const isClasses = computed(() => props.moduleKey === 'classes')
const isStaff = computed(() => props.moduleKey === 'staff')
const supportsReports = computed(() => isGrades.value || isClasses.value || isStaff.value)
const titleOptions = computed(() => [
  { value: 'Mr', label: 'Mr' },
  { value: 'Mrs', label: 'Mrs' },
  { value: 'Miss', label: 'Miss' },
  { value: 'Rev', label: 'Rev' },
  { value: 'Dr', label: 'Dr' },
])
const filteredAppointmentSubjects = computed(() => {
  if (staffForm.app_type_id <= 0) {
    return appointmentSubjects.value
  }

  return appointmentSubjects.value.filter((row) => Number(row.app_type_id ?? 0) === Number(staffForm.app_type_id))
})
const mainTaskSubjects = computed(() => (
  staffForm.main_task_section_id > 0
    ? subjects.value.filter((row) => Number(row.section_id ?? 0) === Number(staffForm.main_task_section_id))
    : subjects.value
))
const secondTaskSubjects = computed(() => (
  staffForm.second_task_section_id > 0
    ? subjects.value.filter((row) => Number(row.section_id ?? 0) === Number(staffForm.second_task_section_id))
    : subjects.value
))
const text = useLocalizedText({
  en: {
    moduleFallbackTitle: 'Module',
    moduleFallbackSubtitle: 'This area is ready for the next rollout.',
    gradesTitle: 'Grades Management',
    gradesSubtitle: 'Manage grade assignments and view grade-based reports.',
    classesTitle: 'Classes Management',
    classesSubtitle: 'Manage class counts, class teachers, and class reports.',
    staffTitle: 'Staff Management',
    staffSubtitle: 'Search staff records and run filter-based staff reports.',
    addStaffTitle: 'Add Staff',
    editStaffTitle: 'Edit Staff',
    staffCore: 'Core Details',
    staffContact: 'Contact Details',
    staffWork: 'Work Details',
    currentSchoolDetails: 'Current School Details',
    involvedTasks: 'Involved Tasks',
    serviceStatusDetails: 'Service Status Details',
    paymentsTitle: 'SDS Payments',
    paymentsSubtitle: 'Payments screens will be connected in the next API rollout.',
    reportsTitle: 'Reporting & Exports',
    reportsSubtitle: 'Reporting tools are being prepared for a future release.',
    queuedModuleMessage: 'This module is queued for API rollout. Functional screens are enabled for Grades, Classes, and Staff first.',
    yearInitialized: 'Year initialized successfully.',
    initializeYearError: 'Unable to initialize year data.',
    gradeRowUpdated: 'Grade row updated.',
    updateGradeError: 'Unable to update grade row.',
    deleteGradeConfirm: 'Delete this grade row? Related classes for this grade/year will also be deleted.',
    gradeRowDeleted: 'Grade row deleted.',
    deleteGradeError: 'Unable to delete grade row.',
    classRowUpdated: 'Class row updated.',
    classAdded: 'Class added successfully.',
    classDeleted: 'Class row deleted.',
    updateClassError: 'Unable to update class row.',
    addClassError: 'Unable to add class row.',
    addNextClassError: 'No more classes available for this grade.',
    deleteClassConfirm: 'Delete this class row?',
    deleteClassError: 'Unable to delete class row.',
    moduleLoadError: 'Unable to load module data right now.',
    close: 'Close',
    cancel: 'Cancel',
    saving: 'Saving...',
    updating: 'Updating...',
    saveStaff: 'Save Staff',
    updateStaff: 'Update Staff',
    staffAdded: 'Staff added successfully.',
    staffUpdated: 'Staff updated successfully.',
    titleLabel: 'Title',
    selectTitle: 'Select title',
    school: 'School',
    selectSchool: 'Select school',
    fullName: 'Full Name',
    nameWithInitials: 'Name With Initials',
    nickName: 'Nick Name',
    gender: 'Gender',
    selectGender: 'Select gender',
    civilStatus: 'Civil Status',
    selectCivilStatus: 'Select civil status',
    nic: 'NIC',
    dob: 'Date of Birth',
    ethnicGroup: 'Ethnic Group',
    selectEthnicGroup: 'Select ethnic group',
    religion: 'Religion',
    selectReligion: 'Select religion',
    educationLevel: 'Education Level',
    selectEducationLevel: 'Select education level',
    professionalLevel: 'Professional Level',
    selectProfessionalLevel: 'Select professional level',
    mobile: 'Mobile 1',
    mobileTwo: 'Mobile 2',
    homePhone: 'Home Phone',
    addressLine1: 'Address Line 1',
    addressLine2: 'Address Line 2',
    email: 'Email',
    vehicleOne: 'Vehicle No 1',
    vehicleTwo: 'Vehicle No 2',
    designation: 'Designation',
    selectDesignation: 'Select designation',
    staffType: 'Staff Type',
    selectStaffType: 'Select staff type',
    staffStatus: 'Staff Status',
    selectStaffStatus: 'Select staff status',
    serviceStatus: 'Service Status',
    selectServiceStatus: 'Select service status',
    serviceGrade: 'Service Grade',
    selectServiceGrade: 'Select service grade',
    subjectMedium: 'Subject Medium',
    selectSubjectMedium: 'Select subject medium',
    appointmentType: 'Appointment Type',
    selectAppointmentType: 'Select appointment type',
    appointmentSubject: 'Appointment Subject',
    selectAppointmentSubject: 'Select appointment subject',
    firstAppDate: 'First Appointed Date',
    startDateSchool: 'Started In This School',
    serviceGradeDate: 'Service Grade Effective Date',
    salaryIncreaseDate: 'Salary Increment Date',
    section: 'Section',
    selectSection: 'Select section',
    sectionRole: 'Section Role',
    selectSectionRole: 'Select section role',
    staffNo: 'Staff No',
    salaryNo: 'Salary No',
    mainTask: 'Main Task',
    selectMainTask: 'Select main task',
    mainTaskSection: 'Main Task Section',
    mainTaskSubject: 'Main Task Subject',
    secondTask: 'Second Task',
    selectSecondTask: 'Select second task',
    secondTaskSection: 'Second Task Section',
    secondTaskSubject: 'Second Task Subject',
    selectTaskSection: 'Select task section',
    selectTaskSubject: 'Select task subject',
    serviceStatusInstitute: 'Institute',
    serviceStatusEffectiveDate: 'Effective Date',
    serviceStatusPeriod: 'Period',
    serviceStatusCurrent: 'Current status record',
    attachedProvince: 'Attached Province',
    selectAttachedProvince: 'Select attached province',
    attachedZone: 'Attached Zone',
    selectAttachedZone: 'Select attached zone',
    attachedSchool: 'Attached School',
    selectAttachedSchool: 'Select attached school',
    customInstitute: 'Other Institute',
    userLogin: 'User Login',
    enableUserLogin: 'Enable user login',
    userRole: 'User Role',
    selectUserRole: 'Select user role',
    loginUsername: 'Login Username',
    temporaryPassword: 'Temporary Password',
    staffLoginCreatedTitle: 'Login Created',
    staffLoginCreatedHelp: 'Please share these credentials with the user now. The temporary password will not be shown again.',
    photo: 'Profile Photo',
    selectPhoto: 'Choose photo',
    photoPreview: 'Preview',
    photoHelp: 'Accepted formats: JPG, JPEG, PNG, WEBP. Maximum file size: 2 MB. A clear portrait photo works best.',
  },
  si: {
    moduleFallbackTitle: 'මොඩියුලය',
    moduleFallbackSubtitle: 'මෙම කොටස ඊළඟ නිකුතුව සඳහා සූදානම්ය.',
    gradesTitle: 'ශ්‍රේණි කළමනාකරණය',
    gradesSubtitle: 'ශ්‍රේණි පැවරීම් කළමනාකරණය කර ශ්‍රේණි පදනම් වූ වාර්තා බලන්න.',
    classesTitle: 'පන්ති කළමනාකරණය',
    classesSubtitle: 'පන්ති සංඛ්‍යාව, පන්ති භාර ගුරුවරු සහ පන්ති වාර්තා කළමනාකරණය කරන්න.',
    staffTitle: 'කාර්ය මණ්ඩල කළමනාකරණය',
    staffSubtitle: 'කාර්ය මණ්ඩල වාර්තා සොයා පෙරහන් මත පදනම් වූ කාර්ය මණ්ඩල වාර්තා ධාවනය කරන්න.',
    addStaffTitle: 'කාර්ය මණ්ඩලය එක් කරන්න',
    editStaffTitle: 'කාර්ය මණ්ඩලය සංස්කරණය කරන්න',
    staffCore: 'මූලික තොරතුරු',
    staffContact: 'සම්බන්ධතා තොරතුරු',
    staffWork: 'සේවා තොරතුරු',
    currentSchoolDetails: 'වත්මන් පාසල් තොරතුරු',
    involvedTasks: 'නිරත වන කාර්යයන්',
    serviceStatusDetails: 'සේවා තත්ත්ව විස්තර',
    paymentsTitle: 'SDS ගෙවීම්',
    paymentsSubtitle: 'ගෙවීම් තිර ඊළඟ API නිකුතුවේ සම්බන්ධ වේ.',
    reportsTitle: 'වාර්තා සහ අපනයන',
    reportsSubtitle: 'අනාගත නිකුතුවක් සඳහා වාර්තා මෙවලම් සූදානම් වෙමින් ඇත.',
    queuedModuleMessage: 'මෙම මොඩියුලය API නිකුතුව සඳහා පෝලිමේ ඇත. දැනට Grades, Classes, සහ Staff තිර පමණක් ක්‍රියාත්මක වේ.',
    yearInitialized: 'වසර සාර්ථකව ආරම්භ කරන ලදී.',
    initializeYearError: 'වසර දත්ත ආරම්භ කළ නොහැක.',
    gradeRowUpdated: 'ශ්‍රේණි පේළිය යාවත්කාලීන කරන ලදී.',
    updateGradeError: 'ශ්‍රේණි පේළිය යාවත්කාලීන කළ නොහැක.',
    deleteGradeConfirm: 'මෙම ශ්‍රේණි පේළිය මකන්නද? මෙම ශ්‍රේණිය/වසරට අදාළ පන්තිද මකා දමනු ලැබේ.',
    gradeRowDeleted: 'ශ්‍රේණි පේළිය මකා දමන ලදී.',
    deleteGradeError: 'ශ්‍රේණි පේළිය මකා දැමිය නොහැක.',
    classRowUpdated: 'පන්ති පේළිය යාවත්කාලීන කරන ලදී.',
    classAdded: 'පන්තිය සාර්ථකව එක් කරන ලදී.',
    classDeleted: 'පන්ති පේළිය මකා දමන ලදී.',
    updateClassError: 'පන්ති පේළිය යාවත්කාලීන කළ නොහැක.',
    addClassError: 'පන්ති පේළිය එක් කළ නොහැක.',
    addNextClassError: 'මෙම ශ්‍රේණිය සඳහා තවත් පන්ති නොමැත.',
    deleteClassConfirm: 'මෙම පන්ති පේළිය මකන්නද?',
    deleteClassError: 'පන්ති පේළිය මකා දැමිය නොහැක.',
    moduleLoadError: 'දැනට මොඩියුල දත්ත පූරණය කළ නොහැක.',
    close: 'වසන්න',
    cancel: 'අවලංගු කරන්න',
    saving: 'සුරකිමින්...',
    updating: 'යාවත්කාලීන කරමින්...',
    saveStaff: 'කාර්ය මණ්ඩලය සුරකින්න',
    updateStaff: 'කාර්ය මණ්ඩලය යාවත්කාලීන කරන්න',
    staffAdded: 'කාර්ය මණ්ඩලය සාර්ථකව එක් කරන ලදී.',
    staffUpdated: 'කාර්ය මණ්ඩලය සාර්ථකව යාවත්කාලීන කරන ලදී.',
    titleLabel: 'Title',
    selectTitle: 'Title තෝරන්න',
    school: 'පාසල',
    selectSchool: 'පාසල තෝරන්න',
    fullName: 'සම්පූර්ණ නම',
    nameWithInitials: 'මුලකුරු සමඟ නම',
    nickName: 'විකල්ප නම',
    gender: 'ස්ත්‍රී/පුරුෂ භාවය',
    selectGender: 'ස්ත්‍රී/පුරුෂ භාවය තෝරන්න',
    civilStatus: 'විවාහක තත්ත්වය',
    selectCivilStatus: 'විවාහක තත්ත්වය තෝරන්න',
    nic: 'ජා.හැ.අංකය',
    dob: 'උපන් දිනය',
    ethnicGroup: 'ජන වර්ගය',
    selectEthnicGroup: 'ජන වර්ගය තෝරන්න',
    religion: 'ආගම',
    selectReligion: 'ආගම තෝරන්න',
    educationLevel: 'අධ්‍යාපන සුදුසුකම',
    selectEducationLevel: 'අධ්‍යාපන සුදුසුකම තෝරන්න',
    professionalLevel: 'වෘත්තීය සුදුසුකම',
    selectProfessionalLevel: 'වෘත්තීය සුදුසුකම තෝරන්න',
    mobile: 'ජංගම 1',
    mobileTwo: 'ජංගම 2',
    homePhone: 'නිවසේ දුරකථන',
    addressLine1: 'ලිපිනය 1',
    addressLine2: 'ලිපිනය 2',
    email: 'ඊමේල්',
    vehicleOne: 'වාහන අංක 1',
    vehicleTwo: 'වාහන අංක 2',
    designation: 'තනතුර',
    selectDesignation: 'තනතුර තෝරන්න',
    staffType: 'කාර්ය මණ්ඩල වර්ගය',
    selectStaffType: 'කාර්ය මණ්ඩල වර්ගය තෝරන්න',
    staffStatus: 'කාර්ය මණ්ඩල තත්ත්වය',
    selectStaffStatus: 'කාර්ය මණ්ඩල තත්ත්වය තෝරන්න',
    serviceStatus: 'සේවා තත්ත්වය',
    selectServiceStatus: 'සේවා තත්ත්වය තෝරන්න',
    serviceGrade: 'සේවා ශ්‍රේණිය',
    selectServiceGrade: 'සේවා ශ්‍රේණිය තෝරන්න',
    subjectMedium: 'විෂය මාධ්‍යය',
    selectSubjectMedium: 'විෂය මාධ්‍යය තෝරන්න',
    appointmentType: 'පත්වීම් වර්ගය',
    selectAppointmentType: 'පත්වීම් වර්ගය තෝරන්න',
    appointmentSubject: 'පත්වීම් විෂයය',
    selectAppointmentSubject: 'පත්වීම් විෂයය තෝරන්න',
    firstAppDate: 'පළමු පත්වීම් දිනය',
    startDateSchool: 'මෙම පාසලේ ආරම්භ කළ දිනය',
    serviceGradeDate: 'සේවා ශ්‍රේණි ක්‍රියාත්මක දිනය',
    salaryIncreaseDate: 'වැටුප් වර්ධක දිනය',
    section: 'අංශය',
    selectSection: 'අංශය තෝරන්න',
    sectionRole: 'අංශ භූමිකාව',
    selectSectionRole: 'අංශ භූමිකාව තෝරන්න',
    staffNo: 'කාර්ය මණ්ඩල අංකය',
    salaryNo: 'වැටුප් අංකය',
    mainTask: 'ප්‍රධාන කාර්යය',
    selectMainTask: 'ප්‍රධාන කාර්යය තෝරන්න',
    mainTaskSection: 'ප්‍රධාන කාර්යයේ අංශය',
    mainTaskSubject: 'ප්‍රධාන කාර්යයේ විෂයය',
    secondTask: 'දෙවන කාර්යය',
    selectSecondTask: 'දෙවන කාර්යය තෝරන්න',
    secondTaskSection: 'දෙවන කාර්යයේ අංශය',
    secondTaskSubject: 'දෙවන කාර්යයේ විෂයය',
    selectTaskSection: 'කාර්ය අංශය තෝරන්න',
    selectTaskSubject: 'කාර්ය විෂයය තෝරන්න',
    serviceStatusInstitute: 'ආයතනය',
    serviceStatusEffectiveDate: 'ක්‍රියාත්මක දිනය',
    serviceStatusPeriod: 'කාලය',
    serviceStatusCurrent: 'වත්මන් සේවා තත්ත්ව වාර්තාව',
    attachedProvince: 'අනුයුක්ත පළාත',
    selectAttachedProvince: 'අනුයුක්ත පළාත තෝරන්න',
    attachedZone: 'අනුයුක්ත කලාපය',
    selectAttachedZone: 'අනුයුක්ත කලාපය තෝරන්න',
    attachedSchool: 'අනුයුක්ත පාසල',
    selectAttachedSchool: 'අනුයුක්ත පාසල තෝරන්න',
    customInstitute: 'වෙනත් ආයතනය',
    userLogin: 'පරිශීලක පිවිසුම',
    enableUserLogin: 'පරිශීලක පිවිසුම සක්‍රිය කරන්න',
    userRole: 'පරිශීලක භූමිකාව',
    selectUserRole: 'පරිශීලක භූමිකාව තෝරන්න',
    loginUsername: 'පිවිසුම් නාමය',
    temporaryPassword: 'තාවකාලික මුරපදය',
    staffLoginCreatedTitle: 'පිවිසුම සාදන ලදී',
    staffLoginCreatedHelp: 'මෙම පිවිසුම් තොරතුරු දැන්ම පරිශීලකයාට ලබා දෙන්න. තාවකාලික මුරපදය නැවත නොපෙන්වයි.',
    photo: 'පැතිකඩ ඡායාරූපය',
    selectPhoto: 'ඡායාරූපය තෝරන්න',
    photoPreview: 'පෙරදසුන',
    photoHelp: 'JPG, JPEG, PNG, WEBP ගොනු පමණක්. උපරිම ගොනු ප්‍රමාණය 2 MB. පැහැදිලි portrait ඡායාරූපයක් වඩා හොඳයි.',
  },
  ta: {
    moduleFallbackTitle: 'தொகுதி',
    moduleFallbackSubtitle: 'இந்த பகுதி அடுத்த வெளியீட்டுக்கு தயாராக உள்ளது.',
    gradesTitle: 'தர மேலாண்மை',
    gradesSubtitle: 'தர ஒதுக்கீடுகளை நிர்வகித்து தர அடிப்படையிலான அறிக்கைகளை பாருங்கள்.',
    classesTitle: 'வகுப்பு மேலாண்மை',
    classesSubtitle: 'வகுப்பு எண்ணிக்கைகள், வகுப்பு ஆசிரியர்கள் மற்றும் வகுப்பு அறிக்கைகளை நிர்வகிக்கவும்.',
    staffTitle: 'பணியாளர் மேலாண்மை',
    staffSubtitle: 'பணியாளர் பதிவுகளை தேடி வடிகட்டி அடிப்படையிலான பணியாளர் அறிக்கைகளை இயக்குங்கள்.',
    addStaffTitle: 'பணியாளர் சேர்க்கவும்',
    editStaffTitle: 'பணியாளரைத் திருத்தவும்',
    staffCore: 'அடிப்படை தகவல்கள்',
    staffContact: 'தொடர்பு தகவல்கள்',
    staffWork: 'சேவை தகவல்கள்',
    currentSchoolDetails: 'தற்போதைய பாடசாலை தகவல்கள்',
    involvedTasks: 'ஈடுபட்ட பணிகள்',
    serviceStatusDetails: 'சேவை நிலை விவரங்கள்',
    paymentsTitle: 'SDS கட்டணங்கள்',
    paymentsSubtitle: 'கட்டண திரைகள் அடுத்த API வெளியீட்டில் இணைக்கப்படும்.',
    reportsTitle: 'அறிக்கைகள் மற்றும் ஏற்றுமதிகள்',
    reportsSubtitle: 'எதிர்கால வெளியீட்டிற்காக அறிக்கை கருவிகள் தயாராகின்றன.',
    queuedModuleMessage: 'இந்த தொகுதி API வெளியீட்டுக்காக வரிசையில் உள்ளது. தற்போது Grades, Classes மற்றும் Staff திரைகள் மட்டுமே செயல்படுகின்றன.',
    yearInitialized: 'ஆண்டு வெற்றிகரமாக தொடங்கப்பட்டது.',
    initializeYearError: 'ஆண்டு தரவை தொடங்க முடியவில்லை.',
    gradeRowUpdated: 'தர வரிசை புதுப்பிக்கப்பட்டது.',
    updateGradeError: 'தர வரிசையை புதுப்பிக்க முடியவில்லை.',
    deleteGradeConfirm: 'இந்த தர வரிசையை நீக்கவா? இந்த தரம்/ஆண்டுக்கான தொடர்புடைய வகுப்புகளும் நீக்கப்படும்.',
    gradeRowDeleted: 'தர வரிசை நீக்கப்பட்டது.',
    deleteGradeError: 'தர வரிசையை நீக்க முடியவில்லை.',
    classRowUpdated: 'வகுப்பு வரிசை புதுப்பிக்கப்பட்டது.',
    classAdded: 'வகுப்பு வெற்றிகரமாக சேர்க்கப்பட்டது.',
    classDeleted: 'வகுப்பு வரிசை நீக்கப்பட்டது.',
    updateClassError: 'வகுப்பு வரிசையை புதுப்பிக்க முடியவில்லை.',
    addClassError: 'வகுப்பு வரிசையை சேர்க்க முடியவில்லை.',
    addNextClassError: 'இந்த தரத்திற்காக மேலும் வகுப்புகள் இல்லை.',
    deleteClassConfirm: 'இந்த வகுப்பு வரிசையை நீக்கவா?',
    deleteClassError: 'வகுப்பு வரிசையை நீக்க முடியவில்லை.',
    moduleLoadError: 'இப்போது தொகுதி தரவை ஏற்ற முடியவில்லை.',
    close: 'மூடு',
    cancel: 'ரத்து செய்',
    saving: 'சேமிக்கப்படுகிறது...',
    updating: 'புதுப்பிக்கப்படுகிறது...',
    saveStaff: 'பணியாளர் சேமிக்கவும்',
    updateStaff: 'பணியாளரை புதுப்பிக்கவும்',
    staffAdded: 'பணியாளர் வெற்றிகரமாக சேர்க்கப்பட்டார்.',
    staffUpdated: 'பணியாளர் வெற்றிகரமாக புதுப்பிக்கப்பட்டார்.',
    titleLabel: 'Title',
    selectTitle: 'Title தேர்ந்தெடுக்கவும்',
    school: 'பாடசாலை',
    selectSchool: 'பாடசாலையைத் தேர்ந்தெடுக்கவும்',
    fullName: 'முழு பெயர்',
    nameWithInitials: 'முதலெழுத்துகளுடன் பெயர்',
    nickName: 'புனைப்பெயர்',
    gender: 'பால்',
    selectGender: 'பாலினத்தைத் தேர்ந்தெடுக்கவும்',
    civilStatus: 'திருமண நிலை',
    selectCivilStatus: 'திருமண நிலையைத் தேர்ந்தெடுக்கவும்',
    nic: 'தே.அ.அ',
    dob: 'பிறந்த தேதி',
    ethnicGroup: 'இனக்குழு',
    selectEthnicGroup: 'இனக்குழுவைத் தேர்ந்தெடுக்கவும்',
    religion: 'மதம்',
    selectReligion: 'மதத்தைத் தேர்ந்தெடுக்கவும்',
    educationLevel: 'கல்வித் தகுதி',
    selectEducationLevel: 'கல்வித் தகுதியைத் தேர்ந்தெடுக்கவும்',
    professionalLevel: 'தொழில்முறை தகுதி',
    selectProfessionalLevel: 'தொழில்முறை தகுதியைத் தேர்ந்தெடுக்கவும்',
    mobile: 'கைபேசி 1',
    mobileTwo: 'கைபேசி 2',
    homePhone: 'வீட்டு தொலைபேசி',
    addressLine1: 'முகவரி வரி 1',
    addressLine2: 'முகவரி வரி 2',
    email: 'மின்னஞ்சல்',
    vehicleOne: 'வாகன எண் 1',
    vehicleTwo: 'வாகன எண் 2',
    designation: 'பதவி',
    selectDesignation: 'பதவியைத் தேர்ந்தெடுக்கவும்',
    staffType: 'பணியாளர் வகை',
    selectStaffType: 'பணியாளர் வகையைத் தேர்ந்தெடுக்கவும்',
    staffStatus: 'பணியாளர் நிலை',
    selectStaffStatus: 'பணியாளர் நிலையைத் தேர்ந்தெடுக்கவும்',
    serviceStatus: 'சேவை நிலை',
    selectServiceStatus: 'சேவை நிலையைத் தேர்ந்தெடுக்கவும்',
    serviceGrade: 'சேவை தரம்',
    selectServiceGrade: 'சேவை தரத்தைத் தேர்ந்தெடுக்கவும்',
    subjectMedium: 'பாட மொழி',
    selectSubjectMedium: 'பாட மொழியைத் தேர்ந்தெடுக்கவும்',
    appointmentType: 'நியமன வகை',
    selectAppointmentType: 'நியமன வகையைத் தேர்ந்தெடுக்கவும்',
    appointmentSubject: 'நியமன பாடம்',
    selectAppointmentSubject: 'நியமன பாடத்தைத் தேர்ந்தெடுக்கவும்',
    firstAppDate: 'முதல் நியமன தேதி',
    startDateSchool: 'இந்த பாடசாலையில் தொடங்கிய தேதி',
    serviceGradeDate: 'சேவை தரம் அமலான தேதி',
    salaryIncreaseDate: 'சம்பள உயர்வு தேதி',
    section: 'பிரிவு',
    selectSection: 'பிரிவைத் தேர்ந்தெடுக்கவும்',
    sectionRole: 'பிரிவு பங்கு',
    selectSectionRole: 'பிரிவு பங்கைத் தேர்ந்தெடுக்கவும்',
    staffNo: 'பணியாளர் எண்',
    salaryNo: 'சம்பள எண்',
    mainTask: 'முக்கிய பணி',
    selectMainTask: 'முக்கிய பணியைத் தேர்ந்தெடுக்கவும்',
    mainTaskSection: 'முக்கிய பணி பிரிவு',
    mainTaskSubject: 'முக்கிய பணி பாடம்',
    secondTask: 'இரண்டாம் பணி',
    selectSecondTask: 'இரண்டாம் பணியைத் தேர்ந்தெடுக்கவும்',
    secondTaskSection: 'இரண்டாம் பணி பிரிவு',
    secondTaskSubject: 'இரண்டாம் பணி பாடம்',
    selectTaskSection: 'பணி பிரிவைத் தேர்ந்தெடுக்கவும்',
    selectTaskSubject: 'பணி பாடத்தைத் தேர்ந்தெடுக்கவும்',
    serviceStatusInstitute: 'நிறுவனம்',
    serviceStatusEffectiveDate: 'அமல்படும் தேதி',
    serviceStatusPeriod: 'காலம்',
    serviceStatusCurrent: 'தற்போதைய சேவை நிலை பதிவு',
    attachedProvince: 'இணைக்கப்பட்ட மாகாணம்',
    selectAttachedProvince: 'இணைக்கப்பட்ட மாகாணத்தைத் தேர்ந்தெடுக்கவும்',
    attachedZone: 'இணைக்கப்பட்ட வலயம்',
    selectAttachedZone: 'இணைக்கப்பட்ட வலயத்தைத் தேர்ந்தெடுக்கவும்',
    attachedSchool: 'இணைக்கப்பட்ட பாடசாலை',
    selectAttachedSchool: 'இணைக்கப்பட்ட பாடசாலையைத் தேர்ந்தெடுக்கவும்',
    customInstitute: 'வேறு நிறுவனம்',
    userLogin: 'பயனர் உள்நுழைவு',
    enableUserLogin: 'பயனர் உள்நுழைவை செயல்படுத்தவும்',
    userRole: 'பயனர் பங்கு',
    selectUserRole: 'பயனர் பங்கைத் தேர்ந்தெடுக்கவும்',
    loginUsername: 'உள்நுழைவு பெயர்',
    temporaryPassword: 'தற்காலிக கடவுச்சொல்',
    staffLoginCreatedTitle: 'உள்நுழைவு உருவாக்கப்பட்டது',
    staffLoginCreatedHelp: 'இந்த உள்நுழைவு தகவல்களை உடனே பயனருடன் பகிருங்கள். தற்காலிக கடவுச்சொல் மீண்டும் காட்டப்படாது.',
    photo: 'சுயவிவர புகைப்படம்',
    selectPhoto: 'புகைப்படத்தைத் தேர்ந்தெடுக்கவும்',
    photoPreview: 'முன்னோட்டம்',
    photoHelp: 'JPG, JPEG, PNG, WEBP கோப்புகள் மட்டும். அதிகபட்ச கோப்பு அளவு 2 MB. தெளிவான portrait புகைப்படம் சிறந்தது.',
  },
})

const title = computed(() => ({
  grades: text.value.gradesTitle,
  classes: text.value.classesTitle,
  staff: text.value.staffTitle,
  payments: text.value.paymentsTitle,
  reports: text.value.reportsTitle,
}[props.moduleKey] ?? text.value.moduleFallbackTitle))

const subtitle = computed(() => ({
  grades: text.value.gradesSubtitle,
  classes: text.value.classesSubtitle,
  staff: text.value.staffSubtitle,
  payments: text.value.paymentsSubtitle,
  reports: text.value.reportsSubtitle,
}[props.moduleKey] ?? text.value.moduleFallbackSubtitle))

const onUpdateGradeHead = (gradeRowId: number, stfId: number): void => {
  gradeEdits[gradeRowId] = stfId
}

const onUpdateSelectedGrade = (gradeId: number): void => {
  selectedGradeId.value = gradeId
}

const onUpdateClassYear = (year: number): void => {
  classYear.value = year
}

const onUpdateClassTeacher = (classRowId: number, stfId: number): void => {
  classTeacherEdits[classRowId] = stfId
}

const onUpdateClassApproved = (classRowId: number, value: number): void => {
  classApprovedEdits[classRowId] = value
}

const loadStaffOptions = async (): Promise<void> => {
  const { data } = await api.get<StaffOptionsResponse>('/staff/options')
  staffOptions.value = data.data
  staffSchools.value = Array.isArray(data.schools) ? data.schools : []
  loginRoles.value = Array.isArray(data.login_roles) ? data.login_roles : []
  staffGenders.value = Array.isArray(data.genders) ? data.genders : []
  staffCivilStatuses.value = Array.isArray(data.civil_statuses) ? data.civil_statuses : []
  staffEthnicGroups.value = Array.isArray(data.ethnic_groups) ? data.ethnic_groups : []
  staffReligions.value = Array.isArray(data.religions) ? data.religions : []
  educationLevels.value = Array.isArray(data.education_levels) ? data.education_levels : []
  professionalLevels.value = Array.isArray(data.professional_levels) ? data.professional_levels : []
  staffDesignations.value = Array.isArray(data.designations) ? data.designations : []
  serviceGrades.value = Array.isArray(data.service_grades) ? data.service_grades : []
  sections.value = Array.isArray(data.sections) ? data.sections : []
  sectionRoles.value = Array.isArray(data.section_roles) ? data.section_roles : []
  staffTypes.value = Array.isArray(data.staff_types) ? data.staff_types : []
  staffStatuses.value = Array.isArray(data.staff_statuses) ? data.staff_statuses : []
  serviceStatuses.value = Array.isArray(data.service_statuses) ? data.service_statuses : []
  provinces.value = Array.isArray(data.provinces) ? data.provinces : []
  zones.value = Array.isArray(data.zones) ? data.zones : []
  serviceStatusSchools.value = Array.isArray(data.all_schools) ? data.all_schools : []
  subjectMediums.value = Array.isArray(data.subject_mediums) ? data.subject_mediums : []
  appointmentTypes.value = Array.isArray(data.appointment_types) ? data.appointment_types : []
  appointmentSubjects.value = Array.isArray(data.appointment_subjects) ? data.appointment_subjects : []
  involvedTasks.value = Array.isArray(data.involved_tasks) ? data.involved_tasks : []
  subjects.value = Array.isArray(data.subjects) ? data.subjects : []
}

const resetStaffForm = (): void => {
  const currentSchoolContextCensusId = getSchoolContextCensusId()
  staffForm.title = ''
  staffForm.census_id = currentSchoolContextCensusId ?? 0
  staffForm.full_name = ''
  staffForm.name_with_ini = ''
  staffForm.nick_name = ''
  staffForm.nic_no = ''
  staffForm.dob = ''
  staffForm.gender_id = 0
  staffForm.civil_status_id = 0
  staffForm.ethnic_group_id = 0
  staffForm.religion_id = 0
  staffForm.phone_home = ''
  staffForm.phone_mobile1 = ''
  staffForm.phone_mobile2 = ''
  staffForm.address1 = ''
  staffForm.address2 = ''
  staffForm.email = ''
  staffForm.vehicle_no1 = ''
  staffForm.vehicle_no2 = ''
  staffForm.edu_q_id = 0
  staffForm.prof_q_id = 0
  staffForm.desig_id = 0
  staffForm.serv_grd_id = 0
  staffForm.sec_id = 0
  staffForm.sec_role_id = 0
  staffForm.stf_type_id = 0
  staffForm.stf_status_id = 0
  staffForm.service_status_id = 0
  staffForm.subj_med_id = 0
  staffForm.app_type_id = 0
  staffForm.app_subj_id = 0
  staffForm.first_app_dt = ''
  staffForm.start_dt_this_sch = ''
  staffForm.serv_grd_effective_dt = ''
  staffForm.sal_incr_dt = ''
  staffForm.stf_no = ''
  staffForm.salary_no = ''
  staffForm.main_task_id = 0
  staffForm.main_task_section_id = 0
  staffForm.main_task_subject_id = 0
  staffForm.second_task_id = 0
  staffForm.second_task_section_id = 0
  staffForm.second_task_subject_id = 0
  staffForm.service_status_institute = ''
  staffForm.service_status_province_id = 0
  staffForm.service_status_zone_id = 0
  staffForm.service_status_school_census_id = ''
  staffForm.service_status_custom_institute = ''
  staffForm.service_status_effective_date = ''
  staffForm.service_status_period = ''
  staffForm.service_status_is_current = true
  staffForm.create_user_login = false
  staffForm.login_role_id = 0
  staffForm.login_username = ''
  staffPhotoFile.value = null
  staffPhotoPreview.value = ''
}

const staffInputClass = (field: string): string[] => [
  'mt-1 w-full rounded-lg border px-3 py-2',
  staffFieldErrors.value[field] ? 'border-red-300' : 'border-slate-300',
]

const extractFieldErrors = (errorValue: unknown): ValidationErrors => {
  if (typeof errorValue === 'object' && errorValue !== null && 'response' in errorValue) {
    const response = (errorValue as { response?: { data?: { errors?: Record<string, string[]> } } }).response
    const errors = response?.data?.errors
    if (errors && typeof errors === 'object') {
      const result: ValidationErrors = {}
      for (const [key, value] of Object.entries(errors)) {
        if (Array.isArray(value) && value.length > 0) {
          result[key] = value[0]
        }
      }
      return result
    }
  }

  return {}
}

const extractApiMessage = (errorValue: unknown): string => {
  if (typeof errorValue === 'object' && errorValue !== null && 'response' in errorValue) {
    const response = (errorValue as { response?: { data?: { message?: string } } }).response
    if (typeof response?.data?.message === 'string' && response.data.message.trim() !== '') {
      return response.data.message
    }
  }

  return ''
}

const scrollToStaffError = async (): Promise<void> => {
  await nextTick()
  if (staffErrorRef.value) {
    staffErrorRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' })
    staffErrorRef.value.focus({ preventScroll: true })
  }
}

const onStaffPhotoChange = (event: Event): void => {
  const target = event.target as HTMLInputElement | null
  const file = target?.files?.[0] ?? null
  staffPhotoFile.value = file
  staffPhotoPreview.value = file ? URL.createObjectURL(file) : ''
}

const openAddStaffDialog = async (): Promise<void> => {
  staffError.value = ''
  staffFieldErrors.value = {}
  editStaffId.value = null
  resetStaffForm()
  await loadStaffOptions()
  showAddStaffDialog.value = true
}

const closeAddStaffDialog = (): void => {
  showAddStaffDialog.value = false
  editStaffId.value = null
  staffError.value = ''
  staffFieldErrors.value = {}
}

const closeStaffCredentialsDialog = (): void => {
  showStaffCredentialsDialog.value = false
  staffCredentials.username = ''
  staffCredentials.temporaryPassword = ''
}

const applyStaffDetailToForm = (detail: StaffDetailResponse['data']): void => {
  staffForm.title = String(detail.title ?? '')
  staffForm.census_id = Number(detail.census_id ?? 0)
  staffForm.full_name = String(detail.full_name ?? '')
  staffForm.name_with_ini = String(detail.name_with_ini ?? '')
  staffForm.nick_name = String(detail.nick_name ?? '')
  staffForm.nic_no = String(detail.nic_no ?? '')
  staffForm.dob = String(detail.dob ?? '')
  staffForm.gender_id = Number(detail.gender_id ?? 0)
  staffForm.civil_status_id = Number(detail.civil_status_id ?? 0)
  staffForm.ethnic_group_id = Number(detail.ethnic_group_id ?? 0)
  staffForm.religion_id = Number(detail.religion_id ?? 0)
  staffForm.phone_home = String(detail.phone_home ?? '')
  staffForm.phone_mobile1 = String(detail.phone_mobile1 ?? '')
  staffForm.phone_mobile2 = String(detail.phone_mobile2 ?? '')
  staffForm.address1 = String(detail.address1 ?? '')
  staffForm.address2 = String(detail.address2 ?? '')
  staffForm.email = String(detail.email ?? '')
  staffForm.vehicle_no1 = String(detail.vehicle_no1 ?? '')
  staffForm.vehicle_no2 = String(detail.vehicle_no2 ?? '')
  staffForm.edu_q_id = Number(detail.edu_q_id ?? 0)
  staffForm.prof_q_id = Number(detail.prof_q_id ?? 0)
  staffForm.desig_id = Number(detail.desig_id ?? 0)
  staffForm.serv_grd_id = Number(detail.serv_grd_id ?? 0)
  staffForm.sec_id = Number(detail.sec_id ?? 0)
  staffForm.sec_role_id = Number(detail.sec_role_id ?? 0)
  staffForm.stf_type_id = Number(detail.stf_type_id ?? 0)
  staffForm.stf_status_id = Number(detail.stf_status_id ?? 0)
  staffForm.service_status_id = Number(detail.service_status_id ?? 0)
  staffForm.subj_med_id = Number(detail.subj_med_id ?? 0)
  staffForm.app_type_id = Number(detail.app_type_id ?? 0)
  staffForm.app_subj_id = Number(detail.app_subj_id ?? 0)
  staffForm.first_app_dt = String(detail.first_app_dt ?? '')
  staffForm.start_dt_this_sch = String(detail.start_dt_this_sch ?? '')
  staffForm.serv_grd_effective_dt = String(detail.serv_grd_effective_dt ?? '')
  staffForm.sal_incr_dt = String(detail.sal_incr_dt ?? '')
  staffForm.stf_no = detail.stf_no ? String(detail.stf_no) : ''
  staffForm.salary_no = detail.salary_no ? String(detail.salary_no) : ''
  staffForm.main_task_id = Number(detail.main_task_id ?? 0)
  staffForm.main_task_section_id = Number(detail.main_task_section_id ?? 0)
  staffForm.main_task_subject_id = Number(detail.main_task_subject_id ?? 0)
  staffForm.second_task_id = Number(detail.second_task_id ?? 0)
  staffForm.second_task_section_id = Number(detail.second_task_section_id ?? 0)
  staffForm.second_task_subject_id = Number(detail.second_task_subject_id ?? 0)
  staffForm.service_status_custom_institute = String(detail.service_status_custom_institute ?? '')
  staffForm.service_status_effective_date = String(detail.service_status_effective_date ?? '')
  staffForm.service_status_period = String(detail.service_status_period ?? '')
  staffForm.service_status_is_current = Boolean(detail.service_status_is_current ?? true)
  staffForm.create_user_login = Boolean(detail.create_user_login ?? false)
  staffForm.login_role_id = Number(detail.login_role_id ?? 0)
  staffForm.login_username = String(detail.login_username ?? '')
  staffPhotoFile.value = null
  staffPhotoPreview.value = String(detail.photo_url ?? '')
}

const openEditStaffDialog = async (row: StaffRow): Promise<void> => {
  loadingEditStaffId.value = row.stf_id
  staffError.value = ''
  staffFieldErrors.value = {}
  const editDebugId = `staff-edit-${row.stf_id}-${Date.now()}`

  console.info('[Staff Edit] started', {
    editDebugId,
    stfId: row.stf_id,
    censusId: row.census_id ?? null,
  })

  try {
    console.info('[Staff Edit] loading options', { editDebugId })
    await loadStaffOptions()
    console.info('[Staff Edit] loading detail', { editDebugId, stfId: row.stf_id })
    const { data } = await api.get<StaffDetailResponse>(`/staff/${row.stf_id}`)
    resetStaffForm()
    applyStaffDetailToForm(data.data)
    editStaffId.value = row.stf_id
    showAddStaffDialog.value = true
    console.info('[Staff Edit] dialog ready', {
      editDebugId,
      stfId: row.stf_id,
      hasPhotoPreview: Boolean(staffPhotoPreview.value),
    })
  } catch (errorValue) {
    console.error('[Staff Edit] failed', {
      editDebugId,
      stfId: row.stf_id,
      error: errorValue,
    })
    error.value = extractApiMessage(errorValue) || text.value.moduleLoadError
  } finally {
    loadingEditStaffId.value = null
  }
}

const hydrateGradeEdits = (): void => {
  Object.keys(gradeEdits).forEach((k) => delete gradeEdits[Number(k)])
  for (const g of grades.value) {
    if (g.sch_grd_id) {
      gradeEdits[g.sch_grd_id] = g.stf_id ?? 0
    }
  }
}

const hydrateClassEdits = (): void => {
  Object.keys(classTeacherEdits).forEach((k) => delete classTeacherEdits[Number(k)])
  Object.keys(classApprovedEdits).forEach((k) => delete classApprovedEdits[Number(k)])

  for (const c of classes.value) {
    if (c.sch_grd_cls_id) {
      classTeacherEdits[c.sch_grd_cls_id] = c.stf_id ?? 0
      classApprovedEdits[c.sch_grd_cls_id] = c.approved_std_count ?? 0
    }
  }
}

const loadGrades = async (): Promise<void> => {
  const { data } = await api.get<{ year?: number; data: Grade[] }>('/grades')
  grades.value = data.data
  latestGradeYear.value = typeof data.year === 'number' ? data.year : null
  hydrateGradeEdits()
}

const loadClassesView = async (): Promise<void> => {
  const params: Record<string, number> = { year: classYear.value }
  if (selectedGradeId.value) params.grade_id = selectedGradeId.value
  const { data } = await api.get<{ year?: number; data: ClassItem[] }>('/classes', { params })
  classes.value = data.data
  latestClassYear.value = typeof data.year === 'number' ? data.year : null
  hydrateClassEdits()
}

const loadClassCreateOptions = async (): Promise<void> => {
  const params: Record<string, number> = { year: classYear.value }
  if (createClassGradeId.value) {
    params.grade_id = createClassGradeId.value
  }

  const { data } = await api.get<{ grades: ClassGradeOption[]; classes: ClassOption[] }>('/classes/options', { params })
  classCreateGradeOptions.value = data.grades
  createClassOptions.value = data.classes

  if (!classCreateGradeOptions.value.some((option) => option.grade_id === selectedGradeId.value)) {
    selectedGradeId.value = 0
  }

  if (!classCreateGradeOptions.value.some((option) => option.grade_id === createClassGradeId.value)) {
    createClassGradeId.value = 0
    createClassId.value = 0
    createClassOptions.value = []
    return
  }

  if (!createClassOptions.value.some((option) => option.class_id === createClassId.value)) {
    createClassId.value = 0
  }
}

const initializeYear = async (): Promise<void> => {
  message.value = ''
  error.value = ''

  try {
    const { data } = await api.post('/grades/initialize-year', { year: targetYear.value })
    message.value = data?.message ?? text.value.yearInitialized
    await Promise.all([loadGrades(), loadClassesView()])
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.initializeYearError
  }
}

const saveGrade = async (gradeRowId: number): Promise<void> => {
  message.value = ''
  error.value = ''

  try {
    const stfId = gradeEdits[gradeRowId] && gradeEdits[gradeRowId] > 0 ? gradeEdits[gradeRowId] : null
    await api.put(`/grades/${gradeRowId}`, { stf_id: stfId })
    message.value = text.value.gradeRowUpdated
    await loadGrades()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.updateGradeError
  }
}

const deleteGrade = async (gradeRowId: number): Promise<void> => {
  if (!window.confirm(text.value.deleteGradeConfirm)) {
    return
  }

  message.value = ''
  error.value = ''

  try {
    await api.delete(`/grades/${gradeRowId}`)
    message.value = text.value.gradeRowDeleted
    await Promise.all([loadGrades(), loadClassesView()])
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.deleteGradeError
  }
}

const saveClass = async (classRowId: number): Promise<void> => {
  message.value = ''
  error.value = ''

  try {
    const stfId = classTeacherEdits[classRowId] && classTeacherEdits[classRowId] > 0 ? classTeacherEdits[classRowId] : null
    await api.put(`/classes/${classRowId}`, {
      stf_id: stfId,
      approved_std_count: classApprovedEdits[classRowId],
    })
    message.value = text.value.classRowUpdated
    await loadClassesView()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.updateClassError
  }
}

const addClass = async (): Promise<void> => {
  message.value = ''
  error.value = ''

  try {
    const { data } = await api.post('/classes', {
      year: classYear.value,
      grade_id: createClassGradeId.value,
      class_id: createClassId.value,
      approved_std_count: createApprovedCount.value,
    })
    message.value = data?.message ?? text.value.classAdded
    createClassId.value = 0
    await Promise.all([loadClassesView(), loadClassCreateOptions()])
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.addClassError
  }
}

const quickAddClass = async (classRowId: number): Promise<void> => {
  message.value = ''
  error.value = ''

  const sourceRow = classes.value.find((item) => item.sch_grd_cls_id === classRowId)
  if (!sourceRow || !sourceRow.grade_id || !sourceRow.year) {
    error.value = text.value.addClassError
    return
  }

  try {
    const { data } = await api.get<{ classes: ClassOption[] }>('/classes/options', {
      params: {
        year: sourceRow.year,
        grade_id: sourceRow.grade_id,
      },
    })

    const nextClass = data.classes[0]
    if (!nextClass) {
      error.value = text.value.addNextClassError
      return
    }

    const response = await api.post('/classes', {
      year: sourceRow.year,
      grade_id: sourceRow.grade_id,
      class_id: nextClass.class_id,
      approved_std_count: sourceRow.approved_std_count ?? 35,
    })

    message.value = response.data?.message ?? text.value.classAdded
    await Promise.all([loadClassesView(), loadClassCreateOptions()])
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.addClassError
  }
}

const deleteClass = async (classRowId: number): Promise<void> => {
  if (!window.confirm(text.value.deleteClassConfirm)) {
    return
  }

  message.value = ''
  error.value = ''

  try {
    const { data } = await api.delete(`/classes/${classRowId}`)
    message.value = data?.message ?? text.value.classDeleted
    await Promise.all([loadClassesView(), loadClassCreateOptions()])
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? text.value.deleteClassError
  }
}

const loadGradeReport = async (): Promise<void> => {
  message.value = ''
  error.value = ''
  const params = reportYear.value ? { year: reportYear.value } : {}
  const { data } = await api.get<{ data: GradeReportRow[] }>('/grades/report', { params })
  gradeReport.value = data.data
}

const loadClassReport = async (): Promise<void> => {
  message.value = ''
  error.value = ''
  const params = reportYear.value ? { year: reportYear.value } : {}
  const { data } = await api.get<{ data: ClassReportRow[] }>('/classes/report', { params })
  classReport.value = data.data
}

const loadStaff = async (page = 1): Promise<void> => {
  const params: Record<string, string | number> = {
    q: staffSearch.value,
    page,
    per_page: staffMeta.per_page,
  }

  if (isAdmin.value && selectedStaffSchoolCensusId.value > 0) {
    params.school_census_id = selectedStaffSchoolCensusId.value
  }

  const { data } = await api.get<{ data: StaffRow[]; meta: StaffMeta }>('/staff', { params })
  staffRows.value = data.data
  Object.assign(staffMeta, data.meta)
}

const updateStaffReportFilters = (patch: Partial<StaffReportFilters>): void => {
  Object.assign(staffReportFilters, patch)
}

const resetStaffReportFilters = async (): Promise<void> => {
  Object.assign(staffReportFilters, createDefaultStaffReportFilters())
  await loadStaffReport()
}

const loadStaffReport = async (): Promise<void> => {
  message.value = ''
  error.value = ''
  const params: Record<string, string | number> = {}

  Object.entries(staffReportFilters).forEach(([key, value]) => {
    if (typeof value === 'string') {
      if (value.trim() !== '') {
        params[key] = value.trim()
      }
      return
    }

    if (Number(value) > 0) {
      params[key] = Number(value)
    }
  })

  const { data } = await api.get<{ data: StaffRow[] }>('/staff/report', { params })
  staffReportRows.value = Array.isArray(data.data) ? data.data : []
}

const saveStaff = async (): Promise<void> => {
  staffError.value = ''
  staffFieldErrors.value = {}
  message.value = ''

  if (isAdmin.value && Number(staffForm.census_id) <= 0) {
    staffError.value = text.value.selectSchool
    staffFieldErrors.value = { census_id: text.value.selectSchool }
    await scrollToStaffError()
    return
  }

  savingStaff.value = true
  const submitDebugId = `staff-save-${Date.now()}`
  console.info('[Staff Save] started', {
    submitDebugId,
    hasPhoto: Boolean(staffPhotoFile.value),
    censusId: isAdmin.value ? Number(staffForm.census_id) : null,
    nicNo: staffForm.nic_no.trim() || null,
  })

  const payload: StaffCreatePayload = {
    title: staffForm.title.trim(),
    full_name: staffForm.full_name.trim(),
    name_with_ini: staffForm.name_with_ini.trim(),
    gender_id: Number(staffForm.gender_id),
    desig_id: Number(staffForm.desig_id),
  }

  if (isAdmin.value && Number(staffForm.census_id) > 0) payload.census_id = String(staffForm.census_id)
  if (staffForm.nick_name.trim() !== '') payload.nick_name = staffForm.nick_name.trim()
  if (staffForm.nic_no.trim() !== '') payload.nic_no = staffForm.nic_no.trim()
  if (staffForm.dob.trim() !== '') payload.dob = staffForm.dob.trim()
  if (Number(staffForm.civil_status_id) > 0) payload.civil_status_id = Number(staffForm.civil_status_id)
  if (Number(staffForm.ethnic_group_id) > 0) payload.ethnic_group_id = Number(staffForm.ethnic_group_id)
  if (Number(staffForm.religion_id) > 0) payload.religion_id = Number(staffForm.religion_id)
  if (staffForm.phone_home.trim() !== '') payload.phone_home = staffForm.phone_home.trim()
  if (staffForm.phone_mobile1.trim() !== '') payload.phone_mobile1 = staffForm.phone_mobile1.trim()
  if (staffForm.phone_mobile2.trim() !== '') payload.phone_mobile2 = staffForm.phone_mobile2.trim()
  if (staffForm.address1.trim() !== '') payload.address1 = staffForm.address1.trim()
  if (staffForm.address2.trim() !== '') payload.address2 = staffForm.address2.trim()
  if (staffForm.email.trim() !== '') payload.email = staffForm.email.trim()
  if (staffForm.vehicle_no1.trim() !== '') payload.vehicle_no1 = staffForm.vehicle_no1.trim()
  if (staffForm.vehicle_no2.trim() !== '') payload.vehicle_no2 = staffForm.vehicle_no2.trim()
  if (Number(staffForm.edu_q_id) > 0) payload.edu_q_id = Number(staffForm.edu_q_id)
  if (Number(staffForm.prof_q_id) > 0) payload.prof_q_id = Number(staffForm.prof_q_id)
  if (Number(staffForm.stf_type_id) > 0) payload.stf_type_id = Number(staffForm.stf_type_id)
  if (Number(staffForm.stf_status_id) > 0) payload.stf_status_id = Number(staffForm.stf_status_id)
  if (Number(staffForm.service_status_id) > 0) payload.service_status_id = Number(staffForm.service_status_id)
  if (Number(staffForm.serv_grd_id) > 0) payload.serv_grd_id = Number(staffForm.serv_grd_id)
  if (Number(staffForm.sec_id) > 0) payload.sec_id = Number(staffForm.sec_id)
  if (Number(staffForm.sec_role_id) > 0) payload.sec_role_id = Number(staffForm.sec_role_id)
  if (Number(staffForm.subj_med_id) > 0) payload.subj_med_id = Number(staffForm.subj_med_id)
  if (Number(staffForm.app_type_id) > 0) payload.app_type_id = Number(staffForm.app_type_id)
  if (Number(staffForm.app_subj_id) > 0) payload.app_subj_id = Number(staffForm.app_subj_id)
  if (staffForm.first_app_dt.trim() !== '') payload.first_app_dt = staffForm.first_app_dt.trim()
  if (staffForm.start_dt_this_sch.trim() !== '') payload.start_dt_this_sch = staffForm.start_dt_this_sch.trim()
  if (staffForm.serv_grd_effective_dt.trim() !== '') payload.serv_grd_effective_dt = staffForm.serv_grd_effective_dt.trim()
  if (staffForm.sal_incr_dt.trim() !== '') payload.sal_incr_dt = staffForm.sal_incr_dt.trim()
  if (Number(staffForm.stf_no) > 0) payload.stf_no = Number(staffForm.stf_no)
  if (Number(staffForm.salary_no) > 0) payload.salary_no = Number(staffForm.salary_no)
  if (Number(staffForm.main_task_id) > 0) payload.main_task_id = Number(staffForm.main_task_id)
  if (Number(staffForm.main_task_section_id) > 0) payload.main_task_section_id = Number(staffForm.main_task_section_id)
  if (Number(staffForm.main_task_subject_id) > 0) payload.main_task_subject_id = Number(staffForm.main_task_subject_id)
  if (Number(staffForm.second_task_id) > 0) payload.second_task_id = Number(staffForm.second_task_id)
  if (Number(staffForm.second_task_section_id) > 0) payload.second_task_section_id = Number(staffForm.second_task_section_id)
  if (Number(staffForm.second_task_subject_id) > 0) payload.second_task_subject_id = Number(staffForm.second_task_subject_id)
  if (staffForm.service_status_institute.trim() !== '') payload.service_status_institute = staffForm.service_status_institute.trim()
  if (Number(staffForm.service_status_province_id) > 0) payload.service_status_province_id = Number(staffForm.service_status_province_id)
  if (Number(staffForm.service_status_zone_id) > 0) payload.service_status_zone_id = Number(staffForm.service_status_zone_id)
  if (staffForm.service_status_school_census_id.trim() !== '') payload.service_status_school_census_id = staffForm.service_status_school_census_id.trim()
  if (staffForm.service_status_custom_institute.trim() !== '') payload.service_status_custom_institute = staffForm.service_status_custom_institute.trim()
  if (staffForm.service_status_effective_date.trim() !== '') payload.service_status_effective_date = staffForm.service_status_effective_date.trim()
  if (staffForm.service_status_period.trim() !== '') payload.service_status_period = staffForm.service_status_period.trim()
  payload.service_status_is_current = Boolean(staffForm.service_status_is_current)
  payload.create_user_login = Boolean(staffForm.create_user_login)
  if (staffForm.create_user_login && Number(staffForm.login_role_id) > 0) {
    payload.login_role_id = Number(staffForm.login_role_id)
  }

  try {
    console.info('[Staff Save] preparing request body', {
      submitDebugId,
      fieldCount: Object.keys(payload).length,
    })
    const requestBody = staffPhotoFile.value
      ? (() => {
          const formData = new FormData()
          Object.entries(payload).forEach(([key, value]) => {
            formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
          })
          formData.append('profile_photo', staffPhotoFile.value as File)
          return formData
        })()
      : payload

    console.info('[Staff Save] sending request', { submitDebugId })
    const requestConfig = staffPhotoFile.value ? {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    } : undefined
    const { data } = isStaffEditMode.value && editStaffId.value !== null
      ? await api.put(`/staff/${editStaffId.value}`, requestBody, requestConfig)
      : await api.post('/staff', requestBody, requestConfig) as { data: StaffSaveResponse }
    console.info('[Staff Save] request completed', {
      submitDebugId,
      stfId: data?.data?.stf_id ?? null,
    })
    message.value = typeof data?.message === 'string' && data.message.trim() !== ''
      ? data.message
      : (isStaffEditMode.value ? text.value.staffUpdated : text.value.staffAdded)
    if (isAdmin.value && Number(staffForm.census_id) > 0 && !isStaffEditMode.value) {
      setSchoolContextCensusId(Number(staffForm.census_id))
    }
    closeAddStaffDialog()
    resetStaffForm()
    savingStaff.value = false

    const loginAccount = data?.data?.login_account
    if (loginAccount?.created && loginAccount.username && loginAccount.temporary_password) {
      staffCredentials.username = loginAccount.username
      staffCredentials.temporaryPassword = loginAccount.temporary_password
      showStaffCredentialsDialog.value = true
    }

    console.info('[Staff Save] refreshing staff list', { submitDebugId })
    void loadStaff(staffMeta.current_page)
      .then(() => {
        console.info('[Staff Save] staff list refreshed', { submitDebugId })
      })
      .catch((reloadError) => {
        console.error('[Staff Save] staff list refresh failed', {
          submitDebugId,
          error: reloadError,
        })
        error.value = extractApiMessage(reloadError) || text.value.moduleLoadError
      })
  } catch (errorValue) {
    console.error('[Staff Save] request failed', {
      submitDebugId,
      error: errorValue,
    })
    staffFieldErrors.value = extractFieldErrors(errorValue)
    staffError.value = extractApiMessage(errorValue) || text.value.moduleLoadError
    await scrollToStaffError()
  } finally {
    savingStaff.value = false
  }
}

const reloadCurrentModuleData = async (): Promise<void> => {
  loading.value = true
  error.value = ''

  try {
    if (canManage.value && (isGrades.value || isClasses.value || isStaff.value)) {
      await loadStaffOptions()
    }

    if (isGrades.value) {
      await loadGrades()
      if (activeTab.value === 'reports') await loadGradeReport()
    }

    if (isClasses.value) {
      if (activeTab.value === 'view') {
        await Promise.all([loadClassesView(), loadClassCreateOptions()])
      } else {
        await loadClassReport()
      }
    }

    if (isStaff.value) {
      if (activeTab.value === 'view') {
        await loadStaff(1)
      } else {
        await loadStaffReport()
      }
    }
  } catch {
    error.value = text.value.moduleLoadError
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.moduleKey, activeTab.value, selectedGradeId.value, classYear.value, createClassGradeId.value],
  async () => {
    await reloadCurrentModuleData()
  },
  { immediate: true },
)

watch(
  () => ui.language,
  async () => {
    await reloadCurrentModuleData()
  },
)

watch(
  () => staffForm.app_type_id,
  () => {
    if (!filteredAppointmentSubjects.value.some((row) => row.id === Number(staffForm.app_subj_id))) {
      staffForm.app_subj_id = 0
    }
  },
)

watch(
  () => staffForm.create_user_login,
  (enabled) => {
    if (!enabled) {
      staffForm.login_role_id = 0
    }
  },
)

watch(
  () => staffForm.main_task_section_id,
  () => {
    if (!mainTaskSubjects.value.some((row) => row.id === Number(staffForm.main_task_subject_id))) {
      staffForm.main_task_subject_id = 0
    }
  },
)

watch(
  () => staffForm.second_task_section_id,
  () => {
    if (!secondTaskSubjects.value.some((row) => row.id === Number(staffForm.second_task_subject_id))) {
      staffForm.second_task_subject_id = 0
    }
  },
)

watch(() => props.moduleKey, () => {
  activeTab.value = 'view'
  reportYear.value = 0
  Object.assign(staffReportFilters, createDefaultStaffReportFilters())
  staffReportRows.value = []
  selectedGradeId.value = 0
  classYear.value = new Date().getFullYear()
  createClassGradeId.value = 0
  createClassId.value = 0
  createApprovedCount.value = 35
  createClassOptions.value = []
  classCreateGradeOptions.value = []
  showAddStaffDialog.value = false
  editStaffId.value = null
  message.value = ''
  error.value = ''
})

watch(() => activeTab.value, () => {
  message.value = ''
  error.value = ''
})
</script>

