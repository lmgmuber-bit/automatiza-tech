const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Ver Parse Custom Date
const parseCustom = j.nodes.find(n => n.name === 'Parse Custom Date');
console.log('=== Parse Custom Date ===');
console.log(parseCustom?.parameters?.jsCode);
