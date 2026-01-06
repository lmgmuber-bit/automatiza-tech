const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Opción 1: Corregir Check Appointment Found para buscar $json.found === true
// Opción 2: Corregir Process Search Results para retornar status: 'FOUND' o 'NOT_FOUND'

// Voy con Opción 2 para mantener consistencia con otros nodos
const processSearchIdx = j.nodes.findIndex(n => n.name === 'Process Search Results');
if (processSearchIdx >= 0) {
    j.nodes[processSearchIdx].parameters.jsCode = `// Procesar respuesta de WordPress API para reprogramación
const response = $input.first().json;
const inputData = $('Parse Reschedule Data').first().json;

// WordPress API devuelve { success: true, data: [...], count: N }
const appointments = response.data || response || [];

if (!appointments || appointments.length === 0) {
  return {
    json: {
      status: 'NOT_FOUND',
      found: false,
      appointmentId: null,
      eventId: null,
      message: 'No se encontraron citas futuras',
      phoneNumber: inputData.phoneNumber,
      phoneNumberId: inputData.phoneNumberId
    }
  };
}

// Tomar la primera cita futura
const appointment = appointments[0];

return {
  json: {
    status: 'FOUND',
    found: true,
    appointmentId: appointment.id,
    eventId: appointment.event_id || null,
    summary: appointment.service || 'Cita agendada',
    start: appointment.scheduled_date + 'T' + appointment.scheduled_time,
    scheduledDate: appointment.scheduled_date,
    scheduledTime: appointment.scheduled_time,
    email: appointment.email,
    clientName: appointment.name,
    meetLink: appointment.meet_link,
    phoneNumber: inputData.phoneNumber,
    phoneNumberId: inputData.phoneNumberId
  }
};`;
    console.log('✅ Process Search Results corregido - ahora retorna status: FOUND/NOT_FOUND');
}

// También corregir Show Confirmation Buttons para usar los datos correctos
const showConfIdx = j.nodes.findIndex(n => n.name === 'Show Confirmation Buttons');
if (showConfIdx >= 0) {
    console.log('\n=== Show Confirmation Buttons ===');
    console.log('URL:', j.nodes[showConfIdx].parameters?.url);
    console.log('jsonBody preview:', (j.nodes[showConfIdx].parameters?.jsonBody || '').substring(0, 200));
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
