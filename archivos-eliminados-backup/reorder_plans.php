<?php
$conn = new mysqli('localhost', 'root', '', 'automatiza_tech_local');
if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

echo "=== REORDENANDO PLANES ===\n";

// Actualizar el orden de los planes
// Plan Básico = orden 1 (izquierda)
$result1 = $conn->query("UPDATE wp_automatiza_services SET service_order = 1 WHERE name LIKE '%Básico%' AND category = 'pricing'");
echo "Plan Básico actualizado a orden 1: " . ($result1 ? "✓" : "✗") . "\n";

// Plan Profesional = orden 2 (centro)  
$result2 = $conn->query("UPDATE wp_automatiza_services SET service_order = 2 WHERE name LIKE '%Profesional%' AND category = 'pricing'");
echo "Plan Profesional actualizado a orden 2: " . ($result2 ? "✓" : "✗") . "\n";

// Plan Enterprise = orden 3 (derecha)
$result3 = $conn->query("UPDATE wp_automatiza_services SET service_order = 3 WHERE name LIKE '%Enterprise%' AND category = 'pricing'");
echo "Plan Enterprise actualizado a orden 3: " . ($result3 ? "✓" : "✗") . "\n";

echo "\n=== VERIFICANDO NUEVO ORDEN ===\n";
$result = $conn->query('SELECT id, name, service_order FROM wp_automatiza_services WHERE category = "pricing" ORDER BY service_order ASC');
while($row = $result->fetch_assoc()) {
    echo "Orden " . $row['service_order'] . ": " . $row['name'] . " (ID: " . $row['id'] . ")\n";
}

$conn->close();
echo "\n¡Planes reordenados exitosamente!\n";
?>