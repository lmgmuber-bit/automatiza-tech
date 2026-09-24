<?php
/**
 * Endpoint público de confirmación de asistencia.
 *
 * Se entra con el token opaco de la invitación (`t`): quien no tiene el
 * enlace no llega. Guarda apoderado/familia + niños invitados (cumpleaños)
 * o solo el nombre de la persona adulta (baby shower). La lista completa la
 * ve la familia en asistencia-papas.php con su token de rol.
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/lib.rsvp.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$salir = static function (int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    $salir(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 4096) {
    $salir(413, ['ok' => false, 'error' => 'payload_too_large']);
}
$input = json_decode($raw, true);
if (!is_array($input)) {
    $salir(400, ['ok' => false, 'error' => 'invalid_json']);
}

$token = (string) ($input['t'] ?? '');
if (!cb_invitation_public_token_is_valid($token)) {
    $salir(400, ['ok' => false, 'error' => 'token_invalido']);
}

try {
    $invitation = cb_load_invitation_by_public_token($token);
    // Las mismas condiciones que exige invitacion.php, repetidas acá porque
    // este endpoint se puede llamar directo sin pasar por la página.
    if (
        !$invitation
        || (string) $invitation['status'] !== 'published'
        || (!empty($invitation['expires_at']) && strtotime((string) $invitation['expires_at']) < time())
    ) {
        $salir(404, ['ok' => false, 'error' => 'no_encontrada']);
    }
    $partyId = (int) ($invitation['party_id'] ?? 0);
    if ($partyId <= 0) {
        $salir(404, ['ok' => false, 'error' => 'sin_evento']);
    }
    $resultado = cb_rsvp_save(
        $partyId,
        (string) ($input['family_name'] ?? ''),
        (string) ($input['guest_names'] ?? '')
    );
    if (!$resultado['ok']) {
        $salir(422, ['ok' => false, 'error' => $resultado['error']]);
    }
    $salir(200, ['ok' => true]);
} catch (Throwable $e) {
    error_log('rsvp-api: ' . $e->getMessage());
    $salir(500, ['ok' => false, 'error' => 'error_interno']);
}
