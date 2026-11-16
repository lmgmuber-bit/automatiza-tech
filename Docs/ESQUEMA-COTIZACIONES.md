# Esquema de la tabla de cotizaciones

El script de instalación `install-quotations.php` se retiró del tema. Este es el `CREATE TABLE` de la tabla `automatiza_tech_quotations` (con el prefijo de WordPress delante), sin datos. Se ejecuta con `dbDelta()` desde WP-CLI o desde un script local.

```sql
CREATE TABLE IF NOT EXISTS `automatiza_tech_quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL COMMENT 'Número de cotización: C-AT-YYYYMMDD-XXXX',
  `contact_id` int(11) NOT NULL COMMENT 'ID del contacto',
  `contact_name` varchar(255) NOT NULL,
  `contact_email` varchar(255) NOT NULL,
  `contact_company` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL COMMENT 'ID del primer plan (para compatibilidad)',
  `plan_name` text NOT NULL COMMENT 'Nombre(s) de plan(es), separados por + si son múltiples',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `iva` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quotation_html` longtext COMMENT 'HTML completo de la cotización',
  `quotation_file_path` varchar(500) DEFAULT NULL COMMENT 'Ruta al archivo PDF generado',
  `qr_code_data` text COMMENT 'Datos codificados en el QR',
  `status` enum('pending','accepted','rejected','expired') NOT NULL DEFAULT 'pending',
  `valid_until` datetime NOT NULL COMMENT 'Fecha de vencimiento (3 días desde creación)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` text COMMENT 'Notas adicionales',
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `contact_id` (`contact_id`),
  KEY `contact_email` (`contact_email`),
  KEY `status` (`status`),
  KEY `valid_until` (`valid_until`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cotizaciones enviadas a contactos interesados';
```
