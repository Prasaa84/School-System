<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm text-slate-600">{{ message }}</p>
  </section>
</template>

<script setup lang="ts">
defineProps<{ message: string }>()
</script>
