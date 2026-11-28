import React, { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react'
import { rellenar } from '../cumpleAdulto.js'
import { EQUIPOS_SUGERIDOS, JUEGOS, NIVEL_TEXTO, PASO_ESTA } from './mazos.js'
import {
  NIVELES, abrirJuego, alternativaDisponible, anotar, cambiarNivel, cartasDelNivel, categoriasDelNivel, confirmarPreparacion,
  elegirCategoria, pendientesPorCategoria,
  deshacer, ganadores, juegosActivos, juegosDelNivel, marcador, necesitaPreparacion, nivelesDelJuego, nuevaRonda,
  nuevaSesion, pasar, reiniciarMazo, restaurarSesion, revelar, serializarSesion, siguiente, yaAnoto,
} from './motor.js'
import {
  RULETA, RULETA_EMOJIS, RULETA_SIN_LISTA, candidatosRuleta, claveCarta, claveRespuesta, claveRuleta, claveTiempo, claveTransicion, presentacion, seLee,
  tieneRespuesta, vozDelJuego,
} from './vocesGuion.js'
import { esperarGiro, useVoz } from './voz.js'

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
 *
 * `apagados`: ids de juegos que el admin apagó para esta fiesta (`party.animadorApagados`). No
 * aparecen en la lista ni como alternativa, y si la partida apunta a uno se vuelve a la lista.
 *
 * `voces`: URL del índice de audios de la fiesta (vocesGuion.js). Con voces, cada juego abre con una
 * presentación hablada (intro, equipos y «presionemos comenzar», con «Omitir») y cada tarjeta se lee.
 * Sin índice, la presentación igual se muestra en texto y todo lo demás queda como antes.
 */
export default function Animador({ nombre = '', edad = null, clave = 'animador', apagados = [], baseImagenes = '', voces = null, invitados = [], onSalir, onFotoGrupal }) {
  const datos = { nombre, edad }
  const t = (texto) => rellenar(texto, datos)
  const activos = useMemo(() => juegosActivos(JUEGOS, apagados), [apagados])
  const [guardada] = useState(() => leerGuardada(clave))
  const [sesion, setSesion] = useState(guardada)
  const [pantalla, setPantalla] = useState(guardada ? 'juegos' : 'preparar')
  const [porPreparar, setPorPreparar] = useState(null) // id del juego que espera su preparación
  const { abrirFicha, ficha } = useFichaJuego(t)
  const voz = useVoz(voces, clave + '_voz')
  const salir = () => { voz.callar(); onSalir?.() }

  useEffect(() => {
    if (!sesion) return
    // Solo lo necesario para retomar. Nada de lo que se dijo en la ronda.
    try { sessionStorage.setItem(clave, JSON.stringify(serializarSesion(sesion))) } catch {}
  }, [sesion, clave])

  // Sin juegos activos no hay nada que preparar: un aviso claro en vez de una lista vacía.
  if (activos.length === 0) {
    return (
      <section className="screen anim">
        <h2 className="anim-titulo">Juegos de la fiesta</h2>
        <p className="anim-instr" role="status">Para esta fiesta no quedan juegos activos: se apagaron todos desde el panel de administración.</p>
        <div className="anim-pie">
          <button type="button" className="cta ghost" onClick={salir}>📸 Volver a la cabina</button>
        </div>
      </section>
    )
  }
  if (pantalla === 'preparar' || !sesion) {
    return (
      <Preparar
        onListo={(nivel, equipos) => { setSesion(nuevaSesion({ nivel, equipos })); setPantalla('juegos') }}
        onSalir={salir}
        t={t}
      />
    )
  }
  // Un juego apagado no se abre aunque la partida apunte a él: cae a la lista.
  const juego = activos.find((j) => j.id === sesion.juego) || null
  if (pantalla === 'cierre') {
    return <Cierre sesion={sesion} onFotoGrupal={onFotoGrupal} onSeguir={() => setPantalla('juegos')} onSalir={salir}
      onNueva={() => { try { sessionStorage.removeItem(clave) } catch {}; setSesion(null); setPantalla('preparar') }} />
  }
  if (pantalla === 'presentacion' && juego) {
    return (
      <Presentacion key={juego.id} juego={juego} pasos={presentacion(JUEGOS, juego, sesion.equipos, EQUIPOS_SUGERIDOS, datos)}
        voz={voz} onListo={() => { voz.callar(); setPantalla('juego') }} />
    )
  }
  if (pantalla === 'juego' && juego) {
    return (
      <>
        <Ronda sesion={sesion} setSesion={setSesion} juego={juego} t={t} onVolver={() => { voz.callar(); setPantalla('juegos') }}
          onInfo={abrirFicha(juego)} baseImagenes={baseImagenes} voz={voz} invitados={invitados} />
        {ficha}
      </>
    )
  }
  const abrir = (j) => {
    if (necesitaPreparacion(sesion, j)) { setPorPreparar(j.id); setPantalla('preparar-juego'); return }
    setSesion((s) => abrirJuego(s, j))
    setPantalla('presentacion')
  }
  const pendiente = activos.find((j) => j.id === porPreparar) || null
  if (pantalla === 'preparar-juego' && pendiente) {
    return (
      <>
      <PrepararJuego sesion={sesion} setSesion={setSesion} juego={pendiente} t={t} juegos={activos}
        onInfo={abrirFicha(pendiente)}
        onConfirmar={() => {
          setSesion((s) => abrirJuego(confirmarPreparacion(s, pendiente), pendiente))
          setPorPreparar(null)
          setPantalla('presentacion')
        }}
        onAlternativa={(alt) => { setPorPreparar(null); abrir(alt) }}
        onVolver={() => { setPorPreparar(null); setPantalla('juegos') }} />
      {ficha}
      </>
    )
  }
  const disponibles = juegosDelNivel(activos, sesion.nivel)
  return (
    <section className="screen anim">
      <Cabecera sesion={sesion} setSesion={setSesion} voz={voz} />
      <h2 className="anim-titulo">Entre cómplices: ¿a qué jugamos?</h2>
      {disponibles.length === 0 ? (
        <p className="anim-instr" role="status">
          En el nivel {NIVEL_TEXTO[sesion.nivel].nombre} no queda ningún juego activo. Sube el nivel arriba o vuelve a la cabina.
        </p>
      ) : (
        <div className="anim-juegos">
          {disponibles.map((j) => (
            <div key={j.id} className="anim-juego-fila">
              <button type="button" className="anim-juego" onClick={() => abrir(j)}>
                <span className="anim-juego__emoji" aria-hidden="true">{j.emoji}</span>
                <span>{t(j.nombre)}</span>
              </button>
              <BotonInfo juego={j} t={t} onClick={abrirFicha(j)} />
            </div>
          ))}
        </div>
      )}
      <div className="anim-pie">
        <button type="button" className="cta" onClick={() => setPantalla('cierre')}>🏆 Premios y cierre</button>
        <button type="button" className="cta ghost" onClick={salir}>📸 Volver a la cabina</button>
      </div>
      {ficha}
    </section>
  )
}

/** El botón ℹ️ de cada juego: abre su guía para quien conduce. */
function BotonInfo({ juego, t, onClick }) {
  const texto = `Cómo se juega: ${t(juego.nombre)}`
  return (
    <button type="button" className="anim-info" aria-label={texto} title={texto} aria-haspopup="dialog" onClick={onClick}>
      <span aria-hidden="true">ℹ️</span>
    </button>
  )
}

/** La guía abierta y quién la abrió. Al cerrar, el foco vuelve a ese botón (si sigue en pantalla). */
function useFichaJuego(t) {
  const [abierta, setAbierta] = useState(null)
  const origen = useRef(null)
  const abrirFicha = (juego) => (e) => {
    origen.current = e?.currentTarget || null
    setAbierta(juego)
  }
  const cerrar = useCallback(() => {
    setAbierta(null)
    const el = origen.current
    origen.current = null
    if (el) setTimeout(() => { if (el.isConnected) el.focus() }, 0)
  }, [])
  return { abrirFicha, ficha: abierta ? <FichaJuego juego={abierta} t={t} onCerrar={cerrar} /> : null }
}

const listaDeNiveles = (niveles) => {
  const nombres = niveles.map((n) => NIVEL_TEXTO[n].nombre)
  return nombres.length > 1 ? `${nombres.slice(0, -1).join(', ')} y ${nombres.at(-1)}` : nombres[0] || ''
}

/**
 * Ficha de un juego para quien conduce: de qué se trata, pasos, duración, quiénes juegan, puntaje,
 * qué preparar y en qué niveles aparece. Se cierra con «Entendido», con Escape o tocando fuera; el
 * Tab no se escapa de la ficha mientras está abierta. Tiene scroll propio en pantallas chicas.
 */
function FichaJuego({ juego, t, onCerrar }) {
  const caja = useRef(null)
  const boton = useRef(null)
  const titulo = useId()
  const g = juego.guia || { resumen: '', pasos: [juego.instrucciones] }

  useEffect(() => {
    boton.current?.focus()
    const tecla = (e) => {
      if (e.key === 'Escape') { e.preventDefault(); onCerrar(); return }
      if (e.key !== 'Tab' || !caja.current) return
      const focos = [...caja.current.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])')]
      if (!focos.length) return
      const primero = focos[0]
      const ultimo = focos[focos.length - 1]
      if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus() }
      else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus() }
      else if (!caja.current.contains(document.activeElement)) { e.preventDefault(); primero.focus() }
    }
    document.addEventListener('keydown', tecla)
    return () => document.removeEventListener('keydown', tecla)
  }, [onCerrar])

  const datos = [
    ['Duración', g.duracion],
    ['Quiénes juegan', g.jugadores],
    ['Cómo se puntúa', g.puntaje],
    ['Qué preparar', g.preparar],
    ['Aparece en los niveles', listaDeNiveles(nivelesDelJuego(juego))],
  ].filter(([, v]) => v)

  return (
    <div className="anim-ficha-velo" onClick={onCerrar}>
      <div className="anim-ficha" role="dialog" aria-modal="true" aria-labelledby={titulo} ref={caja}
        onClick={(e) => e.stopPropagation()}>
        <h2 id={titulo} className="anim-ficha__titulo"><span aria-hidden="true">{juego.emoji}</span> {t(juego.nombre)}</h2>
        {g.resumen && <p className="anim-ficha__resumen">{t(g.resumen)}</p>}
        <h3 className="anim-ficha__sub">Cómo se juega</h3>
        <ol className="anim-ficha__pasos">
          {g.pasos.map((paso, i) => <li key={i}>{t(paso)}</li>)}
        </ol>
        <dl className="anim-ficha__datos">
          {datos.map(([k, v]) => (
            <div key={k}><dt>{k}</dt><dd>{t(v)}</dd></div>
          ))}
        </dl>
        <p className="anim-ficha__nota">{PASO_ESTA} Nadie está obligado a participar.</p>
        <button ref={boton} type="button" className="cta" onClick={onCerrar}>Entendido</button>
      </div>
    </div>
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

function Cabecera({ sesion, setSesion, juego = null, voz = null }) {
  const totales = marcador(sesion)
  return (
    <header className="anim-cabecera">
      {voz?.disponible && (
        <button type="button" className="anim-chip anim-voz" aria-pressed={!voz.silencio}
          aria-label={voz.silencio ? 'Activar la voz' : 'Silenciar la voz'} onClick={() => voz.setSilencio(!voz.silencio)}>
          {voz.silencio ? '🔇' : '🔊'}
        </button>
      )}
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
function PrepararJuego({ sesion, setSesion, juego, t, juegos, onInfo, onConfirmar, onAlternativa, onVolver }) {
  const prep = juego.preparacion
  // Solo entre los juegos activos: si la alternativa está apagada, no se ofrece.
  const alt = alternativaDisponible(juegos, juego, sesion.nivel)
  const altJuego = juegos.find((j) => j.id === prep.alternativa) || null
  const altNivel = altJuego ? NIVELES.find((n) => cartasDelNivel(altJuego, n).length > 0) : null
  return (
    <section className="screen anim">
      <Cabecera sesion={sesion} setSesion={setSesion} />
      <div className="anim-titulo-fila">
        <h2 className="anim-titulo"><span aria-hidden="true">{juego.emoji}</span> {t(juego.nombre)}</h2>
        <BotonInfo juego={juego} t={t} onClick={onInfo} />
      </div>
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
 * Presentación hablada de un juego: el texto que suena va al centro, paso a paso (intro, cada equipo y
 * «presionemos comenzar»). «Omitir» salta todo en cualquier momento; «Comenzar» aparece al final. Sin
 * voz, cada paso queda en pantalla un momento para alcanzar a leerlo.
 */
function Presentacion({ juego, pasos, voz, onListo }) {
  const [i, setI] = useState(0)
  const [fin, setFin] = useState(false)
  useEffect(() => {
    let vivo = true
    const esperar = (ms) => new Promise((r) => setTimeout(r, ms))
    ;(async () => {
      for (let k = 0; k < pasos.length && vivo; k++) {
        setI(k)
        const p = pasos[k]
        const sono = voz.tiene(p.clave) && !voz.silencio ? await voz.decir(p.clave) : false
        if (!sono && vivo) await esperar(Math.min(4500, 1200 + p.texto.length * 35))
      }
      if (vivo) setFin(true)
    })()
    return () => { vivo = false; voz.callar() }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])
  const paso = pasos[Math.min(i, pasos.length - 1)]
  return (
    <section className="screen anim anim-presenta" aria-live="polite">
      <span className="anim-presenta__emoji" aria-hidden="true">{juego.emoji}</span>
      <p key={i} className="anim-presenta__texto">{paso.texto}</p>
      <div className="anim-presenta__puntos" aria-hidden="true">
        {pasos.map((_, k) => <i key={k} className={k <= i ? 'is-on' : ''} />)}
      </div>
      <div className="anim-pie">
        {fin
          ? <button type="button" className="cta" onClick={onListo} autoFocus>▶ Comenzar</button>
          : <button type="button" className="cta ghost" onClick={onListo}>⏭ Omitir</button>}
      </div>
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

function Cronometro({ segundos, reinicio, onFin }) {
  const [quedan, setQuedan] = useState(segundos)
  const [corre, setCorre] = useState(false)
  const avisado = useRef(false)
  useEffect(() => { setQuedan(segundos); setCorre(false); avisado.current = false }, [segundos, reinicio])
  // Al llegar a cero, una sola vez por vuelta: «¡Se acabó el tiempo!» y la respuesta, si la hay.
  useEffect(() => {
    if (quedan === 0 && segundos && !avisado.current) { avisado.current = true; onFin?.() }
  }, [quedan, segundos, onFin])
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
      <button type="button" className="anim-chip" onClick={() => { setCorre(false); setQuedan(segundos); avisado.current = false }}>↺</button>
    </div>
  )
}

/**
 * Imagen de una tarjeta (tipo 'imagen'): se prueba al mostrar la tarjeta y, si carga, un botón la
 * abre a pantalla completa; otro toque (o Escape) la cierra. Si no está o falla, la tarjeta dice que
 * se muestre desde el celular y el juego sigue igual.
 */
function ImagenDeTarjeta({ src }) {
  const [estado, setEstado] = useState('probando') // probando | lista | falta
  const [abierta, setAbierta] = useState(false)
  useEffect(() => {
    let vivo = true
    const img = new Image()
    img.onload = () => { if (vivo) setEstado(img.naturalWidth > 0 ? 'lista' : 'falta') }
    img.onerror = () => { if (vivo) setEstado('falta') }
    img.src = src
    return () => { vivo = false; img.onload = null; img.onerror = null }
  }, [src])
  useEffect(() => {
    if (!abierta) return undefined
    const tecla = (e) => { if (e.key === 'Escape') setAbierta(false) }
    document.addEventListener('keydown', tecla)
    return () => document.removeEventListener('keydown', tecla)
  }, [abierta])
  if (estado === 'falta') return <p className="anim-carta__imagen-falta" role="status">📱 Muestra la imagen en tu celular</p>
  if (estado === 'probando') return <p className="anim-carta__imagen-falta">Cargando la imagen…</p>
  return (
    <>
      <button type="button" className="anim-chip" onClick={() => setAbierta(true)}>🖼️ Mostrar la imagen</button>
      {abierta && (
        <button type="button" className="anim-imagen-grande" onClick={() => setAbierta(false)} aria-label="Cerrar la imagen" autoFocus>
          <img src={src} alt="" />
        </button>
      )}
    </>
  )
}

/**
 * La ruleta del baile: al tocar «¡Girar!» pasan los nombres de los invitados (o frases, sin lista) cada vez más lento
 * hasta detenerse; sale si baila sin pareja o en pareja (y con quién) y la canción que pone el DJ, y la voz lo anuncia.
 * «Otra persona» gira de nuevo con la misma canción. Evita repetir a quien bailó hace poco.
 */
function RuletaBaile({ carta, t, voz, invitados, recientes }) {
  // Las canciones jocosas con destinatario (`para`) giran solo entre los hombres o solo entre las mujeres.
  const lista = candidatosRuleta(invitados, carta.para)
  const nombres = lista.length ? lista.map((i) => i.name) : RULETA_SIN_LISTA
  const todosLosNombres = candidatosRuleta(invitados).map((i) => i.name)
  const [fase, setFase] = useState('listo') // listo | girando | resultado
  const [visor, setVisor] = useState(null)
  const [res, setRes] = useState(null)
  const relojes = useRef([])
  const espera = useRef(null) // cancela la espera de la voz de un giro anterior
  const vueltas = useRef(0)
  const limpiar = () => {
    relojes.current.forEach(clearTimeout); relojes.current = []
    if (espera.current) { espera.current(); espera.current = null }
  }
  useEffect(() => limpiar, [])
  const azar = (lista) => lista[Math.floor(Math.random() * lista.length)]
  const emoji = () => (lista.length ? azar(RULETA_EMOJIS) : '')
  const girar = (otra) => {
    limpiar()
    const n = vueltas.current++
    setFase('girando')
    setRes(null)
    // Al girar de nuevo basta con «¿No quiere? ¡Tranquilos, giramos otra vez!»: sumarle la frase del giro duraba 7,7 s.
    const hablando = voz.decir(otra ? claveRuleta('otra') : claveRuleta('gira', n))
    const libres = nombres.filter((x) => !recientes.current.includes(x))
    const persona = azar(libres.length ? libres : nombres)
    const otros = todosLosNombres.filter((x) => x !== persona)
    const pareja = otros.length > 0 && Math.random() < 0.5 ? azar(otros) : null
    recientes.current = [persona, ...recientes.current].slice(0, Math.max(0, Math.min(nombres.length - 1, Math.floor(nombres.length / 2))))
    const pasos = REDUCE_MOVIMIENTO() ? 3 : 22
    let tiempo = 0
    for (let i = 0; i < pasos; i++) {
      tiempo += 60 + i * i * 0.6 // cada vez más lento, como una ruleta que frena (unos 3,5 s en total)
      relojes.current.push(setTimeout(() => setVisor({ nombre: azar(nombres), emoji: emoji(), k: i }), tiempo))
    }
    // El resultado espera a que la voz termine su frase: si la ruleta ya frenó, sigue pasando nombres despacio.
    let tic = 0
    espera.current = esperarGiro({
      voz: hablando,
      minimo: tiempo + 350,
      onTic: () => setVisor({ nombre: azar(nombres), emoji: emoji(), k: `mas${tic++}` }),
      onFin: () => {
        espera.current = null
        setVisor({ nombre: persona, emoji: emoji() || '💃', k: 'fin' })
        setRes({ persona, pareja })
        setFase('resultado')
        voz.decir([claveRuleta(pareja ? 'pareja' : 'solo'), claveCarta(carta)])
      },
    })
  }
  return (
    <div className={`anim-ruleta${fase === 'resultado' ? ' is-listo' : ''}`}>
      <div className={`anim-ruleta__visor${fase === 'girando' ? ' is-gira' : ''}`} aria-live="polite">
        {visor
          ? <>{visor.emoji && <span className="anim-ruleta__emoji" aria-hidden="true">{visor.emoji}</span>}<b key={visor.k} className="anim-ruleta__nombre">{visor.nombre}</b></>
          : <span className="anim-ruleta__espera">🎡 ¿A quién le toca bailar?</span>}
      </div>
      {fase === 'resultado' && res && (
        <>
          <p className="anim-ruleta__modo">{res.pareja ? `💃🕺 Baila en pareja con ${res.pareja}` : '💃 ¡Baila sin pareja, pura actitud!'}</p>
          <p className="anim-carta__texto">{t(carta.texto)}</p>
          <p className="anim-carta__pista"><span aria-hidden="true">🎵</span> Pídele al DJ {carta.pista}. La app no trae audio.</p>
        </>
      )}
      <div className="anim-pie">
        {fase === 'listo' && <button type="button" className="cta" onClick={() => girar(false)}>🎡 ¡Girar!</button>}
        {fase === 'resultado' && <button type="button" className="cta ghost" onClick={() => girar(true)}>🔄 Otra persona</button>}
      </div>
    </div>
  )
}

const REDUCE_MOVIMIENTO = () => {
  try { return window.matchMedia('(prefers-reduced-motion: reduce)').matches } catch { return false }
}

function Ronda({ sesion, setSesion, juego, t, onVolver, onInfo, baseImagenes = '', voz, invitados = [] }) {
  const carta = juego.cartas.find((c) => c.id === sesion.carta) || null
  const vozJuego = vozDelJuego(JUEGOS, juego)
  const sinTarjetaVoz = Boolean(sesion.agotado || sesion.categoriaAgotada)
  // Cómo se llegó a la tarjeta: con «Paso esta» o «Siguiente» la voz reacciona antes de leerla («¡Uy, esa no nos
  // gustó! Vamos a la siguiente pregunta»). Al entrar al juego o al cambiar de categoría, no.
  const transicion = useRef(null)
  // Cada tarjeta nueva se lee al aparecer (las privadas nunca). Al revelarse, la otra voz da la respuesta.
  useEffect(() => {
    const tipo = transicion.current
    transicion.current = null
    if (!carta || sinTarjetaVoz || juego.ruleta) return
    const claves = [tipo ? claveTransicion(tipo, vozJuego, sesion.ronda) : null, seLee(juego, carta) ? claveCarta(carta) : null].filter(Boolean)
    if (claves.length) voz.decir(claves)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [carta?.id, sinTarjetaVoz])
  const avanzar = (tipo) => { transicion.current = tipo; setSesion(tipo === 'pasar' ? pasar : siguiente) }
  const recientes = useRef([]) // la ruleta evita repetir a quien acaba de bailar
  const revelada = Boolean(sesion.revelada)
  const yaLeida = useRef(null)
  useEffect(() => {
    if (!revelada || !carta || !tieneRespuesta(carta) || yaLeida.current === carta.id) return
    yaLeida.current = carta.id
    voz.decir(claveRespuesta(carta))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [revelada, carta?.id])
  const alTerminarReloj = useCallback(() => {
    const revela = Boolean(carta && tieneRespuesta(carta) && !sesion.revelada && !juego.privado)
    const destapar = () => { if (revela) setSesion((s) => (s.carta === carta.id ? revelar(s) : s)) }
    if (voz.tiene(claveTiempo(vozJuego)) && !voz.silencio) voz.decir(claveTiempo(vozJuego)).then(destapar)
    else destapar()
  }, [carta, sesion.revelada, juego.privado, vozJuego, voz, setSesion])
  const bloqueo = useRef(0)
  // Un toque cada 500 ms: un dedo nervioso no suma dos veces ni salta dos tarjetas.
  const una = (fn) => () => {
    const ahora = Date.now()
    if (ahora - bloqueo.current < 500) return
    bloqueo.current = ahora
    fn()
  }
  const categorias = categoriasDelNivel(juego, sesion.nivel)
  const pendientes = pendientesPorCategoria(sesion)
  const sinTarjeta = sesion.agotado || sesion.categoriaAgotada
  const valores = juego.puntos === 'rubrica' ? [1, 2, 3] : juego.puntos === 'lanzamiento' ? [1, 2, 3] : juego.puntos === 'acierto' ? [1] : []
  const ultimo = sesion.puntos[sesion.puntos.length - 1]
  return (
    <section className="screen anim anim--ronda">
      <Cabecera sesion={sesion} setSesion={setSesion} juego={juego} voz={voz} />
      <div className="anim-titulo-fila">
        <h2 className="anim-titulo"><span aria-hidden="true">{juego.emoji}</span> {t(juego.nombre)}</h2>
        <BotonInfo juego={juego} t={t} onClick={onInfo} />
      </div>
      <p className="anim-instr">{t(juego.instrucciones)}</p>
      {juego.nota && <p className="anim-nota">{t(juego.nota)}</p>}
      {categorias.length > 1 && (
        // Elegir categoría (¿Quién es más venezolano?): «Todas» mezcla; una sola, hasta que se cambie.
        <div className="anim-cats" role="group" aria-label="Categoría de las tarjetas">
          {[null, ...categorias].map((k) => {
            const activa = (sesion.categoria || null) === k
            return (
              <button key={k || 'todas'} type="button" className={`anim-chip anim-cat${activa ? ' is-on' : ''}`}
                aria-pressed={activa} onClick={una(() => setSesion((s) => elegirCategoria(s, k)))}>
                {k || 'Todas'}{k && <small className="anim-cat__n" aria-label={`quedan ${pendientes[k] || 0}`}>{pendientes[k] || 0}</small>}
              </button>
            )
          })}
        </div>
      )}

      {juego.sinCartas ? (
        <div className="anim-carta anim-carta--fisico">Lanzamiento {sesion.ronda - (sesion.rondaInicio || sesion.ronda) + 1}</div>
      ) : sesion.agotado ? (
        <div className="anim-carta">
          <p>Se acabaron las tarjetas de este nivel.</p>
          <button type="button" className="cta" onClick={una(() => setSesion((s) => reiniciarMazo(s, juego)))}>Barajar de nuevo</button>
        </div>
      ) : sesion.categoriaAgotada ? (
        <div className="anim-carta" role="status">
          <p className="anim-carta__agotada">Ya salieron todas las de {sesion.categoria}.</p>
          <button type="button" className="cta" onClick={una(() => setSesion((s) => elegirCategoria(s, null)))}>Volver a «Todas»</button>
        </div>
      ) : carta && juego.ruleta ? (
        <RuletaBaile key={carta.id} carta={carta} t={t} voz={voz} invitados={invitados} recientes={recientes} />
      ) : carta && juego.privado ? (
        <CartaPrivada llave={`${carta.id}|${sesion.nivel}`} texto={t(carta.texto)} />
      ) : carta ? (
        <div className="anim-carta">
          {carta.categoria && <p className="anim-carta__cat">{carta.categoria}</p>}
          <p className="anim-carta__texto">{t(carta.texto)}</p>
          {voz.tiene(claveCarta(carta)) && !voz.silencio && (
            <button type="button" className="anim-chip anim-repetir" onClick={() => voz.decir(claveCarta(carta))} aria-label="Leer de nuevo la tarjeta">🔊 Repetir</button>
          )}
          {carta.tipo === 'reto' && <p className="anim-nota anim-nota--reto">Sin respuesta: el punto lo decide el aplauso.</p>}
          {carta.tipo === 'musica' && (
            <p className="anim-carta__pista"><span aria-hidden="true">🎵</span> Pon en el parlante {carta.pista} o pídeselo al DJ. La app no trae audio.</p>
          )}
          {carta.tipo === 'imagen' && <ImagenDeTarjeta key={carta.id} src={baseImagenes + carta.imagen} />}
          {carta.respuesta && carta.tipo !== 'reto' && (sesion.revelada
            ? <p className="anim-carta__resp">{t(carta.respuesta)}</p>
            : <button type="button" className="anim-chip" onClick={una(() => setSesion(revelar))}>👀 Revelar respuesta</button>)}
        </div>
      ) : null}

      {/* key: cada tarjeta o ronda monta un reloj nuevo; si no, el cero de la anterior disparaba el fin de la
          nueva antes de iniciarla y revelaba su respuesta (revisión B de Codex, CX-30). */}
      <Cronometro key={`${sesion.ronda}|${sesion.carta}`} segundos={juego.segundos} reinicio={`${sesion.ronda}|${sesion.carta}`} onFin={alTerminarReloj} />

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
            <button type="button" className="cta" disabled={sinTarjeta} onClick={una(() => avanzar('siguiente'))}>{juego.ruleta ? 'Siguiente canción ➜' : 'Siguiente ➜'}</button>
            {!juego.ruleta && <button type="button" className="cta ghost" disabled={sinTarjeta} onClick={una(() => avanzar('pasar'))}>🙅 Paso esta</button>}
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
