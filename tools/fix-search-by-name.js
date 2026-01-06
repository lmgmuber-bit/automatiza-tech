const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Search Cancel Appointment - agregar nombre a la búsqueda
const idx = j.nodes.findIndex(n => n.name === 'Search Cancel Appointment');
if (idx >= 0) {
    j.nodes[idx].parameters.url = "=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/search?email={{ encodeURIComponent($json.email) }}&name={{ encodeURIComponent($json.name) }}&future_only=true";
    console.log('✅ Search Cancel Appointment corregido - ahora busca por email Y nombre');
}

// También corregir Search Reschedule Appointment (mismo problema potencial)
const rescheduleIdx = j.nodes.findIndex(n => n.name === 'Search Reschedule Appointment');
if (rescheduleIdx >= 0) {
    j.nodes[rescheduleIdx].parameters.url = "=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/search?email={{ encodeURIComponent($json.email) }}&name={{ encodeURIComponent($json.name) }}&future_only=true";
    console.log('✅ Search Reschedule Appointment corregido - ahora busca por email Y nombre');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
