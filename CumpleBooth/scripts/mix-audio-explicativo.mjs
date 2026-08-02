/**
 * Arma la pista de audio maestra del video explicativo: narración de Alice
 * ubicada en el segundo exacto de cada escena + música instrumental de fondo
 * que baja de volumen ("ducking") mientras ella habla.
 *
 * Los tiempos de arranque de cada escena salen de medir los segmentos YA
 * construidos por build-explicativo.mjs (no de las duraciones declaradas en
 * el guion): el clip del endcard pide 6.0s pero su metraje nativo es 5.04s,
 * así que ese segmento en el video real dura 5.04s, no 6.0 — usar el valor
 * declarado habría desalineado la narración del segmento 12 en adelante.
 *
 * Como el vertical y el horizontal comparten exactamente el mismo guion y las
 * mismas duraciones, esta pista de audio sirve para los DOS formatos: se
 * genera una sola vez y se le pega a cada video en un segundo paso.
 *
 * Requiere haber corrido antes:
 *   node scripts/build-explicativo.mjs   (para medir los segmentos reales)
 *
 * Uso:  node scripts/mix-audio-explicativo.mjs
 */
import { execSync } from 'node:child_process'
import { existsSync, statSync } from 'node:fs'

const D = 'C:/wamp64/www/automatiza-tech/CumpleBooth/design'
const N = 'C:/Users/luis_/AppData/Local/Temp/claude/C--Users-luis-/b1c953d8-e727-4dec-b1fd-f1d0bcd26556/scratchpad/narracion'
const MUSICA = 'C:/Users/luis_/AppData/Local/Temp/claude/C--Users-luis-/b1c953d8-e727-4dec-b1fd-f1d0bcd26556/scratchpad/musica-explicativo.mp3'
const SEGMENTS_DIR = `${process.env.TEMP}/cc-explicativo/vertical` // vertical y horizontal miden lo mismo
const OUT = `${D}/explicativo/audio-master.mp3`

const sh = (cmd) => execSync(cmd, { stdio: ['ignore', 'pipe', 'pipe'], maxBuffer: 1e9 }).toString()

// segmento del guion → línea de narración (null = sin voz, respiro musical)
const MAPA = ['n00', 'n01', 'n02', 'n03', 'n04', 'n05', 'n06', 'n07', 'n08', 'n09', 'n10', null, 'n12']

const dur = (f) => parseFloat(sh(`ffprobe -v error -show_entries format=duration -of csv=p=0 "${f}"`))

// 1) Timeline real: acumular la duración de cada segmento YA construido.
const segFiles = MAPA.map((_, i) => `${SEGMENTS_DIR}/seg${String(i).padStart(2, '0')}.mp4`)
let t = 0
const inicios = segFiles.map((f) => {
  const inicio = t
  t += dur(f)
  return inicio
})
const totalVideo = t
console.log(`Timeline: ${inicios.map((s, i) => `${MAPA[i] ?? '(silencio)'}@${s.toFixed(2)}s`).join(' | ')}`)
console.log(`Duración total: ${totalVideo.toFixed(2)}s`)

// 2) Narración: cada línea entra en el segundo (inicio + 0.3s de aire).
const LEAD = 0.3
const inputs = []
const narrLabels = []
const ventanasDucking = [] // [inicioMusicaBaja, finMusicaBaja] por cada línea con voz

MAPA.forEach((id, i) => {
  if (!id) return
  const file = `${N}/${id}.mp3`
  if (!existsSync(file)) { console.log(`  ! falta ${id}.mp3`); return }
  const start = inicios[i] + LEAD
  const d = dur(file)
  inputs.push(`-i "${file}"`)
  const idx = inputs.length - 1
  const delayMs = Math.round(start * 1000)
  narrLabels.push(`[${idx}:a]adelay=${delayMs}|${delayMs}[n${idx}]`)
  ventanasDucking.push([start, start + d + 0.35])
})

// 3) Música: se recorta a la duración del video, con fade in/out, y se
// duckea (baja a la mitad) dentro de cada ventana donde habla Alice.
const musicIdx = inputs.length
inputs.push(`-i "${MUSICA}"`)
let musicFiltro =
  `[${musicIdx}:a]atrim=0:${totalVideo.toFixed(3)},asetpts=PTS-STARTPTS,` +
  `volume=0.30,afade=t=in:st=0:d=0.6,afade=t=out:st=${(totalVideo - 1.3).toFixed(2)}:d=1.3`
ventanasDucking.forEach(([a, b]) => {
  musicFiltro += `,volume=0.5:enable='between(t,${a.toFixed(2)},${b.toFixed(2)})'`
})
musicFiltro += `[musica]`

const mezclaInputs = ['[musica]', ...inputs.slice(0, narrLabels.length).map((_, i) => `[n${i}]`)]
const filtro = [...narrLabels, musicFiltro, `${mezclaInputs.join('')}amix=inputs=${mezclaInputs.length}:duration=first:normalize=0[out]`].join(';')

sh(`ffmpeg -y -v error ${inputs.join(' ')} -filter_complex "${filtro}" -map "[out]" ` +
   `-t ${totalVideo.toFixed(3)} -c:a libmp3lame -b:a 192k "${OUT}"`)

console.log(`\n✓ Audio maestro: ${OUT}`)
console.log(`  tamaño=${(statSync(OUT).size / 1048576).toFixed(1)} MB  duración=${dur(OUT).toFixed(2)}s`)
