<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 p-4 print:hidden" @click="emit('close')">
    <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" @click.stop>
      <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <div class="flex items-center gap-3">
          <div v-if="$slots.icon" class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-100 text-rose-700">
            <slot name="icon" />
          </div>
          <div>
            <h3 class="font-display text-lg font-bold text-slate-900">{{ title }}</h3>
          </div>
        </div>

        <button
          class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50"
          :title="closeLabel"
          :aria-label="closeLabel"
          :disabled="busy"
          @click="emit('close')"
        >
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
            <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
          </svg>
        </button>
      </div>

      <div class="px-5 py-4">
        <p class="text-sm leading-6 text-slate-600">{{ message }}</p>
      </div>

      <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
        <button
          class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="busy"
          @click="emit('close')"
        >
          {{ cancelLabel }}
        </button>
        <button
          class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-rose-300"
          :disabled="busy"
          @click="emit('confirm')"
        >
          {{ busy ? busyConfirmLabel : confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  open: boolean
  title: string
  message: string
  confirmLabel: string
  busyConfirmLabel: string
  cancelLabel: string
  closeLabel: string
  busy?: boolean
}>()

const emit = defineEmits<{
  close: []
  confirm: []
}>()
</script>
