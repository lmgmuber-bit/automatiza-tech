const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// 1. Actualizar Ask Cancel Data
const askCancelNode = j.nodes.find(n => n.name === 'Ask Cancel Data');
if (askCancelNode) {
    askCancelNode.parameters.jsonBody = `={{ JSON.stringify({ messaging_product: 'whatsapp', recipient_type: 'individual', to: $json.phoneNumber, type: 'text', text: { preview_url: false, body: 'Para cancelar tu cita, necesito verificar tus datos.\\n\\n📝 Por favor envía en un solo mensaje:\\n• Tu nombre completo\\n• Tu correo electrónico\\n• Fecha de tu cita (DD/MM/AAAA)\\n• Hora de tu cita (HH:MM)\\n\\nEjemplo:\\nJuan Pérez\\njuan@email.com\\n30/12/2025\\n10:00' } }) }}`;
    console.log('✅ Ask Cancel Data actualizado');
}

// 2. Actualizar Parse Cancel Data
const parseCancelNode = j.nodes.find(n => n.name === 'Parse Cancel Data');
if (parseCancelNode) {
    parseCancelNode.parameters.jsCode = `const input = $input.first().json;
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

// IMPORTANTE: Detectar si el mensaje es una SOLICITUD de cancelación (no datos)
const lowerText = textContent.toLowerCase();
const isCancelRequest = (
  lowerText.includes('cancelar') ||
  lowerText.includes('anular') ||
  lowerText.includes('eliminar') ||
  (lowerText.includes('quiero') && !lowerText.includes('@')) ||
  (lowerText.includes('necesito') && !lowerText.includes('@'))
);

// Si parece una solicitud y NO tiene formato de datos
const lines = textContent.trim().split('\\n').filter(l => l.trim());
const hasEmailFormat = lines.some(l => /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(l.trim()));

if (isCancelRequest && !hasEmailFormat) {
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
    console.log('✅ Parse Cancel Data actualizado');
}

// 3. Actualizar Search Cancel Appointment
const searchCancelNode = j.nodes.find(n => n.name === 'Search Cancel Appointment');
if (searchCancelNode) {
    searchCancelNode.parameters.url = "=https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/search?email={{ encodeURIComponent($json.email) }}&name={{ encodeURIComponent($json.name) }}&scheduled_date={{ $json.scheduledDate }}&scheduled_time={{ $json.scheduledTime }}";
    console.log('✅ Search Cancel Appointment actualizado');
}

// 4. Actualizar mensaje de formato inválido de cancelación
const invalidCancelNode = j.nodes.find(n => n.name === 'Send Cancel Invalid Format');
if (invalidCancelNode) {
    invalidCancelNode.parameters.jsonBody = `={{ JSON.stringify({ messaging_product: 'whatsapp', recipient_type: 'individual', to: $json.phoneNumber, type: 'text', text: { preview_url: false, body: '❌ Formato inválido.\\n\\nEnvía tus datos en 4 líneas:\\n1. Nombre completo\\n2. Correo electrónico\\n3. Fecha (DD/MM/AAAA)\\n4. Hora (HH:MM)\\n\\nEjemplo:\\nJuan Pérez\\njuan@email.com\\n30/12/2025\\n10:00' } }) }}`;
    console.log('✅ Send Cancel Invalid Format actualizado');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
