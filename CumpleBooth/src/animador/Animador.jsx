import React, { useEffect, useMemo, useRef, useState } from 'react'
import { rellenar } from '../cumpleAdulto.js'
import { EQUIPOS_SUGERIDOS, JUEGOS, NIVEL_TEXTO, PASO_ESTA } from './mazos.js'
import {
  NIVELES, abrirJuego, anotar, cambiarNivel, deshacer, ganadores, juegosDelNivel, marcador,
  nuevaRonda, nuevaSesion, pasar, reiniciarMazo, revelar, siguiente, yaAnoto,
} from './motor.js'

/**
 * Juegos del animador (AT-CUMPLECLICK-026). Un animador conduce desde la tablet; la cabina de
 * fotos sigue disponible entre juegos. Solo el marcador y los equipos se recuerdan en esta
 * pestaña (sessionStorage), para que un recargón no borre la partida; nunca respuestas.
 */
export default function Animador({ nombre = '', edad = null, clave = 'animador', onSalir, onFotoGrupal }) {
  const datos = { nombre, edad }
  const t = (texto) => rellenar(texto, datos)
  const guardada = useMemo(() => {
    try { return JSON.parse(sessionStorage.getItem(clave) || 'null') } catch { return null }
  }, [clave])
  const [sesion, setSesion] = useState(guardada?.equipos ? guardada : null)
  const [pantalla, setPantalla] = useState(guardada?.equipos ? 'juegos' : 'preparar')

  useEffect(() => {
    if (!sesion) return
    // Solo lo necesario para retomar: nivel, equipos y puntos. Nada de lo que se dijo en la ronda.
    const { nivel, equipos, puntos, ronda } = sesion
    try { sessionStorage.setItem(clave, JSON.stringify({ nivel, equipos, puntos, ronda, mazo: [], carta: null, juego: null })) } catch {}
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
  const disponibles = juegosDelNivel(JUEGOS, sesion.nivel)
  return (
    <section className="screen anim">
      <Cabecera sesion={sesion} setSesion={setSesion} />
      <h2 className="anim-titulo">Entre cómplices: ¿a qué jugamos?</h2>
      <div className="anim-juegos">
        {disponibles.map((j) => (
          <button key={j.id} type="button" className="anim-juego"
            onClick={() => { setSesion((s) => abrirJuego(s, j)); setPantalla('juego') }}>
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
  const [ver, setVer] = useState(false)
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
        <div className="anim-carta anim-carta--fisico">Ronda {sesion.ronda}</div>
      ) : sesion.agotado ? (
        <div className="anim-carta">
          <p>Se acabaron las tarjetas de este nivel.</p>
          <button type="button" className="cta" onClick={una(() => setSesion((s) => reiniciarMazo(s, juego)))}>Barajar de nuevo</button>
        </div>
      ) : carta && juego.privado && !ver ? (
        <button type="button" className="anim-carta anim-carta--privada"
          onPointerDown={() => setVer(true)} onPointerUp={() => setVer(false)} onPointerLeave={() => setVer(false)}>
          Mantén apretado para ver la frase (solo quien actúa)
        </button>
      ) : carta ? (
        <div className="anim-carta" onPointerUp={() => setVer(false)}>
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
          .map(({ e, p }) => <li key={e}><span>{e}</span><b>{p}</b></li>)}
      </ol>
      <p className="anim-nota">Premios de broma: Cómplice de honor · Piropo del año · Dueña del swing · Rey del doble sentido.</p>
      <div className="anim-pie">
        {onFotoGrupal && <button type="button" className="cta" onClick={onFotoGrupal}>📸 Foto de todos los cómplices</button>}
        <button type="button" className="cta ghost" onClick={onSeguir}>🎲 Seguir jugando</button>
        <button type="button" className="cta ghost" onClick={onNueva}>Partida nueva</button>
        <button type="button" className="cta ghost" onClick={onSalir}>Salir</button>
      </div>
    </section>
  )
}
