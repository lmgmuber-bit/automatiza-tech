# Videos de la invitación de baby shower

Estado al 2026-08-26: **el código está listo y los archivos no existen.** La
invitación funciona sin ellos; cuando cada MP4 aparezca en su ruta, entra solo.
No hay que tocar PHP, CSS ni la base.

## Por qué esto no se copia de las temáticas infantiles

Carreras e Hielo apoyan la invitación en videos de **personajes saludando**:
Mate llega primero, Rayo cruza la meta, Elsa enciende la magia. Un baby shower
no tiene personajes — no hay a quién hacer saludar.

Copiar el formato con la foto fija daría una versión pobre de la misma idea, y
por eso el diseño actual va por otro lado ("la espera", al final de
`public/assets/invitation.css`). Los videos **suman** sobre eso, no lo
reemplazan: el hero pasa de foto que respira a plano en movimiento, y aparece
un recorrido que hoy no existe.

El recorrido, entonces, no son saludos: es **la sala preparándose**, que es de
lo que trata un baby shower.

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

### Recorrido — `baby-nube`

| Archivo | Texto del capítulo |
|---|---|
| `invitation/capitulo-1-la-noticia.mp4` | Una noticia que cambia todo |
| `invitation/capitulo-2-la-cuna.mp4` | La cuna ya espera a *(nombre)* |
| `invitation/capitulo-3-las-nubes.mp4` | La sala se llena de nubes |
| `invitation/capitulo-4-la-luna.mp4` | La luna se asoma a mirar |
| `invitation/capitulo-5-el-osito.mp4` | El osito guarda el primer lugar |
| `despedida-baby-nube.mp4` | ¡Te esperamos! |

### Recorrido — `baby-safari`

| Archivo | Texto del capítulo |
|---|---|
| `invitation/capitulo-1-la-noticia.mp4` | Una noticia que cambia todo |
| `invitation/capitulo-2-la-manada.mp4` | La manada se prepara |
| `invitation/capitulo-3-la-selva.mp4` | La selva se viste de fiesta |
| `invitation/capitulo-4-los-globos.mp4` | Los globos ya están puestos |
| `invitation/capitulo-5-el-leon.mp4` | El león cuida la entrada de *(nombre)* |
| `despedida-baby-safari.mp4` | ¡Te esperamos! |

El nombre del bebé es dinámico y vive en HTML: **nunca** se quema en el video,
o el clip sirve para una sola fiesta.

La despedida es opcional: si falta, cae al genérico `videos/despedida.mp4`.

## Dos reglas que no son negociables

**Sin logo AT.** Estos clips son la invitación de un cliente, no una pieza de
marketing de AutomatizaTech. La regla del watermark AT aplica a Reels, Stories
y comerciales de AT; poner el logo de la agencia sobre el baby shower de una
familia estaría mal. Las temáticas infantiles tampoco lo llevan.

**Sin superficies escribibles en cuadro.** Ni carteles, ni pizarras, ni el
marco vacío de la pared. No basta con pedir "sin texto": ya pasó que el
generativo estampó *"Subby Shower"* y *"baby showwes"* inventados a pesar de la
instrucción. Lo que sí funcionó fue **sacar la superficie del prompt**. Por eso
el capítulo 4 de Safari dejó de ser "el rincón de las fotos" y pasó a ser los
globos.

## Los prompts

Estilo compartido, para que los seis clips de una temática parezcan del mismo
día y de la misma cámara:

> Cinematic live-action, shallow depth of field, slow deliberate camera move,
> soft natural window light, warm film grain, 9:16 vertical, no people's faces
> in frame, no text, no signage, no lettering, no logos, no watermarks.

### baby-nube

1. **La noticia** — un par de escarpines de lana color crema sobre una manta
   doblada, un rayo de luz que cruza despacio. Cámara: acercamiento lentísimo.
2. **La cuna** — cuna blanca de madera, móvil de nubes de fieltro girando
   apenas, cortina que se mueve con el aire. Cámara: paneo lateral.
3. **Las nubes** — guirnalda de globos azul polvo y crema, la cámara sube por
   ella hasta perderse. Cámara: tilt hacia arriba.
4. **La luna** — luna de utilería iluminada por dentro, luz cálida, penumbra
   azul alrededor. Cámara: quieta, solo la luz cambia.
5. **El osito** — osito de peluche crema sentado en un pedestal, tres velas
   encendidas al lado, la llama se mueve. Cámara: acercamiento muy lento.
6. **Despedida** — la sala completa en penumbra azul, las velas encendidas.

### baby-safari

1. **La noticia** — los mismos escarpines, ahora sobre una hoja de monstera.
2. **La manada** — jirafa, cebra y león de peluche en fila sobre pedestales
   verde salvia. Cámara: paneo lateral que los recorre.
3. **La selva** — hojas de monstera moviéndose con luz moteada de tarde.
   Cámara: acercamiento entre las hojas.
4. **Los globos** — globos verde oliva y crema apilados, rosas pálidas encima.
   Cámara: tilt hacia arriba.
5. **El león** — león de peluche echado, luz de tarde, polvo flotando.
   Cámara: acercamiento lentísimo.
6. **Despedida** — el rincón completo con la luz baja de la tarde.

Los colores salen de `public/data/themes.json` y de los dos `fondo-sala.jpg`.
No inventar paleta: el video tiene que parecer la misma sala de la foto.

## Cómo verificar cuando lleguen

1. Dejar el archivo en su ruta y recargar la invitación local.
2. **Contar bytes de la página, no mirar el status.** Si el MP4 no está donde
   corresponde el servidor devuelve `index.html` con 200 y nada avisa. Con un
   capítulo presente la página pasa de ~12,9 KB a ~13,8 KB — medido.
3. `grep -c 'data-video-playlist'` tiene que dar 1.
