"""Genera argos_preparar_datos.js a partir del Preparar Datos publicado (preparar_datos_actual.js):
reemplaza solo el bloque del mensaje de WhatsApp y el cuerpo del envio. Lo demas queda identico."""
import io
import sys

sys.stdout.reconfigure(encoding="utf-8")
c = io.open("preparar_datos_actual.js", encoding="utf-8").read()

# 1) bloque "FORMATO WHATSAPP" (desde su linea de ═══ hasta la linea de ═══ que abre "FORMATO EMAIL")
i_wa = c.index("// FORMATO WHATSAPP")
ini = c.rindex("// ═", 0, i_wa)
i_mail = c.index("// FORMATO EMAIL")
fin = c.rindex("// ═", 0, i_mail)
assert c[ini:fin].count("const waMessage") == 1, "el bloque de WhatsApp no es el esperado"

nuevo_wa = """// ═══════════════════════════════════════════════════════════════
// WHATSAPP - plantilla aprobada por Meta (alerta_argos)
// Con type 'text' Meta descartaba el aviso (error 131047) si Luis no le habia
// escrito al numero en las ultimas 24 h. La plantilla llega siempre.
// ═══════════════════════════════════════════════════════════════
// Por precaucion: parametros de una sola linea y el cuerpo completo bajo el limite de 1024 de Meta
const plano = (v, porDefecto) => String(v ?? '').replace(/\\s+/g, ' ').trim() || porDefecto;
const corta = (s, max) => (s.length > max ? s.slice(0, Math.max(0, max - 1)).trimEnd() + '…' : s);
const CUERPO_ARGOS = '🛡️ *ARGOS · Error en n8n*\\n\\n⚠️ *Severidad:* {{1}}\\n📋 *Workflow:* {{2}}\\n🔧 *Nodo:* {{3}}\\n⏰ *Hora:* {{4}}\\n\\n💬 *Error:* {{5}}\\n\\n🤖 *Análisis:* {{6}}\\n\\n👁️ Argos vigila. Argos protege.';
const severidadTxt = severity.replace(/🔴|🟠|🟡/g, '').trim() + (isRecurring ? ` · recurrente #${occurrenceCount}` : ' · nuevo');
const waParams = [
  corta(plano(severidadTxt, 'MEDIO · nuevo'), 40),
  corta(plano(workflowName, 'Unknown'), 90),
  corta(plano(errorNode, 'Desconocido'), 60),
  corta(plano(timestamp, '-'), 30),
  corta(plano(errorMessage, 'Sin mensaje'), 250)
];
const LIMITE_ARGOS = 1000;
const usadoArgos = CUERPO_ARGOS.replace(/\\{\\{\\d\\}\\}/g, '').length + waParams.reduce((a, s) => a + s.length, 0);
waParams.push(corta(plano(argosOutput, 'Sin análisis'), Math.max(100, LIMITE_ARGOS - usadoArgos)));

"""
c = c[:ini] + nuevo_wa + c[fin:]

# 2) cuerpo del envio: de type 'text' a la plantilla
k = c.index("// Body para WhatsApp API")
l = c.index("\n};\n", k) + 4
viejo_body = c[k:l]
assert "type: 'text'" in viejo_body and "body: waMessage" in viejo_body, "el cuerpo del envio no es el esperado"
nuevo_body = """// Body para WhatsApp API (plantilla alerta_argos; el boton "Ver panel de errores" es fijo y no lleva parametros)
const whatsappBody = {
  messaging_product: 'whatsapp',
  recipient_type: 'individual',
  to: '56974940070',
  type: 'template',
  template: {
    name: 'alerta_argos',
    language: { code: 'es' },
    components: [
      { type: 'body', parameters: waParams.map(text => ({ type: 'text', text })) }
    ]
  }
};
"""
c = c[:k] + nuevo_body + c[l:]

# 3) freno pedido por Luis el 24-sep: WhatsApp una sola vez por error; correo y guardado siempre
k2 = c.index("return {")
c = c[:k2] + """// WhatsApp una sola vez por cada error (Luis, 24-sep): solo si no hay un error igual
// (mismo workflow, nodo y mensaje) en los ultimos 30 dias. El correo y el guardado salen siempre.
const enviarWhatsApp = (historial.summary?.errors_exact_match || 0) === 0;

""" + c[k2:]
assert c.count("    whatsappBody: JSON.stringify(whatsappBody),") == 1
c = c.replace("    whatsappBody: JSON.stringify(whatsappBody),",
              "    whatsappBody: JSON.stringify(whatsappBody),\n    enviar_whatsapp: enviarWhatsApp,")
assert "enviar_whatsapp: enviarWhatsApp," in c
assert "waMessage" not in c, "quedo alguna referencia al mensaje viejo"
assert "errorData.trigger?.error" in c, "se perdio el fix de trigger.error"
assert "emailHtml" in c and "whatsappBody: JSON.stringify(whatsappBody)" in c
io.open("argos_preparar_datos.js", "w", encoding="utf-8").write(c)
print("ok: argos_preparar_datos.js generado;", len(c), "caracteres")
