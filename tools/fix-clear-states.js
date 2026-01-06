const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Clear Cancel State - obtener phoneNumber de Check Cancel Event Status
const clearCancelIdx = j.nodes.findIndex(n => n.name === 'Clear Cancel State');
if (clearCancelIdx >= 0) {
    j.nodes[clearCancelIdx].parameters.key = "=cancel_{{ $('Check Cancel Event Status').first().json.phoneNumber }}";
    console.log('✅ Clear Cancel State corregido');
}

// Corregir Clear Cancel Confirmed
const clearConfirmedIdx = j.nodes.findIndex(n => n.name === 'Clear Cancel Confirmed');
if (clearConfirmedIdx >= 0) {
    j.nodes[clearConfirmedIdx].parameters.key = "=cancel_confirmed_{{ $('Check Cancel Event Status').first().json.phoneNumber }}";
    console.log('✅ Clear Cancel Confirmed corregido');
}

// Verificar Clear Reschedule State y Clear Confirmed State también
const clearRescheduleIdx = j.nodes.findIndex(n => n.name === 'Clear Reschedule State');
if (clearRescheduleIdx >= 0) {
    console.log('Clear Reschedule State key:', j.nodes[clearRescheduleIdx].parameters.key);
}

const clearConfStateIdx = j.nodes.findIndex(n => n.name === 'Clear Confirmed State');
if (clearConfStateIdx >= 0) {
    console.log('Clear Confirmed State key:', j.nodes[clearConfStateIdx].parameters.key);
}

// También corregir Send Keep Appointment para que limpie estados
// Buscar conexiones de Send Keep Appointment
const keepAppConns = j.connections['Send Keep Appointment'];
console.log('\nSend Keep Appointment conexiones:', keepAppConns ? 'tiene' : 'NO tiene conexiones');

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
