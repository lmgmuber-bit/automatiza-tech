const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

console.log('=== VERIFICACIÓN FLUJO NOMBRE DESPUÉS DE FECHA CUSTOM ===\n');

// 1. Save Custom Booking State guarda el estado
const saveCustom = j.nodes.find(n => n.name === 'Save Custom Booking State');
console.log('1. SAVE CUSTOM BOOKING STATE');
console.log('   Key:', saveCustom?.parameters?.key);
console.log('   Value:', saveCustom?.parameters?.value);

// Verificar que incluya step: 'WAITING_NAME'
if (saveCustom?.parameters?.value?.includes('WAITING_NAME')) {
    console.log('   ✅ Incluye step: WAITING_NAME');
} else {
    console.log('   ❌ NO incluye step: WAITING_NAME');
}

// 2. Cuando usuario escribe nombre, Check Email Booking debe detectar bookingData.step === 'WAITING_NAME'
const checkEmail = j.nodes.find(n => n.name === 'Check Email Booking');
console.log('\n2. CHECK EMAIL BOOKING');
const code = checkEmail?.parameters?.jsCode || '';
if (code.includes('WAITING_NAME')) {
    console.log('   ✅ Detecta bookingData.step === "WAITING_NAME"');
} else {
    console.log('   ❌ NO detecta WAITING_NAME');
}

// Ver qué retorna cuando step === WAITING_NAME
const lines = code.split('\n');
let inWaitingName = false;
lines.forEach((line, i) => {
    if (line.includes('WAITING_NAME')) {
        inWaitingName = true;
    }
    if (inWaitingName && line.includes('return')) {
        console.log(`   L${i+1}: ${line.trim()}`);
        if (line.includes('SAVE_NAME')) {
            console.log('   ✅ Retorna status: SAVE_NAME');
        }
    }
    if (inWaitingName && line.includes('}')) {
        inWaitingName = false;
    }
});

// 3. Booking Status debe tener output para SAVE_NAME
const bookingStatus = j.nodes.find(n => n.name === 'Booking Status');
const bsRules = bookingStatus?.parameters?.rules?.values;
const saveNameRule = bsRules?.find(r => r.outputKey === 'SAVE_NAME');
console.log('\n3. BOOKING STATUS');
if (saveNameRule) {
    console.log('   ✅ Tiene output SAVE_NAME');
} else {
    console.log('   ❌ NO tiene output SAVE_NAME');
}

// Ver a dónde conecta SAVE_NAME
const bsConns = j.connections['Booking Status'];
console.log('   Output 1 (SAVE_NAME) →', bsConns?.main?.[1]?.[0]?.node);

// 4. Save Name debe guardar el nombre y pedir email
const saveName = j.nodes.find(n => n.name === 'Save Name');
console.log('\n4. SAVE NAME');
console.log('   Type:', saveName?.type);
console.log('   Key:', saveName?.parameters?.key);
console.log('   Value preview:', (saveName?.parameters?.value || '').substring(0, 100));

// Verificar que incluya step: 'WAITING_EMAIL'
if (saveName?.parameters?.value?.includes('WAITING_EMAIL')) {
    console.log('   ✅ Incluye step: WAITING_EMAIL');
} else {
    console.log('   ❌ NO incluye step: WAITING_EMAIL');
}

// Conexiones de Save Name
const snConns = j.connections['Save Name'];
console.log('   Save Name →', snConns?.main?.[0]?.[0]?.node);
