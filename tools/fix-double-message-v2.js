const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Check Email Booking - detectar si el mensaje parece datos de cancel/reschedule
const checkEmailIdx = j.nodes.findIndex(n => n.name === 'Check Email Booking');
if (checkEmailIdx >= 0) {
    const newCode = `const input = $input.first().json;
const textContent = $('Process Text').first().json.textContent;
const phoneNumber = $('Process Text').first().json.phoneNumber;

// Verificar si está esperando una fecha custom
let isPendingDate = false;
if (input.pendingDate && input.pendingDate === 'awaiting_custom_date') {
  isPendingDate = true;
}

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

// bookingData puede ser un string JSON o null
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

// Verificar si es un nombre válido
const nameText = textContent.trim();
const isValidName = nameText.length >= 2 && /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\\s]+$/.test(nameText);

// NUEVO: Detectar si el mensaje parece datos de cancel/reschedule
// (2 líneas: nombre + email)
const lines = textContent.trim().split('\\n').filter(l => l.trim());
const looksLikeCancelData = lines.length >= 2 && 
  emailRegex.test(lines[1].trim()) && 
  lines[0].trim().length >= 2;

if (!bookingData) {
  // No hay reserva pendiente
  
  // Si el mensaje parece datos de cancel/reschedule, NO ir al flujo normal
  // (para evitar que el Agente IA responda mientras cancel/reschedule procesa)
  if (looksLikeCancelData) {
    return {
      json: {
        status: 'SKIP_NORMAL',
        reason: 'looks_like_cancel_reschedule_data',
        phoneNumber: phoneNumber,
        phoneNumberId: $('Process Text').first().json.phoneNumberId
      }
    };
  }
  
  // Flujo normal
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

// Hay booking pendiente
if (bookingData.step === 'WAITING_NAME') {
  if (isValidName) {
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
    console.log('✅ Check Email Booking corregido - detecta datos de cancel/reschedule');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
