<template>
  <div class="map-3d-editor">
    <!-- Top Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <h3>3D Map Editor</h3>
      </div>
      <div class="toolbar-center">
        <button 
          @click="setTransformMode('translate')" 
          :class="['toolbar-btn', 'transform-btn', { active: transformMode === 'translate' }]"
          :disabled="!selectedObject"
          title="Move (G)"
        >
          ↔️
        </button>
        <button 
          @click="setTransformMode('rotate')" 
          :class="['toolbar-btn', 'transform-btn', { active: transformMode === 'rotate' }]"
          :disabled="!selectedObject"
          title="Rotate (R)"
        >
          🔄
        </button>
        <button 
          @click="setTransformMode('scale')" 
          :class="['toolbar-btn', 'transform-btn', { active: transformMode === 'scale' }]"
          :disabled="!selectedObject"
          title="Scale (S)"
        >
          📏
        </button>
        <button 
          @click="setTransformMode('deform')" 
          :class="['toolbar-btn', 'transform-btn', { active: transformMode === 'deform' }]"
          :disabled="!selectedObject"
          title="Regional Scale (D)"
        >
          🎯
        </button>
      </div>
      <div class="toolbar-right">
        <div style="display:flex; gap:6px; align-items:center; background:#22313f; padding:4px 6px; border-radius:4px;">
          <input type="file" accept=".glb" @change="onGlbSelected" style="color:white; font-size:12px;" />
          <input type="number" v-model.number="cameraAngles.azimuthDeg" @input="applyCameraAngles" step="1" style="width:60px;" title="Azimuth°" />
          <input type="number" v-model.number="cameraAngles.elevationDeg" @input="applyCameraAngles" step="1" style="width:60px;" title="Elevation°" />
          <input type="number" v-model.number="cameraAngles.distance" @input="applyCameraAngles" step="0.1" min="1" style="width:60px;" title="Distance" />
          <button class="toolbar-btn" @click="setIsometric" title="Isometric">Iso</button>
          <button class="toolbar-btn" @click="setTopView" title="Top">Top</button>
        </div>
        <button @click="resetView" class="toolbar-btn">🔄</button>
        <button @click="exportPNG" class="toolbar-btn export">Export Map</button>
      </div>
    </div>

    <div class="editor-container">
      <!-- Left Sidebar - Scenery Objects -->
      <div class="sidebar left-sidebar">
        <div class="sidebar-header">
          <h4>Scenery Objects</h4>
          <select v-model="activeCategory" class="category-select">
            <option value="terrain">Terrain</option>
            <option value="vegetation">Vegetation</option>
            <option value="structures">Structures</option>
            <option value="roads">Roads & Paths</option>
            <option value="decorative">Decorative</option>
          </select>
        </div>
        
        <div class="shapes-grid">
          <div 
            v-for="shape in getCurrentShapes()" 
            :key="shape.id"
            @click="selectShape(shape)"
            :class="['shape-item', { selected: selectedShape?.id === shape.id }]"
            :title="shape.name"
            draggable="true"
            @dragstart="onDragStart($event, shape)"
          >
            <div class="shape-icon">{{ shape.icon }}</div>
            <div class="shape-name">{{ shape.name }}</div>
          </div>
        </div>
      </div>

      <!-- Main 3D Viewport -->
      <div class="main-viewport">
        <div 
          ref="threeContainer" 
          class="three-container"
          @click="onMouseClick"
          @mousedown="onMouseDown"
          @mousemove="onMouseMove"
          @mouseup="onMouseUp"
          @dragover="onDragOver"
          @drop="onDrop"
        ></div>
        
        <div v-if="!sceneReady" class="loading-indicator">
          <div class="loading-spinner"></div>
          <p>Loading 3D Map Editor...</p>
        </div>
      </div>

      <!-- Right Sidebar - Properties -->
      <div class="sidebar right-sidebar">
        <div class="sidebar-header">
          <h4>Properties</h4>
        </div>
        
        <div v-if="selectedObject" class="properties-panel">
          <div class="property-group">
            <h5>Transform</h5>
            <div class="property-row">
              <label>Position X:</label>
              <input type="number" v-model.number="objectPosition.x" @input="updateObjectPosition" step="0.1">
            </div>
            <div class="property-row">
              <label>Position Y:</label>
              <input type="number" v-model.number="objectPosition.y" @input="updateObjectPosition" step="0.1">
            </div>
            <div class="property-row">
              <label>Position Z:</label>
              <input type="number" v-model.number="objectPosition.z" @input="updateObjectPosition" step="0.1">
            </div>
            <div class="property-row">
              <label>Scale:</label>
              <input type="number" v-model.number="objectScale.x" @input="updateObjectScale" step="0.1" min="0.1">
            </div>
            <div class="property-row">
              <label>Rotation:</label>
              <input type="number" v-model.number="objectRotation" @input="updateObjectRotation" step="1">
            </div>
          </div>
          
          <div class="property-group">
            <h5>Color</h5>
            <div class="color-palette">
              <div 
                v-for="color in colorPalette" 
                :key="color"
                @click="changeObjectColor(color)"
                :class="['color-swatch', { active: selectedColor === color }]"
                :style="{ backgroundColor: color }"
              ></div>
            </div>
          </div>
          
          <div class="property-group">
            <h5>Actions</h5>
            <button @click="duplicateSelected" class="action-btn">Duplicate</button>
            <button @click="deleteSelected" class="action-btn danger">Delete</button>
          </div>
        </div>
        
        <div v-else class="no-selection">
          <p>Select an object to edit properties</p>
        </div>
        
        <div class="stats">
          <div class="stat-item">
            <span>Objects:</span>
            <span>{{ mapObjects.length }}</span>
          </div>
          <div class="stat-item">
            <span>Complexity:</span>
            <span>{{ getComplexityLevel() }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import * as THREE from 'three'
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js'
import { markRaw } from 'vue'

export default {
  name: 'Map3DEditor',
  emits: ['map-created'],
  data() {
    return {
      // Three.js objects (marked as non-reactive)
      scene: null,
      camera: null,
      renderer: null,
      raycaster: null,
      mouse: null,
      gridHelper: null,
      customTransformControls: null,
      isDragging: false,
      dragHandle: null,
      dragStartMouse: null,
      dragStartPosition: null,
      dragStartScale: null,
      dragStartRotation: null,
      
      // Map objects (non-reactive)
      mapObjects: [],
      selectedObject: null,
      transformMode: 'translate', // 'translate', 'rotate', 'scale', 'deform'
      selectedShape: null,
      selectedColor: '#228B22',
      
      // UI state
      showGrid: true,
      objectPosition: { x: 0, y: 0, z: 0 },
      objectScale: { x: 1, y: 1, z: 1 },
      objectRotation: 0,
      animationId: null,
      sceneReady: false,
      activeCategory: 'vegetation',
      // GLB state
      loadedModel: null,
      cameraAngles: { azimuthDeg: 45, elevationDeg: 60, distance: 28 },
      
      // Scenery shape library
      terrainShapes: [
        { id: 'ground-plane', name: 'Ground', icon: '🟫', color: '#8B4513', geometry: 'plane', scale: { x: 10, y: 1, z: 10 } },
        { id: 'hill', name: 'Hill', icon: '⛰️', color: '#228B22', geometry: 'hill' },
        { id: 'rock', name: 'Rock', icon: '🪨', color: '#708090', geometry: 'rock' },
        { id: 'water', name: 'Water', icon: '💧', color: '#4682B4', geometry: 'water' }
      ],
      
      vegetationShapes: [
        { id: 'tree-oak', name: 'Oak Tree', icon: '🌳', color: '#228B22', geometry: 'tree-oak' },
        { id: 'tree-pine', name: 'Pine Tree', icon: '🌲', color: '#006400', geometry: 'tree-pine' },
        { id: 'palm-tree', name: 'Palm Tree', icon: '🌴', color: '#228B22', geometry: 'palm-tree' },
        { id: 'bush', name: 'Bush', icon: '🌿', color: '#32CD32', geometry: 'bush' },
        { id: 'grass-patch', name: 'Grass', icon: '🌱', color: '#9ACD32', geometry: 'grass' },
        { id: 'flower-bed', name: 'Flowers', icon: '🌸', color: '#FFB6C1', geometry: 'flowers' }
      ],
      
      structureShapes: [
        { id: 'bench', name: 'Bench', icon: '🪑', color: '#8B4513', geometry: 'bench' },
        { id: 'lamp-post', name: 'Lamp Post', icon: '💡', color: '#2F2F2F', geometry: 'lamp-post' },
        { id: 'fountain', name: 'Fountain', icon: '⛲', color: '#4682B4', geometry: 'fountain' },
        { id: 'gazebo', name: 'Gazebo', icon: '🏛️', color: '#D2691E', geometry: 'gazebo' }
      ],
      
      roadShapes: [
        { id: 'road-straight', name: 'Straight Road', icon: '🛣️', color: '#2F2F2F', geometry: 'road-straight' },
        { id: 'road-curve', name: 'Curved Road', icon: '🛣️', color: '#2F2F2F', geometry: 'road-curve' },
        { id: 'path', name: 'Walking Path', icon: '🚶', color: '#D2691E', geometry: 'path' },
        { id: 'crosswalk', name: 'Crosswalk', icon: '🚸', color: '#FFFFFF', geometry: 'crosswalk' }
      ],
      
      decorativeShapes: [
        { id: 'statue', name: 'Statue', icon: '🗿', color: '#708090', geometry: 'statue' },
        { id: 'flag-pole', name: 'Flag Pole', icon: '🏁', color: '#2F2F2F', geometry: 'flag-pole' },
        { id: 'sign', name: 'Sign', icon: '🪧', color: '#8B4513', geometry: 'sign' },
        { id: 'trash-bin', name: 'Trash Bin', icon: '🗑️', color: '#696969', geometry: 'trash-bin' }
      ],
      
      colorPalette: [
        '#228B22', '#006400', '#32CD32', '#9ACD32', '#8B4513',
        '#4682B4', '#708090', '#2F4F4F', '#696969', '#D2691E',
        '#8B0000', '#DC143C', '#FFB6C1', '#FFD700', '#4B0082',
        '#87CEEB', '#F5DEB3', '#DEB887', '#D3D3D3', '#2F2F2F'
      ]
    }
  },
  
  mounted() {
    this.initThreeJS()
    this.addEventListeners()
    this.createInitialGround()
  },
  
  beforeUnmount() {
    this.cleanup()
  },
  
  methods: {
    onGlbSelected(event) {
      const file = event.target.files && event.target.files[0]
      if (!file) return
      const url = URL.createObjectURL(file)
      const loader = new GLTFLoader()
      loader.load(url, (gltf) => {
        if (this.loadedModel) {
          this.scene.remove(this.loadedModel)
          this.disposeObject(this.loadedModel)
        }
        this.loadedModel = markRaw(gltf.scene)
        this.loadedModel.traverse((child) => {
          if (child.isMesh) {
            child.castShadow = true
            child.receiveShadow = true
          }
        })
        const box = new THREE.Box3().setFromObject(this.loadedModel)
        const size = new THREE.Vector3()
        box.getSize(size)
        const center = new THREE.Vector3()
        box.getCenter(center)
        this.loadedModel.position.sub(center)
        const maxDim = Math.max(size.x, size.y, size.z)
        if (maxDim > 0) {
          const scale = 8 / maxDim
          this.loadedModel.scale.setScalar(scale)
        }
        this.scene.add(this.loadedModel)
        this.applyCameraAngles()
      })
    },
    applyCameraAngles() {
      if (!this.camera || !this.cameraAngles) return
      const az = (this.cameraAngles.azimuthDeg || 0) * Math.PI / 180
      const el = (this.cameraAngles.elevationDeg || 0) * Math.PI / 180
      const dist = Math.max(1, this.cameraAngles.distance || 20)
      const x = dist * Math.cos(el) * Math.cos(az)
      const y = dist * Math.sin(el)
      const z = dist * Math.cos(el) * Math.sin(az)
      this.camera.position.set(x, y, z)
      this.camera.lookAt(0, 0, 0)
    },
    setIsometric() {
      this.cameraAngles.azimuthDeg = 45
      this.cameraAngles.elevationDeg = 35
      this.cameraAngles.distance = 28
      this.applyCameraAngles()
    },
    setTopView() {
      this.cameraAngles.azimuthDeg = 0
      this.cameraAngles.elevationDeg = 90
      this.cameraAngles.distance = 35
      this.applyCameraAngles()
    },
    disposeObject(obj) {
      obj && obj.traverse && obj.traverse((child) => {
        if (child.isMesh) {
          if (child.geometry) child.geometry.dispose()
          if (child.material) {
            if (Array.isArray(child.material)) child.material.forEach(m => m.dispose && m.dispose())
            else if (child.material.dispose) child.material.dispose()
          }
        }
      })
    },
    initThreeJS() {
      const container = this.$refs.threeContainer
      if (!container) return
      
      // Scene
      this.scene = markRaw(new THREE.Scene())
      this.scene.background = markRaw(new THREE.Color(0x87CEEB)) // Sky blue
      
      // Camera - Same isometric angle as building editor
      this.camera = markRaw(new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000))
      this.camera.position.set(10, 10, 10)
      this.camera.lookAt(0, 0, 0)
      
      // Renderer
      this.renderer = markRaw(new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true }))
      this.renderer.setSize(container.clientWidth, container.clientHeight)
      this.renderer.shadowMap.enabled = true
      this.renderer.shadowMap.type = THREE.PCFSoftShadowMap
      container.appendChild(this.renderer.domElement)
      
      // Lights
      const ambientLight = markRaw(new THREE.AmbientLight(0xffffff, 0.6))
      this.scene.add(ambientLight)
      
      const directionalLight = markRaw(new THREE.DirectionalLight(0xffffff, 0.8))
      directionalLight.position.set(10, 20, 10)
      directionalLight.castShadow = true
      directionalLight.shadow.mapSize.width = 2048
      directionalLight.shadow.mapSize.height = 2048
      this.scene.add(directionalLight)
      
      // Grid
      this.gridHelper = markRaw(new THREE.GridHelper(20, 20, 0x888888, 0xcccccc))
      this.scene.add(this.gridHelper)
      
      // Raycaster and mouse
      this.raycaster = markRaw(new THREE.Raycaster())
      this.mouse = markRaw(new THREE.Vector2())
      
      // Custom transform controls
      this.customTransformControls = markRaw(new THREE.Group())
      this.scene.add(this.customTransformControls)
      this.createTransformControls()
      
      // Start render loop
      this.animate()
      this.sceneReady = true
    },
    
    createInitialGround() {
      // Create a large ground plane
      const groundGeometry = markRaw(new THREE.PlaneGeometry(20, 20))
      const groundMaterial = markRaw(new THREE.MeshLambertMaterial({ 
        color: 0x90EE90, 
        transparent: true, 
        opacity: 0.8 
      }))
      const ground = markRaw(new THREE.Mesh(groundGeometry, groundMaterial))
      ground.rotation.x = -Math.PI / 2
      ground.position.y = -0.01
      ground.receiveShadow = true
      this.scene.add(ground)
    },
    
    createTransformControls() {
      // Create all transform handles like Building3DEditor
      this.createScaleHandles()
      this.createRotationRings()
      this.createPositionArrows()
      this.createDeformHandles()
      this.customTransformControls.visible = false
    },
    
    createScaleHandles() {
      const handleSize = 0.12
      const faceDistance = 1.1 // Distance from object center to face handles
      
      // Face handles for individual face scaling (like Tinkercad)
      const faceHandles = [
        // X faces (left/right)
        { pos: [faceDistance, 0, 0], color: 0xff4444, name: 'scale-face-x-pos', face: '+X' },
        { pos: [-faceDistance, 0, 0], color: 0xff4444, name: 'scale-face-x-neg', face: '-X' },
        
        // Y faces (top/bottom)  
        { pos: [0, faceDistance, 0], color: 0x44ff44, name: 'scale-face-y-pos', face: '+Y' },
        { pos: [0, -faceDistance, 0], color: 0x44ff44, name: 'scale-face-y-neg', face: '-Y' },
        
        // Z faces (front/back)
        { pos: [0, 0, faceDistance], color: 0x4444ff, name: 'scale-face-z-pos', face: '+Z' },
        { pos: [0, 0, -faceDistance], color: 0x4444ff, name: 'scale-face-z-neg', face: '-Z' }
      ]
      
      // Corner handles for uniform scaling
      const cornerHandles = [
        { pos: [1, 1, 1], color: 0xffffff, name: 'scale-uniform' },
        { pos: [-1, 1, 1], color: 0xffffff, name: 'scale-uniform' },
        { pos: [1, -1, 1], color: 0xffffff, name: 'scale-uniform' },
        { pos: [-1, -1, 1], color: 0xffffff, name: 'scale-uniform' },
        { pos: [1, 1, -1], color: 0xffffff, name: 'scale-uniform' },
        { pos: [-1, 1, -1], color: 0xffffff, name: 'scale-uniform' },
        { pos: [1, -1, -1], color: 0xffffff, name: 'scale-uniform' },
        { pos: [-1, -1, -1], color: 0xffffff, name: 'scale-uniform' }
      ]
      
      // Create face handles (squares for face scaling)
      faceHandles.forEach(({ pos, color, name, face }) => {
        const geometry = markRaw(new THREE.BoxGeometry(handleSize, handleSize, handleSize))
        const material = markRaw(new THREE.MeshBasicMaterial({ 
          color: color,
          transparent: true,
          opacity: 0.9
        }))
        const handle = markRaw(new THREE.Mesh(geometry, material))
        handle.position.set(...pos)
        handle.userData = { 
          type: 'scale-handle', 
          axis: name,
          face: face,
          originalColor: color,
          originalOpacity: 0.9 
        }
        this.customTransformControls.add(handle)
      })
      
      // Create corner handles (smaller white cubes for uniform scaling)
      cornerHandles.forEach(({ pos, color, name }) => {
        const geometry = markRaw(new THREE.BoxGeometry(handleSize * 0.8, handleSize * 0.8, handleSize * 0.8))
        const material = markRaw(new THREE.MeshBasicMaterial({ 
          color: color,
          transparent: true,
          opacity: 0.8
        }))
        const handle = markRaw(new THREE.Mesh(geometry, material))
        handle.position.set(...pos)
        handle.userData = { 
          type: 'scale-handle', 
          axis: name, 
          originalColor: color,
          originalOpacity: 0.8 
        }
        this.customTransformControls.add(handle)
      })
    },
    
    createRotationRings() {
      // Create rotation rings like Tinkercad
      const radius = 1.2
      const ringGeometry = markRaw(new THREE.TorusGeometry(radius, 0.03, 8, 64))
      
      // X-axis rotation ring (red) - tilts forward/backward (nodding "yes")
      const xRing = markRaw(new THREE.Mesh(ringGeometry, markRaw(new THREE.MeshBasicMaterial({ 
        color: 0xff0000,
        transparent: true,
        opacity: 0.7
      }))))
      xRing.rotation.y = Math.PI / 2  // Changed back to rotation.y to make it visible
      xRing.userData = { 
        type: 'rotation-handle', 
        axis: 'x',
        originalColor: 0xff0000,
        originalOpacity: 0.7
      }
      this.customTransformControls.add(xRing)
      
      // Y-axis rotation ring (green) - spins left/right (shaking head "no")
      const yRing = markRaw(new THREE.Mesh(ringGeometry, markRaw(new THREE.MeshBasicMaterial({ 
        color: 0x0000ff,
        transparent: true,
        opacity: 0.7
      }))))
      yRing.rotation.x = Math.PI / 2  // Vertical ring for left/right spinning
      yRing.userData = { 
        type: 'rotation-handle', 
        axis: 'y',
        originalColor: 0x00ff00,
        originalOpacity: 0.7
      }
      this.customTransformControls.add(yRing)
      
      // Z-axis rotation ring (blue) - rolls clockwise/counterclockwise (tilting head sideways)
      const zRing = markRaw(new THREE.Mesh(ringGeometry, markRaw(new THREE.MeshBasicMaterial({ 
        color: 0x00ff00,
        transparent: true,
        opacity: 0.7
      }))))
      // No rotation needed - faces forward for rolling motion
      zRing.userData = { 
        type: 'rotation-handle', 
        axis: 'z',
        originalColor: 0x0000ff,
        originalOpacity: 0.7
      }
      this.customTransformControls.add(zRing)
    },
    
    createPositionArrows() {
      // Create arrow handles for Y-axis translation only (up/down)
      // X and Z movement will be handled by direct object dragging
      const arrowLength = 1.5
      const arrowWidth = 0.08
      const headSize = 0.15
      
      // +Y arrow (up) - green
      const yPosArrowGroup = markRaw(new THREE.Group())
      const yPosShaft = markRaw(new THREE.Mesh(
        markRaw(new THREE.CylinderGeometry(arrowWidth, arrowWidth, arrowLength, 8)),
        markRaw(new THREE.MeshBasicMaterial({ color: 0x00ff00, transparent: true, opacity: 0.8 }))
      ))
      yPosShaft.position.y = arrowLength / 2
      
      const yPosHead = markRaw(new THREE.Mesh(
        markRaw(new THREE.ConeGeometry(headSize, headSize * 2, 8)),
        markRaw(new THREE.MeshBasicMaterial({ color: 0x00ff00, transparent: true, opacity: 0.8 }))
      ))
      yPosHead.position.y = arrowLength
      
      yPosArrowGroup.add(yPosShaft)
      yPosArrowGroup.add(yPosHead)
      yPosArrowGroup.userData = { 
        type: 'translate-handle', 
        axis: 'y',
        originalColor: 0x00ff00,
        originalOpacity: 0.8
      }
      this.customTransformControls.add(yPosArrowGroup)
      
      // -Y arrow (down) - green
      const yNegArrowGroup = markRaw(new THREE.Group())
      const yNegShaft = markRaw(new THREE.Mesh(
        markRaw(new THREE.CylinderGeometry(arrowWidth, arrowWidth, arrowLength, 8)),
        markRaw(new THREE.MeshBasicMaterial({ color: 0x00ff00, transparent: true, opacity: 0.8 }))
      ))
      yNegShaft.rotation.x = Math.PI
      yNegShaft.position.y = -arrowLength / 2
      
      const yNegHead = markRaw(new THREE.Mesh(
        markRaw(new THREE.ConeGeometry(headSize, headSize * 2, 8)),
        markRaw(new THREE.MeshBasicMaterial({ color: 0x00ff00, transparent: true, opacity: 0.8 }))
      ))
      yNegHead.rotation.x = Math.PI
      yNegHead.position.y = -arrowLength
      
      yNegArrowGroup.add(yNegShaft)
      yNegArrowGroup.add(yNegHead)
      yNegArrowGroup.userData = { 
        type: 'translate-handle', 
        axis: 'y',
        originalColor: 0x00ff00,
        originalOpacity: 0.8
      }
      this.customTransformControls.add(yNegArrowGroup)
    },
    
    createDeformHandles() {
      // Create handles for regional/local deformation
      // These allow scaling specific regions of the object
      const handleSize = 0.15
      const distance = 1.8
      
      // Create 6 deform handles - one on each face of the object
      const faces = [
        { name: 'x-pos', position: [distance, 0, 0], color: 0xff4444, axis: 'x', direction: 1 },
        { name: 'x-neg', position: [-distance, 0, 0], color: 0xff4444, axis: 'x', direction: -1 },
        { name: 'y-pos', position: [0, distance, 0], color: 0x44ff44, axis: 'y', direction: 1 },
        { name: 'y-neg', position: [0, -distance, 0], color: 0x44ff44, axis: 'y', direction: -1 },
        { name: 'z-pos', position: [0, 0, distance], color: 0x4444ff, axis: 'z', direction: 1 },
        { name: 'z-neg', position: [0, 0, -distance], color: 0x4444ff, axis: 'z', direction: -1 }
      ]
      
      faces.forEach(face => {
        const handle = markRaw(new THREE.Mesh(
          markRaw(new THREE.OctahedronGeometry(handleSize, 0)),
          markRaw(new THREE.MeshBasicMaterial({ 
            color: face.color, 
            transparent: true, 
            opacity: 0.8,
            wireframe: false
          }))
        ))
        
        handle.position.set(...face.position)
        handle.userData = {
          type: 'deform-handle',
          axis: face.axis,
          direction: face.direction,
          name: face.name,
          originalColor: face.color,
          originalOpacity: 0.8
        }
        
        this.customTransformControls.add(handle)
      })
    },
    
    getCurrentShapes() {
      switch(this.activeCategory) {
        case 'terrain':
          return this.terrainShapes
        case 'vegetation':
          return this.vegetationShapes
        case 'structures':
          return this.structureShapes
        case 'roads':
          return this.roadShapes
        case 'decorative':
          return this.decorativeShapes
        default:
          return this.vegetationShapes
      }
    },
    
    selectShape(shape) {
      this.selectedShape = shape
      // Don't auto-add to scene on click - wait for drag/drop
    },
    
    onDragStart(event, shape) {
      event.dataTransfer.setData('shape', JSON.stringify(shape))
    },
    
    onDragOver(event) {
      event.preventDefault()
    },
    
    onDrop(event) {
      event.preventDefault()
      const shapeData = JSON.parse(event.dataTransfer.getData('shape'))
      this.createShape(shapeData, event)
    },
    
    createShape(shapeData, event = null) {
      const geometry = this.createGeometry(shapeData.geometry)
      const material = markRaw(new THREE.MeshLambertMaterial({ 
        color: shapeData.color,
        transparent: true,
        opacity: 0.9
      }))
      
      const mesh = markRaw(new THREE.Mesh(geometry, material))
      
      // Apply initial scale if specified
      if (shapeData.scale) {
        mesh.scale.set(shapeData.scale.x || 1, shapeData.scale.y || 1, shapeData.scale.z || 1)
      }
      
      // Position based on drop location or center
      if (event) {
        const rect = this.renderer.domElement.getBoundingClientRect()
        const x = ((event.clientX - rect.left) / rect.width) * 2 - 1
        const y = -((event.clientY - rect.top) / rect.height) * 2 + 1
        
        this.raycaster.setFromCamera({ x, y }, this.camera)
        const intersects = this.raycaster.intersectObject(this.scene.children.find(child => child.type === 'Mesh' && child.material.transparent))
        
        if (intersects.length > 0) {
          const point = intersects[0].point
          mesh.position.set(
            point.x,
            mesh.scale.y / 2,
            point.z
          )
        }
      } else {
        mesh.position.set(0, mesh.scale.y / 2, 0)
      }
      
      mesh.castShadow = true
      mesh.receiveShadow = true
      mesh.userData.shapeName = shapeData.name
      mesh.userData.shapeId = shapeData.id
      
      this.scene.add(mesh)
      this.mapObjects.push(mesh)
      this.selectObject(mesh)
    },
    
    createGeometry(type) {
      switch(type) {
        case 'plane':
          return markRaw(new THREE.PlaneGeometry(1, 1))
        case 'hill':
          return markRaw(new THREE.SphereGeometry(1, 16, 8, 0, Math.PI * 2, 0, Math.PI / 2))
        case 'rock':
          return markRaw(new THREE.OctahedronGeometry(0.5))
        case 'water':
          return markRaw(new THREE.PlaneGeometry(2, 2))
        
        // Trees
        case 'tree-oak':
          return this.createOakTreeGeometry()
        case 'tree-pine':
          return this.createPineTreeGeometry()
        case 'palm-tree':
          return this.createPalmTreeGeometry()
        case 'bush':
          return markRaw(new THREE.SphereGeometry(0.5, 8, 6))
        case 'grass':
          return markRaw(new THREE.BoxGeometry(1, 0.1, 1))
        case 'flowers':
          return markRaw(new THREE.CylinderGeometry(0.3, 0.3, 0.2, 6))
        
        // Structures
        case 'bench':
          return markRaw(new THREE.BoxGeometry(1.5, 0.4, 0.4))
        case 'lamp-post':
          return markRaw(new THREE.CylinderGeometry(0.05, 0.05, 2.5, 8))
        case 'fountain':
          return markRaw(new THREE.CylinderGeometry(0.8, 0.8, 0.5, 16))
        case 'gazebo':
          return markRaw(new THREE.CylinderGeometry(2, 2, 2, 8))
        
        // Roads
        case 'road-straight':
          return markRaw(new THREE.BoxGeometry(3, 0.1, 1))
        case 'road-curve':
          return markRaw(new THREE.TorusGeometry(1, 0.5, 4, 8, Math.PI/2))
        case 'path':
          return markRaw(new THREE.BoxGeometry(1, 0.05, 3))
        case 'crosswalk':
          return markRaw(new THREE.BoxGeometry(3, 0.05, 3))
        
        // Decorative
        case 'statue':
          return markRaw(new THREE.CylinderGeometry(0.3, 0.3, 1.5, 8))
        case 'flag-pole':
          return markRaw(new THREE.CylinderGeometry(0.02, 0.02, 3, 8))
        case 'sign':
          return markRaw(new THREE.BoxGeometry(1, 0.8, 0.1))
        case 'trash-bin':
          return markRaw(new THREE.CylinderGeometry(0.3, 0.25, 0.6, 8))
        
        default:
          return markRaw(new THREE.BoxGeometry(1, 1, 1))
      }
    },
    
    createOakTreeGeometry() {
      // Simple oak tree: trunk + round canopy
      const group = markRaw(new THREE.Group())
      
      // Trunk
      const trunkGeometry = markRaw(new THREE.CylinderGeometry(0.1, 0.15, 1.5, 8))
      const trunkMaterial = markRaw(new THREE.MeshLambertMaterial({ color: 0x8B4513 }))
      const trunk = markRaw(new THREE.Mesh(trunkGeometry, trunkMaterial))
      trunk.position.y = 0.75
      
      // Canopy
      const canopyGeometry = markRaw(new THREE.SphereGeometry(0.8, 8, 6))
      const canopyMaterial = markRaw(new THREE.MeshLambertMaterial({ color: 0x228B22 }))
      const canopy = markRaw(new THREE.Mesh(canopyGeometry, canopyMaterial))
      canopy.position.y = 1.8
      
      group.add(trunk)
      group.add(canopy)
      
      return group
    },
    
    createPineTreeGeometry() {
      // Simple pine tree: trunk + cone
      const group = markRaw(new THREE.Group())
      
      // Trunk
      const trunkGeometry = markRaw(new THREE.CylinderGeometry(0.08, 0.1, 1, 8))
      const trunkMaterial = markRaw(new THREE.MeshLambertMaterial({ color: 0x8B4513 }))
      const trunk = markRaw(new THREE.Mesh(trunkGeometry, trunkMaterial))
      trunk.position.y = 0.5
      
      // Pine cone shape
      const coneGeometry = markRaw(new THREE.ConeGeometry(0.6, 2, 8))
      const coneMaterial = markRaw(new THREE.MeshLambertMaterial({ color: 0x006400 }))
      const cone = markRaw(new THREE.Mesh(coneGeometry, coneMaterial))
      cone.position.y = 2
      
      group.add(trunk)
      group.add(cone)
      
      return group
    },
    
    createPalmTreeGeometry() {
      // Simple palm tree: curved trunk + leaves
      const group = markRaw(new THREE.Group())
      
      // Trunk (slightly curved)
      const trunkGeometry = markRaw(new THREE.CylinderGeometry(0.08, 0.12, 2.5, 8))
      const trunkMaterial = markRaw(new THREE.MeshLambertMaterial({ color: 0x8B4513 }))
      const trunk = markRaw(new THREE.Mesh(trunkGeometry, trunkMaterial))
      trunk.position.y = 1.25
      trunk.rotation.z = 0.1 // Slight curve
      
      // Palm leaves (simplified as flat discs)
      const leavesGeometry = markRaw(new THREE.CylinderGeometry(1, 1, 0.1, 6))
      const leavesMaterial = markRaw(new THREE.MeshLambertMaterial({ color: 0x228B22 }))
      const leaves = markRaw(new THREE.Mesh(leavesGeometry, leavesMaterial))
      leaves.position.y = 2.5
      
      group.add(trunk)
      group.add(leaves)
      
      return group
    },
    
    selectObject(object) {
      this.selectedObject = object
      
      if (object && this.customTransformControls) {
        this.updateControlsPosition()
        this.updateCustomControlsVisibility()
        this.customTransformControls.visible = true
        
        // Update properties
        this.objectPosition = { ...object.position }
        this.objectScale = { ...object.scale }
        this.objectRotation = (object.rotation.y * 180 / Math.PI) % 360
      } else if (this.customTransformControls) {
        this.customTransformControls.visible = false
      }
    },
    
    updateControlsPosition() {
      if (!this.selectedObject || !this.customTransformControls) return
      this.customTransformControls.position.copy(this.selectedObject.position)
    },
    
    updateCustomControlsVisibility() {
      if (!this.customTransformControls) return
      
      // Hide all controls first
      this.customTransformControls.children.forEach(child => {
        child.visible = false
      })
      
      // Show only controls for current mode
      this.customTransformControls.children.forEach(child => {
        if (this.transformMode === 'scale' && child.userData.type === 'scale-handle') {
          child.visible = true
        } else if (this.transformMode === 'rotate' && child.userData.type === 'rotation-handle') {
          child.visible = true
        } else if (this.transformMode === 'translate' && child.userData.type === 'translate-handle') {
          child.visible = true
        } else if (this.transformMode === 'deform' && child.userData.type === 'deform-handle') {
          child.visible = true
        }
      })
    },
    
    setTransformMode(mode) {
      this.transformMode = mode
      this.updateCustomControlsVisibility()
    },
    
    updateObjectPosition() {
      if (!this.selectedObject) return
      this.selectedObject.position.set(
        this.objectPosition.x,
        this.objectPosition.y,
        this.objectPosition.z
      )
      this.updateControlsPosition()
    },
    
    updateObjectScale() {
      if (!this.selectedObject) return
      this.selectedObject.scale.setScalar(this.objectScale.x)
    },
    
    updateObjectRotation() {
      if (!this.selectedObject) return
      this.selectedObject.rotation.y = this.objectRotation * Math.PI / 180
    },
    
    changeObjectColor(color) {
      if (!this.selectedObject) return
      this.selectedColor = color
      this.selectedObject.material.color.setHex(parseInt(color.replace('#', '0x')))
    },
    
    duplicateSelected() {
      if (!this.selectedObject) return
      
      const original = this.selectedObject
      const geometry = original.geometry.clone()
      const material = original.material.clone()
      const mesh = markRaw(new THREE.Mesh(geometry, material))
      
      // Position slightly offset
      mesh.position.copy(original.position)
      mesh.position.x += 1
      mesh.scale.copy(original.scale)
      mesh.rotation.copy(original.rotation)
      mesh.castShadow = true
      mesh.receiveShadow = true
      
      this.scene.add(mesh)
      this.mapObjects.push(mesh)
      this.selectObject(mesh)
    },
    
    deleteSelected() {
      if (!this.selectedObject) return
      
      this.scene.remove(this.selectedObject)
      const index = this.mapObjects.indexOf(this.selectedObject)
      if (index > -1) {
        this.mapObjects.splice(index, 1)
      }
      
      this.selectObject(null)
    },
    
    resetView() {
      this.camera.position.set(10, 10, 10)
      this.camera.lookAt(0, 0, 0)
      this.cameraAngles = { azimuthDeg: 45, elevationDeg: 60, distance: 28 }
      this.applyCameraAngles()
      if (this.loadedModel) {
        this.scene.remove(this.loadedModel)
        this.disposeObject(this.loadedModel)
        this.loadedModel = null
      }
    },
    
    exportPNG() {
      // Render the scene
      this.renderer.render(this.scene, this.camera)
      
      // Get image data
      const canvas = this.renderer.domElement
      const dataURL = canvas.toDataURL('image/png')
      
      // Create download link
      const link = document.createElement('a')
      link.download = 'map-layout-3d.png'
      link.href = dataURL
      link.click()
      
      // Emit event with map data
      this.$emit('map-created', {
        imageData: dataURL,
        objectCount: this.mapObjects.length,
        complexity: this.getComplexityLevel()
      })
    },
    
    getComplexityLevel() {
      const count = this.mapObjects.length
      if (count === 0) return 'Empty'
      if (count <= 5) return 'Simple'
      if (count <= 15) return 'Medium'
      if (count <= 30) return 'Complex'
      return 'Very Complex'
    },
    
    // Event handlers
    onMouseClick(event) {
      const rect = this.renderer.domElement.getBoundingClientRect()
      this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1
      this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1
      
      this.raycaster.setFromCamera(this.mouse, this.camera)
      const intersects = this.raycaster.intersectObjects(this.mapObjects)
      
      if (intersects.length > 0) {
        this.selectObject(intersects[0].object)
      } else {
        this.selectObject(null)
      }
    },
    
    onMouseDown(event) {
      // Update mouse position
      const rect = this.renderer.domElement.getBoundingClientRect()
      this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1
      this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1
      
      // Check if we're clicking on a transform handle first
      if (this.customTransformControls && this.customTransformControls.visible) {
        this.raycaster.setFromCamera(this.mouse, this.camera)
        const handleIntersects = this.raycaster.intersectObjects(this.customTransformControls.children, true)
        
        if (handleIntersects.length > 0) {
          this.isDragging = true
          let intersectedObject = handleIntersects[0].object
          
          // If we hit a child of a group (like arrow shaft/head), use the parent group
          if (intersectedObject.parent && intersectedObject.parent.userData.type) {
            this.dragHandle = intersectedObject.parent
          } else {
            this.dragHandle = intersectedObject
          }
          
          this.dragStartMouse = { x: this.mouse.x, y: this.mouse.y }
          
          if (this.selectedObject) {
            this.dragStartPosition = this.selectedObject.position.clone()
            this.dragStartScale = this.selectedObject.scale.clone()
            this.dragStartRotation = this.selectedObject.rotation.clone()
          }
          
          event.preventDefault()
          return
        }
      }
      
      // If not clicking on a handle and in translate mode, check if clicking on the object itself for direct dragging
      if (this.transformMode === 'translate' && this.selectedObject) {
        this.raycaster.setFromCamera(this.mouse, this.camera)
        
        // Force update the object's bounding information for deformed objects
        if (this.selectedObject.userData.wasDeformed) {
          this.selectedObject.geometry.computeBoundingBox()
          this.selectedObject.geometry.computeBoundingSphere()
        }
        
        const objectIntersects = this.raycaster.intersectObject(this.selectedObject, true)
        
        if (objectIntersects.length > 0) {
          this.isDragging = true
          this.dragHandle = { userData: { type: 'object-drag' } } // Special handle for direct object dragging
          this.dragStartMouse = { x: this.mouse.x, y: this.mouse.y }
          
          if (this.selectedObject) {
            this.dragStartPosition = this.selectedObject.position.clone()
          }
          
          event.preventDefault()
        }
      }
    },
    
    onMouseMove(event) {
      // Update mouse position
      const rect = this.renderer.domElement.getBoundingClientRect()
      this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1
      this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1
      
      // Handle dragging
      if (this.isDragging && this.dragHandle && this.dragStartMouse) {
        const deltaX = this.mouse.x - this.dragStartMouse.x
        const deltaY = this.mouse.y - this.dragStartMouse.y
        
        const handleData = this.dragHandle.userData
        
        if (handleData.type === 'scale-handle') {
          this.handleScaleDrag(handleData.axis, deltaX, deltaY)
        } else if (handleData.type === 'rotation-handle') {
          this.handleRotationDrag(handleData.axis, deltaX, deltaY)
        } else if (handleData.type === 'translate-handle') {
          this.handleTranslateDrag(handleData.axis, deltaX, deltaY)
        } else if (handleData.type === 'object-drag') {
          this.handleDirectObjectDrag(deltaX, deltaY)
        } else if (handleData.type === 'deform-handle') {
          this.handleDeformDrag(handleData.axis, handleData.direction, deltaX, deltaY)
        }
        
        // Update custom controls position
        this.updateControlsPosition()
        this.customTransformControls.rotation.copy(this.selectedObject.rotation)
        
        // Update properties panel
        this.updatePropertiesFromObject()
        
        event.preventDefault()
        return
      }
      
      // Handle hover effects for transform handles
      if (this.customTransformControls && this.customTransformControls.visible) {
        this.raycaster.setFromCamera(this.mouse, this.camera)
        const handleIntersects = this.raycaster.intersectObjects(this.customTransformControls.children, true)
        
        // Reset all handles to normal appearance
        this.customTransformControls.children.forEach(child => {
          if (child.material && child.userData.originalOpacity !== undefined) {
            child.material.opacity = child.userData.originalOpacity
          }
          // Handle groups (like arrows)
          if (child.children) {
            child.children.forEach(grandchild => {
              if (grandchild.material && grandchild.userData && grandchild.userData.originalOpacity !== undefined) {
                grandchild.material.opacity = grandchild.userData.originalOpacity
              }
            })
          }
        })
        
        // Highlight hovered handle
        if (handleIntersects.length > 0) {
          const hoveredObject = handleIntersects[0].object
          let targetObject = hoveredObject
          
          // If we hit a child of a group, highlight the parent group
          if (hoveredObject.parent && hoveredObject.parent.userData.type) {
            targetObject = hoveredObject.parent
          }
          
          if (targetObject.material && targetObject.userData.originalOpacity !== undefined) {
            targetObject.material.opacity = Math.min(1.0, targetObject.userData.originalOpacity + 0.3)
          }
          
          // Change cursor to indicate interactivity
          this.renderer.domElement.style.cursor = 'pointer'
        } else {
          this.renderer.domElement.style.cursor = 'default'
        }
      }
    },
    
    onMouseUp() {
      // Clean up deform operation
      if (this.isDragging && this.dragHandle && this.dragHandle.userData.type === 'deform-handle') {
        // Clean up original geometry reference
        if (this.selectedObject && this.selectedObject.userData.originalGeometry) {
          this.selectedObject.userData.originalGeometry.dispose()
          delete this.selectedObject.userData.originalGeometry
        }
      }
      
      this.isDragging = false
      this.dragHandle = null
      this.dragStartMouse = null
      this.dragStartPosition = null
      this.dragStartScale = null
      this.dragStartRotation = null
    },
    
    handleScaleDrag(axis, deltaX, deltaY) {
      if (!this.selectedObject || !this.dragStartScale) return
      
      // Calculate scale factor based on mouse movement
      const scaleFactor = 1 + (deltaX + deltaY) * 2
      const clampedScale = Math.max(0.1, Math.min(5, scaleFactor))
      
      switch(axis) {
        // Individual face scaling
        case 'scale-face-x-pos':
        case 'scale-face-x-neg':
          this.selectedObject.scale.x = this.dragStartScale.x * clampedScale
          break
        case 'scale-face-y-pos':
        case 'scale-face-y-neg':
          this.selectedObject.scale.y = this.dragStartScale.y * clampedScale
          break
        case 'scale-face-z-pos':
        case 'scale-face-z-neg':
          this.selectedObject.scale.z = this.dragStartScale.z * clampedScale
          break
        
        // Uniform scaling (corner handles)
        case 'scale-uniform':
          this.selectedObject.scale.x = this.dragStartScale.x * clampedScale
          this.selectedObject.scale.y = this.dragStartScale.y * clampedScale
          this.selectedObject.scale.z = this.dragStartScale.z * clampedScale
          break
          
        // Legacy axis scaling (keep for compatibility)
        case 'scale-x':
          this.selectedObject.scale.x = this.dragStartScale.x * clampedScale
          break
        case 'scale-y':
          this.selectedObject.scale.y = this.dragStartScale.y * clampedScale
          break
        case 'scale-z':
          this.selectedObject.scale.z = this.dragStartScale.z * clampedScale
          break
      }
    },
    
    handleRotationDrag(axis, deltaX, deltaY) {
      if (!this.selectedObject || !this.dragStartRotation) return
      
      // Calculate rotation based on mouse movement
      const rotationSpeed = Math.PI * 2
      const rotationDelta = (deltaX + deltaY) * rotationSpeed
      
      switch(axis) {
        case 'x':
          this.selectedObject.rotation.x = this.dragStartRotation.x - rotationDelta
          break
        case 'y':
          this.selectedObject.rotation.y = this.dragStartRotation.y + rotationDelta
          break
        case 'z':
          this.selectedObject.rotation.z = this.dragStartRotation.z + rotationDelta
          break
      }
    },
    
    handleTranslateDrag(axis, deltaX, deltaY) {
      if (!this.selectedObject || !this.dragStartPosition) return
      
      // Calculate translation based on mouse movement
      const moveSpeed = 8
      
      switch(axis) {
        case 'x':
          this.selectedObject.position.x = this.dragStartPosition.x + deltaX * moveSpeed
          break
        case 'y':
          // Reversed: drag up = move up (use -deltaY for intuitive Y movement)
          this.selectedObject.position.y = this.dragStartPosition.y - deltaY * moveSpeed
          break
        case 'z':
          // Reversed: drag left = move left (use deltaX for Z-axis movement)
          this.selectedObject.position.z = this.dragStartPosition.z - deltaX * moveSpeed
          break
      }
    },
    
    handleDirectObjectDrag(deltaX, deltaY) {
      if (!this.selectedObject || !this.dragStartPosition) return
      
      // Direct object dragging - move X and Z based on mouse movement
      const moveSpeed = 8
      
      // X-axis: drag left/right to move left/right
      this.selectedObject.position.x = this.dragStartPosition.x + deltaX * moveSpeed
      
      // Z-axis: drag up/down to move forward/backward (in 3D space)
      this.selectedObject.position.z = this.dragStartPosition.z - deltaY * moveSpeed
    },
    
    handleDeformDrag(axis, direction, deltaX, deltaY) {
      if (!this.selectedObject || !this.selectedObject.geometry || !this.dragStartPosition) return
      
      // Store original geometry if this is the first time deforming
      if (!this.selectedObject.userData.originalGeometry) {
        this.selectedObject.userData.originalGeometry = this.selectedObject.geometry.clone()
      }
      
      const geometry = this.selectedObject.geometry
      const originalGeometry = this.selectedObject.userData.originalGeometry
      const positions = geometry.attributes.position.array
      const originalPositions = originalGeometry.attributes.position.array
      const positionCount = positions.length / 3
      
      // Calculate deformation based on mouse movement - much more controlled
      let deformAmount = 0
      
      switch(axis) {
        case 'x':
          deformAmount = deltaX * direction * 0.5 // Much smaller multiplier for control
          break
        case 'y':
          deformAmount = -deltaY * direction * 0.5 // Inverted Y for intuitive up/down
          break
        case 'z':
          deformAmount = deltaY * direction * 0.5
          break
      }
      
      // Reset to original geometry first (prevents runaway scaling)
      for (let i = 0; i < positions.length; i++) {
        positions[i] = originalPositions[i]
      }
      
      // Apply deformation to vertices in the specified region
      for (let i = 0; i < positionCount; i++) {
        const x = originalPositions[i * 3]
        const y = originalPositions[i * 3 + 1]
        const z = originalPositions[i * 3 + 2]
        
        // Determine if this vertex should be affected based on axis and direction
        let shouldDeform = false
        
        switch(axis) {
          case 'x':
            shouldDeform = (direction > 0 && x > 0) || (direction < 0 && x < 0)
            if (shouldDeform) {
              positions[i * 3] = x + deformAmount
            }
            break
          case 'y':
            shouldDeform = (direction > 0 && y > 0) || (direction < 0 && y < 0)
            if (shouldDeform) {
              positions[i * 3 + 1] = y + deformAmount
            }
            break
          case 'z':
            shouldDeform = (direction > 0 && z > 0) || (direction < 0 && z < 0)
            if (shouldDeform) {
              positions[i * 3 + 2] = z + deformAmount
            }
            break
        }
      }
      
      // Update the geometry
      geometry.attributes.position.needsUpdate = true
      geometry.computeBoundingBox()
      geometry.computeBoundingSphere()
      
      // Mark object as deformed so controls can follow the new center
      this.selectedObject.userData.wasDeformed = true
    },
    
    updatePropertiesFromObject() {
      if (this.selectedObject) {
        this.objectPosition = { ...this.selectedObject.position }
        this.objectScale = { ...this.selectedObject.scale }
        this.objectRotation = (this.selectedObject.rotation.y * 180 / Math.PI) % 360
      }
    },
    
    animate() {
      this.animationId = requestAnimationFrame(this.animate)
      if (this.renderer && this.scene && this.camera) {
        this.renderer.render(this.scene, this.camera)
      }
    },
    
    addEventListeners() {
      window.addEventListener('resize', this.onWindowResize)
      window.addEventListener('keydown', this.onKeyDown)
    },
    
    onWindowResize() {
      const container = this.$refs.threeContainer
      if (!container || !this.camera || !this.renderer) return
      
      this.camera.aspect = container.clientWidth / container.clientHeight
      this.camera.updateProjectionMatrix()
      this.renderer.setSize(container.clientWidth, container.clientHeight)
    },
    
    onKeyDown(event) {
      switch(event.key.toLowerCase()) {
        case 'g': // Move/Translate
          if (this.selectedObject) {
            this.setTransformMode('translate')
            event.preventDefault()
          }
          break
        case 'r': // Rotate
          if (this.selectedObject) {
            this.setTransformMode('rotate')
            event.preventDefault()
          }
          break
        case 's': // Scale
          if (this.selectedObject) {
            this.setTransformMode('scale')
            event.preventDefault()
          }
          break
        case 'd': // Deform/Regional Scale
          if (this.selectedObject) {
            this.setTransformMode('deform')
            event.preventDefault()
          }
          break
        case 'escape':
          this.selectObject(null)
          event.preventDefault()
          break
        case 'delete':
        case 'backspace':
          if (this.selectedObject) {
            this.deleteSelected()
            event.preventDefault()
          }
          break
      }
    },
    
    cleanup() {
      if (this.animationId) {
        cancelAnimationFrame(this.animationId)
      }
      
      window.removeEventListener('resize', this.onWindowResize)
      window.removeEventListener('keydown', this.onKeyDown)
      
      if (this.renderer) {
        this.renderer.dispose()
      }
    }
  }
}
</script>

<style scoped>
.map-3d-editor {
  width: 100%;
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f5f5f5;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 20px;
  background: #2c3e50;
  color: white;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.toolbar h3 {
  margin: 0;
  font-size: 1.2em;
}

.toolbar-center {
  display: flex;
  gap: 10px;
}

.toolbar-btn {
  padding: 8px 12px;
  border: none;
  border-radius: 4px;
  background: #34495e;
  color: white;
  cursor: pointer;
  transition: background-color 0.2s;
}

.toolbar-btn:hover:not(:disabled) {
  background: #4a6741;
}

.toolbar-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.toolbar-btn.active {
  background: #27ae60;
}

.toolbar-btn.export {
  background: #e74c3c;
}

.toolbar-btn.export:hover {
  background: #c0392b;
}

.editor-container {
  display: flex;
  flex: 1;
  height: calc(100vh - 60px);
}

.sidebar {
  width: 250px;
  background: white;
  border-right: 1px solid #ddd;
  overflow-y: auto;
}

.left-sidebar {
  border-right: 1px solid #ddd;
}

.right-sidebar {
  border-left: 1px solid #ddd;
}

.sidebar-header {
  padding: 15px;
  border-bottom: 1px solid #eee;
  background: #f8f9fa;
}

.sidebar-header h4 {
  margin: 0 0 10px 0;
  color: #2c3e50;
}

.category-select {
  width: 100%;
  padding: 5px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.shapes-grid {
  padding: 15px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.shape-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 10px;
  border: 2px solid transparent;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  background: #f8f9fa;
}

.shape-item:hover {
  border-color: #3498db;
  background: #e3f2fd;
}

.shape-item.selected {
  border-color: #27ae60;
  background: #e8f5e8;
}

.shape-icon {
  font-size: 24px;
  margin-bottom: 5px;
}

.shape-name {
  font-size: 12px;
  text-align: center;
  color: #2c3e50;
}

.main-viewport {
  flex: 1;
  position: relative;
  background: #ecf0f1;
}

.three-container {
  width: 100%;
  height: 100%;
}

.loading-indicator {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  color: #7f8c8d;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #bdc3c7;
  border-top: 4px solid #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 10px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.properties-panel {
  padding: 15px;
}

.property-group {
  margin-bottom: 20px;
}

.property-group h5 {
  margin: 0 0 10px 0;
  color: #2c3e50;
  font-size: 14px;
  font-weight: 600;
}

.property-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.property-row label {
  font-size: 12px;
  color: #7f8c8d;
}

.property-row input {
  width: 80px;
  padding: 4px;
  border: 1px solid #ddd;
  border-radius: 3px;
  font-size: 12px;
}

.color-palette {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 5px;
}

.color-swatch {
  width: 30px;
  height: 30px;
  border-radius: 4px;
  cursor: pointer;
  border: 2px solid transparent;
  transition: border-color 0.2s;
}

.color-swatch:hover {
  border-color: #3498db;
}

.color-swatch.active {
  border-color: #2c3e50;
}

.action-btn {
  width: 100%;
  padding: 8px;
  margin-bottom: 5px;
  border: none;
  border-radius: 4px;
  background: #3498db;
  color: white;
  cursor: pointer;
  font-size: 12px;
}

.action-btn:hover {
  background: #2980b9;
}

.action-btn.danger {
  background: #e74c3c;
}

.action-btn.danger:hover {
  background: #c0392b;
}

.no-selection {
  padding: 15px;
  text-align: center;
  color: #7f8c8d;
  font-style: italic;
}

.stats {
  padding: 15px;
  border-top: 1px solid #eee;
  background: #f8f9fa;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  margin-bottom: 5px;
  font-size: 12px;
  color: #2c3e50;
}
</style>
