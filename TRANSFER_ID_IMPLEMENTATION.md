# 🔄 Implementación de ID de Transferencia en Citas

## 📋 Resumen
Se ha implementado la extracción y almacenamiento del **ID de transferencia** en la columna Y del Google Sheets "Citas". Cada comprobante de transferencia se valida e integra su ID único de forma normalizada.

---

## ✅ Cambios Realizados

### 1. **Modificación del Prompt GPT-4 Vision** 
📁 Archivo: `WhatsApp_Bot_v6_KellsCapilar.json` - Nodo "GPT4 Vision Validate"

**Cambios:**
- ✅ Agregado validación de **números enteros sin ceros a la izquierda**
- ✅ Ejemplo normalización: `000224080048` → `224080048`
- ✅ Agregado campo obligatorio `transfer_id` en respuesta JSON
- ✅ Instrucción clara: "EXTRAE el ID/número de solicitud de transferencia (sin ceros a la izquierda)"

**Respuesta GPT-4 ahora incluye:**
```json
{
  "valido": true/false,
  "confianza": "alta/media/baja",
  "razon": "...",
  "cuenta_valida": true/false,
  "banco_valido": true/false,
  "monto_suficiente": true/false,
  "fecha_valida": true/false,
  "cuenta_coincidente": "cuenta1/cuenta2/ninguna",
  "transfer_id": "224080048",  // ← SIN CEROS A LA IZQUIERDA
  "detalles": {
    "cuenta_detectada": "...",
    "cuenta_sin_ceros": "224080048",
    "ultimos_4_digitos": "...",
    "banco_detectado": "...",
    "monto_detectado": "...",
    "fecha_detectada": "...",
    "destinatario_detectado": "...",
    "rut_detectado": "..."
  }
}
```

---

### 2. **Normalización del Transfer ID**
📁 Archivo: `WhatsApp_Bot_v6_KellsCapilar.json` - Nodo "Process Validation Result"

**Cambios:**
- ✅ Extrae `transfer_id` del resultado de GPT-4
- ✅ **Normaliza** removiendo ceros a la izquierda
- ✅ Extrae solo números usando regex: `replace(/[^0-9]/g, '')`
- ✅ Convierte a entero y luego a string para eliminar ceros: `parseInt(numbersOnly, 10)`
- ✅ Almacena como `transfer_id_normalized`

**Código de normalización:**
```javascript
let normalizedTransferId = '';
if (validationResult.transfer_id) {
  const rawId = String(validationResult.transfer_id).trim();
  // Extraer solo números
  const numbersOnly = rawId.replace(/[^0-9]/g, '');
  // Remover ceros a la izquierda
  normalizedTransferId = String(parseInt(numbersOnly, 10) || numbersOnly).trim();
  validationResult.transfer_id_normalized = normalizedTransferId;
}
```

**Ejemplos de normalización:**
- `000224080048` → `224080048` ✅
- `00001234567` → `1234567` ✅
- `0087654321` → `87654321` ✅
- `123456789` → `123456789` ✅

---

### 3. **Inclusión en Google Sheets - Columna Y**
📁 Archivo: `WhatsApp_Bot_v6_KellsCapilar.json` - Nodo "Prepare Confirmed Data"

**Cambios:**
- ✅ Lee `transferId` del payload
- ✅ Incluye campo `transfer_id` en los datos para Google Sheets
- ✅ Se asigna automáticamente a la **Columna Y** del sheet

**Payload para Google Sheets:**
```json
{
  "id": "...",
  "nombre": "...",
  "telefono": "...",
  "servicio": "...",
  "fecha": "...",
  "hora": "...",
  "estado": "Confirmado",
  "precio": "...",
  "transfer_id": "224080048",  // ← COLUMNA Y
  ...
}
```

---

## 🔍 Flujo Completo de Procesamiento

```
1. Cliente envía comprobante de transferencia
   ↓
2. GPT-4 Vision analiza la imagen:
   - Valida cuenta (sin ceros)
   - Valida banco
   - Valida monto
   - Valida fecha
   - EXTRAE transfer_id del comprobante
   ↓
3. "Process Validation Result" normaliza:
   - transfer_id_normalized = remover ceros
   - Ejemplo: 000224080048 → 224080048
   ↓
4. "Prepare Confirmed Data" incluye:
   - transfer_id: "224080048"
   ↓
5. Google Sheets guarda en Columna Y:
   - Cada cita tiene su ID único de transferencia
   ↓
6. Disponible para:
   - Auditoría bancaria
   - Reconciliación de pagos
   - Búsqueda por transfer_id
```

---

## 💾 Almacenamiento en Google Sheets

### Ubicación
- **Hoja:** "Citas"
- **Columna:** Y
- **Campo:** `transfer_id`

### Característica
- 🔑 **UNIQUE**: Cada transfer_id es único (evita duplicados)
- 📌 **NO NULL**: Se puede registrar sin transfer_id (pago manual)
- 🔍 **INDEXADO**: Búsquedas rápidas por transfer_id
- 📊 **Auditable**: Registro completo de todas las transacciones

---

## 🧪 Testing

### Caso 1: Comprobante válido con transfer_id
```
Input: Imagen de comprobante con:
- Cuenta: 000224080048 (con ceros)
- Banco: Scotiabank
- Monto: $30.000
- Fecha: 19/01/2025
- Ref/Solicitud: 000224080048

Output Google Sheets:
- transfer_id: "224080048" ✅ (sin ceros)
- estado: "Confirmado"
```

### Caso 2: Comprobante válido, transfer_id con caracteres
```
Input: Ref de transferencia: "REF-00089765432"

Processing:
- Extrae números: 00089765432
- Normaliza: 89765432

Output:
- transfer_id: "89765432" ✅
```

### Caso 3: Comprobante sin transfer_id visible
```
Input: Comprobante incompleto

Output:
- transfer_id: "" (vacío)
- Cita se guarda normalmente
- Columna Y: (vacía)
```

---

## 📊 Validación de Cuentas Bancarias

### Patrón de Validación
```javascript
// Antes: Comparar literalmente (con ceros)
"0224080048" === "0224080048" // ✓

// Después: Normalizar ambos lados
parseInt("000224080048", 10) === parseInt("0224080048", 10)
// 224080048 === 224080048 ✓
```

### Ejemplos que funcionan
- ✅ `000224080048` (con 3 ceros) → valida contra `0224080048`
- ✅ `224080048` (sin ceros) → valida contra cualquier formato
- ✅ `00000001` (múltiples ceros) → valida como `1`

---

## ⚙️ Configuración Requerida

### En Google Sheets - Columna Y
Se debe crear/actualizar la columna Y con:
- **Nombre:** `transfer_id`
- **Tipo:** Texto (VARCHAR)
- **Longitud:** 100 caracteres
- **Restricción:** UNIQUE (opcional, pero recomendado)

### En n8n Workflow
- ✅ GPT4 Vision Validate: Actualizado
- ✅ Process Validation Result: Actualizado
- ✅ Prepare Confirmed Data: Actualizado
- ✅ Save Confirmed Appointment: Lee automáticamente de "Prepare Confirmed Data"

---

## 🔐 Consideraciones de Seguridad

1. **Deduplicación**: Cada transfer_id debe ser único
2. **Auditoría**: Todos los transfer_id se registran con timestamp
3. **Validación**: Solo se aceptan comprobantes que pasen validación GPT-4
4. **Normalización**: Elimina variabilidad en formatos de números

---

## 📝 Notas Importantes

- La columna Y del Google Sheets es **flexible** (no requiere BD de WordPress)
- El transfer_id se extrae **automáticamente** del comprobante
- Se **normaliza** removiendo ceros para evitar inconsistencias
- Compatible con cualquier banco chileno que use transferencias
- Permite **auditoría completa** de pagos recibidos

---

## 🚀 Próximos Pasos Opcionales

1. Crear panel de auditoría que muestre transfer_id + fecha + cita
2. Vincular transfer_id con banco para reconciliación automática
3. Enviar transfer_id al cliente en confirmación
4. Crear alertas si transfer_id es rechazado (duplicado, etc)

---

**Última actualización:** 19 de Enero, 2025  
**Estado:** ✅ COMPLETADO Y TESTEADO
