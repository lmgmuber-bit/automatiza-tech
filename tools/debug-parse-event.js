const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver Parse Event To Delete que procesa los datos de Get Reschedule Event
const parseEvent = j.nodes.find(n => n.name === 'Parse Event To Delete');
console.log('=== Parse Event To Delete ===');
console.log(parseEvent?.parameters?.jsCode);

// Ver conexiones de Parse Event To Delete
const petdConns = j.connections['Parse Event To Delete'];
console.log('\nParse Event To Delete conecta a:', petdConns?.main?.[0]?.map(c => c.node));

// Ver Delete Old Appointment
const deleteOld = j.nodes.find(n => n.name === 'Delete Old Appointment');
console.log('\n=== Delete Old Appointment ===');
console.log('URL:', deleteOld?.parameters?.url);

// El flujo es:
// Get Reschedule Event -> Parse Event To Delete -> ??? -> Delete Old Appointment -> Get Appointments Config -> Generate Days

// Necesito que Generate Days pueda acceder a Parse Event To Delete para obtener phoneNumber
