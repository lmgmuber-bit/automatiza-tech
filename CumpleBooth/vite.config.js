import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { resolve } from 'node:path'

// Base RELATIVA: funciona en cualquier carpeta (WAMP local, Hostinger, subcarpetas)
// sin importar el nombre ni la profundidad de la ruta.
export default defineConfig({
  base: './',
  plugins: [react()],
  build: {
    rollupOptions: {
      // Dos entradas separadas a propósito: la tablet del kiosco no descarga
      // el código del Álbum Recuerdo, y quien abre el álbum desde su celular
      // no descarga los mundos 3D del kiosco (three.js pesa 734 kB).
      input: {
        main: resolve(__dirname, 'index.html'),
        album: resolve(__dirname, 'album.html'),
      },
    },
  },
  server: {
    host: true, // permite abrir desde la tablet por IP en la misma WiFi (dev)
    port: 5173,
    // Vite no ejecuta PHP: sin esto el dev server queda sin datos de fiesta y
    // el kiosco arranca en la pantalla de error. Se delegan los endpoints PHP
    // al WAMP local (solo desarrollo; en producción todo va servido junto).
    proxy: {
      '/api.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
      '/upload.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
      '/album-api.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
      '/ver-media.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
    },
  },
})
