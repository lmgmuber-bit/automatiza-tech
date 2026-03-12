const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Buscar Generate Days
const generateDays = j.nodes.find(n => n.name === 'Generate Days');
console.log('=== Generate Days ===');
console.log('jsCode:');
console.log(generateDays?.parameters?.jsCode);

// Ver qué conecta A Generate Days
console.log('\n=== Qué conecta a Generate Days ===');
for (let [name, conns] of Object.entries(j.connections)) {
    if (conns.main) {
        conns.main.forEach((arr, i) => {
            arr.forEach(c => {
                if (c.node === 'Generate Days') {
                    console.log(`${name} (output ${i})`);
                }
            });
        });
    }
}
