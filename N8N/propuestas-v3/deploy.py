"""Publica (crea o actualiza por nombre) y activa un workflow de N8N/propuestas-v3 en el n8n de AT.

Uso: python N8N/propuestas-v3/deploy.py 1-borrador.json
La API key se lee de la config local del conector n8n-mcp (~/.claude.json) y nunca se imprime.
"""
import json, os, sys, urllib.request, urllib.error


def api():
    cfg = json.load(open(os.path.expanduser('~/.claude.json'), encoding='utf-8'))

    def find(o):
        if isinstance(o, dict):
            s = o.get('mcpServers')
            if isinstance(s, dict) and 'n8n-mcp' in s:
                return s['n8n-mcp']
            for v in o.values():
                r = find(v)
                if r:
                    return r
    env = find(cfg)['env']
    return env['N8N_API_URL'].rstrip('/'), env['N8N_API_KEY']


def call(method, path, body=None):
    url, key = api()
    req = urllib.request.Request(url + '/api/v1' + path, method=method,
                                 data=None if body is None else json.dumps(body).encode(),
                                 headers={'X-N8N-API-KEY': key, 'Content-Type': 'application/json', 'Accept': 'application/json'})
    try:
        return json.loads(urllib.request.urlopen(req, timeout=60).read() or b'{}')
    except urllib.error.HTTPError as e:
        raise SystemExit(f'{method} {path} -> HTTP {e.code}: {e.read()[:400]!r}')


def main():
    f = os.path.join(os.path.dirname(os.path.abspath(__file__)), sys.argv[1])
    wf = json.load(open(f, encoding='utf-8'))
    existentes = [w for w in call('GET', '/workflows?limit=250').get('data', []) if w['name'] == wf['name']]
    body = {k: wf[k] for k in ('name', 'nodes', 'connections', 'settings')}
    if existentes:
        wid = existentes[0]['id']
        call('PUT', f'/workflows/{wid}', body)
        accion = 'actualizado'
    else:
        wid = call('POST', '/workflows', body)['id']
        accion = 'creado'
    call('POST', f'/workflows/{wid}/activate')
    w = call('GET', f'/workflows/{wid}')
    print(f"{accion}: {w['name']} id={wid} activo={w['active']} nodos={len(w['nodes'])}")


main()
