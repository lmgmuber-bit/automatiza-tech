# Videos de la invitación de baby shower

Estado al 2026-08-27: **hecho.** Los 12 capítulos y los 16 MP3 de Alice están
en el repo y verificados en el navegador. Lo que sigue abajo es cómo se
hicieron y cómo verificar los que se agreguen después.

El hero **no** se instaló a propósito: se generaron cuatro clips y quedaron
fuera porque son atmósfera genérica, y `fondo-banner.jpg` —que respira con el
bloque "la espera" del CSS— es la decoración real que la familia va a tener.

Estado anterior (2026-08-26): **el código está listo y los archivos no existen.** La
invitación funciona sin ellos; cuando cada MP4 aparezca en su ruta, entra solo.
No hay que tocar PHP, CSS ni la base.

## De qué tratan estos videos

Carreras e Hielo apoyan la invitación en videos de **personajes saludando**:
Mate llega primero, Rayo cruza la meta, Elsa enciende la magia. Un baby shower
no tiene personajes — no hay a quién hacer saludar.

La primera versión de este documento resolvía eso contando **la sala
preparándose**: los globos, la cuna, las hojas. Luis lo corrigió, y tenía
razón: eso es la decoración, no lo que importa.

Un baby shower trata de **la espera**. De lo que significa traer un hijo al
mundo y del nacimiento que se está esperando. Eso es lo que cuentan los
capítulos, y por eso el arco es **el mismo en las dos temáticas**: lo que se
cuenta es del bebé, no del decorado. El decorado es lo único que cambia — de
ahí que los textos se repitan y los archivos no.

Los videos suman sobre el diseño que ya existe ("la espera", al final de
`public/assets/invitation.css`); no lo reemplazan. El hero pasa de foto que
respira a plano en movimiento, y aparece un recorrido que hoy no está.

## El arco

| # | Texto del capítulo |
|---|---|
| 1 | Hay esperas que se sienten distintas |
| 2 | Todo empieza mucho antes de nacer |
| 3 | Manos que ya aprendieron a esperar |
| 4 | El nombre de *(nombre)* ya se dice en voz alta |
| 5 | El mundo se acomoda para recibirte |
| 6 | Ven a esperar con nosotros |

El nombre del bebé es dinámico y vive en HTML: **nunca** se quema en el video,
o el clip sirve para una sola fiesta. El capítulo 4 lo dice en el texto; la
imagen no lo necesita.

## Las rutas exactas

Todo cuelga de `public/themes/<slug>/`. Cada archivo es independiente: el
recorrido aparece con el primero y va creciendo de a uno (`is_file()` por
capítulo en `invitacion.php`).

### Hero — los dos modos

| Archivo | Cuándo se usa |
|---|---|
| `invitation/invitation-scroll-v1.mp4` | Plan Básico: avanza con el dedo |
| `invitation/invitation-motion-v1.mp4` | Plan Full: se reproduce solo, una vez |

720x1280, **sin audio**, loopeable (contrato §5.1). El de scroll va codificado
con un keyframe por cuadro, si no el salto al arrastrar se siente pegado.

Si solo existe uno, los dos planes usan ese y se ven iguales. El póster cae a
`invitation/invitation-base-v1.jpg`, y si no existe, a `fondo-banner.jpg` — que
es lo que se ve hoy.

### Capítulos

Los mismos seis nombres en las dos temáticas:

```
invitation/capitulo-1-la-espera.mp4
invitation/capitulo-2-antes-de-nacer.mp4
invitation/capitulo-3-manos-que-esperan.mp4
invitation/capitulo-4-su-nombre.mp4
invitation/capitulo-5-el-mundo-se-acomoda.mp4
despedida-baby-nube.mp4   ·   despedida-baby-safari.mp4
```

La despedida es opcional: si falta, cae al genérico `videos/despedida.mp4`.

## Dos reglas que no son negociables

**Sin logo AT.** Estos clips son la invitación de un cliente, no una pieza de
marketing de AutomatizaTech. La regla del watermark AT aplica a Reels, Stories
y comerciales de AT; poner el logo de la agencia sobre el baby shower de una
familia estaría mal. Las temáticas infantiles tampoco lo llevan.

**Sin superficies escribibles en cuadro.** Ni carteles, ni pizarras, ni el
marco vacío de la pared. No basta con pedir "sin texto": ya pasó que el
generativo estampó *"Subby Shower"* y *"baby showwes"* inventados a pesar de la
instrucción. Lo que sí funcionó fue **sacar la superficie del prompt**.

## Los prompts

Estilo compartido, para que los seis clips de una temática parezcan del mismo
día y de la misma cámara:

> Cinematic live-action, shallow depth of field, very slow deliberate camera
> move, soft natural window light, warm film grain, tender and quiet mood,
> 9:16 vertical. No faces in frame, no text, no signage, no lettering, no
> logos, no watermarks.

Las manos sí pueden aparecer — son la mitad de la ternura y no identifican a
nadie. Caras no, para que el clip sirva a cualquier familia.

### El arco, plano por plano

1. **La espera** — una mecedora vacía que se mueve apenas, polvo flotando en un
   rayo de luz. Cámara: quieta; lo único que se mueve es la luz.
2. **Antes de nacer** — dos manos tejiendo una mantita diminuta. Cámara:
   acercamiento lentísimo a las agujas.
3. **Manos que esperan** — una mano apoyada sobre un vientre de embarazada, en
   contraluz suave, encuadre de la cintura para abajo. Cámara: quieta.
4. **Su nombre** — un gorrito de lana recién tejido sostenido en dos manos, que
   lo giran despacio. El nombre lo dice el texto del capítulo; la imagen no
   necesita mostrarlo, y mostrarlo obligaría a un clip por fiesta.
5. **El mundo se acomoda** — la habitación completa a la hora dorada, todo en
   su lugar, la cortina respirando. Cámara: paneo lentísimo.
6. **Despedida** — el rincón de la fiesta con la luz baja de la tarde.

### Lo que cambia entre las dos

Solo el decorado y la paleta. Los colores salen de
`public/data/themes.json` y de los dos `fondo-sala.jpg`; **no inventar
paleta**, el video tiene que parecer la misma sala de la foto.

| | `baby-nube` | `baby-safari` |
|---|---|---|
| Paleta | azul polvo, crema, dorado tenue | verde salvia, crema, dorado cálido |
| Luz | noche suave, luz de vela y luna | tarde, luz moteada entre hojas |
| Props | mantita de nubes, osito crema, móvil de fieltro | mantita de hojas, animales de peluche, monstera, rosas pálidas |
| Tejido (cap. 2 y 4) | lana celeste | lana verde salvia |

## Cómo verificar cuando lleguen

1. Dejar el archivo en su ruta y recargar la invitación local.
2. **Contar bytes de la página, no mirar el status.** Si el MP4 no está donde
   corresponde el servidor devuelve `index.html` con 200 y nada avisa. Con un
   capítulo presente la página pasa de ~12,9 KB a ~13,8 KB — medido.
3. `grep -c 'data-video-playlist'` tiene que dar 1, y el JSON de
   `data-playlist-data` tiene que traer el texto del capítulo con el nombre
   real de la fiesta.
