<?php
/**
 * AT Finance + AI Usage Controller
 *
 * Dos módulos administrativos del portal OmniCliente:
 *  1) Finanzas AT (admin-only): registro de gastos de infraestructura
 *     (hosting, n8n, APIs, YCloud, dominios...) con periodicidad mensual/anual
 *     y resumen normalizado (gasto mensual, proyección anual, por categoría).
 *  2) Consumo de tokens IA: tabla de eventos de uso por cliente/canal/bot
 *     con fuente 'bot' (workflows n8n vía usage-ingest) o 'assistant'
 *     (Asistente IA interno, logueado desde omnichannel-controller.php).
 *
 * Tablas: {prefix}omnichannel_at_expenses · {prefix}omnichannel_ai_usage
 */

if (!defined('ABSPATH')) {
    exit;
}

class OmniATFinanceController {

    private $wpdb;
    private $prefix;

    const DB_VERSION = '1';

    /** Precios por millón de tokens (USD) para estimar costo al ingerir. */
    const MODEL_PRICING = [
        'gpt-4o-mini' => ['in' => 0.15, 'out' => 0.60],
        'gpt-4o'      => ['in' => 2.50, 'out' => 10.00],
        'gpt-4.1-mini'=> ['in' => 0.40, 'out' => 1.60],
    ];

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'omnichannel_';
    }

    /* ============================== schema ============================== */

    public function maybe_create_tables() {
        if (get_option('omni_atfinance_db_version') === self::DB_VERSION) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$this->prefix}at_expenses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category VARCHAR(60) NOT NULL DEFAULT 'otros',
            provider VARCHAR(120) NOT NULL DEFAULT '',
            description VARCHAR(255) NOT NULL DEFAULT '',
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'CLP',
            period VARCHAR(10) NOT NULL DEFAULT 'monthly',
            start_date DATE NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY category (category),
            KEY active (active)
        ) $charset;");

        dbDelta("CREATE TABLE {$this->prefix}ai_usage (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            channel_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            bot_name VARCHAR(120) NOT NULL DEFAULT '',
            source VARCHAR(20) NOT NULL DEFAULT 'bot',
            model VARCHAR(60) NOT NULL DEFAULT '',
            prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY client_created (client_id, created_at),
            KEY channel_id (channel_id),
            KEY source (source)
        ) $charset;");

        update_option('omni_atfinance_db_version', self::DB_VERSION);
    }

    /* ============================ gastos AT ============================ */

    public function get_expenses($only_active = false) {
        $where = $only_active ? 'WHERE active = 1' : '';
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->prefix}at_expenses $where ORDER BY active DESC, category, provider"
        );
    }

    public function create_expense($data) {
        $fields = $this->sanitize_expense($data);
        $fields['created_at'] = current_time('mysql');
        $fields['updated_at'] = current_time('mysql');
        $ok = $this->wpdb->insert($this->prefix . 'at_expenses', $fields);
        return $ok ? ['success' => true, 'id' => (int) $this->wpdb->insert_id] : ['error' => 'No se pudo crear el gasto'];
    }

    public function update_expense($id, $data) {
        $fields = $this->sanitize_expense($data);
        $fields['updated_at'] = current_time('mysql');
        $ok = $this->wpdb->update($this->prefix . 'at_expenses', $fields, ['id' => absint($id)]);
        return $ok !== false ? ['success' => true] : ['error' => 'No se pudo actualizar'];
    }

    public function delete_expense($id) {
        $ok = $this->wpdb->delete($this->prefix . 'at_expenses', ['id' => absint($id)]);
        return ['success' => (bool) $ok];
    }

    /**
     * Próximo vencimiento de un gasto recurrente activo (monthly/annual).
     * Ancla: start_date, o la fecha de creación si no se cargó start_date.
     * Devuelve 'Y-m-d' o null si no aplica (one_time / inactivo).
     */
    public function next_due($e) {
        if ((int) $e->active !== 1 || !in_array($e->period, ['monthly', 'annual'], true)) {
            return null;
        }
        $anchor = $e->start_date ?: substr((string) $e->created_at, 0, 10);
        if (!$anchor) {
            return null;
        }
        $interval = $e->period === 'monthly' ? '+1 month' : '+1 year';
        return date('Y-m-d', strtotime($anchor . ' ' . $interval));
    }

    /** Gastos recurrentes activos cuyo período ya venció (para pedir confirmación de renovación). */
    public function get_due_renewals() {
        $today = current_time('Y-m-d');
        $due = [];
        foreach ($this->get_expenses(true) as $e) {
            $next = $this->next_due($e);
            if ($next && $next <= $today) {
                $e->next_due = $next;
                $due[] = $e;
            }
        }
        return $due;
    }

    /**
     * Cierra el período vencido de un gasto recurrente.
     * $paid=true  → marca el registro viejo como inactivo (queda de historial del
     *               período anterior) y crea un registro NUEVO activo para el
     *               período siguiente (si se saltaron períodos, avanza hasta el vigente).
     * $paid=false → solo desactiva el registro (no renovado / servicio cancelado).
     */
    public function renew_expense($id, $paid) {
        $e = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->prefix}at_expenses WHERE id = %d", absint($id)
        ));
        if (!$e) {
            return ['error' => 'Gasto no encontrado'];
        }
        $today = current_time('Y-m-d');
        $now   = current_time('mysql');

        if (!$paid) {
            $this->wpdb->update($this->prefix . 'at_expenses', [
                'active'     => 0,
                'notes'      => trim(($e->notes ? $e->notes . "\n" : '') . "No renovado el $today"),
                'updated_at' => $now,
            ], ['id' => (int) $e->id]);
            return ['success' => true, 'renewed' => false];
        }

        if (!in_array($e->period, ['monthly', 'annual'], true)) {
            return ['error' => 'Solo los gastos mensuales/anuales se renuevan'];
        }
        $interval  = $e->period === 'annual' ? '+1 year' : '+1 month';
        $anchor    = $e->start_date ?: substr((string) $e->created_at, 0, 10);
        $new_start = date('Y-m-d', strtotime($anchor . ' ' . $interval));
        // Si se saltaron períodos completos, avanzar hasta el período vigente
        while (date('Y-m-d', strtotime($new_start . ' ' . $interval)) <= $today) {
            $new_start = date('Y-m-d', strtotime($new_start . ' ' . $interval));
        }

        $copy = [
            'category'    => $e->category,
            'provider'    => $e->provider,
            'description' => $e->description,
            'amount'      => $e->amount,
            'currency'    => $e->currency,
            'period'      => $e->period,
            'start_date'  => $new_start,
            'active'      => 1,
            'notes'       => "Renovación confirmada el $today (período anterior: gasto #{$e->id})",
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
        $ok = $this->wpdb->insert($this->prefix . 'at_expenses', $copy);
        if (!$ok) {
            return ['error' => 'No se pudo crear el registro del nuevo período'];
        }
        $new_id = (int) $this->wpdb->insert_id;

        $this->wpdb->update($this->prefix . 'at_expenses', [
            'active'     => 0,
            'notes'      => trim(($e->notes ? $e->notes . "\n" : '') . "Período cerrado el $today, renovado en gasto #$new_id"),
            'updated_at' => $now,
        ], ['id' => (int) $e->id]);

        return ['success' => true, 'renewed' => true, 'id' => $new_id, 'new_start' => $new_start];
    }

    private function sanitize_expense($data) {
        $period = in_array($data['period'] ?? '', ['monthly', 'annual', 'one_time'], true) ? $data['period'] : 'monthly';
        $currency = in_array(strtoupper($data['currency'] ?? ''), ['CLP', 'USD'], true) ? strtoupper($data['currency']) : 'CLP';
        return [
            'category'    => sanitize_text_field($data['category'] ?? 'otros'),
            'provider'    => sanitize_text_field($data['provider'] ?? ''),
            'description' => sanitize_text_field($data['description'] ?? ''),
            'amount'      => round(floatval($data['amount'] ?? 0), 2),
            'currency'    => $currency,
            'period'      => $period,
            'start_date'  => preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['start_date'] ?? '') ? $data['start_date'] : null,
            'active'      => !empty($data['active']) ? 1 : 0,
            'notes'       => sanitize_textarea_field($data['notes'] ?? ''),
        ];
    }

    /**
     * Resumen normalizado: mensual equivalente (annual/12), por moneda y
     * categoría + costo IA estimado de los últimos 30 días.
     */
    public function finance_summary() {
        $rows = $this->get_expenses(true);
        $summary = [
            'monthly'      => ['CLP' => 0.0, 'USD' => 0.0],
            'annual'       => ['CLP' => 0.0, 'USD' => 0.0],
            'by_category'  => [],
            'active_count' => count($rows),
        ];
        foreach ($rows as $r) {
            $monthly = $r->period === 'annual' ? $r->amount / 12 : ($r->period === 'monthly' ? (float) $r->amount : 0.0);
            $summary['monthly'][$r->currency] += $monthly;
            $summary['annual'][$r->currency]  += $monthly * 12;
            if (!isset($summary['by_category'][$r->category])) {
                $summary['by_category'][$r->category] = ['CLP' => 0.0, 'USD' => 0.0];
            }
            $summary['by_category'][$r->category][$r->currency] += $monthly;
        }
        foreach (['CLP', 'USD'] as $cur) {
            $summary['monthly'][$cur] = round($summary['monthly'][$cur], 2);
            $summary['annual'][$cur]  = round($summary['annual'][$cur], 2);
        }
        foreach ($summary['by_category'] as $cat => $vals) {
            $summary['by_category'][$cat]['CLP'] = round($vals['CLP'], 2);
            $summary['by_category'][$cat]['USD'] = round($vals['USD'], 2);
        }
        $summary['ai_cost_usd_30d'] = round((float) $this->wpdb->get_var(
            "SELECT COALESCE(SUM(cost_usd),0) FROM {$this->prefix}ai_usage
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ), 4);
        return $summary;
    }

    /* ============================ uso de IA ============================ */

    /** Registra un evento de uso. $data ya viene validado/atribuido por el caller. */
    public function log_usage($data) {
        $model  = sanitize_text_field($data['model'] ?? '');
        $pt = absint($data['prompt_tokens'] ?? 0);
        $ct = absint($data['completion_tokens'] ?? 0);
        $tt = absint($data['total_tokens'] ?? ($pt + $ct));
        $cost = isset($data['cost_usd']) ? round(floatval($data['cost_usd']), 6) : $this->estimate_cost($model, $pt, $ct);
        $this->wpdb->insert($this->prefix . 'ai_usage', [
            'client_id'         => absint($data['client_id'] ?? 0),
            'channel_id'        => absint($data['channel_id'] ?? 0),
            'bot_name'          => sanitize_text_field($data['bot_name'] ?? ''),
            'source'            => in_array($data['source'] ?? '', ['bot', 'assistant'], true) ? $data['source'] : 'bot',
            'model'             => $model,
            'prompt_tokens'     => $pt,
            'completion_tokens' => $ct,
            'total_tokens'      => $tt,
            'cost_usd'          => $cost,
            'created_at'        => current_time('mysql'),
        ]);
        return ['success' => true, 'id' => (int) $this->wpdb->insert_id, 'cost_usd' => $cost];
    }

    private function estimate_cost($model, $prompt_tokens, $completion_tokens) {
        $p = self::MODEL_PRICING[$model] ?? self::MODEL_PRICING['gpt-4o-mini'];
        return round(($prompt_tokens * $p['in'] + $completion_tokens * $p['out']) / 1000000, 6);
    }

    /**
     * Estadísticas agregadas.
     * $client_id = -1 → todos los clientes SIN filtrar (admin, vista general).
     * $client_id = 0  → filtro explícito a "AT (plataforma / demos internos)".
     * $client_id > 0  → un cliente real específico.
     * Devuelve totales del período, desglose por canal/bot/fuente y serie diaria.
     */
    public function usage_stats($client_id = -1, $days = 30) {
        $days = min(max(absint($days), 1), 365);
        $where = 'WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
        $args = [$days];
        if ($client_id >= 0) {
            $where .= ' AND u.client_id = %d';
            $args[] = $client_id;
        }

        $totals = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) events, COALESCE(SUM(u.prompt_tokens),0) prompt_tokens,
                    COALESCE(SUM(u.completion_tokens),0) completion_tokens,
                    COALESCE(SUM(u.total_tokens),0) total_tokens,
                    COALESCE(SUM(u.cost_usd),0) cost_usd
             FROM {$this->prefix}ai_usage u $where", $args
        ), ARRAY_A);

        $by_channel = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT u.client_id, c.company_name, u.channel_id,
                    COALESCE(ch.channel_name,'—') channel_name,
                    COALESCE(NULLIF(ch.channel_type,''),'interno') channel_type,
                    u.bot_name, u.source, u.model,
                    COUNT(*) events, SUM(u.total_tokens) total_tokens, SUM(u.cost_usd) cost_usd
             FROM {$this->prefix}ai_usage u
             LEFT JOIN {$this->prefix}clients c ON c.id = u.client_id
             LEFT JOIN {$this->prefix}channels ch ON ch.id = u.channel_id
             $where
             GROUP BY u.client_id, u.channel_id, u.bot_name, u.source, u.model
             ORDER BY total_tokens DESC
             LIMIT 200", $args
        ), ARRAY_A);

        $daily = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(u.created_at) day, SUM(u.total_tokens) total_tokens, SUM(u.cost_usd) cost_usd
             FROM {$this->prefix}ai_usage u $where
             GROUP BY DATE(u.created_at) ORDER BY day ASC", $args
        ), ARRAY_A);

        return [
            'days'       => $days,
            'totals'     => array_map('floatval', $totals ?: []),
            'by_channel' => $by_channel ?: [],
            'daily'      => $daily ?: [],
        ];
    }

    /** Ingesta desde n8n (autenticada por API key del cliente en el caller). */
    public function ingest_from_n8n($client_id, $body) {
        $channel_id = absint($body['channel_id'] ?? 0);
        if ($channel_id) {
            // El canal debe pertenecer al cliente autenticado (no cruzar datos)
            $owner = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT client_id FROM {$this->prefix}channels WHERE id = %d", $channel_id
            ));
            if ($owner !== (int) $client_id) {
                return ['error' => 'channel_id no pertenece a este cliente', 'code' => 403];
            }
        }
        return $this->log_usage([
            'client_id'         => $client_id,
            'channel_id'        => $channel_id,
            'bot_name'          => $body['bot_name'] ?? '',
            'source'            => 'bot',
            'model'             => $body['model'] ?? '',
            'prompt_tokens'     => $body['prompt_tokens'] ?? 0,
            'completion_tokens' => $body['completion_tokens'] ?? 0,
            'total_tokens'      => $body['total_tokens'] ?? 0,
            'cost_usd'          => $body['cost_usd'] ?? null,
        ]);
    }
}

/**
 * Helper global desacoplado: cualquier controlador puede loguear uso IA
 * sin acoplarse a la clase (p. ej. el Asistente IA interno).
 */
if (!function_exists('at_omni_log_ai_usage')) {
    function at_omni_log_ai_usage(array $data) {
        try {
            $fin = new OmniATFinanceController();
            $fin->maybe_create_tables();
            return $fin->log_usage($data);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
