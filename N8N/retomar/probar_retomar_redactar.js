#!/usr/bin/env node
// Prueba de los nodos Code de «Retomar · Redactar» (retomar-redactar.json) y del nodo «Preparar WhatsApp» de
// «Retomar · Reloj y WhatsApp» (retomar-reloj.json). Node plano: sin red, sin credenciales, sin n8n.
//
//   node N8N/retomar/probar_retomar_redactar.js [archivo-de-salida.json]
//
// Extrae el JavaScript de cada nodo Code del export, lo ejecuta con $input, $ y $execution simulados sobre datos ficticios
// y termina con código distinto de cero si algo falla. Con un argumento, deja ahí el callback de la respuesta válida
// (sirve para pasarlo por el validador de WordPress en el sitio local). Regenerar antes el export con
// `python -B N8N/retomar/build_retomar_redactar.py` si se cambió el generador.
'use strict';
const fs = require('fs');
const path = require('path');

const wf = JSON.parse(fs.readFileSync(path.join(__dirname, 'retomar-redactar.json'), 'utf8'));
const nodo = Object.fromEntries(wf.nodes.map((n) => [n.name, n]));

function correr(nombre, entrada, previos) {
  const codigo = nodo[nombre].parameters.jsCode;
  const $input = { first: () => ({ json: entrada }), all: () => [{ json: entrada }] };
  const $ = (n) => {
    if (!(n in previos)) throw new Error('el nodo ' + n + ' no tiene datos en la prueba');
    return { first: () => ({ json: previos[n] }) };
  };
  return new Function('$input', '$', '$execution', codigo)($input, $, { id: '9001' })[0].json;
}

let fallas = 0;
function ok(condicion, mensaje) {
  console.log((condicion ? 'ok   ' : 'FALLA ') + mensaje);
  if (!condicion) fallas++;
}

const GEN = 'aaaaaaaaaaaaaaaa';
const CIERRE = 'no signs, no labels, no text, no lettering, no logos, no watermarks';
const hook = { body: { id: 41, generacion: GEN } };
const ctx = {
  statusCode: 200,
  body: {
    id: 41, generacion: GEN, nombre: 'Persona Ejemplo', negocio: 'Negocio Demostracion', demo_urls: ['https://example.com/demo'],
    notas: 'Quiere partir con algo chico', pedido: '', textos_actuales: { quedamos: '', proponemos: '', partir: '', recomendacion: '' },
    propuesta: { status: 'sent', flujo: 'v3', transcripcion: 'x'.repeat(13000), resumen: 'Resumen' },
    historial: Array.from({ length: 14 }, (_, i) => 'Evento ' + i),
    reglas: { max_caracteres: 600, max_lineas: 6, max_recomendacion: 300, sin_montos: true },
    slides_foto: ['portada', 'cierre', 'demo'],
  },
};
const textos = {
  quedamos: 'Conversamos sobre tu negocio.', proponemos: 'Te proponemos partir por lo esencial.',
  partir: 'Podemos empezar por etapas.', recomendacion: 'Partir por la primera etapa.',
};
const PORTADA_OK = 'Close-up of baker hands shaping dough on a wooden table, warm morning light, blurred background';
const nodos = (contenido, c = ctx) => ({
  Webhook: hook,
  'Leer contexto': c,
  Redactar: contenido === undefined ? { error: { message: 'sin respuesta' } } : { message: { role: 'assistant', content: contenido } },
});
const leer = (contenido, c = ctx) => correr('Leer respuesta', {}, nodos(contenido, c));
const respuesta = (briefs, t = textos) => JSON.stringify({ textos: t, image_briefs: briefs });

// 1) Preparar pedido
const pedido = JSON.parse(correr('Preparar pedido', ctx, {}).mensaje);
ok(pedido.tiene_demo === true && !('demo_urls' in pedido) && !('id' in pedido) && !('generacion' in pedido), 'pedido: sin id, generación ni direcciones de demo; tiene_demo=true');
ok(pedido.propuesta.transcripcion.length === 12000 && pedido.historial.length === 10, 'pedido: recorta la transcripción a 12000 y el historial a 10');
ok(pedido.slides_foto.join() === 'portada,cierre,demo', 'pedido: conserva las láminas con foto');
ok(JSON.parse(correr('Preparar pedido', { body: { id: 1 } }, {}).mensaje).slides_foto.join() === 'portada,cierre', 'pedido: contexto vacío no lanza y pide portada y cierre');
// Las notas crecen por el final (cada «Pedir cambios» se agrega abajo): se conservan las ÚLTIMAS 2000, no las primeras.
const notasLargas = JSON.parse(JSON.stringify(ctx));
notasLargas.body.notas = 'viejo '.repeat(500) + 'Cambio pedido hoy: más corto';
const conNotas = JSON.parse(correr('Preparar pedido', notasLargas, {}).mensaje);
ok(conNotas.notas === notasLargas.body.notas.slice(-2000) && conNotas.notas.endsWith('Cambio pedido hoy: más corto'), 'pedido: de las notas viajan las últimas 2000 (lo más reciente)');
ok(pedido.notas === ctx.body.notas, 'pedido: unas notas cortas viajan enteras');
const prompt = nodo['Redactar'].parameters.prompt.messages[0].content;
ok(['«lucas»', '«mil»', '«millones»', '«USD500»'].every((p) => prompt.includes(p)), 'el prompt prohíbe también las cifras con lucas, mil o millones y las siglas pegadas a la cifra (lo mismo que rechaza WordPress)');

// 2) Leer respuesta: respuesta válida
const valida = leer(respuesta([
  { slide: 'portada', prompt: PORTADA_OK + ', no text' },
  { slide: 'cierre', prompt: 'Smiling shop owner handing a wrapped package to a customer, warm light' },
  { slide: 'demo', prompt: 'Cafe staff serving a customer calmly, shallow depth of field' },
]));
ok(valida.id === 41 && valida.callback.ok === true && valida.callback.generacion === GEN, 'respuesta válida: ok:true con el id y la generación del webhook');
ok(valida.callback.image_briefs.map((b) => b.slide).join() === 'portada,cierre,demo', 'respuesta válida: tres encargos con las láminas de Retomar');
ok(valida.callback.image_briefs.every((b) => b.prompt.endsWith(CIERRE + ', no subtitles, no captions')), 'respuesta válida: todos los encargos terminan con las prohibiciones');
ok(valida.callback.nota === '' && valida.callback.textos.recomendacion === textos.recomendacion, 'respuesta válida: sin nota y con los cuatro textos');
if (process.argv[2]) fs.writeFileSync(process.argv[2], JSON.stringify({ buenos: valida.callback }));

// 3) Portada: las reglas de fondo de la lámina «cover» aplican a «portada» y el nombre de la lámina no cambia
const resultadosPortada = [valida];
for (const [caso, prompt] of [
  ['estadio', 'Football stadium facade with cheering fans holding flags, golden hour'],
  ['fachada de local', 'Storefront of a small bakery with customers lining up, warm light'],
  ['calle', 'Busy street with pedestrians and a market stall'],
]) {
  const r = leer(respuesta([{ slide: 'portada', prompt }]));
  resultadosPortada.push(r);
  const b = r.callback.image_briefs[0];
  ok(r.callback.ok === true && b.slide === 'portada' && !/stadium|facade|storefront|street/i.test(b.prompt) && /neutra/.test(r.callback.nota),
    'portada con ' + caso + ': se reemplaza por una escena neutra, sigue llamándose portada y la nota lo avisa');
}
const sinPrimerPlano = leer(respuesta([{ slide: 'portada', prompt: 'Two bakers kneading dough together in a bright workshop' }])).callback.image_briefs[0];
ok(sinPrimerPlano.slide === 'portada' && /close-up/i.test(sinPrimerPlano.prompt) && /blurred/i.test(sinPrimerPlano.prompt), 'portada sin primer plano: se le agrega primer plano con fondo desenfocado');
const conPrimerPlano = leer(respuesta([{ slide: 'portada', prompt: PORTADA_OK }])).callback.image_briefs[0];
ok(!/close-up, shallow depth of field, background completely blurred/.test(conPrimerPlano.prompt), 'portada que ya es primer plano: no se le repite el agregado');
ok(resultadosPortada.every((r) => r.callback.image_briefs.every((b) => ['portada', 'cierre', 'demo'].includes(b.slide))), 'ningún callback deja «cover» como nombre de lámina');
// Lo que pide texto o etiquetas en cualquier lámina se reemplaza
const cartel = leer(respuesta([{ slide: 'cierre', prompt: 'A woman holding a menu with a sign saying OPEN' }]));
ok(!/sign saying/.test(JSON.stringify(cartel.callback.image_briefs)) && /neutra/.test(cartel.callback.nota), 'cierre con letrero: se reemplaza y la nota lo avisa');

// 4) Láminas: solo las permitidas, una por lámina
const ajenas = leer(respuesta([
  { slide: 'portada', prompt: PORTADA_OK }, { slide: 'portada', prompt: 'repetida' }, { slide: 'otra', prompt: 'ajena' }, { slide: 'cierre', prompt: '   ' },
]));
ok(ajenas.callback.image_briefs.map((b) => b.slide).join() === 'portada', 'encargos repetidos, ajenos o vacíos se descartan');
const sinDemo = JSON.parse(JSON.stringify(ctx));
sinDemo.body.slides_foto = ['portada', 'cierre'];
ok(leer(respuesta([{ slide: 'demo', prompt: 'Cafe staff serving a customer calmly' }]), sinDemo).callback.image_briefs.length === 0, 'sin lámina demo permitida, su encargo se descarta');

// 5) Formas de la respuesta de la IA
const base = respuesta([{ slide: 'portada', prompt: PORTADA_OK }]);
ok(leer('```json\n' + base + '\n```').callback.ok === true, 'acepta un bloque de código json');
ok(leer(base + '}').callback.ok === true, 'tolera una llave de más al final');
const sinReco = leer(JSON.stringify({ textos: { quedamos: 'a', proponemos: 'b', partir: 'c' } })).callback;
ok(sinReco.ok === true && sinReco.textos.recomendacion === '' && sinReco.image_briefs.length === 0, 'recomendacion ausente queda vacía y sin encargos la lista va vacía');

// 6) Respuestas que no sirven: ok:false con nota, nunca una excepción
for (const [caso, contenido] of [
  ['sin respuesta de la IA', undefined],
  ['texto que no es JSON', 'Hola, aquí tienes los textos'],
  ['sin textos', '{"image_briefs":[]}'],
  ['falta partir', '{"textos":{"quedamos":"a","proponemos":"b"}}'],
  ['partir vacío', '{"textos":{"quedamos":"a","proponemos":"b","partir":"  "}}'],
  ['arreglo', '[]'],
]) {
  const r = leer(contenido);
  ok(r.id === 41 && r.callback.ok === false && r.callback.generacion === GEN && /ejecución 9001/.test(r.callback.nota) && !('textos' in r.callback), 'falla (' + caso + '): ok:false con nota y número de ejecución');
}

// 7) Falla de contexto: la nota no copia mensajes de red
for (const lectura of [{ statusCode: 404, body: { code: 'retomar_no_existe' } }, { error: { message: 'connect ECONNREFUSED https://servidor.interno.example' } }]) {
  const r = correr('Falla de contexto', {}, { Webhook: hook, 'Leer contexto': lectura });
  ok(r.id === 41 && r.callback.ok === false && r.callback.generacion === GEN && !/ECONN|servidor/.test(r.callback.nota), 'falla de contexto: ' + r.callback.nota);
}

// 8) Condiciones de los IF y URLs
const condicion = Object.fromEntries(wf.nodes.filter((n) => n.type.endsWith('.if'))
  .map((n) => [n.name, n.parameters.conditions.conditions[0].leftValue.replace(/^=\{\{|\}\}$/g, '')]));
const evaluar = (nombre, json) => new Function('$json', '$', 'return ' + condicion[nombre])(json, () => ({ first: () => ({ json: hook }) }));
ok(evaluar('¿Contexto leído?', ctx) === true && evaluar('¿Contexto leído?', { statusCode: 404, body: { code: 'x' } }) === false && evaluar('¿Contexto leído?', { error: {} }) === false, 'IF contexto leído: 200 con id sí; 404 y error de red no');
ok(evaluar('¿Es el pedido vigente?', ctx) === true && evaluar('¿Es el pedido vigente?', { body: { generacion: 'bbbbbbbbbbbbbbbb' } }) === false, 'IF pedido vigente: misma generación sí; otra no');
ok(nodo['Leer contexto'].parameters.url === '=https://automatizatech.cl/?rest_route=/automatiza-tech/v1/retomar/{{ parseInt($json.body.id, 10) }}/contexto', 'URL de contexto');
ok(nodo['Guardar redacción'].parameters.url === '=https://automatizatech.cl/?rest_route=/automatiza-tech/v1/retomar/{{ $json.id }}/redaccion', 'URL de guardar redacción');
ok(wf.active === false, 'el export sigue inactivo');

// 9) «Retomar · Reloj y WhatsApp» (retomar-reloj.json): el botón «Ver y agendar» de la plantilla abre la página del
// prospecto con la presentación (t=<token>), no el formulario de agenda; el de la baja, la baja.
const reloj = JSON.parse(fs.readFileSync(path.join(__dirname, 'retomar-reloj.json'), 'utf8'));
const preparar = reloj.nodes.find((n) => n.name === 'Preparar WhatsApp').parameters.jsCode;
const pedidoWa = { id: 7, n: 2, telefono: '56900000000', nombre: 'Persona Ejemplo', negocio: 'Negocio Demostracion', token: 'a1b2c3' };
const salidaWa = new Function('$input', preparar)({ all: () => [{ json: { whatsapp: [pedidoWa] } }] });
const botones = salidaWa[0].json.payload.template.components.filter((c) => c.type === 'button');
ok(botones.length === 2 && botones[0].parameters[0].text === 't=a1b2c3' && botones[1].parameters[0].text === 't=a1b2c3&baja=1', 'reloj: «Ver y agendar» lleva t=<token> (la página con la presentación) y «No me interesa» t=<token>&baja=1');
ok(reloj.active === false && reloj.nodes.find((n) => n.name === 'Reloj').parameters.rule.interval[0].expression === '0 5 * * * *', 'reloj: el export sigue inactivo y corre al minuto 5 de cada hora');

console.log(fallas ? '\n' + fallas + ' FALLAS' : '\nTODO OK');
process.exit(fallas ? 1 : 0);
