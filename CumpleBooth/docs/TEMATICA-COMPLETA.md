# Qué es una temática COMPLETA

## Video WOW del juego estrella (plan Full)

Una temática puede declarar `videoEstrella: "<archivo>.mp4"` en
`public/data/themes.json`. El backend solo lo publica para fiestas Full,
valida que sea un basename MP4 existente y nunca expone rutas arbitrarias.

El personaje estrella es el único cuya cadena propia tiene más juegos que las
demás. Su clip se reproduce una sola vez, inmediatamente antes de
`concierto3d`, con fallback y watchdog. Si las cadenas empatan, el archivo
falta o la fiesta es Booth, el flujo continúa sin video.

Asset esperado: vertical 720×1280, H.264, `yuv420p`, 5 segundos y mudo. La
música del kiosco continúa por encima y el texto se dibuja en el frontend.

Estándar obligatorio para toda temática nueva. Sale de lo que realmente
tienen las cinco temáticas terminadas (Carreras, Familia Canina, Tropical,
Hielo, K-Pop) — no es una lista de deseos: cada ítem de la tabla A existe hoy
en las cinco, verificado contra disco.

Una temática **a medias no se ofrece a clientes**. El kiosco no se rompe si
falta algo (todo tiene fallback), pero el invitado nota el bache: un personaje
que no saluda cuando los otros cinco sí, o una foto sin recorte, se lee como
producto sin terminar.

El test `tests/frontend/themeFlow.test.mjs` verifica la tabla A automáticamente
sobre las temáticas marcadas como completas. Si agregás una temática nueva,
agregala a esa lista: es lo que convierte estas reglas en algo que se cumple
en vez de un documento que se pudre.

---

## Tabla A — OBLIGATORIO (sin esto no se ofrece)

Todo va en `public/themes/<slug>/`. Los nombres son convención, no
configuración: el código los arma solos a partir de `personajes[].img`.

| # | Archivo | Cant. | Para qué |
|---|---|---|---|
| 1 | `fondo-banner.jpg` | 1 | Pantalla de bienvenida y pantalla LED del show 3D |
| 2 | `fondo-sala.jpg` | 1 | Sala con marco dorado — es el fondo de la foto final |
| 3 | `musica-fondo.mp3` | 1 | Música en loop de todo el kiosco |
| 4 | `<personaje>.jpg` | 6 | Cara del personaje en la ruleta |
| 5 | `<personaje>-cut.png` | 6 | Recorte con transparencia — la foto con el personaje y la estrella del show 3D |
| 6 | `puzzle-<personaje>.jpg` | 6 | Imagen del juego de fichas |
| 7 | `saludo-<personaje>.mp4` | 6 | El personaje saluda al invitado por su nombre |
| 8 | `despedida-<slug>.mp4` | 1 | Cierre después de la foto |
| 9 | `roulette/roulette-background-v1.png` | 1 | Marca de agua detrás de la ruleta |

`<personaje>` = el `img` del personaje sin `.jpg`. Ej.: si `img` es
`elsa.jpg`, van `elsa-cut.png`, `puzzle-elsa.jpg`, `saludo-elsa.mp4`.

### Y en `public/data/themes.json`

- `nombre`, `franquicia`, `publico`, `diploma`
- `colors` — 9 colores (`accent`, `accentSoft`, `yellow`, `ink`, `bgLight1`,
  `bgLight2`, `dark1`, `dark2`, `dark3`)
- `confetti` — 6 colores
- `personajes` — **exactamente 6**, cada uno con `emoji`, `name`, `img` y su
  cadena `game`
- `videos.despedida`
- `frameBox` — dónde cae la foto dentro del marco de `fondo-sala.jpg`
- `fullGame` — la misión del plan Full (ver más abajo)

### Récords de la fiesta

No hay nada que configurar por temática: **todos los juegos llevan récord
automático** (`src/records.js`). Cada juego guarda su mejor marca con el
nombre de quien la hizo y la muestra en el HUD (`🏆 240 · Sofía`) mientras
juega el siguiente invitado.

- Juegos de puntaje (copos, ritmo, escudo, concierto3d): gana el número más
  alto.
- Rompecabezas (fichas, armar-muneco): gana el **tiempo más bajo**.
- Se guarda en `localStorage` por slug de fiesta, así una fiesta nueva
  arranca con el marcador limpio y el kiosco funciona sin internet.

Un juego nuevo solo tiene que llamar a `guardarRecord(kind, valor, invitado,
modo)` al terminar y mostrar `textoRecord(kind)` en su HUD.

### Cadena de juegos por personaje

El estándar de las cinco completas: **una estrella con 3 juegos + Full, el
resto 2 juegos + Full.**

- Los cinco personajes normales: `['fichas', 'copos']`
- La estrella de la temática: un juego propio adelante — `ritmo` (Carreras,
  K-Pop), `escudo` (Familia Canina, Tropical, Héroes) o `armar-muneco`
  (Hielo) — y después `fichas` y `copos`.
- El Full (`concierto3d`) lo agrega el motor solo al final de toda cadena
  cuando la fiesta es plan premium. No se escribe por personaje.

### La misión Full

```json
"fullGame": {
  "kind": "concierto3d",
  "stage": "<uno de los 6 escenarios>",
  "label": "¡El Show de ... 3D!",
  "seconds": 24
}
```

`stage` elige el vestuario en `SHOW_STYLES` (`src/StageConcert3D.jsx`):
`neon-arena`, `ice-gala`, `beach-luau`, `podium-night`, `backyard-fiesta`,
`rooftop-city`. **Una temática nueva necesita su propia entrada en
`SHOW_STYLES`** — si no la tiene, el saneador la deja en `neon-arena` y el
show sale con la paleta de K-Pop en otro mundo: no rompe, pero se ve prestado.

Al agregar un `stage` nuevo hay que sumarlo en **tres** lugares o no pasa:
`SHOW_STYLES` (front), `allowedStages` en `src/themeFlow.js` y
`$allowedStages` en `public/lib.php`.

Los tres colores de `lanes` tienen que ser **claramente distintos entre sí**
(no tres azules): el niño identifica el carril por color antes que por
posición.

---

## Tabla B — RECOMENDADO (mejora notoria, no bloquea)

| Archivo | Para qué | Quién lo tiene hoy |
|---|---|---|
| `welcome-<slug>.mp4` | Video de bienvenida propio | Hielo, Tropical, Familia Canina, K-Pop |
| `revelacion-<slug>.mp4` | "Cargando tu foto…" entre la captura y el resultado | Hielo, Tropical, K-Pop |
| `fondo-juego-<algo>.jpg` | Foto de fondo del show 3D | Las seis |
| `musica-juego.mp3` | Música distinta en la pantalla de juegos | Hielo |
| `game3d/<personaje>-run-atlas.png` | Atlas 2×2 (frente/derecha/espalda/izquierda) para que la estrella baile con volumen | Carreras, Familia Canina, K-Pop (6); Tropical (4) |
| `photoSession` | Experiencia especial antes de la foto para 2-3 personajes | Familia Canina, Tropical, K-Pop |

Sin foto de fondo el show usa un cielo dibujado por código con la paleta de la
temática: se ve bien, pero una foto real de arena/paisaje queda mejor.
Sin atlas, la estrella usa el `-cut.png` fijo: funciona, pero no baila.

---

## Reglas duras de producción

1. **Nombres reales en el producto, camuflaje SOLO en los prompts.** En
   `themes.json`, en el kiosco y en el admin van los nombres de la franquicia
   (Spider-Man, Elsa, Rayo McQueen). Los descriptores genéricos ("Araña",
   "Hombre de Hierro") existen únicamente para saltar los filtros de IP al
   generar imágenes. Nunca al revés.

2. **Las imágenes NUNCA llevan texto.** Ni pancartas, ni carteles, ni nombres.
   La IA los escribe mal y quedan pegados al asset para siempre.

3. **La música la pone Luis, no se genera.** Son canciones reales con
   derechos. El kiosco espera `musica-fondo.mp3` y punto.

4. **Escenas de grupo (2+ personajes juntos): el filtro nsfw rebota parejo.**
   Está probado varias veces que es consistente, no aleatorio — no insistir.
   La salida probada es compositar local con ffmpeg los recortes individuales
   (que sí pasan) sobre un fondo ya aprobado. Así se armaron el banner grupal
   de Héroes y la revelación de Tropical.

5. **Seis personajes, ni cinco ni siete.** La ruleta, los tests y el layout
   asumen seis.

6. **Antes de subir a PROD, actualizar `docs/FTP-MANIFEST.md`.** Los hashes
   de `assets/` cambian en cada build; copiar la tabla anterior deja el kiosco
   en blanco.

---

## Orden de producción sugerido

De lo más barato a lo más caro. Cada paso deja algo usable, así una temática
se puede mostrar a medio hacer sin que se vea rota.

1. `themes.json`: nombre, colores, confetti, los 6 personajes, cadenas de
   juego, `fullGame` con su `stage` nuevo + entrada en `SHOW_STYLES`.
2. Los 6 retratos individuales (`<personaje>.jpg`) — baratos y destraban la
   ruleta.
3. `fondo-banner.jpg` y `fondo-sala.jpg`.
4. Los 6 `-cut.png` con `remove_background` — barato, sin problemas de filtro
   aun en personajes que fallan en grupo.
5. Los 6 `puzzle-<personaje>.jpg` (recorte de los retratos, sin IA).
6. `roulette/roulette-background-v1.png` (reusar el banner).
7. `musica-fondo.mp3` — la pide Luis.
8. Los 6 `saludo-<personaje>.mp4` y `despedida-<slug>.mp4` — la parte cara.
9. Opcionales de la tabla B según presupuesto.

---

## Estado hoy (2026-08-01)

| Temática | Tabla A | Falta |
|---|---|---|
| Carreras | ✅ | — |
| Familia Canina | ✅ | — |
| Tropical | ✅ | — |
| Hielo | ✅ | — |
| K-Pop | ✅ | — |
| **Héroes** | ⚠️ | los 6 `saludo-*.mp4` y `despedida-heroes.mp4` (bloqueado a propósito hasta que Luis lo pida) |
| Mickey, Cachorros, Princesas, Dinos, Sirenas, Juguetes | ❌ | todo — cero assets |

---

# Baby shower: qué es una temática completa (2026-08-26)

La tabla A de arriba **no aplica**. Está escrita para cumpleaños infantiles y
exige seis personajes con su cadena de juegos, ruleta, puzzles y videos de
saludo. Un baby shower no tiene nada de eso, y pedírselo dejaría a las dos
temáticas marcadas como "a medias" para siempre por algo que no van a tener
nunca.

El recorrido real de la cabina en modalidad `baby_shower` es:

> intro → apuesta → juego → sellado → foto → revelado → QR → recuerdito

Sin lista de invitados, sin ruleta y sin personajes. La rama se activa sola con
`cc_parties.event_type = 'baby_shower'`.

## Tabla A-BS — OBLIGATORIO

| # | Archivo | Cant. | Para qué |
|---|---|---|---|
| 1 | `fondo-banner.jpg` | 1 | Pantalla de bienvenida. **9:16**, 1080×1920 |
| 2 | `fondo-sala.jpg` | 1 | Fondo de la foto final. 9:16, y **tiene que traer un marco decorativo vacío y despejado**: `frameBox` apunta adentro de ese marco |
| 3 | `musica-fondo.mp3` | 1 | Música en loop de todo el kiosco |

Nada más. No van personajes, ni `roulette/`, ni puzzles, ni saludos.

### Y en `public/data/themes.json`

- `modalidad: "baby_shower"` — es lo que separa estas temáticas de las otras,
  tanto para el código como para los tests.
- `nombre`, `publico`, `diploma` (el texto del recuerdito)
- `colors` — los mismos 9 tokens
- `confetti` — 6 colores
- `frameBox` — **calibrado contra el marco de su propio `fondo-sala.jpg`**, no
  heredado de otra temática. Ver abajo.
- `personajes: []` — vacío, y el test lo exige: si trae personajes es que
  alguien copió una temática infantil sin limpiarla.

## Cómo se calibra el frameBox

`frameBox` no es un valor a ojo: marca el recuadro **dentro del marco
decorativo pintado en `fondo-sala.jpg`** (ver `src/frameGeometry.js`). Si el
fondo no tiene marco, no hay nada que calibrar y la foto del invitado queda
flotando sobre la decoración.

El método que funcionó: una página de un solo uso que carga el fondo y le
dibuja encima el rectángulo que devuelve `getSquarePhotoGeometry()` con los
valores candidatos. Se ajusta y se vuelve a mirar; dos iteraciones alcanzan.
Conviene dejar un ~10% de aire respecto del borde interior, porque un recorte
que pisa la moldura delata el montaje.

**Pero el overlay no alcanza: la prueba que vale es la foto ya compuesta.** En
Safari el recuadro se veía bien en el overlay y recién con la foto encima se
notó que quedaba al filo del riel.

## El marco tiene que estar DESPEJADO

Aprendido rehaciendo Safari dos veces. Si un peluche, una planta o un globo se
cruzan por delante del marco en la foto de fondo, la foto del invitado los tapa
—se dibuja encima— y el cliente ve su decoración mutilada. En el primer intento
el león se sentaba delante del marco y la foto le comía la melena.

Al pedir el fondo hay que decirlo explícito: *nada se cruza por delante del
marco ni toca sus bordes; los peluches van a los lados y por debajo, fuera de
su contorno*. El montaje que mejor resultó fue el marco colgado alto en la
pared, con los animales abajo: ahí es imposible que se pisen.

## Lo que NO hace falta

- `grupo-personajes.png`: el cierre del Álbum Recuerdo cae al `fondo-banner.jpg`
  cuando no está, y para un baby shower ese fondo es justamente la foto que
  corresponde. Generar un "grupo" sin personajes no aporta nada.
- `despedida-<slug>.mp4`: cae al genérico `videos/despedida.mp4`.
- `musica-juego.mp3`: si falta, el juego suena con la música de fondo.

## Estado al 2026-08-26

`baby-nube` (Bebé en las Nubes) y `baby-safari` (Bebé Safari) **cumplen la
tabla A-BS completa**, música incluida. Las pistas las generó Luis con Gemini a
partir de los prompts acordados; llegaron con fundido de salida en los últimos
~2 s, que en loop se oye como un bajón cada 90 segundos, así que se les cortó
la cola y se les hizo un cruce de 2 s envolviendo el final sobre el principio.
Medido antes y después: la diferencia de nivel entre el primer y el último
segundo pasó de ~20 dB a menos de 3,5 dB.

Las dos llevan además `musica-juego.mp3`, que es la misma pista para ambas
porque sirve igual en las dos.

**Al pedir música nueva, exigir siempre loop sin fundido en los extremos.** Es
lo que más se olvida y lo único que no se nota hasta que suena en bucle.

## La invitación pública

Una temática de baby shower no está completa hasta que su **invitación** se ve
como corresponde. No es lo mismo que la cabina, y se rompe de forma silenciosa.

Hace falta una entrada propia en `public/data/event-profile-presets.json`
dentro de `themes`, con dos claves:

- `base_image: "fondo-sala.jpg"` — la lámina de la invitación. Es la misma foto
  del kiosco, así que no se genera nada nuevo.
- `text_area` — **exactamente los cuatro números del `frameBox`**, más un
  `tone` con la tinta. Los datos de la fiesta se escriben así dentro del marco
  decorativo que la foto ya trae: el mismo marco que en la fiesta encuadra la
  foto del invitado.

Sin esa entrada el preset cae a `theme_fallback`, que no define `base_image`, y
la invitación muestra **dos veces el mismo `fondo-banner.jpg`** — una de hero y
otra de "lámina". No rompe nada, solo se ve pobre, que es justamente por qué
hay un test (`la invitación tiene lámina propia, dentro del marco`) que exige
que los dos números sigan siendo el mismo.

Lo que **no** hace falta: video. Las temáticas infantiles apoyan la invitación
en el recorrido de personajes, y un baby shower no tiene personajes. En vez de
imitar ese formato con una foto fija —que da una versión pobre de la misma
idea— la invitación de baby shower usa el bloque "la espera" que cierra
`public/assets/invitation.css`: la fotografía respira, `.inv-sparks` se
convierte en estrellas o luciérnagas según el tema, y la cuenta regresiva pone
el número grande y solo. Todo cuelga de `[data-theme]`, así que agregar una
temática de baby shower nueva pide sumar su slug a esos selectores.
