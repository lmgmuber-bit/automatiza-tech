<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Setup Google Drive para MAXTECH
 * Ejecutar una vez para crear la estructura necesaria
 * URL: https://automatizatech.cl/setup-google-drive.php?key=AutomatizaTech2026
 */

// Verificar key de seguridad
if (!isset($_GET['key']) || $_GET['key'] !== 'AutomatizaTech2026') {
    die('Acceso denegado. Usa ?key=AutomatizaTech2026');
}

require_once __DIR__ . '/wp-load.php';

// Verificar que el usuario esté logueado como admin
if (!current_user_can('manage_options')) {
    die('Debes estar logueado como administrador');
}

echo "<h1>🔧 Setup Google Drive para MAXTECH</h1>";
echo "<pre>";

// 1. Crear carpeta private
$upload_dir = wp_upload_dir();
$private_dir = $upload_dir['basedir'] . '/private';

if (!file_exists($private_dir)) {
    if (wp_mkdir_p($private_dir)) {
        echo "✅ Carpeta 'private' creada: {$private_dir}\n";
    } else {
        echo "❌ Error al crear carpeta 'private'\n";
    }
} else {
    echo "📁 Carpeta 'private' ya existe: {$private_dir}\n";
}

// 2. Crear .htaccess para proteger la carpeta
$htaccess_path = $private_dir . '/.htaccess';
$htaccess_content = "# Denegar acceso directo a archivos\nOrder deny,allow\nDeny from all";

if (!file_exists($htaccess_path)) {
    if (file_put_contents($htaccess_path, $htaccess_content)) {
        echo "✅ .htaccess de protección creado\n";
    } else {
        echo "❌ Error al crear .htaccess\n";
    }
} else {
    echo "🔒 .htaccess ya existe\n";
}

// 3. Crear index.php vacío por seguridad
$index_path = $private_dir . '/index.php';
if (!file_exists($index_path)) {
    file_put_contents($index_path, '<?php // Silence is golden');
    echo "✅ index.php de seguridad creado\n";
}

// 4. Verificar si existe el archivo de credenciales
$credentials_path = $private_dir . '/google-service-account.json';
if (file_exists($credentials_path)) {
    $creds = json_decode(file_get_contents($credentials_path), true);
    if (isset($creds['client_email'])) {
        echo "\n✅ Credenciales de Google encontradas!\n";
        echo "📧 Service Account Email: {$creds['client_email']}\n";
        echo "\n⚠️ IMPORTANTE: Comparte las carpetas de Google Drive con este email.\n";
    } else {
        echo "❌ El archivo de credenciales existe pero parece inválido\n";
    }
} else {
    echo "\n⚠️ Archivo de credenciales NO encontrado.\n";
    echo "📍 Debes subir el archivo JSON de Service Account a:\n";
    echo "   {$credentials_path}\n";
}

echo "\n</pre>";

echo "<h2>📋 Pasos para configurar Google Drive:</h2>";
echo "<ol style='font-size: 16px; line-height: 2;'>";
echo "<li>Ve a <a href='https://console.cloud.google.com' target='_blank'>Google Cloud Console</a></li>";
echo "<li>Crea un proyecto nuevo (o usa uno existente)</li>";
echo "<li>Busca y habilita <strong>'Google Drive API'</strong> en la Biblioteca de APIs</li>";
echo "<li>Ve a <strong>Credenciales → Crear credenciales → Cuenta de servicio</strong></li>";
echo "<li>Crea la cuenta (nombre sugerido: 'maxtech-drive')</li>";
echo "<li>En la cuenta creada, ve a <strong>Claves → Agregar clave → Crear clave nueva → JSON</strong></li>";
echo "<li>Descarga el archivo JSON</li>";
echo "<li>Sube el archivo a: <code>{$credentials_path}</code></li>";
echo "<li>En Google Drive, comparte las carpetas de clientes con el email de la Service Account</li>";
echo "<li>Ve a <a href='" . admin_url('admin.php?page=maxtech-google-drive') . "'>CRM → Google Drive</a> para verificar la conexión</li>";
echo "</ol>";

echo "<h2>🔗 Cómo vincular carpetas de Drive a clientes:</h2>";
echo "<p>En la ficha del cliente, puedes guardar el ID de carpeta de Google Drive.</p>";
echo "<p>El ID está en la URL de la carpeta: <code>https://drive.google.com/drive/folders/<strong>ID_AQUI</strong></code></p>";

echo "<hr>";
echo "<p><a href='" . admin_url('admin.php?page=maxtech-google-drive') . "' class='button button-primary' style='padding: 10px 20px; font-size: 16px;'>Ir a configuración de Google Drive →</a></p>";
