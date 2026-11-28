# -*- coding: utf-8 -*-
"""Fiestas Patrias: la bandera de Chile dibujada por código, exacta, sobre las imágenes de la temática.

Por qué por código: la IA deforma la bandera (la estrella, el cantón, las proporciones) y una bandera mal hecha es lo
primero que ve un chileno. Las escenas se generaron SIN bandera (Higgsfield, 26-09-2026) y aquí se les agrega una
guirnalda de banderitas, como las de las fondas, más los textos del fondo de Asómate.

Especificación (Decreto 1534 de 1967, Ministerio del Interior; verificada en es.wikipedia.org/wiki/Bandera_de_Chile):
proporción 2:3; dos franjas horizontales iguales, blanca arriba y roja abajo; cantón azul cuadrado arriba a la
izquierda, del alto de la franja blanca; estrella blanca de cinco puntas, erguida, centrada en el cantón e inscrita en
una circunferencia de diámetro igual a la mitad del lado del cantón. Colores: azul #0039A6, blanco #FFFFFF, rojo
#D52B1E.

Uso:  python fiestas_patrias.py            -> reescribe las imágenes de public/themes/fiestas-patrias/
Las escenas originales (sin bandera) se leen de ORIGENES; no se pisan nunca.
"""
import math
import os
import random

from PIL import Image, ImageDraw, ImageFilter, ImageFont

AQUI = os.path.dirname(os.path.abspath(__file__))
CB = os.path.abspath(os.path.join(AQUI, "..", "..", ".."))
TEMA = os.path.join(CB, "public", "themes", "fiestas-patrias")
ORIGENES = os.environ.get("FP_ORIGENES", r"C:/Users/luis_/Documents/CumpleClick/tematicas-adultos-2026-09-26/higgsfield")
TTF = os.path.join(CB, "design", "generadores", "arte", "ttf")

AZUL, BLANCO, ROJO = (0, 57, 166), (255, 255, 255), (213, 43, 30)
SS = 4  # supermuestreo para bordes limpios


def estrella(cx, cy, radio):
    """Estrella de cinco puntas erguida (una punta hacia arriba)."""
    interior = radio * math.sin(math.radians(18)) / math.sin(math.radians(54))
    puntos = []
    for i in range(10):
        r = radio if i % 2 == 0 else interior
        ang = math.radians(-90 + i * 36)
        puntos.append((cx + r * math.cos(ang), cy + r * math.sin(ang)))
    return puntos


def bandera_chile(ancho):
    """La bandera plana y exacta, RGBA, de `ancho` píxeles (alto = 2/3 del ancho)."""
    W = ancho * SS
    H = round(W * 2 / 3)
    im = Image.new("RGBA", (W, H), ROJO + (255,))
    d = ImageDraw.Draw(im)
    mitad = H // 2
    d.rectangle((0, 0, W, mitad - 1), fill=BLANCO + (255,))
    d.rectangle((0, 0, mitad - 1, mitad - 1), fill=AZUL + (255,))
    lado = mitad
    d.polygon(estrella(lado / 2, lado / 2, lado / 4), fill=BLANCO + (255,))
    return im.resize((ancho, round(ancho * 2 / 3)), Image.LANCZOS)


def con_relieve(flag, fase=0.0):
    """Sombreado suave de papel o tela: la bandera no se deforma, solo se le da volumen."""
    w, h = flag.size
    luz = Image.new("L", (w, h))
    px = luz.load()
    for x in range(w):
        v = 1.0 + 0.07 * math.cos(2 * math.pi * (x / w) * 1.3 + fase)
        for y in range(h):
            px[x, y] = max(0, min(255, int(128 * (v - 0.05 * y / h))))
    base = flag.convert("RGB")
    osc = Image.new("RGB", (w, h), (0, 0, 0))
    cla = Image.new("RGB", (w, h), (255, 255, 255))
    oscuro = Image.composite(base, osc, luz.point(lambda p: min(255, p * 2)))
    claro = Image.composite(cla, oscuro, luz.point(lambda p: max(0, (p - 128) * 2)))
    claro.putalpha(flag.getchannel("A"))
    return claro


def guirnalda(img, y_izq, y_der, caida, ancho_bandera, separacion, semilla=7, hilo=(60, 40, 25)):
    """Un hilo en catenaria de lado a lado con banderitas colgando de su borde superior."""
    rnd = random.Random(semilla)
    W, H = img.size
    capa = Image.new("RGBA", (W * SS, H * SS), (0, 0, 0, 0))
    sombra = Image.new("RGBA", (W, H), (0, 0, 0, 0))

    def y_de(x):
        t = x / W
        return y_izq + (y_der - y_izq) * t + caida * 4 * t * (1 - t)

    d = ImageDraw.Draw(capa)
    pts = [(x * SS, y_de(x) * SS) for x in range(-20, W + 21, 4)]
    d.line(pts, fill=hilo + (235,), width=int(2.4 * SS), joint="curve")
    capa = capa.resize((W, H), Image.LANCZOS)

    paso = ancho_bandera + separacion
    x = rnd.uniform(-ancho_bandera * 0.3, separacion)
    while x < W:
        esc = rnd.uniform(0.94, 1.04)
        bw = int(ancho_bandera * esc)
        y0, y1 = y_de(x), y_de(x + bw)
        ang = math.degrees(math.atan2(y1 - y0, bw)) + rnd.uniform(-3.5, 3.5)
        flag = con_relieve(bandera_chile(bw), rnd.uniform(0, 6.28))
        rot = flag.rotate(-ang, resample=Image.BICUBIC, expand=True)
        # esquina superior izquierda de la bandera sobre el hilo
        dx = (rot.width - flag.width) / 2
        dy = (rot.height - flag.height) / 2
        pos = (int(x - dx), int(y0 - dy + 1))
        sh = Image.new("RGBA", rot.size, (0, 0, 0, 0))
        sh.putalpha(rot.getchannel("A").point(lambda a: int(a * 0.33)))
        sombra.alpha_composite(sh, (pos[0] + 4, pos[1] + 7))
        capa.alpha_composite(rot, pos)
        x += paso
    sombra = sombra.filter(ImageFilter.GaussianBlur(5))
    out = img.convert("RGBA")
    out.alpha_composite(sombra)
    out.alpha_composite(capa)
    return out.convert("RGB")


def fuente(peso, tam):
    return ImageFont.truetype(os.path.join(TTF, "baloo2-%d.ttf" % peso), tam)


def texto_festivo(img, texto, cy, tam, relleno=BLANCO, borde=AZUL, sombra=ROJO):
    """Texto grande con borde azul y sombra roja, centrado en `cy`."""
    im = img.convert("RGBA")
    f = fuente(800, tam)
    d = ImageDraw.Draw(im)
    caja = d.textbbox((0, 0), texto, font=f, stroke_width=max(4, tam // 11))
    ancho = caja[2] - caja[0]
    x = (im.width - ancho) / 2 - caja[0]
    y = cy - (caja[3] + caja[1]) / 2
    capa = Image.new("RGBA", im.size, (0, 0, 0, 0))
    dc = ImageDraw.Draw(capa)
    dc.text((x + tam * 0.05, y + tam * 0.07), texto, font=f, fill=sombra + (255,), stroke_width=max(4, tam // 11), stroke_fill=sombra + (255,))
    dc.text((x, y), texto, font=f, fill=relleno + (255,), stroke_width=max(4, tam // 11), stroke_fill=borde + (255,))
    halo = capa.getchannel("A").filter(ImageFilter.GaussianBlur(tam * 0.12)).point(lambda a: int(a * 0.45))
    oscuro = Image.new("RGBA", im.size, (10, 20, 60, 0))
    oscuro.putalpha(halo)
    im.alpha_composite(oscuro)
    im.alpha_composite(capa)
    return im.convert("RGB")


def preparar(nombre):
    """La escena original de Higgsfield (1520x2688) llevada a 1080x1920, igual que al instalarla."""
    im = Image.open(os.path.join(ORIGENES, "fiestas-patrias--%s-v1.png" % nombre)).convert("RGB")
    W, H = im.size
    nw = round(H * 9 / 16)
    x0 = (W - nw) // 2
    return im.crop((x0, 0, x0 + nw, H)).resize((1080, 1920), Image.LANCZOS)


def guardar(im, rel):
    ruta = os.path.join(TEMA, rel)
    os.makedirs(os.path.dirname(ruta), exist_ok=True)
    im.save(ruta, quality=88, optimize=True, progressive=True)
    print("escrito", rel, im.size)


if __name__ == "__main__":
    # Marco: la guirnalda cuelga sobre el marco y nunca baja al hueco de la foto (empieza en y=148).
    sala = guirnalda(preparar("fondo-sala"), 18, 22, 58, 62, 16, semilla=3)
    guardar(sala, "fondo-sala.jpg")
    # Portada del selector.
    guardar(guirnalda(preparar("fondo-banner"), 26, 40, 120, 78, 20, semilla=5), "fondo-banner.jpg")
    # Fondo completo: la guirnalda va bajo los banderines de la ramada; las cabezas empiezan cerca de y=640.
    escena = guirnalda(preparar("fondo-escena"), 150, 170, 95, 74, 18, semilla=11)
    guardar(escena, "fondo-escena.jpg")
    # Asómate: la escena con los saludos arriba. Con un personaje la figura empieza cerca de y=190 y con varios
    # cerca de y=350: los textos van en la franja de arriba y la guirnalda justo debajo.
    aso = preparar("fondo-escena")
    aso = texto_festivo(aso, "¡Viva Chile!", 96, 118)
    aso = texto_festivo(aso, "Felices Fiestas Patrias", 186, 58, relleno=(255, 236, 170))
    aso = guirnalda(aso, 238, 250, 70, 64, 16, semilla=19)
    guardar(aso, os.path.join("asomate", "fondo.jpg"))
