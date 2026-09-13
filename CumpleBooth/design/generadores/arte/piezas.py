# -*- coding: utf-8 -*-
"""Piezas graficas de CumpleClick: Instagram, fondo de tablet y cartel impreso.

Se componen con el LOGO REAL del repositorio y con Baloo 2, la tipografia de la marca. No se
genera ningun logo: un modelo generativo dibujaria otro, y el manual de marca lo prohibe.
Colores y proporciones salen de design/MANUAL-DE-MARCA.md (70% crema, 20% violeta-fucsia,
8% amarillo, 2% oro: el oro es condimento, no plato).

Todo el texto es literal del sitio publico. Los precios son los del sitio; la fuente viva es
Admin -> Planes (public/data/planes.json).
"""
import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

AQUI = os.path.dirname(os.path.abspath(__file__))
SALIDA = os.path.join(AQUI, "salida")
TTF = os.path.join(AQUI, "ttf")
# CumpleBooth/: tres carpetas arriba de este archivo (design/generadores/arte/). Hasta el
# 2026-09-12 era una ruta fija a una worktree vieja de otra rama y el generador solo corria en
# la PC donde se escribio.
REPO = os.path.abspath(os.path.join(AQUI, "..", "..", "..")).replace("\\", "/") + "/"
LOGOS = REPO + "design/logo/"

# ── Paleta oficial "El globo dulce" (manual, seccion 3)
VIOLETA = "#8B5CF6"
TINTA = "#4C2882"
FUCSIA = "#D6307F"
AMARILLO = "#FBBF24"
ORO = "#E8A317"
CREMA = "#FFF8EC"
LILA = "#A78BFA"
NUDO = "#C2186B"

MARCA = "CumpleClick"
LEMA = "Fotos y recuerdos para tu fiesta"
WEB = "cumpleclick.com"
IG = "@Cumple_Click"
WSP = "+56 9 7494 0070"


def fuente(peso, tam):
    return ImageFont.truetype(os.path.join(TTF, "baloo2-%d.ttf" % peso), tam)


def logo(transparente=True):
    """El logo real. Si el archivo trae fondo blanco opaco, se le recorta el blanco."""
    ruta = LOGOS + ("logo-transparent.png" if transparente else "logo-icon-wordmark.png")
    im = Image.open(ruta).convert("RGBA")
    if im.getextrema()[3][0] == 255:  # totalmente opaco: hay que quitar el fondo
        im = quitar_blanco(im)
    return recortar(im)


def quitar_blanco(im, umbral=246):
    px = im.load()
    an, al = im.size
    for y in range(al):
        for x in range(an):
            r, g, b, a = px[x, y]
            if r >= umbral and g >= umbral and b >= umbral:
                px[x, y] = (r, g, b, 0)
    return im


def recortar(im):
    caja = im.getchannel("A").getbbox()
    return im.crop(caja) if caja else im


def pegar_alto(lienzo, im, cx, y, alto):
    k = alto / im.height
    esc = im.resize((max(1, round(im.width * k)), alto), Image.LANCZOS)
    lienzo.alpha_composite(esc, (round(cx - esc.width / 2), round(y)))
    return esc.width


def centrado(d, texto, cx, y, f, color):
    caja = d.textbbox((0, 0), texto, font=f)
    d.text((cx - (caja[2] - caja[0]) / 2 - caja[0], y), texto, font=f, fill=color)
    return y + (caja[3] - caja[1]) + caja[1]


def ajustar(d, texto, ancho, peso, tam):
    """Baja el cuerpo hasta que el texto entre en `ancho`."""
    f = fuente(peso, tam)
    while tam > 10 and d.textlength(texto, font=f) > ancho:
        tam -= 2
        f = fuente(peso, tam)
    return f


def envolver(d, texto, f, ancho):
    lineas, actual = [], ""
    for palabra in texto.split():
        prueba = (actual + " " + palabra).strip()
        if d.textlength(prueba, font=f) <= ancho:
            actual = prueba
        else:
            if actual:
                lineas.append(actual)
            actual = palabra
    if actual:
        lineas.append(actual)
    return lineas


def confeti(lienzo, puntos):
    """Confeti disperso. Va en una capa aparte para que quede suave sobre la crema."""
    capa = Image.new("RGBA", lienzo.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(capa)
    for x, y, r, color, alfa in puntos:
        d.ellipse([x - r, y - r, x + r, y + r], fill=color + (alfa,))
    lienzo.alpha_composite(capa)


def hexa(c, a=255):
    c = c.lstrip("#")
    return (int(c[0:2], 16), int(c[2:4], 16), int(c[4:6], 16), a)


def degrade_suave(tam, arriba, abajo):
    """Degrade vertical, hecho chico y ampliado: mas barato y sin bandas visibles."""
    chico = Image.new("RGB", (1, 256))
    d = ImageDraw.Draw(chico)
    for i in range(256):
        t = i / 255
        d.point((0, i), fill=tuple(round(arriba[j] + (abajo[j] - arriba[j]) * t) for j in range(3)))
    return chico.resize(tam, Image.BICUBIC).convert("RGBA")


def tarjeta(d, caja, relleno, borde=None, radio=28, grosor=3):
    d.rounded_rectangle(caja, radius=radio, fill=relleno, outline=borde, width=grosor)


def pastilla(lienzo, caja, relleno, borde=None, radio=28, grosor=3):
    """Relleno translucido de verdad.

    `ImageDraw` no mezcla: escribe el RGBA tal cual, asi que un relleno con alfa 46 dejaba el
    pixel casi transparente y al aplanar salia el color plano, tapando el texto de encima.
    Por eso el dibujo va a una capa aparte, que si se compone.
    """
    capa = Image.new("RGBA", lienzo.size, (0, 0, 0, 0))
    ImageDraw.Draw(capa).rounded_rectangle(caja, radius=radio, fill=relleno,
                                           outline=borde, width=grosor)
    lienzo.alpha_composite(capa)


# ─────────────────────────────────────────────────────── 1) Instagram: la marca
def pieza_marca():
    W = H = 1080
    im = Image.new("RGBA", (W, H), hexa(CREMA))
    confeti(im, [(140, 170, 13, hexa(VIOLETA)[:3], 210), (935, 240, 9, hexa(AMARILLO)[:3], 230),
                 (95, 640, 8, hexa(LILA)[:3], 220), (985, 735, 11, hexa(FUCSIA)[:3], 200),
                 (215, 905, 7, hexa(AMARILLO)[:3], 220), (860, 940, 9, hexa(LILA)[:3], 210),
                 (520, 92, 7, hexa(FUCSIA)[:3], 160)])
    d = ImageDraw.Draw(im)
    pegar_alto(im, logo(), W / 2, 168, 560)
    f = ajustar(d, LEMA, W * 0.8, 600, 54)
    centrado(d, LEMA, W / 2, 800, f, hexa(TINTA))
    d.rounded_rectangle([W / 2 - 230, 905, W / 2 + 230, 985], radius=40, fill=hexa(FUCSIA))
    centrado(d, IG, W / 2, 920, fuente(700, 42), hexa("#FFFFFF"))
    centrado(d, WEB, W / 2, 1005, fuente(600, 34), hexa(VIOLETA))
    return im, "instagram-1-marca-1080x1080.png"


# ──────────────────────────────────────────── 2) Instagram: que es y como funciona
ETAPAS = [
    ("La invitación", "Un enlace que se abre como sobre, con música y cuenta regresiva."),
    ("La cabina", "Cada invitado toca su nombre y su personaje lo saluda por su nombre."),
    ("Los juegos", "Cuatro juegos por temática, en la tablet, durante la fiesta."),
    ("El álbum", "Las fotos de todos, en un álbum con enlace privado."),
]


def pieza_como_funciona():
    W, H = 1080, 1350
    im = Image.new("RGBA", (W, H), hexa(CREMA))
    confeti(im, [(80, 300, 9, hexa(LILA)[:3], 200), (1000, 410, 7, hexa(AMARILLO)[:3], 220),
                 (60, 980, 8, hexa(FUCSIA)[:3], 170), (1015, 1090, 10, hexa(VIOLETA)[:3], 160)])
    d = ImageDraw.Draw(im)
    pegar_alto(im, logo(), W / 2, 48, 180)
    titulo = ["Su personaje favorito.", "Su foto. Su recuerdo."]
    y = 250
    for linea in titulo:
        f = ajustar(d, linea, W * 0.86, 800, 76)
        centrado(d, linea, W / 2, y, f, hexa(TINTA))
        y += 88
    y += 14
    for i, (nombre, texto) in enumerate(ETAPAS):
        alto = 176
        tarjeta(d, [64, y, W - 64, y + alto], hexa("#FFFFFF"), hexa(LILA, 90), 34, 3)
        d.ellipse([104, y + 50, 180, y + 126], fill=hexa(VIOLETA) if i % 2 == 0 else hexa(FUCSIA))
        centrado(d, str(i + 1), 142, y + 56, fuente(800, 50), hexa("#FFFFFF"))
        d.text((212, y + 26), nombre, font=fuente(800, 46), fill=hexa(TINTA))
        f = fuente(600, 32)
        for j, linea in enumerate(envolver(d, texto, f, W - 300)[:2]):
            d.text((212, y + 92 + j * 40), linea, font=f, fill=hexa("#5B5470"))
        y += alto + 12
    centrado(d, "Agenda la fecha por WhatsApp", W / 2, y + 24, fuente(800, 44), hexa(FUCSIA))
    centrado(d, WSP + "   ·   " + IG, W / 2, y + 92, fuente(600, 34), hexa(VIOLETA))
    return im, "instagram-2-como-funciona-1080x1350.png"


# ───────────────────────────────────────────────────── 3) Instagram: los planes
PLANES = [
    ("Plan Mágico", "$69.990", "$34.995", ["1 temática a elección", "3 juegos de la temática",
                                           "Hasta 200 fotos", "Invitación digital", "Álbum Recuerdo"], VIOLETA, ""),
    ("Plan Premium", "$99.990", "$49.995", ["1 temática a elección", "Los 4 juegos, con El Show 3D",
                                            "Invitación automática con videos", "Galería privada para papás",
                                            "2 horas de servicio"], FUCSIA, "Más elegido"),
    ("Plan Baby Shower", "$59.990", "$29.995", ["Nubes, Rosas o Safari", "Las apuestas, en su foto",
                                                "Tablero privado para después", "Invitación con lista de regalos",
                                                "Álbum Recuerdo"], LILA, "Otro producto"),
]


def pieza_planes():
    W, H = 1080, 1350
    im = Image.new("RGBA", (W, H), hexa(CREMA))
    d = ImageDraw.Draw(im)
    pegar_alto(im, logo(), W / 2, 36, 172)
    centrado(d, "Precios de lanzamiento", W / 2, 222, fuente(800, 60), hexa(TINTA))
    fb = fuente(800, 32)
    anb = d.textlength("50% de descuento", font=fb) + 56
    d.rounded_rectangle([W / 2 - anb / 2, 306, W / 2 + anb / 2, 366], radius=30, fill=hexa(AMARILLO))
    centrado(d, "50% de descuento", W / 2, 314, fb, hexa(TINTA))
    y = 400
    for nombre, antes, ahora, filas, color, etiqueta in PLANES:
        alto = 268
        tarjeta(d, [56, y, W - 56, y + alto], hexa("#FFFFFF"), hexa(color, 120), 36, 4)
        d.text((100, y + 22), nombre, font=fuente(800, 44), fill=hexa(color))
        if etiqueta:
            f = fuente(700, 26)
            an = d.textlength(etiqueta, font=f) + 40
            pastilla(im, [W - 100 - an, y + 28, W - 100, y + 76], hexa(color, 46), None, 24, 0)
            centrado(d, etiqueta, W - 100 - an / 2, y + 34, f, hexa(color))
        fa = fuente(600, 32)
        an_antes = d.textlength(antes, font=fa)
        d.text((100, y + 96), antes, font=fa, fill=hexa("#9A93AC"))
        d.line([100, y + 114, 100 + an_antes, y + 114], fill=hexa("#9A93AC"), width=3)
        d.text((112 + an_antes, y + 82), ahora, font=fuente(800, 52), fill=hexa(TINTA))
        # Tres viñetas y no cinco: el detalle completo va en el cartel, no en un post.
        f = fuente(600, 28)
        for j, fila in enumerate(filas[:3]):
            cy = y + 152 + j * 34
            d.ellipse([104, cy + 9, 116, cy + 21], fill=hexa(color))
            d.text((130, cy), fila, font=f, fill=hexa("#5B5470"))
        y += alto + 18
    centrado(d, "Temática a medida  +$25.000", W / 2, y + 8, fuente(700, 32), hexa(VIOLETA))
    centrado(d, WSP + "   ·   " + WEB, W / 2, y + 62, fuente(800, 38), hexa(FUCSIA))
    return im, "instagram-3-planes-1080x1350.png"


# ──────────────────────────────────────────────── 4) Fondo de pantalla de tablet
def pieza_fondo(W, H, nombre):
    im = degrade_suave((W, H), hexa(CREMA)[:3], (238, 228, 252))
    r = min(W, H)
    halo = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    dh = ImageDraw.Draw(halo)
    dh.ellipse([W / 2 - r * 0.62, H / 2 - r * 0.72, W / 2 + r * 0.62, H / 2 + r * 0.52],
               fill=hexa(LILA, 46))
    im.alpha_composite(halo.filter(ImageFilter.GaussianBlur(r * 0.09)))
    confeti(im, [(W * 0.12, H * 0.18, r * 0.012, hexa(VIOLETA)[:3], 120),
                 (W * 0.88, H * 0.24, r * 0.009, hexa(AMARILLO)[:3], 140),
                 (W * 0.09, H * 0.78, r * 0.010, hexa(FUCSIA)[:3], 110),
                 (W * 0.91, H * 0.83, r * 0.013, hexa(LILA)[:3], 130)])
    d = ImageDraw.Draw(im)
    alto_logo = round(r * (0.46 if W < H else 0.52))
    tope = round((H - alto_logo - r * 0.11) / 2)
    pegar_alto(im, logo(), W / 2, tope, alto_logo)
    f = ajustar(d, LEMA, W * 0.7, 600, round(r * 0.042))
    centrado(d, LEMA, W / 2, tope + alto_logo + round(r * 0.05), f, hexa(TINTA))
    return im, nombre


# ───────────────────────────────────────────────────── 5) Cartel A4 para imprimir
def pieza_cartel():
    W, H = 2480, 3508  # A4 a 300 ppp
    im = Image.new("RGBA", (W, H), hexa(CREMA))
    d = ImageDraw.Draw(im)
    # Banda superior de marca: color plano de la marca, sin degrade, para que imprima igual.
    d.rectangle([0, 0, W, 96], fill=hexa(VIOLETA))
    d.rectangle([0, 96, W, 116], fill=hexa(ORO))
    confeti(im, [(180, 420, 26, hexa(LILA)[:3], 150), (2300, 520, 20, hexa(AMARILLO)[:3], 170),
                 (140, 1750, 22, hexa(FUCSIA)[:3], 120), (2340, 1900, 26, hexa(VIOLETA)[:3], 110),
                 (250, 3180, 18, hexa(AMARILLO)[:3], 150)])
    pegar_alto(im, logo(), W / 2, 200, 660)
    y = 940
    frase = "Convertimos el cumpleaños en un recuerdo"
    f = ajustar(d, frase, W * 0.86, 800, 118)
    centrado(d, frase, W / 2, y, f, hexa(TINTA))
    y += 180
    bajada = ("Invitación digital, cabina de fotos con personajes que saludan por su nombre, "
              "juegos en la tablet y un álbum con los recuerdos de todos.")
    f = fuente(600, 62)
    for linea in envolver(d, bajada, f, W * 0.82):
        centrado(d, linea, W / 2, y, f, hexa("#5B5470"))
        y += 84
    y += 70
    # Las cuatro etapas, en dos columnas
    an = (W - 300 - 60) / 2
    for i, (nombre, texto) in enumerate(ETAPAS):
        cx = 150 + (i % 2) * (an + 60)
        cy = y + (i // 2) * 310
        tarjeta(d, [cx, cy, cx + an, cy + 270], hexa("#FFFFFF"), hexa(LILA, 90), 44, 5)
        color = hexa(VIOLETA) if i % 2 == 0 else hexa(FUCSIA)
        d.ellipse([cx + 56, cy + 62, cx + 174, cy + 180], fill=color)
        centrado(d, str(i + 1), cx + 115, cy + 76, fuente(800, 78), hexa("#FFFFFF"))
        d.text((cx + 212, cy + 56), nombre, font=fuente(800, 68), fill=hexa(TINTA))
        f = fuente(600, 44)
        for j, linea in enumerate(envolver(d, texto, f, an - 250)[:3]):
            d.text((cx + 212, cy + 146 + j * 56), linea, font=f, fill=hexa("#5B5470"))
    y += 660
    centrado(d, "Temáticas", W / 2, y, fuente(800, 78), hexa(TINTA))
    y += 118
    temas = ["Bluey", "Cars", "Frozen", "Lilo & Stitch", "Capitán América", "KPop Demon Hunters"]
    f = fuente(700, 48)
    x = None
    anchos = [d.textlength(t, font=f) + 70 for t in temas]
    filas, actual, ancho_actual = [], [], 0
    for t, a in zip(temas, anchos):
        if ancho_actual + a > W * 0.84 and actual:
            filas.append((actual, ancho_actual))
            actual, ancho_actual = [], 0
        actual.append((t, a))
        ancho_actual += a + 24
    filas.append((actual, ancho_actual))
    for fila, ancho_fila in filas:
        x = (W - ancho_fila) / 2
        for t, a in fila:
            pastilla(im, [x, y, x + a, y + 86], hexa(VIOLETA, 34), hexa(VIOLETA, 150), 43, 3)
            centrado(d, t, x + a / 2, y + 12, f, hexa(TINTA))
            x += a + 24
        y += 110
    y += 30
    centrado(d, "Y también a medida: Princesas, Dinosaurios, Toy Story, Paw Patrol…",
             W / 2, y, fuente(600, 44), hexa("#5B5470"))
    y += 130
    # Planes
    anp = (W - 300 - 100) / 3
    for i, (nombre, antes, ahora, filas_p, color, etiqueta) in enumerate(PLANES):
        cx = 150 + i * (anp + 50)
        tarjeta(d, [cx, y, cx + anp, y + 510], hexa("#FFFFFF"), hexa(color, 130), 44, 5)
        f = ajustar(d, nombre, anp - 80, 800, 60)
        centrado(d, nombre, cx + anp / 2, y + 40, f, hexa(color))
        centrado(d, antes, cx + anp / 2, y + 130, fuente(600, 44), hexa("#9A93AC"))
        aa = d.textlength(antes, font=fuente(600, 44))
        d.line([cx + anp / 2 - aa / 2, y + 158, cx + anp / 2 + aa / 2, y + 158],
               fill=hexa("#9A93AC"), width=4)
        centrado(d, ahora, cx + anp / 2, y + 186, fuente(800, 96), hexa(TINTA))
        fp = fuente(600, 38)
        yy = y + 306
        for fila in filas_p[:4]:
            for k, linea in enumerate(envolver(d, fila, fp, anp - 130)[:2]):
                if k == 0:
                    d.ellipse([cx + 56, yy + 14, cx + 74, yy + 32], fill=hexa(color))
                d.text((cx + 92, yy), linea, font=fp, fill=hexa("#5B5470"))
                yy += 46
    y += 566
    centrado(d, "Temática a medida  +$25.000   ·   Precios de lanzamiento, 50% de descuento",
             W / 2, y, fuente(700, 44), hexa(VIOLETA))
    # Pie de contacto anclado al borde de la hoja. El renglón de la web va DEBAJO de la banda,
    # sobre crema: antes caía encima del borde, violeta sobre tinta, ilegible al imprimir.
    d.rounded_rectangle([150, H - 388, W - 150, H - 198], radius=52, fill=hexa(TINTA))
    centrado(d, "Agenda la fecha por WhatsApp", W / 2, H - 368, fuente(800, 56), hexa(AMARILLO))
    centrado(d, WSP, W / 2, H - 296, fuente(800, 74), hexa("#FFFFFF"))
    centrado(d, WEB + "   ·   " + IG, W / 2, H - 168, fuente(700, 44), hexa(VIOLETA))
    return im, "cartel-servicios-A4-300ppp.png"


if __name__ == "__main__":
    os.makedirs(SALIDA, exist_ok=True)
    piezas = [pieza_marca(), pieza_como_funciona(), pieza_planes(),
              pieza_fondo(2000, 1200, "tablet-fondo-horizontal-2000x1200.png"),
              pieza_fondo(1200, 2000, "tablet-fondo-vertical-1200x2000.png"),
              pieza_cartel()]
    for im, nombre in piezas:
        ruta = os.path.join(SALIDA, nombre)
        im.convert("RGB").save(ruta, "PNG", optimize=True)
        print("%-44s %5dx%-5d %6.2f MB" % (nombre, im.width, im.height,
                                           os.path.getsize(ruta) / 1048576))
