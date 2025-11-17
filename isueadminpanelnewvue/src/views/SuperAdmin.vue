<template>
  <div class="sa-container">
    <h2 class="sa-title">Superadmin · Admin Management</h2>
    <div class="sa-toolbar">
      <input v-model="search" @input="debouncedSearch" placeholder="Search name/email" class="sa-search-input" />
      <select v-model="activeFilter" @change="onFilterChange" class="sa-select">
        <option value="">All</option>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
      <button class="btn btn-secondary" @click="fetchAdmins">Search</button>
      <button class="btn btn-primary" @click="showCreate=true">Invite/Create Admin</button>
    </div>

    <table class="sa-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Active</th>
          <th>Date Created</th>
          <th>Created By</th>
          <th>Date Updated</th>
          <th>Updated By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="a in admins" :key="a.id">
          <td>{{ formatAdminName(a) }}</td>
          <td>{{ a.email }}</td>
          <td>
            <span class="badge" :class="a.role==='super_admin' ? 'badge-blue' : 'badge-gray'">{{ a.role }}</span>
          </td>
          <td>
            <span class="badge" :class="a.is_active ? 'badge-green' : 'badge-red'">{{ a.is_active ? 'Yes' : 'No' }}</span>
          </td>
          <td class="date-cell">{{ formatDate(a.created_at) }}</td>
          <td class="user-cell">{{ a.created_by_name || formatUserName(a.created_by) || 'System' }}</td>
          <td class="date-cell">{{ formatDate(a.updated_at) }}</td>
          <td class="user-cell">{{ a.updated_by_name || formatUserName(a.updated_by) || 'System' }}</td>
          <td class="actions-cell">
            <button 
              class="btn btn-slate" 
              :class="{ 'btn-disabled': isCurrentUser(a) }"
              :disabled="isCurrentUser(a)"
              :title="isCurrentUser(a) ? 'Cannot edit your own account' : 'Edit profile'" 
              @click="edit(a)"
            >
              Edit
            </button>
            <button 
              class="btn" 
              :class="[a.is_active ? 'btn-yellow' : 'btn-green', { 'btn-disabled': isCurrentUser(a) }]"
              :disabled="isCurrentUser(a)"
              :title="isCurrentUser(a) ? 'Cannot modify your own account' : (a.is_active ? 'Deactivate account' : 'Reactivate account')" 
              @click="toggle(a)"
            >
              {{ a.is_active ? 'Deactivate' : 'Reactivate' }}
            </button>
            <button 
              class="btn btn-blue" 
              :class="{ 'btn-disabled': isCurrentUser(a) }"
              :disabled="isCurrentUser(a)"
              :title="isCurrentUser(a) ? 'Cannot reset your own password' : 'Force password reset'" 
              @click="resetPassword(a)"
            >
              Reset Password
            </button>
            <button 
              class="btn btn-amber" 
              :class="{ 'btn-disabled': isCurrentUser(a) }"
              :disabled="isCurrentUser(a)"
              :title="isCurrentUser(a) ? 'Cannot reset your own 2FA' : 'Clear two-factor setup'" 
              @click="reset2FA(a)"
            >
              Reset 2FA
            </button>
            <button class="btn btn-violet" title="View active sessions" @click="viewSessions(a)">Sessions</button>
            <button 
              class="btn btn-red" 
              :class="{ 'btn-disabled': a.is_active || a.role==='super_admin' || isCurrentUser(a) }" 
              :disabled="a.is_active || a.role==='super_admin' || isCurrentUser(a)" 
              @click="promptDelete(a)" 
              :title="isCurrentUser(a) ? 'Cannot delete your own account' : (a.is_active ? 'Deactivate first' : (a.role==='super_admin' ? 'Cannot delete superadmin' : ''))"
            >
              Delete
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Sessions Modal -->
    <div v-if="showSessions" class="sa-modal-backdrop">
      <div class="sa-modal sa-modal-wide">
        <div class="sa-card-head">
          <h3>Sessions for {{ sessionsFor ? sessionsFor.email : '' }}</h3>
          <div class="sa-actions">
            <button class="btn btn-red" @click="revokeAllSessions">Revoke All</button>
            <button class="btn btn-slate" @click="closeSessions">Close</button>
          </div>
        </div>
        <table class="sa-table sa-table-modal">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Created</th>
              <th>Last Used</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="sessionsLoading">
              <td colspan="5" class="text-center text-muted">Loading sessions…</td>
            </tr>
            <tr v-else-if="!sessions || sessions.length === 0">
              <td colspan="5" class="text-center text-muted">No active sessions</td>
            </tr>
            <tr v-for="s in sessions" :key="s.id">
              <td>{{ s.id }}</td>
              <td>{{ s.name || 'admin-token' }}</td>
              <td>{{ formatTs(s.created_at) }}</td>
              <td>{{ formatTs(s.last_used_at) || '—' }}</td>
              <td>
                <button class="btn btn-red" @click="revokeSession(s.id)">Revoke</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Invite Admin Modal -->
    <div v-if="showCreate" class="sa-modal-backdrop">
      <div class="sa-modal">
        <h3>Create / Invite Admin</h3>
        <div class="sa-form-row">
          <input class="input" v-model="form.first_name" placeholder="First Name" />
          <input class="input" v-model="form.last_name" placeholder="Last Name" />
        </div>
        <div class="sa-form-row">
          <input class="input" v-model="form.email" placeholder="Email" />
        </div>
        <div class="sa-form-row">
          <select class="input" v-model="form.role">
            <option value="admin">admin</option>
            <option value="super_admin">super_admin</option>
          </select>
          <div class="password-field-container">
            <input 
              class="input" 
              v-model="form.password" 
              placeholder="Password (optional, min 12 chars)" 
              @input="validatePassword"
            />
            <div v-if="passwordError" class="password-error">
              <span class="error-icon">⚠️</span>
              {{ passwordError }}
            </div>
            <div v-if="form.password && !passwordError && form.password.length >= 12" class="password-success">
              <span class="success-icon">✓</span>
              Password meets requirements
            </div>
          </div>
        </div>
        <div v-if="error" class="error-message">{{ error }}</div>
        <div class="actions">
          <button class="btn btn-secondary" @click="showCreate=false">Cancel</button>
          <button class="btn btn-primary" @click="create">Create Now</button>
          <button class="btn btn-green" @click="sendInvite">Send Invite</button>
        </div>
      </div>
    </div>

    <!-- Invite Result Modal -->
    <div v-if="showInviteResult" class="sa-modal-backdrop">
      <div class="sa-modal">
        <div class="sa-modal-head">
          <div class="icon">{{ inviteResult?.success ? '✅' : '⚠️' }}</div>
          <div>
            <h3>{{ inviteResult?.success ? 'Invitation Sent' : 'Invitation Issue' }}</h3>
            <div class="subtitle" v-if="inviteResult?.email">Recipient: {{ inviteResult.email }}</div>
          </div>
        </div>
        <div class="sa-modal-body">
          <div v-if="inviteResult?.success">The invitation email was sent successfully. The recipient can now accept the invite and create their account.</div>
          <div v-else>
            Invite was created, but the email failed to send. You may copy the link from activity logs or try again.
            <div v-if="inviteResult?.error" class="error-message" style="margin-top:10px;">{{ inviteResult.error }}</div>
          </div>
        </div>
        <div class="actions">
          <button class="btn btn-primary" @click="closeInviteResult">OK</button>
        </div>
      </div>
    </div>

    <!-- Password Reset Modal -->
    <div v-if="showResetPass" class="sa-modal-backdrop">
      <div class="sa-modal">
        <h3>Reset Password</h3>
        <div class="modal-subtitle">{{ resetTarget ? resetTarget.email : '' }}</div>
        <div class="sa-form-row">
          <input class="input" type="password" v-model="resetForm.new_password" placeholder="New password (min 12 chars)" />
        </div>
        <div v-if="resetError" class="error-message">{{ resetError }}</div>
        <div class="actions">
          <button class="btn btn-secondary" @click="cancelResetPassword">Cancel</button>
          <button class="btn btn-primary" @click="confirmResetPassword">Reset</button>
        </div>
      </div>
    </div>

    <!-- 2FA Reset Modal -->
    <div v-if="showReset2FA" class="sa-modal-backdrop">
      <div class="sa-modal">
        <h3>Reset 2FA</h3>
        <div class="modal-subtitle">Are you sure you want to reset 2FA for {{ reset2FATarget ? reset2FATarget.email : '' }}?</div>
        <div class="actions">
          <button class="btn btn-secondary" @click="cancelReset2FA">Cancel</button>
          <button class="btn btn-red" @click="confirmReset2FA">Reset 2FA</button>
        </div>
      </div>
    </div>

    <!-- Edit Admin Modal -->
    <div v-if="editing" class="sa-modal-backdrop">
      <div class="sa-modal">
        <h3>Edit Admin</h3>
        <div class="sa-form-row">
          <input 
            class="input" 
            v-model="editForm.first_name" 
            placeholder="First Name" 
            :disabled="isCurrentUser(editing)"
            :title="isCurrentUser(editing) ? 'Cannot modify your own name' : ''"
          />
          <input 
            class="input" 
            v-model="editForm.last_name" 
            placeholder="Last Name" 
            :disabled="isCurrentUser(editing)"
            :title="isCurrentUser(editing) ? 'Cannot modify your own name' : ''"
          />
        </div>
        <div class="sa-form-row">
          <input 
            class="input" 
            v-model="editForm.email" 
            placeholder="Email" 
            :disabled="isCurrentUser(editing)"
            :title="isCurrentUser(editing) ? 'Cannot modify your own email' : ''"
          />
        </div>
        <div class="sa-form-row">
          <select 
            class="input" 
            v-model="editForm.role"
            :disabled="isCurrentUser(editing)"
            :title="isCurrentUser(editing) ? 'Cannot change your own role' : ''"
          >
            <option value="admin">admin</option>
            <option value="super_admin">super_admin</option>
          </select>
          <label class="checkbox-label">
            <input 
              type="checkbox" 
              v-model="editForm.is_active" 
              :disabled="isCurrentUser(editing)"
              :title="isCurrentUser(editing) ? 'Cannot modify your own account status' : ''"
            /> 
            Active
          </label>
        </div>
        <div v-if="isCurrentUser(editing)" class="warning-message">
          <span class="warning-icon">⚠️</span>
          You cannot modify your own account details, role, or status. Ask another super admin to make changes for you.
        </div>
        <div v-if="error" class="error-message">{{ error }}</div>
        <div class="actions">
          <button class="btn btn-secondary" @click="cancelEdit">Cancel</button>
          <button class="btn btn-primary" @click="saveEdit">Update</button>
        </div>
      </div>
    </div>

    <!-- Warning/Confirm Modal -->
    <div v-if="showWarn" class="sa-modal-backdrop">
      <div class="sa-modal sa-modal-warning">
        <div class="sa-modal-head">
          <div class="icon">⚠️</div>
          <div>
            <h3>{{ warnTitle }}</h3>
            <div class="subtitle" v-if="warnSub">{{ warnSub }}</div>
          </div>
        </div>
        <div class="sa-modal-body">
          <div class="warn-text">{{ warnMessage }}</div>
          <ul v-if="warnBullets && warnBullets.length" class="sa-bullets">
            <li v-for="(b, i) in warnBullets" :key="i">{{ b }}</li>
          </ul>
        </div>
        <div class="actions">
          <button class="btn btn-secondary" @click="closeWarn">{{ warnConfirmable ? 'Cancel' : 'Close' }}</button>
          <button v-if="warnConfirmable" class="btn btn-red" @click="confirmWarn">Confirm</button>
        </div>
      </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div v-if="showDelete" class="sa-modal-backdrop">
      <div class="sa-modal sa-modal-warning">
        <div class="sa-modal-head">
          <div class="icon">🗑️</div>
          <div>
            <h3>Delete Admin</h3>
            <div class="subtitle">This action permanently removes the admin account.</div>
          </div>
        </div>
        <div class="sa-modal-body">
          <div class="warn-text">Type the admin email to confirm deletion:</div>
          <div class="sa-form-row"><input class="input" v-model="deleteConfirm" placeholder="email@domain.com" /></div>
          <ul class="sa-bullets">
            <li>Account must be deactivated before deletion</li>
            <li>All sessions will be revoked</li>
            <li>This cannot be undone</li>
          </ul>
        </div>
        <div class="actions">
          <button class="btn btn-secondary" @click="cancelDelete">Cancel</button>
          <button class="btn btn-red" :class="{ 'btn-disabled': deleteConfirm !== (deleteTarget? deleteTarget.email : '') }" :disabled="deleteConfirm !== (deleteTarget? deleteTarget.email : '')" @click="confirmDelete">Delete</button>
        </div>
      </div>
    </div>

    <!-- Toast Notifications -->
    <ToastNotification ref="toast" />

    <!-- Activity Log Drawer -->
    <ActivityLogDrawer @show-toast="showToast" />
  </div>

</template>



<script>
import axios from 'axios'
import ToastNotification from '../components/ToastNotification.vue'
import ActivityLogDrawer from '../components/ActivityLogDrawer.vue'

export default {
  name: 'SuperAdmin',
  components: {
    ToastNotification,
    ActivityLogDrawer
  },
  data(){
    return {
      admins: [],
      search: '',
      activeFilter: '',
      showCreate: false,
      form: { first_name: '', last_name: '', email: '', role: 'admin', password: '' },
      editing: null,
      editForm: { first_name: '', last_name: '', email: '', role: 'admin', is_active: true },
      error: '',
      passwordError: '',
      sessionsFor: null,
      sessions: [],
      showSessions: false,
      showResetPass: false,
      resetTarget: null,
      resetForm: { new_password: '' },
      resetError: '',
      showReset2FA: false,
      reset2FATarget: null,
      searchDebounce: null,
      // warning modal
      showWarn: false,
      warnTitle: '',
      warnMessage: '',
      warnConfirmable: false,
      warnAction: null,
      warnTarget: null,
      warnSub: '',
      warnBullets: []
      ,
      showDelete: false,
      deleteTarget: null,
      deleteConfirm: '',
      // invite result modal
      showInviteResult: false,
      inviteResult: { success: false, email: '', error: '' },
      // current user info for restrictions
      currentUser: null
    }
  },
  async created(){
    await this.getCurrentUser()
    await this.fetchAdmins()
  },
  computed: {
    isCurrentUser() {
      return (admin) => {
        return this.currentUser && admin.id === this.currentUser.id
      }
    },
    
    // Format admin name for display
    formatAdminName() {
      return (admin) => {
        // If we have first_name and last_name, format them
        if (admin.first_name && admin.last_name) {
          return this.formatName(admin.first_name, admin.last_name)
        }
        // If we have the old name field, check if it's already formatted
        if (admin.name) {
          // If it contains a comma, it's already in "Last, First" format
          if (admin.name.includes(', ')) {
            return admin.name
          }
          // Otherwise, it's probably just a first name or full name
          return admin.name
        }
        return 'Unknown'
      }
    }
  },
  methods:{
    async getCurrentUser() {
      try {
        const { default: auth } = await import('../services/authService')
        this.currentUser = auth.getUser()
      } catch (e) {
        console.warn('Failed to get current user:', e)
        this.currentUser = null
      }
    },
    async fetchAdmins(){
      try {
        const params = {}
        if (this.search) params.search = this.search
        if (this.activeFilter !== '') params.is_active = this.activeFilter
        const { data } = await axios.get('/superadmin/admins', { params })
        this.admins = data.data || data
        
        // Tracking fields are working correctly
      } catch (e) {
        console.error('Fetch admins error:', e)
        this.error = 'Failed to fetch admins'
      }
    },
    debouncedSearch(){
      if (this.searchDebounce) clearTimeout(this.searchDebounce)
      this.searchDebounce = setTimeout(() => { this.fetchAdmins() }, 300)
    },
    onFilterChange(){
      this.fetchAdmins()
    },
    validatePassword() {
      const password = this.form.password
      if (password.length === 0) {
        this.passwordError = ''
        return
      }
      
      if (password.length < 12) {
        this.passwordError = 'Password must be at least 12 characters long'
        return
      }
      
      this.passwordError = ''
    },
    
    async create(){
      this.error = ''
      
      // Validate password if provided
      if (this.form.password) {
        this.validatePassword()
        if (this.passwordError) {
          this.error = 'Please fix password requirements before creating admin.'
          return
        }
      }
      
      // Format the data before sending - combine names for backend compatibility
      const formattedData = {
        name: this.formatName(this.form.first_name, this.form.last_name),
        email: this.formatEmail(this.form.email),
        role: this.form.role,
        password: this.form.password
      }
      
      // Add tracking fields if current user is available
      if (this.currentUser) {
        formattedData.created_by = this.currentUser.id
        formattedData.created_by_name = this.formatAdminName(this.currentUser)
      }
      
      // Sending tracking data to backend
      
      try {
        await axios.post('/superadmin/admins', formattedData)
        // Admin created successfully
        this.showCreate = false
        this.form = { first_name: '', last_name: '', email: '', role: 'admin', password: '' }
        this.passwordError = ''
        await this.fetchAdmins()
      } catch (e) {
        console.error('Create admin error:', e)
        this.error = e?.response?.data?.message || 'Failed to create admin'
      }
    },
    async sendInvite(){
      this.error = ''
      try {
        console.log('🚀 Sending invite to:', this.form.email, 'as role:', this.form.role)
        const inviteData = { 
          name: this.formatName(this.form.first_name, this.form.last_name),
          email: this.formatEmail(this.form.email), 
          role: this.form.role
        }
        
        // Add tracking fields if current user is available
        if (this.currentUser) {
          inviteData.created_by = this.currentUser.id
          inviteData.created_by_name = this.formatAdminName(this.currentUser)
        }
        
        const { data } = await axios.post('/superadmin/invites', inviteData)
        
        console.log('📧 Backend response:', data)
        
        // Show modal result instead of alert
        this.inviteResult = {
          success: !!data.email_sent,
          email: this.form.email,
          error: data.email_error || ''
        }
        this.showInviteResult = true
        
        this.showCreate = false
        this.form = { first_name: '', last_name: '', email: '', role: 'admin', password: '' }
        await this.fetchAdmins()
        
      } catch (e) {
        console.error('❌ Send invite API Error:', e)
        this.error = e?.response?.data?.message || 'Failed to send invite'
      }
    },
    closeInviteResult(){
      this.showInviteResult = false
      this.inviteResult = { success: false, email: '', error: '' }
    },
    edit(a){
      // Prevent editing current user's account
      if (this.isCurrentUser(a)) {
        this.error = 'You cannot edit your own account. Ask another super admin to make changes for you.'
        return
      }
      this.editing = a
      // Handle both new format (first_name, last_name) and old format (name)
      let firstName = a.first_name || ''
      let lastName = a.last_name || ''
      
      // If we only have the old name field, try to split it
      if (!firstName && !lastName && a.name) {
        const nameParts = a.name.split(', ')
        if (nameParts.length === 2) {
          lastName = nameParts[0]
          firstName = nameParts[1]
        } else {
          // Fallback: put everything in first name
          firstName = a.name
          lastName = ''
        }
      }
      
      this.editForm = { 
        first_name: firstName, 
        last_name: lastName, 
        email: a.email, 
        role: a.role, 
        is_active: !!a.is_active 
      }
    },
    cancelEdit(){
      this.editing = null
      this.error = ''
    },
    async saveEdit(){
      if (!this.editing) return
      this.error = ''
      
      // Format the data before sending - combine names for backend compatibility
      const formattedData = {
        name: this.formatName(this.editForm.first_name, this.editForm.last_name),
        email: this.formatEmail(this.editForm.email),
        role: this.editForm.role,
        is_active: this.editForm.is_active
      }
      
      // Add tracking fields if current user is available
      if (this.currentUser) {
        formattedData.updated_by = this.currentUser.id
        formattedData.updated_by_name = this.formatAdminName(this.currentUser)
      }
      
      // Sending tracking data to backend
      
      try {
        await axios.put(`/superadmin/admins/${this.editing.id}`, formattedData)
        // Admin updated successfully
        this.editing = null
        await this.fetchAdmins()
      } catch (e) {
        console.error('Update admin error:', e)
        this.error = e?.response?.data?.message || 'Failed to update admin'
      }
    },
    async toggle(a){
      // Prevent modifying current user's account
      if (this.isCurrentUser(a)) {
        this.error = 'You cannot modify your own account status. Ask another super admin to make changes for you.'
        return
      }
      
      // Pre-check: if attempting to deactivate last superadmin, show warning and block
      if (a.role === 'super_admin' && a.is_active) {
        const activeSupers = (this.admins || []).filter(x => x.role === 'super_admin' && x.is_active).length
        if (activeSupers <= 1) {
          this.warnTitle = 'Cannot deactivate the last superadmin'
          this.warnSub = 'This action would lock everyone out of Superadmin controls.'
          this.warnMessage = 'At least one active superadmin must remain to manage admin accounts and recover access.'
          this.warnBullets = [
            'Superadmin is required to create, edit, or reactivate admins',
            'Prevents accidental lockout of admin management',
            'Add another superadmin first, then deactivate this one'
          ]
          this.warnConfirmable = false
          this.warnTarget = a
          this.showWarn = true
          return
        }
        // Otherwise, confirm deactivation
        this.warnTitle = 'Deactivate superadmin?'
        this.warnSub = 'You can reactivate later from Superadmin > Admin Management.'
        this.warnMessage = `This removes superadmin privileges for ${a.email}.`
        this.warnBullets = [
          'All existing sessions for this user remain unless you revoke them',
          'They will no longer access Superadmin features'
        ]
        this.warnConfirmable = true
        this.warnAction = async () => {
          await axios.patch(`/superadmin/admins/${a.id}/toggle-active`)
          await this.fetchAdmins()
        }
        this.warnTarget = a
        this.showWarn = true
        return
      }
      // For non-superadmin or reactivation, proceed without confirm
      try{
        await axios.patch(`/superadmin/admins/${a.id}/toggle-active`)
        await this.fetchAdmins()
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to toggle status'
      }
    },
    closeWarn(){
      this.showWarn = false
      this.warnConfirmable = false
      this.warnAction = null
      this.warnTarget = null
      this.warnSub = ''
      this.warnBullets = []
    },
    async confirmWarn(){
      if (!this.warnConfirmable || !this.warnAction) { this.closeWarn(); return }
      try { await this.warnAction() } catch (e) { this.error = e?.response?.data?.message || 'Action failed' }
      this.closeWarn()
    },
    promptDelete(a){
      // Prevent deleting current user's account
      if (this.isCurrentUser(a)) {
        this.error = 'You cannot delete your own account. Ask another super admin to make changes for you.'
        return
      }
      this.deleteTarget = a
      this.deleteConfirm = ''
      this.showDelete = true
    },
    cancelDelete(){
      this.showDelete = false
      this.deleteTarget = null
      this.deleteConfirm = ''
    },
    async confirmDelete(){
      if (!this.deleteTarget) return
      try {
        await axios.delete(`/superadmin/admins/${this.deleteTarget.id}`)
        this.cancelDelete()
        await this.fetchAdmins()
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to delete admin'
      }
    },
    async viewSessions(a){
      this.sessionsFor = a
      const { data } = await axios.get(`/superadmin/admins/${a.id}/sessions`)
      this.sessions = data
      this.showSessions = true
    },
    async revokeSession(tokenId){
      if (!this.sessionsFor) return
      try {
        await axios.delete(`/superadmin/admins/${this.sessionsFor.id}/sessions/${tokenId}`)
        await this.viewSessions(this.sessionsFor)
      } catch (e) {
        this.error = 'Failed to revoke session'
      }
    },
    async revokeAllSessions(){
      if (!this.sessionsFor) return
      if (!confirm('Revoke all sessions for '+this.sessionsFor.email+'?')) return
      try {
        await axios.delete(`/superadmin/admins/${this.sessionsFor.id}/sessions`)
        await this.viewSessions(this.sessionsFor)
      } catch (e) {
        this.error = 'Failed to revoke all sessions'
      }
    },
    closeSessions(){
      this.sessionsFor = null
      this.sessions = []
      this.showSessions = false
    },
    formatTs(ts){
      if (!ts) return ''
      try { return new Date(ts).toLocaleString() } catch { return ts }
    },
    resetPassword(a){
      // Prevent resetting current user's password
      if (this.isCurrentUser(a)) {
        this.error = 'You cannot reset your own password. Use the "Forgot Password" feature on the login page instead.'
        return
      }
      this.resetTarget = a
      this.resetForm = { new_password: '' }
      this.resetError = ''
      this.showResetPass = true
    },
    async confirmResetPassword(){
      if (!this.resetTarget) return
      this.resetError = ''
      const pwd = (this.resetForm?.new_password || '').trim()
      if (pwd.length < 12) { this.resetError = 'Password must be at least 12 characters.'; return }
      try {
        await axios.post(`/superadmin/admins/${this.resetTarget.id}/reset-password`, { new_password: pwd })
        this.showResetPass = false
        this.resetTarget = null
        this.resetForm = { new_password: '' }
      } catch (e) {
        this.resetError = e?.response?.data?.message || 'Failed to reset password'
      }
    },
    cancelResetPassword(){
      this.showResetPass = false
      this.resetTarget = null
      this.resetForm = { new_password: '' }
      this.resetError = ''
    },
    reset2FA(a){
      // Prevent resetting current user's 2FA
      if (this.isCurrentUser(a)) {
        this.error = 'You cannot reset your own 2FA. Ask another super admin to make changes for you.'
        return
      }
      this.reset2FATarget = a
      this.showReset2FA = true
    },
    async confirmReset2FA(){
      if (!this.reset2FATarget) return
      try {
        const { data } = await axios.post(`/superadmin/admins/${this.reset2FATarget.id}/reset-2fa`)
        this.showReset2FA = false
        this.reset2FATarget = null
        
        // Show success message using toast
        if (data.message) {
          this.$refs.toast?.success('2FA Reset Complete', data.message)
        }
        
        await this.fetchAdmins() // Refresh the list
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to reset 2FA'
      }
    },
    cancelReset2FA(){
      this.showReset2FA = false
      this.reset2FATarget = null
    },
    showToast(type, title, message) {
      // Handle toast notifications from ActivityLogDrawer
      this.$refs.toast?.[type]?.(title, message)
    },
    
    // Name formatting utilities
    formatName(firstName, lastName) {
      if (!firstName || !lastName) return ''
      const formattedFirst = this.toCamelCase(firstName.trim())
      const formattedLast = this.toCamelCase(lastName.trim())
      return `${formattedLast}, ${formattedFirst}`
    },
    
    toCamelCase(str) {
      if (!str) return ''
      return str.toLowerCase().replace(/\b\w/g, l => l.toUpperCase())
    },
    
    formatEmail(email) {
      if (!email) return ''
      return email.toLowerCase().trim()
    },
    
    // Date formatting utilities
    formatDate(dateString) {
      if (!dateString) return '—'
      try {
        const date = new Date(dateString)
        if (isNaN(date.getTime())) return 'Invalid Date'
        return date.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        })
      } catch (e) {
        return 'Invalid Date'
      }
    },
    
    // User name formatting utilities
    formatUserName(user) {
      if (!user) return '—'
      
      // If user is an object with name/email
      if (typeof user === 'object') {
        if (user.name) return user.name
        if (user.email) return user.email
        if (user.first_name && user.last_name) {
          return this.formatName(user.first_name, user.last_name)
        }
        return 'Unknown'
      }
      
      // If user is a string (email or name)
      if (typeof user === 'string') {
        return user
      }
      
      return 'Unknown'
    }
  }
}
</script>

<style>
/* Container and Layout */
.sa-container {
  padding: 24px;
  font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  background-color: #f8fafc;
  min-height: 100vh;
}

.sa-title {
  margin: 0 0 16px;
  font-size: 28px;
  font-weight: 700;
  color: #1e293b;
}

.sa-toolbar {
  margin: 16px 0 24px;
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
}

/* Form Controls */
.sa-search-input, .sa-select, .input {
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.sa-search-input {
  width: 280px;
}

.sa-search-input:focus, .sa-select:focus, .input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Buttons */
.btn {
  padding: 10px 16px;
  border: 1px solid transparent;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary {
  background-color: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.btn-primary:hover {
  background-color: #2563eb;
  border-color: #2563eb;
}

.btn-secondary {
  background-color: #f1f5f9;
  color: #475569;
  border-color: #e2e8f0;
}

.btn-secondary:hover {
  background-color: #e2e8f0;
  color: #334155;
}

.btn-red {
  background-color: #ef4444;
  color: white;
  border-color: #ef4444;
}

.btn-red:hover {
  background-color: #dc2626;
  border-color: #dc2626;
}

.btn-green {
  background-color: #10b981;
  color: white;
  border-color: #10b981;
}

.btn-green:hover {
  background-color: #059669;
  border-color: #059669;
}

.btn-blue {
  background-color: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.btn-blue:hover {
  background-color: #2563eb;
  border-color: #2563eb;
}

.btn-slate {
  background-color: #64748b;
  color: white;
  border-color: #64748b;
}

.btn-slate:hover {
  background-color: #475569;
  border-color: #475569;
}

.btn-yellow {
  background-color: #eab308;
  color: white;
  border-color: #eab308;
}

.btn-yellow:hover {
  background-color: #ca8a04;
  border-color: #ca8a04;
}

.btn-amber {
  background-color: #f59e0b;
  color: white;
  border-color: #f59e0b;
}

.btn-amber:hover {
  background-color: #d97706;
  border-color: #d97706;
}

.btn-violet {
  background-color: #8b5cf6;
  color: white;
  border-color: #8b5cf6;
}

.btn-violet:hover {
  background-color: #7c3aed;
  border-color: #7c3aed;
}

.btn-disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
}

/* Table */
.sa-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.sa-table th {
  background: #f8fafc;
  padding: 16px;
  text-align: left;
  font-weight: 600;
  color: #475569;
  font-size: 14px;
  border-bottom: 1px solid #e2e8f0;
}

.sa-table td {
  padding: 16px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
  font-size: 14px;
}

.sa-table tbody tr:hover {
  background-color: #f8fafc;
}

.actions-cell {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.actions-cell .btn {
  padding: 6px 12px;
  font-size: 12px;
}

/* New column styling */
.date-cell {
  font-size: 12px;
  color: #64748b;
  white-space: nowrap;
  min-width: 120px;
}

.user-cell {
  font-size: 12px;
  color: #475569;
  white-space: nowrap;
  min-width: 100px;
  max-width: 150px;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Badges */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.badge-blue {
  background-color: #dbeafe;
  color: #1d4ed8;
}

.badge-gray {
  background-color: #f1f5f9;
  color: #475569;
}

.badge-green {
  background-color: #dcfce7;
  color: #166534;
}

.badge-red {
  background-color: #fee2e2;
  color: #dc2626;
}

/* Modals */
.sa-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.sa-modal {
  background: white;
  width: 520px;
  max-width: 95vw;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  animation: modalSlideIn 0.2s ease-out;
}

.sa-modal-wide {
  width: 720px;
}

.sa-modal-warning {
  width: 560px;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.sa-modal h3 {
  margin: 0 0 16px;
  font-size: 20px;
  font-weight: 600;
  color: #1e293b;
}

.sa-modal-head {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 16px;
}

.sa-modal-head .icon {
  font-size: 24px;
  line-height: 1;
}

.subtitle {
  color: #64748b;
  font-size: 14px;
  margin-top: 4px;
}

.modal-subtitle {
  color: #64748b;
  font-size: 14px;
  margin-bottom: 16px;
}

.sa-modal-body {
  margin: 16px 0;
  line-height: 1.5;
  color: #475569;
}

.warn-text {
  margin-bottom: 12px;
  color: #374151;
}

.sa-bullets {
  margin: 12px 0 0 20px;
  padding: 0;
  color: #64748b;
}

.sa-bullets li {
  margin: 6px 0;
  font-size: 14px;
}

/* Form Elements */
.sa-form-row {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  align-items: center;
  flex-wrap: wrap;
}

.sa-form-row .input {
  flex: 1;
  min-width: 200px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #475569;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: auto;
  margin: 0;
}

.error-message {
  color: #ef4444;
  font-size: 14px;
  margin-top: 8px;
  padding: 8px 12px;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 6px;
}

.password-field-container {
  flex: 1;
  min-width: 200px;
}

.password-error {
  background: #fee;
  color: #c53030;
  padding: 8px 12px;
  border-radius: 6px;
  border-left: 3px solid #fc8181;
  font-size: 0.85em;
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 5px;
}

.password-success {
  background: #f0fff4;
  color: #2f855a;
  padding: 2px 4px;
  border-radius: 4px;
  border-left: 3px solid #68d391;
  font-size: 0.8em;
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
  min-height: auto;
}

.error-icon {
  font-size: 1em;
}

.success-icon {
  font-size: 1em;
}

.warning-message {
  background: #fef3c7;
  color: #92400e;
  padding: 12px 16px;
  border-radius: 8px;
  border-left: 4px solid #f59e0b;
  font-size: 0.9em;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 16px 0;
}

.warning-icon {
  font-size: 1.1em;
}

/* Actions */
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid #f1f5f9;
}

.sa-actions {
  display: flex;
  gap: 8px;
}

.sa-card-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.sa-card-head h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
}

/* Table in Modal */
.sa-table-modal {
  margin-top: 16px;
  font-size: 13px;
}

.sa-table-modal th,
.sa-table-modal td {
  padding: 12px;
}

.text-center {
  text-align: center;
}

.text-muted {
  color: #64748b;
}

/* Button Icon and Text */
.btn-icon {
  font-size: 16px;
  line-height: 1;
}

.btn-text {
  font-size: 14px;
  font-weight: 500;
}

/* Responsive table for new columns */
@media (max-width: 1200px) {
  .sa-table {
    font-size: 12px;
  }
  
  .date-cell {
    min-width: 100px;
    font-size: 11px;
  }
  
  .user-cell {
    min-width: 80px;
    max-width: 120px;
    font-size: 11px;
  }
  
  .actions-cell .btn {
    padding: 4px 8px;
    font-size: 10px;
  }
}

@media (max-width: 768px) {
  .sa-table {
    display: block;
    overflow-x: auto;
    white-space: nowrap;
  }
  
  .sa-table th,
  .sa-table td {
    padding: 8px;
  }
}
</style>


