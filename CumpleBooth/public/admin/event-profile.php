<?php
/**
 * Perfil del protagonista por evento. La vista administra datos y prepara
 * solicitudes de video; nunca llama a Higgsfield ni consume créditos.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/config.php';

$adminSecureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $adminSecureCookie, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['csrf'];
}

function admin_csrf_check(): bool
{
    $token = $_POST['csrf'] ?? '';
    return is_string($token) && $token !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $token);
}

function admin_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(admin_csrf_token()) . '">';
}

function admin_icon(string $name): string
{
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        'warn' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><circle cx="12" cy="12" r="10"/><path d="M12 17h.01"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/></svg>',
        'up' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>',
        'down' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
        'video' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="15" height="12" rx="2"/><path d="m17 10 5-3v10l-5-3z"/></svg>',
        'photo' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function admin_event_profile_actor(): string
{
    return 'admin:' . substr(hash('sha256', session_id()), 0, 16);
}

function admin_event_profile_prompt(array $profile, array $preset, string $phrase, string $style): string
{
    $scene = trim((string) ($preset['theme']['scene'] ?? 'original premium celebration inspired only by the active theme palette, lighting and atmosphere'));
    return "ONE SHOT — 5 seconds — vertical 9:16 — cinematic premium celebration.\n\n"
        . "Create this original themed environment: {$scene}. Emotional style: {$style}. "
        . "Smooth cinematic push-in, layered foreground and background, elegant particles, clear visual center and a graceful final reveal. "
        . "Use the active invitation background only as reference for palette, lighting, depth and atmosphere. "
        . "Do not reproduce recognizable characters, franchises, logos, emblems or protected designs.\n\n"
        . "No written text, names, numbers, gift information or private information inside the video. "
        . "Leave a clean safe area for a crisp frontend HTML title overlay added later. "
        . "No real person appears unless an explicitly authorized reference is attached. "
        . ($phrase !== '' ? "Intended emotional beat: {$phrase}." : 'End on a warm, surprising reveal.');
}

function admin_event_profile_people_from_post($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $people = [];
    foreach (array_slice(array_values($raw), 0, 10) as $personIndex => $person) {
        if (!is_array($person)) {
            continue;
        }
        $fields = [];
        foreach (array_slice(array_values(is_array($person['fields'] ?? null) ? $person['fields'] : []), 0, 60) as $fieldIndex => $field) {
            if (!is_array($field)) {
                continue;
            }
            $fields[] = [
                'section_key' => (string) ($field['section_key'] ?? 'custom'),
                'field_key' => (string) ($field['field_key'] ?? 'custom_' . ($fieldIndex + 1)),
                'label' => (string) ($field['label'] ?? ''),
                'value' => (string) ($field['value'] ?? ''),
                'value_type' => (string) ($field['value_type'] ?? 'text'),
                'is_public' => !empty($field['is_public']),
                'sort_order' => ($fieldIndex + 1) * 10,
            ];
        }
        $people[] = [
            'id' => (int) ($person['id'] ?? 0),
            'display_name' => (string) ($person['display_name'] ?? ''),
            'nickname' => (string) ($person['nickname'] ?? ''),
            'intro_text' => (string) ($person['intro_text'] ?? ''),
            'is_public' => !empty($person['is_public']),
            'photo_public_consent' => !empty($person['photo_public_consent']),
            // El consentimiento IA cierra por defecto: solo true si llegó el checkbox explícito.
            'photo_ai_consent' => !empty($person['photo_ai_consent']),
            'sort_order' => ($personIndex + 1) * 10,
            'fields' => $fields,
        ];
    }
    return $people;
}

// ================== SESIÓN ADMIN ==================
$loggedIn = !empty($_SESSION['admin_logged']);
if ($loggedIn) {
    $idle = (int) cb_config('session_idle_seconds');
    $absolute = (int) cb_config('session_absolute_seconds');
    if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > $idle || time() - (int) ($_SESSION['admin_started'] ?? 0) > $absolute) {
        $_SESSION = [];
        session_destroy();
        $loggedIn = false;
    } else {
        $_SESSION['admin_seen'] = time();
    }
}
if (!$loggedIn) {
    $returnTo = 'index.php';
    if (is_string($_GET['party'] ?? null) && cb_valid_public_slug((string) $_GET['party'])) {
        $returnTo = 'event-profile.php?party=' . rawurlencode((string) $_GET['party']);
    }
    header('Location: index.php?return_to=' . rawurlencode($returnTo));
    exit;
}

// ================== EVENTO Y PERFIL ==================
$publicSlugRaw = is_string($_GET['party'] ?? null) ? (string) $_GET['party'] : '';
$publicSlug = cb_valid_public_slug($publicSlugRaw) ? $publicSlugRaw : '';
$party = $publicSlug !== '' ? cb_load_party_raw($publicSlug) : null;
$partyId = $party !== null ? cb_party_db_id($publicSlug) : null;
$errors = [];
$okMessage = null;
if ($party === null || $partyId === null) {
    $errors[] = 'El evento no existe o no pertenece al almacenamiento activo.';
} elseif (cb_storage_mode() !== 'db') {
    $errors[] = 'El Perfil del protagonista requiere storage_mode=db.';
} elseif (!function_exists('cb_event_profile_get')) {
    $errors[] = 'El módulo de perfiles no está disponible. Revisa la instalación y las migraciones.';
}

$profile = null;
if (!$errors && $partyId !== null) {
    try {
        $profile = cb_event_profile_get($partyId, true);
    } catch (Throwable $e) {
        error_log('CumpleClick admin event profile load: ' . $e->getMessage());
        $errors[] = 'No se pudo abrir el perfil. Ejecuta las migraciones pendientes y reintenta.';
    }
}

// ================== ACCIONES ==================
if (!$errors && $partyId !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check()) {
        $errors[] = 'Sesión expirada. Recarga la página e intenta nuevamente.';
    } else {
        $rate = cb_rate_limit('admin-event-profile:' . $partyId, cb_request_identity(), 40, 600, 600);
        if (!$rate['allowed']) {
            $errors[] = 'Demasiados cambios seguidos. Reintenta en ' . (int) $rate['retry_after'] . ' segundos.';
        } else {
            $action = (string) ($_POST['action'] ?? '');
            $actor = admin_event_profile_actor();
            try {
                if ($action === 'save_profile') {
                    $sectionOrder = [];
                    foreach (array_slice(array_values(is_array($_POST['sections'] ?? null) ? $_POST['sections'] : []), 0, 20) as $index => $section) {
                        if (!is_array($section)) { continue; }
                        $sectionOrder[] = [
                            'key' => (string) ($section['key'] ?? ''),
                            'label' => (string) ($section['label'] ?? ''),
                            'enabled' => !empty($section['enabled']),
                            'order' => ($index + 1) * 10,
                        ];
                    }
                    $saved = cb_event_profile_save($partyId, [
                        'enabled' => !empty($_POST['enabled']),
                        'event_type' => (string) ($_POST['event_type'] ?? 'child_birthday'),
                        'public_title' => (string) ($_POST['public_title'] ?? ''),
                        'cta_label' => (string) ($_POST['cta_label'] ?? ''),
                        'intro_style' => (string) ($_POST['intro_style'] ?? 'mágico'),
                        'intro_phrase' => (string) ($_POST['intro_phrase'] ?? ''),
                        'privacy_ack' => !empty($_POST['privacy_ack']),
                        'section_order' => $sectionOrder,
                    ], $actor);
                    cb_event_profile_replace_people($partyId, admin_event_profile_people_from_post($_POST['people'] ?? []), $actor);
                    $profile = cb_event_profile_get($partyId, true) ?? $saved;
                    $okMessage = 'Perfil y protagonistas guardados correctamente.';
                } elseif ($action === 'upload_photo') {
                    $personId = (int) ($_POST['person_id'] ?? 0);
                    $ownedIds = array_map(static fn(array $person): int => (int) ($person['id'] ?? 0), $profile['featured_people'] ?? []);
                    if ($personId < 1 || !in_array($personId, $ownedIds, true)) {
                        throw new InvalidArgumentException('El protagonista no pertenece a este evento.');
                    }
                    cb_event_profile_upload_media($partyId, $personId, 'photo', $_FILES['photo'] ?? [], [
                        'is_public' => !empty($_POST['is_public']),
                        'photo_public_consent' => !empty($_POST['photo_public_consent']),
                        'photo_ai_consent' => !empty($_POST['photo_ai_consent']),
                        'alt_text' => (string) ($_POST['alt_text'] ?? ''),
                    ], $actor);
                    $profile = cb_event_profile_get($partyId, true);
                    $okMessage = 'Foto validada y guardada en storage privado.';
                } elseif ($action === 'prepare_video') {
                    $preset = cb_event_profile_preset((string) ($profile['event_type'] ?? 'child_birthday'), (string) ($profile['theme_slug'] ?? ''));
                    $style = trim((string) ($_POST['emotional_style'] ?? $profile['intro_style'] ?? 'mágico'));
                    $phrase = trim((string) ($_POST['video_phrase'] ?? $profile['intro_phrase'] ?? ''));
                    $prompt = trim((string) ($_POST['prompt'] ?? ''));
                    if ($prompt === '') {
                        $prompt = admin_event_profile_prompt($profile, $preset, $phrase, $style);
                    }
                    $referenceIds = [];
                    foreach ($profile['featured_people'] ?? [] as $person) {
                        if (!empty($person['photo_ai_consent'])) { $referenceIds[] = (int) $person['id']; }
                    }
                    cb_event_profile_prepare_generation($partyId, [
                        'provider' => 'higgsfield',
                        'model_key' => (string) ($_POST['model_key'] ?? ''),
                        'duration_seconds' => (float) ($_POST['duration_seconds'] ?? 5),
                        'aspect_ratio' => '9:16',
                        'prompt' => $prompt,
                        'emotional_style' => $style,
                        'intro_phrase' => $phrase,
                        'theme_slug' => (string) ($profile['theme_slug'] ?? ''),
                        'reference_person_ids' => $referenceIds,
                        'quote_amount' => ($_POST['quote_amount'] ?? '') !== '' ? (float) $_POST['quote_amount'] : null,
                        'quote_credits' => ($_POST['quote_credits'] ?? '') !== '' ? (int) $_POST['quote_credits'] : null,
                        'quote_currency' => (string) ($_POST['quote_currency'] ?? 'USD'),
                        'quote_expires_at' => (string) ($_POST['quote_expires_at'] ?? ''),
                    ], $actor);
                    $profile = cb_event_profile_get($partyId, true);
                    $okMessage = 'Solicitud preparada. No se llamó a Higgsfield ni se consumieron créditos.';
                } elseif ($action === 'approve_video') {
                    $generationId = (int) ($_POST['generation_id'] ?? 0);
                    $ownedGenerationIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $profile['generations'] ?? []);
                    if ($generationId < 1 || !in_array($generationId, $ownedGenerationIds, true)) {
                        throw new InvalidArgumentException('La solicitud no pertenece a este evento.');
                    }
                    if (empty($_POST['confirm_approval'])) {
                        throw new InvalidArgumentException('Debes confirmar explícitamente modelo, costo y prompt final.');
                    }
                    cb_event_profile_approve_generation($generationId, $actor);
                    $profile = cb_event_profile_get($partyId, true);
                    $okMessage = 'Solicitud aprobada para ejecución posterior. Aún no se generó ningún video.';
                }
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            } catch (Throwable $e) {
                error_log('CumpleClick admin event profile action: ' . $e->getMessage());
                $errors[] = 'No se pudo completar la acción. Revisa almacenamiento, migraciones y logs.';
            }
        }
    }
}

$presets = function_exists('cb_event_profile_presets') ? cb_event_profile_presets() : ['event_types' => []];
$eventTypes = is_array($presets['event_types'] ?? null) ? $presets['event_types'] : [];
$eventType = (string) ($profile['event_type'] ?? $presets['default_event_type'] ?? 'child_birthday');
$themeSlug = (string) ($profile['theme_slug'] ?? $party['tema'] ?? '');
$preset = function_exists('cb_event_profile_preset') ? cb_event_profile_preset($eventType, $themeSlug) : ['event' => [], 'theme' => []];
$eventPreset = is_array($preset['event'] ?? null) ? $preset['event'] : [];
$themePreset = is_array($preset['theme'] ?? null) ? $preset['theme'] : [];
$sections = is_array($profile['sections'] ?? null) && $profile['sections'] ? $profile['sections'] : ($eventPreset['sections'] ?? []);
$people = is_array($profile['featured_people'] ?? null) ? $profile['featured_people'] : [];
if (!$people && $party !== null) {
    $defaultFields = [];
    foreach ($eventPreset['fields'] ?? [] as $index => $field) {
        if (!is_array($field)) { continue; }
        $defaultFields[] = [
            'section_key' => $field['section'] ?? 'custom', 'field_key' => $field['key'] ?? 'custom_' . ($index + 1),
            'public_label' => $field['label'] ?? '', 'value_text' => '', 'value_type' => $field['type'] ?? 'text',
            'is_public' => 0, 'sort_order' => $field['order'] ?? (($index + 1) * 10),
        ];
    }
    $people[] = [
        'id' => 0, 'display_name' => (string) ($party['birthday_person_name'] ?? $party['nombre'] ?? ''),
        'nickname' => '', 'intro_text' => '', 'is_public' => 1, 'sort_order' => 10,
        'photo_public_consent' => 0, 'photo_ai_consent' => 0, 'fields' => $defaultFields, 'media' => [],
    ];
}
$generations = is_array($profile['generations'] ?? null) ? $profile['generations'] : [];
$latestGeneration = $generations[0] ?? null;
$publicInvitationUrl = null;
if ($partyId !== null && function_exists('cb_list_invitations') && function_exists('cb_invitation_share_token')) {
    try {
        foreach (cb_list_invitations($partyId) as $candidateInvitation) {
            if ((string) ($candidateInvitation['status'] ?? '') !== 'published') {
                continue;
            }
            if (!empty($candidateInvitation['expires_at']) && strtotime((string) $candidateInvitation['expires_at']) < time()) {
                continue;
            }
            $publicInvitationUrl = cb_invitation_public_url(
                cb_invitation_share_token((int) $candidateInvitation['id'])
            );
            break;
        }
    } catch (Throwable $e) {
        error_log('CumpleClick admin event profile public link: ' . $e->getMessage());
    }
}
$defaultPrompt = admin_event_profile_prompt($profile ?? [], $preset, (string) ($profile['intro_phrase'] ?? ''), (string) ($profile['intro_style'] ?? $themePreset['intro_style'] ?? 'mágico'));

// Estado real de publicación. Un perfil puede estar activado y aun así no
// mostrar nada: los campos nacen ocultos, así que activar no basta.
$hasVisibleContent = false;
foreach ($people as $statusPerson) {
    if (empty($statusPerson['is_public']) && array_key_exists('is_public', $statusPerson)) {
        continue;
    }
    foreach (is_array($statusPerson['fields'] ?? null) ? $statusPerson['fields'] : [] as $statusField) {
        if (!is_array($statusField) || empty($statusField['is_public'])) {
            continue;
        }
        if (trim((string) ($statusField['value_text'] ?? $statusField['value'] ?? '')) !== '') {
            $hasVisibleContent = true;
            break 2;
        }
    }
    if (trim((string) ($statusPerson['intro_text'] ?? '')) !== '') {
        $hasVisibleContent = true;
        break;
    }
}
if (!empty($profile['is_enabled']) && $hasVisibleContent) {
    $profileState = 'live';
    $profileStateLabel = 'Publicado: los invitados ven el acceso';
} elseif (!empty($profile['is_enabled'])) {
    $profileState = 'incomplete';
    $profileStateLabel = 'Activado pero sin contenido visible: el invitado todavía no ve el acceso';
} else {
    $profileState = 'off';
    $profileStateLabel = 'Desactivado: la invitación no cambia';
}

// Índice de secciones para agrupar los campos y evitar la lista plana.
$sectionIndex = [];
foreach (array_values($sections) as $sectionRow) {
    $key = (string) ($sectionRow['section_key'] ?? $sectionRow['key'] ?? '');
    if ($key === '') {
        continue;
    }
    $sectionIndex[$key] = trim((string) ($sectionRow['public_label'] ?? $sectionRow['label'] ?? $key));
}
if (!$sectionIndex) {
    $sectionIndex = ['custom' => 'Otros datos'];
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Perfil del protagonista · CumpleClick Admin</title>
<style><?php require __DIR__ . '/_style.css.php'; ?></style>
</head>
<body>
<div class="wrap event-profile-admin">
  <header class="topbar">
    <div class="logo"><img src="../brand/cumpleclick-mark.svg" alt="CumpleClick" width="36" height="36"> CumpleClick <span>Admin</span></div>
    <a class="btn btn-ghost" href="index.php"><?= admin_icon('back') ?> Volver a fiestas</a>
  </header>

  <main>
    <section class="profile-page-head">
      <div>
        <p class="profile-eyebrow">Evento · <?= h($party['admin_label'] ?? $publicSlug) ?></p>
        <h1>Perfil del protagonista</h1>
        <p class="muted">Configura una experiencia pública opcional. El contenido se comparte entre todas las invitaciones del evento.</p>
      </div>
      <div class="profile-page-actions">
        <p class="profile-status" data-state="<?= h($profileState) ?>"><span class="profile-status-dot" aria-hidden="true"></span><?= h($profileStateLabel) ?></p>
        <?php if ($publicInvitationUrl !== null): ?>
          <a class="btn btn-ghost" href="<?= h($publicInvitationUrl) ?>" target="_blank" rel="noopener" data-public-invitation-link>Ver como invitado</a>
        <?php else: ?>
          <a class="btn btn-ghost" href="invitations.php?party=<?= rawurlencode($publicSlug) ?>">Administrar invitaciones</a>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($okMessage !== null): ?><p class="alert alert-ok"><?= admin_icon('check') ?> <?= h($okMessage) ?></p><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><?= admin_icon('warn') ?><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <?php if ($party !== null && $partyId !== null && function_exists('cb_event_profile_get')): ?>
    <form method="post" action="event-profile.php?party=<?= rawurlencode($publicSlug) ?>" class="profile-stack" id="profile-form">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="save_profile">

      <section class="card profile-settings-card">
        <div class="profile-card-head">
          <div><h2>Publicación</h2><p class="muted small">Si está apagado, las invitaciones actuales no cambian.</p></div>
          <label class="profile-switch"><input type="checkbox" name="enabled" value="1" <?= !empty($profile['is_enabled']) ? 'checked' : '' ?>><span aria-hidden="true"></span> Activar perfil</label>
        </div>
        <div class="profile-grid profile-grid--2">
          <div class="field">
            <label for="event-type">Tipo de evento</label>
            <select id="event-type" name="event_type">
              <?php foreach ($eventTypes as $typeKey => $typeData):
                  $typeIsActive = (string) ($typeData['status'] ?? '') === 'active';
              ?>
                <option value="<?= h($typeKey) ?>" <?= $eventType === $typeKey ? 'selected' : '' ?> <?= $typeIsActive ? '' : 'disabled' ?>><?= h($typeData['label'] ?? $typeKey) ?><?= !$typeIsActive ? ' · preparado para futuro' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <small>La interfaz final activa en esta etapa es Cumpleaños infantil; los demás presets conservan la arquitectura extensible.</small>
          </div>
          <div class="field"><label for="public-title">Título público</label><input id="public-title" type="text" name="public_title" maxlength="160" value="<?= h($profile['public_title'] ?? $eventPreset['title_suggestions'][0] ?? 'Conoce al protagonista') ?>" list="profile-title-suggestions"><datalist id="profile-title-suggestions"><?php foreach ($eventPreset['title_suggestions'] ?? [] as $suggestion): ?><option value="<?= h($suggestion) ?>"><?php endforeach; ?></datalist></div>
          <div class="field"><label for="cta-label">Texto del acceso</label><input id="cta-label" type="text" name="cta_label" maxlength="120" value="<?= h($profile['cta_label'] ?? $eventPreset['cta_suggestions'][0] ?? 'Conoce al protagonista') ?>"></div>
          <div class="field"><label for="intro-style">Estilo emocional</label><select id="intro-style" name="intro_style"><?php $styles = $eventPreset['intro']['emotional_styles'] ?? ['mágico','épico','tierno','elegante','divertido']; foreach ($styles as $style): ?><option value="<?= h($style) ?>" <?= (string) ($profile['intro_style'] ?? $themePreset['intro_style'] ?? '') === (string) $style ? 'selected' : '' ?>><?= h(ucfirst((string) $style)) ?></option><?php endforeach; ?></select></div>
          <div class="field profile-grid-span"><label for="intro-phrase">Frase corta de introducción</label><input id="intro-phrase" type="text" name="intro_phrase" maxlength="160" value="<?= h($profile['intro_phrase'] ?? '') ?>" placeholder="Ej. Hoy conocerás a la estrella de esta celebración"></div>
        </div>
      </section>

      <section class="card">
        <div class="profile-card-head"><div><h2>Orden de secciones</h2><p class="muted small">Usa los botones para cambiar el orden que verá el invitado.</p></div></div>
        <div class="profile-sort-list" data-sort-list="sections">
          <?php foreach (array_values($sections) as $sectionRowIndex => $section): $sectionKey = (string) ($section['section_key'] ?? $section['key'] ?? 'custom'); ?>
          <div class="profile-sort-row" data-sort-item>
            <input type="hidden" data-sort-name="key" name="sections[<?= $sectionRowIndex ?>][key]" value="<?= h($sectionKey) ?>">
            <input type="text" data-sort-name="label" name="sections[<?= $sectionRowIndex ?>][label]" maxlength="100" value="<?= h($section['public_label'] ?? $section['label'] ?? $sectionKey) ?>" aria-label="Título público de la sección">
            <label class="checkbox-field"><input type="checkbox" data-sort-name="enabled" name="sections[<?= $sectionRowIndex ?>][enabled]" value="1" <?= !array_key_exists('is_public', $section) || !empty($section['is_public']) || !empty($section['enabled']) ? 'checked' : '' ?>> Visible</label>
            <div class="profile-order-actions"><button type="button" class="btn btn-icon" data-move="up" aria-label="Subir sección"><?= admin_icon('up') ?></button><button type="button" class="btn btn-icon" data-move="down" aria-label="Bajar sección"><?= admin_icon('down') ?></button></div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card">
        <div class="profile-card-head"><div><h2>Protagonistas</h2><p class="muted small">Admite una o más personas y queda preparado para bodas u otros eventos.</p></div><button type="button" class="btn btn-primary" id="add-person"><?= admin_icon('plus') ?> Agregar protagonista</button></div>
        <div id="people-list" class="profile-people" data-sort-list="people">
          <?php foreach (array_values($people) as $personIndex => $person): ?>
          <article class="profile-person" data-person data-sort-item>
            <input type="hidden" data-person-name="id" name="people[<?= $personIndex ?>][id]" value="<?= (int) ($person['id'] ?? 0) ?>">
            <div class="profile-person-head"><h3><span data-person-title><?= h(trim((string) ($person['display_name'] ?? '')) !== '' ? $person['display_name'] : 'Protagonista ' . ($personIndex + 1)) ?></span> <span class="profile-person-index">#<span data-person-number><?= $personIndex + 1 ?></span></span></h3><div class="profile-order-actions"><button type="button" class="btn btn-icon" data-move="up" aria-label="Subir protagonista"><?= admin_icon('up') ?></button><button type="button" class="btn btn-icon" data-move="down" aria-label="Bajar protagonista"><?= admin_icon('down') ?></button><button type="button" class="btn btn-danger" data-remove-person><?= admin_icon('trash') ?> Quitar</button></div></div>
            <div class="profile-grid profile-grid--2">
              <div class="field"><label>Nombre público o apodo</label><input data-person-name="display_name" type="text" name="people[<?= $personIndex ?>][display_name]" maxlength="120" required value="<?= h($person['display_name'] ?? '') ?>"></div>
              <div class="field"><label>Apodo adicional (opcional)</label><input data-person-name="nickname" type="text" name="people[<?= $personIndex ?>][nickname]" maxlength="120" value="<?= h($person['nickname'] ?? '') ?>"></div>
              <div class="field profile-grid-span"><label>Presentación breve</label><textarea data-person-name="intro_text" name="people[<?= $personIndex ?>][intro_text]" maxlength="600" rows="3" placeholder="Cuenta algo breve, alegre y apropiado para los invitados."><?= h($person['intro_text'] ?? '') ?></textarea></div>
            </div>
            <div class="profile-consents">
              <label class="checkbox-field"><input type="checkbox" data-person-name="is_public" name="people[<?= $personIndex ?>][is_public]" value="1" <?= !array_key_exists('is_public', $person) || !empty($person['is_public']) ? 'checked' : '' ?>> Mostrar protagonista</label>
              <label class="checkbox-field"><input type="checkbox" data-person-name="photo_public_consent" name="people[<?= $personIndex ?>][photo_public_consent]" value="1" <?= !empty($person['photo_public_consent']) ? 'checked' : '' ?>> Tengo autorización para publicar su foto</label>
              <label class="checkbox-field profile-ai-consent"><input type="checkbox" data-person-name="photo_ai_consent" name="people[<?= $personIndex ?>][photo_ai_consent]" value="1" <?= !empty($person['photo_ai_consent']) ? 'checked' : '' ?>> Autorización separada para usar la foto como referencia IA</label>
            </div>

            <div class="profile-fields-head"><h4>Qué contamos de esta persona</h4><p class="muted small">Cada bloque es una tarjeta en la ficha pública. Solo se publica lo marcado como visible.</p></div>
            <?php
                // Los campos se agrupan por sección en vez de mostrarse como una
                // lista plana: así el admin ve "Tallas" o "Regalos" como bloques
                // y no como catorce filas idénticas.
                $personFieldsRaw = array_values(is_array($person['fields'] ?? null) ? $person['fields'] : []);
                $grouped = [];
                foreach ($sectionIndex as $groupKey => $groupLabel) {
                    $grouped[$groupKey] = [];
                }
                foreach ($personFieldsRaw as $fieldPosition => $fieldRow) {
                    if (!is_array($fieldRow)) { continue; }
                    $fieldSection = (string) ($fieldRow['section_key'] ?? '');
                    if (!array_key_exists($fieldSection, $grouped)) {
                        $fieldSection = array_key_first($grouped);
                    }
                    $grouped[$fieldSection][] = [$fieldPosition, $fieldRow];
                }
            ?>
            <div class="profile-fields" data-fields data-sort-list="fields">
              <?php foreach ($grouped as $groupKey => $groupRows): $groupLabel = $sectionIndex[$groupKey] ?? $groupKey; ?>
              <section class="profile-field-group" data-section-group="<?= h($groupKey) ?>">
                <header class="profile-field-group-head">
                  <h5><?= h($groupLabel) ?></h5>
                  <button type="button" class="btn btn-ghost btn-sm" data-add-field data-section="<?= h($groupKey) ?>"><?= admin_icon('plus') ?> Agregar dato</button>
                </header>
                <div class="profile-field-rows" data-section-rows>
                  <?php if (!$groupRows): ?><p class="profile-group-empty" data-group-empty>Sin datos todavía en este bloque.</p><?php endif; ?>
                  <?php foreach ($groupRows as [$fieldIndex, $field]): ?>
                  <div class="profile-field-row" data-field data-sort-item>
                    <input type="hidden" data-field-name="field_key" name="people[<?= $personIndex ?>][fields][<?= $fieldIndex ?>][field_key]" value="<?= h($field['field_key'] ?? 'custom_' . ($fieldIndex + 1)) ?>">
                    <label class="profile-field-cell profile-field-cell--label"><span>Etiqueta</span><input data-field-name="label" type="text" name="people[<?= $personIndex ?>][fields][<?= $fieldIndex ?>][label]" maxlength="100" value="<?= h($field['public_label'] ?? $field['label'] ?? '') ?>" placeholder="Ej. Talla de polera"></label>
                    <label class="profile-field-cell profile-field-cell--value"><span>Valor</span><textarea data-field-name="value" name="people[<?= $personIndex ?>][fields][<?= $fieldIndex ?>][value]" maxlength="1200" rows="2" placeholder="Lo que verá el invitado"><?= h($field['value_text'] ?? $field['value'] ?? '') ?></textarea></label>
                    <label class="profile-field-cell profile-field-cell--section"><span>Bloque</span><select data-field-name="section_key" name="people[<?= $personIndex ?>][fields][<?= $fieldIndex ?>][section_key]"><?php foreach ($sectionIndex as $optionKey => $optionLabel): ?><option value="<?= h($optionKey) ?>" <?= $groupKey === $optionKey ? 'selected' : '' ?>><?= h($optionLabel) ?></option><?php endforeach; ?></select></label>
                    <input type="hidden" data-field-name="value_type" name="people[<?= $personIndex ?>][fields][<?= $fieldIndex ?>][value_type]" value="<?= h($field['value_type'] ?? 'text') ?>">
                    <label class="checkbox-field profile-field-visible"><input type="checkbox" data-field-name="is_public" name="people[<?= $personIndex ?>][fields][<?= $fieldIndex ?>][is_public]" value="1" <?= !empty($field['is_public']) ? 'checked' : '' ?>> Visible</label>
                    <div class="profile-order-actions"><button type="button" class="btn btn-icon" data-move="up" aria-label="Subir campo"><?= admin_icon('up') ?></button><button type="button" class="btn btn-icon" data-move="down" aria-label="Bajar campo"><?= admin_icon('down') ?></button><button type="button" class="btn btn-icon btn-danger" data-remove-field aria-label="Quitar campo"><?= admin_icon('trash') ?></button></div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </section>
              <?php endforeach; ?>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card privacy-card">
        <div class="privacy-icon"><?= admin_icon('warn') ?></div><div><h2>Privacidad de niños y familias</h2><p>No publiques dirección, teléfono, colegio, rutinas, horarios, documentos, correo ni otros datos sensibles. El sistema también bloqueará patrones riesgosos aunque marques el campo como privado.</p><label class="checkbox-field"><input type="checkbox" name="privacy_ack" value="1" <?= !empty($profile['privacy_confirmed_at']) ? 'checked' : '' ?>> Confirmo que revisé el contenido y tengo autorización para publicarlo.</label></div>
      </section>

      <div class="profile-savebar"><button type="submit" class="btn btn-cta">Guardar Perfil del protagonista</button><span class="muted small">Los cambios no se ven públicamente hasta activar el perfil y tener contenido visible.</span></div>
    </form>

    <?php foreach ($people as $person): if ((int) ($person['id'] ?? 0) < 1) continue; ?>
    <section class="card profile-photo-card">
      <div class="profile-card-head"><div><h2><?= admin_icon('photo') ?> Foto de <?= h($person['display_name'] ?? 'protagonista') ?></h2><p class="muted small">Opcional. Se guarda fuera del webroot y se valida por contenido real.</p></div><?php if (!empty($person['media'])): ?><span class="badge badge-ok">Foto asociada</span><?php endif; ?></div>
      <form method="post" enctype="multipart/form-data" action="event-profile.php?party=<?= rawurlencode($publicSlug) ?>" class="profile-upload-form">
        <?= admin_csrf_field() ?><input type="hidden" name="action" value="upload_photo"><input type="hidden" name="person_id" value="<?= (int) $person['id'] ?>">
        <div class="field"><label>Imagen JPG, PNG o WEBP (máx. 12 MB)</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></div>
        <div class="field"><label>Texto alternativo</label><input type="text" name="alt_text" maxlength="180" placeholder="Ej. Retrato sonriente de <?= h($person['display_name'] ?? '') ?>"></div>
        <label class="checkbox-field"><input type="checkbox" name="is_public" value="1"> Mostrar esta foto públicamente</label>
        <label class="checkbox-field"><input type="checkbox" name="photo_public_consent" value="1" <?= !empty($person['photo_public_consent']) ? 'checked' : '' ?>> Confirmo autorización de publicación</label>
        <label class="checkbox-field profile-ai-consent"><input type="checkbox" name="photo_ai_consent" value="1" <?= !empty($person['photo_ai_consent']) ? 'checked' : '' ?>> Confirmo autorización independiente para referencia IA</label>
        <button type="submit" class="btn btn-primary">Validar y guardar foto</button>
      </form>
    </section>
    <?php endforeach; ?>

    <section class="card profile-video-card">
      <div class="profile-card-head"><div><h2><?= admin_icon('video') ?> Video intro WOW</h2><p class="muted small">Este asistente solo guarda el prompt y una cotización verificada. No llama al proveedor.</p></div><span class="badge badge-warn">0 créditos consumidos aquí</span></div>
      <form method="post" action="event-profile.php?party=<?= rawurlencode($publicSlug) ?>" class="profile-stack">
        <?= admin_csrf_field() ?><input type="hidden" name="action" value="prepare_video">
        <div class="profile-grid profile-grid--3">
          <div class="field"><label>Duración</label><select name="duration_seconds"><option value="4">4 segundos</option><option value="5" selected>5 segundos</option><option value="6">6 segundos</option></select></div>
          <div class="field"><label>Modelo confirmado en models_explore</label><input type="text" name="model_key" maxlength="100" placeholder="Completar después de consultar Higgsfield"></div>
          <div class="field"><label>Estilo emocional</label><input type="text" name="emotional_style" maxlength="40" value="<?= h($profile['intro_style'] ?? $themePreset['intro_style'] ?? 'mágico') ?>"></div>
          <div class="field"><label>Costo exacto (créditos)</label><input type="number" name="quote_credits" min="0" step="1" placeholder="Sin estimar"></div>
          <div class="field"><label>Costo exacto (monto)</label><input type="number" name="quote_amount" min="0" step="0.01" placeholder="0,00"></div>
          <div class="field"><label>Moneda</label><input type="text" name="quote_currency" maxlength="12" value="USD"></div>
          <div class="field"><label>Vigencia de la cotización (opcional)</label><input type="datetime-local" name="quote_expires_at"></div>
          <div class="field profile-grid-span"><label>Frase/beat emocional</label><input type="text" name="video_phrase" maxlength="160" value="<?= h($profile['intro_phrase'] ?? '') ?>"></div>
          <div class="field profile-grid-span"><label>Prompt final a revisar</label><textarea name="prompt" rows="12" maxlength="12000"><?= h($defaultPrompt) ?></textarea><small>Debe mantenerse sin nombres de franquicias ni personajes protegidos. El texto público se superpone después en HTML.</small></div>
        </div>
        <button type="submit" class="btn btn-primary">Preparar solicitud y cotización</button>
      </form>

      <?php if (is_array($latestGeneration)): ?>
      <div class="profile-quote">
        <h3>Última solicitud preparada</h3>
        <div class="profile-quote-meta"><span><strong>Estado:</strong> <?= h($latestGeneration['status'] ?? 'draft') ?></span><span><strong>Modelo:</strong> <?= h(($latestGeneration['model_key'] ?? '') ?: 'pendiente') ?></span><span><strong>Costo:</strong> <?= ($latestGeneration['quote_credits'] ?? null) !== null ? h($latestGeneration['quote_credits']) . ' créditos' : h($latestGeneration['quote_amount'] ?? 'pendiente') . ' ' . h($latestGeneration['quote_currency'] ?? '') ?></span></div>
        <details><summary>Ver prompt almacenado</summary><pre><?= h($latestGeneration['prompt_text'] ?? '') ?></pre></details>
        <?php if (($latestGeneration['status'] ?? '') === 'quoted'): ?>
        <form method="post" action="event-profile.php?party=<?= rawurlencode($publicSlug) ?>" class="profile-approval-form">
          <?= admin_csrf_field() ?><input type="hidden" name="action" value="approve_video"><input type="hidden" name="generation_id" value="<?= (int) $latestGeneration['id'] ?>">
          <label class="checkbox-field"><input type="checkbox" name="confirm_approval" value="1" required> Apruebo explícitamente este modelo, costo y prompt final para una ejecución posterior.</label>
          <button type="submit" class="btn btn-cta">Registrar aprobación</button>
          <small>Registrar la aprobación no genera el video. Un operador autorizado deberá ejecutarlo después.</small>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </main>
</div>

<template id="person-template">
  <article class="profile-person" data-person data-sort-item>
    <input type="hidden" data-person-name="id" value="0">
    <div class="profile-person-head"><h3><span data-person-title>Nuevo protagonista</span> <span class="profile-person-index">#<span data-person-number></span></span></h3><div class="profile-order-actions"><button type="button" class="btn btn-icon" data-move="up" aria-label="Subir protagonista"><?= admin_icon('up') ?></button><button type="button" class="btn btn-icon" data-move="down" aria-label="Bajar protagonista"><?= admin_icon('down') ?></button><button type="button" class="btn btn-danger" data-remove-person><?= admin_icon('trash') ?> Quitar</button></div></div>
    <div class="profile-grid profile-grid--2"><div class="field"><label>Nombre público o apodo</label><input data-person-name="display_name" type="text" maxlength="120" required></div><div class="field"><label>Apodo adicional (opcional)</label><input data-person-name="nickname" type="text" maxlength="120"></div><div class="field profile-grid-span"><label>Presentación breve</label><textarea data-person-name="intro_text" maxlength="600" rows="3"></textarea></div></div>
    <div class="profile-consents"><label class="checkbox-field"><input type="checkbox" data-person-name="is_public" value="1" checked> Mostrar protagonista</label><label class="checkbox-field"><input type="checkbox" data-person-name="photo_public_consent" value="1"> Tengo autorización para publicar su foto</label><label class="checkbox-field profile-ai-consent"><input type="checkbox" data-person-name="photo_ai_consent" value="1"> Autorización separada para usar la foto como referencia IA</label></div>
    <div class="profile-fields-head"><h4>Qué contamos de esta persona</h4><p class="muted small">Cada bloque es una tarjeta en la ficha pública. Solo se publica lo marcado como visible.</p></div>
    <div class="profile-fields" data-fields data-sort-list="fields">
      <?php foreach ($sectionIndex as $optionKey => $optionLabel): ?>
      <section class="profile-field-group" data-section-group="<?= h($optionKey) ?>">
        <header class="profile-field-group-head"><h5><?= h($optionLabel) ?></h5><button type="button" class="btn btn-ghost btn-sm" data-add-field data-section="<?= h($optionKey) ?>"><?= admin_icon('plus') ?> Agregar dato</button></header>
        <div class="profile-field-rows" data-section-rows><p class="profile-group-empty" data-group-empty>Sin datos todavía en este bloque.</p></div>
      </section>
      <?php endforeach; ?>
    </div>
  </article>
</template>
<template id="field-template">
  <div class="profile-field-row" data-field data-sort-item><input type="hidden" data-field-name="field_key" value="custom"><label class="profile-field-cell profile-field-cell--label"><span>Etiqueta</span><input data-field-name="label" type="text" maxlength="100" placeholder="Ej. Talla de polera" required></label><label class="profile-field-cell profile-field-cell--value"><span>Valor</span><textarea data-field-name="value" maxlength="1200" rows="2" placeholder="Lo que verá el invitado" required></textarea></label><label class="profile-field-cell profile-field-cell--section"><span>Bloque</span><select data-field-name="section_key"><?php foreach ($sectionIndex as $optionKey => $optionLabel): ?><option value="<?= h($optionKey) ?>"><?= h($optionLabel) ?></option><?php endforeach; ?></select></label><input type="hidden" data-field-name="value_type" value="text"><label class="checkbox-field profile-field-visible"><input type="checkbox" data-field-name="is_public" value="1" checked> Visible</label><div class="profile-order-actions"><button type="button" class="btn btn-icon" data-move="up" aria-label="Subir campo"><?= admin_icon('up') ?></button><button type="button" class="btn btn-icon" data-move="down" aria-label="Bajar campo"><?= admin_icon('down') ?></button><button type="button" class="btn btn-icon btn-danger" data-remove-field aria-label="Quitar campo"><?= admin_icon('trash') ?></button></div></div>
</template>
<script>
(function () {
  var peopleList = document.getElementById('people-list');
  var personTemplate = document.getElementById('person-template');
  var fieldTemplate = document.getElementById('field-template');
  // Un bloque sin datos muestra su estado vacío en vez de quedar como un hueco.
  function refreshEmptyStates() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-section-rows]'), function (rows) {
      var hasField = rows.querySelector('[data-field]') !== null;
      var empty = rows.querySelector('[data-group-empty]');
      if (!hasField && !empty) {
        var placeholder = document.createElement('p');
        placeholder.className = 'profile-group-empty';
        placeholder.setAttribute('data-group-empty', '');
        placeholder.textContent = 'Sin datos todavía en este bloque.';
        rows.appendChild(placeholder);
      } else if (hasField && empty) {
        empty.remove();
      }
    });
  }
  function reindex() {
    if (!peopleList) return;
    Array.prototype.forEach.call(peopleList.querySelectorAll(':scope > [data-person]'), function (person, pi) {
      var number = person.querySelector('[data-person-number]'); if (number) number.textContent = String(pi + 1);
      var title = person.querySelector('[data-person-title]');
      var nameInput = person.querySelector('[data-person-name="display_name"]');
      if (title && nameInput) title.textContent = nameInput.value.trim() || ('Protagonista ' + (pi + 1));
      Array.prototype.forEach.call(person.querySelectorAll('[data-person-name]'), function (input) { input.name = 'people[' + pi + '][' + input.dataset.personName + ']'; });
      Array.prototype.forEach.call(person.querySelectorAll('[data-field]'), function (field, fi) {
        var key = field.querySelector('[data-field-name="field_key"]'); if (key && (!key.value || key.value === 'custom')) key.value = 'custom_' + (fi + 1);
        Array.prototype.forEach.call(field.querySelectorAll('[data-field-name]'), function (input) { input.name = 'people[' + pi + '][fields][' + fi + '][' + input.dataset.fieldName + ']'; });
      });
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-sort-list="sections"] [data-sort-item]'), function (item, i) {
      Array.prototype.forEach.call(item.querySelectorAll('[data-sort-name]'), function (input) { input.name = 'sections[' + i + '][' + input.dataset.sortName + ']'; });
    });
    refreshEmptyStates();
  }
  document.addEventListener('click', function (event) {
    var move = event.target.closest('[data-move]');
    if (move) { var item = move.closest('[data-sort-item]'); if (!item) return; var sibling = move.dataset.move === 'up' ? item.previousElementSibling : item.nextElementSibling; while (sibling && !sibling.hasAttribute('data-sort-item')) { sibling = move.dataset.move === 'up' ? sibling.previousElementSibling : sibling.nextElementSibling; } if (sibling) item.parentNode.insertBefore(move.dataset.move === 'up' ? item : sibling, move.dataset.move === 'up' ? sibling : item); reindex(); return; }
    var removePerson = event.target.closest('[data-remove-person]'); if (removePerson) { if (window.confirm('¿Quitar este protagonista y sus campos al guardar?')) removePerson.closest('[data-person]').remove(); reindex(); return; }
    var removeField = event.target.closest('[data-remove-field]'); if (removeField) { removeField.closest('[data-field]').remove(); reindex(); return; }
    var addField = event.target.closest('[data-add-field]');
    if (addField && fieldTemplate) {
      var group = addField.closest('[data-section-group]');
      var target = group ? group.querySelector('[data-section-rows]') : addField.closest('[data-person]').querySelector('[data-section-rows]');
      if (!target) return;
      var fragment = fieldTemplate.content.cloneNode(true);
      var select = fragment.querySelector('[data-field-name="section_key"]');
      if (select && group) select.value = group.dataset.sectionGroup;
      target.appendChild(fragment);
      reindex();
      var added = target.querySelectorAll('[data-field]');
      var labelInput = added.length ? added[added.length - 1].querySelector('[data-field-name="label"]') : null;
      if (labelInput) labelInput.focus();
      return;
    }
  });
  // Cambiar el bloque mueve la fila al grupo correcto en el acto.
  document.addEventListener('change', function (event) {
    var select = event.target.closest('[data-field-name="section_key"]');
    if (!select) return;
    var row = select.closest('[data-field]');
    var person = select.closest('[data-person]');
    if (!row || !person) return;
    var group = person.querySelector('[data-section-group="' + select.value.replace(/"/g, '') + '"]');
    var rows = group ? group.querySelector('[data-section-rows]') : null;
    if (rows && row.parentNode !== rows) rows.appendChild(row);
    reindex();
  });
  document.addEventListener('input', function (event) {
    var nameInput = event.target.closest('[data-person-name="display_name"]');
    if (!nameInput) return;
    var person = nameInput.closest('[data-person]');
    var title = person ? person.querySelector('[data-person-title]') : null;
    var number = person ? person.querySelector('[data-person-number]') : null;
    if (title) title.textContent = nameInput.value.trim() || ('Protagonista ' + (number ? number.textContent : ''));
  });
  var addPerson = document.getElementById('add-person');
  if (addPerson && personTemplate && peopleList) addPerson.addEventListener('click', function () { if (peopleList.querySelectorAll(':scope > [data-person]').length >= 10) { window.alert('Máximo 10 protagonistas.'); return; } peopleList.appendChild(personTemplate.content.cloneNode(true)); reindex(); var added = peopleList.querySelectorAll(':scope > [data-person]'); var last = added[added.length - 1].querySelector('[data-person-name="display_name"]'); if (last) last.focus(); });
  reindex();
}());
</script>
</body>
</html>
