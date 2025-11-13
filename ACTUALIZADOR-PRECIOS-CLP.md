# 💱 Sistema de Actualización Automática de Precios CLP

**Versión:** 1.0.0  
**Fecha:** 13 de Noviembre de 2025  
**Autor:** AutomatizaTech Development Team

---

## 📋 Índice

1. [Descripción General](#descripción-general)
2. [Características](#características)
3. [Fuentes Oficiales](#fuentes-oficiales)
4. [Instalación](#instalación)
5. [Configuración](#configuración)
6. [Uso del Sistema](#uso-del-sistema)
7. [Panel de Administración](#panel-de-administración)
8. [Funcionamiento Técnico](#funcionamiento-técnico)
9. [Troubleshooting](#troubleshooting)
10. [API Reference](#api-reference)

---

## 📖 Descripción General

Sistema automático que actualiza diariamente los precios en **CLP (Pesos Chilenos)** de todos los servicios/planes basándose en el tipo de cambio oficial **USD/CLP**.

### ¿Por qué es necesario?

- Los precios base están definidos en **USD** (dólares americanos)
- El tipo de cambio USD/CLP varía constantemente
- Los clientes chilenos pagan en **CLP**, por lo que los precios deben reflejar el valor actual
- Mantiene los precios competitivos y justos según el mercado

### Ventajas

✅ **100% Automático** - Se ejecuta diariamente sin intervención manual  
✅ **Fuente Oficial** - Usa datos del Banco Central de Chile  
✅ **Fallback Inteligente** - Si falla la API principal, usa fuente alternativa  
✅ **Control Manual** - Permite actualizar precios manualmente desde el admin  
✅ **Logs Detallados** - Registra cada cambio para auditoría  
✅ **Umbral de Actualización** - Solo actualiza si el cambio es significativo (>2%)

---

## 🎯 Características

### Actualización Automática

- **Frecuencia:** Diaria
- **Hora:** 8:00 AM (hora de Chile, UTC-3)
- **Ejecución:** WordPress Cron (wp-cron.php)
- **Silenciosa:** No requiere intervención

### Cálculo Inteligente

- **Redondeo:** Múltiplos de $1.000 CLP (precios más limpios)
- **Umbral:** Solo actualiza si la diferencia es ≥ 2%
- **Preserva USD:** Los precios en dólares nunca se modifican

### Seguridad

- **Validación:** Verifica que el tipo de cambio sea válido (> 0)
- **Respaldo:** Guarda el último tipo de cambio conocido
- **Logs:** Registra todas las operaciones en WordPress error_log

---

## 🏦 Fuentes Oficiales

### Principal: Banco Central de Chile (mindicador.cl)

**API:** `https://mindicador.cl/api/dolar`

- ✅ **Oficial:** API pública del dólar observado
- ✅ **Gratuita:** Sin límite de consultas
- ✅ **Sin autenticación:** No requiere API key
- ✅ **Actualizado:** Datos en tiempo real

**Ejemplo de respuesta:**

```json
{
  "version": "1.6.0",
  "autor": "mindicador.cl",
  "codigo": "dolar",
  "nombre": "Dólar observado",
  "unidad_medida": "Pesos",
  "serie": [
    {
      "fecha": "2025-11-13T03:00:00.000Z",
      "valor": 875.43
    }
  ]
}
```

### Alternativa: ExchangeRate-API

**API:** `https://api.exchangerate-api.com/v4/latest/USD`

- ✅ **Fallback automático:** Se usa si falla la API principal
- ✅ **Global:** Cobertura mundial
- ✅ **Gratuita:** Versión básica sin restricciones

**Ejemplo de respuesta:**

```json
{
  "base": "USD",
  "date": "2025-11-13",
  "rates": {
    "CLP": 875.43,
    "EUR": 0.85,
    ...
  }
}
```

---

## 🚀 Instalación

### Archivos Creados

```
wp-content/themes/automatiza-tech/
├── inc/
│   ├── currency-updater.php      # Lógica principal del updater
│   └── currency-admin.php        # Panel de administración
├── assets/
│   └── js/
│       └── currency-admin.js     # JavaScript del admin
└── functions.php                 # (modificado para incluir los nuevos archivos)

test-currency-updater.php         # Script de prueba (raíz del sitio)
```

### Paso 1: Verificar Archivos

Asegúrate de que todos los archivos están en su lugar:

```bash
# Verificar estructura
ls wp-content/themes/automatiza-tech/inc/currency-*.php
ls wp-content/themes/automatiza-tech/assets/js/currency-admin.js
ls test-currency-updater.php
```

### Paso 2: Cargar WordPress

Los archivos se cargan automáticamente gracias a los `require_once` en `functions.php`:

```php
require_once get_template_directory() . '/inc/currency-updater.php';
require_once get_template_directory() . '/inc/currency-admin.php';
```

### Paso 3: Activar el Cron

El cron se programa automáticamente al cargar WordPress. Para forzar la activación:

1. Accede al admin de WordPress
2. Ve a **Clientes → 💱 Precios CLP**
3. El sistema se activará automáticamente

---

## ⚙️ Configuración

### Configuración del Cron

El sistema usa WordPress Cron para ejecutarse diariamente.

**Hook del evento:**

```php
'automatiza_tech_daily_price_update'
```

**Programación:**

```php
wp_schedule_event(
    strtotime('tomorrow 08:00:00'), // Hora de ejecución
    'daily',                        // Frecuencia
    'automatiza_tech_daily_price_update'
);
```

### Modificar Hora de Ejecución

Para cambiar la hora de ejecución diaria, edita `currency-updater.php` línea ~34:

```php
// Cambiar de 08:00 a otra hora (ejemplo: 10:00)
wp_schedule_event(strtotime('tomorrow 10:00:00'), 'daily', 'automatiza_tech_daily_price_update');
```

### Modificar Umbral de Actualización

Para cambiar el porcentaje mínimo de diferencia (actualmente 2%), edita `currency-updater.php` línea ~165:

```php
// Cambiar de 2% a 5%
if ($difference_percent >= 5.0 || $old_clp == 0) {
    // Actualizar...
}
```

### Modificar Redondeo

Para cambiar el redondeo de precios (actualmente $1.000), edita `currency-updater.php` línea ~162:

```php
// Cambiar redondeo de 1000 a 500
$new_clp = round($usd_price * $exchange_rate / 500) * 500;
```

---

## 🖥️ Uso del Sistema

### Actualización Automática

El sistema se ejecuta **automáticamente todos los días a las 8:00 AM** sin necesidad de intervención.

**Proceso:**

1. Se obtiene el tipo de cambio USD/CLP actual
2. Se consultan todos los servicios con precio USD definido
3. Se calcula el nuevo precio CLP para cada servicio
4. Si la diferencia es ≥ 2%, se actualiza el precio
5. Se registra en los logs de WordPress

**Ver logs:**

```bash
# Ver últimas actualizaciones
tail -f wp-content/debug.log | grep "PRECIO"
```

### Actualización Manual

Puedes forzar una actualización manual desde el panel de admin:

1. Ve a **WP Admin → Clientes → 💱 Precios CLP**
2. Haz clic en el botón **"🔄 Actualizar Ahora"**
3. Espera la confirmación (3-5 segundos)
4. La página se recargará mostrando los nuevos precios

### Script de Prueba

Para probar el sistema antes de usarlo en producción:

1. Accede a: `http://tu-sitio.com/test-currency-updater.php`
2. Revisa la información mostrada
3. Haz clic en **"🚀 Ejecutar Actualización Ahora"**
4. Verifica los cambios aplicados

⚠️ **Importante:** El script de prueba requiere ser administrador de WordPress.

---

## 📊 Panel de Administración

### Acceso

**Ruta:** WP Admin → Clientes → 💱 Precios CLP  
**URL:** `/wp-admin/admin.php?page=automatiza-tech-currency`

### Secciones del Panel

#### 1. Tipo de Cambio Actual

- Muestra el tipo de cambio USD/CLP en tiempo real
- Actualizado desde Banco Central de Chile
- Formato: $XXX.XX CLP por 1 USD

#### 2. Última Actualización

- Fecha y hora de la última ejecución
- Cantidad de servicios actualizados
- Tipo de cambio usado en esa actualización

#### 3. Próxima Actualización

- Fecha y hora programada para la siguiente ejecución
- Frecuencia configurada (Diaria)
- Botón para forzar actualización manual

#### 4. Tabla de Servicios

Muestra todos los servicios con:

- **ID:** Identificador del servicio
- **Nombre:** Nombre descriptivo
- **Precio USD:** Precio base en dólares (nunca cambia)
- **Precio CLP Actual:** Precio actual en pesos chilenos
- **Precio CLP Estimado:** Precio calculado según tipo de cambio actual
- **Estado:** Activo/Inactivo

**Colores:**

- 🟢 Verde: Precio actualizado
- 🟡 Amarillo: Requiere actualización (diferencia > 2%)

---

## 🔧 Funcionamiento Técnico

### Flujo de Ejecución

```
┌─────────────────────────────────────────┐
│  WordPress Cron (diario a las 8:00 AM)  │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Hook: automatiza_tech_daily_price_update│
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Método: update_clp_prices()            │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  1. Obtener tipo de cambio USD/CLP      │
│     - API Banco Central (principal)     │
│     - API ExchangeRate (fallback)       │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  2. Consultar servicios con price_usd   │
│     SELECT * FROM wp_automatiza_services│
│     WHERE price_usd > 0                 │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  3. Calcular nuevos precios CLP         │
│     new_clp = round(usd * rate / 1000) * 1000│
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  4. Verificar umbral (2%)               │
│     ¿|new_clp - old_clp| / old_clp >= 0.02?│
└──────────────┬──────────────────────────┘
               │
               ▼
         ┌─────┴─────┐
         │           │
     SÍ  │           │  NO
         │           │
         ▼           ▼
┌────────────┐  ┌────────────┐
│ Actualizar │  │ Mantener   │
│ precio     │  │ precio     │
└────────────┘  └────────────┘
         │           │
         └─────┬─────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  5. Registrar en logs                   │
│     error_log("PRECIO ACTUALIZADO...")  │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  6. Guardar metadata                    │
│     - Fecha de actualización            │
│     - Tipo de cambio usado              │
│     - Cantidad de servicios actualizados│
└─────────────────────────────────────────┘
```

### Estructura de la Base de Datos

**Tabla:** `wp_automatiza_services`

```sql
CREATE TABLE wp_automatiza_services (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    price_usd DECIMAL(10,2),    -- Precio base (nunca cambia)
    price_clp DECIMAL(10,2),    -- Precio calculado (actualizado diariamente)
    status VARCHAR(20),
    created_at DATETIME,
    updated_at DATETIME
);
```

**Options usadas:**

```php
'automatiza_tech_last_price_update'      // Fecha de última actualización
'automatiza_tech_last_update_count'      // Servicios actualizados
'automatiza_tech_last_update_rate'       // Tipo de cambio usado
'automatiza_tech_last_exchange_rate'     // Último tipo de cambio conocido (respaldo)
'automatiza_tech_last_exchange_rate_date' // Fecha del tipo de cambio de respaldo
```

### Métodos Principales

#### `get_current_exchange_rate()`

Obtiene el tipo de cambio actual con fallback automático.

```php
public function get_current_exchange_rate() {
    // 1. Intentar API Banco Central
    $rate = $this->get_exchange_rate_bcch();
    
    // 2. Si falla, usar API alternativa
    if ($rate === false) {
        $rate = $this->get_exchange_rate_alternative();
    }
    
    // 3. Si ambas fallan, usar respaldo
    if ($rate === false) {
        $rate = get_option('automatiza_tech_last_exchange_rate', 850.0);
    }
    
    return $rate;
}
```

#### `update_clp_prices()`

Actualiza todos los precios CLP basados en el tipo de cambio actual.

```php
public function update_clp_prices() {
    // 1. Obtener tipo de cambio
    $exchange_rate = $this->get_current_exchange_rate();
    
    // 2. Obtener servicios
    $services = $wpdb->get_results("SELECT * FROM wp_automatiza_services WHERE price_usd > 0");
    
    // 3. Actualizar cada servicio
    foreach ($services as $service) {
        $new_clp = round($service->price_usd * $exchange_rate / 1000) * 1000;
        
        // Solo si cambio >= 2%
        if (abs(($new_clp - $service->price_clp) / $service->price_clp * 100) >= 2.0) {
            $wpdb->update(
                'wp_automatiza_services',
                ['price_clp' => $new_clp],
                ['id' => $service->id]
            );
        }
    }
    
    return ['success' => true, 'updated' => $count];
}
```

---

## 🐛 Troubleshooting

### El cron no se ejecuta

**Síntomas:**
- Los precios no se actualizan automáticamente
- La "Próxima actualización" muestra "No programada"

**Soluciones:**

1. **Verificar que wp-cron está activo:**

```php
// Agregar a wp-config.php
define('DISABLE_WP_CRON', false);
```

2. **Re-programar el cron manualmente:**

```php
// Ejecutar desde WordPress
wp_clear_scheduled_hook('automatiza_tech_daily_price_update');
wp_schedule_event(strtotime('tomorrow 08:00:00'), 'daily', 'automatiza_tech_daily_price_update');
```

3. **Verificar eventos programados:**

```php
// Ver próxima ejecución
$timestamp = wp_next_scheduled('automatiza_tech_daily_price_update');
echo date('Y-m-d H:i:s', $timestamp);
```

### Error al obtener tipo de cambio

**Síntomas:**
- Mensaje "No se pudo obtener el tipo de cambio"
- Los precios usan valores de respaldo

**Soluciones:**

1. **Verificar conectividad:**

```bash
# Probar API desde servidor
curl https://mindicador.cl/api/dolar
```

2. **Verificar firewall:**
- Asegúrate de que el servidor puede hacer requests HTTP externos
- Algunas configuraciones de Hostinger bloquean `wp_remote_get()`

3. **Usar tipo de cambio manual:**

```php
// Establecer tipo de cambio de respaldo
update_option('automatiza_tech_last_exchange_rate', 875.50);
```

### Los precios no cambian

**Síntomas:**
- La actualización se ejecuta pero los precios no cambian
- Log muestra "Sin cambio significativo"

**Causas posibles:**

1. **Diferencia menor al 2%:**
   - El sistema solo actualiza si el cambio es ≥ 2%
   - Esto evita cambios constantes por fluctuaciones mínimas

2. **Precio USD no definido:**
   - Solo actualiza servicios con `price_usd > 0`
   - Verificar que todos los servicios tienen precio USD

**Solución:**

```sql
-- Verificar precios USD
SELECT id, name, price_usd, price_clp 
FROM wp_automatiza_services 
WHERE price_usd IS NULL OR price_usd = 0;

-- Establecer precio USD
UPDATE wp_automatiza_services 
SET price_usd = 100.00 
WHERE id = X;
```

### Error "No autorizado" en actualización manual

**Síntomas:**
- Botón "Actualizar Ahora" no funciona
- Mensaje de error en consola

**Solución:**

1. **Verificar permisos:**
   - Solo usuarios con rol "Administrator" pueden ejecutar actualizaciones
   - Iniciar sesión como administrador

2. **Verificar nonce:**
   - El nonce puede expirar después de 12 horas
   - Recargar la página del admin

---

## 📚 API Reference

### Clase: `AutomatizaTech_Currency_Updater`

#### Métodos Públicos

##### `__construct()`

Constructor de la clase. Registra hooks y programa el cron.

```php
$updater = new AutomatizaTech_Currency_Updater();
```

##### `get_current_exchange_rate()`

Obtiene el tipo de cambio USD/CLP actual con fallback automático.

**Returns:** `float|false` - Tipo de cambio o false si falla

```php
$rate = $updater->get_current_exchange_rate();
// Retorna: 875.43
```

##### `update_clp_prices()`

Actualiza los precios CLP de todos los servicios.

**Returns:** `array` - Resultado de la actualización

```php
$result = $updater->update_clp_prices();
/*
array(
    'success' => true,
    'message' => '3 servicios actualizados',
    'updated' => 3,
    'exchange_rate' => 875.43,
    'details' => array(...)
)
*/
```

##### `get_last_update_info()`

Obtiene información sobre la última actualización.

**Returns:** `array` - Información del último update

```php
$info = $updater->get_last_update_info();
/*
array(
    'last_update' => '2025-11-13 08:00:15',
    'updated_count' => 3,
    'exchange_rate' => 875.43,
    'next_scheduled' => 1731571200,
    'last_exchange_rate' => 875.43,
    'last_exchange_date' => '2025-11-13 08:00:10'
)
*/
```

### Hooks de WordPress

#### Actions

##### `automatiza_tech_daily_price_update`

Se ejecuta diariamente a las 8:00 AM para actualizar precios.

```php
// Ejecutar manualmente
do_action('automatiza_tech_daily_price_update');
```

##### `wp_ajax_update_clp_prices_manually`

AJAX endpoint para actualización manual desde el admin.

```javascript
// Ejecutar vía AJAX
jQuery.post(ajaxurl, {
    action: 'update_clp_prices_manually',
    nonce: '...'
}, function(response) {
    console.log(response);
});
```

---

## 📝 Changelog

### Versión 1.0.0 - 2025-11-13

**Añadido:**
- Sistema completo de actualización automática de precios CLP
- Integración con API del Banco Central de Chile (mindicador.cl)
- Fallback a API alternativa (exchangerate-api.com)
- Panel de administración en WordPress
- Actualización manual desde el admin
- Script de prueba independiente
- Logs detallados de todas las operaciones
- Sistema de respaldo para tipo de cambio

**Configuración:**
- Ejecución diaria a las 8:00 AM (hora de Chile)
- Umbral de actualización: 2%
- Redondeo a múltiplos de $1.000 CLP
- Preservación de precios USD como referencia

---

## 🤝 Soporte

Para reportar problemas o solicitar nuevas funcionalidades:

- **Email:** info@automatizatech.shop
- **WordPress Admin:** Panel de Precios CLP
- **Script de Prueba:** test-currency-updater.php

---

## 📄 Licencia

Este sistema es parte del proyecto AutomatizaTech y está protegido por las mismas licencias que WordPress.

© 2025 AutomatizaTech - Todos los derechos reservados
