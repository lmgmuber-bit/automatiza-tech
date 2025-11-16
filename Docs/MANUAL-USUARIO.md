# 📖 Manual de Usuario - AutomatizaTech

**Sistema de Facturación Multi-Moneda**  
**Versión:** 2.0  
**Fecha:** Noviembre 2025

---

## 📋 Índice

### Para Usuarios del Sitio Web (Front-end)
1. [Cómo Solicitar una Cotización](#1-cómo-solicitar-una-cotización)
2. [Qué Esperar Después](#2-qué-esperar-después)
3. [Recibir tu Factura](#3-recibir-tu-factura)

### Para Administradores (Back-end)
4. [Acceso al Panel de Administración](#4-acceso-al-panel-de-administración)
5. [Gestionar Contactos](#5-gestionar-contactos)
6. [Convertir Contactos en Clientes](#6-convertir-contactos-en-clientes)
7. [Configurar Datos de Facturación](#7-configurar-datos-de-facturación)
8. [Gestionar Servicios y Planes](#8-gestionar-servicios-y-planes)
9. [Revisar Facturas Generadas](#9-revisar-facturas-generadas)
10. [Sistema de Notificaciones](#10-sistema-de-notificaciones)

---

# PARTE 1: USUARIOS DEL SITIO WEB (FRONT-END)

---

## 1. Cómo Solicitar una Cotización

### Paso 1: Acceder al Formulario de Contacto

1. Visita el sitio web: **https://automatizatech.shop**
2. Busca la sección "Contacto" o "Solicitar Cotización"
3. Verás un formulario con varios campos

### Paso 2: Completar el Formulario

**Campos requeridos:**

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **Nombre Completo** | Tu nombre y apellido | Juan Pérez |
| **Email** | Tu correo electrónico | juan@ejemplo.com |
| **Empresa** (opcional) | Nombre de tu empresa | Empresa Demo SpA |
| **Teléfono** | Con código de país | +56912345678 |
| **Mensaje** | Descripción de lo que necesitas | Necesito automatizar mi sistema de facturación |

### 📱 Importante: Formato del Teléfono

El sistema detecta automáticamente tu país por el código telefónico:

**Formato correcto:**
```
Chile: +56912345678
USA: +1234567890
Argentina: +54987654321
Colombia: +57312345678
México: +52155512345
```

**Formato incorrecto:**
```
❌ 912345678 (falta código de país)
❌ 56912345678 (falta el símbolo +)
❌ (56) 9 1234 5678 (no usar paréntesis)
```

### Paso 3: Enviar el Formulario

1. Revisa que todos los campos estén completos
2. Clic en el botón **"Enviar"** o **"Solicitar Cotización"**
3. Verás un mensaje de confirmación

**Mensaje de éxito:**
```
✅ ¡Mensaje enviado exitosamente!
Te contactaremos pronto.
```

---

## 2. Qué Esperar Después

### Proceso Automático

Una vez envías el formulario:

**Paso 1: Confirmación Inmediata**
- Verás un mensaje de éxito en pantalla
- Tu solicitud fue recibida correctamente

**Paso 2: Revisión por Nuestro Equipo**
- Nuestro equipo recibe una notificación automática
- Revisaremos tu solicitud en menos de 24 horas
- Te contactaremos por email o teléfono

**Paso 3: Cotización y Contratación**
- Te enviaremos una cotización personalizada
- Si decides contratar, procesaremos tu solicitud
- Recibirás tu factura automáticamente

### Tiempos de Respuesta

| Acción | Tiempo |
|--------|--------|
| Confirmación en pantalla | Inmediato |
| Notificación a nuestro equipo | Inmediato |
| Revisión de tu solicitud | 2-24 horas |
| Respuesta con cotización | 24-48 horas |

---

## 3. Recibir tu Factura

### ¿Cuándo recibes la factura?

Una vez que decides contratar nuestros servicios y procesamos tu pago, recibirás un email automático con tu factura.

### Contenido del Email

**Asunto del email:**
```
Bienvenido a AutomatizaTech - Factura AT-20251116-XXXX - [Tu Nombre]
```

**Contenido:**

```
┌─────────────────────────────────────┐
│  🎉 ¡Bienvenido a AutomatizaTech!  │
├─────────────────────────────────────┤
│  Hola [Tu Nombre],                 │
│                                     │
│  Gracias por confiar en nosotros.  │
│  Tu servicio ha sido activado.     │
├─────────────────────────────────────┤
│  📋 Plan Contratado:               │
│  • [Nombre del Plan]               │
│  • Valor: $XXX.XXX (CLP/USD)       │
├─────────────────────────────────────┤
│  📎 FACTURA PDF ADJUNTA            │
│  Archivo: AT-20251116-XXXX.pdf     │
├─────────────────────────────────────┤
│  📞 Información de Contacto:       │
│  • Email: info@automatizatech.shop │
│  • Teléfono: +56 9 1234 5678       │
│  • Horario: Lun-Vie 9:00-18:00     │
└─────────────────────────────────────┘
```

### Factura PDF Adjunta

**Formato del archivo:**
- Nombre: `AT-YYYYMMDD-XXXX.pdf`
- Tamaño: Aproximadamente 50-100 KB
- Formato: PDF estándar (compatible con todos los lectores)

**Contenido de la factura:**

```
╔═══════════════════════════════════════╗
║        AUTOMATIZATECH                 ║
║        [Logo Corporativo]             ║
╠═══════════════════════════════════════╣
║  FACTURA                              ║
║  Nº: AT-20251116-XXXX                ║
║  Fecha: 16 de Noviembre de 2025      ║
╠═══════════════════════════════════════╣
║  DATOS DE LA EMPRESA                 ║
║  AutomatizaTech SpA                  ║
║  RUT: XX.XXX.XXX-X                   ║
║  Dirección: [Dirección completa]     ║
║  Email: info@automatizatech.shop     ║
╠═══════════════════════════════════════╣
║  DATOS DEL CLIENTE                   ║
║  Nombre: [Tu Nombre]                 ║
║  Email: [Tu Email]                   ║
║  Empresa: [Tu Empresa]               ║
║  Teléfono: [Tu Teléfono]             ║
║  País: [Tu País]                     ║
╠═══════════════════════════════════════╣
║  SERVICIOS CONTRATADOS               ║
║  ┌────────────┬─────┬──────────┐    ║
║  │ Servicio   │ Qty │ Precio   │    ║
║  ├────────────┼─────┼──────────┤    ║
║  │ Plan Pro   │  1  │ $XXX.XXX │    ║
║  └────────────┴─────┴──────────┘    ║
╠═══════════════════════════════════════╣
║  TOTALES                             ║
║                                       ║
║  CHILE (CLP):                        ║
║  Subtotal (Neto)    $XXX.XXX         ║
║  IVA (19%)          $XX.XXX          ║
║  ───────────────────────────         ║
║  TOTAL              $XXX.XXX         ║
║                                       ║
║  INTERNACIONAL (USD):                ║
║  TOTAL           USD $XXX.XX         ║
║  * Factura internacional             ║
║    No aplica IVA chileno             ║
╠═══════════════════════════════════════╣
║  TÉRMINOS Y CONDICIONES              ║
║  • Pago contra entrega               ║
║  • Garantía de 30 días               ║
║  • Soporte técnico incluido          ║
╠═══════════════════════════════════════╣
║  Gracias por su preferencia          ║
║  AutomatizaTech                      ║
║  Automatización Digital              ║
╚═══════════════════════════════════════╝
```

### ¿Por qué hay dos formatos de total?

**Si eres de Chile (código +56):**
- Verás el total en **Pesos Chilenos (CLP)**
- Con **IVA 19%** incluido y desglosado
- Formato: `$350.000` (sin decimales)

**Si eres de otro país:**
- Verás el total en **Dólares Americanos (USD)**
- **Sin IVA** (factura internacional)
- Formato: `USD $400.00` (con decimales)

### Qué hacer con tu factura

**1. Guardar el PDF**
- Descarga el archivo adjunto
- Guárdalo en un lugar seguro
- Puedes imprimirlo si necesitas una copia física

**2. Revisar los datos**
- Verifica que tu nombre esté correcto
- Revisa el monto y los servicios
- Confirma que la fecha sea correcta

**3. Si hay algún error**
- Responde al email que recibiste
- Indica qué dato está incorrecto
- Te enviaremos una factura corregida

**4. Para consultas**
- Email: info@automatizatech.shop
- Teléfono: +56 9 1234 5678
- Horario: Lunes a Viernes, 9:00 - 18:00

---

## ❓ Preguntas Frecuentes (Clientes)

### General

**P: ¿Cuánto demora en llegar la factura?**  
R: La factura llega automáticamente al email que registraste, inmediatamente después de que procesemos tu contratación. Si no la recibes en 10 minutos, revisa tu carpeta de spam.

**P: ¿Puedo solicitar otra copia de mi factura?**  
R: Sí, contáctanos por email o teléfono y te la reenviaremos.

**P: ¿La factura es válida para fines tributarios?**  
R: Sí, es una factura válida que incluye todos los datos requeridos por ley.

### Sobre Precios y Monedas

**P: ¿Por qué mi factura está en dólares si soy de Chile?**  
R: Verifica que hayas ingresado tu teléfono con el código correcto (+56 para Chile). Si el sistema no detecta correctamente tu país, puede asignar USD por defecto.

**P: ¿Puedo pagar en otra moneda?**  
R: Actualmente aceptamos pagos en CLP (Chile) y USD (internacional). Contáctanos para opciones especiales.

**P: ¿El precio incluye IVA?**  
R: Si eres de Chile, sí. El precio incluye IVA 19% y está desglosado en la factura. Si eres de otro país, no aplica IVA.

### Técnico

**P: No puedo abrir el PDF adjunto**  
R: Necesitas un lector de PDF como Adobe Reader, Foxit, o el visor de tu navegador. Descarga Adobe Reader gratis desde adobe.com.

**P: El archivo PDF es muy grande**  
R: El PDF debería pesar entre 50-100 KB. Si pesa más, podría estar dañado. Contáctanos para reenviarlo.

**P: ¿Puedo compartir mi factura?**  
R: Sí, puedes compartirla con tu contador o quien necesite revisar la información.

---

# PARTE 2: ADMINISTRADORES (BACK-END)

---

## 4. Acceso al Panel de Administración

### Iniciar Sesión

1. **Ir a la página de login:**
   ```
   https://automatizatech.shop/wp-admin
   ```

2. **Ingresar credenciales:**
   - Usuario: `tu-usuario-admin`
   - Contraseña: `tu-contraseña-segura`

3. **Clic en "Acceder"**

### Dashboard Principal

Una vez dentro, verás el dashboard de WordPress con las siguientes secciones relevantes:

```
Panel de WordPress
├── 📊 Dashboard (Vista general)
├── 👥 Contactos (Nueva sección)
│   ├── Todos los Contactos
│   └── Convertir a Cliente
├── 💳 Datos Facturación (Nueva sección)
│   └── Configuración de Empresa
├── 📄 Páginas
├── 🔧 Ajustes
└── 👤 Usuarios
```

---

## 5. Gestionar Contactos

### Ver Contactos Recibidos

**Ruta:** WordPress Admin → **Contactos** → **Todos los Contactos**

**Vista de lista:**

```
┌─────────────────────────────────────────────────────────────┐
│  CONTACTOS RECIBIDOS                                        │
├──────┬────────────┬──────────────┬─────────────┬──────────┤
│ ID   │ Nombre     │ Email        │ Teléfono    │ Fecha    │
├──────┼────────────┼──────────────┼─────────────┼──────────┤
│ #001 │ Juan Pérez │ juan@e.com   │ +56912345678│ 16/11/25 │
│ #002 │ María G.   │ maria@e.com  │ +1234567890 │ 15/11/25 │
│ #003 │ Pedro S.   │ pedro@e.com  │ +54987654321│ 14/11/25 │
└──────┴────────────┴──────────────┴─────────────┴──────────┘
```

### Detalles de un Contacto

Clic en cualquier contacto para ver los detalles completos:

```
╔═══════════════════════════════════════╗
║  DETALLES DEL CONTACTO #001          ║
╠═══════════════════════════════════════╣
║  👤 Nombre: Juan Pérez               ║
║  📧 Email: juan@ejemplo.com          ║
║  🏢 Empresa: Empresa Demo SpA        ║
║  📱 Teléfono: +56912345678           ║
║  🌎 País: Chile (detectado)          ║
║  📅 Fecha: 16/11/2025 10:30          ║
╠═══════════════════════════════════════╣
║  💬 Mensaje:                         ║
║  "Necesito automatizar mi sistema    ║
║   de facturación..."                 ║
╠═══════════════════════════════════════╣
║  [Convertir a Cliente] [Eliminar]    ║
╚═══════════════════════════════════════╝
```

### Acciones Disponibles

| Acción | Descripción |
|--------|-------------|
| **Ver** | Ver detalles completos del contacto |
| **Convertir a Cliente** | Procesar contratación y generar factura |
| **Editar** | Modificar datos del contacto |
| **Eliminar** | Borrar contacto permanentemente |

---

## 6. Convertir Contactos en Clientes

Este es el proceso más importante del sistema.

### Paso 1: Seleccionar Contacto

1. Ve a **Contactos** → **Todos los Contactos**
2. Clic en el contacto que quieres convertir
3. Clic en el botón **"Convertir a Cliente"**

### Paso 2: Seleccionar Plan/Servicio

Se abrirá un modal o pantalla con:

```
╔═══════════════════════════════════════╗
║  CONVERTIR A CLIENTE                 ║
║  Contacto: Juan Pérez                ║
╠═══════════════════════════════════════╣
║  Selecciona el plan contratado:      ║
║                                       ║
║  ○ Plan Básico          $150.000     ║
║  ○ Plan Profesional     $350.000     ║
║  ○ Plan Empresarial     $650.000     ║
║  ○ Plan Premium         $950.000     ║
║                                       ║
║  País detectado: 🇨🇱 Chile           ║
║  Moneda: CLP (Pesos Chilenos)        ║
║  IVA: 19% (incluido)                 ║
╠═══════════════════════════════════════╣
║  [Cancelar]    [Confirmar Conversión]║
╚═══════════════════════════════════════╝
```

### Paso 3: Confirmar Conversión

1. Selecciona el plan contratado
2. Revisa que los datos sean correctos
3. Clic en **"Confirmar Conversión"**

### Paso 4: Proceso Automático

El sistema ejecutará automáticamente:

```
⏳ Procesando...

✅ 1. Cliente registrado en base de datos
✅ 2. País detectado: Chile (CL)
✅ 3. Moneda configurada: CLP
✅ 4. Factura PDF generada: AT-20251116-0001.pdf
✅ 5. Email enviado al cliente (con PDF adjunto)
✅ 6. Email de notificación enviado al equipo
✅ 7. Contacto eliminado de lista de pendientes

🎉 ¡Conversión completada exitosamente!
```

### Paso 5: Verificación

**Emails enviados:**

1. **Email al cliente:**
   - Destinatario: `juan@ejemplo.com`
   - Asunto: "Bienvenido a AutomatizaTech - Factura AT-20251116-0001"
   - Adjunto: Factura PDF

2. **Email al equipo:**
   - Destinatario: `automatizatech.bots@gmail.com`
   - Asunto: "🎉 ¡Nuevo Cliente Contratado! - Juan Pérez"
   - Contenido: Resumen completo del cliente y contrato

**Archivos generados:**
- PDF guardado en: `/wp-content/uploads/invoices/AT-20251116-0001.pdf`
- Registro en base de datos: Tabla `wp_automatiza_tech_invoices`

---

## 7. Configurar Datos de Facturación

### Acceder al Panel de Configuración

**Ruta:** WordPress Admin → **Datos Facturación**

Este panel te permite configurar los datos de tu empresa que aparecerán en todas las facturas.

### Campos Configurables

```
╔═══════════════════════════════════════╗
║  ⚙️ CONFIGURACIÓN DE FACTURACIÓN     ║
╠═══════════════════════════════════════╣
║  🏢 Nombre de la Empresa             ║
║  ┌─────────────────────────────────┐ ║
║  │ AutomatizaTech SpA              │ ║
║  └─────────────────────────────────┘ ║
║                                       ║
║  🆔 RUT                               ║
║  ┌─────────────────────────────────┐ ║
║  │ 76.123.456-7                    │ ║
║  └─────────────────────────────────┘ ║
║                                       ║
║  💼 Giro                              ║
║  ┌─────────────────────────────────┐ ║
║  │ Servicios de Automatización     │ ║
║  └─────────────────────────────────┘ ║
║                                       ║
║  📍 Dirección                         ║
║  ┌─────────────────────────────────┐ ║
║  │ Av. Providencia 1234, Of. 567   │ ║
║  │ Santiago, Chile                 │ ║
║  └─────────────────────────────────┘ ║
║                                       ║
║  📧 Email                             ║
║  ┌─────────────────────────────────┐ ║
║  │ info@automatizatech.shop        │ ║
║  └─────────────────────────────────┘ ║
║                                       ║
║  📞 Teléfono                          ║
║  ┌─────────────────────────────────┐ ║
║  │ +56 9 1234 5678                 │ ║
║  └─────────────────────────────────┘ ║
║                                       ║
║  🌐 Sitio Web                         ║
║  ┌─────────────────────────────────┐ ║
║  │ https://automatizatech.shop     │ ║
║  └─────────────────────────────────┘ ║
╠═══════════════════════════════════════╣
║  [Guardar Cambios]                   ║
╚═══════════════════════════════════════╝
```

### Vista Previa

Debajo del formulario verás una vista previa en tiempo real de cómo se verán estos datos en las facturas:

```
┌─────────────────────────────────────┐
│  📄 VISTA PREVIA DE FACTURA        │
├─────────────────────────────────────┤
│  AutomatizaTech SpA                │
│  RUT: 76.123.456-7                 │
│  Servicios de Automatización       │
│  ─────────────────────────────     │
│  📍 Av. Providencia 1234, Of. 567  │
│     Santiago, Chile                │
│  📧 info@automatizatech.shop       │
│  📞 +56 9 1234 5678                │
│  🌐 https://automatizatech.shop    │
└─────────────────────────────────────┘
```

### Guardar Cambios

1. Completa todos los campos
2. Revisa la vista previa
3. Clic en **"Guardar Cambios"**
4. Verás mensaje de confirmación:

```
✅ Configuración guardada correctamente.
Los cambios se aplicarán en las próximas facturas.
```

### Notas Importantes

⚠️ **Los cambios NO afectan facturas ya generadas**
- Solo se aplican a facturas nuevas
- Las facturas anteriores mantienen los datos con los que fueron generadas

⚠️ **Todos los campos son obligatorios**
- El sistema validará que no dejes campos vacíos
- El email debe tener formato válido

⚠️ **Backup automático**
- Los datos se guardan en la base de datos
- Puedes cambiarlos las veces que necesites

---

## 8. Gestionar Servicios y Planes

### Ver Servicios Existentes

Los servicios se gestionan directamente en la base de datos.

**Tabla:** `wp_automatiza_services`

**Estructura:**
```
┌────┬──────────────────┬───────────┬───────────┬────────┐
│ ID │ Nombre           │ Precio CLP│ Precio USD│ Estado │
├────┼──────────────────┼───────────┼───────────┼────────┤
│ 1  │ Plan Básico      │ 150.000   │ 180.00    │ Activo │
│ 2  │ Plan Profesional │ 350.000   │ 400.00    │ Activo │
│ 3  │ Plan Empresarial │ 650.000   │ 750.00    │ Activo │
│ 4  │ Plan Premium     │ 950.000   │ 1.100.00  │ Activo │
└────┴──────────────────┴───────────┴───────────┴────────┘
```

### Acceder a la Base de Datos

**Opción 1: phpMyAdmin**
1. Accede a tu panel de hosting (cPanel, Plesk, etc.)
2. Abre phpMyAdmin
3. Selecciona tu base de datos
4. Busca la tabla `wp_automatiza_services`

**Opción 2: MySQL CLI**
```bash
mysql -u usuario -p nombre_bd
```

### Agregar Nuevo Servicio

```sql
INSERT INTO wp_automatiza_services 
(name, description, price_clp, price_usd, status)
VALUES 
('Plan Custom', 'Plan personalizado', 1200000, 1400, 'active');
```

### Actualizar Precios

**Actualizar un servicio específico:**
```sql
UPDATE wp_automatiza_services 
SET price_clp = 400000, price_usd = 450
WHERE id = 2;
```

**Actualizar todos los precios USD (conversión):**
```sql
-- Usando tasa de cambio 950 CLP = 1 USD
UPDATE wp_automatiza_services 
SET price_usd = ROUND(price_clp / 950, 2);
```

### Desactivar un Servicio

```sql
UPDATE wp_automatiza_services 
SET status = 'inactive'
WHERE id = 3;
```

### Reactivar un Servicio

```sql
UPDATE wp_automatiza_services 
SET status = 'active'
WHERE id = 3;
```

---

## 9. Revisar Facturas Generadas

### Acceso a Facturas

**Opción 1: Servidor (FTP/SFTP)**

Ruta: `/wp-content/uploads/invoices/`

```
invoices/
├── AT-20251116-0001.pdf
├── AT-20251116-0002.pdf
├── AT-20251115-0003.pdf
└── AT-20251114-0004.pdf
```

**Opción 2: Base de Datos**

```sql
SELECT * FROM wp_automatiza_tech_invoices 
ORDER BY created_at DESC 
LIMIT 10;
```

### Consultas Útiles

**Facturas generadas hoy:**
```sql
SELECT 
    invoice_number,
    client_id,
    total_amount,
    currency,
    created_at
FROM wp_automatiza_tech_invoices 
WHERE DATE(created_at) = CURDATE();
```

**Facturas por mes:**
```sql
SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as mes,
    COUNT(*) as total_facturas,
    SUM(total_amount) as monto_total,
    currency
FROM wp_automatiza_tech_invoices 
GROUP BY mes, currency
ORDER BY mes DESC;
```

**Facturas por cliente:**
```sql
SELECT 
    c.name as cliente,
    i.invoice_number,
    i.total_amount,
    i.currency,
    i.created_at
FROM wp_automatiza_tech_invoices i
JOIN wp_automatiza_tech_clients c ON i.client_id = c.id
WHERE c.id = 1;
```

### Descargar una Factura

**Por FTP:**
1. Conecta por FTP a tu servidor
2. Navega a `/wp-content/uploads/invoices/`
3. Descarga el archivo PDF deseado

**Por phpMyAdmin:**
1. Busca el registro en `wp_automatiza_tech_invoices`
2. Copia el valor del campo `pdf_path`
3. Descarga el archivo desde esa ruta

### Reenviar una Factura

**Manualmente:**
1. Descarga el PDF desde el servidor
2. Abre tu cliente de email
3. Redacta email al cliente
4. Adjunta el PDF
5. Envía

**Nota:** Actualmente no hay función automática de reenvío desde el panel.

---

## 10. Sistema de Notificaciones

### Emails que Envía el Sistema

El sistema envía 3 tipos de emails automáticos:

#### 1. Notificación de Nuevo Contacto

**Cuándo:** Alguien llena el formulario de contacto

**Destinatario:** automatizatech.bots@gmail.com

**Asunto:** 📧 Nuevo contacto desde Automatiza Tech - [Nombre]

**Contenido:**
- Datos completos del contacto
- Botón para ir al panel admin
- Footer corporativo

**Qué hacer:**
1. Revisa el email en tu bandeja
2. Accede al panel de administración
3. Contacta al cliente para enviar cotización
4. Cuando cierre la venta, convierte a cliente

---

#### 2. Factura al Cliente

**Cuándo:** Conviertes un contacto en cliente

**Destinatario:** Email del cliente

**Asunto:** Bienvenido a AutomatizaTech - Factura AT-XXXXXXXX-XXXX - [Nombre]

**Contenido:**
- Mensaje de bienvenida personalizado
- Plan contratado
- **Factura PDF adjunta**
- Información de contacto
- Próximos pasos

**Qué hacer:**
- El cliente lo recibe automáticamente
- Verifica que llegó correctamente
- Si el cliente no lo recibe, reenvía manualmente

---

#### 3. Notificación Interna de Venta

**Cuándo:** Inmediatamente después de convertir a cliente

**Destinatario:** automatizatech.bots@gmail.com

**Asunto:** 🎉 ¡Nuevo Cliente Contratado! - [Nombre] - Plan: [Plan]

**Contenido:**
- Información completa del cliente
- Detalles del contrato
- Monto y moneda
- Estado de la factura
- Botones de acceso rápido

**Qué hacer:**
1. Confirma que todo se procesó correctamente
2. Actualiza tus registros internos
3. Agenda seguimiento con el cliente

---

### Revisar Bandeja de Notificaciones

**Gmail:**
1. Accede a: `automatizatech.bots@gmail.com`
2. Busca emails con:
   - Asunto: "Nuevo contacto"
   - Asunto: "Nuevo Cliente Contratado"

**Configurar filtros:**
```
De: noreply@automatizatech.shop
Asunto: (Nuevo contacto|Nuevo Cliente)
→ Etiquetar como: "Sistema AutomatizaTech"
→ Marcar como importante
```

---

## 📊 Panel de Estadísticas

### Consultas Útiles para Reportes

**Clientes por país (este mes):**
```sql
SELECT 
    country,
    COUNT(*) as total_clientes
FROM wp_automatiza_tech_clients 
WHERE MONTH(created_at) = MONTH(CURRENT_DATE)
GROUP BY country;
```

**Ingresos por moneda (este mes):**
```sql
SELECT 
    currency,
    COUNT(*) as num_facturas,
    SUM(total_amount) as total_ingresos
FROM wp_automatiza_tech_invoices 
WHERE MONTH(created_at) = MONTH(CURRENT_DATE)
GROUP BY currency;
```

**Servicios más vendidos:**
```sql
SELECT 
    s.name,
    COUNT(i.id) as veces_vendido,
    SUM(i.total_amount) as ingresos_totales
FROM wp_automatiza_services s
LEFT JOIN wp_automatiza_tech_invoices i ON s.id = i.plan_id
GROUP BY s.id
ORDER BY veces_vendido DESC;
```

**Conversión de contactos a clientes:**
```sql
-- Total contactos recibidos este mes
SELECT COUNT(*) FROM wp_automatiza_tech_contacts
WHERE MONTH(created_at) = MONTH(CURRENT_DATE);

-- Total clientes convertidos este mes
SELECT COUNT(*) FROM wp_automatiza_tech_clients
WHERE MONTH(created_at) = MONTH(CURRENT_DATE);

-- Tasa de conversión
SELECT 
    (SELECT COUNT(*) FROM wp_automatiza_tech_clients WHERE MONTH(created_at) = MONTH(CURRENT_DATE)) * 100.0 /
    (SELECT COUNT(*) FROM wp_automatiza_tech_contacts WHERE MONTH(created_at) = MONTH(CURRENT_DATE)) 
    as tasa_conversion_porcentaje;
```

---

## 🔧 Mantenimiento para Administradores

### Tareas Diarias

**1. Revisar contactos nuevos**
- Tiempo: 5-10 minutos
- Frecuencia: 2-3 veces al día
- Acción: Responder y procesar

**2. Verificar emails recibidos**
- Revisar bandeja de automatizatech.bots@gmail.com
- Confirmar que las notificaciones lleguen

**3. Atender consultas de clientes**
- Revisar emails de clientes
- Responder dudas sobre facturas

### Tareas Semanales

**1. Revisar facturas generadas**
```bash
# Conectar por FTP y contar facturas de la semana
ls /wp-content/uploads/invoices/AT-$(date +%Y%m)* | wc -l
```

**2. Estadísticas de contactos**
```sql
SELECT 
    DATE(created_at) as fecha,
    COUNT(*) as contactos
FROM wp_automatiza_tech_contacts
WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
GROUP BY DATE(created_at);
```

**3. Actualizar precios si es necesario**
- Revisar tasas de cambio CLP/USD
- Actualizar precios en servicios si hay cambios significativos

### Tareas Mensuales

**1. Backup de facturas**
```bash
# Comprimir facturas del mes
cd /wp-content/uploads/invoices
tar -czf invoices-backup-$(date +%Y%m).tar.gz AT-$(date +%Y%m)*.pdf
```

**2. Reporte de ventas**
```sql
SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as mes,
    currency,
    COUNT(*) as facturas,
    SUM(total_amount) as total
FROM wp_automatiza_tech_invoices
WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)
GROUP BY mes, currency;
```

**3. Limpieza de logs**
```bash
# Backup y limpiar logs
cp wp-content/debug.log wp-content/debug-backup-$(date +%Y%m%d).log
> wp-content/debug.log
```

---

## ❓ Preguntas Frecuentes (Administradores)

### Gestión de Contactos

**P: ¿Puedo editar un contacto antes de convertirlo?**  
R: Sí, en la vista de detalles del contacto hay un botón "Editar".

**P: ¿Qué pasa si convierto un contacto por error?**  
R: La factura ya fue generada y enviada. Debes contactar al cliente y explicar la situación. Puedes generar una nota de crédito manualmente si es necesario.

**P: ¿Los contactos se eliminan automáticamente?**  
R: Sí, cuando los conviertes en clientes. Los contactos que NO se convierten permanecen en la lista indefinidamente.

### Facturas

**P: ¿Puedo editar una factura después de generada?**  
R: No. Las facturas son inmutables por razones legales. Si hay un error, debes generar una nueva.

**P: ¿Cómo puedo reenviar una factura?**  
R: Descarga el PDF desde `/wp-content/uploads/invoices/` y envíalo manualmente por email.

**P: ¿Se pueden eliminar facturas?**  
R: No es recomendable por temas legales y de auditoría. Si es absolutamente necesario, hazlo desde la base de datos, pero mantén un backup.

### Configuración

**P: ¿Los cambios en "Datos Facturación" afectan facturas anteriores?**  
R: No, solo afectan las facturas nuevas.

**P: ¿Puedo cambiar el formato de numeración de facturas?**  
R: Requiere modificación de código en `lib/invoice-pdf-fpdf.php`.

**P: ¿Cómo cambio el logo en las facturas?**  
R: Modifica el archivo `lib/invoice-pdf-fpdf.php` en la sección donde se dibuja el logo.

### Emails

**P: ¿Por qué los emails van a spam?**  
R: Verifica la configuración SMTP en `inc/contact-form.php`. Asegúrate de que el dominio tenga registros SPF y DKIM configurados.

**P: ¿Puedo cambiar el diseño de los emails?**  
R: Sí, editando los métodos de generación de HTML en `inc/contact-form.php`.

**P: ¿Los emails se guardan en algún lado?**  
R: No, solo se envían. Si falla el envío, se registra en el log (`wp-content/debug.log`).

---

## 📞 Soporte

### Para Clientes
- **Email:** info@automatizatech.shop
- **Teléfono:** +56 9 1234 5678
- **Horario:** Lunes a Viernes, 9:00 - 18:00

### Para Administradores
- **Documentación técnica:** README.md (en el repositorio)
- **Logs del sistema:** `/wp-content/debug.log`
- **Soporte técnico:** Contacta al equipo de desarrollo

---

**Fin del Manual de Usuario**

**Versión:** 2.0  
**Última actualización:** Noviembre 2025  
**Mantenido por:** AutomatizaTech Development Team
