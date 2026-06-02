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
      // Dev: proxa las llamadas al backend PHP servido por Apache (WAMP :80).
      // La app usa base '/automatiza-tech/...'. Cambio local de dev (no commitear sin querer).
      '/automatiza-tech': {
        target: 'http://localhost',
        changeOrigin: true,
        cookieDomainRewrite: 'localhost',
      },
      '/api-omnichannel.php': {
        target: 'http://localhost/automatiza-tech',
        changeOrigin: true,
        cookieDomainRewrite: 'localhost',
      }
    }
  }
})
