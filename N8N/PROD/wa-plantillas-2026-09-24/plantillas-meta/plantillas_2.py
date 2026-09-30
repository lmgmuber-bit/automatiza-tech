"""Cinco plantillas aprobadas por Luis el 2026-09-24 (8 AM, 8 PM y Followup en 3 tipos).
Los ejemplos que exige Meta son inventados; nunca datos de clientes."""

MEET_EJ = "https://meet.google.com/abc-defg-hij"


def botones(*textos):
    # Meta rechaza emojis en botones de plantilla (error 100/2388060)
    return {"type": "BUTTONS", "buttons": [{"type": "QUICK_REPLY", "text": t} for t in textos]}


SEGUIMIENTO = botones("Confirmo", "Reagendar", "Cancelar")
FOLLOWUP = botones("Confirmar", "Reagendar", "Cancelar")

PLANTILLAS = [
    {
        "name": "recordatorio_seguimiento_hoy", "language": "es", "category": "UTILITY",
        "components": [
            {"type": "BODY",
             "text": ("☀️ *¡Tu reunión de seguimiento es HOY!*\n\n"
                      "¡Buenos días {{1}}!\n\n"
                      "Te recordamos tu reunión con AutomatizaTech programada para hoy.\n\n"
                      "📋 *Proyecto:* {{2}}\n"
                      "📅 *Fecha:* {{3}}\n"
                      "🕐 *Hora:* {{4}} hrs\n"
                      "📍 *Enlace:* {{5}}\n\n"
                      "💡 *Recordatorio:*\n"
                      "• Asegúrate de tener buena conexión a internet\n"
                      "• Si no ves el enlace arriba, revisa tu correo\n\n"
                      "¿Podrás asistir?"),
             "example": {"body_text": [["María de Empresa Demo", "Implementación de chatbot", "25-09-2026", "15:00", MEET_EJ]]}},
            SEGUIMIENTO,
        ],
    },
    {
        "name": "recordatorio_seguimiento_manana", "language": "es", "category": "UTILITY",
        "components": [
            {"type": "BODY",
             "text": ("📅 *Recordatorio: tu reunión de seguimiento es mañana*\n\n"
                      "Hola {{1}}, te recordamos tu próxima reunión con AutomatizaTech.\n\n"
                      "📋 *Proyecto:* {{2}}\n"
                      "📅 *Fecha:* {{3}}\n"
                      "🕐 *Hora:* {{4}} hrs\n"
                      "📍 *Enlace:* {{5}}\n\n"
                      "💡 *Recordatorio:*\n"
                      "• Recibirás otro recordatorio el día de la reunión\n"
                      "• Asegúrate de tener buena conexión a internet\n"
                      "• Si no ves el enlace arriba, revisa tu correo\n\n"
                      "¿Podrás asistir mañana?"),
             "example": {"body_text": [["María de Empresa Demo", "Implementación de chatbot", "26-09-2026", "15:00", MEET_EJ]]}},
            SEGUIMIENTO,
        ],
    },
    {
        "name": "aviso_demo", "language": "es", "category": "UTILITY",
        "components": [
            {"type": "BODY",
             "text": ("🚀 *Tu demo con AutomatizaTech fue {{1}}*\n\n"
                      "Hola {{2}}, estos son los datos:\n\n"
                      "📅 *Fecha:* {{3}}\n"
                      "🕐 *Hora:* {{4}} hrs (Chile)\n"
                      "💻 *Modalidad:* Videollamada por Google Meet\n"
                      "⏱️ *Duración:* aprox. 30 minutos\n\n"
                      "📧 Revisa tu correo: ahí está el enlace de la reunión.\n\n"
                      "¿Confirmas tu asistencia?"),
             "example": {"body_text": [["agendada", "María", "25-09-2026", "15:00"]]}},
            FOLLOWUP,
        ],
    },
    {
        "name": "aviso_reunion_prospecto", "language": "es", "category": "UTILITY",
        "components": [
            {"type": "BODY",
             "text": ("📅 *Tu reunión con AutomatizaTech fue {{1}}*\n\n"
                      "Hola {{2}}, estos son los datos:\n\n"
                      "📋 *Asunto:* {{3}}\n"
                      "📅 *Fecha:* {{4}}\n"
                      "🕐 *Hora:* {{5}} hrs (Chile)\n"
                      "💻 *Modalidad:* Videollamada por Google Meet\n\n"
                      "📋 *Tu seguimiento* (historial, propuesta y reuniones): {{6}}\n\n"
                      "📧 Revisa tu correo: ahí está el enlace de la reunión.\n\n"
                      "¿Confirmas tu asistencia?"),
             "example": {"body_text": [["agendada", "María", "Revisión de propuesta", "25-09-2026", "15:00",
                                        "https://automatizatech.cl/seguimiento/ejemplo"]]}},
            FOLLOWUP,
        ],
    },
    {
        "name": "aviso_reunion_seguimiento", "language": "es", "category": "UTILITY",
        "components": [
            {"type": "BODY",
             "text": ("📅 *Tu reunión de seguimiento fue {{1}}*\n\n"
                      "Hola {{2}}, estos son los datos de tu reunión con AutomatizaTech:\n\n"
                      "📋 *Proyecto:* {{3}}\n"
                      "📅 *Fecha:* {{4}}\n"
                      "🕐 *Hora:* {{5}} hrs (Chile)\n\n"
                      "📂 *Tu portal de cliente* (historial, pagos, entregables y MAXTECH, tu agente IA): {{6}}\n\n"
                      "📧 Revisa tu correo: ahí está el enlace de la reunión.\n\n"
                      "¿Confirmas tu asistencia?"),
             "example": {"body_text": [["agendada", "María", "Implementación de chatbot", "25-09-2026", "15:00",
                                        "https://automatizatech.cl/portal/ejemplo"]]}},
            FOLLOWUP,
        ],
    },
]
