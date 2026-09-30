// Prueba del freno de ARGOS: la busqueda del historial y la marca enviar_whatsapp.
const fs = require('fs');
const path = require('path');
const assert = require('assert');

const URL_VIEJA = "=https://automatizatech.cl/wp-json/automatiza/v1/n8n-errors/search?workflow_name={{ encodeURIComponent($json.workflow.name) }}&error_node={{ encodeURIComponent($json.execution.error.node?.name || '') }}&error_message={{ encodeURIComponent($json.execution.error.message?.substring(0, 100) || '') }}&days=30";
const URL_NUEVA = fs.readFileSync(path.join(__dirname, '..', 'codigo-nodos', 'argos_url_historial.txt'), 'utf8').trim();

function expandir(url, $json) {
  return url.replace(/^=/, '').replace(/\{\{(.*?)\}\}/g, (_, e) => {
    try { return String(new Function('$json', `return (${e});`)($json)); } catch (err) { return `<<ERROR ${err.message}>>`; }
  });
}

const normal = { workflow: { name: 'Recordatorio 24h' }, execution: { id: '1', error: { message: 'Bad request - please check your parameters', node: { name: 'Send WhatsApp 24h' } } } };
const trigger = { workflow: { name: 'Google Meet → Propuesta AutomatizaTech' }, trigger: { error: { message: 'Account Restricted', node: { name: 'Nueva Transcripción en Drive' } } } };
let n = 0;

assert.strictEqual(expandir(URL_NUEVA, normal), expandir(URL_VIEJA, normal));
console.log('OK error normal: la busqueda del historial queda IGUAL que antes'); n++;

const vieja = expandir(URL_VIEJA, trigger), nueva = expandir(URL_NUEVA, trigger);
console.log('   fallo de trigger, antes :', vieja.split('?')[1].slice(0, 120));
console.log('   fallo de trigger, ahora :', nueva.split('?')[1].slice(0, 120));
assert.ok(nueva.includes('error_node=Nueva%20Transcripci%C3%B3n%20en%20Drive') && nueva.includes('error_message=Account%20Restricted'));
console.log('OK fallo de trigger: ahora la busqueda lleva nodo y mensaje (antes no), asi el freno puede contar'); n++;

// marca enviar_whatsapp del Preparar Datos nuevo
const code = fs.readFileSync(path.join(__dirname, '..', 'codigo-nodos', 'argos_preparar_datos.js'), 'utf8');
const correr = historial => {
  const nodos = { 'Error Trigger': trigger, 'ARGOS': { output: 'analisis' }, 'Buscar Historial BD': historial };
  return new Function('$', code)(nd => ({ item: { json: nodos[nd] } })).json;
};
assert.strictEqual(correr({ summary: { errors_exact_match: 0 } }).enviar_whatsapp, true);
assert.strictEqual(correr({ summary: { errors_exact_match: 1 } }).enviar_whatsapp, false);
assert.strictEqual(correr({ summary: { errors_exact_match: 7, is_recurring: true } }).enviar_whatsapp, false);
assert.strictEqual(correr({ error: { message: 'historial caido' } }).enviar_whatsapp, true);
assert.ok(correr({ summary: { errors_exact_match: 7 } }).emailHtml.length > 0, 'el correo se arma igual');
console.log('OK enviar_whatsapp: 1a vez si; repetido no; si el historial falla, avisa igual (mejor de mas que de menos)'); n++;

console.log(`\n${n} pruebas pasaron`);
