# QA local — Sitio público CumpleClick

Fecha: 2026-07-28  
URL revisada: `http://localhost/automatiza-tech/CumpleBooth/sitio/`

## Corrección aplicada

El bloque de vídeo deja de presentar al personaje como el producto. Ahora se comunica como demostración de una temática:

- Título: **Una temática en acción**.
- Explicación: recorre ruleta, personaje, juegos y foto final; cada temática tiene su propio mundo.
- Etiqueta que cubre el texto incrustado del póster: **Ejemplo: Reino de Hielo**.
- La llamada de accesibilidad y el modal mantienen la referencia a la temática, no al personaje.

## Resultado de auditoría

- Página, estilos, fuentes, imágenes, vídeos y librerías locales cargan por HTTP 200/206.
- El video de ejemplo abre en modal, muestra controles nativos y se puede cerrar con `Escape`.
- Sin errores JavaScript. Solo aparece una advertencia deprecada de Three.js: `THREE.Clock`; no impide la experiencia, pero conviene actualizarla antes de una fase de optimización.
- Verificado visualmente en escritorio, tablet 10 pulgadas vertical y móvil 390×844.
- `node --check sitio/js/main.js`: correcto.

## Bloqueador antes de producción

`index.html` contiene seis enlaces y el footer con el placeholder `[WHATSAPP_NUMBER]`. Debe sustituirse por el número oficial antes de subir: si no, los CTA de agenda no funcionarán.

## Decisión de ruta pendiente

La documentación del sitio especifica que la URL pública final de esta landing la decide Luis. No subir esta carpeta dentro de `dist/` ni reemplazar el kiosco actual sin decidir si vivirá en `/cumpleclick/` o en otra ruta.

## Evidencia

- `screenshots/01-inicio-desktop.png`
- `screenshots/02-ejemplo-tematica.png`
- `screenshots/03a-poster-completo.png`
- `screenshots/04-modal-abierto.png`
- `screenshots/05-tablet-10in-portrait.png`
- `screenshots/07-mobile-inicio-limpio.png`

## Ajuste 2026-07-29 — Carrusel y nombres de temáticas

- La vitrina móvil ahora recorre las tarjetas automáticamente en vaivén (derecha/izquierda).
- Un gesto táctil, arrastre o rueda pausa el vaivén durante 4,5 segundos, sin impedir el scroll manual; luego retoma si la vitrina sigue visible.
- Las tarjetas ahora usan los nombres pedidos: Bluey, Cars, Frozen, Lilo & Stitch, Capitán América y KPop Demon Hunters.
- KPop usa el `fondo-banner.jpg` aprobado más reciente y Héroes usa el retrato local de Capitán América.
- Verificación móvil: el carrusel pasó de `scrollLeft=196` a `430` de manera automática. Se validó visualmente el extremo con Capitán América y KPop.
- Evidencia adicional: `../sitio-publico-2026-07-29-mundos.png` y `../sitio-publico-2026-07-29-mundos-final.png`.

## Ajuste 2026-07-29 — Eventos y empresas

- La landing ahora comunica CumpleClick para cumpleaños, Navidad, Día del Niño, colegios/jardines, empresas y eventos especiales.
- Se agregó formulario de cotización con nombre, organización, correo, teléfono, tipo de evento, fecha aproximada y detalle.
- El sitio no guarda esos datos: el envío compone un mensaje y abre WhatsApp. Con el placeholder actual muestra un aviso transparente de configuración pendiente.
- Validado en móvil con todos los campos requeridos y la respuesta de configuración pendiente visible.
- Evidencia: `../sitio-publico-2026-07-29-contacto-movil.png` y `../sitio-publico-2026-07-29-contacto-config-pendiente.png`.
