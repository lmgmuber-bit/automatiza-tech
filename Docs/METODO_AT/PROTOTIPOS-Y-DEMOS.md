# Prototipos de Claude Design y demos para prospectos

Fecha: 2026-09-29. Primer caso completo: el demo de una botillería con delivery express (28-sep-2026),
con un nombre provisional mientras el cliente decide el suyo.
Lo propio de cada cliente (precios, nombres, guiones de PROD, fuentes del prototipo) vive en la bóveda privada,
en `10-Projects/<Cliente>/README.md`; este documento es solo el método. El repo es público: aquí no van nombres de
clientes, precios ni teléfonos.

## Recorrido

1. La reunión entra por el flujo de Meet y crea la propuesta v3 (`PROPUESTAS-FLUJO-V3.md`).
2. Con el brief se arma un prompt para **Claude Design** (identidad + pantallas); Luis lo pega y exporta el ZIP.
3. Si el cliente aún no tiene nombre, el prototipo usa uno provisional y todo se trata como genérico hasta que decida.
4. Un agente revisa el export con las skills de diseño (ui-ux-pro-max, design-taste-frontend, frontend-design),
   mide con un script en el navegador y **corrige directamente** en una copia `v2` (nunca sobre el export original).
5. Se publica en `https://automatizatech.cl/demos/<rubro>/` con autorización de Luis.
6. El enlace del demo va **primero en los próximos pasos** de la propuesta (el renderer lo vuelve enlace).
7. Opcional: video vertical de recorrido para mandar por WhatsApp.

## Qué trae un export de Claude Design

- `*.dc.html` + `support.js`: un runtime propio (`<x-dc>`, `sc-if`, `sc-for`, plantillas `{{ }}`) que carga React y
  ReactDOM desde `unpkg.com` con SRI, y Babel standalone (3 MB) solo si hay módulos JSX.
- Errores de consola por atributos `{{ }}` y peticiones 404 a `{{ product.img }}` antes de rellenar la plantilla:
  son del runtime, no del contenido.
- El prototipo móvil se dibuja **dentro de un teléfono de ~844 px** sobre un escenario grande. En un celular real
  eso es inusable: ver «Arreglo móvil».

## Cómo publicarlo en Hostinger

Carpeta `public_html/demos/<rubro>/`: el prototipo como `index.html`, las láminas de identidad como
`identidad-1..n.html`, `support.js`, `vendor/`, `uploads/` (fotos en WebP) y este `.htaccess`:

```apache
Options -Indexes
AddType image/webp .webp
ErrorDocument 404 "No encontrado"
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . - [R=404,L]
<IfModule mod_headers.c>
  Header set X-Robots-Tag "noindex, nofollow"
  <FilesMatch "\.(jpg|jpeg|png|webp)$">
    Header set Cache-Control "public, max-age=31536000, no-transform"
  </FilesMatch>
  <FilesMatch "\.(js|css)$">
    Header set Cache-Control "public, max-age=604800"
  </FilesMatch>
</IfModule>
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript application/x-javascript
</IfModule>
```

- Sin el `RewriteRule` de 404, cada archivo inexistente cae en la página 404 de WordPress (188 KB). El
  `ErrorDocument` solo no basta: la regla de WordPress de la raíz gana.
- `no-transform` evita que el CDN reencode las fotos.
- Permisos: `find <carpeta> -type f -exec chmod 644 {} +`. 🔴 `chmod 644 carpeta/*` le quita la ejecución a
  `uploads/` y todas las fotos dan 404.
- Procedimiento de siempre (ver `Docs/ORCHESTRATION/CONEXIONES-Y-CREDENCIALES.md` §3.3): leer lo que hay,
  respaldar la carpeta en `~/respaldos/demos-<rubro>-antes-<fecha>.tar.gz`, subir, cotejar md5 y probar por HTTP
  desde afuera.

## React local (obligatorio)

Si un navegador móvil o la red no entrega los archivos de `unpkg.com`, la página se ve pero no responde a nada.
Alojar `vendor/react.production.min.js` y `vendor/react-dom.production.min.js` (mismos sha384 que el SRI del export)
y declararlos **antes** de `support.js`:

```html
<script>window.__resources={"https://unpkg.com/react@18.3.1/umd/react.production.min.js":"vendor/react.production.min.js","https://unpkg.com/react-dom@18.3.1/umd/react-dom.production.min.js":"vendor/react-dom.production.min.js"};</script>
```

Aplicarlo en el prototipo y en cada lámina de identidad. Verificar que la única petición externa sea Google Fonts.

## Arreglo móvil (obligatorio antes de mandarlo a un cliente)

Síntoma real: «las pestañas no navegan» en el celular. Causa: la puerta +18 (o cualquier modal) bloquea las
pantallas siguientes y su botón queda fuera de la vista dentro del teléfono dibujado.

Solución, dentro del mismo HTML: un `<style id="at-movil">` con `@media (max-width: 760px)` y un
`MutationObserver` que etiqueta los contenedores del export (`.at-barra`, `.at-tel`, `.at-puerta`, `.at-bo`,
`.at-esc`), porque el export no trae clases estables.

- Bajo 760 px: el teléfono ocupa la pantalla (`calc(100dvh - 92px)`), se ocultan el selector Móvil/Escritorio y la
  fila de pestañas, y un pie fijo muestra «‹ Atrás · PASO n DE N · nombre · Siguiente ›» que dispara el clic de la
  pestaña oculta correspondiente.
- El back office y la vista de escritorio entran al recorrido reducidos (`zoom: .58`) en un contenedor con paneo
  horizontal y un aviso de que se desliza.
- En PC no cambia nada.

Trampas medidas:

- El observador que actualiza el pie se retroalimenta y **tumba la pestaña** («Target crashed» en Playwright) si
  escribe en el DOM sin comparar. Escribir solo si el valor cambió y dentro de `requestAnimationFrame`.
- La pestaña activa se marca cambiando un atributo `style`: el observador necesita `attributes: true`.
- Chrome normaliza `style.background` a `rgb(242, 169, 59)`: comparar colores normalizados, no el hex.
- Con `visibility: hidden`, `innerText` sale vacío: buscar botones por `textContent`.

Prueba: recorrer todos los pasos con Playwright emulando Pixel 7 e iPhone 13 (`tap`, no `click`), comprobar que no
haya scroll horizontal y que el botón de la puerta +18 quede dentro de la pantalla.

## Rubros con alcohol

Toda publicidad digital de bebidas alcohólicas lleva la advertencia sanitaria de la Ley 21.363 (art. 40 ter de la
Ley 19.925, vigente desde el 7-jul-2026): recuadro negro, «ADVERTENCIA:» en mayúsculas blancas, una de las frases
oficiales y el logo del Minsal. Frases: «Todo consumo de alcohol es dañino durante el embarazo», «Todo consumo de
alcohol limita la capacidad de conducir», «El consumo de alcohol en menores de 18 años se encuentra prohibido»,
«El consumo nocivo de alcohol daña tu salud». Además, puerta de edad al entrar. Si el logo oficial no está a mano,
dejar un marcador visible y anotarlo como pendiente.

## Video de recorrido para WhatsApp

No es un Reel: las reglas de los Reels (marca de agua del 4 %, cierre estándar con «Agenda tu diagnóstico gratis»)
no aplican tal cual. Lo que pidió Luis el 29-sep para videos a un prospecto con propuesta:

- Formato 1080×1920, ~90 s, menos de 16 MB, −16 LUFS.
- Logo AT visible arriba a la izquierda (≈170 px al 85 %), rótulo «n / N» por escena.
- Cuadros de escritorio grandes: recortar la barra y los márgenes del escenario y ocupar casi todo el ancho.
- Recorrer **una** propuesta y decir en voz que las otras comparten el flujo.
- **Cierre propio** con el estilo del cierre de los Reels (fondo azul marino, logo AT grande, WhatsApp y
  automatizatech.cl) pero con la frase **«Agenda tu llamada de seguimiento para aclarar dudas»**: el
  `outro-at-10s.mp4` trae «diagnóstico» grabado en la imagen y no sirve para un cliente que ya tiene propuesta.

Proceso (herramientas en la bóveda del cliente, `video/herramientas/`):

1. `guion.json`: una línea de voz y un rótulo por escena.
2. Voz con ElevenLabs (`eleven_v3`; la voz elegida por Luis fue Matilda). Gasta créditos: preguntar antes. No hay
   voces chilenas prehechas; v3 acentúa mejor que multilingual v2.
3. Revisar cada línea con `scribe_v1` y medir la caída final en ventanas de 10 ms: si cae en 40 ms o menos, la
   sílaba quedó cortada; se arregla cerrando la frase con «...» y regenerando solo esa línea.
4. Grabar con el Chrome instalado (Playwright 1.63, `executablePath`) por capturas CDP
   (`Page.captureScreenshot`), recortando al marco del teléfono en las escenas móviles; `recordVideo` sale borroso.
5. Montar con PIL + ffmpeg (duración de cada cuadro según la voz, `adelay` + `amix` + `loudnorm`) y concatenar el
   cierre.
6. Revisar hojas de contacto de los cuadros antes de entregar.

🔴 Un script de voz que genera muestras al importarse gasta créditos cada vez que otro script lo importa: toda
acción con costo va detrás de `if __name__ == '__main__'`.
