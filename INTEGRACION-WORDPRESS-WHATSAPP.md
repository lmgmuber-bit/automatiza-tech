# Integración WordPress + WhatsApp - Gestión de Citas

## 📋 RESUMEN
Integrar el workflow de WhatsApp con WordPress para guardar, actualizar y eliminar citas en la base de datos.

---

## 🔧 PASO 1: Actualizar WordPress

### 1.1 Subir archivo de API
✅ **YA CREADO**: `wp-content/mu-plugins/api-appointments-management.php`

Este archivo provee los endpoints REST API:
- `GET /appointments` - Listar todas las citas
- `GET /appointments/{id}` - Obtener una cita
- `GET /appointments/search?email=xxx&phone=xxx` - Buscar citas
- `POST /appointments` - Crear cita
- `PUT /appointments/{id}` - Actualizar cita (reprogramar)
- `DELETE /appointments/{id}` - Cancelar cita (soft delete)
- `DELETE /appointments/{id}?hard_delete=true` - Eliminar permanentemente

### 1.2 Actualizar esquema de BD
✅ **YA CREADO**: `update-appointments-schema.php`

**Ejecutar una sola vez** visitando: `http://localhost/automatiza-tech/update-appointments-schema.php`

Esto agregará las columnas:
- `event_id` (VARCHAR 255) - ID del evento de Google Calendar
- `source` (VARCHAR 50) - Origen: 'web', 'whatsapp'
- `status` (VARCHAR 20) - Estado: 'active', 'cancelled'
- `updated_at` (DATETIME) - Última actualización
- `cancelled_at` (DATETIME) - Fecha de cancelación

---

## 🤖 PASO 2: Modificar Workflow de WhatsApp

### Nodos a AGREGAR en N8N:

#### A) Después de crear evento en Google Calendar (cuando completa booking)

**Nodo: "Save Appointment to WordPress"**
- Tipo: HTTP Request
- Method: POST
- URL: `https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments`
- Headers: `Content-Type: application/json`
- Body JSON:
```json
{
  "name": "={{ $('Check Email Booking').first().json.name }}",
  "email": "={{ $('Check Email Booking').first().json.email }}",
  "phone": "={{ $('Check Email Booking').first().json.phoneNumber }}",
  "scheduled_date": "={{ $('Check Email Booking').first().json.date }}",
  "scheduled_time": "={{ $('Check Email Booking').first().json.time }}",
  "event_id": "={{ $('Create Google Event').first().json.id }}",
  "meet_link": "={{ $('Create Google Event').first().json.hangoutLink }}",
  "source": "whatsapp",
  "status": "active"
}
```

**Conectar:**
`Create Google Event` → `Save Appointment to WordPress` → `Send Confirmation Message`

---

#### B) En el flujo de BÚSQUEDA para CANCELAR

**Nodo: "Search Appointments in WordPress"**
- Tipo: HTTP Request
- Method: GET
- URL: `https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/search?email={{ $json.email }}&future_only=true`

**Conectar ANTES de "Search Cancel Appointment" (Google Calendar):**
`Parse Cancel Data` → `Search Appointments in WordPress` → (IF encontró) → Usar datos de WordPress

**Modificar "Process Cancel Results":**
- Cambiar para usar datos de WordPress primero
- Si no hay en WordPress, buscar en Google Calendar como fallback

---

#### C) Cuando CONFIRMA CANCELAR (btn_confirmar_cancelar)

**Nodo: "Update Appointment Status"**  
- Tipo: HTTP Request
- Method: DELETE
- URL: `https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/{{ $json.appointmentId }}`
- Query Params: `hard_delete=false` (para soft delete)

**Conectar DESPUÉS de "Delete Cancelled Appointment":**
`Delete Cancelled Appointment` → `Update Appointment Status` → `Send Cancel Success`

---

#### D) En el flujo de REPROGRAMAR

**Nodo 1: "Search Appointment by Email WP"**
- Tipo: HTTP Request  
- Method: GET
- URL: `https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/search?email={{ $json.email }}&future_only=true`

**Conectar:**
`Parse Reschedule Data` → `Search Appointment by Email WP` → (IF encontró) → Usar appointment_id

**Nodo 2: "Update Appointment in WP"**
- Tipo: HTTP Request
- Method: PUT  
- URL: `https://automatizatech.cl/wp-json/automatiza-tech/v1/appointments/{{ $json.appointmentId }}`
- Body JSON:
```json
{
  "scheduled_date": "={{ $json.newDate }}",
  "scheduled_time": "={{ $json.newTime }}",
  "event_id": "={{ $('Create New Google Event').first().json.id }}",
  "meet_link": "={{ $('Create New Google Event').first().json.hangoutLink }}",
  "status": "active"
}
```

**Conectar DESPUÉS de crear nuevo evento:**
`Create New Google Event` → `Update Appointment in WP` → `Send Reschedule Success`

---

## 📊 PASO 3: Ver Citas en Admin de WordPress

Después de subir `api-appointments-management.php`, aparecerá un nuevo menú en WordPress:

**📅 Citas** (en el sidebar del admin)

Mostrará tabla con:
- ID, Nombre, Email, Teléfono
- Fecha, Hora, Meet Link
- Origen (web/whatsapp)
- Estado (active/cancelled)
- Fecha de creación

---

## 🔍 PASO 4: Testing

### 4.1 Crear cita desde WhatsApp
1. Usuario: "quiero agendar"
2. Selecciona fecha + hora
3. Envía nombre + email
4. ✅ Verificar que aparece en:
   - Google Calendar
   - WordPress Admin → Citas

### 4.2 Cancelar cita
1. Usuario: "quiero cancelar mi cita"
2. Envía nombre + email
3. Confirma cancelación
4. ✅ Verificar:
   - Evento eliminado de Google Calendar
   - En WordPress: `status = 'cancelled'`, `cancelled_at` con fecha

### 4.3 Reprogramar cita
1. Usuario: "quiero reprogramar"
2. Envía nombre + email
3. Confirma datos, selecciona nueva fecha
4. ✅ Verificar:
   - Evento viejo eliminado de Google Calendar
   - Evento nuevo creado en Google Calendar
   - En WordPress: `scheduled_date`, `scheduled_time`, `event_id` actualizados

---

## 🚀 BENEFICIOS

1. **Historial Completo**: Todas las citas quedan registradas en WordPress, incluso canceladas
2. **Reporte Fácil**: Desde el admin de WordPress se puede ver todo
3. **Datos Consistentes**: No se pierde información si hay problemas con Google Calendar
4. **Trazabilidad**: Se sabe de dónde vino cada cita (web vs whatsapp)
5. **Auditoría**: Campos `created_at`, `updated_at`, `cancelled_at` para tracking

---

## 📝 NOTAS IMPORTANTES

- Los endpoints **NO requieren autenticación** actualmente (para facilitar integración con N8N)
- Si necesitas seguridad, agregar validación de API Key en los endpoints
- El `event_id` de Google Calendar se guarda para poder hacer match entre ambos sistemas
- El soft delete (`status=cancelled`) permite mantener historial de citas canceladas
- El campo `source` permite diferenciar citas de web vs WhatsApp

---

## 🔗 URLs de los Endpoints

**Producción:**
- Base: `https://automatizatech.cl/wp-json/automatiza-tech/v1`
- Listar: `GET /appointments`
- Buscar: `GET /appointments/search?email=xxx`
- Crear: `POST /appointments`
- Actualizar: `PUT /appointments/{id}`
- Cancelar: `DELETE /appointments/{id}`

**Local:**
- Base: `http://localhost/automatiza-tech/wp-json/automatiza-tech/v1`

---

## 📦 Estructura de Datos

```json
{
  "id": 1,
  "name": "Juan Pérez",
  "email": "juan@email.com",
  "phone": "+56912345678",
  "scheduled_date": "2025-12-25",
  "scheduled_time": "14:00",
  "session_id": "session_123",
  "meet_link": "https://meet.google.com/xxx-yyyy-zzz",
  "event_id": "google_calendar_event_id_123",
  "source": "whatsapp",
  "status": "active",
  "created_at": "2025-12-23 10:30:00",
  "updated_at": "2025-12-23 11:00:00",
  "cancelled_at": null
}
```
