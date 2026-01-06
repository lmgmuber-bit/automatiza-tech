const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Save Event To Delete viene de Show Confirmation Buttons
// Esto significa que se guarda JUSTO DESPUÉS de mostrar los botones
// Y antes de que el usuario presione el botón

// Esto está bien, pero necesitamos verificar que los datos lleguen correctamente

console.log('=== FLUJO DE GUARDADO ===');
console.log('1. Process Search Results extrae: appointmentId, email, clientName, etc.');
console.log('2. Check Appointment Found (IF) filtra solo los encontrados');
console.log('3. Show Confirmation Buttons envía mensaje (usa $json que viene de Process Search Results)');
console.log('4. Save Event To Delete guarda en Redis (usa $json que viene de Show Confirmation Buttons)');
console.log('');
console.log('⚠️ PROBLEMA: Show Confirmation Buttons es un HTTP Request.');
console.log('   El output de un HTTP Request es la RESPUESTA del servidor, no el input.');
console.log('   Por lo tanto, Save Event To Delete NO tiene acceso a appointmentId!');

// La solución es usar referencia explícita a Process Search Results
console.log('\n=== APLICANDO FIX ===');

const saveNode = j.nodes.find(n => n.name === 'Save Event To Delete');
// Cambiar las referencias de $json a $('Process Search Results').first().json
saveNode.parameters.key = "=reschedule_confirmed_{{ $('Process Search Results').first().json.phoneNumber }}";
saveNode.parameters.value = "={{ JSON.stringify({ appointmentId: $('Process Search Results').first().json.appointmentId, eventId: $('Process Search Results').first().json.eventId, email: $('Process Search Results').first().json.email, clientName: $('Process Search Results').first().json.clientName, summary: $('Process Search Results').first().json.summary }) }}";

console.log('✅ Save Event To Delete actualizado para usar Process Search Results directamente');

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
