/**
 * Monta el video promocional de la temática Reino de Hielo (Frozen).
 *
 * Entrada: los clips grabados por record-frozen-gameplay.mjs (gameplay REAL
 * del kiosco) + la narración de Alice + el logo y el eslogan de marca.
 * Salida: design/video-frozen/video-frozen-juegos.mp4 (1080x1920).
 *
 * Los clips vienen con la barra de Chrome y la de tareas alrededor: se recortan
 * a la tarjeta del kiosco (CROP) y se componen centrados sobre un fondo
 * desenfocado del mismo cuadro, igual que el video explicativo.
 *
 * Uso:  node scripts/build-frozen-video.mjs
 */
import { execSync } from 'node:child_process'
import { existsSync, mkdirSync, writeFileSync, rmSync, statSync } from 'node:fs'

const D = 'C:/wamp64/www/automatiza-tech/CumpleBooth/design'
const VF = `${D}/video-frozen`
const VOZ = `${VF}/voz`
const LOGO = `${D}/logo/logo-icon-wordmark.png`
const MUSICA = 'C:/wamp64/www/automatiza-tech/CumpleBooth/public/themes/hielo/musica-fondo.mp3'
const TMP = `${process.env.TEMP}/cc-frozen`
const SALIDA = `${VF}/video-frozen-juegos.mp4`

const W = 1080, H = 1920, FR = 30
// Región útil dentro del cuadro grabado: descarta barra de título, infobar de
// Chrome y barra de tareas de Windows.
const CROP = 'crop=444:628:78:78'

const sh = (c) => execSync(c, { stdio: ['ignore', 'pipe', 'pipe'], maxBuffer: 1e9 }).toString()
const dur = (f) => parseFloat(sh(
  `ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "${f}"`
).trim())
const esc = (t) => t.replace(/'/g, '\u2019').replace(/:/g, '\\:')

rmSync(TMP, { recursive: true, force: true })
mkdirSync(TMP, { recursive: true })

/**
 * Guion. `desde`/`hasta` recortan el clip original: los clips se graban con
 * duración fija, así que arrastran cola del paso siguiente.
 */
// Ventanas medidas sobre los clips con búsqueda EXACTA (-i antes de -ss).
// Varias son más cortas que su narración: el segmento se completa congelando
// el último cuadro (tpad), así el momento clave queda en pantalla el tiempo
// que dura la voz en vez de cortarse a media frase.
const GUION = [
  // La experiencia empieza mucho antes del primer juego: el invitado toca la
  // pantalla, elige su nombre, entra al mundo de la temática y recién ahí la
  // ruleta le asigna su personaje.
  { clip: 'clip-00a-intro.mp4', desde: 0.4, hasta: 5.6, voz: 'n0a-portada.mp3', titulo: 'Elige tu nombre' },
  { clip: 'clip-00b-inmersivo.mp4', desde: 1.0, hasta: 13.5, voz: 'n0b-inmersivo.mp3', titulo: 'La experiencia inmersiva' },
  { clip: 'clip-00c-ruleta.mp4', desde: 0.4, hasta: 5.4, voz: 'n0c-ruleta.mp3', titulo: 'La ruleta elige' },
  // El saludo sale del MISMO clip de la ruleta: cuando arrancaba la grabación
  // dedicada, el video de Olaf ya casi había terminado. Acá está entero.
  { clip: 'clip-00c-ruleta.mp4', desde: 6.0, hasta: 9.4, voz: 'n0d-saludo.mp3', titulo: 'Olaf lo saluda' },
  { clip: 'clip-01-armar-muneco.mp4', desde: 0.5, voz: 'n2-muneco.mp3', titulo: 'Juego 1 · Arma a Olaf' },
  { clip: 'clip-o1-oferta.mp4', desde: 0.2, hasta: 1.35, voz: 'n3-oferta.mp3', titulo: '¿Jugamos otro?' },
  { clip: 'clip-02-fichas.mp4', desde: 0.5, hasta: 9.0, voz: 'n4-fichas.mp3', titulo: 'Juego 2 · El rompecabezas' },
  { clip: 'clip-03-copos.mp4', desde: 1.0, hasta: 13.0, voz: 'n5-copos.mp3', titulo: 'Juego 3 · Atrapa los copos' },
  { clip: 'clip-04-resultado.mp4', desde: 11.0, hasta: 13.6, voz: 'n6-foto.mp3', titulo: 'Su foto con el marco' },
  { clip: 'clip-05-cierre.mp4', desde: 2.2, hasta: 3.6, voz: 'n7-diploma.mp3', titulo: 'Su diploma y su QR' },
  { clip: 'clip-05-cierre.mp4', desde: 11.0, voz: 'n9-despedida.mp3', titulo: 'La despedida' },
]

/** Portada y cierre: logo real + eslogan sobre un fondo de la propia temática. */
function tarjeta(nombre, textoGrande, textoChico, vozArchivo, fondoClip) {
  const salida = `${TMP}/${nombre}.mp4`
  const d = Math.max(4.2, dur(`${VOZ}/${vozArchivo}`) + 1.4)
  sh(`ffmpeg -y -v error -ss 3 -i "${VF}/${fondoClip}" -i "${LOGO}" -filter_complex ` +
     `"[0:v]${CROP},scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H},` +
     `boxblur=32:3,eq=brightness=-0.16:saturation=1.1[bg];` +
     `[1:v]scale=560:-1[lg];[bg][lg]overlay=(W-w)/2:430[c1];` +
     `[c1]drawtext=fontfile='C\\:/Windows/Fonts/segoeuib.ttf':text='${esc(textoGrande)}':` +
     `fontcolor=white:fontsize=86:x=(w-text_w)/2:y=1010:shadowcolor=black@0.6:shadowx=0:shadowy=4[c2];` +
     `[c2]drawtext=fontfile='C\\:/Windows/Fonts/segoeui.ttf':text='${esc(textoChico)}':` +
     `fontcolor=white:fontsize=42:x=(w-text_w)/2:y=1140:shadowcolor=black@0.6:shadowx=0:shadowy=3[v]" ` +
     `-map "[v]" -t ${d.toFixed(2)} -c:v libx264 -preset medium -crf 20 -pix_fmt yuv420p -r ${FR} "${salida}"`)
  return { salida, d, voz: `${VOZ}/${vozArchivo}` }
}

console.log('Armando segmentos...')
const segmentos = []

segmentos.push(tarjeta('seg-00-portada', 'Reino de Hielo',
  'Tu fiesta, tu personaje o temática, tu foto', 'n1-intro.mp3', 'clip-01-armar-muneco.mp4'))
process.stdout.write('.')

GUION.forEach((paso, i) => {
  const fuente = `${VF}/${paso.clip}`
  if (!existsSync(fuente)) { console.log(`\n  ! falta ${paso.clip}`); return }
  const vozPath = `${VOZ}/${paso.voz}`
  const disponible = (paso.hasta ?? dur(fuente)) - paso.desde
  // El segmento dura lo que la narración necesita; si la ventana grabada es
  // más corta, se congela el último cuadro para llegar (tpad).
  const d = Math.max(dur(vozPath) + 1.2, 5)
  const congelar = Math.max(0, d - disponible)
  const salida = `${TMP}/seg-${String(i + 1).padStart(2, '0')}.mp4`

  // La ventana se recorta con el filtro `trim`, no con -ss/-t: puestos después
  // de -i son opciones de SALIDA, así que el segundo -t pisaba al primero y el
  // recorte no se aplicaba (el diploma terminaba mostrando la despedida).
  const fin = paso.hasta ?? dur(fuente)
  sh(`ffmpeg -y -v error -i "${fuente}" -filter_complex ` +
     `"[0:v]trim=start=${paso.desde}:end=${fin},setpts=PTS-STARTPTS,${CROP},` +
     `tpad=stop_mode=clone:stop_duration=${congelar.toFixed(2)},split[a][b];` +
     `[a]scale=${W}:-1[fg];` +
     `[b]scale=${W}:${H}:force_original_aspect_ratio=increase,crop=${W}:${H},` +
     `boxblur=30:3,eq=brightness=-0.10[bg];` +
     `[bg][fg]overlay=0:(H-h)/2[base];` +
     // Franja superior con el título del paso: da contexto sin tapar el juego.
     `[base]drawbox=x=0:y=0:w=${W}:h=190:color=0x0d3a5c@0.86:t=fill[band];` +
     `[band]drawtext=fontfile='C\\:/Windows/Fonts/segoeuib.ttf':text='${esc(paso.titulo)}':` +
     `fontcolor=white:fontsize=56:x=(w-text_w)/2:y=66[v]" ` +
     `-map "[v]" -t ${d.toFixed(2)} -c:v libx264 -preset medium -crf 20 -pix_fmt yuv420p -r ${FR} "${salida}"`)
  segmentos.push({ salida, d, voz: vozPath })
  process.stdout.write('.')
})

segmentos.push(tarjeta('seg-99-cierre', 'CumpleClick',
  'Agenda tu fecha · automatizatech.cl', 'n8-cierre.mp3', 'clip-05-cierre.mp4'))
process.stdout.write('.\n')

const lista = `${TMP}/concat.txt`
writeFileSync(lista, segmentos.map((s) => `file '${s.salida}'`).join('\n'), 'ascii')
const mudo = `${TMP}/mudo.mp4`
sh(`ffmpeg -y -v error -f concat -safe 0 -i "${lista}" -c:v libx264 -preset medium -crf 20 ` +
   `-pix_fmt yuv420p -r ${FR} "${mudo}"`)
const total = dur(mudo)
console.log(`video mudo: ${total.toFixed(2)}s`)

// Audio: narración de Alice sobre música de la temática, con ducking — la
// música baja mientras Alice habla para que la voz siempre se entienda.
let t = 0
const entradas = []
const filtros = []
const mezclas = []
segmentos.forEach((s, i) => {
  entradas.push(`-i "${s.voz}"`)
  const ms = Math.round((t + 0.3) * 1000)
  filtros.push(`[${i + 1}:a]adelay=${ms}|${ms},volume=1.4[v${i}]`)
  mezclas.push(`[v${i}]`)
  t += s.d
})
let t2 = 0
const ducking = segmentos.map((s) => {
  const a = (t2 + 0.15).toFixed(2)
  const b = (t2 + dur(s.voz) + 0.7).toFixed(2)
  t2 += s.d
  return `volume=0.28:enable='between(t,${a},${b})'`
}).join(',')

const audio = `${TMP}/audio.m4a`
sh(`ffmpeg -y -v error -stream_loop -1 -i "${MUSICA}" ${entradas.join(' ')} -filter_complex ` +
   `"[0:a]atrim=0:${total.toFixed(2)},asetpts=N/SR/TB,volume=0.24,${ducking}[mus];` +
   `${filtros.join(';')};${mezclas.join('')}amix=inputs=${segmentos.length}:normalize=0[voz];` +
   `[mus][voz]amix=inputs=2:normalize=0,alimiter=limit=0.95[a]" ` +
   `-map "[a]" -t ${total.toFixed(2)} -c:a aac -b:a 192k "${audio}"`)

sh(`ffmpeg -y -v error -i "${mudo}" -i "${audio}" -map 0:v -map 1:a -c:v copy -c:a aac -b:a 192k ` +
   `-shortest -movflags +faststart "${SALIDA}"`)

const probe = sh(`ffprobe -v error -show_entries stream=codec_name,width,height,pix_fmt ` +
                 `-show_entries format=duration -of default=noprint_wrappers=1 "${SALIDA}"`).trim()
console.log(`\n✓ ${SALIDA}\n${probe.split('\n').map((l) => '   ' + l).join('\n')}`)
console.log(`   tamaño=${(statSync(SALIDA).size / 1048576).toFixed(1)} MB`)
rmSync(TMP, { recursive: true, force: true })
