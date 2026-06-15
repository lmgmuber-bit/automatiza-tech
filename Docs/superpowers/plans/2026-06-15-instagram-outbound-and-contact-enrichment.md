# Instagram Outbound + Contact Enrichment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable the OmniCliente portal to send outbound Instagram DMs (agent + n8n bot) and resolve the real name/username/avatar of Instagram contacts, removing the "Solo guardado" and "Sin nombre" gaps.

**Architecture:** All Instagram Messaging logic lives in `omnichannel-controller.php` (codebase convention = large cohesive controllers). A single private `ig_api_request()` helper centralizes Graph API calls (base URL, version, bearer auth, error parsing). Outbound send and profile lookup are thin public wrappers over it. Inbound name enrichment hooks into the existing generic `receive_message()` so the current insert/update field handling is reused unchanged. Uses the **Instagram API with Instagram Login** flow → `https://graph.instagram.com/v23.0`, token stored in `channels.bot_token`, `recipient.id = external_contact_id` (IGSID).

**Tech Stack:** PHP 7.x/8.x, WordPress (`$wpdb`, `wp_remote_*`), Meta Instagram Graph API v23.0. Tests = standalone `test-*.php` script (project convention; no PHPUnit) using the WP `pre_http_request` filter to mock Graph API without a live token, plus a gated `live` smoke mode.

---

## Context the implementing engineer needs (zero-context assumptions)

- **Branch:** all work on `feature/instagram-cm`. Never commit to `main`.
- **Channel row fields (`wp_omnichannel_channels`):** `bot_token` = Instagram access token; `page_id` = Instagram Business Account ID (`17841477659255160`); `channel_type` = `'instagram'`; `channel_id` for the live IG channel = `2`.
- **Conversations (`wp_omnichannel_conversations`):** has `contact_name`, `contact_avatar_url`, `external_contact_id` (= IGSID for IG), `channel_type`. No `metadata`/JSON column → username is folded into `contact_name`.
- **Inbound flow:** `webhook-omnichannel.php` → `normalize_instagram()` (returns `contact_name => ''`, only `sender.id`) → `OmnichannelController::receive_message()` → `forward_to_n8n()`.
- **Outbound flow:** portal → `api-omnichannel.php` route `send` → `OmnichannelController::send_agent_message()`. Bot replies: n8n → `handle_n8n_callback()`.
- **The two bugs being fixed:**
  - `send_agent_message()` `omnichannel-controller.php:3071` — non-whatsapp falls into the "envío directo no soportado aún" `delivery_note`.
  - `normalize_instagram()` `webhook-omnichannel.php:237-244` — `contact_name` always empty.
- **Reference patterns to mirror:** `send_ycloud_message()` `omnichannel-controller.php:2929` (HTTP send + result shape); the WhatsApp delivery block in `handle_n8n_callback()` `omnichannel-controller.php:2722-2738`; test style in `test-n8n-connection.php`.
- **Security precondition (operational, not code):** the Meta token exposed in earlier setup screenshots must be rotated and updated in the IG channel before any `live` test. Do not test with a compromised token.

## API contract (Instagram API with Instagram Login, v23.0)

- **Send:** `POST https://graph.instagram.com/v23.0/me/messages`
  Header `Authorization: Bearer <bot_token>`; body `{"recipient":{"id":"<IGSID>"},"message":{"text":"<text>"}}`.
  Success → `{"recipient_id":"...","message_id":"..."}`.
- **Profile:** `GET https://graph.instagram.com/v23.0/<IGSID>?fields=name,username,profile_pic`
  Header `Authorization: Bearer <bot_token>`. Success → `{"name":"...","username":"...","profile_pic":"...","id":"..."}`.
- **24h window:** standard messaging only allowed ≤24h after the user's last message. Beyond that requires the `HUMAN_AGENT` tag (needs Meta "Human Agent" advanced access). MVP sends standard; out-of-window errors surface as `delivery_status='failed'` with the Meta message. Tag is a documented future step, NOT in this plan.

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `omnichannel-controller.php` | All IG Messaging logic (helper, send, profile, name compose) + wiring into receive/send/callback | Modify |
| `test-instagram-channel.php` | Standalone test harness: mocked (pre_http_request) unit-ish checks + gated live smoke mode | Create |
| `docs/superpowers/plans/2026-06-15-instagram-outbound-and-contact-enrichment.md` | This plan | Created |
| Vault `Instagram-CM-AT.md`, `Config-Canal-Instagram-Omnichannel.md`, `Portal-OmniCliente-Redseno.md` | Source-of-truth status update + reusable playbook + good/bad bitácora | Modify (final task) |

---

### Task 1: Instagram Graph API plumbing (helper + send + profile + name compose)

**Files:**
- Modify: `omnichannel-controller.php` — add class constants near top of class (after `private $actor_role = null;`, ~line 19) and a new `// ===== INSTAGRAM MESSAGING =====` section placed immediately after `send_ycloud_message()` (ends `omnichannel-controller.php:2973`).
- Test: `test-instagram-channel.php` (created in this task).

- [ ] **Step 1: Add class constants**

Insert after `omnichannel-controller.php:19` (`private $actor_role  = null;`):

```php
    // Instagram Messaging API (Instagram Login flow)
    const IG_GRAPH_VERSION = 'v23.0';
    const IG_GRAPH_BASE    = 'https://graph.instagram.com';
```

- [ ] **Step 2: Add the IG section (helper + 3 methods) after `send_ycloud_message()` (after line 2973)**

```php
    // =========================================================
    // INSTAGRAM MESSAGING (Instagram Login flow, graph.instagram.com)
    // =========================================================

    /**
     * Low-level Graph API request for Instagram. Centralizes base URL,
     * version, bearer auth and error parsing. Returns:
     *   ['success'=>true, 'data'=>array]            on 2xx
     *   ['error'=>string, 'code'=>int, 'fb_error'=>array|null] otherwise
     */
    private function ig_api_request($method, $channel, $path, $body = null) {
        if (empty($channel->bot_token)) {
            return ['error' => 'Canal sin token de Instagram configurado'];
        }
        $url = self::IG_GRAPH_BASE . '/' . self::IG_GRAPH_VERSION . '/' . ltrim($path, '/');
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $channel->bot_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ];
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }
        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) {
            return ['error' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'data' => is_array($json) ? $json : []];
        }
        return [
            'error'    => $json['error']['message'] ?? "HTTP {$code}",
            'code'     => $code,
            'fb_error' => $json['error'] ?? null,
        ];
    }

    /**
     * Send a text DM to an Instagram user (IGSID = recipient).
     * Returns ['success'=>true,'message_id'=>..,'recipient_id'=>..] or ['error'=>..].
     */
    public function send_instagram_message($channel_id, $recipient_igsid, $content, $msg_type = 'text') {
        $channel = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}channels WHERE id = %d", absint($channel_id)
        ));
        if (!$channel) return ['error' => 'Canal no encontrado'];
        if (empty($recipient_igsid)) return ['error' => 'Falta IGSID del destinatario'];
        if ($content === '' || $content === null) return ['error' => 'Contenido vacío'];

        $body = [
            'recipient' => ['id' => (string) $recipient_igsid],
            'message'   => ['text' => $content],
        ];
        $res = $this->ig_api_request('POST', $channel, 'me/messages', $body);
        if (!empty($res['success'])) {
            return [
                'success'      => true,
                'message_id'   => $res['data']['message_id']   ?? '',
                'recipient_id' => $res['data']['recipient_id'] ?? '',
            ];
        }
        return ['error' => $res['error'] ?? 'Error desconocido al enviar a Instagram'];
    }

    /**
     * Look up an Instagram user's profile from their IGSID.
     * Returns ['name'=>..,'username'=>..,'profile_pic'=>..] or null on failure.
     */
    public function get_instagram_profile($channel, $igsid) {
        if (empty($channel) || empty($channel->bot_token) || empty($igsid)) return null;
        $res = $this->ig_api_request('GET', $channel, rawurlencode($igsid) . '?fields=name,username,profile_pic');
        if (empty($res['success'])) {
            if (function_exists('error_log')) {
                error_log('[at-ig] profile lookup failed igsid=' . $igsid . ' err=' . ($res['error'] ?? '?'));
            }
            return null;
        }
        $d = $res['data'];
        return [
            'name'        => sanitize_text_field($d['name'] ?? ''),
            'username'    => sanitize_text_field($d['username'] ?? ''),
            'profile_pic' => esc_url_raw($d['profile_pic'] ?? ''),
        ];
    }

    /**
     * Compose a never-empty display name from an IG profile.
     * "Name (@user)" / "Name" / "@user" / "Usuario Instagram <last6 IGSID>".
     */
    private function ig_display_name($profile, $igsid) {
        if (is_array($profile)) {
            $name = $profile['name'] ?? '';
            $user = $profile['username'] ?? '';
            if ($name !== '' && $user !== '') return $name . ' (@' . $user . ')';
            if ($name !== '') return $name;
            if ($user !== '') return '@' . $user;
        }
        return 'Usuario Instagram ' . substr((string) $igsid, -6);
    }
```

- [ ] **Step 3: Create the test harness `test-instagram-channel.php`**

```php
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

    // Ensure a fake IG channel row exists for the test (id captured below).
    global $wpdb;
    $p = $wpdb->prefix . 'omnichannel_';
    $wpdb->query("INSERT INTO {$p}channels (client_id, channel_type, channel_name, is_active, bot_token, page_id, provider)
                  VALUES (1, 'instagram', 'IG TEST', 1, 'TESTTOKEN123', '17841477659255160', 'meta')");
    $ig_channel_id = (int) $wpdb->insert_id;
    $captured = [];

    // Mock send response
    add_filter('pre_http_request', function ($pre, $args, $url) use (&$captured) {
        $captured['url']    = $url;
        $captured['method'] = $args['method'] ?? 'GET';
        $captured['auth']   = $args['headers']['Authorization'] ?? '';
        $captured['body']   = isset($args['body']) ? json_decode($args['body'], true) : null;
        if (strpos($url, '/messages') !== false) {
            return ['response' => ['code' => 200], 'body' => wp_json_encode(['recipient_id' => '123', 'message_id' => 'mid.abc'])];
        }
        // profile
        return ['response' => ['code' => 200], 'body' => wp_json_encode(['name' => 'Ada Lovelace', 'username' => 'ada.codes', 'profile_pic' => 'https://x/pic.jpg', 'id' => '123'])];
    }, 10, 3);

    // --- send_instagram_message ---
    $send = $controller->send_instagram_message($ig_channel_id, '123', 'Hola desde AT');
    check("send: success",            !empty($send['success']));
    check("send: message_id parsed",  ($send['message_id'] ?? '') === 'mid.abc');
    check("send: URL is graph.instagram v23 /me/messages", strpos($captured['url'], 'https://graph.instagram.com/v23.0/me/messages') === 0);
    check("send: method POST",        $captured['method'] === 'POST');
    check("send: bearer token",       $captured['auth'] === 'Bearer TESTTOKEN123');
    check("send: recipient = IGSID",  ($captured['body']['recipient']['id'] ?? '') === '123');
    check("send: text body",          ($captured['body']['message']['text'] ?? '') === 'Hola desde AT');

    // --- get_instagram_profile ---
    $channel = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}channels WHERE id = %d", $ig_channel_id));
    $prof = $controller->get_instagram_profile($channel, '123');
    check("profile: name parsed",     ($prof['name'] ?? '') === 'Ada Lovelace');
    check("profile: username parsed", ($prof['username'] ?? '') === 'ada.codes');
    check("profile: GET method",      $captured['method'] === 'GET');
    check("profile: fields in URL",   strpos($captured['url'], 'fields=name,username,profile_pic') !== false);

    // --- ig_display_name fallbacks (via reflection, private) ---
    $ref = new ReflectionMethod('OmnichannelController', 'ig_display_name');
    $ref->setAccessible(true);
    check("name: full",     $ref->invoke($controller, ['name' => 'Ada', 'username' => 'ada'], '999') === 'Ada (@ada)');
    check("name: only name", $ref->invoke($controller, ['name' => 'Ada', 'username' => ''], '999') === 'Ada');
    check("name: only user", $ref->invoke($controller, ['name' => '', 'username' => 'ada'], '999') === '@ada');
    check("name: fallback",  $ref->invoke($controller, null, '1234567890') === 'Usuario Instagram 567890');

    // cleanup
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
```

- [ ] **Step 4: Run the mock test — expect FAIL first if methods absent, PASS after Steps 1-2**

Run: `php test-instagram-channel.php`
Expected after Steps 1-2 applied: `Resumen: 15 pass, 0 fail` and exit 0. (If run before Steps 1-2, fatal "Call to undefined method" — confirms the test exercises real code.)

- [ ] **Step 5: Commit**

```bash
git add omnichannel-controller.php test-instagram-channel.php
git commit -m "feat(omnichannel): Instagram Graph API send + profile lookup helpers

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Inbound contact name/avatar enrichment

**Files:**
- Modify: `omnichannel-controller.php` — inside `receive_message()`, after the existing conversation lookup (`omnichannel-controller.php:904-907`, the `$conversation = ...` query) and before `if (!$conversation) {` (line 909).
- Test: `test-instagram-channel.php` (extend mock section).

- [ ] **Step 1: Add enrichment block after the conversation lookup (before line 909 `if (!$conversation) {`)**

```php
        // Instagram: el webhook no trae nombre, solo el IGSID. Enriquecer una sola
        // vez (al crear la conversación o si sigue sin nombre) para no llamar a la
        // Graph API en cada mensaje entrante.
        if ($channel->channel_type === 'instagram'
            && empty($message_data['contact_name'])
            && (!$conversation || empty($conversation->contact_name))) {
            $profile = $this->get_instagram_profile($channel, $external_contact_id);
            $message_data['contact_name'] = $this->ig_display_name($profile, $external_contact_id);
            if ($profile && !empty($profile['profile_pic']) && empty($message_data['contact_avatar_url'])) {
                $message_data['contact_avatar_url'] = $profile['profile_pic'];
            }
        }
```

Rationale: the existing insert (`omnichannel-controller.php:910-923`) already consumes `message_data['contact_name']` and `contact_avatar_url`, and the existing update path (`omnichannel-controller.php:933-940`) only sets `contact_name` when currently empty. So this block is the minimal, DRY change — no edits to the insert/update logic.

- [ ] **Step 2: Extend the mock test to cover enrichment in `receive_message()`**

Add inside the `if ($mode === 'mock')` block, after the `ig_display_name` checks and before cleanup:

```php
    // --- receive_message enriches IG contact name + avatar ---
    $recv = $controller->receive_message($ig_channel_id, [
        'external_contact_id' => '777',
        'contact_name'        => '',           // webhook sends empty
        'content'             => 'hola',
        'type'                => 'text',
    ]);
    $conv = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}conversations WHERE id = %d", (int) $recv['conversation_id']
    ));
    check("recv: name enriched",   $conv->contact_name === 'Ada Lovelace (@ada.codes)');
    check("recv: avatar enriched", $conv->contact_avatar_url === 'https://x/pic.jpg');
    $wpdb->query($wpdb->prepare("DELETE FROM {$p}messages WHERE conversation_id = %d", (int) $recv['conversation_id']));
    $wpdb->query($wpdb->prepare("DELETE FROM {$p}conversations WHERE id = %d", (int) $recv['conversation_id']));
```

Also bump the expected count in your head: total becomes 17 checks.

- [ ] **Step 3: Run the mock test**

Run: `php test-instagram-channel.php`
Expected: `Resumen: 17 pass, 0 fail`, exit 0.

- [ ] **Step 4: Commit**

```bash
git add omnichannel-controller.php test-instagram-channel.php
git commit -m "feat(omnichannel): enrich Instagram contact name/avatar from IGSID

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Outbound from agent (fix "Solo guardado")

**Files:**
- Modify: `omnichannel-controller.php` — `send_agent_message()`, the channel-type branch at `omnichannel-controller.php:3069-3073`.
- Test: `test-instagram-channel.php` (extend mock section).

- [ ] **Step 1: Replace the branch at lines 3069-3073**

Find:

```php
        } elseif ($ch_type === 'whatsapp' && empty($conv->contact_phone)) {
            $local['ycloud'] = ['error' => 'No hay teléfono de contacto en esta conversación'];
        } elseif ($ch_type !== 'whatsapp') {
            $local['delivery_note'] = "Canal tipo '{$ch_type}' — mensaje guardado localmente (envío directo no soportado aún para este canal)";
        }
```

Replace with:

```php
        } elseif ($ch_type === 'whatsapp' && empty($conv->contact_phone)) {
            $local['ycloud'] = ['error' => 'No hay teléfono de contacto en esta conversación'];
        } elseif ($ch_type === 'instagram' && !empty($conv->external_contact_id)) {
            $ig = $this->send_instagram_message($conv->ch_id, $conv->external_contact_id, $content);
            if (!empty($ig['success'])) {
                $this->wpdb->update($this->prefix . 'messages', [
                    'external_message_id' => $ig['message_id'] ?? '',
                    'delivery_status'     => 'sent',
                ], ['id' => $local['message_id']]);
            } else {
                $this->wpdb->update($this->prefix . 'messages', [
                    'delivery_status' => 'failed',
                    'error_message'   => $ig['error'] ?? 'Error desconocido',
                ], ['id' => $local['message_id']]);
            }
            $local['instagram'] = $ig;
        } elseif ($ch_type === 'instagram') {
            $local['instagram'] = ['error' => 'No hay IGSID de contacto en esta conversación'];
        } elseif ($ch_type !== 'whatsapp') {
            $local['delivery_note'] = "Canal tipo '{$ch_type}' — mensaje guardado localmente (envío directo no soportado aún para este canal)";
        }
```

- [ ] **Step 2: Extend the mock test to cover agent send over IG**

Add inside the mock block (before cleanup):

```php
    // --- send_agent_message delivers over Instagram (status -> sent) ---
    $recv2 = $controller->receive_message($ig_channel_id, [
        'external_contact_id' => '888', 'contact_name' => '', 'content' => 'hi', 'type' => 'text',
    ]);
    $cid2 = (int) $recv2['conversation_id'];
    $am = $controller->send_agent_message($cid2, ['content' => 'Respuesta del agente', 'agent_name' => 'Luis']);
    check("agent: instagram success", !empty($am['instagram']['success']));
    $outmsg = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}messages WHERE conversation_id = %d AND direction = 'outbound' ORDER BY id DESC LIMIT 1", $cid2
    ));
    check("agent: msg delivery_status sent", $outmsg && $outmsg->delivery_status === 'sent');
    check("agent: no 'Solo guardado' note", empty($am['delivery_note']));
    $wpdb->query($wpdb->prepare("DELETE FROM {$p}messages WHERE conversation_id = %d", $cid2));
    $wpdb->query($wpdb->prepare("DELETE FROM {$p}conversations WHERE id = %d", $cid2));
```

Total checks now 20.

- [ ] **Step 3: Run the mock test**

Run: `php test-instagram-channel.php`
Expected: `Resumen: 20 pass, 0 fail`, exit 0.

- [ ] **Step 4: Commit**

```bash
git add omnichannel-controller.php test-instagram-channel.php
git commit -m "feat(omnichannel): send agent replies to Instagram (fix Solo guardado)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Outbound from n8n bot (MAXTECH replies over IG)

**Files:**
- Modify: `omnichannel-controller.php` — `handle_n8n_callback()` simple-mode delivery block. The WhatsApp `if` opens at `omnichannel-controller.php:2722` and closes at `omnichannel-controller.php:2738` (the `}` immediately before the blank line and `return $local;`).
- Test: `test-instagram-channel.php` (extend mock section).

- [ ] **Step 1: Convert the closing `}` at line 2738 into an `elseif` for Instagram**

The block currently ends:

```php
            } else {
                $this->wpdb->update($this->prefix . 'messages', [
                    'delivery_status' => 'failed',
                    'error_message'   => $ycloud['error'] ?? 'Error desconocido',
                ], ['id' => $local['message_id']]);
                $local['delivery_error'] = $ycloud['error'] ?? 'Error desconocido';
            }
        }
```

Change the final `}` (line 2738, closes the `if whatsapp`) so the chain continues:

```php
            } else {
                $this->wpdb->update($this->prefix . 'messages', [
                    'delivery_status' => 'failed',
                    'error_message'   => $ycloud['error'] ?? 'Error desconocido',
                ], ['id' => $local['message_id']]);
                $local['delivery_error'] = $ycloud['error'] ?? 'Error desconocido';
            }
        } elseif ($conversation->channel_type === 'instagram' && !empty($conversation->external_contact_id)) {
            $ig = $this->send_instagram_message($channel_id, $conversation->external_contact_id, $content);
            if (!empty($ig['success'])) {
                $this->wpdb->update($this->prefix . 'messages', [
                    'external_message_id' => $ig['message_id'] ?? '',
                    'delivery_status'     => 'sent',
                ], ['id' => $local['message_id']]);
                $local['delivered'] = true;
            } else {
                $this->wpdb->update($this->prefix . 'messages', [
                    'delivery_status' => 'failed',
                    'error_message'   => $ig['error'] ?? 'Error desconocido',
                ], ['id' => $local['message_id']]);
                $local['delivery_error'] = $ig['error'] ?? 'Error desconocido';
            }
        }
```

- [ ] **Step 2: Extend the mock test to cover the n8n callback over IG**

The callback verifies an HMAC token: `hash_hmac('sha256', $conversation_id.':'.$channel_id, OMNI_ADMIN_SECRET ?: 'omni_default_secret')`. Build the same token in the test.

Add inside the mock block (before cleanup):

```php
    // --- handle_n8n_callback delivers bot reply over Instagram ---
    $recv3 = $controller->receive_message($ig_channel_id, [
        'external_contact_id' => '999', 'contact_name' => '', 'content' => 'q', 'type' => 'text',
    ]);
    $cid3 = (int) $recv3['conversation_id'];
    $secret = defined('OMNI_ADMIN_SECRET') ? OMNI_ADMIN_SECRET : 'omni_default_secret';
    $token  = hash_hmac('sha256', $cid3 . ':' . $ig_channel_id, $secret);
    $cb = $controller->handle_n8n_callback([
        'conversation_id' => $cid3,
        'channel_id'      => $ig_channel_id,
        'callback_token'  => $token,
        'content'         => 'Respuesta del bot',
        'message_type'    => 'text',
    ]);
    check("n8n: delivered over IG", !empty($cb['delivered']));
    $wpdb->query($wpdb->prepare("DELETE FROM {$p}messages WHERE conversation_id = %d", $cid3));
    $wpdb->query($wpdb->prepare("DELETE FROM {$p}conversations WHERE id = %d", $cid3));
```

Total checks now 21.

- [ ] **Step 3: Run the mock test**

Run: `php test-instagram-channel.php`
Expected: `Resumen: 21 pass, 0 fail`, exit 0.

- [ ] **Step 4: Commit**

```bash
git add omnichannel-controller.php test-instagram-channel.php
git commit -m "feat(omnichannel): deliver n8n bot replies over Instagram

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Live smoke verification (gated on rotated token)

**Files:** none changed. Operational verification.

- [ ] **Step 1: Confirm the Meta token was rotated** and updated in the IG channel (`channel_id=2`). Do not proceed with the old screenshot-exposed token.

- [ ] **Step 2: Profile live test**

Run: `php "test-instagram-channel.php" live-profile 2 <IGSID-of-a-real-recent-DM-sender>`
Expected: a `['name'=>..,'username'=>..,'profile_pic'=>..]` array, not null. If null → check `instagram_business_manage_messages` scope + token validity (see `error_log`).

- [ ] **Step 3: Send live test (must be within 24h window of that user's last DM)**

Run: `php "test-instagram-channel.php" live-send 2 <IGSID> "Prueba AT envío saliente"`
Expected: `['success'=>true,'message_id'=>'..']`. Confirm the DM arrives in the `@automatizatech.cl` Instagram inbox.

- [ ] **Step 4: End-to-end portal test**

In the portal: open the IG conversation → it shows the real name/username + avatar (no "Sin nombre"). Take over → reply → message shows `delivery_status=sent` (no "Solo guardado") and arrives on Instagram.

- [ ] **Step 5: Out-of-window check**

Reply to a conversation whose user last wrote >24h ago. Expected: message stored with `delivery_status=failed` and a Meta error visible (not a silent success, not a crash). Record the exact Meta error text in the bitácora (Task 6) — it informs whether the HUMAN_AGENT tag is needed later.

---

### Task 6: Documentation — vault source-of-truth + reusable playbook + good/bad bitácora

**Files:**
- Modify (vault): `Instagram-CM-AT.md`, `Config-Canal-Instagram-Omnichannel.md`, `Portal-OmniCliente-Redseno.md`.
- The good/bad bitácora lives in this plan (section below) and is mirrored to the vault.

- [ ] **Step 1: Update `Config-Canal-Instagram-Omnichannel.md`** — move "envío saliente" and "enriquecimiento de contacto" from Pendiente to Hecho, with the confirmed endpoint (`graph.instagram.com/v23.0/me/messages`), token field (`bot_token`), and the 24h-window note + the exact Meta out-of-window error captured in Task 5.

- [ ] **Step 2: Update `Instagram-CM-AT.md`** — Stack table rows "Mensajería saliente IG" and "Perfil usuario IG" → ✅, dated 2026-06-15. Update "Próximo paso" to publicación automática (Fase 3).

- [ ] **Step 3: Update `Portal-OmniCliente-Redseno.md` Pendientes** — strike the IG outbound/Sin-nombre items; keep token rotation + Copiar-URL fix as still-open.

- [ ] **Step 4: Write the reusable playbook** as a vault note `30-Agent-Protocols` (or link from `Instagram-CM-AT.md`): "Cómo añadir un canal Meta saliente al Omnicanal AT" — generalizes the `ig_api_request` + branch-in-three-places + enrich-in-receive_message pattern for the next client. This is the offerable asset.

- [ ] **Step 5: Commit (docs are in vault, outside the repo — commit only repo-side plan updates)**

```bash
git add docs/superpowers/plans/2026-06-15-instagram-outbound-and-contact-enrichment.md
git commit -m "docs: instagram channel plan + good/bad bitacora

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Bitácora de calidad — lo bueno / lo malo (rellenar durante ejecución)

> Requisito del cliente AT: documentar qué salió bien (para repetir y ofrecer) y qué salió mal (para no repetir). Mantener concreto y accionable.

### Lo bueno (patrones a reutilizar)
- **`ig_api_request()` único punto de Graph API:** base URL, versión, auth y parseo de error en un solo lugar → send y profile son wrappers triviales. Reutilizable para cualquier canal Meta.
- **Enriquecer en `receive_message()` reusando insert/update existentes:** cero cambios al manejo de campos; un solo bloque guard evita llamadas HTTP redundantes.
- **Test con `pre_http_request`:** se prueba el código real (URL, headers, body, parseo) sin token ni red, siguiendo la convención `test-*.php` del proyecto.
- **Mismo shape de resultado que `send_ycloud_message`** (`success`/`error`) → el portal y los callbacks tratan WhatsApp e IG igual.

### Lo malo / riesgos (no repetir)
- **Token expuesto en capturas:** NUNCA pegar tokens en notas/capturas; rotar antes de probar. Bloqueante operacional.
- **Ventana 24h de Instagram:** asumir que "la app publicada = puedo escribir siempre" es falso. Fuera de 24h falla; documentar el error real y decidir HUMAN_AGENT en fase 2.
- **`normalize_instagram()` devolvía `contact_name=''`:** los webhooks Meta de IG no traen nombre; siempre requiere lookup. No diseñar asumiendo que el webhook trae perfil.
- **Username sin columna propia:** se folded en `contact_name`. Si se necesita filtrar/buscar por username, evaluar columna `contact_username` (migración) en vez de parsear el string.
- **`me/messages` vs `{page_id}/messages`:** el flujo Instagram-Login usa `me`. Si se migra a Messenger-Platform/Página, cambia endpoint y semántica de token — no es intercambiable sin tocar `ig_api_request`.

---

## Self-Review

**1. Spec coverage**
- Envío saliente agente → Task 3. ✅
- Envío saliente bot n8n → Task 4. ✅
- Enriquecimiento nombre/usuario/avatar → Task 2 (+ Task 1 lookup). ✅
- Flujo graph.instagram.com Instagram-Login, token `bot_token`, recipient IGSID → Task 1 contract. ✅
- 24h window handling → Task 1 doc + Task 5 step 5. ✅
- Documentar good/bad + reusable → Task 6 + bitácora. ✅
- Token rotation precondition → Context + Task 5 step 1. ✅

**2. Placeholder scan:** No TBD/TODO; every code step shows full code; test code included; commands have expected output. ✅

**3. Type consistency:** `send_instagram_message()` returns `success|message_id|recipient_id|error` — consumed identically in Tasks 3 & 4. `get_instagram_profile()` returns `name|username|profile_pic|null` — consumed in Task 2 and `ig_display_name()`. `ig_api_request()` returns `success|data` / `error|code|fb_error` — consumed by both wrappers. `external_contact_id` (IGSID) used consistently as recipient. ✅
