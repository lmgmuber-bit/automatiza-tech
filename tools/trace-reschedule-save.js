const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Buscar qué nodo conecta a Save Event To Delete
console.log('=== CONEXIONES A Save Event To Delete ===');
for (const [nodeName, conn] of Object.entries(j.connections)) {
    if (conn.main) {
        for (const outputs of conn.main) {
            if (outputs) {
                for (const c of outputs) {
                    if (c.node === 'Save Event To Delete') {
                        console.log(`${nodeName} -> Save Event To Delete`);
                    }
                }
            }
        }
    }
}

// Ver la cadena completa antes de Save Event To Delete
console.log('\n=== CADENA DE NODOS ===');
const findPredecessors = (nodeName, depth = 0) => {
    if (depth > 5) return;
    const indent = '  '.repeat(depth);
    
    for (const [name, conn] of Object.entries(j.connections)) {
        if (conn.main) {
            for (const outputs of conn.main) {
                if (outputs) {
                    for (const c of outputs) {
                        if (c.node === nodeName) {
                            console.log(`${indent}${name} -> ${nodeName}`);
                            findPredecessors(name, depth + 1);
                        }
                    }
                }
            }
        }
    }
};

findPredecessors('Save Event To Delete');

// Verificar el nodo anterior
console.log('\n=== Show Reschedule Confirmation ===');
const showReschedule = j.nodes.find(n => n.name === 'Show Reschedule Confirmation');
if (showReschedule) {
    console.log('Tipo:', showReschedule.type);
    if (showReschedule.parameters.jsonBody) {
        console.log('Body (preview):', showReschedule.parameters.jsonBody.substring(0, 300));
    }
}

// Buscar nodo que envía la confirmación "Encontré tu cita"
console.log('\n=== Show Confirmation Buttons ===');
const showConfirmation = j.nodes.find(n => n.name === 'Show Confirmation Buttons');
if (showConfirmation) {
    console.log('Tipo:', showConfirmation.type);
}

// Ver Save Event Data si existe
console.log('\n=== Save Event Data (si existe) ===');
const saveEventData = j.nodes.find(n => n.name === 'Save Event Data');
if (saveEventData) {
    console.log('Parameters:', JSON.stringify(saveEventData.parameters, null, 2));
}
