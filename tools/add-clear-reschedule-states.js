const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Agregar nodo para limpiar estados de reschedule
// Debe ir ANTES de Get Appointments Config

// Crear Clear Reschedule States
const clearRescheduleStatesNode = {
    parameters: {
        operation: "delete",
        key: "=reschedule_confirmed_{{ $('Parse Reschedule Event').first().json.phoneNumber }}"
    },
    type: "n8n-nodes-base.redis",
    typeVersion: 1,
    position: [119900, 42420],
    id: "clear-reschedule-states-new",
    name: "Clear Reschedule States",
    credentials: {
        redis: {
            id: "jQapHy4VhpuQIcJY",
            name: "Redis32"
        }
    },
    onError: "continueRegularOutput"
};

j.nodes.push(clearRescheduleStatesNode);
console.log('✅ Clear Reschedule States agregado');

// Ahora modificar la conexión de Set Reschedule Data
// Debe ir a Clear Reschedule States Y a Get Appointments Config en paralelo
// O bien secuencial: Set Reschedule Data -> Clear Reschedule States -> Get Appointments Config

// Opción secuencial para evitar problemas:
j.connections['Set Reschedule Data'] = {
    main: [[{ node: 'Clear Reschedule States', type: 'main', index: 0 }]]
};

j.connections['Clear Reschedule States'] = {
    main: [[{ node: 'Get Appointments Config', type: 'main', index: 0 }]]
};

console.log('✅ Conexión actualizada: Set Reschedule Data -> Clear Reschedule States -> Get Appointments Config');

// Limpiar también reschedule_search si existe
const clearRescheduleSearchNode = {
    parameters: {
        operation: "delete",
        key: "=reschedule_search_{{ $('Parse Reschedule Event').first().json.phoneNumber }}"
    },
    type: "n8n-nodes-base.redis",
    typeVersion: 1,
    position: [119900, 42560],
    id: "clear-reschedule-search-new",
    name: "Clear Reschedule Search",
    credentials: {
        redis: {
            id: "jQapHy4VhpuQIcJY",
            name: "Redis32"
        }
    },
    onError: "continueRegularOutput"
};

j.nodes.push(clearRescheduleSearchNode);
console.log('✅ Clear Reschedule Search agregado');

// Hacer que Set Reschedule Data vaya a ambos Clear en paralelo, y luego ambos a Get Appointments Config
// Pero es más simple hacerlo secuencial para evitar problemas de merge

// Set Reschedule Data -> Clear Reschedule States -> Clear Reschedule Search -> Get Appointments Config
j.connections['Set Reschedule Data'] = {
    main: [[{ node: 'Clear Reschedule States', type: 'main', index: 0 }]]
};

j.connections['Clear Reschedule States'] = {
    main: [[{ node: 'Clear Reschedule Search', type: 'main', index: 0 }]]
};

j.connections['Clear Reschedule Search'] = {
    main: [[{ node: 'Get Appointments Config', type: 'main', index: 0 }]]
};

console.log('✅ Conexiones finales actualizadas');

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}

console.log('\n=== FLUJO FINAL ===');
console.log('btn_confirmar_reprogramar ->');
console.log('  Get Reschedule Event ->');
console.log('  Parse Reschedule Event ->');
console.log('  Delete For Reschedule ->');
console.log('  Send Reschedule Cancelled ->');
console.log('  Set Reschedule Data ->');
console.log('  Clear Reschedule States ->');
console.log('  Clear Reschedule Search ->');
console.log('  Get Appointments Config ->');
console.log('  Generate Days -> ... flujo booking normal');
