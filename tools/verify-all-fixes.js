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
    if (node && node.parameters.expireTime === 600) {
        console.log(`  ✅ ${name}: TTL = 600s`);
    } else {
        console.log(`  ❌ ${name}: SIN TTL`);
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
            console.log(`  ✅ ${name}: ${key.substring(0, 60)}...`);
        } else {
            console.log(`  ❌ ${name}: ${key}`);
        }
    }
});

// 3. Send Keep Appointment
console.log('\n3. SEND KEEP APPOINTMENT');
const keepNode = j.nodes.find(n => n.name === 'Send Keep Appointment');
if (keepNode) {
    console.log(`  to: ${keepNode.parameters.to}`);
    console.log(`  phoneNumberId: ${keepNode.parameters.additionalFields?.phoneNumberId || 'N/A'}`);
    console.log(`  textBody: ${(keepNode.parameters.textBody || '').substring(0, 50)}...`);
}

// 4. Parse Cancel Event
console.log('\n4. PARSE CANCEL EVENT');
const parseNode = j.nodes.find(n => n.name === 'Parse Cancel Event');
if (parseNode) {
    const code = parseNode.parameters.jsCode || '';
    if (code.includes('eventData')) {
        console.log('  ✅ Lee de eventData (correcto)');
    }
    if (code.includes("$('Process Interactive')")) {
        console.log('  ✅ Obtiene phoneNumber de Process Interactive');
    }
}

// 5. Delete Cancelled Appointment
console.log('\n5. DELETE CANCELLED APPOINTMENT');
const delNode = j.nodes.find(n => n.name === 'Delete Cancelled Appointment');
if (delNode) {
    console.log(`  method: ${delNode.parameters.method}`);
    console.log(`  url: ${(delNode.parameters.url || '').substring(0, 70)}...`);
}

// 6. Flujo de conexiones para Cancel
console.log('\n6. FLUJO DE CONEXIONES (Cancel)');
const checkFlow = (from, to) => {
    const conns = j.connections[from];
    if (conns && conns.main) {
        for (const arr of conns.main) {
            if (arr.some(c => c.node === to)) {
                return true;
            }
        }
    }
    return false;
};

console.log(`  Check Cancel Event Status -> Parse Cancel Event: ${checkFlow('Check Cancel Event Status', 'Parse Cancel Event') ? '✅' : '❌'}`);
console.log(`  Parse Cancel Event -> Check Cancel Status: ${checkFlow('Parse Cancel Event', 'Check Cancel Status') ? '✅' : '❌'}`);
console.log(`  Delete Cancelled Appointment -> Send Cancel Success: ${checkFlow('Delete Cancelled Appointment', 'Send Cancel Success') ? '✅' : '❌'}`);
console.log(`  Send Cancel Success -> Clear Cancel State: ${checkFlow('Send Cancel Success', 'Clear Cancel State') ? '✅' : '❌'}`);
console.log(`  Send Keep Appointment -> Clear Cancel State: ${checkFlow('Send Keep Appointment', 'Clear Cancel State') ? '✅' : '❌'}`);
console.log(`  Clear Cancel State -> Clear Cancel Confirmed: ${checkFlow('Clear Cancel State', 'Clear Cancel Confirmed') ? '✅' : '❌'}`);

console.log('\n======================================================');
console.log('✅ Archivo listo para reimportar en N8N');
console.log('======================================================');
