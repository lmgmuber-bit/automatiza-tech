# -*- coding: utf-8 -*-
"""Marco grande para la foto grupal, hecho con la moldura REAL de cada sala.

El primer intento dibujo la moldura a mano y quedo una plancha mostaza plana: geometria
correcta, aspecto malo. La solucion no es dibujar mejor, es no dibujar: el fondo del kiosco ya
trae una moldura dorada labrada preciosa, solo que en vertical.

Se recorta esa moldura en nueve trozos —cuatro esquinas, cuatro lados y el centro— y se rearma
sobre un rectangulo mas ancho. Las esquinas van tal cual, para que el labrado no se deforme, y
solo los lados se estiran a lo largo, que es donde el patron es repetitivo y no se nota. Es la
tecnica de siempre para agrandar un marco sin que se vea estirado.

Asi el marco nuevo ES el marco de la tematica, no una imitacion.
"""
import io
import json
import os

import numpy as np
from PIL import Image

AQUI = os.path.dirname(os.path.abspath(__file__))
SALIDA = os.path.join(AQUI, "grupal")
# Las tematicas del propio repositorio; antes, una ruta fija a una worktree vieja.
TEMAS = os.path.abspath(os.path.join(AQUI, "..", "..", "..", "public", "themes")).replace("\\", "/") + "/"

# Umbral de "hueco" por tematica: el interior de hielo es casi blanco y el de spidey es un
# gris azulado mas oscuro, asi que un solo numero no sirve para las dos.
HUECO = {"hielo": (150, 34), "spidey": (110, 60)}


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


def hueco(im, tema):
    """El rectangulo interior del marco que la sala ya trae."""
    luz, sat = HUECO.get(tema, (150, 34))
    a = np.asarray(im.convert("RGB")).astype(np.int16)
    H, W = a.shape[:2]
    m = (a.min(axis=2) > luz) & ((a.max(axis=2) - a.min(axis=2)) < sat)
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
        raise SystemExit("no encuentro el hueco del marco en %s" % tema)
    y0, y1 = max(cand, key=lambda b: (b[1] - b[0]) * np.median(largos[b[0]:b[1] + 1]))
    an = int(np.median([rs[y][1] for y in range(y0, y1 + 1)]))
    x0 = int(np.median([rs[y][0] for y in range(y0, y1 + 1)]))
    return x0, y0, x0 + an, y1


def dorado(im, hue, margen=0.45):
    """Los bordes exteriores de la moldura.

    Antes se crecia desde el hueco hasta que el dorado "se acababa", y en spidey eso se comio
    media pared: el fondo tiene amarillos por todas partes (cortinas, globos, trama) y el
    crecimiento no paraba. Ahora se mira SOLO una ventana alrededor del hueco y se toma la
    caja de los pixeles dorados que hay dentro. Acotado por construccion, no por suerte.
    """
    a = np.asarray(im.convert("RGB")).astype(np.int16)
    H, W = a.shape[:2]
    x0, y0, x1, y1 = hue
    an, al = x1 - x0, y1 - y0
    vx0 = max(0, round(x0 - an * margen)); vx1 = min(W, round(x1 + an * margen))
    vy0 = max(0, round(y0 - al * margen)); vy1 = min(H, round(y1 + al * margen))
    v = a[vy0:vy1, vx0:vx1]
    oro = (v[:, :, 0] - v[:, :, 2] > 45) & (v[:, :, 0] > 120) & (v[:, :, 1] > 80)
    # Solo el anillo: lo que esta dentro del hueco no cuenta.
    oro[y0 - vy0:y1 - vy0, x0 - vx0:x1 - vx0] = False
    filas = np.where(oro.sum(axis=1) > (vx1 - vx0) * 0.10)[0]
    cols = np.where(oro.sum(axis=0) > (vy1 - vy0) * 0.10)[0]
    if not len(filas) or not len(cols):
        raise SystemExit("no encuentro la moldura dorada")
    return (vx0 + int(cols[0]), vy0 + int(filas[0]),
            vx0 + int(cols[-1]) + 1, vy0 + int(filas[-1]) + 1)


def nueve(pieza, an, al, esquina):
    """Rearma `pieza` al tamano pedido: esquinas intactas, lados estirados."""
    w, h = pieza.size
    e = min(esquina, w // 2 - 1, h // 2 - 1)
    out = Image.new("RGBA", (an, al), (0, 0, 0, 0))
    partes = {
        "si": (0, 0, e, e), "sd": (w - e, 0, w, e),
        "ii": (0, h - e, e, h), "id": (w - e, h - e, w, h),
        "sup": (e, 0, w - e, e), "inf": (e, h - e, w - e, h),
        "izq": (0, e, e, h - e), "der": (w - e, e, w, h - e),
    }
    c = {k: pieza.crop(v) for k, v in partes.items()}
    out.paste(c["sup"].resize((an - 2 * e, e), Image.LANCZOS), (e, 0))
    out.paste(c["inf"].resize((an - 2 * e, e), Image.LANCZOS), (e, al - e))
    out.paste(c["izq"].resize((e, al - 2 * e), Image.LANCZOS), (0, e))
    out.paste(c["der"].resize((e, al - 2 * e), Image.LANCZOS), (an - e, e))
    out.paste(c["si"], (0, 0)); out.paste(c["sd"], (an - e, 0))
    out.paste(c["ii"], (0, al - e)); out.paste(c["id"], (an - e, al - e))
    return out


def fondo_grupal(tema, ancho_rel=0.88, proporcion=1.8):
    im = Image.open(TEMAS + tema + "/fondo-sala.jpg").convert("RGBA")
    W, H = im.size
    hx0, hy0, hx1, hy1 = hueco(im, tema)
    ox0, oy0, ox1, oy1 = dorado(im, (hx0, hy0, hx1, hy1))
    grosor = max(hx0 - ox0, hy0 - oy0, ox1 - hx1, oy1 - hy1)
    pieza = im.crop((ox0, oy0, ox1, oy1))

    an_ext = round(W * ancho_rel)
    ab_int = an_ext - 2 * grosor
    al_int = round(ab_int / proporcion)
    al_ext = al_int + 2 * grosor
    # Tiene que tapar al viejo por completo, o asoma el labrado de abajo.
    if al_ext < (oy1 - oy0) + 16:
        al_ext = (oy1 - oy0) + 16
        al_int = al_ext - 2 * grosor
    cx, cy = (ox0 + ox1) / 2, (oy0 + oy1) / 2
    x0, y0 = round(cx - an_ext / 2), round(cy - al_ext / 2)

    nuevo = nueve(pieza, an_ext, al_ext, esquina=round(grosor * 1.15))
    # El hueco, blanco liso: es lo que el compositor espera para pegar la foto.
    relleno = Image.new("RGBA", (ab_int, al_int), (255, 255, 255, 255))
    nuevo.paste(relleno, (grosor, grosor))
    im.alpha_composite(nuevo, (x0, y0))

    caja = {"x": round((x0 + grosor) / W, 4), "y": round((y0 + grosor) / H, 4),
            "w": round(ab_int / W, 4), "h": round(al_int / H, 4)}
    return im.convert("RGB"), caja, (ab_int, al_int), (hx1 - hx0, hy1 - hy0), grosor


if __name__ == "__main__":
    os.makedirs(SALIDA, exist_ok=True)
    cajas = {}
    for tema in ("hielo", "spidey"):
        im, caja, (ai, hi), (va, vh), g = fondo_grupal(tema)
        im.save(os.path.join(SALIDA, "%s-fondo-grupal.jpg" % tema), "JPEG", quality=92, optimize=True)
        cajas[tema] = caja
        print("%-8s moldura %d px | hueco %d x %d (antes %d x %d) | %.2f:1 | superficie x%.1f"
              % (tema, g, ai, hi, va, vh, ai / hi, (ai * hi) / max(1, va * vh)))
        print("         frameBoxGrupal = %s" % json.dumps(caja))
    io.open(os.path.join(SALIDA, "frameBoxGrupal.json"), "w", encoding="utf-8").write(
        json.dumps(cajas, indent=2, ensure_ascii=False))
