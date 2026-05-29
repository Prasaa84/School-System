<template>
  <button
    type="button"
    class="inline-flex items-center gap-1 transition hover:text-slate-900"
    :class="alignClass"
    @click="$emit('toggle', field)"
  >
    <span>{{ label }}</span>
    <span class="text-xs leading-none" :class="activeClass">{{ arrow }}</span>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  label: string
  field: string
  arrow: string
  activeClass: string
  align?: 'left' | 'center'
}>(), {
  align: 'left',
})

defineEmits<{
  toggle: [field: string]
}>()

const alignClass = computed(() => (
  props.align === 'center' ? 'justify-center text-center' : 'text-left'
))
</script>
