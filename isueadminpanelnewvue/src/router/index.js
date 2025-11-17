import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/Dashboard.vue'
import MapEditorView from '../views/MapEditor.vue'
import BuildingListView from '../views/BuildingList.vue'
import TrashBin from '../views/TrashBin.vue'
import MapDesigner from '../views/MapDesigner.vue'
import Login from '../views/Login.vue'
import { requireAuth, redirectIfAuthenticated } from './authGuard'
import SuperAdmin from '../views/SuperAdmin.vue'
import AcceptInvite from '../views/AcceptInvite.vue'
const ForgotPassword = () => import('../views/ForgotPassword.vue')
const ResetPassword = () => import('../views/ResetPassword.vue')

const routes = [
  // Login route - default landing page
  {
    path: '/',
    name: 'Login',
    component: Login,
    beforeEnter: redirectIfAuthenticated
  },
  {
    path: '/login',
    name: 'LoginPage',
    component: Login,
    beforeEnter: redirectIfAuthenticated
  },
  // Protected admin routes (will redirect to login for now)
  {
    path: '/superadmin',
    name: 'SuperAdmin',
    component: SuperAdmin,
    beforeEnter: requireAuth
  },
  {
    path: '/accept-invite/:token',
    name: 'AcceptInvite',
    component: AcceptInvite
  },
  // Backward-compatible path used in some emails
  {
    path: '/superadmin/accept-invite/:token',
    name: 'AcceptInviteLegacy',
    component: AcceptInvite
  },
  {
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: ForgotPassword,
    beforeEnter: redirectIfAuthenticated
  },
  {
    path: '/reset-password/:token',
    name: 'ResetPassword',
    component: ResetPassword,
    beforeEnter: redirectIfAuthenticated
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: DashboardView,
    beforeEnter: requireAuth
  },
  {
    path: '/maps',
    name: 'Maps',
    component: MapEditorView,
    beforeEnter: requireAuth
  },
  {
    path: '/buildings',
    name: 'Buildings',
    component: BuildingListView,
    beforeEnter: requireAuth
  },
  {
    path: '/trash',
    name: 'Trash',
    component: TrashBin,
    beforeEnter: requireAuth
  },
  {
    path: '/map-designer',
    name: 'MapDesigner',
    component: MapDesigner,
    beforeEnter: requireAuth
  }
]

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
})

export default router 