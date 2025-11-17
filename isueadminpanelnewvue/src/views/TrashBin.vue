<template>
  <div class="trash-bin">
    <div class="header">
      <div class="header-left">
        <button @click="$router.push('/maps')" class="back-button" title="Back to Admin Panel">
          <span class="back-icon">←</span>
        </button>
        <h1>🗑️ Trash Bin</h1>
      </div>
      <div class="header-actions">
        <select v-model="selectedTab" class="tab-filter">
          <option value="all">All Items</option>
          <option value="buildings">Buildings Only</option>
          <option value="maps">Maps Only</option>
        </select>
        <button 
          v-if="deletedItems.length > 0" 
          @click="showEmptyTrashModal = true" 
          class="empty-trash-button"
        >
          Empty Trash
        </button>
      </div>
    </div>

    <div class="stats">
      <div class="stat-card">
        <div class="stat-number">{{ deletedBuildings.length }}</div>
        <div class="stat-label">Deleted Buildings</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">{{ deletedMaps.length }}</div>
        <div class="stat-label">Deleted Maps</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">{{ totalItems }}</div>
        <div class="stat-label">Total Items</div>
      </div>
    </div>

    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>Loading deleted items...</p>
    </div>

    <div v-else-if="filteredItems.length === 0" class="empty-state">
      <div class="empty-icon">🗑️</div>
      <h3>Trash is empty</h3>
      <p>No deleted {{ selectedTab === 'all' ? 'items' : selectedTab }} found.</p>
    </div>

    <div v-else class="items-grid">
      <div v-for="item in filteredItems" :key="item.id" class="trash-item-card">
        <div class="item-header">
          <div class="item-type-badge" :class="item.item_type">
            {{ item.item_type === 'building' ? '🏢' : '🗺️' }}
            {{ item.item_type.charAt(0).toUpperCase() + item.item_type.slice(1) }}
          </div>
          <div class="deleted-date">
            {{ formatDate(item.deleted_at) }}
          </div>
        </div>

        <div class="item-content">
          <div class="item-visual">
            <!-- Building Marker Icon -->
            <div v-if="item.item_type === 'building'" class="building-marker-container">
              <img 
                v-if="resolvedBuildingMarker(item.item_data)" 
                :src="resolvedBuildingMarker(item.item_data)" 
                :alt="(item.item_data.building_name || 'Building') + ' marker'" 
                class="building-marker-icon"
              >
              <div v-else class="default-building-icon">🏢</div>
            </div>
            
            <!-- Map Image -->
            <div v-if="item.item_type === 'map'" class="map-image-container">
              <img 
                v-if="item.item_data.image_path" 
                :src="getImageUrl(item.item_data.image_path)" 
                :alt="item.item_data.name + ' map'" 
                class="map-preview-image"
              >
              <div v-else class="default-map-icon">🗺️</div>
            </div>
          </div>

          <div class="item-info">
            <h3>{{ item.item_data.name || item.item_data.building_name }}</h3>
            <div class="item-details">
              <p v-if="item.item_type === 'building'">
                <strong>Map:</strong> {{ getMapName(item.item_data.map_id) }}
              </p>
              <p v-if="item.item_type === 'building'">
                <strong>Position:</strong> ({{ item.item_data.x_coordinate }}, {{ item.item_data.y_coordinate }})
              </p>
              <p v-if="item.item_type === 'map'">
                <strong>Dimensions:</strong> {{ item.item_data.width }}x{{ item.item_data.height }}
              </p>
              <p><strong>Deleted by:</strong> {{ item.deleted_by || 'Unknown' }}</p>
            </div>
          </div>
        </div>

        <div class="item-actions">
          <button @click="restoreItem(item)" class="restore-button">
            Restore
          </button>
          <button @click="showPermanentDeleteModal(item)" class="permanent-delete-button">
            Delete Forever
          </button>
        </div>
      </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div v-if="showRestoreModal" class="modal-overlay" @click="closeRestoreModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Restore {{ itemToRestore?.item_type }}</h2>
          <button @click="closeRestoreModal" class="close-button">&times;</button>
        </div>
        
        <div class="modal-body">
          <div class="modal-item-preview">
            <div v-if="itemToRestore?.item_type === 'building'" class="modal-building-marker">
              <img 
                v-if="resolvedBuildingMarker(itemToRestore?.item_data)" 
                :src="resolvedBuildingMarker(itemToRestore.item_data)" 
                :alt="(itemToRestore.item_data.building_name || 'Building') + ' marker'" 
                class="modal-marker-icon"
              >
              <div v-else class="modal-default-icon">🏢</div>
            </div>
            <div v-if="itemToRestore?.item_type === 'map'" class="modal-map-preview">
              <img 
                v-if="itemToRestore?.item_data.image_path" 
                :src="getImageUrl(itemToRestore.item_data.image_path)" 
                :alt="itemToRestore.item_data.name + ' map'" 
                class="modal-map-icon"
              >
              <div v-else class="modal-default-icon">🗺️</div>
            </div>
          </div>
          <p>Are you sure you want to restore <strong>{{ itemToRestore?.item_data.name || itemToRestore?.item_data.building_name }}</strong>?</p>
          <p class="modal-subtext">This will move the item back to the active list.</p>
        </div>
        
        <div class="modal-footer">
          <button @click="closeRestoreModal" class="cancel-button">Cancel</button>
          <button @click="confirmRestore" class="restore-button">Restore</button>
        </div>
      </div>
    </div>

    <!-- Permanent Delete Modal -->
    <div v-if="showPermanentDeleteConfirm" class="modal-overlay" @click="closePermanentDeleteModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Permanently Delete {{ itemToPermanentDelete?.item_type || 'Item' }}</h2>
          <button @click="closePermanentDeleteModal" class="close-button">&times;</button>
        </div>
        
        <div class="modal-body">
          <div class="warning-icon">⚠️</div>
          <p>Are you sure you want to permanently delete <strong>{{ itemToPermanentDelete?.item_data.name || itemToPermanentDelete?.item_data.building_name || '' }}</strong>?</p>
          <p class="modal-subtext">This action cannot be undone and will permanently remove the item from the system.</p>
        </div>
        
        <div class="modal-footer">
          <button @click="closePermanentDeleteModal" class="cancel-button">Cancel</button>
          <button @click="confirmPermanentDelete" class="danger-button">Delete Forever</button>
        </div>
      </div>
    </div>

    <!-- Empty Trash Modal -->
    <div v-if="showEmptyTrashModal" class="modal-overlay" @click="closeEmptyTrashModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Empty Trash</h2>
          <button @click="closeEmptyTrashModal" class="close-button">&times;</button>
        </div>
        
        <div class="modal-body">
          <div class="warning-icon">⚠️</div>
          <p>Are you sure you want to empty the trash?</p>
          <p class="modal-subtext">This will permanently delete <strong>{{ totalItems }} items</strong> and cannot be undone.</p>
        </div>
        
        <div class="modal-footer">
          <button @click="closeEmptyTrashModal" class="cancel-button">Cancel</button>
          <button @click="confirmEmptyTrash" class="danger-button">Empty Trash</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'TrashBin',
  data() {
    return {
      deletedItems: [],
      maps: [],
      loading: true,
      selectedTab: 'all',
      showRestoreModal: false,
      itemToRestore: null,
      showPermanentDeleteConfirm: false,
      itemToPermanentDelete: null,
      showEmptyTrashModal: false
    }
  },
  computed: {
    deletedBuildings() {
      return this.deletedItems.filter(item => item.item_type === 'building')
    },
    deletedMaps() {
      return this.deletedItems.filter(item => item.item_type === 'map')
    },
    totalItems() {
      return this.deletedItems.length
    },
    filteredItems() {
      if (this.selectedTab === 'buildings') return this.deletedBuildings
      if (this.selectedTab === 'maps') return this.deletedMaps
      return this.deletedItems
    }
  },
  mounted() {
    this.fetchDeletedItems()
    this.fetchMaps()
  },
  methods: {
    resolvedBuildingMarker(itemData) {
      if (!itemData) return null
      const marker = itemData.marker_image || itemData.image_path || ''
      if (!marker) return null
      if (marker.startsWith('http')) return marker
      return this.getImageUrl(marker)
    },
    async fetchDeletedItems() {
      try {
        this.loading = true
        const response = await axios.get('/trash/')
        this.deletedItems = [...response.data.buildings, ...response.data.maps]
      } catch (error) {
        console.error('Error fetching deleted items:', error)
      } finally {
        this.loading = false
      }
    },
    async fetchMaps() {
      try {
        const response = await axios.get('/map/')
        this.maps = response.data
      } catch (error) {
        console.error('Error fetching maps:', error)
      }
    },
    getMapName(mapId) {
      const map = this.maps.find(m => m.id === mapId)
      return map ? map.name : 'Unknown Map'
    },
    formatDate(dateString) {
      const date = new Date(dateString)
      return date.toLocaleDateString() + ' at ' + date.toLocaleTimeString()
    },
    getImageUrl(imagePath) {
      if (!imagePath) return null
      // Absolute URLs pass-through
      if (typeof imagePath === 'string' && imagePath.startsWith('http')) {
        return imagePath
      }

      // Normalize common stored paths
      let relative = String(imagePath)
        .replace(/^\\+/g, '/')
        .replace(/^\/+/, '')

      // If the value already contains a leading storage/ segment, strip it
      // so we can consistently prepend the correct base below
        .replace(/^storage\//, '')
        .replace(/^public\//, '')

      // Derive storage base from configured API base
      // axios.defaults.baseURL looks like: https://<host>/backend/api
      // We want: https://<host>/backend/storage/<relative>
      try {
        const apiBase = (this.$axios?.defaults?.baseURL) || (typeof axios !== 'undefined' ? axios.defaults.baseURL : '') || ''
        const u = new URL(apiBase)
        // Remove trailing /api... (e.g., /api or /api/v1)
        const basePath = u.pathname.replace(/\/api(?:\/.*)?$/, '')
        const originAndBase = `${u.origin}${basePath}`.replace(/\/$/, '')
        return `${originAndBase}/storage/${relative}`
      } catch (_) {
        // Fallback to same-origin storage
        return `/storage/${relative}`
      }
    },
    restoreItem(item) {
      this.itemToRestore = item
      this.showRestoreModal = true
    },
    closeRestoreModal() {
      this.showRestoreModal = false
      this.itemToRestore = null
    },
    async confirmRestore() {
      if (!this.itemToRestore) return
      
      try {
        await axios.post(`/trash/${this.itemToRestore.id}/restore`)
        await this.fetchDeletedItems()
        this.closeRestoreModal()
        // Show success message
        this.$emit('show-toast', 'success', 'Item Restored', `${this.itemToRestore.item_type} has been restored successfully.`)
      } catch (error) {
        console.error('Error restoring item:', error)
        this.$emit('show-toast', 'error', 'Restore Failed', 'Failed to restore item. Please try again.')
      }
    },
    showPermanentDeleteModal(item) {
      this.itemToPermanentDelete = item
      this.showPermanentDeleteConfirm = true
    },
    closePermanentDeleteModal() {
      this.showPermanentDeleteConfirm = false
      this.itemToPermanentDelete = null
    },
    async confirmPermanentDelete() {
      if (!this.itemToPermanentDelete) return
      
      try {
        await axios.delete(`/trash/${this.itemToPermanentDelete.id}/permanent`)
        await this.fetchDeletedItems()
        this.closePermanentDeleteModal()
        this.$emit('show-toast', 'success', 'Permanently Deleted', 'Item has been permanently deleted.')
      } catch (error) {
        console.error('Error permanently deleting item:', error)
        this.$emit('show-toast', 'error', 'Delete Failed', 'Failed to permanently delete item.')
      }
    },
    closeEmptyTrashModal() {
      this.showEmptyTrashModal = false
    },
    async confirmEmptyTrash() {
      try {
        await axios.delete('/trash/empty')
        await this.fetchDeletedItems()
        this.closeEmptyTrashModal()
        this.$emit('show-toast', 'success', 'Trash Emptied', 'All items have been permanently deleted.')
      } catch (error) {
        console.error('Error emptying trash:', error)
        this.$emit('show-toast', 'error', 'Empty Failed', 'Failed to empty trash.')
      }
    }
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

.trash-bin {
  padding: 24px;
  max-width: 1200px;
  margin: 0 auto;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.back-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.back-button:hover {
  background: #e5e7eb;
  border-color: #9ca3af;
  transform: translateX(-2px);
}

.back-icon {
  font-size: 18px;
  font-weight: 600;
  color: #374151;
}

.header h1 {
  margin: 0;
  color: #1f2937;
  font-size: 2rem;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  font-weight: 700;
}

.header-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

.tab-filter {
  padding: 8px 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: white;
  font-size: 14px;
}

.empty-trash-button {
  padding: 8px 16px;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.2s;
}

.empty-trash-button:hover {
  background: #b91c1c;
}

.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.stat-number {
  font-size: 2rem;
  font-weight: 700;
  color: #dc2626;
  margin-bottom: 4px;
}

.stat-label {
  color: #6b7280;
  font-size: 14px;
}

.loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 48px;
  color: #6b7280;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #f3f4f6;
  border-top: 3px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.empty-state {
  text-align: center;
  padding: 48px;
  color: #6b7280;
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 16px;
}

.items-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.trash-item-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}

.trash-item-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.item-type-badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  color: white;
}

.item-type-badge.building {
  background: #3b82f6;
}

.item-type-badge.map {
  background: #10b981;
}

.deleted-date {
  font-size: 12px;
  color: #6b7280;
}

.item-content {
  padding: 16px;
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.item-visual {
  flex-shrink: 0;
}

.building-marker-container {
  width: 60px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 12px;
  border: 2px solid #e5e7eb;
  overflow: hidden;
}

.building-marker-icon {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 10px;
}

.default-building-icon {
  font-size: 24px;
  color: #6b7280;
}

.map-image-container {
  width: 80px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 12px;
  border: 2px solid #e5e7eb;
  overflow: hidden;
}

.map-preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 10px;
}

.default-map-icon {
  font-size: 24px;
  color: #6b7280;
}

.item-info {
  flex: 1;
  min-width: 0;
}

.item-info h3 {
  margin: 0 0 12px 0;
  color: #1f2937;
  font-size: 1.1rem;
  word-wrap: break-word;
}

.item-details p {
  margin: 4px 0;
  font-size: 14px;
  color: #6b7280;
}

.item-actions {
  display: flex;
  gap: 8px;
  padding: 16px;
  background: #f9fafb;
}

.restore-button {
  flex: 1;
  padding: 8px 16px;
  background: #10b981;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.2s;
}

.restore-button:hover {
  background: #059669;
}

.permanent-delete-button {
  flex: 1;
  padding: 8px 16px;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.2s;
}

.permanent-delete-button:hover {
  background: #b91c1c;
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
  max-width: 500px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 24px 0;
}

.modal-header h2 {
  margin: 0;
  color: #1f2937;
  font-size: 1.5rem;
  font-weight: 600;
}

.close-button {
  background: none;
  border: none;
  font-size: 24px;
  color: #6b7280;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: background-color 0.2s;
}

.close-button:hover {
  background-color: #f3f4f6;
}

.modal-body {
  padding: 24px;
  text-align: center;
}

.modal-item-preview {
  margin-bottom: 20px;
  display: flex;
  justify-content: center;
}

.modal-building-marker {
  width: 80px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 16px;
  border: 2px solid #e5e7eb;
  overflow: hidden;
}

.modal-map-preview {
  width: 100px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  border-radius: 16px;
  border: 2px solid #e5e7eb;
  overflow: hidden;
}

.modal-marker-icon {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 14px;
}

.modal-map-icon {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 14px;
}

.modal-default-icon {
  font-size: 32px;
  color: #6b7280;
}

.warning-icon {
  font-size: 3rem;
  margin-bottom: 16px;
}

.modal-body p {
  margin-bottom: 8px;
  color: #1f2937;
}

.modal-subtext {
  color: #6b7280;
  font-size: 14px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 0 24px 24px;
}

.cancel-button {
  padding: 10px 20px;
  border: 1px solid #d1d5db;
  background: white;
  color: #374151;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s;
}

.cancel-button:hover {
  background-color: #f9fafb;
  border-color: #9ca3af;
}

.danger-button {
  padding: 10px 20px;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.2s;
}

.danger-button:hover {
  background-color: #b91c1c;
}
</style>
