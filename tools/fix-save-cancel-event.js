const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Save Cancel Event - obtener datos de Check Cancel Found (no de Show Cancel Confirmation)
const idx = j.nodes.findIndex(n => n.name === 'Save Cancel Event');
if (idx >= 0) {
    j.nodes[idx].parameters.key = "=cancel_confirmed_{{ $('Check Cancel Found').first().json.phoneNumber }}";
    j.nodes[idx].parameters.value = "={{ JSON.stringify({ appointmentId: $('Check Cancel Found').first().json.appointmentId, eventId: $('Check Cancel Found').first().json.eventId, email: $('Check Cancel Found').first().json.email, clientName: $('Check Cancel Found').first().json.clientName, summary: $('Check Cancel Found').first().json.eventSummary }) }}";
    console.log('✅ Save Cancel Event corregido - ahora obtiene datos de Check Cancel Found');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
