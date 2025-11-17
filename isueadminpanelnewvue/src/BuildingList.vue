<template>
  <div class="building-list">
    <div class="header">
      <h1>Building Management</h1>
      <button class="btn-primary" @click="showAddBuildingModal = true">
        Add New Building
      </button>
    </div>

    <div class="filters">
      <div class="form-group">
        <label>Filter by Map</label>
        <select v-model="selectedMapId" @change="fetchBuildings">
          <option value="">All Maps</option>
          <option 
            v-for="map in maps" 
            :key="map.id" 
            :value="map.id"
          >
            {{ map.name }}
          </option>
        </select>
      </div>
    </div>

    <div class="buildings-grid">
      <div v-for="building in buildings" :key="building.id" class="building-card">
        <div class="building-preview">
          <img 
            :src="building.image_path || '/placeholder-building.png'" 
            :alt="building.name"
          >
          <div class="building-status" :class="{ active: building.is_active }">
            {{ building.is_active ? 'Active' : 'Inactive' }}
          </div>
        </div>
        
        <div class="building-info">
          <h3>{{ building.name }}</h3>
          <p class="map-name">Map: {{ getMapName(building.map_id) }}</p>
          <p class="coordinates">
            Position: ({{ building.x_coordinate }}, {{ building.y_coordinate }})
          </p>
          <p class="dimensions">
            Size: {{ building.width }}x{{ building.height }}
          </p>
          <p class="description">{{ building.description }}</p>
        </div>
        
        <div class="building-actions">
          <button class="btn-secondary" @click="editBuilding(building)">
            Edit
          </button>
          <button class="btn-danger" @click="deleteBuilding(building.id)">
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Building Modal -->
    <div v-if="showAddBuildingModal" class="modal">
      <div class="modal-content">
        <h2>{{ editingBuilding ? 'Edit Building' : 'Add New Building' }}</h2>
        
        <form @submit.prevent="saveBuilding">
          <div class="form-group">
            <label>Building Name</label>
            <input 
              type="text" 
              v-model="buildingForm.name" 
              required
              placeholder="Enter building name"
            >
          </div>

          <div class="form-group">
            <label>Map</label>
            <select v-model="buildingForm.map_id" required>
              <option value="">Select a map</option>
              <option 
                v-for="map in maps" 
                :key="map.id" 
                :value="map.id"
              >
                {{ map.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea 
              v-model="buildingForm.description" 
              rows="3"
              placeholder="Enter building description"
            ></textarea>
          </div>

          <div class="form-group">
            <label>Position</label>
            <div class="coordinates-input">
              <input 
                type="number" 
                v-model="buildingForm.x_coordinate" 
                required
                placeholder="X"
              >
              <span>,</span>
              <input 
                type="number" 
                v-model="buildingForm.y_coordinate" 
                required
                placeholder="Y"
              >
            </div>
          </div>

          <div class="form-group">
            <label>Dimensions</label>
            <div class="dimensions-input">
              <input 
                type="number" 
                v-model="buildingForm.width" 
                required
                placeholder="Width"
              >
              <span>x</span>
              <input 
                type="number" 
                v-model="buildingForm.height" 
                required
                placeholder="Height"
              >
            </div>
          </div>

          <div class="form-group">
            <label>Building Image</label>
            <input 
              type="file" 
              @change="handleImageUpload" 
              accept="image/*"
            >
          </div>

          <div class="form-group">
            <label class="checkbox-label">
              <input 
                type="checkbox" 
                v-model="buildingForm.is_active"
              >
              Set as Active
            </label>
          </div>

          <div class="modal-actions">
            <button type="button" class="btn-secondary" @click="closeModal">
              Cancel
            </button>
            <button type="submit" class="btn-primary">
              {{ editingBuilding ? 'Update' : 'Create' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'BuildingList',
  data() {
    return {
      buildings: [],
      maps: [],
      selectedMapId: '',
      showAddBuildingModal: false,
      editingBuilding: null,
      buildingForm: {
        name: '',
        map_id: '',
        description: '',
        x_coordinate: '',
        y_coordinate: '',
        width: '',
        height: '',
        image_path: '',
        is_active: false
      }
    }
  },
  async created() {
    await Promise.all([
      this.fetchMaps(),
      this.fetchBuildings()
    ])
  },
  methods: {
    async fetchMaps() {
      try {
        const response = await axios.get('/maps')
        this.maps = response.data
      } catch (error) {
        console.error('Error fetching maps:', error)
      }
    },
    async fetchBuildings() {
      try {
        const url = this.selectedMapId
          ? `/buildings?map_id=${this.selectedMapId}`
          : '/buildings'
        const response = await axios.get(url)
        this.buildings = response.data
      } catch (error) {
        console.error('Error fetching buildings:', error)
      }
    },
    getMapName(mapId) {
      const map = this.maps.find(m => m.id === mapId)
      return map ? map.name : 'Unknown Map'
    },
    editBuilding(building) {
      this.editingBuilding = building
      this.buildingForm = { ...building }
      this.showAddBuildingModal = true
    },
    async deleteBuilding(id) {
      if (!confirm('Are you sure you want to delete this building?')) return

      try {
        await axios.delete(`/buildings/${id}`)
        await this.fetchBuildings()
      } catch (error) {
        console.error('Error deleting building:', error)
      }
    },
    async handleImageUpload(event) {
      const file = event.target.files[0]
      if (!file) return

      const formData = new FormData()
      formData.append('image', file)

      try {
        const response = await axios.post('/buildings/upload', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        this.buildingForm.image_path = response.data.path
      } catch (error) {
        console.error('Error uploading image:', error)
      }
    },
    async saveBuilding() {
      try {
        if (this.editingBuilding) {
          await axios.put(`/buildings/${this.editingBuilding.id}`, this.buildingForm)
        } else {
          await axios.post('/buildings', this.buildingForm)
        }
        await this.fetchBuildings()
        this.closeModal()
      } catch (error) {
        console.error('Error saving building:', error)
      }
    },
    closeModal() {
      this.showAddBuildingModal = false
      this.editingBuilding = null
      this.buildingForm = {
        name: '',
        map_id: '',
        description: '',
        x_coordinate: '',
        y_coordinate: '',
        width: '',
        height: '',
        image_path: '',
        is_active: false
      }
    }
  }
}
</script>

<style scoped>
.building-list {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.filters {
  margin-bottom: 2rem;
  padding: 1rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.buildings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 2rem;
}

.building-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.building-preview {
  position: relative;
  height: 200px;
  overflow: hidden;
}

.building-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.building-status {
  position: absolute;
  top: 1rem;
  right: 1rem;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  background: rgba(0, 0, 0, 0.5);
  color: white;
  font-size: 0.875rem;
}

.building-status.active {
  background: #28a745;
}

.building-info {
  padding: 1rem;
}

.building-info h3 {
  margin: 0 0 0.5rem;
  color: #2c3e50;
}

.building-info p {
  margin: 0.25rem 0;
  color: #666;
  font-size: 0.875rem;
}

.building-info .map-name {
  color: #3498db;
  font-weight: 500;
}

.building-info .description {
  margin-top: 0.5rem;
  color: #666;
  font-size: 0.875rem;
  line-height: 1.4;
}

.building-actions {
  padding: 1rem;
  display: flex;
  gap: 1rem;
  border-top: 1px solid #eee;
}

.btn-primary {
  background: #3498db;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
}

.btn-secondary {
  background: #f8f9fa;
  color: #2c3e50;
  border: 1px solid #dee2e6;
  padding: 0.75rem 1.5rem;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
}

.btn-danger {
  background: #dc3545;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
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
  max-height: 90vh;
  overflow-y: auto;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: #2c3e50;
  font-weight: 500;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #dee2e6;
  border-radius: 4px;
}

.coordinates-input,
.dimensions-input {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.coordinates-input input,
.dimensions-input input {
  width: 100px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 2rem;
}
</style>