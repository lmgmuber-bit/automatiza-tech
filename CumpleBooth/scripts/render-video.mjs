import puppeteer from 'puppeteer-core'
import { execSync } from 'child_process'
import { mkdirSync, writeFileSync } from 'fs'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const DESIGN = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design'
const OVERLAYS = `${DESIGN}\\explicativo\\overlays`
const SCREENS = `${DESIGN}\\screens`
const OUT = `${DESIGN}\\explicativo`
const M = `${DESIGN}\\video`

mkdirSync(OVERLAYS, { recursive: true })

const sleep = (ms) => new Promise(r => setTimeout(r, ms))

const overlays = [
  { id: 't01', text: 'En cada cumpleaños<br>pasa lo mismo…', size: 72 },
  { id: 't02', text: '20 niños.<br>Cero fotos de ellos.', size: 72 },
  { id: 't03', text: 'CumpleClick', size: 90, color: '#FBBF24' },
  { id: 't04', text: '1. Cada invitado toca su nombre', size: 46 },
  { id: 't05', text: '2. La ruleta elige su personaje', size: 46 },
  { id: 't06', text: 'Y sale a recibirlo<br>por su nombre', size: 46 },
  { id: 't07', text: '3. ¡Click!', size: 72 },
  { id: 't08', text: 'Su foto, con el marco<br>de la temática', size: 46 },
  { id: 't09', text: '4. Se la lleva al celular.<br>Y su diploma.', size: 46 },
  { id: 't10', text: 'Los papás reciben<br>el álbum completo', size: 46 },
  { id: 't11', text: '11 temáticas<br>para elegir', size: 72 },
  { id: 't12', text: 'Agenda tu fecha 📲', size: 46 },
]

async function renderOverlays(browser) {
  const html = (id, text, size, color = 'white') => `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><style>
    @font-face{font-family:"Baloo 2";font-weight:800;font-display:block;src:url("file:///C:/wamp64/www/automatiza-tech/CumpleBooth/node_modules/@fontsource/baloo-2/files/baloo-2-latin-800-normal.woff2") format("woff2")}
    @font-face{font-family:"Baloo 2";font-weight:600;font-display:block;src:url("file:///C:/wamp64/www/automatiza-tech/CumpleBooth/node_modules/@fontsource/baloo-2/files/baloo-2-latin-600-normal.woff2") format("woff2")}
    body{margin:0;width:1080px;height:1920px;display:flex;align-items:flex-end;justify-content:center;background:transparent;font-family:"Baloo 2",system-ui;}
    .t{text-align:center;color:${color};font-weight:800;font-size:${size}px;line-height:1.15;margin-bottom:160px;text-shadow:0 3px 12px rgba(0,0,0,.55),0 1px 4px rgba(0,0,0,.4);padding:0 60px;}
  </style></head><body><div class="t">${text}</div></body></html>`

  const page = await browser.newPage()
  for (const ov of overlays) {
    const path = `${OVERLAYS.replace(/\\/g, '/')}/${ov.id}.html`
    writeFileSync(path, html(ov.id, ov.text, ov.size, ov.color))
    await page.goto(`file:///${path}`, { waitUntil: 'networkidle0', timeout: 10000 })
    await sleep(800)
    await page.screenshot({ path: `${OVERLAYS}\\${ov.id}.png`, type: 'png', omitBackground: true })
    console.log(`  ✓ overlay ${ov.id}`)
  }
  await page.close()
}

function buildFfmpegCommand() {
  const v = (p) => `${M}\\${p}`.replace(/\\/g, '/')

  // Scale all clips to 1080x1920 first
  const scale = 'scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920'

  const clips = [
    // 0-3.5s: "En cada cumpleaños pasa lo mismo…"
    { src: v('v1-clip-fiesta-detenida.mp4'), dur: 3.5, overlay: null, filter: scale },
    // 3.5-7.0s: "20 niños. Cero fotos de ellos."
    { src: v('v1-clip-fiesta-detenida.mp4'), dur: 3.5, overlay: null, filter: scale },
    // 7.0-11.0s: "CumpleClick" (logo enters)
    { src: v('clip-01-kiosco.mp4'), dur: 4, overlay: null, filter: scale },
    // 11.0-16.0s: screen-01 zoom → screen-02 "1. Cada invitado toca su nombre"
    { src: `${SCREENS.replace(/\\/g, '/')}/screen-01-intro.png`, dur: 5, overlay: 't04', filter: `zoompan=z='min(zoom+0.0004,1.08)':d=125:s=1080x1920,fps=25` },
    // 16.0-21.0s: screen-03 "2. La ruleta elige su personaje"
    { src: `${SCREENS.replace(/\\/g, '/')}/screen-03-ruleta.png`, dur: 5, overlay: 't05', filter: `zoompan=z='min(zoom+0.0004,1.08)':d=125:s=1080x1920,fps=25` },
    // 21.0-26.0s: screen-04 "Y sale a recibirlo por su nombre"
    { src: `${SCREENS.replace(/\\/g, '/')}/screen-04-personaje.png`, dur: 5, overlay: 't06', filter: `scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920` },
    // 26.0-31.0s: clip-02 flash "3. ¡Click!"
    { src: v('clip-02-flash.mp4'), dur: 5, overlay: null, filter: scale },
    // 31.0-36.0s: screen-06 "Su foto, con el marco de la temática"
    { src: `${SCREENS.replace(/\\/g, '/')}/screen-06-preview.png`, dur: 5, overlay: 't08', filter: `zoompan=z='min(zoom+0.0004,1.08)':d=125:s=1080x1920,fps=25` },
    // 36.0-41.0s: screen-07 "4. Se la lleva al celular. Y su diploma."
    { src: `${SCREENS.replace(/\\/g, '/')}/screen-07-qr.png`, dur: 5, overlay: 't09', filter: `scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920` },
    // 41.0-46.0s: screen-10 "Los papás reciben el álbum completo"
    { src: `${SCREENS.replace(/\\/g, '/')}/screen-10-galeria.png`, dur: 5, overlay: 't10', filter: `scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920` },
    // 46.0-52.0s: temáticas clips "11 temáticas para elegir"
    { src: v('v3a-clip-sala-carreras.mp4'), dur: 6, overlay: null, filter: scale },
    // 52.0-60.0s: endcard "Agenda tu fecha"
    { src: v('clip-03-endcard.mp4'), dur: 8, overlay: null, filter: scale },
  ]

  return { clips, overlaysPath: OVERLAYS }
}

async function main() {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--allow-file-access-from-files'],
    defaultViewport: { width: 1080, height: 1920, deviceScaleFactor: 1 },
  })

  console.log('Rendering text overlays...')
  await renderOverlays(browser)
  await browser.close()

  console.log('\nBuilding video...')
  const { clips } = buildFfmpegCommand()

  // Build concat filter with overlay
  const parts = []
  const overlayM = OVERLAYS.replace(/\\/g, '/')

  // Initial text overlays (above video)
  const initTexts = [
    { id: 't01', at: 1 }, // "En cada cumpleaños..."
    { id: 't02', at: 1 }, // "20 niños..."
    { id: 't03', at: 1 }, // "CumpleClick"
    { id: 't07', at: 1 }, // "3. ¡Click!"
    { id: 't11', at: 1 }, // "11 temáticas"
    { id: 't12', at: 1 }, // "Agenda tu fecha"
  ]

  // For now, let's do a simpler approach: create clips with overlays baked in
  // using a two-pass approach where we overlay each text on its corresponding clip
  let inputs = ''
  let filterParts = []
  let inputIdx = 0
  let concatInputs = ''

  for (let i = 0; i < clips.length; i++) {
    const clip = clips[i]
    const vidLabel = `[v${i}]`

    inputs += `-loop 1 -t ${clip.dur} -i "${clip.src}" `
    const vidIdx = inputIdx
    inputIdx++

    let vidFilter = clip.filter || 'scale=1080:1920'
    if (vidFilter.includes('zoompan')) {
      // zoompan already includes fps
    } else {
      vidFilter += ',fps=25,setpts=PTS-STARTPTS'
    }

    if (clip.overlay) {
      inputs += `-i "${overlayM}\\${clip.overlay}.png" `
      const ovIdx = inputIdx
      inputIdx++
      filterParts.push(`[${vidIdx}:v]${vidFilter}[base${i}];[${ovIdx}:v]scale=1080:1920[ov${i}];[base${i}][ov${i}]overlay=0:0:shortest=1${vidLabel}`)
    } else {
      filterParts.push(`[${vidIdx}:v]${vidFilter}${vidLabel}`)
    }
    concatInputs += vidLabel
  }

  const filterChain = filterParts.join(';') + `;${concatInputs}concat=n=${clips.length}:v=1:a=0[outv]`

  // Add fade transitions
  // For simplicity, let's use xfade approach with ffmpeg concat
  // Actually, let's just render each segment and concat them

  const cmd = `ffmpeg -y ${inputs}-filter_complex "${filterChain}" -map "[outv]" -c:v libx264 -pix_fmt yuv420p -movflags +faststart -r 25 "${OUT}\\video-explicativo.mp4"`

  console.log('Running ffmpeg...')
  try {
    execSync(cmd, { stdio: 'inherit', maxBuffer: 10 * 1024 * 1024 })
    console.log('\n✓ Video created!')
  } catch (e) {
    console.error('ffmpeg failed:', e.message)
  }
}

main().catch(e => { console.error('Fatal:', e.message); process.exit(1) })
