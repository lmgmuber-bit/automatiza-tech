const fs = require('fs');

const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(filePath, 'utf8');

// Corregir bullets corruptos
const bulletsBefore = (content.match(/€¢/g) || []).length;
content = content.split('€¢').join('•');

// Guardar
fs.writeFileSync(filePath, content, 'utf8');

// Verificar JSON
try {
    JSON.parse(content);
    console.log('Bullets €¢ corregidos:', bulletsBefore);
    console.log('✅ JSON válido');
} catch (e) {
    console.error('❌ Error JSON:', e.message);
}
