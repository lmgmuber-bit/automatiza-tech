"""Construye el workflow n8n «Plan de trabajo · 3 Render» y lo guarda en plan-3-render.json.

Diseño: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md; Task 14 del plan de implementación (Etapa 1).
Lo llama WordPress (at_pt_pedir_render) con {id, codigo, modo: 'draft'|'final', aviso} y X-AT-Secret:
- draft: vista previa SIN fotos (tras el borrador, los cambios o «Guardar y recalcular»). No cambia el estado del
  plan; si falla, solo deja la nota. Correo a Luis solo si WordPress pidió aviso o si algo falló. Si GET /render dice
  que el plan ya está «aprobando», «listo» o «enviado», no la dibuja (pisaría la versión final): termina sin más.
  Nunca revisa fotos (no hay).
- final: al «Aprobar» (plan en «aprobando»). Portada y cierre reutilizan las fotos de la propuesta (sin costo); las
  láminas nuevas piden fotos nuevas (gasto: US$0,0032 c/u de lista). Si faltan fotos, no hubo presentación, se cortó
  la red o el renderer dio un 5xx, vuelve a llamarlo (el renderer reutiliza las fotos ya guardadas con el mismo prompt
  y no las vuelve a cobrar). Un 4xx (el 400 del esquema) no se reintenta y sus motivos van a la nota.
  Revisión de texto (decisión D18, 29-sep; la misma de «3 Final» de las propuestas): con la versión completa, GPT-4o
  mira en UNA consulta las fotos nuevas guardadas; las que tengan texto se piden UNA vez más con la descripción +
  RETOMA y se vuelve a renderizar; lo que siga con texto va como aviso en la nota de /vista y en el correo, sin pasar
  el plan a «error». Gasto de lista (documentación de OpenAI, no medido en una factura): ≈ US$0,026 por consulta,
  hasta dos, más US$0,0032 por foto rehecha; el panel lo suma antes de «Aprobar» (at_pt_costo_fotos, Task 4).
  Tope: MAX_RENDERS = 3 renders EN TOTAL por corrida, contando el de la retoma (decisiones D11 y D18): la retoma solo
  se pide si queda al menos un render, y sus reintentos gastan del mismo tope.
  Termina SIEMPRE con POST /vista: WordPress pasa el plan a «listo» o a «error»; nunca queda trabado en «aprobando».
  Siempre le escribe a Luis.
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os, sys

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'propuestas-v3'))
from fotos_guard import JS_LIMPIAR_FOTOS  # noqa: E402
from json_guard import JS_LEER_JSON  # noqa: E402
from correos_plan import correo_render  # noqa: E402
from plan_js import JS_PLAN, JS_RENDER, JS_REVISION, JS_SHA256, RENDERER_BASE  # noqa: E402

CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}  # la misma de las propuestas
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
CRED_RENDER = {'httpHeaderAuth': {'id': 'fj2orzbsjlnHaiLd', 'name': 'X-AT-Render-Key'}}  # clave de /render del renderer
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
RENDERER = RENDERER_BASE + '/render'
LUIS = 'lmgm.0303@gmail.com'
FULL = {'response': {'response': {'fullResponse': True, 'neverError': True}}}
MAX_RENDERS = 3
LIB = (JS_LEER_JSON + '\n' + JS_LIMPIAR_FOTOS + '\n' + JS_PLAN + '\n' + JS_RENDER + '\n' + JS_SHA256 + '\n' + JS_REVISION + '\n'
       + 'const RENDERER_BASE = ' + json.dumps(RENDERER_BASE) + ';\nconst MAX_RENDERS = ' + str(MAX_RENDERS) + ';\n')

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
let retomadas_antes = [];
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
  // D18: una foto que una corrida anterior rehízo por texto se pide con su descripción + RETOMA, y el renderer la
  // reutiliza sin cobrarla (manifest del plan; sin manifest, 404 o ilegible, se piden las de WordPress).
  let previo = null;
  if ($('Fotos previas del plan').isExecuted) {
    const pp = $('Fotos previas del plan').first().json || {};
    if (pp.statusCode === 200) previo = pp.body;
  }
  const rp = retomasPrevias(render.image_briefs, previo);
  render.image_briefs = rp.briefs;
  retomadas_antes = rp.retomadas;
  render.draft = false;
} else {
  // La vista previa nunca paga fotos, venga lo que venga de WordPress.
  render = Object.assign({}, render, { draft: true, image_briefs: [] });
}
return [{ json: { id, modo, aviso: avisoPedido(hook.aviso), render, reutilizadas, sin_foto, retomadas_antes,
  crm: parseInt(lr.crm_cliente_id, 10) || 0,
  proyecto: String(render.proyecto || render.company_name || ('plan ' + id)) } }];"""

CODE_REINTENTAR = r"""// Tras cada render: en la versión final se vuelve a llamar al renderer solo si puede salir distinto (D11): la red o
// el tiempo, un 5xx, o un 200 sin presentación o con fotos faltantes. Un 4xx (el 400 del esquema con details) da lo
// mismo cada vez: no se reintenta. El renderer reutiliza las fotos ya guardadas con el mismo prompt
// (img/manifest.json del plan) y solo pide las que faltan: un reintento no vuelve a pagar las que ya salieron.
// Tope: MAX_RENDERS llamadas EN TOTAL, contando la retoma por texto (D18): si «Decidir retoma» pidió rehacer fotos,
// los reintentos siguen con SUS descripciones (con las originales, el renderer volvería a pedir la foto con texto).
// La revisión de texto corre solo con la versión final completa (presentación y todas las fotos): si faltan fotos,
// el plan queda en «error» y la revisión corre cuando Luis lo apruebe de nuevo. Nunca lanza.
const retoma = $('Decidir retoma').isExecuted && $('Decidir retoma').first().json.rehacer === true;
const base = retoma ? $('Decidir retoma').first().json : $('Preparar render').first().json;
const r = $input.first().json || {};
const intento = $runIndex + 1;
const lr = leerRespuestaRender(r);
const final = base.modo === 'final';
return [{ json: Object.assign({}, base, { resultado: r, intento,
  reintentar: final && lr.reintentable && intento < MAX_RENDERS,
  revisar_texto: final && lr.status === 200 && !!lr.view_url && lr.missing.length === 0 }) }];"""

CODE_PREPARAR_TEXTO = r"""// Arma UNA consulta a GPT-4o con las fotos nuevas del plan que quedaron guardadas (img/manifest.json del plan en el
// renderer). Nunca lanza: sin fotos que mostrar no se consulta y «Resultado del render» lo avisa.
const vig = $('¿Reintentar render?').first().json;
const render = esObjetoPlano(vig.render) ? vig.render : {};
const lr = leerRespuestaRender(vig.resultado);
const pedidas = (Array.isArray(render.image_briefs) ? render.image_briefs : []).map((b) => (esObjetoPlano(b) ? b.slide : '')).filter(Boolean);
let m = $json.statusCode === 200 ? $json.body : null;
if (typeof m === 'string') { try { m = JSON.parse(m); } catch (e) { m = null; } }
const leida = esObjetoPlano(m);
const fotos = leida ? fotosParaRevisar(pedidas, m, lr.images, render.unique_id, RENDERER_BASE, vig.intento) : [];
return [{ json: {
  revisar: fotos.length > 0,
  slides: fotos.map((f) => f.slide),
  ronda: Array.isArray(vig.fotos_retomadas) ? 1 : 0,
  vigente: vig,
  // Sin fotos nuevas pedidas (todo viene de la propuesta) no hay nada que revisar ni que avisar.
  motivo_sin_revision: fotos.length || !pedidas.length ? '' : (leida ? 'no hay fotos guardadas'
    : 'no se pudo leer la lista de fotos (' + ($json.statusCode ? 'HTTP ' + $json.statusCode : 'sin respuesta') + ')'),
  cuerpo: cuerpoRevision(fotos),
} }];"""

CODE_DECIDIR_RETOMA = r"""// Lee la respuesta de GPT-4o. En la primera ronda, las fotos con texto se vuelven a pedir con otra descripción
// (el renderer reutiliza una foto solo si su descripción no cambió), si queda al menos un render del tope. En la
// segunda, o sin renders, solo informa. Cada foto se rehace UNA vez, también entre corridas: la que ya trae RETOMA
// (rehecha antes y reutilizada por «Preparar render») no se vuelve a pagar. Nunca lanza.
const prep = $('Preparar revisión de texto').first().json;
const rev = leerRevision($json, prep.slides);
const vig = prep.vigente;
const render = vig.render;
const vigentes = Array.isArray(render.image_briefs) ? render.image_briefs : [];
const nuevas = (rev.con_texto || []).filter((s) => vigentes.some((b) => b && b.slide === s && !String(b.prompt).endsWith(RETOMA)));
const quedan = (Number(vig.intento) || 0) < MAX_RENDERS;
if (nuevas.length && prep.ronda === 0 && quedan) {
  const briefs = vigentes.map((b) => (nuevas.includes(b.slide) ? { slide: b.slide, prompt: b.prompt + RETOMA } : b));
  return [{ json: Object.assign({}, vig, { render: Object.assign({}, render, { image_briefs: briefs }),
    rehacer: true, fotos_retomadas: nuevas }) }];
}
return [{ json: { rehacer: false, fotos_con_texto: rev.con_texto || [], revision_fallo: rev.fallo,
  sin_renders: nuevas.length > 0 && prep.ronda === 0 && !quedan } }];"""

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
// La última pasada del bucle de renders (llega aquí también desde la revisión de texto).
const v = $('¿Reintentar render?').first().json || {};
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
// Revisión de texto en las fotos (D18): nunca pasa el plan a «error»; solo avisa en la nota y en el correo.
let revisionLimpia = false;
if (Array.isArray(v.fotos_retomadas) && v.fotos_retomadas.length) {
  avisos.push('Fotos rehechas porque tenían texto: ' + nombresLaminas(v.fotos_retomadas));
}
if (modo === 'final' && v.revisar_texto === true) {
  const prep = $('Preparar revisión de texto').isExecuted ? $('Preparar revisión de texto').first().json : null;
  const qa = $('Decidir retoma').isExecuted ? $('Decidir retoma').first().json : null;
  if (!prep) avisos.push('No se revisó si las fotos tienen texto');
  else if (!prep.revisar) { if (prep.motivo_sin_revision) avisos.push('No se revisó si las fotos tienen texto: ' + prep.motivo_sin_revision); }
  else if (qa && !qa.rehacer && qa.revision_fallo) avisos.push('No se pudo revisar si las fotos tienen texto (' + qa.revision_fallo + ')');
  else if (qa && !qa.rehacer && (qa.fotos_con_texto || []).length) {
    avisos.push('Fotos que todavía pueden tener texto (revísalas o pide cambios): ' + nombresLaminas(qa.fotos_con_texto)
      + (qa.sin_renders ? ' (no quedaban renders para rehacerlas)' : ''));
  } else if (qa && !qa.rehacer) revisionLimpia = true;
}
const ok = problemas.length === 0;
const resumen = [];
if (ok && modo === 'final') {
  resumen.push('Presentación y PDF generados');
  resumen.push((pedidas - missing.length) + ' de ' + pedidas + ' fotos nuevas guardadas junto al plan');
  if (Array.isArray(v.reutilizadas) && v.reutilizadas.length) resumen.push('Con las fotos de la propuesta, sin costo: ' + nombresLaminas(v.reutilizadas));
  resumen.push(intento + ' ' + (intento === 1 ? 'render' : 'renders'));
  if (revisionLimpia) resumen.push('Fotos revisadas con GPT-4o: sin texto');
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
    # Solo la versión final lee manifests (la vista previa no lleva fotos).
    iff('r16', '¿Versión final?', [770, 0], "={{ $('Webhook').first().json.body.modo === 'final' }}"),
    # D18: manifest del propio plan, para reutilizar sin costo las fotos rehechas por texto en una corrida anterior.
    # Nunca falla: sin manifest (404, primera versión final) o con error, se piden las descripciones de WordPress.
    http('r17', 'Fotos previas del plan', [880, -240], 'GET',
         "={{ '" + RENDERER_BASE + "/p/' + encodeURIComponent(String(($('Leer render').first().json.body.render || {}).unique_id || ''))"
         " + '/img/manifest.json' }}", cred=False),
    iff('r4', '¿Reutilizar fotos?', [990, -120],
        "={{ String($('Leer render').first().json.body.propuesta_uid || '').length >= 6 }}"),
    # Nunca falla: sin manifest (404) o con error, «Preparar render» deja portada y cierre sin foto y lo avisa.
    http('r5', 'Fotos de la propuesta', [1100, -240], 'GET',
         "={{ '" + RENDERER_BASE + "/p/' + encodeURIComponent($('Leer render').first().json.body.propuesta_uid) + '/img/manifest.json' }}",
         cred=False),
    node('r6', 'Preparar render', 'n8n-nodes-base.code', 2, [1320, -120], {'jsCode': LIB + JS_AVISO + CODE_PREPARAR}),
    # Respuesta completa (código HTTP y cuerpo) para distinguir un 400 del esquema, que no se reintenta, de un 5xx (D11).
    node('r7', 'Render', 'n8n-nodes-base.httpRequest', 4.2, [1540, -120],
         {'method': 'POST', 'url': RENDERER, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
          'sendBody': True, 'specifyBody': 'json', 'jsonBody': '={{ JSON.stringify($json.render) }}',
          'options': dict(FULL, timeout=290000)},
         onError='continueRegularOutput', credentials=CRED_RENDER),
    node('r8', '¿Reintentar render?', 'n8n-nodes-base.code', 2, [1760, -240], {'jsCode': LIB + CODE_REINTENTAR}),
    iff('r9', '¿Faltan fotos?', [1980, -240], '={{ $json.reintentar === true }}'),
    # Revisión de texto en las fotos (D18): ver el docstring.
    iff('r18', '¿Revisar texto?', [2090, -360], '={{ $json.revisar_texto === true }}'),
    http('r19', 'Leer fotos del plan', [2200, -480], 'GET',
         "={{ '" + RENDERER_BASE + "/p/' + encodeURIComponent(String(($json.render || {}).unique_id || '')) + '/img/manifest.json' }}",
         cred=False),
    node('r20', 'Preparar revisión de texto', 'n8n-nodes-base.code', 2, [2310, -480], {'jsCode': LIB + CODE_PREPARAR_TEXTO}),
    iff('r21', '¿Hay fotos que revisar?', [2420, -480], '={{ $json.revisar === true }}'),
    node('r22', 'Buscar texto en fotos', 'n8n-nodes-base.httpRequest', 4.2, [2530, -600],
         {'method': 'POST', 'url': 'https://api.openai.com/v1/chat/completions',
          'authentication': 'predefinedCredentialType', 'nodeCredentialType': 'openAiApi',
          'sendBody': True, 'specifyBody': 'json', 'jsonBody': '={{ JSON.stringify($json.cuerpo) }}',
          'options': dict(FULL, timeout=120000)},
         onError='continueRegularOutput', credentials=CRED_OPENAI),
    node('r23', 'Decidir retoma', 'n8n-nodes-base.code', 2, [2640, -600], {'jsCode': LIB + CODE_DECIDIR_RETOMA}),
    iff('r24', '¿Rehacer fotos?', [2750, -600], '={{ $json.rehacer === true }}'),
    node('r10', 'Resultado del render', 'n8n-nodes-base.code', 2, [2860, 0], {'jsCode': LIB + JS_AVISO + CODE_RESULTADO}),
    http('r11', 'Guardar vista', [3080, 0], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/vista",
         "={{ JSON.stringify({ modo: $json.modo, ok: $json.ok, view_url: $json.view_url, pdf_url: $json.pdf_url,"
         " faltan: $json.faltan, nota: $json.nota }) }}"),
    node('r12', 'Armar correo', 'n8n-nodes-base.code', 2, [3300, 0], {'jsCode': correo_render()}),
    iff('r13', '¿Avisar a Luis?', [3520, 0], '={{ $json.enviar === true }}'),
    node('r14', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [3740, -120],
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
link('¿Toca renderizar?', '¿Versión final?', 0)
link('¿Versión final?', 'Fotos previas del plan', 0)
link('¿Versión final?', 'Preparar render', 1)
link('Fotos previas del plan', '¿Reutilizar fotos?')
link('¿Reutilizar fotos?', 'Fotos de la propuesta', 0)
link('¿Reutilizar fotos?', 'Preparar render', 1)
link('Fotos de la propuesta', 'Preparar render')
link('Preparar render', 'Render')
link('Render', '¿Reintentar render?')
link('¿Reintentar render?', '¿Faltan fotos?')
link('¿Faltan fotos?', 'Render', 0)
link('¿Faltan fotos?', '¿Revisar texto?', 1)
link('¿Revisar texto?', 'Leer fotos del plan', 0)
link('¿Revisar texto?', 'Resultado del render', 1)
link('Leer fotos del plan', 'Preparar revisión de texto')
link('Preparar revisión de texto', '¿Hay fotos que revisar?')
link('¿Hay fotos que revisar?', 'Buscar texto en fotos', 0)
link('¿Hay fotos que revisar?', 'Resultado del render', 1)
link('Buscar texto en fotos', 'Decidir retoma')
link('Decidir retoma', '¿Rehacer fotos?')
link('¿Rehacer fotos?', 'Render', 0)
link('¿Rehacer fotos?', 'Resultado del render', 1)
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
