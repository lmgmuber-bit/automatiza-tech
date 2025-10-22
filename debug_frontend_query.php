<?php
// Simular el mismo código que está en el frontend
$wpdb = new mysqli('localhost', 'root', '', 'automatiza_tech_local');
if ($wpdb->connect_error) {
    die('Error de conexión: ' . $wpdb->connect_error);
}

echo "=== SIMULANDO CONSULTA DEL FRONTEND ===\n";
$result = $wpdb->query("SELECT * FROM wp_automatiza_services WHERE category = 'pricing' AND status = 'active' ORDER BY service_order ASC, name ASC");

if ($result) {
    $services = $wpdb->query("SELECT * FROM wp_automatiza_services WHERE category = 'pricing' AND status = 'active' ORDER BY service_order ASC, name ASC");
    $data = [];
    $result = $wpdb->query("SELECT * FROM wp_automatiza_services WHERE category = 'pricing' AND status = 'active' ORDER BY service_order ASC, name ASC");
    
    // Usar get_result en lugar de query
    $result = $wpdb->query("SELECT id, name, card_color, button_color, text_color, button_text, description, features, icon, price_usd, highlight FROM wp_automatiza_services WHERE category = 'pricing' AND status = 'active' ORDER BY service_order ASC, name ASC");
    
    if ($result) {
        $result_data = $wpdb->query("SELECT id, name, card_color, button_color, text_color, button_text, description, features, icon, price_usd, highlight FROM wp_automatiza_services WHERE category = 'pricing' AND status = 'active' ORDER BY service_order ASC, name ASC");
        
        // Usar mysqli correctamente
        $stmt = $wpdb->prepare("SELECT id, name, card_color, button_color, text_color, button_text, description, features, icon, price_usd, highlight FROM wp_automatiza_services WHERE category = ? AND status = ? ORDER BY service_order ASC, name ASC");
        $stmt->bind_param("ss", $category, $status);
        $category = 'pricing';
        $status = 'active';
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            echo "Plan: " . $row['name'] . "\n";
            echo "Color tarjeta: " . ($row['card_color'] ?: 'NULL') . "\n";
            echo "Color botón: " . ($row['button_color'] ?: 'NULL') . "\n";
            echo "---\n";
        }
    }
} else {
    echo "Error en la consulta: " . $wpdb->error . "\n";
}

$wpdb->close();
?>