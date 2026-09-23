# Generadores de imágenes de CumpleClick

Scripts que producen piezas que se usan de verdad: los carteles de marca que se descargan en
**Admin → Datos de la marca**, las piezas de Instagram, el marco de la foto grupal y la Anna de
gala de los juegos 3D. Ninguno usa IA ni gasta créditos: todo se compone con Pillow desde el
logo real del repositorio, Baloo 2 y las imágenes reales de cada temática.

Hasta el 2026-09-12 vivían solo en una carpeta temporal de sesión.

## Requisitos

Python 3.11 o más nuevo, con `pillow`, `numpy`, `segno` y `pymupdf`. Para medir los códigos QR
de un PDF, además `opencv-python`. Las salidas quedan en `salida/` de cada carpeta y no se
versionan.

## Carteles e Instagram — `arte/`

Correr en este orden, desde `arte/`:

1. `python fuente.py` — convierte Baloo 2 de WOFF a TTF en `ttf/`. Pillow no lee WOFF. Se hace
   una vez por máquina; la TTF no se versiona porque se deriva del mismo paquete del producto.
2. `python piezas.py` — piezas de Instagram.
3. `python carteles.py` — hoja de servicios y hoja de temáticas, A4.
4. `python avisos.py` — avisos grandes de QR de WhatsApp e Instagram, A4.
5. `python redes.py` — avatar y carrusel de Instagram.
6. `python carteles-por-medida.py` — las cuatro hojas en las medidas de acrílico
   (`salida/por-medida/`).
7. `python empaquetar.py` — la carpeta que se sube a `app/brand/carteles/`.

## Foto grupal — `arte/marco9.py`

Arma el fondo de la foto grupal con la moldura real de cada sala, recortada en nueve trozos.
El `frameBox` que imprime va a `public/data/themes.json`. `marco-grupal.py` es el primer
intento, dibujado a mano; se conserva porque explica por qué se descartó.

## Anna de gala — `anna-3d/`

1. `python extraer.py` — saca la textura del modelo.
2. `python mapa.py` — mapa de alturas: qué parte del cuerpo pinta cada zona de la textura.
3. `python vestido.py` — repinta la textura y arma `salida/hermana-gala.glb`.

El modelo original vive en el repositorio del juego 3D; otra copia se indica con la variable
`HERMANA_GLB`. `ver.html` dibuja un modelo desde tres ángulos: servir la carpeta `salida/` y
abrir `ver.html?m=hermana-gala.glb`.

## Lo que ya se pagó una vez

- **Corrección de error "q", no "h".** Más corrección son más módulos y, al mismo tamaño
  impreso, módulos más chicos: el código se lee peor.
- **El QR de WhatsApp no lleva mensaje prellenado.** Con texto pasaba de 29 a 53 módulos.
- **Pillow guarda los PDF como JPEG.** Sin `quality=100, subsampling=0` aparecen halos
  alrededor de las letras y del borde de los QR.
- **Lo que decide si un QR impreso se lee es el tamaño de cada módulo**, no el del código.
  Bajo 0,5 mm un teléfono empieza a fallar. Las hojas de servicios y temáticas en A6 quedan bajo
  ese límite; los avisos grandes de QR sirven en cualquier medida.
- **Las medidas de acrílico solo sirven si tienen la proporción de A4.** Porta menú de
  146 × 206 mm, A5, A6 y 13 × 18 entran; media carta, 10 × 15, 20 × 25 y 15 × 15 hay que
  rediseñarlas, no escalarlas.
- **El porta menú va a 146 × 206 y no a 150 × 210:** los 15 × 21 cm son la medida de afuera
  del acrílico y la ranura es menor.
- **Las hojas 1 y 2 no llevan el bloque de WhatsApp** (Luis, 2026-09-12): el número impreso
  era el único dato de contacto que no quería a la vista. Queda en el aviso grande de WhatsApp.
- **El CDN de Hostinger reencoda los PNG** y a Android se los entrega achicados en WebP.
  `app/brand/carteles/.htaccess` lleva `no-transform`; los PDF no se tocan.
