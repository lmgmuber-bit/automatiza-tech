"""Tech WhatsApp reconoce a quien escribe: aplica los cambios en los flujos vivos de n8n.

Uso:  python -I -X utf8 N8N/wa-reconoce/aplicar.py           (vista previa: GET de los dos flujos, cambios en memoria y comprobaciones)
      python -I -X utf8 N8N/wa-reconoce/aplicar.py --subir   (respalda, sube PejBgr2LZQSAbbBy y después el de WhatsApp, verifica)

Qué cambia y por qué: N8N/wa-reconoce/README.md y el plan Docs/superpowers/plans/2026-10-28-whatsapp-reconoce-cliente.md (Tarea 7).
Dueño de los dos flujos: Eje. Antes de --subir, avisar a la sesión de Instagram (usa PejBgr2LZQSAbbBy para el diagnóstico).
La clave de n8n se lee de ~/.claude.json y nunca se imprime. Respaldos en ~/respaldos/n8n/wa-reconoce/<fecha>/.
Es idempotente: si un nodo nuevo ya existe por su nombre, se actualiza en vez de duplicarse. Nunca cambia si el flujo está activo o no.
"""
import copy
import datetime
import io
import json
import os
import re
import subprocess
import sys
import tempfile
import urllib.error
import urllib.request
import uuid

AQUI = os.path.dirname(os.path.abspath(__file__))
URL = 'https://n8n-n8n.kchiba.easypanel.host'
WHATSAPP, AGENDA = 'bBcNlFgBzQ0766Mq', 'PejBgr2LZQSAbbBy'
SETTINGS_OK = {'executionOrder', 'timezone', 'saveDataErrorExecution', 'saveDataSuccessExecution',
               'saveManualExecutions', 'saveExecutionProgress', 'executionTimeout', 'errorWorkflow', 'callerPolicy'}
RUTA = 'https://automatizatech.cl/wp-json/automatiza-tech/v1/wa-contexto'
MARCAS = ('// <<WA RECONOCE: inicio>>', '// <<WA RECONOCE: fin>>')
TEXTO_SIN_CLIENTE = 'No encontré una propuesta ni un proyecto con este número. ¿Te agendo tu diagnóstico gratis para conocer tu caso?'
TEXTO_SIN_CORREO = ('Para agendar tu seguimiento necesitamos un correo registrado y no lo encontramos. '
                    'Ya le avisamos a Luis para que te escriba y lo coordinen.')
TEXTO_DEL_MENSAJE = "(() => { for (const n of ['Merge Data', 'Merge Audio Data', 'Merge Image Data']) { try { if ($(n).isExecuted) return String($(n).first().json.chatInput || '').slice(0, 500); } catch (e) {} } return ''; })()"


def bloque():
    js = io.open(os.path.join(AQUI, 'nodos', 'contexto.js'), encoding='utf-8').read().replace('\r\n', '\n')
    return js[js.index(MARCAS[0]):js.index(MARCAS[1]) + len(MARCAS[1])]


def codigo_sumar(b):
    return b + """

// Envoltura del nodo «Sumar Contexto»: una sola línea de contexto interno con quién escribe (wa-contexto) y la
// propuesta pendiente (propuesta-contexto). Si WordPress falló, el mensaje pasa tal cual, como antes.
const fuentes = ['Merge Data', 'Merge Audio Data', 'Merge Image Data'];
let base = null;
for (const f of fuentes) {
  try { if ($(f).isExecuted) { base = $(f).first().json; break; } } catch (e) {}
}
base = base || {};
let prop = {};
try { prop = $('Contexto Propuesta').first().json || {}; } catch (e) {}
const ctx = $input.first().json || {};
const original = String(base.chatInput ?? '');
const linea = waLineaContexto(ctx, prop);
return [{ json: { ...base, chatInput: linea ? linea + '\\n\\n' + original : original, chatInputOriginal: original }, pairedItem: 0 }];
"""


def codigo_elegir(b):
    return b + """

// Envoltura del nodo «Elegir Seguimiento»: lo alimentan «Is Client?» (el agente pidió un seguimiento) y
// «Contexto Elegido» (la persona tocó un botón seg_c_<id> o seg_p_<id>).
const ctx = $input.first().json || {};
const em = $('Extract Message Data').first().json || {};
let buttonId = '';
try { if ($('Process Interactive').isExecuted) buttonId = $('Process Interactive').first().json.buttonId || ''; } catch (e) {}
let output = '';
try { if ($('Route Action').isExecuted) output = $('Route Action').first().json.output || ''; } catch (e) {}
return [{ json: waElegirSeguimiento(ctx, { buttonId, phoneNumber: em.phoneNumber, phoneNumberId: em.phoneNumberId, contactName: em.contactName, output }), pairedItem: 0 }];
"""


def api_con(key):
    def api(metodo, ruta, body=None):
        req = urllib.request.Request(URL + '/api/v1' + ruta, method=metodo, data=(json.dumps(body).encode() if body is not None else None))
        for k, v in (('X-N8N-API-KEY', key), ('Content-Type', 'application/json'), ('Accept', 'application/json')):
            req.add_header(k, v)
        try:
            with urllib.request.urlopen(req, timeout=60) as r:
                return r.status, json.loads(r.read().decode() or '{}')
        except urllib.error.HTTPError as e:
            return e.code, {'error': e.read().decode()[:300]}
    return api


def nodo_nuevo(w, nombre, plantilla, params, dx=0, dy=0, sin_credenciales=False):
    """Agrega (o actualiza, si ya existe) un nodo con el tipo y la versión de la plantilla; las credenciales se copian sin leerlas."""
    nodos = {n['name']: n for n in w['nodes']}
    base = nodos[plantilla]
    if nombre in nodos:
        nodos[nombre]['parameters'] = params
        return 'actualizado'
    n = {'parameters': params, 'id': str(uuid.uuid4()), 'name': nombre, 'type': base['type'], 'typeVersion': base['typeVersion'],
         'position': [base['position'][0] + dx, base['position'][1] + dy]}
    if base.get('credentials') and not sin_credenciales:
        n['credentials'] = copy.deepcopy(base['credentials'])
    w['nodes'].append(n)
    return 'agregado'


def conectar(w, origen, salidas):
    """salidas: lista de listas de nombres de nodo, una por salida del nodo origen."""
    w['connections'][origen] = {'main': [[{'node': d, 'type': 'main', 'index': 0} for d in s] for s in salidas]}


def http_contexto(con_texto):
    q = [{'name': 'phone', 'value': "={{ $('Extract Message Data').first().json.phoneNumber }}"}]
    if con_texto:
        q.append({'name': 'texto', 'value': '={{ ' + TEXTO_DEL_MENSAJE + ' }}'})
    return {'method': 'GET', 'url': RUTA, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
            'sendQuery': True, 'queryParameters': {'parameters': q},
            'options': {'timeout': 5000, 'response': {'response': {'neverError': True}}}}


def cambiar_whatsapp(w, b, registro):
    nodos = {n['name']: n for n in w['nodes']}
    p = lambda nombre: nodos[nombre]['parameters']

    # 1-2) «Contexto Cliente» entre «Contexto Propuesta» y «Sumar Contexto», que arma una sola línea
    registro.append(('Contexto Cliente', nodo_nuevo(w, 'Contexto Cliente', 'Contexto Propuesta', http_contexto(True), dx=220, dy=-160)))
    conectar(w, 'Contexto Propuesta', [['Contexto Cliente']])
    conectar(w, 'Contexto Cliente', [['Sumar Contexto']])
    if p('Sumar Contexto').get('jsCode') != codigo_sumar(b):
        p('Sumar Contexto')['jsCode'] = codigo_sumar(b)
        registro.append(('Sumar Contexto', 'código nuevo'))

    # 3) «Verify Client» consulta wa-contexto (y no se cae si WordPress falla)
    url = "={{ '" + RUTA + "?phone=' + encodeURIComponent($json.phoneNumber) + '&texto=' + encodeURIComponent((() => { try { return $('Sumar Contexto').first().json.chatInputOriginal || ''; } catch (e) { return ''; } })()) }}"
    vc = p('Verify Client')
    if vc.get('url') != url:
        vc['url'] = url
        vc.setdefault('options', {}).setdefault('response', {}).setdefault('response', {})['neverError'] = True
        registro.append(('Verify Client', 'ruta wa-contexto'))

    # 4) «Is Client?»: sigue al seguimiento salvo una persona nueva (si la ruta falló, seguimiento general)
    cond = p('Is Client?')['conditions']['boolean'][0]
    if cond.get('value1') != "={{ $json.situacion !== 'nuevo' }}":
        cond['value1'], cond['value2'] = "={{ $json.situacion !== 'nuevo' }}", True
        registro.append(('Is Client?', 'condición por situación'))

    # 5-6) «Elegir Seguimiento» → «Modo Seguimiento» (directo | elegir | sin_correo)
    registro.append(('Elegir Seguimiento', nodo_nuevo(w, 'Elegir Seguimiento', 'Sumar Contexto', {'jsCode': codigo_elegir(b)}, dx=0, dy=420)))
    regla = copy.deepcopy(p('Route Action')['rules']['values'][0])
    reglas = []
    for modo in ('directo', 'elegir', 'sin_correo'):
        r = copy.deepcopy(regla)
        r['conditions']['conditions'][0]['leftValue'] = '={{ $json.modo }}'
        r['conditions']['conditions'][0]['rightValue'] = modo
        r['conditions']['conditions'][0].pop('id', None)
        r['outputKey'] = modo
        reglas.append(r)
    registro.append(('Modo Seguimiento', nodo_nuevo(w, 'Modo Seguimiento', 'Route Action', {'rules': {'values': reglas}, 'options': {'fallbackOutput': 'none'}}, dx=0, dy=420)))
    guardar = copy.deepcopy(p('Save Reschedule Client'))
    guardar.update({'operation': 'set', 'key': "=followup_resched_client_{{ $('Extract Message Data').first().json.phoneNumber }}",
                    'value': "={{ JSON.stringify($('Elegir Seguimiento').first().json.guardar) }}", 'expire': True, 'ttl': 86400})
    registro.append(('Guardar Elección Seguimiento', nodo_nuevo(w, 'Guardar Elección Seguimiento', 'Save Reschedule Client', guardar, dx=0, dy=420)))
    envio = copy.deepcopy(p('Send Not Client Message'))
    envio['url'] = '=https://graph.facebook.com/v22.0/{{ $json.datos.phoneNumberId }}/messages'
    envio['jsonBody'] = '={{ JSON.stringify($json.mensaje) }}'
    registro.append(('Send Seguimiento Opciones', nodo_nuevo(w, 'Send Seguimiento Opciones', 'Send Not Client Message', envio, dx=0, dy=420)))
    sin = copy.deepcopy(envio)
    sin['jsonBody'] = "={{ JSON.stringify({ messaging_product: 'whatsapp', to: $json.datos.phoneNumber, type: 'text', text: { body: " + json.dumps(TEXTO_SIN_CORREO, ensure_ascii=False).replace('"', "'") + " } }) }}"
    registro.append(('Send Seguimiento Sin Correo', nodo_nuevo(w, 'Send Seguimiento Sin Correo', 'Send Not Client Message', sin, dx=0, dy=560)))
    aviso = copy.deepcopy(p('Notify Admin Followup Action Error'))
    aviso['subject'] = 'WhatsApp: seguimiento sin correo registrado'
    aviso['html'] = ("=<p>Un número de WhatsApp quiso agendar un seguimiento y su ficha no tiene un correo registrado.</p>"
                     "<p>Número: {{ $json.datos.phoneNumber }}<br>Opción: {{ ($json.opcion && $json.opcion.nombre) || 'sin opción' }}</p>"
                     "<p>Completa el correo en su ficha del CRM y escríbele para coordinar la reunión.</p>")
    registro.append(('Notify Admin Seguimiento Sin Correo', nodo_nuevo(w, 'Notify Admin Seguimiento Sin Correo', 'Notify Admin Followup Action Error', aviso, dx=0, dy=420)))
    conectar(w, 'Is Client?', [['Elegir Seguimiento'], [d['node'] for d in w['connections']['Is Client?']['main'][1]]])
    conectar(w, 'Elegir Seguimiento', [['Modo Seguimiento']])
    conectar(w, 'Modo Seguimiento', [['Guardar Elección Seguimiento'], ['Send Seguimiento Opciones'], ['Send Seguimiento Sin Correo', 'Notify Admin Seguimiento Sin Correo']])
    conectar(w, 'Guardar Elección Seguimiento', [['Prepare Followup Data']])

    # 7) «Prepare Followup Data» toma los datos de «Elegir Seguimiento» («Generate Days» lo lee por nombre)
    if p('Prepare Followup Data').get('jsonOutput') != "={{ $('Elegir Seguimiento').first().json.datos }}":
        p('Prepare Followup Data')['jsonOutput'] = "={{ $('Elegir Seguimiento').first().json.datos }}"
        registro.append(('Prepare Followup Data', 'datos de Elegir Seguimiento'))

    # 8) «Button Action»: regla «Seguimiento Elegido» (seg_) → «Contexto Elegido» → «Elegir Seguimiento»
    registro.append(('Contexto Elegido', nodo_nuevo(w, 'Contexto Elegido', 'Contexto Propuesta', http_contexto(False), dx=220, dy=260)))
    conectar(w, 'Contexto Elegido', [['Elegir Seguimiento']])
    ba = p('Button Action')
    valores = ba['rules']['values']
    if not any(any(c.get('rightValue') == 'seg_' for c in r['conditions']['conditions']) for r in valores):
        nueva = copy.deepcopy(next(r for r in valores if any(c.get('rightValue') == 'btn_propuesta_' for c in r['conditions']['conditions'])))
        nueva['conditions']['conditions'][0]['rightValue'] = 'seg_'
        nueva['conditions']['conditions'][0].pop('id', None)
        nueva['outputKey'] = 'Seguimiento Elegido'
        n_reglas = len(valores)
        valores.append(nueva)
        main = w['connections']['Button Action']['main']
        while len(main) < n_reglas:
            main.append([])
        main.insert(n_reglas, [{'node': 'Contexto Elegido', 'type': 'main', 'index': 0}])
        registro.append(('Button Action', 'regla Seguimiento Elegido'))

    # 9) «Send Not Client Message»: diagnóstico gratis, nunca «demo»
    nc = p('Send Not Client Message')
    if TEXTO_SIN_CLIENTE not in nc['jsonBody']:
        cuerpo = nc['jsonBody']
        assert cuerpo.count('{{ $json.message }}') == 1, 'Send Not Client Message: el texto viejo no calza una sola vez'
        i = cuerpo.index('{{ $json.message }}')
        j = cuerpo.index('"', i)
        cuerpo = cuerpo[:i] + TEXTO_SIN_CLIENTE + cuerpo[j:]
        assert cuerpo.count('"title": "📅 Agendar Demo"') == 1, 'Send Not Client Message: el botón viejo no calza una sola vez'
        nc['jsonBody'] = cuerpo.replace('"title": "📅 Agendar Demo"', '"title": "Agendar diagnóstico"')
        registro.append(('Send Not Client Message', 'diagnóstico gratis'))

    # 10) Los dos «Prepare Seguimiento …» pasan el vínculo al sub-workflow
    for nombre in ('Prepare Seguimiento Data Direct', 'Prepare Seguimiento Custom'):
        jo = p(nombre)['jsonOutput']
        if 'contrato_id' not in jo:
            assert jo.count("notes: c.notes || '' }") == 1, f'{nombre}: el ancla no calza una sola vez'
            p(nombre)['jsonOutput'] = jo.replace("notes: c.notes || '' }", "notes: c.notes || '', contrato_id: Number(c.contrato_id) || 0, propuesta_id: Number(c.propuesta_id) || 0 }")
            registro.append((nombre, 'contrato_id y propuesta_id'))


def cambiar_agenda(w, registro):
    nodos = {n['name']: n for n in w['nodes']}
    gc = nodos['Get Client Data']['parameters']
    if '/wa-contexto?' not in gc['url']:
        assert gc['url'].count('/verify-client?') == 1, 'Get Client Data: la ruta vieja no calza una sola vez'
        gc['url'] = gc['url'].replace('/verify-client?', '/wa-contexto?')
        registro.append(('Get Client Data', 'ruta wa-contexto'))
    mc = nodos['Merge Client Data']['parameters']
    texto = json.dumps(mc, ensure_ascii=False)
    if 'agenda?.correo' not in texto:
        for viejo, nuevo in (('$json.client_email', '$json.agenda?.correo'), ('$json.client_name', '$json.agenda?.nombre_completo'), ('$json.company_name', '$json.agenda?.empresa')):
            assert texto.count(viejo) == 2, f'Merge Client Data: {viejo} calza {texto.count(viejo)} veces (se esperaban 2)'
            texto = texto.replace(viejo, nuevo)
        nodos['Merge Client Data']['parameters'] = json.loads(texto)
        registro.append(('Merge Client Data', 'datos de la agenda de wa-contexto'))
    asig = nodos['Prepare Payload']['parameters']['assignments']['assignments'][0]
    if 'contrato_id' not in asig['value']:
        ancla = "google_event_id: $('Create Event').item.json.id } : {"
        assert asig['value'].count(ancla) == 1, 'Prepare Payload: el ancla no calza una sola vez'
        asig['value'] = asig['value'].replace(ancla, "google_event_id: $('Create Event').item.json.id, contrato_id: Number($('Execute Workflow Trigger').item.json.contrato_id) || 0, propuesta_id: Number($('Execute Workflow Trigger').item.json.propuesta_id) || 0 } : {")
        registro.append(('Prepare Payload', 'contrato_id y propuesta_id en el seguimiento'))


def comprobar_js(nombre, codigo):
    """node --check del código del nodo, envuelto en una función como lo corre n8n."""
    with tempfile.NamedTemporaryFile('w', suffix='.js', delete=False, encoding='utf-8') as f:
        f.write('(async function () {\n' + codigo + '\n})();\n')
    r = subprocess.run(['node', '--check', f.name], capture_output=True, text=True)
    os.unlink(f.name)
    return r.returncode == 0, (r.stderr or '').strip()[:200]


def comprobar(flujo, w, antes, b):
    nodos = {n['name']: n for n in w['nodes']}
    fallas = []
    for o, v in w['connections'].items():
        if o not in nodos:
            fallas.append(f'conexión desde un nodo que no existe: {o}')
        for rama in v.get('main', []):
            for d in rama or []:
                if d['node'] not in nodos:
                    fallas.append(f'{o} conecta a un nodo que no existe: {d["node"]}')
    tocados = [n for n in nodos if n not in {x['name'] for x in antes['nodes']} or
               json.dumps(nodos[n]['parameters'], sort_keys=True) != json.dumps(next(x for x in antes['nodes'] if x['name'] == n)['parameters'], sort_keys=True)]
    for n in tocados:
        for ref in re.findall(r"\$\('([^']+)'\)", json.dumps(nodos[n]['parameters'], ensure_ascii=False)):
            if ref not in nodos:
                fallas.append(f'{n} lee un nodo que no existe: {ref}')
    if flujo == 'whatsapp':
        reglas = len(nodos['Button Action']['parameters']['rules']['values'])
        main = w['connections']['Button Action']['main']
        if len(main) < reglas or [d['node'] for d in main[reglas - 1]] != ['Contexto Elegido']:
            fallas.append('Button Action: la regla nueva no sale a Contexto Elegido')
        if [d['node'] for d in w['connections']['Is Client?']['main'][0]] != ['Elegir Seguimiento']:
            fallas.append('Is Client? no sale verdadero a Elegir Seguimiento')
        if 'Route Action' in json.dumps(nodos['Prepare Followup Data']['parameters']):
            fallas.append('Prepare Followup Data todavía lee Route Action')
        for n in ('Sumar Contexto', 'Elegir Seguimiento'):
            codigo = nodos[n]['parameters']['jsCode']
            if b not in codigo:
                fallas.append(f'{n}: el bloque no es idéntico al de contexto.js')
            ok, err = comprobar_js(n, codigo)
            if not ok:
                fallas.append(f'{n}: node --check falla: {err}')
        for n in ('Send Not Client Message',):
            if re.search(r'demo', json.dumps(nodos[n]['parameters']['jsonBody'], ensure_ascii=False).replace('btn_agendar_demo', ''), re.I):
                fallas.append(f'{n}: todavía dice «demo» al cliente')
    else:
        corte = lambda s: s.split(' : {', 1)[1] if ' : {' in s else ''
        a = next(x for x in antes['nodes'] if x['name'] == 'Prepare Payload')['parameters']['assignments']['assignments'][0]['value']
        d = nodos['Prepare Payload']['parameters']['assignments']['assignments'][0]['value']
        if corte(a) != corte(d):
            fallas.append('Prepare Payload: el objeto del diagnóstico cambió')
    return tocados, fallas


def main():
    subir = '--subir' in sys.argv
    key = json.load(io.open(os.path.expanduser('~/.claude.json'), encoding='utf-8'))['mcpServers']['n8n-mcp']['env']['N8N_API_KEY']
    api = api_con(key)
    b = bloque()
    hora = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
    carpeta = os.path.join(os.path.expanduser('~/respaldos/n8n/wa-reconoce'), hora)
    resultado = {}
    for flujo, wid in (('agenda', AGENDA), ('whatsapp', WHATSAPP)):
        c, w = api('GET', '/workflows/' + wid)
        assert c == 200, f'{flujo}: GET {c}'
        antes = copy.deepcopy(w)
        registro = []
        cambiar_agenda(w, registro) if flujo == 'agenda' else cambiar_whatsapp(w, b, registro)
        tocados, fallas = comprobar(flujo, w, antes, b)
        # Idempotencia: aplicar otra vez sobre lo ya cambiado no debe agregar ni cambiar nada.
        otra = copy.deepcopy(w)
        registro2 = []
        cambiar_agenda(otra, registro2) if flujo == 'agenda' else cambiar_whatsapp(otra, b, registro2)
        if json.dumps(otra, sort_keys=True) != json.dumps(w, sort_keys=True) or any(q == 'agregado' for _, q in registro2):
            fallas.append('no es idempotente: una segunda pasada cambia el flujo')
        print(f'{flujo} ({wid}, activo={w.get("active")}): {len(tocados)} nodo(s) nuevos o cambiados')
        for n, que in registro:
            print(f'   - {n}: {que}')
        print(f'   comprobaciones: {"OK" if not fallas else "FALLAN"}')
        for f in fallas:
            print('     ✗', f)
        resultado[flujo] = (wid, antes, w, tocados, fallas)
    if any(r[4] for r in resultado.values()):
        sys.exit('hay comprobaciones que fallan: no se sube nada')
    if not subir:
        print('modo vista previa: no se subió nada (usar --subir)')
        return
    os.makedirs(carpeta, exist_ok=True)
    for flujo in ('agenda', 'whatsapp'):
        wid, antes, w, tocados, _ = resultado[flujo]
        if not tocados:
            print(f'{flujo}: sin cambios')
            continue
        json.dump(antes, io.open(os.path.join(carpeta, f'{flujo}-{wid}.antes.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        cuerpo = {'name': w['name'], 'nodes': w['nodes'], 'connections': w['connections'],
                  'settings': {k: v for k, v in (w.get('settings') or {}).items() if k in SETTINGS_OK}}
        c, r = api('PUT', '/workflows/' + wid, cuerpo)
        assert c == 200, f'{flujo}: PUT {c} {r}; el flujo quedó como estaba (respaldo en {carpeta})'
        if antes.get('active'):
            c2, _ = api('POST', f'/workflows/{wid}/activate')
            print(f'{flujo}: subido y republicado ({c2})')
        c3, w3 = api('GET', '/workflows/' + wid)
        publicados = {n['name'] for n in ((w3.get('activeVersion') or {}).get('nodes') or [])} if antes.get('active') else {n['name'] for n in w3['nodes']}
        faltan = [n for n in tocados if n not in publicados]
        print(f'{flujo}: releído activo={w3.get("active")} (antes {antes.get("active")}) | nodos tocados en la versión publicada: {len(tocados) - len(faltan)}/{len(tocados)}')
        assert w3.get('active') == antes.get('active') and not faltan, f'{flujo}: lo publicado no coincide (respaldo en {carpeta})'
    print('respaldos en', carpeta)


if __name__ == '__main__':
    main()
