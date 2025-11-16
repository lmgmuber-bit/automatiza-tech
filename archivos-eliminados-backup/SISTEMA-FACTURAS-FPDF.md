# Sistema de Generación de Facturas PDF con FPDF

## 📋 Descripción

Sistema completo de generación de facturas en formato PDF usando **FPDF**, una librería 100% PHP que **NO requiere instalaciones externas** como wkhtmltopdf. Compatible con local y producción.

## ✨ Características

- ✅ **100% PHP** - No requiere instalaciones externas
- ✅ **Compatible con producción** - Funciona en cualquier servidor PHP
- ✅ **Diseño profesional** - Logo, colores corporativos, tablas
- ✅ **Código QR integrado** - Para validación de facturas
- ✅ **Formato A4** - Optimizado para impresión
- ✅ **Adjunto automático** - Se adjunta al correo del cliente
- ✅ **Footer en 3 columnas** - Contacto, validación, QR

## 📁 Archivos del Sistema

### Librerías Core

```
wp-content/themes/automatiza-tech/lib/
├── fpdf.php                    # Librería FPDF 1.86
├── qrcode.php                  # Generador de códigos QR
└── invoice-pdf-fpdf.php        # Generador de facturas PDF
```

### Scripts de Testing

```
test-fpdf-invoice.php           # Genera factura de prueba
regenerate-invoices-fpdf.php    # Regenera todas las facturas existentes
```

### Integración WordPress

```
wp-content/themes/automatiza-tech/inc/contact-form.php
    └── generate_and_save_pdf() # Función que genera PDFs automáticamente
```

## 🚀 Instalación

### 1. Verificar que FPDF está instalado

```bash
# Verificar que existe la librería
ls wp-content/themes/automatiza-tech/lib/fpdf.php
```

Si no existe, descargarla:

```bash
# Descargar FPDF
Invoke-WebRequest -Uri "http://www.fpdf.org/en/download/fpdf186.zip" -OutFile "fpdf.zip"

# Extraer en directorio lib
Expand-Archive -Path "fpdf.zip" -DestinationPath "wp-content/themes/automatiza-tech/lib/" -Force

# Limpiar
Remove-Item "fpdf.zip"
```

### 2. Crear directorios necesarios

```bash
# Directorio de facturas
mkdir wp-content/uploads/automatiza-tech-invoices

# Directorio de códigos QR
mkdir wp-content/uploads/qr-codes
```

### 3. Configurar permisos (Linux/Mac)

```bash
chmod 755 wp-content/uploads/automatiza-tech-invoices
chmod 755 wp-content/uploads/qr-codes
```

## 🧪 Testing

### Test Básico

Abre en el navegador:
```
http://localhost/automatiza-tech/test-fpdf-invoice.php
```

Este script:
- ✅ Crea una factura de prueba
- ✅ Muestra información del PDF generado
- ✅ Permite descargar el PDF

### Regenerar Facturas Existentes

```
http://localhost/automatiza-tech/regenerate-invoices-fpdf.php
```

Este script:
- 🔄 Regenera todas las facturas de clientes contratados
- 📊 Muestra tabla con resultados
- 🔗 Permite ver/descargar cada PDF

## 📝 Uso Manual

### Generar una factura

```php
require_once(get_template_directory() . '/lib/invoice-pdf-fpdf.php');

// Datos del cliente
$client_data = (object) array(
    'id' => 1,
    'name' => 'Juan Pérez',
    'email' => 'juan@example.com',
    'phone' => '+56 9 1234 5678'
);

// Datos del plan
$plan_data = (object) array(
    'id' => 1,
    'name' => 'Plan Profesional',
    'price_clp' => 350000
);

$invoice_number = 'AT-20251111-0001';

// Crear PDF
$pdf = new InvoicePDFFPDF($client_data, $plan_data, $invoice_number);

// Guardar en archivo
$pdf->save('/ruta/archivo.pdf');

// O descargar directamente
$pdf_content = $pdf->generate();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="factura.pdf"');
echo $pdf_content;
```

## 🎨 Personalización

### Cambiar colores corporativos

Editar `lib/invoice-pdf-fpdf.php`:

```php
class InvoicePDFFPDF extends FPDF {
    // Colores corporativos
    private $primary_color = array(33, 150, 243);     // #2196F3 Azul
    private $secondary_color = array(76, 175, 80);    // #4CAF50 Verde
    private $text_color = array(33, 33, 33);          // #212121 Negro
    private $gray_color = array(117, 117, 117);       // #757575 Gris
}
```

### Cambiar logo

Reemplaza el archivo:
```
wp-content/themes/automatiza-tech/assets/img/logo.png
```

Requisitos:
- Formato: PNG con fondo transparente
- Tamaño recomendado: 400x100 px
- Peso máximo: 50 KB

### Modificar footer

Editar función `Footer()` en `lib/invoice-pdf-fpdf.php`:

```php
function Footer() {
    // Línea 105-130: Layout del footer
    // Modificar textos, posiciones, tamaños
}
```

## 🔄 Integración con WordPress

### Generación Automática

Cuando un contacto es marcado como "Contratado":

1. **Se genera la factura HTML** (backup en BD)
2. **Se genera el PDF** usando FPDF
3. **Se adjunta al correo** automáticamente
4. **Se guarda la ruta** en la base de datos

```php
// En inc/contact-form.php línea 1650
private function generate_and_save_pdf($client_data, $plan_data, $invoice_number) {
    require_once(get_template_directory() . '/lib/invoice-pdf-fpdf.php');
    
    $pdf_generator = new InvoicePDFFPDF($client_data, $plan_data, $invoice_number);
    $pdf_path = /* ruta del archivo */;
    
    $success = $pdf_generator->save($pdf_path);
    return $pdf_path;
}
```

### Adjuntar al Correo

```php
// En inc/contact-form.php línea 1098
$attachments = array();
if ($invoice_pdf_path && file_exists($invoice_pdf_path)) {
    $attachments = array($invoice_pdf_path); // Adjunta PDF
}

wp_mail($to, $subject, $message, $headers, $attachments);
```

## 🐛 Troubleshooting

### Error: "Class 'QRcode' not found"

**Causa:** No se cargó correctamente la librería qrcode.php

**Solución:**
```php
// Verificar que existe el alias en lib/qrcode.php:
class QRcode extends SimpleQRCode {}
```

### Error: "Cannot create directory"

**Causa:** Permisos insuficientes

**Solución:**
```bash
chmod 755 wp-content/uploads/automatiza-tech-invoices
chmod 755 wp-content/uploads/qr-codes
```

### PDF vacío o corrupto

**Causa:** Error en la generación

**Solución:** Revisar logs PHP
```bash
tail -f wp-content/debug.log
# O en Windows:
Get-Content wp-content\debug.log -Tail 50
```

### QR Code no se muestra

**Causa:** API externa no responde o sin internet

**Solución:** El sistema tiene fallback automático. Si persiste:
```php
// En lib/qrcode.php línea 20
// Verificar que la API responde:
$api_url = "https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=test";
$test = file_get_contents($api_url);
var_dump($test !== false); // Debe ser true
```

### PDF no se adjunta al correo

**Causa 1:** Archivo no existe
```php
// Verificar en inc/contact-form.php línea 1098
if ($invoice_pdf_path && file_exists($invoice_pdf_path)) {
    error_log("PDF existe: " . $invoice_pdf_path);
} else {
    error_log("PDF NO existe: " . $invoice_pdf_path);
}
```

**Causa 2:** Permisos de archivo
```bash
# Dar permisos de lectura
chmod 644 wp-content/uploads/automatiza-tech-invoices/*.pdf
```

## 📊 Especificaciones Técnicas

### Formato del PDF

- **Tamaño:** A4 (210 x 297 mm)
- **Orientación:** Vertical (Portrait)
- **Márgenes:** 10mm todos los lados
- **Fuente:** Arial (Unicode)
- **Tamaño archivo:** 3-5 KB aprox.

### Estructura del PDF (Mejorada v2.0)

```
┌─────────────────────────────────────────┐
│ Header (42mm) - Fondo gris claro        │
│  ┌────────────────────────────────────┐ │
│  │ Logo/Empresa (más grande)     Info│ │
│  │                        con iconos ✉│ │
│  └────────────────────────────────────┘ │
│  ═══════════ Línea azul gruesa ════════ │
├─────────────────────────────────────────┤
│ Body (215mm)                            │
│  ╔═══════════════════════════════════╗ │
│  ║     FACTURA (fondo azul)          ║ │
│  ╚═══════════════════════════════════╝ │
│  N° AT-YYYYMMDD-XXXX (grande)           │
│  Fecha: DD/MM/YYYY HH:MM                │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │ DATOS DEL CLIENTE (borde azul)    │ │
│  │ ─────────────────────────────────│ │
│  │ Nombre:  Juan Pérez              │ │
│  │ Teléfono: +56 9 1234 5678        │ │
│  │ Email:   juan@example.com        │ │
│  └───────────────────────────────────┘ │
│                                         │
│  DETALLE DEL SERVICIO                   │
│  ┌─────────────────┬────────┬────────┐ │
│  │ Descripción     │ Cant.  │ Monto  │ │
│  ├─────────────────┼────────┼────────┤ │
│  │ Plan...         │   1    │$350.000│ │
│  └─────────────────┴────────┴────────┘ │
│                                         │
│                     ┌────────┬────────┐ │
│                     │ TOTAL: │$350.000│ │
│                     └────────┴────────┘ │
│                     (verde destacado)   │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │ Mensaje de agradecimiento         │ │
│  │ (fondo verde claro)               │ │
│  └───────────────────────────────────┘ │
├─────────────────────────────────────────┤
│ Footer (55mm)                           │
│  ═══════════ Línea azul gruesa ════════ │
│  ┌───────────┬───────────┬──────────┐  │
│  │ CONTACTO  │ VALIDACIÓN│  ╔═══╗  │  │
│  │ ✉ Email   │ Escanea   │  ║ Q ║  │  │
│  │ ☎ Teléfono│ código QR │  ║ R ║  │  │
│  │ 🌐 Web    │ o visita  │  ╚═══╝  │  │
│  └───────────┴───────────┴──────────┘  │
│  Texto legal centrado                   │
└─────────────────────────────────────────┘
```

**Mejoras visuales v2.0:**
- ✨ Header con fondo gris claro
- ✨ Título FACTURA con fondo azul completo
- ✨ Cuadro de cliente con borde azul grueso
- ✨ Tabla de servicios con filas más altas
- ✨ Total en verde con tamaño más grande
- ✨ Mensaje en cuadro con fondo verde claro
- ✨ Footer con iconos y QR enmarcado
- ✨ Mejor espaciado general

## 🌐 Compatibilidad

### Servidores soportados

- ✅ Apache 2.4+
- ✅ Nginx 1.18+
- ✅ IIS 10+
- ✅ LiteSpeed

### Versiones PHP

- ✅ PHP 7.4
- ✅ PHP 8.0
- ✅ PHP 8.1
- ✅ PHP 8.2
- ✅ PHP 8.3

### Extensiones PHP requeridas

```php
// Verificar extensiones:
php -m | grep -E 'gd|zlib|mbstring'

// Deben estar habilitadas:
- gd          # Para manipulación de imágenes (QR)
- zlib        # Para compresión PDF
- mbstring    # Para textos UTF-8
```

## 📦 Despliegue a Producción

### Checklist

- [ ] FPDF instalado en `/lib/`
- [ ] Directorios creados con permisos 755
- [ ] Extensiones PHP habilitadas
- [ ] Test de factura funcionando
- [ ] Logo corporativo actualizado
- [ ] Colores personalizados (opcional)
- [ ] Datos de contacto actualizados

### Subir archivos

```bash
# Subir librerías
scp -r wp-content/themes/automatiza-tech/lib user@servidor:/path/

# Crear directorios en servidor
ssh user@servidor
mkdir -p wp-content/uploads/automatiza-tech-invoices
mkdir -p wp-content/uploads/qr-codes
chmod 755 wp-content/uploads/automatiza-tech-invoices
chmod 755 wp-content/uploads/qr-codes
```

### Verificar en producción

```
https://tudominio.com/test-fpdf-invoice.php
```

## 📞 Soporte

Para problemas o dudas:

1. **Revisar logs:** `wp-content/debug.log`
2. **Test básico:** `test-fpdf-invoice.php`
3. **Verificar permisos:** `ls -la wp-content/uploads/`
4. **Comprobar PHP:** `php -v` y `php -m`

## 📄 Licencia

- **FPDF:** Licencia gratuita para uso comercial/personal
- **Sistema AutomatizaTech:** Propiedad de automatizatech.shop

---

**Última actualización:** 11 de noviembre de 2025
**Versión:** 1.0.0
**Autor:** AutomatizaTech Development Team
