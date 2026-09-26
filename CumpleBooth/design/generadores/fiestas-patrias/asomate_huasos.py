# -*- coding: utf-8 -*-
"""Asómate de Fiestas Patrias: huasos y huasitas con el hueco de la cara (2026-09-26).

Los seis cuerpos vienen de Higgsfield (flare, 9:16, fondo gris liso, de pie y de frente). Este script:
  1. recorta el fondo en local con rembg (`isnet-general-use`, ya descargado; no gasta nada);
  2. ubica la cara por el tono de piel en la parte alta de la figura y propone el óvalo del hueco;
  3. comprueba que el borde del óvalo (radios 1,00 a 1,05) caiga 100 % sobre la figura, como pide
     docs/ASOMATE-NUEVAS-TEMATICAS.md, y lo achica si no;
  4. `ver`: hoja de contacto para revisar a ojo; `hacer`: escribe los PNG con el hueco, recortados al contorno y en
     paleta de 220 colores, y anota la geometría en el bloque `asomate` de fiestas-patrias en themes.json.

Los ajustes finos por personaje van en ajustes-asomate.json (dx, dy en fracción del ancho de la cara; kx, ky).
Uso: python asomate_huasos.py ver | hacer
"""
import io
import json
import math
import os
import sys

import cv2
import numpy as np
from PIL import Image, ImageDraw, ImageFont

AQUI = os.path.dirname(os.path.abspath(__file__))
CB = os.path.abspath(os.path.join(AQUI, "..", "..", ".."))
ORIGENES = os.environ.get("FP_ORIGENES", r"C:/Users/luis_/Documents/CumpleClick/tematicas-adultos-2026-09-26/higgsfield")
CACHE = os.path.join(ORIGENES, "recortes")
DESTINO = os.path.join(CB, "public", "themes", "fiestas-patrias", "asomate")
THEMES = os.path.join(CB, "public", "data", "themes.json")
TTF = os.path.join(CB, "design", "generadores", "arte", "ttf", "baloo2-700.ttf")
ALTO_FINAL = 1300  # alto de la figura en el PNG final (Olaf mide 1363 y Elsa 1211)

PERSONAJES = [  # clave, nombre visible, emoji
    ("huasita-floreada", "Huasita de vestido floreado", "💃"),
    ("huasita-chupalla", "Huasita de chupalla", "💃"),
    ("huasita-manta", "Huasita de manta", "💃"),
    ("huaso-chamanto", "Huaso de chamanto", "🤠"),
    ("huaso-chupalla", "Huaso de chupalla", "🤠"),
    ("huaso-gala", "Huaso de gala", "🤠"),
]


def rellenar_huecos(im, clave):
    """Un hueco transparente encerrado por la figura puede ser aire (entre el brazo y el cuerpo, entre las piernas)
    o un error del recorte (rembg se comió un pedazo del delantal blanco de la huasita floreada). Se distingue por el
    color original: si ahí había gris de fondo queda transparente; si había tela, se rellena."""
    origen = np.array(Image.open(os.path.join(ORIGENES, "fiestas-patrias-asomate--%s-v1.png" % clave)).convert("RGB")).astype(int)
    esquinas = np.concatenate([origen[:40, :40].reshape(-1, 3), origen[:40, -40:].reshape(-1, 3),
                               origen[-40:, :40].reshape(-1, 3), origen[-40:, -40:].reshape(-1, 3)])
    fondo = np.median(esquinas, axis=0)
    a = np.array(im.getchannel("A"))
    rgb = np.array(im.convert("RGB"))
    fuera = (a <= 128).astype(np.uint8)
    n, lab, stats, _ = cv2.connectedComponentsWithStats(fuera, 4)
    borde = set(np.unique(np.concatenate([lab[0], lab[-1], lab[:, 0], lab[:, -1]])))
    rellenos = 0
    for i in range(1, n):
        if i in borde:
            continue
        zona = lab == i
        if np.abs(origen[zona].mean(axis=0) - fondo).sum() > 30:
            a[zona] = 255
            rgb[zona] = origen[zona]  # rembg deja en negro el color de lo que borra: se devuelve el original
            rellenos += int(zona.sum())
    if rellenos:
        print("%s: %d px de tela devueltos al recorte" % (clave, rellenos))
    im = Image.fromarray(rgb.astype(np.uint8)).convert("RGBA")
    im.putalpha(Image.fromarray(a))
    return im


def recorte(clave):
    """RGBA sin fondo, cacheado: rembg tarda y el resultado no cambia."""
    os.makedirs(CACHE, exist_ok=True)
    ruta = os.path.join(CACHE, clave + ".png")
    if os.path.isfile(ruta):
        return rellenar_huecos(Image.open(ruta).convert("RGBA"), clave)
    from rembg import new_session, remove
    sesion = new_session("isnet-general-use")
    origen = Image.open(os.path.join(ORIGENES, "fiestas-patrias-asomate--%s-v1.png" % clave)).convert("RGB")
    im = remove(origen, session=sesion, post_process_mask=True)
    # Solo la figura: el componente opaco más grande (fuera quedan manchas sueltas del fondo).
    a = np.array(im.getchannel("A"))
    n, lab, stats, _ = cv2.connectedComponentsWithStats((a > 128).astype(np.uint8), 8)
    if n > 1:
        mayor = 1 + int(np.argmax(stats[1:, cv2.CC_STAT_AREA]))
        a[lab != mayor] = 0
    im.putalpha(Image.fromarray(a))
    im.save(ruta)
    return rellenar_huecos(im, clave)


def caja_figura(a):
    ys, xs = np.nonzero(a > 40)
    return int(xs.min()), int(ys.min()), int(xs.max()), int(ys.max())


def cara(im, ajuste):
    """Óvalo de la cara: la mancha de piel más grande y centrada en el tercio alto de la figura."""
    rgb = np.array(im.convert("RGB"))
    a = np.array(im.getchannel("A"))
    x0, y0, x1, y1 = caja_figura(a)
    if all(k in ajuste for k in ("cx", "cy", "rx", "ry")):
        # Medido a ojo sobre la grilla (cejas a mentón, centrado entre los ojos): manda sobre la detección, que
        # confunde la chupalla de paja con piel y se lleva el cuello en los huasos.
        return {k: float(ajuste[k]) for k in ("cx", "cy", "rx", "ry")}, (x0, y0, x1, y1)
    alto = y1 - y0
    ycc = cv2.cvtColor(rgb, cv2.COLOR_RGB2YCrCb)
    Y, Cr, Cb = ycc[..., 0], ycc[..., 1], ycc[..., 2]
    piel = (Cr >= 135) & (Cr <= 178) & (Cb >= 80) & (Cb <= 128) & (Y > 70) & (a > 200)
    piel[: y0, :] = False
    piel[y0 + int(alto * 0.30):, :] = False
    m = cv2.morphologyEx(piel.astype(np.uint8), cv2.MORPH_OPEN, np.ones((5, 5), np.uint8))
    n, lab, stats, cent = cv2.connectedComponentsWithStats(m, 8)
    centro = (x0 + x1) / 2
    mejor, mejor_area = None, 0
    for i in range(1, n):
        if abs(cent[i][0] - centro) < (x1 - x0) * 0.22 and stats[i, cv2.CC_STAT_AREA] > mejor_area:
            mejor, mejor_area = i, stats[i, cv2.CC_STAT_AREA]
    if mejor is None:
        raise RuntimeError("no se encontró la cara")
    comp = lab == mejor
    filas = np.nonzero(comp.any(axis=1))[0]
    top = int(filas.min())
    anchos = comp.sum(axis=1)
    fila_max = int(np.argmax(anchos))
    ancho_cara = int(anchos[fila_max])
    # Centro horizontal: el promedio de las filas anchas (la cara), no del cuello ni de las orejas.
    anchas = [y for y in filas if anchos[y] >= ancho_cara * 0.8]
    cx = float(np.mean([np.nonzero(comp[y])[0].mean() for y in anchas]))
    menton = min(int(filas.max()), int(top + ancho_cara * 1.28))
    cy = (top + menton) / 2 + ancho_cara * ajuste.get("dy", 0)
    cx += ancho_cara * ajuste.get("dx", 0)
    rx = ancho_cara * 0.42 * ajuste.get("kx", 1.0)
    ry = (menton - top) * 0.47 * ajuste.get("ky", 1.0)
    return {"cx": cx, "cy": cy, "rx": rx, "ry": ry}, (x0, y0, x1, y1)


def anillo_dentro(a, ov):
    """Fracción del borde exterior del óvalo (radios 1,00 a 1,05) que cae sobre la figura."""
    total = dentro = 0
    for k in (1.0, 1.025, 1.05):
        for i in range(360):
            t = math.radians(i)
            x = int(round(ov["cx"] + ov["rx"] * k * math.cos(t)))
            y = int(round(ov["cy"] + ov["ry"] * k * math.sin(t)))
            total += 1
            if 0 <= y < a.shape[0] and 0 <= x < a.shape[1] and a[y, x] > 200:
                dentro += 1
    return dentro / total


def medir(clave, ajustes):
    im = recorte(clave)
    a = np.array(im.getchannel("A"))
    ov, caja = cara(im, ajustes.get(clave, {}))
    frac = anillo_dentro(a, ov)
    pasos = 0
    while frac < 1.0 and pasos < 12:  # se achica hasta que el borde quede entero dentro de la figura
        ov["rx"] *= 0.97
        ov["ry"] *= 0.97
        frac = anillo_dentro(a, ov)
        pasos += 1
    return im, ov, caja, frac, pasos


def hoja(resultados):
    f = ImageFont.truetype(TTF, 26)
    paneles = []
    for clave, im, ov, caja, frac, pasos in resultados:
        x0, y0, x1, y1 = caja
        v = Image.new("RGBA", im.size, (226, 233, 241, 255))
        v.alpha_composite(im)
        d = ImageDraw.Draw(v)
        d.ellipse((ov["cx"] - ov["rx"], ov["cy"] - ov["ry"], ov["cx"] + ov["rx"], ov["cy"] + ov["ry"]),
                  outline=(255, 0, 90, 255), width=6)
        entera = v.crop((x0 - 20, y0 - 20, x1 + 20, y1 + 20)).convert("RGB")
        entera.thumbnail((260, 700))
        cabeza = v.crop((int(ov["cx"] - ov["rx"] * 2.2), int(ov["cy"] - ov["ry"] * 2.0),
                         int(ov["cx"] + ov["rx"] * 2.2), int(ov["cy"] + ov["ry"] * 2.0))).convert("RGB")
        cabeza.thumbnail((260, 300))
        p = Image.new("RGB", (280, 1060), (250, 250, 250))
        p.paste(entera, ((280 - entera.width) // 2, 10))
        p.paste(cabeza, ((280 - cabeza.width) // 2, 720))
        ImageDraw.Draw(p).text((140, 1040), "%s  %d%%%s" % (clave, round(frac * 100), " (-%d)" % pasos if pasos else ""),
                               font=f, fill=(30, 30, 40), anchor="mm")
        paneles.append(p)
    salida = Image.new("RGB", (280 * len(paneles) + 10 * (len(paneles) + 1), 1080), "white")
    for i, p in enumerate(paneles):
        salida.paste(p, (10 + i * 290, 10))
    ruta = os.path.join(ORIGENES, "huecos-fiestas-patrias.jpg")
    salida.save(ruta, quality=90)
    print("->", ruta)


def instalar(resultados):
    os.makedirs(DESTINO, exist_ok=True)
    personajes = {}
    for clave, im, ov, caja, frac, pasos in resultados:
        if frac < 1.0:
            raise SystemExit("%s: el borde del hueco se sale de la figura (%.0f %%)" % (clave, frac * 100))
        x0, y0, x1, y1 = caja
        con_hueco = im.copy()
        m = Image.new("L", im.size, 0)
        ImageDraw.Draw(m).ellipse((ov["cx"] - ov["rx"], ov["cy"] - ov["ry"], ov["cx"] + ov["rx"], ov["cy"] + ov["ry"]), fill=255)
        al = con_hueco.getchannel("A")
        al.paste(0, (0, 0), m)
        con_hueco.putalpha(al)
        fig = con_hueco.crop((x0, y0, x1 + 1, y1 + 1))
        k = ALTO_FINAL / fig.height
        fig = fig.resize((round(fig.width * k), ALTO_FINAL), Image.LANCZOS)
        fig = fig.quantize(colors=220, method=Image.FASTOCTREE).convert("RGBA")
        fig.save(os.path.join(DESTINO, clave + ".png"), optimize=True)
        nombre, emoji = next((n, e) for c, n, e in PERSONAJES if c == clave)
        personajes[clave] = {
            "nombre": nombre, "emoji": emoji,
            "w": fig.width, "h": fig.height, "arriba": 0, "pies": fig.height - 1, "izq": 0, "der": fig.width - 1,
            "cx": round((ov["cx"] - x0) * k, 1), "cy": round((ov["cy"] - y0) * k, 1),
            "rx": round(ov["rx"] * k, 1), "ry": round(ov["ry"] * k, 1),
        }
        print("%s: %dx%d, %d KB" % (clave, fig.width, fig.height, os.path.getsize(os.path.join(DESTINO, clave + ".png")) // 1024))
    bloque = {"fondo": "asomate/fondo.jpg", "personajes": personajes,
              "boton": "🇨🇱 Asómate de huaso o huasita", "titulo": "¿Huaso o huasita? Elige tu traje"}
    raw = open(THEMES, "rb").read()
    crlf = b"\r\n" in raw
    datos = json.loads(raw.decode("utf-8"))
    datos["themes"]["fiestas-patrias"]["asomate"] = bloque
    texto = json.dumps(datos, ensure_ascii=False, indent=2) + "\n"
    if crlf:
        texto = texto.replace("\n", "\r\n")
    open(THEMES, "wb").write(texto.encode("utf-8"))
    print("themes.json: bloque asomate de fiestas-patrias con %d personajes" % len(personajes))


if __name__ == "__main__":
    modo = sys.argv[1] if len(sys.argv) > 1 else "ver"
    ruta_aj = os.path.join(AQUI, "ajustes-asomate.json")
    ajustes = json.load(io.open(ruta_aj, encoding="utf-8")) if os.path.isfile(ruta_aj) else {}
    resultados = []
    for clave, _n, _e in PERSONAJES:
        if not os.path.isfile(os.path.join(ORIGENES, "fiestas-patrias-asomate--%s-v1.png" % clave)):
            print("falta", clave)
            continue
        im, ov, caja, frac, pasos = medir(clave, ajustes)
        print("%s: óvalo cx=%.0f cy=%.0f rx=%.0f ry=%.0f, borde dentro %.0f %%" % (clave, ov["cx"], ov["cy"], ov["rx"], ov["ry"], frac * 100))
        resultados.append((clave, im, ov, caja, frac, pasos))
    hoja(resultados)
    if modo == "hacer":
        instalar(resultados)
