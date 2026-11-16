<?php
/**
 * Anuncios de Meta (Portal OmniCliente → Marketing IG → Anuncios).
 *
 * Lee de la Marketing API (graph.facebook.com, permiso ads_read) los anuncios de la
 * cuenta publicitaria de AutomatizaTech y sus resultados: gasto, impresiones, alcance,
 * reproducciones y conversaciones iniciadas por mensaje. Solo hace GET: no crea, pausa
 * ni cambia anuncios ni presupuestos.
 *
 * A diferencia de Métricas IG no guarda tomas en tablas: Meta conserva los resultados de
 * cada anuncio (date_preset=maximum, hasta 37 meses), así que basta una copia en caché de
 * 30 minutos (opción omni_meta_ads_cache). Si una lectura falla, se sigue mostrando la
 * última buena con el estado del fallo.
 *
 * Configuración en wp-config.php (los valores nunca van al repo):
 *   AT_META_ADS_TOKEN       token con ads_read sobre la cuenta (usuario del sistema o de Luis)
 *   AT_META_AD_ACCOUNT_ID   id de la cuenta publicitaria, con o sin el prefijo act_
 *   AT_META_ADS_GRAPH_VERSION (opcional) versión de la Graph API; por defecto la de CAPI
 *
 * Guía: Docs/OMNICLIENTE-METRICAS-IG.md §11.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OmniMetaAdsController {

    const CACHE_OPTION = 'omni_meta_ads_cache';
    const COOLDOWN_OPTION = 'omni_meta_ads_cooldown';
    const LOCK_OPTION = 'omni_meta_ads_lock';
    const CACHE_SECONDS = 1800;
    /** El botón del portal fuerza la lectura, pero no más de una vez cada 2 minutos. */
    const FORCE_MIN_SECONDS = 120;
    const LOCK_SECONDS = 60;
    const RATE_LIMIT_COOLDOWN = 3600;
    /** 4 / 17 / 32 / 613 = límites generales; 80000 y 80004 = límites de la Marketing API. */
    const RATE_LIMIT_CODES = [4, 17, 32, 613, 80000, 80004];
    const DAILY_DAYS = 30;
    const DEFAULT_GRAPH_VERSION = 'v25.0';
    const TZ = 'America/Santiago';

    const ACCOUNT_FIELDS = 'name,currency,account_status,timezone_name';
    const AD_FIELDS = 'id,name,effective_status,created_time,adset{start_time,end_time},creative{title,body,thumbnail_url}';
    const INSIGHT_FIELDS = 'ad_id,spend,impressions,reach,frequency,cpm,clicks,actions,cost_per_action_type,video_thruplay_watched_actions,date_start,date_stop';
    /** Juego más chico si Meta rechaza un campo (pasa al cambiar de versión). */
    const INSIGHT_FIELDS_MIN = 'ad_id,spend,impressions,reach,actions,cost_per_action_type,date_start,date_stop';
    const DAILY_FIELDS = 'spend,impressions,reach,actions,date_start';

    /** Tipos de acción de Meta que se muestran (los demás se ignoran). */
    const ACTION_KEYS = [
        'conversations' => 'onsite_conversion.messaging_conversation_started_7d',
        'video_views'   => 'video_view',
        'link_clicks'   => 'link_click',
        'engagement'    => 'post_engagement',
    ];

    const STATUS_MESSAGES = [
        'ok'            => 'Anuncios al día.',
        'never'         => 'Aún no se han leído los anuncios.',
        'no_config'     => 'Falta configurar la conexión con la cuenta publicitaria (AT_META_ADS_TOKEN y AT_META_AD_ACCOUNT_ID en wp-config.php).',
        'no_permission' => 'El token no tiene permiso para leer la cuenta publicitaria (hace falta ads_read sobre AutomatizaTech Ads).',
        'token_invalid' => 'El token de la cuenta publicitaria venció o no es válido.',
        'rate_limited'  => 'Meta pidió bajar el ritmo de llamadas: se reintenta en una hora.',
        'error'         => 'No se pudieron leer los anuncios.',
    ];

    private $api_calls = 0;

    /* ============================== configuración ============================== */

    private static function token() {
        return defined('AT_META_ADS_TOKEN') ? trim((string) AT_META_ADS_TOKEN) : '';
    }

    /** Solo dígitos: acepta «act_123» o «123». */
    public static function account_id() {
        $raw = defined('AT_META_AD_ACCOUNT_ID') ? (string) AT_META_AD_ACCOUNT_ID : '';
        $id = preg_replace('/\D/', '', preg_replace('/^act_/i', '', trim($raw)));
        return $id === '' ? '' : $id;
    }

    public static function is_configured() {
        return self::token() !== '' && self::account_id() !== '';
    }

    private static function graph_version() {
        if (defined('AT_META_ADS_GRAPH_VERSION') && preg_match('/^v\d+\.\d+$/', (string) AT_META_ADS_GRAPH_VERSION)) {
            return (string) AT_META_ADS_GRAPH_VERSION;
        }
        if (defined('AT_CAPI_GRAPH_VERSION') && preg_match('/^v\d+\.\d+$/', (string) AT_CAPI_GRAPH_VERSION)) {
            return (string) AT_CAPI_GRAPH_VERSION;
        }
        return self::DEFAULT_GRAPH_VERSION;
    }

    /* ============================== lectura ============================== */

    /**
     * Resumen para el portal y los agentes. Usa la caché si tiene menos de 30 minutos;
     * con $force relee (el portal), pero no más de una vez cada 2 minutos.
     */
    public function get_overview($force = false) {
        $cache = get_option(self::CACHE_OPTION, null);
        $cache = is_array($cache) ? $cache : null;

        if (!self::is_configured()) {
            return $this->shape($cache, self::status('no_config'));
        }

        $age = $cache ? time() - (int) ($cache['fetched_ts'] ?? 0) : PHP_INT_MAX;
        // También un fallo queda 30 min: abrir la pestaña no reintenta en cada visita (el botón sí).
        $fresh = $cache && $age < self::CACHE_SECONDS;
        $too_soon = $cache && $age < self::FORCE_MIN_SECONDS;
        if (($fresh && !$force) || ($force && $too_soon)) {
            return $this->shape($cache, $cache['status'] ?? self::status('ok'), $force && $too_soon ? 'Hubo una lectura hace menos de 2 minutos.' : null);
        }

        $pause = (int) get_option(self::COOLDOWN_OPTION, 0);
        if ($pause > time()) {
            return $this->shape($cache, $cache['status'] ?? self::status('rate_limited'), 'Pausa por límite de Meta hasta ' . self::iso_ts($pause) . '.');
        }

        // Candado atómico: dos pestañas del portal no leen a la vez.
        if (!add_option(self::LOCK_OPTION, time() + self::LOCK_SECONDS, '', 'no')) {
            $until = (int) get_option(self::LOCK_OPTION, 0);
            if ($until > time()) {
                return $this->shape($cache, $cache['status'] ?? self::status('never'), 'Otra lectura está en curso.');
            }
            update_option(self::LOCK_OPTION, time() + self::LOCK_SECONDS, 'no');
        }
        try {
            $result = $this->fetch();
        } finally {
            delete_option(self::LOCK_OPTION);
        }

        $status = $result['status'];
        if ($status['status'] === 'rate_limited') {
            update_option(self::COOLDOWN_OPTION, time() + self::RATE_LIMIT_COOLDOWN, 'no');
        }
        $new = [
            'fetched_ts' => time(),
            // Si falló, se conserva lo último bueno y su fecha.
            'data'       => $result['data'] ?? ($cache['data'] ?? null),
            'data_ts'    => isset($result['data']) ? time() : (int) ($cache['data_ts'] ?? 0),
            'status'     => $status,
        ];
        update_option(self::CACHE_OPTION, $new, 'no');
        return $this->shape($new, $status);
    }

    /** Lee cuenta, anuncios, resultados por anuncio y gasto por día. Público para las pruebas. */
    public function fetch() {
        $this->api_calls = 0;
        $act = 'act_' . self::account_id();

        $acc = $this->graph_get($act, ['fields' => self::ACCOUNT_FIELDS]);
        if (empty($acc['success'])) {
            return ['status' => self::classify_error($acc)];
        }

        $ads_res = $this->graph_get($act . '/ads', ['fields' => self::AD_FIELDS, 'limit' => 100]);
        if (empty($ads_res['success'])) {
            return ['status' => self::classify_error($ads_res)];
        }

        $ins_params = ['level' => 'ad', 'date_preset' => 'maximum', 'fields' => self::INSIGHT_FIELDS, 'limit' => 200];
        $ins = $this->graph_get($act . '/insights', $ins_params);
        if (empty($ins['success']) && (int) ($ins['fb_error']['code'] ?? 0) === 100) {
            $ins_params['fields'] = self::INSIGHT_FIELDS_MIN;
            $ins = $this->graph_get($act . '/insights', $ins_params);
        }
        if (empty($ins['success'])) {
            return ['status' => self::classify_error($ins)];
        }

        $since = (new DateTime('now', new DateTimeZone(self::TZ)))->modify('-' . (self::DAILY_DAYS - 1) . ' days')->format('Y-m-d');
        $until = (new DateTime('now', new DateTimeZone(self::TZ)))->format('Y-m-d');
        $daily_res = $this->graph_get($act . '/insights', [
            'fields' => self::DAILY_FIELDS, 'time_increment' => 1, 'limit' => 100,
            'time_range' => wp_json_encode(['since' => $since, 'until' => $until]),
        ]);
        $errors = [];
        if (empty($daily_res['success'])) {
            // El detalle por día es secundario: si falla, se muestran igual los anuncios.
            $errors[] = 'Gasto por día: ' . self::classify_error($daily_res)['message'];
        }

        $by_ad = [];
        foreach ((array) ($ins['data']['data'] ?? []) as $row) {
            if (!empty($row['ad_id'])) {
                $by_ad[(string) $row['ad_id']] = self::parse_insight_row($row);
            }
        }

        $ads = [];
        foreach ((array) ($ads_res['data']['data'] ?? []) as $ad) {
            $ads[] = self::format_ad($ad, $by_ad[(string) ($ad['id'] ?? '')] ?? null);
        }
        usort($ads, static function ($a, $b) { return strcmp((string) $b['created_at'], (string) $a['created_at']); });

        $daily = [];
        foreach ((array) ($daily_res['data']['data'] ?? []) as $row) {
            $m = self::parse_insight_row($row);
            $daily[] = [
                'day'           => (string) ($row['date_start'] ?? ''),
                'spend'         => $m['spend'],
                'impressions'   => $m['impressions'],
                'reach'         => $m['reach'],
                'conversations' => $m['conversations'],
            ];
        }

        $d = $acc['data'];
        $status = self::status('ok');
        $status['errors'] = $errors;
        return [
            'status' => $status,
            'data'   => [
                'account' => [
                    'id'             => self::account_id(),
                    'name'           => sanitize_text_field((string) ($d['name'] ?? '')),
                    'currency'       => sanitize_text_field((string) ($d['currency'] ?? '')),
                    'account_status' => isset($d['account_status']) ? (int) $d['account_status'] : null,
                    'timezone'       => sanitize_text_field((string) ($d['timezone_name'] ?? '')),
                ],
                'totals' => self::totals($ads),
                'ads'    => $ads,
                'daily'  => $daily,
                'api_calls' => $this->api_calls,
            ],
        ];
    }

    /* ============================== formato ============================== */

    /** Fila de insights de Meta → métricas numéricas (null si Meta no la entregó, nunca 0 inventado). */
    public static function parse_insight_row(array $row) {
        $actions = self::actions_map($row['actions'] ?? []);
        $costs = self::actions_map($row['cost_per_action_type'] ?? []);
        $thru = self::actions_map($row['video_thruplay_watched_actions'] ?? []);
        $out = [
            'spend'       => self::num_or_null($row['spend'] ?? null),
            'impressions' => self::int_or_null($row['impressions'] ?? null),
            'reach'       => self::int_or_null($row['reach'] ?? null),
            'frequency'   => self::num_or_null($row['frequency'] ?? null),
            'cpm'         => self::num_or_null($row['cpm'] ?? null),
            'clicks'      => self::int_or_null($row['clicks'] ?? null),
            'thruplays'   => isset($thru['video_view']) ? (int) round($thru['video_view']) : null,
            'date_start'  => isset($row['date_start']) ? (string) $row['date_start'] : null,
            'date_stop'   => isset($row['date_stop']) ? (string) $row['date_stop'] : null,
        ];
        $has_actions = isset($row['actions']);
        foreach (self::ACTION_KEYS as $key => $type) {
            // Con «actions» presente y sin el tipo, es 0 de verdad (Meta omite los tipos sin eventos).
            $out[$key] = isset($actions[$type]) ? (int) round($actions[$type]) : ($has_actions ? 0 : null);
        }
        $out['cost_per_conversation'] = isset($costs[self::ACTION_KEYS['conversations']]) ? (float) $costs[self::ACTION_KEYS['conversations']] : null;
        return $out;
    }

    private static function actions_map($list) {
        $map = [];
        foreach ((array) $list as $a) {
            if (isset($a['action_type'], $a['value']) && is_numeric($a['value'])) {
                $map[(string) $a['action_type']] = (float) $a['value'];
            }
        }
        return $map;
    }

    private static function format_ad(array $ad, $metrics) {
        $creative = (array) ($ad['creative'] ?? []);
        $adset = (array) ($ad['adset'] ?? []);
        return [
            'id'         => (string) ($ad['id'] ?? ''),
            'name'       => sanitize_text_field((string) ($ad['name'] ?? '')),
            'status'     => sanitize_key((string) ($ad['effective_status'] ?? '')),
            'created_at' => self::iso_meta($ad['created_time'] ?? null),
            'start_at'   => self::iso_meta($adset['start_time'] ?? null),
            'end_at'     => self::iso_meta($adset['end_time'] ?? null),
            'title'      => sanitize_text_field((string) ($creative['title'] ?? '')),
            'body'       => sanitize_textarea_field((string) ($creative['body'] ?? '')),
            'thumbnail'  => esc_url_raw((string) ($creative['thumbnail_url'] ?? '')),
            'metrics'    => $metrics,
        ];
    }

    /** Totales de todos los anuncios. El costo por conversación se calcula, no se suma. */
    public static function totals(array $ads) {
        $keys = ['spend', 'impressions', 'reach', 'clicks', 'thruplays', 'conversations', 'video_views', 'link_clicks', 'engagement'];
        $t = array_fill_keys($keys, null);
        foreach ($ads as $ad) {
            foreach ($keys as $k) {
                $v = $ad['metrics'][$k] ?? null;
                if ($v !== null) {
                    $t[$k] = ($t[$k] ?? 0) + $v;
                }
            }
        }
        $t['cost_per_conversation'] = ($t['spend'] !== null && !empty($t['conversations'])) ? round($t['spend'] / $t['conversations'], 2) : null;
        $t['note'] = 'reach es la suma del alcance de cada anuncio: una cuenta que vio dos anuncios cuenta dos veces. spend no incluye IVA.';
        return $t;
    }

    private function shape($cache, array $status, $note = null) {
        $data = $cache['data'] ?? null;
        return [
            'ok'          => true,
            'configured'  => self::is_configured(),
            'ads_status'  => $status + [
                'last_fetch_at' => isset($cache['fetched_ts']) ? self::iso_ts((int) $cache['fetched_ts']) : null,
                'data_at'       => !empty($cache['data_ts']) ? self::iso_ts((int) $cache['data_ts']) : null,
            ],
            'note'        => $note,
            'account'     => $data['account'] ?? null,
            'totals'      => $data['totals'] ?? null,
            'ads'         => $data['ads'] ?? [],
            'daily'       => $data['daily'] ?? [],
        ];
    }

    private static function status($key) {
        return ['status' => $key, 'code' => 0, 'message' => self::STATUS_MESSAGES[$key] ?? self::STATUS_MESSAGES['error'], 'errors' => []];
    }

    /* ============================== HTTP ============================== */

    /** GET a la Graph API con el token en la cabecera (no en la URL, que queda en registros). */
    private function graph_get($path, array $params) {
        $this->api_calls++;
        $url = 'https://graph.facebook.com/' . self::graph_version() . '/' . $path . '?' . http_build_query($params);
        $r = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . self::token()],
        ]);
        if (is_wp_error($r)) {
            return ['error' => $r->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($r);
        $body = json_decode((string) wp_remote_retrieve_body($r), true);
        if ($code >= 200 && $code < 300 && is_array($body) && !isset($body['error'])) {
            return ['success' => true, 'data' => $body];
        }
        $err = is_array($body['error'] ?? null) ? $body['error'] : [];
        return ['error' => (string) ($err['message'] ?? ('HTTP ' . $code)), 'fb_error' => ['code' => (int) ($err['code'] ?? 0), 'subcode' => (int) ($err['error_subcode'] ?? 0)]];
    }

    /**
     * Error de la Graph API → estado. Público para las pruebas.
     * 190 = token vencido; límites = RATE_LIMIT_CODES; 10 / 200 / 294 o «permission» = sin ads_read.
     */
    public static function classify_error($res) {
        $code = (int) ($res['fb_error']['code'] ?? 0);
        $msg = (string) ($res['error'] ?? 'Error desconocido');
        $clean = self::clean_message($msg);
        $key = 'error';
        if ($code === 190) {
            $key = 'token_invalid';
        } elseif (in_array($code, self::RATE_LIMIT_CODES, true) || stripos($msg, 'request limit') !== false || stripos($msg, 'too many calls') !== false) {
            $key = 'rate_limited';
        } elseif (in_array($code, [10, 200, 294], true) || stripos($msg, 'permission') !== false) {
            $key = 'no_permission';
        }
        $s = self::status($key);
        $s['code'] = $code;
        $s['message'] = $key === 'error' ? $clean : $s['message'] . ' (Meta: ' . $clean . ')';
        return $s;
    }

    /** Recorta el mensaje y tacha cualquier cadena larga tipo token por si Meta la devolviera. */
    private static function clean_message($msg) {
        $msg = preg_replace('/[A-Za-z0-9_\-\.]{40,}/', '[oculto]', (string) $msg);
        return mb_substr(sanitize_text_field($msg), 0, 200);
    }

    /* ============================== utilidades ============================== */

    private static function iso_meta($ts) {
        if (!$ts) return null;
        $t = strtotime((string) $ts);
        return $t ? gmdate('Y-m-d\TH:i:s\Z', $t) : null;
    }

    private static function iso_ts($ts) {
        return gmdate('Y-m-d\TH:i:s\Z', (int) $ts);
    }

    private static function int_or_null($v) {
        return ($v === null || $v === '' || !is_numeric($v)) ? null : (int) $v;
    }

    private static function num_or_null($v) {
        return ($v === null || $v === '' || !is_numeric($v)) ? null : round((float) $v, 2);
    }

    public function api_calls() {
        return $this->api_calls;
    }
}
