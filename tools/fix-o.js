const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let c = fs.readFileSync(filePath, 'utf8');

const badO = '\u00c3\u201c';
const goodO = '\u00d3';

const count1 = (c.match(new RegExp(badO, 'g')) || []).length;
console.log('Ocurrencias de O corrupta:', count1);

c = c.split(badO).join(goodO);

fs.writeFileSync(filePath, c, 'utf8');

const check = fs.readFileSync(filePath, 'utf8');
console.log('SUSCRIPCION OK:', check.includes('SUSCRIPCI\u00d3N'));
console.log('COMUNICACION OK:', check.includes('COMUNICACI\u00d3N'));
