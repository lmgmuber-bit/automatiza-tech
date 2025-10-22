<?php
// Conexión a la base de datos de WordPress
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "automatiza_tech_local";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Conexión exitosa a la base de datos</h1>";
    
    // Verificar si la tabla de contactos existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'wp_automatiza_tech_contacts'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ La tabla wp_automatiza_tech_contacts existe</p>";
        
        // Mostrar la estructura de la tabla
        $stmt = $pdo->query("DESCRIBE wp_automatiza_tech_contacts");
        echo "<h3>Estructura de la tabla wp_automatiza_tech_contacts:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Mostrar contactos existentes
        $stmt = $pdo->query("SELECT * FROM wp_automatiza_tech_contacts ORDER BY submitted_at DESC");
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Contactos existentes (" . count($contacts) . "):</h3>";
        if (count($contacts) > 0) {
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Empresa</th><th>Teléfono</th><th>Mensaje</th><th>Fecha</th><th>Estado</th></tr>";
            foreach ($contacts as $contact) {
                echo "<tr>";
                echo "<td>" . $contact['id'] . "</td>";
                echo "<td>" . $contact['name'] . "</td>";
                echo "<td>" . $contact['email'] . "</td>";
                echo "<td>" . $contact['company'] . "</td>";
                echo "<td>" . $contact['phone'] . "</td>";
                echo "<td>" . substr($contact['message'], 0, 50) . "...</td>";
                echo "<td>" . $contact['submitted_at'] . "</td>";
                echo "<td>" . $contact['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No hay contactos registrados aún.</p>";
        }
        
    } else {
        echo "<p>❌ La tabla wp_automatiza_tech_contacts NO existe</p>";
        echo "<p>Creando la tabla...</p>";
        
        $sql = "CREATE TABLE wp_automatiza_tech_contacts (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            company varchar(100) DEFAULT NULL,
            phone varchar(20) DEFAULT NULL,
            message text NOT NULL,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'nuevo',
            notes text DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p>✅ Tabla creada exitosamente</p>";
    }
    
    // Verificar configuración de WordPress
    echo "<h3>Verificación de WordPress:</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wp_options'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ WordPress está instalado correctamente</p>";
        
        $stmt = $pdo->prepare("SELECT option_value FROM wp_options WHERE option_name = 'siteurl'");
        $stmt->execute();
        $siteurl = $stmt->fetchColumn();
        echo "<p>URL del sitio: " . $siteurl . "</p>";
        
        $stmt = $pdo->prepare("SELECT option_value FROM wp_options WHERE option_name = 'template'");
        $stmt->execute();
        $template = $stmt->fetchColumn();
        echo "<p>Tema activo: " . $template . "</p>";
    } else {
        echo "<p>❌ WordPress no está instalado o la base de datos no es correcta</p>";
    }
    
} catch(PDOException $e) {
    echo "<h1>Error de conexión</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    h1 { color: #333; }
    h3 { color: #666; }
    p { margin: 10px 0; }
</style>