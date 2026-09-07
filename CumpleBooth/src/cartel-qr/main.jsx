// Cartel QR imprimible del Álbum Recuerdo.
//
// Es la hoja que el organizador imprime y deja sobre la mesa: un QR grande, el
// mensaje para los invitados y la URL en texto para quien no pueda escanear.
// Hereda la paleta y el arte de la temática de la fiesta.
//
// Se pide con el token de APORTE (el mismo que va dentro del QR), no con el de
// lectura de la revista: son llaves distintas a propósito.

import React, { useEffect, useState } from 'react'
import { createRoot } from 'react-dom/client'
import QRCode from 'qrcode'
import { applyThemeColors } from '../themeVars.js'
import './cartel.css'
import Lockup from '../brand/Lockup.jsx'

const BASE = new URL('./', document.baseURI).href

function getToken() {
  const value = new URLSearchParams(window.location.search).get('t') || ''
  return /^[a-f0-9]{32}$/.test(value) ? value : ''
}

function formatDate(raw) {
  if (!raw) return ''
  const parts = String(raw).split('-')
  if (parts.length !== 3) return raw
  const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
  const mes = meses[Number(parts[1]) - 1]
  return mes ? `${Number(parts[2])} de ${mes}` : raw
}

// Iconos dibujados a mano y en `currentColor`: el cartel se imprime, muchas
// veces en blanco y negro, y un icono de una fuente externa o un PNG de color
// plano ahi se pierde. Con trazo se sigue reconociendo.
const ICONOS = {
  web: 'M12 3a9 9 0 100 18 9 9 0 000-18zM3.6 9h16.8M3.6 15h16.8M12 3c2.4 2.4 3.6 5.4 3.6 9s-1.2 6.6-3.6 9c-2.4-2.4-3.6-5.4-3.6-9S9.6 5.4 12 3z',
  instagram: 'M7.5 3.5h9a4 4 0 014 4v9a4 4 0 01-4 4h-9a4 4 0 01-4-4v-9a4 4 0 014-4zm4.5 5a3.5 3.5 0 100 7 3.5 3.5 0 000-7zm5.1-1.6h.01',
  whatsapp: 'M3.8 20.2l1.2-4a8 8 0 113 3l-4.2 1zM9 9.4c.3-.7.5-.7.8-.7h.6c.2 0 .5 0 .7.6l.8 1.9c.1.2.1.4 0 .6l-.4.6c-.1.2-.2.4 0 .7a6 6 0 002.6 2.3c.3.1.5.1.7-.1l.6-.7c.2-.2.4-.2.6-.1l1.8.9c.3.1.4.3.4.5 0 .6-.4 1.4-.7 1.6-.5.4-1.1.6-1.8.5a9 9 0 01-5.4-3.4c-.8-1-1.4-2.2-1.4-3.4 0-.7.3-1.4.8-1.8z',
}

function Dato({ tipo, valor, url }) {
  const cuerpo = (
    <>
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d={ICONOS[tipo]} fill="none" stroke="currentColor" strokeWidth="1.7"
          strokeLinecap="round" strokeLinejoin="round" />
      </svg>
      <span>{valor}</span>
    </>
  )
  if (!url) return <li className="sign__dato">{cuerpo}</li>
  return (
    <li className="sign__dato">
      <a href={url} target="_blank" rel="noopener noreferrer">{cuerpo}</a>
    </li>
  )
}

/**
 * Pie del cartel: la marca y como contactarla.
 *
 * Este cartel queda sobre la mesa toda la fiesta y lo mira mucha mas gente que
 * la revista, asi que es el mejor lugar de todo el producto para que la marca
 * se lea. Los datos salen de data/marca.json (los mismos que cierran la
 * revista), no del bundle: se editan en admin/marca.php y cambian sin build.
 *
 * Si no hay marca cargada, cae al cierre minimo de siempre en vez de imprimir
 * un pie a medias.
 */
function Pie({ marca }) {
  // Tono de marca: el cartel se imprime sobre papel blanco, que es donde el
  // manual quiere el logo a color.
  const logo = <Lockup base={BASE} tono="marca" className="sign__logo" />

  if (!marca) {
    return <footer className="sign__foot">{logo}</footer>
  }

  const datos = [
    { tipo: 'web', valor: marca.web, url: marca.web_url },
    { tipo: 'instagram', valor: marca.instagram, url: marca.instagram_url },
    { tipo: 'whatsapp', valor: marca.whatsapp, url: marca.whatsapp_url },
  ].filter((dato) => dato.valor)

  return (
    <footer className="sign__foot">
      <span className="sign__foot-rule" aria-hidden="true" />
      {marca.invitacion && <p className="sign__foot-invita">{marca.invitacion}</p>}
      {logo}
      {marca.lema && <p className="sign__foot-lema">{marca.lema}</p>}
      {datos.length > 0 && (
        <ul className="sign__datos">
          {datos.map((dato) => <Dato key={dato.valor} {...dato} />)}
        </ul>
      )}
    </footer>
  )
}

function Sign({ data }) {
  const [qr, setQr] = useState(null)
  const [qrError, setQrError] = useState(false)

  useEffect(() => {
    applyThemeColors(data.theme?.colors)
    document.title = data.eventName
      ? `Cartel QR · ${data.eventName}`
      : 'Cartel QR · Álbum Recuerdo'
  }, [data])

  useEffect(() => {
    // Negro sobre blanco y margen amplio: un QR tematizado se ve lindo en
    // pantalla y falla al escanear impreso. Acá gana que funcione.
    QRCode.toDataURL(data.uploadUrl, {
      width: 1200,
      margin: 2,
      errorCorrectionLevel: 'M',
      color: { dark: '#000000', light: '#FFFFFF' },
    })
      .then(setQr)
      .catch(() => setQrError(true))
  }, [data.uploadUrl])

  const banner = data.theme?.assets?.banner

  return (
    <article className="sign">
      <header className="sign__head" style={banner ? { backgroundImage: `url("${BASE + banner}")` } : undefined}>
        <div className="sign__veil" />
        <div className="sign__head-body">
          <p className="sign__eyebrow">Álbum Recuerdo</p>
          <h1 className="sign__title">
            {data.eventName ? `Fiesta de ${data.eventName}` : 'Comparte tus recuerdos'}
          </h1>
          {data.date && <p className="sign__date">{formatDate(data.date)}</p>}
        </div>
      </header>

      <p className="sign__message">{data.message}</p>

      <div className="sign__qr">
        {qr && <img src={qr} alt="Código QR para subir tus fotos" />}
        {qrError && <p className="sign__qr-error">No se pudo generar el código QR. Recarga la página.</p>}
        {!qr && !qrError && <p className="sign__qr-error">Generando el código…</p>}
      </div>

      <p className="sign__how">Escanea el código con la cámara de tu teléfono</p>

      <div className="sign__url">
        <span className="sign__url-label">o entra a</span>
        <span className="sign__url-value">{data.uploadUrl}</span>
      </div>

      {!data.open && (
        <p className="sign__closed no-print">
          Ojo: la recepción de recuerdos está apagada o cerrada ahora mismo. Actívala
          en el admin antes de imprimir este cartel.
        </p>
      )}

      <Pie marca={data.marca} />
    </article>
  )
}

function App() {
  const token = getToken()
  const [state, setState] = useState(token ? { status: 'loading' } : { status: 'error' })

  useEffect(() => {
    if (!token) return
    fetch(`${BASE}album-api.php?cartel=1&t=${encodeURIComponent(token)}`, { credentials: 'same-origin' })
      .then((response) => response.json())
      .then((body) => {
        if (body && body.ok) setState({ status: 'ready', data: body })
        else setState({ status: 'error' })
      })
      .catch(() => setState({ status: 'error' }))
  }, [token])

  if (state.status === 'loading') {
    return <p className="sign__standalone">Preparando el cartel…</p>
  }
  if (state.status === 'error') {
    return (
      <p className="sign__standalone">
        Este enlace no es válido o ya fue revocado. Genera uno nuevo desde el admin.
      </p>
    )
  }

  return (
    <>
      <div className="sign__toolbar no-print">
        <button type="button" className="sign__print" onClick={() => window.print()}>
          Imprimir el cartel
        </button>
        <p className="sign__hint">Tamaño carta o A4, vertical. Sale en blanco y negro sin problema.</p>
      </div>
      <Sign data={state.data} />
    </>
  )
}

createRoot(document.getElementById('cartel')).render(<App />)
