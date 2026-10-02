"""Plantilla de alertas de ARGOS, aprobada por Luis el 2026-09-24. Ejemplos inventados."""

PLANTILLAS = [
    {
        "name": "alerta_argos", "language": "es", "category": "UTILITY",
        "components": [
            {"type": "BODY",
             "text": ("🛡️ *ARGOS · Error en n8n*\n\n"
                      "⚠️ *Severidad:* {{1}}\n"
                      "📋 *Workflow:* {{2}}\n"
                      "🔧 *Nodo:* {{3}}\n"
                      "⏰ *Hora:* {{4}}\n\n"
                      "💬 *Error:* {{5}}\n\n"
                      "🤖 *Análisis:* {{6}}\n\n"
                      "👁️ Argos vigila. Argos protege."),
             "example": {"body_text": [["MEDIO · nuevo", "Recordatorio 24h", "Send WhatsApp", "24-09-2026, 15:47",
                                        "Bad request - please check your parameters",
                                        "El envío falló porque la plantilla todavía no estaba aprobada."]]}},
            {"type": "BUTTONS", "buttons": [
                {"type": "URL", "text": "Ver panel de errores", "url": "https://automatizatech.cl/admin-tools/"}]},
        ],
    },
]
