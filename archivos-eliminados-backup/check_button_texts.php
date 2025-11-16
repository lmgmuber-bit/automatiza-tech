<?php
$conn = new mysqli('localhost', 'root', '', 'automatiza_tech_local');
if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

echo "=== TEXTOS DE BOTONES ACTUALES ===\n";
$result = $conn->query('SELECT id, name, button_text FROM wp_automatiza_services WHERE category = "pricing" ORDER BY service_order ASC');
while($row = $result->fetch_assoc()) {
    echo "Plan: " . $row['name'] . "\n";
    echo "Texto botón: " . ($row['button_text'] ?: 'NULL/Vacío') . "\n";
    echo "ID: " . $row['id'] . "\n";
    echo "---\n";
}

$conn->close();
?>