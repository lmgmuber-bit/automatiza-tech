const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// El flujo para texto es:
// Trigger -> Process Text -> Check Pending Booking -> Check Pending Date -> Merge Booking Data -> Check Email Booking

// PERO TAMBIÉN HAY:
// Trigger -> Process Text -> Check Reschedule State
// Trigger -> Process Text -> Check Cancel State

// Estos flujos se ejecutan en PARALELO y pueden causar race conditions

console.log('=== FLUJOS PARALELOS DESDE PROCESS TEXT ===\n');

const ptConns = j.connections['Process Text'];
console.log('Process Text conecta a:');
ptConns?.main?.[0]?.forEach(c => console.log(`  - ${c.node}`));

// Si Check Reschedule State y Check Cancel State no encuentran estado,
// ¿qué hacen? ¿Terminan o continúan con el flujo normal?

// Verificar Check Reschedule State
const checkReschedule = j.nodes.find(n => n.name === 'Check Reschedule State');
console.log('\n=== Check Reschedule State ===');
console.log('Key:', checkReschedule?.parameters?.key);

// Conexiones de Check Reschedule State
const crsConns = j.connections['Check Reschedule State'];
console.log('Conecta a:', crsConns?.main?.[0]?.map(c => c.node));

// Check Cancel State
const checkCancel = j.nodes.find(n => n.name === 'Check Cancel State');
console.log('\n=== Check Cancel State ===');
console.log('Key:', checkCancel?.parameters?.key);

// Conexiones de Check Cancel State
const ccsConns = j.connections['Check Cancel State'];
console.log('Conecta a:', ccsConns?.main?.[0]?.map(c => c.node));

// El problema: Cuando el usuario escribe su nombre "Luis Miguel":
// 1. Check Pending Booking lee booking_XXX → tiene datos con step: WAITING_NAME
// 2. Check Reschedule State lee reschedule_XXX → NULL
// 3. Check Cancel State lee cancel_XXX → NULL

// Si hay nodos switch después de Check Reschedule/Cancel que NO manejan NULL,
// podrían causar problemas

// Verificar Is Reschedule Pending
const isReschedule = j.nodes.find(n => n.name === 'Is Reschedule Pending');
console.log('\n=== Is Reschedule Pending ===');
if (isReschedule) {
    console.log('Type:', isReschedule.type);
    console.log('Tiene condición para null/vacío:', isReschedule.parameters?.conditions?.conditions?.[0]?.leftValue || 'N/A');
}

// Verificar Is Cancel Pending
const isCancel = j.nodes.find(n => n.name === 'Is Cancel Pending');
console.log('\n=== Is Cancel Pending ===');
if (isCancel) {
    console.log('Type:', isCancel.type);
}
