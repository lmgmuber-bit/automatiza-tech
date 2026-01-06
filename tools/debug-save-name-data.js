const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// El flujo es:
// Check Email Booking (retorna SAVE_NAME con day, time, phone, name, etc.)
// -> Booking Status (switch)
// -> Save Name

// Verificar qué datos retorna Check Email Booking cuando status === SAVE_NAME
const checkEmail = j.nodes.find(n => n.name === 'Check Email Booking');
const code = checkEmail?.parameters?.jsCode || '';

console.log('=== CHECK EMAIL BOOKING - Retorno SAVE_NAME ===');
// Buscar el bloque que retorna SAVE_NAME
const saveNameMatch = code.match(/status:\s*'SAVE_NAME'[\s\S]*?return\s*{[\s\S]*?json:\s*{[\s\S]*?}\s*}/);
if (saveNameMatch) {
    console.log(saveNameMatch[0].substring(0, 400));
}

// El problema podría ser que Check Email Booking retorna los datos correctos
// pero Save Name usa $json que viene de Booking Status (switch)
// y el switch solo pasa los datos sin modificarlos

// Verificar Save Name
const saveName = j.nodes.find(n => n.name === 'Save Name');
console.log('\n=== SAVE NAME ===');
console.log('Key:', saveName?.parameters?.key);
console.log('Value:', saveName?.parameters?.value);

// El $json viene de Check Email Booking -> Booking Status -> Save Name
// Booking Status es un switch, solo filtra y pasa el item

// Verificar que Check Email Booking retorne 'phone' (no 'phoneNumber')
const lines = code.split('\n');
console.log('\n=== Campos retornados por Check Email Booking (SAVE_NAME) ===');
let inSaveName = false;
lines.forEach((line, i) => {
    if (line.includes("status: 'SAVE_NAME'")) {
        inSaveName = true;
    }
    if (inSaveName && (line.includes('phone:') || line.includes('phoneNumber:') || line.includes('day:') || line.includes('time:') || line.includes('name:'))) {
        console.log(`L${i+1}: ${line.trim()}`);
    }
    if (inSaveName && line.includes('};')) {
        inSaveName = false;
    }
});

// Si Check Email Booking retorna 'phoneNumber' pero Save Name espera 'phone',
// hay una inconsistencia
