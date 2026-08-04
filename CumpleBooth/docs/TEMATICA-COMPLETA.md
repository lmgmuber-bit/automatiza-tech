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
