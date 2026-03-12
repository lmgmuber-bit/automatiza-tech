# AutomatizaTech — ¿Qué es y cómo funciona?

> Guía general para entender AutomatizaTech sin necesidad de conocimientos técnicos.  
> **Última actualización:** 28 de Febrero 2026 — Sincronizada con DOCUMENTO_TECNICO.  
> **IMPORTANTE:** Este documento debe actualizarse cada vez que se suba un cambio a PROD.

---

## ¿Qué es AutomatizaTech?

**AutomatizaTech** es una plataforma digital todo-en-uno que ayuda a empresas a gestionar su negocio de forma inteligente. Combina una página web profesional, un sistema de gestión de clientes (CRM), inteligencia artificial y automatización de procesos en un solo lugar.

Está diseñada y operada por una consultora tecnológica chilena bajo el dominio **automatizatech.cl**.

---

## ¿Qué problemas resuelve?

| Problema | Solución AutomatizaTech |
|---|---|
| "Pierdo leads porque no les doy seguimiento a tiempo" | Bot de WhatsApp + recordatorios automáticos por email y WhatsApp |
| "No sé cuántas citas tengo esta semana" | Calendario integrado con Google Calendar + panel de citas |
| "Mis facturas y cotizaciones las hago en Word" | Generador automático de facturas, cotizaciones y boletas en PDF con código QR |
| "No tengo un lugar centralizado para mis clientes" | CRM completo con ficha de cliente, proyectos, historial y portal de acceso |
| "Necesito un asistente que conozca todo mi negocio" | MAXTECH: agente de IA que conoce tus clientes, proyectos, agenda y documentos |
| "Tengo contraseñas de servicios en un Excel" | Bóveda de credenciales con encriptación de nivel bancario |
| "No sé si mis flujos automáticos están fallando" | ARGOS: monitor de errores en tiempo real |
| "Quiero que mis clientes vean el avance de su proyecto" | Portal público con timeline accesible por enlace privado |

---

## Los 8 módulos principales

### 1. 🌐 Página web profesional
Una landing page moderna con:
- Presentación de servicios y planes con precios en USD y CLP
- Formulario de contacto inteligente (detecta país, valida RUT chileno)
- Botón de WhatsApp flotante
- Chat con inteligencia artificial integrado
- Tipo de cambio actualizado automáticamente desde el Banco Central de Chile

### 2. 📇 CRM (Gestión de Relaciones con Clientes)
Un panel de control donde se gestiona todo el ciclo de vida del cliente:
- **Contactos**: Personas que llegan por el formulario web
- **Leads**: Personas que agendan una cita (desde WhatsApp o la web)
- **Prospectos**: Contactos con potencial, en seguimiento
- **Clientes**: Personas contratadas, con proyectos activos
- **Timeline**: Historial completo de cada interacción con el cliente
- **Búsqueda avanzada**: Encontrar cualquier cliente, contacto o propuesta al instante

### 3. 🤖 MAXTECH — Asistente de Inteligencia Artificial
Un agente de IA integrado en el panel de administración que:
- Conoce todos los clientes, proyectos y el estado del negocio
- Puede analizar documentos (Word, Excel, PowerPoint, PDF, imágenes)
- Lee archivos desde Google Drive de cada cliente
- Conoce los flujos de automatización activos
- Genera respuestas de voz
- Permite grabación de audio para hablarle
- Mantiene historial de conversaciones por sesión
- Respeta privacidad: solo muestra datos según el rol del usuario

### 4. 📱 Bot de WhatsApp + Automatización
Mediante N8N (plataforma de automatización), el sistema:
- Atiende consultas por WhatsApp con IA las 24 horas
- Agenda citas automáticamente verificando disponibilidad
- Envía recordatorios antes de las reuniones (1h, 24h, 72h)
- Envía emails de confirmación y cancelación
- Genera propuestas comerciales automáticas
- Sincroniza con Google Calendar

### 5. 💰 Facturación y Cotizaciones
El sistema genera documentos profesionales en PDF:
- **Cotizaciones**: Numeración automática (C-AT-YYYYMMDD-XXXX), detalle de servicios, precios en USD/CLP
- **Facturas**: Con código QR verificable, datos del emisor, IVA incluido
- **Boletas**: Con cálculo automático de IVA (19%)
- Todos se envían por email automáticamente al cliente
- Las facturas se pueden validar públicamente escaneando el QR

### 6. 📋 QA Testing (Control de Calidad)
Un módulo completo para gestionar pruebas de software:
- Proyectos organizados en módulos y casos de prueba
- Estados: Pendiente, Aprobado, Fallido, Bloqueado
- Subida de evidencias (capturas de pantalla) con descripción
- Galería de imágenes con navegación
- Comentarios entre el equipo
- Asignación de testers por módulo
- Importación de casos desde documentos Markdown
- Generación de reportes

### 7. 🔐 Bóveda de Credenciales
Un lugar seguro para almacenar contraseñas y claves de servicios:
- Encriptación de grado militar (AES-256-CBC con derivación PBKDF2)
- Requiere re-ingresar contraseña de WordPress para acceder
- Sesión con temporizador de seguridad
- Registro de quién accede y cuándo
- 16 categorías (APIs, contraseñas, tokens, certificados, SSH, etc.)
- Categorización por servicio y entorno (producción, desarrollo, etc.)

### 8. 🛡️ ARGOS — Monitor de Errores
Un panel que vigila los flujos automáticos de N8N:
- Recibe alertas cuando un flujo falla
- Muestra detalle del error y el workflow afectado
- Permite buscar errores similares anteriores
- Marcar como resuelto o ignorado

---

## Flujo comercial paso a paso

```
PERSONA INTERESADA
    │
    ├─→ Llega por la WEB → Llena formulario → Queda como CONTACTO
    │
    └─→ Escribe por WHATSAPP → Bot IA le atiende → Agenda una CITA
                                                         │
                                                         ▼
                                              Se crea en GOOGLE CALENDAR
                                                         │
                                     ┌────────────────────┼────────────────────┐
                                     │                    │                    │
                                 72h antes            24h antes             1h antes
                                     │                    │                    │
                                     ▼                    ▼                    ▼
                               RECORDATORIO          RECORDATORIO        RECORDATORIO
                             (Email + WhatsApp)    (Email + WhatsApp)  (Email + WhatsApp)
                                                         │
                                                         ▼
                                                    ¿ASISTIÓ?
                                                    /        \
                                                  SÍ          NO
                                                  │            │
                                                  ▼            ▼
                                          SE CONVIERTE    Se ofrece
                                          EN CLIENTE      REAGENDAR
                                                  │
                                                  ▼
                                        SE CREA FICHA EN CRM
                                    (proyectos, timeline, branding)
                                                  │
                                                  ▼
                                         SE GENERA COTIZACIÓN
                                              (PDF + email)
                                                  │
                                                  ▼
                                        ¿ACEPTA LA PROPUESTA?
                                                  │
                                                  ▼
                                           SE GENERA FACTURA
                                          (PDF + QR + email)
                                                  │
                                                  ▼
                                      SEGUIMIENTO DEL PROYECTO
                                   (reuniones, avances, portal web)
                                                  │
                                                  ▼
                                        CLIENTE SATISFECHO ✅
```

---

## ¿Quién usa el sistema?

| Usuario | Accede a | Cómo |
|---|---|---|
| **Administrador** | Todo el panel de WordPress: CRM, contactos, servicios, facturación, QA, MAXTECH, bóveda | Panel admin WordPress |
| **Tester QA** | Solo el módulo de QA Testing (ver proyectos, ejecutar casos, subir evidencias) | Panel admin con rol limitado |
| **Cliente** | Su timeline público (avance de proyecto) y chat | Enlace privado con token |
| **Visitante web** | Página pública, formulario de contacto, chat IA público | automatizatech.cl |
| **Lead (WhatsApp)** | Bot de WhatsApp, agendar cita, confirmar/cancelar vía email | WhatsApp + enlaces de acción |

---

## Servicios que ofrece AutomatizaTech

La plataforma gestiona y comercializa servicios de consultoría tecnológica:
- Desarrollo web y aplicaciones
- Automatización de procesos con IA
- Chatbots para WhatsApp
- Sistemas CRM personalizados
- Marketplace y e-commerce
- Integración de herramientas (Google, N8N, APIs)

Los precios se manejan en **USD** y **CLP** con actualización automática del tipo de cambio desde el Banco Central de Chile.

---

## Datos clave

| Dato | Valor |
|---|---|
| URL de producción | automatizatech.cl |
| País de operación | Chile |
| Zona horaria | America/Santiago |
| Monedas | USD + CLP (conversión automática) |
| IVA aplicado | 19% |
| IA integrada | OpenAI GPT-4o, GPT-4o-mini, TTS-1, Whisper (MAXTECH) |
| Automatización | N8N (~40 flujos activos) |
| Canales de atención | Web, WhatsApp, Email, Portal de clientes |
| Documentos generados | Facturas, Cotizaciones, Boletas (PDF con QR) |

---

## Identidad de marca

| Elemento | Detalle |
|---|---|
| **Nombre** | AutomatizaTech |
| **Slogan** | "Conectamos tus ventas, web y CRM" |
| **Colores** | Azul eléctrico/Teal, Blanco, Verde lima (gradientes) |
| **Tipografía** | Montserrat (títulos), Poppins (cuerpo) |
| **Agente IA** | MAXTECH |
| **Monitor de errores** | ARGOS |
| **Estilo visual** | Moderno, gradientes, glassmorphism, animaciones suaves |

---

---

## Entorno de desarrollo

El sistema opera en dos entornos:
- **Producción (PROD):** automatizatech.cl — Hostinger LiteSpeed. Aquí ven los clientes.
- **Local (LOCAL):** localhost/automatiza-tech — WAMP64 con Xdebug. Para desarrollo y pruebas.
  - En LOCAL se muestra un banner naranja fijo: "⚠ AMBIENTE LOCAL — NO ES PRODUCCIÓN ⚠"

---

## Procedimiento de actualización

Cada vez que se suba un cambio a producción:
1. Probar los cambios en el ambiente LOCAL
2. Subir archivos modificados a Hostinger (manualmente)
3. Limpiar caché (`purge-cache.php`)
4. **Actualizar este documento y el Documento Técnico** si aplica
5. Hacer commit de los documentos actualizados

---

*Este documento es una guía general de AutomatizaTech. Para detalles técnicos del código, arquitectura y base de datos, consultar el Documento Técnico (`DOCUMENTO_TECNICO_AUTOMATIZATECH.md`).*
