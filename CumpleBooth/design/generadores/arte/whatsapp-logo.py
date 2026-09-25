# -*- coding: utf-8 -*-
"""Imagen de marca para compartir por WhatsApp junto a un texto: SOLO el logo real sobre un fondo bonito
(degradé crema→lila, halo suave, globos de color desenfocados y confeti). Sin texto (2026-09-23, pedido de Luis).
Uso: python whatsapp-logo.py  -> salida/whatsapp-logo-1080x1080.png"""
import os
import random
from PIL import Image, ImageDraw, ImageFilter

import piezas as PZ
from piezas import (AMARILLO, CREMA, FUCSIA, LILA, TINTA, VIOLETA, centrado, confeti, degrade_suave, fuente, hexa,
                    logo, pegar_alto)

W = H = 1080
SALIDA = PZ.SALIDA


def globos(lienzo, rnd):
    """Círculos grandes de color, muy suaves y desenfocados, como globos al fondo."""
    capa = Image.new("RGBA", lienzo.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(capa)
    colores = [hexa(VIOLETA)[:3], hexa(FUCSIA)[:3], hexa(AMARILLO)[:3], hexa(LILA)[:3]]
    posiciones = [(120, 170, 150), (960, 130, 120), (90, 900, 130), (990, 930, 160), (540, 1010, 110), (560, 70, 90)]
    for i, (x, y, r) in enumerate(posiciones):
        c = colores[i % len(colores)]
        d.ellipse([x - r, y - r, x + r, y + r], fill=c + (70,))
    capa = capa.filter(ImageFilter.GaussianBlur(38))
    lienzo.alpha_composite(capa)


def main():
    im = degrade_suave((W, H), hexa(CREMA)[:3], (236, 226, 252))
    rnd = random.Random(11)
    globos(im, rnd)
    # halo claro detrás del logo para que respire
    halo = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    dh = ImageDraw.Draw(halo)
    dh.ellipse([W / 2 - 400, H / 2 - 400, W / 2 + 400, H / 2 + 400], fill=(255, 255, 255, 150))
    im.alpha_composite(halo.filter(ImageFilter.GaussianBlur(70)))
    # confeti disperso, evitando el centro
    puntos = []
    while len(puntos) < 70:
        x, y = rnd.randint(24, W - 24), rnd.randint(24, H - 24)
        if abs(x - W / 2) < 330 and abs(y - H / 2) < 330:
            continue
        color = rnd.choice([hexa(VIOLETA)[:3], hexa(FUCSIA)[:3], hexa(AMARILLO)[:3], hexa(LILA)[:3]])
        puntos.append((x, y, rnd.randint(5, 13), color, rnd.randint(70, 150)))
    confeti(im, puntos)
    # el logo real, grande y centrado (un poco más arriba para dejar el pie)
    pegar_alto(im, logo(), W / 2, H / 2 - 340, 600)
    # pie: Instagram y sitio web
    d = ImageDraw.Draw(im)
    y = centrado(d, "@Cumple_Click", W / 2, H - 150, fuente(800, 46), hexa(FUCSIA)) + 6
    centrado(d, "cumpleclick.com", W / 2, y, fuente(600, 36), hexa(TINTA))

    os.makedirs(SALIDA, exist_ok=True)
    ruta = os.path.join(SALIDA, "whatsapp-logo-1080x1080.png")
    im.convert("RGB").save(ruta, "PNG", optimize=True)
    print(ruta)


if __name__ == "__main__":
    main()
