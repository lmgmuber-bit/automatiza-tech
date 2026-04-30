<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_propuestas';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE $table_name (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    client_email varchar(100) NOT NULL,
    unique_link_id varchar(50) NOT NULL,
    transcript_text longtext,
    gamma_prompt_text longtext,
    system_prompt_text longtext,
    gamma_iframe_url varchar(255),
    n8n_chat_url varchar(255),
    pdf_url varchar(255),
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY unique_link_id (unique_link_id)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

echo "Tabla $table_name creada o actualizada correctamente.";
?>