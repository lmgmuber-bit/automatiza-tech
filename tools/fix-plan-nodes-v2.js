const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';
const j = JSON.parse(fs.readFileSync(filePath, 'utf8'));

// 1. Corregir Set Plan Info - usar \\n en lugar de \n para que lleguen escapados
const spi = j.nodes.find(n => n.name === 'Set Plan Info');

spi.parameters.jsonOutput = `={
  "phoneNumberId": "{{ $('Process Interactive').first().json.phoneNumberId }}",
  "phoneNumber": "{{ $('Process Interactive').first().json.phoneNumber }}",
  "buttonId": "{{ $('Process Interactive').first().json.buttonId }}",
  "planName": "{{ $('Process Interactive').first().json.buttonId === 'btn_plan_basico' ? 'Básico' : ($('Process Interactive').first().json.buttonId === 'btn_plan_pro' ? 'Profesional' : 'Enterprise') }}",
  "planPrice": "{{ $('Process Interactive').first().json.buttonId === 'btn_plan_basico' ? '$99 USD/mes' : ($('Process Interactive').first().json.buttonId === 'btn_plan_pro' ? '$199 USD/mes' : '$399 USD/mes') }}",
  "planDetails": "{{ $('Process Interactive').first().json.buttonId === 'btn_plan_basico' ? '• Hasta 1,000 conversaciones/mes | • 1 número WhatsApp | • Chatbot básico con IA | • Respuestas automáticas 24/7 | • Soporte por email' : ($('Process Interactive').first().json.buttonId === 'btn_plan_pro' ? '• Hasta 5,000 conversaciones/mes | • 2 números WhatsApp | • Chatbot avanzado con IA | • Integración con CRM | • Reportes y analíticas | • Soporte prioritario' : '• Conversaciones ilimitadas | • Números WhatsApp ilimitados | • IA personalizada para tu negocio | • Integraciones a medida | • API dedicada | • Soporte 24/7 dedicado | • Onboarding personalizado') }}"
}`;

console.log('Set Plan Info actualizado');

// 2. Corregir Send Plan Details - simplificar el JSON sin saltos de línea problemáticos
const spd = j.nodes.find(n => n.name === 'Send Plan Details');

spd.parameters.jsonBody = `={"messaging_product":"whatsapp","recipient_type":"individual","to":"{{ $json.phoneNumber }}","type":"interactive","interactive":{"type":"button","body":{"text":"📋 *Plan {{ $json.planName }}*\\n\\n💰 Precio: {{ $json.planPrice }}\\n\\n*Incluye:*\\n{{ $json.planDetails }}\\n\\n¿Te gustaría agendar una demo gratuita para conocer más sobre este plan?"},"action":{"buttons":[{"type":"reply","reply":{"id":"btn_agendar_demo","title":"📅 Agendar Demo"}},{"type":"reply","reply":{"id":"btn_ver_planes","title":"🔄 Ver Otros Planes"}}]}}}`;

console.log('Send Plan Details actualizado');

fs.writeFileSync(filePath, JSON.stringify(j, null, 2), 'utf8');
console.log('Archivo guardado');

// Verificar
const check = JSON.parse(fs.readFileSync(filePath, 'utf8'));
console.log('JSON válido: SI');
