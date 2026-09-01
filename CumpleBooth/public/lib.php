<?php
/**
 * lib.php — funciones compartidas de CumpleBooth (api.php, ver.php, upload.php, admin/).
 * Sin dependencias externas. Baseline PHP 8.2; compatible con PHP 8.0+.
 */

// Regex de validación de slug: solo minúsculas, dígitos y guiones.
const CB_SLUG_RE = '/^[a-z0-9-]+$/';

/** Configuración runtime. Los secretos solo se leen desde env o fuera del webroot. */
function cb_config(?string $key = null)
{
    static $config = null;
    if ($config === null) {
        $root = dirname(__DIR__);
        $config = [
            'storage_mode' => 'json',
            'pdo_dsn' => '',
            'pdo_user' => '',
            'pdo_password' => '',
            'admin_password_hash' => '',
            'app_hmac_key' => '',
            'public_base_url' => '',
            'photo_dir' => $root . '/storage/photos',
            'state_dir' => $root . '/storage/state',
            'invitation_dir' => $root . '/storage/invitations',
            'event_profile_dir' => $root . '/storage/event-profiles',
            'event_profile_enabled' => false,
            'parties_json_path' => __DIR__ . '/data/parties.json',
            'retention_days' => 30,
            'session_idle_seconds' => 7200,
            'session_absolute_seconds' => 43200,
            // Vacío por defecto a propósito: sin binario configurado, la validación de
            // video falla cerrado (rechaza) en vez de aceptar sin inspeccionar.
            'ffprobe_path' => '',
            // Escape para hostings sin ffprobe (Hostinger compartido no lo trae).
            // En true, un video sin ffprobe se acepta con la validación de
            // cabecera (cb_sniff_mp4) + el nombre de destino en whitelist, que
            // son los controles de SEGURIDAD. Lo que se pierde es la validación
            // de CALIDAD (codec h264, duración, dimensiones): un video mal
            // codificado se subirá igual y recién fallará al reproducirse en la
            // tablet. Sigue en false por defecto: hay que activarlo a sabiendas.
            'allow_video_upload_without_ffprobe' => false,

            /* Correo saliente (formulario público). Vacío = no se envía nada y
               el formulario sigue funcionando igual: el lead se guarda y se ve
               en el admin. Se elige así para que una casilla mal configurada
               NUNCA le devuelva un error a quien está pidiendo presupuesto. */
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_user' => '',
            'smtp_password' => '',
            'smtp_from' => '',              // por defecto, el mismo smtp_user
            'smtp_from_name' => 'CumpleClick',
            'smtp_reply_to' => '',          // a dónde contesta el cliente
            'leads_notify_email' => '',     // aviso interno de solicitud nueva
        ];
        $explicitConfig = getenv('CUMPLECLICK_CONFIG_FILE');
        $local = $explicitConfig !== false && $explicitConfig !== ''
            ? (string) $explicitConfig
            : $root . '/config/cumpleclick.local.php';
        if (is_file($local)) {
            $loaded = require $local;
            if (is_array($loaded)) {
                $config = array_replace($config, $loaded);
            }
        }
        $envMap = [
            'CC_STORAGE_MODE' => 'storage_mode', 'CC_PDO_DSN' => 'pdo_dsn',
            'CC_PDO_USER' => 'pdo_user', 'CC_PDO_PASSWORD' => 'pdo_password',
            'CC_ADMIN_PASSWORD_HASH' => 'admin_password_hash', 'CC_APP_HMAC_KEY' => 'app_hmac_key',
            'CC_PUBLIC_BASE_URL' => 'public_base_url',
            'CC_PHOTO_DIR' => 'photo_dir', 'CC_STATE_DIR' => 'state_dir',
            'CC_INVITATION_DIR' => 'invitation_dir',
            'CC_EVENT_PROFILE_DIR' => 'event_profile_dir',
            'CC_EVENT_PROFILE_ENABLED' => 'event_profile_enabled',
            'CC_PARTIES_JSON_PATH' => 'parties_json_path',
            'CC_RETENTION_DAYS' => 'retention_days',
            'CC_FFPROBE_PATH' => 'ffprobe_path',
            'CC_SMTP_HOST' => 'smtp_host', 'CC_SMTP_PORT' => 'smtp_port',
            'CC_SMTP_USER' => 'smtp_user', 'CC_SMTP_PASSWORD' => 'smtp_password',
            'CC_SMTP_FROM' => 'smtp_from', 'CC_SMTP_FROM_NAME' => 'smtp_from_name',
            'CC_SMTP_REPLY_TO' => 'smtp_reply_to',
            'CC_LEADS_NOTIFY_EMAIL' => 'leads_notify_email',
        ];
        foreach ($envMap as $env => $name) {
            $value = getenv($env);
            if ($value !== false && $value !== '') {
                $config[$name] = $value;
            }
        }
        if (!in_array($config['storage_mode'], ['json', 'db'], true)) {
            throw new RuntimeException('CC_STORAGE_MODE debe ser json o db.');
        }
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

function cb_storage_mode(): string
{
    return (string) cb_config('storage_mode');
}

/** Rechaza storage mutable dentro del document root; los fallos son fail-closed. */
function cb_private_dir(string $path, string $label): string
{
    $path = rtrim(str_replace('\\', '/', trim($path)), '/');
    $absolute = preg_match('#^(?:[A-Za-z]:/|/)#', $path) === 1;
    if (!$absolute || $path === '' || preg_match('#(?:^|/)\.\.(?:/|$)#', $path)) {
        throw new RuntimeException("$label debe ser una ruta absoluta privada.");
    }
    $compare = DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    $documentRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($documentRoot !== '') {
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $rootCompare = DIRECTORY_SEPARATOR === '\\' ? strtolower($documentRoot) : $documentRoot;
        if ($compare === $rootCompare || strpos($compare . '/', $rootCompare . '/') === 0) {
            throw new RuntimeException("$label debe quedar fuera del document root.");
        }
    }
    $publicDir = rtrim(str_replace('\\', '/', __DIR__), '/');
    $publicCompare = DIRECTORY_SEPARATOR === '\\' ? strtolower($publicDir) : $publicDir;
    if ($compare === $publicCompare || strpos($compare . '/', $publicCompare . '/') === 0) {
        throw new RuntimeException("$label no puede quedar dentro del directorio público.");
    }
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function cb_hmac(string $value, string $purpose): string
{
    $key = (string) cb_config('app_hmac_key');
    if (strlen($key) < 32) {
        throw new RuntimeException('Falta CC_APP_HMAC_KEY seguro (mínimo 32 caracteres).');
    }
    return hash_hmac('sha256', $purpose . "\0" . $value, $key);
}

function cb_public_base_url(): string
{
    $url = rtrim((string) cb_config('public_base_url'), '/');
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $url)) {
        throw new RuntimeException('Falta CC_PUBLIC_BASE_URL válido.');
    }
    return $url;
}

function cb_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = (string) cb_config('pdo_dsn');
    if ($dsn === '') {
        throw new RuntimeException('Falta CC_PDO_DSN para storage_mode=db.');
    }
    $pdo = new PDO($dsn, (string) cb_config('pdo_user'), (string) cb_config('pdo_password'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

/** Ruta absoluta a la carpeta data/ (junto a este archivo). */
function cb_data_dir(): string
{
    return __DIR__ . '/data';
}

function cb_themes_path(): string
{
    return cb_data_dir() . '/themes.json';
}

function cb_parties_path(): string
{
    return (string) cb_config('parties_json_path');
}

/**
 * Ruta absoluta a la carpeta themes/ (siempre hermana de este archivo: tanto
 * en public/ como en dist/ conviven lib.php y themes/ en el mismo nivel).
 */
function cb_themes_dir(): string
{
    return __DIR__ . '/themes';
}

/**
 * Sanitiza un slug candidato: minúsculas, solo [a-z0-9-], sin espacios.
 * NO valida longitud — eso lo hace el llamador según el contexto (party vs lookup).
 */
function cb_sanitize_slug(string $raw): string
{
    $s = strtolower(trim($raw));
    // Reemplaza cualquier caracter fuera de a-z0-9- por guion
    $s = preg_replace('/[^a-z0-9-]+/', '-', $s);
    // Colapsa guiones repetidos y recorta guiones en extremos
    $s = preg_replace('/-+/', '-', $s ?? '');
    return trim((string) $s, '-');
}

/** true si $slug cumple el patrón estricto [a-z0-9-]{min,max}. */
function cb_valid_slug(string $slug, int $min = 1, int $max = 40): bool
{
    $len = strlen($slug);
    if ($len < $min || $len > $max) {
        return false;
    }
    return preg_match(CB_SLUG_RE, $slug) === 1;
}

/**
 * Genera un slug automático y legible a partir de un nombre (para el backoffice):
 * minúsculas, sin acentos, espacios -> guiones.
 */
function cb_slugify(string $nombre): string
{
    $s = trim($nombre);
    // Quita acentos/diacríticos de forma portable (sin extensión intl obligatoria).
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
    ];
    $s = strtr($s, $map);
    $s = strtolower($s);
    return cb_sanitize_slug($s);
}

/** public_slug: [a-z0-9-]{1,80}. */
function cb_valid_public_slug(string $slug): bool
{
    return cb_valid_slug($slug, 1, 80);
}

/** Directorio privado para archivos de invitación. Falla de forma segura. */
function cb_invitation_dir(): string
{
    $configured = (string) cb_config('invitation_dir');
    if ($configured === '') {
        $configured = dirname((string) cb_config('photo_dir')) . '/invitations';
    }
    $path = cb_private_dir($configured, 'invitation_dir');
    if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
        throw new RuntimeException('No se pudo crear o acceder al directorio privado de invitaciones.');
    }
    return $path;
}

/** Token opaco criptográfico de $bytes bytes (por defecto 128 bits). */
function cb_opaque_token(int $bytes = 16): string
{
    if ($bytes < 12) {
        throw new InvalidArgumentException('El token opaco requiere al menos 96 bits.');
    }
    return bin2hex(random_bytes($bytes));
}

function cb_hash_token(string $token): string
{
    return hash('sha256', $token);
}

/** Genera un public_slug único y no enumerable. */
function cb_generate_public_slug(PDO $pdo, string $birthdayPersonName, string $themePublicName): string
{
    $namePart = cb_slugify($birthdayPersonName);
    $themePart = cb_slugify($themePublicName);
    $base = trim($namePart . '-' . $themePart, '-');
    if ($base === '') {
        $base = 'fiesta';
    }
    $base = substr($base, 0, 55);
    $stmt = $pdo->prepare('SELECT 1 FROM cc_parties WHERE public_slug = ?');
    for ($i = 0; $i < 10; $i++) {
        $suffix = bin2hex(random_bytes(12)); // 96 bits (mínimo 96, cabe en VARCHAR(80): 55 + 1 + 24)
        $slug = $base . '-' . $suffix;
        $stmt->execute([$slug]);
        if ($stmt->fetch() === false) {
            return $slug;
        }
    }
    throw new RuntimeException('No se pudo generar un public_slug único.');
}

/** Nombre público de una temática. */
function cb_theme_public_name(string $themeSlug): string
{
    $themes = cb_load_themes();
    if (!isset($themes['themes'][$themeSlug]) || !is_array($themes['themes'][$themeSlug])) {
        return $themeSlug;
    }
    return (string) ($themes['themes'][$themeSlug]['nombre'] ?? $themeSlug);
}

/** Compila un prompt de invitación y rechaza placeholders sin resolver. */
function cb_compile_invitation_prompt(string $template, array $values): array
{
    // Rechazar cada campo obligatorio vacío por separado ANTES de compilar. strtr()
    // reemplaza un placeholder ausente por '' (cadena vacía), lo que borra el
    // placeholder del texto sin dejar ningún "[...]" residual — el chequeo de
    // placeholders sin resolver de más abajo nunca detecta ese caso, así que sin
    // esta validación explícita el compilador podía devolver ok=true con el
    // nombre, fecha, hora o dirección faltando en silencio dentro del prompt.
    $fields = [
        'birthday_person_name' => 'nombre del cumpleañero',
        'event_date' => 'fecha',
        'event_time' => 'hora',
        'address' => 'dirección',
    ];
    $missing = [];
    foreach ($fields as $field => $label) {
        if (trim((string) ($values[$field] ?? '')) === '') {
            $missing[] = $label;
        }
    }
    if (!empty($missing)) {
        return ['ok' => false, 'error' => 'Faltan datos obligatorios para compilar el prompt: ' . implode(', ', $missing) . '.', 'prompt' => ''];
    }

    $date = trim((string) $values['event_date']);
    $time = trim((string) $values['event_time']);
    $allowed = [
        '[NOMBRE_DEL_CUMPLEAÑERO]' => trim((string) $values['birthday_person_name']),
        '[FECHA_Y_HORA]' => $date . ' ' . $time,
        '[DIRECCIÓN]' => trim((string) $values['address']),
    ];
    $compiled = strtr($template, $allowed);
    if (preg_match('/\[[A-ZÁÉÍÓÚÜÑ_]+\]/u', $compiled) === 1) {
        return ['ok' => false, 'error' => 'El prompt contiene placeholders sin resolver.', 'prompt' => ''];
    }
    return ['ok' => true, 'error' => '', 'prompt' => $compiled];
}

/** Carga un archivo JSON y lo decodifica como array asociativo. false si falla. */
function cb_load_json_file(string $path)
{
    if (!is_file($path)) {
        return false;
    }
    $fp = fopen($path, 'rb');
    if (!$fp) {
        return false;
    }
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if ($raw === false || $raw === '') {
        return false;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return false;
    }
    return $data;
}

/** Escritura JSON atómica reutilizada por el modo legacy y sus índices privados. */
function cb_save_json_file(string $path, array $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        return false;
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $tmp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
    $fp = @fopen($tmp, 'xb');
    if (!$fp) {
        return false;
    }
    $ok = flock($fp, LOCK_EX) && fwrite($fp, $json) !== false && fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if (!$ok || !@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    @chmod($path, 0660);
    return true;
}

/** Carga data/themes.json → ['themes' => [...]] o false. */
function cb_load_themes()
{
    return cb_load_json_file(cb_themes_path());
}

/** Carga data/parties.json → ['parties' => [...]] o false si no existe/corrupto. */
function cb_load_parties()
{
    if (cb_storage_mode() === 'db') {
        $pdo = cb_pdo();
        $rows = $pdo->query('SELECT id, public_slug, admin_label, birthday_person_name, event_type, theme_slug, event_date, active, frame_box_json, gallery_pin_hash, gallery_pin_hmac, service_plan, gallery_enabled, created_at, updated_at, anonymized_at FROM cc_parties ORDER BY created_at DESC, id DESC')->fetchAll();
        $guestStmt = $pdo->prepare('SELECT name, gender FROM cc_guests WHERE party_id = ? ORDER BY sort_order, id');
        $parties = [];
        foreach ($rows as $row) {
            $guestStmt->execute([(int) $row['id']]);
            $guests = [];
            foreach ($guestStmt->fetchAll() as $guest) {
                $guests[] = ['name' => (string) $guest['name'], 'g' => (string) $guest['gender']];
            }
            $frame = $row['frame_box_json'] ? json_decode((string) $row['frame_box_json'], true) : null;
            $publicSlug = (string) $row['public_slug'];
            $galleryEnabled = (bool) ($row['gallery_enabled'] ?? 0);
            $servicePlan = in_array((string) ($row['service_plan'] ?? ''), ['booth', 'full'], true) ? (string) $row['service_plan'] : 'booth';
            $eventType = (string) ($row['event_type'] ?? '') === 'baby_shower' ? 'baby_shower' : 'child_birthday';
            $parties[$publicSlug] = [
                'public_slug' => $publicSlug,
                'admin_label' => (string) ($row['admin_label'] ?? ''),
                'birthday_person_name' => (string) ($row['birthday_person_name'] ?? ''),
                'nombre' => (string) ($row['birthday_person_name'] ?? ''),
                'event_type' => $eventType,
                'tema' => (string) $row['theme_slug'],
                'theme_slug' => (string) $row['theme_slug'],
                'fecha' => (string) ($row['event_date'] ?? ''), 'activa' => (bool) $row['active'],
                'invitados' => $guests, 'frameBox' => is_array($frame) ? $frame : null,
                'galeriaPinHash' => (string) ($row['gallery_pin_hash'] ?? ''),
                'galeriaPinHmac' => (string) ($row['gallery_pin_hmac'] ?? ''),
                'galeriaHabilitada' => $galleryEnabled && !empty($row['gallery_pin_hash']),
                'service_plan' => $servicePlan,
                'gallery_enabled' => $galleryEnabled,
                'creada' => (string) $row['created_at'],
                'anonymizedAt' => (string) ($row['anonymized_at'] ?? ''),
            ];
        }
        return ['parties' => $parties];
    }
    $data = cb_load_json_file(cb_parties_path());
    if ($data === false || !isset($data['parties']) || !is_array($data['parties'])) {
        return ['parties' => []];
    }
    $normalized = [];
    foreach ($data['parties'] as $key => $party) {
        if (!is_array($party)) {
            continue;
        }
        $publicSlug = (string) ($party['public_slug'] ?? $key);
        $birthdayName = (string) ($party['birthday_person_name'] ?? $party['nombre'] ?? '');
        $themeSlug = (string) ($party['theme_slug'] ?? $party['tema'] ?? '');
        $servicePlan = in_array((string) ($party['service_plan'] ?? ''), ['booth', 'full'], true) ? (string) $party['service_plan'] : 'booth';
        $galleryEnabled = (bool) ($party['gallery_enabled'] ?? 0);
        $eventType = (string) ($party['event_type'] ?? '') === 'baby_shower' ? 'baby_shower' : 'child_birthday';
        $normalized[$publicSlug] = [
            'public_slug' => $publicSlug,
            'admin_label' => (string) ($party['admin_label'] ?? ''),
            'birthday_person_name' => $birthdayName,
            'nombre' => $birthdayName,
            'event_type' => $eventType,
            'tema' => $themeSlug,
            'theme_slug' => $themeSlug,
            'fecha' => (string) ($party['fecha'] ?? ''),
            'activa' => (bool) ($party['activa'] ?? false),
            'invitados' => is_array($party['invitados'] ?? null) ? $party['invitados'] : [],
            'frameBox' => cb_normalize_frame_box($party['frameBox'] ?? null),
            'galeriaPinHash' => (string) ($party['galeriaPinHash'] ?? ''),
            'galeriaPinHmac' => (string) ($party['galeriaPinHmac'] ?? ''),
            'galeriaHabilitada' => $galleryEnabled && !empty($party['galeriaPinHash'] ?? ''),
            'service_plan' => $servicePlan,
            'gallery_enabled' => $galleryEnabled,
            'creada' => (string) ($party['creada'] ?? gmdate('Y-m-d H:i:s')),
            'anonymizedAt' => (string) ($party['anonymizedAt'] ?? ''),
        ];
    }
    return ['parties' => $normalized];
}

/**
 * Escritura atómica de parties.json: tmp + LOCK_EX + rename.
 * $data debe ser el array completo ['parties' => [...]].
 */
function cb_save_parties(array $data): bool
{
    $parties = $data['parties'] ?? null;
    if (!is_array($parties)) {
        return false;
    }
    foreach ($parties as &$party) {
        if (!is_array($party)) {
            continue;
        }
        $pin = (string) ($party['galeriaPin'] ?? '');
        if ($pin !== '') {
            if (!cb_valid_galeria_pin($pin)) {
                return false;
            }
            $party['galeriaPinHash'] = password_hash(cb_hmac($pin, 'gallery-pin'), PASSWORD_DEFAULT);
            $party['galeriaPinHmac'] = '';
        }
        unset($party['galeriaPin'], $party['galeriaHabilitada']);
    }
    unset($party);
    if (cb_storage_mode() === 'json') {
        $toSave = [];
        foreach ($parties as $key => $party) {
            if (!is_array($party)) {
                continue;
            }
            $publicSlug = (string) ($party['public_slug'] ?? $key);
            $birthdayName = (string) ($party['birthday_person_name'] ?? $party['nombre'] ?? '');
            $themeSlug = (string) ($party['theme_slug'] ?? $party['tema'] ?? '');
            $servicePlan = in_array((string) ($party['service_plan'] ?? ''), ['booth', 'full'], true) ? (string) $party['service_plan'] : 'booth';
            $galleryEnabled = (bool) ($party['gallery_enabled'] ?? 0);
            $eventType = (string) ($party['event_type'] ?? '') === 'baby_shower' ? 'baby_shower' : 'child_birthday';
            $toSave[$publicSlug] = [
                'public_slug' => $publicSlug,
                'admin_label' => (string) ($party['admin_label'] ?? ''),
                'birthday_person_name' => $birthdayName,
                'event_type' => $eventType,
                'theme_slug' => $themeSlug,
                'fecha' => (string) ($party['fecha'] ?? ''),
                'activa' => (bool) ($party['activa'] ?? false),
                'invitados' => is_array($party['invitados'] ?? null) ? $party['invitados'] : [],
                'frameBox' => cb_normalize_frame_box($party['frameBox'] ?? null),
                'galeriaPinHash' => (string) ($party['galeriaPinHash'] ?? ''),
                'galeriaPinHmac' => (string) ($party['galeriaPinHmac'] ?? ''),
                'service_plan' => $servicePlan,
                'gallery_enabled' => $galleryEnabled,
                'creada' => (string) ($party['creada'] ?? gmdate('Y-m-d H:i:s')),
                'anonymizedAt' => (string) ($party['anonymizedAt'] ?? ''),
            ];
        }
        return cb_save_json_file(cb_parties_path(), ['parties' => $toSave]);
    }

    $pdo = cb_pdo();
    $pdo->beginTransaction();
    try {
        $existing = $pdo->query('SELECT id, public_slug FROM cc_parties')->fetchAll();
        $existingMap = [];
        foreach ($existing as $row) {
            $existingMap[(string) $row['public_slug']] = (int) $row['id'];
        }
        $upsert = $pdo->prepare('UPDATE cc_parties SET admin_label=?, birthday_person_name=?, event_type=?, theme_slug=?, event_date=?, active=?, frame_box_json=?, gallery_pin_hash=?, gallery_pin_hmac=?, service_plan=?, gallery_enabled=?, updated_at=?, anonymized_at=? WHERE public_slug=?');
        $insert = $pdo->prepare('INSERT INTO cc_parties (public_slug,admin_label,birthday_person_name,event_type,theme_slug,event_date,active,frame_box_json,gallery_pin_hash,gallery_pin_hmac,service_plan,gallery_enabled,created_at,updated_at,anonymized_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $syncInvitationEventType = $pdo->prepare('UPDATE cc_invitations SET event_type=?, updated_at=? WHERE party_id=?');
        $deleteGuests = $pdo->prepare('DELETE FROM cc_guests WHERE party_id=?');
        $insertGuest = $pdo->prepare('INSERT INTO cc_guests (party_id,name,gender,sort_order,created_at) VALUES (?,?,?,?,?)');
        $seen = [];
        foreach ($parties as $key => $party) {
            if (!is_array($party)) {
                continue;
            }
            $publicSlug = (string) ($party['public_slug'] ?? $key);
            if (!cb_valid_public_slug($publicSlug)) {
                throw new RuntimeException('Registro de fiesta inválido: public_slug no válido.');
            }
            $now = gmdate('Y-m-d H:i:s');
            $frameJson = is_array($party['frameBox'] ?? null) ? json_encode($party['frameBox']) : null;
            $servicePlan = in_array((string) ($party['service_plan'] ?? ''), ['booth', 'full'], true) ? (string) $party['service_plan'] : 'booth';
            $eventType = (string) ($party['event_type'] ?? '') === 'baby_shower' ? 'baby_shower' : 'child_birthday';
            $values = [
                (string) ($party['admin_label'] ?? ''),
                (string) ($party['birthday_person_name'] ?? $party['nombre'] ?? ''),
                $eventType,
                (string) ($party['theme_slug'] ?? $party['tema'] ?? ''),
                ($party['fecha'] ?? '') ?: null,
                !empty($party['activa']) ? 1 : 0,
                $frameJson,
                ($party['galeriaPinHash'] ?? '') ?: null,
                ($party['galeriaPinHmac'] ?? '') ?: null,
                $servicePlan,
                !empty($party['gallery_enabled']) ? 1 : 0,
                $now,
                ($party['anonymizedAt'] ?? '') ?: null,
            ];
            if (isset($existingMap[$publicSlug])) {
                $upsert->execute(array_merge($values, [$publicSlug]));
                $id = $existingMap[$publicSlug];
            } else {
                $created = (string) ($party['creada'] ?? $now);
                $insert->execute([$publicSlug, $values[0], $values[1], $values[2], $values[3], $values[4], $values[5], $values[6], $values[7], $values[8], $values[9], $values[10], $created, $now, $values[12]]);
                $id = (int) $pdo->lastInsertId();
            }
            // La fiesta es la fuente para sus invitaciones vinculadas. Las
            // invitaciones sin party_id conservan su modalidad independiente.
            $syncInvitationEventType->execute([$eventType, $now, $id]);
            $deleteGuests->execute([$id]);
            foreach (array_values($party['invitados'] ?? []) as $order => $guest) {
                if (is_array($guest) && trim((string) ($guest['name'] ?? '')) !== '') {
                    $insertGuest->execute([$id, trim((string) $guest['name']), ($guest['g'] ?? '') === 'm' ? 'm' : 'f', $order, $now]);
                }
            }
            $seen[$publicSlug] = true;
        }
        $deleteParty = $pdo->prepare('DELETE FROM cc_parties WHERE public_slug=?');
        foreach ($existingMap as $publicSlug => $id) {
            if (!isset($seen[$publicSlug])) {
                $deleteParty->execute([$publicSlug]);
            }
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('CumpleClick save parties: ' . $e->getMessage());
        return false;
    }
}

/**
 * Sanea la config de un mini-juego antes de publicarla en api.php. Vale tanto
 * para el juego global de la temática como para el de un personaje concreto.
 * El contrato del API es lista blanca: nunca se copia el objeto crudo del JSON.
 * Devuelve [] si no hay juego válido.
 */
function cb_sanitize_theme_game($rawGame, string $base = '', string $diskDir = ''): array
{
    if (!is_array($rawGame)) {
        return [];
    }
    $kind = trim((string) ($rawGame['kind'] ?? ''));
    // 'concierto3d' = El Show (StageConcert3D.jsx), la misión Full de las seis
    // temáticas completas. Es un juego de ritmo, no el runner de carriles de
    // 'mundo3d': lo reemplazó en las seis, y hoy ninguna temática declara
    // 'mundo3d'. ThemeWorld3D sigue montado y el kind se sigue aceptando por si
    // una temática nueva lo quiere, así que el saneador entiende los dos.
    if (!in_array($kind, ['copos', 'armar-muneco', 'fichas', 'ritmo', 'escudo', 'mundo3d', 'concierto3d'], true)) {
        return [];
    }
    $game = ['kind' => $kind];
    $seconds = (int) ($rawGame['seconds'] ?? 15);
    $game['seconds'] = max(5, min(30, $seconds ?: 15));
    $label = trim((string) ($rawGame['label'] ?? ''));
    if ($label !== '' && mb_strlen($label) <= 40) {
        $game['label'] = $label;
    }
    // Imagen del rompecabezas: solo un basename existente dentro de la carpeta
    // publica del tema, nunca una ruta que venga del JSON.
    $image = trim((string) ($rawGame['image'] ?? ''));
    $imageOk = $image !== ''
        && basename($image) === $image
        && preg_match('/\A[a-z0-9][a-z0-9._-]*\.(jpe?g|png|webp)\z/i', $image) === 1;
    if ($imageOk && $diskDir !== '' && is_file($diskDir . $image)) {
        $game['image'] = $base . $image;
    }
    // Tablero: solo aplica a 'fichas' (grilla de piezas). 'armar-muneco' usa
    // las 6 piezas fijas del muñeco, no una grilla — publicar cols/filas ahí
    // igual no rompía nada (el frontend los ignora), pero era ruido sin uso.
    if ($kind === 'fichas') {
        $cols = (int) ($rawGame['cols'] ?? 3);
        $filas = (int) ($rawGame['filas'] ?? 3);
        $game['cols'] = max(2, min(4, $cols ?: 3));
        $game['filas'] = max(2, min(4, $filas ?: 3));
    }
    if ($kind === 'copos') {
        $emojis = [];
        foreach ((array) ($rawGame['emojis'] ?? []) as $emoji) {
            $emoji = trim((string) $emoji);
            if ($emoji !== '' && mb_strlen($emoji) <= 4) {
                $emojis[] = $emoji;
            }
        }
        if ($emojis) {
            $game['emojis'] = array_slice(array_values(array_unique($emojis)), 0, 8);
        }
    }
    if ($kind === 'ritmo') {
        $lanes = (int) ($rawGame['lanes'] ?? 4);
        $game['lanes'] = max(3, min(5, $lanes ?: 4));
    }
    if ($kind === 'mundo3d') {
        $world = trim((string) ($rawGame['world'] ?? ''));
        $allowedWorlds = [
            'turbo-track',
            'puppy-park',
            'tropical-wave',
            'ice-bridge',
            'neon-stage',
            'hero-city',
        ];
        $game['world'] = in_array($world, $allowedWorlds, true) ? $world : 'puppy-park';
        $targetScore = (int) ($rawGame['targetScore'] ?? 12);
        $game['targetScore'] = max(5, min(30, $targetScore ?: 12));
        foreach (['collectible' => '⭐', 'hazard' => '💥'] as $field => $fallback) {
            $value = trim((string) ($rawGame[$field] ?? ''));
            $game[$field] = ($value !== '' && mb_strlen($value) <= 4) ? $value : $fallback;
        }
    }
    // El Show (misión Full de las temáticas completas). Solo publica el
    // VESTUARIO del escenario: la dificultad no se configura por temática a
    // propósito, para que los puntajes sean comparables entre fiestas.
    if ($kind === 'concierto3d') {
        $stage = trim((string) ($rawGame['stage'] ?? ''));
        $allowedStages = [
            'neon-arena',
            'ice-gala',
            'beach-luau',
            'podium-night',
            'backyard-fiesta',
            'rooftop-city',
        ];
        $game['stage'] = in_array($stage, $allowedStages, true) ? $stage : 'neon-arena';
    }
    return $game;
}

/**
 * Construye el objeto "theme" resuelto (rutas de assets incluidas) según el
 * contrato de api.php. $themeSlug ya debe estar validado/saneado.
 */
/** Los seis tipos de juego que entiende el kiosco. Fuente única. */
function cb_game_kinds(): array
{
    return ['copos', 'armar-muneco', 'fichas', 'ritmo', 'escudo', 'mundo3d'];
}

/**
 * Tipos de juego que REALMENTE ofrece una temática, en el orden en que
 * aparecen. Sirve para que el admin muestre solo las casillas que tienen
 * sentido en esa fiesta, en vez de los cinco tipos siempre.
 */
function cb_theme_available_game_kinds(array $themeData): array
{
    $kinds = [];
    $push = function ($raw) use (&$kinds) {
        if (!is_array($raw)) {
            return;
        }
        $k = trim((string) ($raw['kind'] ?? ''));
        if ($k !== '' && in_array($k, cb_game_kinds(), true) && !in_array($k, $kinds, true)) {
            $kinds[] = $k;
        }
    };
    foreach (($themeData['personajes'] ?? []) as $p) {
        $g = $p['game'] ?? null;
        if (is_array($g) && $g === array_values($g)) {
            foreach ($g as $one) {
                $push($one);
            }
        } else {
            $push($g);
        }
    }
    $push($themeData['game'] ?? null);
    return $kinds;
}

/**
 * Normaliza la selección de juegos guardada en una fiesta.
 *
 * `null` = la fiesta no eligió nada y juega la cadena completa de la temática
 * (comportamiento histórico, no se toca ninguna fiesta existente). Un array
 * vacío SÍ es una elección válida: significa "esta fiesta no juega".
 */
function cb_sanitize_party_games($raw): ?array
{
    if (!is_array($raw)) {
        return null;
    }
    $out = [];
    foreach ($raw as $k) {
        $k = trim((string) $k);
        if (in_array($k, cb_game_kinds(), true) && !in_array($k, $out, true)) {
            $out[] = $k;
        }
    }
    return $out;
}

function cb_build_theme_payload(
    string $themeSlug,
    array $themeData,
    ?array $juegosPermitidos = null,
    string $servicePlan = 'booth'
): array
{
    $base = 'themes/' . $themeSlug . '/';
    $themeDiskDir = cb_themes_dir() . '/' . $themeSlug . '/';
    $personajes = [];
    foreach (($themeData['personajes'] ?? []) as $p) {
        $img = (string) ($p['img'] ?? '');
        // Recorte transparente OPCIONAL derivado del nombre del img: "<base>-cut.png".
        $pngBase = pathinfo($img, PATHINFO_FILENAME);
        $pngName = $pngBase !== '' ? $pngBase . '-cut.png' : '';
        $pngExists = $pngName !== '' && is_file($themeDiskDir . $pngName);
        // Atlas multivista del runner premium. Solo se publica para fiestas
        // Full y únicamente si el archivo físico existe; el cliente no debe
        // adivinar rutas ni provocar una cascada de 404.
        $runnerAtlasName = $pngBase !== '' ? $pngBase . '-run-atlas.png' : '';
        $runnerAtlasExists = $servicePlan === 'full'
            && $runnerAtlasName !== ''
            && is_file($themeDiskDir . 'game3d/' . $runnerAtlasName);
        // Juego propio del personaje (opcional), con prioridad sobre el de la
        // temática. Puede ser UN juego (objeto) o VARIOS (array de objetos) —
        // Luis 2026-07-25: "tienen que ser los 2 juegos, el anterior y este
        // nuevo". Con array se sanea cada uno y se publica la lista completa;
        // el frontend elige al azar cuál toca esta vez (resolveThemeFlow.js).
        $rawGame = $p['game'] ?? null;
        $isGameList = is_array($rawGame) && $rawGame === array_values($rawGame);
        if ($isGameList) {
            $gameField = [];
            foreach ($rawGame as $g) {
                $sg = cb_sanitize_theme_game($g, $base, $themeDiskDir);
                if ($sg) {
                    $gameField[] = $sg;
                }
            }
        } else {
            $one = cb_sanitize_theme_game($rawGame, $base, $themeDiskDir);
            $gameField = $one ? [$one] : [];
        }
        // Filtro por fiesta: solo sobreviven los tipos que el admin habilitó,
        // conservando el orden que definió la temática. Con null no se filtra
        // nada — las fiestas que nunca tocaron esta opción siguen igual.
        if ($juegosPermitidos !== null) {
            $gameField = array_values(array_filter($gameField, function ($g) use ($juegosPermitidos) {
                return in_array($g['kind'], $juegosPermitidos, true);
            }));
        }
        // Sin juegos se publica un objeto vacío, no una lista: es lo que el
        // frontend ya interpreta como "este personaje no juega".
        $gameField = $gameField ?: new stdClass();
        $personajes[] = [
            'emoji'     => (string) ($p['emoji'] ?? ''),
            'name'      => (string) ($p['name'] ?? ''),
            'img'       => $base . ltrim($img, '/'),
            'png'       => $pngName !== '' ? $base . $pngName : '',
            'pngExists' => $pngExists,
            'runnerAtlas' => $runnerAtlasExists
                ? $base . 'game3d/' . $runnerAtlasName
                : '',
            'runnerAtlasExists' => $runnerAtlasExists,
            'game'      => $gameField,
        ];
    }

    // Assets opcionales de la experiencia previa a cámara. Solo se publican
    // nombres de archivo simples y existentes dentro del directorio del tema.
    $photoSession = [];
    foreach ([
        'video' => 'mp4',
        'poster' => '(?:png|jpe?g|webp)',
        'teaser' => '(?:png|jpe?g|webp)',
        'teaserVideo' => 'mp4',
    ] as $key => $extensionPattern) {
        $assetName = trim((string) ($themeData['photoSession'][$key] ?? ''));
        $isSafeName = $assetName !== ''
            && basename($assetName) === $assetName
            && preg_match('/\A[a-z0-9][a-z0-9._-]*\.' . $extensionPattern . '\z/i', $assetName) === 1;
        if ($isSafeName && is_file($themeDiskDir . $assetName)) {
            $photoSession[$key] = $base . $assetName;
        }
    }
    // Lista opcional de personajes con pase de artista: solo nombres que
    // existan en la temática. Sin lista, el front aplica el pase a todos.
    if (!empty($photoSession)) {
        $declaredNames = [];
        foreach (($themeData['personajes'] ?? []) as $p) {
            $declaredNames[] = (string) ($p['name'] ?? '');
        }
        $sessionCharacters = [];
        foreach ((array) ($themeData['photoSession']['characters'] ?? []) as $charName) {
            $charName = trim((string) $charName);
            if ($charName !== '' && in_array($charName, $declaredNames, true)) {
                $sessionCharacters[] = $charName;
            }
        }
        if (!empty($sessionCharacters)) {
            $photoSession['characters'] = array_values(array_unique($sessionCharacters));
        }
        // Rótulo del cuadro de la ruleta: independiente de qué personajes
        // reciben el pase de artista (p.ej. la imagen puede mostrar a toda
        // la familia aunque el pase siga siendo solo para 2). Texto corto,
        // sin HTML — se imprime tal cual en el DOM.
        $teaserLabel = trim((string) ($themeData['photoSession']['teaserLabel'] ?? ''));
        if ($teaserLabel !== '' && mb_strlen($teaserLabel) <= 40) {
            $photoSession['teaserLabel'] = $teaserLabel;
        }
    }

    // Videos opcionales del tema (bienvenida, suspenso previo a la foto).
    // Nunca se acepta una ruta: solo un basename .mp4 existente dentro de la
    // carpeta pública del tema.
    $safeThemeVideo = static function (string $name) use ($themeData, $themeDiskDir, $base): string {
        $video = trim((string) ($themeData['videos'][$name] ?? ''));
        $isSafe = $video !== ''
            && basename($video) === $video
            && preg_match('/\A[a-z0-9][a-z0-9._-]*\.mp4\z/i', $video) === 1;
        return ($isSafe && is_file($themeDiskDir . $video)) ? $base . $video : '';
    };
    $videos = [];
    if (($v = $safeThemeVideo('welcome')) !== '') {
        $videos['welcome'] = $v;
    }
    if (($v = $safeThemeVideo('revelacion')) !== '') {
        $videos['revelacion'] = $v;
    }
    if (($v = $safeThemeVideo('despedida')) !== '') {
        $videos['despedida'] = $v;
    }

    $game = cb_sanitize_theme_game($themeData['game'] ?? null, $base, $themeDiskDir);
    // Misión 3D exclusiva del plan Full. El dato ni siquiera cruza la API
    // cuando la fiesta es Booth: el frontend no es el único guardián.
    $fullGame = $servicePlan === 'full'
        ? cb_sanitize_theme_game($themeData['fullGame'] ?? null, $base, $themeDiskDir)
        : [];

    // Presentación WOW previa al juego Full. Igual que la misión premium,
    // este asset no cruza la API para fiestas Booth. Solo se admite un
    // basename MP4 existente dentro del directorio de la temática.
    $videoEstrella = '';
    if ($servicePlan === 'full') {
        $assetName = trim((string) ($themeData['videoEstrella'] ?? ''));
        $isSafeName = $assetName !== ''
            && basename($assetName) === $assetName
            && preg_match('/\A[a-z0-9][a-z0-9._-]*\.mp4\z/i', $assetName) === 1;
        if ($isSafeName && is_file($themeDiskDir . $assetName)) {
            $videoEstrella = $base . $assetName;
        }
    }

    return [
        'slug'       => $themeSlug,
        'nombre'     => (string) ($themeData['nombre'] ?? $themeSlug),
        'diploma'    => (string) ($themeData['diploma'] ?? ''),
        'colors'     => $themeData['colors'] ?? new stdClass(),
        'confetti'   => $themeData['confetti'] ?? [],
        'personajes' => $personajes,
        'photoSession' => $photoSession ?: new stdClass(),
        'game'       => $game ?: new stdClass(),
        'fullGame'   => $fullGame ?: new stdClass(),
        'videoEstrella' => $videoEstrella,
        // "none" desactiva la transicion 3D de pista de juguete, que es
        // propia de Carreras y desentonaba en temas sin carretera.
        'transition' => in_array((string) ($themeData['transition'] ?? ''), ['none'], true) ? 'none' : '',
        'videos'     => $videos ?: new stdClass(),
        'images'     => [
            'banner' => $base . 'fondo-banner.jpg',
            'sala'   => $base . 'fondo-sala.jpg',
            'roulette' => is_file($themeDiskDir . 'roulette/roulette-background-v1.png')
                ? $base . 'roulette/roulette-background-v1.png'
                : '',
        ],
        'musica'     => $base . 'musica-fondo.mp3',
        // Música propia de la pantalla de juegos (opcional). Solo se publica si
        // el archivo existe: sin él, el juego sigue sonando con la de fondo.
        'musicaJuego' => is_file($themeDiskDir . 'musica-juego.mp3')
            ? $base . 'musica-juego.mp3'
            : '',
    ];
}

/**
 * Resuelve party + theme para un slug dado (usado por api.php y ver.php).
 * Devuelve:
 *   ['ok'=>true,  'party'=>[...], 'theme'=>[...]]
 *   ['ok'=>false, 'error'=>'bad_slug'|'not_found'|'inactive', 'code'=>400|404|403]
 * IMPORTANTE: si el slug es inválido, retorna de inmediato sin tocar el filesystem.
 */
function cb_resolve_party(string $slugRaw): array
{
    // Slug crudo: se valida ANTES de tocar disco.
    if (!cb_valid_public_slug($slugRaw)) {
        return ['ok' => false, 'error' => 'bad_slug', 'code' => 400];
    }
    $slug = $slugRaw;

    $partiesData = cb_load_parties();
    $parties = $partiesData['parties'];
    if (!isset($parties[$slug]) || !is_array($parties[$slug])) {
        return ['ok' => false, 'error' => 'not_found', 'code' => 404];
    }
    $party = $parties[$slug];

    if (empty($party['activa'])) {
        return ['ok' => false, 'error' => 'inactive', 'code' => 403];
    }

    $themeSlugRaw = (string) ($party['tema'] ?? '');
    $themeSlug = cb_valid_slug($themeSlugRaw, 1, 40) ? $themeSlugRaw : '';

    $themesData = cb_load_themes();
    $themes = $themesData['themes'] ?? [];
    if ($themeSlug === '' || !isset($themes[$themeSlug]) || !is_array($themes[$themeSlug])) {
        return ['ok' => false, 'error' => 'not_found', 'code' => 404];
    }
    $themeData = $themes[$themeSlug];

    // frameBox: override de la fiesta tiene prioridad sobre el default de la temática.
    $frameBox = cb_normalize_frame_box($party['frameBox'] ?? null)
        ?? cb_normalize_frame_box($themeData['frameBox'] ?? null);

    $invitados = [];
    foreach (($party['invitados'] ?? []) as $inv) {
        if (!is_array($inv)) {
            continue;
        }
        $invitados[] = [
            'name' => (string) ($inv['name'] ?? ''),
            'g'    => (string) ($inv['g'] ?? ''),
        ];
    }

    $partyPayload = [
        'public_slug'      => $slug,
        'slug'             => $slug,
        'nombre'           => (string) ($party['nombre'] ?? ''),
        'event_type'       => (string) ($party['event_type'] ?? '') === 'baby_shower' ? 'baby_shower' : 'child_birthday',
        'invitados'        => $invitados,
        'frameBox'         => $frameBox,
        'musica'           => !isset($party['musica']) || !empty($party['musica']),
        'service_plan'     => (string) ($party['service_plan'] ?? 'booth'),
        'gallery_enabled'  => (bool) ($party['gallery_enabled'] ?? false),
    ];

    // Juegos habilitados para ESTA fiesta (los marca Luis en el admin según el
    // plan contratado. La misión 3D del Full se agrega por separado y nunca
    // depende de esta selección manual.
    $effectivePlan = $partyPayload['event_type'] === 'baby_shower'
        ? 'booth'
        : (string) ($partyPayload['service_plan'] ?? 'booth');
    $themePayload = cb_build_theme_payload(
        $themeSlug,
        $themeData,
        cb_sanitize_party_games($party['juegos'] ?? null),
        $effectivePlan
    );

    return ['ok' => true, 'party' => $partyPayload, 'theme' => $themePayload];
}

/**
 * Devuelve el registro CRUDO de una fiesta (incluye campos internos como
 * galeriaPin) o null si no existe. Uso interno (admin/, galeria.php) —
 * NUNCA exponer el resultado completo vía api.php.
 */
function cb_load_party_raw(string $publicSlug): ?array
{
    $partiesData = cb_load_parties();
    $parties = $partiesData['parties'];
    if (!isset($parties[$publicSlug]) || !is_array($parties[$publicSlug])) {
        return null;
    }
    return $parties[$publicSlug];
}

/** Resuelve el id interno de BD de una fiesta a partir de su public_slug. */
function cb_party_db_id(string $publicSlug): ?int
{
    if (!cb_valid_public_slug($publicSlug) || cb_storage_mode() !== 'db') {
        return null;
    }
    $pdo = cb_pdo();
    $stmt = $pdo->prepare('SELECT id FROM cc_parties WHERE public_slug = ?');
    $stmt->execute([$publicSlug]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** true si $pin son exactamente 4 dígitos (formato esperado de galeriaPin). */
function cb_valid_galeria_pin(string $pin): bool
{
    return preg_match('/^\d{4}$/', $pin) === 1;
}

/** Valida el rectángulo relativo usado por el compositor (0..1 y dentro del frame). */
function cb_normalize_frame_box($value): ?array
{
    if (!is_array($value)) {
        return null;
    }
    $box = [];
    foreach (['x', 'y', 'w', 'h'] as $key) {
        if (!isset($value[$key]) || !is_numeric($value[$key])) {
            return null;
        }
        $box[$key] = round((float) $value[$key], 4);
    }
    if ($box['x'] < 0 || $box['y'] < 0 || $box['w'] < 0.05 || $box['h'] < 0.05
        || $box['x'] + $box['w'] > 1 || $box['y'] + $box['h'] > 1) {
        return null;
    }
    return $box;
}

/** Verifica el PIN sin guardar ni comparar secretos nuevos en claro. */
function cb_verify_party_pin(array $party, string $pin): bool
{
    if (!cb_valid_galeria_pin($pin)) {
        return false;
    }
    $hash = (string) ($party['galeriaPinHash'] ?? '');
    if ($hash !== '') {
        return password_verify(cb_hmac($pin, 'gallery-pin'), $hash);
    }
    // Compatibilidad transitoria para parties.json antiguo; import-json lo elimina.
    $legacy = (string) ($party['galeriaPin'] ?? '');
    return $legacy !== '' && hash_equals($legacy, $pin);
}

function cb_state_path(string $name): string
{
    if (!preg_match('/^[a-z0-9_-]+\.json$/', $name)) {
        throw new InvalidArgumentException('Nombre de estado inválido.');
    }
    return cb_private_dir((string) cb_config('state_dir'), 'state_dir') . DIRECTORY_SEPARATOR . $name;
}

/** Mutación JSON bajo un único lock; evita perder contadores en concurrencia. */
function cb_mutate_json_state(string $path, callable $mutator)
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de estado.');
    }
    $fp = fopen($path, 'c+b');
    if (!$fp || !flock($fp, LOCK_EX)) {
        throw new RuntimeException('No se pudo bloquear el estado.');
    }
    rewind($fp);
    $raw = stream_get_contents($fp);
    $data = $raw ? json_decode($raw, true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    $result = $mutator($data);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        flock($fp, LOCK_UN);
        fclose($fp);
        throw new RuntimeException('No se pudo serializar el estado.');
    }
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    @chmod($path, 0660);
    return $result;
}

/** Rate limit persistente por ventana fija. No guarda IP/identidad en claro. */
function cb_rate_limit(string $scope, string $identity, int $limit, int $windowSeconds, int $blockSeconds): array
{
    $now = time();
    $bucket = cb_hmac($scope . '|' . $identity, 'rate-limit');
    if (cb_storage_mode() === 'json') {
        return cb_mutate_json_state(cb_state_path('rate_limits.json'), static function (&$all) use ($bucket, $now, $limit, $windowSeconds, $blockSeconds): array {
            $row = $all[$bucket] ?? ['window' => $now, 'hits' => 0, 'blocked' => 0, 'updated' => $now];
            if ((int) $row['blocked'] > $now) {
                return ['allowed' => false, 'retry_after' => (int) $row['blocked'] - $now, 'hits' => (int) $row['hits']];
            }
            if ($now - (int) $row['window'] >= $windowSeconds) {
                $row = ['window' => $now, 'hits' => 0, 'blocked' => 0, 'updated' => $now];
            }
            $row['hits']++;
            $row['updated'] = $now;
            if ($row['hits'] > $limit) {
                $row['blocked'] = $now + $blockSeconds;
            }
            $all[$bucket] = $row;
            foreach ($all as $key => $candidate) {
                if ($now - (int) ($candidate['updated'] ?? 0) > 86400 * 2) {
                    unset($all[$key]);
                }
            }
            return ['allowed' => $row['hits'] <= $limit, 'retry_after' => max(0, (int) $row['blocked'] - $now), 'hits' => (int) $row['hits']];
        });
    }

    $pdo = cb_pdo();
    $pdo->beginTransaction();
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        // Materializa el bucket antes de bloquearlo. Sin este paso, dos primeras
        // solicitudes simultáneas podrían leer "sin fila" y perder un intento.
        if ($driver === 'mysql') {
            $pdo->prepare('INSERT IGNORE INTO cc_rate_limits (bucket_key,window_started_at,hits,blocked_until,updated_at) VALUES (?,?,?,?,?)')
                ->execute([$bucket, $now, 0, 0, $now]);
        } else {
            $pdo->prepare('INSERT OR IGNORE INTO cc_rate_limits (bucket_key,window_started_at,hits,blocked_until,updated_at) VALUES (?,?,?,?,?)')
                ->execute([$bucket, $now, 0, 0, $now]);
        }
        $selectSql = 'SELECT * FROM cc_rate_limits WHERE bucket_key=?' . ($driver === 'mysql' ? ' FOR UPDATE' : '');
        $select = $pdo->prepare($selectSql);
        $select->execute([$bucket]);
        $row = $select->fetch();
        if (!$row) {
            $row = ['window_started_at' => $now, 'hits' => 0, 'blocked_until' => 0];
        }
        if ((int) $row['blocked_until'] <= $now && $now - (int) $row['window_started_at'] >= $windowSeconds) {
            $row = ['window_started_at' => $now, 'hits' => 0, 'blocked_until' => 0];
        }
        if ((int) $row['blocked_until'] <= $now) {
            $row['hits'] = (int) $row['hits'] + 1;
            if ((int) $row['hits'] > $limit) {
                $row['blocked_until'] = $now + $blockSeconds;
            }
        }
        if ($driver === 'mysql') {
            $sql = 'INSERT INTO cc_rate_limits (bucket_key,window_started_at,hits,blocked_until,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE window_started_at=VALUES(window_started_at),hits=VALUES(hits),blocked_until=VALUES(blocked_until),updated_at=VALUES(updated_at)';
        } else {
            $sql = 'INSERT INTO cc_rate_limits (bucket_key,window_started_at,hits,blocked_until,updated_at) VALUES (?,?,?,?,?) ON CONFLICT(bucket_key) DO UPDATE SET window_started_at=excluded.window_started_at,hits=excluded.hits,blocked_until=excluded.blocked_until,updated_at=excluded.updated_at';
        }
        $pdo->prepare($sql)->execute([$bucket, $row['window_started_at'], $row['hits'], $row['blocked_until'], $now]);
        $pdo->commit();
        return ['allowed' => (int) $row['hits'] <= $limit && (int) $row['blocked_until'] <= $now, 'retry_after' => max(0, (int) $row['blocked_until'] - $now), 'hits' => (int) $row['hits']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function cb_request_identity(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

function cb_photo_root(): string
{
    return cb_private_dir((string) cb_config('photo_dir'), 'photo_dir');
}

function cb_photo_absolute_path(string $storageKey): ?string
{
    // El prefijo debe aceptar exactamente el mismo rango que public_slug.
    // Los slugs opacos de fiesta pueden medir hasta 80 caracteres.
    if (!preg_match('#^[a-z0-9-]{1,80}/\d{4}/\d{2}/[a-f0-9]{32}\.png$#', $storageKey)) {
        return null;
    }
    return cb_photo_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
}

function cb_photo_usage(string $partySlug): array
{
    if (cb_storage_mode() === 'db') {
        $stmt = cb_pdo()->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(byte_size),0) AS bytes FROM cc_photos ph JOIN cc_parties p ON p.id=ph.party_id WHERE p.public_slug=? AND ph.deleted_at IS NULL');
        $stmt->execute([$partySlug]);
        $row = $stmt->fetch() ?: [];
        return ['count' => (int) ($row['total'] ?? 0), 'bytes' => (int) ($row['bytes'] ?? 0)];
    }
    $data = cb_load_json_file(cb_state_path('photos.json'));
    $count = 0;
    $bytes = 0;
    foreach (($data['photos'] ?? []) as $photo) {
        if (($photo['party'] ?? '') === $partySlug && empty($photo['deleted_at'])) {
            $count++;
            $bytes += (int) ($photo['byte_size'] ?? 0);
        }
    }
    return ['count' => $count, 'bytes' => $bytes];
}

/** Reserva cuota y registra metadata de forma atómica. */
function cb_record_photo_with_quota(string $partySlug, array $photo, int $maxCount = 200, int $maxBytes = 1073741824): string
{
    if (cb_storage_mode() === 'db') {
        $pdo = cb_pdo();
        $pdo->beginTransaction();
        try {
            $forUpdate = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $party = $pdo->prepare('SELECT id FROM cc_parties WHERE public_slug=?' . $forUpdate);
            $party->execute([$partySlug]);
            $partyId = $party->fetchColumn();
            if (!$partyId) { $pdo->rollBack(); return 'party_not_found'; }
            $usage = $pdo->prepare('SELECT COUNT(*) AS total,COALESCE(SUM(byte_size),0) AS bytes FROM cc_photos WHERE party_id=? AND deleted_at IS NULL');
            $usage->execute([(int) $partyId]);
            $row = $usage->fetch() ?: [];
            if ((int) ($row['total'] ?? 0) >= $maxCount || (int) ($row['bytes'] ?? 0) + (int) $photo['byte_size'] > $maxBytes) {
                $pdo->rollBack(); return 'quota';
            }
            $stmt = $pdo->prepare('INSERT INTO cc_photos (party_id,access_token,storage_key,original_name,byte_size,width,height,sha256,created_at) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([(int) $partyId, $photo['token'], $photo['storage_key'], $photo['original_name'], $photo['byte_size'], $photo['width'], $photo['height'], $photo['sha256'], $photo['created_at']]);
            $pdo->commit();
            return 'ok';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
    }
    return cb_mutate_json_state(cb_state_path('photos.json'), static function (&$data) use ($partySlug, $photo, $maxCount, $maxBytes): string {
        $data['photos'] = is_array($data['photos'] ?? null) ? $data['photos'] : [];
        $count = 0; $bytes = 0;
        foreach ($data['photos'] as $existing) {
            if (($existing['party'] ?? '') === $partySlug && empty($existing['deleted_at'])) {
                $count++; $bytes += (int) ($existing['byte_size'] ?? 0);
            }
        }
        if ($count >= $maxCount || $bytes + (int) $photo['byte_size'] > $maxBytes) { return 'quota'; }
        $photo['party'] = $partySlug;
        $data['photos'][$photo['token']] = $photo;
        return 'ok';
    });
}

function cb_record_photo(string $partySlug, array $photo): bool
{
    return cb_record_photo_with_quota($partySlug, $photo) === 'ok';
}

function cb_find_photo_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    if (cb_storage_mode() === 'db') {
        $stmt = cb_pdo()->prepare('SELECT ph.*, p.public_slug AS party_slug FROM cc_photos ph JOIN cc_parties p ON p.id=ph.party_id WHERE ph.access_token=? AND ph.deleted_at IS NULL');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    $data = cb_load_json_file(cb_state_path('photos.json'));
    $photo = $data['photos'][$token] ?? null;
    if (!is_array($photo) || !empty($photo['deleted_at'])) {
        return null;
    }
    $photo['party_slug'] = $photo['party'] ?? '';
    return $photo;
}

function cb_list_party_photos(string $partySlug): array
{
    if (cb_storage_mode() === 'db') {
        $stmt = cb_pdo()->prepare('SELECT ph.* FROM cc_photos ph JOIN cc_parties p ON p.id=ph.party_id WHERE p.public_slug=? AND ph.deleted_at IS NULL ORDER BY ph.created_at DESC');
        $stmt->execute([$partySlug]);
        return $stmt->fetchAll();
    }
    $data = cb_load_json_file(cb_state_path('photos.json'));
    $photos = [];
    foreach (($data['photos'] ?? []) as $photo) {
        if (is_array($photo) && ($photo['party'] ?? '') === $partySlug && empty($photo['deleted_at'])) {
            $photos[] = $photo;
        }
    }
    usort($photos, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    return $photos;
}

/** Nombres de archivo requeridos dentro de themes/<slug>/ (8 jpg + 1 mp3). */
function cb_theme_required_files(array $themeData): array
{
    $files = ['fondo-banner.jpg', 'fondo-sala.jpg'];
    foreach (($themeData['personajes'] ?? []) as $p) {
        if (!empty($p['img'])) {
            $files[] = (string) $p['img'];
        }
    }
    $files[] = 'musica-fondo.mp3';
    return $files;
}

/**
 * Estado de archivos de una temática en disco: requeridos, faltantes y si está lista.
 * $themesBaseDir = ruta absoluta a la carpeta themes/ (ej. __DIR__.'/themes').
 */
function cb_theme_files_status(string $themeSlug, array $themeData, string $themesBaseDir): array
{
    $required = cb_theme_required_files($themeData);
    $missing = [];
    foreach ($required as $file) {
        $path = $themesBaseDir . '/' . $themeSlug . '/' . $file;
        if (!is_file($path)) {
            $missing[] = $file;
        }
    }
    return [
        'required' => $required,
        'missing'  => $missing,
        'ready'    => empty($missing),
    ];
}

// ================== SUBIDA DE ARCHIVOS POR TEMÁTICA (backoffice) ==================

/** Tamaño máximo aceptado para audio/video (bytes). */
function cb_theme_upload_max_bytes(): int
{
    return 80 * 1024 * 1024; // 80MB
}

/** Tamaño máximo aceptado para imágenes (bytes) — generoso, se re-comprime igual. */
function cb_theme_image_max_bytes(): int
{
    return 20 * 1024 * 1024; // 20MB
}

/**
 * Lista de "slots" de archivo permitidos para una temática:
 * los 9 requeridos (2 fondos + 6 personajes + música) y hasta 6 videos de saludo
 * OPCIONALES nombrados "saludo-<base-del-img>.mp4".
 * Cada slot: ['name'=>string, 'kind'=>'image'|'audio'|'video', 'required'=>bool].
 */
function cb_theme_upload_slots(array $themeData): array
{
    $slots = [];
    $add = static function (
        string $name,
        string $kind,
        bool $required,
        string $group,
        string $label,
        array $extra = []
    ) use (&$slots): void {
        if (!cb_theme_relative_asset_safe($name) || isset($slots[$name])) {
            return;
        }
        $slots[$name] = array_merge([
            'name' => $name,
            'kind' => $kind,
            'required' => $required,
            'group' => $group,
            'label' => $label,
            'promptable' => in_array($kind, ['image', 'png', 'video'], true),
        ], $extra);
    };

    $add('fondo-banner.jpg', 'image', true, 'fondos', 'Portada del kiosco', ['width' => 1080, 'height' => 1920]);
    $add('fondo-sala.jpg', 'image', true, 'fondos', 'Sala para la foto', ['width' => 1080, 'height' => 1920]);
    foreach (($themeData['personajes'] ?? []) as $p) {
        $img = (string) ($p['img'] ?? '');
        if ($img === '') {
            continue;
        }
        $characterName = trim((string) ($p['name'] ?? 'Personaje'));
        $add($img, 'image', true, 'personajes', 'Retrato · ' . $characterName, ['width' => 1080, 'height' => 1920]);
        $base = pathinfo($img, PATHINFO_FILENAME);
        $add('saludo-' . $base . '.mp4', 'video', false, 'personajes', 'Saludo · ' . $characterName, ['max_duration' => 10.0]);
        // Recorte transparente OPCIONAL (Fase 1): "<base>-cut.png", se guarda SIN
        // recomprimir para conservar el canal alfa (ver cb_process_theme_png).
        $add(
            $base . '-cut.png',
            'png',
            false,
            'personajes',
            'Recorte transparente · ' . $characterName,
            ['requires_alpha' => true]
        );
        $add('invitacion-juego-' . $base . '.mp3', 'audio', false, 'audio', 'Narración del juego · ' . $characterName, ['promptable' => false]);

        foreach ((array) ($p['game'] ?? []) as $gameKey => $rawGame) {
            // Un juego único es un objeto asociativo; una cadena es una lista.
            if (!is_array($rawGame) && is_string($gameKey)) {
                $rawGame = $p['game'];
            }
            if (!is_array($rawGame)) {
                continue;
            }
            $gameImage = trim((string) ($rawGame['image'] ?? ''));
            if ($gameImage !== '') {
                $isPuzzle = strpos($gameImage, 'puzzle-') === 0;
                $add(
                    $gameImage,
                    'image',
                    false,
                    'juegos',
                    ($isPuzzle ? 'Recorte de puzzle' : 'Fondo de juego') . ' · ' . $characterName,
                    [
                        'width' => $isPuzzle ? 900 : 1080,
                        'height' => $isPuzzle ? 900 : 1920,
                        // Los puzzles se derivan del retrato aprobado; no se generan.
                        'promptable' => !$isPuzzle,
                        'derived' => $isPuzzle,
                    ]
                );
            }
            // Evita volver a procesar el mismo objeto asociativo seis veces.
            if (is_string($gameKey)) {
                break;
            }
        }
    }
    // Assets del "pase de artista": solo existen si la temática declara
    // photoSession. Se listan para que su prompt también quede versionado en el
    // admin, igual que el resto del arte generado.
    $session = $themeData['photoSession'] ?? null;
    if (is_array($session)) {
        foreach ([
            ['key' => 'poster', 'kind' => 'png'],
            ['key' => 'teaser', 'kind' => 'image'],
            ['key' => 'video', 'kind' => 'video'],
            ['key' => 'teaserVideo', 'kind' => 'video'],
        ] as $entry) {
            $name = (string) ($session[$entry['key']] ?? '');
            if ($name === '') {
                continue;
            }
            $labels = [
                'poster' => 'Poster de experiencia inmersiva',
                'teaser' => 'Adelanto de artistas',
                'video' => 'Experiencia inmersiva',
                'teaserVideo' => 'Adelanto animado',
            ];
            $assetKind = $entry['kind'];
            if ($assetKind === 'png' && strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'png') {
                $assetKind = 'image';
            }
            $add(
                $name,
                $assetKind,
                false,
                'marketing',
                $labels[$entry['key']],
                $assetKind === 'video'
                    ? ['max_duration' => 15.0]
                    : ['width' => 1080, 'height' => 1920]
            );
        }
    }
    foreach (['welcome' => 'Bienvenida', 'revelacion' => 'Revelación', 'despedida' => 'Despedida'] as $key => $label) {
        $videoName = trim((string) ($themeData['videos'][$key] ?? ''));
        if ($videoName !== '') {
            $add($videoName, 'video', false, 'marketing', $label . ' de la temática', ['max_duration' => 15.0]);
        }
    }

    $visualSource = trim((string) ($themeData['visualSource']['asset'] ?? ''));
    if ($visualSource !== '') {
        $add($visualSource, 'png', false, 'marketing', 'Invitación visual base');
    }
    $add('grupo-personajes.png', 'png', false, 'marketing', 'Grupo de personajes');
    $add('roulette/roulette-background-v1.png', 'png', false, 'marketing', 'Fondo de ruleta');
    // Piezas independientes para campañas manuales. No entran al payload del
    // kiosco: son material de Marketing administrado y versionado junto al tema.
    $add('marketing/marketing-poster.jpg', 'image', false, 'marketing', 'Poster de Marketing', ['width' => 1080, 'height' => 1920]);
    $add('marketing/marketing-promo.mp4', 'video', false, 'marketing', 'Video promocional', ['max_duration' => 15.0]);
    $add('musica-fondo.mp3', 'audio', true, 'audio', 'Música del kiosco', ['promptable' => false]);
    $add('musica-juego.mp3', 'audio', false, 'audio', 'Música de los juegos', ['promptable' => false]);
    return array_values($slots);
}

/** Ruta relativa segura dentro de public/themes/<slug>/; admite subcarpetas. */
function cb_theme_relative_asset_safe(string $name): bool
{
    if ($name === '' || strlen($name) > 160 || strpos($name, '\\') !== false || strpos($name, '..') !== false) {
        return false;
    }
    return preg_match('#\A[a-z0-9][a-z0-9._/-]*\.(?:jpe?g|png|webp|mp3|mp4)\z#i', $name) === 1
        && $name[0] !== '/'
        && substr($name, -1) !== '/';
}

/** Busca el slot permitido cuyo nombre EXACTO coincide con $filename, o null. */
function cb_theme_upload_slot_by_name(array $themeData, string $filename): ?array
{
    foreach (cb_theme_upload_slots($themeData) as $slot) {
        if ($slot['name'] === $filename) {
            return $slot;
        }
    }
    return null;
}

/** Solo los assets visuales de la whitelist pueden tener prompt generativo. */
function cb_theme_prompt_asset_allowed(array $themeData, string $assetKey): bool
{
    if (!cb_theme_relative_asset_safe($assetKey)) {
        return false;
    }
    $slot = cb_theme_upload_slot_by_name($themeData, $assetKey);
    return $slot !== null && !empty($slot['promptable']);
}

/** Nombres internos que nunca deben aparecer dentro de un prompt para Gemini. */
function cb_theme_prompt_forbidden_terms(array $themeData): array
{
    $terms = [];
    $franchise = trim((string) ($themeData['franquicia'] ?? ''));
    if ($franchise !== '') {
        foreach (preg_split('#\s*(?:/|,|\||·)\s*#u', $franchise) ?: [] as $part) {
            if (strlen(trim($part)) >= 3) {
                $terms[] = trim($part);
            }
        }
    }
    // En temáticas originales los nombres pueden ser especies genéricas (T-Rex,
    // triceratops, etc.) y describen justamente los rasgos físicos permitidos.
    if ($franchise !== '') {
        foreach (($themeData['personajes'] ?? []) as $character) {
            $name = trim((string) ($character['name'] ?? ''));
            if (strlen($name) >= 3) {
                $terms[] = $name;
            }
        }
    }
    return array_values(array_unique($terms));
}

/** Valida longitud y camuflaje antes de escribir un prompt en la BD. */
function cb_validate_theme_prompt(array $themeData, string $assetKey, string $promptText): array
{
    if (!cb_theme_prompt_asset_allowed($themeData, $assetKey)) {
        return ['ok' => false, 'error' => 'El asset no pertenece a la lista visual permitida de esta temática.'];
    }
    $promptText = trim($promptText);
    if (strlen($promptText) > 20000) {
        return ['ok' => false, 'error' => 'El prompt supera el máximo de 20.000 caracteres.'];
    }
    $camouflageDisclaimer = 'This is an original toy design, not based on any existing character.';
    if ($promptText !== '' && strpos($promptText, $camouflageDisclaimer) === false) {
        return ['ok' => false, 'error' => 'Falta el cierre obligatorio de camuflaje para generación multimedia.'];
    }
    foreach (cb_theme_prompt_forbidden_terms($themeData) as $term) {
        $pattern = '/(?<![\pL\pN])' . preg_quote($term, '/') . '(?![\pL\pN])/iu';
        if (preg_match($pattern, $promptText) === 1) {
            return ['ok' => false, 'error' => 'El prompt contiene un nombre reservado de franquicia o personaje. Describe únicamente rasgos físicos.'];
        }
    }
    return ['ok' => true, 'prompt' => $promptText];
}

/**
 * Los prompts de temática viven SIEMPRE en `cc_theme_prompts`, aunque las fiestas
 * se guarden en JSON: son dos almacenamientos independientes. Antes estas
 * funciones exigían storage_mode=db, así que al operar en modo json los prompts
 * quedaban invisibles en el admin y no se podían guardar, pese a estar cargados
 * en la BD (2026-07-25: 100 prompts inaccesibles por esto). Ahora el único
 * requisito real es que haya una BD utilizable configurada.
 *
 * @return PDO|null null si no hay BD configurada o si la conexión falla.
 */
function cb_theme_prompts_pdo(): ?PDO
{
    static $resolved = false;
    static $pdo = null;
    if ($resolved) {
        return $pdo;
    }
    $resolved = true;
    if (trim((string) cb_config('pdo_dsn')) === '') {
        return $pdo = null;
    }
    try {
        $pdo = cb_pdo();
    } catch (Throwable $e) {
        error_log('CumpleClick prompts de temática sin BD disponible: ' . $e->getMessage());
        $pdo = null;
    }
    return $pdo;
}

/** Carga únicamente los prompts privados de una temática; nunca se usa en api.php. */
function cb_load_theme_prompts(string $themeSlug, array $themeData): array
{
    $pdo = cb_theme_prompts_pdo();
    if ($pdo === null || !cb_valid_slug($themeSlug, 1, 40)) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT asset_key, prompt_text, updated_at FROM cc_theme_prompts WHERE theme_slug = ? ORDER BY asset_key');
    $stmt->execute([$themeSlug]);
    $prompts = [];
    foreach ($stmt->fetchAll() as $row) {
        $assetKey = (string) ($row['asset_key'] ?? '');
        if (!cb_theme_prompt_asset_allowed($themeData, $assetKey)) {
            continue;
        }
        $prompts[$assetKey] = [
            'prompt' => (string) ($row['prompt_text'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
    return $prompts;
}

/** Upsert preparado; un texto vacío elimina explícitamente la asociación. */
function cb_save_theme_prompt(string $themeSlug, array $themeData, string $assetKey, string $promptText): array
{
    $pdo = cb_theme_prompts_pdo();
    if ($pdo === null || !cb_valid_slug($themeSlug, 1, 40)) {
        return ['ok' => false, 'error' => 'Los prompts editables requieren una base de datos configurada.'];
    }
    $validation = cb_validate_theme_prompt($themeData, $assetKey, $promptText);
    if (!$validation['ok']) {
        return $validation;
    }
    $promptText = (string) $validation['prompt'];
    $now = gmdate('Y-m-d H:i:s');

    // Historial append-only: cada guardado (o borrado) queda como fila nueva,
    // nunca se sobreescribe. cc_theme_prompts sigue siendo solo "la versión
    // actual". Si la tabla de historial no existe aún (BD sin migrar a la
    // 005), no bloquea el guardado normal — solo se pierde ese registro.
    $logHistory = static function (string $action) use ($pdo, $themeSlug, $assetKey, $promptText, $now): void {
        try {
            $pdo->prepare('INSERT INTO cc_theme_prompt_history (theme_slug,asset_key,prompt_text,action,created_by,created_at) VALUES (?,?,?,?,?,?)')
                ->execute([$themeSlug, $assetKey, $promptText, $action, 'admin', $now]);
        } catch (Throwable $e) {
            error_log('CumpleClick theme prompt history: ' . $e->getMessage());
        }
    };

    if ($promptText === '') {
        $stmt = $pdo->prepare('DELETE FROM cc_theme_prompts WHERE theme_slug = ? AND asset_key = ?');
        $stmt->execute([$themeSlug, $assetKey]);
        $logHistory('delete');
        return ['ok' => true, 'deleted' => true];
    }
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql = 'INSERT INTO cc_theme_prompts (theme_slug,asset_key,prompt_text,created_at,updated_at) VALUES (?,?,?,?,?) '
            . 'ON DUPLICATE KEY UPDATE prompt_text=VALUES(prompt_text), updated_at=VALUES(updated_at)';
    } else {
        $sql = 'INSERT INTO cc_theme_prompts (theme_slug,asset_key,prompt_text,created_at,updated_at) VALUES (?,?,?,?,?) '
            . 'ON CONFLICT(theme_slug,asset_key) DO UPDATE SET prompt_text=excluded.prompt_text, updated_at=excluded.updated_at';
    }
    $pdo->prepare($sql)->execute([$themeSlug, $assetKey, $promptText, $now, $now]);
    $logHistory('save');
    return ['ok' => true, 'deleted' => false];
}

/** Historial de versiones de un prompt (más reciente primero), para mostrar
 * en el admin. Nunca se usa en api.php — dato privado, igual que el prompt. */
function cb_load_theme_prompt_history(string $themeSlug, string $assetKey, int $limit = 20): array
{
    $pdo = cb_theme_prompts_pdo();
    if ($pdo === null || !cb_valid_slug($themeSlug, 1, 40) || $assetKey === '') {
        return [];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT prompt_text, action, created_by, created_at FROM cc_theme_prompt_history '
            . 'WHERE theme_slug = ? AND asset_key = ? ORDER BY id DESC LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([$themeSlug, $assetKey]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('CumpleClick load theme prompt history: ' . $e->getMessage());
        return [];
    }
}

/** Inventario seguro: todas las rutas provienen del slug y la whitelist versionada. */
function cb_theme_asset_inventory(string $themeSlug, array $themeData, string $themesBaseDir): array
{
    if (!cb_valid_slug($themeSlug, 1, 40)) {
        return [];
    }
    $inventory = [];
    foreach (cb_theme_upload_slots($themeData) as $slot) {
        $name = (string) $slot['name'];
        $path = rtrim($themesBaseDir, '/\\') . DIRECTORY_SEPARATOR . $themeSlug . DIRECTORY_SEPARATOR . $name;
        $exists = is_file($path);
        $width = null;
        $height = null;
        if ($exists && in_array($slot['kind'], ['image', 'png'], true)) {
            $imageInfo = @getimagesize($path);
            if (is_array($imageInfo)) {
                $width = (int) ($imageInfo[0] ?? 0);
                $height = (int) ($imageInfo[1] ?? 0);
            }
        }
        $inventory[] = $slot + [
            'exists' => $exists,
            'bytes' => $exists ? (int) filesize($path) : 0,
            'width' => $width,
            'height' => $height,
            'modified_at' => $exists ? gmdate('Y-m-d H:i:s', (int) filemtime($path)) : '',
            'preview_url' => '../themes/' . rawurlencode($themeSlug) . '/' . implode('/', array_map('rawurlencode', explode('/', $name))),
        ];
    }
    return $inventory;
}

/**
 * Extrae los prompts históricos del Markdown privado. Devuelve solo asociaciones
 * que pasan la misma whitelist y validación de camuflaje usada por el admin.
 */
function cb_parse_theme_prompts_markdown(string $markdown, array $themes): array
{
    $prompts = [];
    $issues = [];
    $themeSlug = '';
    $assetKey = '';
    $body = [];
    $flush = static function () use (&$prompts, &$issues, &$themeSlug, &$assetKey, &$body, $themes): void {
        if ($themeSlug === '' || $assetKey === '' || !isset($themes[$themeSlug]) || !is_array($themes[$themeSlug])) {
            $assetKey = '';
            $body = [];
            return;
        }
        $raw = trim(implode("\n", $body));
        if (preg_match('/```[^\n]*\n(.*?)```/su', $raw, $match) === 1) {
            $raw = trim((string) $match[1]);
        } else {
            $raw = trim(preg_replace('/^---\s*$/mu', '', $raw) ?? $raw);
        }
        if ($raw !== '') {
            $disclaimer = 'This is an original toy design, not based on any existing character.';
            if (strpos($raw, $disclaimer) === false) {
                $raw .= "\n\n" . $disclaimer;
            }
            $validation = cb_validate_theme_prompt($themes[$themeSlug], $assetKey, $raw);
            if ($validation['ok']) {
                $prompts[$themeSlug][$assetKey] = (string) $validation['prompt'];
            } else {
                $issues[] = $themeSlug . '/' . $assetKey . ': ' . $validation['error'];
            }
        }
        $assetKey = '';
        $body = [];
    };

    foreach (preg_split('/\r\n|\r|\n/', $markdown) ?: [] as $line) {
        if (preg_match('~^#\s+.*`themes/([a-z0-9-]+)/`~u', $line, $match) === 1) {
            $flush();
            $themeSlug = isset($themes[$match[1]]) ? (string) $match[1] : '';
            continue;
        }
        if (preg_match('/^#{2,3}\s+([a-z0-9-]+\.(?:jpe?g|png))(?:\s|$)/iu', $line, $match) === 1) {
            $flush();
            $assetKey = strtolower((string) $match[1]);
            continue;
        }
        if (preg_match('/^#{1,3}\s+/', $line) === 1) {
            $flush();
            continue;
        }
        if ($assetKey !== '') {
            $body[] = $line;
        }
    }
    $flush();
    return ['prompts' => $prompts, 'issues' => $issues];
}

/**
 * Sniff tolerante de MP3: firma ID3 al inicio, o frame-sync MPEG (0xFFEx) en
 * los primeros bytes. No es un parser completo — solo descarta basura obvia.
 */
function cb_sniff_mp3(string $path): bool
{
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return false;
    }
    $head = fread($fh, 8192);
    fclose($fh);
    if ($head === false || $head === '') {
        return false;
    }
    if (substr($head, 0, 3) === 'ID3') {
        return true;
    }
    $len = strlen($head);
    for ($i = 0; $i < $len - 1; $i++) {
        $b0 = ord($head[$i]);
        $b1 = ord($head[$i + 1]);
        if ($b0 === 0xFF && ($b1 & 0xE0) === 0xE0) {
            return true;
        }
    }
    return false;
}

/**
 * Sniff tolerante de MP4: caja "ftyp" cerca del inicio (estándar en MP4/MOV).
 * Tolera algo de offset por si hay cajas raras antes.
 */
function cb_sniff_mp4(string $path): bool
{
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return false;
    }
    $head = fread($fh, 64);
    fclose($fh);
    if ($head === false || strlen($head) < 8) {
        return false;
    }
    return strpos($head, 'ftyp') !== false
        || strpos($head, 'moov') !== false
        || strpos($head, 'mdat') !== false;
}

/** Duración máxima aceptada para video de invitación (segundos). Objetivo del
 * contrato visual es 5-7s; se deja algo de margen para variación de encoder. */
function cb_theme_video_max_duration_seconds(): float
{
    return 10.0;
}

/**
 * Inspecciona un MP4 con ffprobe (metadata real, no solo la firma de bytes de
 * cb_sniff_mp4): duración, presencia de stream de video y códec. Usa
 * proc_open con argv explícito (sin shell) para no depender de escaping.
 * Devuelve null si el binario no está configurado, no existe, la ejecución
 * falla o el JSON no se puede interpretar — el llamador debe tratar null como
 * "no se pudo inspeccionar" y rechazar el archivo (fail closed), nunca aceptarlo.
 */
function cb_inspect_video(string $path): ?array
{
    $ffprobe = (string) cb_config('ffprobe_path');
    if ($ffprobe === '' || !is_file($ffprobe)) {
        return null;
    }
    $cmd = [$ffprobe, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path];
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = @proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        return null;
    }
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || $stdout === false || $stdout === '') {
        return null;
    }
    $data = json_decode($stdout, true);
    if (!is_array($data)) {
        return null;
    }
    $duration = isset($data['format']['duration']) ? (float) $data['format']['duration'] : null;
    if ($duration === null || $duration <= 0) {
        return null;
    }
    $videoStream = null;
    $hasAudio = false;
    foreach ((array) ($data['streams'] ?? []) as $stream) {
        $codecType = (string) ($stream['codec_type'] ?? '');
        if ($codecType === 'video' && $videoStream === null) {
            $videoStream = $stream;
        } elseif ($codecType === 'audio') {
            $hasAudio = true;
        }
    }
    if ($videoStream === null) {
        return null;
    }
    return [
        'duration' => $duration,
        'codec' => (string) ($videoStream['codec_name'] ?? ''),
        'width' => (int) ($videoStream['width'] ?? 0),
        'height' => (int) ($videoStream['height'] ?? 0),
        'has_audio' => $hasAudio,
    ];
}

/**
 * Procesa una imagen subida: valida que sea imagen real (getimagesize), y si GD
 * está disponible la redimensiona en modo cover-crop a 1080x1920 guardando JPEG
 * calidad 87 (también convierte PNG renombrado a .jpg). Sin GD, copia tal cual
 * si es una imagen válida.
 */
function cb_process_theme_image(string $tmpPath, string $dstPath, int $targetW = 1080, int $targetH = 1920): array
{
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        return ['ok' => false, 'error' => 'no es una imagen válida'];
    }
    $mime = $info['mime'] ?? '';
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        return ['ok' => false, 'error' => 'formato de imagen no soportado (usa JPG o PNG)'];
    }
    if ((int) ($info[0] ?? 0) < 1 || (int) ($info[1] ?? 0) < 1) {
        return ['ok' => false, 'error' => 'imagen con dimensiones inválidas'];
    }
    if ((int) $info[0] > 4096 || (int) $info[1] > 4096) {
        return ['ok' => false, 'error' => 'la imagen supera las dimensiones máximas de 4096×4096'];
    }

    if (!extension_loaded('gd')) {
        if (!@copy($tmpPath, $dstPath)) {
            return ['ok' => false, 'error' => 'no se pudo guardar el archivo'];
        }
        @chmod($dstPath, 0664);
        return ['ok' => true];
    }

    $src = null;
    if ($mime === 'image/jpeg') {
        $src = @imagecreatefromjpeg($tmpPath);
    } elseif ($mime === 'image/png') {
        $src = @imagecreatefrompng($tmpPath);
    }
    if (!$src) {
        return ['ok' => false, 'error' => 'no se pudo procesar la imagen (archivo corrupto)'];
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    if ($srcW < 1 || $srcH < 1) {
        imagedestroy($src);
        return ['ok' => false, 'error' => 'imagen con dimensiones inválidas'];
    }

    $targetW = max(320, min(2160, $targetW));
    $targetH = max(320, min(2160, $targetH));
    $targetRatio = $targetW / $targetH;
    $srcRatio = $srcW / $srcH;

    if ($srcRatio > $targetRatio) {
        // La fuente es más "ancha" que el objetivo: recorta a los lados.
        $cropH = $srcH;
        $cropW = (int) round($srcH * $targetRatio);
        $cropX = (int) round(($srcW - $cropW) / 2);
        $cropY = 0;
    } else {
        // La fuente es más "alta": recorta arriba/abajo.
        $cropW = $srcW;
        $cropH = (int) round($srcW / $targetRatio);
        $cropX = 0;
        $cropY = (int) round(($srcH - $cropH) / 2);
    }

    $dst = imagecreatetruecolor($targetW, $targetH);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $targetW, $targetH, max(1, $cropW), max(1, $cropH));
    imagedestroy($src);

    $ok = imagejpeg($dst, $dstPath, 87);
    imagedestroy($dst);
    if (!$ok) {
        return ['ok' => false, 'error' => 'no se pudo guardar la imagen procesada'];
    }
    @chmod($dstPath, 0664);
    return ['ok' => true];
}

/**
 * Procesa un PNG "-cut.png" (recorte transparente de personaje, Fase 1):
 * valida que sea un PNG real (getimagesize + IMAGETYPE_PNG) y lo guarda TAL CUAL,
 * sin pasar por el cover-crop JPEG, para conservar el canal alfa.
 */
function cb_process_theme_png(string $tmpPath, string $dstPath, bool $requiresAlpha = false): array
{
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        return ['ok' => false, 'error' => 'no es una imagen válida'];
    }
    if (($info[2] ?? null) !== IMAGETYPE_PNG) {
        return ['ok' => false, 'error' => 'debe ser un PNG real (con transparencia)'];
    }
    if ((int) ($info[0] ?? 0) < 1 || (int) ($info[1] ?? 0) < 1) {
        return ['ok' => false, 'error' => 'imagen con dimensiones inválidas'];
    }
    if ((int) $info[0] > 4096 || (int) $info[1] > 4096) {
        return ['ok' => false, 'error' => 'la imagen supera las dimensiones máximas de 4096×4096'];
    }
    if ($requiresAlpha) {
        // IHDR byte 25: tipos 4/6 incluyen alfa; los PNG indexados declaran
        // transparencia mediante el chunk tRNS. Así se rechaza un JPG/PNG
        // opaco usado por error como recorte de personaje.
        $head = @file_get_contents($tmpPath, false, null, 0, 262144);
        $colorType = is_string($head) && strlen($head) > 25 ? ord($head[25]) : -1;
        $hasAlpha = in_array($colorType, [4, 6], true)
            || (is_string($head) && strpos($head, 'tRNS') !== false);
        if (!$hasAlpha) {
            return ['ok' => false, 'error' => 'el recorte debe incluir un canal alfa transparente real'];
        }
    }
    if (!@move_uploaded_file($tmpPath, $dstPath) && !@copy($tmpPath, $dstPath)) {
        return ['ok' => false, 'error' => 'no se pudo guardar el archivo (permisos)'];
    }
    @chmod($dstPath, 0664);
    return ['ok' => true];
}

/**
 * Procesa un lote de archivos subidos ($_FILES['campo'] con name="archivos[]" multiple)
 * para una temática. Solo acepta archivos cuyo NOMBRE EXACTO esté en la lista de slots
 * permitidos de esa temática (guardarraíl deliberado de naming). Guarda/sobrescribe en
 * $themesBaseDir/$themeSlug/. Devuelve ['saved'=>[nombres...], 'rejected'=>[['name'=>,'reason'=>], ...]].
 */
function cb_process_theme_uploads(
    string $themeSlug,
    array $themeData,
    string $themesBaseDir,
    array $files,
    string $requestedAssetKey = ''
): array
{
    $saved = [];
    $rejected = [];

    $names = $files['name'] ?? null;
    if (is_string($names)) {
        foreach (['name', 'tmp_name', 'error', 'size', 'type'] as $field) {
            $files[$field] = [$files[$field] ?? null];
        }
        $names = $files['name'];
    }
    if (!is_array($names)) {
        return ['saved' => $saved, 'rejected' => $rejected];
    }

    $destDir = $themesBaseDir . '/' . $themeSlug;
    if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
        return ['saved' => [], 'rejected' => [['name' => '(carpeta)', 'reason' => 'no se pudo crear la carpeta de destino, revisa permisos']]];
    }

    $validNamesList = implode(', ', array_column(cb_theme_upload_slots($themeData), 'name'));
    $count = count($names);
    for ($i = 0; $i < $count; $i++) {
        $origName = basename((string) $names[$i]);
        if ($origName === '' || $origName === '.' || $origName === '..') {
            continue; // input vacío (usuario no seleccionó archivo en ese slot)
        }
        $tmpName = (string) ($files['tmp_name'][$i] ?? '');
        $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($files['size'][$i] ?? 0);

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $rejected[] = ['name' => $origName, 'reason' => 'error de subida (código ' . $error . ', probablemente supera el límite del servidor)'];
            continue;
        }
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $rejected[] = ['name' => $origName, 'reason' => 'archivo inválido'];
            continue;
        }

        $slot = $requestedAssetKey !== ''
            ? cb_theme_upload_slot_by_name($themeData, $requestedAssetKey)
            : cb_theme_upload_slot_by_name($themeData, $origName);
        if ($slot === null) {
            $rejected[] = ['name' => $origName, 'reason' => 'destino no permitido. Nombres válidos: ' . $validNamesList];
            continue;
        }

        $dstPath = $destDir . '/' . $slot['name'];
        $dstParent = dirname($dstPath);
        if (!is_dir($dstParent) && !mkdir($dstParent, 0775, true) && !is_dir($dstParent)) {
            $rejected[] = ['name' => $origName, 'reason' => 'no se pudo crear la subcarpeta de destino'];
            continue;
        }

        if ($slot['kind'] === 'image') {
            if ($size <= 0 || $size > cb_theme_image_max_bytes()) {
                $rejected[] = ['name' => $origName, 'reason' => 'la imagen supera el tamaño máximo permitido'];
                continue;
            }
            $res = cb_process_theme_image(
                $tmpName,
                $dstPath,
                (int) ($slot['width'] ?? 1080),
                (int) ($slot['height'] ?? 1920)
            );
            if (!$res['ok']) {
                $rejected[] = ['name' => $origName, 'reason' => $res['error']];
                continue;
            }
            $saved[] = $slot['name'];
        } elseif ($slot['kind'] === 'audio') {
            if ($size <= 0 || $size > cb_theme_upload_max_bytes()) {
                $rejected[] = ['name' => $origName, 'reason' => 'el audio supera el tamaño máximo permitido (80MB)'];
                continue;
            }
            if (!cb_sniff_mp3($tmpName)) {
                $rejected[] = ['name' => $origName, 'reason' => 'no parece un archivo MP3 válido'];
                continue;
            }
            if (!@move_uploaded_file($tmpName, $dstPath)) {
                $rejected[] = ['name' => $origName, 'reason' => 'no se pudo guardar el archivo (permisos)'];
                continue;
            }
            @chmod($dstPath, 0664);
            $saved[] = $slot['name'];
        } elseif ($slot['kind'] === 'png') {
            if ($size <= 0 || $size > cb_theme_image_max_bytes()) {
                $rejected[] = ['name' => $origName, 'reason' => 'la imagen supera el tamaño máximo permitido'];
                continue;
            }
            $res = cb_process_theme_png(
                $tmpName,
                $dstPath,
                (bool) ($slot['requires_alpha'] ?? false)
            );
            if (!$res['ok']) {
                $rejected[] = ['name' => $origName, 'reason' => $res['error']];
                continue;
            }
            $saved[] = $slot['name'];
        } elseif ($slot['kind'] === 'video') {
            if ($size <= 0 || $size > cb_theme_upload_max_bytes()) {
                $rejected[] = ['name' => $origName, 'reason' => 'el video supera el tamaño máximo permitido (80MB)'];
                continue;
            }
            if (!cb_sniff_mp4($tmpName)) {
                $rejected[] = ['name' => $origName, 'reason' => 'no parece un archivo MP4 válido'];
                continue;
            }
            $videoInfo = cb_inspect_video($tmpName);
            if ($videoInfo === null) {
                // Sin ffprobe no se puede verificar codec/duración/dimensiones.
                // El escape de config permite igual la subida (ver el comentario
                // de 'allow_video_upload_without_ffprobe'); si está apagado, se
                // rechaza explicando la causa real y cómo resolverla, en vez de
                // dejar a quien sube adivinando por qué falla siempre.
                if (!cb_config('allow_video_upload_without_ffprobe')) {
                    $ruta = (string) cb_config('ffprobe_path');
                    $rejected[] = ['name' => $origName, 'reason' => $ruta === ''
                        ? 'este servidor no tiene ffprobe configurado, así que no se puede validar el video. Configura ffprobe_path, o activa allow_video_upload_without_ffprobe si aceptas subir sin validar codec ni duración.'
                        : 'no se pudo ejecutar ffprobe en ' . $ruta . '; revisa la ruta y los permisos.'];
                    continue;
                }
                if (!@move_uploaded_file($tmpName, $dstPath)) {
                    $rejected[] = ['name' => $origName, 'reason' => 'no se pudo guardar el archivo (permisos)'];
                    continue;
                }
                @chmod($dstPath, 0664);
                $saved[] = $slot['name'];
                continue;
            }
            if (($videoInfo['codec'] ?? '') !== 'h264') {
                $rejected[] = ['name' => $origName, 'reason' => 'el video debe usar codec H.264'];
                continue;
            }
            $maxDuration = (float) ($slot['max_duration'] ?? cb_theme_video_max_duration_seconds());
            if ((float) ($videoInfo['duration'] ?? 0) > $maxDuration + 0.2) {
                $maxLabel = rtrim(rtrim(number_format($maxDuration, 1, '.', ''), '0'), '.');
                $rejected[] = ['name' => $origName, 'reason' => 'el video supera ' . $maxLabel . ' segundos'];
                continue;
            }
            $videoW = (int) ($videoInfo['width'] ?? 0);
            $videoH = (int) ($videoInfo['height'] ?? 0);
            if ($videoW < 360 || $videoH < 640 || $videoW > 2160 || $videoH > 3840) {
                $rejected[] = ['name' => $origName, 'reason' => 'dimensiones de video fuera del rango permitido'];
                continue;
            }
            if (!@move_uploaded_file($tmpName, $dstPath)) {
                $rejected[] = ['name' => $origName, 'reason' => 'no se pudo guardar el archivo (permisos)'];
                continue;
            }
            @chmod($dstPath, 0664);
            $saved[] = $slot['name'];
        }
    }

    return ['saved' => $saved, 'rejected' => $rejected];
}

/**
 * Emite los tokens visuales de una temática como variables CSS, para que las
 * páginas PHP (carga de invitado, cartel QR) usen exactamente la misma paleta
 * que el kiosco en vez de una copia a mano.
 *
 * La fuente es siempre themes.json: si un tema no define un token, se cae al
 * default de :root en styles.css, nunca a un color inventado aquí.
 */
/**
 * Lockup de CumpleClick: el isotipo con el nombre al lado, para las paginas PHP.
 *
 * El nombre NO puede venir del SVG. `brand/cumpleclick-lockup.svg` dibuja la
 * palabra con un <text> en Baloo 2, y un SVG cargado dentro de un <img> se
 * renderiza en un documento aislado: no ve las @font-face de la pagina, solo
 * las fuentes instaladas en el sistema. Baloo 2 no viene con Windows ni con
 * iOS. Medido en el navegador, la misma palabra ocupa 403 px en Baloo 2,
 * 437 px en Segoe UI y 496 px en Helvetica: el nombre de la marca cambiaba de
 * forma segun el aparato del invitado. Compuesta en HTML usa la Baloo 2 que la
 * pagina ya trae self-hosted.
 *
 * Es la version PHP de src/brand/Lockup.jsx (album y cartel QR); las clases y
 * las proporciones son las mismas para que las dos se vean identicas.
 *
 * @param string $tono  'marca' sobre fondos claros, 'claro' sobre oscuros.
 *                      El manual pide el isotipo tal cual sobre fondo oscuro y
 *                      prohibe recuadrarlo en una caja blanca.
 * @param string $extra Clase adicional de quien lo usa, para el tamano.
 */
function cb_lockup_html(string $tono = 'marca', string $extra = ''): string
{
    $tono = $tono === 'claro' ? 'claro' : 'marca';
    $clases = trim('cc-lockup cc-lockup--' . $tono . ' ' . $extra);
    return '<span class="' . htmlspecialchars($clases, ENT_QUOTES, 'UTF-8') . '">'
         . '<img class="cc-lockup__mark" src="brand/cumpleclick-mark.svg" alt="CumpleClick" '
         . 'width="400" height="400" draggable="false">'
         . '<span class="cc-lockup__nombre" aria-hidden="true">'
         . 'Cumple<span class="cc-lockup__click">Click</span></span>'
         . '</span>';
}

function cb_theme_css_vars(string $themeSlug): string
{
    static $map = [
        'accent' => '--pink',
        'accentSoft' => '--pink-soft',
        'yellow' => '--yellow',
        'ink' => '--ink',
        'bgLight1' => '--bg-light1',
        'bgLight2' => '--bg-light2',
        'dark1' => '--dark1',
        'dark2' => '--dark2',
        'dark3' => '--dark3',
    ];

    $themes = cb_load_themes();
    $colors = $themes['themes'][$themeSlug]['colors'] ?? null;
    if (!is_array($colors)) {
        return '';
    }
    $out = [];
    foreach ($map as $key => $cssVar) {
        $value = (string) ($colors[$key] ?? '');
        // Solo hexadecimal: estos valores terminan dentro de un bloque <style>,
        // así que cualquier otra cosa se descarta en vez de escaparse.
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
            $out[] = $cssVar . ':' . $value;
        }
    }
    return $out ? implode(';', $out) . ';' : '';
}

/**
 * El color con que el navegador pinta su barra (`<meta name="theme-color">`).
 *
 * En el kiosco y en el álbum lo pone applyThemeColors() al vuelo, pero estas
 * páginas son PHP puro y no pasan por ahí: se quedaban con la barra gris de
 * Chrome mientras el resto de la experiencia iba en el color del tema. Salta a
 * la vista al saltar del kiosco a la invitación en el celular.
 *
 * Mismo criterio que en el front: se usa `accent`, el color con el que ya se
 * reconoce cada temática. Devuelve '' si el tema no existe o no declara accent,
 * y en ese caso NO se imprime el meta: mejor la barra por defecto del navegador
 * que un color inventado.
 */
function cb_theme_meta_color(string $themeSlug): string
{
    if ($themeSlug === '') {
        return '';
    }
    $themes = cb_load_themes();
    $accent = (string) ($themes['themes'][$themeSlug]['colors']['accent'] ?? '');
    // Solo hexadecimal, igual que cb_theme_css_vars: esto entra en un atributo
    // HTML y lo que no calce se descarta en vez de escaparse.
    return preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent) === 1 ? $accent : '';
}

// Módulo de invitaciones (depende de cb_config, cb_pdo, etc.).
require __DIR__ . '/lib.invitations.php';

// Solicitudes comerciales de la landing pública.
require __DIR__ . '/lib.leads.php';

// Álbum Recuerdo: álbum por evento, aportes de invitados y curaduría.
require __DIR__ . '/lib.album.php';

// Perfil del protagonista: datos y media opcionales por evento.
require __DIR__ . '/lib.event-profiles.php';

// Predicciones por evento y tokens privados de baby shower.
require __DIR__ . '/lib.predictions.php';

// Lista de regalos con reserva.
require __DIR__ . '/lib.gifts.php';
