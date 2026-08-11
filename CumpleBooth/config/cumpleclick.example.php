<?php
/**
 * Copiar como cumpleclick.local.php mediante scripts/bootstrap.php.
 * Nunca versionar el archivo local ni reemplazar estos marcadores por secretos reales.
 */
return [
    'storage_mode' => 'db', // db | json
    'pdo_dsn' => 'mysql:host=127.0.0.1;dbname=cumpleclick;charset=utf8mb4',
    'pdo_user' => 'cumpleclick',
    'pdo_password' => '',
    'admin_password_hash' => '',
    'app_hmac_key' => '',
    'public_base_url' => 'https://automatizatech.cl/cumpleclick',
    // Deben ser rutas absolutas fuera de public_html/DocumentRoot.
    'photo_dir' => '/home/ACCOUNT/private/cumpleclick/photos',
    'state_dir' => '/home/ACCOUNT/private/cumpleclick/state',
    'invitation_dir' => '/home/ACCOUNT/private/cumpleclick/invitations',
    'event_profile_dir' => '/home/ACCOUNT/private/cumpleclick/event-profiles',
    // Rollback inmediato: habilitar solo después de migrar y validar.
    'event_profile_enabled' => false,
    'retention_days' => 30,
    'session_idle_seconds' => 7200,
    'session_absolute_seconds' => 43200,
];
