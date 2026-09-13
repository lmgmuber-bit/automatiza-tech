# -*- coding: utf-8 -*-
"""Los cuatro carteles de marca, en las medidas de los porta menu de acrilico.

Pedido de Luis: los carteles de la marca tienen que poder imprimirse en las mismas medidas
que los carteles QR por fiesta, que ya traen selector, porque van dentro de un acrilico.

DE DONDE SALE LA IMAGEN. No se vuelven a dibujar: se rasteriza a 300 ppp el PDF que HOY esta
en produccion y esa imagen se lleva a cada medida. Asi lo impreso es exactamente lo aprobado,
sin riesgo de que el generador produzca algo distinto por una fuente o un dato que cambio.

QUE MEDIDAS ENTRAN Y CUALES NO. La hoja A4 tiene proporcion 1:1,414. Solo se generan las
medidas cuya proporcion cae a menos del 3% de esa, porque ahi el ajuste es imperceptible:

    porta menu 146×206  1,411   (el acrilico de Luis, y el que arranca elegido en el otro)
    A5         148×210  1,419
    A6         105×148  1,410
    foto 13×18 130×180  1,385
    A4         210×297  1,414

Las que NO se generan y por que: media carta (1,543), foto 10×15 (1,500), marco 20×25 (1,250)
y cuadrado 15×15 (1,000) se apartan entre un 9% y un 41%. Meter el mismo dibujo ahi obliga a
estirarlo —el texto sale deformado— o a dejar franjas blancas. Eso no se arregla escalando:
hay que rehacer el diseno para esa forma.

EL PORTA MENU VA A 146×206 Y NO A 150×210 A PROPOSITO. Los 15×21 cm son la medida de AFUERA
del acrilico y la ranura es algo menor; 2 mm por lado hacen que entre sin forzar. Es la misma
correccion que ya se hizo en los carteles por fiesta, despues de que la primera impresion
sobresaliera.

Todo se REDUCE desde A4 a 300 ppp: ninguna medida obliga a agrandar, asi que no se pierde
definicion en ningun caso.
"""
import pathlib

import pymupdf
from PIL import Image

AQUI = pathlib.Path(__file__).parent
# Se leen de `arte/salida`, que es lo que acaba de generar `carteles.py`, y no de la copia
# bajada de produccion: la hoja de servicios cambio y la de produccion todavia es la vieja.
# Se comprobo pixel a pixel que las otras tres salen identicas a las que hoy estan publicadas.
ORIGEN = AQUI / "salida"
# Dentro de la carpeta de salida, que no se versiona; en la carpeta temporal quedaba al lado.
SALIDA = AQUI / "salida" / "por-medida"
SALIDA.mkdir(parents=True, exist_ok=True)

PPP = 300
A4 = (210.0, 297.0)

# El orden es el del cartel impreso: primero los dos que se leen de cerca, despues los dos
# avisos de QR que llaman de lejos.
CARTELES = [
    ("cartel-servicios-A4", "Servicios y planes"),
    ("cartel-tematicas-A4", "Temáticas"),
    ("aviso-qr-whatsapp-A4", "Aviso QR WhatsApp"),
    ("aviso-qr-instagram-A4", "Aviso QR Instagram"),
]

MEDIDAS = [
    ("porta-menu-15x21", "Porta menú 15 × 21 cm (el nuestro)", 146.0, 206.0),
    ("a5", "A5 · 14,8 × 21 cm", 148.0, 210.0),
    ("a6", "A6 · 10,5 × 14,8 cm", 105.0, 148.0),
    ("foto-13x18", "Foto 13 × 18 cm", 130.0, 180.0),
    ("a4", "A4 · 21 × 29,7 cm", 210.0, 297.0),
]

TOLERANCIA = 0.03


def mm_a_px(mm):
    return int(round(mm / 25.4 * PPP))


# ── Los originales, rasterizados una sola vez ───────────────────────────────
maestros = []
for base, titulo in CARTELES:
    ruta = ORIGEN / (base + ".pdf")
    doc = pymupdf.open(ruta)
    pix = doc[0].get_pixmap(dpi=PPP)
    im = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)
    maestros.append((base, titulo, im))
    print("  maestro %-26s %d × %d px" % (base, im.width, im.height))

print()

for idm, nombre, ancho, alto in MEDIDAS:
    prop = alto / ancho
    desvio = abs(prop - A4[1] / A4[0]) / (A4[1] / A4[0])
    assert desvio < TOLERANCIA, "%s se aparta %.0f%% de A4: hay que rediseñar, no escalar" % (idm, desvio * 100)

    px = (mm_a_px(ancho), mm_a_px(alto))
    paginas = []
    for base, titulo, im in maestros:
        assert im.width >= px[0] and im.height >= px[1], "%s obligaría a agrandar %s" % (idm, base)
        paginas.append(im.resize(px, Image.LANCZOS))

    destino = SALIDA / ("carteles-cumpleclick-%s.pdf" % idm)
    # `resolution` es lo que fija el tamaño de página: sin él Pillow escribe una hoja gigante.
    # `quality=100, subsampling=0` porque Pillow guarda los PDF como JPEG y con la calidad por
    # omisión aparecen halos alrededor de las letras y del borde de los QR.
    paginas[0].save(destino, save_all=True, append_images=paginas[1:],
                    resolution=PPP, quality=100, subsampling=0)
    print("  %-22s %3.0f × %3.0f mm  (%d × %d px)  desvío %.1f%%  %s  %.1f MB"
          % (idm, ancho, alto, px[0], px[1], desvio * 100, destino.name,
             destino.stat().st_size / 1e6))
