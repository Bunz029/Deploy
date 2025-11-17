<template>
  <div class="admin-app">
    <!-- Hide navbar on login page -->
    <nav v-if="!isLoginPage" class="navbar">
      <div class="navbar-brand">
        <h1>ISU-E Campus Map Admin</h1>
      </div>
      <div class="navbar-menu">
        <router-link v-if="isSuperAdmin" to="/superadmin" class="nav-item" :class="{ active: $route.path === '/superadmin' }">
          <span class="nav-icon">👥</span>
          <span class="nav-text">Admin Management</span>
        </router-link>
        <router-link v-if="isSuperAdmin" to="/maps" class="nav-item" :class="{ active: $route.path === '/maps' }">
          <span class="nav-icon">🗺️</span>
          <span class="nav-text">Map Editor</span>
        </router-link>
      </div>
      <div class="navbar-user">
        <span class="user-info">
          {{ currentUser?.name || 'User' }}
          <span class="role-badge" :class="{ super: isSuperAdmin }">{{ roleBadge }}</span>
        </span>
        <button @click="showLogoutConfirmation = true" class="logout-btn">
          <span class="logout-icon">🚪</span>
          Logout
        </button>
      </div>
    </nav>

    <main :class="['main-content', { 'no-padding': isLoginPage }]">
      <router-view></router-view>
    </main>

    <!-- Toast Notifications -->
    <ToastNotification ref="toast" />

    <!-- Simple Logout Confirmation Modal -->
    <div v-if="showLogoutConfirmation" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">
      <div style="background: white; border-radius: 12px; padding: 24px; max-width: 400px; width: 90%; box-shadow: 0 20px 25px rgba(0, 0, 0, 0.15);">
        <div style="text-align: center; margin-bottom: 20px;">
          <div style="font-size: 48px; margin-bottom: 16px;">⚠️</div>
          <h3 style="margin: 0; font-size: 18px; color: #1f2937;">Confirm Logout</h3>
        </div>
        
        <p style="color: #6b7280; text-align: center; margin-bottom: 24px; line-height: 1.5;">
          Are you sure you want to logout from the admin panel?
        </p>
        
        <div style="display: flex; gap: 12px;">
          <button @click="showLogoutConfirmation = false" style="flex: 1; padding: 12px 20px; background: #6b7280; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
            Cancel
          </button>
          <button @click="handleLogout" style="flex: 1; padding: 12px 20px; background: #dc3545; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
            Logout
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ToastNotification from './components/ToastNotification.vue'

export default {
  name: 'App',
  components: {
    ToastNotification
  },
  data() {
    return { 
      currentUser: null,
      showLogoutConfirmation: false
    }
  },
  computed: {
    isLoginPage() {
      return this.$route.path === '/' || 
             this.$route.path === '/login' || 
             this.$route.path.startsWith('/accept-invite/') ||
             this.$route.path.startsWith('/reset-password/') ||
             this.$route.path === '/forgot-password'
    },
    isSuperAdmin() {
      return this.currentUser && this.currentUser.role === 'super_admin'
    },
    roleBadge() {
      return this.isSuperAdmin ? 'Super Admin' : 'Admin'
    }
  },
  async created() {
    if (!this.isLoginPage) {
      try {
        const { default: auth } = await import('./services/authService')
        await this.refreshUser(auth)
      } catch (e) { console.warn('Auth initialization failed', e) }
    }
  },
  methods: {
    async refreshUser(authModule) {
      try {
        const auth = authModule || (await import('./services/authService')).default
        if (auth.isAuthenticated()) {
          this.currentUser = auth.getUser()
          try { await auth.me(); this.currentUser = auth.getUser() } catch (e) { /* ignore */ }
        } else {
          this.currentUser = null
        }
      } catch (_) { /* ignore */ }
    },
    async handleLogout() {
      const { default: auth } = await import('./services/authService')
      await auth.logout()
      this.showLogoutConfirmation = false
      this.currentUser = null
      this.$router.push('/login')
    }
  },
  watch: {
    async $route() {
      try {
        const { default: auth } = await import('./services/authService')
        await this.refreshUser(auth)
      } catch (_) { /* no-op */ }
    }
  }
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700&display=swap');

html, body, #app {
  height: 100%;
  margin: 0;
}
.admin-app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.navbar {
  background: #2dbb74;
  padding: 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: white;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.navbar-brand h1 {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
}

.navbar-menu {
  display: flex;
  gap: 1rem;
}

.nav-item {
  color: white;
  text-decoration: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  transition: background-color 0.3s;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.9em;
  font-weight: 500;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.12);
}

.nav-item.active {
  background: rgba(255, 255, 255, 0.2);
  font-weight: 600;
}

.nav-icon {
  font-size: 1em;
  line-height: 1;
}

.nav-text {
  font-size: 0.9em;
}

.navbar-user {
  display: flex;
  align-items: center;
  gap: 15px;
}

.user-info {
  color: #eafff2;
  font-size: 0.9em;
  font-weight: 500;
}
.role-badge {
  margin-left: 10px;
  background: rgba(255,255,255,0.2);
  color: #fff;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.75em;
}
.role-badge.super {
  background: #0ea5e9;
}

.logout-btn {
  background: #dc3545;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.9em;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.3s ease;
}

.logout-btn:hover {
  background: #c82333;
  transform: translateY(-1px);
}

.logout-icon {
  font-size: 1em;
}

.main-content {
  flex: 1;
  padding: 0;
  background: transparent;
}

.main-content.no-padding {
  padding: 0;
  background: none;
}

/* Special handling for fullscreen components */
.main-content .map-3d-editor {
  margin: 0;
  height: calc(100vh - 80px);
}
</style>