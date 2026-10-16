import { useCallback, useEffect, useRef, useState } from 'react'

/**
 * El reproductor sin React (para poder probarlo en node con un Audio falso). Un solo audio: una voz nueva corta la
 * anterior. `decir(urls)` resuelve UNA vez: true si sonó todo, false si se cortó (`callar` u otra voz) o si un audio
 * falló o el navegador bloqueó la reproducción; así quien espera (la presentación, el reloj) sabe si dar tiempo de
 * lectura y nunca queda colgado (revisión B de Codex, CX-31 y CX-32).
 */
export function crearReproductor(nuevoAudio = () => new Audio()) {
  let audio = null
  let pendiente = null // finalizador de la reproducción en curso

  const cortar = () => {
    const fin = pendiente
    pendiente = null
    if (audio) { try { audio.pause() } catch {} ; audio.onended = null; audio.onerror = null }
    if (fin) fin(false)
  }

  const uno = (url) => new Promise((listo) => {
    if (!audio) audio = nuevoAudio()
    const a = audio
    let hecho = false
    const fin = (ok) => {
      if (hecho) return
      hecho = true
      if (pendiente === fin) pendiente = null
      a.onended = null
      a.onerror = null
      listo(ok)
    }
    pendiente = fin
    a.onended = () => fin(true)
    a.onerror = () => fin(false)
    a.src = url
    let r
    try { r = a.play() } catch { fin(false); return }
    if (r && r.catch) r.catch(() => fin(false)) // reproducción bloqueada: cuenta como no sonó
  })

  const decir = async (urls) => {
    cortar()
    for (const url of urls) {
      if (!(await uno(url))) return false
    }
    return true
  }

  return { decir, callar: cortar }
}

/**
 * Voces del animador en el kiosco. Lee el índice de la fiesta (`indice.json`: { clips: { clave: archivo } }) y
 * reproduce claves de a una o en secuencia. Sin índice (fiesta sin voces, archivo caído, sin red) todo sigue igual
 * que antes, solo que mudo: `decir` resuelve al tiro con false. El silencio elegido se recuerda en la pestaña.
 */
export function useVoz(indiceUrl, claveSilencio = 'animador_voz') {
  const [clips, setClips] = useState(null) // { clave: url absoluta } | null
  const [silencio, setSilencioEstado] = useState(() => {
    try { return sessionStorage.getItem(claveSilencio) === '1' } catch { return false }
  })
  const [hablando, setHablando] = useState(false)
  const rep = useRef(null)
  if (!rep.current) rep.current = crearReproductor()
  const turno = useRef(0)

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
    rep.current.callar()
    setHablando(false)
  }, [])

  useEffect(() => () => callar(), [callar])

  /** Reproduce una clave o una lista en orden. Resuelve true si sonó entera; false si no hubo voz o se cortó. */
  const decir = useCallback((claves) => {
    const lista = (Array.isArray(claves) ? claves : [claves]).filter((k) => k && clips && clips[k])
    turno.current += 1
    const mio = turno.current
    if (silencio || lista.length === 0) { rep.current.callar(); setHablando(false); return Promise.resolve(false) }
    setHablando(true)
    return rep.current.decir(lista.map((k) => clips[k])).then((ok) => {
      if (mio === turno.current) setHablando(false)
      return ok
    })
  }, [clips, silencio])

  const tiene = useCallback((clave) => Boolean(clave && clips && clips[clave]), [clips])

  const setSilencio = useCallback((v) => {
    setSilencioEstado(v)
    try { sessionStorage.setItem(claveSilencio, v ? '1' : '0') } catch {}
    if (v) callar()
  }, [claveSilencio, callar])

  return { disponible: Boolean(clips), tiene, decir, callar, hablando, silencio, setSilencio }
}
