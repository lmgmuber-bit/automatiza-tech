const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Crear nodo de envío de correo para cancelación
const sendCancelEmailNode = {
    parameters: {
        method: "POST",
        url: "https://automatizatech.cl/wp-json/automatiza-tech/v1/send-email",
        authentication: "none",
        sendBody: true,
        specifyBody: "json",
        jsonBody: `={
  "to": "{{ $('Check Cancel Event Status').first().json.email }}",
  "subject": "Lamentamos que hayas cancelado tu cita - AutomatizaTech",
  "template": "cancellation",
  "data": {
    "clientName": "{{ $('Check Cancel Event Status').first().json.clientName }}",
    "summary": "{{ $('Check Cancel Event Status').first().json.summary }}",
    "companyName": "AutomatizaTech",
    "websiteUrl": "https://automatizatech.cl",
    "whatsappNumber": "+56912345678"
  }
}`,
        options: {
            timeout: 10000
        }
    },
    type: "n8n-nodes-base.httpRequest",
    typeVersion: 4.2,
    position: [116200, 44100],
    id: "send-cancel-email-node",
    name: "Send Cancel Email",
    onError: "continueRegularOutput" // No bloquear el flujo si falla el correo
};

j.nodes.push(sendCancelEmailNode);
console.log('✅ Nodo Send Cancel Email agregado');

// Modificar conexiones:
// Antes: Send Cancel Success -> Clear Cancel State
// Ahora: Send Cancel Success -> Send Cancel Email -> Clear Cancel State

j.connections['Send Cancel Success'] = {
    main: [[{ node: 'Send Cancel Email', type: 'main', index: 0 }]]
};

j.connections['Send Cancel Email'] = {
    main: [[{ node: 'Clear Cancel State', type: 'main', index: 0 }]]
};

console.log('✅ Conexiones actualizadas');

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}

console.log('\n=== FLUJO DE ANULACIÓN ACTUALIZADO ===');
console.log('Delete Cancelled Appointment ->');
console.log('  Send Cancel Success ->');
console.log('  Send Cancel Email (NUEVO - envía correo) ->');
console.log('  Clear Cancel State ->');
console.log('  Clear Cancel Confirmed');
console.log('\n=== FLUJO DE REPROGRAMACIÓN (sin correo) ===');
console.log('Delete For Reschedule ->');
console.log('  Send Reschedule Cancelled ->');
console.log('  Set Reschedule Data ->');
console.log('  Clear Reschedule States ->');
console.log('  ... (continúa al flujo de booking)');
