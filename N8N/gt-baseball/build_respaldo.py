"""Construye (y con --publicar crea/actualiza y activa) el flujo n8n «GT Baseball · Respaldo del Sheet».

Cada 6 horas mira si el Google Sheet de inscripciones cambió y, si cambió, guarda una copia completa con fecha y
hora en una carpeta privada de contacto@automatizatech.cl que NO está compartida con la academia. Así, si alguien
con permiso de edición borra o cambia algo por error, queda una copia para recuperarlo (además del historial de
versiones que Google Sheets guarda solo).

- «Cambió» se decide comparando el modifiedTime del Sheet con el que quedó anotado en la última copia
  (appProperties.origenModified): sin cambios no se copia nada, así la carpeta no se llena de copias iguales.
- No borra copias viejas: borrar respaldos es decisión de Luis, a mano.
- Los ids (Sheet y carpeta) no están en el repo (es público): se leen de gt-config.json.

    python N8N/gt-baseball/build_respaldo.py <ruta>/gt-config.json              # escribe el JSON del flujo
    python N8N/gt-baseball/build_respaldo.py <ruta>/gt-config.json --probar     # corre una vez (flujo temporal)
    python N8N/gt-baseball/build_respaldo.py <ruta>/gt-config.json --publicar   # crea o actualiza y activa
"""
import json, os, sys, time, urllib.error, urllib.request, uuid

from n8n_api import call, WEBHOOK_BASE

NOMBRE = 'GT Baseball · Respaldo del Sheet'
DRIVE = {'googleDriveOAuth2Api': {'id': 'fXrMhILaDWpAj8Ue', 'name': 'Google Drive account'}}
CADA_HORAS = 6


def http(id_, name, pos, params):
    p = {'authentication': 'predefinedCredentialType', 'nodeCredentialType': 'googleDriveOAuth2Api', 'options': {}}
    p.update(params)
    return {'id': id_, 'name': name, 'type': 'n8n-nodes-base.httpRequest', 'typeVersion': 4.2, 'position': pos,
            'parameters': p, 'credentials': DRIVE}


def construir(cfg, disparador):
    for k in ('sheet_id', 'respaldo_folder_id'):
        if not cfg.get(k):
            raise SystemExit(f'Falta {k} en gt-config.json')
    sheet, carpeta = cfg['sheet_id'], cfg['respaldo_folder_id']
    nodes = [
        disparador,
        http('r1', 'Sheet original', [220, 0], {
            'method': 'GET', 'url': f'https://www.googleapis.com/drive/v3/files/{sheet}',
            'sendQuery': True, 'queryParameters': {'parameters': [{'name': 'fields', 'value': 'id,name,modifiedTime'}]}}),
        http('r2', 'Último respaldo', [440, 0], {
            'method': 'GET', 'url': 'https://www.googleapis.com/drive/v3/files',
            'sendQuery': True, 'queryParameters': {'parameters': [
                {'name': 'q', 'value': f"'{carpeta}' in parents and trashed = false"},
                {'name': 'orderBy', 'value': 'createdTime desc'},
                {'name': 'pageSize', 'value': '1'},
                {'name': 'fields', 'value': 'files(id,name,createdTime,appProperties)'}]}}),
        {'id': 'r3', 'name': '¿Cambió?', 'type': 'n8n-nodes-base.if', 'typeVersion': 2, 'position': [660, 0],
         'parameters': {'conditions': {
             'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'strict'},
             'conditions': [{'id': 'r3c', 'leftValue':
                 "={{ !((($json.files || [])[0] || {}).appProperties || {}).origenModified"
                 " || (($json.files[0].appProperties || {}).origenModified !== $('Sheet original').first().json.modifiedTime) }}",
                 'rightValue': True, 'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
             'combinator': 'and'}, 'options': {}}},
        http('r4', 'Copiar', [880, -80], {
            'method': 'POST', 'url': f'https://www.googleapis.com/drive/v3/files/{sheet}/copy',
            'sendQuery': True, 'queryParameters': {'parameters': [{'name': 'fields', 'value': 'id,name,createdTime'}]},
            'sendBody': True, 'specifyBody': 'json',
            'jsonBody': "={{ JSON.stringify({ name: 'GT Baseball · Inscripciones · respaldo ' + "
                        "$now.setZone('America/Caracas').toFormat('yyyy-LL-dd HH:mm'), parents: ['" + carpeta + "'], "
                        "appProperties: { origenModified: $('Sheet original').first().json.modifiedTime } }) }}"}),
    ]
    enlaces = [(disparador['name'], 'Sheet original'), ('Sheet original', 'Último respaldo'),
               ('Último respaldo', '¿Cambió?')]
    connections = {a: {'main': [[{'node': b, 'type': 'main', 'index': 0}]]} for a, b in enlaces}
    connections['¿Cambió?'] = {'main': [[{'node': 'Copiar', 'type': 'main', 'index': 0}], []]}
    return {'name': NOMBRE, 'nodes': nodes, 'connections': connections,
            'settings': {'executionOrder': 'v1', 'timezone': 'America/Caracas'}}


def programado():
    return {'id': 'r0', 'name': 'Cada 6 horas', 'type': 'n8n-nodes-base.scheduleTrigger', 'typeVersion': 1.2,
            'position': [0, 0], 'parameters': {'rule': {'interval': [
                {'field': 'hours', 'hoursInterval': CADA_HORAS, 'triggerAtMinute': 5}]}}}


def probar(cfg):
    """Corre el mismo flujo una vez, con un webhook temporal en vez del horario, y lo borra."""
    path = 'temp-gt-respaldo-' + uuid.uuid4().hex[:10]
    gancho = {'id': 'r0', 'name': 'Probar', 'type': 'n8n-nodes-base.webhook', 'typeVersion': 2, 'position': [0, 0],
              'webhookId': path, 'parameters': {'httpMethod': 'POST', 'path': path, 'responseMode': 'lastNode', 'options': {}}}
    wf = construir(cfg, gancho)
    wf['name'] = 'TEMP ' + NOMBRE
    # Solo en la prueba: la rama «sin cambios» necesita un nodo final para que el webhook tenga qué responder.
    wf['nodes'].append({'id': 'r9', 'name': 'Sin cambios', 'type': 'n8n-nodes-base.code', 'typeVersion': 2,
                        'position': [880, 80], 'parameters': {'jsCode': 'return [{ json: { sin_cambios: true } }];'}})
    wf['connections']['¿Cambió?']['main'][1] = [{'node': 'Sin cambios', 'type': 'main', 'index': 0}]
    wid = call('POST', '/workflows', wf)['id']
    try:
        call('POST', f'/workflows/{wid}/activate')
        for intento in range(8):
            try:
                raw = urllib.request.urlopen(urllib.request.Request(WEBHOOK_BASE + path, method='POST', data=b'{}',
                                             headers={'Content-Type': 'application/json'}), timeout=120).read()
                break
            except urllib.error.HTTPError as e:
                if e.code != 404 or intento == 7:
                    raise
                time.sleep(1.5)
        r = json.loads(raw) if raw else {}
        r = r[0] if isinstance(r, list) and r else r
        if r.get('id') and r.get('name'):
            print('COPIÓ:', r['name'])
        elif r.get('sin_cambios'):
            print('SIN CAMBIOS: no copió (la última copia ya es del Sheet actual)')
        else:
            print('RESPUESTA INESPERADA:', json.dumps(r, ensure_ascii=False)[:300])
    finally:
        try:
            call('POST', f'/workflows/{wid}/deactivate')
        finally:
            call('DELETE', f'/workflows/{wid}')


def publicar(wf):
    existentes, cursor = [], None
    while True:
        resp = call('GET', '/workflows?limit=250' + (f'&cursor={cursor}' if cursor else ''))
        existentes += [w for w in resp.get('data', []) if w['name'] == wf['name']]
        cursor = resp.get('nextCursor')
        if not cursor:
            break
    if existentes:
        wid = existentes[0]['id']; call('PUT', f'/workflows/{wid}', wf); accion = 'actualizado'
    else:
        wid = call('POST', '/workflows', wf)['id']; accion = 'creado'
    call('POST', f'/workflows/{wid}/activate')
    w = call('GET', f'/workflows/{wid}')
    print(f"{accion}: {w['name']} id={wid} activo={w['active']} nodos={len(w['nodes'])}")


def main():
    if len(sys.argv) < 2:
        raise SystemExit(__doc__)
    cfg_path = os.path.abspath(sys.argv[1])
    cfg = json.load(open(cfg_path, encoding='utf-8'))
    if '--probar' in sys.argv:
        probar(cfg)
        return
    wf = construir(cfg, programado())
    out = os.path.join(os.path.dirname(cfg_path), 'gt-baseball-respaldo.json')
    json.dump(wf, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print('escrito', out, len(wf['nodes']), 'nodos')
    if '--publicar' in sys.argv:
        publicar(wf)


if __name__ == '__main__':
    main()
