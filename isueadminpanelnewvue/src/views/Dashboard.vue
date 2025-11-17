<template>
  <div class="dashboard">
    <h1>Dashboard</h1>
    
    <div class="stats-grid">
      <div class="stat-card">
        <h3>Active Maps</h3>
        <p class="stat-value">{{ stats.activeMaps }}</p>
      </div>
      <div class="stat-card">
        <h3>Total Buildings</h3>
        <p class="stat-value">{{ stats.totalBuildings }}</p>
      </div>
      <div class="stat-card">
        <h3>Active Buildings</h3>
        <p class="stat-value">{{ stats.activeBuildings }}</p>
      </div>
    </div>

    <div class="quick-actions">
      <h2>Quick Actions</h2>
      <div class="action-buttons">
        <router-link to="/maps" class="action-button">
          <span class="icon">🗺️</span>
          Manage Maps
        </router-link>
        <router-link to="/buildings" class="action-button">
          <span class="icon">🏢</span>
          Manage Buildings
        </router-link>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'DashboardView',
  data() {
    return {
      stats: {
        activeMaps: 0,
        totalBuildings: 0,
        activeBuildings: 0
      }
    }
  },
  async created() {
    try {
      const response = await axios.get('/stats')
      this.stats = response.data
    } catch (error) {
      console.error('Error fetching stats:', error)
    }
  }
}
</script>

<style scoped>
.dashboard {
  max-width: 1200px;
  margin: 0 auto;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin: 2rem 0;
}

.stat-card {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stat-card h3 {
  margin: 0;
  color: #666;
  font-size: 1rem;
}

.stat-value {
  font-size: 2rem;
  font-weight: bold;
  color: #2c3e50;
  margin: 0.5rem 0 0;
}

.quick-actions {
  margin-top: 3rem;
}

.action-buttons {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.action-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 1rem;
  background: #2c3e50;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  transition: background-color 0.3s;
}

.action-button:hover {
  background: #34495e;
}

.icon {
  font-size: 1.5rem;
}
</style> 