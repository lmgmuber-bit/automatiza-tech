const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// El problema puede estar en los nombres de campos
// Save Custom Booking State guarda:
// { selectedDay, selectedTime, phone, phoneNumberId, step }

// Pero Check Email Booking espera:
// bookingData.day, bookingData.time

// Verificar Save Custom Booking State
const saveCustom = j.nodes.find(n => n.name === 'Save Custom Booking State');
console.log('=== Save Custom Booking State VALUE ===');
console.log(saveCustom?.parameters?.value);

// Verificar Check Email Booking - cómo lee bookingData
const checkEmail = j.nodes.find(n => n.name === 'Check Email Booking');
const code = checkEmail?.parameters?.jsCode || '';

console.log('\n=== Check Email Booking - Uso de bookingData ===');
const lines = code.split('\n');
lines.forEach((line, i) => {
    if (line.includes('bookingData.') && (line.includes('day') || line.includes('time') || line.includes('step'))) {
        console.log(`L${i+1}: ${line.trim()}`);
    }
});

// El problema: Save Custom guarda con 'day' y 'time'
// Check Email Booking busca bookingData.day y bookingData.time

// Verificar que Save Custom Booking State use los nombres correctos
// Actualmente guarda: { day: ..., time: ..., phone: ..., phoneNumberId: ..., step: 'WAITING_NAME' }
// Esto es correcto!

// Verificar Save Booking State (el normal, para flujo de botones)
const saveBooking = j.nodes.find(n => n.name === 'Save Booking State');
console.log('\n=== Save Booking State VALUE (flujo normal) ===');
console.log(saveBooking?.parameters?.value);

// Comparar
console.log('\n=== COMPARACIÓN ===');
console.log('Save Custom Booking State:');
console.log('  - day, time, phone, phoneNumberId, step');
console.log('\nSave Booking State (normal):');
console.log('  - day, time, name, phone, phoneNumberId, step');
