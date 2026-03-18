# Manual del Programador — Portal OmniCliente AutomatizaTech

> **Versión:** 1.0  
> **Última actualización:** 17 de Marzo de 2026  
> **Dirigido a:** Desarrolladores encargados de mantener y extender el sistema

---

## 1. Arquitectura General

```
┌──────────────────────────────────────────────────┐
│                   FRONTEND (SPA)                  │
│   React 19.1 + Vite 6.4 + Tailwind CSS 3.4      │
│   client-portal-omnichannel/src/                  │
└──────────────┬───────────────────────────────────┘
               │  HTTP (JSON)
               │  Headers: X-Admin-Token | X-Agent-Token | X-API-Key
               ▼
┌──────────────────────────────────────────────────┐
│              API ROUTER (PHP)                     │
│   api-omnichannel.php (~1380 líneas)             │
│   - Parsea rutas desde $_GET['route']            │
│   - Autenticación por headers                    │
│   - CORS configurado                             │
└──────────────┬───────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────┐
│           CONTROLLER (PHP)                        │
│   omnichannel-controller.php (~3150 líneas)      │
│   - Clase OmnichannelController                  │
│   - Métodos CRUD para cada entidad               │
│   - Sistema de auditoría (set_actor)             │
│   - Envío de emails                              │
└──────────────┬───────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────┐
│           BASE DE DATOS (MySQL 9.1)              │
│   Prefijo: wp_omnichannel_                       │
│   ~15 tablas principales                         │
└──────────────────────────────────────────────────┘
```

---

## 2. Setup del Entorno de Desarrollo

### Requisitos
- WAMP64 (PHP 8.3+, MySQL 9.1+, Apache)
- Node.js 18+ y npm
- WordPress instalado en `c:\wamp64\www\automatiza-tech\`

### Instalación

```bash
cd c:\wamp64\www\automatiza-tech\client-portal-omnichannel
npm install
```

### Ejecución en Desarrollo

```bash
npm run dev
# → http://localhost:5173/
# Proxy automático: /api-omnichannel.php → http://localhost/automatiza-tech/api-omnichannel.php
```

### Build de Producción

```bash
npm run build
```

### Deploy

```powershell
Copy-Item -Path "client-portal-omnichannel\dist\*" -Destination "portal-omnichannel\" -Recurse -Force
```

---

## 3. Frontend — Estructura Detallada

### 3.1 Entry Points

| Archivo | Función |
|---------|---------|
| `main.jsx` | Monta `<App />` en `#root` |
| `App.jsx` | Auth check, routing por estado, layout con sidebar |
| `api.js` | Todas las funciones de comunicación con el backend |
| `index.css` | Variables CSS, estilos globales, responsive rules |

### 3.2 Sistema de Routing (sin React Router)

```jsx
// App.jsx
const [currentView, setCurrentView] = useState('inbox');

const views = {
  inbox: <InboxView />,
  channels: <ChannelsView />,
  // ...
};

// Render:
{views[currentView] || views.inbox}
```

La navegación se delega al `Sidebar` que llama `onNavigate(viewId)`.

### 3.3 Sistema de Autenticación (api.js)

```
setApiKey(key):
  - Si key empieza con '__wp_admin_session__' → modo Admin
  - Si key empieza con '__agent_session__'    → modo Agente
  - De lo contrario                            → modo Cliente (API key)

request(endpoint, options):
  - Auto-prefija 'admin/' o 'agent/' según modo
  - Agrega header de auth correspondiente
  - Maneja respuesta JSON y errores
```

### 3.4 Componentes

#### Vistas Principales (Views)
| Componente | Líneas | Descripción |
|------------|--------|-------------|
| `InboxView` | ~421 | Bandeja de conversaciones, chat en tiempo real, mobile toggle |
| `ChannelsView` | ~221 | CRUD canales, formulario dinámico por tipo |
| `ChannelTypesView` | ~328 | CRUD tipos de canal con campos dinámicos |
| `BotsView` | ~264 | Configuración de bots por canal |
| `AgentsView` | ~473 | CRUD agentes con paginación |
| `AuditView` | ~248 | Timeline de auditoría expandible |
| `ClientsView` | ~622 | CRUD clientes, importación WP/CRM |
| `DashboardView` | ~259 | Stats, auditoría, métricas |
| `ProfileView` | ~382 | Perfil agente, avatar, cambio de contraseña (3 pasos) |
| `SupportView` | ~600 | Tickets soporte, admin: master-detail, agent: lista→detalle |
| `LoginScreen` | ~889 | Login 3 tabs, recuperación contraseña, soporte público |

#### Componentes Auxiliares
| Componente | Función |
|------------|---------|
| `Sidebar` | Navegación lateral, roles, dark mode toggle |
| `ConfirmDeleteModal` | Confirmación genérica de eliminación |
| `ResultModal` | Feedback de éxito/error |
| `ExpiryWarningModal` | Aviso de vencimiento de período |
| `TicketNotificationModal` | Notificación de nuevos tickets (admin) |
| `ChannelBadge` | Badge de tipo de canal |

### 3.5 Patrón de Responsive

**Breakpoint principal:** `768px` (Tailwind `sm:`)

**Patrones usados:**

```jsx
// Grid responsive
<div className="grid grid-cols-1 sm:grid-cols-2 gap-4">

// Flex responsive
<div className="flex flex-col sm:flex-row items-start sm:items-center gap-3">

// Ocultar en mobile
<span className="hidden sm:block">Texto largo</span>

// Mobile detection interna (SupportView, InboxView)
const [isMobile, setIsMobile] = useState(window.innerWidth <= 768);
useEffect(() => {
  const onResize = () => setIsMobile(window.innerWidth <= 768);
  window.addEventListener('resize', onResize);
  return () => window.removeEventListener('resize', onResize);
}, []);
```

**CSS responsive (`index.css`):**
```css
@media (max-width: 768px) {
  .sidebar { position: fixed; left: -280px; }
  .sidebar.mobile-open { left: 0; }
  .inbox-panel { width: 100%; min-width: 100%; }
  .inbox-panel.mobile-hidden { display: none; }
  .chat-window-panel.mobile-hidden { display: none; }
}
```

---

## 4. Backend — Estructura Detallada

### 4.1 Router API (`api-omnichannel.php`)

```php
// Carga WordPress
require_once($wp_load_path);

// CORS
header('Access-Control-Allow-Origin: ...');

// Parseo de ruta
$route = sanitize_text_field($_GET['route'] ?? '');
$segments = explode('/', trim($route, '/'));
$method = $_SERVER['REQUEST_METHOD'];

// Autenticación por grupo de rutas
// Público: webhook/*, cron/*, public/*
// Admin: admin/* → authenticate_admin()
// Agent: agent/* → authenticate_agent()
// Client: * → authenticate_client()
```

**Generar Admin Token:**
```php
function generate_admin_token($user_id) {
    $expiry = time() + (7 * 86400); // 7 días
    $payload = $user_id . ':' . $expiry;
    $signature = hash_hmac('sha256', $payload, OMNI_ADMIN_TOKEN_SECRET);
    return base64_encode($payload . ':' . $signature);
}
```

### 4.2 Controller (`omnichannel-controller.php`)

```php
class OmnichannelController {
    private $wpdb;
    private $prefix;          // wp_omnichannel_
    private $actor_email;     // Para auditoría
    private $actor_name;
    private $actor_role;
    
    // Métodos principales por entidad:
    // create_*, get_*, get_*s(), update_*, delete_*
    
    // Sistema de auditoría:
    public function set_actor($email, $name, $role) { ... }
    private function audit_log($action, $entity_type, $entity_id, $details) { ... }
}
```

### 4.3 Patrón de Método CRUD

```php
public function create_channel($data) {
    // 1. Sanitizar inputs
    $name = sanitize_text_field($data['name'] ?? '');
    
    // 2. Validar
    if (empty($name)) return new WP_Error('missing_field', 'Name required');
    
    // 3. Insertar
    $this->wpdb->insert($this->prefix . 'channels', [
        'name' => $name,
        // ...
    ]);
    
    // 4. Auditar
    $this->audit_log('create', 'channel', $this->wpdb->insert_id, "Canal creado: {$name}");
    
    // 5. Retornar
    return ['id' => $this->wpdb->insert_id, 'message' => 'Canal creado'];
}
```

### 4.4 Patrón de Ruta

```php
// En api-omnichannel.php
case 'channels':
    if ($method === 'GET') {
        json_response($ctrl->get_channels());
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        json_response($ctrl->create_channel($data));
    }
    break;

case 'channels' when isset($segments[1]):
    $id = absint($segments[1]);
    if ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        json_response($ctrl->update_channel($id, $data));
    } elseif ($method === 'DELETE') {
        json_response($ctrl->delete_channel($id));
    }
    break;
```

---

## 5. Base de Datos

### 5.1 Tablas Principales

| Tabla | Columnas Clave | Descripción |
|-------|---------------|-------------|
| `omnichannel_clients` | id, company_name, api_key, plan_type, status, period_start, period_end, is_free | Empresas cliente |
| `omnichannel_channels` | id, client_id, channel_type_id, name, config (JSON), status | Canales de comunicación |
| `omnichannel_channel_types` | id, name, icon, description, fields (JSON) | Plantillas de canal |
| `omnichannel_conversations` | id, client_id, channel_id, contact_name, status, assigned_agent_id | Conversaciones |
| `omnichannel_messages` | id, conversation_id, direction, content, sender_type | Mensajes |
| `omnichannel_bots` | id, client_id, channel_id, system_prompt, model, temperature | Config bots |
| `omnichannel_agents` | id, client_id, name, email, password_hash, role, department, skills (JSON), avatar_url | Agentes |
| `omnichannel_audit_log` | id, action, entity_type, entity_id, details, actor_email, actor_name, created_at | Auditoría |
| `omnichannel_support_tickets` | id, ticket_number, agent_id, agent_name, agent_email, subject, description, category, priority, status | Tickets |
| `omnichannel_ticket_messages` | id, ticket_id, sender_type, sender_id, sender_name, message, attachments (JSON) | Mensajes ticket |

### 5.2 Scripts de Migración

Los archivos `setup-*.php` crean/modifican tablas. Se ejecutan vía browser:
```
http://{domain}/automatiza-tech/setup-nombre-tabla.php
```

**Convención para nuevas migraciones:**
```php
<?php
require_once __DIR__ . '/wp-load.php';
global $wpdb;

$charset = $wpdb->get_charset_collate();
$prefix = $wpdb->prefix . 'omnichannel_';

// CREATE TABLE IF NOT EXISTS ...
$sql = "CREATE TABLE IF NOT EXISTS {$prefix}nueva_tabla (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    // columnas...
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) {$charset}";

$wpdb->query($sql);
echo "✅ Tabla creada correctamente";
```

---

## 6. Sistema de Emails

### 6.1 Headers Comunes

```php
public static function email_headers() {
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    if (defined('OMNI_BCC_EMAILS') && OMNI_BCC_EMAILS) {
        foreach (explode(',', OMNI_BCC_EMAILS) as $bcc) {
            $headers[] = 'Bcc: ' . trim($bcc);
        }
    }
    return $headers;
}
```

### 6.2 Template de Email de Soporte

Los emails de soporte usan un template branded con:
1. **Header**: Logo AutomatizaTech + nombre + subtítulo sobre gradiente indigo/violeta
2. **Subtítulo**: Tipo de notificación sobre fondo indigo sólido
3. **Cuerpo**: Saludo personalizado + contenido dinámico
4. **CTA**: Botón "Ir al Portal de Soporte"
5. **Footer**: Datos de empresa + email de contacto

```php
$logo_url = get_site_url() . '/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
$portal_url = get_site_url() . '/portal-omnichannel/';
```

### 6.3 Tipos de Email de Soporte

| Tipo | Subject Pattern | Contenido |
|------|----------------|-----------|
| `created` | "Ticket Creado: TK-..." | Tabla con datos del ticket |
| `status_changed` | "Ticket TK-... — Estado actualizado a: ..." | Tabla con nuevo estado |
| `closed` | "Ticket TK-... — Cerrado" | Mensaje + notas admin opcionales |
| `admin_reply` | "Ticket TK-... — Nueva respuesta del equipo" | Último mensaje citado |

---

## 7. Sistema de Uploads

### 7.1 Frontend

```javascript
// api.js
export async function uploadTicketImages(files) {
    const formData = new FormData();
    files.forEach(f => formData.append('images[]', f));
    // POST a /agent/ticket-images o /admin/tickets/upload-images
    return await request('ticket-images', { method: 'POST', body: formData, raw: true });
}
```

### 7.2 Backend

```php
// api-omnichannel.php — route: ticket-images
$uploaded_urls = [];
foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
    $file = [
        'name'     => $_FILES['images']['name'][$i],
        'type'     => $_FILES['images']['type'][$i],
        'tmp_name' => $tmp,
        'error'    => $_FILES['images']['error'][$i],
        'size'     => $_FILES['images']['size'][$i],
    ];
    // Validar tipo y tamaño
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    if (!in_array($file['type'], $allowed)) continue;
    if ($file['size'] > 3 * 1024 * 1024) continue; // 3MB max
    
    $upload = wp_handle_upload($file, ['test_form' => false]);
    if (!empty($upload['url'])) $uploaded_urls[] = $upload['url'];
}
json_response(['urls' => $uploaded_urls]);
```

### 7.3 Almacenamiento de Attachments

Los URLs de imágenes se almacenan como JSON en la columna `attachments` de `ticket_messages`:
```json
["https://domain.com/wp-content/uploads/2026/03/image1.jpg", "https://domain.com/wp-content/uploads/2026/03/image2.png"]
```

---

## 8. Guía de Extensión

### 8.1 Agregar Nueva Vista

1. **Crear componente**: `src/components/NuevaView.jsx`
2. **Importar en App.jsx**: `import NuevaView from './components/NuevaView';`
3. **Agregar al objeto views** según el rol:
   ```jsx
   const views = {
     // ...existentes
     'nueva-vista': <NuevaView />,
   };
   ```
4. **Agregar al sidebar** en el array de nav correspondiente (`navItems`, `agentNavItems`, etc.):
   ```jsx
   { id: 'nueva-vista', label: 'Nueva Vista', icon: IconName },
   ```

### 8.2 Agregar Nuevo Endpoint API

1. **Controller** — Agregar método en `omnichannel-controller.php`:
   ```php
   public function mi_nuevo_metodo($data) {
       // Sanitizar, validar, ejecutar, auditar, retornar
   }
   ```

2. **Router** — Agregar ruta en `api-omnichannel.php`:
   ```php
   case 'mi-nueva-ruta':
       if ($method === 'GET') { json_response($ctrl->mi_nuevo_metodo()); }
       break;
   ```

3. **Frontend** — Agregar función en `api.js`:
   ```javascript
   export async function miNuevaFuncion(params) {
       return await request('mi-nueva-ruta?' + new URLSearchParams(params));
   }
   ```

### 8.3 Agregar Nueva Tabla

1. Crear `setup-mi-tabla.php` siguiendo la convención
2. Ejecutar en browser
3. Agregar métodos CRUD en controller
4. Agregar rutas en router
5. Agregar funciones en api.js
6. Crear vista frontend

### 8.4 Agregar Nuevo Tipo de Email

En `send_ticket_email()` o nuevo método:
```php
case 'mi_tipo':
    $email_subject = "...";
    $heading = '...';
    $body_msg = "<p>...</p>";
    break;
```
El wrapper HTML con logo se aplica automáticamente.

---

## 9. Seguridad

### 9.1 Autenticación

- **Admin Token**: HMAC-SHA256 con secreto en wp-config.php: `OMNI_ADMIN_TOKEN_SECRET`
- **Agent Token**: Token generado en login, verificado por email lookup
- **API Key**: String único por cliente, header `X-API-Key`
- **Secreto CRON**: `OMNI_CRON_SECRET` para webhooks y cron jobs

### 9.2 Sanitización

| Contexto | Función |
|----------|---------|
| Texto general | `sanitize_text_field()` |
| Email | `sanitize_email()` |
| URL | `esc_url_raw()` |
| HTML (salida) | `esc_html()` |
| HTML (contenido rico) | `wp_kses_post()` |
| Enteros | `absint()` |
| SQL | `$wpdb->prepare()` con placeholders `%s`, `%d` |

### 9.3 Validación de Contraseñas

```javascript
// Frontend validation
- Mínimo 8 caracteres
- Al menos 1 mayúscula
- Al menos 1 minúscula
- Al menos 1 número
- Al menos 1 símbolo
- Caracteres prohibidos: < > { } " ; \
```

### 9.4 CORS

Configurado en `api-omnichannel.php`:
```php
$allowed_origins = [
    'http://localhost:5173', 'http://localhost:5174',
    'http://localhost:5175', 'http://localhost:5176',
    'http://localhost:3000',
    get_site_url(),
];
```

### 9.5 Uploads

- Tipos permitidos: `image/jpeg`, `image/png`, `image/webp`, `image/gif`
- Tamaño máximo: 3MB (tickets), 2MB (avatars)
- Procesado vía `wp_handle_upload()` (WordPress sanitiza y valida)

---

## 10. Depuración

### 10.1 Logs

- **PHP**: Errores en `c:\wamp64\logs\php_error.log`
- **Apache**: `c:\wamp64\logs\apache_error.log`
- **Frontend**: `console.error()` en browser DevTools

### 10.2 Verificar API

```bash
# Test endpoint (PowerShell)
Invoke-RestMethod -Uri "http://localhost/automatiza-tech/api-omnichannel.php?route=admin/session-check" -Headers @{"X-Admin-Token"="tu_token"} -Method GET
```

### 10.3 Verificar Base de Datos

```sql
-- Ver tablas omnichannel
SHOW TABLES LIKE 'wp_omnichannel_%';

-- Ver tickets recientes
SELECT * FROM wp_omnichannel_support_tickets ORDER BY id DESC LIMIT 10;

-- Ver log de auditoría
SELECT * FROM wp_omnichannel_audit_log ORDER BY id DESC LIMIT 20;
```

---

## 11. Convenciones de Código

| Aspecto | Convención |
|---------|-----------|
| Componentes React | PascalCase (`SupportView.jsx`) |
| Funciones JS | camelCase (`getTickets`, `handleCreate`) |
| Archivos PHP setup | kebab-case (`setup-ticket-attachments.php`) |
| Métodos PHP | snake_case (`create_ticket`, `send_ticket_email`) |
| Tablas BD | snake_case con prefijo (`wp_omnichannel_support_tickets`) |
| CSS classes | Tailwind inline; custom CSS solo para layouts complejos |
| Idioma UI | Español (locale `es-CL`) |
| Git branch | `prod-sync-YYYY-MM-DD` para producción |
| Indentación | 2 espacios (JS/JSX), 4 espacios (PHP) |

---

## 12. Dependencias

### Frontend (package.json)

```json
{
  "dependencies": {
    "lucide-react": "^0.577.0",
    "react": "19.1.0",
    "react-dom": "19.1.0"
  },
  "devDependencies": {
    "@vitejs/plugin-react": "^4.3.4",
    "autoprefixer": "^10.4.21",
    "postcss": "^8.5.4",
    "tailwindcss": "^3.4.17",
    "vite": "^6.3.5"
  }
}
```

### Backend
- WordPress (como framework PHP)
- No hay composer.json — usa funciones nativas de WordPress

### Vite Config Highlights
```javascript
{
  base: './',           // Rutas relativas para deploy en subdirectorio
  build: { outDir: 'dist' },
  server: {
    port: 5173,
    proxy: {
      '/api-omnichannel.php': {
        target: 'http://localhost/automatiza-tech',
        changeOrigin: true,
      }
    }
  }
}
```
