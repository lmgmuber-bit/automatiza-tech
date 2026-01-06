const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// El flujo es:
// Process Text -> Check Pending Booking -> Check Pending Date -> Merge Booking Data -> Check Email Booking

// Verificar Check Pending Booking
const checkPB = j.nodes.find(n => n.name === 'Check Pending Booking');
console.log('=== Check Pending Booking ===');
console.log('Key:', checkPB?.parameters?.key);
console.log('PropertyName:', checkPB?.parameters?.propertyName);

// El output de Check Pending Booking es: { bookingData: <string JSON o null> }

// Verificar Merge Booking Data
const merge = j.nodes.find(n => n.name === 'Merge Booking Data');
console.log('\n=== Merge Booking Data ===');
console.log('Assignments:');
merge?.parameters?.assignments?.assignments?.forEach(a => {
    console.log(`  ${a.name}: ${a.value}`);
});

// El problema puede estar en Merge Booking Data
// $('Check Pending Booking') referencia correctamente?
// $input.first() viene de Check Pending Date

// Verificar las conexiones
console.log('\n=== Conexiones ===');
const cpbConns = j.connections['Check Pending Booking'];
console.log('Check Pending Booking ->', cpbConns?.main?.[0]?.map(c => c.node));

const cpdConns = j.connections['Check Pending Date'];
console.log('Check Pending Date ->', cpdConns?.main?.[0]?.map(c => c.node));

// El problema: $('Check Pending Booking') se ejecuta ANTES de Check Pending Date
// pero Merge Booking Data recibe input de Check Pending Date
// y referencia Check Pending Booking por nombre (debería funcionar)

// Verificar el flujo REAL
// 1. Process Text -> Check Pending Booking (Redis GET booking_XXX)
// 2. Check Pending Booking -> Check Pending Date (Redis GET pending_date_XXX)
// 3. Check Pending Date -> Merge Booking Data

// En Merge Booking Data:
// - bookingData = $('Check Pending Booking').first().json.bookingData
// - pendingDate = $input.first().json.pendingDate

// Esto DEBERÍA funcionar porque N8N permite referenciar cualquier nodo ejecutado

console.log('\n=== Verificando Check Email Booking ===');
const checkEmail = j.nodes.find(n => n.name === 'Check Email Booking');
const code = checkEmail?.parameters?.jsCode || '';

// Ver cómo obtiene bookingData
const bookingDataMatch = code.match(/bookingData[^;]+/g);
console.log('Uso de bookingData:');
bookingDataMatch?.slice(0, 5).forEach(m => console.log(`  ${m}`));

// Verificar si parsea correctamente
if (code.includes('JSON.parse(input.bookingData)')) {
    console.log('\n✅ Parsea bookingData con JSON.parse');
}
