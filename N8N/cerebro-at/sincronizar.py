"""Sincroniza el Cerebro AT con los asistentes Tech de n8n (web y WhatsApp; Instagram se copia del web con aplicar.py).

Uso:  python -I -X utf8 sincronizar.py                 (solo dice qué cambiaría y deja vistas previas fuera del repo)
      python -I -X utf8 sincronizar.py --subir         (respalda, sube, republica si estaba activo y verifica)
      ... --subir --instagram RUTA/aplicar.py           (además copia el prompt web a Instagram con aplicar.py)

Qué sincroniza (cada tramo va entre marcas en el prompt; lo de fuera de las marcas no se toca):
- reglas.md               -> «REGLAS AT» en web y WhatsApp (temas permitidos y seguridad).
- cerebro-at.md           -> «CEREBRO AT» en web y WhatsApp (quién es Tech, qué ofrecemos, Método AT, regla de precios, redes).
- planes.md               -> planes del Portal con precio, al final de la personalidad web y de la de WhatsApp (nunca a Instagram).
- personalidad-web.md     -> «PERSONALIDAD» del web, dentro del tramo que aplicar.py le quita a Instagram.
- personalidad-whatsapp.md -> «PERSONALIDAD» de WhatsApp.
- validacion.js           -> nodo «Check Email Booking» de WhatsApp (nombre y correo para agendar).
- personalidad-instagram.md y validacion.js también los usa el asistente de Instagram (N8N/instagram/).
La primera vez que un prompt no tiene las marcas, se migra con reemplazos que deben calzar una sola vez.
La clave de n8n se lee de ~/.claude.json y nunca se imprime. Respaldos en ~/respaldos/n8n/cerebro-at/<fecha>/.
"""
import datetime
import io
import json
import os
import subprocess
import sys
import urllib.error
import urllib.request

AQUI = os.path.dirname(os.path.abspath(__file__))
URL = 'https://n8n-n8n.kchiba.easypanel.host'
WEB, WHATSAPP = '3A5Y1NPBxpTjhHdE', 'bBcNlFgBzQ0766Mq'
SETTINGS_OK = {'executionOrder', 'timezone', 'saveDataErrorExecution', 'saveDataSuccessExecution',
               'saveManualExecutions', 'saveExecutionProgress', 'executionTimeout', 'errorWorkflow', 'callerPolicy'}

REGLAS = ('### 🛡️ REGLAS AT (inicio; se sincroniza desde N8N/cerebro-at/reglas.md)', '### 🛡️ REGLAS AT (fin)')
CEREBRO = ('### 🧠 CEREBRO AT (inicio; se sincroniza desde N8N/cerebro-at/cerebro-at.md)', '### 🧠 CEREBRO AT (fin)')
PERS = {'web': ('### 🎭 PERSONALIDAD (inicio; se sincroniza desde N8N/cerebro-at/personalidad-web.md)', '### 🎭 PERSONALIDAD (fin)'),
        'whatsapp': ('### 🎭 PERSONALIDAD (inicio; se sincroniza desde N8N/cerebro-at/personalidad-whatsapp.md)', '### 🎭 PERSONALIDAD (fin)')}
VAL = ('// <<VALIDACION AT: inicio>>', '// <<VALIDACION AT: fin>>')


def leer(nombre):
    with io.open(os.path.join(AQUI, nombre), encoding='utf-8') as f:
        return f.read().replace('\r\n', '\n').strip()


def tramo_de(texto, marcas):
    i, j = texto.index(marcas[0]), texto.index(marcas[1]) + len(marcas[1])
    return texto[i:j]


def bloque(marcas, contenido):
    return marcas[0] + '\n\n' + contenido + '\n\n' + marcas[1]


class Texto:
    def __init__(self, t, donde):
        self.t, self.donde = t, donde

    def cambiar(self, viejo, nuevo):
        n = self.t.count(viejo)
        assert n == 1, f'{self.donde}: calza {n} veces: {viejo[:70]!r}'
        self.t = self.t.replace(viejo, nuevo)

    def tramo(self, desde, hasta, nuevo):
        assert self.t.count(desde) == 1 and self.t.count(hasta) == 1, f'{self.donde}: marcas {desde[:40]!r} / {hasta[:40]!r}'
        i, j = self.t.index(desde), self.t.index(hasta)
        assert i < j, f'{self.donde}: orden de {desde[:40]!r}'
        self.t = self.t[:i] + nuevo + self.t[j:]

    def poner(self, marcas, contenido):
        assert self.t.count(marcas[0]) == 1 and self.t.count(marcas[1]) == 1, f'{self.donde}: marcas de {marcas[0][:30]!r}'
        i, j = self.t.index(marcas[0]), self.t.index(marcas[1]) + len(marcas[1])
        self.t = self.t[:i] + bloque(marcas, contenido) + self.t[j:]


def armar_web(prompt, f):
    p = Texto(prompt, 'web')
    if REGLAS[0] not in p.t:  # migración única (28-oct-2026)
        p.tramo('### 🚫 RESTRICCIÓN DE TEMAS (MUY IMPORTANTE)', '### 🧠 TU IDENTIDAD Y PERSONALIDAD', bloque(REGLAS, f['reglas']) + '\n\n')
        p.tramo('### 🧠 TU IDENTIDAD Y PERSONALIDAD', CEREBRO[0], '')
        p.cambiar('### 💰 REGLAS CRÍTICAS DE PRECIOS', bloque(PERS['web'], f['web']) + '\n\n### 💰 REGLAS CRÍTICAS DE PRECIOS')
    p.poner(REGLAS, f['reglas'])
    p.poner(CEREBRO, f['cerebro'])
    p.poner(PERS['web'], f['web'])
    # La personalidad web debe quedar dentro del tramo que aplicar.py le quita a Instagram.
    a, b = p.t.index('### 📅 AGENDAMIENTO DE REUNIONES'), p.t.index('### 💰 REGLAS CRÍTICAS DE PRECIOS')
    assert a < p.t.index(PERS['web'][0]) < b, 'web: la personalidad quedó fuera del tramo web'
    return p.t


def simular_instagram(web):
    """Lo mismo que prompt_web_adaptado() de N8N/instagram/aplicar.py; si fallaría, se detiene antes de subir."""
    q = web[1:] if web.startswith('=') else web
    ini, fin = q.find('### 📅 AGENDAMIENTO DE REUNIONES'), q.find('### 💰 REGLAS CRÍTICAS DE PRECIOS')
    marcas = ['4.  Guiar al usuario para agendar citas mediante el formulario.',
              'Si piden hablar con un humano o datos de contacto, entrega SOLO estos:']
    assert ini >= 0 and fin > ini and all(q.count(m) == 1 for m in marcas), 'instagram: aplicar.py se detendría (marcas)'
    q = q[:ini] + q[fin:]
    for m in marcas:
        q = q.replace(m, '·')
    assert 'SHOW_DEMO_FORM' not in q and 'formulario' not in q, 'instagram: aplicar.py se detendría (formulario)'
    assert REGLAS[0] in q and CEREBRO[0] in q and PERS['web'][0] not in q, 'instagram: tramos equivocados'
    assert 'USD/mes' not in q, 'instagram: le llegarían los precios de los planes'
    return q


def armar_whatsapp(prompt, f):
    p = Texto(prompt, 'whatsapp')
    if REGLAS[0] not in p.t:  # migración única (28-oct-2026)
        p.tramo('TU IDENTIDAD Y PERSONALIDAD', '🔒 SEGURIDAD Y PRIVACIDAD (CRÍTICO - NUNCA VIOLAR)', bloque(PERS['whatsapp'], f['whatsapp']) + '\n\n')
        p.tramo('🔒 SEGURIDAD Y PRIVACIDAD (CRÍTICO - NUNCA VIOLAR)', '⚠️ MENSAJE DESDE LA WEB - REGLA ESPECIAL ⚠️', bloque(REGLAS, f['reglas']) + '\n\n')
    p.poner(PERS['whatsapp'], f['whatsapp'])
    p.poner(REGLAS, f['reglas'])
    p.poner(CEREBRO, f['cerebro'])
    # Demo y diagnóstico son lo mismo (Luis, 28-oct): hacia la persona se dice siempre «diagnóstico gratis».
    for viejo, nuevo in (
            ('pregunta: "¿Te gustaría agendar una demo para conocernos o ya eres cliente?"',
             'pregunta: "¿Te gustaría agendar tu diagnóstico gratis o ya eres cliente y quieres una reunión de seguimiento?"'),
            ('PREGUNTA: "¿Es una demo que agendaste o una reunión de seguimiento como cliente?"',
             'PREGUNTA: "¿Es tu diagnóstico gratis o una reunión de seguimiento como cliente?"')):
        if viejo in p.t:
            p.cambiar(viejo, nuevo)
    for accion in ('SHOW_CALENDAR', 'FOLLOWUP_MEETING', 'CANCEL_APPOINTMENT', 'RESCHEDULE_APPOINTMENT', 'RESCHEDULE_FOLLOWUP',
                   'ESCALATE_SUPPORT', 'SHOW_PLANS'):
        assert f'<<ACTION:{accion}>>' in p.t, f'whatsapp: se perdió la acción {accion}'
    return p.t


def armar_check_email(js, val):
    c = Texto(js.replace('\r\n', '\n'), 'Check Email Booking')
    if VAL[0] not in c.t:  # migración única (28-oct-2026)
        c.cambiar("""// Verificar si es un email válido
const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
const isEmail = emailRegex.test(textContent.trim());

// Verificar si es un nombre válido
const nameText = textContent.trim();
const isValidName = nameText.length >= 2 && /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\\s]+$/.test(nameText);""",
                  """// Nombre y correo con la validación común de AT (N8N/cerebro-at/validacion.js; se sincroniza, no editar aquí)
""" + VAL[0] + '\n' + VAL[1] + """
const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
const vCorreo = atValidarCorreo(textContent);
const isEmail = vCorreo.ok;
const vNombre = atValidarNombre(textContent);
const nameText = vNombre.ok ? vNombre.valor : textContent.trim();
const isValidName = vNombre.ok;""")
        c.cambiar("email: textContent.trim(),", "email: vCorreo.valor,")
        c.cambiar("status: 'INVALID_EMAIL',", "status: 'INVALID_EMAIL',\n        sugerencia: vCorreo.sugerencia || '',")
        c.cambiar("status: 'INVALID_NAME',", "status: 'INVALID_NAME',\n        motivo: vNombre.motivo,")
    i, j = c.t.index(VAL[0]), c.t.index(VAL[1]) + len(VAL[1])
    c.t = c.t[:i] + val + c.t[j:]
    return c.t


MENSAJES = [  # (nodo, texto viejo, texto nuevo): se aplican una sola vez
    ('Ask For Name', 'escribe tu nombre completo para la reserva:', 'escribe tu nombre y apellido para la reserva:'),
    ('Ask Name for Custom Date', 'escribe tu nombre completo para la reserva:', 'escribe tu nombre y apellido para la reserva:'),
    ('Ask Valid Name', 'Por favor, escribe tu nombre completo (mínimo 2 caracteres, solo letras):',
     'Por favor, escribe tu nombre y apellido, solo con letras (por ejemplo: María González):'),
    ('Remind Email', 'parece que el correo ingresado no es válido.',
     "parece que el correo ingresado no es válido.{{ $json.sugerencia ? ' ¿Quisiste decir ' + $json.sugerencia + '?' : '' }}"),
]


def main():
    subir = '--subir' in sys.argv
    ig = sys.argv[sys.argv.index('--instagram') + 1] if '--instagram' in sys.argv else ''
    key = json.load(io.open(os.path.expanduser('~/.claude.json'), encoding='utf-8'))['mcpServers']['n8n-mcp']['env']['N8N_API_KEY']

    def api(metodo, ruta, body=None):
        req = urllib.request.Request(URL + '/api/v1' + ruta, method=metodo, data=(json.dumps(body).encode() if body is not None else None))
        for k, v in (('X-N8N-API-KEY', key), ('Content-Type', 'application/json'), ('Accept', 'application/json')):
            req.add_header(k, v)
        try:
            with urllib.request.urlopen(req, timeout=60) as r:
                return r.status, json.loads(r.read().decode() or '{}')
        except urllib.error.HTTPError as e:
            return e.code, {'error': e.read().decode()[:300]}

    # Los planes con precio van solo en la personalidad web y en la de WhatsApp: Instagram nunca da precios
    # (Luis, 29-oct-2026) y la personalidad web queda en el tramo que aplicar.py le quita a Instagram.
    planes = leer('planes.md')
    f = {'reglas': leer('reglas.md'), 'cerebro': leer('cerebro-at.md'), 'web': leer('personalidad-web.md') + '\n\n' + planes,
         'whatsapp': leer('personalidad-whatsapp.md') + '\n\n' + planes}
    vjs = io.open(os.path.join(AQUI, 'validacion.js'), encoding='utf-8').read().replace('\r\n', '\n')
    val = vjs[vjs.index(VAL[0]):vjs.index(VAL[1]) + len(VAL[1])]

    vivos, cambios = {}, {}
    for nombre, wid in (('web', WEB), ('whatsapp', WHATSAPP)):
        c, w = api('GET', '/workflows/' + wid)
        assert c == 200, f'{nombre}: GET {c}'
        vivos[nombre] = w
    nw = {n['name']: n for n in vivos['web']['nodes']}
    na = {n['name']: n for n in vivos['whatsapp']['nodes']}
    web_nuevo = armar_web(nw['Agente IA - Tech']['parameters']['options']['systemMessage'], f)
    ig_simulado = simular_instagram(web_nuevo)
    cambios['web'] = [('Agente IA - Tech', ('options', 'systemMessage'), web_nuevo)]
    cambios['whatsapp'] = [
        ('Agente IA - Tech WhatsApp', ('options', 'systemMessage'),
         armar_whatsapp(na['Agente IA - Tech WhatsApp']['parameters']['options']['systemMessage'], f)),
        ('Check Email Booking', ('jsCode',), armar_check_email(na['Check Email Booking']['parameters']['jsCode'], val)),
    ]
    for nodo, viejo, nuevo in MENSAJES:
        cuerpo = na[nodo]['parameters']['jsonBody']
        if nuevo in cuerpo:
            continue
        assert cuerpo.count(viejo) == 1, f'{nodo}: el texto viejo calza {cuerpo.count(viejo)} veces'
        cambios['whatsapp'].append((nodo, ('jsonBody',), cuerpo.replace(viejo, nuevo)))

    def get(p, ruta):
        for k in ruta:
            p = p[k]
        return p

    def put(p, ruta, v):
        for k in ruta[:-1]:
            p = p[k]
        p[ruta[-1]] = v

    nodos = {'web': nw, 'whatsapp': na}
    hora = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
    vista = os.path.join(os.path.expanduser('~/respaldos/n8n/cerebro-at'), hora)
    os.makedirs(vista, exist_ok=True)
    io.open(os.path.join(vista, 'instagram-simulado.md'), 'w', encoding='utf-8').write(ig_simulado)
    pendientes = {}
    for flujo, lista in cambios.items():
        reales = [(n, r, v) for n, r, v in lista if get(nodos[flujo][n]['parameters'], r).replace('\r\n', '\n') != v]
        pendientes[flujo] = reales
        for n, r, v in lista:
            io.open(os.path.join(vista, f'{flujo}--{n}.nuevo.txt'), 'w', encoding='utf-8').write(v)
        print(f'{flujo}: {len(reales)} nodo(s) cambian: {[n for n, _, _ in reales]}')
    print('vistas previas en', vista)
    if not subir:
        print('modo vista previa: no se subió nada (usar --subir)')
        return

    for flujo, wid in (('web', WEB), ('whatsapp', WHATSAPP)):
        if not pendientes[flujo]:
            continue
        w = vivos[flujo]
        json.dump(w, io.open(os.path.join(vista, f'{flujo}-{wid}.antes.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        for n, r, v in pendientes[flujo]:
            put(nodos[flujo][n]['parameters'], r, v)
        cuerpo = {'name': w['name'], 'nodes': w['nodes'], 'connections': w['connections'],
                  'settings': {k: v for k, v in (w.get('settings') or {}).items() if k in SETTINGS_OK}}
        c, r = api('PUT', '/workflows/' + wid, cuerpo)
        assert c == 200, f'{flujo}: PUT {c} {r}; el flujo quedó como estaba (respaldo en {vista})'
        if w.get('active'):
            c2, _ = api('POST', f'/workflows/{wid}/activate')
            print(f'{flujo}: subido y republicado ({c2})')
        c3, w3 = api('GET', '/workflows/' + wid)
        n3 = {n['name']: n for n in w3['nodes']}
        ok = all(get(n3[n]['parameters'], r) == v for n, r, v in pendientes[flujo])
        print(f'{flujo}: releído activo={w3.get("active")} textos_nuevos={ok}')
        assert ok, f'{flujo}: lo releído no coincide'
    if ig:
        print('instagram: corriendo', os.path.basename(ig))
        subprocess.run([sys.executable, '-I', '-X', 'utf8', ig], check=True, cwd=os.path.dirname(os.path.abspath(ig)))
    else:
        print('instagram: falta copiar el prompt web; correr aplicar.py desde la punta de claude/asistente-ig-directos')


if __name__ == '__main__':
    main()
