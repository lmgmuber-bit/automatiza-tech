<?php
$conn = new mysqli('localhost', 'root', '', 'automatiza_tech_local');
if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

echo "=== VERIFICANDO DATOS ACTUALES EN BD ===\n";
$result = $conn->query('SELECT id, name, card_color, button_color, text_color, button_text, description FROM wp_automatiza_services WHERE category = "pricing" ORDER BY service_order ASC');
while($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n";
    echo "Plan: " . $row['name'] . "\n";
    echo "Color tarjeta: " . $row['card_color'] . "\n";
    echo "Color botón: " . $row['button_color'] . "\n";
    echo "Color texto: " . $row['text_color'] . "\n";
    echo "Texto botón: " . $row['button_text'] . "\n";
    echo "Descripción: " . substr($row['description'], 0, 50) . "...\n";
    echo "---\n";
}

$conn->close();
?>