const fs = require('fs');
const path = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let content = fs.readFileSync(path, 'utf8');
if (content.charCodeAt(0) === 0xFEFF) content = content.slice(1);
const j = JSON.parse(content);

// Problema 1: Corregir caracteres especiales en Send Other Date Options
const otherDateIdx = j.nodes.findIndex(n => n.name === 'Send Other Date Options');
if (otherDateIdx >= 0) {
    j.nodes[otherDateIdx].parameters.jsonBody = `={
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "{{ $json.phoneNumber }}",
  "type": "text",
  "text": {
    "body": "📅 ¡Perfecto! Tienes dos opciones para agendar una fecha más adelante:\\n\\n1️⃣ *Escríbeme la fecha exacta* que deseas (por ejemplo: '15 de enero a las 14:00')\\n\\n2️⃣ *Agenda directamente desde la web:*\\nhttps://automatizatech.cl/#AgendarDemo\\n\\n¿Qué prefieres?"
  }
}`;
    console.log('✅ Send Other Date Options corregido - emojis arreglados');
}

// Problema 2: Corregir Set Date State - falta referencia al nodo
const setDateIdx = j.nodes.findIndex(n => n.name === 'Set Date State');
if (setDateIdx >= 0) {
    j.nodes[setDateIdx].parameters.key = "=pending_date_{{ $('Send Other Date Options').first().json.phoneNumber }}";
    console.log('✅ Set Date State corregido - referencia al nodo arreglada');
}

// También verificar Check Pending Date
const checkDateIdx = j.nodes.findIndex(n => n.name === 'Check Pending Date');
if (checkDateIdx >= 0) {
    console.log('Check Pending Date key:', j.nodes[checkDateIdx].parameters.key);
}

fs.writeFileSync(path, JSON.stringify(j, null, 2), 'utf8');
console.log('✅ Archivo guardado');

try {
    JSON.parse(fs.readFileSync(path, 'utf8'));
    console.log('✅ JSON válido');
} catch (e) {
    console.log('❌ Error:', e.message);
}
