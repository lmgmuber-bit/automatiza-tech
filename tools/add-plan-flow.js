const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

const j = JSON.parse(fs.readFileSync(filePath, 'utf8'));

// 1. Agregar reglas para los 3 planes en Button Action
const buttonAction = j.nodes.find(n => n.name === 'Button Action');

const planRules = [
  {
    conditions: {
      options: { caseSensitive: true, leftValue: "", typeValidation: "loose", version: 2 },
      conditions: [{ leftValue: "={{ $json.buttonId }}", rightValue: "btn_plan_basico", operator: { type: "string", operation: "equals" } }],
      combinator: "and"
    },
    renameOutput: true,
    outputKey: "Plan Basico"
  },
  {
    conditions: {
      options: { caseSensitive: true, leftValue: "", typeValidation: "loose", version: 2 },
      conditions: [{ leftValue: "={{ $json.buttonId }}", rightValue: "btn_plan_pro", operator: { type: "string", operation: "equals" } }],
      combinator: "and"
    },
    renameOutput: true,
    outputKey: "Plan Pro"
  },
  {
    conditions: {
      options: { caseSensitive: true, leftValue: "", typeValidation: "loose", version: 2 },
      conditions: [{ leftValue: "={{ $json.buttonId }}", rightValue: "btn_plan_enterprise", operator: { type: "string", operation: "equals" } }],
      combinator: "and"
    },
    renameOutput: true,
    outputKey: "Plan Enterprise"
  }
];

buttonAction.parameters.rules.values.push(...planRules);
console.log('Reglas agregadas. Total:', buttonAction.parameters.rules.values.length);

// 2. Crear nodo Set para preparar info del plan
const setPlanInfo = {
  parameters: {
    mode: "raw",
    jsonOutput: `={
  "phoneNumberId": "{{ $json.phoneNumberId }}",
  "phoneNumber": "{{ $json.phoneNumber }}",
  "planName": "{{ $json.buttonId === 'btn_plan_basico' ? 'Básico' : ($json.buttonId === 'btn_plan_pro' ? 'Profesional' : 'Enterprise') }}",
  "planPrice": "{{ $json.buttonId === 'btn_plan_basico' ? '$99 USD/mes' : ($json.buttonId === 'btn_plan_pro' ? '$199 USD/mes' : '$399 USD/mes') }}",
  "planDetails": "{{ $json.buttonId === 'btn_plan_basico' ? '✅ Hasta 1,000 conversaciones/mes\\n✅ 1 número WhatsApp\\n✅ Chatbot básico con IA\\n✅ Respuestas automáticas 24/7\\n✅ Soporte por email' : ($json.buttonId === 'btn_plan_pro' ? '✅ Hasta 5,000 conversaciones/mes\\n✅ 2 números WhatsApp\\n✅ Chatbot avanzado con IA\\n✅ Integración con CRM\\n✅ Reportes y analíticas\\n✅ Soporte prioritario' : '✅ Conversaciones ilimitadas\\n✅ Números WhatsApp ilimitados\\n✅ IA personalizada para tu negocio\\n✅ Integraciones a medida\\n✅ API dedicada\\n✅ Soporte 24/7 dedicado\\n✅ Onboarding personalizado') }}"
}`
  },
  type: "n8n-nodes-base.set",
  typeVersion: 3.4,
  position: [109800, 41200],
  id: "plan-info-set-node",
  name: "Set Plan Info"
};

// 3. Crear nodo HTTP Request para enviar mensaje con detalles del plan
const sendPlanDetails = {
  parameters: {
    method: "POST",
    url: "=https://graph.facebook.com/v22.0/{{ $json.phoneNumberId }}/messages",
    authentication: "predefinedCredentialType",
    nodeCredentialType: "whatsAppApi",
    sendBody: true,
    specifyBody: "json",
    jsonBody: `={
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
}`,
    options: {}
  },
  type: "n8n-nodes-base.httpRequest",
  typeVersion: 4.2,
  position: [110000, 41200],
  id: "send-plan-details-node",
  name: "Send Plan Details",
  credentials: {
    whatsAppApi: {
      id: "SH8OXr93p852Ll6m",
      name: "WhatsApp Tech"
    }
  }
};

// Agregar nodos
j.nodes.push(setPlanInfo);
j.nodes.push(sendPlanDetails);
console.log('Nodos agregados. Total nodos:', j.nodes.length);

// 4. Agregar conexiones
// Button Action tiene ahora 13 outputs (10 originales + 3 nuevos)
// Los nuevos outputs son índices 10, 11, 12 (Plan Basico, Plan Pro, Plan Enterprise)
if (!j.connections['Button Action']) {
  j.connections['Button Action'] = { main: [] };
}

// Asegurar que hay suficientes arrays para los outputs
while (j.connections['Button Action'].main.length < 13) {
  j.connections['Button Action'].main.push([]);
}

// Conectar los 3 outputs de planes al mismo nodo Set Plan Info
j.connections['Button Action'].main[10] = [{ node: "Set Plan Info", type: "main", index: 0 }];
j.connections['Button Action'].main[11] = [{ node: "Set Plan Info", type: "main", index: 0 }];
j.connections['Button Action'].main[12] = [{ node: "Set Plan Info", type: "main", index: 0 }];

// Conectar Set Plan Info -> Send Plan Details
j.connections['Set Plan Info'] = {
  main: [[{ node: "Send Plan Details", type: "main", index: 0 }]]
};

console.log('Conexiones agregadas');

// Guardar
fs.writeFileSync(filePath, JSON.stringify(j, null, 2), 'utf8');
console.log('Archivo guardado');

// Verificar
const check = JSON.parse(fs.readFileSync(filePath, 'utf8'));
console.log('Verificación:');
console.log('- Total nodos:', check.nodes.length);
console.log('- Set Plan Info existe:', check.nodes.some(n => n.name === 'Set Plan Info'));
console.log('- Send Plan Details existe:', check.nodes.some(n => n.name === 'Send Plan Details'));
console.log('- Conexiones Button Action:', check.connections['Button Action'].main.length);
