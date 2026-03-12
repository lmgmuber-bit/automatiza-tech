const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let c = fs.readFileSync(filePath, 'utf8');

// Mapeo de patrones corruptos a emojis correctos
// Basado en los patrones encontrados
const emojiFixMap = [
  // f0 178 201c 2026 = 📆 o similar
  ['\u00f0\u0178\u201c\u2026', '\uD83D\uDCC6'], // 📆
  // f0 178 201c 2020 = 📅 
  ['\u00f0\u0178\u201c\u2020', '\uD83D\uDCC5'], // 📅
  // f0 178 201d a7 = 📧
  ['\u00f0\u0178\u201d\u00a7', '\uD83D\uDCE7'], // 📧
  // f0 178 2dc 2022 = 🗒 o similar
  ['\u00f0\u0178\u02dc\u2022', '\uD83D\uDDD2'], // 🗒
  // f0 178 2018 a4 = 👤
  ['\u00f0\u0178\u2018\u00a4', '\uD83D\uDC64'], // 👤
  // f0 178 2dc 2026 = 🗓
  ['\u00f0\u0178\u02dc\u2026', '\uD83D\uDDD3'], // 🗓
  // f0 178 201c 9d = 📝
  ['\u00f0\u0178\u201c\u009d', '\uD83D\uDCDD'], // 📝
];

let totalFixes = 0;

for (const [bad, good] of emojiFixMap) {
  const count = (c.split(bad).length - 1);
  if (count > 0) {
    console.log('Fixing:', count, 'x', good);
    c = c.split(bad).join(good);
    totalFixes += count;
  }
}

// Guardar
fs.writeFileSync(filePath, c, 'utf8');
console.log('Total fixes:', totalFixes);

// Verificar restantes
const remaining = c.match(/\u00f0\u0178/g);
console.log('Remaining corrupted:', remaining?.length || 0);

// JSON válido
try {
  JSON.parse(c);
  console.log('JSON: valid');
} catch (e) {
  console.log('JSON error:', e.message);
}
