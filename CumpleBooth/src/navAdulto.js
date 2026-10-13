/**
 * Navegación del kiosco en un cumpleaños de adulto (AT-CUMPLECLICK-026, pedido de Luis 12-oct):
 * «🏠 Inicio» en cada pantalla del recorrido y «← Volver» donde hay un paso anterior que no esté
 * ya en la propia pantalla (las vistas previas tienen «Otra foto» y «Otra frase»).
 *
 * Devuelve null si la pantalla no lleva navegación (bienvenida y despedida). `propia: true` quiere
 * decir que la pantalla dibuja su propia barra, porque mientras guarda debe bloquearla (CX-24).
 * Sin DOM, para probarla con node --test.
 */
export function navAdulto(pantalla, { hayFoto = false, grupalDesde = null } = {}) {
  const volver = (destino, aria) => ({ destino, aria })
  switch (pantalla) {
    case 'juego':
      return { volver: null }
    case 'frase-adulto':
      // Llegó desde la vista previa con «Otra frase»: se puede volver a la foto sin elegir.
      return { volver: hayFoto ? volver('preview-adulto', 'Volver a mi foto') : null }
    case 'capture':
      return { volver: volver('frase-adulto', 'Volver a elegir la frase') }
    case 'preview-adulto':
    case 'revista-preview':
      return { volver: null, propia: true }
    case 'revista-titular':
      return { volver: hayFoto ? volver('revista-preview', 'Volver a mi portada') : null }
    case 'revista-capturar':
      return { volver: volver('revista-titular', 'Volver a elegir el titular') }
    case 'asomate-elegir':
      // El pie de la pantalla ya trae «Volver» y «Atrás» entre personas.
      return { volver: null }
    case 'asomate-capturar':
      return { volver: volver('asomate-elegir', 'Volver a elegir las figuras') }
    case 'asomate-preview':
    case 'grupal-preview':
    case 'qr':
    case 'animador':
      return { volver: null }
    case 'grupal-capturar':
      return { volver: grupalDesde === 'animador' ? volver('animador', 'Volver a los juegos') : null }
    default:
      return null
  }
}
