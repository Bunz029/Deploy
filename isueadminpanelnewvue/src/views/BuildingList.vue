<template>
  <div class="building-list">
    <div class="header">
      <h1>Building Management</h1>
      <div class="header-actions">
        <select v-model="selectedMap" class="map-filter">
          <option value="">All Maps</option>
          <option v-for="map in maps" :key="map.id" :value="map.id">
            {{ map.name }}
          </option>
        </select>
        <button class="add-button" @click="showAddModal = true">
          Add New Building
        </button>
      </div>
    </div>

    <div class="buildings-grid">
      <div v-for="building in filteredBuildings" :key="building.id" class="building-card">
        <div class="building-image">
          <img :src="building.image_url" :alt="building.name">
          <div class="building-status" :class="{ active: building.is_active }">
            {{ building.is_active ? 'Active' : 'Inactive' }}
          </div>
        </div>
        <div class="building-info">
          <h3>{{ building.name }}</h3>
          <p>Map: {{ getMapName(building.map_id) }}</p>
          <p>Position: ({{ building.x }}, {{ building.y }})</p>
          <p>Dimensions: {{ building.width }}x{{ building.height }}</p>
        </div>
        <div class="building-actions">
          <button @click="editBuilding(building)" class="edit-button">Edit</button>
          <button @click="showDeleteModal(building)" class="delete-button">Delete</button>
          <button 
            @click="toggleBuildingStatus(building)"
            :class="['status-button', building.is_active ? 'deactivate' : 'activate']"
          >
            {{ building.is_active ? 'Deactivate' : 'Activate' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Building Modal -->
    <div v-if="showAddModal || editingBuilding" class="modal">
      <div class="modal-content">
        <h2>{{ editingBuilding ? 'Edit Building' : 'Add New Building' }}</h2>
        <form @submit.prevent="saveBuilding">
          <div class="form-group">
            <label>Building Name</label>
            <input v-model="buildingForm.name" required>
          </div>
          <div class="form-group">
            <label>Map</label>
            <select v-model="buildingForm.map_id" required>
              <option v-for="map in maps" :key="map.id" :value="map.id">
                {{ map.name }}
              </option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>X Position</label>
              <input type="number" v-model="buildingForm.x" required>
            </div>
            <div class="form-group">
              <label>Y Position</label>
              <input type="number" v-model="buildingForm.y" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Width</label>
              <input type="number" v-model="buildingForm.width" required>
            </div>
            <div class="form-group">
              <label>Height</label>
              <input type="number" v-model="buildingForm.height" required>
            </div>
          </div>
          <div class="form-group">
            <label>Building Image</label>
            <input type="file" @change="handleImageUpload" accept="image/*">
          </div>
          <div class="form-actions">
            <button type="submit" class="save-button">Save</button>
            <button type="button" @click="closeModal" class="cancel-button">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click="closeDeleteModal">
      <div class="modal-content" @click.stop>
        <!-- Close button -->
        <button @click="closeDeleteModal" class="modal-close-btn">&times;</button>
        
        <div class="modal-header">
          <div class="warning-icon">⚠️</div>
          <h2>Delete Building</h2>
        </div>
        
        <div class="modal-body">
          <p>Are you sure you want to delete building <strong>{{ buildingToDelete?.name || '' }}</strong>? This action cannot be undone.</p>
        </div>
        
        <div class="modal-footer">
          <div v-if="deleteCountdown > 0" class="countdown-display">
            {{ deleteCountdown }}
          </div>
          <div v-else class="action-buttons">
            <button @click="closeDeleteModal" class="cancel-button">Cancel</button>
            <button @click="confirmDeleteBuilding" class="delete-button">Delete</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'BuildingListView',
  data() {
    return {
      buildings: [],
      maps: [],
      selectedMap: '',
      showAddModal: false,
      editingBuilding: null,
      showDeleteConfirm: false,
      buildingToDelete: null,
      deleteCountdown: 3,
      countdownInterval: null,
      buildingForm: {
        name: '',
        map_id: '',
        x: '',
        y: '',
        width: '',
        height: '',
        image: null
      }
    }
  },
  computed: {
    filteredBuildings() {
      if (!this.selectedMap) return this.buildings
      return this.buildings.filter(b => b.map_id === this.selectedMap)
    }
  },
  async created() {
    await Promise.all([
      this.fetchBuildings(),
      this.fetchMaps()
    ])
  },
  beforeUnmount() {
    this.stopCountdown()
  },
  methods: {
    async fetchBuildings() {
      try {
        const response = await axios.get('/buildings')
        this.buildings = response.data
      } catch (error) {
        console.error('Error fetching buildings:', error)
      }
    },
    async fetchMaps() {
      try {
        const response = await axios.get('/maps')
        this.maps = response.data
      } catch (error) {
        console.error('Error fetching maps:', error)
      }
    },
    getMapName(mapId) {
      const map = this.maps.find(m => m.id === mapId)
      return map ? map.name : 'Unknown Map'
    },
    editBuilding(building) {
      this.editingBuilding = building
      this.buildingForm = { ...building }
    },
    showDeleteModal(building) {
      this.buildingToDelete = building
      this.showDeleteConfirm = true
      this.startCountdown()
    },
    closeDeleteModal() {
      this.stopCountdown()
      this.showDeleteConfirm = false
      this.buildingToDelete = null
      this.deleteCountdown = 3
    },
    startCountdown() {
      this.deleteCountdown = 3
      this.countdownInterval = setInterval(() => {
        this.deleteCountdown--
        if (this.deleteCountdown <= 0) {
          this.stopCountdown()
        }
      }, 1000)
    },
    stopCountdown() {
      if (this.countdownInterval) {
        clearInterval(this.countdownInterval)
        this.countdownInterval = null
      }
    },
    async confirmDeleteBuilding() {
      if (!this.buildingToDelete) return
      
      try {
        await axios.delete(`/buildings/${this.buildingToDelete.id}`)
        await this.fetchBuildings()
        this.closeDeleteModal()
      } catch (error) {
        console.error('Error deleting building:', error)
        this.closeDeleteModal()
      }
    },
    async toggleBuildingStatus(building) {
      try {
        await axios.put(`/buildings/${building.id}/toggle-status`)
        await this.fetchBuildings()
      } catch (error) {
        console.error('Error toggling building status:', error)
      }
    },
    handleImageUpload(event) {
      this.buildingForm.image = event.target.files[0]
    },
    async saveBuilding() {
      const formData = new FormData()
      Object.keys(this.buildingForm).forEach(key => {
        if (this.buildingForm[key] !== null) {
          formData.append(key, this.buildingForm[key])
        }
      })

      try {
        if (this.editingBuilding) {
          await axios.put(`/buildings/${this.editingBuilding.id}`, formData)
        } else {
          await axios.post('/buildings', formData)
        }
        await this.fetchBuildings()
        this.closeModal()
      } catch (error) {
        console.error('Error saving building:', error)
        
        // Extract detailed error message from response
        let errorMessage = 'Unknown error'
        if (error.response) {
          // Handle different response structures
          if (typeof error.response.data === 'string') {
            // Try to parse as JSON first
            try {
              const parsedData = JSON.parse(error.response.data)
              if (parsedData.message) {
                errorMessage = parsedData.message
              } else if (parsedData.error) {
                errorMessage = parsedData.error
              } else {
                errorMessage = error.response.data
              }
            } catch (e) {
              errorMessage = error.response.data
            }
          } else if (error.response.data && typeof error.response.data === 'object') {
            if (error.response.data.message) {
              errorMessage = error.response.data.message
            } else if (error.response.data.error) {
              errorMessage = error.response.data.error
            } else if (error.response.data.errors) {
              // Handle Laravel validation errors
              const errors = error.response.data.errors
              const firstError = Object.values(errors)[0]
              if (Array.isArray(firstError)) {
                errorMessage = firstError[0]
              } else {
                errorMessage = firstError
              }
            }
          }
        } else if (error.message) {
          errorMessage = error.message
        }
        
        // Show specific warnings based on error content and error codes
        let errorCode = null
        if (error.response && error.response.data) {
          if (typeof error.response.data === 'string') {
            try {
              const parsedData = JSON.parse(error.response.data)
              errorCode = parsedData.error_code
            } catch (e) {
              // Not JSON, continue with message parsing
            }
          } else if (error.response.data.error_code) {
            errorCode = error.response.data.error_code
          }
        }

        if (errorCode === 'DUPLICATE_EMAIL' || errorMessage.includes('Duplicate email addresses found') || errorMessage.includes('already in use')) {
          this.$refs.toast.warning('Duplicate Email Detected', errorMessage)
        } else if (errorMessage.includes('validation') || errorMessage.includes('required')) {
          this.$refs.toast.warning('Validation Error', errorMessage)
        } else if (errorMessage.includes('permission') || errorMessage.includes('unauthorized')) {
          this.$refs.toast.error('Access Denied', errorMessage)
        } else {
          this.$refs.toast.error('Save Failed', `Failed to save building: ${errorMessage}`)
        }
      }
    },
    closeModal() {
      this.showAddModal = false
      this.editingBuilding = null
      this.buildingForm = {
        name: '',
        map_id: '',
        x: '',
        y: '',
        width: '',
        height: '',
        image: null
      }
    }
  }
}
</script>

<style scoped>
.building-list {
  max-width: 1200px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
}

.map-filter {
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.add-button {
  background: #2c3e50;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
}

.buildings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.building-card {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.building-image {
  position: relative;
  height: 200px;
}

.building-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.building-status {
  position: absolute;
  top: 1rem;
  right: 1rem;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
}

.building-status.active {
  background: #27ae60;
}

.building-info {
  padding: 1rem;
}

.building-info p {
  margin: 0.25rem 0;
  color: #666;
}

.building-actions {
  padding: 1rem;
  display: flex;
  gap: 0.5rem;
}

.building-actions button {
  flex: 1;
  padding: 0.5rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.edit-button {
  background: #3498db;
  color: white;
}

.delete-button {
  background: #e74c3c;
  color: white;
}

.status-button {
  color: white;
}

.status-button.activate {
  background: #27ae60;
}

.status-button.deactivate {
  background: #e67e22;
}

.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-content {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  width: 100%;
  max-width: 500px;
}

.form-group {
  margin-bottom: 1rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.form-actions {
  display: flex;
  gap: 1rem;
  margin-top: 1.5rem;
}

.form-actions button {
  flex: 1;
  padding: 0.5rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.save-button {
  background: #2c3e50;
  color: white;
}

.cancel-button {
  background: #95a5a6;
  color: white;
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
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 20px 25px rgba(0, 0, 0, 0.15);
  position: relative;
}

.modal-close-btn {
  position: absolute;
  top: 12px;
  right: 12px;
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #6b7280;
  padding: 4px;
  line-height: 1;
  z-index: 1;
}

.modal-close-btn:hover {
  color: #374151;
}

.modal-header {
  text-align: center;
  padding: 24px 24px 0;
}

.warning-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.modal-header h2 {
  margin: 0;
  font-size: 18px;
  color: #1f2937;
}

.modal-body {
  padding: 20px 24px;
  text-align: center;
}

.modal-body p {
  color: #6b7280;
  line-height: 1.5;
  margin: 0;
}

.modal-footer {
  padding: 0 24px 24px;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60px;
}

.countdown-display {
  font-size: 48px;
  font-weight: bold;
  color: #dc2626;
  width: 80px;
  height: 80px;
  border-radius: 50%;
  border: 4px solid #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}

.action-buttons {
  display: flex;
  gap: 12px;
  width: 100%;
}

.cancel-button {
  flex: 1;
  padding: 12px 20px;
  background: #6b7280;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.2s;
}

.cancel-button:hover {
  background: #4b5563;
}

.delete-button {
  flex: 1;
  padding: 12px 20px;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.2s;
}

.delete-button:hover {
  background: #b91c1c;
}
</style> 