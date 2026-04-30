<?php
/**
 * OpenAI Controller Integration
 * 
 * Requiere que se defina OPENAI_API_KEY en wp-config.php.
 * define('OPENAI_API_KEY', 'sk-proj-...');
 */

// Solo cargar wp-load si no está ya cargado (evita doble include desde api-chat-proxy.php)
if (!function_exists('current_time')) {
    require_once(__DIR__ . '/wp-load.php');
}

class OpenAIController {
    private $apiKey;
    private $wpdb;
    private $tableName;
    private $precios = [
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-2024-05-13' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
    ];

    private $tableReady = false;

    public function __construct($apiKey = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'ai_usage_log';
        
        // API Key: parámetro > constante de wp-config.php
        $this->apiKey = $apiKey ?: (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : null);
        
        if (empty($this->apiKey)) {
            error_log('OpenAIController: OPENAI_API_KEY no está definida en wp-config.php');
        }
        
        // Auto-crear tabla si no existe
        $this->ensureTable();
    }

    /**
     * Verifica y crea la tabla ai_usage_log si no existe
     */
    private function ensureTable() {
        // Suprimir errores HTML de WordPress durante la verificación
        $suppress = $this->wpdb->suppress_errors(true);
        $exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->tableName}'");
        $this->wpdb->suppress_errors($suppress);
        
        if ($exists) {
            $this->tableReady = true;
            return;
        }

        // Crear la tabla
        $charset_collate = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL DEFAULT 0,
            client_identifier VARCHAR(100) NOT NULL DEFAULT '',
            prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            model_used VARCHAR(50) NOT NULL DEFAULT '',
            cost_estimated DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
            request_endpoint VARCHAR(100) DEFAULT 'chat/completions',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_client (client_identifier),
            INDEX idx_created (created_at),
            INDEX idx_user_date (user_id, created_at)
        ) ENGINE=InnoDB {$charset_collate}";

        $suppress = $this->wpdb->suppress_errors(true);
        $result = $this->wpdb->query($sql);
        $this->wpdb->suppress_errors($suppress);
        
        if ($result !== false) {
            $this->tableReady = true;
            error_log('OpenAIController: Tabla ' . $this->tableName . ' creada exitosamente');
        } else {
            error_log('OpenAIController: No se pudo crear tabla ' . $this->tableName . ': ' . $this->wpdb->last_error);
        }
    }

    /**
     * Calcula el costo de la llamada
     */
    public function calcularCosto($modelo, $inputTokens, $outputTokens) {
        // Normalizar nombre del modelo (ej. gpt-4o-2024-05-13 -> gpt-4o)
        $modeloBase = $modelo;
        foreach (array_keys($this->precios) as $key) {
            if (strpos($modelo, $key) !== false) {
                $modeloBase = $key;
                break;
            }
        }

        if (!isset($this->precios[$modeloBase])) {
            // Default o log de advertencia
            return 0;
        }

        $costoInput = ($inputTokens / 1000000) * $this->precios[$modeloBase]['input'];
        $costoOutput = ($outputTokens / 1000000) * $this->precios[$modeloBase]['output'];

        return round($costoInput + $costoOutput, 6);
    }

    /**
     * Verifica si el usuario tiene saldo disponible para operar
     */
    public function checkUserBalance($userId) {
        if (!$this->tableReady) {
            return true; // Si no hay tabla, permitir (no bloquear por error de BD)
        }
        
        // Suprimir errores HTML de WordPress
        $suppress = $this->wpdb->suppress_errors(true);
        
        $sql = $this->wpdb->prepare(
            "SELECT SUM(cost_estimated) FROM {$this->tableName} 
             WHERE user_id = %d 
             AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
             AND YEAR(created_at) = YEAR(CURRENT_DATE())",
            $userId
        );
        
        $gastoActual = $this->wpdb->get_var($sql);
        
        $this->wpdb->suppress_errors($suppress);
        
        // Si hubo error en la query, permitir (no bloquear)
        if ($gastoActual === null && !empty($this->wpdb->last_error)) {
            error_log('OpenAIController: Error en checkUserBalance: ' . $this->wpdb->last_error);
            return true;
        }
        
        $limiteMensual = 10.00; // $10 USD
        return ($gastoActual < $limiteMensual);
    }

    /**
     * Registra el consumo en la base de datos
     * Falla silenciosamente si la tabla no existe (no rompe la respuesta de OpenAI)
     */
    public function registrarConsumoIA($userId, $apiResponse, $clientIdentifier = null) {
        if (!isset($apiResponse['usage'])) {
            return false;
        }
        
        if (!$this->tableReady) {
            error_log('OpenAIController: Tabla ' . $this->tableName . ' no disponible, tracking omitido para ' . ($clientIdentifier ?: 'unknown'));
            return false;
        }

        $usage = $apiResponse['usage'];
        $model = $apiResponse['model'];
        
        $promptTokens = $usage['prompt_tokens'];
        $completionTokens = $usage['completion_tokens'];
        $totalTokens = $usage['total_tokens'];
        
        $costo = $this->calcularCosto($model, $promptTokens, $completionTokens);

        $data = [
            'user_id' => $userId,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'model_used' => $model,
            'cost_estimated' => $costo,
            'created_at' => current_time('mysql')
        ];
        
        $format = ['%d', '%d', '%d', '%d', '%s', '%f', '%s'];

        if ($clientIdentifier) {
            $data['client_identifier'] = $clientIdentifier;
            $format[] = '%s';
        }

        // Suprimir errores HTML de WordPress durante el insert
        $suppress = $this->wpdb->suppress_errors(true);
        $result = $this->wpdb->insert($this->tableName, $data, $format);
        $this->wpdb->suppress_errors($suppress);
        
        if ($result === false) {
            error_log('OpenAIController: Error registrando consumo: ' . $this->wpdb->last_error);
        }
        
        return $result;
    }

    /**
     * Realiza la petición a OpenAI
     */
    public function chatCompletion($userId, $messages, $model = 'gpt-4o', $clientIdentifier = null) {
        // 1. Verificar balance
        if (!$this->checkUserBalance($userId)) {
            return ['error' => 'Límite de presupuesto excedido para este usuario.', 'code' => 402]; // Payment Required
        }

        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $model,
            'messages' => $messages
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para entorno local WAMP
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . 'Bearer ' . $this->apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return ['error' => 'cURL Error: ' . $error_msg];
        }
        
        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        // Manejo de Error 429
        if ($httpCode === 429) {
            return ['error' => 'Error 429: Too Many Requests. Has alcanzado el límite de OpenAI.', 'code' => 429];
        }

        if ($httpCode !== 200) {
            return ['error' => 'OpenAI API Error', 'details' => $decodedResponse, 'code' => $httpCode];
        }

        // 2. Registrar consumo si fue exitoso
        $this->registrarConsumoIA($userId, $decodedResponse, $clientIdentifier);

        return $decodedResponse;
    }
    
    /**
     * Obtiene estadísticas para el dashboard
     */
    public function getMonthlyStats() {
        if (!$this->tableReady) return [];
        
        $suppress = $this->wpdb->suppress_errors(true);
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d') as fecha, 
                    SUM(cost_estimated) as costo,
                    SUM(total_tokens) as tokens
                FROM {$this->tableName}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d')
                ORDER BY fecha ASC";
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        $this->wpdb->suppress_errors($suppress);
        return $results ?: [];
    }

    public function getUserStats() {
        if (!$this->tableReady) return [];
        
        $suppress = $this->wpdb->suppress_errors(true);
        $sql = "SELECT user_id, SUM(cost_estimated) as total_cost, COUNT(*) as requests 
                FROM {$this->tableName} 
                GROUP BY user_id 
                ORDER BY total_cost DESC LIMIT 10";
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        $this->wpdb->suppress_errors($suppress);
        return $results ?: [];
    }

    /**
     * Obtiene estadísticas por client_identifier específico
     */
    public function getClientStats($clientIdentifier) {
        if (!$this->tableReady) return ['mes_actual' => [], 'diario' => [], 'modelos' => [], 'facturable' => 0];
        
        $suppress = $this->wpdb->suppress_errors(true);
        
        $mesActual = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                COALESCE(SUM(prompt_tokens), 0) as prompt_tokens,
                COALESCE(SUM(completion_tokens), 0) as completion_tokens,
                COALESCE(SUM(total_tokens), 0) as total_tokens,
                COALESCE(SUM(cost_estimated), 0) as cost_estimated
             FROM {$this->tableName}
             WHERE client_identifier = %s
               AND MONTH(created_at) = MONTH(NOW()) 
               AND YEAR(created_at) = YEAR(NOW())",
            $clientIdentifier
        ), ARRAY_A);

        $diario = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m-%%d') as fecha,
                COUNT(*) as requests,
                SUM(total_tokens) as tokens,
                SUM(cost_estimated) as costo
             FROM {$this->tableName}
             WHERE client_identifier = %s
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE_FORMAT(created_at, '%%Y-%%m-%%d')
             ORDER BY fecha ASC",
            $clientIdentifier
        ), ARRAY_A);

        $modelos = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT model_used, COUNT(*) as veces, SUM(cost_estimated) as costo
             FROM {$this->tableName}
             WHERE client_identifier = %s
             GROUP BY model_used
             ORDER BY costo DESC",
            $clientIdentifier
        ), ARRAY_A);

        $this->wpdb->suppress_errors($suppress);

        return [
            'mes_actual' => $mesActual ?: [],
            'diario' => $diario ?: [],
            'modelos' => $modelos ?: [],
            'facturable' => round(($mesActual['cost_estimated'] ?? 0) * 1.3, 4)
        ];
    }

    /**
     * Lista todos los clientes con consumo registrado
     */
    public function getAllClientIdentifiers() {
        if (!$this->tableReady) return [];
        
        $suppress = $this->wpdb->suppress_errors(true);
        $results = $this->wpdb->get_results(
            "SELECT 
                client_identifier,
                COUNT(*) as total_requests,
                SUM(total_tokens) as total_tokens,
                SUM(cost_estimated) as total_cost,
                MAX(created_at) as last_activity
             FROM {$this->tableName}
             WHERE client_identifier IS NOT NULL AND client_identifier != ''
             GROUP BY client_identifier
             ORDER BY total_cost DESC",
            ARRAY_A
        );
        $this->wpdb->suppress_errors($suppress);
        return $results ?: [];
    }

    /**
     * Indica si la tabla de tracking está disponible
     */
    public function isTrackingReady() {
        return $this->tableReady;
    }

    /**
     * Devuelve la API Key (para verificación en el proxy)
     */
    public function getApiKey() {
        return $this->apiKey;
    }
}
?>