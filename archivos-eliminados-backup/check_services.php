<?php
$conn = new mysqli('localhost', 'root', '', 'automatiza_tech_local');
if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

echo "=== SERVICIOS ACTUALES ===\n";
$result = $conn->query('SELECT * FROM wp_automatiza_services ORDER BY service_order ASC');
while($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n";
    echo "Nombre: " . $row['name'] . "\n";
    echo "Categoría: " . $row['category'] . "\n";
    echo "Precio USD: $" . $row['price_usd'] . "\n";
    echo "Estado: " . $row['status'] . "\n";
    echo "Descripción: " . substr($row['description'], 0, 100) . "...\n";
    echo "---\n";
}

$conn->close();
?>