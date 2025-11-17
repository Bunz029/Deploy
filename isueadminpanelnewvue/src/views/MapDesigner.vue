<template>
  <div class="map-designer">
    <Map3DEditor @map-created="handleMapCreated" />
    
    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="modal-overlay" @click="closeSuccessModal">
      <div class="modal-content" @click.stop>
        <h3>🗺️ Map Created Successfully!</h3>
        <div class="map-preview">
          <img :src="createdMapData.imageData" alt="Created Map" />
        </div>
        <div class="map-stats">
          <div class="stat">
            <strong>Objects:</strong> {{ createdMapData.objectCount }}
          </div>
          <div class="stat">
            <strong>Complexity:</strong> {{ createdMapData.complexity }}
          </div>
        </div>
        <div class="modal-actions">
          <button @click="saveMapLayout" class="btn btn-primary">
            Save as Map Layout
          </button>
          <button @click="downloadMap" class="btn btn-secondary">
            Download PNG
          </button>
          <button @click="closeSuccessModal" class="btn btn-outline">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Map3DEditor from '../components/Map3DEditor.vue'
import axios from 'axios'

export default {
  name: 'MapDesigner',
  components: {
    Map3DEditor
  },
  data() {
    return {
      showSuccessModal: false,
      createdMapData: null
    }
  },
  methods: {
    handleMapCreated(mapData) {
      this.createdMapData = mapData
      this.showSuccessModal = true
    },
    
    async saveMapLayout() {
      if (!this.createdMapData) return
      
      try {
        // Convert data URL to blob
        const response = await fetch(this.createdMapData.imageData)
        const blob = await response.blob()
        
        // Create form data
        const formData = new FormData()
        formData.append('name', `3D Map Layout ${new Date().toLocaleString()}`)
        formData.append('description', `Generated 3D map with ${this.createdMapData.objectCount} objects`)
        formData.append('image', blob, 'map-layout.png')
        formData.append('width', '800') // Default width
        formData.append('height', '600') // Default height
        formData.append('is_active', '0') // Not active by default
        
        // Upload to backend
        await axios.post((process.env.VUE_APP_API_BASE || 'https://isuecampusmap.site/backend/api').replace(/\/$/, '') + '/map', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        
        alert('Map layout saved successfully!')
        this.closeSuccessModal()
        
      } catch (error) {
        console.error('Error saving map:', error)
        alert('Error saving map layout. Please try again.')
      }
    },
    
    downloadMap() {
      if (!this.createdMapData) return
      
      const link = document.createElement('a')
      link.download = `map-layout-${new Date().getTime()}.png`
      link.href = this.createdMapData.imageData
      link.click()
    },
    
    closeSuccessModal() {
      this.showSuccessModal = false
      this.createdMapData = null
    }
  }
}
</script>

<style scoped>
.map-designer {
  width: 100%;
  height: 100vh;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  max-width: 500px;
  width: 90%;
  text-align: center;
}

.modal-content h3 {
  margin: 0 0 20px 0;
  color: #2c3e50;
  font-size: 1.5em;
}

.map-preview {
  margin: 20px 0;
  border: 2px solid #ecf0f1;
  border-radius: 8px;
  overflow: hidden;
}

.map-preview img {
  width: 100%;
  height: auto;
  display: block;
}

.map-stats {
  display: flex;
  justify-content: space-around;
  margin: 20px 0;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
}

.stat {
  font-size: 14px;
  color: #2c3e50;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
  margin-top: 20px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: background-color 0.2s;
}

.btn-primary {
  background: #27ae60;
  color: white;
}

.btn-primary:hover {
  background: #229954;
}

.btn-secondary {
  background: #3498db;
  color: white;
}

.btn-secondary:hover {
  background: #2980b9;
}

.btn-outline {
  background: transparent;
  color: #7f8c8d;
  border: 1px solid #bdc3c7;
}

.btn-outline:hover {
  background: #ecf0f1;
}
</style>
