# Handoff a Codex — llevar kpop, tropical y familia-canina al nivel de Carreras/Hielo

Escrito por Claude el 2026-08-23. Todo lo de abajo está **medido contra disco**,
no supuesto: existencia, dimensiones, fps, duración, pista de audio y similitud
SSIM se verificaron con ffprobe/ffmpeg 8.1.1 en esta máquina.

Luis pidió que la parte que gasta créditos la tome Codex. Lo que se puede
derivar sin gastar nada ya está hecho, o está descrito acá para hacerlo gratis.

---

## Qué ya está listo (Claude, 2026-08-23)

Los tres temas ya tienen invitación funcional. No hace falta rehacer nada de esto:

- `themes/<tema>/invitation/intro-invitacion-wow-v1.mp4` + su poster — intros con
  el logo corregido, incluido el "CumpleClict" mal escrito que traía
  familia-canina.
- `themes/<tema>/invitation/invitation-scroll-v1.mp4` — hero de avance con el
  dedo, derivado del `invitation-motion-v1.mp4` de cada tema.
- `$playlistOrdersByTheme` en `public/invitacion.php` con los tres temas, su
  orden de capítulos y el texto de cada uno.
- Gate por plan: `booth` → hero scroll, `full` → hero auto.

Probado con Apache real, base SQLite y las cinco temáticas en los dos planes:
10 de 10 combinaciones correctas, 35 medios respondiendo 200 con content-type
correcto.

---

## Lo que falta, y qué cuesta

| Ítem | Cantidad | ¿Se puede derivar? |
|---|---|---|
| Narración de Alice por capítulo | 20 mp3 | **No** — TTS |
| Hero a medida, versión auto | 3 mp4 | **No** — generación de video |
| Hero a medida, versión scroll | 3 mp4 | **Sí**, sale del auto con ffmpeg |
| Capítulos ilustrados | 27 jpg | **No** — solo para nivel Carreras |

### Por qué el hero a medida no se deriva

Se midió SSIM contra el `invitation-motion-v1.mp4` de cada tema, escalando y
normalizando fps para que la comparación fuera justa:

| Par | SSIM |
|---|---:|
| `candidate-hielo-scroll` vs `candidate-hielo-auto` | 0,988 |
| `candidate-hielo-auto` vs `invitation-motion-v1` | 0,628 |
| `candidate-wan27-auto` vs `invitation-motion-v1` | 0,465 |
| Control: motion de Hielo vs motion de Carreras | 0,552 |

0,628 y 0,465 están al nivel del control entre dos videos completamente
distintos. Son clips generados aparte, no recodificaciones.

El 0,988 del primer par sí confirma que el **scroll se deriva del auto**.

---

## Tarea 1 — Narración de Alice (20 mp3)

Voz Alice de ElevenLabs, `voice_id Xb7hH8MSUJpSbSDYk0k2`. Es la misma que ya usan
Carreras e Hielo.

### Dónde van

`public/themes/<tema>/narracion-video/<clave>.mp3`

La `<clave>` es el nombre del video del capítulo **sin extensión y sin sufijo
`-vN`** — `invitacion.php:1028` aplica `preg_replace('/-v[0-9]+$/', '', ...)`.
Para estos tres temas ningún archivo lleva sufijo, así que la clave es directa:
`saludo-rumi.mp4` → `saludo-rumi.mp3`.

### Por qué son 20 y no 23

El último capítulo de cualquier temática reutiliza el audio de cierre global
(`$narrationPlaylistEndUrl`), no necesita grabación propia. Así que la despedida
de cada tema queda fuera: 6 + 6 + 8 = 20.

### El texto NO lleva el nombre del cumpleañero

El mp3 es fijo por tema y sirve para todas las fiestas de ese tema. Los textos de
abajo ya están escritos sin nombre. En pantalla el caption sí lo lleva — eso se
compone por invitación y no depende del audio.

### kpop — 6 clips

| Archivo mp3 | Texto |
|---|---|
| `saludo-rumi.mp3` | Rumi abre el escenario. |
| `saludo-mira.mp3` | Mira toma el micrófono. |
| `saludo-zoey.mp3` | Zoey suma su voz. |
| `saludo-luna.mp3` | Luna enciende las luces. |
| `saludo-sussie.mp3` | Sussie llega con la coreografía. |
| `saludo-derpy.mp3` | El escenario ya está listo. |

### tropical — 6 clips

| Archivo mp3 | Texto |
|---|---|
| `saludo-hawaiana.mp3` | La isla ya se prepara. |
| `saludo-surfista.mp3` | El surfista viene en camino. |
| `saludo-loro.mp3` | El loro reparte la noticia. |
| `saludo-tortugamar.mp3` | La tortuga llega sin apuro. |
| `saludo-alienrosa.mp3` | Una visita inesperada aterriza. |
| `saludo-alienazul.mp3` | Toda la playa se suma a celebrar. |

### familia-canina — 8 clips

| Archivo mp3 | Texto |
|---|---|
| `saludo-muffin.mp3` | Muffin da la primera señal. |
| `saludo-chloe.mp3` | Chloe se suma al plan. |
| `saludo-chispa.mp3` | Chispa no se queda quieta. |
| `saludo-manchita.mp3` | Manchita llega corriendo. |
| `saludo-azulita.mp3` | Azulita trae la sorpresa. |
| `saludo-nube.mp3` | Nube deja todo listo. |
| `saludo-mama-coral.mp3` | Mamá Coral ordena la casa. |
| `saludo-papa-marino.mp3` | Papá Marino enciende la música. |

### Formato

Mismo que los de Carreras/Hielo, que van de 26 a 86 KB. MP3, mono, español de
Chile. Cortos: cada uno se escucha una vez por capítulo mientras el video corre.

**Antes de generar, medir el costo y confirmárselo a Luis.** Él aprueba gasto
uno por uno.

---

## Tarea 2 — Hero a medida (3 videos + 3 derivados)

Opcional pero es lo que separa visualmente a los tres temas de Carreras/Hielo.
Sin esto usan su `invitation-motion-v1.mp4` genérico de 5 segundos, que ya
funciona.

### El que hay que generar

`public/themes/<tema>/invitation/candidate-<tema>-auto.mp4`

Spec, tomado de `candidate-hielo-auto.mp4`:

- 720×1280 vertical, H.264, `yuv420p`
- 30 fps, 151 cuadros, 5,03 segundos
- Sin audio

Es la entrada del tema: el primer plano que ve el invitado al abrir la
invitación. En Hielo es el palacio; en Carreras, la pista.

### El derivado, gratis

Una vez exista el auto, el scroll sale con ffmpeg. Sin generación:

```bash
ffmpeg -y -i candidate-<tema>-auto.mp4 \
  -vf "scale=540:960" -r 30 \
  -c:v libx264 -profile:v high -pix_fmt yuv420p -g 1 -bf 0 -crf 20 \
  -movflags +faststart -an \
  candidate-<tema>-scroll.mp4
```

`-g 1 -bf 0` es lo importante: un keyframe por cuadro y sin cuadros B. Sin eso el
avance con el dedo salta en vez de deslizarse.

Verificar después:

```bash
ffprobe -v error -select_streams v:0 -show_entries frame=key_frame \
  -of csv=p=0 -read_intervals "%+#40" candidate-<tema>-scroll.mp4 | grep -c "^1"
```

Debe dar 40 de 40.

### No hace falta tocar código

`invitacion.php` ya busca `$heroAutoCandidatesByTheme` y
`$heroScrollCandidatesByTheme`. Hay que **agregar las tres entradas** a esos dos
arrays cuando los archivos existan. Hoy solo listan `carreras` e `hielo`.

---

## Tarea 3 — Capítulos ilustrados (27 jpg) — solo nivel Carreras

Hielo **no** los tiene, así que esto no hace falta para igualar a Frozen. Solo
para igualar a Carreras.

`public/themes/<tema>/invitation/chapters/` con la estructura de Carreras:
`scene-01.jpg` a `scene-05.jpg` y `conn-01.jpg` a `conn-04.jpg`. Nueve por tema.

Son imágenes fijas que el invitado avanza con el dedo en el Plan Básico. Pesan
menos de 1 MB las nueve juntas, contra los 33 MB de la playlist de videos de
Carreras — por eso el Básico de Carreras carga al instante.

Si se agregan, ese tema deja de usar la playlist en Básico automáticamente: el
gate prefiere capítulos cuando la carpeta existe.

---

## Orden sugerido

1. **Narración.** Es lo único que se nota como falta: hoy los capítulos salen
   mudos. Costo medido y aprobado por Luis antes de generar.
2. **Hero a medida.** Polish. Genera el auto, deriva el scroll gratis, agrega las
   entradas a los dos arrays.
3. **Capítulos ilustrados.** Solo si se decide que los tres temas deben llegar al
   nivel de Carreras y no solo al de Hielo.

## Cómo verificar que quedó

`invitacion.php` es resiliente: lo que falta se omite sin romper. Así que la
prueba no puede ser "abre y no explota". Contra el servidor local, para cada tema
y cada plan, revisar que el HTML referencie:

- los `narracion-video/*.mp3` esperados (hoy son 0 en los tres temas)
- `candidate-<tema>-scroll.mp4` en Básico y `candidate-<tema>-auto.mp4` en Full
- y que cada URL responda 200 con su content-type real, no `text/html`

Ese último punto importa: el `.htaccess` tiene un catch-all que devuelve
`index.html` con status **200** para cualquier ruta que no exista. Un 200 no
prueba que el archivo esté.
