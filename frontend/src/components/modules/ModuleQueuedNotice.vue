<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm text-slate-600">{{ localizedMessage }}</p>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useUiStore } from '../../stores/ui'

const props = defineProps<{ message: string }>()

const ui = useUiStore()

const localizedMessage = computed(() => {
  if (props.message !== 'This module is queued for API rollout. Functional screens are enabled for Grades, Classes, and Staff first.') {
    return props.message
  }

  if (ui.language === 'si') {
    return 'මෙම මොඩියුලය API නිකුතුව සඳහා පෝලිමේ ඇත. දැනට Grades, Classes, සහ Staff තිර පමණක් ක්‍රියාත්මක වේ.'
  }

  if (ui.language === 'ta') {
    return 'இந்த தொகுதி API வெளியீட்டுக்காக வரிசையில் உள்ளது. தற்போது Grades, Classes மற்றும் Staff திரைகள் மட்டுமே செயல்படுகின்றன.'
  }

  return props.message
})
</script>
