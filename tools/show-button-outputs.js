const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

const ba = j.nodes.find(n => n.name === 'Button Action');
console.log('=== BUTTON ACTION OUTPUTS ===');
ba.parameters.rules.values.forEach((r, i) => {
    const buttonId = r.conditions.conditions[0].rightValue;
    const conn = j.connections['Button Action']?.main?.[i];
    const target = conn && conn[0] ? conn[0].node : 'NO CONNECTION';
    console.log(`Output ${i}: ${buttonId} -> ${target}`);
});

console.log('\n=== RESUMEN ===');
console.log('Output 6 (btn_confirmar_reprogramar) ahora va a Get Reschedule Event');
console.log('El flujo simplificado es:');
console.log('  1. Usuario confirma reprogramación');
console.log('  2. Se lee el evento de Redis');
console.log('  3. Se parsean los datos');
console.log('  4. Se ELIMINA la cita (DELETE)');
console.log('  5. Se envía mensaje de confirmación + aviso de nueva cita');
console.log('  6. Se limpia el estado de Redis');
console.log('  7. Se obtiene config de citas');
console.log('  8. Se generan los días disponibles');
console.log('  9. Se envían botones del calendario');
console.log('  10. Usuario selecciona día (flujo normal de booking)');
