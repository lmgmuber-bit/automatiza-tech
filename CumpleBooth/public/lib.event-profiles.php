<?php
/**
 * Perfiles de evento: personas destacadas, datos públicos y multimedia intro.
 *
 * Este módulo no llama proveedores generativos. Solo prepara, cotiza, aprueba
 * y registra el ciclo de vida para que un worker autorizado lo ejecute después.
 */

function cb_event_profile_require_db(): PDO
{
    if (cb_storage_mode() !== 'db') {
        throw new RuntimeException('El Perfil del protagonista requiere storage_mode=db.');
    }
    return cb_pdo();
}

function cb_event_profile_feature_enabled(): bool
{
    return filter_var(cb_config('event_profile_enabled'), FILTER_VALIDATE_BOOLEAN);
}

/** Presets parametrizables: agregar tipos o temas al JSON no exige cambiar PHP. */
function cb_event_profile_presets(): array
{
    static $presets = null;
    if (is_array($presets)) {
        return $presets;
    }
    $path = cb_data_dir() . '/event-profile-presets.json';
    $raw = is_file($path) ? file_get_contents($path) : false;
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    $presets = is_array($decoded) ? $decoded : [
        'default_event_type' => 'child_birthday',
        'event_types' => [],
        'themes' => [],
        'theme_fallback' => [],
    ];
    return $presets;
}

function cb_event_profile_preset(string $eventType, string $themeSlug): array
{
    $all = cb_event_profile_presets();
    $default = (string) ($all['default_event_type'] ?? 'child_birthday');
    $eventTypes = is_array($all['event_types'] ?? null) ? $all['event_types'] : [];
    $themes = is_array($all['themes'] ?? null) ? $all['themes'] : [];
    $event = is_array($eventTypes[$eventType] ?? null)
        ? $eventTypes[$eventType]
        : (is_array($eventTypes[$default] ?? null) ? $eventTypes[$default] : []);
    $theme = is_array($themes[$themeSlug] ?? null)
        ? $themes[$themeSlug]
        : (is_array($all['theme_fallback'] ?? null) ? $all['theme_fallback'] : []);
    return ['event_type' => $eventType, 'theme_slug' => $themeSlug, 'event' => $event, 'theme' => $theme];
}

function cb_event_profile_valid_event_type(string $eventType): bool
{
    if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/', $eventType)) {
        return false;
    }
    $types = cb_event_profile_presets()['event_types'] ?? [];
    return is_array($types) && isset($types[$eventType]) && is_array($types[$eventType]);
}

function cb_event_profile_clean_text($value, int $maxLength, bool $multiline = false): ?string
{
    if (!is_scalar($value) && $value !== null) {
        return null;
    }
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }
    $pattern = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u' : '/[\x00-\x1F\x7F]+/u';
    $text = preg_replace($pattern, ' ', $text) ?? '';
    $text = $multiline
        ? preg_replace('/[ \t]+/u', ' ', $text)
        : preg_replace('/\s+/u', ' ', $text);
    $text = trim((string) $text);
    return $text === '' ? null : mb_substr($text, 0, $maxLength);
}

/**
 * Detecta información que nunca debe publicarse. Devuelve hallazgos legibles;
 * el guardado los trata como error incluso si el campo fue marcado privado.
 */
function cb_event_profile_privacy_issues(string $label, string $value): array
{
    $haystack = mb_strtolower($label . ' ' . $value, 'UTF-8');
    $rules = [
        'dirección o ubicación exacta' => '/\b(direcci[oó]n|domicilio|vive en|calle|pasaje|avenida|departamento)\b/u',
        'teléfono' => '/\b(tel[eé]fono|celular|whatsapp|fono)\b|(?:\+?56\s*)?(?:9\s*)?(?:\d[ .-]*){8}\b/u',
        'colegio o jardín' => '/\b(colegio|escuela|liceo|jard[ií]n infantil|sala cuna|curso)\b/u',
        'rutina u horario' => '/\b(rutina|horario|sale de|llega a|todos los d[ií]as|lunes a viernes)\b/u',
        'documento de identidad' => '/\b(rut|pasaporte|c[eé]dula|documento de identidad)\b|\b\d{1,2}\.\d{3}\.\d{3}-[0-9k]\b/u',
        'correo electrónico' => '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu',
    ];
    $issues = [];
    foreach ($rules as $name => $pattern) {
        if (preg_match($pattern, $haystack)) {
            $issues[] = $name;
        }
    }
    return $issues;
}

function cb_event_profile_assert_private_safe(string $label, string $value): void
{
    $issues = cb_event_profile_privacy_issues($label, $value);
    if ($issues) {
        throw new InvalidArgumentException(
            'El perfil contiene información sensible no permitida: ' . implode(', ', $issues) . '.'
        );
    }
}

function cb_event_profile_find_row(int $partyId): ?array
{
    $stmt = cb_event_profile_require_db()->prepare(
        'SELECT ep.*, p.event_type, p.theme_slug, p.public_slug, p.active AS party_active
         FROM cc_event_profiles ep JOIN cc_parties p ON p.id=ep.party_id WHERE ep.party_id=?'
    );
    $stmt->execute([$partyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cb_event_profile_ensure(int $partyId): array
{
    $found = cb_event_profile_find_row($partyId);
    if ($found !== null) {
        return $found;
    }
    $pdo = cb_event_profile_require_db();
    $partyStmt = $pdo->prepare('SELECT id,event_type,theme_slug FROM cc_parties WHERE id=?');
    $partyStmt->execute([$partyId]);
    $party = $partyStmt->fetch();
    if (!$party) {
        throw new InvalidArgumentException('El evento indicado no existe.');
    }
    $preset = cb_event_profile_preset((string) $party['event_type'], (string) $party['theme_slug']);
    $eventPreset = $preset['event'];
    $themePreset = $preset['theme'];
    $title = (string) (($eventPreset['title_suggestions'][0] ?? '') ?: 'Conoce al protagonista');
    $cta = (string) (($eventPreset['cta_suggestions'][0] ?? '') ?: $title);
    $style = (string) (($themePreset['intro_style'] ?? '') ?: 'magical');
    $layout = (string) (($eventPreset['layout'] ?? '') ?: 'theme');
    $now = gmdate('Y-m-d H:i:s');
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO cc_event_profiles
             (party_id,is_enabled,public_title,cta_label,intro_style,design_variant,locale,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$partyId, 0, $title, $cta, $style, $layout, 'es-CL', $now, $now]);
    } catch (PDOException $e) {
        $again = cb_event_profile_find_row($partyId);
        if ($again === null) {
            throw $e;
        }
        return $again;
    }
    $profile = cb_event_profile_find_row($partyId);
    if ($profile === null) {
        throw new RuntimeException('No se pudo crear el perfil del evento.');
    }
    cb_event_profile_replace_sections((int) $profile['id'], $eventPreset['sections'] ?? []);
    return cb_event_profile_find_row($partyId) ?? $profile;
}

function cb_event_profile_replace_sections(int $profileId, array $sections): void
{
    $pdo = cb_event_profile_require_db();
    $now = gmdate('Y-m-d H:i:s');
    $normalized = [];
    foreach (array_slice($sections, 0, 20) as $index => $section) {
        if (is_string($section)) {
            $key = $section;
            $label = ucfirst(str_replace('_', ' ', $key));
            $order = ($index + 1) * 10;
            $visible = true;
        } elseif (is_array($section)) {
            $key = (string) ($section['key'] ?? $section['section_key'] ?? '');
            $label = (string) ($section['label'] ?? $section['public_label'] ?? $key);
            $order = (int) ($section['order'] ?? $section['sort_order'] ?? (($index + 1) * 10));
            $visible = array_key_exists('enabled', $section)
                ? !empty($section['enabled'])
                : (array_key_exists('is_public', $section) ? !empty($section['is_public']) : true);
        } else {
            continue;
        }
        $key = strtolower(trim($key));
        if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $key) || isset($normalized[$key])) {
            continue;
        }
        $cleanLabel = cb_event_profile_clean_text($label, 100) ?? $key;
        cb_event_profile_assert_private_safe($cleanLabel, '');
        $normalized[$key] = [$cleanLabel, $visible ? 1 : 0, $order];
    }
    if (!$normalized) {
        $normalized = ['introduction' => ['Presentación', 1, 10], 'custom' => ['Más sobre el protagonista', 1, 20]];
    }
    $existing = $pdo->prepare('SELECT id,section_key FROM cc_event_profile_sections WHERE profile_id=?');
    $existing->execute([$profileId]);
    $byKey = [];
    foreach ($existing->fetchAll() as $row) {
        $byKey[(string) $row['section_key']] = (int) $row['id'];
    }
    $update = $pdo->prepare(
        'UPDATE cc_event_profile_sections SET public_label=?,is_public=?,sort_order=?,updated_at=? WHERE id=?'
    );
    $insert = $pdo->prepare(
        'INSERT INTO cc_event_profile_sections
         (profile_id,section_key,public_label,is_public,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?)'
    );
    foreach ($normalized as $key => [$label, $visible, $order]) {
        if (isset($byKey[$key])) {
            $update->execute([$label, $visible, $order, $now, $byKey[$key]]);
            unset($byKey[$key]);
        } else {
            $insert->execute([$profileId, $key, $label, $visible, $order, $now, $now]);
        }
    }
    if ($byKey) {
        $ids = array_values($byKey);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE cc_event_profile_fields SET section_id=NULL WHERE section_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM cc_event_profile_sections WHERE id IN ($placeholders)")->execute($ids);
    }
}

/** Guarda configuración general; las personas se administran por separado. */
function cb_event_profile_save(int $partyId, array $data, ?string $actor = null): array
{
    $pdo = cb_event_profile_require_db();
    $current = cb_event_profile_ensure($partyId);
    $eventType = trim((string) ($data['event_type'] ?? $current['event_type'] ?? 'child_birthday'));
    if (!cb_event_profile_valid_event_type($eventType)) {
        throw new InvalidArgumentException('Tipo de evento no configurado en event-profile-presets.json.');
    }
    $enabled = array_key_exists('enabled', $data) ? !empty($data['enabled']) : !empty($current['is_enabled']);
    $title = cb_event_profile_clean_text($data['public_title'] ?? $current['public_title'] ?? '', 160);
    $cta = cb_event_profile_clean_text($data['cta_label'] ?? $current['cta_label'] ?? '', 120);
    $style = strtolower(trim((string) ($data['intro_style'] ?? $current['intro_style'] ?? 'magical')));
    $phrase = cb_event_profile_clean_text($data['intro_phrase'] ?? $current['intro_phrase'] ?? '', 160);
    if (!preg_match('/^[a-záéíóúüñ0-9_-]{2,40}$/iu', $style)) {
        throw new InvalidArgumentException('Estilo emocional inválido.');
    }
    foreach ([['Título público', $title], ['Texto del acceso', $cta], ['Frase de introducción', $phrase]] as [$label, $value]) {
        if ($value !== null) {
            cb_event_profile_assert_private_safe($label, $value);
        }
    }
    $privacyAck = !empty($data['privacy_ack']);
    if ($enabled && empty($current['privacy_confirmed_at']) && !$privacyAck) {
        throw new InvalidArgumentException('Debes confirmar la advertencia de privacidad antes de activar el perfil.');
    }
    $now = gmdate('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE cc_parties SET event_type=?,updated_at=? WHERE id=?')->execute([$eventType, $now, $partyId]);
        $privacyAt = $privacyAck ? $now : ($current['privacy_confirmed_at'] ?? null);
        $privacyBy = $privacyAck ? $actor : ($current['privacy_confirmed_by'] ?? null);
        $stmt = $pdo->prepare(
            'UPDATE cc_event_profiles SET is_enabled=?,public_title=?,cta_label=?,intro_style=?,intro_phrase=?,
             privacy_version=?,privacy_confirmed_at=?,privacy_confirmed_by=?,updated_at=? WHERE id=?'
        );
        $stmt->execute([
            $enabled ? 1 : 0, $title, $cta, $style, $phrase,
            $privacyAt ? 'event-profile-v1' : null, $privacyAt, $privacyBy, $now, (int) $current['id'],
        ]);
        $sections = $data['section_order'] ?? null;
        if (is_array($sections)) {
            $preset = cb_event_profile_preset($eventType, (string) $current['theme_slug']);
            $presetByKey = [];
            foreach (($preset['event']['sections'] ?? []) as $presetSection) {
                if (is_array($presetSection) && isset($presetSection['key'])) {
                    $presetByKey[(string) $presetSection['key']] = $presetSection;
                }
            }
            $orderedSections = [];
            foreach ($sections as $index => $section) {
                if (is_string($section)) {
                    $key = strtolower(trim($section));
                    $item = $presetByKey[$key] ?? ['key' => $key, 'label' => ucfirst(str_replace('_', ' ', $key)), 'enabled' => true];
                } elseif (is_array($section)) {
                    $key = strtolower(trim((string) ($section['key'] ?? $section['section_key'] ?? '')));
                    $item = array_replace($presetByKey[$key] ?? [], $section);
                    $item['key'] = $key;
                } else {
                    continue;
                }
                $item['order'] = ($index + 1) * 10;
                $orderedSections[] = $item;
            }
            cb_event_profile_replace_sections((int) $current['id'], $orderedSections);
        } elseif (!$pdo->query('SELECT 1 FROM cc_event_profile_sections WHERE profile_id=' . (int) $current['id'] . ' LIMIT 1')->fetch()) {
            $preset = cb_event_profile_preset($eventType, (string) $current['theme_slug']);
            cb_event_profile_replace_sections((int) $current['id'], $preset['event']['sections'] ?? []);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return cb_event_profile_get($partyId, true) ?? [];
}

function cb_event_profile_replace_people(int $partyId, array $people, ?string $actor = null): array
{
    if (count($people) > 10) {
        throw new InvalidArgumentException('El perfil admite hasta 10 protagonistas.');
    }
    $pdo = cb_event_profile_require_db();
    $profile = cb_event_profile_ensure($partyId);
    $profileId = (int) $profile['id'];
    $sectionsStmt = $pdo->prepare('SELECT id,section_key FROM cc_event_profile_sections WHERE profile_id=?');
    $sectionsStmt->execute([$profileId]);
    $sectionIds = [];
    foreach ($sectionsStmt->fetchAll() as $row) {
        $sectionIds[(string) $row['section_key']] = (int) $row['id'];
    }
    $now = gmdate('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        $existingStmt = $pdo->prepare('SELECT id,public_id FROM cc_featured_people WHERE profile_id=?');
        $existingStmt->execute([$profileId]);
        $existing = [];
        foreach ($existingStmt->fetchAll() as $row) {
            $existing[(int) $row['id']] = (string) $row['public_id'];
        }
        $kept = [];
        foreach (array_values($people) as $index => $person) {
            if (!is_array($person)) {
                continue;
            }
            $name = cb_event_profile_clean_text($person['display_name'] ?? '', 120);
            if ($name === null) {
                throw new InvalidArgumentException('Cada protagonista debe tener nombre o apodo público.');
            }
            $nickname = cb_event_profile_clean_text($person['nickname'] ?? '', 120);
            $intro = cb_event_profile_clean_text($person['intro_text'] ?? '', 600, true);
            cb_event_profile_assert_private_safe('Nombre', $name);
            if ($nickname !== null) { cb_event_profile_assert_private_safe('Apodo', $nickname); }
            if ($intro !== null) { cb_event_profile_assert_private_safe('Presentación', $intro); }
            $isPublic = array_key_exists('is_public', $person) ? !empty($person['is_public']) : true;
            $publicConsent = !empty($person['photo_public_consent']);
            $aiConsent = !empty($person['photo_ai_consent']);
            $personId = (int) ($person['id'] ?? 0);
            if ($personId > 0 && isset($existing[$personId])) {
                $stmt = $pdo->prepare(
                    'UPDATE cc_featured_people SET display_name=?,nickname=?,intro_text=?,is_public=?,sort_order=?,
                     photo_public_consent=?,photo_ai_consent=?,consent_recorded_at=?,consent_recorded_by=?,updated_at=?
                     WHERE id=? AND profile_id=?'
                );
                $stmt->execute([
                    $name, $nickname, $intro, $isPublic ? 1 : 0, (int) ($person['sort_order'] ?? (($index + 1) * 10)),
                    $publicConsent ? 1 : 0, $aiConsent ? 1 : 0,
                    ($publicConsent || $aiConsent) ? $now : null, ($publicConsent || $aiConsent) ? $actor : null,
                    $now, $personId, $profileId,
                ]);
            } else {
                $publicId = strtolower((string) ($person['public_id'] ?? ''));
                if (!preg_match('/^[a-f0-9]{32}$/', $publicId)) {
                    $publicId = cb_opaque_token(16);
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO cc_featured_people
                     (profile_id,public_id,display_name,nickname,intro_text,is_public,sort_order,photo_public_consent,
                      photo_ai_consent,consent_recorded_at,consent_recorded_by,created_at,updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $profileId, $publicId, $name, $nickname, $intro, $isPublic ? 1 : 0,
                    (int) ($person['sort_order'] ?? (($index + 1) * 10)), $publicConsent ? 1 : 0, $aiConsent ? 1 : 0,
                    ($publicConsent || $aiConsent) ? $now : null, ($publicConsent || $aiConsent) ? $actor : null, $now, $now,
                ]);
                $personId = (int) $pdo->lastInsertId();
            }
            $kept[] = $personId;
            if (!$isPublic || !$publicConsent) {
                $pdo->prepare("UPDATE cc_event_profile_media SET is_public=0,updated_at=? WHERE featured_person_id=? AND media_kind='photo'")
                    ->execute([$now, $personId]);
            }
            if (!$aiConsent) {
                $pdo->prepare("UPDATE cc_event_profile_media SET authorized_for_ai=0,updated_at=? WHERE featured_person_id=?")
                    ->execute([$now, $personId]);
            }
            $pdo->prepare('DELETE FROM cc_event_profile_fields WHERE featured_person_id=?')->execute([$personId]);
            $fields = is_array($person['fields'] ?? null) ? array_slice($person['fields'], 0, 60) : [];
            $insertField = $pdo->prepare(
                'INSERT INTO cc_event_profile_fields
                 (profile_id,featured_person_id,section_id,section_key,field_key,public_label,value_text,value_type,is_public,sort_order,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            foreach (array_values($fields) as $fieldIndex => $field) {
                if (!is_array($field)) { continue; }
                $sectionKey = strtolower(trim((string) ($field['section_key'] ?? 'custom')));
                if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $sectionKey)) { $sectionKey = 'custom'; }
                $fieldKey = strtolower(trim((string) ($field['field_key'] ?? 'custom_' . ($fieldIndex + 1))));
                $fieldKey = preg_replace('/[^a-z0-9_]+/', '_', $fieldKey) ?? '';
                $fieldKey = trim($fieldKey, '_');
                if ($fieldKey === '') { $fieldKey = 'custom_' . ($fieldIndex + 1); }
                $label = cb_event_profile_clean_text($field['label'] ?? '', 100);
                $value = cb_event_profile_clean_text($field['value'] ?? '', 1200, true);
                if ($label === null || $value === null) { continue; }
                cb_event_profile_assert_private_safe($label, $value);
                $type = strtolower((string) ($field['value_type'] ?? 'text'));
                if (!in_array($type, ['text', 'multiline', 'list', 'size'], true)) { $type = 'text'; }
                $insertField->execute([
                    $profileId, $personId, $sectionIds[$sectionKey] ?? null, $sectionKey, mb_substr($fieldKey, 0, 80),
                    $label, $value, $type, ($isPublic && !empty($field['is_public'])) ? 1 : 0,
                    (int) ($field['sort_order'] ?? (($fieldIndex + 1) * 10)), $now, $now,
                ]);
            }
        }
        $removeIds = array_values(array_diff(array_keys($existing), $kept));
        if ($removeIds) {
            $marks = implode(',', array_fill(0, count($removeIds), '?'));
            $pdo->prepare("UPDATE cc_event_profile_media SET featured_person_id=NULL,is_public=0,authorized_for_ai=0,updated_at=? WHERE featured_person_id IN ($marks)")
                ->execute(array_merge([$now], $removeIds));
            $pdo->prepare("DELETE FROM cc_featured_people WHERE id IN ($marks)")->execute($removeIds);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return cb_event_profile_get($partyId, true) ?? [];
}

function cb_event_profile_get(int $partyId, bool $includePrivate = true): ?array
{
    $profile = cb_event_profile_find_row($partyId);
    if ($profile === null) { return null; }
    $pdo = cb_event_profile_require_db();
    $visibility = $includePrivate ? '' : ' AND is_public=1';
    $stmt = $pdo->prepare('SELECT * FROM cc_event_profile_sections WHERE profile_id=?' . $visibility . ' ORDER BY sort_order,id');
    $stmt->execute([(int) $profile['id']]);
    $sections = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT * FROM cc_featured_people WHERE profile_id=?' . $visibility . ' ORDER BY sort_order,id');
    $stmt->execute([(int) $profile['id']]);
    $people = $stmt->fetchAll();
    $personIds = array_map(static fn(array $row): int => (int) $row['id'], $people);
    $fieldsByPerson = [];
    if ($personIds) {
        $marks = implode(',', array_fill(0, count($personIds), '?'));
        $fieldSql = "SELECT * FROM cc_event_profile_fields WHERE featured_person_id IN ($marks)";
        if (!$includePrivate) { $fieldSql .= ' AND is_public=1'; }
        $fieldSql .= ' ORDER BY sort_order,id';
        $fieldStmt = $pdo->prepare($fieldSql);
        $fieldStmt->execute($personIds);
        foreach ($fieldStmt->fetchAll() as $field) {
            $fieldsByPerson[(int) $field['featured_person_id']][] = $field;
        }
    }
    $mediaSql = 'SELECT * FROM cc_event_profile_media WHERE profile_id=? AND deleted_at IS NULL';
    if (!$includePrivate) { $mediaSql .= " AND is_public=1 AND status='ready'"; }
    $mediaSql .= ' ORDER BY id DESC';
    $mediaStmt = $pdo->prepare($mediaSql);
    $mediaStmt->execute([(int) $profile['id']]);
    $media = $mediaStmt->fetchAll();
    $generations = [];
    if ($includePrivate) {
        $generationStmt = $pdo->prepare(
            'SELECT * FROM cc_event_profile_generations WHERE profile_id=? ORDER BY id DESC LIMIT 20'
        );
        $generationStmt->execute([(int) $profile['id']]);
        $generations = $generationStmt->fetchAll();
    }
    $mediaByPerson = [];
    $profileMedia = [];
    foreach ($media as $item) {
        $personId = (int) ($item['featured_person_id'] ?? 0);
        if ($personId > 0) { $mediaByPerson[$personId][] = $item; }
        else { $profileMedia[] = $item; }
    }
    foreach ($people as &$person) {
        $person['fields'] = $fieldsByPerson[(int) $person['id']] ?? [];
        $person['media'] = $mediaByPerson[(int) $person['id']] ?? [];
    }
    unset($person);
    $profile['sections'] = $sections;
    $profile['featured_people'] = $people;
    $profile['media'] = $profileMedia;
    $profile['preset'] = cb_event_profile_preset((string) $profile['event_type'], (string) $profile['theme_slug']);
    if ($includePrivate) {
        $profile['generations'] = $generations;
    }
    return $profile;
}

/** Agregado público sin consentimiento, storage keys, auditoría ni generaciones. */
function cb_event_profile_public_by_party(int $partyId, ?string $invitationToken = null): ?array
{
    if (!cb_event_profile_feature_enabled()) { return null; }
    $raw = cb_event_profile_get($partyId, false);
    if ($raw === null || empty($raw['is_enabled']) || empty($raw['party_active'])) { return null; }
    $sections = [];
    foreach ($raw['sections'] as $section) {
        $sections[] = [
            'key' => (string) $section['section_key'],
            'label' => (string) $section['public_label'],
            'sort_order' => (int) $section['sort_order'],
        ];
    }
    $people = [];
    foreach ($raw['featured_people'] as $person) {
        $photo = null;
        foreach ($person['media'] as $media) {
            if ((string) $media['media_kind'] === 'photo') {
                $photo = cb_event_profile_public_media_shape($media, $invitationToken);
                break;
            }
        }
        $fields = [];
        foreach ($person['fields'] as $field) {
            $fields[] = [
                'section_key' => (string) $field['section_key'],
                'field_key' => (string) $field['field_key'],
                'label' => (string) $field['public_label'],
                'value' => (string) $field['value_text'],
                'value_type' => (string) $field['value_type'],
                'sort_order' => (int) $field['sort_order'],
            ];
        }
        $people[] = [
            'id' => (int) $person['id'], 'public_id' => (string) $person['public_id'],
            'display_name' => (string) $person['display_name'], 'nickname' => $person['nickname'],
            'intro_text' => $person['intro_text'], 'sort_order' => (int) $person['sort_order'],
            'photo' => $photo, 'fields' => $fields,
        ];
    }
    $video = null;
    $poster = null;
    foreach ($raw['media'] as $media) {
        if ((string) $media['media_kind'] === 'intro_video' && $video === null) {
            $video = cb_event_profile_public_media_shape($media, $invitationToken);
        } elseif ((string) $media['media_kind'] === 'poster' && $poster === null) {
            $poster = cb_event_profile_public_media_shape($media, $invitationToken);
        }
    }
    $hasContent = false;
    foreach ($people as $person) {
        if ($person['display_name'] !== '' || $person['intro_text'] || $person['photo'] || $person['fields']) {
            $hasContent = true;
            break;
        }
    }
    if (!$hasContent) { return null; }
    return [
        'id' => (int) $raw['id'], 'event_type' => (string) $raw['event_type'],
        'theme_slug' => (string) $raw['theme_slug'], 'public_title' => (string) ($raw['public_title'] ?? ''),
        'layout' => (string) ($raw['preset']['theme']['layout'] ?? $raw['preset']['event']['layout']
            ?? $raw['design_variant'] ?? 'event-profile'),
        'cta_label' => (string) ($raw['cta_label'] ?? ''), 'intro_style' => (string) $raw['intro_style'],
        'intro_phrase' => $raw['intro_phrase'], 'preset' => $raw['preset'], 'sections' => $sections,
        'featured_people' => $people, 'intro_media' => $video ? ['video' => $video, 'poster' => $poster] : null,
        'has_public_content' => true,
    ];
}

function cb_event_profile_public_for_invitation(array $invitation, ?string $invitationToken = null): ?array
{
    if ((string) ($invitation['status'] ?? '') !== 'published') { return null; }
    if (!empty($invitation['expires_at']) && strtotime((string) $invitation['expires_at']) < time()) { return null; }
    $partyId = (int) ($invitation['party_id'] ?? 0);
    return $partyId > 0 ? cb_event_profile_public_by_party($partyId, $invitationToken) : null;
}

function cb_event_profile_public_media_shape(array $media, ?string $invitationToken): array
{
    return [
        'token' => (string) $media['access_token'], 'kind' => (string) $media['media_kind'],
        'mime' => (string) $media['mime'], 'byte_size' => (int) $media['byte_size'],
        'width' => (int) $media['width'], 'height' => (int) $media['height'],
        'duration_seconds' => $media['duration_seconds'] !== null ? (float) $media['duration_seconds'] : null,
        'has_audio' => !empty($media['has_audio']), 'alt_text' => $media['alt_text'],
        'url' => $invitationToken ? cb_event_profile_media_url($invitationToken, $media) : null,
    ];
}

function cb_event_profile_storage_root(): string
{
    $configured = trim((string) cb_config('event_profile_dir'));
    if ($configured === '') {
        $configured = dirname((string) cb_config('photo_dir')) . '/event-profiles';
    }
    $root = cb_private_dir($configured, 'event_profile_dir');
    if (!is_dir($root) && !mkdir($root, 0770, true) && !is_dir($root)) {
        throw new RuntimeException('No se pudo crear el storage privado de perfiles.');
    }
    return $root;
}

function cb_event_profile_storage_key(string $partySlug, string $kind, string $extension): string
{
    if (!cb_valid_public_slug($partySlug) || !in_array($kind, ['photo', 'reference', 'poster', 'intro-video'], true)) {
        throw new InvalidArgumentException('Destino de media inválido.');
    }
    $ext = strtolower(ltrim($extension, '.'));
    if (!in_array($ext, ['jpg', 'png', 'webp', 'mp4'], true)) {
        throw new InvalidArgumentException('Formato de media no permitido.');
    }
    return $partySlug . '/' . $kind . '/' . gmdate('Y/m') . '/' . cb_opaque_token(16) . '.' . $ext;
}

function cb_event_profile_media_path(string $storageKey): ?string
{
    if (!preg_match('#^[a-z0-9-]{1,80}/(?:photo|reference|poster|intro-video)/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp|mp4)$#', $storageKey)) {
        return null;
    }
    return cb_event_profile_storage_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
}

function cb_event_profile_media_url(string $invitationToken, array $media): string
{
    if (!cb_invitation_public_token_is_valid($invitationToken) || !preg_match('/^[a-f0-9]{32}$/', (string) ($media['access_token'] ?? ''))) {
        throw new InvalidArgumentException('Token de media inválido.');
    }
    return cb_public_base_url() . '/event-profile-media.php?t=' . rawurlencode($invitationToken)
        . '&mt=' . rawurlencode((string) $media['access_token']);
}

/** Media pública siempre ligada a una invitación publicada, vigente y del mismo evento. */
function cb_event_profile_find_public_media_for_invitation(string $invitationToken, string $mediaToken): ?array
{
    if (!cb_event_profile_feature_enabled()
        || !cb_invitation_public_token_is_valid($invitationToken)
        || !preg_match('/^[a-f0-9]{32}$/', $mediaToken)) {
        return null;
    }
    $invitation = cb_load_invitation_by_public_token($invitationToken);
    if (!is_array($invitation) || (string) ($invitation['status'] ?? '') !== 'published') {
        return null;
    }
    if (!empty($invitation['expires_at']) && strtotime((string) $invitation['expires_at']) < time()) {
        return null;
    }
    $partyId = (int) ($invitation['party_id'] ?? 0);
    if ($partyId < 1) {
        return null;
    }
    $stmt = cb_event_profile_require_db()->prepare(
        "SELECT m.*, p.active AS party_active
         FROM cc_event_profiles ep
         JOIN cc_parties p ON p.id=ep.party_id
         JOIN cc_event_profile_media m ON m.profile_id=ep.id
         WHERE ep.party_id=? AND ep.is_enabled=1 AND m.access_token=? AND m.is_public=1
           AND m.status='ready' AND m.deleted_at IS NULL"
    );
    $stmt->execute([$partyId, $mediaToken]);
    $row = $stmt->fetch();
    if (!$row || empty($row['party_active'])) { return null; }
    $path = cb_event_profile_media_path((string) $row['storage_key']);
    if ($path === null || !is_file($path)) { return null; }
    $row['absolute_path'] = $path;
    return $row;
}

function cb_event_profile_upload_media(
    int $partyId,
    ?int $personId,
    string $kind,
    array $file,
    array $consents = [],
    ?string $actor = null
): array {
    if (!in_array($kind, ['photo', 'reference', 'poster', 'intro_video'], true)) {
        throw new InvalidArgumentException('Tipo de media no permitido.');
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('No se recibió un archivo válido.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new InvalidArgumentException('El archivo no es una carga HTTP válida.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 50 * 1024 * 1024) {
        throw new InvalidArgumentException('El archivo supera el tamaño permitido.');
    }
    $image = @getimagesize($tmp);
    $ext = null; $mime = null; $width = 0; $height = 0; $duration = null; $hasAudio = false;
    if (is_array($image)) {
        $types = [IMAGETYPE_JPEG => ['jpg', 'image/jpeg'], IMAGETYPE_PNG => ['png', 'image/png'], IMAGETYPE_WEBP => ['webp', 'image/webp']];
        if (!isset($types[$image[2] ?? 0]) || $kind === 'intro_video' || $size > 12 * 1024 * 1024) {
            throw new InvalidArgumentException('La imagen no cumple formato o tamaño permitido.');
        }
        [$ext, $mime] = $types[$image[2]]; $width = (int) $image[0]; $height = (int) $image[1];
    } else {
        $head = file_get_contents($tmp, false, null, 0, 16);
        if ($kind !== 'intro_video' || !is_string($head) || strlen($head) < 12 || substr($head, 4, 4) !== 'ftyp') {
            throw new InvalidArgumentException('Solo se admite MP4 para el video intro.');
        }
        if ($size > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('El video intro debe pesar como máximo 8 MiB.');
        }
        $ext = 'mp4'; $mime = 'video/mp4';
        $probe = cb_inspect_video($tmp);
        if (!is_array($probe)) {
            throw new InvalidArgumentException('No se pudo validar técnicamente el video intro.');
        }
        $width = (int) ($probe['width'] ?? 0);
        $height = (int) ($probe['height'] ?? 0);
        $duration = isset($probe['duration']) ? (float) $probe['duration'] : null;
        $hasAudio = !empty($probe['has_audio']);
        if ($duration === null || $duration < 4 || $duration > 6.1) {
            throw new InvalidArgumentException('El video intro debe durar entre 4 y 6 segundos.');
        }
        if ($width < 1 || $height < 1 || abs(($width / $height) - (9 / 16)) > 0.03) {
            throw new InvalidArgumentException('El video intro debe tener orientación 9:16.');
        }
        if (!$hasAudio) {
            throw new InvalidArgumentException('El video intro debe incluir una pista de audio validable.');
        }
    }
    $profile = cb_event_profile_ensure($partyId);
    if ($personId !== null) {
        $check = cb_event_profile_require_db()->prepare('SELECT * FROM cc_featured_people WHERE id=? AND profile_id=?');
        $check->execute([$personId, (int) $profile['id']]);
        $person = $check->fetch();
        if (!$person) { throw new InvalidArgumentException('El protagonista no pertenece al evento.'); }
    } else { $person = null; }
    $publicConsent = !empty($consents['photo_public_consent']) || ($person && !empty($person['photo_public_consent']));
    $aiConsent = !empty($consents['photo_ai_consent']) || ($person && !empty($person['photo_ai_consent']));
    $isPublic = !empty($consents['is_public']) && ($kind !== 'photo' || $publicConsent) && $kind !== 'reference';
    $authorizedAi = $aiConsent && in_array($kind, ['photo', 'reference'], true);
    $storageKind = str_replace('_', '-', $kind);
    $key = cb_event_profile_storage_key((string) $profile['public_slug'], $storageKind, (string) $ext);
    $path = cb_event_profile_media_path($key);
    if ($path === null) { throw new RuntimeException('No se pudo resolver el storage privado.'); }
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de media.');
    }
    $staging = $path . '.tmp.' . bin2hex(random_bytes(4));
    if (!move_uploaded_file($tmp, $staging) || !rename($staging, $path)) {
        @unlink($staging);
        throw new RuntimeException('No se pudo guardar el archivo en storage privado.');
    }
    @chmod($path, 0660);
    try {
        return cb_event_profile_register_media($partyId, $personId, $kind, $key, [
            'mime' => $mime, 'byte_size' => $size, 'width' => $width, 'height' => $height,
            'duration_seconds' => $duration, 'has_audio' => $hasAudio, 'sha256' => hash_file('sha256', $path),
            'alt_text' => cb_event_profile_clean_text($consents['alt_text'] ?? '', 180),
            'status' => 'ready', 'is_public' => $isPublic, 'authorized_for_ai' => $authorizedAi,
        ], $actor);
    } catch (Throwable $e) {
        @unlink($path);
        throw $e;
    }
}

/** Registra media ya validada/normalizada por un proceso confiable. */
function cb_event_profile_register_media(
    int $partyId,
    ?int $personId,
    string $kind,
    string $storageKey,
    array $metadata,
    ?string $actor = null
): array {
    $profile = cb_event_profile_ensure($partyId);
    $path = cb_event_profile_media_path($storageKey);
    if ($path === null || !is_file($path)) { throw new InvalidArgumentException('La media no existe en el storage privado.'); }
    $sha = strtolower((string) ($metadata['sha256'] ?? hash_file('sha256', $path)));
    if (!preg_match('/^[a-f0-9]{64}$/', $sha)) { throw new InvalidArgumentException('SHA-256 inválido.'); }
    if ($personId !== null) {
        $check = cb_event_profile_require_db()->prepare('SELECT 1 FROM cc_featured_people WHERE id=? AND profile_id=?');
        $check->execute([$personId, (int) $profile['id']]);
        if (!$check->fetch()) { throw new InvalidArgumentException('El protagonista no pertenece al evento.'); }
    }
    $now = gmdate('Y-m-d H:i:s');
    $authorized = !empty($metadata['authorized_for_ai']);
    $stmt = cb_event_profile_require_db()->prepare(
        'INSERT INTO cc_event_profile_media
         (profile_id,featured_person_id,media_kind,access_token,storage_key,mime,byte_size,width,height,duration_seconds,
          has_audio,sha256,alt_text,status,is_public,authorized_for_ai,authorization_recorded_at,authorization_recorded_by,
          metadata_json,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        (int) $profile['id'], $personId, $kind, cb_opaque_token(16), $storageKey,
        (string) ($metadata['mime'] ?? 'application/octet-stream'), (int) ($metadata['byte_size'] ?? filesize($path)),
        (int) ($metadata['width'] ?? 0), (int) ($metadata['height'] ?? 0), $metadata['duration_seconds'] ?? null,
        !empty($metadata['has_audio']) ? 1 : 0, $sha, $metadata['alt_text'] ?? null,
        in_array(($metadata['status'] ?? ''), ['draft', 'ready', 'failed'], true) ? $metadata['status'] : 'draft',
        !empty($metadata['is_public']) ? 1 : 0, $authorized ? 1 : 0, $authorized ? $now : null,
        $authorized ? $actor : null, isset($metadata['metadata']) ? json_encode($metadata['metadata'], JSON_UNESCAPED_UNICODE) : null,
        $now, $now,
    ]);
    $id = (int) cb_event_profile_require_db()->lastInsertId();
    $fetch = cb_event_profile_require_db()->prepare('SELECT * FROM cc_event_profile_media WHERE id=?');
    $fetch->execute([$id]);
    return $fetch->fetch() ?: [];
}

function cb_event_profile_prepare_generation(int $partyId, array $request, ?string $actor = null): array
{
    $profile = cb_event_profile_ensure($partyId);
    $prompt = cb_event_profile_clean_text($request['prompt'] ?? '', 12000, true);
    if ($prompt === null || mb_strlen($prompt) < 40) {
        throw new InvalidArgumentException('El prompt final debe estar completo antes de cotizar.');
    }
    cb_event_profile_assert_private_safe('Prompt de video', $prompt);
    $duration = (float) ($request['duration_seconds'] ?? 5);
    $aspect = (string) ($request['aspect_ratio'] ?? '9:16');
    if ($duration < 4 || $duration > 6 || $aspect !== '9:16') {
        throw new InvalidArgumentException('El intro debe ser vertical 9:16 y durar entre 4 y 6 segundos.');
    }
    $provider = strtolower((string) ($request['provider'] ?? 'higgsfield'));
    if (!preg_match('/^[a-z0-9_-]{2,40}$/', $provider)) { throw new InvalidArgumentException('Proveedor inválido.'); }
    $model = cb_event_profile_clean_text($request['model_key'] ?? '', 100);
    $quoteAmount = isset($request['quote_amount']) ? (float) $request['quote_amount'] : null;
    $quoteCredits = isset($request['quote_credits']) ? (int) $request['quote_credits'] : null;
    $currency = cb_event_profile_clean_text($request['quote_currency'] ?? '', 12);
    $quoteExpiresAt = null;
    $quoteExpiresRaw = trim((string) ($request['quote_expires_at'] ?? ''));
    if ($quoteExpiresRaw !== '') {
        $quoteExpiresTimestamp = strtotime($quoteExpiresRaw);
        if ($quoteExpiresTimestamp === false) {
            throw new InvalidArgumentException('La vigencia de la cotización no tiene un formato válido.');
        }
        $quoteExpiresAt = gmdate('Y-m-d H:i:s', $quoteExpiresTimestamp);
    }
    $quoted = $model !== null && ($quoteAmount !== null || $quoteCredits !== null);
    $referenceMediaIds = array_values(array_unique(array_filter(array_map(
        'intval',
        is_array($request['reference_media_ids'] ?? null) ? $request['reference_media_ids'] : []
    ), static fn(int $id): bool => $id > 0)));
    $referencePersonIds = array_values(array_unique(array_filter(array_map(
        'intval',
        is_array($request['reference_person_ids'] ?? null) ? $request['reference_person_ids'] : []
    ), static fn(int $id): bool => $id > 0)));
    if (count($referenceMediaIds) + count($referencePersonIds) > 5) {
        throw new InvalidArgumentException('Se permiten hasta cinco referencias visuales autorizadas.');
    }
    $referenceDb = cb_event_profile_require_db();
    if ($referencePersonIds) {
        $personMedia = $referenceDb->prepare(
            "SELECT m.id FROM cc_featured_people fp JOIN cc_event_profile_media m ON m.featured_person_id=fp.id
             WHERE fp.id=? AND fp.profile_id=? AND fp.photo_ai_consent=1 AND m.profile_id=fp.profile_id
               AND m.media_kind IN ('photo','reference') AND m.status='ready' AND m.authorized_for_ai=1
               AND m.deleted_at IS NULL ORDER BY m.id DESC LIMIT 1"
        );
        foreach ($referencePersonIds as $personId) {
            $personMedia->execute([$personId, (int) $profile['id']]);
            $mediaId = (int) ($personMedia->fetchColumn() ?: 0);
            if ($mediaId < 1) {
                throw new InvalidArgumentException('Una referencia de protagonista no tiene foto lista y autorizada para IA.');
            }
            $referenceMediaIds[] = $mediaId;
        }
        $referenceMediaIds = array_values(array_unique($referenceMediaIds));
    }
    if ($referenceMediaIds) {
        $marks = implode(',', array_fill(0, count($referenceMediaIds), '?'));
        $referenceCheck = $referenceDb->prepare(
            "SELECT id FROM cc_event_profile_media WHERE profile_id=? AND id IN ($marks)
             AND media_kind IN ('photo','reference') AND status='ready' AND authorized_for_ai=1 AND deleted_at IS NULL"
        );
        $referenceCheck->execute(array_merge([(int) $profile['id']], $referenceMediaIds));
        $validReferenceIds = array_map('intval', $referenceCheck->fetchAll(PDO::FETCH_COLUMN));
        sort($validReferenceIds);
        $expectedReferenceIds = $referenceMediaIds;
        sort($expectedReferenceIds);
        if ($validReferenceIds !== $expectedReferenceIds) {
            throw new InvalidArgumentException('La referencia visual no pertenece al evento o no tiene consentimiento para IA.');
        }
    }
    $payload = $request;
    unset($payload['reference_person_ids']);
    $payload['reference_media_ids'] = $referenceMediaIds;
    unset($payload['prompt'], $payload['negative_prompt']);
    $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $idempotency = strtolower((string) ($request['idempotency_key'] ?? ''));
    if (!preg_match('/^[a-f0-9]{64}$/', $idempotency)) {
        $idempotency = hash('sha256', (int) $profile['id'] . "\0" . $prompt . "\0" . $canonical);
    }
    $pdo = cb_event_profile_require_db();
    $existing = $pdo->prepare('SELECT * FROM cc_event_profile_generations WHERE idempotency_key=?');
    $existing->execute([$idempotency]);
    $row = $existing->fetch();
    if ($row) { return $row; }
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO cc_event_profile_generations
         (profile_id,provider,model_key,status,prompt_text,negative_prompt,request_json,quote_amount,quote_currency,
          quote_credits,quote_expires_at,idempotency_key,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        (int) $profile['id'], $provider, $model, $quoted ? 'quoted' : 'draft', $prompt,
        cb_event_profile_clean_text($request['negative_prompt'] ?? '', 4000, true), $canonical,
        $quoteAmount, $currency, $quoteCredits, $quoteExpiresAt, $idempotency, $now, $now,
    ]);
    $id = (int) $pdo->lastInsertId();
    $fetch = $pdo->prepare('SELECT * FROM cc_event_profile_generations WHERE id=?');
    $fetch->execute([$id]);
    return $fetch->fetch() ?: [];
}

function cb_event_profile_approve_generation(int $generationId, string $actor): array
{
    $actor = cb_event_profile_clean_text($actor, 120) ?? '';
    if ($actor === '') { throw new InvalidArgumentException('La aprobación debe identificar al administrador.'); }
    $pdo = cb_event_profile_require_db();
    $stmt = $pdo->prepare('SELECT * FROM cc_event_profile_generations WHERE id=?');
    $stmt->execute([$generationId]);
    $row = $stmt->fetch();
    if (!$row || (string) $row['status'] !== 'quoted' || empty($row['model_key'])) {
        throw new RuntimeException('La generación debe tener modelo y cotización vigente antes de aprobarse.');
    }
    if (!empty($row['quote_expires_at']) && strtotime((string) $row['quote_expires_at']) < time()) {
        throw new RuntimeException('La cotización expiró; debes consultarla nuevamente.');
    }
    $now = gmdate('Y-m-d H:i:s');
    $pdo->prepare("UPDATE cc_event_profile_generations SET status='approved',approved_at=?,approved_by=?,updated_at=? WHERE id=? AND status='quoted'")
        ->execute([$now, $actor, $now, $generationId]);
    $stmt->execute([$generationId]);
    return $stmt->fetch() ?: [];
}

function cb_event_profile_transition_generation(int $generationId, string $toStatus, array $changes = []): array
{
    $allowed = [
        'approved' => ['generating'], 'generating' => ['processing', 'ready', 'failed'],
        'processing' => ['ready', 'failed'],
    ];
    $pdo = cb_event_profile_require_db();
    $stmt = $pdo->prepare('SELECT * FROM cc_event_profile_generations WHERE id=?');
    $stmt->execute([$generationId]);
    $row = $stmt->fetch();
    $from = (string) ($row['status'] ?? '');
    if (!$row || !in_array($toStatus, $allowed[$from] ?? [], true)) {
        throw new RuntimeException("Transición de generación no permitida: $from → $toStatus.");
    }
    if ($toStatus === 'ready') {
        $mediaId = (int) ($changes['output_media_id'] ?? 0);
        $check = $pdo->prepare(
            "SELECT 1 FROM cc_event_profile_media WHERE id=? AND profile_id=? AND media_kind='intro_video' AND status='ready'"
        );
        $check->execute([$mediaId, (int) $row['profile_id']]);
        if (!$check->fetch()) { throw new RuntimeException('La generación lista requiere un video intro validado del mismo perfil.'); }
    }
    $now = gmdate('Y-m-d H:i:s');
    $sets = ['status=?', 'updated_at=?'];
    $values = [$toStatus, $now];
    $allowedChanges = ['provider_job_id', 'output_media_id', 'error_code', 'error_message'];
    foreach ($allowedChanges as $key) {
        if (array_key_exists($key, $changes)) { $sets[] = "$key=?"; $values[] = $changes[$key]; }
    }
    if ($toStatus === 'generating') { $sets[] = 'started_at=?'; $values[] = $now; }
    if ($toStatus === 'ready') { $sets[] = 'completed_at=?'; $values[] = $now; }
    if ($toStatus === 'failed') { $sets[] = 'failed_at=?'; $values[] = $now; }
    $values[] = $generationId; $values[] = $from;
    $pdo->prepare('UPDATE cc_event_profile_generations SET ' . implode(',', $sets) . ' WHERE id=? AND status=?')->execute($values);
    $stmt->execute([$generationId]);
    return $stmt->fetch() ?: [];
}
