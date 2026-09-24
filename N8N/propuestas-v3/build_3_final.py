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
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
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
return [{ json: {
  ok, problemas, id: est.id, unique_id: est.unique_id, company: est.company_name,
  fotos_locales: img.stored_local || 0, fotos_pedidas: img.requested || 0,
  chat_respuesta: respuesta.slice(0, 300),
  note: ok ? `Verificada: presentación, PDF, ${img.stored_local || 0} fotos locales (${renders} render${renders === 1 ? '' : 's'}) y chatbot OK` : problemas.join(' · '),
} }];"""

CODE_REVISAR = r"""
// Último filtro antes de gastar: no guarda nada, solo decide qué fotos se le piden al renderer.
const e = $('Leer estado').first().json.body;
const r = limpiarFotos(e.payload.image_briefs);
return [{ json: { unique_id: e.unique_id, payload: Object.assign({}, e.payload, { image_briefs: r.limpias }), fotos_reemplazadas: r.reemplazadas } }];"""

MAX_RENDERS = 3
CODE_REINTENTAR = r"""// Tras cada render: si faltan fotos o no hubo presentación, se vuelve a llamar al renderer.
// El renderer reutiliza las fotos ya guardadas con el mismo prompt (manifest.json) y solo pide las que faltan,
// así que un reintento no vuelve a pagar las que ya salieron. Tope: MAX_RENDERS llamadas.
const MAX_RENDERS = __MAX__;
const r = $json || {};
const faltan = !r.view_url || ((r.images && r.images.missing) || []).length > 0;
const intento = $runIndex + 1;
const base = $('Revisar fotos').first().json;
return [{ json: Object.assign({}, base, { reintentar: faltan && intento < MAX_RENDERS, intento }) }];""".replace('__MAX__', str(MAX_RENDERS))

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
    return node(id_, name, 'n8n-nodes-base.httpRequest', 4.2, pos, params, **extra)


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
         {'method': 'POST', 'url': RENDERER, 'sendBody': True, 'specifyBody': 'json',
          'jsonBody': "={{ JSON.stringify(Object.assign({}, $json.payload, { unique_id: $json.unique_id, draft: false })) }}",
          'options': {'timeout': 290000}},
         onError='continueRegularOutput'),
    node('f4b', '¿Reintentar render?', 'n8n-nodes-base.code', 2, [770, -300], {'jsCode': CODE_REINTENTAR}),
    iff('f4c', '¿Faltan fotos?', [880, -300], '={{ $json.reintentar }}'),
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
link('¿Faltan fotos?', 'Ver presentación', 1)
link('Ver presentación', 'PDF')
link('PDF', 'Probar chatbot')
link('Probar chatbot', 'Verificar')
link('Verificar', 'Guardar resultado')
link('Motivo lectura', 'Guardar resultado')
link('Guardar resultado', 'Resultado')
link('Resultado', 'Correo a Luis')

wf = {'name': 'Propuestas v3 · 3 Final', 'nodes': nodes, 'connections': connections,
      'settings': {'executionOrder': 'v1'}}
out = os.path.join(os.path.dirname(os.path.abspath(__file__)), '3-final.json')
json.dump(wf, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
