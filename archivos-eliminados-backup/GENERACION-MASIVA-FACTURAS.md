# 📄 Sistema de Generación Masiva de Facturas

## 🎯 Descripción

Este sistema permite generar facturas automáticamente para **todos los clientes que estén en estado "contratado"** en un solo click.

---

## 🚀 Cómo Usar

### Opción 1: Interfaz Web (Recomendado)

1. **Acceder al generador:**
   ```
   http://localhost/automatiza-tech/generate-invoices-for-contracted.php
   ```
   O en producción:
   ```
   https://automatizatech.shop/generate-invoices-for-contracted.php
   ```

2. **El sistema automáticamente:**
   - ✅ Busca todos los clientes con estado "contracted"
   - ✅ Verifica si ya tienen factura generada
   - ✅ Genera facturas solo para los que no tienen
   - ✅ Guarda en base de datos con todos los datos
   - ✅ Genera código QR de validación
   - ✅ Muestra estadísticas detalladas

3. **Interfaz visual:**
   - 📊 Estadísticas en tarjetas (Procesados, Creadas, Ya existían, Errores)
   - 📋 Listado detallado de cada factura generada
   - 🎨 Diseño profesional con colores corporativos
   - 🔗 Botones para ir a panel de clientes o preview

### Opción 2: Línea de Comandos

```bash
cd C:\wamp64\www\automatiza-tech
php generate-invoices-for-contracted.php
```

**Salida en CLI:**
```
🔍 Buscando clientes contratados...
✅ Se encontraron 5 clientes contratados
✅ María González - AT-20251111-0001 - Factura generada exitosamente
📄 Juan Pérez - AT-20251110-0002 - Ya existe
✅ Carlos López - AT-20251111-0003 - Factura generada exitosamente
❌ Ana Martínez - AT-20251111-0004 - Error: No tiene plan asignado
✅ Luis Rodríguez - AT-20251111-0005 - Factura generada exitosamente
🎉 Proceso completado: 3 factura(s) generada(s), 1 ya existían, 1 error(es)
```

---

## 📊 Estadísticas Mostradas

| Métrica | Descripción |
|---------|-------------|
| **Clientes Procesados** | Total de clientes contratados encontrados |
| **Facturas Creadas** | Nuevas facturas generadas exitosamente |
| **Ya Existían** | Facturas que ya estaban en el sistema |
| **Errores** | Clientes que no pudieron procesarse |

---

## 🔍 Verificar Clientes

Antes de generar facturas, puedes verificar el estado de los clientes:

```bash
php check-clients.php
```

**Salida:**
```
=== VERIFICACIÓN DE CLIENTES ===

📊 Total de clientes: 5

📈 Clientes por estado:
   ✅ Contratados: 4
   📞 Contactados: 1

📋 Últimos 10 clientes:
   ID: 5
   Nombre: Luis Rodríguez
   Email: luis@example.com
   Estado: contracted
   Plan ID: 2
   Contratado: 2025-11-11 14:30:00
   ---

⚠️  Clientes contratados SIN factura: 3
   - María González (ID: 1, Plan: 1)
   - Carlos López (ID: 3, Plan: 2)
   - Luis Rodríguez (ID: 5, Plan: 3)
```

---

## 🛠️ Proceso Técnico

### 1. **Búsqueda de Clientes**
```sql
SELECT * FROM wp_automatiza_tech_clients 
WHERE status = 'contracted'
ORDER BY contracted_at DESC
```

### 2. **Generación de Número de Factura**
```php
$invoice_number = 'AT-' . date('Ymd', strtotime($client->contracted_at)) 
                . '-' . str_pad($client_id, 4, '0', STR_PAD_LEFT);
// Ejemplo: AT-20251111-0001
```

### 3. **Verificación de Existencia**
```sql
SELECT id, invoice_number 
FROM wp_automatiza_tech_invoices 
WHERE invoice_number = 'AT-20251111-0001'
```

### 4. **Obtención de Datos del Plan**
```sql
SELECT * FROM wp_automatiza_services 
WHERE id = {plan_id} AND status = 'active'
```

### 5. **Generación de HTML**
- Logo AutomatizaTech (110px)
- Información del cliente y factura
- Tabla de servicios con características
- Cálculo de IVA (19%)
- Código QR de validación (120x120px)
- Footer en 3 columnas optimizado para A4

### 6. **Guardado en Base de Datos**
```sql
INSERT INTO wp_automatiza_tech_invoices (
    invoice_number,
    client_id,
    client_name,
    client_email,
    plan_id,
    plan_name,
    subtotal,
    iva,
    total,
    invoice_html,
    qr_code_data,
    created_at,
    status
) VALUES (...)
```

---

## ⚙️ Configuración

### Requisitos:
- ✅ WordPress instalado
- ✅ Tema AutomatizaTech activo
- ✅ Tabla `wp_automatiza_tech_clients` creada
- ✅ Tabla `wp_automatiza_tech_invoices` creada
- ✅ Tabla `wp_automatiza_services` con planes activos
- ✅ Librería QR Code (`lib/qrcode.php`)
- ✅ Logo (`assets/images/logo-automatiza-tech.png`)

### Variables configurables:
- **IVA:** 19% (Chile) - Línea 283
- **Colores:** `$primary_color`, `$secondary_color` - Líneas 281-283
- **Tamaño QR:** 120px - Línea 489
- **Validez factura:** 30 días - Línea 410

---

## 📋 Casos de Uso

### 1. **Facturación Inicial**
Cuando acabas de implementar el sistema y tienes clientes contratados sin facturas:
```bash
php generate-invoices-for-contracted.php
```
**Resultado:** Todas las facturas se generan a la vez.

### 2. **Facturación Periódica**
Ejecutar semanalmente o mensualmente para generar facturas de nuevos clientes:
```
Acceder a: generate-invoices-for-contracted.php
```
**Resultado:** Solo se generan las facturas nuevas (las existentes se saltan).

### 3. **Verificación de Pendientes**
Revisar qué clientes contratados no tienen factura:
```bash
php check-clients.php
```
**Resultado:** Lista de clientes sin factura.

### 4. **Regeneración Individual**
Si necesitas regenerar una factura específica:
1. Borrar la factura de la BD: `DELETE FROM wp_automatiza_tech_invoices WHERE invoice_number = 'AT-...'`
2. Ejecutar el generador masivo
3. Se regenerará solo esa factura

---

## 🎨 Interfaz Web

### Características:
- ✨ **Diseño moderno** con gradientes corporativos
- 📊 **4 tarjetas de estadísticas** grandes y coloridas
- 📋 **Listado detallado** de cada factura procesada
- 🎯 **Iconos descriptivos** para cada estado
- 🔗 **Botones de acción** para navegar al panel
- 📱 **Responsive** (funciona en móviles)

### Estados visuales:
- ✅ **Verde:** Factura creada exitosamente
- 📄 **Naranja:** Ya existía
- ❌ **Rojo:** Error en el proceso

### Información por factura:
```
✅ María González
Factura: AT-20251111-0001
Estado: Factura generada exitosamente
Total: $2.380.000
```

---

## 🔒 Seguridad

### Validaciones implementadas:
1. ✅ Verificación de existencia de factura (no duplicados)
2. ✅ Validación de plan activo
3. ✅ Escape de HTML para prevenir XSS
4. ✅ Prepared statements (prevención SQL injection)
5. ✅ Verificación de cliente contratado

### Errores capturados:
- Cliente sin plan asignado
- Plan no encontrado o inactivo
- Error al guardar en BD
- Excepciones durante generación HTML

---

## 📤 Integración con Sistema Existente

Este generador se integra perfectamente con:

1. **Panel de Clientes:** `admin.php?page=automatiza-tech-clients`
   - Columna "📄 Factura" muestra botones para descargar
   - Enlaza directamente a `validar-factura.php`

2. **Sistema de Validación:** `validar-factura.php`
   - QR codes generados apuntan aquí
   - Permite descargar factura validada

3. **Preview de Facturas:** `test-invoice-preview.php`
   - Previsualización antes de producción
   - Prueba de diseño y datos

4. **Correo Automático:** `inc/contact-form.php`
   - Al mover contacto a "contratado" se envía factura por email
   - Este generador es complementario (para casos masivos)

---

## 🐛 Troubleshooting

### Problema: "No se encontraron clientes contratados"
**Solución:**
1. Verificar con: `php check-clients.php`
2. Asegurarse de que clientes tienen `status = 'contracted'`
3. Mover contactos a "Contratado" desde el panel admin

### Problema: "Plan no encontrado o inactivo"
**Solución:**
1. Verificar planes activos: `php activate-plans.php`
2. Asignar plan_id correcto al cliente
3. Revisar tabla `wp_automatiza_services`

### Problema: "Error al guardar en BD"
**Solución:**
1. Verificar tabla existe: `php create-invoices-table.php`
2. Revisar permisos de base de datos
3. Check `$wpdb->last_error` en el output

### Problema: "QR Code no se genera"
**Solución:**
1. Verificar librería: `lib/qrcode.php` existe
2. Check API externa: `https://api.qrserver.com`
3. Fallback automático en caso de error

---

## 📝 Logs y Debugging

### Activar modo debug:
En `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Revisar logs:
```
wp-content/debug.log
```

### Output detallado:
El script muestra mensajes descriptivos para cada operación:
- 🔍 Búsqueda
- ✅ Éxito
- ⚠️ Advertencia
- ❌ Error

---

## 🚀 Automatización

### Cron Job (Linux/Mac):
```bash
# Ejecutar cada lunes a las 9:00 AM
0 9 * * 1 cd /var/www/html/automatiza-tech && php generate-invoices-for-contracted.php
```

### Task Scheduler (Windows):
1. Abrir "Programador de tareas"
2. Crear tarea básica
3. Trigger: Semanalmente, lunes 9:00 AM
4. Acción: Iniciar programa
5. Programa: `C:\wamp64\bin\php\php8.3.0\php.exe`
6. Argumentos: `C:\wamp64\www\automatiza-tech\generate-invoices-for-contracted.php`

---

## 📊 Reportes

### Facturas generadas hoy:
```sql
SELECT COUNT(*) as facturas_hoy
FROM wp_automatiza_tech_invoices
WHERE DATE(created_at) = CURDATE();
```

### Total facturado:
```sql
SELECT SUM(total) as total_facturado
FROM wp_automatiza_tech_invoices
WHERE status = 'active';
```

### Clientes sin factura:
```sql
SELECT c.id, c.name, c.email
FROM wp_automatiza_tech_clients c
LEFT JOIN wp_automatiza_tech_invoices i ON CONCAT('AT-', DATE_FORMAT(c.contracted_at, '%Y%m%d'), '-', LPAD(c.id, 4, '0')) = i.invoice_number
WHERE c.status = 'contracted' AND i.id IS NULL;
```

---

## ✨ Ventajas del Sistema

✅ **Procesamiento masivo** - Genera cientos de facturas en segundos  
✅ **Sin duplicados** - Verifica existencia antes de crear  
✅ **Interfaz visual** - Fácil de usar desde el navegador  
✅ **CLI disponible** - Perfecto para automatización  
✅ **Estadísticas en tiempo real** - Feedback inmediato  
✅ **Manejo de errores** - Continúa aunque falle una factura  
✅ **Integración completa** - Funciona con todo el sistema existente  
✅ **Optimizado para A4** - Facturas listas para imprimir  
✅ **QR de validación** - Autenticidad verificable  

---

## 📞 Soporte

Para problemas o mejoras:
- **Email:** contacto@automatizatech.cl
- **Teléfono:** +56 9 6432 4169
- **Documentación:** Ver archivos `.md` en el proyecto

---

**Versión:** 1.0  
**Fecha:** Noviembre 2025  
**Sistema:** AutomatizaTech Facturación Masiva
