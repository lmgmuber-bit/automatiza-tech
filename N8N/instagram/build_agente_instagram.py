"""Genera los dos flujos del asistente de mensajes directos de Instagram, INACTIVOS y saneados (sin red, sin secretos).

- «Agente Tech Instagram (PROD)»: lo llama el Portal OmniCliente (forward_to_n8n) con cada mensaje directo del canal 2.
  Valida, junta las ráfagas (6 s, Redis), arma la entrada, corre el agente (prompt del agente web + bloque «Canal
  Instagram», gpt-4o-mini, memoria Redis ig:<IGSID>) y responde por callback_url. Si el agente usó derivar_a_persona,
  manda un único callback con action=escalate, motivo y la despedida en content (spec, ajuste 4 del 24-oct).
- «Agente Tech Instagram · Herramientas»: sub-workflow de las herramientas ver_disponibilidad y agendar. agendar llama a
  «Agendar Reunión AutomatizaTech - Cliente (PROD)» (PejBgr2LZQSAbbBy), que no se modifica.

Marcadores que reemplaza aplicar.py (no van al repo, que es público):
  RUTA_WEBHOOK       ruta del webhook (se genera una vez y se guarda fuera del repo)
  ID_HERRAMIENTAS    id del sub-workflow, una vez creado
  PROMPT_AGENTE_WEB  prompt del nodo «Agente IA - Tech» del agente web, leído en vivo y adaptado a Instagram

Modo prueba (body.prueba = true, solo desde probar.py): no llama al Portal y agendar no crea la cita.
Credenciales: solo id y nombre.
"""
import json
import os

AQUI = os.path.dirname(os.path.abspath(__file__))
CANAL_IG = 2
CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}
CRED_REDIS = {'redis': {'id': 'fgxjc2NeBOcUCA3v', 'name': 'Redis32'}}
WP = 'https://automatizatech.cl/wp-json/automatiza-tech/v1'
AGENDAR_PROD = 'PejBgr2LZQSAbbBy'
ZONA = 'America/Santiago'


def nodo(nid, nombre, tipo, version, pos, params, **extra):
    n = {'id': nid, 'name': nombre, 'type': tipo, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def code(nid, nombre, pos, js):
    return nodo(nid, nombre, 'n8n-nodes-base.code', 2, pos, {'jsCode': js.strip() + '\n'})


def si_booleano(nid, nombre, pos, expr):
    return nodo(nid, nombre, 'n8n-nodes-base.if', 1, pos,
                {'conditions': {'boolean': [{'value1': '={{ ' + expr + ' }}', 'value2': True}]}})


def redis(nid, nombre, pos, params):
    return nodo(nid, nombre, 'n8n-nodes-base.redis', 1, pos, params, credentials=CRED_REDIS)


def conectar(*pares):
    c = {}
    for origen, destino, salida in pares:
        c.setdefault(origen, {'main': []})
        while len(c[origen]['main']) <= salida:
            c[origen]['main'].append([])
        c[origen]['main'][salida].append({'node': destino, 'type': 'main', 'index': 0})
    return c


# ---------------------------------------------------------------- flujo principal

JS_VALIDAR = r"""
// Solo avisos del Portal para el canal de Instagram, con los datos completos. Si no, termina sin responder.
const b = $json.body || {};
const contacto = b.contact || {};
const m = b.message || {};
const prueba = b.prueba === true;
const conv = Number(b.conversation_id) || 0;
const canal = Number(b.channel_id) || 0;
const igsid = String(contacto.external_id || '');
const tok = String(b.callback_token || '');
const cb = String(b.callback_url || '');
if (b.event !== 'new_message' || canal !== CANAL || !conv) return [];
if (!/^[0-9A-Za-z_-]{3,64}$/.test(igsid) || !/^[0-9a-f]{64}$/.test(tok)) return [];
if (!prueba && !cb.startsWith('https://automatizatech.cl/')) return [];
const texto = String(m.content || '').trim().slice(0, 2000);
const tipo = String(m.type || 'text');
const ctx = (b.contexto && typeof b.contexto === 'object') ? b.contexto : null;
return [{ json: {
  conversation_id: conv, channel_id: canal, igsid, nombre: String(contacto.name || '').slice(0, 80),
  callback_url: cb, callback_token: tok, mensaje_id: String(m.id || Date.now()), texto,
  es_texto: tipo === 'text' && texto !== '', prueba,
  contexto: ctx ? { palabra: String(ctx.palabra || '').slice(0, 40), mensaje_privado: String(ctx.mensaje_privado || '').slice(0, 1000) } : null,
} }];
""".replace('CANAL', str(CANAL_IG))

JS_UNIR = r"""
// Junta los mensajes de la ráfaga (los de los últimos 2 minutos). Si la lista llegó vacía, usa el propio mensaje.
const v = $('Validar').first().json;
const lista = $json.rafaga;
const ahora = Date.now();
let textos = [];
for (const s of (Array.isArray(lista) ? lista : (lista ? [lista] : []))) {
  try { const x = JSON.parse(s); if (x && x.t && ahora - Number(x.ts || 0) < 120000) textos.push(String(x.t)); } catch (e) {}
}
if (!textos.length) textos = [v.texto];
return [{ json: { texto: textos.join('\n').slice(0, 3000), mensajes: textos.length } }];
"""

JS_ENTRADA = r"""
// Entrada del agente: el texto, el tipo de cambio (el prompt web usa $json.rate), la fecha de hoy en Chile y,
// si la persona llegó desde un comentario, qué comentó y qué le enviamos por privado.
const v = $('Validar').first().json;
const texto = $('Unir textos').first().json.texto;
// Si el tipo de cambio no llegó (WordPress tardó o falló, 29-oct), rate = null y el agente da los precios solo en USD.
const rate = Number(($json || {}).rate) > 0 ? Number($json.rate) : null;
const ahora = DateTime.now().setZone('ZONA').setLocale('es');
const dias = [];
for (let i = 0; i < 8; i++) {
  const d = ahora.plus({ days: i });
  dias.push((i === 0 ? 'hoy ' : i === 1 ? 'mañana ' : '') + d.toFormat("cccc d 'de' LLLL") + ' (' + d.toISODate() + ')');
}
let ctx = '';
if (v.contexto && v.contexto.palabra) {
  ctx = '\n\n#### De dónde viene esta persona\nComentó «' + v.contexto.palabra + '» en una publicación de @automatizatech.cl'
    + (v.contexto.mensaje_privado ? ' y le enviamos este mensaje privado: «' + v.contexto.mensaje_privado + '».' : '.')
    + ' Lo que escribe ahora es la respuesta a eso: si dice algo como «quiero agendar», «sí» o «me interesa», ofrécele agendar'
    + ' el diagnóstico gratis aquí mismo. No le repitas el mensaje que ya le enviamos.';
}
if (rate === null) {
  ctx += '\n\n#### Precios sin tipo de cambio\nHoy no tenemos el tipo de cambio: da los precios solo en USD, sin calcular pesos chilenos'
    + ' (nunca digas «$0 CLP»), y di que el valor en pesos lo confirmamos en el diagnóstico gratis.';
}
return [{ json: {
  chatInput: texto, rate, igsid: v.igsid, prueba: v.prueba,
  ahora_txt: ahora.toFormat("cccc d 'de' LLLL 'de' yyyy, HH:mm"), dias_txt: dias.join('; '), contexto_txt: ctx,
} }];
""".replace('ZONA', ZONA)

JS_RESPUESTA = r"""
// Texto plano para Instagram (máximo 1000 caracteres) y, si el agente usó derivar_a_persona, la derivación.
const v = $('Validar').first().json;
let txt = String($json.output || '');
txt = txt.replace(/<<[A-Z_:]+>>/g, '').replace(/\*\*|__|`/g, '').replace(/^#+\s*/gm, '').replace(/\n{3,}/g, '\n\n').trim();
if (txt.length > 990) {
  const corte = txt.slice(0, 990);
  const p = Math.max(corte.lastIndexOf('. '), corte.lastIndexOf('\n'));
  txt = (p > 500 ? corte.slice(0, p + 1) : corte).trim();
}
let motivo = null;
for (const s of ($json.intermediateSteps || [])) {
  const a = s && s.action;
  if (a && a.tool === 'derivar_a_persona') {
    const e = a.toolInput;
    motivo = typeof e === 'string' ? e : String((e && (e.motivo || e.input)) || '');
  }
}
const body = { conversation_id: v.conversation_id, channel_id: v.channel_id, callback_token: v.callback_token };
if (motivo !== null) {
  body.action = 'escalate';
  body.motivo = (motivo || 'Pidió hablar con una persona').slice(0, 300);
  body.content = txt || 'Le avisé a Luis, te va a responder por aquí mismo.';
} else {
  if (!txt) return [];
  body.content = txt;
}
const usadas = ($json.intermediateSteps || []).map(s => s && s.action && s.action.tool).filter(Boolean);
return [{ json: { callback_url: v.callback_url, prueba: v.prueba, body, herramientas: usadas } }];
"""

JS_NO_TEXTO = r"""
const v = $('Validar').first().json;
return [{ json: { callback_url: v.callback_url, prueba: v.prueba, herramientas: [], body: {
  conversation_id: v.conversation_id, channel_id: v.channel_id, callback_token: v.callback_token,
  content: 'Por ahora leo solo texto: ¿me lo escribes? 🙂' } } }];
"""


# Fuente única de Eje (personalidad y validación), 28-oct. AT_CEREBRO_DIR apunta a otra copia (p. ej. la rama de Eje).
CEREBRO = os.environ.get('AT_CEREBRO_DIR') or os.path.join(AQUI, '..', 'cerebro-at')


def leer(*partes):
    with open(os.path.join(*partes), encoding='utf-8') as f:
        return f.read()


def prompt_instagram():
    """Bloque del canal con la personalidad común de Tech Instagram (N8N/cerebro-at/personalidad-instagram.md)."""
    bloque = leer(AQUI, 'prompt-canal-instagram.md').strip()
    personalidad = leer(CEREBRO, 'personalidad-instagram.md').strip()
    if bloque.count('{{PERSONALIDAD_INSTAGRAM}}') != 1 or not personalidad.startswith('### '):
        raise SystemExit('falta la marca {{PERSONALIDAD_INSTAGRAM}} o la personalidad de Instagram del Cerebro AT')
    return bloque.replace('{{PERSONALIDAD_INSTAGRAM}}', personalidad)


def validacion_at():
    """Tramo marcado de N8N/cerebro-at/validacion.js: atValidarNombre, atValidarCorreo y atValidarWhatsApp."""
    js = leer(CEREBRO, 'validacion.js')
    ini, fin = '// <<VALIDACION AT: inicio>>', '// <<VALIDACION AT: fin>>'
    if js.count(ini) != 1 or js.count(fin) != 1:
        raise SystemExit('validacion.js del Cerebro AT no trae las marcas VALIDACION AT')
    tramo = js[js.index(ini):js.index(fin) + len(fin)]
    for f in ('atValidarNombre', 'atValidarCorreo', 'atValidarWhatsApp'):
        if 'function ' + f not in tramo:
            raise SystemExit('validacion.js no trae ' + f)
    return tramo


def flujo_principal():
    k = "$('Validar').first().json.igsid"
    tool_inputs = lambda campos: {  # noqa: E731
        'mappingMode': 'defineBelow',
        'value': campos,
        'matchingColumns': [],
        'schema': [{'id': c, 'displayName': c, 'required': False, 'defaultMatch': False, 'display': True,
                    'canBeUsedToMatch': True, 'type': 'string', 'removed': False} for c in CAMPOS_HERRAMIENTAS],
        'attemptToConvertTypes': False,
        'convertFieldsToString': False,
    }
    fijos = {'igsid': "={{ $('Preparar entrada').first().json.igsid }}",
             'prueba': "={{ $('Preparar entrada').first().json.prueba ? 'si' : 'no' }}"}
    vacios = {c: '' for c in CAMPOS_HERRAMIENTAS}
    disp = dict(vacios, **fijos, accion='disponibilidad',
                fecha="={{ $fromAI('fecha', 'Día que pide la persona en formato AAAA-MM-DD, o vacío para los próximos días disponibles', 'string') }}")
    agen = dict(vacios, **fijos, accion='agendar',
                nombre="={{ $fromAI('nombre', 'Nombre y apellido de la persona (los dos obligatorios)', 'string') }}",
                correo="={{ $fromAI('correo', 'Correo de la persona, donde le llega la invitación', 'string') }}",
                telefono="={{ $fromAI('telefono', 'Celular de WhatsApp con código de país (obligatorio), ej. +56912345678', 'string') }}",
                fecha="={{ $fromAI('fecha', 'Fecha confirmada, formato AAAA-MM-DD', 'string') }}",
                hora="={{ $fromAI('hora', 'Hora confirmada, formato HH:MM de 24 horas', 'string') }}")
    sistema = '=PROMPT_AGENTE_WEB\n\n' + prompt_instagram() + '{{ $json.contexto_txt }}'

    nodes = [
        nodo('ig-webhook', 'Webhook', 'n8n-nodes-base.webhook', 1, [0, 300],
             {'httpMethod': 'POST', 'path': 'RUTA_WEBHOOK', 'options': {}}, webhookId='RUTA_WEBHOOK'),
        code('ig-validar', 'Validar', [220, 300], JS_VALIDAR),
        si_booleano('ig-es-texto', '¿Es texto?', [440, 300], '$json.es_texto'),
        # Ráfaga: cada mensaje se anota; espera 6 s y solo sigue la ejecución del último.
        redis('ig-push', 'Anotar en la ráfaga', [660, 200],
              {'operation': 'push', 'list': '=ig:rafaga:{{ ' + k + ' }}', 'tail': True,
               'messageData': "={{ JSON.stringify({ t: $('Validar').first().json.texto, ts: Date.now() }) }}"}),
        redis('ig-ultimo', 'Marcar el último', [880, 200],
              {'operation': 'set', 'key': '=ig:ultimo:{{ ' + k + ' }}', 'keyType': 'string', 'expire': True, 'ttl': 120,
               'value': "={{ $('Validar').first().json.mensaje_id }}"}),
        nodo('ig-esperar', 'Esperar 6 s', 'n8n-nodes-base.wait', 1, [1100, 200], {'amount': 6, 'unit': 'seconds'},
             webhookId='ig-esperar-6s'),
        redis('ig-leer-ultimo', 'Leer el último', [1320, 200],
              {'operation': 'get', 'propertyName': 'ultimo', 'key': '=ig:ultimo:{{ ' + k + ' }}', 'keyType': 'string', 'options': {}}),
        si_booleano('ig-soy-ultimo', '¿Es el último?', [1540, 200], "String($json.ultimo) === $('Validar').first().json.mensaje_id"),
        redis('ig-leer-rafaga', 'Leer la ráfaga', [1760, 120],
              {'operation': 'get', 'propertyName': 'rafaga', 'key': '=ig:rafaga:{{ ' + k + ' }}', 'keyType': 'list', 'options': {}}),
        redis('ig-vaciar', 'Vaciar la ráfaga', [1980, 120], {'operation': 'delete', 'key': '=ig:rafaga:{{ ' + k + ' }}'}),
        code('ig-unir', 'Unir textos', [2200, 120], JS_UNIR),
        # El endpoint consulta al Banco Central en vivo y a veces pasa los 10 s (29-oct): más espera y reintentos.
        nodo('ig-cambio', 'Tipo de cambio', 'n8n-nodes-base.httpRequest', 4.1, [2420, 120],
             {'url': WP + '/exchange-rate', 'options': {'timeout': 20000}}, onError='continueRegularOutput',
             retryOnFail=True, maxTries=3, waitBetweenTries=1000),
        code('ig-entrada', 'Preparar entrada', [2640, 120], JS_ENTRADA),
        nodo('ig-agente', 'Agente IA - Tech Instagram', '@n8n/n8n-nodes-langchain.agent', 1.8, [2880, 120],
             {'promptType': 'define', 'text': '={{ $json.chatInput }}',
              'options': {'systemMessage': sistema, 'maxIterations': 8, 'returnIntermediateSteps': True}}),
        nodo('ig-cerebro', 'Cerebro', '@n8n/n8n-nodes-langchain.lmChatOpenAi', 1.2, [2780, 360],
             {'model': {'__rl': True, 'value': 'gpt-4o-mini', 'mode': 'list', 'cachedResultName': 'gpt-4o-mini'}, 'options': {}},
             credentials=CRED_OPENAI),
        nodo('ig-memoria', 'Memoria Instagram', '@n8n/n8n-nodes-langchain.memoryRedisChat', 1.5, [2920, 360],
             {'sessionIdType': 'customKey', 'sessionKey': "=ig:{{ $('Preparar entrada').first().json.igsid }}",
              'sessionTTL': 2592000, 'contextWindowLength': 30}, credentials=CRED_REDIS),
        nodo('ig-tool-disp', 'ver_disponibilidad', '@n8n/n8n-nodes-langchain.toolWorkflow', 2.2, [3060, 360],
             {'description': 'Consulta las horas libres para el diagnóstico gratis (hora de Chile, reuniones de 1 hora). '
                             'Pasa una fecha AAAA-MM-DD, o vacío para ver los próximos días disponibles.',
              'workflowId': {'__rl': True, 'value': 'ID_HERRAMIENTAS', 'mode': 'id'},
              'workflowInputs': tool_inputs(disp)}),
        nodo('ig-tool-agendar', 'agendar', '@n8n/n8n-nodes-langchain.toolWorkflow', 2.2, [3200, 360],
             {'description': 'Agenda el diagnóstico gratis por Google Meet. Úsala solo después de consultar la '
                             'disponibilidad y de que la persona confirme día, hora y correo.',
              'workflowId': {'__rl': True, 'value': 'ID_HERRAMIENTAS', 'mode': 'id'},
              'workflowInputs': tool_inputs(agen)}),
        nodo('ig-tool-derivar', 'derivar_a_persona', '@n8n/n8n-nodes-langchain.toolCode', 1.3, [3340, 360],
             {'description': 'Deriva la conversación a una persona del equipo (Luis), que responde por este mismo chat. '
                             'Úsala cuando pidan hablar con una persona, tengan un reclamo, sean clientes pidiendo '
                             'seguimiento o pidan algo que no puedes resolver.',
              'language': 'javaScript',
              'jsCode': "return 'Derivación registrada. Despídete en una frase: Luis le va a responder por este mismo chat.';\n",
              'specifyInputSchema': True, 'schemaType': 'manual',
              'inputSchema': json.dumps({'type': 'object', 'properties': {'motivo': {
                  'type': 'string', 'description': 'Qué necesita la persona, en una frase'}}, 'required': ['motivo']},
                  ensure_ascii=False, indent=1)}),
        code('ig-respuesta', 'Preparar respuesta', [3120, 120], JS_RESPUESTA),
        # No texto: un aviso fijo, una sola vez por persona cada 10 minutos.
        redis('ig-ya-aviso', '¿Ya avisamos?', [660, 460],
              {'operation': 'get', 'propertyName': 'aviso', 'key': '=ig:notexto:{{ ' + k + ' }}', 'keyType': 'string', 'options': {}}),
        si_booleano('ig-sin-aviso', '¿Sin aviso reciente?', [880, 460], '!$json.aviso'),
        redis('ig-recordar', 'Recordar el aviso', [1100, 460],
              {'operation': 'set', 'key': '=ig:notexto:{{ ' + k + ' }}', 'keyType': 'string', 'value': '1', 'expire': True, 'ttl': 600}),
        code('ig-no-texto', 'Respuesta no texto', [1320, 460], JS_NO_TEXTO),
        si_booleano('ig-es-prueba', '¿Es prueba?', [3340, 120], '$json.prueba'),
        nodo('ig-callback', 'Responder al Portal', 'n8n-nodes-base.httpRequest', 4.1, [3560, 200],
             {'method': 'POST', 'url': '={{ $json.callback_url }}', 'sendBody': True, 'specifyBody': 'json',
              'jsonBody': '={{ JSON.stringify($json.body) }}', 'options': {'timeout': 20000}}),
    ]
    con = conectar(
        ('Webhook', 'Validar', 0), ('Validar', '¿Es texto?', 0),
        ('¿Es texto?', 'Anotar en la ráfaga', 0), ('¿Es texto?', '¿Ya avisamos?', 1),
        ('Anotar en la ráfaga', 'Marcar el último', 0), ('Marcar el último', 'Esperar 6 s', 0),
        ('Esperar 6 s', 'Leer el último', 0), ('Leer el último', '¿Es el último?', 0),
        ('¿Es el último?', 'Leer la ráfaga', 0), ('Leer la ráfaga', 'Vaciar la ráfaga', 0),
        ('Vaciar la ráfaga', 'Unir textos', 0), ('Unir textos', 'Tipo de cambio', 0),
        ('Tipo de cambio', 'Preparar entrada', 0), ('Preparar entrada', 'Agente IA - Tech Instagram', 0),
        ('Agente IA - Tech Instagram', 'Preparar respuesta', 0), ('Preparar respuesta', '¿Es prueba?', 0),
        ('¿Es prueba?', 'Responder al Portal', 1),
        ('¿Ya avisamos?', '¿Sin aviso reciente?', 0), ('¿Sin aviso reciente?', 'Recordar el aviso', 0),
        ('Recordar el aviso', 'Respuesta no texto', 0), ('Respuesta no texto', '¿Es prueba?', 0),
    )
    con['Cerebro'] = {'ai_languageModel': [[{'node': 'Agente IA - Tech Instagram', 'type': 'ai_languageModel', 'index': 0}]]}
    con['Memoria Instagram'] = {'ai_memory': [[{'node': 'Agente IA - Tech Instagram', 'type': 'ai_memory', 'index': 0}]]}
    for t in ('ver_disponibilidad', 'agendar', 'derivar_a_persona'):
        con[t] = {'ai_tool': [[{'node': 'Agente IA - Tech Instagram', 'type': 'ai_tool', 'index': 0}]]}
    return {'name': 'Agente Tech Instagram (PROD)', 'nodes': nodes, 'connections': con,
            'settings': {'executionOrder': 'v1', 'timezone': ZONA}}


# ---------------------------------------------------------------- herramientas

CAMPOS_HERRAMIENTAS = ['accion', 'fecha', 'hora', 'nombre', 'correo', 'telefono', 'igsid', 'prueba']

JS_FECHAS = r"""
// Días a revisar según la agenda real (appointments-config): el pedido, o los próximos 4 hábiles para la agenda.
const cfg = ($json && $json.data) || {};
const t = $('Entrada').first().json;
const feriados = new Set(cfg.holidays || []);
const semana = cfg.weekSchedule || {};
const nombres = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
const hoy = DateTime.now().setZone('ZONA').startOf('day');
const abre = d => { const s = semana[nombres[d.weekday % 7]]; return s && s.enabled ? s : null; };
const fin = msg => [{ json: { fin: true, result: msg } }];
const pedida = String(t.fecha || '').trim();
if (pedida) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(pedida)) return fin('Fecha inválida: usa el formato AAAA-MM-DD.');
  const d = DateTime.fromISO(pedida, { zone: 'ZONA' });
  if (!d.isValid) return fin('Fecha inválida.');
  if (d < hoy) return fin('Esa fecha ya pasó. Ofrece los próximos días disponibles.');
  if (d > hoy.plus({ days: 60 })) return fin('Solo agendamos hasta 60 días adelante.');
  if (feriados.has(pedida)) return fin('El ' + pedida + ' es feriado: no hay reuniones ese día. Ofrece otro día.');
  const s = abre(d);
  if (!s) return fin('Ese día no atendemos. Ofrece otro día.');
  return [{ json: { fin: false, date: pedida, start: s.start, end: s.end } }];
}
const out = [];
for (let i = 0; i < 21 && out.length < 4; i++) {
  const d = hoy.plus({ days: i });
  const iso = d.toISODate();
  const s = abre(d);
  if (s && !feriados.has(iso)) out.push({ json: { fin: false, date: iso, start: s.start, end: s.end } });
}
return out.length ? out : fin('No encontramos días disponibles en las próximas semanas.');
""".replace('ZONA', ZONA)

JS_HORAS = r"""
// Horas libres por día (en punto, con al menos 2 horas de anticipación). Hasta 3 días y 8 horas por día.
const fechas = $('Fechas a revisar').all().map(i => i.json);
const res = $input.all().map(i => i.json);
const ahora = DateTime.now().setZone('ZONA');
const lineas = [];
res.forEach((r, i) => {
  const f = fechas[i];
  if (!f || lineas.length >= 3) return;
  const etiqueta = DateTime.fromISO(f.date, { zone: 'ZONA' }).setLocale('es').toFormat("cccc d 'de' LLLL") + ' (' + f.date + ')';
  const ini = parseInt(((r.workingHours || {}).start) || f.start, 10);
  const fin = parseInt(((r.workingHours || {}).end) || f.end, 10);
  const ocupadas = new Set((r.busySlots || []).map(x => String(x).slice(0, 5)));
  const libres = [];
  if (!r.isFullDay) {
    for (let h = ini; h < fin; h++) {
      const hh = String(h).padStart(2, '0') + ':00';
      if (ocupadas.has(hh)) continue;
      if (DateTime.fromISO(f.date + 'T' + hh, { zone: 'ZONA' }) < ahora.plus({ hours: 2 })) continue;
      libres.push(hh);
    }
  }
  if (libres.length) lineas.push('- ' + etiqueta + ': ' + libres.slice(0, 8).join(', '));
  else if (fechas.length === 1) lineas.push('- ' + etiqueta + ': sin horas libres. Ofrece otro día.');
});
const txt = lineas.length ? 'Horas libres (hora de Chile, reuniones de 1 hora):\n' + lineas.join('\n')
  : 'No hay horas libres en los próximos días. Ofrece derivar a una persona.';
return [{ json: { result: txt } }];
""".replace('ZONA', ZONA)

JS_VALIDAR_CITA = r"""
// Revisa la cita antes de agendar. El sub-workflow de PROD vuelve a revisar el horario en WordPress y en Calendar.
// Nombre, correo y WhatsApp con la validación común del Cerebro AT (N8N/cerebro-at/validacion.js, la mantiene Eje).
VALIDACION_AT
const t = $('Entrada').first().json;
const fecha = String(t.fecha || '').trim();
const hora = String(t.hora || '').trim().slice(0, 5);
const malo = msg => [{ json: { ok: false, result: msg + ' No agendes hasta tenerlo bien.' } }];
// Se juntan todos los datos malos para que el agente los pida en un solo mensaje.
const errores = [];
const vn = atValidarNombre(t.nombre);
if (!vn.ok) errores.push(vn.motivo === 'vacio' || vn.motivo === 'falta_apellido'
  ? 'Falta el nombre y apellido de la persona: pídeselo (si dio solo el nombre, pide el apellido).'
  : 'Eso no parece un nombre y apellido: pídeselo de nuevo.');
const vc = atValidarCorreo(t.correo);
if (!vc.ok) errores.push(vc.motivo === 'tipeo'
  ? 'El correo parece mal escrito: pregúntale si es ' + vc.sugerencia + '.'
  : 'El correo no es válido: pídeselo de nuevo.');
// Obligatorio desde el 28-oct (Luis): sin WhatsApp con código de país no se agenda.
const vw = atValidarWhatsApp(t.telefono);
if (!vw.ok) errores.push(vw.motivo === 'chile'
  ? 'El celular de Chile tiene que ser +56 9 y ocho dígitos (por ejemplo +56 9 1234 5678): pídeselo de nuevo.'
  : 'Falta el WhatsApp con código de país (es obligatorio): pídeselo con el ejemplo «en Chile: +56 9 1234 5678».');
if (errores.length) return malo((errores.length > 1 ? 'Pídele todo esto en un solo mensaje: ' : '') + errores.join(' '));
const nombre = vn.valor, correo = vc.valor, tel = vw.valor;
if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha) || !/^\d{2}:00$/.test(hora)) return malo('Fecha u hora inválida: fecha AAAA-MM-DD y hora en punto (HH:00).');
const dt = DateTime.fromISO(fecha + 'T' + hora, { zone: 'ZONA' });
if (!dt.isValid) return malo('Fecha u hora inválida.');
if (dt < DateTime.now().setZone('ZONA').plus({ hours: 2 })) return malo('Esa hora ya pasó o es muy pronto: ofrece otra con ver_disponibilidad.');
return [{ json: { ok: true, prueba: t.prueba === 'si', date: fecha, time: hora, email: correo, name: nombre, phone: tel,
  session_id: 'ig:' + String(t.igsid || ''), appointmentType: 'Demo' } }];
""".replace('ZONA', ZONA)


JS_REVISAR_HORA = r"""
// Guarda firme (28-oct): la herramienta revisa ella misma la agenda real del día antes de agendar (feriado, día sin
// atención, horario y horas tomadas). Así no depende de que el modelo consulte antes ni hace confirmar dos veces.
// $json = respuesta de POST /check-availability {date}. El sub-workflow de PROD vuelve a revisar choques en WordPress y Calendar.
const cita = $('Validar cita').first().json;
const r = $json || {};
const wh = r.workingHours || {};
const ini = parseInt(wh.start, 10), fin = parseInt(wh.end, 10);
const ocupadas = new Set((r.busySlots || []).map(x => String(x).slice(0, 5)));
const libres = [];
if (!r.isFullDay && !isNaN(ini) && !isNaN(fin)) {
  for (let h = ini; h < fin; h++) { const hh = String(h).padStart(2, '0') + ':00'; if (!ocupadas.has(hh)) libres.push(hh); }
}
if (libres.includes(cita.time)) return [{ json: cita }];
const motivo = r.reason === 'Holiday' ? 'es feriado'
  : (r.isFullDay && r.reason === 'Day disabled') || isNaN(ini) ? 'no atendemos ese día'
  : ocupadas.has(cita.time) ? 'las ' + cita.time + ' ya está tomada'
  : 'las ' + cita.time + ' está fuera del horario de atención';
return [{ json: { ok: false, result: 'No se agendó: el ' + cita.date + ' ' + motivo + '. '
  + (libres.length ? 'Horas libres ese día: ' + libres.join(', ') + '. Ofrécele una de esas y confírmala con la persona.'
                   : 'Ofrécele otro día consultando ver_disponibilidad.') } }];
"""


def flujo_herramientas():
    nodes = [
        nodo('igh-entrada', 'Entrada', 'n8n-nodes-base.executeWorkflowTrigger', 1.1, [0, 300],
             {'inputSource': 'workflowInputs', 'workflowInputs': {'values': [{'name': c} for c in CAMPOS_HERRAMIENTAS]}}),
        nodo('igh-switch', '¿Qué herramienta?', 'n8n-nodes-base.switch', 1, [220, 300],
             {'dataType': 'string', 'value1': '={{ $json.accion }}',
              'rules': {'rules': [{'value2': 'disponibilidad'}, {'value2': 'agendar', 'output': 1}]}}),
        nodo('igh-config', 'Agenda', 'n8n-nodes-base.httpRequest', 4.1, [440, 200],
             {'url': WP + '/appointments-config', 'options': {'timeout': 15000}}),
        code('igh-fechas', 'Fechas a revisar', [660, 200], JS_FECHAS),
        si_booleano('igh-fin', '¿Ya hay respuesta?', [880, 200], '$json.fin'),
        nodo('igh-check', 'Horas ocupadas', 'n8n-nodes-base.httpRequest', 4.1, [1100, 260],
             {'method': 'POST', 'url': WP + '/check-availability', 'sendBody': True, 'specifyBody': 'json',
              'jsonBody': '={{ JSON.stringify({ date: $json.date }) }}', 'options': {'timeout': 15000}}),
        code('igh-horas', 'Horas libres', [1320, 260], JS_HORAS),
        code('igh-validar', 'Validar cita', [440, 420], JS_VALIDAR_CITA.replace('VALIDACION_AT', validacion_at())),
        si_booleano('igh-ok', '¿Cita válida?', [660, 420], '$json.ok'),
        nodo('igh-check-cita', 'Agenda del día', 'n8n-nodes-base.httpRequest', 4.1, [880, 420],
             {'method': 'POST', 'url': WP + '/check-availability', 'sendBody': True, 'specifyBody': 'json',
              'jsonBody': '={{ JSON.stringify({ date: $json.date }) }}', 'options': {'timeout': 15000}}),
        code('igh-revisar', 'Revisar hora', [1100, 420], JS_REVISAR_HORA),
        si_booleano('igh-hora-libre', '¿Hora libre?', [1320, 420], '$json.ok'),
        si_booleano('igh-prueba', '¿Es prueba?', [1540, 400], '$json.prueba'),
        # Igual al mensaje de éxito del sub-workflow de PROD («Set Success Message»), para que el agente se comporte igual.
        code('igh-simulada', 'Cita simulada', [1760, 340],
             "const c = $json;\nreturn [{ json: { simulada: true, result: '¡Listo ' + c.name + '! He agendado la reunión para el '"
             " + c.date.split('-').reverse().join('-') + ' a las ' + c.time + ' hrs. Te llegará una invitación a tu correo ' + c.email + '.' } }];"),
        nodo('igh-agendar', 'Agendar en PROD', 'n8n-nodes-base.executeWorkflow', 1, [1760, 480],
             {'workflowId': AGENDAR_PROD, 'options': {}}),
        code('igh-resultado', 'Resultado', [1980, 480],
             "return [{ json: { result: String($json.result || 'No pudimos agendar esa hora. Ofrece otra o deriva a una persona.') } }];"),
    ]
    con = conectar(
        ('Entrada', '¿Qué herramienta?', 0),
        ('¿Qué herramienta?', 'Agenda', 0), ('¿Qué herramienta?', 'Validar cita', 1),
        ('Agenda', 'Fechas a revisar', 0), ('Fechas a revisar', '¿Ya hay respuesta?', 0),
        ('¿Ya hay respuesta?', 'Horas ocupadas', 1), ('Horas ocupadas', 'Horas libres', 0),
        ('Validar cita', '¿Cita válida?', 0), ('¿Cita válida?', 'Agenda del día', 0),
        ('Agenda del día', 'Revisar hora', 0), ('Revisar hora', '¿Hora libre?', 0),
        ('¿Hora libre?', '¿Es prueba?', 0),
        ('¿Es prueba?', 'Cita simulada', 0), ('¿Es prueba?', 'Agendar en PROD', 1),
        ('Agendar en PROD', 'Resultado', 0),
    )
    return {'name': 'Agente Tech Instagram · Herramientas', 'nodes': nodes, 'connections': con,
            'settings': {'executionOrder': 'v1', 'timezone': ZONA}}


if __name__ == '__main__':
    for nombre, wf in (('agente-tech-instagram.json', flujo_principal()),
                       ('agente-tech-instagram-herramientas.json', flujo_herramientas())):
        with open(os.path.join(AQUI, nombre), 'w', encoding='utf-8', newline='\n') as f:
            json.dump(wf, f, ensure_ascii=False, indent=1)
            f.write('\n')
        print('generado', nombre, len(wf['nodes']), 'nodos')
