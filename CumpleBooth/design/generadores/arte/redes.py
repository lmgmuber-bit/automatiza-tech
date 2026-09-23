# -*- coding: utf-8 -*-
"""Avatar de Instagram y carrusel de 7 laminas, segun design/social/ESPECIFICACIONES.md.

El avatar lleva SOLO el globo, sin el nombre: a tamano de avatar el wordmark no se lee. Lo
dice la especificacion y se comprueba mirando un circulo de 110 px.

El carrusel es de 1080x1350 y de maximo 7 laminas, tambien por la especificacion. La portada
tiene que dar ganas de deslizar, asi que lleva el titular y una senal de "desliza"; las cinco
del medio cuentan el servicio paso a paso; la ultima cierra con precio y WhatsApp.

REGLA DURA heredada de la especificacion: en piezas de marca las tematicas se nombran de
forma generica. Por eso aca la de los perros se llama "Familia Canina" y no por su marca,
aunque el sitio publico si use el nombre de la franquicia.
"""
import os

from PIL import Image, ImageDraw

from piezas import (AMARILLO, CREMA, FUCSIA, LILA, TINTA, VIOLETA, ajustar, centrado, confeti,
                    envolver, fuente, hexa, logo, pegar_alto, tarjeta)
import carteles as C
from avisos import globo, marca_de_agua

SALIDA = C.SALIDA
W, H = 1080, 1350

# Nombre genérico por temática, para piezas de marca.
GENERICOS = {"familia-canina": "Familia Canina", "hielo": "Reino de Hielo",
             "carreras": "Carreras Veloces", "heroes": "Súper Héroes",
             "kpop": "Guerreras K-Pop", "spidey": "Aventuras Arácnidas",
             "tropical": "Aventura Tropical"}


def avatar(lado=1080):
    """El globo solo, centrado y con aire: Instagram lo recorta en círculo."""
    im = Image.new("RGBA", (lado, lado), hexa(CREMA))
    confeti(im, [(lado * 0.17, lado * 0.20, lado * 0.016, hexa(LILA)[:3], 180),
                 (lado * 0.84, lado * 0.26, lado * 0.012, hexa(AMARILLO)[:3], 190),
                 (lado * 0.20, lado * 0.80, lado * 0.013, hexa(FUCSIA)[:3], 170)])
    g = globo()
    # 62% del lado: dentro del círculo que recorta Instagram queda con aire por los cuatro
    # costados incluso en la esquina, que es donde el recorte circular muerde más.
    alto = round(lado * 0.62)
    pegar_alto(im, g, lado / 2, round((lado - alto) / 2), alto)
    return im, "instagram-avatar-1080x1080"


def base_lamina(numero=None, total=None):
    im = Image.new("RGBA", (W, H), hexa(CREMA))
    marca_de_agua(im, opacidad=16, lado=300, paso_x=420, paso_y=400) if False else None
    d = ImageDraw.Draw(im)
    if numero:
        f = fuente(700, 30)
        texto = "%d / %d" % (numero, total)
        d.text((W - 64 - d.textlength(texto, font=f), 52), texto, font=f, fill=hexa(LILA))
    return im, d


def flecha_desliza(d, y):
    centrado(d, "Desliza  ›››", W / 2, y, fuente(800, 44), hexa(FUCSIA))


PASOS = [
    ("La invitación", "Un enlace que llega por WhatsApp y se abre como un sobre, con música, "
                      "los personajes de la temática y la cuenta regresiva.",
     "El primer “wow” pasa antes de la fiesta."),
    ("La cabina", "Cada invitado toca su nombre en la tablet. Una ruleta le da un personaje, "
                  "que lo saluda por su nombre, y se lleva su foto y su diploma.",
     "Nadie se va con las manos vacías."),
    ("Los juegos", "Cuatro juegos en 3D por temática, en la misma tablet, durante la fiesta.",
     "Los grandes conversan tranquilos."),
    ("El álbum", "Todas las fotos del día, en un álbum con enlace privado para la familia.",
     "El recuerdo queda, no se pierde en un chat."),
]


def lamina_portada():
    im, d = base_lamina()
    pegar_alto(im, logo(), W / 2, 78, 230)
    y = 380
    for linea in ["Su personaje favorito.", "Su foto.", "Su recuerdo."]:
        f = ajustar(d, linea, W * 0.86, 800, 92)
        centrado(d, linea, W / 2, y, f, hexa(TINTA))
        y += 108
    y += 40
    fb = fuente(600, 42)
    for linea in envolver(d, "Photo booth temático para cumpleaños infantiles, "
                             "con invitación, juegos y álbum.", fb, W * 0.8):
        centrado(d, linea, W / 2, y, fb, hexa("#5B5470"))
        y += 58
    flecha_desliza(d, H - 210)
    centrado(d, "@Cumple_Click", W / 2, H - 130, fuente(700, 38), hexa(VIOLETA))
    return im, "instagram-carrusel-1-portada"


def lamina_paso(i):
    titulo, cuerpo, remate = PASOS[i]
    im, d = base_lamina(i + 2, 7)
    color = [VIOLETA, FUCSIA, LILA, VIOLETA][i]
    d.ellipse([W / 2 - 92, 150, W / 2 + 92, 334], fill=hexa(color))
    centrado(d, str(i + 1), W / 2, 186, fuente(800, 116), hexa("#FFFFFF"))
    y = 420
    f = ajustar(d, titulo, W * 0.8, 800, 90)
    centrado(d, titulo, W / 2, y, f, hexa(TINTA))
    y += 150
    fb = fuente(600, 44)
    for linea in envolver(d, cuerpo, fb, W * 0.78):
        centrado(d, linea, W / 2, y, fb, hexa("#5B5470"))
        y += 62
    y += 50
    fr = ajustar(d, remate, W * 0.82, 800, 46)
    centrado(d, remate, W / 2, y, fr, hexa(color))
    flecha_desliza(d, H - 170)
    return im, "instagram-carrusel-%d-%s" % (i + 2, ["invitacion", "cabina", "juegos", "album"][i])


def lamina_tematicas():
    im, d = base_lamina(6, 7)
    y = 110
    centrado(d, "Elige tu mundo", W / 2, y, fuente(800, 84), hexa(TINTA))
    y += 150
    fiesta, _bebe, _ = C.temas_con_imagen()
    orden = [s for s in C.ORDEN_FIESTA if any(s == x[0] for x in fiesta)]
    por_slug = {s: (s, n, r) for s, n, r in fiesta}
    cols, hueco = 3, 26
    lado = round((W * 0.86 - (cols - 1) * hueco) / cols)
    izq = (W - (cols * lado + (cols - 1) * hueco)) / 2
    colores = [VIOLETA, FUCSIA, LILA]
    for i, slug in enumerate(orden[:6]):
        _s, _n, ruta = por_slug[slug]
        C.azulejo(im, d, ruta, GENERICOS.get(slug, _n),
                  izq + (i % cols) * (lado + hueco), y + (i // cols) * (lado + 78),
                  lado, colores[i % 3], slug)
    y += 2 * (lado + 78) + 20
    centrado(d, "Y la tuya a medida", W / 2, y, fuente(700, 42), hexa(VIOLETA))
    flecha_desliza(d, H - 150)
    return im, "instagram-carrusel-6-tematicas"


def lamina_cierre():
    im, d = base_lamina(7, 7)
    pegar_alto(im, logo(), W / 2, 70, 210)
    y = 330
    centrado(d, "Precios de lanzamiento", W / 2, y, fuente(800, 74), hexa(TINTA))
    y += 116
    fb = fuente(800, 36)
    an = d.textlength("50% de descuento", font=fb) + 64
    d.rounded_rectangle([W / 2 - an / 2, y, W / 2 + an / 2, y + 76], radius=38, fill=hexa(AMARILLO))
    centrado(d, "50% de descuento", W / 2, y + 14, fb, hexa(TINTA))
    y += 132
    for nombre, _antes, ahora, _filas, color, _e in C.PLANES:
        tarjeta(d, [90, y, W - 90, y + 124], hexa("#FFFFFF"), hexa(color, 120), 30, 4)
        f = ajustar(d, nombre, W * 0.44, 800, 44)
        d.text((132, y + 36), nombre, font=f, fill=hexa(color))
        fa = fuente(800, 52)
        d.text((W - 132 - d.textlength(ahora, font=fa), y + 30), ahora, font=fa, fill=hexa(TINTA))
        y += 144
    y += 26
    centrado(d, "Escríbenos por WhatsApp", W / 2, y, fuente(800, 54), hexa(FUCSIA))
    centrado(d, "El enlace está en la biografía", W / 2, y + 76, fuente(600, 38), hexa("#5B5470"))
    centrado(d, "@Cumple_Click  ·  cumpleclick.com", W / 2, H - 130, fuente(700, 36), hexa(VIOLETA))
    return im, "instagram-carrusel-7-cierre"


if __name__ == "__main__":
    os.makedirs(SALIDA, exist_ok=True)
    piezas = [avatar(), lamina_portada()] + [lamina_paso(i) for i in range(4)] + \
             [lamina_tematicas(), lamina_cierre()]
    for im, base in piezas:
        ruta = os.path.join(SALIDA, base + ".png")
        im.convert("RGB").save(ruta, "PNG", optimize=True)
        print("%-44s %4dx%-4d %6.2f MB" % (base, im.width, im.height,
                                           os.path.getsize(ruta) / 1048576))
