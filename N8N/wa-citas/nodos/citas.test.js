// Pruebas de citas.js. Correr: node --test N8N/wa-citas/nodos/citas.test.js
const test = require('node:test');
const assert = require('node:assert');
const { waLeerBotonCita, waMensajeCitas, waConfirmarCita, waDecidirBoton } = require('./citas.js');

const TO = '56900001111';
const cita = (tipo, id, etiqueta) => ({ tipo, id, cuando: '2026-11-03 17:00', etiqueta, nombre: 'Ana Prueba', correo: 'ana@prueba.cl',
  empresa: 'Empresa Prueba', contrato_id: 0, propuesta_id: 0 });
const D7 = cita('diagnostico', 7, 'Diagnóstico gratis · mar 03-nov · 17:00');
const S9 = cita('seguimiento', 9, 'Seguimiento: Propuesta: Sitio web · mié 04-nov · 10:00');
const S10 = cita('seguimiento', 10, 'Seguimiento · jue 05-nov · 11:30');
const varias = (n) => Array.from({ length: n }, (_, i) => cita('diagnostico', 100 + i, `Diagnóstico gratis · lun ${String(9 + i).padStart(2, '0')}-nov · 10:00`));
const ok = (citas, situacion = 'prospecto') => ({ estado: 'ok', citas, situacion });
const ids = (m) => (m.interactive.action.buttons || []).map((b) => b.reply.id);
const titulos = (m) => (m.interactive.action.buttons || []).map((b) => b.reply.title);

test('0 citas, persona nueva: btn_agendar_demo y cita_tengo', () => {
  const r = waMensajeCitas(ok([], 'nuevo'), 'cancelar', TO);
  assert.strictEqual(r.mensaje.interactive.body.text, 'No encontramos citas agendadas con este número.');
  assert.deepStrictEqual(ids(r.mensaje), ['btn_agendar_demo', 'cita_tengo']);
  assert.strictEqual(r.avisarLuis, false);
  assert.strictEqual(r.mensaje.to, TO);
});

test('0 citas, prospecto: seg_general y cita_tengo', () => {
  assert.deepStrictEqual(ids(waMensajeCitas(ok([], 'prospecto'), 'reprogramar', TO).mensaje), ['seg_general', 'cita_tengo']);
  assert.deepStrictEqual(ids(waMensajeCitas(ok([], 'cliente'), 'cancelar', TO).mensaje), ['seg_general', 'cita_tengo']);
});

test('1 cita cancelar: texto exacto y botones cita_ok_c_d_7 / btn_mantener_cita', () => {
  const r = waMensajeCitas(ok([D7]), 'cancelar', TO);
  assert.strictEqual(r.mensaje.interactive.body.text, 'Tienes esta cita: Diagnóstico gratis · mar 03-nov · 17:00. ¿La cancelamos?');
  assert.deepStrictEqual(ids(r.mensaje), ['cita_ok_c_d_7', 'btn_mantener_cita']);
  assert.deepStrictEqual(titulos(r.mensaje), ['Sí, cancelar', 'No, mantenerla']);
  assert.strictEqual(r.cita.id, 7);
});

test('1 cita reprogramar: «¿La cambiamos de día?» y cita_ok_r_s_9', () => {
  const r = waConfirmarCita(S9, 'reprogramar', TO);
  assert.strictEqual(r.interactive.body.text, 'Tienes esta cita: Seguimiento: Propuesta: Sitio web · mié 04-nov · 10:00. ¿La cambiamos de día?');
  assert.deepStrictEqual(ids(r), ['cita_ok_r_s_9', 'btn_mantener_cita']);
  assert.deepStrictEqual(titulos(r), ['Sí, cambiar', 'No, mantenerla']);
});

test('3 citas → 3 botones con títulos ≤20 y distintos', () => {
  const r = waMensajeCitas(ok([D7, S9, S10]), 'cancelar', TO);
  assert.strictEqual(r.mensaje.interactive.type, 'button');
  assert.deepStrictEqual(ids(r.mensaje), ['cita_c_d_7', 'cita_c_s_9', 'cita_c_s_10']);
  const t = titulos(r.mensaje);
  assert.ok(t.every((x) => Array.from(x).length <= 20));
  assert.strictEqual(new Set(t).size, 3);
  assert.ok(r.mensaje.interactive.body.text.startsWith('Tienes estas citas'));
  assert.ok(r.mensaje.interactive.body.text.includes('Seguimiento: Propuesta: Sitio web · mié 04-nov · 10:00'));
  assert.ok(r.mensaje.interactive.body.text.endsWith('¿Cuál quieres cancelar?'));
});

test('2 citas reprogramar: pregunta de cambiar de día', () => {
  const r = waMensajeCitas(ok([D7, S9]), 'reprogramar', TO);
  assert.ok(r.mensaje.interactive.body.text.endsWith('¿Cuál quieres cambiar de día?'));
  assert.deepStrictEqual(ids(r.mensaje), ['cita_r_d_7', 'cita_r_s_9']);
});

test('5 citas → lista con títulos ≤24, descripciones ≤72', () => {
  const largas = varias(5).map((c, i) => ({ ...c, tipo: 'seguimiento', etiqueta: 'Seguimiento: Proyecto: ' + 'Muy largo '.repeat(10) + i + ' · lun 09-nov · 10:00' }));
  const r = waMensajeCitas(ok(largas), 'cancelar', TO);
  assert.strictEqual(r.mensaje.interactive.type, 'list');
  const filas = r.mensaje.interactive.action.sections[0].rows;
  assert.strictEqual(filas.length, 5);
  assert.ok(filas.every((f) => Array.from(f.title).length <= 24 && Array.from(f.description).length <= 72));
  assert.strictEqual(new Set(filas.map((f) => f.title)).size, 5);
  assert.deepStrictEqual(filas.map((f) => f.id), largas.map((c) => 'cita_c_s_' + c.id));
});

test('no_disponible → texto exacto y avisarLuis', () => {
  const r = waMensajeCitas({ estado: 'no_disponible', citas: [] }, 'cancelar', TO);
  assert.strictEqual(r.mensaje.type, 'text');
  assert.strictEqual(r.mensaje.text.body, 'En este momento no pude revisar tus citas. Ya le avisamos a Luis para que te escriba y lo coordinen.');
  assert.strictEqual(r.avisarLuis, true);
  assert.strictEqual(waMensajeCitas(undefined, 'cancelar', TO).avisarLuis, true);
});

test('waLeerBotonCita: cita_ok_r_d_12, cita_c_s_3, cita_tengo, basura → null', () => {
  assert.deepStrictEqual(waLeerBotonCita('cita_ok_r_d_12'), { confirmar: true, accion: 'reprogramar', tipo: 'diagnostico', id: 12 });
  assert.deepStrictEqual(waLeerBotonCita('cita_c_s_3'), { confirmar: false, accion: 'cancelar', tipo: 'seguimiento', id: 3 });
  assert.deepStrictEqual(waLeerBotonCita('cita_tengo'), { tengo: true });
  for (const x of ['cita_x_d_1', 'cita_c_d_', 'seg_p_4', '', null, 'cita_c_d_1 ']) assert.strictEqual(waLeerBotonCita(x), null);
});

test('decidir: elegir una cita vigente pide confirmación', () => {
  const r = waDecidirBoton('cita_c_s_9', ok([D7, S9]), TO);
  assert.strictEqual(r.paso, 'confirmar');
  assert.deepStrictEqual(ids(r.mensaje), ['cita_ok_c_s_9', 'btn_mantener_cita']);
  assert.strictEqual(r.cita.id, 9);
});

test('decidir: confirmar cancelar o reprogramar devuelve la cita y el paso', () => {
  assert.deepStrictEqual([waDecidirBoton('cita_ok_c_d_7', ok([D7]), TO).paso, waDecidirBoton('cita_ok_c_d_7', ok([D7]), TO).cita.id], ['cancelar', 7]);
  const r = waDecidirBoton('cita_ok_r_s_9', ok([S9]), TO);
  assert.strictEqual(r.paso, 'reprogramar');
  assert.strictEqual(r.mensaje, null);
  assert.strictEqual(r.cita.etiqueta, S9.etiqueta);
});

test('botón de cita que ya no está → mensaje de no disponible con las vigentes', () => {
  const r = waDecidirBoton('cita_ok_c_d_7', ok([S9, S10]), TO);
  assert.strictEqual(r.paso, 'no_disponible');
  assert.strictEqual(r.cita, null);
  assert.ok(r.mensaje.interactive.body.text.startsWith('Esa cita ya no está disponible. '));
  assert.deepStrictEqual(ids(r.mensaje), ['cita_c_s_9', 'cita_c_s_10']);
  const sola = waDecidirBoton('cita_c_d_7', ok([]), TO);
  assert.strictEqual(sola.mensaje.type, 'text');
  assert.ok(sola.mensaje.text.body.startsWith('Esa cita ya no está disponible.'));
});

test('decidir: tengo una cita avisa a Luis; consulta caída también', () => {
  const t = waDecidirBoton('cita_tengo', ok([]), TO);
  assert.strictEqual(t.paso, 'tengo');
  assert.strictEqual(t.avisarLuis, true);
  assert.strictEqual(t.mensaje.text.body, 'Le avisamos a Luis para que te escriba y lo revisen juntos.');
  const c = waDecidirBoton('cita_ok_c_d_7', { estado: 'no_disponible', citas: [] }, TO);
  assert.strictEqual(c.paso, 'no_disponible');
  assert.strictEqual(c.avisarLuis, true);
  assert.strictEqual(c.cita, null);
});

test('emojis en la etiqueta no rompen el tope', () => {
  const e = cita('seguimiento', 77, 'Seguimiento: Proyecto: 🎉🎉🎉 Fiesta 😀😀😀😀 · mar 03-nov · 17:00');
  const r = waMensajeCitas(ok([e, D7, S9, S10]), 'cancelar', TO);
  const filas = r.mensaje.interactive.action.sections[0].rows;
  assert.ok(filas.every((f) => Array.from(f.title).length <= 24 && Array.from(f.description).length <= 72));
});
