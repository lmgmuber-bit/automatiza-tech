# Manifiesto FTP CumpleClick — no desplegado

Destino público objetivo: `/public_html/cumpleclick/`. Las rutas privadas son
placeholders y deben resolverse fuera de `public_html` antes del cutover.

## ⚠️ Los nombres de `assets/` CAMBIAN en cada build

Vite pone un hash en el nombre de cada bundle. **Nunca copies esta tabla a mano de
una entrega anterior**: si subes `index-<hash-viejo>.js`, el `index.html` nuevo
va a pedir un archivo que no existe y el kiosco queda en blanco (el `.htaccess`
devuelve 404 limpio en vez de servir HTML, así que el fallo es visible pero
total).

Antes de cada subida, saca la lista real:

```bash
ls dist/assets/          # nombres exactos de este build
grep -o 'assets/[a-zA-Z0-9._-]*' dist/index.html   # lo que index.html pide
```

Sube **todos** los de `dist/assets/` junto con `dist/index.html` en la misma
tanda. Los que sobren del build anterior se pueden borrar después.

## DESPLEGADO 2026-09-13 (madrugada) — PROD alineado con los repositorios

El cotejo completo y la tabla de qué carpeta sale de qué repositorio están en
`MAPA-PROD-Y-REPOSITORIOS.md`.

**En PROD y verificado desde afuera:**

- **`public_html/.htaccess`** (raíz del dominio) lleva el mismo `FilesMatch` de respaldos que
  `app/.htaccess`.
  - `https://cumpleclick.com/index.php.bak-20260907` (el código de la portada) respondía 200 como
    `text/plain`; ahora da 403.
  - `.htaccess.bak` ya daba 403.
  - La portada y el kiosco siguen en 200, y `urls-criticas.py despues` no muestra cambios.
  - Respaldo: `~/respaldos/raiz-htaccess.antes-bloqueo-bak-20260912-2355`. sha256 nuevo `b3f14149…`.
- **Fuera de `public_html`, sin exposición web:** doce migraciones que faltaban en
  `database/migrations/` y tres scripts más nuevos del repositorio.
  - Migraciones: `012`, `013_narration_intro_output`, `014_rsvp`, `017` (con su `.down`), `018`,
    `019`, `020`, `021_sala_carreras` (con su `.down`) y `022` (con su `.down`). La base ya las
    tenía aplicadas: `cc_schema_migrations` registra 24.
  - Scripts: `export-party-sql.php`, `web/_at-migrar.php` y `web/_at-seed-cita-completa.php`.
  - Los quince pasaron sha256 y `php -l`.
  - Respaldo: `~/respaldos/herramientas-antes-alinear-20260913-0035.tar.gz`.

**NO subido, porque lo bloquearon los permisos (OPCIONAL):** `database/migrations/003_invitations_and_plan.php`
(el repositorio agrega guardas) y `scripts/retention.php` (solo fines de línea). Ninguno se sirve
por la web ni se ejecuta solo.

**En esta rama:** el commit `e3e3959` trae 63 archivos que PROD servía y el repositorio no tenía o
tenía distintos:

- backend de juegos: `lib.puntajes.php`, `sala.php`, `lib.sala.carrera.php` y `lib.sala.php`;
- migraciones `014_salas_ayudantes` y `021_sala_carreras`;
- el menú `juego/index.html` y `juego/fuentes/`;
- `admin/marca.php`, `brand/carteles/` y el logo del PDF;
- las cabeceras de correo;
- los scripts de `database/` corridos en el servidor;
- `sitio/.htaccess`.

**Los juegos pasaron a repositorios privados de `lmgmuber-bit`**, con la rama igual a PROD. La
lista está en el mapa.

**Lista FTP pendiente:** nada OBLIGATORIO. OPCIONAL: los dos archivos bloqueados de arriba.

## DESPLEGADO 2026-09-12 (noche) — los respaldos `.bak` de `app/` ya no se sirven

**En PROD y verificado desde afuera (23:15):** `app/.htaccess` lleva al final un
`<FilesMatch "\.(bak|old|orig|save|swp|antes)([-._].*)?$|~$">` con `Require all denied` (y el
`Deny from all` de Apache 2.2, igual que `data/.htaccess`). Los 61 `.bak` responden 403, con y sin
parámetro en la URL; antes `admin/config.php.bak-20260907b` y `lib.php.bak-20260908-hotfix`
salían 200 como `text/plain`. **No se borró ningún archivo.**

- Se agregó solo texto ASCII en CRLF, como el resto del archivo: sha256 `b02fb095…`, 4578 bytes.
  El mismo contenido quedó en `CumpleBooth/public/.htaccess` de esta rama, para que un despliegue
  futuro no borre el bloqueo.
- Respaldo: `~/respaldos/app-htaccess.antes-bloqueo-bak-20260912-2315`. Para deshacer:
  `cat ~/respaldos/app-htaccess.antes-bloqueo-bak-20260912-2315 > ~/domains/cumpleclick.com/public_html/app/.htaccess`.
- `urls-criticas.py despues`: ninguna de las 10 direcciones cambió. Siguen en 200 los bundles del
  kiosco, `juego/mundo.html` y sus scripts, `hermana.glb`, los PDF de `brand/carteles/`, el `.wasm` y
  el `.tflite` de MediaPipe, `api.php` y `puntajes.php`. Los 403 de `brand/carteles/` y
  `vendor/mediapipe/` son por pedir la carpeta sin archivo, y el de `manual.php` sin firma lo da el
  propio PHP (línea 27).
- El CDN no los tenía en caché (`x-hcdn-cache-status: DYNAMIC`): el bloqueo rigió al instante, sin
  purgar.
- **Qué había quedado expuesto: código, ningún valor de secreto.** `admin/config.php.bak` define
  `ADMIN_PASSWORD_HASH` leyéndolo con `cb_config()`, no escrito; `lib.php.bak` solo nombra
  `CC_PDO_PASSWORD`, `CC_SMTP_PASSWORD` y `CC_APP_HMAC_KEY`, con valores por defecto vacíos, porque se
  leen del entorno o del archivo de configuración fuera de la carpeta pública. Revisados los 61 sin
  imprimir ningún valor. Es el mismo código del repositorio. **No hace falta cambiar claves.** Por
  SSH no hay logs de acceso para saber quién los pidió.
- **Pendiente:** borrar los 61 cuando Luis lo decida. Los respaldos de un despliegue van a
  `~/respaldos/`, nunca como `<archivo>.bak-*` junto al original.

## DESPLEGADO 2026-09-12 (tarde) — copia en la tablet, y lo que quedó abierto

**En PROD y verificado desde afuera (15:23):** `assets/main-SnNR4OCg.js` e `index.html`.
Asómate y la foto grupal bajan la foto a la tablet **antes** de subirla, como ya hacía la
cabina (`guardarEnLaTablet` en `App.jsx`). Comprobado en PROD con la fiesta `samantha-hielo`,
cámara falsa y `fetch` de `upload.php` rechazando: se disparó `grupal-Samantha-<sello>.jpg`,
464 KB, y quedó el botón de reintentar. Respaldo:
`~/respaldos/index.html.antes-respaldo-tablet-20260912-1523`.

**Generadores guardados en el repositorio:** `CumpleBooth/design/generadores/` (carteles de
marca, piezas de Instagram, marco de la foto grupal y Anna de gala). Hasta hoy vivían solo en
una carpeta temporal de sesión. Cómo correrlos, en el `README.md` de esa carpeta.

### Hallazgos que NO se arreglaron (decisión de Luis pendiente)

- ~~**61 respaldos `.bak` públicos en `app/`**~~ **Bloqueados esa misma noche** (sección de
  arriba). Aquí se dijo que `config.php.bak` exponía el hash del admin: no lo trae, lo lee de la
  configuración. Falta decidir el borrado.
- **Los carteles de marca corregidos no están subidos.** La hoja de servicios pasó a seis
  tarjetas (entran Asómate, con sus dos temáticas nombradas, y la foto de todos) y las hojas 1
  y 2 perdieron el bloque de WhatsApp con el número impreso. **En PROD el admin todavía entrega
  las hojas viejas con el número.** Las medidas de acrílico (146 × 206, A5, A6, 13 × 18 y A4)
  existen solo en local.
- **El PIN de la galería se bloquea con muchos papás en el mismo wifi** y **reintentar una
  subida duplica la foto.** Detalle en `PENDIENTES-DE-PRUEBA.md` §9.
- **El Plan Premium dice "Los 4 juegos, con El Show 3D"** y El Show 3D no está entre los
  juegos desplegados: PROD ofrece 3 en hielo y 3 en spidey (contado en `puntajes.php` el
  2026-09-12).

### Medido y descartado

Los videos de bienvenida de hielo y spidey y `entrada-palacio-hielo.mp4` tienen el índice
`moov` al final. **No es un defecto:** el servidor responde 206 a pedidos de rango y el
navegador pide el final aparte. Medido desde afuera: 1,9 s con dos pedidos contra 1,8 s
bajando el archivo entero, y Luis los probó en las dos tablets.

## DESPLEGADO 2026-09-12 — foto grupal, pantalla completa, Anna de gala

Todo esto **está en PROD** (`cumpleclick.com/app/`), verificado desde afuera.
Respaldos para volver atrás en `~/respaldos/`:
`app-antes-grupal-20260912-0116.tar.gz`, `galeria.php.antes-pestana-20260912-0132`,
`index.html.antes-roundrect-*` y `juego-temas-antes-20260912-0202.tar.gz`.

**La foto de todos.** Una foto del grupo entero dentro de un marco apaisado, distinta de la
foto de cabina y de Asómate. Se dispara desde un **ícono chico abajo a la izquierda** de la
bienvenida, no desde la pila de botones: la usa un adulto una o dos veces en la fiesta y
darle el mismo peso que a los botones que tocan los niños tapaba la decoración. Avisa de
poner la tablet acostada mientras la pantalla esté de pie, y **avisa, no bloquea**: con el
giro trabado —lo normal en un kiosco— bloquear dejaría el modo inservible en plena fiesta.
Se archiva con prefijo `grupal-` porque no tiene dueño, y la galería le da su propia pestaña.

**Pantalla completa** en la bienvenida, arriba a la izquierda, la misma esquina que ya usan
los juegos. Entra con el toque que arranca la fiesta, porque fuera de un gesto el navegador
la rechaza sin avisar, y el mismo botón sirve para salir.

**Anna de gala** en los dos juegos que la usan (`juego/models/` y `juego/festival/models/`),
repintando la textura del modelo: malla, esqueleto y animación quedan idénticos y no se
gastó ningún crédito. 2,61 MB contra 2,30; se baja en el mismo tiempo que Elsa.

### 🔴 Tres trampas que mordieron hoy

1. **`roundRect` no existe en este proyecto; se llama `roundRectPath`.** La excepción rompía
   la cadena de promesas y la vista previa quedaba en "Preparando la foto…" **para siempre**,
   sin error visible y sin salida. No lo vieron las pruebas de backend —miran el tema, no la
   pantalla— ni la prueba a mano, porque el panel del navegador bloquea la cámara y nunca
   llega a la vista previa. **Para probar esta pantalla hace falta una cámara falsa**: un
   canvas con `captureStream()`, la misma técnica que ya se usa en Asómate. Ahora, si componer
   falla, se muestra la foto sin marco y se puede guardar.

2. **Calcular una lista y no dibujarla.** La galería separaba las fotos grupales del reparto
   por invitado pero no tenía pestaña donde mostrarlas: la foto se habría subido y
   desaparecido. Hay una guardia en `tests/backend/grupal.php` para ese fallo exacto.

3. **El CDN.** Bajar un archivo para compararlo **calienta la caché del borde**: si después
   subes el reemplazo, el borde sigue entregando el viejo hasta 24 horas. Pasó con
   `hermana.glb`. Se resuelve con un flush desde hPanel o esperando.

## DESPLEGADO 2026-09-12 — el juego 3D vuelve a tener su fuente en el repositorio

El repositorio `C:\wamp64\www	ucumple-repo` estaba **atrasado respecto de PROD** y exportar
desde él habría borrado trabajo en silencio: sube bien y reporta éxito. Eran cinco archivos
(`game/main.js`, `ui.js`, `foto.js`, `strings.js`, `index.html`) más `game/posiciones.js`, que
directamente no existía aunque `main.js` lo importa. Los dos espejos, `juego-prod/` y
`tucumple/`, estaban **aún más atrasados** y eran los que iban a pisar PROD.

🔴 **`index.html` del repositorio NO es `index.html` de PROD.** En `app/juego/` ese nombre es
el **menú de juegos**, que viene de CumpleBooth y cuya URL está impresa en los carteles QR.
La página del repositorio se sirve como **`mundo.html`**. Copiar una encima de la otra rompe
el menú o borra el juego. Queda dicho dentro del propio archivo.

🔴 **Bug que estaba vivo en PROD y se corrigió: la foto del juego reventaba en Spidey.**
`aplicarTema` mezclaba los textos con `Object.assign`, que es de un solo nivel: una temática
que declara su propio bloque `foto` reemplazaba el bloque **entero** y perdía las claves que
la base agregó después. La temática arácnida tiene su propio `foto`, así que desde el 11-sep
`T.foto.firmaJugador` no existía ahí y sacar una foto moría con "is not a function". **No se
veía al cargar: solo al sacar la foto.** Ahora la mezcla entra un nivel y los arreglos se
siguen reemplazando, o el tutorial arácnido quedaría mezclado con el de hielo. Comprobado
contra PROD con `luciano-spidey`.

### Sin probar

Nada de esto se ha visto en la tablet física ni con niños: la foto grupal se probó con una
cámara falsa, y la foto del juego 3D se comprobó leyendo los textos ya mezclados, no sacando
una foto de verdad en una partida.

## Delta local — Álbum Recuerdo (rama `feat/album-recuerdo`, no desplegado)

Este delta **incluye y reemplaza** al de Rayo/Carreras/Hielo de abajo: se
construyó encima de él, así que subiendo esta tabla va todo junto.

Verificado local: `npm test` 96/96, `tests/backend/album.php` 157 checks en PHP
8.0–8.4, `npm run build` limpio, `check-dist-parity.php` exit 0 (289 archivos).
**No probado en PROD.**

### ⚠️ Los bundles cambiaron de nombre

El build ahora tiene tres entradas (kiosco, álbum, cartel), así que el bundle
del kiosco pasó de `index-*.js` a **`main-*.js`**. Después de subir hay que
**borrar los `assets/index-*.js` y `assets/index-*.css` viejos** del servidor:
ya no los pide nadie y confunden en la próxima entrega.

**Antes de subir corre `ls dist/assets/` — los hashes de abajo son los de ESTE
build y cambian en el próximo.**

### 1. Base de datos (primero, antes de los archivos)

```bash
php scripts/migrate.php
```

Aplica la migración `007_event_album` (tres tablas nuevas: `cc_event_albums`,
`cc_event_album_tokens`, `cc_event_media`). Es aditiva: no altera ninguna tabla
existente. Si algo sale mal, `007_event_album.down.php` las borra y deja el
esquema exactamente como estaba.

### 2. Archivos

| Ruta local exacta | Destino PROD relativo | Clase |
|---|---|---|
| `CumpleBooth/dist/lib.php` | `/public_html/cumpleclick/lib.php` | OBLIGATORIO — **subir primero**: los demás PHP lo requieren |
| `CumpleBooth/dist/lib.album.php` | `/public_html/cumpleclick/lib.album.php` | OBLIGATORIO — antes que el resto de PHP nuevos |
| `CumpleBooth/dist/subir.php` | `/public_html/cumpleclick/subir.php` | OBLIGATORIO — página de carga del invitado |
| `CumpleBooth/dist/_album-intake.css.php` | `/public_html/cumpleclick/_album-intake.css.php` | OBLIGATORIO — estilos de `subir.php` |
| `CumpleBooth/dist/album-intake.php` | `/public_html/cumpleclick/album-intake.php` | OBLIGATORIO — endpoint de carga |
| `CumpleBooth/dist/album-api.php` | `/public_html/cumpleclick/album-api.php` | OBLIGATORIO — datos de la revista y del cartel |
| `CumpleBooth/dist/ver-media.php` | `/public_html/cumpleclick/ver-media.php` | OBLIGATORIO — sirve el material aportado |
| `CumpleBooth/dist/admin/album.php` | `/public_html/cumpleclick/admin/album.php` | OBLIGATORIO — admin del álbum |
| `CumpleBooth/dist/admin/_style.css.php` | `/public_html/cumpleclick/admin/_style.css.php` | OBLIGATORIO — estilos de curaduría |
| `CumpleBooth/dist/admin/index.php` | `/public_html/cumpleclick/admin/index.php` | OBLIGATORIO — agrega el enlace "Álbum Recuerdo" por fiesta |
| `CumpleBooth/dist/assets/main-C-n-yZAV.js` | `/public_html/cumpleclick/assets/main-C-n-yZAV.js` | OBLIGATORIO — kiosco, **antes** que `index.html` |
| `CumpleBooth/dist/assets/main-Bj9ob-eC.css` | `/public_html/cumpleclick/assets/main-Bj9ob-eC.css` | OBLIGATORIO — kiosco |
| `CumpleBooth/dist/assets/themeVars-BR9-zmCZ.js` | `/public_html/cumpleclick/assets/themeVars-BR9-zmCZ.js` | OBLIGATORIO — compartido por las tres entradas |
| `CumpleBooth/dist/assets/three.module-Y-ql4QRg.js` | `/public_html/cumpleclick/assets/three.module-Y-ql4QRg.js` | OBLIGATORIO — kiosco (no cambió, pero verifica que esté) |
| `CumpleBooth/dist/assets/album-DgeQpXAO.js` | `/public_html/cumpleclick/assets/album-DgeQpXAO.js` | OBLIGATORIO — revista |
| `CumpleBooth/dist/assets/album-xlAm6Rb1.css` | `/public_html/cumpleclick/assets/album-xlAm6Rb1.css` | OBLIGATORIO — revista |
| `CumpleBooth/dist/assets/cartel-D9nSGMpQ.js` | `/public_html/cumpleclick/assets/cartel-D9nSGMpQ.js` | OBLIGATORIO — cartel QR |
| `CumpleBooth/dist/assets/cartel-V-FnTnZT.css` | `/public_html/cumpleclick/assets/cartel-V-FnTnZT.css` | OBLIGATORIO — cartel QR |
| `CumpleBooth/dist/assets/browser-BeMEBtOm.js` | `/public_html/cumpleclick/assets/browser-BeMEBtOm.js` | OBLIGATORIO — librería de QR del cartel |
| `CumpleBooth/dist/album.html` | `/public_html/cumpleclick/album.html` | OBLIGATORIO — **después** de sus assets |
| `CumpleBooth/dist/cartel-qr.html` | `/public_html/cumpleclick/cartel-qr.html` | OBLIGATORIO — **después** de sus assets |
| `CumpleBooth/dist/index.html` | `/public_html/cumpleclick/index.html` | OBLIGATORIO — **el último de todos** |

Más los archivos del delta de Rayo/Carreras/Hielo de la sección siguiente
(`data/themes.json`, los fondos de Carreras, los videos y el pase de artista de
Hielo), que tampoco están en PROD.

### 3. Después de subir

- Borrar `assets/index-*.js` y `assets/index-*.css` del servidor (bundles viejos).
- `admin/album.php?party=<slug>` debe abrir y pedir contraseña.
- `subir.php` sin token debe dar **400** con la página de enlace inválido.
- `album.html` sin token debe mostrar el mensaje de enlace no disponible.
- `ver-media.php?t=<32 hex inventado>` debe dar **404**.
- Verificar que la carpeta `photo_dir` tenga permiso de escritura: ahí se crea
  `album/<slug>/AAAA/MM/`.

### 4. Lo que NO se sube

`src/`, `tests/`, `node_modules/`, `database/`, `scripts/`, `config/`, el
archivo de configuración real, fotos, backups ni `_assets-produccion/`.

---

## Delta local — sesión 2026-08-04 (Rayo/Carreras/Hielo, no desplegado)

Reemplaza y completa el delta parcial "AUD-2026-08-03" de abajo (ese lo dejó
Codex a mitad de auditoría; esta tabla es el cierre real, con el hash de
`assets/` de este build y el pase de artista nuevo de Hielo que faltaba ahí).
Verificado local: `npm test` 83/83, `npm run build` limpio,
`check-dist-parity.php` exit 0 (282 archivos). No probado en PROD.

**⚠️ Antes de subir, corre `ls dist/assets/` — el hash de abajo es el de ESTE
build y cambia en el próximo.**

| Ruta local exacta | Destino PROD relativo | Clase |
|---|---|---|
| `CumpleBooth/dist/assets/index-CsML1zLD.js` | `/public_html/cumpleclick/assets/index-CsML1zLD.js` | OBLIGATORIO — subir antes que `index.html` |
| `CumpleBooth/dist/assets/index-Bj9ob-eC.css` | `/public_html/cumpleclick/assets/index-Bj9ob-eC.css` | OBLIGATORIO |
| `CumpleBooth/dist/data/themes.json` | `/public_html/cumpleclick/data/themes.json` | OBLIGATORIO — fondos propios de ritmo/copos/pantalla LED en Carreras, `photoSession` nuevo de Hielo |
| `CumpleBooth/dist/themes/carreras/fondo-pantalla-circuito.jpg` | `/public_html/cumpleclick/themes/carreras/fondo-pantalla-circuito.jpg` | OBLIGATORIO — pantalla LED del Show 3D, las 6 personajes |
| `CumpleBooth/dist/themes/carreras/fondo-juego-ritmo.jpg` | `/public_html/cumpleclick/themes/carreras/fondo-juego-ritmo.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/fondo-juego-boxes.jpg` | `/public_html/cumpleclick/themes/carreras/fondo-juego-boxes.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/revelacion-carreras.mp4` | `/public_html/cumpleclick/themes/carreras/revelacion-carreras.mp4` | OBLIGATORIO — "Cargando tu foto", ambiente (sin voz Alice todavía) |
| `CumpleBooth/dist/themes/carreras/despedida-carreras.mp4` | `/public_html/cumpleclick/themes/carreras/despedida-carreras.mp4` | OBLIGATORIO — optimizado + voz Alice (Codex) |
| `CumpleBooth/dist/themes/familia-canina/revelacion-familia-canina.mp4` | `/public_html/cumpleclick/themes/familia-canina/revelacion-familia-canina.mp4` | OBLIGATORIO — "Cargando tu foto", ambiente (sin voz Alice todavía) |
| `CumpleBooth/dist/themes/familia-canina/despedida-familia-canina.mp4` | `/public_html/cumpleclick/themes/familia-canina/despedida-familia-canina.mp4` | OBLIGATORIO — optimizado + voz Alice (Codex) |
| `CumpleBooth/dist/themes/tropical/revelacion-tropical.mp4` | `/public_html/cumpleclick/themes/tropical/revelacion-tropical.mp4` | OBLIGATORIO — optimizado (Codex) |
| `CumpleBooth/dist/themes/tropical/despedida-tropical.mp4` | `/public_html/cumpleclick/themes/tropical/despedida-tropical.mp4` | OBLIGATORIO — optimizado (Codex) |
| `CumpleBooth/dist/themes/hielo/revelacion.mp4` | `/public_html/cumpleclick/themes/hielo/revelacion.mp4` | OBLIGATORIO — optimizado (Codex) |
| `CumpleBooth/dist/themes/hielo/despedida-hielo.mp4` | `/public_html/cumpleclick/themes/hielo/despedida-hielo.mp4` | OBLIGATORIO — optimizado + voz Alice (Codex) |
| `CumpleBooth/dist/themes/hielo/entrada-palacio-hielo.mp4` | `/public_html/cumpleclick/themes/hielo/entrada-palacio-hielo.mp4` | OBLIGATORIO — nuevo: pase de artista Elsa+Anna (video completo + teaser bajo la ruleta, mismo archivo para ambos) |
| `CumpleBooth/dist/themes/hielo/entrada-palacio-hielo-poster.jpg` | `/public_html/cumpleclick/themes/hielo/entrada-palacio-hielo-poster.jpg` | OBLIGATORIO — poster/teaser del pase de artista |
| `CumpleBooth/dist/themes/kpop/despedida-kpop.mp4` | `/public_html/cumpleclick/themes/kpop/despedida-kpop.mp4` | OBLIGATORIO — optimizado + voz Alice (Codex) |
| `CumpleBooth/dist/themes/heroes/revelacion-heroes.mp4` | `/public_html/cumpleclick/themes/heroes/revelacion-heroes.mp4` | OPCIONAL — Héroes sigue bloqueado (sin `despedida-heroes.mp4`, sin `saludo-*.mp4`); no ofrecer el tema aunque subas este archivo suelto |
| `CumpleBooth/dist/index.html` | `/public_html/cumpleclick/index.html` | OBLIGATORIO — subir último |

No subir: `CumpleBooth/_assets-produccion/` (material de producción, no es
del kiosco), `CumpleBooth/NUL;` (artefacto de shell, basura), backups
temporales, WAV de auditoría de Codex ni `despedida-heroes.mp4` (no existe).

**Pendiente, no bloqueante:** `revelacion-carreras.mp4` y
`revelacion-familia-canina.mp4` llevan ambiente generado, no la voz Alice
diciendo "Cargando tu foto" — el texto en pantalla ya lo cubre, pero queda
por debajo del estándar de Hielo/K-Pop/Tropical. Ver
`docs/CODEX-HANDOFF-VOZ-Y-VIDEOS-TEMATICAS.md`.

## Delta local AUD-2026-08-03 - MP4 optimizados (superado, ver arriba)

Borrador parcial que dejó Codex a mitad de la auditoría de audio — la tabla
de arriba ya lo incluye completo. Se conserva solo como rastro histórico.
## Delta AT-CUMPLECLICK-012 — misión WOW 3D Full (2026-07-29)

Estado: **solo local, no desplegado**. Este delta no incorpora multimedia
nueva ni requiere migración de BD.

| Orden | Ruta local exacta | Destino PROD relativo | Clase |
|---:|---|---|---|
| 1 | `CumpleBooth/dist/lib.php` | `/public_html/cumpleclick/lib.php` | OBLIGATORIO — gate servidor Full/Booth. 2026-08-01: la lista blanca de `kind` acepta `concierto3d` (El Show) y saneador de `stage`. **Sin este archivo, las seis temáticas se quedan sin misión Full**: el backend descarta el juego entero por `kind` desconocido y el invitado va directo a la cámara |
| 2 | `CumpleBooth/dist/data/themes.json` | `/public_html/cumpleclick/data/themes.json` | OBLIGATORIO — 2026-08-01: las SEIS temáticas completas pasan de `mundo3d` a `concierto3d`, cada una con su `stage` (neon-arena, ice-gala, beach-luau, podium-night, backyard-fiesta, rooftop-city). Sube junto con `lib.php` en la misma tanda |
| 3 | `CumpleBooth/dist/admin/index.php` | `/public_html/cumpleclick/admin/index.php` | OBLIGATORIO — explicación del beneficio Full |
| 4 | `CumpleBooth/dist/assets/three.module-Y-ql4QRg.js` | `/public_html/cumpleclick/assets/three.module-Y-ql4QRg.js` | OBLIGATORIO |
| 5 | `CumpleBooth/dist/assets/index-jouQAXOm.js` | `/public_html/cumpleclick/assets/index-jouQAXOm.js` | OBLIGATORIO — hash 2026-08-01 (entrega final del día). Acumula: El Show 3D en las 6 temáticas (`StageConcert3D.jsx` + `SHOW_STYLES`), Ritmo y Escudo reescritos, récords de fiesta (`records.js`), tercera opción en la pantalla de oferta, y `textSide='left'` de Héroes |
| 6 | `CumpleBooth/dist/assets/index-ahENApX2.css` | `/public_html/cumpleclick/assets/index-ahENApX2.css` | OBLIGATORIO — clases `.show3d-*`, pista/pads del Ritmo, escudos con vida, marcador de récord, tercer botón de la oferta, y el arreglo de responsividad (`vw` → `cqw` en todos los juegos) |
| 7 | `CumpleBooth/dist/themes/carreras/game3d/` | `/public_html/cumpleclick/themes/carreras/game3d/` | OBLIGATORIO — seis atlas |
| 8 | `CumpleBooth/dist/themes/familia-canina/game3d/` | `/public_html/cumpleclick/themes/familia-canina/game3d/` | OBLIGATORIO — seis atlas |
| 9 | `CumpleBooth/dist/themes/tropical/game3d/` | `/public_html/cumpleclick/themes/tropical/game3d/` | OBLIGATORIO — cuatro atlas aprobados; los otros dos usan fallback |
| 9b | `CumpleBooth/dist/themes/tropical/despedida-tropical.mp4` | `/public_html/cumpleclick/themes/tropical/despedida-tropical.mp4` | OBLIGATORIO — nuevo 2026-08-01, tropical ya está en PROD sin este archivo, narración "voz Alice" |
| 9c | `CumpleBooth/dist/themes/tropical/roulette/roulette-background-v1.png` | `/public_html/cumpleclick/themes/tropical/roulette/roulette-background-v1.png` | OBLIGATORIO — nuevo 2026-08-01, foto de fondo de la ruleta |
| 9d | `CumpleBooth/dist/themes/tropical/revelacion-tropical.mp4` | `/public_html/cumpleclick/themes/tropical/revelacion-tropical.mp4` | OBLIGATORIO — nuevo 2026-08-01, tropical no tenía video de revelación; ambiente + voz "Alice" diciendo "Cargando tu foto..." |
| 9e | `CumpleBooth/dist/themes/carreras/fondo-juego-circuito.jpg` | `/public_html/cumpleclick/themes/carreras/fondo-juego-circuito.jpg` | OBLIGATORIO — nuevo 2026-08-01, fondo del show 3D (pit lane nocturno). Sin él el show usa el cielo procedural: no rompe, pero se ve más pobre |
| 9f | `CumpleBooth/dist/themes/tropical/fondo-juego-playa.jpg` | `/public_html/cumpleclick/themes/tropical/fondo-juego-playa.jpg` | OBLIGATORIO — nuevo 2026-08-01, fondo del show 3D (playa nocturna) |
| 9g | `CumpleBooth/dist/themes/familia-canina/fondo-juego-patio.jpg` | `/public_html/cumpleclick/themes/familia-canina/fondo-juego-patio.jpg` | OBLIGATORIO — nuevo 2026-08-01, fondo del show 3D (patio con guirnaldas) |
| 10 | `CumpleBooth/dist/themes/kpop/game3d/` | `/public_html/cumpleclick/themes/kpop/game3d/` | OBLIGATORIO — seis atlas |
| 11 | `CumpleBooth/dist/themes/familia-canina/visual-manifest.v1.json` | `/public_html/cumpleclick/themes/familia-canina/visual-manifest.v1.json` | OPCIONAL — hashes y trazabilidad |
| 12 | `CumpleBooth/dist/index.html` | `/public_html/cumpleclick/index.html` | OBLIGATORIO — subir último |

No subir carpetas `hielo/game3d` ni `heroes/game3d`: no contienen assets
aprobados. Los personajes de esos mundos mantienen el fallback existente hasta
que Luis adjunte atlas generados manualmente.

Los tres WOFF2 de Baloo 2 conservan sus hashes. Son obligatorios únicamente si
todavía no existen en PROD. No subir `src/`, `tests/`, `docs/`,
`qa-evidence/`, `graphify-out/`, `tmp/`, configuración real, fotos privadas,
backups ni dumps.

## Delta local — actualizado 2026-07-27

Reemplaza el delta del 2026-07-26: los hashes de aquella lista
(`index-BXrjMzs5.js`, `index-DGDiPOXD.css`) **ya no existen**, el proyecto se
reconstruyó desde entonces.

| Orden | Ruta local | Destino PROD relativo | Clase |
|---:|---|---|---|
| 1 | `CumpleBooth/scripts/backfill-theme-production-prompts.php` | `<PRIVATE_APP>/scripts/backfill-theme-production-prompts.php` | OBLIGATORIO para poblar prompts privados |
| 2 | `CumpleBooth/dist/lib.php` | `/public_html/cumpleclick/lib.php` | OBLIGATORIO |
| 3 | `CumpleBooth/dist/data/themes.json` | `/public_html/cumpleclick/data/themes.json` | OBLIGATORIO |
| 4 | `CumpleBooth/dist/admin/index.php` | `/public_html/cumpleclick/admin/index.php` | OBLIGATORIO |
| 5 | `CumpleBooth/dist/admin/_style.css.php` | `/public_html/cumpleclick/admin/_style.css.php` | OBLIGATORIO |
| 6 | `CumpleBooth/dist/index.html` | `/public_html/cumpleclick/index.html` | OBLIGATORIO — apunta a los assets de abajo |
| 7 | `CumpleBooth/dist/assets/index-D6aGK6Cn.js` | `/public_html/cumpleclick/assets/index-D6aGK6Cn.js` | OBLIGATORIO — hash 2026-07-28 (ducking música + sonido al atrapar + "trampas" + ícono del juego por temática; foto final: texto "Muchas gracias" al costado del marco SOLO en K-Pop, resto de temáticas centrado abajo como siempre; personaje de la foto un poco más abajo SOLO en Frozen/hielo y K-Pop, resto igual que antes; placa con el nombre del personaje sin cambios) |
| 8 | `CumpleBooth/dist/assets/index-DypfIdNZ.css` | `/public_html/cumpleclick/assets/index-DypfIdNZ.css` | OBLIGATORIO — hash nuevo 2026-07-28 (`src/styles.css`: estilos del aviso 🚫) |
| 9 | `CumpleBooth/dist/assets/three.module-Y-ql4QRg.js` | `/public_html/cumpleclick/assets/three.module-Y-ql4QRg.js` | OBLIGATORIO |
| 10 | `CumpleBooth/dist/assets/baloo-2-latin-600-normal-tIfxVoAe.woff2` | `/public_html/cumpleclick/assets/baloo-2-latin-600-normal-tIfxVoAe.woff2` | OBLIGATORIO |
| 11 | `CumpleBooth/dist/assets/baloo-2-latin-700-normal-CqTg7A15.woff2` | `/public_html/cumpleclick/assets/baloo-2-latin-700-normal-CqTg7A15.woff2` | OBLIGATORIO |
| 12 | `CumpleBooth/dist/assets/baloo-2-latin-800-normal-BbF3Etk1.woff2` | `/public_html/cumpleclick/assets/baloo-2-latin-800-normal-BbF3Etk1.woff2` | OBLIGATORIO |
| 13 | `CumpleBooth/dist/themes/hielo/**` | `/public_html/cumpleclick/themes/hielo/` | Solo si cambió (incluye el arreglo visual de Olaf) |
| 14 | `CumpleBooth/dist/themes/kpop/**` | `/public_html/cumpleclick/themes/kpop/` | OBLIGATORIO — carpeta completa rehecha 2026-07-27/28: 6 retratos + `fondo-banner.jpg` con el look real de la película (no muñeca), 6 `saludo-*.mp4`, `welcome-kpop.mp4`, `revelacion-kpop.mp4`, `despedida-kpop.mp4`, 6 `invitacion-juego-*.mp3` (narración "voz Alice"), `musica-fondo.mp3`, y 6 `*-cut.png` (recorte transparente para que el personaje salga en la foto final). **2026-08-01, correcciones nuevas dentro de la misma carpeta:** `roulette/roulette-background-v1.png` (nuevo, foto de las 4 chicas como fondo de la ruleta), `revelacion-kpop.mp4` (reemplazado dos veces: primero solo narración, luego remezclado con el audio ambiente original de vuelta pero por debajo del volumen de la voz — "Cargando tu foto..." con Alice arriba, ambiente de fondo abajo), y `themes.json` con `photoSession.teaserVideo` apuntando al `entrada-escenario.mp4` ya existente (la tarjeta bajo la ruleta ahora anima en vez de quedar estática). Si ya subiste una versión anterior, esta la reemplaza entera |

Los órdenes 6-12 incluyen el arreglo del juego de armar a Olaf (la nariz de
zanahoria no se dibujaba: `border-width` en porcentaje no es válido en CSS).

Después del orden 1, ejecutar en PROD primero en dry-run y revisar la salida:
`php scripts/backfill-theme-production-prompts.php`; solo entonces usar
`--apply`. `demo-tropical`, `demo-kpop` y `demo-heroes` son datos locales de
verificación y **no se siembran en PROD**.

## Multimedia de K-Pop y Héroes: por FTP, no por Admin

K-Pop ya tiene los 6 `saludo-*.mp4` de personajes y los 3 videos de secuencia
(`welcome-kpop.mp4`, `revelacion-kpop.mp4`, `despedida-kpop.mp4`) generados y
verificados en local el 2026-07-27 — pendiente solo subirlos por FTP (orden 14
arriba). K-Pop ya tiene `musica-fondo.mp3` ("Golden" de HUNTR/X, puesta por
Luis el 2026-07-27 — **ojo: es la canción real con derechos de la película,
no generada**, revisar licencia antes de ofrecer el tema a clientes). Falta
el theme completo de Héroes:

| Temática | Falta |
|---|---|
| Héroes | `welcome-heroes.mp4`, `despedida-heroes.mp4` — `revelacion-heroes.mp4` ✅, `fondo-banner.jpg` ✅ (v2 2026-08-01: personajes subidos, ya no quedaban tan abajo), `musica-fondo.mp3` ✅, `roulette/roulette-background-v1.png` ✅ (regenerado del banner v2) y los 6 `*-cut.png` ✅ agregados 2026-08-01. **Nombres reales de la franquicia en `themes.json` (2026-08-01):** Spider-Man, Hulk, Iron Man, Capitán América, Thor, Pantera Negra — reemplazan los nombres camuflados (Araña, Gigante Verde, Hombre de Hierro, Capitán, Trueno, Pantera) que solo debían usarse en prompts de generación, nunca en el producto. Solo faltan los 2 videos de personaje (welcome/despedida), pendientes hasta que Luis pida seguir con videos |

**Súbelos por FTP directo** a `/public_html/cumpleclick/themes/<slug>/`, no por
el Admin web. Motivo: la validación de video del Admin depende de `ffprobe`,
que un hosting compartido normalmente no trae — sin él, cada `.mp4` se rechaza.
Por FTP no hay validación PHP de por medio, no hay tope de 80MB, y los videos se
producen en local igual, donde se pueden verificar con ffmpeg antes de subir.

El kiosco **no necesita ffprobe para reproducir** — solo se usaba para validar
subidas. Las temáticas ya publicadas funcionan sin él.

Requisitos del archivo antes de subirlo (verificar en local):
`h264` · `yuv420p` · vertical 720x1280 (el estándar real usado por hielo y los
saludo-*.mp4 de kpop, no 1080x1920) · 5-8s los de bienvenida/despedida.

```bash
ffprobe -v error -show_entries stream=codec_name,width,height,pix_fmt \
  -show_entries format=duration -of default=noprint_wrappers=1 <archivo>.mp4
```

No ofrezcas K-Pop ni Héroes a un cliente hasta completar ese inventario y hacer
QA visual del flujo entero en la tablet.

## Orden 1 — privado, OBLIGATORIO

| Ruta local | Destino PROD relativo | Clase |
|---|---|---|
| `CumpleBooth/database/migrations/001_initial.php` | `<PRIVATE_APP>/database/migrations/001_initial.php` | OBLIGATORIO |
| `CumpleBooth/database/migrations/001_initial.down.php` | `<PRIVATE_APP>/database/migrations/001_initial.down.php` | OBLIGATORIO |
| `CumpleBooth/database/migrations/002_theme_prompts.php` | `<PRIVATE_APP>/database/migrations/002_theme_prompts.php` | OBLIGATORIO |
| `CumpleBooth/database/migrations/002_theme_prompts.down.php` | `<PRIVATE_APP>/database/migrations/002_theme_prompts.down.php` | OBLIGATORIO |
| `CumpleBooth/scripts/_cli.php` | `<PRIVATE_APP>/scripts/_cli.php` | OBLIGATORIO |
| `CumpleBooth/scripts/bootstrap.php` | `<PRIVATE_APP>/scripts/bootstrap.php` | OBLIGATORIO |
| `CumpleBooth/scripts/migrate.php` | `<PRIVATE_APP>/scripts/migrate.php` | OBLIGATORIO |
| `CumpleBooth/scripts/import-json-to-db.php` | `<PRIVATE_APP>/scripts/import-json-to-db.php` | OBLIGATORIO |
| `CumpleBooth/scripts/import-theme-prompts.php` | `<PRIVATE_APP>/scripts/import-theme-prompts.php` | OBLIGATORIO |
| `CumpleBooth/docs/PROMPTS-TEMATICAS.md` | `<PRIVATE_APP>/docs/PROMPTS-TEMATICAS.md` | OBLIGATORIO para importar prompts |
| `CumpleBooth/scripts/parity-check.php` | `<PRIVATE_APP>/scripts/parity-check.php` | OBLIGATORIO |
| `CumpleBooth/scripts/export-db-to-json.php` | `<PRIVATE_APP>/scripts/export-db-to-json.php` | OBLIGATORIO |
| `CumpleBooth/scripts/rollback.php` | `<PRIVATE_APP>/scripts/rollback.php` | OBLIGATORIO |
| `CumpleBooth/scripts/retention.php` | `<PRIVATE_APP>/scripts/retention.php` | OBLIGATORIO |
| `CumpleBooth/config/cumpleclick.example.php` | `<PRIVATE_APP>/config/cumpleclick.example.php` | OPCIONAL, plantilla |

Crear en PROD una configuración real fuera del webroot y apuntarla con
`CUMPLECLICK_CONFIG_FILE`. No copiar la configuración local.

## Orden 2 — webroot, OBLIGATORIO

| Ruta local | Destino PROD relativo | Clase |
|---|---|---|
| `CumpleBooth/dist/.htaccess` | `/public_html/cumpleclick/.htaccess` | OBLIGATORIO |
| `CumpleBooth/dist/.user.ini` | `/public_html/cumpleclick/.user.ini` | OBLIGATORIO |
| `CumpleBooth/dist/index.html` | `/public_html/cumpleclick/index.html` | OBLIGATORIO |
| `CumpleBooth/dist/api.php` | `/public_html/cumpleclick/api.php` | OBLIGATORIO |
| `CumpleBooth/dist/lib.php` | `/public_html/cumpleclick/lib.php` | OBLIGATORIO |
| `CumpleBooth/dist/upload.php` | `/public_html/cumpleclick/upload.php` | OBLIGATORIO |
| `CumpleBooth/dist/ver.php` | `/public_html/cumpleclick/ver.php` | OBLIGATORIO |
| `CumpleBooth/dist/galeria.php` | `/public_html/cumpleclick/galeria.php` | OBLIGATORIO |
| `CumpleBooth/dist/admin/index.php` | `/public_html/cumpleclick/admin/index.php` | OBLIGATORIO |
| `CumpleBooth/dist/admin/config.php` | `/public_html/cumpleclick/admin/config.php` | OBLIGATORIO |
| `CumpleBooth/dist/admin/_style.css.php` | `/public_html/cumpleclick/admin/_style.css.php` | OBLIGATORIO |
| `CumpleBooth/dist/data/.htaccess` | `/public_html/cumpleclick/data/.htaccess` | OBLIGATORIO |
| `CumpleBooth/dist/data/themes.json` | `/public_html/cumpleclick/data/themes.json` | OBLIGATORIO |
| `CumpleBooth/dist/data/parties.json` | `/public_html/cumpleclick/data/parties.json` | OBLIGATORIO, snapshot inicial sin PIN |
| `CumpleBooth/dist/brand/at-logo.svg` | `/public_html/cumpleclick/brand/at-logo.svg` | OBLIGATORIO |
| `CumpleBooth/dist/assets/index-BXrjMzs5.js` | `/public_html/cumpleclick/assets/index-BXrjMzs5.js` | OBLIGATORIO |
| `CumpleBooth/dist/assets/index-DGDiPOXD.css` | `/public_html/cumpleclick/assets/index-DGDiPOXD.css` | OBLIGATORIO |
| `CumpleBooth/dist/assets/three.module-Y-ql4QRg.js` | `/public_html/cumpleclick/assets/three.module-Y-ql4QRg.js` | OBLIGATORIO para transición 3D |
| `CumpleBooth/dist/assets/baloo-2-latin-600-normal-tIfxVoAe.woff2` | `/public_html/cumpleclick/assets/baloo-2-latin-600-normal-tIfxVoAe.woff2` | OBLIGATORIO |
| `CumpleBooth/dist/assets/baloo-2-latin-700-normal-CqTg7A15.woff2` | `/public_html/cumpleclick/assets/baloo-2-latin-700-normal-CqTg7A15.woff2` | OBLIGATORIO |
| `CumpleBooth/dist/assets/baloo-2-latin-800-normal-BbF3Etk1.woff2` | `/public_html/cumpleclick/assets/baloo-2-latin-800-normal-BbF3Etk1.woff2` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/fondo-banner.jpg` | `/public_html/cumpleclick/themes/carreras/fondo-banner.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/fondo-sala.jpg` | `/public_html/cumpleclick/themes/carreras/fondo-sala.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/musica-fondo.mp3` | `/public_html/cumpleclick/themes/carreras/musica-fondo.mp3` | OBLIGATORIO |
| `CumpleBooth/dist/welcome-car.mp4` | `/public_html/cumpleclick/welcome-car.mp4` | OBLIGATORIO, saludo alternado base |
| `CumpleBooth/dist/themes/carreras/saludo-rayo-mcqueen-v3.mp4` | `/public_html/cumpleclick/themes/carreras/saludo-rayo-mcqueen-v3.mp4` | OBLIGATORIO, segundo saludo alternado |
| `CumpleBooth/dist/themes/carreras/despedida-carreras.mp4` | `/public_html/cumpleclick/themes/carreras/despedida-carreras.mp4` | OBLIGATORIO — nuevo 2026-08-01, carreras no tenía despedida configurada, narración "voz Alice" |
| `CumpleBooth/dist/themes/carreras/cruz.jpg` | `/public_html/cumpleclick/themes/carreras/cruz.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/el-rey.jpg` | `/public_html/cumpleclick/themes/carreras/el-rey.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/luigi.jpg` | `/public_html/cumpleclick/themes/carreras/luigi.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/mate.jpg` | `/public_html/cumpleclick/themes/carreras/mate.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/rayo-mcqueen.jpg` | `/public_html/cumpleclick/themes/carreras/rayo-mcqueen.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/sally.jpg` | `/public_html/cumpleclick/themes/carreras/sally.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/carreras/cruz-cut.png` | `/public_html/cumpleclick/themes/carreras/cruz-cut.png` | OBLIGATORIO para composición actual |
| `CumpleBooth/dist/themes/carreras/el-rey-cut.png` | `/public_html/cumpleclick/themes/carreras/el-rey-cut.png` | OBLIGATORIO para composición actual |
| `CumpleBooth/dist/themes/carreras/luigi-cut.png` | `/public_html/cumpleclick/themes/carreras/luigi-cut.png` | OBLIGATORIO para composición actual |
| `CumpleBooth/dist/themes/carreras/rayo-mcqueen-cut.png` | `/public_html/cumpleclick/themes/carreras/rayo-mcqueen-cut.png` | OBLIGATORIO para composición actual |
| `CumpleBooth/dist/themes/carreras/sally-cut.png` | `/public_html/cumpleclick/themes/carreras/sally-cut.png` | OBLIGATORIO para composición actual |
| `CumpleBooth/dist/themes/carreras/roulette/roulette-background-v1.png` | `/public_html/cumpleclick/themes/carreras/roulette/roulette-background-v1.png` | OBLIGATORIO — nuevo 2026-08-01, foto de fondo de la ruleta |

## OPCIONAL, no runtime

`dist/audio/AUDIO_OPCIONAL.txt`, `dist/videos/PON_AQUI_LOS_VIDEOS.txt`,
`dist/images/IMAGENES_REQUERIDAS.md` y `dist/themes/carreras/_NOTA.txt` pueden
omitirse de PROD.

`dist/audio/nota.mp3` y `dist/audio/error.mp3` (2026-07-27/28, sonidos de
acierto/error al atrapar en el juego de copos, TODAS las temáticas) son
igual de opcionales que `captura.mp3`/`confetti.mp3` — recomendado subirlos
porque mejoran la experiencia, pero el juego funciona igual sin ellos.

## NO SUBIR

- `CumpleBooth/config/cumpleclick.local.php` ni ninguna credencial/HMAC/password.
- `C:\wamp64\cumpleclick-private\` (fotos, state y backups locales).
- `node_modules/`, `src/`, `tests/`, `.git/`, `graphify-out/`, evidencias QA.
- Dumps SQL, snapshots, ZIP temporales o fotos de invitados.

Después del orden 1 ejecutar migrate → import dry-run → import apply → parity;
después del orden 2 ejecutar el gate HTTP/Chrome de `DEPLOY.md`. Este manifiesto
describe archivos locales: **no prueba ni afirma un despliegue a PROD**.

## AT-CUMPLECLICK-007 — Familia Canina (agregar al Orden 2)

Subir la carpeta completa solo después del gate HTTP/Chrome local. Todos estos
archivos son `OBLIGATORIO` para que el tema funcione de punta a punta:

| Ruta local | Destino PROD relativo | Clase |
|---|---|---|
| `CumpleBooth/dist/themes/familia-canina/fondo-banner.jpg` | `/public_html/cumpleclick/themes/familia-canina/fondo-banner.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/familia-canina/fondo-sala.jpg` | `/public_html/cumpleclick/themes/familia-canina/fondo-sala.jpg` | OBLIGATORIO |
| `CumpleBooth/dist/themes/familia-canina/musica-fondo.mp3` | `/public_html/cumpleclick/themes/familia-canina/musica-fondo.mp3` | OBLIGATORIO |
| `CumpleBooth/dist/themes/familia-canina/grupo-personajes.png` | `/public_html/cumpleclick/themes/familia-canina/grupo-personajes.png` | OBLIGATORIO |
| `CumpleBooth/dist/themes/familia-canina/welcome-familia-canina.mp4` | `/public_html/cumpleclick/themes/familia-canina/welcome-familia-canina.mp4` | OBLIGATORIO |
| `CumpleBooth/dist/themes/familia-canina/despedida-familia-canina.mp4` | `/public_html/cumpleclick/themes/familia-canina/despedida-familia-canina.mp4` | OBLIGATORIO — nuevo 2026-08-01, narración "voz Alice" |
| `CumpleBooth/dist/themes/familia-canina/transicion-sesion-fotos.mp4` | `/public_html/cumpleclick/themes/familia-canina/transicion-sesion-fotos.mp4` | OBLIGATORIO |
| `CumpleBooth/dist/themes/familia-canina/transicion-alfombra-base-v1.png` | `/public_html/cumpleclick/themes/familia-canina/transicion-alfombra-base-v1.png` | OBLIGATORIO |
| `CumpleBooth/dist/themes/familia-canina/{azulita,chispa,papa-marino,mama-coral,muffin,chloe}.jpg` | `/public_html/cumpleclick/themes/familia-canina/` | OBLIGATORIO, 6 archivos |
| `CumpleBooth/dist/themes/familia-canina/{azulita,chispa,papa-marino,mama-coral,muffin,chloe}-cut.png` | `/public_html/cumpleclick/themes/familia-canina/` | OBLIGATORIO, 6 archivos |
| `CumpleBooth/dist/themes/familia-canina/saludo-{azulita,chispa,papa-marino,mama-coral,muffin,chloe}.mp4` | `/public_html/cumpleclick/themes/familia-canina/` | OBLIGATORIO, 6 archivos |
| `CumpleBooth/dist/themes/familia-canina/invitation/` | `/public_html/cumpleclick/themes/familia-canina/invitation/` | OBLIGATORIO, carpeta completa |
| `CumpleBooth/dist/themes/familia-canina/roulette/roulette-background-v1.png` | `/public_html/cumpleclick/themes/familia-canina/roulette/roulette-background-v1.png` | OBLIGATORIO |
| `CumpleBooth/dist/themes/familia-canina/visual-manifest.v1.json` | `/public_html/cumpleclick/themes/familia-canina/visual-manifest.v1.json` | OPCIONAL, trazabilidad |

No subir `storage/`, `.uv-cache/`, modelos de recorte, candidatos, frames de QA,
los scripts Python de construcción ni la fiesta local `DEMO-BLUEY` como si
fuera información de producción.

## DESPLEGADO 2026-09-06 en cumpleclick.com/app — juego 3D "Tu Cumple en 3D" + salas + botón en el kiosco + PIN 1234

Rama `feat/kiosco-juego-3d-prod` (esta), creada desde `369da38` = lo que corría en PROD (bundle reproducido byte a byte antes
del cambio). Subido por SSH: `index.html` + `assets/main-7reZvA3S.js` + `assets/main-CUtameO5.css` (botón "🎮 Aventura 3D" en
la bienvenida de temáticas `hielo`/`heroes`/`spidey` → `juego/?p=<slug>&kiosco=1`), `sala.php` + `lib.sala.php` (de la rama
`feat/cumpleclick-sala-ayudantes`), migración 014 en `database/migrations/` aplicada con `database/aplicar-014.php`, el juego
completo en `app/juego/` (repo `tucumple-repo`, 181 archivos, `.htaccess` propio), PIN 1234 en todas las fiestas
(`database/pin-1234.php`, respaldo JSON) y fiestas reales del 13-sep renombradas (`database/renombrar-slugs.php`):
`isidora-reino-de-hielo`, `luciano-spidey`. Detalle completo en la misma sección del manifiesto de la rama
`feat/cumpleclick-sala-ayudantes` y en `Docs/ORCHESTRATION/CONEXIONES-Y-CREDENCIALES.md` §3.3.

## DESPLEGADO 2026-09-06 (tarde) en cumpleclick.com/app — pantalla Mensajes y correo en la marca

Subido por SSH desde esta rama, con los md5 de PROD verificados antes: coincidían byte a byte
con la base de la rama (PROD guarda los PHP con CRLF; las ediciones se rehicieron en CRLF para
no convertir el archivo entero en un diff).

| Local (`dist/`) | PROD (`/app/`) | Nota |
|---|---|---|
| `assets/album-C3C8CAdS.js`, `assets/cartel-QEaLTDPn.js` | `/assets/` | primero: los HTML nuevos los piden |
| `album.html`, `cartel-qr.html` | `/` | apuntan a los bundles nuevos |
| `album-api.php` | `/album-api.php` | publica `correo`/`correo_url` de la marca |
| `admin/marca.php` | `/admin/marca.php` | campos Correo y Enlace del correo |
| `admin/mensajes.php` | `/admin/mensajes.php` | **nuevo**: textos para el cliente con copiar / abrir WhatsApp |
| `admin/index.php` | `/admin/index.php` | pestaña Mensajes en el nav |
| (dato) `marca-correo.php` | BD/JSON | agregó `correo` a `data/marca.json` con respaldo (`marca.json.bak-20260906-223017`); el archivo NO se subió para no pisar lo editado desde el admin |

Verificado: 173/173 tests, paridad 485 archivos, y por HTTP `admin/mensajes.php` responde con la
pantalla de login, `album.html` y `cartel-qr.html` sirven los bundles nuevos.

## DESPLEGADO 2026-09-06 (noche) en cumpleclick.com/app — aceptación de Términos y firma

Portado desde `feat/cumpleclick-aceptacion-terminos` a esta línea (ver el commit): de aquel
commit solo se copiaron los archivos nuevos; `lib.php` y `admin/index.php` se parchearon a mano
porque los de esa rama son del build del 27-jul.

Orden real de subida (config → migración → librerías → páginas → admin al final):

| # | Local | PROD | Nota |
|---|---|---|---|
| 1 | (script) `config-terminos.php` | `domains/cumpleclick.com/` | agregó `acceptance_dir`, `notify_email`, `mail_from` a `cumpleclick-config.php` insertando texto antes del cierre del array (los secretos existentes no se leen ni se reescriben); respaldo `cumpleclick-config.php.bak-20260907-003647`; creó `almacen/aceptaciones` (0770) |
| 2 | `dist/lib.php` | `/lib.php` | **con LF**: PROD guarda este archivo en LF y el admin en CRLF; subirlo en CRLF habría cambiado 2.471 finales de línea |
| 3 | `dist/lib.acceptance.php` | `/lib.acceptance.php` | nuevo |
| 4 | `dist/legal/*.md` (3) | `/legal/` | carpeta creada en el servidor; sin ellos `aceptar-plan.php` falla cerrado |
| 5 | `dist/aceptar-plan.php`, `dist/comprobante-aceptacion.php` | `/` | nuevos |
| 6 | `dist/admin/aceptaciones.php` | `/admin/` | nuevo |
| 7 | `database/migrations/013_plan_acceptances(.down).php` + `aplicar-013.php` | `domains/cumpleclick.com/database/` | migración aplicada con runner puntual; **las 10 fiestas activas quedaron `waived`** y ninguna dejó de funcionar |
| 8 | `dist/admin/index.php` | `/admin/index.php` | **último**: activa el cierre del plan y agrega el botón Aceptación |

Gate posterior, todo verificado por HTTP: `aceptar-plan.php?t=x` → 400; token de 32 hex inexistente
→ 404; `legal/terminos-y-condiciones.md` → 200; `admin/aceptaciones.php` → login; `api.php` del
kiosco intacto. Y una firma real de punta a punta sobre `demo-carreras`: comprobante de 64 KB con
la firma embebida y su SHA-256, evidencia en `almacen/aceptaciones/`, y los dos correos enviados
por SMTP (`client_mail_sent_at` e `internal_mail_sent_at`). La aceptación de prueba, su evidencia
y el script se borraron después.

**Pendiente de Luis:** los tres textos legales siguen siendo borradores y llevan visible el aviso
de que no son asesoría legal; hay que pasarlos por abogado antes de usarlos con clientes reales.

## DESPLEGADO 2026-09-07 en cumpleclick.com/app — contactos de quien contrata y cobro

| # | Local | PROD | Nota |
|---|---|---|---|
| 1 | `database/migrations/015_party_contacts_billing(.down).php` + `aplicar-015.php` | `domains/cumpleclick.com/database/` | crea `cc_party_contacts` y las 5 columnas de cobro en `cc_parties`; aditiva, ninguna fiesta cambió |
| 2 | `dist/lib.php` | `/lib.php` | **en LF** (PROD guarda este archivo así); solo suma el `require` de la librería nueva |
| 3 | `dist/lib.cliente.php` | `/lib.cliente.php` | nuevo: contactos y cobro |
| 4 | `dist/admin/_style.css.php` | `/admin/_style.css.php` | grillas de contactos y cobro |
| 5 | `dist/admin/index.php` | `/admin/index.php` | **último**: las dos secciones en la ficha de la fiesta |

Verificado en PROD: las cuatro funciones nuevas responden, el admin y el kiosco siguen sirviendo.
Probado antes en local de punta a punta con el formulario real: dos contactos (uno de ellos
"familiar" marcado como principal) y el cobro con descuento ($99.990 − $30.000 = $69.990,
anticipo $20.000, saldo $49.990).

**Dos fallos preexistentes corregidos de paso** (los dos impedían guardar una fiesta desde el
admin y ninguno decía por qué): el calibrador del marco rechazaba las fiestas calibradas
arrastrando, y la validación del PIN exigía volver a escribirlo al editar una fiesta que ya
tenía galería.

## DESPLEGADO 2026-09-07 en cumpleclick.com/app — carteles QR por fiesta

Pantalla nueva para imprimir los avisos con código QR de cada fiesta. Se entra desde el admin,
con el botón **Carteles QR** de la fiesta, o directo en `/app/carteles.html?p=<slug>`.

| # | Local | PROD | Nota |
|---|---|---|---|
| 1 | `public/admin/carteles-api.php` | `/admin/carteles-api.php` | nuevo; exige sesión de admin porque el cartel de la galería lleva el PIN |
| 2 | `dist/carteles.html` | `/carteles.html` | nueva entrada del build |
| 3 | `dist/assets/carteles-OE5qJLX0.js` | `/assets/` | |
| 4 | `dist/assets/carteles-BcTaWotC.css` | `/assets/` | |
| 5 | `dist/assets/client-eulB1LW-.js` | `/assets/` | chunk compartido de React que PROD todavía no tenía |
| 6 | `public/admin/index.php` | `/admin/index.php` | **último**; en CRLF (así lo guarda PROD). Solo agrega el botón por fiesta |

Qué carteles arma, según lo que tenga ESA fiesta: **galería** (con el PIN en grande), **juego 3D**
de su temática (Hielo, Spidey o Héroes), **invitación** si ya hay una emitida, y **Álbum Recuerdo**.

El del Álbum es distinto: su QR lleva el token de **aportes**, que se emite de a uno y en base solo
queda su huella. Por eso no se arma al abrir la pantalla —cada visita revocaría el anterior y
dejaría muertos los carteles ya impresos—: hay un botón **«Generar el QR del Álbum»** que lo pide
por POST con el CSRF del admin, avisa que el anterior queda revocado, y recién ahí aparece el
cartel. Si la fiesta no tiene álbum, o los aportes están cerrados, el panel lo dice y enlaza a
`admin/album.php`.

La hoja sale a **escala real** (`@page` en milímetros) para entrar justa en el soporte: A6, foto
10×15, foto 13×18, cuadrado 15×15, A5, marco 20×25, A4 y una **medida a pedido** en mm. Todo el
diseño se mide en `cqh` (altura de la hoja), así que el mismo cartel funciona en A6 y en A4 sin
rehacerlo: el QR va de 31 mm a 62 mm. Al imprimir hay que dejar los márgenes en «ninguno» y
desactivar «ajustar al papel».

**Dos estilos, los dos imprimibles** (se eligen en la misma pantalla):

- **Fondo completo (marco de agua):** el banner de la temática ocupa la hoja entera y **no hay
  recuadro**: los textos van en blanco directamente sobre la foto, con doble sombra (una difusa
  que los despega y una pegada al borde que los sostiene sobre los fondos claros, como el
  ventanal nevado de Hielo). El fondo se ancla abajo para que los personajes suban en la hoja.
- **Cabecera con la temática:** franja de 30cqh arriba con la foto y el resto en blanco. La franja
  es alta a propósito y va a `object-position: center 48%`: con una franja más baja, o con otro
  encuadre, a los personajes les quedaba cortada la cabeza —lo mismo que Luis marcó en la
  cabecera del correo—. Con estos valores salen enteros en Hielo y en Spidey.

En los dos, el QR va sobre un recuadro **blanco opaco**: un QR con la foto asomando detrás deja de
escanear. En el pie va el isotipo `brand/cumpleclick-mark.svg` junto al nombre, el sitio y el
Instagram (misma convención que la galería y el álbum: el SVG dibuja solo el globo, la palabra
"CumpleClick" es texto).

Verificado en PROD: los cuatro archivos responden 200, `admin/carteles-api.php` responde 401 sin
sesión, y los enlaces que arma el servidor son los de `https://cumpleclick.com/app` para las dos
fiestas del 13-sep (galería y juego 3D, con banner de temática en disco).

**Pendiente:** Luis va a comprar los soportes acrílicos; cuando dé las medidas se agregan como
tamaños fijos en la lista (hoy se cargan a mano en «A medida…»).

### Álbum Recuerdo: aportes abiertos en las dos fiestas (2026-09-07)

Para que el cartel «Suma tus fotos» sirva, el álbum de la fiesta tiene que estar recibiendo. Se
abrió en las dos fiestas del 13-sep con un script puntual (respaldo previo en
`domains/cumpleclick.com/respaldo-album-*.json`):

| Fiesta | Antes | Ahora |
|---|---|---|
| `isidora-reino-de-hielo` | álbum `published`, `intake_enabled=0` | `collecting`, aportes abiertos, videos sí, cierra 2026-09-20 23:59 |
| `luciano-spidey` | sin álbum | álbum creado (id 18), `collecting`, aportes abiertos, videos sí, misma fecha de cierre |

**El álbum de Isidora estaba publicado con material de prueba** (14 registros no eliminados: fotos
de cabina de julio/agosto con nombres de otras fiestas y 10 archivos `rescate-*` del 31-ago). Se
marcaron como `removed` —el mismo borrado que hace el botón del admin, reversible desde el filtro
«Eliminados»— y la portada quedó en nulo. Respaldo de las filas en
`respaldo-media-isidora-*.json`. Volver a `collecting` deja el enlace de VISTA del álbum en 404
hasta que se publique de nuevo después de la fiesta; es el ciclo normal.

La pantalla de carteles ahora avisa **cuándo se emitió el enlace de aportes activo** (en base solo
queda su huella, así que solo se puede saber la fecha) y, al generar uno nuevo, muestra el enlace
con botones de **copiar** y **enviar por WhatsApp**: el mismo que lleva el QR, para quien no esté
en la fiesta.

### La URL escrita bajo el QR y la medida «media carta» (2026-09-07)

Cada cartel lleva ahora **la dirección completa impresa dentro del recuadro blanco, bajo el QR**:
si la cámara no toma el código, o el celular es viejo, se puede tipear. Va adentro del recuadro a
propósito, para que quede en tinta oscura sobre blanco también en el estilo de fondo completo.

Se agregó el tamaño **Media carta · 14 × 21,6 cm**, que es el de los porta menú de acrílico
comunes. **No es A5**: son 8 mm más angosta y 6 mm más alta, así que imprimir un A5 en ese soporte
deja el papel sobrando por los lados.

`scripts/carteles-prod-pdf.mjs` arma el PDF de una fiesta real con los enlaces de producción sin
tener que emitir otro token del Álbum: levanta la misma pantalla del admin e intercepta la
respuesta de la API para reemplazar la lista de carteles. Se usa cuando el enlace de aportes ya
está vivo y compartido, porque volver a generarlo lo revocaría.

### Fondo de la temática en la página de aportes y cartel de marca (2026-09-07)

- `_album-intake.css.php` (**CRLF**, así lo guarda PROD; respaldo en `.bak-20260907`): el banner
  de la temática pasó de una franja al 22% arriba —que prácticamente no se veía— a **pantalla
  completa al 78%**, con un velo en degradado encima y los paneles con `backdrop-filter` para que
  el texto siga legible sobre las temáticas claras.
- La pantalla de carteles arranca en **Media carta**, la medida del porta menú de acrílico.
- Cartel nuevo **«¿Lo quieres en tu fiesta?»**: QR a nuestro Instagram (o al sitio, si no hay
  Instagram cargado en `data/marca.json`), con la temática de la fiesta de fondo. Aparece en toda
  fiesta y sirve para dejar uno en la mesa como aviso institucional.

## DESPLEGADO 2026-09-07 en cumpleclick.com/app — comprobante de pago en PDF

Documento con lo cobrado y lo pagado, que se manda por correo con el PDF adjunto y se comparte
por WhatsApp con un enlace. **No es boleta del SII y el propio PDF lo dice**: la integración
tributaria es la etapa siguiente, como quedó acordado.

| # | Local | PROD | Nota |
|---|---|---|---|
| 1 | `public/brand/pdf-cumpleclick.jpg`, `pdf-automatizatech.jpg` | `/brand/` | los dos logos, rasterizados sobre blanco |
| 2 | `public/lib.pdf.php` | `/lib.pdf.php` | nuevo: escritor de PDF |
| 3 | `public/lib.comprobante.php` | `/lib.comprobante.php` | nuevo: datos, maqueta, enlace firmado y correo |
| 4 | `public/lib.mail.php` | `/lib.mail.php` | **en LF** (PROD lo guarda así); suma adjuntos |
| 5 | `public/comprobante.php` | `/comprobante.php` | nuevo: descarga por enlace firmado |
| 6 | `public/admin/comprobante.php` | `/admin/comprobante.php` | nueva pantalla |
| 7 | `public/admin/mensajes.php` | `/admin/mensajes.php` | **CRLF**; solo la pestaña nueva |
| 8 | `public/admin/index.php` | `/admin/index.php` | **CRLF**, último; pestaña y botón por fiesta |

**Por qué un escritor de PDF propio.** El proyecto no tiene librería y el hosting no deja
instalar una. Los otros PDF (manual, términos) se arman con el navegador en el computador de
Luis, pero este tiene que generarse EN EL SERVIDOR al momento de mandar el correo.
`lib.pdf.php` cubre justo lo que el documento necesita: Helvetica y Helvetica-Bold (de las 14
estándar, no hay que incrustar la fuente), texto en WinAnsi con `iconv` para que salgan los
acentos y la eñe, JPEG embebido tal cual con `DCTDecode` (un PNG obligaría a re-comprimir a
mano), líneas y rectángulos. El flujo va comprimido con `zlib`, que el servidor tiene.

**El PDF no se guarda en disco**: se arma en cada visita, así que siempre refleja lo que dice la
ficha hoy. El enlace público va firmado con `cb_hmac()` (24 caracteres en la URL) porque el
documento es el mismo siempre y no tiene sentido un token de un solo uso; sin firma, cambiar el
slug en la URL mostraría el cobro de otra fiesta.

Verificado en PROD: `php -l` limpio en los siete archivos, el PDF se genera en el servidor
(62 KB, los dos logos, comprimido, EOF correcto) y se revisó el archivo bajado —los acentos,
la eñe y los signos de apertura salen bien—; el enlace con firma inválida responde 403 y sin
firma 400; `cc_mail_enabled()` es `true`. Pruebas: `tests/backend/comprobante.php` (23
comprobaciones) y `tests/backend/cliente.php` (30) pasan.

**Pendiente de Luis:** confirmar que el RUT que sale en el comprobante (78.363.717-0, de
`cb_comprobante_emisor()` en `lib.comprobante.php`) es el correcto para facturar.

### Clases del admin sin CSS detrás (2026-09-07)

Luis vio pantallas del admin con elementos "en HTML puro". La causa: nombres de clase que no
existen en `admin/_style.css.php`, así que el navegador no aplicaba nada. Se revisaron las siete
pantallas comparando las clases usadas contra las definidas.

| Pantalla | Clase | Qué pasaba | Arreglo |
|---|---|---|---|
| `admin/comprobante.php` | `inline` (×3) | la clase real es `inline-form`; la barra superior y la fila de botones se apilaban | `inline-form` y una `cmp-acciones` propia para la fila |
| `admin/mensajes.php` | `head` | la cabecera del admin es `topbar`; se veía sin maquetar | `topbar` |
| `admin/marca.php` | `lede` | el párrafo de entrada quedaba como texto plano | `muted`, que ya existe |
| `admin/album.php` | `badge--video` | la variante nunca se definió: el badge salía con el estilo base y sin color | definida en `_style.css.php` con `--primary-soft` / `--primary-dark` |

Los cuatro archivos van **en CRLF**, que es como PROD los guarda; se verificó que el md5 de cada
uno coincidía con su base convertida a CRLF antes de subir, para no ensuciar el diff.
`btn-label` y `frame-value` en `index.php` quedaron como están: no son estilos, son enganches
que usa el JavaScript de la pantalla.

## Revision completa antes del domingo 13 (2026-09-07)

Se corrio todo lo que existe y se sumo un smoke de produccion reutilizable,
`tests/smoke-prod.sh`, que se puede correr despues de cada despliegue.

| Que se probo | Resultado |
|---|---|
| Pruebas backend (11 archivos) | todas pasan: 46 aceptacion, 157 album, 30 cliente, 23 comprobante, 32 perfiles, 39 fuente, 45 leads, 8 entrypoints, 28 predicciones, 163 backend general |
| Pruebas frontend (`npm test`) | 173 de 173 |
| `php -l` de todo lo publicado en PROD | 49 archivos, 0 con error |
| Smoke HTTP de PROD | 34 comprobaciones, 0 fuera de lo esperado |
| Integridad de datos en PROD | 30 revisiones; las dos fiestas activas, con PIN, banner, cabecera de correo, album abierto y enlace de aportes vigente |
| Correo | los 6 correos del flujo enviados a una bandeja real, con sus adjuntos |

**Tres comprobaciones del smoke fallaban por una expectativa mia equivocada, no por un
defecto** (quedaron corregidas y documentadas dentro del script):

- `admin/marca.php` devuelve 302 hacia el ingreso en vez de mostrarlo; no filtra nada.
- La API de carteles corta con 401 antes de mirar el CSRF cuando no hay sesion. Es el orden
  correcto.
- `comprobante.php` valida la firma ANTES de mirar si la fiesta existe, asi que una fiesta
  inventada con firma mala da 403 y no 400: no filtra que fiestas hay.

### Hallazgo: el registro de migraciones de PROD no refleja la realidad

`cc_schema_migrations` en PROD lista 14 versiones y **faltan tres que si estan aplicadas de
hecho**: `012_lead_mail_tracking` (las columnas `confirmation_sent_at`, `notified_at` y
`mail_error` estan en `cc_leads`), `013_narration_intro_output` (el enum de
`cc_invitation_outputs` ya trae `personalized_narration_intro`) y `014_rsvp` (la tabla
`cc_rsvps` existe). Ademas PROD tiene `014_salas_ayudantes`, que viene de la otra rama y no
esta en esta linea de codigo: los numeros 013 y 014 chocaron entre ramas.

**Hoy no rompe nada.** El riesgo es a futuro: un runner de migraciones intentaria aplicarlas de
nuevo y `012` fallaria por columna duplicada, dejando el proceso a medias. Lo prolijo es
registrar esas tres versiones como ya aplicadas (un INSERT en `cc_schema_migrations`, sin tocar
ninguna tabla de datos). Queda pendiente de decision de Luis por ser una escritura en PROD.

## DESPLEGADO 2026-09-07 (tarde) — correos de Terminos con la marca y registro de migraciones

**1. Los dos correos de la aceptacion salian en texto pelado.** El cliente recibia un mensaje
sin logo, con un SHA-256 en medio; desentonaba con el resto de los correos, que ya usaban la
plantilla de la marca. Ahora los dos van con `cc_mail_shell`: filas de datos, boton para
descargar el comprobante firmado, y la huella al final en letra chica (es respaldo legal, no
lo que la persona vino a leer). La fecha del evento se muestra como se escribe en Chile.
El texto plano se mantiene como alternativa del correo y como respaldo del envio por `mail()`.

| Local | PROD | Nota |
|---|---|---|
| `public/lib.acceptance.php` | `/lib.acceptance.php` | **CRLF**; respaldo en `.bak-20260907` |

`cb_send_mail()` acepta ahora un cuarto parametro opcional con el HTML. Sin ese parametro se
comporta igual que antes, asi que ningun otro envio cambia.

**2. Registro de migraciones al dia.** Se anotaron en `cc_schema_migrations` las tres
versiones que estaban aplicadas de hecho pero sin registrar: `012_lead_mail_tracking`,
`013_narration_intro_output` y `014_rsvp`. El script **verifico el efecto de cada una en la
base antes de anotarla** (columnas de `cc_leads`, el enum de `cc_invitation_outputs` y la
tabla `cc_rsvps`): marcar como aplicada una migracion que falta seria esconder el problema.
El registro paso de 14 a 17 versiones y quedo respaldado en
`~/respaldo-migraciones-20260907.txt`. No se toco ninguna tabla de datos.

Verificado despues del despliegue: `php -l` limpio, md5 del archivo igual al local, smoke de
PROD 34/34, pruebas de aceptacion 46/46 y backend general 163/163, y los dos correos
reenviados a una bandeja real con el formato nuevo.

## DESPLEGADO 2026-09-07 (noche) — descuento en porcentaje por fiesta

El descuento solo se podia escribir en pesos, y en la practica se piensa al reves: "a esta le
hago 20%", "esta va sin costo". Calcularlo a mano es donde aparecen los errores de monto en un
comprobante ya impreso.

| # | Local | PROD | Nota |
|---|---|---|---|
| 1 | `database/migrations/016_discount_percent(.down).php` + `cc-aplicar-016.php` | `domains/cumpleclick.com/database/` | agrega `discount_percent DECIMAL(5,2) NULL` a `cc_parties`; aditiva |
| 2 | `public/lib.cliente.php` | `/lib.cliente.php` | **en LF** (PROD lo guarda asi) |
| 3 | `public/lib.comprobante.php` | `/lib.comprobante.php` | **CRLF** |
| 4 | `public/admin/index.php` | `/admin/index.php` | **CRLF**, ultimo; el campo nuevo en la ficha |

**Como funciona.** En la ficha de la fiesta hay dos campos: *Descuento en %* y *Descuento en
pesos*. Si el porcentaje esta puesto, **manda**: el monto en pesos se calcula sobre el precio y
se guarda derivado, asi que el comprobante sigue leyendo un monto y los dos numeros no pueden
discrepar. Cambiar el precio recalcula el descuento solo. `DECIMAL(5,2)` y no float porque 12,5%
tiene que valer 12,5 exacto.

El porcentaje aparece en la etiqueta del comprobante ("Descuento · 100% · Fiesta de prueba") y
en el correo, que ahora muestra tambien el precio del plan y el descuento cuando lo hay, no solo
el total. `cb_parse_percent()` acepta "20", "20%", "12,5" y "12.5"; distingue vacio de 0%.

**Las dos fiestas del domingo quedaron en costo 0** (marcha blanca), con la nota "Fiesta de
marcha blanca: el servicio va sin costo." No se invento un precio: poner una cifra que no es la
real en un documento que ve el cliente es peor que un cero. Si se quiere mostrar el valor de lo
que se esta regalando, se escribe el precio real y 100 en el porcentaje, y el documento hace la
resta solo.

Verificado: migracion aplicada y registrada (18 versiones), `php -l` limpio en los tres
archivos, pruebas `cliente` ampliadas a 46 comprobaciones (16 nuevas de porcentaje: que manda
sobre el monto, que se recalcula al cambiar el precio, 100% = total cero, rechazo de >100 y de
porcentaje sin precio), suites `comprobante`, `acceptance`, `run`, `leads` y `album` sin
cambios, y smoke de PROD 34/34. Los comprobantes de las dos fiestas se generan (62 kB); les
falta solo cargar los contactos para poder enviarlos.

### El descuento se carga desde la ficha, no por script (2026-09-07)

Se probo el recorrido completo por la pantalla, escribiendo como lo hace una persona:
`admin/index.php?action=editar&slug=<fiesta>` -> seccion **Cobro del servicio** -> precio
120.000 y **25** en *Descuento en %* -> Guardar -> reabrir la ficha. Quedo guardado el 25%, el
monto derivado de 30.000 aparecio solo, y la pantalla muestra "Total con descuento: $90.000".
El mismo 25% sale despues en el PDF y en el correo.

**Un error propio corregido de paso:** el boton "Ir a la ficha de la fiesta" de la pantalla del
comprobante apuntaba a `index.php?edit=<slug>`, que no existe —el formulario se abre con
`?action=editar&slug=`—, asi que caia en la lista de fiestas sin abrir nada. Corregido y
desplegado (`admin/comprobante.php`).

## DESPLEGADO 2026-09-07 (noche) — catalogo de planes editable desde el admin

Los precios estaban **escritos a mano en el HTML del sitio** (`sitio/index.php`), en tres
tarjetas: cambiar uno obligaba a editar la pagina y volver a subirla. Ahora viven una sola vez
en `data/planes.json`, se editan en **Admin -> Planes**, y de ahi los leen el sitio publico y
el selector de la ficha de la fiesta.

| # | Local | PROD | Nota |
|---|---|---|---|
| 1 | `public/lib.planes.php` | `/lib.planes.php` | nuevo: lee, calcula y guarda el catalogo |
| 2 | `public/data/planes.json` | `/data/planes.json` | nuevo: los tres planes con sus precios reales |
| 3 | `public/admin/planes.php` | `/admin/planes.php` | nueva pantalla |
| 4 | `public/admin/index.php` | `/admin/index.php` | **CRLF**; pestaña Planes y selector de plan en la ficha |
| 5 | `sitio/index.php` | `public_html/index.php` | **en LF** (PROD lo guarda asi); respaldo en `.bak-20260907` |

**El precio con promocion no se guarda: se calcula** restandole el porcentaje al precio normal,
igual que el descuento por fiesta. Guardar los dos numeros es la forma segura de terminar con un
sitio que dice una cosa y un comprobante que dice otra. Con la promo de lanzamiento al 50%, el
catalogo reproduce exactamente los precios que ya mostraba la pagina: $34.995, $49.995 y $29.995.

**El sitio conserva un respaldo con los tres planes escritos.** Si `planes.json` falta o queda
roto, la landing sigue mostrando precios reales en vez de quedarse sin la seccion que decide la
venta. Es el mismo criterio que ya usaba con el numero de WhatsApp.

**En la ficha de la fiesta, el plan solo COPIA su precio.** Lo que se guarda es el numero, no el
plan: si mas adelante cambia el precio del catalogo, una fiesta ya acordada no cambia — seria
feo que un comprobante ya enviado mostrara otra cifra. El descuento por fiesta se aplica encima.

Los campos de presentacion de cada tarjeta (clase CSS, badge y emoji del boton de WhatsApp) no
se editan desde el admin porque son diseno, pero se conservan al guardar: si el formulario
reconstruyera el plan solo con lo que manda, cada guardado borraria el diseno.

Verificado de punta a punta: se cambio el precio del Premium desde la pantalla, se guardo, y el
sitio paso a mostrar $109.990 tachado con $54.995 — despues se restauro el valor real. El
selector de la ficha llena el precio ($49.995 al elegir Premium). En PROD: `php -l` limpio en
los cuatro PHP, la home responde 200 con los precios correctos y su estructura intacta (9
enlaces de WhatsApp, todas las secciones), `admin/planes.php` pide contrasena, y
`data/planes.json` **no es accesible por web (403)**. Pruebas backend y smoke 34/34 sin cambios.

## DESPLEGADO 2026-09-07 (cierre) — revision responsiva del admin

Luis edita a veces desde el telefono, asi que se midio cada pantalla del admin a 375 px
buscando desbordes y controles imposibles de tocar. Cuatro defectos reales, todos en
`admin/_style.css.php` (**CRLF**) salvo donde se indica:

| Que pasaba | Donde se veia | Arreglo |
|---|---|---|
| La barra de botones se salia de la pantalla en movil | Mensajes (3 botones al 100% en una sola fila) | `.inline-form` con `flex-wrap: wrap` dentro del bloque de 480 px |
| Los campos quedaban en 21 px de alto, sin estilo | Planes y el selector de fiesta del Comprobante | usar la clase `field`, que es la convencion del admin (`admin/planes.php`, `admin/comprobante.php`) |
| Los cuatro numeros del calibrador de marco, en 20 px y sin borde | Ficha de la fiesta | regla propia para `input[type="number"].frame-value`, 44 px |
| **La fila de contactos se salia del recuadro** | Ficha de la fiesta, en escritorio | `minmax(0, ...)` en la grilla y `min-width: 0` en los inputs |
| El selector de plan sin estilo: etiqueta, menu y explicacion en una linea revuelta | Ficha de la fiesta | `.cobro-plan`, que no existia |

**Por que se salia la fila de contactos:** `1fr` es `minmax(auto, 1fr)`, asi que la columna no
puede achicarse por debajo del ancho minimo del input. Cuatro campos mas el radio "Principal"
empujaban la grilla fuera del fieldset. Es el mismo error que suele confundirse con "falta
responsive": no era el ancho de la pantalla, era la grilla.

Verificado a 375 px despues del arreglo: las **nueve** pantallas del admin dan 375 px de ancho
de documento, cero elementos fuera y cero controles bajo 40 px (salvo el boton "+ Agregar
contacto", que es secundario y mide 36). En escritorio, la fila de contactos termina en 661 px
dentro de un recuadro que llega a 675. Las paginas publicas (home, galeria, subir fotos, admin,
carteles) declaran todas su `viewport`.

## 2026-09-07 (cierre) — campos crudos y la fiesta de Frozen que no era

### Ningun campo se queda con el estilo del navegador

La regla `.field` solo cubria `text` y `date`, asi que los campos de **correo, telefono, numero,
hora, clave y URL** salian cuadrados y de 21 px al lado de los demas: es lo que se ve como "HTML
puro". Se extendio la regla a todos los tipos (`admin/_style.css.php`, **CRLF**).

Verificado recorriendo **14 pantallas y vistas** del admin y midiendo cada control: cero
elementos con esquinas rectas o bajo 34 px. La revision quedo hecha con la misma medicion que
detecto el problema, no a ojo.

### La fiesta de Frozen del 13-sep es la de Samantha, no la de Isidora

Al buscar las invitaciones aparecieron **tres** fiestas con fecha 2026-09-13:

| Fiesta | Creada | Invitacion | Estado |
|---|---|---|---|
| `samantha-hielo` (Samantha, Hielo) | 02-sep | #22 | la real |
| `luciano-spidey` (Luciano, Spidey) | 02-sep | #23 | la real |
| `isidora-reino-de-hielo` (Isidora, Hielo) | **27-jul** | #3 | vieja, con la misma fecha |

`isidora-reino-de-hielo` es de julio: quedo con la fecha del 13-sep y por eso se tomo como la
fiesta de Frozen en el trabajo anterior. Todo lo que se preparo para ella —album abierto, enlace
de aportes, cobro en cero, carteles— **corresponde en realidad a Samantha**.

Corregido: la fiesta de Samantha quedo con album creado (id 19), aportes abiertos hasta el
20-sep, enlace de aportes vigente y cobro en cero con la nota de marcha blanca. Se regeneraron
sus carteles y los de Luciano, ahora **con el cartel de la invitacion**, que antes no salia
porque ninguna de las dos fiestas que se estaban usando tenia una emitida.

**Pendiente de decision de Luis:** que hacer con `isidora-reino-de-hielo`. Sigue activa, con sus
enlaces vivos y su album abierto. No se toco: desactivar una fiesta es su decision, no la mia.

## 2026-09-07 — revision previa al domingo y dos hallazgos

### Isidora desactivada

`isidora-reino-de-hielo` (la fiesta de julio que quedo con fecha 13-sep) esta **desactivada**:
`active=0`, galeria cerrada, aportes cerrados y su enlace de aportes revocado. El kiosco
responde `{"ok":false,"error":"inactive"}` y su galeria devuelve 404. No se borro nada.

### Revision de las dos fiestas reales: 49 comprobaciones

Samantha y Luciano quedaron verificadas de punta a punta: activas, con la fecha correcta,
tematica con mundo 3D, **PIN de galeria 1234 comprobado de verdad** (no asumido), fondo y
cabecera de correo en disco, invitacion emitida y publicada, album con aportes abiertos y
enlace que sigue vivo el dia de la fiesta, precio cargado y comprobante que se genera (61 kB).

Falta una sola cosa, y es de Luis: **ninguna de las dos tiene contactos cargados**, asi que
todavia no se les puede mandar el correo.

Sobre el marco de la foto: Samantha usa el **default de la tematica** (`frame_box_json` en
nulo), igual que usaba Isidora; el kiosco recibe `x=0.3315 y=0.3948 w=0.3407 h=0.1995`. Luciano
tiene calibracion propia. No es un error, pero conviene mirarlo una vez en el calibrador antes
del domingo.

### Defecto encontrado: apagar la galeria no la cerraba

`galeria.php` decidia el acceso mirando **solo si existia el hash del PIN**, no el interruptor
del admin. Apagar "galeria habilitada" no cerraba nada: como todas las fiestas usan el mismo
PIN, cualquiera con el enlace seguia entrando. Ahora mira `galeriaHabilitada`, que es el
interruptor **y** el PIN. Verificado: la fiesta desactivada da 404 y las dos reales siguen
abriendo.

### AVISO IMPORTANTE PARA FUTUROS DESPLIEGUES

**La `galeria.php` de PROD NO es la de esta rama.** PROD corre una version de 32 kB con lista de
invitados, impresion por invitado y entrada sin PIN para el admin logueado; la de esta linea de
codigo tiene 8 kB. Viene de otra rama que se desplego aparte.

Por eso el arreglo se aplico **sobre el archivo de PROD** (se bajo, se parcho la linea, se
volvio a subir) y no subiendo el de la rama, que habria borrado toda esa funcionalidad. Antes de
subir `galeria.php` desde aca, comparar siempre el md5 con PROD. Fue justo esa comparacion la
que evito el destrozo.

## DESPLEGADO 2026-09-07 — Ajustes generales: copia oculta y recuperacion de contrasena

Dos cosas que son del **administrador**, no de una fiesta ni de una tematica, asi que viven en
una pantalla nueva **Admin -> Ajustes** (`data/ajustes.json`, mismo tipo de dato que
`marca.json` y `planes.json`): Luis las cambia cuando quiera, sin tocar el hosting.

| # | Local | PROD | Nota |
|---|---|---|---|
| 1 | `public/lib.ajustes.php` | `/lib.ajustes.php` | nuevo |
| 2 | `public/lib.admin-password.php` | `/lib.admin-password.php` | nuevo |
| 3 | `public/lib.mail.php` | `/lib.mail.php` | **en LF**; la copia oculta |
| 4 | `public/data/ajustes.json` | `/data/ajustes.json` | nuevo; no accesible por web (403) |
| 5 | `public/admin/ajustes.php`, `recuperar.php` | `/admin/` | pantallas nuevas |
| 6 | `public/admin/config.php` | `/admin/config.php` | prefiere la contrasena recuperada |
| 7 | 8 pantallas del admin | `/admin/` | enlace "Olvide la contrasena"; `invitations.php` **en LF** |

**La copia oculta va como destinatario extra del SOBRE, no como cabecera `Bcc:`.** Hablando SMTP
directo, esa cabecera viajaria dentro del mensaje y el cliente veria a quien mas se le mando,
que es exactamente lo contrario de una copia oculta. Si el servidor rechaza la copia, el envio
sigue: que falle la copia no puede impedir que al cliente le llegue su correo.

**La contrasena nueva no reescribe el archivo de configuracion del servidor** (el que tiene las
credenciales de la base y del SMTP). Queda como hash en el directorio de estado, fuera de la
carpeta publica, y `admin/config.php` lo prefiere cuando existe. Borrar ese archivo devuelve la
contrasena original.

Lo que protege la recuperacion: el enlace se manda **siempre** al correo de Ajustes, nunca a uno
escrito en el formulario; dura 30 minutos; sirve una sola vez; pedirlo esta limitado a 3 veces
por hora y por IP; y al cambiarla se avisa por correo, para enterarse si no fue uno.

Verificado en PROD: `php -l` limpio en los 15 archivos, ajustes cargados, **enlace de
recuperacion pedido desde la pantalla real y enviado**, y **un correo de prueba enviado de
verdad** con su copia oculta saliendo en el sobre y sin que la direccion aparezca en el mensaje.
Pruebas: `tests/backend/admin-password.php` (26 comprobaciones nuevas) mas las suites de
cliente, comprobante, backend general, leads y aceptacion. Smoke 33/33.

## DESPLEGADO 2026-09-07 (cierre) — pantalla de Finanzas y logo de Instagram en los carteles

### Finanzas: inversion contra ingresos, con graficos

Luis pidio "un tema de finanzas inversion + ingresos con estadisticas y graficos para saber
cuanto invierto y cuanto me ingresa". La decision de diseno que importa es **que NO se guarda**:
el cobro de cada fiesta ya vive en `cc_parties` y **no se copia** a la tabla nueva. La pantalla
lo lee con el mismo `cb_party_billing()` que arma el comprobante, asi que finanzas y la boleta
del cliente no pueden mostrar numeros distintos. En `cc_finanzas` va solo lo que la aplicacion
no puede saber sola: impresora, papel, imanes, publicidad.

Lo regalado se cuenta aparte y **no como ingreso cero**: una fiesta de marcha blanca al 100% es
plata que se decidio no cobrar, y verla sumada es lo que avisa si la marcha blanca se estira.

| Archivo local | Destino en PROD | Tipo |
|---|---|---|
| `public/lib.finanzas.php` | `app/lib.finanzas.php` | OBLIGATORIO (nuevo) |
| `public/admin/finanzas.php` | `app/admin/finanzas.php` | OBLIGATORIO (nuevo) |
| `public/admin/index.php` | `app/admin/index.php` | OBLIGATORIO (**CRLF**, solo la pestana) |
| `public/admin/planes.php` | `app/admin/planes.php` | OBLIGATORIO (**CRLF**, solo la pestana) |
| `public/admin/ajustes.php` | `app/admin/ajustes.php` | OBLIGATORIO (**CRLF**, solo la pestana) |
| `public/admin/comprobante.php` | `app/admin/comprobante.php` | OBLIGATORIO (**CRLF**, solo la pestana) |
| `public/admin/mensajes.php` | `app/admin/mensajes.php` | OBLIGATORIO (**CRLF**, solo la pestana) |
| `public/admin/aceptaciones.php` | `app/admin/aceptaciones.php` | OBLIGATORIO (**CRLF**, solo la pestana) |
| `database/migrations/017_finanzas.php` | — | Se aplico con runner puntual, borrado despues |

**La tabla de migraciones en PROD se llama `cc_schema_migrations`, no `cc_migrations`.** El
runner fallaba en silencio hasta correrlo con `-d display_errors=1`: en el PHP CLI del host los
errores no salen por defecto. Anotarlo aca ahorra el mismo rato la proxima vez.

Aplicada y registrada: **19 versiones**. Verificado en PROD contra la base real (no solo por
HTTP): `cb_finanzas_resumen()` devuelve las tres fiestas con precio cargado, los ingresos
cuadran con la suma de sus partes y el resultado cuadra con ingresado menos invertido.
`tests/backend/finanzas.php`: 26 comprobaciones. Los otros tests siguen verdes (comprobante 23,
cliente 46, admin 26).

**Bug que solo aparecio al probar contra PROD:** `lib.finanzas.php` no requeria `lib.php`, asi
que funcionaba desde el admin (que ya lo carga) y reventaba desde cualquier script. La prueba
por HTTP no lo habria encontrado nunca, porque sin sesion solo se ve el login.

### El Instagram del pie de los carteles, con su logo

En el pie de cada cartel el handle salia como texto suelto y se leia como un correo. Ahora lleva
el glifo de la camara al lado, dibujado con `currentColor` para que siga al texto en los dos
estilos (blanco sobre el fondo en "marco de agua", gris en "cabecera") y dimensionado en `em`,
no en pixeles, porque el mismo pie se imprime en A6 y en A4.

| Archivo local | Destino en PROD | Tipo |
|---|---|---|
| `dist/assets/carteles-DPB3tLmo.js` | `app/assets/carteles-DPB3tLmo.js` | OBLIGATORIO (hash nuevo) |
| `dist/assets/carteles-DhQkSTt2.css` | `app/assets/carteles-DhQkSTt2.css` | OBLIGATORIO (hash nuevo) |
| `dist/carteles.html` | `app/carteles.html` | OBLIGATORIO (apunta a los hashes nuevos) |

Los bundles viejos (`carteles-CMXBjjcX.js`, `carteles-CM2zCFlK.css`) quedaron en el servidor y
se pueden borrar. Verificado por HTTP: los tres responden 200 y el bundle trae `cartel__ig`.

### Catalogo: el recuerdo imantado entra al Plan Premium

`data/planes.json` en PROD se bajo primero y estaba **identico** al del repo (Luis no lo habia
editado desde el admin). Se agrego una sola linea al Plan Premium, en tercer lugar de la lista
porque es lo unico fisico del plan y tiene que leerse antes de que dejen de mirar:
`Foto impresa con iman para el refri: una por invitado (hasta 25)`. Precios sin tocar.
Respaldo en el scratchpad. Verificado en el sitio publico.

## DESPLEGADO 2026-09-07 (noche) — dos juegos por fiesta, menu y tabla de posiciones

Sesion larga. Lo que sigue es el estado real de PROD al cerrar, con lo que hay que saber para
retomar sin romper nada.

### Regla que manda sobre todo lo demas: las URL impresas no se tocan

Luis ya imprimio los carteles QR de las dos fiestas del 13-sep. Un QR impreso no se puede
corregir el dia de la fiesta, asi que **ninguna direccion existente puede cambiar**. El
procedimiento que se uso y conviene repetir:

1. `scratchpad/urls-criticas.py antes` guarda el estado HTTP de las 10 direcciones que estan
   impresas o en uso.
2. Se hace el cambio.
3. `urls-criticas.py despues` compara y avisa si alguna cambio de estado.

Se corrio en cada paso de esta sesion. **Ninguna URL cambio nunca.**

### El menu de juegos: misma URL, contenido nuevo

`app/juego/?p=<slug>` antes abria el juego directamente. Ahora abre un **menu**, y el juego
que estaba ahi paso a llamarse `mundo.html` **en la misma carpeta**. Renombrar el HTML y no
mover la carpeta fue deliberado: asi todas sus rutas relativas siguen resolviendo igual.

- `app/juego/?p=<slug>` -> menu (index.html nuevo)
- `app/juego/mundo.html` -> el juego de siempre, solo renombrada la entrada
- `app/juego/festival/` -> "El Festival de las Estrellas" (de Codex)

**Que juegos aparecen depende de la tematica**, y lo decide el servidor en `puntajes.php`. En
un cumpleanos de superheroes no puede ofrecerse un mundo de nieve. Hoy: `hielo` tiene dos
juegos, `spidey` y `heroes` tienen uno, y las otras siete tematicas ninguno.

**Ojo con los nombres, que confunden:** la tematica de fiesta `spidey` usa el tema `heroes`
del motor del juego (el mapeo esta en `juego/game/temas/index.js` linea 20). Por eso los
recursos aracnidos viven en `juego/temas/heroes/`. Pero en el admin `heroes` es OTRA tematica
distinta, la de "Mision 3D". No son lo mismo y confundirlas lleva a construir sobre la
tematica equivocada.

### El Festival de las Estrellas (juego de Codex) — tematica hielo/Frozen

Origen: `C:\wamp64\www\tucumple-codex\`, rama `codex/festival-estrellas`, commit `0b68f50`.
**El original quedo intacto**; se trabajo sobre una copia. Se subieron solo los 58 archivos
que su `docs/ENTREGA.md` marca como obligatorios (19,44 MB). Nada de `.git/`, `docs/`,
`tests/`, `README.md` ni `AGENTS.md`: verificado por HTTP que dan 403/404.

`models/heroina.glb` conserva su SHA-256 aprobado
(`59fa55ef55bb36e3dc40da5c9a565b9e5eb5fe0d5b7cc81518e1e1a094c4a549`).

**El servidor no conocia la extension `.mjs`.** No esta en `/etc/mime.types` ni en la
configuracion de Apache, y no habia ningun `.mjs` servido en todo el dominio. Sin declararlo,
los once modulos del juego se sirven con un tipo que el navegador rechaza al importarlos y la
pantalla queda negra **sin ningun error visible**. Se resolvio con un `.htaccess` propio en
`festival/`, aditivo, que solo agrega `AddType text/javascript .mjs` y politica de cache. El
del padre (`juego/`) no se toco: ya corta el catch-all del kiosco y declara `.glb`, `.wasm` y
`.js`.

Cambios hechos al codigo de Codex — los tres que pedia el encargo, mas dos correcciones que
salieron de la prueba de Luis en tablet:

| Que | Donde | Por que |
|---|---|---|
| Guardado por fiesta | `src/persistencia.mjs` | la clave era unica, asi que dos cumpleanos en la misma tablet se pisaban los datos. Ahora `cumpleclick-festival-v1:<slug>`; sin `?p=` cae en la clave vieja para no perder una partida abierta antes del cambio |
| Hook de pruebas cerrado | `src/main.mjs` | `window.__juego` exponia `forzar()`, que inyecta controles: un mando invisible al alcance de cualquiera que abriera la consola durante la fiesta. Ahora solo aparece con `?debug=1` |
| **Voz de Alice sobre el sintetizador** | `src/main.mjs`, 3 llamadas | decia `audio.localVoice ? null : T.instruccionAudio[...]`, o sea "si la tablet tiene voz espanola, usa esa en vez de la grabacion". Estaba invertido: ganaba el sintetizador del sistema y Alice nunca sonaba. **Las 9 grabaciones que trae el Festival SI son de Alice** (md5 identico a las del juego de hielo); el problema nunca fue el archivo sino cual elegia reproducir |
| Letras del HUD | `estilo.css`, bloque al final | de 26 a 70 px encima del mundo 3D tapaban justo lo que hay que mirar. Bajadas con `clamp` contra `vmin`. En un caso hubo que igualar especificidad (`#hud #jugador`) porque `#hud strong` ganaba; se hizo **sin `!important`** |

El bloque de CSS va **al final y comentado**, para poder distinguir que se cambio despues de
la entrega de Codex.

### Posiciones de la fiesta (migracion 018)

`cc_puntajes` (fiesta, juego, jugador, puntaje, fecha). **Guarda cada intento**, no el mejor:
el mejor se calcula al leer, y asi ademas se puede mostrar cuantas veces jugo cada nino.

La decision de fondo esta probada en `tests/backend/puntajes.php` para que nadie la
"simplifique" mas adelante: **los puntajes de dos juegos no se suman**. Un juego reparte miles
de puntos por nivel y el otro decenas por turno; sumandolos, el de numeros grandes decide la
fiesta entera y el otro no influye. Medido con datos reales en PROD: sumando, Sofia le sacaba
**4.617** a Matias; con medallas la diferencia quedo en **1 punto**.

Por eso cada juego reparte su propio podio y la tabla general cuenta **medallas** (3/2/1).
Dentro de cada juego se compara el **mejor intento**, no la suma: con la suma gana el que se
queda pegado a la tablet toda la tarde, no el que juega bien.

Los nombres se unifican con `MB_CASE_TITLE`, porque "lucho", "Lucho " y "LUCHO" son el mismo
nino y sin eso la tabla se llena de duplicados justo cuando mas se mira.

`puntajes.php` **no lleva autenticacion, y es a proposito**: lo llama un juego que corre en la
tablet de la fiesta, sin sesion de admin ni PIN. Una credencial ahi tendria que viajar en el
JavaScript del juego, donde cualquiera la lee, y no protegeria nada. Lo que si se hace es
acotar el dano: solo fiestas y juegos que existen, nombre saneado, puntaje acotado y limite
de peticiones por direccion. El peor caso real es que alguien con el QR del cartel meta
puntajes falsos en un cumpleanos: molesto, sin consecuencias, y se arregla borrando filas.

### Los juegos reportan

Ambos anotan con `sendBeacon` y no con `fetch`: un turno termina justo cuando la pantalla
cambia y el nino le pasa la tablet al siguiente, y ahi un envio normal se cancela a mitad de
vuelo. El beacon lo entrega el navegador aunque la pagina ya se haya ido.

- **Festival** (`src/posiciones.mjs`): anota a cada nino **apenas termina su turno**, no al
  final de la fiesta. Si la tablet se apaga o el cumpleanos se corta antes de la ceremonia, lo
  ya jugado no se pierde. El ultimo jugador se anota en `ceremony()`, que es el unico que no
  pasa por el cambio de turno; mandarlos a todos ahi inflaria su contador de partidas.
- **mundo.html** (`game/posiciones.js`): es una aventura de un jugador, no por turnos, y no
  lleva puntos sino objetivos. El puntaje sale del progreso guardado (copos x10, criaturas
  x40, cascada y tormenta x60, extra por completarla). Recien empezado da 0; completa, 540.

**Cual juego es se lo dice el menu** con `&juego=`, no lo adivina el juego: el motor solo
conoce su tema, y `spidey` y `heroes` comparten mundo pero son juegos distintos en la tabla.

Nada de esto puede arruinar una partida: si no hay red o el servidor no responde, se pierde
ese puntaje y el juego sigue igual.

### Girar el aparato y pantalla completa

`juego/orientacion.js` mas `orientacion.css`, compartidos por los dos juegos. Es un script
clasico y no un modulo porque los dos juegos tienen sistemas de modulos distintos.

**El CSS va en archivo aparte** porque el Festival declara `style-src 'self'` en su CSP: un
`<style>` inyectado desde el script queda bloqueado como estilo en linea y el aviso se veria
sin ningun formato.

Detecta el aparato con `pointer: coarse` (el puntero principal es un dedo) y no preguntando
"hay tactil": un notebook con pantalla tactil respondia que si y recibia un aviso que no le
corresponde, porque ahi uno agranda la ventana, no gira el monitor. Celular contra tablet lo
decide el lado corto de `screen` (menos de 500 = celular), no el de la ventana, que cambia al
girar y clasificaria al mismo aparato de dos formas.

Celular -> "Gira el celular". Tablet -> "Gira la tablet". Computador -> no se muestra nada.

**Pantalla completa:** el navegador no deja entrar por su cuenta. `requestFullscreen()` exige
un gesto de la persona y **girar el aparato no cuenta como gesto**. Por eso se engancha al
primer toque despues de girar: como para jugar hay que tocar la pantalla igual, en la practica
sale automatico. Ademas intenta fijar la orientacion en horizontal (Android lo permite; iOS lo
ignora sin romper nada).

### Boton para enviar el comprobante desde la ficha

En `admin/index.php`, al pie de la seccion Cobro. El envio sigue viviendo en
`admin/comprobante.php` —un solo lugar que manda correos— pero antes de llevar alla se dice
que falta: sin contactos con correo, sin precio, o con descuento sin motivo escrito. Enterarse
en la otra pantalla obligaba a volver.

### Lista de subida

| Archivo local | Destino en PROD | Tipo |
|---|---|---|
| `database/migrations/018_puntajes.php` | — | aplicado con runner puntual, borrado despues |
| `public/lib.puntajes.php` | `app/lib.puntajes.php` | OBLIGATORIO (nuevo) |
| `public/puntajes.php` | `app/puntajes.php` | OBLIGATORIO (nuevo) |
| `scratchpad/menu/index.html` | `app/juego/index.html` | OBLIGATORIO (reemplaza el juego, que paso a `mundo.html`) |
| `scratchpad/orientacion/orientacion.js` | `app/juego/orientacion.js` | OBLIGATORIO (nuevo) |
| `scratchpad/orientacion/orientacion.css` | `app/juego/orientacion.css` | OBLIGATORIO (nuevo) |
| `scratchpad/festival/` (58 archivos) | `app/juego/festival/` | OBLIGATORIO (19,44 MB) |
| `scratchpad/festival/.htaccess` | `app/juego/festival/.htaccess` | OBLIGATORIO — sin esto el juego no arranca |
| `scratchpad/game-posiciones/posiciones.js` | `app/juego/game/posiciones.js` | OBLIGATORIO (nuevo) |
| `main.js` parcheado | `app/juego/game/main.js` | OBLIGATORIO |
| `public/admin/index.php` | `app/admin/index.php` | OBLIGATORIO (**CRLF**) |
| `public/admin/_style.css.php` | `app/admin/_style.css.php` | OBLIGATORIO (**CRLF**) |
| `dist/carteles.html` y sus dos assets | `app/` y `app/assets/` | OBLIGATORIO (los hashes cambian en cada build) |

`mundo.html` se creo copiando `index.html` **en el servidor** con `cp -p`, no subiendolo: asi
el archivo queda identico bit a bit al que ya funcionaba.

### Respaldo y rollback

`~/respaldos/juego-antes-festival-20260907.tar.gz` (30 MB, 195 archivos, verificado que se
puede leer) mas una copia local en el scratchpad. Revertir es descomprimir ese tar y borrar
`festival/`.

### Lo que NO esta probado

- **El Festival nunca ha corrido en la tablet fisica.** Sus 45 fps no estan certificados; su
  propia documentacion registra caidas y hasta 179 draw calls contra un presupuesto de 150.
- No hay partida real de 12 participantes; la automatizacion de Codex cubre 8.
- El aviso de girar se verifico emulando celular y computador. **El caso tablet con tactil no
  se pudo emular** y se valido por logica contra medidas de aparatos reales.
- La tabla de posiciones se probo con datos inyectados, no con ninos jugando de verdad.

### Estado de la base al cerrar

20 migraciones aplicadas. `cc_puntajes` y `cc_finanzas` existen y estan **vacias**: los datos
de prueba se borraron al terminar.

## DESPLEGADO 2026-09-07 (cierre) — manual de la fiesta, PDF de Terminos y los correos desde el admin

Luis pidio que el manual, el enlace de firma y los Terminos firmados salieran **desde el admin,
por correo, con boton para verlos y ademas el PDF adjunto**. Lo que habia era un script de
Python del sabado 6 con los datos escritos a mano (`scratchpad/correo/generar_manual.py`),
hecho para la fiesta equivocada (Isidora) y sin nada de lo que aparecio despues (menu de
juegos, Posiciones, Festival, Album, comprobante).

### El motor de documentos: `lib.documentos.php`

`CcPdf` (la boleta) dibuja en coordenadas absolutas y no sabe que una hoja se llena. Encima va
`CcDocumento`: un cursor que avanza, corta la hoja sola, numera las paginas, y ofrece bloques
(banner, titulo, parrafo, lista, pasos numerados, tabla de dos columnas, imagen, firma). Los
renglones se parten aca y no con `CcPdf::parrafo()`, porque ese dibuja todas las lineas de una
vez y no puede detenerse a mitad de parrafo para cambiar de hoja. Una palabra mas larga que el
renglon (una URL, una huella SHA-256) se corta por letras en vez de salirse del margen.

**La firma dibujada llega como PNG con fondo transparente y el motor solo sabe de JPEG**: se
aplana sobre blanco con GD (disponible en PROD) a un archivo temporal que se borra al instante.

### El manual: una sola fuente, tres salidas

`lib.manual.php` arma los datos desde la ficha (nombre, edad, fecha, tematica, contacto
principal, hora y lugar del Resumen del Plan, juegos de la tematica). `cb_manual_secciones()`
es **el unico lugar donde vive el contenido**; de ahi salen la pagina (`manual.php`), el PDF
(`&pdf=1`, el mismo que va adjunto) y el correo. Si se escriben por separado terminan diciendo
cosas distintas.

- Los nombres de los personajes salen de `themes.json` en tiempo de ejecucion: **el
  repositorio no lleva nombres de franquicias**.
- Lo que la ficha no sabe entra como parametro al generar: el PIN de la galeria y el enlace de
  la invitacion (su token va hasheado en la base y **no se puede reconstruir**; se pega a mano,
  como ya hacia Mensajes).
- Las dos cifras del manual —minutos de anticipacion y dias de plazo para la lista— son
  **ajustes** (`manual_anticipacion_min`, `manual_dias_lista`, pantalla Ajustes). Vacias, el
  manual dice "con anticipacion" y "antes de la fiesta" sin inventar un numero.
- El enlace va **firmado con HMAC** (`manual.php?p=<slug>&f=<firma>`), igual que el
  comprobante de pago: trae el nombre del papa y el PIN, y no debe abrirse adivinando el slug.
  La firma se comprueba antes de mirar si la fiesta existe.

### El PDF de Terminos firmados: `cb_acceptance_pdf()`

Las mismas seis secciones que la evidencia HTML. **La evidencia HTML sigue siendo el documento
probatorio** (es lo que se hasheo al firmar y se verifica byte a byte); el PDF es una copia
legible generada a pedido, y por eso lleva la huella del HTML y no una propia.

El texto legal integro se incluye **solo si el que hay hoy en disco es exactamente el que se
firmo** (misma huella y misma version). Si los documentos cambiaron despues, el PDF lo dice y
remite a la evidencia HTML, en vez de imprimir un texto que el firmante nunca vio.
`tests/backend/acceptance-pdf.php` fija ese criterio con una firma PNG real.

Sale por tres lados: adjunto en el correo al firmar (`cb_acceptance_send_notifications`, y si
el PDF falla el correo sale igual con el enlace), `comprobante-aceptacion.php?r=...&pdf=1` para
el cliente, y `admin/aceptaciones.php?action=pdf&id=N` con boton "PDF" por fila.

### Los tres correos, separados a proposito

| Correo | Desde donde | Como |
|---|---|---|
| **Firma los Terminos** | Aceptaciones, boton junto al enlace recien generado | el enlace es de un solo uso y vence; solo en este correo, para que el papa lo encuentre por lo que es |
| **El manual de tu fiesta** | ficha de la fiesta, bloque Cobro, con PIN e invitacion opcional | PDF adjunto + boton "Ver el manual"; al contacto principal |
| **Terminos firmados** | automatico al firmar (ya existia) | ahora con el PDF adjunto |

Los tres con copia oculta al correo de Ajustes. El boton de firma manda el enlace de vuelta
oculto en el formulario —ya esta a la vista en esa misma pantalla, no expone nada nuevo— y el
servidor comprueba que sea un enlace de aceptacion de este sitio y de una fila pendiente de
esta fiesta.

### Reconciliacion de `admin/index.php` y `lib.php`

Los cambios de la edad (`birthday_age`, migracion 019) se habian aplicado sobre copias en el
scratchpad y subido a PROD, y el repositorio se quedo atras. Se unificaron: el repositorio
tiene ahora la edad (de PROD) mas el envio del manual (nuevo), y es lo que se subio.

### Lista de subida

| Archivo local | Destino en PROD | Tipo |
|---|---|---|
| `public/lib.documentos.php` | `app/lib.documentos.php` | OBLIGATORIO (nuevo) |
| `public/lib.manual.php` | `app/lib.manual.php` | OBLIGATORIO (nuevo) |
| `public/manual.php` | `app/manual.php` | OBLIGATORIO (nuevo) |
| `public/lib.acceptance.php` | `app/lib.acceptance.php` | OBLIGATORIO (**CRLF**) |
| `public/comprobante-aceptacion.php` | `app/comprobante-aceptacion.php` | OBLIGATORIO (**CRLF**) |
| `public/lib.ajustes.php` | `app/lib.ajustes.php` | OBLIGATORIO (**CRLF**) |
| `public/admin/aceptaciones.php` | `app/admin/aceptaciones.php` | OBLIGATORIO (**CRLF**) |
| `public/admin/ajustes.php` | `app/admin/ajustes.php` | OBLIGATORIO (**CRLF**) |
| `public/admin/index.php` | `app/admin/index.php` | OBLIGATORIO (**CRLF**) |
| `public/admin/_style.css.php` | `app/admin/_style.css.php` | OBLIGATORIO (**CRLF**) |

Respaldos en el servidor: `<archivo>.bak-20260907-manual` para los siete modificados.

Verificado en PROD contra datos reales: manual de 3 paginas con la cabecera JPEG de la
tematica para las dos fiestas; enlace firmado valida y 403 sin firma o con firma falsa; las 10
URL impresas sin cambio de estado; el admin en pie sin errores PHP. Correo de prueba del
manual de Samantha enviado a la casilla de Luis con el PDF adjunto (132 kB). Bateria backend:
257 comprobaciones en verde (manual 41, acceptance-pdf 18, acceptance 46, comprobante 23,
cliente 46, admin-password 26, finanzas 26, puntajes 31).

**No probado:** el PDF de Terminos contra una firma real de PROD (todavia no hay ninguna
aceptacion firmada); se probo en local con una firma PNG dibujada con GD. Y el boton de enviar
la firma se probo por codigo, no disparando un correo a un papa.

---

## DESPLEGADO 2026-09-07 (tarde) — Instagram enlazable en el PDF, la pagina y el correo

Luis: *"el instagram debe estar tambien linkeable en el PDF asi cmo el del correo con el log
de instagram"*. El correo tampoco lo tenia: ahora las tres piezas llevan la misma cuenta, con
su icono y como enlace.

### Que se hizo

- **El motor de PDF aprendio a curvar.** `CcPdf` solo sabia lineas, rectangulos y JPEG. Se le
  agregaron `rectanguloRedondeado()` y `circulo()`, ambos por curvas Bezier, porque el formato
  PDF no tiene primitiva de arco. Con eso el glifo de Instagram se **dibuja**, no se pega como
  imagen: sale nitido a cualquier zoom, no depende de que exista un archivo en el servidor
  (que es justo lo que rompe un PDF en silencio) y no cuesta un JPEG mas.
- **`CcDocumento::cierreConLogos()`** recibe un parametro `instagram` opcional y pinta la
  cuenta con el glifo al lado, subrayada, en el morado de enlace, y **anota la zona tocable**
  incluyendo el icono.
- **Enlaces de la tabla, corregidos.** La deteccion anterior comparaba el renglon entero con
  la URL, asi que la fila `https://...galeria.php?p=samantha (PIN 1234)` no se reconocia: la
  URL y el texto que le sigue comparten renglon. Ahora la URL se consume caracter a caracter,
  se pinta solo ese tramo como enlace y el resto queda en tinta normal. Antes de esto, las
  filas mas utiles del manual eran las unicas que **no** se podian tocar.
- **Bug de sombra corregido** en `CcDocumento::tabla()`: una variable `$ancho` interna pisaba
  el ancho de la tabla y la fila siguiente quedaba con ancho negativo, lo que hacia que
  `partir()` no avanzara nunca y agotara la memoria. `partir()` ahora tambien se defiende:
  con un ancho no positivo devuelve el texto sin partir en vez de colgarse.
- **El correo:** pie con `@Cumple_Click` y el logo, enlazado. El icono va como PNG alojado
  (`assets/img/instagram.png`, generado con GD a 4x y reducido) porque los clientes de correo
  no dibujan SVG; el `alt` es la cuenta, para que con imagenes bloqueadas siga leyendose.
- **La pagina del manual:** el mismo glifo, en SVG en linea.
- La cuenta y su enlace salen de `data/marca.json` (Admin -> Marca). Si falta el enlace, se
  arma con la cuenta, igual que ya hacia el cartel QR.

### Lista de subida

| Orden | Archivo local | Destino en PROD | Tipo |
|---|---|---|---|
| 1 | `sitio/assets/img/instagram.png` | `public_html/assets/img/instagram.png` | OBLIGATORIO (nuevo, **raiz del sitio, no `app/`**) |
| 2 | `public/lib.pdf.php` | `app/lib.pdf.php` | OBLIGATORIO (**CRLF**) |
| 3 | `public/lib.documentos.php` | `app/lib.documentos.php` | OBLIGATORIO (**CRLF**) |
| 4 | `public/lib.manual.php` | `app/lib.manual.php` | OBLIGATORIO (**CRLF**) |
| 5 | `public/lib.mail-templates.php` | `app/lib.mail-templates.php` | OBLIGATORIO (**CRLF**) |

El PNG va **primero** a proposito: el pie del correo lo referencia, y al reves los correos
enviados en ese hueco saldrian con la imagen rota. `public/manual.php` no cambio (md5 igual
al de PROD); tampoco `lib.acceptance.php`, `lib.ajustes.php` ni el admin.

Respaldos en el servidor: `<archivo>.bak-20260907-instagram` para los cuatro PHP.

### Verificado en PROD

- md5 de los cuatro PHP en el servidor identicos a los locales; `php -l` limpio en los cuatro.
- Contra los datos reales de `samantha-hielo` y `luciano-spidey`: PDF valido de 135 y 151 kB,
  **6 enlaces tocables anotados** en cada uno, `/URI (https://instagram.com/Cumple_Click)`
  presente, la cuenta impresa al pie (comprobado inflando los flujos, no en los bytes crudos,
  que darian falso positivo por la anotacion), y la galeria tambien tocable.
- El PDF real bajado por HTTP y **renderizado**: el glifo sale correcto y el enlace de la
  ultima pagina es `https://instagram.com/Cumple_Click`.
- `https://cumpleclick.com/assets/img/instagram.png` responde 200 `image/png`. Ojo: el CDN de
  Hostinger sirve una version recodificada (1979 bytes vs 1631 en disco) — le pasa igual a
  `correo-logo.png` desde siempre; la imagen servida es la misma y es valida.
- La pagina del manual en el navegador: enlace a Instagram con el glifo, color `#d6307f`.
- El correo renderizado: icono cargado desde el CDN (64x64) y enlazado.
- Las 10 URL impresas, todas 200, sin cambio de estado.
- Backend: manual 51, acceptance-pdf 18, comprobante 23, finanzas 26, puntajes 31,
  fuente-baloo 39, lint OK.

**No probado:** no se envio ningun correo nuevo a un papa; el pie se verifico renderizando el
HTML del correo, no con un envio real.

---

## DESPLEGADO 2026-09-07 (noche) — Panel de correos con reenvío, y el texto legal sin notas internas

Dos encargos de Luis: *"y desde el admin ya cuando se envia hay un mensaje que diga enviado? y
boton de reenviar cada uno de los correos con los PDF?"* y *"de paso arregla el texto legal
borrador"*.

### El problema que resolvía

Mandar un correo dejaba un mensaje en pantalla y **nada más**. Al día siguiente no había forma
de saber si el manual de una fiesta ya se había enviado. Con dos fiestas se aguanta de
memoria; con seis, no. El peor caso era el enlace de firma: su botón solo existía en la
pantalla inmediatamente después de generarlo, así que para reenviarlo había que generar uno
nuevo —lo que **revocaba el anterior**— sin que nada lo dijera.

### Qué se hizo

- **Migración 020: `cc_envios`.** Bitácora de los cuatro correos. Guarda **cada envío**, no el
  último por tipo: un reenvío es un hecho distinto y saber cuántas veces se mandó algo es lo
  que uno quiere cuando el papá dice "no me llegó". Los intentos fallidos también se guardan,
  y **no** cuentan como enviado.
- **`public/lib.envios.php` (nuevo).** Los cuatro correos detrás de la misma puerta. No
  duplica el contenido de ninguno: el del manual sale de `cb_manual_correo()`, el de la boleta
  de `cb_comprobante_correo()` y el de Términos firmados de la misma función que lo manda al
  firmar. El único que vive ahí es el de la firma, que estaba escrito **dentro** de
  `admin/aceptaciones.php` y se movió entero, sin cambiarle una coma.
- **Panel "Correos de esta fiesta"** en la ficha (sección Cobro): los cuatro con su estado
  —enviado cuándo, a quién, cuántas veces, cuántos intentos fallaron— y botón de envío o
  reenvío. Cada tipo bloqueado dice **por qué** ("Ya está firmado", "Nadie ha firmado
  todavía", "Falta el valor del servicio") en vez de un botón gris sin explicación.
- **El PIN y el enlace de la invitación quedan guardados** con el envío del manual, y el
  reenvío los repite. Sin eso, un reenvío saldría sin la invitación que el primero sí llevaba.
- 🔴 **Los dos correos que rotan enlace avisan antes.** El token de firma y el del comprobante
  se guardan **hasheados** —a propósito, para que una filtración de la base no permita firmar
  ni descargar el comprobante de nadie—, así que el enlace en claro existe una sola vez y
  reenviar obliga a emitir uno nuevo. Los botones lo dicen y piden confirmación. El de
  Términos firmados lleva además el PDF adjunto, así que el papá recibe el documento aunque
  tenga guardado el enlace viejo.

### El texto legal

Los tres documentos que firma el cliente llevaban dentro las notas de trabajo: `BORRADOR
PREPARADO POR EL EQUIPO DE AUTOMATIZATECH`, trece `TODO-LUIS` y dos `(abogado: confirmar...)`.
Eso iba **dentro del PDF que firma el papá**. Como ninguna fiesta ha firmado todavía (las diez
están `waived`), nadie lo recibió.

Se cerró cada pendiente con el criterio de no inventar nada:

| Pendiente | Cómo se cerró | De dónde salió el dato |
|---|---|---|
| Razón social y RUT | `AUTOMATIZATECH SpA, RUT 78.363.717-0` | dato canónico de la empresa |
| Contacto de privacidad | `contacto@cumpleclick.com` | `data/marca.json` |
| Porcentaje del anticipo | **No se fija uno**: es por evento y ya vive en el Resumen del Plan | evitar que el contrato contradiga la ficha |
| Medios de pago | "se informan junto con el Resumen del Plan y en el comprobante" | los datos bancarios no van en un contrato: cambian |
| Escala de cancelación | Se adopta la que **ya estaba escrita** (15+ días íntegro, 14-7 el 50%, menos de 7 sin devolución) | el texto ya la traía; solo se quitó el "confirmar" |
| Valores de reposición | "el valor del equipo afectado, acreditado con factura o cotización" | una tabla que no existía dejaba la cláusula vacía |
| Retención de fotos | **30 días la foto de cabina y 90 el Álbum Recuerdo** | `lib.php` y `lib.album.php`: la política decía solo 30 |
| Proveedores | Solo Hostinger (hosting, BD y correo) | verificado en PROD; no se usa Drive |
| Domicilio | Se quita la mención en la identificación; el foro pactado sigue siendo Santiago | no inventar un domicilio |

🔴 **Una decisión que necesita abogado:** el derecho a retracto del art. 3 bis de la Ley
19.496 quedó **expresamente excluido**, apoyado en que esa misma norma lo permite si el
proveedor lo dispone y lo informa antes, y en que el servicio bloquea una fecha en exclusiva.
Es coherente con la escala de cancelación que ya estaba escrita, pero es una postura legal
tomada por un agente: hay que confirmarla.

Versión del paquete legal: **2026-09-05 → 2026-09-07**; huella `684a8c4a...`. Cambiar el texto
cambia la huella, y por eso solo se podía hacer sin ninguna firma emitida: un comprobante
firmado solo incluye el texto legal si su huella coincide con la firmada.

También salieron tres `TODO-LUIS` del propio admin: el contacto de CumpleClick en el resumen
del plan ahora sale de `data/marca.json` en vez de estar escrito a mano.

### Arreglos de paso

- **La huella SHA-256 se partía según qué dígitos le tocaran.** En Helvetica una `f` mide 278
  y un `0` mide 556, así que dos huellas de 64 caracteres ocupan anchos muy distintos: la
  vieja entraba justa y la nueva se cortaba en dos renglones. Una huella cortada no sirve para
  verificar nada. Ahora la tabla achica el valor hasta que entre, con prueba de regresión
  usando la peor huella posible (64 dígitos anchos).
- **Seis suites de pruebas estaban en rojo desde antes.** Cada una listaba a mano las
  migraciones que aplicaba y esas listas se quedaron en la 016; la 019 agregó una columna que
  el código ya escribía. Ahora todas usan `tests/backend/_migraciones.php`, que aplica el
  esquema completo. Para que eso fuera posible hubo que hacer idempotente la migración 003:
  sus dos `RENAME COLUMN` no tenían guarda y reventaban al aplicarse dos veces.
- **La prueba de correos de `leads` contaba imágenes** y el ícono de Instagram la rompió. Se
  cambió el criterio por el que de verdad importa: el tope subió a dos, pero ahora se exige
  `alt` en **todas** las imágenes y no solo cuando hay una.

### Lista de subida

| Orden | Archivo local | Destino en PROD | Tipo |
|---|---|---|---|
| 1 | `database/migrations/020_envios.php` | (runner puntual, **no queda en el servidor**) | OBLIGATORIO — antes que el código |
| 2 | `public/legal/terminos-y-condiciones.md` | `app/legal/terminos-y-condiciones.md` | OBLIGATORIO (**CRLF**) |
| 3 | `public/legal/politica-de-privacidad.md` | `app/legal/politica-de-privacidad.md` | OBLIGATORIO (**CRLF**) |
| 4 | `public/legal/consentimiento-imagen-menores.md` | `app/legal/consentimiento-imagen-menores.md` | OBLIGATORIO (**CRLF**) |
| 5 | `public/lib.envios.php` | `app/lib.envios.php` | OBLIGATORIO (nuevo, LF) |
| 6 | `public/lib.acceptance.php` | `app/lib.acceptance.php` | OBLIGATORIO (**CRLF**) |
| 7 | `public/lib.documentos.php` | `app/lib.documentos.php` | OBLIGATORIO (LF) |
| 8 | `public/admin/_style.css.php` | `app/admin/_style.css.php` | OBLIGATORIO (**CRLF**) |
| 9 | `public/admin/comprobante.php` | `app/admin/comprobante.php` | OBLIGATORIO (**CRLF**) |
| 10 | `public/admin/aceptaciones.php` | `app/admin/aceptaciones.php` | OBLIGATORIO (**CRLF**) |
| 11 | `public/admin/index.php` | `app/admin/index.php` | OBLIGATORIO (**CRLF**) |

Los legales van **antes** que el código que los lee, para que no exista un instante con el
texto viejo y la versión nueva. Respaldos: `<archivo>.bak-20260907-envios` para los diez que
ya existían. `tests/backend/*` y `database/migrations/020_envios.php` **no se suben**: el
runner se borró del servidor al terminar.

### Verificado en PROD

- Migración 020 aplicada; `cc_envios` con sus diez columnas. `020_envios.php` y el runner
  borrados del servidor.
- md5 de los diez archivos en el servidor **idénticos** a los locales; `php -l` limpio.
- Contra los datos reales: el texto legal en versión `2026-09-07`, huella `684a8c4a...`, **sin
  BORRADOR, sin TODO-LUIS y sin notas para el abogado**; nombra el RUT, resuelve el retracto,
  y la retención dice los dos plazos que el código aplica de verdad (30 y 90 días).
- El panel devuelve los cuatro correos para las dos fiestas, y reenviar Términos sin firma
  avisa en vez de reventar.
- `admin/index.php`, `admin/aceptaciones.php` y `admin/comprobante.php` responden 200.
- Las 10 URL impresas, todas 200, sin cambio de estado.
- Batería backend completa: **16 suites en verde** (~660 comprobaciones), incluidas las seis
  que estaban rotas de antes.

**No probado:** no se mandó ningún correo real desde el panel nuevo. El botón de reenvío se
ejercitó por código (bitácora, rotación de tokens y los avisos cuando no hay nada que
reenviar), no disparando un correo a un papá. Tampoco se probó el panel dentro del admin con
sesión iniciada: se renderizó el bloque real fuera del login.

---

## 🔴 INCIDENTE Y HOTFIX 2026-09-08 — "Guardar" mandaba un correo en vez de guardar

Luis reportó dos síntomas que parecían distintos: *"quiero editar el dato de los años que va a
cumplir Samantha y siempre sale 5, cuando edito y grabo 4 no lo hace"* y, poco después, la
pregunta que destapó todo: *"¿cada vez que le doy a guardar se envían los correos?"*.

**Sí se enviaban.** La bitácora `cc_envios` lo dejó por escrito: dos manuales salieron a
`orihennys88@gmail.com` el 2026-09-08 a las 03:02:36 y 03:02:49 UTC, con trece segundos de
diferencia. Eso no fue nadie apretando "Reenviar": fue Luis apretando **Guardar** dos veces
mientras intentaba corregir la edad.

### La causa

El panel de correos se dibujó **dentro** del formulario de la ficha, y cada botón traía su
propio `<form>`. HTML **no permite formularios anidados**: el navegador descarta el interno y
se queda con sus campos. Así, los `<input name="action" value="reenviar_...">` terminaban
dentro del formulario grande, **después** del `action=guardar` de la línea 796. PHP, ante dos
campos con el mismo nombre, se queda con el último.

Resultado: apretar Guardar mandaba `action=reenviar_...`, así que **no guardaba nada** y en su
lugar disparaba un correo al cliente. Los dos síntomas eran el mismo bug.

El mismo defecto existía antes del panel: el formulario del manual ya estaba anidado ahí desde
que se desplegó el manual, más temprano el mismo día. El panel solo lo hizo evidente al
multiplicarlo por cuatro y al dejar registro de los envíos —sin la bitácora, los dos correos
habrían salido sin que nadie se enterara nunca.

### El arreglo

Los botones ya no viven en formularios propios: se enganchan con `form="cc-envios"` a un
formulario declarado **fuera** del de la ficha. El atributo `form` asocia un control a
cualquier formulario del documento, así que el panel se ve dentro de la ficha sin compartirle
un solo campo.

### Otros dos bugs que aparecieron tirando del hilo

- **Crear una fiesta estaba roto.** El `INSERT` de `cb_save_parties()` enumeraba los valores a
  mano y se saltaba `gallery_enabled`: 15 valores para 16 columnas. PDO tiraba `Invalid
  parameter number`, la función devolvía `false` y **nadie miraba ese `false`**. Ahora los
  valores se arman desde el mismo arreglo que usa el `UPDATE`.
- **El campo de la edad decía `placeholder="5"`.** Con el campo vacío, ese 5 gris se lee como
  un valor guardado. Es la mitad de "siempre sale 5". Ahora dice `Ej: 5`.

### Prueba de regresión

`tests/backend/admin-formularios.php` (51 comprobaciones). Comprueba la propiedad estructural
—ningún `<form>` dentro de otro en `admin/*.php`, y ningún formulario con dos `action`
propios— y que guardar la ficha conserve de verdad la edad y la galería, incluido el caso
exacto que reportó Luis: cambiar 5 por 4 y releer.

### De paso, en el mismo despliegue

- **El panel estaba desalineado.** La versión anterior era una tabla de tres columnas que se
  apilaba con `@media (max-width: 720px)`. Una media query mide la **ventana**, no el
  contenedor: la ventana era de 1170 px pero la columna de la ficha de 610, así que nunca se
  activaba. Ahora cada correo es un bloque que se apila y funciona a cualquier ancho.
- **El selector "Tomar el precio de un plan" siempre mostraba la primera opción.** No tiene
  `name` y no se guarda —a propósito: lo que queda en la ficha es el número, para que subir el
  precio del catálogo no le mueva el precio a una fiesta ya acordada—, pero encontrarlo
  siempre en "Escribir el precio a mano" se lee como que no guardó. Ahora marca el plan cuyo
  precio coincide con el guardado: refleja la ficha aunque no la mande.
- **Mensajes listos para WhatsApp.** El manual y el comprobante tienen su texto ya rellenado
  con los datos de la fiesta, con botón de copiar y de compartir. Solo esos dos: sus enlaces
  van firmados con HMAC y son siempre los mismos. Los de Términos no se pueden copiar —existen
  en claro una sola vez—, así que el de la firma se copia y se manda por WhatsApp desde
  Aceptaciones, en el momento en que se genera, que es donde ahora hay botón para eso.
- **Textos neutrales.** "El enlace para que el papá firme" pasó a "para que quien contrató
  firme": puede ser la mamá, el papá o cualquier adulto responsable. Se mantiene "galería de
  papás" donde habla del grupo de adultos invitados, que es otra cosa.
- La fila de la firma ahora lleva botón directo a **Aceptaciones**, que es donde se genera y
  se manda ese enlace.

### Lista de subida

| Orden | Archivo local | Destino en PROD | Tipo |
|---|---|---|---|
| 1 | `public/lib.php` | `app/lib.php` | OBLIGATORIO — arregla el alta de fiestas |
| 2 | `public/lib.envios.php` | `app/lib.envios.php` | OBLIGATORIO |
| 3 | `public/admin/_style.css.php` | `app/admin/_style.css.php` | OBLIGATORIO |
| 4 | `public/admin/aceptaciones.php` | `app/admin/aceptaciones.php` | OBLIGATORIO |
| 5 | `public/admin/index.php` | `app/admin/index.php` | 🔴 OBLIGATORIO — es el que arregla el incidente |

Respaldos: `.bak-20260908-hotfix` (primera tanda) y `.bak-20260908-wa` (segunda).

### Verificado en PROD

- md5 de los cinco archivos idénticos a los locales; `php -l` limpio.
- `admin/index.php` y `admin/aceptaciones.php` responden 200.
- Las 10 URL impresas, todas 200, sin cambio de estado.
- Batería backend: 17 suites en verde (~710 comprobaciones), con la nueva de formularios.

**No probado:** no se apretó "Guardar" en el admin de PROD con sesión iniciada. La propiedad
que causó el incidente se comprueba en el código, no en el navegador.

---

## DESPLEGADO 2026-09-08 — El Resumen del Plan se llena solo, y el logo de AT unificado

Luis: *"esos campos ya existen, no tengo que volverlos a llenar al menos que los quiera editar,
ya deben venir precargados"* y *"en unos PDF vi el logo de AT en negro, deben ser así como el
del comprobante"*.

### Generar enlace de aceptación: precargado desde la ficha

El formulario pedía a mano el nombre, correo y teléfono del cliente, el valor, el anticipo, la
hora y la dirección — **teniéndolos todos cargados**. No era solo trabajo repetido: era la
forma más fácil de que el contrato terminara diciendo un número distinto del que dice la
boleta.

Dos funciones nuevas en `lib.cliente.php`, que es donde ya viven los contactos y el cobro:

- `cb_party_contacto_principal($slug)` — nombre, correo y teléfono del contacto principal.
- `cb_party_resumen_plan($slug)` — lo que el Resumen puede deducir: hora y dirección de la
  invitación publicada, valor y anticipo del cobro, forma de pago, y el descuento explicado.

Tres decisiones que no son obvias:

- **El valor total es lo que se paga**, no el precio de lista: precio menos descuento. Un
  contrato dice lo que el cliente debe, y así no puede contradecir la boleta, que sale del
  mismo `cb_party_billing()`.
- **El descuento se explica en Observaciones.** Un total de `$0` sin nada al lado se lee como
  un error; con "Precio de lista $49.995 con un descuento de $49.995 (Marcha blanca)" se lee
  como lo que es.
- **Lo escrito a mano gana.** Se usa `+=` sobre el arreglo, que no pisa lo que ya trae, y el
  orden de precedencia es: lo que el admin acaba de escribir (POST) → el último enlace emitido
  para esa fiesta → la ficha.

`cb_manual_invitacion()` pasó a delegar en `cb_party_invitacion_datos()`: la consulta de "qué
invitación manda cuando hay varias" ahora tiene un solo dueño, porque la usan el manual y el
formulario de Términos.

### El logo de AT en la página del manual

`brand/logo-automatizatech.png` es la versión **sobre fondo oscuro**: en el pie blanco de la
página del manual se veía como un recuadro negro, distinto del logo que sale en el PDF y en el
comprobante. La página ahora usa el mismo archivo que los documentos,
`brand/pdf-automatizatech.jpg`.

De paso se le quitó a ese JPEG una franja gris de 4 px que traía arriba del recorte original y
que en el pie del PDF se veía como una línea suelta encima del logo.

### Lista de subida

| Orden | Archivo local | Destino en PROD | Tipo |
|---|---|---|---|
| 1 | `public/brand/pdf-automatizatech.jpg` | `app/brand/pdf-automatizatech.jpg` | OBLIGATORIO (recortado) |
| 2 | `public/lib.cliente.php` | `app/lib.cliente.php` | OBLIGATORIO |
| 3 | `public/lib.manual.php` | `app/lib.manual.php` | OBLIGATORIO |
| 4 | `public/admin/aceptaciones.php` | `app/admin/aceptaciones.php` | OBLIGATORIO |

Respaldos: `.bak-20260908-precarga`.

### Verificado en PROD

- md5 de los cuatro idénticos a los locales; `php -l` limpio.
- Contra los datos reales: `samantha-hielo` precarga contacto completo (nombre, correo y
  teléfono), hora `15:00`, la dirección de la invitación, el valor con su nota de descuento y
  la forma de pago. `luciano-spidey` precarga hora y dirección; su ficha no tiene contactos
  cargados, y el formulario queda vacío en esos tres campos en vez de inventar nada.
- La página del manual usa el logo claro y el PDF se sigue armando.
- El CDN ya sirve el JPEG recortado (1317×241, era 1317×246).
- Las 10 URL impresas, todas 200. Batería backend: 17 suites en verde.

**No probado:** no se generó un enlace de aceptación real desde el admin de PROD; la precarga
se comprobó llamando a las funciones con los datos reales, no llenando el formulario.

---

## DESPLEGADO 2026-09-09 — Impulso Arácnido, el aviso de girar que ya no obliga, y el preset de carteles

Tres cosas en una sola subida, autorizada por Luis ("sube todo").

### 1. "Impulso Arácnido" — segundo juego de la temática Spidey

Origen: `C:\wamp64\www\impulso-aracnido\`, rama `codex/equipo-y-colores`, commit `5fff942`.
**65 archivos, 20,6 MB** en `app/juego/impulso-aracnido/`. Los dos juegos arácnidos conviven:
la fiesta `spidey` ofrece ahora **Aventura Arácnida en 3D** e **Impulso Arácnido**.

Tres detalles que no son obvios:

- 🔴 **`nombre` no significa lo mismo en los dos juegos.** En `mundo.html` y el Festival es
  QUIÉN JUEGA; en Impulso es DE QUIÉN ES LA FIESTA ("La fiesta de Luciano · 3 años"). Reusar
  la rama del menú habría hecho decir *"La fiesta de Sofía"* en el cumpleaños de Luciano. Por
  eso el menú manda **dos parámetros distintos**: `nombre` (la fiesta) y `jugador` (quién
  juega, que es el que se anota en la tabla).
- 🔴 **El juego llegó sin `.htaccess`.** Mismo caso del Festival: el servidor no conoce `.mjs`
  y sin declararlo la pantalla queda negra sin error visible.
- **El menú del repositorio estaba desactualizado** (versión anterior al selector de nombres,
  13.358 bytes contra los 19.462 de PROD). Se sincronizó antes de tocarlo.

**Reporta puntajes**, con un módulo agregado (`src/posiciones.mjs`) y una línea enganchada en
`terminar()`. **Solo en formato individual**: en el de turnos el juego no pregunta el nombre de
cada participante, así que los 8-12 turnos quedarían todos a nombre del mismo niño, que es
peor que no anotar.

### 2. El aviso de girar sugiere, ya no obliga

Pedido de Luis: *"es necesario si el usuario quiere girar mejor, pero no le obliguemos"*.

La versión anterior era una capa a pantalla completa que **solo se iba si el aparato se giraba
de verdad**. Quien prefiriera vertical —o tuviera el giro bloqueado desde los ajustes del
sistema— quedaba encerrado sin salida. Ahora hay un botón **"Jugar así"**, se devuelve el
scroll y la decisión se recuerda en `sessionStorage` (del momento, no de la persona: la
próxima fiesta vuelve a sugerirlo).

También **se quitó `screen.orientation.lock('landscape')`**: dejaba el aparato clavado en
apaisado y ya no se podía volver. Eso también era obligar.

⚠️ Esto cambia **los tres juegos**, no solo el nuevo.

### 3. Rendimiento del juego de Frozen en tablets de gama de entrada

La tablet que Luis ya tiene es una **Galaxy Tab A7**: Snapdragon 662, Adreno 610, 3 GB. El
juego arrancaba en "media" en toda pantalla táctil, y "media" todavía renderiza a ratio 1,5
con bloom. Ahora, si es táctil y `deviceMemory <= 4` o `hardwareConcurrency <= 8`, arranca
directo en **"baja"**; y el primer ajuste automático baja de 6 s a **2,5 s**.

🔴 **No está medido en la A7.** Se puede comparar en la tablet real con
`mundo.html?p=<slug>&calidad=media&debug=1` contra `&calidad=baja&debug=1`; el HUD muestra
fps, draw calls y qué calidad está activa.

### 4. El preset de carteles que faltaba

**El primer juego de carteles impresos no entró en el acrílico.** Se midió el PDF que se mandó
a imprimir: **140 × 215,9 mm** (media carta) contra un soporte de **150 × 210 mm**. Sobraban
**5,9 mm de alto**. No fue error de Luis ni de la imprenta: era el tamaño **por defecto** del
generador, y su etiqueta decía "(porta menú)", que es justo el soporte que se compró.

Nuevo preset **"Porta menú 15 × 21 cm · el nuestro"** en **146 × 206 mm**, ahora el
predeterminado. Se imprime 4 mm más chico que la medida del acrílico a propósito: esos 15 × 21
son el **exterior**, y la ranura donde entra la hoja siempre es menor. Una hoja algo más chica
se ve igual —el acrílico la enmarca—; una más grande no entra. El de media carta quedó
rotulado "(no entra en el nuestro)".

⚠️ El rebuild de Vite movió el hash de **seis** bundles, pero la página de carteles solo
necesita uno nuevo: los otros tres que usa ya estaban en PROD sin cambios. **Los otros cinco
bundles NO se subieron** — son de otras páginas y habrían arrastrado cambios que nadie pidió.

### Lista de subida (en el orden en que se hizo)

| Orden | Local | Destino | Tipo |
|---|---|---|---|
| 1 | 65 archivos de `C:\wamp64\www\impulso-aracnido\` | `app/juego/impulso-aracnido/` | OBLIGATORIO — recursos, luego `src/`, luego CSS, `index.html` al final |
| 2 | `juego-prod/impulso-aracnido.htaccess` | `app/juego/impulso-aracnido/.htaccess` | 🔴 sin esto el juego no arranca |
| 3 | `public/lib.puntajes.php` | `app/lib.puntajes.php` | OBLIGATORIO |
| 4 | `juego-prod/orientacion.js` | `app/juego/orientacion.js` | OBLIGATORIO — afecta a los tres juegos |
| 5 | `juego-prod/orientacion.css` | `app/juego/orientacion.css` | OBLIGATORIO |
| 6 | `juego-prod/game/main.js` | `app/juego/game/main.js` | rendimiento de Frozen |
| 7 | `dist/assets/carteles-ARrrllKm.js` | `app/assets/carteles-ARrrllKm.js` | OBLIGATORIO — antes del html |
| 8 | `dist/carteles.html` | `app/carteles.html` | OBLIGATORIO |
| 9 | `juego-prod/menu/index.html` | `app/juego/index.html` | 🔴 **EL ÚLTIMO** |

El menú va al final a propósito: hasta que se sube, el juego nuevo no existe para nadie y el
cartel QR impreso sigue llevando exactamente a lo de antes.

Respaldos en el servidor: `<archivo>.bak-20260909-impulso` para los seis que se sobrescribieron.

### Verificado en PROD

- **Las 10 URL impresas: ninguna cambió de estado.** `juego/?p=…` pasó de 19.462 a 20.405
  bytes, que es el menú con el juego nuevo; sigue en 200.
- md5 de los diez archivos del integrador **idénticos** a los locales; `php -l` limpio.
- El menú de `luciano-spidey` muestra los **dos** juegos y "Cumpleaños de Luciano · 3 años".
- El juego nuevo **carga entero desde PROD**: 12 módulos `.mjs`, **ninguno vacío**, 0 recursos
  fallidos, canvas activo, la ciudad y los tres personajes en pantalla.
- El botón volver lleva a `app/juego/?p=luciano-spidey&nombre=Luciano&edad=3`.
- `spidey` → 2 juegos · `heroes` → **solo Misión 3D** · `hielo` → 2 (sin contaminar).
- `carteles.html` sirve el bundle nuevo y trae el preset de 146 × 206 como predeterminado.
- 66 archivos y 20 MB en la carpeta del juego (los 65 más el `.htaccess`).

### No probado

- **Ninguna tablet física.** Ni la A7 ni la A9+ recién comprada.
- **No se jugó una ronda completa** en PROD: pausa, audio, turnos y pantalla final.
- **El reporte de puntajes se probó en local**, contra el endpoint real, pero no con una
  partida de verdad en PROD.
- **45 fps no certificados** en Impulso: en el entorno de prueba hubo caídas a 37-43 fps.
- Safari/iOS, y niños de verdad.

## 2026-09-09 tarde — Cotejo completo de PROD e imprimir desde el admin

### Cotejo PROD vs repositorio

Se comparó **archivo por archivo, por md5**, todo `cumpleclick.com/app` contra
`CumpleBooth/public/`. Se hizo después de descubrir que `galeria.php` llevaba 458 líneas de
atraso en la copia local: desplegarla habría borrado el sistema de impresión, y además hizo
revisar el archivo equivocado al migrar las fotos a JPEG (de ahí el bug
`Emilia.jpg (no está en la lista)`).

**Resultado: no hay ningún otro archivo desfasado.** 512 idénticos. Las diferencias que
aparecen son todas explicables y están detalladas en la memoria
`reference_cumpleclick_prod_vs_repo`: 14 archivos que solo difieren en CRLF/LF, dos JSON con
distinta sangría y mismo contenido, y 77 archivos que viven solo en PROD (salidas de build
con hash, `sala.php`/`lib.sala.php` de otra rama, y assets generados).

### Imprimir fotos del kiosco desde el admin

Antes solo se podía imprimir desde la galería pública (`galeria.php`, con PIN). Ahora la
sección **Fotos del kiosco** del álbum del admin usa el **mismo mecanismo ya probado con la
Selphy**: una hoja por foto, `@page` en milímetros, y espera a que carguen todas las
imágenes antes de `window.print()` (sin esa espera la primera hoja sale en blanco en la
tablet).

Se reutiliza la selección múltiple que ya existía: lo marcado se puede imprimir o borrar.
Controles: copias (1-5), papel (10×15 Selphy, 13×18, A4, según impresora) y "llenar la hoja".
El papel se recuerda en el navegador, porque en la fiesta se imprime muchas veces seguidas.

De paso se corrigió un defecto propio: en la **papelera** las miniaturas salían rotas, porque
`ver.php` exige `deleted_at IS NULL` y por lo tanto devolvía 404. Ahora una **sesión de admin
válida** puede ver una foto borrada; para un invitado no cambia nada y el QR de una foto
borrada sigue dando 404.

### Lista de subida

| Archivo local (`CumpleBooth/public/`) | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `lib.php` | `lib.php` | OBLIGATORIO | 1 |
| `ver.php` | `ver.php` | OBLIGATORIO | 2 |
| `admin/_style.css.php` | `admin/_style.css.php` | OBLIGATORIO | 3 |
| `admin/album.php` | `admin/album.php` | OBLIGATORIO | 4 |

El orden importa: `ver.php` llama a `cb_admin_sesion_activa()`, que se define en `lib.php`;
y el CSS va antes que `album.php` para que la barra nueva no aparezca un instante sin estilo.

No subir: nada del `scratchpad/` (scripts de cotejo, banco de pruebas, respaldos).

### Verificado en PROD

- **Ya está subido** (2026-09-09 15:16). md5 de los cuatro archivos idénticos a los locales,
  `php -l` limpio en el servidor.
- Respaldo previo: `~/respaldos/imprimir-admin-antes-20260909-1516.tar.gz`.
- **Las 10 URL impresas: ninguna cambió de estado.**
- La foto del enlace que mandó Luis sigue sirviéndose: 200, `image/jpeg`, 542 KB.
- La lógica de impresión se probó extrayendo el CSS, la barra y el script **del propio
  `album.php`** y montándolos en un banco con tres fotos: 2 fotos × 3 copias = 6 páginas, las
  6 imágenes cargadas, `@page` emitido como `127mm 178mm`, `window.print()` llamado una sola
  vez, y con las reglas de impresión aplicadas el resto del admin queda en `display:none` y
  cada página ocupa la hoja completa.

### No probado

- **Nadie ha impreso una hoja de verdad desde el admin.** El banco prueba que se arma la hoja
  y que se llama a imprimir; no prueba la Selphy ni el diálogo de AirPrint.
- No se abrió el admin de PROD con sesión iniciada (no tengo la contraseña).
- Tablet física: sigue sin probarse.

## 2026-09-09 noche — El diploma de Asómate lleva la foto del niño hecho héroe

Hasta ahora, al terminar "Asómate y sé el héroe" el diploma se armaba igual que el de la
ruleta: de fondo la **ilustración del personaje** de la temática. Es lo que corresponde en el
flujo de la ruleta, donde no existe ninguna foto del niño con el personaje. En Asómate sí
existe, y es justamente la gracia del juego.

Ahora el diploma de Asómate **es esa foto**, enmarcada:

- **La escena se recompone**, no se reusa la foto. Se dibuja más chica (la figura ocupa del
  29 % al 79 % del alto en vez del 10 % al 90 %) y sin el título al pie, para que el
  encabezado y el nombre del diploma no le tapen la cara ni le corten los pies.
- **Moldura dorada** con degradado y dos filetes, uno claro por fuera y otro oscuro por
  dentro. Los filetes son lo que hace que se lea como un marco de cuadro y no como una línea
  dorada encima de la foto. Sellos de estrella en las dos esquinas de arriba; abajo no, ahí
  va la marca de agua de CumpleClick y se encimaban.
- **Degradados arriba y abajo en lugar de paneles con borde.** El texto necesita fondo para
  leerse, pero un recuadro opaco parte la escena en dos.
- **El texto se reparte a los dos extremos.** Con foto: título, "Se otorga a" y nombre arriba;
  título honorífico, fiesta y agradecimiento abajo. Sin foto, el reparto es el de siempre.
- No van el confeti ni el sello inferior cuando hay foto: el confeti queda salpicado encima
  de la única imagen que importa, y el sello caía sobre las piernas del personaje.

De paso, dos correcciones del mismo tipo que el bug de la galería: la descarga del diploma y
la de la predicción **deducen la extensión del propio data URL** (`extensionDe`). Estaban
fijas en `.png` desde antes del cambio a JPEG, así que bajaban un JPEG con nombre `.png`.

### Lista de subida

| Archivo local (`CumpleBooth/dist/`) | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `assets/main-DTDqSxpq.js` | `assets/main-DTDqSxpq.js` | OBLIGATORIO | 1 |
| `index.html` | `index.html` | OBLIGATORIO | 2 |

El orden no es negociable: al revés, el kiosco queda un rato apuntando a un archivo que aún
no existe. El resto de lo que pide `index.html` (`main-x_rodESo.css`, `browser-BeMEBtOm.js`,
`client-eulB1LW-.js`, `themeVars-BWg77og2.js`) ya estaba en el servidor con el mismo md5.

No subir: `src/App.jsx` (fuente, no se sirve) ni nada del scratchpad.

### Verificado en PROD

- **Ya está subido** (2026-09-09 16:12). md5 de los dos archivos idénticos a los locales.
- Respaldo previo: `~/respaldos/kiosco-antes-diploma-heroe-20260909-1612.tar.gz`.
- `index.html` de PROD apunta a `assets/main-DTDqSxpq.js`, y ese archivo responde 200 con
  142.176 bytes.
- **Las 10 URL impresas: ninguna cambió de estado.**
- El diploma se miró renderizado, con el fondo y el PNG reales de la temática arácnida y una
  cara de prueba: entra el personaje completo con los pies dentro del marco, y el texto de
  arriba y el de abajo no lo tapan.
- Las seis líneas de texto se midieron con sus fuentes y posiciones reales, incluidas las que
  el entorno local no puede dibujar por no tener fiesta (título honorífico y línea de la
  fiesta): ninguna se sale del marco ni entra en la banda del personaje.

### No probado

- **Con una cara de verdad.** La prueba usó un rostro dibujado; no se ha visto una foto real
  de un niño dentro del hueco en el diploma.
- **En grupo.** Los cálculos cubren dos y tres niños, pero solo se miró el caso de uno.
- Tablet física, y la impresión del diploma en papel.

## 2026-09-09 cierre — El hueco de Spin estaba corrido

Luis lo vio en el diploma: en Spin (el arácnido de traje negro) el óvalo no calzaba con la
máscara. Se comía casi entero el lente derecho y dejaba el izquierdo completo, así que la
cara quedaba de lado.

**Causa:** `tematica.py` saca el centro de la cabeza promediando las filas anchas entre la
coronilla y `corte_px`. En Spin, que está en pose de salto, entre el mentón y ese corte
entran las filas donde el brazo se pega a la cabeza: la fila y=448 mide 499 px de ancho
contra los 462 de la cabeza sola, y esas filas corrieron el promedio 12 px a la derecha.
Los otros cinco personajes están de pie y de frente, y por eso ninguno falló.

**Arreglo:** `dx: -0.026` en `prototipo/spidey-ajustes.json`, que compensa exactamente ese
desvío (537,9 medido contra 526 real), y se regeneró el recorte. Se comprobó que de los seis
PNG solo cambió `spin.png`, y que en `themes.json` el único dato distinto contra el que corre
en PROD es `spin.cx`: 529,9 → 518,0.

**Sello de versión en las imágenes de Asómate.** Los recortes se llaman siempre igual y se
sirven con `max-age=2592000`. La tablet de la fiesta ya tiene esos PNG guardados: sin cambiar
la URL, la corrección no le habría llegado en 30 días. Ahora `cb_theme_asomate` le agrega
`?v=<mtime>` al fondo y a cada personaje, así que cambiar un archivo cambia su URL.

⚠️ **Para el próximo que verifique:** el CDN de Hostinger **reencoda los PNG**. Lo que
devuelve HTTP pesa distinto y tiene otro md5 que el archivo del disco aunque sea la misma
imagen (322.536 bytes contra 310.893 en este caso). Comparar bytes lleva a concluir que el
despliegue no llegó. Hay que comparar **píxeles**.

### Lista de subida

| Archivo local (`CumpleBooth/public/`) | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `themes/spidey/asomate/spin.png` | igual | OBLIGATORIO | 1 |
| `data/themes.json` | igual | OBLIGATORIO | 2 |
| `lib.php` | `lib.php` | OBLIGATORIO | 3 |

Primero el PNG y después `themes.json`: al revés hay un momento con la geometría nueva
apuntando al hueco viejo. `lib.php` puede ir en cualquier momento.

No subir: `prototipo/` ni `themes/spidey/*-cut.png` (originales de trabajo, no se sirven).

### Verificado en PROD

- **Ya está subido** (2026-09-09 16:49 y 17:0x). md5 idénticos, `php -l` limpio.
- Respaldos: `~/respaldos/spin-antes-20260909-1649.tar.gz` y `~/respaldos/lib-antes-sello-*`.
- `api.php` entrega las seis URL con sello, y la de Spin (`?v=a1b846`) difiere de las otras
  cinco, que conservan el suyo.
- La imagen que devuelve esa URL tiene **cero píxeles de diferencia** con el archivo local.
- **Las 10 URL impresas: ninguna cambió de estado.**
- Se miró la composición con el recorte nuevo: los dos lentes de la máscara quedan simétricos,
  como en Spidey.

### No probado

- En la tablet. El sello debería bastar, pero nadie ha abierto Asómate en el dispositivo real
  después del cambio.

## 2026-09-09 cierre 2 — El enlace de confirmados pasa a ser fijo

El enlace para la familia era un token aleatorio que se guardaba **hasheado** y se mostraba
**una sola vez**, al emitirlo. Después no había forma de recuperarlo: para volver a verlo
había que generar otro, y eso mataba el que ya estaba compartido. Luis se quedó sin el enlace
de la fiesta de Luciano justo por eso.

El hash tampoco compraba mucho. Lo único que ese enlace muestra son los nombres de quienes
confirmaron, y esos nombres viven en la misma base de datos que guardaba el hash: quien pueda
leer una cosa puede leer la otra.

**Ahora es una firma sobre el slug**, el mismo patrón que ya usaba `manual.php`:
`asistencia-papas.php?p=<slug>&f=<hmac de 24>`. No se emite, se calcula. Está siempre a la
vista en la ficha de la fiesta, con copiar, abrir y el mensaje de WhatsApp.

- **Los enlaces viejos siguen funcionando.** La pantalla acepta las dos entradas. El token de
  rol `parents` no se tocó, porque en baby shower ese mismo token abre predicciones y regalos
  y se emite desde Invitaciones.
- **Se cierra desactivando la fiesta.** `cb_rsvp_acceso_por_slug` exige `activa`, igual que
  hacía el camino del token. Desaparecieron los botones "Generar" y "Anular" de la ficha.

**La lista, además, quedó ordenada.** Antes salía por hora de confirmación, que sirve para ver
quién llegó último y no para buscar un apellido. Ahora va alfabética por familia, comparando
sin tildes ni mayúsculas (si no, "Álvarez" cae al final en vez de entre "Abreu" y "Andrade",
y "Ñuñez" antes que "Neumann"). Y los niños salen **uno por uno**, cada uno en su pastilla:
"Ana y ari" en un renglón se leía como un solo nombre, y esta pantalla existe justamente para
contar cuántos niños vienen. La regla que separa los nombres es **la misma** que da la cifra
del encabezado, para que el número y los nombres no puedan discrepar.

### Lista de subida

| Archivo local (`CumpleBooth/public/`) | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `lib.rsvp.php` | `lib.rsvp.php` | OBLIGATORIO | 1 |
| `asistencia-papas.php` | igual | OBLIGATORIO | 2 |
| `admin/index.php` | `admin/index.php` | OBLIGATORIO | 3 |

`lib.rsvp.php` primero: define las funciones que usan los otros dos.

### Verificado en PROD

- **Ya está subido** (2026-09-09 18:17). md5 idénticos, `php -l` limpio en el servidor.
- Respaldo: `~/respaldos/confirmados-antes-20260909-1817.tar.gz`.
- Los dos enlaces reales responden 200 y muestran la lista; una firma inválida y un slug
  inexistente caen en "Enlace no válido".
- Las 15 familias de Luciano salen en orden alfabético, y los niños separados en pastillas.
- **Las 10 URL impresas: ninguna cambió de estado.**
- En local, con datos sembrados a propósito desordenados y con tildes, el orden salió
  correcto y la cuenta de niños calzó con los nombres mostrados.

### No probado

- **La ficha del admin con sesión iniciada.** El bloque nuevo se subió con `php -l` limpio,
  pero nadie lo ha visto renderizado; no tengo la contraseña.

## 2026-09-09 noche 2 — Circuito Arácnido en PROD e Impulso actualizado

Desplegado con autorización de Luis. Detalle de la revisión previa en
`REVISION-JUEGOS-CODEX.md`.

### Lo que se subió, en orden

1. **Migración 021** (`cc_sala_carreras`), con un runner puntual que aplica **solo esa**.
   No se usó `migrate.php`: la rama de Codex numera 018 y salta a 021, y PROD ya tenía
   aplicadas la 019 y la 020, que en esa rama no existen.
2. **`app/juego/circuito/`**: 37 archivos, 33,3 MB. Se subió el `README.md` por error y se
   borró del servidor enseguida; el `.htaccess` sí queda, porque es el que declara el tipo de
   los `.mjs`.
3. **Backend compartido**, en este orden: `lib.sala.carrera.php` (nuevo), `lib.puntajes.php`,
   `puntajes.php`, `sala.php`.
4. **Impulso Arácnido**: `src/entrada.mjs` (nuevo), `reglas`, `datos`, `mundo`, `entorno`,
   `main`, `estilo.css` e `index.html` al final.
5. **`app/juego/index.html`** (el menú).

### 🔴 Lo importante: no se subieron los archivos de la rama

Tres archivos compartidos de la rama `codex/narracion-alice` están **atrasados** respecto de
PROD. Subirlos habría borrado trabajo vivo:

- `puntajes.php` de la rama no devuelve `invitados` ni `edad`: el menú dejaría de ofrecer la
  lista de nombres y los niños tendrían que teclearlos.
- `lib.puntajes.php` de la rama no tiene `impulso`: Impulso Arácnido habría **desaparecido**
  del menú.
- `orientacion.css` y `orientacion.js` de la rama vuelven a **obligar** a girar el aparato;
  PROD ya tiene el botón "Jugar así". Esos dos ni se tocaron.

Se tomó el archivo de PROD y se le injertó solo lo que Codex agrega, con aserciones que
comprueban que lo anterior sigue ahí.

### Un defecto encontrado al probar, no reportado en la entrega

El menú **no conocía `circuito`**: `RUTAS` no tenía su entrada, así que caía al `mundo.html`
de respaldo y **la tarjeta abría el juego equivocado**. Se le agregaron ruta, ícono (🏎️) y
pie. Respaldo del menú anterior en `~/respaldos/menu-antes-circuito.html`.

### Verificado en PROD

- **Las 10 URL impresas: ninguna cambió de estado**, medidas antes y después.
- md5 idéntico en los 37 archivos del juego, los 4 del backend, los 8 de Impulso y el menú.
- `php -l` limpio en el servidor sobre los cuatro PHP.
- La API del menú de `luciano-spidey` devuelve los **tres** juegos y conserva los **6
  invitados** y la edad. La de `samantha-hielo` **no** trae circuito, como corresponde.
- Circuito **carga en producción**: canvas activo, cero errores de consola, los seis pilotos y
  el selector de 1 a 5 vueltas, y saluda "¡Vamos, Luciano!".
- Impulso **sigue cargando** tras la actualización, cero errores, con el control nuevo.
- `.htaccess` y `src/posiciones.mjs` de Impulso intactos: la integración de puntajes sigue.
- Los endpoints nuevos responden bien: 403 con JSON limpio sin sesión válida, 422 con petición
  mal formada, y una operación inventada se sigue rechazando.
- **Prueba real de sala:** dos jugadores entraron a la misma sala (BCZ4P), fase `espera`, 3
  vueltas por defecto, y los cuatro puestos libres se llenaron con pilotos automáticos.
- La sala de prueba **se borró**, y al borrarla desapareció su fila en `cc_sala_carreras`: la
  cascada de la migración funciona. `cc_salas` volvió a 11 filas, las mismas de antes.

### No probado

- **Ninguna tablet ni celular físico.** Ni una carrera de verdad entre varios aparatos.
- Safari/iOS.
- Los 45 fps en móvil **no están certificados**: seis renderizados a la vez en un PC dieron
  unos 30 fps de mediana.
- La voz Alice no la ha escuchado una persona.
- **34 MB por dispositivo.** Un papá con datos móviles y sin el wifi de la casa paga esa
  descarga. Sin resolver.
- Doce turnos completos de Impulso, y niños de tres años de verdad.

## 2026-09-09 cierre 3 — Volver al menú, pantalla completa y fondo temático

Tres pedidos de Luis después de probar Circuito.

### Un control común para todos los juegos: `app/juego/pantalla.js`

Dos botones flotantes arriba a la izquierda, en cualquier juego que incluya el archivo:
**← volver al menú** y **⛶ pantalla completa**.

Va por fuera y no dentro de cada juego porque **el mundo 3D viene empaquetado**: su código
está en otro repositorio y en PROD solo hay un bundle con nombre con hash. Agregarle un botón
por dentro obligaba a reconstruirlo entero.

- **Volver** usa el parámetro `volver` que el menú ya mandaba y que el mundo 3D ignoraba. Se
  valida contra el mismo origen: es un parámetro de la URL y mandar a la gente a otro sitio
  desde ahí sería un regalo. Pide **dos toques** con tres segundos de gracia: un niño de tres
  años apoya el dedo donde sea, y un solo toque le borraba la partida.
- **Pantalla completa** entra también con el primer toque en la página. Antes eso solo pasaba
  **estando apaisado** (`orientacion.js` lo condicionaba a `!enVertical()`), así que quien
  jugara en vertical no la veía nunca.

🔴 **Girar el aparato no puede activar la pantalla completa.** `requestFullscreen()` solo
corre dentro de un gesto de la persona; al girar el teléfono nadie tocó nada y el navegador
lo rechaza. Lo que sí pasa es que, ya estando en pantalla completa, girar **no la corta**.

🔴 **Los juegos se sirven con `style-src 'self'`.** Un `<style>` inyectado o un atributo
`style=` los bloquea la CSP **en silencio**: el botón aparece sin forma, pegado arriba a la
izquierda. Se descubrió probando. Por eso el archivo no tiene ni una hoja de estilo y todo va
por CSSOM (`el.style.prop = ...`), que la CSP no toca.

Circuito **no** lo lleva: ya tiene sus propios botones de volver y de pantalla completa.

### Fondo de la temática en el menú

El menú y la pantalla "¿Quién va a jugar?" salían con un degradado neutro. Ahora llevan
detrás el `fondo-banner.jpg` de la temática, el mismo que ya usa la pantalla de confirmados.
**Ninguna imagen nueva, cero créditos.** Va difuminado y al 34 % porque este menú es claro
—tarjetas blancas, texto oscuro— y la foto a plena vista dejaba el texto ilegible.

### Lista de subida

| Archivo local (`CumpleBooth/public/juego/`) | Destino (`app/juego/`) | Clase | Orden |
| --- | --- | --- | --- |
| `pantalla.js` | `pantalla.js` | OBLIGATORIO | 1 |
| — | `mundo.html` | OBLIGATORIO | 2 |
| — | `festival/index.html` | OBLIGATORIO | 2 |
| — | `impulso-aracnido/index.html` | OBLIGATORIO | 2 |
| `index.html` | `index.html` | OBLIGATORIO | 3 |

El script primero: si el HTML llega antes, hay un rato pidiendo un archivo que no existe.

⚠️ **Los tres HTML se editaron sobre la copia bajada de PROD, no sobre un original del
repositorio.** `mundo.html` viene de `tucumple-repo` y en PROD solo existe compilado;
`festival/index.html` viene de `tucumple-codex`. Un export nuevo de cualquiera de esos dos
**borra la línea del script**. Está anotado en `PENDIENTES-DE-PRUEBA.md`.

### Verificado en PROD

- **Las 10 URL impresas: ninguna cambió de estado.**
- Respaldos: `~/respaldos/juegos-antes-pantalla-20260909-*.tar.gz` y `menu-antes-circuito.html`.
- Los dos botones salen en Aventura Arácnida, Impulso y el Festival, de 44 px de alto, sobre
  cielo vacío y sin tapar ningún control.
- **El botón de volver se probó de verdad:** dos toques en Aventura Arácnida y quedó en el
  menú de juegos.
- El fondo temático carga en las dos fiestas: el arácnido en la de Luciano y el de hielo en la
  de Samantha, en el menú y en la pantalla de elegir jugador.
- El error de CSP que aparece en consola **ya estaba antes**: sale igual en Circuito, que no
  lleva este script. No lo introdujo este cambio.

### No probado

- Ninguna tablet ni celular físico, que es donde la pantalla completa importa de verdad.
- Safari en iPhone no permite pantalla completa en una página: ahí el botón **no se dibuja**,
  a propósito, en vez de quedar uno que no hace nada.

## 2026-09-09 cierre 4 — El enlace de aportes del Álbum se puede volver a mostrar

Luis: *"genero un QR del álbum, recargo la página y no aparece el 5° cartel ni el link"*.

Era por diseño, y el diseño estaba mal. El token de aportes se sorteaba al azar y en la base
quedaba **solo su hash**, así que se mostraba una única vez. Recargar la página lo perdía, y
para recuperarlo había que generar otro — que **revoca el anterior** y mata los carteles del
Álbum ya impresos. O sea: la única forma de volver a ver el enlace era romper el que estaba
funcionando.

**Ahora el token se deriva de la fila que lo representa**, con la llave HMAC de la aplicación,
así que el admin siempre puede recalcular el que está vivo. La base sigue guardando solo el
hash y la llave vive fuera de la base: una copia de la base tampoco alcanza para reconstruir
un token. Se conserva la revocación, que acá sí importa porque este enlace permite **subir**
archivos, no solo leer.

### El fallo que encontró la prueba

La primera versión derivaba de `(album, propósito, created_at)`. `created_at` tiene resolución
de **un segundo** y `token_hash` tiene **índice único**: rotando dos veces dentro del mismo
segundo, el segundo token salía idéntico al primero y el INSERT moría con *"Duplicate entry
… for key 'token_hash'"*. Dos clics seguidos en el admin = error 500.

Se corrigió derivando del **id de la fila**, que es único para siempre. Cuesta una sentencia
más: se inserta con un hash provisorio al azar, se lee el id y recién entonces se calcula el
token definitivo. El provisorio es aleatorio a propósito; uno fijo volvería a chocar con el
índice único si alguna fila quedara a medio camino.

**Esto solo salió probando.** El razonamiento previo dijo "dos emisiones en el mismo segundo
son improbables" y siguió adelante.

### Lista de subida

| Archivo local | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `public/lib.album.php` | `lib.album.php` | OBLIGATORIO | 1 |
| `public/admin/carteles-api.php` | `admin/carteles-api.php` | OBLIGATORIO | 2 |
| `dist/assets/carteles-CIwK2MVx.js` | igual | OBLIGATORIO | 3 |
| `dist/carteles.html` | `carteles.html` | OBLIGATORIO | 4 |

El panel del Álbum ahora **sigue apareciendo** aunque el cartel ya esté armado: es el único
lugar desde donde se rota el enlace, y antes desaparecía justo cuando el cartel existía. El
botón pasa a decir "Generar uno nuevo (mata el anterior)".

### Verificado en PROD

- **Ciclo completo probado sobre el álbum de la fiesta DEMO** (`demo-carreras`), para no tocar
  las dos fiestas reales: emitir revoca el anterior y eso mataría un cartel ya impreso.
  Emitir → recuperar idéntico → sirve para subir → rotar tres veces seguidas en el mismo
  segundo sin reventar → el viejo queda muerto → el nuevo se recupera. **Ocho de ocho.**
- La derivación es estable, cambia con la fila y da 32 hexadecimales, que es lo que exige
  `cb_album_resolve_token`.
- `php -l` limpio en el servidor. `carteles.html` responde 200.
- **Las 10 URL impresas: ninguna cambió de estado.**
- Respaldo: `~/respaldos/album-token-antes-20260909-*.tar.gz`.

### Lo que Luis tiene que hacer una vez

🔴 **Los dos enlaces que están vivos hoy son de los antiguos, sorteados al azar.** De ellos
solo quedó el hash, así que **no se pueden recuperar**. Hay que generar uno nuevo por fiesta,
una última vez. Desde ahí en adelante ya no se pierde nunca más.

### No probado

- La página de carteles con sesión de admin iniciada: no tengo la contraseña. El ciclo del
  token se probó por debajo, llamando a las funciones reales contra la base real.

## 2026-09-09 cierre 5 — Apagar los juegos 3D, y pie de página con la marca

Dos pedidos de Luis: que los niños no se queden pegados al teléfono, y que el menú lleve los
datos de contacto de CumpleClick.

### El interruptor

Botón **"Apagar juegos 3D"** en cada tarjeta de fiesta del admin, junto a Duplicar. Va ahí y
no dentro de "Editar fiesta" a propósito: se usa **en medio de la fiesta**, y abrir la ficha
entera para destildar una casilla y guardar todo el formulario es lento y arriesgado. Pide
confirmación al apagar, no al prender.

🔴 **La dirección del QR impreso no cambia.** `juego/?p=<slug>` sigue respondiendo igual;
apagado, muestra "Se acabó la hora de juego · Ahora viene lo mejor: la torta, las fotos y los
amigos de verdad". Cambiar la dirección no era opción: está impresa en papel.

`puntajes.php` además devuelve la lista de juegos **vacía** cuando está apagado, para que
ningún camino alternativo deje entrar igual.

### Dos fallos que salieron al probar, no al razonar

1. **La clave se perdía sin avisar.** La primera versión guardaba `juegos3d` en el registro de
   la fiesta. En PROD las fiestas viven en la **base**, y `cb_save_parties` escribe una lista
   fija de columnas: la clave no llegaba a ninguna parte. El síntoma fue que el interruptor
   decía PRENDIDOS después de apagarlo, y `grep juegos3d data/parties.json` daba cero.
   Se resolvió con la **migración 022** (`games3d_enabled TINYINT NOT NULL DEFAULT 1`).
2. **Los índices del INSERT están escritos a mano** (`array_slice($values, 0, 12)`,
   `$values[13]`), y el propio código cuenta que meter una columna en medio ya rompió esto
   una vez con un "Invalid parameter number". Por eso la columna nueva va **al final de todo**:
   de los valores, del SET y de la lista de columnas.

⚠️ **Error de orden mío en el despliegue:** subí `lib.php` —cuyo SELECT ya nombraba la
columna— **antes** de correr la migración. Entre una cosa y otra pasaron segundos y era de
madrugada, así que no alcanzó a afectar a nadie, pero el orden correcto es siempre migración
primero. Está anotado porque el próximo puede no tener esa suerte.

### El pie de página

El menú y todas sus pantallas llevan abajo el isotipo de CumpleClick, la frase
"¿Lo quieres en tu próxima fiesta?" y tres enlaces: la página, Instagram y WhatsApp. Los
textos y las direcciones salen de `data/marca.json`, no escritos en el juego: cambiar un
teléfono se hace editando un JSON.

Los íconos de web, Instagram y WhatsApp van **dibujados en el propio HTML**, no como archivos:
son cuatro trazos, toman el color del texto y no agregan cuatro peticiones más a una tablet en
plena fiesta. El isotipo de CumpleClick sí es el archivo real (`brand/cumpleclick-mark.svg`).

### Lista de subida

| Archivo local | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `database/migrations/022_juegos3d.php` | (privado, runner puntual) | OBLIGATORIO | **1** |
| `public/lib.php` | `lib.php` | OBLIGATORIO | 2 |
| `public/puntajes.php` | `puntajes.php` | OBLIGATORIO | 3 |
| `public/admin/index.php` | `admin/index.php` | OBLIGATORIO | 4 |
| `public/juego/index.html` | `juego/index.html` | OBLIGATORIO | 5 |

La migración **antes** que `lib.php`: su SELECT nombra la columna nueva y sin ella la consulta
de fiestas falla entera.

### Verificado en PROD

- Migración 022 aplicada con runner puntual; la columna existe con default 1.
- **Ciclo completo del interruptor:** apagado → la API devuelve `juegos_activos: false` y cero
  juegos → el menú muestra la pantalla de despedida → prendido de nuevo → los tres juegos
  vuelven. Luciano quedó **prendido**.
- Samantha: 2 juegos y 10 invitados. Luciano: 3 juegos y 17 invitados.
- El pie sale en las dos fiestas con el isotipo y los tres enlaces.
- **Las 10 URL impresas: ninguna cambió de estado.**
- Respaldo: `~/respaldos/apagar-juegos-antes-20260909-*.tar.gz`, con `data/parties.json`.

### No probado

- **El botón del admin, apretado por una persona.** El ciclo se probó llamando a las funciones
  reales contra la base real, no desde la pantalla: no tengo la contraseña del admin.
- Un niño ya metido dentro de un juego **no se entera** hasta que vuelve al menú. El
  interruptor corta la entrada, no la partida en curso.

## 2026-09-09 cierre 6 — El nombre de Asómate sale de la temática

En la fiesta de Samantha, que es de hielo, el botón decía **"Asómate y sé el héroe"**. El
concepto es el mismo en todas las temáticas —la cara del invitado dentro del personaje— pero
cómo se llama no lo es.

Ahora los textos viven en `themes.json`, en el bloque `asomate`, igual que ya pasaba con el
título del diploma:

- **spidey**: `🕷️ Asómate y sé el héroe`
- **hielo**: `❄️ Asómate y entra al reino`

El kiosco cae al texto genérico si la temática no lo dice, así que una temática a medias no
se rompe.

### Lista de subida

| Archivo local | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `public/data/themes.json` | `data/themes.json` | OBLIGATORIO | 1 |
| `public/lib.php` | `lib.php` | OBLIGATORIO | 2 |
| `dist/assets/main-BYsZOpv1.js` | igual | OBLIGATORIO | 3 |
| `dist/index.html` | `index.html` | OBLIGATORIO | 4 |

### Verificado en PROD

- La API de la fiesta de hielo entrega el botón nuevo y sus **4 personajes**; la de spidey,
  los **6**. El cambio de forma del retorno de `cb_theme_asomate()` no se llevó nada.
- **Las 10 URL impresas: ninguna cambió de estado.**
- Respaldo: `~/respaldos/asomate-nombre-antes-20260909-*.tar.gz`.

### Decisión de Luis: las otras temáticas quedan para después

Se puede agregar Asómate a **carreras, familia-canina, heroes, kpop y tropical** sin gastar
créditos: tienen los seis recortes y un fondo de sala de 1080×1920. Luis decidió dejarlo
**para cuando entre una fiesta de esas**, porque lo caro es revisar 30 huecos a ojo.

La receta y los errores ya cometidos quedaron en `ASOMATE-NUEVAS-TEMATICAS.md`.


## 2026-09-10 — Asómate: guía del hueco sobre la cámara y mando izquierda/derecha

Luis probó Asómate en las dos fiestas y pidió dos cosas: que la foto se pueda correr a los
lados, y que "el óvalo de la foto" coincida con "el espacio que dejaste" en el personaje.
Las tres fotos que produjo hoy (almacén de fotos, 13:18 y 13:23) muestran lo mismo: la cara
de Luisana quedó a la izquierda del hueco de Ghost-Spider y la de Sofía a la derecha del de
Elsa, chica y con la pared de fondo.

### Qué era

- **No era geometría.** Los PNG, la geometría de `themes.json` y el bundle de PROD son
  idénticos a la copia local (md5, y el hueco medido por píxeles calza con el óvalo de
  `themes.json` en los diez personajes). El hueco recortado y el óvalo donde se recorta la
  foto coinciden al píxel.
- **El compositor ya entendía `dx`**, pero la vista previa solo ofrecía tamaño y subir/bajar.
  Un niño parado a un lado no se podía centrar.
- **En la cámara no había ninguna marca.** La foto se muestreaba siempre en el centro del
  cuadro, a un quinto del alto, y nadie sabía que la cara tenía que estar justo ahí.

### Qué cambió

- **`src/asomateGuia.js` (nuevo): la única fuente de la relación foto ↔ hueco.**
  `FOTO_POR_HUECO = 5`, `rectFotoEnLienzo()` (lo usa el compositor para dibujar la foto),
  `huecoEnFoto()` y `guiaEnPantalla()` (lo usa la cámara, corrigiendo por `object-fit: cover`).
  Si cambia una, cambia la otra: no pueden volver a desacordarse.
- **`Capture` recibe `guia`** (el personaje del niño al que le toca) y dibuja un SVG con el
  óvalo y el resto del cuadro oscurecido; el texto pasa a "Pon tu cara dentro del óvalo y toca
  el botón". La foto se captura del video, así que la guía nunca sale en ella. El óvalo es
  simétrico: da lo mismo que la vista en vivo esté espejada.
- **Vista previa: tercer deslizador "↔ Izquierda o derecha"** (±1,2 anchos del hueco). Mover a
  la derecha lleva la cara a la derecha.
- **`tests/frontend/asomateGuia.test.mjs`**: seis pruebas. La central comprueba que el óvalo de
  la guía, llevado al lienzo por el rectángulo del compositor, cae exactamente sobre el hueco,
  con cámara horizontal y vertical. `npm test`: 179/179.

### Lista de subida (hecha por SSH)

| Archivo local | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `dist/assets/main-DIoFqU80.js` | igual | OBLIGATORIO | 1 |
| `dist/assets/main-C_-7odVg.css` | igual | OBLIGATORIO | 2 |
| `dist/index.html` | `index.html` | OBLIGATORIO | 3 |

No se subió nada más a propósito: `album.html`, `cartel-qr.html`, `carteles.html` y sus
chunks cambiaron de hash en el build (comparten el chunk `Lockup`) y **no se tocaron**; PROD
sigue con los suyos, consistentes entre sí. `assets/invitation.css` difiere de la rama y
tampoco se tocó. Los bundles viejos `main-*` siguen en el servidor (limpieza OPCIONAL).

### Verificado en PROD

- md5 iguales en los tres archivos; `index.html` por HTTP referencia los dos bundles nuevos;
  el JS servido trae los textos nuevos y la clase `cam-guia`.
- **Recorrido completo en PROD con una cámara falsa** (`getUserMedia` sobrescrito con un
  canvas) hasta la vista previa, **sin guardar**: guía centrada (Spidey: rx 189,8 × ry 204,8
  en una caja de 576×1024, un quinto del alto), tres deslizadores, y la cara dibujada dentro
  de la guía cayó centrada en el hueco sin mover nada.
- **Las 10 URL impresas: ninguna cambió de estado.**
- Respaldo: `~/respaldos/kiosco-antes-asomate-guia-20260910.tar.gz` (`index.html` y los dos
  bundles anteriores). Rollback: restaurar ese `index.html`.

### Cómo se probó sin cámara (repetible)

- `scratchpad/kiosco-local/servidor.py`, registrado en `.claude/launch.json` como
  `kiosco-asomate-local`: sirve `dist/` (Vite copia `public/` adentro, así que van temas y
  recortes) y contesta `api.php` con la respuesta real de PROD guardada en `api-<slug>.json`.
  Cero base de datos, misma geometría que la fiesta.
- En el navegador, antes de entrar a Asómate:
  `navigator.mediaDevices.getUserMedia = async () => canvas.captureStream(15)` con una cara
  dibujada en el canvas, y `enumerateDevices = async () => []`.

### No probado

- **Un niño real frente a la tablet.** La duda concreta: si un quinto del alto de la pantalla
  obliga a acercarse demasiado. El ajuste es `FOTO_POR_HUECO`; con 4 la cara sale un 25% más
  grande a la misma distancia, y cambia la cámara y el compositor a la vez.
- **Ghost-Spider:** el hueco está DENTRO de la máscara blanca y alrededor queda el aro de la
  capucha. Si Luis sigue viendo "dos óvalos" ahí, lo que hay que agrandar es el hueco del PNG
  (receta en `ASOMATE-NUEVAS-TEMATICAS.md`), no el código.
- Tablet en horizontal: la guía se recalcula con `ResizeObserver`, no visto en un aparato.

### Incidente aparte: 504 durante un minuto

A las 13:37 (hora del servidor) `cumpleclick.com/app/` devolvió **504 desde el borde del CDN**
mientras el origen contestaba 200 en 0,1 s; el servidor compartido tenía load average 65. Se
fue solo en un minuto. No es del código y no hay nada que subir. Detalle y cómo distinguirlo
en la memoria `reference_hostinger_504_borde_cdn_carga`.

## 2026-09-10 cierre 2 — La despedida que no se veía

Luis, en la tablet: al terminar el diploma de Asómate y pasar al siguiente invitado, **no salía
el video de despedida**. Preguntó si era a propósito. No lo era: la ruleta y Asómate terminan
en la misma pantalla, `farewell`, y ahí va el video de la temática.

### Qué era

`VideoScreen` tenía una red de seguridad de **1,2 segundos fijos**: si el video no tenía el
primer cuadro en ese plazo, mostraba la tarjeta "¡Gracias por venir!" 3,2 s y volvía a la
portada. La despedida de spidey pesa **2,7 MB**; en el wifi de un salón, o con el servidor
compartido cargado como estaba hoy, el primer cuadro no llegaba a tiempo y el invitado veía la
tarjeta en vez del video. Que la ruleta "sí lo hiciera" fue casualidad de caché: el mismo
código, el mismo plazo.

Reproducido en local con un servidor que entrega los `.mp4` con 3 s de demora
(`kiosco-lento` en `.claude/launch.json`): tarjeta y portada a los 6 s, sin video.

### Qué cambió

- **`src/videoListo.js` (nuevo):** la despedida se **baja entera apenas se conoce la fiesta**,
  una vez por carga del kiosco, y se guarda en memoria (blob URL). La pantalla de despedida
  monta el video desde ahí y arranca en el acto. Si la descarga falla, se sigue usando la URL
  de red, como antes. Siete pruebas en `tests/frontend/videoListo.test.mjs`.
- **`VideoScreen` espera 6 s** (`VIDEO_ESPERA_MS`) en vez de 1,2 antes de rendirse, y también
  cae a la tarjeta si el navegador no deja reproducir (antes quedaba un cuadro quieto). Un
  archivo que falta sigue cayendo al instante por `onError`, y el botón "Terminar" sigue ahí.

`npm test`: 186/186.

### Lista de subida (hecha por SSH)

| Archivo local | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `dist/assets/main-D1mrMuzY.js` | igual | OBLIGATORIO | 1 |
| `dist/index.html` | `index.html` | OBLIGATORIO | 2 |

El CSS no cambió (`main-C_-7odVg.css` sigue). Respaldo:
`~/respaldos/kiosco-antes-despedida-20260910.tar.gz`.

### Verificado en PROD

- md5 iguales; `index.html` por HTTP referencia el bundle nuevo; el JS trae el precargador.
- Recorrido completo de Asómate en PROD con cámara falsa y **la subida bloqueada desde el
  navegador** (para no meter una foto de prueba en el álbum de Luciano): tras "Siguiente
  invitado", el video de despedida corriendo desde memoria (`blob:`), `readyState` 4, sin
  tarjeta.
- **Las 10 URL impresas: ninguna cambió de estado.**

### No probado

- **En la tablet, con el wifi del salón.** Es donde falló; ahora debería verse siempre porque
  el archivo ya está en la tablet cuando le toca.
- **El video de bienvenida (`welcome-spidey.mp4`, 3,3 MB) tiene el mismo tipo de red de
  seguridad** (1,8 s en `PhotoSessionVideo`), y NO se tocó. Si en la tablet la bienvenida a
  veces sale como póster fijo en vez de video, es esto mismo, y el arreglo es el mismo
  precargador: `prepararVideo(CONFIG.videos.welcome)` y montar con `urlDeVideo()`.

## 2026-09-10 cierre 3 — Asómate: la cara se ajusta sola

Luis probó los seis personajes arácnidos en la tablet con su propia cara y mandó las fotos:
**solo Spidey quedaba bien**. En los demás la cara salía chica dentro del hueco, corrida a un
lado o con el cuello y la pared de fondo a la vista.

### Qué era

La foto se muestreaba siempre en el centro del cuadro y a un tamaño fijo (dos quintos del
alto). Eso queda bien únicamente si la persona está exactamente a la distancia y en el lugar
que la guía pide. Luis sostenía la tablet en la mano y se sacó cada foto a una distancia
distinta: en Spidey estaba cerca y la cara llenó el hueco; en los otros, más lejos. Un niño en
un kiosco tampoco va a estar nunca en el lugar exacto.

### Qué cambió

- **La cara se detecta y el ajuste se calcula solo.** `src/detectorCara.js` carga MediaPipe
  Tasks Vision (modelo BlazeFace de corto alcance) en segundo plano apenas se conoce la fiesta,
  solo si la temática tiene Asómate. Al llegar a la vista previa, se busca la cara en cada foto
  y `src/caraAuto.js` calcula el zoom y el corrimiento que la dejan **centrada en el hueco y
  a 1,1 veces su alto**. Los tres mandos parten de ahí: el operador solo afina.
- **Si el detector no está, falla o no ve nada, todo sigue como antes** (guía + mandos, con
  el centro y el tamaño fijos). Nunca bloquea: espera a lo sumo 4 s.
- **El modelo no ve caras chicas.** Probado con una cara real recortada de las fotos de hoy:
  al 22% del alto del cuadro no detecta nada, al 36% la ve con puntaje 0,9. Un niño a medio
  metro cae en lo primero. Por eso se mira el cuadro entero **y además cuatro cuadrantes
  solapados**, donde la misma cara ocupa el doble: cinco pasadas de ~50 ms.
- Los mandos se **estiran** para incluir el valor automático (una cara en la esquina se corre
  varios huecos); el de tamaño llega ahora a 3,5.
- Dependencia nueva en `package.json`: `@mediapipe/tasks-vision` 0.10.14. Sus archivos van
  copiados en `public/vendor/mediapipe/` (19 MB entre las dos variantes del WebAssembly, el
  cargador y el modelo de 230 KB) con un `.htaccess` propio: 404 real para lo que no existe,
  `AddType application/wasm` (el servidor los servía como `text/plain` y la raíz manda
  `nosniff`) y un mes de caché.
- Pruebas: `tests/frontend/caraAuto.test.mjs` (5). `npm test`: 191/191.

### Lista de subida (hecha por SSH, en este orden)

| Archivo local | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `public/vendor/mediapipe/.htaccess` | `vendor/mediapipe/.htaccess` | OBLIGATORIO | 1 |
| `public/vendor/mediapipe/blaze_face_short_range.tflite` | igual | OBLIGATORIO | 2 |
| `public/vendor/mediapipe/vision_wasm_internal.js` + `.wasm` | igual | OBLIGATORIO | 3 |
| `public/vendor/mediapipe/vision_wasm_nosimd_internal.js` + `.wasm` | igual | OBLIGATORIO (tablets sin SIMD) | 4 |
| `dist/assets/vision_bundle-Cd7_-YIR.js` | igual | OBLIGATORIO (trozo que carga el detector) | 5 |
| `dist/assets/main-CxDls_ua.js` | igual | OBLIGATORIO | 6 |
| `dist/index.html` | `index.html` | OBLIGATORIO | 7 |

El CSS no cambió. Respaldo: `~/respaldos/kiosco-antes-cara-auto-20260910.tar.gz`.

### Verificado en PROD

- md5 iguales en los 9 archivos. `HEAD` del `.wasm`: `application/wasm`, caché de un mes;
  un `.wasm` inexistente da 404 y no el HTML del kiosco.
- Recorrido completo en PROD con cámara falsa, **una cara real** (la de Luisana, recortada de
  la foto de hoy) puesta chica y a un costado del cuadro, y la subida bloqueada desde el
  navegador: Ms. Marvel salió con la cara centrada y llenando el hueco sin tocar un mando
  (zoom 2,22; corrimiento −2,84 y 0,58). En local, lo mismo con Pantera Negra y Hulk.
- El WebAssembly de 9,4 MB tardó 6,6 s en bajar la primera vez desde PROD; queda en caché.
- **Las 10 URL impresas: ninguna cambió de estado.**

### No probado

- **Un niño real en la tablet.** Lo que hay que mirar: si la cara queda al tamaño correcto en
  Spidey (hueco dentro de la máscara, donde antes se veía bien con la cara más grande). El
  número es `factor` en `ajusteParaCara` (1,1); si en algún personaje conviene otro, se puede
  anotar por personaje en `themes.json` más adelante.
- Que el detector alcance a cargar en el wifi del salón antes de la primera foto (9,4 MB en
  segundo plano; el flujo tarda más de medio minuto en llegar a la vista previa).
- Dos o tres niños: cada foto se detecta por separado, no visto.

## 2026-09-10 cierre 4 — Bienvenida de spidey: la voz femenina no se entendía

Luis, en la tablet: en el video de bienvenida donde los tres arácnidos saludan, lo que dice
la voz femenina no se entiende.

### Qué era (medido, no supuesto)

Las tres frases se generaron el 2026-09-02 con voces prehechas de ElevenLabs en inglés
hablando español (`scratchpad/voces-bienvenida-spidey.py`: Josh, Rachel, Antoni) y se
concatenaron sin nivelar.

- **Nivel:** la línea de Rachel estaba a **−28,2 LUFS**, contra −20,5 y −14,6 las otras dos:
  entre 8 y 14 dB más baja. En el parlante de una tablet, desaparece.
- **Dicción:** la transcripción automática de ElevenLabs (`/v1/speech-to-text`, `scribe_v1`)
  de esa línea dio **"¿Qué acá le diría que se llegaran?"** en vez de "¡Qué alegría que
  llegaran!". Las dos líneas masculinas transcribieron perfecto.

### Qué se hizo

- **Cada voz en el momento en que su personaje mueve la boca** (Luis: "deben coincidir las
  voces con los personajes"). Con una hoja de cuadros a 4 por segundo se vio que la niña de
  capucha blanca saluda con la mano de 0 a 1,75 s, Spidey abre la boca grande de 2,0 a 2,75 s y
  el trío sonríe de 3,5 a 4,5 s antes del primer plano. Orden nuevo: **Ghost-Spider** (Rachel)
  "¡Hola! ¡Bienvenidos a la fiesta!" desde 0,20 s; **Spidey** (Josh) "¡Qué alegría que
  vinieron!" desde 2,25 s; **Spin** (Antoni) "¡Vamos a celebrar en grande!" desde 4,55 s.
- Frases con sonidos simples y **verificadas por transcripción** antes de usarlas: "¡Qué alegría
  que llegaran!" salía como "diría que se llegaran"; "¡Hola, hola!" en Rachel salía como
  "Hola, chola"; Sarah dio basura ("Um, ¿qué rollo Chris K?"). Rachel con `stability` 0,7
  transcribe exacto.
- **La toma de Spin se cortaba al final** (Luis: "no termina de decirlo"). Medido: la última
  sílaba de la toma original caía 30 dB en **60 ms**, un final de guillotina que la
  transcripción no delata. Se regrabó la misma frase con puntos suspensivos al final
  ("¡Vamos a celebrar en grande!...") y la caída pasó a **280 ms**; con un punto en vez de
  exclamación daba 180 ms y la retoma sin cambios 140 ms. Spin arranca ahora en 4,30 s y su
  última palabra termina en 6,10 s, con 0,3 s de silencio antes del fin del video.
  Gasto total del día en ElevenLabs: 292 caracteres.
- Las tres líneas niveladas a **−15,5 LUFS** (±0,1) con ganancia medida y limitador a
  −1,5 dBFS, colocadas con `adelay`, silencio hasta los 6,5 s del video.
- El video no se recodificó: se copió la pista de video de `welcome-spidey-voz.mp4` y se
  cambió solo el audio (`ffmpeg -c:v copy`). Sigue h264 720×1280, 24 fps, 6,5 s, 3,3 MB.
- Transcripción de la mezcla final: **"Hola, bienvenidos a la fiesta. Qué alegría que
  vinieron. Vamos a celebrar en grande."**, con las frases empezando en 0,30 s, 2,34 s y
  4,45 s: donde tenían que estar.

### Lista de subida (hecha por SSH)

| Archivo local | Destino (`app/`) | Clase |
| --- | --- | --- |
| `public/themes/spidey/welcome-spidey.mp4` | `themes/spidey/welcome-spidey.mp4` | OBLIGATORIO |

Sin cambio de código. El kiosco pone `?v=<assetsVersion>` (el mtime más nuevo de la carpeta
del tema) a cada asset, así que la tablet y el CDN piden el archivo nuevo solos:
`assetsVersion` pasó a `1789054154`. Respaldo:
`~/respaldos/welcome-spidey-antes-voz-20260910.tar.gz`.

### Verificado en PROD

md5 igual; la API entrega `assetsVersion = 1789055545`; `HEAD` a la URL **con** ese `?v=`
devuelve los 3.313.131 B del archivo nuevo. Ojo: un `HEAD` a la URL sin `?v=` seguía
devolviendo el tamaño del archivo anterior (caché del CDN); el kiosco nunca pide esa URL.

### No probado

- **Oírlo en la tablet.** La transcripción dice que se entiende y los tiempos calzan con los
  cuadros; el gusto lo decide Luis, que tiene el MP4 y una toma alternativa con Alice para el
  saludo de la niña.
- Las bienvenidas de las otras temáticas con voz no se midieron. La receta para revisarlas
  es la misma: `ebur128` por línea y transcribir con `scribe_v1`.

## 2026-09-10 cierre 5 — El CDN achicaba las imágenes en la tablet: la causa real de "los óvalos"

Luis: "en la PC funciona, en la tablet Spin sale con la foto fuera del traje; solo Spidey está
bien". Con su foto de la tablet se vio que el personaje salía **más chico que su óvalo**, con
la foto asomando por la derecha del traje.

### Qué era (medido)

El CDN de Hostinger sirve **otra imagen según el User-Agent**: a un navegador Android le
entrega cada imagen **achicada a 800 px de ancho y convertida a WebP**; a un escritorio, la
original. Medido pidiendo cada archivo con los dos agentes:

| Imagen | Anotado | Escritorio | Tablet Android |
| --- | --- | --- | --- |
| `asomate/fondo.jpg` | 1080×1920 | 1080×1920 | **800×1422** |
| `spidey.png` | 711×1762 | 711×1762 | 711×1762 |
| `ghost-spider.png` | 916×1868 | 916×1868 | **800×1631** |
| `spin.png` | 1063×1285 | 1063×1285 | **800×967** |
| `hulk.png` | 973×2315 | 973×2315 | **800×1903** |
| `ms-marvel.png` | 1008×1821 | 1008×1821 | **800×1445** |
| `pantera.png` | 847×1891 | 847×1891 | **800×1786** |

`componerAsomate()` dibujaba el recorte con `naturalWidth × k`, o sea con el tamaño del
archivo que llegó, mientras el óvalo usaba las medidas anotadas en `themes.json`. En la
tablet el personaje salía al 75 % (Spin) y el óvalo al 100 %: la foto se veía fuera del
traje. **Spidey, de 711 px, era el único que no se achicaba** y por eso era el único que se
veía bien. Y por lo mismo las fotos de la tablet salían de 800×1422 en vez de 1080×1920.

Ninguna prueba desde el escritorio ni desde Python lo reproducía. Se vio recién al pedir las
imágenes con el User-Agent de la tablet.

### Qué cambió (tres capas)

1. **`componerAsomate()` dibuja cada recorte con `w`/`h` anotados** y el lienzo mide 1080 de
   ancho aunque el fondo llegue más chico. Funciona aunque el CDN vuelva a achicar.
2. **`public/themes/.htaccess` (nuevo):** `Cache-Control: no-transform` para las imágenes.
   El CDN lo respeta: pedido como Android, `spin.png` volvió a llegar de 1063×1285.
3. `touch` a los recortes y fondos de Asómate de spidey y hielo para cambiar su `?v=` y que
   la tablet pidiera versiones frescas; y **Luis vació el caché del CDN en hPanel**. Con eso
   confirmó en la tablet que Spin y los demás calzan.

### Lista de subida (hecha por SSH)

| Archivo local | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `dist/assets/main-BI2KBazm.js` | igual | OBLIGATORIO | 1 |
| `dist/index.html` | `index.html` | OBLIGATORIO | 2 |
| `public/themes/.htaccess` | `themes/.htaccess` | OBLIGATORIO | 3 |

Respaldo: `~/respaldos/kiosco-antes-medidas-anotadas-20260910.tar.gz`. Las 10 URL: sin cambio.

### Lección

Cuando el cliente ve en un aparato algo que no se reproduce en el escritorio, **pedir el
recurso con el User-Agent de ese aparato** antes de tocar el código. Hoy se gastaron horas
en la geometría de los huecos, que estaba perfecta.

## 2026-09-10 cierre 6 — La bienvenida se cortaba en la tablet lenta

Luis, en la Tab A7: "el saludo se ve lento y a Spin se le corta la última frase". En el
portátil, completo. El archivo estaba entero (cierre 4): la frase de Spin termina en 6,10 s
de un video de 6,50 s.

### Qué era

`ListaInvitados` tiene un temporizador de seguridad para bienvenidas que nunca terminan:
`duración + 0,6 s` desde que se leen los metadatos, y al vencer **cierra la bienvenida sin
mirar el video**. En una tablet lenta el video arranca tarde o se traba un momento, así que a
los 7,1 s de reloj todavía iba por Spin, y el temporizador lo mataba a media frase. En el
portátil el video no se atrasa y el temporizador nunca alcanza a vencer.

Además, al abrir el kiosco se cargaban los 9 MB de WebAssembly del detector de caras
(cierre 3), justo cuando suena la primera bienvenida: en una Tab A7 eso compite con el video.

### Qué cambió

- **El temporizador, al vencer, mira el video:** si sigue avanzando (`currentTime` creció y no
  está en pausa ni terminado) le da el tiempo que le falta más 0,6 s, y así hasta un tope de
  45 s. Solo abandona un video que no avanza. `onEnded` sigue siendo el camino normal.
- **El detector de caras se carga al tocar "Asómate"**, no al abrir el kiosco. De ahí a la
  primera foto pasan más de 15 s; si no llegara, la foto se ajusta con la guía y los mandos.

### Lista de subida (hecha por SSH)

| Archivo local | Destino (`app/`) | Clase | Orden |
| --- | --- | --- | --- |
| `dist/assets/main-DzdFwgzX.js` | igual | OBLIGATORIO | 1 |
| `dist/index.html` | `index.html` | OBLIGATORIO | 2 |

Respaldo: `~/respaldos/kiosco-antes-bienvenida-espera-20260910.tar.gz`. Las 10 URL: sin cambio.

### Verificado en PROD

Bienvenida de spidey con una **traba simulada** (pausa de 2,5 s a los 0,5 s de video, desde
el navegador): a los 7,4 s de reloj, donde antes se cortaba, la bienvenida seguía con el
video en 3,87 s; `ended` llegó con el video en 6,50 s y recién ahí se cerró, a los 10,1 s. Y
al abrir el kiosco ya no se descarga ningún archivo del detector.

### No probado

- **La Tab A7 misma.** Si además de arrancar tarde el video se ve a tirones, es la tablet: el
  temporizador ya no lo corta, pero no lo hace más fluido. Comparar con la otra tablet.

## 2026-09-10 cierre 7 — Frozen: los huecos de Elsa y Olaf se salían del personaje

Luis preguntó si Frozen tenía los mismos errores. Se midió en vez de suponer: para cada
personaje de hielo se comprobó cuánto del **borde exterior** del óvalo anotado cae sobre
píxeles opacos del recorte original (si el óvalo está dentro de la figura, el 100 %).

| Personaje | Borde exterior dentro de la figura |
| --- | --- |
| anna | 100 % |
| kristoff | 100 % |
| elsa | 88 % (se salía por la izquierda y bajaba hasta el cuello) |
| olaf | 59 % (más ancho y más alto que la cabeza) |

Con la foto ajustada a 1,1 veces el hueco, en esos dos la foto asomaba por fuera del
personaje. Se recortaron de nuevo desde `elsa-cut.png` y `olaf-cut.png` con un óvalo que
queda dentro de la cara y cubre cejas a mentón (buscado en una grilla de escalas y
corrimientos, exigiendo 100 % dentro): Elsa 134×236 centrado en (323,189) en vez de 158×248
en (303,219); Olaf 302×420 en (323,478) en vez de 336×466 en (323,448). Verificado por
píxeles: hueco 99-100 % transparente, borde exterior 98,6 % y 99,9 % opaco. En `themes.json`
solo cambiaron los ocho números (`cx`, `cy`, `rx`, `ry` de los dos).

Los seis de spidey ya cumplían el 100 % (el componente transparente del hueco calzaba con
el óvalo y estaba encerrado por la figura).

## 2026-09-10 cierre 8 — Frozen: bienvenida con voces

Luis: "el video de bienvenida de Frozen no tiene audio; que tenga voces como el de Spidey".
El archivo era solo video, 14,04 s: 0-4,5 s un túnel de hielo que se acerca al salón, 4,5 s
un destello, y de 5 s en adelante Elsa, Anna, Kristoff, Sven y Olaf alrededor de la torta,
con un destello de magia entre 9 y 11,5 s.

Cuatro voces, colocadas donde el video las pide y **todas verificadas por transcripción**
antes de usarlas (frases con sonidos simples; `<break time="0.6s" />` al final para que la
última sílaba tenga cola):

| Cuándo | Quién | Voz | Frase |
| --- | --- | --- | --- |
| 1,1 s (túnel) | narradora | Alice | "¡Bienvenidos al reino de hielo!" |
| 5,5 s (aparece el grupo) | Elsa | Rachel, `stability` 0,7 | "¡Hola! ¡Qué alegría que vinieron!" |
| 8,2 s | Anna | Jessica | "¡Bienvenidos a la fiesta!" |
| 10,3 s (destello) | Olaf | Josh | "¡Vamos a celebrar en grande!" |

Kristoff y Sven no hablan: una quinta frase no cabía antes de los 14 s sin apretar. Matilda
también transcribe bien en español (queda de reserva); Lily no existe en la cuenta. Las
cuatro líneas a −15,6 LUFS (±0,2); transcripción de la mezcla final exacta. El video no se
recodificó (`-c:v copy`). Gasto: 382 caracteres de ElevenLabs.

### Lista de subida de los cierres 7 y 8 (hecha por SSH)

| Archivo local | Destino (`app/`) | Clase |
| --- | --- | --- |
| `public/themes/hielo/asomate/elsa.png` | igual | OBLIGATORIO |
| `public/themes/hielo/asomate/olaf.png` | igual | OBLIGATORIO |
| `public/data/themes.json` | `data/themes.json` | OBLIGATORIO (solo 8 números; PROD era idéntico al local antes) |
| `public/themes/hielo/welcome-hielo.mp4` | igual | OBLIGATORIO |

Respaldo: `~/respaldos/hielo-antes-huecos-y-bienvenida-20260910.tar.gz`. Verificado: md5
iguales; la API de `samantha-hielo` entrega la geometría nueva y `assetsVersion` nuevo; con
User-Agent Android los PNG llegan a tamaño completo y el video con `?v=` pesa lo mismo que el
local.

### No probado

- Asómate de Frozen con los huecos nuevos en la tablet, y la bienvenida oída ahí.

## 2026-09-10 cierre 9 — Frozen: el pase de artista con voces

Luis: "el pase de artista donde salen las dos hermanas también necesita audio". Es
`themes/hielo/entrada-palacio-hielo.mp4` (`photoSession.video`, 5,04 s): Elsa y Anna en el
palacio de hielo, de perfil, girándose hacia la cámara mientras acerca el plano. Tenía una
pista de audio a **−52,7 LUFS**: silencio en la práctica.

Dos voces, verificadas por transcripción, con `<break time="0.5s" />` al final:

| Cuándo | Quién | Voz | Frase |
| --- | --- | --- | --- |
| 0,3 s | Elsa | Rachel, `stability` 0,7 | "¡Ven! ¡Ponte aquí con nosotras!" |
| 2,5 s | Anna | Jessica | "¡Sonríe para la foto!" |

Las dos a −16 LUFS; la última palabra termina en 3,72 s de 5,04. Transcripción de la mezcla
exacta. Video sin recodificar. Gasto: 96 caracteres de ElevenLabs (total del día: 478).

| Archivo local | Destino (`app/`) | Clase |
| --- | --- | --- |
| `public/themes/hielo/entrada-palacio-hielo.mp4` | igual | OBLIGATORIO |

Respaldo: `~/respaldos/hielo-pase-antes-voz-20260910.tar.gz`. Verificado: md5 igual, la API
entrega `assetsVersion` nuevo y el `HEAD` con `?v=` pesa lo mismo que el local (6.346.292 B).

### No probado

- Oído en la tablet. Y el resto de temáticas con pase de artista **no se midió**: la receta es
  `ebur128` sobre el archivo; si da menos de −40 LUFS, está mudo.

## 2026-09-10 cierre 10 — Circuito Arácnido: las flechas empujan, los rayos recargan

Un usuario le hizo dos observaciones a Luis:

1. **Las rampas con flechas mentían.** En un juego de carros una flecha en el suelo significa
   "esto te empuja de una"; el rayo significa energía. Aquí las flechas **recargaban el turbo**
   y no empujaban. Ahora las flechas **impulsan** (velocidad de 23 a 37 durante 1,5 s, sin
   apretar nada) y se agregaron **ocho rayos por vuelta** repartidos por la pista que son los
   que **recargan** la barra (+30 cada uno).
2. **En un iPhone con Chrome, al terminar la carrera ningún botón respondía.** En Android y en
   las dos tablets de Luis funcionaba.

### Qué se cambió

- **`sim.mjs`:** `Piloto` gana `impulso` (segundos de empujón) y `energias` (rayos recogidos);
  `ENERGIA` lista las ocho posiciones por vuelta con su carril. `paso()` ya no devuelve un
  booleano sino `'impulso'`, `'energia'` o `''`.
- **`world.mjs`:** las flechas quedan **igual**; se agregan los rayos, hechos con el mismo
  contorno del SVG del botón TURBO, extruidos, amarillos, flotando 1,6 sobre la pista, con un
  balanceo suave. Se apagan al recogerlos y vuelven en la vuelta siguiente.
- **`main.mjs`:** avisos distintos ("¡Impulso!" y "¡Turbo recargado!", este último con la voz
  de siempre); `dibujar()` recibe el piloto local para saber qué rayos ya se tomaron.
- **`main.mjs`, la salida:** `salir()` hacía `dejar(); enviarPuntaje(); voz.detener();
  musica.pause(); location.href=...` en una sola línea. **Si cualquiera de esas llamadas
  lanzaba, la navegación no se ejecutaba y el botón parecía muerto.** Ahora cada paso va
  aislado y, si la asignación se ignora, un respaldo a los 700 ms fuerza la salida. Lo mismo
  en "¡Otra carrera!".
- **`circuito.css`:** en iPhone las barras del navegador se montan encima de un elemento
  `position:fixed; inset:0`, así que el borde inferior —donde están esos botones— no recibe el
  toque. Las pantallas ahora miden `100dvh`, el alto que de verdad se ve.
- **`.htaccess` del juego:** los `.mjs` **no entraban** en la regla de caché de la carpeta
  padre (cubre `.js`, no `.mjs`) y salían sin `Cache-Control`. Un navegador podía quedarse con
  un `sim.mjs` viejo junto a un `main.mjs` nuevo. Ahora se revalidan siempre. El sello de
  `index.html` pasó a `?v=impulso-rayos`.

### Cómo se probó

- **Copia de ensayo en el servidor** (`juego/circuito-prueba/`, con los 34 MB de `assets`
  enlazados, no duplicados) contra el backend real, para no tocar el juego en uso. Borrada al
  terminar.
- Carrera completa en esa copia: los rayos se ven flotando, el aviso "¡Turbo recargado!"
  aparece al recogerlos, y **"Volver al menú" del podio navega** al menú de juegos.
- La física se comprobó **fuera del navegador**, corriendo `sim.mjs` en node: la rampa da
  `impulso` y sube la velocidad de 23 a 37 sin tocar la carga; el rayo da `energia` y sube la
  carga sin acelerar; pasar por el carril equivocado no recoge nada.
- Los puntajes de prueba se borraron de `cc_puntajes` (quedan solo los ocho jugadores reales).

### Lista de subida (hecha por SSH, en este orden)

| Archivo local | Destino (`app/juego/circuito/`) | Clase |
| --- | --- | --- |
| `.htaccess` | igual | OBLIGATORIO (primero: fija la caché de los `.mjs`) |
| `sim.mjs`, `world.mjs`, `main.mjs` | igual | OBLIGATORIO |
| `circuito.css` | igual | OBLIGATORIO |
| `index.html` | igual | OBLIGATORIO (último: trae el sello nuevo) |

Respaldo: `~/respaldos/circuito-antes-impulso-20260910.tar.gz`. Las 10 URL: sin cambio.

🔴 **El Circuito no tiene copia local en esta máquina.** Vino de Codex y PROD es el único
lugar donde vive. Para trabajarlo se baja con SFTP a `scratchpad/circuito-trabajo/`. Si Codex
vuelve a exportarlo, **estos cambios se pierden**: hay que pasarle esta sección.

### No probado

- **El iPhone del usuario.** Las dos causas posibles quedaron cerradas (la excepción que
  cortaba la navegación y el botón bajo la barra del navegador), pero no tengo un iPhone.
- Que el empujón se sienta bien para un niño: 14 de velocidad extra durante 1,5 s es lo que
  se eligió; el número está en `IMPULSO_S` y en el `+ 14` de `sim.mjs`.
- (Cerrado) El usuario había mencionado "un icono arriba que parece un freno de mano".
  Luis confirmó que **se confundió**: arriba solo están la nota musical y el botón de PAUSA,
  y ninguno cambia.
