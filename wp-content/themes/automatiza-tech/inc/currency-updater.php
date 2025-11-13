<?php
/**
 * Actualizador Automático de Precios CLP
 * 
 * Actualiza diariamente los precios en CLP de los servicios
 * basándose en el tipo de cambio oficial USD/CLP del Banco Central de Chile
 * 
 * @package AutomatizaTech
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class AutomatizaTech_Currency_Updater {
    
    /**
     * Tabla de servicios
     */
    private $services_table;
    
    /**
     * API del Banco Central de Chile
     * Endpoint: Indicadores diarios (Dólar observado)
     */
    private $bcch_api_url = 'https://mindicador.cl/api/dolar';
    
    /**
     * API alternativa (si falla la primera)
     * API pública de exchangerate-api.com
     */
    private $exchange_api_url = 'https://api.exchangerate-api.com/v4/latest/USD';
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->services_table = $wpdb->prefix . 'automatiza_services';
        
        // Hook para ejecutar actualización diaria
        add_action('automatiza_tech_daily_price_update', array($this, 'update_clp_prices'));
        
        // Hook para AJAX manual (desde admin)
        add_action('wp_ajax_update_clp_prices_manually', array($this, 'update_prices_manually'));
        
        // Programar evento diario si no existe
        if (!wp_next_scheduled('automatiza_tech_daily_price_update')) {
            // Programar para las 8:00 AM Chile (UTC-3)
            wp_schedule_event(strtotime('tomorrow 08:00:00'), 'daily', 'automatiza_tech_daily_price_update');
        }
    }
    
    /**
     * Obtener tipo de cambio USD/CLP desde Banco Central de Chile
     * Fuente oficial: mindicador.cl (API pública del Dólar observado)
     * 
     * @return float|false Tipo de cambio o false si falla
     */
    private function get_exchange_rate_bcch() {
        $response = wp_remote_get($this->bcch_api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log("ERROR BCCH API: " . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['serie']) && is_array($data['serie']) && count($data['serie']) > 0) {
            // El primer elemento es el valor más reciente
            $latest = $data['serie'][0];
            $exchange_rate = floatval($latest['valor']);
            
            error_log("TIPO DE CAMBIO BCCH: 1 USD = " . number_format($exchange_rate, 2) . " CLP (Fecha: {$latest['fecha']})");
            
            return $exchange_rate;
        }
        
        error_log("ERROR BCCH: Formato de respuesta inesperado");
        return false;
    }
    
    /**
     * Obtener tipo de cambio desde API alternativa
     * 
     * @return float|false Tipo de cambio o false si falla
     */
    private function get_exchange_rate_alternative() {
        $response = wp_remote_get($this->exchange_api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log("ERROR API ALTERNATIVA: " . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['rates']['CLP'])) {
            $exchange_rate = floatval($data['rates']['CLP']);
            
            error_log("TIPO DE CAMBIO ALTERNATIVO: 1 USD = " . number_format($exchange_rate, 2) . " CLP");
            
            return $exchange_rate;
        }
        
        error_log("ERROR API ALTERNATIVA: No se encontró tasa CLP");
        return false;
    }
    
    /**
     * Obtener tipo de cambio con fallback automático
     * Intenta primero BCCH (oficial), si falla usa API alternativa
     * 
     * @return float|false Tipo de cambio o false si ambas fallan
     */
    public function get_current_exchange_rate() {
        // Intentar primero con Banco Central de Chile (fuente oficial)
        $rate = $this->get_exchange_rate_bcch();
        
        // Si falla, intentar con API alternativa
        if ($rate === false) {
            error_log("FALLBACK: Usando API alternativa para tipo de cambio");
            $rate = $this->get_exchange_rate_alternative();
        }
        
        // Si ambas fallan, usar tipo de cambio de respaldo (último conocido o valor por defecto)
        if ($rate === false) {
            $rate = floatval(get_option('automatiza_tech_last_exchange_rate', 850.0));
            error_log("ADVERTENCIA: Usando tipo de cambio de respaldo: {$rate} CLP");
        } else {
            // Guardar tipo de cambio exitoso para usar como respaldo
            update_option('automatiza_tech_last_exchange_rate', $rate);
            update_option('automatiza_tech_last_exchange_rate_date', current_time('mysql'));
        }
        
        return $rate;
    }
    
    /**
     * Actualizar precios CLP basados en precios USD
     * 
     * @return array Resultado con servicios actualizados y errores
     */
    public function update_clp_prices() {
        global $wpdb;
        
        error_log("========================================");
        error_log("INICIANDO ACTUALIZACIÓN DE PRECIOS CLP");
        error_log("Fecha: " . current_time('Y-m-d H:i:s'));
        error_log("========================================");
        
        // Obtener tipo de cambio actual
        $exchange_rate = $this->get_current_exchange_rate();
        
        if (!$exchange_rate || $exchange_rate <= 0) {
            error_log("ERROR: No se pudo obtener tipo de cambio válido");
            return array(
                'success' => false,
                'message' => 'No se pudo obtener el tipo de cambio',
                'updated' => 0
            );
        }
        
        // Obtener todos los servicios con precio USD definido
        $services = $wpdb->get_results("
            SELECT id, name, price_usd, price_clp 
            FROM {$this->services_table} 
            WHERE price_usd > 0
            ORDER BY id ASC
        ");
        
        if (empty($services)) {
            error_log("ADVERTENCIA: No hay servicios con precio USD para actualizar");
            return array(
                'success' => false,
                'message' => 'No hay servicios con precio USD',
                'updated' => 0
            );
        }
        
        $updated_count = 0;
        $results = array();
        
        foreach ($services as $service) {
            $old_clp = floatval($service->price_clp);
            $usd_price = floatval($service->price_usd);
            
            // Calcular nuevo precio CLP
            // Redondear a múltiplos de 1000 para precios más "limpios"
            $new_clp = round($usd_price * $exchange_rate / 1000) * 1000;
            
            // Actualizar solo si hay cambio significativo (más de 2% de diferencia)
            $difference_percent = $old_clp > 0 ? abs(($new_clp - $old_clp) / $old_clp * 100) : 100;
            
            if ($difference_percent >= 2.0 || $old_clp == 0) {
                $updated = $wpdb->update(
                    $this->services_table,
                    array('price_clp' => $new_clp),
                    array('id' => $service->id),
                    array('%f'),
                    array('%d')
                );
                
                if ($updated !== false) {
                    $updated_count++;
                    
                    $log_msg = sprintf(
                        "✓ Servicio ID %d (%s): %s USD × %s CLP = %s CLP (anterior: %s CLP, cambio: %s%%)",
                        $service->id,
                        $service->name,
                        number_format($usd_price, 2),
                        number_format($exchange_rate, 2),
                        number_format($new_clp, 0),
                        number_format($old_clp, 0),
                        number_format($difference_percent, 1)
                    );
                    
                    error_log($log_msg);
                    
                    $results[] = array(
                        'id' => $service->id,
                        'name' => $service->name,
                        'usd' => $usd_price,
                        'old_clp' => $old_clp,
                        'new_clp' => $new_clp,
                        'change_percent' => $difference_percent
                    );
                } else {
                    error_log("✗ Error al actualizar servicio ID {$service->id}");
                }
            } else {
                error_log("○ Servicio ID {$service->id} ({$service->name}): Sin cambio significativo (cambio: " . number_format($difference_percent, 1) . "%)");
            }
        }
        
        error_log("========================================");
        error_log("ACTUALIZACIÓN COMPLETADA: {$updated_count} servicios actualizados");
        error_log("Tipo de cambio usado: 1 USD = " . number_format($exchange_rate, 2) . " CLP");
        error_log("========================================");
        
        // Guardar registro de actualización
        update_option('automatiza_tech_last_price_update', current_time('mysql'));
        update_option('automatiza_tech_last_update_count', $updated_count);
        update_option('automatiza_tech_last_update_rate', $exchange_rate);
        
        return array(
            'success' => true,
            'message' => "{$updated_count} servicios actualizados",
            'updated' => $updated_count,
            'exchange_rate' => $exchange_rate,
            'details' => $results
        );
    }
    
    /**
     * AJAX: Actualizar precios manualmente desde el admin
     */
    public function update_prices_manually() {
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No tienes permisos'));
            wp_die(); // Importante: terminar ejecución
        }
        
        // Verificar nonce
        check_ajax_referer('automatiza_tech_admin', 'nonce');
        
        // Ejecutar actualización
        $result = $this->update_clp_prices();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
        
        wp_die(); // Importante: terminar ejecución AJAX correctamente
    }
    
    /**
     * Obtener información del último update
     * 
     * @return array Info del último update
     */
    public function get_last_update_info() {
        return array(
            'last_update' => get_option('automatiza_tech_last_price_update', 'Nunca'),
            'updated_count' => get_option('automatiza_tech_last_update_count', 0),
            'exchange_rate' => get_option('automatiza_tech_last_update_rate', 0),
            'next_scheduled' => wp_next_scheduled('automatiza_tech_daily_price_update'),
            'last_exchange_rate' => get_option('automatiza_tech_last_exchange_rate', 0),
            'last_exchange_date' => get_option('automatiza_tech_last_exchange_rate_date', 'Nunca')
        );
    }
}

// Inicializar la clase
function automatiza_tech_init_currency_updater() {
    return new AutomatizaTech_Currency_Updater();
}

// Hook de inicialización
add_action('init', 'automatiza_tech_init_currency_updater');
