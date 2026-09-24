"""Construye el workflow n8n «Propuestas v3 · 0 Avisar error» y lo guarda en 0-errores.json.

Es el «error workflow» de los tres flujos v3 (settings.errorWorkflow): si uno se cae sin llegar a su propio
aviso (OpenAI caído, un JSON que no se pudo leer, WordPress sin responder), le llega un correo a Luis.
Revisión final 2026-09-24, I5: antes un Borrador fallido no dejaba fila ni correo y la reunión se perdía sin aviso.
No se activa: n8n corre el Error Trigger aunque el workflow esté inactivo.
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os
from correos import correo_error_flujo

CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
LUIS = 'lmgm.0303@gmail.com'


def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


nodes = [
    node('e1', 'Error en un flujo v3', 'n8n-nodes-base.errorTrigger', 1, [0, 0], {}),
    node('e2', 'Armar correo', 'n8n-nodes-base.code', 2, [220, 0], {'jsCode': correo_error_flujo()}),
    node('e3', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [440, 0],
         {'fromEmail': 'contacto@automatizatech.cl', 'toEmail': LUIS,
          'subject': '={{ $json.asunto }}', 'html': '={{ $json.html }}', 'options': {}},
         credentials=CRED_SMTP),
]
connections = {
    'Error en un flujo v3': {'main': [[{'node': 'Armar correo', 'type': 'main', 'index': 0}]]},
    'Armar correo': {'main': [[{'node': 'Correo a Luis', 'type': 'main', 'index': 0}]]},
}
wf = {'name': 'Propuestas v3 · 0 Avisar error', 'nodes': nodes, 'connections': connections,
      'settings': {'executionOrder': 'v1'}}
out = os.path.join(os.path.dirname(os.path.abspath(__file__)), '0-errores.json')
json.dump(wf, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
