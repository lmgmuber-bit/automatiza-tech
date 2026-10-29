// Prueba del nodo «Validar cita» del flujo de herramientas, tal como lo genera build_agente_instagram.py
// (con la validación común del Cerebro AT). Sin red ni n8n. Correr: node N8N/instagram/validar-cita.test.js
const fs = require('fs');
const path = require('path');

const wf = JSON.parse(fs.readFileSync(path.join(__dirname, 'agente-tech-instagram-herramientas.json'), 'utf8'));
const codigo = wf.nodes.find(n => n.name === 'Validar cita').parameters.jsCode;

// Lo mínimo de Luxon que usa el nodo: fechas como milisegundos.
const DateTime = {
  fromISO: s => { const d = new Date(s + ':00-03:00'); return { isValid: !isNaN(d), valueOf: () => d.getTime() }; },
  now: () => ({ setZone: () => ({ plus: ({ hours }) => ({ valueOf: () => Date.now() + hours * 3600e3 }) }) }),
};
const correr = entrada => new Function('$', 'DateTime', codigo)(() => ({ first: () => ({ json: entrada }) }), DateTime)[0].json;
const base = { nombre: 'ana rojas', correo: 'Ana.Rojas@Ejemplo.cl', telefono: '9 1234 5678', fecha: '2099-12-01',
  hora: '10:00', igsid: '123', prueba: 'si' };

let fallas = 0;
const ok = (c, m, d) => { console.log((c ? 'ok    ' : 'FALLA ') + m + (c ? '' : '  ' + JSON.stringify(d))); if (!c) fallas++; };

let r = correr(base);
ok(r.ok === true && r.name === 'Ana Rojas' && r.email === 'ana.rojas@ejemplo.cl' && r.phone === '+56912345678',
  'datos buenos: nombre con mayúsculas, correo en minúscula y celular chileno con +56', r);
ok(r.session_id === 'ig:123' && r.appointmentType === 'Demo' && r.prueba === true, 'sesión ig:, tipo Demo y modo prueba', r);
r = correr({ ...base, nombre: 'Ana' });
ok(!r.ok && /apellido/.test(r.result), 'sin apellido: lo pide', r);
r = correr({ ...base, nombre: 'Hola Ana' });
ok(!r.ok && /no parece un nombre/.test(r.result), 'un saludo no es un nombre', r);
r = correr({ ...base, correo: 'ana@gmail.con' });
ok(!r.ok && r.result.includes('ana@gmail.com'), 'gmail.con: sugiere gmail.com', r);
r = correr({ ...base, correo: 'ana@' });
ok(!r.ok && /correo no es válido/.test(r.result), 'correo inválido: lo vuelve a pedir', r);
r = correr({ ...base, telefono: '' });
ok(!r.ok && r.result.includes('+56 9 1234 5678'), 'sin WhatsApp: lo pide con el ejemplo', r);
r = correr({ ...base, telefono: '+56 2 2345 6789' });
ok(!r.ok && /\+56 9 y ocho dígitos/.test(r.result), 'número de Chile que no es celular: lo rechaza', r);
r = correr({ ...base, telefono: '+58 414 123 4567' });
ok(r.ok && r.phone === '+584141234567', 'celular de otro país con código: sirve', r);
r = correr({ ...base, hora: '10:30' });
ok(!r.ok && /hora en punto/.test(r.result), 'hora que no es en punto: se rechaza', r);
r = correr({ ...base, fecha: '2020-01-01' });
ok(!r.ok && /ya pasó/.test(r.result), 'fecha pasada: se rechaza', r);
for (const x of [correr({ ...base, nombre: 'Ana' })]) ok(/No agendes/.test(x.result), 'cada rechazo le dice al agente que no agende', x);
r = correr({ ...base, nombre: 'Ana', correo: 'ana@gmail.con', telefono: '' });
ok(!r.ok && /apellido/.test(r.result) && r.result.includes('ana@gmail.com') && r.result.includes('+56 9 1234 5678'),
  'varios datos malos: los pide todos en un solo mensaje', r);

r = correr({ ...base, telefono: '4141234567' });
ok(!r.ok && r.result.includes('+56 9 1234 5678'), 'número sin + que no es de Chile: pide el código de país, no lo adivina', r);

// «Revisar hora»: antes de agendar, la herramienta revisa ella misma la agenda real del día (check-availability),
// para no depender de que el modelo consulte y no hacer confirmar dos veces (28-oct).
ok(!wf.nodes.some(n => n.name === 'Revisar consulta'), 'ya no está la guarda de Redis que obligaba a confirmar dos veces');
const revisarHora = (wf.nodes.find(n => n.name === 'Revisar hora') || { parameters: { jsCode: 'throw new Error("falta Revisar hora")' } }).parameters.jsCode;
const cita = correr(base);
const revisar = disp => new Function('$', '$json', revisarHora)(() => ({ first: () => ({ json: cita }) }), disp)[0].json;
const dia = { isFullDay: false, busySlots: ['09:00'], workingHours: { start: '09:00', end: '18:00' } };
const intentar = (m, f) => { try { f(); } catch (e) { ok(false, m, e.message); } };
intentar('hora libre', () => { r = revisar(dia);
  ok(r.ok === true && r.date === '2099-12-01' && r.time === '10:00', 'hora libre: sigue a agendar en el mismo turno', r); });
intentar('hora tomada', () => { r = revisar({ ...dia, busySlots: ['10:00'] });
  ok(r.ok === false && /ya está tomada/.test(r.result) && r.result.includes('11:00') && !r.result.includes(' 10:00,'), 'hora tomada: no agenda y ofrece las libres', r); });
intentar('fuera de horario', () => { r = revisar({ ...dia, workingHours: { start: '15:00', end: '17:00' } });
  ok(r.ok === false && /fuera del horario/.test(r.result) && r.result.includes('15:00, 16:00'), 'fuera de horario: no agenda', r); });
intentar('feriado', () => { r = revisar({ isFullDay: true, reason: 'Holiday' });
  ok(r.ok === false && /feriado/.test(r.result) && /ver_disponibilidad/.test(r.result), 'feriado: no agenda y pide otro día', r); });
intentar('día sin atención', () => { r = revisar({ isFullDay: true, reason: 'Day disabled' });
  ok(r.ok === false && /no atendemos/.test(r.result), 'día sin atención: no agenda', r); });
intentar('vacía', () => ok(revisar({}).ok === false, 'respuesta vacía de la agenda: no agenda'));

// «Preparar entrada» del flujo principal: si el tipo de cambio no llegó (timeout de WordPress, 29-oct), nunca «$0 CLP».
const principal = JSON.parse(fs.readFileSync(path.join(__dirname, 'agente-tech-instagram.json'), 'utf8'));
const entradaJs = principal.nodes.find(n => n.name === 'Preparar entrada').parameters.jsCode;
const tipoCambio = principal.nodes.find(n => n.name === 'Tipo de cambio');
const Lux = { now: () => { const d = { setZone: () => d, setLocale: () => d, plus: () => d, toFormat: () => 'hoy', toISODate: () => '2099-12-01' }; return d; } };
const nodos = { Validar: { igsid: '1', prueba: true, contexto: null }, 'Unir textos': { texto: '¿cuánto cuesta?' } };
const prep = tc => new Function('$', '$json', 'DateTime', entradaJs)(n => ({ first: () => ({ json: nodos[n] }) }), tc, Lux)[0].json;
r = prep({ rate: 981.08 });
ok(r.rate === 981.08 && !/tipo de cambio/.test(r.contexto_txt), 'tipo de cambio presente: se usa tal cual', r);
for (const tc of [{ error: { message: 'timeout of 10000ms exceeded' } }, { rate: 0 }, {}]) {
  r = prep(tc);
  ok(r.rate === null && /solo en USD/.test(r.contexto_txt), 'sin tipo de cambio: precios solo en USD, nunca $0 CLP (' + JSON.stringify(tc).slice(0, 30) + ')', r);
}
ok(tipoCambio.retryOnFail === true && tipoCambio.maxTries >= 3 && tipoCambio.parameters.options.timeout >= 20000,
  'Tipo de cambio: reintenta y espera 20 s', { retryOnFail: tipoCambio.retryOnFail, maxTries: tipoCambio.maxTries, options: tipoCambio.parameters.options });

console.log(fallas ? `\n${fallas} FALLAS` : '\nTODO OK');
process.exit(fallas ? 1 : 0);
