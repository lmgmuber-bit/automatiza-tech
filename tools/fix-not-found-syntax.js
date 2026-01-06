const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Send Cancel Not Found - escapar comillas
const idx = j.nodes.findIndex(n => n.name === 'Send Cancel Not Found');
if (idx >= 0) {
    j.nodes[idx].parameters.jsonBody = `={{ JSON.stringify({ messaging_product: "whatsapp", recipient_type: "individual", to: $json.phoneNumber, type: "text", text: { preview_url: false, body: "❌ No encontré ninguna cita con los datos proporcionados.\\n\\nVerifica que:\\n• El nombre sea exacto\\n• El correo electrónico sea el que usaste al agendar\\n• Tengas una cita agendada a futuro\\n\\nSi necesitas ayuda, escribe soporte" } }) }}`;
    console.log('✅ Send Cancel Not Found corregido');
}

// También corregir otros nodos similares que puedan tener el mismo problema
const nodesToFix = [
    'Send Reschedule Not Found',
    'Send Invalid Format Message',
    'Send Reschedule Invalid Format'
];

nodesToFix.forEach(nodeName => {
    const nodeIdx = j.nodes.findIndex(n => n.name === nodeName);
    if (nodeIdx >= 0 && j.nodes[nodeIdx].parameters.jsonBody) {
        // Reemplazar comillas simples problemáticas
        let body = j.nodes[nodeIdx].parameters.jsonBody;
        // Cambiar comillas simples por dobles en el JSON.stringify
        body = body.replace(/JSON\.stringify\(\{/g, 'JSON.stringify({');
        body = body.replace(/messaging_product: 'whatsapp'/g, 'messaging_product: "whatsapp"');
        body = body.replace(/recipient_type: 'individual'/g, 'recipient_type: "individual"');
        body = body.replace(/type: 'text'/g, 'type: "text"');
        // Escapar comillas simples dentro del body
        body = body.replace(/'soporte'/g, 'soporte');
        body = body.replace(/'agendar'/g, 'agendar');
        body = body.replace(/'cancelar cita'/g, 'cancelar cita');
        j.nodes[nodeIdx].parameters.jsonBody = body;
        console.log(`✅ ${nodeName} revisado`);
    }
});

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
