const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Buscar Check Appointment Found
const checkFound = j.nodes.find(n => n.name === 'Check Appointment Found');
console.log('=== Check Appointment Found ===');
console.log('Type:', checkFound?.type);
console.log('Params:', JSON.stringify(checkFound?.parameters, null, 2));

// Ver conexiones
const cafConns = j.connections['Check Appointment Found'];
console.log('\nConexiones:');
cafConns?.main?.forEach((arr, i) => {
    console.log(`  Output ${i}:`, arr.map(c => c.node));
});

// Ver qué conecta A Check Appointment Found
console.log('\n=== Qué conecta a Check Appointment Found ===');
for (let [name, conns] of Object.entries(j.connections)) {
    if (conns.main) {
        conns.main.forEach((arr, i) => {
            arr.forEach(c => {
                if (c.node === 'Check Appointment Found') {
                    console.log(`${name} (output ${i})`);
                }
            });
        });
    }
}

// Verificar el flujo de reprogramación completo
console.log('\n=== FLUJO REPROGRAMACIÓN ===');
// Parse Reschedule Data -> Check Parse Status -> Search User Appointment -> Process Search Results -> Check Appointment Found

const searchUser = j.nodes.find(n => n.name === 'Search User Appointment');
console.log('\nSearch User Appointment:');
console.log('URL:', searchUser?.parameters?.url);

// Ver Process Search Results qué pasa a Check Appointment Found
const processSearch = j.nodes.find(n => n.name === 'Process Search Results');
console.log('\nProcess Search Results retorna:');
console.log('  - found: true/false');
console.log('  - appointmentId, eventId, summary, start, email, clientName');
