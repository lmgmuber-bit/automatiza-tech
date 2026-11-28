<?php
/** La 022 debe preservar datos y ser idempotente en SQLite y MySQL. */
if (PHP_SAPI !== 'cli') { exit(2); }
$mysql = in_array('--mysql', $argv, true);
if ($mysql) {
    // Únicamente la instancia desechable de QA; nunca el MySQL habitual de WAMP.
    $server = new PDO('mysql:host=127.0.0.1;port=33387;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db = 'cc_m022_test_' . bin2hex(random_bytes(6));
    $server->exec("CREATE DATABASE $db CHARACTER SET utf8mb4");
    register_shutdown_function(static fn() => $server->exec("DROP DATABASE $db"));
    $pdo = new PDO("mysql:host=127.0.0.1;port=33387;dbname=$db;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} else {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
$pdo->exec('CREATE TABLE cc_parties (id INTEGER PRIMARY KEY)');
$pdo->exec('INSERT INTO cc_parties(id) VALUES(1)');
$migration = require dirname(__DIR__,2) . '/database/migrations/022_juegos3d.php';
$migration($pdo);
if ((int)$pdo->query('SELECT games3d_enabled FROM cc_parties WHERE id=1')->fetchColumn() !== 1) { throw new RuntimeException('FAIL: default'); }
$pdo->exec('UPDATE cc_parties SET games3d_enabled=0 WHERE id=1');
try { $migration($pdo); } catch (Throwable $e) { echo "FAIL: segunda aplicación: ".$e->getMessage()."\n"; exit(1); }
if ((int)$pdo->query('SELECT games3d_enabled FROM cc_parties WHERE id=1')->fetchColumn() !== 0) { throw new RuntimeException('FAIL: conserva apagado'); }
echo "migracion-022 " . ($mysql ? "MySQL" : "SQLite") . ": 3 comprobaciones, 0 fallos\n";
