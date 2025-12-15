# 🌐 Diagrama de Flujo - Usuario del Sitio Web (Front-end)

**Sistema de Facturación AutomatizaTech**  
**Versión:** 2.0

---

## 📊 Mapa del Sitio - Vista del Usuario

```
┌─────────────────────────────────────────────────────────────┐
│                                                               │
│           🏠 AUTOMATIZATECH.SHOP                             │
│           Sistema de Automatización Digital                  │
│                                                               │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────┴───────────────────┐
        │                                       │
        ▼                                       ▼
┌───────────────┐                      ┌───────────────┐
│   NAVEGACIÓN  │                      │    ACCIONES   │
│    PÚBLICA    │                      │   USUARIO     │
└───────────────┘                      └───────────────┘
        │                                       │
        ▼                                       ▼
┌─────────────────────────────────────┐ ┌─────────────────────────────────────┐
│                                     │ │                                     │
│  📄 Páginas Disponibles             │ │  🎯 Puede Realizar                  │
│                                     │ │                                     │
│  ┌────────────────────────────┐    │ │  ┌────────────────────────────┐    │
│  │ 🏠 Inicio                  │    │ │  │ 📝 Llenar Formulario       │    │
│  │  • Banner principal        │    │ │  │  • Nombre                  │    │
│  │  • Servicios destacados    │    │ │  │  • Email                   │    │
│  │  • Testimonios             │    │ │  │  • Empresa (opcional)      │    │
│  │  • Call to Action          │    │ │  │  • Teléfono (+código)      │    │
│  └────────────────────────────┘    │ │  │  • Mensaje                 │    │
│                                     │ │  └────────────────────────────┘    │
│  ┌────────────────────────────┐    │ │                                     │
│  │ 💼 Servicios               │    │ │  ┌────────────────────────────┐    │
│  │  • Plan Básico             │    │ │  │ 📧 Recibir Cotización      │    │
│  │  • Plan Profesional        │    │ │  │  • Por email               │    │
│  │  • Plan Empresarial        │    │ │  │  • Por teléfono            │    │
│  │  • Plan Premium            │    │ │  └────────────────────────────┘    │
│  │  • Comparador de planes    │    │ │                                     │
│  └────────────────────────────┘    │ │  ┌────────────────────────────┐    │
│                                     │ │  │ 💳 Contratar Servicio      │    │
│  ┌────────────────────────────┐    │ │  │  • Revisar cotización      │    │
│  │ 📞 Contacto               │    │ │  │  • Aceptar propuesta       │    │
│  │  • Formulario de contacto  │    │ │  │  • Esperar confirmación    │    │
│  │  • Información de empresa  │    │ │  └────────────────────────────┘    │
│  │  • Mapa de ubicación       │    │ │                                     │
│  │  • Redes sociales          │    │ │  ┌────────────────────────────┐    │
│  └────────────────────────────┘    │ │  │ 📄 Recibir Factura         │    │
│                                     │ │  │  • Email automático        │    │
│  ┌────────────────────────────┐    │ │  │  • PDF adjunto             │    │
│  │ ℹ️ Sobre Nosotros          │    │ │  │  • Descargar y guardar     │    │
│  │  • Historia de la empresa  │    │ │  └────────────────────────────┘    │
│  │  • Misión y visión         │    │ │                                     │
│  │  • Equipo                  │    │ │  ┌────────────────────────────┐    │
│  └────────────────────────────┘    │ │  │ 📱 Consultar Dudas         │    │
│                                     │ │  │  • Responder al email      │    │
│  ┌────────────────────────────┐    │ │  │  • Llamar por teléfono     │    │
│  │ 📝 Blog/Noticias           │    │ │  │  • WhatsApp                │    │
│  │  • Artículos               │    │ │  └────────────────────────────┘    │
│  │  • Casos de éxito          │    │ │                                     │
│  │  • Tutoriales              │    │ └─────────────────────────────────────┘
│  └────────────────────────────┘    │
│                                     │
└─────────────────────────────────────┘
```

---

## 🔄 Flujo de Interacción del Usuario

```
                    INICIO
                      │
                      ▼
        ┌─────────────────────────────┐
        │  Usuario visita el sitio    │
        │  automatizatech.shop        │
        └─────────────────────────────┘
                      │
                      ▼
        ┌─────────────────────────────┐
        │  Explora servicios y        │
        │  planes disponibles         │
        └─────────────────────────────┘
                      │
                      ▼
              ┌───────┴────────┐
              │                │
              ▼                ▼
    ┌──────────────┐   ┌──────────────┐
    │ Solo navega  │   │ Interesado   │
    │ el sitio     │   │ en contratar │
    └──────────────┘   └──────────────┘
         │                      │
         │                      ▼
         │            ┌──────────────────┐
         │            │ Llena formulario │
         │            │ de contacto      │
         │            └──────────────────┘
         │                      │
         │                      ▼
         │            ┌──────────────────┐
         │            │ Ingresa datos:   │
         │            │ • Nombre         │
         │            │ • Email          │
         │            │ • Teléfono +56   │
         │            │ • Mensaje        │
         │            └──────────────────┘
         │                      │
         │                      ▼
         │            ┌──────────────────┐
         │            │ Clic en "Enviar" │
         │            └──────────────────┘
         │                      │
         │                      ▼
         │            ┌──────────────────┐
         │            │ ✅ Confirmación  │
         │            │ "Mensaje enviado"│
         │            └──────────────────┘
         │                      │
         │                      ▼
         │            ┌──────────────────┐
         │            │ Espera respuesta │
         │            │ (24-48 horas)    │
         │            └──────────────────┘
         │                      │
         │                      ▼
         │            ┌──────────────────┐
         │            │ Recibe cotización│
         │            │ por email        │
         │            └──────────────────┘
         │                      │
         │              ┌───────┴────────┐
         │              │                │
         │              ▼                ▼
         │      ┌──────────────┐ ┌──────────────┐
         │      │ No acepta    │ │ Acepta y     │
         │      │ cotización   │ │ contrata     │
         │      └──────────────┘ └──────────────┘
         │              │                │
         ▼              ▼                ▼
    ┌──────────┐  ┌──────────┐  ┌──────────────┐
    │   FIN    │  │   FIN    │  │ Procesa pago │
    └──────────┘  └──────────┘  └──────────────┘
                                        │
                                        ▼
                              ┌──────────────────┐
                              │ Sistema genera   │
                              │ factura PDF      │
                              └──────────────────┘
                                        │
                                        ▼
                              ┌──────────────────┐
                              │ 📧 Recibe email  │
                              │ con factura PDF  │
                              └──────────────────┘
                                        │
                                        ▼
                              ┌──────────────────┐
                              │ Descarga PDF     │
                              │ Guarda factura   │
                              └──────────────────┘
                                        │
                                        ▼
                                   ┌────────┐
                                   │   FIN  │
                                   └────────┘
```

---

## 📱 Interacción con el Formulario de Contacto

```
┌────────────────────────────────────────────────────────────┐
│                                                            │
│           📝 FORMULARIO DE CONTACTO                        │
│                                                            │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  Nombre Completo: *                                        │
│  ┌──────────────────────────────────────────┐             │
│  │ Juan Pérez                               │             │
│  └──────────────────────────────────────────┘             │
│                                                            │
│  Email: *                                                  │
│  ┌──────────────────────────────────────────┐             │
│  │ juan@ejemplo.com                         │             │
│  └──────────────────────────────────────────┘             │
│                                                            │
│  Empresa: (opcional)                                       │
│  ┌──────────────────────────────────────────┐             │
│  │ Mi Empresa SpA                           │             │
│  └──────────────────────────────────────────┘             │
│                                                            │
│  Teléfono: *  ⚠️ IMPORTANTE: Con código de país           │
│  ┌──────────────────────────────────────────┐             │
│  │ +56912345678                             │             │
│  └──────────────────────────────────────────┘             │
│  💡 Ejemplos correctos:                                    │
│     Chile: +56912345678                                    │
│     USA: +1234567890                                       │
│     Argentina: +54987654321                                │
│                                                            │
│  Mensaje: *                                                │
│  ┌──────────────────────────────────────────┐             │
│  │ Necesito automatizar mi sistema de       │             │
│  │ facturación para mi empresa...           │             │
│  │                                          │             │
│  └──────────────────────────────────────────┘             │
│                                                            │
│         [  Enviar Solicitud  ]                             │
│                                                            │
└────────────────────────────────────────────────────────────┘
                    │
                    ▼
          ┌──────────────────┐
          │ Validación       │
          │ • Campos llenos  │
          │ • Email válido   │
          │ • Teléfono +XX   │
          └──────────────────┘
                    │
          ┌─────────┴─────────┐
          ▼                   ▼
    ┌───────────┐      ┌────────────┐
    │ ❌ Error  │      │ ✅ Éxito   │
    │ Corregir  │      │ Enviado    │
    └───────────┘      └────────────┘
```

---

## 📧 Proceso de Recepción de Factura

```
                    Usuario contrata servicio
                              │
                              ▼
              ┌───────────────────────────────┐
              │ Admin procesa en el sistema   │
              └───────────────────────────────┘
                              │
                              ▼
              ┌───────────────────────────────┐
              │ Sistema genera factura PDF     │
              │ • Detecta país por teléfono    │
              │ • Configura moneda (CLP/USD)   │
              │ • Calcula IVA si aplica        │
              └───────────────────────────────┘
                              │
                              ▼
              ┌───────────────────────────────┐
              │ Email automático enviado       │
              └───────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
    ┌─────────────────┐            ┌─────────────────┐
    │ 📧 Bandeja      │            │ 📎 Archivo      │
    │ de entrada      │            │ adjunto         │
    └─────────────────┘            └─────────────────┘
              │                               │
              ▼                               ▼
    ┌─────────────────┐            ┌─────────────────┐
    │ Asunto:         │            │ AT-YYYYMMDD-    │
    │ "Bienvenido a   │            │ XXXX.pdf        │
    │ AutomatizaTech" │            │                 │
    │                 │            │ Tamaño:         │
    │ De:             │            │ 50-100 KB       │
    │ noreply@        │            │                 │
    │ automatiza      │            │ Formato:        │
    │ tech.shop       │            │ PDF estándar    │
    └─────────────────┘            └─────────────────┘
              │                               │
              └───────────────┬───────────────┘
                              ▼
                    ┌──────────────────┐
                    │ Usuario recibe   │
                    │ notificación     │
                    └──────────────────┘
                              │
                              ▼
                    ┌──────────────────┐
                    │ Abre email       │
                    └──────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
    ┌─────────────────┐            ┌─────────────────┐
    │ Lee mensaje     │            │ Descarga PDF    │
    │ de bienvenida   │            │ adjunto         │
    └─────────────────┘            └─────────────────┘
              │                               │
              │                               ▼
              │                     ┌─────────────────┐
              │                     │ Abre PDF        │
              │                     │ • Ver factura   │
              │                     │ • Revisar datos │
              │                     │ • Guardar       │
              │                     └─────────────────┘
              │                               │
              └───────────────┬───────────────┘
                              ▼
                    ┌──────────────────┐
                    │ Todo correcto?   │
                    └──────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
    ┌─────────────────┐            ┌─────────────────┐
    │ ✅ Sí           │            │ ❌ No           │
    │ Guardar factura │            │ Contactar       │
    │ para contabilidad│           │ soporte         │
    └─────────────────┘            └─────────────────┘
              │                               │
              ▼                               ▼
         ┌────────┐                  ┌─────────────┐
         │  FIN   │                  │ Corrección  │
         └────────┘                  │ y reenvío   │
                                     └─────────────┘
```

---

## 💰 Diferencias: Factura Chile vs Internacional

```
┌────────────────────────────────────────────────────────────┐
│                   DETECCIÓN DE PAÍS                        │
│              (Por código de teléfono)                      │
└────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
    ┌──────────────────┐            ┌──────────────────┐
    │ Teléfono +56     │            │ Teléfono +1, +54 │
    │ (Chile)          │            │ +57, etc.        │
    └──────────────────┘            └──────────────────┘
              │                               │
              ▼                               ▼
    ┌──────────────────┐            ┌──────────────────┐
    │ País: CL         │            │ País: US, AR, CO │
    │ Moneda: CLP      │            │ Moneda: USD      │
    │ IVA: Sí (19%)    │            │ IVA: No          │
    └──────────────────┘            └──────────────────┘
              │                               │
              ▼                               ▼
┌───────────────────────────┐    ┌───────────────────────────┐
│  FACTURA CHILENA          │    │  FACTURA INTERNACIONAL    │
├───────────────────────────┤    ├───────────────────────────┤
│                           │    │                           │
│ Plan Profesional: 1 ud    │    │ Plan Profesional: 1 ud    │
│                           │    │                           │
│ Subtotal (Neto):          │    │ Subtotal:                 │
│              $294.118     │    │           USD $400.00     │
│                           │    │                           │
│ IVA (19%):   $ 55.882     │    │ ────────────────────      │
│                           │    │                           │
│ ──────────────────────    │    │ TOTAL:    USD $400.00     │
│                           │    │                           │
│ TOTAL:       $350.000     │    │ * Factura internacional   │
│                           │    │   No aplica IVA chileno   │
│                           │    │                           │
└───────────────────────────┘    └───────────────────────────┘
```

---

## 📞 Canales de Contacto Disponibles

```
┌────────────────────────────────────────────────────────────┐
│                                                            │
│          📞 FORMAS DE CONTACTARNOS                         │
│                                                            │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  1. 📝 Formulario Web                                      │
│     └─ Desde automatizatech.shop/contacto                 │
│        • Respuesta en 24-48 horas                          │
│        • Genera ticket automático                          │
│                                                            │
│  2. 📧 Email                                               │
│     └─ contacto@automatizatech.cl                           │
│        • Consultas generales                               │
│        • Soporte técnico                                   │
│        • Solicitud de facturas                             │
│                                                            │
│  3. 📱 Teléfono                                            │
│     └─ +56 9 1234 5678                                     │
│        • Lunes a Viernes                                   │
│        • 9:00 AM - 6:00 PM                                 │
│        • Horario Chile                                     │
│                                                            │
│  4. 💬 WhatsApp                                            │
│     └─ +56 9 1234 5678                                     │
│        • Respuesta rápida                                  │
│        • Consultas cortas                                  │
│                                                            │
│  5. 🌐 Redes Sociales                                      │
│     └─ LinkedIn, Twitter, Facebook                         │
│        • Noticias y actualizaciones                        │
│        • Casos de éxito                                    │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

---

## ✅ Checklist para el Usuario

### Antes de Contactar

```
[ ] Revisar servicios disponibles en el sitio
[ ] Leer descripción de planes
[ ] Preparar información de tu empresa
[ ] Tener teléfono con código de país (+56, +1, etc.)
[ ] Tener email válido y activo
[ ] Definir qué necesitas (descripción clara)
```

### Al Llenar el Formulario

```
[ ] Nombre completo (no solo nombre)
[ ] Email correcto (revisar escritura)
[ ] Teléfono con + y código de país
[ ] Mensaje claro y específico
[ ] Revisar datos antes de enviar
[ ] Esperar mensaje de confirmación
```

### Después de Enviar

```
[ ] Revisar bandeja de entrada (24-48h)
[ ] Revisar carpeta de spam si no llega
[ ] Responder cotización si interesa
[ ] Esperar factura después de contratar
[ ] Descargar y guardar PDF de factura
[ ] Contactar si hay dudas o errores
```

---

## 🎯 Resumen Visual del Viaje del Usuario

```
1. DESCUBRIMIENTO        2. INTERÉS           3. CONTACTO
   │                        │                    │
   ▼                        ▼                    ▼
┌─────────┐            ┌─────────┐          ┌─────────┐
│ Visita  │            │ Explora │          │ Llena   │
│ Sitio   │───────────>│ Planes  │─────────>│ Form    │
└─────────┘            └─────────┘          └─────────┘
   Web                    Web                   Web


4. COTIZACIÓN            5. CONTRATACIÓN      6. FACTURA
   │                        │                    │
   ▼                        ▼                    ▼
┌─────────┐            ┌─────────┐          ┌─────────┐
│ Recibe  │            │ Acepta  │          │ Recibe  │
│ Email   │───────────>│ y Paga  │─────────>│ PDF     │
└─────────┘            └─────────┘          └─────────┘
   Email                  Email                Email


   Tiempo estimado total: 1-7 días
```

---

**Fin del Diagrama de Usuario Front-end**

**Versión:** 2.0  
**Actualización:** Noviembre 2025  
**AutomatizaTech Development Team**
