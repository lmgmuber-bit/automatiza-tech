# -*- coding: utf-8 -*-
"""Comprueba las URLs que estan impresas en los carteles de las fiestas del domingo.

Se corre ANTES de tocar nada para tener la linea base, y DESPUES de cada cambio. Si una
sola cambia de estado, el cambio se revierte: los carteles ya estan impresos y un QR que no
responde no se puede arreglar el dia de la fiesta.

Uso:  python urls-criticas.py antes|despues
"""
import json
import pathlib
import sys
import urllib.error
import urllib.request

BASE = 'https://cumpleclick.com/app/'
FIESTAS = ['samantha-hielo', 'luciano-spidey']

URLS = []
for slug in FIESTAS:
    URLS += [
        ('juego 3D', f'{BASE}juego/?p={slug}'),
        ('galeria', f'{BASE}galeria.php?p={slug}'),
        ('album', f'{BASE}album.html?p={slug}'),
        ('kiosco', f'{BASE}?p={slug}'),
    ]
URLS += [
    ('juego sin fiesta', f'{BASE}juego/'),
    ('sitio publico', 'https://cumpleclick.com/'),
]


def revisar(url):
    req = urllib.request.Request(url, headers={'User-Agent': 'cumpleclick-check'})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            cuerpo = r.read()
            return {'codigo': r.status, 'tipo': r.headers.get('Content-Type', ''),
                    'bytes': len(cuerpo)}
    except urllib.error.HTTPError as e:
        return {'codigo': e.code, 'tipo': '', 'bytes': 0}
    except Exception as e:
        return {'codigo': 0, 'tipo': 'ERROR: ' + str(e)[:60], 'bytes': 0}


momento = sys.argv[1] if len(sys.argv) > 1 else 'antes'
archivo = pathlib.Path(f'urls-{momento}.json')
resultado = {}

print(f'--- {momento.upper()} ---')
for nombre, url in URLS:
    r = revisar(url)
    resultado[url] = r
    corto = url.replace(BASE, '').replace('https://cumpleclick.com/', '/') or '/'
    print(f'  {r["codigo"]}  {nombre:18} {corto:34} {r["bytes"]:>7} bytes')

archivo.write_text(json.dumps(resultado, indent=2), encoding='utf-8')

# Si ya existe el "antes", comparar.
previo = pathlib.Path('urls-antes.json')
if momento == 'despues' and previo.is_file():
    base = json.loads(previo.read_text(encoding='utf-8'))
    print('\n--- COMPARACION CON LA LINEA BASE ---')
    roto = 0
    for url, ahora in resultado.items():
        antes = base.get(url)
        if antes is None:
            continue
        if antes['codigo'] != ahora['codigo']:
            roto += 1
            print(f'  ROTO  {url}\n        antes {antes["codigo"]} -> ahora {ahora["codigo"]}')
    print('  ninguna URL cambio de estado' if roto == 0
          else f'  >>> {roto} URLS ROMPIDAS, hay que revertir')
    sys.exit(1 if roto else 0)
