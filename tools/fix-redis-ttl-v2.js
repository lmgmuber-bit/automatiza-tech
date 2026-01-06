const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Asegurar TTL en todos los nodos Redis SET
const redisSetNodes = [
    'Save Booking State', 'Save Name', 'Set Date State', 'Save Custom Booking State',
    'Save Reschedule State', 'Save Event To Delete', 'Save Cancel State', 'Save Cancel Event'
];

console.log('Aplicando TTL a nodos Redis SET...\n');

redisSetNodes.forEach(name => {
    const idx = j.nodes.findIndex(n => n.name === name);
    if (idx >= 0) {
        const node = j.nodes[idx];
        
        // Asegurar estructura options
        if (!node.parameters.options) {
            node.parameters.options = {};
        }
        
        // Establecer TTL de 600 segundos (10 minutos)
        node.parameters.options.expireTime = 600;
        
        // También agregar expire y ttl por si la versión de N8N los usa
        node.parameters.expire = true;
        node.parameters.ttl = 600;
        
        console.log(`✅ ${name}: options.expireTime=600, expire=true, ttl=600`);
    } else {
        console.log(`❌ ${name}: NO ENCONTRADO`);
    }
});

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

// Verificar
const j2 = JSON.parse(fs.readFileSync(path, 'utf8'));
console.log('\nVerificación:');
redisSetNodes.forEach(name => {
    const node = j2.nodes.find(n => n.name === name);
    if (node) {
        const ttl = node.parameters.options?.expireTime || node.parameters.ttl;
        console.log(`  ${name}: TTL=${ttl}s`);
    }
});
