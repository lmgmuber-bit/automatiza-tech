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

🤖 Bot: "¡Excelente! Te muestro los horarios disponibles 
         para tu evento..."
```

---

## ⚠️ Puntos Importantes

### ✅ Lo que SÍ pasa:

| Situación | Resultado |
|-----------|-----------|
| Usas `/tomar` con Cliente A | Bot se silencia **solo para Cliente A** |
| Cliente B escribe | Bot **sigue respondiendo** a Cliente B |
| Cliente C escribe | Bot **sigue respondiendo** a Cliente C |
| Tus mensajes al cliente | Se **guardan en memoria** del bot |

### ❌ Lo que NO pasa:

| Situación | Resultado |
|-----------|-----------|
| Respondes SIN usar `/tomar` | Bot **sigue activo** y puede responder también |
| Olvidas escribir `/bot` | Bot **queda silenciado** indefinidamente para ese cliente |

---

## 🧠 El Bot Recuerda Todo

Cuando tomas el control y conversas con el cliente, **el bot guarda esa conversación en su memoria**.

### ¿Qué significa esto?

- Si le diste un descuento especial → El bot lo recordará
- Si acordaron algo específico → El bot lo tendrá presente
- Si el cliente regresa después → El bot dará continuidad

### Ejemplo:

```
📅 Lunes (tú atendiste):
🙋 Tú: "Te hago 20% de descuento por ser cliente frecuente"

📅 Jueves (bot atiende):
👤 Cliente: "Hola, el otro día me dieron un descuento"
🤖 Bot: "¡Hola! Sí, veo que tienes un 20% de descuento 
         por ser cliente frecuente. ¿Deseas agendar?"
```

---

## 📊 Duración de la Memoria

| Aspecto | Duración |
|---------|----------|
| Historial de conversaciones | **6 meses** |
| Mensajes recordados por sesión | **200 mensajes** |

> Después de 6 meses sin actividad, el historial de un cliente se borra automáticamente.

---

## 📋 Resumen Rápido

```
╔═══════════════════════════════════════════════════════════╗
║                                                           ║
║   🙋 PARA ATENDER PERSONALMENTE:     /tomar              ║
║                                                           ║
║   🤖 PARA QUE EL BOT RETOME:         /bot                ║
║                                                           ║
╚═══════════════════════════════════════════════════════════╝
```

---

## ❓ Preguntas Frecuentes

**¿Qué pasa si respondo sin usar /tomar?**
> El bot sigue activo y podría responder también al cliente, causando confusión.

**¿Puedo usar /tomar con varios clientes a la vez?**
> Sí, cada cliente es independiente. Puedes tener tomado el control de varios chats simultáneamente.

**¿El cliente ve los comandos /tomar y /bot?**
> Sí, el cliente verá esos mensajes. Son comandos que tú envías en el chat.

**¿Qué pasa si nunca escribo /bot?**
> El bot quedará silenciado permanentemente para ese cliente hasta que uses /bot.

**¿El bot sabe lo que hablé con el cliente?**
> Sí, todos tus mensajes se guardan y el bot tendrá contexto de la conversación.

---

## 🆘 Soporte

Si tienes dudas o problemas con esta funcionalidad, contacta a:

📧 **contacto@automatizatech.cl**
📱 **+56 9 2700 2984**

---

*Documentación v6.1 - WhatsApp Bot con Intervención Humana*
