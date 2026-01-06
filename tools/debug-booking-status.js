const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver nodo Booking Status
const bookingStatus = j.nodes.find(n => n.name === 'Booking Status');
console.log('=== Booking Status (Switch) ===');
console.log('Tipo:', bookingStatus?.type);

// Ver las reglas del switch
if (bookingStatus?.parameters?.rules?.rules) {
    bookingStatus.parameters.rules.rules.forEach((rule, i) => {
        console.log(`\nOutput ${i}: ${rule.outputKey || 'default'}`);
        if (rule.conditions?.conditions) {
            rule.conditions.conditions.forEach(cond => {
                console.log(`  Condición: ${cond.leftValue} ${cond.operator} ${cond.rightValue}`);
            });
        }
    });
}

// Ver las conexiones de Booking Status
console.log('\n=== Conexiones de Booking Status ===');
const bsConns = j.connections['Booking Status'];
if (bsConns?.main) {
    bsConns.main.forEach((arr, i) => {
        console.log(`Output ${i}:`, arr.map(c => c.node));
    });
}
