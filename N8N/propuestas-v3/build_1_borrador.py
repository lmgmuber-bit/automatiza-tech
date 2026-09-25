"""Construye el workflow n8n «Propuestas v3 · 1 Borrador» y lo guarda en 1-borrador.json.

Plan: Docs/superpowers/plans/2026-09-23-flujo-propuestas-v3.md, Task 10.
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os
from fotos_guard import JS_LIMPIAR_FOTOS
from correos import correo_borrador

CRED_OPENAI = {'openAiApi': {'id': 'g52IEXpRfN5r7jKw', 'name': 'OpenAi account'}}
CRED_SMTP = {'smtp': {'id': 'dyhVFWmjRNC45ccA', 'name': 'SMTP account PROD'}}
CRED_WP = {'httpHeaderAuth': {'id': '1NI0sJKc0kC430pb', 'name': 'AT REST Secret (header)'}}
WP = 'https://automatizatech.cl/?rest_route=/automatiza-tech/v1'
RENDERER = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/render'
LUIS = 'lmgm.0303@gmail.com'

PROMPT_REDACTAR = """Eres consultor senior de AutomatizaTech (Chile). Con la transcripción de una reunión con un cliente, redacta el contenido de una propuesta comercial que se mostrará con una plantilla fija de láminas. Responde SOLO un objeto JSON (sin texto antes ni después, sin bloques de código) con esta forma exacta:
{
 "client_name": "Nombre y apellido del cliente",
 "company_name": "Nombre del negocio",
 "phone": "teléfono del cliente si aparece, si no vacío",
 "challenge_title": "máx. 7 palabras",
 "challenge_text": "60 a 80 palabras, con datos concretos dichos en la reunión (cifras, lugares, experiencias). No inventes cifras.",
 "solution_title": "máx. 7 palabras",
 "solution_text": "60 a 80 palabras: qué hará AutomatizaTech y cómo resuelve cada problema mencionado",
 "benefits": [{"title": "máx. 5 palabras", "text": "una línea, empieza en minúscula"}],
 "how_it_works": [{"step_title": "máx. 4 palabras", "step_text": "una línea, empieza en minúscula"}],
 "extra_slides": [{"eyebrow": "etiqueta corta", "title": "título", "bullets": [{"title": "punto", "text": "una línea"}]}],
 "pricing_rows": [{"service": "nombre del servicio o fase", "price_usd": 0, "price_label": "Por confirmar"}],
 "pricing_note": "",
 "next_steps": ["paso concreto acordado en la reunión"],
 "correo_cliente": {"asunto": "máx. 12 palabras, con el nombre del negocio", "introduccion": "2 o 3 frases que retoman lo conversado en la reunión", "que_incluye": "2 o 3 frases con lo que trae la propuesta (servicios o fases), sin montos", "cierre": "1 o 2 frases con el siguiente paso acordado"},
 "image_briefs": [{"slide": "cover|challenge|solution|benefits|how_it_works|extra_1|extra_2|pricing|next_steps", "prompt": "descripción en inglés"}],
 "tono": "tu o usted",
 "resumen_para_luis": "3 a 5 líneas: qué pidió el cliente, presupuesto mencionado, dudas abiertas"
}
Reglas:
- Español de Chile. Trata al cliente de "tú", salvo rubros donde corresponde "usted" (funerario, salud, legal): ahí "usted" en todo el texto.
- benefits: 4 o 5. how_it_works: 4 o 5 pasos. next_steps: 3 o 4.
- extra_slides: 0, 1 o 2, solo si la reunión trae material real (por ejemplo fases o planes que el cliente pidió). No rellenes.
- PRECIOS: nunca escribas montos. Cada fila lleva "price_label": "Por confirmar" y "price_usd": 0. Las filas nombran los servicios o fases que se cotizarán. "pricing_note" vacío.
- image_briefs: una por lámina usada (cover, challenge, solution, benefits, how_it_works, pricing, next_steps y extra_N por cada extra). Fotografía realista, cálida y respetuosa, del RUBRO de este cliente: su gente trabajando o atendiendo, sus clientes, sus productos y sus lugares (por ejemplo, una academia de béisbol: niños entrenando, un bateador, un entrenador con su equipo; una funeraria: manos dejando un lirio, velas, una capilla serena). Se permiten personas en acción.
  · Cada foto muestra el MUNDO DEL CLIENTE, nunca la solución de AutomatizaTech: nada de celulares navegando, catálogos digitales, computadores, oficinas, reuniones, apretones de mano, planes ni documentos, aunque la lámina hable de tecnología.
  · Qué mostrar en cada lámina: cover = primer plano de las manos o de un detalle del oficio del rubro; challenge = el negocio en su momento más exigente (por ejemplo, un mesón lleno un viernes en la noche); solution = un cliente del rubro disfrutando el producto o el servicio; benefits = la gente del negocio contenta atendiendo; how_it_works = manos haciendo el trabajo del rubro (preparar, envolver, entregar); pricing = un detalle del producto del rubro como bodegón; next_steps = una escena esperanzadora del rubro (una entrega que llega, un brindis, un equipo celebrando); extra_N = otra escena distinta del mismo rubro.
  · cover: SIEMPRE un primer plano de una persona o un objeto del rubro, con el fondo completamente desenfocado o con cielo detrás; nunca estadios, fachadas, calles, galerías ni muros de fondo (ahí el modelo de imagen inventa carteles).
  · Si el producto del rubro lleva etiqueta o pantalla (botellas, latas, cajas, celulares, libros), muéstralo sin etiqueta, de espaldas, apagado o desenfocado, o muestra el producto en uso (un vaso servido en vez de la botella); escríbelo así en el prompt (por ejemplo "unlabeled bottle", "phone screen facing away").
  · Nunca pantallas con contenido, sitios web, gráficos, documentos, pizarras, letreros, carteles, marcadores, menús ni texto de ningún tipo.
  · Cada prompt termina con: "no signs, no labels, no text, no lettering, no logos, no watermarks".
- No inventes datos del cliente (teléfonos, direcciones, precios, años) que no estén en la transcripción.
- correo_cliente: es el correo con el que Luis enviará la propuesta al cliente. Mismo tratamiento (tú/usted) que la propuesta. Sin saludo ni firma (la plantilla ya pone «Estimado/a <nombre>,» y «Atentamente, El equipo de Automatiza Tech»). Sin montos. No menciones enlaces, botones ni adjuntos (la plantilla los agrega). Cálido y concreto, con algo propio de la reunión."""

PROMPT_BOT = """Escribe el system prompt de un asistente virtual de demostración para este negocio, en español de Chile. Estructura: identidad (1 párrafo); TONO (tú o usted según el rubro, breve, sin emojis si el rubro es delicado); ATENCIÓN URGENTE (si aplica al rubro: primero empatía, luego el contacto directo del negocio); SERVICIOS; PRECIOS (solo los que aparezcan en la transcripción, con la aclaración de que un asesor confirma); REGLAS (no inventar datos; derivar a un humano cuando hay intención clara de contratar pidiendo nombre, teléfono y comuna). Usa solo datos que estén en la transcripción. Devuelve solo el texto del system prompt."""

CODE_ARMAR = r"""// Reglas que no se le confían al modelo: formato JSON, precios y cantidad de láminas extra.
let raw = $('Redactar propuesta').first().json.message.content;
if (typeof raw === 'string') {
  raw = raw.trim().replace(/^```(?:json)?\s*/i, '').replace(/```\s*$/, '');
}
const d = typeof raw === 'string' ? JSON.parse(raw) : raw;
const body = $('Webhook (Entrada)').first().json.body || {};
const prueba = body.prueba === true;
d.pricing_rows = (d.pricing_rows || []).map((r) => ({ service: String(r.service || 'Servicio'), price_usd: 0, price_label: 'Por confirmar' }));
if (!d.pricing_rows.length) d.pricing_rows = [{ service: 'Servicio', price_usd: 0, price_label: 'Por confirmar' }];
d.pricing_note = '';
d.extra_slides = (d.extra_slides || []).slice(0, 2);
const validSlides = new Set(['cover', 'challenge', 'solution', 'benefits', 'how_it_works', 'pricing', 'next_steps', ...d.extra_slides.map((_, i) => `extra_${i + 1}`)]);
d.image_briefs = limpiarFotos((d.image_briefs || []).filter((b) => b && validSlides.has(b.slide) && b.prompt)).limpias;
if (prueba) d.company_name = `[PRUEBA] ${d.company_name}`;
const resumen = d.resumen_para_luis || '';
const phone = d.phone || '';
delete d.resumen_para_luis; delete d.tono; delete d.phone;
const cc = (d.correo_cliente && typeof d.correo_cliente === 'object') ? d.correo_cliente : {};
d.correo_cliente = { asunto: String(cc.asunto || '').trim(), introduccion: String(cc.introduccion || '').trim(), que_incluye: String(cc.que_incluye || '').trim(), cierre: String(cc.cierre || '').trim() };
return [{ json: {
  payload: d,
  resumen,
  phone,
  client_email: prueba ? 'contacto@automatizatech.cl' : (body.client_email || ''),
  transcript: body.transcript || '',
  system_prompt: $('Personalidad del chatbot').first().json.message.content,
} }];"""



def node(id_, name, type_, version, pos, params, **extra):
    n = {'id': id_, 'name': name, 'type': type_, 'typeVersion': version, 'position': pos, 'parameters': params}
    n.update(extra)
    return n


nodes = [
    node('b1', 'Webhook (Entrada)', 'n8n-nodes-base.webhook', 2, [0, 0],
         {'httpMethod': 'POST', 'path': 'propuesta-v3-borrador', 'responseMode': 'onReceived', 'options': {}},
         webhookId='propuesta-v3-borrador'),
    node('b2', 'Redactar propuesta', 'n8n-nodes-base.openAi', 1, [220, 0],
         {'resource': 'chat', 'model': 'gpt-4o',
          'prompt': {'messages': [{'role': 'system', 'content': PROMPT_REDACTAR},
                                  {'content': "={{ $('Webhook (Entrada)').item.json.body.transcript }}"}]},
          'options': {'temperature': 0.4}, 'requestOptions': {}},
         credentials=CRED_OPENAI),
    node('b3', 'Personalidad del chatbot', 'n8n-nodes-base.openAi', 1, [440, 0],
         {'resource': 'chat', 'model': 'gpt-4o',
          'prompt': {'messages': [{'role': 'system', 'content': PROMPT_BOT},
                                  {'content': "={{ $('Webhook (Entrada)').item.json.body.transcript }}"}]},
          'options': {'temperature': 0.4}, 'requestOptions': {}},
         credentials=CRED_OPENAI),
    node('b4', 'Armar payload', 'n8n-nodes-base.code', 2, [660, 0], {'jsCode': JS_LIMPIAR_FOTOS + '\n' + CODE_ARMAR}),
    node('b5', 'Crear en WordPress', 'n8n-nodes-base.httpRequest', 4.2, [880, 0],
         {'method': 'POST', 'url': f'{WP}/proposal', 'authentication': 'genericCredentialType',
          'genericAuthType': 'httpHeaderAuth', 'sendBody': True, 'specifyBody': 'json',
          'jsonBody': '={{ JSON.stringify({ client_email: $json.client_email, phone: $json.phone, transcript: $json.transcript, system_prompt: $json.system_prompt, payload: $json.payload }) }}',
          'options': {}},
         credentials=CRED_WP),
    node('b6', 'Vista previa (sin fotos)', 'n8n-nodes-base.httpRequest', 4.2, [1100, 0],
         {'method': 'POST', 'url': RENDERER, 'sendBody': True, 'specifyBody': 'json',
          'jsonBody': "={{ JSON.stringify(Object.assign({}, $('Armar payload').item.json.payload, { unique_id: $('Crear en WordPress').item.json.unique_id, draft: true, image_briefs: [] })) }}",
          'options': {'timeout': 120000}},
         onError='continueRegularOutput'),
    node('b6b', 'Armar correo', 'n8n-nodes-base.code', 2, [1210, 0], {'jsCode': correo_borrador()}),
    node('b7', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [1320, 0],
         {'fromEmail': 'contacto@automatizatech.cl', 'toEmail': LUIS,
          'subject': '={{ $json.asunto }}', 'html': '={{ $json.html }}', 'options': {}},
         credentials=CRED_SMTP),
]
order = [n['name'] for n in nodes]
connections = {a: {'main': [[{'node': b, 'type': 'main', 'index': 0}]]} for a, b in zip(order, order[1:])}

wf = {'name': 'Propuestas v3 · 1 Borrador', 'nodes': nodes, 'connections': connections,
      # Si el flujo se cae sin llegar a su propio aviso, «0 Avisar error» (build_0_errores.py) le escribe a Luis.
      'settings': {'executionOrder': 'v1', 'errorWorkflow': 'm7TOfKznVSBGz4Nd'}}
out = os.path.join(os.path.dirname(os.path.abspath(__file__)), '1-borrador.json')
json.dump(wf, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
