/**
 * Arma el video explicativo de CumpleClick en sus DOS formatos:
 *   - vertical 1080x1920  → Instagram / TikTok / WhatsApp estado
 *   - horizontal 1920x1080 → WhatsApp directo, web, presentaciones
 *
 * Reemplaza a build-video.ps1, que tenía dos problemas:
 *   1) `$files += MakePngSeg ...` guardaba el texto que imprimía la función
 *      ("  seg0 ✓"), no la ruta del segmento, así que la lista de concat salía
 *      con basura en vez de archivos.
 *   2) Solo generaba el vertical; el horizontal (entregable G del ticket
 *      AT-CUMPLECLICK-008) nunca se llegó a hacer.
 *
 * Los pantallazos que entran acá ya deben estar corregidos con
 * `node scripts/fix-green-screen.mjs` (si no, se ve el verde de la cámara de
 * prueba justo en la toma de la foto).
 *
 * Uso:  node scripts/build-explicativo.mjs
 */
import { execSync } from 'node:child_process'
import { existsSync, mkdirSync, writeFileSync, rmSync, statSync } from 'node:fs'

const D = 'C:/wamp64/www/automatiza-tech/CumpleBooth/design'
const M = `${D}/video`
const S = `${D}/screens`
const O = `${D}/explicativo/overlays`
const TMP = `${process.env.TEMP}/cc-explicativo`
const FR = 25

// Guion del video. `ov` es el PNG de subtítulo (ya trae su banda de fondo).
const GUION = [
  { src: `${M}/campania-fase1/v1-clip-fiesta-detenida.mp4`, dur: 3.5, ov: 't01' },
  { src: `${M}/campania-fase1/v1-clip-fiesta-detenida.mp4`, dur: 3.5, ov: 't02' },
  // Antes era el clip 3D con la pantalla en blanco. Luis pidió que se vea el
  // logo real (no solo texto) y una foto real de niño en la pantalla del
  // kiosco — se compone la foto ya corregida de Bluey/Sofía sobre el render.
  { src: `${M}/frame-sala-kiosco-con-foto.png`, dur: 4.0, ov: 't03' },
  // Antes usaba screen-01-intro.png (la portada) — pero el texto dice "Cada
  // invitado toca su nombre", y la pantalla que muestra esos nombres es la
  // grilla de invitados, no la portada. Luis lo detectó: "no sale la pantalla
  // de la lista de invitado".
  { src: `${S}/screen-02-invitados.png`, dur: 4.5, ov: 't04' },
  { src: `${S}/screen-03-ruleta.png`, dur: 4.5, ov: 't05' },
  { src: `${S}/screen-04-personaje.png`, dur: 4.0, ov: 't06' },
  { src: `${M}/clip-02-flash.mp4`, dur: 3.5, ov: 't07' },
  { src: `${S}/screen-06-preview.png`, dur: 5.0, ov: 't08' },
  { src: `${S}/screen-07-qr.png`, dur: 4.5, ov: 't09' },
  { src: `${S}/screen-08-diploma.png`, dur: 4.5, ov: 't10' },
  { src: `${M}/campania-fase1/v3a-clip-sala-carreras.mp4`, dur: 3.0, ov: 't11' },
  { src: `${M}/campania-fase1/v3b-clip-sala-bluey.mp4`, dur: 3.0, ov: null },
  { src: `${M}/clip-03-endcard.mp4`, dur: 6.0, ov: 't12' },
]

// `sufijoOv` elige el juego de subtítulos: cada formato tiene el suyo, ya
// renderizado a su tamaño. Reusar los verticales en horizontal deformaba la
// tipografía (se estiraba a lo ancho al escalar 1080x1920 → 1920x1080).
const FORMATOS = [
  { nombre: 'vertical', w: 1080, h: 1920, sufijoOv: '', salida: `${D}/explicativo/video-explicativo.mp4` },
  { nombre: 'horizontal', w: 1920, h: 1080, sufijoOv: '-16x9', salida: `${D}/explicativo/video-explicativo-16x9.mp4` },
]

const sh = (cmd) => execSync(cmd, { stdio: ['ignore', 'pipe', 'pipe'], maxBuffer: 1e9 })
const esImagen = (p) => /\.(png|jpe?g)$/i.test(p)

function construir(fmt) {
  const { w, h, salida, nombre, sufijoOv } = fmt
  const tmp = `${TMP}/${nombre}`
  rmSync(tmp, { recursive: true, force: true })
  mkdirSync(tmp, { recursive: true })

  // El material nativo es vertical 9:16. Para el horizontal NO se recorta a
  // 16:9 (perderíamos la cabeza del niño y los botones del kiosco): se pone el
  // cuadro completo centrado sobre un fondo desenfocado del mismo cuadro, que
  // es lo que hace cualquier reencuadre decente de vertical a horizontal.
  const encuadre = w > h
    ? `[0:v]scale=${w}:${h}:force_original_aspect_ratio=increase,crop=${w}:${h},boxblur=28:2,eq=brightness=-0.06[bg];` +
      `[0:v]scale=-1:${h}[fg];[bg][fg]overlay=(W-w)/2:0,fps=${FR}`
    : `[0:v]scale=${w}:${h}:force_original_aspect_ratio=increase,crop=${w}:${h},fps=${FR}`

  const segmentos = []
  GUION.forEach((paso, i) => {
    if (!existsSync(paso.src)) {
      console.log(`  ! falta ${paso.src.split('/').pop()} — se omite`)
      return
    }
    const out = `${tmp}/seg${String(i).padStart(2, '0')}.mp4`
    const entrada = esImagen(paso.src)
      ? `-loop 1 -t ${paso.dur} -i "${paso.src}"`
      : `-t ${paso.dur} -i "${paso.src}"`

    // Subtítulo del formato correspondiente; si falta, cae al vertical.
    let ovPng = paso.ov ? `${O}/${paso.ov}${sufijoOv}.png` : null
    if (ovPng && !existsSync(ovPng)) ovPng = `${O}/${paso.ov}.png`
    let filtro
    let extra = ''
    if (ovPng && existsSync(ovPng)) {
      extra = `-loop 1 -t ${paso.dur} -i "${ovPng}"`
      filtro = `${encuadre}[base];[1:v]scale=${w}:${h}[ov];[base][ov]overlay=0:0:shortest=1`
    } else {
      filtro = encuadre
    }

    sh(`ffmpeg -y -v error ${entrada} ${extra} -filter_complex "${filtro}" ` +
       `-c:v libx264 -preset medium -crf 20 -pix_fmt yuv420p -r ${FR} -t ${paso.dur} "${out}"`)

    if (statSync(out).size < 1000) throw new Error(`segmento vacío: ${out}`)
    segmentos.push(out)
    process.stdout.write('.')
  })

  const lista = `${tmp}/concat.txt`
  writeFileSync(lista, segmentos.map((s) => `file '${s}'`).join('\n'), 'ascii')
  sh(`ffmpeg -y -v error -f concat -safe 0 -i "${lista}" ` +
     `-c:v libx264 -preset medium -crf 20 -pix_fmt yuv420p -movflags +faststart -r ${FR} "${salida}"`)

  const probe = sh(`ffprobe -v error -show_entries stream=codec_name,width,height,pix_fmt,r_frame_rate ` +
                   `-show_entries format=duration -of default=noprint_wrappers=1 "${salida}"`).toString().trim()
  console.log(`\n  ✓ ${nombre}\n${probe.split('\n').map((l) => '     ' + l).join('\n')}`)
  console.log(`     tamaño=${(statSync(salida).size / 1048576).toFixed(1)} MB`)
}

console.log(`Armando ${GUION.length} segmentos por formato...\n`)
for (const f of FORMATOS) {
  console.log(`${f.nombre} ${f.w}x${f.h}`)
  construir(f)
}
console.log('\nListo.')
