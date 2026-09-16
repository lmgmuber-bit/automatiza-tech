# Contexto Completo: AutomatizaTech

## 🏢 ¿Qué es AutomatizaTech?

AutomatizaTech es una empresa especializada en **automatización de procesos de negocio mediante chatbots inteligentes**. Ofrece soluciones de automatización para negocios que integran ventas, web y CRM. El sitio web es https://automatizatech.shop

## 🏷️ Identidad de Marca

- **Nombre oficial:** Automatiza Tech (también escrito como "AutomatizaTech" en contextos técnicos como el dominio `automatizatech.shop`)
- **Slogan:** "Conectamos tus ventas, web y CRM."
- **Tagline:** "Bots inteligentes para negocios que no se detienen."

### Paleta de Colores
- 🔵 Azul eléctrico `#1e40af`: tecnología y confianza
- ⚪ Blanco `#ffffff`: claridad y simplicidad
- 🟢 Verde lima `#06d6a0`: innovación y energía
- ⬛ Gris `#6b7280`: texto secundario

### Tipografía
- Titulares: Poppins SemiBold / Montserrat Bold
- Texto base: Open Sans / Roboto

### Iconografía
- Chatbots, engranajes, nube conectada, reloj 24/7, flechas de flujo (Font Awesome)

---

## 🌐 Secciones del Sitio Web

1. **Hero** con CTA "Solicita tu Demo" y botón WhatsApp
2. **Beneficios/Features**: "Automatiza tu atención, ahorra tiempo, escala tu negocio"
3. **Integraciones**: WhatsApp Business, Instagram DM, Web, CRM
4. **Casos de uso por industria**
5. **Planes y Precios** (gestionados desde el admin)
6. **Formulario de Contacto** (genera leads y facturas automáticas)
7. **Contacto directo por WhatsApp**

---

## 🛠️ Stack Tecnológico

- **CMS:** WordPress 6.x (actualmente 6.8.3, compatible con Hostinger)
- **Tema:** Tema personalizado `automatiza-tech` (en `wp-content/themes/automatiza-tech/`)
- **Framework CSS:** Bootstrap 5
- **Fuentes:** Google Fonts (Poppins, Open Sans)
- **Iconos:** Font Awesome
- **Base de datos:** MySQL
- **Email SMTP:** Hostinger SMTP (`smtp.hostinger.com:587 TLS`) en producción; Gmail SMTP en desarrollo
- **PDF:** FPDF 1.86 (librería PHP pura, sin dependencias externas)
- **Hosting:** Hostinger

---

## 🏗️ Arquitectura del Sistema

### Estructura de Archivos Principales

```
wp-content/themes/automatiza-tech/
├── style.css                    # Estilos principales + metadata del tema
├── functions.php                # Punto de entrada principal, carga todos los módulos
├── index.php                    # Template principal del sitio
├── header.php                   # Header del sitio
├── footer.php                   # Footer del sitio
├── services-frontend.php        # Renderizado de servicios/planes en el frontend
├── services-manager.php         # CRUD de servicios desde el admin
├── services-admin.php           # Interfaz admin de servicios
├── assets/
│   ├── css/                     # Estilos adicionales
│   ├── js/                      # Scripts JavaScript
│   └── images/                  # Imágenes del tema
├── inc/
│   ├── contact-form.php         # Formulario de contacto + gestión de contactos/clientes
│   ├── contact-shortcode.php    # Shortcode del formulario de contacto
│   ├── invoice-settings.php     # Panel de configuración de datos de facturación
│   ├── invoice-handlers.php     # Manejadores AJAX para facturas
│   ├── smtp-config.php          # Configuración SMTP (producción: Hostinger)
│   ├── customizer.php           # Opciones del Customizer de WordPress
│   ├── template-functions.php   # Funciones de plantilla
│   ├── services-manager.php     # Gestión de servicios (clase PHP)
│   ├── currency-admin.php       # Panel admin de monedas
│   └── currency-updater.php     # Actualización de tasas de cambio
└── lib/
    └── invoice-pdf-fpdf.php     # Generación de PDFs con FPDF
```

### Clases Principales

| Clase | Archivo | Responsabilidad |
|-------|---------|-----------------|
| `AutomatizaTechContactForm` | `inc/contact-form.php` | Formulario de contacto, gestión de contactos y clientes, envío de emails |
| `AutomatizaTechServicesManager` | `inc/services-manager.php` | CRUD completo de servicios/planes desde el admin |

---

## 🗄️ Base de Datos

### Tablas Personalizadas

#### `wp_automatiza_tech_contacts`
Leads/contactos que llenan el formulario del sitio.
- `id`, `name`, `email`, `company`, `phone`, `message`, `status`, `created_at`

#### `wp_automatiza_tech_clients`
Clientes que han contratado un plan (convertidos desde contactos).
- `id`, `contact_id`, `name`, `email`, `company`, `phone`, `country`, `plan_id`, `total_amount`, `currency`, `created_at`, `updated_at`

#### `wp_automatiza_tech_invoices`
Registro de facturas generadas.
- `id`, `client_id`, `invoice_number`, `plan_id`, `total_amount`, `currency`, `invoice_html`, `pdf_path`, `created_at`

#### `wp_automatiza_services`
Servicios y planes configurables desde el admin.
- `id`, `name`, `category` (pricing/features/integration), `price_usd`, `price_clp`, `description`, `features`, `icon`, `highlight`, `button_text`, `whatsapp_message`, `status`, `service_order`

#### `wp_options` (claves personalizadas)
- `automatiza_company_name`, `automatiza_company_rut`, `automatiza_company_giro`
- `automatiza_company_address`, `automatiza_company_email`, `automatiza_company_phone`, `automatiza_company_website`

---

## 💰 Sistema de Facturación Multi-Moneda (v2.0)

### Países y Monedas Soportados (18 países)
- 🇨🇱 **Chile** (+56): CLP con IVA 19%
- 🌎 **Internacional** (18 países): USD sin IVA

### Detección de País (por prioridad)
1. Campo `country` en la base de datos del cliente
2. Código telefónico del teléfono ingresado
3. Por defecto: Chile (CL)

### Numeración de Facturas
Formato: `AT-YYYYMMDD-XXXX` (ej: `AT-20251111-0001`)

### Almacenamiento de PDFs
`/wp-content/uploads/invoices/AT-YYYYMMDD-XXXX.pdf`

### Panel de Configuración
- Acceso: WordPress Admin → "Datos Facturación"
- URL: `/wp-admin/admin.php?page=automatiza-invoice-settings`
- Permite configurar datos de empresa sin tocar código

---

## 📧 Sistema de Emails Automáticos

### Email #1 – Notificación interna de nuevo contacto
- **Trigger:** Usuario envía el formulario de contacto
- **Destinatario:** automatizatech.bots@gmail.com
- **Asunto:** `📧 Nuevo contacto desde Automatiza Tech - [Nombre]`

### Email #2 – Factura al cliente
- **Trigger:** Contacto convertido a cliente desde el admin
- **Destinatario:** Email del cliente
- **Asunto:** `Bienvenido a AutomatizaTech - Factura AT-YYYYMMDD-XXXX - [Nombre]`
- **Adjunto:** PDF de factura generado con FPDF

### Email #3 – Notificación interna de cliente contratado
- **Trigger:** Después de convertir contacto a cliente
- **Destinatario:** automatizatech.bots@gmail.com
- **Asunto:** `🎉 ¡Nuevo Cliente Contratado! - [Nombre] - Plan: [Plan]`

---

## ⚙️ Configuración de Entornos

### Producción (Hostinger)
- SMTP: `smtp.hostinger.com:587 TLS`
- Credenciales en `wp-config.php`: `SMTP_USER`, `SMTP_PASS`
- Dominio: `automatizatech.shop`

### Desarrollo (Local)
- Entorno XAMPP/WAMP/Laragon
- SMTP: Gmail (`smtp.gmail.com:587`)
- Configuración en `wp-config-local.php`

---

## 🔌 Hooks y Shortcodes Importantes

### Shortcodes
- `[automatiza_contact_form]` – Formulario de contacto

### AJAX Actions (WordPress)
- `submit_contact_form` – Envía el formulario de contacto
- `check_phone_exists` – Verifica si el teléfono ya existe
- `get_contact_details` – Obtiene detalles de un contacto
- `get_client_details` – Obtiene detalles de un cliente
- `search_contacts` / `search_clients` – Búsqueda
- `filter_contacts` – Filtrar contactos
- `save_service` / `delete_service` – CRUD de servicios
- `toggle_service_status` – Activar/desactivar servicio
- `duplicate_service` – Duplicar servicio

---

## 📋 Guías de Desarrollo

- Utiliza WordPress en su versión más estable compatible con Hostinger
- Utiliza Bootstrap 5 para estilos y componentes
- Usa buenas prácticas de WordPress (hooks, sanitización, escape de salida)
- Motor de base de datos: MySQL (usar `$wpdb` para queries)
- Refactoriza el código siempre que sea posible
- Crea tests de las funcionalidades principales
- Siempre escapar salidas con `esc_html()`, `esc_attr()`, `esc_url()`
- Sanitizar entradas con `sanitize_text_field()`, `sanitize_email()`, etc.
- Verificar nonces en formularios y acciones AJAX
- Evitar acceso directo con `if (!defined('ABSPATH')) { exit; }`
- Comentar el código en español siguiendo el estilo del proyecto
- Los archivos PHP deben ser UTF-8 sin BOM
