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

      <footer className="sign__foot">
        <img src={`${BASE}brand/cumpleclick-mark.svg`} alt="" width="26" height="26" />
        <span>CumpleClick</span>
      </footer>
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
