<?php
$conn = new mysqli('localhost', 'root', '', 'automatiza_tech_local');
if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

echo "=== SIMULANDO CONSULTA EXACTA DEL FRONTEND ===\n";
$query = "SELECT * FROM wp_automatiza_services WHERE category = 'pricing' AND status = 'active' ORDER BY service_order ASC, name ASC";
echo "Consulta: $query\n\n";

$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    echo "Encontrados " . $result->num_rows . " registros:\n";
    while($row = $result->fetch_assoc()) {
        echo "Plan: " . $row['name'] . "\n";
        echo "Color tarjeta: " . ($row['card_color'] ?: 'NULL') . "\n";
        echo "Color botón: " . ($row['button_color'] ?: 'NULL') . "\n";
        echo "Color texto: " . ($row['text_color'] ?: 'NULL') . "\n";
        echo "Status: " . $row['status'] . "\n";
        echo "Category: " . $row['category'] . "\n";
        echo "---\n";
    }
} else {
    echo "No se encontraron registros o error: " . $conn->error . "\n";
}

$conn->close();
?>