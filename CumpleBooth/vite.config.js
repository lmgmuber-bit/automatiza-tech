import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Base RELATIVA: funciona en cualquier carpeta (WAMP local, Hostinger, subcarpetas)
// sin importar el nombre ni la profundidad de la ruta.
export default defineConfig({
  base: './',
  plugins: [react()],
  server: {
    host: true, // permite abrir desde la tablet por IP en la misma WiFi (dev)
    port: 5173,
    // Vite no ejecuta PHP: sin esto el dev server queda sin datos de fiesta y
    // el kiosco arranca en la pantalla de error. Se delegan los endpoints PHP
    // al WAMP local (solo desarrollo; en producción todo va servido junto).
    proxy: {
      '/api.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
      '/upload.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
    },
  },
})
