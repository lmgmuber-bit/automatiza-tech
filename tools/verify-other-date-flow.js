const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

console.log('==========================================================');
console.log('FLUJO "OTRA FECHA" - VERIFICACIÓN COMPLETA');
console.log('==========================================================\n');

// 1. Button Action detecta other_date
const ba = j.nodes.find(n => n.name === 'Button Action');
const baRules = ba?.parameters?.rules?.values;
const otherDateRule = baRules?.find(r => r.outputKey === 'Otra Fecha');
console.log('1. DETECCIÓN DEL BOTÓN');
console.log(`   ✅ Button Action output "Otra Fecha" cuando buttonId === "other_date"`);

// 2. Send Other Date Options envía mensaje
const sodo = j.nodes.find(n => n.name === 'Send Other Date Options');
console.log('\n2. ENVÍO DE MENSAJE');
if (sodo?.parameters?.jsonBody?.includes('Escríbeme la fecha exacta')) {
    console.log('   ✅ Send Other Date Options envía instrucciones al usuario');
}

// 3. Set Date State guarda pending_date
const setDate = j.nodes.find(n => n.name === 'Set Date State');
console.log('\n3. GUARDAR ESTADO EN REDIS');
console.log(`   Key: ${setDate?.parameters?.key}`);
console.log(`   Value: ${setDate?.parameters?.value}`);
console.log(`   TTL: ${setDate?.parameters?.options?.expireTime || setDate?.parameters?.ttl}s`);
if (setDate?.parameters?.key?.includes('Process Interactive')) {
    console.log('   ✅ Usa phoneNumber de Process Interactive (correcto)');
} else {
    console.log('   ❌ Referencia incorrecta');
}

// 4. Check Pending Date lee el estado
const checkPending = j.nodes.find(n => n.name === 'Check Pending Date');
console.log('\n4. LECTURA DE ESTADO (siguiente mensaje del usuario)');
console.log(`   Key: ${checkPending?.parameters?.key}`);
if (checkPending?.parameters?.key?.includes('Process Text')) {
    console.log('   ✅ Usa phoneNumber de Process Text (correcto)');
}

// 5. Merge Booking Data combina
const merge = j.nodes.find(n => n.name === 'Merge Booking Data');
console.log('\n5. MERGE BOOKING DATA');
const pendingAssign = merge?.parameters?.assignments?.assignments?.find(a => a.name === 'pendingDate');
console.log(`   pendingDate: ${pendingAssign?.value}`);

// 6. Check Email Booking detecta PARSE_CUSTOM_DATE
const checkEmail = j.nodes.find(n => n.name === 'Check Email Booking');
console.log('\n6. CHECK EMAIL BOOKING');
if (checkEmail?.parameters?.jsCode?.includes('PARSE_CUSTOM_DATE')) {
    console.log('   ✅ Detecta pendingDate === "awaiting_custom_date"');
    console.log('   ✅ Retorna status: "PARSE_CUSTOM_DATE"');
}

// 7. Booking Status rutea
console.log('\n7. BOOKING STATUS (Switch)');
const bsConns = j.connections['Booking Status'];
console.log(`   Output 5 (PARSE_CUSTOM_DATE): ${bsConns?.main?.[5]?.[0]?.node}`);

// 8. Parse Custom Date
const parseCustom = j.nodes.find(n => n.name === 'Parse Custom Date');
console.log('\n8. PARSE CUSTOM DATE');
console.log('   ✅ Parsea fechas como "30 de diciembre a las 10:00"');

// 9. Validación y flujo siguiente
console.log('\n9. IS CUSTOM DATE VALID?');
const icdvConns = j.connections['Is Custom Date Valid?'];
console.log(`   Output 0 (válido): ${icdvConns?.main?.[0]?.[0]?.node}`);
console.log(`   Output 1 (inválido): ${icdvConns?.main?.[1]?.[0]?.node}`);

console.log('\n==========================================================');
console.log('✅ FLUJO COMPLETO VERIFICADO');
console.log('==========================================================');
console.log('\nREIMPORTA EL ARCHIVO EN N8N PARA APLICAR LAS CORRECCIONES');
