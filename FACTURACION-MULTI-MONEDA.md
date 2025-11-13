# Sistema de Facturación Multi-Moneda

## 📋 Descripción

Sistema automático que detecta el país del cliente y genera facturas con la moneda correspondiente:

- **🇨🇱 Chile (CL):** Pesos Chilenos (CLP) con IVA 19%
- **🌎 Otros países:** Dólares Americanos (USD) sin IVA

## 🎯 Reglas de Negocio

### Chile (CL)
- **Moneda:** Pesos Chilenos (CLP)
- **Símbolo:** $ (ej: $350.000)
- **Formato:** Sin decimales, separador de miles con punto
- **IVA:** 19% incluido en el precio
- **Cálculo:** `Neto = Total / 1.19`
- **Código WhatsApp:** +56

### Internacional (Otros Países)
- **Moneda:** Dólares Americanos (USD)
- **Símbolo:** USD $ (ej: USD $400.00)
- **Formato:** Con 2 decimales, separador de miles con coma
- **IVA:** No aplica
- **Precio:** Tal cual está en la base de datos (price_usd)
- **Códigos WhatsApp:** +1 (USA), +54 (ARG), +57 (COL), etc.

## 🗄️ Estructura de Base de Datos

### Tabla: `wp_automatiza_tech_clients`
```sql
ALTER TABLE `wp_automatiza_tech_clients` 
ADD COLUMN `country` varchar(2) DEFAULT 'CL' 
COMMENT 'Código ISO de 2 letras del país' 
AFTER `phone`;
```

### Tabla: `wp_automatiza_services`
```sql
CREATE TABLE `wp_automatiza_services` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `price_usd` decimal(10,2) DEFAULT 0.00,  -- Precio en USD
    `price_clp` decimal(12,0) DEFAULT 0,     -- Precio en CLP
    ...
);
```

## 🔍 Detección Automática de País

El sistema detecta el país del cliente en el siguiente orden:

### 1. Campo `country` en BD (Prioridad 1)
```php
if (isset($client_data->country) && !empty($client_data->country)) {
    return strtoupper($client_data->country);
}
```

### 2. Código Telefónico de WhatsApp (Prioridad 2)
```php
$country_codes = [
    '+56' => 'CL',  // Chile
    '+1'  => 'US',  // USA/Canadá
    '+54' => 'AR',  // Argentina
    '+57' => 'CO',  // Colombia
    '+52' => 'MX',  // México
    '+51' => 'PE',  // Perú
    '+34' => 'ES',  // España
    '+55' => 'BR',  // Brasil
];
```

### 3. Valor por Defecto
Si no se puede detectar → **Chile (CL)**

## 💻 Implementación Técnica

### Clase: `InvoicePDFFPDF`

#### Propiedades Nuevas
```php
private $client_country;      // CL, US, AR, etc.
private $currency;            // CLP o USD
private $currency_symbol;     // $ o USD $
private $apply_iva;          // true/false
```

#### Métodos Principales

**1. `detect_client_country($client_data)`**
- Detecta país basado en campo `country` o código telefónico
- Retorna: código ISO de 2 letras (ej: 'CL', 'US')

**2. `configure_currency($country)`**
- Configura moneda, símbolo y si aplica IVA según país
- Chile: CLP, $, con IVA
- Otros: USD, USD $, sin IVA

**3. `get_item_price($item)`**
- Retorna `price_clp` para Chile
- Retorna `price_usd` para otros países

**4. `format_currency($amount)`**
- Chile: `$350.000` (sin decimales)
- USD: `USD $400.00` (con decimales)

## 📄 Formato de Facturas

### Factura Chile (CLP)
```
DETALLE DEL SERVICIO
─────────────────────────────────────────────
Descripción                    | Cant. | Monto
─────────────────────────────────────────────
Plan Profesional              |   1   | $350.000
Hosting Premium               |   1   | $120.000
Mantenimiento                 |   1   |  $80.000
─────────────────────────────────────────────

                    Neto:      $462.185
                    IVA (19%):  $87.815
                    ─────────────────────
                    TOTAL:     $550.000
```

### Factura Internacional (USD)
```
DETALLE DEL SERVICIO
─────────────────────────────────────────────
Descripción                    | Cant. | Monto
─────────────────────────────────────────────
Plan Profesional              |   1   | USD $400.00
Hosting Premium               |   1   | USD $140.00
Mantenimiento                 |   1   |  USD $90.00
─────────────────────────────────────────────

* Factura internacional - No aplica IVA chileno

                    TOTAL:     USD $630.00
```

## 🧪 Testing

### Test Manual

**Factura Chile:**
```bash
http://localhost/automatiza-tech/test-fpdf-invoice.php?country=CL
```

**Factura Internacional:**
```bash
http://localhost/automatiza-tech/test-fpdf-invoice.php?country=US
```

### Datos de Prueba

```php
// Cliente Chile
$client_data = (object) array(
    'name' => 'Juan Pérez García',
    'phone' => '+56 9 8765 4321',
    'country' => 'CL'  // Explícito
);

// Cliente USA
$client_data = (object) array(
    'name' => 'John Smith',
    'phone' => '+1 305 555 1234',
    'country' => 'US'  // Explícito
);

// Servicio con ambos precios
$service = (object) array(
    'name' => 'Plan Profesional',
    'price_clp' => 350000,   // $350.000 CLP
    'price_usd' => 400        // $400.00 USD
);
```

## 📊 Migración de Datos

### Script: `add-country-field.php`

**Funcionalidad:**
1. Agrega columna `country` a `wp_automatiza_tech_clients`
2. Detecta país de clientes existentes por código telefónico
3. Por defecto marca todos como Chile (CL)
4. Muestra resumen de clientes por país

**Ejecución:**
```bash
php add-country-field.php
```

**Salida:**
```
✅ Columna 'country' agregada exitosamente
✅ Actualizado país de 5 cliente(s)

📊 Resumen de clientes por país:
   CL (Chile): 4 cliente(s)
   US (Estados Unidos): 1 cliente(s)
```

## 🔄 Flujo de Generación

```
1. Cliente solicita factura
   ↓
2. Sistema detecta país (BD o teléfono)
   ↓
3. Configura moneda según país
   ├─ CL → CLP ($), con IVA 19%
   └─ Otros → USD (USD $), sin IVA
   ↓
4. Obtiene precios correctos de BD
   ├─ CL → price_clp
   └─ Otros → price_usd
   ↓
5. Calcula totales
   ├─ CL → Neto + IVA = Total
   └─ Otros → Total directo
   ↓
6. Formatea moneda
   ├─ CL → $350.000
   └─ USD → USD $400.00
   ↓
7. Genera PDF con datos correctos
```

## 📝 Ejemplos de Uso

### Generar Factura Programáticamente

```php
// Cliente Chile
$client = get_client_by_id(123);  // phone: +56 9 1234 5678
$services = get_client_services(123);

$pdf = new InvoicePDFFPDF($client, $services, 'AT-20251111-001');
// Detecta automáticamente: país=CL, moneda=CLP, IVA=19%

$pdf->Output('I', 'factura-123-CL.pdf');
// Genera factura en CLP con IVA

// Cliente Internacional
$client = get_client_by_id(456);  // phone: +1 305 555 1234
$services = get_client_services(456);

$pdf = new InvoicePDFFPDF($client, $services, 'AT-20251111-002');
// Detecta automáticamente: país=US, moneda=USD, sin IVA

$pdf->Output('I', 'invoice-456-US.pdf');
// Genera factura en USD sin IVA
```

### Forzar País Manualmente

```php
$client->country = 'US';  // Forzar USA
$pdf = new InvoicePDFFPDF($client, $services, 'AT-20251111-003');
// Usará USD independientemente del teléfono
```

## ⚙️ Configuración

### Agregar Nuevo País

**1. Actualizar detección en `invoice-pdf-fpdf.php`:**
```php
$country_codes = [
    '+56' => 'CL',
    '+1'  => 'US',
    '+549' => 'AR',  // Nuevo: Argentina
];
```

**2. Actualizar configuración de moneda:**
```php
private function configure_currency($country) {
    if ($country === 'CL') {
        // Chile
        $this->currency = 'CLP';
        $this->apply_iva = true;
    } elseif ($country === 'AR') {
        // Nuevo: Argentina
        $this->currency = 'ARS';
        $this->currency_symbol = 'AR$ ';
        $this->apply_iva = true;  // IVA 21%
    } else {
        // Otros
        $this->currency = 'USD';
        $this->apply_iva = false;
    }
}
```

### Cambiar Tasa de IVA

```php
// Para Chile (línea ~310)
$neto = round($total_con_iva / 1.19);  // Cambiar 1.19 por nueva tasa
```

## 🚨 Validaciones y Errores

### Cliente sin País
```php
// Se asigna Chile por defecto
if (!isset($client->country)) {
    $client->country = 'CL';
}
```

### Servicio sin Precio
```php
// Retorna 0 si falta precio
$price = isset($item->price_clp) ? $item->price_clp : 0;
```

### Código Telefónico No Reconocido
```php
// Se asume Chile por defecto
return 'CL';
```

## 📈 Estadísticas de Uso

```php
// Query para ver distribución de países
SELECT country, COUNT(*) as total,
       SUM(CASE WHEN country = 'CL' THEN 1 ELSE 0 END) as chile,
       SUM(CASE WHEN country != 'CL' THEN 1 ELSE 0 END) as internacional
FROM wp_automatiza_tech_clients
GROUP BY country;
```

## 🔐 Seguridad

- ✅ Validación de código ISO de país (2 letras)
- ✅ Sanitización de datos de cliente
- ✅ Validación de precios (no negativos)
- ✅ Escape de caracteres especiales en PDF
- ✅ Prevención de inyección SQL

## 📚 Referencias

- **ISO 3166-1 alpha-2:** Códigos de países (CL, US, AR, etc.)
- **ISO 4217:** Códigos de monedas (CLP, USD, ARS, etc.)
- **Códigos telefónicos:** ITU-T E.164

## 🎯 Casos de Uso

### Caso 1: Cliente Chileno Nuevo
```
1. Cliente llena formulario con teléfono +56 9 1234 5678
2. Sistema crea registro con country='CL'
3. Al generar factura usa CLP con IVA
```

### Caso 2: Cliente Internacional
```
1. Cliente de USA con teléfono +1 305 555 1234
2. Sistema detecta país='US'
3. Factura en USD sin IVA
```

### Caso 3: Migración de Cliente Existente
```
1. Cliente antiguo sin campo country
2. Script de migración detecta +56 → CL
3. Próxima factura usa CLP con IVA
```

## 🔄 Actualizaciones Futuras

- [ ] Soporte para más monedas (EUR, GBP, ARS)
- [ ] IVA configurable por país
- [ ] Conversión automática de monedas
- [ ] Facturas en múltiples idiomas
- [ ] API de tipo de cambio

---

**Versión:** 2.0  
**Última actualización:** Noviembre 2025  
**Autor:** AutomatizaTech Development Team
