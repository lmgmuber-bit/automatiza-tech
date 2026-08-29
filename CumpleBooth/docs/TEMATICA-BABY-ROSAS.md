# Bebé entre Rosas — la tercera temática de baby shower

Pedido de Luis (2026-08-28): *"quiero una temática rosado de una niña"*.

Slug: **`baby-rosas`**. Rosa polvo y crema, no rosa chicle: las otras dos
—`baby-nube` (azul noche) y `baby-safari` (verde salvia tarde)— están en un
registro adulto y sobrio, y una temática que se salga de ahí va a parecer de
otro producto puesta al lado.

## Estado — COMPLETA según la tabla A-BS (2026-08-29)

| Pieza | Estado |
|---|---|
| Paleta, nombre, confetti | Hecho |
| Bloque CSS de la invitación | Hecho — `[data-theme="baby-rosas"]`, chispas de pétalos |
| `fondo-banner.jpg` | Hecho — 1080×1920, generado |
| `fondo-sala.jpg` | Hecho — 1080×1920, marco grande vacío |
| `frameBox` calibrado | Hecho — **552×552 px**, verificado con foto compuesta |
| Registro en `themes.json` y presets | Hecho, y los dos valores comprobados iguales |
| `musica-fondo.mp3` | **Prestada de `baby-nube`** — ver abajo |
| Recorrido de 6 videos | No hecho. Opcional: no lo exige la tabla A-BS |

Costo: **6 créditos de Higgsfield** (3 imágenes × 2). El primer `fondo-sala`
salió con un marco ovalado y alto: el cuadrado más grande que cabía adentro
eran 352 px, la mitad que en Safari (714 px), y la cara del invitado habría
salido chica. Se pidió de nuevo con marco grande, rectangular y centrado, y
quedó en 552 px. Los 2 créditos del descarte valen menos que una temática con
la foto enana.

## La paleta

Los mismos nueve tokens que las otras dos, en el mismo orden y con el mismo rol.

```json
"colors": {
  "accent":     "#C4708C",
  "accentSoft": "#F8E6EB",
  "yellow":     "#E3C08D",
  "ink":        "#452A34",
  "bgLight1":   "#FDF7F9",
  "bgLight2":   "#F4E1E7",
  "dark1":      "#7B3F55",
  "dark2":      "#532A3A",
  "dark3":      "#DFA2B6"
},
"confetti": ["#DFA2B6", "#FFFFFF", "#E3C08D", "#F4E1E7", "#C4708C", "#F7EFE2"]
```

El `yellow` es el mismo dorado tenue de las otras dos y no un dorado rosado:
es el color del acento cálido en toda la línea de baby shower, y cambiarlo
rompería el parecido de familia justo en el detalle que lo sostiene.

Resto de la ficha:

```json
"nombre": "Bebé entre Rosas",
"franquicia": null,
"publico": "nina",
"modalidad": "baby_shower",
"diploma": "Invitada de honor de Bebé entre Rosas",
"personajes": [],
"musicaHint": "Cuna con cuerdas suaves",
"transition": "none"
```

`personajes: []` no es un olvido: el test lo exige. Si trae personajes es que
alguien copió una temática infantil sin limpiarla.

## En la invitación

Las chispas de `baby-nube` son polvo de estrellas y las de `baby-safari`
luciérnagas de tarde. Las de rosas son **pétalos que caen**: rosa polvo sobre
crema, borde irregular en vez de círculo, y el vaivén más ancho, porque un
pétalo se mece al caer y una luciérnaga no.

Los otros dieciséis grupos del bloque "la espera" —la foto que respira, los dos
contadores, la lámina— se comparten con las otras dos temáticas sin cambios.

## Los prompts de los fondos

Los dos en **9:16, 1080×1920**, y con las dos reglas que costaron rehacer
trabajo antes:

1. **Nada se cruza por delante del marco.** En el primer Safari el león se
   sentaba delante y la foto del invitado le comía la melena.
2. **Sin superficies escribibles en cuadro.** No basta pedir "sin texto": el
   generativo estampó *"Subby Shower"* en un banner a pesar de la instrucción.
   Lo que funciona es sacarle la superficie.

### `fondo-banner.jpg` — pantalla de bienvenida

> Vertical 9:16 photograph of a baby shower corner decorated in dusty rose and
> cream. A cluster of pale pink, blush and cream balloons gathered high in one
> corner, a garland of preserved pale roses and eucalyptus running along the
> upper edge, a cream knitted blanket folded on a low wooden stool, warm
> afternoon light falling through a sheer curtain. Shallow depth of field, warm
> film grain, tender quiet mood. No people, no hands, no faces, no text, no
> signage, no lettering, no logos, no watermarks, no framed pictures, no
> blackboards, no banners.

### `fondo-sala.jpg` — fondo de la foto final

El difícil: **tiene que traer un marco decorativo vacío y despejado**, porque
el `frameBox` apunta adentro de ese marco.

Este es el prompt que **quedó**, con la corrección que importa en negrita:

> Vertical 9:16 photograph of a baby shower photo backdrop in dusty rose and
> cream. **A LARGE EMPTY rectangular decorative frame** with a soft cream-and-gold
> moulding **dominates the wall: it is centered and occupies most of the upper two
> thirds of the image, wide and tall, almost square in shape.** The inside of the
> frame is completely empty and shows only bare smooth plaster wall — nothing
> hangs inside it, nothing is written inside it, nothing crosses in front of it,
> and nothing touches its edges. ALL decoration sits BELOW the frame and outside
> its outline, along the floor: pale pink and blush balloons resting on the wooden
> floor, preserved pale roses in ceramic vases, eucalyptus branches, a cream
> knitted blanket over a low wooden stool. Warm afternoon light, shallow depth of
> field, warm film grain, tender quiet mood, elegant and restrained, not candy
> pink. No people, no hands, no faces, no text, no signage, no lettering, no
> logos, no watermarks.

**El primer intento pidió un marco ovalado colgado alto** —copiando el montaje
de Safari, que evita que la decoración se cruce— y salió precioso, pero un
óvalo alto deja poco espacio para un cuadrado: el `frameBox` daba 352 px contra
los 714 de Safari, y la cara del invitado habría salido chica. La lección para
la próxima: **al pedir el fondo hay que decir el tamaño y la forma del marco,
no solo que esté vacío y despejado.** Un marco grande, rectangular y centrado
cumple las dos reglas igual y además deja espacio.

## Cómo se calibró el `frameBox`

No es un valor a ojo. Se dibuja encima del fondo el rectángulo que devuelve
`getSquarePhotoGeometry()` con los valores candidatos, se ajusta y se vuelve a
mirar; dos iteraciones alcanzan, dejando un ~10% de aire respecto del borde
interior. **Y el overlay no alcanza: la prueba que vale es la foto ya
compuesta.** En Safari el recuadro se veía bien en el overlay y recién con una
foto encima se notó que quedaba al filo del riel.

El mismo valor va en dos lugares y tienen que coincidir: `frameBox` en
`themes.json` y `text_area` en `event-profile-presets.json`. Sin la entrada en
presets la temática cae a `theme_fallback`, que no define `base_image`, y la
página muestra dos veces el mismo banner — una degradación silenciosa que ya
tiene test.

Entrada de presets, tal como quedó:

```json
"baby-rosas": {
  "status": "active",
  "background": "fondo-banner.jpg",
  "base_image": "fondo-sala.jpg",
  "layout": "storybook-kids-v1",
  "intro_style": "mágico",
  "surface": { "scrim": 0.73, "blur": 14, "saturate": 0.78,
               "title": "#f4e1e7", "surface_mix": 89, "hero_dim": 0.41 },
  "scene": "warm dusty-rose baby shower corner with pale roses, blush balloons, eucalyptus and soft afternoon light",
  "text_area": { "x": 0.2454, "y": 0.2078, "w": 0.5111, "h": 0.2875, "tone": "#452A34" }
}
```

## La música — prestada, y hay que decirlo

`musica-fondo.mp3` y `musica-juego.mp3` son **copias de `baby-nube`**. Es una
cuna suave, ya está loopeada sin costura y suena bien con rosas, así que la
temática funciona; pero es música prestada y no identidad propia. Generar una
con cuerdas suaves queda pendiente de que Luis lo pida, porque cuesta créditos
y la actual no molesta.

Sea cual sea, tiene que quedar **loopeada sin costura**: el fundido final se
cruza sobre el principio, o se oye un bajón cada 90 s. Así están las otras dos.

## El recorrido de videos

**No lo exige la tabla A-BS** y la temática se considera completa sin él. Pero
las otras dos lo tienen, así que al lado se va a notar.

Son 6 clips (5 capítulos + despedida), `seedance1_5`, 8 s, 720p, 9:16, sin
audio: **9,6 créditos cada uno, 57,6 en total**. El arco y los textos son los
mismos —lo que se cuenta es del bebé, no del decorado— y solo cambia el mundo:
rosas, tul, pétalos y luz de tarde en vez de nubes o monsteras.

De las narraciones, **la mitad se copia**: el capítulo 1, los dos del nombre y
los tres del cierre son idénticos entre temáticas. Habría que grabar solo los
capítulos 2, 3 y 5, que son los que hablan desde el mundo de cada una.
