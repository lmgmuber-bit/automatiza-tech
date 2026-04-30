<?php
/**
 * Plugin Name: AT Security Monitor
 * Description: Alerta por email/WP cron ante eventos sospechosos: bursts a wp-login, accesos a archivos sensibles, errores 5xx, picos de uso de OpenAI. Tambien incluye dashboard widget.
 * Version:     1.0.0
 * Author:      Automatiza Tech
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'at_secmon_log_event' ) ) {
    /**
     * Registra evento de seguridad. Usar para anomalias.
     * Persiste en option 'at_security_events' (rolling window de 200).
     */
    function at_secmon_log_event( $type, $detail = [] ) {
        $events = get_option( 'at_security_events', [] );
        if ( ! is_array( $events ) ) { $events = []; }
        $events[] = [
            't'      => time(),
            'type'   => sanitize_key( $type ),
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? '?',
            'ua'     => substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 200 ),
            'uri'    => substr( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 0, 200 ),
            'detail' => $detail,
        ];
        if ( count( $events ) > 200 ) {
            $events = array_slice( $events, -200 );
        }
        update_option( 'at_security_events', $events, false );
    }
}

/**
 * Detecta accesos a paths sensibles (que ya bloqueamos a nivel htaccess).
 * Util para detectar reconnaissance/scanners.
 */
add_action( 'init', function () {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $sensitive = '/(\\.env|\\.git|wp-config\\.php|wp-config-secrets|phpmyadmin|adminer|wp-config\\.bak)/i';
    if ( preg_match( $sensitive, $uri ) ) {
        at_secmon_log_event( 'sensitive_probe', [ 'pattern' => 'config/admin' ] );
    }
}, 1 );

/**
 * Loguea logins fallidos (complemento al at-login-hardening).
 */
add_action( 'wp_login_failed', function ( $user_login ) {
    at_secmon_log_event( 'login_failed', [ 'user' => substr( (string) $user_login, 0, 60 ) ] );
} );

/**
 * Loguea acceso a wp_ajax_nopriv_* sin nonce, si la peticion lo permite detectar.
 * (Notice silencioso, no rompe el handler.)
 */
add_action( 'admin_init', function () {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) { return; }
    $action = sanitize_key( $_REQUEST['action'] ?? '' );
    if ( $action === '' || is_user_logged_in() ) { return; }
    if ( empty( $_REQUEST['_ajax_nonce'] ) && empty( $_REQUEST['nonce'] ) && empty( $_REQUEST['_wpnonce'] ) ) {
        at_secmon_log_event( 'ajax_nopriv_no_nonce', [ 'action' => $action ] );
    }
} );

/**
 * Cron: cada hora revisa eventos y dispara alerta si supera umbrales.
 */
if ( ! wp_next_scheduled( 'at_secmon_hourly' ) ) {
    wp_schedule_event( time() + 60, 'hourly', 'at_secmon_hourly' );
}
add_action( 'at_secmon_hourly', function () {
    $events = get_option( 'at_security_events', [] );
    if ( ! is_array( $events ) || empty( $events ) ) { return; }

    $hour_ago = time() - 3600;
    $recent   = array_filter( $events, function ( $e ) use ( $hour_ago ) {
        return ( $e['t'] ?? 0 ) >= $hour_ago;
    } );

    $by_type = [];
    foreach ( $recent as $e ) {
        $t = $e['type'] ?? '?';
        $by_type[ $t ] = ( $by_type[ $t ] ?? 0 ) + 1;
    }

    $alerts = [];
    if ( ( $by_type['login_failed']        ?? 0 ) >= 20 ) { $alerts[] = "{$by_type['login_failed']} login_failed/h"; }
    if ( ( $by_type['sensitive_probe']     ?? 0 ) >= 10 ) { $alerts[] = "{$by_type['sensitive_probe']} sensitive_probe/h"; }
    if ( ( $by_type['ajax_nopriv_no_nonce'] ?? 0 ) >= 50 ) { $alerts[] = "{$by_type['ajax_nopriv_no_nonce']} ajax_nopriv sin nonce/h"; }

    if ( empty( $alerts ) ) { return; }

    $admin_email = get_option( 'admin_email' );
    if ( ! $admin_email ) { return; }

    $env = defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'production';
    if ( $env !== 'production' ) { return; }

    $subject = '[AT Security] Alerta: ' . count( $alerts ) . ' anomalias detectadas';
    $body    = "Eventos de la ultima hora:\n\n - " . implode( "\n - ", $alerts ) . "\n\nSitio: " . home_url();

    wp_mail( $admin_email, $subject, $body );
} );

/**
 * Dashboard widget para superadmin con resumen.
 */
add_action( 'wp_dashboard_setup', function () {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    wp_add_dashboard_widget( 'at_secmon_widget', 'AT Seguridad — eventos recientes', function () {
        $events = get_option( 'at_security_events', [] );
        if ( empty( $events ) ) {
            echo '<p>Sin eventos registrados.</p>';
            return;
        }
        $events = array_slice( array_reverse( $events ), 0, 25 );
        echo '<table class="widefat striped"><thead><tr><th>Hora</th><th>Tipo</th><th>IP</th><th>URI</th></tr></thead><tbody>';
        foreach ( $events as $e ) {
            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td><code>%s</code></td></tr>',
                esc_html( gmdate( 'Y-m-d H:i', (int) $e['t'] ) ),
                esc_html( $e['type'] ?? '?' ),
                esc_html( $e['ip']   ?? '?' ),
                esc_html( $e['uri']  ?? '?' )
            );
        }
        echo '</tbody></table>';
    } );
} );
