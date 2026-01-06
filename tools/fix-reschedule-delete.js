const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Actualizar Parse Reschedule Event para hacer mejor debugging y manejo
const node = j.nodes.find(n => n.name === 'Parse Reschedule Event');

const newCode = `// Parsear datos del evento a reprogramar (de Redis)
const items = $input.all();
const results = [];

// Obtener phoneNumber y phoneNumberId de Process Interactive
const phoneNumber = $('Process Interactive').first().json.phoneNumber;
const phoneNumberId = $('Process Interactive').first().json.phoneNumberId;

for (const item of items) {
  try {
    let eventData;
    
    // Redis GET devuelve el valor en eventData (como string JSON)
    const rawData = item.json.eventData || item.json.data || item.json.value;
    
    // Log para debug
    console.log('Raw data type:', typeof rawData);
    console.log('Raw data:', rawData);
    
    // Intentar parsear si es string JSON
    if (typeof rawData === 'string') {
      try {
        eventData = JSON.parse(rawData);
      } catch (parseError) {
        // Si falla el parse, usar como está
        eventData = { raw: rawData };
      }
    } else if (rawData && typeof rawData === 'object') {
      eventData = rawData;
    } else {
      // Fallback: buscar appointmentId directamente en json
      eventData = item.json;
    }
    
    // Extraer appointmentId (puede estar en diferentes lugares)
    const appointmentId = eventData.appointmentId || 
                          item.json.appointmentId || 
                          null;
    
    results.push({
      json: {
        appointmentId: appointmentId,
        eventId: eventData.eventId || null,
        summary: eventData.summary || 'Cita',
        email: eventData.email || '',
        clientName: eventData.clientName || '',
        hasAppointmentId: !!appointmentId,
        phoneNumber: phoneNumber,
        phoneNumberId: phoneNumberId,
        _debug_rawType: typeof rawData,
        _debug_parsed: eventData
      }
    });
  } catch (e) {
    results.push({
      json: {
        appointmentId: null,
        hasAppointmentId: false,
        phoneNumber: phoneNumber,
        phoneNumberId: phoneNumberId,
        _error: e.message
      }
    });
  }
}

return results;`;

node.parameters.jsCode = newCode;
console.log('✅ Parse Reschedule Event actualizado con mejor manejo de datos');

// También actualizar Delete For Reschedule para usar referencia explícita
const deleteNode = j.nodes.find(n => n.name === 'Delete For Reschedule');
deleteNode.parameters.url = "=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/{{ $('Parse Reschedule Event').first().json.appointmentId }}";
console.log('✅ Delete For Reschedule actualizado para usar referencia explícita');

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
