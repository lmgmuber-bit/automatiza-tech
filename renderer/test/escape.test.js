const test = require('node:test');
const assert = require('node:assert/strict');
const { escapeHtml } = require('../src/escape');

test('escapes html special characters', () => {
  assert.equal(escapeHtml('<script>alert("x")</script>'), '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;');
});

test('returns empty string for null/undefined', () => {
  assert.equal(escapeHtml(null), '');
  assert.equal(escapeHtml(undefined), '');
});

test('passes through plain text unchanged', () => {
  assert.equal(escapeHtml('Academia de Béisbol'), 'Academia de Béisbol');
});
