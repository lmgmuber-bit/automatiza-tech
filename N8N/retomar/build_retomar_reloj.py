"""Genera el reloj + WhatsApp de Retomar, siempre INACTIVO y sin red.

Corre en el minuto 5 de cada hora, todos los días (`0 5 * * * *`, zona America/Santiago). WordPress decide en cada pasada qué
toca (envíos aprobados, detección, reintentos de redacción y correo semanal) y respeta por su cuenta la ventana de envío del
prospecto; como hace todo eso dentro de la misma petición, el nodo «Tick» espera hasta 120 s.

El botón «Ver y agendar» de la plantilla `retomar_propuesta` (URL `retomar.php?{{1}}`) lleva el sufijo `t=<token>`: abre la
página del prospecto con la presentación, «Agendar» y «No me interesa» (no el formulario de agenda). El sufijo es dinámico,
así que la plantilla aprobada por Meta no cambia.

El routing Graph v22.0 se toma del export local Followup_WhatsApp_Send.json.
Eje debe validarlo al desplegar. Sólo se referencian nombres de credenciales.
"""
import json
from pathlib import Path

WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1/retomar/'
AUTH_WP = {'httpHeaderAuth': {'name': 'AT REST Secret (header)'}}
AUTH_WA = {'whatsAppApi': {'name': 'WhatsApp Tech'}}

PREPARAR = r"""const salida = [];
for (const [i, item] of $input.all().entries()) {
  const res = item.json || {};
  for (const w of (Array.isArray(res.whatsapp) ? res.whatsapp : [])) {
    const token = encodeURIComponent(String(w.token));
    salida.push({json: {id: w.id, n: w.n, payload: {
      messaging_product: 'whatsapp', to: String(w.telefono), type: 'template',
      template: {name: 'retomar_propuesta', language: {code: 'es'}, components: [
        {type: 'body', parameters: [
          {type: 'text', text: String(w.nombre)}, {type: 'text', text: String(w.negocio)}]},
        {type: 'button', sub_type: 'url', index: '0',
          parameters: [{type: 'text', text: 't=' + token}]},
        {type: 'button', sub_type: 'url', index: '1',
          parameters: [{type: 'text', text: 't=' + token + '&baja=1'}]}
      ]}
    }}, pairedItem: {item: i}});
  }
}
return salida;"""

NORMALIZAR = r"""return $input.all().map((item, i) => {
  // El HTTP reemplaza json: recuperar el original por su enlace, no por posición global.
  const original = $('Preparar WhatsApp').itemMatching(i).json;
  const res = item.json || {};
  let body = res.body || {};
  if (typeof body === 'string') { try { body = JSON.parse(body); } catch (_) { body = {}; } }
  const estado = Number(res.statusCode) || 0;
  const wamid = body.messages?.[0]?.id;
  const metaError = body.error || res.error || {};
  const codigo = Number(metaError.code || metaError.context?.code) || 0;
  const resultado = {id: original.id, n: original.n};
  if (estado >= 200 && estado < 300 && !body.error && typeof wamid === 'string' && wamid) {
    resultado.wamid = wamid;
  } else {
    resultado.codigo = codigo;
    // No copiar mensajes de error arbitrarios (pueden contener tokens o datos personales).
    resultado.titulo = codigo === 131049 ? 'Meta limita marketing: sin reintento' :
      (estado ? 'Error WhatsApp HTTP ' + estado : 'Error de red WhatsApp: sin reintento');
  }
  return {json: resultado, pairedItem: {item: i}};
});"""


def node(id_, name, kind, version, x, parameters, **extra):
    return {'id': id_, 'name': name, 'type': 'n8n-nodes-base.' + kind,
            'typeVersion': version, 'position': [x, 0], 'parameters': parameters, **extra}


def build():
    source = Path(__file__).resolve().parent.parent / 'PROD' / 'Followup_WhatsApp_Send.json'
    export = json.loads(source.read_text(encoding='utf-8-sig'))
    routing = next(n['parameters']['url'] for n in export['nodes']
                   if n.get('name') == 'Send WhatsApp' and
                   n.get('parameters', {}).get('nodeCredentialType') == 'whatsAppApi')
    if not routing.startswith('https://graph.facebook.com/v22.0/') or not routing.endswith('/messages'):
        raise ValueError('Routing local inesperado: Eje debe revisar antes de generar')
    nodes = [
        node('rr1', 'Reloj', 'scheduleTrigger', 1.2, 0,
             {'rule': {'interval': [{'field': 'cronExpression', 'expression': '0 5 * * * *'}]}}),
        node('rr2', 'Tick', 'httpRequest', 4.1, 240,
             {'method': 'POST', 'url': WP + 'tick', 'authentication': 'genericCredentialType',
              'genericAuthType': 'httpHeaderAuth', 'options': {'timeout': 120000,
              'redirect': {'redirect': {'followRedirects': False}}}}, credentials=AUTH_WP),
        node('rr3', 'Preparar WhatsApp', 'code', 2, 480, {'jsCode': PREPARAR}),
        node('rr4', 'Enviar WhatsApp', 'httpRequest', 4.1, 720,
             {'method': 'POST', 'url': routing, 'authentication': 'predefinedCredentialType',
              'nodeCredentialType': 'whatsAppApi', 'sendBody': True, 'specifyBody': 'json',
              'jsonBody': '={{ $json.payload }}', 'options': {'timeout': 30000,
              'redirect': {'redirect': {'followRedirects': False}},
              'response': {'response': {'fullResponse': True, 'neverError': True}}}},
             credentials=AUTH_WA, onError='continueRegularOutput'),
        node('rr5', 'Normalizar resultado', 'code', 2, 960, {'jsCode': NORMALIZAR}),
        node('rr6', 'Registrar resultado', 'httpRequest', 4.1, 1200,
             {'method': 'POST', 'url': WP + 'whatsapp-resultado',
              'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
              'sendBody': True, 'specifyBody': 'json', 'jsonBody': '={{ $json }}',
              'options': {'timeout': 30000, 'redirect': {'redirect': {'followRedirects': False}}}},
             credentials=AUTH_WP),
    ]
    connections = {a['name']: {'main': [[{'node': b['name'], 'type': 'main', 'index': 0}]]}
                   for a, b in zip(nodes, nodes[1:])}
    return {'name': 'Retomar · Reloj y WhatsApp', 'active': False, 'nodes': nodes,
            'connections': connections, 'settings': {'executionOrder': 'v1', 'timezone': 'America/Santiago'}}


if __name__ == '__main__':
    output = Path(__file__).with_name('retomar-reloj.json')
    output.write_text(json.dumps(build(), ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print('Generado workflow inactivo:', output.name)
