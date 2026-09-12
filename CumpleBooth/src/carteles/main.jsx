// Carteles con QR para imprimir, uno por enlace de la fiesta.
//
// Cada fiesta tiene sus propios enlaces y su temática, así que los carteles se arman por
// fiesta: los datos vienen de admin/carteles-api.php, que exige sesión de admin porque el
// cartel de la galería lleva el PIN.
//
// Se imprime desde el navegador (no se genera PDF): `@page size` toma la medida elegida y
// cada cartel ocupa una hoja exacta, para que salga a escala real dentro del acrílico.
import { Fragment, StrictMode, useEffect, useMemo, useState } from 'react'
import { createRoot } from 'react-dom/client'
import QRCode from 'qrcode'
import './carteles.css'

const BASE = import.meta.env.BASE_URL

// Medidas en milímetros. Las de acrílico son las de portarretratos y portamenús comunes.
const TAMANOS = [
  /*
   * El soporte que Luis compró de verdad, medido con regla: 15 cm de ancho por 21 de alto.
   * Va primero y es el que arranca elegido.
   *
   * Se imprime a 146 × 206 y no a 150 × 210 a propósito: esos 15 × 21 son la medida de AFUERA
   * del acrílico, y la ranura donde entra la hoja es siempre algo menor. Dejar 2 mm por lado
   * hace que entre sin forzar. Una hoja un poco más chica se ve igual —el acrílico la
   * enmarca—; una más grande no entra, y esa es justamente la falla que hubo que corregir.
   */
  { id: 'porta-menu-15x21', nombre: 'Porta menú 15 × 21 cm · el nuestro', ancho: 146, alto: 206 },
  { id: 'a5', nombre: 'A5 · 14,8 × 21 cm', ancho: 148, alto: 210 },
  // Media carta = media hoja carta (5,5 × 8,5 pulgadas). Es la medida de los porta menú
  // de acrílico más comunes, y NO es A5: son 8 mm más angosta y 6 mm más alta.
  // 🔴 Con 216 mm de alto NO entra en el porta menú de 21 cm: sobresale 6 mm. Fue el error
  // de la primera impresión, cuando esta era la medida por defecto.
  { id: 'media-carta', nombre: 'Media carta · 14 × 21,6 cm (no entra en el nuestro)', ancho: 140, alto: 216 },
  { id: 'a6', nombre: 'A6 · 10,5 × 14,8 cm', ancho: 105, alto: 148 },
  { id: '10x15', nombre: 'Foto 10 × 15 cm', ancho: 100, alto: 150 },
  { id: '13x18', nombre: 'Foto 13 × 18 cm', ancho: 130, alto: 180 },
  { id: '15x15', nombre: 'Cuadrado 15 × 15 cm', ancho: 150, alto: 150 },
  { id: '20x25', nombre: 'Marco 20 × 25 cm', ancho: 200, alto: 250 },
  { id: 'a4', nombre: 'A4 · 21 × 29,7 cm', ancho: 210, alto: 297 },
]

// Dos maneras de mostrar la temática, las dos imprimibles: Luis elige por fiesta.
const ESTILOS = [
  { id: 'agua', nombre: 'Fondo completo (marco de agua)' },
  { id: 'cabecera', nombre: 'Cabecera con la temática' },
]

// Las fechas vienen del servidor en UTC (YYYY-MM-DD HH:MM:SS); acá solo interesa el día.
function fechaCorta(iso) {
  const d = new Date(String(iso).replace(' ', 'T') + 'Z')
  return Number.isNaN(d.getTime()) ? String(iso) : d.toLocaleDateString('es-CL')
}

function useQr(url) {
  const [dataUrl, setDataUrl] = useState(null)
  const [error, setError] = useState(false)
  useEffect(() => {
    let vivo = true
    if (!url) return undefined
    // Negro sobre blanco y margen amplio: un QR con los colores de la temática se ve
    // lindo en pantalla y falla al escanear impreso, que es justo donde tiene que servir.
    QRCode.toDataURL(url, { errorCorrectionLevel: 'M', margin: 2, width: 900, color: { dark: '#000000', light: '#FFFFFF' } })
      .then((d) => { if (vivo) setDataUrl(d) })
      .catch(() => { if (vivo) setError(true) })
    return () => { vivo = false }
  }, [url])
  return { dataUrl, error }
}

/**
 * Parte la dirección para que el renglón corte donde la dirección tiene una junta —después
 * de una barra, un signo de pregunta o un guion— y no a mitad de palabra, que es lo que hacía
 * `word-break: break-all` (dejaba cosas como «...galeri / a.php»).
 *
 * Se hace con `<wbr>`, que es una sugerencia: el navegador corta ahí si le sirve y si no,
 * sigue de largo. El CSS acompaña con `overflow-wrap: break-word`, así una dirección sin
 * ninguna junta —un slug larguísimo de una palabra— se parte igual en vez de desbordar.
 */
function urlConCortes(url) {
  const texto = String(url || '')
  const trozos = []
  let actual = ''
  for (const caracter of texto) {
    actual += caracter
    // El separador se queda al final del renglón, como se leen las direcciones escritas.
    if ('/?&=-_'.includes(caracter)) { trozos.push(actual); actual = '' }
  }
  if (actual !== '') { trozos.push(actual) }
  return trozos.map((t, i) => (
    <Fragment key={i}>{t}{i < trozos.length - 1 && <wbr />}</Fragment>
  ))
}

function Cartel({ cartel, fiesta, tema, marca, pin, tamano, estilo: estiloId }) {
  const { dataUrl, error } = useQr(cartel.url)
  const colores = tema.colors || {}
  const estilo = {
    '--acento': colores.accent || '#8B5CF6',
    '--oscuro': colores.dark1 || '#2C1A4A',
    '--tinta': colores.ink || '#241436',
    '--claro': colores.bgLight1 || '#F3EFF7',
    width: `${tamano.ancho}mm`,
    height: `${tamano.alto}mm`,
  }
  return (
    <article className={`cartel cartel--${estiloId}`} style={estilo} data-cartel={cartel.id}>
      {/* La temática se ve entera de fondo (marco de agua) o en una franja de cabecera; en las
          dos, el QR va sobre un recuadro blanco opaco, que es el contraste que necesita. */}
      {tema.banner && <img className="cartel__fondo" src={`${BASE}${tema.banner}`} alt="" />}
      <div className="cartel__velo" />

      <div className="cartel__cuerpo">
        <header className="cartel__cabecera">
          <p className="cartel__fiesta">{fiesta.nombre}</p>
          <p className="cartel__tema">{fiesta.temaNombre}</p>
        </header>

        <div className="cartel__tarjeta">
          <h1 className="cartel__titulo">{cartel.titulo}</h1>
          <p className="cartel__bajada">{cartel.bajada}</p>

          <div className="cartel__qr">
            {dataUrl && <img src={dataUrl} alt={`Código QR: ${cartel.titulo}`} />}
            {error && <p className="cartel__error">No se pudo generar el código. Recarga la página.</p>}
            {!dataUrl && !error && <p className="cartel__espera">Generando el código…</p>}
            {/* La dirección escrita, dentro del recuadro blanco: si la cámara no toma el
                código, o el celular es viejo, se puede tipear. */}
            <p className="cartel__url">{urlConCortes(cartel.url)}</p>
          </div>

          <p className="cartel__instruccion">Apunta la cámara de tu celular al código</p>

          {cartel.necesitaPin && (
            <p className="cartel__pin">
              PIN: <strong>{pin || '••••'}</strong>
            </p>
          )}
          {cartel.pie && !cartel.necesitaPin && <p className="cartel__pie-nota">{cartel.pie}</p>}
        </div>

        <footer className="cartel__pie">
          {/* El isotipo va aparte del nombre: el SVG dibuja solo el globo, la palabra
              "CumpleClick" es texto (misma convención que la galería y el álbum). */}
          <img className="cartel__logo" src={`${BASE}brand/cumpleclick-mark.svg`} alt="" />
          <span className="cartel__marca">{(marca && marca.nombre) || 'CumpleClick'}</span>
          {marca && marca.web && <span className="cartel__web">{marca.web}</span>}
          {/* El glifo va con el arroba: suelto, "@cumpleclick" se lee como un correo. Es
              stroke con currentColor para que siga al texto en los dos estilos de cartel. */}
          {marca && marca.instagram && (
            <span className="cartel__web cartel__ig">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
                   strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
                <rect x="2" y="2" width="20" height="20" rx="5" />
                <circle cx="12" cy="12" r="4" />
                <circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none" />
              </svg>
              {marca.instagram}
            </span>
          )}
        </footer>
      </div>
    </article>
  )
}

function App() {
  const slug = useMemo(() => new URLSearchParams(location.search).get('p') || '', [])
  const [datos, setDatos] = useState(null)
  const [error, setError] = useState(null)
  // Arranca en la medida del porta menú de acrílico, que es el soporte que se usa en la
  // fiesta; el resto de los tamaños sigue disponible en la lista.
  const [tamanoId, setTamanoId] = useState('porta-menu-15x21')
  const [medida, setMedida] = useState({ ancho: 146, alto: 206 })
  const [elegidos, setElegidos] = useState(null)
  const [pin, setPin] = useState('1234')
  const [estilo, setEstilo] = useState('agua')
  const [pidiendoAlbum, setPidiendoAlbum] = useState(false)
  const [errorAlbum, setErrorAlbum] = useState('')
  const [copiado, setCopiado] = useState(false)

  useEffect(() => {
    if (!slug) { setError('Falta la fiesta: abre esta página desde el botón "Carteles QR" del admin.'); return }
    fetch(`${BASE}admin/carteles-api.php?p=${encodeURIComponent(slug)}`, { credentials: 'same-origin', cache: 'no-store' })
      .then(async (r) => {
        const d = await r.json().catch(() => null)
        if (!r.ok || !d || !d.ok) {
          throw new Error(d && d.error === 'no_autenticado'
            ? 'Tu sesión de admin expiró. Entra de nuevo al admin y vuelve a abrir esta página.'
            : (d && d.error) || `error_${r.status}`)
        }
        return d
      })
      .then((d) => { setDatos(d); setElegidos(d.carteles.map((c) => c.id)) })
      .catch((e) => setError(String(e.message || e)))
  }, [slug])

  // El QR del Álbum lleva el token de aportes, que se emite de a uno: pedirlo revoca el
  // anterior y deja muertos los carteles impresos antes. Por eso es un botón aparte y no
  // algo que pase solo con abrir la pantalla.
  async function pedirQrAlbum() {
    setPidiendoAlbum(true)
    setErrorAlbum('')
    try {
      const r = await fetch(`${BASE}admin/carteles-api.php?p=${encodeURIComponent(slug)}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ accion: 'album-token', csrf: datos.csrf || '' }),
      })
      const d = await r.json().catch(() => null)
      if (!r.ok || !d || !d.ok) throw new Error((d && d.error) || `error_${r.status}`)
      setDatos(d)
      setElegidos((prev) => (prev.includes('album') ? prev : [...prev, 'album']))
    } catch (e) {
      setErrorAlbum('No se pudo generar el enlace de aportes: ' + String(e.message || e))
    } finally {
      setPidiendoAlbum(false)
    }
  }

  const tamano = tamanoId === 'custom'
    ? { ancho: Math.max(50, medida.ancho), alto: Math.max(50, medida.alto) }
    : TAMANOS.find((t) => t.id === tamanoId) || TAMANOS[0]

  // `@page size` no se puede poner en una hoja de estilos estática: cambia con lo que se
  // elija, así que la regla se inyecta y se reemplaza en cada cambio.
  useEffect(() => {
    const id = 'regla-pagina'
    let el = document.getElementById(id)
    if (!el) { el = document.createElement('style'); el.id = id; document.head.appendChild(el) }
    el.textContent = `@page { size: ${tamano.ancho}mm ${tamano.alto}mm; margin: 0; }`
  }, [tamano.ancho, tamano.alto])

  if (error) return <div className="aviso aviso--error">{error}</div>
  if (!datos) return <div className="aviso">Cargando los carteles…</div>
  if (!datos.carteles.length) {
    return (
      <div className="aviso">
        Esta fiesta todavía no tiene enlaces para poner en un cartel. Habilita la galería con su PIN,
        crea la invitación, o elige una temática con juego 3D.
      </div>
    )
  }

  const visibles = datos.carteles.filter((c) => elegidos.includes(c.id))
  const enlaceAlbum = (datos.carteles.find((c) => c.id === 'album') || {}).url || ''

  return (
    <>
      <div className="panel">
        <div className="panel__cabecera">
          <h1>Carteles QR · {datos.fiesta.nombre}</h1>
          <p className="panel__nota">
            Elige el tamaño de tu soporte e imprime. Cada cartel sale en una hoja del tamaño exacto,
            a escala real. En el diálogo de impresión deja los márgenes en «ninguno» y desactiva
            «ajustar al papel».
          </p>
        </div>

        <div className="panel__campos">
          <label>
            Tamaño del soporte
            <select value={tamanoId} onChange={(e) => setTamanoId(e.target.value)}>
              {TAMANOS.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
              <option value="custom">A medida…</option>
            </select>
          </label>

          <label>
            Estilo del cartel
            <select value={estilo} onChange={(e) => setEstilo(e.target.value)}>
              {ESTILOS.map((e) => <option key={e.id} value={e.id}>{e.nombre}</option>)}
            </select>
          </label>

          {tamanoId === 'custom' && (
            <>
              <label>
                Ancho (mm)
                <input type="number" min="50" max="420" value={medida.ancho}
                  onChange={(e) => setMedida((m) => ({ ...m, ancho: Number(e.target.value) || 0 }))} />
              </label>
              <label>
                Alto (mm)
                <input type="number" min="50" max="420" value={medida.alto}
                  onChange={(e) => setMedida((m) => ({ ...m, alto: Number(e.target.value) || 0 }))} />
              </label>
            </>
          )}

          {datos.carteles.some((c) => c.necesitaPin) && (
            <label>
              PIN de la galería
              <input type="text" maxLength={4} inputMode="numeric" value={pin}
                onChange={(e) => setPin(e.target.value.replace(/[^0-9]/g, ''))} />
            </label>
          )}
        </div>

        <div className="panel__lista">
          {datos.carteles.map((c) => (
            <label key={c.id} className="panel__check">
              <input
                type="checkbox"
                checked={elegidos.includes(c.id)}
                onChange={() => setElegidos((prev) => prev.includes(c.id) ? prev.filter((x) => x !== c.id) : [...prev, c.id])}
              />
              {c.titulo}
            </label>
          ))}
        </div>

        {/* El panel sigue apareciendo aunque el cartel ya esté armado: es el único lugar
            desde donde se puede rotar el enlace, y antes desaparecía justo cuando el cartel
            existía. Lo que cambia es el texto, porque ya no hay nada que explicar. */}
        {datos.album && (
          <div className="panel__album">
            {!datos.carteles.some((c) => c.id === 'album') && (
              <p className="panel__album-txt">
                <strong>Álbum Recuerdo:</strong> su QR lleva el enlace de aportes, que se emite de a uno.
                Al generarlo, <strong>el anterior queda revocado</strong> y cualquier cartel del Álbum que
                hayas impreso antes deja de servir.
              </p>
            )}
            {!datos.album.existe && (
              <p className="panel__album-txt">
                Esta fiesta todavía no tiene álbum. Créalo en{' '}
                <a href={`${BASE}admin/album.php?p=${encodeURIComponent(slug)}`}>Álbum Recuerdo</a> y vuelve acá.
              </p>
            )}
            {datos.album.existe && !datos.album.abierto && (
              <p className="panel__album-txt">
                Los aportes están cerrados
                {datos.album.motivo === 'fiesta_inactiva' ? ' porque la fiesta no está activa' : ''}.
                Ábrelos en <a href={`${BASE}admin/album.php?p=${encodeURIComponent(slug)}`}>Álbum Recuerdo</a> y
                vuelve acá.
              </p>
            )}
            {datos.album.existe && datos.album.abierto && datos.album.enlaceVivo
              && !datos.carteles.some((c) => c.id === 'album') && (
              <p className="panel__album-txt">
                Ya hay un enlace de aportes activo (emitido el {fechaCorta(datos.album.enlaceVivo.creado)}).
                Si lo compartiste con alguien, generar otro lo deja muerto.
              </p>
            )}
            {datos.album.existe && datos.album.abierto && (
              <button type="button" className="boton boton--claro" onClick={pedirQrAlbum} disabled={pidiendoAlbum}>
                {pidiendoAlbum
                  ? 'Generando…'
                  : (datos.carteles.some((c) => c.id === 'album')
                    ? 'Generar uno nuevo (mata el anterior)'
                    : 'Generar el QR del Álbum')}
              </button>
            )}
            {errorAlbum && <p className="panel__album-txt panel__album-txt--error">{errorAlbum}</p>}
          </div>
        )}

        {datos.avisoAlbum && <p className="panel__aviso">{datos.avisoAlbum}</p>}

        {enlaceAlbum && (
          <div className="panel__enlace">
            <p className="panel__album-txt">
              <strong>Enlace de aportes</strong> — el mismo que lleva el QR del cartel. Sirve para
              mandárselo por WhatsApp a quien no esté en la fiesta.
            </p>
            <input className="panel__enlace-txt" type="text" readOnly value={enlaceAlbum}
              onFocus={(e) => e.target.select()} />
            <div className="panel__acciones">
              <button type="button" className="boton boton--claro" onClick={async () => {
                try { await navigator.clipboard.writeText(enlaceAlbum); setCopiado(true) } catch { setCopiado(false) }
              }}>{copiado ? 'Copiado' : 'Copiar enlace'}</button>
              <a className="boton boton--claro" target="_blank" rel="noopener"
                href={`https://wa.me/?text=${encodeURIComponent(
                  `Sube tus fotos del cumpleaños de ${datos.fiesta.nombre} acá: ${enlaceAlbum}`)}`}>
                Enviar por WhatsApp
              </a>
            </div>
          </div>
        )}

        {/* Los tres ajustes que arruinan la impresión si quedan como vienen: el encabezado
            con la fecha y la URL, el margen que encoge el cartel, y los gráficos de fondo
            apagados (sin eso el cartel sale en blanco, sin la temática). Van acá y no en un
            instructivo aparte porque es justo donde se olvidan. */}
        <div className="panel__impresion">
          <p className="panel__impresion-tit">Antes de imprimir o guardar en PDF</p>
          <ul className="panel__impresion-lista">
            <li><b>Destino:</b> Guardar como PDF <span>— «Microsoft Print to PDF» no sirve: es una
              impresora y obliga a papel A4, por eso sobra hoja</span></li>
            <li><b>Márgenes:</b> Ninguno</li>
            <li><b>Gráficos de fondo:</b> activado <span>— si no, el cartel sale sin el fondo de la temática</span></li>
            <li><b>Encabezados y pies de página:</b> desactivado <span>— quita la fecha y la dirección web</span></li>
          </ul>
          <p className="panel__impresion-pie">
            Con esto el archivo sale de <b>{tamano.ancho} × {tamano.alto} mm</b> exactos y no hay
            que recortar nada. Si tu impresora no deja elegir ese tamaño, usa papel <b>carta</b>:
            media carta es la mitad justa, así que el corte es recto por el medio.
          </p>
        </div>

        <div className="panel__acciones">
          <button type="button" className="boton" onClick={() => window.print()}>
            Imprimir {visibles.length === 1 ? 'el cartel' : `los ${visibles.length} carteles`}
          </button>
          <button type="button" className="boton boton--claro" onClick={() => window.print()}>
            Guardar en PDF
          </button>
          <a className="boton boton--claro" href={`${BASE}admin/index.php`}>Volver al admin</a>
        </div>
      </div>

      <div className="hojas">
        {visibles.map((c) => (
          <Cartel key={c.id} cartel={c} fiesta={datos.fiesta} tema={datos.tema}
            marca={datos.marca} pin={pin} tamano={tamano} estilo={estilo} />
        ))}
      </div>
    </>
  )
}

createRoot(document.getElementById('root')).render(<StrictMode><App /></StrictMode>)
