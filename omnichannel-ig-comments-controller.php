<?php
/**
 * Respuestas automáticas a comentarios con palabra clave en Instagram (Portal OmniCliente).
 *
 * Cuando alguien comenta una palabra clave configurada (MÉTODO, SPA, …) en un post de la
 * cuenta, se le responde en público bajo su comentario y después se le manda la respuesta
 * privada ligada a ese comentario (recipient.comment_id). La conversación queda en el inbox
 * para que la siga el asistente de mensajes directos o una persona.
 *
 * - Entrada: el webhook de Meta (campo `comments`, desde webhook-omnichannel.php) o, como
 *   plan B, la consulta periódica poll() que n8n dispara por ig-comments/poll. Los dos pasan
 *   por process_comment(), así que las reglas son una sola.
 * - Nunca se escribe por IGSID: el privado va al comment_id y Meta lo entrega solo a quien
 *   escribió ese comentario. Una reserva atómica (comment_id único) impide responder dos veces.
 * - Llama a Meta solo a través de OmnichannelController (el token no sale de este servidor).
 * - Meta (doc vigente, 24-oct-2026): permisos instagram_business_basic +
 *   instagram_business_manage_comments; una sola respuesta privada por comentario y dentro
 *   de 7 días; el webhook de comments exige Advanced Access.
 * - Configuración por canal en bot_configs.custom_functions_json, clave `palabras_clave`.
 *
 * Tabla: {prefix}omnichannel_ig_comment_replies
 * Diseño: Docs/superpowers/specs/2026-10-24-ig-comentarios-palabra-clave-design.md
 */

if (!defined('ABSPATH')) {
    exit;
}

class OmniIgCommentsController {

    const DB_VERSION = '1';
    /** Meta solo acepta la respuesta privada dentro de 7 días desde el comentario. */
    const MAX_AGE_SECONDS = 604800;
    const DEFAULT_MAX_PER_PERSON = 3;
    const MAX_ATTEMPTS = 3;
    /** La consulta mira los posts de los últimos 30 días: uno viejo puede recibir comentarios hoy. */
    const POLL_MEDIA_DAYS = 30;
    const POLL_MEDIA_LIMIT = 15;
    const POLL_COMMENTS_LIMIT = 50;
    const POLL_LOCK_SECONDS = 120;
    const ACCOUNT_CACHE_SECONDS = 43200;

    private $wpdb;
    private $prefix;
    /** @var OmnichannelController */
    private $omni;

    public function __construct($omni) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'omnichannel_';
        $this->omni = $omni;
    }

    /* ============================== esquema ============================== */

    public function maybe_create_tables() {
        if (get_option('omni_ig_comments_db_version') === self::DB_VERSION) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$this->prefix}ig_comment_replies (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id VARCHAR(64) NOT NULL,
            channel_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            media_id VARCHAR(64) NOT NULL DEFAULT '',
            from_igsid VARCHAR(64) NOT NULL DEFAULT '',
            from_username VARCHAR(100) NOT NULL DEFAULT '',
            keyword VARCHAR(60) NOT NULL DEFAULT '',
            comment_text VARCHAR(500) NOT NULL DEFAULT '',
            source VARCHAR(10) NOT NULL DEFAULT 'webhook',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            public_status VARCHAR(10) NOT NULL DEFAULT 'pending',
            public_reply_id VARCHAR(64) NOT NULL DEFAULT '',
            dm_status VARCHAR(10) NOT NULL DEFAULT 'pending',
            dm_message_id VARCHAR(191) NOT NULL DEFAULT '',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
            error VARCHAR(500) NOT NULL DEFAULT '',
            conversation_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY comment_id (comment_id),
            KEY channel_created (channel_id, created_at),
            KEY person (channel_id, from_igsid, created_at)
        ) " . $this->wpdb->get_charset_collate() . ";");
        update_option('omni_ig_comments_db_version', self::DB_VERSION, false);
    }

    /* ============================== configuración ============================== */

    /**
     * Texto comparable: sin tildes, en mayúsculas, sin emojis ni signos y con un solo espacio.
     * «Método 🙌» → «METODO»; una frase sigue siendo frase («QUIERO EL METODO»).
     */
    public static function normalize_text($text) {
        $t = remove_accents((string) $text);
        $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t);
        $t = preg_replace('/\s+/u', ' ', (string) $t);
        return mb_strtoupper(trim((string) $t), 'UTF-8');
    }

    /** Configuración válida del canal o null si no hay palabras configuradas. */
    public function get_config($channel_id) {
        $raw = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT custom_functions_json FROM {$this->prefix}bot_configs WHERE channel_id = %d ORDER BY id LIMIT 1",
            absint($channel_id)
        ));
        $all = $raw ? json_decode($raw, true) : null;
        $cfg = is_array($all) ? ($all['palabras_clave'] ?? null) : null;
        if (!is_array($cfg) || empty($cfg['palabras']) || !is_array($cfg['palabras'])) {
            return null;
        }
        $words = [];
        foreach ($cfg['palabras'] as $w) {
            $key = self::normalize_text($w['palabra'] ?? '');
            $pub = trim((string) ($w['publica'] ?? ''));
            $dm  = trim((string) ($w['privada'] ?? ''));
            if ($key !== '' && $pub !== '' && $dm !== '') {
                $words[$key] = ['palabra' => (string) $w['palabra'], 'publica' => $pub, 'privada' => $dm];
            }
        }
        $since = strtotime((string) ($cfg['activo_desde'] ?? ''));
        if (!$words || !$since) {
            return null;
        }
        return [
            'activo'       => !empty($cfg['activo']),
            'activo_desde' => $since,
            'max_24h'      => max(1, (int) ($cfg['max_por_persona_24h'] ?? self::DEFAULT_MAX_PER_PERSON)),
            'palabras'     => $words,
        ];
    }

    /**
     * Guarda la clave palabras_clave del canal sin tocar las demás (p. ej. derivacion del asistente).
     * @return true|array ['error' => ...]
     */
    public function save_config($channel_id, array $config) {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id, custom_functions_json FROM {$this->prefix}bot_configs WHERE channel_id = %d ORDER BY id LIMIT 1",
            absint($channel_id)
        ));
        if (!$row) return ['error' => 'El canal no tiene configuración de bot'];
        $all = $row->custom_functions_json ? json_decode($row->custom_functions_json, true) : [];
        if (!is_array($all)) return ['error' => 'custom_functions_json no es un JSON válido: no se pisa'];
        $all['palabras_clave'] = $config;
        $ok = $this->wpdb->update($this->prefix . 'bot_configs',
            ['custom_functions_json' => wp_json_encode($all, JSON_UNESCAPED_UNICODE)], ['id' => $row->id]);
        return $ok === false ? ['error' => 'No se pudo guardar'] : true;
    }

    /* ============================== entrada ============================== */

    /** True si un aviso de Meta trae cambios del campo comments. */
    public static function payload_has_comments($payload) {
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? '') === 'comments') return true;
            }
        }
        return false;
    }

    /** Aviso del webhook: procesa cada comentario de cada entry. */
    public function handle_webhook($channel_id, array $payload) {
        $this->maybe_create_tables();
        $results = [];
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? '') !== 'comments' || !is_array($change['value'] ?? null)) continue;
                $v = $change['value'];
                $results[] = $this->process_comment($channel_id, [
                    'id'         => (string) ($v['id'] ?? ''),
                    'text'       => (string) ($v['text'] ?? ''),
                    'from_id'    => (string) ($v['from']['id'] ?? ''),
                    'username'   => (string) ($v['from']['username'] ?? ''),
                    'media_id'   => (string) ($v['media']['id'] ?? ''),
                    'account_id' => (string) ($entry['id'] ?? ''),
                    // El aviso no trae la hora del comentario: se usa la del envío de Meta.
                    'time'       => (int) ($entry['time'] ?? time()),
                ], 'webhook');
            }
        }
        return ['ok' => true, 'results' => $results];
    }

    /**
     * Plan B: lee los comentarios de los posts recientes y procesa los nuevos. Solo relee un
     * post si cambió su cantidad de comentarios desde la consulta anterior.
     */
    public function poll($channel_id) {
        $channel_id = absint($channel_id);
        $this->maybe_create_tables();
        $cfg = $this->get_config($channel_id);
        if (!$cfg || !$cfg['activo']) {
            return ['ok' => true, 'status' => 'disabled', 'replied' => 0];
        }
        $lock = 'omni_ig_comments_poll_lock_' . $channel_id;
        $held = (int) get_option($lock, 0);
        if ($held && $held > time() - self::POLL_LOCK_SECONDS) {
            return ['ok' => true, 'status' => 'locked', 'replied' => 0];
        }
        if ($held) delete_option($lock);
        if (!add_option($lock, time(), '', false)) {
            return ['ok' => true, 'status' => 'locked', 'replied' => 0];
        }

        try {
            $media = $this->omni->ig_graph_get($channel_id, 'me/media?fields=id,timestamp,comments_count&limit=' . self::POLL_MEDIA_LIMIT);
            if (empty($media['success'])) {
                return ['ok' => false, 'status' => 'error', 'error' => $media['error'] ?? 'No se pudo leer la lista de posts', 'replied' => 0];
            }
            $seen_key = 'omni_ig_comments_poll_seen_' . $channel_id;
            $seen = get_option($seen_key, []);
            if (!is_array($seen)) $seen = [];
            $next_seen = [];
            $summary = ['ok' => true, 'status' => 'ok', 'media_read' => 0, 'replied' => 0, 'results' => []];
            $min_media = time() - self::POLL_MEDIA_DAYS * DAY_IN_SECONDS;
            $min_comment = max($cfg['activo_desde'], time() - self::MAX_AGE_SECONDS);

            foreach ((array) ($media['data']['data'] ?? []) as $m) {
                $mid = (string) ($m['id'] ?? '');
                $count = (int) ($m['comments_count'] ?? 0);
                if ($mid === '' || strtotime((string) ($m['timestamp'] ?? '')) < $min_media) continue;
                $next_seen[$mid] = $count;
                if ($count === 0 || (isset($seen[$mid]) && (int) $seen[$mid] === $count)) continue;

                $list = $this->omni->ig_graph_get($channel_id, rawurlencode($mid)
                    . '/comments?fields=id,text,timestamp,from,username&limit=' . self::POLL_COMMENTS_LIMIT);
                $summary['media_read']++;
                if (empty($list['success'])) {
                    unset($next_seen[$mid]); // se reintenta en la próxima consulta
                    $summary['errors'][] = $list['error'] ?? 'No se pudieron leer los comentarios';
                    continue;
                }
                foreach ((array) ($list['data']['data'] ?? []) as $c) {
                    $ts = strtotime((string) ($c['timestamp'] ?? ''));
                    if (!$ts || $ts < $min_comment) continue;
                    $res = $this->process_comment($channel_id, [
                        'id'       => (string) ($c['id'] ?? ''),
                        'text'     => (string) ($c['text'] ?? ''),
                        'from_id'  => (string) ($c['from']['id'] ?? ''),
                        'username' => (string) ($c['from']['username'] ?? ($c['username'] ?? '')),
                        'media_id' => $mid,
                        'time'     => $ts,
                    ], 'poll');
                    if (in_array($res['status'], ['replied', 'partial'], true)) $summary['replied']++;
                    if ($res['status'] === 'failed') unset($next_seen[$mid]); // reintento en la próxima consulta
                    if (!in_array($res['status'], ['no_keyword', 'duplicate', 'own_account', 'too_old'], true)) {
                        $summary['results'][] = $res;
                    }
                }
            }
            update_option($seen_key, $next_seen, false);
            return $summary;
        } finally {
            delete_option($lock);
        }
    }

    /* ============================== proceso ============================== */

    /**
     * Un comentario: reglas, reserva, respuesta pública, respuesta privada y registro.
     * @param array $c id, text, from_id, username, media_id, time, account_id?
     * @return array ['status' => disabled|own_account|too_old|no_keyword|duplicate|limit|replied|partial|failed, ...]
     */
    public function process_comment($channel_id, array $c, $source) {
        $channel_id = absint($channel_id);
        $comment_id = preg_replace('/[^0-9A-Za-z_\-]/', '', (string) ($c['id'] ?? ''));
        if ($comment_id === '') return ['status' => 'invalid'];
        $out = ['comment_id' => $comment_id];

        $cfg = $this->get_config($channel_id);
        if (!$cfg || !$cfg['activo']) return $out + ['status' => 'disabled'];

        if ($this->is_own_account($channel_id, (string) ($c['from_id'] ?? ''), (string) ($c['username'] ?? ''), (string) ($c['account_id'] ?? ''))) {
            return $out + ['status' => 'own_account'];
        }
        $time = (int) ($c['time'] ?? time());
        if ($time < $cfg['activo_desde'] || $time < time() - self::MAX_AGE_SECONDS) {
            return $out + ['status' => 'too_old'];
        }
        $key = self::normalize_text($c['text'] ?? '');
        if (!isset($cfg['palabras'][$key])) return $out + ['status' => 'no_keyword'];
        $word = $cfg['palabras'][$key];
        $out['keyword'] = $key;

        $from_id = sanitize_text_field((string) ($c['from_id'] ?? ''));
        $table = $this->prefix . 'ig_comment_replies';
        $now = current_time('mysql', 1);

        // Reserva atómica: el comment_id es único. Si ya estaba, solo se reintenta una privada fallida.
        $inserted = $this->wpdb->query($this->wpdb->prepare(
            "INSERT IGNORE INTO {$table} (comment_id, channel_id, client_id, media_id, from_igsid, from_username, keyword,
                comment_text, source, status, created_at, updated_at)
             VALUES (%s, %d, %d, %s, %s, %s, %s, %s, %s, 'pending', %s, %s)",
            $comment_id, $channel_id, $this->channel_client_id($channel_id),
            sanitize_text_field((string) ($c['media_id'] ?? '')), $from_id,
            sanitize_text_field((string) ($c['username'] ?? '')), $key,
            mb_substr(sanitize_textarea_field((string) ($c['text'] ?? '')), 0, 500), $source === 'poll' ? 'poll' : 'webhook',
            $now, $now
        ));
        if ($inserted !== 1) {
            $retry = $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$table} SET dm_status = 'pending', attempts = attempts + 1, updated_at = %s
                 WHERE comment_id = %s AND dm_status = 'failed' AND attempts < %d",
                $now, $comment_id, self::MAX_ATTEMPTS
            ));
            if ($retry !== 1) return $out + ['status' => 'duplicate'];
        } else {
            // Tope por persona: las respondidas a la misma persona en las últimas 24 h.
            $recent = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE channel_id = %d AND from_igsid = %s AND comment_id <> %s
                 AND status IN ('replied','partial','failed','pending') AND created_at >= %s",
                $channel_id, $from_id, $comment_id, gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)
            ));
            if ($from_id !== '' && $recent >= $cfg['max_24h']) {
                $this->wpdb->update($table, ['status' => 'limit', 'public_status' => 'skipped', 'dm_status' => 'skipped', 'updated_at' => $now],
                    ['comment_id' => $comment_id]);
                return $out + ['status' => 'limit'];
            }
        }

        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE comment_id = %s", $comment_id));
        $errors = [];
        $upd = [];

        // 1) Respuesta pública (solo si todavía no salió).
        if ($row->public_status !== 'sent') {
            $pub = $this->omni->reply_instagram_comment($channel_id, $comment_id, $word['publica']);
            if (!empty($pub['success'])) {
                $upd['public_status'] = 'sent';
                $upd['public_reply_id'] = sanitize_text_field((string) $pub['id']);
            } else {
                $upd['public_status'] = 'failed';
                $errors[] = 'pública: ' . ($pub['error'] ?? 'error desconocido');
            }
        }
        $public_ok = ($upd['public_status'] ?? $row->public_status) === 'sent';

        // 2) Respuesta privada, ligada al comentario.
        $dm = $this->omni->send_instagram_private_reply($channel_id, $comment_id, $word['privada']);
        if (!empty($dm['success'])) {
            $upd['dm_status'] = 'sent';
            $upd['dm_message_id'] = sanitize_text_field((string) $dm['message_id']);
        } else {
            $upd['dm_status'] = 'failed';
            $errors[] = 'privada: ' . ($dm['error'] ?? 'error desconocido');
        }
        $dm_ok = $upd['dm_status'] === 'sent';

        $status = $dm_ok ? ($public_ok ? 'replied' : 'partial') : 'failed';
        $upd['status'] = $status;
        $upd['error'] = mb_substr(implode(' · ', $errors), 0, 500);
        $upd['updated_at'] = current_time('mysql', 1);

        if ($dm_ok) {
            $igsid = sanitize_text_field((string) ($dm['recipient_id'] ?: $from_id));
            $conv_id = $this->record_in_inbox($channel_id, $igsid, $c, $word, $comment_id, $upd['dm_message_id']);
            if ($conv_id) $upd['conversation_id'] = $conv_id;
        }
        $this->wpdb->update($table, $upd, ['comment_id' => $comment_id]);

        $this->omni->audit_log('ig_comment_reply', 'conversation', $upd['conversation_id'] ?? null,
            "Respuesta automática a comentario «{$word['palabra']}» de @" . sanitize_text_field((string) ($c['username'] ?? '')) . " ({$status})",
            null,
            ['channel_id' => $channel_id, 'comment_id' => $comment_id, 'keyword' => $key, 'source' => $source,
             'public_status' => $upd['public_status'] ?? $row->public_status, 'dm_status' => $upd['dm_status'], 'error' => $upd['error']],
            $this->channel_client_id($channel_id)
        );

        return $out + ['status' => $status, 'error' => $upd['error']];
    }

    /** Conversación de quien comentó (la crea en modo bot si no existe), con la nota y el privado. */
    private function record_in_inbox($channel_id, $igsid, array $c, array $word, $comment_id, $dm_message_id) {
        if ($igsid === '') return null;
        $username = sanitize_text_field((string) ($c['username'] ?? ''));
        $now = current_time('mysql', 1);
        $conv = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id, unread_count FROM {$this->prefix}conversations
             WHERE channel_id = %d AND external_contact_id = %s AND status != 'archived' ORDER BY id DESC LIMIT 1",
            $channel_id, $igsid
        ));
        $preview = mb_substr(sanitize_text_field($word['privada']), 0, 500);
        if ($conv) {
            $conv_id = (int) $conv->id;
            $this->wpdb->update($this->prefix . 'conversations', [
                'last_message_at' => $now, 'last_message_preview' => $preview,
                'unread_count' => (int) $conv->unread_count + 1,
            ], ['id' => $conv_id]);
        } else {
            $this->wpdb->insert($this->prefix . 'conversations', [
                'client_id'            => $this->channel_client_id($channel_id),
                'channel_id'           => $channel_id,
                'external_contact_id'  => $igsid,
                'contact_name'         => $username !== '' ? '@' . $username : 'Usuario Instagram ' . substr($igsid, -6),
                'channel_type'         => 'instagram',
                'status'               => 'bot',
                'last_message_at'      => $now,
                'last_message_preview' => $preview,
                'unread_count'         => 1,
            ]);
            $conv_id = (int) $this->wpdb->insert_id;
            if (!$conv_id) return null;
        }
        $meta = wp_json_encode(['origen' => 'comentario_palabra_clave', 'comment_id' => $comment_id,
            'media_id' => (string) ($c['media_id'] ?? ''), 'keyword' => $word['palabra']], JSON_UNESCAPED_UNICODE);
        $this->wpdb->insert($this->prefix . 'messages', [
            'conversation_id' => $conv_id, 'channel_type' => 'instagram', 'direction' => 'inbound',
            'sender_type' => 'system', 'sender_name' => 'Sistema', 'message_type' => 'text',
            'content' => '💬 Comentó «' . $word['palabra'] . '» en una publicación: «' . mb_substr(sanitize_textarea_field((string) ($c['text'] ?? '')), 0, 300) . '»',
            'delivery_status' => 'delivered', 'metadata_json' => $meta, 'created_at' => $now,
        ]);
        $this->wpdb->insert($this->prefix . 'messages', [
            'conversation_id' => $conv_id, 'channel_type' => 'instagram', 'direction' => 'outbound',
            'sender_type' => 'bot', 'sender_name' => 'Respuesta automática', 'message_type' => 'text',
            'content' => sanitize_textarea_field($word['privada']), 'external_message_id' => $dm_message_id,
            'delivery_status' => 'sent', 'metadata_json' => $meta, 'created_at' => $now,
        ]);
        return $conv_id;
    }

    /* ============================== apoyo ============================== */

    /** La propia cuenta: sus respuestas también llegan como comentarios y no se responden. */
    private function is_own_account($channel_id, $from_id, $username, $account_id) {
        if ($from_id !== '' && $account_id !== '' && $from_id === $account_id) return true;
        $acc = $this->account($channel_id);
        if ($from_id !== '' && in_array($from_id, $acc['ids'], true)) return true;
        return $username !== '' && $acc['username'] !== '' && strcasecmp($username, $acc['username']) === 0;
    }

    /** ids y usuario de la cuenta del canal (GET me), guardados 12 h. */
    private function account($channel_id) {
        $key = 'omni_ig_comments_account_' . $channel_id;
        $cached = get_option($key);
        if (is_array($cached) && ($cached['at'] ?? 0) > time() - self::ACCOUNT_CACHE_SECONDS) {
            return $cached;
        }
        $me = $this->omni->ig_graph_get($channel_id, 'me?fields=user_id,id,username');
        if (empty($me['success'])) {
            return is_array($cached) ? $cached : ['ids' => [], 'username' => ''];
        }
        $acc = [
            'ids'      => array_values(array_filter([(string) ($me['data']['user_id'] ?? ''), (string) ($me['data']['id'] ?? '')])),
            'username' => (string) ($me['data']['username'] ?? ''),
            'at'       => time(),
        ];
        update_option($key, $acc, false);
        return $acc;
    }

    private function channel_client_id($channel_id) {
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT client_id FROM {$this->prefix}channels WHERE id = %d", absint($channel_id)
        ));
    }

    /** Registro para el portal: quién, cuándo, qué palabra y cómo salieron las respuestas. */
    public function get_log($channel_id, $limit = 50) {
        $this->maybe_create_tables();
        $limit = max(1, min(200, (int) $limit));
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT comment_id, media_id, from_username, keyword, comment_text, source, status, public_status,
                    dm_status, attempts, error, conversation_id, created_at
             FROM {$this->prefix}ig_comment_replies WHERE channel_id = %d ORDER BY id DESC LIMIT %d",
            absint($channel_id), $limit
        ), ARRAY_A);
    }
}
