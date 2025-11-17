<template>
  <div v-if="show" class="rooms-modal-overlay" @click="$emit('close')">
    <div class="rooms-modal-content" @click.stop>
      <div class="rooms-modal-header">
        <h2 class="rooms-modal-title">
          <span class="title-icon">🏠</span>
          Manage Rooms - {{ building?.building_name || building?.name || 'Building' }}
        </h2>
        <button @click="$emit('close')" class="rooms-close-btn">×</button>
      </div>

      <div class="rooms-modal-body">
        <!-- Loading State -->
        <div v-if="loading" class="rooms-loading">
          <div class="spinner"></div>
          <p>Loading rooms...</p>
        </div>

        <!-- Rooms Grid -->
        <div v-else class="rooms-grid">
          <!-- Existing Rooms -->
          <div 
            v-for="room in rooms" 
            :key="room.id" 
            class="room-card"
          >
            <div class="room-thumbnail">
              <img 
                v-if="room.thumbnail_path" 
                :src="getThumbnailUrl(room.thumbnail_path)" 
                :alt="room.name"
                class="room-thumb-image"
              />
              <div v-else class="room-thumb-placeholder">
                <span class="thumb-icon">🏠</span>
              </div>
            </div>
            <div class="room-info">
              <h4 class="room-name">{{ room.name }}</h4>
            </div>
            <div class="room-actions">
              <button @click="editRoom(room)" class="room-action-btn edit" title="Edit Room">
                ✏️
              </button>
              <button @click="deleteRoom(room)" class="room-action-btn delete" title="Delete Room">
                🗑️
              </button>
            </div>
          </div>

          <!-- Add Room Button (always last) -->
          <div class="room-card add-room-card" @click="openAddRoomModal">
            <div class="add-room-content">
              <div class="add-room-icon">+</div>
              <div class="add-room-text">Add Room</div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="rooms.length === 0" class="empty-rooms-state">
            <div class="empty-icon">🏠</div>
            <p class="empty-text">No rooms added yet</p>
            <p class="empty-subtext">Click "Add Room" to create your first 360° room</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add/Edit Room Modal -->
  <div v-if="showRoomModal" class="room-edit-modal-overlay" @click="closeRoomModal">
    <div class="room-edit-modal-content" @click.stop>
      <div class="room-edit-header">
        <h3>{{ editingRoom ? 'Edit Room' : 'Add New Room' }}</h3>
        <button @click="closeRoomModal" class="room-edit-close">×</button>
      </div>
      
      <form @submit.prevent="saveRoom" class="room-edit-form">
        <div class="form-group">
          <label class="form-label">Room Name</label>
          <input 
            v-model="roomForm.name" 
            type="text" 
            class="form-input" 
            placeholder="Enter room name"
            required
          />
        </div>


        <div class="form-group">
          <label class="form-label">360° Panorama Image</label>
          <input 
            ref="panoramaFileInput"
            type="file" 
            @change="handlePanoramaUpload" 
            accept="image/*"
            class="form-file-input"
          />
          <p class="form-help">Upload a 360° panoramic image (JPG, PNG)</p>
        </div>

        <!-- Image Preview -->
        <div v-if="roomForm.panorama_preview" class="panorama-preview">
          <img :src="roomForm.panorama_preview" alt="Panorama Preview" />
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-save" :disabled="roomSaving">
            {{ roomSaving ? 'Saving...' : (editingRoom ? 'Update Room' : 'Add Room') }}
          </button>
          <button type="button" @click="closeRoomModal" class="btn-cancel">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Room Delete Confirmation Modal -->
  <div v-if="showDeleteModal" class="delete-modal-overlay" @click="closeDeleteModal">
    <div class="delete-modal-content" @click.stop>
      <!-- Close button -->
      <button @click="closeDeleteModal" class="delete-modal-close">&times;</button>
      
      <div class="delete-modal-header">
        <div class="delete-modal-icon">⚠️</div>
        <h3 class="delete-modal-title">Delete Room</h3>
      </div>
      
      <p class="delete-modal-message">
        Are you sure you want to delete room <strong>{{ roomToDelete ? roomToDelete.name : 'Unknown' }}</strong>? This action cannot be undone.
      </p>
      
      <div class="delete-modal-actions">
        <div class="delete-modal-buttons">
          <button @click="closeDeleteModal" class="delete-modal-cancel">
            Cancel
          </button>
          <button @click="confirmDeleteRoom" class="delete-modal-confirm">
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'RoomsManagementModal',
  props: {
    show: {
      type: Boolean,
      default: false
    },
    building: {
      type: Object,
      default: null
    }
  },
  emits: ['close', 'room-added', 'room-updated', 'room-deleted'],
  data() {
    return {
      loading: false,
      rooms: [],
      showRoomModal: false,
      editingRoom: null,
      roomSaving: false,
      showDeleteModal: false,
      roomToDelete: null,
      roomForm: {
        name: '',
        panorama_file: null,
        panorama_preview: null
      }
    }
  },
  watch: {
    show(newValue) {
      if (newValue && this.building) {
        this.loadRooms()
      }
    }
  },
  methods: {
    async loadRooms() {
      if (!this.building?.id) {
        // If no building ID, show empty state
        this.rooms = []
        this.loading = false
        return
      }

      this.loading = true
      try {
        const response = await axios.get(`/buildings/${this.building.id}/rooms/admin`)
        this.rooms = response.data
      } catch (error) {
        console.error('Error loading rooms:', error)
        this.$emit('show-toast', 'Failed to load rooms', 'error')
        this.rooms = []
      } finally {
        this.loading = false
      }
    },

    openAddRoomModal() {
      this.editingRoom = null
      this.roomForm = {
        name: '',
        panorama_file: null,
        panorama_preview: null
      }
      this.showRoomModal = true
      
      // Reset file input to ensure clean state
      this.$nextTick(() => {
        if (this.$refs.panoramaFileInput) {
          this.$refs.panoramaFileInput.value = ''
        }
      })
    },

    editRoom(room) {
      this.editingRoom = room
      this.roomForm = {
        name: room.name,
        panorama_file: null,
        panorama_preview: room.panorama_image_path ? this.getPanoramaUrl(room.panorama_image_path) : null
      }
      this.showRoomModal = true
      
      // Reset file input to ensure clean state
      this.$nextTick(() => {
        if (this.$refs.panoramaFileInput) {
          this.$refs.panoramaFileInput.value = ''
        }
      })
    },

    closeRoomModal() {
      this.showRoomModal = false
      this.editingRoom = null
      this.roomForm = {
        name: '',
        panorama_file: null,
        panorama_preview: null
      }
    },

    handlePanoramaUpload(event) {
      const file = event.target.files[0]
      
      if (file) {
        this.roomForm.panorama_file = file
        
        // Create preview
        const reader = new FileReader()
        reader.onload = (e) => {
          this.roomForm.panorama_preview = e.target.result
        }
        reader.readAsDataURL(file)
      }
    },

    async saveRoom() {
      if (!this.building?.id) {
        this.$emit('show-toast', 'Building must be saved before adding rooms', 'error')
        return
      }

      // Debug: Log form data

      this.roomSaving = true
      try {
        const formData = new FormData()
        formData.append('building_id', this.building.id)
        formData.append('name', this.roomForm.name)
        
        if (this.roomForm.panorama_file) {
          formData.append('panorama_image', this.roomForm.panorama_file)
        }

        let response
        if (this.editingRoom) {
          // For updates, don't send building_id as it's not needed
          const updateFormData = new FormData()
          updateFormData.append('name', this.roomForm.name)
          
          if (this.roomForm.panorama_file) {
            updateFormData.append('panorama_image', this.roomForm.panorama_file)
          }
          
          // Debug: Log FormData contents
          
          // Debug: Check if file is actually in FormData
          
          // Debug: Check if FormData has the file
          
          response = await axios.put(`/rooms/${this.editingRoom.id}`, updateFormData)
          this.$emit('room-updated', response.data.room)
        } else {
          response = await axios.post('/rooms', formData)
          this.$emit('room-added', response.data.room)
        }

        this.closeRoomModal()
        this.loadRooms()
        
        const action = this.editingRoom ? 'updated' : 'added'
        this.$emit('show-toast', `Room ${action} successfully`, 'success')

      } catch (error) {
        console.error('Error saving room:', error)
        this.$emit('show-toast', 'Failed to save room', 'error')
      } finally {
        this.roomSaving = false
      }
    },

    deleteRoom(room) {
      this.roomToDelete = room
      this.showDeleteModal = true
    },

    closeDeleteModal() {
      this.showDeleteModal = false
      this.roomToDelete = null
    },

    async confirmDeleteRoom() {
      if (!this.roomToDelete) return

      try {
        await axios.delete(`/rooms/${this.roomToDelete.id}`)
        this.loadRooms()
        this.$emit('room-deleted', this.roomToDelete)
        this.$emit('show-toast', 'Room deleted successfully', 'success')
        this.closeDeleteModal()
      } catch (error) {
        console.error('Error deleting room:', error)
        this.$emit('show-toast', 'Failed to delete room', 'error')
      }
    },

    getThumbnailUrl(path) {
      if (!path) return null
      return `${process.env.VUE_APP_STORAGE_BASE || 'https://isuecampusmap.site/backend/storage/'}${path}`
    },

    getPanoramaUrl(path) {
      if (!path) return null
      return `${process.env.VUE_APP_STORAGE_BASE || 'https://isuecampusmap.site/backend/storage/'}${path}`
    }
  }
}
</script>

<style scoped>
.rooms-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.rooms-modal-content {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 800px;
  max-height: 80vh;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.rooms-modal-header {
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: white;
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.rooms-modal-title {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
}

.title-icon {
  font-size: 24px;
}

.rooms-close-btn {
  background: none;
  border: none;
  color: white;
  font-size: 24px;
  cursor: pointer;
  padding: 5px;
  border-radius: 50%;
  transition: background-color 0.2s;
}

.rooms-close-btn:hover {
  background: rgba(255, 255, 255, 0.2);
}

.rooms-modal-body {
  padding: 20px;
  max-height: 60vh;
  overflow-y: auto;
}

.rooms-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px;
  color: #666;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.rooms-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px;
}

.room-card {
  border: 2px solid #e1e5e9;
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.3s ease;
  background: white;
}

.room-card:hover {
  border-color: #2dbb74;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
  transform: translateY(-2px);
}

.add-room-card {
  border: 2px dashed #ccc;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  background: #f8f9fa;
}

.add-room-card:hover {
  border-color: #2dbb74;
  background: #f0f2ff;
}

.add-room-content {
  text-align: center;
  color: #666;
}

.add-room-icon {
  font-size: 48px;
  margin-bottom: 10px;
  color: #2dbb74;
}

.add-room-text {
  font-size: 16px;
  font-weight: 500;
}

.room-thumbnail {
  height: 120px;
  background: #f5f5f5;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.room-thumb-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.room-thumb-placeholder {
  color: #ccc;
  font-size: 48px;
}

.room-info {
  padding: 16px;
  flex-grow: 1;
}

.room-name {
  margin: 0 0 8px 0;
  font-size: 16px;
  font-weight: 600;
  color: #333;
}


.room-actions {
  padding: 12px 16px;
  background: #f8f9fa;
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.room-action-btn {
  background: none;
  border: none;
  padding: 8px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  transition: background-color 0.2s;
}

.room-action-btn.edit:hover {
  background: #e3f2fd;
}

.room-action-btn.delete:hover {
  background: #ffebee;
}

.empty-rooms-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 60px 20px;
  color: #666;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 16px;
  opacity: 0.5;
}

.empty-text {
  font-size: 18px;
  font-weight: 500;
  margin-bottom: 8px;
}

.empty-subtext {
  font-size: 14px;
  color: #999;
}

/* Room Edit Modal Styles */
.room-edit-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1100;
}

.room-edit-modal-content {
  background: white;
  border-radius: 12px;
  width: 500px;
  max-width: 500px;
  max-height: 80vh;
  overflow: auto;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.room-edit-header {
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: white;
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.room-edit-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.room-edit-close {
  background: none;
  border: none;
  color: white;
  font-size: 24px;
  cursor: pointer;
  padding: 5px;
  border-radius: 50%;
  transition: background-color 0.2s;
}

.room-edit-close:hover {
  background: rgba(255, 255, 255, 0.2);
}

.room-edit-form {
  padding: 25px;
  max-height: 60vh;
  max-width: 425px;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #333;
}

.form-input, .form-textarea {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  transition: border-color 0.2s;
}

.form-input:focus, .form-textarea:focus {
  outline: none;
  border-color: #2dbb74;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-file-input {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.form-help {
  margin-top: 4px;
  font-size: 12px;
  color: #666;
}

.panorama-preview {
  margin-top: 12px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid #ddd;
}

.panorama-preview img {
  width: 100%;
  height: 150px;
  object-fit: cover;
}

.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #eee;
}

.btn-save, .btn-cancel {
  padding: 12px 24px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-save {
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: white;
}

.btn-save:hover:not(:disabled) {
  background: linear-gradient(135deg, #26a666 0%, #0ab963 100%);
}

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-cancel {
  background: #f8f9fa;
  color: #666;
  border: 1px solid #ddd;
}

.btn-cancel:hover {
  background: #e9ecef;
}

/* Delete Modal Styles */
.delete-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1200;
}

.delete-modal-content {
  background: white;
  border-radius: 12px;
  width: 400px;
  max-width: 90%;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
  position: relative;
}

.delete-modal-close {
  position: absolute;
  top: 15px;
  right: 15px;
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #666;
  padding: 5px;
  border-radius: 50%;
  transition: background-color 0.2s;
  z-index: 1;
}

.delete-modal-close:hover {
  background: rgba(0, 0, 0, 0.1);
}

.delete-modal-header {
  padding: 25px 25px 15px 25px;
  text-align: center;
  border-bottom: 1px solid #eee;
}

.delete-modal-icon {
  font-size: 48px;
  margin-bottom: 10px;
}

.delete-modal-title {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #333;
}

.delete-modal-message {
  padding: 20px 25px;
  margin: 0;
  font-size: 16px;
  line-height: 1.5;
  color: #666;
  text-align: center;
}

.delete-modal-actions {
  padding: 0 25px 25px 25px;
}

.delete-modal-buttons {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.delete-modal-cancel,
.delete-modal-confirm {
  padding: 12px 24px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  min-width: 100px;
}

.delete-modal-cancel {
  background: #f8f9fa;
  color: #666;
  border: 1px solid #ddd;
}

.delete-modal-cancel:hover {
  background: #e9ecef;
}

.delete-modal-confirm {
  background: #dc3545;
  color: white;
}

.delete-modal-confirm:hover {
  background: #c82333;
}

/* Responsive */
@media (max-width: 768px) {
  .rooms-grid {
    grid-template-columns: 1fr;
  }
  
  .rooms-modal-content {
    width: 95%;
    margin: 10px;
  }
  
  .room-edit-modal-content {
    width: 95%;
    margin: 10px;
  }

  .delete-modal-content {
    width: 95%;
    margin: 10px;
  }
}
</style>
