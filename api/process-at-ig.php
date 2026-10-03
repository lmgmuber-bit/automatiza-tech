<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configFile = __DIR__ . '/process-at-ig.config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'missing_config']);
    exit;
}

require $configFile;

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function request_token(array $body): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === 'x-at-signal-token') {
            return (string) $value;
        }
    }
    if (isset($_SERVER['HTTP_X_AT_SIGNAL_TOKEN'])) {
        return (string) $_SERVER['HTTP_X_AT_SIGNAL_TOKEN'];
    }
    if (isset($body['token'])) {
        return (string) $body['token'];
    }
    return '';
}

function default_state(): array
{
    return [
        'pending_count' => 0,
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
        'last_enqueue_at' => null,
        'last_take_at' => null,
        'last_report_at' => null,
        'last_report' => null,
    ];
}

function mutate_state(callable $callback): array
{
    $stateFile = AT_IG_SIGNAL_STATE_FILE;
    $dir = dirname($stateFile);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        response(500, ['ok' => false, 'error' => 'state_dir_unwritable']);
    }

    $handle = fopen($stateFile, 'c+');
    if (!$handle) {
        response(500, ['ok' => false, 'error' => 'state_unwritable']);
    }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = $raw ? json_decode($raw, true) : null;
    if (!is_array($state)) {
        $state = default_state();
    }

    $result = $callback($state);
    $state['updated_at'] = gmdate('c');

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $result;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$body = read_json_body();
if (!defined('AT_IG_SIGNAL_TOKEN') || AT_IG_SIGNAL_TOKEN === 'change-this-long-random-token') {
    response(500, ['ok' => false, 'error' => 'token_not_configured']);
}

if (!hash_equals(AT_IG_SIGNAL_TOKEN, request_token($body))) {
    response(401, ['ok' => false, 'error' => 'unauthorized']);
}

$action = strtolower(trim((string) ($body['action'] ?? 'enqueue')));

if ($action === 'enqueue') {
    $result = mutate_state(function (array &$state): array {
        $state['pending_count'] = max(0, (int) ($state['pending_count'] ?? 0)) + 1;
        $state['last_enqueue_at'] = gmdate('c');
        return [
            'ok' => true,
            'action' => 'enqueue',
            'pending_count' => $state['pending_count'],
        ];
    });
    response(200, $result);
}

if ($action === 'take') {
    $result = mutate_state(function (array &$state): array {
        $pending = max(0, (int) ($state['pending_count'] ?? 0));
        if ($pending < 1) {
            return [
                'ok' => true,
                'action' => 'take',
                'should_process' => false,
                'pending_count' => 0,
            ];
        }
        $state['pending_count'] = $pending - 1;
        $state['last_take_at'] = gmdate('c');
        return [
            'ok' => true,
            'action' => 'take',
            'should_process' => true,
            'pending_count' => $state['pending_count'],
        ];
    });
    response(200, $result);
}

if ($action === 'status') {
    $result = mutate_state(function (array &$state): array {
        return [
            'ok' => true,
            'action' => 'status',
            'pending_count' => max(0, (int) ($state['pending_count'] ?? 0)),
            'updated_at' => $state['updated_at'] ?? null,
            'last_enqueue_at' => $state['last_enqueue_at'] ?? null,
            'last_take_at' => $state['last_take_at'] ?? null,
            'last_report_at' => $state['last_report_at'] ?? null,
            'last_report' => $state['last_report'] ?? null,
        ];
    });
    response(200, $result);
}

if ($action === 'report') {
    $result = mutate_state(function (array &$state) use ($body): array {
        $state['last_report_at'] = gmdate('c');
        $state['last_report'] = [
            'ok' => (bool) ($body['ok'] ?? false),
            'has_job' => (bool) ($body['has_job'] ?? false),
            'id' => substr((string) ($body['id'] ?? ''), 0, 120),
            'estado' => substr((string) ($body['estado'] ?? ''), 0, 60),
            'message' => substr((string) ($body['message'] ?? ''), 0, 240),
        ];
        return [
            'ok' => true,
            'action' => 'report',
        ];
    });
    response(200, $result);
}

response(400, ['ok' => false, 'error' => 'unknown_action']);
