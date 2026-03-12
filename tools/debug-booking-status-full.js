const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver nodo Booking Status completo
const bookingStatus = j.nodes.find(n => n.name === 'Booking Status');
console.log('=== Booking Status ===');
console.log(JSON.stringify(bookingStatus?.parameters, null, 2));
