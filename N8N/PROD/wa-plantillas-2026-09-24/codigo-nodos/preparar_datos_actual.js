const errorData = $('Error Trigger').item.json;
const argosOutput = $('ARGOS').item.json.output || 'Error sin análisis';
const historial = $('Buscar Historial BD').item.json;

const workflowName = errorData.workflow?.name || 'Unknown';
// Un fallo de TRIGGER llega en trigger.error, no en execution.error.
// Sin este fallback, error_message iba vacio y el endpoint WP respondia 400 missing_data.
const errSrc = errorData.execution?.error || errorData.trigger?.error || {};
const errorNode = errSrc.node?.name || 'Desconocido';
const errorMessage = errSrc.message || errSrc.description || 'Error sin mensaje (fallo en el trigger del workflow)';
const executionMode = errorData.execution?.mode || (errorData.trigger ? 'trigger' : 'unknown');
const executionId = errorData.execution?.id || '';
const isRecurring = historial.summary?.is_recurring || false;
const occurrenceCount = (historial.summary?.errors_exact_match || 0) + 1;
const timestamp = new Date().toLocaleString('es-CL', { timeZone: 'America/Santiago' });

// Determinar severidad basado en el análisis
let severity = '🟡 MEDIO';
let severityEmoji = '⚠️';
if (isRecurring || argosOutput.toLowerCase().includes('crítico')) {
  severity = '🔴 CRÍTICO';
  severityEmoji = '🚨';
} else if (argosOutput.toLowerCase().includes('alto')) {
  severity = '🟠 ALTO';
  severityEmoji = '⚠️';
}

// ═══════════════════════════════════════════════════════════════
// FORMATO WHATSAPP - Visual y compacto
// ═══════════════════════════════════════════════════════════════
const waMessage = `🛡️ *ARGOS* - Detección de Error
━━━━━━━━━━━━━━━━━━━━━━━━

${severityEmoji} *Severidad:* ${severity}
${isRecurring ? '🔄 *RECURRENTE* (#' + occurrenceCount + ')' : '🆕 *NUEVO*'}

📋 *Workflow:* ${workflowName}
🔧 *Nodo:* ${errorNode}
⏰ *Hora:* ${timestamp}
🆔 *ID:* ${executionId.substring(0,8)}...

━━━━━━━━━━━━━━━━━━━━━━━━

💬 *Mensaje de Error:*
\`\`\`
${errorMessage.substring(0, 300)}${errorMessage.length > 300 ? '...' : ''}
\`\`\`

━━━━━━━━━━━━━━━━━━━━━━━━

🤖 *Análisis ARGOS:*

${argosOutput.substring(0, 1500)}

━━━━━━━━━━━━━━━━━━━━━━━━
👁️ _Argos vigila. Argos protege._
🔗 Ver más: automatizatech.cl/admin-tools/`;

// ═══════════════════════════════════════════════════════════════
// FORMATO EMAIL - HTML bonito
// ═══════════════════════════════════════════════════════════════
const severityColor = isRecurring ? '#dc3545' : (severity.includes('ALTO') ? '#fd7e14' : '#ffc107');
const severityBg = isRecurring ? '#fff5f5' : (severity.includes('ALTO') ? '#fff8f0' : '#fffef0');

const emailHtml = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7fa;">
  <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
    
    <!-- Header -->
    <tr>
      <td style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 30px 40px; text-align: center;">
        <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">
          🛡️ ARGOS
        </h1>
        <p style="color: #a0a0a0; margin: 5px 0 0 0; font-size: 14px;">Sistema de Monitoreo Inteligente</p>
      </td>
    </tr>
    
    <!-- Severity Banner -->
    <tr>
      <td style="background-color: ${severityBg}; border-left: 4px solid ${severityColor}; padding: 20px 40px;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td>
              <span style="font-size: 28px;">${severityEmoji}</span>
            </td>
            <td style="padding-left: 15px;">
              <p style="margin: 0; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px;">Severidad</p>
              <p style="margin: 5px 0 0 0; font-size: 20px; font-weight: 700; color: ${severityColor};">${severity.replace(/🔴|🟠|🟡/g, '').trim()}</p>
            </td>
            <td style="text-align: right;">
              ${isRecurring ? '<span style="background-color: #dc3545; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">🔄 RECURRENTE #' + occurrenceCount + '</span>' : '<span style="background-color: #28a745; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">🆕 NUEVO</span>'}
            </td>
          </tr>
        </table>
      </td>
    </tr>
    
    <!-- Info Cards -->
    <tr>
      <td style="padding: 30px 40px;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 12px; overflow: hidden;">
          <tr>
            <td style="padding: 20px; border-bottom: 1px solid #e9ecef;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="50%" style="padding-right: 10px;">
                    <p style="margin: 0; font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">📋 Workflow</p>
                    <p style="margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #212529;">${workflowName}</p>
                  </td>
                  <td width="50%" style="padding-left: 10px;">
                    <p style="margin: 0; font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">🔧 Nodo</p>
                    <p style="margin: 5px 0 0 0; font-size: 16px; font-weight: 600; color: #212529;">${errorNode}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding: 20px;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="50%" style="padding-right: 10px;">
                    <p style="margin: 0; font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">⏰ Timestamp</p>
                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #495057;">${timestamp}</p>
                  </td>
                  <td width="50%" style="padding-left: 10px;">
                    <p style="margin: 0; font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">🔖 Modo</p>
                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #495057;">${executionMode}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    
    <!-- Error Message -->
    <tr>
      <td style="padding: 0 40px 30px 40px;">
        <p style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #212529;">💬 Mensaje de Error</p>
        <div style="background-color: #2d2d2d; border-radius: 8px; padding: 20px; overflow-x: auto;">
          <code style="color: #f8f8f2; font-family: 'Fira Code', 'Consolas', monospace; font-size: 13px; line-height: 1.5; white-space: pre-wrap; word-break: break-word;">${errorMessage.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</code>
        </div>
      </td>
    </tr>
    
    <!-- ARGOS Analysis -->
    <tr>
      <td style="padding: 0 40px 30px 40px;">
        <p style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #212529;">🤖 Análisis de ARGOS</p>
        <div style="background-color: #e8f4fd; border-left: 4px solid #0d6efd; border-radius: 0 8px 8px 0; padding: 20px;">
          <p style="margin: 0; font-size: 14px; color: #212529; line-height: 1.6; white-space: pre-wrap;">${argosOutput.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>
        </div>
      </td>
    </tr>
    
    <!-- CTA Button -->
    <tr>
      <td style="padding: 0 40px 30px 40px; text-align: center;">
        <a href="https://automatizatech.cl/admin-tools/" style="display: inline-block; background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 14px;">Ver Panel de Errores →</a>
      </td>
    </tr>
    
    <!-- Footer -->
    <tr>
      <td style="background-color: #1a1a2e; padding: 25px 40px; text-align: center;">
        <p style="margin: 0; color: #a0a0a0; font-size: 13px;">👁️ Argos vigila. Argos protege.</p>
        <p style="margin: 10px 0 0 0; color: #6c757d; font-size: 11px;">AutomatizaTech © 2026 - Sistema de Monitoreo</p>
      </td>
    </tr>
    
  </table>
</body>
</html>
`;

// Body para WhatsApp API
const whatsappBody = {
  messaging_product: 'whatsapp',
  recipient_type: 'individual',
  to: '56974940070',
  type: 'text',
  text: {
    preview_url: false,
    body: waMessage
  }
};

return {
  json: {
    workflow_name: workflowName,
    workflow_id: errorData.workflow?.id || '',
    execution_id: executionId,
    error_message: errorMessage,
    error_node: errorNode,
    error_stack: errSrc.stack || 'No disponible',
    error_timestamp: new Date().toISOString(),
    execution_mode: executionMode,
    argos_analysis: argosOutput,
    is_recurring: isRecurring,
    occurrence_count: occurrenceCount,
    whatsappBody: JSON.stringify(whatsappBody),
    emailHtml: emailHtml
  }
};