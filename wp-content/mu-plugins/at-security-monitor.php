<?php
/**
 * Plugin Name: AT Security Monitor
 * Description: Alerta por email/WP cron ante eventos sospechosos: bursts a wp-login, accesos a archivos sensibles, errores 5xx, picos de uso de OpenAI. También incluye dashboard widget.
 * Version:     1.1.0
 * Author:      Automatiza Tech
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ─────────────────────────────────────────────────────────
 * 1. CORE: at_secmon_log_event()
 *    Rolling buffer de 500 eventos en option 'at_security_events'.
 *    Llamado desde: rate-limit, autenticación API, contratos, IDOR.
 * ───────────────────────────────────────────────────────── */
if ( ! function_exists( 'at_secmon_log_event' ) ) {
    /**
     * Registra evento de seguridad. Usar para anomalías.
     *
     * @param string $type   Clave del evento, ej. 'rate_limit_reject', 'api_auth_failed'.
     * @param array  $detail Datos extra (bucket, endpoint, id, etc.).
     */
    function at_secmon_log_event( string $type, array $detail = [] ): void {
        $events = get_option( 'at_security_events', [] );
        if ( ! is_array( $events ) ) { $events = []; }

        // Deduplicar ráfagas: si el último evento del mismo tipo+IP tiene <2s de diferencia, sólo actualizar contador
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '?';
        $last    = end( $events );
        if ( $last
            && ( $last['type'] ?? '' ) === sanitize_key( $type )
            && ( $last['ip'] ?? '' ) === $ip
            && ( time() - ( $last['t'] ?? 0 ) ) < 2
        ) {
            $events[ key( $events ) ]['count'] = ( $last['count'] ?? 1 ) + 1;
            update_option( 'at_security_events', $events, false );
            return;
        }

        $events[] = [
            't'      => time(),
            'type'   => sanitize_key( $type ),
            'ip'     => $ip,
            'ua'     => substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 200 ),
            'uri'    => substr( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 0, 200 ),
            'detail' => $detail,
            'count'  => 1,
        ];

        // Rolling window: guardar últimos 500
        if ( count( $events ) > 500 ) {
            $events = array_slice( $events, -500 );
        }
        update_option( 'at_security_events', $events, false );
    }
}

/* ─────────────────────────────────────────────────────────
 * 2. HOOKS: detectar eventos automáticos
 * ───────────────────────────────────────────────────────── */

/** Detecta accesos a paths sensibles (reconnaissance/scanners). */
add_action( 'init', function () {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( preg_match( '/(\\.env|\\.git|wp-config\\.php|wp-config-secrets|phpmyadmin|adminer|wp-config\\.bak)/i', $uri ) ) {
        at_secmon_log_event( 'sensitive_probe', [ 'pattern' => 'config_admin' ] );
    }
}, 1 );

/** Logins fallidos de WordPress. */
add_action( 'wp_login_failed', function ( $user_login ) {
    at_secmon_log_event( 'login_failed', [ 'user' => substr( (string) $user_login, 0, 60 ) ] );
} );

/** AJAX no autenticado sin nonce (detección pasiva). */
add_action( 'admin_init', function () {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) { return; }
    $action = sanitize_key( $_REQUEST['action'] ?? '' );
    if ( $action === '' || is_user_logged_in() ) { return; }
    if ( empty( $_REQUEST['_ajax_nonce'] ) && empty( $_REQUEST['nonce'] ) && empty( $_REQUEST['_wpnonce'] ) ) {
        at_secmon_log_event( 'ajax_nopriv_no_nonce', [ 'action' => $action ] );
    }
} );

/* ─────────────────────────────────────────────────────────
 * 3. CRON: alerta horaria si umbrales superados
 *    Umbrales por tipo de evento:
 *      login_failed          ≥ 20/h
 *      sensitive_probe       ≥ 10/h
 *      ajax_nopriv_no_nonce  ≥ 50/h
 *      rate_limit_reject     ≥ 100/h  (nuevo — C4)
 *      api_auth_failed       ≥ 10/h   (nuevo — C4)
 *      download_token_invalid ≥ 5/h   (nuevo — C4)
 *      contract_token_invalid ≥ 3/h   (nuevo — C4)
 * ───────────────────────────────────────────────────────── */
if ( ! wp_next_scheduled( 'at_secmon_hourly' ) ) {
    wp_schedule_event( time() + 60, 'hourly', 'at_secmon_hourly' );
}

add_action( 'at_secmon_hourly', function () {
    $events = get_option( 'at_security_events', [] );
    if ( ! is_array( $events ) || empty( $events ) ) { return; }

    $hour_ago = time() - 3600;
    $recent   = array_filter( $events, fn( $e ) => ( $e['t'] ?? 0 ) >= $hour_ago );

    // Contar eventos (respetando deduplicación por 'count')
    $by_type = [];
    $by_ip   = [];
    foreach ( $recent as $e ) {
        $t                = $e['type'] ?? '?';
        $n                = (int) ( $e['count'] ?? 1 );
        $by_type[ $t ]    = ( $by_type[ $t ] ?? 0 ) + $n;
        $ip               = $e['ip'] ?? '?';
        $by_ip[ $ip ]     = ( $by_ip[ $ip ] ?? 0 ) + $n;
    }

    // Umbrales
    $thresholds = [
        'login_failed'           => 20,
        'sensitive_probe'        => 10,
        'ajax_nopriv_no_nonce'   => 50,
        'rate_limit_reject'      => 100,
        'api_auth_failed'        => 10,
        'download_token_invalid' => 5,
        'contract_token_invalid' => 3,
    ];

    $alerts = [];
    foreach ( $thresholds as $type => $threshold ) {
        $count = $by_type[ $type ] ?? 0;
        if ( $count >= $threshold ) {
            $alerts[] = sprintf( '%d %s (umbral: %d)', $count, $type, $threshold );
        }
    }

    if ( empty( $alerts ) ) { return; }

    // Solo en producción
    $env = defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'production';
    if ( $env !== 'production' ) { return; }

    $admin_email = get_option( 'admin_email' );
    if ( ! $admin_email ) { return; }

    // Top 5 IPs ofensivas
    arsort( $by_ip );
    $top_ips = array_slice( $by_ip, 0, 5, true );
    $ip_lines = [];
    foreach ( $top_ips as $ip => $n ) {
        $ip_lines[] = "  {$ip} → {$n} eventos";
    }

    $subject = '[AT Security] ⚠️ ' . count( $alerts ) . ' umbrales superados en la última hora';
    $body    = "Eventos de seguridad — última hora:\n\n";
    $body   .= implode( "\n", array_map( fn( $a ) => " • {$a}", $alerts ) );
    $body   .= "\n\nTop IPs:\n" . implode( "\n", $ip_lines );
    $body   .= "\n\nSitio: " . home_url();
    $body   .= "\n\nRevisa el panel: " . admin_url( 'index.php' );

    wp_mail( $admin_email, $subject, $body );
} );

/* ─────────────────────────────────────────────────────────
 * 4. DASHBOARD WIDGET
 * ───────────────────────────────────────────────────────── */
add_action( 'wp_dashboard_setup', function () {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    wp_add_dashboard_widget(
        'at_secmon_widget',
        '🛡️ AT Seguridad — eventos recientes',
        function () {
            $events = get_option( 'at_security_events', [] );
            if ( empty( $events ) ) {
                echo '<p>Sin eventos registrados.</p>';
                return;
            }
            // Resumen por tipo (última hora)
            $hour_ago = time() - 3600;
            $by_type  = [];
            foreach ( $events as $e ) {
                if ( ( $e['t'] ?? 0 ) >= $hour_ago ) {
                    $t              = $e['type'] ?? '?';
                    $by_type[ $t ]  = ( $by_type[ $t ] ?? 0 ) + (int) ( $e['count'] ?? 1 );
                }
            }
            if ( ! empty( $by_type ) ) {
                echo '<p><strong>Última hora:</strong> ';
                $parts = [];
                foreach ( $by_type as $t => $n ) {
                    $parts[] = esc_html( "{$n} {$t}" );
                }
                echo implode( ' · ', $parts );
                echo '</p>';
            }
            // Últimos 25 eventos
            $recent = array_slice( array_reverse( $events ), 0, 25 );
            echo '<table class="widefat striped" style="font-size:12px"><thead>';
            echo '<tr><th>Hora</th><th>Tipo</th><th>IP</th><th>Detail</th></tr></thead><tbody>';
            foreach ( $recent as $e ) {
                $detail_str = '';
                if ( ! empty( $e['detail'] ) && is_array( $e['detail'] ) ) {
                    foreach ( $e['detail'] as $k => $v ) {
                        $detail_str .= esc_html( $k ) . '=' . esc_html( (string) $v ) . ' ';
                    }
                }
                if ( ( $e['count'] ?? 1 ) > 1 ) {
                    $detail_str .= '(x' . (int) $e['count'] . ')';
                }
                printf(
                    '<tr><td>%s</td><td><code>%s</code></td><td>%s</td><td style="color:#666">%s</td></tr>',
                    esc_html( gmdate( 'H:i:s', (int) $e['t'] ) ),
                    esc_html( $e['type'] ?? '?' ),
                    esc_html( $e['ip']   ?? '?' ),
                    $detail_str
                );
            }
            echo '</tbody></table>';
            echo '<p style="text-align:right;margin-top:6px"><a href="' . esc_url( admin_url( 'admin.php?page=at_security_log' ) ) . '">Ver log completo →</a></p>';
        }
    );
} );

/* ─────────────────────────────────────────────────────────
 * 5. ADMIN PAGE: log completo paginado
 * ───────────────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'AT Security Log',
        '🛡️ Security Log',
        'manage_options',
        'at_security_log',
        'at_secmon_render_log_page'
    );
} );

function at_secmon_render_log_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sin permisos' ); }
    $events  = get_option( 'at_security_events', [] );
    $events  = array_reverse( (array) $events );
    $total   = count( $events );
    $per_page = 50;
    $page    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
    $offset  = ( $page - 1 ) * $per_page;
    $slice   = array_slice( $events, $offset, $per_page );

    // Filtro por tipo
    $filter_type = sanitize_key( $_GET['type'] ?? '' );
    if ( $filter_type ) {
        $events = array_values( array_filter( $events, fn( $e ) => ( $e['type'] ?? '' ) === $filter_type ) );
        $total  = count( $events );
        $slice  = array_slice( $events, $offset, $per_page );
    }

    echo '<div class="wrap"><h1>🛡️ AT Security Log <span style="font-size:14px;font-weight:normal">(' . (int) $total . ' eventos)</span></h1>';

    // Botón de limpieza
    if ( isset( $_POST['at_secmon_clear'] ) && check_admin_referer( 'at_secmon_clear' ) ) {
        update_option( 'at_security_events', [], false );
        echo '<div class="notice notice-success"><p>Log limpiado.</p></div>';
        $slice = [];
    }

    echo '<form method="post" style="margin-bottom:12px">';
    wp_nonce_field( 'at_secmon_clear' );
    echo '<button name="at_secmon_clear" class="button button-secondary" onclick="return confirm(\'¿Limpiar todo el log?\')">🗑 Limpiar log</button>';
    echo '</form>';

    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>Hora (UTC)</th><th>Tipo</th><th>IP</th><th>URI</th><th>Detail</th>';
    echo '</tr></thead><tbody>';
    foreach ( $slice as $e ) {
        $detail_str = '';
        if ( ! empty( $e['detail'] ) && is_array( $e['detail'] ) ) {
            foreach ( $e['detail'] as $k => $v ) {
                $detail_str .= esc_html( $k ) . '=<strong>' . esc_html( (string) $v ) . '</strong> ';
            }
        }
        if ( ( $e['count'] ?? 1 ) > 1 ) {
            $detail_str .= '<em>(×' . (int) $e['count'] . ')</em>';
        }
        printf(
            '<tr><td nowrap>%s</td><td><code>%s</code></td><td>%s</td><td style="max-width:200px;word-break:break-all"><code>%s</code></td><td style="font-size:11px">%s</td></tr>',
            esc_html( gmdate( 'Y-m-d H:i:s', (int) $e['t'] ) ),
            esc_html( $e['type'] ?? '?' ),
            esc_html( $e['ip']   ?? '?' ),
            esc_html( substr( (string) ( $e['uri'] ?? '' ), 0, 100 ) ),
            $detail_str
        );
    }
    echo '</tbody></table>';

    // Paginación simple
    $pages = (int) ceil( $total / $per_page );
    if ( $pages > 1 ) {
        echo '<div style="margin-top:12px">';
        for ( $i = 1; $i <= $pages; $i++ ) {
            $url = add_query_arg( [ 'page' => 'at_security_log', 'paged' => $i ], admin_url( 'tools.php' ) );
            $style = $i === $page ? 'font-weight:bold' : '';
            echo '<a href="' . esc_url( $url ) . '" style="' . $style . ';margin-right:6px">' . (int) $i . '</a>';
        }
        echo '</div>';
    }

    echo '</div>';
}

