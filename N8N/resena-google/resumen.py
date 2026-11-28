"""Reseñas: flujo n8n «Reseña · Resumen» (correo a Luis 15 min después de una tanda de envíos) y su llamada desde el diario.
Por defecto solo JSON, sin credenciales, archivos ni red:  python -B N8N/resena-google/resumen.py --dry-run
--apply (encargo explícito de Luis): crea «Reseña · Resumen» INACTIVO y agrega al diario remoto «Pedir resumen (una vez)».
Nunca activa el flujo nuevo ni imprime respuestas de la API.
"""
import argparse
import copy
import importlib.util
import json
import os
from pathlib import Path
import sys
import urllib.parse
import urllib.request
import uuid

_DIR = Path(__file__).resolve().parent
_spec = importlib.util.spec_from_file_location('resena_aplicar_para_resumen', _DIR / 'aplicar.py')
aplicar = importlib.util.module_from_spec(_spec); _spec.loader.exec_module(aplicar)
node, edge, http, WP = aplicar.node, aplicar.edge, aplicar.http, aplicar.WP

NOMBRE = 'Reseña · Resumen'
WEBHOOK_PATH = 'at-resena-resumen'
WEBHOOK_URL = 'https://n8n-n8n.kchiba.easypanel.host/webhook/' + WEBHOOK_PATH  # la misma que llama WordPress tras un envío manual
RECLAMAR = 'Reclamar (WP envía correo y dispara WA)'
SEPARAR = 'Separar proyectos'
PEDIR_UNA = 'Pedir resumen (una vez)'
# Mismo corte que el diario base, con un «desde» común a toda la tanda (un minuto antes, para no perder el primer envío).
SEPARAR_JS = ("const desde = new Date(Date.now() - 60000).toISOString();\n"
              "return ($input.first().json.pendientes || []).slice(0,10).map(p => ({json:{...p, desde}}));")
SETTINGS_QUE_REBOTAN = ('timeSavedMode', 'availableInMCP', 'binaryMode')  # la API pública no los acepta de vuelta en un PUT


def _codigo_correo():
    lineas = (_DIR / 'resumen-correo.js').read_text(encoding='utf-8').splitlines()
    cuerpo = '\n'.join(l for l in lineas if 'module.exports' not in l).rstrip()
    # Modo por defecto «Run Once for All Items»: ahí n8n no define $json; el resumen es el único ítem de entrada.
    return cuerpo + "\nreturn [{ json: armarCorreo($input.first().json, $('Webhook resumen').first().json.body.origen) }];\n"


def workflow(rest_credential, webhook_credential, smtp_credential, remitente):
    webhook = node('Webhook resumen', 'webhook', {'path': WEBHOOK_PATH, 'httpMethod': 'POST', 'authentication': 'headerAuth',
                                                  'responseMode': 'onReceived', 'options': {}}, 0, 2,
                   webhookId=str(uuid.uuid5(uuid.NAMESPACE_URL, WEBHOOK_PATH)),
                   credentials={'httpHeaderAuth': {'id': webhook_credential, 'name': 'AT REST (entrada X-AT-Secret)'}})
    espera = node('Esperar 15 minutos', 'wait', {'amount': 15, 'unit': 'minutes'}, 240, 1.1,
                  webhookId=str(uuid.uuid5(uuid.NAMESPACE_URL, WEBHOOK_PATH + ':espera')))
    pedir = http('Pedir resumen', '={{ "' + WP + '/resumen?desde=" + encodeURIComponent($(\'Webhook resumen\').first().json.body.desde) }}',
                 rest_credential, 480, method='GET', full=False)
    # Si WordPress falla, la ejecución queda en error y el flujo común de avisos de error (errorWorkflow) avisa:
    # sin continueRegularOutput ni neverError. La lectura es idempotente, por eso se reintenta.
    pedir.update(retryOnFail=True, maxTries=3, waitBetweenTries=5000)
    del pedir['onError']
    pedir['parameters']['options']['response']['response']['neverError'] = False
    armar = node('Armar correo', 'code', {'jsCode': _codigo_correo()}, 720, 2)
    gate = node('¿Hubo envíos?', 'if', {'conditions': {'options': {'typeValidation': 'strict'}, 'conditions': [
        {'id': 'enviar', 'leftValue': '={{ $json.enviar }}', 'rightValue': True, 'operator': {'type': 'boolean', 'operation': 'equals'}}],
        'combinator': 'and'}, 'options': {}}, 960, 2.2)
    mail = node('Enviar correo a Luis', 'emailSend', {'fromEmail': remitente, 'toEmail': '={{ $json.aviso_a }}', 'subject': '={{ $json.asunto }}',
                                                      'emailFormat': 'html', 'html': '={{ $json.html }}',
                                                      'options': {'appendAttribution': False}}, 1200, 2.1,
                credentials={'smtp': {'id': smtp_credential, 'name': 'SMTP (configurar en n8n)'}})
    nodos = [webhook, espera, pedir, armar, gate, mail]
    conns = {a['name']: {'main': [[edge(b['name'])]]} for a, b in zip(nodos[:4], nodos[1:5])}
    conns[gate['name']] = {'main': [[edge(mail['name'])], []]}
    settings = copy.deepcopy(aplicar.workflows(resumen=False)[0]['settings'])
    settings['errorWorkflow'] = 'm7TOfKznVSBGz4Nd'  # flujo común de avisos de error (el mismo de N8N/plan-trabajo)
    settings['executionTimeout'] = 1800  # solo este flujo: la espera de 15 min no cabe en los 300 s de los demás
    return {'name': NOMBRE, 'active': False, 'settings': settings, 'nodes': nodos, 'connections': conns}


def patch_daily(daily, rest_credential):
    """Copia del diario con el «desde» común y una sola llamada al resumen al final. No toca el original."""
    w = copy.deepcopy(daily)
    by = {n['name']: n for n in w['nodes']}
    if PEDIR_UNA in by:
        raise ValueError('El diario ya llama al resumen')
    if SEPARAR not in by or RECLAMAR not in by:
        raise ValueError('Faltan nodos del diario: ' + ', '.join(n for n in (SEPARAR, RECLAMAR) if n not in by))
    if by[SEPARAR]['parameters'].get('jsCode') != aplicar.SEPARAR_JS:
        raise ValueError('«Separar proyectos» no es el del repositorio; revisar a mano antes de parchar')
    by[SEPARAR]['parameters']['jsCode'] = SEPARAR_JS
    pedir = http(PEDIR_UNA, WEBHOOK_URL, rest_credential, 0,
                 body="={{ {desde: $('Separar proyectos').first().json.desde, origen: 'diario'} }}")
    pedir['executeOnce'] = True
    x, y = by[RECLAMAR].get('position', [720, 0])
    pedir['position'] = [x + 240, y]
    w['nodes'].append(pedir)
    salidas = w.setdefault('connections', {}).setdefault(RECLAMAR, {}).setdefault('main', [])
    if not salidas: salidas.append([])
    salidas[0].append(edge(PEDIR_UNA))
    return w


def aplicar_flujos(resumen, daily_id, rest_credential, backup_dir, api):
    """Inyección de API para pruebas. Respalda el diario, crea el resumen inactivo, parcha el diario y verifica."""
    inventory = api('GET', '/workflows?limit=250')
    if inventory.get('nextCursor'):
        raise RuntimeError('Inventario paginado: revisar antes de crear para evitar duplicados')
    if NOMBRE in {x.get('name') for x in inventory.get('data', [])}:
        raise RuntimeError('Ya existe «Reseña · Resumen»; se conserva sin cambios')
    remoto = api('GET', '/workflows/' + daily_id)
    parchado = patch_daily(remoto, rest_credential)  # falla antes de tocar nada si ya está parchado o difiere
    backup_dir.mkdir(parents=True, exist_ok=False)
    (backup_dir / 'diario-antes.json').write_text(json.dumps(remoto, ensure_ascii=False, indent=2), encoding='utf-8')
    creado = api('POST', '/workflows', {k: resumen[k] for k in ('name', 'nodes', 'connections', 'settings')})
    (backup_dir / 'resumen-creado.json').write_text(json.dumps({'id': creado['id'], 'name': NOMBRE}, ensure_ascii=False), encoding='utf-8')
    aplicar.verify_inactive(api('GET', '/workflows/' + creado['id']))
    settings = {k: v for k, v in (parchado.get('settings') or {}).items() if k not in SETTINGS_QUE_REBOTAN}
    api('PUT', '/workflows/' + daily_id, {'name': parchado['name'], 'nodes': parchado['nodes'],
                                          'connections': parchado['connections'], 'settings': settings})
    despues = api('GET', '/workflows/' + daily_id)
    if despues.get('versionId') != despues.get('activeVersionId'):
        raise RuntimeError('El diario quedó con cambios sin publicar; revisar en n8n')
    return {'resumen_id': creado['id'], 'diario_id': daily_id}


def main(argv=None):
    p = argparse.ArgumentParser(description=__doc__)
    g = p.add_mutually_exclusive_group()
    g.add_argument('--dry-run', action='store_true'); g.add_argument('--apply', action='store_true')
    for nombre in ('--rest-credential', '--webhook-credential', '--smtp-credential', '--remitente', '--daily-id', '--backup-dir'):
        p.add_argument(nombre)
    args = p.parse_args(argv)
    if not args.apply:
        # En seco siempre con marcadores: un remitente o id real pasado por error no sale en pantalla.
        flows = [workflow('CONFIGURAR_AT_REST', 'CONFIGURAR_AT_REST', 'CONFIGURAR_SMTP', 'CONFIGURAR_REMITENTE'), aplicar.workflows()[0]]
        print(json.dumps({'workflows': flows}, ensure_ascii=False, indent=2)); return 0
    valores = (args.rest_credential, args.webhook_credential, args.smtp_credential, args.remitente, args.daily_id, args.backup_dir)
    if any(not v or v.startswith('CONFIGURAR') for v in valores):
        p.error('Se requieren --rest-credential, --webhook-credential, --smtp-credential, --remitente, --daily-id y --backup-dir')
    dest = Path(args.backup_dir).resolve()
    if dest == aplicar.ROOT or aplicar.ROOT in dest.parents or any(x.name.lower() == 'www' for x in dest.parents):
        p.error('El respaldo debe estar fuera del repo y webroot')
    url = os.environ.get('AT_N8N_URL', '').rstrip('/'); key = os.environ.get('AT_N8N_API_KEY', '')
    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme != 'https' or not parsed.hostname or parsed.username or parsed.password or parsed.query or parsed.fragment or not key:
        p.error('Falta AT_N8N_URL HTTPS o AT_N8N_API_KEY en entorno; no se leen archivos de claves')

    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None  # Un redirect nunca reenvía X-N8N-API-KEY a otro destino.
    opener = urllib.request.build_opener(NoRedirect())

    def api(method, path, body=None):
        req = urllib.request.Request(url + '/api/v1' + path, method=method, data=None if body is None else json.dumps(body).encode(),
                                     headers={'X-N8N-API-KEY': key, 'Content-Type': 'application/json'})
        with opener.open(req, timeout=30) as response:
            return json.load(response)
    try:
        res = aplicar_flujos(workflow(args.rest_credential, args.webhook_credential, args.smtp_credential, args.remitente),
                             args.daily_id, args.rest_credential, dest, api)
    except Exception:
        print('Aplicación detenida. Revisar el respaldo privado y el estado real antes de reintentar; no se imprimen respuestas ni secretos.',
              file=sys.stderr); return 1
    print(json.dumps({'resumen_inactivo': res['resumen_id'], 'diario_parchado': res['diario_id']}, ensure_ascii=False)); return 0


if __name__ == '__main__':
    sys.stdout.reconfigure(encoding='utf-8'); sys.exit(main())
