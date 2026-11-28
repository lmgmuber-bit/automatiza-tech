// Prueba local del nuevo "Preparar Datos" de ARGOS antes de subirlo.
const fs = require('fs');
const path = require('path');
const assert = require('assert');

const code = fs.readFileSync(path.join(__dirname, '..', 'codigo-nodos', 'argos_preparar_datos.js'), 'utf8');
const CUERPO = '🛡️ *ARGOS · Error en n8n*\n\n⚠️ *Severidad:* {{1}}\n📋 *Workflow:* {{2}}\n🔧 *Nodo:* {{3}}\n⏰ *Hora:* {{4}}\n\n💬 *Error:* {{5}}\n\n🤖 *Análisis:* {{6}}\n\n👁️ Argos vigila. Argos protege.';

function correr(errorTrigger, argosOutput, historial) {
  const nodos = { 'Error Trigger': errorTrigger, 'ARGOS': { output: argosOutput }, 'Buscar Historial BD': historial };
  const $ = n => ({ item: { json: nodos[n] } });
  return new Function('$', code)($).json;
}

function revisar(out) {
  const b = JSON.parse(out.whatsappBody);
  assert.strictEqual(b.type, 'template');
  assert.strictEqual(b.to, '56974940070');
  assert.strictEqual(b.template.name, 'alerta_argos');
  const ps = b.template.components[0].parameters.map(p => p.text);
  assert.strictEqual(ps.length, 6, '6 parametros = 6 variables de la plantilla');
  for (const p of ps) assert.ok(p && p === p.trim() && !/[\n\t]/.test(p) && !/ {4,}/.test(p), `parametro invalido: ${JSON.stringify(p)}`);
  let expandido = CUERPO;
  ps.forEach((p, i) => { expandido = expandido.replace(`{{${i + 1}}}`, p); });
  assert.ok(expandido.length <= 1024, `cuerpo expandido ${expandido.length} > 1024`);
  return { ps, largo: expandido.length };
}

const analisisLargo = ('El nodo HTTP devolvio 401.\n\nCausa probable: el token vencio.\n• Revisar la credencial\n• Reintentar\n').repeat(25);
let n = 0;

// 1) error normal de un nodo, no recurrente
let out = correr(
  { workflow: { id: 'abc', name: 'WhatsApp Recordatorio 24h - AutomatizaTech (PROD)' },
    execution: { id: '404001', mode: 'trigger', error: { message: 'Bad request - please check your parameters', node: { name: 'Send WhatsApp 24h' }, stack: 'NodeApiError...' } } },
  'El envio fallo porque la plantilla no esta aprobada todavia.',
  { summary: { is_recurring: false, errors_exact_match: 0 } });
let r = revisar(out);
assert.strictEqual(r.ps[0], 'MEDIO · nuevo');
assert.strictEqual(r.ps[2], 'Send WhatsApp 24h');
console.log(`OK error normal (${r.largo} car):`, JSON.stringify(r.ps)); n++;

// 2) fallo de TRIGGER (el fix del 6-sep) + analisis enorme con saltos de linea + recurrente
out = correr(
  { workflow: { id: 'FrWZ', name: 'Google Meet → Propuesta AutomatizaTech' }, trigger: { error: { message: 'Account Restricted', node: { name: 'Nueva Transcripción en Drive' } } } },
  analisisLargo,
  { summary: { is_recurring: true, errors_exact_match: 4 } });
r = revisar(out);
assert.strictEqual(r.ps[0], 'CRÍTICO · recurrente #5');
assert.strictEqual(r.ps[4], 'Account Restricted');
assert.ok(r.ps[5].endsWith('…'), 'el analisis largo se recorta con …');
assert.ok(r.largo <= 1000, 'queda bajo el margen de 1000');
assert.strictEqual(out.argos_analysis, analisisLargo, 'la base y el correo reciben el analisis COMPLETO');
assert.ok(out.emailHtml.includes('Causa probable: el token vencio.'), 'el correo sigue con el analisis');
assert.strictEqual(out.error_message, 'Account Restricted'); assert.strictEqual(out.execution_mode, 'trigger');
console.log(`OK fallo de trigger + analisis de ${analisisLargo.length} car -> mensaje de ${r.largo} car; correo y base con el texto completo`); n++;

// 3) nombres y error larguisimos
out = correr(
  { workflow: { id: 'x', name: 'W'.repeat(300) }, execution: { id: '1', mode: 'webhook', error: { message: ('linea\n').repeat(200), node: { name: 'N'.repeat(200) } } } },
  analisisLargo, { summary: {} });
r = revisar(out);
assert.ok(r.ps[1].length <= 90 && r.ps[2].length <= 60 && r.ps[4].length <= 250);
console.log(`OK todo larguisimo -> ${r.largo} car, ningun parametro vacio ni multilinea`); n++;

// 4) sin analisis ni historial
out = correr({ workflow: { name: '' }, execution: { error: {} } }, '', {});
r = revisar(out);
assert.deepStrictEqual([r.ps[1], r.ps[2]], ['Unknown', 'Desconocido']);
console.log('OK sin datos: valores por defecto, nunca vacios'); n++;

console.log(`\n${n} pruebas pasaron`);
