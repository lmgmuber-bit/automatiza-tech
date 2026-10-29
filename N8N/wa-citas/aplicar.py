"""Tech WhatsApp: cancelar y reprogramar con botones, enlace al portal y tipo de cambio protegido. Aplica los cambios en n8n.

Uso:  python -I -B -X utf8 N8N/wa-citas/aplicar.py           (vista previa: GET, cambios en memoria y comprobaciones)
      python -I -B -X utf8 N8N/wa-citas/aplicar.py --subir   (respalda, sube agenda, WhatsApp y web, y verifica lo publicado)

Diseño: Docs/superpowers/specs/2026-10-29-whatsapp-citas-botones-design.md. Plan: Docs/superpowers/plans/2026-10-29-whatsapp-citas-botones.md.
Reusa N8N/wa-reconoce/aplicar.py (sus cambios se vuelven a aplicar primero: son idempotentes y llevan el bloque nuevo de
contexto.js con la línea del portal). Dueño de los flujos: Eje. Antes de --subir, avisar a la sesión de Instagram (agenda).
La clave de n8n se lee de ~/.claude.json y nunca se imprime. Respaldos en ~/respaldos/n8n/wa-citas/<fecha>/.
"""
import copy
import datetime
import importlib.util
import io
import json
import os
import sys

AQUI = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location('wa_reconoce_aplicar', os.path.join(AQUI, '..', 'wa-reconoce', 'aplicar.py'))
reconoce = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(reconoce)

WHATSAPP, AGENDA, WEB = reconoce.WHATSAPP, reconoce.AGENDA, '3A5Y1NPBxpTjhHdE'
RUTA_CITAS = 'https://automatizatech.cl/wp-json/automatiza-tech/v1/wa-citas'
MARCAS = ('// <<WA CITAS: inicio>>', '// <<WA CITAS: fin>>')
CAMBIO_WA = ('Get Exchange Rate', 'Get Exchange Rate Audio', 'Get Exchange Rate Image')
CAMBIO_WEB = ('Get Exchange Rate',)
LINEA_TASA = 'Si no tienes el tipo de cambio (la tasa viene vacía o en 0), da los precios solo en USD; nunca escribas "$0 CLP".'
ANCLA_TASA = {'whatsapp': ('Agente IA - Tech WhatsApp', 'REGLAS DE PRECIOS\n'), 'web': ('Agente IA - Tech', '### 💰 REGLAS CRÍTICAS DE PRECIOS (IMPORTANTE)\n')}
ACCIONES = ('cancel_appointment', 'reschedule_appointment', 'reschedule_followup')
ASK_VIEJOS = {'cancel_appointment': 'Ask Cancel Data', 'reschedule_appointment': 'Ask Reschedule Data', 'reschedule_followup': 'Ask Reschedule Followup Data'}


def bloque():
    js = io.open(os.path.join(AQUI, 'nodos', 'citas.js'), encoding='utf-8').read().replace('\r\n', '\n')
    return js[js.index(MARCAS[0]):js.index(MARCAS[1]) + len(MARCAS[1])]


def codigo_mensaje(b):
    return b + """

// Envoltura de «Mensaje Citas»: el agente pidió cancelar o reprogramar; «Citas del Número» trae las citas de este número.
const r = $input.first().json || {};
const ra = $('Route Action').first().json || {};
const accion = ra.action === 'cancel_appointment' ? 'cancelar' : 'reprogramar';
let situacion = '';
try { if ($('Contexto Cliente').isExecuted) situacion = $('Contexto Cliente').first().json.situacion || ''; } catch (e) {}
const x = waMensajeCitas({ estado: r.estado, citas: r.citas, situacion }, accion, ra.phoneNumber);
return [{ json: { ...x, accion, phoneNumber: ra.phoneNumber, phoneNumberId: ra.phoneNumberId,
  motivoLuis: x.avisarLuis ? 'no pudimos revisar sus citas (WordPress no respondió)' : '' }, pairedItem: 0 }];
"""


def codigo_decidir(b):
    return b + """

// Envoltura de «Decidir Botón Cita»: la persona tocó un botón cita_…; «Citas Vigentes» trae la lista fresca de su número,
// así un botón viejo (cita ya cancelada o de otro número) nunca cancela ni mueve otra cosa.
const r = $input.first().json || {};
const pi = $('Process Interactive').first().json || {};
const x = waDecidirBoton(pi.buttonId, { estado: r.estado, citas: r.citas }, pi.phoneNumber);
const motivo = x.paso === 'tengo' ? 'dice que tiene una cita agendada y no la encontramos con su número (pudo agendar con otro número o correo)'
  : x.avisarLuis ? 'no pudimos revisar sus citas (WordPress no respondió)' : '';
return [{ json: { ...x, phoneNumber: pi.phoneNumber, phoneNumberId: pi.phoneNumberId, motivoLuis: motivo }, pairedItem: 0 }];
"""


def http_citas(metodo='GET', cuerpo=None):
    """GET /wa-citas del número que escribe, o POST /wa-citas/cancelar; con la clave X-AT-Secret y sin cortar el flujo."""
    p = {'method': metodo, 'url': RUTA_CITAS + ('/cancelar' if metodo == 'POST' else ''), 'authentication': 'genericCredentialType',
         'genericAuthType': 'httpHeaderAuth', 'options': {'timeout': 40000 if metodo == 'POST' else 8000, 'response': {'response': {'neverError': True}}}}
    if metodo == 'GET':
        p.update({'sendQuery': True, 'queryParameters': {'parameters': [{'name': 'phone', 'value': "={{ $('Extract Message Data').first().json.phoneNumber }}"}]}})
    else:
        p.update({'sendBody': True, 'specifyBody': 'json', 'jsonBody': cuerpo})
    return p


def envio_whatsapp(nodos, cuerpo, numero_id='$json.phoneNumberId'):
    """Mensaje por la API de WhatsApp con las credenciales de «Send Not Client Message»; numero_id es una expresión JS."""
    e = copy.deepcopy(nodos['Send Not Client Message']['parameters'])
    e['url'] = '=https://graph.facebook.com/v22.0/{{ ' + numero_id + ' }}/messages'
    e['jsonBody'] = cuerpo
    return e


def texto_decidir(cuerpo_js):
    """Mensaje de texto al número del botón, con el cuerpo como expresión JS."""
    return ("={{ JSON.stringify({ messaging_product: 'whatsapp', recipient_type: 'individual', to: $('Decidir Botón Cita').first().json.phoneNumber, "
            "type: 'text', text: { body: " + cuerpo_js + " } }) }}")


def si(nodos, condicion):
    f = copy.deepcopy(nodos['Is Client?']['parameters'])
    f['conditions']['boolean'][0]['value1'] = condicion
    f['conditions']['boolean'][0]['value2'] = True
    return f


def aviso_luis(nodos, asunto, html):
    a = copy.deepcopy(nodos['Notify Admin Followup Action Error']['parameters'])
    a['subject'], a['html'] = asunto, html
    return a


def selector(nodos, expresion, salidas):
    regla = copy.deepcopy(nodos['Route Action']['parameters']['rules']['values'][0])
    reglas = []
    for s in salidas:
        r = copy.deepcopy(regla)
        r['conditions']['conditions'][0]['leftValue'] = expresion
        r['conditions']['conditions'][0]['rightValue'] = s
        r['conditions']['conditions'][0].pop('id', None)
        r['outputKey'] = s
        reglas.append(r)
    return {'rules': {'values': reglas}, 'options': {'fallbackOutput': 'none'}}


def proteger_cambio(w, nombres, registro):
    """Revisión del 29-oct: un tipo de cambio lento o caído no deja al bot sin responder (sigue y el agente da USD)."""
    nodos = {n['name']: n for n in w['nodes']}
    for nombre in nombres:
        n = nodos[nombre]
        antes = json.dumps([n.get('onError'), n.get('retryOnFail'), n.get('maxTries'), n.get('waitBetweenTries'), n['parameters'].get('options', {}).get('timeout')])
        n['onError'], n['retryOnFail'], n['maxTries'], n['waitBetweenTries'] = 'continueRegularOutput', True, 3, 1000
        n['parameters'].setdefault('options', {})['timeout'] = 8000
        if json.dumps([n.get('onError'), n.get('retryOnFail'), n.get('maxTries'), n.get('waitBetweenTries'), 8000]) != antes:
            registro.append((nombre, 'onError, timeout 8 s y 3 intentos'))


def linea_tasa(w, flujo, registro):
    nodo, ancla = ANCLA_TASA[flujo]
    p = {n['name']: n for n in w['nodes']}[nodo]['parameters']
    clave = 'systemMessage' if 'systemMessage' in p.get('options', {}) else None
    texto = p['options']['systemMessage'] if clave else p['text']
    if LINEA_TASA in texto:
        return
    assert texto.count(ancla) == 1, f'{nodo}: el ancla de precios calza {texto.count(ancla)} veces'
    texto = texto.replace(ancla, ancla + LINEA_TASA + '\n')
    if clave:
        p['options']['systemMessage'] = texto
    else:
        p['text'] = texto
    registro.append((nodo, 'sin tasa, precios solo en USD'))


def cambiar_whatsapp_citas(w, b, registro):
    nodos = {n['name']: n for n in w['nodes']}
    p = lambda nombre: nodos[nombre]['parameters']
    nuevo = lambda nombre, plantilla, params, dx, dy, **k: (registro.append((nombre, reconoce.nodo_nuevo(w, nombre, plantilla, params, dx=dx, dy=dy, **k))), nodos.update({n['name']: n for n in w['nodes']}))

    # 1) Cancelar y reprogramar desde el agente: ya no se piden datos escritos; se muestran las citas del número.
    nuevo('Citas del Número', 'Contexto Propuesta', http_citas(), 0, 700, on_error='continueRegularOutput')
    nuevo('Mensaje Citas', 'Sumar Contexto', {'jsCode': codigo_mensaje(b)}, 220, 700)
    nuevo('Send Citas', 'Send Not Client Message', envio_whatsapp(nodos, '={{ JSON.stringify($json.mensaje) }}'), 440, 700)
    nuevo('¿Avisar a Luis (citas)?', 'Is Client?', si(nodos, '={{ $json.avisarLuis === true }}'), 440, 860)
    nuevo('Notify Admin Citas', 'Notify Admin Followup Action Error', aviso_luis(nodos, 'WhatsApp: citas para revisar a mano',
          '=<p>Un número de WhatsApp necesita ayuda con sus citas: {{ $json.motivoLuis }}.</p><p>Número: {{ $json.phoneNumber }}</p><p>Escríbele para coordinarlo.</p>'), 660, 860)
    reconoce.conectar(w, 'Citas del Número', [['Mensaje Citas']])
    reconoce.conectar(w, 'Mensaje Citas', [['Send Citas', '¿Avisar a Luis (citas)?']])
    reconoce.conectar(w, '¿Avisar a Luis (citas)?', [['Notify Admin Citas'], []])
    reglas = p('Route Action')['rules']['values']
    ra = w['connections']['Route Action']['main']
    for accion in ACCIONES:
        i = next(k for k, r in enumerate(reglas) if r['conditions']['conditions'][0].get('rightValue') == accion)
        destino = [d['node'] for d in ra[i]]
        if destino != ['Citas del Número']:
            assert destino == [ASK_VIEJOS[accion]], f'Route Action ({accion}) iba a {destino}, no a {ASK_VIEJOS[accion]}'
            ra[i] = [{'node': 'Citas del Número', 'type': 'main', 'index': 0}]
            registro.append(('Route Action', f'{accion} → citas con botones'))

    # 2) Botones cita_…: lista fresca del número → decidir → mensaje, cancelar o reprogramar (Tarea 8).
    nuevo('Citas Vigentes', 'Contexto Propuesta', http_citas(), 220, 1100, on_error='continueRegularOutput')
    nuevo('Decidir Botón Cita', 'Sumar Contexto', {'jsCode': codigo_decidir(b)}, 440, 1100)
    nuevo('Ruta Botón Cita', 'Route Action', selector(nodos, "={{ ['cancelar', 'reprogramar'].includes($json.paso) ? $json.paso : 'mensaje' }}",
                                                      ('mensaje', 'cancelar', 'reprogramar')), 660, 1100)
    cuerpo = "={{ JSON.stringify({ phone: $json.phoneNumber, tipo: $json.cita.tipo, id: $json.cita.id, motivo: 'cancelada' }) }}"
    nuevo('Cancelar Cita', 'Contexto Propuesta', http_citas('POST', cuerpo), 880, 1100, on_error='continueRegularOutput')
    nuevo('¿Cita cancelada?', 'Is Client?', si(nodos, '={{ $json.ok === true }}'), 1100, 1100)
    numero = "$('Decidir Botón Cita').first().json.phoneNumberId"
    nuevo('Send Cita Cancelada', 'Send Not Client Message', envio_whatsapp(nodos, texto_decidir(
        "'Listo, cancelamos tu cita: ' + $('Decidir Botón Cita').first().json.cita.etiqueta + '. Te mandamos la confirmación a tu correo. Si quieres agendar otra, escríbeme cuando quieras.'"), numero), 1320, 1040)
    nuevo('Send Cita No Cancelada', 'Send Not Client Message', envio_whatsapp(nodos, texto_decidir("'No pude cancelarla en este momento, ya le avisamos a Luis.'"), numero), 1320, 1180)
    nuevo('Notify Admin Cita No Cancelada', 'Notify Admin Followup Action Error', aviso_luis(nodos, 'WhatsApp: no se pudo cancelar una cita',
          "=<p>Por WhatsApp pidieron cancelar la cita {{ $('Decidir Botón Cita').first().json.cita.etiqueta }} y no se pudo "
          "({{ $json.motivo || 'WordPress no respondió' }}).</p><p>Número: {{ $('Decidir Botón Cita').first().json.phoneNumber }}</p><p>Revísala en el CRM y escríbele.</p>"), 1320, 1320)
    reconoce.conectar(w, 'Citas Vigentes', [['Decidir Botón Cita']])
    reconoce.conectar(w, 'Decidir Botón Cita', [['Ruta Botón Cita']])
    previa = w['connections'].get('Ruta Botón Cita', {}).get('main', [])
    reprogramar = previa[2] if len(previa) > 2 else []
    w['connections']['Ruta Botón Cita'] = {'main': [[{'node': 'Send Citas', 'type': 'main', 'index': 0}, {'node': '¿Avisar a Luis (citas)?', 'type': 'main', 'index': 0}],
                                                    [{'node': 'Cancelar Cita', 'type': 'main', 'index': 0}], reprogramar]}
    reconoce.conectar(w, 'Cancelar Cita', [['¿Cita cancelada?']])
    reconoce.conectar(w, '¿Cita cancelada?', [['Send Cita Cancelada'], ['Send Cita No Cancelada', 'Notify Admin Cita No Cancelada']])
    valores = p('Button Action')['rules']['values']
    if not any(any(c.get('rightValue') == 'cita_' for c in r['conditions']['conditions']) for r in valores):
        # Va justo antes de la regla seg_ (los prefijos no se pisan), así seg_ sigue siendo la última, como espera wa-reconoce.
        i_seg = next(k for k, r in enumerate(valores) if any(c.get('rightValue') == 'seg_' for c in r['conditions']['conditions']))
        regla = copy.deepcopy(valores[i_seg])
        regla['conditions']['conditions'][0]['rightValue'] = 'cita_'
        regla['conditions']['conditions'][0].pop('id', None)
        regla['outputKey'] = 'Cita Elegida'
        main = w['connections']['Button Action']['main']
        while len(main) < len(valores):
            main.append([])
        valores.insert(i_seg, regla)
        main.insert(i_seg, [{'node': 'Citas Vigentes', 'type': 'main', 'index': 0}])
        registro.append(('Button Action', 'regla Cita Elegida (cita_)'))

    # 3) Tipo de cambio protegido y prompt sin «$0 CLP».
    proteger_cambio(w, CAMBIO_WA, registro)
    linea_tasa(w, 'whatsapp', registro)


def cambiar_web(w, registro):
    proteger_cambio(w, CAMBIO_WEB, registro)
    linea_tasa(w, 'web', registro)


def cambiar(flujo, w, b_reconoce, b_citas, registro):
    if flujo == 'whatsapp':
        reconoce.cambiar_whatsapp(w, b_reconoce, registro)
        cambiar_whatsapp_citas(w, b_citas, registro)
    elif flujo == 'agenda':
        reconoce.cambiar_agenda(w, registro)
    else:
        cambiar_web(w, registro)


def comprobar_citas(w, b):
    """Comprobaciones propias de las citas con botones (además de las de wa-reconoce)."""
    nodos = {n['name']: n for n in w['nodes']}
    con = w['connections']
    fallas = []
    reglas = nodos['Route Action']['parameters']['rules']['values']
    for accion in ACCIONES:
        i = next(k for k, r in enumerate(reglas) if r['conditions']['conditions'][0].get('rightValue') == accion)
        if [d['node'] for d in con['Route Action']['main'][i]] != ['Citas del Número']:
            fallas.append(f'Route Action ({accion}) no va a Citas del Número')
    for n in ('Mensaje Citas', 'Decidir Botón Cita'):
        codigo = nodos[n]['parameters']['jsCode']
        if b not in codigo:
            fallas.append(f'{n}: el bloque no es idéntico al de citas.js')
        ok, err = reconoce.comprobar_js(n, codigo)
        if not ok:
            fallas.append(f'{n}: node --check falla: {err}')
    for n in ('Citas del Número', 'Citas Vigentes', 'Cancelar Cita'):
        if nodos[n].get('onError') != 'continueRegularOutput':
            fallas.append(f'{n}: una consulta caída detendría el flujo')
    ra = {'action': 'cancel_appointment', 'phoneNumber': '56900001111', 'phoneNumberId': '123'}
    m = reconoce.simular_nodo(nodos['Mensaje Citas']['parameters']['jsCode'], {'Route Action': ra}, {'error': {'message': 'timeout'}})
    if not isinstance(m, dict) or m.get('avisarLuis') is not True or 'no pude revisar tus citas' not in json.dumps(m, ensure_ascii=False):
        fallas.append(f'consulta caída → Mensaje Citas no avisa a Luis: {m}')
    cita = {'tipo': 'diagnostico', 'id': 7, 'cuando': '2026-11-03 17:00', 'etiqueta': 'Diagnóstico gratis · mar 03-nov · 17:00'}
    m = reconoce.simular_nodo(nodos['Mensaje Citas']['parameters']['jsCode'], {'Route Action': ra, 'Contexto Cliente': {'situacion': 'prospecto'}}, {'estado': 'ok', 'citas': [cita]})
    ids = [b_['reply']['id'] for b_ in (m or {}).get('mensaje', {}).get('interactive', {}).get('action', {}).get('buttons', [])] if isinstance(m, dict) else []
    if ids != ['cita_ok_c_d_7', 'btn_mantener_cita']:
        fallas.append(f'Mensaje Citas con una cita no pide confirmar: {m}')
    pi = {'buttonId': 'cita_ok_c_d_8', 'phoneNumber': '56900001111', 'phoneNumberId': '123'}
    d = reconoce.simular_nodo(nodos['Decidir Botón Cita']['parameters']['jsCode'], {'Process Interactive': pi}, {'estado': 'ok', 'citas': [cita]})
    if not isinstance(d, dict) or d.get('paso') != 'no_disponible' or d.get('cita') is not None:
        fallas.append(f'un botón de una cita que ya no está no vuelve a mostrar las vigentes: {d}')
    sel = reconoce.evaluar(nodos['Ruta Botón Cita']['parameters']['rules']['values'][0]['conditions']['conditions'][0]['leftValue'], {'paso': 'confirmar'}, {})
    if sel != 'mensaje':
        fallas.append(f'Ruta Botón Cita no manda «confirmar» al envío: {sel}')
    valores = nodos['Button Action']['parameters']['rules']['values']
    i = next((k for k, r in enumerate(valores) if r['conditions']['conditions'][0].get('rightValue') == 'cita_'), None)
    if i is None or [d_['node'] for d_ in con['Button Action']['main'][i]] != ['Citas Vigentes'] or nodos['Button Action']['parameters'].get('options', {}).get('fallbackOutput') != 'extra':
        fallas.append('Button Action: la regla cita_ no sale a Citas Vigentes o se perdió el fallback')
    fallas += comprobar_cambio(w, CAMBIO_WA)
    return fallas


def comprobar_cambio(w, nombres):
    nodos = {n['name']: n for n in w['nodes']}
    fallas = []
    for n in nombres:
        x = nodos[n]
        if (x.get('onError'), x.get('retryOnFail'), x.get('maxTries'), x['parameters'].get('options', {}).get('timeout')) != ('continueRegularOutput', True, 3, 8000):
            fallas.append(f'{n}: el tipo de cambio no quedó protegido')
    return fallas


def comprobar(flujo, w, antes, b_reconoce, b_citas):
    if flujo == 'web':
        nodos = {n['name']: n for n in w['nodes']}
        fallas = [f'{o} conecta a un nodo que no existe: {d["node"]}' for o, v in w['connections'].items()
                  for rama in v.get('main', []) for d in rama or [] if d['node'] not in nodos]
        return reconoce.nodos_tocados(antes, w), fallas + comprobar_cambio(w, CAMBIO_WEB)
    tocados, fallas = reconoce.comprobar(flujo, w, antes, b_reconoce)
    if flujo == 'whatsapp':
        fallas += comprobar_citas(w, b_citas)
    return tocados, fallas


def verificar_reintentos(releido, nombres):
    av = releido.get('activeVersion') or releido
    pub = {n['name']: n for n in av.get('nodes') or []}
    return [f'{n}: reintentos publicados distintos' for n in nombres
            if (pub.get(n, {}).get('retryOnFail'), pub.get(n, {}).get('maxTries')) != (True, 3)]


def main():
    subir = '--subir' in sys.argv
    key = json.load(io.open(os.path.expanduser('~/.claude.json'), encoding='utf-8'))['mcpServers']['n8n-mcp']['env']['N8N_API_KEY']
    api = reconoce.api_con(key)
    b_reconoce, b_citas = reconoce.bloque(), bloque()
    carpeta = os.path.join(os.path.expanduser('~/respaldos/n8n/wa-citas'), datetime.datetime.now().strftime('%Y%m%d-%H%M%S'))
    resultado = {}
    for flujo, wid in (('agenda', AGENDA), ('whatsapp', WHATSAPP), ('web', WEB)):
        c, w = api('GET', '/workflows/' + wid)
        assert c == 200, f'{flujo}: GET {c}'
        antes = copy.deepcopy(w)
        registro = []
        cambiar(flujo, w, b_reconoce, b_citas, registro)
        tocados, fallas = comprobar(flujo, w, antes, b_reconoce, b_citas)
        otra, registro2 = copy.deepcopy(w), []
        cambiar(flujo, otra, b_reconoce, b_citas, registro2)
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
    for flujo in ('agenda', 'whatsapp', 'web'):
        wid, antes, w, tocados, _ = resultado[flujo]
        if not tocados:
            print(f'{flujo}: sin cambios')
            continue
        json.dump(antes, io.open(os.path.join(carpeta, f'{flujo}-{wid}.antes.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        cuerpo = {'name': w['name'], 'nodes': w['nodes'], 'connections': w['connections'],
                  'settings': {k: v for k, v in (w.get('settings') or {}).items() if k in reconoce.SETTINGS_OK}}
        c, r = api('PUT', '/workflows/' + wid, cuerpo)
        assert c == 200, f'{flujo}: PUT {c} {r}; el flujo quedó como estaba (respaldo en {carpeta})'
        c2 = None
        if antes.get('active'):
            c2, _ = api('POST', f'/workflows/{wid}/activate')
            print(f'{flujo}: subido y republicado ({c2})')
        c3, w3 = api('GET', '/workflows/' + wid)
        fallas = reconoce.verificar_publicacion(w, w3, tocados, bool(antes.get('active')), c2, c3)
        fallas += verificar_reintentos(w3, CAMBIO_WA if flujo == 'whatsapp' else CAMBIO_WEB if flujo == 'web' else ())
        print(f'{flujo}: releído activo={w3.get("active")} | {len(tocados)} nodo(s) verificados en lo publicado: {"OK" if not fallas else "FALLAN"}')
        for f in fallas:
            print('   ✗', f)
        assert not fallas, f'{flujo}: lo publicado no coincide. Detenido; para volver atrás, subir {flujo}-{wid}.antes.json de {carpeta}'
    print('respaldos en', carpeta)


if __name__ == '__main__':
    main()
