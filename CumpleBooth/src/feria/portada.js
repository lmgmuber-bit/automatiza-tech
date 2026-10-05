// Los diseños que se dibujan encima de la escena (04-10-2026): la Portada de Revista, Año Nuevo y el muro de prensa de
// Empresa. Cada uno da las mismas piezas para `drawScene` (`antes` detrás de la persona, `despues` delante, `encuadre` si la
// persona se encuadra como en portada) y un `sinRecorte` para cuando el segmentador no está. La vista previa de la cámara y
// la foto final llaman a esta misma función, así lo que se ve al posar es lo que sale.
import { capasPortada, cargarFuentesRevista } from './revista.js'
import { capasAnioNuevo } from './anioNuevo.js'
import { capasMuroLogos, cargarLogo, tonoDeImagen } from './muroLogos.js'

export async function prepararCapas(portada, base, { espejo = false, suave = false } = {}) {
  await cargarFuentesRevista(base)
  if (portada.diseno === 'anioNuevo') return capasAnioNuevo(portada.textos, { espejo }, { suave })
  if (portada.diseno === 'muroLogos') {
    const logo = await cargarLogo(portada.logo ? base + portada.logo : '')
    return capasMuroLogos({ logo, texto: portada.textos?.marca, tono: logo ? tonoDeImagen(logo) : 'claro' }, { espejo })
  }
  return capasPortada(portada.textos, { ...portada.estilo, espejo }, { suave })
}
