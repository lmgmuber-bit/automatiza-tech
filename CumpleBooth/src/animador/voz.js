import { useCallback, useEffect, useRef, useState } from 'react'

/**
 * Voces del animador en el kiosco. Lee el índice de la fiesta (`indice.json`: { clips: { clave: archivo } })
 * y reproduce claves de a una o en secuencia, con un solo <audio>: una voz nueva corta la anterior.
 *
 * Sin índice (fiesta sin voces, archivo caído, sin red) todo sigue igual que antes, solo que mudo:
 * `decir` resuelve al tiro y la pantalla no espera nada. El silencio elegido se recuerda en la pestaña.
 */
export function useVoz(indiceUrl, claveSilencio = 'animador_voz') {
  const [clips, setClips] = useState(null) // { clave: url absoluta } | null
  const [silencio, setSilencioEstado] = useState(() => {
    try { return sessionStorage.getItem(claveSilencio) === '1' } catch { return false }
  })
  const [hablando, setHablando] = useState(false)
  const audio = useRef(null)
  const turno = useRef(0) // cada `decir` nuevo invalida la secuencia anterior

  useEffect(() => {
    if (!indiceUrl) { setClips(null); return undefined }
    let vivo = true
    const base = indiceUrl.split('?')[0].replace(/[^/]*$/, '')
    fetch(indiceUrl, { cache: 'no-cache' })
      .then((r) => (r.ok ? r.json() : null))
      .then((j) => {
        if (!vivo || !j || typeof j.clips !== 'object') return
        const mapa = {}
        for (const [k, v] of Object.entries(j.clips)) if (typeof v === 'string' && /^[\w.-]+\.mp3(\?[\w=.-]*)?$/.test(v)) mapa[k] = base + v
        setClips(mapa)
      })
      .catch(() => {})
    return () => { vivo = false }
  }, [indiceUrl])

  const callar = useCallback(() => {
    turno.current += 1
    const a = audio.current
    if (a) { try { a.pause() } catch {} }
    setHablando(false)
  }, [])

  useEffect(() => () => callar(), [callar])

  /** Reproduce una clave o una lista en orden. Resuelve al terminar (o al ser cortada). */
  const decir = useCallback((claves) => {
    const lista = (Array.isArray(claves) ? claves : [claves]).filter((k) => k && clips && clips[k])
    callar()
    if (silencio || lista.length === 0) return Promise.resolve(false)
    const mio = turno.current
    if (!audio.current) audio.current = new Audio()
    const a = audio.current
    setHablando(true)
    return lista.reduce((p, k) => p.then((seguir) => {
      if (!seguir || mio !== turno.current) return false
      return new Promise((listo) => {
        const fin = () => { a.onended = null; a.onerror = null; listo(mio === turno.current) }
        a.onended = fin
        a.onerror = fin
        a.src = clips[k]
        const r = a.play()
        if (r && r.catch) r.catch(fin) // autoplay bloqueado: se sigue sin voz
      })
    }), Promise.resolve(true)).then((ok) => {
      if (mio === turno.current) setHablando(false)
      return ok
    })
  }, [clips, silencio, callar])

  const tiene = useCallback((clave) => Boolean(clave && clips && clips[clave]), [clips])

  const setSilencio = useCallback((v) => {
    setSilencioEstado(v)
    try { sessionStorage.setItem(claveSilencio, v ? '1' : '0') } catch {}
    if (v) callar()
  }, [claveSilencio, callar])

  return { disponible: Boolean(clips), tiene, decir, callar, hablando, silencio, setSilencio }
}
