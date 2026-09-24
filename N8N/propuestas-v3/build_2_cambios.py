"""Construye el workflow n8n «Propuestas v3 · 2 Cambios» y lo guarda en 2-cambios.json.

Plan: Docs/superpowers/plans/2026-09-23-flujo-propuestas-v3.md, Task 11.
Lo llama el panel de WordPress («Pedir cambios») con {id} y el header X-AT-Secret.
Nunca deja una propuesta trabada en «ajustando»: si algo falla, la pasa a «error» con el motivo
(ajustando→error es transición válida y desde error Luis puede reintentar).
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os

CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}
CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
RENDERER = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/render'
LUIS = 'lmgm.0303@gmail.com'
# Nunca enlazar *.easypanel.host en un correo: el SMTP de Hostinger lo rechaza (554 5.7.1, 2026-09-24).
VER = 'https://automatizatech.cl/ver-presentacion.php?id='
PANEL = 'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-proposals&edit_id='

PROMPT_CAMBIOS = """Recibes el JSON de una propuesta comercial y los comentarios del consultor. Devuelve SOLO el JSON completo (sin texto antes ni después, sin bloques de código) con SOLO los cambios que piden los comentarios; todo lo demás queda idéntico, con la misma forma y claves. No toques pricing_rows, pricing_note ni unique_id (se descartan igual). Mantén español de Chile y el mismo tratamiento (tú/usted). Si un comentario pide cambiar precios, ignóralo: los precios se editan en el panel. Si pide una lámina extra nueva, agrégala en extra_slides (máximo 2) y su image_brief extra_N con el mismo cierre de prohibiciones que las demás."""

CODE_PAYLOAD = r"""// Une las dos ramas: con comentario (lo aplicó el modelo) o sin comentario (solo cambiaron precios).
// Nunca lanza: si algo no cuadra devuelve ok=false con el motivo, para marcar la propuesta en error.
const estado = $('Leer estado').first().json;
let p = estado.payload;
let reason = '';
if ($('Aplicar comentarios').isExecuted) {
  const ia = $('Aplicar comentarios').first().json;
  let c = ia && ia.message ? ia.message.content : null;
  if (!c) {
    reason = 'El modelo no respondió al aplicar los comentarios' + (ia && ia.error ? ': ' + (ia.error.message || ia.error) : '');
  } else {
    try {
      if (typeof c === 'string') c = c.trim().replace(/^```(?:json)?\s*/i, '').replace(/```\s*$/, '');
      p = typeof c === 'string' ? JSON.parse(c) : c;
    } catch (e) {
      reason = 'El modelo devolvió un JSON inválido al aplicar los comentarios';
    }
  }
}
if (!reason && (!p || typeof p !== 'object')) reason = 'La propuesta no tiene un payload legible';
if (!reason) p.extra_slides = (p.extra_slides || []).slice(0, 2);
// WordPress restaura precios y unique_id de todos modos; aquí solo se asegura el formato.
return [{ json: { ok: !reason, reason, id: estado.id, unique_id: estado.unique_id, company: estado.company_name, payload: p } }];"""

CODE_MOTIVO = r"""// Motivo legible para status_note, venga de donde venga el fallo.
const base = $('Payload final').first().json;
let reason = base.reason;
if (!reason && $('Guardar y volver a borrador').isExecuted) {
  const g = $('Guardar y volver a borrador').first().json;
  const msg = g.body && (g.body.message || g.body.code) ? ' — ' + (g.body.message || g.body.code) : '';
  reason = `WordPress rechazó el cambio (HTTP ${g.statusCode})${msg}`;
}
return [{ json: { id: base.id, unique_id: base.unique_id, company: base.company, reason: reason || 'Error desconocido al aplicar los cambios', exec: $execution.id } }];"""

EMAIL_OK = """=<h3>{{ $('Vista previa').item.json.view_url ? '' : '⚠️ ' }}Cambios aplicados: {{ $('Payload final').item.json.company }}</h3>
<p>Último comentario: <em>{{ $('Leer estado').item.json.ultimo_comentario || '(sin comentario: solo precios)' }}</em></p>
{{ $('Vista previa').item.json.view_url ? '' : '<p style="color:#b45309"><strong>La nueva vista previa no se pudo generar.</strong> Los cambios quedaron guardados; «Pedir cambios» sin comentario la vuelve a generar.</p>' }}
<p><a href="__VER__{{ $('Payload final').item.json.unique_id }}">👀 Ver la nueva vista previa (sin fotos)</a></p>
<p><a href="__PANEL__{{ $('Payload final').item.json.id }}">✏️ Seguir revisando o aprobar en el panel</a></p>
<p style="color:#666">No se ha gastado nada en fotos. Nada se envía al cliente desde este flujo.</p>""".replace('__VER__', VER).replace('__PANEL__', PANEL)

EMAIL_ERROR = """=<h3>⚠️ No se pudieron aplicar los cambios: {{ $('Motivo del error').item.json.company }}</h3>
<p>{{ $('Motivo del error').item.json.reason }}</p>
<p>La propuesta quedó en estado <strong>error</strong>. Desde el panel puedes volver a «Pedir cambios» o aprobarla.</p>
<p><a href="__PANEL__{{ $('Motivo del error').item.json.id }}">✏️ Abrir en el panel</a></p>
<p style="color:#666">Ejecución de n8n: {{ $('Motivo del error').item.json.exec }}</p>""".replace('__PANEL__', PANEL)


def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def wp_http(id_, name, pos, method, url, body_expr=None):
    params = {'method': method, 'url': url, 'authentication': 'genericCredentialType',
              'genericAuthType': 'httpHeaderAuth',
              'options': {'response': {'response': {'fullResponse': True, 'neverError': True}}}}
    if body_expr:
        params.update({'sendBody': True, 'specifyBody': 'json', 'jsonBody': body_expr})
    return node(id_, name, 'n8n-nodes-base.httpRequest', 4.2, pos, params, credentials=CRED_WP)


def email(id_, name, pos, subject, html):
    return node(id_, name, 'n8n-nodes-base.emailSend', 1, pos,
                {'fromEmail': 'contacto@automatizatech.cl', 'toEmail': LUIS, 'subject': subject, 'html': html, 'options': {}},
                credentials=CRED_SMTP)


def iff(id_, name, pos, left):
    return node(id_, name, 'n8n-nodes-base.if', 2.2, pos, {
        'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose', 'version': 2},
                       'conditions': [{'id': id_ + '-c', 'leftValue': left, 'rightValue': True,
                                       'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
                       'combinator': 'and'},
        'options': {}})


nodes = [
    node('c1', 'Webhook', 'n8n-nodes-base.webhook', 2, [0, 0],
         {'httpMethod': 'POST', 'path': 'propuesta-v3-cambios', 'authentication': 'headerAuth',
          'responseMode': 'onReceived', 'options': {}},
         webhookId='propuesta-v3-cambios', credentials=CRED_WP),
    node('c2', 'Leer estado', 'n8n-nodes-base.httpRequest', 4.2, [220, 0],
         {'method': 'GET', 'url': f"={WP}/proposal/{{{{ $json.body.id }}}}/state",
          'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth', 'options': {}},
         credentials=CRED_WP),
    iff('c3', '¿Hay comentario?', [440, 0], "={{ String($json.ultimo_comentario || '').trim().length > 0 }}"),
    node('c4', 'Aplicar comentarios', 'n8n-nodes-base.openAi', 1, [660, -120],
         {'resource': 'chat', 'model': 'gpt-4o',
          'prompt': {'messages': [{'role': 'system', 'content': PROMPT_CAMBIOS},
                                  {'content': "={{ JSON.stringify({ comentarios: $json.ultimo_comentario, propuesta: $json.payload }) }}"}]},
          'options': {'temperature': 0.2}, 'requestOptions': {}},
         credentials=CRED_OPENAI, onError='continueRegularOutput'),
    node('c5', 'Payload final', 'n8n-nodes-base.code', 2, [880, 0], {'jsCode': CODE_PAYLOAD}),
    iff('c6', '¿Payload OK?', [1100, 0], '={{ $json.ok }}'),
    wp_http('c7', 'Guardar y volver a borrador', [1320, -120], 'POST',
            f"={WP}/proposal/{{{{ $json.id }}}}/state",
            "={{ JSON.stringify({ status: 'borrador', note: '', payload: $json.payload }) }}"),
    iff('c8', '¿Guardado OK?', [1540, -120], '={{ $json.statusCode === 200 }}'),
    node('c9', 'Vista previa', 'n8n-nodes-base.httpRequest', 4.2, [1760, -240],
         {'method': 'POST', 'url': RENDERER, 'sendBody': True, 'specifyBody': 'json',
          'jsonBody': "={{ JSON.stringify(Object.assign({}, $('Guardar y volver a borrador').item.json.body.payload, { unique_id: $('Payload final').item.json.unique_id, draft: true, image_briefs: [] })) }}",
          'options': {'timeout': 120000}},
         onError='continueRegularOutput'),
    email('c10', 'Correo cambios aplicados', [1980, -240],
          "={{ ($('Vista previa').item.json.view_url ? '' : '⚠️ ') + $('Payload final').item.json.company + ' · cambios aplicados' }}",
          EMAIL_OK),
    node('c11', 'Motivo del error', 'n8n-nodes-base.code', 2, [1760, 120], {'jsCode': CODE_MOTIVO}),
    wp_http('c12', 'Marcar error', [1980, 120], 'POST',
            f"={WP}/proposal/{{{{ $json.id }}}}/state",
            "={{ JSON.stringify({ status: 'error', note: $json.reason + ' (ejecución ' + $json.exec + ')' }) }}"),
    email('c13', 'Correo con problema', [2200, 120],
          "={{ '⚠️ ' + $('Motivo del error').item.json.company + ' · no se pudieron aplicar los cambios' }}",
          EMAIL_ERROR),
]


def link(a, b, output=0):
    connections.setdefault(a, {'main': []})
    while len(connections[a]['main']) <= output:
        connections[a]['main'].append([])
    connections[a]['main'][output].append({'node': b, 'type': 'main', 'index': 0})


connections = {}
link('Webhook', 'Leer estado')
link('Leer estado', '¿Hay comentario?')
link('¿Hay comentario?', 'Aplicar comentarios', 0)
link('¿Hay comentario?', 'Payload final', 1)
link('Aplicar comentarios', 'Payload final')
link('Payload final', '¿Payload OK?')
link('¿Payload OK?', 'Guardar y volver a borrador', 0)
link('¿Payload OK?', 'Motivo del error', 1)
link('Guardar y volver a borrador', '¿Guardado OK?')
link('¿Guardado OK?', 'Vista previa', 0)
link('¿Guardado OK?', 'Motivo del error', 1)
link('Vista previa', 'Correo cambios aplicados')
link('Motivo del error', 'Marcar error')
link('Marcar error', 'Correo con problema')

wf = {'name': 'Propuestas v3 · 2 Cambios', 'nodes': nodes, 'connections': connections,
      'settings': {'executionOrder': 'v1'}}
out = os.path.join(os.path.dirname(os.path.abspath(__file__)), '2-cambios.json')
json.dump(wf, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
