import axios from 'axios'

const API_BASE = process.env.VUE_APP_API_BASE || 'https://isuecampusmap.site/backend/api'
const TOKEN_KEY = 'admin_token'
const USER_KEY = 'admin_user'

// Attach token to axios when present
const instance = axios.create({ baseURL: API_BASE })
instance.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Propagate admin identity for activity logs
  try {
    const rawUser = localStorage.getItem(USER_KEY)
    if (rawUser) {
      const user = JSON.parse(rawUser)
      if (user && (user.name || user.email)) {
        config.headers['X-Admin-Name'] = user.name || user.email
      }
      if (user && (user.id || user.admin_id)) {
        config.headers['X-Admin-Id'] = user.id || user.admin_id
      }
    }
  } catch (_) { /* ignore header enrichment errors */ }
  return config
})

// Handle 401 responses - don't auto-logout for login attempts
instance.interceptors.response.use(
  (response) => response,
  (error) => {
    // Don't auto-logout for login/OTP endpoints
    if (error.config?.url?.includes('/auth/login') || 
        error.config?.url?.includes('/auth/otp/') ||
        error.config?.url?.includes('/auth/logout')) {
      return Promise.reject(error)
    }
    
    // For other 401s, clear auth and redirect
    if (error.response?.status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem(USER_KEY)
      // Don't redirect on login page
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
    }
    
    return Promise.reject(error)
  }
)

export default {
  async login({ email, password }) {
    const { data } = await instance.post('/auth/login', { email, password })
    localStorage.setItem(TOKEN_KEY, data.token)
    localStorage.setItem(USER_KEY, JSON.stringify(data.user))
    return data
  },
  async me() {
    const { data } = await instance.get('/auth/me')
    localStorage.setItem(USER_KEY, JSON.stringify(data))
    return data
  },
  async logout() {
    try { await instance.post('/auth/logout') } catch (e) { /* ignore */ }
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
  },
  isAuthenticated() {
    return !!localStorage.getItem(TOKEN_KEY)
  },
  getUser() {
    const raw = localStorage.getItem(USER_KEY)
    return raw ? JSON.parse(raw) : null
  }
}


