"""Construye el workflow n8n «Plan de trabajo · 2 Cambios» y lo guarda en plan-2-cambios.json.

Diseño: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md; Task 13 del plan de implementación (Etapa 1).
Lo llama el panel del plan («Pedir cambios», at_pt_cambios) con {id, codigo} y X-AT-Secret, después de guardar los
comentarios y pasar el plan a «cambios». GPT-4o aplica los comentarios sobre el plan guardado sin tocar los días
que Luis editó a mano (origen «luis»); este flujo además los restituye si el modelo los cambió. Sin comentarios no
se llama al modelo: se vuelve a guardar el plan actual (WordPress recalcula y pide la vista previa).
Nunca deja el plan trabado en «cambios»: si algo falla, lo pasa a «error» con el motivo y le escribe a Luis.
Si llegó tarde (el plan ya no está en «cambios» ni en «error», o WordPress responde 409), no hace nada. Si WordPress
guardó pero no pudo pedir la vista previa, le escribe a Luis.
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os, sys

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'propuestas-v3'))
from fotos_guard import JS_LIMPIAR_FOTOS  # noqa: E402
from json_guard import JS_LEER_JSON  # noqa: E402
from correos_plan import AVISO_SIN_VISTA, correo_error_plan, correo_sin_vista  # noqa: E402
from plan_js import JS_PLAN, code_motivo  # noqa: E402

CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}
CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
LUIS = 'lmgm.0303@gmail.com'
FULL = {'response': {'response': {'fullResponse': True, 'neverError': True}}}
LIB = JS_LEER_JSON + '\n' + JS_LIMPIAR_FOTOS + '\n' + JS_PLAN + '\n'

PROMPT_CAMBIOS = """Recibes en JSON el plan de trabajo actual de un proyecto (plan_actual), los comentarios del consultor (comentarios), los datos del contrato firmado (contrato), la tabla de tiempos de referencia (tabla), el rubro del cliente según su ficha (rubro; puede venir vacío) y las láminas que llevan foto nueva (slides_foto). Devuelve SOLO el objeto JSON del plan completo, directamente (no lo envuelvas en otra clave como "plan" o "plan_actual"; sin texto antes ni después, sin bloques de código), con la misma forma y las mismas claves que plan_actual, aplicando SOLO los cambios que piden los comentarios; todo lo demás queda idéntico.
Reglas:
- Español de Chile, trato de "tú", frases simples.
- Las actividades con "origen": "luis" las editó el consultor a mano: nunca cambies sus "dias_habiles" ni su nombre (su detalle, solo si un comentario lo pide).
- Conserva el "origen" de cada actividad que ya existe. En una actividad nueva escribe "origen": "ia".
- No cambies nombres de bloques ni de actividades salvo que un comentario lo pida: el sistema reconoce las actividades por su nombre.
- fases: las mismas tres claves, en el mismo orden: "diseno_desarrollo", "implementacion" y "soporte".
- plan_actual viene sin el bloque «Arranque» (reunión de inicio y entrega de logo, textos y accesos). NO incluyas el bloque «Arranque» en tu respuesta, aunque un comentario hable de él: el sistema lo pone tal como está guardado (el consultor lo edita en el panel).
- No escribas fechas ("desde", "hasta", "fecha_inicio", "cronograma"): el sistema las recalcula en días hábiles.
- TOPES que revisa el sistema (si pasas uno, el plan entero se rechaza): como máximo 13 bloques, 10 actividades por bloque y 58 actividades en total; de 1 a 60 días hábiles por actividad; el plan completo, en secuencia y contando 5 días hábiles de revisión por cada bloque con entrega, no pasa de 130 días hábiles (unas 26 semanas); como máximo 10 hitos; fases solo con las claves "diseno_desarrollo", "implementacion" y "soporte", y responsable solo "at", "cliente" o "ambos". Si un comentario pide algo que no cabe, junta bloques o actividades que no sean del consultor.
- dias_habiles: entero (ver TOPES). en_paralelo: la primera actividad de cada bloque siempre false. servicio y etapa: como vienen; en una actividad nueva que calce con la tabla, su clave de tabla y su etapa ("diseno", "desarrollo", "pruebas" o "implementacion"); si no, "".
- soporte.garantia_meses: contrato.garantia_meses si viene; si no, deja el que trae plan_actual.
- entrega: true solo en bloques que terminan con algo que el cliente revisa y aprueba (el sistema agrega sus 5 días hábiles de revisión).
- Lo contratado manda (contrato): no agregues trabajo fuera de lo contratado salvo que un comentario lo pida expresamente. No inventes datos del cliente (nombres, teléfonos, direcciones, cifras) ni escribas montos.
- Si un comentario pide cambiar una foto, reescribe solo ese image_brief y deja los demás idénticos: fotografía realista del rubro del cliente (rubro; si viene vacío, dedúcelo del plan y del contrato; se permiten personas en acción); la de cover siempre en primer plano con el fondo desenfocado; productos con etiqueta o pantalla, sin etiqueta, de espaldas o apagados; nunca pantallas, computadores, oficinas, letreros, carteles, documentos, planos, hojas ni texto; de 40 a 60 palabras (máximo 400 caracteres); termina con "no signs, no labels, no text, no lettering, no logos, no watermarks". Nunca crees image_briefs para láminas que no estén en slides_foto."""

CODE_PEDIDO = r"""// Lo que ve el modelo: el plan guardado (sin fechas: las recalcula WordPress; sin el bloque «Arranque»: lo pone
// WordPress y «Leer plan» restituye el guardado), los comentarios de Luis, lo contratado (con los meses de garantía),
// el rubro, la tabla y las láminas con foto nueva.
const c = ($input.first().json || {}).body || {};
const hayPropuesta = esObjetoPlano(c.propuesta);
const actual = JSON.parse(JSON.stringify(c.plan_actual || {}));
delete actual.cronograma;
delete actual.fecha_inicio;
quitarArranque(actual);
recorrerActividades(actual, (a) => { delete a.desde; delete a.hasta; });
const permitidas = Array.isArray(c.slides_foto) && c.slides_foto.length ? c.slides_foto : null;
const n = Array.isArray(actual.fases) ? actual.fases.length : 3;
const datos = {
  comentarios: String(c.comentarios || '').trim(),
  rubro: String(c.rubro || '').slice(0, 100),
  plan_actual: actual,
  contrato: esObjetoPlano(c.contrato) ? c.contrato : {},
  tabla: esObjetoPlano(c.tabla) ? c.tabla : {},
  slides_foto: slidesConFoto(hayPropuesta, n).filter((s) => !permitidas || permitidas.includes(s)),
};
return [{ json: { mensaje: JSON.stringify(datos) } }];"""

CODE_LEER = r"""// Nunca lanza: si la respuesta no sirve, ok=false con el motivo y el flujo pasa el plan a «error».
// Sin comentarios no se llamó al modelo: el plan guardado pasa por el mismo camino (queda igual).
const ctx = $('Leer contexto').first().json.body || {};
const hook = $('Webhook').first().json.body || {};
const anterior = esObjetoPlano(ctx.plan_actual) ? ctx.plan_actual : {};
const conIa = $('Aplicar cambios').isExecuted;
const ia = conIa ? $('Aplicar cambios').first().json : { message: { content: JSON.stringify(anterior) } };
const r = leerPlanModelo(ia, { modo: 'cambios', hayPropuesta: esObjetoPlano(ctx.propuesta), anterior });
return [{ json: Object.assign({ id: parseInt(hook.id, 10) || 0, con_ia: conIa }, r) }];"""


def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def http_wp(id_, name, pos, method, url, body_expr=None, timeout=30000):
    params = {'method': method, 'url': url, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
              'options': dict(FULL, timeout=timeout)}
    if body_expr:
        params.update({'sendBody': True, 'specifyBody': 'json', 'jsonBody': body_expr})
    return node(id_, name, 'n8n-nodes-base.httpRequest', 4.2, pos, params, credentials=CRED_WP,
                onError='continueRegularOutput')


def iff(id_, name, pos, left):
    return node(id_, name, 'n8n-nodes-base.if', 2.2, pos, {
        'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose', 'version': 2},
                       'conditions': [{'id': id_ + '-c', 'leftValue': left, 'rightValue': True,
                                       'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
                       'combinator': 'and'},
        'options': {}})


nodes = [
    node('q1', 'Webhook', 'n8n-nodes-base.webhook', 2, [0, 0],
         {'httpMethod': 'POST', 'path': 'plan-v1-cambios', 'authentication': 'headerAuth',
          'responseMode': 'onReceived', 'options': {}},
         webhookId='plan-v1-cambios', credentials=CRED_WP),
    http_wp('q2', 'Leer contexto', [220, 0], 'GET', f"={WP}/plan/{{{{ $json.body.id }}}}/contexto"),
    iff('q3', '¿Contexto leído?', [440, 0], '={{ $json.statusCode === 200 && !!$json.body && $json.body.ok === true }}'),
    # Llegó tarde si el plan ya no espera cambios (POST /borrador con origen «cambios» solo se acepta en «cambios» o
    # «error»; Task 7). Va antes de mirar el plan guardado: un pedido viejo no pasa a «error» un plan que se está
    # generando. Falso termina aquí: sin gastar en GPT-4o ni tocar el plan.
    iff('q15', '¿Espera respuesta?', [660, 0], "={{ ['cambios', 'error'].includes(String($json.body.estado)) }}"),
    iff('q16', '¿Hay plan guardado?', [880, 0],
        "={{ !!$json.body.plan_actual && typeof $json.body.plan_actual === 'object' && !Array.isArray($json.body.plan_actual) }}"),
    iff('q4', '¿Hay comentarios?', [1100, 0], "={{ String(($json.body && $json.body.comentarios) || '').trim().length > 0 }}"),
    node('q5', 'Preparar pedido', 'n8n-nodes-base.code', 2, [1320, -120], {'jsCode': LIB + CODE_PEDIDO}),
    node('q6', 'Aplicar cambios', 'n8n-nodes-base.openAi', 1, [1540, -120],
         {'resource': 'chat', 'model': 'gpt-4o',
          'prompt': {'messages': [{'role': 'system', 'content': PROMPT_CAMBIOS}, {'content': '={{ $json.mensaje }}'}]},
          'options': {'temperature': 0.2}, 'requestOptions': {}},
         credentials=CRED_OPENAI, onError='continueRegularOutput'),
    node('q7', 'Leer plan', 'n8n-nodes-base.code', 2, [1760, 0], {'jsCode': LIB + CODE_LEER}),
    iff('q8', '¿Plan legible?', [1980, 0], '={{ $json.ok === true }}'),
    http_wp('q9', 'Guardar borrador', [2200, -120], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/borrador",
            "={{ JSON.stringify({ plan: $json.plan, origen: 'cambios' }) }}", timeout=60000),
    iff('q10', '¿Guardado?', [2420, -120], '={{ $json.statusCode === 200 && !!$json.body && $json.body.ok === true }}'),
    iff('q17', '¿Vista previa pedida?', [2640, -240],
        "={{ ![].concat(($json.body && $json.body.avisos) || []).some((a) => String(a).startsWith("
        + json.dumps(AVISO_SIN_VISTA) + ")) }}"),
    node('q18', 'Armar correo sin vista', 'n8n-nodes-base.code', 2, [2860, -240], {'jsCode': correo_sin_vista('cambios')}),
    # 409 = WordPress dice que estos cambios llegaron tarde (el plan ya siguió): no se marca error ni se escribe.
    iff('q19', '¿Llegó tarde?', [2640, 0], '={{ $json.statusCode === 409 }}'),
    node('q11', 'Motivo del error', 'n8n-nodes-base.code', 2, [2860, 160], {'jsCode': LIB + code_motivo('cambios')}),
    http_wp('q12', 'Marcar error', [3080, 160], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/error",
            "={{ JSON.stringify({ nota: $json.reason + ' (ejecución ' + $json.exec + ')' }) }}"),
    node('q13', 'Armar correo error', 'n8n-nodes-base.code', 2, [3300, 160], {'jsCode': correo_error_plan('cambios')}),
    node('q14', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [3520, -40],
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


link('Webhook', 'Leer contexto')
link('Leer contexto', '¿Contexto leído?')
link('¿Contexto leído?', '¿Espera respuesta?', 0)
link('¿Contexto leído?', 'Motivo del error', 1)
link('¿Espera respuesta?', '¿Hay plan guardado?', 0)
link('¿Hay plan guardado?', '¿Hay comentarios?', 0)
link('¿Hay plan guardado?', 'Motivo del error', 1)
link('¿Hay comentarios?', 'Preparar pedido', 0)
link('¿Hay comentarios?', 'Leer plan', 1)
link('Preparar pedido', 'Aplicar cambios')
link('Aplicar cambios', 'Leer plan')
link('Leer plan', '¿Plan legible?')
link('¿Plan legible?', 'Guardar borrador', 0)
link('¿Plan legible?', 'Motivo del error', 1)
link('Guardar borrador', '¿Guardado?')
link('¿Guardado?', '¿Vista previa pedida?', 0)
link('¿Guardado?', '¿Llegó tarde?', 1)
# Vista previa pedida: termina aquí; «3 Render» le escribe a Luis cuando la vista previa esté lista.
link('¿Vista previa pedida?', 'Armar correo sin vista', 1)
link('Armar correo sin vista', 'Correo a Luis')
link('¿Llegó tarde?', 'Motivo del error', 1)
link('Motivo del error', 'Marcar error')
link('Marcar error', 'Armar correo error')
link('Armar correo error', 'Correo a Luis')

wf = {'name': 'Plan de trabajo · 2 Cambios', 'nodes': nodes, 'connections': connections,
      # Si el flujo se cae sin llegar a su propio aviso, «Propuestas v3 · 0 Avisar error» le escribe a Luis.
      'settings': {'executionOrder': 'v1', 'errorWorkflow': 'm7TOfKznVSBGz4Nd'}}
out = os.path.join(AQUI, 'plan-2-cambios.json')
with open(out, 'w', encoding='utf-8') as fh:
    json.dump(wf, fh, ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
