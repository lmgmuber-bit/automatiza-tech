"""Construye el workflow n8n «Plan de trabajo · 1 Borrador» y lo guarda en plan-1-borrador.json.

Diseño: Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md; Task 13 del plan de implementación (Etapa 1).
Lo llama WordPress (at_pt_iniciar_borrador, al firmar el contrato o con «Crear plan» / «Reintentar borrador») con
{id, codigo} y la cabecera X-AT-Secret. Lee el contexto del plan (lo contratado sale del CONTRATO; la propuesta, si existe,
solo aporta rubro y extracto), pide el plan a GPT-4o y lo guarda en WordPress, que lo valida, aplica la tabla de
tiempos, calcula las fechas y pide la vista previa (flujo «3 Render», que es el que le escribe a Luis).
Nunca deja el plan trabado en «generando»: si algo falla, lo pasa a «error» con el motivo y le escribe a Luis.
Si llegó tarde (el plan ya no está en «generando» ni en «error» sin contenido, o WordPress responde 409), no hace nada: no gasta en
GPT-4o ni pasa a «error» un plan que ya siguió. Si WordPress guardó pero no pudo pedir la vista previa, le escribe a
Luis (si no, esperaría el correo «borrador listo», que no llega).
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

PROMPT_PLAN = """Eres jefe de proyectos de AutomatizaTech (Chile). Recibes en JSON los datos de un contrato de servicios que el cliente ya firmó (contrato: servicios contratados, alcance, entregables, fases siguientes, plazo y meses de garantía), el rubro del cliente según su ficha (rubro; puede venir vacío), la tabla de tiempos de referencia de AutomatizaTech (tabla), las láminas que llevan foto nueva (slides_foto) y, si existe, un extracto de la propuesta y de la reunión con el cliente (propuesta; puede venir null). Arma el PLAN DE TRABAJO del proyecto: las actividades concretas para entregar lo contratado, en orden, con su responsable y sus días hábiles. Responde SOLO un objeto JSON (sin texto antes ni después, sin bloques de código) con esta forma exacta:
{
 "version": 1,
 "proyecto": "nombre corto del proyecto, máx. 8 palabras; usa el de los datos si viene",
 "fases": [
  {"clave": "diseno_desarrollo", "descripcion": "2 o 3 frases para el cliente: qué hacemos en esta fase",
   "bloques": [
    {"nombre": "máx. 4 palabras", "entregable": "una línea: qué recibe o revisa el cliente al cerrar el bloque; vacío si nada", "entrega": true,
     "actividades": [
      {"nombre": "máx. 8 palabras", "detalle": "una línea", "responsable": "at", "dias_habiles": 3, "servicio": "clave de la tabla o vacío", "etapa": "diseno", "en_paralelo": false}
     ]}
   ]},
  {"clave": "implementacion", "descripcion": "…", "bloques": ["…"]},
  {"clave": "soporte", "descripcion": "…", "bloques": ["…"]}
 ],
 "hitos": [{"nombre": "máx. 5 palabras", "despues_de": "nombre exacto de un bloque del plan"}],
 "necesitamos_de_ti": ["insumo concreto que el cliente debe entregar, una línea"],
 "reuniones": [{"nombre": "máx. 6 palabras", "detalle": "una línea: para qué es"}],
 "soporte": {"garantia_meses": 3, "mensuales": ["nombre de un servicio mensual contratado"]},
 "image_briefs": [{"slide": "una lámina de slides_foto", "prompt": "descripción en inglés, de 40 a 60 palabras"}]
}
Reglas:
- Español de Chile, trato de "tú", frases simples y concretas: el documento lo lee el cliente.
- LO CONTRATADO MANDA: arma actividades solo para lo que dicen contrato.servicios_contratados, contrato.alcance y contrato.entregables. contrato.fases_siguientes son etapas futuras que NO están contratadas: no las planifiques. La propuesta solo sirve para entender el rubro y el detalle de lo acordado; si contradice al contrato, manda el contrato.
- No inventes datos del cliente ni del proyecto: ni nombres de personas, teléfonos, correos, direcciones, montos, cifras, fechas ni herramientas que no estén en los datos. Nunca escribas precios ni montos (contrato.servicios_contratados trae precios: ignóralos). No escribas fechas: las calcula el sistema en días hábiles.
- fases: exactamente las tres, en este orden y con estas claves: "diseno_desarrollo", "implementacion" y "soporte". No escribas "titulo": el sistema pone los títulos.
- NO incluyas el bloque «Arranque» (reunión de inicio y entrega de logo, textos y accesos): lo agrega el sistema al comienzo del plan.
- TOPES que revisa el sistema (si pasas uno, el plan entero se rechaza): como máximo 13 bloques, 10 actividades por bloque y 58 actividades en total; de 1 a 60 días hábiles por actividad; el plan completo, en secuencia y contando 5 días hábiles de revisión por cada bloque con entrega, no pasa de 126 días hábiles (el sistema suma 4 días del Arranque, que tú no mandas: unas 26 semanas en total); como máximo 10 hitos; fases solo con las claves "diseno_desarrollo", "implementacion" y "soporte", y responsable solo "at", "cliente" o "ambos". Si el proyecto no cabe, junta bloques o actividades.
- bloques: en diseno_desarrollo de 2 a 4 (por ejemplo «Diseño», «Desarrollo», «Pruebas y revisión»); en implementacion 1 o 2; en soporte 1. En total, no más de 7: cada bloque es una barra de la carta Gantt. Cada bloque con 1 a 5 actividades.
- entrega: true solo en los bloques que terminan con algo que el cliente revisa y aprueba (el diseño, una versión para probar, la puesta en marcha). Después de cada entrega el sistema agrega 5 días hábiles de revisión del cliente (cláusula 6.1 del contrato): no los agregues tú.
- responsable: "at" (AutomatizaTech), "cliente" o "ambos".
- servicio y etapa: si la actividad es parte de un servicio de la tabla, escribe en "servicio" su clave (una clave de tabla) y en "etapa" la etapa: "diseno", "desarrollo", "pruebas" o "implementacion". El sistema reparte los días de la tabla entre las actividades de un mismo servicio y etapa según los días que propongas. En la fase soporte usa "etapa": "soporte". Si la actividad no calza con la tabla, "servicio": "" y "etapa": "".
- dias_habiles: entero, días hábiles de trabajo de esa actividad (ver TOPES). Si calza con la tabla, coherente con ella; si no, una estimación prudente.
- en_paralelo: true solo si la actividad se puede hacer al mismo tiempo que la anterior del mismo bloque; la primera actividad de cada bloque siempre false.
- hitos: 2 a 4; despues_de es el nombre exacto de un bloque que existe en el plan. El sistema agrega solo el hito «Entrega estimada».
- necesitamos_de_ti: 3 a 6 insumos concretos que este proyecto necesita del cliente (por ejemplo logo, textos, fotos de sus productos, accesos al dominio).
- reuniones: 3 o 4. Siempre «Reunión de inicio», «Llamada de seguimiento del plan» y «Entrega y capacitación»; una cuarta solo si el proyecto la necesita.
- soporte: garantia_meses = contrato.garantia_meses si viene; si no, 3. mensuales: los servicios mensuales que aparezcan en lo contratado (por ejemplo mantención o gestión de campañas); si no hay, [].
- image_briefs: exactamente una por cada lámina de slides_foto, y ninguna otra. Fotografía realista, cálida y respetuosa del RUBRO de este cliente (el de rubro; si viene vacío, dedúcelo de la propuesta o del alcance y del nombre del negocio): su gente trabajando o atendiendo, sus clientes, sus productos y sus lugares. Se permiten personas en acción.
  · Cada foto muestra el MUNDO DEL CLIENTE, nunca el trabajo de AutomatizaTech: nada de celulares, computadores, pantallas, oficinas, mesas de trabajo con papeles, calendarios, planos, documentos ni gráficos, aunque la lámina hable de plazos, reuniones o de un portal. No uses las palabras "meeting" ni "office".
  · Qué mostrar: cover = primer plano de las manos o de un detalle del oficio del rubro; metodo = la gente del negocio preparando su lugar antes de abrir; gantt = manos del rubro haciendo el trabajo paso a paso, en orden; fase_1 = los materiales, herramientas o productos del rubro dispuestos para empezar; fase_2 = el negocio atendiendo a su primer cliente del día; fase_3 = la gente del negocio atendiendo con calma, tiempo después; necesitamos = manos reuniendo los productos o herramientas propios del negocio sobre un mesón; reuniones = dos personas del rubro conversando cara a cara, de pie, en el lugar de trabajo; portal = el dueño o la dueña tranquilo, con un café, en su negocio al final del día; cierre = una escena esperanzadora del rubro (una entrega que llega, un equipo celebrando).
  · cover: SIEMPRE un primer plano con el fondo completamente desenfocado; nunca fachadas, calles, estadios, galerías ni muros de fondo (ahí el modelo de imagen inventa carteles).
  · Si un producto del rubro lleva etiqueta o pantalla (botellas, latas, cajas, celulares, libros), muéstralo sin etiqueta, de espaldas o apagado, y escríbelo así en el prompt (por ejemplo "unlabeled bottle", "phone screen facing away").
  · Nunca letreros, carteles, menús, pizarras, hojas, planos, dibujos, instrucciones ni texto de ningún tipo. En una tienda o sala de ventas, primer plano con el fondo desenfocado.
  · Cada prompt tiene de 40 a 60 palabras (máximo 400 caracteres) y termina con: "no signs, no labels, no text, no lettering, no logos, no watermarks"."""

CODE_PEDIDO = r"""// Lo que ve el modelo: lo contratado (del contrato), el rubro de la ficha del CRM (para las fotos), la propuesta si
// existe, la tabla y las láminas con foto nueva.
// No se le manda el nombre de la persona ni fechas: el plan no los necesita y las fechas las calcula WordPress.
const c = ($input.first().json || {}).body || {};
const hayPropuesta = esObjetoPlano(c.propuesta);
const permitidas = Array.isArray(c.slides_foto) && c.slides_foto.length ? c.slides_foto : null;
const datos = {
  proyecto: String(c.proyecto || ''),
  empresa: String(c.empresa || ''),
  rubro: String(c.rubro || '').slice(0, 100),
  contrato: esObjetoPlano(c.contrato) ? c.contrato : {},
  propuesta: hayPropuesta ? c.propuesta : null,
  tabla: esObjetoPlano(c.tabla) ? c.tabla : {},
  slides_foto: slidesConFoto(hayPropuesta, 3).filter((s) => !permitidas || permitidas.includes(s)),
};
return [{ json: { mensaje: JSON.stringify(datos) } }];"""

CODE_LEER = r"""// Nunca lanza: si la respuesta no sirve, ok=false con el motivo y el flujo pasa el plan a «error».
const ctx = $('Leer contexto').first().json.body || {};
const hook = $('Webhook').first().json.body || {};
const r = leerPlanModelo($('Redactar plan').first().json, { modo: 'borrador', hayPropuesta: esObjetoPlano(ctx.propuesta) });
return [{ json: Object.assign({ id: parseInt(hook.id, 10) || 0 }, r) }];"""


def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


def http_wp(id_, name, pos, method, url, body_expr=None, timeout=30000):
    params = {'method': method, 'url': url, 'authentication': 'genericCredentialType', 'genericAuthType': 'httpHeaderAuth',
              'options': dict(FULL, timeout=timeout)}
    if body_expr:
        params.update({'sendBody': True, 'specifyBody': 'json', 'jsonBody': body_expr})
    # neverError no cubre timeouts ni conexiones cortadas: con continueRegularOutput pasa {error} sin statusCode
    # y los «¿…?» lo mandan a la rama de error (el plan nunca queda trabado en «generando»).
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
    node('p1', 'Webhook', 'n8n-nodes-base.webhook', 2, [0, 0],
         {'httpMethod': 'POST', 'path': 'plan-v1-borrador', 'authentication': 'headerAuth',
          'responseMode': 'onReceived', 'options': {}},
         webhookId='plan-v1-borrador', credentials=CRED_WP),
    http_wp('p2', 'Leer contexto', [220, 0], 'GET', f"={WP}/plan/{{{{ $json.body.id }}}}/contexto"),
    iff('p3', '¿Contexto leído?', [440, 0], '={{ $json.statusCode === 200 && !!$json.body && $json.body.ok === true }}'),
    # Llegó tarde si el plan ya no espera un borrador (POST /borrador solo lo acepta en «generando», o en «error» todavía
    # sin contenido; Task 7 y D5): otra ejecución ya lo guardó, o Luis lo destrabó y siguió. Falso termina aquí: sin
    # gastar en GPT-4o ni tocar el plan.
    iff('p14', '¿Espera respuesta?', [660, -120],
        "={{ $json.body.estado === 'generando' || ($json.body.estado === 'error' && !($json.body.plan_actual"
        " && Array.isArray($json.body.plan_actual.fases) && $json.body.plan_actual.fases.length > 0)) }}"),
    node('p4', 'Preparar pedido', 'n8n-nodes-base.code', 2, [880, -120], {'jsCode': LIB + CODE_PEDIDO}),
    node('p5', 'Redactar plan', 'n8n-nodes-base.openAi', 1, [1100, -120],
         {'resource': 'chat', 'model': 'gpt-4o',
          'prompt': {'messages': [{'role': 'system', 'content': PROMPT_PLAN}, {'content': '={{ $json.mensaje }}'}]},
          'options': {'temperature': 0.3}, 'requestOptions': {}},
         credentials=CRED_OPENAI, onError='continueRegularOutput'),
    node('p6', 'Leer plan', 'n8n-nodes-base.code', 2, [1320, -120], {'jsCode': LIB + CODE_LEER}),
    iff('p7', '¿Plan legible?', [1540, -120], '={{ $json.ok === true }}'),
    http_wp('p8', 'Guardar borrador', [1760, -240], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/borrador",
            "={{ JSON.stringify({ plan: $json.plan, origen: 'borrador' }) }}", timeout=60000),
    iff('p9', '¿Guardado?', [1980, -240], '={{ $json.statusCode === 200 && !!$json.body && $json.body.ok === true }}'),
    iff('p16', '¿Vista previa pedida?', [2200, -360],
        "={{ ![].concat(($json.body && $json.body.avisos) || []).some((a) => String(a).startsWith("
        + json.dumps(AVISO_SIN_VISTA) + ")) }}"),
    node('p17', 'Armar correo sin vista', 'n8n-nodes-base.code', 2, [2420, -360], {'jsCode': correo_sin_vista('borrador')}),
    # 409 = WordPress dice que este borrador llegó tarde (el plan ya siguió): no se marca error ni se escribe.
    iff('p15', '¿Llegó tarde?', [2200, -120], '={{ $json.statusCode === 409 }}'),
    node('p10', 'Motivo del error', 'n8n-nodes-base.code', 2, [2420, 120], {'jsCode': LIB + code_motivo('borrador')}),
    http_wp('p11', 'Marcar error', [2640, 120], 'POST', f"={WP}/plan/{{{{ $json.id }}}}/error",
            "={{ JSON.stringify({ nota: $json.reason + ' (ejecución ' + $json.exec + ')' }) }}"),
    node('p12', 'Armar correo error', 'n8n-nodes-base.code', 2, [2860, 120], {'jsCode': correo_error_plan('borrador')}),
    node('p13', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [3080, -120],
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
link('¿Espera respuesta?', 'Preparar pedido', 0)
link('Preparar pedido', 'Redactar plan')
link('Redactar plan', 'Leer plan')
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

wf = {'name': 'Plan de trabajo · 1 Borrador', 'nodes': nodes, 'connections': connections,
      # Si el flujo se cae sin llegar a su propio aviso, «Propuestas v3 · 0 Avisar error» le escribe a Luis.
      'settings': {'executionOrder': 'v1', 'errorWorkflow': 'm7TOfKznVSBGz4Nd'}}
out = os.path.join(AQUI, 'plan-1-borrador.json')
with open(out, 'w', encoding='utf-8') as fh:
    json.dump(wf, fh, ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
