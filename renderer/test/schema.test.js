const test = require('node:test');
const assert = require('node:assert/strict');
const { validatePayload } = require('../src/schema');

const VALID_PAYLOAD = {
  unique_id: 'abc123',
  client_name: 'Jeffer Garcia',
  company_name: 'Academia de Béisbol X',
  challenge_title: 'Gestión manual',
  challenge_text: 'Texto',
  solution_title: 'Solución',
  solution_text: 'Texto',
  benefits: [{ title: 'A', text: 'B' }],
  how_it_works: [{ step_title: 'A', step_text: 'B' }],
  pricing_rows: [{ service: 'Sitio web', price_usd: 500, price_clp: 460000 }],
  next_steps: ['Aprobación'],
};

test('accepts a fully populated payload', () => {
  const result = validatePayload(VALID_PAYLOAD);
  assert.equal(result.valid, true);
  assert.deepEqual(result.errors, []);
});

test('rejects a payload missing required fields', () => {
  const result = validatePayload({});
  assert.equal(result.valid, false);
  assert.ok(result.errors.includes('missing or empty required field: unique_id'));
  assert.ok(result.errors.includes('missing or empty required array field: benefits'));
});

test('rejects a non-object body', () => {
  const result = validatePayload(null);
  assert.equal(result.valid, false);
  assert.deepEqual(result.errors, ['request body must be a JSON object']);
});

test('rejects image_briefs when present but not an array', () => {
  const result = validatePayload({ ...VALID_PAYLOAD, image_briefs: 'nope' });
  assert.equal(result.valid, false);
  assert.ok(result.errors.includes('image_briefs must be an array when present'));
});
