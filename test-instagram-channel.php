<?php
require_once __DIR__ . '/at-maintenance-guard.php';
/**
 * Test del canal Instagram (envío saliente + perfil de contacto).
 *
 * Modo MOCK (default, sin red, sin token real):
 *   php test-instagram-channel.php
 *   Intercepta Graph API con el filtro pre_http_request y verifica que
 *   send_instagram_message() y get_instagram_profile() construyan la
 *   request correcta y parseen la respuesta. Verifica ig_display_name().
 *
 * Modo LIVE (smoke real, requiere token rotado y ventana 24h):
 *   php "test-instagram-channel.php" live-profile <channel_id> <igsid>
 *   php "test-instagram-channel.php" live-send    <channel_id> <igsid> "texto"
 */
require_once __DIR__ . '/wp-load.php';
require_once __DIR__ . '/omnichannel-controller.php';

$controller = new OmnichannelController();
$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    echo ($cond ? "✅" : "❌") . " {$label}" . PHP_EOL;
    $cond ? $pass++ : $fail++;
}

$mode = $argv[1] ?? 'mock';

if ($mode === 'mock') {
    echo "=== MODO MOCK (sin red) ===" . PHP_EOL;

    global $wpdb;
    $p = $wpdb->prefix . 'omnichannel_';
    $wpdb->insert($p . 'channels', [
        'client_id'    => 1,
        'channel_type' => 'instagram',
        'channel_name' => 'IG TEST',
        'is_active'    => 1,
        'bot_token'    => 'TESTTOKEN123',
        'page_id'      => '17841477659255160',
        'provider'     => 'meta',
    ]);
    $ig_channel_id = (int) $wpdb->insert_id;
    $captured = [];
    $profile_calls = 0;

    add_filter('pre_http_request', function ($pre, $args, $url) use (&$captured, &$profile_calls) {
        $captured['url']    = $url;
        $captured['method'] = $args['method'] ?? 'GET';
        $captured['auth']   = $args['headers']['Authorization'] ?? '';
        $captured['body']   = isset($args['body']) ? json_decode($args['body'], true) : null;
        if (strpos($url, '/messages') !== false) {
            return ['response' => ['code' => 200], 'body' => wp_json_encode(['recipient_id' => '123', 'message_id' => 'mid.abc'])];
        }
        if (strpos($url, 'graph.instagram.com') !== false) {
            $profile_calls++;
        }
        return ['response' => ['code' => 200], 'body' => wp_json_encode(['name' => 'Ada Lovelace', 'username' => 'ada.codes', 'profile_pic' => 'https://x/pic.jpg', 'id' => '123'])];
    }, 10, 3);

    $send = $controller->send_instagram_message($ig_channel_id, '123', 'Hola desde AT');
    check("send: success",            !empty($send['success']));
    check("send: message_id parsed",  ($send['message_id'] ?? '') === 'mid.abc');
    check("send: URL is graph.instagram v23 /me/messages", strpos($captured['url'], 'https://graph.instagram.com/v23.0/me/messages') === 0);
    check("send: method POST",        $captured['method'] === 'POST');
    check("send: bearer token",       $captured['auth'] === 'Bearer TESTTOKEN123');
    check("send: recipient = IGSID",  ($captured['body']['recipient']['id'] ?? '') === '123');
    check("send: text body",          ($captured['body']['message']['text'] ?? '') === 'Hola desde AT');

    $channel = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}channels WHERE id = %d", $ig_channel_id));
    $prof = $controller->get_instagram_profile($channel, '123');
    check("profile: name parsed",     ($prof['name'] ?? '') === 'Ada Lovelace');
    check("profile: username parsed", ($prof['username'] ?? '') === 'ada.codes');
    check("profile: GET method",      $captured['method'] === 'GET');
    check("profile: fields in URL",   strpos($captured['url'], 'fields=name,username,profile_pic') !== false);

    $ref = new ReflectionMethod('OmnichannelController', 'ig_display_name');
    $ref->setAccessible(true);
    check("name: full",     $ref->invoke($controller, ['name' => 'Ada', 'username' => 'ada'], '999') === 'Ada (@ada)');
    check("name: only name", $ref->invoke($controller, ['name' => 'Ada', 'username' => ''], '999') === 'Ada');
    check("name: only user", $ref->invoke($controller, ['name' => '', 'username' => 'ada'], '999') === '@ada');
    check("name: fallback",  $ref->invoke($controller, null, '1234567890') === 'Usuario Instagram 567890');

    // --- receive_message enriches IG contact name + avatar ---
    $profile_calls = 0; // reset counter: only count calls made inside receive_message
    $recv = $controller->receive_message($ig_channel_id, [
        'external_contact_id' => '777',
        'contact_name'        => '',
        'content'             => 'hola',
        'type'                => 'text',
    ]);
    $conv = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}conversations WHERE id = %d", (int) $recv['conversation_id']
    ));
    check("recv: name enriched",   $conv->contact_name === 'Ada Lovelace (@ada.codes)');
    check("recv: avatar enriched", $conv->contact_avatar_url === 'https://x/pic.jpg');
    check("recv: profile called once", $profile_calls === 1);
    // Second inbound from same contact must NOT trigger another profile lookup (guard)
    $controller->receive_message($ig_channel_id, [
        'external_contact_id' => '777',
        'contact_name'        => '',
        'content'             => 'otra vez',
        'type'                => 'text',
    ]);
    check("recv: guard prevents 2nd profile call", $profile_calls === 1);
    $conv2 = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}conversations WHERE id = %d", (int) $recv['conversation_id']
    ));
    check("recv: name unchanged on 2nd msg", $conv2->contact_name === 'Ada Lovelace (@ada.codes)');
    $wpdb->query($wpdb->prepare("DELETE FROM {$p}messages WHERE conversation_id = %d", (int) $recv['conversation_id']));
    $wpdb->query($wpdb->prepare("DELETE FROM {$p}conversations WHERE id = %d", (int) $recv['conversation_id']));

    $wpdb->query($wpdb->prepare("DELETE FROM {$p}channels WHERE id = %d", $ig_channel_id));

    echo PHP_EOL . "Resumen: {$pass} pass, {$fail} fail" . PHP_EOL;
    exit($fail ? 1 : 0);
}

if ($mode === 'live-profile') {
    $cid = (int) ($argv[2] ?? 0); $igsid = $argv[3] ?? '';
    global $wpdb; $p = $wpdb->prefix . 'omnichannel_';
    $channel = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}channels WHERE id = %d", $cid));
    var_dump($controller->get_instagram_profile($channel, $igsid));
    exit;
}

if ($mode === 'live-send') {
    $cid = (int) ($argv[2] ?? 0); $igsid = $argv[3] ?? ''; $text = $argv[4] ?? 'Test AT';
    var_dump($controller->send_instagram_message($cid, $igsid, $text));
    exit;
}

echo "Modo desconocido. Usa: mock | live-profile | live-send" . PHP_EOL;
