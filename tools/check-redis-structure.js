const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver estructura real de un nodo Redis SET
const saveCancel = j.nodes.find(n => n.name === 'Save Cancel State');
console.log('=== Save Cancel State ===');
console.log(JSON.stringify(saveCancel?.parameters, null, 2));

const saveBooking = j.nodes.find(n => n.name === 'Save Booking State');
console.log('\n=== Save Booking State ===');
console.log(JSON.stringify(saveBooking?.parameters, null, 2));
