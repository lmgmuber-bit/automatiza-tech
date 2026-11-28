# -*- coding: utf-8 -*-
"""Imagen para WhatsApp (1080x1350) con el texto de "sígueme en Instagram" que Luis manda a sus contactos
(2026-09-23). Marca real (logo + Baloo 2 + paleta de piezas.py); sin emojis porque PIL no los dibuja.
Uso: python whatsapp-sigueme.py  -> salida/whatsapp-sigueme-1080x1350.png
(los TTF de Baloo 2 se derivan con `python fuente.py` si no está la carpeta ttf/)."""
import os
import random
from PIL import Image, ImageDraw

import piezas as PZ
from piezas import (CREMA, FUCSIA, LILA, TINTA, VIOLETA, AMARILLO, centrado, confeti, envolver, fuente, hexa,
                    logo, pegar_alto, tarjeta)

W, H = 1080, 1350
SALIDA = PZ.SALIDA
GRIS = "#5B5470"

PASOS = [
    ("Todo empieza con una invitación interactiva",
     "No una tarjeta cualquiera: se abre en el celular con música, la voz que le cuenta la historia al niño "
     "y la confirmación de los papás ahí mismo."),
    ("El día de la fiesta, la cabina de fotos",
     "Cada niño elige a su personaje favorito, juega, se saca su foto y se la lleva impresa al instante "
     "con su diploma."),
    ("Y después, el Álbum Recuerdo",
     "Los papás lo reciben en su celular con todas las fotos y los videos de la fiesta."),
]


def main():
    im = Image.new("RGBA", (W, H), hexa(CREMA))
    rnd = random.Random(7)
    puntos = []
    for _ in range(38):
        x, y = rnd.randint(30, W - 30), rnd.randint(30, H - 30)
        color = rnd.choice([hexa(VIOLETA)[:3], hexa(FUCSIA)[:3], hexa(AMARILLO)[:3], hexa(LILA)[:3]])
        puntos.append((x, y, rnd.randint(5, 11), color, rnd.randint(50, 110)))
    confeti(im, puntos)
    d = ImageDraw.Draw(im)

    # Logo
    pegar_alto(im, logo(), W / 2, 40, 186)
    y = 250

    # Titular (dos líneas)
    f = fuente(800, 42)
    for linea in envolver(d, "Te cuento algo que estoy armando con mucho cariño", f, 760):
        y = centrado(d, linea, W / 2, y, f, hexa(TINTA)) + 4
    y += 20

    # Tres pasos en tarjetas
    ft, fb = fuente(700, 32), fuente(500, 27)
    mx, ancho_texto = 72, W - 72 * 2 - 128
    for i, (titulo, cuerpo) in enumerate(PASOS):
        lineas = envolver(d, cuerpo, fb, ancho_texto)
        alto = 22 + 40 + 8 + len(lineas) * 34 + 22
        tarjeta(d, [mx, y, W - mx, y + alto], hexa("#FFFFFF"), hexa(LILA, 110), 32, 4)
        cx, cy = mx + 60, y + 52
        d.ellipse([cx - 28, cy - 28, cx + 28, cy + 28], fill=hexa(VIOLETA))
        centrado(d, str(i + 1), cx, cy - 23, fuente(800, 34), hexa("#FFFFFF"))
        tx = mx + 116
        d.text((tx, y + 20), titulo, font=ft, fill=hexa(TINTA))
        yy = y + 20 + 44
        for linea in lineas:
            d.text((tx, yy), linea, font=fb, fill=hexa(GRIS))
            yy += 34
        y += alto + 14

    # Cierre
    y += 8
    y = centrado(d, "Ya hicimos las primeras fiestas y quedaron increíbles.", W / 2, y, fuente(600, 30), hexa(TINTA)) + 6
    y = centrado(d, "Me ayudarías muchísimo siguiéndome en Instagram:", W / 2, y, fuente(500, 27), hexa(GRIS)) + 16

    # Pastilla de Instagram
    fp = fuente(800, 40)
    texto = "@Cumple_Click"
    an = d.textlength(texto, font=fp) + 110
    x0 = (W - an) / 2
    tarjeta(d, [x0, y, x0 + an, y + 78], hexa(FUCSIA), None, 39)
    centrado(d, texto, W / 2, y + 11, fp, hexa("#FFFFFF"))
    y += 78 + 8
    y = centrado(d, "instagram.com/cumple_click", W / 2, y, fuente(500, 24), hexa(GRIS)) + 14

    # Pie
    y = centrado(d, "Si conoces a alguien con un cumple en camino, ¡pásale el dato!", W / 2, y, fuente(500, 24), hexa(GRIS)) + 4
    y = centrado(d, "Gracias de corazón  ·  cumpleclick.com", W / 2, y, fuente(600, 24), hexa(TINTA))
    assert y <= H - 36, f"se sale por abajo: {y}"

    os.makedirs(SALIDA, exist_ok=True)
    ruta = os.path.join(SALIDA, "whatsapp-sigueme-1080x1350.png")
    im.convert("RGB").save(ruta, "PNG", optimize=True)
    print(ruta, "| ultimo y:", y)


if __name__ == "__main__":
    main()
