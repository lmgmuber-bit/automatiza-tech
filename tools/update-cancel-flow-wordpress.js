const fs = require('fs');

const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';
const j = JSON.parse(fs.readFileSync(filePath, 'utf8'));

// 1. Modificar "Search Cancel Appointment" para usar WordPress API
const searchNode = j.nodes.find(n => n.name === 'Search Cancel Appointment');
if (searchNode) {
    console.log('Modificando Search Cancel Appointment...');
    searchNode.type = 'n8n-nodes-base.httpRequest';
    searchNode.typeVersion = 4.1;
    searchNode.parameters = {
        method: 'GET',
        url: '=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/search?email={{ encodeURIComponent($json.email) }}&future_only=true',
        options: {}
    };
    // Remover credentials de Google Calendar
    delete searchNode.credentials;
}

// 2. Modificar "Process Cancel Results" para trabajar con la respuesta de WordPress
const processNode = j.nodes.find(n => n.name === 'Process Cancel Results');
if (processNode) {
    console.log('Modificando Process Cancel Results...');
    processNode.parameters.jsCode = `const response = $input.first().json;
const userData = $('Parse Cancel Data').first().json;

// WordPress API devuelve { success: true, data: [...], count: N }
const appointments = response.data || [];

if (!appointments || appointments.length === 0) {
  return {
    json: {
      status: 'NOT_FOUND',
      phoneNumber: userData.phoneNumber,
      phoneNumberId: userData.phoneNumberId
    }
  };
}

// Tomar la primera cita encontrada
const appointment = appointments[0];

return {
  json: {
    status: 'FOUND',
    appointmentId: appointment.id,
    eventId: appointment.event_id,
    eventSummary: 'Cita agendada',
    eventStart: appointment.scheduled_date + 'T' + appointment.scheduled_time,
    attendeeEmail: appointment.email,
    attendeeName: appointment.name,
    phoneNumber: userData.phoneNumber,
    phoneNumberId: userData.phoneNumberId
  }
};`;
}

// 3. Modificar "Save Cancel Event" para guardar appointmentId
const saveNode = j.nodes.find(n => n.name === 'Save Cancel Event');
if (saveNode) {
    console.log('Modificando Save Cancel Event...');
    saveNode.parameters.value = '={{ JSON.stringify({ appointmentId: $json.appointmentId, eventId: $json.eventId, email: $json.attendeeEmail, name: $json.attendeeName }) }}';
}

// 4. Modificar "Parse Cancel Event" para usar appointmentId
const parseNode = j.nodes.find(n => n.name === 'Parse Cancel Event');
if (parseNode) {
    console.log('Modificando Parse Cancel Event...');
    parseNode.parameters.jsCode = `const input = $input.first().json;
const buttonData = $('Process Interactive').first().json;

if (!input.eventData) {
  return {
    json: {
      status: 'ERROR',
      phoneNumber: buttonData.phoneNumber,
      phoneNumberId: buttonData.phoneNumberId
    }
  };
}

const eventData = JSON.parse(input.eventData);

return {
  json: {
    status: 'DELETE',
    appointmentId: eventData.appointmentId,
    eventId: eventData.eventId,
    phoneNumber: buttonData.phoneNumber,
    phoneNumberId: buttonData.phoneNumberId
  }
};`;
}

// 5. Modificar "Delete Cancelled Appointment" para usar WordPress API
const deleteNode = j.nodes.find(n => n.name === 'Delete Cancelled Appointment');
if (deleteNode) {
    console.log('Modificando Delete Cancelled Appointment...');
    deleteNode.type = 'n8n-nodes-base.httpRequest';
    deleteNode.typeVersion = 4.1;
    deleteNode.parameters = {
        method: 'DELETE',
        url: '=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/{{ $json.appointmentId }}',
        options: {
            response: {
                response: {
                    fullResponse: false
                }
            }
        }
    };
    // Remover credentials de Google Calendar
    delete deleteNode.credentials;
}

// Guardar
fs.writeFileSync(filePath, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Flujo de cancelación actualizado para usar WordPress API');
console.log('Total nodos:', j.nodes.length);
