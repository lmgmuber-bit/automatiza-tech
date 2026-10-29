<?php
/**
 * Asistente de directos de Instagram: funciones puras de la derivación a una persona,
 * la vuelta automática al bot y el contexto de quien llega desde un comentario.
 * Sin WordPress ni base de datos: las usa OmnichannelController y se prueban solas
 * (tests/omnicanal/derivacion-puras-test.php).
 */
if (!defined('ABSPATH') && PHP_SAPI !== 'cli') { exit; }

if (!function_exists('omni_derivacion_config')) {
    /**
     * Configuración de la derivación del canal, desde bot_configs.custom_functions_json:
     * {"derivacion": {"agente_id": N, "correos": [...]}}. Correos válidos, en minúscula y sin repetir.
     */
    function omni_derivacion_config($custom_functions_json) {
        $vacia = ['agente_id' => 0, 'correos' => []];
        $cfg = json_decode((string) $custom_functions_json, true);
        if (!is_array($cfg) || !isset($cfg['derivacion']) || !is_array($cfg['derivacion'])) return $vacia;
        $d = $cfg['derivacion'];
        $correos = [];
        foreach ((isset($d['correos']) && is_array($d['correos'])) ? $d['correos'] : [] as $c) {
            $c = strtolower(trim((string) $c));
            if (filter_var($c, FILTER_VALIDATE_EMAIL) && !in_array($c, $correos, true)) $correos[] = $c;
        }
        return ['agente_id' => max(0, (int) ($d['agente_id'] ?? 0)), 'correos' => $correos];
    }
}

if (!function_exists('omni_sin_bot')) {
    /**
     * Cuentas a las que el bot nunca responde (ej. la cuenta personal de Luis, que le manda reels a la de AT):
     * bot_configs.custom_functions_json.sin_bot = ["<igsid>", {"igsid": "<igsid>", "nota": "..."}]. Sus mensajes
     * se guardan y se ven en el Portal; solo no van a n8n.
     */
    function omni_sin_bot($custom_functions_json, $external_contact_id) {
        $id = trim((string) $external_contact_id);
        $cfg = json_decode((string) $custom_functions_json, true);
        if ($id === '' || !is_array($cfg) || !isset($cfg['sin_bot']) || !is_array($cfg['sin_bot'])) return false;
        foreach ($cfg['sin_bot'] as $e) {
            $v = is_array($e) ? ($e['igsid'] ?? '') : $e;
            if (is_scalar($v) && trim((string) $v) === $id) return true;
        }
        return false;
    }
}

if (!function_exists('omni_debe_volver_al_bot')) {
    /**
     * Una conversación de Instagram que derivó el asistente vuelve al bot cuando la persona escribe
     * de nuevo y pasaron más de $umbral segundos desde la última actividad ($last_message_at_gmt, GMT).
     * Las que tomó a mano un ejecutivo no vuelven solas (decisión de Luis, 24-oct).
     */
    function omni_debe_volver_al_bot($channel_type, $intervention_mode, $last_message_at_gmt, $ahora_ts, $derivada_por_bot, $umbral = 86400) {
        if ($channel_type !== 'instagram' || $intervention_mode !== 'human' || !$derivada_por_bot) return false;
        if (!$last_message_at_gmt) return false;
        $ultimo = strtotime($last_message_at_gmt . ' UTC');
        if ($ultimo === false) return false;
        return ((int) $ahora_ts - $ultimo) > (int) $umbral;
    }
}

if (!function_exists('omni_contexto_comentario')) {
    /**
     * Si la persona llegó desde un comentario con palabra clave en los últimos $max_dias, devuelve
     * {origen, palabra, mensaje_privado, fecha} para que el asistente sepa qué está respondiendo.
     * $filas: mensajes de la conversación (sender_type, content, metadata_json, created_at GMT), el más nuevo primero.
     */
    function omni_contexto_comentario(array $filas, $ahora_ts, $max_dias = 7) {
        $palabra = null; $fecha = ''; $privado = '';
        foreach ($filas as $f) {
            $m = json_decode((string) ($f['metadata_json'] ?? ''), true);
            if (!is_array($m) || ($m['origen'] ?? '') !== 'comentario_palabra_clave') continue;
            if ($palabra === null) {
                $palabra = (string) ($m['keyword'] ?? '');
                $fecha = (string) ($f['created_at'] ?? '');
            } elseif ((string) ($m['keyword'] ?? '') !== $palabra) {
                break;   // un comentario anterior, de otra palabra
            }
            if ($privado === '' && ($f['sender_type'] ?? '') === 'bot') $privado = (string) ($f['content'] ?? '');
        }
        if ($palabra === null || $palabra === '') return null;
        $ts = strtotime($fecha . ' UTC');
        if ($ts === false || ((int) $ahora_ts - $ts) > (int) $max_dias * 86400) return null;
        return ['origen' => 'comentario_palabra_clave', 'palabra' => $palabra,
            'mensaje_privado' => mb_substr($privado, 0, 1000), 'fecha' => $fecha];
    }
}

if (!function_exists('omni_derivacion_correo')) {
    /**
     * Correo de «una persona de Instagram quiere hablar contigo». $ultimos: [['dir' => 'in'|'out', 'texto' => ...]],
     * del más antiguo al más nuevo; se muestran los últimos 6. Todo el texto va escapado.
     */
    function omni_derivacion_correo(array $conv, $motivo, array $ultimos, $url_portal) {
        $e = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $nombre = trim((string) ($conv['contact_name'] ?? ''));
        if (preg_match('/^Usuario Instagram /', $nombre)) $nombre = '';
        $asunto = $nombre !== '' ? "Instagram: {$nombre} quiere hablar contigo" : 'Instagram: una persona quiere hablar contigo';

        $filas = '';
        foreach (array_slice($ultimos, -6) as $m) {
            $es_persona = ($m['dir'] ?? '') === 'in';
            $quien = $es_persona ? ($nombre !== '' ? $nombre : 'La persona') : 'Asistente';
            $fondo = $es_persona ? '#f0fdfa' : '#f8f9ff';
            $filas .= '<tr><td style="padding:4px 0;"><div style="background:' . $fondo . ';border-left:3px solid #0d9488;border-radius:8px;padding:8px 12px;">'
                . '<div style="font-size:11px;color:#666;"><strong>' . $e($quien) . '</strong></div>'
                . '<div style="font-size:13px;color:#333;line-height:1.4;">' . $e(mb_substr((string) ($m['texto'] ?? ''), 0, 300)) . '</div></div></td></tr>';
        }
        if ($filas === '') $filas = '<tr><td style="padding:8px;color:#999;font-size:13px;">Sin mensajes previos</td></tr>';

        $usuario = $nombre !== '' ? $nombre : 'Una persona';
        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f5f5f5;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:20px;"><tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;">'
            . '<tr><td style="background:linear-gradient(135deg,#0d9488 0%,#14b8a6 50%,#06d6a0 100%);padding:24px;text-align:center;">'
            . '<h1 style="color:#fff;margin:0;font-size:22px;">📸 Instagram: quieren hablar contigo</h1>'
            . '<p style="color:#f0f0f0;margin:8px 0 0;font-size:13px;">Portal OmniCliente &mdash; AutomatizaTech</p></td></tr>'
            . '<tr><td style="padding:28px;">'
            . '<p style="color:#333;font-size:15px;margin:0 0 16px;"><strong>' . $e($usuario) . '</strong> pidió hablar con una persona por mensaje directo de Instagram. El asistente dejó de responder en esa conversación.</p>'
            . '<div style="background:#fff9f0;padding:16px;border-radius:8px;border-left:4px solid #ff9800;margin-bottom:16px;">'
            . '<strong style="color:#b45309;">Lo que pidió:</strong> ' . $e($motivo !== '' ? $motivo : 'Hablar con una persona') . '</div>'
            . '<h3 style="color:#0d9488;margin:0 0 8px;font-size:15px;">💬 Últimos mensajes</h3>'
            . '<table width="100%" cellpadding="0" cellspacing="0">' . $filas . '</table>'
            . '<p style="color:#b91c1c;font-size:13px;margin:16px 0;">Instagram solo permite responder dentro de las 24 horas desde el último mensaje de la persona.</p>'
            . '<div style="text-align:center;margin-top:20px;"><a href="' . $e($url_portal) . '" style="display:inline-block;background:#0d9488;color:#fff;padding:12px 28px;text-decoration:none;border-radius:25px;font-weight:bold;">Abrir la conversación en el Portal</a></div>'
            . '<p style="color:#666;font-size:12px;margin:16px 0 0;">También puedes responder desde la app de Instagram. Para devolverla al asistente, libérala en el Portal.</p>'
            . '</td></tr>'
            . '<tr><td style="background:#f8f9ff;padding:16px;text-align:center;border-top:1px solid #e0e0e0;color:#999;font-size:11px;">Aviso automático del Portal OmniCliente &mdash; AutomatizaTech</td></tr>'
            . '</table></td></tr></table></body></html>';
        return ['asunto' => $asunto, 'html' => $html];
    }
}
