# -*- coding: utf-8 -*-
"""Que parte del cuerpo pinta cada trozo de la textura de Anna.

La textura es un atlas: islas de UV repartidas sin orden visible, asi que recolorear "el azul"
a ojo pintaria tambien el lazo del pelo o el forro de la capa. Esto lo resuelve mirando la
malla: para cada triangulo se sabe su altura en el modelo (Y) y su lugar en la textura (UV),
asi que se puede pintar en el espacio de la textura un mapa de alturas. Con ese mapa, "la
falda" deja de ser un color y pasa a ser una region concreta del atlas.

No modifica nada: solo lee el GLB y deja dos imagenes para mirar.
"""
import json
import os
import struct
import sys

import numpy as np
from PIL import Image, ImageDraw

# El modelo vive en OTRO repositorio, el del juego 3D; otra copia se indica con HERMANA_GLB.
# Lo que se lee y se escribe queda en `salida/`, al lado de este archivo. Antes era una carpeta
# temporal de sesion.
RUTA = os.environ.get("HERMANA_GLB", r"C:/wamp64/www/tucumple-repo/app/public/models/hermana.glb")
SAL = os.path.join(os.path.dirname(os.path.abspath(__file__)), "salida").replace("\\", "/")
os.makedirs(SAL, exist_ok=True)

TIPOS = {5120: ('b', 1), 5121: ('B', 1), 5122: ('h', 2), 5123: ('H', 2), 5125: ('I', 4), 5126: ('f', 4)}
CUENTA = {'SCALAR': 1, 'VEC2': 2, 'VEC3': 3, 'VEC4': 4, 'MAT4': 16}


def leer_glb(ruta):
    with open(ruta, 'rb') as f:
        _, _, largo = struct.unpack('<III', f.read(12))
        trozos = []
        while f.tell() < largo:
            clen, ctipo = struct.unpack('<II', f.read(8))
            trozos.append((ctipo, f.read(clen)))
    j = next(d for t, d in trozos if t == 0x4E4F534A)
    b = next((d for t, d in trozos if t == 0x004E4942), b'')
    return json.loads(j.decode('utf-8')), b


def acceso(g, b, i):
    """Lee un accessor completo como array de numpy (N, componentes)."""
    a = g['accessors'][i]
    bv = g['bufferViews'][a['bufferView']]
    fmt, tam = TIPOS[a['componentType']]
    n = CUENTA[a['type']]
    base = bv.get('byteOffset', 0) + a.get('byteOffset', 0)
    paso = bv.get('byteStride') or (tam * n)
    datos = np.empty((a['count'], n), dtype=np.float64)
    for k in range(a['count']):
        o = base + k * paso
        datos[k] = struct.unpack_from('<' + fmt * n, b, o)
    return datos


g, b = leer_glb(RUTA)
prim = g['meshes'][0]['primitives'][0]
pos = acceso(g, b, prim['attributes']['POSITION'])
uv = acceso(g, b, prim['attributes']['TEXCOORD_0'])
idx = acceso(g, b, prim['indices']).astype(np.int64).ravel()
print('vertices', len(pos), 'triangulos', len(idx) // 3)
print('caja del modelo  X %.3f..%.3f  Y %.3f..%.3f  Z %.3f..%.3f'
      % (pos[:, 0].min(), pos[:, 0].max(), pos[:, 1].min(), pos[:, 1].max(),
         pos[:, 2].min(), pos[:, 2].max()))

LADO = 2048
ymin, ymax = pos[:, 1].min(), pos[:, 1].max()
alto = Image.new('L', (LADO, LADO), 0)
d = ImageDraw.Draw(alto)

for t in range(0, len(idx), 3):
    tri = idx[t:t + 3]
    # Altura media del triangulo, 1 = cabeza, 0 = pies. Se pinta +1 para distinguir el
    # fondo (0 = ninguna isla cae aqui) de lo que si esta pintado.
    h = (pos[tri, 1].mean() - ymin) / (ymax - ymin)
    v = int(round(h * 253)) + 1
    pts = [(float(uv[i, 0]) * LADO, float(uv[i, 1]) * LADO) for i in tri]
    d.polygon(pts, fill=v)

alto.save(SAL + '/hermana-alturas.png')

# Vista lado a lado: la textura y el mapa de alturas en falso color
tex = Image.open(SAL + '/hermana-textura.jpg').convert('RGB')
paleta = np.zeros((256, 3), dtype=np.uint8)
for i in range(1, 256):
    f = (i - 1) / 253.0
    f = min(1.0, f)
    g_ = 0.3 + (1 - abs(0.5 - f) * 2) * 0.7
    ch = lambda v: max(0, min(255, int(255 * v)))
    paleta[i] = (ch(1 - f), ch(g_), ch(f))
color = Image.fromarray(paleta[np.array(alto)], 'RGB')

lienzo = Image.new('RGB', (LADO, LADO // 2 * 1), 'black')
par = Image.new('RGB', (LADO, LADO // 2))
par.paste(tex.resize((LADO // 2, LADO // 2), Image.LANCZOS), (0, 0))
par.paste(color.resize((LADO // 2, LADO // 2), Image.NEAREST), (LADO // 2, 0))
par.resize((1400, 700), Image.LANCZOS).save(SAL + '/hermana-textura-vs-alturas.png')
print('listo')
