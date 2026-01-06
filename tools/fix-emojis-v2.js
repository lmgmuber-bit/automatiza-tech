const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let c = fs.readFileSync(filePath, 'utf8');

// Lista de emojis corruptos usando solo códigos Unicode
const emojiFixMap = [
  // Calendar - varias variantes
  ['\u00f0\u0178\u201c\u0085', '\uD83D\uDCC5'],
  ['\u00c3\u00b0\u0178\u201c\u0085', '\uD83D\uDCC5'],
  // Clock
  ['\u00e2\u008f\u00b0', '\u23F0'],
  ['\u00e2\u0178\u00b0', '\u23F0'],
  // Sparkles
  ['\u00e2\u009c\u00a8', '\u2728'],
  ['\u00e2\u0153\u00a8', '\u2728'],
  // Person
  ['\u00f0\u0178\u2019\u00a4', '\uD83D\uDC64'],
  ['\u00c3\u00b0\u0178\u2019\u00a4', '\uD83D\uDC64'],
  // Email
  ['\u00f0\u0178\u201c\u00a7', '\uD83D\uDCE7'],
  ['\u00c3\u00b0\u0178\u201c\u00a7', '\uD83D\uDCE7'],
  // Briefcase
  ['\u00f0\u0178\u2019\u00bc', '\uD83D\uDCBC'],
  // Check
  ['\u00e2\u009c\u0085', '\u2705'],
  ['\u00e2\u0153\u0085', '\u2705'],
  // X
  ['\u00e2\u009d\u0152', '\u274C'],
  // Warning
  ['\u00e2\u0161\u0020\u00ef\u00b8\u008f', '\u26A0\uFE0F'],
  // Arrow
  ['\u00e2\u2020\u2019', '\u2192'],
];

let totalFixes = 0;

for (const [bad, good] of emojiFixMap) {
  const count = (c.split(bad).length - 1);
  if (count > 0) {
    console.log('Fixing:', count, 'occurrences');
    c = c.split(bad).join(good);
    totalFixes += count;
  }
}

fs.writeFileSync(filePath, c, 'utf8');
console.log('Total fixes:', totalFixes);

// Verificar que el JSON es válido
try {
  JSON.parse(c);
  console.log('JSON: valid');
} catch (e) {
  console.log('JSON error:', e.message);
}
