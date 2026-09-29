<?php
declare(strict_types=1);

/**
 * Chat de la portada: recibe la pregunta del visitante y se la pasa al asistente de CumpleClick en n8n
 * (flujo "Agente CumpleClick Web (PROD)"). El navegador nunca ve la dirección de n8n: vive fuera del webroot,
 * en `cumpleclick-chat.php` junto a la configuración del dominio, y no entra al repositorio.
 *
 * Límites (cada respuesta la paga la cuenta de OpenAI): 500 caracteres por mensaje, 20 mensajes cada 10 minutos
 * por visitante y 1.500 al día en total. No se guarda ni se registra el texto de las conversaciones aquí.
 */

require dirname(__DIR__, 2) . '/public/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

const CC_CHAT_WHATSAPP = 'https://wa.me/56974940070';

function cc_chat_response(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cc_chat_fallback(int $status, string $motivo): void
{
    cc_chat_response($status, [
        'ok' => false,
        'error' => $motivo,
        'reply' => 'Uy, ahora no alcanzo a responderte por aquí. Escríbenos por WhatsApp y te ayudamos: ' . CC_CHAT_WHATSAPP,
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    cc_chat_fallback(405, 'Método no permitido.');
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
    cc_chat_fallback(413, 'Mensaje demasiado largo.');
}
if (strpos(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json') !== 0) {
    cc_chat_fallback(415, 'El contenido debe enviarse como JSON.');
}
$fetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'same-site'], true)) {
    cc_chat_fallback(403, 'Origen no permitido.');
}

try {
    $porVisitante = cb_rate_limit('public-chat', cb_request_identity(), 20, 600, 600);
    if (!$porVisitante['allowed']) {
        header('Retry-After: ' . max(1, (int) $porVisitante['retry_after']));
        cc_chat_response(429, [
            'ok' => false,
            'error' => 'Demasiados mensajes seguidos.',
            'reply' => 'Me escribiste harto seguido 😅 Espera unos minutos, o escríbenos directo por WhatsApp: ' . CC_CHAT_WHATSAPP,
        ]);
    }
    $total = cb_rate_limit('public-chat-dia', 'todos', 1500, 86400, 3600);
    if (!$total['allowed']) {
        cc_chat_fallback(429, 'Tope diario alcanzado.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode(is_string($raw) ? $raw : '', true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        cc_chat_fallback(400, 'Formato inválido.');
    }
    $mensaje = trim(preg_replace('/\s+/u', ' ', (string) ($input['message'] ?? '')) ?? '');
    $sesion = (string) ($input['sessionId'] ?? '');
    if ($mensaje === '' || mb_strlen($mensaje) > 500 || !preg_match('/^[A-Za-z0-9_-]{8,64}$/', $sesion)) {
        cc_chat_response(422, ['ok' => false, 'error' => 'Revisa el mensaje.', 'reply' => 'Escríbeme tu pregunta en un mensaje de menos de 500 letras.']);
    }

    $configChat = dirname(__DIR__, 2) . '/cumpleclick-chat.php';
    $config = is_file($configChat) ? require $configChat : [];
    $webhook = is_array($config) ? (string) ($config['webhook_url'] ?? '') : '';
    if (strpos($webhook, 'https://') !== 0) {
        error_log('CumpleClick chat: falta webhook_url en cumpleclick-chat.php');
        cc_chat_fallback(503, 'Chat no configurado.');
    }

    $ch = curl_init($webhook);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['message' => $mensaje, 'sessionId' => $sesion], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errorCurl = curl_error($ch);

    $datos = is_string($cuerpo) ? json_decode($cuerpo, true) : null;
    $respuesta = is_array($datos) ? trim((string) ($datos['reply'] ?? '')) : '';
    if ($codigo !== 200 || $respuesta === '') {
        error_log('CumpleClick chat: n8n respondió ' . $codigo . ($errorCurl !== '' ? ' (' . $errorCurl . ')' : ''));
        cc_chat_fallback(502, 'El asistente no respondió.');
    }
    cc_chat_response(200, ['ok' => true, 'reply' => mb_substr($respuesta, 0, 2000)]);
} catch (JsonException $e) {
    cc_chat_fallback(400, 'La solicitud no tiene un formato válido.');
} catch (Throwable $e) {
    error_log('CumpleClick chat: ' . $e->getMessage());
    cc_chat_fallback(503, 'Error interno.');
}
