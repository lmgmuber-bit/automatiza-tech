# -*- coding: utf-8 -*-
"""Anna de gala en los juegos 3D, repintando su textura. Cero creditos.

Por que repintar y no generar un modelo nuevo: `hermana.glb` viene con esqueleto y con la
animacion de baile que usan los juegos. Un modelo nuevo llega sin rig —y ademas la moderacion
de Higgsfield rechazo tres veces a este personaje—, asi que habria que rehacer el enganche a
mano y volver a exportar los tres juegos. La malla, el esqueleto y la animacion no se tocan:
lo unico que cambia son los colores de la unica textura que tiene el modelo.

COMO SE SABE QUE ES CADA COSA. La textura es un atlas: las islas de UV estan repartidas sin
orden y "el azul" aparece tanto en la falda como en el lazo del pelo. Por eso, ademas del
color, se usa un mapa de alturas calculado desde la malla (`mapa.py`): para cada punto de la
textura se sabe a que altura del cuerpo corresponde. Con eso, "la falda" es una region
concreta y no una conjetura.

EL VESTIDO. Se copia el de la invitacion, que es la imagen que Luis ya aprobo: falda marfil
con motivos celestes, corpino azul rey con filigrana clara, guantes plateados.

Se conserva el sombreado: no se pinta un color plano encima, se cambia tono y saturacion y
se reajusta el brillo respetando sus variaciones. Un color plano borra los pliegues y la
figura queda de carton.
"""
import colorsys
import json
import os
import struct

import numpy as np
from PIL import Image

# Todo queda en `salida/`, al lado de este archivo: la textura que saca `extraer.py`, el mapa de
# alturas de `mapa.py` y el modelo nuevo. El original vive en el repositorio del juego 3D; otra
# copia se indica con HERMANA_GLB.
AQUI = os.path.join(os.path.dirname(os.path.abspath(__file__)), "salida").replace("\\", "/")
ORIGEN = os.environ.get("HERMANA_GLB", r"C:/wamp64/www/tucumple-repo/app/public/models/hermana.glb")
os.makedirs(AQUI, exist_ok=True)


# ── Herramientas de color ───────────────────────────────────────────────────
def a_hsv(rgb):
    return np.stack(np.vectorize(colorsys.rgb_to_hsv)(rgb[..., 0], rgb[..., 1], rgb[..., 2]), -1)


def a_rgb(hsv):
    return np.stack(np.vectorize(colorsys.hsv_to_rgb)(hsv[..., 0], hsv[..., 1], hsv[..., 2]), -1)


def pintar(hsv, mascara, tono, sat, v0, v1):
    """Reemplaza tono y saturacion, y estira el brillo al rango pedido conservando el relieve.

    `v0`/`v1` son el brillo de la sombra y el de la luz. El brillo original se normaliza
    dentro de lo que ocupa ESA region —no en 0..1— porque una zona casi negra, estirada con
    la escala global, sale plana.
    """
    if not mascara.any():
        return
    v = hsv[..., 2][mascara]
    lo, hi = np.percentile(v, 3), np.percentile(v, 97)
    if hi - lo < 1e-4:
        norm = np.full_like(v, 0.5)
    else:
        norm = np.clip((v - lo) / (hi - lo), 0, 1)
    hsv[..., 0][mascara] = tono
    hsv[..., 1][mascara] = sat
    hsv[..., 2][mascara] = v0 + norm * (v1 - v0)


# ── Entrada ─────────────────────────────────────────────────────────────────
tex = np.asarray(Image.open(AQUI + "/hermana-textura.jpg").convert("RGB"), dtype=np.float64) / 255.0
alturas = np.asarray(Image.open(AQUI + "/hermana-alturas.png"))  # 0 = ningun triangulo cae aca
hsv = a_hsv(tex)
H, S, V = hsv[..., 0], hsv[..., 1], hsv[..., 2]

# La altura viene como 1..254 sobre 0 = vacio; se pasa a 0..1 del alto del cuerpo.
h = np.where(alturas > 0, (alturas.astype(np.float64) - 1) / 253.0, -1.0)

# Bandas medidas sobre el modelo dibujado (1,60 de alto): botas hasta 0,22; falda hasta
# 0,75; corpiño y mangas hasta 1,15; de ahi arriba, cabeza.
botas = (h >= 0) & (h < 0.14)
falda = (h >= 0.14) & (h < 0.47)
torso = (h >= 0.47) & (h < 0.72)
cabeza = h >= 0.72

# ── Familias de color ───────────────────────────────────────────────────────
azul = (H > 0.53) & (H < 0.70) & (S > 0.30) & (V > 0.12)
agua = (H > 0.40) & (H < 0.545) & (S > 0.12)
# Menta y bordado son los dos verdeagua del personaje y se separan por el COLOR, no por la
# altura: las mangas llegan hasta la muñeca, que cae justo en el borde entre "torso" y
# "falda", y cortarlas por altura dejaba el puño celeste y el resto plateado.
menta = agua & (S < 0.32) & (V > 0.52)
bordado = agua & ~menta
oscuro = V < 0.26
vino = (((H > 0.86) | (H < 0.04)) & (S > 0.28) & (V < 0.62))
# El ribete dorado empieza en H 0,10: por debajo esta el PELO, que es el mismo naranja pero
# mucho mas saturado (S 0,86 contra 0,50) y ocupa seis veces mas textura. Bajar este limite
# a 0,07 le dejo las trenzas blancas a Anna.
oro = (H > 0.095) & (H < 0.19) & (S > 0.22) & (V > 0.15)
rosa = (H > 0.88) & (S > 0.35) & (V >= 0.45)

# ── El vestido ──────────────────────────────────────────────────────────────
# Mangas: un solo plateado parejo de hombro a puño, en todo el cuerpo.
pintar(hsv, menta, 0.60, 0.06, 0.70, 0.97)

# Falda: de azul rey a marfil. Es el cambio que hace la silueta.
pintar(hsv, falda & azul, 0.60, 0.05, 0.62, 1.00)
# Los motivos de la falda pasan a celeste, como el bordado del vestido de la invitacion.
pintar(hsv, falda & (bordado | rosa), 0.585, 0.32, 0.62, 0.88)
# Lo oscuro que queda dentro de la falda son costuras y pliegues del forro: se aclara para
# que no queden grietas negras cruzando el vestido.
pintar(hsv, falda & oscuro, 0.60, 0.09, 0.62, 0.86)
pintar(hsv, falda & vino, 0.585, 0.28, 0.55, 0.80)
pintar(hsv, falda & oro, 0.60, 0.06, 0.70, 0.93)

# Corpiño: el negro pasa a azul rey —mas claro que un azul marino, que a esta escala se lee
# como negro— y su bordado a plata clara.
pintar(hsv, torso & oscuro, 0.615, 0.70, 0.26, 0.68)
pintar(hsv, torso & bordado, 0.60, 0.06, 0.82, 1.00)
pintar(hsv, torso & oro, 0.60, 0.05, 0.72, 0.96)
pintar(hsv, torso & rosa, 0.60, 0.08, 0.78, 0.96)
pintar(hsv, torso & azul, 0.60, 0.07, 0.60, 0.94)

# Botas: plata con suela celeste, para que no queden dos manchas negras bajo el vestido.
pintar(hsv, botas & (oscuro | azul), 0.60, 0.08, 0.46, 0.84)
pintar(hsv, botas & vino, 0.585, 0.25, 0.52, 0.76)
pintar(hsv, botas & oro, 0.60, 0.05, 0.70, 0.94)

# La cabeza NO se toca: pelo, piel, ojos y la cinta blanca ya son los de la invitacion.

nueva = np.clip(a_rgb(hsv), 0, 1)
img = Image.fromarray((nueva * 255).round().astype(np.uint8), "RGB")
# Calidad 88 con submuestreo normal: la textura original era un JPEG de 430 KB y volver a
# comprimir sin submuestreo la dejaba en 1,4 MB —el modelo pasaba de 2,3 a 3,7 MB y eso se
# baja en la tablet de la fiesta, con el wifi de una casa—. A 88 quedan 735 KB y la
# diferencia no se ve: son superficies planas, no fotografia.
img.save(AQUI + "/hermana-textura-gala.jpg", quality=88, subsampling=2)
print("textura repintada")


# ── Volver a empacar el GLB ─────────────────────────────────────────────────
def leer_glb(ruta):
    with open(ruta, "rb") as f:
        _, _, largo = struct.unpack("<III", f.read(12))
        trozos = []
        while f.tell() < largo:
            clen, ctipo = struct.unpack("<II", f.read(8))
            trozos.append((ctipo, f.read(clen)))
    j = next(d for t, d in trozos if t == 0x4E4F534A)
    b = next(d for t, d in trozos if t == 0x004E4942)
    return json.loads(j.decode("utf-8")), bytearray(b)


g, binario = leer_glb(ORIGEN)

# La imagen nueva va EN EL SITIO de la vieja, que no esta al final del buffer: detras de ella
# hay 75 bufferViews de animacion. Se reemplaza el bloque y se corren esos desplazamientos
# por la diferencia de tamano. Agregarla al final habria sido mas simple pero deja los 430 KB
# de la textura vieja dentro del archivo, muertos, y esto se baja en la fiesta.
#
# La diferencia se mantiene multiplo de 4 porque los accessors leen flotantes y un offset
# desalineado los rompe. Se comprueba despues, y ademas se abre el modelo resultante.
nueva_jpg = open(AQUI + "/hermana-textura-gala.jpg", "rb").read()
bv_img = g["images"][0]["bufferView"]
inicio = g["bufferViews"][bv_img].get("byteOffset", 0)

# Donde termina el bloque viejo: el comienzo del siguiente bufferView, para no perder el
# relleno de alineacion que hay entre medio.
siguientes = [i for i, bv in enumerate(g["bufferViews"]) if bv.get("byteOffset", 0) > inicio]
fin_viejo = min((g["bufferViews"][i].get("byteOffset", 0) for i in siguientes), default=len(binario))

bloque = bytearray(nueva_jpg)
while len(bloque) % 4:
    bloque.append(0)
delta = len(bloque) - (fin_viejo - inicio)
assert delta % 4 == 0, "el corrimiento tiene que ser multiplo de 4"

binario = bytearray(binario[:inicio]) + bloque + bytearray(binario[fin_viejo:])
for i in siguientes:
    g["bufferViews"][i]["byteOffset"] = g["bufferViews"][i].get("byteOffset", 0) + delta
g["bufferViews"][bv_img]["byteLength"] = len(nueva_jpg)
g["buffers"][0]["byteLength"] = len(binario)

# Nadie se sale del buffer ni queda desalineado.
for i, bv in enumerate(g["bufferViews"]):
    o = bv.get("byteOffset", 0)
    assert o + bv["byteLength"] <= len(binario), "bufferView %d se sale del buffer" % i
for a in g["accessors"]:
    bv = g["bufferViews"][a["bufferView"]]
    o = bv.get("byteOffset", 0) + a.get("byteOffset", 0)
    tam = {5120: 1, 5121: 1, 5122: 2, 5123: 2, 5125: 4, 5126: 4}[a["componentType"]]
    assert o % tam == 0, "accessor desalineado"

json_bytes = json.dumps(g, separators=(",", ":")).encode("utf-8")
while len(json_bytes) % 4:
    json_bytes += b" "

total = 12 + 8 + len(json_bytes) + 8 + len(binario)
with open(AQUI + "/hermana-gala.glb", "wb") as f:
    f.write(struct.pack("<III", 0x46546C67, 2, total))
    f.write(struct.pack("<II", len(json_bytes), 0x4E4F534A))
    f.write(json_bytes)
    f.write(struct.pack("<II", len(binario), 0x004E4942))
    f.write(binario)

print("hermana-gala.glb", total, "bytes")
