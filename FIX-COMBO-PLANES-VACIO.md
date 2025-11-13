# 🚀 FIX: Combo de Planes Vacío - CORREGIDO

## ❌ Problema Identificado

El combo de planes en el modal de conversión estaba vacío porque:

1. **El método `get_available_plans()` NO EXISTÍA** en `inc/contact-form.php`
2. **El action AJAX no estaba registrado** en WordPress
3. WordPress devolvía solo: `{"wp-auth-check":true,"server_time":1762915094}`
4. **Planes 4, 5, 6 tienen precios en 0** (price_clp=0, price_usd=0)

---

## ✅ Solución Implementada

### 1. Agregado Hook AJAX en Constructor

```php
add_action('wp_ajax_get_available_plans', array($this, 'get_available_plans'));
```

### 2. Creado Método `get_available_plans()`

```php
/**
 * Obtener lista de planes disponibles para el combo
 */
public function get_available_plans() {
    // Limpiar cualquier output previo
    if (ob_get_level()) {
        ob_clean();
    }
    
    global $wpdb;
    
    // Obtener planes activos con precios definidos
    $plans = $wpdb->get_results("
        SELECT id, name, description, price_clp, price_usd
        FROM {$wpdb->prefix}automatiza_services
        WHERE status = 'active'
        AND (price_clp > 0 OR price_usd > 0)
        ORDER BY id ASC
    ");
    
    if (!$plans) {
        wp_send_json_error('No hay planes disponibles');
        wp_die();
    }
    
    wp_send_json_success($plans);
    wp_die();
}
```

**Características:**
- ✅ Filtra planes activos (status = 'active')
- ✅ **Solo devuelve planes con precio definido** (price_clp > 0 OR price_usd > 0)
- ✅ Ordena por ID ascendente (eliminado display_order que no existe)
- ✅ Devuelve JSON con estructura correcta para el combo

### 3. Corregida Query en Modal HTML

**Query anterior (INCORRECTA):**
```sql
SELECT id, name, price, description 
FROM wp_automatiza_services 
WHERE category = 'pricing' AND status = 'active' 
ORDER BY display_order ASC
-- ❌ Campo 'price' no existe
-- ❌ Campo 'display_order' no existe
-- ❌ Campo 'category' no aplica
```

**Query corregida (CORRECTA):**
```sql
SELECT id, name, price_clp, price_usd, description 
FROM wp_automatiza_services 
WHERE status = 'active' 
AND (price_clp > 0 OR price_usd > 0) 
ORDER BY id ASC
-- ✅ Usa price_clp y price_usd
-- ✅ Sin display_order
-- ✅ Sin category
```

### 4. Actualizado JavaScript del Preview

**Antes:**
```javascript
data-price  // ❌ Atributo inexistente
```

**Después:**
```javascript
data-price-clp="${plan->price_clp}"
data-price-usd="${plan->price_usd}"
// ✅ Ambas monedas disponibles
```

---

## 📋 Pasos para Desplegar en Producción

### Paso 1: Subir Archivo Corregido

**Archivo modificado:**
```
wp-content/themes/automatiza-tech/inc/contact-form.php
```

**Opciones para subir:**

#### Opción A: FileZilla (FTP)
1. Conecta a tu servidor Hostinger
2. Navega a: `/home/u187918280/domains/automatizatech.shop/public_html/wp-content/themes/automatiza-tech/inc/`
3. Sube `contact-form.php`
4. Sobrescribe el archivo existente

#### Opción B: Administrador de Archivos Hostinger
1. Accede a hPanel → Administrador de Archivos
2. Navega a: `public_html/wp-content/themes/automatiza-tech/inc/`
3. Clic derecho en `contact-form.php` → Eliminar
4. Sube el nuevo `contact-form.php`

---

### Paso 2: Asignar Precios a Planes 4, 5, 6

**Ejecuta estos comandos SQL en phpMyAdmin:**

```sql
-- Plan 4: Atención 24/7
UPDATE wp_automatiza_services 
SET price_clp = 150000, 
    price_usd = 171.43,
    description = 'Soporte y atención al cliente 24 horas, 7 días a la semana'
WHERE id = 4;

-- Plan 5: Aumenta tus Ventas
UPDATE wp_automatiza_services 
SET price_clp = 200000, 
    price_usd = 228.57,
    description = 'Estrategias y herramientas para incrementar tus ventas online'
WHERE id = 5;

-- Plan 6: Fácil Integración
UPDATE wp_automatiza_services 
SET price_clp = 180000, 
    price_usd = 205.71,
    description = 'Integración simple y rápida con tus sistemas existentes'
WHERE id = 6;
```

**O ejecuta todo desde el archivo:**
```bash
# Opción: Ejecutar SQL desde archivo
mysql -u u187918280_automatiza -p u187918280_automatiza < fix-planes-sin-precio.sql
```

---

### Paso 3: Verificar en Producción

**3.1. Limpia caché:**
```sql
DELETE FROM wp_options WHERE option_name LIKE '%transient%';
DELETE FROM wp_options WHERE option_name LIKE '%cache%';
```

**3.2. Verifica planes en BD:**
```sql
SELECT id, name, status, price_clp, price_usd
FROM wp_automatiza_services
WHERE status = 'active'
ORDER BY id ASC;
```

**Resultado esperado: 7 planes activos con precios definidos**

**3.3. Prueba AJAX en navegador:**

Abre DevTools (F12) → Console → Ejecuta:

```javascript
fetch('https://automatizatech.shop/wp-admin/admin-ajax.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/x-www-form-urlencoded'},
  body: 'action=get_available_plans'
})
.then(r => r.json())
.then(data => {
  console.log('✅ AJAX Response:', data);
  if(data.success && data.data.length > 0) {
    console.log('✅ Planes disponibles:', data.data.length);
  }
});
```

**Respuesta esperada:**
```json
{
  "success": true,
  "data": [
    {"id": "1", "name": "Plan Básico", "price_clp": "79200", "price_usd": "99.00", ...},
    {"id": "2", "name": "Plan Profesional", "price_clp": "159200", "price_usd": "199.00", ...},
    {"id": "3", "name": "Plan Enterprise", "price_clp": "319200", "price_usd": "399.00", ...},
    {"id": "4", "name": "Atención 24/7", "price_clp": "150000", "price_usd": "171.43", ...},
    {"id": "5", "name": "Aumenta tus Ventas", "price_clp": "200000", "price_usd": "228.57", ...},
    {"id": "6", "name": "Fácil Integración", "price_clp": "180000", "price_usd": "205.71", ...},
    {"id": "7", "name": "Web + WhatsApp Business", "price_clp": "239200", "price_usd": "299.00", ...}
  ]
}
```

---

### Paso 4: Probar Conversión Contacto → Cliente

1. Ve a: **Panel CRM → Contactos**
2. Clic en cualquier contacto → **"Convertir a Cliente"**
3. **✅ El combo de planes ahora debe mostrar las 7 opciones**
4. Selecciona un plan
5. Completa datos y guarda
6. Verifica que se genere la factura PDF

---

## 🔍 Antes vs Después

### ❌ ANTES (Problema)

```json
// Respuesta AJAX incorrecta
{"wp-auth-check":true,"server_time":1762915094}

// Combo vacío
<select id="plan_id">
  <option value="">-- Selecciona un plan --</option>
  <!-- SIN OPCIONES -->
</select>
```

### ✅ DESPUÉS (Corregido)

```json
// Respuesta AJAX correcta
{
  "success": true,
  "data": [
    {"id": "1", "name": "Plan Básico", ...},
    {"id": "2", "name": "Plan Profesional", ...},
    // ... 7 planes total
  ]
}

// Combo poblado
<select id="plan_id">
  <option value="">-- Selecciona un plan --</option>
  <option value="1">Plan Básico - $99.00 USD / $79.200 CLP</option>
  <option value="2">Plan Profesional - $199.00 USD / $159.200 CLP</option>
  <option value="3">Plan Enterprise - $399.00 USD / $319.200 CLP</option>
  <option value="4">Atención 24/7 - $171.43 USD / $150.000 CLP</option>
  <option value="5">Aumenta tus Ventas - $228.57 USD / $200.000 CLP</option>
  <option value="6">Fácil Integración - $205.71 USD / $180.000 CLP</option>
  <option value="7">Web + WhatsApp Business - $299.00 USD / $239.200 CLP</option>
</select>
```

---

## 📊 Resumen de Cambios

| Componente | Estado Anterior | Estado Actual |
|-----------|----------------|---------------|
| Método `get_available_plans()` | ❌ No existía | ✅ Creado |
| Hook AJAX | ❌ No registrado | ✅ Registrado |
| Respuesta AJAX | `{"wp-auth-check":true}` | `{"success":true,"data":[...]}` |
| Planes en combo | 0 opciones | 7 opciones |
| Planes 4, 5, 6 | Precios en 0 | Precios asignados |

---

## ⚠️ Notas Importantes

1. **El filtro `AND (price_clp > 0 OR price_usd > 0)` es intencional**
   - Solo muestra planes con precios definidos
   - Evita errores en facturación
   - Si necesitas planes sin precio, elimina esta condición

2. **Precios sugeridos para planes 4, 5, 6**
   - Puedes ajustarlos según tu estrategia comercial
   - Mantén coherencia con la moneda (CLP ~875 por USD)

3. **Caché de WordPress**
   - Si no ves cambios inmediatos, limpia caché
   - Ctrl+Shift+R en navegador (recarga forzada)

---

## 🎯 Próximos Pasos Sugeridos

1. ✅ **Subir contact-form.php corregido a producción**
2. ✅ **Ejecutar SQL para asignar precios**
3. ✅ **Probar combo de planes**
4. 📁 **Crear carpeta /invoices/** (para PDFs de facturas)
5. 📄 **Probar generación completa de factura PDF**

---

## 📞 Si Necesitas Ayuda

- El combo sigue vacío después de subir el archivo
- Errores en consola JavaScript
- Problemas con facturación
- Ajustar precios de planes

¡El sistema ya está listo para funcionar correctamente! 🚀
