/**
 * Script para corregir los nombres de campos en Process Results nodes
 */

const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, '..', 'N8N', 'PROD', 'WhatsApp_Tech_Principal.json');

// Leer el archivo
let content = fs.readFileSync(filePath, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) {
    content = content.slice(1);
}

const workflow = JSON.parse(content);

console.log('=== Corrigiendo nombres de campos del API ===\n');

// 1. Actualizar Process Search Results (reprogramación)
const processSearchResults = workflow.nodes.find(x => x.name === 'Process Search Results');
if (processSearchResults) {
    processSearchResults.parameters.jsCode = `// Procesar respuesta de WordPress API para reprogramación
const response = $input.first().json;

// WordPress API devuelve { success: true, data: [...], count: N }
const appointments = response.data || response || [];

if (!appointments || appointments.length === 0) {
  return {
    json: {
      found: false,
      appointmentId: null,
      eventId: null,
      message: 'No se encontraron citas futuras'
    }
  };
}

// Tomar la primera cita futura
const appointment = appointments[0];

return {
  json: {
    found: true,
    appointmentId: appointment.id,
    eventId: appointment.event_id || null,
    summary: appointment.service || 'Cita',
    start: appointment.scheduled_date + 'T' + appointment.scheduled_time,
    email: appointment.email,
    clientName: appointment.name
  }
};`;
    console.log('1. Process Search Results actualizado');
}

// 2. Actualizar Process Cancel Results (cancelación)
const processCancelResults = workflow.nodes.find(x => x.name === 'Process Cancel Results');
if (processCancelResults) {
    processCancelResults.parameters.jsCode = `// Procesar respuesta de WordPress API para cancelación
const response = $input.first().json;
const userData = $('Parse Cancel Data').first().json;

// WordPress API devuelve { success: true, data: [...], count: N }
const appointments = response.data || response || [];

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
    eventId: appointment.event_id || null,
    eventSummary: appointment.service || 'Cita agendada',
    eventStart: appointment.scheduled_date + 'T' + appointment.scheduled_time,
    email: appointment.email,
    clientName: appointment.name,
    phoneNumber: userData.phoneNumber,
    phoneNumberId: userData.phoneNumberId
  }
};`;
    console.log('2. Process Cancel Results actualizado');
}

// 3. Actualizar Save Cancel Event para usar los nuevos campos
const saveCancelEvent = workflow.nodes.find(x => x.name === 'Save Cancel Event');
if (saveCancelEvent) {
    saveCancelEvent.parameters.value = '={{ JSON.stringify({ appointmentId: $json.appointmentId, eventId: $json.eventId, email: $json.email, clientName: $json.clientName, summary: $json.eventSummary }) }}';
    console.log('3. Save Cancel Event actualizado');
}

// 4. Actualizar Parse Cancel Event para extraer appointmentId
const parseCancelEvent = workflow.nodes.find(x => x.name === 'Parse Cancel Event');
if (parseCancelEvent) {
    parseCancelEvent.parameters.jsCode = `// Parsear datos del evento a cancelar (de Redis)
const items = $input.all();
const results = [];

for (const item of items) {
  try {
    let eventData;
    
    // Intentar parsear si es string JSON
    if (typeof item.json.data === 'string') {
      eventData = JSON.parse(item.json.data);
    } else if (item.json.data) {
      eventData = item.json.data;
    } else {
      eventData = item.json;
    }
    
    results.push({
      json: {
        appointmentId: eventData.appointmentId || null,
        eventId: eventData.eventId || null,
        summary: eventData.summary || eventData.eventSummary || 'Cita',
        email: eventData.email || '',
        clientName: eventData.clientName || '',
        hasAppointmentId: !!eventData.appointmentId
      }
    });
  } catch (e) {
    results.push({
      json: {
        appointmentId: null,
        eventId: null,
        error: e.message,
        hasAppointmentId: false
      }
    });
  }
}

return results;`;
    console.log('4. Parse Cancel Event actualizado');
}

// 5. Actualizar Parse Event To Delete (reprogramación) para extraer appointmentId
const parseEventToDelete = workflow.nodes.find(x => x.name === 'Parse Event To Delete');
if (parseEventToDelete) {
    parseEventToDelete.parameters.jsCode = `// Parsear datos del evento a eliminar (de Redis)
const items = $input.all();
const results = [];

for (const item of items) {
  try {
    let eventData;
    
    // Intentar parsear si es string JSON
    if (typeof item.json.data === 'string') {
      eventData = JSON.parse(item.json.data);
    } else if (item.json.data) {
      eventData = item.json.data;
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
        hasAppointmentId: !!eventData.appointmentId
      }
    });
  } catch (e) {
    results.push({
      json: {
        appointmentId: null,
        eventId: null,
        error: e.message,
        hasAppointmentId: false
      }
    });
  }
}

return results;`;
    console.log('5. Parse Event To Delete actualizado');
}

// Guardar
fs.writeFileSync(filePath, JSON.stringify(workflow, null, 2), 'utf8');

console.log('\n=== Cambios guardados ===');
console.log('\nCampos del API WordPress usados:');
console.log('- appointment.id → appointmentId');
console.log('- appointment.event_id → eventId (Google Calendar)');
console.log('- appointment.email → email');
console.log('- appointment.name → clientName');
console.log('- appointment.service → summary');
console.log('- appointment.scheduled_date/time → start');
