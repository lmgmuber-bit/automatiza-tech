"""Pone la revisión de Cloudflare Turnstile en «Agente Tech Web (PROD)» (3A5Y1NPBxpTjhHdE), antes de guardar una cita:

  Switch Action (saveLead) → Verificar Turnstile → ¿Turnstile OK?
        sí → Pedido original → Limpiar sesión → Check Availability → Respond Lead   (como antes)
        no → Rechazo Turnstile (HTTP 403, la cita no se guarda)

Uso:  python -P N8N/web-turnstile/aplicar.py --ver   (solo dice qué haría; no lee la clave ni sube nada)
      python -P N8N/web-turnstile/aplicar.py         (crea la credencial si falta, respalda, sube y verifica)

- La clave secreta se lee del archivo de claves por su etiqueta exacta «TURNSTILE AT SECRET» (valor en la misma línea,
  «ETIQUETA : valor»), queda solo en memoria y va directo a la credencial «AT Turnstile Secret (web)» de n8n
  (httpCustomAuth, solo para challenges.cloudflare.com). Nunca se imprime ni se escribe en el repo.
- La clave de n8n se lee de ~/.claude.json (servidor n8n-mcp) y nunca se imprime.
- Respaldo del flujo, antes de tocarlo, en ~/respaldos/n8n/web-turnstile/<fecha>-antes.json.
- PUT con {name, nodes, connections, settings} y settings filtrado; si el flujo estaba activo se reactiva para publicar.
- Idempotente: si los nodos ya existen, solo actualiza sus parámetros y el cableado.
"""
import datetime
import json
import os
import re
import sys
import urllib.error
import urllib.request

sys.stdout.reconfigure(encoding='utf-8')
WF = '3A5Y1NPBxpTjhHdE'
CRED = 'AT Turnstile Secret (web)'
ETIQUETA = 'TURNSTILE AT SECRET'
ARCHIVO_CLAVES = r"C:/Users/luis_/OneDrive/Documentos/APIS KEy/APIS KEY.txt"
SWITCH, LIMPIAR = 'Switch Action', 'Limpiar sesión'
VERIFICAR, SI_OK, ORIGINAL, RECHAZO = 'Verificar Turnstile', '¿Turnstile OK?', 'Pedido original', 'Rechazo Turnstile'
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
        return e.code, {'error': re.sub(r'0x[0-9A-Za-z_-]{20,}', '[oculto]', e.read().decode('utf-8', 'replace'))[:300]}


def leer_secreto():
    lineas = open(ARCHIVO_CLAVES, encoding='utf-8', errors='ignore').read().splitlines()
    hallados = [l for l in lineas if l.strip().upper().startswith(ETIQUETA)]
    if len(hallados) != 1:
        raise SystemExit(f'la etiqueta «{ETIQUETA}» aparece {len(hallados)} veces: pedirle a Luis que la deje una sola vez')
    m = re.match(r'\s*[:=]\s*(\S+)\s*$', hallados[0].strip()[len(ETIQUETA):])
    if not m:
        raise SystemExit(f'la etiqueta «{ETIQUETA}» no trae el valor en la misma línea («ETIQUETA : valor»)')
    valor = m.group(1)
    if not (30 <= len(valor) <= 60) or ' ' in valor:
        raise SystemExit('el valor no tiene la forma de una clave secreta de Turnstile: no se usa')
    return valor


def credencial_id(crear):
    """Id de la credencial; la crea solo si falta y crear=True. La API pública no lista credenciales en todas las
    versiones, así que el id se guarda junto al respaldo para no crear duplicados."""
    marca = os.path.expanduser('~/respaldos/n8n/web-turnstile/credencial-id.txt')
    if os.path.exists(marca):
        return open(marca, encoding='utf-8').read().strip(), 'existente'
    if not crear:
        return None, 'se crearía'
    cuerpo = {'name': CRED, 'type': 'httpCustomAuth',
              'data': {'json': json.dumps({'body': {'secret': leer_secreto()}}),
                       'allowedHttpRequestDomains': 'domains', 'allowedDomains': 'challenges.cloudflare.com'}}
    c, r = api('POST', '/credentials', cuerpo)
    if c not in (200, 201) or 'id' not in r:
        raise SystemExit(f'no se pudo crear la credencial ({c}): {r.get("error", "")}')
    os.makedirs(os.path.dirname(marca), exist_ok=True)
    open(marca, 'w', encoding='utf-8').write(r['id'])
    return r['id'], 'creada'


def nodo(nodos, nombre, tipo, version, pos, params, extra=None):
    n = next((x for x in nodos if x['name'] == nombre), None)
    if n is None:
        n = {'id': 'web-ts-' + re.sub(r'[^a-z]', '', nombre.lower())[:20], 'name': nombre}
        nodos.append(n)
    n.update({'type': tipo, 'typeVersion': version, 'position': pos, 'parameters': params})
    n.update(extra or {})
    return n


def preparar(w, cred_id):
    nodos, con = w['nodes'], w['connections']
    nombres = {n['name'] for n in nodos}
    if SWITCH not in nombres or LIMPIAR not in nombres:
        raise SystemExit(f'falta «{SWITCH}» o «{LIMPIAR}»: no se sube nada')
    salidas = con.get(SWITCH, {}).get('main', [])
    if len(salidas) < 2 or not salidas[1]:
        raise SystemExit('«Switch Action» no tiene la salida de saveLead: no se sube nada')
    x, y = next(n for n in nodos if n['name'] == LIMPIAR).get('position', [1432, 608])
    cred = {'httpCustomAuth': {'id': cred_id or 'PENDIENTE', 'name': CRED}}
    nodo(nodos, VERIFICAR, 'n8n-nodes-base.httpRequest', 4.2, [x, y + 200], {
        'method': 'POST',
        'url': 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'authentication': 'genericCredentialType',
        'genericAuthType': 'httpCustomAuth',
        'sendBody': True,
        'specifyBody': 'json',
        'jsonBody': "={{ JSON.stringify({ response: String(($json.body && $json.body.turnstileToken) || '') }) }}",
        'options': {'timeout': 10000},
    }, {'credentials': cred, 'retryOnFail': True, 'maxTries': 2, 'waitBetweenTries': 1000})
    nodo(nodos, SI_OK, 'n8n-nodes-base.if', 2, [x + 220, y + 200], {
        'conditions': {
            'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose'},
            'conditions': [{'id': 'ts-ok', 'leftValue': '={{ $json.success }}', 'rightValue': True,
                            'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
            'combinator': 'and',
        },
        'options': {},
    })
    # El pedido que llegó al Webhook (lo que «Limpiar sesión» espera), no la respuesta de Cloudflare.
    nodo(nodos, ORIGINAL, 'n8n-nodes-base.code', 2, [x + 440, y + 120], {
        'jsCode': "// Devuelve el pedido original del Webhook (16-nov-2026): Turnstile ya lo aprobó.\n"
                  "return $('Webhook').all().map(i => ({ json: i.json }));",
    })
    nodo(nodos, RECHAZO, 'n8n-nodes-base.respondToWebhook', 1.1, [x + 440, y + 300], {
        'respondWith': 'json',
        'responseBody': "={{ JSON.stringify({ error: 'turnstile', message: 'No pudimos confirmar que no eres un robot. "
                        "Vuelve a marcar la casilla e intenta de nuevo.' }) }}",
        'options': {'responseCode': 403},
    })
    salidas[1] = [{'node': VERIFICAR, 'type': 'main', 'index': 0}]
    con[VERIFICAR] = {'main': [[{'node': SI_OK, 'type': 'main', 'index': 0}]]}
    con[SI_OK] = {'main': [[{'node': ORIGINAL, 'type': 'main', 'index': 0}], [{'node': RECHAZO, 'type': 'main', 'index': 0}]]}
    con[ORIGINAL] = {'main': [[{'node': LIMPIAR, 'type': 'main', 'index': 0}]]}


def verificar():
    c, w = api('GET', f'/workflows/{WF}')
    act = w.get('activeVersion') or {}
    con = act.get('connections', {})
    nombres = {n['name'] for n in act.get('nodes', [])}
    ok = {
        'nodos': all(n in nombres for n in (VERIFICAR, SI_OK, ORIGINAL, RECHAZO)),
        'saveLead → Verificar': con.get(SWITCH, {}).get('main', [[], []])[1] == [{'node': VERIFICAR, 'type': 'main', 'index': 0}],
        'OK → Pedido original → Limpiar': con.get(SI_OK, {}).get('main', [[]])[0] == [{'node': ORIGINAL, 'type': 'main', 'index': 0}]
        and con.get(ORIGINAL, {}).get('main', [[]])[0] == [{'node': LIMPIAR, 'type': 'main', 'index': 0}],
        'no → Rechazo': con.get(SI_OK, {}).get('main', [[], []])[1] == [{'node': RECHAZO, 'type': 'main', 'index': 0}],
    }
    print('activo:', w.get('active'), '|', ' | '.join(f'{k}: {v}' for k, v in ok.items()))
    return w.get('active') and all(ok.values())


def main():
    ver = '--ver' in sys.argv
    c, w = api('GET', f'/workflows/{WF}')
    if c != 200:
        raise SystemExit(f'no se pudo leer el flujo ({c})')
    activo = bool(w.get('active'))
    cred_id, estado = credencial_id(crear=not ver)
    preparar(w, cred_id)
    print(f'«{w["name"]}» activo={activo}: credencial {estado}; cablea {SWITCH} (saveLead) → {VERIFICAR} → {SI_OK} '
          f'→ sí: {ORIGINAL} → {LIMPIAR} / no: {RECHAZO} (403)')
    if ver:
        print('modo vista previa: no se leyó la clave ni se subió nada')
        return
    carpeta = os.path.expanduser('~/respaldos/n8n/web-turnstile')
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
