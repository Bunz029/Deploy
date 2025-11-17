const { defineConfig } = require('@vue/cli-service')
module.exports = defineConfig({
  transpileDependencies: true,
  devServer: {
    // Disable WebSocket connections for hot reload to prevent connection errors
    webSocketServer: false,
    hot: false,
    liveReload: false,
    // Increase client-side upload limits
    client: {
      webSocketURL: 'auto://0.0.0.0:0/ws'
    }
  }
})
