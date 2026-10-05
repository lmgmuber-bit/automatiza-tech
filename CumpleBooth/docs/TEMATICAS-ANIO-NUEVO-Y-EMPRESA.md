# Año Nuevo y Muro de prensa (temáticas de adultos)

Pedido de Luis el 04-10-2026 ("si, haz Año Nuevo y Empresa"), las dos que se le propusieron para la temporada de fin de año.
Rama `claude/adultos-anio-nuevo-empresa`, que parte de `claude/revista-click` (usa su mecanismo de capas). **Sin desplegar.**

## Qué ve el invitado

**Año Nuevo** (`adulto-anio-nuevo`): fuegos artificiales sobre una bahía con cerros iluminados, como Valparaíso. Arriba, el
año en dorado, detrás de la persona; abajo y delante, "¡Feliz Año Nuevo!" y su nombre. El año sale de la fecha del evento: uno
de **noviembre o diciembre celebra el año que viene** (las fiestas de fin de año de las empresas parten en noviembre) y uno de
enero, el que empieza. La persona se encuadra con la coronilla bajo los números (al 22,5 % del alto): a diferencia del título
de la revista, un año tapado por una cabeza ya no se lee.

**Muro de prensa** (`adulto-empresa`, la temática Empresa): el lienzo de prensa de un lanzamiento ("step and repeat"), con el
**logo del cliente repetido** detrás de la persona. Sin logo, el muro repite en letras el nombre de quien organiza. Si el logo
es claro (por ejemplo blanco sobre transparente), el muro pasa a oscuro para que se vea. Aquí no hay encuadre de portada: la
persona queda como en las demás temáticas y el logo se ve alrededor de la cabeza. En la tablet se llama "Muro de prensa",
que es lo que el invitado entiende; en el admin aparece igual, con la nota "para adultos".

## El logo del cliente

- Se sube en **Admin → Ferias → la ficha del evento**, campo "Logo de la empresa": PNG con fondo transparente, JPG o WebP,
  entre 16 y 4096 px por lado y hasta 4 MB, o menos si el servidor acepta menos (`upload_max_filesize`, `post_max_size`):
  el formulario muestra el tope real (`cb_feria_logo_max_texto()`; en el PHP local es 2 MB). Ahí mismo se ve el actual y se
  puede quitar.
- El servidor lo **vuelve a codificar con GD** a PNG (con su transparencia, a lo más 1000 px por lado): lo que se guarda y se
  sirve nunca es el archivo que mandó el navegador. Un archivo que no es imagen (por ejemplo código con nombre `.png`) se
  rechaza y el logo anterior queda igual.
- Vive **fuera del webroot**, en el directorio de estado: `<state_dir>/ferias-logos/<id del evento>.png`. Sin columna nueva
  en la base y sin migración: el archivo es la marca de que el evento tiene logo.
- Lo entrega `feria-logo.php?f=<slug>` como `image/png`, con `nosniff` y `no-transform` (el CDN de Hostinger re-codifica los
  PNG para Android). La dirección lleva la fecha del archivo, así un logo nuevo no queda en caché.
- "Duplicar para otra feria" copia el logo: la próxima del mismo cliente queda lista.

## Cómo funciona

| Pieza | Archivo |
|---|---|
| Registro de las temáticas (`rotulo.diseno`: `anioNuevo` o `muroLogos`) | `public/data/themes.json` |
| Publicación del rótulo y del logo; guardar, validar y borrar el logo; copia al duplicar | `public/lib.ferias.php` (`cb_feria_rotulo()`, `cb_feria_logo_*`) |
| Entrega del logo | `public/feria-logo.php` |
| Campo del logo en la ficha del evento | `public/admin/ferias.php` |
| Un solo despachador para los tres diseños (revista, Año Nuevo, muro): misma función en la cámara y en la foto | `src/feria/portada.js` |
| Año, saludo y encuadre | `src/feria/anioNuevo.js` |
| Muro: grilla, tono del logo, letras de respaldo | `src/feria/muroLogos.js` |

Sin segmentador, Año Nuevo pone el año y el saludo sobre la foto entera, y el muro pone el logo en una placa arriba a la
izquierda.

## Imágenes y costo

| Archivo | De dónde sale |
|---|---|
| `adulto-anio-nuevo/fondo-escena.jpg` | Higgsfield por API REST (`marketing-studio/image/flare`, 9:16), sin números ni letras. Se pidieron dos candidatas y se eligió la A; la B quedó de repuesto |
| `adulto-empresa/fondo-escena.jpg` | dibujado por `design/generadores/anio-nuevo-empresa/instalar.py`, sin IA: blanco cálido con degradé y grano de tela |
| `fondo-sala.jpg` | copia de la escena |
| `fondo-evento.jpg` | `design/generadores/fondo-evento/derivar.py` |
| `fondo-banner.jpg` (tarjeta del selector) | `design/generadores/revista/render-banner.mjs <tema>` con el código del kiosco: Año Nuevo con el año del próximo 31 de diciembre (**hay que volver a correrlo cada temporada**) y el muro con "TU LOGO AQUÍ" |

Costo: **USD 0,0324 de lista** (dos imágenes a USD 0,0162), dentro del estimado aprobado (unos USD 0,05 por temática). El muro
no costó nada. Prompts y `request_id` en `C:\Users\luis_\Documents\CumpleClick\tematicas-adultos-2026-10\anio-nuevo\`.

## Pruebas

- `tests/frontend/anio-nuevo-empresa.test.mjs` (8): año que se celebra, textos, orden de las capas y coronilla bajo el año, tono
  del logo (blanco, oscuro, amarillo, JPG con fondo blanco), grilla del muro, logo o letras en cada celda, archivos.
- `tests/backend/ferias-http.php`: rótulos publicados; subida real del logo por la ficha (multipart); PNG servido con su
  transparencia y achicado a 1000 px; rechazo de un archivo que no es imagen; WebP guardado como PNG; quitar y duplicar. Se
  verificó en rojo: sin `imagesavealpha` la prueba de transparencia falla.
- `tests/frontend/feria-integracion.test.mjs`: con cámara simulada, las dos sin menú, vista previa 9:16 y foto con recorte; el
  año presente sobre la escena (64 contra 2 en la esquina de control) y el logo del evento en el muro.

## No probado

- Tablet física y una persona adulta real (la cámara simulada usa el cóndor del juego del volantín).
- Un logo real de un cliente: se probó con uno de mentira. Logos muy anchos o muy altos caben sin deformarse, pero no se han visto.
- La foto impresa en la Selphy.
