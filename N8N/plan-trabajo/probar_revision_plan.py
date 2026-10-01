"""Prueba local de la revisión de texto en las fotos de «Plan de trabajo · 3 Render» (decisión D18, 29-sep).

Uso: python N8N/plan-trabajo/probar_revision_plan.py   (sale con código 1 si algo falla)
Como N8N/propuestas-v3/probar_revision_fotos.py (la misma revisión en las propuestas): no llama a OpenAI, al renderer,
a WordPress ni a n8n; recorre el grafo de plan-3-render.json con node, como una pequeña máquina de estados. Los nodos
Code corren con su jsCode REAL (el que se publica); los HTTP, el webhook y el correo son simulados. El renderer
simulado se porta como el de verdad (renderer/src/server.js y photo-manifest.js): reutiliza una foto solo si el sha256
de su descripción no cambió, guarda `img/<lámina>.jpg`, su manifest.json conserva las entradas viejas aunque la foto
nueva no salga y no anota las fotos que llegan hechas en `images` (portada y cierre de la propuesta).

$('Nodo').first() devuelve la ÚLTIMA pasada del nodo (como n8n sin runIndex) y lanza si el nodo no corrió; $runIndex
es cuántas veces corrió antes el nodo; las expresiones ={{ … }} de los HTTP y de los IF se evalúan igual que en n8n.
Datos inventados («Cliente Prueba», «[PRUEBA] …»): el repositorio es público.
"""
import json, os, re, subprocess, sys

sys.stdout.reconfigure(encoding='utf-8')
AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'propuestas-v3'))
sys.path.insert(0, AQUI)
from probar_plan import (BASE_R, BRIEF_CIERRE, BRIEF_COVER, BRIEFS_NUEVAS, MANIFEST_PROP, NUEVAS, TODAS,  # noqa: E402
                         brief, cuerpo_render)
from plan_js import INSTRUCCION_TEXTO, JS_SHA256, RETOMA  # noqa: E402
import build_plan_3_render as B  # noqa: E402  (vuelve a escribir plan-3-render.json)

fallas = []


def ok(cond, msg, detalle=''):
    print(('ok    ' if cond else 'FALLA ') + msg + ('' if cond or not detalle else f' ({str(detalle)[:300]})'))
    if not cond:
        fallas.append(msg)


with open(os.path.join(AQUI, 'plan-3-render.json'), encoding='utf-8') as fh:
    WF = json.load(fh)
UID = 'PRUEBAplan01'
MAX_R = B.MAX_RENDERS

SIM = r"""
const crypto = require('node:crypto');
const { wf, esc: E } = DATOS;
const sha = (s) => crypto.createHash('sha256').update(String(s)).digest('hex');
const nodos = Object.fromEntries(wf.nodes.map((n) => [n.name, n]));
const runData = {};
const T = { pasos: [], renders: [], pagos: {}, consultas: [], lecturas: [], lecturas_previas: [], preparaciones: [],
            decisiones: [], vista: null, resultado: null, correo: null, error: null };
let manifest = E.manifest_inicial ? JSON.parse(JSON.stringify(E.manifest_inicial)) : {};
let llamadasOpenai = 0;
const $ = (n) => ({
  get isExecuted() { return !!(runData[n] && runData[n].length); },
  first: () => {
    if (!runData[n] || !runData[n].length) throw new Error(`Nodo sin ejecutar: ${n}`);
    return runData[n][runData[n].length - 1][0];
  },
});
const $execution = { id: 'SIM-1' };
function evaluar(expr, $json, $input) {
  if (typeof expr !== 'string' || !expr.startsWith('=')) return expr;
  const s = expr.slice(1);
  const uno = s.match(/^\{\{([\s\S]*)\}\}$/);
  const ev = (code) => new Function('$', '$json', '$input', '$execution', 'return (' + code + ');')($, $json, $input, $execution);
  if (uno && !uno[1].includes('{{')) return ev(uno[1]);
  return s.replace(/\{\{([\s\S]*?)\}\}/g, (_, code) => String(ev(code)));
}
// Salida del nodo «Render» con fullResponse: {statusCode, body}. E.http_render[n-1] fuerza otra respuesta en el render n.
function renderer(cuerpo) {
  const n = T.renders.length + 1;
  const briefs = Array.isArray(cuerpo.image_briefs) ? cuerpo.image_briefs : [];
  T.renders.push({ n, unique_id: cuerpo.unique_id, draft: cuerpo.draft, images: cuerpo.images,
                   prompts: Object.fromEntries(briefs.map((b) => [b.slide, b.prompt])) });
  const forzada = (E.http_render || [])[n - 1];
  if (forzada) return forzada;
  const fallan = (E.fallan && E.fallan[n - 1]) || E.fallan_siempre || [];
  const missing = [];
  let reused = 0;
  for (const b of briefs) {
    const e = manifest[b.slide];
    if (e && e.hash === sha(b.prompt)) { reused++; continue; }
    if (fallan.includes(b.slide)) { missing.push(b.slide); continue; }
    T.pagos[b.slide] = (T.pagos[b.slide] || 0) + 1;
    manifest[b.slide] = { file: b.slide + '.jpg', hash: sha(b.prompt) };
  }
  const r = { images: { requested: briefs.length, stored_local: briefs.length - missing.length, kept_remote: [], missing, reused } };
  if (!(E.sin_vista || []).includes(n)) Object.assign(r, { view_url: '__BASE__/p/' + cuerpo.unique_id + '/index.html',
                                                          pdf_url: '__BASE__/p/' + cuerpo.unique_id + '/presentation.pdf' });
  return { statusCode: 200, body: r, headers: {} };
}
function openai(cuerpo) {
  T.consultas.push(cuerpo);
  const lista = E.openai || [];
  const m = llamadasOpenai < lista.length ? lista[llamadasOpenai] : E.openai_siempre;
  llamadasOpenai++;
  if (!m) return { statusCode: 200, body: { choices: [{ message: { content: '{"con_texto": []}' } }] } };
  if (m.raw) return m.raw;
  const content = 'contenido' in m ? m.contenido : JSON.stringify({ con_texto: m.con_texto });
  return { statusCode: 200, body: { choices: [{ message: { content } }] } };
}
function http(nombre, url, cuerpo) {
  switch (nombre) {
    case 'Leer render': return E.leer_render;
    case 'Fotos de la propuesta': return E.manifest_propuesta || { statusCode: 404, body: 'Not Found' };
    case 'Fotos previas del plan': {
      T.lecturas_previas.push(url);
      if (E.manifest_previo) return E.manifest_previo;
      // Como el static del renderer: sin manifest.json todavía (primera versión final), 404.
      return Object.keys(manifest).length ? { statusCode: 200, body: JSON.parse(JSON.stringify(manifest)) } : { statusCode: 404, body: 'Not Found' };
    }
    case 'Render': return renderer(cuerpo);
    case 'Leer fotos del plan': {
      T.lecturas.push(url);
      if (E.manifest) return E.manifest;
      return { statusCode: 200, body: Object.assign({}, manifest, E.manifest_extra || {}) };
    }
    case 'Buscar texto en fotos': return openai(cuerpo);
    case 'Guardar vista': {
      T.vista = cuerpo;
      return { statusCode: 200, body: { ok: true, estado: cuerpo.modo === 'final' ? (cuerpo.ok ? 'listo' : 'error') : 'borrador' } };
    }
    default: throw new Error('HTTP sin simular: ' + nombre);
  }
}
function correr(nombre, items) {
  const n = nodos[nombre];
  if (!n) throw new Error('Conexión a un nodo que no existe: ' + nombre);
  const $json = items[0] ? items[0].json : {};
  const $input = { first: () => items[0], all: () => items, item: items[0] };
  const $runIndex = (runData[nombre] || []).length;
  const p = n.parameters;
  let salidas;
  if (n.type === 'n8n-nodes-base.webhook') {
    salidas = [[{ json: { headers: {}, body: E.webhook } }]];
  } else if (n.type === 'n8n-nodes-base.code') {
    const fn = new Function('$', '$json', '$input', '$runIndex', '$execution', p.jsCode);
    salidas = [fn($, $json, $input, $runIndex, $execution)];
  } else if (n.type === 'n8n-nodes-base.httpRequest') {
    const url = evaluar(p.url, $json, $input);
    const cuerpo = p.sendBody ? JSON.parse(evaluar(p.jsonBody, $json, $input)) : undefined;
    salidas = [[{ json: http(nombre, url, cuerpo) }]];
  } else if (n.type === 'n8n-nodes-base.if') {
    const v = evaluar(p.conditions.conditions[0].leftValue, $json, $input);
    salidas = (v === true || v === 'true') ? [items, []] : [[], items];
  } else if (n.type === 'n8n-nodes-base.emailSend') {
    T.correo = { asunto: evaluar(p.subject, $json, $input), html: evaluar(p.html, $json, $input) };
    salidas = [items];
  } else {
    throw new Error('tipo sin simular: ' + n.type);
  }
  runData[nombre] = runData[nombre] || [];
  runData[nombre].push(salidas.flat());
  return salidas;
}
try {
  const cola = [['Webhook', [{ json: {} }]]];
  let pasos = 0;
  while (cola.length) {
    if (++pasos > 300) throw new Error('el flujo no termina (más de 300 pasos)');
    const [nombre, items] = cola.shift();
    T.pasos.push(nombre);
    const salidas = correr(nombre, items);
    const ult = runData[nombre][runData[nombre].length - 1];
    if (nombre === 'Preparar revisión de texto') T.preparaciones.push(ult[0].json);
    if (nombre === 'Decidir retoma') T.decisiones.push(ult[0].json);
    if (nombre === 'Resultado del render') T.resultado = ult[0].json;
    const con = (wf.connections[nombre] || { main: [] }).main;
    salidas.forEach((its, i) => {
      if (!its || !its.length) return;
      for (const d of con[i] || []) cola.push([d.node, its]);
    });
  }
} catch (e) {
  T.error = e.message;
}
T.manifest = manifest;
console.log(JSON.stringify(T));
""".replace('__BASE__', BASE_R)

RENDER_CON = cuerpo_render(True, unique_id=UID, image_briefs=[BRIEF_COVER] + BRIEFS_NUEVAS + [BRIEF_CIERRE])
RENDER_SIN = cuerpo_render(True, unique_id=UID, image_briefs=[BRIEF_COVER] + BRIEFS_NUEVAS + [BRIEF_CIERRE])


def leido(render, uid='PRUEBAprop01', estado=None):
    if estado is None:
        estado = 'borrador' if render.get('draft') else 'aprobando'
    return {'statusCode': 200, 'body': {'ok': True, 'render': render, 'propuesta_uid': uid, 'crm_cliente_id': 5, 'estado': estado}}


def simular(**esc):
    esc.setdefault('webhook', {'id': 9, 'codigo': UID, 'modo': 'final', 'aviso': False})
    if 'leer_render' not in esc:
        con_propuesta = esc.pop('con_propuesta', True)
        render = esc.pop('render', RENDER_CON if con_propuesta else RENDER_SIN)
        esc['leer_render'] = leido(render, uid='PRUEBAprop01' if con_propuesta else '')
        if con_propuesta:
            esc.setdefault('manifest_propuesta', MANIFEST_PROP)
    prog = 'const DATOS = ' + json.dumps({'wf': WF, 'esc': esc}, ensure_ascii=False) + ';\n' + SIM
    r = subprocess.run(['node', '-'], input=prog, capture_output=True, text=True, encoding='utf-8')
    if r.returncode != 0:
        return {'error': (r.stderr.strip().splitlines() or ['node sin mensaje'])[-1], 'renders': [], 'consultas': [], 'pagos': {},
                'decisiones': [], 'preparaciones': [], 'lecturas': [], 'lecturas_previas': [], 'manifest': {}}
    return json.loads(r.stdout)


URL_SEGURA = re.compile(r'^' + re.escape(BASE_R) + r'/p/' + re.escape(UID) + r'/img/[A-Za-z0-9_-]+\.[A-Za-z0-9]{2,5}\?v=\d+$')


def urls(consulta):
    return [c['image_url']['url'] for c in consulta['messages'][0]['content'] if c.get('type') == 'image_url']


def slides_de(consulta):
    return [u.rsplit('/', 1)[1].split('.')[0] for u in urls(consulta)]


def avisos(t):
    return (t.get('resultado') or {}).get('avisos') or []


def estado(t):
    v = t.get('vista') or {}
    return ('listo' if v.get('ok') else 'error') if v.get('modo') == 'final' else v.get('modo')


def nota(t):
    return (t.get('vista') or {}).get('nota') or ''


def sin_error(t, caso):
    ok(not t.get('error') and t.get('vista') and t.get('correo'), f'{caso}: el recorrido llega a /vista y al correo sin lanzar',
       t.get('error') or 'no llegó al correo')


# 0. Estructura: la credencial de OpenAI de las propuestas, respuesta completa y nunca corta el flujo.
nd = {n['name']: n for n in WF['nodes']}
g = nd.get('Buscar texto en fotos', {})
gp = g.get('parameters', {})
ok(g.get('credentials', {}).get('openAiApi', {}).get('id') == 'g52IEXpRfN5r7jKw' and gp.get('nodeCredentialType') == 'openAiApi'
   and gp.get('authentication') == 'predefinedCredentialType' and gp.get('url') == 'https://api.openai.com/v1/chat/completions'
   and g.get('onError') == 'continueRegularOutput' and gp.get('options', {}).get('timeout') == 120000
   and gp.get('options', {}).get('response', {}).get('response') == {'fullResponse': True, 'neverError': True},
   '0: «Buscar texto en fotos» usa la credencial de OpenAI de las propuestas (g52IEXpRfN5r7jKw), 120 s y nunca corta el flujo')
ok(all('credentials' not in nd[x] and nd[x].get('onError') == 'continueRegularOutput' for x in ('Fotos previas del plan', 'Leer fotos del plan')),
   '0: los manifest del plan se leen sin credenciales y nunca cortan el flujo')
ok(len(WF['nodes']) == 24 and MAX_R == 3, '0: 24 nodos y MAX_RENDERS = 3 (tope total, retoma incluida)', len(WF['nodes']))
w_prep = nd['Preparar render']['parameters']['jsCode']
ok(JS_SHA256 in w_prep and json.dumps(RETOMA) in w_prep and json.dumps(INSTRUCCION_TEXTO) in nd['Preparar revisión de texto']['parameters']['jsCode'],
   '0: los nodos publicados llevan el sha256, la RETOMA y la instrucción de build_3_final.py (las de las propuestas)')

# 1. Con propuesta, versión completa y GPT-4o dice [] → una consulta con las 8 fotos nuevas (no portada ni cierre).
t = simular(openai=[{'con_texto': []}])
sin_error(t, '1')
ok(len(t['renders']) == 1, '1: un solo render', f"renders={len(t['renders'])}")
ok(len(t['consultas']) == 1 and slides_de(t['consultas'][0]) == NUEVAS,
   '1: una sola consulta a GPT-4o con las 8 fotos nuevas, sin la portada ni el cierre de la propuesta', [slides_de(c) for c in t['consultas']])
ok(avisos(t) == [] and estado(t) == 'listo', '1: sin avisos de fotos y el plan queda «listo»', f'{estado(t)} {avisos(t)}')
ok('Fotos revisadas con GPT-4o: sin texto' in nota(t), '1: la nota de /vista dice que se revisaron (evidencia para la prueba real)', nota(t))
ok(t['pagos'] == {s: 1 for s in NUEVAS}, '1: cada foto nueva se pagó una vez', t['pagos'])
c0 = t['consultas'][0] if t['consultas'] else {}
ok(c0.get('model') == 'gpt-4o' and c0.get('temperature') == 0 and c0.get('response_format') == {'type': 'json_object'}
   and c0.get('max_tokens') == 200, '1: la consulta es gpt-4o, temperatura 0, JSON y 200 tokens de salida')
cont = c0.get('messages', [{}])[0].get('content', [])
ok(cont and cont[0] == {'type': 'text', 'text': INSTRUCCION_TEXTO} and all(c['image_url']['detail'] == 'high' for c in cont if c['type'] == 'image_url'),
   '1: la instrucción es la de las propuestas y cada foto va en detail high')
ok(all(URL_SEGURA.match(u) for c in t['consultas'] for u in urls(c)) and all(u.endswith('?v=1') for u in urls(c0)),
   '1: todas las URL son del plan en el renderer, seguras y con ?v= del render', [u for c in t['consultas'] for u in urls(c)])
ok(t['lecturas_previas'] == [f'{BASE_R}/p/{UID}/img/manifest.json'] and t['lecturas'] == [f'{BASE_R}/p/{UID}/img/manifest.json'],
   '1: lee el manifest del plan antes del render y después, para la revisión', (t['lecturas_previas'], t['lecturas']))

# 2. Ronda 0 con ['fase_2'] → se rehace solo esa foto, con RETOMA una sola vez; las demás idénticas.
t = simular(openai=[{'con_texto': ['fase_2']}, {'con_texto': []}])
sin_error(t, '2')
ok(len(t['renders']) == 2, '2: dos renders (el normal y la retoma)', f"renders={len(t['renders'])}")
if len(t['renders']) == 2:
    p0, p1 = t['renders'][0]['prompts'], t['renders'][1]['prompts']
    ok(p1['fase_2'] == p0['fase_2'] + RETOMA and p1['fase_2'].count(RETOMA) == 1, '2: la foto con texto se pide con su descripción + RETOMA, una vez')
    ok(all(p1[s] == p0[s] for s in NUEVAS if s != 'fase_2') and set(p1) == set(p0),
       '2: las demás descripciones son idénticas (mismo hash: el renderer las reutiliza)')
    ok(t['renders'][1]['images'] == t['renders'][0]['images'] and set(t['renders'][1]['images']) == {'cover', 'cierre'},
       '2: la retoma conserva la portada y el cierre de la propuesta', t['renders'][1]['images'])
ok(t['pagos'] == {**{s: 1 for s in NUEVAS}, 'fase_2': 2}, '2: solo la foto rehecha se paga de nuevo', t['pagos'])
ok(t['decisiones'] and t['decisiones'][0].get('rehacer') is True and t['decisiones'][0].get('fotos_retomadas') == ['fase_2'],
   '2: «Decidir retoma» pide rehacer solo «fase_2»', str(t['decisiones'][:1])[:300])
ok(len(t['consultas']) == 2 and t['preparaciones'][1]['ronda'] == 1, '2: la segunda revisión es la ronda 1')
if len(t['consultas']) == 2:
    u0 = dict(zip(slides_de(t['consultas'][0]), urls(t['consultas'][0])))
    u1 = dict(zip(slides_de(t['consultas'][1]), urls(t['consultas'][1])))
    ok(u0.get('fase_2') != u1.get('fase_2'), '2: la ronda 1 pide la foto rehecha con otra URL (mismo archivo sobrescrito)')
ok(estado(t) == 'listo' and avisos(t) == ['Fotos rehechas porque tenían texto: fase 2'], '2: «listo», con el aviso de la foto rehecha', avisos(t))
ok('2 renders' in nota(t) and 'Fotos rehechas porque tenían texto: fase 2' in nota(t), '2: la nota de /vista cuenta los 2 renders y la retoma', nota(t))
html = (t.get('correo') or {}).get('html') or ''
ok('⚠️ Fotos rehechas porque tenían texto: fase 2' in html and 'easypanel' not in html, '2: el correo a Luis lo avisa y no enlaza al renderer')

# 2b. La foto rehecha no sale nunca: la retoma gasta del mismo tope de 3 renders y el plan queda en «error».
t = simular(openai=[{'con_texto': ['fase_2']}], fallan=[[], ['fase_2'], ['fase_2'], ['fase_2'], ['fase_2']])
sin_error(t, '2b')
ok(len(t['renders']) == MAX_R, f'2b: la retoma cuenta dentro del tope: {MAX_R} renders en total', f"renders={len(t['renders'])}")
ok(all(r['prompts']['fase_2'].endswith(RETOMA) and r['prompts']['fase_2'].count(RETOMA) == 1 for r in t['renders'][1:]),
   '2b: cada reintento de la retoma usa la descripción de la retoma, no la original')
ok(len(t['consultas']) == 1, '2b: sin versión completa no hay segunda revisión (no se revisa una foto que no salió)', len(t['consultas']))
ok(estado(t) == 'error' and 'fotos que no se generaron tras 3 intentos: fase 2' in nota(t) and 'Fotos rehechas porque tenían texto: fase 2' in nota(t),
   '2b: la foto que no salió deja el plan en «error», y la nota dice que se intentó rehacer', nota(t))

# 2c. El render normal gasta los 3 renders: no queda ninguno para la retoma; solo se avisa.
t = simular(openai=[{'con_texto': ['fase_2']}], fallan=[['gantt'], ['gantt'], []])
sin_error(t, '2c')
ok(len(t['renders']) == MAX_R and len(t['consultas']) == 1 and t['pagos'].get('fase_2') == 1,
   '2c: con los 3 renders gastados no se rehace nada (ni se paga otra foto)', f"renders={len(t['renders'])} pagos={t['pagos']}")
ok(estado(t) == 'listo' and any('todavía pueden tener texto' in a and 'fase 2' in a and 'no quedaban renders' in a for a in avisos(t)),
   '2c: «listo» con el aviso de la foto con texto y el motivo', avisos(t))

# 2d. El render normal usó 2: la retoma es el 3.º y, si su foto sale, hay segunda revisión.
t = simular(openai=[{'con_texto': ['fase_2']}, {'con_texto': []}], fallan=[['gantt'], [], []])
sin_error(t, '2d')
ok(len(t['renders']) == 3 and len(t['consultas']) == 2 and estado(t) == 'listo' and '3 renders' in nota(t),
   '2d: 2 renders + la retoma = 3, segunda revisión y «listo»', f"renders={len(t['renders'])} {nota(t)}")

# 3. La ronda 1 todavía ve texto → no hay tercer render; avisa las dos cosas, en español.
t = simular(openai=[{'con_texto': ['fase_2', 'necesitamos']}, {'con_texto': ['necesitamos']}])
sin_error(t, '3')
ok(len(t['renders']) == 2 and len(t['decisiones']) == 2 and t['decisiones'][1].get('rehacer') is False, '3: la ronda 1 no pide rehacer')
av = avisos(t)
ok(any(a.startswith('Fotos rehechas porque tenían texto: ') and 'fase 2' in a and 'qué necesitamos de ti' in a for a in av),
   '3: avisa «rehechas» con los nombres en español', av)
ok(any('todavía pueden tener texto' in a and 'qué necesitamos de ti' in a and 'no quedaban' not in a for a in av), '3: avisa «todavía pueden tener texto»', av)
ok(not any(re.search(r'\b(fase_2|necesitamos)\b', a.replace('qué necesitamos de ti', '')) for a in av), '3: ningún nombre de lámina en clave', av)
ok(estado(t) == 'listo' and 'todavía pueden tener texto' in nota(t), '3: sigue «listo» y la nota lo dice')

# 4. OpenAI con error HTTP, sin respuesta o con JSON ilegible → «No se pudo revisar» y sigue «listo».
for nombre, resp, pista in (
    ('HTTP 500', {'raw': {'statusCode': 500, 'body': {'error': {'message': 'server'}}}}, 'HTTP 500'),
    ('HTTP 429', {'raw': {'statusCode': 429, 'body': 'rate limit'}}, 'HTTP 429'),
    ('sin respuesta (timeout)', {'raw': {'error': {'message': 'timeout of 120000ms exceeded'}}}, 'no respondió'),
    ('sin respuesta y sin mensaje', {'raw': {}}, 'no respondió'),
    ('contenido que no es JSON', {'contenido': 'No veo texto.'}, 'ilegible'),
    ('JSON sin con_texto', {'contenido': '{"fotos": []}'}, 'ilegible'),
    ('con_texto que no es lista', {'contenido': '{"con_texto": "fase_2"}'}, 'ilegible'),
    ('null', {'contenido': 'null'}, 'ilegible'),
    ('200 con cuerpo de texto', {'raw': {'statusCode': 200, 'body': '<html>proxy</html>'}}, 'ilegible'),
    ('200 sin choices', {'raw': {'statusCode': 200, 'body': {'choices': []}}}, 'ilegible'),
):
    t = simular(openai=[resp])
    av = avisos(t)
    ok(not t.get('error') and estado(t) == 'listo' and len(t['renders']) == 1 and any(a.startswith('No se pudo revisar') and pista in a for a in av),
       f'4: OpenAI {nombre} → «No se pudo revisar» y sigue «listo»', f"{t.get('error')} {estado(t)} {av}")
t = simular(openai=[{'con_texto': ['fase_2']}, {'raw': {'statusCode': 503, 'body': ''}}])
av = avisos(t)
ok(estado(t) == 'listo' and any(a.startswith('Fotos rehechas') for a in av) and any('No se pudo revisar' in a for a in av),
   '4: falla de OpenAI en la ronda 1 → avisa la retoma y que no se pudo revisar', av)

# 5. Manifest del plan que no se puede leer → no se consulta a OpenAI y se avisa; sin presentación no se revisa.
for nombre, man in (
    ('404', {'statusCode': 404, 'body': 'Not Found'}),
    ('HTML con 200', {'statusCode': 200, 'body': '<html>error</html>'}),
    ('arreglo', {'statusCode': 200, 'body': [{'file': 'metodo.jpg'}]}),
    ('null', {'statusCode': 200, 'body': None}),
    ('sin respuesta', {'error': {'message': 'ECONNRESET'}}),
):
    t = simular(manifest=man)
    ok(not t.get('error') and t['consultas'] == [] and estado(t) == 'listo'
       and any(a.startswith('No se revisó si las fotos tienen texto: no se pudo leer la lista de fotos') for a in avisos(t)),
       f'5: manifest del plan {nombre} → sin consulta a OpenAI, «listo» y con aviso', f"{t.get('error')} {len(t['consultas'])} {avisos(t)}")
t = simular(manifest={'statusCode': 200, 'body': json.dumps({'metodo': {'file': 'metodo.jpg', 'hash': 'x'}})})
ok(len(t['consultas']) == 1 and slides_de(t['consultas'][0]) == ['metodo'], '5: un manifest que llega como texto JSON se lee igual',
   [slides_de(c) for c in t['consultas']])
t = simular(manifest={'statusCode': 200, 'body': {}})
ok(t['consultas'] == [] and any('no hay fotos guardadas' in a for a in avisos(t)), '5: manifest vacío → «no hay fotos guardadas»', avisos(t))
t = simular(sin_vista=list(range(1, 10)))
ok(t['consultas'] == [] and t['lecturas'] == [] and estado(t) == 'error' and len(t['renders']) == MAX_R,
   '5: sin presentación no se revisan fotos (el manifest puede ser de una versión anterior)', f"{len(t['consultas'])} {estado(t)}")
t = simular(http_render=[{'statusCode': 400, 'body': {'error': 'invalid payload', 'details': ['fases debe traer al menos una fase']}}])
ok(len(t['renders']) == 1 and t['consultas'] == [] and estado(t) == 'error', '5: un 400 del esquema no se reintenta ni se revisa (D11)')
t = simular(fallan_siempre=['gantt'])
ok(len(t['renders']) == MAX_R and t['consultas'] == [] and estado(t) == 'error',
   '5: si faltan fotos tras los 3 renders, no se paga la revisión: el plan queda en «error» y se revisa al aprobarlo de nuevo')

# 6. Nombres de archivo raros, láminas que no se pidieron o unique_id inválido → nunca entran a la consulta.
RAROS = {'metodo': {'file': '../../etc/passwd', 'hash': 'x'}, 'gantt': {'file': 'a/b.jpg', 'hash': 'x'},
         'fase_1': {'file': 'x\\y.jpg', 'hash': 'x'}, 'fase_2': {'file': '..', 'hash': 'x'}, 'fase_3': {'file': '.htaccess', 'hash': 'x'},
         'necesitamos': {'file': 'foto con espacio.jpg', 'hash': 'x'}, 'reuniones': {'file': 'reuniones.jpg?x=1', 'hash': 'x'},
         'portal': 'portal.jpg', 'cover': {'file': 'cover.jpg', 'hash': 'x'}, 'cierre': {'file': 'cierre.jpg', 'hash': 'x'},
         '__proto__': {'file': 'proto.jpg', 'hash': 'x'}}
t = simular(manifest={'statusCode': 200, 'body': RAROS})
ok(not t.get('error') and t['consultas'] == [] and any('no hay fotos guardadas' in a for a in avisos(t)),
   '6: ningún nombre raro ni lámina no pedida (portada y cierre de la propuesta) entra a la consulta', [urls(c) for c in t['consultas']])
t = simular(manifest_extra={'cover': {'file': 'cover.jpg', 'hash': 'x'}, 'cierre': {'file': 'cierre.jpg', 'hash': 'x'},
                            'challenge': {'file': 'challenge.jpg', 'hash': 'x'}})
ok(len(t['consultas']) == 1 and slides_de(t['consultas'][0]) == NUEVAS, '6: láminas del manifest que no se pidieron quedan fuera',
   [slides_de(c) for c in t['consultas']])
for uid_malo in ('../x', 'ab', 'con espacio12', 'a' * 65):
    r = cuerpo_render(True, unique_id=uid_malo, image_briefs=BRIEFS_NUEVAS)
    t = simular(render=r)
    ok(not t.get('error') and t['consultas'] == [] and any(a.startswith('No se revisó si las fotos tienen texto') for a in avisos(t)),
       f'6: unique_id inválido {uid_malo!r} → no se consulta a OpenAI', f"{t.get('error')} {len(t['consultas'])} {avisos(t)}")

# 7. El modelo devuelve láminas que no existen, repetidas o que no se le mostraron → se ignoran / se deduplican.
t = simular(openai=[{'con_texto': ['fase_2', 'fase_2', 'inventada', 'metodo', '../metodo', 3, None, 'cover', 'cierre']}, {'con_texto': []}])
sin_error(t, '7')
ok(t['decisiones'] and t['decisiones'][0].get('fotos_retomadas') == ['fase_2', 'metodo'],
   '7: repetidas se deduplican; inventadas, portada y cierre (no se le mostraron) se ignoran', str(t['decisiones'][:1])[:200])
if len(t['renders']) == 2:
    ok(t['renders'][1]['prompts']['fase_2'].count(RETOMA) == 1 and t['renders'][1]['prompts']['metodo'].count(RETOMA) == 1
       and 'cover' not in t['renders'][1]['prompts'], '7: RETOMA una sola vez y la portada sigue viniendo de la propuesta')
t = simular(openai=[{'con_texto': ['inventada', 'cover']}])
ok(len(t['renders']) == 1 and t['decisiones'] and t['decisiones'][0].get('rehacer') is False and avisos(t) == [],
   '7: solo láminas que no se revisaron → no se rehace nada y no hay aviso', f"renders={len(t['renders'])} {avisos(t)}")

# 8. Terminación: en ningún camino hay más de MAX_RENDERS llamadas al renderer, ni más de 2 consultas ni 2 pagos por foto.
peor = 0
for fallan in ([], ['fase_2'], ['gantt'], NUEVAS):
    for sin_vista in ([], list(range(1, 20))):
        for gpt in ({'con_texto': NUEVAS}, {'con_texto': ['fase_2']}, {'raw': {'statusCode': 500}}, {'con_texto': []}):
            t = simular(fallan_siempre=fallan, sin_vista=sin_vista, openai_siempre=gpt)
            n = len(t['renders'])
            peor = max(peor, n)
            if t.get('error') or n > MAX_R or estado(t) not in ('listo', 'error') or len(t['consultas']) > 2 or any(v > 2 for v in t['pagos'].values()):
                ok(False, f'8: termina con fallan={fallan} sin_vista={bool(sin_vista)} gpt={gpt}',
                   f"renders={n} consultas={len(t['consultas'])} estado={estado(t)} pagos={t['pagos']} error={t.get('error')}")
ok(peor <= MAX_R, f'8: 32 combinaciones terminan; el peor caso hace {peor} llamadas al renderer (tope {MAX_R}, retoma incluida)')
t = simular(openai_siempre={'con_texto': NUEVAS})
ok(len(t['renders']) == 2 and len(t['consultas']) == 2 and all(v == 2 for v in t['pagos'].values()) and estado(t) == 'listo',
   '8: si todas tienen texto: una retoma de las 8 (cada una pagada 2 veces como máximo) y «listo» con aviso', t['pagos'])

# 9. El correo a Luis muestra los avisos de fotos, escapados.
t = simular(openai=[{'con_texto': ['fase_2']}, {'raw': {'error': {'message': '<b>caída</b> & "otra" <script>x</script>'}}}])
sin_error(t, '9')
html = (t.get('correo') or {}).get('html') or ''
ok('Fotos rehechas porque tenían texto: fase 2' in html and 'No se pudo revisar si las fotos tienen texto' in html,
   '9: el correo muestra la foto rehecha y que no se pudo revisar')
ok('&lt;b&gt;caída&lt;/b&gt; &amp;' in html and '<script>' not in html and '<b>caída' not in html, '9: el texto del error viene escapado')
t = simular(openai=[{'con_texto': []}])
html = (t.get('correo') or {}).get('html') or ''
ok(html and 'tenían texto' not in html and 'No se revisó' not in html and 'todavía pueden' not in html and 'sin texto' in html,
   '9: sin avisos, el correo solo dice que las fotos se revisaron sin texto')

# 10. Segunda versión final sobre el mismo plan (aprobar de nuevo desde «listo» o «error»): la foto rehecha se reutiliza.
t1 = simular(openai=[{'con_texto': ['fase_2']}, {'con_texto': []}])
M1 = t1['manifest']
ok(t1['pagos'].get('fase_2') == 2 and M1['fase_2']['file'] == 'fase_2.jpg', '10: la primera corrida rehízo «fase_2»', t1['pagos'])
t = simular(manifest_inicial=M1, openai=[{'con_texto': []}])
sin_error(t, '10a')
ok(len(t['renders']) == 1 and t['renders'][0]['prompts']['fase_2'].endswith(RETOMA) and t['renders'][0]['prompts']['fase_2'].count(RETOMA) == 1,
   '10a: la segunda corrida pide «fase_2» con la descripción de la retoma (RETOMA una sola vez)')
ok(t['pagos'] == {} and estado(t) == 'listo' and avisos(t) == [], '10a: no le paga ninguna foto a Higgsfield; «listo» y sin avisos', t['pagos'])
ok(all(t['renders'][0]['prompts'][s] == t1['renders'][0]['prompts'][s] for s in NUEVAS if s != 'fase_2'),
   '10a: las demás láminas van con su descripción original')
t = simular(manifest_inicial=M1, openai=[{'con_texto': ['fase_2']}])
ok(len(t['renders']) == 1 and t['pagos'] == {} and any('todavía pueden tener texto' in a and 'fase 2' in a for a in avisos(t)),
   '10b: la foto ya rehecha que sigue con texto no se rehace otra vez; se avisa', f"renders={len(t['renders'])} {avisos(t)}")
t = simular(manifest_inicial=M1, openai=[{'con_texto': ['fase_2', 'portal']}, {'con_texto': []}])
ok(len(t['renders']) == 2 and t['pagos'] == {'portal': 1} and avisos(t) == ['Fotos rehechas porque tenían texto: sigue tu proyecto'],
   '10c: otra foto con texto sí se rehace (solo esa se paga) y «fase_2» no acumula otra RETOMA',
   f"pagos={t['pagos']} {avisos(t)}")
ok(all(r['prompts']['fase_2'].count(RETOMA) == 1 for r in t['renders']), '10c: «fase_2» lleva RETOMA una sola vez en los dos renders')
CAMBIADO = cuerpo_render(True, unique_id=UID, image_briefs=[BRIEF_COVER] + [
    brief('fase_2', 'potter shaping a clay bowl on a wheel, warm studio light') if b['slide'] == 'fase_2' else b
    for b in BRIEFS_NUEVAS] + [BRIEF_CIERRE])
t = simular(manifest_inicial=M1, render=CAMBIADO, openai=[{'con_texto': []}])
ok(t['pagos'] == {'fase_2': 1} and t['renders'] and RETOMA not in t['renders'][0]['prompts']['fase_2'],
   '10d: si Luis cambió esa lámina, la descripción nueva se genera como siempre, sin RETOMA', t['pagos'])
for nombre, man in (
    ('404', {'statusCode': 404, 'body': 'Not Found'}),
    ('sin respuesta', {'error': {'message': 'ECONNRESET'}}),
    ('HTML con 200', {'statusCode': 200, 'body': '<html>error</html>'}),
    ('arreglo', {'statusCode': 200, 'body': [M1['fase_2']]}),
    ('null', {'statusCode': 200, 'body': None}),
    ('hash que no es texto', {'statusCode': 200, 'body': {'fase_2': {'file': 'fase_2.jpg', 'hash': 123}}}),
    ('entrada que no es objeto', {'statusCode': 200, 'body': {'fase_2': 'fase_2.jpg'}}),
    ('__proto__', {'statusCode': 200, 'body': json.loads('{"__proto__": {"hash": "x"}, "fase_2": null}')}),
    ('texto JSON', {'statusCode': 200, 'body': json.dumps(M1)}),
):
    t = simular(manifest_inicial=M1, manifest_previo=man, openai=[{'con_texto': []}])
    f2 = t['renders'][0]['prompts']['fase_2'] if t['renders'] else ''
    esperado = nombre == 'texto JSON'
    ok(not t.get('error') and estado(t) == 'listo' and f2.endswith(RETOMA) == esperado and t['pagos'] == ({} if esperado else {'fase_2': 1}),
       f'10e: manifest previo {nombre} → ' + ('se lee igual' if esperado else 'descripción de WordPress, sin romper'), f"{estado(t)} pagos={t['pagos']}")
ACENTOS = cuerpo_render(True, unique_id=UID, image_briefs=[BRIEF_COVER] + [
    brief('fase_2', 'barista pouring café au lait next to a sunny window in Ñuñoa, piña on the counter') if b['slide'] == 'fase_2' else b
    for b in BRIEFS_NUEVAS] + [BRIEF_CIERRE])
t1 = simular(render=ACENTOS, openai=[{'con_texto': ['fase_2']}, {'con_texto': []}])
t = simular(render=ACENTOS, manifest_inicial=t1['manifest'], openai=[{'con_texto': []}])
ok(t1['pagos'].get('fase_2') == 2 and t['pagos'] == {}, '10f: con acentos y eñes la retoma también se reutiliza (sha256 en UTF-8)', t['pagos'])
PRUEBA_SHA = JS_SHA256 + r"""
const crypto = require('node:crypto');
const casos = ['', 'a', 'abc', 'x'.repeat(55), 'x'.repeat(56), 'x'.repeat(64), 'x'.repeat(1000), 'café ñandú Ñuñoa', '\u{1F304} amanecer'];
console.log(JSON.stringify(casos.filter((c) => sha256(c) !== crypto.createHash('sha256').update(c).digest('hex')).length));
"""
r = subprocess.run(['node', '-'], input=PRUEBA_SHA, capture_output=True, text=True, encoding='utf-8')
ok(r.returncode == 0 and r.stdout.strip() == '0', '10g: el sha256 de los nodos calza con node:crypto (también fuera del ASCII)', r.stderr[-200:])

# 11. Vista previa: nunca revisa ni lee manifests; sin propuesta: se revisan también portada y cierre.
t = simular(webhook={'id': 9, 'codigo': UID, 'modo': 'draft', 'aviso': True},
            leer_render=leido(cuerpo_render(False, unique_id=UID), estado='borrador'))
ok(not t.get('error') and len(t['renders']) == 1 and t['renders'][0]['draft'] is True and t['consultas'] == []
   and t['lecturas'] == [] and t['lecturas_previas'] == [] and (t.get('vista') or {}).get('ok') is True,
   '11: la vista previa no lee manifests ni consulta a GPT-4o (no lleva fotos)', t.get('error') or t['pasos'])
t = simular(con_propuesta=False, openai=[{'con_texto': ['cover']}, {'con_texto': []}])
sin_error(t, '11 sin propuesta')
ok(t['consultas'] and slides_de(t['consultas'][0]) == TODAS and t['pagos'] == {**{s: 1 for s in TODAS}, 'cover': 2},
   '11: sin propuesta se revisan las 10 fotos y la portada con texto se rehace', t['pagos'])
ok(estado(t) == 'listo' and avisos(t) == ['Fotos rehechas porque tenían texto: portada'], '11: «listo» con el aviso de la portada', avisos(t))

print('TODO OK' if not fallas else f'{len(fallas)} FALLA(S)')
sys.exit(1 if fallas else 0)
