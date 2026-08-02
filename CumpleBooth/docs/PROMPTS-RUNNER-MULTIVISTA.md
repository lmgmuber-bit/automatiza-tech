# Runner Full — atlas multivista de personajes

Fecha: 2026-07-29. Tema piloto: `familia-canina`.
Extensión: los seis mundos Full terminados.

## Decisión visual

El juego conserva el mundo Three.js interactivo. El avatar ya no gira un
recorte frontal: usa un atlas 2×2 con cuatro renders coherentes del mismo
personaje. El runtime muestra:

| Cuadrante | Vista | Uso |
|---|---|---|
| superior izquierdo | frente | introducción y celebración |
| superior derecho | tres cuartos derecho | cambio al carril derecho |
| inferior izquierdo | espalda/tres cuartos | carrera normal hacia la meta |
| inferior derecho | tres cuartos izquierdo | cambio al carril izquierdo |

Los atlas finales están en
`public/themes/<slug>/game3d/*-run-atlas.png`. Se generaron con la herramienta
integrada de imágenes y los recortes/retratos aprobados como referencia.
Higgsfield no estuvo disponible en la sesión. El magenta plano se eliminó
localmente con `remove_chroma_key.py`; los originales no se despliegan.

Cobertura aprobada en este cierre: `carreras`, `familia-canina` y `kpop`
tienen seis personajes; `tropical` tiene cuatro. Los dos tropicales restantes
y los atlas de `hielo`/`heroes` fueron bloqueados por el proveedor aun usando
camuflaje y no se sustituyeron por diseños genéricos. Esos casos conservan el
fallback aprobado. El archivo mantiene el nombre técnico para que el runtime
lo encuentre; ese nombre nunca se envía al proveedor dentro del prompt.

## Prompt maestro camuflado

Nunca agregar nombres de franquicia ni personajes al prompt. Sustituir
`[RASGOS_FÍSICOS]` por la descripción física privada del personaje.

```text
Use case: stylized-concept.
Asset type: production 2x2 sprite sheet for a vertical tablet 3D runner game.
Use the supplied image as strict identity reference. Preserve the exact same
premium rounded 3D toy character: [RASGOS_FÍSICOS]. Do not redesign.

Create exactly four separate full-body running views in one clean 2x2 sheet:
TOP LEFT front-facing running; TOP RIGHT right-facing three-quarter/profile
running; BOTTOM LEFT back-facing three-quarter running away with a physically
coherent back of head, body and tail; BOTTOM RIGHT left-facing
three-quarter/profile running. Opposite arms and legs in motion. Every view
must be head-to-toe, at the same scale, camera height, materials and lighting,
centered in equal quadrants with generous padding and no overlap.

High-end photorealistic 3D collectible toy rendering, smooth rounded geometry,
matte-to-satin surfaces, believable volume and soft studio lighting. Entire
background perfectly uniform solid #ff00ff chroma key, no floor, horizon,
gradient, texture, shadow or reflection.

No text, labels, grid, logo, watermark, props, additional characters, official
names, franchise logos, crops, duplicated view, flat 2D look or sticker
outline.
```

## Bloques físicos usados

- Azul protagonista: cuerpo azul cielo saturado; orejas y máscara azul marino;
  franja celeste, vientre y patas claros; hocico crema; nariz negra; cola
  marino.
- Hermana naranja: cuerpo naranja cálido; parches naranja oscuro; cara,
  vientre y patas crema; nariz marrón; orejas altas.
- Padre azul: proporción adulta robusta; cuerpo azul medio con pequeñas marcas
  claras; máscara y cola marino; franja celeste; vientre y patas claros.
- Madre coral: proporción adulta alta; cuerpo coral con pequeñas marcas crema;
  franja facial, vientre, patas y punta de cola crema; pestañas largas.
- Cachorra moteada: cuerpo marfil con manchas negras; una oreja negra y otra
  marfil moteada; nariz negra y cola pequeña moteada.
- Cachorra gris: cuerpo gris azulado con pequeñas marcas claras; máscara y
  orejas carbón; franja facial, vientre y patas blancos; cola gris esponjosa.

## Regla de continuidad

El nombre real puede mostrarse en la interfaz, pero nunca se incorpora al
prompt enviado al proveedor. El runtime deriva la ruta multivista únicamente
cuando el backend publica un `fullGame`. Si un atlas falta o no carga, usa el
recorte/JPG histórico como fallback y no interrumpe la fiesta.
