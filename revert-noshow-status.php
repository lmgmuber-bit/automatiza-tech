<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para revertir el estado "No Asistió" de una cita
 * Esto permite volver a marcarla desde la grilla para que envíe el correo
 */

require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('Sin permisos - Debes estar logueado como administrador');
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

echo "<h1>Revertir Estado 'No Asistió'</h1>";

// Buscar por email específico (Anamaría Sandoval)
$email_buscar = isset($_GET['email']) ? sanitize_email($_GET['email']) : 'sandovalrodriguez@gmail.com';
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Si no hay ID, buscar por email
if ($appointment_id > 0) {
    $appointment = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, email, phone, appointment_date, appointment_time, attendance_status, no_show_email_sent, confirmed_attendance FROM $table_name WHERE id = %d",
        $appointment_id
    ));
} else {
    // Buscar por email
    $appointment = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, email, phone, appointment_date, appointment_time, attendance_status, no_show_email_sent, confirmed_attendance FROM $table_name WHERE email = %s ORDER BY id DESC LIMIT 1",
        $email_buscar
    ));
}

// Si aún no encontramos, listar todas las citas con estado no_show
if (!$appointment) {
    echo "<p style='color:orange;'>⚠️ No se encontró cita con email: $email_buscar</p>";
    echo "<h2>Citas con estado 'No Asistió' o recientes:</h2>";
    
    $all_appointments = $wpdb->get_results(
        "SELECT id, name, email, phone, appointment_date, appointment_time, attendance_status, no_show_email_sent 
         FROM $table_name 
         WHERE attendance_status IS NOT NULL OR appointment_date >= '2026-01-01'
         ORDER BY id DESC 
         LIMIT 20"
    );
    
    if ($all_appointments) {
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
        echo "<tr style='background:#333;'><th>ID</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Fecha</th><th>Estado</th><th>Email Enviado</th><th>Acción</th></tr>";
        foreach ($all_appointments as $apt) {
            $status_color = $apt->attendance_status == 'no_show' ? 'red' : ($apt->attendance_status == 'attended' ? 'green' : 'gray');
            echo "<tr>";
            echo "<td><strong>{$apt->id}</strong></td>";
            echo "<td>" . esc_html($apt->name) . "</td>";
            echo "<td>" . esc_html($apt->email) . "</td>";
            echo "<td>" . esc_html($apt->phone) . "</td>";
            echo "<td>{$apt->appointment_date} {$apt->appointment_time}</td>";
            echo "<td style='color:$status_color;'>" . esc_html($apt->attendance_status ?: '-') . "</td>";
            echo "<td>" . ($apt->no_show_email_sent ? '✅ ' . $apt->no_show_email_sent : '-') . "</td>";
            echo "<td><a href='?id={$apt->id}' style='background:#007bff;color:white;padding:5px 10px;text-decoration:none;border-radius:3px;'>Seleccionar</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No se encontraron citas.</p>";
    }
    
    echo "<br><hr>";
    echo "<p><a href='" . admin_url('admin.php?page=automatiza-reminders') . "'>← Volver a Gestión de Citas</a></p>";
    exit;
}

echo "<h2>Datos actuales de la cita #$appointment_id:</h2>";
echo "<ul>";
echo "<li><strong>Nombre:</strong> " . esc_html($appointment->name) . "</li>";
echo "<li><strong>Email:</strong> " . esc_html($appointment->email) . "</li>";
echo "<li><strong>Estado asistencia:</strong> " . esc_html($appointment->attendance_status ?: 'NULL') . "</li>";
echo "<li><strong>Asistencia confirmada:</strong> " . esc_html($appointment->confirmed_attendance ?: 'NULL') . "</li>";
echo "<li><strong>Email no_show enviado:</strong> " . esc_html($appointment->no_show_email_sent ?: 'NULL') . "</li>";
echo "</ul>";

// Si se confirma la acción
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Revertir el estado: quitar attendance_status y no_show_email_sent
    $result = $wpdb->update(
        $table_name,
        array(
            'attendance_status' => null,
            'no_show_email_sent' => null,
            'confirmed_attendance' => null
        ),
        array('id' => $appointment_id),
        array('%s', '%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        echo "<p style='color:green; font-size:18px;'>✅ Estado revertido correctamente.</p>";
        echo "<p>Ahora puedes marcar 'No Asistió' desde la grilla para que envíe el correo.</p>";
        
        // Mostrar datos actualizados
        $updated = $wpdb->get_row($wpdb->prepare(
            "SELECT attendance_status, no_show_email_sent, confirmed_attendance FROM $table_name WHERE id = %d",
            $appointment_id
        ));
        echo "<h3>Nuevos valores:</h3>";
        echo "<ul>";
        echo "<li><strong>Estado asistencia:</strong> " . esc_html($updated->attendance_status ?: 'NULL') . "</li>";
        echo "<li><strong>Email no_show enviado:</strong> " . esc_html($updated->no_show_email_sent ?: 'NULL') . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color:red;'>❌ Error al actualizar: " . $wpdb->last_error . "</p>";
    }
} else {
    // Mostrar botón de confirmación
    echo "<br><br>";
    echo "<a href='?id=$appointment_id&confirm=yes' style='background:#dc3545; color:white; padding:15px 30px; text-decoration:none; border-radius:5px; font-size:16px;'>🔄 Confirmar Revertir Estado</a>";
    echo "<br><br><br>";
    echo "<p><em>Esto eliminará el estado 'No Asistió' y el registro del email enviado, permitiéndote marcarlo nuevamente desde la grilla.</em></p>";
}

echo "<br><hr>";
echo "<p><a href='" . admin_url('admin.php?page=automatiza-reminders') . "'>← Volver a Gestión de Citas</a></p>";
?>
