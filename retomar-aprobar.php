<?php
// Botones del correo semanal de Retomar (aprobar, pedir cambios, omitir, ver). GET y HEAD solo muestran; ejecuta el POST.
// La lógica se prueba sin servidor en inc/retomar/aprobacion.php.
require_once __DIR__ . '/wp-load.php';
if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
nocache_headers();
if (!function_exists('at_rt_pagina_aprobacion')) {
    status_header(503);
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
    exit('Aprobación no disponible por ahora.');
}
$res = at_rt_pagina_aprobacion(wp_unslash($_GET), (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), wp_unslash($_POST));
status_header($res['http']);
foreach ($res['cabeceras'] as $nombre => $valor) { header($nombre . ': ' . $valor); }
echo $res['html'];
