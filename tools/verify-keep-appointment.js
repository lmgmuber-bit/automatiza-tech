const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Verificar Send Keep Appointment
const node = j.nodes.find(n => n.name === 'Send Keep Appointment');
if (node) {
    console.log('=== Send Keep Appointment ===');
    console.log('to:', node.parameters.to);
    console.log('phoneNumberId:', node.parameters.phoneNumberId);
    console.log('textBody:', node.parameters.textBody?.substring(0, 200));
}

// Verificar Check Cancel Confirmation Status que provee datos
const checkNode = j.nodes.find(n => n.name === 'Check Cancel Confirmation Status');
if (checkNode) {
    console.log('\n=== Check Cancel Confirmation Status ===');
    console.log('Es el nodo correcto a referenciar para Send Keep Appointment');
}

// Ver qué conecta a Send Keep Appointment
console.log('\n=== Conexiones hacia Send Keep Appointment ===');
for (let [name, conns] of Object.entries(j.connections)) {
    if (conns.main) {
        conns.main.forEach((arr, i) => {
            arr.forEach(c => {
                if (c.node === 'Send Keep Appointment') {
                    console.log(`${name} output ${i} -> Send Keep Appointment`);
                }
            });
        });
    }
}

// Verificar Parse Cancel Event
const parseCancel = j.nodes.find(n => n.name === 'Parse Cancel Event');
if (parseCancel) {
    console.log('\n=== Parse Cancel Event jsCode ===');
    console.log(parseCancel.parameters.jsCode);
}
