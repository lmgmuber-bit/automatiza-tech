// Pruebas de contexto.js. Correr: node --test N8N/wa-reconoce/nodos/
const test = require('node:test');
const assert = require('node:assert');
const { waTituloCorto, waLineaContexto, waElegirSeguimiento } = require('./contexto.js');

const agenda = (correo) => ({ nombre_completo: 'Julio Prueba', correo, empresa: 'Empresa Prueba' });
const proyecto = (id, nombre, codigo = '') => ({ tipo: 'proyecto', id, nombre, plan_codigo: codigo, agenda: agenda('julio@prueba.cl') });
const propuesta = (id, nombre) => ({ tipo: 'propuesta', id, nombre, plan_codigo: '', agenda: agenda('julio@prueba.cl') });
const ctx = (situacion, opciones, extra = {}) => ({ situacion, nombre: 'Julio', opciones, diagnostico_proximo: '', elegida: null, agenda: agenda('julio@prueba.cl'), ...extra });
const entrada = (extra = {}) => ({ buttonId: '', phoneNumber: '56911112222', phoneNumberId: '123', contactName: 'Julio WhatsApp', output: '📆 Te muestro el calendario', ...extra });
const PROP = { tiene: true, codigo: 'abc', nombre: 'María', empresa: 'Spa Prueba', estado: 'sent', propuesto: 'Sitio web, US$ 100', url: 'https://automatizatech.cl/x' };

test('título corto: recorta por caracteres y termina en …', () => {
  assert.strictEqual(waTituloCorto('Configurador Vitolio', 20), 'Configurador Vitolio');
  assert.strictEqual(waTituloCorto('Agenda y recordatorios para el spa', 20), 'Agenda y recordator…');
  assert.strictEqual(Array.from(waTituloCorto('Ñandú ñandú ñandú ñandú 😀😀', 24)).length <= 24, true);
});

test('línea: cliente con un proyecto, texto exacto', () => {
  assert.strictEqual(waLineaContexto(ctx('cliente', [proyecto(5, 'Proyecto Prueba')]), {}),
    '[Contexto interno, no la repitas: quien escribe es cliente de AutomatizaTech. Se llama Julio. Sus proyectos: «Proyecto Prueba».]');
});

test('línea: prospecto con dos propuestas y propuesta pendiente, en un solo corchete', () => {
  const l = waLineaContexto(ctx('prospecto', [propuesta(2, 'Sitio web'), propuesta(1, 'Agenda')]), PROP);
  assert.match(l, /^\[Contexto interno, no la repitas: quien escribe es un prospecto: ya tuvo una reunión con nosotros\. Se llama Julio\. Sus propuestas abiertas: «Sitio web», «Agenda»\. Además, tiene la propuesta de AutomatizaTech para Spa Prueba esperando respuesta\. Lo propuesto para partir: Sitio web, US\$ 100\. Enlace para responder: https:\/\/automatizatech\.cl\/x\]$/);
  assert.strictEqual((l.match(/\[/g) || []).length, 1);
});

test('línea: prospecto sin propuestas abiertas lo dice', () => {
  assert.match(waLineaContexto(ctx('prospecto', []), {}), /No tiene propuestas abiertas\.\]$/);
});

test('línea: persona nueva sin nada no agrega línea; con diagnóstico, lo recuerda', () => {
  assert.strictEqual(waLineaContexto(ctx('nuevo', [], { nombre: '' }), {}), '');
  assert.strictEqual(waLineaContexto(ctx('nuevo', [], { nombre: '', diagnostico_proximo: '2026-11-12 10:00' }), {}),
    '[Contexto interno, no la repitas: quien escribe es una persona nueva. Tiene un diagnóstico gratis agendado para el 12-11 a las 10:00.]');
});

test('línea: número en dos fichas no saluda por nombre; código del plan nombra el proyecto', () => {
  assert.match(waLineaContexto(ctx('cliente', [proyecto(5, 'A'), propuesta(3, 'B')], { nombre: '' }), {}), /Este número está en más de una ficha: no lo saludes por nombre\./);
  const p = proyecto(5, 'Configurador', 'Ab3dE6gH9jK1');
  assert.match(waLineaContexto(ctx('cliente', [p], { elegida: p }), {}), /Escribe desde el plan del proyecto «Configurador»\./);
});

test('línea: si la ruta falló queda el aviso de propuesta de hoy, tal cual', () => {
  assert.strictEqual(waLineaContexto({}, PROP),
    '[Contexto interno, no la repitas: este número tiene la propuesta de AutomatizaTech para Spa Prueba esperando respuesta. Lo propuesto para partir: Sitio web, US$ 100. Enlace para responder: https://automatizatech.cl/x]');
  assert.strictEqual(waLineaContexto({}, {}), '');
  assert.strictEqual(waLineaContexto(null, null), '');
});

test('línea: nunca lleva correos ni la palabra demo', () => {
  for (const c of [ctx('cliente', [proyecto(5, 'A')]), ctx('prospecto', [propuesta(1, 'B')]), ctx('nuevo', [], { diagnostico_proximo: '2026-11-12 10:00' })]) {
    const l = waLineaContexto(c, PROP);
    assert.strictEqual(l.includes('@'), false);
    assert.strictEqual(/demo/i.test(l), false);
  }
});

test('elegir: una opción va directo, con el plan en la nota', () => {
  const r = waElegirSeguimiento(ctx('cliente', [proyecto(5, 'Configurador', 'Ab3dE6gH9jK1')]), entrada());
  assert.strictEqual(r.modo, 'directo');
  assert.deepStrictEqual(r.guardar, { name: 'Julio Prueba', email: 'julio@prueba.cl', company: 'Empresa Prueba', subject: 'Seguimiento: Configurador',
    notes: 'Plan de trabajo Ab3dE6gH9jK1. Agendada por el cliente por WhatsApp.', contrato_id: 5, propuesta_id: 0 });
  assert.deepStrictEqual(Object.keys(r.datos).sort(), ['appointmentType', 'clientEmail', 'clientType', 'companyName', 'contactName', 'output', 'phoneNumber', 'phoneNumberId']);
  assert.strictEqual(r.datos.appointmentType, 'Seguimiento');
  assert.strictEqual(r.datos.clientEmail, 'julio@prueba.cl');
});

test('elegir: dos opciones piden elegir con botones de 20 caracteres o menos', () => {
  const r = waElegirSeguimiento(ctx('cliente', [proyecto(5, 'Configurador de muebles Vitolio'), propuesta(3, 'Agenda y recordatorios')]), entrada());
  assert.strictEqual(r.modo, 'elegir');
  assert.strictEqual(r.guardar, null);
  assert.strictEqual(r.mensaje.to, '56911112222');
  assert.strictEqual(r.mensaje.interactive.type, 'button');
  assert.strictEqual(r.mensaje.interactive.body.text, 'Hola Julio 👋 ¿Sobre cuál quieres agendar el seguimiento?');
  const b = r.mensaje.interactive.action.buttons;
  assert.deepStrictEqual(b.map((x) => x.reply.id), ['seg_c_5', 'seg_p_3']);
  assert.ok(b.every((x) => x.type === 'reply' && Array.from(x.reply.title).length <= 20));
});

test('elegir: textos según sean proyectos, propuestas o sin nombre', () => {
  assert.strictEqual(waElegirSeguimiento(ctx('cliente', [proyecto(1, 'A'), proyecto(2, 'B')]), entrada()).mensaje.interactive.body.text, 'Hola Julio 👋 ¿De cuál proyecto quieres agendar el seguimiento?');
  assert.strictEqual(waElegirSeguimiento(ctx('prospecto', [propuesta(1, 'A'), propuesta(2, 'B')], { nombre: '' }), entrada()).mensaje.interactive.body.text, 'Hola 👋 ¿Sobre cuál propuesta quieres conversar?');
});

test('elegir: cinco opciones van en lista; doce se cortan en diez', () => {
  const muchas = (n) => Array.from({ length: n }, (_, i) => propuesta(i + 1, `Propuesta de prueba número ${i + 1} con nombre largo`));
  const r = waElegirSeguimiento(ctx('prospecto', muchas(5)), entrada());
  assert.strictEqual(r.mensaje.interactive.type, 'list');
  assert.strictEqual(r.mensaje.interactive.action.button, 'Ver opciones');
  const filas = r.mensaje.interactive.action.sections[0].rows;
  assert.strictEqual(filas.length, 5);
  assert.ok(filas.every((f) => Array.from(f.title).length <= 24 && f.description === 'Propuesta'));
  assert.strictEqual(waElegirSeguimiento(ctx('prospecto', muchas(12)), entrada()).mensaje.interactive.action.sections[0].rows.length, 10);
});

test('elegir: títulos repetidos se distinguen con un número', () => {
  const r = waElegirSeguimiento(ctx('prospecto', [propuesta(2, 'Sitio web para la clínica dental'), propuesta(1, 'Sitio web para la clínica veterinaria')]), entrada());
  const t = r.mensaje.interactive.action.buttons.map((x) => x.reply.title);
  assert.notStrictEqual(t[0], t[1]);
  assert.match(t[1], / 2$/);
  assert.ok(t.every((x) => Array.from(x).length <= 20));
});

test('elegir: el botón elegido va directo con su propuesta; un botón viejo vuelve a preguntar', () => {
  const c = ctx('prospecto', [propuesta(4, 'Sitio web'), propuesta(3, 'Agenda')]);
  const r = waElegirSeguimiento(c, entrada({ buttonId: 'seg_p_3' }));
  assert.strictEqual(r.modo, 'directo');
  assert.strictEqual(r.guardar.propuesta_id, 3);
  assert.strictEqual(r.guardar.contrato_id, 0);
  assert.strictEqual(r.guardar.notes, 'Seguimiento de la propuesta «Agenda». Agendada por WhatsApp.');
  assert.strictEqual(waElegirSeguimiento(c, entrada({ buttonId: 'seg_p_99' })).modo, 'elegir');
  assert.strictEqual(waElegirSeguimiento(c, entrada({ buttonId: 'seg_c_4' })).modo, 'elegir');
});

test('revisión B-03: un botón viejo con una sola opción vigente pide confirmarla, no agenda otra', () => {
  const r = waElegirSeguimiento(ctx('cliente', [proyecto(7, 'Configurador')]), entrada({ buttonId: 'seg_c_99' }));
  assert.strictEqual(r.modo, 'elegir');
  assert.strictEqual(r.guardar, null);
  assert.strictEqual(r.mensaje.interactive.type, 'button');
  assert.strictEqual(r.mensaje.interactive.body.text, 'Hola Julio 👋 Esa opción ya no está disponible. ¿Agendamos el seguimiento de «Configurador»?');
  assert.deepStrictEqual(r.mensaje.interactive.action.buttons.map((x) => x.reply.id), ['seg_c_7']);
});

test('revisión B-03: un botón viejo sin opciones vigentes avisa y no abre el calendario', () => {
  const r = waElegirSeguimiento(ctx('cliente', []), entrada({ buttonId: 'seg_c_99' }));
  assert.strictEqual(r.modo, 'elegir');
  assert.strictEqual(r.guardar, null);
  assert.deepStrictEqual(r.mensaje, { messaging_product: 'whatsapp', recipient_type: 'individual', to: '56911112222', type: 'text',
    text: { body: 'Esa opción ya no está disponible para agendar. Si quieres, cuéntame sobre qué quieres conversar y lo coordinamos.' } });
});

test('revisión B-03: un botón viejo con varias opciones avisa y vuelve a preguntar', () => {
  const r = waElegirSeguimiento(ctx('prospecto', [propuesta(4, 'Sitio web'), propuesta(3, 'Agenda')]), entrada({ buttonId: 'seg_p_99' }));
  assert.strictEqual(r.modo, 'elegir');
  assert.strictEqual(r.mensaje.interactive.body.text, 'Hola Julio 👋 Esa opción ya no está disponible. ¿Sobre cuál propuesta quieres conversar?');
});

test('revisión B-06: un título con número no choca con el de otra opción', () => {
  const nombres = ['123456789012345678901', '123456789012345678902', '12345678901234567… 2'];
  for (const n of [3, 5]) {
    const ops = nombres.concat(['otra uno', 'otra dos']).slice(0, n).map((x, i) => propuesta(i + 1, x));
    const m = waElegirSeguimiento(ctx('prospecto', ops), entrada()).mensaje.interactive;
    const t = m.type === 'button' ? m.action.buttons.map((x) => x.reply.title) : m.action.sections[0].rows.map((x) => x.title);
    assert.strictEqual(new Set(t).size, t.length, `títulos repetidos: ${t.join(' | ')}`);
    assert.ok(t.every((x) => Array.from(x).length <= (m.type === 'button' ? 20 : 24)));
  }
});

test('revisión B-01: la forma que deja n8n cuando una consulta falla no inventa contexto ni ofrece diagnóstico', () => {
  const falla = { error: { message: 'timeout of 5000ms exceeded' } };
  assert.strictEqual(waLineaContexto(falla, {}), '');
  assert.match(waLineaContexto(falla, PROP), /^\[Contexto interno, no la repitas: este número tiene la propuesta/);
  assert.strictEqual(waLineaContexto({ situacion: 'no_disponible', opciones: [] }, {}), '');
  for (const c of [falla, { situacion: 'no_disponible', opciones: [], agenda: { nombre_completo: '', correo: '', empresa: '' } }]) {
    const r = waElegirSeguimiento(c, entrada());
    assert.strictEqual(r.modo, 'directo');
    assert.strictEqual(r.guardar.subject, 'Reunión de Seguimiento');
    assert.strictEqual(r.datos.appointmentType, 'Seguimiento');
  }
});

test('elegir: el proyecto del código del plan va directo sin preguntar', () => {
  const p = proyecto(5, 'Configurador', 'Ab3dE6gH9jK1');
  const r = waElegirSeguimiento(ctx('cliente', [p, propuesta(3, 'Agenda')], { elegida: p }), entrada());
  assert.strictEqual(r.modo, 'directo');
  assert.strictEqual(r.guardar.contrato_id, 5);
});

test('elegir: proyecto sin plan', () => {
  const r = waElegirSeguimiento(ctx('cliente', [proyecto(9, 'Soporte técnico')]), entrada());
  assert.strictEqual(r.guardar.notes, 'Seguimiento del proyecto «Soporte técnico». Agendada por WhatsApp.');
});

test('elegir: sin correo avisa en vez de agendar', () => {
  const sin = { tipo: 'proyecto', id: 5, nombre: 'A', plan_codigo: '', agenda: { nombre_completo: 'X', correo: '', empresa: '' } };
  const r = waElegirSeguimiento(ctx('cliente', [sin], { agenda: { nombre_completo: '', correo: '', empresa: '' } }), entrada());
  assert.strictEqual(r.modo, 'sin_correo');
  assert.strictEqual(r.guardar, null);
  assert.strictEqual(r.opcion.id, 5);
});

test('elegir: si la ruta falló, seguimiento general como hoy', () => {
  const r = waElegirSeguimiento({}, entrada());
  assert.strictEqual(r.modo, 'directo');
  assert.deepStrictEqual(r.guardar, { name: 'Julio WhatsApp', email: '', company: '', subject: 'Reunión de Seguimiento', notes: 'Agendada desde whatsapp', contrato_id: 0, propuesta_id: 0 });
  assert.strictEqual(r.datos.contactName, 'Julio WhatsApp');
  assert.strictEqual(r.datos.clientType, '');
  assert.strictEqual(waElegirSeguimiento(null, entrada()).modo, 'directo');
});

test('elegir: prospecto sin propuestas abiertas, seguimiento general con su agenda', () => {
  const r = waElegirSeguimiento(ctx('prospecto', []), entrada());
  assert.strictEqual(r.modo, 'directo');
  assert.strictEqual(r.guardar.email, 'julio@prueba.cl');
  assert.strictEqual(r.guardar.subject, 'Reunión de Seguimiento');
});
