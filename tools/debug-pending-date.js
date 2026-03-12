const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Buscar nodos relacionados con pending_date
console.log('=== NODOS PENDING_DATE ===\n');

// 1. Send Other Date Options - guarda el estado
const sendOther = j.nodes.find(n => n.name === 'Send Other Date Options');
console.log('1. Send Other Date Options:');
console.log('   textBody:', (sendOther?.parameters?.textBody || '').substring(0, 100));

// 2. Set Date State - guarda en Redis
const setDate = j.nodes.find(n => n.name === 'Set Date State');
console.log('\n2. Set Date State (guarda en Redis):');
console.log('   key:', setDate?.parameters?.key);
console.log('   value:', setDate?.parameters?.value);

// 3. Buscar nodo que LEE pending_date de Redis
const checkPending = j.nodes.find(n => n.name === 'Check Pending Date State');
console.log('\n3. Check Pending Date State (lee de Redis):');
if (checkPending) {
    console.log('   key:', checkPending.parameters?.key);
} else {
    console.log('   ❌ NO EXISTE');
    // Buscar todos los nodos Redis GET
    const redisGets = j.nodes.filter(n => n.type === 'n8n-nodes-base.redis' && n.parameters?.operation === 'get');
    console.log('   Nodos Redis GET encontrados:');
    redisGets.forEach(n => {
        console.log(`     - ${n.name}: key=${n.parameters?.key}`);
    });
}

// 4. Ver el flujo de Check Booking Status
const checkBooking = j.nodes.find(n => n.name === 'Check Booking Status');
console.log('\n4. Check Booking Status:');
if (checkBooking) {
    console.log('   jsCode preview:');
    const code = checkBooking.parameters?.jsCode || '';
    // Mostrar la parte relevante de pending_date
    if (code.includes('pendingDate')) {
        const lines = code.split('\n');
        lines.forEach((line, i) => {
            if (line.includes('pending') || line.includes('Pending')) {
                console.log(`     L${i+1}: ${line.trim()}`);
            }
        });
    }
}

// 5. Ver Check Text States
const checkStates = j.nodes.find(n => n.name === 'Check Text States');
console.log('\n5. Check Text States:');
if (checkStates) {
    console.log('   Existe:', !!checkStates);
    // Ver las keys que consulta
    if (checkStates.parameters?.keysToGet) {
        console.log('   Keys:', JSON.stringify(checkStates.parameters.keysToGet, null, 2));
    }
}

// 6. Ver conexiones de Process Text
console.log('\n6. Conexiones desde Process Text:');
const processTextConns = j.connections['Process Text'];
if (processTextConns?.main?.[0]) {
    processTextConns.main[0].forEach(c => {
        console.log(`   -> ${c.node}`);
    });
}

// 7. Buscar nodo que verifica pending_date antes de Check Booking Status
console.log('\n7. Buscando nodo que lee pending_date_...');
j.nodes.forEach(n => {
    if (n.parameters?.key && n.parameters.key.includes('pending_date')) {
        console.log(`   ${n.name}: ${n.parameters.key}`);
    }
});
