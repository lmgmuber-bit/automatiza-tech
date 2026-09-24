<?php
return static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS cc_sala_acciones');
    $pdo->exec('DROP TABLE IF EXISTS cc_sala_ayudantes');
    $pdo->exec('DROP TABLE IF EXISTS cc_salas');
};
