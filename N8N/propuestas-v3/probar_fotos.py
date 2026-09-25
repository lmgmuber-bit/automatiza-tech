"""Prueba local del filtro de fotos (fotos_guard.JS_LIMPIAR_FOTOS) con casos de varios rubros.

Uso: python N8N/propuestas-v3/probar_fotos.py   (sale con código 1 si algo falla)
No llama a Higgsfield ni a n8n: solo ejecuta el JavaScript del filtro con node y compara.
Resultados posibles por caso: 'conserva' (pasa tal cual), 'neutraliza' (se le agrega sin etiqueta / pantalla
apagada y de espaldas, sin perder la escena del rubro) y 'reemplaza' (escena neutra).
"""
import json, subprocess, sys
from fotos_guard import JS_LIMPIAR_FOTOS

C, N, R = 'conserva', 'neutraliza', 'reemplaza'
CASOS = [
    # Lo que salió limpio el 2026-09-24
    ('cover', 'close-up of gentle hands placing a white lily on a polished dark wooden casket, background completely blurred into warm golden bokeh', C),
    ('challenge', 'close-up of hands holding a smartphone at night, phone screen facing away from camera, face not visible', C),
    ('benefits', 'children in baseball uniforms high-fiving their coach next to the dugout, joyful genuine moment', C),
    ('next_steps', 'youth baseball team joining hands in a huddle on the field, warm afternoon light', C),
    ('solution', 'bartender pouring whisky into a glass with ice, warm bar light, shallow depth of field', C),
    ('pricing', 'row of unlabeled amber bottles on a wooden shelf, soft warm light', C),
    ('how_it_works', 'man arranging flowers on a wooden table by a window', C),
    ('how_it_works', 'clean wooden desk with a closed laptop and a cup of coffee', C),
    ('benefits', 'happy customer receiving a delivery at home', C),
    # Prohibiciones escritas por el modelo en otras formas (revisión final, M5): no deben tumbar la escena
    ('cover', 'close-up of hands holding white lilies in a serene chapel. No text, no signs', C),
    ('solution', 'family walking together in a memorial garden without any text or signage', C),
    ('benefits', 'coach high-fiving kids after practice; no logos or lettering anywhere', C),
    # Lo que trae texto inventado o contenido en pantalla (se reemplaza)
    ('cover', 'youth baseball players playing a game in a stadium at golden hour', R),
    ('cover', 'elegant storefront of the funeral home at dusk', R),
    ('benefits', 'coach pointing at a scoreboard during a game', R),
    ('solution', 'clean desk with a laptop showing a website', R),
    ('how_it_works', 'team meeting in an office around a whiteboard', R),
    ('solution', 'monitor displaying a dashboard in an office', R),
    ('extra_1', 'two-phase project plan on a desk, people discussing', R),
    ('solution', 'person using a smartphone to browse a digital catalog in a relaxed environment', R),
    ('pricing', 'business meeting with documents and a laptop on the table', R),
    ('next_steps', '', R),
    # Productos con etiqueta o pantalla: se conserva la escena del rubro, neutralizada
    ('cover', 'close-up of a hand holding a wine bottle with a blurred background', N),
    ('how_it_works', 'hands arranging wine bottles on a shelf', N),
    ('pricing', 'a detailed view of a wine bottle and glass', N),
    ('next_steps', 'a delivery person handing a package to a happy customer', N),
    ('pricing', 'bottle of premium whisky with its label on a bar counter', N),
    ('challenge', 'customer looking at a smartphone in a store', N),
    ('extra_1', 'shelf of books in a library', N),
    ('challenge', 'busy store with customers browsing the shelves', N),
    ('how_it_works', 'close-up of hands wrapping a gift box in kraft paper', N),
]

briefs = [{'slide': s, 'prompt': p} for s, p, _ in CASOS]
js = (JS_LIMPIAR_FOTOS + '\nconst BRIEFS = ' + json.dumps(briefs) + ';\n'
      'process.stdout.write(JSON.stringify(BRIEFS.map((b) => { const r = limpiarFotos([b]); '
      'const r2 = limpiarFotos(r.limpias); '
      'return { salida: r.limpias[0].prompt, reemplazadas: r.reemplazadas, neutralizadas: r.neutralizadas, '
      'segunda: r2.limpias[0].prompt }; })));')
r = subprocess.run(['node', '-e', js], capture_output=True, text=True, encoding='utf-8')
if r.returncode != 0:
    print(r.stderr[-800:]); sys.exit(1)
fallas = 0
for (slide, prompt, debe), res in zip(CASOS, json.loads(r.stdout)):
    obtuvo = R if res['reemplazadas'] else (N if res['neutralizadas'] else C)
    ok = obtuvo == debe
    fallas += not ok
    print(f"{'OK ' if ok else 'MAL'} {slide:12} {obtuvo:10} {prompt[:58]!r}")
    if obtuvo == N:
        print(f"      -> {res['salida'][:150]}")
    if not res['salida'].endswith('no signs, no labels, no text, no lettering, no logos, no watermarks'):
        print('    MAL: falta el cierre'); fallas += 1
    # Borrador guarda la descripción ya filtrada y Final la vuelve a filtrar: si el texto cambiara, el hash del
    # prompt cambiaría y el renderer volvería a pagar una foto que ya tiene.
    if res['segunda'] != res['salida']:
        print(f"    MAL: filtrar dos veces cambia el texto -> {res['segunda'][:120]}"); fallas += 1
    if slide == 'cover' and 'close-up' not in res['salida'] and 'blurred' not in res['salida']:
        print('    MAL: portada sin primer plano'); fallas += 1
print('TODO OK' if not fallas else f'{fallas} falla(s)')
sys.exit(1 if fallas else 0)
