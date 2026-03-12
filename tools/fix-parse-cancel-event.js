const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// 1. Corregir Parse Cancel Event - agregar phoneNumber y phoneNumberId
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
        hasAppointmentId: !!eventData.appointmentId,
        phoneNumber: phoneNumber,
        phoneNumberId: phoneNumberId
      }
    });
  } catch (e) {
    results.push({
      json: {
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
    console.log('✅ Parse Cancel Event corregido - ahora incluye phoneNumber y phoneNumberId');
}

// 2. Corregir Parse Cancel Data - remover filtro de actionKeywords que causa doble mensaje
const parseCancelDataIdx = j.nodes.findIndex(n => n.name === 'Parse Cancel Data');
if (parseCancelDataIdx >= 0) {
    const newCancelDataCode = `const input = $input.first().json;
const textContent = $('Process Text').first().json.textContent;
const phoneNumber = $('Process Text').first().json.phoneNumber;
const phoneNumberId = $('Process Text').first().json.phoneNumberId;

// Verificar si está en estado de espera de datos de cancelación
if (input.cancelState !== 'awaiting_cancel_data') {
  return {
    json: {
      status: 'NOT_WAITING',
      phoneNumber: phoneNumber,
      phoneNumberId: phoneNumberId
    }
  };
}

// Parsear datos del mensaje
const lines = textContent.trim().split('\\n').filter(l => l.trim());
if (lines.length < 2) {
  return {
    json: {
      status: 'INVALID_FORMAT',
      phoneNumber: phoneNumber,
      phoneNumberId: phoneNumberId
    }
  };
}

const name = lines[0].trim();
const email = lines[1].trim();

// Validar email
const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
if (!emailRegex.test(email)) {
  return {
    json: {
      status: 'INVALID_EMAIL',
      phoneNumber: phoneNumber,
      phoneNumberId: phoneNumberId
    }
  };
}

// Datos válidos - buscar cita
return {
  json: {
    status: 'SEARCH_APPOINTMENT',
    name: name,
    email: email,
    phoneNumber: phoneNumber,
    phoneNumberId: phoneNumberId
  }
};`;
    j.nodes[parseCancelDataIdx].parameters.jsCode = newCancelDataCode;
    console.log('✅ Parse Cancel Data corregido - removido filtro de actionKeywords que causaba doble mensaje');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado correctamente');

// Validar JSON
try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error en JSON:', e.message);
}
