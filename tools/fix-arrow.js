const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let c = fs.readFileSync(filePath, 'utf8');

// Fix arrow: â†' -> →
const badArrow = '\u00e2\u2020\u2019';
const goodArrow = '\u2192';

const count = (c.match(new RegExp(badArrow, 'g')) || []).length;
console.log('Arrow matches:', count);

c = c.split(badArrow).join(goodArrow);

fs.writeFileSync(filePath, c, 'utf8');
console.log('Guardado');

// Verify
const check = fs.readFileSync(filePath, 'utf8');
console.log('Contains good arrow:', check.includes('\u2192'));
console.log('Sample:', check.substring(check.indexOf('SHOW_CALENDAR')-30, check.indexOf('SHOW_CALENDAR')+20));
