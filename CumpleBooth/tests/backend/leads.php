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

fwrite(STDOUT, "OK $tests checks leads\n");
