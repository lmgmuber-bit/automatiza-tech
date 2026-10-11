# -*- coding: utf-8 -*-
"""Fondos de la temática adulta «Coquetería Pop» (AT-CUMPLECLICK-026).

Genera, con código y sin IA, los tres fondos 1080x1920 de la temática:

  fondo-sala.jpg    cabina: «¡SE BUSCA!» + marco dorado + panel crema donde el kiosco escribe la frase
  fondo-banner.jpg  bienvenida del kiosco: rayos, «¡SE BUSCA!» arriba y el centro libre para los botones
  fondo-grupal.jpg  foto de todos: marco dorado apaisado
  fondo-revista.jpg portada de revista para tomarse en pareja (foto 4:5 y panel de titulares)

Es GENÉRICA a propósito: ni nombre, ni edad, ni fotos de nadie. El nombre y la edad de la
celebrada los escribe el kiosco encima, en tiempo de ejecución (src/coqueteria.js).
Estilo tomado de la invitación aprobada de un evento piloto: fucsia con rayos, semitono, cinta
dorada, paneles crema con contorno negro grueso.

Tipografía: Anton (OFL-1.1, paquete @fontsource/anton del propio proyecto). FreeType no lee
WOFF, así que el .woff se reconstruye a sfnt en memoria (WOFF 1.0 = sfnt con tablas en zlib).

Uso:  python design/generadores/tematicas/coqueteria_pop.py [carpeta_salida]
      (por defecto public/themes/adulto-coqueteria-pop/). Imprime el frameBox de cada fondo.
"""
import io
import math
import struct
import sys
import zlib
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

CB = Path(__file__).resolve().parents[3]
SALIDA = Path(sys.argv[1]) if len(sys.argv) > 1 else CB / "public/themes/adulto-coqueteria-pop"
W, H = 1080, 1920

FUCSIA = (249, 11, 120)
ROSA = (255, 85, 154)
FUCSIA_OSCURO = (214, 0, 98)
CREMA = (255, 240, 212)
NEGRO = (25, 18, 24)
DORADO = (216, 170, 67)
DORADO_CLARO = (255, 233, 166)
DORADO_OSCURO = (150, 108, 28)

# El kiosco inscribe un cuadrado en el frameBox y lo mete un 8,5 % por lado
# (FRAME_PHOTO_INSET_RATIO en src/frameGeometry.js). El borde dorado se pinta en ese 8,5 %.
INSET = 0.085


def woff_a_sfnt(ruta):
    datos = Path(ruta).read_bytes()
    firma, flavor, _largo, ntablas = struct.unpack(">IIIH", datos[:14])
    assert firma == 0x774F4646, "no es WOFF 1.0"
    entradas = []
    for i in range(ntablas):
        tag, off, comp, orig, chk = struct.unpack(">4sIIII", datos[44 + i * 20:64 + i * 20])
        bloque = datos[off:off + comp]
        entradas.append((tag, chk, zlib.decompress(bloque) if comp != orig else bloque))
    potencia = 2 ** int(math.log2(ntablas))
    cabecera = struct.pack(">IHHHH", flavor, ntablas, potencia * 16, int(math.log2(potencia)), ntablas * 16 - potencia * 16)
    pos = 12 + 16 * ntablas
    directorio, cuerpo = b"", b""
    for tag, chk, tabla in entradas:
        directorio += struct.pack(">4sIII", tag, chk, pos + len(cuerpo), len(tabla))
        cuerpo += tabla + b"\0" * ((4 - len(tabla) % 4) % 4)
    return cabecera + directorio + cuerpo


ANTON = woff_a_sfnt(CB / "node_modules/@fontsource/anton/files/anton-latin-400-normal.woff")


def fuente(px):
    return ImageFont.truetype(io.BytesIO(ANTON), px)


def rayos(im, centro, n=36):
    d = ImageDraw.Draw(im)
    d.rectangle([0, 0, W, H], fill=FUCSIA)
    r = 3000
    for i in range(n):
        if i % 2:
            continue
        a0 = 2 * math.pi * i / n
        a1 = 2 * math.pi * (i + 1) / n
        d.polygon([centro, (centro[0] + r * math.cos(a0), centro[1] + r * math.sin(a0)),
                   (centro[0] + r * math.cos(a1), centro[1] + r * math.sin(a1))], fill=ROSA)


def semitono(im, esquina, alcance=430, paso=30, color=FUCSIA_OSCURO):
    """Puntos que crecen hacia una esquina, como trama de cómic."""
    d = ImageDraw.Draw(im)
    ex, ey = esquina
    for y in range(0, H + paso, paso):
        for x in range((y // paso % 2) * paso // 2, W + paso, paso):
            dist = math.hypot(x - ex, y - ey)
            if dist < alcance:
                rr = 9 * (1 - dist / alcance)
                if rr > 1.2:
                    d.ellipse([x - rr, y - rr, x + rr, y + rr], fill=color)


def confeti_dorado(im, esquina, cuantos=14, semilla=1):
    d = ImageDraw.Draw(im)
    ex, ey = esquina
    s = semilla
    for i in range(cuantos):
        s = (s * 1103515245 + 12345) & 0x7FFFFFFF
        ang = (s % 900) / 900 * math.pi / 2
        s = (s * 1103515245 + 12345) & 0x7FFFFFFF
        dist = 30 + (s % 230)
        x = ex + (dist * math.cos(ang) if ex == 0 else -dist * math.cos(ang))
        y = ey + (dist * math.sin(ang) if ey == 0 else -dist * math.sin(ang))
        r = 9 + (s % 9)
        d.ellipse([x - r, y - r, x + r, y + r], fill=DORADO, outline=NEGRO, width=3)
        d.ellipse([x - r * 0.45, y - r * 0.45, x + r * 0.2, y + r * 0.2], fill=DORADO_CLARO)


def corazon(im, cx, cy, t, color=DORADO, borde=NEGRO):
    d = ImageDraw.Draw(im)
    pts = []
    for k in range(60):
        a = 2 * math.pi * k / 60
        x = 16 * math.sin(a) ** 3
        y = 13 * math.cos(a) - 5 * math.cos(2 * a) - 2 * math.cos(3 * a) - math.cos(4 * a)
        pts.append((cx + x * t / 16, cy - y * t / 16))
    d.polygon(pts, fill=color, outline=borde, width=max(2, int(t / 10)))


def estrella(im, cx, cy, r, color=CREMA):
    d = ImageDraw.Draw(im)
    pts = []
    for k in range(10):
        rr = r if k % 2 == 0 else r * 0.42
        a = -math.pi / 2 + k * math.pi / 5
        pts.append((cx + rr * math.cos(a), cy + rr * math.sin(a)))
    d.polygon(pts, fill=color, outline=NEGRO, width=4)


def texto_cartel(im, texto, cy, px, relleno=(255, 255, 255), contorno=NEGRO, grosor=10, sombra=9, ancho_max=W - 90):
    f = fuente(px)
    d = ImageDraw.Draw(im)
    while d.textlength(texto, font=f) > ancho_max and px > 20:
        px -= 4
        f = fuente(px)
    d.text((W / 2 + sombra, cy + sombra), texto, font=f, fill=NEGRO, anchor="mm", stroke_width=grosor, stroke_fill=NEGRO)
    d.text((W / 2, cy), texto, font=f, fill=relleno, anchor="mm", stroke_width=grosor, stroke_fill=contorno)


def degradado_dorado(ancho, alto, vertical=True):
    franja = Image.new("RGB", (ancho, alto))
    px = franja.load()
    largo = alto if vertical else ancho
    for i in range(largo):
        t = i / max(1, largo - 1)
        # oscuro → claro → base → oscuro, como una cinta metálica
        tramos = [(0, DORADO_OSCURO), (0.28, DORADO_CLARO), (0.55, DORADO), (1, DORADO_OSCURO)]
        for (t0, c0), (t1, c1) in zip(tramos, tramos[1:]):
            if t0 <= t <= t1:
                u = (t - t0) / (t1 - t0)
                c = tuple(int(c0[j] + (c1[j] - c0[j]) * u) for j in range(3))
                break
        if vertical:
            for x in range(ancho):
                px[x, i] = c
        else:
            for y in range(alto):
                px[i, y] = c
    return franja


def marco_dorado(im, x0, y0, x1, y1, grosor):
    """Moldura dorada con contorno negro y sombra dura; el interior queda crema."""
    d = ImageDraw.Draw(im)
    d.rectangle([x0 + 16, y0 + 16, x1 + 16, y1 + 16], fill=NEGRO)
    im.paste(degradado_dorado(x1 - x0, y1 - y0), (x0, y0))
    d.rectangle([x0, y0, x1, y1], outline=NEGRO, width=8)
    d.rectangle([x0 + grosor, y0 + grosor, x1 - grosor, y1 - grosor], fill=CREMA, outline=NEGRO, width=6)
    # filo claro interior de la moldura
    d.rectangle([x0 + 14, y0 + 14, x1 - 14, y1 - 14], outline=DORADO_CLARO, width=3)


def leopardo(im, x0, y0, x1, y1, semilla=7):
    """Cinta con estampado leopardo, para las esquinas del marco (detalle de la invitación)."""
    d = ImageDraw.Draw(im)
    d.rectangle([x0, y0, x1, y1], fill=(236, 190, 116), outline=NEGRO, width=4)
    s = semilla
    paso = 30
    for fila, cy in enumerate(range(y0 + 12, y1, paso - 6)):
        for cx in range(x0 + 10 + (fila % 2) * paso // 2, x1, paso):
            s = (s * 1103515245 + 12345) & 0x7FFFFFFF
            r = 7 + s % 4
            d.ellipse([cx - r * 0.55, cy - r * 0.45, cx + r * 0.55, cy + r * 0.45], fill=(176, 108, 44))
            for k in range(4):  # roseta: manchas negras alrededor del centro café
                a = k * math.pi / 2 + (s % 7) / 7
                bx, by = cx + r * 0.95 * math.cos(a), cy + r * 0.8 * math.sin(a)
                d.ellipse([bx - 2.6, by - 2.2, bx + 2.6, by + 2.2], fill=NEGRO)


def cinta_esquina(im, cx, cy, angulo):
    tira = Image.new("RGBA", (150, 48), (0, 0, 0, 0))
    leopardo(tira, 0, 0, 149, 47)
    tira = tira.rotate(angulo, expand=True, resample=Image.BICUBIC)
    im.paste(tira, (int(cx - tira.width / 2), int(cy - tira.height / 2)), tira)


def panel_crema(im, x0, y0, x1, y1):
    d = ImageDraw.Draw(im)
    d.rectangle([x0 + 14, y0 + 14, x1 + 14, y1 + 14], fill=NEGRO)
    d.rectangle([x0, y0, x1, y1], fill=CREMA, outline=NEGRO, width=8)


def frame_box(x0, y0, x1, y1):
    return {k: round(v, 4) for k, v in {"x": x0 / W, "y": y0 / H, "w": (x1 - x0) / W, "h": (y1 - y0) / H}.items()}


def fondo_sala():
    im = Image.new("RGB", (W, H))
    rayos(im, (W // 2, 760))
    semitono(im, (0, 0))
    semitono(im, (W, H))
    confeti_dorado(im, (0, 0), semilla=3)
    confeti_dorado(im, (W, H), semilla=11)
    texto_cartel(im, "¡SE BUSCA!", 112, 164)
    texto_cartel(im, "POR EXCESO DE COQUETERÍA", 238, 60, relleno=CREMA, grosor=7, sombra=6)
    # Marco: el kiosco pone la foto en el cuadrado interior (lado * (1 - 2*INSET)).
    lado = 820
    x0, y0 = (W - lado) // 2, 330
    x1, y1 = x0 + lado, y0 + lado
    marco_dorado(im, x0, y0, x1, y1, round(lado * INSET))
    cinta_esquina(im, x0 + 18, y0 + 18, 45)
    cinta_esquina(im, x1 - 18, y0 + 18, -45)
    corazon(im, 120, 1240, 34)
    corazon(im, 965, 300, 28)
    corazon(im, 62, 1150, 26)
    estrella(im, 92, 360, 30, color=DORADO_CLARO)
    # Cinta dorada vacía: el kiosco escribe el nombre y la edad encima.
    cinta = (90, 1196, W - 90, 1296)
    ImageDraw.Draw(im).rectangle([cinta[0] + 12, cinta[1] + 12, cinta[2] + 12, cinta[3] + 12], fill=NEGRO)
    im.paste(degradado_dorado(cinta[2] - cinta[0], cinta[3] - cinta[1]), (cinta[0], cinta[1]))
    ImageDraw.Draw(im).rectangle(cinta, outline=NEGRO, width=7)
    # Panel crema vacío: la frase elegida por el invitado.
    panel = (80, 1340, W - 80, 1700)
    panel_crema(im, *panel)
    return im, frame_box(x0, y0, x1, y1), {"cinta": frame_box(*cinta), "panel": frame_box(*panel)}


def fondo_banner():
    im = Image.new("RGB", (W, H))
    rayos(im, (W // 2, 900))
    semitono(im, (0, 0))
    semitono(im, (W, H), alcance=520)
    confeti_dorado(im, (0, 0), semilla=5)
    confeti_dorado(im, (W, H), cuantos=18, semilla=13)
    texto_cartel(im, "¡SE BUSCA!", 300, 200)
    texto_cartel(im, "POR EXCESO DE COQUETERÍA", 455, 70, relleno=CREMA, grosor=7, sombra=6)
    corazon(im, 150, 640, 40)
    corazon(im, 930, 600, 32)
    estrella(im, 960, 160, 46)
    estrella(im, 110, 180, 34, color=DORADO_CLARO)
    corazon(im, 940, 1560, 30)
    estrella(im, 130, 1620, 40, color=DORADO_CLARO)
    return im


def fondo_grupal():
    im = Image.new("RGB", (W, H))
    rayos(im, (W // 2, 820))
    semitono(im, (0, 0))
    semitono(im, (W, H))
    confeti_dorado(im, (0, 0), semilla=9)
    confeti_dorado(im, (W, H), semilla=17)
    texto_cartel(im, "¡SE BUSCAN!", 170, 170)
    texto_cartel(im, "CÓMPLICES DE LA FIESTA", 310, 68, relleno=CREMA, grosor=7, sombra=6)
    # Hueco apaisado 4:3: la foto grupal se toma con la tablet acostada.
    x0, x1 = 90, W - 90
    alto = round((x1 - x0) * 3 / 4)
    y0 = 470
    y1 = y0 + alto
    # Moldura delgada: el kiosco escribe el título del grupo 26 px bajo el hueco (componerGrupal).
    borde = 22
    marco_dorado(im, x0 - borde, y0 - borde, x1 + borde, y1 + borde, borde)
    cinta_esquina(im, x0 - 4, y0 - 4, 40)
    cinta_esquina(im, x1 + 4, y0 - 4, -40)
    corazon(im, 140, 1330, 34)
    estrella(im, 950, 1340, 42)
    return im, frame_box(x0, y0, x1, y1)


def fondo_revista():
    """Portada de revista para la pareja: el kiosco escribe el nombre de la revista, la edición,
    el titular y el sello con la edad; aquí solo van la trama, el hueco de la foto y el panel."""
    im = Image.new("RGB", (W, H))
    rayos(im, (W // 2, 300), n=44)
    semitono(im, (W, 0), alcance=380)
    semitono(im, (0, H), alcance=460)
    confeti_dorado(im, (W, H), cuantos=16, semilla=21)
    d = ImageDraw.Draw(im)
    # Hueco 4:5 de la foto, con filo crema y contorno negro como una portada impresa.
    x0, x1 = 90, W - 90
    y0 = 300
    y1 = y0 + round((x1 - x0) * 5 / 4)
    d.rectangle([x0 + 14, y0 + 14, x1 + 14, y1 + 14], fill=NEGRO)
    d.rectangle([x0 - 10, y0 - 10, x1 + 10, y1 + 10], fill=CREMA, outline=NEGRO, width=6)
    d.rectangle([x0, y0, x1, y1], fill=(60, 40, 60))
    panel = (70, y1 + 40, W - 70, H - 125)  # la marca de agua del kiosco va debajo
    panel_crema(im, *panel)
    corazon(im, 1010, 1505, 30)
    estrella(im, 62, 250, 32, color=DORADO_CLARO)
    return im, frame_box(x0, y0, x1, y1), frame_box(*panel)


def main():
    SALIDA.mkdir(parents=True, exist_ok=True)
    sala, caja_sala, extra = fondo_sala()
    sala.save(SALIDA / "fondo-sala.jpg", quality=90, optimize=True)
    fondo_banner().save(SALIDA / "fondo-banner.jpg", quality=90, optimize=True)
    grupal, caja_grupal = fondo_grupal()
    grupal.save(SALIDA / "fondo-grupal.jpg", quality=90, optimize=True)
    print("frameBox sala  ", caja_sala)
    print("cinta / panel  ", extra)
    print("frameBox grupal", caja_grupal)
    revista, caja_revista, panel_revista = fondo_revista()
    revista.save(SALIDA / "fondo-revista.jpg", quality=90, optimize=True)
    print("revista foto / panel", caja_revista, panel_revista)


if __name__ == "__main__":
    main()
