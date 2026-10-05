# -*- coding: utf-8 -*-
"""Instala las temáticas de adultos Año Nuevo y Muro de prensa (Empresa) en public/themes/. 04-10-2026.

Año Nuevo (adulto-anio-nuevo): la escena elegida es la candidata A de Higgsfield por API REST (marketing-studio/image/flare,
9:16, USD 0,0162 de lista; las dos candidatas costaron USD 0,0324; prompts y request_id en registro-generacion.json de la
carpeta de origen). Va SIN números: el año y el saludo los dibuja el kiosco (src/feria/anioNuevo.js). Se recorta al centro a
9:16 exacto y se lleva a 1080x1920 como las demás temáticas de adultos.

Muro de prensa (adulto-empresa): el muro lo dibuja este script, sin IA: blanco cálido con un degradé suave y el grano de una tela
impresa. Los logos del cliente los repite el kiosco encima (src/feria/muroLogos.js).

En las dos, fondo-sala.jpg es copia de la escena y fondo-evento.jpg sale de design/generadores/fondo-evento/derivar.py. La
tarjeta del selector (fondo-banner.jpg) la dibuja design/generadores/revista/render-banner.mjs con el código del kiosco.
Uso: python instalar.py [--forzar]   Nunca pisa un archivo existente, salvo con --forzar."""
import pathlib
import shutil
import sys

from PIL import Image, ImageChops

AQUI = pathlib.Path(__file__).resolve().parent
TEMAS = AQUI.parents[2] / "public" / "themes"
ORIGEN_ANIO = pathlib.Path(r"C:/Users/luis_/Documents/CumpleClick/tematicas-adultos-2026-10/anio-nuevo/anio-nuevo-bahia-a-v1.png")
W, H = 1080, 1920


def a_916(origen, destino):
    im = Image.open(origen).convert("RGB")
    ancho = min(im.width, round(im.height * W / H))
    alto = min(im.height, round(ancho * H / W))
    x, y = (im.width - ancho) // 2, (im.height - alto) // 2
    im.crop((x, y, x + ancho, y + alto)).resize((W, H), Image.LANCZOS).save(destino, quality=86, optimize=True, progressive=True)


def muro(destino):
    # Blanco cálido arriba y un poco más gris abajo, como un lienzo iluminado desde arriba, con grano fino de tela impresa.
    degrade = Image.linear_gradient("L").resize((W, H))
    arriba = Image.new("RGB", (W, H), (250, 249, 247))
    abajo = Image.new("RGB", (W, H), (237, 236, 232))
    im = Image.composite(abajo, arriba, degrade)
    grano = Image.effect_noise((W, H), 3).convert("RGB")
    ImageChops.add(im, grano, scale=1.0, offset=-128).save(destino, quality=90, optimize=True, progressive=True)


def instalar(tema, hacer_escena, forzar):
    carpeta = TEMAS / tema
    carpeta.mkdir(parents=True, exist_ok=True)
    escena = carpeta / "fondo-escena.jpg"
    if forzar or not escena.is_file():
        hacer_escena(escena)
        print(tema, "fondo-escena.jpg hecho")
    sala = carpeta / "fondo-sala.jpg"
    if forzar or not sala.is_file():
        shutil.copyfile(escena, sala)
        print(tema, "fondo-sala.jpg hecho")
    sys.path.insert(0, str(AQUI.parent / "fondo-evento"))
    from derivar import derivar  # noqa: E402
    print(tema, "fondo-evento.jpg", "hecho" if derivar(carpeta, forzar) else "ya estaba")


if __name__ == "__main__":
    forzar = "--forzar" in sys.argv
    instalar("adulto-anio-nuevo", lambda destino: a_916(ORIGEN_ANIO, destino), forzar)
    instalar("adulto-empresa", muro, forzar)
