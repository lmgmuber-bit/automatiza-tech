# 🕐 Configurar Zona Horaria de Chile

## ⚠️ Problema Detectado y Resuelto

**Síntoma:** Las facturas en producción mostraban una diferencia de **3 horas** adelantadas.

**Ejemplo:**
- Hora real en Chile: 16/11/2025 00:18
- Factura generaba: 16/11/2025 03:18 ❌
- **Diferencia: +3 horas**

**Causa Raíz:**
- El servidor usaba UTC (horario universal)
- Chile usa UTC-3 (horario de verano)
- Las funciones PHP `date()` no tenían la zona horaria configurada

**Solución Aplicada:**
✅ Se agregó `date_default_timezone_set('America/Santiago')` en los constructores de:
   - `invoice-pdf-fpdf.php` (facturas)
   - `quotation-pdf-fpdf.php` (cotizaciones)

**Resultado:**
- ✅ Facturas con hora correcta de Chile
- ✅ Cotizaciones con hora correcta de Chile
- ✅ No más diferencia de 3 horas

---

## 🎯 Objetivo

Configurar el sitio WordPress para usar la zona horaria de Chile (America/Santiago) en todas las fechas y horas del sistema, incluyendo facturas, contactos, posts, etc.

## 📋 Cambios a Realizar

### 0. ⚠️ IMPORTANTE: Subir Archivos Modificados PRIMERO

**Antes de hacer cualquier otra cosa, sube estos 2 archivos modificados al servidor:**

1. **`wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php`**
   - ✅ Ya modificado localmente con `date_default_timezone_set('America/Santiago')`
   - 📤 Subir por FTP/cPanel al servidor de producción
   - 📍 Ruta: `/public_html/wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php`

2. **`wp-content/themes/automatiza-tech/lib/quotation-pdf-fpdf.php`**
   - ✅ Ya modificado localmente con `date_default_timezone_set('America/Santiago')`
   - 📤 Subir por FTP/cPanel al servidor de producción
   - 📍 Ruta: `/public_html/wp-content/themes/automatiza-tech/lib/quotation-pdf-fpdf.php`

**Verificar que se subieron correctamente:**
```
- Ambos archivos deben tener el mismo tamaño que los locales
- La fecha de modificación debe ser reciente
- Hacer backup de los archivos actuales antes de sobrescribir
```

### 1. Modificar wp-config.php

Agregar esta línea después de `define('DB_COLLATE', '');`:

```php
/* Configuración de Zona Horaria - Chile */
date_default_timezone_set('America/Santiago');
```

**Ubicación exacta en wp-config.php:**
```php
define('DB_COLLATE', ''); 

/* Configuración de Zona Horaria - Chile */
date_default_timezone_set('America/Santiago');

$table_prefix = 'wp_'; 
```

### 2. Configurar WordPress (Base de Datos)

Sube y ejecuta el script `set-timezone-chile.php`:

**Via FTP/cPanel:**
1. Sube `set-timezone-chile.php` a la raíz del sitio
2. Accede a: `https://automatizatech.shop/set-timezone-chile.php`
3. Verifica que muestre "✅ Configuración completada"
4. **IMPORTANTE:** Elimina el archivo después de ejecutarlo

## 🚀 Pasos Detallados

### Paso 1: Modificar wp-config.php en Producción

**Via FTP/cPanel File Manager:**

1. Conecta a tu servidor
2. Abre `/public_html/wp-config.php`
3. Busca la línea: `define('DB_COLLATE', '');`
4. Después de esa línea, agrega:
   ```php
   
   /* Configuración de Zona Horaria - Chile */
   date_default_timezone_set('America/Santiago');
   ```
5. Guarda el archivo

**Via SSH (si tienes acceso):**
```bash
cd /home/u187918280/domains/automatizatech.shop/public_html
nano wp-config.php
# Agregar las líneas
# Guardar con Ctrl+X, Y, Enter
```

### Paso 2: Ejecutar Script de Configuración

1. Sube `set-timezone-chile.php` a la raíz del sitio
2. Accede desde tu navegador (como admin logueado):
   ```
   https://automatizatech.shop/set-timezone-chile.php
   ```
3. El script mostrará:
   - ⏰ Zona horaria actual
   - 🇨🇱 Proceso de configuración
   - ✅ Verificación de cambios
   - 📝 Resumen de lo aplicado

### Paso 3: Verificar Cambios

**En el Script:**
- Verificar que "Fecha/Hora WordPress ahora" muestre hora de Chile
- Verificar que "Zona horaria PHP" sea "America/Santiago"

**En WordPress Admin:**
1. Ve a `Ajustes → Generales`
2. Busca sección "Zona horaria"
3. Debería mostrar: **Santiago** o **America/Santiago**
4. Formato de fecha: `d/m/Y`
5. Formato de hora: `H:i`

**En el Panel de Clientes:**
1. Ve al panel de clientes contratados
2. Verifica que las fechas de contratación muestren hora correcta de Chile
3. Verifica que las facturas tengan hora correcta

### Paso 4: Limpieza

Elimina el script de configuración:
```bash
rm /public_html/set-timezone-chile.php
```

O vía FTP/cPanel elimina: `set-timezone-chile.php`

## ⚙️ Qué Hace Esta Configuración

### 1. En PHP (`wp-config.php`)
```php
date_default_timezone_set('America/Santiago');
```
- Configura la zona horaria para todas las funciones PHP
- Afecta `date()`, `time()`, `strtotime()`, etc.
- Se aplica antes de que WordPress cargue

### 2. En WordPress (Base de Datos)
```php
update_option('timezone_string', 'America/Santiago');
```
- Configura la zona horaria en las opciones de WordPress
- Afecta `current_time()`, fechas de posts, comentarios, etc.
- Se almacena en la tabla `wp_options`

### 3. Formatos de Fecha/Hora
- **Fecha:** `d/m/Y` → 15/11/2025
- **Hora:** `H:i` → 15:30 (formato 24 horas)

## 🇨🇱 Información de Chile

**Zona Horaria:** America/Santiago

**Offset UTC:**
- **Horario de Verano (CLST):** UTC-3 (septiembre - abril)
- **Horario Estándar (CLT):** UTC-4 (abril - septiembre)

**Cambio Automático:** WordPress/PHP manejan automáticamente el cambio entre horario de verano y estándar.

## ✅ Verificación Post-Configuración

### Checklist

- [ ] `wp-config.php` modificado con `date_default_timezone_set('America/Santiago')`
- [ ] Script `set-timezone-chile.php` ejecutado exitosamente
- [ ] En `Ajustes → Generales` aparece "Santiago" como zona horaria
- [ ] Las fechas en el panel de clientes muestran hora correcta de Chile
- [ ] Las facturas nuevas se generan con hora de Chile
- [ ] Script `set-timezone-chile.php` eliminado del servidor

### Prueba Final

Crea un nuevo cliente de prueba y verifica que:
1. La fecha de contrato muestre hora correcta de Chile
2. Si se genera una factura, tenga la fecha/hora correcta
3. Los timestamps en la base de datos sean correctos

## 🔍 Troubleshooting

### Problema: Las fechas siguen en UTC/hora incorrecta

**Causa:** El hosting puede tener configuración que sobrescribe

**Solución:**
```php
// Agregar al inicio de wp-config.php (después de <?php)
define('WP_TIMEZONE', 'America/Santiago');
date_default_timezone_set('America/Santiago');
```

### Problema: Las fechas en facturas siguen incorrectas ✅ SOLUCIONADO

**Causa:** El código de generación de facturas usa `date()` en lugar de `current_time()`

**Solución Aplicada:** Se configuró la zona horaria en los constructores de las clases PDF:

**Archivo 1:** `wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php`
```php
public function __construct($client_data, $plan_data, $invoice_number = '') {
    parent::__construct('P', 'mm', 'A4');
    $this->client_data = $client_data;
    
    // ✅ AGREGADO: Configurar zona horaria de Chile al inicio
    date_default_timezone_set('America/Santiago');
    
    // ... resto del código
}
```

**Archivo 2:** `wp-content/themes/automatiza-tech/lib/quotation-pdf-fpdf.php`
```php
public function __construct($contact_data, $plan_data, $quotation_number = '', $valid_until = '') {
    parent::__construct('P', 'mm', 'A4');
    $this->contact_data = $contact_data;
    
    // ✅ AGREGADO: Configurar zona horaria de Chile al inicio
    date_default_timezone_set('America/Santiago');
    
    // ... resto del código
}
```

**Impacto:**
- ✅ Las facturas ahora muestran la hora correcta de Chile
- ✅ Las cotizaciones también usan la hora correcta
- ✅ No hay diferencia de 3 horas como antes
- ✅ Se aplica automáticamente al crear cada PDF

### Problema: Hosting no permite modificar wp-config.php

**Solución:** Crear plugin personalizado:
```php
// Crear: wp-content/mu-plugins/timezone-chile.php
<?php
/**
 * Plugin Name: Timezone Chile
 */
date_default_timezone_set('America/Santiago');
add_filter('pre_option_timezone_string', function() {
    return 'America/Santiago';
});
```

## 📚 Recursos Adicionales

**Documentación WordPress:**
- [Timezone Settings](https://wordpress.org/support/article/settings-general-screen/#timezone)
- [current_time() Function](https://developer.wordpress.org/reference/functions/current_time/)

**PHP Timezones:**
- [Lista de Zonas Horarias](https://www.php.net/manual/en/timezones.america.php)
- [date_default_timezone_set](https://www.php.net/manual/en/function.date-default-timezone-set.php)

**Chile - Información Horaria:**
- Zona: America/Santiago
- Sigla: CLT (Chile Standard Time) / CLST (Chile Summer Time)
- Cambio de hora: Primer sábado de abril y septiembre

---

**Creado:** 2025-11-16  
**Versión:** 1.0  
**Estado:** ✅ Listo para aplicar
