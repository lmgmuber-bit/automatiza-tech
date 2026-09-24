# -*- coding: utf-8 -*-
"""Los dos carteles impresos de CumpleClick, en PNG y en PDF.

1. Cartel de servicios: que es, las cuatro etapas y los tres planes.
2. Cartel de tematicas: la grilla con la imagen real de cada mundo.

Los dos cierran con tres codigos QR —WhatsApp, Instagram y el sitio— porque un cartel
impreso no se puede tocar: el QR es la unica forma de que alguien que lo ve en un local
llegue a nosotros sin copiar un numero a mano.

Las tematicas salen de `public/data/themes.json` con su nombre oficial y su imagen real; no
se escriben a mano ni se inventan. Solo se listan las que TIENEN imagen en disco.

Se exporta PDF a 300 ppp: el PNG queda de 2480x3508, que es A4 exacto a esa resolucion, y
Pillow escribe el tamano de pagina desde ahi. Sin `resolution` el PDF sale gigante.
"""
import io
import json
import os

import segno
from PIL import Image, ImageDraw

import piezas as PZ
from piezas import (AMARILLO, CREMA, ETAPAS, FUCSIA, LILA, ORO, PLANES, TINTA, VIOLETA, WEB,
                    ajustar, centrado, confeti, envolver, fuente, hexa, logo, pastilla,
                    pegar_alto, tarjeta)

AQUI = os.path.dirname(os.path.abspath(__file__))
SALIDA = os.path.join(AQUI, "salida")
TEMAS_DIR = PZ.REPO + "public/themes/"
TEMAS_JSON = PZ.REPO + "public/data/themes.json"

W, H = 2480, 3508  # A4 a 300 ppp
PPP = 300
MARGEN = 150

# Sin mensaje pegado a propósito: con el texto prellenado el código pasaba de 29 a 53
# módulos y dejaba de leerse a la distancia a la que se mira un cartel. Probado decodificando
# el PNG final al 25%.
WSP_URL = "https://wa.me/56974940070"
IG_URL = "https://instagram.com/Cumple_Click"
WEB_URL = "https://cumpleclick.com"

ENLACES = [("WhatsApp", WSP_URL, "+56 9 7494 0070"),
           ("Instagram", IG_URL, "@Cumple_Click"),
           ("Sitio web", WEB_URL, "cumpleclick.com")]

# NINGUNA de las dos hojas de cartel lleva WhatsApp (Luis, 2026-09-12, en dos pedidos: primero
# la de servicios y despues "no corregiste en la 2 pagina el numero de whatsapp"). El numero
# estaba escrito bajo el QR y es el unico dato de contacto que no queria impreso. Sale el
# bloque entero y no solo el texto, porque un QR sin etiqueta al lado de otros dos etiquetados
# se lee como un error de armado.
#
# El WhatsApp no se pierde: tiene su propia hoja, el aviso grande con un solo QR, que es la
# que se pega a la vista y la que de verdad se escanea de lejos.
ENLACES_CARTEL = [e for e in ENLACES if e[0] != "WhatsApp"]

# Las seis tarjetas de la hoja de servicios. Deliberadamente NO se toca `ETAPAS` de piezas.py:
# esa la usan las piezas de Instagram, con otra grilla y otro alto, y agregarle dos entradas
# las desbordaria sin que se note hasta mirarlas una por una.
TARJETAS_CARTEL = [
    ("La invitación", "Un enlace que se abre como sobre, con música y cuenta regresiva."),
    ("La cabina", "Cada invitado toca su nombre y su personaje lo saluda por su nombre."),
    # Se nombran las dos temáticas y no se dice "en algunas": Asómate no está en ningún plan
    # del catálogo, así que sin el límite escrito el vendedor tiene que explicarlo de palabra
    # justo cuando alguien eligió Princesas. Comprobado en themes.json: 2 de 16 lo traen.
    ("Asómate al cuento", "Pone su cara en el hueco del personaje. En Reino de Hielo y Aventuras Arácnidas."),
    ("La foto de todos", "Una foto del grupo entero dentro del marco de la temática."),
    # "Cuatro juegos por temática" era falso, y "hasta tres juegos en 3D" tambien: son dos
    # cosas distintas. Juegos en la tablet los traen 7 de las 16 temáticas, y mundos en 3D
    # solo tres. Seis temáticas de cumpleaños no tienen ninguno de los dos. Contado sobre
    # themes.json y sobre lo que produccion contesta en puntajes.php.
    ("Los juegos", "Juegos en la tablet según la temática, y mundos en 3D en tres de ellas."),
    ("El álbum", "Las fotos de todos, en un álbum con enlace privado."),
]


# ────────────────────────────────────────────────────────────────────── códigos QR
def qr_imagen(url, lado, oscuro=TINTA, claro="#FFFFFF"):
    """QR con corrección alta: un cartel impreso se raya, se dobla y se mira de lejos."""
    # Corrección "q" (25%) y no "h": "h" agrega tantos módulos que, al mismo tamaño impreso,
    # cada módulo queda más chico y el código se lee PEOR. 25% ya tolera un cartel rayado.
    codigo = segno.make(url, error="q")
    buf = io.BytesIO()
    codigo.save(buf, kind="png", scale=10, border=2, dark=oscuro, light=claro)
    buf.seek(0)
    im = Image.open(buf).convert("RGB")
    # NEAREST y no LANCZOS: suavizar los módulos le come el contraste al lector.
    return im.resize((lado, lado), Image.NEAREST)


def banda_qr(im, d, y, alto_qr=460, enlaces=None):
    """Una tarjeta por enlace, repartidas a lo ancho de la hoja.

    Con tres enlaces cada tarjeta es angosta y el QR va arriba con la etiqueta debajo. Con
    dos, cada una es el doble de ancha y conviene acostarla —QR a la izquierda, texto al
    lado—: ocupa 210 px menos de alto, que es lo que necesita la fila extra de tarjetas.
    """
    enlaces = ENLACES if enlaces is None else enlaces
    n = len(enlaces)
    hueco = 40 if n > 2 else 60
    an = (W - 2 * MARGEN - (n - 1) * hueco) / n
    acostada = n <= 2
    # Acostada NO se achica el codigo. La primera version lo bajaba a 0.86 para ganar alto, y
    # con eso el QR de Instagram de la hoja de tematicas quedaba en 0,49 mm por modulo a la
    # medida del acrilico: por debajo del limite al que un telefono empieza a fallar. Acostar
    # la tarjeta ya devuelve 130 px de alto por si sola, que es lo que hacia falta.
    lado = round(alto_qr)
    alto = lado + 80 if acostada else alto_qr + 210
    # Un QR cortado no falla: no decodifica nada y nadie se entera hasta que lo escanea alguien.
    assert y + alto <= H - 260, (
        "la banda de QR no cabe: empieza en %d, necesita %d y la hoja mide %d" % (y, alto, H))
    for i, (titulo, url, etiqueta) in enumerate(enlaces):
        x = MARGEN + i * (an + hueco)
        tarjeta(d, [x, y, x + an, y + alto], hexa("#FFFFFF"), hexa(VIOLETA, 120), 44, 5)
        qr = qr_imagen(url, lado)
        if acostada:
            im.paste(qr, (round(x + 40), round(y + 40)))
            tx = x + 40 + lado + 44
            ancho_texto = an - (tx - x) - 40
            ft = ajustar(d, titulo, ancho_texto, 800, 62)
            d.text((tx, y + alto / 2 - 82), titulo, font=ft, fill=hexa(VIOLETA))
            fe = ajustar(d, etiqueta, ancho_texto, 700, 50)
            d.text((tx, y + alto / 2 + 6), etiqueta, font=fe, fill=hexa(TINTA))
        else:
            centrado(d, titulo, x + an / 2, y + 30, fuente(800, 52), hexa(VIOLETA))
            im.paste(qr, (round(x + an / 2 - lado / 2), round(y + 112)))
            f = ajustar(d, etiqueta, an - 60, 700, 44)
            centrado(d, etiqueta, x + an / 2, y + 130 + lado, f, hexa(TINTA))
    return y + alto


def pie_contacto(d, y):
    d.rounded_rectangle([MARGEN, y, W - MARGEN, y + 150], radius=52, fill=hexa(TINTA))
    centrado(d, "Escanea y agenda la fecha", W / 2, y + 34, fuente(800, 62), hexa(AMARILLO))


def cabecera(im, d, titulo, alto_logo=520):
    d.rectangle([0, 0, W, 96], fill=hexa(VIOLETA))
    d.rectangle([0, 96, W, 116], fill=hexa(ORO))
    pegar_alto(im, logo(), W / 2, 176, alto_logo)
    y = 176 + alto_logo + 54
    f = ajustar(d, titulo, W * 0.86, 800, 118)
    centrado(d, titulo, W / 2, y, f, hexa(TINTA))
    return y + 170


# ───────────────────────────────────────────────────── 1) el cartel de servicios
def cartel_servicios():
    im = Image.new("RGBA", (W, H), hexa(CREMA))
    d = ImageDraw.Draw(im)
    confeti(im, [(180, 760, 26, hexa(LILA)[:3], 150), (2300, 860, 20, hexa(AMARILLO)[:3], 170),
                 (140, 1750, 22, hexa(FUCSIA)[:3], 120), (2340, 1900, 26, hexa(VIOLETA)[:3], 110)])
    y = cabecera(im, d, "Convertimos el cumpleaños en un recuerdo")
    bajada = ("Invitación digital, cabina de fotos con personajes que saludan por su nombre, "
              "juegos en la tablet y un álbum con los recuerdos de todos.")
    f = fuente(600, 58)
    for linea in envolver(d, bajada, f, W * 0.82):
        centrado(d, linea, W / 2, y, f, hexa("#5B5470"))
        y += 80
    y += 60
    # Las seis tarjetas, en tres columnas. En tres columnas la tarjeta es angosta y el numero
    # ya no cabe al lado del titulo: va arriba, centrado, y debajo el titulo y el texto.
    # 404 y no 372: con tres renglones de texto el tercero rozaba el borde de abajo de la
    # tarjeta. Se vio recien al mirar el PDF ampliado; el codigo no tiene como avisarlo,
    # porque Pillow dibuja fuera de la caja sin quejarse.
    COLS, ALTO_T, HUECO_T = 3, 404, 40
    an = (W - 2 * MARGEN - (COLS - 1) * 50) / COLS
    for i, (nombre, texto) in enumerate(TARJETAS_CARTEL):
        cx = MARGEN + (i % COLS) * (an + 50)
        cy = y + (i // COLS) * (ALTO_T + HUECO_T)
        tarjeta(d, [cx, cy, cx + an, cy + ALTO_T], hexa("#FFFFFF"), hexa(LILA, 90), 44, 5)
        color = hexa(VIOLETA) if i % 2 == 0 else hexa(FUCSIA)
        d.ellipse([cx + an / 2 - 48, cy + 30, cx + an / 2 + 48, cy + 126], fill=color)
        centrado(d, str(i + 1), cx + an / 2, cy + 42, fuente(800, 64), hexa("#FFFFFF"))
        ft = ajustar(d, nombre, an - 56, 800, 56)
        centrado(d, nombre, cx + an / 2, cy + 150, ft, hexa(TINTA))
        fe = fuente(600, 38)
        # Tres renglones como tope: el cuarto se sale de la tarjeta sin avisar.
        lineas = envolver(d, texto, fe, an - 72)[:3]
        for j, linea in enumerate(lineas):
            centrado(d, linea, cx + an / 2, cy + 228 + j * 48, fe, hexa("#5B5470"))
    y += 2 * (ALTO_T + HUECO_T) + 16
    # Los tres planes.
    anp = (W - 2 * MARGEN - 100) / 3
    for i, (nombre, antes, ahora, filas_p, color, _etiqueta) in enumerate(PLANES):
        cx = MARGEN + i * (anp + 50)
        tarjeta(d, [cx, y, cx + anp, y + 486], hexa("#FFFFFF"), hexa(color, 130), 44, 5)
        f = ajustar(d, nombre, anp - 80, 800, 58)
        centrado(d, nombre, cx + anp / 2, y + 34, f, hexa(color))
        fa = fuente(600, 42)
        centrado(d, antes, cx + anp / 2, y + 118, fa, hexa("#9A93AC"))
        aa = d.textlength(antes, font=fa)
        d.line([cx + anp / 2 - aa / 2, y + 146, cx + anp / 2 + aa / 2, y + 146],
               fill=hexa("#9A93AC"), width=4)
        centrado(d, ahora, cx + anp / 2, y + 172, fuente(800, 92), hexa(TINTA))
        fp = fuente(600, 37)
        yy = y + 296
        for fila in filas_p[:4]:
            for k, linea in enumerate(envolver(d, fila, fp, anp - 130)[:2]):
                if k == 0:
                    d.ellipse([cx + 56, yy + 13, cx + 74, yy + 31], fill=hexa(color))
                d.text((cx + 92, yy), linea, font=fp, fill=hexa("#5B5470"))
                yy += 44
    y += 540
    centrado(d, "Temática a medida  +$25.000   ·   Precios de lanzamiento, 50% de descuento",
             W / 2, y, fuente(700, 44), hexa(VIOLETA))
    y += 100
    banda_qr(im, d, y, enlaces=ENLACES_CARTEL)
    pie_contacto(d, H - 240)
    return im, "cartel-servicios-A4"


# ───────────────────────────────────────────────────── 2) el cartel de temáticas
def temas_con_imagen():
    """Las temáticas que tienen imagen en disco, con su nombre oficial de themes.json."""
    datos = json.load(io.open(TEMAS_JSON, encoding="utf-8"))
    crudo = datos.get("themes", datos) if isinstance(datos, dict) else datos
    nombres = {}
    if isinstance(crudo, dict):
        for slug, v in crudo.items():
            nombres[slug] = (v.get("nombre") or v.get("name") or slug) if isinstance(v, dict) else str(v)
    else:
        for v in crudo:
            nombres[v.get("slug", "")] = v.get("nombre") or v.get("name") or v.get("slug", "")
    fiesta, bebe = [], []
    for slug, nombre in nombres.items():
        ruta = TEMAS_DIR + slug + "/fondo-banner.jpg"
        if not os.path.isfile(ruta):
            continue
        (bebe if slug.startswith("baby-") else fiesta).append((slug, nombre, ruta))
    return fiesta, bebe, [n for s, n in nombres.items()
                          if not os.path.isfile(TEMAS_DIR + s + "/fondo-banner.jpg")]


# Centro vertical del recorte de cada temática, como fracción de la altura de la imagen.
# Se eligió mirando cada banner, no con una fórmula: las figuras están a distinta altura en
# cada uno y un valor único dejaba dos temáticas mostrando pared vacía.
CENTROS = {
    "carreras": 0.70, "familia-canina": 0.53, "heroes": 0.71, "hielo": 0.61,
    "kpop": 0.62, "spidey": 0.515, "tropical": 0.57,
    "baby-nube": 0.30, "baby-safari": 0.60, "baby-rosas": 0.28,
}
CENTRO_POR_DEFECTO = 0.46

# Cuánto del ancho de la imagen ocupa el cuadrado. Menos de 1 es acercarse. Solo para los dos
# azulejos que se veían vacíos: mover el centro no los arregla porque ya están en su tope.
ZOOM = {"carreras": 0.84, "baby-rosas": 0.78, "heroes": 0.92}
# Centro horizontal, para los casos en que lo interesante no está al medio.
CENTROS_X = {"baby-rosas": 0.60}

# El orden de la grilla se fija a mano: por saturación y por cuánto llena cada figura su
# azulejo. hielo es el más pálido y spidey el más saturado, y vecinos hacen que hielo se vea
# lavado impreso; carreras es el más plano y va entre dos cargados.
ORDEN_FIESTA = ["kpop", "carreras", "familia-canina", "hielo",
                "spidey", "tropical", "heroes"]


def azulejo(im, d, ruta, nombre, x, y, lado, color, slug=""):
    """Un mundo: recorte cuadrado del banner real, con su nombre debajo."""
    foto = Image.open(ruta).convert("RGB")
    corte = min(foto.width, foto.height)
    corte = round(corte * ZOOM.get(slug, 1.0))
    centro = CENTROS.get(slug, CENTRO_POR_DEFECTO)
    # El centro pedido se lleva al rango donde el cuadrado entra entero en la imagen.
    arriba = max(0, min(foto.height - corte, round(centro * foto.height - corte / 2)))
    izq = max(0, min(foto.width - corte,
                     round(CENTROS_X.get(slug, 0.5) * foto.width - corte / 2)))
    foto = foto.crop((izq, arriba, izq + corte, arriba + corte))
    foto = foto.resize((lado, lado), Image.LANCZOS)
    mascara = Image.new("L", (lado, lado), 0)
    ImageDraw.Draw(mascara).rounded_rectangle([0, 0, lado - 1, lado - 1], radius=36, fill=255)
    im.paste(foto, (round(x), round(y)), mascara)
    d.rounded_rectangle([x, y, x + lado, y + lado], radius=36, outline=hexa(color, 160), width=6)
    f = ajustar(d, nombre, lado, 800, 46)
    centrado(d, nombre, x + lado / 2, y + lado + 14, f, hexa(TINTA))


def cartel_tematicas():
    fiesta, bebe, a_medida = temas_con_imagen()
    # La grilla se ordena a mano (ver ORDEN_FIESTA); lo que no esté en la lista va al final.
    por_slug = {s: (s, n, r) for s, n, r in fiesta}
    fiesta = ([por_slug[s] for s in ORDEN_FIESTA if s in por_slug]
              + [v for s, v in por_slug.items() if s not in ORDEN_FIESTA])
    im = Image.new("RGBA", (W, H), hexa(CREMA))
    d = ImageDraw.Draw(im)
    confeti(im, [(150, 900, 24, hexa(LILA)[:3], 140), (2330, 1500, 22, hexa(AMARILLO)[:3], 150),
                 (130, 2400, 20, hexa(FUCSIA)[:3], 120)])
    y = cabecera(im, d, "Elige tu mundo", 340)

    # El azulejo NO se deriva del margen: se fija para que la hoja cierre con la banda de QR
    # dentro, y la grilla se centra con el sobrante.
    cols, hueco, lado, paso = 4, 34, 440, 96
    izq = (W - (cols * lado + (cols - 1) * hueco)) / 2
    colores = [VIOLETA, FUCSIA, LILA, AMARILLO]

    centrado(d, "Cumpleaños", W / 2, y, fuente(800, 68), hexa(VIOLETA))
    y += 106
    for i, (_slug, nombre, ruta) in enumerate(fiesta):
        azulejo(im, d, ruta, nombre, izq + (i % cols) * (lado + hueco),
                y + (i // cols) * (lado + paso), lado, colores[i % 4], _slug)
    filas = (len(fiesta) + cols - 1) // cols
    # La celda libre de la última fila cuenta lo que no está en la grilla.
    libre = filas * cols - len(fiesta)
    if libre:
        x = izq + (len(fiesta) % cols) * (lado + hueco)
        cy = y + (filas - 1) * (lado + paso)
        pastilla(im, [x, cy, x + lado, cy + lado], hexa(VIOLETA, 30), hexa(VIOLETA, 140), 36, 6)
        f = fuente(800, 44)
        centrado(d, "A medida", x + lado / 2, cy + lado * 0.30, f, hexa(VIOLETA))
        fm = fuente(600, 32)
        texto = ", ".join(a_medida[:6]) + "…"
        for j, linea in enumerate(envolver(d, texto, fm, lado - 60)[:4]):
            centrado(d, linea, x + lado / 2, cy + lado * 0.44 + j * 42, fm, hexa("#5B5470"))
    y += filas * (lado + paso) + 24

    centrado(d, "Baby shower", W / 2, y, fuente(800, 68), hexa(FUCSIA))
    y += 106
    ancho_bebe = len(bebe) * lado + (len(bebe) - 1) * hueco
    x0 = (W - ancho_bebe) / 2
    for i, (_slug, nombre, ruta) in enumerate(bebe):
        azulejo(im, d, ruta, nombre, x0 + i * (lado + hueco), y, lado,
                [FUCSIA, LILA, VIOLETA][i % 3], _slug)
    y += lado + 100

    banda_qr(im, d, y, 400, enlaces=ENLACES_CARTEL)
    pie_contacto(d, H - 240)
    return im, "cartel-tematicas-A4"


def guardar(im, base):
    plano = im.convert("RGB")
    png = os.path.join(SALIDA, base + "-300ppp.png")
    pdf = os.path.join(SALIDA, base + ".pdf")
    plano.save(png, "PNG", optimize=True)
    # Pillow guarda el PDF en JPEG. Con los valores por defecto el peor pixel se iba 135
    # niveles y el 7,8% de la hoja quedaba con error visible: eso es zumbido alrededor del
    # texto y de los modulos del QR, justo lo que se nota impreso en grande. Sin submuestreo
    # de color y a calidad 100 el peor pixel baja a 5 y ningun pixel pasa de 8. Medido contra
    # el PNG; cuesta 1,7 MB mas por hoja y los vale.
    plano.save(pdf, "PDF", resolution=PPP, quality=100, subsampling=0)
    print("%-34s PNG %5.2f MB   PDF %5.2f MB" % (base, os.path.getsize(png) / 1048576,
                                                 os.path.getsize(pdf) / 1048576))
    return png, pdf


if __name__ == "__main__":
    os.makedirs(SALIDA, exist_ok=True)
    for im, base in (cartel_servicios(), cartel_tematicas()):
        guardar(im, base)
