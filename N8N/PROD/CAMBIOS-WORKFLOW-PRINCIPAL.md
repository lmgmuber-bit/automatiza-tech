# Cambios Aplicados al Workflow Principal

## Fecha: 23 de Diciembre de 2025

## Resumen
Se ha integrado la lógica completa de agendamiento con validación de horarios, feriados, botones interactivos y parseo de fechas en texto natural al workflow **WhatsApp Tech - Principal (PROD)**.

---

## ✅ Cambios Implementados

### 1. **Validación de Horarios desde WordPress**

#### Nodo: `Get Appointments Config`
- **Posición**: Después de "Route Action" cuando acción es "show_calendar"
- **Función**: Obtiene configuración de horarios y feriados desde WordPress REST API
- **Endpoint**: `https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments-config`
- **Conecta a**: Generate Days

#### Nodo: `Generate Days` (Actualizado)
- **Cambios**:
  - Ahora recibe configuración del API en lugar de hardcodear días hábiles
  - Valida que el día esté habilitado en `weekSchedule`
  - Valida que no sea feriado (desde `holidays`)
  - Genera máximo 3 días disponibles
  - Salta días no disponibles hasta encontrar 3 válidos

---

### 2. **Lista Interactiva con Opción "Otra Fecha"**

#### Nodo: `Send Calendar Buttons` (Actualizado)
- **Cambio de tipo**: De `button` a `list`
- **Opciones**:
  1. Día 1 (dinámico)
  2. Día 2 (dinámico)
  3. Día 3 (dinámico)
  4. 📆 **Otra fecha** - "Agendar para más adelante" (ID: `other_date`)

#### Nodo: `Button Action` (Actualizado)
- **Nueva condición agregada**:
  ```javascript
  buttonId === "other_date" → Salida: "Otra Fecha"
  ```
- **Nueva ruta**: Output 5 (índice 5) conecta a "Send Other Date Options"

---

### 3. **Manejo de "Otra Fecha"**

#### Nodo: `Send Other Date Options` (Nuevo)
- **ID**: `wa-send-other-date-options`
- **Función**: Muestra opciones para agendar fecha fuera de las 3 mostradas
- **Mensaje**:
  ```
  📆 ¡Perfecto! Tienes dos opciones para agendar una fecha más adelante:

  1️⃣ *Escríbeme la fecha exacta* que deseas (por ejemplo: '15 de enero a las 14:00')

  2️⃣ *Agenda directamente desde la web:*
  https://automatizatech.cl/#AgendarDemo

  ¿Qué prefieres?
  ```
- **Conecta a**: Set Date State

#### Nodo: `Set Date State` (Nuevo)
- **ID**: `wa-set-date-state`
- **Función**: Marca en Redis que el usuario está esperando ingresar fecha custom
- **Redis Key**: `pending_date_{{ phoneNumber }}`
- **Valor**: `"awaiting_custom_date"`
- **TTL**: 600 segundos (10 minutos)

---

### 4. **Parseo de Fechas en Texto Natural**

#### Nodo: `Check Pending Date` (Nuevo)
- **ID**: `wa-check-pending-date`
- **Posición**: Entre "Check Pending Booking" y "Check Email Booking"
- **Función**: Verifica si el usuario tiene estado "awaiting_custom_date"
- **Redis Key**: `pending_date_{{ phoneNumber }}`

#### Nodo: `Check Email Booking` (Actualizado)
- **Nuevo flujo agregado**:
  - Si `pendingDate === "awaiting_custom_date"` → Status: `PARSE_CUSTOM_DATE`
  - Retorna datos para parsear la fecha escrita por el usuario

#### Nodo: `Booking Status` (Actualizado)
- **Nueva ruta agregada**: `PARSE_CUSTOM_DATE` (output 5)
- **Conecta a**: Get Config For Parsing

#### Nodo: `Get Config For Parsing` (Nuevo)
- **ID**: `wa-get-config-parse`
- **Función**: Obtiene configuración para validar fecha parseada
- **Conecta a**: Parse Custom Date

#### Nodo: `Parse Custom Date` (Nuevo)
- **ID**: `wa-parse-custom-date`
- **Función**: Parsea fechas en español con regex
- **Formatos soportados**:
  - Fechas: "15 de enero", "15 enero", "enero 15", "31 dic"
  - Horas: "14:00", "a las 14", "2 pm", "14 horas"
- **Validaciones**:
  ✅ Fecha no pasada
  ✅ Día habilitado en configuración
  ✅ No sea feriado
  ✅ Horario dentro del rango del día
- **Salidas**:
  - `isValid: true` → Fecha válida con `selectedDay`, `selectedTime`
  - `isValid: false` → Error descriptivo
- **Conecta a**: Is Custom Date Valid?

#### Nodo: `Is Custom Date Valid?` (Nuevo)
- **ID**: `wa-is-custom-date-valid`
- **Función**: IF node que valida `isValid === true`
- **Rutas**:
  - TRUE → Save Custom Booking State
  - FALSE → Send Custom Date Error

#### Nodo: `Send Custom Date Error` (Nuevo)
- **ID**: `wa-send-custom-date-error`
- **Función**: Envía mensaje de error específico
- **Mensajes posibles**:
  - Formato inválido
  - Fecha pasada
  - Día no habilitado
  - Feriado
  - Fuera de horario

#### Nodo: `Save Custom Booking State` (Nuevo)
- **ID**: `wa-save-custom-booking`
- **Función**: Guarda reserva en Redis
- **Redis Key**: `booking_{{ phoneNumber }}`
- **Datos**: selectedDay, selectedTime, step: 'WAITING_NAME'
- **Conecta a**: Ask Name for Custom Date

#### Nodo: `Ask Name for Custom Date` (Nuevo)
- **ID**: `wa-ask-name-custom`
- **Función**: Solicita nombre mostrando la fecha parseada
- **Mensaje**:
  ```
  ✨ ¡Excelente!

  Has elegido:
  📅 {{ parsedDateDisplay }}
  ⏰ {{ selectedTime }} hrs

  👤 Por favor, escribe tu nombre completo para la reserva:
  ```

---

### 5. **Horarios Dinámicos desde WordPress**

#### Nodo: `Get Appointments Config 2` (Nuevo)
- **ID**: `wa-get-appointments-config-2`
- **Posición**: Paralelo a "Get Calendar Events" después de "Extract Day"
- **Función**: Obtiene configuración para Find Available Slots
- **Conecta a**: Find Available Slots

#### Nodo: `Find Available Slots` (Actualizado)
- **Cambios**:
  - Ahora recibe configuración además de eventos de Google Calendar
  - Obtiene horarios del día desde `weekSchedule[dayName]`
  - Genera slots dinámicamente según `start` y `end` del día
  - Ya no usa horarios hardcodeados
  - Respeta configuración por día de la semana

---

## 🔗 Flujo Completo

### Flujo Normal (Botones):
```
Route Action (show_calendar)
  → Get Appointments Config
    → Generate Days
      → Send Calendar Buttons (lista con 4 opciones)
        → Button Action
          → Extract Day (si día 1-3)
            → Get Calendar Events + Get Appointments Config 2
              → Find Available Slots
                → Has Slots?
                  → Send Times Buttons
                    → Extract Time
                      → Save Booking State
                        → Ask For Name
                          → (flujo existente...)
```

### Flujo "Otra Fecha":
```
Send Calendar Buttons
  → Button Action (other_date)
    → Send Other Date Options
      → Set Date State (Redis: awaiting_custom_date)
        → (Usuario escribe fecha en texto)
          → Check Pending Date
            → Check Email Booking
              → Booking Status (PARSE_CUSTOM_DATE)
                → Get Config For Parsing
                  → Parse Custom Date
                    → Is Custom Date Valid?
                      → [VÁLIDA] Save Custom Booking State
                        → Ask Name for Custom Date
                          → (flujo existente...)
                      → [INVÁLIDA] Send Custom Date Error
```

---

## 📋 Validaciones Implementadas

### En `Generate Days`:
- ✅ Día habilitado en configuración
- ✅ No sea feriado

### En `Parse Custom Date`:
- ✅ Formato de fecha válido en español
- ✅ Formato de hora válido
- ✅ Fecha no pasada
- ✅ Día habilitado en configuración
- ✅ No sea feriado
- ✅ Horario dentro del rango del día

### En `Find Available Slots`:
- ✅ Horarios según configuración del día
- ✅ No ocupados en Google Calendar
- ✅ Máximo 3 slots mostrados

---

## 🔧 Nodos que Requieren Credenciales

1. **Get Appointments Config** - No requiere (API pública)
2. **Get Appointments Config 2** - No requiere (API pública)
3. **Get Config For Parsing** - No requiere (API pública)
4. **Send Calendar Buttons** - WhatsApp API (ya configurada: `TVTLZP26kDJjR0KP`)
5. **Send Other Date Options** - WhatsApp API (ya configurada: `TVTLZP26kDJjR0KP`)
6. **Send Custom Date Error** - WhatsApp API (ya configurada: `TVTLZP26kDJjR0KP`)
7. **Ask Name for Custom Date** - WhatsApp API (ya configurada: `TVTLZP26kDJjR0KP`)
8. **Set Date State** - Redis (ya configurada: `fgxjc2NeBOcUCA3v`)
9. **Check Pending Date** - Redis (ya configurada: `fgxjc2NeBOcUCA3v`)
10. **Save Custom Booking State** - Redis (ya configurada: `fgxjc2NeBOcUCA3v`)
11. **Get Calendar Events** - Google Calendar (ya configurada: `NrhQQuWgel9eWwzp`)

---

## 📦 Archivos Afectados

1. ✅ `WhatsApp_Tech_Principal.json` - Modificado completamente
2. ✅ `api-appointments-config.php` - Debe estar en servidor (wp-content/mu-plugins/)

---

## 🚀 Próximos Pasos

1. ⚠️ **Subir `api-appointments-config.php` por FTP** a `wp-content/mu-plugins/`
2. ⚠️ **Importar `WhatsApp_Tech_Principal.json`** actualizado en N8N (o reemplazar el existente)
3. ⚠️ **Probar flujo completo**:
   - Solicitar agendar demo
   - Seleccionar una de las 3 fechas
   - Seleccionar horario
   - Completar nombre y email
   - Verificar creación en Google Calendar
4. ⚠️ **Probar flujo "Otra Fecha"**:
   - Seleccionar "📆 Otra fecha"
   - Escribir fecha en texto (ej: "15 de enero a las 14:00")
   - Verificar validaciones (feriados, horarios, etc.)
   - Completar reserva
5. ⚠️ **Validar en WordPress Admin**:
   - Configurar horarios por día
   - Agregar feriados
   - Verificar que el bot respete la configuración

---

## ⚙️ Configuración de WordPress

### Panel Admin: `/wp-admin`
### Ruta: Chat IA > Configuración de Citas

**Configurar**:
1. **Horarios por día** (Lun-Dom):
   - Checkbox: Habilitado
   - Hora inicio: HH:MM
   - Hora fin: HH:MM

2. **Feriados y días bloqueados**:
   - Formato: Un día por línea
   - Ejemplo:
     ```
     2025-12-25
     2025-12-31
     2026-01-01
     ```

---

## 🐛 Debugging

Si algo no funciona:

1. **Verificar API funciona**:
   ```bash
   curl https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments-config
   ```
   Debe retornar JSON con `weekSchedule` y `holidays`

2. **Verificar Redis**:
   - Revisar que las keys se están creando: `pending_date_*`, `booking_*`

3. **Verificar N8N**:
   - Activar workflow
   - Ver logs de ejecución
   - Verificar que todos los nodos están conectados

4. **Revisar formato de fechas**:
   - Timezone: America/Santiago
   - Formato fechas: YYYY-MM-DD
   - Formato horas: HH:MM

---

## ✅ Checklist Final

- [x] API endpoint creado
- [x] Nodos agregados al workflow
- [x] Conexiones actualizadas
- [x] Validaciones implementadas
- [x] Parseo de fechas en español
- [x] Lista interactiva con "Otra fecha"
- [x] Redis state management
- [x] Documentación completa
- [ ] **Subir API por FTP**
- [ ] **Importar workflow a N8N**
- [ ] **Probar flujo completo**
- [ ] **Configurar horarios en WordPress**
- [ ] **Probar con fechas reales**
