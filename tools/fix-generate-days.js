const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Generate Days para usar $input o buscar en múltiples nodos
const generateDaysIdx = j.nodes.findIndex(n => n.name === 'Generate Days');
if (generateDaysIdx >= 0) {
    j.nodes[generateDaysIdx].parameters.jsCode = `// Generar días disponibles validando horarios y feriados
const config = $('Get Appointments Config').first().json;

// Obtener phoneNumber/phoneNumberId del input (puede venir de diferentes flujos)
let phoneNumber, phoneNumberId, contactName;

// Intentar obtener de Route Action (flujo normal de booking)
try {
  const routeData = $('Route Action').first().json;
  phoneNumber = routeData.phoneNumber;
  phoneNumberId = routeData.phoneNumberId;
  contactName = routeData.contactName;
} catch (e) {
  // Si no existe Route Action, intentar otros nodos
}

// Si no se obtuvo, intentar de Process Search Results (flujo de reprogramación)
if (!phoneNumber) {
  try {
    const searchData = $('Process Search Results').first().json;
    phoneNumber = searchData.phoneNumber;
    phoneNumberId = searchData.phoneNumberId;
    contactName = searchData.clientName;
  } catch (e) {}
}

// Si todavía no hay, usar el input directo
if (!phoneNumber) {
  const inputData = $input.first().json;
  phoneNumber = inputData.phoneNumber;
  phoneNumberId = inputData.phoneNumberId;
  contactName = inputData.contactName || inputData.clientName;
}

const weekSchedule = config.data?.weekSchedule || {};
const holidays = config.data?.holidays || [];

const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
const dayNamesES = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
const monthsES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

const now = DateTime.now().setZone('America/Santiago');
const days = [];
let checkDate = now;
let attempts = 0;

while (days.length < 3 && attempts < 30) {
  checkDate = checkDate.plus({ days: 1 });
  attempts++;

  const dayOfWeek = checkDate.weekday % 7;
  const dayName = dayNames[dayOfWeek];
  const dateKey = checkDate.toFormat('yyyy-MM-dd');

  // Verificar si el día está habilitado
  const dayConfig = weekSchedule[dayName] || { enabled: false };
  if (!dayConfig.enabled) continue;

  // Verificar si es feriado
  if (holidays.includes(dateKey)) continue;

  days.push({
    date: dateKey,
    display: \`\${dayNamesES[dayOfWeek]} \${checkDate.day} \${monthsES[checkDate.month - 1]}\`,
    btnId: \`day_\${dateKey}\`
  });
}

return {
  json: {
    phoneNumber: phoneNumber,
    phoneNumberId: phoneNumberId,
    contactName: contactName,
    availableDays: days
  }
};`;
    console.log('✅ Generate Days corregido');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
