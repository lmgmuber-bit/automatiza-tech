# Instrucciones: Procesamiento de Fechas en Texto Natural

## Flujo Implementado

Se ha agregado la funcionalidad para que los usuarios puedan escribir fechas en texto natural (ej: "15 de enero a las 14:00") en el workflow de WhatsApp.

## Nodos Agregados

### 1. **Parse Date from Text** (Code Node)
- **ID**: `wa-parse-date-text`
- **Función**: Parsea fechas en español usando patrones regex
- **Validaciones**:
  - Fecha no sea pasada
  - Día esté habilitado en configuración
  - No sea feriado
  - Horario dentro del rango configurado
  
### 2. **Is Date Valid?** (IF Node)
- **ID**: `wa-is-date-valid`
- **Función**: Valida si la fecha parseada es correcta
- **Salidas**:
  - TRUE → Pide email con "Ask Email for Custom Date"
  - FALSE → Muestra error con "Send Date Error"

### 3. **Ask Email for Custom Date** (HTTP Request)
- **ID**: `wa-ask-email-custom-date`
- **Función**: Solicita email del usuario mostrando la fecha elegida

### 4. **Send Date Error** (HTTP Request)
- **ID**: `wa-send-date-error`
- **Función**: Informa al usuario sobre el error en la fecha

### 5. **Get Config For Date Parsing** (HTTP Request)
- **ID**: `wa-get-config-for-parsing`
- **Función**: Obtiene configuración de horarios y feriados del API

## Formatos de Fecha Soportados

### Fechas:
- ✅ "15 de enero"
- ✅ "15 enero"
- ✅ "enero 15"
- ✅ "31 dic"
- ✅ "23 de diciembre"

### Horas:
- ✅ "14:00"
- ✅ "a las 14"
- ✅ "las 2 pm"
- ✅ "14 horas"
- ✅ "2 pm"

### Ejemplos Completos:
- ✅ "15 de enero a las 14:00"
- ✅ "23 dic 10:30"
- ✅ "enero 31 a las 16 horas"
- ✅ "5 de febrero las 2 pm"

## Integración con Webhook

Para activar el parseo de fechas, el webhook debe recibir:

```json
{
  "phoneNumber": "56912345678",
  "phoneNumberId": "123456789",
  "contactName": "Juan Pérez",
  "action": "parse_date",
  "dateText": "15 de enero a las 14:00"
}
```

## Flujo de Usuario

1. Usuario elige "📆 Otra fecha" en la lista de opciones
2. Bot responde con mensaje:
   - Opción 1: Escribir fecha en texto
   - Opción 2: Agendar por web
3. **Usuario escribe fecha** (ej: "15 de enero a las 14:00")
4. **Webhook debe capturar este mensaje y reenviarlo con `action: "parse_date"`**
5. Workflow parsea y valida la fecha
6. Si es válida: pide email
7. Si es inválida: muestra error y sugiere la web
8. Usuario proporciona email
9. Se crea la cita usando el workflow existente

## Consideraciones Importantes

### ⚠️ Configuración Necesaria

El webhook de entrada debe tener lógica para:

1. **Detectar contexto**: Si el último mensaje del bot fue "Send Other Date Options"
2. **Capturar respuesta**: Guardar el texto del usuario
3. **Reenviar con action**: Hacer POST al webhook con `action: "parse_date"` y `dateText: <mensaje usuario>`

### Ejemplo de Lógica de Webhook (Python):

```python
# En tu webhook que recibe mensajes de WhatsApp
if last_bot_message == "other_date_options":
    user_message = incoming_message.text
    
    # Enviar al workflow con action parse_date
    payload = {
        "phoneNumber": phone,
        "phoneNumberId": phone_id,
        "contactName": name,
        "action": "parse_date",
        "dateText": user_message
    }
    
    requests.post("https://n8n.automatizatech.cl/webhook/whatsapp-appointments", json=payload)
```

### Ejemplo de Lógica en N8N (Otro Workflow):

Si manejas los mensajes entrantes de WhatsApp en otro workflow de N8N:

```javascript
// Nodo Code para detectar y procesar
const message = $input.first().json;
const lastAction = $getWorkflowStaticData('lastAction');

// Si el último mensaje fue "other_date_options" y recibimos texto
if (lastAction === 'other_date_options' && message.type === 'text') {
  return {
    json: {
      phoneNumber: message.from,
      phoneNumberId: message.to,
      contactName: message.profile?.name || 'Cliente',
      action: 'parse_date',
      dateText: message.text.body
    }
  };
}

// Guardar última acción
$setWorkflowStaticData('lastAction', message.action);
```

## Validaciones Implementadas

✅ Fecha en formato válido español  
✅ Fecha no sea pasada  
✅ Día habilitado en configuración  
✅ No sea feriado  
✅ Horario dentro del rango del día  
✅ Hora válida (00:00 - 23:59)  
✅ Manejo de años (si mes ya pasó, usa año siguiente)

## Mensajes de Error

El bot enviará mensajes específicos según el error:

- **Formato inválido**: "No pude entender la fecha. Por favor usa el formato: '15 de enero a las 14:00' o agenda desde la web."
- **Fecha pasada**: "Esa fecha ya pasó. Por favor elige una fecha futura."
- **Día no habilitado**: "Lo siento, los [día]s no hay atención disponible. Por favor elige otro día."
- **Feriado**: "Ese día es feriado o está bloqueado. Por favor elige otra fecha."
- **Fuera de horario**: "El horario de atención ese día es de [inicio] a [fin] hrs. Por favor elige un horario dentro de ese rango."

## Testing

Para probar el flujo manualmente en N8N:

1. Ejecutar workflow con datos de prueba:
```json
{
  "phoneNumber": "56912345678",
  "phoneNumberId": "123456789",
  "contactName": "Test User",
  "action": "parse_date",
  "dateText": "15 de enero a las 14:00"
}
```

2. Verificar que pasa por:
   - Get Config For Date Parsing ✓
   - Parse Date from Text ✓
   - Is Date Valid? ✓
   - Ask Email for Custom Date ✓ (si válida)
   - Send Date Error ✓ (si inválida)

## Próximos Pasos

1. ✅ Subir archivo `api-appointments-config.php` por FTP
2. ✅ Importar `WhatsApp_Tech_Agendamiento_v3.json` en N8N
3. ⚠️ **Configurar webhook para capturar mensajes de texto y reenviar con `action: parse_date`**
4. ⚠️ Configurar credenciales de WhatsApp en N8N
5. ⚠️ Probar con fechas reales en WhatsApp
6. ⚠️ Validar que email capture funciona después de fecha custom
7. ⚠️ Verificar integración completa con workflow "Create Appointment"
