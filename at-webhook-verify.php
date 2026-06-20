<?php
/**
 * AT Webhook HMAC Verifier
 *
 * Verifica la firma HMAC-SHA256 de un webhook.
 * Headers esperados:
 *   X-AT-Signature: hex(hmac_sha256(timestamp + "." + body, secret))
 *   X-AT-Timestamp: unix epoch seconds (rechaza si delta > 300s)
 *
 * Si los headers no estan presentes, devuelve null para que el caller
 * decida si rechazar o caer al modo legacy (transitorio).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'at_webhook_verify_hmac' ) ) {
    /**
     * @param string $body          Body crudo del request.
     * @param string $secret        Secret compartido.
     * @param int    $tolerance     Segundos de tolerancia para clock skew. Default 300 (5min).
     * @return bool|null            true=valida, false=invalida, null=headers ausentes.
     */
    function at_webhook_verify_hmac( $body, $secret, $tolerance = 300 ) {
        $sig = $_SERVER['HTTP_X_AT_SIGNATURE'] ?? '';
        $ts  = $_SERVER['HTTP_X_AT_TIMESTAMP'] ?? '';
        if ( $sig === '' || $ts === '' ) {
            return null;
        }
        $ts = (int) $ts;
        if ( abs( time() - $ts ) > $tolerance ) {
            return false;
        }
        if ( $secret === '' ) {
            return false;
        }
        $expected = hash_hmac( 'sha256', $ts . '.' . $body, $secret );
        return hash_equals( $expected, (string) $sig );
    }
}
