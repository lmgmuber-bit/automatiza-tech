const fs = require('fs');

const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

// Leer el archivo como texto
let content = fs.readFileSync(filePath, 'utf8');

// Mapeo de caracteres corruptos a correctos
const replacements = [
    // Emojis
    ['ðŸ"…', '📅'],
    ['ðŸ'¤', '👤'],
    ['ðŸ"§', '📧'],
    ['ðŸ"', '📝'],
    ['ðŸ¤"', '🤔'],
    ['ðŸ'‹', '👋'],
    ['ðŸ'¡', '💡'],
    ['ðŸ'°', '💰'],
    ['ðŸš€', '🚀'],
    ['ðŸ"ž', '📞'],
    ['ðŸ"±', '📱'],
    ['ðŸ"', '📍'],
    ['ðŸ'¬', '💬'],
    ['ðŸ"Œ', '📌'],
    ['ðŸ"¢', '📢'],
    ['ðŸ—"ï¸', '🗓️'],
    ['ðŸ•', '🕐'],
    ['ðŸ•'', '🕐'],
    ['ðŸ"†', '📆'],
    ['âœ…', '✅'],
    ['âœ"', '✔'],
    ['âœ"ï¸', '✔️'],
    ['âš ï¸', '⚠️'],
    ['âš ', '⚠'],
    ['â„¹ï¸', 'ℹ️'],
    ['â„¹', 'ℹ'],
    ['âŒ', '❌'],
    ['â€¢', '•'],
    ['â†'', '→'],
    ['â†"', '↓'],
    ['â†', '←'],
    ['â†'', '↑'],
    ['ï¸Ž', '️'],
    ['ï¸', '️'],
    
    // Tildes y ñ
    ['Ã¡', 'á'],
    ['Ã©', 'é'],
    ['Ã­', 'í'],
    ['Ã³', 'ó'],
    ['Ãº', 'ú'],
    ['Ã±', 'ñ'],
    ['Ã', 'Á'],
    ['Ã‰', 'É'],
    ['Ã', 'Í'],
    ['Ã"', 'Ó'],
    ['Ãš', 'Ú'],
    ['Ã'', 'Ñ'],
    ['Ã¼', 'ü'],
    ['Ãœ', 'Ü'],
    
    // Otros caracteres especiales
    ['â€œ', '"'],
    ['â€', '"'],
    ['â€™', "'"],
    ['â€˜', "'"],
    ['â€"', '—'],
    ['â€"', '–'],
    ['â€¦', '…'],
    ['Â¡', '¡'],
    ['Â¿', '¿'],
    ['Â°', '°'],
    ['Â©', '©'],
    ['Â®', '®'],
    ['â„¢', '™'],
    ['Â«', '«'],
    ['Â»', '»'],
    ['Â·', '·'],
    ['Ã§', 'ç'],
    ['Ã‡', 'Ç'],
];

let changeCount = 0;

for (const [corrupted, correct] of replacements) {
    const regex = new RegExp(corrupted.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
    const matches = content.match(regex);
    if (matches) {
        changeCount += matches.length;
        content = content.replace(regex, correct);
        console.log(`Reemplazado "${corrupted}" → "${correct}" (${matches.length} veces)`);
    }
}

// Guardar el archivo corregido
fs.writeFileSync(filePath, content, 'utf8');

console.log(`\n✅ Total de reemplazos: ${changeCount}`);
console.log('Archivo guardado correctamente.');

// Verificar que el JSON es válido
try {
    JSON.parse(content);
    console.log('✅ JSON válido');
} catch (e) {
    console.error('❌ Error en JSON:', e.message);
}
