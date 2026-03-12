const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';
const j = JSON.parse(fs.readFileSync(filePath, 'utf8'));

// Encontrar Set Plan Info y corregir para leer de Process Interactive
const spi = j.nodes.find(n => n.name === 'Set Plan Info');

spi.parameters.jsonOutput = `={
  "phoneNumberId": "{{ $('Process Interactive').first().json.phoneNumberId }}",
  "phoneNumber": "{{ $('Process Interactive').first().json.phoneNumber }}",
  "buttonId": "{{ $('Process Interactive').first().json.buttonId }}",
  "planName": "{{ $('Process Interactive').first().json.buttonId === 'btn_plan_basico' ? 'Básico' : ($('Process Interactive').first().json.buttonId === 'btn_plan_pro' ? 'Profesional' : 'Enterprise') }}",
  "planPrice": "{{ $('Process Interactive').first().json.buttonId === 'btn_plan_basico' ? '$99 USD/mes' : ($('Process Interactive').first().json.buttonId === 'btn_plan_pro' ? '$199 USD/mes' : '$399 USD/mes') }}",
  "planDetails": "{{ $('Process Interactive').first().json.buttonId === 'btn_plan_basico' ? '✅ Hasta 1,000 conversaciones/mes\\n✅ 1 número WhatsApp\\n✅ Chatbot básico con IA\\n✅ Respuestas automáticas 24/7\\n✅ Soporte por email' : ($('Process Interactive').first().json.buttonId === 'btn_plan_pro' ? '✅ Hasta 5,000 conversaciones/mes\\n✅ 2 números WhatsApp\\n✅ Chatbot avanzado con IA\\n✅ Integración con CRM\\n✅ Reportes y analíticas\\n✅ Soporte prioritario' : '✅ Conversaciones ilimitadas\\n✅ Números WhatsApp ilimitados\\n✅ IA personalizada para tu negocio\\n✅ Integraciones a medida\\n✅ API dedicada\\n✅ Soporte 24/7 dedicado\\n✅ Onboarding personalizado') }}"
}`;

fs.writeFileSync(filePath, JSON.stringify(j, null, 2), 'utf8');
console.log('Set Plan Info actualizado');

// Verificar
const check = JSON.parse(fs.readFileSync(filePath, 'utf8'));
const spiCheck = check.nodes.find(n => n.name === 'Set Plan Info');
console.log('Verificación - contiene Process Interactive:', spiCheck.parameters.jsonOutput.includes("Process Interactive"));
