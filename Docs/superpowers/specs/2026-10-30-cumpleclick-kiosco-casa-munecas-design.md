# Kiosco de la temática Casa de Muñecas — diseño

Fecha: 2026-10-30 · Pedido de Luis · Rama `claude/cumple-casa-gabby` · Sesión Confeti

## Intención

La temática `casa-munecas` hoy solo tiene la invitación. Para la primera fiesta que la usa (cortesía, Plan
Premium completo) Luis pidió el kiosco «como el de Spidey, pero orientado a la casa de Gabby»: ruleta, saludo del
personaje, juegos antes de la foto, foto en la cabina con el personaje, show 3D (Premium) y la despedida con sus QR.
El objetivo es que la temática quede **completa según la tabla A** de `CumpleBooth/docs/TEMATICA-COMPLETA.md`, igual
que Spidey, y que sirva para cualquier fiesta futura con esta temática (nada lleva el nombre de una familia).

## Decisiones de Luis (30-oct)

- Basarse en Spidey, con el mundo de la serie.
- **Seis personajes**: Gabby (la estrella), Pandy Paws, Cakey, MerCat, Kitty Fairy y DJ Catnip. Fuente de quiénes son:
  Wikipedia, «Gabby's Dollhouse» (Cakey = cocina, MerCat = baño/spa, Kitty Fairy = jardín, DJ Catnip = sala de música).
- **La voz es la misma de la invitación**: la voz diseñada con Voice Design de ElevenLabs (voice_id en el material
  privado, `voz/diseno/voz-elegida.json`). Nunca clonar la voz del doblaje.
- **Reutilizar los videos que ya existen.**
- **Saludos**: Gabby con boca sincronizada (sale gratis del intro de la invitación, ver abajo); los cinco gatitos sin
  sincronizar (Wan 3.0 imagen-a-video, con la voz encima).
- Dar costo antes de gastar y registrar los gastos para Balance.

## Lo que se arma

Todo va en `CumpleBooth/public/themes/casa-munecas/` y en la entrada `casa-munecas` de `public/data/themes.json`.

| Pieza (tabla A) | Cómo se obtiene | Costo |
|---|---|---|
| `fondo-banner.jpg` | Ya existe | — |
| `musica-fondo.mp3` | Ya existe (solo servidor, `.gitignore`) | — |
| `despedida-casa-munecas.mp4` | Ya existe (voz «¡Te esperamos!» y sello) | — |
| `<personaje>.jpg` ×6 | Qwen Image 3 edit por la API (`alibaba/qwen-image-3/edit`, US$0,04) con las imágenes ya aprobadas de la serie como referencia de estilo; un personaje por imagen, de cuerpo entero, sobre un fondo liso de un color | ~US$0,25–0,50 con reintentos |
| `<personaje>-cut.png` ×6 | Local, sin costo: se genera sobre color liso y se quita ese color (la API no tiene quitar fondo) | — |
| `puzzle-<personaje>.jpg` ×6 | Local: recorte del retrato | — |
| `fondo-sala.jpg` | Qwen Image 3 edit: la sala de juegos rosada de la casa con un marco **grande, rectangular y centrado**, vacío, sin texto (lección de baby-rosas: un marco chico deja la cara chica) | ~US$0,04–0,12 |
| `roulette/roulette-background-v1.png` | Local, a partir de `fondo-banner.jpg`, igual que las otras temáticas | — |
| `saludo-gabby.mp4` | Gratis: los primeros ~4,3 s del clip A del intro de la invitación (MiniMax H3, boca sincronizada), que dicen «¡Hola, hola! ¡Soy Gabby! ¡Bienvenido a mi casa de muñecas!», sin nombres; con su sello CumpleClick | — |
| `saludo-<gatito>.mp4` ×5 | Wan 3.0 imagen-a-video por la API (US$0,05/s), 5 s, desde el retrato aprobado: el gatito saluda con la mano y se mueve en su ambiente; encima, una frase corta con la voz de la invitación | ~US$1,25 |
| Frases de los saludos ×5 | ElevenLabs con la voz diseñada, niveladas a −21,8 LUFS y verificadas con scribe_v1 | ~300 caracteres |

Total estimado: **unos US$2** de la API de Higgsfield (saldo verificado ~US$8 el 29-oct; confirmar antes) y unos 300
caracteres de ElevenLabs. Los precios salen del catálogo de la API (`hf_api.py models`, 30-oct).

Frases propuestas (el builder puede ajustarlas; deben ser genéricas, sin nombres de familia):
Pandy Paws «¡Hola! Soy Pandy Paws, ¡qué bueno que viniste a jugar!»; Cakey «¡Hola! Soy Cakey, ¡bienvenido a mi cocina!»;
MerCat «¡Hola! Soy MerCat, ¡vamos al spa!»; Kitty Fairy «¡Hola! Soy Kitty Fairy, ¡bienvenido a mi jardín mágico!»;
DJ Catnip «¡Hola! Soy DJ Catnip, ¡que suene la música!».

### En `themes.json`

- `personajes`: los seis, con `emoji`, `name` (nombres reales de la serie), `img` y su cadena `game`. Gabby es la
  estrella: un juego propio adelante (`ritmo`, como Spidey) y después `fichas` y `copos`; los demás `['fichas',
  'copos']`. Los `copos` de cada gatito con emojis de su mundo (cupcakes, burbujas y conchas, flores y mariposas,
  notas musicales, moños y estrellas).
- `fullGame`: `concierto3d` con un **escenario que ya existe** en `SHOW_STYLES`. Un escenario propio exige tocar
  `src/StageConcert3D.jsx`, `src/themeFlow.js` y `public/lib.php` y **recompilar y subir el kiosco entero**, que es
  de todas las fiestas (hay un baby shower en PROD el 31-oct). Queda como mejora posterior. Elegir el que mejor calce
  con el rosado y morado de la temática; su `label` dice «¡El Show de la Casa de Muñecas 3D!».
- `videos.despedida` ya está; `videos.welcome` solo si se arma (ver opcionales).
- `frameBox`: se mide contra el marco del `fondo-sala.jpg` nuevo, que se pide grande, rectangular y centrado.
  **Resuelto (mi decisión)**: `invitacion.php` no lee `frameBox` (la lámina se posiciona con el `text_area` de
  `event-profile-presets.json`, sobre `invitation/invitation-base-v1.jpg`). La aserción `caja.y === area.y` de
  `tests/frontend/cumpleCasaMunecas.test.mjs:81`, que escribí el 30-oct, solo tiene sentido en los baby shower, donde
  la lámina sí va sobre `fondo-sala`. Se reemplaza: el `text_area` sigue en 0,32–0,34 (la lámina no se toca) y el
  `frameBox` se valida contra el marco del fondo nuevo (dentro del lienzo y con un cuadrado de foto de 550 px o más).
- Agregar `casa-munecas` a `COMPLETAS` en `tests/frontend/themeFlow.test.mjs`: es lo que hace cumplir la tabla A.

## Metas, no-metas, anti-metas

- **Meta**: tabla A completa y verificada por la prueba; el kiosco de la fiesta funciona de punta a punta en la tablet
  con la temática.
- **No-metas** (posteriores, si Luis las pide): escenario 3D propio, Asómate, foto grupal, `photoSession`,
  `revelacion`, atlas del show.
- **Anti-metas**: recompilar o subir el bundle del kiosco; texto dentro de las imágenes generadas; clonar o imitar la
  voz de una persona; datos de la familia en el repo (es público); que la temática de la invitación cambie de aspecto.

## Restricciones

- Reglas duras de producción de `TEMATICA-COMPLETA.md`: seis personajes; sin texto en las imágenes; escenas de grupo
  rebotan por nsfw (si hace falta grupo, compositar recortes individuales); antes de subir, actualizar
  `docs/FTP-MANIFEST.md`.
- Regla de Luis: todo video e imagen lleva el sello CumpleClick (logo real + pie, `herramientas/sello_cumpleclick.py`).
  En los saludos y la bienvenida, discreto, como en el intro; los retratos y recortes de la ruleta y la cabina no lo
  llevan (son piezas de interfaz, no piezas que se comparten).
- Iterar en imagen hasta aprobar y recién después animar. Mostrar los seis retratos y el fondo de la sala a Luis antes
  de generar los videos.
- Despliegue: solo datos y archivos de la temática (`themes.json` y `themes/casa-munecas/`), con el mismo método de
  los scripts de esta rama (cotejar, respaldar, subir, verificar desde afuera); lo corre Luis.

## Opcionales que el builder puede hacer si sobran minutos (sin costo)

- `welcome-casa-munecas.mp4`: tramos del intro donde Gabby saluda, con una frase genérica nueva con la misma voz.
- `fondo-juego` del show: reutilizar `fondo-banner.jpg`.

## Material de partida (privado, fuera del repo)

En `C:/Users/luis_/Videos/cumpleclick-reels/material/cumple-gabby-2026-11-29/`:
- `video/parte-a-voz2.mp4`: clip A del intro (MiniMax H3, 1440×2560, 24 fps, 12,25 s) con la voz diseñada
  sincronizada; su audio calza 0 ms con `voz/parte-a.mp3` (nuestra grabación limpia). Los primeros ~4,3 s
  (pausa en 4,28–5,08 s) son el saludo genérico de Gabby. Usar el audio limpio, no el del clip.
- `hf/gabby-flux-1.png` y `hf/gabby-flux-2.png`: imágenes aprobadas de Gabby con Pandy, MerCat y Cakey (referencia de
  estilo); `referencia-gabby*.png|webp`: la referencia que mandó Luis.
- `voz/diseno/voz-elegida.json`: voice_id de la voz diseñada; `voz/grabar.py`: cómo se graba y nivela.
- `video/componer-intro.py` y `herramientas/sello_cumpleclick.py`: cómo se pone el sello en un video.
- Cliente de la API: `C:/wamp64/www/automatiza-tech/scratchpad/higgsfield-api/hf_api.py --cuenta n8n`.
El material de la fiesta lleva el nombre de la cumpleañera: lo que se produzca para la temática no puede llevarlo.

## Control obligatorio antes y después de subir

Agregar personajes y archivos a la temática no debe cambiar la invitación que ya está en PROD (su recorrido es una
lista fija en `$playlistOrdersByTheme` con solo la despedida, y no lee `personajes`). Aun así: correr
`tests/backend/invitacion-intro-personalizado-http.php` (trae una invitación de Casa de Muñecas) y la validación externa
de la invitación de la fiesta antes y después de desplegar.

Nombres de archivo (mi decisión): `gabby`, `pandy-paws`, `cakey`, `mercat`, `kitty-fairy`, `dj-catnip`.
La fiesta es el 29-nov-2026: hay tiempo para probar en la tablet antes.

## Lo que queda al builder

Modelo y prompts exactos de cada imagen; cómo quitar el fondo localmente; duración exacta de cada saludo (entre 5 y
7 s, como Spidey); los emojis de cada juego; qué escenario 3D existente usar; nivel de la música bajo la voz.
