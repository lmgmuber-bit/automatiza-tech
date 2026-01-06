const fs = require('fs');
const path = process.argv[2] || 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

console.log('Leyendo:', path);
let content = fs.readFileSync(path, 'utf8');

// Arreglos usando String.fromCharCode para evitar corrupción
const replacements = [
  // Mayúsculas con tilde corruptas
  ['\u00c3\u0093', '\u00d3'],  // Ó
  ['\u00c3\u0089', '\u00c9'],  // É
  ['\u00c3\u008d', '\u00cd'],  // Í
  ['\u00c3\u009a', '\u00da'],  // Ú
  ['\u00c3\u0091', '\u00d1'],  // Ñ
  ['\u00c3\u0081', '\u00c1'],  // Á
  // Minúsculas 
  ['\u00c3\u00a1', '\u00e1'],  // á
  ['\u00c3\u00a9', '\u00e9'],  // é
  ['\u00c3\u00ad', '\u00ed'],  // í
  ['\u00c3\u00b3', '\u00f3'],  // ó
  ['\u00c3\u00ba', '\u00fa'],  // ú
  ['\u00c3\u00b1', '\u00f1'],  // ñ
  // Signos
  ['\u00c2\u00bf', '\u00bf'],  // ¿
  ['\u00c2\u00a1', '\u00a1'],  // ¡
  // Emojis corruptos (UTF-8 double encoded)
  ['\u00e2\u0086\u0092', '\u2192'],  // →
  ['\u00e2\u009a\u00a0\u00ef\u00b8\u008f', '\u26a0\ufe0f'],  // ⚠️
  ['\u00e2\u009c\u0085', '\u2705'],  // ✅
  ['\u00e2\u009d\u008c', '\u274c'],  // ❌
  ['\u00c3\u00b0\u0178\u00a4\u0096', '\ud83e\udd16'],  // 🤖
  ['\u00c3\u00b0\u0178\u201c\u0085', '\ud83d\udcc5'],  // 📅
];

let count = 0;
for (const [bad, good] of replacements) {
  if (content.includes(bad)) {
    const matches = content.split(bad).length - 1;
    count += matches;
    content = content.split(bad).join(good);
    console.log(`  Reemplazado: ${matches}x`);
  }
}

// Arreglos directos con strings (ya verificados en archivo)
const directFixes = {
  'Ã"': 'Ó',
  'â†'': '→',
  'âš ï¸': '⚠️',
};

for (const [bad, good] of Object.entries(directFixes)) {
  if (content.includes(bad)) {
    const matches = content.split(bad).length - 1;
    count += matches;
    content = content.split(bad).join(good);
    console.log(`  Directo: ${bad} -> ${good} (${matches}x)`);
  }
}

fs.writeFileSync(path, content, 'utf8');
console.log(`Total reemplazos: ${count}`);

// Verificación
const check = fs.readFileSync(path, 'utf8');
console.log('\nVerificación:');
console.log('- SUSCRIPCIÓN:', check.includes('SUSCRIPCIÓN') ? '✓' : '✗');
console.log('- COMUNICACIÓN:', check.includes('COMUNICACIÓN') ? '✓' : '✗');
console.log('- reunión:', check.includes('reunión') ? '✓' : '✗');
