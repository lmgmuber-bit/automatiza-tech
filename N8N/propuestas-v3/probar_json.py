"""Prueba local de la lectura del JSON del modelo (json_guard.JS_LEER_JSON) y de los nodos que la usan.

Uso: python N8N/propuestas-v3/probar_json.py   (sale con código 1 si algo falla)
No llama a OpenAI ni a n8n: ejecuta con node el JavaScript de json_guard y el código real de los nodos
«Payload final» (2-cambios.json) y «Armar payload» (1-borrador.json), con datos inventados.

Regla (2026-09-26): lectura estricta como siempre, con UNA tolerancia (llaves «}» de más al final, ejecución
408862). «2 Cambios» además rechaza lo que no es la propuesta ni un pedazo de ella (claves ajenas, arreglos,
null, envoltorio mezclado) y sigue aceptando respuestas parciales. Los casos A*, B*, C* vienen de las dos
rondas de revisión adversarial de ese día: versiones más tolerantes tomaban algo que no era la propuesta.
"""
import json, os, subprocess, sys
from json_guard import JS_LEER_JSON

sys.stdout.reconfigure(encoding='utf-8')
AQUI = os.path.dirname(os.path.abspath(__file__))
fallas = []


def ok(cond, msg):
    print(('ok    ' if cond else 'FALLA ') + msg)
    if not cond:
        fallas.append(msg)


def node_js(codigo, datos=None):
    """Corre un trozo de JavaScript con node; devuelve (salida_json | None, error | None)."""
    prog = 'const DATOS = ' + json.dumps(datos) + ';\n' + codigo
    r = subprocess.run(['node', '-e', prog], capture_output=True, text=True, encoding='utf-8')
    if r.returncode != 0:
        return None, (r.stderr.strip().splitlines() or ['sin mensaje'])[-1]
    try:
        return json.loads(r.stdout), None
    except ValueError:
        return None, 'salida no es JSON: ' + r.stdout[:200]


GUARDADA = {
    'client_name': 'Cliente Prueba', 'company_name': '[PRUEBA] Empresa',
    'challenge_title': 'Título original', 'challenge_text': 'Dijo "hola }" y siguió.',
    'solution_title': 'Solución', 'solution_text': 'Texto {con llaves} de la solución.',
    'benefits': [{'title': 'Menos espera', 'text': 'respuestas al instante'}],
    'next_steps': ['Paso uno', 'Paso dos'],
    'correo_cliente': {'asunto': 'Propuesta', 'introduccion': 'Hola', 'que_incluye': 'Todo', 'cierre': 'Chao'},
    'pricing_rows': [{'service': 'Servicio de prueba', 'price_usd': 0, 'price_label': '$1.000.000 en 2 pagos'}],
    'image_briefs': [{'slide': 'next_steps', 'prompt': 'a team celebrating, no signs, no labels, no text, no lettering, no logos, no watermarks'}],
    'unique_id': 'PRUEBA123456',
}
BRIEF_NUEVO = [{'slide': 'next_steps', 'prompt': 'a craftsman in a workshop, no signs, no labels, no text, no lettering, no logos, no watermarks'}]
PROPUESTA = dict(GUARDADA, challenge_title='Título nuevo pedido', image_briefs=BRIEF_NUEVO)
TXT = json.dumps(PROPUESTA, ensure_ascii=False)
TXT_ORIG = json.dumps(GUARDADA, ensure_ascii=False)
TXT_COMILLA = TXT.replace('\\"hola }\\"', '"hola }')  # comilla sin escapar en un texto con llave
assert TXT_COMILLA != TXT
PLANTILLA = json.dumps({'client_name': 'Nombre y apellido del cliente', 'company_name': 'Nombre del negocio',
                        'challenge_title': 'máx. 7 palabras', 'challenge_text': '2 a 3 frases',
                        'solution_title': 'máx. 7 palabras', 'solution_text': '2 a 3 frases'}, ensure_ascii=False)
CORTADA = TXT[:int(len(TXT) * 0.6)]

# 1. leerJsonModelo sola: devuelve lo mismo que JSON.parse, salvo las llaves de más al final.
P, W, E = 'propuesta', 'envuelta', 'error'
CASOS = [
    ('JSON limpio', TXT, P),
    ('bloque ```json', '```json\n' + TXT + '\n```', P),
    ('llave de más al final (forma de la ejecución 408862)', TXT + '}', P),
    ('dos llaves de más y un salto de línea', TXT + '}\n}', P),
    ('envuelta en {propuesta: …} (ejecución 403689)', json.dumps({'propuesta': PROPUESTA}, ensure_ascii=False), W),
    ('texto antes y después', 'Aquí va la propuesta:\n' + TXT + '\nListo.', E),
    ('texto antes con llaves que no son JSON', 'Nota {sin comillas} y luego ' + TXT, E),
    ('llave de más al inicio (A7)', '{\n' + TXT, E),
    ('nota JSON antes de la propuesta (B1)', '{"nota":"x"}\n' + TXT, E),
    ('correo JSON antes (B2)', 'Actualicé correo_cliente así: {"asunto":"Nuevo"}\n\nPropuesta completa:\n' + TXT, E),
    ('original y con cambios (B4)', 'Propuesta original:\n' + TXT_ORIG + '\n\nPropuesta con los cambios:\n' + TXT, E),
    ('coma sobrante y una nota después (B5)', TXT[:-1] + ',}\n{"cambios_aplicados":["challenge_title"]}', E),
    ('original y con cambios cortada (A1)', TXT_ORIG + '\n\n' + CORTADA, E),
    ('plantilla del prompt y propuesta cortada (A12)', 'Formato:\n' + PLANTILLA + '\n\n' + CORTADA, E),
    ('{antes, despues} con coma sobrante (A3)', '{"antes": ' + TXT_ORIG + ', "despues": ' + TXT[:-1] + ',}}', E),
    ('comilla sin escapar y llave en un texto', TXT_COMILLA, E),
    ('cortada a la mitad', TXT[:len(TXT) // 2], E),
    ('vacía', '', E),
    ('objeto con error de sintaxis dentro', TXT.replace('"next_steps"', '"next_steps" "x"'), E),
]
for nombre, entrada, esperado in CASOS:
    salida, error = node_js(JS_LEER_JSON + '\ntry { console.log(JSON.stringify({ r: leerJsonModelo(DATOS) })); } '
                            'catch (e) { console.log(JSON.stringify({ error: e.message })); }', entrada)
    if esperado == P:
        bien = salida is not None and salida.get('r') == PROPUESTA
    elif esperado == W:
        bien = salida is not None and salida.get('r') == {'propuesta': PROPUESTA}
    else:
        bien = salida is not None and 'error' in salida and 'r' not in salida
    ok(bien, f'leerJsonModelo: {nombre} → {esperado}' + ('' if bien else f' (salida: {salida or error})'))
salida, _ = node_js(JS_LEER_JSON + '\nconsole.log(JSON.stringify({ r: leerJsonModelo(DATOS) }));', PROPUESTA)
ok(salida is not None and salida.get('r') == PROPUESTA, 'leerJsonModelo: un objeto ya leído se devuelve igual')


def codigo_nodo(archivo, nombre):
    wf = json.load(open(os.path.join(AQUI, archivo), encoding='utf-8'))
    return next(n['parameters']['jsCode'] for n in wf['nodes'] if n['name'] == nombre)


# Stub mínimo de $('Nodo') de n8n: .first().json y .isExecuted, desde DATOS[nombre].
STUB = """
const $ = (n) => ({ isExecuted: Object.prototype.hasOwnProperty.call(DATOS, n), first: () => ({ json: DATOS[n] }) });
const correr = new Function('$', CODIGO);
try { console.log(JSON.stringify(correr($))); } catch (e) { console.log(JSON.stringify({ lanzo: e.message })); }
"""

# 2. «2 Cambios» › Payload final, con el código que está en 2-cambios.json
cod = codigo_nodo('2-cambios.json', 'Payload final')
estado = {'body': {'id': 999, 'unique_id': 'PRUEBA123456', 'company_name': '[PRUEBA] Empresa', 'payload': GUARDADA}}
BIEN, MAL = 'aplica', 'error'
for nombre, contenido, esperado in (
    ('respuesta limpia', TXT, BIEN),
    ('llave de más al final (408862)', TXT + '}', BIEN),
    ('envuelta en {propuesta}', json.dumps({'propuesta': PROPUESTA}, ensure_ascii=False), BIEN),
    ('envuelta con los comentarios de la entrada', json.dumps({'comentarios': 'x', 'propuesta': PROPUESTA}, ensure_ascii=False), BIEN),
    ('parcial: solo lo que cambió (como antes de hoy)', json.dumps({'challenge_title': 'Título nuevo pedido', 'image_briefs': BRIEF_NUEVO}, ensure_ascii=False), BIEN),
    ('solo una nota JSON', '{"nota":"x"}', MAL),
    ('{original, cambios} válido (A6)', json.dumps({'original': GUARDADA, 'cambios': {'challenge_title': 'Título nuevo pedido'}}, ensure_ascii=False), MAL),
    ('envoltorio con otra clave (A5)', json.dumps({'propuesta': GUARDADA, 'cambios': {'challenge_title': 'x'}}, ensure_ascii=False), MAL),
    ('propuesta con una clave propuesta adentro (A8)', json.dumps(dict(PROPUESTA, propuesta=GUARDADA), ensure_ascii=False), MAL),
    ('arreglo con la propuesta (C1)', '[' + TXT + ']', MAL),
    ('null (C2)', 'null', MAL),
    ('doble codificada (C5)', json.dumps(TXT, ensure_ascii=False), MAL),
    ('nota JSON antes (B1)', '{"nota":"x"}\n' + TXT, MAL),
    ('original y con cambios (B4)', TXT_ORIG + '\n' + TXT, MAL),
    ('original y con cambios cortada (A1)', TXT_ORIG + '\n\n' + CORTADA, MAL),
    ('{antes, despues} con coma sobrante (A3)', '{"antes": ' + TXT_ORIG + ', "despues": ' + TXT[:-1] + ',}}', MAL),
    ('comilla sin escapar', TXT_COMILLA, MAL),
    ('respuesta cortada', TXT[:len(TXT) // 2], MAL),
):
    salida, error = node_js('const CODIGO = ' + json.dumps(cod) + ';\n' + STUB,
                            {'Leer estado': estado, 'Aplicar comentarios': {'message': {'content': contenido}}})
    r = (salida or [{}])[0].get('json', {}) if isinstance(salida, list) else {}
    pay = r.get('payload', {}) or {}
    if esperado == BIEN:
        bien = (r.get('ok') is True and pay.get('challenge_title') == 'Título nuevo pedido' and pay.get('image_briefs') == BRIEF_NUEVO
                and pay.get('solution_text') == GUARDADA['solution_text'] and set(pay) <= set(GUARDADA) | {'extra_slides'})
    else:
        bien = r.get('ok') is False and r.get('reason', '').startswith('No se pudo usar la respuesta del modelo')
    ok(bien, f'«2 Cambios» Payload final: {nombre} → {esperado}' + ('' if bien else f' (salida: {r or salida or error})'))

# 3. «1 Borrador» › Armar payload, con el código que está en 1-borrador.json
cod = codigo_nodo('1-borrador.json', 'Armar payload')
base = {'Webhook (Entrada)': {'body': {'client_email': 'cliente-prueba@example.com', 'transcript': 'x'}},
        'Personalidad del chatbot': {'message': {'content': 'prompt del bot'}}}
for nombre, contenido, debe_ok in (
    ('respuesta limpia', TXT, True),
    ('llave de más al final', TXT + '}', True),
    ('plantilla del prompt antes de la propuesta', 'Uso esta forma:\n' + PLANTILLA + '\n\n' + TXT, False),
    ('solo la plantilla con texto antes', 'La transcripción no trae datos suficientes; dejo la estructura:\n' + PLANTILLA, False),
    ('dos propuestas distintas (B4)', TXT_ORIG + '\n' + TXT, False),
    ('arreglo', '[' + TXT + ']', False),
    ('null', 'null', False),
    ('respuesta cortada', TXT[:len(TXT) // 2], False),
):
    datos = dict(base, **{'Redactar propuesta': {'message': {'content': contenido}}})
    salida, error = node_js('const CODIGO = ' + json.dumps(cod) + ';\n' + STUB, datos)
    r = (salida or [{}])[0].get('json', {}) if isinstance(salida, list) else {}
    if debe_ok:
        ok(r.get('payload', {}).get('challenge_title') == 'Título nuevo pedido' and r.get('payload', {}).get('pricing_rows', [{}])[0].get('price_label') == 'Por confirmar',
           f'«1 Borrador» Armar payload: {nombre} → payload armado' + ('' if r else f' (salida: {salida or error})'))
    else:
        ok(isinstance(salida, dict) and 'lanzo' in salida, f'«1 Borrador» Armar payload: {nombre} → falla (el flujo avisa el error)'
           + ('' if isinstance(salida, dict) and 'lanzo' in salida else f' (salida: {str(salida or error)[:160]})'))

print('TODO OK' if not fallas else f'{len(fallas)} FALLA(S)')
sys.exit(1 if fallas else 0)
