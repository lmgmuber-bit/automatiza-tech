# ANÁLISIS COMPLETO — Módulos `inc/` AutomatizaTech Theme

> Generado: 2026-02-28  
> Directorio: `wp-content/themes/automatiza-tech/inc/`  
> Total archivos PHP: **30**  
> Total líneas de código: **~36,067**

---

## INVENTARIO GENERAL

| # | Archivo | Líneas | Bytes | Clase | Tipo |
|---|---------|--------|-------|-------|------|
| 1 | contact-form.php | 6,866 | 339,502 | `AutomatizaTechContactForm` | CRM central |
| 2 | admin-followup-meetings.php | 3,495 | 164,831 | — (funcional) | Seguimiento reuniones |
| 3 | admin-followup-meetings2.php | 3,050 | 140,246 | — (funcional) | Backup/versión anterior |
| 4 | admin-qa-module.php | 3,114 | 179,926 | — (funcional) | QA / Testing |
| 5 | services-manager.php | 2,267 | 92,890 | `AutomatizaTechServicesManager` | Admin servicios |
| 6 | credentials-vault-module.php | 2,053 | 73,801 | `AutomatizaTech_Credentials_Vault` | Bóveda segura |
| 7 | client-details-module.php | 1,796 | 72,882 | `AutomatizaTech_Client_Details` | Detalles clientes |
| 8 | api-endpoints.php | 1,772 | 73,021 | — (funcional) | REST API leads |
| 9 | client-operations-module.php | 1,514 | 71,163 | `AutomatizaTech_Client_Operations` | Info operativa |
| 10 | admin-leads-manager.php | 1,374 | 69,313 | — (funcional) | Admin leads |
| 11 | admin-n8n-errors.php | 1,177 | 44,350 | — (funcional) | ARGOS errores |
| 12 | contact-shortcode.php | 1,161 | 50,290 | — (shortcode) | Frontend form |
| 13 | services-manager-clean.php | 1,151 | 45,390 | `AutomatizaTechServicesManager` | Backup limpio |
| 14 | service-categories-manager.php | 1,068 | 41,188 | `AutomatizaTechServiceCategoriesManager` | Categorías |
| 15 | admin-reminders.php | 897 | 43,992 | — (funcional) | Recordatorios |
| 16 | admin-proposals.php | 835 | 45,887 | — (funcional) | Propuestas demo |
| 17 | receipts-module.php | 827 | 34,097 | `AutomatizaTechReceipts` | Boletas |
| 18 | services-frontend.php | 604 | 31,397 | — (funcional) | Render frontend |
| 19 | ver-demo.php | 523 | 17,123 | — (standalone) | Demo propuesta |
| 20 | client-pdf-report.php | 449 | 18,525 | `AutomatizaTech_Client_Report_PDF`, `AutomatizaTech_Client_Report_Generator` | PDF reportes |
| 21 | template-functions.php | 421 | 14,972 | — (funcional) | Helpers tema |
| 22 | currency-admin.php | 406 | 18,071 | `AutomatizaTech_Currency_Admin` | Admin moneda |
| 23 | invoice-settings.php | 405 | 16,132 | — (funcional) | Config facturas |
| 24 | invoice-handlers.php | 358 | 11,962 | — (funcional) | Descarga/validación |
| 25 | invoice-handlers_OLD.php | 357 | 11,935 | — (funcional) | Backup anterior |
| 26 | currency-updater.php | 322 | 11,815 | `AutomatizaTech_Currency_Updater` | Conversión CLP |
| 27 | customizer.php | 265 | 8,635 | — (funcional) | WP Customizer |
| 28 | development-config.php | 231 | 8,170 | — (funcional) | Dev / debug |
| 29 | smtp-config.php | 218 | 7,850 | — (funcional) | Correo SMTP |
| 30 | chat-widget.php | 199 | 7,150 | — (funcional) | Widget chat |

---

## 1. `contact-form.php` — 6,866 líneas (MAYOR)

### Clase: `AutomatizaTechContactForm` (L7)

### Tablas DB
| Tabla | Uso |
|-------|-----|
| `automatiza_tech_contacts` | Contactos del formulario |
| `automatiza_tech_clients` | Clientes contratados |
| `automatiza_propuestas` | Propuestas/demos |
| `automatiza_tech_invoices` | Facturas generadas |
| `automatiza_tech_quotations` | Cotizaciones generadas |

### AJAX Hooks (20 registros)
| Hook | Priv | NoPriv | Método |
|------|------|--------|--------|
| `submit_contact_form` | ✅ | ✅ | `handle_form_submission()` |
| `check_phone_exists` | ✅ | ✅ | `check_phone_exists()` |
| `get_contact_details` | ✅ | ✅ | `get_contact_details()` |
| `get_client_details` | ✅ | ✅ | `get_client_details()` |
| `search_contacts` | ✅ | ✅ | `search_contacts()` |
| `search_clients` | ✅ | ✅ | `search_clients()` |
| `filter_contacts` | ✅ | — | `filter_contacts()` |
| `send_email_to_new_contacts` | ✅ | — | `send_email_to_new_contacts()` |
| `send_email_to_new_contacts_n8n` | ✅ | ✅ | `send_email_to_new_contacts_n8n()` |
| `get_available_plans` | ✅ | — | `get_available_plans()` |
| `get_nonce` | ✅ | ✅ | `get_nonce()` |

### REST API: Ninguna (todo por AJAX)

### add_action / add_filter
| Hook | Callback | Línea |
|------|----------|-------|
| `admin_menu` | `add_admin_menu()` | L38 |
| `admin_enqueue_scripts` | `admin_scripts()` | L39 |
| `wp_enqueue_scripts` | `frontend_scripts()` | L40 |
| `admin_init` | `handle_export_action()` | L41 |
| `init` | `check_table_structure()` | L47 |
| `phpmailer_init` | (inline) — versión texto plano | L1452 |
| `admin_head` | (inline) — CSS custom | L6679 |

### Métodos PHP Públicos (23 métodos)
- `__construct()`, `get_nonce()`, `check_table_structure()`, `create_table()`
- `handle_form_submission()` — procesamiento central del formulario
- `add_admin_menu()`, `frontend_scripts()`, `admin_scripts()`
- `send_invoice_email_to_client()` — envío de factura PDF por correo
- `send_email_to_new_contacts_n8n()` — endpoint N8N para emails masivos
- `check_phone_exists()`, `get_contact_details()`, `get_client_details()`
- `get_available_plans()`, `download_invoice()`
- `search_contacts()`, `search_clients()`, `filter_contacts()`
- `send_email_to_new_contacts()`, `handle_export_action()`
- `admin_page()` — página admin contactos (~1800 líneas de HTML/JS inline)
- `clients_page()` — página admin clientes (~1000 líneas de HTML/JS inline)
- `export_to_csv()`

### Funcionalidad Clave
- **CRM Central**: gestión completa de contactos y clientes
- **Formulario de contacto**: recepción, validación multicapa, sanitización
- **Sistema de estados**: new → contacted → interested → contracted
- **Cotizaciones**: generación automática de cotizaciones PDF con validez 3 días, envío por email, guardado en BD
- **Facturas**: generación de factura PDF con QR, envío por correo con adjunto, guardado en BD
- **Integración N8N**: endpoint para disparar emails masivos a contactos nuevos con token seguro
- **Admin contactos**: tabla paginada, búsqueda, filtros, edición en modal, cambio de estados, eliminación masiva
- **Admin clientes**: tabla con info operativa, regeneración de facturas QR, edición modal
- **Exportación CSV**: descarga de contactos filtrados
- **Anti-spam**: headers profesionales, versión texto plano alternativa, DKIM friendly

---

## 2. `admin-followup-meetings.php` — 3,495 líneas

### Tablas DB
| Tabla | Uso |
|-------|-----|
| `automatiza_followup_meetings` | Reuniones de seguimiento |
| `automatiza_leads` | Leads (JOIN) |
| `automatiza_propuestas` | Propuestas (JOIN) |
| `crm_clientes` | Clientes CRM (verificación email) |

### AJAX Hooks (4)
| Hook | Callback |
|------|----------|
| `followup_update_status` | `automatiza_tech_followup_update_status()` |
| `followup_check_availability` | `automatiza_tech_followup_check_availability_ajax()` |
| `followup_send_email` | `automatiza_tech_followup_send_email_ajax()` |
| `followup_create_calendar_event` | `automatiza_tech_create_calendar_event_ajax()` |

### REST API (14 rutas — namespace `automatiza-tech/v1`)
| Ruta | Método | Función |
|------|--------|---------|
| `/followup-meetings/{id}` | GET | `automatiza_tech_get_followup_meeting()` |
| `/followup-meetings/{id}/update-meet` | POST | `automatiza_tech_update_followup_meet_link()` |
| `/followup-meetings/{id}/mark-whatsapp-sent` | POST | `automatiza_tech_mark_followup_whatsapp_sent()` |
| `/followup-meetings/check-event` | POST | `automatiza_tech_check_event_exists()` |
| `/followup-meetings/{id}/confirm` | POST | `automatiza_tech_confirm_followup_attendance()` |
| `/followup-meetings/{id}/cancel` | POST | `automatiza_tech_cancel_followup_meeting()` |
| `/followup-meetings/search` | GET | `automatiza_tech_search_followup_meeting()` |
| `/followup-meetings` | POST | `automatiza_tech_create_followup_meeting_api()` |
| `/verify-client` | POST | `automatiza_tech_verify_client_by_phone()` |
| `/followup-meetings/email-action/confirm/{id}` | GET | `automatiza_tech_followup_email_confirm()` |
| `/followup-meetings/email-action/reschedule/{id}` | GET | `automatiza_tech_followup_email_reschedule()` |
| `/followup-meetings/email-action/cancel/{id}` | GET | `automatiza_tech_followup_email_cancel()` |
| `/followup-meetings/reschedule` | POST | `automatiza_tech_followup_reschedule_api()` |

### Hooks
| Hook | Callback |
|------|----------|
| `after_switch_theme` | `automatiza_tech_followup_create_table()` |
| `init` | inline — table creation check |
| `admin_menu` | `automatiza_tech_followup_menu()` |
| `rest_api_init` | `automatiza_tech_register_followup_api_routes()` |

### Funciones Clave (27 funciones)
- `automatiza_tech_followup_create_table()` — DDL tabla con columnas: google_event_id, meet_link, whatsapp_reminder_sent, daily_reminder_8pm/8am
- `automatiza_tech_check_slot_availability()` — verificación disponibilidad de horarios
- `automatiza_tech_followup_page()` — Admin UI (~1400 líneas con HTML/JS inline)
- `automatiza_tech_send_followup_email()` — envío de correo HTML profesional de reunión
- `automatiza_tech_create_followup_calendar_event()` — creación evento Google Calendar (delegado a N8N)
- `automatiza_tech_send_followup_whatsapp()` — envío WhatsApp vía webhook N8N
- `automatiza_tech_verify_client_by_phone()` — verificación de cliente por teléfono
- `automatiza_tech_create_followup_meeting_api()` — crear reunión desde API
- Email actions: confirm, cancel, reschedule — acciones desde links de email
- `automatiza_tech_call_followup_reschedule_workflow()` — delegar re-agendamiento a N8N
- `automatiza_tech_render_followup_action_page()` — página HTML de resultado de acción

### Funcionalidad Clave
- **Gestión de reuniones de seguimiento** para clientes que ya fueron contactados
- **Integración Google Calendar** mediante N8N (evento + Meet link)
- **Integración WhatsApp** vía webhooks N8N
- **Email actions** con links de confirmación/reagendamiento/cancelación
- **Verificación de disponibilidad** de horarios
- **Panel admin** con lista de reuniones, filtros, acciones rápidas

---

## 3. `admin-followup-meetings2.php` — 3,050 líneas

**⚠️ VERSIÓN BACKUP** — Misma estructura que `admin-followup-meetings.php` con diferencias menores:
- Menos rutas REST (no incluye `mark-whatsapp-sent`)
- Funciones ligeramente más cortas
- Misma arquitectura y tablas

---

## 4. `admin-qa-module.php` — 3,114 líneas

### Versión: AT_QA_VERSION `1.2.0`

### Tablas DB (5 + 4 legacy)
| Tabla | Uso |
|-------|-----|
| `at_qa_projects` | Proyectos QA |
| `at_qa_modules` | Módulos (suites de test) |
| `at_qa_cases` | Casos de prueba |
| `at_qa_evidence` | Evidencias (screenshots, archivos) |
| `at_qa_comments` | Comentarios en casos |
| `crm_clientes` | Clientes CRM (para selects) |
| `qa_petsgo_modules` | Legacy — migración |
| `qa_petsgo_cases` | Legacy — migración |
| `qa_petsgo_evidence` | Legacy — migración |
| `qa_petsgo_comments` | Legacy — migración |

### AJAX Hooks (12)
| Hook | Descripción |
|------|-------------|
| `at_qa_save_project` | Crear/editar proyecto QA |
| `at_qa_delete_project` | Eliminar proyecto |
| `at_qa_update_status` | Actualizar estado de caso (pass/fail/blocked/skip) |
| `at_qa_update_bug_id` | Asignar ID de bug a caso |
| `at_qa_add_comment` | Agregar comentario |
| `at_qa_upload_evidence` | Subir screenshot/evidencia |
| `at_qa_delete_evidence` | Eliminar evidencia |
| `at_qa_update_comment` | Editar comentario |
| `at_qa_delete_comment` | Eliminar comentario |
| `at_qa_get_case_detail` | Obtener detalle de caso |
| `at_qa_assign_module_tester` | Asignar tester a módulo |
| `at_qa_generate_report` | Generar reporte QA |

### REST API: Ninguna

### Hooks
| Hook | Callback |
|------|----------|
| `admin_init` | `at_qa_setup_tables()` — creación tablas |
| `admin_init` | `at_qa_setup_role()` — rol `qa_tester` |
| `admin_menu` | `at_qa_admin_menu()` |

### Funciones Clave (13 servidor + JS inline)
- `at_qa_table_names()` — helper nombres de tablas
- `at_qa_send_notification()` — email HTML profesional con template AT
- `at_qa_get_context()` — obtener contexto completo de un caso
- `at_qa_setup_tables()` — creación/migración de tablas con datos legacy PetsGo
- `at_qa_setup_role()` — crear rol `qa_tester` con capabilities
- `at_qa_router()` — router de páginas (projects, suite, import)
- `at_qa_parse_md_file()` / `at_qa_import_project_from_md()` — importar suites desde archivos Markdown
- `at_qa_detect_md_files()` — detectar archivos MD para importación
- `at_qa_shared_styles()` — CSS compartido (badges, layout)
- `at_qa_render_projects_page()` — página con proyectos, stats, modal crear/editar
- `at_qa_render_suite_page()` — página detalle de suite con lightbox, drag-drop evidencias
- `at_qa_render_import_page()` — importación desde MD

### Funcionalidad Clave
- **Sistema QA completo**: Proyecto → Módulos → Casos → Evidencias + Comentarios
- **Importación from Markdown**: parsea archivos .md con estructura de casos de prueba
- **Galería de evidencias**: lightbox con navegación, upload drag-and-drop
- **Notificaciones por email**: cuando se asigna tester, se reporta bug, etc.
- **Rol QA Tester**: custom WordPress role con permisos limitados
- **Migración datos legacy**: importa desde tablas `qa_petsgo_*`
- **Generación de reportes**: resumen estadístico por proyecto/módulo

---

## 5. `api-endpoints.php` — 1,772 líneas

### Tablas DB
| Tabla | Uso |
|-------|-----|
| `automatiza_leads` | Leads (principal) |
| `automatiza_leads_logs` | Logs de acciones sobre leads |
| `automatiza_followup_meetings` | Reuniones de seguimiento (JOIN) |

### REST API (22 rutas — namespace `automatiza-tech/v1`)
| Ruta | Método | Función |
|------|--------|---------|
| `/exchange-rate` | GET | `automatiza_tech_get_exchange_rate()` |
| `/leads` | POST | `automatiza_tech_save_lead()` |
| `/check-availability` | POST | `automatiza_tech_check_availability()` |
| `/check-limit` | POST | `automatiza_tech_check_booking_limit()` |
| `/leads/reminders(/{type})` | GET | `automatiza_tech_get_leads_for_reminders()` |
| `/leads/reminders-wa(/{type})` | GET | `automatiza_tech_get_leads_for_reminders_wa()` |
| `/leads/reminders-8pm` | GET | `automatiza_tech_get_leads_reminder_8pm()` |
| `/leads/reminders-8am` | GET | `automatiza_tech_get_leads_reminder_8am()` |
| `/leads/reminders-wa-8pm` | GET | `automatiza_tech_get_leads_reminder_8pm_wa()` |
| `/leads/reminders-wa-8am` | GET | `automatiza_tech_get_leads_reminder_8am_wa()` |
| `/leads/update-reminder-daily/{id}/{type}` | POST | `automatiza_tech_mark_reminder_daily_sent()` |
| `/leads/update-reminder-wa-daily/{id}/{type}` | POST | `automatiza_tech_mark_reminder_daily_sent_wa()` |
| `/leads/update-reminder/{id}/{type}` | POST | `automatiza_tech_mark_reminder_sent()` |
| `/leads/update-reminder-wa/{id}/{type}` | POST | `automatiza_tech_mark_reminder_sent_wa()` |
| `/leads/update-reminder` | POST | `automatiza_tech_mark_reminder_sent()` (legacy) |
| `/leads/action` | POST | `automatiza_tech_handle_lead_action()` |
| `/leads/reschedule` | POST | `automatiza_tech_reschedule_lead()` |
| `/leads/confirm-attendance/{id}/{type}` | POST | `automatiza_tech_confirm_attendance()` |
| `/leads/check-event` | POST | `automatiza_tech_check_lead_event_exists()` |
| `/leads/{id}/mark-whatsapp-sent` | POST | `automatiza_tech_mark_lead_whatsapp_sent()` |

### AJAX Hooks: Ninguno

### Hooks
| Hook | Callback |
|------|----------|
| `rest_post_dispatch` | Filtro CORS headers |
| `rest_api_init` | Registra todas las rutas |
| `after_switch_theme` | `automatiza_tech_create_leads_table()` |
| `init` | Creación tabla leads |

### Funciones Clave (20)
- `automatiza_tech_normalize_phone()` — normalización teléfonos Chile (+56)
- `automatiza_tech_delete_google_calendar_event()` — borrar evento Google via N8N
- `automatiza_tech_save_lead()` — crear lead con detección de duplicados por teléfono
- `automatiza_tech_get_exchange_rate()` — tasa USD→CLP desde mindicador.cl
- `automatiza_tech_check_availability()` — verificar disponibilidad de citas
- `automatiza_tech_check_booking_limit()` — límite de citas por día
- Recordatorios email: funciones para obtener leads pendientes de recordatorio (1h, 24h, 8pm, 8am)
- Recordatorios WhatsApp: mismas funciones paralelas para canal WA
- `automatiza_tech_handle_lead_action()` — acción desde email (confirmar/cancelar/reagendar)
- `automatiza_tech_reschedule_lead()` — reagendamiento

### Funcionalidad Clave
- **API central para N8N**: todos los endpoints que consume la automatización
- **Sistema de recordatorios multinivel**: email + WhatsApp, 1h antes + 24h antes + 8pm/8am diarios
- **CORS habilitado** para acceso desde flujos N8N
- **Normalización de teléfonos** con prefijo chileno
- **Gestión de eventos Google Calendar** via N8N
- **Detección de duplicados** por teléfono normalizado

---

## 6. `credentials-vault-module.php` — 2,053 líneas

### Clase: `AutomatizaTech_Credentials_Vault` (singleton, L23)

### Tablas DB
| Tabla | Uso |
|-------|-----|
| `automatiza_credentials_vault` | Credenciales encriptadas |
| `automatiza_credentials_logs` | Logs de acceso |

### AJAX Hooks (6)
| Hook | Método |
|------|--------|
| `vault_get_credentials` | `ajax_get_credentials()` |
| `vault_save_credential` | `ajax_save_credential()` |
| `vault_delete_credential` | `ajax_delete_credential()` |
| `vault_reveal_password` | `ajax_reveal_password()` |
| `vault_verify_admin` | `ajax_verify_admin()` |
| `vault_get_logs` | `ajax_get_logs()` |

### Hooks
| Hook | Callback |
|------|----------|
| `init` | `create_tables()` |
| `admin_menu` | `add_admin_menu()` |

### Métodos Públicos (10)
- `get_instance()` — singleton
- `encrypt_password()` — AES-256-CBC encriptación
- `decrypt_password()` — desencriptación
- `create_tables()` — DDL tablas vault + logs
- `add_admin_menu()` — menú admin
- `ajax_verify_admin()` — verificación password admin
- `ajax_get_credentials()` — listar credenciales (passwords ocultas)
- `ajax_save_credential()` — guardar con encriptación
- `ajax_delete_credential()` — eliminar
- `ajax_reveal_password()` — revelar (requiere verificación admin previa)
- `ajax_get_logs()` — historial de accesos
- `render_admin_page()` — UI completa (~1200 líneas HTML/CSS/JS inline)

### Funcionalidad Clave
- **Bóveda de credenciales** con encriptación AES-256-CBC
- **16 categorías**: server, domain, hosting, FTP, database, email, N8N, API, social, payment, analytics, AI, WhatsApp, Google, WordPress, other
- **Derivación de clave** PBKDF2 desde AUTH_KEY de WordPress
- **Sesión de seguridad** con timer para auto-bloqueo
- **Logs de acceso** a credenciales sensibles
- **Búsqueda y filtrado** por categoría, entorno (producción/staging/dev)
- **UI con modal** para crear/editar, revelación de passwords

---

## 7. `client-details-module.php` — 1,796 líneas

### Clase: `AutomatizaTech_Client_Details` (L15)

### Tablas DB
| Tabla | Uso |
|-------|-----|
| `automatiza_propuestas_details` | Detalles de prospecto |
| `automatiza_clients_details` | Detalles de cliente |
| `automatiza_propuestas` | Propuestas (referencia) |
| `automatiza_tech_clients` | Clientes (referencia) |

### AJAX Hooks (6)
| Hook | Método |
|------|--------|
| `get_prospect_tracking_details` | `ajax_get_prospect_details()` |
| `save_prospect_tracking_detail` | `ajax_save_prospect_detail()` |
| `get_client_tracking_details` | `ajax_get_client_tracking_details()` |
| `save_client_tracking_detail` | `ajax_save_client_detail()` |
| `get_tracking_detail_history` | `ajax_get_detail_history()` |
| `delete_tracking_detail` | `ajax_delete_detail()` |

### Hooks
| Hook | Callback |
|------|----------|
| `admin_init` | `create_tables()` |
| `automatiza_client_contracted` | `migrate_details_to_client()` |

### Métodos Públicos (17)
- `create_tables()` — crea tablas propuestas_details y clients_details
- `get_detail_types()` — 30+ tipos de detalle (reunión, llamada, email, WhatsApp, propuesta, cotización, pago, acuerdo, etc.)
- `get_statuses()` — estados de seguimiento
- `add_prospect_detail()` / `add_client_detail()` — insertar detalle
- `get_prospect_details()` / `get_client_details()` — listar
- `migrate_details_to_client()` — migrar de prospecto a cliente en contratación
- `get_prospect_summary()` / `get_client_summary()` — resumen con conteos
- `update_client_detail()` — actualizar detalle
- `ajax_*` — handlers AJAX
- `render_prospect_details_widget()` / `render_client_details_widget()` — widgets reutilizables
- `render_styles_and_scripts()` — CSS/JS inline (~600 líneas) con modales, timeline

### Funcionalidad Clave
- **Tracking de interacciones** con prospectos y clientes
- **30+ tipos de detalle**: reunión presencial, videollamada, llamada, email, WhatsApp, propuesta, cotización, contrato, pago, soporte técnico, milestone, deploy, capacitación, etc.
- **Timeline visual** de interacciones
- **Migración automática** de detalles cuando prospecto se convierte en cliente
- **Adjuntos/archivos** en detalles
- **Funciones helper globales**: `automatiza_render_prospect_details()`, `automatiza_render_client_details()`

---

## 8. `client-operations-module.php` — 1,514 líneas

### Clase: `AutomatizaTech_Client_Operations` (singleton, L18)

### Tablas DB
| Tabla | Uso |
|-------|-----|
| `automatiza_tech_clients` | Clientes (principal) |
| `automatiza_clients_details` | Detalles (JOIN) |
| `automatiza_services` | Servicios (para select) |

### AJAX Hooks (5)
| Hook | Método |
|------|--------|
| `get_client_operational_data` | `ajax_get_operational_data()` |
| `save_client_operational_data` | `ajax_save_operational_data()` |
| `get_client_full_details` | `ajax_get_full_details()` |
| `notify_project_progress` | `ajax_notify_project_progress()` |
| `regenerate_and_resend_invoice_op` | `ajax_regenerate_and_resend_invoice()` |

### Hooks
| Hook | Callback |
|------|----------|
| `automatiza_tech_clients_actions` (filter) | `add_regenerate_button()` |
| `admin_footer-contactos_page_automatiza-tech-clients` | `add_regenerate_script()` |
| `admin_footer` | `render_styles()` + `render_scripts()` |

### Métodos Públicos (10)
- `get_instance()` — singleton
- `add_regenerate_button()` — botón regenerar factura en listado clientes
- `ajax_get_operational_data()` — datos operativos: URLs, redes, hosting, etc.
- `ajax_save_operational_data()` — guardar datos operativos en campos JSON
- `ajax_get_full_details()` — ficha completa del cliente
- `ajax_notify_project_progress()` — enviar email de progreso al cliente
- `render_full_details_modal()` — modal completo (~460 líneas) con tabs
- `render_styles()` / `render_scripts()` — CSS/JS estáticos
- `ajax_regenerate_and_resend_invoice()` — regenera factura PDF con QR y re-envía

### Funcionalidad Clave
- **Ficha operativa completa** del cliente en modal con pestañas
- **Datos operativos**: URLs de app, redes sociales, dominios, hosting, correos, accesos, integraciones
- **Notificación de progreso**: email profesional HTML al cliente con avance del proyecto
- **Regeneración de facturas**: genera nuevo PDF y re-envía por correo
- **Tabs en modal**: Info General, Datos Operativos, Detalles de Seguimiento, Acciones

---

## 9. `admin-leads-manager.php` — 1,374 líneas

### Tablas DB
| Tabla | Uso |
|-------|-----|
| `automatiza_leads` | Leads (principal) |
| `automatiza_leads_logs` | Logs |
| `automatiza_followup_meetings` | Reuniones (JOIN) |
| `automatiza_propuestas` | Propuestas (conversión) |
| `automatiza_tech_clients` | Clientes (conversión) |

### AJAX Hooks (1)
| Hook | Callback |
|------|----------|
| `mark_attendance` | `automatiza_tech_mark_attendance()` |

### Hooks
| Hook | Callback |
|------|----------|
| `admin_menu` | `automatiza_tech_leads_manager_menu()` |
| `admin_action_convert_to_client` | `automatiza_tech_convert_to_client()` |

### Funciones (8)
- `automatiza_tech_leads_manager_page()` — página admin completa (~830 líneas) con tabla, modales
- `automatiza_tech_mark_attendance()` — marcar asistencia/no-show con email automático de no-show
- `automatiza_tech_send_no_show_email()` — email de reagendamiento automático
- `automatiza_tech_validate_appointment_datetime()` — validar fecha/hora de cita
- `automatiza_tech_send_reschedule_email()` — email de reagendamiento
- `automatiza_tech_convert_to_client()` — convertir lead en cliente (copia a tabla clientes, crea propuesta)
- `automatiza_tech_send_lead_whatsapp()` — WhatsApp vía N8N webhook

### Funcionalidad Clave
- **Panel de leads**: listado con filtros (estado, fecha, búsqueda)
- **Estados**: pending, confirmed, attended, no_show, cancelled, rescheduled
- **Marcado de asistencia**: confirmar o no-show con email automático
- **Conversión lead→cliente**: flujo completo con creación de propuesta
- **Integración WhatsApp** vía N8N
- **Validación de citas**: evitar solapamiento de horarios

---

## 10. `admin-n8n-errors.php` — 1,177 líneas

### Tablas DB: `automatiza_n8n_errors`

### REST API (3 rutas — namespace `automatiza/v1`)
| Ruta | Método | Función |
|------|--------|---------|
| `/n8n-errors` | POST | `automatiza_save_n8n_error()` |
| `/n8n-errors` | GET | `automatiza_get_n8n_errors()` |
| `/n8n-errors/search` | POST | `automatiza_search_similar_errors()` |

### AJAX: Ninguno

### Hooks
| Hook | Callback |
|------|----------|
| `admin_menu` | `automatiza_n8n_errors_menu()` |
| `rest_api_init` | `automatiza_register_n8n_errors_api()` + búsqueda |

### Funciones (10)
- `automatiza_verify_n8n_api_key()` — verificación de API key para recibir errores
- `automatiza_save_n8n_error()` — guardar error desde N8N
- `automatiza_get_n8n_errors()` — listar errores (filtros: workflow, severidad, fecha)
- `automatiza_search_similar_errors()` — buscar errores similares
- `automatiza_n8n_errors_page()` — dashboard (~670 líneas) con cards, modal detalle
- `automatiza_n8n_errors_settings_page()` — página configuración API key

### Funcionalidad Clave
- **ARGOS**: sistema de monitoreo de errores N8N
- **Recepción por API**: N8N envía errores automáticamente
- **Dashboard**: cards por severidad (critical, error, warning, info)
- **Búsqueda de similares**: encuentra errores recurrentes
- **Acciones**: resolver, ignorar, ver detalles con trace
- **Configuración**: API key para autenticación de N8N

---

## 11. `services-manager.php` — 2,267 líneas

### Clase: `AutomatizaTechServicesManager` (L12)

### Tablas DB: `automatiza_services`

### AJAX Hooks (5)
| Hook | Método |
|------|--------|
| `save_service` | `save_service()` |
| `delete_service` | `delete_service()` |
| `toggle_service_status` | `toggle_service_status()` |
| `get_service_details` | `get_service_details()` |
| `duplicate_service` | `duplicate_service()` |

### Hooks
| Hook | Callback |
|------|----------|
| `admin_menu` | `add_admin_menu()` |
| `admin_enqueue_scripts` | `admin_scripts()` |
| `after_setup_theme` | `create_table()` |

### Métodos Públicos (14)
- `create_table()` — DDL tabla servicios con campos: name, slug, category, description, features, price_usd, price_clp, icon, image_url, status, display_order, discount_percent
- `admin_page()` — listado de servicios (~480 líneas)
- `new_service_page()` — formulario crear/editar servicio (~195 líneas)
- `frontend_editor_page()` — editor visual de frontend de servicios
- `plans_editor_page()` — editor de planes de precios
- `config_page()` — configuración general
- `get_services_by_category()` / `get_active_services()` — consultas
- CRUD: save, delete, toggle_status, get_details, duplicate

### Funcionalidad Clave
- **Gestión de servicios/planes**: CRUD completo
- **Editor visual de frontend**: preview en tiempo real
- **Editor de planes de precios**: gestión de planes con descuentos
- **Categorías**: vinculado a `service-categories-manager.php`
- **Precios duales**: USD y CLP con conversión
- **Descuentos**: campo `discount_percent` por servicio
- **Funciones helper globales**: `get_automatiza_services()`, `get_active_automatiza_services()`

---

## 12. `service-categories-manager.php` — 1,068 líneas

### Clase: `AutomatizaTechServiceCategoriesManager` (L11)

### Tablas DB: `automatiza_service_categories`

### AJAX Hooks (4)
| Hook | Método |
|------|--------|
| `save_service_category` | `save_category()` |
| `delete_service_category` | `delete_category()` |
| `toggle_category_status` | `toggle_status()` |
| `get_category_details` | `get_category_details()` |

### Hooks
| Hook | Callback |
|------|----------|
| `admin_menu` | `add_submenu()` (prioridad 20) |
| `after_setup_theme` | `create_table()` |

### Funciones Helper Globales (5)
- `get_automatiza_service_categories()` — todas las categorías activas
- `get_quotation_service_categories()` — categorías para cotizaciones
- `get_frontend_service_categories()` — categorías visibles en frontend
- `get_service_category_by_slug()` / `get_service_category_name()`
- `get_default_service_categories()` — categorías por defecto
- `render_service_categories_select()` — render HTML de select
- `get_service_categories_js_options()` — opciones para JS

### Funcionalidad Clave
- **Gestión de categorías** de servicios: CRUD, toggle activo/inactivo
- **Categorías por defecto**: web-apps, automations, chatbots, integrations, consulting, other
- **Campos**: name, slug, description, icon, color, status, display_order, is_quotation_visible, show_in_frontend

---

## 13. `admin-proposals.php` — 835 líneas

### Tablas DB: `automatiza_propuestas`

### AJAX/REST: Ninguno

### Hooks
| Hook | Callback |
|------|----------|
| `admin_menu` | `automatiza_tech_proposals_menu()` |

### Funcionalidad Clave
- **Panel de propuestas/demos**: listado con acciones
- **Eliminación masiva** con nonce
- **Eliminación individual** con confirmación
- **Vista de propuestas** con datos de empresa, URL de demo, prompt

---

## 14. `admin-reminders.php` — 897 líneas

### Tablas DB
| Tabla | Uso |
|-------|-----|
| `automatiza_leads` | Leads con citas |
| `automatiza_followup_meetings` | Reuniones seguimiento |

### AJAX Hooks (1)
| Hook | Callback |
|------|----------|
| `send_manual_reminder` | `automatiza_tech_send_manual_reminder()` |

### Hooks
| Hook | Callback |
|------|----------|
| `admin_menu` | `automatiza_tech_reminders_menu()` |

### Funcionalidad Clave
- **Panel de recordatorios**: leads/reuniones pendientes de recordar
- **Envío manual** de recordatorio individual vía AJAX
- **Procesamiento batch** con cola de envío
- **Tabs**: por tipo de recordatorio (email/WhatsApp, 1h/24h/diario)

---

## 15. `receipts-module.php` — 827 líneas

### Clase: `AutomatizaTechReceipts` (L13)

### Tablas DB: `automatiza_tech_receipts`

### AJAX Hooks (2)
| Hook | Priv | NoPriv |
|------|------|--------|
| `generate_receipt` | ✅ | ✅ |

### Hooks
| Hook | Callback |
|------|----------|
| `admin_menu` | `add_admin_menu()` |
| `init` | `check_table()` |

### Funcionalidad Clave
- **Generación de boletas** (no facturas)
- **Items parametrizables** con cantidad y precio
- **PDF via FPDF** (`receipt-pdf-fpdf.php`)
- **Formulario admin** con items dinámicos (agregar/quitar)
- **Cálculo automático** de totales

---

## 16. `client-pdf-report.php` — 449 líneas

### Clases
| Clase | Línea | Descripción |
|-------|-------|-------------|
| `AutomatizaTech_Client_Report_PDF` (extends FPDF) | L15 | Layout PDF |
| `AutomatizaTech_Client_Report_Generator` | L89 | Generador de reportes |

### Tablas DB
| Tabla | Uso |
|-------|-----|
| `crm_clientes` | Datos del cliente |
| `crm_proyectos` | Proyectos del cliente |
| `automatiza_clients_details` | Detalles de seguimiento |
| `automatiza_propuestas` | Propuestas vinculadas |

### AJAX: `download_client_report_pdf`

### Funcionalidad Clave
- **Reporte PDF completo** de un cliente con toda su info
- **Secciones**: info general, proyectos, detalles de seguimiento, propuestas
- **Header/footer** con branding AT

---

## 17–30. MÓDULOS MENORES

### `currency-admin.php` (406 líneas)
- **Clase**: `AutomatizaTech_Currency_Admin`
- **Tabla**: `automatiza_services`
- **Función**: Panel admin para ver tasa de cambio USD→CLP y precios convertidos
- **Hooks**: `admin_menu`, `admin_enqueue_scripts`

### `currency-updater.php` (322 líneas)
- **Clase**: `AutomatizaTech_Currency_Updater`
- **Tabla**: `automatiza_services`
- **AJAX**: `update_clp_prices_manually`
- **Función**: Actualización de precios CLP desde API mindicador.cl
- **Cron**: `automatiza_tech_daily_price_update` vía WP Cron
- **Hooks**: `init` → `automatiza_tech_init_currency_updater()`

### `customizer.php` (265 líneas)
- **Función**: Extensiones del WP Customizer (colores, fuentes, logo, CTA)
- **Hooks**: `customize_register`, `wp_head`, `customize_preview_init`, `customize_controls_enqueue_scripts`

### `development-config.php` (231 líneas)
- **AJAX**: `create_test_data`
- **Función**: Config de desarrollo: debug bar, email interceptor, cache disabled, test data creator
- **Hooks**: `wp_footer`, `admin_bar_menu`, `init`, `admin_menu`, `wp_mail` (filter)

### `smtp-config.php` (218 líneas)
- **Función**: Configuración SMTP (host, port, user, pass desde constantes)
- **Hooks**: `phpmailer_init`, `wp_mail_from`, `wp_mail_from_name`, `wp_mail_content_type`, `wp_mail_failed`, `admin_notices`, `admin_init`, `admin_footer`
- **Features**: test email button, error logging

### `chat-widget.php` (199 líneas)
- **Función**: Widget de chat con horarios de disponibilidad
- **Hooks**: `wp_enqueue_scripts`, `admin_menu`, `admin_init`, `wp_footer`
- **Settings**: horarios por día, festivos, URL del chat
- **Render**: widget en footer con estado online/offline

### `contact-shortcode.php` (1,161 líneas)
- **Shortcode**: `[automatiza_contact_form]`
- **Función**: Formulario de contacto frontend con validación RUT chileno
- **Features**: autocompletado RUT, validación teléfono internacional, preview formateado, detección de duplicados por teléfono, sanitización multicapa

### `services-frontend.php` (604 líneas)
- **Shortcode**: `[automatiza_services]`
- **Funciones**: `render_features_section()`, `render_pricing_section()`, `render_special_services_section()`
- **Función**: Render dinámico de servicios en frontend con fallback a contenido estático

### `services-manager-clean.php` (1,151 líneas)
- **⚠️ VERSIÓN LIMPIA** de `services-manager.php` — sin editor visual frontend ni editor de planes
- **Misma clase**: `AutomatizaTechServicesManager`

### `template-functions.php` (421 líneas)
- **Funciones helper** del tema: social links, featured services, pricing plans, industries, testimonials, FAQ, price formatting, contact info, dev mode check, optimized images

### `invoice-handlers.php` (358 líneas)
- **AJAX**: `download_invoice` (priv), `validate_invoice` (priv + nopriv)
- **Tabla**: `automatiza_tech_invoices`
- **Shortcode**: `[automatiza_invoice_validation]`
- **Función**: Descarga de facturas PDF y validación pública de facturas por número

### `invoice-handlers_OLD.php` (357 líneas)
- **⚠️ BACKUP** de `invoice-handlers.php`

### `invoice-settings.php` (405 líneas)
- **Función**: Configuración de facturas (datos empresa, RUT, giro, numeración)
- **Hooks**: `admin_menu`, `admin_init`

### `ver-demo.php` (523 líneas)
- **Standalone**: Página de demostración de propuesta
- **Tabla**: `automatiza_propuestas`
- **Función**: Renderiza chat embebido de N8N con prompt personalizado por empresa

---

## RESUMEN DE TABLAS REFERENCIADAS

| Tabla | Módulos que la usan |
|-------|---------------------|
| `automatiza_leads` | api-endpoints, admin-leads-manager, admin-reminders, admin-followup-meetings |
| `automatiza_leads_logs` | api-endpoints, admin-leads-manager |
| `automatiza_followup_meetings` | admin-followup-meetings, api-endpoints, admin-reminders, admin-leads-manager |
| `automatiza_tech_contacts` | contact-form |
| `automatiza_tech_clients` | contact-form, client-operations, client-details, admin-leads-manager |
| `automatiza_propuestas` | admin-proposals, contact-form, admin-followup-meetings, client-details, ver-demo, admin-leads-manager, client-pdf-report |
| `automatiza_propuestas_details` | client-details |
| `automatiza_clients_details` | client-details, client-operations, client-pdf-report |
| `automatiza_tech_invoices` | contact-form, invoice-handlers |
| `automatiza_tech_quotations` | contact-form |
| `automatiza_tech_receipts` | receipts-module |
| `automatiza_services` | services-manager, currency-updater, currency-admin, client-operations |
| `automatiza_service_categories` | service-categories-manager |
| `automatiza_credentials_vault` | credentials-vault-module |
| `automatiza_credentials_logs` | credentials-vault-module |
| `automatiza_n8n_errors` | admin-n8n-errors |
| `at_qa_projects` | admin-qa-module |
| `at_qa_modules` | admin-qa-module |
| `at_qa_cases` | admin-qa-module |
| `at_qa_evidence` | admin-qa-module |
| `at_qa_comments` | admin-qa-module |
| `crm_clientes` | admin-followup-meetings, admin-qa-module, client-pdf-report |
| `crm_proyectos` | client-pdf-report |
| `contact_leads` | development-config (test data) |

---

## RESUMEN TOTAL DE HOOKS

| Tipo | Cantidad |
|------|----------|
| **AJAX hooks (wp_ajax_)** | 63 registros |
| **AJAX nopriv (wp_ajax_nopriv_)** | 14 registros |
| **REST API routes** | 39 rutas |
| **add_action (no-AJAX)** | ~55 hooks |
| **add_filter** | ~8 filtros |
| **Clases PHP** | 12 clases |
| **Funciones/Métodos** | ~200+ |

---

## ARCHIVOS BACKUP/DUPLICADOS

| Archivo | Original | Notas |
|---------|----------|-------|
| `admin-followup-meetings2.php` | `admin-followup-meetings.php` | Versión anterior, menos rutas REST |
| `services-manager-clean.php` | `services-manager.php` | Versión sin editores visuales |
| `invoice-handlers_OLD.php` | `invoice-handlers.php` | Backup prácticamente idéntico |

⚠️ **Riesgo**: Si ambas versiones se cargan simultáneamente, habrá conflictos por funciones duplicadas (mismos nombres de función).
