<?php
/**
 * AT-CUMPLECLICK-026: textos operativos adultos y conservación exacta de infantil/baby.
 * Sólo funciones puras y fragmentos de las plantillas reales. No configura DB, no crea
 * archivos, no envía correos ni carga audios. --revision=ee02457 demuestra RED contra
 * el código anterior, leído por git show en memoria, sin cambiar el worktree.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$root = dirname(__DIR__, 2);
require_once $root . '/public/lib.manual.php';

// Golden calculados con el fixture sintético $data de abajo contra
// ee02457321400fa40b15a71a96237b110bcda01a, mediante git show sólo al prepararlos:
// SHA-256 de json_encode(cb_manual_secciones($data + ['event_type' => $tipo]),
// JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES). Infantil, baby y tipo ausente
// daban el mismo contenido exacto. Metadatos legales: mismo JSON de cb_legal_documents().
// Cuerpo legal: bytes del archivo con CRLF normalizado a LF antes del SHA-256.
// GREEN normal usa estos resultados fijados; no requiere Git ni historial de CI.
const TEXTO_GOLDEN_MANUAL = '28acfb52106b17eb0e467bbaed97f06a55cfd4af2eced855ca4b1cd1f067caef';
const TEXTO_GOLDEN_LEGAL_META = 'e80c348697b5d462e30a3d10ab0fe344bda034a95e2c8e6a127f159b79187ab1';
const TEXTO_GOLDEN_LEGAL_BODY_LF = 'e5e5b447ea54692efe8a307c97b08a8c68a31e32f1f5209017a473ec30f4629f';
$revision = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--revision=([a-f0-9]{7,40})$/', $arg, $m)) { $revision = $m[1]; }
    else { throw new InvalidArgumentException('Usa --revision=<SHA> o ningún argumento.'); }
}

function texto_source(string $path, ?string $revision = null): string
{
    global $root;
    if ($revision === null) { return (string) file_get_contents($root . '/' . $path); }
    $process = proc_open(['git', 'show', $revision . ':CumpleBooth/' . $path],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) { throw new RuntimeException('No se pudo leer la revisión de prueba.'); }
    fclose($pipes[0]);
    $source = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { throw new RuntimeException('git show falló: ' . $error); }
    return $source;
}

/** Extrae una función de nivel superior del código versionado, sólo para el contraste. */
function texto_function(string $source, string $name, string $alias): void
{
    if (preg_match('/^function ' . preg_quote($name, '/') . '\([^\n]*\r?\n\{.*?^\}/ms', $source, $m) !== 1) {
        throw new RuntimeException('No se encontró la función ' . $name);
    }
    eval(preg_replace('/^function ' . preg_quote($name, '/') . '\(/', 'function ' . $alias . '(', $m[0], 1));
}

function texto_fragment(string $source, string $pattern, array $variables): string
{
    if (preg_match($pattern, $source, $m) !== 1) { throw new RuntimeException('No se encontró el fragmento de plantilla.'); }
    return (static function (string $fragment, array $variables): string {
        extract($variables, EXTR_SKIP);
        ob_start();
        try { eval('?>' . $fragment); return (string) ob_get_contents(); }
        finally { ob_end_clean(); }
    })($m[0], $variables);
}

function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$checks = 0; $failures = 0;
function texto_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) { $failures++; echo 'FAIL: ' . $message . "\n"; }
}

$manual = 'cb_manual_secciones';
$validate = 'cb_validate_acceptance_submission';
if ($revision !== null) {
    texto_function(texto_source('public/lib.manual.php', $revision), 'cb_manual_secciones', 'texto_manual_revision');
    texto_function(texto_source('public/lib.acceptance.php', $revision), 'cb_validate_acceptance_submission', 'texto_validate_revision');
    $manual = 'texto_manual_revision';
    $validate = 'texto_validate_revision';
}
$data = [
    'nombre' => 'Persona de Prueba', 'edad' => 61, 'tema_nombre' => 'Tema de prueba',
    'anticipacion_min' => 30, 'dias_lista' => 3, 'personajes' => [], 'juegos' => ['Juego de prueba'],
    'fecha' => '', 'hora' => '', 'lugar' => '', 'plan' => '', 'contacto' => null,
    'invitados' => 3, 'whatsapp' => 'Contacto de prueba', 'correo' => 'qa@example.invalid', 'pin' => '0000',
    'enlaces' => ['galeria' => '/galeria', 'album' => '/album', 'juegos' => '/juegos', 'invitacion' => '', 'manual' => '/manual'],
];
$adult = $data + ['event_type' => 'adult_birthday'];
$adultText = json_encode($manual($adult), JSON_UNESCAPED_UNICODE);
texto_check(preg_match('/\b(?:niños?|niñas?|var[oó]n(?:es)?)\b/iu', $adultText) === 0, 'el manual adulto no trata a sus invitados como menores');
texto_check(str_contains($adultText, 'Cada invitado pasa por la cabina'), 'el paso de cabina adulto habla de invitados');
texto_check(str_contains($adultText, 'cada persona toca su nombre'), 'los juegos adultos hablan de personas');
texto_check(!str_contains($adultText, 'su diploma') && !str_contains($adultText, 'el personaje de'), 'el manual adulto describe la escena y el recuerdo sin prometer diploma');
foreach (['child_birthday', 'baby_shower', ''] as $type) {
    $case = $data + ['event_type' => $type];
    texto_check(hash('sha256', json_encode($manual($case), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === TEXTO_GOLDEN_MANUAL,
        'el manual conserva exactamente el contenido previo para ' . ($type ?: 'modalidad ausente'));
    $error = $validate([], $type)['errors']['signer_relationship'] ?? '';
    texto_check($error === 'Indica tu relación con el niño o niña homenajeado.', 'el error infantil/baby se conserva para ' . ($type ?: 'modalidad ausente'));
}
texto_check(($validate([], 'adult_birthday')['errors']['signer_relationship'] ?? '') === 'Indica tu relación con la persona homenajeada.', 'la validación adulta usa persona homenajeada');

$acceptanceSource = texto_source('public/aceptar-plan.php', $revision);
$invitationSource = texto_source('public/admin/invitations.php', $revision);
$profileSource = texto_source('public/admin/event-profile.php', $revision);
$adminSource = texto_source('public/admin/index.php', $revision);
foreach (['adult_birthday', 'child_birthday', 'baby_shower'] as $type) {
    $isAdult = $type === 'adult_birthday';
    $variables = ['esAdulto' => $isAdult, 'party' => ['event_type' => $type],
        'inv' => ['id' => 123, 'birthday_person_gender' => 'f']];
    $relationship = texto_fragment($acceptanceSource, '#<label for="signer_relationship">.*?</label>#s', $variables);
    texto_check(str_contains($relationship, $isAdult ? 'Relación con la persona homenajeada' : 'Relación con el niño o niña homenajeado'), 'etiqueta de relación para ' . $type);
    foreach (['#<select id="i-gender".*?</select>#s', '#<select id="inv-field-3-.*?</select>#s'] as $pattern) {
        $select = texto_fragment($invitationSource, $pattern, $variables);
        texto_check(str_contains($select, $isAdult ? 'Hombre (cumpleañero)' : 'Niño (cumpleañero)'), 'opción masculina en alta/edición para ' . $type);
        texto_check(str_contains($select, $isAdult ? 'Mujer (cumpleañera)' : 'Niña (cumpleañera)'), 'opción femenina en alta/edición para ' . $type);
        if ($isAdult) { texto_check(preg_match('/\b(?:niños?|niñas?)\b/iu', $select) === 0, 'selector adulto sin términos infantiles'); }
    }
    $help = texto_fragment($invitationSource, '#<p class="small muted">(?:<\?= \$esAdulto.*?\?>|Elige la narración de cierre de Alice.*?)</p>#s', $variables);
    texto_check(str_contains($help, $isAdult ? 'Identifica el género de la persona homenajeada' : 'Elige la narración de cierre de Alice'), 'la ayuda de invitación describe su modalidad para ' . $type);
    $heading = texto_fragment($profileSource, '#<h2>(?:<\?= cb_party_event_type.*?\?>|Privacidad de niños y familias)</h2>#s', $variables);
    texto_check(str_contains($heading, $isAdult ? 'Privacidad de la persona homenajeada y sus invitados' : 'Privacidad de niños y familias'), 'título de privacidad para ' . $type);
    $confirm = texto_fragment($adminSource, '#<\?= \$juegosOn \? \'data-confirm=.*?\?>#s',
        ['juegosOn' => true, 'p' => ['event_type' => $type]]);
    texto_check(str_contains($confirm, $isAdult ? 'Los invitados que entren' : 'Los niños que entren'), 'confirmación de apagar juegos para ' . $type);
    texto_check(texto_fragment($adminSource, '#<\?= \$juegosOn \? \'data-confirm=.*?\?>#s',
        ['juegosOn' => false, 'p' => ['event_type' => $type]]) === '', 'sin confirmación cuando los juegos ya están apagados para ' . $type);
    if (preg_match('#\$_SESSION\[\'envio_flash\'\] = \$prender\s*\?.*?: \'Juegos 3D apagados\..*?\';#s', $adminSource, $flashMatch) !== 1) {
        throw new RuntimeException('No se encontró el mensaje de activar juegos.');
    }
    $flashSource = '<?php ' . str_replace("\$_SESSION['envio_flash'] =", 'echo', $flashMatch[0]);
    $flash = texto_fragment($flashSource, '#<\?php.*#s', ['prender' => true,
        'slugJ' => 'qa-fiesta', 'partiesJ' => ['qa-fiesta' => ['event_type' => $type]]]);
    texto_check($flash === ($isAdult ? 'Juegos 3D prendidos: los invitados ya pueden entrar con el QR.' : 'Juegos 3D prendidos: los niños ya pueden entrar con el QR.'), 'mensaje de activar juegos para ' . $type);
}

// El consentimiento de menores queda pendiente de abogado: no se disfraza de adulto.
texto_check(hash('sha256', json_encode(cb_legal_documents(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === TEXTO_GOLDEN_LEGAL_META,
    'se conservan exactamente títulos, casillas y declaraciones legales');
texto_check(hash('sha256', str_replace("\r\n", "\n", texto_source('public/legal/consentimiento-imagen-menores.md'))) === TEXTO_GOLDEN_LEGAL_BODY_LF,
    'el documento legal de menores no se reescribe');

echo ($failures ? 'FAIL ' : 'OK ') . $checks . ' comprobaciones, ' . $failures . ' fallas'
    . ($revision !== null ? ' · revisión ' . $revision : ' · working tree') . "\n";
exit($failures ? 1 : 0);
