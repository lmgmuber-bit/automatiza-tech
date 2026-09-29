"""Construye (y con --publicar crea/actualiza y activa) el flujo n8n «GT Baseball · Buscador de planillas».

Lo usa la página privada gtbaseball.com/buscador/ (demos/gt-baseball/buscador/), que no está enlazada desde la portada:
la academia busca a un atleta ya inscrito (por nombre, cédula, posición, pago, edad o fecha) y vuelve a descargar su
planilla desde Drive.

- Acceso con clave: la página manda {clave} en el cuerpo y el flujo la compara con la variable de entorno
  GT_BUSCADOR_CLAVE del servicio n8n en Easypanel (la elige Luis; nunca va en el repo ni en el flujo). Sin esa variable
  (o con menos de 12 caracteres) responde 503 «sin_configurar». Con clave equivocada, 401; tras 8 fallos desde una IP
  en 15 minutos, 429 hasta que pase la ventana.
- Con la clave correcta lee Inscripciones!A2:Y del Sheet (credencial Google Sheets PROD) y devuelve la lista con los
  campos que usa el buscador; el filtrado y la paginación los hace la página (son pocos cientos de filas).
- Las planillas, fotos y comprobantes son enlaces de Drive: solo abren con una cuenta de Google con acceso a la carpeta
  (Jeffer es lector), así que un enlace filtrado no alcanza para ver el archivo.
- No guarda las ejecuciones exitosas (traen datos de menores).

    python N8N/gt-baseball/build_buscador.py <ruta>/gt-config.json              # escribe el JSON del flujo
    python N8N/gt-baseball/build_buscador.py <ruta>/gt-config.json --probar     # prueba en un flujo temporal
    python N8N/gt-baseball/build_buscador.py <ruta>/gt-config.json --publicar   # crea o actualiza y activa
"""
import json, os, secrets, sys, time, urllib.error, urllib.request, uuid

from n8n_api import call, WEBHOOK_BASE

NOMBRE = 'GT Baseball · Buscador de planillas'
PATH = 'gt-baseball-buscador'
# La página vive en gtbaseball.com; los dos locales son para probarla (la clave sigue siendo obligatoria).
ORIGENES = 'https://gtbaseball.com,https://www.gtbaseball.com,http://localhost:8771,http://127.0.0.1:8774'
SHEETS = {'googleSheetsOAuth2Api': {'id': 'xWQj9WmGzqGKwQtb', 'name': 'Google Sheets PROD'}}
FALLOS_MAX = 8
VENTANA_MIN = 15

JS_CLAVE = r"""
// Compara la clave del cuerpo con la variable de entorno sin cortar en el primer carácter distinto, y limita los
// intentos fallidos por IP (datos estáticos del flujo; solo cuentan en ejecuciones de producción).
const entrada = $input.first().json;
const h = entrada.headers || {};
const body = entrada.body || {};
const clave = String(body.clave || '');
const esperada = __ESPERADA__;
// La IP es la ÚLTIMA de X-Forwarded-For (la que agrega el proxy de Easypanel); la primera la puede inventar el cliente.
const xff = String(h['x-forwarded-for'] || '').split(',').map(x => x.trim()).filter(Boolean);
const ip = xff[xff.length - 1] || String(h['x-real-ip'] || '').trim() || 'sin-ip';
const sd = $getWorkflowStaticData('global');
sd.fallos = sd.fallos || {};
const ahora = Date.now();
const VENTANA = __VENTANA__ * 60 * 1000;
for (const k of Object.keys(sd.fallos)) if (ahora - sd.fallos[k].desde > VENTANA) delete sd.fallos[k];
const f = sd.fallos[ip];
if (f && f.n >= __MAX__) return [{ json: { ok: false, codigo: 429, error: 'demasiados_intentos' } }];
if (esperada.length < 12) return [{ json: { ok: false, codigo: 503, error: 'sin_configurar' } }];
let dif = clave.length ^ esperada.length;
for (let i = 0; i < esperada.length; i++) dif |= (clave.charCodeAt(i) || 0) ^ esperada.charCodeAt(i);
if (dif !== 0) {
  sd.fallos[ip] = { desde: f ? f.desde : ahora, n: (f ? f.n : 0) + 1 };
  return [{ json: { ok: false, codigo: 401, error: 'clave' } }];
}
if (f) delete sd.fallos[ip];
return [{ json: { ok: true } }];
"""

# Solo los campos que usa el buscador (ubicar al atleta y volver a bajar sus archivos); lo demás queda en el Sheet.
# Columnas A-Y del Sheet (las escribe «Armar fila y correo» de build.py, en este orden): fecha, tipo, nombre,
# nacimiento, edad, documento, nacionalidad, teléfono, correo, dirección, representante, teléfono del representante,
# liga, posición, batea, lanza, estatura, peso, millas, año de firma, foto, planilla, id, forma de pago, comprobante.
JS_LISTA = r"""
const r = $input.first().json;
if (r.error || !Array.isArray(r.values) && r.range === undefined) return [{ json: { ok: false, codigo: 502, error: 'sheet' } }];
const filas = r.values || [];
const idDe = u => { const m = String(u || '').match(/\/d\/([A-Za-z0-9_-]{10,})/); return m ? m[1] : ''; };
const atletas = [];
filas.forEach((fila, i) => {
  const c = k => String(fila[k] == null ? '' : fila[k]).trim();
  if (!c(2)) return;
  const comp = c(24);
  const compId = idDe(comp);
  atletas.push({
    fila: i + 2,
    fecha: c(0),
    prueba: c(1).toUpperCase() === 'PRUEBA',
    nombre: c(2),
    nacimiento: c(3),
    edad: /^\d{1,2}$/.test(c(4)) ? Number(c(4)) : null,
    documento: c(5),
    representante: c(10),
    rep_telefono: c(11),
    posicion: c(13),
    foto: idDe(c(20)),
    planilla: idDe(c(21)),
    id: c(22),
    pago: c(23),
    comprobante: compId ? 'adjunto' : (/^pendiente$/i.test(comp) ? 'pendiente' : (/^no aplica$/i.test(comp) ? 'no_aplica' : (/^adjunto$/i.test(comp) ? 'adjunto' : ''))),
    comprobante_id: compId,
  });
});
return [{ json: { ok: true, total: atletas.length, atletas } }];
"""


def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def code(id_, name, pos, js):
    return node(id_, name, 'n8n-nodes-base.code', 2, pos, {'jsCode': js.strip()})


def si(id_, name, pos, expresion):
    return node(id_, name, 'n8n-nodes-base.if', 2, pos, {
        'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'strict'},
                       'conditions': [{'id': id_, 'leftValue': expresion, 'rightValue': True,
                                       'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
                       'combinator': 'and'}, 'options': {}})


def construir(cfg, webhook=None, esperada_js="String($env.GT_BUSCADOR_CLAVE || '')"):
    if not cfg.get('sheet_id'):
        raise SystemExit('Falta sheet_id en gt-config.json')
    clave_js = (JS_CLAVE.replace('__ESPERADA__', esperada_js).replace('__VENTANA__', str(VENTANA_MIN))
                .replace('__MAX__', str(FALLOS_MAX)))
    sin_cache = {'responseHeaders': {'entries': [{'name': 'Cache-Control', 'value': 'no-store'}]}}
    nodes = [
        webhook or node('b1', 'Webhook', 'n8n-nodes-base.webhook', 2, [0, 0],
                        {'httpMethod': 'POST', 'path': PATH, 'responseMode': 'responseNode',
                         'options': {'allowedOrigins': ORIGENES}}, webhookId='5c0e7b1a-9d42-4f3e-8a61-2b7d9e4c1f08'),
        code('b2', 'Revisar clave', [220, 0], clave_js),
        si('b3', '¿Clave correcta?', [440, 0], '={{ $json.ok }}'),
        node('b4', 'Leer Sheet', 'n8n-nodes-base.httpRequest', 4.2, [660, -100], {
            'method': 'GET',
            'url': f"https://sheets.googleapis.com/v4/spreadsheets/{cfg['sheet_id']}/values/Inscripciones!A2:Y",
            'authentication': 'predefinedCredentialType', 'nodeCredentialType': 'googleSheetsOAuth2Api',
            'sendQuery': True, 'queryParameters': {'parameters': [{'name': 'valueRenderOption', 'value': 'FORMATTED_VALUE'}]},
            'options': {}}, credentials=SHEETS, onError='continueRegularOutput'),
        code('b5', 'Armar lista', [880, -100], JS_LISTA),
        si('b6', '¿Lista?', [1100, -100], '={{ $json.ok }}'),
        node('b7', 'Responder lista', 'n8n-nodes-base.respondToWebhook', 1.1, [1320, -200], {
            'respondWith': 'json', 'responseBody': '={{ JSON.stringify($json) }}',
            'options': {'responseCode': 200, **sin_cache}}),
        node('b8', 'Responder error', 'n8n-nodes-base.respondToWebhook', 1.1, [1320, 100], {
            'respondWith': 'json', 'responseBody': '={{ JSON.stringify({ ok: false, error: $json.error }) }}',
            'options': {'responseCode': '={{ $json.codigo || 500 }}', **sin_cache}}),
    ]
    nombre_webhook = nodes[0]['name']
    enlaces = [(nombre_webhook, 0, 'Revisar clave'), ('Revisar clave', 0, '¿Clave correcta?'),
               ('¿Clave correcta?', 0, 'Leer Sheet'), ('¿Clave correcta?', 1, 'Responder error'),
               ('Leer Sheet', 0, 'Armar lista'), ('Armar lista', 0, '¿Lista?'),
               ('¿Lista?', 0, 'Responder lista'), ('¿Lista?', 1, 'Responder error')]
    connections = {}
    for a, salida, b in enlaces:
        main = connections.setdefault(a, {'main': []})['main']
        while len(main) <= salida:
            main.append([])
        main[salida].append({'node': b, 'type': 'main', 'index': 0})
    return {'name': NOMBRE, 'nodes': nodes, 'connections': connections,
            'settings': {'executionOrder': 'v1', 'timezone': 'America/Caracas',
                         # Cada respuesta trae nombres y cédulas de menores: no guardar las ejecuciones exitosas.
                         'saveDataSuccessExecution': 'none', 'saveDataErrorExecution': 'none',
                         'saveManualExecutions': False}}


def pedir(url, cuerpo):
    req = urllib.request.Request(url, method='POST', data=json.dumps(cuerpo).encode(),
                                 headers={'Content-Type': 'application/json'})
    try:
        r = urllib.request.urlopen(req, timeout=60)
        return r.status, json.loads(r.read() or b'{}')
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read() or b'{}')
        except ValueError:
            return e.code, {}


def probar(cfg):
    """Prueba el flujo en uno temporal que se borra al terminar. La clave de prueba es aleatoria, vive solo en ese
    flujo temporal y nunca se imprime. No muestra datos personales: solo cantidades y nombres de campos."""
    clave = secrets.token_urlsafe(18)
    path = 'temp-gt-buscador-' + uuid.uuid4().hex[:10]
    gancho = node('b1', 'Probar', 'n8n-nodes-base.webhook', 2, [0, 0],
                  {'httpMethod': 'POST', 'path': path, 'responseMode': 'responseNode', 'options': {}}, webhookId=path)
    wf = construir(cfg, webhook=gancho, esperada_js=json.dumps(clave))
    wf['name'] = 'TEMP ' + NOMBRE
    wid = call('POST', '/workflows', wf)['id']
    resultados = []
    def ok(cond, texto):
        resultados.append((bool(cond), texto))
    try:
        call('POST', f'/workflows/{wid}/activate')
        url = WEBHOOK_BASE + path
        for intento in range(8):
            est, _ = pedir(url, {'clave': 'x'})
            if est != 404:
                break
            time.sleep(1.5)
        ok(est == 401, f'clave equivocada → {est} (espera 401)')
        est, r = pedir(url, {'clave': clave})
        atletas = r.get('atletas') or []
        ok(est == 200 and r.get('ok') is True, f'clave correcta → {est}, ok={r.get("ok")}, total={r.get("total")}')
        ok(len(atletas) == r.get('total'), f'total coincide con la lista ({len(atletas)})')
        campos = sorted(atletas[0].keys()) if atletas else []
        ok(bool(campos), f'campos: {", ".join(campos)}')
        ok(all(a.get('planilla') for a in atletas), f'todas traen id de planilla ({sum(1 for a in atletas if a.get("planilla"))}/{len(atletas)})')
        ok(all(a.get('edad') is None or 3 <= a['edad'] <= 40 for a in atletas), 'edades numéricas y razonables')
        print('  pruebas:', sum(1 for a in atletas if a.get('prueba')), '· reales:', sum(1 for a in atletas if not a.get('prueba')),
              '· comprobante:', {e: sum(1 for a in atletas if a.get('comprobante') == e) for e in ('adjunto', 'pendiente', 'no_aplica', '')})
        # Límite: 8 fallos desde la misma IP bloquean (429), incluso con la clave buena, hasta que pase la ventana.
        cods = [pedir(url, {'clave': 'mala-' + str(i)})[0] for i in range(FALLOS_MAX)]
        est_bloq, _ = pedir(url, {'clave': clave})
        ok(cods.count(401) >= FALLOS_MAX - 1 and est_bloq == 429, f'tras {FALLOS_MAX} fallos: {cods[-3:]} y la buena → {est_bloq} (espera 429)')
    finally:
        try:
            call('POST', f'/workflows/{wid}/deactivate')
        finally:
            call('DELETE', f'/workflows/{wid}')
    for bien, texto in resultados:
        print(('  ✓ ' if bien else '  ✗ ') + texto)
    print('RESULTADO:', 'todo bien' if all(b for b, _ in resultados) else 'HAY FALLAS')


def publicar(wf):
    existentes, cursor = [], None
    while True:
        resp = call('GET', '/workflows?limit=250' + (f'&cursor={cursor}' if cursor else ''))
        existentes += [w for w in resp.get('data', []) if w['name'] == wf['name']]
        cursor = resp.get('nextCursor')
        if not cursor:
            break
    if existentes:
        wid = existentes[0]['id']
        call('PUT', f'/workflows/{wid}', wf)
        accion = 'actualizado'
    else:
        wid = call('POST', '/workflows', wf)['id']
        accion = 'creado'
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
    wf = construir(cfg)
    out = os.path.join(os.path.dirname(cfg_path), 'gt-baseball-buscador.json')
    json.dump(wf, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print('escrito', out, len(wf['nodes']), 'nodos')
    if '--publicar' in sys.argv:
        publicar(wf)


if __name__ == '__main__':
    main()
