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
                '130 días hábiles', '5 días hábiles de revisión por cada bloque con entrega', '10 hitos',
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
       '130 días hábiles, 10 hitos, claves) y pide no mandar el «Arranque»', faltan)
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
