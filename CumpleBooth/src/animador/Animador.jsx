import React, { useCallback, useEffect, useRef, useState } from 'react'
import { rellenar } from '../cumpleAdulto.js'
import { EQUIPOS_SUGERIDOS, JUEGOS, NIVEL_TEXTO, PASO_ESTA } from './mazos.js'
import {
  NIVELES, abrirJuego, alternativaDisponible, anotar, cambiarNivel, cartasDelNivel, confirmarPreparacion,
  deshacer, ganadores, juegosDelNivel, marcador, necesitaPreparacion, nuevaRonda, nuevaSesion, pasar,
  reiniciarMazo, restaurarSesion, revelar, serializarSesion, siguiente, yaAnoto,
} from './motor.js'

/**
 * Lee la partida guardada en esta pestaña. Si no calza con la forma esperada (versión, nivel,
 * equipos, puntos…), descarta solo esa clave y devuelve null: el animador vuelve a Preparar.
 */
function leerGuardada(clave) {
  let crudo = null
  try { crudo = sessionStorage.getItem(clave) } catch { return null }
  if (crudo == null) return null
  let datos
  try { datos = JSON.parse(crudo) } catch { datos = undefined }
  const sesion = restaurarSesion(datos)
  if (!sesion) { try { sessionStorage.removeItem(clave) } catch {} }
  return sesion
}

/**
 * Juegos del animador (AT-CUMPLECLICK-026). Un animador conduce desde la tablet; la cabina de
 * fotos sigue disponible entre juegos. Se recuerdan en esta pestaña (sessionStorage) el marcador,
 * los equipos, las tarjetas ya usadas por juego y qué juegos se confirmaron preparados, para que
 * un recargón no borre la partida; nunca respuestas.
 */
export default function Animador({ nombre = '', edad = null, clave = 'animador', onSalir, onFotoGrupal }) {
  const datos = { nombre, edad }
  const t = (texto) => rellenar(texto, datos)
  const [guardada] = useState(() => leerGuardada(clave))
  const [sesion, setSesion] = useState(guardada)
  const [pantalla, setPantalla] = useState(guardada ? 'juegos' : 'preparar')
  const [porPreparar, setPorPreparar] = useState(null) // id del juego que espera su preparación

  useEffect(() => {
    if (!sesion) return
    // Solo lo necesario para retomar. Nada de lo que se dijo en la ronda.
    try { sessionStorage.setItem(clave, JSON.stringify(serializarSesion(sesion))) } catch {}
  }, [sesion, clave])

  if (pantalla === 'preparar' || !sesion) {
    return (
      <Preparar
        onListo={(nivel, equipos) => { setSesion(nuevaSesion({ nivel, equipos })); setPantalla('juegos') }}
        onSalir={onSalir}
        t={t}
      />
    )
  }
  const juego = JUEGOS.find((j) => j.id === sesion.juego) || null
  if (pantalla === 'cierre') {
    return <Cierre sesion={sesion} onFotoGrupal={onFotoGrupal} onSeguir={() => setPantalla('juegos')} onSalir={onSalir}
      onNueva={() => { try { sessionStorage.removeItem(clave) } catch {}; setSesion(null); setPantalla('preparar') }} />
  }
  if (pantalla === 'juego' && juego) {
    return <Ronda sesion={sesion} setSesion={setSesion} juego={juego} t={t} onVolver={() => setPantalla('juegos')} />
  }
  const abrir = (j) => {
    if (necesitaPreparacion(sesion, j)) { setPorPreparar(j.id); setPantalla('preparar-juego'); return }
    setSesion((s) => abrirJuego(s, j))
    setPantalla('juego')
  }
  const pendiente = JUEGOS.find((j) => j.id === porPreparar) || null
  if (pantalla === 'preparar-juego' && pendiente) {
    return (
      <PrepararJuego sesion={sesion} setSesion={setSesion} juego={pendiente} t={t}
        onConfirmar={() => {
          setSesion((s) => abrirJuego(confirmarPreparacion(s, pendiente), pendiente))
          setPorPreparar(null)
          setPantalla('juego')
        }}
        onAlternativa={(alt) => { setPorPreparar(null); abrir(alt) }}
        onVolver={() => { setPorPreparar(null); setPantalla('juegos') }} />
    )
  }
  const disponibles = juegosDelNivel(JUEGOS, sesion.nivel)
  return (
    <section className="screen anim">
      <Cabecera sesion={sesion} setSesion={setSesion} />
      <h2 className="anim-titulo">Entre cómplices: ¿a qué jugamos?</h2>
      <div className="anim-juegos">
        {disponibles.map((j) => (
          <button key={j.id} type="button" className="anim-juego" onClick={() => abrir(j)}>
            <span className="anim-juego__emoji" aria-hidden="true">{j.emoji}</span>
            <span>{t(j.nombre)}</span>
          </button>
        ))}
      </div>
      <div className="anim-pie">
        <button type="button" className="cta" onClick={() => setPantalla('cierre')}>🏆 Premios y cierre</button>
        <button type="button" className="cta ghost" onClick={onSalir}>📸 Volver a la cabina</button>
      </div>
    </section>
  )
}

function Preparar({ onListo, onSalir, t }) {
  const [nivel, setNivel] = useState('coqueto')
  const [cuantos, setCuantos] = useState(2)
  const [nombres, setNombres] = useState(EQUIPOS_SUGERIDOS)
  return (
    <section className="screen anim">
      <h2 className="anim-titulo">Entre cómplices: elige el nivel de picardía</h2>
      <div className="anim-niveles" role="radiogroup" aria-label="Nivel">
        {NIVELES.map((n) => (
          <button key={n} type="button" role="radio" aria-checked={nivel === n}
            className={`anim-nivel${nivel === n ? ' is-on' : ''}`} onClick={() => setNivel(n)}>
            <b>{NIVEL_TEXTO[n].nombre}</b>
            <small>{NIVEL_TEXTO[n].detalle}</small>
          </button>
        ))}
      </div>
      <p className="anim-nota">El nivel es un techo: nunca aparece una tarjeta más atrevida. Se puede bajar en cualquier momento.</p>
      <div className="anim-equipos">
        <span>Equipos</span>
        {[2, 3, 4].map((n) => (
          <button key={n} type="button" className={`anim-chip${cuantos === n ? ' is-on' : ''}`} onClick={() => setCuantos(n)}>{n}</button>
        ))}
      </div>
      {nombres.slice(0, cuantos).map((nm, i) => (
        <input key={i} className="anim-input" value={nm} maxLength={32} aria-label={`Nombre del equipo ${i + 1}`}
          onChange={(e) => setNombres((l) => l.map((x, k) => (k === i ? e.target.value : x)))} />
      ))}
      <p className="anim-nota">{t('Cada quien elige su equipo. Hombres contra mujeres es solo una variante.')}</p>
      <div className="anim-pie">
        <button type="button" className="cta" onClick={() => onListo(nivel, nombres.slice(0, cuantos))}>🎲 Empezar</button>
        <button type="button" className="cta ghost" onClick={onSalir}>📸 Volver a la cabina</button>
      </div>
    </section>
  )
}

function Cabecera({ sesion, setSesion, juego = null }) {
  const totales = marcador(sesion)
  return (
    <header className="anim-cabecera">
      <div className="anim-marcador" aria-label="Marcador">
        {sesion.equipos.map((e, i) => (
          <span key={i} className="anim-marca"><small>{e}</small><b>{totales[i]}</b></span>
        ))}
      </div>
      <label className="anim-nivel-sel">
        Nivel{' '}
        <select value={sesion.nivel} onChange={(e) => setSesion((s) => cambiarNivel(s, e.target.value, juego))}>
          {NIVELES.map((n) => <option key={n} value={n}>{NIVEL_TEXTO[n].nombre}</option>)}
        </select>
      </label>
    </header>
  )
}

/**
 * Preparación previa de un juego (Tribunal): el animador confirma que tiene lo necesario o juega
 * la alternativa. Solo se guarda el sí; las respuestas nunca pasan por la tablet.
 */
function PrepararJuego({ sesion, setSesion, juego, t, onConfirmar, onAlternativa, onVolver }) {
  const prep = juego.preparacion
  const alt = alternativaDisponible(JUEGOS, juego, sesion.nivel)
  const altJuego = JUEGOS.find((j) => j.id === prep.alternativa) || null
  const altNivel = altJuego ? NIVELES.find((n) => cartasDelNivel(altJuego, n).length > 0) : null
  return (
    <section className="screen anim">
      <Cabecera sesion={sesion} setSesion={setSesion} />
      <h2 className="anim-titulo"><span aria-hidden="true">{juego.emoji}</span> {t(juego.nombre)}</h2>
      <p className="anim-instr">{t(prep.explicacion)}</p>
      <div className="anim-pie">
        <button type="button" className="cta" onClick={onConfirmar}>✅ {t(prep.confirmar)}</button>
        {alt && (
          <button type="button" className="cta ghost" onClick={() => onAlternativa(alt)}>
            <span aria-hidden="true">{alt.emoji}</span> Jugar {t(alt.nombre)} en su lugar
          </button>
        )}
        <button type="button" className="cta ghost" onClick={onVolver}>☰ Otros juegos</button>
      </div>
      {!alt && altJuego && altNivel && (
        <p className="anim-nota">
          {t(altJuego.nombre)} se juega desde el nivel {NIVEL_TEXTO[altNivel].nombre}: súbelo arriba si quieren, o elige otro juego.
        </p>
      )}
    </section>
  )
}

/**
 * Tarjeta privada (Mímica): un solo botón que nunca se reemplaza. La frase se ve solo mientras se
 * mantiene apretado (dedo, mouse o Espacio/Enter) y se tapa al soltar donde sea, al cancelarse el
 * toque, al perder el foco o la pestaña, y al cambiar de tarjeta o de nivel (`llave`).
 */
function CartaPrivada({ llave, texto }) {
  const [vista, setVista] = useState(null) // llave de la tarjeta que se está mirando
  const visible = vista !== null && vista === llave
  const ocultar = useCallback(() => setVista(null), [])

  useEffect(() => {
    if (!visible) return undefined
    // Red de seguridad por si el navegador no respeta la captura del puntero.
    const alOcultarPestana = () => { if (document.hidden) ocultar() }
    window.addEventListener('pointerup', ocultar)
    window.addEventListener('pointercancel', ocultar)
    window.addEventListener('blur', ocultar)
    document.addEventListener('visibilitychange', alOcultarPestana)
    return () => {
      window.removeEventListener('pointerup', ocultar)
      window.removeEventListener('pointercancel', ocultar)
      window.removeEventListener('blur', ocultar)
      document.removeEventListener('visibilitychange', alOcultarPestana)
    }
  }, [visible, ocultar])

  const esTecla = (e) => e.key === ' ' || e.key === 'Enter'
  return (
    <button type="button" className="anim-carta anim-carta--privada" aria-pressed={visible}
      onPointerDown={(e) => {
        if (e.pointerType === 'mouse' && e.button !== 0) return
        try { e.currentTarget.setPointerCapture(e.pointerId) } catch {}
        setVista(llave)
      }}
      onPointerUp={(e) => {
        try { if (e.currentTarget.hasPointerCapture(e.pointerId)) e.currentTarget.releasePointerCapture(e.pointerId) } catch {}
        ocultar()
      }}
      onPointerCancel={ocultar}
      onLostPointerCapture={ocultar}
      onPointerLeave={ocultar}
      onBlur={ocultar}
      onKeyDown={(e) => { if (esTecla(e)) { e.preventDefault(); setVista(llave) } }}
      onKeyUp={(e) => { if (esTecla(e)) { e.preventDefault(); ocultar() } }}
      onContextMenu={(e) => e.preventDefault()}>
      {visible
        ? <span className="anim-carta__texto">{texto}</span>
        : 'Mantén apretado para ver la frase (solo quien actúa)'}
    </button>
  )
}

function Cronometro({ segundos, reinicio }) {
  const [quedan, setQuedan] = useState(segundos)
  const [corre, setCorre] = useState(false)
  useEffect(() => { setQuedan(segundos); setCorre(false) }, [segundos, reinicio])
  useEffect(() => {
    if (!corre) return undefined
    const id = setInterval(() => setQuedan((q) => {
      if (q <= 1) { setCorre(false); return 0 }
      return q - 1
    }), 1000)
    return () => clearInterval(id)
  }, [corre])
  if (!segundos) return null
  return (
    <div className={`anim-crono${quedan === 0 ? ' is-fin' : ''}`}>
      <span className="anim-crono__num" aria-live="polite">{quedan === 0 ? '¡Tiempo!' : `${quedan} s`}</span>
      <button type="button" className="anim-chip" onClick={() => setCorre((c) => !c)} disabled={quedan === 0}>
        {corre ? '⏸ Pausar' : '▶ Iniciar'}
      </button>
      <button type="button" className="anim-chip" onClick={() => { setCorre(false); setQuedan(segundos) }}>↺</button>
    </div>
  )
}

function Ronda({ sesion, setSesion, juego, t, onVolver }) {
  const carta = juego.cartas.find((c) => c.id === sesion.carta) || null
  const bloqueo = useRef(0)
  // Un toque cada 500 ms: un dedo nervioso no suma dos veces ni salta dos tarjetas.
  const una = (fn) => () => {
    const ahora = Date.now()
    if (ahora - bloqueo.current < 500) return
    bloqueo.current = ahora
    fn()
  }
  const valores = juego.puntos === 'rubrica' ? [1, 2, 3] : juego.puntos === 'lanzamiento' ? [1, 2, 3] : juego.puntos === 'acierto' ? [1] : []
  const ultimo = sesion.puntos[sesion.puntos.length - 1]
  return (
    <section className="screen anim anim--ronda">
      <Cabecera sesion={sesion} setSesion={setSesion} juego={juego} />
      <h2 className="anim-titulo"><span aria-hidden="true">{juego.emoji}</span> {t(juego.nombre)}</h2>
      <p className="anim-instr">{t(juego.instrucciones)}</p>
      {juego.nota && <p className="anim-nota">{t(juego.nota)}</p>}

      {juego.sinCartas ? (
        <div className="anim-carta anim-carta--fisico">Lanzamiento {sesion.ronda - (sesion.rondaInicio || sesion.ronda) + 1}</div>
      ) : sesion.agotado ? (
        <div className="anim-carta">
          <p>Se acabaron las tarjetas de este nivel.</p>
          <button type="button" className="cta" onClick={una(() => setSesion((s) => reiniciarMazo(s, juego)))}>Barajar de nuevo</button>
        </div>
      ) : carta && juego.privado ? (
        <CartaPrivada llave={`${carta.id}|${sesion.nivel}`} texto={t(carta.texto)} />
      ) : carta ? (
        <div className="anim-carta">
          <p className="anim-carta__texto">{t(carta.texto)}</p>
          {carta.respuesta && (sesion.revelada
            ? <p className="anim-carta__resp">{t(carta.respuesta)}</p>
            : <button type="button" className="anim-chip" onClick={una(() => setSesion(revelar))}>👀 Revelar respuesta</button>)}
        </div>
      ) : null}

      <Cronometro segundos={juego.segundos} reinicio={sesion.ronda} />

      {valores.length > 0 && (
        <div className="anim-puntos">
          {sesion.equipos.map((e, i) => (
            <div key={i} className="anim-puntos__fila">
              <span>{e}</span>
              {valores.map((v) => (
                <button key={v} type="button" className="anim-chip" disabled={yaAnoto(sesion, i)}
                  onClick={una(() => setSesion((s) => anotar(s, i, v)))}>+{v}</button>
              ))}
            </div>
          ))}
          {ultimo && (
            <button type="button" className="anim-chip anim-chip--deshacer" onClick={una(() => setSesion(deshacer))}>
              ↶ Deshacer +{ultimo.valor} de {sesion.equipos[ultimo.equipo]}
            </button>
          )}
        </div>
      )}

      <p className="anim-nota">{PASO_ESTA}</p>
      <div className="anim-pie">
        {juego.sinCartas ? (
          <button type="button" className="cta" onClick={una(() => setSesion(nuevaRonda))}>Siguiente lanzamiento ➜</button>
        ) : (
          <>
            <button type="button" className="cta" disabled={sesion.agotado} onClick={una(() => setSesion(siguiente))}>Siguiente ➜</button>
            <button type="button" className="cta ghost" disabled={sesion.agotado} onClick={una(() => setSesion(pasar))}>🙅 Paso esta</button>
          </>
        )}
        <button type="button" className="cta ghost" onClick={onVolver}>☰ Otros juegos</button>
      </div>
      <p className="anim-premio">Premio: {t(juego.premio)}</p>
    </section>
  )
}

function Cierre({ sesion, onFotoGrupal, onSeguir, onSalir, onNueva }) {
  const totales = marcador(sesion)
  const quienes = ganadores(sesion)
  return (
    <section className="screen anim">
      <h2 className="anim-titulo">{quienes.length ? '¡Caso cerrado! Tenemos equipo ganador' : 'Caso cerrado'}</h2>
      {quienes.length > 0 && <p className="anim-ganador">🏆 {quienes.join(' y ')}</p>}
      <ol className="anim-tabla">
        {sesion.equipos
          .map((e, i) => ({ e, p: totales[i] }))
          .sort((a, b) => b.p - a.p)
          .map(({ e, p }, i) => <li key={i}><span>{e}</span><b>{p}</b></li>)}
      </ol>
      <p className="anim-nota">Premios de broma: Cómplice de honor · Piropo del año · Dueña o dueño del swing · Rey o reina del doble sentido.</p>
      <div className="anim-pie">
        {onFotoGrupal && <button type="button" className="cta" onClick={onFotoGrupal}>📸 Foto de todos los cómplices</button>}
        <button type="button" className="cta ghost" onClick={onSeguir}>🎲 Seguir jugando</button>
        <button type="button" className="cta ghost" onClick={onNueva}>Partida nueva</button>
        <button type="button" className="cta ghost" onClick={onSalir}>Salir</button>
      </div>
    </section>
  )
}
