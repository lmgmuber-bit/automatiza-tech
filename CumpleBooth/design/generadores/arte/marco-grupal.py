# -*- coding: utf-8 -*-
"""Fondo de foto grupal, dibujando el marco en vez de generarlo.

Por que dibujado y no generado: cinco intentos con Higgsfield dieron siempre un marco de
tamano "normal" y vertical, aunque se pidieran medidas explicitas, negaciones y referencias.
El generador tiene una idea muy firme de como es un cuadro colgado. Dibujarlo cuesta cero
creditos y, mas importante, deja la geometria EXACTA: el `frameBox` sale por construccion en
vez de medirse a ojo despues, que es justo el error que costo caro en baby-rosas.

El truco para no tener que borrar el marco que la sala ya trae: el marco nuevo se dibuja
OPACO y con su caja exterior mas grande que la del viejo, asi lo tapa entero y no queda
ningun resto asomando por los bordes.
"""
import io
import json
import os

import numpy as np
from PIL import Image, ImageDraw, ImageFilter

AQUI = os.path.dirname(os.path.abspath(__file__))
SALIDA = os.path.join(AQUI, "grupal")
# CumpleBooth/, tres carpetas arriba de este archivo; antes, una ruta fija a una worktree vieja.
REPO = os.path.abspath(os.path.join(AQUI, "..", "..", "..")).replace("\\", "/") + "/"
TEMAS_DIR = REPO + "public/themes/"

# Color del marco por tematica, tomado del que la sala ya usa.
ORO = {"hielo": ((255, 228, 138), (206, 154, 32), (140, 96, 12)),
       "spidey": ((255, 224, 120), (214, 158, 30), (132, 90, 10))}


def marco_viejo(im):
    """Donde esta el marco que la sala ya trae.

    Por rachas horizontales y no por sumas de filas: el piso y las mesas tambien son claros y
    planos, asi que sumar pixeles claros por fila engancha cualquier cosa. Lo que distingue al
    hueco del marco es que tiene una RACHA continua ancha, y que no toca el borde inferior.
    Este es el metodo que midio bien el fondo de hielo (372 x 384).
    """
    a = np.asarray(im.convert("RGB")).astype(np.int16)
    H, W = a.shape[:2]
    m = (a.min(axis=2) > 150) & ((a.max(axis=2) - a.min(axis=2)) < 34)

    def racha(fila):
        mejor = ini = cur = cini = 0
        for x, v in enumerate(fila):
            if v:
                if cur == 0:
                    cini = x
                cur += 1
                if cur > mejor:
                    mejor, ini = cur, cini
            else:
                cur = 0
        return ini, mejor

    rs = [racha(m[y]) for y in range(H)]
    largos = np.array([r[1] for r in rs])
    bandas, dentro = [], None
    for y in range(H):
        if largos[y] > W * 0.15:
            if dentro is None:
                dentro = y
        elif dentro is not None:
            bandas.append((dentro, y - 1))
            dentro = None
    if dentro is not None:
        bandas.append((dentro, H - 1))
    cand = [b for b in bandas if b[1] - b[0] > 80 and b[1] < H - 40]
    if not cand:
        raise SystemExit("no encuentro el marco en la sala")
    y0, y1 = max(cand, key=lambda b: (b[1] - b[0]) * np.median(largos[b[0]:b[1] + 1]))
    an = int(np.median([rs[y][1] for y in range(y0, y1 + 1)]))
    x0 = int(np.median([rs[y][0] for y in range(y0, y1 + 1)]))
    return x0, y0, x0 + an, y1


def moldura(d, caja, colores, grosor):
    """Moldura de tres tonos: luz arriba, cuerpo, sombra abajo. Sencilla pero con volumen."""
    claro, cuerpo, oscuro = colores
    x0, y0, x1, y1 = caja
    r = grosor * 0.55
    d.rounded_rectangle([x0, y0, x1, y1], radius=r, fill=cuerpo)
    # Bisel exterior: una linea clara arriba-izquierda y una oscura abajo-derecha.
    d.line([(x0 + r, y0 + 2), (x1 - r, y0 + 2)], fill=claro, width=max(2, grosor // 7))
    d.line([(x0 + 2, y0 + r), (x0 + 2, y1 - r)], fill=claro, width=max(2, grosor // 7))
    d.line([(x0 + r, y1 - 2), (x1 - r, y1 - 2)], fill=oscuro, width=max(2, grosor // 7))
    d.line([(x1 - 2, y0 + r), (x1 - 2, y1 - r)], fill=oscuro, width=max(2, grosor // 7))


def fondo_grupal(tema, ancho_rel=0.86, proporcion=1.85, grosor_rel=0.034):
    ruta = TEMAS_DIR + tema + "/fondo-sala.jpg"
    im = Image.open(ruta).convert("RGB")
    W, H = im.size
    vx0, vy0, vx1, vy1 = marco_viejo(im)
    cx, cy = (vx0 + vx1) / 2, (vy0 + vy1) / 2

    grosor = round(W * grosor_rel)
    an_ext = round(W * ancho_rel)
    ab_int = an_ext - 2 * grosor
    al_int = round(ab_int / proporcion)
    al_ext = al_int + 2 * grosor

    # El marco nuevo tiene que tapar al viejo entero: si no llega, se engorda la moldura.
    falta = (vy1 - vy0 + 24) - al_ext
    if falta > 0:
        grosor += round(falta / 2)
        al_ext = al_int + 2 * grosor
    x0, y0 = round(cx - an_ext / 2), round(cy - al_ext / 2)
    x1, y1 = x0 + an_ext, y0 + al_ext

    capa = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    sombra = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(sombra).rounded_rectangle(
        [x0 + 10, y0 + 14, x1 + 10, y1 + 14], radius=grosor * 0.55, fill=(0, 0, 0, 120))
    im.paste(Image.alpha_composite(im.convert("RGBA"), sombra.filter(
        ImageFilter.GaussianBlur(grosor * 0.5))).convert("RGB"), (0, 0))

    d = ImageDraw.Draw(capa)
    moldura(d, (x0, y0, x1, y1), ORO.get(tema, ORO["hielo"]), grosor)
    # El hueco: blanco liso, que es lo que el kiosco espera encontrar para pegar la foto.
    d.rounded_rectangle([x0 + grosor, y0 + grosor, x1 - grosor, y1 - grosor],
                        radius=grosor * 0.18, fill=(255, 255, 255, 255))
    im = Image.alpha_composite(im.convert("RGBA"), capa).convert("RGB")

    caja = {"x": round((x0 + grosor) / W, 4), "y": round((y0 + grosor) / H, 4),
            "w": round(ab_int / W, 4), "h": round(al_int / H, 4)}
    return im, caja, (ab_int, al_int), (vx1 - vx0, vy1 - vy0)


if __name__ == "__main__":
    os.makedirs(SALIDA, exist_ok=True)
    cajas = {}
    for tema in ("hielo", "spidey"):
        im, caja, (ai, hi), (va, vh) = fondo_grupal(tema)
        ruta = os.path.join(SALIDA, "%s-fondo-grupal.jpg" % tema)
        im.save(ruta, "JPEG", quality=92, optimize=True)
        cajas[tema] = caja
        print("%-8s hueco %d x %d px (%.0f%% del ancho), antes era %d x %d  ->  %.2f:1"
              % (tema, ai, hi, 100 * ai / im.width, va, vh, ai / hi))
        print("         frameBoxGrupal = %s" % json.dumps(caja))
    io.open(os.path.join(SALIDA, "frameBoxGrupal.json"), "w", encoding="utf-8").write(
        json.dumps(cajas, indent=2, ensure_ascii=False))
