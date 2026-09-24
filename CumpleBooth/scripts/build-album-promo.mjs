/**
 * build-album-promo.mjs — arma el video promocional 9:16 del Álbum Recuerdo.
 *
 * Mismo patrón probado del explicativo (build-explicativo.mjs): segmentos
 * normalizados a 1080x1920/25fps + overlays de texto renderizados HTML→PNG
 * con la Baloo 2 real (NUNCA drawtext) + concat. 0 créditos de IA: el
 * contenido es la revista real grabada por record-album-promo.mjs.
 *
 * Diferencias de fuente:
 *  - El screencast de Chrome solo emite frames cuando la página SE PINTA: un
 *    clip queda más corto que la acción si el final es estático. Cada
 *    segmento se extiende con tpad=clone hasta su duración de guion.
 *  - Clips landscape/mobile van sobre fondo desenfocado de sí mismos.
 *  - Marca de agua AT arriba-izquierda en TODOS los segmentos: logo real
 *    (solo-logo.svg de AutomatizaTech), ~4% del ancho, opacidad ~0.42.
 *
 * Uso:  node scripts/build-album-promo.mjs
 */
import puppeteer from 'puppeteer-core'
import { execSync } from 'node:child_process'
import { existsSync, mkdirSync, writeFileSync, rmSync, statSync } from 'node:fs'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const RAIZ = 'C:/wamp64/www/automatiza-tech/CumpleBooth'
const D = `${RAIZ}/design/album-promo`
const CLIPS = `${D}/clips`
const OV = `${D}/overlays`
const TMP = `${process.env.TEMP}/cc-album-promo-build`
const SALIDA = `${D}/album-recuerdo-promo-9x16.mp4`
const AT_LOGO_SVG = 'C:/wamp64/www/automatiza-tech/wp-content/themes/automatiza-tech/assets/images/solo-logo.svg'
const CC_LOCKUP_SVG = `${RAIZ}/design/logo/cumpleclick-globo-lockup.svg`
const BALOO_800 = `${RAIZ}/node_modules/@fontsource/baloo-2/files/baloo-2-latin-800-normal.woff2`
const BALOO_700 = `${RAIZ}/node_modules/@fontsource/baloo-2/files/baloo-2-latin-700-normal.woff2`
const FR = 25
const W = 1080
const H = 1920

const sh = (cmd) => execSync(cmd, { stdio: ['ignore', 'pipe', 'pipe'], maxBuffer: 1e9 })
const duracionReal = (f) => Number(sh(`ffprobe -v error -show_entries format=duration -of csv=p=0 "${f}"`).toString().trim())

/* Guion. `desde` recorta el clip; `dur` es lo que dura en el video final
   (tpad rellena con el último frame si el screencast quedó corto).
   `marco`: full = cubre el cuadro; inset = centrado sobre fondo difuso. */
const GUION = [
  { clip: 'portrait-portada', desde: 0.0, dur: 3.5, marco: 'full', ov: 'o1' },
  { clip: 'portrait-portada', desde: 2.8, dur: 4.5, marco: 'full', ov: 'o2' },
  { clip: 'landscape-pliego', desde: 0.0, dur: 5.0, marco: 'inset', ov: 'o3' },
  { clip: 'portrait-nota', desde: 0.0, dur: 3.2, marco: 'full', ov: 'o4' },
  { clip: 'portrait-video', desde: 0.0, dur: 5.2, marco: 'full', ov: 'o5' },
  { clip: 'mobile-aviso', desde: 0.0, dur: 5.0, marco: 'inset-movil', ov: 'o6' },
  { clip: 'portrait-cierre', desde: 0.0, dur: 3.8, marco: 'full', ov: 'o7' },
  { endcard: true, dur: 6.0 },
]

/* Textos en pantalla — español de Chile, banda inferior como el explicativo
   (contraste garantizado sobre cualquier página). */
const TEXTOS = [
  { id: 'o1', text: 'Las fotos de la fiesta,<br>reunidas en una revista', size: 56, alto: 380 },
  { id: 'o2', text: 'Los invitados las suben<br>desde su propio celular', size: 56, alto: 380 },
  { id: 'o3', text: 'Se hojea como<br>una revista de verdad', size: 56, alto: 380 },
  { id: 'o4', text: 'También con mensajes<br>de cariño…', size: 56, alto: 380 },
  { id: 'o5', text: '…y hasta videos<br>con su saludo', size: 56, alto: 380 },
  { id: 'o6', text: 'En el celular: una página de pie,<br>pliego doble al girarlo', size: 48, alto: 380 },
  { id: 'o7', text: 'Un recuerdo guardado<br>para siempre', size: 56, alto: 380 },
]

async function renderPngs() {
  mkdirSync(OV, { recursive: true })
  const browser = await puppeteer.launch({
    executablePath: CHROME, headless: 'new',
    args: ['--allow-file-access-from-files'],
    defaultViewport: { width: W, height: H, deviceScaleFactor: 1 },
  })
  const page = await browser.newPage()
  const fuentes = `
@font-face{font-family:"Baloo 2";font-weight:800;font-display:block;src:url("file:///${BALOO_800}") format("woff2")}
@font-face{font-family:"Baloo 2";font-weight:700;font-display:block;src:url("file:///${BALOO_700}") format("woff2")}`

  // 1) Bandas de texto
  for (const ov of TEXTOS) {
    const html = `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><style>
${fuentes}
body{margin:0;width:${W}px;height:${H}px;display:flex;justify-content:center;align-items:flex-end;background:transparent;font-family:"Baloo 2",system-ui}
.t{width:100%;height:${ov.alto}px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;text-align:center;color:#fff;font-weight:800;font-size:${ov.size}px;line-height:1.2;padding:0 64px 34px;
background:linear-gradient(to bottom,rgba(76,40,130,0) 0%,rgba(76,40,130,.72) 34%,rgba(76,40,130,.94) 62%,rgba(76,40,130,.97) 100%);}
</style></head><body><div class="t">${ov.text}</div></body></html>`
    writeFileSync(`${OV}/${ov.id}.html`, html)
    await page.goto(`file:///${OV}/${ov.id}.html`, { waitUntil: 'networkidle0', timeout: 15000 })
    await page.screenshot({ path: `${OV}/${ov.id}.png`, type: 'png', omitBackground: true })
    process.stdout.write('.')
  }

  // 2) Endcard: portada final de marca.
  const endcard = `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><style>
${fuentes}
body{margin:0;width:${W}px;height:${H}px;background:linear-gradient(165deg,#3b1d5e 0%,#26123f 55%,#8B5CF6 150%);font-family:"Baloo 2",system-ui;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}
img.lockup{width:340px;filter:drop-shadow(0 10px 30px rgba(0,0,0,.35))}
h1{margin:.55em 0 0;font-size:92px;font-weight:800;line-height:1.02}
p.nuevo{margin:26px 0 0;font-size:44px;font-weight:700;color:#FBBF24;letter-spacing:.14em;text-transform:uppercase}
p.cta{margin:60px 0 0;font-size:46px;font-weight:700;line-height:1.3;max-width:840px}
p.url{margin:34px 0 0;font-size:38px;font-weight:800;color:#FBBF24}
p.at{margin:80px 0 0;font-size:30px;font-weight:700;opacity:.75}
</style></head><body>
<img class="lockup" src="file:///${CC_LOCKUP_SVG}" alt="CumpleClick">
<p class="nuevo">Nuevo servicio</p>
<h1>Álbum Recuerdo</h1>
<p class="cta">Pregunta por él en tu próxima fiesta</p>
<p class="url">automatizatech.cl/cumpleclick</p>
<p class="at">CumpleClick · un servicio de AutomatizaTech</p>
</body></html>`
  writeFileSync(`${OV}/endcard.html`, endcard)
  await page.goto(`file:///${OV}/endcard.html`, { waitUntil: 'networkidle0', timeout: 15000 })
  await page.screenshot({ path: `${OV}/endcard.png`, type: 'png' })
  process.stdout.write('.')

  // 3) Marca de agua AT: el isotipo real, nítido (se escala a 44px en ffmpeg).
  const wm = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{margin:0;background:transparent}img{width:176px;height:176px;object-fit:contain}</style></head><body><img src="file:///${AT_LOGO_SVG}"></body></html>`
  writeFileSync(`${OV}/at-watermark.html`, wm)
  await page.setViewport({ width: 220, height: 220, deviceScaleFactor: 1 })
  await page.goto(`file:///${OV}/at-watermark.html`, { waitUntil: 'networkidle0', timeout: 15000 })
  const img = await page.$('img')
  await img.screenshot({ path: `${OV}/at-watermark.png`, type: 'png', omitBackground: true })
  process.stdout.write('.')
  await browser.close()
  console.log(' PNGs listos')
}

/** Filtro de encuadre por tipo de clip. Devuelve la cadena que produce [v0]. */
function filtroBase(marco) {
  if (marco === 'full') {
    return `[0:v]scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H}[v0]`
  }
  if (marco === 'inset') { // landscape: ancho casi completo, centrado en alto
    return `[0:v]scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H},boxblur=28:2,eq=brightness=-0.08[bg];` +
      `[0:v]scale=1008:-2[fg];[bg][fg]overlay=(W-w)/2:(H-h)/2[v0]`
  }
  // inset-movil: alto casi completo, centrado en ancho
  return `[0:v]scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H},boxblur=28:2,eq=brightness=-0.08[bg];` +
    `[0:v]scale=-2:1896[fg];[bg][fg]overlay=(W-w)/2:(H-h)/2[v0]`
}

function construir() {
  rmSync(TMP, { recursive: true, force: true })
  mkdirSync(TMP, { recursive: true })
  const wmPng = `${OV}/at-watermark.png`
  const segmentos = []

  GUION.forEach((paso, i) => {
    const out = `${TMP}/seg${String(i).padStart(2, '0')}.mp4`
    const etiqueta = paso.endcard ? 'endcard' : paso.clip
    let entrada
    let filtro
    if (paso.endcard) {
      entrada = `-loop 1 -t ${paso.dur} -i "${OV}/endcard.png"`
      filtro = `[0:v]fps=${FR},setsar=1[v0]`
    } else {
      const src = `${CLIPS}/${paso.clip}.mp4`
      if (!existsSync(src)) throw new Error(`falta el clip ${paso.clip}.mp4 — corre record-album-promo.mjs primero`)
      const real = duracionReal(src)
      const disponible = Math.max(0, real - paso.desde)
      const pad = Math.max(0, paso.dur - disponible)
      entrada = `-ss ${paso.desde} -t ${Math.min(disponible, paso.dur)} -i "${src}"`
      // fps primero para que tpad cuadre; el último frame se clona si el
      // screencast no alcanzó (final estático de la acción grabada).
      filtro = `${filtroBase(paso.marco)};[v0]fps=${FR}${pad > 0.05 ? `,tpad=stop_mode=clone:stop=${pad.toFixed(2)}` : ''},setsar=1[v0f]`
      filtro = filtro.replace('[v0];[v0]', '[v0X];[v0X]').replace('[v0f]', '[v0]')
    }

    let n = 1 // siguiente índice de input
    let ultimo = 'v0'
    if (paso.ov) {
      filtro += `;[${ultimo}][${n}:v]overlay=0:0:shortest=1[vo]`
      entrada += ` -loop 1 -t ${paso.dur} -i "${OV}/${paso.ov}.png"`
      ultimo = 'vo'
      n++
    }
    // Marca de agua AT en todos los segmentos, endcard incluido: ~4% del
    // ancho del cuadro, translúcida, esquina superior izquierda.
    filtro += `;[${n}:v]scale=44:44,format=rgba,colorchannelmixer=aa=0.42[wm];[${ultimo}][wm]overlay=36:36[vf]`
    entrada += ` -loop 1 -t ${paso.dur} -i "${wmPng}"`
    // El JPEG del screencast es rango completo: sin esta conversión x264
    // etiqueta yuvj420p y el estándar del proyecto es yuv420p (rango TV).
    filtro += `;[vf]scale=in_range=auto:out_range=tv[vout]`

    sh(`ffmpeg -y -v error ${entrada} -filter_complex "${filtro}" ` +
       `-map "[vout]" -c:v libx264 -preset medium -crf 20 -pix_fmt yuv420p -color_range tv -r ${FR} -t ${paso.dur} "${out}"`)
    if (statSync(out).size < 1000) throw new Error(`segmento vacío: ${etiqueta}`)
    segmentos.push(out)
    process.stdout.write('.')
  })

  const lista = `${TMP}/concat.txt`
  writeFileSync(lista, segmentos.map((s) => `file '${s}'`).join('\n'), 'ascii')
  sh(`ffmpeg -y -v error -f concat -safe 0 -i "${lista}" ` +
     `-c:v libx264 -preset medium -crf 20 -pix_fmt yuv420p -movflags +faststart -r ${FR} "${SALIDA}"`)

  const probe = sh(`ffprobe -v error -show_entries stream=codec_name,width,height,pix_fmt,r_frame_rate ` +
                   `-show_entries format=duration -of default=noprint_wrappers=1 "${SALIDA}"`).toString().trim()
  console.log(`\n✓ ${SALIDA.split('/').pop()}\n${probe.split('\n').map((l) => '   ' + l).join('\n')}`)
  console.log(`   tamaño=${(statSync(SALIDA).size / 1048576).toFixed(1)} MB`)
}

async function main() {
  console.log('Renderizando textos, endcard y marca de agua…')
  await renderPngs()
  console.log('Armando segmentos…')
  construir()
  console.log('\nListo.')
}
main().catch((e) => { console.error('Fatal:', e.message); process.exit(1) })
