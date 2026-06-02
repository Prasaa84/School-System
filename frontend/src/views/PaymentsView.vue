<template>
  <div class="space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h1 class="font-display text-2xl font-bold text-slate-900">{{ text.title }}</h1>
      <p class="mt-2 text-sm text-slate-600">{{ pageSubtitle }}</p>
    </header>

    <p v-if="classTeacherAssignmentWarning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      {{ classTeacherAssignmentWarning }}
    </p>

    <div v-if="showPaymentModeToggle" class="flex gap-2">
      <button
        class="rounded-xl px-4 py-2 text-sm font-semibold transition"
        :class="isPaymentEntryMode ? 'bg-cyan-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
        @click="switchToPaymentEntry"
      >
        {{ text.paymentEntry }}
      </button>
      <button
        class="rounded-xl px-4 py-2 text-sm font-semibold transition"
        :class="isPaymentReportMode ? 'bg-cyan-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
        @click="switchToPaymentReport"
      >
        {{ text.paymentReport }}
      </button>
    </div>

    <section v-if="!isFeeTypesRoute && !isStudent && isPaymentEntryMode" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="grid gap-4 md:grid-cols-3">
        <label v-if="isAdmin" class="text-sm text-slate-700 md:col-span-3">
          {{ text.school }}
          <select v-model.number="selectedSchoolCensusId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onSchoolChange">
            <option :value="0">{{ text.selectSchool }}</option>
            <option v-for="row in schools" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
        </label>

        <label class="text-sm text-slate-700 md:col-span-2">
          {{ text.studentAdmissionNo }}
          <input
            v-model="studentIndexNo"
            type="text"
            maxlength="5"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2"
            :placeholder="text.studentAdmissionPlaceholder"
            :disabled="lookupDisabled"
            @keyup.enter="lookupStudent"
          />
        </label>

        <div class="flex items-end">
          <button
            class="w-full rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="lookupDisabled || lookingUpStudent"
            @click="lookupStudent"
          >
            {{ lookingUpStudent ? text.searching : text.searchStudent }}
          </button>
        </div>
      </div>

      <p v-if="!student && message" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</p>
      <p v-if="!student && errorMessage" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>
    </section>

    <section v-if="!isFeeTypesRoute && student && isPaymentEntryMode" class="space-y-6">
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p v-if="message" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</p>
        <p v-if="errorMessage" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>

        <div class="mb-4 flex items-center justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.paymentHistory }}</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-slate-900">{{ text.paymentHistoryTitle }}</h2>
          </div>
          <div class="flex items-center gap-3">
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ paymentRows.length }}</span>
            <button v-if="canManagePayments" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" @click="openAddPaymentDialog">
              {{ text.addPayment }}
            </button>
          </div>
        </div>

        <div class="overflow-auto rounded-xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.numberLabel }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.admissionLabel }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.nameLabel }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.invoiceNo }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.year }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.annualFee }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.memberFee }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.total }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.paidDate }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-if="paymentRows.length === 0">
                <td colspan="9" class="px-3 py-6 text-center text-slate-500">{{ text.noPayments }}</td>
              </tr>
              <tr v-for="(row, index) in paymentRows" :key="`payment-row-${row.id}`" class="hover:bg-slate-50">
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ index + 1 }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ student.index_no }}</td>
                <td class="px-3 py-2 text-slate-700 max-w-[220px] truncate" :title="student.name_with_initials || student.fullname">{{ student.name_with_initials || student.fullname }}</td>
                <td class="px-3 py-2 font-medium text-slate-900 whitespace-nowrap">{{ row.invoice_no }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.year }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ formatAmount(row.annual_fee) }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">
                  {{ row.include_member_fee ? formatAmount(row.member_fee) : text.notIncluded }}
                </td>
                <td class="px-3 py-2 font-semibold text-slate-900 whitespace-nowrap">{{ formatAmount(row.total) }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ formatDate(row.paid_date) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </section>

    <section v-if="!isFeeTypesRoute && canViewPaymentReport && isPaymentReportMode" class="space-y-6">
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.paymentReportSection }}</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-slate-900">{{ text.paymentReportTitle }}</h2>
          </div>
          <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ reportSummary.payment_count }}</span>
        </div>

        <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-5 xl:grid-cols-6">
          <label v-if="isAdmin" class="text-sm text-slate-700 lg:col-span-5">
            {{ text.school }}
            <select v-model.number="selectedSchoolCensusId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="onSchoolChange">
              <option :value="0">{{ text.selectSchool }}</option>
              <option v-for="row in schools" :key="`payment-report-school-${row.id}`" :value="row.id">{{ row.label }}</option>
            </select>
          </label>

          <label class="min-w-0 text-[11px] text-slate-700">
            {{ text.year }}
            <select
              v-model.number="reportFilters.year"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]"
              @change="onReportYearChange"
            >
              <option :value="0">{{ text.allYears }}</option>
              <option v-for="year in yearOptions" :key="`payment-report-year-${year}`" :value="year">{{ year }}</option>
            </select>
          </label>

          <label v-if="!isClassTeacher" class="text-sm text-slate-700">
            {{ text.grade }}
            <select v-model.number="reportFilters.grade_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="reportFilters.year <= 0" @change="onReportGradeChange">
              <option :value="0">{{ text.allGrades }}</option>
              <option v-for="row in reportGrades" :key="`payment-report-grade-${row.grade_id}`" :value="row.grade_id">{{ row.grade }}</option>
            </select>
          </label>

          <label v-if="!isClassTeacher" class="text-sm text-slate-700">
            {{ text.class }}
            <select v-model.number="reportFilters.class_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="reportFilters.year <= 0 || reportFilters.grade_id <= 0">
              <option :value="0">{{ text.allClasses }}</option>
              <option v-for="row in reportClasses" :key="`payment-report-class-${row.class_id}`" :value="row.class_id">{{ row.class }}</option>
            </select>
          </label>

          <label class="min-w-0 text-[11px] text-slate-700">
            {{ text.admissionLabel }}
            <input v-model="reportFilters.admission_no" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" />
          </label>

          <label class="min-w-0 text-[11px] text-slate-700">
            {{ text.invoiceNo }}
            <input v-model="reportFilters.invoice_no" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]" />
          </label>

          <label class="min-w-0 text-[11px] text-slate-700">
            {{ text.paymentStatus }}
            <select v-model="reportFilters.payment_status" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-[11px]">
              <option value="all">{{ text.paymentStatusAll }}</option>
              <option value="paid">{{ text.paymentStatusPaid }}</option>
              <option value="not_paid">{{ text.paymentStatusNotPaid }}</option>
            </select>
          </label>

          <div class="flex items-end">
            <button class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="loadingReport" @click="resetPaymentReport">
              {{ text.reset }}
            </button>
          </div>

          <div class="flex items-end">
            <button class="w-full rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingReport" @click="exportPaymentReportExcel">
              {{ text.exportExcel }}
            </button>
          </div>

          <div class="flex items-end">
            <button class="w-full rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingReport" @click="searchPaymentReport">
              {{ loadingReport ? text.searching : text.refresh }}
            </button>
          </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-3">
          <div v-if="isClassTeacher" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.scope }}</p>
            <p class="mt-2 text-sm font-semibold text-slate-900">{{ reportScopeLabel }}</p>
          </div>
          <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.totalPayments }}</p>
            <p class="mt-2 text-sm font-semibold text-slate-900">{{ reportSummary.payment_count }}</p>
          </div>
          <div class="rounded-xl border border-slate-200 bg-emerald-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-600">{{ text.total }}</p>
            <p class="mt-2 text-sm font-semibold text-emerald-900">{{ formatAmount(reportSummary.total_amount) }}</p>
          </div>
        </div>

        <p v-if="message" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</p>
        <p v-if="errorMessage" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>

        <div class="mt-4 overflow-auto rounded-xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.numberLabel }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.admissionLabel }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600">{{ text.nameLabel }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.grade }}/{{ text.class }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.invoiceNo }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.year }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.status }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.annualFee }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.memberFee }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.total }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.paidDate }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-if="!loadingReport && reportRows.length === 0">
                <td colspan="11" class="px-3 py-6 text-center text-slate-500">{{ text.noReportPayments }}</td>
              </tr>
              <tr v-for="(row, index) in reportRows" :key="`payment-report-row-${row.id}`" class="hover:bg-slate-50">
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ index + 1 }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.admission_no }}</td>
                <td class="px-3 py-2 text-slate-700 max-w-[220px] truncate" :title="row.name_with_initials || row.fullname">{{ row.name_with_initials || row.fullname }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.grade_class || '-' }}</td>
                <td class="px-3 py-2 font-medium text-slate-900 whitespace-nowrap">{{ row.invoice_no }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.year }}</td>
                <td class="px-3 py-2 whitespace-nowrap">
                  <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                    :class="row.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                  >
                    {{ row.payment_status === 'paid' ? text.paymentStatusPaid : text.paymentStatusNotPaid }}
                  </span>
                </td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ formatAmount(row.annual_fee) }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.payment_status === 'paid' && row.include_member_fee ? formatAmount(row.member_fee) : text.notIncluded }}</td>
                <td class="px-3 py-2 font-semibold text-slate-900 whitespace-nowrap">{{ row.payment_status === 'paid' ? formatAmount(row.total) : '-' }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.payment_status === 'paid' ? formatDate(row.paid_date) : '-' }}</td>
              </tr>
              <tr v-if="!loadingReport && reportRows.length > 0" class="bg-slate-50">
                <td colspan="8" class="px-3 py-3"></td>
                <td class="px-3 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">{{ text.totalFee }}</td>
                <td class="px-3 py-3 font-semibold text-slate-900 whitespace-nowrap">{{ formatAmount(reportSummary.total_amount) }}</td>
                <td class="px-3 py-3"></td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </section>

    <section v-if="isFeeTypesRoute" class="space-y-6">
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p v-if="message" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</p>
        <p v-if="errorMessage" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</p>

        <div class="mb-4 flex items-center justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ text.feeTypesSection }}</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-slate-900">{{ text.feeTypesTitle }}</h2>
          </div>
          <button
            v-if="canManageFeeTypes"
            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
            @click="openFeeTypeDialog()"
          >
            {{ text.addFeeType }}
          </button>
        </div>

        <div class="overflow-auto rounded-xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.numberLabel }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.year }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.annualFee }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.memberFee }}</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-600 whitespace-nowrap">{{ text.dateAdded }}</th>
                <th v-if="canManageFeeTypes" class="px-3 py-2 text-right font-semibold text-slate-600 whitespace-nowrap">{{ text.actions }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-if="feeTypeRows.length === 0">
                <td :colspan="canManageFeeTypes ? 6 : 5" class="px-3 py-6 text-center text-slate-500">{{ text.noFeeTypes }}</td>
              </tr>
              <tr v-for="(row, index) in feeTypeRows" :key="`fee-type-${row.id}`" class="hover:bg-slate-50">
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ index + 1 }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ row.year }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ formatAmount(row.annual_fee) }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ formatAmount(row.member_fee) }}</td>
                <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ formatDate(row.date_added) }}</td>
                <td v-if="canManageFeeTypes" class="px-3 py-2 text-right whitespace-nowrap">
                  <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="openFeeTypeDialog(row)">
                    {{ text.edit }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </section>

    <div v-if="showAddPaymentDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeAddPaymentDialog">
      <section class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-display text-xl font-bold text-slate-900">{{ text.addPaymentDialogTitle }}</h2>
          <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeAddPaymentDialog">{{ text.close }}</button>
        </div>

        <p v-if="paymentDialogError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {{ paymentDialogError }}
        </p>

        <div class="grid gap-4 md:grid-cols-2">
          <label class="text-sm text-slate-700">
            {{ text.studentAdmissionNo }}
            <input :value="student?.index_no ?? ''" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
          </label>

          <label class="text-sm text-slate-700">
            {{ text.studentName }}
            <input :value="student ? (student.name_with_initials || student.fullname) : ''" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
          </label>

          <label class="text-sm text-slate-700">
            {{ text.invoiceNo }}
            <input v-model="paymentForm.invoice_no" type="text" maxlength="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
          </label>

          <label class="text-sm text-slate-700">
            {{ text.year }}
            <select v-model.number="paymentForm.year" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
              <option :value="0">{{ text.selectYear }}</option>
              <option v-for="year in yearOptions" :key="`payment-year-dialog-${year}`" :value="year">{{ year }}</option>
            </select>
          </label>

          <label class="text-sm text-slate-700">
            {{ text.annualFee }}
            <input :value="formatAmount(feeDetails?.annual_fee ?? 0)" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
          </label>

          <label class="text-sm text-slate-700">
            {{ text.memberFee }}
            <input :value="formatAmount(feeDetails?.member_fee ?? 0)" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" readonly />
          </label>

          <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 md:col-span-2">
            <input v-model="paymentForm.include_member_fee" type="checkbox" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" />
            <span>{{ text.includeMemberFee }}</span>
          </label>

          <label class="text-sm text-slate-700 md:col-span-2">
            {{ text.total }}
            <input :value="formatAmount(calculatedTotal)" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-900" readonly />
          </label>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeAddPaymentDialog">
            {{ text.cancel }}
          </button>
          <button
            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="savingPayment || !canSubmitPayment"
            @click="savePayment"
          >
            {{ savingPayment ? text.saving : text.addPayment }}
          </button>
        </div>
      </section>
    </div>

    <div v-if="showFeeTypeDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeFeeTypeDialog">
      <section class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-display text-xl font-bold text-slate-900">{{ feeTypeDialogTitle }}</h2>
          <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeFeeTypeDialog">{{ text.close }}</button>
        </div>

        <p v-if="feeTypeDialogError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          {{ feeTypeDialogError }}
        </p>

        <div class="grid gap-4 md:grid-cols-2">
          <label class="text-sm text-slate-700">
            {{ text.year }}
            <select v-model.number="feeTypeForm.year" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2">
              <option :value="0">{{ text.selectYear }}</option>
              <option v-for="year in feeTypeYearOptions" :key="`fee-type-year-${year}`" :value="year">{{ year }}</option>
            </select>
          </label>

          <label class="text-sm text-slate-700">
            {{ text.annualFee }}
            <input v-model.number="feeTypeForm.annual_fee" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
          </label>

          <label class="text-sm text-slate-700 md:col-span-2">
            {{ text.memberFee }}
            <input v-model.number="feeTypeForm.member_fee" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-cyan-500 focus:ring-2" />
          </label>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeFeeTypeDialog">
            {{ text.cancel }}
          </button>
          <button
            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="savingFeeType || !canSubmitFeeType"
            @click="saveFeeType"
          >
            {{ savingFeeType ? text.saving : feeTypeSubmitLabel }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '../services/api'
import { getSchoolContextCensusId, getToken, getUser, setAuthSession, setSchoolContextCensusId, type AuthUser } from '../services/auth'
import { useUiStore } from '../stores/ui'

interface OptionRow {
  id: number
  label: string
}

interface PaymentStudent {
  std_id: number
  index_no: string
  fullname: string
  name_with_initials: string
  census_id: string
  school_name: string
}

interface PaymentRow {
  id: number
  invoice_no: string
  year: number
  include_member_fee: boolean
  total: number
  paid_date: string
  annual_fee: number
  member_fee: number
}

interface PaymentReportRow {
  id: number
  std_id: number
  admission_no: string
  invoice_no: string
  year: number
  include_member_fee: boolean
  total: number
  paid_date: string
  annual_fee: number
  member_fee: number
  fullname: string
  name_with_initials: string
  grade_id: number
  class_id: number
  grade: string
  class: string
  grade_class: string
  payment_status: 'paid' | 'not_paid'
}

interface GradeRow {
  grade_id: number
  grade: string
}

interface ClassRow {
  class_id: number
  class: string
}

interface PaymentReportScope {
  sch_grd_cls_id: number
  year: number
  grade_id: number
  class_id: number
  grade: string
  class: string
  grade_class: string
}

interface PaymentReportSummary {
  payment_count: number
  total_amount: number
}

interface PaymentReportFilters {
  year: number
  grade_id: number
  class_id: number
  admission_no: string
  invoice_no: string
  payment_status: 'all' | 'paid' | 'not_paid'
}

interface FeeDetails {
  year: number
  annual_fee: number
  member_fee: number
}

interface FeeTypeRow {
  id: number
  year: number
  annual_fee: number
  member_fee: number
  date_added: string
}

interface PaymentOptionsResponse {
  schools?: OptionRow[]
  years?: number[]
}

interface PaymentStudentResponse {
  student?: PaymentStudent | null
  payments?: PaymentRow[]
}

interface FeeTypeListResponse {
  fee_types?: FeeTypeRow[]
}

interface GradeResponse {
  data?: GradeRow[]
}

interface ClassResponse {
  data?: ClassRow[]
}

interface PaymentReportResponse {
  filters?: PaymentReportFilters
  scope?: PaymentReportScope | null
  summary?: PaymentReportSummary | null
  data?: PaymentReportRow[]
  message?: string
}

type PaymentViewMode = 'entry' | 'report'

const ui = useUiStore()
const route = useRoute()
const currentUser = ref<AuthUser | null>(getUser())
const roleName = computed(() => String(currentUser.value?.role_name ?? '').trim().toLowerCase())
const isAdmin = computed(() => (currentUser.value?.role_id ?? 0) === 1 || roleName.value === 'admin' || roleName.value === 'administrator')
const isPrincipal = computed(() => (currentUser.value?.role_id ?? 0) === 2 || roleName.value === 'principal')
const isSdsUser = computed(() => (currentUser.value?.role_id ?? 0) === 4 || roleName.value === 'sds user')
const isClassTeacher = computed(() => ['class teacher', 'class_teacher', 'classteacher'].includes(roleName.value))
const classTeacherAssignmentWarning = computed(() => {
  if (!isClassTeacher.value) return ''

  const status = currentUser.value?.class_teacher_assignment_status ?? null
  if (status && status.is_assigned === false && typeof status.message === 'string') {
    return status.message
  }

  const message = currentUser.value?.class_teacher_assignment_message
  return typeof message === 'string' ? message : ''
})
const isStudent = computed(() => (currentUser.value?.role_id ?? 0) === 7 || roleName.value === 'student')
const canViewFeeTypes = computed(() => isAdmin.value || isPrincipal.value || isSdsUser.value)
const canManageFeeTypes = computed(() => isAdmin.value || isPrincipal.value)
const canManagePayments = computed(() => isAdmin.value || isPrincipal.value || isSdsUser.value)
const canViewPaymentReport = computed(() => isAdmin.value || isPrincipal.value || isSdsUser.value || isClassTeacher.value)
const isFeeTypesRoute = computed(() => route.name === 'payments-fee-types')
const refreshCurrentUser = async (): Promise<void> => {
  const token = getToken()
  if (!token) {
    return
  }

  try {
    const { data } = await api.get<{ user: AuthUser }>('/auth/me')
    if (data.user) {
      setAuthSession(token, data.user)
      currentUser.value = data.user
    }
  } catch {
    currentUser.value = getUser()
  }
}

const text = computed(() => {
  if (ui.language === 'si') {
    return {
      title: 'SDS ගෙවීම්',
      subtitle: 'සිසුන්ගේ SDS ගෙවීම් සොයන්න, වාර්ෂික ගාස්තු පරීක්ෂා කරන්න, සහ නව ගෙවීම් සටහන් කරන්න.',
      reportSubtitle: 'වර්ෂය, ශ්‍රේණිය, පන්තිය, ඇතුළත් අංකය සහ ඉන්වොයිස් අංකය අනුව SDS ගෙවීම් වාර්තා බලන්න.',
      feeTypesSubtitle: canManageFeeTypes.value
        ? 'වාර්ෂික SDS ගාස්තු වර්ග කළමනාකරණය කර වර්ෂ අනුව ගාස්තු අගයන් යාවත්කාලීන කරන්න.'
        : 'වාර්ෂික SDS ගාස්තු වර්ග සහ වර්ෂ අනුව ගාස්තු අගයන් බලන්න.',
      school: 'පාසල',
      selectSchool: 'පාසල තෝරන්න',
      studentAdmissionNo: 'ඇතුළත් අංකය',
      studentAdmissionPlaceholder: 'ඇතුළත් අංකය ඇතුළත් කරන්න',
      searchStudent: 'සිසුවා සොයන්න',
      searching: 'සොයමින්...',
      numberLabel: '#',
      nameLabel: 'නම',
      admissionLabel: 'ඇතුළත් අංකය',
      studentName: 'සිසුවාගේ නම',
      invoiceNo: 'ඉන්වොයිස් අංකය',
      year: 'වර්ෂය',
      selectYear: 'වර්ෂය තෝරන්න',
      annualFee: 'වාර්ෂික ගාස්තුව',
      memberFee: 'සාමාජික ගාස්තුව',
      includeMemberFee: 'සාමාජික ගාස්තුව එකතු කරන්න',
      total: 'මුළු මුදල',
      totalPayments: 'මුළු ගෙවීම්',
      totalFee: 'මුළු ගාස්තුව',
      addPayment: 'ගෙවීම එක් කරන්න',
      addFeeType: 'ගාස්තු වර්ගය එක් කරන්න',
      paymentEntry: 'ගෙවීම් ඇතුළත් කිරීම',
      paymentReport: 'ගෙවීම් වාර්තාව',
      edit: 'සංස්කරණය',
      saving: 'සුරකිමින්...',
      paymentHistory: 'ගෙවීම් ඉතිහාසය',
      paymentHistoryTitle: 'සිසුවාගේ ගෙවීම්',
      paymentReportSection: 'ගෙවීම් වාර්තාව',
      paymentReportTitle: 'SDS ගෙවීම් වාර්තාව',
      paymentStatus: 'ගෙවීම් තත්ත්වය',
      paymentStatusAll: 'සියල්ල',
      paymentStatusPaid: 'ගෙවූ',
      paymentStatusNotPaid: 'නොගෙවූ',
      feeTypesSection: 'ගාස්තු වර්ග',
      feeTypesTitle: 'වාර්ෂික ගාස්තු වර්ග',
      addPaymentDialogTitle: 'ගෙවීම එක් කරන්න',
      addFeeTypeDialogTitle: 'වාර්ෂික ගාස්තු වර්ගය එක් කරන්න',
      editFeeTypeDialogTitle: 'වාර්ෂික ගාස්තු වර්ගය සංස්කරණය කරන්න',
      paidDate: 'ගෙවූ දිනය',
      dateAdded: 'එක් කළ දිනය',
      actions: 'ක්‍රියා',
      noPayments: 'මෙම සිසුවා සඳහා ගෙවීම් හමු නොවීය.',
      noReportPayments: 'තෝරාගත් පෙරහන් සඳහා ගෙවීම් හමු නොවීය.',
      noReportStudents: 'තෝරාගත් පෙරහන් සඳහා සිසුන් හමු නොවීය.',
      noFeeTypes: 'වාර්ෂික ගාස්තු වර්ග හමු නොවීය.',
      notIncluded: 'එකතු කර නැත',
      paymentAdded: 'ගෙවීම සාර්ථකව එක් කරන ලදී.',
      feeTypeAdded: 'වාර්ෂික ගාස්තු වර්ගය සාර්ථකව එක් කරන ලදී.',
      feeTypeUpdated: 'වාර්ෂික ගාස්තු වර්ගය සාර්ථකව යාවත්කාලීන කරන ලදී.',
      selectStudentFirst: 'පළමුව සිසුවෙකු තෝරන්න.',
      unableToLoadPayments: 'ගෙවීම් දත්ත පූරණය කළ නොහැක.',
      unableToLoadFee: 'මෙම වර්ෂය සඳහා ගාස්තු හමු නොවීය.',
      unableToLoadFeeTypes: 'ගාස්තු වර්ග දත්ත පූරණය කළ නොහැක.',
      invoiceRequired: 'ඉන්වොයිස් අංකය අවශ්‍යය.',
      yearRequired: 'වර්ෂය අවශ්‍යය.',
      annualFeeRequired: 'වාර්ෂික ගාස්තුව අවශ්‍යය.',
      memberFeeRequired: 'සාමාජික ගාස්තුව අවශ්‍යය.',
      selectSchoolFirst: 'පළමුව පාසලක් තෝරන්න.',
      allYears: 'වර්ෂය තෝරන්න',
      allGrades: 'ශ්‍රේණිය තෝරන්න',
      allClasses: 'පන්තිය තෝරන්න',
      grade: 'ශ්‍රේණිය',
      class: 'පන්තිය',
      scope: 'පරාසය',
      status: 'තත්ත්වය',
      exportExcel: 'Excel',
      refresh: 'නැවත පූරණය',
      reset: 'යළි සකසන්න',
      close: 'වසන්න',
      cancel: 'අවලංගු කරන්න',
      saveChanges: 'වෙනස්කම් සුරකින්න',
    }
  }

  if (ui.language === 'ta') {
    return {
      title: 'SDS கட்டணங்கள்',
      subtitle: 'மாணவர் SDS கட்டணங்களைத் தேடவும், வருடாந்திர கட்டணங்களை பார்க்கவும், புதிய கட்டணங்களை பதிவு செய்யவும்.',
      reportSubtitle: 'ஆண்டு, தரம், வகுப்பு, அனுமதி இலக்கம் மற்றும் விலைப்பட்டியல் எண் அடிப்படையில் SDS கட்டண அறிக்கைகளை பார்க்கவும்.',
      feeTypesSubtitle: canManageFeeTypes.value
        ? 'வருடாந்திர SDS கட்டண வகைகளை நிர்வகித்து ஆண்டுவாரியான கட்டண தொகைகளை புதுப்பிக்கவும்.'
        : 'வருடாந்திர SDS கட்டண வகைகள் மற்றும் ஆண்டுவாரியான கட்டண தொகைகளை பார்க்கவும்.',
      school: 'பாடசாலை',
      selectSchool: 'பாடசாலையைத் தேர்ந்தெடுக்கவும்',
      studentAdmissionNo: 'அனுமதி இலக்கம்',
      studentAdmissionPlaceholder: 'அனுமதி இலக்கத்தை உள்ளிடவும்',
      searchStudent: 'மாணவரை தேடுக',
      searching: 'தேடப்படுகிறது...',
      numberLabel: '#',
      nameLabel: 'பெயர்',
      admissionLabel: 'அனுமதி இலக்கம்',
      studentName: 'மாணவர் பெயர்',
      invoiceNo: 'விலைப்பட்டியல் எண்',
      year: 'ஆண்டு',
      selectYear: 'ஆண்டைத் தேர்ந்தெடுக்கவும்',
      annualFee: 'வருடாந்திர கட்டணம்',
      memberFee: 'உறுப்பினர் கட்டணம்',
      includeMemberFee: 'உறுப்பினர் கட்டணத்தை சேர்க்கவும்',
      total: 'மொத்தம்',
      totalPayments: 'மொத்த கட்டணங்கள்',
      totalFee: 'மொத்த கட்டணம்',
      addPayment: 'கட்டணம் சேர்க்கவும்',
      addFeeType: 'கட்டண வகை சேர்க்கவும்',
      paymentEntry: 'கட்டண பதிவு',
      paymentReport: 'கட்டண அறிக்கை',
      edit: 'திருத்து',
      saving: 'சேமிக்கப்படுகிறது...',
      paymentHistory: 'கட்டண வரலாறு',
      paymentHistoryTitle: 'மாணவர் கட்டணங்கள்',
      paymentReportSection: 'கட்டண அறிக்கை',
      paymentReportTitle: 'SDS கட்டண அறிக்கை',
      paymentStatus: 'கட்டண நிலை',
      paymentStatusAll: 'அனைத்தும்',
      paymentStatusPaid: 'செலுத்தப்பட்டது',
      paymentStatusNotPaid: 'செலுத்தப்படவில்லை',
      feeTypesSection: 'கட்டண வகைகள்',
      feeTypesTitle: 'வருடாந்திர கட்டண வகைகள்',
      addPaymentDialogTitle: 'கட்டணம் சேர்க்கவும்',
      addFeeTypeDialogTitle: 'வருடாந்திர கட்டண வகை சேர்க்கவும்',
      editFeeTypeDialogTitle: 'வருடாந்திர கட்டண வகையை திருத்தவும்',
      paidDate: 'செலுத்திய தேதி',
      dateAdded: 'சேர்த்த தேதி',
      actions: 'செயல்கள்',
      noPayments: 'இந்த மாணவருக்கான கட்டணங்கள் இல்லை.',
      noReportPayments: 'தேர்ந்தெடுத்த வடிகட்டல்களுக்கு கட்டணங்கள் இல்லை.',
      noReportStudents: 'தேர்ந்தெடுத்த வடிகட்டல்களுக்கு மாணவர்கள் இல்லை.',
      noFeeTypes: 'வருடாந்திர கட்டண வகைகள் இல்லை.',
      notIncluded: 'சேர்க்கப்படவில்லை',
      paymentAdded: 'கட்டணம் வெற்றிகரமாக சேர்க்கப்பட்டது.',
      feeTypeAdded: 'வருடாந்திர கட்டண வகை வெற்றிகரமாக சேர்க்கப்பட்டது.',
      feeTypeUpdated: 'வருடாந்திர கட்டண வகை வெற்றிகரமாக புதுப்பிக்கப்பட்டது.',
      selectStudentFirst: 'முதலில் ஒரு மாணவரைத் தேர்ந்தெடுக்கவும்.',
      unableToLoadPayments: 'கட்டண தரவை ஏற்ற முடியவில்லை.',
      unableToLoadFee: 'இந்த ஆண்டிற்கு கட்டணங்கள் இல்லை.',
      unableToLoadFeeTypes: 'கட்டண வகை தரவை ஏற்ற முடியவில்லை.',
      invoiceRequired: 'விலைப்பட்டியல் எண் தேவை.',
      yearRequired: 'ஆண்டு தேவை.',
      annualFeeRequired: 'வருடாந்திர கட்டணம் தேவை.',
      memberFeeRequired: 'உறுப்பினர் கட்டணம் தேவை.',
      selectSchoolFirst: 'முதலில் ஒரு பாடசாலையைத் தேர்ந்தெடுக்கவும்.',
      allYears: 'ஆண்டைத் தேர்ந்தெடுக்கவும்',
      allGrades: 'தரத்தைத் தேர்ந்தெடுக்கவும்',
      allClasses: 'வகுப்பைத் தேர்ந்தெடுக்கவும்',
      grade: 'தரம்',
      class: 'வகுப்பு',
      scope: 'வரம்பு',
      status: 'நிலை',
      exportExcel: 'Excel',
      refresh: 'மீண்டும் ஏற்று',
      reset: 'மீட்டமை',
      close: 'மூடு',
      cancel: 'ரத்து செய்',
      saveChanges: 'மாற்றங்களை சேமிக்கவும்',
    }
  }

  return {
    title: 'SDS Payments',
    subtitle: 'Search student SDS payments, review yearly fee amounts, and record new payments.',
    reportSubtitle: 'Review SDS payment reports by year, grade, class, admission no, and invoice no.',
    feeTypesSubtitle: canManageFeeTypes.value
      ? 'Manage annual SDS fee types and update yearly fee amounts.'
      : 'View annual SDS fee types and yearly fee amounts.',
    school: 'School',
    selectSchool: 'Select school',
    studentAdmissionNo: 'Admission No',
    studentAdmissionPlaceholder: 'Enter admission number',
    searchStudent: 'Search Student',
    searching: 'Searching...',
    numberLabel: '#',
    nameLabel: 'Name',
    admissionLabel: 'Admission No',
    studentName: 'Student Name',
    invoiceNo: 'Invoice No',
    year: 'Year',
    selectYear: 'Select year',
    annualFee: 'Annual Fee',
    memberFee: 'Member Fee',
    includeMemberFee: 'Include member fee',
    total: 'Total',
    totalPayments: 'Total Payments',
    totalFee: 'Total Fee',
    addPayment: 'Add Payment',
    addFeeType: 'Add Fee Type',
    paymentEntry: 'Payment Entry',
    paymentReport: 'Payment Report',
    edit: 'Edit',
    saving: 'Saving...',
    paymentHistory: 'Payment History',
    paymentHistoryTitle: 'Student Payments',
    paymentReportSection: 'Payment Report',
    paymentReportTitle: 'SDS Payment Report',
    paymentStatus: 'Payment Status',
    paymentStatusAll: 'All',
    paymentStatusPaid: 'Paid',
    paymentStatusNotPaid: 'Not Paid',
    feeTypesSection: 'Fee Types',
    feeTypesTitle: 'Annual Fee Types',
    addPaymentDialogTitle: 'Add Payment',
    addFeeTypeDialogTitle: 'Add Annual Fee Type',
    editFeeTypeDialogTitle: 'Edit Annual Fee Type',
    paidDate: 'Paid Date',
    dateAdded: 'Date Added',
    actions: 'Actions',
    noPayments: 'No payments found for this student.',
    noReportPayments: 'No payments found for the selected filters.',
    noReportStudents: 'No students found for the selected filters.',
    noFeeTypes: 'No annual fee types found.',
    notIncluded: 'Not included',
    paymentAdded: 'Payment added successfully.',
    feeTypeAdded: 'Annual fee type added successfully.',
    feeTypeUpdated: 'Annual fee type updated successfully.',
    selectStudentFirst: 'Select a student first.',
    unableToLoadPayments: 'Unable to load payment data.',
    unableToLoadFee: 'No fees assigned to this year.',
    unableToLoadFeeTypes: 'Unable to load annual fee type data.',
    invoiceRequired: 'Invoice number is required.',
    yearRequired: 'Year is required.',
    annualFeeRequired: 'Annual fee is required.',
    memberFeeRequired: 'Member fee is required.',
    selectSchoolFirst: 'Select a school first.',
    allYears: 'Select Year',
    allGrades: 'Select Grade',
    allClasses: 'Select Class',
    grade: 'Grade',
    class: 'Class',
    scope: 'Scope',
    status: 'Status',
    exportExcel: 'Excel',
    refresh: 'Refresh',
    reset: 'Reset',
    close: 'Close',
    cancel: 'Cancel',
    saveChanges: 'Save Changes',
  }
})

const schools = ref<OptionRow[]>([])
const yearOptions = ref<number[]>([])
const selectedSchoolCensusId = ref<number>(getSchoolContextCensusId() ?? 0)
const paymentViewMode = ref<PaymentViewMode>('entry')
const studentIndexNo = ref('')
const student = ref<PaymentStudent | null>(null)
const paymentRows = ref<PaymentRow[]>([])
const reportRows = ref<PaymentReportRow[]>([])
const reportGrades = ref<GradeRow[]>([])
const reportClasses = ref<ClassRow[]>([])
const reportScope = ref<PaymentReportScope | null>(null)
const feeDetails = ref<FeeDetails | null>(null)
const feeTypeRows = ref<FeeTypeRow[]>([])
const message = ref('')
const errorMessage = ref('')
const lookingUpStudent = ref(false)
const loadingReport = ref(false)
const savingPayment = ref(false)
const showAddPaymentDialog = ref(false)
const paymentDialogError = ref('')
const showFeeTypeDialog = ref(false)
const savingFeeType = ref(false)
const feeTypeDialogError = ref('')
const editingFeeTypeId = ref<number | null>(null)

const paymentForm = ref({
  invoice_no: '',
  year: 0,
  include_member_fee: true,
})

const feeTypeForm = ref({
  year: 0,
  annual_fee: 0,
  member_fee: 0,
})

const reportFilters = ref<PaymentReportFilters>({
  year: 0,
  grade_id: 0,
  class_id: 0,
  admission_no: '',
  invoice_no: '',
  payment_status: 'all',
})

const reportSummary = ref<PaymentReportSummary>({
  payment_count: 0,
  total_amount: 0,
})

const isPaymentReportMode = computed(() => !isFeeTypesRoute.value && paymentViewMode.value === 'report')
const isPaymentEntryMode = computed(() => !isFeeTypesRoute.value && paymentViewMode.value === 'entry')
const showPaymentModeToggle = computed(() => !isFeeTypesRoute.value && !isStudent.value && canViewPaymentReport.value)
const pageSubtitle = computed(() => {
  if (isFeeTypesRoute.value) {
    return text.value.feeTypesSubtitle
  }

  return isPaymentReportMode.value ? text.value.reportSubtitle : text.value.subtitle
})
const lookupDisabled = computed(() => isAdmin.value && selectedSchoolCensusId.value <= 0)
const calculatedTotal = computed(() => {
  const annual = Number(feeDetails.value?.annual_fee ?? 0)
  const member = paymentForm.value.include_member_fee ? Number(feeDetails.value?.member_fee ?? 0) : 0
  return annual + member
})
const canSubmitPayment = computed(() => {
  return student.value !== null
    && paymentForm.value.invoice_no.trim() !== ''
    && paymentForm.value.year > 0
    && feeDetails.value !== null
})
const canSubmitFeeType = computed(() => {
  return feeTypeForm.value.year > 0
    && Number.isFinite(Number(feeTypeForm.value.annual_fee))
    && Number(feeTypeForm.value.annual_fee) >= 0
    && Number.isFinite(Number(feeTypeForm.value.member_fee))
    && Number(feeTypeForm.value.member_fee) >= 0
})
const feeTypeYearOptions = computed<number[]>(() => {
  const currentYear = new Date().getFullYear()
  const years: number[] = []

  for (let year = currentYear; year >= 2020; year -= 1) {
    years.push(year)
  }

  return years
})
const feeTypeDialogTitle = computed(() => (editingFeeTypeId.value === null ? text.value.addFeeTypeDialogTitle : text.value.editFeeTypeDialogTitle))
const feeTypeSubmitLabel = computed(() => (editingFeeTypeId.value === null ? text.value.addFeeType : text.value.saveChanges))
const reportScopeLabel = computed(() => reportScope.value?.grade_class || '-')

const clearStatus = (): void => {
  message.value = ''
  errorMessage.value = ''
}

const resetReportState = (): void => {
  reportRows.value = []
  reportScope.value = null
  reportSummary.value = {
    payment_count: 0,
    total_amount: 0,
  }
}

const resetReportFilters = (): void => {
  reportFilters.value = {
    year: 0,
    grade_id: 0,
    class_id: 0,
    admission_no: '',
    invoice_no: '',
    payment_status: 'all',
  }
  reportGrades.value = []
  reportClasses.value = []
  resetReportState()
}

const resetStudentState = (): void => {
  student.value = null
  paymentRows.value = []
  feeDetails.value = null
  paymentForm.value.invoice_no = ''
  paymentForm.value.year = 0
  paymentForm.value.include_member_fee = true
  showAddPaymentDialog.value = false
}

const resetPaymentForm = (): void => {
  paymentForm.value.invoice_no = ''
  paymentForm.value.year = 0
  paymentForm.value.include_member_fee = true
  feeDetails.value = null
  paymentDialogError.value = ''
}

const resetFeeTypeForm = (): void => {
  feeTypeForm.value.year = 0
  feeTypeForm.value.annual_fee = 0
  feeTypeForm.value.member_fee = 0
  feeTypeDialogError.value = ''
  editingFeeTypeId.value = null
}

const buildSchoolHeaders = (): Record<string, string> | undefined => {
  if (!isAdmin.value) {
    return undefined
  }

  const selected = Number(selectedSchoolCensusId.value)
  if (!Number.isFinite(selected) || selected <= 0) {
    return undefined
  }

  return { 'X-School-Census-Id': String(selected) }
}

const formatAmount = (value: number): string => {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const formatDate = (value: string): string => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toISOString().slice(0, 10)
}

const switchToPaymentEntry = (): void => {
  paymentViewMode.value = 'entry'
  clearStatus()
}

const switchToPaymentReport = async (): Promise<void> => {
  paymentViewMode.value = 'report'
  resetStudentState()
  clearStatus()
  resetReportState()

  if (!isClassTeacher.value && reportFilters.value.year > 0) {
    await loadReportGrades(reportFilters.value.year)
  }
}

const openAddPaymentDialog = (): void => {
  resetPaymentForm()
  clearStatus()
  showAddPaymentDialog.value = true
}

const closeAddPaymentDialog = (): void => {
  showAddPaymentDialog.value = false
  resetPaymentForm()
}

const openFeeTypeDialog = (row?: FeeTypeRow): void => {
  clearStatus()
  feeTypeDialogError.value = ''

  if (row) {
    editingFeeTypeId.value = row.id
    feeTypeForm.value.year = row.year
    feeTypeForm.value.annual_fee = row.annual_fee
    feeTypeForm.value.member_fee = row.member_fee
  } else {
    resetFeeTypeForm()
  }

  showFeeTypeDialog.value = true
}

const closeFeeTypeDialog = (): void => {
  showFeeTypeDialog.value = false
  resetFeeTypeForm()
}

const extractApiMessage = (reason: unknown): string => {
  if (typeof reason === 'object' && reason !== null && 'response' in reason) {
    const response = (reason as { response?: { data?: { message?: string } } }).response
    if (typeof response?.data?.message === 'string' && response.data.message.trim() !== '') {
      return response.data.message
    }
  }

  return ''
}

const loadReportGrades = async (year: number): Promise<void> => {
  if (isClassTeacher.value || year <= 0) {
    reportGrades.value = []
    reportFilters.value.grade_id = 0
    reportClasses.value = []
    reportFilters.value.class_id = 0
    return
  }

  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.get<GradeResponse>('/grades', {
      headers,
      params: { year },
    })

    reportGrades.value = Array.isArray(data.data) ? data.data : []
    if (!reportGrades.value.some((row) => row.grade_id === reportFilters.value.grade_id)) {
      reportFilters.value.grade_id = 0
    }
  } catch {
    reportGrades.value = []
    reportFilters.value.grade_id = 0
  }
}

const loadReportClasses = async (gradeId: number, year: number): Promise<void> => {
  if (isClassTeacher.value || gradeId <= 0 || year <= 0) {
    reportClasses.value = []
    reportFilters.value.class_id = 0
    return
  }

  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.get<ClassResponse>(`/classes/by-grade/${gradeId}`, {
      headers,
      params: { year },
    })

    reportClasses.value = Array.isArray(data.data) ? data.data : []
    if (!reportClasses.value.some((row) => row.class_id === reportFilters.value.class_id)) {
      reportFilters.value.class_id = 0
    }
  } catch {
    reportClasses.value = []
    reportFilters.value.class_id = 0
  }
}

const loadPaymentReport = async (): Promise<void> => {
  if (!canViewPaymentReport.value) {
    return
  }

  const hasHistoryLookup = reportFilters.value.admission_no.trim() !== '' || reportFilters.value.invoice_no.trim() !== ''

  if (!isClassTeacher.value && reportFilters.value.year <= 0 && !hasHistoryLookup) {
    clearStatus()
    resetReportState()
    return
  }

  if (lookupDisabled.value) {
    errorMessage.value = text.value.selectSchoolFirst
    resetReportState()
    return
  }

  loadingReport.value = true
  clearStatus()

  try {
    const headers = buildSchoolHeaders()
    const params: Record<string, number | string> = {}
    if (reportFilters.value.year > 0) params.year = reportFilters.value.year
    if (reportFilters.value.grade_id > 0) params.grade_id = reportFilters.value.grade_id
    if (reportFilters.value.class_id > 0) params.class_id = reportFilters.value.class_id
    if (reportFilters.value.admission_no.trim() !== '') params.admission_no = reportFilters.value.admission_no.trim()
    if (reportFilters.value.invoice_no.trim() !== '') params.invoice_no = reportFilters.value.invoice_no.trim()
    if (reportFilters.value.payment_status !== 'all') params.payment_status = reportFilters.value.payment_status

    const { data } = await api.get<PaymentReportResponse>('/payments/report', {
      headers,
      params,
    })

    reportRows.value = Array.isArray(data.data) ? data.data : []
    reportScope.value = data.scope ?? null
    reportSummary.value = data.summary ?? { payment_count: 0, total_amount: 0 }
    if (data.filters) {
      reportFilters.value = {
        year: Number(data.filters.year ?? 0),
        grade_id: Number(data.filters.grade_id ?? 0),
        class_id: Number(data.filters.class_id ?? 0),
        admission_no: String(data.filters.admission_no ?? ''),
        invoice_no: String(data.filters.invoice_no ?? ''),
        payment_status: String(data.filters.payment_status ?? 'all') as PaymentReportFilters['payment_status'],
      }
    }
    message.value = typeof data.message === 'string' ? data.message : ''

    if (!isClassTeacher.value && reportFilters.value.year > 0) {
      await loadReportGrades(reportFilters.value.year)
      if (reportFilters.value.grade_id > 0) {
        await loadReportClasses(reportFilters.value.grade_id, reportFilters.value.year)
      } else {
        reportClasses.value = []
      }
    }
  } catch (reason) {
    resetReportState()
    errorMessage.value = extractApiMessage(reason) || text.value.unableToLoadPayments
  } finally {
    loadingReport.value = false
  }
}

const onReportYearChange = async (): Promise<void> => {
  reportFilters.value.grade_id = 0
  reportFilters.value.class_id = 0
  reportClasses.value = []
  await loadReportGrades(reportFilters.value.year)
}

const onReportGradeChange = async (): Promise<void> => {
  reportFilters.value.class_id = 0
  await loadReportClasses(reportFilters.value.grade_id, reportFilters.value.year)
}

const searchPaymentReport = async (): Promise<void> => {
  await loadPaymentReport()
}

const exportPaymentReportExcel = async (): Promise<void> => {
  if (!canViewPaymentReport.value) {
    return
  }

  clearStatus()

  const hasHistoryLookup = reportFilters.value.admission_no.trim() !== '' || reportFilters.value.invoice_no.trim() !== ''

  if (!isClassTeacher.value && reportFilters.value.year <= 0 && !hasHistoryLookup) {
    errorMessage.value = text.value.yearRequired
    return
  }

  if (lookupDisabled.value) {
    errorMessage.value = text.value.selectSchoolFirst
    return
  }

  try {
    const headers = buildSchoolHeaders()
    const params: Record<string, number | string> = {}
    if (reportFilters.value.year > 0) params.year = reportFilters.value.year
    if (reportFilters.value.grade_id > 0) params.grade_id = reportFilters.value.grade_id
    if (reportFilters.value.class_id > 0) params.class_id = reportFilters.value.class_id
    if (reportFilters.value.admission_no.trim() !== '') params.admission_no = reportFilters.value.admission_no.trim()
    if (reportFilters.value.invoice_no.trim() !== '') params.invoice_no = reportFilters.value.invoice_no.trim()
    if (reportFilters.value.payment_status !== 'all') params.payment_status = reportFilters.value.payment_status

    const response = await api.get('/payments/report/export', {
      headers,
      params,
      responseType: 'blob',
    })

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    const disposition = String(response.headers?.['content-disposition'] ?? '')
    const fileNameMatch = disposition.match(/filename=\"?([^\"]+)\"?/i)
    link.href = url
    link.download = fileNameMatch?.[1] || 'payments-report.xlsx'
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (reason) {
    errorMessage.value = extractApiMessage(reason) || text.value.unableToLoadPayments
  }
}

const resetPaymentReport = async (): Promise<void> => {
  resetReportFilters()
}

const loadOptions = async (): Promise<void> => {
  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.get<PaymentOptionsResponse>('/payments/options', headers ? { headers } : undefined)
    schools.value = Array.isArray(data.schools) ? data.schools : []
    yearOptions.value = Array.isArray(data.years) ? data.years.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0) : []
  } catch (reason) {
    schools.value = []
    yearOptions.value = []
    errorMessage.value = extractApiMessage(reason) || text.value.unableToLoadPayments
  }
}

const loadFeeTypeRows = async (): Promise<void> => {
  try {
    const { data } = await api.get<FeeTypeListResponse>('/payments/fee-types')
    feeTypeRows.value = Array.isArray(data.fee_types) ? data.fee_types : []
  } catch (reason) {
    feeTypeRows.value = []
    errorMessage.value = extractApiMessage(reason) || text.value.unableToLoadFeeTypes
  }
}

const lookupStudent = async (keepStatus: boolean = false): Promise<void> => {
  if (lookupDisabled.value) {
    errorMessage.value = text.value.selectSchoolFirst
    return
  }

  if (!keepStatus) {
    clearStatus()
  }
  lookingUpStudent.value = true

  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.get<PaymentStudentResponse>('/payments/student', {
      headers,
      params: {
        index_no: studentIndexNo.value.trim(),
      },
    })

    student.value = data.student ?? null
    paymentRows.value = Array.isArray(data.payments) ? data.payments : []
    resetPaymentForm()
  } catch (reason) {
    resetStudentState()
    errorMessage.value = extractApiMessage(reason) || text.value.unableToLoadPayments
  } finally {
    lookingUpStudent.value = false
  }
}

const loadFeeDetails = async (year: number): Promise<void> => {
  feeDetails.value = null

  if (year <= 0 || isFeeTypesRoute.value) {
    return
  }

  clearStatus()

  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.get<FeeDetails>('/payments/fee', {
      headers,
      params: { year },
    })

    feeDetails.value = data
  } catch (reason) {
    feeDetails.value = null
    errorMessage.value = extractApiMessage(reason) || text.value.unableToLoadFee
  }
}

const savePayment = async (): Promise<void> => {
  if (student.value === null) {
    paymentDialogError.value = text.value.selectStudentFirst
    return
  }

  if (paymentForm.value.invoice_no.trim() === '') {
    paymentDialogError.value = text.value.invoiceRequired
    return
  }

  if (paymentForm.value.year <= 0) {
    paymentDialogError.value = text.value.yearRequired
    return
  }

  clearStatus()
  paymentDialogError.value = ''
  savingPayment.value = true

  try {
    const headers = buildSchoolHeaders()
    const { data } = await api.post('/payments', {
      index_no: student.value.index_no,
      invoice_no: paymentForm.value.invoice_no.trim(),
      year: paymentForm.value.year,
      include_member_fee: paymentForm.value.include_member_fee,
    }, headers ? { headers } : undefined)

    message.value = typeof data?.message === 'string' && data.message.trim() !== ''
      ? data.message
      : text.value.paymentAdded

    closeAddPaymentDialog()
    await loadOptions()
    await lookupStudent(true)
  } catch (reason) {
    paymentDialogError.value = extractApiMessage(reason) || text.value.unableToLoadPayments
  } finally {
    savingPayment.value = false
  }
}

const saveFeeType = async (): Promise<void> => {
  if (feeTypeForm.value.year <= 0) {
    feeTypeDialogError.value = text.value.yearRequired
    return
  }

  if (Number(feeTypeForm.value.annual_fee) < 0 || Number.isNaN(Number(feeTypeForm.value.annual_fee))) {
    feeTypeDialogError.value = text.value.annualFeeRequired
    return
  }

  if (Number(feeTypeForm.value.member_fee) < 0 || Number.isNaN(Number(feeTypeForm.value.member_fee))) {
    feeTypeDialogError.value = text.value.memberFeeRequired
    return
  }

  clearStatus()
  feeTypeDialogError.value = ''
  savingFeeType.value = true

  try {
    const payload = {
      year: Number(feeTypeForm.value.year),
      annual_fee: Number(feeTypeForm.value.annual_fee),
      member_fee: Number(feeTypeForm.value.member_fee),
    }

    if (editingFeeTypeId.value === null) {
      const { data } = await api.post('/payments/fee-types', payload)
      message.value = typeof data?.message === 'string' && data.message.trim() !== ''
        ? data.message
        : text.value.feeTypeAdded
    } else {
      const { data } = await api.put(`/payments/fee-types/${editingFeeTypeId.value}`, payload)
      message.value = typeof data?.message === 'string' && data.message.trim() !== ''
        ? data.message
        : text.value.feeTypeUpdated
    }

    closeFeeTypeDialog()
    await loadOptions()
    await loadFeeTypeRows()
  } catch (reason) {
    feeTypeDialogError.value = extractApiMessage(reason) || text.value.unableToLoadFeeTypes
  } finally {
    savingFeeType.value = false
  }
}

const onSchoolChange = async (): Promise<void> => {
  clearStatus()
  setSchoolContextCensusId(selectedSchoolCensusId.value > 0 ? selectedSchoolCensusId.value : null)
  studentIndexNo.value = ''
  resetStudentState()
  resetReportFilters()
  await loadOptions()
}

const initializeView = async (): Promise<void> => {
  clearStatus()

  if (isFeeTypesRoute.value) {
    closeAddPaymentDialog()
    resetStudentState()
    await loadFeeTypeRows()
    return
  }

  closeFeeTypeDialog()
  await loadOptions()
  resetReportFilters()

  if (isStudent.value) {
    try {
      const { data } = await api.get<{ data?: { index_no?: string } }>('/students/me')
      studentIndexNo.value = String(data.data?.index_no ?? '').trim()
    } catch {
      studentIndexNo.value = ''
    }

    if (studentIndexNo.value !== '') {
      await lookupStudent()
    }
  }
}

watch(() => paymentForm.value.year, async (year) => {
  await loadFeeDetails(Number(year))
})

watch(() => route.name, async () => {
  await initializeView()
})

onMounted(async () => {
  await refreshCurrentUser()
  await initializeView()
})
</script>
