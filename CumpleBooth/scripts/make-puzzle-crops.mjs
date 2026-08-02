/**
 * Genera los `puzzle-<personaje>.jpg` de una temática recortando un cuadrado
 * de la foto ya aprobada de cada personaje. Mismo criterio que se usó en
 * Reino de Hielo: lado = 83% del ancho, centrado en X y arrancando al 13% de
 * la altura, que es donde queda la cara/torso en estos encuadres verticales.
 * Proporcional, no en píxeles fijos: las fotos miden distinto según la tanda
 * en que se generaron (720x1280, 768x1344 y 1080x1920 conviven hoy).
 *
 * El 13% sirve para retratos (Bluey, Stitch, Frozen), donde la cara va arriba.
 * En Carreras no: son escenas amplias con el auto en la mitad inferior, y con
 * el valor por defecto el recorte salía con puros globos y trofeos. Por eso el
 * punto vertical es configurable con `--top`.
 *
 * Uso:  node scripts/make-puzzle-crops.mjs <tema> [--top=0.48] [--apply]
 */
import { execSync } from 'node:child_process'
import { existsSync, readFileSync } from 'node:fs'

const tema = process.argv[2]
const aplicar = process.argv.includes('--apply')
if (!tema) { console.error('Falta el tema. Ej: node scripts/make-puzzle-crops.mjs carreras --apply'); process.exit(1) }

const themes = JSON.parse(readFileSync('public/data/themes.json', 'utf8')).themes
const th = themes[tema]
if (!th) { console.error(`Temática desconocida: ${tema}`); process.exit(1) }

const sh = (c) => execSync(c, { stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim()
const LADO_REL = 0.83
const argTop = process.argv.find((a) => a.startsWith('--top='))
const TOP_REL = argTop ? parseFloat(argTop.split('=')[1]) : 0.13

for (const p of th.personajes) {
  const src = `public/themes/${tema}/${p.img}`
  if (!existsSync(src)) { console.log(`  ! falta ${p.img}`); continue }
  const [w, h] = sh(`ffprobe -v error -show_entries stream=width,height -of csv=p=0:s=x "${src}"`)
    .split('x').map(Number)

  let lado = Math.round(w * LADO_REL); lado -= lado % 2
  const x = Math.round((w - lado) / 2)
  // Si el recorte se pasa del alto, se pega al borde inferior en vez de fallar.
  const y = Math.min(Math.round(h * TOP_REL), Math.max(0, h - lado))

  const dst = `public/themes/${tema}/puzzle-${p.img.replace(/\.jpg$/i, '')}.jpg`
  console.log(`  ${p.name.padEnd(18)} ${p.img.padEnd(20)} ${w}x${h} → ${lado}x${lado} en (${x},${y})`)
  if (aplicar) {
    sh(`ffmpeg -y -v error -i "${src}" -vf "crop=${lado}:${lado}:${x}:${y},scale=900:900" -q:v 3 "${dst}"`)
  }
}
console.log(aplicar ? '\nGenerados.' : '\nDry-run. Usa --apply para escribir.')
