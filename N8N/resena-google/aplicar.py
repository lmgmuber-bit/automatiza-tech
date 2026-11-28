"""Reseñas: genera dos flujos INACTIVOS. Por defecto solo JSON, sin credenciales, archivos externos ni red.
Uso autorizado en este ticket: python -B N8N/resena-google/aplicar.py --dry-run
--apply queda preparado para un encargo futuro explícito. Nunca activa, publica ni actualiza flujos existentes.
"""
import argparse
import copy
import json
import os
from pathlib import Path
import sys
import urllib.request
import urllib.parse
import uuid

WP = 'https://automatizatech.cl/wp-json/at/v1/resena'
WA_EMISOR = '946501128543201'  # phone_number_id de WhatsApp Tech; ya público en N8N/PROD
ROOT = Path(__file__).resolve().parents[2]
SEPARAR_JS = "return ($input.first().json.pendientes || []).slice(0,10).map(p => ({json:p}));"  # sin el parche del resumen
_RESUMEN = None


def _resumen():
    """resumen.py importa este archivo; se carga recién al usarlo para no armar un ciclo de import."""
    global _RESUMEN
    if _RESUMEN is None:
        import importlib.util
        spec = importlib.util.spec_from_file_location('resena_resumen_desde_aplicar', Path(__file__).resolve().parent / 'resumen.py')
        _RESUMEN = importlib.util.module_from_spec(spec); spec.loader.exec_module(_RESUMEN)
    return _RESUMEN


def node(name, kind, parameters, x, version=1, **extra):
    return dict(id=str(uuid.uuid5(uuid.NAMESPACE_URL, 'at-resena:' + name)), name=name,
                type='n8n-nodes-base.' + kind, typeVersion=version, position=[x, 0],
                parameters=parameters, retryOnFail=False, **extra)


def edge(target, index=0):
    return {'node': target, 'type': 'main', 'index': index}


def http(name, url, cred, x, body=None, method='POST', full=True):
    p = dict(method=method, url=url, authentication='genericCredentialType', genericAuthType='httpHeaderAuth',
             options={'timeout': 20000, 'response': {'response': {'neverError': True, 'fullResponse': full, 'responseFormat': 'json'}}})
    if body is not None:
        p.update(sendBody=True, specifyBody='json', jsonBody=body)
    return node(name, 'httpRequest', p, x, 4.2, credentials={'httpHeaderAuth': {'id': cred, 'name': 'AT REST (configurar en n8n)'}}, onError='continueRegularOutput')


def workflows(rest_credential='CONFIGURAR_AT_REST', wa_credential='CONFIGURAR_WHATSAPP', webhook_credential='CONFIGURAR_AT_REST', resumen=True):
    """resumen=True: el diario sale con el parche de resumen.py (lo que corre en n8n). False: el diario base."""
    settings = {'timezone': 'America/Santiago', 'executionOrder': 'v1', 'saveDataSuccessExecution': 'none',
                'saveDataErrorExecution': 'none', 'saveManualExecutions': False, 'saveExecutionProgress': False,
                'executionTimeout': 300}
    daily = {'name': 'Reseña · Diario 10:00', 'active': False, 'settings': copy.deepcopy(settings), 'nodes': [
        node('10:00 Chile', 'scheduleTrigger', {'rule': {'interval': [{'field': 'cronExpression', 'expression': '0 10 * * *'}]}}, 0, 1.2),
        http('Pendientes', WP + '/pendientes', rest_credential, 240, method='GET', full=False),
        node('Separar proyectos', 'code', {'jsCode': SEPARAR_JS}, 480, 2),
        http('Reclamar (WP envía correo y dispara WA)', '={{ "' + WP + '/proyecto/" + $json.proyecto_id + "/reclamar" }}', rest_credential, 720, body='{}'),
    ], 'connections': {'10:00 Chile': {'main': [[edge('Pendientes')]]}, 'Pendientes': {'main': [[edge('Separar proyectos')]]},
                       'Separar proyectos': {'main': [[edge('Reclamar (WP envía correo y dispara WA)')]]}}}
    if resumen:
        daily = _resumen().patch_daily(daily, rest_credential)
    webhook = node('Webhook autenticado', 'webhook', {'path': 'at-resena-whatsapp', 'httpMethod': 'POST', 'authentication': 'headerAuth', 'responseMode': 'onReceived', 'options': {}}, 0, 2,
                   webhookId=str(uuid.uuid5(uuid.NAMESPACE_URL, 'at-resena-whatsapp')), credentials={'httpHeaderAuth': {'id': webhook_credential, 'name': 'AT REST (entrada X-AT-Secret)'}})
    authorize = http('Autorizar despacho', '={{ "' + WP + '/proyecto/" + $json.body.proyecto_id + "/autorizar-whatsapp" }}', rest_credential, 240,
                     '={{ {intento_id:$json.body.intento_id, modo:$json.body.modo} }}')
    gate = node('Solo despacho nuevo', 'if', {'conditions': {'options': {'typeValidation': 'strict'}, 'conditions': [
        {'id': 'status', 'leftValue': '={{ $json.statusCode }}', 'rightValue': 200, 'operator': {'type': 'number', 'operation': 'equals'}}], 'combinator': 'and'}, 'options': {}}, 480, 2.2)
    # Emisor escrito en la URL, igual que el flujo de propuestas (n8n de AT no expone $env). Cuerpo con llaves separadas:
    # n8n corta la expresión en el primer «}}» interno (PROD 22-nov-2026: «invalid syntax», Meta nunca recibió nada).
    meta = http('Enviar plantilla (sin reintento)', 'https://graph.facebook.com/v22.0/' + WA_EMISOR + '/messages', rest_credential, 720,
                r'={{ JSON.stringify({ messaging_product: "whatsapp", to: String($json.body.telefono).replace(/^\+/, ""), type: "template", '
                'template: { name: "at_resena_google_cierre_v2", language: { code: "es" }, components: [ { type: "body", parameters: [ '
                '{ type: "text", text: String($json.body.nombre) }, { type: "text", text: String($json.body.proyecto) } ] }, '
                # v2: el botón 1 (respuesta rápida «No recibir más») vuelve al bot con este payload.
                '{ type: "button", sub_type: "quick_reply", index: "1", parameters: [ { type: "payload", payload: "' + 'btn_resena_baja' + '" } ] } ] } }) }}')
    meta['parameters'].update(authentication='predefinedCredentialType', nodeCredentialType='whatsAppApi')
    del meta['parameters']['genericAuthType']
    meta['credentials'] = {'whatsAppApi': {'id': wa_credential, 'name': 'WhatsApp Tech'}}
    normalize = node('Clasificar resultado', 'code', {'jsCode': """const base = $('Autorizar despacho').item.json.body;
const r = $json; const wamid = r.body?.messages?.[0]?.id || '';
const status = Number(r.statusCode || 0);
const estado = status >= 200 && status < 300 && wamid ? 'aceptado' :
  (status >= 400 && status < 500 ? 'fallido' : 'desconocido');
const codigo = Number(r.body?.error?.code || 0);
return [{json:{proyecto_id:base.proyecto_id,intento_id:base.intento_id,modo:base.modo,estado,wamid,
detalle:codigo ? 'Meta: código '+codigo : (estado==='aceptado' ? 'Aceptado por Meta; entrega no confirmada.' : 'Sin confirmación de envío; revisar ejecución.')}}];"""}, 960, 2)
    result = http('Registrar resultado', '={{ "' + WP + '/proyecto/" + $json.proyecto_id + "/resultado" }}', rest_credential, 1200,
                  '={{ {intento_id:$json.intento_id,modo:$json.modo,estado:$json.estado,wamid:$json.wamid,detalle:$json.detalle} }}')
    wa = {'name': 'Reseña · Enviar WhatsApp', 'active': False, 'settings': copy.deepcopy(settings),
          'nodes': [webhook, authorize, gate, meta, normalize, result], 'connections': {
              'Webhook autenticado': {'main': [[edge('Autorizar despacho')]]}, 'Autorizar despacho': {'main': [[edge('Solo despacho nuevo')]]},
              'Solo despacho nuevo': {'main': [[edge('Enviar plantilla (sin reintento)')], []]},
              'Enviar plantilla (sin reintento)': {'main': [[edge('Clasificar resultado')]]}, 'Clasificar resultado': {'main': [[edge('Registrar resultado')]]}}}
    return [daily, wa]


BAJA_PAYLOAD = 'btn_resena_baja'  # payload del botón «No recibir más» de at_resena_google_cierre_v2
BAJA_OK = ('Listo, ya no te enviaremos más invitaciones ni mensajes promocionales. '
           'Si necesitas ayuda con tu proyecto, escríbenos por aquí cuando quieras.')
BAJA_FALLA = 'No pudimos registrar tu solicitud en este momento. Escribe BAJA en unos minutos y la registramos.'


def _wa_send(name, wa_credential, x):
    """Envío de texto dentro de la ventana de 24 h (la abre el propio mensaje o el toque del botón)."""
    p = dict(method='POST', url='https://graph.facebook.com/v22.0/' + WA_EMISOR + '/messages',
             authentication='predefinedCredentialType', nodeCredentialType='whatsAppApi', sendBody=True,
             specifyBody='json', jsonBody='={{ $json.whatsappBody }}',
             options={'timeout': 20000, 'response': {'response': {'neverError': True}}})
    return node(name, 'httpRequest', p, x, 4.2, credentials={'whatsAppApi': {'id': wa_credential, 'name': 'WhatsApp Tech'}},
                onError='continueRegularOutput')


def _confirmar(name, telefono_js, x, solo_si_baja):
    """Arma el texto de confirmación. telefono_js: expresión JS que devuelve el teléfono del chat."""
    falla = 'null' if solo_si_baja else json.dumps(BAJA_FALLA, ensure_ascii=False)
    code = ('const r = $input.first().json || {};\n'
            'const ok = r.body && r.body.ok === true && r.body.baja === true;\n'
            'const cuerpo = ok ? ' + json.dumps(BAJA_OK, ensure_ascii=False) + ' : ' + falla + ';\n'
            'if (!cuerpo) return [];\n'
            'const to = String(' + telefono_js + ' || "").replace(/\\D/g, "");\n'
            'return [{ json: { whatsappBody: JSON.stringify({ messaging_product: "whatsapp", recipient_type: "individual", '
            'to, type: "text", text: { preview_url: false, body: cuerpo } }) } }];\n')
    return node(name, 'code', {'jsCode': code}, x, 2)


def patch_bot(export, rest_credential='CONFIGURAR_AT_REST', wa_credential='CONFIGURAR_WHATSAPP',
              source='Merge Data', switch='Button Action', interactive='Process Interactive'):
    """Baja de reseñas/promociones en el bot de WhatsApp. Función pura: devuelve una copia y no toca el original.

    Texto: lo que menciona baja/stop va a WordPress, que decide (at_rg_intencion_baja). Si registró la baja, el bot
    confirma y no llama al agente; si no (duda, «¿cómo es la baja?», o WordPress caído), la conversación sigue al
    agente con los datos originales de `source`, como si el desvío no existiera.
    Botón: el payload btn_resena_baja de la plantilla v2 se rutea en `switch` y registra la baja con texto «baja».
    """
    w = copy.deepcopy(export)
    names = {n['name'] for n in w['nodes']}
    if source not in names or switch not in names or interactive not in names:
        raise ValueError('Faltan nodos del bot: ' + ', '.join(n for n in (source, switch, interactive) if n not in names))
    if any(n.startswith('Reseña · ') for n in names):
        raise ValueError('El desvío de baja ya está instalado')
    conns = w.setdefault('connections', {})
    old = copy.deepcopy(conns.get(source, {}).get('main', [[]]))
    if len(old) != 1 or not old[0]:
        raise ValueError('El origen debe tener una sola salida main conectada')

    # --- texto ---
    gate = node('Reseña · ¿Menciona BAJA?', 'if', {'conditions': {'options': {'typeValidation': 'strict'}, 'conditions': [
        {'id': 'baja', 'leftValue': '={{ /baja|stop|no me escriban|no quiero recibir/.test(String($json.chatInput || "")'
         '.normalize("NFD").replace(/[\\u0300-\\u036f]/g,"").toLowerCase().replace(/[^a-z0-9]+/g," ").trim()) }}',
         'rightValue': True, 'operator': {'type': 'boolean', 'operation': 'equals'}}], 'combinator': 'and'}, 'options': {}}, 0, 2.2)
    reg_t = http('Reseña · Registrar BAJA (texto)', WP + '/baja', rest_credential, 240,
                 '={{ JSON.stringify({ texto: String($json.chatInput || ""), telefono: "+" + String($json.phoneNumber || "").replace(/\\D/g, "") }) }}')
    ok_t = node('Reseña · ¿BAJA registrada?', 'if', {'conditions': {'options': {'typeValidation': 'loose'}, 'conditions': [
        {'id': 'ok', 'leftValue': '={{ $json.body && $json.body.ok === true && $json.body.baja === true }}',
         'rightValue': True, 'operator': {'type': 'boolean', 'operation': 'equals'}}], 'combinator': 'and'}, 'options': {}}, 480, 2.2)
    conf_t = _confirmar('Reseña · Confirmar BAJA (texto)', "$('" + source + "').first().json.phoneNumber", 720, True)
    send_t = _wa_send('Reseña · Enviar confirmación (texto)', wa_credential, 960)
    back = node('Reseña · Seguir al agente', 'code',
                {'jsCode': "return $('" + source + "').all().map(i => ({ json: i.json }));"}, 720, 2)

    # --- botón de la plantilla v2 ---
    data_b = node('Reseña · Datos BAJA (botón)', 'code', {'jsCode':
        "const pi = $('" + interactive + "').first().json;\n"
        "return [{ json: { texto: 'baja', telefono: '+' + String(pi.phoneNumber || '').replace(/\\D/g, '') } }];"}, 0, 2)
    reg_b = http('Reseña · Registrar BAJA (botón)', WP + '/baja', rest_credential, 240,
                 '={{ JSON.stringify({ texto: $json.texto, telefono: $json.telefono }) }}')
    conf_b = _confirmar('Reseña · Confirmar BAJA (botón)', "$('Reseña · Datos BAJA (botón)').first().json.telefono", 480, False)
    send_b = _wa_send('Reseña · Enviar confirmación (botón)', wa_credential, 720)

    sw = next(n for n in w['nodes'] if n['name'] == switch)
    rules = sw['parameters']['rules']['values']
    regla = copy.deepcopy(rules[-1])
    cond = regla['conditions']['conditions'][0]
    cond.update(leftValue='={{ $json.buttonId }}', rightValue=BAJA_PAYLOAD, operator={'type': 'string', 'operation': 'equals'})
    cond.pop('id', None)
    regla['outputKey'] = 'Reseña BAJA'
    idx = len(rules)
    rules.append(regla)
    outs = conns.setdefault(switch, {}).setdefault('main', [])
    while len(outs) < idx:
        outs.append([])
    outs.insert(idx, [edge(data_b['name'])])  # si la salida «extra» estaba conectada, queda corrida a idx+1

    nuevos = [gate, reg_t, ok_t, conf_t, send_t, back, data_b, reg_b, conf_b, send_b]
    ox, oy = sw['position']
    for i, n in enumerate(nuevos):
        n['position'] = [ox + 400 + (i % 5) * 240, oy + 1600 + (i // 5) * 220]
    w['nodes'].extend(nuevos)
    conns[source] = {'main': [[edge(gate['name'])]]}
    conns[gate['name']] = {'main': [[edge(reg_t['name'])], copy.deepcopy(old[0])]}
    conns[reg_t['name']] = {'main': [[edge(ok_t['name'])]]}
    conns[ok_t['name']] = {'main': [[edge(conf_t['name'])], [edge(back['name'])]]}
    conns[conf_t['name']] = {'main': [[edge(send_t['name'])]]}
    conns[back['name']] = {'main': [copy.deepcopy(old[0])]}
    conns[data_b['name']] = {'main': [[edge(reg_b['name'])]]}
    conns[reg_b['name']] = {'main': [[edge(conf_b['name'])]]}
    conns[conf_b['name']] = {'main': [[edge(send_b['name'])]]}
    return w


def patch_statuses(export, source, rest_credential='CONFIGURAR_AT_REST'):
    """Tap OFFLINE del Webhook Meta: conserva las conexiones y añade estados mínimos a la ruta existente."""
    w = copy.deepcopy(export)
    names = {n['name'] for n in w['nodes']}
    if source not in names or 'Reseña · Estados mínimos' in names:
        raise ValueError('Webhook de estados inexistente o tap ya instalado')
    normalize = node('Reseña · Estados mínimos', 'code', {'jsCode': """const root = $json.body || $json;
const estados = [];
for (const entry of root.entry || []) for (const change of entry.changes || []) {
  for (const s of change.value?.statuses || []) {
    if (['sent','delivered','read','failed'].includes(s.status) && typeof s.id === 'string')
      estados.push({wamid:s.id,estado:s.status,codigo:Number(s.errors?.[0]?.code || 0)});
  }
}
const out = []; for(let n=0;n<estados.length;n+=20) out.push({json:{estados:estados.slice(n,n+20)}});
return out;"""}, 0, 2)
    report = http('Reseña · Registrar estados', WP.rsplit('/resena', 1)[0] + '/propuesta-whatsapp-estado', rest_credential, 240, '={{ {estados:$json.estados} }}')
    w['nodes'].extend([normalize, report])
    connections = w.setdefault('connections', {}).setdefault(source, {}).setdefault('main', [[]])
    if not connections: connections.append([])
    connections[0].append(edge(normalize['name']))
    w['connections'][normalize['name']] = {'main': [[edge(report['name'])]]}
    return w

def verify_inactive(w):
    if 'activeVersion' not in w and 'activeVersionId' not in w:
        raise RuntimeError('La API no expone activeVersion/activeVersionId; verificación pendiente, detener')
    if w.get('active') is not False or w.get('activeVersion') is not None or w.get('activeVersionId') is not None:
        raise RuntimeError('El flujo no quedó inactivo/sin versión publicada. Detener; no se activa ni se reintenta.')


def apply_inactive(flows, backup_dir, api):
    """Inyección de API para pruebas. Solo crea; nunca PUT, activar ni publicar. Respalda antes de POST."""
    inventory = api('GET', '/workflows?limit=250')
    if inventory.get('nextCursor'):
        raise RuntimeError('Inventario paginado: revisar antes de crear para evitar duplicados')
    if {x['name'] for x in inventory.get('data', [])} & {x['name'] for x in flows}:
        raise RuntimeError('Ya existe un flujo con ese nombre; se conserva sin cambios')
    backup_dir.mkdir(parents=True, exist_ok=False)
    # Ningún payload remoto que pudiera contener secretos entra al respaldo.
    (backup_dir / 'propuestos-inactivos.json').write_text(json.dumps(flows, ensure_ascii=False, indent=2), encoding='utf-8')
    manifest = []
    for flow in flows:
        payload = {k: flow[k] for k in ('name', 'nodes', 'connections', 'settings')}
        created = api('POST', '/workflows', payload)
        manifest.append({'id': created['id'], 'name': flow['name']})
        (backup_dir / 'creados.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding='utf-8')
        verify_inactive(api('GET', '/workflows/' + created['id']))
    return manifest


def main(argv=None):
    p = argparse.ArgumentParser(description=__doc__)
    g = p.add_mutually_exclusive_group()
    g.add_argument('--dry-run', action='store_true'); g.add_argument('--apply', action='store_true')
    p.add_argument('--rest-credential', default='CONFIGURAR_AT_REST'); p.add_argument('--wa-credential', default='CONFIGURAR_WHATSAPP')
    p.add_argument('--webhook-credential', default='CONFIGURAR_AT_REST'); p.add_argument('--backup-dir')
    p.add_argument('--bot-export', type=Path); p.add_argument('--bot-source', default='Merge Data'); p.add_argument('--bot-status-source')
    args = p.parse_args(argv)
    flows = workflows(args.rest_credential, args.wa_credential, args.webhook_credential)
    if not args.apply:
        result = {'workflows': flows}
        if args.bot_export:
            bot = patch_bot(json.loads(args.bot_export.read_text(encoding='utf-8-sig')), args.rest_credential, args.bot_source)
            if args.bot_status_source: bot = patch_statuses(bot, args.bot_status_source, args.rest_credential)
            result['bot_export_modificado_solo_en_memoria'] = bot
        print(json.dumps(result, ensure_ascii=False, indent=2)); return 0
    if args.bot_export:
        p.error('El bot se adapta solo en seco; ownership de Eje')
    if any(v.startswith('CONFIGURAR') for v in (args.rest_credential,args.wa_credential,args.webhook_credential)) or not args.backup_dir:
        p.error('Se requieren referencias a credenciales y un directorio privado de respaldo')
    dest = Path(args.backup_dir).resolve()
    if dest == ROOT or ROOT in dest.parents or any(x.name.lower() == 'www' for x in dest.parents):
        p.error('El respaldo debe estar fuera del repo y webroot')
    url = os.environ.get('AT_N8N_URL', '').rstrip('/'); key = os.environ.get('AT_N8N_API_KEY', '')
    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme != 'https' or not parsed.hostname or parsed.username or parsed.password or parsed.query or parsed.fragment or not key:
        p.error('Falta AT_N8N_URL HTTPS o AT_N8N_API_KEY en entorno; no se leen archivos de claves')
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *args, **kwargs):
            return None  # Un redirect nunca reenvía X-N8N-API-KEY a otro destino.
    opener = urllib.request.build_opener(NoRedirect())
    def api(method, path, body=None):
        req = urllib.request.Request(url + '/api/v1' + path, method=method, data=None if body is None else json.dumps(body).encode(),
                                     headers={'X-N8N-API-KEY': key, 'Content-Type': 'application/json'})
        with opener.open(req, timeout=30) as response:
            return json.load(response)
    try:
        manifest = apply_inactive(flows, dest, api)
    except Exception:
        print('Aplicación detenida. Revisar manifiesto privado y estado real antes de reintentar; no se imprimen respuestas ni secretos.', file=sys.stderr); return 1
    print(json.dumps({'creados_inactivos': manifest}, ensure_ascii=False)); return 0


if __name__ == '__main__':
    sys.stdout.reconfigure(encoding='utf-8'); sys.exit(main())