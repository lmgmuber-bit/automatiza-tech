const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

console.log('=== VERIFICACIÓN DEL FLUJO DE REPROGRAMACIÓN ===\n');

const flow = [
    'Get Reschedule Event',
    'Parse Reschedule Event',
    'Delete For Reschedule',
    'Send Reschedule Cancelled',
    'Set Reschedule Data',
    'Clear Reschedule States',
    'Clear Reschedule Search',
    'Get Appointments Config',
    'Generate Days',
    'Send Calendar Buttons'
];

for (let i = 0; i < flow.length - 1; i++) {
    const from = flow[i];
    const to = flow[i + 1];
    const conn = j.connections[from];
    const hasConn = conn && conn.main && conn.main[0] && conn.main[0].some(c => c.node === to);
    console.log(`${from} -> ${to}: ${hasConn ? '✅' : '❌'}`);
}

// Verificar que existen los nodos nuevos
console.log('\n=== NODOS NUEVOS ===');
const newNodes = ['Parse Reschedule Event', 'Delete For Reschedule', 'Send Reschedule Cancelled', 'Set Reschedule Data', 'Clear Reschedule States', 'Clear Reschedule Search'];
for (const name of newNodes) {
    const node = j.nodes.find(n => n.name === name);
    console.log(`${name}: ${node ? '✅ Existe' : '❌ NO EXISTE'}`);
}

// Verificar Button Action output 6 -> Get Reschedule Event
console.log('\n=== BUTTON ACTION OUTPUT 6 ===');
const buttonActionConn = j.connections['Button Action'];
if (buttonActionConn && buttonActionConn.main && buttonActionConn.main[6]) {
    console.log('Output 6 va a:', buttonActionConn.main[6].map(c => c.node).join(', '));
}

// También verificar el flujo desde Get Appointments Config hacia adelante
console.log('\n=== FLUJO BOOKING NORMAL ===');
const bookingFlow = ['Get Appointments Config', 'Generate Days', 'Send Calendar Buttons'];
for (let i = 0; i < bookingFlow.length - 1; i++) {
    const from = bookingFlow[i];
    const to = bookingFlow[i + 1];
    const conn = j.connections[from];
    const hasConn = conn && conn.main && conn.main[0] && conn.main[0].some(c => c.node === to);
    console.log(`${from} -> ${to}: ${hasConn ? '✅' : '❌'}`);
}
