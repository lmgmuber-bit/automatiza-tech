import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { resolve } from 'node:path'
import { readFileSync } from 'node:fs'

// Fuente bajo public/ por el reparto 020/021; salida compilada junto a index.html.
function feriaEntry() {
  const model = resolve(__dirname, 'src/feria/models/selfie_segmenter.tflite')
  const sdk = resolve(__dirname, 'node_modules/@mediapipe/tasks-vision/vision_bundle.cjs')
  return {
    name: 'feria-entry',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        const path = req.url?.split('?')[0]
        if (path === '/feria.html') req.url = '/public' + req.url
        if (path === '/vendor/mediapipe/selfie_segmenter.tflite' || path === '/feria/vision-segmenter.js') {
          res.setHeader('Content-Type', path.endsWith('.js') ? 'text/javascript' : 'application/octet-stream')
          res.end(readFileSync(path.endsWith('.js') ? sdk : model))
          return
        }
        next()
      })
    },
    generateBundle: { order: 'post', handler(_options, bundle) {
      const html = bundle['public/feria.html']
      if (html) {
        html.fileName = 'feria.html'
        html.source = String(html.source).replaceAll('../assets/', './assets/')
        delete bundle['public/feria.html']; bundle['feria.html'] = html
      }
      this.emitFile({ type: 'asset', fileName: 'vendor/mediapipe/selfie_segmenter.tflite', source: readFileSync(model) })
      this.emitFile({ type: 'asset', fileName: 'feria/vision-segmenter.js', source: readFileSync(sdk) })
    } },
  }
}

// Base RELATIVA: funciona en WAMP, Hostinger y subcarpetas.
export default defineConfig({
  base: './',
  plugins: [react(), feriaEntry()],
  build: {
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'index.html'),
        album: resolve(__dirname, 'album.html'),
        cartel: resolve(__dirname, 'cartel-qr.html'),
        carteles: resolve(__dirname, 'carteles.html'),
        feria: resolve(__dirname, 'public/feria.html'),
      },
    },
  },
  server: {
    host: true,
    port: 5173,
    proxy: {
      '/api.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
      '/feria-api.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
      '/upload.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
      '/album-api.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
      '/ver-media.php': { target: 'http://localhost/automatiza-tech/CumpleBooth/dist', changeOrigin: true },
    },
  },
})
