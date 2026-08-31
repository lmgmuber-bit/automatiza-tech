/* ============================================================
   Motor de audio de los juegos — batería, bajo y riff sintetizados
   con WebAudio. Cero archivos, cero derechos de autor.

   Nació dentro de StageConcert3D (El Show 3D) y se extrajo acá cuando el
   juego de Ritmo 2D pasó a necesitar exactamente lo mismo: un pulso audible
   con tiempos exactos de sample para que la nota que ves caer suene cuando
   la tocas. Dos copias de esto se habrían desincronizado a la primera
   corrección de tempo.

   POR QUÉ SINTETIZADO Y NO UN MP3: el chart cae siempre en el beat exacto
   sin tener que medir el BPM de un archivo ni alinear su fase — y la música
   de fondo del kiosco viene sonando desde antes, reanudada en un segundo
   arbitrario, así que jamás se podría enganchar con ella.

   RELOJ: los tiempos que recibe `step()` son del reloj del AudioContext
   (`ctx.currentTime`), sample-accurate. Pero el juego NO debe usar ese reloj
   para su lógica: si el navegador deja el contexto en 'suspended' (política
   de autoplay) el reloj no avanza y el juego se congela. Se usa
   performance.now() y se ANCLA el audio a él — ver `createBeatClock`.
   ============================================================ */

export const DEFAULT_BPM = 112

const RIFF = [
  // 4 compases de 8 corcheas. null = silencio. Semitonos sobre A3 (220 Hz).
  12, null, 15, null, 19, null, 17, 15,
  12, null, 15, null, 19, 22, 19, null,
  17, null, 15, null, 12, null, 10, 12,
  15, null, 17, null, 19, null, 22, 24,
]

export function createAudioKit() {
  const Ctx = typeof window !== 'undefined' && (window.AudioContext || window.webkitAudioContext)
  if (!Ctx) return null
  let ctx
  try {
    ctx = new Ctx()
  } catch {
    return null
  }

  const master = ctx.createGain()
  master.gain.value = 0.55
  master.connect(ctx.destination)

  // Un solo buffer de ruido blanco reutilizado por claps y hats: crear un
  // buffer por golpe generaba basura a 8 golpes por segundo.
  const noise = ctx.createBuffer(1, ctx.sampleRate * 0.5, ctx.sampleRate)
  const data = noise.getChannelData(0)
  for (let i = 0; i < data.length; i += 1) data[i] = Math.random() * 2 - 1

  const burst = (t, dur, freq, q, gain, type = 'bandpass') => {
    const src = ctx.createBufferSource()
    src.buffer = noise
    const f = ctx.createBiquadFilter()
    f.type = type
    f.frequency.value = freq
    f.Q.value = q
    const g = ctx.createGain()
    g.gain.setValueAtTime(gain, t)
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur)
    src.connect(f)
    f.connect(g)
    g.connect(master)
    src.start(t)
    src.stop(t + dur + 0.02)
  }

  const tone = (t, freq, dur, type, gain, glideTo) => {
    const o = ctx.createOscillator()
    const g = ctx.createGain()
    o.type = type
    o.frequency.setValueAtTime(freq, t)
    if (glideTo) o.frequency.exponentialRampToValueAtTime(glideTo, t + dur * 0.9)
    g.gain.setValueAtTime(0.0001, t)
    g.gain.exponentialRampToValueAtTime(gain, t + 0.008)
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur)
    o.connect(g)
    g.connect(master)
    o.start(t)
    o.stop(t + dur + 0.02)
  }

  const kick = (t) => tone(t, 150, 0.26, 'sine', 0.95, 46)
  const clap = (t) => burst(t, 0.17, 1500, 1.1, 0.42)
  const hat = (t, open) => burst(t, open ? 0.14 : 0.045, 8200, 0.9, open ? 0.14 : 0.1, 'highpass')

  const bass = (t, semi) => {
    const o = ctx.createOscillator()
    const f = ctx.createBiquadFilter()
    const g = ctx.createGain()
    o.type = 'sawtooth'
    o.frequency.value = 110 * Math.pow(2, semi / 12)
    f.type = 'lowpass'
    f.frequency.setValueAtTime(900, t)
    f.frequency.exponentialRampToValueAtTime(180, t + 0.22)
    g.gain.setValueAtTime(0.0001, t)
    g.gain.exponentialRampToValueAtTime(0.3, t + 0.01)
    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.24)
    o.connect(f)
    f.connect(g)
    g.connect(master)
    o.start(t)
    o.stop(t + 0.27)
  }

  const lead = (t, semi, gain = 0.16) => {
    tone(t, 220 * Math.pow(2, semi / 12), 0.2, 'triangle', gain)
    tone(t, 440 * Math.pow(2, semi / 12), 0.14, 'square', gain * 0.28)
  }

  return {
    ctx,
    master,
    resume: () => ctx.state === 'suspended' && ctx.resume().catch(() => {}),
    /* Un paso = una corchea. `step` es global desde el arranque del juego. */
    step(t, step) {
      const inBar = step % 8
      if (inBar === 0 || inBar === 3 || inBar === 4) kick(t)
      if (inBar === 4) clap(t)
      hat(t, inBar === 7)
      if (inBar % 2 === 0 || inBar === 7) bass(t, [0, 0, 0, -2, 3, 3, -4, -4][inBar])
      const semi = RIFF[step % RIFF.length]
      if (semi !== null && semi !== undefined) lead(t, semi)
    },
    perfect(t, combo) {
      const semi = [12, 15, 19, 22, 24][Math.min(4, Math.floor(combo / 3))]
      lead(t, semi, 0.3)
      lead(t + 0.055, semi + 5, 0.2)
      burst(t, 0.2, 5200, 0.7, 0.16, 'highpass')
    },
    good(t) {
      lead(t, 12, 0.2)
    },
    miss(t) {
      tone(t, 190, 0.2, 'sawtooth', 0.16, 70)
    },
    cheer(t) {
      burst(t, 1.5, 900, 0.35, 0.3)
      burst(t + 0.05, 1.3, 2600, 0.4, 0.16, 'highpass')
    },
    close() {
      try {
        master.disconnect()
        ctx.close()
      } catch {}
    },
  }
}

/* ── Reloj de beat ────────────────────────────────────────────────────────
   Encapsula el patrón que costó un bug en El Show: el tiempo de juego sale
   SIEMPRE de performance.now(); el audio se ancla a él la primera vez que el
   AudioContext está realmente corriendo, y los pasos que quedaron atrás
   mientras estaba suspendido se descartan en vez de dispararse todos juntos.

   Si el audio nunca arranca, el juego sigue perfectamente jugable, mudo.
   ─────────────────────────────────────────────────────────────────────── */
export function createBeatClock(kit, bpm = DEFAULT_BPM) {
  const beat = 60 / bpm
  const eighth = beat / 2
  let t0 = 0
  let anchored = false
  let anchor = 0
  let step = 0

  return {
    beat,
    eighth,
    start() {
      if (kit) kit.resume()
      t0 = performance.now() / 1000
      anchored = false
      anchor = 0
      step = 0
    },
    /** Segundos transcurridos desde start(). Nunca se congela. */
    now() {
      return performance.now() / 1000 - t0
    },
    /** Llamar una vez por frame: agenda la batería 0.25s hacia adelante. */
    tick(t) {
      if (!kit || kit.ctx.state !== 'running') return
      if (!anchored) {
        anchored = true
        anchor = kit.ctx.currentTime - t
        step = Math.max(step, Math.ceil(t / eighth))
      }
      const horizon = t + 0.25
      while (step * eighth < horizon) {
        const when = anchor + step * eighth
        if (when >= kit.ctx.currentTime) kit.step(when, step)
        step += 1
      }
    },
  }
}
