const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// El problema: Generate Days no está obteniendo phoneNumber/phoneNumberId
// Porque Process Search Results tampoco lo tiene en su output actual
// O porque el try/catch está fallando silenciosamente

// Ver el flujo de reprogramación completo
console.log('=== FLUJO DE DATOS PARA REPROGRAMACIÓN ===\n');

// 1. Show Confirmation Buttons - usuario confirma su cita
// 2. Save Event To Delete - guarda datos en Redis
// 3. Get Reschedule Event - lee de Redis cuando usuario presiona "Confirmar"
// 4. Get Appointments Config - obtiene config
// 5. Generate Days - genera días disponibles
// 6. Send Calendar Buttons - envía opciones

// El problema: cuando viene de Get Reschedule Event -> Get Appointments Config -> Generate Days
// Generate Days no puede acceder a Process Search Results porque no está en ese path

// Ver Get Reschedule Event
const getReschedule = j.nodes.find(n => n.name === 'Get Reschedule Event');
console.log('Get Reschedule Event:');
console.log('  Key:', getReschedule?.parameters?.key);
console.log('  PropertyName:', getReschedule?.parameters?.propertyName);

// Ver conexiones de Get Reschedule Event
const greConns = j.connections['Get Reschedule Event'];
console.log('  Conecta a:', greConns?.main?.[0]?.map(c => c.node));

// Ver qué hay entre Get Reschedule Event y Generate Days
// Get Reschedule Event -> ??? -> Get Appointments Config -> Generate Days

console.log('\n=== Conexiones hacia Generate Days ===');
for (let [name, conns] of Object.entries(j.connections)) {
    if (conns.main) {
        conns.main.forEach((arr, i) => {
            arr.forEach(c => {
                if (c.node === 'Generate Days') {
                    console.log(`${name} -> Generate Days`);
                }
            });
        });
    }
}

// Ver Get Appointments Config conexiones
const gacConns = j.connections['Get Appointments Config'];
console.log('\nGet Appointments Config conecta a:', gacConns?.main?.[0]?.map(c => c.node));

// Ver qué conecta A Get Appointments Config
console.log('\n=== Qué conecta a Get Appointments Config ===');
for (let [name, conns] of Object.entries(j.connections)) {
    if (conns.main) {
        conns.main.forEach((arr, i) => {
            arr.forEach(c => {
                if (c.node === 'Get Appointments Config') {
                    console.log(`${name} -> Get Appointments Config`);
                }
            });
        });
    }
}
