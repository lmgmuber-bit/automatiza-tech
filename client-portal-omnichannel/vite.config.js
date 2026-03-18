import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    outDir: 'dist',
  },
  server: {
    port: 5173,
    proxy: {
      '/api-omnichannel.php': {
        target: 'http://localhost/automatiza-tech',
        changeOrigin: true,
        cookieDomainRewrite: 'localhost',
      }
    }
  }
})
