const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Send Cancel Success - obtener phoneNumber y phoneNumberId de Check Cancel Event Status
const idx = j.nodes.findIndex(n => n.name === 'Send Cancel Success');
if (idx >= 0) {
    j.nodes[idx].parameters.url = "=https://graph.facebook.com/v22.0/{{ $('Check Cancel Event Status').first().json.phoneNumberId }}/messages";
    j.nodes[idx].parameters.jsonBody = `={{ JSON.stringify({ messaging_product: "whatsapp", recipient_type: "individual", to: $('Check Cancel Event Status').first().json.phoneNumber, type: "text", text: { preview_url: false, body: "✅ Tu cita ha sido cancelada exitosamente.\\n\\nSi deseas agendar una nueva cita en el futuro, escribe \\"agendar\\" 📆" } }) }}`;
    console.log('✅ Send Cancel Success corregido - obtiene datos de Check Cancel Event Status');
}

// También corregir Send Cancel Event Error
const errIdx = j.nodes.findIndex(n => n.name === 'Send Cancel Event Error');
if (errIdx >= 0) {
    j.nodes[errIdx].parameters.url = "=https://graph.facebook.com/v22.0/{{ $('Check Cancel Event Status').first().json.phoneNumberId }}/messages";
    j.nodes[errIdx].parameters.jsonBody = `={{ JSON.stringify({ messaging_product: "whatsapp", recipient_type: "individual", to: $('Check Cancel Event Status').first().json.phoneNumber, type: "text", text: { preview_url: false, body: "❌ No se pudo procesar la cancelación. Por favor intenta de nuevo escribiendo \\"cancelar cita\\"." } }) }}`;
    console.log('✅ Send Cancel Event Error corregido');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
