# "La Mansión de la Noche de Brujas" — experiencia 3D inmersiva de Halloween (brief para Codex, 2026-09-26)

Escrito por Claude como orquestador. Ticket `Docs/ORCHESTRATION/AT-CUMPLECLICK-022.yaml`. **Luis Miguel** aprueba y
paga: nada se despliega, se publica, se mergea ni gasta créditos sin su "ok" explícito.

## 1. Qué pidió Luis

> "Así como hizo juegos de Spidey, deben construir una experiencia inmersiva que sea sorprendente para la temática de
> Halloween." — Luis, 26-09-2026.

Es el cuarto mundo 3D de CumpleClick después de Reino de Hielo, los tres juegos arácnidos y Aurora de Cristal. Va con
la temática **Noche de Brujas** (`adulto-noche-brujas`, ya hecha: `public/themes/adulto-noche-brujas/` y su entrada en
`public/data/themes.json` del worktree `C:\wamp64\www\automatiza-tech\.worktrees\modo-feria\CumpleBooth`). Su paleta,
sus fondos y su tono son la referencia visual: **morado profundo y naranjo de vela, elegante y misterioso, nunca gore**.

Fecha que manda: **sábado 31 de octubre de 2026**. Tiene que estar probado en tablet antes, para venderlo en ferias y
cumpleaños de octubre.

## 2. La experiencia

**Público:** todo público, de 5 años a adultos (se juega en ferias, en cumpleaños infantiles de Halloween y en fiestas de
adultos). Misterio y sorpresa, **sin sangre, sin armas, sin sustos violentos**. Un niño de 6 años tiene que salir
riéndose, no llorando.

**Duración de una partida: 60 a 90 s.** En una feria hay fila.

**Recorrido (propuesta, Codex puede mejorarla):** el jugador entra de noche a una mansión y la cruza con un **farol**
que guía con el dedo. La luz del farol **enciende calabazas** y **revela fantasmitas amistosos** que se atrapan
alumbrándolos. Cuatro escenas encadenadas:
1. **El jardín:** portón de hierro, niebla baja, luna llena, calabazas por encender.
2. **El vestíbulo:** candelabros, una escalera, murciélagos que cruzan.
3. **El pasillo de los retratos:** los ojos de los cuadros siguen al jugador; libros y sillas que flotan.
4. **La galería final:** un marco dorado vacío que se ilumina…

**Los momentos "wow" (lo que hace que la gente lo recomiende):**
- **Relámpago** que ilumina de golpe la mansión entera y deja ver por un instante lo que hay en la oscuridad.
- **Mirar alrededor moviendo la tablet** (giroscopio), con alternativa táctil si el permiso se niega o no existe.
- **El cierre: la foto del jugador aparece en el retrato de la galería** ("ahora eres parte de la mansión"). Solo si
  llega desde la cabina en la misma tablet y la foto está en el navegador (misma origen `cumpleclick.com/app/`): no se
  sube nada nuevo ni se le pide nada al servidor. Si no hay foto, el retrato muestra su nombre en letras doradas.
- Puntaje final por calabazas encendidas y fantasmas atrapados, con **medallas** como los otros juegos.

**Sonido:** viento, crujidos de madera, un órgano suave lejano, risas de fantasmitas. Efectos sintetizados con WebAudio
o de bibliotecas **CC0** con la licencia anotada (Kenney, Quaternius, freesound CC0): la API de Higgsfield no hace audio.
La **música** la genera Luis en Gemini: el prompt está en la sección 6; mientras tanto, sin música o con un marcador
silencioso.

## 3. Técnica (lo que ya se aprendió con los otros juegos — leer antes de escribir)

- **Base:** la misma pila que Aurora de Cristal (`C:\wamp64\www\cumpleclick-juego-aurora-cristal`, rama `main` = PROD):
  Three.js en `vendor/`, módulos `.mjs`, `pantalla.mjs`/`juego/pantalla.js` para **volver al menú** y **pantalla
  completa**. Leer `CumpleBooth/docs/REVISION-JUEGOS-CODEX.md` y `CumpleBooth/docs/AURORA-INTEGRACION-CLAUDE.md`.
- 🔴 **`.htaccess` propio** con `AddType text/javascript .mjs` (y `model/gltf-binary .glb` si hay modelos): sin eso la
  pantalla queda negra sin ningún error visible.
- 🔴 **CSP `style-src 'self'`:** nada de `<style>` inyectado ni atributos `style=`; escribir estilos por CSSOM o en el CSS.
- 🔴 **Parámetros:** `?p=<slug de la fiesta>&nombre=<nombre de la FIESTA>&jugador=<quien juega>&volver=<url>&kiosco=1`.
  `nombre` es el de la fiesta, **no** el del jugador (ya se equivocó dos veces en otros juegos). Saluda a `jugador`.
- **Pantalla vertical** (Galaxy Tab A7, 1200 × 2000) y también horizontal si se gira; botones de 44 px o más.
- **Rendimiento:** P10 ≥ 30 fps en la Tab A7 (medirlo; Aurora tuvo 29,94 y se notó). Peso total ≤ 15 MB; los GLB
  comprimidos. Carga inicial ≤ 8 s en 4G con una pantalla de carga temática.
- `prefers-reduced-motion`: sin relámpagos que destellen ni sacudidas de cámara.
- Todo el texto en **español de Chile**. Sin nombres ni personajes de franquicias (nada de brujas, fantasmas o
  calabazas "de película": diseño propio).
- Puntajes: la tabla de la fiesta es por **medallas** (`puntajes.php`); Claude registra el juego como `mansion` en
  `cb_juegos()` y hace la integración con el menú y con el modo feria. Codex **no toca CumpleBooth**.

## 4. Dónde se trabaja

Repositorio **nuevo y local**: `C:\wamp64\www\cumpleclick-juego-noche-brujas\` (git, rama `main`), con la misma forma
que Aurora (`index.html`, `*.mjs`, `assets/`, `vendor/`, `tests/`, `scripts/`, `README.md`). Destino en PROD:
`app/juego/mansion/`. Claude lo sube a un repositorio **privado** de `lmgmuber-bit` cuando Luis lo apruebe (como los
demás juegos). **No toca** el repositorio `automatiza-tech`, ni `tucumple-repo`, ni los otros juegos.

### 4.1 Arte con Higgsfield por su API (pedido de Luis, 26-09: "debe apoyarse en Higgsfield con su API")

- Cliente: `C:\wamp64\www\automatiza-tech\scratchpad\higgsfield-api\hf_api.py`. Leer antes su `README.md`
  (trampas medidas: **una validación con el cuerpo completo encola un trabajo real y se cobra**; Kling no se deja
  cancelar). El cliente lee las claves solo, por rótulo: **nunca abras, copies ni imprimas una clave**.
- Qué hace la API: **imágenes y video**. No hace 3D ni audio. Úsala para lo que se ve: fondos pintados por capas para
  parallax (cielo con luna, silueta de la mansión, niebla), texturas (madera, piedra, papel mural; pedir "seamless
  tileable texture"), los **retratos pintados del pasillo** (personajes inventados, nada de caras reales ni
  famosas), sprites de fantasmitas, murciélagos y calabazas (sobre fondo liso, se recortan en local) y, si suma, **un
  video de entrada de 5 s** (la reja que se abre) con Kling image-to-video desde una imagen aprobada. Los modelos 3D
  salen de geometría procedural o CC0.
- Modelos y precio de lista (del cliente, `python hf_api.py estimar <modelo> ...`): imagen
  `marketing-studio/image/flare` USD 0,0162 (9:16 entrega 1520 × 2688; también acepta otras proporciones);
  video `kling-video/v3.0/pro/image-to-video` USD 0,084 por segundo (5 s = USD 0,42, entrega 1172 × 1764 a 24 fps).
- **Tope de gasto: el que diga el prompt que te pegue Luis** (propuesto USD 1,50 para esta experiencia). Sin tope en
  el prompt, no generes: arma la lista con costo y espera. Antes de cada `generar ... --si`, suma lo gastado; si una
  pieza pasa de USD 0,50 o el total pasaría el tope, para y pregunta.
- **Sin texto dentro de las imágenes** (la IA lo deforma): el texto va encima, en el juego.
- Registro obligatorio en `assets/higgsfield/registro-generacion.json`: pieza, modelo, prompt, `request_id`, USD de
  lista, estado. Los rechazos por moderación no se cobran; tras 2 rechazos de la misma pieza, cambia de enfoque.
- Iterar en imagen (barato) y animar solo lo aprobado; nunca iterar en video.

### 4.2 Voces con ElevenLabs (Luis, 26-09: "si necesitas voces, podemos usar ElevenLabs")

Si la experiencia gana con voz (una narradora que da la bienvenida, los fantasmitas que ríen o saludan, el anuncio
del final), **Codex escribe el guion y Claude genera las voces**: la clave de ElevenLabs está restringida a texto a
voz y transcripción, no se puede ver la cuota por API, y en CumpleClick ya hay reglas medidas para que se entiendan
(voces que sirven en español, frases simples, nivelar cada línea a −16/−17 LUFS y transcribir para comprobar).
Entrega `assets/voces/guion.json` con una fila por línea: `id`, `texto` (español de Chile, frases cortas y simples),
`quien` (narradora, fantasmita…), `tono` y `momento` del juego en que suena. Mientras no lleguen los MP3, el juego
funciona sin voz (subtítulo en pantalla). Claude devuelve `assets/voces/<id>.mp3` ya nivelados.

## 5. Entrega

- Commit en `main` del repo nuevo (sin push), `README.md` con cómo correrlo en local.
- `assets/higgsfield/registro-generacion.json` con cada pedido y el total gastado.
- Video o capturas a 1200 × 2000 de las cuatro escenas y del retrato final (con foto de ejemplo, sin caras reales).
- **fps medidos** (P10 y promedio) en Chrome con la tablet emulada y, si se puede, en la Tab A7.
- Lista FTP: ruta local → `app/juego/mansion/...`, OBLIGATORIO/OPCIONAL, orden (`.htaccess` primero).
- Anotar en el README lo que quedó sin probar.

## 6. Música (la genera Luis en Gemini → Crear música, instrumental, duración completa)

> Música instrumental de Halloween elegante y misteriosa, no de terror: clavecín, órgano suave, contrabajo en
> pizzicato, celesta y campanas lejanas, a 96 BPM en compás de vals. Juguetona y con suspenso, como recorrer una
> mansión encantada con una linterna. Sin voz, melodía original, volumen parejo de principio a fin, sin final abrupto.

## 7. Fuera de esta versión

Modo por sala con amigos (como el Circuito); el "espejo embrujado" en la cabina (un fantasma que aparece detrás de las
personas en la foto, sobre el recorte del modo fondo del ticket 021); tabla de posiciones de feria.
