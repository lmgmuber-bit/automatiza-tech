# 🚀 Guía de Despliegue a Producción - Sistema Completo

## 📋 Resumen de Cambios

### ✨ Nuevas Funcionalidades Implementadas

#### 1. Sistema Multi-Moneda 🌎
- **Chile (CL):** Facturas en Pesos Chilenos (CLP) con IVA 19%
- **Internacional:** Facturas en Dólares (USD) sin IVA
- Detección automática de país por código telefónico
- 18 países soportados (Chile, USA, Argentina, Colombia, México, Perú, España, Brasil, Ecuador, Paraguay, Uruguay, Venezuela, Costa Rica, Panamá, El Salvador, Honduras, Nicaragua, Guatemala)

#### 2. Sistema de Emails Automáticos 📧

##### Email #1: Notificación Interna de Contacto
- **Cuándo se envía:** Cuando alguien llena el formulario de contacto
- **Destinatario:** automatizatech.bots@gmail.com
- **Contenido:** 
  - Datos del contacto (nombre, email, empresa, teléfono, mensaje)
  - Fecha y hora del contacto
  - Enlace directo al panel de administración

##### Email #2: Factura al Cliente con PDF Adjunto 💼
- **Cuándo se envía:** Cuando un contacto es convertido a cliente
- **Destinatario:** Email del cliente
- **Contenido:** 
  - Mensaje de bienvenida personalizado con nombre del cliente
  - Detalles del plan contratado
  - **Factura PDF profesional adjunta**
  - Información de contacto y soporte
  - Diseño responsive con colores corporativos

**La factura PDF incluye:**
- Logo y datos de la empresa (configurables desde admin)
- Número de factura único (formato: AT-YYYYMMDD-XXXX)
- Fecha de emisión
- Información completa del cliente
- Lista de servicios contratados
- Precios en CLP (Chile) o USD (Internacional)
- Cálculo de IVA 19% solo para Chile
- Subtotal, IVA y Total
- Nota para facturas internacionales: "No aplica IVA chileno"
- Términos y condiciones
- Diseño profesional con gradientes corporativos

##### Email #3: Notificación Interna de Cliente Contratado 🎉
- **Cuándo se envía:** Cuando un contacto es convertido a cliente
- **Destinatario:** automatizatech.bots@gmail.com
- **Contenido:**
  - Datos completos del cliente
  - Plan contratado y valor
  - País detectado y moneda usada
  - Fecha de contratación
  - Enlace directo al panel de clientes en admin

#### 3. Panel de Configuración en WordPress Admin ⚙️

**Nuevo menú: "Datos Facturación"**
- **Ubicación:** WordPress Admin → Datos Facturación
- **Propósito:** Configurar datos de la empresa que aparecen en las facturas

**Campos configurables:**
- ✏️ Nombre de la empresa
- 🆔 RUT de la empresa
- 🏢 Giro comercial
- 📍 Dirección completa
- 📧 Email de contacto
- 📱 Teléfono
- 🌐 Sitio web

**Características:**
- Vista previa de cómo se verán los datos en las facturas
- Validación de campos obligatorios
- Botón de guardado con confirmación visual
- Los cambios se reflejan automáticamente en todas las facturas nuevas

#### 4. Gestión Automática de Facturas 📄

**Generación:**
- Sistema basado en FPDF (100% PHP, sin dependencias externas)
- Generación automática al convertir contacto a cliente
- Formato de nombre: `AT-YYYYMMDD-XXXX.pdf`

**Almacenamiento:**
- Carpeta: `/wp-content/uploads/invoices/`
- Backup en base de datos (tabla `wp_automatiza_tech_invoices`)
- Backup HTML si falla el envío de email
- Registro en logs de WordPress

**Seguridad:**
- Carpeta de facturas protegida con `.htaccess`
- Solo accesible desde panel de admin
- Registro de todas las operaciones

---

## 📦 Archivos a Subir a Producción

### Archivos PHP Modificados

```
wp-content/themes/automatiza-tech/
├── lib/
│   └── invoice-pdf-fpdf.php              [MODIFICADO] ⚠️
├── inc/
│   ├── contact-form.php                  [MODIFICADO] ⚠️
│   └── invoice-settings.php              [NUEVO] ✨
└── functions.php                         [MODIFICADO] ⚠️
```

#### Detalle de cambios por archivo:

**1. lib/invoice-pdf-fpdf.php** `[MODIFICADO]`
- Sistema multi-moneda (CLP/USD)
- Detección automática de país por 3 métodos:
  - Campo `country` en base de datos
  - Código telefónico del cliente
  - Default a Chile (CL)
- Cálculo de IVA condicional (19% solo para Chile)
- Formato de moneda según país:
  - CLP: `$350.000` (sin decimales)
  - USD: `USD $400.00` (con decimales)
- Datos de empresa desde configuración (get_option)
- Diseño mejorado con gradientes y separadores

**2. inc/contact-form.php** `[MODIFICADO]`
- **Nuevo método:** `detect_country_from_phone()` - Detecta país por código telefónico
- Campo `country` agregado al insertar clientes
- **Sistema completo de emails:**
  - `send_notification_email()` - Email interno al recibir contacto
  - `send_contracted_client_email()` - Email interno al contratar cliente
  - `send_invoice_email_to_client()` - Email al cliente con factura PDF
  - `configure_smtp()` - Configuración SMTP para envío confiable
- **Generación de facturas:**
  - `generate_and_save_pdf()` - Genera PDF con FPDF
  - `save_invoice_to_database()` - Guarda en BD
  - `save_invoice_file()` - Backup HTML
- Soporte para 18 países con códigos telefónicos

**3. inc/invoice-settings.php** `[NUEVO]`
- Panel completo de configuración en admin
- Registro de settings en wp_options
- Formulario con validación
- Vista previa de factura
- Diseño moderno con estilos integrados

**4. functions.php** `[MODIFICADO]`
- Línea agregada: `require_once get_template_directory() . '/inc/invoice-settings.php';`
- (Buscar alrededor de las líneas 30-40 donde están otros requires)

### Archivo SQL de Migración

```
sql/
└── migration-production-multi-currency.sql   [NUEVO]
```

**Contenido del script SQL:**
- Verificación condicional de columna `country`
- ALTER TABLE para agregar columna
- UPDATE masivo para asignar países por código telefónico (18 países)
- Queries de verificación
- Comentarios explicativos

---

## 🔧 Pasos de Despliegue

### ⚠️ IMPORTANTE: Hacer en Horario de Bajo Tráfico

Recomendado: Madrugada o domingo en la mañana

---

### PASO 1: Backup de Seguridad 💾

**🔴 CRÍTICO: No saltarse este paso**

#### Opción A: Desde cPanel/phpMyAdmin
```
1. Login a cPanel de tu hosting
2. Ir a phpMyAdmin
3. Seleccionar base de datos de WordPress
4. Clic en pestaña "Exportar"
5. Método: Rápido
6. Formato: SQL
7. Clic en "Continuar"
8. Descargar → Guardar como: backup_YYYYMMDD_HHMM.sql
```

#### Opción B: Desde SSH
```bash
# Conectar a servidor
ssh usuario@tuservidor.com

# Backup de base de datos
mysqldump -u usuario_mysql -p nombre_base_datos > backup_$(date +%Y%m%d_%H%M).sql

# Backup de archivos del tema
cd wp-content/themes
tar -czf automatiza-tech-backup-$(date +%Y%m%d).tar.gz automatiza-tech/

# Descargar backups a tu local (desde otra terminal)
scp usuario@tuservidor.com:~/backup_*.sql .
scp usuario@tuservidor.com:~/automatiza-tech-backup-*.tar.gz .
```

✅ **Verificar que los archivos de backup se descargaron correctamente**

---

### PASO 2: Subir Archivos PHP 📤

#### Opción A: FTP/SFTP (FileZilla, WinSCP, etc.)

```
1. Conectar con tu cliente FTP favorito
2. Navegar a: /wp-content/themes/automatiza-tech/

3. Subir/Reemplazar archivos:
   📁 lib/
      📄 invoice-pdf-fpdf.php         → REEMPLAZAR
   
   📁 inc/
      📄 contact-form.php             → REEMPLAZAR
      📄 invoice-settings.php         → SUBIR NUEVO ✨
   
   📄 functions.php                   → REEMPLAZAR

4. Verificar que los archivos tengan permisos 644
```

#### Opción B: Git (Recomendado si usas control de versiones)

```bash
# En tu repositorio local
git add lib/invoice-pdf-fpdf.php
git add inc/contact-form.php
git add inc/invoice-settings.php
git add functions.php
git add sql/migration-production-multi-currency.sql

git commit -m "feat: Sistema multi-moneda, emails automáticos y panel de configuración"
git push origin main

# En el servidor de producción
ssh usuario@tuservidor.com
cd /path/to/wordpress/wp-content/themes/automatiza-tech
git pull origin main
```

✅ **Verificar que los 4 archivos se subieron correctamente**

---

### PASO 3: Ejecutar Migración SQL 🗄️

#### Opción A: phpMyAdmin (Más Visual)

```
1. Login a phpMyAdmin en producción
2. Seleccionar base de datos de WordPress
3. Clic en pestaña "SQL"
4. Copiar TODO el contenido de: sql/migration-production-multi-currency.sql
5. Pegar en el editor SQL
6. Clic en "Continuar" o "Go"
7. Esperar confirmación (puede tomar 10-30 segundos)
```

**Mensajes esperados:**
```
✅ Columna country agregada exitosamente
✅ X filas actualizadas (clientes con país asignado)
✅ Consultas de verificación ejecutadas
```

#### Opción B: MySQL CLI (Más Rápido)

```bash
# Subir archivo SQL al servidor
scp sql/migration-production-multi-currency.sql usuario@servidor.com:~/

# Conectar por SSH
ssh usuario@servidor.com

# Ejecutar migración
mysql -u usuario_mysql -p nombre_base_datos < migration-production-multi-currency.sql

# Ver resultados de verificación
mysql -u usuario_mysql -p nombre_base_datos -e "
SELECT country, COUNT(*) as total 
FROM wp_automatiza_tech_clients 
GROUP BY country 
ORDER BY total DESC;
"
```

✅ **Verificar que la columna country existe y tiene datos**

---

### PASO 4: Verificar Servicios con Precio USD 💵

**Todos los servicios activos DEBEN tener precio_usd configurado**

#### Desde phpMyAdmin:
```sql
SELECT id, name, price_clp, price_usd 
FROM wp_automatiza_services 
WHERE status = 'active' 
AND (price_usd IS NULL OR price_usd = 0);
```

#### Si hay servicios sin precio USD:

**Calcular precio sugerido:**
- Usar tasa de conversión actual (ej: 1 USD ≈ 875 CLP)
- Redondear a valores limpios

**Ejemplo:**
```sql
-- Servicio con price_clp = 350000
-- USD sugerido = 350000 / 875 = 400

UPDATE wp_automatiza_services 
SET price_usd = 400 
WHERE id = 1;

UPDATE wp_automatiza_services 
SET price_usd = 1200 
WHERE id = 2 AND price_clp = 1050000;
```

✅ **Ejecutar UPDATE para cada servicio sin precio USD**

---

### PASO 5: Configurar Datos de la Empresa 🏢

**Acceder al panel de configuración:**

```
1. Login al WordPress Admin en producción
2. En el menú lateral, buscar: "Datos Facturación"
3. Llenar todos los campos:
   - Nombre empresa: Automatiza Tech
   - RUT: 12.345.678-9
   - Giro: Servicios de Automatización Digital
   - Dirección: Tu dirección real
   - Email: contacto@automatizatech.cl
   - Teléfono: +56 9 XXXX XXXX
   - Web: https://automatizatech.shop
4. Clic en "Guardar Cambios"
5. Verificar confirmación: "Configuración guardada correctamente"
```

✅ **Verificar vista previa en la misma página**

---

### PASO 6: Pruebas en Producción 🧪

#### Prueba 1: Verificar Sistema (Script Automático)

```
URL: https://tudominio.com/verify-system.php

Verificar:
✅ Campo country existe
✅ Todos los clientes tienen país
✅ Distribución de países es correcta
✅ Servicios tienen price_clp y price_usd
✅ Archivos PHP están presentes
✅ No hay errores en pantalla
```

#### Prueba 2: Panel de Configuración

```
URL: WordPress Admin → Datos Facturación

Verificar:
✅ Panel carga correctamente
✅ Todos los campos muestran los valores guardados
✅ Vista previa se muestra
✅ No hay errores de consola (F12)
```

#### Prueba 3: Factura Chile (CLP)

```
URL: https://tudominio.com/test-fpdf-invoice.php?country=CL

Verificar:
✅ PDF se genera y descarga
✅ Moneda es CLP ($)
✅ Precios sin decimales (ej: $350.000)
✅ Muestra subtotal NETO
✅ Muestra IVA 19%
✅ Muestra TOTAL
✅ Cálculo correcto: Total / 1.19 = Neto
✅ Datos de empresa son correctos
```

#### Prueba 4: Factura Internacional (USD)

```
URL: https://tudominio.com/test-fpdf-invoice.php?country=US

Verificar:
✅ PDF se genera y descarga
✅ Moneda es USD (USD $)
✅ Precios con 2 decimales (ej: USD $400.00)
✅ NO muestra IVA
✅ Muestra TOTAL directo
✅ Muestra nota: "Factura internacional - No aplica IVA chileno"
✅ Datos de empresa son correctos
```

#### Prueba 5: Sistema de Emails (Opcional)

**⚠️ Cuidado: Esto enviará emails reales**

```
1. Crear un contacto de prueba desde el formulario web
2. Verificar que llegue email a automatizatech.bots@gmail.com
3. Convertir contacto a cliente desde panel admin
4. Verificar:
   ✅ Email con PDF llega al cliente
   ✅ Email de notificación llega a automatizatech.bots@gmail.com
   ✅ PDF adjunto se ve correctamente
   ✅ Factura tiene el país y moneda correctos
```

---

### PASO 7: Limpieza y Seguridad 🔒

#### Eliminar o Proteger Archivos de Prueba

**Archivos a eliminar/proteger:**
```
verify-system.php           → ELIMINAR o renombrar
test-fpdf-invoice.php       → ELIMINAR o renombrar
test-country-detection.php  → ELIMINAR
add-country-field.php       → ELIMINAR
```

**Opción 1: Eliminar (Recomendado)**
```bash
rm verify-system.php
rm test-fpdf-invoice.php
rm test-country-detection.php
rm add-country-field.php
```

**Opción 2: Renombrar con Seguridad**
```bash
mv verify-system.php verify-system-PRIVATE-XyZ123.php
mv test-fpdf-invoice.php test-invoice-PRIVATE-XyZ123.php
```

#### Proteger Carpeta de Facturas

Crear archivo: `/wp-content/uploads/invoices/.htaccess`

```apache
# Proteger facturas
Order Deny,Allow
Deny from all
<FilesMatch "\.(pdf)$">
    Allow from all
</FilesMatch>

# Prevenir listado de directorio
Options -Indexes

# Solo permitir acceso desde dominio propio
SetEnvIf Referer "^https://tudominio\.com" local_ref=1
Order Allow,Deny
Allow from env=local_ref
```

---

## ✅ Verificación Post-Despliegue

### Checklist Completo

#### Base de Datos
- [ ] Columna `country` existe en `wp_automatiza_tech_clients`
- [ ] Todos los clientes tienen país asignado (no NULL)
- [ ] Distribución de países es correcta
- [ ] Todos los servicios activos tienen `price_usd > 0`

#### Archivos PHP
- [ ] `lib/invoice-pdf-fpdf.php` actualizado
- [ ] `inc/contact-form.php` actualizado
- [ ] `inc/invoice-settings.php` existe
- [ ] `functions.php` incluye require de invoice-settings

#### Panel de Admin
- [ ] Menú "Datos Facturación" visible
- [ ] Todos los campos cargan correctamente
- [ ] Guardado funciona
- [ ] Vista previa se muestra

#### Facturas
- [ ] Facturas Chile usan CLP con IVA 19%
- [ ] Facturas internacionales usan USD sin IVA
- [ ] Datos de empresa aparecen correctamente
- [ ] PDF se genera sin errores
- [ ] Cálculos son correctos

#### Emails
- [ ] Email de contacto llega a automatizatech.bots@gmail.com
- [ ] Email con factura llega al cliente
- [ ] PDF se adjunta correctamente
- [ ] Email de cliente contratado llega a admin

#### Seguridad
- [ ] Archivos de prueba eliminados o protegidos
- [ ] Carpeta /invoices/ protegida con .htaccess
- [ ] No hay errores en logs de WordPress
- [ ] No hay errores en logs de PHP

---

## 📊 Monitoreo Post-Despliegue

### Logs a Revisar

#### WordPress Debug Log
```bash
# Ver últimas líneas
tail -f wp-content/debug.log

# Filtrar errores de facturas
grep "INVOICE\|PDF\|CORREO" wp-content/debug.log
```

#### Logs de Servidor
```bash
# Apache
tail -f /var/log/apache2/error.log

# Nginx
tail -f /var/log/nginx/error.log

# PHP-FPM
tail -f /var/log/php-fpm/error.log
```

### Queries de Monitoreo

#### Clientes por País (Últimas 24h)
```sql
SELECT 
    country,
    COUNT(*) as nuevos_clientes
FROM wp_automatiza_tech_clients
WHERE contracted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY country;
```

#### Facturas Generadas Hoy
```sql
SELECT 
    invoice_number,
    client_id,
    created_at,
    pdf_path
FROM wp_automatiza_tech_invoices
WHERE DATE(created_at) = CURDATE()
ORDER BY created_at DESC;
```

#### Emails Enviados (desde logs)
```bash
grep "CORREO ENVIADO" wp-content/debug.log | tail -20
```

---

## 🚨 Plan de Rollback (Si algo sale mal)

### Síntomas de Problemas

- ❌ Sitio web muestra pantalla blanca
- ❌ Error 500 en páginas
- ❌ Panel de admin no carga
- ❌ Facturas no se generan
- ❌ Emails no se envían

### Pasos de Rollback

#### 1. Restaurar Base de Datos

```bash
# Conectar a servidor
ssh usuario@servidor.com

# Restaurar backup
mysql -u usuario_mysql -p nombre_base_datos < backup_YYYYMMDD_HHMM.sql

# Verificar
mysql -u usuario_mysql -p nombre_base_datos -e "SHOW TABLES;"
```

#### 2. Restaurar Archivos PHP

```bash
# Desde backup tar.gz
cd wp-content/themes
rm -rf automatiza-tech
tar -xzf automatiza-tech-backup-YYYYMMDD.tar.gz

# O desde FTP
# Descargar backup de archivos
# Reemplazar archivos en servidor
```

#### 3. Limpiar Cache

```bash
# WordPress cache
wp cache flush

# Si tienes Redis
redis-cli FLUSHALL

# Si tienes Memcached
echo 'flush_all' | nc localhost 11211

# Desde WP Admin
# Ir a plugin de cache → Purgar todo
```

#### 4. Verificar Funcionamiento

```
1. Acceder al sitio web → ¿Carga?
2. Login a admin → ¿Funciona?
3. Ver lista de clientes → ¿Se muestra?
4. Generar una factura de prueba → ¿Funciona?
```

---

## 📞 Soporte y Problemas Comunes

### Problema 1: "Column 'country' doesn't exist"

**Causa:** Migración SQL no se ejecutó

**Solución:**
```sql
-- Verificar si existe
SHOW COLUMNS FROM wp_automatiza_tech_clients LIKE 'country';

-- Si no existe, ejecutar:
ALTER TABLE wp_automatiza_tech_clients 
ADD COLUMN country VARCHAR(2) DEFAULT 'CL' 
COMMENT 'Código ISO de 2 letras del país' 
AFTER phone;

-- Actualizar datos
UPDATE wp_automatiza_tech_clients 
SET country = 'CL' 
WHERE phone LIKE '+56%';
```

### Problema 2: Facturas sin Precio USD

**Causa:** Servicios no tienen price_usd

**Solución:**
```sql
-- Listar servicios sin USD
SELECT id, name, price_clp, price_usd 
FROM wp_automatiza_services 
WHERE status = 'active' 
AND (price_usd IS NULL OR price_usd = 0);

-- Actualizar (ajustar valores según tu tasa)
UPDATE wp_automatiza_services 
SET price_usd = ROUND(price_clp / 875, 2) 
WHERE price_usd IS NULL OR price_usd = 0;
```

### Problema 3: Panel "Datos Facturación" no aparece

**Causa:** functions.php no tiene el require

**Solución:**
```php
// Editar functions.php y agregar:
require_once get_template_directory() . '/inc/invoice-settings.php';

// Limpiar cache de WordPress
wp cache flush
```

### Problema 4: Emails no se envían

**Causa:** Configuración SMTP o límites del servidor

**Solución:**
```php
// Verificar configuración SMTP en inc/contact-form.php
// Método: configure_smtp()

// Probar envío manual
wp_mail('test@example.com', 'Test', 'Mensaje de prueba');

// Verificar logs
grep "wp_mail\|phpmailer" wp-content/debug.log

// Alternativa: Instalar plugin WP Mail SMTP
```

### Problema 5: PDF no se genera

**Causa:** Permisos de carpeta o FPDF

**Solución:**
```bash
# Verificar/crear carpeta
mkdir -p wp-content/uploads/invoices
chmod 755 wp-content/uploads/invoices

# Verificar que FPDF existe
ls -la lib/fpdf/

# Ver logs
grep "FPDF\|PDF" wp-content/debug.log
```

---

## ✅ Confirmación Final

Una vez completados TODOS los pasos, documentar:

```
✅ Fecha de despliegue: _____________
✅ Hora de inicio: _____________
✅ Hora de finalización: _____________
✅ Backup guardado en: _____________
✅ Todas las pruebas pasaron: SÍ / NO
✅ Problemas encontrados: _____________
✅ Archivos de prueba eliminados: SÍ / NO
✅ Monitoreo activo: SÍ / NO
✅ Responsable del despliegue: _____________
```

---

## 📚 Referencias

- **Script de verificación:** `verify-system.php`
- **Script SQL:** `sql/migration-production-multi-currency.sql`
- **Documentación técnica:** Ver comentarios en los archivos PHP
- **Logs:** `wp-content/debug.log`
- **Facturas:** `wp-content/uploads/invoices/`

---

## 🎯 Próximos Pasos (Opcional)

### Mejoras Futuras

1. **Dashboard de Facturas**
   - Ver todas las facturas desde admin
   - Reenviar facturas por email
   - Descargar facturas antiguas

2. **Más Monedas**
   - Euro (EUR)
   - Peso Argentino (ARS)
   - Peso Colombiano (COP)

3. **Conversión Automática**
   - API de tasas de cambio en tiempo real
   - Actualización automática de precios USD

4. **Multi-idioma**
   - Facturas en inglés para clientes USA
   - Facturas en portugués para Brasil
   - Facturas en español para resto de LATAM

5. **Firma Digital**
   - Integración con servicios de firma electrónica
   - Validación de facturas con QR

---

**¡Despliegue Exitoso! 🎉**

Si tienes dudas o problemas, revisa la sección de "Soporte y Problemas Comunes" o contacta al equipo de desarrollo.
