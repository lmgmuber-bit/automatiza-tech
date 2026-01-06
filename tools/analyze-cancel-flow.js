const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Encontrar el flujo de cancelación (Output 8: btn_confirmar_cancelar -> Get Cancel Event)
console.log('=== FLUJO DE ANULACIÓN ACTUAL ===\n');

// Seguir el flujo desde Get Cancel Event
const follow = (nodeName, depth = 0) => {
    const indent = '  '.repeat(depth);
    const conn = j.connections[nodeName];
    if (!conn || !conn.main) {
        console.log(`${indent}${nodeName} -> (fin)`);
        return;
    }
    
    const targets = conn.main.flat().filter(c => c && c.node);
    if (targets.length === 0) {
        console.log(`${indent}${nodeName} -> (fin)`);
        return;
    }
    
    for (const t of targets) {
        console.log(`${indent}${nodeName} -> ${t.node}`);
        if (depth < 6) { // Limitar profundidad
            follow(t.node, depth + 1);
        }
    }
};

follow('Get Cancel Event');

// Ver qué nodo envía la confirmación de cancelación
console.log('\n=== NODOS DE CONFIRMACIÓN DE CANCELACIÓN ===');
const cancelNodes = j.nodes.filter(n => 
    n.name.toLowerCase().includes('cancel') && 
    (n.name.toLowerCase().includes('confirm') || n.name.toLowerCase().includes('success') || n.name.toLowerCase().includes('send'))
);

for (const node of cancelNodes) {
    console.log(`\n${node.name} (${node.type}):`);
    if (node.parameters.jsonBody) {
        const bodyPreview = node.parameters.jsonBody.substring(0, 200);
        console.log(`  Body preview: ${bodyPreview}...`);
    }
}
