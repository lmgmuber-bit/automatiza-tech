const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Configurar Delete Cancelled Appointment para no fallar en errores
const idx = j.nodes.findIndex(n => n.name === 'Delete Cancelled Appointment');
if (idx >= 0) {
    // Agregar configuración para no fallar en errores HTTP
    j.nodes[idx].parameters.options = {
        "response": {
            "response": {
                "fullResponse": true,
                "responseFormat": "json"
            }
        }
    };
    // Agregar onError: continueRegularOutput para que no falle
    j.nodes[idx].onError = "continueRegularOutput";
    console.log('✅ Delete Cancelled Appointment configurado para manejar errores');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
