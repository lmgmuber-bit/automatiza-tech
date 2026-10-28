"""Pruebas del asistente de Instagram con JSON falso del Portal, en modo prueba: n8n no llama al Portal y agendar no crea
la cita. Activa los dos flujos durante la prueba y al terminar (aunque falle) los deja como estaban: si estaban activos (PROD), siguen activos. No imprime la ruta.

Uso:  python probar.py            (todos los casos)
      python probar.py precio     (solo los casos nombrados)

Lee el resultado de cada caso en las ejecuciones de n8n (nodos «Unir textos», «Preparar respuesta» y «Respuesta no
texto»). Las respuestas del modelo varían: los casos revisan lo que debe pasar (derivar, no agendar, mostrar CLP), no el
texto exacto. Cada corrida gasta OpenAI (gpt-4o-mini, centavos): avisar a Balance.
"""
import json
import os
import secrets
import sys
import time
import urllib.request

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.dont_write_bytecode = True
sys.path.insert(0, AQUI)
import aplicar  # noqa: E402  (solo usa su api() y el estado; no sube nada)

estado = json.load(open(aplicar.ESTADO, encoding='utf-8'))
WF = estado['principal']
HOOK = aplicar.URL + '/webhook/' + estado['ruta']
fallas = []
USADAS = set()  # ejecuciones ya contadas en un turno anterior
HERR = estado['herramientas']


def ok(cond, msg, detalle=''):
    print(('ok    ' if cond else 'FALLA ') + msg + (('\n      ' + detalle) if (detalle and not cond) else ''))
    if not cond:
        fallas.append(msg)


def enviar(igsid, conv, texto, tipo='text', contexto=None):
    body = {'event': 'new_message', 'conversation_id': conv, 'channel_id': 2, 'business_phone': '',
            'contact': {'external_id': igsid, 'name': 'Ana Prueba', 'phone': ''},
            'message': {'id': secrets.randbelow(10 ** 9), 'type': tipo, 'content': texto, 'media_url': ''},
            'callback_url': 'https://prueba.invalid/no-se-usa', 'callback_token': secrets.token_hex(32),
            'timestamp': time.strftime('%Y-%m-%dT%H:%M:%S'), 'prueba': True}
    if contexto:
        body['contexto'] = contexto
    req = urllib.request.Request(HOOK, data=json.dumps(body).encode(), method='POST',
                                 headers={'Content-Type': 'application/json'})
    with urllib.request.urlopen(req, timeout=30) as r:
        return r.status


def salida(run, nodo):
    try:
        return run[nodo][0]['data']['main'][0][0]['json']
    except (KeyError, IndexError, TypeError):
        return None


def ejecuciones(igsid, desde, esperar=90, cuantas=1):
    """Ejecuciones terminadas de este igsid desde 'desde' (epoch). Devuelve lista de runData."""
    fin = time.time() + esperar
    vistas, revisadas = {}, set()
    while time.time() < fin:
        c, j = aplicar.api('GET', '/executions?workflowId=%s&limit=30' % WF)
        for e in j.get('data', []):
            if e['id'] in revisadas or e['id'] in USADAS or not e.get('stoppedAt'):
                continue
            revisadas.add(e['id'])
            if e.get('startedAt', '') < time.strftime('%Y-%m-%dT%H:%M:%S', time.gmtime(desde - 5)):
                continue
            c2, d = aplicar.api('GET', '/executions/%s?includeData=true' % e['id'])
            run = (((d.get('data') or {}).get('resultData') or {}).get('runData')) or {}
            v = salida(run, 'Validar')
            if v and v.get('igsid') == igsid:
                vistas[e['id']] = run
                USADAS.add(e['id'])
        if len(vistas) >= cuantas:
            time.sleep(3)  # por si llega otra de la misma ráfaga
            return list(vistas.values())
        time.sleep(4)
    return list(vistas.values())


def turno(igsid, conv, texto, **kw):
    t0 = time.time()
    enviar(igsid, conv, texto, **kw)
    runs = ejecuciones(igsid, t0)
    resp = [salida(r, 'Preparar respuesta') or salida(r, 'Respuesta no texto') for r in runs]
    resp = [x for x in resp if x]
    return resp[-1] if resp else None


def nuevo():
    return 'prueba-' + secrets.token_hex(5), 990000 + secrets.randbelow(9000)


def proximo(dia_semana):  # 1 = lunes
    import datetime
    d = datetime.date.today() + datetime.timedelta(days=2)
    while d.isoweekday() != dia_semana:
        d += datetime.timedelta(days=1)
    return d


CASOS = {}


def caso(f):
    CASOS[f.__name__] = f
    return f


@caso
def basico():
    g, c = nuevo()
    r = turno(g, c, 'Hola, ¿qué hacen ustedes?')
    ok(r is not None and r['body'].get('content'), 'básico: responde', json.dumps(r, ensure_ascii=False)[:300])
    ok(r and r['body']['conversation_id'] == c and len(r['body']['callback_token']) == 64 and 'action' not in r['body'],
       'básico: callback con la conversación y el token, sin derivar')
    ok(r and '**' not in r['body']['content'] and len(r['body']['content']) <= 1000, 'básico: texto plano y corto')
    print('      →', (r or {}).get('body', {}).get('content', '')[:200].replace('\n', ' '))


@caso
def precio():
    g, c = nuevo()
    r = turno(g, c, '¿Cuánto cuesta el Plan Básico?')
    t = (r or {}).get('body', {}).get('content', '')
    ok('USD' in t and 'CLP' in t, 'precio: muestra USD y su equivalente en CLP', t[:300])
    print('      →', t[:200].replace('\n', ' '))


@caso
def fuera_de_tema():
    g, c = nuevo()
    r = turno(g, c, 'Dame una receta de pan amasado')
    t = (r or {}).get('body', {}).get('content', '').lower()
    ok(t and 'harina' not in t and not r['herramientas'], 'fuera de tema: no da la receta ni usa herramientas', t[:300])
    print('      →', t[:200].replace('\n', ' '))


@caso
def persona():
    g, c = nuevo()
    r = turno(g, c, 'Quiero hablar con una persona, por favor')
    ok(r and r['body'].get('action') == 'escalate' and r['body'].get('motivo'), 'persona: deriva con motivo',
       json.dumps(r, ensure_ascii=False)[:300])
    ok(r and r['body'].get('content'), 'persona: con despedida en el mismo aviso')
    print('      →', (r or {}).get('body', {}).get('motivo'), '|', (r or {}).get('body', {}).get('content', '')[:160])


@caso
def derivar_en_agenda():
    g, c = nuevo()
    r1 = turno(g, c, 'Quiero agendar el diagnóstico')
    r2 = turno(g, c, 'Mejor que me llame alguien del equipo, prefiero hablar con una persona')
    usadas = (r1 or {}).get('herramientas', []) + (r2 or {}).get('herramientas', [])
    ok(r2 and r2['body'].get('action') == 'escalate', 'derivar en medio de agenda: deriva', json.dumps(r2, ensure_ascii=False)[:300])
    ok('agendar' not in usadas, 'derivar en medio de agenda: no agenda', str(usadas))


@caso
def feriado():
    g, c = nuevo()
    r = turno(g, c, 'Quiero agendar el diagnóstico el sábado 31 de octubre de 2026 a las 16:00')
    t = (r or {}).get('body', {}).get('content', '')
    ok(r and 'ver_disponibilidad' in r['herramientas'] and 'agendar' not in r['herramientas'],
       'feriado: consulta la agenda y no agenda', str((r or {}).get('herramientas')))
    print('      →', t[:220].replace('\n', ' '))


@caso
def rafaga():
    g, c = nuevo()
    t0 = time.time()
    for txt in ('Hola', 'tengo una tienda de ropa', 'quiero automatizar los pedidos por Instagram'):
        enviar(g, c, txt)
        time.sleep(1.2)
    runs = ejecuciones(g, t0, cuantas=3)
    con_resp = [r for r in runs if salida(r, 'Preparar respuesta')]
    unir = [salida(r, 'Unir textos') for r in runs if salida(r, 'Unir textos')]
    ok(len(runs) == 3, 'ráfaga: 3 ejecuciones', str(len(runs)))
    ok(len(con_resp) == 1, 'ráfaga: una sola respuesta', str(len(con_resp)))
    ok(unir and unir[0]['mensajes'] == 3, 'ráfaga: la respuesta considera los 3 mensajes', json.dumps(unir, ensure_ascii=False)[:300])


@caso
def no_texto():
    g, c = nuevo()
    r1 = turno(g, c, '', tipo='image')
    ok(r1 and 'solo texto' in r1['body']['content'], 'no texto: aviso fijo', json.dumps(r1, ensure_ascii=False)[:200])
    r2 = turno(g, c, '', tipo='image')
    ok(r2 is None, 'no texto: un segundo aviso en menos de 10 min no se manda')


@caso
def agenda_simulada():
    g, c = nuevo()
    martes = proximo(2)
    r1 = turno(g, c, 'Hola, quiero agendar el diagnóstico gratis para el %s' % martes.isoformat())
    r2 = turno(g, c, 'A las 11:00. Soy Ana Rojas, mi correo es ana.rojas@ejemplo.cl.')
    t2 = (r2 or {}).get('body', {}).get('content', '')
    ok('agendar' not in (r1 or {}).get('herramientas', []) + (r2 or {}).get('herramientas', []) and '+56' in t2,
       'agenda: sin celular no agenda y lo pide con el ejemplo +56', t2[:300])
    r2b = turno(g, c, 'Mi WhatsApp es +56 9 1234 5678')
    t2b = (r2b or {}).get('body', {}).get('content', '')
    ok('agendar' not in (r2b or {}).get('herramientas', []), 'agenda: con todos los datos, primero confirma y no agenda', t2b[:300])
    r3 = turno(g, c, 'Sí, confirmo')
    usadas = sum(((x or {}).get('herramientas', []) for x in (r1, r2, r2b, r3)), [])
    # Desde el 28-oct «agendar» revisa la agenda real del día por su cuenta («Revisar hora»): que el modelo consulte antes
    # es deseable pero no obligatorio. Se informa, no se cuenta como falla.
    print('      (ver_disponibilidad antes de agendar: %s)' % ('sí' if 'ver_disponibilidad' in usadas else 'no; la revisó agendar'))
    ok('agendar' in (r3 or {}).get('herramientas', []) and '¡Listo' in (r3 or {}).get('body', {}).get('content', ''),
       'agenda: agenda después del «sí»', str(usadas))
    t3 = (r3 or {}).get('body', {}).get('content', '')
    ok('11:00' in t3 and 'ana.rojas@ejemplo.cl' in t3, 'agenda: confirma la hora y el correo de la cita', t3[:300])
    for i, x in enumerate((r1, r2, r2b, r3), 1):
        print('      %d →' % i, (x or {}).get('body', {}).get('content', '')[:220].replace('\n', ' '))


@caso
def agenda_whatsapp():
    g, c = nuevo()
    jueves = proximo(4)
    r1 = turno(g, c, 'Quiero agendar el diagnóstico el %s a las 10:00. Soy Ana Rojas, ana.rojas@ejemplo.cl' % jueves.isoformat())
    t1 = (r1 or {}).get('body', {}).get('content', '')
    ok(any(x in t1.lower() for x in ('whatsapp', 'celular', 'teléfono', 'telefono')) and 'agendar' not in (r1 or {}).get('herramientas', []),
       'whatsapp: pide el celular antes de agendar', t1[:300])
    r2 = turno(g, c, 'Mi WhatsApp es 9 1234 5678')
    t2 = (r2 or {}).get('body', {}).get('content', '')
    ok('agendar' not in (r2 or {}).get('herramientas', []) and '+56' in t2, 'whatsapp: sin código de país lo vuelve a pedir con el ejemplo', t2[:300])
    r3 = turno(g, c, '+56 9 1234 5678')
    r4 = r3 if 'agendar' in (r3 or {}).get('herramientas', []) else turno(g, c, 'Sí, confirmo')
    usadas = sum(((x or {}).get('herramientas', []) for x in (r1, r2, r3, r4)), [])
    ok('agendar' in usadas and '¡Listo' in (r4 or {}).get('body', {}).get('content', ''), 'whatsapp: con código de país, agenda', str(usadas))
    for i, x in enumerate((r1, r2, r3, r4), 1):
        print('      %d →' % i, (x or {}).get('body', {}).get('content', '')[:200].replace('\n', ' '))


@caso
def datos_malos():
    # Validación común del Cerebro AT: sin apellido y con un correo «@gmail.con» no agenda y los pide de nuevo.
    g, c = nuevo()
    martes = proximo(2)
    r1 = turno(g, c, 'Quiero agendar el diagnóstico el %s a las 15:00. Soy Ana, ana.rojas@gmail.con, WhatsApp +56 9 1234 5678' % martes.isoformat())
    r2 = turno(g, c, 'Sí, confirmo')
    t = ' '.join((x or {}).get('body', {}).get('content', '') for x in (r1, r2)).lower()
    ok('apellido' in t, 'datos malos: pide el apellido', t[:300])
    ok('gmail.com' in t, 'datos malos: sugiere gmail.com', t[:300])
    print('      →', t[:300].replace('\n', ' '))


@caso
def fuera_de_horario():
    # Guarda firme «Revisar hora»: una hora fuera del horario de atención no se agenda aunque la persona la confirme.
    g, c = nuevo()
    jueves = proximo(4)
    r = turno(g, c, 'Agenda el diagnóstico el %s a las 21:00. Soy Ana Rojas, ana.rojas@ejemplo.cl, WhatsApp +56 9 1234 5678. '
                    'Confirmo, agéndalo directo.' % jueves.isoformat())
    turnos = [r] if '¡Listo' in (r or {}).get('body', {}).get('content', '') else [r, turno(g, c, 'Sí, confirmo, a las 21:00')]
    agendo = any('¡Listo' in (x or {}).get('body', {}).get('content', '') for x in turnos)
    ok(not agendo, 'fuera de horario: no agenda a las 21:00', (turnos[-1] or {}).get('body', {}).get('content', '')[:300])
    print('      →', (turnos[-1] or {}).get('body', {}).get('content', '')[:200].replace('\n', ' '))


@caso
def comentario():
    g, c = nuevo()
    ctx = {'origen': 'comentario_palabra_clave', 'palabra': 'MÉTODO', 'fecha': '2026-10-24 15:57:58',
           'mensaje_privado': 'Hola, gracias por escribirnos. Así trabajamos en AutomatizaTech: diagnóstico, priorización, propuesta por '
                              'fases, desarrollo con avances, entrega probada y soporte. ¿Qué proceso de tu negocio te gustaría ordenar? '
                              'Si quieres, agenda tu diagnóstico gratis en automatizatech.cl'}
    r = turno(g, c, 'quiero agendar', contexto=ctx)
    t = (r or {}).get('body', {}).get('content', '')
    ok(r and 'action' not in r['body'] and ('diagn' in t.lower() or 'ver_disponibilidad' in r['herramientas']),
       'comentario: entiende que quiere agendar el diagnóstico', t[:300])
    ok('formulario' not in t.lower(), 'comentario: no lo manda al formulario de la web')
    print('      →', t[:220].replace('\n', ' '))


def main():
    elegidos = [a for a in sys.argv[1:] if a in CASOS] or list(CASOS)
    # Estado previo: si ya estaban activos (PROD desde el 28-oct), se dejan activos al terminar.
    activos = {w: bool(aplicar.api('GET', '/workflows/' + w)[1].get('active')) for w in (HERR, WF)}
    c0, _ = aplicar.api('POST', '/workflows/%s/activate' % HERR)  # n8n solo ejecuta sub-workflows activos
    c, _ = aplicar.api('POST', '/workflows/%s/activate' % WF)
    print('flujos activados para la prueba:', c0, c, '· antes:', 'activos' if all(activos.values()) else activos)
    try:
        time.sleep(3)
        for n in elegidos:
            print('\n==', n)
            try:
                CASOS[n]()
            except Exception as e:  # noqa: BLE001
                ok(False, n + ': error ' + type(e).__name__, str(e)[:200])
    finally:
        for w in (WF, HERR):
            if not activos[w]:
                aplicar.api('POST', '/workflows/%s/deactivate' % w)
        fin = {w: bool(aplicar.api('GET', '/workflows/' + w)[1].get('active')) for w in (WF, HERR)}
        print('\nestado final (igual al de antes):', fin == activos, fin)
    print('\n%d fallas' % len(fallas) if fallas else '\nTODO OK')
    sys.exit(1 if fallas else 0)


if __name__ == '__main__':
    main()
