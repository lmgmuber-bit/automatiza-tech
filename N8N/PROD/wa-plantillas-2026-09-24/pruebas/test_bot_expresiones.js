// Compara las expresiones de "Process Interactive" antes y despues del cambio,
// con los tres formatos de mensaje que puede recibir el bot. Sin tocar n8n.
const assert = require('assert');
const fs = require('fs');
const path = require('path');

// El respaldo del bot vive en la boveda privada (este repo es publico). Uso:
//   node test_bot_expresiones.js <bot_publicado.json> [respaldo_previo.json]
const RESPALDO = process.argv[3] || 'C:/Users/luis_/Documents/Codex/AI-Memory-Vault/90-Archive/respaldos-n8n/2026-09-24-wa-plantillas/respaldo_bBcNlFgBzQ0766Mq_20260924-143033.json';
const antes = JSON.parse(fs.readFileSync(RESPALDO, 'utf8'));
const pi = n => n.nodes.find(x => x.name === 'Process Interactive').parameters.values.string;
const mt = n => n.nodes.find(x => x.name === 'Message Type').parameters.rules.rules;

// la version nueva publicada la guardo el aplicador como respaldo previo; la nueva la reconstruimos de PROD
const despuesArchivo = process.argv[2];
const crudo = JSON.parse(fs.readFileSync(despuesArchivo, 'utf8'));
assert.strictEqual(crudo.versionId, crudo.activeVersionId, 'el borrador del bot no coincide con lo publicado');
const despues = crudo.activeVersion || crudo; // se prueba la version PUBLICADA, la que corre

function evaluar(expr, msg) {
  const cuerpo = expr.replace(/^=\{\{\s*/, '').replace(/\s*\}\}$/, '');
  const $node = { 'WhatsApp Webhook': { json: { body: { entry: [{ changes: [{ value: { messages: [msg] } }] }] } } } };
  try { return new Function('$node', `return (${cuerpo});`)($node); } catch (e) { return `ERROR: ${e.message}`; }
}

const casos = {
  'interactive button_reply': { type: 'interactive', interactive: { type: 'button_reply', button_reply: { id: 'btn_plan_pro', title: 'Plan Pro' } } },
  'interactive list_reply': { type: 'interactive', interactive: { type: 'list_reply', list_reply: { id: 'slot_10', title: '10:00' } } },
  'button de plantilla': { type: 'button', button: { payload: 'btn_reminder_confirm_99', text: 'Sí, confirmo' } },
};

for (const [nombre, msg] of Object.entries(casos)) {
  const r = {};
  for (const [lado, wf] of [['antes', antes], ['despues', despues]]) {
    r[lado] = Object.fromEntries(pi(wf).filter(v => ['interactiveType', 'buttonId', 'buttonTitle'].includes(v.name))
      .map(v => [v.name, evaluar(v.value, msg)]));
  }
  console.log(`\n${nombre}`);
  console.log('  antes  :', JSON.stringify(r.antes));
  console.log('  despues:', JSON.stringify(r.despues));
  if (nombre.startsWith('interactive')) {
    assert.deepStrictEqual(r.despues, r.antes, `${nombre}: el cambio altero un caso que ya funcionaba`);
    console.log('  OK: identico al comportamiento anterior');
  } else {
    assert.ok(String(r.antes.buttonId).startsWith('ERROR'), 'antes, el boton de plantilla deberia romper la expresion');
    assert.strictEqual(r.despues.buttonId, 'btn_reminder_confirm_99');
    assert.strictEqual(r.despues.buttonTitle, 'Sí, confirmo');
    console.log('  OK: antes se rompia, ahora entrega el id que enruta "Button Action"');
  }
}

// ruteo de Message Type: 'button' debe ir a la misma salida que 'interactive' y nada mas cambiar
const salida = (reglas, t) => (reglas.find(r => r.value2 === t) || {}).output ?? (reglas.find(r => r.value2 === t) ? 0 : 'fallback');
for (const t of ['text', 'audio', 'image', 'interactive']) assert.strictEqual(salida(mt(despues), t), salida(mt(antes), t));
assert.strictEqual(salida(mt(antes), 'button'), 'fallback');
assert.strictEqual(salida(mt(despues), 'button'), 3);
console.log('\nMessage Type: text/audio/image/interactive igual que antes; button pasa de fallback (perdido) a la salida 3');
console.log('\nTodas las comprobaciones pasaron');
