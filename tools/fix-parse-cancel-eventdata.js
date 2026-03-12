const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Parse Cancel Event - buscar en eventData (no data)
const parseIdx = j.nodes.findIndex(n => n.name === 'Parse Cancel Event');
if (parseIdx >= 0) {
    const newCode = `// Parsear datos del evento a cancelar (de Redis)
const items = $input.all();
const results = [];

// Obtener phoneNumber y phoneNumberId de Process Interactive
const phoneNumber = $('Process Interactive').first().json.phoneNumber;
const phoneNumberId = $('Process Interactive').first().json.phoneNumberId;

for (const item of items) {
  try {
    let eventData;

    // Redis devuelve en eventData (no data)
    const rawData = item.json.eventData || item.json.data;
    
    // Intentar parsear si es string JSON
    if (typeof rawData === 'string') {
      eventData = JSON.parse(rawData);
    } else if (rawData) {
      eventData = rawData;
    } else {
      eventData = item.json;
    }

    // Determinar status basado en si hay appointmentId
    const hasAppointmentId = !!eventData.appointmentId;
    const status = hasAppointmentId ? 'DELETE' : 'NO_APPOINTMENT';

    results.push({
      json: {
        status: status,
        appointmentId: eventData.appointmentId || null,
        eventId: eventData.eventId || null,
        summary: eventData.summary || eventData.eventSummary || 'Cita',
        email: eventData.email || '',
        clientName: eventData.clientName || '',
        hasAppointmentId: hasAppointmentId,
        phoneNumber: phoneNumber,
        phoneNumberId: phoneNumberId
      }
    });
  } catch (e) {
    results.push({
      json: {
        status: 'ERROR',
        appointmentId: null,
        eventId: null,
        error: e.message,
        hasAppointmentId: false,
        phoneNumber: phoneNumber,
        phoneNumberId: phoneNumberId
      }
    });
  }
}

return results;`;
    j.nodes[parseIdx].parameters.jsCode = newCode;
    console.log('✅ Parse Cancel Event corregido - ahora busca en eventData');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
