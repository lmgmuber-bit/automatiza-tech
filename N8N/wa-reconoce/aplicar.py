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
return [{ json: { ...base, chatInput: linea ? linea + '\\n\\n' + original : original, chatInputOriginal: original, reconocimiento: waReconocimiento(ctx) }, pairedItem: 0 }];
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


def nodo_nuevo(w, nombre, plantilla, params, dx=0, dy=0, sin_credenciales=False, on_error=None):
    """Agrega (o actualiza, si ya existe) un nodo con el tipo y la versión de la plantilla; las credenciales se copian sin leerlas.
    on_error='continueRegularOutput' hace que un timeout o error de red pase como un ítem con «error» (revisión B-01)."""
    nodos = {n['name']: n for n in w['nodes']}
    base = nodos[plantilla]
    if nombre in nodos:
        nodos[nombre]['parameters'] = params
        if on_error:
            nodos[nombre]['onError'] = on_error
        return 'actualizado'
    n = {'parameters': params, 'id': str(uuid.uuid4()), 'name': nombre, 'type': base['type'], 'typeVersion': base['typeVersion'],
         'position': [base['position'][0] + dx, base['position'][1] + dy]}
    if on_error:
        n['onError'] = on_error
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
    registro.append(('Contexto Cliente', nodo_nuevo(w, 'Contexto Cliente', 'Contexto Propuesta', http_contexto(True), dx=220, dy=-160, on_error='continueRegularOutput')))
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
    if nodos['Verify Client'].get('onError') != 'continueRegularOutput':
        nodos['Verify Client']['onError'] = 'continueRegularOutput'
        registro.append(('Verify Client', 'sigue si la consulta se cae'))

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
    # El texto lo decide «Elegir Seguimiento» según el motivo: sin correo registrado o sin poder revisar los datos (R2-01).
    sin['jsonBody'] = "={{ JSON.stringify({ messaging_product: 'whatsapp', to: $json.datos.phoneNumber, type: 'text', text: { body: $json.aviso } }) }}"
    registro.append(('Send Seguimiento Sin Correo', nodo_nuevo(w, 'Send Seguimiento Sin Correo', 'Send Not Client Message', sin, dx=0, dy=560)))
    aviso = copy.deepcopy(p('Notify Admin Followup Action Error'))
    aviso['subject'] = 'WhatsApp: seguimiento para coordinar a mano'
    aviso['html'] = ("=<p>Un número de WhatsApp quiso agendar un seguimiento y el bot no pudo agendarlo solo: "
                     "{{ $json.motivo === 'no_disponible' ? 'no pudimos revisar sus datos (WordPress no respondió)' : 'su ficha no tiene un correo registrado' }}.</p>"
                     "<p>Número: {{ $json.datos.phoneNumber }}<br>Opción: {{ ($json.opcion && $json.opcion.nombre) || 'sin opción' }}</p>"
                     "<p>Escríbele para coordinar la reunión{{ $json.motivo === 'no_disponible' ? '' : ' y completa el correo en su ficha del CRM' }}.</p>")
    registro.append(('Notify Admin Seguimiento Sin Correo', nodo_nuevo(w, 'Notify Admin Seguimiento Sin Correo', 'Notify Admin Followup Action Error', aviso, dx=0, dy=420)))
    conectar(w, 'Is Client?', [['Elegir Seguimiento'], [d['node'] for d in w['connections']['Is Client?']['main'][1]]])
    conectar(w, 'Elegir Seguimiento', [['Modo Seguimiento']])
    conectar(w, 'Modo Seguimiento', [['Guardar Elección Seguimiento'], ['Send Seguimiento Opciones'], ['Send Seguimiento Sin Correo', 'Notify Admin Seguimiento Sin Correo']])
    conectar(w, 'Guardar Elección Seguimiento', [['Prepare Followup Data']])

    # 6b) Revisión R2-01: freno antes del calendario del diagnóstico. Si la consulta de quién escribe se cayó en este mensaje
    # («Sumar Contexto» dejó reconocimiento=no_disponible), se pregunta primero si es la primera reunión o un seguimiento.
    # «Primera reunión» (btn_agendar_demo) vuelve al agente por «Button to Text», que no pasa por «Sumar Contexto»: no hay ciclo.
    # «Es un seguimiento» (seg_general) entra por la regla seg_ de «Button Action».
    freno = copy.deepcopy(p('Is Client?'))
    freno['conditions']['boolean'][0]['value1'] = "={{ $('Sumar Contexto').isExecuted && $('Sumar Contexto').first().json.reconocimiento === 'no_disponible' }}"
    freno['conditions']['boolean'][0]['value2'] = True
    registro.append(('¿Reconocimiento caído?', nodo_nuevo(w, '¿Reconocimiento caído?', 'Is Client?', freno, dx=-220, dy=-420)))
    confirmar = copy.deepcopy(p('Send Not Client Message'))
    confirmar['url'] = "=https://graph.facebook.com/v22.0/{{ $('Route Action').first().json.phoneNumberId }}/messages"
    confirmar['jsonBody'] = ("={{ JSON.stringify({ messaging_product: 'whatsapp', recipient_type: 'individual', to: $('Route Action').first().json.phoneNumber, "
                             "type: 'interactive', interactive: { type: 'button', body: { text: 'Antes de mostrarte el calendario: ¿es tu primera reunión con nosotros "
                             "o un seguimiento de tu proyecto o propuesta?' }, action: { buttons: [ { type: 'reply', reply: { id: 'btn_agendar_demo', title: 'Primera reunión' } }, "
                             "{ type: 'reply', reply: { id: 'seg_general', title: 'Es un seguimiento' } } ] } } }) }}")
    registro.append(('Send Confirmar Tipo Reunión', nodo_nuevo(w, 'Send Confirmar Tipo Reunión', 'Send Not Client Message', confirmar, dx=-220, dy=-560)))
    ra = w['connections']['Route Action']['main']
    i_cal = next(i for i, r in enumerate(p('Route Action')['rules']['values']) if r['conditions']['conditions'][0].get('rightValue') == 'show_calendar')
    if [d['node'] for d in ra[i_cal]] != ['¿Reconocimiento caído?']:
        destino_viejo = [d['node'] for d in ra[i_cal]]
        assert destino_viejo == ['Get Appointments Config'], f'Route Action (calendario) iba a {destino_viejo}, no al calendario'
        ra[i_cal] = [{'node': '¿Reconocimiento caído?', 'type': 'main', 'index': 0}]
    conectar(w, '¿Reconocimiento caído?', [['Send Confirmar Tipo Reunión'], ['Get Appointments Config']])

    # 7) «Prepare Followup Data» toma los datos de «Elegir Seguimiento» («Generate Days» lo lee por nombre)
    if p('Prepare Followup Data').get('jsonOutput') != "={{ $('Elegir Seguimiento').first().json.datos }}":
        p('Prepare Followup Data')['jsonOutput'] = "={{ $('Elegir Seguimiento').first().json.datos }}"
        registro.append(('Prepare Followup Data', 'datos de Elegir Seguimiento'))

    # 8) «Button Action»: regla «Seguimiento Elegido» (seg_) → «Contexto Elegido» → «Elegir Seguimiento»
    registro.append(('Contexto Elegido', nodo_nuevo(w, 'Contexto Elegido', 'Contexto Propuesta', http_contexto(False), dx=220, dy=260, on_error='continueRegularOutput')))
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

    # 10b) Revisión B-04: al reagendar, «Save Reschedule Client» guarda también el vínculo de la reunión que se reagenda
    src = p('Save Reschedule Client')
    if 'contrato_id' not in src['value']:
        ancla = "company: $json.company_name || '', "
        assert src['value'].count(ancla) == 1, 'Save Reschedule Client: el ancla no calza una sola vez'
        src['value'] = src['value'].replace(ancla, ancla + "contrato_id: Number($json.contrato_id) || 0, propuesta_id: Number($json.propuesta_id) || 0, ")
        registro.append(('Save Reschedule Client', 'guarda contrato_id y propuesta_id al reagendar'))

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
    """Revisa la sintaxis del código del nodo como cuerpo de función (así lo corre n8n), por la entrada estándar y sin temporales."""
    revisor = "const c = require('fs').readFileSync(0, 'utf8'); new Function('$', '$input', '$json', c);"
    r = subprocess.run(['node', '-e', revisor], input=codigo, capture_output=True, text=True, encoding='utf-8')
    return r.returncode == 0, (r.stderr or '').strip()[:200]


def correr_js(codigo):
    """Corre JS con node por la entrada estándar (sin archivos temporales) y devuelve lo que imprime, como JSON."""
    r = subprocess.run(['node'], input=codigo, capture_output=True, text=True, encoding='utf-8')
    if r.returncode != 0:
        return {'_error': (r.stderr or '').strip().splitlines()[-1:] }
    return json.loads(r.stdout or 'null')


STUB_N8N = """
const STUBS = %s;
const $ = (n) => { const s = STUBS[n]; return { isExecuted: s !== undefined, first: () => { if (s === undefined) throw new Error('no ejecutado: ' + n); return { json: s }; } }; };
"""


def simular_nodo(codigo, stubs, entrada):
    """Corre el código de un nodo Code con $ y $input simulados (sin n8n) y devuelve el json del primer ítem."""
    js = STUB_N8N % json.dumps(stubs, ensure_ascii=False) + 'const $input = { first: () => ({ json: %s }) };\n' % json.dumps(entrada, ensure_ascii=False)
    js += 'const $json = $input.first().json;\n'
    js += 'const salida = (function () {\n' + codigo + '\n})();\nconst item = Array.isArray(salida) ? salida[0] : salida;\nconsole.log(JSON.stringify(item && item.json !== undefined ? item.json : item));\n'
    return correr_js(js)


def simular_calendario(w, stubs):
    """Revisión R2-01: desde una respuesta de diagnóstico del agente (<<ACTION:SHOW_CALENDAR>>) hasta el nodo que se ejecuta
    después del freno, con el código real de «Parse Response Actions», las reglas de «Route Action» y la condición del freno."""
    nodos = {n['name']: n for n in w['nodes']}
    con = w['connections']
    pr = simular_nodo(nodos['Parse Response Actions']['parameters']['jsCode'], stubs, {'output': 'Te muestro el calendario. <<ACTION:SHOW_CALENDAR>>'})
    accion = (pr or {}).get('action')
    reglas = nodos['Route Action']['parameters']['rules']['values']
    idx = next((i for i, r in enumerate(reglas) if r['conditions']['conditions'][0].get('rightValue') == accion), None)
    if idx is None:
        return f'sin regla para {accion}'
    destino = [d['node'] for d in con['Route Action']['main'][idx]]
    if destino != ['¿Reconocimiento caído?']:
        return f'Route Action va a {destino}, no al freno'
    frena = evaluar(nodos['¿Reconocimiento caído?']['parameters']['conditions']['boolean'][0]['value1'], {}, stubs)
    return [d['node'] for d in con['¿Reconocimiento caído?']['main'][0 if frena is True else 1]]


def evaluar(expresion, actual, stubs):
    """Evalúa una expresión «={{ … }}» de n8n con $json y $ simulados."""
    cuerpo = expresion.strip()
    assert cuerpo.startswith('={{') and cuerpo.endswith('}}'), 'no es una expresión de n8n'
    js = STUB_N8N % json.dumps(stubs, ensure_ascii=False) + 'const $json = %s;\n' % json.dumps(actual, ensure_ascii=False)
    js += 'console.log(JSON.stringify(' + cuerpo[3:-2] + '));\n'
    return correr_js(js)


def comprobar_fallos_y_reagendar(nodos, conexiones):
    """Revisión B de Codex: B-01 (una consulta caída no detiene el flujo ni ofrece diagnóstico) y B-04 (reagendar no pierde el vínculo)."""
    fallas = []
    for n in ('Contexto Cliente', 'Contexto Elegido', 'Verify Client'):
        if nodos[n].get('onError') != 'continueRegularOutput':
            fallas.append(f'{n}: un timeout o error de red detendría el flujo (falta onError=continueRegularOutput)')
    em = {'phoneNumber': '56911112222', 'phoneNumberId': '123', 'contactName': 'Persona Prueba'}
    caida = {'error': {'message': 'timeout of 5000ms exceeded'}}
    no_disp = {'situacion': 'no_disponible', 'nombre': '', 'opciones': [], 'diagnostico_proximo': '', 'elegida': None, 'agenda': {'nombre_completo': '', 'correo': '', 'empresa': ''}}
    nuevo = dict(no_disp, situacion='nuevo')
    base = {'Merge Data': {'chatInput': 'quiero agendar', 'phoneNumber': '56911112222'}, 'Contexto Propuesta': {}}
    # R2-01: la consulta caída (timeout o 503) llega al agente como tal y queda marcada; una persona nueva de verdad, no.
    for nombre, ctx, espera in (('timeout', caida, 'no_disponible'), ('503', no_disp, 'no_disponible'), ('persona nueva', nuevo, 'nuevo')):
        s = simular_nodo(nodos['Sumar Contexto']['parameters']['jsCode'], base, ctx)
        avisa = isinstance(s, dict) and 'No pudimos revisar' in str(s.get('chatInput'))
        if not isinstance(s, dict) or s.get('reconocimiento') != espera or s.get('chatInputOriginal') != 'quiero agendar' or avisa != (espera == 'no_disponible') \
                or not str(s.get('chatInput')).endswith('quiero agendar'):
            fallas.append(f'Sumar Contexto ({nombre}) no deja reconocimiento={espera} y la línea correcta: {s}')
    # R2-01: si el agente igual responde con el calendario del diagnóstico, el freno pregunta antes; con persona nueva o desde un botón, no.
    for nombre, stubs, espera in (('consulta caída', {'Sumar Contexto': {'reconocimiento': 'no_disponible'}}, ['Send Confirmar Tipo Reunión']),
                                  ('persona nueva', {'Sumar Contexto': {'reconocimiento': 'nuevo'}}, ['Get Appointments Config']),
                                  ('cliente', {'Sumar Contexto': {'reconocimiento': 'cliente'}}, ['Get Appointments Config']),
                                  ('después de tocar «Primera reunión»', {}, ['Get Appointments Config'])):
        r = simular_calendario({'nodes': list(nodos.values()), 'connections': conexiones}, stubs)
        if r != espera:
            fallas.append(f'calendario del diagnóstico ({nombre}): termina en {r}, se esperaba {espera}')
    msg = evaluar(nodos['Send Confirmar Tipo Reunión']['parameters']['jsonBody'], {}, {'Route Action': {'phoneNumber': '56911112222', 'phoneNumberId': '123'}})
    try:
        botones = json.loads(msg)['interactive']['action']['buttons']
        if [b['reply']['id'] for b in botones] != ['btn_agendar_demo', 'seg_general'] or any(len(b['reply']['title']) > 20 or 'demo' in b['reply']['title'].lower() for b in botones):
            fallas.append(f'Send Confirmar Tipo Reunión: botones inesperados {botones}')
    except Exception:
        fallas.append(f'Send Confirmar Tipo Reunión: el cuerpo no es un mensaje válido: {msg}')
    e = simular_nodo(nodos['Elegir Seguimiento']['parameters']['jsCode'], {'Extract Message Data': em, 'Route Action': {'output': 'x'}}, caida)
    if not isinstance(e, dict) or e.get('modo') != 'sin_correo' or e.get('motivo') != 'no_disponible':
        fallas.append(f'Elegir Seguimiento con la consulta caída promete una agenda que no se puede guardar: {e}')
    aviso = evaluar(nodos['Send Seguimiento Sin Correo']['parameters']['jsonBody'], {'datos': {'phoneNumber': '56911112222'}, 'aviso': 'AVISO DE PRUEBA'}, {})
    if not isinstance(aviso, str) or json.loads(aviso).get('text', {}).get('body') != 'AVISO DE PRUEBA':
        fallas.append(f'Send Seguimiento Sin Correo no usa el aviso de Elegir Seguimiento: {aviso}')
    ctx = {'situacion': 'cliente', 'nombre': 'Persona', 'opciones': [{'tipo': 'proyecto', 'id': 7, 'nombre': 'Proyecto', 'plan_codigo': '', 'agenda': {'nombre_completo': 'P', 'correo': 'p@prueba.cl', 'empresa': ''}}], 'agenda': {'nombre_completo': 'P', 'correo': 'p@prueba.cl', 'empresa': ''}}
    e = simular_nodo(nodos['Elegir Seguimiento']['parameters']['jsCode'], {'Extract Message Data': em, 'Process Interactive': {'buttonId': 'seg_c_99'}}, ctx)
    if not isinstance(e, dict) or e.get('modo') != 'elegir':
        fallas.append(f'Elegir Seguimiento: un botón viejo no vuelve a preguntar: {e}')
    for contrato, propuesta in ((17, 0), (0, 5)):
        reunion = {'client_name': 'Persona Prueba', 'client_email': 'p@prueba.cl', 'company_name': '', 'meeting_subject': 'Seguimiento: X',
                   'notes': 'Plan de trabajo Ab3dE6gH9jK1. Agendada por el cliente por WhatsApp.', 'contrato_id': contrato, 'propuesta_id': propuesta}
        guardado = evaluar(nodos['Save Reschedule Client']['parameters']['value'], reunion, {'Extract Followup Reschedule ID': {'phoneNumber': '56911112222'}})
        if not isinstance(guardado, str) or json.loads(guardado).get('contrato_id', 0) != contrato or json.loads(guardado).get('propuesta_id', 0) != propuesta:
            fallas.append(f'Save Reschedule Client no guarda el vínculo de la reunión ({contrato}, {propuesta}): {guardado}')
            continue
        for nodo, origen in (('Prepare Seguimiento Data Direct', 'Extract Time'), ('Prepare Seguimiento Custom', 'Parse Custom Date')):
            r = evaluar(nodos[nodo]['parameters']['jsonOutput'], {'reschedClient': guardado}, {origen: {'selectedDay': '2026-11-20', 'selectedTime': '10:00', 'phoneNumber': '56911112222'}})
            if not isinstance(r, dict) or r.get('contrato_id') != contrato or r.get('propuesta_id') != propuesta or 'Plan de trabajo' not in str(r.get('notes')):
                fallas.append(f'{nodo}: el reagendamiento pierde el vínculo o la nota ({contrato}, {propuesta}): {r}')
    return fallas


def nodos_tocados(antes, despues):
    """Nodos nuevos o con algún cambio que hay que subir y verificar: parámetros, onError o sus conexiones de salida (R2-05)."""
    previos = {n['name']: n for n in antes['nodes']}
    huella = lambda x: json.dumps(x, sort_keys=True, ensure_ascii=False)
    tocados = []
    for n in despues['nodes']:
        a = previos.get(n['name'])
        if (a is None or huella(n.get('parameters')) != huella(a.get('parameters')) or n.get('onError') != a.get('onError')
                or huella(despues['connections'].get(n['name'])) != huella(antes['connections'].get(n['name']))):
            tocados.append(n['name'])
    return tocados


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
    tocados = nodos_tocados(antes, w)
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
        fallas += comprobar_fallos_y_reagendar(nodos, w['connections'])
    else:
        corte = lambda s: s.split(' : {', 1)[1] if ' : {' in s else ''
        a = next(x for x in antes['nodes'] if x['name'] == 'Prepare Payload')['parameters']['assignments']['assignments'][0]['value']
        d = nodos['Prepare Payload']['parameters']['assignments']['assignments'][0]['value']
        if corte(a) != corte(d):
            fallas.append('Prepare Payload: el objeto del diagnóstico cambió')
    return tocados, fallas


def contiene(publicado, esperado):
    """¿Lo publicado trae todo lo esperado? Admite claves que n8n agregue por su cuenta; no admite valores distintos."""
    if isinstance(esperado, dict):
        return isinstance(publicado, dict) and all(k in publicado and contiene(publicado[k], v) for k, v in esperado.items())
    if isinstance(esperado, list):
        return isinstance(publicado, list) and len(publicado) == len(esperado) and all(contiene(a, b) for a, b in zip(publicado, esperado))
    return publicado == esperado


def verificar_publicacion(esperado, releido, tocados, estaba_activo, cod_activate, cod_get):
    """Revisión B-05: lo que quedó corriendo es exactamente lo que se subió. Devuelve la lista de fallas ([] si está bien).
    Un flujo activo se compara contra su activeVersion (lo publicado), no contra el borrador."""
    fallas = []
    if estaba_activo and cod_activate != 200:
        fallas.append(f'activate respondió {cod_activate}')
    if cod_get != 200:
        return fallas + [f'la relectura respondió {cod_get}']
    if releido.get('active') != estaba_activo:
        fallas.append(f'el flujo quedó active={releido.get("active")} y estaba {estaba_activo}')
    if estaba_activo:
        if releido.get('activeVersionId') != releido.get('versionId'):
            fallas.append('la versión activa no es la que se subió')
        av = releido.get('activeVersion') or {}
        nodos, conexiones = av.get('nodes') or [], av.get('connections') or {}
    else:
        nodos, conexiones = releido.get('nodes') or [], releido.get('connections') or {}
    pub = {n['name']: n for n in nodos}
    esp = {n['name']: n for n in esperado['nodes']}
    for t in tocados:
        if t not in pub:
            fallas.append(f'{t}: no está en lo publicado')
            continue
        if not contiene(pub[t].get('parameters'), esp[t].get('parameters')):
            fallas.append(f'{t}: los parámetros publicados no son los subidos')
        if esp[t].get('onError') and pub[t].get('onError') != esp[t].get('onError'):
            fallas.append(f'{t}: onError publicado distinto')
        if t in esperado['connections'] and not contiene(conexiones.get(t), esperado['connections'][t]):
            fallas.append(f'{t}: sus conexiones publicadas no son las subidas')
    return fallas


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
        c2 = None
        if antes.get('active'):
            c2, _ = api('POST', f'/workflows/{wid}/activate')
            print(f'{flujo}: subido y republicado ({c2})')
        c3, w3 = api('GET', '/workflows/' + wid)
        fallas = verificar_publicacion(w, w3, tocados, bool(antes.get('active')), c2, c3)
        print(f'{flujo}: releído activo={w3.get("active")} (antes {antes.get("active")}) | {len(tocados)} nodo(s) tocados verificados en lo publicado: {"OK" if not fallas else "FALLAN"}')
        for f in fallas:
            print('   ✗', f)
        assert not fallas, f'{flujo}: lo publicado no coincide con lo subido. Detenido; para volver atrás, subir {flujo}-{wid}.antes.json de {carpeta}'
    print('respaldos en', carpeta)


if __name__ == '__main__':
    main()
