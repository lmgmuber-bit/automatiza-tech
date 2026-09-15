<?php
/** Borra solo el estado de carreras. Exportarlo antes: los resultados históricos permanecen. */
return static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS cc_sala_carreras');
};
