import auth from '../services/authService'

export function requireAuth(to, from, next) {
  if (!auth.isAuthenticated()) {
    next({ path: '/login', query: { redirect: to.fullPath } })
    return
  }
  // Validate token with backend; if invalid, logout and redirect
  auth.me().then(() => {
    next()
  }).catch(() => {
    auth.logout().finally(() => {
      next({ path: '/login', query: { redirect: to.fullPath } })
    })
  })
}

export function redirectIfAuthenticated(to, from, next) {
  if (auth.isAuthenticated()) {
    try {
      const user = auth.getUser()
      if (user && user.role === 'super_admin') {
        next({ path: '/superadmin' })
      } else {
        next({ path: '/maps' })
      }
    } catch (_) {
      next({ path: '/maps' })
    }
  } else {
    next()
  }
}


