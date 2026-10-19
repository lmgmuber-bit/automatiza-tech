"""Genera «Retomar · Redactar» INACTIVO y sin red: la redacción con IA de los textos de Retomar y sus encargos de foto.

Lo llama WordPress (at_rt_pedir_redaccion, inc/retomar/redaccion.php) con {id, generacion} y la cabecera X-AT-Secret.
Lee el contexto del seguimiento (WordPress ya lo deja sin líneas con montos), pide los textos a GPT-4o y los guarda por el
callback POST .../retomar/{id}/redaccion. Con eso WordPress valida (largo, líneas, montos, láminas), guarda y pide la
presentación al flujo «Retomar · Render».

Respuestas del callback (contrato de at_rt_guardar_redaccion):
  - 200 ok            : listo; el flujo termina.
  - 200 {reintento}   : trae montos; WordPress vuelve a pedir la redacción por su cuenta; el flujo termina.
  - 409               : el pedido ya no es el vigente (llegó tarde); el flujo termina, nunca reintenta.
  - 422               : la validación falló; WordPress YA dejó la redacción en «error» con el motivo exacto
                        (at_rt_red_rechazar). El flujo termina sin mandar ok:false: un segundo POST pisaría ese
                        motivo con uno más pobre. Es la opción más simple y la que conserva más información.
Si el flujo no llega a guardar (red, caída de WordPress), la redacción queda «pendiente» y el reloj la vuelve a pedir
pasadas 6 horas (at_rt_red_atascada); por eso aquí no hay reintentos del callback.

El nodo OpenAI heredado (n8n-nodes-base.openAi v1) no tiene opción de formato JSON (se revisó su definición), igual que en
los flujos del plan de trabajo: el prompt exige JSON estricto y «Leer respuesta» lo lee con leerJsonModelo (json_guard.py).

Credenciales: solo id y nombre; ningún valor secreto. El generador no accede a n8n, a Meta ni a OpenAI.
"""
import json
import os
import sys

sys.dont_write_bytecode = True  # no dejar __pycache__ dentro de ../propuestas-v3
AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'propuestas-v3'))
from fotos_guard import JS_LIMPIAR_FOTOS  # noqa: E402
from json_guard import JS_LEER_JSON  # noqa: E402

CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
LIB = JS_LEER_JSON + '\n' + JS_LIMPIAR_FOTOS + '\n'

PROMPT_REDACTAR = """Eres el equipo de AutomatizaTech, una agencia chilena de automatización y desarrollo web. Hace unas semanas le enviamos una propuesta a un prospecto y no hemos vuelto a saber de él. Vas a redactar los textos de una presentación breve para retomar la conversación con calidez y sin presionar. Recibes en JSON: el nombre y el negocio del prospecto (nombre, negocio); si tiene una demo (tiene_demo); el estado de la propuesta (estado_propuesta) y lo conversado (propuesta.resumen y propuesta.transcripcion; pueden venir vacíos); notas internas del equipo (notas); un pedido especial de quien revisa (pedido; puede venir vacío: si viene, manda sobre lo demás); los textos que ya existen (textos_actuales; pueden venir vacíos, úsalos solo como punto de partida); hechos registrados del contacto (historial); los límites (reglas) y las láminas que llevan foto nueva (slides_foto). Responde SOLO un objeto JSON (sin texto antes ni después, sin bloques de código) con esta forma exacta:
{
 "textos": {
  "quedamos": "texto",
  "proponemos": "texto",
  "partir": "texto",
  "recomendacion": "texto"
 },
 "image_briefs": [{"slide": "una lámina de slides_foto", "prompt": "descripción en inglés, de 40 a 60 palabras"}]
}
Qué va en cada texto (cada uno se muestra en su lámina de la presentación):
- quedamos («En qué quedamos»): lo que conversamos y quedó pendiente, con las necesidades del prospecto.
- proponemos («Lo que proponemos»): lo que le planteamos, ajustado a lo que necesita.
- partir («Podemos partir por lo prioritario y ajustarnos a tu presupuesto»): cómo podemos empezar por etapas, por lo más importante, y crecer después. Si en lo conversado o en las notas el presupuesto era una preocupación, ajústate a eso; si no, igual propón partir en etapas.
- recomendacion («Nuestra recomendación»): una recomendación concreta y breve de por dónde empezar.
Reglas de redacción:
- Español de Chile, cercano y profesional, en la voz del equipo en plural («te dejamos», «podemos», «te proponemos»), tuteando. Frases simples y concretas. Sin presionar, sin culpar por el silencio y sin urgencia falsa.
- Largo, que revisa el sistema (si te pasas, la respuesta se rechaza): quedamos, proponemos y partir, cada uno de 1 a 600 caracteres y como máximo 6 líneas; recomendacion, como máximo 300 caracteres y 3 líneas.
- NINGÚN monto ni precio, de ningún tipo: no escribas «$», «CLP», «USD», «UF», «pesos», «dólares», cifras con separador de miles ni descuentos con cifras. Si los datos traen precios, ignóralos. Del presupuesto habla solo con palabras («ajustarnos a tu presupuesto»).
- No inventes: ni nombres de personas, teléfonos, correos, direcciones, plazos, cifras, funcionalidades ni resultados que no estén en los datos. Si falta información sobre lo conversado, redacta en general y con amabilidad sobre el negocio y lo propuesto, sin inventar detalles.
- No escribas enlaces: la presentación ya los trae. No saludes ni te despidas dentro de los textos y no repitas el nombre del prospecto más de una vez.
- Texto plano: sin Markdown, sin viñetas con símbolos y sin emojis. Usa saltos de línea solo si ayudan a leer.
Encargos de foto (image_briefs):
- Exactamente uno por cada lámina de slides_foto y ninguno más. Las láminas son «portada» (fondo de la primera lámina), «cierre» (lámina final) y «demo» (junto a la demo).
- Cada uno es una fotografía realista, cálida y horizontal 16:9 de una escena del RUBRO de este prospecto (deduce el rubro del negocio, de la propuesta y de lo conversado): su gente trabajando o atendiendo, sus clientes, sus productos y sus lugares. Siempre con personas en acción.
- Muestra el MUNDO DEL PROSPECTO, nunca el trabajo de AutomatizaTech: nada de computadores, celulares con pantalla, oficinas, reuniones, papeles, planos ni gráficos.
- SIN texto de ningún tipo: nada de letreros, carteles, menús, pizarras, hojas, etiquetas, envases con texto, poleras con letras ni logos. Si un producto del rubro lleva etiqueta (botellas, cajas, libros), descríbelo sin etiqueta, de espaldas o liso (por ejemplo «unlabeled bottle»). En una tienda o local, primer plano con el fondo desenfocado.
- portada: primer plano de las manos o de un detalle del oficio, con el fondo completamente desenfocado. cierre: una escena esperanzadora del rubro (una entrega que llega, el equipo del negocio celebrando). demo: el negocio atendiendo con calma a una persona.
- Cada prompt va en inglés, tiene de 40 a 60 palabras (máximo 400 caracteres) y termina con: «no signs, no labels, no text, no lettering, no logos, no watermarks»."""

CODE_PEDIDO = r"""// Lo que ve el modelo: el contexto que WordPress ya dejó sin líneas con montos (la IA no ve precios) y las láminas con foto.
// No se le mandan ni el id ni la generación, ni las direcciones de las demos: los textos no llevan enlaces.
const c = ($input.first().json || {}).body || {};
const cadena = (v, max) => String(v == null ? '' : v).slice(0, max);
const prop = esObjetoPlano(c.propuesta) ? c.propuesta : {};
const actuales = esObjetoPlano(c.textos_actuales) ? c.textos_actuales : {};
const laminas = (Array.isArray(c.slides_foto) ? c.slides_foto : []).filter((s) => ['portada', 'cierre', 'demo'].includes(s));
const datos = {
  nombre: cadena(c.nombre, 100),
  negocio: cadena(c.negocio, 150),
  tiene_demo: Array.isArray(c.demo_urls) && c.demo_urls.length > 0,
  estado_propuesta: cadena(prop.status, 30),
  propuesta: { resumen: cadena(prop.resumen, 1200), transcripcion: cadena(prop.transcripcion, 12000) },
  notas: cadena(c.notas, 2000),
  pedido: cadena(c.pedido, 2000),
  textos_actuales: {
    quedamos: cadena(actuales.quedamos, 600), proponemos: cadena(actuales.proponemos, 600),
    partir: cadena(actuales.partir, 600), recomendacion: cadena(actuales.recomendacion, 300),
  },
  historial: (Array.isArray(c.historial) ? c.historial : []).slice(0, 10).map((h) => cadena(h, 200)),
  reglas: esObjetoPlano(c.reglas) ? c.reglas : {},
  slides_foto: laminas.length ? laminas : ['portada', 'cierre'],
};
return [{ json: { mensaje: JSON.stringify(datos) } }];"""

CODE_LEER = r"""// Nunca lanza: si la respuesta no sirve, ok=false con el motivo y WordPress pasa la redacción a «error».
// El largo, las líneas, los montos y las láminas los valida WordPress (callback .../redaccion); aquí solo se cuida la forma.
const hook = $('Webhook').first().json.body || {};
const ctx = $('Leer contexto').first().json.body || {};
const id = parseInt(hook.id, 10) || 0;
const generacion = String(hook.generacion || '');
const permitidas = (Array.isArray(ctx.slides_foto) ? ctx.slides_foto : []).filter((s) => ['portada', 'cierre', 'demo'].includes(s));
const fallo = (motivo) => [{ json: { id, callback: { generacion, ok: false, nota: motivo + ' (ejecución ' + $execution.id + ')' } } }];
const ia = $('Redactar').first().json || {};
const crudo = ia.message && ia.message.content;
if (!crudo) return fallo('La IA no respondió.');
let r;
try { r = leerJsonModelo(crudo); } catch (e) { return fallo('La IA no devolvió un JSON legible.'); }
if (!esObjetoPlano(r) || !esObjetoPlano(r.textos)) return fallo('La IA no devolvió los textos.');
const t = r.textos;
for (const k of ['quedamos', 'proponemos', 'partir']) {
  if (typeof t[k] !== 'string' || !t[k].trim()) return fallo('La IA no escribió el texto «' + k + '».');
}
const textos = { quedamos: t.quedamos, proponemos: t.proponemos, partir: t.partir,
  recomendacion: typeof t.recomendacion === 'string' ? t.recomendacion : '' };
// Un encargo por lámina permitida; lo que sobre, se repita o venga vacío se descarta antes de que WordPress rechace todo.
const vistas = new Set();
const encargos = [];
for (const b of (Array.isArray(r.image_briefs) ? r.image_briefs : [])) {
  if (!esObjetoPlano(b) || typeof b.slide !== 'string' || typeof b.prompt !== 'string') continue;
  if (!permitidas.includes(b.slide) || vistas.has(b.slide) || !b.prompt.trim()) continue;
  vistas.add(b.slide);
  encargos.push({ slide: b.slide, prompt: b.prompt });
}
// Reglas de fotos (fotos_guard.py): sin texto, letreros ni etiquetas; siempre con el cierre de prohibiciones.
// El filtro aplica sus reglas de portada (fondo sin estadios, fachadas ni calles; primer plano desenfocado) a la lámina
// «cover»; la de Retomar se llama «portada». Se traduce al entrar y se deshace al salir: el callback conserva «portada».
const f = limpiarFotos(encargos.map((b) => ({ slide: b.slide === 'portada' ? 'cover' : b.slide, prompt: b.prompt })));
const limpias = f.limpias.map((b) => ({ slide: b.slide === 'cover' ? 'portada' : b.slide, prompt: b.prompt }));
const notas = [];
if (f.reemplazadas) notas.push(f.reemplazadas + ' encargo(s) de foto se cambiaron por una escena neutra porque pedían texto, pantallas o productos con etiqueta.');
return [{ json: { id, callback: { generacion, ok: true, textos, image_briefs: limpias, nota: notas.join(' ') } } }];"""

CODE_FALLA_CONTEXTO = r"""// Sin copiar mensajes de red: pueden traer direcciones internas del servidor.
const hook = $('Webhook').first().json.body || {};
const le = $('Leer contexto').first().json || {};
const estado = Number(le.statusCode) || 0;
const nota = 'No se pudo leer el contexto del seguimiento (' + (estado ? 'HTTP ' + estado : 'sin respuesta') + ') (ejecución ' + $execution.id + ')';
return [{ json: { id: parseInt(hook.id, 10) || 0, callback: { generacion: String(hook.generacion || ''), ok: false, nota } } }];"""


def node(id_, name, kind, version, pos, parameters, **extra):
    return {'id': id_, 'name': name, 'type': 'n8n-nodes-base.' + kind,
            'typeVersion': version, 'position': pos, 'parameters': parameters, **extra}


def http_wp(id_, name, pos, method, url, body_expr=None, timeout=30000):
    """HTTP a WordPress: sin seguir redirecciones (reenviarían X-AT-Secret) y sin lanzar por el código HTTP."""
    params = {'method': method, 'url': url, 'authentication': 'genericCredentialType',
              'genericAuthType': 'httpHeaderAuth',
              'options': {'timeout': timeout, 'redirect': {'redirect': {'followRedirects': False}},
                          'response': {'response': {'fullResponse': True, 'neverError': True}}}}
    if body_expr:
        params.update({'sendBody': True, 'specifyBody': 'json', 'jsonBody': body_expr})
    # neverError no cubre timeouts ni cortes de conexión: con continueRegularOutput pasa {error} sin statusCode.
    return node(id_, name, 'httpRequest', 4.2, pos, params, credentials=CRED_WP, onError='continueRegularOutput')


def iff(id_, name, pos, left):
    return node(id_, name, 'if', 2.2, pos, {
        'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose', 'version': 2},
                       'conditions': [{'id': id_ + '-c', 'leftValue': left, 'rightValue': True,
                                       'operator': {'type': 'boolean', 'operation': 'true', 'singleValue': True}}],
                       'combinator': 'and'},
        'options': {}})


nodes = [
    node('rd1', 'Webhook', 'webhook', 2, [0, 0],
         {'httpMethod': 'POST', 'path': 'retomar-redactar', 'authentication': 'headerAuth',
          'responseMode': 'onReceived', 'options': {}}, webhookId='retomar-redactar', credentials=CRED_WP),
    http_wp('rd2', 'Leer contexto', [240, 0], 'GET', f"={WP}/retomar/{{{{ parseInt($json.body.id, 10) }}}}/contexto"),
    iff('rd3', '¿Contexto leído?', [480, 0],
        '={{ $json.statusCode === 200 && !!$json.body && Number($json.body.id) > 0 }}'),
    # Llegó tarde si WordPress ya pidió otra redacción de este seguimiento: se termina sin gastar en GPT-4o
    # (el callback con una generación vieja volvería de todos modos con 409).
    iff('rd4', '¿Es el pedido vigente?', [720, -120],
        "={{ $json.body.generacion === $('Webhook').first().json.body.generacion }}"),
    node('rd5', 'Preparar pedido', 'code', 2, [960, -120], {'jsCode': LIB + CODE_PEDIDO}),
    node('rd6', 'Redactar', 'openAi', 1, [1200, -120],
         {'resource': 'chat', 'model': 'gpt-4o',
          'prompt': {'messages': [{'role': 'system', 'content': PROMPT_REDACTAR}, {'content': '={{ $json.mensaje }}'}]},
          'options': {'temperature': 0.4}, 'requestOptions': {}},
         credentials=CRED_OPENAI, onError='continueRegularOutput'),
    node('rd7', 'Leer respuesta', 'code', 2, [1440, -120], {'jsCode': LIB + CODE_LEER}),
    node('rd8', 'Falla de contexto', 'code', 2, [720, 120], {'jsCode': CODE_FALLA_CONTEXTO}),
    # 200 listo o reintento, 409 tarde y 422 inválida (WordPress ya marcó el error): en todos los casos termina aquí.
    http_wp('rd9', 'Guardar redacción', [1680, 0], 'POST', f"={WP}/retomar/{{{{ $json.id }}}}/redaccion",
            '={{ JSON.stringify($json.callback) }}', timeout=60000),
]

connections = {}


def link(a, b, output=0):
    connections.setdefault(a, {'main': []})
    while len(connections[a]['main']) <= output:
        connections[a]['main'].append([])
    connections[a]['main'][output].append({'node': b, 'type': 'main', 'index': 0})


link('Webhook', 'Leer contexto')
link('Leer contexto', '¿Contexto leído?')
link('¿Contexto leído?', '¿Es el pedido vigente?', 0)
link('¿Contexto leído?', 'Falla de contexto', 1)
link('¿Es el pedido vigente?', 'Preparar pedido', 0)  # la salida «falso» no se conecta: termina sin hacer nada
link('Preparar pedido', 'Redactar')
link('Redactar', 'Leer respuesta')
link('Leer respuesta', 'Guardar redacción')
link('Falla de contexto', 'Guardar redacción')

workflow = {'name': 'Retomar · Redactar', 'active': False, 'nodes': nodes, 'connections': connections,
            'settings': {'executionOrder': 'v1'}}

if __name__ == '__main__':
    from pathlib import Path
    output = Path(__file__).with_name('retomar-redactar.json')
    output.write_text(json.dumps(workflow, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print('Generado workflow inactivo:', output.name, len(nodes), 'nodos')
