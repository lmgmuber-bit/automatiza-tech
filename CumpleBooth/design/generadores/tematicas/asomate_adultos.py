# -*- coding: utf-8 -*-
"""Asómate de la temática adulta «Coquetería Pop» (AT-CUMPLECLICK-026): recorte y hueco de la cara.

Las 14 figuras son ilustraciones genéricas (Recraft v4.1 por la API de Higgsfield, 11-oct-2026, USD 0,56 de lista,
aprobado por Luis): ninguna es una persona real. Este script, sin IA:

  1. quita el fondo blanco (solo el blanco CONECTADO al borde: el blanco de una camisa, encerrado por el contorno
     negro, se conserva);
  2. recorta al contorno de la figura;
  3. busca la cara: la mancha de piel (crema) más grande en el tercio de arriba, y abre ahí un óvalo transparente;
  4. escribe asomate/<clave>.png y anota la geometría (w, h, arriba, pies, izq, der, cx, cy, rx, ry) en themes.json.

Uso:
  python asomate_adultos.py <carpeta con las .jpg> ver     → hoja de contacto con los óvalos propuestos
  python asomate_adultos.py <carpeta con las .jpg> hacer   → PNG + themes.json
Ajustes a ojo por figura en AJUSTES (dx, dy en fracción del ancho/alto del óvalo; kx, ky escala del óvalo).
"""
import json
import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw
from scipy import ndimage

CB = Path(__file__).resolve().parents[3]
TEMA = CB / "public/themes/adulto-coqueteria-pop"

# clave → (nombre visible, emoji). El orden es el de la pantalla de elección: de a pareja.
FIGURAS = {
    "a1-vestido-rojo": ("Dama de rojo", "💃"),
    "a2-terno": ("Galán de terno", "🤵"),
    "b1-pinup": ("Pin-up", "🎀"),
    "b2-galan": ("Galán de telenovela", "🌹"),
    "c1-diva": ("Diva de cabaret", "🪶"),
    "c2-ganster": ("Gánster elegante", "🎩"),
    "d1-salsera": ("Salsera", "💃"),
    "d2-salsero": ("Salsero", "🕺"),
    "e1-reina": ("Reina de la fiesta", "👑"),
    "e2-rey": ("Rey de la fiesta", "🤴"),
    "f1-playa": ("Reina del verano", "👙"),
    "f2-forzudo": ("El forzudo", "💪"),
    "g1-superheroina": ("Súper Coqueta", "🦸‍♀️"),
    "g2-superheroe": ("Capitán Galán", "🦸‍♂️"),
}
# Óvalo de la cara puesto a ojo sobre la hoja de revisión (fracciones del recorte: cx, cy, rx, ry).
# La detección automática de piel falla con piel rosada y camisas claras; estas medidas mandan.
OVALOS = {
    "a1-vestido-rojo": (0.54, 0.30, 0.18, 0.105),
    "a2-terno": (0.44, 0.28, 0.195, 0.12),
    "b1-pinup": (0.51, 0.285, 0.285, 0.147),
    "b2-galan": (0.50, 0.258, 0.34, 0.142),
    "c1-diva": (0.445, 0.247, 0.153, 0.074),
    "c2-ganster": (0.51, 0.234, 0.24, 0.099),
    "d1-salsera": (0.51, 0.226, 0.153, 0.104),
    "d2-salsero": (0.516, 0.139, 0.107, 0.067),
    "e1-reina": (0.477, 0.227, 0.16, 0.089),
    "e2-rey": (0.502, 0.22, 0.117, 0.088),
    "f1-playa": (0.463, 0.241, 0.15, 0.08),
    "f2-forzudo": (0.48, 0.147, 0.117, 0.082),
    "g1-superheroina": (0.50, 0.214, 0.214, 0.092),
    "g2-superheroe": (0.484, 0.22, 0.206, 0.112),
}
AJUSTES = {}


def recortar_fondo(rgb):
    a = np.asarray(rgb.convert("RGB")).astype(np.int16)
    blanco = (a.min(axis=2) > 225) & ((a.max(axis=2) - a.min(axis=2)) < 22)
    etiquetas, _ = ndimage.label(blanco)
    borde = set(np.unique(np.concatenate([etiquetas[0], etiquetas[-1], etiquetas[:, 0], etiquetas[:, -1]]))) - {0}
    fondo = np.isin(etiquetas, list(borde))
    alfa = np.where(fondo, 0, 255).astype(np.uint8)
    # Suaviza el filo: un píxel de transición para que el recorte no se vea dentado.
    alfa = ndimage.uniform_filter(alfa.astype(np.float32), size=3).astype(np.uint8)
    img = np.dstack([a.astype(np.uint8), alfa])
    ys, xs = np.where(alfa > 20)
    return Image.fromarray(img, "RGBA").crop((xs.min(), ys.min(), xs.max() + 1, ys.max() + 1))


def buscar_cara(rgba):
    a = np.asarray(rgba).astype(np.int16)
    r, g, b, al = a[..., 0], a[..., 1], a[..., 2], a[..., 3]
    # Piel de las ilustraciones: crema o durazno (claro, cálido, poco saturado), dentro de la figura.
    piel = (al > 200) & (r > 200) & (g > 170) & (b > 140) & (r >= g) & (g >= b - 10) & ((r - b) < 90)
    alto = a.shape[0]
    piel[int(alto * 0.42):] = False
    piel = ndimage.binary_opening(piel, iterations=2)
    etiquetas, n = ndimage.label(piel)
    if not n:
        return None
    tamanos = ndimage.sum(piel, etiquetas, range(1, n + 1))
    k = int(np.argmax(tamanos)) + 1
    ys, xs = np.where(etiquetas == k)
    return float(xs.mean()), float((ys.min() + ys.max()) / 2), (xs.max() - xs.min()) / 2, (ys.max() - ys.min()) / 2


def ovalo(clave, rgba):
    if clave in OVALOS:
        fx, fy, frx, fry = OVALOS[clave]
        w, h = rgba.size
        return fx * w, fy * h, frx * w, fry * h
    cara = buscar_cara(rgba)
    if cara is None:
        raise SystemExit(f"{clave}: no encontré la cara; ajústala a mano")
    cx, cy, rx, ry = cara
    aj = AJUSTES.get(clave, {})
    rx, ry = rx * 0.9 * aj.get("kx", 1), ry * 0.92 * aj.get("ky", 1)
    return cx + aj.get("dx", 0) * rx, cy + aj.get("dy", 0) * ry, rx, ry


def main():
    carpeta = Path(sys.argv[1])
    modo = sys.argv[2] if len(sys.argv) > 2 else "ver"
    hojas, geo = [], {}
    for clave in FIGURAS:
        rgba = recortar_fondo(Image.open(carpeta / f"{clave}.jpg"))
        cx, cy, rx, ry = ovalo(clave, rgba)
        if modo == "ver":
            vista = rgba.copy()
            dib = ImageDraw.Draw(vista)
            w, h = vista.size
            for i in range(1, 10):  # cuadrícula al 10 % para ubicar el óvalo a ojo
                dib.line([(w * i / 10, 0), (w * i / 10, h)], fill=(0, 255, 0, 160), width=1)
                dib.line([(0, h * i / 20), (w, h * i / 20)], fill=(255, 255, 0, 120) if i % 2 else (0, 255, 0, 160), width=1)
            dib.ellipse([cx - rx, cy - ry, cx + rx, cy + ry], outline=(0, 200, 255, 255), width=5)
            fondo = Image.new("RGBA", vista.size, (90, 90, 90, 255))
            fondo.alpha_composite(vista)
            hojas.append(fondo.convert("RGB").resize((300, round(300 * h / w))))
            continue
        hueco = Image.new("L", rgba.size, 0)
        ImageDraw.Draw(hueco).ellipse([cx - rx, cy - ry, cx + rx, cy + ry], fill=255)
        al = np.asarray(rgba)[..., 3].copy()
        al[np.asarray(hueco) > 0] = 0
        salida = np.dstack([np.asarray(rgba)[..., :3], al])
        destino = TEMA / "asomate" / f"{clave}.png"
        destino.parent.mkdir(parents=True, exist_ok=True)
        Image.fromarray(salida, "RGBA").quantize(220, method=Image.Quantize.FASTOCTREE).save(destino, optimize=True)
        w, h = rgba.size
        nombre, emoji = FIGURAS[clave]
        geo[clave] = {"nombre": nombre, "emoji": emoji, "w": w, "h": h, "arriba": 0, "pies": h - 1, "izq": 0,
                      "der": w - 1, "cx": round(cx, 1), "cy": round(cy, 1), "rx": round(rx, 1), "ry": round(ry, 1)}
    if modo == "ver":
        alto = max(i.height for i in hojas)
        for n in range(2):
            hoja = Image.new("RGB", (7 * 305, alto), "white")
            for i, im in enumerate(hojas[n * 7:(n + 1) * 7]):
                hoja.paste(im, (i * 305, 0))
            hoja.save(carpeta / f"huecos-{n + 1}.jpg", quality=85)
        print("hojas:", carpeta / "huecos-1.jpg", carpeta / "huecos-2.jpg")
        return
    # El fondo de Asómate es la bienvenida de la temática: «¡SE BUSCA!» arriba y el centro libre.
    Image.open(TEMA / "fondo-banner.jpg").save(TEMA / "asomate" / "fondo.jpg", quality=90)
    ruta = CB / "public/data/themes.json"
    raw = ruta.read_bytes().decode("utf-8")
    datos = json.loads(raw)
    tema = datos["themes"]["adulto-coqueteria-pop"]
    tema["asomate"] = {"fondo": "asomate/fondo.jpg", "boton": "🎭 Asómate y sé la estrella",
                       "titulo": "¿En quién te quieres convertir?", "personajes": geo}
    texto = json.dumps(datos, ensure_ascii=False, indent=2).replace("\n", "\r\n") + "\r\n"
    ruta.write_bytes(texto.encode("utf-8"))
    print("listo:", len(geo), "figuras")


if __name__ == "__main__":
    main()
