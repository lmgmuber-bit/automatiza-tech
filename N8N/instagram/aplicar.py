"""Crea o actualiza en n8n los dos flujos del asistente de Instagram. Uno nuevo queda INACTIVO; uno que ya estaba activo
(en PROD desde el 28-oct) se republica y sigue activo. No imprime secretos ni la ruta.

Uso:  python aplicar.py            (genera con build_agente_instagram.py y sube)
      python aplicar.py --ver      (solo dice qué haría)

- La clave de n8n se lee de ~/.claude.json (servidor n8n-mcp) y nunca se imprime.
- La ruta del webhook se genera una sola vez (secrets.token_urlsafe(24)) y se guarda FUERA del repo, junto con los ids,
  en ~/respaldos/deploy-scripts/2026-10-24-asistente-instagram/estado.json.
- El prompt se lee en vivo del nodo «Agente IA - Tech» de «Agente Tech Web (PROD)» (3A5Y1NPBxpTjhHdE, solo lectura) y se
  adapta a Instagram: sin el formulario <<SHOW_DEMO_FORM>> y con «derivar_a_persona» en vez de solo dar los contactos.
  Si el texto del agente web cambió y una marca no calza, el script se detiene sin subir nada.
- PUT con {name, nodes, connections, settings} y settings filtrado (timeSavedMode, availableInMCP y binaryMode rebotan).
"""
import json
import re
import os
import secrets
import sys
import urllib.error
import urllib.request

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.dont_write_bytecode = True
sys.path.insert(0, AQUI)
import build_agente_instagram as build  # noqa: E402

ESTADO_DIR = os.path.expanduser('~/respaldos/deploy-scripts/2026-10-24-asistente-instagram')
ESTADO = os.path.join(ESTADO_DIR, 'estado.json')
AGENTE_WEB = '3A5Y1NPBxpTjhHdE'
SETTINGS_OK = {'executionOrder', 'timezone', 'saveDataErrorExecution', 'saveDataSuccessExecution',
               'saveManualExecutions', 'saveExecutionProgress', 'executionTimeout', 'errorWorkflow', 'callerPolicy'}


def n8n_env():
    def buscar(o):
        if isinstance(o, dict):
            ms = o.get('mcpServers')
            if isinstance(ms, dict) and 'n8n-mcp' in ms and 'env' in ms['n8n-mcp']:
                return ms['n8n-mcp']['env']
            for v in o.values():
                r = buscar(v)
                if r:
                    return r
        return None
    env = buscar(json.load(open(os.path.expanduser('~/.claude.json'), encoding='utf-8')))
    return env['N8N_API_URL'].rstrip('/'), env['N8N_API_KEY']


URL, KEY = n8n_env()


def api(metodo, ruta, body=None):
    req = urllib.request.Request(URL + '/api/v1' + ruta, method=metodo,
                                 data=(json.dumps(body).encode() if body is not None else None))
    req.add_header('X-N8N-API-KEY', KEY)
    req.add_header('Content-Type', 'application/json')
    req.add_header('Accept', 'application/json')
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, json.loads(r.read().decode() or '{}')
    except urllib.error.HTTPError as e:
        return e.code, {'error': e.read().decode()[:400]}


def prompt_web_adaptado():
    c, wf = api('GET', '/workflows/' + AGENTE_WEB)
    if c != 200:
        sys.exit('no se pudo leer el agente web: HTTP %s' % c)
    nodo = next(n for n in wf['nodes'] if n['name'] == 'Agente IA - Tech')
    p = nodo['parameters']['options']['systemMessage']
    try:
        return adaptar_prompt(p[1:] if p.startswith('=') else p)
    except ValueError as e:
        sys.exit(str(e))


PLANES_SIN_PRECIO = ('### 📦 PLANES DEL PORTAL OMNICLIENTE\n'
                     'Hay planes mensuales del Portal OmniCliente (Básico, Profesional y Enterprise) según la cantidad de '
                     'conversaciones, canales y agentes. Por Instagram no damos precios: el plan y el valor se ven en el '
                     'diagnóstico gratis.\n')
MONTO = re.compile(r'(US)?\$\s?\d|\$\{\{|\d[\d.,]*\s*(USD|CLP|UF)\b|\b(USD|CLP)\b|\$json\.rate')


def adaptar_prompt(p):
    """Prompt web → Instagram: sin el formulario de la web (se agenda en el chat) y SIN PRECIOS (Luis, 29-oct: el precio
    se define en el diagnóstico gratis y en la propuesta). Si el texto web cambió y una marca no calza, ValueError."""
    ini, fin = p.find('### 📅 AGENDAMIENTO DE REUNIONES'), p.find('### 💬 REGLAS DE COMUNICACIÓN')
    planes, fin_cerebro = p.find('### 📦 PLANES DEL PORTAL OMNICLIENTE'), p.find('### 🧠 CEREBRO AT (fin)')
    cambios = [
        ('4.  Guiar al usuario para agendar citas mediante el formulario.',
         '4.  Agendar el diagnóstico gratis aquí mismo, en el chat de Instagram, y derivar a una persona cuando haga falta.'),
        ('Si piden hablar con un humano o datos de contacto, entrega SOLO estos:',
         'Si piden datos de contacto, entrega SOLO estos (si piden hablar con una persona, usa derivar_a_persona):'),
        ('2.  Calcular precios en tiempo real (USD a CLP).',
         '2.  Explicar que el valor depende de lo que necesita cada negocio y ofrecer el diagnóstico gratis para definirlo.'),
    ]
    if ini < 0 or fin < ini or planes < 0 or fin_cerebro < planes or fin_cerebro > ini \
            or any(p.count(a) != 1 for a, _ in cambios):
        raise ValueError('el prompt del agente web cambió: revisar las marcas de adaptar_prompt() antes de subir')
    # Orden en el prompt web: … PLANES … CEREBRO AT (fin) … CAPACIDADES … AGENDAMIENTO … PRECIOS … COMUNICACIÓN
    p = p[:planes] + PLANES_SIN_PRECIO + p[fin_cerebro:ini] + p[fin:]
    for a, b in cambios:
        p = p.replace(a, b)
    if 'SHOW_DEMO_FORM' in p or 'formulario' in p:
        raise ValueError('el prompt adaptado todavía menciona el formulario')
    quedan = [m.group(0) for m in MONTO.finditer(p)]
    if quedan:
        raise ValueError('el prompt adaptado todavía trae precios o el tipo de cambio: %s' % quedan[:5])
    return p


def rellenar(o, reemplazos):
    if isinstance(o, dict):
        return {k: rellenar(v, reemplazos) for k, v in o.items()}
    if isinstance(o, list):
        return [rellenar(v, reemplazos) for v in o]
    if isinstance(o, str):
        for a, b in reemplazos.items():
            o = o.replace(a, b)
    return o


def subir(wf, wid):
    """Crea (inactivo) o actualiza. Si el flujo estaba ACTIVO (en PROD desde el 28-oct), queda activo: se republica."""
    cuerpo = {'name': wf['name'], 'nodes': wf['nodes'], 'connections': wf['connections'],
              'settings': {k: v for k, v in wf.get('settings', {}).items() if k in SETTINGS_OK}}
    estaba_activo = False
    if wid:
        c0, w0 = api('GET', '/workflows/' + wid)
        estaba_activo = c0 == 200 and bool(w0.get('active'))
        c, r = api('PUT', '/workflows/' + wid, cuerpo)
    else:
        c, r = api('POST', '/workflows', cuerpo)
    if c not in (200, 201):
        sys.exit('n8n rechazó «%s»: HTTP %s %s' % (wf['name'], c, r.get('error', '')[:300]))
    if estaba_activo:
        c2, _ = api('POST', '/workflows/%s/activate' % r['id'])  # republica la versión nueva
        print('  «%s» estaba activo: reactivado (%s)' % (wf['name'], c2))
    elif r.get('active'):
        api('POST', '/workflows/%s/deactivate' % r['id'])
    return r['id']


def main():
    ver = '--ver' in sys.argv
    os.makedirs(ESTADO_DIR, exist_ok=True)
    estado = json.load(open(ESTADO, encoding='utf-8')) if os.path.exists(ESTADO) else {}
    if not estado.get('ruta'):
        estado['ruta'] = 'ig-' + secrets.token_urlsafe(24).replace('_', '').replace('-', '')[:28].lower()
    prompt = prompt_web_adaptado()
    print('prompt web adaptado:', len(prompt), 'caracteres')
    if ver:
        print('herramientas:', estado.get('herramientas') or 'nuevo', '· principal:', estado.get('principal') or 'nuevo')
        return
    estado['herramientas'] = subir(build.flujo_herramientas(), estado.get('herramientas'))
    json.dump(estado, open(ESTADO, 'w', encoding='utf-8'), indent=1)
    principal = rellenar(build.flujo_principal(), {'RUTA_WEBHOOK': estado['ruta'], 'ID_HERRAMIENTAS': estado['herramientas'],
                                                     'PROMPT_AGENTE_WEB': prompt})
    estado['principal'] = subir(principal, estado.get('principal'))
    json.dump(estado, open(ESTADO, 'w', encoding='utf-8'), indent=1)
    print('herramientas:', estado['herramientas'], '· principal:', estado['principal'])


if __name__ == '__main__':
    main()
