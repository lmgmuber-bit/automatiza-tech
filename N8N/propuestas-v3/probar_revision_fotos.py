"""Prueba local de la revisión de texto en las fotos de «3 Final» (render → GPT-4o → retoma → Verificar → correo).

Uso: python N8N/propuestas-v3/probar_revision_fotos.py   (sale con código 1 si algo falla)
No llama a OpenAI, al renderer, a WordPress ni a n8n: recorre el grafo de 3-final.json con node, como una pequeña
máquina de estados. Los nodos Code corren con su jsCode REAL (el que se publica); los HTTP, el webhook y el correo
son simulados. El renderer simulado se porta como el de verdad (renderer/src/server.js y photo-manifest.js):
reutiliza una foto solo si el sha256 de su descripción no cambió, guarda `img/<lámina>.jpg` y su manifest.json
conserva las entradas viejas aunque la foto nueva no salga.

$('Nodo').first() devuelve la ÚLTIMA pasada del nodo (como n8n sin runIndex) y lanza si el nodo no corrió;
$runIndex es cuántas veces corrió antes el nodo; $json/$input son la entrada; las expresiones ={{ … }} de los HTTP
y de los IF se evalúan igual que en n8n, así que lo que se revisa es lo que de verdad se enviaría.
"""
import ast, json, os, re, subprocess, sys

sys.stdout.reconfigure(encoding='utf-8')
AQUI = os.path.dirname(os.path.abspath(__file__))
fallas = []


def ok(cond, msg, detalle=''):
    print(('ok    ' if cond else 'FALLA ') + msg + ('' if cond or not detalle else f' ({detalle})'))
    if not cond:
        fallas.append(msg)


def constantes():
    """RETOMA, MAX_RENDERS, MAX_RENDERS_RETOMA y JS_SHA256 de build_3_final.py sin ejecutarlo (ejecutarlo reescribe el JSON)."""
    arbol = ast.parse(open(os.path.join(AQUI, 'build_3_final.py'), encoding='utf-8').read())
    valores = {}
    for n in arbol.body:
        if isinstance(n, ast.Assign) and len(n.targets) == 1 and isinstance(n.targets[0], ast.Name):
            nombre = n.targets[0].id
            if nombre in ('RETOMA', 'MAX_RENDERS', 'MAX_RENDERS_RETOMA', 'JS_SHA256'):
                valores[nombre] = ast.literal_eval(n.value)
    return valores


K = constantes()
RETOMA, MAX_R, MAX_RR = K['RETOMA'], K['MAX_RENDERS'], K['MAX_RENDERS_RETOMA']
WF = json.load(open(os.path.join(AQUI, '3-final.json'), encoding='utf-8'))
RENDER = 'https://n8n-propuesta-renderer.kchiba.easypanel.host'
UID = 'PRUEBAabc123'

SIM = r"""
const crypto = require('node:crypto');
const { wf, esc: E } = DATOS;
const sha = (s) => crypto.createHash('sha256').update(String(s)).digest('hex');
const nodos = Object.fromEntries(wf.nodes.map((n) => [n.name, n]));
const runData = {};
const T = { pasos: [], renders: [], pagos: {}, consultas: [], lecturas: [], lecturas_previas: [], preparaciones: [], decisiones: [],
            guardar: null, verificar: null, resultado: null, correo: null, error: null };
let manifest = E.manifest_inicial ? JSON.parse(JSON.stringify(E.manifest_inicial)) : {};
let llamadasOpenai = 0;

const $ = (n) => ({
  get isExecuted() { return !!(runData[n] && runData[n].length); },
  first: () => {
    if (!runData[n] || !runData[n].length) throw new Error(`Nodo sin ejecutar: ${n}`);
    const ult = runData[n][runData[n].length - 1];
    return ult[0];
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

function renderer(cuerpo) {
  const n = T.renders.length + 1;
  const fallan = (E.fallan && E.fallan[n - 1]) || E.fallan_siempre || [];
  const briefs = Array.isArray(cuerpo.image_briefs) ? cuerpo.image_briefs : [];
  T.renders.push({ n, unique_id: cuerpo.unique_id, prompts: Object.fromEntries(briefs.map((b) => [b.slide, b.prompt])) });
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
  if (!(E.sin_vista || []).includes(n)) r.view_url = `${'__RENDER__'}/p/${cuerpo.unique_id}/index.html`;
  return r;
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
    case 'Leer estado': return E.estado || { statusCode: 200, body: { id: 999, unique_id: E.uid, company_name: '[PRUEBA] Empresa', payload: E.payload } };
    case 'Render final': return renderer(cuerpo);
    case 'Leer fotos previas': {
      T.lecturas_previas.push(url);
      if (E.manifest_previo) return E.manifest_previo;
      // Como el static del renderer: sin manifest.json todavía (primera versión final), 404.
      return Object.keys(manifest).length ? { statusCode: 200, body: JSON.parse(JSON.stringify(manifest)) } : { statusCode: 404, body: 'Not Found' };
    }
    case 'Leer fotos': {
      T.lecturas.push(url);
      if (E.manifest) return E.manifest;
      return { statusCode: 200, body: Object.assign({}, manifest, E.manifest_extra || {}) };
    }
    case 'Buscar texto en fotos': return openai(cuerpo);
    case 'Ver presentación': return { statusCode: 200, body: '<html></html>' };
    case 'PDF': return { statusCode: 200, body: '' };
    case 'Probar chatbot': return { statusCode: 200, body: { output: 'Hola, soy el asistente <demo>.' } };
    case 'Guardar resultado': T.guardar = cuerpo; return { statusCode: 200, body: { ok: true } };
    default: throw new Error('HTTP sin simular: ' + nombre);
  }
}

function correr(nombre, items) {
  const n = nodos[nombre];
  const $json = items[0] ? items[0].json : {};
  const $input = { first: () => items[0], all: () => items, item: items[0] };
  const $runIndex = (runData[nombre] || []).length;
  let salidas;  // arreglo de ramas, cada una un arreglo de items
  const p = n.parameters;
  if (n.type === 'n8n-nodes-base.webhook') {
    salidas = [[{ json: { body: { id: 999 } } }]];
  } else if (n.type === 'n8n-nodes-base.code') {
    const fn = new Function('$', '$json', '$input', '$runIndex', '$execution', p.jsCode);
    salidas = [fn($, $json, $input, $runIndex, $execution)];
  } else if (n.type === 'n8n-nodes-base.httpRequest') {
    const url = evaluar(p.url, $json, $input);
    const cuerpo = p.sendBody ? JSON.parse(evaluar(p.jsonBody, $json, $input)) : undefined;
    salidas = [[{ json: http(nombre, url, cuerpo) }]];
  } else if (n.type === 'n8n-nodes-base.if') {
    const c = p.conditions.conditions[0];
    const v = evaluar(c.leftValue, $json, $input);
    const si = v === true || v === 'true';
    salidas = si ? [items, []] : [[], items];
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
  let cola = [['Webhook', [{ json: {} }]]];
  let pasos = 0;
  while (cola.length) {
    if (++pasos > 300) throw new Error('el flujo no termina (más de 300 pasos)');
    const [nombre, items] = cola.shift();
    T.pasos.push(nombre);
    const salidas = correr(nombre, items);
    const ult = runData[nombre][runData[nombre].length - 1];
    if (nombre === 'Preparar revisión de texto') T.preparaciones.push(ult[0].json);
    if (nombre === 'Decidir retoma') T.decisiones.push(ult[0].json);
    if (nombre === 'Verificar') T.verificar = ult[0].json;
    if (nombre === 'Resultado') T.resultado = ult[0].json;
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
""".replace('__RENDER__', RENDER)

# Descripciones que el filtro de fotos deja pasar tal cual (sus escenas neutras), para que la prueba mire la retoma.
PAYLOAD = {
    'company_name': '[PRUEBA] Empresa',
    'image_briefs': [
        {'slide': 'cover', 'prompt': 'close-up of warm morning light falling across a textured wooden surface with a small green plant, background completely blurred into soft golden bokeh, shallow depth of field'},
        {'slide': 'challenge', 'prompt': 'quiet empty room at night lit by a single warm lamp, rain drops on a large window, calm pensive mood'},
        {'slide': 'solution', 'prompt': 'bright calm space with natural light through tall windows and a green plant, soft morning light'},
        {'slide': 'benefits', 'prompt': 'wide open landscape of the Chilean central valley at sunrise with the Andes in the distance, soft golden light'},
        {'slide': 'how_it_works', 'prompt': 'close-up of hands arranging small wooden blocks in a neat row on a light table, soft daylight, shallow depth of field'},
        {'slide': 'pricing', 'prompt': 'close-up of a single green sprout growing from rich dark soil in soft morning light, shallow depth of field'},
        {'slide': 'next_steps', 'prompt': 'open country road through green hills at sunrise, soft mist, hopeful calm atmosphere'},
    ],
}
SLIDES = [b['slide'] for b in PAYLOAD['image_briefs']]
URL_SEGURA = re.compile(r'^' + re.escape(RENDER) + r'/p/' + re.escape(UID) + r'/img/[A-Za-z0-9_-]+\.[A-Za-z0-9]{2,5}(\?v=\d+)?$')


def simular(**esc):
    esc.setdefault('uid', UID)
    esc.setdefault('payload', PAYLOAD)
    prog = 'const DATOS = ' + json.dumps({'wf': WF, 'esc': esc}, ensure_ascii=False) + ';\n' + SIM
    # Por la entrada estándar: el flujo entero no cabe en la línea de comandos de Windows (32 KB).
    r = subprocess.run(['node', '-'], input=prog, capture_output=True, text=True, encoding='utf-8')
    if r.returncode != 0:
        return {'error': (r.stderr.strip().splitlines() or ['node sin mensaje'])[-1]}
    return json.loads(r.stdout)


def urls(consulta):
    return [c['image_url']['url'] for c in consulta['messages'][0]['content'] if c.get('type') == 'image_url']


def slides_de(consulta):
    return [u.rsplit('/', 1)[1].split('.')[0] for u in urls(consulta)]


def avisos(t):
    return (t.get('verificar') or {}).get('avisos_fotos') or []


def estado(t):
    return (t.get('guardar') or {}).get('status')


def sin_error(t, caso):
    ok(not t.get('error') and t.get('resultado') and t.get('correo'), f'{caso}: el recorrido llega al correo sin lanzar',
       t.get('error') or 'no llegó al correo')


# 1. Render completo y GPT-4o dice [] → no se rehace nada, sin avisos de fotos.
t = simular(openai=[{'con_texto': []}])
sin_error(t, '1')
ok(len(t['renders']) == 1, '1: un solo render', f"renders={len(t['renders'])}")
ok(len(t['consultas']) == 1 and sorted(slides_de(t['consultas'][0])) == sorted(SLIDES),
   '1: una sola consulta a GPT-4o con las 7 fotos', str([slides_de(c) for c in t['consultas']]))
ok(avisos(t) == [], '1: sin avisos de fotos', str(avisos(t)))
ok(estado(t) == 'lista' and t['verificar']['ok'] is True, '1: la propuesta queda «lista»', str(t.get('guardar')))
ok(all(v == 1 for v in t['pagos'].values()) and len(t['pagos']) == 7, '1: cada foto se pagó una vez', str(t['pagos']))
c0 = t['consultas'][0] if t['consultas'] else {}
ok(c0.get('model') == 'gpt-4o' and c0.get('response_format') == {'type': 'json_object'} and c0.get('temperature') == 0,
   '1: la consulta es gpt-4o, temperatura 0 y JSON', str({k: c0.get(k) for k in ('model', 'temperature', 'response_format')}))
ok(all(URL_SEGURA.match(u) for c in t['consultas'] for u in urls(c)), '1: todas las URL de la consulta son del renderer y seguras',
   str([u for c in t['consultas'] for u in urls(c)]))

# 2. Ronda 0 con ['solution'] → se rehace solo esa foto, con RETOMA una sola vez; las demás idénticas.
t = simular(openai=[{'con_texto': ['solution']}, {'con_texto': []}])
sin_error(t, '2')
ok(len(t['renders']) == 2, '2: dos renders (el normal y la retoma)', f"renders={len(t['renders'])}")
if len(t['renders']) == 2:
    p0, p1 = t['renders'][0]['prompts'], t['renders'][1]['prompts']
    ok(p1['solution'] == p0['solution'] + RETOMA, '2: la foto con texto se pide con la descripción + RETOMA', p1['solution'][-120:])
    ok(p1['solution'].count(RETOMA) == 1, '2: RETOMA agregada una sola vez')
    ok(all(p1[s] == p0[s] for s in SLIDES if s != 'solution') and set(p1) == set(p0),
       '2: las demás descripciones son idénticas (mismo hash, el renderer las reutiliza)')
ok(t['pagos'] == {**{s: 1 for s in SLIDES}, 'solution': 2}, '2: solo la foto rehecha se paga de nuevo', str(t['pagos']))
ok(t['decisiones'] and t['decisiones'][0].get('rehacer') is True and t['decisiones'][0].get('fotos_retomadas') == ['solution'],
   '2: «Decidir retoma» pide rehacer solo «solution»', str(t['decisiones'][:1])[:300])
ok(len(t['consultas']) == 2 and t['preparaciones'][1]['ronda'] == 1, '2: la segunda revisión es la ronda 1')
if len(t['consultas']) == 2:
    u0 = dict(zip(slides_de(t['consultas'][0]), urls(t['consultas'][0])))
    u1 = dict(zip(slides_de(t['consultas'][1]), urls(t['consultas'][1])))
    ok(u0.get('solution') != u1.get('solution'),
       '2: la ronda 1 pide la foto rehecha con otra URL (mismo archivo sobrescrito: que no se lea una copia guardada)',
       f"{u0.get('solution')} vs {u1.get('solution')}")
ok(estado(t) == 'lista' and avisos(t) == ['Fotos rehechas porque tenían texto: solución'],
   '2: «lista», con aviso de la foto rehecha', str(avisos(t)))

# 2b. La retoma sigue con SUS descripciones y respeta su tope propio (la foto rehecha no sale nunca).
t = simular(openai=[{'con_texto': ['solution']}], fallan=[[], ['solution'], ['solution'], ['solution'], ['solution']])
sin_error(t, '2b')
ok(len(t['renders']) == 1 + MAX_RR, f'2b: la retoma para en su tope ({MAX_RR} renders más)', f"renders={len(t['renders'])}")
ok(all(r['prompts']['solution'].endswith(RETOMA) and r['prompts']['solution'].count(RETOMA) == 1 for r in t['renders'][1:]),
   '2b: cada reintento de la retoma usa la descripción de la retoma, no la original')
ok(len(t['consultas']) == 2 and 'solution' not in slides_de(t['consultas'][1]),
   '2b: la ronda 1 no revisa la foto que no salió (el manifest guarda la vieja, con texto)',
   str([slides_de(c) for c in t['consultas']]))
ok(estado(t) == 'error' and any('solution' in p for p in t['verificar']['problemas']),
   '2b: la foto que no salió deja la propuesta en «error»', str(t.get('verificar', {}).get('problemas')))

# 2c. El render normal gasta su tope (falta una foto) y la retoma igual tiene el suyo.
t = simular(openai=[{'con_texto': ['solution']}], fallan_siempre=['pricing'])
sin_error(t, '2c')
ok(len(t['renders']) == MAX_R + MAX_RR, f'2c: {MAX_R} renders normales + {MAX_RR} de la retoma', f"renders={len(t['renders'])}")
ok(all(r['prompts']['pricing'] == t['renders'][0]['prompts']['pricing'] for r in t['renders']),
   '2c: la retoma no cambia la descripción de las otras fotos')

# 3. Ronda 1 que todavía ve texto → no se rehace de nuevo; Verificar avisa las dos cosas, en español.
t = simular(openai=[{'con_texto': ['solution', 'how_it_works']}, {'con_texto': ['how_it_works']}])
sin_error(t, '3')
ok(len(t['renders']) == 2, '3: no hay un tercer render', f"renders={len(t['renders'])}")
ok(len(t['decisiones']) == 2 and t['decisiones'][1].get('rehacer') is False, '3: la ronda 1 no pide rehacer')
av = avisos(t)
ok(any(a.startswith('Fotos rehechas porque tenían texto: ') and 'solución' in a and 'cómo funciona' in a for a in av),
   '3: avisa «rehechas» con los nombres en español', str(av))
ok(any('todavía pueden tener texto' in a and 'cómo funciona' in a for a in av), '3: avisa «todavía pueden tener texto»', str(av))
ok(not any(re.search(r'\b(solution|how_it_works)\b', a) for a in av), '3: ningún nombre en inglés en los avisos', str(av))
ok(estado(t) == 'lista' and 'todavía pueden tener texto' in t['guardar']['note'], '3: sigue «lista» y la nota lo dice')

# 4. OpenAI con error HTTP, sin respuesta o con JSON ilegible → «No se pudo revisar» y sigue «lista».
for nombre, resp, pista in (
    ('HTTP 500', {'raw': {'statusCode': 500, 'body': {'error': {'message': 'server'}}}}, 'HTTP 500'),
    ('HTTP 429', {'raw': {'statusCode': 429, 'body': 'rate limit'}}, 'HTTP 429'),
    ('sin respuesta (timeout)', {'raw': {'error': {'message': 'timeout of 120000ms exceeded'}}}, 'no respondió'),
    ('sin respuesta y sin mensaje', {'raw': {}}, 'no respondió'),
    ('contenido que no es JSON', {'contenido': 'No veo texto.'}, 'ilegible'),
    ('JSON sin con_texto', {'contenido': '{"fotos": []}'}, 'ilegible'),
    ('con_texto que no es lista', {'contenido': '{"con_texto": "solution"}'}, 'ilegible'),
    ('null', {'contenido': 'null'}, 'ilegible'),
    ('200 con cuerpo de texto', {'raw': {'statusCode': 200, 'body': '<html>proxy</html>'}}, 'ilegible'),
    ('200 sin choices', {'raw': {'statusCode': 200, 'body': {'choices': []}}}, 'ilegible'),
):
    t = simular(openai=[resp])
    sin_error(t, f'4 {nombre}')
    av = avisos(t)
    ok(estado(t) == 'lista' and len(t['renders']) == 1 and any(a.startswith('No se pudo revisar') and pista in a for a in av),
       f'4: OpenAI {nombre} → «No se pudo revisar» y sigue «lista»', f'{estado(t)} {av}')
t = simular(openai=[{'con_texto': ['solution']}, {'raw': {'statusCode': 503, 'body': ''}}])
av = avisos(t)
ok(estado(t) == 'lista' and any(a.startswith('Fotos rehechas') for a in av) and any('No se pudo revisar' in a for a in av),
   '4: falla de OpenAI en la ronda 1 → avisa la retoma y que no se pudo revisar', str(av))

# 5. manifest.json que no se puede leer → no se consulta a OpenAI y Verificar lo avisa.
for nombre, man in (
    ('404', {'statusCode': 404, 'body': 'Not Found'}),
    ('HTML con 200', {'statusCode': 200, 'body': '<html>error</html>'}),
    ('arreglo', {'statusCode': 200, 'body': [{'file': 'cover.jpg'}]}),
    ('null', {'statusCode': 200, 'body': None}),
    ('sin respuesta', {'error': {'message': 'ECONNRESET'}}),
):
    t = simular(manifest=man)
    sin_error(t, f'5 {nombre}')
    av = avisos(t)
    ok(t['consultas'] == [] and estado(t) == 'lista'
       and any(a.startswith('No se revisó si las fotos tienen texto: no se pudo leer la lista de fotos') for a in av),
       f'5: manifest {nombre} → sin consulta a OpenAI y con aviso', f'{len(t["consultas"])} consultas, {av}')
t = simular(manifest={'statusCode': 200, 'body': json.dumps({'cover': {'file': 'cover.jpg', 'hash': 'x'}})})
ok(len(t['consultas']) == 1 and slides_de(t['consultas'][0]) == ['cover'], '5: manifest que llega como texto JSON se lee igual',
   str([slides_de(c) for c in t['consultas']]))
t = simular(sin_vista=list(range(1, 10)))
ok(t['consultas'] == [] and estado(t) == 'error' and len(t['renders']) == MAX_R,
   '5: sin presentación no se revisan fotos (el manifest puede ser de una versión anterior)',
   f"{len(t['consultas'])} consultas, renders={len(t['renders'])}, {estado(t)}")
t = simular(manifest={'statusCode': 200, 'body': {}})
ok(t['consultas'] == [] and any('no hay fotos guardadas' in a for a in avisos(t)), '5: manifest vacío → «no hay fotos guardadas»',
   str(avisos(t)))

# 6. Nombres de archivo raros, unique_id inválido o láminas que no se pidieron → nunca entran a la URL de la consulta.
RAROS = {
    'cover': {'file': '../../etc/passwd', 'hash': 'x'},
    'challenge': {'file': 'a/b.jpg', 'hash': 'x'},
    'solution': {'file': 'x\\y.jpg', 'hash': 'x'},
    'benefits': {'file': '..', 'hash': 'x'},
    'how_it_works': {'file': '.htaccess', 'hash': 'x'},
    'pricing': {'file': 'foto con espacio.jpg', 'hash': 'x'},
    'next_steps': {'file': 'next_steps.jpg?x=1', 'hash': 'x'},
    'extra_1': {'file': 'extra_1.jpg', 'hash': 'x'},
    'extra_9': {'file': 'extra_9.jpg', 'hash': 'x'},
    '__proto__': {'file': 'proto.jpg', 'hash': 'x'},
}
t = simular(manifest={'statusCode': 200, 'body': RAROS})
sin_error(t, '6 raros')
ok(t['consultas'] == [] and any('no hay fotos guardadas' in a for a in avisos(t)),
   '6: ningún nombre raro ni lámina no pedida entra a la consulta', str([urls(c) for c in t['consultas']]))
for malo in ('../../x.jpg', 'a/b', 'cover.jpg%2F..'):
    raros = dict(RAROS, cover={'file': malo, 'hash': 'x'}, pricing={'file': 'pricing.jpg', 'hash': 'x'})
    t = simular(manifest={'statusCode': 200, 'body': raros})
    todas = [u for c in t['consultas'] for u in urls(c)]
    ok(len(todas) == 1 and URL_SEGURA.match(todas[0]) and '/img/pricing.jpg?' in todas[0],
       f'6: con cover={malo!r} solo entra la foto válida (pricing)', str(todas))
extra = {'extra_9': {'file': 'extra_9.jpg', 'hash': 'x'}, 'otra': {'file': 'otra.jpg', 'hash': 'x'}, 'cover2': 'cover.jpg'}
t = simular(manifest_extra=extra)
ok(len(t['consultas']) == 1 and sorted(slides_de(t['consultas'][0])) == sorted(SLIDES),
   '6: láminas del manifest que no se pidieron quedan fuera', str([slides_de(c) for c in t['consultas']]))
for uid_malo in ('../x', 'ab', 'con espacio12', 'a' * 41, 'abc/def123', ''):
    t = simular(uid=uid_malo)
    sin_error(t, f'6 uid {uid_malo!r}')
    ok(t['consultas'] == [] and any(a.startswith('No se revisó si las fotos tienen texto') for a in avisos(t)),
       f'6: unique_id inválido {uid_malo!r} → no se consulta a OpenAI', f'{len(t["consultas"])} consultas, {avisos(t)}')
t = simular(openai=[{'con_texto': []}])
ok(all(URL_SEGURA.match(u) for c in t['consultas'] for u in urls(c)), '6: con datos normales, todas las URL calzan con la forma segura')

# 7. El modelo devuelve láminas que no existen o repetidas → se ignoran / se deduplican.
t = simular(openai=[{'con_texto': ['solution', 'solution', 'inventada', 'cover', '../cover', 3, None, 'cover']}, {'con_texto': []}])
sin_error(t, '7')
ok(t['decisiones'] and t['decisiones'][0].get('fotos_retomadas') == ['solution', 'cover'],
   '7: repetidas se deduplican y las que no existen se ignoran', str(t['decisiones'][:1])[:200])
if len(t['renders']) == 2:
    p0, p1 = t['renders'][0]['prompts'], t['renders'][1]['prompts']
    ok(p1['solution'].count(RETOMA) == 1 and p1['cover'].count(RETOMA) == 1, '7: RETOMA una sola vez aunque venga repetida')
    ok(all(p1[s] == p0[s] for s in SLIDES if s not in ('solution', 'cover')), '7: el resto sin tocar')
t = simular(openai=[{'con_texto': ['inventada', 'extra_1']}])
ok(len(t['renders']) == 1 and t['decisiones'][0].get('rehacer') is False and avisos(t) == [],
   '7: solo láminas inexistentes → no se rehace nada y no hay aviso', f"renders={len(t['renders'])} {avisos(t)}")
# Pedida pero no revisada (no quedó en el manifest): el modelo no puede hacerla rehacer.
t = simular(openai=[{'con_texto': ['pricing']}], fallan_siempre=['pricing'])
ok(t['decisiones'] and t['decisiones'][0].get('rehacer') is False and len(t['renders']) == MAX_R,
   '7: una lámina que no se le mostró al modelo no se rehace', f"renders={len(t['renders'])}")

# 8. Terminación: en ningún camino hay más de MAX_RENDERS + MAX_RENDERS_RETOMA llamadas al renderer.
TOPE = MAX_R + MAX_RR
peor = 0
for fallan in ([], ['solution'], ['pricing'], SLIDES):
    for sin_vista in ([], list(range(1, 20))):
        for gpt in ({'con_texto': SLIDES}, {'con_texto': ['solution']}, {'raw': {'statusCode': 500}}, {'con_texto': []}):
            t = simular(fallan_siempre=fallan, sin_vista=sin_vista, openai_siempre=gpt)
            n = len(t['renders'])
            peor = max(peor, n)
            bien = (not t.get('error') and n <= TOPE and estado(t) in ('lista', 'error') and len(t['consultas']) <= 2
                    and all(v <= 2 for v in t['pagos'].values()))
            if not bien:
                ok(False, f'8: termina con fallan={fallan} sin_vista={bool(sin_vista)} gpt={gpt}',
                   f"renders={n} consultas={len(t['consultas'])} estado={estado(t)} pagos={t['pagos']} error={t.get('error')}")
ok(peor <= TOPE, f'8: 32 combinaciones terminan; el peor caso hace {peor} llamadas al renderer (tope {TOPE})')
t = simular(fallan_siempre=['solution'], openai_siempre={'con_texto': SLIDES})
ok(len(t['renders']) == TOPE and estado(t) == 'error', f'8: el peor caso llega justo a {TOPE} renders y termina en «error»',
   f"renders={len(t['renders'])} estado={estado(t)}")
t = simular(estado={'statusCode': 404, 'body': 'no existe'})
ok(not t.get('error') and t['renders'] == [] and estado(t) == 'error' and t.get('correo'),
   '8: sin estado de WordPress no se llama al renderer y se avisa el error', t.get('error') or str(t.get('guardar')))

# 9. El correo a Luis (nodo «Resultado») muestra los avisos de fotos, escapados.
t = simular(openai=[{'con_texto': ['solution']}, {'raw': {'error': {'message': '<b>caída</b> & "otra" <script>x</script>'}}}])
sin_error(t, '9')
html = (t.get('correo') or {}).get('html') or ''
ok('Fotos rehechas porque tenían texto: solución' in html, '9: el correo muestra la foto rehecha')
ok('No se pudo revisar si las fotos tienen texto' in html, '9: el correo muestra que no se pudo revisar')
ok('&lt;b&gt;caída&lt;/b&gt; &amp;' in html and '<script>' not in html and '<b>caída' not in html,
   '9: el texto del error viene escapado en el correo', re.search(r'No se pudo revisar[^<]{0,160}', html).group(0) if 'No se pudo revisar' in html else '')
ok('⚠️' in html, '9: los avisos llevan su marca ⚠️')
t = simular(openai=[{'con_texto': []}])
html = (t.get('correo') or {}).get('html') or ''
ok(html and 'tienen texto' not in html and 'tenían texto' not in html and 'No se revisó' not in html,
   '9: sin avisos, el correo no menciona texto en las fotos')

# 10. Segunda corrida de «3 Final» sobre el mismo unique_id (Destrabar y reintentar, o Pedir cambios y Aprobar sin
# tocar la lámina): la foto rehecha por texto se reutiliza, no se vuelve a pagar ni se reemplaza (revisión 2026-09-27).
t1 = simular(openai=[{'con_texto': ['solution']}, {'con_texto': []}])
ok(t1['pagos'].get('solution') == 2 and t1['manifest']['solution']['file'] == 'solution.jpg', '10: la primera corrida rehízo «solution»',
   str(t1['pagos']))
M1 = t1['manifest']
t = simular(manifest_inicial=M1, openai=[{'con_texto': []}])
sin_error(t, '10a')
ok(t['lecturas_previas'] == [f'{RENDER}/p/{UID}/img/manifest.json'], '10a: lee el manifest del renderer antes de renderizar',
   str(t['lecturas_previas']))
ok(len(t['renders']) == 1 and t['renders'][0]['prompts']['solution'].endswith(RETOMA)
   and t['renders'][0]['prompts']['solution'].count(RETOMA) == 1,
   '10a: la segunda corrida pide «solution» con la descripción de la retoma (RETOMA una sola vez)',
   t['renders'][0]['prompts']['solution'][-80:] if t['renders'] else 'sin render')
ok(t['pagos'] == {}, '10a: la segunda corrida no le paga a Higgsfield ninguna foto', str(t['pagos']))
ok(t['renders'] and all(t['renders'][0]['prompts'][s] == t1['renders'][0]['prompts'][s] for s in SLIDES if s != 'solution'),
   '10a: las demás láminas van con su descripción original')
ok(estado(t) == 'lista' and avisos(t) == [], '10a: «lista» y sin avisos de fotos', f'{estado(t)} {avisos(t)}')
# La foto ya rehecha sigue con texto: no se paga otra retoma, solo se avisa.
t = simular(manifest_inicial=M1, openai=[{'con_texto': ['solution']}])
sin_error(t, '10b')
ok(len(t['renders']) == 1 and t['pagos'] == {} and t['decisiones'] and t['decisiones'][0].get('rehacer') is False,
   '10b: la foto rehecha antes que sigue con texto no se rehace otra vez', f"renders={len(t['renders'])} pagos={t['pagos']}")
ok(any('todavía pueden tener texto' in a and 'solución' in a for a in avisos(t)), '10b: se avisa en el correo', str(avisos(t)))
# Otra foto con texto sí se rehace, una vez; la rehecha antes queda intacta.
t = simular(manifest_inicial=M1, openai=[{'con_texto': ['solution', 'pricing']}, {'con_texto': []}])
sin_error(t, '10c')
ok(len(t['renders']) == 2 and t['pagos'] == {'pricing': 1}, '10c: solo «pricing» se rehace y se paga',
   f"renders={len(t['renders'])} pagos={t['pagos']}")
ok(t['decisiones'] and t['decisiones'][0].get('fotos_retomadas') == ['pricing'], '10c: la retoma nombra solo «pricing»',
   str(t['decisiones'][:1])[:200])
ok(all(r['prompts']['solution'].count(RETOMA) == 1 for r in t['renders']), '10c: «solution» no acumula otra RETOMA')
ok(avisos(t) == ['Fotos rehechas porque tenían texto: inversión'], '10c: el aviso nombra solo la rehecha en esta corrida', str(avisos(t)))
# «2 Cambios» cambió esa lámina: la descripción nueva no calza con la retoma y se genera como siempre.
PAYLOAD_CAMBIADO = dict(PAYLOAD, image_briefs=[dict(b, prompt='potter shaping a clay bowl on a wheel, warm studio light')
                                                if b['slide'] == 'solution' else b for b in PAYLOAD['image_briefs']])
t = simular(manifest_inicial=M1, payload=PAYLOAD_CAMBIADO, openai=[{'con_texto': []}])
sin_error(t, '10d')
ok(t['pagos'] == {'solution': 1} and t['renders'] and RETOMA not in t['renders'][0]['prompts']['solution'],
   '10d: la lámina cambiada se genera con su descripción nueva, sin RETOMA', str(t['pagos']))
# Manifest previo ilegible, raro o caído: se piden las descripciones de WordPress (la foto se rehace, sin romper nada).
for nombre, man in (
    ('404', {'statusCode': 404, 'body': 'Not Found'}),
    ('sin respuesta', {'error': {'message': 'ECONNRESET'}}),
    ('HTML con 200', {'statusCode': 200, 'body': '<html>error</html>'}),
    ('arreglo', {'statusCode': 200, 'body': [M1['solution']]}),
    ('null', {'statusCode': 200, 'body': None}),
    ('hash que no es texto', {'statusCode': 200, 'body': {'solution': {'file': 'solution.jpg', 'hash': 123}}}),
    ('entrada que no es objeto', {'statusCode': 200, 'body': {'solution': 'solution.jpg'}}),
    ('__proto__', {'statusCode': 200, 'body': json.loads('{"__proto__": {"hash": "x"}, "solution": null}')}),
    ('texto JSON', {'statusCode': 200, 'body': json.dumps(M1)}),
):
    t = simular(manifest_inicial=M1, manifest_previo=man, openai=[{'con_texto': []}])
    sin_error(t, f'10e {nombre}')
    sol = t['renders'][0]['prompts']['solution'] if t['renders'] else ''
    esperado = nombre == 'texto JSON'
    ok(estado(t) == 'lista' and sol.endswith(RETOMA) == esperado and t['pagos'] == ({} if esperado else {'solution': 1}),
       f'10e: manifest previo {nombre} → ' + ('se lee igual' if esperado else 'descripción original, sin romper'),
       f'{estado(t)} pagos={t["pagos"]}')
# Descripción con acentos y eñes: el sha256 del nodo tiene que calzar con el del renderer (UTF-8).
PAYLOAD_ACENTOS = dict(PAYLOAD, image_briefs=[dict(b, prompt='barista pouring café au lait next to a sunny window in Ñuñoa, piña on the counter')
                                               if b['slide'] == 'solution' else b for b in PAYLOAD['image_briefs']])
t1 = simular(payload=PAYLOAD_ACENTOS, openai=[{'con_texto': ['solution']}, {'con_texto': []}])
t = simular(payload=PAYLOAD_ACENTOS, manifest_inicial=t1['manifest'], openai=[{'con_texto': []}])
ok(t1['pagos'].get('solution') == 2 and t['pagos'] == {}, '10f: con acentos y eñes la retoma también se reutiliza', str(t['pagos']))
# El sha256 en JavaScript puro de «Revisar fotos» calza con node:crypto en los bordes del relleno y fuera del ASCII.
PRUEBA_SHA = K['JS_SHA256'] + r"""
const crypto = require('node:crypto');
const casos = ['', 'a', 'abc', 'x'.repeat(55), 'x'.repeat(56), 'x'.repeat(63), 'x'.repeat(64), 'x'.repeat(65),
  'x'.repeat(1000), 'café ñandú Ñuñoa', '\u{1F304} amanecer \u{1F304}', 'ab\uD800cd', 'z\uDC00', 'x'.repeat(119) + 'é'];
const malos = casos.filter((c) => sha256(c) !== crypto.createHash('sha256').update(c).digest('hex'));
console.log(JSON.stringify({ total: casos.length, malos: malos.map((c) => c.slice(0, 20) + ' (' + c.length + ')') }));
"""
r = subprocess.run(['node', '-'], input=PRUEBA_SHA, capture_output=True, text=True, encoding='utf-8')
res = json.loads(r.stdout) if r.returncode == 0 else {'malos': [r.stderr[-200:]]}
ok(res['malos'] == [], f"10g: sha256 del nodo = node:crypto en {res.get('total')} textos", str(res['malos']))
w_revisar = next(n for n in WF['nodes'] if n['name'] == 'Revisar fotos')['parameters']['jsCode']
ok(K['JS_SHA256'] in w_revisar and json.dumps(RETOMA) in w_revisar, '10g: el nodo publicado lleva ese sha256 y la RETOMA vigente')

print('TODO OK' if not fallas else f'{len(fallas)} FALLA(S)')
sys.exit(1 if fallas else 0)
