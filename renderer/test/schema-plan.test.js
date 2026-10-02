const test = require('node:test');
const assert = require('node:assert/strict');
const { validatePlanPayload, validatePayload } = require('../src/schema');
const { planCorto } = require('./fixtures/plan-ejemplo');

test('acepta el cuerpo de un plan completo', () => {
  assert.deepEqual(validatePlanPayload(planCorto()), { valid: true, errors: [] });
});

test('rechaza un cuerpo que no es un objeto', () => {
  for (const cuerpo of [null, [], 'plan']) {
    assert.deepEqual(validatePlanPayload(cuerpo), { valid: false, errors: ['el cuerpo debe ser un objeto JSON'] });
  }
});

test('exige unique_id (con la forma que acepta /render), company_name y proyecto con texto', () => {
  const r = validatePlanPayload({ ...planCorto(), unique_id: '', company_name: '   ', proyecto: 7 });
  assert.equal(r.valid, false);
  assert.ok(r.errors.includes('falta o está vacío el campo obligatorio: unique_id'));
  assert.ok(r.errors.includes('falta o está vacío el campo obligatorio: company_name'));
  assert.ok(r.errors.includes('falta o está vacío el campo obligatorio: proyecto'));
  assert.ok(!r.errors.some((e) => e.startsWith('unique_id debe')), 'vacío: un solo motivo');
  // El código del plan (12 letras y números, wp_generate_password) siempre pasa; un unique_id raro se rechaza
  // con motivo legible, igual que los demás errores del esquema.
  assert.deepEqual(validatePlanPayload({ ...planCorto(), unique_id: 'Ab3dE5fG7hJ9' }), { valid: true, errors: [] });
  for (const malo of ['../fuera', 'corto', 'con espacio 1']) {
    assert.deepEqual(validatePlanPayload({ ...planCorto(), unique_id: malo }).errors, [
      'unique_id debe tener de 6 a 64 caracteres, solo letras, números, guion o guion bajo',
    ]);
  }
});

test('exige al menos una fase', () => {
  assert.ok(validatePlanPayload({ ...planCorto(), fases: [] }).errors.includes('falta o está vacío el arreglo obligatorio: fases'));
  const sin = planCorto();
  delete sin.fases;
  assert.ok(validatePlanPayload(sin).errors.includes('falta o está vacío el arreglo obligatorio: fases'));
});

test('exige el cronograma con inicio y fin como fechas reales y las barras como arreglo', () => {
  const sin = planCorto();
  delete sin.cronograma;
  assert.deepEqual(validatePlanPayload(sin).errors, ['falta el objeto obligatorio: cronograma']);

  const malo = planCorto();
  malo.cronograma = { inicio: '05-10-2026', fin: '2026-02-30', barras: 'ninguna' };
  const errores = validatePlanPayload(malo).errors;
  assert.ok(errores.includes('cronograma.inicio debe ser una fecha AAAA-MM-DD'));
  assert.ok(errores.includes('cronograma.fin debe ser una fecha AAAA-MM-DD'));
  assert.ok(errores.includes('cronograma.barras debe ser un arreglo'));

  const alReves = planCorto();
  alReves.cronograma.fin = '2026-09-01';
  assert.ok(validatePlanPayload(alReves).errors.includes('cronograma.fin no puede ser anterior a cronograma.inicio'));
});

test('rechaza image_briefs que no sea un arreglo', () => {
  assert.ok(validatePlanPayload({ ...planCorto(), image_briefs: 'fotos' }).errors.includes('image_briefs debe ser un arreglo si viene'));
});

test('la validación de propuestas no cambia: un plan no pasa como propuesta', () => {
  assert.equal(validatePayload(planCorto()).valid, false);
});
