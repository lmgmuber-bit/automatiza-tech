# Configuración de Datos de Facturación

## 📋 Descripción

Sistema de configuración que permite modificar desde el panel de administración de WordPress todos los datos de la empresa que aparecen en las facturas PDF, incluyendo:

- Nombre de la empresa
- RUT
- Giro comercial
- Dirección
- Email
- Teléfono
- Sitio web

## 🎯 Ubicación en el Panel Admin

**Menú:** `Datos Facturación`
**Icono:** 📄 (dashicons-text-page)
**Permisos:** Solo administradores (`manage_options`)

## 📝 Campos Configurables

### Información de la Empresa

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **Nombre de la Empresa** | Razón social o nombre comercial | `AutomatizaTech SpA` |
| **RUT** | RUT de la empresa con formato | `77.123.456-7` |
| **Giro Comercial** | Actividad económica principal | `Servicios tecnológicos` |
| **Dirección** | Dirección física de la empresa | `Av. Providencia 123, Santiago` |

### Datos de Contacto

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **Email** | Email de contacto principal | `info@automatizatech.shop` |
| **Teléfono** | Número con código de país | `+56 9 1234 5678` |
| **Sitio Web** | URL sin http:// | `www.automatizatech.shop` |

## 🔧 Implementación Técnica

### Archivos Modificados

#### 1. **inc/invoice-settings.php** (NUEVO)
Panel de administración completo con:
- Formulario de configuración
- Validación de datos
- Vista previa en vivo
- Mensajes de confirmación
- Diseño con colores corporativos

#### 2. **lib/invoice-pdf-fpdf.php** (MODIFICADO)
Generador de PDFs actualizado para usar valores configurables:

```php
// En lugar de valores fijos:
$this->Cell(85, 6, 'AutomatizaTech SpA', 0, 1, 'R');

// Ahora usa valores configurables:
$company_name = get_option('company_name', 'AutomatizaTech SpA');
$this->Cell(85, 6, utf8_decode($company_name), 0, 1, 'R');
```

#### 3. **functions.php** (MODIFICADO)
Agregado require para cargar el sistema:

```php
require_once get_template_directory() . '/inc/invoice-settings.php';
```

### Zonas del PDF Afectadas

#### Header (Líneas 77-100)
```php
$company_name = get_option('company_name', 'AutomatizaTech SpA');
$company_rut = get_option('company_rut', '77.123.456-7');
$company_email = get_option('company_email', 'info@automatizatech.shop');
$company_phone = get_option('company_phone', '+56 9 1234 5678');
$company_website = get_option('company_website', 'www.automatizatech.shop');
```

**Muestra:**
- Nombre de la empresa (esquina superior derecha)
- RUT
- Email
- Teléfono
- Sitio web

#### Footer Legal (Líneas 103-120)
```php
$company_name = get_option('company_name', 'AutomatizaTech SpA');
$company_rut = get_option('company_rut', '77.123.456-7');
```

**Muestra:**
- `[Empresa] - RUT: [RUT] - Factura válida para efectos tributarios`
- `© 2025 [Empresa]. Todos los derechos reservados.`

#### Mensaje de Agradecimiento (Líneas 297-302)
```php
$company_name = get_option('company_name', 'AutomatizaTech SpA');
$this->Cell(0, 5, utf8_decode('¡Gracias por confiar en ' . $company_name . '!'), 0, 1, 'L');
```

#### Sección de Contacto (Líneas 314-333)
```php
$company_email = get_option('company_email', 'info@automatizatech.shop');
$company_phone = get_option('company_phone', '+56 9 1234 5678');
$company_website = get_option('company_website', 'www.automatizatech.shop');
```

**Columna CONTACTO:**
- Email
- Teléfono
- Sitio web

#### Información Tributaria (Líneas 335-358)
```php
$company_rut = get_option('company_rut', '77.123.456-7');
$company_giro = get_option('company_giro', 'Servicios tecnológicos');
$company_website = get_option('company_website', 'www.automatizatech.shop');
```

**Columna INFORMACIÓN:**
- RUT
- Giro comercial
- URL de validación (`[sitio]/validar`)

## 📖 Uso

### 1. Acceder a la Configuración
1. Iniciar sesión en WordPress como administrador
2. Ir al menú lateral: **`Datos Facturación`**
3. Verás el formulario con todos los campos

### 2. Modificar los Datos
1. Editar los campos que desees cambiar
2. Verificar la vista previa en la parte inferior
3. Hacer clic en **"Guardar Configuración"**
4. Verás un mensaje de confirmación verde

### 3. Verificar Cambios
1. Generar una nueva factura de prueba:
   - Ir a: `http://localhost/automatiza-tech/test-fpdf-invoice.php`
2. Verificar que los nuevos datos aparezcan en:
   - Header (arriba derecha)
   - Footer legal (abajo)
   - Mensaje de agradecimiento
   - Sección de contacto
   - Información tributaria

### 4. Regenerar Facturas Anteriores (Opcional)
Si deseas que las facturas anteriores reflejen los nuevos datos:
1. Ir a: `http://localhost/automatiza-tech/regenerate-invoices-fpdf.php`
2. Se regenerarán todas las facturas con la nueva información

## ⚙️ Valores por Defecto

Si no se configura ningún valor, el sistema usa los valores por defecto:

```php
$defaults = array(
    'company_name'    => 'AutomatizaTech SpA',
    'company_rut'     => '77.123.456-7',
    'company_giro'    => 'Servicios tecnológicos',
    'company_email'   => 'info@automatizatech.shop',
    'company_phone'   => '+56 9 1234 5678',
    'company_website' => 'www.automatizatech.shop',
    'company_address' => 'Santiago, Chile'
);
```

## 🎨 Características del Panel

### Diseño Profesional
- **Colores corporativos:** Azul #0096C7 y verde turquesa #00BFB3
- **Iconos visuales:** Emojis y dashicons
- **Secciones separadas:** Información de empresa y datos de contacto
- **Tooltips descriptivos:** Cada campo tiene una explicación

### Vista Previa en Vivo
Muestra cómo se verán los datos en:
- **HEADER:** Nombre, RUT, email, teléfono, web
- **FOOTER:** RUT, giro, URL de validación

### Mensajes de Confirmación
- ✅ **Éxito:** "Configuración guardada correctamente"
- ⚠️ **Advertencia:** Recordatorio para regenerar facturas antiguas

### Notas Informativas
- ℹ️ **Info:** Los datos se aplican a todas las facturas
- ⚠️ **Importante:** Sugerencia de regenerar facturas anteriores

## 🔒 Seguridad

- **Sanitización:** Todos los valores se sanitizan con `sanitize_text_field()` y `sanitize_email()`
- **Validación:** Los emails se validan antes de guardar
- **Permisos:** Solo usuarios con capacidad `manage_options` (administradores)
- **Nonces:** WordPress maneja automáticamente la verificación de seguridad
- **Escape de salida:** Todo se escapa con `esc_attr()` y `esc_html()`

## 🧪 Testing

### Probar Panel de Configuración
```bash
# 1. Acceder al panel
http://localhost/automatiza-tech/wp-admin/admin.php?page=automatiza-invoice-settings

# 2. Modificar datos
# 3. Guardar y verificar mensaje de éxito
```

### Probar Factura de Prueba
```bash
# Generar PDF con nuevos datos
http://localhost/automatiza-tech/test-fpdf-invoice.php
```

### Probar Regeneración de Facturas
```bash
# Regenerar todas las facturas con nuevos datos
http://localhost/automatiza-tech/regenerate-invoices-fpdf.php
```

## 📊 Base de Datos

Los valores se guardan en la tabla `wp_options` con los siguientes nombres:

| Option Name | Descripción |
|-------------|-------------|
| `company_name` | Nombre de la empresa |
| `company_rut` | RUT |
| `company_giro` | Giro comercial |
| `company_email` | Email |
| `company_phone` | Teléfono |
| `company_website` | Sitio web |
| `company_address` | Dirección |

### Consulta SQL para Ver Valores
```sql
SELECT option_name, option_value 
FROM wp_options 
WHERE option_name LIKE 'company_%';
```

### Resetear a Valores por Defecto
```sql
DELETE FROM wp_options WHERE option_name LIKE 'company_%';
```

## 🔄 Compatibilidad

- ✅ **WordPress:** 6.8.3+
- ✅ **PHP:** 8.3+
- ✅ **FPDF:** 1.86
- ✅ **Codificación:** UTF-8 con `utf8_decode()` para compatibilidad FPDF
- ✅ **Navegadores:** Todos los modernos

## 🚀 Próximas Mejoras

1. **Logo configurable:** Permitir subir logo desde el panel
2. **Colores personalizables:** Elegir colores corporativos
3. **Múltiples direcciones:** Para facturas de diferentes sucursales
4. **Plantillas:** Diferentes estilos de factura
5. **Export/Import:** Backup de configuración

## 📞 Soporte

Si encuentras algún problema o necesitas ayuda:

1. Verificar que el archivo `inc/invoice-settings.php` existe
2. Verificar que está cargado en `functions.php`
3. Verificar permisos de usuario (debe ser administrador)
4. Revisar logs de error de PHP
5. Verificar que los valores se guardan en la base de datos

## 📄 Licencia

Este sistema es parte del tema AutomatizaTech y está bajo la misma licencia del proyecto.

---

**Versión:** 1.0  
**Última actualización:** Noviembre 2025  
**Autor:** AutomatizaTech Development Team
