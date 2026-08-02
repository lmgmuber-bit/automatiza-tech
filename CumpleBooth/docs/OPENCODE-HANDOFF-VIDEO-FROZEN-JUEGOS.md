# HANDOFF OPENCODE — Video exclusivo temática Reino de Hielo (Frozen), foco en los juegos

**Fecha:** 2026-07-26 · **Autor del brief:** Claude · **Ejecutor:** OpenCode
**Objetivo:** un video corto (30-45s) que muestre EN VIVO los minijuegos de la temática Reino de
Hielo (slug interno `hielo`, franquicia Frozen), con foco especial en Olaf porque es el único
personaje con **3 juegos encadenados** (los demás tienen 2).

**Ticket:** `Docs/ORCHESTRATION/AT-CUMPLECLICK-009.yaml` · **Clase:** C1 · **Riesgo:** medio (por el
punto técnico de la §2, léelo antes de escribir una sola línea de código)

---

## −1. ANTES DE LEER NADA MÁS — de dónde sacas el contexto

Igual que en todo handoff de este proyecto:

1. `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\10-Projects\CumpleClick.md` — empieza por ahí.
2. `C:\wamp64\www\automatiza-tech\Docs\ORCHESTRATION\current-handoff.yaml` — el lease activo.
3. `Docs/ORCHESTRATION/AT-CUMPLECLICK-009.yaml` — tu ticket (alcance, gates, criterios).
4. Al cerrar: actualiza el ticket, la nota del vault y escribe el cierre en `50-Daily-Logs/`.

Rama nueva antes de tocar código. Sin push/merge/deploy sin permiso explícito de Luis.

---

## 0. Contexto — por qué este video existe

Ya se entregó (y Luis aprobó) el video **explicativo genérico** de CumpleClick
(`design/explicativo/video-explicativo.mp4`) — ese muestra el flujo completo con la temática Bluey
como ejemplo. Este es distinto: es un video **exclusivo de una sola temática** (Reino de
Hielo/Frozen) cuyo ángulo de venta es *"mira todo lo que se puede jugar, no solo la foto"*. El
diferencial de CumpleClick frente a cualquier photo booth genérico son los juegos, y Frozen es la
temática con más variedad (Olaf tiene 3 juegos, el resto 2).

No reuses el guion del video genérico. Este es nuevo, corto, y 100% sobre gameplay.

---

## 1. La temática y sus juegos — de dónde sale la data

`public/data/themes.json` → clave `hielo`. Seis personajes, cada uno con su cadena de juegos
(campo `game`, array = juegos en orden, ejecutados uno tras otro con pantalla "¿Jugamos otro?"
entre medio):

| Personaje | Juegos (en orden) |
|---|---|
| Elsa | fichas (`puzzle-elsa.jpg`, 3×3) → copos (15s) |
| Anna | fichas (`puzzle-anna.jpg`, 3×3) → copos (15s) |
| **Olaf** | **armar-muneco** (`fondo-juego-nieve.jpg`) → **fichas** (`puzzle-olaf.jpg`, 3×3) → **copos** (15s) |
| Kristoff | fichas (`puzzle-kristoff.jpg`, 3×3) → copos (15s) |
| Sven | fichas (`puzzle-sven.jpg`, 3×3) → copos (15s) |
| Bruni | fichas (`puzzle-bruni.jpg`, 3×3) → copos (15s) |

Los tres tipos de juego que vas a grabar:

- **`armar-muneco`** (solo Olaf): arrastrar piezas de Olaf a su lugar sobre un fondo de nieve.
  Componente en `src/App.jsx`, busca `juego--muneco`. Revisa `MUNECO_PARTES` (o el nombre similar
  cerca de ese componente) para saber cuántas piezas hay y cómo se arrastran — no lo adivines,
  léelo antes de automatizar el drag.
- **`fichas`**: puzzle deslizante 3×3 de una imagen del personaje.
- **`copos`**: atrapar copos de nieve que caen durante 15s (animación CSS, no requiere arrastrar).

La fiesta demo **ya existe y está creada** (ver §3), no necesitas crear nada en `themes.json`.

---

## 2. EL PUNTO TÉCNICO MÁS IMPORTANTE — léelo antes de empezar

Durante la sesión anterior (corrección del video explicativo genérico) se intentó grabar la
ruleta girando con `puppeteer-core` + CDP `Page.startScreencast` (la técnica estándar para
"grabar" una página con Puppeteer). **Falló de forma reproducible:** aunque el JS de la animación
avanzaba a velocidad real (verificado leyendo `getComputedStyle` de la propiedad `--spin` cada
frame — a los 3.6s reales el valor mostraba el giro completo), los frames que llegaba a capturar
el screencast estaban comprimidos en **menos de 1 segundo de tiempo real**, sin importar cuánto
duraba la animación de verdad. Se intentaron dos arreglos (`page.bringToFront()` +
`Emulation.setFocusEmulationEnabled`) y ninguno lo resolvió. Se abandonó esa vía para la ruleta y
se usó una captura estática en su lugar.

**Esto es un problema conocido de Chrome cuando corre con `headless:false` en ciertos entornos:
el proceso de composición/pintado de la GPU no funciona a un ritmo normal aunque el JS sí.**
`Page.startScreencast` depende de que Chrome pinte frames reales — si no pinta, no hay frames que
capturar, sin importar qué tan bien esté escrito el script.

Como el gameplay de `armar-muneco`, `fichas` y `copos` son TODOS animaciones/interacciones
continuas (arrastre con feedback visual, deslizamiento de fichas, copos cayendo), es muy probable
que el mismo problema aparezca si usas `Page.startScreencast` para grabarlos. **No asumas que tu
entorno tiene el mismo problema — pruébalo primero (§2.3)** — pero ve preparado con el plan B.

### 2.1 La solución recomendada: grabación de pantalla a nivel de SISTEMA OPERATIVO, no de Chrome

En vez de pedirle a Chrome que te dé los frames por dentro (CDP screencast, que depende de su
pipeline de composición interno), captura lo que la GPU ya compuso y Windows ya mostró en pantalla
— eso sortea el problema por completo porque no depende de que Chrome "coopere" internamente.

**Ffmpeg con `gdigrab` (captura de escritorio de Windows) es la vía recomendada:**

```bash
ffmpeg -f gdigrab -framerate 30 -offset_x 100 -offset_y 100 -video_size 768x1024 \
  -i desktop -t 12 -c:v libx264 -preset ultrafast -pix_fmt yuv420p salida.mp4
```

- `-offset_x/-offset_y/-video_size` recortan la región exacta donde Puppeteer abrió la ventana de
  Chrome (con `defaultViewport` fijo y la ventana posicionada en una esquina conocida — usa
  `--window-position=0,0` en los args de `puppeteer.launch` para que la posición sea predecible).
- Corre el `ffmpeg -f gdigrab` como proceso hijo (`child_process.spawn`) DESDE el mismo script
  Node que controla Puppeteer, arrancándolo justo antes de disparar la acción (click en "Girar",
  inicio del arrastre, etc.) y matándolo (`SIGINT` o `.kill()`) cuando el juego termina.
- Alternativa si `gdigrab -i desktop` captura de más (otras ventanas encima, notificaciones):
  `-i title="<título de la ventana de Chrome>"` en vez de `-i desktop` — más preciso, no requiere
  offsets.

Esto es exactamente cómo se graba cualquier demo de software en Windows de forma confiable: no es
un hack, es el método estándar cuando la captura interna del navegador no alcanza.

### 2.2 Plan B si ni gdigrab funciona (poco probable, pero documenta si pasa)

Si por algún motivo tampoco puedes grabar video fluido, el fallback ya está probado y **a Luis le
gustó el resultado** en el video explicativo genérico: pantallazos ESTÁTICOS en 2-3 momentos clave
de cada juego (inicio, a mitad de progreso, resultado/éxito) y armar el video con ffmpeg dándoles
vida con un `zoompan` suave (`zoompan=z='min(zoom+0.0006,1.12)':d=125:s=1080x1920`), igual que se
hizo con `screen-02-invitados.png`, `screen-03-ruleta.png`, etc. en el video explicativo. No hay
vergüenza en usar este plan B — es más importante entregar algo pulido que insistir con video real
que sale con parpadeos o segmentos negros.

### 2.3 Antes de comprometerte a un plan, haz una prueba de 2 minutos

1. Levanta el flujo hasta el juego `copos` (el más simple: no requiere simular arrastre, cae solo
   durante 15s).
2. Graba con `gdigrab` 5 segundos reales.
3. Verifica con `ffprobe -show_entries format=duration` que el video resultante mide ~5s (no <1s).
4. Si mide ~5s: gdigrab funciona en tu entorno, sigue con esa vía para todo.
5. Si también sale comprimido a <1s: es un problema de esta máquina más profundo que CDP, y pasas
   directo al Plan B (§2.2) sin perder más tiempo probando variantes.

---

## 3. La fiesta demo — ya existe, no la crees de nuevo

Se agregó una entrada nueva a `scripts/seed-demo-theme-parties.php` (ya está en el repo, en la
rama en la que trabajes tráela con `git pull`/merge de main) y se ejecutó con `--apply`:

| Slug | Temática | URL kiosco |
|---|---|---|
| `demo-hielo` | Reino de Hielo (Frozen) | `?p=demo-hielo` en `http://localhost/automatiza-tech/CumpleBooth/dist/` |

10 invitados (5 niñas, 5 niños), igual esquema que `demo`, `demo-tropical`, `demo-carreras`. **No
la borres ni le cambies el slug.** Si necesitas más invitados o resetear su estado, edítala desde
`admin/` (usar la contraseña local desde `CUMPLECLICK_ADMIN_PASSWORD`; no
escribirla en scripts ni documentación).

**Storage es `db`, no JSON.** Este proyecto migró de `parties.json` a una base de datos
(`cb_storage_mode() === 'db'`). Si necesitas inspeccionar o tocar fiestas por script, usa
`cb_load_parties()` / `cb_save_parties()` de `public/lib.php` vía PHP CLI — **no edites
`public/data/parties.json` directamente**, ese archivo es un remanente legacy y ya no es la fuente
de verdad (confirmado el 2026-07-26: contenía datos viejos que no coincidían con lo que la app
realmente sirve).

---

## 4. Cómo llegar a Olaf — la ruleta es aleatoria, no hay atajo de query string

El componente de la ruleta (`src/App.jsx`, busca `winIdx = useRef(Math.floor(Math.random() * n))`)
elige el ganador con `Math.random()` puro, sin ninguna semilla ni parámetro de URL para forzarlo.
No hay atajo — no pierdas tiempo buscando uno. La única forma de "forzar" a Olaf es reintentar:

1. Recarga la página (o pulsa el flujo desde el inicio).
2. Elige un invitado.
3. Gira la ruleta, espera a que pare.
4. Lee el nombre del personaje resultante (hay un texto en pantalla, o revisa el estado `personaje`
   — inspecciona el DOM después de que pare para encontrar el selector exacto).
5. Si NO es Olaf, vuelve a 1.

Con 6 personajes la probabilidad es 1/6 por intento — en promedio 6 intentos, rara vez más de 15.
Automatízalo en un loop con tope de ~25 intentos y aborta con un mensaje claro si no cae (algo
raro estaría pasando, no sigas insistiendo a ciegas).

**Nota:** los otros 5 personajes (Elsa, Anna, Kristoff, Sven, Bruni) solo tienen 2 juegos
(fichas → copos) y cualquiera de ellos sirve si necesitas cubrir esa parte del guion sin tener que
esperar a que caiga Olaf — solo la cadena de 3 juegos requiere específicamente a Olaf.

---

## 5. Selección de invitado — por GÉNERO, nunca por nombre exacto

Bug ya encontrado y corregido esta sesión en otro script: los nombres de invitados tienen tilde
("Sofía", "Matías") y cambian con el tiempo si la fiesta se resiembra — **nunca hardcodees un
nombre exacto ni un regex que asuma sin tilde.** El DOM es una lista plana:

```html
<p class="invitados-group">Niñas</p>
<button class="invitado-item"><span class="check">○</span><span>Sofía</span></button>
...
<p class="invitados-group">Varones</p>
<button class="invitado-item">...</button>
```

Recorre el DOM en orden llevando la cuenta de qué `.invitados-group` viene antes de cada
`.invitado-item`, y elige por género (`nina`/`varon`), no por texto exacto:

```js
async function elegirInvitado(page, generoDeseado) {
  return page.evaluate((generoDeseado) => {
    const nodos = [...document.querySelectorAll('.invitados-group, .invitado-item')]
    let grupoActual = null
    for (const n of nodos) {
      if (n.classList.contains('invitados-group')) {
        grupoActual = /varon/i.test(n.textContent) ? 'varon' : 'nina'
        continue
      }
      if (grupoActual === generoDeseado) { n.click(); return n.textContent.trim() }
    }
    return null
  }, generoDeseado)
}
```

---

## 6. Cámara falsa — flags obligatorios, los dos juntos, siempre

Para el juego `armar-muneco` y la foto final no necesitas cámara real, pero si tu flujo pasa por la
pantalla de captura en algún punto, **usa SIEMPRE ambos flags juntos**:

```js
args: [
  '--use-fake-device-for-media-stream',
  '--use-file-for-fake-video-capture=<ruta-absoluta>.y4m',
  '--use-fake-ui-for-media-stream',
  '--autoplay-policy=no-user-gesture-required',
]
```

**Advertencia seria, pasó esta sesión:** usar `--use-file-for-fake-video-capture` **sin** el flag
`--use-fake-device-for-media-stream` hace que Chrome caiga silenciosamente a la **cámara física
real** de la máquina — se capturó por accidente un cuadro con una persona real de este entorno.
Se borró de inmediato. Si solo necesitas `--use-fake-device-for-media-stream` (sin archivo), el
resultado es un cuadro verde sintético — aceptable para pruebas, pero si vas a mostrar una foto en
pantalla usa el flag de archivo también, **nunca uno sin el otro.**

Si generas un `.y4m` como cámara falsa: **debe tener varias frames** (ej. 1s a 8fps = 8 frames), no
un solo frame estático — un Y4M de un solo frame hizo que la pantalla de captura del kiosco se
quedara colgada (shutter habilitado, click registrado, pero nunca pasaba a preview) esta sesión.

```bash
ffmpeg -loop 1 -i foto.png -t 1 -vf "scale=1280:960:force_original_aspect_ratio=increase,crop=1280:960,format=yuv420p" -r 8 -pix_fmt yuv420p salida.y4m
```

---

## 7. Guion sugerido (ajústalo si el gameplay real te da mejores momentos)

30-45s, vertical 1080x1920 primero (horizontal después si alcanza el tiempo, mismo criterio del
video explicativo: fondo desenfocado del mismo cuadro, no recortar cabezas ni botones).

| Momento | Contenido |
|---|---|
| 0-3s | Portada: logo real (`design/logo/logo-icon-wordmark.png`, ya recortado con fondo transparente — **reutilízalo, no regeneres el logo**) + texto "Reino de Hielo" o "Frozen" + "3 juegos con Olaf" |
| 3-10s | Gameplay real: Olaf, juego `armar-muneco` (arrastrar piezas) |
| 10-11s | Transición "¿Jugamos otro?" → "Sí, jugar" (pantalla real, se ve tal cual la ve el invitado) |
| 11-18s | Gameplay real: `fichas` con `puzzle-olaf.jpg` |
| 18-19s | Transición "¿Jugamos otro?" de nuevo |
| 19-30s | Gameplay real: `copos` cayendo, invitado atrapándolos (o el contador bajando si no simulas clics) |
| 30-38s | Resultado: foto final con marco de la temática + diploma "Guardián del Reino de Hielo" (ese es el texto exacto de `themes.json → hielo.diploma`, no lo cambies) |
| 38-45s | Cierre: logo + "Agenda tu fecha" (mismo CTA del video explicativo) |

Si quieres narración con la voz de Alice (ElevenLabs) o música, **pregúntale a Luis primero** —
no lo asumas ni lo generes sin confirmar, es la misma regla que rigió todo el trabajo de audio del
video explicativo.

---

## 8. Reglas que ya aplican a TODO este proyecto (no las repitas de memoria, respétalas)

- Logo: **nunca se genera ni se redibuja**, se compone desde `design/logo/logo-icon-wordmark.png`
  (ya recortado, fondo transparente) o `design/logo/cumpleclick-logo-master-render.png` (con
  color-key si necesitas quitarle el fondo crema: `colorkey=0xFBF5E7:0.14:0.06,format=rgba`).
- Sin caras de niños reales, sin nombrar la franquicia en prompts de generación de IA (el nombre
  real SÍ puede aparecer en la pieza pública final).
- ffmpeg de salida siempre `libx264`, `-pix_fmt yuv420p`, `-movflags +faststart`.
- No toques `src/`, `public/` ni `dist/` salvo lo estrictamente necesario para la fiesta demo (que
  ya existe, no necesitas tocar nada ahí).
- No instales nada global. `puppeteer-core` ya es dependencia autorizada del proyecto.
- Guarda todo en `design/video-frozen/` (créala) — no mezcles con `design/explicativo/` que es del
  video genérico.

---

## 9. Tu reporte final debe decir

- Si `gdigrab` funcionó en tu entorno o si tuviste que usar el Plan B (pantallazos estáticos), y
  por qué.
- Cuántos intentos tomó que la ruleta cayera en Olaf.
- Ruta exacta de cada archivo entregado.
- El `ffprobe` del video final pegado literal.
- Cualquier otro personaje/juego que hayas tenido que grabar aparte para completar el guion.

**Sé honesto si algo no salió.** Un video más corto pero real es mejor que uno más largo con
huecos rellenados a la fuerza.
