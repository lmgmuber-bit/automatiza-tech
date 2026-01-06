const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';
const j = JSON.parse(fs.readFileSync(filePath, 'utf8'));

// Encontrar Send Plan Details y corregir el JSON
const spd = j.nodes.find(n => n.name === 'Send Plan Details');

spd.parameters.jsonBody = `={
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "{{ $json.phoneNumber }}",
  "type": "interactive",
  "interactive": {
    "type": "button",
    "body": {
      "text": "📋 *Plan {{ $json.planName }}*\\n\\n💰 Precio: {{ $json.planPrice }}\\n\\n*Incluye:*\\n{{ $json.planDetails }}\\n\\n¿Te gustaría agendar una demo gratuita para conocer más sobre este plan?"
    },
    "action": {
      "buttons": [
        {
          "type": "reply",
          "reply": {
            "id": "btn_agendar_demo",
            "title": "📅 Agendar Demo"
          }
        },
        {
          "type": "reply",
          "reply": {
            "id": "btn_ver_planes",
            "title": "🔄 Ver Otros Planes"
          }
        }
      ]
    }
  }
}`;

fs.writeFileSync(filePath, JSON.stringify(j, null, 2), 'utf8');
console.log('Send Plan Details actualizado');

// Verificar que el JSON interno es válido
const check = JSON.parse(fs.readFileSync(filePath, 'utf8'));
const spdCheck = check.nodes.find(n => n.name === 'Send Plan Details');
const jsonContent = spdCheck.parameters.jsonBody.substring(1); // Quitar el = inicial
try {
  // No podemos parsear porque tiene expresiones {{ }}, pero verificamos estructura
  console.log('Contiene phoneNumber:', jsonContent.includes('$json.phoneNumber'));
  console.log('Contiene planName:', jsonContent.includes('$json.planName'));
  console.log('No tiene saltos de linea en text:', !jsonContent.match(/"text":\s*"[^"]*\n/));
} catch (e) {
  console.log('Error:', e.message);
}
