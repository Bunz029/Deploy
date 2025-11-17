<template>
  <div>
    <!-- Activity Log Button -->
    <button 
      @click="toggleDrawer" 
      class="activity-log-button"
      :class="{ 'drawer-open': isOpen }"
      title="View Activity Log"
    >
      <span class="log-icon">📜</span>
      <span class="log-text">Logs</span>
    </button>

    <!-- Activity Log Drawer -->
    <div v-if="isOpen" class="drawer-overlay" @click="closeDrawer">
      <div class="drawer-content" @click.stop>
        <!-- Drawer Header -->
        <div class="drawer-header">
          <h2 class="drawer-title">Activity Log</h2>
          <button @click="closeDrawer" class="drawer-close" title="Close Activity Log">
            <span>&times;</span>
          </button>
        </div>

        <!-- Filter Toggle -->
        <div class="filter-section">
          <div class="filter-toggle">
            <button 
              @click="setFilter('published')" 
              :class="{ active: currentFilter === 'published' }"
              class="filter-btn published-btn"
            >
              <span class="filter-icon">📤</span>
              Published Only
            </button>
            <button 
              @click="setFilter('all')" 
              :class="{ active: currentFilter === 'all' }"
              class="filter-btn all-btn"
            >
              <span class="filter-icon">📋</span>
              All Activity
            </button>
          </div>
          <div class="filter-info">
            <span class="filter-count">{{ filteredLogs.length }} entries</span>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading activity log...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="activityLogs.length === 0" class="empty-state">
          <div class="empty-icon">📝</div>
          <h3>No Activity Yet</h3>
          <p>Admin actions will appear here once they are performed.</p>
        </div>

        <!-- Activity Log Entries -->
        <div v-else class="activity-list">
          <div 
            v-for="log in filteredLogs" 
            :key="log.id" 
            class="activity-entry"
            :class="getActivityTypeClass(log.action)"
          >
            <div class="activity-icon">
              {{ getActivityIcon(log.action) }}
            </div>
            <div class="activity-content">
              <!-- Summary Display -->
              <div class="activity-summary">
                <div class="summary-main">
                  <span class="summary-description">{{ getSummaryDescription(log) }}</span>
                  <button 
                    @click="toggleDetails(log.id)" 
                    class="view-details-btn"
                    :class="{ expanded: expandedEntries.includes(log.id) }"
                  >
                    <span class="details-text">View Details</span>
                    <span class="details-icon">{{ expandedEntries.includes(log.id) ? '▼' : '▶' }}</span>
                  </button>
                </div>
                <div class="summary-meta">
                  <span class="action-type">{{ getFormattedAction(log.action) }}</span>
                  <span class="meta-separator">•</span>
                  <span class="admin-name">{{ log.user_name || 'System' }}</span>
                  <span class="meta-separator">•</span>
                  <span class="timestamp">{{ formatDateTime(log.created_at) }}</span>
                  <span v-if="log.ip_address" class="meta-separator">•</span>
                  <span v-if="log.ip_address" class="ip-address">{{ log.ip_address }}</span>
                </div>
              </div>
              
              <!-- Expanded Details Section -->
              <div v-if="expandedEntries.includes(log.id)" class="expanded-details">
                <!-- Action-Specific Content -->
                <div v-if="getActionSpecificContent(log)" class="action-content">
                  <div v-html="getActionSpecificContent(log)"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Refresh Button -->
        <div class="drawer-footer">
          <button @click="refreshLogs" class="refresh-button" :disabled="loading">
            <span class="refresh-icon">🔄</span>
            Refresh
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ActivityLogDrawer',
  data() {
    return {
      isOpen: false,
      loading: false,
      activityLogs: [],
      currentFilter: 'published', // Default to published only
      expandedEntries: [] // Track which entries are expanded
    }
  },
  computed: {
    filteredLogs() {
      if (this.currentFilter === 'published') {
        // Only show published actions
        return this.activityLogs.filter(log => 
          log.action === 'published' || 
          log.action === 'published_building' || 
          log.action === 'published_map' ||
          log.action === 'published_deletion' ||
          log.action === 'published_restoration'
        )
      }
      // Show all activities
      return this.activityLogs
    }
  },
  mounted() {
    // Load initial activity logs
    this.loadActivityLogs()
  },
  methods: {
    toggleDrawer() {
      this.isOpen = !this.isOpen
      if (this.isOpen) {
        this.loadActivityLogs()
      }
    },
    closeDrawer() {
      this.isOpen = false
    },
    async loadActivityLogs() {
      try {
        this.loading = true
        const response = await axios.get('/activity-logs')
        this.activityLogs = response.data
      } catch (error) {
        console.error('Error loading activity logs:', error)
        this.$emit('show-toast', 'error', 'Load Failed', 'Failed to load activity logs.')
      } finally {
        this.loading = false
      }
    },
    async refreshLogs() {
      await this.loadActivityLogs()
    },
    setFilter(filter) {
      this.currentFilter = filter
    },
    toggleDetails(logId) {
      const index = this.expandedEntries.indexOf(logId)
      if (index > -1) {
        this.expandedEntries.splice(index, 1)
      } else {
        this.expandedEntries.push(logId)
      }
    },
    formatDateTime(dateString) {
      const date = new Date(dateString)
      const options = {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      }
      return date.toLocaleDateString('en-US', options)
    },
    getActivityIcon(action) {
      const icons = {
        'Created': '➕',
        'Updated': '✏️',
        'Deleted': '🗑️',
        'Restored': '♻️',
        'Published': '📤',
        'Activated': '🟢',
        'Deactivated': '🔴',
        'Login': '🔐',
        'Logout': '🚪'
      }
      return icons[action] || '📝'
    },
    getActivityTypeClass(action) {
      const classes = {
        'created': 'activity-created',
        'updated': 'activity-updated',
        'deleted': 'activity-deleted',
        'restored': 'activity-restored',
        'published': 'activity-published',
        'published_building': 'activity-published',
        'published_map': 'activity-published',
        'published_deletion': 'activity-published',
        'published_restoration': 'activity-published',
        'activated': 'activity-activated',
        'deactivated': 'activity-deactivated',
        'login': 'activity-login',
        'logout': 'activity-logout'
      }
      return classes[action] || 'activity-default'
    },
    getFormattedAction(action) {
      const actions = {
        'created': 'Create',
        'updated': 'Update',
        'deleted': 'Delete',
        'restored': 'Restore',
        'published': 'Publish',
        'published_building': 'Publish',
        'published_map': 'Publish',
        'published_deletion': 'Publish',
        'published_restoration': 'Publish',
        'activated': 'Activated',
        'deactivated': 'Deactivated',
        'reverted': 'Revert',
        'login': 'Login',
        'logout': 'Logout'
      }
      return actions[action] || action.charAt(0).toUpperCase() + action.slice(1)
    },
    getSummaryDescription(log) {
      const { action, target_type, target_name } = log
      
      switch (action) {
        case 'created':
          return target_type === 'room' 
            ? `Added room: ${target_name}`
            : `Added ${target_type}: ${target_name}`
        case 'updated':
          return target_type === 'room'
            ? `Edited room: ${target_name}`
            : `Edited ${target_type}: ${target_name}`
        case 'deleted':
          return target_type === 'room'
            ? `Deleted room: ${target_name}`
            : `Deleted ${target_type}: ${target_name}`
        case 'restored':
          return `Restored ${target_type}: ${target_name}`
        case 'reverted':
          return target_type === 'room'
            ? `Reverted room: ${target_name}`
            : `Reverted ${target_type}: ${target_name}`
        case 'published':
          return target_type === 'room'
            ? `Published Room: ${target_name}`
            : `Published All Changes`
        case 'published_building':
          return `Published Building: ${target_name}`
        case 'published_map':
          return `Published Map: ${target_name}`
        case 'published_deletion':
          return `Published Deletion: ${target_name}`
        case 'published_restoration':
          return `Published Restoration: ${target_name}`
        case 'activated':
          return `Activated ${target_type}: ${target_name}`
        case 'deactivated':
          return `Deactivated ${target_type}: ${target_name}`
        default:
          return `${action} ${target_type}: ${target_name}`
      }
    },
    formatDetailKey(key) {
      const keys = {
        'map_id': 'Map ID',
        'employee_count': 'Employees',
        'building_id': 'Building ID',
        'map_name': 'Map Name',
        'building_name': 'Building Name',
        'coordinates': 'Coordinates',
        'dimensions': 'Dimensions',
        'total_published': 'Total Published',
        'published_buildings': 'Published Buildings',
        'published_maps': 'Published Maps'
      }
      return keys[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    },
    formatDetailValue(value) {
      if (typeof value === 'object' && value !== null) {
        return JSON.stringify(value)
      }
      if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No'
      }
      return value
    },
    // Safely normalize details: supports objects or JSON strings
    getDetailsObject(log) {
      const raw = log && log.details !== undefined ? log.details : null
      if (!raw) return {}
      if (typeof raw === 'object') return raw
      try {
        return JSON.parse(raw)
      } catch (_) {
        return { value: raw }
      }
    },
    // Extract common before/after shapes for edits
    getBeforeAfter(details) {
      const before = details.before || details.old || details.previous || details.prev || null
      const after = details.after || details.new || details.current || details.item || null
      return { before, after }
    },
    getActionSpecificContent(log) {
      const { action } = log
      
      switch (action) {
        case 'created':
          return this.getAddContent(log)
        case 'updated':
          return this.getEditContent(log)
        case 'deleted':
          return this.getDeleteContent(log)
        case 'restored':
          return this.getRestoreContent(log)
        case 'reverted':
          return this.getRevertContent(log)
        case 'published':
        case 'published_building':
        case 'published_map':
        case 'published_deletion':
        case 'published_restoration':
          return this.getPublishContent(log)
        default:
          return null
      }
    },

    getAddContent(log) {
      const { target_type, target_name } = log
      const details = this.getDetailsObject(log)
      let content = '<div class="detail-section">'
      // YAML-like header
      content += `<div class="detail-item"><span class="label">action:</span> <span class="value">Create</span></div>`
      content += `<div class="detail-item"><span class="label">target:</span> <span class="value">${this.formatTargetType(target_type)}</span></div>`
      content += `<div class="detail-item"><span class="label">name:</span> <span class="value">"${target_name}"</span></div>`

      if (target_type === 'room') {
        // Hide image path details for rooms as requested
        // if (details?.image) content += this.renderKV('  image', details.image)
      } else if (target_type === 'building') {
        const { after } = this.getBeforeAfter(details)
        const d = after || details || {}
        if (d.description) content += this.renderKV('  description', `"${d.description}"`)
        const employeeNames = Array.isArray(details.employees) ? this.extractEmployeeNames(details.employees) : this.extractEmployeeNames(d.employees)
        if (employeeNames.length) content += this.renderList('  employees', employeeNames)
        if (d.marker_image) content += this.renderKV('  marker', 'Added new marker on map')
        if (d.modal_image || d.building_image) content += this.renderKV('  modal', 'Created building modal with info')
        // include position and services for Add
        if (d.coordinates) content += this.renderKV('  position', `"${d.coordinates}"`)
        if (d.dimensions) content += this.renderKV('  size', `"${d.dimensions}"`)
        const services = Array.isArray(d.services) ? d.services : (typeof d.services === 'string' ? d.services.split(',').map(s => s.trim()).filter(Boolean) : [])
        if (services.length) content += this.renderList('  services', services)
      } else if (target_type === 'map') {
        const { after } = this.getBeforeAfter(details)
        const d = after || details || {}
        if (d.width) content += this.renderKV('  width', `${d.width}px`)
        if (d.height) content += this.renderKV('  height', `${d.height}px`)
        if (d.map_image) content += this.renderKV('  image_url', `"${d.map_image}"`)
      }
      
      content += '</div>'
      return content
    },

    getEditContent(log) {
      let content = '<div class="detail-section">'
      
      // YAML-like header
      content += `<div class="detail-item"><span class="label">action:</span> <span class="value">Update</span></div>`
      content += `<div class="detail-item"><span class="label">changes:</span> <span class="value"></span></div>`

      // For rooms, show only what actually changed
      const details = this.getDetailsObject(log)
      if (log.target_type === 'room') {
        if (details?.name) content += this.renderKV('  name', `"${details.name}"`)
        // Hide image path details for rooms as requested
        // if (details?.image) content += this.renderKV('  image', details.image)
      } else {
        // Try to get field changes from details (buildings/maps)
        const changes = this.getEditChanges(log)
        if (changes.length > 0) {
          changes.forEach(change => {
            content += this.renderKV(`  ${change.field}`, `"${change.old}" → "${change.new}"`)
          })
        }

        // Employees added/removed if present
        const empDiff = this.getEmployeeDiff(details)
        if (empDiff.added.length) content += this.renderList('  employees.added', empDiff.added)
        if (empDiff.removed.length) content += this.renderList('  employees.removed', empDiff.removed)

        // Services added/removed if present
        const svcDiff = this.getServiceDiff(details)
        if (svcDiff.added.length) content += this.renderList('  services.added', svcDiff.added)
        if (svcDiff.removed.length) content += this.renderList('  services.removed', svcDiff.removed)

        // Marker / Modal image change flags
        const { before, after } = this.getBeforeAfter(details)
        if (before && after) {
          const beforeMarker = before.marker_image || before.image_path || before.marker
          const afterMarker = after.marker_image || after.image_path || after.marker
          if (beforeMarker !== undefined && afterMarker !== undefined && String(beforeMarker) !== String(afterMarker)) {
            content += this.renderKV('  marker image changed', 'Yes')
          }
          const beforeModal = before.modal_image || before.modal_image_path
          const afterModal = after.modal_image || after.modal_image_path
          if (beforeModal !== undefined && afterModal !== undefined && String(beforeModal) !== String(afterModal)) {
            content += this.renderKV('  modal image changed', 'Yes')
          }
        }
      }

      // For rooms, we already showed name/image; for others, show fallback note if nothing
      if (log.target_type !== 'room') {
        const changes = this.getEditChanges(log)
        const details2 = this.getDetailsObject(log)
        const empDiff2 = this.getEmployeeDiff(details2)
        const svcDiff2 = this.getServiceDiff(details2)
        if (changes.length === 0 && !empDiff2.added.length && !empDiff2.removed.length && !svcDiff2.added.length && !svcDiff2.removed.length) {
          content += `<div class="detail-item"><span class="label">  note</span> <span class="value">Field changes not tracked</span></div>`
        }
      }
      
      content += '</div>'
      return content
    },

    getDeleteContent(log) {
      const { target_type, target_name } = log
      const details = this.getDetailsObject(log)
      let content = '<div class="detail-section">'
      
      // YAML-like header (no explicit "details" row for delete)
      content += `<div class="detail-item"><span class="label">action:</span> <span class="value">Delete</span></div>`
      content += `<div class="detail-item"><span class="label">target:</span> <span class="value">${this.formatTargetType(target_type)}</span></div>`
      content += `<div class="detail-item"><span class="label">name:</span> <span class="value">"${target_name}"</span></div>`

      if (target_type === 'building') {
        const d = details.before || details || {}
        if (d.description) content += this.renderKV('  description', `"${d.description}"`)
        const employeeNames = this.extractEmployeeNames(d.employees)
        if (employeeNames.length) content += this.renderList('  employees', employeeNames)
        if (d.coordinates) content += this.renderKV('  position', `"${d.coordinates}"`)
        // include services list on delete (parse string or array)
        const services = Array.isArray(d.services)
          ? d.services
          : (typeof d.services === 'string'
              ? d.services.split(',').map(s => s.trim()).filter(Boolean)
              : [])
        if (services.length) content += this.renderList('  services', services)
      } else if (target_type === 'map') {
        const d = details.before || details || {}
        if (d.width) content += this.renderKV('  width', `${d.width}px`)
        if (d.height) content += this.renderKV('  height', `${d.height}px`)
      }
      
      content += '</div>'
      return content
    },

    getRestoreContent(log) {
      const { target_type, target_name } = log
      let content = '<div class="detail-section">'
      
      if (target_type === 'building') {
        content += `<div class="detail-item"><span class="label">Building Name:</span> <span class="value">"${target_name}"</span></div>`
        content += `<div class="detail-item"><span class="label">Restored By:</span> <span class="value">${log.user_name || 'Admin'}</span></div>`
      } else if (target_type === 'map') {
        content += `<div class="detail-item"><span class="label">Map Name:</span> <span class="value">"${target_name}"</span></div>`
        content += `<div class="detail-item"><span class="label">Restored By:</span> <span class="value">${log.user_name || 'Admin'}</span></div>`
      }
      
      content += '</div>'
      return content
    },

    getRevertContent(log) {
      // For rooms, return null (no details) as requested
      if (log.target_type === 'room') {
        return null
      }
      
      // For other types, show basic revert info
      const { target_type, target_name } = log
      let content = '<div class="detail-section">'
      content += `<div class="detail-item"><span class="label">action:</span> <span class="value">Revert</span></div>`
      content += `<div class="detail-item"><span class="label">target:</span> <span class="value">${this.formatTargetType(target_type)}</span></div>`
      content += `<div class="detail-item"><span class="label">name:</span> <span class="value">"${target_name}"</span></div>`
      content += '</div>'
      return content
    },

    getPublishContent(log) {
      const { action } = log
      const details = this.getDetailsObject(log)
      let content = '<div class="detail-section">'
      
      // Room-specific single publish must be checked before the general 'published' summary
      if (action === 'published' && log.target_type === 'room') {
        // Single room publish
        if (log.target_name) content += `<div class="detail-item"><span class="label">Room:</span> <span class="value">"${log.target_name}"</span></div>`
        // Hide image path details for rooms as requested
        // if (details?.image) content += `<div class="detail-item"><span class="label">Image:</span> <span class="value">${details.image}</span></div>`
      } else if (action === 'published') {
        // Show summary counts
        if (details?.total_published) content += `<div class="detail-item"><span class="label">Total:</span> <span class="value">${details.total_published}</span></div>`
        if (details?.maps_published) content += `<div class="detail-item"><span class="label">Maps:</span> <span class="value">${details.maps_published}</span></div>`
        if (details?.buildings_published) content += `<div class="detail-item"><span class="label">Buildings:</span> <span class="value">${details.buildings_published}</span></div>`
        if (details?.buildings_deleted) content += `<div class="detail-item"><span class="label">Deleted:</span> <span class="value">${details.buildings_deleted}</span></div>`
        if (details?.buildings_restored) content += `<div class="detail-item"><span class="label">Restored:</span> <span class="value">${details.buildings_restored}</span></div>`
        
        // Show lists of affected items
        const lists = this.getPublishLists(log)
        if (lists.published_buildings.length) {
          content += `<div class="detail-item"><span class="label">Buildings Published:</span> <span class="value">[${lists.published_buildings.map(b => `"${b}"`).join(', ')}]</span></div>`
        }
        if (lists.deleted_buildings.length) {
          content += `<div class="detail-item"><span class="label">Buildings Deleted:</span> <span class="value">[${lists.deleted_buildings.map(b => `"${b}"`).join(', ')}]</span></div>`
        }
        if (lists.restored_buildings.length) {
          content += `<div class="detail-item"><span class="label">Buildings Restored:</span> <span class="value">[${lists.restored_buildings.map(b => `"${b}"`).join(', ')}]</span></div>`
        }
      } else if (action === 'published_building') {
        if (log.target_name) content += `<div class="detail-item"><span class="label">Building:</span> <span class="value">"${log.target_name}"</span></div>`
        if (details?.map_id) content += `<div class="detail-item"><span class="label">Map ID:</span> <span class="value">${details.map_id}</span></div>`
        if (details?.employee_count !== undefined) content += `<div class="detail-item"><span class="label">Employees:</span> <span class="value">${details.employee_count}</span></div>`
      } else if (action === 'published_map') {
        if (log.target_name) content += `<div class="detail-item"><span class="label">Map:</span> <span class="value">"${log.target_name}"</span></div>`
      } else if (action === 'published_deletion') {
        if (log.target_name) content += `<div class="detail-item"><span class="label">Deleted:</span> <span class="value">"${log.target_name}"</span></div>`
      } else if (action === 'published_restoration') {
        if (log.target_name) content += `<div class="detail-item"><span class="label">Restored:</span> <span class="value">"${log.target_name}"</span></div>`
      }
      
      content += '</div>'
      return content
    },

    // Helper methods
    formatTargetType(t) {
      if (!t) return 'Item'
      return t.charAt(0).toUpperCase() + t.slice(1)
    },
    renderKV(label, value) {
      return `<div class="detail-item"><span class="label">${label}:</span> <span class="value">${value}</span></div>`
    },
    renderList(label, items) {
      const list = Array.isArray(items) ? items : []
      const htmlList = `<ul class="value-list">${list.map(i => `<li>${i}</li>`).join('')}</ul>`
      return `<div class="detail-item"><span class="label">${label}:</span> <span class="value">${htmlList}</span></div>`
    },
    extractEmployeeNames(arr) {
      if (!Array.isArray(arr)) return []
      return arr.map(e => {
        if (typeof e === 'string') return e
        if (e && typeof e === 'object') {
          return e.name || e.employee_name || e.full_name || e.email || 'Employee'
        }
        return 'Employee'
      })
    },
    getEmployeeDiff(details) {
      const diff = { added: [], removed: [] }
      if (!details || typeof details !== 'object') return diff
      if (details.changes && details.changes.employees) {
        const c = details.changes.employees
        if (Array.isArray(c.added)) diff.added = this.extractEmployeeNames(c.added)
        if (Array.isArray(c.removed)) diff.removed = this.extractEmployeeNames(c.removed)
        return diff
      }
      // Support root-level employees: { employees: { added: [], removed: [] } }
      if (details.employees) {
        const e = details.employees
        if (Array.isArray(e.added)) diff.added = this.extractEmployeeNames(e.added)
        if (Array.isArray(e.removed)) diff.removed = this.extractEmployeeNames(e.removed)
        if (diff.added.length || diff.removed.length) return diff
      }
      const { before, after } = this.getBeforeAfter(details)
      if (Array.isArray(before?.employees) && Array.isArray(after?.employees)) {
        const beforeNames = new Set(this.extractEmployeeNames(before.employees))
        const afterNames = new Set(this.extractEmployeeNames(after.employees))
        diff.added = [...afterNames].filter(n => !beforeNames.has(n))
        diff.removed = [...beforeNames].filter(n => !afterNames.has(n))
      }
      return diff
    },
    getServiceDiff(details) {
      const diff = { added: [], removed: [] }
      if (!details || typeof details !== 'object') return diff
      // Prefer root-level services diff if present
      if (details.services && (Array.isArray(details.services.added) || Array.isArray(details.services.removed))) {
        const added = Array.isArray(details.services.added) ? details.services.added : []
        const removed = Array.isArray(details.services.removed) ? details.services.removed : []
        diff.added = added.map(s => typeof s === 'string' ? s : String(s))
        diff.removed = removed.map(s => typeof s === 'string' ? s : String(s))
        return diff
      }
      // Derive from before/after strings or arrays
      const normalize = (v) => {
        if (Array.isArray(v)) return v.map(x => String(x).trim()).filter(Boolean)
        if (typeof v === 'string') return v.split(',').map(x => x.trim()).filter(Boolean)
        return []
      }
      const { before, after } = this.getBeforeAfter(details)
      const beforeList = normalize(before?.services)
      const afterList = normalize(after?.services)
      diff.added = afterList.filter(x => !beforeList.includes(x))
      diff.removed = beforeList.filter(x => !afterList.includes(x))
      return diff
    },
    getEditChanges(log) {
      const details = this.getDetailsObject(log)
      const changes = []
      if (!details) return changes

      // Prefer explicit changes array
      if (Array.isArray(details.changes)) {
        details.changes.forEach(ch => {
          if (ch && ch.field) {
            changes.push({
              field: this.formatDetailKey(ch.field),
              old: this.formatDetailValue(ch.old ?? '[empty]'),
              new: this.formatDetailValue(ch.new ?? '[empty]')
            })
          }
        })
        return changes
      }

      // Derive from before/after objects if present
      if (details && typeof details === 'object') {
        const { before, after } = this.getBeforeAfter(details)
        if (before && after && typeof before === 'object' && typeof after === 'object') {
          const ignore = new Set(['id', 'created_at', 'updated_at', 'published_at'])
          const keys = new Set([...Object.keys(before), ...Object.keys(after)])
          keys.forEach(k => {
            const keyLower = String(k).toLowerCase()
            if (ignore.has(k) || keyLower.includes('image')) return
            const oldVal = before[k]
            const newVal = after[k]
            if ((typeof oldVal !== 'object') && (typeof newVal !== 'object') && JSON.stringify(oldVal) !== JSON.stringify(newVal)) {
              changes.push({
                field: this.formatDetailKey(k),
                old: this.formatDetailValue(oldVal ?? '[empty]'),
                new: this.formatDetailValue(newVal ?? '[empty]')
              })
            }
          })
        }
      }
      return changes
    },

    getPublishLists(log) {
      const d = log?.details || {}
      return {
        published_buildings: Array.isArray(d.published_buildings) ? d.published_buildings : [],
        deleted_buildings: Array.isArray(d.deleted_buildings) ? d.deleted_buildings : [],
        restored_buildings: Array.isArray(d.restored_buildings) ? d.restored_buildings : []
      }
    }
  }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

/* Activity Log Button */
.activity-log-button {
  position: fixed;
  bottom: 24px;
  left: 24px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  color: white;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  font-weight: 600;
  font-size: 14px;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
  transition: all 0.3s ease;
  z-index: 1000;
}

.activity-log-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
  background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
}

.activity-log-button.drawer-open {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.log-icon {
  font-size: 16px;
}

.log-text {
  font-size: 14px;
}

/* Drawer Overlay */
.drawer-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  z-index: 2000;
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Drawer Content */
.drawer-content {
  position: fixed;
  top: 0;
  right: 0;
  width: 400px;
  height: 100vh;
  background: white;
  box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  animation: slideInRight 0.3s ease-out;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
  }
  to {
    transform: translateX(0);
  }
}

/* Drawer Header */
.drawer-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
}

/* Filter Section */
.filter-section {
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
}

.filter-toggle {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
}

.filter-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: white;
  color: #6b7280;
  cursor: pointer;
  font-weight: 500;
  font-size: 14px;
  transition: all 0.2s ease;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.filter-btn:hover {
  border-color: #3b82f6;
  color: #3b82f6;
}

.filter-btn.active {
  background: #3b82f6;
  border-color: #3b82f6;
  color: white;
}

.filter-btn.active:hover {
  background: #2563eb;
  border-color: #2563eb;
}

.filter-icon {
  font-size: 14px;
}

.filter-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.filter-count {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.drawer-title {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #1f2937;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.drawer-close {
  background: none;
  border: none;
  font-size: 24px;
  color: #6b7280;
  cursor: pointer;
  padding: 8px;
  border-radius: 6px;
  transition: all 0.2s ease;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.drawer-close:hover {
  background: #e5e7eb;
  color: #374151;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
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

.loading-state p {
  margin: 0;
  font-size: 14px;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  text-align: center;
  color: #6b7280;
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.empty-state h3 {
  margin: 0 0 8px 0;
  font-size: 18px;
  font-weight: 600;
  color: #374151;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

.empty-state p {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

/* Activity List */
.activity-list {
  flex: 1;
  overflow-y: auto;
  padding: 16px 0;
}

.activity-entry {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px 24px;
  border-bottom: 1px solid #f3f4f6;
  transition: background-color 0.2s ease;
}

.activity-entry:hover {
  background: #f9fafb;
}

.activity-entry:last-child {
  border-bottom: none;
}

.activity-icon {
  font-size: 20px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: #f3f4f6;
  flex-shrink: 0;
}

.activity-content {
  flex: 1;
  min-width: 0;
}

/* Summary Display */
.activity-summary {
  margin-bottom: 8px;
}

.summary-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 6px;
}

.summary-description {
  font-weight: 600;
  font-size: 14px;
  color: #1f2937;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  flex: 1;
}

.view-details-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  color: #6b7280;
  transition: all 0.2s ease;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  flex-shrink: 0;
}

.view-details-btn:hover {
  background: #e5e7eb;
  border-color: #9ca3af;
  color: #374151;
}

.view-details-btn.expanded {
  background: #3b82f6;
  border-color: #3b82f6;
  color: white;
}

.view-details-btn.expanded:hover {
  background: #2563eb;
  border-color: #2563eb;
}

.details-text {
  font-weight: 500;
}

.details-icon {
  font-size: 10px;
  transition: transform 0.2s ease;
}

.summary-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #6b7280;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  flex-wrap: wrap;
}

.action-type {
  font-weight: 600;
  color: #3b82f6;
  background: #eff6ff;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 11px;
}

.admin-name {
  font-weight: 500;
  color: #374151;
}

.timestamp {
  font-weight: 400;
  color: #6b7280;
}

.ip-address {
  font-size: 11px;
  color: #9ca3af;
  font-family: monospace;
}

.meta-separator {
  color: #d1d5db;
}

/* Expanded Details Styles */
.expanded-details {
  margin-top: 12px;
  padding: 16px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  animation: slideDown 0.3s ease-out;
}

/* Action Content Styles */
.action-content .detail-section {
  background: white;
  border-radius: 6px;
  padding: 12px;
  border: 1px solid #e5e7eb;
}

.action-content .detail-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 6px 0;
  border-bottom: 1px solid #f3f4f6;
  gap: 12px;
}

.action-content .detail-item:last-child {
  border-bottom: none;
}

.action-content .detail-item .label {
  font-weight: 500;
  color: #374151;
  font-size: 12px;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  min-width: 100px;
  flex-shrink: 0;
}

.action-content .detail-item .value {
  color: #1f2937;
  font-weight: 400;
  font-size: 12px;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  text-align: right;
  word-break: break-word;
  flex: 1;
}

@keyframes slideDown {
  from {
    opacity: 0;
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
  }
  to {
    opacity: 1;
    max-height: 1000px;
    padding-top: 16px;
    padding-bottom: 16px;
  }
}

.details-section {
  margin-bottom: 20px;
}

.details-section:last-child {
  margin-bottom: 0;
}

.details-title {
  margin: 0 0 12px 0;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  display: flex;
  align-items: center;
  gap: 6px;
}

.details-grid {
  display: grid;
  gap: 8px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  border-bottom: 1px solid #f1f5f9;
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-row .detail-label {
  font-weight: 500;
  color: #64748b;
  font-size: 12px;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  min-width: 120px;
}

.detail-row .detail-value {
  color: #1e293b;
  font-weight: 400;
  font-size: 12px;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  text-align: right;
  word-break: break-word;
  max-width: 200px;
}

.detail-row .detail-value.user-agent {
  font-size: 10px;
  color: #64748b;
  max-width: 150px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Activity Type Colors */
.activity-created .activity-icon {
  background: #dcfce7;
  color: #16a34a;
}

.activity-updated .activity-icon {
  background: #fef3c7;
  color: #d97706;
}

.activity-deleted .activity-icon {
  background: #fee2e2;
  color: #dc2626;
}

.activity-restored .activity-icon {
  background: #dbeafe;
  color: #2563eb;
}

.activity-published .activity-icon {
  background: #e0e7ff;
  color: #7c3aed;
}

.activity-activated .activity-icon {
  background: #dcfce7;
  color: #16a34a;
}

.activity-deactivated .activity-icon {
  background: #fee2e2;
  color: #dc2626;
}

.activity-login .activity-icon {
  background: #dbeafe;
  color: #2563eb;
}

.activity-logout .activity-icon {
  background: #f3f4f6;
  color: #6b7280;
}

.activity-default .activity-icon {
  background: #f3f4f6;
  color: #6b7280;
}

/* Drawer Footer */
.drawer-footer {
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
}

.refresh-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  font-size: 14px;
  transition: all 0.2s ease;
  font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  width: 100%;
  justify-content: center;
}

.refresh-button:hover:not(:disabled) {
  background: #2563eb;
  transform: translateY(-1px);
}

.refresh-button:disabled {
  background: #9ca3af;
  cursor: not-allowed;
  transform: none;
}

.refresh-icon {
  font-size: 14px;
}

/* Responsive Design */
@media (max-width: 768px) {
  .drawer-content {
    width: 100%;
  }
  
  .activity-log-button {
    bottom: 16px;
    left: 16px;
    padding: 10px 14px;
  }
  
  .log-text {
    display: none;
  }
}
</style>
