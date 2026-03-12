const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

console.log('=== VERIFICACIÓN SAVE CUSTOM BOOKING STATE ===\n');

const saveCustom = j.nodes.find(n => n.name === 'Save Custom Booking State');
console.log('Type:', saveCustom?.type);
console.log('Operation:', saveCustom?.parameters?.operation);
console.log('Key:', saveCustom?.parameters?.key);
console.log('Value:', saveCustom?.parameters?.value);
console.log('TTL (options.expireTime):', saveCustom?.parameters?.options?.expireTime);
console.log('TTL (ttl):', saveCustom?.parameters?.ttl);
console.log('Expire:', saveCustom?.parameters?.expire);

console.log('\n=== COMPARACIÓN CON SAVE BOOKING STATE (flujo normal) ===\n');

const saveBooking = j.nodes.find(n => n.name === 'Save Booking State');
console.log('Type:', saveBooking?.type);
console.log('Operation:', saveBooking?.parameters?.operation);
console.log('Key:', saveBooking?.parameters?.key);
console.log('Value:', saveBooking?.parameters?.value);
console.log('TTL (options.expireTime):', saveBooking?.parameters?.options?.expireTime);

console.log('\n=== RESUMEN ===');
console.log('Save Custom Booking State usa:', saveCustom?.parameters?.key?.includes('Parse Custom Date') ? 'Parse Custom Date ✅' : 'otra referencia ⚠️');
console.log('Save Booking State usa:', saveBooking?.parameters?.key?.includes('$json') ? '$json ✅' : 'otra referencia');
