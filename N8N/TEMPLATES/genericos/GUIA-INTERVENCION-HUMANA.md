# 🙋 Guía de Intervención Humana - WhatsApp Bot

## ¿Qué es esto?

Tu bot de WhatsApp atiende automáticamente a tus clientes. Pero cuando quieras atender **personalmente** a un cliente, puedes "tomar el control" del chat. El bot se quedará en silencio hasta que tú decidas devolverle el control.

---

## 📱 Comandos Disponibles

| Comando | Acción |
|---------|--------|
| `/tomar` | Tomas el control. El bot se silencia para ese cliente. |
| `/bot` | Devuelves el control. El bot vuelve a responder. |

> 💡 También funcionan: `/humano` (igual que /tomar) y `/liberar` (igual que /bot)

---

## 🔄 Flujo de Uso

```
┌─────────────────────────────────────────────────────────────┐
│                    FLUJO NORMAL                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   👤 Cliente escribe ──────► 🤖 Bot responde                │
│                                                              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│               CUANDO QUIERES INTERVENIR                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   👤 Cliente escribe                                         │
│         │                                                    │
│         ▼                                                    │
│   🤖 Bot responde                                           │
│         │                                                    │
│         ▼                                                    │
│   📱 Tú escribes: /tomar                                    │
│         │                                                    │
│         ▼                                                    │
│   🔇 Bot se SILENCIA para este cliente                      │
│         │                                                    │
│         ▼                                                    │
│   🙋 Tú atiendes personalmente                              │
│      (puedes enviar varios mensajes)                        │
│         │                                                    │
│         ▼                                                    │
│   📱 Tú escribes: /bot                                      │
│         │                                                    │
│         ▼                                                    │
│   🤖 Bot RETOMA el control                                  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 💬 Ejemplo Práctico

```
👤 Cliente: "Hola, tengo una consulta especial"

🤖 Bot: "¡Hola! ¿En qué puedo ayudarte?"

👤 Cliente: "Quiero un presupuesto personalizado para un evento"

────────────────────────────────────────────────
📱 TÚ ESCRIBES: /tomar
────────────────────────────────────────────────

👤 Cliente: "Es para un evento corporativo de 50 personas"

🙋 Tú: "¡Hola! Claro, para ese volumen te puedo hacer 
        un precio especial..."

🙋 Tú: "El valor sería $500.000 con 15% de descuento"

👤 Cliente: "Excelente, lo voy a pensar"

🙋 Tú: "Perfecto, cualquier duda me escribes"

────────────────────────────────────────────────
📱 TÚ ESCRIBES: /bot
────────────────────────────────────────────────

👤 Cliente: "Ok decidí que sí, quiero agendar"

🤖 Bot: "¡Excelente! Te muestro los horarios disponibles..."
```

---

## ⚠️ Puntos Importantes

### ✅ Lo que SÍ pasa:

1. **El control es por cliente** (número de teléfono)
   - Si tomas el control con un cliente, otros clientes siguen siendo atendidos por el bot

2. **Tus mensajes se guardan en la memoria**
   - Cuando el bot retome el control, sabrá qué hablaste con el cliente
   - Esto permite continuidad en la conversación

3. **El estado persiste**
   - Si cierras WhatsApp y vuelves, el control sigue siendo tuyo hasta que escribas `/bot`

### ❌ Lo que NO pasa:

1. **El bot NO responde mientras tienes el control**
   - El cliente solo ve tus mensajes

2. **Los comandos NO se envían al cliente**
   - `/tomar` y `/bot` son invisibles para el cliente

---

## 🔔 Cuándo Usar la Intervención

### Recomendado intervenir cuando:
- 📋 Cliente pide presupuesto personalizado
- 🎁 Quieres ofrecer descuento especial
- ❓ Consulta técnica muy específica
- 😤 Cliente molesto o frustrado
- 💼 Oportunidad de venta importante

### Dejar al bot cuando:
- 📅 Cliente quiere agendar cita normal
- ℹ️ Preguntas sobre horarios/servicios/precios
- 📍 Consultas de ubicación/contacto
- ❌ Cancelar o reagendar cita

---

## 📊 Memoria del Bot

El bot tiene memoria de **6 meses** que incluye:
- Todas las conversaciones del bot con el cliente
- **Todas tus conversaciones** cuando intervienes
- Contexto de citas anteriores

Esto significa que si atendiste personalmente a un cliente hace 2 meses, el bot lo recordará y podrá hacer referencia a esa conversación.

---

## 🆘 Soporte

¿Tienes dudas sobre cómo usar los comandos?
- 📧 Email: contacto@automatizatech.cl
- 📱 WhatsApp: +56 9 2700 2984
