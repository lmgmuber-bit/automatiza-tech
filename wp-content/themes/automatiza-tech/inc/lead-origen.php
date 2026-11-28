<?php
/**
 * Origen de un lead (POST /leads), sin WordPress. Lo usa automatiza_tech_save_lead() y se prueba solo
 * (tests/omnicanal/derivacion-puras-test.php).
 */
if (!defined('ABSPATH') && PHP_SAPI !== 'cli') { exit; }

if (!function_exists('automatiza_tech_lead_origen')) {
    /**
     * Un origen explícito distinto de 'web' se respeta. Si no:
     * - session_id que empieza con «ig:» → instagram (asistente de directos, desde el 24-oct-2026);
     * - session_id con «whatsapp», o teléfono sin session_id → whatsapp (regla de siempre);
     * - si no, web.
     */
    function automatiza_tech_lead_origen($source, $session_id, $phone) {
        $source = (string) $source;
        if ($source !== 'web') return $source;
        $sesion = strtolower((string) $session_id);
        if (strpos($sesion, 'ig:') === 0) return 'instagram';
        if (strpos($sesion, 'whatsapp') !== false) return 'whatsapp';
        if ((string) $phone !== '' && $sesion === '') return 'whatsapp';
        return 'web';
    }
}
