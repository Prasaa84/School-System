import { computed } from 'vue'
import { useUiStore, type UiLanguage } from '../stores/ui'

export interface LocalizedText<T> {
  en: T
  si: T
  ta: T
}

export const pickLocalizedText = <T>(language: UiLanguage, text: LocalizedText<T>): T => {
  return text[language] ?? text.en
}

export const useLocalizedText = <T>(text: LocalizedText<T>) => {
  const ui = useUiStore()
  return computed(() => pickLocalizedText(ui.language, text))
}
