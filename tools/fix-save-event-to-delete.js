const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Save Event To Delete
const saveEventIdx = j.nodes.findIndex(n => n.name === 'Save Event To Delete');
if (saveEventIdx >= 0) {
    const oldValue = j.nodes[saveEventIdx].parameters.value;
    console.log('=== Save Event To Delete (antes) ===');
    console.log('Value corrupto:', oldValue.includes('@{name='));
    
    // Corregir el value
    j.nodes[saveEventIdx].parameters.value = "={{ JSON.stringify({ appointmentId: $json.appointmentId, eventId: $json.eventId, email: $json.email, clientName: $json.clientName, summary: $json.summary }) }}";
    console.log('\n✅ Save Event To Delete corregido');
}

// También verificar Show Confirmation Buttons usa $json.eventStart pero Process Search Results retorna "start"
const showConfIdx = j.nodes.findIndex(n => n.name === 'Show Confirmation Buttons');
if (showConfIdx >= 0) {
    const jsonBody = j.nodes[showConfIdx].parameters.jsonBody || '';
    if (jsonBody.includes('eventStart')) {
        // Cambiar eventStart por start
        j.nodes[showConfIdx].parameters.jsonBody = jsonBody.replace(/\$json\.eventStart/g, '$json.start');
        console.log('✅ Show Confirmation Buttons corregido (eventStart -> start)');
    }
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
