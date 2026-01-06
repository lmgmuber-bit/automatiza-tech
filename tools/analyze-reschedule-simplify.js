const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

console.log('=== SIMPLIFICANDO FLUJO DE REPROGRAMACIÓN ===\n');
console.log('NUEVO FLUJO:');
console.log('1. Usuario dice "reprogramar"');
console.log('2. Bot pide datos (nombre + email)');
console.log('3. Bot busca y muestra la cita');
console.log('4. Usuario confirma');
console.log('5. Bot CANCELA la cita');
console.log('6. Bot muestra mensaje + opciones de días para NUEVA cita');
console.log('7. Continúa flujo normal de booking\n');

// Buscar el nodo que maneja btn_confirmar_reprogramar en Button Action
const buttonAction = j.nodes.find(n => n.name === 'Button Action');
console.log('Button Action outputs:');
const baConns = j.connections['Button Action'];
baConns?.main?.forEach((arr, i) => {
    console.log(`  Output ${i}:`, arr.map(c => c.node));
});

// Ver qué output va a Get Reschedule Event
// Ese es el que debemos modificar para que:
// 1. Elimine la cita
// 2. Envíe mensaje de confirmación
// 3. Lleve al flujo de booking (Get Appointments Config -> Generate Days)

// Buscar las reglas de Button Action
const rules = buttonAction?.parameters?.rules?.values;
rules?.forEach((rule, i) => {
    const condition = rule.conditions?.conditions?.[0]?.rightValue;
    console.log(`\nOutput ${i}: ${rule.outputKey || 'default'}`);
    console.log(`  Condición: buttonId === "${condition}"`);
});
