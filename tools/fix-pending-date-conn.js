const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver las conexiones de Process Text
console.log('=== CONEXIONES Process Text ===');
const ptConns = j.connections['Process Text'];
console.log(JSON.stringify(ptConns, null, 2));

// Agregar conexión a Check Pending Date si no existe
let hasCheckPendingDate = false;
if (ptConns?.main?.[0]) {
    hasCheckPendingDate = ptConns.main[0].some(c => c.node === 'Check Pending Date');
}

if (!hasCheckPendingDate) {
    console.log('\n❌ Falta conexión Process Text -> Check Pending Date');
    console.log('Agregando...');
    
    if (!ptConns.main[0]) ptConns.main[0] = [];
    ptConns.main[0].push({
        node: 'Check Pending Date',
        type: 'main',
        index: 0
    });
    console.log('✅ Conexión agregada');
} else {
    console.log('\n✅ Ya existe conexión a Check Pending Date');
}

// Ver las conexiones de Check Pending Date
console.log('\n=== CONEXIONES Check Pending Date ===');
const cpConns = j.connections['Check Pending Date'];
console.log(JSON.stringify(cpConns, null, 2));

// Check Pending Date debe ir a Check Booking Status (junto con Check Pending Booking)
// Ver qué nodo procesa estos resultados
console.log('\n=== Buscando nodo que recibe Check Pending Date ===');
for (let [name, conns] of Object.entries(j.connections)) {
    if (conns.main) {
        conns.main.forEach((arr, i) => {
            arr.forEach(c => {
                if (c.node === 'Check Booking Status') {
                    console.log(`${name} -> Check Booking Status`);
                }
            });
        });
    }
}

// Ver Check Booking Status
const cbs = j.nodes.find(n => n.name === 'Check Booking Status');
console.log('\n=== Check Booking Status jsCode ===');
if (cbs?.parameters?.jsCode) {
    const code = cbs.parameters.jsCode;
    console.log('Primeras 800 chars:');
    console.log(code.substring(0, 800));
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');
