-- ========================================
-- ASIGNAR PRECIOS A PLANES 4, 5, 6
-- ========================================
-- Estos planes están activos pero sin precios definidos
-- Lo que causa que no aparezcan en el combo de conversión a cliente
-- ========================================

-- Plan 4: Atención 24/7
-- Precio sugerido: $150.000 CLP / $171.43 USD
UPDATE wp_automatiza_services 
SET price_clp = 150000, 
    price_usd = 171.43,
    description = 'Soporte y atención al cliente 24 horas, 7 días a la semana'
WHERE id = 4;

-- Plan 5: Aumenta tus Ventas
-- Precio sugerido: $200.000 CLP / $228.57 USD
UPDATE wp_automatiza_services 
SET price_clp = 200000, 
    price_usd = 228.57,
    description = 'Estrategias y herramientas para incrementar tus ventas online'
WHERE id = 5;

-- Plan 6: Fácil Integración
-- Precio sugerido: $180.000 CLP / $205.71 USD
UPDATE wp_automatiza_services 
SET price_clp = 180000, 
    price_usd = 205.71,
    description = 'Integración simple y rápida con tus sistemas existentes'
WHERE id = 6;

-- Verificar los cambios
SELECT id, name, status, price_clp, price_usd, description
FROM wp_automatiza_services
WHERE id IN (4, 5, 6);

-- ========================================
-- NOTA: Después de ejecutar estos UPDATEs
-- los 7 planes aparecerán en el combo
-- ========================================
