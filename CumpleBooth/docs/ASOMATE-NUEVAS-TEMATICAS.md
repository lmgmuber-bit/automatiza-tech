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
