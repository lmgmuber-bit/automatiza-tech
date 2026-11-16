"""Pone el nodo «Limpiar sesión» en «Agente Tech Web (PROD)» (3A5Y1NPBxpTjhHdE): Switch Action (saveLead) → Limpiar sesión
→ Check Availability. Idempotente: si el nodo ya existe, solo actualiza su código. No imprime secretos.

Uso:  python -P N8N/web-sesion/aplicar.py --ver   (solo dice qué haría)
      python -P N8N/web-sesion/aplicar.py         (respalda, sube y verifica la versión activa)

- La clave de n8n se lee de ~/.claude.json (servidor n8n-mcp) y nunca se imprime.
- Respaldo del flujo, antes de tocarlo, en ~/respaldos/n8n/web-sesion/<fecha>-antes.json.
- PUT con {name, nodes, connections, settings} y settings filtrado (como N8N/instagram/aplicar.py).
- Si el flujo estaba activo, se vuelve a activar para publicar la versión nueva; nunca cambia su estado.
"""
import datetime
import json
import os
import sys
import urllib.error
import urllib.request

sys.stdout.reconfigure(encoding='utf-8')
AQUI = os.path.dirname(os.path.abspath(__file__))
WF = '3A5Y1NPBxpTjhHdE'
NODO = 'Limpiar sesión'
ORIGEN, DESTINO = 'Switch Action', 'Check Availability'
SETTINGS_OK = {'executionOrder', 'timezone', 'saveDataErrorExecution', 'saveDataSuccessExecution',
               'saveManualExecutions', 'saveExecutionProgress', 'executionTimeout', 'errorWorkflow', 'callerPolicy'}

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


def preparar(w):
    js = open(os.path.join(AQUI, 'limpiar-sesion.js'), encoding='utf-8').read()
    nodos = w['nodes']
    nombres = {n['name'] for n in nodos}
    if ORIGEN not in nombres or DESTINO not in nombres:
        raise SystemExit(f'falta «{ORIGEN}» o «{DESTINO}» en el flujo: no se sube nada')
    con = w['connections']
    salidas = con.get(ORIGEN, {}).get('main', [])
    if len(salidas) < 2:
        raise SystemExit('«Switch Action» no tiene la salida de saveLead: no se sube nada')
    existente = next((n for n in nodos if n['name'] == NODO), None)
    if existente:
        existente['parameters']['jsCode'] = js
        accion = 'actualiza el código del nodo'
    else:
        dest = next(n for n in nodos if n['name'] == DESTINO)
        x, y = dest.get('position', [0, 0])
        nodos.append({'id': 'web-limpiar-sesion', 'name': NODO, 'type': 'n8n-nodes-base.code', 'typeVersion': 2,
                      'position': [x - 200, y], 'parameters': {'jsCode': js}})
        accion = 'agrega el nodo'
    # saveLead (salida 1) → Limpiar sesión → Check Availability
    salidas[1] = [{'node': NODO, 'type': 'main', 'index': 0}]
    con[NODO] = {'main': [[{'node': DESTINO, 'type': 'main', 'index': 0}]]}
    return accion


def verificar():
    c, w = api('GET', f'/workflows/{WF}')
    act = w.get('activeVersion') or {}
    nodos = {n['name']: n for n in act.get('nodes', [])}
    con = act.get('connections', {})
    ok_nodo = NODO in nodos and 'web-' in nodos[NODO]['parameters'].get('jsCode', '')
    ok_entrada = con.get(ORIGEN, {}).get('main', [[], []])[1] == [{'node': NODO, 'type': 'main', 'index': 0}]
    ok_salida = con.get(NODO, {}).get('main', [[]])[0] == [{'node': DESTINO, 'type': 'main', 'index': 0}]
    print('activo:', w.get('active'), '| nodo en la versión activa:', ok_nodo, '| saveLead → Limpiar sesión:', ok_entrada,
          '| Limpiar sesión → Check Availability:', ok_salida)
    return w.get('active') and ok_nodo and ok_entrada and ok_salida


def main():
    ver = '--ver' in sys.argv
    c, w = api('GET', f'/workflows/{WF}')
    if c != 200:
        raise SystemExit(f'no se pudo leer el flujo ({c})')
    activo = bool(w.get('active'))
    accion = preparar(w)
    print(f'«{w["name"]}» activo={activo}: {accion} y cablea {ORIGEN} (saveLead) → {NODO} → {DESTINO}')
    if ver:
        print('modo vista previa: no se subió nada')
        return
    carpeta = os.path.expanduser('~/respaldos/n8n/web-sesion')
    os.makedirs(carpeta, exist_ok=True)
    c0, original = api('GET', f'/workflows/{WF}')
    ruta = os.path.join(carpeta, datetime.datetime.now().strftime('%Y%m%d-%H%M%S') + '-antes.json')
    json.dump(original, open(ruta, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print('respaldo:', ruta)
    cuerpo = {'name': w['name'], 'nodes': w['nodes'], 'connections': w['connections'],
              'settings': {k: v for k, v in (w.get('settings') or {}).items() if k in SETTINGS_OK}}
    c, r = api('PUT', f'/workflows/{WF}', cuerpo)
    print('PUT:', c, '' if c == 200 else r)
    if c != 200:
        raise SystemExit('el PUT falló: el flujo quedó como estaba')
    if activo:
        c2, _ = api('POST', f'/workflows/{WF}/activate')
        print('reactivado para publicar:', c2)
    if not verificar():
        raise SystemExit('la verificación falló: restaurar con el respaldo de arriba')
    print('listo')


if __name__ == '__main__':
    main()
