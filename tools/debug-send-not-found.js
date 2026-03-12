const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Buscar Send Not Found Message
const sendNotFound = j.nodes.find(n => n.name === 'Send Not Found Message');
console.log('=== Send Not Found Message ===');
console.log('Type:', sendNotFound?.type);
console.log('URL:', sendNotFound?.parameters?.url);
console.log('jsonBody:', sendNotFound?.parameters?.jsonBody);

// También ver Process Search Results que está antes
const processSearch = j.nodes.find(n => n.name === 'Process Search Results');
console.log('\n=== Process Search Results ===');
console.log('Type:', processSearch?.type);
console.log('jsCode preview:', (processSearch?.parameters?.jsCode || '').substring(0, 500));
