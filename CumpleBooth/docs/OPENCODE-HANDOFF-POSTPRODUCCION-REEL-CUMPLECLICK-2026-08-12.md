# Handoff a OpenCode — Postproducción Reel CumpleClick

Fecha: 2026-08-12
Responsable del traspaso: Claude
Proyecto: `C:\wamp64\www\automatiza-tech\.worktrees\cumplebooth-protagonista\CumpleBooth`
Rama activa: `codex/frozen-invitation-parity` (working tree sucio, NO limpiar ni resetear)

## 0. Reglas estrictas (no negociables)

- No hacer commit, push, merge, deploy ni publicación sin autorización expresa de Luis.
- No generar video/imagen/audio ni consumir créditos (Higgsfield, ElevenLabs, cualquier API paga) sin mostrar antes modelo, costo y prompt/texto exacto, y recibir aprobación expresa.
- No tocar Show 3D ni la lógica histórica de Carreras/Rayo.
- No incluir tokens de invitación, credenciales ni datos sensibles en Git, docs o entregables.
- No nombrar franquicias ni personajes protegidos en prompts generativos; describir solo estética original.
- Logo AT / CumpleClick: ver contrato exacto en la sección 3 de este documento. Nunca lo aproximes a ojo.
- Antes de tocar cualquier archivo, revisar `git status` — el working tree tiene cambios de varias tareas simultáneas, no asumir que todo lo modificado es tuyo.

## 1. Qué hay que hacer

Luis grabó la pantalla de las dos invitaciones (Carreras y Hielo) navegando en vivo. El objetivo es:

1. Revisar esas grabaciones.
2. Proponer una EDL/storyboard con tiempos exactos (qué segundo de qué archivo va en qué bloque del Reel).
3. Limpiar el logo falso de los 3 videos de Higgsfield pendientes (Familia Canina, Tropical, K-Pop).
4. Armar un primer corte de baja compresión del Reel maestro de 30-40s.
5. Mostrárselo a Luis y esperar su aprobación antes de cualquier exportación final o integración a la invitación real.

No cerrar ni dar por publicable nada sin que Luis lo revise visualmente primero.

## 2. Material disponible

### 2.1 Grabaciones de pantalla (nuevas, sin procesar)

Carpeta: `C:\Users\luis_\Videos\Captures\`

| Archivo | Duración | Specs | Contenido esperado |
|---|---|---|---|
| `Invitacion cars.mp4` | 89.27s | 1920×1032, h264, 60fps, audio AAC | Recorrido completo invitación Carreras (Vicente) |
| `Invitacion Frozen.mp4` | 88.78s | 1920×1032, h264, 60fps, audio AAC | Recorrido completo invitación Hielo (Isidora) |
| `Invitación de Vicente · CumpleClick - Google Chrome 2026-08-12 20-55-50.mp4` | corta (~8.3 MB) | igual | posible toma descartada o de respaldo, revisar si sirve |
| `Invitación de Vicente · CumpleClick - Google Chrome 2026-08-12 20-56-03.mp4` | corta (~10.2 MB) | igual | ídem |

**Importante**: son capturas de escritorio a 1920×1032 (landscape), probablemente con DevTools en modo móvil dentro de la ventana — el contenido real de la invitación (9:16) ocupa solo una parte del frame. Antes de usar cualquier clip en el Reel (formato final 9:16, 1080×1920 o 720×1280) hay que:
- Ubicar con `ffprobe`/inspección visual el rectángulo exacto donde está el viewport móvil dentro del frame de 1920×1032.
- Recortar (`ffmpeg -vf crop=...`) a ese rectángulo antes de escalar a 9:16.
- Si el recorte no da una relación de aspecto limpia 9:16, decírselo a Luis en vez de forzar un crop deformado.

Antes de recortar nada, generar una lámina de contacto para revisar contenido sin gastar tiempo reproduciendo entero:
```powershell
ffmpeg -i "C:\Users\luis_\Videos\Captures\Invitacion cars.mp4" -vf "fps=1/4,scale=320:-1,tile=6x4" -frames:v 1 contact-cars.png
ffmpeg -i "C:\Users\luis_\Videos\Captures\Invitacion Frozen.mp4" -vf "fps=1/4,scale=320:-1,tile=6x4" -frames:v 1 contact-frozen.png
```
(ajustar `tile`/`fps` según duración real; con ~89s y una miniatura cada 4s salen ~22 cuadros, un tile 5x5 alcanza.)

### 2.2 Guion aprobado del Reel maestro (fuente: `docs/CLAUDE-HANDOFF-CAMPANA-INVITACIONES-CUMPLECLICK-2026-08-12.md`, sección 9)

Duración objetivo: 30–40s, 9:16, subtítulos nítidos, CTA a WhatsApp.

| Tiempo | Imagen | Locución/texto |
|---|---|---|
| 0–5 s | Intro IA: sobre abre portal temático | «¿Y si la fiesta comenzara antes del cumpleaños?» |
| 5–10 s | Grabación real del sobre CumpleClick y nombre dinámico | «En CumpleClick, todo empieza con la invitación.» |
| 10–17 s | Plan Básico: Scroll real | «Plan Básico: ellos descubren la historia con el dedo.» |
| 17–24 s | Transición + Plan Full real | «Plan Full: la historia cobra vida y avanza sola.» |
| 24–30 s | Perfil del protagonista | «Y pueden conocer al protagonista antes de llegar.» |
| 30–35 s | Guardar/compartir + vistazo Booth | «Invitación, experiencia y recuerdos en un solo mundo.» |
| 35–40 s | Endcard de marca | «Elige tu experiencia. Reserva tu fecha por WhatsApp.» |

Como ahora hay grabaciones de las DOS temáticas (Carreras y Hielo), decidir con Luis (o proponer y que él confirme) si el Reel es monotemático o alterna clips de ambas para mostrar variedad — no está resuelto, no asumir.

Antes de cerrar el montaje, confirmar con Luis (preguntas ya listadas en el handoff original, sección 9): duración final, tema del hook, CTA/WhatsApp definitivo, si los precios van en este Reel, voz (Alice/humana), música y derechos, y si hace falta una versión corta de 15-20s.

### 2.3 Videos con logo pendiente de limpiar

`storage/event-profile-demo/logo-review-20260812/` (ignorado por Git, local):

| Archivo | Tema | Defecto |
|---|---|---|
| `v0.mp4` | K-Pop | wordmark gris/morado apagado, sin símbolo circular |
| `v1.mp4` | Tropical | wordmark gris/blanco, sin símbolo |
| `v2.mp4` | Familia Canina | texto claro + huella no oficial en 2 cuadros |

Los tres: H.264+AAC, 720×1280, ~15s, 24fps. Lámina de evidencia: `storage/event-profile-demo/logo-review-20260812/top-left-contact.png`.

## 3. Contrato exacto del logo (para las dos limpiezas: máster de invitación y copia promocional)

Logo oficial fuente: `design/logo/logo-icon-wordmark.png`
SHA-256: `f9baa519f06d8d7b74836ee45b684a9818b315c4dc540015b1d9147c29b7f8ca` (verificar antes de usar cualquier copia).

Overlay HTML ya en producción (`public/assets/invitation.css`, `.inv-theme-intro-brand`), replicar estos valores exactos en cualquier logo quemado para la copia promocional:
```
top: ~18px | left: ~16px | width: clamp(86px, 24vw, 118px) → ~118px @720px ancho
opacity: .58 | drop-shadow sutil
```
Posición esquina superior izquierda, pequeño, sutil, translúcido, estable. `Cumple` violeta/morado, `Click` rosado/magenta, símbolo circular oficial conservado. Nunca gris/blanco/plateado/monocromático ni adaptado al color del tema. Nunca sustituir el símbolo por huella/flor/estrella.

Plan de trabajo por video defectuoso (los 3 de la sección 2.3):
1. Máscara localizada sobre el wordmark falso (esquina sup-izq, ~140×60px) + reconstrucción visual suave del fondo.
2. Salida A — **máster de invitación**: limpio, SIN logo quemado (la web ya superpone `brand/cumpleclick-lockup.svg` en HTML). Destino final (recién con aprobación de Luis): `public/themes/<tema>/invitation/intro-invitacion-wow-v1.mp4`.
3. Salida B — **copia promocional**: con el logo oficial (`design/logo/logo-icon-wordmark.png`) quemado con los valores de arriba. Solo para el Reel, no para la invitación.
4. Póster JPG derivado de cada máster A.
5. Normalizar a H.264, yuv420p, AAC estéreo, 720×1280, faststart.
6. Verificar que no quede doble logo en ningún caso.

No mover nada a `public/themes/<tema>/invitation/` sin que Luis apruebe visualmente la escena primero.

## 4. Entorno local para revisar las invitaciones ya construidas

Servidor PHP local (puede que ya esté corriendo; si no, levantarlo así):
```powershell
cd "C:\wamp64\www\automatiza-tech\.worktrees\cumplebooth-protagonista\CumpleBooth"
$env:CC_STORAGE_MODE = 'db'
$env:CC_PDO_DSN = 'sqlite:' + (Resolve-Path 'storage\event-profile-demo\cumpleclick-demo.sqlite')
$env:CC_PUBLIC_BASE_URL = 'http://127.0.0.1:8092'
$env:CC_APP_HMAC_KEY = 'a' * 64
& 'C:\wamp64\bin\php\php8.2.29\php.exe' -S 127.0.0.1:8092 -t public
```
(los tres env vars son obligatorios o tira `RuntimeException`; son valores locales, no secretos reales.)

URLs de referencia (mismos tokens QA usados toda la sesión):
```
Carreras Scroll:     http://127.0.0.1:8092/invitacion.php?t=6a4d2a1f6d5a297c1e79a28055c87407&hero=scroll&capitulos=1
Carreras Automática: http://127.0.0.1:8092/invitacion.php?t=6a4d2a1f6d5a297c1e79a28055c87407&hero=auto&capitulos=auto
Hielo Scroll:        http://127.0.0.1:8092/invitacion.php?t=730e79283517e026f72f255560ac0673&hero=scroll&capitulos=1
Hielo Automática:    http://127.0.0.1:8092/invitacion.php?t=730e79283517e026f72f255560ac0673&hero=auto&capitulos=candidatos
```
No mostrar estos tokens en capturas públicas ni en el Reel final.

## 5. Estado funcional relevante (por si hay que volver a grabar algo)

- Música + Alice + mute funcionan en las 4 combinaciones de arriba.
- Auto-scroll: al terminar de hablar Alice (y, en Automática, también el video de entrada si sigue corriendo), la página avanza sola a la siguiente sección — implementado 2026-08-12, corregido dos veces el mismo día (ver `public/assets/invitation.js`, funciones `autoAdvanceToNextSection`/`maybeAutoAdvance`). Si al mirar la grabación se ve que no avanza solo, puede ser un bug real pendiente — avisar a Luis, no asumir que es la grabación.
- Narración de cierre ahora tiene 3 variantes por género (`narracion-final.mp3`/`-nino.mp3`/`-nina.mp3`) en la sección "Guarda y comparte", y una narración aparte (`narracion-playlist-final.mp3`, texto "Toca aquí para ver la invitación a la fiesta") al terminar el recorrido de personajes en Automática — son dos momentos distintos, no confundirlos si hay que resincronizar audio de la grabación.
- Foto del protagonista: Vicente y la Isidora demo ya tienen foto real en vez de la inicial (ver tarjeta "Conoce a...").

## 6. Próximo flujo recomendado

1. Leer este documento completo.
2. Generar las láminas de contacto de los 2 videos largos (sección 2.1) y mapear qué segundo corresponde a qué beat del guion (sección 2.2).
3. Proponer EDL con tiempos exactos (archivo fuente + in/out) para cada bloque del guion — como texto, antes de tocar ffmpeg de verdad.
4. Recién con esa EDL aprobada por Luis, hacer los recortes/crops y el primer armado de baja compresión.
5. En paralelo o después (no bloquea lo anterior): limpiar logo de los 3 videos pendientes (sección 3), producir máster + promo + póster de cada uno, mostrar a Luis.
6. Nada de esto se integra a `public/themes/.../invitation/` ni se publica sin aprobación expresa.

## 7. Entrega esperada de esta etapa

- EDL/storyboard en texto con tiempos exactos.
- Contact sheets de los 2 videos largos.
- 3 pares de video limpio (máster + promo) + póster, de Familia Canina/Tropical/K-Pop.
- Un primer corte de baja compresión del Reel (o aviso claro de qué falta para poder armarlo).
- Resumen de cambios, sin commit/push/deploy.
