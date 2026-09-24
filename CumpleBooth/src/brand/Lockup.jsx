// Lockup de CumpleClick: el isotipo con el nombre al lado.
//
// POR QUE NO ES UN <img src="cumpleclick-lockup.svg">
//
// Ese SVG dibuja el nombre con un <text> en Baloo 2, y un SVG cargado dentro
// de un <img> se renderiza en un documento aislado: no ve las @font-face de la
// pagina, solo las fuentes instaladas en el sistema. Baloo 2 no viene con
// Windows ni con iOS. Medido en la pagina del cartel, la misma palabra ocupa
// 403 px en Baloo 2, 437 px en Segoe UI y 496 px en Helvetica: el nombre de la
// marca cambiaba de forma segun el aparato del invitado. Compuesto en HTML usa
// la Baloo 2 que el producto ya trae self-hosted, y se ve igual en todas partes.
//
// El isotipo sigue siendo el SVG oficial sin tocar. Lo unico que se compone
// aca es la palabra, con la tipografia, los colores y las proporciones que fija
// design/MANUAL-DE-MARCA.md (Baloo 2 ExtraBold, "Cumple" en Tinta Violeta
// #4C2882 y "Click" en Fucsia Click #D6307F, interletrado normal).
//
// TONOS
//
// El manual es explicito: el logo vive sobre crema o blanco, y sobre fondos
// oscuros va "el isotipo tal cual (el globo brilla solo); nunca recuadrarlo en
// una caja blanca". Por eso hay dos tonos y no uno:
//
//   marca — colores de marca. Para fondos claros (el cartel imprimible).
//   claro — la palabra en blanco. Para el cierre del album, que va sobre el
//           arte oscuro de la tematica. El globo va tal cual, sin placa detras.
//
// TAMANO
//
// Todo se mide en `em` sobre el font-size de `.cc-lockup`, que lo fija quien lo
// usa: el album piensa en cqw (el lienzo manda, no el viewport) y el cartel en
// mm (el destino es una hoja A4). Con em los dos funcionan sin que el
// componente sepa nada de sus unidades.

import React from 'react'
import './lockup.css'

export default function Lockup({ base, tono = 'marca', className = '' }) {
  const raiz = base ?? new URL('./', document.baseURI).href
  return (
    <span className={`cc-lockup cc-lockup--${tono} ${className}`.trim()}>
      <img
        className="cc-lockup__mark"
        src={raiz + 'brand/cumpleclick-mark.svg'}
        alt=""
        draggable="false"
      />
      <span className="cc-lockup__nombre">
        Cumple<span className="cc-lockup__click">Click</span>
      </span>
    </span>
  )
}
