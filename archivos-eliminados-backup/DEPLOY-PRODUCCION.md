# 🚀 Guía de Despliegue a Producción - Sistema Multi-Moneda

## 📋 Checklist Pre-Despliegue

- [ ] Backup completo de base de datos producción
- [ ] Backup de archivos del tema actual
- [ ] Verificar que todos los servicios tienen price_usd
- [ ] Probar facturas en entorno local
- [ ] Coordinar horario de mantenimiento (bajo tráfico)

## 📦 Archivos a Subir a Producción

### 1. Archivos PHP Modificados

```
wp-content/themes/automatiza-tech/
├── lib/
│   └── invoice-pdf-fpdf.php         [MODIFICADO]
├── inc/
│   ├── contact-form.php             [MODIFICADO]
│   └── invoice-settings.php         [NUEVO]
└── functions.php                    [MODIFICADO - línea agregada]
```

### 2. Archivos de Migración

```
sql/
└── migration-production-multi-currency.sql   [NUEVO]
```

### 3. Documentación (Opcional)

```
FACTURACION-MULTI-MONEDA.md         [NUEVO]
CONFIGURACION-FACTURACION.md        [NUEVO]
```

## 🔧 Pasos de Despliegue

### PASO 1: Backup de Seguridad

#### Opción A: Desde cPanel/phpMyAdmin
1. Ir a phpMyAdmin en producción
2. Seleccionar base de datos
3. Clic en "Exportar"
4. Método: Rápido
5. Formato: SQL
6. Descargar archivo → Guardar con fecha: `backup_YYYYMMDD_HHMM.sql`

#### Opción B: Desde línea de comandos
```bash
# SSH a servidor producción
ssh usuario@servidor.com

# Backup de base de datos
mysqldump -u usuario -p nombre_bd > backup_$(date +%Y%m%d_%H%M).sql

# Backup de archivos del tema
cd wp-content/themes
tar -czf automatiza-tech-backup-$(date +%Y%m%d).tar.gz automatiza-tech/
```

### PASO 2: Subir Archivos PHP

#### Opción A: FTP/SFTP
```
1. Conectar con FileZilla o cliente FTP
2. Navegar a: /wp-content/themes/automatiza-tech/
3. Subir archivos:
   - lib/invoice-pdf-fpdf.php         (REEMPLAZAR)
   - inc/contact-form.php             (REEMPLAZAR)
   - inc/invoice-settings.php         (NUEVO)
   - functions.php                    (REEMPLAZAR)
```

#### Opción B: Git (Recomendado)
```bash
# En local
git add .
git commit -m "feat: Sistema multi-moneda con detección automática de país"
git push origin main

# En servidor producción
cd /path/to/wordpress
git pull origin main
```

### PASO 3: Ejecutar Migración SQL

#### Opción A: phpMyAdmin
1. Ir a phpMyAdmin en producción
2. Seleccionar base de datos
3. Clic en pestaña "SQL"
4. Copiar contenido de `migration-production-multi-currency.sql`
5. Pegar en el editor SQL
6. Clic en "Continuar"
7. ✅ Verificar mensajes de éxito

#### Opción B: MySQL CLI
```bash
# SSH a servidor
ssh usuario@servidor.com

# Ejecutar migración
mysql -u usuario -p nombre_bd < migration-production-multi-currency.sql

# Verificar resultados
mysql -u usuario -p nombre_bd -e "
    SELECT country, COUNT(*) as total 
    FROM wp_automatiza_tech_clients 
    GROUP BY country;
"
```

### PASO 4: Verificar Servicios con Precios USD

```sql
-- Verificar que todos los servicios tengan precio USD
SELECT id, name, price_clp, price_usd
FROM wp_automatiza_services
WHERE status = 'active'
AND (price_usd IS NULL OR price_usd = 0);
```

**Si hay servicios sin precio USD:**
```sql
-- Ejemplo: Actualizar precios USD (ajustar según tu tasa)
-- Tasa ejemplo: 1 USD = 875 CLP

UPDATE wp_automatiza_services
SET price_usd = ROUND(price_clp / 875, 2)
WHERE status = 'active'
AND (price_usd IS NULL OR price_usd = 0);

-- O actualizar manualmente uno por uno:
UPDATE wp_automatiza_services 
SET price_usd = 400.00 
WHERE id = 1; -- Plan Profesional
```

### PASO 5: Pruebas en Producción

#### 5.1 Verificar Panel de Configuración
```
URL: https://tudominio.com/wp-admin/admin.php?page=automatiza-invoice-settings

✓ Verificar que carga correctamente
✓ Probar cambiar datos de empresa
✓ Guardar y verificar mensaje de éxito
```

#### 5.2 Probar Detección de País
```
URL: https://tudominio.com/test-country-detection.php

✓ Ver distribución de clientes por país
✓ Verificar que todos tienen país asignado
✓ Confirmar monedas correctas (CL=CLP, otros=USD)
```

#### 5.3 Generar Factura de Prueba Chile
```
URL: https://tudominio.com/test-fpdf-invoice.php?country=CL

✓ Verificar que muestra CLP
✓ Confirmar cálculo IVA 19%
✓ Revisar formato: $350.000 (sin decimales)
✓ Descargar PDF y verificar
```

#### 5.4 Generar Factura de Prueba Internacional
```
URL: https://tudominio.com/test-fpdf-invoice.php?country=US

✓ Verificar que muestra USD
✓ Confirmar NO aplica IVA
✓ Revisar formato: USD $400.00 (con decimales)
✓ Descargar PDF y verificar
```

### PASO 6: Limpieza (Opcional)

#### Eliminar archivos de test en producción:
```bash
rm test-country-detection.php
rm test-fpdf-invoice.php
rm add-country-field.php
```

O protegerlos con .htaccess:
```apache
# .htaccess en raíz de WordPress
<Files "test-*.php">
    Order Allow,Deny
    Deny from all
    Allow from 192.168.1.0/24  # Tu IP de oficina
</Files>
```

## 🔍 Verificación Post-Despliegue

### Checklist de Verificación

- [ ] **Base de datos actualizada**
  - Campo `country` existe en `wp_automatiza_tech_clients`
  - Todos los clientes tienen país asignado
  - Distribución de países es correcta

- [ ] **Servicios con precios**
  - Todos los servicios activos tienen `price_clp`
  - Todos los servicios activos tienen `price_usd`

- [ ] **Panel de administración**
  - Menú "Datos Facturación" visible
  - Formulario de configuración funciona
  - Cambios se guardan correctamente

- [ ] **Generación de facturas**
  - Facturas Chile usan CLP con IVA
  - Facturas internacionales usan USD sin IVA
  - Precios se muestran correctamente
  - PDFs se descargan sin errores

- [ ] **Formulario de contacto**
  - Acepta números con código de país
  - Valida formato internacional
  - Guarda contactos correctamente

- [ ] **Conversión contacto → cliente**
  - Detecta país automáticamente
  - Guarda campo country
  - No hay errores en logs

## 📊 Monitoreo Post-Despliegue

### Logs a Revisar

```bash
# Logs de PHP (buscar errores)
tail -f /var/log/php-fpm/error.log | grep -i "automatiza"

# Logs de WordPress
tail -f wp-content/debug.log | grep -i "invoice\|country"

# Logs de Apache/Nginx
tail -f /var/log/apache2/error.log
```

### Queries de Monitoreo

```sql
-- Clientes sin país (debería ser 0)
SELECT COUNT(*) as sin_pais
FROM wp_automatiza_tech_clients
WHERE country IS NULL OR country = '';

-- Distribución actual de clientes
SELECT 
    country,
    COUNT(*) as total,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wp_automatiza_tech_clients), 2) as porcentaje
FROM wp_automatiza_tech_clients
GROUP BY country
ORDER BY total DESC;

-- Servicios sin precio USD (debería ser 0)
SELECT COUNT(*) as sin_precio_usd
FROM wp_automatiza_services
WHERE status = 'active'
AND (price_usd IS NULL OR price_usd = 0);
```

## 🚨 Plan de Rollback

### Si algo sale mal:

#### Rollback de Base de Datos
```bash
# Restaurar backup
mysql -u usuario -p nombre_bd < backup_YYYYMMDD_HHMM.sql

# Verificar restauración
mysql -u usuario -p nombre_bd -e "SHOW TABLES;"
```

#### Rollback de Archivos
```bash
# Restaurar desde backup
cd wp-content/themes
tar -xzf automatiza-tech-backup-YYYYMMDD.tar.gz

# O revertir commit Git
git revert HEAD
git push origin main
```

## 📞 Soporte

### Problemas Comunes

**1. Error: "Column 'country' doesn't exist"**
- Solución: Re-ejecutar migración SQL
- Verificar: `SHOW COLUMNS FROM wp_automatiza_tech_clients;`

**2. Facturas en moneda incorrecta**
- Verificar campo country del cliente
- Verificar código telefónico del cliente
- Re-ejecutar actualización de países

**3. Servicios sin precio USD**
- Ejecutar UPDATE para calcular precio_usd
- O actualizar manualmente desde admin

**4. Panel "Datos Facturación" no aparece**
- Verificar que functions.php incluye invoice-settings.php
- Limpiar caché de WordPress
- Verificar permisos de usuario (debe ser admin)

## ✅ Confirmación Final

Una vez completados todos los pasos:

```
✓ Base de datos migrada correctamente
✓ Archivos PHP actualizados
✓ Servicios con ambos precios (CLP y USD)
✓ Facturas Chile usan CLP con IVA
✓ Facturas internacionales usan USD sin IVA
✓ Detección automática de país funciona
✓ Panel de configuración operativo
✓ Sin errores en logs
✓ Backup de seguridad guardado
```

**Sistema Multi-Moneda desplegado exitosamente en producción! 🎉**

---

**Fecha de despliegue:** _______________  
**Ejecutado por:** _______________  
**Hora inicio:** _______________  
**Hora fin:** _______________  
**Incidentes:** _______________
