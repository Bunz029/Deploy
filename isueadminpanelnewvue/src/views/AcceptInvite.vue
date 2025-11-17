<template>
  <div class="invite-container">
    <div class="invite-card">
      <!-- Header Section -->
      <div class="header-section" v-if="!success">
        <div class="institution-logo">
          <div class="logo-placeholder">🏛️</div>
        </div>
        <h1 class="system-title">ISU-E Campus Interactive Map</h1>
        <h2 class="invite-title">Administrative Access Invitation</h2>
        <p class="institution-subtitle">Isabela State University - Echague Campus</p>
      </div>

      <div v-if="loading" class="loading-section">
        <div class="loading-spinner">⏳</div>
        <p class="loading-text">Validating your invitation credentials...</p>
        <p class="loading-subtext">Please wait while we verify your access authorization.</p>
      </div>

      <div v-else>
        <div v-if="!valid" class="error-section">
          <div class="error-icon">⚠️</div>
          <h3 class="error-title">Invalid Invitation</h3>
          <p class="error-text">The invitation link you accessed is either invalid, expired, or has already been used.</p>
          <p class="error-subtext">Please contact your system administrator for a new invitation.</p>
          <button class="btn btn-secondary" @click="goLogin">Return to Login</button>
        </div>

        <div v-else>
          <!-- Success state -->
          <div v-if="success" class="success-state">
            <div class="success-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.172 7.707 8.879a1 1 0 10-1.414 1.414L9 13l4.707-4.707z" clip-rule="evenodd"/>
              </svg>
            </div>
            <h3 class="success-title">Administrative Account Successfully Created</h3>
            <p class="success-message">
              Welcome to the ISU-E Campus Interactive Map Administration Team, <span class="highlight">{{ formatName(form.first_name, form.last_name) }}</span>. 
              Your administrative account has been successfully established and you now have authorized access to the system.
            </p>
            <div class="access-info">
              <p><strong>Your Role:</strong> System Administrator</p>
              <p><strong>Access Level:</strong> {{ invite.role === 'super_admin' ? 'Super Administrator' : 'Administrator' }}</p>
              <p><strong>Institution:</strong> Isabela State University - Echague Campus</p>
            </div>
            <button class="btn btn-success" @click="goLogin">Proceed to Administrative Portal</button>
            <div class="countdown-text">Automatically redirecting in {{ countdown }} seconds...</div>
          </div>

          <!-- Form state -->
          <div v-else>
            <div class="invitation-details">
              <div class="invitation-header">
                <h3>Administrative Invitation Details</h3>
                <p class="invitation-description">
                  You have been invited to join the ISU-E Campus Interactive Map administrative team. 
                  This system manages the digital campus map and building information for students, faculty, and visitors.
                </p>
              </div>
              
              <div class="access-grant">
                <div class="grant-item">
                  <strong>Invited Email:</strong> {{ invite.email }}
                </div>
                <div class="grant-item">
                  <strong>Administrative Role:</strong> {{ invite.role === 'super_admin' ? 'Super Administrator' : 'Administrator' }}
                </div>
                <div class="grant-item">
                  <strong>Authority Level:</strong> {{ invite.role === 'super_admin' ? 'Full System Control & User Management' : 'Content Management & System Operations' }}
                </div>
              </div>
            </div>

            <div class="form-section">
              <h4 class="form-section-title">Complete Your Administrative Profile</h4>
              <p class="form-instructions">Please provide the required information to establish your administrative account.</p>
              
              <div class="form-fields">
                <div class="field-group">
                  <label class="field-label">First Name</label>
                  <input v-model.trim="form.first_name" class="input" placeholder="Enter your first name" />
                </div>
                
                <div class="field-group">
                  <label class="field-label">Last Name</label>
                  <input v-model.trim="form.last_name" class="input" placeholder="Enter your last name" />
                </div>
                
                <div class="field-group">
                  <label class="field-label">Administrative Password</label>
                  <div class="password-field">
                    <input :type="showPassword ? 'text' : 'password'" v-model="form.password" class="input" placeholder="Create a secure password (minimum 12 characters)" @input="validatePassword" />
                    <button type="button" class="eye-button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                      <span v-if="!showPassword">👁️</span>
                      <span v-else>🙈</span>
                    </button>
                  </div>
                  <div v-if="passwordError" class="password-error">
                    <span class="error-icon">⚠️</span>
                    {{ passwordError }}
                  </div>
                  <div v-if="form.password && !passwordError && form.password.length >= 12" class="password-success">
                    <span class="success-icon">✓</span>
                    Password meets requirements
                  </div>
                  <p class="field-hint">Use a combination of uppercase, lowercase, numbers, and special characters</p>
                </div>
                
                <div class="field-group">
                  <label class="field-label">Confirm Administrative Password</label>
                  <div class="password-field">
                    <input :type="showConfirm ? 'text' : 'password'" v-model="form.confirm" class="input" placeholder="Re-enter your password to confirm" />
                    <button type="button" class="eye-button" @click="showConfirm = !showConfirm" :aria-label="showConfirm ? 'Hide password' : 'Show password'">
                      <span v-if="!showConfirm">👁️</span>
                      <span v-else>🙈</span>
                    </button>
                  </div>
                  <div class="password-match" :class="matchClass">{{ matchText }}</div>
                </div>
              </div>

              <div class="terms-section">
                <p class="terms-text">
                  By accepting this invitation, you acknowledge that you will have administrative access to sensitive university data 
                  and agree to maintain the confidentiality and security of the ISU-E Campus Interactive Map system.
                </p>
              </div>

              <div class="form-actions">
                <button class="btn btn-secondary" @click="goLogin">Decline Invitation</button>
                <button class="btn btn-primary" @click="submit" :disabled="submitting || !canSubmit">
                  {{ submitting ? 'Creating Administrative Account...' : 'Accept Invitation & Create Account' }}
                </button>
              </div>
              
              <div class="error-message" v-if="error">{{ error }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
export default {
  name: 'AcceptInvite',
  data(){
    return { loading: true, valid: false, error: '', invite: {}, form: { first_name: '', last_name: '', password: '', confirm: '' }, submitting: false, showPassword: false, showConfirm: false, success: false, countdown: 5, passwordError: '' }
  },
  computed: {
    matchText() {
      if (!this.form.password || !this.form.confirm) return ''
      return this.form.password === this.form.confirm ? 'Passwords match ✓' : 'Passwords do not match'
    },
    matchClass() {
      if (!this.form.password || !this.form.confirm) return ''
      return this.form.password === this.form.confirm ? 'match-success' : 'match-error'
    },
    canSubmit() {
      return this.form.first_name.trim() && 
             this.form.last_name.trim() && 
             this.form.password.length >= 12 && 
             this.form.password === this.form.confirm &&
             !this.passwordError
    }
  },
  async created(){
    const token = this.$route.params.token
    try {
      const { data } = await axios.get(`/auth/invite/${token}`)
      this.valid = data.valid
      this.invite = data
    } catch (e) {
      this.valid = false
    } finally {
      this.loading = false
    }
  },
  methods: {
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
    
    async submit(){
      this.validatePassword()
      if (this.passwordError) {
        this.error = 'Please fix password requirements before submitting.'
        return
      }
      
      if (!this.form.first_name || !this.form.last_name || (this.form.password||'').length < 12) { 
        this.error = 'Provide first name, last name, and a password of at least 12 characters.'; 
        return 
      }
      if (this.form.password !== this.form.confirm) { this.error = 'Passwords do not match.'; return }
      this.error=''; this.submitting=true
      try {
        const token = this.$route.params.token
        const formattedData = {
          token,
          name: this.formatName(this.form.first_name, this.form.last_name),
          password: this.form.password
        }
        await axios.post('/auth/accept-invite', formattedData)
        this.success = true
        const timer = setInterval(() => {
          if (this.countdown <= 1) { clearInterval(timer); this.goLogin(); } else { this.countdown -= 1 }
        }, 1000)
      } catch(e) {
        this.error = e?.response?.data?.message || 'Failed to accept invite'
      } finally { this.submitting=false }
    },
    goLogin(){ this.$router.push('/login') },
    
    // Name formatting utilities
    toCamelCase(str) {
      if (!str) return ''
      return str.toLowerCase().replace(/\b\w/g, l => l.toUpperCase())
    },
    
    formatName(firstName, lastName) {
      if (!firstName || !lastName) return ''
      const formattedFirst = this.toCamelCase(firstName.trim())
      const formattedLast = this.toCamelCase(lastName.trim())
      return `${formattedLast}, ${formattedFirst}`
    }
  }
}
</script>

<style scoped>
/* Container and Layout */
.invite-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #2d5016 0%, #4a7c59 50%, #c8102e 100%);
  padding: 24px;
  font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
}

.invite-card {
  background: white;
  border-radius: 16px;
  padding: 40px;
  width: 100%;
  max-width: 600px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

/* Header Section */
.header-section {
  text-align: center;
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 2px solid #f1f5f9;
}

.institution-logo {
  margin-bottom: 16px;
}

.logo-placeholder {
  font-size: 48px;
  margin-bottom: 8px;
}

.system-title {
  margin: 0 0 8px;
  font-size: 28px;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: -0.5px;
}

.invite-title {
  margin: 0 0 8px;
  font-size: 20px;
  font-weight: 600;
  color: #475569;
}

.institution-subtitle {
  margin: 0;
  font-size: 14px;
  color: #64748b;
  font-weight: 500;
}

/* Loading Section */
.loading-section {
  text-align: center;
  padding: 60px 20px;
}

.loading-spinner {
  font-size: 48px;
  margin-bottom: 20px;
  animation: spin 2s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.loading-text {
  color: #1e293b;
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 8px;
}

.loading-subtext {
  color: #64748b;
  font-size: 14px;
}

/* Error Section */
.error-section {
  text-align: center;
  padding: 40px 20px;
}

.error-icon {
  font-size: 48px;
  margin-bottom: 20px;
}

.error-title {
  margin: 0 0 16px;
  font-size: 20px;
  font-weight: 600;
  color: #1e293b;
}

.error-text {
  color: #ef4444;
  font-size: 16px;
  font-weight: 500;
  margin-bottom: 8px;
}

.error-subtext {
  color: #64748b;
  font-size: 14px;
  margin-bottom: 24px;
}

.invite-email {
  color: #64748b;
  font-size: 14px;
  margin-bottom: 24px;
  text-align: center;
  padding: 12px;
  background-color: #f8fafc;
  border-radius: 8px;
}

.invite-email strong {
  color: #1e293b;
}

/* Success State */
.success-state {
  text-align: center;
  padding: 20px 0;
}

.success-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 20px;
  background-color: #dcfce7;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: successPulse 0.6s ease-out;
}

.success-icon svg {
  width: 32px;
  height: 32px;
  color: #16a34a;
}

@keyframes successPulse {
  0% {
    transform: scale(0.8);
    opacity: 0;
  }
  50% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.success-title {
  margin: 0 0 16px;
  font-size: 20px;
  font-weight: 600;
  color: #1e293b;
}

.success-message {
  color: #64748b;
  font-size: 16px;
  line-height: 1.6;
  margin-bottom: 24px;
}

.highlight {
  color: #1e293b;
  font-weight: 600;
}

.access-info {
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 20px;
  margin: 24px 0;
  text-align: left;
}

.access-info p {
  margin: 8px 0;
  font-size: 14px;
  color: #475569;
}

.countdown-text {
  color: #94a3b8;
  font-size: 12px;
  margin-top: 16px;
}

/* Invitation Details */
.invitation-details {
  margin-bottom: 32px;
}

.invitation-header {
  margin-bottom: 24px;
}

.invitation-header h3 {
  margin: 0 0 12px;
  font-size: 20px;
  font-weight: 600;
  color: #1e293b;
}

.invitation-description {
  color: #64748b;
  font-size: 15px;
  line-height: 1.6;
  margin: 0;
}

.access-grant {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px;
  margin-top: 20px;
}

.grant-item {
  margin-bottom: 12px;
  font-size: 14px;
  color: #475569;
  line-height: 1.5;
}

.grant-item:last-child {
  margin-bottom: 0;
}

.grant-item strong {
  color: #1e293b;
  font-weight: 600;
}

/* Form Section */
.form-section {
  border-top: 2px solid #f1f5f9;
  padding-top: 32px;
}

.form-section-title {
  margin: 0 0 8px;
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
}

.form-instructions {
  color: #64748b;
  font-size: 14px;
  margin: 0 0 24px;
  line-height: 1.5;
}

.field-group {
  margin-bottom: 20px;
}

.field-label {
  display: block;
  margin-bottom: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.field-hint {
  margin-top: 6px;
  font-size: 12px;
  color: #64748b;
  line-height: 1.4;
}

.terms-section {
  background-color: #fefce8;
  border: 1px solid #fde047;
  border-radius: 8px;
  padding: 16px;
  margin: 24px 0;
}

.terms-text {
  margin: 0;
  font-size: 13px;
  color: #713f12;
  line-height: 1.5;
}

/* Form Elements */
.form-fields {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.input {
  padding: 12px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  width: 100%;
  box-sizing: border-box;
  transition: border-color 0.2s, box-shadow 0.2s;
  background-color: #ffffff;
}

.input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.input::placeholder {
  color: #94a3b8;
}

.password-field {
  position: relative;
}

.eye-button {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 16px;
  padding: 4px;
  border-radius: 4px;
  transition: background-color 0.2s;
}

.eye-button:hover {
  background-color: #f1f5f9;
}

.password-match {
  font-size: 12px;
  margin-top: 6px;
  margin-bottom: 0;
  line-height: 1.4;
  min-height: 18px; /* reserve space to prevent layout jump/overlap */
  display: block;
}

.match-success {
  color: #16a34a;
}

.match-error {
  color: #ef4444;
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

/* Buttons */
.btn {
  padding: 12px 20px;
  border: 2px solid transparent;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 100px;
}

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn-primary {
  background-color: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.btn-primary:hover:not(:disabled) {
  background-color: #2563eb;
  border-color: #2563eb;
}

.btn-secondary {
  background-color: #f8fafc;
  color: #475569;
  border-color: #e2e8f0;
}

.btn-secondary:hover:not(:disabled) {
  background-color: #e2e8f0;
  color: #334155;
}

.btn-success {
  background-color: #10b981;
  color: white;
  border-color: #10b981;
  padding: 12px 32px;
  font-size: 16px;
}

.btn-success:hover:not(:disabled) {
  background-color: #059669;
  border-color: #059669;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 8px;
}

.error-message {
  color: #ef4444;
  font-size: 14px;
  padding: 12px 16px;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  margin-top: 8px;
}

/* Responsive Design */
@media (max-width: 480px) {
  .invite-container {
    padding: 16px;
  }
  
  .invite-card {
    padding: 24px;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
  }
}
</style>


