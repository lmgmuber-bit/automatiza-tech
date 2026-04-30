<?php
/**
 * Script de migración y corrección para tracking de consumo AI
 * 
 * Corrige:
 * 1. Migra datos de 'ai_usage_log' (sin prefix) → '{prefix}ai_usage_log' (con prefix)
 * 2. Agrega Alexiandra Andrade (PetsGo) al CRM con ai_identifier = 'cliente_petsgo'
 * 3. Limpia tabla sin prefijo después de migrar
 * 
 * EJECUTAR EN LOCAL Y EN PRODUCCIÓN
 */
require_once('wp-load.php');
global $wpdb;

$prefix = $wpdb->prefix;
$tabla_con_prefix = $prefix . 'ai_usage_log';
$tabla_sin_prefix = 'ai_usage_log';
$tabla_crm = $prefix . 'crm_clientes';

echo "=== MIGRACIÓN DE CONSUMO AI ===" . PHP_EOL;
echo "Prefix: {$prefix}" . PHP_EOL;
echo "Tabla destino (CRM usa): {$tabla_con_prefix}" . PHP_EOL;
echo "Tabla origen (proxy usaba): {$tabla_sin_prefix}" . PHP_EOL . PHP_EOL;

// ============================================================
// PASO 1: Verificar que la tabla destino exista
// ============================================================
echo "--- PASO 1: Verificar tabla destino ---" . PHP_EOL;
$wpdb->suppress_errors(true);
$exists_dest = $wpdb->get_var("SHOW TABLES LIKE '{$tabla_con_prefix}'");
$wpdb->suppress_errors(false);

if (!$exists_dest) {
    echo "  Tabla {$tabla_con_prefix} no existe. Creándola via OpenAIController..." . PHP_EOL;
    require_once('openai-controller.php');
    $controller = new OpenAIController();
    $exists_dest = $wpdb->get_var("SHOW TABLES LIKE '{$tabla_con_prefix}'");
    if ($exists_dest) {
        echo "  ✅ Tabla {$tabla_con_prefix} creada" . PHP_EOL;
    } else {
        echo "  ❌ No se pudo crear la tabla. Abortando." . PHP_EOL;
        exit(1);
    }
} else {
    $count_dest = $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_con_prefix}");
    echo "  ✅ Tabla {$tabla_con_prefix} ya existe ({$count_dest} registros)" . PHP_EOL;
}

// ============================================================
// PASO 2: Migrar datos de tabla sin prefix (si existe)
// ============================================================
echo PHP_EOL . "--- PASO 2: Migrar datos de tabla sin prefix ---" . PHP_EOL;
$wpdb->suppress_errors(true);
$exists_orig = $wpdb->get_var("SHOW TABLES LIKE '{$tabla_sin_prefix}'");
$wpdb->suppress_errors(false);

if ($exists_orig && $tabla_sin_prefix !== $tabla_con_prefix) {
    $count_orig = $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_sin_prefix}");
    echo "  Tabla {$tabla_sin_prefix} tiene {$count_orig} registros" . PHP_EOL;
    
    if ($count_orig > 0) {
        // Obtener columnas de la tabla destino para hacer INSERT compatible
        $cols_dest = $wpdb->get_col("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tabla_con_prefix}' ORDER BY ORDINAL_POSITION");
        $cols_orig = $wpdb->get_col("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tabla_sin_prefix}' ORDER BY ORDINAL_POSITION");
        
        // Columnas en común (excluyendo 'id' para evitar conflictos)
        $common_cols = array_filter(array_intersect($cols_dest, $cols_orig), function($c) { return $c !== 'id'; });
        $cols_str = implode(', ', $common_cols);
        
        echo "  Columnas a migrar: {$cols_str}" . PHP_EOL;
        
        // Verificar registros ya existentes (evitar duplicados)
        $max_id_dest = $wpdb->get_var("SELECT MAX(id) FROM {$tabla_con_prefix}") ?: 0;
        
        // Insertar datos no duplicados basándose en client_identifier + created_at
        $wpdb->suppress_errors(true);
        $migrated = $wpdb->query("
            INSERT INTO {$tabla_con_prefix} ({$cols_str})
            SELECT {$cols_str} FROM {$tabla_sin_prefix} o
            WHERE NOT EXISTS (
                SELECT 1 FROM {$tabla_con_prefix} d 
                WHERE d.client_identifier = o.client_identifier 
                AND d.created_at = o.created_at
                AND d.total_tokens = o.total_tokens
            )
        ");
        $wpdb->suppress_errors(false);
        
        if ($migrated !== false) {
            echo "  ✅ Migrados {$migrated} registros nuevos a {$tabla_con_prefix}" . PHP_EOL;
        } else {
            echo "  ⚠️ Error migrando: " . $wpdb->last_error . PHP_EOL;
            // Intentar registro por registro como fallback
            echo "  Intentando migración registro por registro..." . PHP_EOL;
            $rows = $wpdb->get_results("SELECT * FROM {$tabla_sin_prefix}", ARRAY_A);
            $migrated_count = 0;
            foreach ($rows as $row) {
                unset($row['id']); // Dejar que auto-increment asigne
                $wpdb->suppress_errors(true);
                $ins = $wpdb->insert($tabla_con_prefix, $row);
                $wpdb->suppress_errors(false);
                if ($ins) $migrated_count++;
            }
            echo "  ✅ Migrados {$migrated_count} de " . count($rows) . " registros" . PHP_EOL;
        }
    } else {
        echo "  ℹ️ Tabla sin prefix vacía, nada que migrar" . PHP_EOL;
    }
    
    // No eliminar la tabla vieja automáticamente (puede ser peligroso en producción)
    echo "  ℹ️ Tabla {$tabla_sin_prefix} se mantiene como backup" . PHP_EOL;
    echo "     Para eliminarla: DROP TABLE {$tabla_sin_prefix};" . PHP_EOL;
} elseif ($tabla_sin_prefix === $tabla_con_prefix) {
    echo "  ℹ️ Sin prefix en wp-config (prefix = ''), ambas tablas son la misma" . PHP_EOL;
} else {
    echo "  ℹ️ Tabla {$tabla_sin_prefix} no existe, nada que migrar" . PHP_EOL;
}

// ============================================================
// PASO 3: Agregar Alexiandra Andrade (PetsGo) al CRM
// ============================================================
echo PHP_EOL . "--- PASO 3: Agregar Alexiandra/PetsGo al CRM ---" . PHP_EOL;
$wpdb->suppress_errors(true);
$tabla_crm_exists = $wpdb->get_var("SHOW TABLES LIKE '{$tabla_crm}'");
$wpdb->suppress_errors(false);

if ($tabla_crm_exists) {
    // Verificar si ya existe
    $existe_petsgo = $wpdb->get_row("SELECT id, nombre, ai_identifier FROM {$tabla_crm} WHERE ai_identifier = 'cliente_petsgo'", ARRAY_A);
    $existe_alex = $wpdb->get_row("SELECT id, nombre, ai_identifier FROM {$tabla_crm} WHERE nombre LIKE '%lexiandr%' OR nombre LIKE '%ndrade%'", ARRAY_A);
    
    if ($existe_petsgo) {
        echo "  ✅ Ya existe: ID:{$existe_petsgo['id']} | {$existe_petsgo['nombre']} | ai_id: {$existe_petsgo['ai_identifier']}" . PHP_EOL;
    } elseif ($existe_alex) {
        // Alexiandra existe pero sin ai_identifier
        echo "  Alexiandra existe (ID:{$existe_alex['id']}) pero sin ai_identifier. Actualizando..." . PHP_EOL;
        $wpdb->update($tabla_crm, 
            ['ai_identifier' => 'cliente_petsgo'], 
            ['id' => $existe_alex['id']]
        );
        echo "  ✅ Actualizado ai_identifier = 'cliente_petsgo' para {$existe_alex['nombre']}" . PHP_EOL;
    } else {
        // Crear nuevo registro
        $wpdb->insert($tabla_crm, [
            'nombre' => 'Alexiandra Andrade',
            'empresa' => 'PetsGo',
            'email' => '',
            'tipo' => 'cliente',
            'estado' => 'contratado',
            'ai_identifier' => 'cliente_petsgo',
            'fecha_contacto' => current_time('mysql')
        ]);
        $new_id = $wpdb->insert_id;
        echo "  ✅ Creada: ID:{$new_id} | Alexiandra Andrade | PetsGo | ai_id: cliente_petsgo" . PHP_EOL;
    }
} else {
    echo "  ⚠️ Tabla CRM {$tabla_crm} no existe" . PHP_EOL;
}

// ============================================================
// PASO 4: Verificación final
// ============================================================
echo PHP_EOL . "--- PASO 4: Verificación final ---" . PHP_EOL;
$total = $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_con_prefix}");
$petsgo = $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_con_prefix} WHERE client_identifier = 'cliente_petsgo'");
echo "  Registros totales en {$tabla_con_prefix}: {$total}" . PHP_EOL;
echo "  Registros PetsGo: {$petsgo}" . PHP_EOL;

$cliente_crm = $wpdb->get_row("SELECT id, nombre, ai_identifier FROM {$tabla_crm} WHERE ai_identifier = 'cliente_petsgo'", ARRAY_A);
if ($cliente_crm) {
    echo "  Cliente CRM: ID:{$cliente_crm['id']} | {$cliente_crm['nombre']} | {$cliente_crm['ai_identifier']}" . PHP_EOL;
} else {
    echo "  ⚠️ Cliente PetsGo no encontrado en CRM" . PHP_EOL;
}

echo PHP_EOL . "=== DÓNDE VER EL CONSUMO ===" . PHP_EOL;
echo "  1. Admin WP → CRM Clientes → Consumo AI (menú lateral)" . PHP_EOL;
echo "  2. Admin WP → CRM Clientes → Ficha de Cliente → buscar 'Alexiandra' → Tab 'Consumo AI'" . PHP_EOL;
echo "  3. URL directa al dashboard: /wp-admin/admin.php?page=automatiza-crm-ai" . PHP_EOL;
echo "  4. URL directa a ficha: /wp-admin/admin.php?page=automatiza-crm-ficha&ai_id=cliente_petsgo" . PHP_EOL;
echo PHP_EOL . "✅ MIGRACIÓN COMPLETADA" . PHP_EOL;
