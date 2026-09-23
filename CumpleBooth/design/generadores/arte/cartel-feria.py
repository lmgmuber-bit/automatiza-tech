# -*- coding: utf-8 -*-
"""Cartel A4 para la mesa de la feria (Mini Feria de Emprendedores, Paseo Dieciochero, 26-sep-2026):
logo, qué hacemos en tres pasos, los planes con precio de lanzamiento, QR de WhatsApp e Instagram y,
abajo, el Instagram y el sitio web. Reusa las piezas de carteles.py (mismo logo, Baloo 2 y paleta).
Regla de Luis (12-sep): el NÚMERO de WhatsApp no se imprime; el QR sí, con etiqueta sin número.
Uso: python cartel-feria.py  -> salida/cartel-feria-A4.pdf (+ PNG 300 ppp y previa .jpg)"""
import os
from PIL import Image, ImageDraw

import carteles as C
from carteles import H, IG_URL, MARGEN, W, WSP_URL, banda_qr, cabecera, guardar
from piezas import (AMARILLO, CREMA, FUCSIA, LILA, PLANES, TINTA, VIOLETA, ajustar, centrado, confeti, envolver,
                    fuente, hexa, tarjeta)

PASOS = [
    ("La invitación interactiva",
     "Se abre en el celular con música, la voz que le cuenta la historia al niño y la confirmación de los papás."),
    ("La cabina de fotos",
     "Cada niño elige a su personaje, juega, se saca su foto y se la lleva impresa con su diploma."),
    ("El Álbum Recuerdo",
     "Los papás lo reciben en su celular con todas las fotos y los videos de la fiesta."),
]
ENLACES_FERIA = [("WhatsApp", WSP_URL, "Escanea y escríbenos"), ("Instagram", IG_URL, "@Cumple_Click")]


def cartel_feria():
    im = Image.new("RGBA", (W, H), hexa(CREMA))
    d = ImageDraw.Draw(im)
    confeti(im, [(180, 760, 26, hexa(LILA)[:3], 150), (2300, 860, 20, hexa(AMARILLO)[:3], 170),
                 (140, 1750, 22, hexa(FUCSIA)[:3], 120), (2340, 1900, 26, hexa(VIOLETA)[:3], 110),
                 (220, 2600, 20, hexa(AMARILLO)[:3], 140), (2280, 2700, 24, hexa(LILA)[:3], 130)])
    y = cabecera(im, d, "Tu cumple, diferente", alto_logo=440)
    y -= 30
    bajada = ("Invitación interactiva, cabina de fotos con personajes, juegos en la tablet "
              "y el Álbum Recuerdo en el celular de los papás.")
    f = fuente(600, 56)
    for linea in envolver(d, bajada, f, W * 0.82):
        centrado(d, linea, W / 2, y, f, hexa("#5B5470"))
        y += 76
    y += 50

    # Tres pasos, tres columnas.
    COLS, ALTO_T = 3, 372
    an = (W - 2 * MARGEN - (COLS - 1) * 50) / COLS
    for i, (nombre, texto) in enumerate(PASOS):
        cx = MARGEN + i * (an + 50)
        tarjeta(d, [cx, y, cx + an, y + ALTO_T], hexa("#FFFFFF"), hexa(LILA, 90), 44, 5)
        color = hexa(VIOLETA) if i % 2 == 0 else hexa(FUCSIA)
        d.ellipse([cx + an / 2 - 44, y + 28, cx + an / 2 + 44, y + 116], fill=color)
        centrado(d, str(i + 1), cx + an / 2, y + 38, fuente(800, 60), hexa("#FFFFFF"))
        ft = ajustar(d, nombre, an - 56, 800, 54)
        centrado(d, nombre, cx + an / 2, y + 138, ft, hexa(TINTA))
        fe = fuente(600, 37)
        for j, linea in enumerate(envolver(d, texto, fe, an - 72)[:3]):
            centrado(d, linea, cx + an / 2, y + 214 + j * 46, fe, hexa("#5B5470"))
    y += ALTO_T + 110

    # Precio de lanzamiento: cinta.
    d.rounded_rectangle([MARGEN, y, W - MARGEN, y + 120], radius=44, fill=hexa(FUCSIA))
    centrado(d, "Precio de lanzamiento: 50% de descuento", W / 2, y + 22, fuente(800, 62), hexa("#FFFFFF"))
    y += 160

    # Los tres planes (mismo bloque que el cartel de servicios).
    anp = (W - 2 * MARGEN - 100) / 3
    for i, (nombre, antes, ahora, filas_p, color, _etiqueta) in enumerate(PLANES):
        cx = MARGEN + i * (anp + 50)
        tarjeta(d, [cx, y, cx + anp, y + 486], hexa("#FFFFFF"), hexa(color, 130), 44, 5)
        f = ajustar(d, nombre, anp - 80, 800, 58)
        centrado(d, nombre, cx + anp / 2, y + 34, f, hexa(color))
        fa = fuente(600, 42)
        centrado(d, antes, cx + anp / 2, y + 118, fa, hexa("#9A93AC"))
        aa = d.textlength(antes, font=fa)
        d.line([cx + anp / 2 - aa / 2, y + 146, cx + anp / 2 + aa / 2, y + 146], fill=hexa("#9A93AC"), width=4)
        centrado(d, ahora, cx + anp / 2, y + 172, fuente(800, 92), hexa(TINTA))
        fp = fuente(600, 37)
        yy = y + 296
        for fila in filas_p[:4]:
            for k, linea in enumerate(envolver(d, fila, fp, anp - 130)[:2]):
                if k == 0:
                    d.ellipse([cx + 56, yy + 13, cx + 74, yy + 31], fill=hexa(color))
                d.text((cx + 92, yy), linea, font=fp, fill=hexa("#5B5470"))
                yy += 44
    y += 486 + 40
    centrado(d, "Temática a medida  +$25.000   ·   Reserva tu fecha hoy", W / 2, y, fuente(700, 44), hexa(VIOLETA))
    y += 150

    # QR de WhatsApp e Instagram (acostados), grandes: se escanean desde el otro lado de la mesa.
    centrado(d, "Escanea y agenda la fecha", W / 2, y, fuente(800, 62), hexa(TINTA))
    y += 120
    y = banda_qr(im, d, y, alto_qr=600, enlaces=ENLACES_FERIA)

    # Pie: Instagram y sitio web.
    yp = H - 250
    d.rounded_rectangle([MARGEN, yp, W - MARGEN, yp + 160], radius=52, fill=hexa(TINTA))
    centrado(d, "@Cumple_Click     ·     cumpleclick.com", W / 2, yp + 36, fuente(800, 66), hexa(AMARILLO))
    assert y <= yp - 40, "la banda de QR pisa el pie: %d > %d" % (y, yp - 40)
    return im, "cartel-feria-A4"


if __name__ == "__main__":
    os.makedirs(C.SALIDA, exist_ok=True)
    im, base = cartel_feria()
    png, pdf = guardar(im, base)
    previa = os.path.join(C.SALIDA, base + "-previa.jpg")
    im.convert("RGB").resize((827, 1170), Image.LANCZOS).save(previa, "JPEG", quality=88)
    print(previa)
