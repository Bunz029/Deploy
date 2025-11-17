<template>
  <TransitionGroup
    name="toast"
    tag="div"
    class="toast-container"
  >
    <div
      v-for="toast in toasts"
      :key="toast.id"
      :class="['toast', `toast-${toast.type}`]"
    >
      <div class="toast-icon">
        <span v-if="toast.type === 'success'">✅</span>
        <span v-else-if="toast.type === 'error'">❌</span>
        <span v-else-if="toast.type === 'warning'">⚠️</span>
        <span v-else-if="toast.type === 'loading'" class="loading-spinner">⏳</span>
        <span v-else>ℹ️</span>
      </div>
      <div class="toast-content">
        <div class="toast-title">{{ toast.title }}</div>
        <div v-if="toast.message" class="toast-message">{{ toast.message }}</div>
        <div v-if="toast.type === 'loading'" class="loading-bar">
          <div class="loading-bar-fill" :style="{ width: toast.progress + '%' }"></div>
        </div>
        <div v-if="toast.type === 'loading' && toast.progress !== undefined" class="progress-text">
          {{ toast.progress }}%
        </div>
      </div>
      <button @click="removeToast(toast.id)" class="toast-close">×</button>
    </div>
  </TransitionGroup>
</template>

<script>
export default {
  name: 'ToastNotification',
  data() {
    return {
      toasts: [],
      nextId: 1
    }
  },
  methods: {
    showToast(type, title, message = '', duration = 4000) {
      const toast = {
        id: this.nextId++,
        type,
        title,
        message,
        duration
      }
      
      this.toasts.push(toast)
      
      // Auto-remove after duration (skip for loading toasts)
      if (type !== 'loading' && duration > 0) {
        setTimeout(() => {
          this.removeToast(toast.id)
        }, duration)
      }
    },
    
    removeToast(id) {
      const index = this.toasts.findIndex(toast => toast.id === id)
      if (index > -1) {
        this.toasts.splice(index, 1)
      }
    },
    
    // Convenience methods
    success(title, message = '') {
      this.showToast('success', title, message)
    },
    
    error(title, message = '') {
      this.showToast('error', title, message)
    },
    
    warning(title, message = '') {
      this.showToast('warning', title, message)
    },
    
    info(title, message = '') {
      this.showToast('info', title, message)
    },
    
    loading(title, message = '') {
      this.showToast('loading', title, message)
    },
    
    removeLoadingToasts() {
      this.toasts = this.toasts.filter(toast => toast.type !== 'loading')
    },
    
    updateLoadingProgress(progress, message = '') {
      const loadingToast = this.toasts.find(toast => toast.type === 'loading')
      if (loadingToast) {
        loadingToast.progress = Math.min(100, Math.max(0, progress))
        if (message) {
          loadingToast.message = message
        }
      }
    }
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

.toast-container {
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 99999;
  display: flex;
  flex-direction: column;
  gap: 12px;
  pointer-events: none;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.toast {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  min-width: 300px;
  max-width: 500px;
  z-index: 99999;
  pointer-events: auto;
  border-left: 4px solid;
}

.toast-success {
  border-left-color: #10b981;
}

.toast-error {
  border-left-color: #ef4444;
}

.toast-warning {
  border-left-color: #f59e0b;
}

.toast-info {
  border-left-color: #3b82f6;
}

.toast-loading {
  border-left-color: #8b5cf6;
}

.toast-icon {
  font-size: 20px;
  flex-shrink: 0;
}

.toast-content {
  flex: 1;
  min-width: 0;
}

.toast-title {
  font-weight: 600;
  color: #1f2937;
  font-size: 14px;
  margin-bottom: 2px;
}

.toast-message {
  color: #6b7280;
  font-size: 13px;
  line-height: 1.4;
}

.toast-close {
  background: none;
  border: none;
  color: #9ca3af;
  font-size: 18px;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: all 0.2s;
  flex-shrink: 0;
}

.toast-close:hover {
  color: #6b7280;
  background: #f3f4f6;
}

/* Toast Animations */
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(-100%) translateY(-20px);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%) translateY(-20px);
}

.toast-move {
  transition: transform 0.3s ease;
}

.loading-spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.loading-bar {
  width: 100%;
  height: 4px;
  background-color: #e5e7eb;
  border-radius: 2px;
  overflow: hidden;
  margin-top: 8px;
}

.loading-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #8b5cf6, #a855f7, #8b5cf6);
  border-radius: 2px;
  transition: width 0.3s ease;
  position: relative;
  overflow: hidden;
}

.loading-bar-fill::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

.progress-text {
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: #8b5cf6;
  margin-top: 4px;
}
</style>
