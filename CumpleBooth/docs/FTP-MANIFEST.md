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
