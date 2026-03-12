const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// El problema es que Merge Booking Data usa $('Check Pending Booking')
// pero Check Pending Booking NO está en el camino hacia Merge
// Necesita que Check Pending Booking se ejecute ANTES (en paralelo)

// Check Pending Booking conecta a...
console.log('=== Conexiones de Check Pending Booking ===');
const cpbConns = j.connections['Check Pending Booking'];
console.log(JSON.stringify(cpbConns, null, 2));

// El flujo correcto debería ser:
// Process Text -> Check Pending Booking -> Merge Booking Data (input 1)
// Process Text -> Check Pending Date -> Merge Booking Data (input 0)
// Merge Booking Data -> Check Email Booking

// Verificar si Merge Booking Data tiene múltiples inputs
const mergeNode = j.nodes.find(n => n.name === 'Merge Booking Data');
console.log('\n=== Merge Booking Data ===');
console.log('Type:', mergeNode?.type);
// Si es Set, solo tiene 1 input

// El problema: Merge es un nodo SET, no un Merge real
// Usa expresiones para obtener datos de otros nodos
// Esto funciona porque N8N permite referenciar cualquier nodo ejecutado antes

// Verificar la expresión de pendingDate
console.log('\nExpresión pendingDate:', mergeNode?.parameters?.assignments?.assignments?.[1]?.value);

// Verificar si Check Email Booking procesa pendingDate
const checkEmail = j.nodes.find(n => n.name === 'Check Email Booking');
console.log('\n=== Check Email Booking ===');
console.log('Type:', checkEmail?.type);
if (checkEmail?.parameters?.jsCode) {
    const code = checkEmail.parameters.jsCode;
    console.log('Procesa pendingDate:', code.includes('pendingDate'));
    // Mostrar líneas relevantes
    const lines = code.split('\n');
    let foundSection = false;
    lines.forEach((line, i) => {
        if (line.includes('pendingDate') || line.includes('PARSE_CUSTOM_DATE')) {
            console.log(`L${i+1}: ${line.trim()}`);
            foundSection = true;
        }
    });
}

// Ahora el flujo real es:
// Process Text -> Check Pending Date -> Merge Booking Data -> Check Email Booking
// Process Text -> Check Pending Booking (en paralelo, referenciado por expresión)

// Verificar Check Email Booking conexiones
console.log('\n=== Conexiones de Check Email Booking ===');
const ceConns = j.connections['Check Email Booking'];
console.log(JSON.stringify(ceConns, null, 2));
