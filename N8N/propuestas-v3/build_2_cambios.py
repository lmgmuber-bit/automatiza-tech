"""Construye el workflow n8n «Propuestas v3 · 2 Cambios» y lo guarda en 2-cambios.json.

Plan: Docs/superpowers/plans/2026-09-23-flujo-propuestas-v3.md, Task 11.
Lo llama el panel de WordPress («Pedir cambios») con {id} y el header X-AT-Secret.
Nunca deja una propuesta trabada en «ajustando»: si algo falla, la pasa a «error» con el motivo
(ajustando→error es transición válida y desde error Luis puede reintentar).
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os
from correos import correo_cambios_ok, correo_cambios_error

CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}
CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
RENDERER = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/render'
LUIS = 'lmgm.0303@gmail.com'
# Nunca enlazar *.easypanel.host en un correo: el SMTP de Hostinger lo rechaza (554 5.7.1, 2026-09-24).
VER = 'https://automatizatech.cl/ver-presentacion.php?id='
PANEL = 'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-proposals&edit_id='

PROMPT_CAMBIOS = """Recibes el JSON de una propuesta comercial y los comentarios del consultor. Devuelve SOLO el objeto JSON de la propuesta, directamente (no lo envuelvas en otra clave como "propuesta"; sin texto antes ni después, sin bloques de código), con SOLO los cambios que piden los comentarios; todo lo demás queda idéntico, con la misma forma y claves. No toques pricing_rows, pricing_note ni unique_id (se descartan igual). Mantén español de Chile y el mismo tratamiento (tú/usted). Si un comentario pide cambiar precios, ignóralo: los precios se editan en el panel. Si pide una lámina extra nueva, agrégala en extra_slides (máximo 2) y su image_brief extra_N con el mismo cierre de prohibiciones que las demás. Si un comentario pide cambiar una foto, reescribe solo ese image_brief: foto realista del rubro del cliente (se permiten personas en acción); la de cover siempre en primer plano con el fondo desenfocado; productos con etiqueta o pantalla, sin etiqueta, de espaldas o apagados; nunca pantallas con contenido, letreros, carteles, documentos ni texto. Si un comentario pide cambiar el correo al cliente (asunto, introducción, qué incluye o cierre), cámbialo en correo_cliente con el mismo tratamiento; si no, deja correo_cliente idéntico."""

CODE_PAYLOAD = r"""// Une las dos ramas: con comentario (lo aplicó el modelo) o sin comentario (solo cambiaron precios).
// Nunca lanza: si algo no cuadra devuelve ok=false con el motivo, para marcar la propuesta en error.
const estado = $('Leer estado').first().json.body;
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
      const parsed = typeof c === 'string' ? JSON.parse(c) : c;
      // El modelo a veces imita la forma de la entrada y devuelve {propuesta: {...}} (medido: ejecución 403689).
      const candidato = parsed && parsed.propuesta && typeof parsed.propuesta === 'object' ? parsed.propuesta : parsed;
      // Lo que el modelo omita se conserva de lo guardado: nunca se manda a WordPress un payload incompleto.
      p = Object.assign({}, estado.payload, candidato);
    } catch (e) {
      reason = 'El modelo devolvió un JSON inválido al aplicar los comentarios';
    }
  }
}
if (!reason && (!p || typeof p !== 'object')) reason = 'La propuesta no tiene un payload legible';
if (!reason) p.extra_slides = (p.extra_slides || []).slice(0, 2);
// WordPress restaura precios y unique_id de todos modos; aquí solo se asegura el formato.
return [{ json: { ok: !reason, reason, id: estado.id, unique_id: estado.unique_id, company: estado.company_name, payload: p } }];"""

CODE_MOTIVO = r"""// Motivo legible para status_note, venga de donde venga el fallo (lectura, modelo o guardado).
const hook = $('Webhook').first().json.body || {};
let base = { id: hook.id, unique_id: '', company: `propuesta ${hook.id}`, reason: '' };
if ($('Payload final').isExecuted) {
  base = $('Payload final').first().json;
} else {
  const le = $('Leer estado').first().json;
  base.reason = `No se pudo leer la propuesta en WordPress (${le.statusCode ? 'HTTP ' + le.statusCode : 'sin respuesta' + (le.error && le.error.message ? ': ' + le.error.message : '')})`;
}
let reason = base.reason;
if (!reason && $('Guardar y volver a borrador').isExecuted) {
  const g = $('Guardar y volver a borrador').first().json;
  const msg = g.body && (g.body.message || g.body.code) ? ' — ' + (g.body.message || g.body.code) : '';
  reason = g.statusCode ? `WordPress rechazó el cambio (HTTP ${g.statusCode})${msg}`
    : `WordPress no respondió al guardar el cambio${g.error && g.error.message ? ': ' + g.error.message : ''}`;
}
return [{ json: { id: base.id, unique_id: base.unique_id, company: base.company, reason: reason || 'Error desconocido al aplicar los cambios', exec: $execution.id } }];"""




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
    # neverError solo cubre respuestas con código de error; un timeout o una conexión cortada lanzan igual.
    # continueRegularOutput deja pasar {error} sin statusCode, y los «¿… OK?» lo mandan a la rama de error
    # (revisión final 2026-09-24, I1: si no, la propuesta quedaba trabada en «ajustando»).
    return node(id_, name, 'n8n-nodes-base.httpRequest', 4.2, pos, params, credentials=CRED_WP,
                onError='continueRegularOutput')


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
    wp_http('c2', 'Leer estado', [220, 0], 'GET', f"={WP}/proposal/{{{{ $json.body.id }}}}/state"),
    iff('c2b', '¿Estado leído?', [330, 0], '={{ $json.statusCode === 200 }}'),
    iff('c3', '¿Hay comentario?', [440, 0], "={{ String($json.body.ultimo_comentario || '').trim().length > 0 }}"),
    node('c4', 'Aplicar comentarios', 'n8n-nodes-base.openAi', 1, [660, -120],
         {'resource': 'chat', 'model': 'gpt-4o',
          'prompt': {'messages': [{'role': 'system', 'content': PROMPT_CAMBIOS},
                                  {'content': "={{ JSON.stringify({ comentarios: $json.body.ultimo_comentario, propuesta: $json.body.payload }) }}"}]},
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
    node('c9b', 'Armar correo cambios', 'n8n-nodes-base.code', 2, [1870, -240], {'jsCode': correo_cambios_ok()}),
    email('c10', 'Correo cambios aplicados', [1980, -240], '={{ $json.asunto }}', '={{ $json.html }}'),
    node('c11', 'Motivo del error', 'n8n-nodes-base.code', 2, [1760, 120], {'jsCode': CODE_MOTIVO}),
    wp_http('c12', 'Marcar error', [1980, 120], 'POST',
            f"={WP}/proposal/{{{{ $json.id }}}}/state",
            "={{ JSON.stringify({ status: 'error', note: $json.reason + ' (ejecución ' + $json.exec + ')' }) }}"),
    node('c12b', 'Armar correo error', 'n8n-nodes-base.code', 2, [2090, 120], {'jsCode': correo_cambios_error()}),
    email('c13', 'Correo con problema', [2200, 120], '={{ $json.asunto }}', '={{ $json.html }}'),
]


def link(a, b, output=0):
    connections.setdefault(a, {'main': []})
    while len(connections[a]['main']) <= output:
        connections[a]['main'].append([])
    connections[a]['main'][output].append({'node': b, 'type': 'main', 'index': 0})


connections = {}
link('Webhook', 'Leer estado')
link('Leer estado', '¿Estado leído?')
link('¿Estado leído?', '¿Hay comentario?', 0)
link('¿Estado leído?', 'Motivo del error', 1)
link('¿Hay comentario?', 'Aplicar comentarios', 0)
link('¿Hay comentario?', 'Payload final', 1)
link('Aplicar comentarios', 'Payload final')
link('Payload final', '¿Payload OK?')
link('¿Payload OK?', 'Guardar y volver a borrador', 0)
link('¿Payload OK?', 'Motivo del error', 1)
link('Guardar y volver a borrador', '¿Guardado OK?')
link('¿Guardado OK?', 'Vista previa', 0)
link('¿Guardado OK?', 'Motivo del error', 1)
link('Vista previa', 'Armar correo cambios')
link('Armar correo cambios', 'Correo cambios aplicados')
link('Motivo del error', 'Marcar error')
link('Marcar error', 'Armar correo error')
link('Armar correo error', 'Correo con problema')

wf = {'name': 'Propuestas v3 · 2 Cambios', 'nodes': nodes, 'connections': connections,
      # Si el flujo se cae sin llegar a su propio aviso, «0 Avisar error» (build_0_errores.py) le escribe a Luis.
      'settings': {'executionOrder': 'v1', 'errorWorkflow': 'm7TOfKznVSBGz4Nd'}}
out = os.path.join(os.path.dirname(os.path.abspath(__file__)), '2-cambios.json')
json.dump(wf, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
