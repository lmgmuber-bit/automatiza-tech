# Música de las temáticas infantiles nuevas: prompts para Gemini

**Estado (2026-09-29):** faltan las dos pistas. Las genera Luis en Gemini (Lyria) con estos prompts; Claude las deja
listas para el kiosco. Ningún prompt nombra una canción, una película ni un artista: el filtro de seguridad de Lyria bloquea
voces de artistas y letras con derechos ([documentación de Lyria 3.5](https://ai.google.dev/gemini-api/docs/music-generation)),
y una melodía que se parezca a una conocida sería un problema de derechos en la fiesta de un colegio.

## Qué exige el kiosco

| Punto | Valor | Fuente |
| --- | --- | --- |
| Archivo | `themes/<slug>/musica-fondo.mp3`, un loop para todo el kiosco | `docs/TEMATICA-COMPLETA.md` (tabla A, fila 3) |
| Volumen dentro del kiosco | 0,15 normal y 0,04 mientras habla un video (bienvenida, saludo, despedida) | `src/feria/FeriaBooth.jsx` (`MUSICA`, `MUSICA_BAJO_VOZ`) |
| Duración de las pistas de hoy | 26 a 237 s (la más corta es `familia-canina`); las de baby shower miden 84 a 86 s | medido con `ffprobe` el 2026-09-29 |
| Sin fundido en los extremos | lo que más se olvida: en loop se oye como un bajón cada vez que reinicia | `docs/TEMATICA-COMPLETA.md` ("Al pedir música nueva…") |
| Test | `themeFlow.test.mjs` exige que exista y pese más de 200 KB | `tests/frontend/themeFlow.test.mjs` |

## Cómo se pide

Reglas de Lyria 3.5 que se usaron ([fuente](https://ai.google.dev/gemini-api/docs/music-generation)): pedir "instrumental
only, no vocals"; ser específico con instrumentos, BPM, tonalidad, ánimo y estructura; las marcas de tiempo
(`[0:00 - 0:15]`) controlan las secciones; la duración se controla con el prompt ("un par de minutos"); cada llamada es de un
solo turno y **el resultado cambia entre llamadas** aunque el prompt sea idéntico, así que conviene generar dos o tres tomas y
elegir. Lyria 3 Clip entrega siempre 30 s: sirve para probar el ánimo antes de pedir la pista larga.

Los prompts van en inglés porque los términos musicales rinden más así.

### Noche de Brujas (`brujitas`)

```text
Instrumental only, no vocals, no choir, no spoken words. A playful, mischievous and sweet Halloween party track for small children aged 3 to 8: friendly-spooky, like a cozy ghost party, never scary. Original melody that does not resemble any existing song, film or cartoon theme. Bouncy 4/4 at 108 BPM with a light swing feel, D minor with sunny major-chord lifts. Instruments: plucked pizzicato strings, celesta, glockenspiel, toy piano, marimba, a wobbly muted trombone, a clarinet answering the melody, warm upright bass, soft brushed snare, wood block, shaker and gentle hand claps. No horror drones, no church organ, no screams, no laughing, no howls, no thunder, no distortion, no dark cinematic tension. Warm, light and funny, with a steady medium-low energy that works as background music for a party while children talk and play. Structure: [0:00 - 0:15] soft intro with celesta and pizzicato bass establishing the groove; [0:15 - 0:50] main melody on celesta and glockenspiel with bass and light percussion; [0:50 - 1:25] second section where clarinet and marimba trade phrases, a little more playful; [1:25 - 1:50] main melody returns with the full band; [1:50 - 2:00] back to the same groove and chord as the opening. It must loop seamlessly: no fade-in, no fade-out, no ending, no final chord, no silence at the start or the end, and the last bar leads straight back into the first bar. Total length about 2 minutes.
```

### Navidad del Viejito Pascuero (`navidad`)

```text
Instrumental only, no vocals, no choir, no spoken words. A warm, joyful and cozy Christmas party track for small children aged 3 to 8: magical, cheerful, snug and full of wonder. Original melody in the spirit of a friendly modern Christmas song; it must not resemble any traditional carol, existing song or film theme. Bouncy 4/4 at 112 BPM with a light swing feel, G major. Instruments: glockenspiel, celesta, music box, sleigh bells, jingle bells, tambourine, soft acoustic guitar strums, warm upright bass, brushed drums, pizzicato strings, a soft string pad and two warm muted trumpets or French horns for harmony. No spoken "ho ho ho", no sound effects, no distortion, no sad or dramatic passages. Steady medium energy that works as background music for a party while children talk and play. Structure: [0:00 - 0:15] gentle intro with music box, sleigh bells and bass establishing the groove; [0:15 - 0:50] main melody on glockenspiel and celesta with guitar strums; [0:50 - 1:25] second section where pizzicato strings and horns answer the melody, slightly more playful; [1:25 - 1:50] main melody returns with the full band and sleigh bells; [1:50 - 2:00] back to the same groove and chord as the opening. It must loop seamlessly: no fade-in, no fade-out, no ending, no final chord, no silence at the start or the end, and the last bar leads straight back into the first bar. Total length about 2 minutes.
```

## Qué hace Claude con lo que llegue

1. Mide el nivel y el silencio de los extremos.
2. Si trae fundido de salida (Lyria suele hacerlo aunque se pida lo contrario), corta la cola y cruza 2 s del final sobre el
   principio, como se hizo con `baby-nube` y `baby-safari`: la diferencia de nivel entre el primer y el último segundo
   pasó de unos 20 dB a menos de 3,5 dB.
3. Exporta MP3 de 128 a 192 kbps a `public/themes/<slug>/musica-fondo.mp3` y agrega el `musicaHint` real en `themes.json`.
4. Corre `themeFlow.test.mjs` y suma la temática a la lista de completas.

## Lo que no se probó

Los prompts no se han pasado por Gemini: no hay forma de saber cómo suenan hasta que Luis genere las tomas. Lo que sí se
verificó es lo que el kiosco necesita (tabla de arriba) y las reglas de Lyria (fuente citada).
