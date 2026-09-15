# -*- coding: utf-8 -*-
"""WOFF 1.0 -> TTF, para poder dibujar con Baloo 2 desde Pillow.

Pillow no lee WOFF y @fontsource no distribuye TTF. Un WOFF 1.0 es el MISMO sfnt de un TTF
con cada tabla comprimida en zlib, asi que rehacerlo es rearmar el directorio y descomprimir
tabla por tabla. El repositorio ya hace esto mismo en PHP (scripts/lib-woff.php) para el
compositor del kiosco; esta es la version minima en Python.

No se versiona el TTF: se deriva del mismo .woff que usa el producto, asi no puede quedar
desincronizado de la tipografia real.
"""
import os
import struct
import zlib


def woff_a_ttf(origen, destino):
    datos = open(origen, "rb").read()
    if datos[:4] != b"wOFF":
        raise ValueError("no es un WOFF 1.0: " + origen)
    flavor, _largo, num_tablas = struct.unpack(">4sIH", datos[4:14])

    entradas = []
    for i in range(num_tablas):
        base = 44 + i * 20
        tag, off, comp, orig, _suma = struct.unpack(">4sIIII", datos[base:base + 20])
        crudo = datos[off:off + comp]
        cuerpo = zlib.decompress(crudo) if comp != orig else crudo
        if len(cuerpo) != orig:
            raise ValueError("tabla %s con largo inesperado" % tag)
        entradas.append([tag, cuerpo, _suma])
    entradas.sort(key=lambda e: e[0])

    # Cabecera sfnt: los tres numeros de busqueda se calculan, no se copian.
    potencia = 1
    while potencia * 2 <= num_tablas:
        potencia *= 2
    rango = potencia * 16
    selector = potencia.bit_length() - 1
    resto = num_tablas * 16 - rango

    salida = bytearray(struct.pack(">4sHHHH", flavor, num_tablas, rango, selector, resto))
    desplazamiento = 12 + num_tablas * 16
    directorio = bytearray()
    cuerpos = bytearray()
    for tag, cuerpo, suma in entradas:
        directorio += struct.pack(">4sIII", tag, suma, desplazamiento, len(cuerpo))
        cuerpos += cuerpo
        relleno = (-len(cuerpo)) % 4
        cuerpos += b"\0" * relleno
        desplazamiento += len(cuerpo) + relleno
    salida += directorio + cuerpos
    open(destino, "wb").write(bytes(salida))
    return destino


if __name__ == "__main__":
    AQUI = os.path.dirname(os.path.abspath(__file__))
    # La tipografia sale del mismo paquete que usa el producto. npm la ha dejado en dos lugares
    # distintos en este proyecto, asi que se buscan los dos.
    CB = os.path.abspath(os.path.join(AQUI, "..", "..", ".."))
    candidatas = [os.path.join(CB, "node_modules", "@fontsource", "baloo-2", "files"),
                  os.path.join(CB, "node_modules", ".ignored", "@fontsource", "baloo-2", "files")]
    REPO = next((c for c in candidatas if os.path.isdir(c)), candidatas[0]) + os.sep
    os.makedirs(os.path.join(AQUI, "ttf"), exist_ok=True)
    for peso in ("400", "500", "600", "700", "800"):
        origen = REPO + "baloo-2-latin-%s-normal.woff" % peso
        if not os.path.isfile(origen):
            print("falta", origen)
            continue
        destino = os.path.join(AQUI, "ttf", "baloo2-%s.ttf" % peso)
        woff_a_ttf(origen, destino)
        print("%s -> %d bytes" % (os.path.basename(destino), os.path.getsize(destino)))
