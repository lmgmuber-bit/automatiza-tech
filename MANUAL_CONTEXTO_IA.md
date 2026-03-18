# Manual de Contexto para IA — Portal OmniCliente AutomatizaTech

> **Propósito:** Este documento provee contexto completo para que cualquier asistente de IA pueda entender, mantener y extender el sistema OmniCliente sin ambigüedades.

---

## 1. Descripción General del Proyecto

**OmniCliente** es un portal omnicanal SPA (Single Page Application) construido para **AutomatizaTech**. Permite gestionar conversaciones de clientes a través de múltiples canales (WhatsApp, Email, Webchat, etc.) con un sistema de agentes, supervisores y administradores.

### Stack Tecnológico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Frontend | React + Vite + Tailwind CSS | 19.1.0 / 6.4.1 / 3.4.17 |
| Iconos | Lucide React | 0.577.0 |
| Backend | PHP (WordPress como framework) | 8.3.14 |
| Base de Datos | MySQL | 9.1.0 |
| Servidor | WAMP64 (Windows) | — |
| Enrutamiento | Interno por estado React (no react-router) | — |

### No se usa React Router
La navegación se gestiona con `currentView` state en `App.jsx` y un objeto `views` que mapea IDs a componentes.

---

## 2. Estructura de Archivos

```
automatiza-tech/
├── api-omnichannel.php          # Router API principal (1380+ líneas)
├── omnichannel-controller.php   # Controlador principal (3050+ líneas)
├── client-portal-omnichannel/   # Frontend React
│   ├── src/
│   │   ├── App.jsx              # Layout principal, auth, routing
│   │   ├── api.js               # Funciones API y auth (~330 líneas)
│   │   ├── index.css            # Estilos globales + responsive
│   │   ├── main.jsx             # Entry point
│   │   └── components/
│   │       ├── Sidebar.jsx          # Navegación lateral con roles
│   │       ├── LoginScreen.jsx      # Login 3 tabs + soporte público
│   │       ├── InboxView.jsx        # Bandeja de conversaciones
│   │       ├── ChannelsView.jsx     # CRUD canales
│   │       ├── ChannelTypesView.jsx # CRUD tipos de canal
│   │       ├── BotsView.jsx         # Configuración de bots
│   │       ├── AgentsView.jsx       # Gestión de agentes
│   │       ├── AuditView.jsx        # Log de auditoría
│   │       ├── ClientsView.jsx      # Gestión de clientes (admin)
│   │       ├── DashboardView.jsx    # Dashboard admin con stats
│   │       ├── ProfileView.jsx      # Perfil del agente
│   │       ├── SupportView.jsx      # Tickets de soporte
│   │       ├── ConfirmDeleteModal.jsx
│   │       ├── ResultModal.jsx
│   │       ├── ExpiryWarningModal.jsx
│   │       ├── TicketNotificationModal.jsx
│   │       └── ChannelBadge.jsx
│   ├── public/
│   │   └── logo-automatiza-tech.png
│   ├── vite.config.js
│   ├── tailwind.config.js
│   └── package.json
├── portal-omnichannel/          # Build de producción (deploy target)
├── setup-*.php                  # Scripts de migración de BD
└── *.md                         # Documentación
```

---

## 3. Sistema de Autenticación (3 Modos)

### 3.1 Cliente (API Key)
- Header: `X-API-Key`
- Almacenado en: `localStorage.omni_api_key`
- Rutas: Sin prefijo (`/conversations`, `/channels`, etc.)
- Capacidades: CRUD de sus propios recursos

### 3.2 Administrador WP
- Header: `X-Admin-Token` (HMAC-SHA256, 7 días) o sesión WP cookie
- Almacenado en: `localStorage.omni_admin_token`
- Flag: `localStorage.omni_is_admin = 'true'`
- Rutas: Prefijo `/admin/` (se agrega automáticamente en `api.js`)
- Capacidades: Gestión total, CRUD clientes, dashboard, soporte

### 3.3 Agente
- Header: `X-Agent-Token`
- Almacenado en: `localStorage.omni_agent_token`
- Flag: `localStorage.omni_is_agent = 'true'`
- Datos: `localStorage.omni_agent_data` (JSON con name, email, role, skills, avatar_url, etc.)
- Rutas: Prefijo `/agent/`
- Roles internos: `agent`, `supervisor`, `admin`
- Supervisor/Admin pueden: gestionar bots, agentes, ver auditoría

---

## 4. Navegación por Roles (Sidebar.jsx)

```
Cliente/WP Admin (navItems):
  Bandeja → Canales → Tipos de Canal → Bots → Agentes → Auditoría

Agente (agentNavItems):
  Mis Conversaciones → Equipo → Soporte

Supervisor (supervisorNavItems):
  Bandeja → Bots → Agentes → Auditoría → Soporte

Admin WP (adminNavItems — sección ADMIN):
  Clientes → Dashboard → Soporte (con badge de tickets abiertos)
```

---

## 5. Base de Datos

### Prefijo: `wp_omnichannel_`

### Tablas Principales
| Tabla | Propósito |
|-------|-----------|
| `clients` | Empresas cliente con API key, plan, período |
| `channels` | Canales de comunicación (WhatsApp, Email, etc.) |
| `channel_types` | Definiciones de tipos de canal y sus campos |
| `conversations` | Conversaciones con clientes finales |
| `messages` | Mensajes dentro de conversaciones |
| `bots` | Configuración de bots por cliente |
| `agents` | Agentes con credenciales, rol, skills |
| `audit_log` | Registro de todas las acciones del sistema |
| `support_tickets` | Tickets de soporte |
| `ticket_messages` | Mensajes en tickets (con columna `attachments` JSON) |
| `client_details` | Datos extendidos de clientes |

### Formato de Ticket: `TK-YYYYMMDD-XXXX`

---

## 6. API — Estructura de Rutas

### Archivo: `api-omnichannel.php`
- Carga WordPress vía `wp-load.php`
- CORS configurado para `localhost:5173-5176`, `localhost:3000`, y `get_site_url()`
- Ruta parseada de `$_GET['route']`

### Rutas Públicas (sin auth)
| Método | Ruta | Función |
|--------|------|---------|
| POST | `/webhook/{channel_id}?secret=` | Mensajes entrantes |
| POST | `/cron/expiry-reminders?secret=` | Procesamiento de vencimientos |
| POST | `/public/support-ticket` | Ticket de soporte público |
| POST | `/public/upload-images` | Subida de imágenes público |

### Rutas Admin (`/admin/...`)
CRUD completo de: clients, agents, channels, bots, channel-types, bot-templates, workflows, tickets, audit, stats.

### Rutas Agent (`/agent/...`)
Login, perfil, conversaciones, tickets, equipos. Supervisores tienen permisos extendidos.

### Rutas Cliente (API Key, sin prefijo)
Conversations, channels, bots, agents, audit, channel-types, period-status.

---

## 7. Patrones de Código Importantes

### 7.1 Función `request()` en api.js
```js
async function request(endpoint, options = {}) {
  // Agrega automáticamente 'admin/' o 'agent/' según el modo de auth
  // Headers: X-Admin-Token, X-Agent-Token, o X-API-Key
  // Maneja JSON response y errores
}
```

### 7.2 Patrón de Vista (View Components)
Todas las vistas siguen un patrón similar:
1. Estado para datos, paginación, loading, modales
2. `useEffect` para cargar datos iniciales
3. Funciones handler para CRUD
4. Return con layout: header + contenido + paginación + modales

### 7.3 Patrón Responsive
- **Sidebar**: CSS off-screen en mobile (`left: -280px`), overlay, hamburger button
- **InboxView**: Patrón `mobileShowChat` — toggle entre lista y chat en mobile
- **SupportView Admin**: Toggle entre lista y detalle en mobile (`isMobile` state interno)
- **Otros**: `flex-col sm:flex-row`, `grid-cols-1 sm:grid-cols-2`, `hidden sm:block`

### 7.4 Dark Mode
- Controlado por `data-theme` attribute y clase `dark` en `<html>`
- Todos los componentes usan `dark:` variants de Tailwind
- Toggle en Sidebar footer

### 7.5 Auditoría (set_actor)
El controller tiene `set_actor($email, $name, $role)` que se llama desde el router.
Todos los cambios se registran en `audit_log` con: action, entity_type, entity_id, details, actor.

---

## 8. Sistema de Soporte (Tickets)

### Backend
- `create_ticket()` — Crea ticket con número `TK-YYYYMMDD-XXXX`
- `add_ticket_message()` — Agrega mensaje con `attachments` JSON opcional
- `send_ticket_email()` — 4 tipos: created, status_changed, closed, admin_reply
- `create_public_ticket()` — Sin auth, desde login screen
- Emails con header branded (logo + datos AutomatizaTech)

### Frontend
- **Admin**: Layout maestro-detalle dos paneles (desktop), toggle lista/detalle (mobile)
- **Agente**: Navegación full-page lista → detalle
- Upload de hasta 5 imágenes por ticket/mensaje
- Lightbox para visualización de imágenes adjuntas

---

## 9. Uploads de Archivos

### Patrón de Upload
```
Frontend: uploadTicketImages(files) → FormData → POST /agent/ticket-images
Backend: wp_handle_upload() → return { urls: [...] }
```
- Tipos permitidos: jpeg, png, webp, gif
- Límite: 3MB por archivo tickets, 2MB avatars
- Máximo: 5 imágenes por ticket/mensaje

---

## 10. Build y Deploy

### Desarrollo
```bash
cd client-portal-omnichannel
npm install
npm run dev    # localhost:5173 con proxy a /api-omnichannel.php
```

### Producción
```bash
cd client-portal-omnichannel
npm run build
# Deploy:
Copy-Item -Path "dist\*" -Destination "..\portal-omnichannel\" -Recurse -Force
```

### URLs
- Dev: `http://localhost:5173/` (con Vite proxy)
- Prod: `http://{domain}/automatiza-tech/portal-omnichannel/`

---

## 11. Emails del Sistema

### Emails enviados:
1. **Bienvenida agente**: Credenciales al crear agente
2. **Reset de contraseña**: Token de recuperación
3. **Vencimiento de período**: Notificación a cliente
4. **Vencimiento a agente**: Notificación al agente
5. **Ticket creado**: Confirmación al agente/usuario
6. **Ticket estado cambiado**: Notificación de actualización
7. **Ticket cerrado**: Notificación de cierre
8. **Respuesta admin en ticket**: Notificación de nueva respuesta

### Template de email de soporte:
- Header con logo AutomatizaTech + gradiente indigo/violeta
- Subtítulo con tipo de notificación
- Cuerpo con datos del ticket
- Botón "Ir al Portal de Soporte"
- Footer con datos de la empresa

---

## 12. Guía Rápida para Modificaciones

### Agregar una nueva vista:
1. Crear `NuevaView.jsx` en `src/components/`
2. Importar en `App.jsx`
3. Agregar al objeto `views` correspondiente al rol
4. Agregar item de navegación en `Sidebar.jsx` (en el array correspondiente)
5. Agregar rutas en `api-omnichannel.php`
6. Agregar métodos en `omnichannel-controller.php`

### Agregar una nueva tabla:
1. Crear `setup-nueva-tabla.php`
2. Ejecutar vía browser: `http://{domain}/automatiza-tech/setup-nueva-tabla.php`
3. Agregar métodos CRUD en controller
4. Agregar rutas en API router
5. Agregar funciones en `api.js`

### Agregar un nuevo tipo de email:
1. Agregar `case` en `send_ticket_email()` o crear nuevo método
2. El wrapper HTML con logo está en la sección final de `send_ticket_email()`
3. Usar `self::email_headers()` para headers con BCC

---

## 13. Constantes Importantes (wp-config.php)

```php
define('OMNI_BCC_EMAILS', 'email1@domain.com,email2@domain.com');
define('OMNI_CRON_SECRET', 'secret_key_here');
define('OMNI_ADMIN_TOKEN_SECRET', 'hmac_secret_here');
```

---

## 14. Convenciones de Código

- **PHP**: CamelCase para clase, snake_case para métodos y variables
- **JavaScript**: camelCase para funciones y variables, PascalCase para componentes
- **CSS**: Clases Tailwind inline, CSS custom solo para layouts complejos (sidebar, inbox)
- **Nombres de archivo**: PascalCase para componentes React, kebab-case para PHP
- **Git**: Branch `prod-sync-*` para sincronización de producción
- **Idioma UI**: Español (es-CL locale)
- **Validación**: Caracteres prohibidos `< > { } " ; \` en inputs de texto
- **Contraseñas**: Mínimo 8 caracteres, 1 mayúscula, 1 minúscula, 1 número, 1 símbolo
