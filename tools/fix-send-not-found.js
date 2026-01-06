const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Send Not Found Message
const sendNotFoundIdx = j.nodes.findIndex(n => n.name === 'Send Not Found Message');
if (sendNotFoundIdx >= 0) {
    // El jsonBody tiene un problema con 'soporte'' - comillas mal
    j.nodes[sendNotFoundIdx].parameters.jsonBody = `={\n  "messaging_product": "whatsapp",\n  "recipient_type": "individual",\n  "to": "{{ $json.phoneNumber }}",\n  "type": "text",\n  "text": {\n    "preview_url": false,\n    "body": "❌ No encontré ninguna cita con los datos proporcionados.\\n\\nVerifica que:\\n• El nombre sea exacto\\n• El correo electrónico sea el que usaste al agendar\\n• Tengas una cita agendada a futuro\\n\\nSi necesitas ayuda, escribe \\"soporte\\""\n  }\n}`;
    console.log('✅ Send Not Found Message corregido');
}

// Verificar Send Invalid Format Message también
const sendInvalidIdx = j.nodes.findIndex(n => n.name === 'Send Invalid Format Message');
if (sendInvalidIdx >= 0) {
    const body = j.nodes[sendInvalidIdx].parameters.jsonBody;
    console.log('\nSend Invalid Format Message jsonBody:', body);
}

// También verificar Process Search Results
const processSearchIdx = j.nodes.findIndex(n => n.name === 'Process Search Results');
if (processSearchIdx >= 0) {
    console.log('\n=== Process Search Results ===');
    console.log(j.nodes[processSearchIdx].parameters.jsCode);
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

// Verificar JSON válido
try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error JSON:', e.message);
}
