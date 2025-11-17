<template>
  <div v-if="show" class="import-modal-overlay" @click="$emit('close')">
    <div class="import-modal-content" @click.stop>
      <!-- Modal Header -->
      <div class="import-modal-header">
        <div class="header-content">
          <div class="header-icon">📥</div>
          <div class="header-text">
            <h2 class="modal-title">Import Map Layout</h2>
            <p class="modal-subtitle">Restore a previously exported map with all buildings, rooms, and images</p>
          </div>
        </div>
        <button @click="$emit('close')" class="close-button">
          <span class="close-icon">×</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="import-modal-body">
        <!-- Instructions Section -->
        <div class="instructions-section">
          <div class="section-header">
            <div class="section-icon">📋</div>
            <h3 class="section-title">Import Instructions</h3>
          </div>
          <div class="instructions-content">
            <div class="instruction-item">
              <div class="instruction-number">1</div>
              <div class="instruction-text">Select a previously exported map ZIP file (.zip)</div>
            </div>
            <div class="instruction-item">
              <div class="instruction-number">2</div>
              <div class="instruction-text">The import will create a new map with all buildings, rooms, and images</div>
            </div>
            <div class="instruction-item">
              <div class="instruction-number">3</div>
              <div class="instruction-text">Imported maps are inactive by default for safety</div>
            </div>
            <div class="instruction-item">
              <div class="instruction-number">4</div>
              <div class="instruction-text">All data will be preserved including positions, descriptions, and images</div>
            </div>
          </div>
        </div>

        <!-- File Upload Section -->
        <div class="upload-section">
          <div class="section-header">
            <div class="section-icon">📁</div>
            <h3 class="section-title">Select Map File</h3>
          </div>
          
          <div 
            class="file-upload-area" 
            :class="{ 'drag-over': isDragOver, 'has-file': selectedFile }"
            @dragover.prevent="isDragOver = true"
            @dragleave.prevent="isDragOver = false"
            @drop.prevent="handleFileDrop"
            @click="$refs.fileInput.click()"
          >
            <input 
              ref="fileInput"
              type="file" 
              accept=".zip" 
              @change="handleFileSelect"
              style="display: none"
            >
            
            <div v-if="!selectedFile" class="upload-content">
              <div class="upload-icon">📁</div>
              <div class="upload-text">
                <h4>Drop your map export ZIP file here</h4>
                <p>or click to browse files</p>
              </div>
              <button class="browse-button">Browse Files</button>
            </div>
            
            <div v-else class="selected-file-content">
              <div class="file-icon">📄</div>
              <div class="file-details">
                <div class="file-name">{{ selectedFile.name }}</div>
                <div class="file-size">{{ formatFileSize(selectedFile.size) }}</div>
              </div>
              <button @click.stop="clearSelectedFile" class="remove-file-button">
                <span class="remove-icon">×</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Upload Progress (if importing) -->
        <div v-if="importing" class="progress-section">
          <div class="section-header">
            <div class="section-icon">⏳</div>
            <h3 class="section-title">Import Progress</h3>
          </div>
          <div class="progress-content">
            <div class="progress-bar">
              <div 
                class="progress-fill" 
                :style="{ width: uploadProgress + '%' }"
              ></div>
            </div>
            <div class="progress-text">
              {{ uploadStatus }} - {{ Math.round(uploadProgress) }}%
            </div>
          </div>
        </div>

        <!-- Success State (if import completed successfully) -->
        <div v-if="importing && uploadProgress === 100 && uploadStatus.includes('complete')" class="success-section">
          <div class="section-header">
            <div class="section-icon">✅</div>
            <h3 class="section-title">Import Successful!</h3>
          </div>
          <div class="success-content">
            <div class="success-message">
              {{ uploadStatus }}
            </div>
            <div class="success-icon">🎉</div>
          </div>
        </div>

        <!-- Import Preview (if file selected) -->
        <div v-if="selectedFile && importPreview" class="preview-section">
          <div class="section-header">
            <div class="section-icon">👁️</div>
            <h3 class="section-title">Import Preview</h3>
          </div>
          <div class="preview-content">
            <div class="preview-item">
              <span class="preview-label">Map Name:</span>
              <span class="preview-value">{{ importPreview.mapName }}</span>
            </div>
            <div class="preview-item">
              <span class="preview-label">Dimensions:</span>
              <span class="preview-value">{{ importPreview.dimensions }}</span>
            </div>
            <div class="preview-item">
              <span class="preview-label">Buildings:</span>
              <span class="preview-value">{{ importPreview.buildingsCount }}</span>
            </div>
            <div class="preview-item">
              <span class="preview-label">Rooms:</span>
              <span class="preview-value">{{ importPreview.roomsCount }}</span>
            </div>
            <div class="preview-item">
              <span class="preview-label">Exported:</span>
              <span class="preview-value">{{ importPreview.exportedAt }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="import-modal-footer">
        <button @click="$emit('close')" class="cancel-button">
          <span class="button-icon">↩️</span>
          <span class="button-text">Cancel</span>
        </button>
        <button 
          @click="handleImport" 
          class="import-button" 
          :disabled="!selectedFile || importing"
          :class="{ 'loading': importing }"
        >
          <span v-if="importing" class="button-icon">⏳</span>
          <span v-else class="button-icon">📥</span>
          <span class="button-text">{{ importing ? 'Importing...' : 'Import Map' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ImportMapModal',
  props: {
    show: {
      type: Boolean,
      default: false
    },
    importing: {
      type: Boolean,
      default: false
    },
    uploadProgress: {
      type: Number,
      default: 0
    },
    uploadStatus: {
      type: String,
      default: ''
    }
  },
  emits: ['close', 'import'],
  data() {
    return {
      selectedFile: null,
      isDragOver: false,
      importPreview: null
    }
  },
  watch: {
    show(newValue) {
      if (newValue) {
        this.resetModal()
      }
    }
  },
  methods: {
    resetModal() {
      this.selectedFile = null
      this.isDragOver = false
      this.importPreview = null
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = ''
      }
    },

    handleFileSelect(event) {
      const file = event.target.files[0]
      if (file && (file.type === 'application/zip' || file.name.endsWith('.zip'))) {
        this.selectedFile = file
        this.previewFile()
      } else {
        this.$emit('show-toast', 'Invalid File', 'Please select a valid ZIP file', 'error')
      }
    },

    handleFileDrop(event) {
      this.isDragOver = false
      const files = event.dataTransfer.files
      if (files.length > 0) {
        const file = files[0]
        if (file.type === 'application/zip' || file.name.endsWith('.zip')) {
          this.selectedFile = file
          this.previewFile()
        } else {
          this.$emit('show-toast', 'Invalid File', 'Please drop a valid ZIP file', 'error')
        }
      }
    },

    clearSelectedFile() {
      this.selectedFile = null
      this.importPreview = null
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = ''
      }
    },

    async previewFile() {
      try {
        // For ZIP files, we'll show basic file info
        // The actual preview will be done on the server side
        this.importPreview = {
          mapName: 'Map Export (ZIP)',
          dimensions: 'Will be determined on import',
          buildingsCount: 'Will be determined on import',
          roomsCount: 'Will be determined on import',
          exportedAt: 'Will be determined on import'
        }
      } catch (error) {
        this.$emit('show-toast', 'File Error', 'Could not read the selected file', 'error')
        this.clearSelectedFile()
      }
    },

    async handleImport() {
      if (!this.selectedFile) {
        this.$emit('show-toast', 'No File Selected', 'Please select a map export file', 'error')
        return
      }

      try {
        // Pass the file directly to the parent component
        this.$emit('import', this.selectedFile)
      } catch (error) {
        this.$emit('show-toast', 'Import Error', 'Failed to process the selected file', 'error')
      }
    },

    readFileAsText(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.onload = (e) => resolve(e.target.result)
        reader.onerror = (e) => reject(e)
        reader.readAsText(file)
      })
    },

    formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes'
      const k = 1024
      const sizes = ['Bytes', 'KB', 'MB', 'GB']
      const i = Math.floor(Math.log(bytes) / Math.log(k))
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
    }
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

/* Modal Overlay */
.import-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 10000;
  backdrop-filter: blur(4px);
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

/* Modal Content */
.import-modal-content {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
  animation: modalSlideIn 0.3s ease-out;
  display: flex;
  flex-direction: column;
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

/* Modal Header */
.import-modal-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  position: relative;
}

.header-content {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  flex: 1;
}

.header-icon {
  font-size: 32px;
  margin-top: 4px;
}

.header-text {
  flex: 1;
}

.modal-title {
  margin: 0 0 8px 0;
  font-size: 24px;
  font-weight: 700;
  line-height: 1.2;
}

.modal-subtitle {
  margin: 0;
  font-size: 14px;
  opacity: 0.9;
  line-height: 1.4;
}

.close-button {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 20px;
}

.close-button:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.1);
}

/* Modal Body */
.import-modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

/* Section Styles */
.instructions-section,
.upload-section,
.progress-section,
.preview-section {
  margin-bottom: 24px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.section-icon {
  font-size: 20px;
}

.section-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}

/* Instructions */
.instructions-content {
  background: #f8fafc;
  border-radius: 12px;
  padding: 20px;
  border-left: 4px solid #3b82f6;
}

.instruction-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 12px;
}

.instruction-item:last-child {
  margin-bottom: 0;
}

.instruction-number {
  background: #3b82f6;
  color: white;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
  margin-top: 2px;
}

.instruction-text {
  font-size: 14px;
  color: #4b5563;
  line-height: 1.5;
}

/* File Upload */
.file-upload-area {
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  padding: 40px 20px;
  text-align: center;
  transition: all 0.3s;
  cursor: pointer;
  background: #fafafa;
  min-height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-upload-area:hover,
.file-upload-area.drag-over {
  border-color: #3b82f6;
  background: #f0f9ff;
}

.file-upload-area.has-file {
  border-color: #10b981;
  background: #f0fdf4;
}

.upload-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.upload-icon {
  font-size: 48px;
  color: #6b7280;
}

.upload-text h4 {
  margin: 0 0 4px 0;
  font-size: 18px;
  font-weight: 600;
  color: #374151;
}

.upload-text p {
  margin: 0;
  font-size: 14px;
  color: #6b7280;
}

.browse-button {
  background: #3b82f6;
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.browse-button:hover {
  background: #2563eb;
  transform: translateY(-1px);
}

/* Selected File */
.selected-file-content {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: white;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  width: 100%;
  max-width: 400px;
}

.file-icon {
  font-size: 32px;
  color: #10b981;
}

.file-details {
  flex: 1;
  text-align: left;
}

.file-name {
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 4px;
}

.file-size {
  font-size: 12px;
  color: #6b7280;
}

.remove-file-button {
  background: #ef4444;
  color: white;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 16px;
}

.remove-file-button:hover {
  background: #dc2626;
  transform: scale(1.1);
}

/* Progress Section */
.progress-content {
  background: #f8fafc;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #e5e7eb;
}

.progress-bar {
  width: 100%;
  height: 24px;
  background-color: #f0f0f0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 12px;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #10b981 0%, #059669 100%);
  border-radius: 12px;
  transition: width 0.3s ease;
  position: relative;
}

.progress-fill::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  bottom: 0;
  right: 0;
  background-image: linear-gradient(
    -45deg,
    rgba(255, 255, 255, 0.2) 25%,
    transparent 25%,
    transparent 50%,
    rgba(255, 255, 255, 0.2) 50%,
    rgba(255, 255, 255, 0.2) 75%,
    transparent 75%,
    transparent
  );
  background-size: 20px 20px;
  animation: progressMove 1s linear infinite;
}

@keyframes progressMove {
  0% {
    background-position: 0 0;
  }
  100% {
    background-position: 20px 20px;
  }
}

.progress-text {
  text-align: center;
  font-size: 14px;
  color: #374151;
  font-weight: 600;
}

/* Preview Section */
.preview-content {
  background: #f8fafc;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #e5e7eb;
}

.preview-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #e5e7eb;
}

.preview-item:last-child {
  border-bottom: none;
}

.preview-label {
  font-weight: 500;
  color: #374151;
}

.preview-value {
  color: #6b7280;
  font-weight: 600;
}

/* Modal Footer */
.import-modal-footer {
  padding: 24px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  background: #f9fafb;
}

.cancel-button,
.import-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.cancel-button {
  background: #6b7280;
  color: white;
}

.cancel-button:hover {
  background: #4b5563;
  transform: translateY(-1px);
}

.import-button {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.import-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  transform: translateY(-1px);
}

.import-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.import-button.loading {
  opacity: 0.8;
}

.button-icon {
  font-size: 16px;
}

.button-text {
  font-size: 14px;
}

/* Success Section Styles */
.success-section {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border: 2px solid #10b981;
  border-radius: 12px;
  padding: 20px;
  margin: 16px 0;
  animation: successPulse 0.6s ease-out;
}

@keyframes successPulse {
  0% {
    transform: scale(0.95);
    opacity: 0.8;
  }
  50% {
    transform: scale(1.02);
    opacity: 1;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.success-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
}

.success-message {
  color: #065f46;
  font-weight: 600;
  font-size: 16px;
}

.success-icon {
  font-size: 32px;
  animation: bounce 1s ease-in-out;
}

@keyframes bounce {
  0%, 20%, 50%, 80%, 100% {
    transform: translateY(0);
  }
  40% {
    transform: translateY(-10px);
  }
  60% {
    transform: translateY(-5px);
  }
}

/* Responsive Design */
@media (max-width: 768px) {
  .import-modal-content {
    width: 95%;
    margin: 20px;
  }
  
  .import-modal-header {
    padding: 20px;
  }
  
  .modal-title {
    font-size: 20px;
  }
  
  .import-modal-body {
    padding: 20px;
  }
  
  .import-modal-footer {
    padding: 20px;
    flex-direction: column;
  }
  
  .cancel-button,
  .import-button {
    width: 100%;
    justify-content: center;
  }
}
</style>
