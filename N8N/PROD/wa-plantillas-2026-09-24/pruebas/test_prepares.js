// Prueba local de los nodos "Prepare" nuevos (8 AM, 8 PM y Followup) antes de subirlos a n8n.
const fs = require('fs');
const path = require('path');
const assert = require('assert');

// variables de cada plantilla creada en Meta (plantillas_2.py)
const VARS = { recordatorio_seguimiento_hoy: 5, recordatorio_seguimiento_manana: 5, aviso_demo: 4,
  aviso_reunion_prospecto: 6, aviso_reunion_seguimiento: 6 };

function correr(archivo, json) {
  const code = fs.readFileSync(path.join(__dirname, '..', 'codigo-nodos', archivo), 'utf8');
  return new Function('$input', code)({ first: () => ({ json }), all: () => [{ json }] }).json;
}

function revisar(out, plantilla, idsEsperados) {
  const b = JSON.parse(out.whatsappBody);
  assert.strictEqual(b.type, 'template');
  assert.strictEqual(b.template.name, plantilla);
  assert.strictEqual(b.template.language.code, 'es');
  assert.ok(!b.to.startsWith('+'));
  const body = b.template.components.find(c => c.type === 'body');
  assert.strictEqual(body.parameters.length, VARS[plantilla], `${plantilla}: parametros = variables`);
  for (const p of body.parameters) {
    assert.ok(p.text && p.text.trim() === p.text && !/[\n\t]/.test(p.text) && !/ {4,}/.test(p.text), `parametro invalido: ${JSON.stringify(p.text)}`);
  }
  const btns = b.template.components.filter(c => c.type === 'button');
  assert.deepStrictEqual(btns.map(x => x.index), ['0', '1', '2']);
  assert.deepStrictEqual(btns.map(x => x.parameters[0].payload), idsEsperados);
  return body.parameters.map(p => p.text);
}

const reunion = { id: 31, name: 'María Pérez', company_name: 'Empresa Demo', phone: '+56911112222',
  meeting_subject: 'Implementación de chatbot', scheduled_date: '25-09-2026', scheduled_time: '15:00',
  meet_link: 'https://meet.google.com/abc-defg-hij' };
const fu = id => [`btn_followup_confirm_${id}`, `btn_followup_reschedule_${id}`, `btn_followup_cancel_${id}`];
let n = 0;

for (const [archivo, plantilla] of [['prepare_8am.js', 'recordatorio_seguimiento_hoy'], ['prepare_8pm.js', 'recordatorio_seguimiento_manana']]) {
  const out = correr(archivo, reunion);
  const p = revisar(out, plantilla, fu(31));
  assert.strictEqual(p[0], 'María Pérez de Empresa Demo');
  assert.strictEqual(out.meeting_id, 31); // lo lee "Mark as Sent"
  console.log(`OK ${plantilla}: ${JSON.stringify(p)}`); n++;
  const sinDatos = correr(archivo, { id: 32, name: '', company_name: '', phone: '56933334444', meeting_subject: '',
    scheduled_date: '25-09-2026', scheduled_time: '10:00', meet_link: '' });
  const q = revisar(sinDatos, plantilla, fu(32));
  assert.deepStrictEqual([q[0], q[1], q[4]], ['Cliente', 'Reunión de Seguimiento', 'Te llegará por correo']);
  console.log(`OK ${plantilla} sin nombre/empresa/asunto/enlace: ${JSON.stringify(q)}`); n++;
}

const base = { meeting_id: 77, client_name: 'Juan Soto', company_name: '', phone: '+56 9 5555 6666',
  formatted_date: 'jueves 25 de septiembre', formatted_time: '10:00', meeting_subject: 'Revisión de propuesta' };

let o = correr('prepare_followup.js', { body: { ...base, type: 'demo' } });
assert.deepStrictEqual(revisar(o, 'aviso_demo', ['btn_reminder_confirm_77', 'btn_reprogramar_lead_77', 'btn_cancelar_lead_77']),
  ['agendada', 'Juan Soto', 'jueves 25 de septiembre', '10:00']);
assert.strictEqual(o.meetingType, 'demo'); assert.strictEqual(o.meeting_id, 77); assert.strictEqual(o.phone, '56955556666');
console.log('OK demo nueva: botones a las acciones de lead que el bot ya tiene'); n++;

o = correr('prepare_followup.js', { body: { ...base, type: 'demo', context: 'reschedule' } });
assert.strictEqual(revisar(o, 'aviso_demo', ['btn_reminder_confirm_77', 'btn_reprogramar_lead_77', 'btn_cancelar_lead_77'])[0], 'reprogramada');
console.log('OK demo reprogramada'); n++;

o = correr('prepare_followup.js', { body: { ...base, type: 'seguimiento_prospecto', prospect_timeline_url: 'https://automatizatech.cl/s/abc' } });
assert.deepStrictEqual(revisar(o, 'aviso_reunion_prospecto', fu(77)),
  ['agendada', 'Juan Soto', 'Revisión de propuesta', 'jueves 25 de septiembre', '10:00', 'https://automatizatech.cl/s/abc']);
console.log('OK prospecto: botones btn_followup_* (antes btn_prospect_*, sin ruta)'); n++;

o = correr('prepare_followup.js', { body: { ...base, type: 'seguimiento_prospecto', context: 'reschedule' } });
const pp = revisar(o, 'aviso_reunion_prospecto', fu(77));
assert.strictEqual(pp[0], 'reagendada'); assert.strictEqual(pp[5], 'te lo enviaremos por correo');
console.log('OK prospecto reagendado y sin enlace de seguimiento'); n++;

o = correr('prepare_followup.js', { body: { ...base, company_name: 'Tienda X', ficha_url: 'https://automatizatech.cl/ficha/9' } });
const ps = revisar(o, 'aviso_reunion_seguimiento', fu(77));
assert.strictEqual(ps[1], 'Juan Soto de Tienda X'); assert.strictEqual(ps[5], 'https://automatizatech.cl/ficha/9');
assert.strictEqual(o.meetingType, 'seguimiento');
console.log('OK seguimiento de cliente (tipo por defecto) con portal'); n++;

o = correr('prepare_followup.js', { body: { ...base, phone: '123' } });
assert.strictEqual(o.error, true); assert.ok(o.errorMessage.includes('Datos incompletos'));
console.log('OK validacion: telefono invalido sigue yendo a "Respond Validation Error"'); n++;

console.log(`\n${n} pruebas pasaron`);
