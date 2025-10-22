<?php 
error_reporting(E_ALL); 
ini_set('display_errors', 1); 
 
echo '<h1>🔍 Test Específico WordPress</h1>'; 
echo '<style>body{font-family:Arial;margin:20px;background:#f8f9fa;} .alert{padding:15px;margin:10px 0;border-radius:5px;} .success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;} .error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;} .info{background:#cce5ff;border:1px solid #b3d7ff;color:#004085;}</style>'; 
 
// Test 1: Extensiones PHP 
echo '<div class="alert info"><h2>1. Verificando Extensiones PHP</h2>'; 
$extensions = ['mysqli', 'mysql', 'pdo_mysql']; 
foreach ($extensions as $ext) { 
    $loaded = extension_loaded($ext); 
    $icon = $loaded ? '✅' : '❌'; 
    echo "^<p^>$icon $ext: " . ($loaded ? 'Disponible' : 'NO disponible') . "^</p^>"; 
} 
echo '</div>'; 
 
// Test 2: Conexión mysqli (que usa WordPress) 
echo '<div class="alert info"><h2>2. Test mysqli (WordPress usa esto)</h2>'; 
if (extension_loaded('mysqli')) { 
    $connection = @mysqli_connect('localhost', 'root', '', 'automatiza_tech'); 
    if ($connection) { 
        echo '<div class="alert success">✅ mysqli funciona perfectamente</div>'; 
        mysqli_close($connection); 
    } else { 
        echo '<div class="alert error">❌ mysqli falla: ' . mysqli_connect_error() . '</div>'; 
    } 
} else { 
    echo '<div class="alert error">❌ mysqli no está disponible - ESTE ES EL PROBLEMA</div>'; 
} 
echo '</div>'; 
 
// Test 3: Conexión PDO (que usa phpMyAdmin) 
echo '<div class="alert info"><h2>3. Test PDO (phpMyAdmin usa esto)</h2>'; 
try { 
    $pdo = new PDO('mysql:host=localhost;dbname=automatiza_tech', 'root', ''); 
    echo '<div class="alert success">✅ PDO funciona (por eso phpMyAdmin funciona)</div>'; 
} catch (Exception $e) { 
    echo '<div class="alert error">❌ PDO falla: ' . $e->getMessage() . '</div>'; 
} 
echo '</div>'; 
 
// Test 4: Simulando wp-config.php 
echo '<div class="alert info"><h2>4. Simulando WordPress</h2>'; 
define('DB_NAME', 'automatiza_tech'); 
define('DB_USER', 'root'); 
define('DB_PASSWORD', ''); 
define('DB_HOST', 'localhost'); 
 
if (extension_loaded('mysqli')) { 
    $wp_connection = @mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME); 
    if ($wp_connection) { 
        echo '<div class="alert success">'; 
        echo '<h3>🎉 ¡SOLUCION ENCONTRADA!</h3>'; 
        echo '<p>WordPress DEBERIA funcionar ahora. El problema era la configuración.</p>'; 
        echo '<p><a href="." style="background:#28a745;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;">🚀 Probar Sitio Web</a></p>'; 
        echo '</div>'; 
        mysqli_close($wp_connection); 
    } else { 
        echo '<div class="alert error">'; 
        echo '<h3>❌ Problema específico encontrado:</h3>'; 
        echo '<p><strong>Error:</strong> ' . mysqli_connect_error() . '</p>'; 
        echo '<p><strong>Posibles causas:</strong></p>'; 
        echo '<ul>'; 
        echo '<li>Base de datos automatiza_tech no existe</li>'; 
        echo '<li>Usuario root no tiene permisos para esta base</li>'; 
        echo '<li>Contraseña incorrecta</li>'; 
        echo '</ul>'; 
        echo '</div>'; 
    } 
} else { 
    echo '<div class="alert error"><h3>❌ PROBLEMA ENCONTRADO: mysqli no disponible</h3><p>WordPress necesita la extensión mysqli de PHP.</p></div>'; 
} 
echo '</div>'; 
 
echo '<div class="alert info">'; 
echo '<h3>🔗 Enlaces de Verificación</h3>'; 
echo '<p><a href="http://localhost/phpmyadmin" target="_blank">📊 phpMyAdmin (funciona)</a></p>'; 
echo '<p><a href="test-extensiones-php.php" target="_blank">🔧 Ver phpinfo() completo</a></p>'; 
echo '</div>'; 
?> 
