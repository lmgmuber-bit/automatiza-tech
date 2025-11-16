# 📦 ARCHIVOS PARA SUBIR A PRODUCCIÓN - VALIDACIÓN RUT + TAX ID EN FACTURAS
**Fecha:** 2025-11-16
**Cambios:** Validación de RUT inline + Campo tax_id en facturas + Botones ocultos

---

## 🔧 CAMBIOS REALIZADOS

### 1. Validación de RUT en Formulario de Contacto
- ✅ Campo RUT con validación en tiempo real (inline)
- ✅ Formato automático con guión (ejemplo: 26191807-2)
- ✅ Validación del dígito verificador
- ✅ Máximo 10 caracteres (RUT completo con guión)
- ✅ Bloqueo de envío si el RUT no es válido
- ✅ Mensajes de error en tiempo real

### 2. Base de Datos
- ✅ Campo `tax_id` agregado a tabla `Contactos` (`contacts`)
- ✅ Campo `tax_id` agregado a tabla `Clientes` (`clients`)
- ⚠️ **IMPORTANTE:** Debes ejecutar el script SQL en PRODUCCIÓN
- ✅ El RUT se guarda automáticamente al enviar formulario
- ✅ El RUT se transfiere cuando un contacto se convierte en cliente
- ✅ El RUT aparece en las facturas PDF generadas

### 3. Interfaz de Administración
- ✅ Botón "Regenerar Facturas con QR" oculto en sección de Contactos
- ✅ Botón "Regenerar QR de Facturas" oculto en sección de Clientes
- ✅ Los botones están comentados y pueden reactivarse fácilmente

### 4. Rate Limiting (Control de Envíos)
- ✅ Sistema de límite de intentos por IP
- ✅ Máximo 3 intentos por hora
- ✅ Script para limpiar rate limit: `clear-rate-limit.php`

---

## 📁 ARCHIVOS A SUBIR A PRODUCCIÓN

### **⚠️ ORDEN CRÍTICO DE EJECUCIÓN**

**PASO 1: Base de Datos PRIMERO**
```sql
-- Ejecutar en phpMyAdmin de PRODUCCIÓN
-- Agregar campo tax_id a tabla clients (clientes)
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS tax_id VARCHAR(20) NULL AFTER country;

-- Verificar que se creó
SHOW COLUMNS FROM clients LIKE 'tax_id';
```

**PASO 2: Subir Archivos DESPUÉS**

### **CRÍTICO - ARCHIVOS PRINCIPALES**

```
wp-content/themes/automatiza-tech/inc/contact-form.php
```
**Cambios:**
- Validación de RUT inline
- Campo tax_id en formulario
- Guardado de RUT en base de datos
- Transferencia de RUT a tabla clientes
- Botones de regenerar facturas ocultos (comentados)

```
wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php
```
**Cambios:**
- ✅ **YA ESTÁ ACTUALIZADO EN PRODUCCIÓN** (verificado línea 302)
- Campo RUT/DNI del cliente en factura PDF
- Muestra el tax_id del cliente en la sección "DATOS DEL CLIENTE"
- Adapta el label según país (RUT para Chile, RUT/DNI/Pasaporte para otros)
- **NO ES NECESARIO SUBIRLO DE NUEVO**

---

### **NUEVO ARCHIVO - SCRIPT DE LIMPIEZA**

```
clear-rate-limit.php
```
**Función:** Limpiar límite de intentos de envío del formulario
**Uso:** Ejecutar directamente desde navegador cuando sea necesario
**URL de acceso:** `https://automatizatech.shop/clear-rate-limit.php`

---

### **OPCIONAL - SCRIPT DE DEBUG**

```
debug-form-error.php
```
**Función:** Verificar y crear campos tax_id en tablas Contactos y Clientes
**Uso:** Ejecutar UNA VEZ en producción para verificar estructura de BD
**URL de acceso:** `https://automatizatech.shop/debug-form-error.php`

---

## 🗄️ CAMBIOS EN BASE DE DATOS

### **⚠️ EJECUTAR PRIMERO - ANTES DE SUBIR ARCHIVOS:**

```sql
-- 1. Agregar campo tax_id a tabla Clientes (clients)
-- NOTA: Verifica el nombre exacto de tu tabla en producción
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS tax_id VARCHAR(20) NULL AFTER country;

-- 2. Verificar que el campo se creó correctamente
SHOW COLUMNS FROM clients LIKE 'tax_id';

-- 3. (OPCIONAL) Si también necesitas en tabla contacts:
ALTER TABLE contacts 
ADD COLUMN IF NOT EXISTS tax_id VARCHAR(20) NULL AFTER phone;

SHOW COLUMNS FROM contacts LIKE 'tax_id';
```

**⚠️ IMPORTANTE:** 
- Verifica el nombre de tus tablas en producción
- Algunas instalaciones usan prefijos como `wp_`, `wp_automatiza_tech_`, etc.
- Ajusta las consultas según corresponda
- **NO continúes al siguiente paso hasta confirmar que el campo existe**

---

## 📋 CHECKLIST DE DESPLIEGUE

### **ANTES DE SUBIR**
- [ ] Hacer backup completo de producción
- [ ] Backup de base de datos de producción
- [ ] Verificar que el servidor tiene PHP 7.4+

### **SUBIR ARCHIVOS**
- [ ] Subir `contact-form.php` a `/wp-content/themes/automatiza-tech/inc/`
- [ ] Subir `clear-rate-limit.php` a raíz del sitio `/`
- [ ] (Opcional) Subir `debug-form-error.php` a raíz del sitio `/`

### **CONFIGURAR BASE DE DATOS**
- [ ] Conectar a phpMyAdmin de producción
- [ ] **PRIMERO:** Ejecutar `ALTER TABLE clients ADD COLUMN...` 
- [ ] **VERIFICAR:** Ejecutar `SHOW COLUMNS FROM clients LIKE 'tax_id';`
- [ ] **CONFIRMAR:** El campo aparece en la estructura de la tabla
- [ ] **SOLO DESPUÉS:** Continuar con subida de archivos

### **VERIFICAR FUNCIONAMIENTO**
- [ ] **Base de Datos:** Confirmar que campo `tax_id` existe en tabla `clients`
- [ ] **Formulario:** Acceder a `https://automatizatech.shop/#contacto`
- [ ] **RUT Válido:** Probar con `261918072` (debe formatear a `26191807-2`)
- [ ] **RUT Inválido:** Probar con `12345678-9` (debe mostrar error rojo)
- [ ] **Envío:** Verificar que RUT se guarda en BD
- [ ] **Factura:** Generar factura y verificar que aparece el RUT
- [ ] **Admin:** Verificar que NO se muestran botones de regenerar facturas
- [ ] **Rate Limit:** Si hay problema, usar `https://automatizatech.shop/clear-rate-limit.php`

### **SI HAY PROBLEMAS**
- [ ] Ejecutar `https://automatizatech.shop/debug-form-error.php` para diagnosticar
- [ ] Si se excede límite de intentos, ejecutar `https://automatizatech.shop/clear-rate-limit.php`
- [ ] Revisar logs de errores de PHP en el servidor
- [ ] Verificar permisos de archivos (644 para PHP, 755 para directorios)

---

## 🔄 CÓMO REACTIVAR BOTONES DE REGENERAR FACTURAS

Si en el futuro necesitas reactivar los botones que ocultamos:

1. Editar: `wp-content/themes/automatiza-tech/inc/contact-form.php`

2. Buscar la línea **3883** (Sección Contactos):
```php
<?php /* 
// Botón de regenerar facturas desactivado
<button type="button" id="regenerate-invoices-qr"...
*/?>
```

3. Quitar los comentarios `<?php /* ... */ ?>`:
```php
<?php if (current_user_can('administrator')): ?>
<button type="button" id="regenerate-invoices-qr"...
<?php endif; ?>
```

4. Hacer lo mismo en la línea **5452** (Sección Clientes)

---

## 📞 INFORMACIÓN DE TABLAS

**Tabla Clientes:** `clients` (o `wp_automatiza_tech_clients`)
- Campo agregado: `tax_id` VARCHAR(20)
- Posición: Después del campo `country`
- **CRÍTICO:** Este campo debe existir ANTES de subir archivos

**Tabla Contactos:** `contacts` (o `wp_automatiza_tech_contacts`)
- Campo agregado: `tax_id` VARCHAR(20)
- Posición: Después del campo `phone`
- Opcional, pero recomendado

---

## ⚠️ SOLUCIÓN AL PROBLEMA "FACTURA SIN RUT"

Si la factura NO muestra el RUT del cliente:

**CAUSA:** El campo `tax_id` no existe en la tabla `clients`

**SOLUCIÓN:**
1. Ir a phpMyAdmin de producción
2. Ejecutar:
```sql
ALTER TABLE clients ADD COLUMN IF NOT EXISTS tax_id VARCHAR(20) NULL AFTER country;
```
3. Verificar:
```sql
SHOW COLUMNS FROM clients LIKE 'tax_id';
```
4. Regenerar la factura del cliente

**VERIFICAR CÓDIGO (línea 302 de invoice-pdf-fpdf.php):**
```php
$this->Cell(0, 5, utf8_to_latin1(!empty($this->client_data->tax_id) ? $this->client_data->tax_id : 'N/A'), 0, 1, 'L');
```

Si el código está correcto pero aún no funciona, el problema es 100% el campo faltante en BD.

---

## ⚠️ NOTAS IMPORTANTES

1. **Rate Limiting:** El sistema limita a 3 intentos de envío por hora por IP. Para resetear, usar `clear-rate-limit.php`

2. **Validación de RUT:**
   - Solo acepta números chilenos de 7-8 dígitos + dígito verificador
   - Formato automático: agrega guión automáticamente
   - Validación en tiempo real (no espera a enviar formulario)

3. **Botones Ocultos:**
   - Los botones NO están eliminados, solo comentados
   - Se pueden reactivar fácilmente si es necesario
   - No afecta ninguna funcionalidad existente

4. **Compatibilidad:**
   - Funciona con todos los navegadores modernos
   - Compatible con móviles y tablets
   - No requiere librerías adicionales

---

## 🎯 RESULTADO ESPERADO

Después del despliegue:

✅ **Formulario de Contacto:**
- Campo RUT visible y funcional
- Validación en tiempo real
- Formateo automático con guión
- Bloqueo de envío si RUT inválido

✅ **Base de Datos:**
- Campo tax_id en tabla Contactos
- Campo tax_id en tabla Clientes
- RUT guardado correctamente al enviar formulario

✅ **Panel de Administración:**
- Botón "Regenerar Facturas" NO visible en Contactos
- Botón "Regenerar QR" NO visible en Clientes
- Resto de funcionalidades intactas

---

## 🚀 COMANDOS RÁPIDOS

### Verificar campos en BD (phpMyAdmin):
```sql
DESCRIBE wp_automatiza_tech_contacts;
DESCRIBE wp_automatiza_tech_clients;
```

### Ver RUTs guardados:
```sql
SELECT id, name, email, tax_id FROM clients 
WHERE tax_id IS NOT NULL;

-- Si tienes prefijo en las tablas:
SELECT id, name, email, tax_id FROM wp_automatiza_tech_clients 
WHERE tax_id IS NOT NULL;
```

### Actualizar RUT de un cliente específico:
```sql
UPDATE clients SET tax_id = '26191807-2' WHERE id = 1;
```

---

**📅 Última actualización:** 2025-11-16  
**👨‍💻 Preparado para:** Despliegue en Producción  
**🔧 Versión:** 3.0 - Fix: RUT en Facturas + Validación Inline  
**✅ Estado:** invoice-pdf-fpdf.php YA está correcto en PROD (verificado)
