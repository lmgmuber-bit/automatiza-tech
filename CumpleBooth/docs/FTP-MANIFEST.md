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
