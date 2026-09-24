"""Construye el workflow n8n «Propuestas v3 · 1 Borrador» y lo guarda en 1-borrador.json.

Plan: Docs/superpowers/plans/2026-09-23-flujo-propuestas-v3.md, Task 10.
Solo referencia credenciales por id; no contiene secretos.
"""
import json, os

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
 "image_briefs": [{"slide": "cover|challenge|solution|benefits|how_it_works|extra_1|extra_2|pricing|next_steps", "prompt": "descripción en inglés"}],
 "tono": "tu o usted",
 "resumen_para_luis": "3 a 5 líneas: qué pidió el cliente, presupuesto mencionado, dudas abiertas"
}
Reglas:
- Español de Chile. Trata al cliente de "tú", salvo rubros donde corresponde "usted" (funerario, salud, legal): ahí "usted" en todo el texto.
- benefits: 4 o 5. how_it_works: 4 o 5 pasos. next_steps: 3 o 4.
- extra_slides: 0, 1 o 2, solo si la reunión trae material real (por ejemplo fases o planes que el cliente pidió). No rellenes.
- PRECIOS: nunca escribas montos. Cada fila lleva "price_label": "Por confirmar" y "price_usd": 0. Las filas nombran los servicios o fases que se cotizarán. "pricing_note" vacío.
- image_briefs: una por lámina usada (cover, challenge, solution, benefits, how_it_works, pricing, next_steps y extra_N por cada extra). Fotografía realista, cálida y respetuosa del rubro. Cada prompt termina con: "no people facing camera, no screens, no phones, no computers, no papers, no signs, no text, no lettering, no logos, no watermarks". Nunca infografías, diagramas ni oficinas con papeles.
- No inventes datos del cliente (teléfonos, direcciones, precios, años) que no estén en la transcripción."""

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
d.image_briefs = (d.image_briefs || []).filter((b) => b && validSlides.has(b.slide) && b.prompt);
if (prueba) d.company_name = `[PRUEBA] ${d.company_name}`;
const resumen = d.resumen_para_luis || '';
const phone = d.phone || '';
delete d.resumen_para_luis; delete d.tono; delete d.phone;
return [{ json: {
  payload: d,
  resumen,
  phone,
  client_email: prueba ? 'contacto@automatizatech.cl' : (body.client_email || ''),
  transcript: body.transcript || '',
  system_prompt: $('Personalidad del chatbot').first().json.message.content,
} }];"""

EMAIL_HTML = """=<h3>{{ $('Vista previa (sin fotos)').item.json.view_url ? '' : '⚠️ ' }}Borrador de propuesta: {{ $('Armar payload').item.json.payload.company_name }}</h3>
<p style="white-space:pre-wrap">{{ $('Armar payload').item.json.resumen }}</p>
{{ $('Vista previa (sin fotos)').item.json.view_url ? '' : '<p style="color:#b45309"><strong>La vista previa no se pudo generar.</strong> La propuesta quedó creada en borrador: en el panel, «Pedir cambios» (aunque sea sin comentario) vuelve a generarla.</p>' }}
<p><a href="{{ $('Crear en WordPress').item.json.view_url }}">👀 Ver vista previa (sin fotos)</a></p>
<p><a href="{{ $('Crear en WordPress').item.json.panel_url }}">✏️ Revisar en el panel: precios, comentarios y aprobación</a></p>
<p style="color:#666">Los precios dicen «Por confirmar» hasta que los escribas en el panel. No se ha gastado nada en fotos. Nada se envía al cliente desde este flujo.</p>"""


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
    node('b4', 'Armar payload', 'n8n-nodes-base.code', 2, [660, 0], {'jsCode': CODE_ARMAR}),
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
    node('b7', 'Correo a Luis', 'n8n-nodes-base.emailSend', 1, [1320, 0],
         {'fromEmail': 'contacto@automatizatech.cl', 'toEmail': LUIS,
          'subject': "={{ ($('Vista previa (sin fotos)').item.json.view_url ? '' : '⚠️ ') + $('Armar payload').item.json.payload.company_name + ' · borrador listo para revisar' }}",
          'html': EMAIL_HTML, 'options': {}},
         credentials=CRED_SMTP),
]
order = [n['name'] for n in nodes]
connections = {a: {'main': [[{'node': b, 'type': 'main', 'index': 0}]]} for a, b in zip(order, order[1:])}

wf = {'name': 'Propuestas v3 · 1 Borrador', 'nodes': nodes, 'connections': connections,
      'settings': {'executionOrder': 'v1'}}
out = os.path.join(os.path.dirname(os.path.abspath(__file__)), '1-borrador.json')
json.dump(wf, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
print('escrito', out, len(nodes), 'nodos')
