const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let c = fs.readFileSync(filePath, 'utf8');

// Fix warning emoji
const badWarning = '\u00e2\u0161\u0020\u00ef\u00b8\u008f';
const goodWarning = '\u26a0\ufe0f';

// Fix arrow
const badArrow = '\u00e2\u0086\u2019';
const goodArrow = '\u2192';

console.log('Warning matches:', (c.match(/\u00e2\u0161/g) || []).length);
console.log('Arrow matches:', (c.match(/\u00e2\u0086\u2019/g) || []).length);

// Replace the corrupted warning emoji pattern
c = c.replace(/\u00e2\u0161\s*\u00ef\u00b8\u008f/g, '\u26a0\ufe0f');

// Replace corrupted arrow
c = c.replace(/\u00e2\u0086\u2019/g, '\u2192');

fs.writeFileSync(filePath, c, 'utf8');
console.log('Guardado');

// Verify
const check = fs.readFileSync(filePath, 'utf8');
console.log('Contains warning emoji:', check.includes('\u26a0'));
