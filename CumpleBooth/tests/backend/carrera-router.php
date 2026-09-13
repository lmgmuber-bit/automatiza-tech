<?php
/** Router de QA local: jamás forma parte del paquete público. */
if (PHP_SAPI !== 'cli-server' || !getenv('CARRERA_TEST_DB')) { exit(2); }
$root=dirname(__DIR__,2);$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
if (strpos($path,'..')!==false) { http_response_code(400);exit; }
// El menú legado no declara favicon; la fixture no debe inventar un error del juego.
if ($path==='/favicon.ico') { http_response_code(204);return true; }
if (in_array($path,['/sala.php','/puntajes.php','/api.php'],true)) {
    require __DIR__.'/carrera-fixture.php';
    require $root.'/public'.$path;return true;
}
if ($path==='/juego/' || $path==='/juego/index.html') {
    $menu=getenv('CARRERA_TEST_MENU');
    if ($menu && is_file($menu)) {
        header('Content-Type: text/html; charset=utf-8');
        $html=file_get_contents($menu);
        if (!preg_match('/[\"\']circuito[\"\']\s*:/',$html)) {
            $html=str_replace('var RUTAS = {',"var RUTAS = {\n    'circuito': 'circuito/',",$html);
        }
        echo $html;return true;
    }
}
if (str_ends_with($path,'/')) { $path.='index.html'; }
$file=realpath($root.'/public'.$path);$public=realpath($root.'/public');
if (!$file||strpos($file,$public.DIRECTORY_SEPARATOR)!==0||!is_file($file)||str_ends_with($file,'.php')) {http_response_code(404);echo 'No encontrado';return true;}
$types=['html'=>'text/html; charset=utf-8','mjs'=>'text/javascript','js'=>'text/javascript','css'=>'text/css','json'=>'application/json','jpg'=>'image/jpeg','png'=>'image/png','mp3'=>'audio/mpeg','woff2'=>'font/woff2'];
header('Content-Type: '.($types[pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream'));
header('Cache-Control: public, max-age=60');readfile($file);return true;
