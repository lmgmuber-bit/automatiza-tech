# AutomatizaTech — Documento General

> **Versión:** 1.0  
> **Última actualización:** 24 de marzo de 2026  
> **Propósito:** Dar contexto general a cualquier persona o agente IA que necesite entender qué es AutomatizaTech

---

## 1. ¿Qué es AutomatizaTech?

**AutomatizaTech** es una consultora tecnológica chilena que ofrece una plataforma integral de automatización digital, CRM e inteligencia artificial para pequeñas y medianas empresas. Combina presencia web profesional, chatbots inteligentes por WhatsApp, gestión de clientes, facturación electrónica y automatización de procesos en un ecosistema unificado.

- **Dominio:** automatizatech.cl
- **Ubicación:** Santiago, Chile
- **Monedas:** USD + CLP (conversión automática desde Banco Central de Chile)
- **Operando desde:** ~2024

---

## 2. Problema que Resuelve

| Problema del negocio | Solución AutomatizaTech |
|---|---|
| Citas perdidas por WhatsApp | Bot de WhatsApp que agenda automáticamente |
| Seguimiento manual de leads | CRM con timeline completa + seguimiento automático |
| Datos de clientes dispersos | CRM centralizado con proyectos y documentos |
| Facturación manual | PDFs auto-generados con QR, IVA 19%, envío por email |
| Soporte sin IA | MAXTECH — Asistente IA que conoce todos los clientes |
| Contraseñas y credenciales perdidas | Bóveda encriptada (AES-256-CBC) |
| Errores en automatizaciones | ARGOS — Monitoreo de errores en N8N en tiempo real |
| Falta de visibilidad del cliente | Portal público con estado de proyectos |

---

## 3. Los 8 Módulos Core

### 3.1 Portal Web Profesional
- Landing page con showcase de servicios
- Precios dinámicos (USD ↔ CLP)
- Formulario de contacto con IA (detección de país, validación RUT)
- Botón flotante de WhatsApp
- Chatbot embebido

### 3.2 CRM
- Flujo: Contacto → Lead → Prospecto → Cliente
- Timeline histórico por cliente
- Seguimiento de proyectos con hitos
- Búsqueda avanzada

### 3.3 MAXTECH — Asistente IA
- Conoce todos los clientes, proyectos y estado del negocio
- Analiza documentos (Word, Excel, PDF, imágenes)
- Lee archivos de Google Drive por cliente
- Entrada/salida de voz (Whisper + TTS)
- Respuestas contextualizadas por rol de usuario
- Historial de chat por sesión

### 3.4 WhatsApp Bot + Automatización N8N
- Soporte IA 24/7
- Agendamiento automático con validación de disponibilidad
- Recordatorios inteligentes (72h, 24h, 1h)
- Generación automática de propuestas
- Sincronización Google Calendar
- Validación de pagos por transferencia bancaria (GPT-4 Vision)
- ~40 workflows activos en N8N

### 3.5 Facturación y Cotizaciones
- Cotizaciones auto-numeradas (C-AT-YYYYMMDD-XXXX)
- Facturas con código QR de verificación
- Boletas con IVA 19%
- Generación PDF + envío por email
- Validación pública por QR

### 3.6 Módulo QA Testing
- Gestión de casos de prueba por proyecto/módulo
- Carga de evidencias (capturas de pantalla)
- Estados: Pendiente, Aprobado, Fallido, Bloqueado
- Hilos de comentarios
- Importación de casos en Markdown
- Generación de reportes

### 3.7 Bóveda de Credenciales
- Encriptación AES-256-CBC con PBKDF2
- 16 categorías (APIs, passwords, tokens, certificados, SSH)
- Auditoría de accesos
- Re-autenticación requerida
- Timeout de seguridad

### 3.8 ARGOS — Monitoreo de Errores
- Alertas de fallos en workflows N8N en tiempo real
- Detalle e historial de errores
- Marcado de resolución

---

## 4. Portal OmniCliente

El **Portal OmniCliente** es el producto SaaS principal: una bandeja de entrada unificada para gestionar conversaciones de múltiples canales.

### Canales Soportados
- WhatsApp Business API (YCloud)
- Instagram Direct
- Telegram Bot API
- Facebook Messenger
- Email
- Webchat

### Roles de Usuario
| Rol | Acceso |
|---|---|
| **Admin (SuperAdmin)** | Todo: clientes, dashboard, canales, bots, agentes, auditoría |
| **Supervisor** | Bandeja, agentes, bots, auditoría (de su cliente) |
| **Agente** | Bandeja, conversaciones asignadas, perfil |
| **Cliente** | Bandeja, canales, tickets de soporte |

### Planes
| Plan | Canales | Agentes | Funciones |
|---|---|---|---|
| **Básico** | 2 | 3 | Bot básico, email soporte |
| **Profesional** | 5 | 10 | Bot + reportes, chat soporte, integraciones |
| **Enterprise** | Ilimitado | Ilimitado | Todo + account manager dedicado |

### Funcionalidades Clave
- Bandeja de entrada unificada (todas las conversaciones en un lugar)
- Configuración de bots por canal (prompt, tono, idioma, escalamiento)
- Gestión de agentes con roles y habilidades
- Auditoría completa de todas las acciones
- Sistema de tickets de soporte (público + interno)
- Templates de bot multilenguaje
- Integración webhook con N8N para automatizaciones avanzadas
- Modo oscuro / claro
- Perfil con avatar upload y cambio de contraseña con OTP por email

---

## 5. Clientes Conocidos

### PetsGO 🐾
- **Tipo:** Marketplace de productos para mascotas
- **URL:** petsgo.cl
- **Funciones:** Marketplace multi-vendor, pagos Transbank, panel de vendedores, facturación, JWT auth
- **QA:** Testing completo documentado

### Kells Capilares ✂️
- **Tipo:** Peluquería / Salón de belleza
- **Uso principal:** Bot de WhatsApp para agendamiento + validación de transferencias bancarias
- **Funciones:** Booking por WhatsApp, selección de servicios, validación de pagos, confirmación automática, recordatorios, Google Sheets

---

## 6. Segmentos de Mercado Objetivo

| Segmento | Dolor | Solución | Precio |
|---|---|---|---|
| Peluquerías/Salones | Citas perdidas por WhatsApp | Bot + auto-booking | $99-149/mes |
| Barberías | Overbooking + confirmaciones manuales | Auto-scheduling + validación | $99-199/mes |
| Estéticas/Spas | Múltiples servicios, combos | Selección inteligente + upsells | $199-299/mes |
| Clínicas/Consultorios | Validación de seguros + cobros | Pre-validación + automatización | $299-499/mes |
| Academias/Cursos | Multi-horario + cupos | Auto-waitlist + notificaciones | $199-299/mes |
| E-commerce | Tracking de pedidos, soporte | Bot de estado + soporte IA | Variable |

---

## 7. URLs y Accesos

| Recurso | URL |
|---|---|
| Sitio web | https://automatizatech.cl |
| Portal OmniCliente | https://automatizatech.cl/omnicliente/ |
| API OmniCliente | https://automatizatech.cl/api-omnichannel.php |
| WordPress Admin | https://automatizatech.cl/wp-admin/ |
| Repositorio Git | github.com/lmgmuber-bit/automatiza-tech |

---

## 8. Stack Tecnológico (Resumen)

| Capa | Tecnología |
|---|---|
| Frontend | React 19.1 + Vite 6.4 + Tailwind CSS 3.4 |
| Backend | WordPress + PHP 8.3 |
| Base de datos | MySQL 9.1 |
| Automatización | N8N (~40 workflows) |
| IA | OpenAI GPT-4o, GPT-4o-mini, Whisper, TTS-1 |
| Mensajería | WhatsApp Business API (YCloud), Instagram, Telegram, Messenger |
| Hosting | Hostinger (Linux, Apache) |
| Dev local | WAMP64 (Windows) |
| Repositorio | Git (branch: prod-sync-2025-06-26) |

---

## 9. Documentación Disponible

| Documento | Contenido |
|---|---|
| **AUTOMATIZATECH_GENERAL.md** (este) | Visión general del ecosistema |
| **AUTOMATIZATECH_TECNICO.md** | Detalles técnicos de todos los componentes |
| **INTEGRACION_N8N_PORTAL_OMNICLIENTE.md** | Integración N8N ↔ Portal (Opción B) |
| **GUIA_DEPLOY_MOBILE.md** | Guía de despliegue como app móvil |
| **MANUAL_PROGRAMADOR.md** | Manual para desarrolladores |
| **MANUAL_USUARIO.md** | Manual para usuarios finales |
| **MANUAL_CONTEXTO_IA.md** | Contexto para agentes IA |
| **PORTAL_OMNICANAL_DOCS.md** | Documentación técnica del portal |
| **ESTRATEGIA_VENTAS_AGENDAMIENTO.md** | Estrategia de ventas |
| **PROMPTS_VENTAS_AGENDAMIENTO.md** | Prompts para generación de contenido de ventas |

---

## 10. Flujo de Negocio Principal

```
VISITANTE WEB
    ↓
Landing Page → Formulario de Contacto → Se crea CONTACTO en CRM
                    ↓
         Bot IA hace seguimiento (24h, 48h, 72h)
                    ↓
        AGENDA CITA (sync con Google Calendar)
                    ↓
         Recordatorios (72h, 24h, 1h antes)
                    ↓
    ¿ASISTIÓ? SÍ → Se convierte en CLIENTE
               ↓
    Generar Propuesta (PDF + email)
               ↓
    ¿Cliente aprueba? SÍ → Generar Factura (con QR)
               ↓
    Pago enviado → Transfer ID extraído + validado
               ↓
    Proyecto trackeado en CRM con hitos
               ↓
    Portal del cliente con visibilidad del progreso
               ↓
    CLIENTE SATISFECHO ✅
```

---

*Este documento es mantenido como referencia central. Para detalles técnicos profundos, consultar AUTOMATIZATECH_TECNICO.md*
