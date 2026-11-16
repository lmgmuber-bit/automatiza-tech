<?php
// php tests/seguridad-errores/guardas-tema-test.php
// Cada plantilla/include del tema, ejecutado sin WordPress (sin ABSPATH), debe salir vacio.
require __DIR__ . '/harness.php';
$tema = 'wp-content/themes/automatiza-tech/';
$archivos = ['index', 'header', 'footer', 'customizer', 'contact-shortcode', 'services-admin', 'services-frontend',
    'template-functions',
    'inc/contact-form', 'inc/contact-shortcode', 'inc/customizer', 'inc/development-config', 'inc/template-functions'];
foreach ($archivos as $a) {
    $ruta = raiz() . '/' . $tema . $a . '.php';
    $existe = is_file($ruta);
    comprobar($existe, "$a.php existe");
    if (!$existe) { continue; }
    $p = proc_open([PHP_BINARY, '-d', 'display_errors=1', $ruta], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $salida = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    comprobar(trim($salida . $err) === '', "$a.php sale vacio sin ABSPATH", substr(trim($salida . $err), 0, 100));
    // la guarda es la primera instruccion
    $codigo = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($ruta));
    comprobar((bool) preg_match('/\A(<\?php\s+defined\(\'ABSPATH\'\) \|\| exit;\s*\?>|<\?php\r?\ndefined\(\'ABSPATH\'\) \|\| exit;\r?\n)/', $codigo), "$a.php: guarda como primera instruccion");
}
// services-frontend-backup.php y services-frontend-broken.php (copias muertas) ya no compilan en main
// (funciones declaradas dos veces); llevan la guarda pero no se pueden ejecutar para probarla.
// at-texto.php tambien tiene guarda (ya cubierto en helper-texto-test)
cierre();
