# AutomatizaTech — Documento Técnico Completo

> ⚠️ **DOCUMENTO HISTÓRICO (Feb 2026, rama `prod-sync-2025-06-26`)** — Puede contener datos desactualizados.
> La fuente única de verdad actual es **[`Docs/MASTER/`](./MASTER/00_INDEX.md)**.
> Discrepancias: rama obsoleta, ~40 workflows (real: ~63), sin contratos, sin hardening Phase 0.

> **Propósito:** Este documento sirve como contexto técnico integral para cualquier IA, desarrollador o equipo que necesite entender la arquitectura, código y funcionamiento de AutomatizaTech.  
> **Última actualización:** 28 de Febrero 2026 — Rama `prod-sync-2025-06-26` (commit `b1c05b9`)  
> **IMPORTANTE:** Este documento debe actualizarse cada vez que se suba un cambio a PROD.

---

## 1. VISIÓN GENERAL

**AutomatizaTech** es una plataforma web empresarial construida sobre **WordPress 6.8.3** que funciona como un **CRM + Sistema de Automatización + Agente IA** todo-en-uno para una empresa de consultoría tecnológica chilena. No es un blog ni un sitio estático: es un sistema de gestión completo.

### Stack tecnológico
| Componente | Tecnología | Versión/Detalle |
|---|---|---|
| CMS | WordPress | 6.8.3 |
| Backend | PHP | 8.2+ |
| Frontend | Bootstrap + jQuery | 5.3 / 3.6 |
| IA | OpenAI GPT-4o / GPT-4o-mini | Chat, Vision, TTS, Whisper |
| Automatización | n8n (self-hosted) | https://n8n-n8n.kchiba.easypanel.host |
| Estado WhatsApp | Redis | Vía n8n |
| PDFs | FPDF 1.86 | Facturas, cotizaciones, boletas, reportes |
| Gráficos | Chart.js | Dashboard administrativo |
| Hosting PROD | Hostinger | LiteSpeed, automatizatech.cl |
| Hosting LOCAL | WAMP64 | Xdebug habilitado, MySQL 5.7.31 |
| Google Drive | Service Account + OAuth 2.0 | Doble integración |
| Google Calendar | REST API vía n8n | Agendamiento |
| SMTP | PHPMailer | Credenciales encriptadas en Bóveda (`smtp.hostinger.com:587 TLS`) |
| Control de versiones | Git + GitHub | Rama activa: `prod-sync-2025-06-26` |

### Entornos
| Entorno | URL/Host | BD | Prefijo tablas | Notas |
|---|---|---|---|---|
| **PROD** | automatizatech.cl | u402745362_automatizatech | wp_ | Hostinger LiteSpeed |
| **LOCAL** | localhost/automatiza-tech | automatiza_tech_local | wp_ | WAMP64, Xdebug, banner naranja "AMBIENTE LOCAL" |

### Detección de entorno
El `wp-config.php` detecta el entorno vía `$_SERVER['HTTP_HOST']`:
- Si contiene `localhost` → Credenciales locales (root, sin password)
- Si no → Credenciales PROD (Hostinger)
- `WP_DEBUG = true` solo en LOCAL, `WP_DEBUG_DISPLAY = false` siempre (para no contaminar AJAX JSON)

### Banner LOCAL
En `functions.php` se inyecta un banner naranja fijo en la parte superior con texto "⚠ AMBIENTE LOCAL — NO ES PRODUCCIÓN ⚠" en frontend, admin y login — solo cuando `home_url()` contiene `localhost` o `.local`.

---

## 2. ARQUITECTURA DE ARCHIVOS

### 2.1 Ramas Git
| Rama | Propósito | Commit | Estado |
|---|---|---|---|
| `prod-sync-2025-06-26` | **Rama activa** — Sync con PROD al 26-Jun-2025 | `b1c05b9` | ✅ Activa |
| `AutomatizaTechV4` | Rama de trabajo anterior (pre-sync) | `090d991` | Referencia |
| `main` | Legacy estable | `152f9bf` | No se usa |
| `copilot/update-automatia-tech-info` | PR #4 — actualización de docs | `adcea0a` | Abierta |
| `developer` | Desarrollo anterior | `f4a3cdc` | Inactiva |
| `feature/AutomatizaTechV3` | Versión anterior | `137a6a0` | Inactiva |

> **IMPORTANTE:** El despliegue a PROD se hace **manualmente** (subida de archivos a Hostinger), NO mediante git push.

### 2.2 .gitignore — Qué NO se versiona
- `wp-admin/`, `wp-includes/`, archivos core WP raíz (wp-login.php, wp-settings.php, etc.)
- `wp-content/uploads/` (binarios: PDFs, imágenes, QR, audio)
- `wp-content/themes/twentytwenty*/` (temas default)
- `wp-content/plugins/duplicator/`, `litespeed-cache/`, `pixelyoursite/`
- `wp-config.php`, `.htaccess`, `wp-config-*.php`
- `*.sql`, `*.wpress`, `*.log`
- Scripts temporales: `test-*.php`, `debug-*.php`, `check-*.php`, `fix-*.php`, `add-*.php`, `setup-*.php`, `reset-*.php`, etc.
- Archivos backup: `*_old`, `*_old[0-9]*`, `*.php2`, `*.php3`

### 2.3 Estructura de directorios

```
automatiza-tech/
├── .github/
│   └── instructions.md                    # Contexto para GitHub Copilot
├── wp-content/
│   ├── mu-plugins/                        # 7 archivos — AUTO-CARGADOS por WordPress
│   │   ├── crm-ai-completo.php            # 7,305 líneas — CRM completo
│   │   ├── aria-agente-core.php           # 2,378 líneas — Motor IA MAXTECH
│   │   ├── aria-widget-flotante.php       # 1,141 líneas — Widget chat flotante
│   │   ├── google-drive-integration.php   # 764 líneas — Drive (Service Account)
│   │   ├── api-appointments-management.php# 711 líneas — API REST citas
│   │   ├── google-drive-oauth.php         # 285 líneas — Drive (OAuth + OCR)
│   │   └── api-appointments-config.php    # 151 líneas — API disponibilidad
│   ├── plugins/                           # Plugins de terceros
│   │   ├── akismet/                       # Anti-spam
│   │   └── all-in-one-wp-migration/       # Respaldos/migraciones
│   └── themes/automatiza-tech/            # Tema custom
│       ├── functions.php                  # 739 líneas — Entry point, carga 24 módulos
│       ├── style.css                      # 1,552 líneas — CSS variables + responsive
│       ├── header.php                     # 581 líneas — SEO, Open Graph, navbar sticky
│       ├── footer.php                     # 418 líneas — Footer 4-col, WhatsApp CTA
│       ├── index.php                      # 315 líneas — Landing page (hero, features, pricing)
│       ├── services-frontend.php          # 1,404 líneas — Shortcodes servicios/precios
│       ├── contact-form.php               # 3,884 líneas — Contact form (versión tema)
│       ├── contact-shortcode.php          # 671 líneas — Shortcode contacto
│       ├── customizer.php                 # 261 líneas — Opciones Customizer extendidas
│       ├── template-functions.php         # 408 líneas — Helpers de plantilla
│       ├── smtp-config.php                # 182 líneas — Config SMTP directa
│       ├── services-admin.php             # 516 líneas — Admin servicios
│       ├── services-manager.php           # 205 líneas — Backend servicios
│       ├── validar-factura.php            # 282 líneas — Validación facturas (tema)
│       ├── inc/                           # 24 módulos PHP (ver sección 3.2)
│       ├── lib/                           # FPDF 1.86 + 6 generadores PDF + QR
│       └── assets/
│           ├── chat/css/style.css         # 10.6 KB — Estilos chat
│           ├── chat/js/chat.js            # 40.9 KB — JS chat
│           ├── js/main.js                 # 12.2 KB — JS frontend principal
│           ├── js/currency-admin.js       # 6.6 KB — JS admin moneda
│           ├── js/client-operations.js    # 1.6 KB — JS operaciones cliente
│           └── images/                    # favicon.svg, logos SVG/PNG
├── api-chat-history.php                   # 179 líneas — API historial chat (n8n)
├── api-chat-proxy.php                     # 99 líneas — Proxy OpenAI centralizado
├── api-get-prompt.php                     # 104 líneas — System prompt propuestas
├── api-save-proposal.php                  # 43 líneas — Guardar propuesta (n8n)
├── validar-factura.php                    # 278 líneas — Validación pública facturas
├── validar-boleta.php                     # 169 líneas — Validación pública boletas
├── ver-demo.php                           # 477 líneas — Página demo propuesta
├── ver-presentacion.php                   # 44 líneas — Presentación por ID
├── purge-cache.php                        # 64 líneas — Purge LiteSpeed
├── tech-assistant.php                     # 481 líneas — Asistente multimodal standalone
├── openai-controller.php                  # 342 líneas — Clase OpenAIController
├── admin-ai-dashboard.php                 # 149 líneas — Dashboard consumo IA
├── admin-approve-proposal.php             # 197 líneas — Aprobar/rechazar propuestas
├── N8N/                                   # Workflows N8N
│   ├── PROD/                              # ~40 workflows producción + docs
│   └── TEMPLATES/                         # Templates genéricos + por cliente
├── Docs/                                  # Documentación técnica y diagramas
├── sql/                                   # DDL y dumps SQL
└── tools/                                 # ~88 scripts Node.js/PS1 para N8N y deploy
```

---

## 3. MÓDULOS DEL SISTEMA (DETALLE COMPLETO)

### 3.1 MU-PLUGINS (carga automática por WordPress)

Los mu-plugins se cargan ANTES que el tema. WordPress los ejecuta automáticamente sin necesidad de activación.

#### `crm-ai-completo.php` — CRM Completo (7,305 líneas)
- **Clases:** `AutomatizaTech_CRM_AI`, `AutomatizaTech_Clientes_List_Table` (extiende `WP_List_Table`)
- **Métodos públicos (33):**
  - `crear_tablas()` — Crea 4 tablas BD via `dbDelta`
  - `render_clientes_page()` — Listado de clientes
  - `render_ficha_cliente()` — Ficha detallada (~1,800 líneas: timeline, proyectos, operativo, branding)
  - `render_dashboard()` / `render_consumo_ai()` — Dashboard analítico con Chart.js
  - `render_agente()` — Página completa del agente MAXTECH inline (~570 líneas)
  - `render_busqueda_avanzada()` — Búsqueda multi-tabla
  - `render_public_timeline()` — Portal público de clientes (token HMAC)
  - `render_public_prospect_timeline()` — Portal público de prospectos (token HMAC)
  - `ajax_chat_cliente()` — Chat MAXTECH público (clientes chatean con GPT-4o + RAG desde Drive)
  - `ajax_convertir_cliente()` / `ajax_convertir_propuesta()` — Conversión prospecto→cliente
  - `ajax_crear_proyecto()` — Crea proyecto + envía email con enlace timeline
  - `ajax_actualizar_proyecto()` — Actualiza estado/detalles de proyecto
  - `ajax_agendar_seguimiento()` — Agenda reunión → Calendar + Email + WhatsApp
  - `ajax_eliminar_seguimiento()` — Elimina reunión + evento Calendar
  - `ajax_guardar_nota()` — Agrega nota a timeline
  - `ajax_update_timeline_item()` — Edita items en 3 tablas (clients_details, propuestas_details, crm_historial)
  - `crm_enviar_notificacion_historial()` — Email notificación de evento timeline
- **Métodos privados (6):** `_generar_token()`, `_generar_token_prospecto()`, `_ensure_db_schema()`, `_enviar_correo_bienvenida()`, `render_styles()`, `procesar_consulta_agente()`
- **Tablas BD creadas (4):** `crm_clientes`, `crm_historial`, `crm_proyectos`, `crm_chat_historial`
- **Tablas BD leídas (8 adicionales):** `ai_usage_log`, `automatiza_propuestas`, `automatiza_propuestas_details`, `automatiza_followup_meetings`, `automatiza_clients_details`, `automatiza_tech_clients`, `at_qa_projects`, `at_qa_modules`/`at_qa_cases`
- **AJAX (13):** `crm_buscar`⚠️, `crm_agente_consulta`, `crm_guardar_nota`, `crm_enviar_notificacion_historial`, `ajax_convertir_cliente`, `crm_convertir_propuesta`, `crm_crear_proyecto`, `crm_actualizar_proyecto`, `crm_update_timeline_item`, `crm_agendar_seguimiento`, `crm_eliminar_seguimiento`, `crm_chat_cliente`+nopriv, `crm_chat_history`+nopriv
- ⚠️ **Bug conocido:** `wp_ajax_crm_buscar` → `ajax_buscar` no existe (dead hook)
- **Integraciones:** OpenAI Chat/Whisper/TTS, Google Drive OAuth (RAG en chat clientes), Google Calendar (via N8N), N8N WhatsApp/Email, Credentials Vault, Chart.js CDN

#### `aria-agente-core.php` — Motor IA MAXTECH (2,378 líneas)
- **Clase:** `ARIA_Agente`
- **Métodos públicos (16):** `procesar_chat()`, `procesar_upload()`, `generar_audio()`, `obtener_historial()`, `cargar_sesion()`, `nueva_sesion()`, `buscar_cliente()`, `obtener_disponibilidad()`, `agendar_seguimiento()`, `ajax_obtener_documento_cliente()`, `ajax_leer_archivo_drive()`, `ajax_listar_carpeta_drive()`, `agregar_menu_config()`, `registrar_settings()`, `pagina_config()`, `limpiar_output()`
- **Métodos privados (24):** Incluyen `construir_mensajes()` (system prompt masivo ~8,000 chars con conocimiento completo del negocio, servicios, precios, equipo, menús, módulos), `obtener_contexto_crm()`, `obtener_contexto_qa()`, `obtener_contexto_automatizatech()`, `obtener_contexto_argos()`, `obtener_workflows_n8n()`, `obtener_workflow_detalle()`, extractores de documentos (Word, Excel, PPT, PDF, DOC legacy, XML), `analizar_imagen_con_vision()`, `calcular_costo()`, `registrar_consumo()`
- **Comando especial:** `[AGENDAR_SEGUIMIENTO]` — El IA puede agendar reuniones autónomamente embebiendo JSON en su respuesta
- **Tablas BD (13):** Lee/escribe `crm_chat_historial`, `ai_usage_log`; lee `automatiza_leads`, `automatiza_propuestas`, `automatiza_propuestas_details`, `automatiza_followup_meetings`, `automatiza_tech_clients`, `automatiza_clients_details`, `automatiza_n8n_errors`, `at_qa_projects`, `at_qa_modules`, `at_qa_cases`, `crm_proyectos`
- **AJAX (18):** 6 con `limpiar_output()` a prioridad 1 + 6 principales (`aria_chat`, `aria_upload`, `aria_tts`, `aria_historial`, `aria_cargar_sesion`, `aria_nueva_sesion`) + 6 MAXTECH (`maxtech_agendar_seguimiento`, `maxtech_buscar_cliente`, `maxtech_obtener_disponibilidad`, `maxtech_obtener_documento`, `maxtech_leer_drive`, `maxtech_listar_drive`)
- **Modelo adaptativo:** GPT-4o para imágenes, GPT-4o-mini para texto
- **Privacidad por rol:** Non-admins no ven datos privados (emails, RUTs, montos)

#### `aria-widget-flotante.php` — Widget Flotante (1,141 líneas)
- **Clase:** `ARIA_Widget_Flotante`
- **Hook:** `admin_footer` → renderiza widget completo (HTML + CSS + JS inline, ~1,100 líneas)
- **JS embebido:** `safeJson()`, `toggleAriaPanel()`, `ariaEnviar()`, `ariaAdjuntar()`, `toggleRecording()`/`startRecording()`/`stopRecording()` (MediaRecorder API), `playAriaAudio()`, `ariaHistorial()`/`cargarSesion()`/`ariaNuevaSesion()`
- **UI:** Botón circular pulsante esquina inferior derecha, panel chat moderno con gradientes, glassmorphism, responsive full-screen en móvil (<767px), safe areas para notch
- **Greeting:** Saludo personalizado por nombre, auto-dismiss 6s, 1 vez por día
- **Tecla Escape:** Cierra el panel

#### `google-drive-integration.php` — Drive Service Account (764 líneas)
- **Clase:** `Google_Drive_Integration`
- **Auth:** JWT con Service Account (`wp-content/uploads/private/google-service-account.json`), token cacheado 55min en transient
- **Métodos públicos (12):** `list_files()`, `search_files()`, `get_file_content()`, `read_and_extract_content()`, `get_client_drive_files()`, `read_drive_file()`, 4 AJAX handlers, `add_admin_menu()` (deshabilitado), `render_admin_page()`
- **Extractores privados (4):** DOCX, XLSX, PPTX, PDF (fallback pdftotext → regex)
- **Helpers globales (3):** `maxtech_read_drive_file()`, `maxtech_list_drive_folder()`, `maxtech_search_drive()`
- **AJAX (4):** `maxtech_drive_list_files`, `maxtech_drive_get_file`⚠️, `maxtech_drive_search`, `maxtech_drive_read_content`
- ⚠️ **Bug conocido:** `maxtech_drive_get_file` → `ajax_get_file` no existe (dead hook)

#### `google-drive-oauth.php` — Drive OAuth (285 líneas)
- **Clase:** `Google_Drive_OAuth`
- **Auth:** OAuth 2.0 con consent flow + auto-refresh 60s antes de expirar
- **OCR:** `convert_pdf_to_text()` — Copia PDF como Google Doc → exporta TXT → borra copia
- **Tokens:** 5 keys en `wp_options` (client_id, client_secret, access_token, refresh_token, token_expires)
- **Scope:** `https://www.googleapis.com/auth/drive` (full — necesario para copy/convert)
- **Menú admin:** Doble registro bajo `crm-automatiza` y `automatiza-crm` (backward compat)
- **AJAX (2):** `maxtech_drive_oauth_start`, `maxtech_drive_oauth_callback`

#### `api-appointments-management.php` — API REST Citas (711 líneas)
- **Clase:** `AutomatizaTech_Appointments_API`
- **9 endpoints REST** bajo `automatiza-tech/v1/`:
  - `GET /appointments` — Listar
  - `GET /appointments/{id}` — Obtener una
  - `GET /appointments/search` — Buscar (case-insensitive con `UPPER(TRIM())`)
  - `GET /appointments/debug` — Debug
  - `POST /appointments` — Crear
  - `PUT /appointments/{id}` — Actualizar completa
  - `PATCH /appointments/{id}` — Actualizar parcial
  - `DELETE /appointments/{id}` — Eliminar (soft/hard)
  - `POST /send-email` — Enviar correo (cancellation, default)
- **Tabla:** `wp_automatiza_leads` (R/W)
- **Cross-validation:** Verifica slots en `automatiza_followup_meetings`
- **BCC:** `automatizacionesbotcore@gmail.com`
- **Permisos:** `__return_true` (público — para N8N)

#### `api-appointments-config.php` — API Disponibilidad (151 líneas)
- **Endpoint:** `GET /wp-json/automatiza-tech/v1/appointments-config`
- **Retorna:** Horarios semanales, feriados, slots ocupados (30 días), timezone `America/Santiago`
- **Cross-table:** Merge slots de `automatiza_leads` + `automatiza_followup_meetings`

---

### 3.2 MÓDULOS INC/ (cargados por functions.php via require_once)

#### Orden de carga (24 módulos activos):
```php
 1. inc/customizer.php                  // 261 líneas
 2. inc/template-functions.php          // 408 líneas
 3. inc/development-config.php          // 224 líneas — SOLO localhost + WP_DEBUG
 4. inc/contact-form.php                // 6,866 líneas ⭐ Más grande
 5. inc/smtp-config.php                 // 208 líneas
 6. inc/contact-shortcode.php           // 1,159 líneas
 7. inc/invoice-settings.php            // 380 líneas
 8. inc/currency-updater.php            // 318 líneas
 9. inc/currency-admin.php              // 403 líneas
10. inc/service-categories-manager.php  // 1,056 líneas
11. inc/services-manager.php            // 2,262 líneas
12. services-frontend.php               // 1,404 líneas — RAÍZ del tema
13. inc/invoice-handlers.php            // 348 líneas
14. inc/receipts-module.php             // 816 líneas
15. inc/client-details-module.php       // 1,796 líneas
16. inc/client-operations-module.php    // 1,514 líneas
17. inc/credentials-vault-module.php    // 2,053 líneas
18. inc/chat-widget.php                 // 182 líneas
19. inc/api-endpoints.php               // 1,772 líneas ⭐ Columna vertebral N8N
20. inc/admin-reminders.php             // 874 líneas
21. inc/admin-leads-manager.php         // 1,349 líneas
22. inc/admin-proposals.php             // 836 líneas
23. inc/admin-followup-meetings.php     // 3,495 líneas
24. inc/client-pdf-report.php           // 422 líneas
25. inc/admin-n8n-errors.php            // 1,167 líneas
26. inc/admin-qa-module.php             // 3,114 líneas
```

#### `contact-form.php` — Módulo Central de Contactos (6,866 líneas) ⭐
- **Clase:** `AutomatizaTechContactForm`
- **Tablas BD (5):** `at_contacts`, `at_clients`, `at_invoices`, `at_quotations`, `at_services_v2` (lectura)
- **Funcionalidades:**
  - Formulario público con validación exhaustiva (nombre, email, teléfono, empresa, RUT, país)
  - Validación de RUT chileno (módulo 11)
  - Anti-spam: rate limiting (max 3/hora por IP) + detección contenido
  - Pipeline de ventas: contacto → interesado → contratado → cliente
  - Generación de cotizaciones PDF (C-AT-YYYYMMDD-XXXX)
  - Generación de facturas PDF con QR verificable
  - Envío de emails transaccionales con BCC
  - Búsqueda/filtrado + Exportación CSV
  - Integración N8N para envío de emails
- **AJAX (20):** `submit_contact_form`+nopriv, `check_phone_exists`+nopriv, `get_contact_details`, `get_client_details`, `search_contacts`, `search_clients`, `filter_contacts`, `send_email_to_new_contacts`, `send_email_to_new_contacts_n8n`, `get_available_plans`+nopriv, `get_nonce`+nopriv

#### `admin-followup-meetings.php` — Reuniones de Seguimiento (3,495 líneas)
- **Tabla BD:** `at_followup_meetings` (20+ columnas)
- **14+ rutas REST** bajo `automatiza-tech/v1/followup-meetings/`
- **Funciones globales:** `automatiza_tech_create_followup_calendar_event()`, `automatiza_tech_delete_google_calendar_event()`, `automatiza_tech_send_followup_email()`, `automatiza_tech_send_followup_whatsapp()`, `automatiza_tech_check_slot_availability()`
- **AJAX (4):** `followup_update_status`, `followup_check_availability`, `followup_send_email`, `followup_delete_meeting`

#### `admin-qa-module.php` — QA Testing (3,114 líneas)
- **Funciones:** Prefijo `at_qa_*`
- **Tablas BD (5):** `at_qa_projects`, `at_qa_modules`, `at_qa_cases`, `at_qa_evidence`, `at_qa_comments`
- **Rol custom:** `qa_tester` con capabilities: `read`, `upload_files`, `at_qa_view`, `at_qa_execute`, `at_qa_comment`
- **Funcionalidades:** Jerarquía Proyectos→Módulos→Casos, estados (pendiente/aprobado/fallido/bloqueado), evidencias con lightbox + descarga masiva, comentarios con edición inline, asignación testers, importación desde Markdown, reportes, notificaciones email
- **AJAX (12):** `at_qa_save_project`, `at_qa_delete_project`, `at_qa_update_status`, `at_qa_update_bug_id`, `at_qa_add_comment`, `at_qa_upload_evidence`, `at_qa_delete_evidence`, `at_qa_update_comment`, `at_qa_delete_comment`, `at_qa_get_case_detail`, `at_qa_assign_module_tester`, `at_qa_generate_report`

#### `api-endpoints.php` — API REST Principal (1,772 líneas) ⭐ Columna vertebral N8N
- **22+ rutas REST** bajo `automatiza-tech/v1/`:
  - `GET/POST /leads` — CRUD de leads
  - `GET /check-availability` — Disponibilidad horario
  - `GET /check-limit` — Límite reservas/día
  - `GET /exchange-rate` — Tipo de cambio USD/CLP
  - `POST /leads/reminders` y `/leads/reminders-wa` — Recordatorios genéricos
  - `POST /leads/reminders-8pm` / `reminders-8am` — Recordatorios horarios email
  - `POST /leads/reminders-8pm-wa` / `reminders-8am-wa` — Recordatorios horarios WhatsApp
  - `POST /leads/action` — Confirmar/cancelar/reagendar (con token)
  - `POST /leads/confirm-attendance` — Confirmar asistencia
  - `GET /leads/check-event` — Verificar evento Calendar
  - `GET /leads/phone-normalize` — Normalizar teléfono
- **Tablas BD:** `at_leads` (R/W), `at_reminder_logs` (R/W)
- **CORS:** Habilitado

#### `credentials-vault-module.php` — Bóveda de Credenciales (2,053 líneas)
- **Clase:** `AutomatizaTech_Credentials_Vault` (Singleton)
- **Tablas BD (2):** `at_credentials_vault`, `at_vault_access_logs`
- **Seguridad:** AES-256-CBC, PBKDF2 desde `AUTH_KEY`, re-auth con password WP, timer sesión, logs acceso
- **16 categorías:** API keys, contraseñas, tokens, certificados, etc.
- **Acceso:** `AutomatizaTech_Credentials_Vault::get_instance()->get_api_key('OpenAI', 'ai')`

#### `services-manager.php` — Servicios v2 (2,262 líneas)
- **Clase:** `AutomatizaTechServicesManager`
- **Tabla BD:** `at_services_v2` (nombre, categoría, USD/CLP, features JSON, icono, orden, status)

#### `client-details-module.php` — Tracking de Clientes (1,796 líneas)
- **Clase:** `AutomatizaTech_Client_Details`
- **Tablas BD (2):** `at_propuesta_details`, `at_client_details`
- **30+ tipos de detalle**, migración automática prospectos→clientes, análisis con OpenAI Vision

#### `client-operations-module.php` — Operaciones de Clientes (1,514 líneas)
- **Clase:** `AutomatizaTech_Client_Operations` (Singleton)
- **Modal tabs:** general, operativo, seguimiento, credenciales
- **Funcionalidades:** Upload logos/branding, notificaciones avance, regenerar facturas

#### `admin-leads-manager.php` — Gestión de Leads (1,349 líneas)
- **Funcionalidades:** Tabla leads con filtros, asistencia/no-show, conversión a cliente, emails/WhatsApp individual/masivo

#### `admin-n8n-errors.php` — ARGOS: Monitoreo N8N (1,167 líneas)
- **3 rutas REST:** `automatiza/v1/n8n-errors` (POST/GET), `search` (POST)
- **Tabla:** `automatiza_n8n_errors`
- **Dashboard:** Tabla con estado, workflow, mensaje — acciones: resolver/ignorar

#### Módulos menores
| Módulo | Líneas | Propósito |
|---|---|---|
| `contact-shortcode.php` | 1,159 | Shortcode `[contact_form]` con validación RUT + 190+ prefijos |
| `service-categories-manager.php` | 1,056 | Categorías servicios + helpers globales |
| `admin-reminders.php` | 874 | Recordatorios manuales con `processNext()` secuencial |
| `admin-proposals.php` | 836 | CRUD propuestas (unique_link_id, system_prompt_text) |
| `receipts-module.php` | 816 | Boletas (IVA 19%, BOL-YYYYMMDD-XXXX) |
| `client-pdf-report.php` | 422 | Reportes PDF por cliente (extiende FPDF) |
| `template-functions.php` | 408 | Helpers: social links, pricing, testimonials, FAQ |
| `currency-admin.php` | 403 | Dashboard tipo cambio con Chart.js |
| `invoice-settings.php` | 380 | Config facturación (razón social, RUT, banco, etc.) |
| `invoice-handlers.php` | 348 | Descarga/validación facturas |
| `currency-updater.php` | 318 | Actualizador USD/CLP (Banco Central + fallback) |
| `customizer.php` | 261 | Opciones: hero, colores, redes sociales (6+), footer |
| `development-config.php` | 224 | Debug bar, interceptor emails, cache busting (solo LOCAL) |
| `smtp-config.php` | 208 | SMTP desde Bóveda → wp_options → constantes |
| `chat-widget.php` | 182 | Widget n8n-chat público con horarios configurables |

---

### 3.3 GENERADORES PDF (lib/)

| Archivo | Líneas | Genera | Tecnología | Estado |
|---|---|---|---|---|
| `invoice-pdf-fpdf.php` | 608 | Facturas con QR (AT-YYYYMMDD-XXXX) | FPDF | ✅ Activo |
| `quotation-pdf-fpdf.php` | 641 | Cotizaciones (C-AT-YYYYMMDD-XXXX) | FPDF | ✅ Activo |
| `receipt-pdf-fpdf.php` | 441 | Boletas (BOL-YYYYMMDD-XXXX) | FPDF | ✅ Activo |
| `invoice-pdf-generator.php` | 506 | Facturas alternativa | TCPDF/mPDF | Fallback |
| `invoice-pdf-generator-simple.php` | 481 | Facturas simple | HTML/wkhtmltopdf | Fallback |
| `mpdf-simple.php` | 109 | Wrapper DomPDF | DomPDF | Fallback |
| `fpdf.php` | — | Librería base | FPDF 1.86 | Core |
| `qrcode.php` | 93 | Códigos QR PNG | SimpleQRCode | ✅ Activo |

> **Nota:** Generadores activos usan conversión UTF-8→Latin1 para caracteres chilenos (ñ, tildes).

---

### 3.4 ARCHIVOS EN RAÍZ DEL PROYECTO

#### Endpoints API (consumidos por N8N)
| Archivo | Líneas | Método | Propósito |
|---|---|---|---|
| `api-chat-history.php` | 179 | POST/GET/DELETE | CRUD historial conversaciones WhatsApp |
| `api-chat-proxy.php` | 99 | POST | Proxy OpenAI centralizado — registra en `ai_usage_log` |
| `api-get-prompt.php` | 104 | GET `?id=` | System prompt de propuesta por `unique_link_id` |
| `api-save-proposal.php` | 43 | POST | N8N envía datos de propuesta → BD |

#### Páginas públicas
| Archivo | Líneas | Acceso | Propósito |
|---|---|---|---|
| `validar-factura.php` | 278 | GET `?codigo=` | Validación pública facturas + descarga PDF |
| `validar-boleta.php` | 169 | GET `?codigo=` | Validación pública boletas + descarga PDF |
| `ver-demo.php` | 477 | GET `?id=` | Demo interactiva de propuesta comercial |
| `ver-presentacion.php` | 44 | GET `?id=` | Presentación por ID |

#### Herramientas admin
| Archivo | Líneas | Propósito |
|---|---|---|
| `tech-assistant.php` | 481 | Asistente multimodal standalone |
| `openai-controller.php` | 342 | Clase `OpenAIController` wrapper OpenAI |
| `admin-ai-dashboard.php` | 149 | Dashboard consumo IA |
| `admin-approve-proposal.php` | 197 | Aprobar/rechazar propuestas |
| `purge-cache.php` | 64 | Purge LiteSpeed (`?format=json` para N8N) |

---

## 4. BASE DE DATOS (~24 tablas custom)

### Grupo CRM (crm-ai-completo.php — dbDelta)
| Tabla | Columnas clave | Propósito |
|---|---|---|
| `crm_clientes` | tipo, estado, nombre, email, teléfono, empresa, rubro, origen, ai_identifier, logo_url, colores, tipografía, drive_folder_id | Clientes y prospectos unificados |
| `crm_historial` | cliente_id, tipo_evento, titulo, descripcion, metadata (JSON), usuario_id | Timeline de eventos |
| `crm_proyectos` | cliente_id, nombre, estado, tipo_servicio, precio_acordado, moneda, repositorio_url, credenciales | Proyectos |
| `crm_chat_historial` | session_id, user_id, role, content, archivos (JSON), audio_url, tokens_used | Chat MAXTECH |
| `ai_usage_log` | user_id, model, tokens_input, tokens_output, cost, endpoint, client_name | Consumo OpenAI |

### Grupo Contactos/Clientes (contact-form.php)
| Tabla | Propósito |
|---|---|
| `at_contacts` | Contactos captados (formulario web) — 25+ columnas incl. spam_score |
| `at_clients` | Clientes contratados — 30+ columnas incl. factura_*, cotizacion_* |
| `at_invoices` | Facturas generadas (numero, monto, pdf_path, qr_code) |
| `at_quotations` | Cotizaciones generadas (items JSON, pdf_path) |

### Grupo Leads/Citas
| Tabla | Propósito |
|---|---|
| `automatiza_leads` | Leads con citas (google_event_id, meet_link, 10+ flags recordatorio) |
| `at_reminder_logs` | Logs de recordatorios enviados |

### Grupo Seguimiento
| Tabla | Propósito |
|---|---|
| `automatiza_followup_meetings` | Reuniones post-venta (calendar_event_id, meet_link, status) |
| `automatiza_propuestas_details` | Tracking avances prospectos |
| `automatiza_clients_details` | Tracking avances clientes |
| `automatiza_propuestas` | Propuestas comerciales (unique_link_id, system_prompt_text) |

### Grupo Servicios/Facturación
| Tabla | Propósito |
|---|---|
| `at_services_v2` | Catálogo servicios (USD/CLP, features JSON) |
| `at_service_categories` | Categorías de servicios |
| `at_receipts` | Boletas generadas |

### Grupo Seguridad
| Tabla | Propósito |
|---|---|
| `at_credentials_vault` | Credenciales encriptadas AES-256-CBC |
| `at_vault_access_logs` | Logs de acceso a bóveda |

### Grupo QA
| Tabla | Propósito |
|---|---|
| `at_qa_projects` | Proyectos QA |
| `at_qa_modules` | Módulos por proyecto |
| `at_qa_cases` | Casos de prueba |
| `at_qa_evidence` | Evidencias (screenshots) |
| `at_qa_comments` | Comentarios en casos |

### Grupo Monitoreo
| Tabla | Propósito |
|---|---|
| `automatiza_n8n_errors` | Errores de workflows N8N |

> **Nota sobre prefijos:** Tablas usan `at_`, `automatiza_` o `crm_`. Todas llevan `wp_` delante.

---

## 5. INTEGRACIONES EXTERNAS

### 5.1 OpenAI
| Servicio | Modelo | Archivos |
|---|---|---|
| Chat | GPT-4o (principal), GPT-4o-mini (texto) | aria-agente-core, crm-ai-completo, api-chat-proxy |
| Vision | GPT-4o-mini | aria-agente-core |
| TTS | tts-1 (voz: `nova`) | aria-agente-core, crm-ai-completo |
| Whisper | whisper-1 | crm-ai-completo |
- **API Key:** Bóveda → fallback `OPENAI_API_KEY` en wp-config
- **Proxy centralizado:** `api-chat-proxy.php` para N8N, PetsGo y clientes

### 5.2 N8N
- **URL:** `https://n8n-n8n.kchiba.easypanel.host`
- **Workflows PROD (~40):** WhatsApp_Tech_Principal (277 KB), Agendamiento, Recordatorios (1h/24h/72h/8AM/8PM), Calendar Subworkflow, Followup Scheduler, Reschedule Handler
- **Comunicación:** N8N→WP via REST API + endpoints raíz; WP→N8N via API workflows + webhooks

### 5.3 Google Drive
| Tipo | Archivo | Auth | Uso especial |
|---|---|---|---|
| Service Account | google-drive-integration.php | JWT | Carpetas compartidas |
| OAuth 2.0 | google-drive-oauth.php | Consent flow | **OCR de PDFs** |
- **RAG en MAXTECH:** Accede a docs de clientes vía `drive_folder_id`

### 5.4 Google Calendar
- Crear/eliminar eventos, Meet links automáticos, sincronización via N8N

### 5.5 WhatsApp (via N8N + Meta Business API)
- Redis para estado conversación, 10 endpoints de recordatorio

### 5.6 SMTP (`smtp.hostinger.com:587 TLS`)
- Credenciales desde Bóveda → wp_options → constantes
- BCC global: `automatizacionesbotcore@gmail.com`
- 12+ tipos de email (bienvenida, facturas, cotizaciones, boletas, recordatorios, QA, avances)
- Interceptor LOCAL: guarda emails en carpeta local

### 5.7 Banco Central de Chile
- `si3.bcentral.cl` (primario), `exchangerate-api.com` (fallback)
- WP-Cron diario para USD→CLP

---

## 6. MENÚS ADMIN

| Menú | Módulo |
|---|---|
| CRM AutomatizaTech (+ Dashboard IA, Clientes, Búsqueda, Agente MAXTECH) | crm-ai-completo.php |
| Contactos (+ Clientes) | contact-form.php |
| Servicios AT (+ Categorías, Tipo Cambio) | services-manager.php |
| Propuestas | admin-proposals.php |
| Leads / Citas | admin-leads-manager.php |
| Recordatorios | admin-reminders.php |
| Reuniones Seguimiento | admin-followup-meetings.php |
| Config. Facturación | invoice-settings.php |
| Boletas | receipts-module.php |
| Chat IA (N8N público) | chat-widget.php |
| Config MAXTECH/N8N | aria-agente-core.php |
| Google Drive OAuth | google-drive-oauth.php (submenú CRM) |
| ARGOS – Monitoreo N8N | admin-n8n-errors.php |
| QA Testing (+ Importar MD) | admin-qa-module.php |
| Bóveda de Credenciales | credentials-vault-module.php |

---

## 7. SHORTCODES

| Shortcode | Módulo | Función |
|---|---|---|
| `[contact_form]` | inc/contact-shortcode.php | Formulario contacto completo |
| `[pricing_services]` | services-frontend.php | Planes y precios multi-moneda |
| `[exchange_rate]` | services-frontend.php | Widget tipo cambio USD/CLP |

---

## 8. PATRONES TÉCNICOS

- **Singleton:** `Credentials_Vault`, `Client_Operations`
- **safeJson(text):** JS que elimina HTML Xdebug antes de `JSON.parse`
- **limpiar_output():** `ob_end_clean()` a prioridad 1 antes de AJAX
- **AJAX:** `jQuery.ajax` con `dataType:'text'` + `safeJson()` (NUNCA `dataType:'json'`)
- **Tablas:** `$wpdb->prefix` + nombre via `dbDelta()`
- **Encriptación:** AES-256-CBC + PBKDF2 desde `AUTH_KEY`
- **Tokens públicos:** HMAC para portales clientes/prospectos
- **PDFs:** FPDF con `utf8_decode()`/`iconv()` para caracteres latinos
- **Cache REST:** `functions.php` envía no-cache + DONOTCACHEPAGE para `/wp-json/`
- **BCC global:** Emails con copia a `automatizacionesbotcore@gmail.com`
- **Rol QA:** `qa_tester` con 5 capabilities custom
- **WP-Cron:** 2 jobs diarios (tipo cambio + precios servicios)

### Prefijos
| Prefijo | Contexto |
|---|---|
| `automatiza_tech_*` | Funciones tema + helpers seguimiento |
| `at_qa_*` | Módulo QA |
| `maxtech_*` | Drive helpers + agente IA |

### Config WP
| Setting | PROD | LOCAL |
|---|---|---|
| WP_DEBUG | false | true |
| WP_DEBUG_DISPLAY | false | false |
| LiteSpeed | Activo | No |
| Xdebug | No | Sí |

---

## 9. BUGS CONOCIDOS

| Issue | Archivo | Descripción |
|---|---|---|
| Dead hook | crm-ai-completo.php | `crm_buscar` → `ajax_buscar()` no existe |
| Dead hook | google-drive-integration.php | `drive_get_file` → `ajax_get_file()` no existe |
| REST sin auth | api-appointments-*.php | 10 endpoints públicos sin autenticación |
| Duplicación Drive | 2 mu-plugins | Service Account + OAuth proveen acceso simultáneo |
| Dos contact-form | raíz tema + inc/ | Potencial conflicto si ambos se cargan |

---

## 10. PROCEDIMIENTO DE ACTUALIZACIÓN

### Al subir cambios a PROD:
1. Hacer cambios en rama `prod-sync-2025-06-26`
2. Probar localmente (verificar banner "AMBIENTE LOCAL")
3. Commitear con mensaje descriptivo
4. Subir archivos a Hostinger manualmente
5. Ejecutar `purge-cache.php?format=json`
6. **ACTUALIZAR ESTE DOCUMENTO** con los cambios
7. Commitear la actualización del documento

### Archivos que NUNCA se suben a PROD desde git:
- `wp-config.php`, `.htaccess`
- `wp-content/uploads/`
- Scripts `debug-*.php`, `test-*.php`, `check-*.php`, `fix-*.php`
- Directorios `tools/`, `sql/`

---

## 11. NÚMEROS TOTALES (Feb 2026)

| Métrica | Valor |
|---|---|
| Archivos PHP activos | 60+ |
| Líneas de código total | ~55,000+ |
| MU-Plugins | 7 (12,735 líneas) |
| Módulos inc/ | 24 activos (~30,000 líneas) |
| Tablas custom BD | 24+ |
| REST API endpoints | 55+ |
| AJAX hooks | 63+ |
| AJAX nopriv (públicos) | 8+ |
| Menús admin | 16 top-level |
| Workflows N8N | ~40 |
| Clases PHP | 16+ |
| Shortcodes | 3 |
| PDF generators activos | 3 |

---

*Documento generado por análisis exhaustivo del repositorio `lmgmuber-bit/automatiza-tech`, rama `prod-sync-2025-06-26`. Última actualización: 28 Feb 2026.*
