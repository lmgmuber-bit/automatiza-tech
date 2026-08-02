/**
 * Reemplaza el cuadro verde de la cámara de prueba de Chrome por una foto real
 * en los pantallazos del kiosco.
 *
 * Por qué existe: los pantallazos se capturaron con
 * `--use-fake-device-for-media-stream`, que alimenta un patrón verde sólido en
 * vez de una cámara. En la pantalla de resultado eso deja un rectángulo verde
 * justo donde va la foto del niño — que es LA toma que vende el producto.
 *
 * Detecta la caja verde en cada imagen (no está en la misma posición en todas
 * las temáticas, cada una tiene su frameBox), recorta la foto a esa proporción
 * y la compone encima. El resto del pantallazo queda intacto: sigue siendo una
 * captura real del kiosco.
 *
 * Uso:  node scripts/fix-green-screen.mjs
 */
import { execSync } from 'node:child_process'
import { existsSync, mkdirSync, copyFileSync } from 'node:fs'
import path from 'node:path'

const RAIZ = 'C:/wamp64/www/automatiza-tech/CumpleBooth'
const SCREENS = `${RAIZ}/design/screens`
const FOTO = `${RAIZ}/design/explicativo/ia/IMG-08-nino-camara-limpio.png`
const RESPALDO = `${SCREENS}/_con-verde`

// La cámara falsa de Chrome no pinta UN verde plano: genera un degradado que va
// de #008800 a tonos bastante más oscuros (#104c00 medido en la franja del pie).
// Por eso no alcanza con keyear un color puntual — se detecta la FAMILIA verde:
// el canal G domina claramente sobre R y B, y hay algo de luz.
// El césped de la temática Bluey no cumple: es un verde mucho menos saturado,
// con R alto (≈ #7CB342), así que la condición de dominancia lo deja fuera.
const ES_VERDE = 'gt(g(X,Y),40)*gt(g(X,Y),r(X,Y)*1.8)*gt(g(X,Y),b(X,Y)*1.8)'

const sh = (cmd) => execSync(cmd, { maxBuffer: 1e9 })

/** Devuelve {x,y,w,h} de la caja verde, o null si la imagen no tiene. */
function ubicarVerde(archivo, W, H) {
  // 768 y no 384: con la máscara chica la caja salía más baja de lo real y
  // quedaba una franja verde sin tapar debajo de la foto.
  const mw = 768
  const mh = Math.round((H / W) * mw)
  const tmp = `${process.env.TEMP}/mask-verde.png`
  const geq = `geq=r='if(${ES_VERDE},255,0)':g='if(${ES_VERDE},255,0)':b='if(${ES_VERDE},255,0)'`
  sh(`ffmpeg -v error -y -i "${archivo}" -vf "${geq},scale=${mw}:${mh}" -frames:v 1 -update 1 "${tmp}"`)
  const buf = sh(`ffmpeg -v error -i "${tmp}" -vf format=gray -f rawvideo -`)

  // Se busca la REGIÓN CONECTADA más grande, no el rectángulo que abarca todos
  // los píxeles verdes sueltos. En la temática tropical hay verdes dispersos
  // (hojas de palmera) que, metidos en la misma caja, la estiraban de 250px a
  // 1100px: la foto se escalaba para cubrir ese alto y en el hueco real solo
  // se veían los ojos del niño.
  const visto = new Uint8Array(mw * mh)
  let mejor = null

  for (let sy = 0; sy < mh; sy++) {
    for (let sx = 0; sx < mw; sx++) {
      const s = sy * mw + sx
      if (visto[s] || buf[s] <= 128) continue

      // Recorrido en anchura sobre la componente.
      let x0 = sx, y0 = sy, x1 = sx, y1 = sy, n = 0
      const cola = [s]
      visto[s] = 1
      while (cola.length) {
        const p = cola.pop()
        const px = p % mw
        const py = (p - px) / mw
        n++
        if (px < x0) x0 = px
        if (px > x1) x1 = px
        if (py < y0) y0 = py
        if (py > y1) y1 = py
        if (px > 0) { const q = p - 1; if (!visto[q] && buf[q] > 128) { visto[q] = 1; cola.push(q) } }
        if (px < mw - 1) { const q = p + 1; if (!visto[q] && buf[q] > 128) { visto[q] = 1; cola.push(q) } }
        if (py > 0) { const q = p - mw; if (!visto[q] && buf[q] > 128) { visto[q] = 1; cola.push(q) } }
        if (py < mh - 1) { const q = p + mw; if (!visto[q] && buf[q] > 128) { visto[q] = 1; cola.push(q) } }
      }
      if (!mejor || n > mejor.n) mejor = { x0, y0, x1, y1, n }
    }
  }

  // Menos de ~800px de máscara = ruido suelto, no el cuadro de la foto.
  if (!mejor || mejor.n < 800) return null

  const k = W / mw
  return {
    x: Math.round(mejor.x0 * k),
    y: Math.round(mejor.y0 * k),
    w: Math.round((mejor.x1 - mejor.x0 + 1) * k),
    h: Math.round((mejor.y1 - mejor.y0 + 1) * k),
    px: mejor.n,
  }
}

function dimensiones(archivo) {
  const out = sh(`ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 "${archivo}"`)
    .toString().trim().split(',')
  return { W: +out[0], H: +out[1] }
}

function main() {
  if (!existsSync(FOTO)) {
    console.error('Falta la foto de reemplazo:', FOTO)
    process.exit(1)
  }
  mkdirSync(RESPALDO, { recursive: true })

  const archivos = sh(`ls "${SCREENS}"/*.png`).toString().trim().split('\n').filter(Boolean)
  let tocados = 0

  for (const f of archivos) {
    const nombre = path.basename(f)
    const { W, H } = dimensiones(f)
    const caja = ubicarVerde(f, W, H)
    if (!caja) continue

    // Respaldo del original ANTES de tocarlo (una sola vez).
    const bak = `${RESPALDO}/${nombre}`
    if (!existsSync(bak)) copyFileSync(f, bak)

    // Se agranda la caja un poco: la detección puede quedar 1-2px corta y
    // dejaría un borde verde visible alrededor de la foto.
    const m = Math.ceil(Math.max(caja.w, caja.h) * 0.02)
    const bx = Math.max(0, caja.x - m)
    const by = Math.max(0, caja.y - m)
    const bw = Math.min(W - bx, caja.w + m * 2)
    const bh = Math.min(H - by, caja.h + m * 2)

    // Dos pasadas:
    //   1) la foto tapa toda la caja,
    //   2) encima se vuelve a poner el pantallazo con SOLO el verde recortado.
    // Así los botones, textos y marcos que estaban sobre el verde (el botón de
    // disparo, el rótulo "¡Sonríe!") siguen nítidos en su lugar, en vez de
    // quedar borrados por la foto.
    // El recorte del verde usa la MISMA condición que la detección (familia
    // verde, no un color puntual): `colorkey` solo borra un tono y dejaba
    // franjas del degradado sin tapar.
    const filtro =
      `[1:v]scale=${bw}:${bh}:force_original_aspect_ratio=increase,crop=${bw}:${bh}[foto];` +
      `[0:v][foto]overlay=${bx}:${by}[base];` +
      `[2:v]format=rgba,geq=r='r(X,Y)':g='g(X,Y)':b='b(X,Y)':a='if(${ES_VERDE},0,255)'[key];` +
      `[base][key]overlay=0:0`
    sh(`ffmpeg -v error -y -i "${bak}" -i "${FOTO}" -i "${bak}" -filter_complex "${filtro}" -frames:v 1 -update 1 "${f}"`)

    console.log(`  ✓ ${nombre}  caja ${caja.w}x${caja.h} en (${caja.x},${caja.y})`)
    tocados++
  }

  console.log(`\n${tocados} pantallazos corregidos. Originales en design/screens/_con-verde/`)
}

main()
