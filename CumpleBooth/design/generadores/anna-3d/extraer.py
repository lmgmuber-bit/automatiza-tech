# -*- coding: utf-8 -*-
"""Primer paso de Anna de gala: saca la textura de `hermana.glb` a `salida/hermana-textura.jpg`.

`mapa.py` y `vestido.py` leen esa imagen. Se copia tal cual viene dentro del GLB, sin
recomprimir, para que el repintado parta del original.
"""
import json
import os
import struct

AQUI = os.path.dirname(os.path.abspath(__file__))
SALIDA = os.path.join(AQUI, "salida")
ORIGEN = os.environ.get("HERMANA_GLB", r"C:/wamp64/www/tucumple-repo/app/public/models/hermana.glb")


def leer_glb(ruta):
    with open(ruta, "rb") as f:
        _, _, largo = struct.unpack("<III", f.read(12))
        trozos = []
        while f.tell() < largo:
            clen, ctipo = struct.unpack("<II", f.read(8))
            trozos.append((ctipo, f.read(clen)))
    j = next(d for t, d in trozos if t == 0x4E4F534A)
    b = next(d for t, d in trozos if t == 0x004E4942)
    return json.loads(j.decode("utf-8")), b


if __name__ == "__main__":
    g, b = leer_glb(ORIGEN)
    bv = g["bufferViews"][g["images"][0]["bufferView"]]
    inicio = bv.get("byteOffset", 0)
    os.makedirs(SALIDA, exist_ok=True)
    destino = os.path.join(SALIDA, "hermana-textura.jpg")
    with open(destino, "wb") as f:
        f.write(b[inicio:inicio + bv["byteLength"]])
    print(destino, bv["byteLength"], "bytes")
