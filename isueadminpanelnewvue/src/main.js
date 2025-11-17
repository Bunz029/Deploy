import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import axios from 'axios'
// Removed global Tailwind import to prevent style leaks into non-Tailwind pages

// Configure axios
axios.defaults.baseURL = process.env.VUE_APP_API_BASE || 'https://isuecampusmap.site/backend/api'
axios.defaults.headers.common['Accept'] = 'application/json'
axios.defaults.withCredentials = false

// Attach admin identity headers globally so backend logs correct admin instead of "System"
axios.interceptors.request.use((config) => {
  try {
    const raw = localStorage.getItem('admin_user')
    if (raw) {
      const user = JSON.parse(raw)
      if (user) {
        if (user.name || user.email) {
          config.headers['X-Admin-Name'] = user.name || user.email
        }
        if (user.id || user.admin_id) {
          config.headers['X-Admin-Id'] = user.id || user.admin_id
        }
      }
    }
  } catch (e) { /* ignore header enrichment errors */ }
  
  // Ensure Authorization header is present for all API calls
  try {
    const token = localStorage.getItem('admin_token')
    if (token && !config.headers.Authorization) {
      config.headers.Authorization = `Bearer ${token}`
    }
  } catch (_) { /* no-op */ }

  // Set Content-Type to application/json only if it's not FormData
  // FormData requests should let the browser set multipart/form-data with boundary
  if (!(config.data instanceof FormData)) {
    config.headers['Content-Type'] = 'application/json'
  }
  
  return config
})

// Auto-logout on 401 responses (but not for auth endpoints)
axios.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error?.response?.status
    const url = error?.config?.url || ''
    
    // Don't auto-logout for auth endpoints (login, otp, logout)
    if (status === 401 && !url.includes('/auth/')) {
      try {
        const { default: auth } = await import('./services/authService')
        await auth.logout()
      } catch (_) { /* no-op */ }
      try { window?.location?.assign('/login') } catch (_) { /* no-op */ }
    }
    return Promise.reject(error)
  }
)

// Create Vue app
const app = createApp(App)

// Use router
app.use(router)

// Mount app
app.mount('#app')
