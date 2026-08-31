<?php
/** Endpoint de fotos: PNG real, cuota por fiesta, rate limit persistente y storage privado. */
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function cb_upload_error(int $status, string $error, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => false, 'error' => $error], $extra), JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    cb_upload_error(405, 'method_not_allowed');
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 12 * 1024 * 1024) {
    cb_upload_error(413, 'too_big');
}
$raw = file_get_contents('php://input', false, null, 0, 12 * 1024 * 1024 + 1);
if (!is_string($raw) || strlen($raw) > 12 * 1024 * 1024) {
    cb_upload_error(413, 'too_big');
}
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['image'])) {
    cb_upload_error(400, 'no_image');
}

$partySlug = (string) ($data['party'] ?? '');
if (!cb_valid_public_slug($partySlug)) {
    cb_upload_error(400, 'bad_party');
}
$party = cb_load_party_raw($partySlug);
if ($party === null) {
    cb_upload_error(403, 'party_not_found');
}
if (empty($party['activa'])) {
    cb_upload_error(403, 'party_inactive');
}

$limit = cb_rate_limit('photo-upload:' . $partySlug, cb_request_identity(), 30, 600, 600);
if (!$limit['allowed']) {
    header('Retry-After: ' . max(1, (int) $limit['retry_after']));
    cb_upload_error(429, 'rate_limited', ['retry_after' => (int) $limit['retry_after']]);
}

$image = (string) $data['image'];
if (strpos($image, 'data:image/png;base64,') !== 0) {
    cb_upload_error(415, 'png_required');
}
$encoded = substr($image, strlen('data:image/png;base64,'));
if (strlen($encoded) > (int) ceil(8 * 1024 * 1024 * 4 / 3) + 8) {
    cb_upload_error(413, 'too_big');
}
$bin = base64_decode(str_replace(' ', '+', $encoded), true);
if ($bin === false || strlen($bin) === 0) {
    cb_upload_error(400, 'bad_base64');
}
$bytes = strlen($bin);
if ($bytes > 8 * 1024 * 1024) {
    cb_upload_error(413, 'too_big');
}
if (substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") {
    cb_upload_error(415, 'png_required');
}
$info = @getimagesizefromstring($bin);
if ($info === false || ($info[2] ?? null) !== IMAGETYPE_PNG) {
    cb_upload_error(415, 'invalid_png');
}
$width = (int) ($info[0] ?? 0);
$height = (int) ($info[1] ?? 0);
if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096) {
    cb_upload_error(422, 'invalid_dimensions', ['max_dimension' => 4096]);
}

$usage = cb_photo_usage($partySlug);
if ($usage['count'] >= 200 || $usage['bytes'] + $bytes > 1024 * 1024 * 1024) {
    cb_upload_error(507, 'party_quota_exceeded', ['max_photos' => 200, 'max_bytes' => 1073741824]);
}

$token = bin2hex(random_bytes(16)); // 128 bits, opaco y no enumerable.
$storageKey = $partySlug . '/' . gmdate('Y/m') . '/' . $token . '.png';
$path = cb_photo_absolute_path($storageKey);
if ($path === null) {
    cb_upload_error(500, 'storage_key_failed');
}
$dir = dirname($path);
if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
    cb_upload_error(500, 'mkdir_failed');
}
$tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
if (file_put_contents($tmp, $bin, LOCK_EX) !== $bytes || !rename($tmp, $path)) {
    @unlink($tmp);
    cb_upload_error(500, 'write_failed');
}
@chmod($path, 0660);

$safeName = preg_replace('/[^\pL\pN_-]+/u', '-', trim((string) ($data['name'] ?? 'foto')));
$safeName = trim((string) $safeName, '-');
if ($safeName === '') {
    $safeName = 'foto';
}
$record = [
    'token' => $token, 'storage_key' => $storageKey,
    'original_name' => substr($safeName, 0, 80) . '.png',
    'byte_size' => $bytes, 'width' => $width, 'height' => $height,
    'sha256' => hash('sha256', $bin), 'created_at' => gmdate('Y-m-d H:i:s'),
];
$recordResult = cb_record_photo_with_quota($partySlug, $record);
if ($recordResult !== 'ok') {
    @unlink($path);
    if ($recordResult === 'quota') {
        cb_upload_error(507, 'party_quota_exceeded', ['max_photos' => 200, 'max_bytes' => 1073741824]);
    }
    cb_upload_error(500, 'metadata_failed');
}

$url = cb_public_base_url() . '/ver.php?t=' . rawurlencode($token);
echo json_encode(['ok' => true, 'url' => $url], JSON_UNESCAPED_SLASHES);
