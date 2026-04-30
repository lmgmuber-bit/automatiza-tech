<?php
/**
 * Setup tabla de contratos digitales (firma electrónica simple)
 * Ejecutar 1 vez: https://automatizatech.cl/contracts/setup-contracts-db.php?key=AT_SETUP_2026
 */
require_once dirname(__DIR__) . '/wp-load.php';

if (!isset($_GET['key']) || $_GET['key'] !== 'AT_SETUP_2026') {
    wp_die('Acceso denegado');
}

global $wpdb;
$charset = $wpdb->get_charset_collate();
$table = $wpdb->prefix . 'automatiza_contracts';

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$sql = "CREATE TABLE {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    proposal_id BIGINT UNSIGNED NULL,
    contract_number VARCHAR(40) NOT NULL,
    type ENUM('soporte','servicios','sla','nda','handover') DEFAULT 'soporte',
    template_id VARCHAR(80) DEFAULT 'soporte_v2',
    placeholders LONGTEXT NOT NULL,
    pdf_url VARCHAR(500) NULL,
    signed_pdf_url VARCHAR(500) NULL,
    status ENUM('draft','at_pending','at_signed','sent','viewed','signed','expired','cancelled') DEFAULT 'draft',
    sign_token CHAR(64) NOT NULL,
    at_review_token CHAR(64) NULL,
    document_hash CHAR(64) NULL,
    signed_document_hash CHAR(64) NULL,
    sent_at DATETIME NULL,
    viewed_at DATETIME NULL,
    signed_at DATETIME NULL,
    expires_at DATETIME NULL,
    -- Firma AT (representante AutomatizaTech)
    at_signer_user_id BIGINT UNSIGNED NULL,
    at_signer_name VARCHAR(160) NULL,
    at_signer_rut VARCHAR(20) NULL,
    at_signer_email VARCHAR(160) NULL,
    at_signer_ip VARCHAR(45) NULL,
    at_signed_at DATETIME NULL,
    at_signature_method ENUM('canvas','image_upload') NULL,
    at_signature_image_url VARCHAR(500) NULL,
    -- Firma cliente
    signer_name VARCHAR(160) NULL,
    signer_rut VARCHAR(20) NULL,
    signer_email VARCHAR(160) NULL,
    signer_ip VARCHAR(45) NULL,
    signer_user_agent VARCHAR(400) NULL,
    signature_method ENUM('canvas','image_upload','advanced') NULL,
    signature_image_url VARCHAR(500) NULL,
    starts_at DATE NULL,
    ends_at DATE NULL,
    monthly_amount DECIMAL(12,2) NULL,
    currency CHAR(3) DEFAULT 'CLP',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_token (sign_token),
    UNIQUE KEY uniq_number (contract_number),
    KEY idx_client (client_id, status),
    KEY idx_status (status, sent_at)
) {$charset};";

dbDelta($sql);

echo '<pre>OK Tabla contratos creada/actualizada: ' . esc_html($table);
echo "\nÚltimos errores: " . esc_html($wpdb->last_error ?: 'ninguno') . '</pre>';
