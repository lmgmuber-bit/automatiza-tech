const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Actualizar el nodo Send Cancel Email para usar Parse Cancel Event en lugar de Check Cancel Event Status
// porque Check Cancel Event Status es solo un filtro IF y puede no pasar todos los datos
const emailNode = j.nodes.find(n => n.name === 'Send Cancel Email');

if (emailNode) {
    emailNode.parameters.jsonBody = `={
  "to": "{{ $('Parse Cancel Event').first().json.email }}",
  "subject": "Lamentamos que hayas cancelado tu cita - AutomatizaTech",
  "template": "cancellation",
  "data": {
    "clientName": "{{ $('Parse Cancel Event').first().json.clientName }}",
    "summary": "{{ $('Parse Cancel Event').first().json.summary }}"
  }
}`;
    console.log('✅ Send Cancel Email actualizado para usar Parse Cancel Event');
} else {
    console.log('❌ No se encontró Send Cancel Email');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

// Verificar flujo completo de cancelación
console.log('\n=== VERIFICACIÓN FLUJO CANCELACIÓN ===');
const cancelFlow = [
    'Get Cancel Event',
    'Parse Cancel Event', 
    'Check Cancel Event Status',
    'Delete Cancelled Appointment',
    'Send Cancel Success',
    'Send Cancel Email',
    'Clear Cancel State',
    'Clear Cancel Confirmed'
];

for (let i = 0; i < cancelFlow.length - 1; i++) {
    const from = cancelFlow[i];
    const to = cancelFlow[i + 1];
    const conn = j.connections[from];
    const hasConn = conn && conn.main && conn.main.some(outputs => 
        outputs && outputs.some(c => c.node === to)
    );
    console.log(`${from} -> ${to}: ${hasConn ? '✅' : '❌'}`);
}
