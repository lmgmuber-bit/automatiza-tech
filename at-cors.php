<?php
/**
 * AT CORS Helper
 * ==============
 * Aplica CORS de forma segura: refleja Origin solo si esta en una whitelist
 * dependiente del ambiente (local vs produccion). Nunca emite "*".
 *
 * Uso:
 *   require_once __DIR__ . '/at-cors.php';
 *   at_cors_apply([
 *       'methods' => 'GET, POST, OPTIONS',
 *       'headers' => 'Content-Type, Authorization',
 *   ]);
 */

if (!function_exists('at_cors_allowed_origins')) {
    function at_cors_allowed_origins() {
        $env = defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production';
        $origins = [];

        // Produccion: solo dominios oficiales.
        $origins[] = 'https://automatizatech.cl';
        $origins[] = 'https://www.automatizatech.cl';

        if ($env === 'local') {
            $origins = array_merge($origins, [
                'http://localhost',
                'http://localhost:3000',
                'http://localhost:5173',
                'http://localhost:5174',
                'http://localhost:5175',
                'http://localhost:5176',
                'http://127.0.0.1',
                'http://127.0.0.1:5173',
            ]);
        }

        // Permitir agregar/override desde el sitio (opcional via filter)
        if (function_exists('apply_filters')) {
            $origins = apply_filters('at_cors_allowed_origins', $origins);
        }
        return array_values(array_unique($origins));
    }
}

if (!function_exists('at_cors_apply')) {
    function at_cors_apply(array $opts = []) {
        $methods = $opts['methods'] ?? 'GET, POST, OPTIONS';
        $headers = $opts['headers'] ?? 'Content-Type, Authorization';
        $credentials = !empty($opts['credentials']);
        $max_age = (int) ($opts['max_age'] ?? 86400);

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = at_cors_allowed_origins();

        // Importante para caches/proxies: variar por Origin.
        header('Vary: Origin', false);

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            if ($credentials) {
                header('Access-Control-Allow-Credentials: true');
            }
        }
        header('Access-Control-Allow-Methods: ' . $methods);
        header('Access-Control-Allow-Headers: ' . $headers);
        header('Access-Control-Max-Age: ' . $max_age);

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
