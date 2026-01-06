const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

console.log('=== FLUJO DESPUÉS DE FECHA PERSONALIZADA VÁLIDA ===\n');

// Después de Is Custom Date Valid? (true), va a:
const icdvConns = j.connections['Is Custom Date Valid?'];
console.log('1. Is Custom Date Valid? (output 0 - válido) →', icdvConns?.main?.[0]?.[0]?.node);

// Clear Pending Date State
const clearPDConns = j.connections['Clear Pending Date State'];
console.log('2. Clear Pending Date State →', clearPDConns?.main?.[0]?.[0]?.node);

// Save Custom Booking State
const saveCustom = j.nodes.find(n => n.name === 'Save Custom Booking State');
console.log('\n=== Save Custom Booking State ===');
console.log('Key:', saveCustom?.parameters?.key);
console.log('Value:', saveCustom?.parameters?.value);
console.log('TTL:', saveCustom?.parameters?.options?.expireTime || saveCustom?.parameters?.ttl);

// Conexiones de Save Custom Booking State
const scbsConns = j.connections['Save Custom Booking State'];
console.log('\n3. Save Custom Booking State →', scbsConns?.main?.[0]?.[0]?.node);

// Ask Name for Custom Date
const askName = j.nodes.find(n => n.name === 'Ask Name for Custom Date');
console.log('\n=== Ask Name for Custom Date ===');
console.log('Type:', askName?.type);
// Si es HTTP Request, ver el body
if (askName?.parameters?.jsonBody) {
    console.log('Message:', askName.parameters.jsonBody.substring(0, 200));
}

// AHORA: Cuando el usuario escribe su nombre, el flujo es:
// Process Text -> Check Pending Booking -> ...
// Check Pending Booking debe leer el estado guardado por Save Custom Booking State

console.log('\n=== VERIFICACIÓN DE CLAVES ===');
console.log('Save Custom Booking State guarda en:', saveCustom?.parameters?.key);

const checkPB = j.nodes.find(n => n.name === 'Check Pending Booking');
console.log('Check Pending Booking lee de:', checkPB?.parameters?.key);

// El problema: Save Custom Booking State puede estar usando una referencia incorrecta
// Necesito ver qué nodo referencia para obtener phoneNumber
