const test = require('node:test');
const assert = require('node:assert/strict');
const tp = require('../src/template-plan');
const { planCorto, planLargo } = require('./fixtures/plan-ejemplo');

function contar(texto, aguja) {
  return texto.split(aguja).length - 1;
}

// --- Fechas y ayudas ---------------------------------------------------------

test('lunesDe devuelve el lunes de la semana, también en domingo y al cambiar de año', () => {
  assert.equal(tp.lunesDe('2026-10-05'), '2026-10-05');
  assert.equal(tp.lunesDe('2026-10-07'), '2026-10-05');
  assert.equal(tp.lunesDe('2026-10-11'), '2026-10-05');
  assert.equal(tp.lunesDe('2027-01-01'), '2026-12-28');
  assert.equal(tp.lunesDe('2026-02-30'), '');
  assert.equal(tp.lunesDe(''), '');
});

test('semanaIndice cuenta semanas de lunes a domingo desde el lunes de inicio', () => {
  assert.equal(tp.semanaIndice('2026-10-05', '2026-10-05'), 0);
  assert.equal(tp.semanaIndice('2026-10-11', '2026-10-05'), 0);
  assert.equal(tp.semanaIndice('2026-10-12', '2026-10-05'), 1);
  assert.equal(tp.semanaIndice('2027-01-08', '2026-10-05'), 13);
  assert.ok(Number.isNaN(tp.semanaIndice('no-es-fecha', '2026-10-05')));
});

test('las fechas se escriben en español y no dependen de la zona horaria', () => {
  assert.equal(tp.fechaLarga('2026-09-28'), '28 de septiembre de 2026');
  assert.equal(tp.fechaLarga('2027-01-01'), '1 de enero de 2027');
  assert.equal(tp.fechaCorta('2026-10-05'), '5 oct');
  assert.equal(tp.rangoCorto('2026-10-09', '2026-10-14'), '9 – 14 oct');
  assert.equal(tp.rangoCorto('2026-10-28', '2026-11-05'), '28 oct – 5 nov');
  assert.equal(tp.rangoCorto('2026-12-23', '2026-12-23'), '23 dic');
  assert.equal(tp.diasEntre('2026-10-05', '2026-10-12'), 7);
  assert.equal(tp.fechaLarga('2026-13-01'), '');
});

test('días hábiles en singular y plural', () => {
  assert.equal(tp.diasTexto(1), '1 día hábil');
  assert.equal(tp.diasTexto(3), '3 días hábiles');
  assert.equal(tp.diasTexto('x'), '0 días hábiles');
});

test('urlSegura solo deja pasar enlaces https sin comillas ni espacios', () => {
  assert.equal(tp.urlSegura(' https://wa.me/56927002984?text=Hola '), 'https://wa.me/56927002984?text=Hola');
  assert.equal(tp.urlSegura('javascript:alert(1)'), '');
  assert.equal(tp.urlSegura('http://automatizatech.cl'), '');
  assert.equal(tp.urlSegura('https://x.cl/"><script>'), '');
  assert.equal(tp.urlSegura(''), '');
  assert.equal(tp.urlSegura(null), '');
});
