"""Prueba local de los flujos n8n del plan de trabajo, sin llamar a OpenAI, WordPress, al renderer ni a n8n.

Uso: python N8N/plan-trabajo/probar_plan.py [sección ...]   (sin argumentos corre todas; sale con 1 si algo falla)
Secciones: ver SECCIONES al final (puras, correos, borrador, cambios y las que agregan las tareas siguientes).

Qué se prueba:
- El JavaScript compartido (plan_js.py) corriendo con node: lectura de la respuesta del modelo, fotos del plan,
  días que Luis editó a mano.
- Los correos (correos_plan.py) con datos de ejemplo: nunca enlazan a *.easypanel.host.
- Los flujos completos, recorriendo el grafo de plan-*.json con una pequeña máquina de estados en node (como
  N8N/propuestas-v3/probar_revision_fotos.py): los nodos Code corren con su jsCode REAL (el que se publica); los
  HTTP, OpenAI, el webhook y el correo son simulados; las expresiones ={{ … }} de URL, cuerpos e IF se evalúan igual
  que en n8n, así que lo que se revisa es lo que de verdad se enviaría.
Datos de prueba inventados: «Cliente Prueba», «[PRUEBA] …». El repositorio es público.
"""
import json, os, subprocess, sys

sys.stdout.reconfigure(encoding='utf-8')
AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'propuestas-v3'))
sys.path.insert(0, AQUI)
fallas = []
SECCIONES = {}

CIERRE = 'no signs, no labels, no text, no lettering, no logos, no watermarks, no subtitles, no captions'
NUEVAS = ['metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal']
TODAS = ['cover', 'metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal', 'cierre']
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
CRED_WP_ID = '1NI0sJKc0kC430pb'
# Topes que valida WordPress (grupo-A.md, Task 2, «Para la Task 13»; decisión D12): los dos prompts los dicen tal cual.
TOPES_PROMPT = ['13 bloques', '10 actividades por bloque', '58 actividades en total', 'de 1 a 60 días hábiles por actividad',
                # M1 (revisión final): WordPress cuenta 130 días con el «Arranque» (4 días) incluido; el modelo no lo manda, así
                # que su tope es 126 y el prompt avisa que el sistema suma esos 4.
                '126 días hábiles', 'el sistema suma 4 días del Arranque',
                '5 días hábiles de revisión por cada bloque con entrega', '10 hitos',
                '"diseno_desarrollo", "implementacion" y "soporte"', '"at", "cliente" o "ambos"',
                'NO incluyas el bloque «Arranque»']


def seccion(nombre):
    def deco(fn):
        SECCIONES[nombre] = fn
        return fn
    return deco


def ok(cond, msg, detalle=''):
    print(('ok    ' if cond else 'FALLA ') + msg + ('' if cond or not detalle else f' ({str(detalle)[:300]})'))
    if not cond:
        fallas.append(msg)


def node_js(programa, datos=None):
    """Corre JavaScript con node (por la entrada estándar: no cabe en la línea de comandos de Windows)."""
    prog = 'const DATOS = ' + json.dumps(datos, ensure_ascii=False) + ';\n' + programa
    r = subprocess.run(['node', '-'], input=prog, capture_output=True, text=True, encoding='utf-8')
    if r.returncode != 0:
        return None, (r.stderr.strip().splitlines() or ['node sin mensaje'])[-1]
    try:
        return json.loads(r.stdout), None
    except ValueError:
        return None, 'salida no es JSON: ' + r.stdout[:200]


def lib():
    from fotos_guard import JS_LIMPIAR_FOTOS
    from json_guard import JS_LEER_JSON
    from plan_js import JS_PLAN
    return JS_LEER_JSON + '\n' + JS_LIMPIAR_FOTOS + '\n' + JS_PLAN + '\n'


def cargar(archivo):
    with open(os.path.join(AQUI, archivo), encoding='utf-8') as fh:
        return json.load(fh)


# ---------------------------------------------------------------- datos de prueba (inventados)

def act(nombre, responsable='at', dias=3, servicio='', etapa='', paralelo=False, origen=None, **extra):
    a = {'nombre': nombre, 'detalle': '', 'responsable': responsable, 'dias_habiles': dias, 'servicio': servicio,
         'etapa': etapa, 'en_paralelo': paralelo}
    if origen:
        a['origen'] = origen
    a.update(extra)
    return a


TABLA = {'sitio_web_tienda': {'nombre': 'Sitio web o tienda', 'diseno': 5, 'desarrollo': 10, 'pruebas': 3, 'implementacion': 2}}
CONTRATO = {'servicios_contratados': '- **Sitio web de prueba**: $1.000.000', 'alcance': 'Sitio de prueba con catálogo.',
            'entregables': 'Sitio publicado', 'fases_siguientes': 'Fase 2: tienda en línea',
            'plazo': 'Se define con EL CLIENTE en la reunión de inicio y queda por escrito.', 'garantia_meses': 6}
PROPUESTA = {'company_name': '[PRUEBA] Panadería', 'solution_text': 'Sitio con catálogo de panes.',
             'how_it_works': ['Catálogo: tus panes en línea'],
             'extracto_reunion': 'Cliente Prueba vende pan amasado en su barrio.'}


def contexto(**cambios):
    base = {'ok': True, 'plan_id': 9, 'codigo': 'PRUEBAplan01', 'estado': 'generando',
            'proyecto': '[PRUEBA] Sitio de la panadería', 'empresa': '[PRUEBA] Panadería', 'cliente': 'Cliente Prueba',
            'fecha_firma': '2026-09-26', 'contrato': CONTRATO, 'propuesta': PROPUESTA, 'tabla': TABLA,
            'slides_foto': TODAS, 'plan_actual': None, 'comentarios': '', 'crm_cliente_id': 5,
            'rubro': '[PRUEBA] Panadería'}
    base.update(cambios)
    return base


PLAN_IA = {
    'version': 1, 'proyecto': '[PRUEBA] Sitio de la panadería',
    'fases': [
        {'clave': 'diseno_desarrollo', 'descripcion': 'Diseñamos y construimos tu sitio.', 'bloques': [
            {'nombre': 'Arranque', 'entregable': '', 'entrega': False, 'actividades': [act('Reunión de inicio', 'ambos', 1)]},
            {'nombre': 'Diseño', 'entregable': 'Diseño del sitio', 'entrega': True, 'actividades': [
                act('Diseño de la portada', 'at', 3, 'sitio_web_tienda', 'diseno', origen='tabla'),
                act('Diseño del catálogo', 'at', 2, 'sitio_web_tienda', 'diseno', True)]},
            {'nombre': 'Desarrollo', 'entregable': 'Sitio para probar', 'entrega': True, 'actividades': [
                act('Construcción del sitio', 'at', 8, 'sitio_web_tienda', 'desarrollo')]}]},
        {'clave': 'implementacion', 'descripcion': 'Publicamos tu sitio.', 'bloques': [
            {'nombre': 'Puesta en marcha', 'entregable': 'Sitio publicado', 'entrega': True, 'actividades': [
                act('Publicación del sitio', 'at', 2, 'sitio_web_tienda', 'implementacion')]}]},
        {'clave': 'soporte', 'descripcion': 'Te acompañamos.', 'bloques': [
            {'nombre': 'Garantía', 'entregable': '', 'entrega': False, 'actividades': [
                act('Acompañamiento y ajustes', 'at', 5, '', 'soporte')]}]},
    ],
    'hitos': [{'nombre': 'Diseño aprobado', 'despues_de': 'Diseño'}],
    'necesitamos_de_ti': ['Logo', 'Textos', 'Fotos de tus productos'],
    'reuniones': [{'nombre': 'Reunión de inicio', 'detalle': ''}, {'nombre': 'Llamada de seguimiento del plan', 'detalle': ''},
                  {'nombre': 'Entrega y capacitación', 'detalle': ''}],
    'soporte': {'garantia_meses': 3, 'mensuales': []},
    'image_briefs': [
        {'slide': 'cover', 'prompt': 'close-up of hands kneading bread dough, background completely blurred'},
        {'slide': 'metodo', 'prompt': 'bakers preparing the counter before opening, warm morning light'},
        {'slide': 'gantt', 'prompt': 'hands shaping loaves one by one on a floured table'},
        {'slide': 'gantt', 'prompt': 'segunda foto de la misma lámina, se descarta'},
        {'slide': 'fase_1', 'prompt': 'flour, eggs and wooden tools laid out on a table'},
        {'slide': 'fase_2', 'prompt': 'baker handing a warm loaf to the first customer of the day'},
        {'slide': 'fase_3', 'prompt': 'baker calmly serving a customer in the afternoon'},
        {'slide': 'necesitamos', 'prompt': 'hands gathering bread baskets on a counter'},
        {'slide': 'reuniones', 'prompt': 'two bakers talking in a meeting at the office'},
        {'slide': 'portal', 'prompt': 'owner resting with a coffee next to a laptop'},
        {'slide': 'cierre', 'prompt': 'bakery team celebrating the end of the day'},
        {'slide': 'challenge', 'prompt': 'lámina de la propuesta, no del plan'},
    ],
}


def brief(slide, tema):
    return {'slide': slide, 'prompt': f'{tema}, {CIERRE}'}


# Plan guardado en WordPress (forma normalizada): Arranque de la tabla, días de Luis, fechas y cronograma.
PLAN_GUARDADO = {
    'version': 1, 'proyecto': '[PRUEBA] Sitio de la panadería', 'fecha_firma': '2026-09-26', 'fecha_inicio': '2026-09-28',
    'fases': [
        {'clave': 'diseno_desarrollo', 'titulo': 'Diseño y desarrollo', 'descripcion': 'Diseñamos y construimos tu sitio.', 'bloques': [
            {'nombre': 'Arranque', 'entregable': '', 'entrega': False, 'actividades': [
                act('Reunión de inicio', 'ambos', 1, '', 'arranque', origen='tabla', desde='2026-09-28', hasta='2026-09-28'),
                act('Entrega de logo, textos y accesos', 'cliente', 3, '', 'arranque', origen='tabla', desde='2026-09-29', hasta='2026-10-01')]},
            {'nombre': 'Diseño', 'entregable': 'Diseño del sitio', 'entrega': True, 'actividades': [
                act('Diseño de la portada', 'at', 7, 'sitio_web_tienda', 'diseno', origen='luis', desde='2026-10-02', hasta='2026-10-12'),
                act('Diseño del catálogo', 'at', 2, 'sitio_web_tienda', 'diseno', True, origen='tabla', desde='2026-10-02', hasta='2026-10-05')]},
            {'nombre': 'Desarrollo', 'entregable': 'Sitio para probar', 'entrega': True, 'actividades': [
                act('Construcción del sitio', 'at', 10, 'sitio_web_tienda', 'desarrollo', origen='tabla', desde='2026-10-20', hasta='2026-11-02')]}]},
        {'clave': 'implementacion', 'titulo': 'Implementación', 'descripcion': 'Publicamos tu sitio.', 'bloques': [
            {'nombre': 'Puesta en marcha', 'entregable': 'Sitio publicado', 'entrega': True, 'actividades': [
                act('Publicación del sitio', 'at', 2, 'sitio_web_tienda', 'implementacion', origen='tabla', desde='2026-11-10', hasta='2026-11-11')]}]},
        {'clave': 'soporte', 'titulo': 'Soporte y mejora continua', 'descripcion': 'Te acompañamos.', 'bloques': [
            {'nombre': 'Garantía', 'entregable': '', 'entrega': False, 'actividades': [
                act('Acompañamiento y ajustes', 'at', 5, '', 'soporte', origen='ia', desde='2026-11-19', hasta='2026-11-25')]}]},
    ],
    'hitos': [{'nombre': 'Diseño aprobado', 'despues_de': 'Diseño', 'fecha': '2026-10-19'}],
    'necesitamos_de_ti': ['Logo', 'Textos'],
    'reuniones': [{'nombre': 'Reunión de inicio', 'detalle': ''}],
    'soporte': {'garantia_meses': 3, 'mensuales': []},
    'image_briefs': [brief('metodo', 'bakers preparing the counter before opening, warm morning light'),
                     brief('gantt', 'hands shaping loaves one by one on a floured table')],
    'cronograma': {'inicio': '2026-09-28', 'fin': '2026-11-25', 'semanas': 9, 'barras': [], 'hitos': []},
}


def actividades(plan):
    return [(f['clave'], b['nombre'], a) for f in plan['fases'] for b in f['bloques'] for a in b['actividades']]


def actividad(plan, nombre):
    return next((a for _, _, a in actividades(plan) if a['nombre'] == nombre), None)


# ---------------------------------------------------------------- simulador de flujos n8n

SIM = r"""
const { wf, esc: E } = DATOS;
const nodos = Object.fromEntries(wf.nodes.map((n) => [n.name, n]));
const runData = {};
const T = { pasos: [], http: {}, openai: {}, correos: [], error: null };
const cuenta = {};
const $execution = { id: 'SIM-1' };
const $ = (n) => ({
  get isExecuted() { return !!(runData[n] && runData[n].length); },
  first: () => {
    if (!runData[n] || !runData[n].length) throw new Error('Nodo sin ejecutar: ' + n);
    return runData[n][runData[n].length - 1][0];
  },
});
function evaluar(expr, $json, $input) {
  if (typeof expr !== 'string' || !expr.startsWith('=')) return expr;
  const s = expr.slice(1);
  const ev = (code) => new Function('$', '$json', '$input', '$execution', 'return (' + code + ');')($, $json, $input, $execution);
  const uno = s.match(/^\{\{([\s\S]*)\}\}$/);
  if (uno && !uno[1].includes('{{')) return ev(uno[1]);
  return s.replace(/\{\{([\s\S]*?)\}\}/g, (_, code) => String(ev(code)));
}
function siguiente(lista, nombre) {
  const l = (lista || {})[nombre];
  if (!Array.isArray(l) || !l.length) throw new Error('Respuesta sin simular para: ' + nombre);
  cuenta[nombre] = (cuenta[nombre] || 0) + 1;
  return JSON.parse(JSON.stringify(l[Math.min(cuenta[nombre] - 1, l.length - 1)]));
}
function correr(nombre, items) {
  const n = nodos[nombre];
  if (!n) throw new Error('Conexión a un nodo que no existe: ' + nombre);
  const $json = items[0] ? items[0].json : {};
  const $input = { first: () => items[0], all: () => items, item: items[0] };
  const $runIndex = (runData[nombre] || []).length;
  const p = n.parameters;
  let salidas;
  if (n.type === 'n8n-nodes-base.webhook') {
    salidas = [[{ json: { headers: {}, body: E.webhook } }]];
  } else if (n.type === 'n8n-nodes-base.code') {
    const fn = new Function('$', '$json', '$input', '$runIndex', '$execution', p.jsCode);
    salidas = [fn($, $json, $input, $runIndex, $execution)];
  } else if (n.type === 'n8n-nodes-base.httpRequest') {
    const url = evaluar(p.url, $json, $input);
    const cuerpo = p.sendBody ? JSON.parse(evaluar(p.jsonBody, $json, $input)) : undefined;
    (T.http[nombre] = T.http[nombre] || []).push({ url, body: cuerpo, method: p.method });
    salidas = [[{ json: siguiente(E.http, nombre) }]];
  } else if (n.type === 'n8n-nodes-base.openAi') {
    const msgs = p.prompt.messages;
    (T.openai[nombre] = T.openai[nombre] || []).push({ model: p.model, temperature: p.options.temperature,
      system: msgs[0].content, user: evaluar(msgs[1].content, $json, $input) });
    const r = siguiente(E.openai, nombre);
    // {content} = respuesta; {error} = falla con el detalle en json; {} = falla sin detalle (el nodo declarativo
    // de OpenAI con continueRegularOutput puede dejar el error fuera de json y entregar {}).
    salidas = [[{ json: r.error ? { error: r.error } : ('content' in r ? { message: { role: 'assistant', content: r.content } } : {}) }]];
  } else if (n.type === 'n8n-nodes-base.if') {
    const v = evaluar(p.conditions.conditions[0].leftValue, $json, $input);
    salidas = (v === true || v === 'true') ? [items, []] : [[], items];
  } else if (n.type === 'n8n-nodes-base.emailSend') {
    T.correos.push({ para: p.toEmail, asunto: evaluar(p.subject, $json, $input), html: evaluar(p.html, $json, $input) });
    salidas = [items];
  } else {
    throw new Error('tipo sin simular: ' + n.type);
  }
  runData[nombre] = runData[nombre] || [];
  runData[nombre].push(salidas.flat());
  T.salidas = T.salidas || {};
  T.salidas[nombre] = salidas.flat().map((i) => i.json);
  return salidas;
}
try {
  const cola = [['Webhook', [{ json: {} }]]];
  let pasos = 0;
  while (cola.length) {
    if (++pasos > 300) throw new Error('el flujo no termina (más de 300 pasos)');
    const [nombre, items] = cola.shift();
    T.pasos.push(nombre);
    const salidas = correr(nombre, items);
    const con = (wf.connections[nombre] || { main: [] }).main;
    salidas.forEach((its, i) => {
      if (!its || !its.length) return;
      for (const d of con[i] || []) cola.push([d.node, its]);
    });
  }
} catch (e) {
  T.error = e.message;
}
console.log(JSON.stringify(T));
"""


def simular(archivo, **esc):
    t, err = node_js(SIM, {'wf': cargar(archivo), 'esc': esc})
    return t if t is not None else {'error': err, 'pasos': [], 'http': {}, 'openai': {}, 'correos': [], 'salidas': {}}


def llamadas(t, nodo):
    return t.get('http', {}).get(nodo, [])


def sin_error(t, caso):
    ok(not t.get('error'), f'{caso}: el recorrido termina sin lanzar', t.get('error'))


def revisar_workflow(archivo, nombre, path):
    wf = cargar(archivo)
    nombres = [n['name'] for n in wf['nodes']]
    ok(wf['name'] == nombre, f'{archivo}: se llama «{nombre}»', wf['name'])
    ok(len(nombres) == len(set(nombres)), f'{archivo}: nombres de nodo únicos')
    ok(wf['settings'].get('errorWorkflow') == 'm7TOfKznVSBGz4Nd', f'{archivo}: usa el flujo común de avisos de error')
    destinos = [d['node'] for c in wf['connections'].values() for rama in c['main'] for d in rama]
    ok(all(d in nombres for d in destinos) and all(o in nombres for o in wf['connections']),
       f'{archivo}: toda conexión une nodos que existen')
    hook = next(n for n in wf['nodes'] if n['type'] == 'n8n-nodes-base.webhook')
    hp = hook['parameters']
    ok(hp['path'] == path and hp['authentication'] == 'headerAuth' and hp['responseMode'] == 'onReceived'
       and hook['credentials']['httpHeaderAuth']['id'] == CRED_WP_ID and hook.get('webhookId') == path,
       f'{archivo}: webhook {path} con la credencial «AT REST Secret (header)»')
    for n in wf['nodes']:
        if n['type'] == 'n8n-nodes-base.httpRequest' and n['parameters']['url'].startswith('=' + WP):
            ok(n['credentials']['httpHeaderAuth']['id'] == CRED_WP_ID and n.get('onError') == 'continueRegularOutput'
               and n['parameters']['options']['response']['response'] == {'fullResponse': True, 'neverError': True},
               f'{archivo}: «{n["name"]}» va a WordPress con X-AT-Secret y nunca corta el flujo')
        if n['type'] == 'n8n-nodes-base.emailSend':
            ok(n['parameters']['toEmail'] == 'lmgm.0303@gmail.com', f'{archivo}: «{n["name"]}» le escribe solo a Luis')
        if n['type'] == 'n8n-nodes-base.openAi':
            ok(n['credentials']['openAiApi']['id'] == 'g52IEXpRfN5r7jKw' and n.get('onError') == 'continueRegularOutput',
               f'{archivo}: «{n["name"]}» usa la credencial de OpenAI y una falla no corta el flujo')
    return wf


# ---------------------------------------------------------------- secciones de la Task 13

@seccion('puras')
def prueba_puras():
    base = lib()
    prog = base + r"""
const R = {};
R.slides_con = slidesConFoto(true, 3);
R.slides_sin = slidesConFoto(false, 3);
R.slides_dos = slidesConFoto(true, 2);
const b = DATOS.briefs;
R.fotos_con = fotosDelPlan(b, 3, true);
R.fotos_sin = fotosDelPlan(b, 3, false);
R.fotos_dos = fotosDelPlan(b, 2, true);
R.fotos_dos_veces = fotosDelPlan(R.fotos_sin, 3, false);
R.fotos_basura = fotosDelPlan([null, 'x', { slide: 'metodo' }, { slide: 'gantt', prompt: 7 }, { slide: 'portal', prompt: '   ' }], 3, true);
const leer = (content, op) => leerPlanModelo({ message: { content } }, op);
const B = { modo: 'borrador', hayPropuesta: true };
R.ok = leer(JSON.stringify(DATOS.plan), B);
R.llave = leer(JSON.stringify(DATOS.plan) + '}', B);
R.envuelto = leer(JSON.stringify({ plan: DATOS.plan }), B);
R.texto = leer('Aquí va el plan:\n' + JSON.stringify(DATOS.plan), B);
R.arreglo = leer('[' + JSON.stringify(DATOS.plan) + ']', B);
R.nulo = leer('null', B);
R.ajenas = leer(JSON.stringify(Object.assign({ nota: 'x' }, DATOS.plan)), B);
R.sin_fases = leer(JSON.stringify({ proyecto: 'x', fases: [] }), B);
R.fase_rara = leer(JSON.stringify({ fases: [{ clave: 'diseno_desarrollo', bloques: 'no' }] }), B);
R.solo_arranque = leer(JSON.stringify({ fases: [{ clave: 'diseno_desarrollo', bloques: [DATOS.plan.fases[0].bloques[0]] },
  { clave: 'implementacion', bloques: [] }] }), B);
R.vacia = leerPlanModelo({ message: { content: '' } }, B);
R.caida = leerPlanModelo({ error: { message: 'Rate limit' } }, B);
R.cortada = leer(JSON.stringify(DATOS.plan).slice(0, 400), B);
const C = { modo: 'cambios', hayPropuesta: true, anterior: DATOS.guardado };
const cambiado = JSON.parse(JSON.stringify(DATOS.guardado));
cambiado.fases[0].bloques[1].actividades[0].dias_habiles = 2;
cambiado.fases[0].bloques[1].actividades[0].origen = 'ia';
cambiado.fases[0].bloques[2].actividades[0].origen = 'luis';
cambiado.fases[0].bloques[2].actividades[0].dias_habiles = 12;
cambiado.fases[1].bloques[0].actividades.push({ nombre: 'Capacitación del equipo', responsable: 'ambos', dias_habiles: 1, origen: 'luis' });
delete cambiado.image_briefs;
R.cambios = leer(JSON.stringify(cambiado), C);
R.cambios_vacio = leer('{}', C);
R.cambios_nota = leer('{"nota": "no hay cambios"}', C);
R.cambios_clave_previa = leer(JSON.stringify({ extra_wp: 1, fases: DATOS.guardado.fases }), Object.assign({}, C,
  { anterior: Object.assign({ extra_wp: 0 }, DATOS.guardado) }));
R.cambios_ajena = leer(JSON.stringify({ nota: 'x', fases: DATOS.guardado.fases }), C);
// Luis pidió «pasa la portada a Desarrollo»: el modelo la movió de bloque y le cambió los días.
const movido = JSON.parse(JSON.stringify(DATOS.guardado));
const mov = movido.fases[0].bloques[1].actividades.shift();
mov.dias_habiles = 2;
mov.origen = 'ia';
movido.fases[0].bloques[2].actividades.push(mov);
R.cambios_movida = leer(JSON.stringify(movido), C);
// Respuesta parcial: solo la fase que cambió y solo la foto pedida.
const impl = JSON.parse(JSON.stringify(DATOS.guardado.fases[1]));
impl.bloques[0].actividades.push({ nombre: 'Capacitación del equipo', responsable: 'ambos', dias_habiles: 1 });
R.cambios_parcial = leer(JSON.stringify({ fases: [impl],
  image_briefs: [{ slide: 'metodo', prompt: 'baker opening the shutters of the bakery at dawn' }] }), C);
// Dos actividades con el mismo nombre: se reconocen por orden de aparición, como at_pt_recorrer_actividades (Task 2).
const repetida = JSON.parse(JSON.stringify(DATOS.guardado));
repetida.fases[0].bloques[1].actividades.push({ nombre: 'Revisión interna', responsable: 'at', dias_habiles: 4, origen: 'luis' });
repetida.fases[0].bloques[2].actividades.push({ nombre: 'Revisión interna', responsable: 'at', dias_habiles: 2, origen: 'ia' });
const repetidaIa = JSON.parse(JSON.stringify(repetida));
repetidaIa.fases[0].bloques[1].actividades[2].dias_habiles = 1;
repetidaIa.fases[0].bloques[2].actividades[1].dias_habiles = 3;
R.cambios_repetida = leer(JSON.stringify(repetidaIa), Object.assign({}, C, { anterior: repetida }));
// «Arranque» (D12): al modelo no se le manda; si no lo devuelve, vuelve el guardado; si manda uno propio, se descarta.
const sinArr = quitarArranque(JSON.parse(JSON.stringify(DATOS.guardado)));
R.quitado = sinArr.fases.map((f) => f.bloques.map((b) => b.nombre));
R.guardado_intacto = DATOS.guardado.fases[0].bloques[0].nombre;
R.cambios_sin_arranque = leer(JSON.stringify({ fases: sinArr.fases }), C);
const conSuyo = JSON.parse(JSON.stringify(DATOS.guardado));
conSuyo.fases[0].bloques[0].actividades[1].dias_habiles = 9;
conSuyo.fases[0].bloques[0].actividades.push({ nombre: 'Visita al local', responsable: 'ambos', dias_habiles: 2 });
conSuyo.fases[1].bloques.unshift({ nombre: 'Arranque', entrega: false, actividades: [{ nombre: 'Otra reunión', responsable: 'ambos', dias_habiles: 1 }] });
R.cambios_arranque_ia = leer(JSON.stringify(conSuyo), C);
const luisEnArranque = JSON.parse(JSON.stringify(DATOS.guardado));
Object.assign(luisEnArranque.fases[0].bloques[0].actividades[1], { dias_habiles: 5, origen: 'luis' });
R.cambios_arranque_luis = leer(JSON.stringify({ fases: sinArr.fases }), Object.assign({}, C, { anterior: luisEnArranque }));
console.log(JSON.stringify(R));
"""
    r, err = node_js(prog, {'briefs': PLAN_IA['image_briefs'], 'plan': PLAN_IA, 'guardado': PLAN_GUARDADO})
    ok(r is not None, 'puras: el JavaScript de plan_js corre con node', err)
    if r is None:
        return
    ok(r['slides_con'] == NUEVAS, 'slidesConFoto: con propuesta, solo las 8 láminas nuevas', r['slides_con'])
    ok(r['slides_sin'] == TODAS, 'slidesConFoto: sin propuesta, también portada (primera) y cierre (última)', r['slides_sin'])
    ok('fase_3' not in r['slides_dos'] and 'fase_2' in r['slides_dos'], 'slidesConFoto: con 2 fases no pide fase_3', r['slides_dos'])
    s_con = [b['slide'] for b in r['fotos_con']]
    ok(s_con == NUEVAS, 'fotosDelPlan: con propuesta descarta portada, cierre, láminas ajenas y la foto repetida', s_con)
    ok([b['slide'] for b in r['fotos_sin']] == ['cover'] + NUEVAS + ['cierre'],
       'fotosDelPlan: sin propuesta conserva portada y cierre', [b['slide'] for b in r['fotos_sin']])
    ok('fase_3' not in [b['slide'] for b in r['fotos_dos']], 'fotosDelPlan: con 2 fases descarta fase_3')
    ok(all(b['prompt'].endswith(CIERRE) and b['prompt'].count('no subtitles') == 1 for b in r['fotos_sin']),
       'fotosDelPlan: toda descripción termina con el cierre de prohibiciones, una sola vez')
    gantt = next(b for b in r['fotos_con'] if b['slide'] == 'gantt')
    ok(gantt['prompt'].startswith('hands shaping loaves'), 'fotosDelPlan: de una lámina repetida queda la primera', gantt['prompt'])
    reu = next(b for b in r['fotos_con'] if b['slide'] == 'reuniones')
    ok('meeting' not in reu['prompt'] and 'office' not in reu['prompt'],
       'fotosDelPlan: «reunión en la oficina» se reemplaza por una escena neutra (fotos_guard)', reu['prompt'])
    portal = next(b for b in r['fotos_con'] if b['slide'] == 'portal')
    ok('facing away' in portal['prompt'], 'fotosDelPlan: el computador del portal queda apagado y de espaldas', portal['prompt'])
    cover = next(b for b in r['fotos_sin'] if b['slide'] == 'cover')
    ok('close-up' in cover['prompt'] or 'blurred' in cover['prompt'], 'fotosDelPlan: la portada va en primer plano', cover['prompt'])
    ok(r['fotos_dos_veces'] == r['fotos_sin'], 'fotosDelPlan: filtrar dos veces no cambia nada (el renderer reutiliza por hash)')
    ok(r['fotos_basura'] == [], 'fotosDelPlan: descarta entradas sin lámina o sin descripción', r['fotos_basura'])

    plan = (r['ok'] or {}).get('plan') or {}
    ok(r['ok']['ok'] is True and r['ok']['reason'] == '', 'leerPlanModelo: JSON limpio → ok', r['ok'].get('reason'))
    nombres_b = [b['nombre'] for b in plan.get('fases', [{}])[0].get('bloques', [])]
    ok(nombres_b == ['Diseño', 'Desarrollo'], 'leerPlanModelo (borrador): descarta el «Arranque» del modelo', nombres_b)
    ok(all(a.get('origen') == 'ia' for _, _, a in actividades(plan)) and plan.get('fases'),
       'leerPlanModelo (borrador): toda actividad queda con origen «ia» (el modelo no puede declarar «tabla» ni «luis»)')
    ok([b['slide'] for b in plan.get('image_briefs', [])] == NUEVAS, 'leerPlanModelo (borrador): fotos filtradas', plan.get('image_briefs'))
    ok(r['llave']['ok'] is True, 'leerPlanModelo: tolera una llave «}» de más al final (json_guard)')
    ok(r['envuelto']['ok'] is True and r['envuelto']['plan']['proyecto'] == PLAN_IA['proyecto'], 'leerPlanModelo: desenvuelve {"plan": …}')
    aj = r['ajenas']
    ok(aj['ok'] is True and 'nota' not in (aj['plan'] or {}) and (aj['plan'] or {}).get('fases'),
       'leerPlanModelo (borrador): una clave ajena de primer nivel se descarta sin botar el plan (WordPress arma el plan solo con claves conocidas)', aj.get('reason'))
    for caso, inicio in (('texto', 'La respuesta del modelo no es un JSON válido'), ('arreglo', 'La respuesta del modelo no es un objeto JSON'),
                         ('nulo', 'La respuesta del modelo no es un objeto JSON'),
                         ('sin_fases', 'El plan del modelo no trae fases'), ('fase_rara', 'El plan del modelo trae una fase sin su lista de bloques'),
                         ('solo_arranque', 'El modelo no propuso ninguna actividad'), ('vacia', 'El modelo no respondió'),
                         ('caida', 'El modelo no respondió: Rate limit'), ('cortada', 'La respuesta del modelo no es un JSON válido')):
        ok(r[caso]['ok'] is False and r[caso]['plan'] is None and r[caso]['reason'].startswith(inicio),
           f'leerPlanModelo: {caso} → error legible «{inicio}…»', r[caso])

    c = r['cambios']
    pc = c.get('plan') or {}
    ok(c['ok'] is True, 'leerPlanModelo (cambios): respuesta completa → ok', c.get('reason'))
    portada = actividad(pc, 'Diseño de la portada') if pc else None
    ok(portada and portada['dias_habiles'] == 7 and portada['origen'] == 'luis',
       'protegerLuis: los días que Luis puso a mano (7) no se pisan aunque el modelo los cambie a 2', portada)
    ok(c['protegidas'] == 1, 'protegerLuis: cuenta la actividad de Luis restituida', c['protegidas'])
    constr = actividad(pc, 'Construcción del sitio') if pc else None
    ok(constr and constr['origen'] == 'ia' and constr['dias_habiles'] == 12,
       'protegerLuis: el modelo no puede declarar «luis»; la actividad de la tabla a la que le cambió los días queda «ia» (revisar)', constr)
    catalogo = actividad(pc, 'Diseño del catálogo') if pc else None
    ok(catalogo and catalogo['origen'] == 'tabla' and catalogo['dias_habiles'] == 2,
       'protegerLuis: una actividad de la tabla con los mismos días conserva «tabla»', catalogo)
    nueva = actividad(pc, 'Capacitación del equipo') if pc else None
    ok(nueva and nueva['origen'] == 'ia', 'protegerLuis: una actividad que agregó el modelo queda «ia» aunque diga «luis»', nueva)
    arr = [a['origen'] for f, b, a in actividades(pc) if b == 'Arranque'] if pc else []
    ok(arr == ['tabla', 'tabla'], 'leerPlanModelo (cambios): el bloque «Arranque» se conserva con origen «tabla»', arr)
    ok(pc.get('image_briefs') == PLAN_GUARDADO['image_briefs'], 'leerPlanModelo (cambios): lo que el modelo omite se conserva del plan guardado')
    ok(r['cambios_vacio']['ok'] is False and 'no trae ninguna parte del plan' in r['cambios_vacio']['reason'],
       'leerPlanModelo (cambios): {} → error, no un «cambio» vacío')
    ok(r['cambios_nota']['ok'] is False and 'claves que no son del plan: nota' in r['cambios_nota']['reason'],
       'leerPlanModelo (cambios): una nota suelta no pasa como cambio aplicado')
    ok(r['cambios_clave_previa']['ok'] is True, 'leerPlanModelo (cambios): acepta una clave que ya traía el plan guardado')
    ok(r['cambios_ajena']['ok'] is False and 'claves que no son del plan: nota' in r['cambios_ajena']['reason'],
       'leerPlanModelo (cambios): una nota junto a las fases tampoco pasa (puede ser un «antes/después»)')
    pm = r['cambios_movida'].get('plan') or {'fases': []}
    mv = actividad(pm, 'Diseño de la portada')
    en_desarrollo = [a['nombre'] for f, b, a in actividades(pm) if b == 'Desarrollo']
    ok(mv and mv['dias_habiles'] == 7 and mv['origen'] == 'luis' and 'Diseño de la portada' in en_desarrollo,
       'protegerLuis: si el modelo mueve de bloque una actividad de Luis, sus días (7) se conservan', mv)
    pp = r['cambios_parcial'].get('plan') or {'fases': []}
    ok(r['cambios_parcial']['ok'] is True and [f['clave'] for f in pp['fases']] == ['diseno_desarrollo', 'implementacion', 'soporte'],
       'leerPlanModelo (cambios): si el modelo devuelve solo la fase que cambió, las otras fases se conservan', [f.get('clave') for f in pp['fases']])
    pp_portada = actividad(pp, 'Diseño de la portada')
    ok(pp_portada and pp_portada['dias_habiles'] == 7 and pp_portada['origen'] == 'luis'
       and (actividad(pp, 'Capacitación del equipo') or {}).get('origen') == 'ia',
       'leerPlanModelo (cambios): respuesta parcial → los días de Luis siguen y la actividad nueva entra «ia»', pp_portada)
    fotos_pp = pp.get('image_briefs') or []
    ok([b['slide'] for b in fotos_pp] == ['metodo', 'gantt'] and fotos_pp[0]['prompt'] == 'baker opening the shutters of the bakery at dawn, ' + CIERRE
       and fotos_pp[1] == PLAN_GUARDADO['image_briefs'][1],
       'leerPlanModelo (cambios): si el modelo devuelve solo la foto pedida, las demás quedan idénticas (mismo hash)', fotos_pp)
    pr = r['cambios_repetida'].get('plan') or {'fases': []}
    rep = [(b, a['dias_habiles'], a['origen']) for f, b, a in actividades(pr) if a['nombre'] == 'Revisión interna']
    ok(rep == [('Diseño', 4, 'luis'), ('Desarrollo', 3, 'ia')],
       'protegerLuis: dos actividades con el mismo nombre se reconocen por orden de aparición (como at_pt_recorrer_actividades)', rep)
    ok(r['quitado'] == [['Diseño', 'Desarrollo'], ['Puesta en marcha'], ['Garantía']] and r['guardado_intacto'] == 'Arranque',
       'quitarArranque: saca solo el bloque «Arranque» de la copia que recibe', r['quitado'])
    guardado_arr = PLAN_GUARDADO['fases'][0]['bloques'][0]
    ps = r['cambios_sin_arranque'].get('plan') or {'fases': [{'bloques': [{}]}]}
    ok(r['cambios_sin_arranque']['ok'] is True and ps['fases'][0]['bloques'][0] == guardado_arr
       and [b['nombre'] for f in ps['fases'] for b in f['bloques']].count('Arranque') == 1,
       'restituirArranque: si el modelo no manda el «Arranque», vuelve el guardado, igual y en su lugar', ps['fases'][0]['bloques'][0])
    pa_ = r['cambios_arranque_ia'].get('plan') or {'fases': [{'bloques': [{}]}, {'bloques': []}]}
    ok(pa_['fases'][0]['bloques'][0] == guardado_arr and [b['nombre'] for b in pa_['fases'][1]['bloques']] == ['Puesta en marcha']
       and actividad(pa_, 'Visita al local') is None and actividad(pa_, 'Otra reunión') is None,
       'restituirArranque: el «Arranque» que manda el modelo (con días o actividades cambiados, o en otra fase) se descarta', pa_['fases'][0]['bloques'][0])
    pl = r['cambios_arranque_luis'].get('plan') or {'fases': []}
    ent = actividad(pl, 'Entrega de logo, textos y accesos') or {}
    ok(ent.get('dias_habiles') == 5 and ent.get('origen') == 'luis',
       'restituirArranque: lo que Luis editó en el «Arranque» (5 días, «luis») se conserva', ent)


@seccion('correos')
def prueba_correos():
    from correos_plan import JS_PANEL, correo_error_plan, correo_sin_vista
    r, err = node_js(JS_PANEL + "console.log(JSON.stringify([urlPanel(5, 9), urlPanel(0, 9), urlPanel('abc', 9), urlPanel(5, 0), urlPanel(null, null)]));")
    ok(r == ['https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-ficha&id=5&pt=9#tab-plan',
             'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-clientes',
             'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-clientes',
             'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-ficha&id=5#tab-plan',
             'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-clientes'],
       'urlPanel: ficha del cliente en la pestaña del plan; sin ficha conocida, la lista de clientes', r or err)
    try:
        correo_error_plan('otro')
        ok(False, 'correo_error_plan: un flujo desconocido se rechaza')
    except ValueError:
        ok(True, 'correo_error_plan: un flujo desconocido se rechaza')

    def correr(codigo, datos):
        js = ("const $ = (n) => ({ isExecuted: n in DATOS, first: () => ({ json: DATOS[n] }) });\n"
              "const $execution = { id: '777' };\n"
              "const salida = (function () {\n" + codigo + "\n})();\nconsole.log(JSON.stringify(salida[0].json));")
        return node_js(js, datos)

    motivo = {'id': 9, 'crm': 5, 'proyecto': '[PRUEBA] Sitio <b>de</b> la panadería', 'reason': 'La respuesta no es JSON\nsegunda línea', 'exec': '777'}
    for flujo, frase, siguiente in (('borrador', 'no se pudo generar el borrador del plan', 'Reintentar borrador'),
                                    ('cambios', 'no se pudieron aplicar los cambios al plan', 'Volver al borrador')):
        r, err = correr(correo_error_plan(flujo), {'Motivo del error': motivo, 'Marcar error': {'statusCode': 200}})
        ok(r is not None, f'correo {flujo}: el código corre', err)
        if r is None:
            continue
        h = r['html']
        ok(r['asunto'] == '⚠️ [PRUEBA] Sitio <b>de</b> la panadería · ' + frase, f'correo {flujo}: asunto', r['asunto'])
        ok('Plan de trabajo · aviso interno' in h and 'Propuestas · aviso interno' not in h, f'correo {flujo}: rótulo del plan')
        ok('easypanel' not in h, f'correo {flujo}: no enlaza a *.easypanel.host')
        ok('automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in h, f'correo {flujo}: botón a la pestaña del plan en la ficha')
        ok('&lt;b&gt;de&lt;/b&gt;' in h and '<b>de</b>' not in h, f'correo {flujo}: escapa el nombre del proyecto')
        ok(siguiente in h and 'segunda línea' in h and 'La respuesta no es JSON<br>' in h, f'correo {flujo}: motivo y siguiente paso')
        ok('777' in h and 'Nada de esto le llegó al cliente' in h, f'correo {flujo}: ejecución y aviso de que el cliente no recibió nada')
        ok('el envío lo haces tú' not in h and 'Aviso automático del plan de trabajo de AutomatizaTech. Nada de esto le llega al cliente.' in h,
           f'correo {flujo}: pie del plan (en la Etapa 1 nada se le envía al cliente)')
        r2, _ = correr(correo_error_plan(flujo), {'Motivo del error': dict(motivo, crm=0), 'Marcar error': {'error': {'message': 'ECONNRESET'}}})
        h2 = (r2 or {}).get('html', '')
        trabado = 'generando' if flujo == 'borrador' else 'cambios'
        ok('Tampoco se pudo marcar el plan como error' in h2 and 'sin respuesta' in h2 and f'«{trabado}»' in h2 and 'Destrabar' in h2,
           f'correo {flujo}: si tampoco se pudo marcar el error, lo dice y sugiere «Destrabar»')
        ok('automatiza-crm-clientes' in h2, f'correo {flujo}: sin ficha conocida, enlaza a la lista de clientes')

    try:
        correo_sin_vista('otro')
        ok(False, 'correo_sin_vista: un flujo desconocido se rechaza')
    except ValueError:
        ok(True, 'correo_sin_vista: un flujo desconocido se rechaza')
    guardado = {'Webhook': {'body': {'id': 9}},
                'Leer contexto': {'statusCode': 200, 'body': {'proyecto': '[PRUEBA] Sitio <b>de</b> la panadería', 'crm_cliente_id': 5}},
                'Guardar borrador': {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': [
                    'Se descartó la foto de la lámina «gantt»: no trae descripción.', 'No se pudo pedir la vista previa: HTTP 404']}}}
    for flujo, frase in (('borrador', 'borrador del plan guardado sin vista previa'), ('cambios', 'cambios del plan guardados sin vista previa')):
        r, err = correr(correo_sin_vista(flujo), guardado)
        ok(r is not None, f'correo sin vista {flujo}: el código corre', err)
        if r is None:
            continue
        h = r['html']
        ok(r['asunto'] == '⚠️ [PRUEBA] Sitio <b>de</b> la panadería · ' + frase, f'correo sin vista {flujo}: asunto', r['asunto'])
        ok('No se pudo pedir la vista previa: HTTP 404' in h and 'gantt' not in h and 'Guardar y recalcular' in h and 'borrador listo' in h,
           f'correo sin vista {flujo}: dice por qué no llega el aviso «borrador listo» y cómo pedir la vista previa')
        ok('automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in h and 'easypanel' not in h and '&lt;b&gt;de&lt;/b&gt;' in h and '777' in h,
           f'correo sin vista {flujo}: botón a la pestaña del plan, proyecto escapado y ejecución')


@seccion('borrador')
def prueba_borrador():
    from build_plan_1_borrador import PROMPT_PLAN  # noqa: F401  (vuelve a escribir plan-1-borrador.json)
    revisar_workflow('plan-1-borrador.json', 'Plan de trabajo · 1 Borrador', 'plan-v1-borrador')
    ok('easypanel' not in json.dumps(cargar('plan-1-borrador.json')), '1 Borrador: no toca el renderer ni enlaza a *.easypanel.host')
    faltan = [x for x in TOPES_PROMPT if x not in PROMPT_PLAN]
    ok(not faltan, 'PROMPT_PLAN: dice los topes que valida WordPress (13 bloques, 10 por bloque, 58 actividades, 1 a 60 días, '
       '126 días hábiles más 4 del Arranque, 10 hitos, claves) y pide no mandar el «Arranque»', faltan)
    ok('130 días hábiles' not in PROMPT_PLAN, 'PROMPT_PLAN: no le dice al modelo 130 días (el tope que ve es 126: el Arranque suma 4)')
    GUARDADO_OK = {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': []}}
    MARCADO = {'statusCode': 200, 'body': {'ok': True}}
    hook = {'id': 9, 'codigo': 'PRUEBAplan01'}

    # 1. Contrato con propuesta: el plan se guarda en WordPress sin Arranque, con origen «ia» y solo fotos nuevas.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [GUARDADO_OK]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA, ensure_ascii=False)}]})
    sin_error(t, 'B1')
    g = llamadas(t, 'Guardar borrador')
    ok(len(g) == 1 and g[0]['url'] == WP + '/plan/9/borrador' and g[0]['body']['origen'] == 'borrador',
       'B1: un solo POST /plan/9/borrador con origen «borrador»', g)
    plan = g[0]['body']['plan'] if g else {}
    ok([b['nombre'] for b in plan.get('fases', [{}])[0].get('bloques', [])] == ['Diseño', 'Desarrollo'], 'B1: sin el Arranque del modelo')
    ok([b['slide'] for b in plan.get('image_briefs', [])] == NUEVAS, 'B1: con propuesta, solo las 8 fotos nuevas', plan.get('image_briefs'))
    ok(llamadas(t, 'Leer contexto')[0]['url'] == WP + '/plan/9/contexto', 'B1: lee el contexto del plan 9')
    ok(not llamadas(t, 'Marcar error') and not t['correos'], 'B1: sin error ni correo (el aviso lo manda «3 Render»)')
    o = t['openai'].get('Redactar plan', [{}])[0]
    ok(o.get('model') == 'gpt-4o' and o.get('temperature') == 0.3 and o.get('system') == PROMPT_PLAN,
       'B1: gpt-4o, temperatura 0,3 y PROMPT_PLAN', {k: o.get(k) for k in ('model', 'temperature')})
    pedido = json.loads(o.get('user') or '{}')
    ok(set(pedido) == {'proyecto', 'empresa', 'rubro', 'contrato', 'propuesta', 'tabla', 'slides_foto'} and pedido.get('rubro') == '[PRUEBA] Panadería',
       'B1: el modelo recibe proyecto, empresa, rubro (de la ficha del CRM), contrato, propuesta, tabla y láminas (ni el nombre de la persona ni fechas)', sorted(pedido))
    ok(pedido.get('slides_foto') == NUEVAS and pedido.get('contrato') == CONTRATO and pedido.get('tabla') == TABLA,
       'B1: lo contratado sale del contrato y las láminas pedidas son las nuevas')
    ok((pedido.get('contrato') or {}).get('garantia_meses') == 6,
       'B1: el modelo recibe los meses de garantía del contrato (contrato.garantia_meses, D7)', pedido.get('contrato'))

    # 2. Contrato sin propuesta: portada y cierre piden foto nueva.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto(propuesta=None, rubro='')}],
                'Guardar borrador': [GUARDADO_OK]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA, ensure_ascii=False)}]})
    sin_error(t, 'B2')
    g = llamadas(t, 'Guardar borrador')
    slides = [b['slide'] for b in (g[0]['body']['plan'].get('image_briefs', []) if g else [])]
    ok(slides == TODAS, 'B2: sin propuesta, el plan pide también portada y cierre', slides)
    pedido = json.loads(t['openai'].get('Redactar plan', [{}])[0].get('user') or '{}')
    ok(pedido.get('propuesta') is None and pedido.get('rubro') == '' and pedido.get('slides_foto') == TODAS, 'B2: el modelo sabe que no hay propuesta y qué láminas describir')

    # 3. Respuesta que no es JSON → «error» con motivo legible y correo; nunca se guarda un plan roto.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Marcar error': [MARCADO]}, openai={'Redactar plan': [{'content': 'Aquí va el plan:\n' + json.dumps(PLAN_IA)}]})
    sin_error(t, 'B3')
    me = llamadas(t, 'Marcar error')
    ok(not llamadas(t, 'Guardar borrador'), 'B3: no guarda nada en WordPress')
    ok(len(me) == 1 and me[0]['url'] == WP + '/plan/9/error' and me[0]['body']['nota'].startswith('La respuesta del modelo no es un JSON válido')
       and me[0]['body']['nota'].endswith('(ejecución SIM-1)'), 'B3: POST /plan/9/error con el motivo y la ejecución', me)
    ok(len(t['correos']) == 1 and t['correos'][0]['asunto'] == '⚠️ [PRUEBA] Sitio de la panadería · no se pudo generar el borrador del plan'
       and 'automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in t['correos'][0]['html'] and 'easypanel' not in t['correos'][0]['html'],
       'B3: correo a Luis con el enlace a la pestaña del plan', [c['asunto'] for c in t['correos']])

    # 4. Ninguna actividad (solo el Arranque del modelo) → error.
    vacio = {'fases': [{'clave': 'diseno_desarrollo', 'bloques': [PLAN_IA['fases'][0]['bloques'][0]]}, {'clave': 'implementacion', 'bloques': []}]}
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}], 'Marcar error': [MARCADO]},
                openai={'Redactar plan': [{'content': json.dumps(vacio)}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('El modelo no propuso ninguna actividad') and not llamadas(t, 'Guardar borrador'),
       'B4: sin actividades → «error», sin guardar', me)

    # 5. Fases desconocidas, días 0 y 200 y un texto enorme: n8n no los corrige (lo decide WordPress, que responde 422).
    raro = json.loads(json.dumps(PLAN_IA))
    raro['fases'].append({'clave': 'marketing', 'descripcion': '', 'bloques': [{'nombre': 'Campaña', 'actividades': [act('Anuncios', 'at', 200)]}]})
    raro['fases'][0]['bloques'][1]['actividades'][0]['dias_habiles'] = 0
    raro['fases'][0]['bloques'][1]['actividades'][0]['detalle'] = 'x' * 5000
    rechazo = {'statusCode': 422, 'body': {'ok': False, 'errores': ['Fase desconocida: marketing', 'Días hábiles fuera de rango (1 a 60) en «Anuncios»: 200'], 'avisos': []}}
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [rechazo], 'Marcar error': [MARCADO]}, openai={'Redactar plan': [{'content': json.dumps(raro)}]})
    sin_error(t, 'B5')
    g = llamadas(t, 'Guardar borrador')
    enviado = g[0]['body']['plan'] if g else {}
    ok(enviado and [f['clave'] for f in enviado['fases']][-1] == 'marketing'
       and actividad(enviado, 'Diseño de la portada')['dias_habiles'] == 0 and len(actividad(enviado, 'Diseño de la portada')['detalle']) == 5000,
       'B5: n8n manda el plan tal cual y WordPress (at_pt_validar_plan) decide')
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('WordPress rechazó el plan: Fase desconocida: marketing · Días hábiles fuera de rango'),
       'B5: el 422 de WordPress queda como motivo legible en la nota', me)
    ok(len(t['correos']) == 1 and 'Fase desconocida: marketing' in t['correos'][0]['html'], 'B5: el correo trae los errores de WordPress')

    # 6. WordPress no encuentra el plan: ni se llama a OpenAI.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 404, 'body': {'code': 'at_pt_no_existe', 'message': 'Plan no encontrado'}}],
                'Marcar error': [{'statusCode': 404, 'body': {}}]}, openai={})
    sin_error(t, 'B6')
    me = llamadas(t, 'Marcar error')
    ok(not t['openai'] and me and me[0]['body']['nota'].startswith('No se pudo leer el contexto del plan en WordPress (HTTP 404) — Plan no encontrado'),
       'B6: contexto ilegible → motivo con el HTTP y sin gastar en OpenAI', me)
    ok(t['correos'] and 'Tampoco se pudo marcar el plan como error' in t['correos'][0]['html'] and 'plan 9' in t['correos'][0]['asunto'],
       'B6: el correo avisa que tampoco se pudo marcar el error', [c['asunto'] for c in t['correos']])

    # 7. OpenAI caído; 8. WordPress caído al marcar el error.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}], 'Marcar error': [{'error': {'message': 'ECONNRESET'}}]},
                openai={'Redactar plan': [{'error': {'message': 'Rate limit reached'}}]})
    sin_error(t, 'B7')
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('El modelo no respondió: Rate limit reached'), 'B7: OpenAI caído → motivo legible', me)
    ok(t['correos'] and 'sin respuesta' in t['correos'][0]['html'] and '«generando»' in t['correos'][0]['html'],
       'B8: si WordPress tampoco responde, el correo dice que el plan puede seguir en «generando»')
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}], 'Marcar error': [MARCADO]},
                openai={'Redactar plan': [{}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('El modelo no respondió (ejecución SIM-1)') and len(t['correos']) == 1,
       'B7b: OpenAI falla sin detalle ({}, el error queda fuera de json) → «error» legible y correo', me)

    # 9. Llave de más al final y 10. respuesta envuelta: se aceptan.
    for caso, contenido in (('B9 llave de más', json.dumps(PLAN_IA) + '}'), ('B10 envuelta', json.dumps({'plan': PLAN_IA}))):
        t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}], 'Guardar borrador': [GUARDADO_OK]},
                    openai={'Redactar plan': [{'content': contenido}]})
        ok(len(llamadas(t, 'Guardar borrador')) == 1 and not t['correos'], f'{caso}: se guarda el plan')

    # 11. WordPress guarda pero no confirma (5xx): error.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [{'statusCode': 500, 'body': {'message': 'Error crítico'}}], 'Marcar error': [MARCADO]},
                openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('WordPress no guardó el plan (HTTP 500) — Error crítico'), 'B11: un 500 al guardar → error legible', me)
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [{'statusCode': 500, 'body': {'ok': False, 'errores': ['No se pudo guardar el plan.'], 'avisos': []}}],
                'Marcar error': [MARCADO]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('WordPress no guardó el plan (HTTP 500): No se pudo guardar el plan.'),
       'B11b: el 500 propio de /borrador trae su motivo en «errores» y llega a la nota', me)

    # 12. Llegó tarde (el plan ya siguió: otra ejecución lo guardó, Luis lo destrabó y volvió al borrador, etc.):
    #     no se gasta en OpenAI, no se toca el plan y no se escribe. Con el plan en «error» sí se trabaja (WordPress lo acepta).
    for estado in ('borrador', 'aprobando', 'listo'):
        t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto(estado=estado)}]}, openai={})
        sin_error(t, f'B12 {estado}')
        ok(not t['openai'] and not llamadas(t, 'Guardar borrador') and not llamadas(t, 'Marcar error') and not t['correos'],
           f'B12: plan en «{estado}» → el borrador no se pide, no se guarda, no se marca error ni se escribe')
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto(estado='error')}],
                'Guardar borrador': [GUARDADO_OK]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    ok(len(llamadas(t, 'Guardar borrador')) == 1 and not t['correos'], 'B12: plan en «error» (Destrabar antes de que llegue n8n) → se genera y se guarda')
    # D5: en «error» CON contenido, WordPress responde 409 a un borrador (nunca pisa lo que Luis editó): ni se pide.
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto(estado='error', plan_actual=PLAN_GUARDADO)}]},
                openai={})
    sin_error(t, 'B12 error con contenido')
    ok(not t['openai'] and not llamadas(t, 'Guardar borrador') and not llamadas(t, 'Marcar error') and not t['correos'],
       'B12: plan en «error» que ya tiene contenido → el borrador no se pide (WordPress respondería 409)')

    # 13. WordPress responde 409 al guardar («llegó tarde y no se aplica»): no se pasa a «error» un plan que ya siguió.
    tarde = {'statusCode': 409, 'body': {'ok': False, 'errores': ['El plan está en «aprobando»: este borrador llegó tarde y no se aplica.'], 'avisos': []}}
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [tarde]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    sin_error(t, 'B13')
    ok(len(llamadas(t, 'Guardar borrador')) == 1 and not llamadas(t, 'Marcar error') and not t['correos'],
       'B13: un borrador tardío (409) no pasa a «error» un plan que Luis ya está usando ni le escribe')

    # 14. WordPress guardó pero no pudo pedir la vista previa: correo a Luis (si no, esperaría el «borrador listo»).
    sin_vista = {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': ['No se pudo pedir la vista previa: HTTP 404']}}
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [sin_vista]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    sin_error(t, 'B14')
    ok(not llamadas(t, 'Marcar error') and len(t['correos']) == 1
       and t['correos'][0]['asunto'] == '⚠️ [PRUEBA] Sitio de la panadería · borrador del plan guardado sin vista previa'
       and 'Guardar y recalcular' in t['correos'][0]['html'] and 'automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in t['correos'][0]['html'],
       'B14: guardado sin vista previa → correo a Luis con «Guardar y recalcular», sin marcar error', [c['asunto'] for c in t['correos']])
    otro_aviso = {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': ['La IA mandó su propio bloque «Arranque»: se usó el fijo.']}}
    t = simular('plan-1-borrador.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': contexto()}],
                'Guardar borrador': [otro_aviso]}, openai={'Redactar plan': [{'content': json.dumps(PLAN_IA)}]})
    ok(not t['correos'], 'B14: otros avisos de WordPress no mandan correo (los muestra el panel)')


@seccion('cambios')
def prueba_cambios():
    from build_plan_2_cambios import PROMPT_CAMBIOS  # noqa: F401  (vuelve a escribir plan-2-cambios.json)
    revisar_workflow('plan-2-cambios.json', 'Plan de trabajo · 2 Cambios', 'plan-v1-cambios')
    ok('easypanel' not in json.dumps(cargar('plan-2-cambios.json')), '2 Cambios: no toca el renderer ni enlaza a *.easypanel.host')
    faltan = [x for x in TOPES_PROMPT if x not in PROMPT_CAMBIOS]
    ok(not faltan, 'PROMPT_CAMBIOS: dice los mismos topes que valida WordPress y pide no mandar el «Arranque»', faltan)
    ok('130 días hábiles' not in PROMPT_CAMBIOS, 'PROMPT_CAMBIOS: no le dice al modelo 130 días (el tope que ve es 126: el Arranque suma 4)')
    GUARDADO_OK = {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': []}}
    MARCADO = {'statusCode': 200, 'body': {'ok': True}}
    hook = {'id': 9, 'codigo': 'PRUEBAplan01'}
    ctx = contexto(estado='cambios', plan_actual=PLAN_GUARDADO, comentarios='Agrega la capacitación y cambia la foto del método.')

    # 1. Luis editó los días de «Diseño de la portada» (7, origen «luis»); el modelo los cambia a 2: vuelven a 7.
    resp = json.loads(json.dumps(PLAN_GUARDADO))
    del resp['cronograma']
    for _, _, a in actividades(resp):
        a.pop('desde', None)
        a.pop('hasta', None)
    actividad(resp, 'Diseño de la portada').update(dias_habiles=2, origen='ia')
    actividad(resp, 'Construcción del sitio').update(origen='luis', dias_habiles=12)
    resp['fases'][1]['bloques'][0]['actividades'].append(act('Capacitación del equipo', 'ambos', 1))
    resp['image_briefs'][0] = {'slide': 'metodo', 'prompt': 'baker opening the shutters of the bakery at dawn'}
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [GUARDADO_OK]},
                openai={'Aplicar cambios': [{'content': json.dumps(resp, ensure_ascii=False)}]})
    sin_error(t, 'C1')
    g = llamadas(t, 'Guardar borrador')
    ok(len(g) == 1 and g[0]['url'] == WP + '/plan/9/borrador' and g[0]['body']['origen'] == 'cambios', 'C1: POST /plan/9/borrador con origen «cambios»', g)
    plan = g[0]['body']['plan'] if g else PLAN_GUARDADO
    portada = actividad(plan, 'Diseño de la portada') or {}
    ok(portada.get('dias_habiles') == 7 and portada.get('origen') == 'luis',
       'C1: los días que Luis editó a mano no se pisan (7, «luis») aunque el modelo los cambie a 2', portada)
    # Lo que n8n MANDA; WordPress vuelve a marcar el origen al guardar (at_pt_respetar_dias_luis y
    # at_pt_marcar_ediciones(…, 'ia'), Tasks 2 y 7) con la misma regla.
    ok((actividad(plan, 'Construcción del sitio') or {}).get('origen') == 'ia',
       'C1: el modelo no puede marcar «luis»: la actividad de la tabla a la que le cambió los días va «ia» (revisar)')
    ok((actividad(plan, 'Diseño del catálogo') or {}).get('origen') == 'tabla', 'C1: una actividad de la tabla que no cambió va «tabla»')
    ok((actividad(plan, 'Capacitación del equipo') or {}).get('origen') == 'ia', 'C1: la actividad nueva va «ia»')
    ok(plan['image_briefs'][0]['prompt'] == 'baker opening the shutters of the bakery at dawn, ' + CIERRE
       and plan['image_briefs'][1] == PLAN_GUARDADO['image_briefs'][1], 'C1: la foto pedida cambia y la otra queda idéntica (mismo hash)')
    o = t['openai'].get('Aplicar cambios', [{}])[0]
    pedido = json.loads(o.get('user') or '{}')
    ok(o.get('temperature') == 0.2 and o.get('system') == PROMPT_CAMBIOS and pedido.get('comentarios') == ctx['comentarios'],
       'C1: gpt-4o a 0,2 con PROMPT_CAMBIOS y los comentarios de Luis')
    pa = pedido.get('plan_actual') or {}
    ok('cronograma' not in pa and all('desde' not in a and 'hasta' not in a for _, _, a in actividades(pa)) and pa.get('fases'),
       'C1: el modelo recibe el plan sin fechas (las recalcula WordPress)')
    ok('Arranque' not in [b['nombre'] for f in pa.get('fases', []) for b in f['bloques']]
       and (pedido.get('contrato') or {}).get('garantia_meses') == 6,
       'C1: el modelo no recibe el bloque «Arranque» (D12) y sí los meses de garantía del contrato (D7)')
    ok(plan['fases'][0]['bloques'][0] == PLAN_GUARDADO['fases'][0]['bloques'][0],
       'C1: a WordPress el «Arranque» vuelve tal como estaba guardado', plan['fases'][0]['bloques'][0])
    ok(pedido.get('slides_foto') == NUEVAS and pedido.get('rubro') == '[PRUEBA] Panadería',
       'C1: con propuesta, el modelo solo puede describir láminas nuevas, del rubro del cliente')

    # 2. Sin comentarios: no se llama al modelo y el plan guardado vuelve tal cual.
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': dict(ctx, comentarios='  ')}],
                'Guardar borrador': [GUARDADO_OK]}, openai={})
    sin_error(t, 'C2')
    g = llamadas(t, 'Guardar borrador')
    ok(not t['openai'] and g and g[0]['body']['plan'] == PLAN_GUARDADO, 'C2: sin comentarios, sin OpenAI, y el plan vuelve idéntico')

    # 3. Respuesta parcial (solo fases): lo demás se conserva.
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [GUARDADO_OK]},
                openai={'Aplicar cambios': [{'content': json.dumps({'fases': resp['fases']}, ensure_ascii=False)}]})
    g = llamadas(t, 'Guardar borrador')
    p3 = g[0]['body']['plan'] if g else {}
    ok(p3.get('image_briefs') == PLAN_GUARDADO['image_briefs'] and p3.get('hitos') == PLAN_GUARDADO['hitos'],
       'C3: lo que el modelo omite se conserva del plan guardado')

    # 4. Una nota suelta, 5. sin plan guardado, 6. respuesta cortada, 7. envuelta, 8. vacía.
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Marcar error': [MARCADO]},
                openai={'Aplicar cambios': [{'content': '{"nota": "ya está listo"}'}]})
    me = llamadas(t, 'Marcar error')
    ok(me and 'claves que no son del plan: nota' in me[0]['body']['nota'] and not llamadas(t, 'Guardar borrador'), 'C4: una nota no pasa como cambio', me)
    ok(t['correos'] and t['correos'][0]['asunto'].endswith('no se pudieron aplicar los cambios al plan') and 'Volver al borrador' in t['correos'][0]['html'],
       'C4: correo de cambios con el siguiente paso')
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': dict(ctx, plan_actual=None)}], 'Marcar error': [MARCADO]}, openai={})
    me = llamadas(t, 'Marcar error')
    ok(not t['openai'] and me and me[0]['body']['nota'].startswith('El plan no tiene un borrador guardado al que aplicarle cambios'),
       'C5: sin plan guardado → error, sin gastar en OpenAI', me)
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Marcar error': [MARCADO]},
                openai={'Aplicar cambios': [{'content': json.dumps(resp)[:500]}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('La respuesta del modelo no es un JSON válido'), 'C6: respuesta cortada → error', me)
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [GUARDADO_OK]},
                openai={'Aplicar cambios': [{'content': json.dumps({'comentarios': 'x', 'plan_actual': resp})}]})
    ok(len(llamadas(t, 'Guardar borrador')) == 1, 'C7: {"plan_actual": …, "comentarios": …} se desenvuelve y se guarda')
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Marcar error': [MARCADO]},
                openai={'Aplicar cambios': [{'content': '{}'}]})
    me = llamadas(t, 'Marcar error')
    ok(me and 'no trae ninguna parte del plan' in me[0]['body']['nota'], 'C8: {} → error', me)

    # 9. Respuesta parcial: solo la fase que cambió y solo la foto pedida → a WordPress va el plan entero.
    impl = json.loads(json.dumps(resp['fases'][1]))
    parcial = {'fases': [impl], 'image_briefs': [{'slide': 'metodo', 'prompt': 'baker opening the shutters of the bakery at dawn'}]}
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [GUARDADO_OK]},
                openai={'Aplicar cambios': [{'content': json.dumps(parcial, ensure_ascii=False)}]})
    g = llamadas(t, 'Guardar borrador')
    p9 = g[0]['body']['plan'] if g else {'fases': []}
    ok([f['clave'] for f in p9['fases']] == ['diseno_desarrollo', 'implementacion', 'soporte']
       and (actividad(p9, 'Diseño de la portada') or {}).get('dias_habiles') == 7 and actividad(p9, 'Capacitación del equipo'),
       'C9: si el modelo devuelve solo la fase que cambió, las otras fases (con los días de Luis) no se pierden', [f.get('clave') for f in p9['fases']])
    ok([b['slide'] for b in p9.get('image_briefs', [])] == ['metodo', 'gantt'] and p9['image_briefs'][1] == PLAN_GUARDADO['image_briefs'][1],
       'C9: si el modelo devuelve solo la foto pedida, las demás quedan idénticas')

    # 10. Llegó tarde: el plan ya no está en «cambios» (Luis lo destrabó y volvió al borrador, u otra ejecución lo guardó).
    for estado in ('borrador', 'aprobando'):
        t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': dict(ctx, estado=estado)}]}, openai={})
        sin_error(t, f'C10 {estado}')
        ok(not t['openai'] and not llamadas(t, 'Guardar borrador') and not llamadas(t, 'Marcar error') and not t['correos'],
           f'C10: plan en «{estado}» → no se piden cambios, no se guarda, no se marca error ni se escribe')
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': dict(ctx, estado='error')}],
                'Guardar borrador': [GUARDADO_OK]}, openai={'Aplicar cambios': [{'content': json.dumps(resp, ensure_ascii=False)}]})
    ok(len(llamadas(t, 'Guardar borrador')) == 1, 'C10: plan en «error» → los cambios se aplican (WordPress los acepta)')
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': dict(ctx, estado='generando', plan_actual=None)}]}, openai={})
    ok(not llamadas(t, 'Marcar error') and not t['correos'], 'C10: un plan que se está generando no se pasa a «error» por un pedido de cambios viejo')

    # 11. WordPress responde 409 al guardar: no se pasa a «error» el plan que ya siguió.
    tarde = {'statusCode': 409, 'body': {'ok': False, 'errores': ['El plan está en «borrador»: este cambios llegó tarde y no se aplica.'], 'avisos': []}}
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [tarde]},
                openai={'Aplicar cambios': [{'content': json.dumps(resp, ensure_ascii=False)}]})
    sin_error(t, 'C11')
    ok(len(llamadas(t, 'Guardar borrador')) == 1 and not llamadas(t, 'Marcar error') and not t['correos'], 'C11: 409 → sin «error» ni correo')

    # 12. Guardado sin vista previa y 13. un 422 de WordPress en cambios.
    sin_vista = {'statusCode': 200, 'body': {'ok': True, 'errores': [], 'avisos': ['No se pudo pedir la vista previa: sin respuesta']}}
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [sin_vista]},
                openai={'Aplicar cambios': [{'content': json.dumps(resp, ensure_ascii=False)}]})
    ok(not llamadas(t, 'Marcar error') and len(t['correos']) == 1 and t['correos'][0]['asunto'].endswith('cambios del plan guardados sin vista previa'),
       'C12: cambios guardados sin vista previa → correo a Luis', [c['asunto'] for c in t['correos']])
    rechazo = {'statusCode': 422, 'body': {'ok': False, 'errores': ['Días hábiles fuera de rango (1 a 60) en «Capacitación del equipo»: 0'], 'avisos': []}}
    t = simular('plan-2-cambios.json', webhook=hook, http={'Leer contexto': [{'statusCode': 200, 'body': ctx}], 'Guardar borrador': [rechazo],
                'Marcar error': [MARCADO]}, openai={'Aplicar cambios': [{'content': json.dumps(resp, ensure_ascii=False)}]})
    me = llamadas(t, 'Marcar error')
    ok(me and me[0]['body']['nota'].startswith('WordPress rechazó el plan: Días hábiles fuera de rango') and t['correos']
       and 'Volver al borrador' in t['correos'][0]['html'], 'C13: un 422 en cambios → «error» con el motivo de WordPress y correo', me)


# ---------------------------------------------------------------- secciones de la Task 14 («3 Render»)

BASE_R = 'https://n8n-propuesta-renderer.kchiba.easypanel.host'
UID_PROP = 'PRUEBAprop01'
VISTA = BASE_R + '/p/PRUEBAplan01/index.html'
PDF = BASE_R + '/p/PRUEBAplan01/presentation.pdf'
BRIEFS_NUEVAS = [brief(s, t) for s, t in (
    ('metodo', 'bakers preparing the counter before opening, warm morning light'),
    ('gantt', 'hands shaping loaves one by one on a floured table'),
    ('fase_1', 'flour, eggs and wooden tools laid out on a table'),
    ('fase_2', 'baker handing a warm loaf to the first customer of the day'),
    ('fase_3', 'baker calmly serving a customer in the afternoon'),
    ('necesitamos', 'hands gathering bread baskets on a counter'),
    ('reuniones', 'two bakers talking face to face in the bakery'),
    ('portal', 'owner resting with a cup of coffee in the bakery at dusk'))]
BRIEF_COVER = {'slide': 'cover', 'prompt': 'close-up of hands kneading bread dough, shallow depth of field, background completely blurred, ' + CIERRE}
BRIEF_CIERRE = brief('cierre', 'bakery team celebrating the end of the day')


def cuerpo_render(final=True, **cambios):
    """Cuerpo que arma WordPress (at_pt_armar_render) para el renderer; datos inventados."""
    b = {'document_type': 'plan', 'unique_id': 'PRUEBAplan01', 'draft': not final, 'company_name': '[PRUEBA] Panadería',
         'client_name': 'Cliente Prueba', 'proyecto': '[PRUEBA] Sitio de la panadería', 'fecha_firma_larga': '26 de septiembre de 2026',
         'fecha_inicio': '2026-09-28', 'fecha_fin': '2026-11-25', 'semanas': 9,
         'metodo': {'hechas': ['diagnostico', 'priorizacion'], 'actual': 'propuesta', 'proximas': ['diseno_desarrollo', 'implementacion', 'soporte']},
         'fases': PLAN_GUARDADO['fases'], 'cronograma': PLAN_GUARDADO['cronograma'], 'necesitamos_de_ti': ['Logo', 'Textos'],
         'reuniones': [{'nombre': 'Reunión de inicio', 'detalle': ''}], 'soporte': {'garantia_meses': 3, 'mensuales': []},
         'portal_url': '', 'agenda': {'whatsapp_url': 'https://wa.me/56900000000?text=prueba', 'web_url': ''},
         'image_briefs': ([BRIEF_COVER] + BRIEFS_NUEVAS + [BRIEF_CIERRE]) if final else [], 'images': {}}
    b.update(cambios)
    return b


def leido(render, uid=UID_PROP, estado=None, **extra):
    """Respuesta de GET /plan/{id}/render (Task 7, D10): cuerpo, código de la propuesta, ficha del CRM (5) y estado del
    plan («borrador» para una vista previa y «aprobando» para la versión final, si no se dice otro)."""
    if estado is None:
        estado = 'borrador' if render.get('draft') else 'aprobando'
    return [{'statusCode': 200, 'body': dict({'ok': True, 'render': render, 'propuesta_uid': uid, 'crm_cliente_id': 5,
                                              'estado': estado}, **extra)}]


def renderer_ok(pedidas=8, missing=(), sin_vista=False, error=None):
    """Salida del nodo «Render» (fullResponse + neverError): {statusCode, body}; {error} si se cortó la red."""
    if error:
        return {'error': {'message': error}}
    r = {'images': {'requested': pedidas, 'stored_local': pedidas - len(missing), 'kept_remote': [], 'missing': list(missing), 'reused': 0}}
    if not sin_vista:
        r.update(view_url=VISTA, pdf_url=PDF)
    return {'statusCode': 200, 'body': r, 'headers': {}}


def renderer_http(status, body):
    return {'statusCode': status, 'body': body, 'headers': {}}


RECHAZO_ESQUEMA = renderer_http(400, {'error': 'invalid payload', 'details': ['falta el objeto obligatorio: cronograma',
                                                                            'fases debe traer al menos una fase']})


MANIFEST_PROP = {'statusCode': 200, 'body': {'cover': {'file': 'cover.jpg', 'hash': 'a' * 64},
                                             'next_steps': {'file': 'next_steps.png', 'hash': 'b' * 64},
                                             'solution': {'file': 'solution.jpg', 'hash': 'c' * 64}}}
# Respuesta de POST /plan/{id}/vista (Task 7): {ok, estado}. La versión final completa deja el plan «listo».
VISTA_LISTO = [{'statusCode': 200, 'body': {'ok': True, 'estado': 'listo'}}]
VISTA_BORRADOR = [{'statusCode': 200, 'body': {'ok': True, 'estado': 'borrador'}}]
# Manifest del plan en el renderer (todas sus láminas guardadas) y GPT-4o que no ve texto: los usan los valores por
# defecto de sim() en la sección `render` para los nodos de la revisión de texto de las fotos (ciclo D, D18).
MANIFEST_PLAN = {'statusCode': 200, 'body': {s: {'file': s + '.jpg', 'hash': 'd' * 64} for s in TODAS}}
SIN_TEXTO = {'statusCode': 200, 'body': {'choices': [{'message': {'content': '{"con_texto": []}'}}]}}


@seccion('render_puras')
def prueba_render_puras():
    from plan_js import JS_RENDER, RENDERER_BASE
    ok(RENDERER_BASE == BASE_R, 'RENDERER_BASE es el renderer de Easypanel (solo para n8n, nunca en un correo)')
    prog = lib() + JS_RENDER + r"""
const R = {};
const render = DATOS.render;
const copia = JSON.stringify(render);
R.completo = reutilizarFotosPlan(render, DATOS.manifest, DATOS.base, 'PRUEBAprop01');
R.no_muta = JSON.stringify(render) === copia;
R.sin_uid = reutilizarFotosPlan(render, DATOS.manifest, DATOS.base, '');
R.uid_malo = reutilizarFotosPlan(render, DATOS.manifest, DATOS.base, '../x');
R.texto = reutilizarFotosPlan(render, JSON.stringify({ cover: { file: 'cover.webp', hash: 'x' } }), DATOS.base, 'PRUEBAprop01');
R.nulo = reutilizarFotosPlan(render, null, DATOS.base, 'PRUEBAprop01');
R.ilegible = reutilizarFotosPlan(render, '<html>404</html>', DATOS.base, 'PRUEBAprop01');
R.arreglo = reutilizarFotosPlan(render, [1, 2], DATOS.base, 'PRUEBAprop01');
R.malicioso = reutilizarFotosPlan(render, { cover: { file: '../../secreto.jpg' }, next_steps: { file: '.oculto' } }, DATOS.base, 'PRUEBAprop01');
R.base_mala = reutilizarFotosPlan(render, DATOS.manifest, 'javascript:alert(1)', 'PRUEBAprop01');
R.dada = reutilizarFotosPlan(Object.assign({}, render, { images: { cover: 'https://example.com/foto.jpg' } }), {}, DATOS.base, 'PRUEBAprop01');
R.render_nulo = reutilizarFotosPlan(null, DATOS.manifest, DATOS.base, 'PRUEBAprop01');
R.nombres = nombresLaminas(['gantt', 'necesitamos', 'otra']);
const L = (x) => leerRespuestaRender(x);
R.resp = {
  ok: L(DATOS.r_ok), falta: L(DATOS.r_falta), sin_vista: L(DATOS.r_sin_vista), esquema: L(DATOS.r_400),
  caido: L(DATOS.r_502), red: L({ error: { message: 'socket hang up' } }), suelto: L(DATOS.r_ok.body),
  texto: L({ statusCode: 400, body: JSON.stringify(DATOS.r_400.body) }), html: L({ statusCode: 503, body: '<html>Bad gateway</html>' }),
  clave: L({ statusCode: 401, body: { error: 'unauthorized' } }), nada: L(null),
};
R.sin_vista_previa = ESTADOS_SIN_VISTA_PREVIA;
console.log(JSON.stringify(R));
"""
    r, err = node_js(prog, {'render': cuerpo_render(True), 'manifest': MANIFEST_PROP['body'], 'base': BASE_R,
                            'r_ok': renderer_ok(8), 'r_falta': renderer_ok(8, ['gantt']), 'r_sin_vista': renderer_ok(8, sin_vista=True),
                            'r_400': RECHAZO_ESQUEMA, 'r_502': renderer_http(502, {'error': 'render failed', 'details': 'Playwright timeout'})})
    ok(r is not None, 'render_puras: el JavaScript corre con node', err)
    if r is None:
        return
    c = r['completo']
    ok(c['render']['images'] == {'cover': BASE_R + '/p/PRUEBAprop01/img/cover.jpg', 'cierre': BASE_R + '/p/PRUEBAprop01/img/next_steps.png'},
       'reutilizarFotosPlan: portada ← cover y cierre ← next_steps de la propuesta, con URL absoluta', c['render']['images'])
    ok([b['slide'] for b in c['render']['image_briefs']] == NUEVAS, 'reutilizarFotosPlan: portada y cierre salen de las fotos a pagar')
    ok(c['reutilizadas'] == ['cover', 'cierre'] and c['sin_foto'] == [], 'reutilizarFotosPlan: informa las reutilizadas')
    ok(r['no_muta'], 'reutilizarFotosPlan: no cambia el objeto que recibe')
    ok(r['sin_uid']['render']['image_briefs'] == cuerpo_render(True)['image_briefs'] and r['sin_uid']['render']['images'] == {}
       and r['sin_uid']['reutilizadas'] == [] and r['sin_uid']['sin_foto'] == [],
       'reutilizarFotosPlan: sin propuesta no toca nada (portada y cierre piden foto nueva)')
    ok(r['uid_malo']['reutilizadas'] == [] and r['uid_malo']['render']['images'] == {}, 'reutilizarFotosPlan: un uid raro se trata como sin propuesta')
    ok(r['texto']['reutilizadas'] == ['cover'] and r['texto']['sin_foto'] == ['cierre']
       and r['texto']['render']['images']['cover'].endswith('/img/cover.webp'), 'reutilizarFotosPlan: lee el manifest aunque llegue como texto')
    for caso in ('nulo', 'ilegible', 'arreglo', 'base_mala'):
        x = r[caso]
        ok(x['reutilizadas'] == [] and x['sin_foto'] == ['cover', 'cierre'] and x['render']['images'] == {}
           and [b['slide'] for b in x['render']['image_briefs']] == NUEVAS,
           f'reutilizarFotosPlan: manifest o base {caso} → sin foto y sin pagar portada ni cierre', x['sin_foto'])
    m = r['malicioso']
    ok(m['reutilizadas'] == [] and m['render']['images'] == {} and m['sin_foto'] == ['cover', 'cierre'],
       'reutilizarFotosPlan: nombres de archivo con «..», barras u ocultos no se usan', m)
    ok(r['dada']['render']['images'] == {'cover': 'https://example.com/foto.jpg'} and r['dada']['sin_foto'] == ['cierre'],
       'reutilizarFotosPlan: una imagen que ya trae el cuerpo se respeta y no se avisa como faltante')
    ok(r['render_nulo']['render']['images']['cover'].endswith('/cover.jpg'), 'reutilizarFotosPlan: un cuerpo nulo no lanza')
    ok(r['nombres'] == 'carta Gantt, qué necesitamos de ti, otra', 'nombresLaminas: nombres legibles para las notas', r['nombres'])
    ok(r['sin_vista_previa'] == ['aprobando', 'listo', 'enviado'], 'ESTADOS_SIN_VISTA_PREVIA: aprobando, listo y enviado (D10)')
    x = r['resp']
    ok(x['ok']['status'] == 200 and x['ok']['view_url'] == VISTA and x['ok']['pdf_url'] == PDF and x['ok']['missing'] == []
       and x['ok']['reintentable'] is False, 'leerRespuestaRender: 200 completo → enlaces y nada que reintentar', x['ok'])
    ok(x['falta']['missing'] == ['gantt'] and x['falta']['reintentable'] is True, 'leerRespuestaRender: 200 con fotos faltantes → se reintenta')
    ok(x['sin_vista']['view_url'] == '' and x['sin_vista']['reintentable'] is True, 'leerRespuestaRender: 200 sin presentación → se reintenta')
    ok(x['esquema']['status'] == 400 and x['esquema']['reintentable'] is False
       and x['esquema']['detalles'] == ['falta el objeto obligatorio: cronograma', 'fases debe traer al menos una fase'],
       'leerRespuestaRender: 400 del esquema con details → no se reintenta y trae los motivos (D11)', x['esquema'])
    ok(x['caido']['status'] == 502 and x['caido']['reintentable'] is True and x['caido']['detalles'] == ['Playwright timeout'],
       'leerRespuestaRender: 502 con details en texto → se reintenta y trae el motivo', x['caido'])
    ok(x['red']['status'] == 0 and x['red']['fallo'] == 'socket hang up' and x['red']['reintentable'] is True,
       'leerRespuestaRender: error de red o tiempo vencido → se reintenta', x['red'])
    ok(x['suelto']['status'] == 200 and x['suelto']['view_url'] == VISTA, 'leerRespuestaRender: acepta el cuerpo suelto (sin fullResponse)')
    ok(x['texto']['detalles'] == x['esquema']['detalles'] and x['texto']['reintentable'] is False,
       'leerRespuestaRender: un cuerpo que llega como texto JSON se lee igual')
    ok(x['html']['status'] == 503 and x['html']['reintentable'] is True and x['html']['detalles'] == [] and x['html']['cuerpo'].get('error', '').startswith('<html>'),
       'leerRespuestaRender: un 503 con HTML → se reintenta, sin romperse', x['html'])
    ok(x['clave']['reintentable'] is False and x['nada']['status'] == 0 and x['nada']['reintentable'] is True,
       'leerRespuestaRender: un 401 no se reintenta; una salida vacía cuenta como falla de red')


@seccion('correos_render')
def prueba_correos_render():
    from correos_plan import correo_render
    codigo = correo_render()

    def correr(v, g):
        js = ("const $ = (n) => ({ isExecuted: n in DATOS, first: () => ({ json: DATOS[n] }) });\n"
              "const salida = (function () {\n" + codigo + "\n})();\nconsole.log(JSON.stringify(salida[0].json));")
        return node_js(js, {'Resultado del render': v, 'Guardar vista': g})

    base = {'id': 9, 'crm': 5, 'exec': '888', 'proyecto': '[PRUEBA] Sitio <i>panadería</i>', 'view_url': VISTA, 'pdf_url': PDF,
            'faltan': [], 'problemas': [], 'avisos': [], 'resumen': []}
    g_listo = {'statusCode': 200, 'body': {'ok': True, 'estado': 'listo'}}
    g_error = {'statusCode': 200, 'body': {'ok': True, 'estado': 'error'}}
    g_borr = {'statusCode': 200, 'body': {'ok': True, 'estado': 'borrador'}}
    p = '[PRUEBA] Sitio <i>panadería</i>'
    sin_guardar = '⚠️ ' + p + ' · el resultado del plan no quedó guardado en WordPress'
    # (nombre, resultado, respuesta de /vista, enviar, asunto, frases que debe traer, frases que no debe traer)
    casos = [
        ('final ok', dict(base, modo='final', aviso=False, ok=True,
                          resumen=['Presentación y PDF generados', '8 de 8 fotos nuevas guardadas junto al plan',
                                   'Con las fotos de la propuesta, sin costo: portada, cierre'],
                          avisos=['la propuesta no tiene foto guardada para cierre: esas láminas van sin foto']), g_listo,
         True, '✅ ' + p + ' · plan de trabajo listo',
         ['Con las fotos de la propuesta, sin costo', '⚠️ la propuesta no tiene foto', '>Listo<', 'quedó <strong>listo</strong>'], ['«aprobando»']),
        ('final con problemas', dict(base, modo='final', aviso=False, ok=False,
                                     problemas=['fotos que no se generaron tras 3 intentos: carta Gantt']), g_error,
         True, '⚠️ ' + p + ' · la versión final del plan tiene problemas', ['carta Gantt', 'no se vuelven a pagar', 'Con problemas'], ['>Listo<']),
        ('final ok con el plan en error', dict(base, modo='final', aviso=False, ok=True, resumen=['Presentación y PDF generados']), g_error,
         True, '⚠️ ' + p + ' · la versión final salió, pero el plan quedó en «error»',
         ['dejó el plan en «error»', 'no se vuelven a pagar', 'Con problemas'], ['quedó <strong>listo</strong>', '>Listo<']),
        ('borrador con aviso', dict(base, modo='draft', aviso=True, ok=True, resumen=['Vista previa generada (sin fotos)']), g_borr,
         True, p + ' · borrador del plan listo para revisar', ['solo al «Aprobar»', 'Borrador'], []),
        ('borrador sin aviso', dict(base, modo='draft', aviso=False, ok=True), g_borr, False, None, [], []),
        ('borrador que falló', dict(base, modo='draft', aviso=False, ok=False,
                                    problemas=['el renderer no devolvió la presentación (timeout)']), g_borr,
         True, '⚠️ ' + p + ' · la vista previa del plan no se pudo generar', ['Guardar y recalcular', 'timeout'], []),
        ('final sin guardar en WordPress', dict(base, modo='final', aviso=False, ok=True), {'statusCode': 500, 'body': {}},
         True, sin_guardar, ['No se pudo guardar el resultado en WordPress', 'HTTP 500', '«aprobando»', 'Destrabar', 'Con problemas',
                             'La presentación salió bien'], ['quedó <strong>listo</strong>', '>Listo<']),
        ('borrador sin guardar en WordPress', dict(base, modo='draft', aviso=False, ok=True), {'error': {'message': 'ECONNRESET'}},
         True, sin_guardar, ['No se pudo guardar el resultado en WordPress', 'sin respuesta', 'Con problemas'], ['>Borrador<']),
    ]
    for nombre, v, g, enviar, asunto, frases, ausentes in casos:
        r, err = correr(v, g)
        ok(r is not None, f'correo render {nombre}: el código corre', err)
        if r is None:
            continue
        ok(r['enviar'] is enviar, f'correo render {nombre}: enviar = {enviar}', r['enviar'])
        if asunto:
            ok(r['asunto'] == asunto, f'correo render {nombre}: asunto', r['asunto'])
        h = r['html']
        ok('easypanel' not in h, f'correo render {nombre}: no enlaza a *.easypanel.host (ni la vista ni el PDF)')
        ok('automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in h and 'Plan de trabajo · aviso interno' in h,
           f'correo render {nombre}: botón a la pestaña del plan y rótulo del plan')
        ok('&lt;i&gt;panadería&lt;/i&gt;' in h and '<i>panadería</i>' not in h, f'correo render {nombre}: escapa el proyecto')
        faltan = [f for f in frases if f not in h]
        ok(not faltan, f'correo render {nombre}: trae lo que debe decir', faltan)
        sobran = [f for f in ausentes if f in h]
        ok(not sobran, f'correo render {nombre}: no dice lo que no corresponde', sobran)
    r, _ = correr(dict(base, modo='final', aviso=False, ok=True, proyecto='x'), g_listo)
    ok(r and '«aprobando»' not in r['html'], 'correo render: si WordPress guardó, no habla de un plan trabado')


@seccion('render')
def prueba_render():
    from build_plan_3_render import MAX_RENDERS  # vuelve a escribir plan-3-render.json
    wf = revisar_workflow('plan-3-render.json', 'Plan de trabajo · 3 Render', 'plan-v1-render')
    rn = next(n for n in wf['nodes'] if n['name'] == 'Render')
    ok(rn['credentials']['httpHeaderAuth']['id'] == 'fj2orzbsjlnHaiLd' and rn['parameters']['url'] == BASE_R + '/render'
       and rn['parameters']['options']['timeout'] == 290000 and rn.get('onError') == 'continueRegularOutput'
       and rn['parameters']['options']['response']['response'] == {'fullResponse': True, 'neverError': True},
       'plan-3-render.json: el render va con la clave X-AT-Render-Key, 290 s, respuesta completa (código HTTP) y sin cortar el flujo')
    fp = next(n for n in wf['nodes'] if n['name'] == 'Fotos de la propuesta')
    ok('credentials' not in fp and fp.get('onError') == 'continueRegularOutput',
       'plan-3-render.json: el manifest público se lee sin credenciales y nunca corta el flujo')
    ok(MAX_RENDERS == 3, 'plan-3-render.json: hasta 3 renders en total')
    correo = next(n for n in wf['nodes'] if n['name'] == 'Armar correo')['parameters']['jsCode']
    ok('easypanel' not in correo, 'plan-3-render.json: el código del correo no nombra *.easypanel.host')

    def sim(webhook, **http):
        http.setdefault('Guardar vista', VISTA_LISTO if webhook.get('modo') == 'final' else VISTA_BORRADOR)
        # Revisión de texto de las fotos (ciclo D de esta tarea, D18; hasta el Step 19 estos nodos no existen y no se
        # usan): sin manifest previo del plan, las fotos guardadas y GPT-4o sin texto. Estos casos miran el render; la
        # revisión la prueba probar_revision_plan.py.
        http.setdefault('Fotos previas del plan', [{'statusCode': 404, 'body': 'Not Found'}])
        http.setdefault('Leer fotos del plan', [MANIFEST_PLAN])
        http.setdefault('Buscar texto en fotos', [SIN_TEXTO])
        return simular('plan-3-render.json', webhook=webhook, http=http, openai={})

    # R1. Vista previa con aviso: sin fotos aunque WordPress mande alguna, un solo render, correo «borrador listo».
    t = sim({'id': 9, 'modo': 'draft', 'aviso': True}, **{'Leer render': leido(cuerpo_render(False, image_briefs=[BRIEFS_NUEVAS[0]])),
                                                         'Render': [renderer_ok(0)]})
    sin_error(t, 'R1')
    ok(llamadas(t, 'Leer render')[0]['url'] == WP + '/plan/9/render&modo=draft',
       'R1: GET /plan/9/render con &modo=draft (la base ya trae ?rest_route=)', llamadas(t, 'Leer render'))
    rc = llamadas(t, 'Render')
    ok(len(rc) == 1 and rc[0]['body']['draft'] is True and rc[0]['body']['image_briefs'] == [] and rc[0]['body']['unique_id'] == 'PRUEBAplan01',
       'R1: un render, draft, sin fotos y con el código del plan', rc)
    ok(not llamadas(t, 'Fotos de la propuesta'), 'R1: la vista previa no lee las fotos de la propuesta')
    gv = llamadas(t, 'Guardar vista')
    ok(gv and gv[0]['url'] == WP + '/plan/9/vista' and {k: gv[0]['body'][k] for k in ('modo', 'ok', 'view_url', 'pdf_url', 'faltan')}
       == {'modo': 'draft', 'ok': True, 'view_url': VISTA, 'pdf_url': PDF, 'faltan': []} and gv[0]['body']['nota'].startswith('Vista previa lista'),
       'R1: POST /plan/9/vista {modo: draft, ok: true, …}', gv)
    ok(len(t['correos']) == 1 and t['correos'][0]['asunto'] == '[PRUEBA] Sitio de la panadería · borrador del plan listo para revisar'
       and 'easypanel' not in t['correos'][0]['html'], 'R1: correo «borrador listo» sin enlaces a *.easypanel.host',
       [c['asunto'] for c in t['correos']])
    ok(t['correos'] and 'automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in t['correos'][0]['html'],
       'R1: el botón abre la pestaña del plan en la ficha del CRM (crm_cliente_id de /render)')

    # R2. Vista previa sin aviso («Guardar y recalcular»): sin correo. Con aviso 'true' como texto: con correo.
    t = sim({'id': 9, 'modo': 'draft', 'aviso': False}, **{'Leer render': leido(cuerpo_render(False)), 'Render': [renderer_ok(0)]})
    ok(not t['correos'] and llamadas(t, 'Guardar vista'), 'R2: vista previa sin aviso → se guarda y no se manda correo')
    t = sim({'id': 9, 'modo': 'draft', 'aviso': 'true'}, **{'Leer render': leido(cuerpo_render(False)), 'Render': [renderer_ok(0)]})
    ok(len(t['correos']) == 1, 'R2: aviso "true" como texto también avisa')

    # R3. La vista previa falla: no se reintenta, queda la nota y se avisa.
    t = sim({'id': 9, 'modo': 'draft', 'aviso': False}, **{'Leer render': leido(cuerpo_render(False)),
                                                          'Render': [renderer_ok(error='connect ECONNREFUSED')]})
    sin_error(t, 'R3')
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 1 and gv and gv[0]['body']['ok'] is False
       and gv[0]['body']['nota'].startswith('el renderer no devolvió la presentación (connect ECONNREFUSED)'), 'R3: un solo intento y nota con el motivo', gv)
    ok(t['correos'] and t['correos'][0]['asunto'].endswith('la vista previa del plan no se pudo generar'), 'R3: correo con el problema')

    # R4. Versión final con propuesta: portada y cierre reutilizan sus fotos; se pagan solo las 8 nuevas.
    render_final = cuerpo_render(True, image_briefs=[BRIEF_COVER] + BRIEFS_NUEVAS + [BRIEFS_NUEVAS[1], brief('challenge', 'x'), BRIEF_CIERRE])
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [renderer_ok(8)]})
    sin_error(t, 'R4')
    ok(llamadas(t, 'Leer render')[0]['url'].endswith('/plan/9/render&modo=final'), 'R4: GET con &modo=final')
    fpc = llamadas(t, 'Fotos de la propuesta')
    ok(fpc and fpc[0]['url'] == BASE_R + '/p/PRUEBAprop01/img/manifest.json', 'R4: lee el manifest de fotos de la propuesta', fpc)
    rc = llamadas(t, 'Render')
    body = rc[0]['body'] if rc else {}
    ok(len(rc) == 1 and body.get('draft') is False and body.get('images') == {'cover': BASE_R + '/p/PRUEBAprop01/img/cover.jpg',
                                                                                'cierre': BASE_R + '/p/PRUEBAprop01/img/next_steps.png'},
       'R4: un render final con portada y cierre desde la propuesta', body.get('images'))
    ok(body.get('image_briefs') == BRIEFS_NUEVAS,
       'R4: se piden solo las 8 fotos nuevas, sin repetidas ni láminas ajenas, con la misma descripción (mismo hash)')
    ok(all(body.get(k) == render_final[k] for k in ('document_type', 'unique_id', 'fases', 'cronograma', 'agenda', 'portal_url', 'client_name', 'metodo')),
       'R4: el resto del cuerpo de WordPress llega intacto al renderer (también portal_url vacío: la lámina «Sigue tu proyecto» sale sin enlace)')
    gv = llamadas(t, 'Guardar vista')
    ok(gv and gv[0]['body']['modo'] == 'final' and gv[0]['body']['ok'] is True and gv[0]['body']['faltan'] == []
       and gv[0]['body']['nota'].startswith('Versión final verificada: Presentación y PDF generados · 8 de 8 fotos nuevas guardadas junto al plan'
                                            ' · Con las fotos de la propuesta, sin costo: portada, cierre · 1 render'),
       'R4: POST /vista final ok (WordPress pasa el plan a «listo»)', gv)
    ok(t['correos'] and t['correos'][0]['asunto'] == '✅ [PRUEBA] Sitio de la panadería · plan de trabajo listo'
       and 'automatiza-crm-ficha&amp;id=5&amp;pt=9#tab-plan' in t['correos'][0]['html'], 'R4: correo «plan de trabajo listo» con el botón a la ficha')

    # R5. Faltan fotos en los tres intentos: 3 renders con el mismo cuerpo y el plan pasa a «error».
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [renderer_ok(8, ['gantt', 'fase_2']), renderer_ok(8, ['gantt'])]})
    sin_error(t, 'R5')
    rc = llamadas(t, 'Render')
    ok(len(rc) == 3 and rc[0]['body'] == rc[1]['body'] == rc[2]['body'],
       'R5: tres renders con el mismo cuerpo (el renderer reutiliza lo ya pagado)', len(rc))
    gv = llamadas(t, 'Guardar vista')
    ok(gv and gv[0]['body']['ok'] is False and gv[0]['body']['faltan'] == ['gantt']
       and gv[0]['body']['nota'].startswith('fotos que no se generaron tras 3 intentos: carta Gantt') and '(ejecución SIM-1)' in gv[0]['body']['nota'],
       'R5: POST /vista final sin ok, con lo que falta (WordPress lo pasa a «error»)', gv)
    ok(t['correos'] and t['correos'][0]['asunto'].endswith('la versión final del plan tiene problemas'), 'R5: correo con problemas')

    # R6. La segunda pasada completa las fotos: queda listo con 2 renders.
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [renderer_ok(8, ['gantt']), renderer_ok(8)]})
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 2 and gv and gv[0]['body']['ok'] is True and '2 renders' in gv[0]['body']['nota'],
       'R6: se reintenta solo hasta que salen todas', gv)

    # R7. La propuesta no tiene manifest (404): portada y cierre sin foto, sin pagarlas, y el plan igual queda listo.
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final),
                                                          'Fotos de la propuesta': [{'statusCode': 404, 'body': 'Not Found'}],
                                                          'Render': [renderer_ok(8)]})
    rc = llamadas(t, 'Render')
    gv = llamadas(t, 'Guardar vista')
    ok(rc and rc[0]['body']['images'] == {} and [b['slide'] for b in rc[0]['body']['image_briefs']] == NUEVAS,
       'R7: sin manifest no se reutiliza ni se paga portada o cierre')
    ok(gv and gv[0]['body']['ok'] is True and 'la propuesta no tiene foto guardada para portada, cierre' in gv[0]['body']['nota'],
       'R7: queda listo con el aviso en la nota', gv)

    # R8. Contrato sin propuesta: portada y cierre piden foto nueva; no se lee ningún manifest.
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(cuerpo_render(True), uid=''), 'Render': [renderer_ok(10)]})
    rc = llamadas(t, 'Render')
    ok(not llamadas(t, 'Fotos de la propuesta') and rc and [b['slide'] for b in rc[0]['body']['image_briefs']] == TODAS
       and rc[0]['body']['images'] == {}, 'R8: sin propuesta se piden las 10 fotos (portada y cierre incluidas)')
    gv = llamadas(t, 'Guardar vista')
    ok(gv and gv[0]['body']['ok'] is True and '10 de 10 fotos nuevas' in gv[0]['body']['nota'], 'R8: listo con 10 fotos nuevas', gv)

    # R9. WordPress no entrega el cuerpo: no se renderiza y la versión final queda en «error» (nunca en «aprobando»).
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': [{'statusCode': 404, 'body': {'code': 'x', 'message': 'Plan no encontrado'}}]})
    sin_error(t, 'R9')
    gv = llamadas(t, 'Guardar vista')
    ok(not llamadas(t, 'Render') and gv and gv[0]['url'] == WP + '/plan/9/vista' and gv[0]['body']['modo'] == 'final'
       and gv[0]['body']['ok'] is False and gv[0]['body']['nota'].startswith('No se pudo leer el plan en WordPress (HTTP 404) — Plan no encontrado'),
       'R9: POST /vista final sin ok', gv)
    ok(t['correos'] and 'plan 9' in t['correos'][0]['asunto'], 'R9: correo aunque no se sepa el nombre del proyecto')

    # R10. Todo salió bien pero WordPress no guardó el resultado: el correo lo dice y sugiere «Destrabar».
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [renderer_ok(8)], 'Guardar vista': [{'statusCode': 500, 'body': {}}]})
    h = t['correos'][0]['html'] if t['correos'] else ''
    ok(t['correos'] and t['correos'][0]['asunto'].startswith('⚠️') and 'No se pudo guardar el resultado en WordPress' in h and 'Destrabar' in h,
       'R10: si WordPress no guarda, el correo avisa que el plan puede seguir en «aprobando»')

    # R11. El renderer nunca responde: 3 intentos y «error».
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [renderer_ok(error='timeout of 290000ms exceeded')]})
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 3 and gv and gv[0]['body']['ok'] is False
       and gv[0]['body']['nota'].startswith('el renderer no devolvió la presentación (timeout of 290000ms exceeded) tras 3 intentos'),
       'R11: 3 intentos y error', gv)

    # R12. Modo desconocido: se trata como vista previa (nunca se pagan fotos por error).
    t = sim({'id': 9, 'modo': 'otro'}, **{'Leer render': leido(cuerpo_render(True), estado='borrador'), 'Render': [renderer_ok(0)]})
    rc = llamadas(t, 'Render')
    ok(llamadas(t, 'Leer render')[0]['url'].endswith('&modo=draft') and rc and rc[0]['body']['draft'] is True
       and rc[0]['body']['image_briefs'] == [], 'R12: modo desconocido → vista previa sin fotos')

    # R13 (D10). Vista previa atrasada: el plan ya está «aprobando», «listo» o «enviado» → no se dibuja ni se escribe.
    for estado in ('aprobando', 'listo', 'enviado'):
        t = sim({'id': 9, 'codigo': 'PRUEBAplan01', 'modo': 'draft', 'aviso': True},
                **{'Leer render': leido(cuerpo_render(False), estado=estado), 'Render': [renderer_ok(0)]})
        sin_error(t, f'R13 {estado}')
        ok(t['pasos'][-1] == '¿Toca renderizar?' and not llamadas(t, 'Render') and not llamadas(t, 'Guardar vista') and not t['correos'],
           f'R13: vista previa con el plan en «{estado}» → no toca el renderer (/p/<codigo>/ queda con la versión final), ni /vista, ni correo',
           t['pasos'])
    t = sim({'id': 9, 'modo': 'draft', 'aviso': False}, **{'Leer render': leido(cuerpo_render(False), estado='error'), 'Render': [renderer_ok(0)]})
    ok(len(llamadas(t, 'Render')) == 1 and llamadas(t, 'Guardar vista'), 'R13: con el plan en «error» la vista previa sí se dibuja (Volver al borrador)')
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final, estado='aprobando'),
                                                          'Fotos de la propuesta': [MANIFEST_PROP], 'Render': [renderer_ok(8)]})
    ok(len(llamadas(t, 'Render')) == 1, 'R13: la versión final con el plan «aprobando» se dibuja')

    # R14 (D11). El renderer rechaza el cuerpo (400 del esquema): no se reintenta y los motivos van a la nota.
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [RECHAZO_ESQUEMA, renderer_ok(8)]})
    sin_error(t, 'R14')
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 1 and gv and gv[0]['body']['ok'] is False
       and gv[0]['body']['nota'].startswith('el renderer rechazó el plan (HTTP 400: falta el objeto obligatorio: cronograma; '
                                            'fases debe traer al menos una fase)'),
       'R14: 400 con details → un solo render y los motivos en la nota (WordPress pasa el plan a «error»)', gv)
    ok(t['correos'] and 'falta el objeto obligatorio: cronograma' in t['correos'][0]['html'], 'R14: el correo trae los motivos del renderer')
    t = sim({'id': 9, 'modo': 'draft', 'aviso': False}, **{'Leer render': leido(cuerpo_render(False)), 'Render': [RECHAZO_ESQUEMA]})
    gv = llamadas(t, 'Guardar vista')
    ok(gv and gv[0]['body']['ok'] is False and 'falta el objeto obligatorio: cronograma' in gv[0]['body']['nota'] and t['correos'],
       'R14: en la vista previa el 400 también llega a la nota y al correo', gv)

    # R15 (D11). Un 5xx sí se reintenta: el segundo render sale bien; si los tres fallan, la nota trae el HTTP y el motivo.
    caido = renderer_http(502, {'error': 'render failed', 'details': 'Playwright timeout'})
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [caido, renderer_ok(8)]})
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 2 and gv and gv[0]['body']['ok'] is True and '2 renders' in gv[0]['body']['nota'],
       'R15: un 502 se reintenta y la segunda pasada deja el plan listo', gv)
    t = sim({'id': 9, 'modo': 'final', 'aviso': False}, **{'Leer render': leido(render_final), 'Fotos de la propuesta': [MANIFEST_PROP],
                                                          'Render': [caido]})
    gv = llamadas(t, 'Guardar vista')
    ok(len(llamadas(t, 'Render')) == 3 and gv and gv[0]['body']['ok'] is False
       and gv[0]['body']['nota'].startswith('el renderer no devolvió la presentación (HTTP 502: Playwright timeout) tras 3 intentos'),
       'R15: tres 502 → 3 intentos y la nota con el código y el motivo', gv)


# ==== Las tareas siguientes agregan sus secciones justo antes de esta línea ====

if __name__ == '__main__':
    pedidas = sys.argv[1:] or list(SECCIONES)
    desconocidas = [s for s in pedidas if s not in SECCIONES]
    if desconocidas:
        print('secciones desconocidas:', ', '.join(desconocidas), '— hay:', ', '.join(SECCIONES))
        sys.exit(2)
    for s in pedidas:
        print(f'== {s}')
        try:
            SECCIONES[s]()
        except Exception as e:  # una sección que lanza cuenta como falla y no oculta las demás
            ok(False, f'{s}: la sección lanzó {type(e).__name__}: {e}')
    print('TODO OK' if not fallas else f'{len(fallas)} FALLA(S)')
    sys.exit(1 if fallas else 0)
