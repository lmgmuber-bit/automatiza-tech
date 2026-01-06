const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// TTL de 10 minutos en segundos
const TTL_SECONDS = 600;

// Nodos Redis SET que necesitan TTL
const setNodes = [
    'Save Booking State',
    'Save Name', 
    'Set Date State',
    'Save Custom Booking State',
    'Save Reschedule State',
    'Save Event To Delete',
    'Save Cancel State',
    'Save Cancel Event'
];

// Agregar TTL a todos los nodos SET
setNodes.forEach(nodeName => {
    const idx = j.nodes.findIndex(n => n.name === nodeName);
    if (idx >= 0) {
        // Agregar expireTime a options
        if (!j.nodes[idx].parameters.options) {
            j.nodes[idx].parameters.options = {};
        }
        j.nodes[idx].parameters.options.expireTime = TTL_SECONDS;
        console.log(`✅ ${nodeName} - TTL de ${TTL_SECONDS}s agregado`);
    } else {
        console.log(`⚠️ ${nodeName} - No encontrado`);
    }
});

// Verificar que Clear Cancel State y Clear Cancel Confirmed se ejecuten
// Buscar las conexiones de Send Cancel Success
const cancelSuccessConns = j.connections['Send Cancel Success'];
if (cancelSuccessConns) {
    console.log('\n📋 Conexiones de Send Cancel Success:', JSON.stringify(cancelSuccessConns, null, 2));
} else {
    console.log('\n⚠️ Send Cancel Success no tiene conexiones de salida');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
