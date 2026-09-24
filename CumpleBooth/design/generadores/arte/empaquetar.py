# -*- coding: utf-8 -*-
"""Arma la carpeta que se sube al servidor, con los archivos que descarga el admin.

De cada cartel salen tres archivos y cada uno tiene un trabajo distinto:
  .pdf        A4 a 300 ppp, para imprimir. Es el que importa.
  -web.png    la misma hoja a 150 ppp, para mandarla por WhatsApp sin que pese 3 MB.
  -previa.jpg miniatura de 420 px, solo para que el admin muestre lo que se va a bajar.

Las piezas de Instagram van en PNG al tamano exacto que pide la aplicacion, sin PDF: no se
imprimen.
"""
import os
import shutil

from PIL import Image

AQUI = os.path.dirname(os.path.abspath(__file__))
SALIDA = os.path.join(AQUI, "salida")
DESTINO = os.path.join(AQUI, "para-subir", "carteles")

IMPRIMIR = ["cartel-servicios-A4", "cartel-tematicas-A4",
            "aviso-qr-instagram-A4", "aviso-qr-whatsapp-A4"]
REDES = ["instagram-avatar-1080x1080",
         "instagram-1-marca-1080x1080", "instagram-2-como-funciona-1080x1350",
         "instagram-3-planes-1080x1350",
         # El carrusel, en el orden en que se sube a Instagram.
         "instagram-carrusel-1-portada", "instagram-carrusel-2-invitacion",
         "instagram-carrusel-3-cabina", "instagram-carrusel-4-juegos",
         "instagram-carrusel-5-album", "instagram-carrusel-6-tematicas",
         "instagram-carrusel-7-cierre"]


def main():
    if os.path.isdir(DESTINO):
        shutil.rmtree(DESTINO)
    os.makedirs(DESTINO)
    total = 0
    for base in IMPRIMIR:
        pdf = os.path.join(SALIDA, base + ".pdf")
        png = os.path.join(SALIDA, base + "-300ppp.png")
        shutil.copyfile(pdf, os.path.join(DESTINO, base + ".pdf"))
        hoja = Image.open(png).convert("RGB")
        # 150 ppp: la mitad de lado, la cuarta parte de peso, y sigue siendo legible en pantalla.
        web = hoja.resize((hoja.width // 2, hoja.height // 2), Image.LANCZOS)
        web.save(os.path.join(DESTINO, base + "-web.png"), "PNG", optimize=True)
        k = 420 / hoja.width
        hoja.resize((420, round(hoja.height * k)), Image.LANCZOS).save(
            os.path.join(DESTINO, base + "-previa.jpg"), "JPEG", quality=82, optimize=True)
    for base in REDES:
        png = os.path.join(SALIDA, base + ".png")
        shutil.copyfile(png, os.path.join(DESTINO, base + ".png"))
        im = Image.open(png).convert("RGB")
        k = 420 / im.width
        im.resize((420, round(im.height * k)), Image.LANCZOS).save(
            os.path.join(DESTINO, base + "-previa.jpg"), "JPEG", quality=82, optimize=True)

    for n in sorted(os.listdir(DESTINO)):
        tam = os.path.getsize(os.path.join(DESTINO, n))
        total += tam
        print("%10d  carteles/%s" % (tam, n))
    print("%10d  TOTAL (%.2f MB, %d archivos)" % (total, total / 1048576, len(os.listdir(DESTINO))))


if __name__ == "__main__":
    main()
