import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    outDir: 'dist',
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('node_modules')) {
            if (id.includes('react-dom') || id.includes('/react/') || id.includes('scheduler')) return 'react-vendor';
            if (id.includes('lucide-react')) return 'icons';
            return 'vendor';
          }
        },
      },
    },
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
