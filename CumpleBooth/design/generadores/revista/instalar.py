# -*- coding: utf-8 -*-
"""Instala los fondos de la Portada de Revista CLICK (adulto-revista) en public/themes/. 04-10-2026.

Las tres escenas se generaron con Higgsfield por API REST (marketing-studio/image/flare, 9:16, 1520x2688, USD 0,0162 de
lista c/u; prompts y request_id en registro-generacion.json de la carpeta de origen) y van SIN texto: el título CLICK y los
titulares los dibuja el kiosco (src/feria/revista.js). Este script las recorta al centro a 9:16 exacto, las baja a
1080x1920 como las demás temáticas de adultos y deja:
  revista-alfombra.jpg, revista-estudio.jpg, revista-bn.jpg  las tres escenas que el invitado elige en el menú
  fondo-sala.jpg                                              copia de la alfombra (el marco de respaldo y el selector)
  fondo-evento.jpg                                            con design/generadores/fondo-evento/derivar.py
fondo-banner.jpg (la tarjeta del selector) es una portada de muestra sin persona y la dibuja el código real del kiosco:
ver render-banner.mjs en esta carpeta.

Uso: python instalar.py [carpeta_origen]   Nunca pisa un archivo existente, salvo con --forzar."""
import pathlib
import shutil
import sys

from PIL import Image

AQUI = pathlib.Path(__file__).resolve().parent
TEMA = AQUI.parents[2] / "public" / "themes" / "adulto-revista"
ORIGEN = pathlib.Path(r"C:/Users/luis_/Documents/CumpleClick/tematicas-adultos-2026-10/revista")
W, H = 1080, 1920
ESCENAS = {"alfombra": "revista-alfombra-v1.png", "estudio": "revista-estudio-v1.png", "bn": "revista-bn-v1.png"}


def a_916(origen, destino, forzar):
    if destino.is_file() and not forzar:
        return "ya estaba"
    im = Image.open(origen).convert("RGB")
    ancho = min(im.width, round(im.height * W / H))
    alto = min(im.height, round(ancho * H / W))
    x, y = (im.width - ancho) // 2, (im.height - alto) // 2
    im = im.crop((x, y, x + ancho, y + alto)).resize((W, H), Image.LANCZOS)
    im.save(destino, quality=86, optimize=True, progressive=True)
    return "hecho"


if __name__ == "__main__":
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    forzar = "--forzar" in sys.argv
    origen = pathlib.Path(args[0]) if args else ORIGEN
    TEMA.mkdir(parents=True, exist_ok=True)
    for clave, archivo in ESCENAS.items():
        print("revista-%s.jpg" % clave, a_916(origen / archivo, TEMA / ("revista-%s.jpg" % clave), forzar))
    sala = TEMA / "fondo-sala.jpg"
    if forzar or not sala.is_file():
        shutil.copyfile(TEMA / "revista-alfombra.jpg", sala)
        print("fondo-sala.jpg hecho")
    sys.path.insert(0, str(AQUI.parent / "fondo-evento"))
    from derivar import derivar  # noqa: E402
    print("fondo-evento.jpg", "hecho" if derivar(TEMA, forzar) else "ya estaba")
