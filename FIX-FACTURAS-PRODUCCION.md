# 🔧 Fix: Hora Correcta en Facturas (Timezone Chile)

## 🎯 Problema

Las facturas generadas mostraban hora incorrecta (diferencia de 3 horas) porque el código usaba `date()` en lugar de `current_time()` de WordPress.

**Ejemplo del problema:**
- Hora real de contratación: 00:18 (medianoche Chile)
- Hora en la factura: 03:18 (3 horas de diferencia - UTC)

## ✅ Solución Aplicada

Se reemplazaron todas las instancias de `date()` por `current_time()` para respetar la zona horaria configurada en WordPress.

## 📝 Archivos Modificados

### 1. `lib/invoice-pdf-fpdf.php`

**Línea 264 - Fecha en el encabezado de la factura:**
```php
// ANTES
$this->Cell(70, 8, 'Fecha: ' . date('d/m/Y H:i'), 0, 1, 'R');

// DESPUÉS
$this->Cell(70, 8, 'Fecha: ' . current_time('d/m/Y H:i'), 0, 1, 'R');
```

**Línea 229 - Copyright en el footer:**
```php
// ANTES
$this->Cell(0, 3, utf8_to_latin1('© ' . date('Y') . ' ' . $company_name . '...

// DESPUÉS
$this->Cell(0, 3, utf8_to_latin1('© ' . current_time('Y') . ' ' . $company_name . '...
```

### 2. `inc/contact-form.php`

**Líneas 1074-1075 - Fechas en email de notificación:**
```php
// ANTES
<p><span class='label'>Contactado:</span> <span class='value'>" . date('d/m/Y H:i', strtotime($client_data->contacted_at)) . "</span></p>
<p><span class='label'>Contratado:</span> <span class='value'>" . date('d/m/Y H:i', strtotime($client_data->contracted_at)) . "</span></p>

// DESPUÉS
<p><span class='label'>Contactado:</span> <span class='value'>" . current_time('d/m/Y H:i', strtotime($client_data->contacted_at)) . "</span></p>
<p><span class='label'>Contratado:</span> <span class='value'>" . current_time('d/m/Y H:i', strtotime($client_data->contracted_at)) . "</span></p>
```

**Línea 1099 - Footer del email:**
```php
// ANTES
<p>📅 " . date('d/m/Y H:i:s') . "</p>

// DESPUÉS
<p>📅 " . current_time('d/m/Y H:i:s') . "</p>
```

**Línea 1456 - Versión texto plano del email:**
```php
// ANTES
$plain_text .= "Fecha: " . date('d/m/Y H:i') . "\n\n";

// DESPUÉS
$plain_text .= "Fecha: " . current_time('d/m/Y H:i') . "\n\n";
```

**Línea 1923 - Fecha de validez de cotización:**
```php
// ANTES
$fecha_validez = date('d/m/Y', strtotime($valid_until));

// DESPUÉS
$fecha_validez = current_time('d/m/Y', strtotime($valid_until));
```

**Línea 2644 - Nombre de archivo de backup:**
```php
// ANTES
$filename = 'cliente-contratado-' . date('Y-m-d_H-i-s') . '-' . sanitize_file_name($contact->name) . '.html';

// DESPUÉS
$filename = 'cliente-contratado-' . current_time('Y-m-d_H-i-s') . '-' . sanitize_file_name($contact->name) . '.html';
```

**Línea 2660 - Fecha en backup de email:**
```php
// ANTES
<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>

// DESPUÉS
<p><strong>Fecha:</strong> " . current_time('Y-m-d H:i:s') . "</p>
```

**Línea 2750 - Formato de timestamp en índice:**
```php
// ANTES
$formatted_time = date('Y-m-d H:i:s', $file_time);

// DESPUÉS
$formatted_time = current_time('Y-m-d H:i:s', $file_time);
```

## 🔄 Diferencias entre date() y current_time()

### `date()`
- Usa la hora del servidor (típicamente UTC)
- **NO** respeta la configuración de zona horaria de WordPress
- Es la hora del sistema PHP

### `current_time()`
- Usa la zona horaria configurada en WordPress
- **SÍ** respeta el setting `timezone_string` de WordPress
- Hora correcta para el usuario final

## 🇨🇱 Configuración de Zona Horaria

Para que los cambios funcionen correctamente, es necesario tener configurada la zona horaria de Chile en WordPress:

### En `wp-config.php`:
```php
date_default_timezone_set('America/Santiago');
```

### En WordPress (Base de Datos):
```php
update_option('timezone_string', 'America/Santiago');
```

Ver documento completo: `CONFIGURAR-TIMEZONE-CHILE.md`

## 🚀 Despliegue a Producción

### Archivos a Subir

1. **`wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php`**
   - Genera las facturas PDF con FPDF
   - **Crítico:** Este es el archivo principal de las facturas

2. **`wp-content/themes/automatiza-tech/inc/contact-form.php`**
   - Maneja emails y proceso de contratación
   - **Importante:** Afecta emails y cotizaciones

### Pasos para Deploy

```bash
# Via FTP/cPanel
1. Conectar a servidor
2. Ir a /public_html/wp-content/themes/automatiza-tech/
3. Subir lib/invoice-pdf-fpdf.php (reemplazar)
4. Subir inc/contact-form.php (reemplazar)

# Via SSH (si tienes acceso)
cd /home/u187918280/domains/automatizatech.shop/public_html/wp-content/themes/automatiza-tech
# Subir los archivos nuevos
```

### ⚠️ Importante - Backup Antes de Desplegar

```bash
# Hacer backup de los archivos actuales
cp lib/invoice-pdf-fpdf.php lib/invoice-pdf-fpdf.php.backup-$(date +%Y%m%d)
cp inc/contact-form.php inc/contact-form.php.backup-$(date +%Y%m%d)
```

## ✅ Verificación Post-Deploy

### 1. Generar una Factura de Prueba

1. Ir al panel de contactos en WordPress admin
2. Crear un nuevo contacto de prueba
3. Moverlo a "Contratado" con un plan
4. Verificar que se genere la factura

### 2. Revisar la Hora en la Factura

- **Esperado:** Hora correcta de Chile
- **Formato:** `dd/mm/YYYY HH:mm` (ej: 15/11/2025 23:45)
- **No debe:** Mostrar UTC ni hora incorrecta

### 3. Comparar con Hora del Sistema

```php
// Acceder a: https://automatizatech.shop/test-timezone.php
<?php
echo "Hora PHP (date): " . date('d/m/Y H:i:s') . "\n";
echo "Hora WordPress (current_time): " . current_time('d/m/Y H:i:s') . "\n";
echo "Timezone PHP: " . date_default_timezone_get() . "\n";
echo "Timezone WordPress: " . get_option('timezone_string') . "\n";
```

### 4. Verificar Facturas Existentes

Las facturas **YA GENERADAS** seguirán mostrando la hora incorrecta porque son archivos PDF estáticos. Solo las **NUEVAS** facturas tendrán la hora correcta.

Si necesitas **regenerar facturas antiguas** con hora correcta:
- Usa el script: `regenerate-invoices-fpdf.php`
- Esto volverá a generar los PDFs con la hora correcta

## 📊 Impacto de los Cambios

### ✅ Beneficios

1. **Facturas con hora correcta de Chile** - Principal objetivo
2. **Emails con timestamps correctos** - Mejor trazabilidad
3. **Cotizaciones con fechas correctas** - Profesionalismo
4. **Consistencia en todo el sistema** - Todas las fechas iguales

### ⚠️ Consideraciones

1. **PDFs existentes NO cambian** - Son archivos estáticos
2. **Necesita timezone configurado** - Ver `CONFIGURAR-TIMEZONE-CHILE.md`
3. **Compatible con horario de verano** - WordPress maneja DST automáticamente

## 🔍 Testing

### Test Local (ANTES de subir a producción)

```bash
# 1. Verificar que los archivos estén correctos
grep -n "current_time" lib/invoice-pdf-fpdf.php
grep -n "current_time" inc/contact-form.php

# 2. Generar factura de prueba local
# Acceder a: http://localhost/automatiza-tech/wp-admin
# Crear contacto → Contratar → Verificar PDF
```

### Test en Producción

```bash
# 1. Después del deploy
# 2. Crear cliente de prueba
# 3. Generar factura
# 4. Descargar y verificar hora
# 5. Eliminar cliente de prueba si todo OK
```

## 📝 Registro de Cambios

| Fecha | Versión | Cambio |
|-------|---------|--------|
| 2025-11-16 | 1.0 | Fix inicial: Reemplazo de date() por current_time() |

## 🔗 Documentos Relacionados

- `CONFIGURAR-TIMEZONE-CHILE.md` - Configuración de zona horaria
- `SISTEMA-FACTURAS-FPDF.md` - Sistema de facturación completo
- `DEPLOY-PRODUCCION.md` - Guía general de deployment

---

**Estado:** ✅ Listo para producción  
**Prioridad:** 🔴 Alta - Afecta datos mostrados a clientes  
**Complejidad:** 🟢 Baja - Cambios simples y localizados  
**Riesgo:** 🟡 Medio - Probar bien antes de deploy
