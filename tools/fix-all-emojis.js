const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let c = fs.readFileSync(filePath, 'utf8');

// Lista de emojis corruptos y sus correcciones
const emojiFixMap = [
  // Calendar 📅
  ['\u00f0\u0178\u201c\u0085', '\ud83d\udcc5'],
  // Clock ⏰
  ['\u00e2\u0178\u00b0', '\u23f0'],
  ['\u00e2\u008f\u00b0', '\u23f0'],
  // Sparkles ✨
  ['\u00e2\u0153\u00a8', '\u2728'],
  ['\u00e2\u009c\u00a8', '\u2728'],
  // Person 👤
  ['\u00f0\u0178\u2019\u00a4', '\ud83d\udc64'],
  // Email 📧
  ['\u00f0\u0178\u201c\u00a7', '\ud83d\udce7'],
  // Briefcase 💼
  ['\u00f0\u0178\u2019\u00bc', '\ud83d\udcbc'],
  // Robot 🤖
  ['\u00f0\u0178\u00a4\u0096', '\ud83e\udd16'],
  // Thinking 🤔
  ['\u00f0\u0178\u00a4\u201d', '\ud83e\udd14'],
  // Wave 👋
  ['\u00f0\u0178\u2019\u2039', '\ud83d\udc4b'],
  // Party 🎉
  ['\u00f0\u0178\u0178\u2030', '\ud83c\udf89'],
  // Check ✅
  ['\u00e2\u0153\u0085', '\u2705'],
  ['\u00e2\u009c\u0085', '\u2705'],
  // X ❌
  ['\u00e2\u009d\u0152', '\u274c'],
  // Warning ⚠️
  ['\u00e2\u0161\u0020', '\u26a0'],
  // Arrow →
  ['\u00e2\u2020\u2019', '\u2192'],
  // Bullet •
  ['\u00e2\u20ac\u00a2', '\u2022'],
];

let totalFixes = 0;

for (const [bad, good] of emojiFixMap) {
  const count = (c.split(bad).length - 1);
  if (count > 0) {
    console.log(`Fixing: ${count}x`);
    c = c.split(bad).join(good);
    totalFixes += count;
  }
}

// También buscar patrones más genéricos de emojis corruptos
// Patrón: ðŸ seguido de caracteres
const genericPatterns = [
  // Más variantes de calendario
  ['ðŸ"…', '📅'],
  ['ðŸ'¼', '💼'],
  ['ðŸ"§', '📧'],
  ['ðŸ'¤', '👤'],
  ['ðŸ¤–', '🤖'],
  ['ðŸ¤"', '🤔'],
  ['ðŸ'‹', '👋'],
  ['ðŸŽ‰', '🎉'],
  // Reloj y otros
  ['â°', '⏰'],
  ['âœ¨', '✨'],
  ['âœ…', '✅'],
  ['âŒ', '❌'],
  ['â ï¸', '⚠️'],
  ['â†'', '→'],
];

for (const [bad, good] of genericPatterns) {
  const count = (c.split(bad).length - 1);
  if (count > 0) {
    console.log(`Fixing generic: "${bad}" -> "${good}" (${count}x)`);
    c = c.split(bad).join(good);
    totalFixes += count;
  }
}

fs.writeFileSync(filePath, c, 'utf8');
console.log(`\nTotal fixes: ${totalFixes}`);

// Verificación
const check = fs.readFileSync(filePath, 'utf8');
console.log('\nVerificación:');
console.log('Contiene 📅:', check.includes('\ud83d\udcc5') || check.includes('📅'));
console.log('Contiene ⏰:', check.includes('\u23f0') || check.includes('⏰'));
console.log('Contiene ✨:', check.includes('\u2728') || check.includes('✨'));
console.log('Contiene 👤:', check.includes('\ud83d\udc64') || check.includes('👤'));
console.log('Contiene 📧:', check.includes('\ud83d\udce7') || check.includes('📧'));
