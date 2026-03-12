const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// El problema puede ser que cuando hay pending_date, el flujo va a NORMAL también
// porque Check Email Booking pone status: PARSE_CUSTOM_DATE pero 
// Booking Status también tiene output para NORMAL

// Ver si hay flujos paralelos que podrían interferir
console.log('=== Verificando race conditions en flujo TEXT ===\n');

// Conexiones desde Process Text
const ptConns = j.connections['Process Text'];
console.log('Process Text conecta en paralelo a:');
ptConns?.main?.[0]?.forEach(c => console.log(`  - ${c.node}`));

// Pero estos nodos son secuenciales en realidad:
// Process Text -> Check Pending Booking -> Check Pending Date -> Merge -> Check Email Booking

// El problema puede estar en Redis Push que es el flujo "NORMAL"
// Ver qué pasa cuando Check Email Booking retorna PARSE_CUSTOM_DATE

console.log('\n=== Outputs de Booking Status ===');
const bsConns = j.connections['Booking Status'];
bsConns?.main?.forEach((arr, i) => {
    console.log(`Output ${i}:`, arr.map(c => c.node));
});

// Verificar que PARSE_CUSTOM_DATE no active también otros flujos
// El switch solo activa UN output basado en status

// Ver Redis Push - qué conecta
console.log('\n=== Redis Push conecta a ===');
const rpConns = j.connections['Redis Push'];
console.log(rpConns?.main?.[0]?.map(c => c.node));

// El problema real podría estar en que cuando el usuario escribe "30 de diciembre a las 10",
// el flujo va así:
// 1. Process Text - OK
// 2. Check Pending Booking - lee booking_XXX (null porque no hay booking activo para esta fecha)
// 3. Check Pending Date - lee pending_date_XXX 
// 4. Merge Booking Data - debería tener pendingDate = 'awaiting_custom_date'
// 5. Check Email Booking - debería retornar PARSE_CUSTOM_DATE

// Pero algo pasa que va a NORMAL en lugar de PARSE_CUSTOM_DATE

// Verificar la clave usada para guardar pending_date
const setDateState = j.nodes.find(n => n.name === 'Set Date State');
console.log('\n=== Set Date State (guarda pending_date) ===');
console.log('Key:', setDateState?.parameters?.key);

const checkPendingDate = j.nodes.find(n => n.name === 'Check Pending Date');
console.log('\n=== Check Pending Date (lee pending_date) ===');
console.log('Key:', checkPendingDate?.parameters?.key);
console.log('PropertyName:', checkPendingDate?.parameters?.propertyName);

// Verificar si las claves coinciden
const setKey = setDateState?.parameters?.key || '';
const getKey = checkPendingDate?.parameters?.key || '';
console.log('\n=== Comparación de claves ===');
console.log('SET usa: $("Send Other Date Options")');
console.log('GET usa: $("Process Text")');
console.log('Ambos usan phoneNumber - OK si son el mismo número');

// El problema podría ser el orden de ejecución
// Send Other Date Options se ejecuta cuando el usuario presiona el botón "Otra fecha"
// Set Date State guarda pending_date_XXX = awaiting_custom_date

// Luego cuando el usuario escribe la fecha:
// Process Text -> Check Pending Booking -> Check Pending Date
// Check Pending Date debería leer pending_date_XXX y obtener 'awaiting_custom_date'

// Ver el código de Merge Booking Data
const mergeNode = j.nodes.find(n => n.name === 'Merge Booking Data');
console.log('\n=== Merge Booking Data ===');
console.log(JSON.stringify(mergeNode?.parameters?.assignments, null, 2));
