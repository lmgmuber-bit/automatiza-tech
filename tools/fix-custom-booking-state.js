const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// El flujo es:
// Parse Custom Date -> Is Custom Date Valid? -> Clear Pending Date State -> Save Custom Booking State

// Parse Custom Date obtiene datos de Check Email Booking y retorna:
// { isValid, selectedDay, selectedTime, parsedDateDisplay, phoneNumber, phoneNumberId, ... }

// Is Custom Date Valid? pasa los datos

// Clear Pending Date State es un Redis DELETE - su output es la respuesta de Redis, NO los datos originales

// Save Custom Booking State recibe el output de Clear Pending Date State (respuesta Redis)
// Por eso $json.phoneNumber está vacío

console.log('=== ANÁLISIS DEL PROBLEMA ===\n');

// Ver Clear Pending Date State
const clearPD = j.nodes.find(n => n.name === 'Clear Pending Date State');
console.log('Clear Pending Date State:');
console.log('  Type:', clearPD?.type);
console.log('  Operation:', clearPD?.parameters?.operation);
console.log('  Key:', clearPD?.parameters?.key);

// El problema: Clear Pending Date State es un Redis DELETE
// Su output es algo como { success: true } o similar
// NO pasa los datos originales

console.log('\n=== SOLUCIÓN ===');
console.log('Cambiar Save Custom Booking State para referenciar Parse Custom Date o Check Email Booking');
console.log('que SÍ tienen phoneNumber, selectedDay, selectedTime, etc.\n');

// Corregir Save Custom Booking State
const scbsIdx = j.nodes.findIndex(n => n.name === 'Save Custom Booking State');
if (scbsIdx >= 0) {
    // Los datos vienen de Parse Custom Date que los obtiene de Check Email Booking
    // Parse Custom Date retorna los datos completos
    
    const oldKey = j.nodes[scbsIdx].parameters.key;
    const oldValue = j.nodes[scbsIdx].parameters.value;
    
    // Cambiar para referenciar Parse Custom Date
    j.nodes[scbsIdx].parameters.key = "=booking_{{ $('Parse Custom Date').first().json.phoneNumber }}";
    j.nodes[scbsIdx].parameters.value = "={{ JSON.stringify({ day: $('Parse Custom Date').first().json.selectedDay, time: $('Parse Custom Date').first().json.selectedTime, phone: $('Parse Custom Date').first().json.phoneNumber, phoneNumberId: $('Parse Custom Date').first().json.phoneNumberId, step: 'WAITING_NAME' }) }}";
    
    console.log('✅ Save Custom Booking State corregido');
    console.log('   Key antes:', oldKey);
    console.log('   Key después:', j.nodes[scbsIdx].parameters.key);
    console.log('   Value antes:', oldValue.substring(0, 80) + '...');
    console.log('   Value después:', j.nodes[scbsIdx].parameters.value.substring(0, 80) + '...');
}

// También corregir Ask Name for Custom Date
const askNameIdx = j.nodes.findIndex(n => n.name === 'Ask Name for Custom Date');
if (askNameIdx >= 0) {
    const node = j.nodes[askNameIdx];
    // Verificar si usa $json
    const jsonBody = node.parameters.jsonBody || '';
    if (jsonBody.includes('$json.phoneNumber')) {
        // Necesita corregirse también
        console.log('\n⚠️ Ask Name for Custom Date también usa $json - verificando...');
        console.log('   jsonBody preview:', jsonBody.substring(0, 200));
        
        // Corregir las referencias
        let newJsonBody = jsonBody
            .replace(/\$json\.phoneNumber/g, "$('Parse Custom Date').first().json.phoneNumber")
            .replace(/\$json\.phoneNumberId/g, "$('Parse Custom Date').first().json.phoneNumberId")
            .replace(/\$json\.parsedDateDisplay/g, "$('Parse Custom Date').first().json.parsedDateDisplay")
            .replace(/\$json\.selectedTime/g, "$('Parse Custom Date').first().json.selectedTime");
        
        node.parameters.jsonBody = newJsonBody;
        console.log('✅ Ask Name for Custom Date corregido');
    }
    
    // También verificar la URL
    if (node.parameters.url?.includes('$json.phoneNumberId')) {
        node.parameters.url = node.parameters.url.replace(
            /\$json\.phoneNumberId/g, 
            "$('Parse Custom Date').first().json.phoneNumberId"
        );
        console.log('✅ URL de Ask Name for Custom Date corregida');
    }
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('\n✅ Archivo guardado');

// Verificar
const j2 = JSON.parse(fs.readFileSync(path, 'utf8'));
const scbs2 = j2.nodes.find(n => n.name === 'Save Custom Booking State');
console.log('\nVerificación Save Custom Booking State:');
console.log('  Key:', scbs2?.parameters?.key);
