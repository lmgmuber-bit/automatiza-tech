# Asómate: cómo agregarlo a una temática nueva

**Estado (2026-09-09):** funcionando en **spidey** y **hielo**. Luis decidió dejar el resto
**para cuando entre una fiesta de esa temática**, porque no cuesta créditos pero sí varias
horas de revisión a ojo, y ninguna de esas temáticas tiene fiesta agendada.

Esta receta existe para no volver a descubrir todo desde cero. Lo de abajo se aprendió
equivocándose.

---

## Qué se puede y qué no

| Temáticas | Se puede |
| --- | --- |
| carreras, familia-canina, heroes, kpop, tropical | **Sí.** Tienen los 6 recortes `*-cut.png` y `fondo-sala.jpg` de 1080×1920 |
| baby-nube, baby-rosas, baby-safari | No aplica: son baby shower, no tienen personajes |
| mickey, cachorros, princesas, dinos, sirenas, juguetes | No. Están en `themes.json` pero **no tienen ni una imagen** en el servidor |

**Cero créditos.** El fondo se reutiliza de `fondo-sala.jpg`, que ya existe con la medida
exacta; los recortes con hueco se derivan de los `*-cut.png` que ya están.

---

## La receta

Los scripts quedaron en el scratchpad de la sesión del 2026-09-09. Si ya no están, lo que
hacen está descrito abajo con suficiente detalle para reescribirlos.

1. **`tematica.py <tema> ver`** — propone el óvalo de cada personaje y saca una hoja de
   contacto (`prototipo/huecos-<tema>.jpg`) para revisarla a ojo.
2. Ajustar en **`prototipo/<tema>-ajustes.json`**, por personaje: `corte_px` (dónde está el
   mentón), `dx`, `dy` (correr el óvalo) y `kx`, `ky` (agrandarlo o achicarlo).
3. **`tematica.py <tema> hacer`** — escribe los PNG con el hueco y `geo-<tema>.json`.
4. **`instalar-asomate.py <tema>`** — recorta al contorno de la figura, cuantiza a 220
   colores y anota la geometría en `themes.json`. **Corre las coordenadas al nuevo origen**:
   sin eso el hueco queda desplazado.
5. Copiar `fondo-sala.jpg` de la temática a `themes/<tema>/asomate/fondo.jpg`.
6. Agregar `boton` y `titulo` al bloque `asomate` de esa temática en `themes.json`. El nombre
   **cambia por temática**: en una fiesta de hielo, "sé el héroe" no significa nada.
7. Subir: `themes/<tema>/asomate/` completo y `data/themes.json`.

La guía sobre la cámara, el mando izquierda/derecha y el ajuste automático por cara detectada
(2026-09-10) **no piden nada por temática**: salen de `rx`/`ry` del hueco que anota `instalar-asomate.py`. Si el hueco está bien
anotado, la guía está bien; si el hueco está corrido, la guía lo va a estar igual.

`cb_theme_asomate()` en `lib.php` publica el modo **solo si los archivos existen en disco**,
así que una temática a medias no rompe nada: simplemente no ofrece el botón.

---

## Los errores que ya se cometieron

**El hueco es un óvalo DENTRO de la cabeza, no un corte en el cuello.** El primer intento
detectaba el cuello y cortaba ahí: a Ghost-Spider la partió por el pecho. Cortar por el
cuello además se lleva la capucha, el pelo o la máscara, que es justo lo que enmarca la cara
del niño.

**La caja de la cabeza se mide con el tramo continuo más largo de cada fila**, no con el
recuadro opaco. El recuadro lo contaminan las manos levantadas y los destellos, y el óvalo
sale descentrado.

**La pose asimétrica rompe la detección automática.** Spin (el arácnido de traje negro) está
en salto con los brazos abiertos: entre el mentón y `corte_px` entran las filas donde el brazo
se pega a la cabeza —una fila mide 499 px contra los 462 de la cabeza sola— y eso corrió el
centro 12 px a la derecha. El óvalo se comía un lente de la máscara entero. **Se arregló con
`dx: -0.026`, y lo detectó Luis en producción, no la revisión.** Los otros cinco personajes
están de pie y de frente y ninguno falló. Regla: **cualquier personaje que no esté de pie y
de frente hay que mirarlo dos veces.**

**Los personajes no comparten la proporción cabeza/cuerpo.** La cabeza de Elsa es el 15% de
su cuerpo y la de Olaf el 28%. Escalando por el hueco de la cara, los cuerpos salen de
tamaños absurdos; escalando por la altura, los huecos quedan de distinto tamaño, que se nota
mucho menos. Se escala por **altura**, y se calcula la mayor altura que TODOS pueden alcanzar
sin salirse de su carril: escalando cada uno por separado, Kristoff salía a la mitad de Olaf.

**El óvalo tiene que quedar DENTRO de la figura, y eso se mide.** Para cada personaje, tomar el
recorte original y calcular qué fracción del borde exterior del óvalo (radio 1,00 a 1,05) cae
sobre píxeles opacos: tiene que dar 100 %. Elsa daba 88 % y Olaf 59 % (2026-09-10): con la
foto ajustada, asomaba por fuera del personaje. Se corrige buscando en una grilla de escalas y
corrimientos el óvalo más grande que dé 100 % y siga cubriendo cejas a mentón.

**Cuidado con el CDN.** Los recortes se llaman siempre igual y se sirven con caché de 30
días. `cb_theme_asomate` les pone `?v=<mtime>`; si se agrega otro recurso de temática que
pueda corregirse después, necesita el mismo sello. Y al verificar, **comparar píxeles y no
md5**: el CDN de Hostinger reencoda los PNG.

---

## Cuánto cuesta

Cero créditos y unas dos o tres horas por temática, casi todo revisando los 6 huecos a ojo y
corrigiendo los que la detección automática deja mal. Con spidey y hielo hicieron falta varias
vueltas cada una.

---

## Temáticas de peluches con cuerpo nuevo: brujitas y navidad (2026-09-30)

"Cero créditos" vale cuando la temática ya tiene seis recortes de pie y de frente. Las dos temáticas infantiles nuevas tienen
peluches con poses de saludo (sentado, flotando, alas abiertas, brazo levantado) y **con eso Asómate no sirve**: en una foto de
grupo manda la figura más ancha (`alturaComunAsomate` en `src/App.jsx`). Con los recortes de los saludos, el peor trío medía
**450 px** de alto (calabaza redonda 0,75 de ancho sobre alto, fantasma 0,69, murciélago con las alas abiertas); con cuerpos
de pie, **637 px** en Noche de Brujas y **583 px** en Navidad. Por eso se hicieron cuerpos nuevos.

| Paso | Cómo | Costo |
|---|---|---|
| Cuerpo de pie | `alibaba/qwen-image-3/edit` por la API de Higgsfield, con la imagen aprobada del personaje como referencia (`image_urls`): mismo diseño, de frente, brazos abajo, fondo gris liso. Script `tematicas-2026-10/asomate_generar.py` (fuera del repo) | USD 0,04 de lista por imagen; 16 imágenes buenas = USD 0,64 (12 cuerpos y 4 repeticiones) |
| Recorte y hueco | `design/generadores/asomate-tematicas/asomate_tematica.py`: `cuadricula` (para medir), `ver` (hoja de contacto), `hacer` (instala y anota `themes.json`) | 0 |
| Óvalos | a ojo, en `ajustes-<tema>.json` | unas 3 horas para las dos |

**Lo que se aprendió:**

- **Sin `aspect_ratio=9:16,image_size=portrait_16_9` el modelo entrega 1024x1024**; con ellos, 720x1280 (probado con el murciélago
  y la calabaza). Hay que pedir además que la figura llene el 90 % del alto, si no ocupa el 60 % y los píxeles se pierden.
- **El modelo responde "temporarily unavailable" a ratos**, más si se piden varias a la vez (12 de 12 fallaron con seis
  peticiones en paralelo). Los fallos no se cobran según la referencia de la API (no se comprobó en la consola). El script
  reintenta hasta 4 veces con pausa y usa dos hilos.
- **Hay que pedir la silueta, no solo la pose.** "Brazos abajo" no alcanzó para el muñeco de nieve (las ramas siguen abiertas)
  ni para la calabaza (pidió el mismo ancho); para achicarla se pidió "más alta y angosta, piernas largas": el cuerpo de
  Pepa Calabaza quedó de 0,48 de ancho sobre alto y con piernas largas. La versión redonda queda en `tematicas-2026-10/brujitas/asomate/calabaza-v2.png`.
- **El óvalo cubre los ojos Y las cejas del muñeco.** Si no, asoman como cejas de más sobre la cara del niño (pasó con la
  calabaza, el gato, el duende y el pingüino en la primera pasada). La hoja `vista-huecos` (agujero en magenta) lo muestra.
- **Pelo de lana:** los huecos entre hebras tienen alfa bajo y hacían fallar la comprobación del borde de la Señora Pascuera,
  que se achicaba sola hasta dejar los lentes afuera. `solida()` cierra esos huecos antes de medir el borde.
- **`asomate.suelo`** (nuevo, opcional): fracción del alto donde pisan los pies de un grupo. El piso de la escena de Navidad
  empieza en 0,86 y el 0,8 de siempre dejaba al grupo flotando delante de la pared (visto en el navegador). Lo publica
  `cb_theme_asomate` (solo entre 0,6 y 0,95). El diploma usa su propia banda (`suelo` 0,79).
- El fondo de Asómate es `fondo-escena.jpg` de la temática (la escena despejada), sin copiarlo a `asomate/`: `fondo` acepta
  cualquier ruta relativa a la carpeta de la temática.

Prueba: `tests/frontend/asomate-infantiles.test.mjs` (hueco transparente por dentro, figura por fuera, medidas del PNG, peor
trío) y las 12 comprobaciones nuevas de `tests/backend/ferias-http.php`. Para verlo con la cámara simulada:
`local-eventos/asomate-de-prueba.mjs <tema> <1-3> <cartas> <salida.jpg>` (con `DIPLOMA=1` sigue hasta el diploma).
