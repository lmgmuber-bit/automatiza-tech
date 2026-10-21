<?php
/**
 * Métricas de Instagram (Insights) del Portal OmniCliente.
 *
 * Lee de la Graph API de Instagram (Instagram Login, graph.instagram.com) las
 * publicaciones y las métricas de la cuenta de AutomatizaTech, guarda tomas
 * (snapshots) en la base y las entrega al portal (vista Marketing IG) y a los
 * agentes (ruta ig-metrics/* con X-IG-Metrics-Secret).
 *
 * - Solo LEE de Instagram: nunca publica ni borra. Usa el mismo token del canal
 *   que el publicador (bot_token), a través de OmnichannelController::ig_graph_get,
 *   así que el token nunca sale de este servidor.
 * - Métricas pedidas según la doc vigente de Meta (21-oct-2026): `views`
 *   reemplazó a impressions/plays/video_views (retiradas el 21-abr-2025).
 * - Sin el permiso instagram_business_manage_insights igual se guardan los datos
 *   básicos (likes, comentarios, seguidores) y el estado queda en no_permission.
 *
 * Tablas: {prefix}omnichannel_ig_posts · {prefix}omnichannel_ig_post_metrics ·
 *         {prefix}omnichannel_ig_account_daily
 */

if (!defined('ABSPATH')) {
    exit;
}

class OmniIgInsightsController {

    private $wpdb;
    private $prefix;
    /** @var OmnichannelController */
    private $omni;
    private $api_calls = 0;

    const DB_VERSION = '1';
    const TZ = 'America/Santiago';

    /** Tomas fijas por publicación: horas desde la publicación. */
    const CHECKPOINTS = ['24h' => 24, '48h' => 48, '7d' => 168, '30d' => 720];
    /** Una toma fija solo se registra si la publicación no pasó 1,5 veces su edad (si no, quedaría mal rotulada). */
    const CHECKPOINT_SLACK = 1.5;
    /** Cada cuánto se refresca la toma "latest" de una publicación de hasta 35 días. */
    const LATEST_EVERY_HOURS = 6;
    const LATEST_MAX_AGE_HOURS = 840;
    /** Cada cuánto se vuelven a pedir los días de la cuenta (Meta tarda hasta 48 h en asentar). */
    const ACCOUNT_EVERY_HOURS = 6;
    const ACCOUNT_DAYS_BACK = 3;
    /** Entre dos sincronizaciones seguidas (salvo force). */
    const MIN_SYNC_SECONDS = 120;

    const MEDIA_FIELDS = [
        'id,caption,media_type,media_product_type,timestamp,permalink,like_count,comments_count',
        'id,caption,media_type,timestamp,permalink,like_count,comments_count',
        'id,media_type,timestamp,permalink,like_count,comments_count',
    ];

    /** Métricas por tipo, de la más completa a la mínima (si Meta rechaza una métrica se baja un nivel). */
    const POST_METRICS = [
        'REELS' => [
            'views,reach,likes,comments,saved,shares,total_interactions,ig_reels_avg_watch_time,ig_reels_video_view_total_time',
            'views,reach,likes,comments,saved,shares,total_interactions',
            'reach,likes,comments,saved,shares',
        ],
        'FEED' => [
            'views,reach,likes,comments,saved,shares,total_interactions,follows,profile_visits',
            'views,reach,likes,comments,saved,shares,total_interactions',
            'reach,likes,comments,saved,shares',
        ],
        'STORY' => [
            'views,reach,replies,shares,total_interactions,follows,profile_visits',
            'views,reach,replies,shares',
        ],
    ];

    const ACCOUNT_METRICS = 'reach,views,accounts_engaged,total_interactions,likes,comments,shares,saves,replies,profile_links_taps';

    /** Nombre de la métrica en Meta → columna de ig_post_metrics. */
    const POST_COLUMNS = [
        'views' => 'views', 'reach' => 'reach', 'likes' => 'likes', 'comments' => 'comments',
        'saved' => 'saved', 'shares' => 'shares', 'total_interactions' => 'total_interactions',
        'follows' => 'follows', 'profile_visits' => 'profile_visits', 'replies' => 'replies',
        'ig_reels_avg_watch_time' => 'avg_watch_time_ms', 'ig_reels_video_view_total_time' => 'total_watch_time_ms',
    ];

    const ACCOUNT_COLUMNS = [
        'reach', 'views', 'accounts_engaged', 'total_interactions', 'likes', 'comments',
        'shares', 'saves', 'replies', 'profile_links_taps',
    ];

    /** Mensajes de estado para el portal y los agentes (sin cifras inventadas). */
    const STATUS_MESSAGES = [
        'ok'            => 'Métricas de Instagram al día.',
        'never'         => 'Todavía no se ha hecho ninguna toma de métricas.',
        'no_token'      => 'El canal de Instagram no tiene token configurado.',
        'token_invalid' => 'El token de Instagram venció o no es válido: hay que generar uno nuevo y pegarlo en Canales.',
        'no_permission' => 'El token de Instagram no tiene el permiso instagram_business_manage_insights: hay datos básicos (likes, comentarios, seguidores), pero no alcance ni reproducciones.',
        'error'         => 'La última toma de métricas falló.',
    ];

    public function __construct($omni) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'omnichannel_';
        $this->omni = $omni;
    }

    /* ============================== esquema ============================== */

    public function maybe_create_tables() {
        if (get_option('omni_ig_insights_db_version') === self::DB_VERSION) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$this->prefix}ig_posts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            channel_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            media_id VARCHAR(40) NOT NULL DEFAULT '',
            media_type VARCHAR(30) NOT NULL DEFAULT '',
            media_product_type VARCHAR(20) NOT NULL DEFAULT '',
            caption TEXT NULL,
            permalink VARCHAR(255) NOT NULL DEFAULT '',
            posted_at DATETIME NULL,
            like_count INT NULL,
            comments_count INT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'published',
            insights_state VARCHAR(30) NOT NULL DEFAULT 'pending',
            insights_error VARCHAR(255) NOT NULL DEFAULT '',
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            last_insights_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY channel_media (channel_id,media_id),
            KEY channel_posted (channel_id,posted_at)
        ) $charset;");

        dbDelta("CREATE TABLE {$this->prefix}ig_post_metrics (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            checkpoint VARCHAR(10) NOT NULL DEFAULT 'latest',
            taken_at DATETIME NOT NULL,
            age_hours DECIMAL(8,1) NOT NULL DEFAULT 0,
            views BIGINT NULL,
            reach BIGINT NULL,
            likes INT NULL,
            comments INT NULL,
            saved INT NULL,
            shares INT NULL,
            total_interactions INT NULL,
            follows INT NULL,
            profile_visits INT NULL,
            replies INT NULL,
            avg_watch_time_ms BIGINT NULL,
            total_watch_time_ms BIGINT NULL,
            raw TEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY post_checkpoint (post_id,checkpoint)
        ) $charset;");

        dbDelta("CREATE TABLE {$this->prefix}ig_account_daily (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            channel_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            day DATE NOT NULL,
            followers_count INT NULL,
            follows_count INT NULL,
            media_count INT NULL,
            snapshot_at DATETIME NULL,
            reach BIGINT NULL,
            views BIGINT NULL,
            accounts_engaged BIGINT NULL,
            total_interactions BIGINT NULL,
            likes INT NULL,
            comments INT NULL,
            shares INT NULL,
            saves INT NULL,
            replies INT NULL,
            profile_links_taps INT NULL,
            followers_gained INT NULL,
            insights_at DATETIME NULL,
            raw TEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY channel_day (channel_id,day)
        ) $charset;");

        update_option('omni_ig_insights_db_version', self::DB_VERSION);
    }

    /* ============================== canal ============================== */

    /** Canal de Instagram de AutomatizaTech (el mismo que publica el Reel Diario y el Calendario AT). */
    public static function default_channel_id() {
        if (defined('AT_IG_METRICS_CHANNEL_ID') && (int) AT_IG_METRICS_CHANNEL_ID > 0) {
            return (int) AT_IG_METRICS_CHANNEL_ID;
        }
        return defined('REEL_DIARIO_CHANNEL_ID') ? (int) REEL_DIARIO_CHANNEL_ID : 0;
    }

    /** null si el canal existe, es de Instagram y tiene token; si no, el estado que corresponde. */
    private function channel_problem($channel_id) {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT channel_type, bot_token FROM {$this->prefix}channels WHERE id = %d",
            absint($channel_id)
        ));
        if (!$row) return ['status' => 'error', 'message' => 'Canal no encontrado'];
        if ($row->channel_type !== 'instagram') return ['status' => 'error', 'message' => 'El canal no es de Instagram'];
        if (empty($row->bot_token)) return ['status' => 'no_token', 'message' => self::STATUS_MESSAGES['no_token']];
        return null;
    }

    private function graph_get($channel_id, $path) {
        $this->api_calls++;
        return $this->omni->ig_graph_get($channel_id, $path);
    }

    /* ============================== sincronización ============================== */

    /**
     * Toma de métricas. La llama el cron de n8n (cada hora) y el botón «Actualizar»
     * del portal. Hace pocas llamadas: perfil, lista de medios, historias, las
     * publicaciones que tienen una toma pendiente y, cada 6 h, los últimos 3 días de la cuenta.
     *
     * @return array resumen: ok, status, posts_seen, snapshots, account_days, api_calls, errors
     */
    public function sync($channel_id, $force = false) {
        $channel_id = absint($channel_id);
        $this->maybe_create_tables();
        $state = $this->get_state($channel_id);
        $now = time();

        // Vale también con force: protege la cuota de Meta de clics y agentes repetidos.
        if (!empty($state['last_sync_at']) && $now - strtotime($state['last_sync_at'] . ' UTC') < self::MIN_SYNC_SECONDS) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'Hubo una toma hace menos de ' . self::MIN_SYNC_SECONDS . ' s', 'last_summary' => $state['last_summary'] ?? null];
        }
        $lock = 'omni_ig_insights_lock_' . $channel_id;
        if (get_transient($lock)) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'Hay otra toma en curso'];
        }
        set_transient($lock, 1, 120);
        $this->api_calls = 0;

        $summary = ['ok' => false, 'status' => 'error', 'posts_seen' => 0, 'snapshots' => 0, 'account_days' => 0, 'api_calls' => 0, 'errors' => []];
        try {
            $summary = $this->run_sync($channel_id, $force, $summary);
        } finally {
            delete_transient($lock);
        }
        $summary['api_calls'] = $this->api_calls;

        $state['status'] = $summary['status'];
        $state['message'] = $summary['message'] ?? (self::STATUS_MESSAGES[$summary['status']] ?? '');
        $state['code'] = $summary['code'] ?? 0;
        $state['last_sync_at'] = gmdate('Y-m-d H:i:s', $now);
        $state['last_sync_ok'] = !empty($summary['ok']);
        $state['last_summary'] = $summary;
        update_option('omni_ig_insights_state_' . $channel_id, $state, false);
        return $summary;
    }

    private function run_sync($channel_id, $force, $summary) {
        $problem = $this->channel_problem($channel_id);
        if ($problem) {
            return array_merge($summary, $problem);
        }

        // 1) Perfil: seguidores, seguidos y cantidad de publicaciones (permiso básico).
        $me = $this->graph_get($channel_id, 'me?fields=id,user_id,username,followers_count,follows_count,media_count');
        if (empty($me['success'])) {
            $cls = $this->classify_error($me);
            return array_merge($summary, $cls, ['errors' => ['perfil: ' . $cls['message']]]);
        }
        $profile = $me['data'];
        update_option('omni_ig_insights_profile_' . $channel_id, [
            'ig_id'           => (string) ($profile['user_id'] ?? ($profile['id'] ?? '')),
            'username'        => sanitize_text_field($profile['username'] ?? ''),
            'followers_count' => isset($profile['followers_count']) ? (int) $profile['followers_count'] : null,
            'follows_count'   => isset($profile['follows_count']) ? (int) $profile['follows_count'] : null,
            'media_count'     => isset($profile['media_count']) ? (int) $profile['media_count'] : null,
            'snapshot_at'     => gmdate('Y-m-d H:i:s'),
        ], false);
        $this->upsert_account_day($channel_id, $this->today(), [
            'followers_count' => isset($profile['followers_count']) ? (int) $profile['followers_count'] : null,
            'follows_count'   => isset($profile['follows_count']) ? (int) $profile['follows_count'] : null,
            'media_count'     => isset($profile['media_count']) ? (int) $profile['media_count'] : null,
            'snapshot_at'     => gmdate('Y-m-d H:i:s'),
        ]);

        // 2) Publicaciones (feed y reels) y 3) historias vigentes.
        $listed = $this->sync_media_list($channel_id, $summary);
        $this->sync_stories($channel_id);

        // 4) Métricas por publicación (tomas fijas 24h/48h/7d/30d y la última).
        $perm = null;   // primer error de permiso o token que aparezca
        $insights_ok = false;
        foreach ($this->posts_due($channel_id, $force) as $post) {
            $res = $this->snapshot_post($channel_id, $post);
            if ($res['taken'] > 0) {
                $summary['snapshots'] += $res['taken'];
                $insights_ok = true;
            }
            if (!empty($res['error'])) {
                if (in_array($res['error']['status'], ['no_permission', 'token_invalid'], true)) {
                    $perm = $perm ?: $res['error'];
                    break; // con un token sin permiso, las demás darán el mismo error
                }
                $summary['errors'][] = 'media ' . $post->media_id . ': ' . $res['error']['message'];
            }
        }

        // 5) Cuenta: últimos días completos (hora de Chile).
        if (!$perm) {
            $acc = $this->sync_account_days($channel_id, $force);
            $summary['account_days'] = $acc['days'];
            if ($acc['days'] > 0) $insights_ok = true;
            if (!empty($acc['error'])) {
                if (in_array($acc['error']['status'], ['no_permission', 'token_invalid'], true)) {
                    $perm = $acc['error'];
                } else {
                    $summary['errors'][] = 'cuenta: ' . $acc['error']['message'];
                }
            }
        }

        $summary['posts_seen'] = $listed;
        if ($perm) {
            return array_merge($summary, ['ok' => true, 'status' => $perm['status'], 'message' => $perm['message'], 'code' => $perm['code']]);
        }
        if ($insights_ok) {
            $status = 'ok';
        } elseif (!empty($summary['errors'])) {
            $status = 'error';
        } else {
            // Pasada sin nada pendiente: no se probó el permiso, se conserva lo que se sabía.
            $prev = $this->get_state($channel_id)['status'] ?? '';
            $status = $prev === 'no_permission' ? 'no_permission' : 'ok';
        }
        return array_merge($summary, [
            'ok'      => $status !== 'error',
            'status'  => $status,
            'message' => self::STATUS_MESSAGES[$status],
        ]);
    }

    /** Lista /me/media (hasta 3 páginas o 35 días atrás) y actualiza ig_posts. Devuelve cuántas vio. */
    private function sync_media_list($channel_id, &$summary) {
        $items = null;
        $fields_used = '';
        foreach (self::MEDIA_FIELDS as $fields) {
            $res = $this->graph_get($channel_id, 'me/media?fields=' . $fields . '&limit=50');
            if (!empty($res['success'])) {
                $items = $res;
                $fields_used = $fields;
                break;
            }
            if ((int) ($res['fb_error']['code'] ?? 0) !== 100) {
                $summary['errors'][] = 'lista de medios: ' . $this->classify_error($res)['message'];
                return 0;
            }
        }
        if ($items === null) {
            $summary['errors'][] = 'lista de medios: Meta rechazó los campos pedidos';
            return 0;
        }

        $seen = [];
        $oldest = null;
        $page = $items;
        for ($p = 0; $p < 3; $p++) {
            foreach (($page['data']['data'] ?? []) as $m) {
                $id = (string) ($m['id'] ?? '');
                if ($id === '') continue;
                $posted = $this->ig_time_to_mysql($m['timestamp'] ?? '');
                $this->upsert_post($channel_id, $m, $posted);
                $seen[] = $id;
                if ($posted && ($oldest === null || $posted < $oldest)) $oldest = $posted;
            }
            $after = $page['data']['paging']['cursors']['after'] ?? '';
            $has_next = !empty($page['data']['paging']['next']);
            if (!$has_next || $after === '' || ($oldest && strtotime($oldest . ' UTC') < time() - 35 * DAY_IN_SECONDS)) {
                break;
            }
            $page = $this->graph_get($channel_id, 'me/media?fields=' . $fields_used . '&limit=50&after=' . rawurlencode($after));
            if (empty($page['success'])) break;
        }

        // Publicación que estaba y ya no aparece en el tramo listado → eliminada en Instagram.
        if ($seen && $oldest) {
            $placeholders = implode(',', array_fill(0, count($seen), '%s'));
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->prefix}ig_posts SET status = 'deleted'
                 WHERE channel_id = %d AND status = 'published' AND media_product_type <> 'STORY'
                   AND posted_at >= %s AND media_id NOT IN ($placeholders)",
                array_merge([$channel_id, $oldest], $seen)
            ));
        }
        return count($seen);
    }

    /** Historias vigentes (duran 24 h; sus métricas también). Errores ignorados: es un extra. */
    private function sync_stories($channel_id) {
        $res = $this->graph_get($channel_id, 'me/stories?fields=id,media_type,timestamp,permalink');
        if (empty($res['success'])) return;
        foreach (($res['data']['data'] ?? []) as $m) {
            if (empty($m['id'])) continue;
            $m['media_product_type'] = 'STORY';
            $this->upsert_post($channel_id, $m, $this->ig_time_to_mysql($m['timestamp'] ?? ''));
        }
        // Historias de más de 24 h: ya no tienen métricas en Meta.
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->prefix}ig_posts SET status = 'expired'
             WHERE channel_id = %d AND media_product_type = 'STORY' AND status = 'published' AND posted_at < %s",
            $channel_id, gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)
        ));
    }

    private function upsert_post($channel_id, array $m, $posted) {
        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'channel_id'         => $channel_id,
            'media_id'           => sanitize_text_field((string) $m['id']),
            'media_type'         => sanitize_text_field($m['media_type'] ?? ''),
            'media_product_type' => sanitize_text_field($m['media_product_type'] ?? ''),
            'permalink'          => esc_url_raw($m['permalink'] ?? ''),
            'posted_at'          => $posted,
            'first_seen_at'      => $now,
            'last_seen_at'       => $now,
            'status'             => 'published',
        ];
        $update = ['media_type', 'permalink', 'posted_at', 'last_seen_at', 'status'];
        if (array_key_exists('caption', $m)) {
            $data['caption'] = sanitize_textarea_field((string) $m['caption']);
            $update[] = 'caption';
        }
        if ($data['media_product_type'] !== '') $update[] = 'media_product_type';
        foreach (['like_count', 'comments_count'] as $k) {
            if (isset($m[$k])) {
                $data[$k] = (int) $m[$k];
                $update[] = $k;
            }
        }
        $this->upsert($this->prefix . 'ig_posts', $data, $update);
    }

    /** Publicaciones que necesitan una toma ahora. */
    private function posts_due($channel_id, $force) {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}ig_posts
             WHERE channel_id = %d AND status = 'published' AND posted_at IS NOT NULL AND posted_at >= %s
             ORDER BY posted_at DESC",
            $channel_id, gmdate('Y-m-d H:i:s', time() - self::LATEST_MAX_AGE_HOURS * HOUR_IN_SECONDS)
        ));
        if (!$rows) return [];
        $ids = array_map(function ($r) { return (int) $r->id; }, $rows);
        $taken = $this->taken_checkpoints($ids);
        $due = [];
        foreach ($rows as $r) {
            $r->due = $this->due_checkpoints($r, $taken[(int) $r->id] ?? [], $force);
            if ($r->due) $due[] = $r;
        }
        return $due;
    }

    /** [post_id => [checkpoint => taken_at]] */
    private function taken_checkpoints(array $ids) {
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT post_id, checkpoint, taken_at FROM {$this->prefix}ig_post_metrics WHERE post_id IN ($placeholders)",
            $ids
        ));
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->post_id][$r->checkpoint] = $r->taken_at;
        }
        return $out;
    }

    /**
     * Qué tomas faltan para una publicación. Pública para las pruebas.
     * Regla: una toma fija se toma al cumplir su edad y solo hasta 1,5 veces esa edad
     * (si la publicación se descubrió tarde, esa toma queda sin registrar en vez de mal rotulada).
     * La "latest" se refresca cada 6 h (las historias, en cada pasada) o con force.
     *
     * @return string[] checkpoints a guardar con la misma lectura
     */
    public function due_checkpoints($post, array $taken, $force = false, $now = null) {
        $now = $now ?: time();
        $age_h = ($now - strtotime($post->posted_at . ' UTC')) / HOUR_IN_SECONDS;
        $due = [];
        if ($post->media_product_type !== 'STORY') {
            foreach (self::CHECKPOINTS as $cp => $hours) {
                if (!isset($taken[$cp]) && $age_h >= $hours && $age_h <= $hours * self::CHECKPOINT_SLACK) {
                    $due[] = $cp;
                }
            }
        }
        $latest_at = isset($taken['latest']) ? strtotime($taken['latest'] . ' UTC') : 0;
        $every = $post->media_product_type === 'STORY' ? 0 : self::LATEST_EVERY_HOURS * HOUR_IN_SECONDS;
        if ($force || $due || !$latest_at || $now - $latest_at >= $every) {
            $due[] = 'latest';
        }
        return array_values(array_unique($due));
    }

    /** Una lectura de /{media}/insights, guardada en cada toma pendiente. */
    private function snapshot_post($channel_id, $post) {
        $kind = $this->post_kind($post);
        $res = null;
        foreach (self::POST_METRICS[$kind] as $metrics) {
            $res = $this->graph_get($channel_id, rawurlencode($post->media_id) . '/insights?metric=' . $metrics);
            if (!empty($res['success'])) break;
            $code = (int) ($res['fb_error']['code'] ?? 0);
            $msg = strtolower((string) ($res['error'] ?? ''));
            // Solo se baja de nivel si Meta rechazó una métrica; cualquier otro error corta.
            if (!($code === 100 && strpos($msg, 'metric') !== false)) break;
        }

        $now = gmdate('Y-m-d H:i:s');
        if (empty($res['success'])) {
            $cls = $this->classify_error($res);
            $state = $cls['status'];
            $code = (int) ($res['fb_error']['code'] ?? 0);
            $sub = (int) ($res['fb_error']['error_subcode'] ?? 0);
            if ($code === 10 && stripos((string) ($res['error'] ?? ''), 'not enough viewers') !== false) {
                $state = 'not_enough_viewers';
            } elseif ($code === 100 && ($sub === 33 || stripos((string) ($res['error'] ?? ''), 'does not exist') !== false)) {
                $this->wpdb->update($this->prefix . 'ig_posts', ['status' => 'deleted'], ['id' => (int) $post->id]);
                $state = 'deleted';
            }
            $this->wpdb->update($this->prefix . 'ig_posts', [
                'insights_state' => $state,
                'insights_error' => $cls['message'],
                'last_insights_at' => $now,
            ], ['id' => (int) $post->id]);
            if (in_array($state, ['not_enough_viewers', 'deleted'], true)) {
                return ['taken' => 0];
            }
            return ['taken' => 0, 'error' => $cls];
        }

        $values = self::parse_insights($res['data']);
        $row = [
            'post_id'   => (int) $post->id,
            'taken_at'  => $now,
            'age_hours' => round((time() - strtotime($post->posted_at . ' UTC')) / HOUR_IN_SECONDS, 1),
            'raw'       => wp_json_encode($values),
        ];
        foreach (self::POST_COLUMNS as $metric => $col) {
            $row[$col] = array_key_exists($metric, $values) ? (int) round($values[$metric]) : null;
        }
        $update_cols = array_merge(['taken_at', 'age_hours', 'raw'], array_values(self::POST_COLUMNS));
        foreach ($post->due as $cp) {
            $this->upsert($this->prefix . 'ig_post_metrics', array_merge($row, ['checkpoint' => $cp]), $update_cols);
        }
        $this->wpdb->update($this->prefix . 'ig_posts', [
            'insights_state' => 'ok',
            'insights_error' => '',
            'last_insights_at' => $now,
        ], ['id' => (int) $post->id]);
        return ['taken' => count($post->due)];
    }

    /** REELS / FEED / STORY según lo que dijo Meta. Un VIDEO sin tipo de producto se trata como Reel (desde 2022 los videos del feed son Reels). */
    public function post_kind($post) {
        $pt = strtoupper((string) ($post->media_product_type ?? ''));
        if ($pt === 'STORY') return 'STORY';
        if ($pt === 'REELS') return 'REELS';
        if ($pt === 'FEED' || $pt === 'AD') return 'FEED';
        return strtoupper((string) ($post->media_type ?? '')) === 'VIDEO' ? 'REELS' : 'FEED';
    }

    /** Días completos de la cuenta (ayer y dos más), cada 6 h salvo force. */
    private function sync_account_days($channel_id, $force) {
        $out = ['days' => 0];
        $tz = new DateTimeZone(self::TZ);
        $today = new DateTimeImmutable('today', $tz);
        for ($i = 1; $i <= self::ACCOUNT_DAYS_BACK; $i++) {
            $day = $today->modify("-{$i} day");
            $ymd = $day->format('Y-m-d');
            $prev = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT insights_at FROM {$this->prefix}ig_account_daily WHERE channel_id = %d AND day = %s",
                $channel_id, $ymd
            ));
            if (!$force && $prev && time() - strtotime($prev . ' UTC') < self::ACCOUNT_EVERY_HOURS * HOUR_IN_SECONDS) {
                continue;
            }
            $since = $day->getTimestamp();
            $until = $day->modify('+1 day')->getTimestamp();
            $res = $this->graph_get($channel_id, 'me/insights?metric=' . self::ACCOUNT_METRICS
                . '&period=day&metric_type=total_value&since=' . $since . '&until=' . $until);
            if (empty($res['success'])) {
                $out['error'] = $this->classify_error($res);
                return $out;
            }
            $values = self::parse_insights($res['data']);
            $data = ['insights_at' => gmdate('Y-m-d H:i:s'), 'raw' => wp_json_encode($values)];
            foreach (self::ACCOUNT_COLUMNS as $col) {
                $data[$col] = array_key_exists($col, $values) ? (int) round($values[$col]) : null;
            }
            $this->upsert_account_day($channel_id, $ymd, $data);
            $out['days']++;
        }

        // Seguidores nuevos por día: Meta solo lo da a cuentas con 100 o más seguidores.
        $profile = get_option('omni_ig_insights_profile_' . $channel_id, []);
        if ($out['days'] > 0 && (int) ($profile['followers_count'] ?? 0) >= 100) {
            $since = $today->modify('-' . self::ACCOUNT_DAYS_BACK . ' day')->getTimestamp();
            $res = $this->graph_get($channel_id, 'me/insights?metric=follower_count&period=day&since=' . $since . '&until=' . $today->getTimestamp());
            if (!empty($res['success'])) {
                foreach (($res['data']['data'] ?? []) as $item) {
                    foreach (($item['values'] ?? []) as $v) {
                        if (!isset($v['value'], $v['end_time'])) continue;
                        // end_time marca el fin del día medido: el día es el segundo anterior.
                        $d = (new DateTimeImmutable('@' . (strtotime($v['end_time']) - 1)))->setTimezone($tz)->format('Y-m-d');
                        $this->upsert_account_day($channel_id, $d, ['followers_gained' => (int) $v['value']]);
                    }
                }
            }
        }
        return $out;
    }

    private function upsert_account_day($channel_id, $day, array $data) {
        $row = array_merge(['channel_id' => $channel_id, 'day' => $day], $data);
        $this->upsert($this->prefix . 'ig_account_daily', $row, array_keys($data));
    }

    /* ============================== lectura ============================== */

    public function get_state($channel_id) {
        $s = get_option('omni_ig_insights_state_' . absint($channel_id), []);
        if (!is_array($s) || empty($s)) {
            $s = ['status' => 'never', 'message' => self::STATUS_MESSAGES['never'], 'code' => 0, 'last_sync_at' => null, 'last_sync_ok' => false];
        }
        return $s;
    }

    /**
     * Resumen para el portal y los agentes. Formato documentado en
     * Docs/MASTER/06_PORTAL_OMNICLIENTE_FRONTEND.md (sección «Métricas IG»).
     */
    public function get_overview($channel_id, $days = 30, $posts_limit = 30) {
        $channel_id = absint($channel_id);
        $days = min(max(absint($days), 1), 90);
        $posts_limit = min(max(absint($posts_limit), 1), 100);
        $this->maybe_create_tables();

        $profile = get_option('omni_ig_insights_profile_' . $channel_id, []);
        $tz = new DateTimeZone(self::TZ);
        $today = new DateTimeImmutable('today', $tz);
        $from = $today->modify("-{$days} day")->format('Y-m-d');
        $to = $today->modify('-1 day')->format('Y-m-d');

        $daily_rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}ig_account_daily WHERE channel_id = %d AND day BETWEEN %s AND %s ORDER BY day ASC",
            $channel_id, $from, $to
        ));
        $daily = [];
        $totals = array_fill_keys(self::ACCOUNT_COLUMNS, null);
        $with_data = 0;
        $gained = null;
        foreach ($daily_rows as $r) {
            $item = ['day' => $r->day];
            foreach (self::ACCOUNT_COLUMNS as $col) {
                $item[$col] = self::int_or_null($r->$col);
                if ($item[$col] !== null) $totals[$col] = ($totals[$col] ?? 0) + $item[$col];
            }
            $item['followers_count'] = self::int_or_null($r->followers_count);
            $item['followers_gained'] = self::int_or_null($r->followers_gained);
            if ($item['followers_gained'] !== null) $gained = ($gained ?? 0) + $item['followers_gained'];
            if ($r->insights_at) $with_data++;
            $daily[] = $item;
        }
        // Cambio neto de seguidores entre la primera foto del periodo y la actual.
        $first_followers = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT followers_count FROM {$this->prefix}ig_account_daily
             WHERE channel_id = %d AND day >= %s AND followers_count IS NOT NULL ORDER BY day ASC LIMIT 1",
            $channel_id, $from
        ));
        $current_followers = isset($profile['followers_count']) ? $profile['followers_count'] : null;
        $net = ($first_followers !== null && $current_followers !== null) ? (int) $current_followers - (int) $first_followers : null;

        return [
            'ok'           => true,
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'channel_id'   => $channel_id,
            'account'      => [
                'ig_user_id'      => $profile['ig_id'] ?? null,
                'username'        => $profile['username'] ?? null,
                'followers_count' => $current_followers,
                'follows_count'   => $profile['follows_count'] ?? null,
                'media_count'     => $profile['media_count'] ?? null,
                'snapshot_at'     => self::iso($profile['snapshot_at'] ?? null),
            ],
            'insights_status' => $this->get_status($channel_id),
            'period' => [
                'days'                  => $days,
                'from'                  => $from,
                'to'                    => $to,
                'days_with_data'        => $with_data,
                'totals'                => $totals,
                'followers_gained'      => $gained,
                'followers_net_change'  => $net,
                'note'                  => 'reach y accounts_engaged son sumas de valores diarios: una cuenta que vio contenido dos días cuenta dos veces.',
            ],
            'daily' => $daily,
            'posts' => $this->list_posts($channel_id, $posts_limit),
        ];
    }

    /** Estado de la última toma, tal como lo ven el portal y los agentes. */
    public function get_status($channel_id) {
        $state = $this->get_state($channel_id);
        return [
            'status'       => $state['status'] ?? 'never',
            'message'      => $state['message'] ?? '',
            'code'         => (int) ($state['code'] ?? 0),
            'last_sync_at' => self::iso($state['last_sync_at'] ?? null),
            'last_sync_ok' => !empty($state['last_sync_ok']),
            'last_errors'  => array_slice((array) ($state['last_summary']['errors'] ?? []), 0, 5),
        ];
    }

    /** Publicaciones más recientes con sus tomas. */
    public function get_posts($channel_id, $limit = 30) {
        $this->maybe_create_tables();
        return $this->list_posts(absint($channel_id), min(max(absint($limit), 1), 100));
    }

    /** Una publicación con todas sus tomas, o null si no existe. */
    public function get_post($channel_id, $media_id) {
        $this->maybe_create_tables();
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}ig_posts WHERE channel_id = %d AND media_id = %s",
            absint($channel_id), sanitize_text_field($media_id)
        ));
        if (!$row) return null;
        $metrics = $this->metrics_by_post([(int) $row->id]);
        return $this->format_post($row, $metrics[(int) $row->id] ?? []);
    }

    private function list_posts($channel_id, $limit) {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}ig_posts WHERE channel_id = %d ORDER BY posted_at DESC, id DESC LIMIT %d",
            $channel_id, $limit
        ));
        if (!$rows) return [];
        $metrics = $this->metrics_by_post(array_map(function ($r) { return (int) $r->id; }, $rows));
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->format_post($r, $metrics[(int) $r->id] ?? []);
        }
        return $out;
    }

    private function metrics_by_post(array $ids) {
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}ig_post_metrics WHERE post_id IN ($placeholders)",
            $ids
        ));
        $out = [];
        foreach ($rows as $m) {
            $out[(int) $m->post_id][$m->checkpoint] = $m;
        }
        return $out;
    }

    private function format_post($r, array $metrics) {
        $checkpoints = [];
        foreach (array_keys(self::CHECKPOINTS) as $cp) {
            $checkpoints[$cp] = isset($metrics[$cp]) ? $this->format_metrics($metrics[$cp]) : null;
        }
        return [
            'media_id'           => $r->media_id,
            'posted_at'          => self::iso($r->posted_at),
            'media_type'         => $r->media_type,
            'media_product_type' => $r->media_product_type !== '' ? $r->media_product_type : null,
            'kind'               => $this->post_kind($r),
            'caption'            => $r->caption,
            'permalink'          => $r->permalink,
            'status'             => $r->status,
            'like_count'         => self::int_or_null($r->like_count),
            'comments_count'     => self::int_or_null($r->comments_count),
            'insights_state'     => $r->insights_state,
            'insights_error'     => $r->insights_error !== '' ? $r->insights_error : null,
            'last_seen_at'       => self::iso($r->last_seen_at),
            'latest'             => isset($metrics['latest']) ? $this->format_metrics($metrics['latest']) : null,
            'checkpoints'        => $checkpoints,
        ];
    }

    private function format_metrics($m) {
        $out = ['taken_at' => self::iso($m->taken_at), 'age_hours' => (float) $m->age_hours];
        foreach (self::POST_COLUMNS as $col) {
            $out[$col] = self::int_or_null($m->$col);
        }
        return $out;
    }

    /* ============================== utilidades ============================== */

    /**
     * Respuesta de /insights → [métrica => valor]. Acepta los dos formatos de Meta:
     * total_value.value (cuenta con metric_type=total_value) y values[0].value (medios).
     */
    public static function parse_insights($data) {
        $out = [];
        foreach ((array) ($data['data'] ?? []) as $item) {
            $name = (string) ($item['name'] ?? '');
            if ($name === '') continue;
            if (isset($item['total_value']['value']) && is_numeric($item['total_value']['value'])) {
                $out[$name] = $item['total_value']['value'] + 0;
            } elseif (isset($item['values'][0]['value']) && is_numeric($item['values'][0]['value'])) {
                $out[$name] = $item['values'][0]['value'] + 0;
            }
        }
        return $out;
    }

    /**
     * Error de la Graph API → estado para el portal. Público para las pruebas.
     * 190 = token vencido o inválido; 10 / 200 o mensaje de permiso = falta el permiso de insights.
     */
    public static function classify_error($res) {
        $code = (int) ($res['fb_error']['code'] ?? 0);
        $msg = (string) ($res['error'] ?? 'Error desconocido');
        $clean = self::clean_message($msg);
        if ($code === 190) {
            return ['status' => 'token_invalid', 'code' => $code, 'message' => self::STATUS_MESSAGES['token_invalid'] . ' (Meta: ' . $clean . ')'];
        }
        $is_perm = $code === 200 || ($code === 10 && stripos($msg, 'not enough viewers') === false) || stripos($msg, 'permission') !== false;
        if ($is_perm) {
            return ['status' => 'no_permission', 'code' => $code, 'message' => self::STATUS_MESSAGES['no_permission'] . ' (Meta: ' . $clean . ')'];
        }
        return ['status' => 'error', 'code' => $code, 'message' => $clean];
    }

    /** Recorta el mensaje y tacha cualquier cadena larga tipo token por si Meta la devolviera. */
    private static function clean_message($msg) {
        $msg = preg_replace('/[A-Za-z0-9_\-\.]{40,}/', '[oculto]', (string) $msg);
        return mb_substr(sanitize_text_field($msg), 0, 200);
    }

    /** INSERT … ON DUPLICATE KEY UPDATE que respeta NULL (prepare con %d convertiría NULL en 0). */
    private function upsert($table, array $data, array $update_cols) {
        $cols = [];
        $vals = [];
        foreach ($data as $col => $val) {
            $cols[] = '`' . $col . '`';
            if ($val === null) {
                $vals[] = 'NULL';
            } elseif (is_int($val)) {
                $vals[] = $this->wpdb->prepare('%d', $val);
            } elseif (is_float($val)) {
                $vals[] = $this->wpdb->prepare('%f', $val);
            } else {
                $vals[] = $this->wpdb->prepare('%s', $val);
            }
        }
        $updates = [];
        foreach (array_unique($update_cols) as $col) {
            if (array_key_exists($col, $data)) $updates[] = "`$col` = VALUES(`$col`)";
        }
        $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
        if ($updates) $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        return $this->wpdb->query($sql);
    }

    private function today() {
        return (new DateTimeImmutable('today', new DateTimeZone(self::TZ)))->format('Y-m-d');
    }

    /** "2026-10-21T15:30:12+0000" → "2026-10-21 15:30:12" (UTC) o null. */
    private function ig_time_to_mysql($ts) {
        $t = strtotime((string) $ts);
        return $t ? gmdate('Y-m-d H:i:s', $t) : null;
    }

    private static function iso($mysql_utc) {
        return $mysql_utc ? str_replace(' ', 'T', $mysql_utc) . 'Z' : null;
    }

    private static function int_or_null($v) {
        return $v === null || $v === '' ? null : (int) $v;
    }

    public function api_calls() {
        return $this->api_calls;
    }
}
