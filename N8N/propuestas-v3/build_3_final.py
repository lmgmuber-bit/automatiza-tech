"""Construye el workflow n8n «Propuestas v3 · 3 Final» y lo guarda en 3-final.json.

Plan: Docs/superpowers/plans/2026-09-23-flujo-propuestas-v3.md, Task 12.
Lo llama el panel («Aprobar y generar versión final») con {id} y X-AT-Secret, después de pasar la
propuesta a «generando». Genera las fotos (gasto: Soul 2, US$0,0032 c/u de lista), las guarda junto a la
presentación, renderiza y verifica presentación, PDF y chatbot. Termina SIEMPRE en «lista» o «error»
(generando→lista|error): ningún paso que pueda fallar deja la propuesta trabada en «generando».
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os
from fotos_guard import JS_LIMPIAR_FOTOS
from correos import correo_final

CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}  # la misma de «1 Borrador»
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
CRED_RENDER = {'httpHeaderAuth': {'id': 'fj2orzbsjlnHaiLd', 'name': 'X-AT-Render-Key'}}  # clave de /render del renderer
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
RENDERER = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/render'
CHAT = 'https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat'
LUIS = 'lmgm.0303@gmail.com'
# Nunca enlazar *.easypanel.host en un correo: el SMTP de Hostinger lo rechaza (554 5.7.1, 2026-09-24).
VER = 'https://automatizatech.cl/ver-presentacion.php?id='
PANEL = 'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-proposals&edit_id='
FULL = {'response': {'response': {'fullResponse': True, 'neverError': True}}}

CODE_VERIFICAR = r"""// Junta las verificaciones. Nada aquí lanza: el resultado decide «lista» o «error».
const est = $('Leer estado').first().json.body;
// Sin runIndex, $('Render final') devuelve la ÚLTIMA pasada del bucle de reintentos.
const r = $('Render final').first().json || {};
const img = r.images || {};
const renders = $('¿Reintentar render?').first().json.intento;
const problemas = [];
if (!r.view_url) problemas.push('el renderer no devolvió la presentación' + (r.error ? ` (${r.error.message || r.error})` : ''));
if ((img.missing || []).length) problemas.push(`fotos que no se generaron tras ${renders} intentos: ${img.missing.join(', ')}`);
if ((img.kept_remote || []).length) problemas.push(`fotos que no se pudieron guardar en local: ${img.kept_remote.join(', ')}`);
const pres = $('Ver presentación').first().json;
if (pres.statusCode !== 200) problemas.push(`ver-presentacion respondió ${pres.statusCode}`);
const pdf = $('PDF').first().json;
if (pdf.statusCode !== 200) problemas.push(`el PDF respondió ${pdf.statusCode}`);
const chat = $('Probar chatbot').first().json;
const respuesta = chat.body && chat.body.output ? String(chat.body.output) : '';
if (chat.statusCode !== 200 || !respuesta) problemas.push(`el chatbot de demo no respondió (HTTP ${chat.statusCode})`);
const ok = problemas.length === 0;
// Revisión de texto en las fotos: nunca pasa la propuesta a «error», solo avisa (Luis la revisa antes de enviarla).
const NOMBRES = { cover: 'portada', challenge: 'desafío', solution: 'solución', benefits: 'beneficios',
  how_it_works: 'cómo funciona', extra_1: 'extra 1', extra_2: 'extra 2', pricing: 'inversión', next_steps: 'próximos pasos' };
const nombres = (l) => l.map((s) => NOMBRES[s] || s).join(', ');
const prep = $('Preparar revisión de texto').isExecuted ? $('Preparar revisión de texto').first().json : null;
const qa = $('Decidir retoma').isExecuted ? $('Decidir retoma').first().json : null;
const avisos_fotos = [];
const retomadas = prep && prep.vigente && Array.isArray(prep.vigente.fotos_retomadas) ? prep.vigente.fotos_retomadas : [];
if (retomadas.length) avisos_fotos.push('Fotos rehechas porque tenían texto: ' + nombres(retomadas));
if (!prep) avisos_fotos.push('No se revisó si las fotos tienen texto');
else if (!prep.revisar) avisos_fotos.push('No se revisó si las fotos tienen texto: ' + prep.motivo_sin_revision);
else if (qa && !qa.rehacer && qa.revision_fallo) avisos_fotos.push('No se pudo revisar si las fotos tienen texto (' + qa.revision_fallo + ')');
else if (qa && !qa.rehacer && qa.fotos_con_texto.length) avisos_fotos.push('Fotos que todavía pueden tener texto (revísalas o pide cambios): ' + nombres(qa.fotos_con_texto));
return [{ json: {
  ok, problemas, avisos_fotos, id: est.id, unique_id: est.unique_id, company: est.company_name,
  fotos_locales: img.stored_local || 0, fotos_pedidas: img.requested || 0,
  chat_respuesta: respuesta.slice(0, 300),
  note: ok ? `Verificada: presentación, PDF, ${img.stored_local || 0} fotos locales (${renders} render${renders === 1 ? '' : 's'}) y chatbot OK` + (avisos_fotos.length ? ' · ' + avisos_fotos.join(' · ') : '') : problemas.join(' · '),
} }];"""

CODE_REVISAR = r"""
// Último filtro antes de gastar: no guarda nada, solo decide qué fotos se le piden al renderer.
// Solo láminas que existen y una foto por lámina: Cambios puede dejar briefs de extras borradas o repetidos,
// y cada uno se paga (revisión final 2026-09-24, M6). Nunca lanza: sin payload el renderer contesta 400 y
// Verificar deja la propuesta en «error» en vez de trabada en «generando» (I1).
const e = $('Leer estado').first().json.body || {};
const p = e.payload && typeof e.payload === 'object' ? e.payload : {};
const extras = Math.min(Array.isArray(p.extra_slides) ? p.extra_slides.length : 0, 2);
const validas = new Set(['cover', 'challenge', 'solution', 'benefits', 'how_it_works', 'pricing', 'next_steps',
  ...Array.from({ length: extras }, (_, i) => `extra_${i + 1}`)]);
const vistas = new Set();
const briefs = (Array.isArray(p.image_briefs) ? p.image_briefs : [])
  .filter((b) => b && validas.has(b.slide) && !vistas.has(b.slide) && vistas.add(b.slide));
const r = limpiarFotos(briefs);
return [{ json: { unique_id: e.unique_id, payload: Object.assign({}, p, { image_briefs: r.limpias }), fotos_reemplazadas: r.reemplazadas } }];"""

MAX_RENDERS = 3
MAX_RENDERS_RETOMA = 2
CODE_REINTENTAR = r"""// Tras cada render: si faltan fotos o no hubo presentación, se vuelve a llamar al renderer.
// El renderer reutiliza las fotos ya guardadas con el mismo prompt (manifest.json) y solo pide las que faltan,
// así que un reintento no vuelve a pagar las que ya salieron. Tope: MAX_RENDERS llamadas; la ronda de fotos
// rehechas por texto («Decidir retoma») tiene su propio tope y sigue con SUS descripciones, no con las originales
// (con las originales, el renderer volvería a pedir la foto con texto).
const MAX_RENDERS = __MAX__;
const MAX_RENDERS_RETOMA = __MAXR__;
const r = $json || {};
const faltan = !r.view_url || ((r.images && r.images.missing) || []).length > 0;
const intento = $runIndex + 1;
const retoma = $('Decidir retoma').isExecuted && $('Decidir retoma').first().json.rehacer === true;
const base = retoma ? $('Decidir retoma').first().json : $('Revisar fotos').first().json;
const previos = retoma ? (base.renders_previos || 0) : 0;
const tope = retoma ? MAX_RENDERS_RETOMA : MAX_RENDERS;
return [{ json: Object.assign({}, base, { reintentar: faltan && (intento - previos) < tope, intento }) }];""".replace(
    '__MAX__', str(MAX_RENDERS)).replace('__MAXR__', str(MAX_RENDERS_RETOMA))

# Revisión de texto en las fotos (2026-09-26). En la propuesta 53 el modelo de imagen inventó leyendas blancas
# como subtítulos de película y hojas con garabatos, aunque el prompt dice «no text». GPT-4o mira las fotos
# guardadas; las que tengan texto se piden UNA vez más con otra descripción y lo que siga con texto se avisa
# en el correo (no pasa la propuesta a «error»: Luis la revisa antes de enviarla). Gasto por versión final:
# una consulta de GPT-4o con las fotos (~US$0,03) y US$0,0032 por foto rehecha.
INSTRUCCION_TEXTO = ('You are checking photos for a sales presentation. Report every photo in which ANY written '
                     'characters are visible: letters, words, numbers, subtitles or captions over the image, signs, '
                     'labels, stickers, papers or screens with writing, or scribbles that look like writing, even '
                     'if small, blurred or illegible. Ignore plain shapes, patterns and wood grain. Answer ONLY '
                     'with JSON {"con_texto": ["<photo name>", ...]} using the photo names given; use [] if no '
                     'photo has text.')
RETOMA = (', clean photograph with no written characters anywhere in the frame: no subtitles, no captions, '
          'no signs, no papers with writing or drawings')

CODE_PREPARAR_TEXTO = r"""// Arma UNA consulta a GPT-4o con las fotos pedidas que quedaron guardadas (img/manifest.json del renderer).
// Nunca lanza: sin lista de fotos no se revisa y Verificar lo avisa.
const vig = $('¿Reintentar render?').first().json;
const uid = String(vig.unique_id || '');
const pedidas = new Set(((vig.payload && vig.payload.image_briefs) || []).map((b) => b && b.slide).filter(Boolean));
let m = $json.body;
if (typeof m === 'string') { try { m = JSON.parse(m); } catch (e) { m = null; } }
const leida = $json.statusCode === 200 && m && typeof m === 'object' && !Array.isArray(m);
const fotos = leida && /^[A-Za-z0-9_-]{6,40}$/.test(uid)
  ? Object.keys(m).filter((s) => pedidas.has(s) && m[s] && typeof m[s].file === 'string' && /^[A-Za-z0-9._-]+$/.test(m[s].file))
      .map((s) => ({ slide: s, url: '__RENDER__/p/' + uid + '/img/' + m[s].file }))
  : [];
const contenido = [{ type: 'text', text: __INSTRUCCION__ }];
for (const f of fotos) {
  contenido.push({ type: 'text', text: 'Photo "' + f.slide + '":' });
  contenido.push({ type: 'image_url', image_url: { url: f.url, detail: 'high' } });
}
return [{ json: {
  revisar: fotos.length > 0,
  slides: fotos.map((f) => f.slide),
  ronda: Array.isArray(vig.fotos_retomadas) ? 1 : 0,
  vigente: vig,
  motivo_sin_revision: fotos.length ? '' : (leida ? 'no hay fotos guardadas' : 'no se pudo leer la lista de fotos (' + ($json.statusCode ? 'HTTP ' + $json.statusCode : 'sin respuesta') + ')'),
  cuerpo: { model: 'gpt-4o', temperature: 0, max_tokens: 200, response_format: { type: 'json_object' },
            messages: [{ role: 'user', content: contenido }] },
} }];""".replace('__RENDER__', 'https://n8n-propuesta-renderer.kchiba.easypanel.host').replace(
    '__INSTRUCCION__', json.dumps(INSTRUCCION_TEXTO))

CODE_DECIDIR_RETOMA = r"""// Lee la respuesta de GPT-4o. En la primera ronda, las fotos con texto se vuelven a pedir con otra descripción
// (el renderer reutiliza una foto solo si su descripción no cambió). En la segunda, solo informa. Nunca lanza.
const prep = $('Preparar revisión de texto').first().json;
const r = $json || {};
let conTexto = null;
let fallo = '';
if (r.statusCode === 200) {
  try {
    const j = JSON.parse(r.body.choices[0].message.content);
    if (!Array.isArray(j.con_texto)) throw new Error('sin con_texto');
    conTexto = [...new Set(j.con_texto.map(String))].filter((s) => prep.slides.includes(s));
  } catch (e) {
    fallo = 'respuesta ilegible del modelo';
  }
} else {
  fallo = r.statusCode ? 'OpenAI respondió HTTP ' + r.statusCode : 'OpenAI no respondió' + (r.error && r.error.message ? ': ' + r.error.message : '');
}
const vig = prep.vigente;
if (conTexto && conTexto.length && prep.ronda === 0) {
  const briefs = ((vig.payload && vig.payload.image_briefs) || []).map((b) =>
    conTexto.includes(b.slide) ? { slide: b.slide, prompt: b.prompt + __RETOMA__ } : b);
  return [{ json: Object.assign({}, vig, {
    payload: Object.assign({}, vig.payload, { image_briefs: briefs }),
    rehacer: true, fotos_retomadas: conTexto, renders_previos: vig.intento || 0,
  }) }];
}
return [{ json: { rehacer: false, fotos_con_texto: conTexto || [], revision_fallo: fallo } }];""".replace(
    '__RETOMA__', json.dumps(RETOMA))

CODE_MOTIVO = r"""// Falló la lectura del estado: no hay payload que renderizar.
const hook = $('Webhook').first().json.body || {};
const le = $('Leer estado').first().json;
return [{ json: { ok: false, id: hook.id, unique_id: '', company: `propuesta ${hook.id}`,
  problemas: [`No se pudo leer la propuesta en WordPress (HTTP ${le.statusCode})`],
  note: `No se pudo leer la propuesta en WordPress (HTTP ${le.statusCode})`, fotos_locales: 0, fotos_pedidas: 0, chat_respuesta: '' } }];"""



def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def http(id_, name, pos, method, url, body_expr=None, cred=True, timeout=None):
    params = {'method': method, 'url': url, 'options': dict(FULL)}
    if timeout:
        params['options']['timeout'] = timeout
    if cred:
        params.update({'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth'})
    if body_expr:
        params.update({'sendBody': True, 'specifyBody': 'json', 'jsonBody': body_expr})
    extra = {'credentials': CRED_WP} if cred else {}
    # neverError no cubre timeouts ni conexiones cortadas: sin esto la propuesta quedaba en «generando»
    # (revisión final 2026-09-24, I1). Con {error} y sin statusCode, las comprobaciones de 200 lo tratan como falla.
    return node(id_, name, 'n8n-nodes-base.httpRequest', 4.2, pos, params, onError='continueRegularOutput', **extra)


def iff(id_, name, pos, left):
    return node(id_, name, 'n8n-nodes-base.if', 2.2, pos, {
        'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose', 'version': 2},
                       'conditions': [{'id': id_ + '-c', 'leftValue': left, 'rightValue': True,
                                       'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
                       'combinator': 'and'},
        'options': {}})


nodes = [
    node('f1', 'Webhook', 'n8n-nodes-base.webhook', 2, [0, 0],
         {'httpMethod': 'POST', 'path': 'propuesta-v3-final', 'authentication': 'headerAuth',
          'responseMode': 'onReceived', 'options': {}},
         webhookId='propuesta-v3-final', credentials=CRED_WP),
    http('f2', 'Leer estado', [220, 0], 'GET', f"={WP}/proposal/{{{{ $json.body.id }}}}/state"),
    iff('f3', '¿Estado leído?', [440, 0], '={{ $json.statusCode === 200 }}'),
    # Revisar fotos: último filtro antes de gastar. No guarda nada; solo decide qué se le pide al renderer.
    node('f3b', 'Revisar fotos', 'n8n-nodes-base.code', 2, [550, -120],
         {'jsCode': JS_LIMPIAR_FOTOS + CODE_REVISAR}),
    # Render final: aquí SÍ se piden las fotos (image_briefs del payload). El renderer se da ~210 s para fotos + render;
    # si faltan fotos, «¿Reintentar render?» vuelve a llamarlo (hasta MAX_RENDERS) y el renderer reutiliza las ya guardadas.
    node('f4', 'Render final', 'n8n-nodes-base.httpRequest', 4.2, [660, -120],
         {'method': 'POST', 'url': RENDERER, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
          'sendBody': True, 'specifyBody': 'json',
          'jsonBody': "={{ JSON.stringify(Object.assign({}, $json.payload, { unique_id: $json.unique_id, draft: false })) }}",
          'options': {'timeout': 290000}},
         onError='continueRegularOutput', credentials=CRED_RENDER),
    node('f4b', '¿Reintentar render?', 'n8n-nodes-base.code', 2, [770, -300], {'jsCode': CODE_REINTENTAR}),
    iff('f4c', '¿Faltan fotos?', [880, -300], '={{ $json.reintentar }}'),
    # Revisión de texto en las fotos (2026-09-26): ver INSTRUCCION_TEXTO.
    http('f4d', 'Leer fotos', [1000, -420], 'GET',
         "={{ 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/' + $('Leer estado').first().json.body.unique_id + '/img/manifest.json' }}",
         cred=False, timeout=30000),
    node('f4e', 'Preparar revisión de texto', 'n8n-nodes-base.code', 2, [1120, -420], {'jsCode': CODE_PREPARAR_TEXTO}),
    iff('f4f', '¿Hay fotos que revisar?', [1240, -420], '={{ $json.revisar }}'),
    node('f4g', 'Buscar texto en fotos', 'n8n-nodes-base.httpRequest', 4.2, [1360, -520],
         {'method': 'POST', 'url': 'https://api.openai.com/v1/chat/completions',
          'authentication': 'predefinedCredentialType', 'nodeCredentialType': 'openAiApi',
          'sendBody': True, 'specifyBody': 'json', 'jsonBody': '={{ JSON.stringify($json.cuerpo) }}',
          'options': dict(FULL, timeout=120000)},
         onError='continueRegularOutput', credentials=CRED_OPENAI),
    node('f4h', 'Decidir retoma', 'n8n-nodes-base.code', 2, [1480, -520], {'jsCode': CODE_DECIDIR_RETOMA}),
    iff('f4i', '¿Rehacer fotos?', [1600, -520], '={{ $json.rehacer === true }}'),
    http('f5', 'Ver presentación', [880, -120], 'GET',
         f"={VER}{{{{ $('Leer estado').first().json.body.unique_id }}}}", cred=False, timeout=30000),
    http('f6', 'PDF', [1100, -120], 'HEAD',
         "={{ 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/' + $('Leer estado').first().json.body.unique_id + '/presentation.pdf' }}",
         cred=False, timeout=30000),
    http('f7', 'Probar chatbot', [1320, -120], 'POST', CHAT,
         "={{ JSON.stringify({ chatInput: 'Hola', sessionId: $('Leer estado').first().json.body.unique_id, action: 'sendMessage' }) }}",
         cred=False, timeout=90000),
    node('f8', 'Verificar', 'n8n-nodes-base.code', 2, [1540, -120], {'jsCode': CODE_VERIFICAR}),
    node('f9', 'Motivo lectura', 'n8n-nodes-base.code', 2, [660, 160], {'jsCode': CODE_MOTIVO}),
    http('f10', 'Guardar resultado', [1760, 0], 'POST', f"={WP}/proposal/{{{{ $json.id }}}}/state",
         "={{ JSON.stringify({ status: $json.ok ? 'lista' : 'error', note: $json.note + ($json.ok ? '' : ' (ejecución ' + $execution.id + ')') }) }}"),
    node('f11', 'Resultado', 'n8n-nodes-base.code', 2, [1980, 0],
         {'jsCode': correo_final()}),
    node('f12', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [2200, 0],
         {'fromEmail': 'contacto@automatizatech.cl', 'toEmail': LUIS,
          'subject': '={{ $json.asunto }}', 'html': '={{ $json.html }}', 'options': {}},
         credentials=CRED_SMTP),
]


def link(a, b, output=0):
    connections.setdefault(a, {'main': []})
    while len(connections[a]['main']) <= output:
        connections[a]['main'].append([])
    connections[a]['main'][output].append({'node': b, 'type': 'main', 'index': 0})


connections = {}
link('Webhook', 'Leer estado')
link('Leer estado', '¿Estado leído?')
link('¿Estado leído?', 'Revisar fotos', 0)
link('Revisar fotos', 'Render final')
link('¿Estado leído?', 'Motivo lectura', 1)
link('Render final', '¿Reintentar render?')
link('¿Reintentar render?', '¿Faltan fotos?')
link('¿Faltan fotos?', 'Render final', 0)
link('¿Faltan fotos?', 'Leer fotos', 1)
link('Leer fotos', 'Preparar revisión de texto')
link('Preparar revisión de texto', '¿Hay fotos que revisar?')
link('¿Hay fotos que revisar?', 'Buscar texto en fotos', 0)
link('¿Hay fotos que revisar?', 'Ver presentación', 1)
link('Buscar texto en fotos', 'Decidir retoma')
link('Decidir retoma', '¿Rehacer fotos?')
link('¿Rehacer fotos?', 'Render final', 0)
link('¿Rehacer fotos?', 'Ver presentación', 1)
link('Ver presentación', 'PDF')
link('PDF', 'Probar chatbot')
link('Probar chatbot', 'Verificar')
link('Verificar', 'Guardar resultado')
link('Motivo lectura', 'Guardar resultado')
link('Guardar resultado', 'Resultado')
link('Resultado', 'Correo a Luis')

wf = {'name': 'Propuestas v3 · 3 Final', 'nodes': nodes, 'connections': connections,
      # Si el flujo se cae sin llegar a su propio aviso, «0 Avisar error» (build_0_errores.py) le escribe a Luis.
      'settings': {'executionOrder': 'v1', 'errorWorkflow': 'm7TOfKznVSBGz4Nd'}}
out = os.path.join(os.path.dirname(os.path.abspath(__file__)), '3-final.json')
json.dump(wf, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
