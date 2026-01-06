const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver Merge Booking Data
const mergeNode = j.nodes.find(n => n.name === 'Merge Booking Data');
console.log('=== Merge Booking Data ===');
console.log('Tipo:', mergeNode?.type);
console.log('Parámetros:', JSON.stringify(mergeNode?.parameters, null, 2));

// Ver qué conecta a Merge Booking Data
console.log('\n=== Conexiones HACIA Merge Booking Data ===');
for (let [name, conns] of Object.entries(j.connections)) {
    if (conns.main) {
        conns.main.forEach((arr, i) => {
            arr.forEach(c => {
                if (c.node === 'Merge Booking Data') {
                    console.log(`${name} (output ${i}) -> Merge Booking Data (input ${c.index})`);
                }
            });
        });
    }
}

// Ver conexiones de Merge Booking Data
console.log('\n=== Conexiones DESDE Merge Booking Data ===');
const mergeConns = j.connections['Merge Booking Data'];
console.log(JSON.stringify(mergeConns, null, 2));

// Ver Check Booking Status
const checkBS = j.nodes.find(n => n.name === 'Check Booking Status');
console.log('\n=== Check Booking Status ===');
if (checkBS?.parameters?.jsCode) {
    // Ver si procesa pendingDate
    const code = checkBS.parameters.jsCode;
    if (code.includes('pendingDate')) {
        console.log('✅ Procesa pendingDate');
        // Mostrar la parte relevante
        const lines = code.split('\n');
        let inPendingSection = false;
        lines.forEach((line, i) => {
            if (line.includes('pendingDate') || line.includes('pending')) {
                console.log(`L${i+1}: ${line}`);
            }
        });
    } else {
        console.log('❌ NO procesa pendingDate');
    }
}

// Check Pending Booking qué devuelve
const checkPB = j.nodes.find(n => n.name === 'Check Pending Booking');
console.log('\n=== Check Pending Booking ===');
console.log('Key:', checkPB?.parameters?.key);
console.log('Property Name:', checkPB?.parameters?.propertyName);

// Check Pending Date qué devuelve
const checkPD = j.nodes.find(n => n.name === 'Check Pending Date');
console.log('\n=== Check Pending Date ===');
console.log('Key:', checkPD?.parameters?.key);
console.log('Property Name:', checkPD?.parameters?.propertyName);
