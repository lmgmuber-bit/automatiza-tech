const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Configurar Send Keep Appointment correctamente
// Viene de Button Action output 9, necesita referenciar Check Cancel Event Status para phoneNumber
const idx = j.nodes.findIndex(n => n.name === 'Send Keep Appointment');
if (idx >= 0) {
    j.nodes[idx].parameters = {
        ...j.nodes[idx].parameters,
        operation: "sendMessage",
        to: "={{ $('Check Cancel Event Status').first().json.phoneNumber }}",
        textBody: "=✅ *Perfecto, tu cita se mantiene activa*\n\nTe esperamos en la fecha y hora programada.\n\n¿Hay algo más en lo que pueda ayudarte?",
        additionalFields: {
            phoneNumberId: "={{ $('Check Cancel Event Status').first().json.phoneNumberId }}"
        }
    };
    console.log('✅ Send Keep Appointment configurado');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
