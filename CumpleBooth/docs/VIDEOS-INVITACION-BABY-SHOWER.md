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
| 3 | Alguien ya te está esperando |
| 4 | El nombre de *(nombre)* ya se dice en voz alta |
| 5 | El mundo se acomoda para recibirte |
| 6 | Ven a esperar con nosotros |

Ningún clip tiene personas. Una versión anterior de este documento proponía
manos tejiendo y una mano sobre un vientre de embarazada; Luis la descartó y
pidió que la espera se contara con los objetos de cada temática. Se deja
escrito para que nadie la reintroduzca.

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
invitation/capitulo-3-alguien-te-espera.mp4
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

Modelo `seedance1_5`, 8 s, 720p, 9:16, `generate_audio: false`. 9,6 créditos por
clip. Estilo compartido, para que los seis de una temática parezcan del mismo día
y de la misma cámara:

> Cinematic live-action, vertical 9:16, shallow depth of field, warm film grain,
> tender quiet mood. [la escena]. [el movimiento de cámara]. No people, no hands,
> no faces, no text, no signage, no lettering, no logos, no watermarks.

### Lo que se generó, plano por plano

| # | `baby-nube` | `baby-safari` |
|---|---|---|
| 1 | Cuna vacía en penumbra azul; una lámpara se enciende despacio y la llena de luz. Cámara quieta | Sala vacía al atardecer, luz moteada cruzando entre monsteras, polvo flotando. Cámara quieta |
| 2 | Móvil de nubes girando lentísimo, visto desde abajo como lo vería el bebé | Los peluches formados en sus pedestales, la luz corriéndose sobre ellos. Paneo lateral |
| 3 | El osito crema solo, tres velas encendidas, su sombra moviéndose. Acercamiento lentísimo | El león echado en primer plano, la tarde encendiéndole la melena. Acercamiento lentísimo |
| 4 | Gorrito de lana celeste sobre una manta doblada | Gorrito verde salvia sobre una manta, junto a una rosa pálida |
| 5 | La luna encendida y los globos azules, la sala a media luz. Paneo | Paneo por el rincón: globos, hojas, animales, todo en su lugar |
| 6 | El rincón completo en penumbra con las velas prendidas | Los globos sobre la alfombra, a ras de suelo, sin pared en cuadro |

**La despedida de Safari se hizo dos veces.** La primera estampó letras
inventadas en un banner del fondo, a pesar de decir "no text, no signage, no
lettering" en el mismo prompt — el mismo fallo de *"Subby Shower"*. La toma
buena es la que **sacó la pared del encuadre**: cámara a ras de suelo mirando
los globos sobre la alfombra. Confirma la regla de arriba: no se gana repitiendo
la instrucción, se gana quitándole la superficie.

Los otros once se revisaron cuadro a cuadro y están limpios.

### La voz

16 MP3 con Alice (`voice_id Xb7hH8MSUJpSbSDYk0k2`, `eleven_multilingual_v2`),
por ElevenLabs directo. Los textos **no pronuncian el nombre del bebé**: si lo
dijeran, cada MP3 serviría para una sola fiesta en vez de para toda la temática.
El capítulo del nombre tiene dos versiones —`capitulo-4-su-nombre.mp3` y
`capitulo-4-sin-nombre.mp3`— y ninguna lo dice; el nombre lo pone el HTML.

Tres frases son idénticas en las dos temáticas porque son la tesis de la pieza:
la 1, la del nombre y la 6. Las otras hablan desde el mundo de cada una.

Van en `themes/<slug>/narracion-video/<nombre-del-clip>.mp3`. Además:

- `assets/audio/narracion-final-baby-shower.mp3` — reemplaza al de cumpleaños en
  "Guarda y comparte".
- `assets/audio/narracion-playlist-final-baby-shower-{nina,nino,neutro}.mp3` —
  el respaldo del cierre del recorrido, que en baby shower solo suena si el
  capítulo 6 no tiene su propio MP3.

### El cierre lleva género (2026-08-28)

Pedido de Luis: que Alice diga que **sus papás y seres queridos esperan al bebé
con ansias**. Esa frase le habla al INVITADO sobre el bebé, y ahí el pronombre
lleva género, así que el capítulo 6 pasó de un MP3 a tres por temática:

| versión | texto |
|---|---|
| `nina` | Sus papás y todos sus seres queridos **la** esperamos con ansias. |
| `nino` | Sus papás y todos sus seres queridos **lo** esperamos con ansias. |
| `neutro` | Sus papás y todos sus seres queridos esperamos con ansias **su llegada**. |

La neutra reordena la frase en vez de inventar una forma que nadie usa al
hablar; es la que suena cuando el sexo no está revelado, que es el caso de las
familias que hacen la fiesta justamente para revelarlo.

**El video sigue siendo uno solo por temática**: cambia la voz, no la imagen.
Con MP4 por sexo harían falta seis clips en vez de dos.

`$sufijoSexoBebe` se resuelve una vez en `invitacion.php`, junto al sexo, porque
lo usan dos cosas distintas —el capítulo 6 de cada temática y el respaldo
compartido— y calculado en cada lugar podrían separarse.
`tests/frontend/babyShowerVoice.test.mjs` verifica los nueve MP3 contra disco,
que sean MP3 de verdad y que la selección siga saliendo del sexo.

Los tres archivos anteriores —`despedida-baby-nube.mp3`,
`despedida-baby-safari.mp3` y `narracion-playlist-final-baby-shower.mp3`—
quedaron con el texto viejo y se borraron; siguen en el historial de git.
**`narracion-final-baby-shower.mp3` NO se tocó**: ese suena en "Guarda y
comparte" y le habla al invitado sobre guardar el enlace, no sobre el bebé.

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
