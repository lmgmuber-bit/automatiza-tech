<?php
/**
 * Prueba el manual de la fiesta sin base de datos: los datos se arman a mano y se pasan a
 * las mismas funciones que usan la página, el PDF y el correo.
 *
 * Lo que más importa es que el PDF de verdad cambie de hoja. El motor de la boleta no sabía
 * hacerlo, y un manual que dibuja la sección "Preguntas frecuentes" fuera del papel se ve
 * bien en la prueba y llega cortado al papá.
 */

$raiz = dirname(__DIR__, 2);
putenv('CC_AJUSTES_PATH=' . sys_get_temp_dir() . '/cc-ajustes-prueba-' . getmypid() . '.json');
require_once $raiz . '/public/lib.php';
require_once $raiz . '/public/lib.manual.php';

$fallos = 0;
$total = 0;
function ok(string $que, bool $cond): void
{
    global $fallos, $total;
    $total++;
    if (!$cond) { $fallos++; echo "  FALLA: $que\n"; }
}

echo "Manual de la fiesta\n";

// ---------- Datos armados a mano, como los devolvería cb_manual_datos() ----------
$base = 'https://cumpleclick.com/app';
$d = [
    'slug' => 'prueba-hielo', 'nombre' => 'Samantha', 'edad' => 5,
    'fecha' => '2026-09-13', 'fecha_larga' => cb_manual_fecha_larga('2026-09-13'),
    'hora' => '16:30', 'lugar' => 'Salón de eventos, San Miguel',
    'plan' => 'Plan Premium', 'duracion' => '2 horas',
    'tema' => 'hielo', 'tema_nombre' => 'Reino de Hielo',
    'personajes' => ['Personaje A', 'Personaje B'],
    'banner' => null,
    'contacto' => ['nombre' => 'Carolina Pérez', 'email' => 'carolina@ejemplo.cl'],
    'invitados' => 6,
    'juegos' => ['Reino de Hielo en 3D', 'El Festival de las Estrellas'],
    'pin' => '1234', 'anticipacion_min' => 30, 'dias_lista' => 3,
    'enlaces' => [
        'galeria' => "$base/galeria.php?p=prueba-hielo",
        'album' => "$base/album.html?p=prueba-hielo",
        'juegos' => "$base/juego/?p=prueba-hielo",
        'invitacion' => "$base/samantha-abc123",
        'manual' => "$base/manual.php?p=prueba-hielo&f=x",
        'manual_pdf' => "$base/manual.php?p=prueba-hielo&f=x&pdf=1",
    ],
    'whatsapp' => '+56 9 7494 0070', 'correo' => 'contacto@cumpleclick.com',
    'instagram' => '@Cumple_Click', 'instagram_url' => 'https://instagram.com/Cumple_Click',
];

// ---------- La fecha larga ----------
ok('la fecha sale en español y con día de la semana', $d['fecha_larga'] === 'domingo 13 de septiembre de 2026');
ok('una fecha rara no revienta', cb_manual_fecha_larga('sin fecha') === 'sin fecha');

// ---------- Las secciones ----------
$secciones = cb_manual_secciones($d);
$titulos = array_column($secciones, 'titulo');
ok('tiene las nueve secciones', count($secciones) === 9);
ok('empieza por los datos de la fiesta', $titulos[0] === 'Los datos de tu fiesta');
ok('termina en las preguntas frecuentes', end($titulos) === 'Preguntas frecuentes');
$todo = json_encode($secciones, JSON_UNESCAPED_UNICODE);
ok('usa los minutos de anticipación de ajustes', str_contains($todo, 'Llegamos 30 minutos antes'));
ok('usa los días de plazo de ajustes', str_contains($todo, 'hasta 3 días antes'));
ok('nombra los dos juegos de la temática', str_contains($todo, 'Reino de Hielo en 3D y El Festival de las Estrellas'));
ok('habla del menú y de las posiciones (lo nuevo)', str_contains($todo, 'tabla de Posiciones') && str_contains($todo, 'menú de juegos'));
ok('habla del Álbum Recuerdo (faltaba en la versión del sábado)', str_contains($todo, 'Álbum Recuerdo'));
ok('lleva el PIN de la galería', str_contains($todo, 'PIN 1234'));
ok('la invitación aparece cuando se entregó el enlace', str_contains($todo, 'samantha-abc123'));
ok('los personajes salen de los datos, no del código', str_contains($todo, 'Personaje A, Personaje B'));

// Sin cifras en ajustes, el manual no inventa ninguna.
$sin = $d; $sin['anticipacion_min'] = 0; $sin['dias_lista'] = 0; $sin['enlaces']['invitacion'] = '';
$todoSin = json_encode(cb_manual_secciones($sin), JSON_UNESCAPED_UNICODE);
ok('sin minutos cargados dice "con anticipación", sin número', str_contains($todoSin, 'con anticipación') && !str_contains($todoSin, 'minutos antes de la hora'));
ok('sin días cargados dice "antes de la fiesta"', str_contains($todoSin, ', antes de la fiesta'));
ok('sin enlace de invitación no aparece la fila', !str_contains($todoSin, 'Invitación digital'));

// ---------- La página ----------
$html = cb_manual_html($d);
ok('la página lleva el nombre y la edad', str_contains($html, 'La fiesta de Samantha · 5 años'));
ok('la página no se indexa', str_contains($html, 'noindex'));
ok('los enlaces son clicables', str_contains($html, '<a href="' . $base . '/galeria.php?p=prueba-hielo">'));
ok('el botón de PDF apunta al mismo enlace firmado', str_contains($html, 'pdf=1'));
ok('nada sin escapar: el nombre con tilde y el < no rompen', str_contains($html, 'Carolina Pérez') && !str_contains($html, '<script'));

// ---------- El PDF ----------
$pdf = cb_manual_pdf($d);
ok('el PDF empieza como PDF', str_starts_with($pdf, '%PDF'));
$paginas = preg_match_all('#/Type\s*/Page[^s]#', $pdf);
ok("el PDF cambió de hoja solo: tiene $paginas páginas (esperaba 2 o más)", $paginas >= 2);

// El contenido va comprimido (FlateDecode): para leer lo que se dibujó hay que
// descomprimir cada stream. Buscar texto en el archivo crudo no encuentra nada.
function cb_prueba_pdf_texto(string $pdf): string
{
    preg_match_all('#stream\r?\n(.*?)\r?\nendstream#s', $pdf, $m);
    $texto = '';
    foreach ($m[1] as $s) {
        $u = @gzuncompress($s);
        if ($u === false) { $u = @gzinflate($s); }
        if ($u !== false) { $texto .= $u; }
    }
    return $texto;
}
$dibujado = cb_prueba_pdf_texto($pdf);
ok('el contenido descomprimido pesa lo que un documento real (más de 8 kB)', strlen($dibujado) > 8000);
ok('cada página lleva su número al pie', substr_count($dibujado, 'gina ') === $paginas);
ok('la última sección de verdad se dibujó (no quedó fuera del papel)', str_contains($dibujado, 'Preguntas frecuentes'));

// Con banner ausente sigue saliendo; con un JPEG real, lo usa.
$conBanner = $d; $conBanner['banner'] = $raiz . '/public/themes/hielo/correo-cabecera.jpg';
if (is_file($conBanner['banner'])) {
    $pdf2 = cb_manual_pdf($conBanner);
    ok('con cabecera de temática el PDF incluye la imagen', strlen($pdf2) > strlen($pdf) + 20000 && str_contains($pdf2, '/DCTDecode'));
}

// Un nombre larguísimo sin espacios (una URL) no se sale del margen: se parte por letras.
$largo = $d; $largo['enlaces']['manual'] = $base . '/manual.php?p=' . str_repeat('x', 180);
$pdf3 = cb_manual_pdf($largo);
ok('una URL más larga que el renglón no revienta el PDF', str_starts_with($pdf3, '%PDF'));

// ---------- Instagram y los enlaces tocables del PDF ----------
// Un enlace en un PDF no es el texto pintado sino una anotacion aparte; que la URL aparezca
// en el archivo no prueba nada, porque tambien esta como texto. Lo que se comprueba aca es
// que exista la anotacion: /Annots con /Subtype /Link y la URL en /URI.
$anots = preg_match_all('#/Subtype\s*/Link#', $pdf);
ok('el PDF trae anotaciones de enlace', $anots > 0 && str_contains($pdf, '/Annots'));
ok('Instagram queda como enlace tocable en el PDF',
    str_contains($pdf, '/URI (' . $d['instagram_url'] . ')'));
// El dibujo va comprimido: para leer lo que dice la hoja hay que inflar los flujos. Buscar
// en los bytes crudos daria falsos positivos, porque la URL tambien esta en la anotacion.
$textoPdf = static function (string $pdf): string {
    $todo = '';
    preg_match_all('#stream\r?\n(.*?)\r?\nendstream#s', $pdf, $m);
    foreach ($m[1] as $flujo) {
        $x = @gzuncompress($flujo);
        if ($x !== false) { $todo .= $x; }
    }
    return $todo;
};
$hoja = $textoPdf($pdf);
ok('la cuenta de Instagram se imprime al pie', str_contains($hoja, '@Cumple_Click'));
ok('y el pie del PDF dibuja el glifo, no solo el texto', substr_count($hoja, ' c') >= 12);
ok('las URL de la fiesta tambien son tocables',
    str_contains($pdf, '/URI (' . $d['enlaces']['galeria'] . ')'));
ok('hay una anotacion por cada enlace escrito, no una sola', $anots >= 4);

$htmlPie = cb_manual_html($d);
ok('la pagina enlaza Instagram con su icono',
    str_contains($htmlPie, 'href="' . $d['instagram_url'] . '"')
    && str_contains($htmlPie, '<svg viewBox="0 0 24 24"'));

// La cuenta real sale de marca.json, no del fixture: si Luis la cambia en Admin -> Marca,
// el manual tiene que seguirla, y el enlace tiene que apuntar a esa misma cuenta.
$marca = cb_manual_marca();
ok('la cuenta de Instagram sale de marca.json', ($marca['instagram'] ?? '') !== '');
ok('su enlace apunta a esa misma cuenta en Instagram',
    preg_match('#^https://(www\.)?instagram\.com/#i', (string) $marca['instagram_url']) === 1
    && stripos($marca['instagram_url'], ltrim((string) $marca['instagram'], '@')) !== false);

// ---------- El correo ----------
$correo = cb_manual_correo($d);
ok('el pie del correo lleva Instagram con su logo y enlazado',
    str_contains($correo['html'], 'assets/img/instagram.png')
    && str_contains($correo['html'], 'href="' . $d['instagram_url'] . '"'));
ok('el asunto nombra al cumpleañero', $correo['subject'] === 'El manual de la fiesta de Samantha · CumpleClick');
ok('saluda por el nombre de pila del contacto', str_contains($correo['html'], 'Hola Carolina'));
ok('lleva el botón al manual', str_contains($correo['html'], 'Ver el manual'));
ok('el texto plano trae el enlace', str_contains($correo['text'], $d['enlaces']['manual']));
ok('el nombre del adjunto es seguro', $correo['filename'] === 'manual-fiesta-prueba-hielo.pdf');

// ---------- La firma del enlace ----------
$firma = cb_manual_firma('prueba-hielo');
ok('la firma tiene largo fijo', strlen($firma) === 24);
ok('la firma correcta valida', cb_manual_firma_valida('prueba-hielo', $firma));
ok('otra fiesta con la misma firma NO valida', !cb_manual_firma_valida('otra-fiesta', $firma));
ok('una firma vacía NO valida', !cb_manual_firma_valida('prueba-hielo', ''));
ok('la URL firmada lleva p y f', preg_match('#manual\.php\?p=prueba-hielo&f=[a-f0-9]{24}$#', cb_manual_url('prueba-hielo')) === 1);

// ---------- Los ajustes nuevos ----------
$r = cb_guardar_ajustes(['bcc_email' => '', 'recovery_email' => '', 'manual_anticipacion_min' => '30', 'manual_dias_lista' => '3']);
ok('ajustes guarda las dos cifras', !empty($r['ok']) && cb_ajustes()['manual_anticipacion_min'] === 30 && cb_ajustes()['manual_dias_lista'] === 3);
$r = cb_guardar_ajustes(['manual_anticipacion_min' => '', 'manual_dias_lista' => '']);
ok('vacío se guarda como cero', !empty($r['ok']) && cb_ajustes()['manual_anticipacion_min'] === 0);
$r = cb_guardar_ajustes(['manual_anticipacion_min' => '-5']);
ok('un negativo se rechaza con mensaje', empty($r['ok']) && str_contains(implode(' ', $r['errors']), 'entre 0 y'));
$r = cb_guardar_ajustes(['manual_dias_lista' => '999']);
ok('un plazo absurdo se rechaza', empty($r['ok']));
@unlink(getenv('CC_AJUSTES_PATH'));

echo $fallos === 0
    ? "  $total comprobaciones, todas bien\n"
    : "  $total comprobaciones, $fallos con problemas\n";
exit($fallos === 0 ? 0 : 1);
