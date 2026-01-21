# 💳 Sistema de Validación de Pago - Kells Capilar

## 📋 Descripción General

Este sistema implementa una validación de pago por transferencia bancaria **antes** de confirmar una cita. El flujo es:

1. Cliente selecciona servicio → día → hora
2. Se crea cita con estado "**Pendiente Pago**"
3. Bot envía instrucciones de transferencia con datos bancarios
4. Cliente envía captura de pantalla del comprobante
5. **GPT-4 Vision** analiza la imagen y valida:
   - Cuenta destino correcta
   - Transferencia reciente (no antigua)
6. Si es válido → Cita confirmada
7. Si no es válido → Se pide nuevo comprobante

---

## 🏦 Datos Bancarios Configurados

```
Nombre: KELLYS TIRADO
RUT: 26.312.327-1
Banco: Banco Itaú
Tipo: Cuenta Corriente
Número: 0224080048
Email: KELLYSISABEL1504@GMAIL.COM
```

---

## 💰 Condiciones de Agendamiento

- **Abono requerido**: $20,000 CLP
- **No reembolsable**: El abono no se devuelve
- **Cambio de hora**: Máximo 1 cambio con 24 horas de anticipación
- **Tiempo límite**: 30 minutos para enviar comprobante

---

## 🔄 Flujo de Nodos

```
Webhook 
    ↓
Extract Message Data (detecta hasImage)
    ↓
Redis Push (guarda imagen info)
    ↓
Combine Messages (pasa hasImage)
    ↓
Tipo de Mensaje [Switch]
    ├── Servicio → selección servicio
    ├── Día → selección día  
    ├── Hora → Build Appointment → Send Confirmation (instrucciones de pago)
    │          (guarda en staticData.pendingPayments, estado "Pendiente Pago")
    │          ❌ NO se guarda en Google Sheets aún
    │          ❌ NO se envía email al negocio aún
    ├── ...
    └── ImagenComprobante → Validate Payment Image
                               ↓
                          Payment Status Check [Switch]
                               ├── ValidarComprobante → Download Image → GPT4 Vision → Process Result
                               │                                                           ↓
                               │                                                    Validation Result [Switch]
                               │                                                           ├── Válido → Prepare Confirmed Data
                               │                                                           │              ↓
                               │                                                           │           Save Confirmed Appointment (Google Sheets)
                               │                                                           │              ↓
                               │                                                           │           ┌──────────────────────────┐
                               │                                                           │           │ Send Payment Confirmed   │
                               │                                                           │           │ Send Email New Appointment│ ← EMAIL SOLO AQUÍ
                               │                                                           │           └──────────────────────────┘
                               │                                                           └── Inválido → Send Payment Invalid
                               └── PagoExpirado → Send Payment Expired
```

### ⚠️ IMPORTANTE: Orden de operaciones

1. **Al seleccionar hora**: Solo se guarda en memoria (`staticData.pendingPayments`), NO en Google Sheets
2. **Al validar pago exitosamente**: Se guarda en Google Sheets + se envía email al negocio
3. El email de confirmación **SOLO se envía después de validar el pago**

---

## 📸 Nodos Agregados para Validación de Imagen

### 1. **Validate Payment Image** (Code)
- Verifica si hay pago pendiente para el número
- Verifica si no ha expirado (30 min)
- Prepara datos para validación

### 2. **Payment Status Check** (Switch)
- `ValidarComprobante`: Hay pago pendiente válido
- `PagoExpirado`: El tiempo expiró
- `Fallback`: No hay pago pendiente (ignora imagen)

### 3. **Download Payment Image** (HTTP Request)
- Descarga la imagen desde YCloud
- La imagen se pasa como binario al siguiente nodo

### 4. **GPT4 Vision Validate** (HTTP Request)
- Envía imagen a GPT-4o con Vision
- Prompt especializado para validar transferencias chilenas
- Responde en JSON con resultado de validación

### 5. **Process Validation Result** (Code)
- Parsea la respuesta de GPT-4
- Determina si la validación es exitosa
- Limpia pago pendiente si es válido

### 6. **Validation Result** (Switch)
- `Válido`: Transferencia verificada
- `Inválido`: Comprobante rechazado

### 7. **Prepare Confirmed Data** (Code)
- Prepara datos para Google Sheets
- Cambia estado a "Confirmado"

### 8. **Save Confirmed Appointment** (Google Sheets)
- Guarda la cita confirmada

### 9. **Send Payment Confirmed** (HTTP Request)
- Envía mensaje de confirmación al cliente
- Incluye detalles de la cita y código

### 10. **Send Payment Invalid** (HTTP Request)
- Envía mensaje de rechazo
- Explica razón y solicita nuevo comprobante

### 11. **Send Payment Expired** (HTTP Request)
- Notifica que el tiempo expiró
- Invita a volver a agendar

---

## 🤖 Prompt de GPT-4 Vision

El sistema usa un prompt especializado que valida:

1. **Destinatario**: Nombre similar a KELLYS TIRADO o Kelly Tirado
2. **RUT**: 26.312.327-1 (con o sin puntos)
3. **Banco**: Itaú o Banco Itaú
4. **Cuenta**: Contiene 0224080048
5. **Fecha**: Transferencia reciente (últimas 24 horas)
6. **Autenticidad**: Parece comprobante real (no editado)

### Respuesta JSON del validador:
```json
{
  "valido": true/false,
  "confianza": "alta/media/baja",
  "razon": "explicación breve",
  "detalles": {
    "destinatario_detectado": "nombre encontrado",
    "rut_detectado": "rut encontrado",
    "banco_detectado": "banco encontrado",
    "cuenta_detectada": "número encontrado",
    "monto_detectado": "monto si es visible",
    "fecha_detectada": "fecha si es visible"
  }
}
```

---

## ⏱️ Timeouts y Expiración

- **Tiempo para enviar comprobante**: 30 minutos
- **Validez de transferencia**: Últimas 24 horas
- **Storage**: `staticData.pendingPayments[phoneNumber]`

---

## 📝 Estados de Cita

| Estado | Descripción |
|--------|-------------|
| `Pendiente Pago` | Cita reservada, esperando comprobante |
| `Confirmado` | Pago validado, cita confirmada |

---

## ⚠️ Consideraciones

1. **Credenciales OpenAI**: El nodo GPT4 Vision Validate necesita credenciales de OpenAI configuradas en n8n
2. **Modelo**: Usa `gpt-4o` que tiene capacidad de visión
3. **Costo**: Cada validación de imagen consume tokens de OpenAI (aprox $0.01-0.05 por imagen)
4. **Falsos positivos/negativos**: GPT-4 es muy preciso pero puede haber casos edge

---

## 🔧 Configuración Requerida

1. **Credenciales OpenAI** en n8n con acceso a GPT-4o
2. **Google Sheets** configurado con columna `estado`
3. **YCloud** debe entregar link de imagen accesible

---

## 📱 Mensajes al Cliente

### Instrucciones de Pago:
```
💳 *Instrucciones de Pago*

Para confirmar tu cita de [Servicio], debes realizar una transferencia:

📋 *Condiciones:*
- Para poder hacer efectiva la reserva... se requiere abono de $20.000
- Este monto no es reembolsable
- Puedes cambiar la hora máximo 1 vez con 24 horas de anticipación

🏦 *Datos para transferir:*
Nombre: KELLYS TIRADO
RUT: 26.312.327-1
Banco: Banco Itaú
Tipo: Cuenta Corriente
Número: 0224080048
Email: KELLYSISABEL1504@GMAIL.COM

📸 *Una vez realizada la transferencia, envía una captura de pantalla del comprobante*

⏰ Tienes 30 minutos para enviar el comprobante.
```

### Pago Confirmado:
```
✅ *¡Pago Validado y Cita Confirmada!*

[Nombre], tu comprobante de transferencia ha sido verificado correctamente.

📋 *Detalles de tu cita:*
🛍️ Servicio: [Servicio]
📅 Fecha: [Fecha]
🕐 Hora: [Hora]
🎫 Código: [ID]

📍 *Recuerda llegar con el cabello limpio*

¡Te esperamos! 💇‍♀️✨
```

### Pago Inválido:
```
❌ *Comprobante no válido*

Lo sentimos, no pudimos validar tu comprobante de transferencia.

📋 *Razón:* [Razón de GPT-4]

*Por favor verifica que:*
✅ El destinatario sea KELLYS TIRADO
✅ El RUT sea 26.312.327-1
✅ El banco sea Itaú
✅ La transferencia sea reciente

📸 Envía nuevamente una captura clara del comprobante.
```

### Tiempo Expirado:
```
⏰ *Tiempo Expirado*

El tiempo para enviar el comprobante ha expirado (30 minutos).

Si deseas agendar una cita, escríbeme 'quiero agendar'.
```

---

## 📊 Telemetría

La información de validación se registra en las notas de la cita:
```
Pago validado automáticamente por IA - [timestamp]
```
