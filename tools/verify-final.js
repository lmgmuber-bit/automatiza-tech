const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

console.log('======================================================');
console.log('RESUMEN DE CORRECCIONES - WhatsApp_Tech_Principal.json');
console.log('======================================================\n');

// 1. Redis TTL
console.log('1. REDIS TTL (600 segundos)');
const redisNodes = ['Save Booking State', 'Save Name', 'Set Date State', 'Save Custom Booking State',
    'Save Reschedule State', 'Save Event To Delete', 'Save Cancel State', 'Save Cancel Event'];
redisNodes.forEach(name => {
    const node = j.nodes.find(n => n.name === name);
    const ttl = node?.parameters?.options?.expireTime || node?.parameters?.ttl;
    if (ttl === 600) {
        console.log(`  ✅ ${name}: TTL = 600s`);
    } else {
        console.log(`  ❌ ${name}: TTL = ${ttl || 'N/A'}`);
    }
});

// 2. Clear States Keys
console.log('\n2. CLEAR STATES - Referencias correctas');
const clearNodes = [
    { name: 'Clear Cancel State', expected: "Check Cancel Event Status" },
    { name: 'Clear Cancel Confirmed', expected: "Check Cancel Event Status" },
    { name: 'Clear Reschedule State', expected: "Process Interactive" },
    { name: 'Clear Confirmed State', expected: "Process Interactive" }
];
clearNodes.forEach(({ name, expected }) => {
    const node = j.nodes.find(n => n.name === name);
    if (node) {
        const key = node.parameters.key || '';
        if (key.includes(expected)) {
            console.log(`  ✅ ${name}`);
        } else {
            console.log(`  ❌ ${name}: usa ${key.match(/\$\([^)]+\)/)?.[0] || 'desconocido'}`);
        }
    }
});

// 3. Send Keep Appointment
console.log('\n3. SEND KEEP APPOINTMENT');
const keepNode = j.nodes.find(n => n.name === 'Send Keep Appointment');
if (keepNode?.parameters?.to) {
    console.log(`  ✅ Configurado correctamente`);
} else {
    console.log(`  ❌ Falta configuración`);
}

// 4. Parse Cancel Event
console.log('\n4. PARSE CANCEL EVENT');
const parseNode = j.nodes.find(n => n.name === 'Parse Cancel Event');
if (parseNode) {
    const code = parseNode.parameters.jsCode || '';
    const checks = [
        code.includes('eventData') ? '✅' : '❌',
        code.includes("$('Process Interactive')") ? '✅' : '❌'
    ];
    console.log(`  ${checks[0]} Lee de eventData`);
    console.log(`  ${checks[1]} Obtiene phoneNumber de Process Interactive`);
}

// 5. Delete Cancelled Appointment
console.log('\n5. DELETE CANCELLED APPOINTMENT');
const delNode = j.nodes.find(n => n.name === 'Delete Cancelled Appointment');
if (delNode) {
    const method = delNode.parameters.method;
    console.log(`  ${method === 'DELETE' ? '✅' : '❌'} Método: ${method}`);
}

// 6. Flujo de conexiones para Cancel
console.log('\n6. FLUJO DE CONEXIONES (Limpieza de estados)');
const checkFlow = (from, to) => {
    const conns = j.connections[from];
    if (conns && conns.main) {
        for (const arr of conns.main) {
            if (arr.some(c => c.node === to)) return true;
        }
    }
    return false;
};

console.log(`  ${checkFlow('Send Cancel Success', 'Clear Cancel State') ? '✅' : '❌'} Send Cancel Success -> Clear Cancel State`);
console.log(`  ${checkFlow('Send Keep Appointment', 'Clear Cancel State') ? '✅' : '❌'} Send Keep Appointment -> Clear Cancel State`);
console.log(`  ${checkFlow('Clear Cancel State', 'Clear Cancel Confirmed') ? '✅' : '❌'} Clear Cancel State -> Clear Cancel Confirmed`);

console.log('\n======================================================');
console.log('✅ REIMPORTA EL ARCHIVO EN N8N');
console.log('   N8N/PROD/WhatsApp_Tech_Principal.json');
console.log('======================================================');
