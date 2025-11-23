# 📘 AutomatizaTech - Sistema de Facturación Multi-Moneda v2.0

**Documentación Oficial Completa**  
**Última actualización:** Noviembre 2025  
**Versión:** 2.0  
**Estado:** ✅ Producción

---

## 📋 Tabla de Contenidos

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Funcionalidades Implementadas](#funcionalidades-implementadas)
3. [Arquitectura del Sistema](#arquitectura-del-sistema)
4. [Guía de Instalación](#guía-de-instalación)
5. [Configuración](#configuración)
6. [Uso del Sistema](#uso-del-sistema)
7. [Desarrollo y Código](#desarrollo-y-código)
8. [Troubleshooting](#troubleshooting)
9. [Mantenimiento](#mantenimiento)
10. [FAQ](#faq)

---

## 🎯 Resumen Ejecutivo

### Objetivo
Sistema completo de facturación automatizada con soporte multi-moneda, generación de PDFs profesionales, envío automático de emails y panel de administración integrado en WordPress.

### Características Principales

**Sistema Multi-Moneda**
- Soporte para 18 países
- Chile: Pesos Chilenos (CLP) con IVA 19%
- Internacional: Dólares (USD) sin IVA
- Detección automática de país por código telefónico

**Emails Automáticos**
- Notificación interna al recibir contacto
- Factura PDF enviada automáticamente al cliente
- Notificación interna de cliente contratado

**Panel de Administración**
- Configuración de datos de empresa sin tocar código
- Vista previa en tiempo real
- Integrado en WordPress Admin

**Facturas PDF Profesionales**
- Generadas con FPDF (sin dependencias externas)
- Diseño corporativo con gradientes
- Numeración única: AT-YYYYMMDD-XXXX
- Cálculos automáticos según país

### Beneficios Clave

- ⏱️ **Ahorro de tiempo:** ~15 minutos por cliente
- 🌎 **Expansión internacional:** 18 países soportados
- ⚖️ **Cumplimiento legal:** IVA correcto según país
- 🤖 **Automatización:** Cero intervención manual
- 💼 **Profesionalismo:** Imagen corporativa mejorada

---

## ✨ Funcionalidades Implementadas

### 1. Sistema Multi-Moneda

#### Países Soportados (18)

| País | Código | Moneda | IVA |
|------|--------|--------|-----|
| 🇨🇱 Chile | +56 | CLP | 19% |
| 🇺🇸 Estados Unidos | +1 | USD | No |
| 🇦🇷 Argentina | +54 | USD | No |
| 🇨🇴 Colombia | +57 | USD | No |
| 🇲🇽 México | +52 | USD | No |
| 🇵🇪 Perú | +51 | USD | No |
| 🇪🇸 España | +34 | USD | No |
| 🇧🇷 Brasil | +55 | USD | No |
| 🇪🇨 Ecuador | +593 | USD | No |
| 🇵🇾 Paraguay | +595 | USD | No |
| 🇺🇾 Uruguay | +598 | USD | No |
| 🇻🇪 Venezuela | +58 | USD | No |
| 🇨🇷 Costa Rica | +506 | USD | No |
| 🇵🇦 Panamá | +507 | USD | No |
| 🇸🇻 El Salvador | +503 | USD | No |
| 🇭🇳 Honduras | +504 | USD | No |
| 🇳🇮 Nicaragua | +505 | USD | No |
| 🇬🇹 Guatemala | +502 | USD | No |

#### Detección Automática de País

El sistema detecta el país mediante 3 métodos (en orden de prioridad):

1. **Campo `country` en Base de Datos**
   - Si el cliente ya tiene país asignado

2. **Código Telefónico** (Automático)
   ```php
   +56912345678 → Chile (CL)
   +1234567890 → Estados Unidos (US)
   +54987654321 → Argentina (AR)
   ```

3. **Valor por Defecto**
   - Chile (CL) si no se puede determinar

#### Formato de Precios

**Chile (CLP):**
```
Subtotal (Neto):  $336.135
IVA (19%):        $ 63.865
─────────────────────────
TOTAL:            $400.000
```

**Internacional (USD):**
```
TOTAL:            USD $500.00

* Factura internacional
  No aplica IVA chileno
```

---

### 2. Sistema de Emails Automáticos

#### Email #1: Notificación Interna de Contacto

**Trigger:** Usuario llena formulario de contacto

**Destinatario:** automatizatech.bots@gmail.com

**Contenido:**
- Header con logo corporativo
- Datos completos del contacto
- Botón para acceder al panel admin
- Footer con información de la empresa

**Asunto:** 
```
📧 Nuevo contacto desde Automatiza Tech - [Nombre]
```

---

#### Email #2: Factura al Cliente

**Trigger:** Contacto convertido a cliente

**Destinatario:** Email del cliente

**Contenido:**
- Mensaje de bienvenida personalizado
- Plan contratado destacado
- **Factura PDF adjunta**
- Detalles de la factura
- Próximos pasos
- Información de contacto y soporte

**Asunto:**
```
Bienvenido a AutomatizaTech - Factura AT-YYYYMMDD-XXXX - [Nombre]
```

**Archivos adjuntos:**
- PDF de factura profesional

---

#### Email #3: Notificación Interna de Cliente Contratado

**Trigger:** Después de convertir contacto a cliente

**Destinatario:** automatizatech.bots@gmail.com

**Contenido:**
- Información completa del cliente
- Detalles del contrato (plan, precio, moneda)
- Estado de la factura
- Botones de acciones rápidas

**Asunto:**
```
🎉 ¡Nuevo Cliente Contratado! - [Nombre] - Plan: [Plan]
```

---

#### Configuración SMTP

**Servidor:** Gmail SMTP
```php
Host: smtp.gmail.com
Port: 587
Security: TLS
Auth: Yes
```

**Credenciales:**
- Usuario: automatizatech.bots@gmail.com
- Password: Contraseña de aplicación de Gmail

**From:**
- Email: noreply@automatizatech.shop
- Nombre: AutomatizaTech

---

### 3. Panel de Administración

#### Acceso
```
WordPress Admin → Menú "Datos Facturación"
URL: /wp-admin/admin.php?page=automatiza-invoice-settings
```

#### Campos Configurables

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **Nombre Empresa** | Razón social | AutomatizaTech SpA |
| **RUT** | Rol Único Tributario | 76.123.456-7 |
| **Giro** | Actividad comercial | Servicios de Automatización Digital |
| **Dirección** | Dirección completa | Av. Providencia 1234, Of. 567 |
| **Email** | Email de contacto | info@automatizatech.shop |
| **Teléfono** | Teléfono | +56 9 1234 5678 |
| **Sitio Web** | URL | https://automatizatech.shop |

#### Características

- ✅ Validación de campos
- ✅ Vista previa en tiempo real
- ✅ Guardado en wp_options
- ✅ Los cambios se aplican inmediatamente
- ✅ Interfaz responsive
- ✅ Diseño moderno con gradientes

---

### 4. Facturas PDF

#### Tecnología: FPDF

**Ventajas:**
- 100% PHP puro
- Sin dependencias externas
- Funciona en cualquier servidor
- Soporte UTF-8
- Ligero y rápido

#### Estructura de la Factura

```
┌───────────────────────────────────────┐
│  AUTOMATIZATECH [Logo]               │
├───────────────────────────────────────┤
│  FACTURA Nº: AT-20251111-0001        │
│  Fecha: 11 de Noviembre de 2025      │
├───────────────────────────────────────┤
│  DATOS DE LA EMPRESA                 │
│  [Desde panel de configuración]      │
├───────────────────────────────────────┤
│  DATOS DEL CLIENTE                   │
│  Nombre, Email, Empresa, Teléfono    │
│  País: 🇨🇱 Chile                     │
├───────────────────────────────────────┤
│  DETALLE DE SERVICIOS                │
│  [Tabla con servicios contratados]   │
├───────────────────────────────────────┤
│  TOTALES                             │
│  [Subtotal, IVA si aplica, Total]    │
├───────────────────────────────────────┤
│  TÉRMINOS Y CONDICIONES              │
│  Gracias por su preferencia          │
└───────────────────────────────────────┘
```

#### Numeración

```
Formato: AT-YYYYMMDD-XXXX

AT: Prefijo AutomatizaTech
YYYY: Año (2025)
MM: Mes (01-12)
DD: Día (01-31)
XXXX: ID del cliente (4 dígitos)

Ejemplos:
- AT-20251111-0001.pdf
- AT-20251215-0123.pdf
```

#### Almacenamiento

**Archivo físico:**
```
/wp-content/uploads/invoices/
├── AT-20251111-0001.pdf
├── AT-20251111-0002.pdf
└── AT-20251112-0003.pdf
```

**Base de datos:**
```sql
Tabla: wp_automatiza_tech_invoices
- id
- client_id
- invoice_number
- plan_id
- total_amount
- currency
- invoice_html (backup)
- pdf_path
- created_at
```

---

## 🏗️ Arquitectura del Sistema

### Flujo Completo

```
1. Usuario llena formulario
   ↓
2. Validación (anti-spam, rate limit)
   ↓
3. Guardar en BD (wp_automatiza_tech_contacts)
   ↓
4. EMAIL #1: Notificación interna
   ↓
5. Admin revisa contacto
   ↓
6. Admin clic "Convertir a Cliente"
   ↓
7. Detectar país (por código telefónico)
   ↓
8. Configurar moneda (CLP o USD)
   ↓
9. Insertar cliente en BD (con campo country)
   ↓
10. Generar factura PDF con FPDF
    ↓
11. Guardar PDF en /uploads/invoices/
    ↓
12. Registrar en BD (wp_automatiza_tech_invoices)
    ↓
13. EMAIL #2: Factura al cliente (PDF adjunto)
    ↓
14. EMAIL #3: Notificación interna de venta
    ↓
15. Eliminar de tabla contactos
    ↓
16. Log completo de operaciones
    ↓
17. ✅ Proceso completado
```

### Archivos del Sistema

```
wp-content/themes/[tema]/
├── functions.php                    (require modules)
├── inc/
│   ├── contact-form.php            (emails, detección, conversión)
│   └── invoice-settings.php        (panel admin)
└── lib/
    ├── invoice-pdf-fpdf.php        (generación PDF, multi-moneda)
    └── fpdf/
        └── fpdf.php                (librería FPDF)
```

### Base de Datos

#### Tabla: wp_automatiza_tech_clients

```sql
CREATE TABLE wp_automatiza_tech_clients (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  email varchar(255) NOT NULL,
  company varchar(255),
  phone varchar(50),
  country varchar(2) DEFAULT 'CL',  -- ← NUEVO
  plan_id int(11),
  status enum('active','inactive') DEFAULT 'active',
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);
```

#### Tabla: wp_automatiza_tech_invoices

```sql
CREATE TABLE wp_automatiza_tech_invoices (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  client_id bigint(20) NOT NULL,
  invoice_number varchar(50) NOT NULL,
  plan_id int(11),
  total_amount decimal(10,2),
  currency varchar(3),
  invoice_html text,
  pdf_path varchar(255),
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (client_id) REFERENCES wp_automatiza_tech_clients(id)
);
```

#### Tabla: wp_automatiza_services

```sql
CREATE TABLE wp_automatiza_services (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  description text,
  price_clp decimal(10,2),     -- Precio en pesos chilenos
  price_usd decimal(10,2),     -- Precio en dólares
  status enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (id)
);
```

#### Opciones en wp_options

```
company_name: AutomatizaTech SpA
company_rut: 76.123.456-7
company_giro: Servicios de Automatización Digital
company_address: Av. Providencia 1234, Of. 567, Santiago
company_email: info@automatizatech.shop
company_phone: +56 9 1234 5678
company_website: https://automatizatech.shop
```

---

## 🚀 Guía de Instalación

### Prerrequisitos

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+
- Acceso FTP/SFTP o cPanel
- Acceso a phpMyAdmin o MySQL CLI

### Paso 1: Backup Completo

```bash
# Backup de Base de Datos
mysqldump -u usuario -p nombre_bd > backup-$(date +%Y%m%d).sql

# Backup de Archivos
tar -czf backup-archivos-$(date +%Y%m%d).tar.gz wp-content/
```

### Paso 2: Subir Archivos PHP

**Archivos a subir:**

1. `wp-content/themes/[tu-tema]/inc/invoice-settings.php` (NUEVO)
2. `wp-content/themes/[tu-tema]/inc/contact-form.php` (MODIFICADO)
3. `wp-content/themes/[tu-tema]/lib/invoice-pdf-fpdf.php` (MODIFICADO)
4. `wp-content/themes/[tu-tema]/functions.php` (MODIFICADO)

**Vía FTP:**
```
- Conectar a servidor FTP
- Navegar a /wp-content/themes/[tu-tema]/
- Subir archivos manteniendo estructura
```

**Vía SSH:**
```bash
scp inc/invoice-settings.php usuario@servidor:/ruta/wp-content/themes/tema/inc/
scp inc/contact-form.php usuario@servidor:/ruta/wp-content/themes/tema/inc/
scp lib/invoice-pdf-fpdf.php usuario@servidor:/ruta/wp-content/themes/tema/lib/
scp functions.php usuario@servidor:/ruta/wp-content/themes/tema/
```

### Paso 3: Ejecutar Migración SQL

**Opción A: phpMyAdmin**
1. Acceder a phpMyAdmin
2. Seleccionar base de datos
3. Pestaña "SQL"
4. Copiar y pegar contenido de `migration-production-multi-currency.sql`
5. Clic en "Continuar"

**Opción B: MySQL CLI**
```bash
mysql -u usuario -p nombre_bd < sql/migration-production-multi-currency.sql
```

**Script SQL:**
```sql
-- Verificar si columna country existe
SET @dbname = DATABASE();
SET @tablename = 'wp_automatiza_tech_clients';
SET @columnname = 'country';

SET @query = CONCAT('SELECT COUNT(*) INTO @exist FROM information_schema.columns WHERE table_schema = "', @dbname, '" AND table_name = "', @tablename, '" AND column_name = "', @columnname, '"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar columna si no existe
SET @query = IF(@exist = 0, 
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(2) DEFAULT "CL" COMMENT "Código ISO de 2 letras del país" AFTER phone'),
  'SELECT "Columna country ya existe" AS resultado'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Asignar países por código telefónico
UPDATE wp_automatiza_tech_clients SET country = 'CL' WHERE phone LIKE '+56%';
UPDATE wp_automatiza_tech_clients SET country = 'US' WHERE phone LIKE '+1%';
UPDATE wp_automatiza_tech_clients SET country = 'AR' WHERE phone LIKE '+54%';
UPDATE wp_automatiza_tech_clients SET country = 'CO' WHERE phone LIKE '+57%';
UPDATE wp_automatiza_tech_clients SET country = 'MX' WHERE phone LIKE '+52%';
UPDATE wp_automatiza_tech_clients SET country = 'PE' WHERE phone LIKE '+51%';
UPDATE wp_automatiza_tech_clients SET country = 'ES' WHERE phone LIKE '+34%';
UPDATE wp_automatiza_tech_clients SET country = 'BR' WHERE phone LIKE '+55%';
UPDATE wp_automatiza_tech_clients SET country = 'EC' WHERE phone LIKE '+593%';
UPDATE wp_automatiza_tech_clients SET country = 'PY' WHERE phone LIKE '+595%';
UPDATE wp_automatiza_tech_clients SET country = 'UY' WHERE phone LIKE '+598%';
UPDATE wp_automatiza_tech_clients SET country = 'VE' WHERE phone LIKE '+58%';
UPDATE wp_automatiza_tech_clients SET country = 'CR' WHERE phone LIKE '+506%';
UPDATE wp_automatiza_tech_clients SET country = 'PA' WHERE phone LIKE '+507%';
UPDATE wp_automatiza_tech_clients SET country = 'SV' WHERE phone LIKE '+503%';
UPDATE wp_automatiza_tech_clients SET country = 'HN' WHERE phone LIKE '+504%';
UPDATE wp_automatiza_tech_clients SET country = 'NI' WHERE phone LIKE '+505%';
UPDATE wp_automatiza_tech_clients SET country = 'GT' WHERE phone LIKE '+502%';

-- Asegurar que todos tengan país
UPDATE wp_automatiza_tech_clients SET country = 'CL' WHERE country IS NULL OR country = '';

-- Verificaciones
SELECT country, COUNT(*) as total FROM wp_automatiza_tech_clients GROUP BY country;
```

### Paso 4: Verificar Precios USD en Servicios

```sql
-- Ver servicios sin precio USD
SELECT id, name, price_clp, price_usd 
FROM wp_automatiza_services 
WHERE price_usd IS NULL OR price_usd = 0;

-- Actualizar precios USD (ejemplo)
UPDATE wp_automatiza_services 
SET price_usd = ROUND(price_clp / 950, 2)
WHERE price_usd IS NULL OR price_usd = 0;
```

### Paso 5: Configurar Datos de Empresa

1. Ir a WordPress Admin
2. Menú "Datos Facturación"
3. Completar todos los campos
4. Clic en "Guardar Cambios"

### Paso 6: Pruebas

#### Prueba 1: Formulario de Contacto
```
1. Llenar formulario con datos de prueba
2. Verificar email recibido
3. Revisar contacto en panel admin
```

#### Prueba 2: Conversión a Cliente
```
1. Convertir contacto de prueba
2. Verificar factura PDF generada
3. Verificar email al cliente
4. Verificar email de notificación interna
5. Revisar PDF en /wp-content/uploads/invoices/
```

#### Prueba 3: Multi-Moneda
```
1. Crear contacto con teléfono chileno (+56...)
   → Debe generar factura en CLP con IVA

2. Crear contacto con teléfono internacional (+1...)
   → Debe generar factura en USD sin IVA
```

### Paso 7: Limpieza

```bash
# Eliminar archivos de test
rm test-*.php
rm debug-*.php
rm verify-*.php

# Eliminar documentación (excepto README.md)
rm *.md
mv README.md ../

# Mantener solo archivos funcionales
```

### Paso 8: Monitoreo

```bash
# Revisar logs
tail -f wp-content/debug.log

# Ver facturas generadas
ls -lh wp-content/uploads/invoices/

# Verificar clientes por país
mysql -u usuario -p -e "SELECT country, COUNT(*) FROM wp_automatiza_tech_clients GROUP BY country;"
```

---

## ⚙️ Configuración

### Configuración SMTP

**Ubicación:** `inc/contact-form.php` → método `configure_smtp()`

```php
private function configure_smtp($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = 'smtp.gmail.com';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = 587;
    $phpmailer->Username = 'automatizatech.bots@gmail.com';
    $phpmailer->Password = 'tu-contraseña-de-aplicacion';
    $phpmailer->SMTPSecure = 'tls';
    $phpmailer->From = 'noreply@automatizatech.shop';
    $phpmailer->FromName = 'AutomatizaTech';
    $phpmailer->CharSet = 'UTF-8';
}
```

**Obtener contraseña de aplicación de Gmail:**
1. Cuenta de Google → Seguridad
2. Verificación en 2 pasos (activar)
3. Contraseñas de aplicaciones
4. Generar nueva contraseña
5. Usar en el código

### Configuración de País

**Ubicación:** `inc/contact-form.php` → método `detect_country_from_phone()`

**Agregar nuevo país:**
```php
private function detect_country_from_phone($phone) {
    $country_codes = array(
        // ... países existentes
        '+XX' => 'XX',  // Nuevo país
    );
    
    // Ordenar por longitud
    uksort($country_codes, function($a, $b) {
        return strlen($b) - strlen($a);
    });
    
    foreach ($country_codes as $code => $country) {
        if (strpos($phone, $code) === 0) {
            return $country;
        }
    }
    
    return 'CL'; // Default
}
```

### Configuración de Moneda

**Ubicación:** `lib/invoice-pdf-fpdf.php` → método `configure_currency()`

```php
private function configure_currency($country) {
    if ($country === 'CL') {
        $this->currency = 'CLP';
        $this->currency_symbol = '$';
        $this->apply_iva = true;
    } else {
        $this->currency = 'USD';
        $this->currency_symbol = 'USD $';
        $this->apply_iva = false;
    }
}
```

**Agregar nueva moneda (ejemplo: EUR):**
```php
private function configure_currency($country) {
    if ($country === 'CL') {
        $this->currency = 'CLP';
        $this->currency_symbol = '$';
        $this->apply_iva = true;
    } elseif ($country === 'ES' || $country === 'FR') {
        $this->currency = 'EUR';
        $this->currency_symbol = '€';
        $this->apply_iva = true; // IVA europeo
    } else {
        $this->currency = 'USD';
        $this->currency_symbol = 'USD $';
        $this->apply_iva = false;
    }
}
```

### Configuración de IVA

**Chile: 19%**
```php
$iva_rate = 0.19;
$neto = $total / 1.19;
$iva = $total - $neto;
```

**Otros países (sin IVA):**
```php
$iva = 0;
$neto = $total;
```

---

## 💼 Uso del Sistema

### Para Administradores

#### Panel de Datos de Facturación

1. **Acceder al panel**
   ```
   WordPress Admin → Datos Facturación
   ```

2. **Configurar datos de empresa**
   - Completar todos los campos
   - Ver vista previa
   - Guardar cambios

3. **Los cambios se aplican**
   - Inmediatamente en nuevas facturas
   - No afecta facturas ya generadas

#### Gestión de Contactos

1. **Ver contactos recibidos**
   ```
   WordPress Admin → Contactos
   ```

2. **Convertir a cliente**
   - Revisar datos del contacto
   - Seleccionar plan
   - Clic en "Convertir a Cliente"
   - Sistema genera todo automáticamente

3. **Verificar factura**
   ```
   /wp-content/uploads/invoices/AT-YYYYMMDD-XXXX.pdf
   ```

#### Gestión de Servicios

1. **Actualizar precios**
   ```sql
   UPDATE wp_automatiza_services 
   SET price_clp = 400000, price_usd = 500
   WHERE id = 1;
   ```

2. **Agregar nuevo servicio**
   ```sql
   INSERT INTO wp_automatiza_services 
   (name, description, price_clp, price_usd, status)
   VALUES 
   ('Plan Premium', 'Descripción', 500000, 600, 'active');
   ```

### Para Clientes

#### Recibir Factura

1. **Llenar formulario de contacto**
2. **Esperar confirmación de contratación**
3. **Recibir email con factura PDF adjunta**
4. **Descargar y guardar PDF**

---

## 👨‍💻 Desarrollo y Código

### Estructura de Archivos

```
wp-content/themes/[tema]/
├── functions.php
│   └── require_once 'inc/invoice-settings.php'
│
├── inc/
│   ├── contact-form.php
│   │   ├── Clase: AutomatizaTech_Contact_Form
│   │   ├── Métodos:
│   │   │   ├── detect_country_from_phone()
│   │   │   ├── handle_contact_submission()
│   │   │   ├── handle_convert_to_client()
│   │   │   ├── send_notification_email()
│   │   │   ├── send_invoice_email_to_client()
│   │   │   ├── send_contracted_client_email()
│   │   │   ├── generate_and_save_pdf()
│   │   │   └── configure_smtp()
│   │   └── Líneas importantes:
│   │       ├── 413-456: Detección de país
│   │       ├── 687-753: Conversión a cliente
│   │       ├── 900-1200: Email con factura
│   │       └── 1698-1730: Generación PDF
│   │
│   └── invoice-settings.php
│       ├── Funciones:
│       │   ├── automatiza_invoice_settings_menu()
│       │   ├── automatiza_register_invoice_settings()
│       │   └── automatiza_invoice_settings_page()
│       └── 320 líneas: Panel completo
│
└── lib/
    ├── invoice-pdf-fpdf.php
    │   ├── Clase: AutomatizaTech_Invoice_PDF_FPDF
    │   ├── Propiedades:
    │   │   ├── $client_country
    │   │   ├── $currency
    │   │   ├── $currency_symbol
    │   │   └── $apply_iva
    │   ├── Métodos:
    │   │   ├── detect_client_country()
    │   │   ├── configure_currency()
    │   │   ├── get_item_price()
    │   │   ├── format_currency()
    │   │   └── generate()
    │   └── Líneas importantes:
    │       ├── 14-93: Detección y configuración
    │       ├── 287-305: Tabla de servicios
    │       ├── 307-365: Cálculos con IVA
    │       └── 475-497: Métodos auxiliares
    │
    └── fpdf/
        └── fpdf.php (librería FPDF)
```

### Código Importante

#### Detección de País

```php
// inc/contact-form.php - Líneas 413-456
private function detect_country_from_phone($phone) {
    $country_codes = array(
        '+56' => 'CL',   // Chile
        '+1'  => 'US',   // USA/Canadá
        '+54' => 'AR',   // Argentina
        '+57' => 'CO',   // Colombia
        '+52' => 'MX',   // México
        '+51' => 'PE',   // Perú
        '+34' => 'ES',   // España
        '+55' => 'BR',   // Brasil
        '+593' => 'EC',  // Ecuador
        '+595' => 'PY',  // Paraguay
        '+598' => 'UY',  // Uruguay
        '+58' => 'VE',   // Venezuela
        '+506' => 'CR',  // Costa Rica
        '+507' => 'PA',  // Panamá
        '+503' => 'SV',  // El Salvador
        '+504' => 'HN',  // Honduras
        '+505' => 'NI',  // Nicaragua
        '+502' => 'GT'   // Guatemala
    );
    
    // Ordenar por longitud descendente
    uksort($country_codes, function($a, $b) {
        return strlen($b) - strlen($a);
    });
    
    // Buscar coincidencia
    foreach ($country_codes as $code => $country) {
        if (strpos($phone, $code) === 0) {
            return $country;
        }
    }
    
    return 'CL'; // Por defecto Chile
}
```

#### Configuración de Moneda

```php
// lib/invoice-pdf-fpdf.php - Líneas 62-74
private function configure_currency($country) {
    if ($country === 'CL') {
        $this->currency = 'CLP';
        $this->currency_symbol = '$';
        $this->apply_iva = true;
    } else {
        $this->currency = 'USD';
        $this->currency_symbol = 'USD $';
        $this->apply_iva = false;
    }
}
```

#### Cálculo de IVA

```php
// lib/invoice-pdf-fpdf.php - Líneas 307-365
if ($this->apply_iva) {
    // Chile: IVA 19%
    $neto = $subtotal / 1.19;
    $iva = $subtotal - $neto;
    
    $this->Cell(140, 6, 'Subtotal (Neto)', 0, 0, 'R');
    $this->Cell(40, 6, $this->format_currency($neto), 0, 1, 'R');
    
    $this->Cell(140, 6, 'IVA (19%)', 0, 0, 'R');
    $this->Cell(40, 6, $this->format_currency($iva), 0, 1, 'R');
} else {
    // Internacional: sin IVA
    $this->Cell(0, 6, '* Factura internacional - No aplica IVA chileno', 0, 1, 'R');
}
```

#### Generación de PDF

```php
// inc/contact-form.php - Líneas 1698-1730
private function generate_and_save_pdf($client_data) {
    require_once get_template_directory() . '/lib/invoice-pdf-fpdf.php';
    
    $pdf_generator = new AutomatizaTech_Invoice_PDF_FPDF($client_data);
    $pdf_content = $pdf_generator->generate();
    
    // Crear directorio si no existe
    $upload_dir = wp_upload_dir();
    $invoice_dir = $upload_dir['basedir'] . '/invoices';
    
    if (!file_exists($invoice_dir)) {
        wp_mkdir_p($invoice_dir);
    }
    
    // Generar nombre de archivo
    $invoice_number = $pdf_generator->get_invoice_number();
    $pdf_filename = $invoice_number . '.pdf';
    $pdf_path = $invoice_dir . '/' . $pdf_filename;
    
    // Guardar PDF
    file_put_contents($pdf_path, $pdf_content);
    
    return array(
        'path' => $pdf_path,
        'filename' => $pdf_filename,
        'number' => $invoice_number
    );
}
```

#### Envío de Email con PDF

```php
// inc/contact-form.php - Líneas 900-1200
private function send_invoice_email_to_client($client_id, $pdf_info) {
    global $wpdb;
    
    // Obtener datos del cliente
    $client = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$this->clients_table_name} WHERE id = %d",
        $client_id
    ));
    
    // Configurar SMTP
    add_action('phpmailer_init', array($this, 'configure_smtp'));
    
    // Asunto
    $subject = sprintf(
        'Bienvenido a AutomatizaTech - Factura %s - %s',
        $pdf_info['number'],
        $client->name
    );
    
    // Cuerpo HTML
    $message = $this->generate_client_email_html($client, $pdf_info);
    
    // Headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: AutomatizaTech <noreply@automatizatech.shop>'
    );
    
    // Adjuntar PDF
    $attachments = array($pdf_info['path']);
    
    // Enviar
    $sent = wp_mail($client->email, $subject, $message, $headers, $attachments);
    
    // Remover hook
    remove_action('phpmailer_init', array($this, 'configure_smtp'));
    
    return $sent;
}
```

### Hooks y Filtros

```php
// Registrar AJAX handlers
add_action('wp_ajax_submit_contact_form', array($this, 'handle_contact_submission'));
add_action('wp_ajax_nopriv_submit_contact_form', array($this, 'handle_contact_submission'));
add_action('wp_ajax_convert_to_client', array($this, 'handle_convert_to_client'));

// Configurar SMTP
add_action('phpmailer_init', array($this, 'configure_smtp'));

// Panel de administración
add_action('admin_menu', 'automatiza_invoice_settings_menu');
add_action('admin_init', 'automatiza_register_invoice_settings');
```

---

## 🔧 Troubleshooting

### Problemas Comunes

#### 1. Emails No Se Envían

**Síntomas:**
- No llegan emails de notificación
- No llega factura al cliente

**Soluciones:**

**A) Verificar configuración SMTP**
```php
// Verificar credenciales en inc/contact-form.php
$phpmailer->Username = 'automatizatech.bots@gmail.com';
$phpmailer->Password = 'tu-contraseña-correcta';
```

**B) Verificar logs**
```bash
tail -f wp-content/debug.log | grep "CORREO"
```

**C) Probar SMTP manualmente**
```php
// Crear archivo test-smtp.php
<?php
require_once('wp-load.php');

$to = 'tu-email@test.com';
$subject = 'Test SMTP';
$message = 'Prueba de envío';
$headers = array('Content-Type: text/html; charset=UTF-8');

$result = wp_mail($to, $subject, $message, $headers);
echo $result ? 'Email enviado' : 'Error al enviar';
?>
```

**D) Revisar límites del servidor**
```php
// Verificar límite de emails por hora
// Contactar con hosting si hay límite
```

---

#### 2. PDF No Se Genera

**Síntomas:**
- Error al convertir cliente
- PDF no aparece en /invoices/

**Soluciones:**

**A) Verificar permisos**
```bash
chmod 755 wp-content/uploads
chmod 755 wp-content/uploads/invoices
```

**B) Verificar que directorio existe**
```php
$upload_dir = wp_upload_dir();
$invoice_dir = $upload_dir['basedir'] . '/invoices';

if (!file_exists($invoice_dir)) {
    wp_mkdir_p($invoice_dir);
}
```

**C) Verificar librería FPDF**
```bash
ls -l wp-content/themes/[tema]/lib/fpdf/fpdf.php
```

**D) Revisar logs**
```bash
tail -f wp-content/debug.log | grep "PDF"
```

---

#### 3. País No Se Detecta Correctamente

**Síntomas:**
- Cliente extranjero recibe factura en CLP
- Cliente chileno recibe factura en USD

**Soluciones:**

**A) Verificar formato de teléfono**
```
Correcto: +56912345678
Incorrecto: 56912345678 (falta +)
Incorrecto: 912345678 (falta código)
```

**B) Verificar código telefónico**
```sql
SELECT id, name, phone, country 
FROM wp_automatiza_tech_clients 
WHERE country != 'CL';
```

**C) Actualizar manualmente**
```sql
UPDATE wp_automatiza_tech_clients 
SET country = 'AR' 
WHERE phone LIKE '+54%';
```

---

#### 4. IVA Se Calcula Mal

**Síntomas:**
- Totales no cuadran
- IVA incorrecto

**Soluciones:**

**A) Verificar cálculo**
```php
// Para Chile (19% IVA incluido)
$total = 400000;
$neto = $total / 1.19;  // = 336,135
$iva = $total - $neto;  // = 63,865
```

**B) Verificar país del cliente**
```sql
SELECT id, name, country, 
  CASE 
    WHEN country = 'CL' THEN 'Con IVA' 
    ELSE 'Sin IVA' 
  END as aplica_iva
FROM wp_automatiza_tech_clients;
```

---

#### 5. Panel de Configuración No Aparece

**Síntomas:**
- No se ve menú "Datos Facturación"

**Soluciones:**

**A) Verificar que archivo está incluido**
```php
// En functions.php debe estar:
require_once get_template_directory() . '/inc/invoice-settings.php';
```

**B) Verificar permisos de usuario**
```php
// Solo usuarios con capacidad 'manage_options' pueden ver
// Normalmente: Administrador
```

**C) Limpiar caché**
```bash
# Limpiar caché de WordPress
wp cache flush

# O desde panel
WordPress Admin → Performance/WP Rocket → Limpiar caché
```

---

#### 6. Precios en USD Faltantes

**Síntomas:**
- Servicios sin precio USD
- Error al generar factura internacional

**Soluciones:**

**A) Verificar precios**
```sql
SELECT id, name, price_clp, price_usd 
FROM wp_automatiza_services 
WHERE price_usd IS NULL OR price_usd = 0;
```

**B) Actualizar precios**
```sql
-- Calcular automáticamente (tasa ejemplo: 950 CLP = 1 USD)
UPDATE wp_automatiza_services 
SET price_usd = ROUND(price_clp / 950, 2)
WHERE price_usd IS NULL OR price_usd = 0;
```

**C) Agregar validación**
```php
// En lib/invoice-pdf-fpdf.php
private function get_item_price($item) {
    if ($this->currency === 'USD') {
        if (empty($item->price_usd) || $item->price_usd == 0) {
            // Calcular automáticamente
            return round($item->price_clp / 950, 2);
        }
        return $item->price_usd;
    }
    return $item->price_clp;
}
```

---

### Debugging

#### Habilitar Logs

```php
// En wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
```

#### Ver Logs en Tiempo Real

```bash
# Linux/Mac
tail -f wp-content/debug.log

# Windows (PowerShell)
Get-Content wp-content\debug.log -Wait -Tail 50

# Filtrar por tipo
tail -f wp-content/debug.log | grep "INVOICE"
tail -f wp-content/debug.log | grep "CORREO"
tail -f wp-content/debug.log | grep "PDF"
```

#### Queries SQL Útiles

```sql
-- Clientes por país
SELECT country, COUNT(*) as total 
FROM wp_automatiza_tech_clients 
GROUP BY country;

-- Facturas generadas hoy
SELECT * FROM wp_automatiza_tech_invoices 
WHERE DATE(created_at) = CURDATE();

-- Clientes sin país asignado
SELECT * FROM wp_automatiza_tech_clients 
WHERE country IS NULL OR country = '';

-- Servicios sin precio USD
SELECT * FROM wp_automatiza_services 
WHERE price_usd IS NULL OR price_usd = 0;

-- Última factura generada
SELECT * FROM wp_automatiza_tech_invoices 
ORDER BY created_at DESC LIMIT 1;
```

---

## 🔄 Mantenimiento

### Tareas Semanales

#### 1. Revisar Logs
```bash
cd wp-content
tail -100 debug.log | grep "ERROR"
```

#### 2. Verificar Emails
- Revisar bandeja de automatizatech.bots@gmail.com
- Confirmar recepción de notificaciones
- Verificar que no hay rebotes

#### 3. Revisar Facturas
```bash
ls -lh wp-content/uploads/invoices/ | tail -20
```

### Tareas Mensuales

#### 1. Actualizar Precios USD
```sql
-- Si hay cambio en tasa de cambio
UPDATE wp_automatiza_services 
SET price_usd = ROUND(price_clp / 950, 2);
```

#### 2. Estadísticas
```sql
-- Clientes por país en el mes
SELECT country, COUNT(*) as total 
FROM wp_automatiza_tech_clients 
WHERE MONTH(created_at) = MONTH(CURRENT_DATE)
GROUP BY country;

-- Facturas generadas en el mes
SELECT COUNT(*) as total_facturas, 
       SUM(total_amount) as monto_total,
       currency
FROM wp_automatiza_tech_invoices 
WHERE MONTH(created_at) = MONTH(CURRENT_DATE)
GROUP BY currency;
```

#### 3. Limpiar Logs Antiguos
```bash
# Backup y limpieza de logs
cp wp-content/debug.log wp-content/debug-backup-$(date +%Y%m%d).log
> wp-content/debug.log
```

### Tareas Trimestrales

#### 1. Backup de Facturas
```bash
# Crear backup de PDFs
tar -czf invoices-backup-$(date +%Y%m%d).tar.gz wp-content/uploads/invoices/

# Mover a almacenamiento seguro
mv invoices-backup-*.tar.gz /path/to/backups/
```

#### 2. Auditoría de Base de Datos
```sql
-- Verificar integridad
CHECK TABLE wp_automatiza_tech_clients;
CHECK TABLE wp_automatiza_tech_invoices;
CHECK TABLE wp_automatiza_services;

-- Optimizar tablas
OPTIMIZE TABLE wp_automatiza_tech_clients;
OPTIMIZE TABLE wp_automatiza_tech_invoices;
```

#### 3. Revisar Documentación
- Actualizar README.md si hay cambios
- Documentar nuevas funcionalidades
- Actualizar número de versión

---

## ❓ FAQ

### Preguntas Generales

**Q: ¿Qué versión de PHP requiere?**  
A: PHP 7.4 o superior.

**Q: ¿Funciona con cualquier tema de WordPress?**  
A: Sí, el sistema se integra en cualquier tema. Solo necesitas agregar los archivos en las carpetas `inc/` y `lib/`.

**Q: ¿Se puede usar con WooCommerce?**  
A: Sí, es compatible. El sistema de facturación es independiente.

**Q: ¿Los PDFs se guardan en el servidor?**  
A: Sí, en `/wp-content/uploads/invoices/`. También se adjuntan al email.

**Q: ¿Cuánto espacio ocupan los PDFs?**  
A: Aproximadamente 50-100 KB por factura.

---

### Preguntas Técnicas

**Q: ¿Por qué usar FPDF y no otra librería?**  
A: FPDF es 100% PHP, sin dependencias externas, funciona en cualquier servidor y es muy ligero.

**Q: ¿Se puede cambiar el diseño de la factura?**  
A: Sí, editando `lib/invoice-pdf-fpdf.php`. El código está bien comentado.

**Q: ¿Se puede agregar más monedas?**  
A: Sí, modificando `configure_currency()` y agregando lógica de detección.

**Q: ¿Se puede cambiar la tasa de cambio dinámicamente?**  
A: El sistema actual usa precios fijos por servicio. Se puede integrar una API de tasas de cambio.

**Q: ¿Cómo agregar más países?**  
A: Agregando el código telefónico en `detect_country_from_phone()`.

---

### Preguntas de Facturación

**Q: ¿Cómo se calcula el IVA chileno?**  
A: El precio incluye IVA. Se calcula: `neto = total / 1.19`, `iva = total - neto`.

**Q: ¿Se puede cambiar el porcentaje de IVA?**  
A: Sí, modificando el factor 1.19 en el código.

**Q: ¿Se pueden reenviar facturas?**  
A: Actualmente no hay función automática. Se puede enviar el PDF manualmente.

**Q: ¿Se puede editar una factura después de generada?**  
A: No. Las facturas son inmutables. Se debe generar una nueva.

**Q: ¿Hay numeración correlativa?**  
A: La numeración usa formato AT-YYYYMMDD-XXXX donde XXXX es el ID del cliente.

---

### Preguntas de Emails

**Q: ¿Se pueden personalizar los emails?**  
A: Sí, editando los métodos de generación de HTML en `inc/contact-form.php`.

**Q: ¿Qué pasa si falla el envío de email?**  
A: Se registra en el log. El PDF se guarda igual en el servidor.

**Q: ¿Se puede usar otro servicio SMTP?**  
A: Sí, modificando `configure_smtp()` con los datos del nuevo servicio.

**Q: ¿Cuántos emails se pueden enviar por día?**  
A: Depende del límite de tu servidor SMTP. Gmail permite ~500/día.

---

### Preguntas de Seguridad

**Q: ¿Los PDFs son públicos?**  
A: No, están en directorio protegido. Solo se puede acceder con la URL exacta.

**Q: ¿Se puede agregar un .htaccess en /invoices/?**  
A: Sí, recomendado:
```apache
# En /wp-content/uploads/invoices/.htaccess
<Files *.pdf>
    Order Deny,Allow
    Deny from all
</Files>
```

**Q: ¿Dónde se guarda la configuración SMTP?**  
A: En el código fuente. No uses credenciales en wp_options.

**Q: ¿Los datos se encriptan?**  
A: Los emails usan TLS. La BD debe tener seguridad a nivel de servidor.

---

## 📞 Soporte

### Recursos

**Documentación:**
- Este archivo README.md

**Logs:**
```bash
wp-content/debug.log
```

**Base de Datos:**
```sql
-- Tablas principales
wp_automatiza_tech_clients
wp_automatiza_tech_invoices
wp_automatiza_services
```

**Archivos Clave:**
```
inc/contact-form.php
inc/invoice-settings.php
lib/invoice-pdf-fpdf.php
```

### Información para Reportar Problemas

Al reportar un problema, incluye:

1. **Descripción del problema**
   - ¿Qué estabas intentando hacer?
   - ¿Qué esperabas que pasara?
   - ¿Qué pasó en realidad?

2. **Entorno**
   - Versión de WordPress
   - Versión de PHP
   - Hosting (Hostinger, GoDaddy, etc.)

3. **Logs relevantes**
   ```bash
   tail -50 wp-content/debug.log
   ```

4. **Capturas de pantalla** (si aplica)

5. **Queries para diagnóstico**
   ```sql
   -- Estado de clientes
   SELECT COUNT(*), country FROM wp_automatiza_tech_clients GROUP BY country;
   
   -- Última factura
   SELECT * FROM wp_automatiza_tech_invoices ORDER BY created_at DESC LIMIT 1;
   ```

---

## 📊 Apéndices

### Apéndice A: Países Soportados

| # | País | Código | Teléfono | Moneda | IVA |
|---|------|--------|----------|--------|-----|
| 1 | Chile | CL | +56 | CLP | 19% |
| 2 | Estados Unidos | US | +1 | USD | No |
| 3 | Argentina | AR | +54 | USD | No |
| 4 | Colombia | CO | +57 | USD | No |
| 5 | México | MX | +52 | USD | No |
| 6 | Perú | PE | +51 | USD | No |
| 7 | España | ES | +34 | USD | No |
| 8 | Brasil | BR | +55 | USD | No |
| 9 | Ecuador | EC | +593 | USD | No |
| 10 | Paraguay | PY | +595 | USD | No |
| 11 | Uruguay | UY | +598 | USD | No |
| 12 | Venezuela | VE | +58 | USD | No |
| 13 | Costa Rica | CR | +506 | USD | No |
| 14 | Panamá | PA | +507 | USD | No |
| 15 | El Salvador | SV | +503 | USD | No |
| 16 | Honduras | HN | +504 | USD | No |
| 17 | Nicaragua | NI | +505 | USD | No |
| 18 | Guatemala | GT | +502 | USD | No |

### Apéndice B: Scripts SQL Útiles

```sql
-- Migración completa (ejecutar al instalar)
-- Ver: sql/migration-production-multi-currency.sql

-- Verificar estructura de clientes
DESCRIBE wp_automatiza_tech_clients;

-- Ver clientes por país
SELECT country, COUNT(*) as total 
FROM wp_automatiza_tech_clients 
GROUP BY country;

-- Facturas del mes actual
SELECT DATE(created_at) as fecha, COUNT(*) as total
FROM wp_automatiza_tech_invoices
WHERE MONTH(created_at) = MONTH(CURRENT_DATE)
GROUP BY DATE(created_at);

-- Ingresos por moneda
SELECT currency, SUM(total_amount) as total
FROM wp_automatiza_tech_invoices
GROUP BY currency;

-- Servicios más vendidos
SELECT s.name, COUNT(i.id) as veces_vendido
FROM wp_automatiza_services s
LEFT JOIN wp_automatiza_tech_invoices i ON s.id = i.plan_id
GROUP BY s.id
ORDER BY veces_vendido DESC;

-- Clientes sin país
SELECT * FROM wp_automatiza_tech_clients
WHERE country IS NULL OR country = '';

-- Actualizar país por teléfono (si hace falta)
UPDATE wp_automatiza_tech_clients SET country = 'CL' WHERE phone LIKE '+56%';
UPDATE wp_automatiza_tech_clients SET country = 'US' WHERE phone LIKE '+1%';
UPDATE wp_automatiza_tech_clients SET country = 'AR' WHERE phone LIKE '+54%';
```

### Apéndice C: Comandos Útiles

```bash
# Ver logs en tiempo real
tail -f wp-content/debug.log

# Filtrar logs por tipo
tail -f wp-content/debug.log | grep "INVOICE"
tail -f wp-content/debug.log | grep "CORREO"
tail -f wp-content/debug.log | grep "PDF"

# Listar facturas generadas
ls -lht wp-content/uploads/invoices/ | head -20

# Contar facturas
ls wp-content/uploads/invoices/*.pdf | wc -l

# Buscar factura específica
find wp-content/uploads/invoices/ -name "AT-20251111-*"

# Tamaño total de facturas
du -sh wp-content/uploads/invoices/

# Backup de facturas
tar -czf invoices-backup-$(date +%Y%m%d).tar.gz wp-content/uploads/invoices/

# Backup de base de datos
mysqldump -u usuario -p nombre_bd > backup-$(date +%Y%m%d).sql

# Restaurar base de datos
mysql -u usuario -p nombre_bd < backup-20251111.sql

# Verificar permisos
ls -la wp-content/uploads/invoices/

# Cambiar permisos si es necesario
chmod 755 wp-content/uploads/invoices/
chmod 644 wp-content/uploads/invoices/*.pdf
```

### Apéndice D: Checklist de Despliegue

```
Pre-Despliegue:
[ ] Backup de base de datos
[ ] Backup de archivos
[ ] Revisar conexión a producción
[ ] Coordinar horario de mantenimiento

Despliegue:
[ ] Subir inc/invoice-settings.php (NUEVO)
[ ] Subir inc/contact-form.php (MODIFICADO)
[ ] Subir lib/invoice-pdf-fpdf.php (MODIFICADO)
[ ] Subir functions.php (MODIFICADO)
[ ] Ejecutar migration-production-multi-currency.sql
[ ] Verificar columna 'country' en BD
[ ] Actualizar precios USD de servicios
[ ] Configurar datos de empresa en panel

Post-Despliegue:
[ ] Prueba: Enviar formulario de contacto
[ ] Prueba: Convertir contacto a cliente
[ ] Prueba: Verificar email recibido
[ ] Prueba: Verificar PDF generado
[ ] Prueba: Cliente chileno (CLP + IVA)
[ ] Prueba: Cliente internacional (USD sin IVA)
[ ] Revisar logs (sin errores)
[ ] Monitoreo por 24-48 horas
[ ] Eliminar archivos de test

Limpieza:
[ ] Eliminar archivos test-*.php
[ ] Eliminar archivos debug-*.php
[ ] Eliminar documentación .md (excepto README.md)
[ ] Eliminar scripts de instalación
```

---

## 📝 Changelog

### Versión 2.0 (Noviembre 2025)

**Nuevas Funcionalidades:**
- ✨ Sistema multi-moneda (CLP/USD)
- 🌎 Soporte para 18 países
- 📧 3 tipos de emails automáticos
- ⚙️ Panel de configuración en WordPress Admin
- 📄 Generación de PDF con FPDF
- 🧮 Cálculo automático de IVA según país
- 📊 Detección automática de país por teléfono

**Archivos Modificados:**
- `lib/invoice-pdf-fpdf.php` - Sistema multi-moneda
- `inc/contact-form.php` - Emails y detección

**Archivos Nuevos:**
- `inc/invoice-settings.php` - Panel admin

**Base de Datos:**
- Nueva columna `country` en `wp_automatiza_tech_clients`

**Breaking Changes:**
- Ninguno (compatible con datos existentes)

**Migración Requerida:**
- Ejecutar `sql/migration-production-multi-currency.sql`

---

### Versión 1.0 (Anterior)

Sistema base de facturación con:
- Formulario de contacto
- Conversión manual a cliente
- Factura básica en CLP
- Email simple de notificación

---

## 📄 Licencia

Este sistema es propietario de **AutomatizaTech**.

**Uso:**
- ✅ Uso interno en proyectos de AutomatizaTech
- ✅ Modificación para necesidades específicas
- ❌ Distribución a terceros sin autorización
- ❌ Venta o licenciamiento a terceros

---

## 🎉 Créditos

**Desarrollo:**
- AutomatizaTech Development Team

**Librerías Utilizadas:**
- FPDF (http://www.fpdf.org/) - Licencia permisiva

**Tecnologías:**
- WordPress
- PHP
- MySQL
- FPDF

---

**Última actualización:** Noviembre 2025  
**Versión:** 2.0  
**Mantenido por:** AutomatizaTech Development Team  
**Estado:** ✅ Producción Activa

---

**Fin de la Documentación**
