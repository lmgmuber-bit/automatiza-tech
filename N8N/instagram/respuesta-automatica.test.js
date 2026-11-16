// Prueba del filtro de respuestas automáticas de otros negocios en el nodo «Validar» del flujo principal, tal como lo
// genera build_agente_instagram.py. Sin red ni n8n. Correr: node N8N/instagram/respuesta-automatica.test.js
const fs = require('fs');
const path = require('path');

const wf = JSON.parse(fs.readFileSync(path.join(__dirname, 'agente-tech-instagram.json'), 'utf8'));
const codigo = wf.nodes.find(n => n.name === 'Validar').parameters.jsCode;
const correr = texto => new Function('$json', codigo)({ body: {
  event: 'new_message', channel_id: 2, conversation_id: 29, prueba: true,
  contact: { external_id: '1234567890', name: 'Negocio' },
  message: { id: 'm1', type: 'text', content: texto },
  callback_token: 'a'.repeat(64), callback_url: 'https://automatizatech.cl/x',
} });

let fallas = 0;
const ok = (c, m, d) => { console.log((c ? 'ok    ' : 'FALLA ') + m + (c ? '' : '  ' + JSON.stringify(d))); if (!c) fallas++; };
const calla = (t, m) => { const r = correr(t); ok(Array.isArray(r) && r.length === 0, 'no responde: ' + m, r); };
const pasa = (t, m) => { const r = correr(t); ok(r.length === 1 && r[0].json.texto === t.trim().slice(0, 2000), 'responde: ' + m, r); };

// Respuestas automáticas típicas de un negocio (WhatsApp Business / Instagram).
calla('¡Hola! Gracias por escribirnos. En este momento no estamos disponibles, te responderemos a la brevedad.', 'gracias + te responderemos');
calla('Gracias por contactarnos 😊 Nuestro horario de atención es de lunes a viernes de 9:00 a 18:00 hrs.', 'gracias + horario de atención');
calla('Este es un mensaje automático. Gracias por comunicarte con Pastelería Dulce, pronto te contestaremos.', 'mensaje automático + gracias');
calla('Hola, gracias por tu mensaje! Elige una opción:\n1️⃣ Ver catálogo\n2️⃣ Hacer un pedido\n3️⃣ Hablar con una persona', 'menú con opciones numeradas');
calla('Respuesta automática: estamos fuera de horario. Escribe 1 para ventas\n2 para soporte', 'respuesta automática + menú');
calla('Thank you for your message! This is an automated reply, we will get back to you as soon as possible.', 'en inglés');
calla('GRACIAS POR ESCRIBIRNOS, LE RESPONDEREMOS LO ANTES POSIBLE', 'en mayúsculas y sin tildes');
// Texto real de la conversación 29 (15-nov).
calla('¡Hola! Gracias por ponerte en contacto con nosotros. Hemos recibido tu mensaje y agradecemos tu interés.', 'conversación 29 (real)');
// Publicidad masiva de plantilla, como la conversación 27: título en negrita, saludo con el nombre vacío y texto largo.
const relleno = '\n• Ahorro de tiempo en tus procesos contables.\n• Reportes al día para tomar mejores decisiones.\n• Cumplimiento tributario sin sorpresas.\n• Más control del flujo de caja de tu empresa.';
calla('*Empresa X | Top 9 beneficios de la gestión financiera y contable* 💪🏻\nHola , ¿Cómo estás?\nTe comparto los beneficios de trabajar con nosotros:' + relleno + relleno, 'publicidad masiva con título y nombre vacío');

// Personas reales con rasgos parecidos: no se callan.
pasa('Hola , quiero saber cuánto demora una web', 'persona que escribe «Hola ,» en un mensaje corto');
pasa('*Urgente* necesito un sistema de reservas para mi spa, ¿me ayudan?', 'persona que usa negrita en un mensaje corto');
pasa('Gracias por ponerte en contacto conmigo tan rápido, ¿cómo seguimos?', 'agradece el contacto sin voz de negocio');

// Personas reales: nunca se callan.
pasa('Hola, ¿qué tipo de negocios pueden beneficiarse del diagnóstico?', 'pregunta del anuncio');
pasa('No gracias, prefiero investigar por mi cuenta primero', 'prefiere investigar');
pasa('Tengo una respuesta automática en mi WhatsApp y nadie la lee, ¿ustedes pueden mejorarla?', 'pregunta por respuestas automáticas');
pasa('Gracias por responder! Quiero agendar el diagnóstico para el martes', 'agradece y agenda');
pasa('Mi horario de atención es de 9 a 18, ¿el bot puede responder fuera de horario?', 'habla de su horario de atención');
pasa('1. ventas 2. soporte, así tengo mi menú hoy. ¿Se puede automatizar?', 'describe su menú en una línea');
pasa('Gracias, te escribo a la brevedad cuando tenga los datos', 'promete escribir, sin agradecer el contacto');

console.log(fallas ? `\n${fallas} FALLA(S)` : '\nTODO OK');
process.exit(fallas ? 1 : 0);
