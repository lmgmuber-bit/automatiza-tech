<?php
/**
 * Plugin Name: AT Instagram Token Auto-Refresh
 * Description: Renueva automaticamente los tokens de larga duracion de los canales de
 *              Instagram antes de que venzan. Los tokens de Instagram Login duran 60 dias;
 *              el 2026-08-14 uno vencio en silencio y el Reel Diario dejo de publicar una
 *              semana, gastando renders y creditos de IA en cada intento fallido.
 *
 * Endpoint: GET https://graph.instagram.com/refresh_access_token
 *           ?grant_type=ig_refresh_token&access_token=<token vigente>
 * Requisitos de Meta: el token debe estar VIGENTE y tener mas de 24h de vida.
 * Un token ya vencido no se puede refrescar — hay que re-autorizar a mano en el panel.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const AT_IG_REFRESH_HOOK       = 'at_ig_token_refresh_daily';
const AT_IG_REFRESH_META       = 'at_ig_token_meta';   // option: [channel_id => [...]]
const AT_IG_REFRESH_THRESHOLD  = 15 * DAY_IN_SECONDS;  // renovar con 15 dias de margen
const AT_IG_REFRESH_MIN_AGE    = 25 * HOUR_IN_SECONDS; // Meta exige >24h de vida

if ( ! wp_next_scheduled( AT_IG_REFRESH_HOOK ) ) {
    wp_schedule_event( time() + 300, 'daily', AT_IG_REFRESH_HOOK );
}

add_action( AT_IG_REFRESH_HOOK, 'at_ig_token_refresh_run' );

/**
 * Recorre los canales de Instagram activos y renueva los que esten por vencer.
 */
function at_ig_token_refresh_run() {
    global $wpdb;

    $channels = $wpdb->get_results(
        "SELECT id, channel_name, bot_token
           FROM {$wpdb->prefix}omnichannel_channels
          WHERE channel_type = 'instagram'
            AND is_active = 1
            AND bot_token IS NOT NULL
            AND bot_token != ''"
    );
    if ( empty( $channels ) ) return;

    $meta   = get_option( AT_IG_REFRESH_META, [] );
    if ( ! is_array( $meta ) ) $meta = [];
    $now    = time();
    $fallos = [];

    foreach ( $channels as $ch ) {
        $cid  = (int) $ch->id;
        $info = isset( $meta[ $cid ] ) && is_array( $meta[ $cid ] ) ? $meta[ $cid ] : [];

        // Si sabemos cuando vence y aun queda margen, no lo tocamos: cada refresh
        // rota el valor del token y eso invalida los respaldos que tenga el equipo.
        if ( ! empty( $info['expires_at'] ) && ( $info['expires_at'] - $now ) > AT_IG_REFRESH_THRESHOLD ) {
            continue;
        }
        // Meta rechaza refrescar un token con menos de 24h de vida.
        if ( ! empty( $info['obtained_at'] ) && ( $now - $info['obtained_at'] ) < AT_IG_REFRESH_MIN_AGE ) {
            continue;
        }

        $resp = wp_remote_get(
            add_query_arg(
                [ 'grant_type' => 'ig_refresh_token', 'access_token' => $ch->bot_token ],
                'https://graph.instagram.com/refresh_access_token'
            ),
            [ 'timeout' => 20 ]
        );

        if ( is_wp_error( $resp ) ) {
            $info['last_error'] = $resp->get_error_message();
            $fallos[] = sprintf( 'Canal %d (%s): %s', $cid, $ch->channel_name, $info['last_error'] );
            $meta[ $cid ] = $info;
            continue;
        }

        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        $code = (int) wp_remote_retrieve_response_code( $resp );

        if ( $code < 200 || $code >= 300 || empty( $body['access_token'] ) ) {
            $msg = $body['error']['message'] ?? ( 'HTTP ' . $code );

            // Meta exige que el token tenga mas de 24h antes de poder refrescarlo.
            // La primera corrida tras generar un token a mano cae siempre aqui: no es
            // un fallo real, solo hay que esperar. Se anota la edad y se reintenta manana,
            // sin mandar correo (si no, cada token nuevo generaria una falsa alarma).
            if ( stripos( $msg, '24 hour' ) !== false || stripos( $msg, '24 hours' ) !== false ) {
                $info['obtained_at'] = $now;
                $info['last_error']  = '';
                $meta[ $cid ] = $info;
                error_log( sprintf(
                    '[at-ig-token-refresh] Canal %d (%s): token aun muy nuevo para refrescar, se reintenta manana.',
                    $cid, $ch->channel_name
                ) );
                continue;
            }

            $info['last_error'] = $msg;
            $fallos[] = sprintf( 'Canal %d (%s): %s', $cid, $ch->channel_name, $info['last_error'] );
            $meta[ $cid ] = $info;
            continue;
        }

        $expires_in = (int) ( $body['expires_in'] ?? 0 );
        $updated    = $wpdb->update(
            $wpdb->prefix . 'omnichannel_channels',
            [ 'bot_token' => $body['access_token'] ],
            [ 'id' => $cid ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( $updated === false ) {
            $info['last_error'] = 'Token renovado en Meta pero fallo el UPDATE en la BD';
            $fallos[] = sprintf( 'Canal %d (%s): %s', $cid, $ch->channel_name, $info['last_error'] );
            $meta[ $cid ] = $info;
            continue;
        }

        $meta[ $cid ] = [
            'obtained_at'  => $now,
            'expires_at'   => $expires_in > 0 ? $now + $expires_in : $now + ( 60 * DAY_IN_SECONDS ),
            'last_refresh' => $now,
            'last_error'   => '',
        ];
        error_log( sprintf(
            '[at-ig-token-refresh] Canal %d (%s) renovado. Vence en %d dias.',
            $cid, $ch->channel_name, (int) round( $expires_in / DAY_IN_SECONDS )
        ) );
    }

    update_option( AT_IG_REFRESH_META, $meta, false );

    // Un fallo aqui es silencioso por naturaleza: nadie mira los logs hasta que algo
    // deja de publicar. Por eso se avisa por correo, como maximo una vez al dia.
    if ( $fallos ) {
        wp_mail(
            get_option( 'admin_email' ),
            '[AutomatizaTech] Fallo la renovacion del token de Instagram',
            "No se pudo renovar el token de larga duracion de Instagram:\n\n- "
            . implode( "\n- ", $fallos )
            . "\n\nSi el token ya vencio, la renovacion automatica NO puede recuperarlo: "
            . "hay que generar uno nuevo en developers.facebook.com (Casos de uso -> "
            . "Administrar mensajes y contenido en Instagram -> Configuracion de la API "
            . "con inicio de sesion de Instagram -> Generar tokens de acceso) y pegarlo "
            . "en el canal desde el Portal OmniCliente.\n"
        );
    }
}
