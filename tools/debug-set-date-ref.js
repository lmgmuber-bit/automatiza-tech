const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver Send Other Date Options completo
const sendOther = j.nodes.find(n => n.name === 'Send Other Date Options');
console.log('=== Send Other Date Options (completo) ===');
console.log(JSON.stringify(sendOther?.parameters, null, 2));

// El problema: Set Date State usa $('Send Other Date Options').first().json.phoneNumber
// pero Send Other Date Options es un HTTP Request - su output es la respuesta del API

// Necesito que Set Date State use el phoneNumber de otro nodo anterior
// que SÍ tenga el phoneNumber disponible

// Ver Process Interactive que procesa los botones
const procInt = j.nodes.find(n => n.name === 'Process Interactive');
console.log('\n=== Process Interactive ===');
console.log('jsCode preview:', (procInt?.parameters?.jsCode || '').substring(0, 300));

// El flujo de botones es:
// WhatsApp Trigger -> Process Interactive -> Button Action -> Send Other Date Options
// Process Interactive tiene phoneNumber en su output
// Pero Set Date State referencia Send Other Date Options que es HTTP

console.log('\n=== Set Date State (actual) ===');
const setDateState = j.nodes.find(n => n.name === 'Set Date State');
console.log('key:', setDateState?.parameters?.key);

// CORRECCIÓN: Cambiar la referencia a Process Interactive
console.log('\n=== CORRECCIÓN NECESARIA ===');
console.log('ACTUAL: pending_date_{{ $("Send Other Date Options")...phoneNumber }}');
console.log('CORRECTO: pending_date_{{ $("Process Interactive").first().json.phoneNumber }}');
