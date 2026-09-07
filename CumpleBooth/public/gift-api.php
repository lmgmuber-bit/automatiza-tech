<?php
/**
 * Endpoint público de la lista de regalos de una invitación de baby shower.
 *
 * Se entra con el mismo token opaco de la invitación (`t`), así que quien no
 * tiene el enlace no llega. Acciones: reservar, soltar y agregar uno propio.
 *
 * La identidad del invitado es un token de navegador que emite ESTE endpoint
 * en la primera reserva y el cliente guarda. Nunca sale de acá el nombre de
 * quien reservó: ver la cabecera de lib.gifts.php.
 */
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

/** Responde y corta. */
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
    // Las tres condiciones que ya exige invitacion.php, repetidas acá porque
    // este endpoint se puede llamar directo sin pasar por la página.
    if (
        !$invitation
        || (string) $invitation['status'] !== 'published'
        || (!empty($invitation['expires_at']) && strtotime((string) $invitation['expires_at']) < time())
    ) {
        $salir(404, ['ok' => false, 'error' => 'no_encontrada']);
    }
    if ((string) ($invitation['event_type'] ?? '') !== 'baby_shower') {
        // La lista es de baby shower. En cumpleaños no existe y no se inventa.
        $salir(404, ['ok' => false, 'error' => 'no_aplica']);
    }

    $invitationId = (int) $invitation['id'];
    $accion = (string) ($input['accion'] ?? '');
    $visitante = (string) ($input['visitante'] ?? '');

    // El enlace es público: sin esto, una sola persona vacía la lista o la
    // llena de basura. Se reutiliza el limitador que ya existe.
    $rate = cb_rate_limit('gift:' . $invitationId, cb_request_identity(), 30, 600, 600);
    if (!$rate['allowed']) {
        header('Retry-After: ' . max(1, (int) $rate['retry_after']));
        $salir(429, ['ok' => false, 'error' => 'rate_limited']);
    }

    if ($accion === 'listar') {
        // Solo lectura: sirve para que, al abrir la invitación, el navegador
        // que ya reservó algo vea cuáles son suyos. No cambia nada.
        $salir(200, ['ok' => true, 'lista' => cb_gift_list_public($invitationId, $visitante)]);
    }

    if ($accion === 'reservar') {
        // Primera reserva de este navegador: el token lo emite el servidor y
        // el cliente lo guarda. Así el invitado puede soltar lo suyo después
        // sin que exista una cuenta.
        if ($visitante === '') {
            $visitante = cb_opaque_token(16);
        }
        $res = cb_gift_claim($invitationId, (int) ($input['id'] ?? 0), (string) ($input['nombre'] ?? ''), $visitante);
        if (empty($res['ok'])) {
            $codigo = ($res['error'] ?? '') === 'ya_tomado' ? 409 : 422;
            $salir($codigo, ['ok' => false, 'error' => $res['error'], 'limite' => $res['limite'] ?? null]
                + ['lista' => cb_gift_list_public($invitationId, $visitante)]);
        }
        $salir(200, ['ok' => true, 'visitante' => $visitante, 'lista' => cb_gift_list_public($invitationId, $visitante)]);
    }

    if ($accion === 'soltar') {
        $res = cb_gift_release($invitationId, (int) ($input['id'] ?? 0), $visitante);
        if (empty($res['ok'])) {
            $salir(409, ['ok' => false, 'error' => $res['error'], 'lista' => cb_gift_list_public($invitationId, $visitante)]);
        }
        $salir(200, ['ok' => true, 'lista' => cb_gift_list_public($invitationId, $visitante)]);
    }

    if ($accion === 'agregar') {
        $res = cb_gift_add($invitationId, [
            'title' => $input['titulo'] ?? '',
            'notes' => $input['nota'] ?? '',
        ], 'guest');
        if (empty($res['ok'])) {
            $salir(422, ['ok' => false, 'error' => $res['error']]);
        }
        $salir(201, ['ok' => true, 'id' => $res['id'], 'lista' => cb_gift_list_public($invitationId, $visitante)]);
    }

    $salir(400, ['ok' => false, 'error' => 'accion_desconocida']);
} catch (Throwable $e) {
    error_log('CumpleClick gift-api: ' . $e->getMessage());
    $salir(503, ['ok' => false, 'error' => 'servicio_no_disponible']);
}
