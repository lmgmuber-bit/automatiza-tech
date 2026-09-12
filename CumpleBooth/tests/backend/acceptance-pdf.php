<?php
/**
 * El comprobante de Términos firmados como PDF, generado desde una fila armada a mano y una
 * firma PNG de verdad (dibujada con GD en un archivo temporal).
 *
 * Lo que hay que vigilar: que el texto legal solo entre cuando es EXACTAMENTE el que se
 * firmó. Si alguien edita los documentos y sube la versión, un comprobante viejo no puede
 * salir con el texto nuevo como si el firmante lo hubiera visto.
 */

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/public/lib.php';
require_once $raiz . '/public/lib.acceptance.php';

$fallos = 0;
$total = 0;
function ok(string $que, bool $cond): void
{
    global $fallos, $total;
    $total++;
    if (!$cond) { $fallos++; echo "  FALLA: $que\n"; }
}
function pdf_texto(string $pdf): string
{
    preg_match_all('#stream\r?\n(.*?)\r?\nendstream#s', $pdf, $m);
    $t = '';
    foreach ($m[1] as $s) { $u = @gzuncompress($s); if ($u === false) { $u = @gzinflate($s); } if ($u !== false) { $t .= $u; } }
    return $t;
}

echo "Comprobante de Términos en PDF\n";

// ---------- Una firma PNG real, con fondo transparente como la que dibuja el navegador ----------
// Va al directorio privado de aceptaciones con un nombre válido, porque
// cb_acceptance_file_path() solo resuelve claves con la forma acc-NNNNNN-xxx.png: así la
// prueba ejercita el mismo camino que una firma de verdad.
$claveFirma = 'acc-000042-prueba-firma.png';
$dirFirmas = cb_acceptance_dir();
@mkdir($dirFirmas, 0700, true);
$png = imagecreatetruecolor(400, 160);
imagesavealpha($png, true);
imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
$tinta = imagecolorallocate($png, 20, 20, 80);
for ($x = 20; $x < 380; $x += 2) { imagesetpixel($png, $x, (int) (80 + 40 * sin($x / 30)), $tinta); imagesetpixel($png, $x, (int) (81 + 40 * sin($x / 30)), $tinta); }
$rutaFirma = $dirFirmas . DIRECTORY_SEPARATOR . $claveFirma;
imagepng($png, $rutaFirma);
$shaFirma = hash_file('sha256', $rutaFirma);

$bundle = cb_legal_bundle();

$fila = [
    'id' => 42, 'party_public_slug' => 'prueba-hielo', 'party_admin_label' => 'Fiesta de prueba',
    'plan_summary' => ['plan_name' => 'Plan Premium', 'event_date' => '2026-09-13', 'event_time' => '16:30',
                       'event_address' => 'Salón, San Miguel', 'price_total' => '$49.995', 'deposit' => '$0'],
    'client_name' => 'Carolina Pérez', 'client_email' => 'carolina@ejemplo.cl', 'client_phone' => '+56 9 1234 5678',
    'signer_name' => 'Carolina Pérez', 'signer_rut' => '12.345.678-9', 'signer_email' => 'carolina@ejemplo.cl',
    'signer_relationship' => 'madre',
    'accepted_terms' => 1, 'accepted_privacy' => 1, 'accepted_minors' => 1, 'accepted_marketing' => 0,
    'signature_storage_key' => $claveFirma, 'signature_sha256' => $shaFirma,
    'evidence_sha256' => str_repeat('ab', 32),
    'ip_address' => '190.1.2.3', 'user_agent' => 'Prueba/1.0',
    'legal_version' => $bundle['version'], 'legal_text_sha256' => $bundle['sha256'],
    'created_at' => '2026-09-07 20:00:00', 'created_by' => 'admin', 'first_viewed_at' => '2026-09-07 20:05:00',
    'accepted_at' => '2026-09-07 20:10:00',
    'client_meta' => ['pantalla' => '1280x800'],
];

$pdf = cb_acceptance_pdf($fila);
$texto = pdf_texto($pdf);
ok('es un PDF', str_starts_with($pdf, '%PDF'));
$paginas = preg_match_all('#/Type\s*/Page[^s]#', $pdf);
ok("con el texto legal completo ocupa varias hojas ($paginas)", $paginas >= 4);
ok('lleva las seis secciones', str_contains($texto, '1. Resumen del Plan') && str_contains($texto, '6. Texto') );
ok('el resumen usa las etiquetas del admin', str_contains($texto, 'Plan contratado') && str_contains($texto, 'Hora de inicio'));
ok('la relación se traduce', str_contains($texto, 'Madre'));
ok('las casillas marcadas y la de marketing sin marcar', str_contains($texto, '[X] T') && str_contains($texto, '[ ] Uso promocional'));
ok('la huella del texto aceptado va completa (64 hex)', str_contains($texto, $bundle['sha256']));

// Una huella cortada por la mitad no sirve para verificar nada, y si cabe o no en el renglon
// depende de que digitos le tocaron: en Helvetica una `f` mide 278 y un `0` 556, asi que dos
// huellas del mismo largo ocupan anchos muy distintos. Se prueba con la peor posible —solo
// digitos anchos— que es la que reventaba el renglon antes de que la tabla achicara el valor.
$anchaFila = $fila;
$anchaFila['evidence_sha256'] = str_repeat('0', 64);
$peorTexto = pdf_texto(cb_acceptance_pdf($anchaFila));
ok('la peor huella posible (64 digitos anchos) tampoco se parte', str_contains($peorTexto, str_repeat('0', 64)));
ok('la huella de la evidencia HTML va en el documento', str_contains($texto, str_repeat('ab', 32)));
ok('incluye el título del primer documento legal', str_contains($texto, 'rminos y Condiciones del Servicio'));
ok('la fila de meta del cliente aparece', str_contains($texto, 'pantalla') && str_contains($texto, '1280x800'));
// El texto legal también dice "página" (web, siguiente...): se cuenta solo "Página N".
ok('cada página está numerada', preg_match_all('#gina \d+#', $texto) === $paginas);

// La firma se resolvió por su clave, se aplanó sobre blanco y va incrustada como JPEG.
ok('la clave de la firma resuelve al archivo', cb_acceptance_file_path($claveFirma) === $rutaFirma);
ok('la firma PNG se aplanó y se incrustó como JPEG', str_contains($pdf, '/DCTDecode'));
ok('no dice que falte la imagen', !str_contains($texto, 'disponible en esta copia'));

// ---------- Si los documentos cambiaron después de firmar, el texto NO se incluye ----------
$vieja = $fila;
$vieja['legal_text_sha256'] = str_repeat('00', 32);
$vieja['legal_version'] = '2020-01-01';
$pdf2 = cb_acceptance_pdf($vieja);
$texto2 = pdf_texto($pdf2);
ok('con huella distinta avisa que el texto vigente no es el aceptado', str_contains($texto2, 'no son los mismos que se aceptaron'));
ok('y no imprime el texto legal de hoy', !str_contains($texto2, 'rminos y Condiciones del Servicio'));
ok('el PDF sin texto legal queda corto', preg_match_all('#/Type\s*/Page[^s]#', $pdf2) < $paginas);

// ---------- Firma perdida o alterada ----------
$sinFirma = $fila;
$sinFirma['signature_sha256'] = str_repeat('ff', 32);   // la imagen existe pero no es la registrada
$pdf3 = cb_acceptance_pdf($sinFirma);
ok('una firma cuyo hash no coincide no se dibuja y se avisa', str_contains(pdf_texto($pdf3), 'disponible en esta copia'));

// ---------- Limpieza: solo lo que esta prueba creó ----------
@unlink($rutaFirma);

echo $fallos === 0
    ? "  $total comprobaciones, todas bien\n"
    : "  $total comprobaciones, $fallos con problemas\n";
exit($fallos === 0 ? 0 : 1);
