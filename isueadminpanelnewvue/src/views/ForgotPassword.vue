<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 p-6">
    <div class="bg-white rounded-xl shadow p-6 w-full max-w-md">
      <h2 class="text-xl font-semibold mb-2 text-gray-900">Forgot Password</h2>
      <p class="text-sm text-gray-600 mb-4">Enter your admin email and we’ll send a reset link.</p>
      <div class="space-y-3">
        <input v-model="email" type="email" class="input" placeholder="Email address" />
        <div class="flex justify-end gap-2">
          <button class="btn" @click="$router.push('/login')">Cancel</button>
          <button class="btn primary bg-emerald-600 hover:bg-emerald-700 border-emerald-600 text-white" @click="submit" :disabled="submitting">Send Link</button>
        </div>
        <div v-if="message" class="text-emerald-700 text-sm">{{ message }}</div>
      </div>
    </div>
  </div>
  </template>

<script>
import axios from 'axios'
export default {
  name: 'ForgotPassword',
  data(){
    return { email: '', submitting: false, message: '' }
  },
  methods: {
    async submit(){
      if (!this.email || !this.email.includes('@')) return
      this.submitting = true; this.message = ''
      try {
        await axios.post('/auth/password/forgot', { email: this.email })
        this.message = 'If an account exists, a reset link has been sent.'
      } finally { this.submitting = false }
    }
  }
}
</script>

<style scoped>
.btn { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; }
.input { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; width: 100%; }
</style>


