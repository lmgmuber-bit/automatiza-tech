const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Check Email Booking - debe verificar si hay cancel/reschedule activo
const checkEmailIdx = j.nodes.findIndex(n => n.name === 'Check Email Booking');
if (checkEmailIdx >= 0) {
    const newCode = `const input = $input.first().json;
const textContent = $('Process Text').first().json.textContent;
const phoneNumber = $('Process Text').first().json.phoneNumber;

// Verificar si está esperando una fecha custom (viene de Check Pending Date - Redis Get)
let isPendingDate = false;
if (input.pendingDate && input.pendingDate === 'awaiting_custom_date') {
  isPendingDate = true;
}

// Si está esperando fecha custom, procesar
if (isPendingDate) {
  return {
    json: {
      status: 'PARSE_CUSTOM_DATE',
      messageText: textContent,
      phoneNumber: phoneNumber,
      phoneNumberId: $('Process Text').first().json.phoneNumberId,
      contactName: $('Process Text').first().json.contactName
    }
  };
}

// NUEVO: Verificar si hay estado de cancelación o reprogramación activo
// Estos datos vienen del Merge que junta Check Pending Booking, Check Cancel State y Check Reschedule State
const cancelState = $('Check Cancel State').first().json.cancelState;
const rescheduleState = $('Check Reschedule State').first().json.rescheduleState;

const hasCancelActive = cancelState === 'awaiting_cancel_data' || cancelState === 'cancel_confirmed';
const hasRescheduleActive = rescheduleState === 'awaiting_reschedule_data' || rescheduleState === 'reschedule_confirmed';

// Si hay cancelación o reprogramación activa, NO ir al flujo normal (para evitar doble respuesta)
if (hasCancelActive || hasRescheduleActive) {
  return {
    json: {
      status: 'SKIP_NORMAL',
      reason: hasCancelActive ? 'cancel_active' : 'reschedule_active',
      phoneNumber: phoneNumber,
      phoneNumberId: $('Process Text').first().json.phoneNumberId
    }
  };
}

// bookingData puede ser un string JSON o null si no existe la key
let bookingData = null;
if (input.bookingData && input.bookingData !== null) {
  try {
    bookingData = JSON.parse(input.bookingData);
  } catch (e) {
    bookingData = null;
  }
}

// Verificar si es un email válido
const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
const isEmail = emailRegex.test(textContent.trim());

// Verificar si es un nombre válido (mínimo 2 caracteres, solo letras y espacios)
const nameText = textContent.trim();
const isValidName = nameText.length >= 2 && /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\\s]+$/.test(nameText);

if (!bookingData) {
  // No hay reserva pendiente → flujo normal
  return {
    json: {
      status: 'NORMAL',
      hasBooking: false,
      textContent: textContent,
      phoneNumber: $('Process Text').first().json.phoneNumber,
      contactName: $('Process Text').first().json.contactName,
      phoneNumberId: $('Process Text').first().json.phoneNumberId
    }
  };
}

// Hay booking pendiente - verificar en qué paso está
if (bookingData.step === 'WAITING_NAME') {
  if (isValidName) {
    // Nombre válido - guardar y pedir email
    return {
      json: {
        status: 'SAVE_NAME',
        name: nameText,
        day: bookingData.day,
        time: bookingData.time,
        phone: bookingData.phone,
        phoneNumber: bookingData.phone,
        phoneNumberId: bookingData.phoneNumberId
      }
    };
  } else {
    // Nombre inválido - pedir de nuevo
    return {
      json: {
        status: 'INVALID_NAME',
        day: bookingData.day,
        time: bookingData.time,
        phone: bookingData.phone,
        phoneNumber: bookingData.phone,
        phoneNumberId: bookingData.phoneNumberId
      }
    };
  }
} else if (bookingData.step === 'WAITING_EMAIL') {
  if (isEmail) {
    // Tiene nombre y ahora envió email → completar reserva
    return {
      json: {
        status: 'COMPLETE_BOOKING',
        hasBooking: true,
        email: textContent.trim(),
        date: bookingData.day,
        time: bookingData.time,
        name: bookingData.name,
        phone: bookingData.phone,
        phoneNumber: bookingData.phone,
        phoneNumberId: bookingData.phoneNumberId
      }
    };
  } else {
    // Esperando email pero no es email válido
    return {
      json: {
        status: 'INVALID_EMAIL',
        hasPendingBooking: true,
        textContent: textContent,
        day: bookingData.day,
        time: bookingData.time,
        name: bookingData.name,
        phone: bookingData.phone,
        phoneNumber: bookingData.phone,
        phoneNumberId: bookingData.phoneNumberId
      }
    };
  }
} else {
  // Estado desconocido - flujo normal
  return {
    json: {
      status: 'NORMAL',
      hasBooking: false,
      textContent: textContent,
      phoneNumber: $('Process Text').first().json.phoneNumber,
      contactName: $('Process Text').first().json.contactName,
      phoneNumberId: $('Process Text').first().json.phoneNumberId
    }
  };
}`;
    j.nodes[checkEmailIdx].parameters.jsCode = newCode;
    console.log('✅ Check Email Booking corregido - bloquea IA cuando hay cancel/reschedule activo');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
