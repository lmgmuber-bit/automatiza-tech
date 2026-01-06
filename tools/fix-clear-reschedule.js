const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Buscar el nodo fuente correcto para Reschedule
// Clear Reschedule State viene después de Delete Old Appointment
// Necesito ver el flujo

// Ver qué nodo conecta a Clear Reschedule State
for (let [name, conns] of Object.entries(j.connections)) {
    if (conns.main) {
        conns.main.forEach((arr, i) => {
            arr.forEach(c => {
                if (c.node === 'Clear Reschedule State') {
                    console.log(name, 'output', i, '-> Clear Reschedule State');
                }
            });
        });
    }
}

// Corregir Clear Reschedule State
const clearRescheduleIdx = j.nodes.findIndex(n => n.name === 'Clear Reschedule State');
if (clearRescheduleIdx >= 0) {
    // Obtener de Process Interactive que tiene phoneNumber
    j.nodes[clearRescheduleIdx].parameters.key = "=reschedule_{{ $('Process Interactive').first().json.phoneNumber }}";
    console.log('✅ Clear Reschedule State corregido');
}

// Corregir Clear Confirmed State
const clearConfStateIdx = j.nodes.findIndex(n => n.name === 'Clear Confirmed State');
if (clearConfStateIdx >= 0) {
    j.nodes[clearConfStateIdx].parameters.key = "=reschedule_confirmed_{{ $('Process Interactive').first().json.phoneNumber }}";
    console.log('✅ Clear Confirmed State corregido');
}

// Verificar Send Keep Appointment - debe limpiar estados de cancel
const keepAppIdx = j.nodes.findIndex(n => n.name === 'Send Keep Appointment');
if (keepAppIdx >= 0) {
    console.log('Send Keep Appointment existe');
}

// Ver conexiones de Send Keep Appointment
const keepConns = j.connections['Send Keep Appointment'];
if (keepConns) {
    console.log('Send Keep Appointment conexiones:', JSON.stringify(keepConns, null, 2));
}

// Agregar conexión de Send Keep Appointment a Clear Cancel State si no existe
// Primero verifico si ya existe
let hasConnection = false;
if (keepConns && keepConns.main && keepConns.main[0]) {
    hasConnection = keepConns.main[0].some(c => c.node === 'Clear Cancel State');
}

if (!hasConnection) {
    // Agregar conexión
    if (!j.connections['Send Keep Appointment']) {
        j.connections['Send Keep Appointment'] = { main: [[]] };
    }
    if (!j.connections['Send Keep Appointment'].main) {
        j.connections['Send Keep Appointment'].main = [[]];
    }
    if (!j.connections['Send Keep Appointment'].main[0]) {
        j.connections['Send Keep Appointment'].main[0] = [];
    }
    j.connections['Send Keep Appointment'].main[0].push({
        node: 'Clear Cancel State',
        type: 'main',
        index: 0
    });
    console.log('✅ Conexión Send Keep Appointment -> Clear Cancel State agregada');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
