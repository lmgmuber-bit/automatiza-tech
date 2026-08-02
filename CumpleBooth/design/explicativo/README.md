# Material explicativo CumpleClick

Generado 2026-07-25 por OpenCode (AT-CUMPLECLICK-008) y **corregido 2026-07-26 por Claude**.
Todo el material usa pantallazos reales del kiosco, logo compuesto desde SVG, tipografía Baloo 2 self-hosted y paleta 70/20/8/2.

## ⚠️ Correcciones del 2026-07-26 — leer antes de regenerar nada

Tres defectos que hacían el paquete no publicable, con su causa real:

### 1. El cuadro verde donde va la foto del niño

Los pantallazos se capturaron con `--use-fake-device-for-media-stream`, que alimenta un patrón verde en vez de una cámara. En la pantalla de resultado eso dejaba un rectángulo verde **justo en la toma que vende el producto**.

Corregido con `node scripts/fix-green-screen.mjs`, que compone una foto real sobre el verde. Originales respaldados en `design/screens/_con-verde/`.

Tres cosas que costaron encontrar, por si hay que tocarlo:
- El verde **no es un color plano**: es un degradado de `#008800` a `#104c00`. Keyear un tono puntual deja franjas sin tapar. Se detecta la familia verde por dominancia de canal (`G > R*1.8 && G > B*1.8`).
- Hay que buscar la **región conectada más grande**, no el rectángulo de todos los píxeles verdes: en la temática tropical las hojas de palmera estiraban la caja de 250px a 1100px y la foto salía ampliada hasta verse solo los ojos.
- El césped de Bluey **no** dispara el falso positivo porque tiene R alto (≈`#7CB342`).

La foto que se compone es `ia/IMG-08-nino-camara-limpio.png` — generada con IA (no es un niño real, cumple la regla de marca). El original venía con un "REC 00:04:32" pegado que se quitó con `delogo`.

### 2. Los subtítulos del video pisaban los botones del kiosco

Iban a `flex-end` con `margin-bottom:180px`, que es exactamente donde la app dibuja "Siguiente invitado", "Otra foto" y "Guardar". Se leían los dos textos superpuestos y ninguno.

Ahora cada subtítulo va sobre una **banda violeta degradada** que tapa esa fila a propósito y garantiza contraste sobre cualquier temática. Ver `scripts/render-overlays.mjs`.

**Hay dos juegos de subtítulos**, uno por formato (`t01.png` y `t01-16x9.png`). El horizontal no puede reusar los verticales: escalar 1080×1920 a 1920×1080 no conserva la proporción y la tipografía sale estirada.

### 3. `screen-03-ruleta.png` no era la ruleta — CORREGIDO

OpenCode corrió `capture-full.mjs` dos veces el 2026-07-25 (01:0x y 12:0x). La segunda pasada fotografió el video de bienvenida antes de que terminara y **sobrescribió la captura buena** con una pantalla azul vacía con botón "Continuar". Se propagaba a la infografía "Así funciona" (paso 2) y al video.

Recapturado con `node scripts/capture-ruleta.mjs`. Tres cosas que hicieron falta y que conviene no volver a romper:

- **Esperar a que la rueda tenga ángulo real** (`--spin > 40` en `.spinner-rotator`), no un timeout fijo. Ese fue el error original.
- **Empezar a medir ANTES de clickear "girar".** La rueda dura 3.6s y después la pantalla avanza sola: si se consulta después, ya no está. Un bucle que clickeaba "Continuar" a ciegas se pasaba de largo hasta la pantalla de cámara.
- **Elegir bien el botón del invitado.** Filtrar por longitud de texto no alcanza: el botón de silenciar música es `🎵`, que en UTF-16 mide 2 y quedaba primero en la lista. Se exige que el texto tenga ≥3 letras reales.

> ⚠️ **La fiesta `demo` cambió de temática.** Cuando OpenCode capturó servía Bluey; hoy sirve Carreras. La ruleta de Bluey sale de **`demo-bluey`**. No asumas qué temática devuelve un slug — verificalo contra `cc_parties` antes de capturar, o la infografía mezcla dos temáticas.

## Pantallazos (`design/screens/`)

Capturados con puppeteer-core + Chrome 768×1024@2x, cámara sintética (`--use-fake-device-for-media-stream`):

| Archivo | Temática | Pantalla |
|---|---|---|
| `screen-01-intro.png` | Familia Canina | Portada |
| `screen-02-invitados.png` | Familia Canina | Grilla de invitados |
| `screen-03-ruleta.png` | Familia Canina | Ruleta girando |
| `screen-04-personaje.png` | Familia Canina | Personaje saludando |
| `screen-05-captura.png` | Familia Canina | Cámara |
| `screen-06-preview.png` | Familia Canina | Foto compuesta |
| `screen-07-qr.png` | Familia Canina | QR de descarga |
| `screen-08-diploma.png` | Familia Canina | Diploma |
| `screen-09-diploma-qr.png` | Familia Canina | Diploma con QR |
| `tropical-screen-01..09.png` | Aventura Tropical | Flujo completo |
| `carreras-screen-01..09.png` | Carreras Veloces | Flujo completo |
| `screen-10-galeria.png` | — | Galería papás |
| `screen-11-admin.png` | — | Backoffice |

**Flow frames** (`design/screens/flow/`): secuencias de ruleta mostrando personajes de cada temática (Bluey/Bingo, Lilo/Stitch, Rayo McQueen).

### Regenerar pantallazos

```bash
node scripts/capture-full.mjs
```

Requiere WAMP corriendo, storage_mode=json y 3 fiestas demo en parties.json con 10 invitados y galeriaPin=2026.

---

## Infografías (`design/explicativo/`)

Renderizadas con Chrome headless desde HTML + Baloo 2 + SVG. Tamaño 1080×1350 (4:5).

| Archivo | Descripción |
|---|---|
| `info-01-como-funciona.png` | "Así funciona CumpleClick" — 4 pasos en grilla 2×2 |
| `info-02-que-se-lleva.png` | "Cada invitado se va con algo" — 3 bloques verticales |
| `info-03-planes.png` | "Planes y precios" — Mágico $69.990 / Premium $99.990 |

### Regenerar infografías

```bash
node scripts/render-all-infografias.mjs
```

Los HTML fuente están en `design/explicativo/src/`. Las fuentes Baloo 2 se cargan desde `node_modules/@fontsource/baloo-2/files/`.

---

## Carrusel IG (`design/explicativo/carrusel-01..06.png`)

6 láminas 1080×1350 (4:5). Mismo pipeline HTML→PNG.

### Regenerar carrusel

```bash
node scripts/render-all-infografias.mjs
```

---

## Video explicativo (`design/explicativo/video-explicativo.mp4` + `-16x9.mp4`)

- Formato: 1080×1920 (y 1920×1080), 25fps, h264, yuv420p, faststart
- Duración: 52.68s
- **Con audio (2026-07-26):** narración de Alice (ElevenLabs) + música instrumental
  emotiva (también ElevenLabs, `music_generation`), con la música bajando de
  volumen automáticamente mientras Alice habla. Los originales SIN audio quedaron
  respaldados en `design/explicativo/_sin-audio/`.
- Overlays de texto: PNGs transparentes renderizados con Baloo 2 real

### Regenerar audio (narración + música)

```bash
# 1. Generar narración por línea con ElevenLabs (voz Alice, Xb7hH8MSUJpSbSDYk0k2)
#    y música instrumental (endpoint /v1/music, requiere permiso music_generation
#    habilitado en la cuenta) — ver scripts/mix-audio-explicativo.mjs para el
#    detalle de cómo se generaron los mp3 fuente.
node scripts/mix-audio-explicativo.mjs   # arma design/explicativo/audio-master.mp3

# 2. Pegarlo a los dos videos (silenciosos)
ffmpeg -y -i video-explicativo.mp4 -i audio-master.mp3 -map 0:v -map 1:a \
  -c:v copy -c:a aac -b:a 192k -shortest -movflags +faststart video-explicativo.mp4
```

> ⚠️ **La sincronía depende de medir los segmentos YA construidos, no las
> duraciones declaradas en `build-explicativo.mjs`.** El clip del endcard pide
> `dur: 6.0` pero su metraje nativo es 5.04s — el segmento real en el video dura
> 5.04s. Si `mix-audio-explicativo.mjs` usara el valor declarado, la narración
> del cierre quedaría 0.96s adelantada. El script ya mide los `seg*.mp4`
> reales en `%TEMP%/cc-explicativo/vertical/` para evitar esto.

### Regenerar video (sin audio, desde cero)

```bash
# 1. Renderizar overlays de texto
node scripts/render-overlays.mjs

# 2. Construir los dos formatos
node scripts/build-explicativo.mjs

# 3. Volver a generar y pegar el audio (pasos de arriba)
```

### Verificar video

```bash
ffprobe -v error -show_entries stream=codec_name,width,height,pix_fmt,r_frame_rate -show_entries format=duration -of default=noprint_wrappers=1 design/explicativo/video-explicativo.mp4
```

Salida esperada:
```
codec_name=h264
width=1080
height=1920
pix_fmt=yuv420p
r_frame_rate=25/1
duration=52.080000
```

---

## Piezas pendientes o marcadas como borrador

| Pieza | Estado | Motivo |
|---|---|---|
| G (video 16:9 horizontal) | ✅ **HECHO 2026-07-26** | `video-explicativo-16x9.mp4`, 1920×1080 |
| D (info-03-planes.png) | Borrador | Diferencias entre Premium y Mágico sin confirmar con Luis |
| `screen-03-ruleta.png` | ✅ **CORREGIDO 2026-07-26** | Recapturado desde `demo-bluey` con `capture-ruleta.mjs` |
| §7 IA (6 imágenes) | ✅ Hecho | En `ia/`, más `IMG-08-nino-camara` agregada el 26 |
| §9 Prueba infografía IA | ✅ Hecho | `ia/TEST-A/B/C` |

## Cómo regenerar TODO, en orden

```bash
node scripts/fix-green-screen.mjs      # 1. foto real sobre el verde
node scripts/render-overlays.mjs       # 2. subtítulos, ambos formatos
node scripts/render-all-infografias.mjs # 3. infografías + carrusel
node scripts/build-explicativo.mjs     # 4. los dos videos
```

> `scripts/build-video.ps1` quedó obsoleto: guardaba en la lista de concat el
> texto que imprimía la función (`"  seg0 ✓"`) en vez de la ruta del archivo, y
> solo generaba el vertical. Reemplazado por `build-explicativo.mjs`.

### Sobre el video horizontal

El material nativo es todo vertical. Para el 16:9 **no se recorta** —perdería la
cabeza del niño y los botones del kiosco—: se centra el cuadro completo sobre un
fondo desenfocado de sí mismo, que es el reencuadre estándar de vertical a
horizontal.

---

## Créditos IA

0 créditos gastados. BudgetPixel retornó "Unauthorized" — la generación IA queda para Claude o Luis.

---

## Reglas de marca verificadas

- [x] Logo compuesto desde SVG, nunca generado
- [x] Baloo 2 real en todas las piezas
- [x] Proporción 70/20/8/2
- [x] Cero nombres de franquicia en piezas públicas
- [x] Cero caras de niños reales
- [x] Cero jerga técnica (IA, app, software)
- [x] Paleta exacta del MANUAL-DE-MARCA.md

---

## Restaurar entorno

```bash
# Restaurar parties.json (si se modificó para capturas)
copy public\data\parties.json.bak public\data\parties.json
copy dist\data\parties.json.bak dist\data\parties.json

# Verificar storage_mode=db en config\cumpleclick.local.php
php scripts\check-dist-parity.php
```
