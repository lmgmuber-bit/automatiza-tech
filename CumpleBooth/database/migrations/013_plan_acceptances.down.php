<?php
/** Revierte 013. Borra la tabla de aceptaciones; los archivos privados de evidencia no se tocan. */
return static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS cc_plan_acceptances');
};
