# Portada de Revista CLICK (temática de adultos)

Pedido de Luis el 04-10-2026 ("parte con la revista CLICK y los tres fondos, la invitación para después"), a partir de lo que
ofrece la competencia en Instagram: un "Magazine Booth" de cartón (Tu Fiesta 360) y un tótem con IA (Enjoy Producciones). Rama
`claude/revista-click`, que parte de `claude/tematicas-brujitas-navidad`. **LOCAL, sin desplegar.**

## Qué ve el invitado

En la feria, en modo Adultos, elige **Portada de Revista**, escribe su nombre y el menú le ofrece tres portadas:

| Portada | Escena | Color de la etiqueta EXCLUSIVA |
|---|---|---|
| 📸 Alfombra roja | alfombra con fotógrafos y flashes | rojo `#C1121F` |
| 💗 Estudio de color | fondo de estudio fucsia | negro `#16121A` |
| 🖤 Blanco y negro | estudio gris, y la foto entera pasa a blanco y negro | negro |

La cámara muestra la portada **entera** (9:16) mientras posa. La foto final es una portada de 1080x1920: el título **CLICK**
detrás de la cabeza, como en una revista de verdad, y delante los titulares: EXCLUSIVA, "Así se vivió <nombre del evento>",
dos llamados, "LA ESTRELLA DE HOY" y su **nombre** en grande, y un código de barras. Debajo va la franja de recuerdo de la feria
con su número F-###, igual que en las demás temáticas.

## Reglas que se respetaron

- **El texto lo dibuja el código, nunca la IA.** Las tres escenas se generaron sin una letra; título y titulares se montan encima.
- **Ninguna marca ajena.** El título es CLICK, no el de una revista que existe.
- **Nada de texto delante de una cara.** El título y la línea de edición y fecha van detrás de la persona; los titulares de
  adelante están en los costados y abajo.
- **Sirve de día y de noche:** ningún texto dice "la noche" (una feria es de día).

## Cómo funciona

| Pieza | Archivo |
|---|---|
| Registro de la temática y sus portadas (`revista.titulo`, `revista.variantes[]`) | `public/data/themes.json`, clave `adulto-revista` |
| Validación y publicación de las portadas | `public/lib.ferias.php`, `cb_feria_revista()` |
| Textos fijos editables (Admin → Ajustes) | `public/lib.ajustes.php` (`cb_revista_textos_campos()`, `cb_revista_textos()`), `public/admin/ajustes.php` |
| Menú "ELIGE TU PORTADA" y la portada elegida (escena, filtro, colores) | `src/feria/FeriaBooth.jsx` |
| Textos y dibujo de la portada; carga de las fuentes | `src/feria/revista.js` |
| Capas (`antes`/`despues`) y encuadre de portada | `src/feria/segmentation.js`: `drawScene`, `cajaDeMascara`, `encuadrePortada` |
| Vista previa 9:16 en la cámara | `src/feria/Camera.jsx`, `src/feria/feria.css` (`.feria-camera-portada`) |
| Foto final, y portada sin recorte si el segmentador falla | `src/feria/photo.js` |
| Fuentes Bodoni Moda (900 y 700 cursiva) y Oswald (500 y 700), licencia OFL 1.1 | `public/fonts/revista/` (de `@fontsource` 5.3.0, con su licencia) |

**El servidor publica solo lo que existe.** `cb_feria_revista()` deja pasar una portada si su escena es un nombre de archivo
simple que está en disco; el título son letras y números (hasta 12); `tinta` y `acento` solo como `#rrggbb`; el filtro solo
`bn`; como máximo cuatro portadas. Con una sola portada no hay menú. Una temática sin bloque `revista` no cambia en nada.

**Los textos fijos se editan en Admin → Ajustes**, sección "Portada de Revista" (Luis, 04-10: "deja los textos así, pero
que yo lo pueda editar si quisiera"). Son ocho: etiqueta (EXCLUSIVA), bajada ("Así se vivió {evento}"), los dos llamados,
lo que va sobre el nombre (LA ESTRELLA DE HOY), lo que va si no hay nombre (ERES TÚ), la edición y el número. Un campo vacío
deja el de siempre; cada uno tiene un máximo de letras para que quepa en la portada, y lo que no cabe se rechaza al guardar.
Se guardan en `data/ajustes.json` (`revista_textos`) y el servidor los manda con la temática (`theme.revista.textos`). Un
guardado que no los trae no los borra. En la portada todo va en mayúsculas, menos el número. Los textos de siempre están en
PHP (`cb_revista_textos_campos()`) y en el kiosco (`TEXTOS_DE_SIEMPRE` de `revista.js`); una prueba exige que sean iguales.
La tarjeta del selector (`fondo-banner.jpg`) es una imagen hecha con los de siempre: no cambia si se editan.

**El encuadre de portada es solo de esta temática.** Las otras temáticas de fondo conservan la escala de la cámara
(`subjectPlacement`). En la revista, la caja de la persona sale de su máscara y se encuadra para que la coronilla quede al
9,5 % del alto, montada sobre el pie del título. Nunca se achica bajo la escala de siempre ni se agranda más de 1,6 veces
(se pondría borrosa). Si la cabeza ya toca el borde de arriba de la cámara, el cuadro se estira hasta el borde del lienzo para
que el corte no se vea. En la vista previa el encuadre se suaviza cuadro a cuadro para que no tiemble.

**La cámara frontal se ve como espejo** (CSS). El texto de la vista previa se dibuja ya volteado, así se lee derecho; la foto
final no se voltea.

## Imágenes y costo

| Archivo (en `public/themes/adulto-revista/`) | De dónde sale |
|---|---|
| `revista-alfombra.jpg`, `revista-estudio.jpg`, `revista-bn.jpg` | Higgsfield por API REST, `marketing-studio/image/flare` 9:16, recortadas a 1080x1920 con `design/generadores/revista/instalar.py` |
| `fondo-sala.jpg` | copia de la alfombra (marco de respaldo) |
| `fondo-evento.jpg` | `design/generadores/fondo-evento/derivar.py` |
| `fondo-banner.jpg` (tarjeta del selector) | portada de muestra sin persona, dibujada con el mismo `revista.js` por `design/generadores/revista/render-banner.mjs` |

Costo: **USD 0,0486 de lista** (tres imágenes a USD 0,0162), aprobado por Luis el 04-10. Prompts y `request_id` en
`C:\Users\luis_\Documents\CumpleClick\tematicas-adultos-2026-10\revista\` (`generar.py` y `registro-generacion.json`), fuera
del repositorio. Sin música, como las otras temáticas de adultos.

## Cómo agregar otra portada

1. Generar la escena 9:16 **sin texto**, con el centro despejado de abajo hacia arriba (ahí va la persona).
2. Dejarla en 1080x1920 como `public/themes/adulto-revista/revista-<clave>.jpg` (agregarla a `ESCENAS` de `instalar.py`).
3. Sumar la variante en `themes.json`: `clave`, `etiqueta`, `detalle`, `emoji`, `escena`, `tinta`, `acento` y, si es en
   blanco y negro, `filtro: "bn"`. Máximo cuatro.
4. Correr `tests/frontend/revista.test.mjs` y `tests/backend/ferias-http.php`, y mirar una foto final de la portada nueva.

Los textos fijos se cambian en Admin → Ajustes, sin tocar código (ver arriba).

## Pruebas

- `tests/frontend/revista.test.mjs` (14): textos, fecha, cortes de línea, tamaño de letra, código de barras, orden de las
  capas, texto volteado en la vista previa, caja de la máscara, encuadre de portada, archivos de la temática, textos de
  Ajustes, textos de siempre iguales en PHP y en el kiosco, y un llamado largo que achica la letra sin perder palabras.
- `tests/backend/ferias-http.php`: lo que publica `api.php` y el selector, la validación de `cb_feria_revista()` y la
  pantalla de Ajustes por HTTP (muestra los campos, guarda, rechaza lo que no cabe, no borra lo que no viene).
- `tests/frontend/feria-integracion.test.mjs`: con cámara simulada, por cada portada: menú sin Asómate, vista previa 9:16,
  foto final 1080x1920 con recorte, el título CLICK en la foto (comparada contra su escena) y gris solo en la de blanco y negro.

## No probado

- Tablet física y una **persona adulta real**: la cámara simulada usa el cóndor del juego del volantín. El encuadre de portada
  se midió con él, no con una cara humana a distintas distancias.
- La foto impresa en la Selphy: si los titulares chicos se leen en 10x15.
- La cámara trasera (sin espejo) con la portada.
