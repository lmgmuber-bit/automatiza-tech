# Handoff a Codex — voz Alice, despedida de Héroes y peso de los MP4

Escrito por Claude el 2026-08-03. Todo lo de abajo está **medido**, no supuesto:
existencia en disco, duración, dimensiones y presencia de pista de audio se
verificaron reproduciendo cada archivo en Chrome real contra el Apache local.

## Por qué esto va a Codex y no lo hizo Claude

**En esta máquina no hay `ffmpeg` ni `ffprobe`.** Se buscó en PATH, `where.exe`,
`Program Files`, `Program Files (x86)`, `%LOCALAPPDATA%\Programs`, `scoop\shims`,
`WindowsApps`, `node_modules` y `%APPDATA%\npm`: no está en ninguno. Sin ffmpeg
no se puede muxear voz ni recomprimir, que es justamente lo que falta. Codex sí
lo tiene según los handoffs anteriores.

Luis confirmó que **la voz de la revelación es "Alice" de ElevenLabs**, diciendo
"Cargando tu foto", con el video de fondo. Ya está así en K-Pop y Hielo.

## Estado medido de los 12 videos (6 temáticas completas)

| Temática | Tipo | Archivo | Disco | MB | Duración | Dimensiones |
|---|---|---|---|---:|---:|---|
| carreras | revelación | `revelacion-carreras.mp4` | sí | 5,45 | 5,04 | 720×1280 |
| carreras | despedida | `despedida-carreras.mp4` | sí | 5,31 | 5,04 | **724×1268** |
| familia-canina | revelación | `revelacion-familia-canina.mp4` | sí | 6,83 | 5,04 | 720×1280 |
| familia-canina | despedida | `despedida-familia-canina.mp4` | sí | 7,36 | 5,04 | 720×1280 |
| tropical | revelación | `revelacion-tropical.mp4` | sí | 4,86 | 5,04 | 720×1280 |
| tropical | despedida | `despedida-tropical.mp4` | sí | 0,42 | 5,00 | 720×1280 |
| hielo | revelación | `revelacion.mp4` | sí | 0,91 | 5,03 | 720×1280 |
| hielo | despedida | `despedida-hielo.mp4` | sí | 0,73 | 5,04 | **716×1284** |
| kpop | revelación | `revelacion-kpop.mp4` | sí | 7,64 | 5,04 | 720×1280 |
| kpop | despedida | `despedida-kpop.mp4` | sí | 6,95 | 5,04 | **716×1284** |
| heroes | revelación | `revelacion-heroes.mp4` | sí | 8,02 | 5,04 | 720×1280 |
| heroes | despedida | `despedida-heroes.mp4` | **NO** | — | — | — |

Los 11 que existen **tienen pista de audio** (29–46 KB decodificados en 2,6 s de
reproducción). Lo que NO se pudo determinar desde acá es **qué dice** cada pista:
sin ffmpeg no hay forma de extraer ni escuchar el audio. Ese es el primer trabajo
de Codex.

## Auditoria de audio completada por Codex (2026-08-03)

Se instalo FFmpeg 8.1.1 Essentials localmente y se extrajeron las 11 pistas a WAV
PCM mono de 24 kHz en una carpeta temporal. La clasificacion de frases se valido
sin subir audio usando el reconocedor local de Windows es-ES con gramatica cerrada.
No se modifico ningun MP4.

| Tematica | Archivo | Clasificacion | Evidencia |
|---|---|---|---|
| carreras | revelacion-carreras.mp4 | solo musica/ambiente | Kling confirmado por Claude; no reconoce voz. |
| carreras | despedida-carreras.mp4 | voz Alice | "Gracias por venir, vuelve pronto" (0,69). |
| familia-canina | revelacion-familia-canina.mp4 | solo musica/ambiente | Kling confirmado por Claude; no reconoce voz. |
| familia-canina | despedida-familia-canina.mp4 | voz Alice | "Gracias por venir, vuelve pronto" (0,69). |
| tropical | revelacion-tropical.mp4 | voz Alice | "Cargando tu foto" (0,62). |
| tropical | despedida-tropical.mp4 | voz Alice | "Gracias por venir, vuelve pronto" (0,69). |
| hielo | revelacion.mp4 | voz Alice | Ya documentada como Alice; reconocedor local no obtuvo frase concluyente. |
| hielo | despedida-hielo.mp4 | voz Alice | "Gracias por venir, vuelve pronto" (0,72). |
| kpop | revelacion-kpop.mp4 | voz Alice | "Cargando tu foto" (0,49). |
| kpop | despedida-kpop.mp4 | voz Alice | Mezclada 2026-08-03 desde historial Alice: "Adios! Gracias por venir. Vuelve pronto!". |
| heroes | revelacion-heroes.mp4 | voz Alice | "Cargando tu foto" (0,54). |
| heroes | despedida-heroes.mp4 | inexistente | Heroes sigue bloqueado: no generar sin aprobacion de Luis. |

Resultado: no hay MP4 realmente mudo. Carreras y Familia Canina ya incorporan
Alice diciendo "Cargando tu foto". K-Pop ya incorpora despedida Alice. No se
genero audio nuevo: se reutilizo historial existente.
## Tareas 5 y 6 completadas por Codex (2026-08-03)

Se optimizaron 9 MP4 locales sin generar multimedia ni cambiar contenido: H.264,
AAC, yuv420p, 720x1280 y faststart. Todos conservan audio y duran ~5,04 s.
La prueba tecnica contra originales dio SSIM entre 0,975 y 0,985. Carreras
Despedida se escalo directamente a 720x1280 para normalizar su geometria.

- Antes: 53,15 MB.
- Despues: 7,14 MB.
- Ahorro: 46,01 MB (86,6%).
- Backup temporal recuperable: `C:\Users\luis_\AppData\Local\Temp\cumpleclick-video-backup-before-opt-20260803`.

No se creo `despedida-heroes.mp4`; Heroes sigue bloqueado hasta autorizacion
expresa de Luis. Se mezclo voz Alice ya existente en Carreras/Familia revelacion
y K-Pop despedida; no hubo sintesis nueva ni consumo de caracteres.
## Conexion ElevenLabs verificada por Codex (2026-08-03)

- La primera lectura tomo por error la linea de correo. La API key correcta
  esta en la linea siguiente, con etiqueta generica `API`; nunca se imprimio.
- API oficial verificada: plan `payg`, 8.117 caracteres disponibles antes de
  reutilizar historial. La voz Alice es `Xb7hH8MSUJpSbSDYk0k2`.
- Se agrego MCP remoto `rube`, pero `codex mcp login rube` devolvio
  `Error: No authorization support detected`; no fue necesario para el fallback
  API oficial.
- Se reutilizaron, sin nueva sintesis ni gasto de caracteres, los items de
  historial Alice "Cargando tu foto..." y "Adios! Gracias por venir. Vuelve
  pronto!" para los tres MP4 pendientes.
## Tarea 1 — Auditar qué pistas llevan voz Alice y cuáles no

Con ffmpeg disponible, extraer el audio de los 11 y escuchar/transcribir:

```bash
ffmpeg -i public/themes/<slug>/<archivo>.mp4 -vn -acodec pcm_s16le -ar 24000 -ac 1 /tmp/<slug>-<tipo>.wav
```

Clasificar cada uno en: **voz Alice**, **solo música/ambiente**, o **mudo real**.
Dejar la tabla resultante en este mismo documento.

Lo que ya se sabe con certeza:

- `revelacion-carreras.mp4` y `revelacion-familia-canina.mp4` los generó Claude
  el 2026-08-03 con Higgsfield `kling3_0_turbo` (7,5 créditos c/u). Su pista es
  **ambiente generado por el modelo, NO la voz Alice**. Estos dos necesitan la
  voz sí o sí.
- El resto son anteriores y su contenido de audio no está verificado.

## Tarea 2 — Voz Alice en las revelaciones que falten

Frase exacta: **"Cargando tu foto"**. Español de Chile, tono cálido y amable
(público: niños de 4 a 10 años).

**El nombre del invitado NO va en el audio.** El frontend ya dibuja
`Cargando tu foto, {nombre}...` en HTML encima del video
(`Revelacion` en `src/App.jsx`, alrededor de la línea 3210). Por eso una sola
locución genérica sirve para todas las temáticas — no hay que generar una por
invitado ni por tema.

Muxear conservando el video intacto:

```bash
ffmpeg -y -i revelacion-<slug>.mp4 -i voz-alice.wav \
  -map 0:v -map 1:a -c:v copy -c:a aac -b:a 96k -shortest \
  -movflags +faststart revelacion-<slug>-final.mp4
```

Si el video ya trae ambiente que valga la pena conservar, mezclar en vez de
reemplazar:

```bash
ffmpeg -y -i revelacion-<slug>.mp4 -i voz-alice.wav \
  -filter_complex "[0:a]volume=0.35[amb];[amb][1:a]amix=inputs=2:duration=first[a]" \
  -map 0:v -map "[a]" -c:v copy -c:a aac -b:a 96k \
  -movflags +faststart revelacion-<slug>-final.mp4
```

## Tarea 3 — Despedida de Héroes (falta el archivo)

`themes.json` declara `heroes.videos.despedida = "despedida-heroes.mp4"` pero el
archivo **no existe en disco**. El backend valida existencia, así que hoy
simplemente no se publica y Héroes cierra sin despedida.

Ojo: `docs/TEMATICA-COMPLETA.md` dice que Héroes está **bloqueado a propósito**
hasta que Luis lo pida (le faltan también los 6 `saludo-*.mp4`). Confirmar con
Luis antes de gastar créditos acá.

## Tarea 4 — Voz de despedida

Luis pidió revisar si las despedidas también llevan voz despidiéndose. Depende
del resultado de la Tarea 1: para las que no la tengan, generar locución con la
misma voz Alice y muxear igual que arriba. Texto sugerido, a confirmar con Luis:
**"¡Gracias por venir! Nos vemos pronto."**

## Tarea 5 — Peso de los MP4

Hielo pesa 0,91 y 0,73 MB; Tropical 0,42 MB. Los demás van de 4,86 a 8,02 MB
para los mismos 5 segundos. Son los archivos más pesados del deploy por FTP y
los que más tardan en cargar en la tablet.

```bash
ffmpeg -y -i entrada.mp4 -c:v libx264 -crf 26 -preset slow -pix_fmt yuv420p \
  -vf "scale=720:1280" -c:a aac -b:a 96k -movflags +faststart salida.mp4
```

Objetivo: ≤1,5 MB por clip sin que se note pérdida. Verificar siempre con
`ffprobe` antes de reemplazar.

## Tarea 6 — Normalizar dimensiones

Tres archivos se salen del estándar 720×1280 del proyecto:
`despedida-carreras.mp4` (724×1268), `despedida-hielo.mp4` y
`despedida-kpop.mp4` (716×1284). No rompen nada, pero conviene alinearlos en el
mismo pase de recompresión de la Tarea 5.

## Reglas que aplican

1. **Nunca nombrar la franquicia ni el personaje en prompts generativos.**
   Describir por rasgos. En el producto (`themes.json`, kiosco, admin) sí van los
   nombres reales.
2. **Las imágenes y videos NUNCA llevan texto quemado.** El texto lo dibuja el
   frontend en HTML.
3. **No gastar créditos sin aprobación expresa de Luis.** Preflight con
   `get_cost: true` antes de cada generación.
4. Formato de salida: H.264, `yuv420p`, 720×1280, ~5 s, `+faststart`.
5. Al terminar: `npm.cmd test`, `npm.cmd run build`,
   `php scripts/check-dist-parity.php` y actualizar `docs/FTP-MANIFEST.md`.
6. **No afirmar que algo está en PROD sin evidencia.** PROD corre el build del
   2026-07-27; nada del trabajo de agosto está desplegado.

## Contexto de crédito

Higgsfield al cierre de este handoff: **36,67 créditos** (plan basic). Gastado
por Claude el 2026-08-03: 4 (dos imágenes de fondo) + 2 (pantalla LED) + 15 (dos
revelaciones) = 21 créditos. Referencias: imagen `nano_banana_pro` 2K = 2
créditos; video `kling3_0_turbo` 5 s = 7,5 créditos.
