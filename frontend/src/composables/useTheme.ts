import { ref } from 'vue'

const THEME_KEY = 'fone-ninja-theme'

const dark = ref(false)
let initialized = false

function apply(value: boolean) {
  dark.value = value
  document.documentElement.classList.toggle('dark', value)
  localStorage.setItem(THEME_KEY, value ? 'dark' : 'light')
}

function initTheme() {
  if (initialized) return
  initialized = true
  const stored = localStorage.getItem(THEME_KEY)
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
  apply(stored ? stored === 'dark' : prefersDark)
}

export function useTheme() {
  return {
    dark,
    toggle: () => apply(!dark.value),
    initTheme,
  }
}
