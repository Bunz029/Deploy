<template>
  <div class="reset-container">
    <div class="reset-card">
      <div class="reset-header">
        <h2 v-if="!success" class="reset-title">Reset Password</h2>
        <div v-if="loading" class="loading-text">Checking link…</div>
      </div>
      
      <div v-if="!loading">
        <div v-if="!valid" class="error-section">
          <div class="error-icon">⚠️</div>
          <div class="error-title">Invalid Reset Link</div>
          <div class="error-text">This password reset link is invalid or has expired.</div>
          <button class="btn btn-primary" @click="$router.push('/login')">Go to Login</button>
        </div>
        
        <div v-else>
          <div v-if="success" class="success-section">
            <div class="success-icon">
              <svg xmlns="http://www.w3.org/2000/svg" class="success-checkmark" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.172 7.707 8.879a1 1 0 10-1.414 1.414L9 13l4.707-4.707z" clip-rule="evenodd"/>
              </svg>
            </div>
            <h3 class="success-title">Password Updated Successfully</h3>
            <p class="success-message">You can now sign in with your new password.</p>
            <button class="btn btn-success" @click="$router.push('/login')">Go to Login</button>
          </div>
          
          <div v-else class="form-section">
            <div class="form-group">
              <label class="field-label">New Password</label>
              <div class="password-field">
                <input 
                  :type="show ? 'text' : 'password'" 
                  v-model="pwd" 
                  class="input" 
                  placeholder="Enter new password (minimum 12 characters)" 
                />
                <button type="button" class="eye-button" @click="show = !show">
                  {{ show ? '🙈' : '👁️' }}
                </button>
              </div>
            </div>
            
            <div class="form-group">
              <label class="field-label">Confirm Password</label>
              <div class="password-field">
                <input 
                  :type="show ? 'text' : 'password'" 
                  v-model="confirm" 
                  class="input" 
                  placeholder="Confirm your new password" 
                />
                <button type="button" class="eye-button" @click="show = !show">
                  {{ show ? '🙈' : '👁️' }}
                </button>
              </div>
            </div>
            
            <div class="password-match" :class="matchClass">{{ matchText }}</div>
            
            <div class="form-actions">
              <button class="btn btn-secondary" @click="$router.push('/login')">Cancel</button>
              <button 
                class="btn btn-primary" 
                @click="submit" 
                :disabled="submitting || !canSubmit"
              >
                {{ submitting ? 'Updating...' : 'Update Password' }}
              </button>
            </div>
            
            <div class="error-message" v-if="error">{{ error }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
export default {
  name: 'ResetPassword',
  data(){
    return { loading: true, valid: false, success: false, error: '', pwd: '', confirm: '', show: false, submitting: false }
  },
  computed: {
    matchText(){ 
      if(!this.pwd && !this.confirm) return ''; 
      return this.pwd===this.confirm?'Passwords match':'Passwords do not match' 
    },
    matchClass(){ 
      if(!this.pwd && !this.confirm) return 'match-neutral'; 
      return this.pwd===this.confirm?'match-success':'match-error' 
    },
    canSubmit() {
      return this.pwd && this.confirm && this.pwd === this.confirm && this.pwd.length >= 12
    }
  },
  async created(){
    const token = this.$route.params.token
    try {
      const { data } = await axios.get(`/auth/password/reset/${token}`)
      this.valid = data.valid
    } catch { this.valid = false } finally { this.loading = false }
  },
  methods: {
    async submit(){
      if ((this.pwd||'').length<12) { this.error='Password must be at least 12 characters.'; return }
      if (this.pwd!==this.confirm) { this.error='Passwords do not match.'; return }
      this.error=''; this.submitting=true
      try {
        await axios.post('/auth/password/reset', { token: this.$route.params.token, password: this.pwd })
        this.success = true
      } catch(e) { this.error = e?.response?.data?.message || 'Failed to reset password' } finally { this.submitting=false }
    }
  }
}
</script>

<style scoped>
/* Container and Layout */
.reset-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #2d5016 0%, #4a7c59 50%, #c8102e 100%);
  padding: 24px;
  font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
}

.reset-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  padding: 32px;
  width: 100%;
  max-width: 480px;
  position: relative;
  overflow: hidden;
}

.reset-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

/* Header */
.reset-header {
  text-align: center;
  margin-bottom: 32px;
}

.reset-title {
  font-size: 28px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 8px 0;
}

.loading-text {
  color: #64748b;
  font-size: 16px;
  font-weight: 500;
}

/* Error Section */
.error-section {
  text-align: center;
  padding: 24px 0;
}

.error-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.error-title {
  font-size: 20px;
  font-weight: 600;
  color: #dc2626;
  margin-bottom: 8px;
}

.error-text {
  color: #64748b;
  font-size: 14px;
  margin-bottom: 24px;
  line-height: 1.5;
}

/* Success Section */
.success-section {
  text-align: center;
  padding: 24px 0;
}

.success-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981, #059669);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;
  box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
}

.success-checkmark {
  width: 40px;
  height: 40px;
  color: white;
}

.success-title {
  font-size: 24px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 12px;
}

.success-message {
  color: #64748b;
  font-size: 16px;
  margin-bottom: 32px;
  line-height: 1.5;
}

/* Form Section */
.form-section {
  padding: 8px 0;
}

.form-group {
  margin-bottom: 24px;
}

.field-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.password-field {
  position: relative;
}

.input {
  width: 93%;
  padding: 14px 16px;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  font-size: 16px;
  transition: all 0.2s ease;
  background: #f9fafb;
}

.input:focus {
  outline: none;
  border-color: #4a7c59;
  background: white;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.eye-button {
  position: absolute;
  right: 5px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 18px;
  padding: 4px;
  border-radius: 4px;
  transition: background-color 0.2s;
}

.eye-button:hover {
  background: rgba(0, 0, 0, 0.05);
}

/* Password Match Indicator */
.password-match {
  font-size: 13px;
  margin-top: 8px;
  margin-bottom: 16px;
  font-weight: 500;
  min-height: 18px;
  display: block;
}

.match-neutral {
  color: #9ca3af;
}

.match-success {
  color: #059669;
}

.match-error {
  color: #dc2626;
}

/* Form Actions */
.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 32px;
  padding-top: 24px;
  border-top: 1px solid #f1f5f9;
}

/* Buttons */
.btn {
  padding: 12px 24px;
  border: 2px solid transparent;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 120px;
}

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
}

.btn-secondary {
  background: #f8fafc;
  color: #475569;
  border-color: #e2e8f0;
}

.btn-secondary:hover {
  background: #e2e8f0;
  color: #334155;
}

.btn-primary {
  background: #26432f;
  color: white;
  border-color: #192f0b
  
}

.btn-primary:hover {
  background: #4a7c59;
  border-color: #2d5016
}

.btn-success {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  border-color: #10b981;
}

.btn-success:hover {
  background: linear-gradient(135deg, #059669, #047857);
  border-color: #059669;
}

/* Error Message */
.error-message {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  margin-top: 16px;
  font-weight: 500;
}

/* Responsive Design */
@media (max-width: 640px) {
  .reset-container {
    padding: 16px;
  }
  
  .reset-card {
    padding: 24px;
    border-radius: 12px;
  }
  
  .reset-title {
    font-size: 24px;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
  }
}
</style>


