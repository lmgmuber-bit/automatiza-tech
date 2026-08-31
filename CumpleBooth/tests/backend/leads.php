<?php
/** Pruebas aisladas del formulario comercial; no dependen de assets temáticos. */
if (PHP_SAPI !== 'cli') { exit(2); }
$tmp = sys_get_temp_dir() . '/cumpleclick-leads-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $db = $tmp . '/leads.sqlite';
    if (is_file($db)) { @unlink($db); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=db');
putenv('CC_PDO_DSN=sqlite:' . $tmp . '/leads.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('b', 64));
require dirname(__DIR__, 2) . '/public/lib.php';

$tests = 0;
function lead_check(bool $condition, string $message): void {
    global $tests;
    $tests++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
}
(require dirname(__DIR__, 2) . '/database/migrations/001_initial.php')(cb_pdo());
(require dirname(__DIR__, 2) . '/database/migrations/006_public_leads.php')(cb_pdo());

$valid = [
    'nombre' => 'María González',
    'organizacion' => 'Colegio Demo',
    'email' => 'maria@example.test',
    'telefono' => '+56 9 1234 5678',
    'tipo' => 'Día del Niño',
    'fecha' => '2026-10-15',
    'comuna' => 'Santiago Centro',
    'mensaje' => 'Actividad para aproximadamente 80 asistentes.',
    'consentimiento' => true,
    'website' => '',
];
$validated = cb_validate_lead_input($valid);
lead_check($validated['ok'], 'acepta entrada válida');
lead_check(!$validated['errors'], 'entrada válida no genera errores');

$invalid = cb_validate_lead_input(array_merge($valid, [
    'email' => 'correo-malo', 'telefono' => '123', 'fecha' => '2026-02-31',
    'consentimiento' => false,
]));
lead_check(!$invalid['ok'], 'rechaza entrada inválida');
lead_check(isset($invalid['errors']['email'], $invalid['errors']['telefono'], $invalid['errors']['fecha'], $invalid['errors']['consentimiento']), 'reporta campos inválidos');

$bot = cb_validate_lead_input(array_merge($valid, ['website' => 'spam.test']));
lead_check(!$bot['ok'] && isset($bot['errors']['website']), 'honeypot bloquea bot');

$created = cb_create_lead($valid, '203.0.113.8', 'CumpleClickTest/1.0');
lead_check($created['ok'], 'crea lead');
lead_check(preg_match('/^CC-[A-F0-9]{12}$/', $created['reference']) === 1, 'referencia opaca válida');
$stmt = cb_pdo()->prepare('SELECT * FROM cc_leads WHERE public_ref=?');
$stmt->execute([$created['reference']]);
$stored = $stmt->fetch();
lead_check((bool) $stored && $stored['status'] === 'new', 'persiste con estado new');
lead_check($stored['ip_hmac'] === cb_hmac('203.0.113.8', 'lead-ip'), 'IP se guarda como HMAC');
lead_check(strpos(json_encode($stored), '203.0.113.8') === false, 'IP clara no se persiste');

$before = (int) cb_pdo()->query('SELECT COUNT(*) FROM cc_leads')->fetchColumn();
(require dirname(__DIR__, 2) . '/database/migrations/006_public_leads.php')(cb_pdo());
$after = (int) cb_pdo()->query('SELECT COUNT(*) FROM cc_leads')->fetchColumn();
lead_check($before === $after, 'migración 006 es idempotente y conserva datos');

/* ================= Correo de confirmación y aviso interno =================
 *
 * Lo que se cuida acá no es "que el correo se vea lindo", sino tres defectos
 * que no rompen nada y salen caros:
 *
 * 1. Que un fallo del SMTP tumbe el formulario. El lead ya está guardado
 *    cuando se intenta enviar; si `cb_lead_enviar_correos` lanzara, el
 *    visitante vería un error por algo que YA salió bien.
 * 2. Que el correo se vaya sin versión de texto plano. Es de las señales que
 *    más pesa en los filtros de spam, y no se nota mirando: en el escritorio
 *    se ve perfecto igual.
 * 3. Que la plantilla traiga una imagen remota. La mayoría de los clientes las
 *    bloquea, así que un encabezado que ES una imagen llega vacío justo en el
 *    primer correo que le mandamos a alguien.
 */
(require dirname(__DIR__, 2) . '/database/migrations/012_lead_mail_tracking.php')(cb_pdo());
require_once dirname(__DIR__, 2) . '/public/lib.mail.php';
require_once dirname(__DIR__, 2) . '/public/lib.mail-templates.php';

$cols = [];
foreach (cb_pdo()->query('PRAGMA table_info(cc_leads)')->fetchAll() as $row) { $cols[] = $row['name']; }
lead_check(in_array('confirmation_sent_at', $cols, true), 'migración 012 agrega confirmation_sent_at');
lead_check(in_array('mail_error', $cols, true), 'migración 012 agrega mail_error');

$antes012 = (int) cb_pdo()->query('SELECT COUNT(*) FROM cc_leads')->fetchColumn();
(require dirname(__DIR__, 2) . '/database/migrations/012_lead_mail_tracking.php')(cb_pdo());
lead_check($antes012 === (int) cb_pdo()->query('SELECT COUNT(*) FROM cc_leads')->fetchColumn(),
    'migración 012 es idempotente y conserva datos');

$leadCorreo = $created['lead'];
lead_check(is_array($leadCorreo) && ($leadCorreo['public_ref'] ?? '') !== '',
    'cb_create_lead devuelve los datos para armar el correo');

// Sin SMTP configurado NO puede lanzar: el lead ya está guardado.
$lanzo = false;
try { cb_lead_enviar_correos($leadCorreo); } catch (Throwable $e) { $lanzo = true; }
lead_check(!$lanzo, 'sin SMTP configurado, el envío no lanza excepción');

$stmt = cb_pdo()->prepare('SELECT confirmation_sent_at, mail_error FROM cc_leads WHERE public_ref = ?');
$stmt->execute([$leadCorreo['public_ref']]);
$fila = $stmt->fetch();
lead_check(($fila['confirmation_sent_at'] ?? null) === null, 'sin SMTP no se marca como enviado');
lead_check(trim((string) ($fila['mail_error'] ?? '')) !== '', 'sin SMTP queda registrado el motivo');

foreach ([
    'confirmación' => cc_mail_confirmacion($leadCorreo),
    'aviso interno' => cc_mail_aviso_interno($leadCorreo, 'https://cumpleclick.com/admin/leads.php'),
] as $nombre => $plantilla) {
    lead_check(trim($plantilla['subject']) !== '', "$nombre: tiene asunto");
    lead_check(trim($plantilla['html']) !== '', "$nombre: tiene HTML");
    // Texto plano de VERDAD, no un HTML sin etiquetas ni una cadena de relleno.
    lead_check(strlen(trim($plantilla['text'])) > 120, "$nombre: trae versión de texto plano");
    lead_check(strpos($plantilla['text'], '<') === false, "$nombre: la versión de texto no lleva HTML");
    /* Una imagen COMO MÁXIMO —el logo—, y el correo no puede depender de ella.
     *
     * La versión anterior exigía CERO imágenes. Era pasarse de prudente: lo que
     * penalizan los filtros no es una imagen, es un correo que ES una imagen y
     * no tiene texto que leer. Pero la cautela de fondo sí valía, así que se
     * conserva en tres condiciones concretas: una sola imagen, con `alt` —si el
     * cliente la bloquea se lee la marca en vez de quedar un hueco— y mucho más
     * texto que imagen. */
    $imgs = preg_match_all('/<img\b/i', $plantilla['html']);
    lead_check($imgs <= 1, "$nombre: más de una imagen; el correo no debe depender de imágenes");
    if ($imgs === 1) {
        lead_check(preg_match('/<img\b[^>]*\balt="[^"]+"/i', $plantilla['html']) === 1,
            "$nombre: la imagen necesita alt, o bloqueada deja un hueco");
        $soloTexto = trim(preg_replace('/\s+/', ' ', strip_tags($plantilla['html'])));
        lead_check(strlen($soloTexto) > 300, "$nombre: hay imagen pero muy poco texto alrededor");
    }
    /* El único recurso remoto permitido es el logo, y del propio dominio. */
    preg_match_all('/(?:src|background)\s*=\s*["\']([^"\']+)/i', $plantilla['html'], $recursos);
    foreach ($recursos[1] as $url) {
        if (strpos($url, 'http') !== 0) { continue; }
        lead_check(strpos($url, 'https://cumpleclick.com/') === 0,
            "$nombre: carga algo de otro servidor: $url");
    }
    lead_check(strpos($plantilla['html'], '<script') === false, "$nombre: no inyecta script");
}

$conf = cc_mail_confirmacion($leadCorreo);
lead_check(strpos($conf['subject'], (string) $leadCorreo['public_ref']) !== false,
    'el asunto al cliente lleva el número de solicitud');
lead_check(stripos($conf['text'], 'brevedad') !== false,
    'el correo al cliente promete contacto a la brevedad');
lead_check(in_array('Auto-Submitted: auto-replied', $conf['headers'] ?? [], true),
    'la confirmación se declara como respuesta automática');

$aviso = cc_mail_aviso_interno($leadCorreo, '');
lead_check(($aviso['reply_to'] ?? '') === $leadCorreo['email'],
    'responder el aviso interno escribe al cliente');

// Las cabeceras con acentos van codificadas, o llegan rotas en varios clientes.
lead_check(cc_mail_encode_header('Muñoz') !== 'Muñoz', 'las cabeceras con acentos se codifican');
lead_check(cc_mail_encode_header('Hola') === 'Hola', 'las cabeceras ASCII quedan legibles');

// Datos hostiles: no deben escaparse del cuerpo ni de un atributo.
$hostil = $leadCorreo;
$hostil['name'] = '<script>alert(1)</script>';
$hostil['message'] = '"><img src=x onerror=alert(1)>';
$sucio = cc_mail_aviso_interno($hostil, '');
lead_check(strpos($sucio['html'], '<script>alert') === false, 'el nombre se escapa en el HTML');
lead_check(stripos($sucio['html'], '<img src=x') === false, 'el mensaje se escapa en el HTML');

lead_check(cc_mail_enabled() === false, 'sin configuración, el envío se declara apagado');

fwrite(STDOUT, "OK $tests checks leads\n");
