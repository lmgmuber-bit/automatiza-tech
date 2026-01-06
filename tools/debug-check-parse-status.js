const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver Check Parse Status (para reschedule)
const checkParse = j.nodes.find(n => n.name === 'Check Parse Status');
console.log('=== Check Parse Status (Reschedule) ===');
console.log('Type:', checkParse?.type);
const cpsRules = checkParse?.parameters?.rules?.values;
cpsRules?.forEach((r, i) => {
    console.log(`  Output ${i}: ${r.outputKey} - ${r.conditions?.conditions?.[0]?.rightValue}`);
});

// Conexiones
const cpsConns = j.connections['Check Parse Status'];
console.log('Conexiones:');
cpsConns?.main?.forEach((arr, i) => {
    console.log(`  Output ${i}:`, arr.map(c => c.node));
});

console.log('\n' + '='.repeat(50));

// Ver Check Cancel Parse Status
const checkCancelParse = j.nodes.find(n => n.name === 'Check Cancel Parse Status');
console.log('\n=== Check Cancel Parse Status ===');
console.log('Type:', checkCancelParse?.type);
const ccpsRules = checkCancelParse?.parameters?.rules?.values;
ccpsRules?.forEach((r, i) => {
    console.log(`  Output ${i}: ${r.outputKey} - ${r.conditions?.conditions?.[0]?.rightValue}`);
});

// Conexiones
const ccpsConns = j.connections['Check Cancel Parse Status'];
console.log('Conexiones:');
ccpsConns?.main?.forEach((arr, i) => {
    console.log(`  Output ${i}:`, arr.map(c => c.node));
});

// El problema: cuando status === NOT_WAITING, ¿a dónde va?
// Si no hay output definido para NOT_WAITING, podría causar error o flujo inesperado
