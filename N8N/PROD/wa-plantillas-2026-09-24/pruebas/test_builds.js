// Prueba local de los nodos "Build WhatsApp Body" nuevos antes de subirlos a n8n.
// Simula $input.all() y verifica el cuerpo que se enviaria a Meta.
const fs = require('fs');
const path = require('path');
const assert = require('assert');

const VARS = { recordatorio_cita_72h: 3, recordatorio_cita_24h: 4, recordatorio_cita_1h: 5 };

function correr(archivo, leads) {
  const code = fs.readFileSync(path.join(__dirname, '..', 'codigo-nodos', archivo), 'utf8');
  const fn = new Function('$input', code);
  const out = fn({ all: () => leads.map(json => ({ json })) });
  return out.map(o => ({ data: o.json, body: JSON.parse(o.json.whatsappBody) }));
}

function revisar(nombre, r, lead) {
  const b = r.body;
  assert.strictEqual(b.type, 'template', `${nombre}: debe ser plantilla`);
  assert.strictEqual(b.template.name, nombre);
  assert.strictEqual(b.template.language.code, 'es');
  assert.ok(!b.to.startsWith('+'), 'telefono sin +');
  const body = b.template.components.find(c => c.type === 'body');
  assert.strictEqual(body.parameters.length, VARS[nombre], `${nombre}: parametros = variables de la plantilla`);
  for (const p of body.parameters) {
    assert.strictEqual(p.type, 'text');
    assert.ok(p.text && p.text.trim() === p.text && !/[\n\t]/.test(p.text) && !/ {4,}/.test(p.text),
      `${nombre}: parametro invalido para Meta: ${JSON.stringify(p.text)}`);
  }
  const btns = b.template.components.filter(c => c.type === 'button');
  assert.deepStrictEqual(btns.map(x => x.index), ['0', '1']);
  assert.strictEqual(btns[0].parameters[0].payload, `btn_reminder_confirm_${lead.id}`);
  assert.strictEqual(btns[1].parameters[0].payload, `btn_reminder_no_${lead.id}`);
  return body.parameters.map(p => p.text);
}

// fecha/hora de una cita a N minutos de ahora, en hora de Chile, con el formato que entrega WordPress
function citaEn(min) {
  const t = new Date(Date.now() + min * 60000);
  const f = new Intl.DateTimeFormat('en-GB', { timeZone: 'America/Santiago', year: 'numeric', month: '2-digit',
    day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(t);
  const g = k => f.find(x => x.type === k).value;
  return { scheduled_date: `${g('day')}-${g('month')}-${g('year')}`, scheduled_time: `${g('hour')}:${g('minute')}` };
}

const base = { id: 99, name: 'María Pérez', phone: '+56911112222', meet_link: 'https://meet.google.com/abc-defg-hij' };
let ok = 0;

// 72h y 24h
for (const [archivo, nombre] of [['build_72h.js', 'recordatorio_cita_72h'], ['build_24h.js', 'recordatorio_cita_24h']]) {
  const lead = { ...base, scheduled_date: '28-09-2026', scheduled_time: '15:00' };
  const p = revisar(nombre, correr(archivo, [lead])[0], lead);
  console.log(`OK ${nombre}: ${JSON.stringify(p)}`); ok++;
}

// valores feos que Meta rechazaria: sin nombre, sin enlace, nombre con salto de linea
const feo = { id: 7, name: '  Juan\n Soto  ', phone: '56933334444', meet_link: '', scheduled_date: '28-09-2026', scheduled_time: '15:00' };
const p24 = revisar('recordatorio_cita_24h', correr('build_24h.js', [feo])[0], feo);
assert.strictEqual(p24[0], 'Juan Soto');
assert.strictEqual(p24[3], 'Te llegará por correo');
console.log(`OK 24h con datos feos: ${JSON.stringify(p24)}`); ok++;
const sinNombre = { ...feo, name: '' };
assert.strictEqual(revisar('recordatorio_cita_72h', correr('build_72h.js', [sinNombre])[0], sinNombre)[0], 'cliente');
console.log('OK 72h sin nombre -> "cliente"'); ok++;

// 1h a distintas distancias de la cita
const esperado = [[45, /^4[4-5] minutos$/], [60, /^1 hora$/], [80, /^1 hora y media$/], [100, /^1 hora y 3[89] minutos$/], [118, /^casi 2 horas$/], [10, /^menos de 30 minutos$/], [-5, /^pocos minutos$/]];
for (const [min, re] of esperado) {
  const lead = { ...base, ...citaEn(min) };
  const r = correr('build_1h.js', [lead])[0];
  const p = revisar('recordatorio_cita_1h', r, lead);
  assert.ok(re.test(p[0]), `1h a ${min} min: encabezado "${p[0]}" no calza con ${re}`);
  console.log(`OK 1h a ${String(min).padStart(3)} min: "¡Tu cita es en ${p[0]}!"`); ok++;
}

// varios leads en la misma corrida
const dos = correr('build_24h.js', [{ ...base, id: 1, scheduled_date: '28-09-2026', scheduled_time: '10:00' }, { ...base, id: 2, scheduled_date: '28-09-2026', scheduled_time: '11:00' }]);
assert.strictEqual(dos.length, 2);
assert.strictEqual(dos[1].body.template.components[1].parameters[0].payload, 'btn_reminder_confirm_2');
console.log('OK dos leads en la misma corrida'); ok++;

console.log(`\n${ok} pruebas pasaron`);
