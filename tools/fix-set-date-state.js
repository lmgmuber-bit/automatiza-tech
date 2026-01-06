const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Corregir Set Date State para usar Process Interactive
const setDateIdx = j.nodes.findIndex(n => n.name === 'Set Date State');
if (setDateIdx >= 0) {
    const oldKey = j.nodes[setDateIdx].parameters.key;
    j.nodes[setDateIdx].parameters.key = "=pending_date_{{ $('Process Interactive').first().json.phoneNumber }}";
    console.log('✅ Set Date State corregido');
    console.log('   Antes:', oldKey);
    console.log('   Después:', j.nodes[setDateIdx].parameters.key);
}

// También corregir Send Other Date Options para que pase phoneNumber al siguiente nodo
// MEJOR: Reorganizar la conexión para que Set Date State se ejecute ANTES de Send Other Date Options
// O usar un nodo Set para pasar los datos

// Opción más simple: Cambiar Send Other Date Options para que NO sea el trigger de Set Date State
// En lugar de eso, usar un nodo NoOp o Set entre Button Action y Send Other Date Options
// que preserve phoneNumber

// Por ahora, la corrección de la referencia debería bastar
// Process Interactive se ejecuta ANTES en el flujo

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

// Verificar
const j2 = JSON.parse(fs.readFileSync(path, 'utf8'));
const setDate2 = j2.nodes.find(n => n.name === 'Set Date State');
console.log('\nVerificación:');
console.log('  Set Date State key:', setDate2?.parameters?.key);
