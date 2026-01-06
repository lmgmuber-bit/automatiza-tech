const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Parse Cancel Data - detectar si es solicitud de cancelación vs datos reales
const parseCancelDataIdx = j.nodes.findIndex(n => n.name === 'Parse Cancel Data');
if (parseCancelDataIdx >= 0) {
    const newCode = `const input = $input.first().json;
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
// Esto evita race condition cuando el usuario dice "quiero cancelar mi cita"
const lowerText = textContent.toLowerCase();
const isCancelRequest = (
  lowerText.includes('cancelar') ||
  lowerText.includes('anular') ||
  lowerText.includes('quiero') ||
  lowerText.includes('necesito') ||
  lowerText.includes('cita') ||
  lowerText.includes('reunion') ||
  lowerText.includes('reunión')
);

// Si parece una solicitud y NO tiene formato de datos (nombre + email en 2 líneas)
const lines = textContent.trim().split('\\n').filter(l => l.trim());
const hasEmailFormat = lines.length >= 2 && /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(lines[1].trim());

if (isCancelRequest && !hasEmailFormat) {
  // Es una solicitud de cancelación, no datos - ignorar para evitar doble mensaje
  return {
    json: {
      status: 'NOT_WAITING',
      phoneNumber: phoneNumber,
      phoneNumberId: phoneNumberId
    }
  };
}

// Validar formato: debe tener al menos 2 líneas
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
    j.nodes[parseCancelDataIdx].parameters.jsCode = newCode;
    console.log('✅ Parse Cancel Data corregido - detecta solicitudes vs datos reales');
}

// También corregir Parse Reschedule Data con la misma lógica
const parseRescheduleIdx = j.nodes.findIndex(n => n.name === 'Parse Reschedule Data');
if (parseRescheduleIdx >= 0) {
    const currentCode = j.nodes[parseRescheduleIdx].parameters.jsCode;
    
    const newRescheduleCode = `const input = $input.first().json;
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
  lowerText.includes('quiero') ||
  lowerText.includes('necesito') ||
  lowerText.includes('cita') ||
  lowerText.includes('reunion') ||
  lowerText.includes('reunión')
);

// Si parece una solicitud y NO tiene formato de datos (nombre + email en 2 líneas)
const lines = textContent.trim().split('\\n').filter(l => l.trim());
const hasEmailFormat = lines.length >= 2 && /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(lines[1].trim());

if (isRescheduleRequest && !hasEmailFormat) {
  return {
    json: {
      status: 'NOT_WAITING',
      phoneNumber: phoneNumber,
      phoneNumberId: phoneNumberId
    }
  };
}

// Validar formato
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

return {
  json: {
    status: 'SEARCH_APPOINTMENT',
    name: name,
    email: email,
    phoneNumber: phoneNumber,
    phoneNumberId: phoneNumberId
  }
};`;
    j.nodes[parseRescheduleIdx].parameters.jsCode = newRescheduleCode;
    console.log('✅ Parse Reschedule Data corregido - detecta solicitudes vs datos reales');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

// Validar
try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
