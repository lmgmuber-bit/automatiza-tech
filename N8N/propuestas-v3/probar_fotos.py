"""Prueba local del filtro de fotos (fotos_guard.JS_LIMPIAR_FOTOS) con casos de varios rubros.

Uso: python N8N/propuestas-v3/probar_fotos.py
No llama a Higgsfield ni a n8n: solo ejecuta el JavaScript del filtro con node y compara.
"""
import json, subprocess, sys
from fotos_guard import JS_LIMPIAR_FOTOS

# (slide, prompt, ¿debe reemplazarse?)
CASOS = [
    # Lo que sí salió limpio el 2026-09-24 (se conserva)
    ('cover', 'close-up of gentle hands placing a white lily on a polished dark wooden casket, background completely blurred into warm golden bokeh', False),
    ('challenge', 'close-up of hands holding a smartphone at night, phone screen facing away from camera, face not visible', False),
    ('benefits', 'children in baseball uniforms high-fiving their coach next to the dugout, joyful genuine moment', False),
    ('next_steps', 'youth baseball team joining hands in a huddle on the field, warm afternoon light', False),
    ('solution', 'bartender pouring whisky into a glass with ice, warm bar light, shallow depth of field', False),
    ('pricing', 'row of unlabeled amber bottles on a wooden shelf, soft warm light', False),
    ('how_it_works', 'man arranging flowers on a wooden table by a window', False),
    # Lo que trajo texto inventado (se reemplaza)
    ('cover', 'youth baseball players playing a game in a stadium at golden hour', True),
    ('cover', 'elegant storefront of the funeral home at dusk', True),
    ('benefits', 'coach pointing at a scoreboard during a game', True),
    ('solution', 'clean desk with a laptop showing a website', True),
    ('how_it_works', 'team meeting in an office around a whiteboard', True),
    ('pricing', 'bottle of premium whisky with its label on a bar counter', True),
    ('challenge', 'customer looking at a smartphone in a store', True),
    ('extra_1', 'shelf of books in a library', True),
    ('how_it_works', 'clean wooden desk with a closed laptop and a cup of coffee', False),
    ('solution', 'monitor displaying a dashboard in an office', True),
    # Botillería de prueba (fila 49, 2026-09-24): lo que escribió GPT-4o
    ('cover', 'close-up of a wine bottle being held by a hand in a cozy store setting, blurred background', True),
    ('cover', 'close-up of a hand holding a plain unlabeled wine bottle, blurred background', False),
    ('extra_1', 'two-phase project plan on a desk, people discussing', True),
    ('solution', 'person using a smartphone to browse a digital catalog in a relaxed environment', True),
    ('pricing', 'business meeting with documents and a laptop on the table', True),
    ('challenge', 'busy store with customers browsing the shelves', True),
    ('benefits', 'happy customer receiving a delivery at home', False),
    ('how_it_works', 'close-up of hands wrapping a gift box in kraft paper', True),
    ('how_it_works', 'close-up of hands wrapping a plain gift box in kraft paper', False),
    ('next_steps', '', True),
]

briefs = [{'slide': s, 'prompt': p} for s, p, _ in CASOS]
js = (JS_LIMPIAR_FOTOS + '\nconst BRIEFS = ' + json.dumps(briefs) + ';\n'
      'process.stdout.write(JSON.stringify(BRIEFS.map((b) => ({ slide: b.slide, motivo: motivoFoto(b.slide, b.prompt), '
      'salida: limpiarFotos([b]).limpias[0].prompt }))));')
r = subprocess.run(['node', '-e', js], capture_output=True, text=True, encoding='utf-8')
if r.returncode != 0:
    print(r.stderr[-800:]); sys.exit(1)
fallas = 0
for (slide, prompt, debe), res in zip(CASOS, json.loads(r.stdout)):
    reemplazada = bool(res['motivo'])
    ok = reemplazada == debe
    fallas += not ok
    print(f"{'OK ' if ok else 'MAL'} {slide:12} {'reemplazada (' + res['motivo'] + ')' if reemplazada else 'se conserva':46} {prompt[:60]!r}")
    if not res['salida'].endswith('no signs, no labels, no text, no lettering, no logos, no watermarks'):
        print('    MAL: falta el cierre'); fallas += 1
    if slide == 'cover' and 'close-up' not in res['salida'] and 'blurred' not in res['salida']:
        print('    MAL: portada sin primer plano'); fallas += 1
print('TODO OK' if not fallas else f'{fallas} falla(s)')
sys.exit(1 if fallas else 0)
