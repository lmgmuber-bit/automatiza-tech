import React from 'react'
import { createRoot } from 'react-dom/client'
import App from './App.jsx'
import { ensureCanvasFonts } from './fonts.js'
import './styles.css'

// La fuente se empaqueta con Vite y se precarga al arrancar. Los compositores
// vuelven a esperar esta misma promesa antes de dibujar texto en canvas.
ensureCanvasFonts()

createRoot(document.getElementById('root')).render(<App />)
