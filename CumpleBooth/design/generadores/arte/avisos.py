# -*- coding: utf-8 -*-
"""Los dos avisos de un solo QR: Instagram y WhatsApp.

Van pegados donde la gente pasa: un QR grande, el canal, y nada mas que estorbe. El de
WhatsApp NO lleva el numero escrito (pedido de Luis, 2026-09-11): quien lo escanea llega al
chat, y un numero impreso solo invita a copiarlo mal.

El fondo lleva una marca de agua construida con el LOGO REAL repetido, no con una imagen
generada: el globo de la marca, muy claro, en diagonal. Asi el aviso se reconoce de lejos
como nuestro incluso antes de leerlo.

Los iconos de WhatsApp e Instagram se dibujan simplificados y en un solo color de marca. Son
marcas de terceros y aqui cumplen su unica funcion legitima: decir a que aplicacion lleva el
codigo. No se imita su color corporativo ni se presentan como propias.
"""
import math
import os

from PIL import Image, ImageDraw, ImageFilter

from piezas import (AMARILLO, CREMA, FUCSIA, LILA, ORO, TINTA, VIOLETA, ajustar, centrado,
                    envolver, fuente, hexa, logo, pegar_alto, tarjeta)
import carteles as C

W, H, MARGEN = C.W, C.H, C.MARGEN


# ──────────────────────────────────────────────────────────── la marca de agua
def globo():
    """Solo el globo del logo, sin el texto: el lockup trae el nombre debajo."""
    im = logo()
    return im.crop((0, 0, im.width, round(im.height * 0.70)))


def marca_de_agua(lienzo, opacidad=26, lado=520, paso_x=760, paso_y=700, giro=-18):
    """Globos de marca repetidos en diagonal, muy claros, detrás de todo."""
    g = globo()
    g = g.resize((lado, round(g.height * lado / g.width)), Image.LANCZOS)
    tinta = Image.new("RGBA", g.size, (0, 0, 0, 0))
    tinta.paste(hexa(VIOLETA)[:3] + (255,), (0, 0), g)          # silueta en un solo color
    alfa = tinta.getchannel("A").point(lambda v: v * opacidad // 255)
    tinta.putalpha(alfa)
    tinta = tinta.rotate(giro, expand=True, resample=Image.BICUBIC)

    capa = Image.new("RGBA", (W + paso_x, H + paso_y), (0, 0, 0, 0))
    fila = 0
    y = -paso_y // 2
    while y < H + paso_y:
        x = -paso_x // 2 + (paso_x // 2 if fila % 2 else 0)
        while x < W + paso_x:
            capa.alpha_composite(tinta, (x, y))
            x += paso_x
        y += paso_y
        fila += 1
    lienzo.alpha_composite(capa.crop((0, 0, W, H)))


def fondo(color_arriba=CREMA, color_abajo=(246, 240, 255)):
    im = C.PZ.degrade_suave((W, H), hexa(color_arriba)[:3], color_abajo)
    marca_de_agua(im)
    return im


# ─────────────────────────────────────────────────────────── iconos de canal
def icono_whatsapp(lado, color):
    """Burbuja de chat con tres puntos.

    El auricular dibujado a mano se leia como una pesa, y ademas imitar el simbolo de una
    marca ajena no aporta nada: la burbuja de chat se entiende sola y la palabra WhatsApp va
    escrita en grande justo debajo."""
    im = Image.new("RGBA", (lado, lado), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    m = lado * 0.06
    cuerpo = [m, m, lado - m, lado - m * 3.2]
    d.rounded_rectangle(cuerpo, radius=lado * 0.26, fill=color)
    # La colita, abajo a la izquierda, apuntando hacia afuera.
    d.polygon([(lado * 0.26, lado - m * 3.4), (lado * 0.50, lado - m * 3.4),
               (lado * 0.22, lado - m * 0.4)], fill=color)
    r = lado * 0.055
    cy = (cuerpo[1] + cuerpo[3]) / 2
    for k in (-1, 0, 1):
        cx = lado / 2 + k * lado * 0.20
        d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=(255, 255, 255, 255))
    return im


def icono_instagram(lado, color):
    """Cámara: cuadrado redondeado, lente y punto del flash."""
    im = Image.new("RGBA", (lado, lado), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    m = lado * 0.10
    grosor = round(lado * 0.085)
    d.rounded_rectangle([m, m, lado - m, lado - m], radius=lado * 0.27,
                        outline=color, width=grosor)
    r = lado * 0.19
    d.ellipse([lado / 2 - r, lado / 2 - r, lado / 2 + r, lado / 2 + r],
              outline=color, width=grosor)
    p = lado * 0.045
    d.ellipse([lado * 0.71 - p, lado * 0.27 - p, lado * 0.71 + p, lado * 0.27 + p], fill=color)
    return im


CANALES = {
    "instagram": {
        "titulo": "Síguenos en Instagram",
        "bajada": "Mira fiestas de verdad: las fotos, los personajes y los juegos.",
        "url": C.IG_URL,
        "pie": "@Cumple_Click",
        "color": FUCSIA,
        "icono": icono_instagram,
        "archivo": "aviso-qr-instagram-A4",
    },
    "whatsapp": {
        "titulo": "Escríbenos por WhatsApp",
        "bajada": "Cuéntanos la fecha y te pasamos el valor el mismo día.",
        "url": C.WSP_URL,
        # Sin número escrito, a propósito: el QR abre el chat solo.
        "pie": "Escanea con la cámara de tu teléfono",
        "color": VIOLETA,
        "icono": icono_whatsapp,
        "archivo": "aviso-qr-whatsapp-A4",
    },
}


def aviso_qr(canal):
    c = CANALES[canal]
    im = fondo()
    d = ImageDraw.Draw(im)
    d.rectangle([0, 0, W, 96], fill=hexa(c["color"]))
    d.rectangle([0, 96, W, 116], fill=hexa(ORO))

    pegar_alto(im, logo(), W / 2, 176, 400)
    y = 640

    ic = c["icono"](400, hexa(c["color"]))
    im.alpha_composite(ic, (round(W / 2 - 200), y))
    y += 466

    f = ajustar(d, c["titulo"], W * 0.84, 800, 132)
    centrado(d, c["titulo"], W / 2, y, f, hexa(TINTA))
    y += 190

    fb = fuente(600, 60)
    for linea in envolver(d, c["bajada"], fb, W * 0.78):
        centrado(d, linea, W / 2, y, fb, hexa("#5B5470"))
        y += 82
    y += 60

    # El QR, grande y sobre blanco sólido: la marca de agua detrás de un código lo arruina.
    lado = 1180
    caja = [W / 2 - lado / 2 - 70, y, W / 2 + lado / 2 + 70, y + lado + 140]
    d.rounded_rectangle(caja, radius=60, fill=hexa("#FFFFFF"), outline=hexa(c["color"], 150), width=8)
    im.paste(C.qr_imagen(c["url"], lado), (round(W / 2 - lado / 2), round(y + 70)))
    y += lado + 200

    f = ajustar(d, c["pie"], W * 0.8, 800, 66)
    centrado(d, c["pie"], W / 2, y, f, hexa(c["color"]))
    y += 130

    # El hueco que quedaba entre el QR y el pie lleva la oferta: es lo que hace que alguien
    # que pasa se detenga a escanear.
    fo = fuente(800, 62)
    texto = "Precios de lanzamiento · 50% de descuento"
    an = d.textlength(texto, font=fo) + 90
    d.rounded_rectangle([W / 2 - an / 2, y, W / 2 + an / 2, y + 104], radius=52,
                        fill=hexa(AMARILLO))
    centrado(d, texto, W / 2, y + 18, fo, hexa(TINTA))

    d.rounded_rectangle([MARGEN, H - 240, W - MARGEN, H - 90], radius=52, fill=hexa(TINTA))
    centrado(d, "CumpleClick  ·  cumpleclick.com", W / 2, H - 206, fuente(800, 58), hexa(AMARILLO))
    return im, c["archivo"]


if __name__ == "__main__":
    os.makedirs(C.SALIDA, exist_ok=True)
    for canal in ("instagram", "whatsapp"):
        C.guardar(*aviso_qr(canal))
