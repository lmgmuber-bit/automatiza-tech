<?php
/**
 * Webhook Receiver - Receptor de mensajes de canales externos
 * 
 * Este archivo recibe webhooks de:
 * - WhatsApp Business API (Cloud/On-premise)
 * - Instagram Messaging API
 * - Telegram Bot API
 * - Facebook Messenger API
 * 
 * URL: /webhook-omnichannel.php?channel_id={ID}&secret={WEBHOOK_SECRET}
 * 
 * Cada plataforma envía en formato diferente, este archivo normaliza
 * los datos antes de pasarlos al controller.
 */

ob_start();
require_once __DIR__ . '/wp-load.php';
ob_end_clean();

require_once __DIR__ . '/omnichannel-controller.php';

header('Content-Type: application/json; charset=utf-8');

$controller = new OmnichannelController();

$channel_id = absint($_GET['channel_id'] ?? 0);
$secret = sanitize_text_field($_GET['secret'] ?? '');

if (!$channel_id || empty($secret)) {
    http_response_code(400);
    echo wp_json_encode(['error' => 'channel_id y secret requeridos']);
    exit;
}

// Verificar webhook secret
global $wpdb;
$channel = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}omnichannel_channels WHERE id = %d AND is_active = 1",
    $channel_id
));

if (!$channel || !hash_equals($channel->webhook_secret, $secret)) {
    http_response_code(403);
    echo wp_json_encode(['error' => 'No autorizado']);
    exit;
}

// GET = verificación del webhook (WhatsApp/Meta)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // WhatsApp/Meta webhook verification
    $mode = sanitize_text_field($_GET['hub_mode'] ?? '');
    $token = sanitize_text_field($_GET['hub_verify_token'] ?? '');
    $challenge = sanitize_text_field($_GET['hub_challenge'] ?? '');
    
    if ($mode === 'subscribe' && $token === $secret) {
        http_response_code(200);
        echo $challenge;
        exit;
    }
    
    // Telegram webhook verification (just respond 200)
    http_response_code(200);
    echo wp_json_encode(['status' => 'ok']);
    exit;
}

// POST = mensaje entrante
$raw_body = file_get_contents('php://input');
$payload = json_decode($raw_body, true);

if (empty($payload)) {
    http_response_code(400);
    echo wp_json_encode(['error' => 'Payload vacío']);
    exit;
}

// Normalizar según tipo de canal
$normalized = normalize_message($channel->channel_type, $payload);

if (!$normalized) {
    http_response_code(200); // Respond 200 to avoid retries for status updates, etc.
    echo wp_json_encode(['status' => 'ignored']);
    exit;
}

$result = $controller->receive_message($channel_id, $normalized);

http_response_code(isset($result['error']) ? 400 : 200);
echo wp_json_encode($result);

// ==========================================
// NORMALIZACIÓN POR PLATAFORMA
// ==========================================

function normalize_message($channel_type, $payload) {
    switch ($channel_type) {
        case 'whatsapp':
            return normalize_whatsapp($payload);
        case 'instagram':
            return normalize_instagram($payload);
        case 'telegram':
            return normalize_telegram($payload);
        case 'messenger':
            return normalize_messenger($payload);
        default:
            return null;
    }
}

/**
 * WhatsApp Business API (Cloud API format)
 */
function normalize_whatsapp($payload) {
    $entry = $payload['entry'][0] ?? null;
    if (!$entry) return null;
    
    $changes = $entry['changes'][0] ?? null;
    if (!$changes) return null;
    
    $value = $changes['value'] ?? null;
    if (!$value || empty($value['messages'])) return null;
    
    $msg = $value['messages'][0];
    $contact = $value['contacts'][0] ?? [];
    
    $type = $msg['type'] ?? 'text';
    $content = '';
    $media_url = '';
    
    switch ($type) {
        case 'text':
            $content = $msg['text']['body'] ?? '';
            break;
        case 'image':
            $content = $msg['image']['caption'] ?? '[Imagen]';
            $media_url = $msg['image']['id'] ?? '';
            break;
        case 'video':
            $content = '[Video]';
            $media_url = $msg['video']['id'] ?? '';
            break;
        case 'audio':
            $content = '[Audio]';
            $media_url = $msg['audio']['id'] ?? '';
            break;
        case 'document':
            $content = $msg['document']['filename'] ?? '[Documento]';
            $media_url = $msg['document']['id'] ?? '';
            break;
        case 'location':
            $lat = $msg['location']['latitude'] ?? 0;
            $lon = $msg['location']['longitude'] ?? 0;
            $content = "📍 Ubicación: $lat, $lon";
            $type = 'location';
            break;
        case 'sticker':
            $content = '[Sticker]';
            $type = 'sticker';
            break;
        default:
            $content = "[Mensaje tipo: $type]";
            $type = 'text';
    }
    
    return [
        'external_contact_id' => $msg['from'] ?? '',
        'contact_name'        => $contact['profile']['name'] ?? '',
        'contact_phone'       => $msg['from'] ?? '',
        'content'             => $content,
        'type'                => $type,
        'media_url'           => $media_url,
        'external_message_id' => $msg['id'] ?? '',
    ];
}

/**
 * Instagram Messaging API
 */
function normalize_instagram($payload) {
    $entry = $payload['entry'][0] ?? null;
    if (!$entry) return null;
    
    $messaging = $entry['messaging'][0] ?? null;
    if (!$messaging || !isset($messaging['message'])) return null;
    
    $msg = $messaging['message'];
    $sender_id = $messaging['sender']['id'] ?? '';
    
    $content = $msg['text'] ?? '';
    $type = 'text';
    $media_url = '';
    
    if (!empty($msg['attachments'])) {
        $attachment = $msg['attachments'][0];
        $att_type = $attachment['type'] ?? 'fallback';
        $media_url = $attachment['payload']['url'] ?? '';
        
        switch ($att_type) {
            case 'image': $type = 'image'; $content = $content ?: '[Imagen]'; break;
            case 'video': $type = 'video'; $content = '[Video]'; break;
            case 'audio': $type = 'audio'; $content = '[Audio]'; break;
            default: $content = $content ?: '[Archivo adjunto]';
        }
    }
    
    return [
        'external_contact_id' => $sender_id,
        'contact_name'        => '',
        'content'             => $content,
        'type'                => $type,
        'media_url'           => $media_url,
        'external_message_id' => $msg['mid'] ?? '',
    ];
}

/**
 * Telegram Bot API
 */
function normalize_telegram($payload) {
    $message = $payload['message'] ?? $payload['edited_message'] ?? null;
    if (!$message) return null;
    
    $from = $message['from'] ?? [];
    $chat_id = $message['chat']['id'] ?? '';
    $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    
    $content = $message['text'] ?? '';
    $type = 'text';
    $media_url = '';
    
    if (isset($message['photo'])) {
        $largest = end($message['photo']);
        $content = $message['caption'] ?? '[Foto]';
        $type = 'image';
        $media_url = $largest['file_id'] ?? '';
    } elseif (isset($message['video'])) {
        $content = '[Video]';
        $type = 'video';
        $media_url = $message['video']['file_id'] ?? '';
    } elseif (isset($message['voice'])) {
        $content = '[Audio]';
        $type = 'audio';
        $media_url = $message['voice']['file_id'] ?? '';
    } elseif (isset($message['document'])) {
        $content = $message['document']['file_name'] ?? '[Documento]';
        $type = 'document';
        $media_url = $message['document']['file_id'] ?? '';
    } elseif (isset($message['sticker'])) {
        $content = $message['sticker']['emoji'] ?? '[Sticker]';
        $type = 'sticker';
    } elseif (isset($message['location'])) {
        $lat = $message['location']['latitude'];
        $lon = $message['location']['longitude'];
        $content = "📍 Ubicación: $lat, $lon";
        $type = 'location';
    }
    
    return [
        'external_contact_id' => (string) $chat_id,
        'contact_name'        => $name,
        'content'             => $content,
        'type'                => $type,
        'media_url'           => $media_url,
        'external_message_id' => (string) ($message['message_id'] ?? ''),
    ];
}

/**
 * Facebook Messenger API
 */
function normalize_messenger($payload) {
    $entry = $payload['entry'][0] ?? null;
    if (!$entry) return null;
    
    $messaging = $entry['messaging'][0] ?? null;
    if (!$messaging || !isset($messaging['message'])) return null;
    
    $msg = $messaging['message'];
    $sender_id = $messaging['sender']['id'] ?? '';
    
    $content = $msg['text'] ?? '';
    $type = 'text';
    $media_url = '';
    
    if (!empty($msg['attachments'])) {
        $attachment = $msg['attachments'][0];
        $att_type = $attachment['type'] ?? 'fallback';
        $media_url = $attachment['payload']['url'] ?? '';
        
        switch ($att_type) {
            case 'image': $type = 'image'; $content = $content ?: '[Imagen]'; break;
            case 'video': $type = 'video'; $content = '[Video]'; break;
            case 'audio': $type = 'audio'; $content = '[Audio]'; break;
            case 'location':
                $lat = $attachment['payload']['coordinates']['lat'] ?? 0;
                $lon = $attachment['payload']['coordinates']['long'] ?? 0;
                $content = "📍 Ubicación: $lat, $lon";
                $type = 'location';
                break;
            default: $content = $content ?: '[Archivo adjunto]';
        }
    }
    
    return [
        'external_contact_id' => $sender_id,
        'contact_name'        => '',
        'content'             => $content,
        'type'                => $type,
        'media_url'           => $media_url,
        'external_message_id' => $msg['mid'] ?? '',
    ];
}
