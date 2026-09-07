# Handoff Claude → Codex — pulir la revista del Álbum Recuerdo + video promocional

Fecha: 2026-08-11. Encargo de Luis (vía chat con Claude).

## ⚠️ Rama y ubicación — importante

Este encargo **no es del worktree de Hielo/Carreras**
(`.worktrees\cumplebooth-protagonista`, rama `codex/frozen-invitation-parity`).
Álbum Recuerdo vive en el checkout principal:

```
C:\wamp64\www\automatiza-tech\CumpleBooth
```

rama **`feat/album-recuerdo`**. Ahí ya están comiteadas las 5 fases
(`Album Recuerdo fase A` hasta `fase E`, la última es
`8e4daec feat(cumpleclick): Album Recuerdo fase E - cartel QR, docs y manifiesto`).
No trabajar esto en el worktree de hielo — son ramas y carpetas distintas.

## Qué YA existe (no partir de cero)

La "revista" con efecto de hojear ya está construida, no es un prototipo:

- `src/album/FlipBook.jsx` — motor de pase de página en React + CSS 3D puro
  (portado de un prototipo de Umbría), sin librería externa. Ya tiene: arrastre
  con el dedo/mouse (`pointerdown/move/up`), sombra de "curl" en la esquina que
  se dobla, avance con teclado (flechas, PageUp/Down, Home, Escape), modo una
  página vs. doble página (cambia solo bajo 820px vía `matchMedia`), ventana de
  montaje (no arma las 100 páginas de una), `prefers-reduced-motion` respetado.
- `src/album/AlbumPage.jsx` — layouts (portada, foto, mensaje, video, mosaico, cierre).
- `src/album/main.jsx` + `album.html` — entrada Vite propia (no carga el
  bundle del kiosco).
- `public/admin/album.php` — curaduría (aprobar/ocultar/reordenar/portada).
- `public/subir.php` + `album-intake.php` — carga del invitado.
- Ver `docs/ALBUM-RECUERDO-PROPUESTA.md` (diseño completo, fases A-E) y
  `docs/ALBUM-RECUERDO-PRUEBAS.md` (pasos de validación manual ya usados).

Verificado antes (por el propio historial, `docs/FTP-MANIFEST.md` sección
"Delta local — Álbum Recuerdo"): `npm test` 96/96,
`tests/backend/album.php` 157 checks, `check-dist-parity.php` exit 0
(289 archivos). **No desplegado a PROD.**

## Qué falta — el encargo real

### 1. Responsive de verdad en los 3 formatos (P1)

Revisar `src/album/album.css` — hoy casi no tiene `@media` propios (solo
`prefers-reduced-motion`); el cambio de una/dos páginas lo resuelve JS a los
820px, pero el resto del layout (controles, tipografía, header, cartel) no
tiene breakpoints explícitos para mobile/tablet/PC. Objetivo:

- Se ve bien y usable en celular (portrait Y landscape), tablet y
  PC/notebook — sin overflow horizontal, sin controles tapados, sin texto
  cortado.
- **Nuevo, pedido explícito de Luis:** en celular, la revista se disfruta
  mejor en horizontal (más ancho para ver el pliego de dos páginas). Agregar:
  - Un aviso/overlay que aparece SOLO en mobile-portrait (`orientation:
    portrait` + ancho típico de celular) invitando a girar el teléfono —
    ícono de rotación + texto corto tipo "Gira tu celular para una mejor
    experiencia". Debe desaparecer solo al detectar landscape
    (`matchMedia('(orientation: landscape)')` + evento `resize`/`orientationchange`,
    mismo patrón defensivo que ya usa `FlipBook.jsx` para `singlePage`).
    No debe bloquear el uso en portrait — es una sugerencia, no un gate: si
    el usuario ignora el aviso y sigue en portrait, la revista debe seguir
    funcionando (modo una página).
  - No inventar una librería para esto: mismo enfoque CSS+JS vanilla que ya
    usa el resto del álbum.

### 2. QA visual real (P1, ya estaba en el plan de Fase D y no hay evidencia de que se haya hecho en dispositivo)

Del plan original (`docs/ALBUM-RECUERDO-PROPUESTA.md` §11, Fase D): "Chrome
real en móvil y desktop; sin WebGL; `prefers-reduced-motion`; álbum de 100+
fotos; consola limpia". Confirmar esto de verdad (capturas o evidencia
equivalente), no solo que los tests automatizados pasen.

### 3. Imágenes de prueba con Higgsfield (autorización de Luis pendiente de confirmar cupo/costo)

Generar un set de fotos de prueba (no reales, no de menores identificables —
mismo criterio de camuflaje/seguridad que el resto del proyecto: nada de
material sensible) para poblar un álbum demo local y así:

- Probar la revista con contenido realista (mezcla de fotos + al menos un
  video corto + un mensaje de texto, para ejercitar los layouts de
  `AlbumPage.jsx`).
- Servir de material para el punto 4.

Antes de generar, confirmar costo con `get_cost` como ya es la práctica en
este proyecto, y avisar el total antes de gastar créditos si no está ya
autorizado en el chat con Luis.

### 4. Video promocional/educativo del servicio (depende del punto 3)

Un video corto (formato Reel, 9:16, mismo criterio de marca que
`CLAUDE.md`/`AGENTS.md` del monorepo: logo AT discreto esquina superior
izquierda, texto en pantalla en español de Chile) que muestre/explique que
CumpleClick también ofrece este servicio de "álbum recuerdo tipo revista".
Puede reusar el patrón ya probado de video explicativo con ffmpeg (0 créditos
de render, ver `docs/OPENCODE-HANDOFF-EXPLICATIVO-CUMPLECLICK.md` como
referencia de formato/piezas), grabando la revista real funcionando en
Chrome (como ya se hizo para otros videos de este proyecto) en vez de
animarla de cero.

## Reglas (las mismas de siempre en este proyecto)

- No tocar Show 3D, `themeFlow.js`, Carreras/Rayo, ni el worktree de hielo.
- No generar nada con nombres/franquicias/personajes protegidos ni rostros
  reales identificables en las imágenes/video de prueba.
- No commit/push/merge/deploy sin autorización expresa de Luis.
- No gastar créditos Higgsfield sin confirmar costo primero.
- `npm test`, `npm run build`, `check-dist-parity.php` al cerrar, mismo rigor
  de evidencia que los handoffs anteriores de este proyecto.
- Reportar hallazgos que no se corrijan, igual que en las auditorías previas.

## Nota de Claude sobre Higgsfield

Mi conexión a Higgsfield en esta sesión está desconectada (pide
reautorización) — por eso este encargo queda para Codex, que sí tuvo acceso
funcionando hoy mismo (candidatos de Hielo). Si Codex tampoco tiene acceso,
avisar a Luis en vez de improvisar con otra herramienta sin confirmar.
