"""Construye el workflow n8n «Plan de trabajo · 3 Render» y lo guarda en plan-3-render.json.

Diseño: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md; Task 14 del plan de implementación (Etapa 1).
Lo llama WordPress (at_pt_pedir_render) con {id, codigo, modo: 'draft'|'final', aviso} y X-AT-Secret:
- draft: vista previa SIN fotos (tras el borrador, los cambios o «Guardar y recalcular»). No cambia el estado del
  plan; si falla, solo deja la nota. Correo a Luis solo si WordPress pidió aviso o si algo falló. Si GET /render dice
  que el plan ya está «aprobando», «listo» o «enviado», no la dibuja (pisaría la versión final): termina sin más.
- final: al «Aprobar» (plan en «aprobando»). Portada y cierre reutilizan las fotos de la propuesta (sin costo); las
  láminas nuevas piden fotos nuevas (gasto: US$0,0032 c/u de lista, el mismo cálculo que muestra el panel antes de
  aprobar). Si faltan fotos, no hubo presentación, se cortó la red o el renderer dio un 5xx, vuelve a llamarlo (hasta
  3 renders en total; el renderer reutiliza las fotos ya guardadas con el mismo prompt y no las vuelve a cobrar). Un
  4xx (el 400 del esquema) no se reintenta y sus motivos van a la nota. Termina SIEMPRE con POST /vista:
  WordPress pasa el plan a «listo» o a «error»; nunca queda trabado en «aprobando». Siempre le escribe a Luis.
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os, sys

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'propuestas-v3'))
from fotos_guard import JS_LIMPIAR_FOTOS  # noqa: E402
from json_guard import JS_LEER_JSON  # noqa: E402
from correos_plan import correo_render  # noqa: E402
from plan_js import JS_PLAN, JS_RENDER, RENDERER_BASE  # noqa: E402

CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
CRED_RENDER = {'httpHeaderAuth': {'id': 'fj2orzbsjlnHaiLd', 'name': 'X-AT-Render-Key'}}  # clave de /render del renderer
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
RENDERER = RENDERER_BASE + '/render'
LUIS = 'lmgm.0303@gmail.com'
FULL = {'response': {'response': {'fullResponse': True, 'neverError': True}}}
MAX_RENDERS = 3
LIB = (JS_LEER_JSON + '\n' + JS_LIMPIAR_FOTOS + '\n' + JS_PLAN + '\n' + JS_RENDER + '\n'
       + 'const RENDERER_BASE = ' + json.dumps(RENDERER_BASE) + ';\n')

JS_AVISO = r"""const avisoPedido = (v) => v === true || v === 1 || v === '1' || v === 'true';
"""

CODE_PREPARAR = r"""// Arma el cuerpo que va al renderer. Nunca lanza.
const hook = $('Webhook').first().json.body || {};
const lr = ($('Leer render').first().json || {}).body || {};
const id = parseInt(hook.id, 10) || 0;
const modo = hook.modo === 'final' ? 'final' : 'draft';
const uid = /^[A-Za-z0-9_-]{6,64}$/.test(String(lr.propuesta_uid || '')) ? String(lr.propuesta_uid) : '';
let render = esObjetoPlano(lr.render) ? lr.render : {};
let reutilizadas = [];
let sin_foto = [];
if (modo === 'final') {
  let manifest = null;
  if ($('Fotos de la propuesta').isExecuted) {
    const fp = $('Fotos de la propuesta').first().json || {};
    if (fp.statusCode === 200) manifest = fp.body;
  }
  const x = reutilizarFotosPlan(render, manifest, RENDERER_BASE, uid);
  render = x.render;
  reutilizadas = x.reutilizadas;
  sin_foto = x.sin_foto;
  // Último filtro antes de gastar (idempotente, no cambia el hash de una foto ya guardada): solo láminas del plan,
  // una por lámina, reglas de fotos_guard; con propuesta, nunca portada ni cierre.
  render.image_briefs = fotosDelPlan(render.image_briefs, Array.isArray(render.fases) ? render.fases.length : 0, uid !== '');
  render.draft = false;
} else {
  // La vista previa nunca paga fotos, venga lo que venga de WordPress.
  render = Object.assign({}, render, { draft: true, image_briefs: [] });
}
return [{ json: { id, modo, aviso: avisoPedido(hook.aviso), render, reutilizadas, sin_foto,
  crm: parseInt(lr.crm_cliente_id, 10) || 0,
  proyecto: String(render.proyecto || render.company_name || ('plan ' + id)) } }];"""

CODE_REINTENTAR = r"""// Tras cada render: en la versión final se vuelve a llamar al renderer solo si puede salir distinto (D11): la red o
// el tiempo, un 5xx, o un 200 sin presentación o con fotos faltantes. Un 4xx (el 400 del esquema con details) da lo
// mismo cada vez: no se reintenta. El renderer reutiliza las fotos ya guardadas con el mismo prompt
// (img/manifest.json del plan) y solo pide las que faltan: un reintento no vuelve a pagar las que ya salieron.
// Tope: MAX_RENDERS llamadas en total. Nunca lanza.
const MAX_RENDERS = __MAX__;
const base = $('Preparar render').first().json;
const r = $input.first().json || {};
const intento = $runIndex + 1;
const lr = leerRespuestaRender(r);
return [{ json: Object.assign({}, base, { resultado: r, intento,
  reintentar: base.modo === 'final' && lr.reintentable && intento < MAX_RENDERS }) }];""".replace(
    '__MAX__', str(MAX_RENDERS))

CODE_RESULTADO = r"""// Resume lo que pasó para WordPress (POST /vista) y para el correo. Nunca lanza.
const hook = $('Webhook').first().json.body || {};
const id = parseInt(hook.id, 10) || 0;
const modo = hook.modo === 'final' ? 'final' : 'draft';
const exec = String($execution.id);
const vacio = { id, modo, aviso: avisoPedido(hook.aviso), exec, ok: false, view_url: '', pdf_url: '', faltan: [],
  problemas: [], avisos: [], resumen: [], nota: '', crm: 0, proyecto: 'plan ' + id, renders: 0 };
if (!$('Preparar render').isExecuted) {
  const lr = $('Leer render').first().json || {};
  const b = esObjetoPlano(lr.body) ? lr.body : {};
  const motivo = 'No se pudo leer el plan en WordPress (' + (lr.statusCode ? 'HTTP ' + lr.statusCode
    : 'sin respuesta' + (lr.error && lr.error.message ? ': ' + lr.error.message : '')) + ')' + (b.message ? ' — ' + b.message : '');
  return [{ json: Object.assign(vacio, { problemas: [motivo], nota: motivo + ' (ejecución ' + exec + ')' }) }];
}
const v = $input.first().json || {};
const lr = leerRespuestaRender(v.resultado);
const img = lr.images;
const intento = Number(v.intento) || 1;
const veces = intento + ' ' + (intento === 1 ? 'intento' : 'intentos');
const missing = lr.missing;
const remotas = Array.isArray(img.kept_remote) ? img.kept_remote.map(String) : [];
const pedidas = Number(img.requested) || 0;
const problemas = [];
const avisos = [];
if (!lr.view_url) {
  // D11: los motivos del renderer (details del 400 del esquema o del 502) van a la nota, que WordPress guarda.
  const motivo = lr.detalles.length ? lr.detalles.join('; ') : String(lr.cuerpo.error || '').slice(0, 200);
  const rechazo = lr.status >= 400 && lr.status < 500;
  const det = lr.status === 0 ? lr.fallo : (lr.status !== 200 ? 'HTTP ' + lr.status + (motivo ? ': ' + motivo : '') : '');
  problemas.push((rechazo ? 'el renderer rechazó el plan' : 'el renderer no devolvió la presentación') + (det ? ' (' + det + ')' : '')
    + (modo === 'final' && !rechazo ? ' tras ' + veces : ''));
}
if (modo === 'final' && missing.length) problemas.push('fotos que no se generaron tras ' + veces + ': ' + nombresLaminas(missing));
if (remotas.length) avisos.push('fotos que no se pudieron guardar junto al plan (quedaron enlazadas): ' + nombresLaminas(remotas));
if (Array.isArray(v.sin_foto) && v.sin_foto.length) {
  avisos.push('la propuesta no tiene foto guardada para ' + nombresLaminas(v.sin_foto) + ': esas láminas van sin foto');
}
const ok = problemas.length === 0;
const resumen = [];
if (ok && modo === 'final') {
  resumen.push('Presentación y PDF generados');
  resumen.push((pedidas - missing.length) + ' de ' + pedidas + ' fotos nuevas guardadas junto al plan');
  if (Array.isArray(v.reutilizadas) && v.reutilizadas.length) resumen.push('Con las fotos de la propuesta, sin costo: ' + nombresLaminas(v.reutilizadas));
  resumen.push(intento + ' ' + (intento === 1 ? 'render' : 'renders'));
} else if (ok) {
  resumen.push('Vista previa generada (sin fotos)');
}
const extra = avisos.length ? ' · ' + avisos.join(' · ') : '';
const nota = ok ? (modo === 'final' ? 'Versión final verificada: ' : 'Vista previa lista: ') + resumen.join(' · ') + extra
  : problemas.join(' · ') + extra + ' (ejecución ' + exec + ')';
return [{ json: Object.assign(vacio, { ok, view_url: lr.view_url, pdf_url: lr.pdf_url,
  faltan: modo === 'final' ? missing : [], problemas, avisos, resumen, nota, crm: v.crm || 0, proyecto: v.proyecto || vacio.proyecto,
  renders: intento }) }];"""


def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def http(id_, name, pos, method, url, body_expr=None, cred=True, timeout=30000):
    params = {'method': method, 'url': url, 'options': dict(FULL, timeout=timeout)}
    if cred:
        params.update({'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth'})
    if body_expr:
        params.update({'sendBody': True, 'specifyBody': 'json', 'jsonBody': body_expr})
    extra = {'credentials': CRED_WP} if cred else {}
    # neverError no cubre timeouts ni conexiones cortadas: con continueRegularOutput pasa {error} sin statusCode y
    # el flujo sigue hasta POST /vista (el plan nunca queda trabado en «aprobando»).
    return node(id_, name, 'n8n-nodes-base.httpRequest', 4.2, pos, params, onError='continueRegularOutput', **extra)


def iff(id_, name, pos, left):
    return node(id_, name, 'n8n-nodes-base.if', 2.2, pos, {
        'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose', 'version': 2},
                       'conditions': [{'id': id_ + '-c', 'leftValue': left, 'rightValue': True,
                                       'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
                       'combinator': 'and'},
        'options': {}})


nodes = [
    node('r1', 'Webhook', 'n8n-nodes-base.webhook', 2, [0, 0],
         {'httpMethod': 'POST', 'path': 'plan-v1-render', 'authentication': 'headerAuth',
          'responseMode': 'onReceived', 'options': {}},
         webhookId='plan-v1-render', credentials=CRED_WP),
    # La base de WP ya trae «?rest_route=», así que el modo va con «&».
    http('r2', 'Leer render', [220, 0], 'GET',
         f"={WP}/plan/{{{{ $json.body.id }}}}/render&modo={{{{ $json.body.modo === 'final' ? 'final' : 'draft' }}}}"),
    iff('r3', '¿Render leído?', [440, 0],
        "={{ $json.statusCode === 200 && !!$json.body && $json.body.ok === true && !!$json.body.render"
        " && typeof $json.body.render === 'object' }}"),
    # D10: una vista previa con el plan ya «aprobando», «listo» o «enviado» pisaría en /p/<codigo>/ la versión final.
    # Falso termina aquí: sin renderer, sin /vista y sin correo (el webhook ya respondió al recibir).
    iff('r15', '¿Toca renderizar?', [660, 0],
        "={{ $('Webhook').first().json.body.modo === 'final' || !" + json.dumps(['aprobando', 'listo', 'enviado'])
        + ".includes(String($json.body.estado)) }}"),
    iff('r4', '¿Reutilizar fotos?', [880, -120],
        "={{ $('Webhook').first().json.body.modo === 'final' && String($json.body.propuesta_uid || '').length >= 6 }}"),
    # Nunca falla: sin manifest (404) o con error, «Preparar render» deja portada y cierre sin foto y lo avisa.
    http('r5', 'Fotos de la propuesta', [1100, -240], 'GET',
         "={{ '" + RENDERER_BASE + "/p/' + encodeURIComponent($json.body.propuesta_uid) + '/img/manifest.json' }}", cred=False),
    node('r6', 'Preparar render', 'n8n-nodes-base.code', 2, [1320, -120], {'jsCode': LIB + JS_AVISO + CODE_PREPARAR}),
    # Respuesta completa (código HTTP y cuerpo) para distinguir un 400 del esquema, que no se reintenta, de un 5xx (D11).
    node('r7', 'Render', 'n8n-nodes-base.httpRequest', 4.2, [1540, -120],
         {'method': 'POST', 'url': RENDERER, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
          'sendBody': True, 'specifyBody': 'json', 'jsonBody': '={{ JSON.stringify($json.render) }}',
          'options': dict(FULL, timeout=290000)},
         onError='continueRegularOutput', credentials=CRED_RENDER),
    node('r8', '¿Reintentar render?', 'n8n-nodes-base.code', 2, [1760, -240], {'jsCode': LIB + CODE_REINTENTAR}),
    iff('r9', '¿Faltan fotos?', [1980, -240], '={{ $json.reintentar === true }}'),
    node('r10', 'Resultado del render', 'n8n-nodes-base.code', 2, [2200, 0], {'jsCode': LIB + JS_AVISO + CODE_RESULTADO}),
    http('r11', 'Guardar vista', [2420, 0], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/vista",
         "={{ JSON.stringify({ modo: $json.modo, ok: $json.ok, view_url: $json.view_url, pdf_url: $json.pdf_url,"
         " faltan: $json.faltan, nota: $json.nota }) }}"),
    node('r12', 'Armar correo', 'n8n-nodes-base.code', 2, [2640, 0], {'jsCode': correo_render()}),
    iff('r13', '¿Avisar a Luis?', [2860, 0], '={{ $json.enviar === true }}'),
    node('r14', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [3080, -120],
         {'fromEmail': 'contacto@automatizatech.cl', 'toEmail': LUIS,
          'subject': '={{ $json.asunto }}', 'html': '={{ $json.html }}', 'options': {}},
         credentials=CRED_SMTP),
]

connections = {}


def link(a, b, output=0):
    connections.setdefault(a, {'main': []})
    while len(connections[a]['main']) <= output:
        connections[a]['main'].append([])
    connections[a]['main'][output].append({'node': b, 'type': 'main', 'index': 0})


link('Webhook', 'Leer render')
link('Leer render', '¿Render leído?')
link('¿Render leído?', '¿Toca renderizar?', 0)
link('¿Render leído?', 'Resultado del render', 1)
# ¿Toca renderizar? falso (vista previa atrasada, D10): termina aquí.
link('¿Toca renderizar?', '¿Reutilizar fotos?', 0)
link('¿Reutilizar fotos?', 'Fotos de la propuesta', 0)
link('¿Reutilizar fotos?', 'Preparar render', 1)
link('Fotos de la propuesta', 'Preparar render')
link('Preparar render', 'Render')
link('Render', '¿Reintentar render?')
link('¿Reintentar render?', '¿Faltan fotos?')
link('¿Faltan fotos?', 'Render', 0)
link('¿Faltan fotos?', 'Resultado del render', 1)
link('Resultado del render', 'Guardar vista')
link('Guardar vista', 'Armar correo')
link('Armar correo', '¿Avisar a Luis?')
link('¿Avisar a Luis?', 'Correo a Luis', 0)

wf = {'name': 'Plan de trabajo · 3 Render', 'nodes': nodes, 'connections': connections,
      # Si el flujo se cae sin llegar a su propio aviso, «Propuestas v3 · 0 Avisar error» le escribe a Luis.
      'settings': {'executionOrder': 'v1', 'errorWorkflow': 'm7TOfKznVSBGz4Nd'}}
out = os.path.join(AQUI, 'plan-3-render.json')
with open(out, 'w', encoding='utf-8') as fh:
    json.dump(wf, fh, ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
