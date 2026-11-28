# "Chile en Volantín" — experiencia 3D inmersiva de Fiestas Patrias (brief para Codex, 2026-09-26)

Escrito por Claude como orquestador. Ticket `Docs/ORCHESTRATION/AT-CUMPLECLICK-023.yaml`. **Luis Miguel** aprueba y
paga: nada se despliega, se publica, se mergea ni gasta créditos sin su "ok" explícito.

## 1. Qué pidió Luis

> "Debe haber una temática bien chilena, con fondos y todo lo referente a Chile y sus fechas patrias. […] La tarea de
> la experiencia inmersiva de Halloween y de Chile y sus fechas patrias debe apoyarse en Higgsfield con su API."
> — Luis, 26-09-2026.

Hermana de "La Mansión de la Noche de Brujas" (ticket 022): misma pila, mismas reglas, otro mundo. Va con la temática
**Fiestas Patrias** (`fiestas-patrias`, la produce Claude con sus fondos; mientras no esté, la referencia es esta
sección y la paleta tricolor). Sirve para ferias dieciocheras, fondas, colegios en septiembre, empresas que celebran
las Fiestas Patrias y cualquier cumpleaños con temática chilena.

## 2. La experiencia

**Público:** todo público, familias. Alegre, orgullosa y cálida. **Partida de 60 a 90 s.**

**Idea central:** el jugador **encumbra un volantín** con el viento de septiembre y lo lleva volando por Chile. Con el
dedo tira o suelta el hilo (sube, baja, se inclina); con la tablet puede inclinarlo (giroscopio, con alternativa
táctil). Recoge **copihues** y **estrellas**, aprovecha las **rachas de viento** y esquiva ramas, cables y cerros.

**Recorrido (propuesta, Codex puede mejorarla):**
1. **Valparaíso:** cerros de casas de colores, ascensores, el mar abajo.
2. **La cordillera de los Andes** nevada al atardecer; **cóndores** planean al lado y dan impulso.
3. **El valle central:** viñedos, campos, una **ramada** con banderines tricolor a lo lejos.
4. **La noche del 18:** la fonda iluminada, **fuegos artificiales** y la cueca sonando.

**Los momentos "wow":**
- El paso de una escena a otra en pleno vuelo, sin cortes de carga.
- Los cóndores volando junto al volantín.
- **El cierre:** el volantín del jugador aparece **gigante con su foto de la cabina** sobre la fonda, entre los fuegos
  artificiales. Igual que en la Mansión: solo si viene de la cabina en la misma tablet y la foto está en el navegador;
  sin foto, el volantín lleva su nombre.
- Puntaje por copihues, estrellas y distancia, con **medallas** como los otros juegos.
- Opcional si alcanza: un **"pie de cueca"** de 10 s en la fonda (tocar al ritmo agitando el pañuelo).

**Cuidados propios de este tema:**
- 🔴 **La bandera de Chile se dibuja en código, exacta** (franja blanca arriba y roja abajo, cantón azul arriba a la
  izquierda con la estrella blanca de cinco puntas, proporción 2:3) y siempre con respeto: nunca en el suelo, rota ni
  deformada. **Nunca la pidas a la IA**: la deforma, y una bandera mal hecha es lo primero que ve un chileno. Lo mismo
  para el escudo: no se usa.
- Nada de "comisión" ni hilo curado: cortar volantines con hilo curado es peligroso y no se promueve.
- Sin marcas (bebidas, supermercados, cervezas) ni personajes de franquicias. Los huasos y las chinas, si aparecen, son
  figuras propias y respetuosas, sin caricatura.
- Todo el texto en español de Chile.

## 3. Técnica

Igual que el ticket 022, sección 3 (`CODEX-HANDOFF-JUEGO-NOCHE-BRUJAS-2026-09-26.md`): pila de Aurora de Cristal,
`.htaccess` con `AddType .mjs`, sin estilos en línea (CSP), parámetros `p`, `nombre` (de la FIESTA), `jugador`,
`volver`, `kiosco`; vertical 1200 × 2000; P10 ≥ 30 fps en la Tab A7; peso ≤ 15 MB; `prefers-reduced-motion` sin
destellos de fuegos artificiales. Claude lo registra como `volantin` en `cb_juegos()` y lo integra con el menú y la
feria.

## 4. Dónde se trabaja

Repositorio **nuevo y local**: `C:\wamp64\www\cumpleclick-juego-chile-volantin\` (git, rama `main`). Destino en PROD:
`app/juego/volantin/`. No toca `automatiza-tech`, `tucumple-repo` ni los otros juegos.

### 4.1 Arte con Higgsfield por su API

Mismas reglas que el ticket 022, sección 4.1: cliente `C:\wamp64\www\automatiza-tech\scratchpad\higgsfield-api\hf_api.py`
(leer su `README.md`; nunca abrir ni copiar una clave), solo imagen y video, **tope de gasto el que diga el prompt que
pegue Luis** (propuesto USD 1,50 para esta experiencia), registro en `assets/higgsfield/registro-generacion.json`,
sin texto dentro de las imágenes, iterar en imagen y animar solo lo aprobado.

Qué pedirle a la API: los **paisajes pintados por capas** para parallax (Valparaíso, la cordillera al atardecer, el valle
con viñedos, la fonda de noche), texturas, sprites de **cóndor**, **copihue** y volantines de colores (fondo liso, se
recortan en local) y, si suma, **un video de entrada de 5 s** (un volantín que se eleva sobre los cerros de
Valparaíso) con Kling image-to-video. Los banderines de las ramadas pueden ser tricolores lisos; la bandera completa,
en código.

### 4.2 Voces con ElevenLabs

Igual que el ticket 022, sección 4.2: si suma voz (una narradora que dice "¡Encumbra tu volantín!", el anuncio de la
fonda, un "¡Viva Chile!" al cierre), Codex escribe `assets/voces/guion.json` y Claude genera los MP3 con ElevenLabs,
nivelados. Sin los MP3, el juego funciona con subtítulos.

## 5. Entrega

La misma del ticket 022: commit en `main` del repo nuevo (sin push), `README.md`, registro de Higgsfield con el total,
capturas o video a 1200 × 2000 de las cuatro escenas y del cierre (foto de ejemplo, sin caras reales), fps medidos
(P10 y promedio) y lista FTP hacia `app/juego/volantin/` (`.htaccess` primero).

## 6. Música (la genera Luis en Gemini → Crear música, instrumental, duración completa)

> Cueca chilena instrumental y alegre para Fiestas Patrias: guitarra, arpa, acordeón y pandero, con palmas, en compás
> de 6/8, con ambiente de fonda en septiembre. Festiva y cálida. Sin voz, melodía original, volumen parejo de principio
> a fin, sin final abrupto.

## 7. Fuera de esta versión

Modo por sala con amigos; tabla de posiciones de feria; más escenas (Atacama, Rapa Nui, Torres del Paine).
