# -*- coding: utf-8 -*-
"""Instala Graduación (graduacion) y Cumpleaños de gala (adulto-cumpleanos) en public/themes/. 05-10-2026.

Las dos escenas son de Higgsfield por API REST (marketing-studio/image/flare, 9:16, USD 0,0162 de lista c/u; prompts y
request_id en registro-generacion.json de la carpeta de origen), sin texto ni números: el año, la edad y los saludos los
dibuja el kiosco (src/feria/celebraciones.js). Se recortan a 9:16 y se llevan a 1080x1920; fondo-sala.jpg es copia de la
escena y fondo-evento.jpg sale de derivar.py. La tarjeta del selector la dibuja design/generadores/revista/render-banner.mjs.
Uso: python instalar.py [--forzar]"""
import pathlib
import shutil
import sys

AQUI = pathlib.Path(__file__).resolve().parent
sys.path.insert(0, str(AQUI.parent / "anio-nuevo-empresa"))
sys.path.insert(0, str(AQUI.parent / "fondo-evento"))
from instalar import a_916, TEMAS  # noqa: E402
from derivar import derivar  # noqa: E402

ORIGEN = pathlib.Path(r"C:/Users/luis_/Documents/CumpleClick/tematicas-adultos-2026-10/grad-cumple")
ESCENAS = {"graduacion": "graduacion-v1.png", "adulto-cumpleanos": "cumpleanos-v1.png"}

if __name__ == "__main__":
    forzar = "--forzar" in sys.argv
    for tema, archivo in ESCENAS.items():
        carpeta = TEMAS / tema
        carpeta.mkdir(parents=True, exist_ok=True)
        escena = carpeta / "fondo-escena.jpg"
        if forzar or not escena.is_file():
            a_916(ORIGEN / archivo, escena)
        sala = carpeta / "fondo-sala.jpg"
        if forzar or not sala.is_file():
            shutil.copyfile(escena, sala)
        print(tema, "escena y sala listas; fondo-evento", "hecho" if derivar(carpeta, forzar) else "ya estaba")
