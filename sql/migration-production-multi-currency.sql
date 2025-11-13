-- =====================================================
-- MIGRACIÓN PRODUCCIÓN: Sistema Facturación Multi-Moneda
-- Fecha: 2025-11-11
-- Descripción: Agregar campo country y actualizar estructura
-- =====================================================

-- PASO 1: Verificar si la columna ya existe
SET @dbname = DATABASE();
SET @tablename = "wp_automatiza_tech_clients";
SET @columnname = "country";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 'La columna country ya existe' AS msg;",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(2) DEFAULT 'CL' COMMENT 'Código ISO de 2 letras del país' AFTER phone;")
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- PASO 2: Actualizar clientes existentes según código telefónico
-- Chile: +56
UPDATE wp_automatiza_tech_clients 
SET country = 'CL' 
WHERE phone LIKE '+56%' OR country IS NULL OR country = '';

-- Estados Unidos/Canadá: +1
UPDATE wp_automatiza_tech_clients 
SET country = 'US' 
WHERE phone LIKE '+1%';

-- Argentina: +54
UPDATE wp_automatiza_tech_clients 
SET country = 'AR' 
WHERE phone LIKE '+54%';

-- Colombia: +57
UPDATE wp_automatiza_tech_clients 
SET country = 'CO' 
WHERE phone LIKE '+57%';

-- México: +52
UPDATE wp_automatiza_tech_clients 
SET country = 'MX' 
WHERE phone LIKE '+52%';

-- Perú: +51
UPDATE wp_automatiza_tech_clients 
SET country = 'PE' 
WHERE phone LIKE '+51%';

-- España: +34
UPDATE wp_automatiza_tech_clients 
SET country = 'ES' 
WHERE phone LIKE '+34%';

-- Brasil: +55
UPDATE wp_automatiza_tech_clients 
SET country = 'BR' 
WHERE phone LIKE '+55%';

-- Ecuador: +593
UPDATE wp_automatiza_tech_clients 
SET country = 'EC' 
WHERE phone LIKE '+593%';

-- Paraguay: +595
UPDATE wp_automatiza_tech_clients 
SET country = 'PY' 
WHERE phone LIKE '+595%';

-- Uruguay: +598
UPDATE wp_automatiza_tech_clients 
SET country = 'UY' 
WHERE phone LIKE '+598%';

-- Venezuela: +58
UPDATE wp_automatiza_tech_clients 
SET country = 'VE' 
WHERE phone LIKE '+58%';

-- Costa Rica: +506
UPDATE wp_automatiza_tech_clients 
SET country = 'CR' 
WHERE phone LIKE '+506%';

-- Panamá: +507
UPDATE wp_automatiza_tech_clients 
SET country = 'PA' 
WHERE phone LIKE '+507%';

-- El Salvador: +503
UPDATE wp_automatiza_tech_clients 
SET country = 'SV' 
WHERE phone LIKE '+503%';

-- Honduras: +504
UPDATE wp_automatiza_tech_clients 
SET country = 'HN' 
WHERE phone LIKE '+504%';

-- Nicaragua: +505
UPDATE wp_automatiza_tech_clients 
SET country = 'NI' 
WHERE phone LIKE '+505%';

-- Guatemala: +502
UPDATE wp_automatiza_tech_clients 
SET country = 'GT' 
WHERE phone LIKE '+502%';

-- PASO 3: Asegurar que todos tengan un país (por defecto Chile)
UPDATE wp_automatiza_tech_clients 
SET country = 'CL' 
WHERE country IS NULL OR country = '';

-- PASO 4: Verificar integridad de datos
SELECT 
    'VERIFICACIÓN DE MIGRACIÓN' AS tipo,
    COUNT(*) as total_clientes,
    SUM(CASE WHEN country IS NOT NULL THEN 1 ELSE 0 END) as con_pais,
    SUM(CASE WHEN country IS NULL THEN 1 ELSE 0 END) as sin_pais
FROM wp_automatiza_tech_clients;

-- PASO 5: Mostrar resumen por país
SELECT 
    CASE country
        WHEN 'CL' THEN '🇨🇱 Chile'
        WHEN 'US' THEN '🇺🇸 Estados Unidos'
        WHEN 'AR' THEN '🇦🇷 Argentina'
        WHEN 'CO' THEN '🇨🇴 Colombia'
        WHEN 'MX' THEN '🇲🇽 México'
        WHEN 'PE' THEN '🇵🇪 Perú'
        WHEN 'ES' THEN '🇪🇸 España'
        WHEN 'BR' THEN '🇧🇷 Brasil'
        ELSE CONCAT('🌎 ', country)
    END as pais,
    country as codigo,
    COUNT(*) as total_clientes,
    CASE 
        WHEN country = 'CL' THEN 'CLP (Pesos Chilenos) con IVA 19%'
        ELSE 'USD (Dólares) sin IVA'
    END as moneda_facturacion
FROM wp_automatiza_tech_clients
GROUP BY country
ORDER BY total_clientes DESC;

-- PASO 6: Verificar servicios tienen ambos precios
SELECT 
    'VERIFICACIÓN SERVICIOS' AS tipo,
    COUNT(*) as total_servicios,
    SUM(CASE WHEN price_clp > 0 THEN 1 ELSE 0 END) as con_precio_clp,
    SUM(CASE WHEN price_usd > 0 THEN 1 ELSE 0 END) as con_precio_usd,
    SUM(CASE WHEN price_clp > 0 AND price_usd > 0 THEN 1 ELSE 0 END) as con_ambos_precios
FROM wp_automatiza_services
WHERE status = 'active';

-- PASO 7: Mostrar servicios que necesitan precio USD
SELECT 
    id,
    name,
    price_clp,
    price_usd,
    CASE 
        WHEN price_usd = 0 OR price_usd IS NULL THEN 'NECESITA PRECIO USD'
        ELSE 'OK'
    END as estado
FROM wp_automatiza_services
WHERE status = 'active'
AND (price_usd = 0 OR price_usd IS NULL);

-- =====================================================
-- NOTAS IMPORTANTES PARA PRODUCCIÓN:
-- =====================================================
-- 1. Hacer BACKUP completo de la base de datos antes de ejecutar
-- 2. Ejecutar en horario de bajo tráfico
-- 3. Verificar que todos los servicios tienen price_usd
-- 4. Probar generación de facturas después de migrar
-- 5. Los archivos PHP ya están actualizados en el tema
-- 
-- ARCHIVOS PHP MODIFICADOS (ya incluidos en el tema):
-- - wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php
-- - wp-content/themes/automatiza-tech/inc/contact-form.php
-- - wp-content/themes/automatiza-tech/inc/invoice-settings.php
-- 
-- NUEVOS ARCHIVOS DE DOCUMENTACIÓN:
-- - FACTURACION-MULTI-MONEDA.md
-- - CONFIGURACION-FACTURACION.md
-- =====================================================
