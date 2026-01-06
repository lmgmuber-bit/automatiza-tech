/**
 * Script para actualizar el flujo de reprogramación para usar WordPress API
 * Y asegurar que Google Calendar no bloquee el flujo si falla
 */

const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, '..', 'N8N', 'PROD', 'WhatsApp_Tech_Principal.json');

// Leer el archivo
let content = fs.readFileSync(filePath, 'utf8');

// Remover BOM si existe
if (content.charCodeAt(0) === 0xFEFF) {
    content = content.slice(1);
}

const workflow = JSON.parse(content);

console.log('=== Actualizando flujo de REPROGRAMACIÓN para usar WordPress API ===\n');

// 1. Cambiar "Search User Appointment" de Google Calendar a HTTP Request (WordPress API)
const searchUserAppointment = workflow.nodes.find(n => n.name === 'Search User Appointment');
if (searchUserAppointment) {
    console.log('1. Modificando "Search User Appointment"...');
    console.log('   Antes:', searchUserAppointment.type);
    
    // Guardar posición y conexiones
    const position = searchUserAppointment.position;
    const id = searchUserAppointment.id;
    
    // Cambiar a HTTP Request
    searchUserAppointment.type = 'n8n-nodes-base.httpRequest';
    searchUserAppointment.typeVersion = 4.2;
    searchUserAppointment.parameters = {
        method: 'GET',
        url: '=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/search?email={{ encodeURIComponent($json.email) }}&future_only=true',
        authentication: 'none',
        options: {
            response: {
                response: {
                    fullResponse: false
                }
            }
        }
    };
    // Remover credentials de Google
    delete searchUserAppointment.credentials;
    
    console.log('   Después:', searchUserAppointment.type);
    console.log('   URL: WordPress API search endpoint');
}

// 2. Modificar "Process Search Results" para manejar respuesta de WordPress API
const processSearchResults = workflow.nodes.find(n => n.name === 'Process Search Results');
if (processSearchResults) {
    console.log('\n2. Modificando "Process Search Results"...');
    
    processSearchResults.parameters.jsCode = `// Procesar respuesta de WordPress API para reprogramación
const items = $input.all();
const results = [];

for (const item of items) {
  // WordPress API devuelve array de appointments
  const appointments = item.json;
  
  if (Array.isArray(appointments) && appointments.length > 0) {
    // Tomar la primera cita futura
    const appointment = appointments[0];
    
    results.push({
      json: {
        found: true,
        appointmentId: appointment.id,
        eventId: appointment.google_event_id || null,
        summary: appointment.service_name || 'Cita',
        start: appointment.appointment_date,
        email: appointment.client_email,
        clientName: appointment.client_name
      }
    });
  } else {
    results.push({
      json: {
        found: false,
        appointmentId: null,
        eventId: null,
        message: 'No se encontraron citas futuras'
      }
    });
  }
}

return results;`;
    
    console.log('   Código actualizado para manejar respuesta de WordPress');
}

// 3. Modificar "Save Reschedule Event" para guardar datos de WordPress
const saveRescheduleEvent = workflow.nodes.find(n => n.name === 'Save Reschedule Event');
if (saveRescheduleEvent) {
    console.log('\n3. Modificando "Save Reschedule Event"...');
    
    // Buscar o crear este nodo si no existe
    if (saveRescheduleEvent.type === 'n8n-nodes-base.redis') {
        saveRescheduleEvent.parameters.value = `={{ JSON.stringify({
  appointmentId: $json.appointmentId,
  eventId: $json.eventId,
  summary: $json.summary,
  start: $json.start,
  email: $json.email,
  clientName: $json.clientName
}) }}`;
        console.log('   Value actualizado para incluir appointmentId');
    }
}

// 4. Modificar "Parse Event To Delete" para extraer appointmentId
const parseEventToDelete = workflow.nodes.find(n => n.name === 'Parse Event To Delete');
if (parseEventToDelete) {
    console.log('\n4. Modificando "Parse Event To Delete"...');
    
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
        hasAppointmentId: !!eventData.appointmentId,
        hasEventId: !!eventData.eventId
      }
    });
  } catch (e) {
    results.push({
      json: {
        appointmentId: null,
        eventId: null,
        error: e.message,
        hasAppointmentId: false,
        hasEventId: false
      }
    });
  }
}

return results;`;
    
    console.log('   Código actualizado para extraer appointmentId');
}

// 5. Cambiar "Delete Old Appointment" de Google Calendar a HTTP Request (WordPress API)
const deleteOldAppointment = workflow.nodes.find(n => n.name === 'Delete Old Appointment');
if (deleteOldAppointment) {
    console.log('\n5. Modificando "Delete Old Appointment"...');
    console.log('   Antes:', deleteOldAppointment.type);
    
    // Guardar posición
    const position = deleteOldAppointment.position;
    const id = deleteOldAppointment.id;
    
    // Cambiar a HTTP Request para WordPress API
    deleteOldAppointment.type = 'n8n-nodes-base.httpRequest';
    deleteOldAppointment.typeVersion = 4.2;
    deleteOldAppointment.parameters = {
        method: 'DELETE',
        url: '=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/{{ $json.appointmentId }}',
        authentication: 'none',
        options: {
            response: {
                response: {
                    fullResponse: false
                }
            }
        }
    };
    // Remover credentials de Google
    delete deleteOldAppointment.credentials;
    
    // Configurar para continuar si hay error (On Error: Continue)
    deleteOldAppointment.onError = 'continueRegularOutput';
    
    console.log('   Después:', deleteOldAppointment.type);
    console.log('   URL: WordPress API delete endpoint');
    console.log('   onError: continueRegularOutput (no bloquea si falla)');
}

// 6. Asegurar que Delete Cancelled Appointment también tenga onError: continue
const deleteCancelledAppointment = workflow.nodes.find(n => n.name === 'Delete Cancelled Appointment');
if (deleteCancelledAppointment) {
    console.log('\n6. Configurando "Delete Cancelled Appointment" con onError...');
    deleteCancelledAppointment.onError = 'continueRegularOutput';
    console.log('   onError: continueRegularOutput');
}

// 7. Buscar si hay nodo para eliminar de Google Calendar en reprogramación y configurarlo
const googleDeleteNodes = workflow.nodes.filter(n => 
    n.type === 'n8n-nodes-base.googleCalendar' && 
    n.parameters && 
    n.parameters.operation === 'delete'
);

if (googleDeleteNodes.length > 0) {
    console.log('\n7. Configurando nodos de Google Calendar Delete para no bloquear...');
    googleDeleteNodes.forEach(node => {
        node.onError = 'continueRegularOutput';
        console.log(`   ${node.name}: onError = continueRegularOutput`);
    });
}

// Guardar el archivo
fs.writeFileSync(filePath, JSON.stringify(workflow, null, 2), 'utf8');

console.log('\n=== Cambios aplicados exitosamente ===');
console.log('\nResumen de cambios:');
console.log('- Search User Appointment: Google Calendar → WordPress API');
console.log('- Process Search Results: Actualizado para respuesta WordPress');
console.log('- Save Reschedule Event: Incluye appointmentId');
console.log('- Parse Event To Delete: Extrae appointmentId');
console.log('- Delete Old Appointment: Google Calendar → WordPress API');
console.log('- Todos los DELETE configurados con onError: continue');
console.log('\n¡Reimporta el workflow en N8N!');
