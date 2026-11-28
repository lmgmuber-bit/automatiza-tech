"""Genera Retomar · Render INACTIVO. No accede a n8n ni genera fotos.

Credenciales existentes referidas únicamente por nombre; sus valores nunca se exportan.
El POST autenticado recibe {id, generacion, render}; cualquier respuesta/error del renderer llega
al callback con ok booleano y las fotos que el renderer dejó guardadas (`fotos` = images.urls: lámina => URL; {} si no hay).
El render espera hasta 300 s al renderer (puede generar hasta cuatro fotos). La URL interna se guarda solo para iframe/PDF en WP.
"""
import json
from pathlib import Path

WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
RENDERER = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/render'
AUTH_WP = {'httpHeaderAuth': {'name': 'AT REST Secret (header)'}}
AUTH_RENDER = {'httpHeaderAuth': {'name': 'X-AT-Render-Key'}}

RESULTADO = r"""const respuesta = $input.first().json || {};
const hook = $('Webhook').first().json.body || {};
let body = respuesta.body || {};
if (typeof body === 'string') { try { body = JSON.parse(body); } catch (_) { body = {}; } }
const estado = Number(respuesta.statusCode) || 0;
const ok = estado >= 200 && estado < 300 && typeof body.view_url === 'string' && !!body.view_url;
// Sin copiar mensajes de red: pueden contener URLs internas o datos de infraestructura.
const detalles = Array.isArray(body.details) ? body.details.filter(x => typeof x === 'string').join('; ') : '';
const nota = ok ? '' : ('No se pudo generar la presentación (HTTP ' + estado + '). ' + detalles)
  .replace(/https?:\/\/\S+/gi, '[enlace omitido]').slice(0, 1000);
return [{json: {id: Number(hook.id), callback: {ok, generacion: hook.generacion,
  view_url: ok ? body.view_url : '', pdf_url: ok && typeof body.pdf_url === 'string' ? body.pdf_url : '', nota,
  fotos: body.images && body.images.urls || {}}}}];"""


def node(id_, name, kind, version, x, parameters, **extra):
    return {'id': id_, 'name': name, 'type': 'n8n-nodes-base.' + kind,
            'typeVersion': version, 'position': [x, 0], 'parameters': parameters, **extra}


nodes = [
    node('rt1', 'Webhook', 'webhook', 2, 0,
         {'httpMethod': 'POST', 'path': 'retomar-render', 'authentication': 'headerAuth',
          'responseMode': 'onReceived', 'options': {}}, webhookId='retomar-render', credentials=AUTH_WP),
    node('rt2', 'Render', 'httpRequest', 4.2, 260,
         {'method': 'POST', 'url': RENDERER, 'authentication': 'genericCredentialType',
          'genericAuthType': 'httpHeaderAuth', 'sendBody': True, 'specifyBody': 'json',
          'jsonBody': '={{ $json.body.render }}',
          'options': {'timeout': 300000, 'redirect': {'redirect': {'followRedirects': False}},
                      'response': {'response': {'fullResponse': True, 'neverError': True}}}},
         credentials=AUTH_RENDER, onError='continueRegularOutput'),
    node('rt3', 'Resultado', 'code', 2, 520, {'jsCode': RESULTADO}),
    node('rt4', 'Guardar vista', 'httpRequest', 4.2, 780,
         {'method': 'POST', 'url': '={{ "' + WP + '/retomar/" + $json.id + "/vista" }}',
          'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
          'sendBody': True, 'specifyBody': 'json', 'jsonBody': '={{ $json.callback }}',
          'options': {'timeout': 30000, 'redirect': {'redirect': {'followRedirects': False}}}},
         credentials=AUTH_WP),
]
connections = {a: {'main': [[{'node': b, 'type': 'main', 'index': 0}]]}
               for a, b in [('Webhook', 'Render'), ('Render', 'Resultado'), ('Resultado', 'Guardar vista')]}
workflow = {'name': 'Retomar · Render', 'active': False, 'nodes': nodes,
            'connections': connections, 'settings': {'executionOrder': 'v1'}}

if __name__ == '__main__':
    output = Path(__file__).with_name('retomar-render.json')
    output.write_text(json.dumps(workflow, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print('Generado workflow inactivo:', output.name, len(nodes), 'nodos')
