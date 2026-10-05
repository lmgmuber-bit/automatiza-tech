# -*- coding: utf-8 -*-
"""Asómate de las temáticas infantiles nuevas: brujitas y navidad (2026-09-30).

Los seis cuerpos de cada temática salen de la imagen aprobada del personaje, editada por Higgsfield (alibaba/qwen-image-3/edit,
USD 0,04 de lista) para dejarlo de pie, de frente, con los brazos abajo y sobre gris liso (`tematicas-2026-10/asomate_generar.py`,
fuera del repo). Con la pose de los saludos no servía: el murciélago con las alas abiertas medía 1050 px de ancho por 905 de
alto, y en una foto grupal la figura más ancha manda la altura de todas (`alturaComunAsomate` en src/App.jsx). Este script:
  1. recorta el fondo en local con rembg (`isnet-general-use`, no gasta nada) y devuelve al recorte la tela que rembg se comió;
  2. toma el óvalo de la cara de ajustes-<tema>.json (medido a ojo: cejas a mentón, con los ojos del muñeco ENTEROS adentro; si
     no, asoman junto a la cara de la persona) y comprueba que su borde (radios 1,00 a 1,05) caiga 100 % sobre la figura, como
     pide docs/ASOMATE-NUEVAS-TEMATICAS.md; si no, lo achica;
  3. `cuadricula`: recortes de la cabeza con una cuadrícula en píxeles de origen, para medir; `ver`: hoja de contacto con el
     óvalo dibujado; `hacer`: escribe los PNG con el hueco (recortados al contorno, 220 colores) en
     public/themes/<tema>/asomate/ y anota el bloque `asomate` de la temática en themes.json.
Uso: python asomate_tematica.py <tema> cuadricula | ver | hacer
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
BASE = os.environ.get("ASOMATE_ORIGENES", r"C:/Users/luis_/Documents/CumpleClick/tematicas-2026-10")
THEMES = os.path.join(CB, "public", "data", "themes.json")
TTF = os.path.join(CB, "design", "generadores", "arte", "ttf", "baloo2-700.ttf")
ALTO_FINAL = 1300  # alto de la figura en el PNG final, igual que Fiestas Patrias

TEXTOS = {  # el nombre del modo lo decide la temática (docs/ASOMATE-NUEVAS-TEMATICAS.md, paso 6)
    "brujitas": {"boton": "🎃 Asómate y sé de la Noche de Brujas", "titulo": "¿Con quién te quieres asomar?", "fondo": "fondo-escena.jpg"},
    # `suelo`: fracción del alto donde pisan los pies en una foto de grupo. El piso de Navidad empieza en 0,86; con el 0,8 de siempre
    # el grupo flotaba delante de la pared. Noche de Brujas tiene el piso desde 0,78 y le sirve el de siempre.
    "navidad": {"boton": "🎄 Asómate y sé parte de la Navidad", "titulo": "¿Con quién te quieres asomar?", "fondo": "fondo-escena.jpg", "suelo": 0.865},
}


def carpeta(tema):
    return os.path.join(BASE, tema, "asomate")


def personajes_de(tema):
    datos = json.load(io.open(THEMES, encoding="utf-8"))["themes"][tema]["personajes"]
    return [(p["img"].rsplit(".", 1)[0], p["name"], p["emoji"]) for p in datos]


def version_de(tema, clave):
    """La versión elegida (versiones-asomate.json) o, si no hay, la más nueva que exista."""
    ruta = os.path.join(carpeta(tema), "versiones-asomate.json")
    if os.path.isfile(ruta):
        v = json.load(io.open(ruta, encoding="utf-8")).get(clave)
        if v:
            return int(v)
    hechas = [int(f.split("-v")[1].split(".")[0]) for f in os.listdir(carpeta(tema)) if f.startswith(clave + "-v") and f.endswith(".png")]
    return max(hechas) if hechas else None


def ruta_origen(tema, clave):
    v = version_de(tema, clave)
    return os.path.join(carpeta(tema), "%s-v%d.png" % (clave, v)) if v else None


def rellenar_huecos(im, origen_ruta):
    """Un hueco transparente encerrado por la figura puede ser aire (entre el brazo y el cuerpo) o un error del recorte (rembg
    se come tela clara sobre el fondo gris). Se distingue por el color original: si ahí había gris de fondo queda transparente;
    si había tela, se rellena."""
    origen = np.array(Image.open(origen_ruta).convert("RGB")).astype(int)
    esquinas = np.concatenate([origen[:40, :40].reshape(-1, 3), origen[:40, -40:].reshape(-1, 3),
                               origen[-40:, :40].reshape(-1, 3), origen[-40:, -40:].reshape(-1, 3)])
    fondo = np.median(esquinas, axis=0)
    a = np.array(im.getchannel("A"))
    rgb = np.array(im.convert("RGB"))
    fuera = (a <= 128).astype(np.uint8)
    n, lab, _s, _c = cv2.connectedComponentsWithStats(fuera, 4)
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
        print("  %d px de tela devueltos al recorte" % rellenos)
    im = Image.fromarray(rgb.astype(np.uint8)).convert("RGBA")
    im.putalpha(Image.fromarray(a))
    return im


def recorte(tema, clave):
    """RGBA sin fondo, cacheado: rembg tarda y el resultado no cambia."""
    origen = ruta_origen(tema, clave)
    cache = os.path.join(carpeta(tema), "recortes")
    os.makedirs(cache, exist_ok=True)
    ruta = os.path.join(cache, os.path.basename(origen))
    if os.path.isfile(ruta):
        return rellenar_huecos(Image.open(ruta).convert("RGBA"), origen)
    from rembg import new_session, remove
    im = remove(Image.open(origen).convert("RGB"), session=new_session("isnet-general-use"), post_process_mask=True)
    a = np.array(im.getchannel("A"))
    n, lab, stats, _c = cv2.connectedComponentsWithStats((a > 128).astype(np.uint8), 8)
    if n > 1:  # solo la figura: el componente opaco más grande
        a[lab != 1 + int(np.argmax(stats[1:, cv2.CC_STAT_AREA]))] = 0
    im.putalpha(Image.fromarray(a))
    im.save(ruta)
    return rellenar_huecos(im, origen)


def caja_figura(a):
    ys, xs = np.nonzero(a > 40)
    return int(xs.min()), int(ys.min()), int(xs.max()), int(ys.max())


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


def solida(a):
    """La silueta sin los huecos finos: el hilado del pelo de la abuela deja huecos de alfa bajo entre las hebras, y el borde del
    óvalo pasaría por ahí aunque la cabeza siga. Lo que importa es que el óvalo no salga de la silueta, no cada hebra."""
    return cv2.morphologyEx(((a > 128) * 255).astype(np.uint8), cv2.MORPH_CLOSE, np.ones((21, 21), np.uint8))


def medir(tema, clave, ajustes):
    im = recorte(tema, clave)
    a = np.array(im.getchannel("A"))
    caja = caja_figura(a)
    a = solida(a)
    aj = ajustes.get(clave)
    if not aj or not all(k in aj for k in ("cx", "cy", "rx", "ry")):
        # Sin medida a ojo, un óvalo provisional en el tercio alto para poder verlo y corregirlo.
        x0, y0, x1, y1 = caja
        aj = {"cx": (x0 + x1) / 2, "cy": y0 + (y1 - y0) * 0.25, "rx": (x1 - x0) * 0.18, "ry": (y1 - y0) * 0.09}
    ov = {k: float(aj[k]) for k in ("cx", "cy", "rx", "ry")}
    frac = anillo_dentro(a, ov)
    pasos = 0
    while frac < 1.0 and pasos < 12:  # se achica hasta que el borde quede entero dentro de la figura
        ov["rx"] *= 0.97
        ov["ry"] *= 0.97
        frac = anillo_dentro(a, ov)
        pasos += 1
    return im, ov, caja, frac, pasos


def cuadricula(tema):
    """Recorte de la cabeza de cada figura, a escala 1:1 con una cuadrícula de 50 px rotulada en píxeles de origen."""
    f = ImageFont.truetype(TTF, 15)
    for clave, _n, _e in personajes_de(tema):
        if not ruta_origen(tema, clave):
            continue
        im = recorte(tema, clave)
        x0, y0, x1, y1 = caja_figura(np.array(im.getchannel("A")))
        alto = y1 - y0
        zona = (max(0, x0 - 20), max(0, y0 - 20), min(im.width, x1 + 20), min(im.height, y0 + int(alto * 0.5)))
        v = Image.new("RGBA", im.size, (226, 233, 241, 255))
        v.alpha_composite(im)
        d = ImageDraw.Draw(v)
        for x in range((zona[0] // 50) * 50, zona[2], 50):
            d.line([(x, zona[1]), (x, zona[3])], fill=(255, 0, 90, 150) if x % 100 == 0 else (0, 120, 255, 110), width=1)
            d.text((x + 2, zona[1] + 2), str(x), font=f, fill=(200, 0, 60, 255))
        for y in range((zona[1] // 50) * 50, zona[3], 50):
            d.line([(zona[0], y), (zona[2], y)], fill=(255, 0, 90, 150) if y % 100 == 0 else (0, 120, 255, 110), width=1)
            d.text((zona[0] + 2, y + 2), str(y), font=f, fill=(200, 0, 60, 255))
        salida = os.path.join(carpeta(tema), "cuadricula-%s.jpg" % clave)
        v.crop(zona).convert("RGB").save(salida, quality=90)
        print(clave, "figura", (x0, y0, x1, y1), "->", salida)


def hoja(tema, resultados):
    f = ImageFont.truetype(TTF, 22)
    paneles = []
    for clave, im, ov, caja, frac, pasos in resultados:
        x0, y0, x1, y1 = caja
        v = Image.new("RGBA", im.size, (226, 233, 241, 255))
        v.alpha_composite(im)
        d = ImageDraw.Draw(v)
        d.ellipse((ov["cx"] - ov["rx"], ov["cy"] - ov["ry"], ov["cx"] + ov["rx"], ov["cy"] + ov["ry"]), outline=(255, 0, 90, 255), width=4)
        entera = v.crop((x0 - 10, y0 - 10, x1 + 10, y1 + 10)).convert("RGB")
        entera.thumbnail((250, 560))
        cabeza = v.crop((int(ov["cx"] - ov["rx"] * 2.0), int(ov["cy"] - ov["ry"] * 1.8),
                         int(ov["cx"] + ov["rx"] * 2.0), int(ov["cy"] + ov["ry"] * 1.8))).convert("RGB")
        cabeza.thumbnail((250, 260))
        p = Image.new("RGB", (270, 900), (250, 250, 250))
        p.paste(entera, ((270 - entera.width) // 2, 8))
        p.paste(cabeza, ((270 - cabeza.width) // 2, 590))
        ImageDraw.Draw(p).text((135, 880), "%s  %d%%%s" % (clave, round(frac * 100), " (-%d)" % pasos if pasos else ""),
                               font=f, fill=(30, 30, 40), anchor="mm")
        paneles.append(p)
    salida = Image.new("RGB", (270 * len(paneles) + 10 * (len(paneles) + 1), 920), "white")
    for i, p in enumerate(paneles):
        salida.paste(p, (10 + i * 280, 10))
    ruta = os.path.join(carpeta(tema), "huecos-%s.jpg" % tema)
    salida.save(ruta, quality=90)
    print("->", ruta)


def instalar(tema, resultados):
    destino = os.path.join(CB, "public", "themes", tema, "asomate")
    os.makedirs(destino, exist_ok=True)
    nombres = {c: (n, e) for c, n, e in personajes_de(tema)}
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
        salida = os.path.join(destino, clave + ".png")
        fig.save(salida, optimize=True)
        personajes[clave] = {
            "w": fig.width, "h": fig.height, "arriba": 0, "pies": fig.height - 1, "izq": 0, "der": fig.width - 1,
            "cx": round((ov["cx"] - x0) * k, 1), "cy": round((ov["cy"] - y0) * k, 1),
            "rx": round(ov["rx"] * k, 1), "ry": round(ov["ry"] * k, 1),
        }
        print("%s (%s): %dx%d, %d KB" % (clave, nombres[clave][0], fig.width, fig.height, os.path.getsize(salida) // 1024))
    t = TEXTOS[tema]
    bloque = {"fondo": t["fondo"], "personajes": personajes, "boton": t["boton"], "titulo": t["titulo"]}
    if t.get("suelo"):
        bloque["suelo"] = t["suelo"]
    raw = open(THEMES, "rb").read()
    crlf = b"\r\n" in raw
    texto_json = raw.decode("utf-8").replace("\r\n", "\n")
    antes = json.loads(texto_json)
    nuevo = json.loads(texto_json)
    nuevo["themes"][tema]["asomate"] = bloque
    # Solo cambia la clave `asomate` de esta temática: el resto del archivo tiene que quedar igual.
    for k, v in antes["themes"].items():
        if k != tema:
            assert nuevo["themes"][k] == v, "cambió otra temática: " + k
    salida = json.dumps(nuevo, ensure_ascii=False, indent=2) + "\n"
    if crlf:
        salida = salida.replace("\n", "\r\n")
    open(THEMES, "wb").write(salida.encode("utf-8"))
    print("themes.json: bloque asomate de %s con %d personajes" % (tema, len(personajes)))


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8")
    tema = sys.argv[1]
    modo = sys.argv[2] if len(sys.argv) > 2 else "ver"
    if modo == "cuadricula":
        cuadricula(tema)
        raise SystemExit(0)
    ruta_aj = os.path.join(AQUI, "ajustes-%s.json" % tema)
    ajustes = json.load(io.open(ruta_aj, encoding="utf-8")) if os.path.isfile(ruta_aj) else {}
    resultados = []
    for clave, _n, _e in personajes_de(tema):
        if not ruta_origen(tema, clave):
            print("falta", clave)
            continue
        im, ov, caja, frac, pasos = medir(tema, clave, ajustes)
        print("%s: óvalo cx=%.0f cy=%.0f rx=%.0f ry=%.0f, borde dentro %.0f %%" % (clave, ov["cx"], ov["cy"], ov["rx"], ov["ry"], frac * 100))
        resultados.append((clave, im, ov, caja, frac, pasos))
    hoja(tema, resultados)
    if modo == "hacer":
        instalar(tema, resultados)
