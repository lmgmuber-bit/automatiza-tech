/**
 * Transform v6 (YCloud direct) → v7 (Portal OmniCliente)
 * 
 * Changes:
 * 1. Entry point: Parse portal format instead of YCloud format
 * 2. All 19 send nodes: Route through portal callback instead of YCloud API
 * 3. Data flow: Pass callback fields (token, conversation_id, channel_id) through pipeline
 * 4. Audio upload: Keep YCloud direct (only node that keeps credentials)
 */
const fs = require('fs');
const path = require('path');

const INPUT  = path.join(__dirname, 'WhatsApp_Bot_v6_KellsCapilar.json');
const OUTPUT = path.join(__dirname, 'WhatsApp_Bot_v7_Portal_OmniCliente.json');

const wf = JSON.parse(fs.readFileSync(INPUT, 'utf8'));

// Helper
function findNode(name) {
  return wf.nodes.find(n => n.name === name);
}

// =================================================================
// 1. CHANGE WORKFLOW NAME
// =================================================================
wf.name = "WhatsApp Bot - v7 Portal OmniCliente (Google Sheets)";

// =================================================================
// 2. MODIFY "Filter Event Type" — check portal format
// =================================================================
const filterNode = findNode('Filter Event Type');
if (filterNode) {
  // Route 1: Portal sends event: "new_message" (was: whatsapp.inbound_message.received)
  filterNode.parameters.rules.values[0].conditions.conditions[0] = {
    leftValue: "={{ $json.body.event }}",
    rightValue: "new_message",
    operator: { type: "string", operation: "equals" },
    id: "filter-inbound-portal"
  };
  filterNode.parameters.rules.values[0].outputKey = "MensajeRecibido";
  
  // Route 2: Echo (DuenoResponde) — portal doesn't send echoes, keep but won't match
  // No change needed — it will simply never trigger
  console.log('✅ Filter Event Type updated');
}

// =================================================================
// 3. MODIFY "Has Message?" — check portal format
// =================================================================
const hasMessageNode = findNode('Has Message?');
if (hasMessageNode) {
  hasMessageNode.parameters.conditions.conditions[0] = {
    leftValue: "={{ $json.body.message?.content || $json.body.message?.id }}",
    rightValue: "",
    operator: { type: "string", operation: "notEmpty" },
    id: "has-message-portal"
  };
  console.log('✅ Has Message? updated');
}

// =================================================================
// 4. MODIFY "Extract Message Data" — parse portal payload
// =================================================================
const extractNode = findNode('Extract Message Data');
if (extractNode) {
  extractNode.parameters.jsonOutput = [
    '={',
    '  "phoneNumber": "{{ $json.body.contact.phone }}",',
    '  "businessPhone": "{{ $json.body.business_phone || \'\' }}",',
    '  "messageId": "{{ $json.body.message.id }}",',
    '  "messageType": "{{ $json.body.message.type || \'text\' }}",',
    '  "contactName": "{{ $json.body.contact.name || $json.body.contact.phone }}",',
    '  "phoneNumberId": "",',
    '  "textContent": {{ JSON.stringify($json.body.message.content || \'\') }},',
    '  "audioId": "",',
    '  "audioLink": "{{ ($json.body.message.type === \'audio\') ? ($json.body.message.media_url || \'\') : \'\' }}",',
    '  "imageId": "",',
    '  "imageLink": "{{ ($json.body.message.type === \'image\') ? ($json.body.message.media_url || \'\') : \'\' }}",',
    '  "imageMimeType": "",',
    '  "hasImage": {{ $json.body.message.type === \'image\' }},',
    '  "callbackUrl": "{{ $json.body.callback_url }}",',
    '  "callbackToken": "{{ $json.body.callback_token }}",',
    '  "conversationId": {{ $json.body.conversation_id }},',
    '  "channelId": {{ $json.body.channel_id }}',
    '}'
  ].join('\n');
  console.log('✅ Extract Message Data updated');
}

// =================================================================
// 5. MODIFY "Redis Push" — include callback fields in stored data
// =================================================================
const redisPush = findNode('Redis Push');
if (redisPush) {
  const old = redisPush.parameters.messageData;
  // Add callback fields before the closing })
  redisPush.parameters.messageData = old.replace(
    "imageMimeType: $json.imageMimeType || '' })",
    "imageMimeType: $json.imageMimeType || '', callbackUrl: $json.callbackUrl || '', callbackToken: $json.callbackToken || '', conversationId: $json.conversationId || 0, channelId: $json.channelId || 0 })"
  );
  console.log('✅ Redis Push updated');
}

// =================================================================
// 6. MODIFY "Combine Messages" — output callback fields
// =================================================================
const combineNode = findNode('Combine Messages');
if (combineNode) {
  const code = combineNode.parameters.jsCode;
  // Add extraction of callback fields from parsed messages
  const oldReturn = `return {
  json: {
    textContent,
    phoneNumber,
    contactName,
    phoneNumberId,
    businessPhone,
    isFromAudio,
    hasInteractiveAction: !!interactiveAction,
    hasImage,
    imageId,
    imageLink,
    imageMimeType
  }
};`;
  
  const newReturn = `// Extract portal callback fields from parsed messages
let callbackUrl = '';
let callbackToken = '';
let conversationId = 0;
let channelId = 0;
for (const p of parsed) {
  if (p.callbackUrl) {
    callbackUrl = p.callbackUrl;
    callbackToken = p.callbackToken;
    conversationId = p.conversationId;
    channelId = p.channelId;
    break;
  }
}

return {
  json: {
    textContent,
    phoneNumber,
    contactName,
    phoneNumberId,
    businessPhone,
    isFromAudio,
    hasInteractiveAction: !!interactiveAction,
    hasImage,
    imageId,
    imageLink,
    imageMimeType,
    callbackUrl,
    callbackToken,
    conversationId,
    channelId
  }
};`;
  
  combineNode.parameters.jsCode = code.replace(oldReturn, newReturn);
  console.log('✅ Combine Messages updated');
}

// =================================================================
// 7. MODIFY "Audio to Text" Set node — pass callback fields
// =================================================================
// This is the Set node that maps transcribed audio back to the pipeline
for (const node of wf.nodes) {
  if (node.parameters?.jsonOutput && 
      node.parameters.jsonOutput.includes('"isFromAudio": true') &&
      node.type === 'n8n-nodes-base.set') {
    node.parameters.jsonOutput = node.parameters.jsonOutput.replace(
      '"isFromAudio": true\n}',
      [
        '"isFromAudio": true,',
        '  "callbackUrl": "{{ $(\'Extract Message Data\').first().json.callbackUrl }}",',
        '  "callbackToken": "{{ $(\'Extract Message Data\').first().json.callbackToken }}",',
        '  "conversationId": {{ $(\'Extract Message Data\').first().json.conversationId }},',
        '  "channelId": {{ $(\'Extract Message Data\').first().json.channelId }}',
        '}'
      ].join('\n')
    );
    console.log('✅ Audio-to-Text Set node updated');
    break;
  }
}

// =================================================================
// 8. TRANSFORM ALL YCLOUD SEND NODES → Portal callback
// =================================================================
const CALLBACK_TPL = [
  '',
  '  "callback_token": "{{ $(\'Combine Messages\').first().json.callbackToken }}",',
  '  "conversation_id": {{ $(\'Combine Messages\').first().json.conversationId }},',
  '  "channel_id": {{ $(\'Combine Messages\').first().json.channelId }},'
].join('\n');

const CALLBACK_JS = "callback_token: $('Combine Messages').first().json.callbackToken, conversation_id: $('Combine Messages').first().json.conversationId, channel_id: $('Combine Messages').first().json.channelId, ";

let sendNodesChanged = 0;

for (const node of wf.nodes) {
  // Skip Upload Audio to WhatsApp — keep YCloud direct for media uploads
  if (node.name === 'Upload Audio to WhatsApp') continue;
  
  if (node.parameters?.url === '=https://api.ycloud.com/v2/whatsapp/messages') {
    // Change URL to portal callback
    node.parameters.url = "={{ $('Combine Messages').first().json.callbackUrl }}";
    
    // Remove YCloud authentication
    delete node.parameters.authentication;
    delete node.parameters.nodeCredentialType;
    delete node.credentials;
    
    // Add callback fields to jsonBody
    const body = node.parameters.jsonBody;
    
    if (body.startsWith('={\n')) {
      // Pattern A: Template literal JSON — insert fields after opening {
      node.parameters.jsonBody = body.replace('={\n', '={\n' + CALLBACK_TPL + '\n');
      sendNodesChanged++;
    } else if (body.includes('JSON.stringify({')) {
      // Pattern B/C/D: JSON.stringify, IIFE, ternary — add JS props after each {
      node.parameters.jsonBody = body.replace(
        /JSON\.stringify\(\{/g,
        'JSON.stringify({ ' + CALLBACK_JS
      );
      sendNodesChanged++;
    } else {
      console.warn('⚠️ Unknown jsonBody pattern for node:', node.name);
    }
    
    console.log(`  → ${node.name} (send → portal)`);
  }
}
console.log(`✅ ${sendNodesChanged} send nodes converted to portal callback`);

// =================================================================
// 9. UPDATE INSTRUCTIONS NOTE
// =================================================================
const instrNode = findNode('📖 INSTRUCCIONES');
if (instrNode && instrNode.notes) {
  instrNode.notes = instrNode.notes.replace(
    '# 🚀 WhatsApp Bot Demo v6.1 Redis (Google Sheets)',
    '# 🚀 WhatsApp Bot v7 - Portal OmniCliente (Google Sheets)'
  );
  instrNode.notes += [
    '',
    '',
    '## 🆕 Cambios v7 — Integración Portal OmniCliente',
    '',
    '**Arquitectura Opción B**: El portal es el punto de entrada.',
    '',
    '```',
    'WhatsApp → YCloud → PORTAL → DB → N8N (este workflow)',
    '                                     ↓',
    'WhatsApp ← YCloud ← PORTAL ← callback ← N8N responde',
    '```',
    '',
    '### Qué cambió:',
    '- ✅ Entrada: El portal envía mensajes al webhook N8N (ya no YCloud directo)',
    '- ✅ Salida: Todos los mensajes van al callback del portal (ya no a api.ycloud.com)',
    '- ✅ Portal maneja: DB, bandeja unificada, toma de control humano, auditoría',
    '- ✅ Audio Upload: Sigue directo a YCloud (única excepción)',
    '- ✅ Contingencia: Si el portal cae, N8N puede reactivar webhook directo',
    '',
    '### Campos del portal en el pipeline:',
    '- `callbackUrl`: URL del callback del portal',
    '- `callbackToken`: Token HMAC para autenticar el callback',
    '- `conversationId`: ID de conversación en el portal',
    '- `channelId`: ID del canal en el portal',
    '',
    '### Control humano:',
    '- Ya NO se usa /tomar y /bot por WhatsApp',
    '- El ejecutivo toma control desde la Bandeja Unificada del portal',
    '- Cuando está en modo humano, el portal NO reenvía a N8N'
  ].join('\n');
  console.log('✅ Instructions note updated');
}

// =================================================================
// WRITE OUTPUT
// =================================================================
fs.writeFileSync(OUTPUT, JSON.stringify(wf, null, 2), 'utf8');
console.log(`\n🎉 v7 written to: ${OUTPUT}`);
console.log(`   Size: ${(fs.statSync(OUTPUT).size / 1024).toFixed(1)} KB`);
