# -*- coding: utf-8 -*-
"""Fondo de pantalla de evento (fondo-evento.jpg) para las temáticas que no tienen uno ilustrado. 29-09-2026.

El selector y el kiosco de un evento pintan detrás de los paneles el fondo de la temática (src/feria/fondo.js). Las
temáticas nuevas (brujitas, navidad, fiestas-patrias) traen un collage de papel hecho a propósito. Para el resto, este
script deriva uno SIN créditos desde su fondo-sala.jpg: desenfoque fuerte y mezcla con crema, para que quede el color y
la luz de la temática sin figuras reconocibles y el texto se lea encima.

Uso: python derivar.py [slug ...]   (sin argumentos: todas las que tengan fondo-sala.jpg y no tengan fondo-evento.jpg)
Nunca pisa un fondo-evento.jpg existente, salvo con --forzar."""
import pathlib
import sys

from PIL import Image, ImageFilter

TEMAS = pathlib.Path(__file__).resolve().parents[3] / "public" / "themes"
W, H = 1080, 1920
CREMA = (255, 250, 240)
MEZCLA = 0.30  # cuánto crema se mezcla encima
DESENFOQUE = 26


def derivar(carpeta, forzar=False):
    origen = carpeta / "fondo-sala.jpg"
    destino = carpeta / "fondo-evento.jpg"
    if not origen.is_file() or (destino.is_file() and not forzar):
        return None
    im = Image.open(origen).convert("RGB")
    esc = max(W / im.width, H / im.height)
    im = im.resize((round(im.width * esc), round(im.height * esc)), Image.LANCZOS)
    x, y = (im.width - W) // 2, (im.height - H) // 2
    im = im.crop((x, y, x + W, y + H)).filter(ImageFilter.GaussianBlur(DESENFOQUE))
    im = Image.blend(im, Image.new("RGB", (W, H), CREMA), MEZCLA)
    im.save(destino, quality=84, optimize=True)
    return destino


if __name__ == "__main__":
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    forzar = "--forzar" in sys.argv
    carpetas = [TEMAS / a for a in args] if args else sorted(p for p in TEMAS.iterdir() if p.is_dir())
    for c in carpetas:
        hecho = derivar(c, forzar)
        print(c.name, "->", "hecho" if hecho else "sin cambios")
