# 🚀 Portal Omnicanal - AutomatizaTech

## Arquitectura del Sistema

Sistema de bandeja unificada que consolida conversaciones de **WhatsApp**, **Instagram**, **Telegram** y **Messenger** en un solo portal, con configuración de bots por canal, auditoría completa y toma de control por ejecutivos.

---

## 📁 Archivos del Sistema

### Backend (WordPress/PHP)
| Archivo | Descripción |
|---------|-------------|
| `setup-omnichannel-db.php` | Crea las 8 tablas del sistema en MySQL |
| `omnichannel-controller.php` | Clase principal con toda la lógica de negocio |
| `api-omnichannel.php` | API REST con endpoints JSON para el portal |
| `webhook-omnichannel.php` | Receptor de webhooks de WhatsApp, IG, Telegram, Messenger |
| `admin-omnichannel-superadmin.php` | Panel Super Admin para AutomatizaTech |

### Frontend (React/Vite/Tailwind)
| Directorio | Descripción |
|------------|-------------|
| `client-portal-omnichannel/src/` | Código fuente React |
| `client-portal-omnichannel/dist/` | Build de producción |

---

## 🗄️ Base de Datos (8 tablas)

```
wp_omnichannel_clients        → Clientes con acceso al portal
wp_omnichannel_channels       → Canales configurados por cliente
wp_omnichannel_conversations  → Conversaciones unificadas
wp_omnichannel_messages       → Mensajes de todos los canales
wp_omnichannel_bot_configs    → Configuración de bots por canal  
wp_omnichannel_audit_log      → Registro completo de auditoría
wp_omnichannel_takeovers      → Toma de control por ejecutivos
wp_omnichannel_agents         → Agentes/usuarios del portal
```

---

## 🔧 Setup Inicial

### 1. Crear las tablas
Acceder como admin de WordPress:
```
https://tudominio.com/setup-omnichannel-db.php
```

### 2. Acceder al panel Super Admin
```
https://tudominio.com/admin-omnichannel-superadmin.php
```
Requiere usuario WordPress con `manage_options`.

### 3. Crear un cliente
Desde el panel Super Admin → **Nuevo Cliente**, o por API:
```bash
# (Requiere estar logueado como admin WordPress)
curl -X POST https://tudominio.com/api-omnichannel.php?route=admin/clients \
  -H "Content-Type: application/json" \
  -H "Cookie: wordpress_logged_in_xxx=..." \
  -d '{
    "company_name": "Mi Empresa",
    "contact_name": "Juan Pérez", 
    "email": "juan@miempresa.com",
    "plan_type": "professional"
  }'
```
Esto genera un **API Key** que el cliente usa para acceder al portal.

### 4. El cliente configura sus canales
Con su API Key, el cliente accede al portal React:
```
https://tudominio.com/client-portal-omnichannel/dist/
```

### 5. Configurar webhooks en las plataformas
URL del webhook para cada canal:
```
https://tudominio.com/webhook-omnichannel.php?channel_id={ID}&secret={WEBHOOK_SECRET}
```

---

## 📡 API Endpoints

### Rutas para Clientes (requiere header `X-API-Key`)

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `?route=conversations` | Listar conversaciones |
| GET | `?route=conversations/{id}/messages` | Ver mensajes |
| POST | `?route=conversations/{id}/messages` | Enviar mensaje |
| POST | `?route=conversations/{id}/takeover` | Tomar control |
| POST | `?route=conversations/{id}/release` | Devolver al bot |
| POST | `?route=conversations/{id}/transfer` | Transferir a otro agente |
| GET | `?route=channels` | Listar canales |
| POST | `?route=channels` | Crear canal |
| PUT | `?route=channels/{id}` | Editar canal |
| GET | `?route=bots` | Listar configs de bots |
| PUT | `?route=bots/{id}` | Editar config de bot |
| GET | `?route=agents` | Listar agentes |
| POST | `?route=agents` | Crear agente |
| GET | `?route=audit` | Ver auditoría del cliente |

### Rutas Admin (requiere sesión WordPress admin)

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `?route=admin/stats` | Estadísticas globales |
| GET | `?route=admin/clients` | Listar todos los clientes |
| POST | `?route=admin/clients` | Crear cliente |
| GET | `?route=admin/clients/{id}` | Detalle de cliente |
| PUT | `?route=admin/clients/{id}` | Editar cliente |
| GET | `?route=admin/audit` | Auditoría global |

### Webhook (sin auth, verifica secret)

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `?route=webhook/{channel_id}&secret={SECRET}` | Recibir mensajes |
| GET | `?route=webhook/{channel_id}&secret={SECRET}` | Verificación webhook |

---

## 🤖 Configuración de Bots

Cada canal tiene un bot configurable con:
- **System Prompt**: Instrucciones del bot (personalidad, comportamiento)
- **Modelo AI**: GPT-4o, GPT-4o-mini, GPT-4-turbo
- **Temperatura**: 0 (preciso) a 1 (creativo)
- **Mensaje de bienvenida**: Primer mensaje automático
- **Mensaje fallback**: Cuando el bot no entiende
- **Palabras de escalamiento**: Keywords que disparan transferencia a humano
- **Horario comercial**: JSON con horarios por día
- **Mensaje fuera de horario**: Auto-respuesta fuera de horario
- **Webhook N8N**: Para integraciones avanzadas

---

## 👤 Toma de Control por Ejecutivo

Flujo:
1. Conversación inicia en modo **Bot** 🤖
2. Si hay keywords de escalamiento o el ejecutivo decide → **Tomar Control**
3. Estado cambia a **Asignado** 👤 y el agente puede escribir directamente
4. Al terminar → **Devolver al Bot** o **Transferir** a otro agente
5. Cada acción se registra en auditoría con timestamp, agente, IP

---

## 📋 Sistema de Auditoría

Cada acción registra:
- **Quién**: usuario, email, rol, IP
- **Qué**: acción (create/update/delete/takeover/release/transfer)
- **Dónde**: entidad afectada (client/channel/bot_config/conversation/agent)
- **Antes/Después**: JSON con valores anteriores y nuevos
- **Cuándo**: timestamp preciso

---

## 🔐 Seguridad

- **API Key**: Cada cliente tiene API key única de 48 chars
- **Webhook Secret**: Cada canal tiene secret de 32 chars verificado con `hash_equals`
- **CORS**: Solo orígenes permitidos
- **WordPress Auth**: Panel admin requiere `manage_options`
- **Nonce CSRF**: Formularios admin protegidos con `wp_nonce_field`
- **Sanitización**: Todo input sanitizado con funciones WordPress
- **IP logging**: Todas las acciones registran IP del usuario

---

## 🖥️ Frontend React

### Desarrollo
```bash
cd client-portal-omnichannel
npm install
npm run dev    # http://localhost:5173
```

### Build para producción
```bash
npm run build  # Genera dist/
```

### Vistas del portal
1. **Bandeja Unificada**: Todas las conversaciones con filtros por canal/estado
2. **Canales**: Agregar/configurar WhatsApp, Instagram, Telegram, Messenger
3. **Bots**: Configurar AI, prompts, respuestas, escalamiento
4. **Agentes**: Gestionar equipo con roles (admin/supervisor/agente)
5. **Auditoría**: Timeline de todos los cambios con diff de valores

---

## 📊 Panel Super Admin (AutomatizaTech)

Acceso: `/admin-omnichannel-superadmin.php`

Funcionalidades:
- **Dashboard**: Stats globales (clientes, canales, conversaciones, mensajes)
- **Clientes**: CRUD completo con búsqueda, filtros, paginación
- **Auditoría**: Logs de todos los clientes con filtros por acción/entidad
- **Canales Globales**: Vista panorámica de todos los canales de todos los clientes
- **Edición rápida**: Cambiar estado, plan, límites de cada cliente
