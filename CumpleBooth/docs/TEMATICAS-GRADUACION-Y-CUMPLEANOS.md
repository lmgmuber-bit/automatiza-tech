# Graduación y Cumpleaños de gala

Luis el 05-10-2026, ante la recomendación de hacer estas dos para la temporada ("haz lo que consideres necesario"). Rama
`claude/graduacion-cumpleanos`, que parte de `claude/adultos-anio-nuevo-empresa` (PR lmgmuber-bit/automatiza-tech#71): usa
su número dorado, el logo del evento y el mecanismo de capas. **Sin desplegar.**

## Qué ve el invitado

**Graduación** (`graduacion`, para Niños y Adultos: colegios, jardines y universidades): un escenario con telón azul y
birretes al aire. Arriba y detrás de la persona, "GENERACIÓN" y el año en dorado; abajo y delante, "¡Felicitaciones!" con su
nombre y, si el evento tiene logo, la placa del colegio. El año es el de la fecha del evento: una licenciatura de diciembre es
de ese año, no del siguiente (al revés que Año Nuevo).

**Cumpleaños de gala** (`adulto-cumpleanos`, adultos): un arco de globos rosa dorado y luces. Arriba y detrás, la edad que se
celebra en grande; abajo, "¡Feliz cumpleaños, Ana!". El saludo es para el festejado, no para el invitado de la foto: él está
de visita. Sin edad cargada, la foto lleva solo "¡Feliz cumpleaños!" y la persona queda con el encuadre de siempre. Sobre los
globos claros el dorado se perdía: el número va con sombra oscura y borde marcado (`fondoClaro` en `dibujarAnio`).

## Los datos del evento

En **Admin → Ferias → ficha del evento** hay dos campos nuevos, "Nombre del festejado" y "Edad que se celebra" (de 1 a 120;
una edad imposible se rechaza y no cambia nada). Se guardan en `<state_dir>/ferias-extras/<id>.json`, como el logo: sin columna
nueva ni migración. Vaciar los dos borra el archivo. El campo del logo ahora dice que sirve también para Graduación.

## Cómo funciona

| Pieza | Archivo |
|---|---|
| Registro (`rotulo.diseno`: `graduacion` o `cumpleanos`) | `public/data/themes.json` |
| Edad y festejado (validar, leer, guardar) y su publicación en el rótulo | `public/lib.ferias.php` (`cb_feria_extras_*`, `cb_feria_rotulo()`) |
| Campos en la ficha | `public/admin/ferias.php` |
| Textos, capas y encuadre de las dos | `src/feria/celebraciones.js` |
| El número dorado (zona y contraste configurables) y la placa del logo | `src/feria/anioNuevo.js` (`dibujarAnio`), `src/feria/muroLogos.js` (`dibujarPlacaLogo`) |
| Despachador | `src/feria/portada.js` |

## Imágenes y costo

Escenas de Higgsfield por API REST (`marketing-studio/image/flare`, 9:16), sin texto ni números; instaladas con
`design/generadores/graduacion-cumpleanos/instalar.py`. Tarjetas del selector con `render-banner.mjs graduacion` y
`render-banner.mjs adulto-cumpleanos` (un 40 de muestra). Gasto: **USD 0,0324 de lista** (una imagen por temática). Además,
un primer intento corrió por error una copia del script de Año Nuevo y se detuvo a los segundos: **puede** haber enviado una
imagen repetida (USD 0,0162); la API no permite listar trabajos para confirmarlo. Prompts y `request_id` en
`C:\Users\luis_\Documents\CumpleClick\tematicas-adultos-2026-10\grad-cumple\`.

## Pruebas

- `tests/frontend/graduacion-cumpleanos.test.mjs` (5): año de la generación, edad válida, saludo al festejado, capas y coronilla
  bajo el número, placa del logo centrada abajo, sin edad no hay número ni encuadre, archivos.
- `tests/backend/ferias-http.php`: Graduación en Niños y Adultos y Cumpleaños solo en Adultos; rótulos; edad, festejado y logo
  guardados desde la ficha por HTTP; edad imposible rechazada; vaciar borra los datos.
- `tests/frontend/feria-integracion.test.mjs`: las dos con cámara simulada; el año y la edad presentes en la foto (81 y 60
  contra controles de 0,6 y 4,6 en la prueba local).

## No probado

Tablet física, una persona real, el logo real de un colegio y la foto impresa.
