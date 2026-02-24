# Flujo Comercial AutomatizaTech + Sistema de Tracking AI

## Ciclo de Vida del Cliente

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           EMBUDO DE VENTAS                                       │
└─────────────────────────────────────────────────────────────────────────────────┘

   CONTACTOS (CRM)                    TRACKING AI                 FACTURACIÓN
   ══════════════                     ═══════════                 ════════════

┌──────────────┐
│   🆕 NUEVO    │ ◄─── Lead llega (web, WhatsApp, referido)
└──────┬───────┘
       │ Se envía email con planes
       ▼
┌──────────────┐
│ 📞 CONTACTADO │ ◄─── Primer contacto realizado
└──────┬───────┘
       │ Muestra interés, quiere ver demo
       ▼
┌──────────────┐     ┌─────────────────────────┐
│ ⚡ INTERESADO │────►│ Se crea bot de prueba   │
└──────┬───────┘     │ client_identifier:      │
       │             │ "demo_nombre_empresa"   │──► NO SE FACTURA
       │             └─────────────────────────┘    (costo adquisición)
       │
       ▼
┌──────────────────────────────────────────────┐
│  📅 DEMO AGENDADA                             │
│  (Gestión de Citas y Recordatorios)          │
│  - Recordatorio 72h (WhatsApp automático)    │
│  - Recordatorio 24h (WhatsApp automático)    │
│  - Recordatorio 1h (WhatsApp automático)     │
└──────────────────────────────────────────────┘
       │
       │ Demo realizada
       ▼
┌──────────────────────────────────────────────┐
│  📋 REUNIONES DE SEGUIMIENTO                  │
│  - Se envía cotización/propuesta             │
│  - Se afinan detalles técnicos               │
│  - Se resuelven dudas                        │
│  - Puede haber varias reuniones              │
└──────────────────────────────────────────────┘
       │
       │ ¿Acepta propuesta?
       │
   ┌───┴───┐
   │       │
   ▼       ▼
  SÍ      NO
   │       │
   │       └──────────────────┐
   │                          ▼
   │                   ┌──────────────┐
   │                   │❌ NO INTERESADO│ ──► Se archiva
   │                   └──────────────┘      (demo_* queda como costo)
   │
   ▼
┌──────────────┐     ┌─────────────────────────┐
│ ⭐ CONTRATADO │────►│ CONVERSIÓN:             │
└──────┬───────┘     │ demo_* → cliente_*      │──► SE EMPIEZA A FACTURAR
       │             │ php gestion-ai.php      │
       │             │ convertir nombre_empresa│
       │             └─────────────────────────┘
       │
       │ Pasa a tabla de CLIENTES
       ▼
┌──────────────────────────────────────────────────────────────────────┐
│                        👤 CLIENTE ACTIVO                              │
│                                                                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  │
│  │ Bot WhatsApp│  │ Sitio Web   │  │ Integraciones│  │ Soporte     │  │
│  │ funcionando │  │ activo      │  │ activas      │  │ técnico     │  │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘  │
│                                                                       │
│  client_identifier: "cliente_nombre_empresa"                          │
│                                                                       │
│  FACTURACIÓN MENSUAL:                                                 │
│  ├── Consumo AI (tokens) ──► Del tracking                            │
│  ├── Mantenimiento ────────► Fijo mensual                            │
│  └── Servicios adicionales ► Según contrato                          │
└──────────────────────────────────────────────────────────────────────┘
       │
       │ Si cancela servicio
       ▼
┌──────────────┐
│ 🔒 CERRADO   │ ──► Se desactiva bot, se archiva historial
└──────────────┘
```

---

## Mapeo de Estados con Tracking AI

| Estado CRM | ¿Usa AI? | client_identifier | ¿Se Factura? |
|------------|----------|-------------------|--------------|
| Nuevo | No | - | No |
| Contactado | No | - | No |
| Interesado | SÍ (demo) | `demo_empresa` | ❌ No (costo adquisición) |
| No Interesado | - | - | No |
| **Contratado** | SÍ | `cliente_empresa` | ✅ **SÍ** |
| Cerrado | No | Se archiva | No |

---

## Historial Completo de un Cliente

Cuando un cliente contrata, su historial incluye:

### 1. Datos de Contacto (tabla `wp_automatiza_contactos`)
- Nombre, email, teléfono, empresa
- Fecha primer contacto
- Estado actual
- Notas comerciales

### 2. Historial de Demos (tabla `wp_appointments`)
- Fecha de demo inicial
- Recordatorios enviados
- Estado (completada/cancelada/no-show)

### 3. Reuniones de Seguimiento (tabla seguimiento)
- Fechas de reuniones post-demo
- Propuestas enviadas
- Negociaciones

### 4. Consumo AI (tabla `ai_usage_log`)
- Tokens usados en demo
- Tokens usados como cliente
- Costo acumulado
- Detalle por mes

### 5. Facturación (por implementar)
- Facturas emitidas
- Estado de pago
- Historial de cobros

---

## Integración con N8N

### Flujos por Etapa:

| Etapa | Flujo N8N | client_identifier |
|-------|-----------|-------------------|
| Demo agendada | Tech_Calendar_Subworkflow | - |
| Recordatorios | Tech_Reminder_72h/24h/1h | - |
| Bot en demo | Bot personalizado | `demo_empresa` |
| Bot en producción | Bot personalizado | `cliente_empresa` |
| WhatsApp Tech | WhatsApp_Tech_Principal | `interno_whatsapp` |
| Propuestas | propuesta-gamma-workflow | `interno_propuestas` |

---

## Comandos de Gestión

```bash
# Ver prospectos en demo (aún no contratan)
php gestion-ai.php demos

# Ver clientes activos y cuánto facturar
php gestion-ai.php clientes

# Cuando un prospecto contrata:
php gestion-ai.php convertir nombre_empresa

# Ver historial de un cliente específico
php gestion-ai.php detalle cliente_nombre_empresa

# Ver consumo interno de AutomatizaTech
php gestion-ai.php interno

# Reporte completo
php reporte-consumo-ai.php
```

---

## Próximos Pasos de Implementación

### Fase 1: Ya Implementado ✅
- [x] Tabla `ai_usage_log` para tracking
- [x] Controlador PHP con cálculo de costos
- [x] Dashboard visual de consumo
- [x] Herramienta de gestión por consola
- [x] Template N8N para nuevos bots

### Fase 2: Por Implementar
- [ ] Integrar tracking con tabla de Contactos (relacionar client_identifier con ID contacto)
- [ ] Vista de consumo AI en ficha de cliente (dentro de WP Admin)
- [ ] Módulo de facturación mensual automática
- [ ] Alertas cuando cliente supere umbral de consumo
- [ ] Dashboard unificado: Contactos + Demos + Consumo AI + Facturación

### Fase 3: Automatizaciones
- [ ] Email automático fin de mes con resumen de consumo
- [ ] Workflow N8N para generar pre-facturas
- [ ] Integración con sistema de boletas/facturas existente
