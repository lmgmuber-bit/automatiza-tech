# Handoff entre sesiones de Claude — Auditoría y mejora UX Perfil del protagonista

Sesión: **claude · Auditoría y mejora UX Perfil del protagonista (CumpleClick)**
Fecha: 2026-08-12
Proyecto: `C:\wamp64\www\automatiza-tech\.worktrees\cumplebooth-protagonista\CumpleBooth`
Rama: `codex/frozen-invitation-parity` (working tree sucio, mezclado con trabajo pendiente de Codex — no asumir que todo lo modificado es de esta sesión)
Último commit real: `c13abea`. **Nada de esta sesión está comiteado.**

## 0. Contexto de arranque

Continué el handoff `docs/CLAUDE-HANDOFF-CAMPANA-INVITACIONES-CUMPLECLICK-2026-08-12.md` (de Codex, sobre la campaña promocional Básico/Full). Luis pidió ir resolviendo bugs puntuales de UX que encontró probando las invitaciones en vivo, uno detrás de otro. Todo lo de abajo es reactivo a reportes suyos en tiempo real, no un plan armado de antemano.

## 1. Qué se hizo, en orden

### 1.1 Servidor local y fixes de entorno
- Levanté PHP built-in server (`127.0.0.1:8092`) contra `storage/event-profile-demo/cumpleclick-demo.sqlite`.
- Encontré y corregí 2 env vars faltantes que rompían el server local: `CC_PUBLIC_BASE_URL` y `CC_APP_HMAC_KEY` (ninguno de los dos es secreto real, son valores dummy locales). Sin ellos, `descargar-invitacion.php` tiraba fatal error.

### 1.2 Narración de Alice — texto y audio
- **Bug real encontrado**: `saludo-rayo-mcqueen-v3.mp3` (narración "Rayo cruza la meta", Carreras) nunca sonaba porque el código le quita el sufijo `-v3` al nombre del VIDEO para buscar su MP3, pero el archivo se había guardado CON el sufijo. Renombrado a `saludo-rayo-mcqueen.mp3` (`git mv`). Verificado por HTTP.
- **Narración de cierre por género** (pedido de Luis): antes había un solo audio compartido ("Toca aquí para ver la invitación a la fiesta") que ya no calzaba con la pantalla real (botones de compartir + CTA "Conoce al cumpleañero/a"). Se separó en:
  - `public/assets/audio/narracion-final-nino.mp3` / `-nina.mp3` / `-final.mp3` (neutro, fallback) — suenan en la sección "Guarda y comparte", elegidos por `cc_invitations.birthday_person_gender`.
  - `public/assets/audio/narracion-playlist-final.mp3` — audio APARTE con el texto viejo restaurado, para el fin del recorrido automático de personajes (momento distinto, antes se pisaban).
  - Generados con ElevenLabs, voz Alice (`voice_id Xb7hH8MSUJpSbSDYk0k2`), modelo `eleven_multilingual_v2`. La API key se leyó desde `C:\Users\luis_\OneDrive\Documentos\APIS KEy\APIS KEY.txt` solo en memoria (nunca escrita a archivo/log), con autorización explícita de Luis en el chat.
- **Migración nueva**: `database/migrations/009_invitation_gender.php` (+`.down.php`) — agrega `cc_invitations.birthday_person_gender` (`m`/`f`/NULL). Corrida solo contra la sqlite local de demo.
- Selector de género agregado en `public/admin/invitations.php` (alta y edición), wireado en `public/lib.invitations.php` (`cb_create_invitation`, `cb_update_invitation`) y `public/invitacion.php` (elige el mp3 correcto).

### 1.3 Auto-scroll al terminar de hablar Alice
- Nuevo comportamiento en `public/assets/invitation.js`: al terminar la narración de inicio, la página avanza sola hasta la siguiente sección (no requiere que el usuario encuentre el botón "Desliza para seguir"). Vale para Scroll y Automática, ambas temáticas.
- **Bug de carrera corregido**: en Automática, si el video de entrada terminaba antes que Alice, el avance disparaba de inmediato y la narración de la SIGUIENTE sección se pisaba con la de Alice. Fix: espera a que terminen las DOS (`introNarrationEnded` + scroll no bloqueado), gane quien gane la carrera. Verificado con las dos secuencias posibles, en Carreras e Hielo.
- **Endurecido** (reporte de Luis "no salieron los personajes, no hizo auto-scroll"): el cálculo del destino del scroll pasó de "altura calculada del hero" a "el elemento real que sigue en el DOM" (`heroSection.nextElementSibling`) — más robusto si el layout no está 100% asentado.
- **Limitación de la herramienta de prueba descubierta**: el panel de navegador de Claude Code no compone fotogramas reales, así que ni `scrollTo({behavior:'smooth'})` ni `IntersectionObserver` disparan ahí — confirmado con una prueba aislada (un observer nuevo, sin relación con el código de la invitación, tampoco disparó). No se pudo verificar 100% en este entorno si el trigger de la playlist de personajes (`runPlaylist`, gateado por `IntersectionObserver` en `.inv-playlist`) funciona con el nuevo destino — geométricamente el scroll llega exacto (0% de margen, top:0 tras el scroll), pero falta confirmación en dispositivo real.
- Corregí de raíz un problema de caché que venía arrastrando: `invitation.js` se servía con versión fija `?v=5/6/7` a mano. Ahora `invitacion.php` la calcula con `filemtime()`, automático.

### 1.4 Foto del protagonista ("Conoce a Vicente/Isidora")
- Antes mostraba solo la inicial ("V"/"I"). Adjunté fotos reales usando el flujo real (`cb_event_profile_register_media`, la misma función que usa el admin al subir por HTTP) vía script local `storage/event-profile-demo/attach-photos.php` (gitignorado, no es parte del feature, solo la herramienta que until lo usé para poblar la demo).
- Fuente: `design/explicativo/ia/IMG-08-nino-camara-limpio.png` (Vicente) e `IMG-09-nina-camara.png` (Isidora) — fotos IA de marketing ya existentes en el repo, no de niños reales.
- Verificado por HTTP: `event-profile-media.php` sirve las imágenes reales (200, bytes/dimensiones exactas).

### 1.5 Delegación a OpenCode
- Luis grabó 2 pantallazos completos (`C:\Users\luis_\Videos\Captures\Invitacion cars.mp4` e `Invitacion Frozen.mp4`, ~89s c/u, 1920×1032, landscape con el móvil emulado adentro).
- Armé `docs/OPENCODE-HANDOFF-POSTPRODUCCION-REEL-CUMPLECLICK-2026-08-12.md`: instrucciones completas para que OpenCode (Luis usó `kimi-k3`, se quedó sin tokens en Codex) revise esas grabaciones, arme el EDL del Reel promocional 30-40s, y limpie el logo falso de los 3 videos de Higgsfield pendientes (K-Pop/Tropical/Familia Canina, en `storage/event-profile-demo/logo-review-20260812/`). Ese trabajo todavía no arrancó cuando cerré.

### 1.6 Lista de entrega FTP
Ya se la di a Luis en el chat de esta sesión (no repetida acá completa por espacio) — resumen: migración `009_invitation_gender` PRIMERO (por SSH, `php scripts/migrate.php`), después `lib.invitations.php` → `invitacion.php`/`admin/invitations.php` → `invitation.js` → los 5 audios (incluye borrar el mp3 viejo renombrado). Nada en PROD todavía. Excluí a propósito `invitation.css` y los 4 intros de video (son de Codex, sin revisar por mí esta sesión).

## 2. Verificación hecha

- `npm test`: 101/101 en cada punto de corte.
- `php -l` en todos los PHP tocados: limpio.
- `node --check` en `invitation.js`: limpio.
- `npm run build`: corrido, sin errores, confirma que `public/` se copia tal cual a `dist/` (sin hash) y que el bundle React (kiosco/álbum/cartel) es independiente de estos cambios.
- `graphify update .`: corrido después del primer bloque de cambios de código.

## 3. Pendiente / abierto para quien retome

1. **Confirmar en dispositivo real** que el auto-scroll + arranque de la playlist de personajes funciona en Automática (no se pudo verificar 100% en el panel de Claude Code, ver 1.3).
2. Confirmar con Luis si el botón de mute alguna vez realmente falló en Automática — se investigó, no se pudo reproducir, pero Luis no llegó a confirmar si el problema era ese o el auto-scroll.
3. OpenCode: revisar si ya avanzó con `docs/OPENCODE-HANDOFF-POSTPRODUCCION-REEL-CUMPLECLICK-2026-08-12.md`.
4. Nada de esto se comitea, se sube a PROD ni se publica sin autorización expresa de Luis.
5. Claims sin verificar por mí (reportados por otro agente/sesión a Luis, quien me los reenvió): backup en `origin/fix/form-agenda-calendario-whatsapp`, Álbum Recuerdo respondiendo 200 en PROD, retiro de tokens hardcodeados en `scripts/qa-album-visual.mjs`/`record-album-promo.mjs`. Esos archivos no existen en este worktree — es de otra rama/checkout, no lo verifiqué.
