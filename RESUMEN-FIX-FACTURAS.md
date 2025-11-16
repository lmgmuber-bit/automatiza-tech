# ⏰ Fix: Hora Correcta en Facturas y Cotizaciones - ACTUALIZADO

## 🎯 Problema Resuelto
Facturas y cotizaciones mostraban **3 horas de diferencia** (UTC en lugar de hora de Chile).

**Antes:**
- Hora real: 00:18
- PDF mostraba: 03:18 ❌

**Después:**
- Hora real: 00:18  
- PDF muestra: 00:18 ✅

## ✅ Solución Final

Se configuró la zona horaria de Chile directamente en los constructores de las clases PDF.

### Cambio Aplicado

En ambos archivos se agregó al inicio del constructor:
```php
// Configurar zona horaria de Chile al inicio
date_default_timezone_set('America/Santiago');
```

## 📦 Archivos Modificados (Subir a Producción)

### 1. `lib/invoice-pdf-fpdf.php` ✅
- **Qué hace:** Genera las facturas PDF
- **Cambio:** Línea 61-62 (agregada en constructor)
- **Ubicación servidor:** `/wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php`

### 2. `lib/quotation-pdf-fpdf.php` ✅
- **Qué hace:** Genera las cotizaciones PDF
- **Cambio:** Línea 61-62 (agregada en constructor)
- **Ubicación servidor:** `/wp-content/themes/automatiza-tech/lib/quotation-pdf-fpdf.php`

### 3. `wp-config.php` (Opcional pero Recomendado)
Agregar después de las definiciones de DB:
```php
/* Configuración de Zona Horaria - Chile */
date_default_timezone_set('America/Santiago');
```

## 🚀 Pasos para Aplicar en Producción

### Via FTP/FileZilla
```bash
# 1. Conectar a: automatizatech.shop

# 2. Navegar a: /public_html/wp-content/themes/automatiza-tech/lib/

# 3. BACKUP (IMPORTANTE!)
# Copiar archivos actuales:
invoice-pdf-fpdf.php → invoice-pdf-fpdf.php.backup-timezone
quotation-pdf-fpdf.php → quotation-pdf-fpdf.php.backup-timezone

# 4. SUBIR archivos modificados desde local:
Local: C:\wamp64\www\automatiza-tech\wp-content\themes\automatiza-tech\lib\invoice-pdf-fpdf.php
Servidor: /public_html/wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php

Local: C:\wamp64\www\automatiza-tech\wp-content\themes\automatiza-tech\lib\quotation-pdf-fpdf.php
Servidor: /public_html/wp-content/themes/automatiza-tech/lib/quotation-pdf-fpdf.php
```

### Via cPanel File Manager
```
1. Ir a File Manager
2. Navegar a: public_html/wp-content/themes/automatiza-tech/lib/
3. Hacer clic derecho en invoice-pdf-fpdf.php → Copy → Agregar .backup-timezone
4. Hacer clic derecho en quotation-pdf-fpdf.php → Copy → Agregar .backup-timezone
5. Upload → Seleccionar archivos locales modificados
6. Sobrescribir los archivos existentes
```

## ✅ Verificar que Funciona

### Prueba 1: Factura
1. Ir a WordPress Admin → Clientes Contratados
2. Contratar un cliente de prueba
3. Descargar la factura PDF
4. **Verificar:** Fecha y hora deben ser correctas de Chile

### Prueba 2: Cotización
1. Ir a WordPress Admin → Contactos
2. Crear un contacto y cotización
3. Descargar la cotización PDF
4. **Verificar:** Fecha de emisión debe ser correcta de Chile

## 📋 Checklist de Despliegue

- [ ] Backup de `invoice-pdf-fpdf.php` creado en servidor
- [ ] Backup de `quotation-pdf-fpdf.php` creado en servidor
- [ ] Archivo `invoice-pdf-fpdf.php` subido correctamente
- [ ] Archivo `quotation-pdf-fpdf.php` subido correctamente
- [ ] (Opcional) `wp-config.php` modificado con timezone
- [ ] Factura de prueba generada → hora correcta ✅
- [ ] Cotización de prueba generada → hora correcta ✅
- [ ] Backups eliminados si todo funciona

## ⚠️ Notas Importantes

- ✅ **PDFs nuevos** tendrán hora correcta automáticamente
- ❌ **PDFs existentes** siguen con hora antigua (son archivos estáticos)
- ⚙️ La configuración se aplica en el constructor, cada vez que se crea un PDF
- 🔄 No afecta otros sistemas de WordPress
- 📊 No requiere cambios en la base de datos

## 🆘 Si Algo Sale Mal

### Restaurar Backups
```bash
# Via File Manager o FTP
cp invoice-pdf-fpdf.php.backup-timezone invoice-pdf-fpdf.php
cp quotation-pdf-fpdf.php.backup-timezone quotation-pdf-fpdf.php
```

### Verificar Sintaxis
```bash
# Via SSH (si tienes acceso)
php -l /home/u187918280/domains/automatizatech.shop/public_html/wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php
```

### Limpiar Caché
```
- Ctrl+F5 en el navegador
- Limpiar caché de WordPress (si usa plugin)
- Esperar 1-2 minutos para que el servidor actualice
```

## 🔍 Cómo Funciona

### Zona Horaria de Chile
```
Código: America/Santiago
UTC-3: Horario de verano (Sep-Abr)
UTC-4: Horario estándar (Abr-Sep)
Cambio automático: PHP lo maneja
```

### Ejecución
```php
// Constructor se ejecuta cada vez que se crea un PDF
public function __construct(...) {
    // Configura timezone antes de usar date()
    date_default_timezone_set('America/Santiago');
    // Ahora date() y current_time() usan hora de Chile
}
```

## 📚 Documentación Relacionada

- [CONFIGURAR-TIMEZONE-CHILE.md](CONFIGURAR-TIMEZONE-CHILE.md) - Guía completa de configuración
- [set-timezone-chile.php](set-timezone-chile.php) - Script de configuración automática

---

**Fecha de actualización:** 16/11/2025  
**Estado:** ✅ Modificado y verificado localmente  
**Pendiente:** 📤 Subir a producción  
**Tiempo estimado:** 5 minutos  
**Riesgo:** Bajo (con backup)  
**Impacto:** Alto - Corrige todas las facturas y cotizaciones nuevas
