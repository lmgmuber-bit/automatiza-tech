const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// 1. Actualizar mensaje que pide datos (Ask Reschedule Data)
const askRescheduleNode = j.nodes.find(n => n.name === 'Ask Reschedule Data');
if (askRescheduleNode) {
    askRescheduleNode.parameters.jsonBody = `={{ JSON.stringify({ messaging_product: 'whatsapp', recipient_type: 'individual', to: $json.phoneNumber, type: 'text', text: { preview_url: false, body: 'Para reprogramar tu cita, necesito verificar tus datos.\\n\\n📝 Por favor envía en un solo mensaje:\\n• Tu nombre completo\\n• Tu correo electrónico\\n• Fecha de tu cita (DD/MM/AAAA)\\n• Hora de tu cita (HH:MM)\\n\\nEjemplo:\\nJuan Pérez\\njuan@email.com\\n30/12/2025\\n10:00' } }) }}`;
    console.log('✅ Ask Reschedule Data actualizado');
} else {
    console.log('⚠️ No se encontró Ask Reschedule Data');
}

// 2. Actualizar Parse Reschedule Data para parsear 4 líneas
const parseNode = j.nodes.find(n => n.name === 'Parse Reschedule Data');
if (parseNode) {
    parseNode.parameters.jsCode = `const input = $input.first().json;
const textContent = $('Process Text').first().json.textContent;
const phoneNumber = $('Process Text').first().json.phoneNumber;
const phoneNumberId = $('Process Text').first().json.phoneNumberId;

// Verificar si está en estado de espera de datos de reprogramación
if (input.rescheduleState !== 'awaiting_reschedule_data') {
  return {
    json: {
      status: 'NOT_WAITING',
      phoneNumber: phoneNumber,
      phoneNumberId: phoneNumberId
    }
  };
}

// IMPORTANTE: Detectar si el mensaje es una SOLICITUD de reprogramación (no datos)
const lowerText = textContent.toLowerCase();
const isRescheduleRequest = (
  lowerText.includes('reprogramar') ||
  lowerText.includes('reagendar') ||
  lowerText.includes('cambiar') ||
  lowerText.includes('mover') ||
  (lowerText.includes('quiero') && !lowerText.includes('@')) ||
  (lowerText.includes('necesito') && !lowerText.includes('@'))
);

// Si parece una solicitud y NO tiene formato de datos (mínimo 4 líneas con email)
const lines = textContent.trim().split('\\n').filter(l => l.trim());
const hasEmailFormat = lines.some(l => /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(l.trim()));

if (isRescheduleRequest && !hasEmailFormat) {
  return {
    json: {
      status: 'NOT_WAITING',
      phoneNumber: phoneNumber,
      phoneNumberId: phoneNumberId
    }
  };
}

// Validar formato - ahora necesitamos 4 líneas: nombre, email, fecha, hora
if (lines.length < 4) {
  return {
    json: {
      status: 'INVALID_FORMAT',
      message: 'Se requieren 4 líneas: nombre, email, fecha y hora',
      phoneNumber: phoneNumber,
      phoneNumberId: phoneNumberId
    }
  };
}

const name = lines[0].trim();
const email = lines[1].trim();
const dateStr = lines[2].trim();
const timeStr = lines[3].trim();

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

// Validar fecha (DD/MM/AAAA o DD-MM-AAAA)
const dateRegex = /^(\\d{1,2})[\\/\\-](\\d{1,2})[\\/\\-](\\d{4})$/;
const dateMatch = dateStr.match(dateRegex);
if (!dateMatch) {
  return {
    json: {
      status: 'INVALID_DATE',
      message: 'Formato de fecha inválido. Usa DD/MM/AAAA',
      phoneNumber: phoneNumber,
      phoneNumberId: phoneNumberId
    }
  };
}

// Convertir a formato YYYY-MM-DD
const day = dateMatch[1].padStart(2, '0');
const month = dateMatch[2].padStart(2, '0');
const year = dateMatch[3];
const scheduledDate = year + '-' + month + '-' + day;

// Validar hora (HH:MM)
const timeRegex = /^(\\d{1,2}):(\\d{2})$/;
const timeMatch = timeStr.match(timeRegex);
if (!timeMatch) {
  return {
    json: {
      status: 'INVALID_TIME',
      message: 'Formato de hora inválido. Usa HH:MM',
      phoneNumber: phoneNumber,
      phoneNumberId: phoneNumberId
    }
  };
}

const hour = timeMatch[1].padStart(2, '0');
const minute = timeMatch[2];
const scheduledTime = hour + ':' + minute + ':00';

return {
  json: {
    status: 'SEARCH_APPOINTMENT',
    name: name,
    email: email,
    scheduledDate: scheduledDate,
    scheduledTime: scheduledTime,
    phoneNumber: phoneNumber,
    phoneNumberId: phoneNumberId
  }
};`;
    console.log('✅ Parse Reschedule Data actualizado para 4 campos');
}

// 3. Actualizar Search User Appointment para buscar con todos los criterios
const searchNode = j.nodes.find(n => n.name === 'Search User Appointment');
if (searchNode) {
    searchNode.parameters.url = "=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/search?email={{ encodeURIComponent($json.email) }}&name={{ encodeURIComponent($json.name) }}&scheduled_date={{ $json.scheduledDate }}&scheduled_time={{ $json.scheduledTime }}";
    console.log('✅ Search User Appointment actualizado con todos los criterios');
}

// 4. Actualizar mensaje de formato inválido
const invalidFormatNode = j.nodes.find(n => n.name === 'Send Reschedule Invalid Format' || n.name === 'Send Invalid Format');
if (invalidFormatNode) {
    invalidFormatNode.parameters.jsonBody = `={{ JSON.stringify({ messaging_product: 'whatsapp', recipient_type: 'individual', to: $json.phoneNumber, type: 'text', text: { preview_url: false, body: '❌ Formato inválido.\\n\\nEnvía tus datos en 4 líneas:\\n1. Nombre completo\\n2. Correo electrónico\\n3. Fecha (DD/MM/AAAA)\\n4. Hora (HH:MM)\\n\\nEjemplo:\\nJuan Pérez\\njuan@email.com\\n30/12/2025\\n10:00' } }) }}`;
    console.log('✅ Mensaje de formato inválido actualizado');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
