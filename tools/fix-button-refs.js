const fs = require('fs');

const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(filePath, 'utf8');

// Contar antes
const beforeCount = (content.match(/Button to Text/g) || []).length;

// Reemplazar todas las referencias
content = content.split("$('Button to Text')").join("$('Process Interactive')");

// Guardar
fs.writeFileSync(filePath, content, 'utf8');

// Contar después
const afterCount = (content.match(/Button to Text/g) || []).length;

// Verificar JSON válido
try {
    JSON.parse(content);
    console.log(`Referencias a "Button to Text": antes=${beforeCount}, después=${afterCount}`);
    console.log('✅ JSON válido');
} catch (e) {
    console.error('❌ Error JSON:', e.message);
}
