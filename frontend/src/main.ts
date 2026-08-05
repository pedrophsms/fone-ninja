import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './style.css'
import '@fontsource-variable/newsreader'
import '@fontsource-variable/schibsted-grotesk'
import '@fontsource-variable/spline-sans-mono'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')
