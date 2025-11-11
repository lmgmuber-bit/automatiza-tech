<?php
/**
 * Limpiar contactos de prueba
 */

// Cargar WordPress
require_once('wp-load.php');

// Verificar que sea administrador
if (!current_user_can('administrator')) {
    die('⛔ Solo administradores pueden ejecutar este script');
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_tech_contacts';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpiar Contactos de Prueba</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #667eea; margin-bottom: 20px; }
        .status-box {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid;
        }
        .success { background: #e8f5e9; border-color: #4caf50; }
        .warning { background: #fff3e0; border-color: #ff9800; }
        .info { background: #e3f2fd; border-color: #2196f3; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th { background: #667eea; color: white; }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
        }
        .button.danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Limpieza de Contactos de Prueba</h1>
        
        <?php
        // Obtener contactos de prueba
        $test_contacts = $wpdb->get_results("
            SELECT * FROM $table_name 
            WHERE email LIKE '%@test.com' 
            OR email LIKE '%prueba%'
            OR name LIKE '%prueba%'
            OR name LIKE '%test%'
        ");
        
        // Si se solicita eliminar
        if (isset($_POST['delete_test_contacts'])) {
            $deleted = $wpdb->query("
                DELETE FROM $table_name 
                WHERE email LIKE '%@test.com' 
                OR email LIKE '%prueba%'
                OR name LIKE '%prueba%'
                OR name LIKE '%test%'
            ");
            
            echo '<div class="status-box success">
                    <h3>✅ Contactos de Prueba Eliminados</h3>
                    <p><strong>' . $deleted . '</strong> contactos eliminados exitosamente.</p>
                  </div>';
            
            // Recargar lista
            $test_contacts = $wpdb->get_results("
                SELECT * FROM $table_name 
                WHERE email LIKE '%@test.com' 
                OR email LIKE '%prueba%'
                OR name LIKE '%prueba%'
                OR name LIKE '%test%'
            ");
        }
        
        if (empty($test_contacts)) {
            echo '<div class="status-box success">
                    <h3>✅ No hay contactos de prueba</h3>
                    <p>La base de datos está limpia y lista para producción.</p>
                  </div>';
        } else {
            echo '<div class="status-box warning">
                    <h3>⚠️ Se encontraron ' . count($test_contacts) . ' contactos de prueba</h3>
                    <p>Estos contactos tienen emails de prueba y deben ser eliminados antes de usar el sistema en producción.</p>
                  </div>';
            
            echo '<h3 style="color: #667eea; margin-top: 20px;">📋 Contactos de Prueba Detectados:</h3>';
            echo '<table>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                    </tr>';
            
            foreach ($test_contacts as $contact) {
                echo '<tr>
                        <td>' . $contact->id . '</td>
                        <td>' . esc_html($contact->name) . '</td>
                        <td>' . esc_html($contact->email) . '</td>
                        <td>' . $contact->status . '</td>
                        <td>' . date('d/m/Y', strtotime($contact->submitted_at)) . '</td>
                      </tr>';
            }
            
            echo '</table>';
            
            echo '<form method="post" onsubmit="return confirm(\'¿Estás seguro de eliminar ' . count($test_contacts) . ' contactos de prueba? Esta acción no se puede deshacer.\');">
                    <button type="submit" name="delete_test_contacts" class="button danger">
                        🗑️ Eliminar Todos los Contactos de Prueba
                    </button>
                  </form>';
        }
        
        // Mostrar contactos reales
        $real_contacts = $wpdb->get_results("
            SELECT * FROM $table_name 
            WHERE email NOT LIKE '%@test.com' 
            AND email NOT LIKE '%prueba%'
            AND name NOT LIKE '%prueba%'
            AND name NOT LIKE '%test%'
        ");
        
        echo '<div class="status-box info" style="margin-top: 30px;">
                <h3>📊 Contactos Reales en el Sistema: ' . count($real_contacts) . '</h3>';
        
        if (empty($real_contacts)) {
            echo '<p>No hay contactos reales aún. Cuando lleguen contactos desde tu formulario, aparecerán aquí.</p>';
        } else {
            echo '<p>Estos son contactos legítimos que pueden recibir correos.</p>';
            echo '<h4 style="margin-top: 15px;">Contactos por Estado:</h4><ul>';
            
            $status_counts = array(
                'new' => 0,
                'contacted' => 0,
                'follow_up' => 0,
                'interested' => 0,
                'not_interested' => 0,
                'contracted' => 0,
                'closed' => 0
            );
            
            foreach ($real_contacts as $contact) {
                if (isset($status_counts[$contact->status])) {
                    $status_counts[$contact->status]++;
                }
            }
            
            $status_names = array(
                'new' => '🆕 Nuevos',
                'contacted' => '📞 Contactados',
                'follow_up' => '📅 Seguimiento',
                'interested' => '💜 Interesados',
                'not_interested' => '👎 No Interesados',
                'contracted' => '✅ Contratados',
                'closed' => '🔒 Cerrados'
            );
            
            foreach ($status_counts as $status => $count) {
                if ($count > 0) {
                    echo '<li>' . $status_names[$status] . ': <strong>' . $count . '</strong></li>';
                }
            }
            
            echo '</ul>';
        }
        
        echo '</div>';
        ?>
        
        <div style="margin-top: 40px; padding: 20px; background: #f8f9ff; border-radius: 10px; text-align: center;">
            <h3 style="color: #667eea; margin-bottom: 10px;">✨ Próximos Pasos</h3>
            <p style="margin-bottom: 15px;">Una vez limpia la base de datos:</p>
            <a href="<?php echo admin_url('admin.php?page=automatiza-tech-contacts'); ?>" class="button">
                📧 Ir al Panel de Contactos
            </a>
        </div>
        
        <div style="margin-top: 20px; text-align: center; color: #999; font-size: 12px;">
            <p>⚠️ Elimina este archivo (clean-test-contacts.php) después de usarlo por seguridad</p>
        </div>
    </div>
</body>
</html>
