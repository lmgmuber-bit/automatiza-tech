# Sistema de Validación de Transfer_ID - Documentación de Cambios

**Fecha de Inicio:** Enero 2026  
**Estado Actual:** En Desarrollo (Problema de Flujo Identificado)  
**Archivo Principal:** `N8N/TEMPLATES/kellscapilar/WhatsApp_Bot_v6_KellsCapilar.json` (3,774 líneas)

---

## 📋 Tabla de Contenidos
1. [Objetivo General](#objetivo-general)
2. [Cambios Realizados](#cambios-realizados)
3. [Arquitectura del Sistema](#arquitectura-del-sistema)
4. [Problema Identificado](#problema-identificado)
5. [Soluciones Intentadas](#soluciones-intentadas)
6. [Código Implementado](#código-implementado)
7. [Estado Actual](#estado-actual)
8. [Próximos Pasos](#próximos-pasos)

---

## 🎯 Objetivo General

Implementar un sistema robusto que **prevenga el uso duplicado de transfer_ids (IDs de transferencia bancaria)** en el flujo de confirmación de citas de WhatsApp de Kells Capilar.

**Requisitos:**
- ✅ Extraer transfer_id de la imagen del comprobante bancario usando GPT-4 Vision
- ✅ Validar que el transfer_id no haya sido usado antes
- ✅ Comparar contra todas las citas confirmadas en Google Sheets
- ✅ Si hay duplicado: Enviar solo mensaje de error, SIN confirmación
- ✅ Si es válido: Guardar cita y enviar confirmación completa
- ✅ Diferenciar entre error de duplicado y error de validación de pago

---

## 📝 Cambios Realizados

### Fase 1: Extracción y Normalización de Transfer_ID

**Nodo:** `Validation Result` (Process Validation Result)  
**Línea:** ~530 en JSON  
**Cambio:** Agregado parseo de transfer_id desde respuesta de GPT-4 Vision

```javascript
// Extrae transfer_id normalizado de la validación de GPT-4
const validationText = input.validationResult || '';
const transferIdMatch = validationText.match(/#(\d+)/);
const transferId = transferIdMatch ? String(parseInt(transferIdMatch[1], 10)) : '';
```

**Propósito:** 
- Normaliza el ID eliminando ceros a la izquierda (`parseInt`)
- Permite comparación consistente contra Google Sheets

---

### Fase 2: Lectura de Todas las Citas Existentes

**Nodo:** `Get All Citas` (ID: `get-all-citas-validation`)  
**Tipo:** Google Sheets Read  
**Línea:** ~750 en JSON  
**Cambio:** Nuevo nodo agregado

```json
{
  "parameters": {
    "documentId": {
      "__rl": true,
      "value": "1ww6qJe057_HUaPTWgxT9pU1cfp8-HqmLecZjmYGB6Ps",
      "mode": "id"
    },
    "sheetName": {
      "__rl": true,
      "mode": "id",
      "value": 1703053541,
      "cachedResultName": "Citas"
    },
    "options": {}
  },
  "type": "n8n-nodes-base.googleSheets",
  "typeVersion": 4.4,
  "position": [364400, 178850],
  "id": "get-all-citas-validation",
  "name": "Get All Citas"
}
```

**Propósito:** 
- Lee TODAS las filas de Google Sheets
- Obtiene array completo de citas confirmadas
- Pasa datos al siguiente nodo para comparación

---

### Fase 3: Merge de Datos de Validación con Citas

**Nodo:** `Merge Citas with Validation` (ID: `merge-citas-validation`)  
**Tipo:** Code Node  
**Línea:** ~770 en JSON  
**Cambio:** Nuevo nodo agregado

```javascript
// Combinar datos de validación con citas existentes
const validationData = $('Validation Result').first().json;
const allCitasData = $input.all();

return [{
  json: {
    ...validationData,
    allCitas: allCitasData.map(item => item.json)
  }
}];
```

**Propósito:**
- Combina datos de validación del pago con array de citas
- Pasa todo junto al siguiente nodo para decisión
- Estructura: `{ transferId, phoneNumber, allCitas: [...] }`

---

### Fase 4: Validación de Uniqueness - Prepare Confirmed Data

**Nodo:** `Prepare Confirmed Data` (ID: `4acb2844-43ad-4a66-b7ba-aa9eb175df10`)  
**Tipo:** Code Node  
**Línea:** ~675 en JSON  
**Cambio:** REEMPLAZADO completamente

```javascript
// ===========================================
// VALIDATE TRANSFER_ID UNIQUENESS AGAINST GOOGLE SHEETS
// ===========================================
const input = $json;
const transferId = input.transferId || input.validationResult?.transfer_id_normalized || '';

// Get all existing citas from Google Sheets (passed from previous node)
const allCitas = input.allCitas || [];

// Check if transfer_id already exists in any confirmed appointment
if (transferId) {
  const transferIdStr = String(transferId).trim();
  
  // Check against all confirmed citas in Google Sheets
  const duplicateInSheets = allCitas.some(cita => {
    const citaTransferId = String(cita.transfer_id || cita.id_transferencia || '').trim();
    const citaEstado = String(cita.estado || '').toLowerCase();
    return citaTransferId === transferIdStr && 
           (citaEstado === 'confirmado' || citaEstado === 'pago validado');
  });
  
  if (duplicateInSheets) {
    return [{
      json: {
        error: true,
        mensaje: '❌ *Este ID de transferencia ya fue utilizado*\\n\\nNo puedes usar el mismo comprobante de pago dos veces. Por favor, verifica el número de transferencia e intenta con otro comprobante.',
        transferId: transferId,
        skipAppointment: true
      }
    }];
  }
}

// ===========================================
// PREPARE CONFIRMED APPOINTMENT FOR GOOGLE SHEETS
// ===========================================
const appointment = input.pendingAppointment;
const staticData = $getWorkflowStaticData('global');
const phoneNumber = input.phoneNumber;

// Clear pending payment and selection
if (staticData.pendingPayments) {
  delete staticData.pendingPayments[phoneNumber];
}
if (staticData.serviceSelections) {
  delete staticData.serviceSelections[phoneNumber];
}

// Get business config if available
const businessConfig = staticData.businessConfig || {};

// Get binary data (payment proof image)
const binaryData = $binary;

// Return data for Google Sheets with status CONFIRMED
return [{
  json: {
    id: appointment.id || appointment.appointmentId,
    nombre: appointment.nombre || appointment.contactName,
    email: '',
    telefono: appointment.telefono || appointment.phoneNumber,
    servicio: appointment.servicio,
    fecha: appointment.fecha || appointment.selectedDay,
    hora: appointment.hora,
    hora_fin: appointment.hora_fin,
    estado: 'Confirmado',
    precio: appointment.precio,
    notas: 'Pago validado automáticamente por IA - ' + new Date().toISOString(),
    creado: appointment.creado || appointment.createdAt || new Date().toISOString(),
    negocio_id: appointment.negocio_id || '1',
    negocio_email: businessConfig.email || 'Kellysisabel1504@gmail.com',
    negocio_nombre: businessConfig.nombre || 'Kells Capilar',
    displayDate: appointment.displayDate || appointment.fecha,
    transfer_id: transferId
  },
  binary: binaryData
}];
```

**Propósito:**
- Valida si transfer_id ya existe en Google Sheets
- Si existe con estado "confirmado" o "pago validado" → Retorna error + `skipAppointment: true`
- Si NO existe → Prepara datos para guardar en Google Sheets
- **CRÍTICO:** Setea flag `skipAppointment: true` para que Switch Node lo detecte

---

### Fase 5: Switch Node de 3 Outputs

**Nodo:** `Validate Transfer ID Error` (ID: `validate-transfer-id-error`)  
**Tipo:** Switch Node  
**Línea:** ~800 en JSON  
**Cambio:** Modificado de 2 a 3 outputs

```json
{
  "parameters": {
    "rules": {
      "values": [
        {
          "conditions": {
            "options": {
              "caseSensitive": true,
              "leftValue": "",
              "typeValidation": "strict",
              "version": 1
            },
            "conditions": [
              {
                "leftValue": "={{ $json.skipAppointment }}",
                "rightValue": true,
                "operator": {
                  "type": "boolean",
                  "operation": "equals"
                }
              }
            ],
            "combinator": "and"
          },
          "renameOutput": true,
          "outputKey": "DuplicateTransferId"
        },
        {
          "conditions": {
            "options": {
              "caseSensitive": true,
              "leftValue": "",
              "typeValidation": "strict",
              "version": 1
            },
            "conditions": [
              {
                "leftValue": "={{ $json.error }}",
                "rightValue": true,
                "operator": {
                  "type": "boolean",
                  "operation": "equals"
                }
              },
              {
                "leftValue": "={{ $json.skipAppointment }}",
                "rightValue": true,
                "operator": {
                  "type": "boolean",
                  "operation": "equals"
                },
                "operator": {
                  "type": "boolean",
                  "operation": "notEquals"
                }
              }
            ],
            "combinator": "and"
          },
          "renameOutput": true,
          "outputKey": "PaymentError"
        },
        {
          "conditions": {
            "options": {
              "caseSensitive": true,
              "leftValue": "",
              "typeValidation": "strict",
              "version": 1
            },
            "conditions": [
              {
                "leftValue": "={{ $json.error }}",
                "rightValue": true,
                "operator": {
                  "type": "boolean",
                  "operation": "notEquals"
                }
              }
            ],
            "combinator": "and"
          },
          "renameOutput": true,
          "outputKey": "Valid"
        }
      ]
    }
  },
  "type": "n8n-nodes-base.switchNode",
  "typeVersion": 1,
  "position": [364640, 178960],
  "id": "validate-transfer-id-error",
  "name": "Validate Transfer ID Error"
}
```

**Lógica de Outputs:**
- **Output 0 (DuplicateTransferId):** `$json.skipAppointment === true` → Duplicado detectado
- **Output 1 (PaymentError):** `$json.error === true && $json.skipAppointment !== true` → Error de validación
- **Output 2 (Valid):** `$json.error !== true` → Todo válido, proceder

---

### Fase 6: Nodo de Error para Duplicado

**Nodo:** `Send Duplicate Transfer ID` (ID: `send-duplicate-transfer-id-error`)  
**Tipo:** HTTP Request (WhatsApp API)  
**Línea:** ~630 en JSON  
**Cambio:** Nuevo nodo agregado

```json
{
  "parameters": {
    "method": "POST",
    "url": "=https://api.ycloud.com/v2/whatsapp/messages",
    "authentication": "predefinedCredentialType",
    "nodeCredentialType": "httpHeaderAuth",
    "sendBody": true,
    "specifyBody": "json",
    "jsonBody": "={{ (function() { const data = $json; return JSON.stringify({ from: data.businessPhone, to: data.phoneNumber, type: 'text', text: { body: '⚠️ *Transferencia ya registrada*\\n\\nLo sentimos, el ID de transferencia #' + data.transferId + ' ya fue utilizado para confirmar otra cita.\\n\\n🔄 *Por favor:*\\n\\n1️⃣ Realiza una nueva transferencia\\n2️⃣ Toma una captura de pantalla del nuevo comprobante\\n3️⃣ Envíamela para validar tu pago\\n\\n💡 Recuerda que cada transferencia debe tener un ID de transferencia único.\\n\\n¿Necesitas ayuda? Estamos aquí para asistirte 😊' } }); })() }}",
    "options": {}
  },
  "type": "n8n-nodes-base.httpRequest",
  "typeVersion": 4.1,
  "position": [364350, 179250],
  "id": "send-duplicate-transfer-id-error",
  "name": "Send Duplicate Transfer ID",
  "credentials": {
    "httpHeaderAuth": {
      "id": "cDHuTtbqeib255B5",
      "name": "Header Auth WB YcloudDemo"
    }
  }
}
```

**Mensaje Enviado:**
```
⚠️ *Transferencia ya registrada*

Lo sentimos, el ID de transferencia #[ID] ya fue utilizado para confirmar otra cita.

🔄 *Por favor:*

1️⃣ Realiza una nueva transferencia
2️⃣ Toma una captura de pantalla del nuevo comprobante
3️⃣ Envíamela para validar tu pago

💡 Recuerda que cada transferencia debe tener un ID de transferencia único.

¿Necesitas ayuda? Estamos aquí para asistirte 😊
```

---

### Fase 7: Conexiones del Switch Node

**Línea:** ~3062 en JSON  
**Cambio:** Actualizado connections

```json
"Validate Transfer ID Error": {
  "main": [
    [
      {
        "node": "Send Duplicate Transfer ID",
        "type": "main",
        "index": 0
      }
    ],
    [
      {
        "node": "Send Payment Invalid",
        "type": "main",
        "index": 0
      }
    ],
    [
      {
        "node": "Prepare Confirmed Data",
        "type": "main",
        "index": 0
      }
    ]
  ]
}
```

**Flujo:**
- Output 0 → Send Duplicate Transfer ID (nodo de error)
- Output 1 → Send Payment Invalid (nodo de error)
- Output 2 → Prepare Confirmed Data → Save → Confirmation

---

## 🏗️ Arquitectura del Sistema

### Flujo Completo (Teórico - Objetivo)

```
┌─────────────────────────────────────────────────────────────────┐
│ WhatsApp Event Trigger (Usuario envía comprobante de pago)      │
└──────────────────────┬──────────────────────────────────────────┘
                       ↓
        ┌──────────────────────────────┐
        │ Download Payment Image       │
        │ (Descarga comprobante)       │
        └──────────────┬───────────────┘
                       ↓
        ┌──────────────────────────────┐
        │ GPT4 Vision Validate         │
        │ (Extrae transfer_id)         │
        └──────────────┬───────────────┘
                       ↓
        ┌──────────────────────────────┐
        │ Get All Citas                │
        │ (Lee todas las citas)        │
        └──────────────┬───────────────┘
                       ↓
        ┌──────────────────────────────────┐
        │ Merge Citas with Validation      │
        │ (Combina datos)                  │
        └──────────────┬────────────────────┘
                       ↓
        ┌──────────────────────────────┐
        │ Prepare Confirmed Data       │
        │ (Valida uniqueness)          │
        └──────────────┬───────────────┘
                       ↓
    ┌──────────────────────────────────────┐
    │ Validate Transfer ID Error           │
    │ (Switch con 3 outputs)               │
    └─────┬────────────────┬──────────────┬┘
          │                │              │
   ╔══════╩═╗      ╔══════╩════╗    ╔════╩══════╗
   ║Output 0║      ║ Output 1  ║    ║ Output 2  ║
   ║Duplicate║      ║ Error    ║    ║ Valid     ║
   ╚════┬════╝      ╚════┬─────╝    ╚────┬──────╝
        │                │              │
        ↓                ↓              ↓
  ┌──────────────┐ ┌───────────────┐ ┌──────────────────┐
  │Send Duplicate│ │Send Payment   │ │Prepare Confirmed │
  │Message (WA)  │ │Invalid (WA)   │ │Data (Already sent)
  └──────────────┘ └───────────────┘ └─────────┬────────┘
                                               ↓
                                      ┌──────────────────┐
                                      │Save Confirmed    │
                                      │Appointment (GS)  │
                                      └─────────┬────────┘
                                               ↓
                                      ┌──────────────────┐
                                      │Send Payment      │
                                      │Confirmed (WA)    │
                                      └─────────┬────────┘
                                               ↓
                                      ┌──────────────────┐
                                      │Send Email        │
                                      │Confirmation      │
                                      └──────────────────┘
```

---

## ❌ Problema Identificado

### Síntoma Observado (Reporte del Usuario)

**Test realizado:** Usuario envió comprobante con transfer_id duplicado (13030940)

**Comportamiento esperado:**
```
✅ Detectar duplicado
✅ Enviar solo mensaje "Transferencia ya registrada"
❌ NO enviar confirmación
❌ NO enviar email
❌ NO guardar cita
```

**Comportamiento real:**
```
✅ Detectar duplicado (visible en column "skipAppointment" = TRUE)
✅ Enviar mensaje "Transferencia ya registrada"
❌ TAMBIÉN enviar mensaje WhatsApp "✅ ¡Pago Validado y Cita Confirmada!"
❌ TAMBIÉN enviar email con detalles de la cita
❌ Cita NO aparece en la hoja principal de citas (error silencioso)
```

### Root Cause Analysis

**Problema 1: Referenciación Directa en Templates**

Los nodos `Send Payment Confirmed` y `Send Email Now Appointment` (línea ~1498-1502) hacen referencia directa:

```javascript
$('Prepare Confirmed Data').first().json
```

Esto significa que si `Prepare Confirmed Data` ha ejecutado alguna vez en el flujo, estos nodos tendrán datos disponibles SIN IMPORTAR de qué output del Switch Node provengan.

**Problema 2: Conexiones de Nodos**

Línea ~3010:
```json
"Save Confirmed Appointment": {
  "main": [
    [
      {
        "node": "Send Payment Confirmed",
        "type": "main",
        "index": 0
      }
    ]
  ]
}
```

El flujo es:
```
Prepare Confirmed Data → Save Confirmed Appointment → Send Payment Confirmed
```

Pero `Save Confirmed Appointment` se ejecuta AUNQUE haya venido del output de error del Switch.

**Problema 3: Falta de Control de Flujo**

No hay nodos "Stop" o "Continue" que detengan la ejecución en los branches de error.

```
❌ FALTA: Send Duplicate Transfer ID → [STOP] ⛔
❌ FALTA: Send Payment Invalid → [STOP] ⛔
```

---

## 🔄 Soluciones Intentadas

### Intento 1: Validación en Memoria (FALLIDO)
- **Método:** Guardar transfer_ids en cache local durante sesión
- **Problema:** Se perdía al reiniciar el workflow
- **Razón por la que falló:** n8n reinicia variables globales frecuentemente
- **Decisión:** Cambiar a Google Sheets persistent storage

### Intento 2: Switch Node con 2 Outputs (INSUFICIENTE)
- **Método:** Dividir en "Error" vs "Valid"
- **Problema:** No distinguía entre duplicado y error de validación
- **Decisión:** Expandir a 3 outputs con lógica más precisa

### Intento 3: Agregar Lógica en Switch (PARCIALMENTE EFECTIVO)
- **Método:** Configurar Switch con 3 reglas precisas
- **Resultado:** ✅ Switch routing está bien configurado
- **Pero:** ❌ Downstream nodes ignoran el routing

### Intento 4: Validación Dupla (TRABAJANDO)
- **Método:** Comparación en memoria + Google Sheets
- **Resultado:** ✅ Detecta duplicado correctamente
- **Pero:** ❌ Flujo no respeta el resultado

---

## 💻 Código Implementado

### 1. Transfer_ID Extraction (Validation Result Node)
```javascript
// Extrae y normaliza transfer_id
const validationText = input.validationResult || '';
const transferIdMatch = validationText.match(/#(\d+)/);
const transferId = transferIdMatch ? String(parseInt(transferIdMatch[1], 10)) : '';
// Resultado: "13030940" (sin ceros a izquierda)
```

### 2. Uniqueness Validation (Prepare Confirmed Data)
```javascript
const duplicateInSheets = allCitas.some(cita => {
  const citaTransferId = String(cita.transfer_id || cita.id_transferencia || '').trim();
  const citaEstado = String(cita.estado || '').toLowerCase();
  return citaTransferId === transferIdStr && 
         (citaEstado === 'confirmado' || citaEstado === 'pago validado');
});

if (duplicateInSheets) {
  return [{
    json: {
      error: true,
      skipAppointment: true,  // ← CRITICAL FLAG
      transferId: transferId
    }
  }];
}
```

### 3. Switch Node Rules
```javascript
// Rule 0: DuplicateTransferId
$json.skipAppointment === true

// Rule 1: PaymentError
$json.error === true && $json.skipAppointment !== true

// Rule 2: Valid
$json.error !== true
```

### 4. Google Sheets Storage
```
Columna Y: transfer_id
Columna Z: (disponible para datos adicionales)

Ejemplo de fila:
├─ A: ID = "CITA-001"
├─ B: Nombre = "Juan"
├─ ...
├─ Y: transfer_id = "13030940"
└─ Z: (reserve)
```

---

## ✅ Estado Actual

| Componente | Estado | Notas |
|-----------|--------|-------|
| Transfer_ID Extraction | ✅ Funcionando | GPT-4 Vision extrae correctamente |
| Normalización (parseInt) | ✅ Funcionando | Elimina ceros a izquierda |
| Google Sheets Reading | ✅ Funcionando | Lee todas las citas |
| Duplicate Detection Logic | ✅ Funcionando | Encuentra duplicados correctamente |
| Switch Node Config | ✅ Funcionando | 3 outputs configurados correctamente |
| Error Message Creation | ✅ Funcionando | Mensaje duplicado creado y configurado |
| Flow Control | ❌ ROTO | Confirmación se ejecuta incluso en error |
| JSON Syntax | ✅ Válido | Validado sin errores |

---

## 🚀 Próximos Pasos Urgentes

### PRIORIDAD 1: Agregar Nodos de Control de Flujo

**Solución:** Agregar nodos "Stop" o "Continue" después de cada rama de error

```
Validate Transfer ID Error
├─ Output 0 → Send Duplicate Transfer ID → [CONTINUE/STOP] ⛔ (Detener aquí)
├─ Output 1 → Send Payment Invalid → [CONTINUE/STOP] ⛔ (Detener aquí)
└─ Output 2 → Prepare Confirmed Data → Save → Confirmations ✅
```

### PRIORIDAD 2: Remover Referencias Directas

En nodos `Send Payment Confirmed` y `Send Email Now Appointment`:
- Cambiar de `$('Prepare Confirmed Data').first().json` a pasar datos mediante branch proper
- Asegurar que solo ejecuten cuando estén en Output 2 (Valid)

### PRIORIDAD 3: Testing Exhaustivo

```
Test Case 1: Transfer_ID válido (nuevo)
├─ Input: Comprobante con transfer_id 999999
├─ Expected: Confirmación + email
└─ Result: ⏳

Test Case 2: Transfer_ID duplicado
├─ Input: Comprobante con transfer_id 13030940
├─ Expected: Solo mensaje "Transferencia ya registrada"
└─ Result: ❌ FALLA (envía confirmación extra)

Test Case 3: Error de validación (comprobante inválido)
├─ Input: Imagen corrupta o no bancaria
├─ Expected: Solo mensaje error genérico
└─ Result: ⏳
```

### PRIORIDAD 4: Documentar en Google Sheets

Agregar columnas para tracking:
```
Columna AA: transfer_id_status = "new" | "duplicate" | "error"
Columna AB: validation_timestamp = fecha de validación
Columna AC: transfer_id_checked = "yes" | "no"
```

---

## 📞 Contacto y Notas

- **Archivo Principal:** `N8N/TEMPLATES/kellscapilar/WhatsApp_Bot_v6_KellsCapilar.json`
- **Nodos Críticos:** 
  - `validate-transfer-id-error` (Switch)
  - `Prepare Confirmed Data` (Validation)
  - `Send Duplicate Transfer ID` (Error message)
  
- **Google Sheets:**
  - ID: `1ww6qJe057_HUaPTWgxT9pU1cfp8-HqmLecZjmYGB6Ps`
  - Sheet: "Citas" (ID: 1703053541)
  - Columna Y: transfer_id

- **API Endpoints:**
  - WhatsApp: `https://api.ycloud.com/v2/whatsapp/messages`
  - Credencial: "Header Auth WB YcloudDemo" (ID: cDHuTtbqeib255B5)

---

## 🔒 Validaciones Realizadas

✅ **JSON Syntax Validation**
```
Command: node -e "JSON.parse(...)"
Result: ✅ "JSON válido - LISTO"
```

✅ **Switch Node Logic Validation**
```
├─ Rule 0: skipAppointment === true → Output "DuplicateTransferId"
├─ Rule 1: error === true && skipAppointment !== true → Output "PaymentError"
└─ Rule 2: error !== true → Output "Valid"
```

✅ **Google Sheets Connection**
```
Status: Connected
Credentials: Google Sheets PROD
Sheets Available: Citas, Propuestas, etc.
```

⚠️ **Flow Execution**
```
Status: Partially Working
├─ Validation: ✅ Funciona
├─ Detection: ✅ Funciona
├─ Routing: ❌ Necesita fix
└─ Flow Control: ❌ Necesita stop nodes
```

---

## 📚 Recursos

- **n8n Docs:** https://docs.n8n.io/
- **Google Sheets API:** https://developers.google.com/sheets/api
- **WhatsApp YCloud API:** https://docs.ycloud.com/whatsapp/messages/send

---

**Última Actualización:** Enero 21, 2026  
**Responsable Anterior:** GitHub Copilot  
**Para Transferencia:** Próximo desarrollador debe agregar STOP nodes y remover referencias cruzadas en templates.
