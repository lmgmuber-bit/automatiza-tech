# Handoff a OpenCode — videos promocionales CumpleClick (2026-08-05)

**ESTADO (actualizado 2026-08-07 por Claude, tras retomar de OpenCode que
también se quedó sin tokens):** Los videos de las secciones 1 y 2 de abajo
(ad principal + 4 videos por temática) siguen completos, sin cambios. Lo
que **no** está completo:

1. **5 clips IA nuevos para variedad del ad principal** (Higgsfield Seedance,
   sin personajes con copyright — cabina física, familia, fila de niños,
   confeti, compartir por QR). Luis generó 4 de 5 bien; el 5º ("El cierre")
   salió como un carrito/kiosco de dulces en vez de una cabina digital,
   porque el prompt decía solo "kiosk" en inglés y el modelo lo interpretó
   mal. Prompt corregido ya entregado a Luis (especifica
   "a modern digital photo booth kiosk — a tall white stand with a glowing
   touchscreen display"); falta que Luis lo regenere y confirme que salió
   bien. Los 5 prompts originales están más abajo en este documento.
2. **Diseño físico del kiosco/cabina** (proyecto nuevo, en paralelo, hecho
   con OpenCode): Luis quiere fabricar una carcasa a medida para su tablet
   Samsung Tab A7 (upgradeable a Tab S9 FE u otra 10-11"), con pedestal de
   altura regulable e inclinación ajustable. Todo en
   `CumpleBooth/design/kiosco/`:
   - `KIOSCO-PLANO-CARPINTERO.md` — **el que se le pasa al carpintero**,
     lenguaje simple, diagramas ASCII, medidas, lista de compras (~$67.000
     CLP en materiales). Hueco de tablet universal (18×26cm + espuma EVA)
     para que sirva con cualquier tablet 10-11" futura, no solo la actual.
   - `KIOSCO-SPECS-CARPINTERO.md` — versión técnica completa (tablas de
     compatibilidad de tablets, presupuesto detallado ~$130-240 mil CLP
     todo incluido). Referencia si el plano simple no alcanza.
   - `kiosco-3d-preview-v3.html` — preview 3D interactivo (Three.js, abrir
     en Chrome), la versión vigente; v1 y v2 tienen bugs conocidos (tablet
     no visible, altura mal calculada) — no usarlas de base para más
     cambios, partir de v3.
   - 6 prompts para generar renders realistas del kiosco (ChatGPT/Gemini)
     con el logo real como referencia — están en el historial de la sesión
     de OpenCode, no en archivo; si Luis los pide de nuevo, están basados en
     las medidas de `KIOSCO-SPECS-CARPINTERO.md` sección 2.
   - Este es un proyecto de **hardware/merchandising**, no toca el código de
     CumpleBooth ni sus dimensiones internas (la app sigue siendo 1080×1920
     dentro de la tablet, sin importar la carcasa externa).

Escrito originalmente por Claude al quedarse sin tokens en la sesión que
produjo los videos base.
Esto **no toca el repo ni PROD**: es trabajo de marketing (ads para WhatsApp),
100% generado en la carpeta temporal de una sesión de Claude Code. Nada de esto
se ha commiteado ni se va a commitear — es contenido de campaña, no código.

## ⚠️ Antes de nada

Todos los archivos viven en una carpeta **temporal ligada a una sesión de Claude
Code específica**:

```
C:\Users\luis_\AppData\Local\Temp\claude\C--wamp64-www-automatiza-tech\0e982820-64ad-4873-a1c5-9b76d02715b9\scratchpad\
```

Esa carpeta puede desaparecer si la sesión se limpia. **Si Luis quiere que
OpenCode continúe, lo primero es confirmar que la carpeta sigue existiendo**, y
si Luis quiere conservar los resultados de forma permanente, copiar `build/*.mp4`
a otro lugar (por ejemplo `C:\videos CumpleClick\`) antes de seguir editando.

## Qué se hizo (contexto completo)

1. **Video ad principal** (`build/cumpleclick-ad-v5.mp4`, ~1:27): mezcla los 5
   clips IA que Luis generó en Higgsfield Seedance (`C:\videos CumpleClick\1-5.mp4`)
   con metraje real de las 6 temáticas (saludo/revelación/despedida de personajes,
   juegos reales, screenshots de foto+diploma), narrado por ElevenLabs "Alice",
   con marca de agua CumpleClick superpuesta solo en el metraje que no trae logo
   propio (los 5 clips IA NO llevan marca de agua, ya traen su logo horneado).
2. **4 videos individuales por temática** (Hielo, Carreras, K-Pop, Bluey/Familia
   Canina), armados **100% con grabaciones reales que Luis hizo con el kiosco**
   (`C:\Users\luis_\Videos\Captures\*.mp4`, 4 archivos ~2min cada uno, capturas de
   pantalla completa del navegador). Estructura de cada uno: bienvenida → ruleta
   girando y revelando personaje → juegos reales → foto/diploma+QR real → mismo
   cartel de cierre CTA. Versión vigente: **`cumpleclick-{tema}-v4.mp4`** (hielo,
   carreras, kpop, bluey). Versiones v1-v3 son iteraciones intermedias, ignorarlas.
   **Excepción Bluey**: corregido a v5 (2026-08-07) porque dos cortes de "juego"
   en v4 (t=88s y t=93s) caían dentro del conteo/captura real de foto y mostraban
   la pantalla gris de carga. El juego "Atrapa los globos" solo dura de t≈76 a
   t≈83s en la grabación de Luis; los cortes de ese juego deben mantenerse dentro
   de esa ventana. Hielo, Carreras y K-Pop siguen en v4 sin cambios.

## Decisiones y correcciones ya aplicadas (no las repitas)

- El marco de la foto **no debe quedar vacío ni mostrar el gris de "cargando"**.
  Carreras y Bluey usan capturas reales ya existentes en
  `design/screens/carreras-screen-06-preview.png` (niño "Mateo") y
  `design/screens/screen-06-preview.png` (niña "Sofía", pese al nombre genérico
  del archivo, es la versión Bluey). Hielo y K-Pop **no tienen captura real
  equivalente** — Claude compuso a mano (aproximación, no pantallazo real del
  compositor) la cara de Sofía/Mateo sobre el fondo+marco real de esa temática.
  Assets: `frames/hielo-composited-sofia.png`, `frames/kpop-composited.png`.
  Si se necesita un tercer/cuarto niño o mayor fidelidad, lo correcto es que
  Luis genere la captura real desde el kiosco (como hizo con los 4 videos base)
  en vez de seguir aproximando con canvas.
- El tramo "diploma" debe durar bastante (Luis pidió más pausa ahí) — en v4 dura
  ~5.5s por temática, arrancando ya en la pantalla limpia "tu foto está lista"
  con QR, nunca en el placeholder gris. Timestamps limpios verificados por tema
  (dentro del capture de cada uno): hielo=101.5s, carreras=120.0s, kpop=130.0s
  (¡ojo! a los 128s todavía sale gris en K-Pop, hay que empezar más tarde),
  bluey=104.0s.
- Los juegos deben mostrarse variados: Carreras y K-Pop tienen 3 minijuegos
  distintos disponibles en su grabación; Hielo y Bluey solo tienen 2 tipos
  (no hay un tercero grabado), así que se usan 2 momentos distintos de cada uno
  para dar sensación de variedad.
  **Bluey "Atrapa los globos"**: solo dura de t≈76 a t≈83s en la grabación.
  Cualquier corte de "juego" fuera de esa ventana cae en el conteo/captura real
  de foto con pantalla gris. Corregido en v5.
- **El juego premium 3D (`mundo3d`) no aparece en ninguna de las 4 grabaciones**
  de Luis (corrieron en plan Booth, no Full). No hay ningún screenshot/video de
  ese juego en todo el repo. Si Luis lo vuelve a pedir, la única vía es que él
  mismo lo grabe (como hizo con los 4 capturas) — un intento de navegación en
  vivo por Claude para llegar hasta ahí ya se quedó pegado sin error una vez
  antes en esta misma sesión (flujo cámara→ruleta→captura→bonus es largo y fue
  poco confiable en automatización).

## Cómo reproducir/editar (scripts en `scratchpad/`)

Todo usa el ffmpeg portátil de `imageio_ffmpeg` (no hay que instalar nada):
```
C:\Users\luis_\AppData\Local\Packages\PythonSoftwareFoundation.Python.3.13_qbz5n2kfra8p0\LocalCache\local-packages\Python313\site-packages\imageio_ffmpeg\binaries\ffmpeg-win-x86_64-v7.1.exe
```
Ejecutar los scripts con `python` (no `python3` — en esta máquina `python3` con
rutas `/c/...` da `FileNotFoundError` en directorios que sí existen; usar rutas
Windows con backslash y el intérprete `python`).

- `build_final.py` / `rebuild_beat3.py` / `add_ruleta.py` → arman el ad principal
  (`cumpleclick-ad-v5.mp4`).
- `build_temas.py` → arma la v1 de los 4 videos por temática desde cero.
- `refine_temas.py` → v2 (más juegos + foto real en vez de vacía).
- `fix_hielo.py` / `fix_kpop.py` → correcciones puntuales de un solo tema.
- `fix_bluey.py` (o rebuild inline, 2026-08-07) → Bluey v5: corrige cortes de
  juego que mostraban pantalla gris de carga. Ventana real de "Atrapa los
  globos": t≈76-83s.
- `extend_temas.py` → v4 base para Hielo/Carreras/K-Pop (juegos y diploma más
  largos). **Este es el que hay que tomar como base si se pide otro ajuste de
  timing para esos 3 temas** — es el más completo y ya trae todas las
  correcciones anteriores incorporadas.

Recorte del panel del kiosco dentro de las grabaciones de pantalla completa de
Luis (1920×1140, incluye la barra de Chrome): `crop=555:980:682:165` antes de
normalizar a 1080×1920. Si Luis graba con otra resolución/posición de ventana,
este crop hay que volver a calibrarlo (comparar un frame extraído contra el
recorte, como se hizo en esta sesión).

## Voz (ElevenLabs "Alice", NO Higgsfield)

Luis rechazó la voz nativa de Higgsfield ("suena a gringa") y pidió Alice real
de ElevenLabs. Método que funciona:

- `voice_id`: `Xb7hH8MSUJpSbSDYk0k2`, `model_id`: `eleven_multilingual_v2`
  (obligatorio para acento en español correcto).
- La API key **ya está guardada como variable de entorno de Windows (User)**
  `ELEVENLABS_API_KEY` — NO pedirla a Luis de nuevo, NO leerla del archivo de
  credenciales del vault para esto. Se obtiene así, en la MISMA cadena de
  comando que la usa (el estado de shell no persiste entre llamadas):
  ```
  KEY=$(powershell.exe -NoProfile -Command "[System.Environment]::GetEnvironmentVariable('ELEVENLABS_API_KEY','User')") && ELEVENLABS_API_KEY="$KEY" python script.py
  ```
- **Nunca imprimir, loguear ni escribir esa key en ningún archivo** (ni siquiera
  temporal fuera del env var). Nunca hacer `echo` de la variable.
- El cuerpo JSON debe escribirse con Python (`json.dumps(..., ensure_ascii=False)`,
  UTF-8) — pasarlo inline por `curl -d` en git-bash rompe los acentos.
- Todas las líneas ya generadas están en `build/voz-*.mp3` (ad principal) y
  `build/voz-tema-*.mp3` (videos por temática). Antes de generar audio nuevo,
  **redactar el guion y esperar aprobación explícita de Luis** — es una regla
  que él estableció varias veces en esta sesión, no asumirla.

## Riesgo de copyright (ya evaluado y aceptado por Luis, no re-litigar)

Los personajes (Elsa, Rayo McQueen, Bluey, etc.) aparecen de cerca y con nombre
en varios clips. Luis evaluó esto explícitamente y aceptó el riesgo **porque la
distribución es privada por WhatsApp a sus contactos, no publicidad paga
pública**. Si se plantea subir esto a un canal público/pago, hay que volver a
plantearle el riesgo — no asumir que la aprobación anterior se extiende a un
canal distinto.

## Otros entregables de esta sesión

- `frames/album-graphic.png` — infografía Álbum de Recuerdos Digital (beat 10
  del ad principal).
- `frames/cta-card.png` — cartel de cierre con WhatsApp +56 9 7494 0070 (número
  real confirmado por Luis, no inventar otro).
- Textos de WhatsApp Status y de grupo de apoderados ya redactados y aprobados
  por Luis (no están en archivo, quedaron solo en el chat — si se necesitan de
  nuevo, pedírselos a Luis o revisar el historial de la sesión).

## Clips 6-10 (Higgsfield, variedad para el ad principal, 2026-08-07)

Genéricos, sin personajes con copyright (cabina física + familia + fiesta),
para sumar variedad al ad principal sin arrastrar el riesgo de copyright de
los clips de temáticas. Logo real subido como referencia en Higgsfield
(`design/logo/logo-icon-wordmark.png` o `logo-transparent.png`), con esta
línea al final de cada prompt: *"The provided reference image is the
CumpleClick logo. It must appear as a very small, subtle, translucent
watermark in the top-left corner of the frame, like a professional TV
watermark. Never center it, never enlarge it."*

1. **El problema**: niño solo en fiesta aburrida, ilumina la cara al ver algo.
2. **La magia**: personaje 3D emerge de la pantalla con chispas doradas.
3. **La fiesta**: niños bailando/jugando alrededor del kiosco, confeti.
4. **El recuerdo**: madre en el sofá viendo fotos de la fiesta en su celular.
5. **El cierre** — ⚠️ **prompt original fallaba** ("kiosk" se interpretó como
   carrito de dulces). Versión corregida, especifica que es una cabina
   digital: *"...the camera slowly pulls back revealing that everything is
   happening around a modern digital photo booth kiosk — a tall white stand
   with a glowing touchscreen display showing colorful party characters..."*
   Confirmar con Luis si ya la regeneró y salió bien antes de usarla.

Una vez estén los 5 buenos, van al ad principal (`build_final.py` o una
nueva iteración) igual que se hizo con los 5 originales — sin marca de agua
extra porque ya traen el logo horneado por Higgsfield.
