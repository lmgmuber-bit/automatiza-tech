const fs = require('fs');
const path = process.argv[2] || 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

console.log('Leyendo:', path);
let content = fs.readFileSync(path, 'utf8');

// Mapa de caracteres UTF-8 mal interpretados como Latin-1
const replacements = {
  '\u00c3\u00a1': 'á',  // á
  '\u00c3\u00a9': 'é',  // é
  '\u00c3\u00ad': 'í',  // í
  '\u00c3\u00b3': 'ó',  // ó
  '\u00c3\u00ba': 'ú',  // ú
  '\u00c3\u00b1': 'ñ',  // ñ
  '\u00c3\u0081': 'Á',  // Á
  '\u00c3\u0089': 'É',  // É
  '\u00c3\u008d': 'Í',  // Í
  '\u00c3\u0093': 'Ó',  // Ó
  '\u00c3\u009a': 'Ú',  // Ú
  '\u00c3\u0091': 'Ñ',  // Ñ
  '\u00c2\u00bf': '¿',  // ¿
  '\u00c2\u00a1': '¡',  // ¡
  '\u00c3\u00bc': 'ü',  // ü
  // Mayúsculas con tilde en otra variante
  'Ã"': 'Ó',
  'Ã‰': 'É', 
  'Ã': 'Í',
  'Ãš': 'Ú',
  'Ã'': 'Ñ',
  // Emojis y símbolos corruptos
  'â†'': '→',
  'âš ï¸': '⚠️',
  'âœ…': '✅',
  'âŒ': '❌',
  'ðŸ¤–': '🤖',
  'ðŸ"…': '📅',
  'ðŸ¤"': '🤔',
  'ðŸ'‹': '👋',
  'ðŸŽ‰': '🎉',
  'â€¢': '•',
};

let count = 0;
for (const [bad, good] of Object.entries(replacements)) {
  const regex = new RegExp(bad, 'g');
  const matches = content.match(regex);
  if (matches) {
    count += matches.length;
    content = content.replace(regex, good);
  }
}

fs.writeFileSync(path, content, 'utf8');
console.log(`Reemplazos realizados: ${count}`);

// Verificar
const check = fs.readFileSync(path, 'utf8');
const samples = check.match(/reunión|confirmación|inválido|información/gi) || [];
console.log('Verificación:', samples.length > 0 ? samples.slice(0,5).join(', ') : 'No se encontraron palabras de prueba');

// Buscar si quedan caracteres corruptos
const remaining = check.match(/\u00c3[\u0080-\u00bf]/g);
if (remaining) {
  console.log('ADVERTENCIA: Aún quedan caracteres por corregir:', remaining.length);
} else {
  console.log('✓ No se detectaron más caracteres corruptos');
}
