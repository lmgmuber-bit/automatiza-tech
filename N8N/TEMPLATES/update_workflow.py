import json

# Leer archivo
with open(r'WhatsApp_Bot_Demo_v5_EXPORTED.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Crear nuevo nodo Switch para filtrar eventos
filter_node = {
    "parameters": {
        "rules": {
            "values": [
                {
                    "conditions": {
                        "options": {"caseSensitive": True, "leftValue": "", "typeValidation": "strict", "version": 1},
                        "conditions": [
                            {"leftValue": "={{ $json.body.type }}", "rightValue": "whatsapp.inbound_message.received", "operator": {"type": "string", "operation": "equals"}, "id": "filter-inbound"}
                        ],
                        "combinator": "and"
                    },
                    "renameOutput": True,
                    "outputKey": "ClienteEscribe"
                },
                {
                    "conditions": {
                        "options": {"caseSensitive": True, "leftValue": "", "typeValidation": "strict", "version": 1},
                        "conditions": [
                            {"leftValue": "={{ $json.body.type }}", "rightValue": "whatsapp.smb.message.echoes", "operator": {"type": "string", "operation": "equals"}, "id": "filter-echo"}
                        ],
                        "combinator": "and"
                    },
                    "renameOutput": True,
                    "outputKey": "DuenoResponde"
                }
            ]
        },
        "options": {"fallbackOutput": "none"}
    },
    "type": "n8n-nodes-base.switch",
    "typeVersion": 3,
    "position": [700, 300],
    "id": "filter-event-type",
    "name": "Filter Event Type"
}

# Nodo silencio
silence_node = {
    "parameters": {},
    "type": "n8n-nodes-base.noOp",
    "typeVersion": 1,
    "position": [700, 500],
    "id": "silence-node",
    "name": "🔇 Dueño Respondió (Silencio)"
}

# Insertar nodos nuevos despues del webhook
webhook_idx = None
for i, node in enumerate(data['nodes']):
    if node.get('name') == 'WhatsApp Webhook':
        webhook_idx = i
        break

if webhook_idx is not None:
    data['nodes'].insert(webhook_idx + 1, filter_node)
    data['nodes'].insert(webhook_idx + 2, silence_node)

# Modificar nodos existentes
for node in data['nodes']:
    name = node.get('name', '')
    params = node.get('parameters', {})
    
    # Modificar Has Message para usar estructura YCloud
    if name == 'Has Message?':
        params['conditions']['conditions'][0]['leftValue'] = '={{ $json.body.whatsappInboundMessage?.id }}'
        params['conditions']['conditions'][0]['operator'] = {'type': 'string', 'operation': 'notEmpty'}
    
    # Modificar Extract Message Data para YCloud
    if name == 'Extract Message Data':
        params['jsonOutput'] = '={\n  "phoneNumber": "{{ $json.body.whatsappInboundMessage.from }}",\n  "businessPhone": "{{ $json.body.whatsappInboundMessage.to }}",\n  "messageId": "{{ $json.body.whatsappInboundMessage.id }}",\n  "messageType": "{{ $json.body.whatsappInboundMessage.type }}",\n  "contactName": "{{ $json.body.whatsappInboundMessage.customerProfile?.name || $json.body.whatsappInboundMessage.from }}",\n  "phoneNumberId": "{{ $json.body.whatsappInboundMessage.wabaId }}",\n  "textContent": {{ JSON.stringify($json.body.whatsappInboundMessage.text?.body || $json.body.whatsappInboundMessage.interactive?.buttonReply?.id || $json.body.whatsappInboundMessage.interactive?.listReply?.id || $json.body.whatsappInboundMessage.button?.text || \'\') }},\n  "audioId": {{ JSON.stringify($json.body.whatsappInboundMessage.audio?.id || \'\') }}\n}'
    
    # Modificar Audio to Text para incluir businessPhone
    if name == 'Audio to Text':
        params['jsonOutput'] = '={\n  "phoneNumber": "{{ $(\'Extract Message Data\').first().json.phoneNumber }}",\n  "businessPhone": "{{ $(\'Extract Message Data\').first().json.businessPhone }}",\n  "messageId": "{{ $(\'Extract Message Data\').first().json.messageId }}",\n  "messageType": "text",\n  "contactName": "{{ $(\'Extract Message Data\').first().json.contactName }}",\n  "phoneNumberId": "{{ $(\'Extract Message Data\').first().json.phoneNumberId }}",\n  "textContent": {{ JSON.stringify($json.text || \'\') }},\n  "audioId": "",\n  "isFromAudio": true\n}'
    
    # Modificar Buffer Manager para incluir businessPhone
    if name == 'Buffer Manager':
        params['jsCode'] = """// Buffer Manager - agrupa mensajes rápidos (ventana de 5 segundos)
const input = $input.first().json;
const phoneNumber = input.phoneNumber;
const businessPhone = input.businessPhone;
const textContent = input.textContent;
const isFromAudio = input.isFromAudio || false;
const currentTime = Date.now();

const staticData = $getWorkflowStaticData('global');
if (!staticData.messageBuffers) staticData.messageBuffers = {};

const bufferKey = 'buffer_' + phoneNumber;
const existingBuffer = staticData.messageBuffers[bufferKey];

// Si hay un buffer activo y el mensaje llega dentro de 5 segundos
if (existingBuffer && (currentTime - existingBuffer.timestamp) < 5000) {
  existingBuffer.messages.push(textContent);
  existingBuffer.timestamp = currentTime;
  if (isFromAudio) existingBuffer.isFromAudio = true;
  return { json: { action: 'wait', phoneNumber } };
}

// Crear nuevo buffer
staticData.messageBuffers[bufferKey] = {
  messages: [textContent],
  timestamp: currentTime,
  phoneNumber: phoneNumber,
  businessPhone: businessPhone,
  contactName: input.contactName,
  phoneNumberId: input.phoneNumberId,
  isFromAudio: isFromAudio
};

return { json: { action: 'process', bufferKey, ...input } };"""
    
    # Modificar Combine Messages para incluir businessPhone
    if name == 'Combine Messages':
        params['jsCode'] = """// Combinar todos los mensajes del buffer
const bufferKey = $json.bufferKey;
const staticData = $getWorkflowStaticData('global');
const buffer = staticData.messageBuffers[bufferKey];

if (!buffer) {
  return { json: { error: 'Buffer not found', phoneNumber: $json.phoneNumber } };
}

// Combinar mensajes
const combinedMessage = buffer.messages.join(' ');
const messageCount = buffer.messages.length;
const isFromAudio = buffer.isFromAudio || false;

// Limpiar buffer
delete staticData.messageBuffers[bufferKey];

return {
  json: {
    phoneNumber: buffer.phoneNumber,
    businessPhone: buffer.businessPhone,
    phoneNumberId: buffer.phoneNumberId,
    contactName: buffer.contactName,
    textContent: combinedMessage,
    messageCount: messageCount,
    isFromAudio: isFromAudio
  }
};"""

    # Cambiar URLs de graph.facebook.com a api.ycloud.com
    if 'url' in params:
        url = params['url']
        if 'graph.facebook.com' in url:
            if '/messages' in url and 'audioId' not in url:
                params['url'] = '=https://api.ycloud.com/v2/whatsapp/messages'
            elif 'audioId' in url:
                params['url'] = '=https://api.ycloud.com/v1/whatsapp/messages/media/{{ $json.audioId }}'

    # Cambiar jsonBody de formato Facebook a YCloud
    if 'jsonBody' in params:
        body = params['jsonBody']
        if '"messaging_product": "whatsapp"' in body:
            # Para nodos que usan $json directamente
            body = body.replace('"messaging_product": "whatsapp",\n  "to":', '"from": "{{ $json.businessPhone }}",\n  "to":')
            params['jsonBody'] = body

# Actualizar conexiones
connections = data.get('connections', {})

# Modificar conexión del webhook para ir al filter primero
if 'WhatsApp Webhook' in connections:
    connections['WhatsApp Webhook'] = {
        "main": [[{"node": "Filter Event Type", "type": "main", "index": 0}]]
    }

# Agregar conexiones del Filter Event Type
connections['Filter Event Type'] = {
    "main": [
        [{"node": "Has Message?", "type": "main", "index": 0}],
        [{"node": "🔇 Dueño Respondió (Silencio)", "type": "main", "index": 0}]
    ]
}

print('Modificaciones aplicadas')

# Guardar
with open(r'WhatsApp_Bot_Demo_v5_EXPORTED.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print('Archivo guardado correctamente')
