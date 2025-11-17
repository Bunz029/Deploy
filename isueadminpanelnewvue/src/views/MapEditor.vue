u<template>
  <div class="map-editor-container">
    
    
    <!-- Header Section -->
    <header class="editor-header">
      <div class="header-content">
        <div class="header-left">
          <h1 class="page-title">Campus Map Editor</h1>
          <p class="page-subtitle">Manage buildings and map layouts</p>
      </div>
        <div class="header-right">
          <div class="active-map-info">
            <span class="info-label">Active Map:</span>
            <span class="info-value">{{ activeMap?.name || 'None' }}</span>
          </div>
          <div class="header-actions">
            <button @click="$router.push('/trash')" class="header-btn trash-btn" title="Trash Bin">
              <span class="btn-icon">🗑️</span>
              <span class="btn-text">Trash</span>
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <div class="editor-main">
      <!-- Removed old sidebar - now using unified right panel -->

      <!-- Main Map Area -->
      <main class="map-workspace">
        <div class="map-header">
          <div class="map-title">
            <h2>Campus Map</h2>
          </div>
          <div class="map-controls">
            <button @click="toggleZoomMode" :class="['control-btn', { active: zoomMode }]" title="Zoom Mode">
              <span class="control-icon">🔍</span>
            </button>
            <button @click="toggleDragMode" :class="['control-btn', { active: dragMode }]" title="Pan Mode">
              <span class="control-icon">✋</span>
            </button>
            <div class="zoom-controls">
              <button @click="zoomOut" class="zoom-btn" title="Zoom Out">-</button>
              <span class="zoom-level">{{ Math.round(mapScale * 100) }}%</span>
              <button @click="zoomIn" class="zoom-btn" title="Zoom In">+</button>
          </div>
            <div class="map-dimensions" v-if="activeMap">
              <span class="dimension-text">{{ activeMap.width }}×{{ activeMap.height }}</span>
          </div>
            <button @click="resetMapView" class="control-btn" title="Reset View">
              <span class="control-icon">🔄</span>
            </button>
            <button @click="showPublishModal" class="control-btn push-btn" :disabled="isEditingLayout" :class="{ 'has-updates': unpublishedCount > 0, disabled: isEditingLayout }" title="Push Updates to App">
              <span class="control-icon">📤</span>
              <span v-if="unpublishedCount > 0" class="update-badge">{{ unpublishedCount }}</span>
            </button>
            <template v-if="isEditingLayout">
              <button @click="saveLayout" class="control-btn save-btn" title="Save Map Layout">
                <span class="control-icon">💾</span>
              </button>
              <button @click="stopEditingLayout" class="control-btn" title="Stop Editing Layout (Auto-Save)">
                <span class="control-icon">✅</span>
              </button>
            </template>
            <button 
              v-if="buildingForm.x_coordinate || buildingForm.y_coordinate" 
              @click="clearPositionMarker" 
              class="control-btn danger" 
              title="Clear Position Marker"
            >
              <span class="control-icon">❌</span>
            </button>
        </div>
      </div>

        <div class="map-container custom-map-style" v-if="activeMap">
          <div 
            class="map-canvas"
            :class="{ 'drag-mode': dragMode, 'zoom-mode': zoomMode }"
            ref="mapCanvas" 
            @click="handleMapClick"
            @mousemove="handleMapMove"
            @mouseleave="handleMouseLeave"
            @mouseenter="handleMapMouseEnter"
            @mousedown="startDrag"
            @mouseup="stopDrag"
          >
            <div class="map-wrapper" :style="getMapWrapperStyle()">
              <img 
                :src="activeMap.image_url || getImageUrl(activeMap.image_path)" 
                :alt="activeMap.name"
                class="map-image"
                :style="{
                  width: activeMap.width + 'px',
                  height: activeMap.height + 'px',
                  objectFit: 'fill',
                  objectPosition: 'center'
                }"
                @load="onMapImageLoad"
                @error="onMapImageError"
              />
              
              <!-- Building Markers (hide the one being edited to avoid duplicate/old marker) -->
              <div 
                v-for="building in buildings" 
                :key="building.id"
                v-show="!editingBuilding || building.id !== editingBuilding.id"
                :class="['building-marker', { selected: selectedBuilding && selectedBuilding.id === building.id }]"
                :style="{ 
                  left: `${building.x_coordinate}px`, 
                  top: `${building.y_coordinate}px`
                }"
                @click.stop="handleBuildingClick($event, building)"
              >
                <div class="marker-container">
                  <img 
                    v-if="building.image_path" 
                    :src="getImagePath(building)" 
                    :alt="building.building_name"
                    class="marker-image"
                    :style="{
                      width: `${building.width || 28}px`,
                      height: `${building.height || 28}px`
                    }"
                  />
                  <div v-else class="marker-dot" :style="{
                    transform: `scale(${Math.max(0.5, 1 / this.mapScale)})`
                  }"></div>
            </div>
                <div class="marker-label" :style="{
                  fontSize: `${Math.max(8, 12 / Math.sqrt(this.mapScale))}px`,
                  transform: `scale(${Math.max(0.7, 1 / Math.sqrt(this.mapScale))})`
                }">{{ building.building_name }}</div>
            </div>
              
              <!-- Temporary Marker for New Building -->
              <div 
                v-if="(showAddBuildingModal || editingBuilding) && buildingForm.x_coordinate && buildingForm.y_coordinate" 
                class="building-marker new-building-marker"
                :style="{ 
                  left: `${buildingForm.x_coordinate}px`, 
                  top: `${buildingForm.y_coordinate}px`,
                  transform: `translate(-50%, -50%)`
                }"
              >
                <div class="marker-container">
                  <img 
                    v-if="buildingForm.image_preview" 
                    :src="buildingForm.image_preview" 
                    class="marker-image new-marker-image"
                    :style="{
                      width: `${buildingForm.image_width}px`,
                      height: `${buildingForm.image_height}px`
                    }"
                  />
                  <div v-else class="marker-dot new-marker-dot" :style="{
                    transform: `scale(${Math.max(0.5, 1 / this.mapScale)})`
                  }"></div>
            </div>
                <div class="marker-label" :style="{
                  fontSize: `${Math.max(8, 12 / Math.sqrt(this.mapScale))}px`,
                  transform: `scale(${Math.max(0.7, 1 / Math.sqrt(this.mapScale))})`
                }">New Position</div>
            </div>

            </div>
            
                        <!-- Coordinate Preview (positioned relative to map container) -->
            <div 
              v-if="coordPreview.visible" 
              class="coord-preview"
              :style="{ 
                left: `${coordPreview.x + 8}px`, 
                top: `${coordPreview.y - 20}px`,
                fontSize: `${Math.max(8, 10 / Math.sqrt(this.mapScale))}px`,
                padding: `${Math.max(1, 2 / Math.sqrt(this.mapScale))}px ${Math.max(3, 4 / Math.sqrt(this.mapScale))}px`
              }"
            >
              {{ coordPreview.text }}
        </div>
      </div>
    </div>


        <div v-else class="no-active-map">
          <div class="empty-state">
            <div class="empty-icon">🗺️</div>
            <h3>No Active Map</h3>
            <p>Please activate a map from the Map Layout panel to start editing.</p>
          </div>
        </div>
      </main>

      <!-- Unified Right Panel -->
      <aside class="right-panel">
        <!-- Panel Toggle Buttons -->
        <div class="panel-toggle-buttons">
          <button 
            @click="activePanel = 'map-layout'" 
            :class="['panel-toggle-btn', { active: activePanel === 'map-layout' }]"
          >
            <span class="btn-icon">🗺️</span>
            <span class="btn-text">Map Layout</span>
          </button>
          <button 
            @click="activePanel = 'building-management'" 
            :class="['panel-toggle-btn', { active: activePanel === 'building-management' }]"
          >
            <span class="btn-icon">🏗️</span>
            <span class="btn-text">Building Management</span>
          </button>
        </div>

                <!-- Map Layout Panel -->
        <div v-if="activePanel === 'map-layout'" class="panel-section">
          <div class="section-header">
            <h3 class="section-title">Map Layout</h3>
            <button class="add-btn" @click="showAddMapModal = true">
              <span class="btn-icon">+</span>
              <span class="btn-text">Add Map</span>
        </button>
      </div>

      <!-- Import Actions -->
      <div class="import-section">
        <h4 class="subsection-title">Import Map</h4>
        <div class="import-actions">
          <button class="import-btn" @click="openImportModal">
            <span class="btn-icon">📥</span>
            <span class="btn-text">Import Map</span>
          </button>
        </div>
        <p class="import-help">
          Import restores a previously exported map layout with all buildings, rooms, and images.
        </p>
      </div>

      <div class="maps-grid">
            <!-- Debug Info -->
            <div v-if="maps.length === 0" class="debug-info">
              <p>No maps loaded. Maps array length: {{ maps.length }}</p>
              <button @click="fetchMaps" class="debug-btn">Reload Maps</button>
            </div>
            
        <div v-for="map in maps" :key="map.id" class="map-card">
              <div class="map-image-container">
                <img 
                  v-if="map.image_path"
                  :src="getImageUrl(map.image_path)" 
                  :alt="map.name"
                  @error="handleImageError"
                  @load="handleImageLoad"
                  class="map-preview-image"
                >
                <div v-else class="map-placeholder">
                  <span class="placeholder-icon">🗺️</span>
                  <span class="placeholder-text">No Image</span>
                </div>
                <div class="map-status-badge" :class="{ active: map.is_active }">
              {{ map.is_active ? 'Active' : 'Inactive' }}
            </div>
          </div>
          <div class="map-info">
                <h4 class="map-name">{{ map.name || 'Unnamed Map' }}</h4>
                <p class="map-dimensions">{{ map.width || 0 }}×{{ map.height || 0 }}</p>
          </div>
          <div class="map-actions">
                <button @click="exportMap(map)" class="action-btn export" title="Export Map" :disabled="exporting">
                  <span v-if="exporting">⏳</span>
                  <span v-else>📤</span>
                </button>
                <button @click="editMap(map)" class="action-btn edit" title="Edit Map">✏️</button>
                <button @click="enterLayoutEditModeFor(map)" class="action-btn" title="Edit Layout">🗺️</button>
                <button @click="showMapDeleteConfirmation(map)" class="action-btn delete" title="Delete Map">🗑️</button>
            <button 
              v-if="!map.is_active"
              @click="setActiveMap(map.id)"
                  class="action-btn activate"
                  title="Set Active"
            >
                  ✓
            </button>
              </div>
          </div>
        </div>
      </div>

        <!-- Building Management Panel -->
        <div v-if="activePanel === 'building-management'" class="panel-section">
          <div class="section-header">
            <h3 class="section-title">Building Management</h3>
            <div class="section-actions">
              <button class="add-btn" @click="openAddBuildingModal">
                <span class="btn-icon">📁</span>
                <span class="btn-text">Add Building</span>
              </button>
            </div>
            </div>

          <div class="buildings-list">

            
            <div v-for="building in buildings" :key="building.id" class="building-card">

              
              <div class="building-image-container">
                <img 
                  :src="getImagePath(building)" 
                  :alt="building.building_name"
                  class="building-preview-image"
                  @click="selectBuilding(building)"
                >
                <div class="image-overlay">
                  <span v-if="building.description" class="has-description" title="Has description">📝</span>
                </div>
              </div>
              <div class="building-info">
                <h4 class="building-title">{{ building.building_name || 'Unnamed Building' }}</h4>
                <p class="building-location">({{ building.x_coordinate || 0 }}, {{ building.y_coordinate || 0 }})</p>

              </div>
              <div class="building-actions">
                <button @click="editBuilding(building)" class="action-btn edit" title="Edit Building">🔧</button>
                <button @click="showDeleteConfirmation(building)" class="action-btn delete" title="Delete Building">🗑️</button>
              </div>
            </div>
            
            <div v-if="buildings.length === 0" class="empty-state">
              <div class="empty-icon">🏢</div>
              <p class="empty-text">No buildings added yet</p>
              <button class="add-btn primary" @click="openAddBuildingModal">Add First Building</button>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- Building Editor Slide Panel -->
    <div v-if="showAddBuildingModal || editingBuilding" class="modern-editor-panel">
      <div class="modern-editor-header">
        <h2 class="modern-editor-title">{{ editingBuilding ? 'Edit Building' : 'Add New Building' }}</h2>
        <button @click="closeBuildingModal" class="modern-close-btn">×</button>
      </div>
      <div class="modern-editor-content">
        <form @submit.prevent="saveBuilding" class="modern-form">
          <div class="modern-form-group">
            <label class="modern-label">Building Name</label>
            <input v-model="buildingForm.name" required class="modern-input" placeholder="Enter building name">
          </div>
          
          <div class="modern-form-group">
            <label class="modern-label">Description</label>
            <textarea v-model="buildingForm.description" class="modern-textarea" placeholder="Describe the building and its purpose"></textarea>
          </div>
          
          <div class="modern-form-group">
            <label class="modern-label">Services</label>
            <div class="services-input-container">
              <div class="services-tags">
                <div 
                  v-for="(service, index) in buildingForm.services" 
                  :key="index" 
                  class="service-tag"
                >
                  <span class="service-text">{{ service }}</span>
                  <button 
                    @click="removeService(index)" 
                    class="remove-service-btn"
                    type="button"
                  >
                    ×
                  </button>
                </div>
              </div>
              <input 
                v-model="newServiceInput" 
                @keydown.enter="addServices"
                placeholder="Type a service and press Enter, or multiple services separated by commas"
                class="modern-input services-input"
              >
            </div>
            <p class="helper-text">Press Enter to add services. Use commas to add multiple at once. Services are saved when you click Save.</p>
          </div>

          <!-- Employee Management Section -->
          <div class="modern-form-group employee-section">
            <h3 class="section-subtitle">Building Employees</h3>
            <div v-if="buildingForm.employees && buildingForm.employees.length > 0" class="modern-employees-list">
              <div v-for="(employee, index) in buildingForm.employees" :key="index" class="modern-employee-item">
                <div class="modern-employee-info">
                  <div class="modern-employee-avatar">
                    <img v-if="employee.image_preview" :src="employee.image_preview" alt="Employee Image">
                    <div v-else class="modern-avatar-placeholder">👤</div>
                  </div>
                  <div class="modern-employee-details">
                    <input v-model="employee.name" placeholder="Employee Name" class="modern-input employee-input">
                  </div>
                  <div class="modern-employee-actions">
                    <input type="file" @change="handleEmployeeImageUpload($event, index)" accept="image/*" class="hidden-file-input" :id="`employee-image-${index}`">
                    <label :for="`employee-image-${index}`" class="modern-btn-secondary camera-btn">📷</label>
                    <button type="button" @click="removeEmployee(index)" class="modern-btn-danger remove-btn">❌</button>
                  </div>
                </div>
              </div>
            </div>
            <button type="button" @click="addEmployee" class="modern-btn-secondary add-employee-btn">➕ Add Employee</button>
          </div>
          
          <div class="modern-form-group map-instructions">
            <h3 class="section-subtitle">Position on Map</h3>
            <p class="instruction-text">Click directly on the map to set coordinates, or enter them manually below.</p>
            <p class="instruction-text small">💡 Building markers use smart click detection - only the visible parts of images are clickable.</p>
          </div>
          
          <div class="modern-form-group coordinate-inputs">
            <div>
              <label class="modern-label">X Coordinate</label>
              <input type="number" v-model="buildingForm.x_coordinate" required class="modern-input">
            </div>
            <div>
              <label class="modern-label">Y Coordinate</label>
              <input type="number" v-model="buildingForm.y_coordinate" required class="modern-input">
            </div>
            <div class="coordinate-hint">
              <small class="hint-text">💡 Use arrow keys to fine-tune position (Shift + arrow for 10px steps)</small>
            </div>
          </div>
          
          <div class="modern-form-group">
            <label class="modern-label">Map Marker Image (Icon)</label>
            <p class="field-description">This image will be used as the building icon on the map</p>
            <input 
              type="file" 
              @change="handleBuildingImageUpload" 
              accept="image/*"
              ref="buildingImageInput"
              class="modern-file-input"
            >
          </div>
          
          <div class="modern-form-group">
            <label class="modern-label">Modal Image (Photo)</label>
            <p class="field-description">This image will be shown in the building details modal and cards</p>
            <input 
              type="file" 
              @change="handleModalImageUpload" 
              accept="image/*"
              ref="modalImageInput"
              class="modern-file-input"
            >
          </div>

          <!-- Rooms Management Section -->
          <div class="modern-form-group">
            <label class="modern-label">360° Room Images</label>
            <p class="field-description">Manage 360° panoramic images for rooms in this building</p>
            <button 
              type="button" 
              @click="openRoomsManagement" 
              class="modern-btn-rooms-inline"
            >
              <span class="btn-icon">🏠</span>
              <span class="btn-text">Manage Rooms (360°)</span>
            </button>
          </div>
          
          <div class="modern-form-group image-size-controls">
            <label class="modern-label">Image Size (pixels)</label>
            <div class="size-slider-container">
              <div class="slider-wrapper">
                <div class="slider-header">
                  <label class="modern-label">Size: {{ buildingForm.image_width }}px</label>
                  <input 
                    type="number" 
                    v-model.number="imageSizeSlider" 
                    min="10" 
                    max="700" 
                    step="1" 
                    class="size-input"
                    @input="updateImageSizeFromSlider"
                  >
                </div>
                <input 
                  type="range" 
                  v-model.number="imageSizeSlider" 
                  min="10" 
                  max="700" 
                  step="1" 
                  class="size-slider"
                  @input="updateImageSizeFromSlider"
                >
                <div class="slider-labels">
                  <span class="slider-label">10px</span>
                  <span class="slider-label">700px</span>
                </div>
              </div>
            </div>
            <div class="size-presets">
              <button type="button" @click="setImageSize(28, 28)" class="modern-preset-btn">Small</button>
              <button type="button" @click="setImageSize(40, 40)" class="modern-preset-btn">Medium</button>
              <button type="button" @click="setImageSize(60, 60)" class="modern-preset-btn">Large</button>
              <button type="button" @click="setImageSize(80, 80)" class="modern-preset-btn">Extra Large</button>
              <button type="button" @click="setImageSize(120, 120)" class="modern-preset-btn">XXL</button>
              <button type="button" @click="setImageSize(200, 200)" class="modern-preset-btn">XXXL</button>
              <button type="button" @click="setImageSize(400, 400)" class="modern-preset-btn">4XL</button>
              <button type="button" @click="setImageSize(700, 700)" class="modern-preset-btn">5XL</button>
            </div>
          </div>
          
          <div v-if="buildingForm.image_preview || buildingForm.modal_image_preview" class="image-previews">
            <div v-if="buildingForm.image_preview" class="image-preview">
              <h4>Map Marker Preview:</h4>
              <img :src="buildingForm.image_preview" alt="Building Marker Preview">
            </div>
            <div v-if="buildingForm.modal_image_preview" class="image-preview">
              <h4>Modal Image Preview:</h4>
              <img :src="buildingForm.modal_image_preview" alt="Modal Image Preview">
            </div>
          </div>
          
          
          <div class="modern-form-actions">
            <button type="submit" class="modern-btn-save">Save</button>
            <button type="button" @click="closeBuildingModal" class="modern-btn-cancel">Cancel</button>
          </div>
          </form>
        </div>
      </div>

      <!-- Add/Edit Map Slide Panel -->
      <div v-if="showAddMapModal || editingMap" class="modern-editor-panel">
        <div class="modern-editor-header">
          <h2 class="modern-editor-title">{{ editingMap ? 'Edit Map' : 'Add New Map' }}</h2>
          <button @click="closeMapModal" class="modern-close-btn">×</button>
    </div>
        <div class="modern-editor-content">
          <form @submit.prevent="saveMap" class="modern-form">
            <div class="modern-form-group">
              <label class="modern-label">Map Name</label>
              <input v-model="mapForm.name" required class="modern-input" placeholder="Enter map name">
  </div>
            
            
            <div class="modern-form-group">
              <label class="modern-label">Map Image</label>
              <input 
                type="file" 
                @change="handleImageUpload" 
                accept="image/*"
                ref="mapImageInput"
                class="modern-file-input"
              >
            </div>
            
            <div v-if="mapForm.image_path" class="image-preview">
              <img :src="mapForm.image_path" alt="Preview">
            </div>
            
            <div class="modern-form-actions">
              <button type="submit" class="modern-btn-save">Save</button>
              <button type="button" @click="closeMapModal" class="modern-btn-cancel">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Building Info Slide Modal -->
      <div v-if="selectedBuilding" class="modern-editor-panel building-info-panel" @click.stop>
        <div class="modern-editor-header">
          <h2 class="modern-editor-title">{{ selectedBuilding.building_name }}</h2>
          <button @click="selectedBuilding = null" class="modern-close-btn">×</button>
        </div>
        <div class="modern-editor-content building-info-content">
          <!-- Building Image -->
          <div class="building-image-section">
            <div class="building-image-container">
              <img 
                v-if="selectedBuilding.image_path"
                :src="getImageUrl(selectedBuilding.image_path)" 
                :alt="selectedBuilding.building_name"
                class="building-image"
              >
              <div v-else class="image-placeholder">
                🏢
              </div>
            </div>
            <div class="image-info">
              <p class="info-label">Building Image</p>
              <p class="info-text">
                {{ selectedBuilding.image_path ? 'Custom icon in use' : 'No image available' }}
              </p>
            </div>
          </div>

          <!-- Building Details -->
          <div class="details-section">
            <!-- Description -->
            <div class="detail-item">
              <h4 class="detail-label">Description</h4>
              <p class="detail-text">
                {{ selectedBuilding.description && selectedBuilding.description.trim() ? selectedBuilding.description : 'No description available' }}
              </p>
            </div>

            <!-- Services -->
            <div class="detail-item">
              <h4 class="detail-label">Services</h4>
              <div v-if="getServicesList(selectedBuilding).length" class="services-tags-display">
                <div 
                  v-for="(service, index) in getServicesList(selectedBuilding)" 
                  :key="index" 
                  class="service-tag-display"
                >
                  {{ service }}
                </div>
              </div>
              <p v-else class="detail-text">No services listed</p>
            </div>

            <!-- Coordinates -->
            <div class="detail-item">
              <h4 class="detail-label">Coordinates</h4>
              <p class="detail-text">
                {{ hasCoordinates(selectedBuilding) ? `X: ${selectedBuilding.x_coordinate}, Y: ${selectedBuilding.y_coordinate}` : 'Not set' }}
              </p>
            </div>

            <!-- Image Size -->
            <div class="detail-item">
              <h4 class="detail-label">Image Size</h4>
              <p class="detail-text">
                {{ hasSize(selectedBuilding) ? `${selectedBuilding.width || 28} × ${selectedBuilding.height || 28} pixels` : 'Default size' }}
              </p>
            </div>

            <!-- Employees -->
            <div class="detail-item">
              <h4 class="detail-label">Employees</h4>
              <div v-if="getEmployees(selectedBuilding).length" class="employees-list">
                <div 
                  v-for="emp in getEmployees(selectedBuilding)" 
                  :key="emp.id" 
                  class="employee-card"
                >
                  <div class="employee-avatar">
                    <img 
                      v-if="emp.employee_image" 
                      :src="getImageUrl(emp.employee_image)" 
                      :alt="emp.employee_name"
                      class="avatar-image"
                    />
                    <div v-else class="avatar-placeholder">👤</div>
              </div>
              <div class="employee-info">
                <p class="employee-name">{{ emp.employee_name }}</p>
                <p class="employee-position">
                  {{ emp.position || '—' }}
                  <span v-if="emp.department"> · {{ emp.department }}</span>
                </p>
                <p class="employee-email">
                  {{ emp.email || 'Contact info not available' }}
                </p>
              </div>
            </div>
          </div>
          <p v-else class="detail-text no-employees">No employees listed</p>
        </div>

        <!-- Modal Image -->
        <div class="detail-item">
          <h4 class="detail-label">Modal Image</h4>
          <div class="modal-image-container">
            <img 
              v-if="selectedBuilding.modal_image_path" 
              :src="getImageUrl(selectedBuilding.modal_image_path)" 
              :alt="selectedBuilding.building_name + ' Modal Image'"
              class="modal-image"
            />
            <div v-else class="modal-image-placeholder">
              📷
            </div>
          </div>
          <p class="detail-text">
            {{ selectedBuilding.modal_image_path ? '' : 'No modal image available' }}
          </p>
        </div>
              </div>

        <!-- Footer Actions -->
        <div class="modal-footer">
          <button @click="editBuildingAndClose(selectedBuilding)" class="action-btn edit-btn">
            <span>✏️</span>
            Edit Building
          </button>
          <button @click="showDeleteConfirmation(selectedBuilding)" class="action-btn delete-btn">
            <span>🗑️</span>
            Delete Building
          </button>
        </div>
      </div>


    </div>
  </div>

    <!-- Toast Notifications -->
    <ToastNotification ref="toast" />
    
    <!-- Publish Changes Modal -->
    <PublishChangesModal 
      :show="showPublishChangesModal" 
      @cancel="handlePublishCancel"
      @success="handlePublishSuccess"
      @error="handlePublishError"
      @change-processed="handleChangeProcessed"
      @show-toast="showToast"
    />

    <!-- Activity Log Drawer -->
    <ActivityLogDrawer @show-toast="showToast" />

    <!-- Rooms Management Modal -->
    <RoomsManagementModal 
      :show="showRoomsManagementModal"
      :building="editingBuilding"
      @close="closeRoomsManagement"
      @room-added="handleRoomAdded"
      @room-updated="handleRoomUpdated"
      @room-deleted="handleRoomDeleted"
    />

    <!-- Import Map Modal -->
    <ImportMapModal 
      :show="showImportModal"
      :importing="importing"
      :upload-progress="importProgress"
      :upload-status="importStatus"
      @close="closeImportModal"
      @import="handleImportMap"
      @show-toast="showToast"
    />
  
  <!-- Delete Confirmation Modals - Moved outside panels for global access -->
  
  <!-- Building Delete Confirmation Modal -->
  <div v-if="showDeleteModal" class="delete-modal-overlay">
    <div class="delete-modal-content">
      <!-- Close button -->
      <button @click="showDeleteModal = false; stopBuildingCountdown(); buildingDeleteCountdown = 3" class="delete-modal-close">&times;</button>
      
      <div class="delete-modal-header">
        <div class="delete-modal-icon">⚠️</div>
        <h3 class="delete-modal-title">Delete Building</h3>
      </div>
      
      <p class="delete-modal-message">
        Are you sure you want to delete building <strong>{{ buildingToDelete ? buildingToDelete.building_name : 'Unknown' }}</strong>? This action cannot be undone.
      </p>
      
      <div class="delete-modal-actions">
        <div v-if="buildingDeleteCountdown > 0" class="countdown-display">
          {{ buildingDeleteCountdown }}
        </div>
        <div v-else class="delete-modal-buttons">
          <button @click="showDeleteModal = false; stopBuildingCountdown(); buildingDeleteCountdown = 3" class="delete-modal-cancel">
            Cancel
          </button>
          <button @click="confirmDeleteBuilding" class="delete-modal-confirm">
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Map Delete Confirmation Modal -->
  <div v-if="showMapDeleteModal" class="delete-modal-overlay" @click="closeMapDeleteModal">
    <div class="delete-modal-content" @click.stop>
      <!-- Close button -->
      <button @click="closeMapDeleteModal" class="delete-modal-close">&times;</button>
      
      <div class="delete-modal-header">
        <div class="delete-modal-icon">⚠️</div>
        <h3 class="delete-modal-title">Mark Map for Deletion</h3>
      </div>
      
      <p class="delete-modal-message">
        Are you sure you want to mark map <strong>{{ mapToDelete ? mapToDelete.name : 'Unknown' }}</strong> for deletion? This will mark it for deletion and it will be removed when you publish changes. All buildings associated with it will also be affected.
      </p>
      
      <div class="delete-modal-actions">
        <div v-if="mapDeleteCountdown > 0" class="countdown-display">
          {{ mapDeleteCountdown }}
        </div>
        <div v-else class="delete-modal-buttons">
          <button @click="closeMapDeleteModal" class="delete-modal-cancel">
            Cancel
          </button>
          <button @click="confirmMapDelete" class="delete-modal-confirm">
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>

  

</template>


<script>
import axios from 'axios'
import ToastNotification from '@/components/ToastNotification.vue'
import PublishChangesModal from '@/components/PublishChangesModal.vue'
import ActivityLogDrawer from '@/components/ActivityLogDrawer.vue'
import RoomsManagementModal from '@/components/RoomsManagementModal.vue'
import ImportMapModal from '@/components/ImportMapModal.vue'

export default {
  name: 'MapEditorView',
  components: {
    ToastNotification,
    PublishChangesModal,
    ActivityLogDrawer,
    RoomsManagementModal,
    ImportMapModal
  },
  data() {
    return {
      maps: [],
      buildings: [],
      activeMap: null,
      selectedBuilding: null,
      showAddMapModal: false,
      showAddBuildingModal: false,
      editingMap: null,
      editingBuilding: null,
      showDeleteModal: false,
      buildingToDelete: null,
      buildingDeleteCountdown: 3,
      buildingCountdownInterval: null,
      showMapDeleteModal: false,
      mapToDelete: null,
      mapDeleteCountdown: 3,
      mapCountdownInterval: null,
      unpublishedCount: 0,
      isEditingLayout: false,
      editingLayoutSnapshot: null,
      mapForm: {
        name: '',
        width: '6100',
        height: '3000',
        image: null,
        image_path: null
      },
      buildingForm: {
        name: '',
        description: '',
        services: [],
        x_coordinate: '',
        y_coordinate: '',
        map_id: null,
        image: null,
        image_preview: null,
        modal_image: null,
        modal_image_preview: null,
        image_width: 28,
        image_height: 28,
        employees: []
      },
      imageSizeSlider: 28, // Combined slider value for width and height
      newServiceInput: '',
      coordPreview: {
        visible: false,
        x: 0,
        y: 0,
        text: ''
      },
      mapWidth: 0,
      mapHeight: 0,
      // Enhanced Building Creator
      showBuildingCreator: false,
      newBuildingPosition: null,
      // Map scaling and interaction
      mapScale: 1,
      isDragging: false,
      dragStart: { x: 0, y: 0 },
      lastDragPosition: { x: 0, y: 0 },
      dragMode: false,
      zoomMode: false,
      sidebarCollapsed: false, // New state for sidebar collapse
      activePanel: 'map-layout', // Active panel in unified right sidebar
      showPublishChangesModal: false, // Publish modal state
      showRoomsManagementModal: false, // Rooms management modal state
      // Export/Import functionality
      showImportModal: false,
      exporting: false,
      importing: false,
      importProgress: 0,
      importStatus: ''
    }
  },
  async created() {
    await Promise.all([
      this.fetchMaps(),
      this.fetchBuildings(),
      this.fetchUnpublishedCount()
    ])
  },
  mounted() {
    window.addEventListener('click', this.handleOutsideClick)
    // Add keyboard event listeners for arrow key controls
    document.addEventListener('keydown', this.handleKeyDown)
  },
  beforeUnmount() {
    window.removeEventListener('click', this.handleOutsideClick)
    // Remove keyboard event listeners
    document.removeEventListener('keydown', this.handleKeyDown)
    this.stopMapCountdown()
    this.stopBuildingCountdown()
  },
  watch: {
    showAddBuildingModal(newVal) {
      if (newVal) {
        this.$nextTick(() => {
          const panel = document.querySelector('.modern-editor-panel')
          if (panel) panel.style.right = '0'
        })
      }
    },
    editingBuilding(newVal) {
      if (newVal) {
        this.$nextTick(() => {
          const panel = document.querySelector('.modern-editor-panel')
          if (panel) panel.style.right = '0'
        })
      }
    },
    selectedBuilding(newVal) {
      if (newVal) {
        this.$nextTick(() => {
          const infoPanel = document.querySelector('.building-info-panel')
          if (infoPanel) {
            infoPanel.classList.add('show')
          }
        })
      } else {
        this.$nextTick(() => {
          const infoPanel = document.querySelector('.building-info-panel')
          if (infoPanel) {
            infoPanel.classList.remove('show')
          }
        })
      }
    },
    showAddMapModal(newVal) {
      if (newVal) {
        this.$nextTick(() => {
          const panel = document.querySelector('.modern-editor-panel')
          if (panel) panel.style.right = '0'
        })
      }
    },
    editingMap(newVal) {
      if (newVal) {
        this.$nextTick(() => {
          const panel = document.querySelector('.modern-editor-panel')
          if (panel) panel.style.right = '0'
        })
      }
    }
  },

  methods: {
    // Helper function to check if a pixel is transparent
    async isPixelTransparent(imageSrc, x, y, imageWidth, imageHeight) {
      return new Promise((resolve) => {
        const img = new Image()
        
        // Try with CORS first, fallback without if it fails
        img.crossOrigin = 'anonymous'
        
        img.onload = () => {
          try {
            const canvas = document.createElement('canvas')
            const ctx = canvas.getContext('2d')
            canvas.width = imageWidth
            canvas.height = imageHeight
            
            // Draw the image to canvas
            ctx.drawImage(img, 0, 0, imageWidth, imageHeight)
            
            // Get pixel data at the clicked position
            const pixelData = ctx.getImageData(x, y, 1, 1).data
            const alpha = pixelData[3] // Alpha channel (0-255)
            
            // Consider pixel transparent if alpha is less than 128 (50% opacity)
            resolve(alpha < 128)
          } catch (error) {
            console.warn('Canvas pixel detection failed, defaulting to non-transparent:', error)
            resolve(false) // Default to non-transparent on canvas error
          }
        }
        
        img.onerror = () => {
          // If CORS fails, try without CORS
          if (img.crossOrigin === 'anonymous') {
            img.crossOrigin = null
            img.src = imageSrc
            return
          }
          console.warn('Image loading failed for pixel detection, defaulting to non-transparent')
          resolve(false) // Default to non-transparent on error
        }
        
        img.src = imageSrc
      })
    },

    // Enhanced click handler for building markers with transparent pixel detection
    async handleBuildingClick(event, building) {
      if (!building.image_path) {
        // If no image, use the original click behavior
        this.selectBuilding(building)
        return
      }

      const img = event.target
      
      // Ensure we have valid image dimensions
      if (!img.naturalWidth || !img.naturalHeight) {
        console.warn('Image not fully loaded, defaulting to non-transparent')
        this.selectBuilding(building)
        return
      }
      
      const rect = img.getBoundingClientRect()
      
      // Calculate click position relative to the image
      const clickX = event.clientX - rect.left
      const clickY = event.clientY - rect.top
      
      // Ensure click is within image bounds
      if (clickX < 0 || clickY < 0 || clickX >= rect.width || clickY >= rect.height) {
        return // Click outside image bounds
      }
      
      // Scale click coordinates to match the actual image dimensions
      const scaleX = img.naturalWidth / rect.width
      const scaleY = img.naturalHeight / rect.height
      const imageX = Math.floor(clickX * scaleX)
      const imageY = Math.floor(clickY * scaleY)
      
      // Ensure coordinates are within image bounds
      if (imageX < 0 || imageY < 0 || imageX >= img.naturalWidth || imageY >= img.naturalHeight) {
        return // Coordinates outside image bounds
      }
      
      try {
        // Check if the clicked pixel is transparent
        const isTransparent = await this.isPixelTransparent(
          img.src, 
          imageX, 
          imageY, 
          img.naturalWidth, 
          img.naturalHeight
        )
        
        // Only trigger building selection if clicked on non-transparent pixel
        if (!isTransparent) {
          this.selectBuilding(building)
        }
      } catch (error) {
        console.warn('Pixel detection failed, defaulting to non-transparent:', error)
        this.selectBuilding(building)
      }
    },

    async enterLayoutEditModeFor(map) {
      try {
        if (!map || !map.id) return
        const { data } = await axios.get(`/map/${map.id}/layout`)
        this.activeMap = map
        // Ensure buildingForm.map_id is set to the map being edited
        this.buildingForm.map_id = map.id
        await this.fetchBuildings()
        this.editingLayoutSnapshot = data
        this.isEditingLayout = true
        this.$refs.toast?.info('Layout Edit', `Loaded layout snapshot for ${map.name}.`)
      } catch (e) {
        console.error('Failed to load layout snapshot', e)
        this.$refs.toast?.error('Load Failed', 'Could not load layout snapshot.')
      }
    },
    async enterLayoutEditMode() {
      try {
        if (!this.activeMap || !this.activeMap.id) return
        const { data } = await axios.get(`/map/${this.activeMap.id}/layout`)
        this.editingLayoutSnapshot = data
        this.isEditingLayout = true
        // Ensure buildingForm.map_id is set to the active map
        this.buildingForm.map_id = this.activeMap.id
        await this.fetchBuildings()
        this.$refs.toast?.info('Layout Edit', 'Loaded layout snapshot for editing.')
      } catch (e) {
        console.error('Failed to load layout snapshot', e)
        this.$refs.toast?.error('Load Failed', 'Could not load layout snapshot.')
      }
    },
    async saveLayout() {
      try {
        if (!this.activeMap || !this.activeMap.id) return
        const { data } = await axios.post(`/map/${this.activeMap.id}/save-layout`)
        this.$refs.toast?.success('Layout Saved', 'Map layout snapshot saved.')
        // Stay in edit mode; just refresh local snapshot reference
        this.editingLayoutSnapshot = data.snapshot
      } catch (e) {
        console.error('Failed to save layout snapshot', e)
        this.$refs.toast?.error('Save Failed', 'Could not save layout snapshot.')
      }
    },
    async stopEditingLayout() {
      // Auto-save then exit edit mode and restore active map view
      await this.saveLayout()
      // Reload maps to ensure active map is correctly set
      await this.fetchMaps()
      this.isEditingLayout = false
    },
    getImageUrl(path) {
      if (!path) return ''
      if (path.startsWith('http')) return path
      return (process.env.VUE_APP_STORAGE_BASE || 'https://isuecampusmap.site/backend/storage/') + path
    },
    async fetchMaps() {
      try {
        const response = await axios.get('/map')
        // Show published maps and restored maps (not pending deletion)
        // Exclude only truly new unpublished maps that have never been published
        this.maps = (response.data || []).filter(map => 
          map.is_published || (map.published_data && !map.pending_deletion)
        )
        
        // Find and set active map
        const activeMap = this.maps.find(map => map.is_active)
        if (activeMap) {
          this.activeMap = activeMap
          await this.fetchBuildings()
        }
      } catch (error) {
        console.error('Error fetching maps:', error)
        this.$refs.toast.error('Load Failed', 'Error loading maps. Please try again later.')
      }
    },
    
    async fetchBuildings() {
      try {
        const params = {}
        if (this.activeMap?.id) params.map_id = this.activeMap.id
        const response = await axios.get('/buildings', { params })
        
        this.buildings = response.data || []
      } catch (error) {
        console.error('❌ Error fetching buildings:', error)
        if (error.response) {
          console.error('Response status:', error.response.status)
          console.error('Response data:', error.response.data)
        }
        this.$refs.toast.error('Load Failed', 'Error loading buildings. Please try again later.')
      }
    },
    
    handleMapClick(event) {
      if (!this.activeMap || this.isDragging) return
      
      // If in zoom mode, handle zoom instead of building placement
      if (this.zoomMode) {
        this.handleZoomClick(event)
        return
      }
      
      // If in drag mode, don't handle clicks
      if (this.dragMode) {
        return
      }
      
      const mapCanvas = this.$refs.mapCanvas
      if (!mapCanvas) return
      
      const rect = mapCanvas.getBoundingClientRect()
      
      // Calculate mouse position relative to the map container
      const mouseX = event.clientX - rect.left
      const mouseY = event.clientY - rect.top
      
      // Calculate map coordinates accounting for scaling
      const rawX = mouseX + mapCanvas.scrollLeft
      const rawY = mouseY + mapCanvas.scrollTop
      
      // Scale coordinates back to original map dimensions
      const x = Math.round(rawX / this.mapScale)
      const y = Math.round(rawY / this.mapScale)
      
      // Update coordinates for both old and new building creators
      this.buildingForm.x_coordinate = x
      this.buildingForm.y_coordinate = y
      this.newBuildingPosition = { x, y }
      
      
      // If building creator is open, just update position
      
      
      // Open the add building modal if not already open
      if (!this.showAddBuildingModal && !this.editingBuilding) {
        // Ensure buildingForm.map_id is set to the current active map
        this.buildingForm.map_id = this.activeMap ? this.activeMap.id : null
        this.showAddBuildingModal = true
      } else if (this.selectedBuilding) {
        // Clear selection if a building was selected
        this.selectedBuilding = null
      }
    },
    
    handleMapMove(event) {
      // Handle dragging only if in drag mode
      if (this.dragMode) {
        this.handleMapDrag(event)
      }
      
      // ALWAYS handle coordinate preview - regardless of drag mode
      this.updateCoordinatePreview(event)
    },

    updateCoordinatePreview(event) {
      const mapCanvas = this.$refs.mapCanvas
      if (!mapCanvas) return
      
      const rect = mapCanvas.getBoundingClientRect()
      
      // Always calculate current mouse position relative to the map container
      const mouseX = event.clientX - rect.left
      const mouseY = event.clientY - rect.top
      
      // Calculate map coordinates accounting for scaling
      const rawX = mouseX + mapCanvas.scrollLeft
      const rawY = mouseY + mapCanvas.scrollTop
      
      // Scale coordinates back to original map dimensions
      const x = Math.round(rawX / this.mapScale)
      const y = Math.round(rawY / this.mapScale)
      
      // Always update coordinate preview to follow mouse cursor
      this.coordPreview.x = mouseX
      this.coordPreview.y = mouseY
      this.coordPreview.visible = true
      this.coordPreview.text = `(${x}, ${y})`
    },

    handleMouseLeave() {
      // Hide coordinate preview
      this.coordPreview.visible = false
      
      // Stop dragging if active
      if (this.isDragging) {
        this.stopDrag()
      }
    },

    handleMapMouseEnter(event) {
      // Ensure coordinate preview is visible when mouse enters the map
      this.handleMapMove(event)
    },
    
    selectBuilding(building) {
      this.selectedBuilding = building === this.selectedBuilding ? null : building
      
      // If we're in edit mode, update the form with the selected building's data
      if (this.editingBuilding && this.selectedBuilding && this.selectedBuilding.id === this.editingBuilding.id) {
        this.buildingForm.name = building.building_name
        this.buildingForm.description = building.description || ''
        this.buildingForm.services = building.services || ''
        this.buildingForm.x_coordinate = building.x_coordinate
        this.buildingForm.y_coordinate = building.y_coordinate
        this.buildingForm.image_width = building.width || 28
        this.buildingForm.image_height = building.height || 28
      }
    },
    

    
    handleOutsideClick(event) {
      // Close the building info panel when clicking outside
      if (this.selectedBuilding) {
        const infoPanel = document.querySelector('.building-info-panel')
        const markers = document.querySelectorAll('.building-marker')
        
        // Check if click was inside panel or on a marker
        let clickedInside = infoPanel && infoPanel.contains(event.target)
        markers.forEach(marker => {
          if (marker.contains(event.target)) clickedInside = true
        })
        
        if (!clickedInside) {
          this.selectedBuilding = null
        }
      }
    },
    
    editBuilding(building) {
      
      // Store the original building for reference
      this.editingBuilding = building
      
      // Set up form data with proper field mapping
      this.buildingForm = {
        name: building.building_name,
        description: building.description || '',
        services: this.parseServices(building.services) || [],
        x_coordinate: building.x_coordinate,
        y_coordinate: building.y_coordinate,
        map_id: building.map_id,
        image: null, // Will be set if user selects a new image
        image_preview: building.image_path ? (process.env.VUE_APP_STORAGE_BASE || 'https://isuecampusmap.site/backend/storage/') + building.image_path : null,
        modal_image: null, // Will be set if user selects a new modal image
        modal_image_preview: building.modal_image_path ? (process.env.VUE_APP_STORAGE_BASE || 'https://isuecampusmap.site/backend/storage/') + building.modal_image_path : null,
        image_width: building.width || 28,
        image_height: building.height || 28,
        employees: building.employees ? building.employees.map(emp => ({
          id: emp.id,
          name: emp.employee_name,
          position: emp.position || '',
          department: emp.department || '',
          email: emp.email || '',
          image: null,
          image_preview: emp.employee_image ? (process.env.VUE_APP_STORAGE_BASE || 'https://isuecampusmap.site/backend/storage/') + emp.employee_image : null,
          existing_image: emp.employee_image // Store original image path for preservation
        })) : []
      }
      
      // Sync the slider with the building's current image size
      this.imageSizeSlider = building.width || 28
      
      // Open the modal
      this.showAddBuildingModal = true
    },

    editBuildingAndClose(building) {
      // Close the building info modal first
      this.selectedBuilding = null
      
      // Then open the edit modal
      this.editBuilding(building)
    },
    
    showDeleteConfirmation(building) {
      this.buildingToDelete = building
      this.showDeleteModal = true
      this.startBuildingCountdown()
    },

    startBuildingCountdown() {
      this.buildingDeleteCountdown = 3
      this.buildingCountdownInterval = setInterval(() => {
        this.buildingDeleteCountdown--
        if (this.buildingDeleteCountdown <= 0) {
          this.stopBuildingCountdown()
        }
      }, 1000)
    },

    stopBuildingCountdown() {
      if (this.buildingCountdownInterval) {
        clearInterval(this.buildingCountdownInterval)
        this.buildingCountdownInterval = null
      }
    },

    async confirmDeleteBuilding() {
      if (!this.buildingToDelete) return
      
      this.stopBuildingCountdown()
      
      try {
        await axios.delete(`/buildings/${this.buildingToDelete.id}`)
        
        // Clear selection if this was the selected building
        if (this.selectedBuilding && this.selectedBuilding.id === this.buildingToDelete.id) {
          this.selectedBuilding = null
        }
        
        // Refresh the buildings list from server instead of just filtering locally
        await this.fetchBuildings()
        
        // Update unpublished count after building deletion
        this.fetchUnpublishedCount()
        
        // Show success toast notification
        this.$refs.toast.success('Building Deleted', 'Building has been successfully removed from the system.')
        
        // Close the confirmation modal
        this.showDeleteModal = false
        this.buildingToDelete = null
        this.buildingDeleteCountdown = 3
      } catch (error) {
        console.error('Error deleting building:', error.response?.data || error.message || error)
        
        // Show error toast notification
        this.$refs.toast.error('Delete Failed', `Failed to delete building: ${error.message || 'Unknown error'}`)
        
        // Close the confirmation modal
        this.showDeleteModal = false
        this.buildingToDelete = null
        this.buildingDeleteCountdown = 3
      }
    },





    async deleteBuilding(id) {
      // This method is kept for backward compatibility but now uses confirmation modal
      const building = this.buildings.find(b => b.id === id)
      if (building) {
        this.showDeleteConfirmation(building)
      }
    },
    
    handleBuildingImageUpload(event) {
      const file = event.target.files[0]
      if (!file) return
      
      
      // Just set the local reference for later upload and preview
      this.buildingForm.image = file
      this.buildingForm.image_preview = URL.createObjectURL(file)
    },
    
    handleModalImageUpload(event) {
      const file = event.target.files[0]
      if (!file) return
      
      
      // Just set the local reference for later upload and preview
      this.buildingForm.modal_image = file
      this.buildingForm.modal_image_preview = URL.createObjectURL(file)
    },

    // Employee Management Methods
    addEmployee() {
      this.buildingForm.employees.push({
        name: '',
        image: null,
        image_preview: null,
        existing_image: null
      })
    },

    removeEmployee(index) {
      this.buildingForm.employees.splice(index, 1)
    },

    handleEmployeeImageUpload(event, index) {
      const file = event.target.files[0]
      if (!file) return
      
      
      // Set the local reference for later upload and preview
      this.buildingForm.employees[index].image = file
      this.buildingForm.employees[index].image_preview = URL.createObjectURL(file)
    },

    // Service Management Methods
    addServices(event) {
      // Prevent form submission
      if (event) {
        event.preventDefault()
        event.stopPropagation()
      }
      
      if (!this.newServiceInput.trim()) return
      
      // Split by commas and trim each service
      const services = this.newServiceInput.split(',').map(service => service.trim()).filter(service => service)
      
      // Add each service if it's not a duplicate
      services.forEach(service => {
        if (service && !this.buildingForm.services.includes(service)) {
          this.buildingForm.services.push(service)
        }
      })
      
      // Clear the input
      this.newServiceInput = ''
    },

    removeService(index) {
      this.buildingForm.services.splice(index, 1)
    },

    parseServices(services) {
      if (!services) return []
      if (Array.isArray(services)) return services
      
      // Try to parse JSON string
      try {
        const parsed = JSON.parse(services)
        return Array.isArray(parsed) ? parsed : []
      } catch (e) {
        // If it's a comma-separated string, split it
        if (typeof services === 'string') {
          return services.split(',').map(s => s.trim()).filter(s => s)
        }
        return []
      }
    },

    // Building Info Modal Methods
    getServicesList(building) {
      if (!building?.services) return []
      
      // If it's already an array, return it
      if (Array.isArray(building.services)) return building.services
      
      // Try to parse JSON string
      try {
        const parsed = JSON.parse(building.services)
        return Array.isArray(parsed) ? parsed : []
      } catch (e) {
        // If it's a comma-separated string, split it
        if (typeof building.services === 'string') {
          return building.services.split(',').map(s => s.trim()).filter(s => s)
        }
        return []
      }
    },

    getEmployees(building) {
      return Array.isArray(building?.employees) ? building.employees : []
    },

    hasCoordinates(building) {
      return Number.isFinite(+building?.x_coordinate) && Number.isFinite(+building?.y_coordinate)
    },

    hasSize(building) {
      return Number.isFinite(+building?.width) && Number.isFinite(+building?.height)
    },
    
    async saveBuilding() {
      if (!this.activeMap && !this.editingBuilding) {
        this.$refs.toast.error('No Map Selected', 'No active map selected. Please activate a map first.')
        return
      }
      
      // Check that coordinates are set
      if (!this.buildingForm.x_coordinate || !this.buildingForm.y_coordinate) {
        this.$refs.toast.error('Position Required', 'Please click on the map to set the building position.')
        return
      }

      try {
        
        // Create form data object
        const formData = new FormData()
        
        // Add all the form fields
        formData.append('building_name', this.buildingForm.name)
        formData.append('description', this.buildingForm.description || '')
        formData.append('services', JSON.stringify(this.buildingForm.services) || '[]')
        formData.append('x_coordinate', parseInt(this.buildingForm.x_coordinate))
        formData.append('y_coordinate', parseInt(this.buildingForm.y_coordinate))
        formData.append('width', parseInt(this.buildingForm.image_width))
        formData.append('height', parseInt(this.buildingForm.image_height))
        
        
        // Add map_id
        const mapId = this.buildingForm.map_id || (this.activeMap ? this.activeMap.id : null)
        if (!mapId) {
          this.$refs.toast.error('No Map Selected', 'No map selected. Please activate a map first.')
          return
        }
        formData.append('map_id', mapId)
        
        // Always mark as unpublished for now - we'll handle visibility in backend
        formData.append('is_published', false)
        
        // Add image if available
        if (this.$refs.buildingImageInput && this.$refs.buildingImageInput.files[0]) {
          formData.append('image', this.$refs.buildingImageInput.files[0])
        }
        
        // Add modal image if available
        if (this.$refs.modalImageInput && this.$refs.modalImageInput.files[0]) {
          formData.append('modal_image', this.$refs.modalImageInput.files[0])
        }
        
        // Handle employees
        if (this.buildingForm.employees && this.buildingForm.employees.length > 0) {
          // Filter out empty employees (no name)
          const validEmployees = this.buildingForm.employees.filter(employee => 
            employee.name && employee.name.trim()
          )
          
          // Add employee count
          formData.append('employee_count', validEmployees.length)
          
          // Add each employee's data
          validEmployees.forEach((employee, index) => {
            formData.append(`employees[${index}][name]`, employee.name.trim())
            
            // Include employee ID if editing existing employee (for image preservation)
            if (employee.id) {
              formData.append(`employees[${index}][id]`, employee.id)
            }
            
            // Add employee image if available
            if (employee.image) {
              formData.append(`employees[${index}][image]`, employee.image)
            }
          })
        } else {
          // No employees
          formData.append('employee_count', 0)
        }
        
        // Handle updating vs creating
        
        if (this.editingBuilding) {
          
          // For update, convert to raw XMLHttpRequest for maximum compatibility
          await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest()
            xhr.open('POST', `${process.env.VUE_APP_API_BASE || 'https://isuecampusmap.site/backend/api'}/buildings/${this.editingBuilding.id}`)
            // Use stateless token auth; do not send cookies
            xhr.withCredentials = false
            xhr.setRequestHeader('Accept', 'application/json')
            try {
              const token = localStorage.getItem('admin_token')
              if (token) {
                xhr.setRequestHeader('Authorization', `Bearer ${token}`)
              }
            } catch (_) { /* no-op */ }
            
            // Set up proper method override for Laravel
            formData.append('_method', 'PUT')
            
            xhr.onload = function() {
              if (xhr.status >= 200 && xhr.status < 300) {
                resolve(JSON.parse(xhr.responseText))
        } else {
                reject({
                  status: xhr.status,
                  statusText: xhr.statusText,
                  response: xhr.responseText
                })
              }
            }
            
            xhr.onerror = function() {
              reject({
                status: xhr.status,
                statusText: xhr.statusText,
                response: 'XHR error'
              })
            }
            
            xhr.send(formData)
          }).then(() => {
            // Show success toast notification for building update
            this.$refs.toast.success('Building Updated', 'Building has been successfully updated.')
            
            // Update unpublished count
            this.fetchUnpublishedCount()
          })
        } else {
          // For create, use axios directly
          await axios.post('/buildings', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
          })
          
          // Show success toast notification for new building
          this.$refs.toast.success('Building Created', 'New building has been successfully added to the system.')
          
          // Update unpublished count
          this.fetchUnpublishedCount()
        }
        
        // Refresh buildings from the server to get latest data
        await this.fetchBuildings()
        
        // Close modal and reset form
        this.closeBuildingModal()
        

      } catch (error) {
        console.error('Error saving building:', error)
        
        // Extract detailed error message from response
        let errorMessage = 'Unknown error'
        let errorCode = null
        
        // Handle different error structures
        if (error.response && error.response.data) {
          // Standard axios error structure
          if (typeof error.response.data === 'string') {
            try {
              const parsedData = JSON.parse(error.response.data)
              errorMessage = parsedData.message || parsedData.error || error.response.data
              errorCode = parsedData.error_code
            } catch (e) {
              errorMessage = error.response.data
            }
          } else if (error.response.data && typeof error.response.data === 'object') {
            errorMessage = error.response.data.message || error.response.data.error
            errorCode = error.response.data.error_code
            if (error.response.data.errors) {
              const errors = error.response.data.errors
              const firstError = Object.values(errors)[0]
              errorMessage = Array.isArray(firstError) ? firstError[0] : firstError
            }
          }
        } else if (error.response && typeof error.response === 'string') {
          // Direct response string
          try {
            const parsedData = JSON.parse(error.response)
            errorMessage = parsedData.message || parsedData.error || 'Parsing error'
            errorCode = parsedData.error_code
          } catch (e) {
            errorMessage = error.response
          }
        } else if (error.message) {
          errorMessage = error.message
        }

        // Show specific warnings based on error content and error codes
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
    
    openAddBuildingModal() {
      // Ensure buildingForm.map_id is set to the current active map
      this.buildingForm.map_id = this.activeMap ? this.activeMap.id : null
      this.showAddBuildingModal = true
    },

    closeBuildingModal() {
      this.showAddBuildingModal = false
      this.editingBuilding = null
      this.buildingForm = {
        name: '',
        description: '',
        services: [],
        x_coordinate: '',
        y_coordinate: '',
        map_id: this.activeMap ? this.activeMap.id : null,
        image: null,
        image_preview: null,
        modal_image: null,
        modal_image_preview: null,
        image_width: 28,
        image_height: 28,
        employees: []
      }
      this.newServiceInput = ''
      // Hide the modern modal
      this.$nextTick(() => {
        const panel = document.querySelector('.modern-editor-panel')
        if (panel) panel.style.right = '-450px'
      })
    },
    
    editMap(map) {
      this.editingMap = map
      this.mapForm = { 
        ...map,
        // If the map has an image path, create the complete URL for display
        image_path: map.image_path ? (process.env.VUE_APP_STORAGE_BASE || 'https://isuecampusmap.site/backend/storage/') + map.image_path : null
      }
      this.showAddMapModal = true
    },
    
    showMapDeleteConfirmation(map) {
      this.mapToDelete = map
      this.showMapDeleteModal = true
      this.startMapCountdown()
    },

    closeMapDeleteModal() {
      this.stopMapCountdown()
      this.showMapDeleteModal = false
      this.mapToDelete = null
      this.mapDeleteCountdown = 3
    },

    startMapCountdown() {
      this.mapDeleteCountdown = 3
      this.mapCountdownInterval = setInterval(() => {
        this.mapDeleteCountdown--
        if (this.mapDeleteCountdown <= 0) {
          this.stopMapCountdown()
        }
      }, 1000)
    },

    stopMapCountdown() {
      if (this.mapCountdownInterval) {
        clearInterval(this.mapCountdownInterval)
        this.mapCountdownInterval = null
      }
    },

    async confirmMapDelete() {
      if (!this.mapToDelete) return
      
      try {
        await axios.delete(`/map/${this.mapToDelete.id}`)
        await this.fetchMaps()
        
        // Update unpublished count after map deletion
        this.fetchUnpublishedCount()
        
        // Show success toast notification
        this.$refs.toast.success('Map Marked for Deletion', 'Map has been marked for deletion and will be removed when you publish changes.')
        
        // Close the confirmation modal
        this.closeMapDeleteModal()
      } catch (error) {
        console.error('Error deleting map:', error)
        this.$refs.toast.error('Delete Failed', 'Error deleting map. Please try again.')
        
        // Close the confirmation modal
        this.closeMapDeleteModal()
      }
    },

        async deleteMap(id) {
      // This method is kept for backward compatibility but now uses confirmation modal
      const map = this.maps.find(m => m.id === id)
      if (map) {
        this.showMapDeleteConfirmation(map)
      }
    },
    
    async setActiveMap(id) {
      try {
        await axios.put(`/map/${id}/activate`)
        await this.fetchMaps()
        // Update unpublished count after map activation change
        this.fetchUnpublishedCount()
      } catch (error) {
        console.error('Error setting active map:', error)
        this.$refs.toast.error('Activation Failed', 'Error setting active map. Please try again.')
      }
    },
    
    async handleImageUpload(event) {
      const file = event.target.files[0]
      if (!file) return

      // Check file size (100MB = 100 * 1024 * 1024 bytes)
      const maxSize = 100 * 1024 * 1024 // 100MB in bytes
      if (file.size > maxSize) {
        this.$refs.toast.error('File Too Large', `Image file is ${(file.size / (1024 * 1024)).toFixed(1)}MB. Maximum allowed size is 100MB.`)
        // Clear the file input
        event.target.value = ''
        return
      }

      // Check file type
      const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']
      if (!allowedTypes.includes(file.type)) {
        this.$refs.toast.error('Invalid File Type', 'Please select a valid image file (JPEG, PNG, GIF, or WebP).')
        // Clear the file input
        event.target.value = ''
        return
      }

      // Just set the local preview
      this.mapForm.image_path = URL.createObjectURL(file)
      // Store the file object for uploading
      this.mapForm.image = file
      
      // Show success message for valid file
      this.$refs.toast.success('Image Selected', `Image loaded successfully (${(file.size / (1024 * 1024)).toFixed(1)}MB)`)
      
      // We'll upload the file when the form is submitted
    },
    
    async saveMap() {
      try {
      const formData = new FormData()
        formData.append('name', this.mapForm.name)
        formData.append('width', parseInt(this.mapForm.width) || 800) // Default to 800 if not valid
        formData.append('height', parseInt(this.mapForm.height) || 600) // Default to 600 if not valid
        
        // Set as unpublished so changes don't auto-reflect to app
        formData.append('is_published', false)
        
        // Check for image file
        if (this.$refs.mapImageInput && this.$refs.mapImageInput.files[0]) {
          formData.append('image', this.$refs.mapImageInput.files[0])
      }

        if (this.editingMap) {
          // Update existing map
          await axios.post(`/map/${this.editingMap.id}?_method=PUT`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
          })
          
          // Show success toast notification for map update
          this.$refs.toast.success('Map Updated', 'Map has been successfully updated.')
          
          // Update unpublished count
          this.fetchUnpublishedCount()
        } else {
                  // Create new map
        await axios.post('/map', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        
        // Show success toast notification for new map
        this.$refs.toast.success('Map Created', 'New map has been successfully created.')
        
        // Update unpublished count
        this.fetchUnpublishedCount()
        }

        await this.fetchMaps()
        this.closeMapModal()
        

      } catch (error) {
        console.error('Error saving map:', error)
        console.error('Response data:', error.response?.data)
        
        // Handle specific validation errors
        if (error.response?.status === 422) {
          const responseData = error.response.data
          if (responseData.message && responseData.message.includes('image field must not be greater than')) {
            this.$refs.toast.error('File Too Large', 'The image file is too large. Please use an image smaller than 100MB.')
          } else if (responseData.errors) {
            // Handle other validation errors
            const firstError = Object.values(responseData.errors)[0]
            if (Array.isArray(firstError) && firstError.length > 0) {
              this.$refs.toast.error('Validation Error', firstError[0])
            } else {
              this.$refs.toast.error('Validation Error', 'Please check your input and try again.')
            }
          } else {
            this.$refs.toast.error('Validation Error', responseData.message || 'Please check your input and try again.')
          }
        } else if (error.response?.status === 413) {
          this.$refs.toast.error('File Too Large', 'The image file is too large for the server to process.')
        } else if (error.response?.status >= 500) {
          this.$refs.toast.error('Server Error', 'A server error occurred. Please try again later.')
        } else {
          this.$refs.toast.error('Save Failed', 'Error saving map. Please try again.')
        }
      }
    },
    
    closeMapModal() {
      this.showAddMapModal = false
      this.editingMap = null
      this.mapForm = {
        name: '',
        width: '5500',
        height: '2700',
        image: null,
        image_path: null
      }
      // Hide the modern modal
      this.$nextTick(() => {
        const panel = document.querySelector('.modern-editor-panel')
        if (panel) panel.style.right = '-450px'
      })
    },
    
    
    getImagePath(building) {
      // Debug data coming from backend
      
      // The image path is already in the correct format (images/buildings/...)
      // We just need to prefix it with the storage URL
      if (building.image_path) {
        return (process.env.VUE_APP_STORAGE_BASE || 'https://isuecampusmap.site/backend/storage/') + building.image_path
      }
      
      // Use a default placeholder image when no image is available
      return 'https://via.placeholder.com/300x200?text=ISU+Building+Image'
    },
    
    // Test method to directly upload an image to the server
    async testDirectImageUpload() {
      if (!this.$refs.buildingImageInput || !this.$refs.buildingImageInput.files[0]) {
        return
      }
      
      try {
        const formData = new FormData()
        const file = this.$refs.buildingImageInput.files[0]
        formData.append('image', file)
        
        
        await axios.post('/buildings/upload', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        
        
      } catch (error) {
        console.error('❌ Direct upload error:', error)
        if (error.response) {
          console.error('Response:', error.response.data)
        }
      }
    },
    
    async diagnoseBuildingIssues() {
      // 1. Check building data loaded in memory
      
      // 2. Make a fresh API call to compare with local data
      try {
        const response = await axios.get('/buildings')
        const freshBuildings = response.data || []
        
        
        // 3. Compare loaded buildings with fresh API results
        if (this.buildings.length !== freshBuildings.length) {
          console.warn('⚠️ Building count mismatch: memory has', this.buildings.length, 'but API returned', freshBuildings.length)
        }
        
        // 4. Check for image path issues in each building
        freshBuildings.forEach(apiBldg => {
          const memBldg = this.buildings.find(b => b.id === apiBldg.id)
          
          if (memBldg && memBldg.image_path !== apiBldg.image_path) {
            console.warn('⚠️ Image path mismatch for building', apiBldg.id)
          }
          
          // Test if image actually loads
          if (apiBldg.image_path) {
            const img = new Image()
            img.onload = () => {}
            img.onerror = () => console.error(`❌ Image for building ${apiBldg.id} FAILS to load`)
            img.src = `${process.env.VUE_APP_STORAGE_BASE || 'https://isuecampusmap.site/backend/storage/'}${apiBldg.image_path}`
        }
        })
        
        // 5. Check Laravel public storage link
        try {
          const testURL = (process.env.VUE_APP_STORAGE_BASE || 'https://isuecampusmap.site/backend/storage/') + 'test-access.txt'
          await axios.head(testURL)
      } catch (error) {
          console.error('❌ Storage symlink test failed:', error.message)
        }
        
        // 6. Test PUT method on a building (without changing it)
        if (freshBuildings.length > 0) {
          const testBuilding = freshBuildings[0]
          
          try {
            // Create a minimal FormData with just the name
      const formData = new FormData()
            formData.append('building_name', testBuilding.building_name)
            formData.append('map_id', testBuilding.map_id)
            formData.append('x_coordinate', testBuilding.x_coordinate)
            formData.append('y_coordinate', testBuilding.y_coordinate)
            
            // Attempt the PUT request
            await axios({
              method: 'put',
              url: `/buildings/${testBuilding.id}`,
              data: formData,
              headers: { 'Content-Type': 'multipart/form-data' }
            })
            
          } catch (error) {
            console.error('❌ PUT test failed:', error.message)
            if (error.response) {
              console.error('Response status:', error.response.status)
              console.error('Response data:', error.response.data)
            }
          }
        }
        
      } catch (error) {
        console.error('Error during diagnosis:', error)
      }
    },
    
    // Removed Building Creator methods
    
    
    
    
    
    handlePositionChange(position) {
      this.newBuildingPosition = position
    },
    
    
    
    setImageSize(width, height) {
      this.buildingForm.image_width = width
      this.buildingForm.image_height = height
      this.imageSizeSlider = width // Update slider to match the preset
    },

    updateImageSizeFromSlider() {
      // Set both width and height to the same value from slider
      this.buildingForm.image_width = this.imageSizeSlider
      this.buildingForm.image_height = this.imageSizeSlider
    },

    handleKeyDown(event) {
      // Only handle arrow keys when building modal is open and coordinates are set
      if (!this.showAddBuildingModal && !this.editingBuilding) return
      if (!this.buildingForm.x_coordinate || !this.buildingForm.y_coordinate) return
      
      // Check if we're in an input field - don't handle arrow keys there
      if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') return
      
      const step = event.shiftKey ? 10 : 1 // Shift + arrow = 10px steps, normal arrow = 1px steps
      let newX = parseInt(this.buildingForm.x_coordinate)
      let newY = parseInt(this.buildingForm.y_coordinate)
      
      switch (event.key) {
        case 'ArrowUp':
          event.preventDefault()
          newY = Math.max(0, newY - step)
          break
        case 'ArrowDown':
          event.preventDefault()
          newY = Math.min(this.activeMap?.height || 1000, newY + step)
          break
        case 'ArrowLeft':
          event.preventDefault()
          newX = Math.max(0, newX - step)
          break
        case 'ArrowRight':
          event.preventDefault()
          newX = Math.min(this.activeMap?.width || 1000, newX + step)
          break
        default:
          return // Don't prevent default for other keys
      }
      
      // Update coordinates
      this.buildingForm.x_coordinate = newX
      this.buildingForm.y_coordinate = newY
      this.newBuildingPosition = { x: newX, y: newY }
    },
    
    // Map interaction methods
    startDrag(event) {
      if (event.button !== 0) return // Only left mouse button
      
      // Only allow dragging if in drag mode
      if (!this.dragMode) return
      
      this.isDragging = true
      this.dragStart = { x: event.clientX, y: event.clientY }
      this.lastDragPosition = { 
        x: this.$refs.mapCanvas.scrollLeft, 
        y: this.$refs.mapCanvas.scrollTop 
      }
      event.preventDefault()
      
    },
    
    stopDrag() {
      this.isDragging = false
    },
    
    handleMapDrag(event) {
      if (!this.isDragging || !this.dragMode) return
      
      const deltaX = event.clientX - this.dragStart.x
      const deltaY = event.clientY - this.dragStart.y
      
      const mapCanvas = this.$refs.mapCanvas
      if (mapCanvas) {
        // Calculate new scroll positions
        const newScrollLeft = this.lastDragPosition.x - deltaX
        const newScrollTop = this.lastDragPosition.y - deltaY
        
        // Apply scroll limits to prevent going outside map boundaries
        const limits = this.calculateScrollLimits()
        
        mapCanvas.scrollLeft = Math.max(0, Math.min(limits.maxScrollX, newScrollLeft))
        mapCanvas.scrollTop = Math.max(0, Math.min(limits.maxScrollY, newScrollTop))
      }
      
      // Update coordinate preview during drag to keep it following the mouse
      this.updateCoordinatePreview(event)
    },
    

    
    zoomIn() {
      const newScale = this.mapScale * 1.2
      this.mapScale = Math.min(3, newScale) // Limit max zoom to 300%
      this.updateScrollVisibility()
      
      // Apply scroll limits after zoom change
      this.$nextTick(() => {
        this.constrainMapToLimits()
      })
      
    },
    
    zoomOut() {
      const newScale = this.mapScale / 1.2
      this.mapScale = Math.max(0.1, newScale) // Limit min zoom to 10%
      this.updateScrollVisibility()
      
      // Apply scroll limits after zoom change
      this.$nextTick(() => {
        this.constrainMapToLimits()
      })
      
    },
    
    resetMapView() {
      this.calculateInitialZoom()
      this.centerMap()
      this.updateScrollVisibility()
      
      // Ensure map stays within limits after reset
      this.$nextTick(() => {
        this.constrainMapToLimits()
      })
    },
    
    toggleZoomMode() {
      this.zoomMode = !this.zoomMode
      this.dragMode = false // Disable drag mode when zoom mode is active
    },
    
    toggleDragMode() {
      this.dragMode = !this.dragMode
      this.zoomMode = false // Disable zoom mode when drag mode is active
    },
    
    handleZoomClick(event) {
      const mapCanvas = this.$refs.mapCanvas
      if (!mapCanvas) return
      
      const rect = mapCanvas.getBoundingClientRect()
      const mouseX = event.clientX - rect.left + mapCanvas.scrollLeft
      const mouseY = event.clientY - rect.top + mapCanvas.scrollTop
      
      // Zoom in by 20% at click position
      const newScale = Math.min(3, this.mapScale * 1.2) // Limit max zoom to 300%
      const scaleChange = newScale / this.mapScale
      
      // Calculate new scroll position to zoom towards mouse position
      const newScrollX = mouseX * scaleChange - (event.clientX - rect.left)
      const newScrollY = mouseY * scaleChange - (event.clientY - rect.top)
      
      this.mapScale = newScale
      
      // Apply scroll limits to prevent going outside map boundaries
      const limits = this.calculateScrollLimits()
      
      // Apply new scroll position with limits
      mapCanvas.scrollLeft = Math.max(0, Math.min(limits.maxScrollX, newScrollX))
      mapCanvas.scrollTop = Math.max(0, Math.min(limits.maxScrollY, newScrollY))
      
    },
    
    getMapWrapperStyle() {
      return {
        position: 'relative',
        display: 'block',
        width: '100%',
        height: '100%',
        transformOrigin: '0 0',
        transform: `scale(${this.mapScale})`
      }
    },



    // Sidebar toggle methods
    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed
    },

    // Image handling methods
    handleImageError(event) {
      console.error('Image failed to load:', event.target.src)
      // You could set a fallback image here
      event.target.style.display = 'none'
    },

    handleImageLoad() {
    },
    
    onMapImageLoad() {
      // Calculate initial zoom to fit the entire map in view
      this.$nextTick(() => {
        this.calculateInitialZoom()
        this.centerMap()
      })
    },
    
    onMapImageError(event) {
      console.error('❌ Map image failed to load:', event.target.src)
      console.error('🔍 Active map data:', this.activeMap)
    },

    handleBuildingImageError(event) {
      console.error('❌ Building image failed to load:', event.target.src)
      // Hide the broken image and show placeholder
      event.target.style.display = 'none'
      const imageSection = event.target.closest('.building-image-section')
      if (imageSection) {
        const placeholder = imageSection.querySelector('.no-image-placeholder')
        if (placeholder) {
          placeholder.style.display = 'flex'
        }
      }
    },



    calculateInitialZoom() {
      const mapContainer = this.$refs.mapCanvas
      if (!mapContainer || !this.activeMap) return
      
      const containerRect = mapContainer.getBoundingClientRect()
      const mapWidth = this.activeMap.width
      const mapHeight = this.activeMap.height
      
      // PERFECT FIT - Make map fit exactly in container without scroll bars
      const scaleX = containerRect.width / mapWidth
      const scaleY = containerRect.height / mapHeight
      const fitScale = Math.min(scaleX, scaleY) // Use the smaller scale to fit completely
      
      // Option A: Use calculated zoom with padding
      // Change this value to adjust initial zoom:
      // 0.98 = 98% (current - shows more of the map)
      // 0.95 = 95% (zoomed in more - shows less of the map)
      // 0.90 = 90% (zoomed in even more)
      // 0.85 = 85% (zoomed in a lot)
      // 1.0 = 100% (no padding - exact fit)
      const paddedScale = fitScale * 1.0  // 100% = exact fit, no gap
      this.mapScale = Math.max(0.1, paddedScale)
      
      // Option B: Set fixed zoom level (uncomment one of these):
      // this.mapScale = 0.5    // 50% - very zoomed out (see whole map)
      // this.mapScale = 0.8    // 80% - zoomed out (see most of map)
      // this.mapScale = 1.0    // 100% - full size
      // this.mapScale = 1.5    // 150% - zoomed in (see details)
      // this.mapScale = 2.0    // 200% - very zoomed in (see fine details)
      
      // Ensure scroll bars are hidden initially
      this.$nextTick(() => {
        this.updateScrollVisibility()
      })
      
    },



    centerMap() {
      const mapContainer = this.$refs.mapCanvas
      if (!mapContainer) return
      
      const containerRect = mapContainer.getBoundingClientRect()
      const imageElement = mapContainer.querySelector('.map-image')
      if (!imageElement) return
      
      const imageRect = imageElement.getBoundingClientRect()
      
      // Center the map with scroll limits
      const limits = this.calculateScrollLimits()
      const centerX = Math.max(0, Math.min(limits.maxScrollX, (imageRect.width - containerRect.width) / 2))
      const centerY = Math.max(0, Math.min(limits.maxScrollY, (imageRect.height - containerRect.height) / 2))
      
      mapContainer.scrollLeft = centerX
      mapContainer.scrollTop = centerY
      
    },
    
    updateScrollVisibility() {
      const mapContainer = this.$refs.mapCanvas
      if (!mapContainer) return
      
      // Check if content overflows the container
      const hasHorizontalOverflow = mapContainer.scrollWidth > mapContainer.clientWidth
      const hasVerticalOverflow = mapContainer.scrollHeight > mapContainer.clientHeight
      
      // Show/hide scroll bars based on overflow
      if (hasHorizontalOverflow || hasVerticalOverflow) {
        mapContainer.style.overflow = 'auto'
      } else {
        mapContainer.style.overflow = 'hidden'
      }
      
    },

    calculateScrollLimits() {
      const mapContainer = this.$refs.mapCanvas
      if (!mapContainer || !this.activeMap) {
        return { maxScrollX: 0, maxScrollY: 0 }
      }
      
      // Get the actual scaled dimensions of the map image
      const scaledMapWidth = this.activeMap.width * this.mapScale
      const scaledMapHeight = this.activeMap.height * this.mapScale
      
      // Get container dimensions
      const containerRect = mapContainer.getBoundingClientRect()
      
      // Calculate maximum scroll values to prevent showing white void
      // If map is smaller than container, no scrolling needed (maxScroll = 0)
      // If map is larger, allow scrolling only to the edge of the map
      const maxScrollX = Math.max(0, scaledMapWidth - containerRect.width)
      const maxScrollY = Math.max(0, scaledMapHeight - containerRect.height)
      
      
      return { maxScrollX, maxScrollY }
    },

    constrainMapToLimits() {
      const mapContainer = this.$refs.mapCanvas
      if (!mapContainer) return
      
      const limits = this.calculateScrollLimits()
      
      // Get current scroll position
      const currentScrollX = mapContainer.scrollLeft
      const currentScrollY = mapContainer.scrollTop
      
      // Constrain to limits
      const constrainedScrollX = Math.max(0, Math.min(limits.maxScrollX, currentScrollX))
      const constrainedScrollY = Math.max(0, Math.min(limits.maxScrollY, currentScrollY))
      
      // Apply constrained values if they differ from current
      if (constrainedScrollX !== currentScrollX || constrainedScrollY !== currentScrollY) {
        mapContainer.scrollLeft = constrainedScrollX
        mapContainer.scrollTop = constrainedScrollY
        
      }
    },

    clearPositionMarker() {
      // Clear the position marker coordinates
      this.buildingForm.x_coordinate = ''
      this.buildingForm.y_coordinate = ''
      this.newBuildingPosition = null
      
      // Hide coordinate preview
      this.coordPreview.visible = false
      
    },
    
    // Publication/Push Update methods
    async fetchUnpublishedCount() {
      try {
        const response = await axios.get('/publish/status')
        this.unpublishedCount = response.data.unpublished.total
      } catch (error) {
        console.error('Error fetching unpublished count:', error)
      }
    },
    
    showPublishModal() {
      if (this.unpublishedCount === 0) {
        this.$refs.toast.info('No Updates', 'No unpublished changes to push.')
        return
      }
      
      this.showPublishChangesModal = true
    },
    
    handlePublishCancel() {
      this.showPublishChangesModal = false
    },
    
    handlePublishSuccess(data) {
      this.showPublishChangesModal = false
      this.$refs.toast.success('Updates Pushed', `Successfully published ${data.total_published} items to the app.`)
      this.unpublishedCount = 0
      // Refresh the data to reflect published changes
      this.fetchMaps()
      this.fetchBuildings()
    },
    
    handlePublishError(message) {
      this.showPublishChangesModal = false
      this.$refs.toast.error('Push Failed', message || 'Failed to push updates to the app.')
    },
    
    handleChangeProcessed(changeData) {
      // Handle individual change processing
      const { type } = changeData
      
      switch (type) {
        case 'building-deleted':
          this.$refs.toast.success('Moved to Trash', 'Building has been moved to trash.')
          break
        case 'building-restored':
          this.$refs.toast.success('Building Restored', 'Building has been restored from trash.')
          break
        case 'building-reverted':
          this.$refs.toast.success('Changes Reverted', 'Building changes have been reverted.')
          break
        case 'building-published':
          this.$refs.toast.success('Building Published', 'Building has been published to the app.')
          break
        case 'building-deletion-published':
          this.$refs.toast.success('Moved to Trash', 'Building has been moved to trash.')
          break
        case 'map-reverted':
          this.$refs.toast.success('Map Changes Reverted', 'Map changes have been reverted.')
          break
        case 'map-published':
          this.$refs.toast.success('Map Published', 'Map has been published to the app.')
          break
        case 'map-restored':
          this.$refs.toast.success('Map Restored', 'Map has been restored from trash.')
          // Fully refresh data so publish button/count reacts and map returns without waiting for bulk publish
          this.fetchMaps()
          this.fetchBuildings()
          this.fetchUnpublishedCount()
          break
        case 'map-deletion-published':
          // Avoid racing the modal's refresh; only update the counter
          this.$refs.toast.success('Moved to Trash', 'Map has been moved to trash.')
          this.fetchUnpublishedCount()
          return
      }
      
      // Refresh data and unpublished count
      this.fetchMaps()
      this.fetchBuildings()
      this.fetchUnpublishedCount()
    },
    
    // Keep the old method for backward compatibility (if needed elsewhere)
    async pushUpdates() {
      if (this.unpublishedCount === 0) {
        this.$refs.toast.info('No Updates', 'No unpublished changes to push.')
        return
      }
      
      try {
        const response = await axios.post('/publish/all')
        this.$refs.toast.success('Updates Pushed', `Successfully published ${response.data.total_published} items to the app.`)
        this.unpublishedCount = 0
      } catch (error) {
        console.error('Error pushing updates:', error)
        this.$refs.toast.error('Push Failed', 'Failed to push updates to the app.')
      }
    },

    showToast(arg1, arg2, arg3) {
      // Support multiple call signatures:
      // 1) (type, title, message)
      // 2) (title, message, type)
      // 3) (title, message) -> defaults to info
      const validTypes = ['success', 'error', 'warning', 'info']
      let type = 'info'
      let title = ''
      let message = ''

      if (validTypes.includes(arg1)) {
        type = arg1
        title = arg2 || ''
        message = arg3 || ''
      } else if (validTypes.includes(arg3)) {
        title = arg1 || ''
        message = arg2 || ''
        type = arg3
      } else {
        title = arg1 || ''
        message = arg2 || ''
      }

      this.$refs.toast?.[type]?.(title, message)
    },

    // Room Management Methods
    openRoomsManagement() {
      // For new buildings, we need to save first or use a temporary building object
      if (!this.editingBuilding && !this.buildingForm.name) {
        this.$refs.toast?.info('Info', 'Please enter a building name first before managing rooms.')
        return
      }
      
      // If it's a new building, we'll need to handle it differently
      if (!this.editingBuilding) {
        this.$refs.toast?.info('Info', 'Please save the building first, then you can manage rooms.')
        return
      }
      
      this.showRoomsManagementModal = true
    },

    closeRoomsManagement() {
      this.showRoomsManagementModal = false
    },

    handleRoomAdded(room) {
      this.$refs.toast?.success('Room Added', `Room "${room.name}" has been added successfully.`)
      // Refresh building data to include new room count
      this.fetchBuildings()
      // Update unpublished count for publish button
      this.fetchUnpublishedCount()
    },

    handleRoomUpdated(room) {
      this.$refs.toast?.success('Room Updated', `Room "${room.name}" has been updated successfully.`)
      // Refresh building data
      this.fetchBuildings()
      // Update unpublished count for publish button
      this.fetchUnpublishedCount()
    },

    handleRoomDeleted(room) {
      this.$refs.toast?.success('Room Deleted', `Room "${room.name}" has been deleted successfully.`)
      // Refresh building data
      this.fetchBuildings()
      // Update unpublished count for publish button
      this.fetchUnpublishedCount()
    },

    // Export/Import functionality
    async exportMap(map) {
      if (!map) {
        this.$refs.toast.error('No Map Selected', 'Please select a map to export')
        return
      }

      this.exporting = true
      this.$refs.toast.loading('Exporting Map', `Preparing "${map.name}" for export...`)
      
      try {
        // Update progress to show we're starting
        this.$refs.toast.updateLoadingProgress(10, 'Connecting to server...')
        
        const response = await axios.get(`/map-export/${map.id}`, {
          responseType: 'blob',
          onDownloadProgress: (progressEvent) => {
            if (progressEvent.lengthComputable) {
              const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
              // Start from 20% and go up to 90% for download progress
              const adjustedProgress = 20 + Math.round((percentCompleted * 70) / 100)
              this.$refs.toast.updateLoadingProgress(adjustedProgress, `Downloading map data...`)
            }
          }
        })
        
        if (response.status === 200) {
          // Update progress for file processing
          this.$refs.toast.updateLoadingProgress(90, 'Processing export file...')
          
          // Create blob URL and trigger download
          const blob = new Blob([response.data], { type: 'application/zip' })
          const url = window.URL.createObjectURL(blob)
          
          // Generate filename from map name and current date
          const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-')
          const filename = `map_export_${map.name.replace(/[^a-zA-Z0-9_-]/g, '_')}_${timestamp}.zip`
          
          // Update progress for final steps
          this.$refs.toast.updateLoadingProgress(95, 'Preparing download...')
          
          const link = document.createElement('a')
          link.href = url
          link.download = filename
          document.body.appendChild(link)
          link.click()
          document.body.removeChild(link)
          window.URL.revokeObjectURL(url)
          
          // Complete the progress
          this.$refs.toast.updateLoadingProgress(100, 'Export completed!')
          
          // Small delay to show 100% completion, then remove loading toast and show success
          setTimeout(() => {
            this.$refs.toast.removeLoadingToasts()
            this.$refs.toast.success('Export Successful', `Map "${map.name}" has been exported successfully`)
          }, 500)
        } else {
          throw new Error('Export failed')
        }
      } catch (error) {
        console.error('Export error:', error)
        // Remove loading toast and show error
        this.$refs.toast.removeLoadingToasts()
        this.$refs.toast.error('Export Failed', error.response?.data?.message || error.message || 'Failed to export map')
      } finally {
        this.exporting = false
      }
    },

    openImportModal() {
      this.showImportModal = true
    },

    closeImportModal() {
      this.showImportModal = false
      // Reset import progress state
      this.importing = false
      this.importProgress = 0
      this.importStatus = ''
    },

    async handleImportMap(file) {
      this.importing = true
      this.importProgress = 0
      this.importStatus = 'Preparing import...'
      
      try {
        console.log('Starting async import process', {
          fileName: file.name,
          fileSize: file.size,
          fileSizeMB: Math.round(file.size / 1024 / 1024 * 100) / 100,
          fileType: file.type
        })

        // Show warning for very large files
        const fileSizeMB = file.size / 1024 / 1024
        if (fileSizeMB > 100) {
          this.importStatus = `Uploading large file (${Math.round(fileSizeMB)}MB) - this may take several minutes...`
        }

        // Try normal upload first, fallback to chunked if it fails
        this.importStatus = 'Uploading file...'
        console.log('Attempting normal upload first')
        
        let response
        // Skip normal upload entirely and use chunked upload to avoid corruption
        console.log('Using chunked upload directly to avoid file corruption')
        this.importStatus = 'Preparing chunked upload...'
        response = await this.uploadFileInChunks(file)

        console.log('Upload response received', response)

        if (
          (response.complete && response.importId) ||
          (response.accepted && response.importId)
        ) {
          // Start polling for status
          this.importStatus = 'Processing import...'
          this.importProgress = 100
          await this.pollImportStatus(response.importId)
        } else if (response.success) {
          // Map imported successfully—treat as a win!
          this.importStatus = response.message || 'Import complete!';
          this.importProgress = 100;
          
          // Show success toast with high z-index to appear above modal
          setTimeout(() => {
            this.$refs.toast.success('Import Successful', response.message || 'Map imported successfully!')
            this.closeImportModal()
            // Refresh the maps list
            this.fetchMaps()
            this.fetchUnpublishedCount()
          }, 1000)
          
          return response;
        } else {
          throw new Error(response.message || 'Failed to start import')
        }
      } catch (error) {
        console.error('Import error:', error)
        let errorMessage = 'Failed to import map'
        
        this.importStatus = 'Import failed!'
        this.importProgress = 0
        
        if (error.response?.data?.message) {
          errorMessage = error.response.data.message
        } else if (error.message) {
          errorMessage = error.message
        } else if (error.code === 'ECONNABORTED' || error.message?.includes('timeout')) {
          errorMessage = 'Upload timeout - file may be too large or server is overloaded. Try again or use a smaller file.'
        } else if (error.response?.status === 413) {
          errorMessage = 'File too large - server rejected the file size. Please reduce file size and try again.'
        } else if (error.response?.status === 422) {
          errorMessage = 'Invalid file format - please ensure you are uploading a valid ZIP file'
        } else if (error.response?.status === 504) {
          errorMessage = 'Server timeout - the upload is taking too long. Try uploading during off-peak hours or use a smaller file.'
        } else if (error.response?.status === 502 || error.response?.status === 503) {
          errorMessage = 'Server temporarily unavailable - please try again in a few minutes.'
        }
        
        setTimeout(() => {
          this.importing = false
          this.importProgress = 0
          this.importStatus = ''
        }, 2000)
        
        this.$refs.toast.error('Import Failed', errorMessage)
      }
    },

    async pollImportStatus(importId) {
      const maxAttempts = 120 // 10 minutes max (5 second intervals)
      let attempts = 0
      
      const poll = async () => {
        try {
          attempts++
          const statusResponse = await axios.get(`/map-export/import/status?id=${importId}`)
          const status = statusResponse.data

          // Drive step-based processing on shared hosting
          if (status && (status.status === 'queued' || status.status === 'running')) {
            try {
              await axios.post('/map-export/import/step', { id: importId })
            } catch (_) { /* no-op; next poll will try again */ }
          }
          
          console.log('Import status:', status)
          
          // Update UI with current status
          this.importStatus = status.message || 'Processing...'
          if (status.progress !== undefined) {
            // Map backend progress (0-100) to frontend progress (30-100)
            this.importProgress = Math.max(30, Math.min(100, 30 + (status.progress * 0.7)))
          }
          
          if (status.status === 'success') {
            this.importStatus = 'Import complete!'
            this.importProgress = 100
            
            // Show success and close modal
            setTimeout(() => {
              this.$refs.toast.success('Import Successful', 'Map has been imported successfully')
              this.closeImportModal()
            }, 1000)
            
            // Refresh the maps list
            await this.fetchMaps()
            this.fetchUnpublishedCount()
            
            // Reset UI after delay
            setTimeout(() => {
              this.importing = false
              this.importProgress = 0
              this.importStatus = ''
            }, 2000)
            
          } else if (status.status === 'error') {
            throw new Error(status.message || 'Import failed')
            
          } else if (status.status === 'running' || status.status === 'queued') {
            // Continue polling if not finished and haven't exceeded max attempts
            if (attempts < maxAttempts) {
              setTimeout(poll, 5000) // Poll every 5 seconds
            } else {
              throw new Error('Import timeout - process took too long')
            }
          } else {
            throw new Error(`Unknown import status: ${status.status}`)
          }
          
        } catch (error) {
          console.error('Status polling error:', error)
          
          this.importStatus = 'Import failed!'
          this.importProgress = 0
          
          let errorMessage = 'Import failed'
          if (error.response?.data?.message) {
            errorMessage = error.response.data.message
          } else if (error.message) {
            errorMessage = error.message
          }
          
          setTimeout(() => {
            this.importing = false
            this.importProgress = 0
            this.importStatus = ''
          }, 2000)
          
          this.$refs.toast.error('Import Failed', errorMessage)
        }
      }
      
      // Start polling
      setTimeout(poll, 2000) // Wait 2 seconds before first status check
    },

    // Helper method to convert ArrayBuffer to base64 in chunks to avoid call stack overflow
    arrayBufferToBase64(buffer) {
      const chunkSize = 8192 // Process 8KB at a time
      let binary = ''
      const bytes = new Uint8Array(buffer)
      
      for (let i = 0; i < bytes.byteLength; i += chunkSize) {
        const chunk = bytes.slice(i, i + chunkSize)
        binary += String.fromCharCode.apply(null, chunk)
      }
      
      return btoa(binary)
    },

    async uploadFileInChunks(file) {
      const chunkSize = 5 * 1024 * 1024 // 5MB per chunk
      const totalChunks = Math.ceil(file.size / chunkSize)
      const uploadId = Date.now() + '_' + Math.random().toString(36).substr(2, 9)

      for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
        const start = chunkIndex * chunkSize
        const end = Math.min(file.size, start + chunkSize)
        const chunkBlob = file.slice(start, end)

        // Update progress display
        const uploadProgress = Math.round((chunkIndex / totalChunks) * 100)
        this.importProgress = uploadProgress
        this.importStatus = `Uploading chunk ${chunkIndex + 1} of ${totalChunks}...`

        const chunkFormData = new FormData()
        chunkFormData.append('chunk', chunkBlob, file.name + '.part' + chunkIndex)
        chunkFormData.append('chunkIndex', chunkIndex.toString())
        chunkFormData.append('totalChunks', totalChunks.toString())
        chunkFormData.append('uploadId', uploadId)
        chunkFormData.append('fileName', file.name)

        try {
          const response = await axios.post('/map-export/upload-chunk', chunkFormData, {
            headers: {
              'Content-Type': 'multipart/form-data'
            },
            timeout: 60000 // 1 minute per chunk
          })
          if (response.data.complete) {
            this.importProgress = 100
            this.importStatus = 'Upload complete, starting import...'
            // Now call the import with uploadId and fileName
            const importResponse = await axios.post('/map-export/import', { uploadId, fileName: file.name })
            return importResponse.data
          }
        } catch (error) {
          console.error(`Chunk ${chunkIndex + 1} failed:`, error)
          throw new Error(`Failed to upload chunk ${chunkIndex + 1}: ${error.response?.data?.message || error.message}`)
        }
        await new Promise(resolve => setTimeout(resolve, 100))
      }
      throw new Error('Upload completed but no final response received')
    },

    async uploadFileNormally(file) {
      const formData = new FormData()
      formData.append('map_file', file)

      const response = await axios.post('/map-export/import', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        timeout: 0, // No timeout - let it run
        onUploadProgress: (progressEvent) => {
          if (progressEvent.lengthComputable) {
            // Upload progress is 0-100%
            const uploadProgress = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            this.importProgress = uploadProgress
            this.importStatus = 'Uploading file...'
          }
        }
      })

      if (response.status === 202 && response.data.accepted && response.data.importId) {
        return {
          accepted: true,
          importId: response.data.importId,
          message: response.data.message
        }
      } else {
        throw new Error(response.data.message || 'Failed to start import')
      }
    }

  }
}
</script>

<style scoped>
.map-editor-container {
  display: flex;
  flex-direction: column;
  height: 100vh;
  font-family: 'Inter', sans-serif;
}



.editor-header {
  background: #f8f9fa;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e9ecef;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-family: 'Inter', sans-serif;
  z-index: 10;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.header-left h1 {
  margin: 0;
  font-size: 1.8rem;
  color: #333;
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  letter-spacing: -0.025em;
}

.page-subtitle {
  font-size: 0.9rem;
  color: #666;
  margin-top: 0.5rem;
  font-family: 'Inter', sans-serif;
  font-weight: 400;
}

.active-map-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: #555;
}

.info-label {
  font-weight: 600;
}

.header-actions {
  display: flex;
  gap: 0.5rem;
}

.header-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  color: #475569;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.header-btn:hover {
  background: #e2e8f0;
  border-color: #cbd5e1;
  color: #334155;
}

.trash-btn {
  color: #dc2626;
}

.trash-btn:hover {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #b91c1c;
}

.btn-secondary {
  background: #e9ecef;
  color: #333;
  border: 1px solid #dee2e6;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.btn-secondary:hover {
  background: #dee2e6;
  border-color: #ced4da;
}

.btn-secondary .btn-icon {
  font-size: 1rem;
}

.editor-main {
  display: flex;
  flex: 1;
  position: relative;
}

.editor-sidebar {
  background: white;
  padding: 1.5rem;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
  overflow-y: auto;
  transition: transform 0.3s ease-in-out;
  position: fixed;
  top: 60px;
  left: 0;
  bottom: 0;
  z-index: 100;
  transform: translateX(0);
  width: 320px;
  border-right: 1px solid #e9ecef;
}

.editor-sidebar.sidebar-collapsed {
  transform: translateX(-100%);
}

.map-workspace.sidebar-collapsed {
  margin-left: 0;
}

.sidebar-section {
  margin-bottom: 1.5rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  flex-shrink: 0;
}

.section-header h3 {
  margin: 0;
  font-size: 1.1rem;
  color: #333;
  font-family: 'Inter', sans-serif;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.section-toggle {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: #666;
  padding: 0.25rem;
  border-radius: 4px;
  transition: background-color 0.2s;
}

.section-toggle:hover {
  background: #f0f0f0;
}

.section-toggle .toggle-icon {
  font-size: 1rem;
}

.section-actions {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.btn-primary {
  background: #2dbb74; /* match navbar light green */
  color: white;
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 4px 14px rgba(45, 187, 116, 0.25);
  font-family: 'Inter', sans-serif;
  letter-spacing: -0.01em;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(45, 187, 116, 0.35);
}
.btn-cancel {
  background: #e74c3c;
  color: #fff;
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-family: 'Inter', sans-serif;
}

.btn-cancel:hover {
  background: #cf4335;
}

/* Panel polish */
.building-editor-panel {
  backdrop-filter: saturate(120%) blur(1px);
}
.editor-panel-header h2 {
  font-family: 'Inter', sans-serif;
  letter-spacing: -0.01em;
}
.editor-form .form-input, .editor-form textarea {
  border-radius: 8px;
  border: 1px solid #e3e7eb;
}
.form-actions {
  display: flex;
  gap: 0.75rem;
}

.btn-primary .btn-icon {
  font-size: 1rem;
}

.btn-secondary {
  background: #f8f9fa;
  color: #333;
  border: 1px solid #dee2e6;
  padding: 0.6rem 1.2rem;
  border-radius: 6px;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-family: 'Inter', sans-serif;
  font-weight: 500;
}

.btn-secondary:hover {
  background: #e9ecef;
  border-color: #ced4da;
}

.btn-secondary.small {
  padding: 0.4rem 0.8rem;
  font-size: 0.8rem;
}

.btn-secondary .btn-icon {
  font-size: 1rem;
}

.btn-icon-small {
  background: none;
  border: none;
  font-size: 0.8rem;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 4px;
  color: #333;
  transition: background-color 0.2s;
}

.btn-icon-small:hover {
  background: #f0f0f0;
}

.btn-icon-small.danger {
  color: #e74c3c;
}

.btn-icon-small.success {
  color: #27ae60;
}

.buildings-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  flex: 1;
  overflow-y: auto;
  padding-right: 0.5rem;
  min-height: 0;
}

/* Use exact same structure as map cards for building cards */
.right-panel .building-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  border: 1px solid #e9ecef;
  flex-shrink: 0;
}

.right-panel .building-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  border-color: #667eea;
}

.right-panel .building-image {
  position: relative;
  height: 120px;
  overflow: hidden;
  border-radius: 8px 8px 0 0;
}

.right-panel .card-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.right-panel .building-info {
  padding: 1px;
}

.right-panel .building-title {
  margin: 0 0 5px 0;
  font-size: 1rem;
  font-family: 'Inter', sans-serif;
  font-weight: 600;
  color: #333;
}

.right-panel .building-location {
  margin: 0;
  font-size: 0.8rem;
  color: #666;
}

.right-panel .building-actions {
  padding: 1rem;
  display: flex;
  gap: 0.5rem;
}

.right-panel .empty-state {
  text-align: center;
  padding: 2rem 1rem;
  color: #666;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.right-panel .empty-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.right-panel .empty-state p {
  margin-bottom: 1rem;
  font-size: 1rem;
}

.right-panel .empty-state .btn-primary {
  padding: 0.6rem 1.2rem;
  font-size: 0.9rem;
}

.building-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
  border: 1px solid #e9ecef;
}

.building-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  border-color: #667eea;
}

.building-image {
  position: relative;
  height: 140px;
  overflow: hidden;
}

.card-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
  cursor: pointer;
}

.card-image:hover {
  transform: scale(1.05);
}

.image-overlay {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(0, 0, 0, 0.6);
  border-radius: 50%;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.has-description {
  color: white;
  font-size: 0.9rem;
}

.building-info {
  padding: 10px;
}

.building-title {
  margin: 0 0 5px 0;
  font-size: 1rem;
  font-family: 'Inter', sans-serif;
  font-weight: 600;
  color: #333;
}

.building-location {
  margin: 0;
  font-size: 0.8rem;
  color: #666;
}

.building-actions {
  padding: 10px;
  display: flex;
  gap: 8px;
  justify-content: space-between;
  border-top: 1px solid #eee;
}

.map-workspace {
  background: white;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow-y: auto;
  flex: 1;
  margin-left: 0; /* No left margin since we removed the left sidebar */
}

.map-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #eee;
}

.map-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.map-title h2 {
  margin: 0;
  font-size: 1.5rem;
  color: #333;
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  letter-spacing: -0.025em;
}

.map-status {
  background: #27ae60;
  color: white;
  padding: 0.3rem 0.8rem;
  border-radius: 5px;
  font-size: 0.8rem;
  font-weight: 600;
}

.map-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.control-btn {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 0.4rem 0.7rem;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
  min-width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #333;
}

.control-btn:hover {
  background: #e9ecef;
  border-color: #adb5bd;
}

.control-btn.active {
  background: #007bff;
  color: white;
  border-color: #007bff;
}

.control-btn.active:hover {
  background: #0056b3;
  border-color: #0056b3;
}

.control-btn.danger {
  background: #dc3545;
  color: white;
  border-color: #dc3545;
}

.control-btn.danger:hover {
  background: #c82333;
  border-color: #c82333;
}

.push-btn {
  position: relative;
  background: #10b981;
  color: white;
  border-color: #10b981;
}

.push-btn:hover {
  background: #059669;
  border-color: #059669;
}

.push-btn.has-updates {
  background: #f59e0b;
  border-color: #f59e0b;
  animation: pulse 2s infinite;
}

.push-btn.has-updates:hover {
  background: #d97706;
  border-color: #d97706;
}

.update-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: #dc2626;
  color: white;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  font-size: 10px;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}

.zoom-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 0.4rem 0.7rem;
  font-size: 0.9rem;
}

.zoom-btn {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: #333;
  padding: 0.25rem;
  border-radius: 4px;
  transition: background-color 0.2s;
}

.zoom-btn:hover {
  background: #e9ecef;
}

.zoom-level {
  font-size: 0.9rem;
  color: #666;
  min-width: 50px;
  text-align: center;
}

.map-dimensions {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 0.4rem 0.7rem;
  margin-left: 0.5rem;
}

.dimension-text {
  font-size: 0.8rem;
  font-weight: 600;
  color: #2c3e50;
  font-family: 'Courier New', monospace;
}

.map-container {
  position: relative;
  width: 100%;
  height: 80vh; /* Optimal height for perfect fit */
  overflow: hidden; /* Hide scroll bars initially - only show when zoomed in */
  border: 2px solid #007bff; /* Thicker blue border */
  border-radius: 8px; /* More rounded corners */
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); /* Deeper shadow */
  cursor: default;
  scroll-behavior: smooth;
  background: #f8f9fa; /* Light background */
}

.map-canvas {
  position: relative;
  width: 100%;
  height: 100%;
  cursor: default;
  overscroll-behavior: none; /* Prevent scroll chaining and enforce map boundaries */
}

.map-canvas.drag-mode {
  cursor: grab;
}

.map-canvas.drag-mode:active {
  cursor: grabbing;
}

.map-canvas.zoom-mode {
  cursor: crosshair;
}

.map-wrapper {
  position: relative;
  display: block;
  width: 100%;
  height: 100%;
  transform-origin: 0 0;
}

.map-image {
  display: block;
  max-width: none;
  max-height: none;
  /* Exact dimensions set via inline styles */
}

.building-marker {
  position: absolute;
  transform: translate(-50%, -50%);
  z-index: 10;
  cursor: pointer;
}

.marker-container {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  transition: transform 0.12s ease-out;
  transform-origin: center;
}

.marker-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.marker-dot {
  width: 12px;
  height: 12px;
  background-color: #e74c3c;
  border: 2px solid white;
  border-radius: 50%;
  box-shadow: 0 0 4px rgba(0, 0, 0, 0.5);
}

/* Pop-up animation when hovering the marker */
.building-marker:hover .marker-container {
  transform: scale(1.12);
}

/* Slight pop while pressing */
.building-marker:active .marker-container {
  transform: scale(1.04);
}

.marker-label {
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0, 0, 0, 0.8);
  color: white;
  font-size: 0.75rem;
  padding: 3px 6px;
  border-radius: 4px;
  white-space: nowrap;
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
  font-family: 'Inter', sans-serif;
  font-weight: 500;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.2);
  z-index: 100;
}

.building-marker:hover .marker-label {
  opacity: 1;
}

/* Selected building pulsing (slower) */
.building-marker.selected .marker-container {
  animation: pulse 2.2s infinite;
}

/* Keep the create/edit preview without pulse unless selected */
.new-building-marker .marker-container {
  animation: none;
}

.new-marker-image {
  border-color: #27ae60; /* Green border for new position */
}

.new-marker-dot {
  background-color: #27ae60; /* Green color for new position */
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.12); }
  100% { transform: scale(1); }
}

.coord-preview {
  position: absolute;
  background: rgba(0, 0, 0, 0.8);
  color: white;
  font-size: 0.65rem;
  padding: 2px 4px;
  border-radius: 3px;
  pointer-events: none;
  z-index: 1000;
  font-family: 'Courier New', monospace;
  font-weight: 500;
  white-space: nowrap;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.2);
}



.no-active-map {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 300px;
  background: #f8f9fa;
  border-radius: 4px;
  color: #666;
  text-align: center;
  padding: 2rem;
}

.right-panel {
  background: white;
  padding: 0; /* Remove padding to accommodate toggle buttons */
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  width: 380px; /* Increased width for better card display */
  border-left: 1px solid #e9ecef;
  display: flex;
  flex-direction: column;
  height: calc(100vh - 10px); /* Increased height - you can change this value */
  overflow: hidden;
}

/* Panel Toggle Buttons */
.panel-toggle-buttons {
  display: flex;
  background: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  padding: 0.5rem;
  gap: 0.5rem;
}

.panel-toggle-btn {
  flex: 1;
  background: white;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  padding: 0.75rem 1rem;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: #666;
}

.panel-toggle-btn:hover {
  background: #f8f9fa;
  border-color: #adb5bd;
}

.panel-toggle-btn.active {
  background: linear-gradient(135deg, #38e36e 0%, #0ccc72 100%);
  color: white;
  border-color: #038b27;
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.panel-toggle-btn .btn-icon {
  font-size: 1rem;
}

.panel-section {
  margin-bottom: 1.5rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 1.5rem;
}

.maps-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  flex: 1;
  overflow-y: auto;
  padding-right: 0.5rem;
  min-height: 0;
}

.map-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  border: 1px solid #e9ecef;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.map-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  border-color: #667eea;
}

.map-image-container {
  min-height: 142px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.map-info {
  padding: 1rem;
  flex: 1 1 auto;
}

.map-actions {
  padding: 1rem;
  display: flex;
  gap: 0.5rem;
  margin-top: auto;
}

.map-placeholder {
  width: 100%;
  height: 100%;
  background: #f1f5f9;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #64748b;
}

.placeholder-icon {
  font-size: 24px;
  margin-bottom: 8px;
}

.placeholder-text {
  font-size: 12px;
  font-weight: 500;
}

.map-status-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.map-status-badge.active {
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: #ffffff;
  border-color: #bbf7d0;
}

.map-name {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 4px 0;
}

.map-dimensions {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

.map-actions button,
.building-actions button {
  flex: 1;
  padding: 0.5rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  color: white;
}

.edit-button {
  background: #3498db;
  transition: all 0.3s;
}

.edit-button.enhanced {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  font-weight: 600;
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.edit-button.enhanced:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.edit-button.quick {
  background: #95a5a6;
}

.delete-button {
  background: #e74c3c;
}

.activate-button {
  background: #27ae60;
}

/* Building Editor Slide Panel */
.building-editor-panel {
  position: fixed;
  top: 0;
  right: 0;
  width: 450px; /* Increased from 300px to 450px for better input experience */
  max-width: 90vw; /* Ensure it doesn't exceed 90% of viewport width on smaller screens */
  height: 92vh; /* Taller panel for more content */
  background: white;
  box-shadow: -2px 0 8px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  display: flex;
  flex-direction: column;
  animation: slideInFromRight 0.3s ease-out;
}

@keyframes slideInFromRight {
  from {
    transform: translateX(100%);
  }
  to {
    transform: translateX(0);
  }
}

.editor-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #e9ecef;
  background: #f8f9fa;
  flex-shrink: 0;
}

.editor-panel-header h2 {
  margin: 0;
  font-size: 1.2rem;
  color: #333;
  font-family: 'Inter', sans-serif;
  font-weight: 600;
}

.panel-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #666;
  padding: 0.25rem;
  border-radius: 4px;
  transition: background-color 0.2s;
}

.panel-close:hover {
  background: #e9ecef;
}

.editor-panel-content {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
}

.editor-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
  color: #555;
  font-family: 'Inter', sans-serif;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 1rem;
  color: #333;
  font-family: 'Inter', sans-serif;
  font-weight: 400;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

.form-actions {
  display: flex;
  gap: 1rem;
  margin-top: 1.5rem;
}

.form-actions button {
  flex: 1;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  color: white;
  font-size: 1rem;
  font-weight: 600;
  transition: all 0.3s;
}

.save-button {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.save-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.cancel-button {
  background: #95a5a6;
}

.cancel-button:hover {
  background: #7f8c8d;
}

.image-preview {
  margin-bottom: 1rem;
}

.image-preview img {
  width: 100%;
  height: auto;
  border-radius: 4px;
}

/* NUCLEAR OVERRIDE - Prevent building images from being affected by image-preview styles */
.building-editor-panel .image-preview img:not(.modal-preview-img) {
  width: 80px !important;
  height: 80px !important;
  max-width: 80px !important;
  max-height: 80px !important;
  min-width: 80px !important;
  min-height: 80px !important;
  object-fit: cover !important;
}

/* Ensure building preview images are not affected by general image-preview styles */
.building-editor-panel .image-preview img {
  width: 120px !important;
  height: 120px !important;
  max-width: 120px !important;
  max-height: 120px !important;
  min-width: 120px !important;
  min-height: 120px !important;
}

/* CRITICAL: Override the .image-preview img rule specifically for building images */
.building-editor-panel .building-image-section .building-image-container img {
  width: 120px !important;
  height: 120px !important;
  max-width: 120px !important;
  max-height: 120px !important;
  min-width: 120px !important;
  min-height: 120px !important;
  object-fit: cover !important;
}

/* FINAL ABSOLUTE OVERRIDE - This should override everything */
.building-editor-panel .building-image-container img[alt="Building Image"] {
  width: 120px !important;
  height: 120px !important;
  max-width: 120px !important;
  max-height: 120px !important;
  min-width: 120px !important;
  min-height: 120px !important;
  object-fit: cover !important;
  flex-shrink: 0 !important;
}

.image-previews {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1rem;
}

.field-description {
  font-size: 0.8rem;
  color: #666;
  margin-top: 0.25rem;
  margin-bottom: 0.5rem;
  font-style: italic;
}


.building-modal {
  max-width: 600px; /* Wider to accommodate the map picker */
}

.helper-text {
  font-size: 0.8rem;
  color: #666;
  margin-top: 0.5rem;
}

.test-button {
  margin-top: 0.5rem;
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 0.3rem 0.6rem;
  font-size: 0.8rem;
  cursor: pointer;
}

.test-button:hover {
  background-color: #2980b9;
}

.image-size-controls {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #eee;
}

.size-inputs {
  display: flex;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.size-inputs > div {
  flex: 1;
}

/* Slider Styles */
.size-slider-container {
  margin-bottom: 1rem;
}

.slider-wrapper {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.slider-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.size-input {
  width: 80px;
  padding: 0.25rem 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9rem;
  text-align: center;
  background: #fff;
}

.size-input:focus {
  outline: none;
  border-color: #4CAF50;
  box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
}

.size-slider {
  width: 100%;
  height: 6px;
  border-radius: 3px;
  background: #ddd;
  outline: none;
  -webkit-appearance: none;
  appearance: none;
  cursor: pointer;
}

.size-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #4CAF50;
  cursor: pointer;
  border: 2px solid #fff;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.size-slider::-moz-range-thumb {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #4CAF50;
  cursor: pointer;
  border: 2px solid #fff;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.slider-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 0.25rem;
}

.slider-label {
  font-size: 0.75rem;
  color: #666;
  font-weight: 500;
}

.size-inputs label {
  display: block;
  font-size: 0.8rem;
  color: #666;
  margin-bottom: 0.25rem;
}

.size-inputs input {

/* Building Info Display Styles */
.building-info-display {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.building-image-section {
  text-align: center;
}

.building-image-section h3 {
  margin-bottom: 0.5rem;
  color: #333;
  font-size: 0.8rem;
  font-weight: 600;
}

.building-editor-panel .no-image-placeholder {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  height: 50px !important;
  width: 50px !important;
  background: #f8f9fa !important;
  border: 2px dashed #dee2e6 !important;
  border-radius: 4px !important;
  color: #6c757d !important;
  max-width: 50px !important;
  max-height: 50px !important;
}

.placeholder-icon {
  font-size: 1.2rem;
  margin-bottom: 0.2rem;
}

.no-image-placeholder p {
  margin: 0;
  font-size: 0.6rem;
  color: #6c757d;
}

/* Override any conflicting image styles for building preview - CRITICAL OVERRIDE */
.building-editor-panel .building-preview-image,
.building-editor-panel .building-image-container img,
.building-editor-panel .building-image-section img,
.building-editor-panel .building-info-display img,
.building-editor-panel img[alt="Building Image"] {
  width: 120px !important;
  height: 120px !important;
  border-radius: 4px !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
  object-fit: cover !important;
  display: block !important;
  margin: 0 auto !important;
  max-width: 120px !important;
  max-height: 120px !important;
  min-width: 120px !important;
  min-height: 120px !important;
}

.building-editor-panel .building-image-container {
  display: flex !important;
  justify-content: center !important;
  align-items: center !important;
  width: 120px !important;
  height: 120px !important;
  padding: 0.25rem !important;
  background: #f8f9fa !important;
  border-radius: 6px !important;
  border: 1px solid #e9ecef !important;
  margin: 0 auto !important;
  max-width: 120px !important;
  max-height: 120px !important;
  overflow: hidden !important;
  flex-shrink: 0 !important;
}

.building-details-section {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.detail-group {
  padding: 0.75rem;
  background: #f8f9fa;
  border-radius: 8px;
  border-left: 4px solid #007bff;
}

.detail-group h3 {
  margin: 0 0 0.5rem 0;
  color: #333;
  font-size: 0.9rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.building-description,
.building-services,
.building-coords,
.building-size {
  margin: 0;
  color: #555;
  font-size: 0.9rem;
  line-height: 1.4;
}

.no-description,
.no-services {
  margin: 0;
  color: #999;
  font-size: 0.9rem;
  font-style: italic;
}

.building-actions-section {
  margin-top: 0.75rem;
}

.building-actions-section h3 {
  margin-bottom: 1rem;
  color: #333;
  font-size: 1rem;
  font-weight: 600;
}

.action-buttons {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.action-buttons button {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border: none;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: left;
}

.action-buttons .btn-primary {
  background: #007bff;
  color: white;
}

.action-buttons .btn-primary:hover {
  background: #0056b3;
  transform: translateY(-1px);
}

.action-buttons .btn-secondary {
  background: #6c757d;
  color: white;
}

.action-buttons .btn-secondary:hover {
  background: #545b62;
  transform: translateY(-1px);
}

.action-buttons .btn-danger {
  background: #dc3545;
  color: white;
}

.action-buttons .btn-danger:hover {
  background: #c82333;
  transform: translateY(-1px);
}

/* Employee cards in building info panel */
.employees-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}
.employee-card {
  display: grid;
  grid-template-columns: 40px 1fr;
  gap: 10px;
  padding: 8px;
  border: 1px solid #eef1f4;
  border-radius: 8px;
  background: #fafbfc;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.emp-avatar {
  width: 40px;
  height: 40px;
}
/* Strong override to prevent generic image rules from stretching employee images */
.building-editor-panel .employees-grid .emp-avatar img {
  width: 40px !important;
  height: 40px !important;
  min-width: 40px !important;
  min-height: 40px !important;
  max-width: 40px !important;
  max-height: 40px !important;
  border-radius: 50% !important;
  object-fit: cover !important;
  display: block;
}
.emp-name {
  font-weight: 600;
}
.emp-meta {
  color: #666;
  font-size: 0.85rem;
}
.emp-email {
  color: #444;
  font-size: 0.85rem;
}

.btn-icon {
  font-size: 1rem;
}
  width: 100%;
  padding: 0.25rem;
  border: 1px solid #ddd;
  border-radius: 3px;
  font-size: 0.8rem;
}

.size-presets {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.size-preset-btn {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 3px;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}

.size-preset-btn:hover {
  background: #e9ecef;
  border-color: #adb5bd;
}

.size-preset-btn:active {
  background: #dee2e6;
}

.diagnostic-tools {
  margin-top: 1rem;
  padding: 0.5rem 0;
}

.diagnostic-button {
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 0.5rem 1rem;
  cursor: pointer;
}

.diagnostics-note {
  font-size: 0.8rem;
  color: #666;
  margin-top: 0.5rem;
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: #666;
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.empty-state p {
  margin-bottom: 1.5rem;
  font-size: 1.1rem;
}

.empty-state .btn-primary {
  padding: 0.75rem 1.5rem;
  font-size: 1rem;
}

/* Custom Map Container Styling */
.custom-map-style {
  /* Add your custom styles here */
  transition: all 0.3s ease;
}

.custom-map-style:hover {
  box-shadow: 0 6px 16px rgba(0, 123, 255, 0.2);
  transform: translateY(-1px);
}

/* Employee Management Styles */
.employee-section {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 16px;
  background-color: #f9f9f9;
}

.employee-section h3 {
  margin: 0 0 12px 0;
  color: #333;
  font-size: 1.1em;
}

.employees-list {
  margin-bottom: 16px;
}

.employee-item {
  background: white;
  border: 1px solid #ddd;
  border-radius: 6px;
  padding: 12px;
  margin-bottom: 8px;
}

.employee-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.employee-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid #ddd;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f0f0f0;
  flex-shrink: 0;
}

.employee-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-placeholder {
  font-size: 1.5em;
  color: #999;
}

.employee-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.employee-input {
  font-size: 0.9em;
  padding: 6px 8px;
  margin: 0;
}

.employee-actions {
  display: flex;
  gap: 4px;
  align-items: center;
  flex-shrink: 0;
}

.hidden-file-input {
  display: none;
}

.btn-secondary.small,
.btn-danger.small {
  padding: 4px 8px;
  font-size: 0.8em;
  min-width: auto;
  line-height: 1;
}

.btn-danger.small {
  background-color: #dc3545;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-danger.small:hover {
  background-color: #c82333;
}

/* Optimized Form Layout for Wider Modal */
.coordinate-inputs,
.size-inputs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.coordinate-inputs > div,
.size-inputs > div {
  display: flex;
  flex-direction: column;
}

.coordinate-inputs label,
.size-inputs label {
  margin-bottom: 4px;
  font-size: 0.85em;
  font-weight: 500;
}

.coordinate-hint {
  grid-column: 1 / -1;
  margin-top: 8px;
  text-align: center;
}

.hint-text {
  color: #6b7280;
  font-size: 0.8em;
  font-style: italic;
}

/* Improved spacing for employee section in wider modal */
.employee-info {
  gap: 16px; /* Increased from 12px */
}

.employee-details {
  gap: 6px; /* Slightly increased from 4px */
}

/* Better button layout for size presets */
.size-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.size-presets button {
  flex: 1;
  min-width: calc(50% - 3px);
}

/* Responsive adjustments for smaller screens */
@media (max-width: 768px) {
  .building-editor-panel {
    width: 100vw;
    max-width: 100vw;
  }
  
  .coordinate-inputs,
  .size-inputs {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  
  .size-presets button {
    min-width: calc(33.33% - 4px);
  }
}

/* Modern Right Panel Styles */
.right-panel {
  background: #f8fafc;
  border-left: 1px solid #e2e8f0;
  box-shadow: -2px 0 10px rgba(0, 0, 0, 0.05);
}

.panel-toggle-buttons {
  display: flex;
  background: white;
  border-bottom: 1px solid #e2e8f0;
  padding: 0;
}

.panel-toggle-btn {
  flex: 1;
  padding: 16px 20px;
  border: none;
  background: transparent;
  color: #64748b;
  font-weight: 500;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-bottom: 2px solid transparent;
}

.panel-toggle-btn:hover {
  background: #f1f5f9;
  color: #334155;
}

.panel-toggle-btn.active {
  color: #2dbb74;
  border-bottom-color: #2dbb74;
  background: #f0fdf4;
}

.btn-icon {
  font-size: 16px;
}

.btn-text {
  font-weight: 500;
}

.panel-section {
  padding: 24px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.add-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 4px rgba(45, 187, 116, 0.2);
}

.add-btn:hover {
  background: linear-gradient(135deg, #25a065 0%, #0bb362 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(45, 187, 116, 0.3);
}

.add-btn.primary {
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
}

/* Map Cards */
.maps-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: 1fr;
}

.map-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #e2e8f0;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  min-height: 300px;
  display: flex;
  flex-direction: column;
  position: relative;
  justify-content: space-between;
}

.map-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border-color: #cbd5e1;
}

.map-image-container {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 12px;
  aspect-ratio: 4/3;
  min-height: 150px;
}

.map-preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.map-placeholder {
  width: 100%;
  height: 100%;
  background: #f1f5f9;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #64748b;
}

.placeholder-icon {
  font-size: 24px;
  margin-bottom: 8px;
}

.placeholder-text {
  font-size: 12px;
  font-weight: 500;
}

.map-status-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.map-status-badge.active {
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: #ffffff;
  border-color: #bbf7d0;
}

.map-info {
  margin-bottom: 12px;
  flex: 1;
}

.map-name {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 4px 0;
}

.map-dimensions {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

.map-actions {
  display: flex !important;
  gap: 8px;
  position: relative;
  z-index: 1;
  margin-top: auto;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
  opacity: 1 !important;
  visibility: visible !important;
}

.action-btn {
  padding: 8px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 14px;
  display: flex !important;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 32px;
  z-index: 9999 !important;
  position: relative;
  background: white !important;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
  opacity: 1 !important;
  visibility: visible !important;
}

/* Ensure map cards don't clip the action buttons */
.map-card { overflow: visible !important; position: relative; }
.map-card .map-preview { overflow: visible !important; }

.action-btn.edit {
  background: #fef3c7 !important;
  color: #92400e !important;
}

.action-btn.edit:hover {
  background: #fde68a;
}

.action-btn.delete {
  background: #fee2e2 !important;
  color: #991b1b !important;
  cursor: pointer !important;
  pointer-events: auto !important;
  z-index: 20 !important;
  position: relative !important;
}

.action-btn.delete:hover {
  background: #fecaca;
}

.action-btn.activate {
  background: #dcfce7 !important;
  color: #166534 !important;
}

.action-btn.activate:hover {
  background: #bbf7d0;
}

/* Building Cards */
.buildings-list {
  display: grid;
  gap: 16px;
  grid-template-columns: 1fr;
}

.building-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #e2e8f0;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  gap: 20px;
  min-height: 250px;
  position: relative;
  justify-content: space-between;
}

.building-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border-color: #cbd5e1;
}

.building-image-container {
  position: relative;
  width: 120px;
  height: 120px;
  border-radius: 8px;
  overflow: hidden;
  flex-shrink: 0;
}

.building-preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  cursor: pointer;
  transition: transform 0.2s ease;
}

.building-preview-image:hover {
  transform: scale(1.05);
}

.image-overlay {
  position: absolute;
  top: 4px;
  right: 4px;
}

.has-description {
  background: rgba(45, 187, 116, 0.9);
  color: white;
  padding: 2px 6px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
}

.building-info {
  flex: 1;
  min-width: 0;
  margin-right: 20px;
}

.building-title {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 4px 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.building-location {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

.building-actions {
  display: flex !important;
  gap: 8px;
  flex-shrink: 0;
  min-width: 80px;
  justify-content: flex-end;
  z-index: 10;
  position: relative;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  background: white;
  border-radius: 12px;
  border: 2px dashed #e2e8f0;
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.empty-text {
  font-size: 16px;
  color: #64748b;
  margin: 0 0 20px 0;
}

/* Modern Editor Panel Styles */
.modern-editor-panel {
  position: fixed;
  top: 0;
  right: -450px;
  width: 450px;
  height: 100vh;
  background: white;
  box-shadow: -5px 0 20px rgba(0, 0, 0, 0.15);
  transition: right 0.3s ease-in-out;
  z-index: 1000;
  overflow-y: auto;
  border-left: 3px solid #2dbb74;
}

.modern-editor-panel.show {
  right: 0;
}

.modern-editor-header {
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: white;
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 8px rgba(45, 187, 116, 0.3);
}

.modern-editor-title {
  font-size: 20px;
  font-weight: 600;
  margin: 0;
}

.modern-close-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  font-size: 24px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.modern-close-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.1);
}

.modern-editor-content {
  padding: 24px;
}

.modern-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.modern-form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.modern-label {
  font-weight: 600;
  color: #1e293b;
  font-size: 14px;
}

.modern-input,
.modern-textarea {
  padding: 12px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.2s ease;
  background: white;
}

.modern-input:focus,
.modern-textarea:focus {
  outline: none;
  border-color: #2dbb74;
  box-shadow: 0 0 0 3px rgba(45, 187, 116, 0.1);
}

.modern-textarea {
  min-height: 80px;
  resize: vertical;
}

.modern-file-input {
  padding: 12px 16px;
  border: 2px dashed #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modern-file-input:hover {
  border-color: #2dbb74;
  background: #f0fdf4;
}

.modern-form-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.modern-btn-save {
  flex: 1;
  padding: 14px 24px;
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 4px rgba(45, 187, 116, 0.2);
}

.modern-btn-save:hover {
  background: linear-gradient(135deg, #25a065 0%, #0bb362 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(45, 187, 116, 0.3);
}

.modern-btn-cancel {
  flex: 1;
  padding: 14px 24px;
  background: #dc3545;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modern-btn-cancel:hover {
  background: #c82333;
  transform: translateY(-1px);
}

.modern-btn-rooms {
  flex: 1;
  padding: 14px 24px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
}

.modern-btn-rooms:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
  background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
}

.modern-btn-rooms .btn-icon {
  font-size: 16px;
}

.modern-btn-rooms-inline {
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: white;
  border: none;
  padding: 12px 20px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  font-size: 14px;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
  width: 100%;
  justify-content: center;
}

.modern-btn-rooms-inline:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
  background: linear-gradient(135deg, #26a666 0%, #0ab963 100%);
}

.modern-btn-rooms-inline .btn-icon {
  font-size: 16px;
}

.modern-btn-rooms-inline .btn-text {
  font-weight: 500;
}

.modern-btn-secondary {
  padding: 8px 16px;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modern-btn-secondary:hover {
  background: #e2e8f0;
  color: #334155;
}

.modern-btn-danger {
  padding: 8px 16px;
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modern-btn-danger:hover {
  background: #fecaca;
  color: #7f1d1d;
}

.modern-preset-btn {
  padding: 8px 12px;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  flex: 1;
}

.modern-preset-btn:hover {
  background: #e2e8f0;
  color: #334155;
}


/* Modern Employee Section */
.section-subtitle {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 16px 0;
  padding-bottom: 8px;
  border-bottom: 2px solid #e2e8f0;
}

/* Fix stray selector */
.modern-employees-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 16px;
}

.modern-employee-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  height: 90px;
  padding: 16px;
}

.modern-employee-info {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  position: relative;
}

.modern-employee-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  border: 2px solid #e2e8f0;
}

.modern-employee-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.modern-avatar-placeholder {
  width: 100%;
  height: 100%;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: #64748b;
}

.modern-employee-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.modern-employee-actions {
  display: flex;
  flex-direction: row;
  gap: 8px;
  flex-shrink: 0;
  position: relative;
  right: 285px;
  top: 60px;
}

.camera-btn {
  order: 1;
  margin-bottom: 4px;
}

.remove-btn {
  order: 2;
}

.add-employee-btn {
  width: 100%;
  padding: 12px;
  background: #f0fdf4;
  color: #166534;
  border: 2px dashed #bbf7d0;
  border-radius: 8px;
  font-weight: 500;
  transition: all 0.2s ease;
}

.add-employee-btn:hover {
  background: #dcfce7;
  border-color: #86efac;
  color: #15803d;
}

/* Helper Text */
.helper-text {
  font-size: 12px;
  color: #64748b;
  margin-top: 4px;
}

.field-description {
  font-size: 12px;
  color: #64748b;
  margin: 4px 0 0 0;
  font-style: italic;
}

.instruction-text {
  font-size: 13px;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}

.instruction-text.small {
  font-size: 11px;
  color: #94a3b8;
  margin-top: 4px;
}

/* Image Previews */
.image-previews {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.image-preview {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 16px;
}

.image-preview h4 {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 12px 0;
}

.image-preview img {
  max-width: 100%;
  height: auto;
  border-radius: 4px;
}

/* Debug Info */
.debug-info {
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 8px;
  padding: 16px;
  text-align: center;
  color: #92400e;
}

.debug-info p {
  margin: 0 0 12px 0;
  font-weight: 500;
}

.debug-btn {
  background: #f59e0b;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s ease;
}

.debug-btn:hover {
  background: #d97706;
}

/* Services Tags Styles */
.services-input-container {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.services-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 32px;
}

.service-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: white;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
  box-shadow: 0 2px 4px rgba(45, 187, 116, 0.2);
  transition: all 0.2s ease;
}

.service-tag:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(45, 187, 116, 0.3);
}

.service-text {
  white-space: nowrap;
}

.remove-service-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: bold;
  transition: all 0.2s ease;
  padding: 0;
  line-height: 1;
}

.remove-service-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.1);
}

.services-input {
  margin-top: 8px;
}

/* Building Info Modal Styles */
.building-info-panel {
  border-left: 3px solid #2dbb74;
  position: fixed;
  top: 0;
  right: -450px;
  width: 450px;
  height: 100vh;
  background: white;
  box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: right 0.3s ease-in-out;
}

.building-info-panel.show {
  right: 0;
}

.building-info-content {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  max-height: calc(100vh - 140px); /* Account for header and footer */
}

.building-image-section {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
  padding: 16px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.building-image-container {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid #e2e8f0;
  flex-shrink: 0;
}

.building-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.image-placeholder {
  width: 100%;
  height: 100%;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  color: #64748b;
}

.image-info {
  flex: 1;
}

.info-label {
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 4px 0;
  font-size: 14px;
}

.info-text {
  color: #64748b;
  margin: 0;
  font-size: 13px;
}

.details-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.detail-item {
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 16px;
}

.detail-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.detail-label {
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 8px 0;
  font-size: 14px;
}

.detail-text {
  color: #475569;
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
}

.no-employees {
  font-style: italic;
  color: #94a3b8;
}

.employees-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.employee-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.employee-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  overflow: hidden;
  border: 1px solid white;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
  flex-shrink: 0;
}

.avatar-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-placeholder {
  width: 100%;
  height: 100%;
  background: #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: #6b7280;
}

.employee-info {
  flex: 1;
  min-width: 0;
}

.employee-name {
  font-weight: 500;
  color: #111827;
  margin: 0 0 2px 0;
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.employee-position {
  color: #6b7280;
  margin: 0 0 2px 0;
  font-size: 11px;
}

.employee-email {
  color: #6b7280;
  margin: 0;
  font-size: 11px;
}

/* Service Tags Display Styles */
.services-tags-display {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 8px 0;
}

.service-tag-display {
  display: inline-block;
  padding: 6px 12px;
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%);
  color: white;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
  box-shadow: 0 2px 4px rgba(45, 187, 116, 0.2);
  transition: all 0.2s ease;
}

.service-tag-display:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(45, 187, 116, 0.3);
}

/* Modal Image Container */
.modal-image-container {
  width: 100%;
  max-width: 400px;
  height: 200px;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 8px 0;
}

.modal-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.modal-image-placeholder {
  width: 100%;
  height: 100%;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 48px;
  color: #64748b;
}

/* Modal Footer */
.modal-footer {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  gap: 12px;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 14px 24px;
  border: none;
  border-radius: 6px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
  flex: 1;
  justify-content: center;
  color: white;
  text-decoration: none;
}

.edit-btn {
  background: linear-gradient(135deg, #2dbb74 0%, #0ccc72 100%) !important;
  color: white !important;
  box-shadow: 0 2px 4px rgba(45, 187, 116, 0.2);
}

.edit-btn:hover {
  background: linear-gradient(135deg, #25a065 0%, #0bb362 100%) !important;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(45, 187, 116, 0.3);
}

.delete-btn {
  background: #dc3545 !important;
  color: white !important;
  box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
}

.delete-btn:hover {
  background: #c82333 !important;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
  .modern-editor-panel {
    width: 100vw;
    right: -100vw;
  }
  
  
  .modern-form-actions {
    flex-direction: column;
  }
}

.countdown-display {
  font-size: 30px;
  font-weight: bold;
  color: #dc2626;
  width: 40px;
  height: 40px;
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

/* Delete Modal Styles with Modern Inter Font */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

.delete-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.delete-modal-content {
  background: white;
  border-radius: 16px;
  padding: 32px;
  max-width: 400px;
  width: 90%;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
  position: relative;
  animation: modalSlideIn 0.3s ease-out;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
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

.delete-modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #6b7280;
  padding: 8px;
  line-height: 1;
  border-radius: 6px;
  transition: all 0.2s ease;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.delete-modal-close:hover {
  background-color: #f3f4f6;
  color: #374151;
}

.delete-modal-header {
  text-align: center;
  margin-bottom: 24px;
}

.delete-modal-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.delete-modal-title {
  margin: 0;
  font-size: 20px;
  color: #1f2937;
  font-weight: 600;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.delete-modal-message {
  color: #6b7280;
  text-align: center;
  margin-bottom: 32px;
  line-height: 1.6;
  font-size: 15px;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.delete-modal-message strong {
  color: #1f2937;
  font-weight: 600;
}

.delete-modal-actions {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60px;
}

.delete-modal-buttons {
  display: flex;
  gap: 12px;
  width: 100%;
}

.delete-modal-cancel {
  flex: 1;
  padding: 14px 20px;
  background: #6b7280;
  color: white;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  font-size: 15px;
  transition: all 0.2s ease;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.delete-modal-cancel:hover {
  background: #4b5563;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(107, 114, 128, 0.3);
}

.delete-modal-confirm {
  flex: 1;
  padding: 14px 20px;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  font-size: 15px;
  transition: all 0.2s ease;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.delete-modal-confirm:hover {
  background: #b91c1c;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(220, 38, 38, 0.3);
}

/* Import Styles */
.import-section {
  margin: 20px 0;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.subsection-title {
  margin: 0 0 15px 0;
  font-size: 16px;
  font-weight: 600;
  color: #333;
}

.import-actions {
  display: flex;
  gap: 12px;
  margin-bottom: 15px;
}

.import-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
  color: white;
}

.import-btn:hover {
  background: linear-gradient(135deg, #0056b3 0%, #520dc2 100%);
  transform: translateY(-1px);
}

.import-help {
  margin: 0;
  font-size: 13px;
  color: #666;
  line-height: 1.4;
}

/* Export button in map actions */
.action-btn.export {
  background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
  color: white;
  border: none;
  border-radius: 6px;
  padding: 8px 12px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.action-btn.export:hover:not(:disabled) {
  background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
  transform: translateY(-1px);
}

.action-btn.export:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}


/* Responsive adjustments for import */
@media (max-width: 768px) {
  .import-actions {
    flex-direction: column;
  }

  .import-modal {
    width: 95%;
    margin: 10px;
  }
}
</style> 