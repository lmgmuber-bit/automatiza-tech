const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// ESTRATEGIA: Modificar el flujo de btn_confirmar_reprogramar
// En lugar de ir a Get Reschedule Event -> Parse Event To Delete -> Delete Old Appointment -> Get Appointments Config...
// Hacer: Get Reschedule Event -> Delete For Reschedule -> Send Reschedule Cancelled -> Get Appointments Config -> Generate Days

// 1. Primero, crear un nuevo nodo "Delete For Reschedule" que elimine la cita
// 2. Crear "Send Reschedule Cancelled" que envíe mensaje y pase phoneNumber
// 3. Conectar a Get Appointments Config -> Generate Days

// Buscar posición de nodos existentes para ubicar los nuevos
const getReschedule = j.nodes.find(n => n.name === 'Get Reschedule Event');
const getAppConfig = j.nodes.find(n => n.name === 'Get Appointments Config');

console.log('Get Reschedule Event position:', getReschedule?.position);
console.log('Get Appointments Config position:', getAppConfig?.position);

// Crear nodo "Parse Reschedule Event" similar a Parse Event To Delete pero más simple
const parseRescheduleEventNode = {
    parameters: {
        jsCode: `// Parsear datos del evento a reprogramar (de Redis)
const items = $input.all();
const results = [];

// Obtener phoneNumber y phoneNumberId de Process Interactive
const phoneNumber = $('Process Interactive').first().json.phoneNumber;
const phoneNumberId = $('Process Interactive').first().json.phoneNumberId;

for (const item of items) {
  try {
    let eventData;
    const rawData = item.json.eventData || item.json.data;

    if (typeof rawData === 'string') {
      eventData = JSON.parse(rawData);
    } else if (rawData) {
      eventData = rawData;
    } else {
      eventData = item.json;
    }

    results.push({
      json: {
        appointmentId: eventData.appointmentId || null,
        eventId: eventData.eventId || null,
        summary: eventData.summary || 'Cita',
        email: eventData.email || '',
        clientName: eventData.clientName || '',
        hasAppointmentId: !!eventData.appointmentId,
        phoneNumber: phoneNumber,
        phoneNumberId: phoneNumberId
      }
    });
  } catch (e) {
    results.push({
      json: {
        appointmentId: null,
        hasAppointmentId: false,
        phoneNumber: phoneNumber,
        phoneNumberId: phoneNumberId
      }
    });
  }
}

return results;`
    },
    type: "n8n-nodes-base.code",
    typeVersion: 2,
    position: [119200, 42300],
    id: "parse-reschedule-event-new",
    name: "Parse Reschedule Event"
};

// Crear nodo "Delete For Reschedule" 
const deleteForRescheduleNode = {
    parameters: {
        method: "DELETE",
        url: "=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/{{ $json.appointmentId }}",
        authentication: "none",
        options: {}
    },
    type: "n8n-nodes-base.httpRequest",
    typeVersion: 4.2,
    position: [119400, 42300],
    id: "delete-for-reschedule-new",
    name: "Delete For Reschedule",
    onError: "continueRegularOutput"
};

// Crear nodo "Send Reschedule Cancelled" que envía mensaje y pasa datos al siguiente nodo
const sendRescheduleCancelledNode = {
    parameters: {
        method: "POST",
        url: "=https://graph.facebook.com/v22.0/{{ $('Parse Reschedule Event').first().json.phoneNumberId }}/messages",
        authentication: "predefinedCredentialType",
        nodeCredentialType: "whatsAppApi",
        sendBody: true,
        specifyBody: "json",
        jsonBody: `={
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "{{ $('Parse Reschedule Event').first().json.phoneNumber }}",
  "type": "text",
  "text": {
    "body": "✅ Tu cita anterior ha sido cancelada.\\n\\n📅 Ahora vamos a agendar tu nueva cita:"
  }
}`,
        options: {}
    },
    type: "n8n-nodes-base.httpRequest",
    typeVersion: 4.1,
    position: [119600, 42300],
    id: "send-reschedule-cancelled-new",
    name: "Send Reschedule Cancelled",
    credentials: {
        whatsAppApi: {
            id: "SH8OXr93p852Ll6m",
            name: "WhatsApp Tech"
        }
    }
};

// Crear nodo Set para pasar phoneNumber al flujo de booking
const setRescheduleDataNode = {
    parameters: {
        assignments: {
            assignments: [
                {
                    id: "set-phone-1",
                    name: "phoneNumber",
                    value: "={{ $('Parse Reschedule Event').first().json.phoneNumber }}",
                    type: "string"
                },
                {
                    id: "set-phone-2", 
                    name: "phoneNumberId",
                    value: "={{ $('Parse Reschedule Event').first().json.phoneNumberId }}",
                    type: "string"
                },
                {
                    id: "set-phone-3",
                    name: "contactName",
                    value: "={{ $('Parse Reschedule Event').first().json.clientName }}",
                    type: "string"
                }
            ]
        },
        options: {}
    },
    type: "n8n-nodes-base.set",
    typeVersion: 3.4,
    position: [119800, 42300],
    id: "set-reschedule-data-new",
    name: "Set Reschedule Data"
};

// Agregar los nuevos nodos
j.nodes.push(parseRescheduleEventNode);
j.nodes.push(deleteForRescheduleNode);
j.nodes.push(sendRescheduleCancelledNode);
j.nodes.push(setRescheduleDataNode);

console.log('✅ Nuevos nodos agregados');

// Modificar conexiones:
// 1. Get Reschedule Event -> Parse Reschedule Event (en lugar de Parse Event To Delete)
j.connections['Get Reschedule Event'] = {
    main: [[{ node: 'Parse Reschedule Event', type: 'main', index: 0 }]]
};

// 2. Parse Reschedule Event -> Delete For Reschedule
j.connections['Parse Reschedule Event'] = {
    main: [[{ node: 'Delete For Reschedule', type: 'main', index: 0 }]]
};

// 3. Delete For Reschedule -> Send Reschedule Cancelled
j.connections['Delete For Reschedule'] = {
    main: [[{ node: 'Send Reschedule Cancelled', type: 'main', index: 0 }]]
};

// 4. Send Reschedule Cancelled -> Set Reschedule Data
j.connections['Send Reschedule Cancelled'] = {
    main: [[{ node: 'Set Reschedule Data', type: 'main', index: 0 }]]
};

// 5. Set Reschedule Data -> Get Appointments Config
j.connections['Set Reschedule Data'] = {
    main: [[{ node: 'Get Appointments Config', type: 'main', index: 0 }]]
};

console.log('✅ Conexiones actualizadas');

// También necesito limpiar el estado de reschedule después
// Agregar Clear Reschedule State después de Set Reschedule Data
// Pero por ahora lo mantenemos simple

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}

console.log('\n=== NUEVO FLUJO DE REPROGRAMACIÓN ===');
console.log('btn_confirmar_reprogramar ->');
console.log('  Get Reschedule Event ->');
console.log('  Parse Reschedule Event ->');
console.log('  Delete For Reschedule ->');
console.log('  Send Reschedule Cancelled ->');
console.log('  Set Reschedule Data ->');
console.log('  Get Appointments Config ->');
console.log('  Generate Days ->');
console.log('  Send Calendar Buttons ->');
console.log('  ... flujo normal de booking');
