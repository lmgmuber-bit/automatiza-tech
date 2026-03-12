# 🤖 WhatsApp Bot Demo Template

> Template simplificado para demostración de bot de WhatsApp con agendamiento de citas usando Google Sheets como base de datos.

## 🎯 ¿Para qué sirve?

Este template permite:
- ✅ Crear demos rápidas para clientes potenciales
- ✅ Clonar fácilmente para cada nuevo cliente
- ✅ No requiere base de datos MySQL/PostgreSQL
- ✅ Google Sheets visible = fácil de entender para el cliente

---

## 📋 Requisitos Previos

### 1. Cuenta de n8n
- n8n Self-hosted o n8n Cloud
- Versión 1.0+ recomendada

### 2. WhatsApp Business API
- Cuenta de Meta Business
- App creada en Meta Developers
- Número de WhatsApp Business API activo

### 3. Google Account
- Cuenta de Google con acceso a Sheets
- Cuenta de servicio configurada en n8n

### 4. OpenAI
- API Key activa

---

## 🚀 Instalación Paso a Paso

### Paso 1: Crear Google Sheet

1. Ve a [Google Sheets](https://sheets.google.com)
2. Crea un nuevo documento llamado: `Bot WhatsApp - [Nombre Cliente]`
3. Crea **2 hojas**:

#### Hoja 1: `Citas`
| Columna | Descripción |
|---------|-------------|
| id | ID único de la cita |
| nombre | Nombre del cliente |
| telefono | Número de WhatsApp |
| email | Email (opcional) |
| fecha | Fecha de la cita (YYYY-MM-DD) |
| hora | Hora de la cita (HH:MM) |
| estado | confirmado / cancelado / completado |
| created_at | Fecha de creación |

#### Hoja 2: `Configuracion`
| parametro | valor |
|-----------|-------|
| horario_inicio | 09:00 |
| horario_fin | 18:00 |
| dias_habiles | lunes,martes,miercoles,jueves,viernes |
| duracion_cita | 30 |
| negocio_nombre | Mi Negocio |
| negocio_telefono | +56912345678 |

4. Copia el **ID del Spreadsheet** de la URL:
```
https://docs.google.com/spreadsheets/d/[ESTE_ES_EL_ID]/edit
```

### Paso 2: Configurar Credenciales en n8n

#### 2.1 WhatsApp API (HTTP Header Auth)
1. Ve a **Settings → Credentials**
2. Crea nueva credencial: **HTTP Header Auth**
3. Configura:
   - Name: `WhatsApp API`
   - Header Name: `Authorization`
   - Header Value: `Bearer [TU_ACCESS_TOKEN]`

#### 2.2 Google Sheets
1. Crea nueva credencial: **Google Sheets OAuth2**
2. Sigue las instrucciones de autenticación
3. Autoriza acceso al Sheet creado

#### 2.3 OpenAI
1. Crea nueva credencial: **OpenAI**
2. Ingresa tu API Key

### Paso 3: Importar Workflow

1. Ve a **Workflows** en n8n
2. Click en **Import from File**
3. Selecciona `WhatsApp_Bot_Demo_Template.json`
4. Click en **Import**

### Paso 4: Configurar Variables

Busca y reemplaza las siguientes variables en el workflow:

| Variable | Reemplazar por | Ejemplo |
|----------|----------------|---------|
| `{{SPREADSHEET_ID}}` | ID de tu Google Sheet | `1abc123xyz...` |
| `{{BUSINESS_NAME}}` | Nombre del negocio | `Clínica Dental Sonrisas` |
| `{{BUSINESS_PHONE}}` | Teléfono del negocio | `+56 9 1234 5678` |
| `{{BUSINESS_WEBSITE}}` | Web del negocio | `www.clinicasonrisas.cl` |
| `{{BUSINESS_EMAIL}}` | Email del negocio | `contacto@clinica.cl` |
| `{{BUSINESS_SERVICES}}` | Descripción de servicios | `Limpieza dental, Blanqueamiento...` |
| `{{BUSINESS_HOURS}}` | Horarios de atención | `Lunes a Viernes 9:00-18:00` |

### Paso 5: Configurar Webhook en Meta

1. Ve a [Meta Developers](https://developers.facebook.com)
2. Selecciona tu App → WhatsApp → Configuration
3. En **Webhook**:
   - Callback URL: `https://tu-n8n.com/webhook/whatsapp-demo-webhook`
   - Verify Token: Cualquier string seguro
4. Suscribe a: `messages`

### Paso 6: Activar Workflow

1. En n8n, activa el workflow (switch superior derecho)
2. Envía un mensaje de prueba al número de WhatsApp
3. ¡Listo! El bot debería responder

---

## 🔄 Cómo Clonar para un Nuevo Cliente

1. **Duplicar Google Sheet**
   - Abre el Sheet base
   - File → Make a copy
   - Renombra: `Bot WhatsApp - [Nuevo Cliente]`

2. **Duplicar Workflow en n8n**
   - Abre el workflow template
   - Click en `⋮` → Duplicate
   - Renombra: `WhatsApp - [Nuevo Cliente]`

3. **Actualizar Variables**
   - Reemplaza `{{SPREADSHEET_ID}}` con el nuevo ID
   - Actualiza datos del negocio

4. **Crear nuevo Webhook en Meta**
   - Cambia el path del webhook: `/webhook/whatsapp-[cliente]`
   - Actualiza en Meta Developers

5. **Activar**
   - Activa el nuevo workflow
   - Prueba enviando mensaje

---

## 📊 Estructura del Workflow

```
┌─────────────────┐
│ WhatsApp        │
│ Webhook         │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Has Message?    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Extract Data    │
│ + Deduplication │
└────────┬────────┘
         │
    ┌────┴────┬──────────┐
    ▼         ▼          ▼
┌───────┐ ┌───────┐ ┌─────────┐
│ Day   │ │ Time  │ │ AI      │
│Select │ │Select │ │ Agent   │
└───┬───┘ └───┬───┘ └────┬────┘
    │         │          │
    ▼         ▼          ▼
┌───────┐ ┌───────┐ ┌─────────┐
│ Show  │ │ Save  │ │ Parse   │
│ Times │ │Sheets │ │ Actions │
└───────┘ └───┬───┘ └────┬────┘
              │          │
              ▼          ▼
         ┌───────┐ ┌─────────┐
         │Confirm│ │ Route   │
         └───────┘ │ Action  │
                   └────┬────┘
                        │
         ┌──────┬───────┼───────┬──────┐
         ▼      ▼       ▼       ▼      ▼
       Text  Calendar Cancel Reschedule Escalate
```

---

## 🔧 Personalización Avanzada

### Cambiar Modelo de IA
Por defecto usa `gpt-4o-mini`. Para cambiarlo:
1. Click en el nodo "GPT-4o Mini"
2. Cambia el modelo a `gpt-4o` o `gpt-3.5-turbo`

### Agregar más horarios
En el nodo "Extract Day Selection", modifica:
```javascript
// Generar horarios disponibles (9:00 - 18:00, cada 30 min)
for (let hour = 9; hour < 18; hour++) {
  for (let min of [0, 30]) {
    const time = `${hour.toString().padStart(2, '0')}:${min.toString().padStart(2, '0')}`;
    // ...
  }
}
```

### Agregar validación de disponibilidad real
Para evitar dobles reservas, antes de mostrar horarios:
1. Leer citas existentes del día desde Google Sheets
2. Filtrar horarios ya ocupados
3. Mostrar solo disponibles

---

## ⚠️ Limitaciones del Demo

| Feature | Demo | Versión Full |
|---------|------|--------------|
| Base de datos | Google Sheets | MySQL/PostgreSQL |
| Recordatorios automáticos | ❌ | ✅ |
| Validación de disponibilidad | Básica | Completa |
| Historial de conversaciones | Memory Buffer | Redis |
| Múltiples canales | Solo WhatsApp | Web + WhatsApp |
| Pagos integrados | ❌ | ✅ |
| Analytics | ❌ | ✅ |

---

## 📞 Soporte

¿Necesitas ayuda configurando el demo?

- 📧 Email: soporte@automatiza.tech
- 💬 WhatsApp: +56 9 xxxx xxxx
- 🌐 Web: https://automatiza.tech

---

## 📄 Changelog

### v1.0.0 (2025-01)
- ✅ Template inicial
- ✅ Agendamiento básico con Google Sheets
- ✅ Cancelación de citas
- ✅ Escalamiento a soporte humano
