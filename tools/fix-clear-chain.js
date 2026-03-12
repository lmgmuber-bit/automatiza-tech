const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver conexiones actuales de Clear Cancel State
const clearCancelConns = j.connections['Clear Cancel State'];
console.log('Clear Cancel State conecta a:', JSON.stringify(clearCancelConns, null, 2));

// Clear Cancel State debe conectar a Clear Cancel Confirmed para limpiar ambos estados
if (clearCancelConns && clearCancelConns.main && clearCancelConns.main[0]) {
    const hasConnection = clearCancelConns.main[0].some(c => c.node === 'Clear Cancel Confirmed');
    if (!hasConnection) {
        clearCancelConns.main[0].push({
            node: 'Clear Cancel Confirmed',
            type: 'main',
            index: 0
        });
        console.log('✅ Conexión Clear Cancel State -> Clear Cancel Confirmed agregada');
    } else {
        console.log('✅ Ya conecta a Clear Cancel Confirmed');
    }
} else {
    j.connections['Clear Cancel State'] = {
        main: [[{ node: 'Clear Cancel Confirmed', type: 'main', index: 0 }]]
    };
    console.log('✅ Conexión creada');
}

// Ver qué conecta a Clear Cancel State (para asegurar que Send Keep -> Clear Cancel)
console.log('\n=== Nodos que conectan a Clear Cancel State ===');
for (let [name, conns] of Object.entries(j.connections)) {
    if (conns.main) {
        conns.main.forEach((arr, i) => {
            arr.forEach(c => {
                if (c.node === 'Clear Cancel State') {
                    console.log(`${name} output ${i}`);
                }
            });
        });
    }
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
