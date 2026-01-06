const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Delete Cancelled Appointment - usar POST con _method=DELETE (más compatible)
// O agregar hard_delete para eliminar realmente
const idx = j.nodes.findIndex(n => n.name === 'Delete Cancelled Appointment');
if (idx >= 0) {
    // Opción 1: Usar DELETE con hard_delete
    j.nodes[idx].parameters = {
        "method": "DELETE",
        "url": "=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/{{ $json.appointmentId }}?hard_delete=true",
        "options": {
            "response": {
                "response": {
                    "fullResponse": true,
                    "responseFormat": "json"
                }
            }
        }
    };
    // Agregar onError para continuar en caso de error
    j.nodes[idx].onError = "continueRegularOutput";
    console.log('✅ Delete Cancelled Appointment corregido con hard_delete=true');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
