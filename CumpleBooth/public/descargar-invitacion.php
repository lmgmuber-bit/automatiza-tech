<?php
/**
 * descargar-invitacion.php — enlace opaco de descarga de invitaciones.
 * Requiere token vía GET ?t=<token>. Los archivos se sirven desde almacenamiento privado.
 */
require __DIR__ . '/lib.php';

$token = isset($_GET['t']) ? (string) $_GET['t'] : '';

function cb_invitation_deny(): void
{
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$requestedType = isset($_GET['type']) && in_array((string) $_GET['type'], ['image', 'video', 'narracion_inicio'], true) ? (string) $_GET['type'] : null;

// Vista previa para la tarjeta de WhatsApp y redes. Mismo archivo y mismo
// control de token; cambia solo cómo se entrega: los rastreadores no renderizan
// algo marcado `attachment` ni guardan lo que va `no-store`. Se limita a la
// imagen: el video y la narración siguen igual que siempre.
$isSocialPreview = $requestedType === 'image'
    && isset($_GET['preview']) && (string) $_GET['preview'] === '1';

// Endpoint público sin auth: rate limit persistente por IP antes de cualquier
// otra cosa, para frenar bursts de enumeración/abuso de descarga.
$dlLimit = cb_rate_limit('invitation-download', cb_request_identity(), 60, 600, 600);
if (!$dlLimit['allowed']) {
    header('Retry-After: ' . max(1, (int) $dlLimit['retry_after']));
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'rate_limited', 'retry_after' => (int) $dlLimit['retry_after']]);
    exit;
}

if (!cb_invitation_public_token_is_valid($token)) {
    cb_invitation_deny();
}

try {
    $invitation = cb_load_invitation_by_public_token($token);
    if (!$invitation) {
        cb_invitation_deny();
    }
    if ((string) $invitation['status'] !== 'published') {
        cb_invitation_deny();
    }
    if (!empty($invitation['expires_at']) && strtotime((string) $invitation['expires_at']) < time()) {
        cb_invitation_deny();
    }

    // Buscar output aprobado según tipo solicitado o por defecto imagen > video
    $outputs = cb_load_invitation_outputs((int) $invitation['id']);
    $selected = null;
    $priority = ['personalized_image' => 1, 'personalized_video' => 2, 'personalized_narration_intro' => 3];
    foreach ($outputs as $output) {
        if ((string) $output['status'] !== 'approved') {
            continue;
        }
        $outputType = (string) $output['output_type'];
        if ($requestedType === 'image' && $outputType !== 'personalized_image') {
            continue;
        }
        if ($requestedType === 'video' && $outputType !== 'personalized_video') {
            continue;
        }
        if ($requestedType === 'narracion_inicio' && $outputType !== 'personalized_narration_intro') {
            continue;
        }
        if ($selected === null || ($priority[$outputType] ?? 99) < ($priority[(string) $selected['output_type']] ?? 99)) {
            $selected = $output;
        }
    }
    if (!$selected) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'not_ready']);
        exit;
    }

    $filePath = cb_invitation_file_path((string) $selected['file_storage_key']);
    if (!$filePath || !is_file($filePath)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'file_not_found']);
        exit;
    }

    $mime = (string) ($selected['file_mime'] ?: 'application/octet-stream');
    /* El nombre con el que se guarda.

       Cuando esta cabecera trae `filename`, MANDA sobre el atributo
       `download` del enlace: poner un nombre lindo en el HTML y dejar acá
       otro distinto significa que gana este y el del HTML no se usa nunca.
       Los dos tienen que decir lo mismo.

       Sigue sin exponer nada interno —el nombre del cumpleañero o del bebé
       está en toda la página— y pasa por el mismo slug que el resto: sin
       tildes, sin ñ y en minúscula, que es lo que sobrevive a un FTP y a
       reenviarlo desde un teléfono. */
    $quien = trim((string) ($invitation['birthday_person_name'] ?? ''));
    $prefijo = (string) ($invitation['event_type'] ?? '') === 'baby_shower'
        ? 'baby-shower-'
        : 'cumpleanos-';
    // Sin nombre, un baby shower baja como "baby-shower.jpg" a secas: el
    // respaldo "cumpleclick" pondría la marca donde va el nombre del bebé, y
    // en baby shower ese campo está vacío a propósito, no por error.
    $base = $quien !== ''
        ? $prefijo . cb_invitation_name_slug($quien)
        : rtrim($prefijo, '-');
    $fileName = $base . '.' . pathinfo($filePath, PATHINFO_EXTENSION);

    // Incrementar contador de descargas de forma no bloqueante. El rastreador
    // que arma la tarjeta no descargó nada: contarlo inflaría la métrica que
    // Luis usa para saber cuántas familias guardaron su invitación.
    if (!$isSocialPreview) {
        cb_increment_invitation_download((int) $invitation['id']);
    }

    // La narración va dentro de un <audio> de la propia página: si se fuerza
    // "attachment" algunos navegadores intentan descargar en vez de reproducir.
    // La vista previa social necesita lo mismo, más una caché pública corta:
    // WhatsApp descarta la imagen si viene como adjunto o marcada no-store.
    $disposition = ($selected['output_type'] === 'personalized_narration_intro' || $isSocialPreview)
        ? 'inline'
        : 'attachment';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: ' . ($isSocialPreview ? 'public, max-age=86400' : 'private, no-store'));
    header('X-Robots-Tag: noindex, nofollow');
    readfile($filePath);
    exit;
} catch (Throwable $e) {
    error_log('CumpleClick descargar invitacion: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'service_unavailable']);
    exit;
}
