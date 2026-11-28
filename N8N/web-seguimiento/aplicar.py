"""Pestaña «Seguimiento» del formulario de agenda de automatizatech.cl (pedido de Luis, 17-nov-2026).

El formulario manda appointmentType='Seguimiento'. El subflujo de agenda, que ya maneja ese tipo para el bot de WhatsApp,
ahora además:
  1. Si la cita viene de la web (session_id «web-…», lo pone «Limpiar sesión»), revisa con wa-contexto si el teléfono
     es cliente o tiene propuesta, y si el correo coincide con el de su ficha cuando la ficha lo trae. Si no, la agenda
     como diagnóstico (appointmentType='Demo'): queda como lead y recibe el correo de siempre (decisión de Luis).
  2. Guarda el seguimiento con source='web' (antes siempre 'whatsapp').
  3. Devuelve el tipo final en el mensaje de éxito, y el flujo web lo pasa al navegador, que así no cuenta como lead a un
     cliente.
Lo que llega por WhatsApp no cambia: su session_id no empieza con «web-».

Flujos: «Agendar Reunión AutomatizaTech - Cliente (PROD)» (PejBgr2LZQSAbbBy) y «Agente Tech Web (PROD)» (3A5Y1NPBxpTjhHdE).
Dueño: Eje. Uso:
  python -P N8N/web-seguimiento/aplicar.py --ver   (dice qué cambiaría; no sube nada)
  python -P N8N/web-seguimiento/aplicar.py         (respalda, sube, republica si estaba activo y verifica la versión activa)
La clave de n8n se lee de ~/.claude.json y nunca se imprime. Respaldos en ~/respaldos/n8n/web-seguimiento/.
Idempotente: si un cambio ya está, lo salta.
"""
import datetime
import json
import os
import re
import sys
import urllib.error
import urllib.request

sys.stdout.reconfigure(encoding='utf-8')
SUB, WEB = 'PejBgr2LZQSAbbBy', '3A5Y1NPBxpTjhHdE'
SETTINGS_OK = {'executionOrder', 'timezone', 'saveDataErrorExecution', 'saveDataSuccessExecution',
               'saveManualExecutions', 'saveExecutionProgress', 'executionTimeout', 'errorWorkflow', 'callerPolicy'}

N = "$('Normalize Data').item.json"
APPT_ANTES = "={{ " + N + ".appointmentType }}"
APPT_NUEVO = ("={{ " + N + ".appointmentType === 'Seguimiento' && String(" + N + ".session_id || '').startsWith('web-')"
              " && !(['cliente', 'prospecto'].includes($json.situacion) && (!$json.agenda?.correo"
              " || String($json.agenda.correo).trim().toLowerCase() === String(" + N + ".email || '').trim().toLowerCase()))"
              " ? 'Demo' : " + N + ".appointmentType }}")
SRC_ANTES = "source: 'whatsapp'"
SRC_NUEVO = "source: (String($('Merge Client Data').item.json.session_id || '').startsWith('web-') ? 'web' : 'whatsapp')"
TIPO_EXITO = {'name': 'appointmentType', 'value': "={{ $('Merge Client Data').item.json.appointmentType }}"}
RESP_ANTES = "={{ JSON.stringify({ text: $json.result }) }}"
RESP_NUEVO = "={{ JSON.stringify({ text: $json.result, appointmentType: $json.appointmentType || '' }) }}"

env = json.load(open(os.path.expanduser('~/.claude.json'), encoding='utf-8'))['mcpServers']['n8n-mcp']['env']
URL, KEY = env['N8N_API_URL'].rstrip('/'), env['N8N_API_KEY']


def api(metodo, ruta, cuerpo=None):
    datos = json.dumps(cuerpo).encode() if cuerpo is not None else (b'{}' if metodo == 'POST' else None)
    req = urllib.request.Request(URL + '/api/v1' + ruta, data=datos, method=metodo,
                                 headers={'X-N8N-API-KEY': KEY, 'Content-Type': 'application/json'})
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, json.load(r)
    except urllib.error.HTTPError as e:
        return e.code, {'error': e.read().decode('utf-8', 'replace')[:300]}


def nodo(w, nombre):
    hits = [n for n in w['nodes'] if n['name'] == nombre]
    if len(hits) != 1:
        raise SystemExit(f'nodo «{nombre}» aparece {len(hits)} veces en «{w["name"]}»')
    return hits[0]


def preparar_sub(w):
    hechos = []
    merge = nodo(w, 'Merge Client Data')
    a = [x for x in merge['parameters']['assignments']['assignments'] if x['name'] == 'appointmentType']
    assert len(a) == 1
    if a[0]['value'] == APPT_ANTES:
        a[0]['value'] = APPT_NUEVO; hechos.append('Merge Client Data: tipo según cliente (solo web)')
    elif a[0]['value'] != APPT_NUEVO:
        raise SystemExit('Merge Client Data.appointmentType no es el esperado: revisar a mano')
    prep = nodo(w, 'Prepare Payload')
    j = [x for x in prep['parameters']['assignments']['assignments'] if x['name'] == 'json']
    assert len(j) == 1
    if SRC_ANTES in j[0]['value']:
        assert j[0]['value'].count(SRC_ANTES) == 1
        j[0]['value'] = j[0]['value'].replace(SRC_ANTES, SRC_NUEVO); hechos.append("Prepare Payload: source 'web' desde la web")
    elif SRC_NUEVO not in j[0]['value']:
        raise SystemExit('Prepare Payload no trae el source esperado: revisar a mano')
    exito = nodo(w, 'Set Success Message')
    cadenas = exito['parameters']['values']['string']
    if not any(c['name'] == 'appointmentType' for c in cadenas):
        cadenas.append(dict(TIPO_EXITO)); hechos.append('Set Success Message: devuelve appointmentType')
    return hechos


def preparar_web(w):
    resp = nodo(w, 'Respond Lead')
    if resp['parameters'].get('responseBody') == RESP_ANTES:
        resp['parameters']['responseBody'] = RESP_NUEVO
        return ['Respond Lead: pasa appointmentType al navegador']
    if resp['parameters'].get('responseBody') != RESP_NUEVO:
        raise SystemExit('Respond Lead no tiene el cuerpo esperado: revisar a mano')
    return []


def verificar(wid, chequeo):
    c, w = api('GET', f'/workflows/{wid}')
    act = w.get('activeVersion') or {}
    nodos = {n['name']: n for n in act.get('nodes', [])}
    ok = chequeo(nodos)
    print(f'  {w["name"]}: activo={w.get("active")} versión activa con el cambio={ok}')
    return bool(w.get('active')) and ok


def chequeo_sub(nodos):
    s = json.dumps([nodos.get('Merge Client Data'), nodos.get('Prepare Payload'), nodos.get('Set Success Message')], ensure_ascii=False)
    return "startsWith('web-')" in s and SRC_NUEVO in s and '"appointmentType", "value": "={{ $(\'Merge Client Data\')' in s


def chequeo_web(nodos):
    return (nodos.get('Respond Lead') or {}).get('parameters', {}).get('responseBody') == RESP_NUEVO


def subir(wid, preparar, ver, carpeta, marca):
    c, w = api('GET', f'/workflows/{wid}')
    if c != 200:
        raise SystemExit(f'no se pudo leer {wid} ({c})')
    activo = bool(w.get('active'))
    original = json.loads(json.dumps(w))
    hechos = preparar(w)
    print(f'«{w["name"]}» activo={activo}:', '; '.join(hechos) if hechos else 'ya estaba aplicado')
    if ver or not hechos:
        return activo
    ruta = os.path.join(carpeta, f'{marca}-{wid}-antes.json')
    json.dump(original, open(ruta, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print('  respaldo:', ruta)
    cuerpo = {'name': w['name'], 'nodes': w['nodes'], 'connections': w['connections'],
              'settings': {k: v for k, v in (w.get('settings') or {}).items() if k in SETTINGS_OK}}
    c, r = api('PUT', f'/workflows/{wid}', cuerpo)
    print('  PUT:', c, '' if c == 200 else r)
    if c != 200:
        raise SystemExit('el PUT falló: el flujo quedó como estaba')
    if activo:
        c2, _ = api('POST', f'/workflows/{wid}/activate')
        print('  republicado:', c2)
    return activo


def main():
    ver = '--ver' in sys.argv
    carpeta = os.path.expanduser('~/respaldos/n8n/web-seguimiento')
    os.makedirs(carpeta, exist_ok=True)
    marca = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
    # Primero el subflujo: con él solo, la web todavía no manda el tipo y nada cambia para nadie.
    subir(SUB, preparar_sub, ver, carpeta, marca)
    subir(WEB, preparar_web, ver, carpeta, marca)
    if ver:
        print('modo vista previa: no se subió nada')
        return
    ok = verificar(SUB, chequeo_sub) and verificar(WEB, chequeo_web)
    if not ok:
        raise SystemExit('la verificación falló: restaurar con los respaldos de arriba')
    print('listo')


if __name__ == '__main__':
    main()
