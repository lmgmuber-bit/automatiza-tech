const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver Parse Reschedule Data
const parseReschedule = j.nodes.find(n => n.name === 'Parse Reschedule Data');
console.log('=== Parse Reschedule Data ===');
console.log('Type:', parseReschedule?.type);
console.log('jsCode:');
console.log(parseReschedule?.parameters?.jsCode);

// Ver conexiones
const prdConns = j.connections['Parse Reschedule Data'];
console.log('\nConecta a:', prdConns?.main?.[0]?.map(c => c.node));

console.log('\n' + '='.repeat(50));

// Ver Parse Cancel Data
const parseCancel = j.nodes.find(n => n.name === 'Parse Cancel Data');
console.log('\n=== Parse Cancel Data ===');
console.log('Type:', parseCancel?.type);
console.log('jsCode:');
console.log(parseCancel?.parameters?.jsCode);

// Ver conexiones
const pcdConns = j.connections['Parse Cancel Data'];
console.log('\nConecta a:', pcdConns?.main?.[0]?.map(c => c.node));
