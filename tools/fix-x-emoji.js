const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let c = fs.readFileSync(filePath, 'utf8');

// Fix X emoji: âŒ -> ❌
// Pattern found: \u00e2\u009d\u0152
const badX = '\u00e2\u009d\u0152';
const goodX = '\u274c';

const count = (c.match(new RegExp(badX, 'g')) || []).length;
console.log('X emoji matches:', count);

c = c.split(badX).join(goodX);

// Also fix checkmark if corrupted
const badCheck = '\u00e2\u0153\u0085';
const goodCheck = '\u2705';
const count2 = (c.match(new RegExp(badCheck, 'g')) || []).length;
if (count2 > 0) {
  console.log('Check emoji matches:', count2);
  c = c.split(badCheck).join(goodCheck);
}

fs.writeFileSync(filePath, c, 'utf8');
console.log('Guardado');

// Verify
const check = fs.readFileSync(filePath, 'utf8');
console.log('Contains good X:', check.includes('\u274c'));
