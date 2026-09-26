"""Prueba local de la lectura del JSON del modelo (json_guard.JS_LEER_JSON) y de los nodos que la usan.

Uso: python N8N/propuestas-v3/probar_json.py   (sale con código 1 si algo falla)
No llama a OpenAI ni a n8n: ejecuta con node el JavaScript de json_guard y el código real de los nodos
«Payload final» (2-cambios.json) y «Armar payload» (1-borrador.json), con datos inventados.
Reproduce la forma de la ejecución 408862 del 2026-09-26: respuesta correcta con una llave `}` de más al final.
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


PROPUESTA = {
    'client_name': 'Cliente Prueba', 'company_name': '[PRUEBA] Empresa',
    'challenge_title': 'Reto {con llaves} en el texto', 'challenge_text': 'Dijo "hola }" y siguió.',
    'next_steps': ['Paso uno', 'Paso dos'],
    'correo_cliente': {'asunto': 'Propuesta', 'introduccion': 'Hola', 'que_incluye': 'Todo', 'cierre': 'Chao'},
    'pricing_rows': [{'service': 'Servicio de prueba', 'price_usd': 0, 'price_label': '$1.000.000 en 2 pagos'}],
    'image_briefs': [{'slide': 'next_steps', 'prompt': 'a craftsman in a workshop, no signs, no labels, no text, no lettering, no logos, no watermarks'}],
    'unique_id': 'PRUEBA123456',
}
TXT = json.dumps(PROPUESTA, ensure_ascii=False)

# 1. leerJsonModelo por sí sola
CASOS = [
    ('JSON limpio', TXT, 'objeto'),
    ('bloque ```json', '```json\n' + TXT + '\n```', 'objeto'),
    ('llave de más al final (forma de la ejecución 408862)', TXT + '}', 'objeto'),
    ('dos llaves de más y un salto de línea', TXT + '}\n}', 'objeto'),
    ('texto antes y después', 'Aquí va la propuesta:\n' + TXT + '\nListo.', 'objeto'),
    ('texto antes con llaves que no son JSON', 'Nota {sin comillas} y luego ' + TXT, 'objeto'),
    ('envuelta en {propuesta: …} (ejecución 403689)', json.dumps({'propuesta': PROPUESTA}, ensure_ascii=False), 'envuelta'),
    ('cortada a la mitad', TXT[:len(TXT) // 2], 'error'),
    ('vacía', '', 'error'),
    ('objeto con error de sintaxis dentro', TXT.replace('"next_steps"', '"next_steps" "x"') , 'error'),
]
for nombre, entrada, esperado in CASOS:
    salida, error = node_js(JS_LEER_JSON + '\ntry { console.log(JSON.stringify({ r: leerJsonModelo(DATOS) })); } '
                            'catch (e) { console.log(JSON.stringify({ error: e.message })); }', entrada)
    if esperado == 'objeto':
        ok(salida is not None and salida.get('r') == PROPUESTA, f'leerJsonModelo: {nombre} → la propuesta completa y exacta')
    elif esperado == 'envuelta':
        ok(salida is not None and salida.get('r') == {'propuesta': PROPUESTA}, f'leerJsonModelo: {nombre} → la devuelve tal cual (el nodo la desenvuelve)')
    else:
        ok(salida is not None and 'error' in salida and 'r' not in salida, f'leerJsonModelo: {nombre} → error, sin inventar datos')
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
guardada = dict(PROPUESTA, image_briefs=[{'slide': 'next_steps', 'prompt': 'a team celebrating, no signs, no labels, no text, no lettering, no logos, no watermarks'}])
estado = {'body': {'id': 999, 'unique_id': 'PRUEBA123456', 'company_name': '[PRUEBA] Empresa', 'payload': guardada}}
for nombre, contenido, debe_ok in (
    ('respuesta limpia', TXT, True),
    ('llave de más al final (408862)', TXT + '}', True),
    ('envuelta en {propuesta}', json.dumps({'propuesta': PROPUESTA}, ensure_ascii=False), True),
    ('respuesta cortada', TXT[:len(TXT) // 2], False),
):
    salida, error = node_js('const CODIGO = ' + json.dumps(cod) + ';\n' + STUB,
                            {'Leer estado': estado, 'Aplicar comentarios': {'message': {'content': contenido}}})
    r = (salida or [{}])[0].get('json', {}) if isinstance(salida, list) else {}
    if debe_ok:
        ok(r.get('ok') is True and r.get('payload', {}).get('image_briefs') == PROPUESTA['image_briefs'],
           f'«2 Cambios» Payload final: {nombre} → ok y con el cambio aplicado' + ('' if r else f' (salida: {salida or error})'))
    else:
        ok(r.get('ok') is False and 'JSON' in r.get('reason', ''), f'«2 Cambios» Payload final: {nombre} → error con motivo')

# 3. «1 Borrador» › Armar payload, con el código que está en 1-borrador.json
cod = codigo_nodo('1-borrador.json', 'Armar payload')
base = {'Webhook (Entrada)': {'body': {'client_email': 'cliente-prueba@example.com', 'transcript': 'x'}},
        'Personalidad del chatbot': {'message': {'content': 'prompt del bot'}}}
for nombre, contenido, debe_ok in (
    ('respuesta limpia', TXT, True),
    ('llave de más al final', TXT + '}', True),
    ('respuesta cortada', TXT[:len(TXT) // 2], False),
):
    datos = dict(base, **{'Redactar propuesta': {'message': {'content': contenido}}})
    salida, error = node_js('const CODIGO = ' + json.dumps(cod) + ';\n' + STUB, datos)
    r = (salida or [{}])[0].get('json', {}) if isinstance(salida, list) else {}
    if debe_ok:
        ok(r.get('payload', {}).get('company_name') == PROPUESTA['company_name'] and r.get('payload', {}).get('pricing_rows', [{}])[0].get('price_label') == 'Por confirmar',
           f'«1 Borrador» Armar payload: {nombre} → payload armado' + ('' if r else f' (salida: {salida or error})'))
    else:
        ok(isinstance(salida, dict) and 'lanzo' in salida, f'«1 Borrador» Armar payload: {nombre} → falla (el flujo avisa el error)')

print('TODO OK' if not fallas else f'{len(fallas)} FALLA(S)')
sys.exit(1 if fallas else 0)
