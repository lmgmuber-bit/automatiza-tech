# Servicios AutomatizaTech — Casos de uso y aplicación del Método AT

## Posicionamiento

AutomatizaTech no vende piezas sueltas: diagnostica el problema y combina marketing, software, automatización e inteligencia artificial en un sistema coherente. El catálogo actual incluye sitios web premium, aplicaciones web, apps móviles, sistemas a medida, automatización, asistentes inteligentes, Portal OmniCliente, marketing digital y asesoría tecnológica.

## Árbol de decisión

```mermaid
flowchart TD
    A["Cliente contacta a AT"] --> B["Diagnóstico tecnológico y comercial"]
    B --> C{"¿Cuál es el problema principal?"}
    C -->|"No vende o no transmite confianza"| WEB["Sitio web premium<br/>Landing · Ecommerce · SEO"]
    C -->|"Necesita operar desde navegador"| APPWEB["Aplicación web<br/>Portal · Dashboard · Reservas"]
    C -->|"Uso recurrente desde celular"| MOBILE["Aplicación móvil<br/>iOS · Android"]
    C -->|"Procesos manuales o repetitivos"| AUTO["Automatización<br/>N8N · CRM · Pagos · Agenda"]
    C -->|"Pierde consultas o ventas"| IA["Asistente inteligente<br/>WhatsApp · Instagram · Web"]
    C -->|"Operación con reglas únicas"| CUSTOM["Sistema a medida<br/>Backoffice · Inventario · Gestión"]
    C -->|"Muchos canales y agentes"| OMNI["Portal OmniCliente<br/>Inbox · Bots · Soporte · Analytics"]
    C -->|"No genera demanda suficiente"| MKT["Marketing digital<br/>SEO · Contenido · Ads · Tracking"]
    C -->|"No sabe qué implementar"| ADVICE["Asesoría tecnológica<br/>Auditoría · Roadmap · Priorización"]
    WEB & APPWEB & MOBILE & AUTO & IA & CUSTOM & OMNI & MKT & ADVICE --> P["Propuesta por fases"]
    P --> E["Ejecución · QA · Entrega"]
    E --> S["Soporte y mejora continua"]
```

## Resumen de casos

| Servicio | Cliente/caso típico | Resultado de negocio | KPI principal |
|---|---|---|---|
| Sitio web premium | Clínica, estudio, profesional, empresa B2B | Confianza y captación | Leads/conversión |
| Ecommerce | Tienda física o catálogo por WhatsApp | Venta digital integrada | Ventas/ticket promedio |
| Aplicación web | Operación basada en Excel/WhatsApp | Centralización y trazabilidad | Tiempo/error por proceso |
| Aplicación móvil | Usuarios recurrentes o trabajo en terreno | Acceso, recurrencia y fidelización | Usuarios activos/retención |
| Sistema a medida | Reglas operacionales únicas | Control y escalabilidad | Productividad/costo operativo |
| Automatización | Copia manual entre herramientas | Menos trabajo repetitivo | Horas ahorradas/errores |
| Asistente IA | Consultas y ventas fuera de horario | Respuesta y conversión 24/7 | Resolución/conversión |
| Portal OmniCliente | Múltiples canales, bots y agentes | Atención organizada | SLA/tiempo de respuesta |
| Marketing digital | Buen servicio sin demanda estable | Leads medibles y crecimiento | CAC/ROAS/conversión |
| Asesoría tecnológica | Herramientas dispersas y prioridades confusas | Roadmap de inversión | Ahorro/impacto ejecutado |

## 1. Sitio web premium o a medida

**Ejemplo:** estudio fotográfico, clínica estética, spa, abogado, restaurante o empresa B2B.

**Problema:** sitio genérico, lento o inexistente; poca confianza y escasa conversión.

```mermaid
flowchart LR
    A["Diagnóstico de marca y ventas"] --> B["Arquitectura de contenidos"]
    B --> C["Copy + identidad visual"]
    C --> D["Diseño UX/UI mobile-first"]
    D --> E["Desarrollo"]
    E --> F["Formularios · WhatsApp · Analytics"]
    F --> G["SEO técnico + QA"]
    G --> H["Publicación"]
    H --> I["Soporte · contenido · CRO"]
```

Entregables posibles:

- Diseño personalizado y responsive.
- Páginas de servicios, casos, equipo y contacto.
- Copy orientado a conversión.
- Formularios, WhatsApp y CRM.
- SEO técnico, schema y analytics.
- Hosting/deploy, capacitación y mantenimiento.

Soporte: seguridad, backups, contenido, SEO, conversiones y nuevas landings.

## 2. Ecommerce conectado

**Ejemplo:** tienda que hoy vende por Instagram/WhatsApp y controla stock manualmente.

```mermaid
flowchart LR
    A["Campaña/SEO"] --> B["Catálogo online"]
    B --> C["Carro y pago"]
    C --> D["Validar stock"]
    D --> E["Orden y despacho"]
    E --> F["Notificación cliente"]
    F --> G["CRM + recompra"]
```

Entregables: catálogo, variantes, pagos, despacho, inventario, emails/WhatsApp, analítica e integración con backoffice.

Soporte: catálogo, pasarelas, seguridad, conciliación, campañas y optimización de checkout.

## 3. Aplicación web

**Ejemplo:** clínica o empresa que gestiona clientes, reservas, pagos y reportes en planillas.

```mermaid
flowchart LR
    A["Levantamiento de procesos"] --> B["Roles y permisos"]
    B --> C["Modelo de datos"]
    C --> D["Prototipo UX"]
    D --> E["MVP web"]
    E --> F["Módulos e integraciones"]
    F --> G["QA + UAT"]
    G --> H["Deploy y soporte"]
```

Módulos frecuentes: usuarios, clientes, agenda, pagos, documentos, notificaciones, dashboard, auditoría e integraciones.

La diferencia con un sitio es que la aplicación ejecuta procesos autenticados y guarda estado operacional.

## 4. Aplicación móvil

**Ejemplo:** gimnasio, centro médico, comunidad, comercio, fidelización o equipo en terreno.

```mermaid
flowchart TD
    A["Público y problema"] --> B["MVP móvil"]
    B --> C["UX/UI y prototipo"]
    C --> D["Backend + panel admin"]
    D --> E["Reservas · compras · servicios"]
    E --> F["Push · cámara · GPS · QR"]
    F --> G["QA Android/iOS"]
    G --> H["Publicación en tiendas"]
    H --> I["Analítica y versiones"]
```

Casos: reservas, puntos, comunidad, seguimiento, notificaciones, pagos, contenido, cámara/GPS y trabajo offline.

Soporte: crashes, compatibilidad, stores, analítica, seguridad y nuevas versiones.

## 5. Sistema a medida

**Ejemplo:** distribuidora, operación logística, producción, órdenes o control interno con reglas que un SaaS genérico no cubre.

```mermaid
flowchart LR
    A["Pedido"] --> B["Cliente + stock"]
    B --> C["Orden"]
    C --> D["Preparación"]
    D --> E["Despacho"]
    E --> F["Confirmación móvil"]
    F --> G["Factura/pago"]
    G --> H["Dashboard gerencial"]
```

Puede incluir inventario, órdenes, proveedores, despacho, facturación, app de terreno, reportes, auditoría e integración ERP/CRM.

Gate especial: blueprint/ADR, MVP por fases, migración y rollback antes de construir el núcleo.

## 6. Automatización de negocios

**Ejemplo:** un equipo copia formularios a Excel, agenda manualmente y envía recordatorios uno a uno.

```mermaid
flowchart LR
    A["Formulario/mensaje"] --> B["Validación"]
    B --> C["Lead en CRM"]
    C --> D["Clasificación"]
    D --> E["Agenda"]
    E --> F["Confirmación y recordatorios"]
    F --> G["Seguimiento"]
    G --> H["Reporte"]
```

Automatizaciones frecuentes:

- Lead → CRM.
- WhatsApp → Calendar.
- Pago → factura.
- Venta → onboarding.
- Ticket → asignación/escalamiento.
- Cobranzas y recordatorios.
- Sincronización entre plataformas.
- Reportes diarios o ejecutivos.

Soporte: monitoreo de workflows, errores, cambios de proveedores, credenciales y métricas.

## 7. Asistente inteligente

**Ejemplo:** negocio que pierde consultas fuera de horario o repite respuestas todo el día.

```mermaid
flowchart TD
    A["Cliente escribe"] --> B["Detectar intención"]
    B --> C{"Tipo"}
    C -->|"Pregunta"| D["Responder con conocimiento"]
    C -->|"Cotización"| E["Capturar y calificar"]
    C -->|"Reserva"| F["Consultar agenda"]
    C -->|"Caso complejo"| G["Escalar a humano"]
    D & E & F --> H["Registrar resultado"]
    G --> I["Takeover del agente"]
```

Canales: WhatsApp, Instagram, web, Telegram y Messenger.

Integraciones: Calendar, CRM, catálogo, pagos, inventario, N8N y Portal OmniCliente.

Soporte: precisión, prompts, base de conocimiento, herramientas, escalamiento y costo por conversación.

## 8. Portal OmniCliente

**Ejemplo:** empresa con varios canales, bots y ejecutivos que responden sin coordinación.

```mermaid
flowchart LR
    A["WhatsApp"] --> P["Portal OmniCliente"]
    B["Instagram"] --> P
    C["Telegram"] --> P
    D["Web"] --> P
    P --> BOT["Bot IA"]
    P --> AG["Agentes humanos"]
    P --> TK["Tickets"]
    P --> AN["Analytics y consumo"]
```

Casos: inbox, asignación, takeover, bots, prompts, agentes, vault, tickets, errores N8N, analytics y costos IA.

No todos los clientes necesitan comprar el Portal. Puede complementar otros servicios cuando resuelve atención, soporte u operación omnicanal.

## 9. Marketing digital y crecimiento

**Ejemplo:** negocio con buen producto pero sin flujo constante de prospectos.

```mermaid
flowchart TD
    A["Auditoría de mercado/oferta"] --> B["ICP + propuesta de valor"]
    B --> C["Landing y activos"]
    C --> D["SEO · contenido · redes"]
    D --> E["Ads"]
    E --> F["Leads al CRM"]
    F --> G["Automatización de seguimiento"]
    G --> H["Medición y optimización"]
```

Servicios: estrategia, ICP, oferta, SEO, contenido, redes, reels/comerciales, Meta/Google Ads, landings, email/WhatsApp marketing, tracking y CRO.

La diferencia AT es conectar marketing con CRM, automatización, atención y ventas; el lead no termina abandonado en un formulario.

## 10. Asesoría y diagnóstico tecnológico

**Ejemplo:** pyme con muchas herramientas, costos, riesgos y prioridades poco claras.

```mermaid
flowchart LR
    A["Entrevistas"] --> B["Mapa de procesos"]
    B --> C["Auditoría tecnológica"]
    C --> D["Problemas y riesgos"]
    D --> E["Impacto/esfuerzo"]
    E --> F["Roadmap 30/60/90"]
    F --> G["Presupuesto por fases"]
    G --> H["Ejecución o acompañamiento"]
```

Entregables: diagnóstico ejecutivo, procesos, inventario tecnológico, riesgos, oportunidades, arquitectura, roadmap, presupuesto, proveedores y acompañamiento.

## Paquetes frecuentes

```mermaid
flowchart TD
    D["Diagnóstico AT"] --> W["Presencia y captación<br/>Web + marketing"]
    D --> O["Operación<br/>App web + sistema"]
    D --> A["Eficiencia<br/>Automatización"]
    D --> I["Atención<br/>Asistente IA + Portal"]
    W & O & A & I --> DATA["CRM y datos compartidos"]
    DATA --> R["Reportes · soporte · mejora continua"]
```

- Spa/clínica: sitio premium + marketing + asistente + agenda + CRM.
- Tienda: ecommerce + inventario + automatización + WhatsApp.
- Empresa de servicios: web + aplicación interna + portal de clientes.
- Operación en terreno: sistema web + app móvil + dashboard.
- Equipo de atención: asistente IA + Portal OmniCliente + tickets.
- Pyme desordenada: asesoría + roadmap + automatizaciones por fases.

## Aplicación del Método AT por servicio

| Servicio | Diagnóstico/prioridad | Diseño/desarrollo | QA/entrega | Soporte |
|---|---|---|---|---|
| Sitio/ecommerce | Marca, oferta, conversión | UX, copy, desarrollo | Responsive, SEO, formularios/pagos | Seguridad, contenido, CRO |
| App web/móvil | Usuarios y procesos | Prototipo, backend, módulos | Integración, UAT, stores/deploy | Bugs, analítica, versiones |
| Sistema a medida | Reglas y datos | Blueprint, MVP, migración | Seguridad, performance, rollback | Operación, evolución |
| Automatización | Proceso y excepciones | Workflow, credenciales, idempotencia | Casos reales y fallos | Monitoreo y proveedores |
| Asistente IA | Intenciones y conocimiento | Prompt, tools, canales | Precisión, escalamiento, costo | Tuning y base de conocimiento |
| Portal OmniCliente | Canales/equipo/SLA | Configuración e integraciones | Permisos, mensajes, takeover | Tickets, errores, consumo |
| Marketing | Oferta, ICP y medición | Activos, campañas, tracking | Eventos, atribución, calidad lead | Optimización mensual |
| Asesoría | Auditoría y objetivos | Roadmap y arquitectura | Validación ejecutiva | Acompañamiento |

## Regla comercial

AT recomienda primero la combinación mínima que resuelve el problema. Las capacidades adicionales se presentan como fases con impacto, costo, dependencias y criterios de éxito, no como tecnología agregada sin justificación.
