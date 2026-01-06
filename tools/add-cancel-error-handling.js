const fs = require('fs');

const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';
const j = JSON.parse(fs.readFileSync(filePath, 'utf8'));

// Agregar nodo IF para verificar status
const newIfNode = {
  parameters: {
    conditions: {
      options: { caseSensitive: true, leftValue: '', typeValidation: 'strict' },
      conditions: [{
        leftValue: '={{ $json.status }}',
        rightValue: 'DELETE',
        operator: { type: 'string', operation: 'equals', name: 'filter.operator.equals' }
      }],
      combinator: 'and'
    },
    options: {}
  },
  type: 'n8n-nodes-base.if',
  typeVersion: 2,
  position: [119500, 42856],
  id: 'check-cancel-event-status',
  name: 'Check Cancel Event Status'
};

// Agregar nodo de error
const errorNode = {
  parameters: {
    method: 'POST',
    url: '=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages',
    authentication: 'predefinedCredentialType',
    nodeCredentialType: 'whatsAppApi',
    sendBody: true,
    specifyBody: 'json',
    jsonBody: '={{ JSON.stringify({ messaging_product: "whatsapp", recipient_type: "individual", to: $json.phoneNumber, type: "text", text: { preview_url: false, body: "❌ No se pudo procesar la cancelación. Por favor intenta de nuevo escribiendo \\"cancelar cita\\"." } }) }}',
    options: {}
  },
  type: 'n8n-nodes-base.httpRequest',
  typeVersion: 4.1,
  position: [119700, 43050],
  id: 'send-cancel-event-error',
  name: 'Send Cancel Event Error',
  credentials: { whatsAppApi: { id: 'SH8OXr93p852Ll6m', name: 'WhatsApp Tech' } }
};

j.nodes.push(newIfNode);
j.nodes.push(errorNode);

// Actualizar conexiones
j.connections['Parse Cancel Event'] = { 
  main: [[{ node: 'Check Cancel Event Status', type: 'main', index: 0 }]] 
};

j.connections['Check Cancel Event Status'] = { 
  main: [
    [{ node: 'Delete Cancelled Appointment', type: 'main', index: 0 }],  // true
    [{ node: 'Send Cancel Event Error', type: 'main', index: 0 }]       // false
  ] 
};

fs.writeFileSync(filePath, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Nodos agregados y conexiones actualizadas');
console.log('Total nodos:', j.nodes.length);
