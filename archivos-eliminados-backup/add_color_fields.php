<?php
$conn = new mysqli('localhost', 'root', '', 'automatiza_tech_local');
if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

echo "=== AGREGANDO CAMPOS DE COLORES A LA TABLA ===\n";

// Agregar columnas para colores
$alterQueries = [
    "ALTER TABLE wp_automatiza_services ADD COLUMN card_color VARCHAR(7) DEFAULT '#007cba' AFTER icon",
    "ALTER TABLE wp_automatiza_services ADD COLUMN button_color VARCHAR(7) DEFAULT '#28a745' AFTER card_color",
    "ALTER TABLE wp_automatiza_services ADD COLUMN text_color VARCHAR(7) DEFAULT '#ffffff' AFTER button_color"
];

foreach ($alterQueries as $query) {
    $result = $conn->query($query);
    if ($result) {
        echo "✓ Campo agregado exitosamente\n";
    } else {
        // Si el campo ya existe, no es un error
        if (strpos($conn->error, 'Duplicate column name') !== false) {
            echo "✓ Campo ya existe\n";
        } else {
            echo "✗ Error: " . $conn->error . "\n";
        }
    }
}

echo "\n=== CONFIGURANDO COLORES POR DEFECTO PARA CADA PLAN ===\n";

// Configurar colores específicos para cada plan
$colorUpdates = [
    // Plan Básico - Azul claro
    "UPDATE wp_automatiza_services SET 
     card_color = '#3b82f6', 
     button_color = '#22c55e', 
     text_color = '#ffffff' 
     WHERE name LIKE '%Básico%' AND category = 'pricing'",
     
    // Plan Profesional - Azul oscuro (destacado)
    "UPDATE wp_automatiza_services SET 
     card_color = '#1d4ed8', 
     button_color = '#22c55e', 
     text_color = '#ffffff' 
     WHERE name LIKE '%Profesional%' AND category = 'pricing'",
     
    // Plan Enterprise - Verde
    "UPDATE wp_automatiza_services SET 
     card_color = '#059669', 
     button_color = '#22c55e', 
     text_color = '#ffffff' 
     WHERE name LIKE '%Enterprise%' AND category = 'pricing'"
];

foreach ($colorUpdates as $index => $query) {
    $result = $conn->query($query);
    $planNames = ['Básico', 'Profesional', 'Enterprise'];
    echo "Plan " . $planNames[$index] . ": " . ($result ? "✓" : "✗") . "\n";
}

echo "\n=== VERIFICANDO COLORES CONFIGURADOS ===\n";
$result = $conn->query('SELECT name, card_color, button_color, text_color FROM wp_automatiza_services WHERE category = "pricing" ORDER BY service_order ASC');
while($row = $result->fetch_assoc()) {
    echo "Plan: " . $row['name'] . "\n";
    echo "  Color tarjeta: " . $row['card_color'] . "\n";
    echo "  Color botón: " . $row['button_color'] . "\n";
    echo "  Color texto: " . $row['text_color'] . "\n";
    echo "---\n";
}

$conn->close();
echo "\n¡Campos de colores agregados exitosamente!\n";
?>