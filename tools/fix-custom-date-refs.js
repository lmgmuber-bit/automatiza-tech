const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Clear Pending Date State
const clearPDIdx = j.nodes.findIndex(n => n.name === 'Clear Pending Date State');
if (clearPDIdx >= 0) {
    const oldKey = j.nodes[clearPDIdx].parameters.key;
    // Clear Pending Date State viene después de Is Custom Date Valid?
    // que viene de Parse Custom Date
    // Los datos están en Parse Custom Date o Check Email Booking
    j.nodes[clearPDIdx].parameters.key = "=pending_date_{{ $('Check Email Booking').first().json.phoneNumber }}";
    console.log('✅ Clear Pending Date State corregido');
    console.log('   Antes:', oldKey);
    console.log('   Después:', j.nodes[clearPDIdx].parameters.key);
}

// Verificar si hay otros nodos con $json.phoneNumber que deberían usar otra referencia
console.log('\n=== Buscando otros nodos con $json.phoneNumber en el flujo custom date ===');
const customDateNodes = [
    'Clear Pending Date State',
    'Save Custom Booking State', 
    'Ask Name for Custom Date',
    'Is Custom Date Valid?',
    'Parse Custom Date',
    'Get Config For Parsing',
    'Send Custom Date Error'
];

customDateNodes.forEach(name => {
    const node = j.nodes.find(n => n.name === name);
    if (node?.parameters) {
        const params = JSON.stringify(node.parameters);
        if (params.includes('$json.phoneNumber') || params.includes('$json.phoneNumberId')) {
            console.log(`⚠️ ${name} todavía usa $json.phoneNumber/Id`);
        } else if (params.includes('phoneNumber')) {
            console.log(`✅ ${name} - OK`);
        }
    }
});

// Verificar Send Custom Date Error
const sendError = j.nodes.find(n => n.name === 'Send Custom Date Error');
if (sendError?.parameters?.jsonBody?.includes('$json.phoneNumber')) {
    console.log('\n⚠️ Send Custom Date Error usa $json - corrigiendo...');
    sendError.parameters.jsonBody = sendError.parameters.jsonBody
        .replace(/\$json\.phoneNumber/g, "$('Check Email Booking').first().json.phoneNumber")
        .replace(/\$json\.phoneNumberId/g, "$('Check Email Booking').first().json.phoneNumberId")
        .replace(/\$json\.error/g, "$('Parse Custom Date').first().json.error");
    
    if (sendError.parameters.url?.includes('$json.phoneNumberId')) {
        sendError.parameters.url = sendError.parameters.url.replace(
            /\$json\.phoneNumberId/g,
            "$('Check Email Booking').first().json.phoneNumberId"
        );
    }
    console.log('✅ Send Custom Date Error corregido');
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

// Verificación final
console.log('\n=== VERIFICACIÓN FINAL ===');
const j2 = JSON.parse(fs.readFileSync(path, 'utf8'));

const checkNodes = ['Clear Pending Date State', 'Save Custom Booking State', 'Ask Name for Custom Date', 'Send Custom Date Error'];
checkNodes.forEach(name => {
    const node = j2.nodes.find(n => n.name === name);
    if (node) {
        const key = node.parameters?.key || 'N/A';
        const url = node.parameters?.url || 'N/A';
        console.log(`${name}:`);
        if (key !== 'N/A') console.log(`  Key: ${key.substring(0, 60)}...`);
        if (url !== 'N/A') console.log(`  URL: ${url.substring(0, 60)}...`);
    }
});
