  <template>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <div class="logo-container">
          <img 
            src="@/assets/app-logo.png" 
            alt="ISU-E Campus Map Logo" 
            class="logo-image"
            @error="onImageError"
          />
        </div>
        <h1>ISU-E Campus Map</h1>
        <h2>Admin Panel</h2>
        <p>Sign in to access the admin dashboard</p>
      </div>
      
      <form class="login-form" @submit.prevent>
        <!-- Step 1: Email and Password -->
        <div v-if="loginStep === 1">
        <div class="form-group">
          <label for="email">Email Address</label>
          <input
            id="email"
            v-model="loginForm.email"
            type="email"
            placeholder="Enter your email address"
            required
              :disabled="isSendingOtp"
            class="form-input"
          />
        </div>
        
        <div class="form-group">
          <label for="password">Password</label>
          <div class="password-input-container">
            <input
              id="password"
              v-model="loginForm.password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="Enter your password"
              required
                :disabled="isSendingOtp"
              class="form-input password-input"
              @input="validatePassword"
            />
            <button
              type="button"
              @click="togglePassword"
              class="password-toggle"
                :disabled="isSendingOtp"
            >
              {{ showPassword ? '👁️' : '👁️‍🗨️' }}
            </button>
          </div>
          <div v-if="passwordError" class="password-error">
            <span class="error-icon">⚠️</span>
            {{ passwordError }}
          </div>
          <div v-if="loginForm.password && !passwordError && loginForm.password.length >= 12" class="password-success">
            <span class="success-icon">✓</span>
            Password meets requirements
          </div>
        </div>
        
        <!-- reCAPTCHA -->
        <div class="recaptcha-container">
          <div ref="recaptcha" @click="onRecaptchaClick">
          </div>
        </div>
        
        <button
            type="button"
            @click="handleLogin"
            :disabled="isSendingOtp || !isFormValid"
          class="login-button"
        >
            <span v-if="isSendingOtp" class="loading-spinner"></span>
            Sign In
        </button>
        </div>

        
        <!-- Show error message only if not rate limited -->
        <div v-if="errorMessage && !isAnyRateLimited" class="error-message">
          <span class="error-icon">⚠️</span>
          {{ errorMessage }}
        </div>
        
        <!-- Rate Limiting Messages (Live Timer) -->
        <div v-if="isAnyRateLimited" class="rate-limit-message">
          <span class="rate-limit-icon">{{ isPasswordRateLimited ? '🔒' : '⏰' }}</span>
          <span v-if="rateLimitRemaining > 0">
            Account temporarily locked due to too many {{ isPasswordRateLimited ? 'password' : 'OTP resend' }} attempts. 
            Try again in {{ Math.floor(rateLimitRemaining / 60) }}:{{ (rateLimitRemaining % 60).toString().padStart(2, '0') }}
          </span>
          <span v-else>{{ formattedRateLimitMessage }}</span>
        </div>
        
        <div v-if="otpError" class="error-message">
          <span class="error-icon">⚠️</span>
          {{ otpError }}
        </div>
      </form>
      
      <div class="login-footer">
        <p>© 2024 ISU E-MAP Admin Panel</p>
        <p class="help-text">
          Need help? Contact IT Support
        </p>
        <p style="margin-top:8px;">
          <a @click.prevent="showForgotPasswordModal = true" href="#" style="color:#0ea5e9; text-decoration:underline;">Forgot password?</a>
        </p>
      </div>
    </div>

    <!-- Forgot Password Modal -->
    <div v-if="showForgotPasswordModal" class="modal-overlay" @click="closeForgotPasswordModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Reset Password</h3>
          <button @click="closeForgotPasswordModal" class="modal-close">&times;</button>
        </div>
        
        <div class="modal-body">
          <p class="modal-description">Enter your email address to receive a password reset link.</p>
          
          <form @submit.prevent="requestPasswordReset" v-if="!emailSent">
            <div class="form-group">
              <label for="reset-email">Email Address</label>
              <input
                id="reset-email"
                v-model="resetForm.email"
                type="email"
                placeholder="Enter your email address"
                required
                :disabled="isSubmittingReset"
                class="form-input"
              />
            </div>
            
            <div v-if="resetError" class="error-message">
              <span class="error-icon">⚠️</span>
              {{ resetError }}
            </div>
            
            <div class="modal-actions">
              <button
                type="button"
                @click="closeForgotPasswordModal"
                class="btn-secondary"
                :disabled="isSubmittingReset"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="btn-primary"
                :disabled="isSubmittingReset || !resetForm.email"
              >
                <span v-if="isSubmittingReset" class="loading-spinner"></span>
                {{ isSubmittingReset ? 'Sending...' : 'Send Reset Link' }}
              </button>
            </div>
          </form>
          
          <div v-else class="success-message">
            <div class="success-icon">📧</div>
            <h4>Email Sent!</h4>
            <p>If an account with <strong>{{ resetForm.email }}</strong> exists, a password reset link has been sent to your inbox. Please check your email (and spam folder).</p>
            <div class="success-actions">
              <button 
                @click="resendForgotPassword" 
                :disabled="!canResendForgotPassword || isSubmittingReset"
                class="btn-secondary"
              >
                <span v-if="isSubmittingReset" class="loading-spinner"></span>
                {{ forgotPasswordResendButtonText }}
              </button>
              <button @click="closeForgotPasswordModal" class="btn-primary">
                Back to Login
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- OTP Verification Modal -->
    <div v-if="loginStep === 2" class="modal-overlay" @click="goBackToStep1">
      <div class="modal-content otp-modal" @click.stop>
        <div class="modal-header">
          <h3>Enter Verification Code</h3>
          <button @click="goBackToStep1" class="modal-close">&times;</button>
        </div>
        
        <div class="modal-body">
          <div class="otp-step-header">
            <p>We sent a 6-digit code to <strong>{{ loginForm.email }}</strong></p>
            <div v-if="otpCountdown > 0" class="otp-countdown">
              Code expires in: {{ formatCountdown() }}
            </div>
          </div>
          
          <form @submit.prevent="verifyOtp">
            <div class="form-group">
              <label for="otp-code">Verification Code</label>
              <input
                id="otp-code"
                v-model="otpForm.code"
                type="text"
                placeholder="000000"
                maxlength="6"
                required
                :disabled="isVerifyingOtp"
                class="form-input otp-input"
                @input="formatOtpInput"
                autofocus
              />
            </div>
            
            <div class="form-group checkbox-group">
              <label class="checkbox-label">
                <input
                  v-model="otpForm.trustDevice"
                  type="checkbox"
                  :disabled="isVerifyingOtp"
                />
                <span class="checkmark"></span>
                Trust this device for 30 days
              </label>
            </div>
            
            <!-- Show OTP error only if not rate limited -->
            <div v-if="otpError && !isAnyRateLimited" class="error-message">
              <span class="error-icon">⚠️</span>
              {{ otpError }}
            </div>
            
            <!-- Rate Limiting Messages for OTP Step (Live Timer) -->
            <div v-if="isAnyRateLimited" class="rate-limit-message">
              <span class="rate-limit-icon">{{ isPasswordRateLimited ? '🔒' : '⏰' }}</span>
              <span v-if="rateLimitRemaining > 0">
                Account temporarily locked due to too many {{ isPasswordRateLimited ? 'password' : 'OTP resend' }} attempts. 
                Try again in {{ Math.floor(rateLimitRemaining / 60) }}:{{ (rateLimitRemaining % 60).toString().padStart(2, '0') }}
              </span>
              <span v-else>{{ formattedRateLimitMessage }}</span>
            </div>
            
            <div class="modal-actions">
              <button
                type="button"
                @click="goBackToStep1"
                class="btn-secondary"
                :disabled="isVerifyingOtp"
              >
                Back
              </button>
              <button
                type="button"
                @click="resendOtp"
                :disabled="!canResendOtp || isSendingOtp"
                class="btn-secondary"
              >
                <span v-if="isSendingOtp" class="loading-spinner"></span>
                {{ otpResendButtonText }}
              </button>
              <button
                type="submit"
                :disabled="isVerifyingOtp || !isOtpValid"
                class="btn-primary"
              >
                <span v-if="isVerifyingOtp" class="loading-spinner"></span>
                {{ isVerifyingOtp ? 'Verifying...' : 'Verify & Sign In' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LoginPage',
  data() {
    return {
      loginForm: {
        email: '',
        password: '',
        remember: false
      },
      showPassword: false,
      isLoading: false,
      errorMessage: '',
      passwordError: '',
      // Forgot password modal
      showForgotPasswordModal: false,
      resetForm: {
        email: ''
      },
      isSubmittingReset: false,
      resetError: '',
      emailSent: false,
      // OTP Authentication
      loginStep: 1, // 1 = email/password, 2 = OTP
      otpForm: {
        code: '',
        trustDevice: false
      },
      isSendingOtp: false,
      isVerifyingOtp: false,
      otpError: '',
      otpExpiresAt: null,
      otpCountdown: 0,
      otpTimer: null,
      deviceToken: null,
      recaptchaResponse: null,
      recaptchaWidgetId: null,
      recaptchaRetryCount: 0,
      recaptchaInitializing: false,
      // OTP Resend Cooldown
      otpResendCooldown: 0,
      otpResendTimer: null,
      // Forgot Password Resend Cooldown
      forgotPasswordCooldown: 0,
      forgotPasswordTimer: null,
      // Backend Rate Limiting (handled by server)
      isPasswordRateLimited: false,
      isOtpRateLimited: false,
      rateLimitMessage: '',
      rateLimitType: null,
      rateLimitRemaining: 0,
      rateLimitTimer: null
    }
  },
  computed: {
    isFormValid() {
      // Only check reCAPTCHA when on step 1 (login form)
      if (this.loginStep === 1) {
        return this.loginForm.email && 
               this.loginForm.password && 
               this.loginForm.email.includes('@') &&
               this.loginForm.password.length >= 12 &&
               !this.passwordError &&
               this.recaptchaResponse
      }
      // For OTP step, just check basic form validity
      return this.loginForm.email && 
             this.loginForm.password && 
             this.loginForm.email.includes('@') &&
             this.loginForm.password.length >= 12 &&
             !this.passwordError
    },
    isOtpValid() {
      return this.otpForm.code.length === 6 && /^\d{6}$/.test(this.otpForm.code)
    },
    isOtpExpired() {
      return this.otpExpiresAt && new Date() > new Date(this.otpExpiresAt)
    },
    canResendOtp() {
      return this.otpResendCooldown === 0
    },
    canResendForgotPassword() {
      return this.forgotPasswordCooldown === 0
    },
    otpResendButtonText() {
      return this.otpResendCooldown > 0 ? `Resend in ${this.otpResendCooldown}s` : 'Resend Code'
    },
    forgotPasswordResendButtonText() {
      return this.forgotPasswordCooldown > 0 ? `Resend in ${this.forgotPasswordCooldown}s` : 'Resend Link'
    },
    isAnyRateLimited() {
      return this.isPasswordRateLimited || this.isOtpRateLimited
    },
    formattedRateLimitMessage() {
      if (this.rateLimitMessage) {
        return this.rateLimitMessage
      }
      return ''
    }
  },
  beforeUnmount() {
    if (this.otpTimer) {
      clearInterval(this.otpTimer)
    }
    if (this.otpResendTimer) {
      clearInterval(this.otpResendTimer)
    }
    if (this.forgotPasswordTimer) {
      clearInterval(this.forgotPasswordTimer)
    }
    if (this.rateLimitTimer) {
      clearInterval(this.rateLimitTimer)
    }
    // Clean up reCAPTCHA
    this.cleanupRecaptcha()
  },
  created() {
    this.getOrCreateDeviceToken()
  },
  mounted() {
    // Clean up any existing reCAPTCHA first
    this.cleanupRecaptcha()
    
    // Wait a bit longer for the page to fully load before initializing reCAPTCHA
    setTimeout(() => {
      this.initializeRecaptcha()
    }, 500)
  },
  watch: {
    recaptchaResponse(newVal) {
      // Only log in development
      if (process.env.NODE_ENV === 'development') {
        console.log('reCAPTCHA Response changed:', newVal ? 'Present' : 'Missing')
      }
    },
    isFormValid(newVal) {
      // Only log in development without sensitive data
      if (process.env.NODE_ENV === 'development') {
        console.log('Form valid:', newVal, {
          email: this.loginForm.email ? 'Present' : 'Missing',
          passwordLength: this.loginForm.password.length,
          passwordError: this.passwordError,
          recaptchaResponse: this.recaptchaResponse ? 'Present' : 'Missing',
          loginStep: this.loginStep
        })
      }
      
      // Only check reCAPTCHA status when on step 1 and form is almost valid but reCAPTCHA response is missing
      if (this.loginStep === 1 && !newVal && this.loginForm.email && this.loginForm.password && this.loginForm.password.length >= 12 && !this.passwordError && !this.recaptchaResponse) {
        this.checkRecaptchaStatus()
      }
    }
  },
  methods: {
    togglePassword() {
      this.showPassword = !this.showPassword
    },
    
    validatePassword() {
      const password = this.loginForm.password
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
    
    onImageError(event) {
      // Hide the image if it fails to load and show a fallback
      event.target.style.display = 'none'
      console.warn('Logo image not found. Please check the file exists at: public/img/isu-e-logo.png')
    },
    
    async handleLogin(event) {
      if (event) {
        event.preventDefault()
        event.stopPropagation()
      }
      
      // Check if any rate limiting is active
      if (this.isAnyRateLimited) {
        this.errorMessage = this.formattedRateLimitMessage
        return
      }
      
      // Validate password before proceeding
      this.validatePassword()
      if (this.passwordError) {
        return
      }
      
      // Check reCAPTCHA - try to get response if not already set
      if (!this.recaptchaResponse) {
        // eslint-disable-next-line no-undef
        if (typeof grecaptcha !== 'undefined' && this.$refs.recaptcha) {
          try {
            // eslint-disable-next-line no-undef
            const response = grecaptcha.getResponse()
            if (response) {
              this.recaptchaResponse = response
            }
          } catch (e) {
            console.log('reCAPTCHA getResponse error:', e)
          }
        }
        
        if (!this.recaptchaResponse) {
          this.errorMessage = 'Please complete the reCAPTCHA verification'
          return
        }
      }
      
      if (!this.isFormValid) return
      
      if (this.loginStep === 1) {
        // Step 1: Send OTP
        await this.sendOtp()
      } else {
        // Step 2: Verify OTP
        await this.verifyOtp()
      }
    },

    async sendOtp() {
      // Check if any rate limiting is active
      if (this.isAnyRateLimited) {
        this.errorMessage = this.formattedRateLimitMessage
        return
      }

      this.isSendingOtp = true
      this.errorMessage = ''
      this.otpError = ''
      
      try {
        const { default: axios } = await import('axios')
        const API_BASE = (process.env.VUE_APP_API_BASE || 'https://isuecampusmap.site/backend/api').replace(/\/$/, '')
        const response = await axios.post(`${API_BASE}/auth/otp/send`, {
          email: this.loginForm.email,
          password: this.loginForm.password,
          recaptcha_response: this.recaptchaResponse
        }, {
          headers: {
            'X-Device-Token': this.deviceToken
          }
        })
        
        if (response.data.success) {
          // Reset rate limiting flags on successful send
          this.isPasswordRateLimited = false
          this.isOtpRateLimited = false
          this.rateLimitMessage = ''
          
          // Check if device is trusted and we got a direct login
          if (response.data.trusted_device && response.data.token) {
            // Device is trusted, login directly
            localStorage.setItem('admin_token', response.data.token)
            localStorage.setItem('admin_user', JSON.stringify(response.data.user))
            
            const role = response.data.user?.role
            if (this.$route.query.redirect) {
              this.$router.push(this.$route.query.redirect)
            } else if (role === 'super_admin') {
              this.$router.push('/superadmin')
            } else {
              this.$router.push('/maps')
            }
          } else {
            // Device not trusted, show OTP step
            this.loginStep = 2
            this.otpExpiresAt = response.data.expires_at
            this.startOtpCountdown()
            this.startOtpResendCooldown() // Start cooldown after successful OTP send
            
            // Check if device might already be trusted for this admin
            this.checkDeviceTrustStatus()
          }
        }
      } catch (err) {
        // Handle rate limiting from backend
        if (err?.response?.status === 429 && err?.response?.data?.rate_limited) {
          this.isPasswordRateLimited = err.response.data.rate_limit_type === 'password'
          this.isOtpRateLimited = err.response.data.rate_limit_type === 'otp'
          this.rateLimitMessage = err.response.data.message
          this.rateLimitType = err.response.data.rate_limit_type
          this.rateLimitRemaining = err.response.data.remaining_seconds || 0
          // Don't set errorMessage for rate limiting - let the rate limit message handle it
          
          // Start countdown timer
          if (this.rateLimitRemaining > 0) {
            this.startRateLimitCountdown(this.rateLimitRemaining)
          }
        } else {
          this.errorMessage = err?.response?.data?.message || 'Failed to send verification code'
        }
        
        // Reset reCAPTCHA on authentication errors (401, 400)
        if (err?.response?.status === 401 || err?.response?.status === 400) {
          this.resetRecaptcha()
        }
        
        return // Stop execution on error
      } finally {
        this.isSendingOtp = false
      }
    },

    async verifyOtp() {
      this.isVerifyingOtp = true
      this.otpError = ''
      
      try {
        const { default: axios } = await import('axios')
        const API_BASE = (process.env.VUE_APP_API_BASE || 'https://isuecampusmap.site/backend/api').replace(/\/$/, '')
        const response = await axios.post(`${API_BASE}/auth/otp/verify`, {
          email: this.loginForm.email,
          otp_code: this.otpForm.code,
          trust_device: this.otpForm.trustDevice
        }, {
          headers: {
            'X-Device-Token': this.deviceToken
          }
        })
        
        if (response.data.success) {
          // Store token and redirect
          localStorage.setItem('admin_token', response.data.token)
          localStorage.setItem('admin_user', JSON.stringify(response.data.user))
          
          // If device was trusted, store the trust information
          if (this.otpForm.trustDevice) {
            this.storeDeviceTrustInfo(response.data.user)
          }
          
          const role = response.data.user?.role
          if (this.$route.query.redirect) {
            this.$router.push(this.$route.query.redirect)
          } else if (role === 'super_admin') {
            this.$router.push('/superadmin')
          } else {
            this.$router.push('/maps')
          }
        }
      } catch (err) {
        this.otpError = err?.response?.data?.message || 'Invalid verification code'
      } finally {
        this.isVerifyingOtp = false
      }
    },

    startOtpCountdown() {
      if (this.otpTimer) {
        clearInterval(this.otpTimer)
      }
      
      this.otpCountdown = 10 * 60 // 10 minutes in seconds
      
      this.otpTimer = setInterval(() => {
        this.otpCountdown--
        if (this.otpCountdown <= 0) {
          clearInterval(this.otpTimer)
          this.otpTimer = null
        }
      }, 1000)
    },

    formatCountdown() {
      const minutes = Math.floor(this.otpCountdown / 60)
      const seconds = this.otpCountdown % 60
      return `${minutes}:${seconds.toString().padStart(2, '0')}`
    },

    goBackToStep1() {
      this.loginStep = 1
      this.otpForm.code = ''
      this.otpForm.trustDevice = false
      this.otpError = ''
      this.otpExpiresAt = null
      this.otpCountdown = 0
      if (this.otpTimer) {
        clearInterval(this.otpTimer)
        this.otpTimer = null
      }
      // Reset OTP resend cooldown
      this.otpResendCooldown = 0
      if (this.otpResendTimer) {
        clearInterval(this.otpResendTimer)
        this.otpResendTimer = null
      }
    },

    formatOtpInput(event) {
      // Only allow numbers and limit to 6 digits
      let value = event.target.value.replace(/\D/g, '').slice(0, 6)
      this.otpForm.code = value
      event.target.value = value
    },

    getOrCreateDeviceToken() {
      // Get device token from localStorage or create a new one
      let token = localStorage.getItem('device_token')
      if (!token) {
        // Generate a simple device token
        token = this.generateDeviceToken()
        localStorage.setItem('device_token', token)
      }
      this.deviceToken = token
    },

    initializeRecaptcha() {
      // Prevent multiple initialization attempts
      if (this.recaptchaInitializing) {
        return
      }

      this.recaptchaInitializing = true

      // Check if reCAPTCHA script is loaded in DOM
      const recaptchaScript = document.querySelector('script[src*="recaptcha"]')
      if (!recaptchaScript) {
        console.warn('reCAPTCHA script not found in DOM')
        this.recaptchaInitializing = false
        return
      }

      // Wait for reCAPTCHA to load completely
      // eslint-disable-next-line no-undef
      if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.render === 'function') {
        this.setupRecaptcha()
        this.recaptchaInitializing = false
      } else {
        // Retry after a short delay, but limit retries
        if (this.recaptchaRetryCount < 50) { // Max 5 seconds of retries
          this.recaptchaRetryCount++
          this.recaptchaInitializing = false
          setTimeout(() => this.initializeRecaptcha(), 100)
        } else {
          console.warn('reCAPTCHA failed to load after multiple attempts')
          this.recaptchaInitializing = false
        }
      }
    },

    setupRecaptcha() {
      // Check if already rendered
      if (this.recaptchaWidgetId !== null) {
        // Only log in development
        if (process.env.NODE_ENV === 'development') {
          console.log('reCAPTCHA already rendered, skipping')
        }
        return
      }

      // Check if DOM element exists and grecaptcha is available
      // eslint-disable-next-line no-undef
      if (this.$refs.recaptcha && typeof grecaptcha !== 'undefined' && typeof grecaptcha.render === 'function') {
        try {
          // Clear any existing content in the reCAPTCHA element
          this.$refs.recaptcha.innerHTML = ''

          // eslint-disable-next-line no-undef
          this.recaptchaWidgetId = grecaptcha.render(this.$refs.recaptcha, {
            'sitekey': '6LdCwfMrAAAAAECnuuWNsR9E2PPIUl2m8UZuQ-hU',
            'callback': this.onRecaptchaSuccess,
            'expired-callback': this.onRecaptchaExpired,
            'error-callback': this.onRecaptchaError
          })
          // Only log in development
          if (process.env.NODE_ENV === 'development') {
            console.log('reCAPTCHA rendered with widget ID:', this.recaptchaWidgetId)
          }
        } catch (e) {
          console.error('reCAPTCHA render error:', e)
          // Don't retry if it's already rendered error
          if (e.message && e.message.includes('already been rendered')) {
            console.warn('reCAPTCHA already rendered, stopping retry attempts')
            return
          }
          // Reset retry count and try again after a longer delay
          this.recaptchaRetryCount = 0
          setTimeout(() => this.initializeRecaptcha(), 1000)
        }
      } else {
        console.warn('reCAPTCHA setup failed: DOM element or grecaptcha not ready')
        // Reset retry count and try again
        this.recaptchaRetryCount = 0
        setTimeout(() => this.initializeRecaptcha(), 1000)
      }
    },

    onRecaptchaSuccess(response) {
      // Only log in development
      if (process.env.NODE_ENV === 'development') {
        console.log('reCAPTCHA Success: Response received')
      }
      this.recaptchaResponse = response
      this.errorMessage = ''
    },

    onRecaptchaExpired() {
      this.recaptchaResponse = null
      // Only log in development
      if (process.env.NODE_ENV === 'development') {
        console.log('reCAPTCHA expired, response cleared')
      }
    },

    onRecaptchaError() {
      this.recaptchaResponse = null
      this.errorMessage = 'reCAPTCHA verification failed. Please try again.'
    },

    resetRecaptcha() {
      // eslint-disable-next-line no-undef
      if (typeof grecaptcha !== 'undefined' && this.recaptchaWidgetId !== null) {
        try {
          // eslint-disable-next-line no-undef
          grecaptcha.reset(this.recaptchaWidgetId)
          this.recaptchaResponse = null
          // Only log in development
          if (process.env.NODE_ENV === 'development') {
            console.log('reCAPTCHA reset')
          }
        } catch (e) {
          console.log('reCAPTCHA reset error:', e)
        }
      }
    },

    cleanupRecaptcha() {
      if (this.recaptchaWidgetId !== null) {
        try {
          // eslint-disable-next-line no-undef
          if (typeof grecaptcha !== 'undefined') {
            // eslint-disable-next-line no-undef
            grecaptcha.reset(this.recaptchaWidgetId)
          }
        } catch (e) {
          console.log('reCAPTCHA cleanup error:', e)
        }
      }
      
      // Clear the DOM element completely
      if (this.$refs.recaptcha) {
        this.$refs.recaptcha.innerHTML = ''
      }
      
      // Reset all reCAPTCHA state
      this.recaptchaWidgetId = null
      this.recaptchaResponse = null
      this.recaptchaRetryCount = 0
      this.recaptchaInitializing = false
    },

    onRecaptchaClick() {
      // Clear any previous error messages when user interacts with reCAPTCHA
      if (this.errorMessage && this.errorMessage.includes('reCAPTCHA')) {
        this.errorMessage = ''
      }
    },

    checkDeviceTrustStatus() {
      // Check if this device has been trusted before for any admin
      // This helps prevent duplicate trust attempts
      const trustedDevices = JSON.parse(localStorage.getItem('trusted_devices') || '[]')
      const currentDevice = trustedDevices.find(device => device.token === this.deviceToken)
      
      if (currentDevice) {
        // Only log in development
        if (process.env.NODE_ENV === 'development') {
          console.log('Device has been trusted before for admin:', currentDevice.adminEmail)
        }
        
        // Show a helpful message to the user
        this.otpError = 'This device has been trusted before. You can still complete OTP verification.'
      }
    },

    storeDeviceTrustInfo(user) {
      // Store device trust information in localStorage to track trusted devices
      const trustedDevices = JSON.parse(localStorage.getItem('trusted_devices') || '[]')
      
      // Remove any existing entry for this device token
      const filteredDevices = trustedDevices.filter(device => device.token !== this.deviceToken)
      
      // Add new trust entry
      const trustEntry = {
        token: this.deviceToken,
        adminEmail: user.email,
        adminName: user.name,
        trustedAt: new Date().toISOString(),
        expiresAt: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString() // 30 days
      }
      
      filteredDevices.push(trustEntry)
      localStorage.setItem('trusted_devices', JSON.stringify(filteredDevices))
      
      // Only log in development
      if (process.env.NODE_ENV === 'development') {
        console.log('Device trust info stored:', trustEntry)
      }
    },

    checkRecaptchaStatus() {
      // Don't check reCAPTCHA if we're on step 2 (OTP step) or if reCAPTCHA is not available
      if (this.loginStep !== 1) {
        return false
      }
      
      // eslint-disable-next-line no-undef
      if (typeof grecaptcha !== 'undefined' && this.recaptchaWidgetId !== null) {
        try {
          // eslint-disable-next-line no-undef
          const response = grecaptcha.getResponse(this.recaptchaWidgetId)
          if (response && response.length > 0) {
            this.recaptchaResponse = response
            // Only log in development
            if (process.env.NODE_ENV === 'development') {
              console.log('reCAPTCHA status check - response found')
            }
            return true
          }
        } catch (e) {
          console.log('reCAPTCHA status check error:', e)
        }
      }
      return false
    },

    generateDeviceToken() {
      // Generate a simple device token based on browser info
      const canvas = document.createElement('canvas')
      const ctx = canvas.getContext('2d')
      ctx.textBaseline = 'top'
      ctx.font = '14px Arial'
      ctx.fillText('Device fingerprint', 2, 2)
      
      const fingerprint = [
        navigator.userAgent,
        navigator.language,
        screen.width + 'x' + screen.height,
        new Date().getTimezoneOffset(),
        canvas.toDataURL()
      ].join('|')
      
      // Simple hash function
      let hash = 0
      for (let i = 0; i < fingerprint.length; i++) {
        const char = fingerprint.charCodeAt(i)
        hash = ((hash << 5) - hash) + char
        hash = hash & hash // Convert to 32-bit integer
      }
      
      return Math.abs(hash).toString(36)
    },

    // OTP Resend Cooldown Methods
    startOtpResendCooldown() {
      if (this.otpResendTimer) {
        clearInterval(this.otpResendTimer)
      }
      
      this.otpResendCooldown = 60 // 60 seconds cooldown
      
      this.otpResendTimer = setInterval(() => {
        this.otpResendCooldown--
        if (this.otpResendCooldown <= 0) {
          clearInterval(this.otpResendTimer)
          this.otpResendTimer = null
        }
      }, 1000)
    },

    async resendOtp() {
      if (!this.canResendOtp) return
      
      // Check if any rate limiting is active
      if (this.isAnyRateLimited) {
        this.otpError = this.formattedRateLimitMessage
        return
      }
      
      this.isSendingOtp = true
      this.otpError = ''
      
      try {
        const { default: axios } = await import('axios')
        const API_BASE = (process.env.VUE_APP_API_BASE || 'https://isuecampusmap.site/backend/api').replace(/\/$/, '')
        const response = await axios.post(`${API_BASE}/auth/otp/send`, {
          email: this.loginForm.email,
          password: this.loginForm.password,
          recaptcha_response: 'resend_otp'
        }, {
          headers: {
            'X-Device-Token': this.deviceToken
          }
        })
        
        if (response.data.success) {
          // Check if device is trusted and we got a direct login
          if (response.data.trusted_device && response.data.token) {
            // Device is trusted, login directly
            localStorage.setItem('admin_token', response.data.token)
            localStorage.setItem('admin_user', JSON.stringify(response.data.user))
            
            const role = response.data.user?.role
            if (this.$route.query.redirect) {
              this.$router.push(this.$route.query.redirect)
            } else if (role === 'super_admin') {
              this.$router.push('/superadmin')
            } else {
              this.$router.push('/maps')
            }
          } else {
            // Device not trusted, show OTP step
            this.otpExpiresAt = response.data.expires_at
            this.startOtpCountdown()
            this.startOtpResendCooldown() // Start cooldown after successful resend
          }
        }
      } catch (err) {
        // Handle rate limiting from backend
        if (err?.response?.status === 429 && err?.response?.data?.rate_limited) {
          this.isPasswordRateLimited = err.response.data.rate_limit_type === 'password'
          this.isOtpRateLimited = err.response.data.rate_limit_type === 'otp'
          this.rateLimitMessage = err.response.data.message
          this.rateLimitType = err.response.data.rate_limit_type
          this.rateLimitRemaining = err.response.data.remaining_seconds || 0
          // Don't set otpError for rate limiting - let the rate limit message handle it
          
          // Start countdown timer
          if (this.rateLimitRemaining > 0) {
            this.startRateLimitCountdown(this.rateLimitRemaining)
          }
        } else {
          this.otpError = err?.response?.data?.message || 'Failed to resend verification code'
        }
        
        // Reset reCAPTCHA on authentication errors (401, 400)
        if (err?.response?.status === 401 || err?.response?.status === 400) {
          this.resetRecaptcha()
        }
      } finally {
        this.isSendingOtp = false
      }
    },

    // Forgot Password Resend Cooldown Methods
    startForgotPasswordCooldown() {
      if (this.forgotPasswordTimer) {
        clearInterval(this.forgotPasswordTimer)
      }
      
      this.forgotPasswordCooldown = 60 // 60 seconds cooldown
      
      this.forgotPasswordTimer = setInterval(() => {
        this.forgotPasswordCooldown--
        if (this.forgotPasswordCooldown <= 0) {
          clearInterval(this.forgotPasswordTimer)
          this.forgotPasswordTimer = null
        }
      }, 1000)
    },

    async resendForgotPassword() {
      if (!this.canResendForgotPassword) return
      
      this.resetError = ''
      this.isSubmittingReset = true
      
      try {
        const { default: axios } = await import('axios')
        await axios.post('/auth/password/forgot', {
          email: this.resetForm.email
        })
        this.startForgotPasswordCooldown() // Start cooldown after successful resend
      } catch (err) {
        this.resetError = err?.response?.data?.message || 'Failed to resend reset link. Please try again.'
      } finally {
        this.isSubmittingReset = false
      }
    },

    // Rate Limiting Methods
    startRateLimitCountdown(remainingSeconds) {
      if (this.rateLimitTimer) {
        clearInterval(this.rateLimitTimer)
      }
      
      this.rateLimitRemaining = remainingSeconds
      
      this.rateLimitTimer = setInterval(() => {
        this.rateLimitRemaining--
        if (this.rateLimitRemaining <= 0) {
          clearInterval(this.rateLimitTimer)
          this.rateLimitTimer = null
          this.isPasswordRateLimited = false
          this.isOtpRateLimited = false
          this.rateLimitMessage = ''
        }
      }, 1000)
    },

    // Forgot password modal methods
    closeForgotPasswordModal() {
      this.showForgotPasswordModal = false
      this.resetForm.email = ''
      this.resetError = ''
      this.emailSent = false
      this.isSubmittingReset = false
      // Reset forgot password cooldown
      this.forgotPasswordCooldown = 0
      if (this.forgotPasswordTimer) {
        clearInterval(this.forgotPasswordTimer)
        this.forgotPasswordTimer = null
      }
    },

    async requestPasswordReset() {
      this.resetError = ''
      this.isSubmittingReset = true
      
      try {
        const { default: axios } = await import('axios')
        await axios.post('/auth/password/forgot', {
          email: this.resetForm.email
        })
        this.emailSent = true
        this.startForgotPasswordCooldown() // Start cooldown after successful send
      } catch (err) {
        this.resetError = err?.response?.data?.message || 'Failed to send reset link. Please try again.'
      } finally {
        this.isSubmittingReset = false
      }
    }
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap');

.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #2d5016 0%, #4a7c59 50%, #c8102e 100%);
  padding: 20px;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.login-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
  padding: 40px;
  width: 100%;
  max-width: 420px;
  animation: slideUp 0.5s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.login-header {
  text-align: center;
  margin-bottom: 30px;
}

/* Logo Container */
.logo-container {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}

.logo-image {
  width: 80px;
  height: 80px;
  object-fit: contain;
  filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
  transition: transform 0.3s ease;
}

.logo-image:hover {
  transform: scale(1.05);
}

.login-header h1 {
  color: #2c3e50;
  font-size: 2.2em;
  font-weight: 700;
  margin: 0 0 8px 0;
  font-family: 'Inter', sans-serif;
  letter-spacing: -0.025em;
}

.login-header h2 {
  color: #c8102e;
  font-size: 1.3em;
  font-weight: 600;
  margin: 0 0 8px 0;
  font-family: 'Inter', sans-serif;
  letter-spacing: -0.01em;
}

.login-header p {
  color: #7f8c8d;
  font-size: 0.95em;
  margin: 0;
  font-family: 'Inter', sans-serif;
  font-weight: 400;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  color: #2c3e50;
  font-weight: 600;
  margin-bottom: 8px;
  font-size: 0.95em;
  font-family: 'Inter', sans-serif;
  letter-spacing: -0.01em;
}

.form-input {
  padding: 14px 16px;
  border: 2px solid #e1e8ed;
  border-radius: 8px;
  font-size: 1em;
  transition: all 0.3s ease;
  background: #fafbfc;
  font-family: 'Inter', sans-serif;
  font-weight: 400;
  letter-spacing: -0.01em;
}

.form-input:focus {
  outline: none;
  border-color: #c8102e;
  background: white;
  box-shadow: 0 0 0 3px rgba(200, 16, 46, 0.1);
}

.form-input:disabled {
  background: #f8f9fa;
  color: #6c757d;
  cursor: not-allowed;
}

.password-input-container {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.password-toggle {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  font-size: 1.1em;
  color: #6c757d;
  transition: color 0.2s ease;
}

.password-toggle:hover:not(:disabled) {
  color: #c8102e;
}

.password-toggle:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.password-input {
  width: 100%;
  padding-right: 50px; /* Make room for the toggle button */
}

.checkbox-group {
  flex-direction: row;
  align-items: center;
  margin-top: -5px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  font-size: 0.9em;
  color: #5a6c7d;
  margin: 0;
}

.checkbox-label input[type="checkbox"] {
  margin-right: 8px;
  width: 16px;
  height: 16px;
  accent-color: #c8102e;
}

.login-button {
  background: linear-gradient(135deg, #c8102e 0%, #a00d26 100%);
  color: white;
  border: none;
  padding: 16px;
  border-radius: 8px;
  font-size: 1.05em;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 10px;
  font-family: 'Inter', sans-serif;
  letter-spacing: -0.01em;
}

.login-button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(200, 16, 46, 0.3);
}

.login-button:active:not(:disabled) {
  transform: translateY(0);
}

.login-button:disabled {
  background: #95a5a6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.loading-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid transparent;
  border-top: 2px solid white;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-message {
  background: #fee;
  color: #c53030;
  padding: 12px 16px;
  border-radius: 8px;
  border-left: 4px solid #fc8181;
  font-size: 0.9em;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 5px;
}

.error-icon {
  font-size: 1.1em;
}

.rate-limit-message {
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffeaa7;
  border-radius: 8px;
  padding: 12px 16px;
  margin: 16px 0;
  font-size: 0.9em;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
  border-left: 4px solid #f39c12;
}

.rate-limit-icon {
  font-size: 1.1em;
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

.success-icon {
  font-size: 1em;
}

.recaptcha-container {
  display: flex;
  justify-content: center;
  margin: 15px 0;
}

.recaptcha-container .g-recaptcha {
  transform: scale(0.9);
  transform-origin: center;
}

.login-footer {
  text-align: center;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid #e1e8ed;
}

.login-footer p {
  color: #7f8c8d;
  font-size: 0.85em;
  margin: 4px 0;
}

.help-text {
  font-size: 0.8em !important;
  color: #95a5a6 !important;
}

/* Responsive Design */
@media (max-width: 480px) {
  .login-container {
    padding: 15px;
  }
  
  .login-card {
    padding: 30px 25px;
  }
  
  .login-header h1 {
    font-size: 1.8em;
  }
  
  .login-header h2 {
    font-size: 1.1em;
  }
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-content {
  background: white;
  border-radius: 12px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  width: 100%;
  max-width: 450px;
  max-height: 90vh;
  overflow-y: auto;
  animation: modalSlideIn 0.3s ease-out;
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

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 30px;
  border-bottom: 1px solid #e1e8ed;
}

.modal-header h3 {
  margin: 0;
  color: #2c3e50;
  font-size: 1.3em;
  font-weight: 600;
  font-family: 'Inter', sans-serif;
}

.modal-close {
  background: none;
  border: none;
  font-size: 24px;
  color: #95a5a6;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s ease;
}

.modal-close:hover {
  background: #f8f9fa;
  color: #6c757d;
}

.modal-body {
  padding: 30px;
}

.modal-description {
  color: #7f8c8d;
  margin-bottom: 20px;
  font-size: 0.95em;
  line-height: 1.5;
}

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 25px;
}

.btn-secondary {
  background: #f8f9fa;
  color: #6c757d;
  border: 1px solid #dee2e6;
  padding: 12px 20px;
  border-radius: 8px;
  font-size: 0.95em;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: 'Inter', sans-serif;
}

.btn-secondary:hover:not(:disabled) {
  background: #e9ecef;
  border-color: #adb5bd;
}

.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(135deg, #c8102e 0%, #a00d26 100%);
  color: white;
  border: none;
  padding: 12px 20px;
  border-radius: 8px;
  font-size: 0.95em;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: 'Inter', sans-serif;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(200, 16, 46, 0.3);
}

.btn-primary:disabled {
  background: #95a5a6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.success-message {
  text-align: center;
  padding: 20px 0;
}

.success-icon {
  font-size: 48px;
  margin-bottom: 15px;
}

.success-message h4 {
  color: #2c3e50;
  font-size: 1.2em;
  font-weight: 600;
  margin: 0 0 10px 0;
  font-family: 'Inter', sans-serif;
}

.success-message p {
  color: #7f8c8d;
  margin-bottom: 20px;
  line-height: 1.5;
}

.success-message strong {
  color: #2c3e50;
}

.success-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-top: 20px;
}

/* Modal responsive */
@media (max-width: 480px) {
  .modal-overlay {
    padding: 15px;
  }
  
  .modal-content {
    max-width: 100%;
  }
  
  .modal-header,
  .modal-body {
    padding: 20px;
  }
  
  .modal-actions {
    flex-direction: column;
  }
  
  .btn-secondary,
  .btn-primary {
    width: 100%;
    justify-content: center;
  }
  
  .success-actions {
    flex-direction: column;
  }
}

/* OTP Modal Styles */
.otp-modal {
  max-width: 400px;
}

.otp-step-header {
  text-align: center;
  margin-bottom: 20px;
}

.otp-step-header p {
  color: #7f8c8d;
  margin: 0 0 15px 0;
  font-size: 0.95em;
}

.otp-step-header strong {
  color: #2c3e50;
}

.otp-countdown {
  background: #fff3cd;
  border: 1px solid #ffeaa7;
  border-radius: 5px;
  padding: 8px 12px;
  color: #856404;
  font-size: 0.9em;
  font-weight: 500;
  display: inline-block;
}

.otp-input {
  text-align: center;
  font-size: 1.4em;
  font-weight: 600;
  letter-spacing: 0.3em;
  font-family: 'Courier New', monospace;
  padding: 15px;
}
</style>

