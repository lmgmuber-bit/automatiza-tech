<?php
/** Reversión 020: elimina solo los datos del modo feria (las fiestas y sus fotos quedan). Respaldar antes en PROD. */
return static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS cc_feria_fotos');
    $pdo->exec('DROP TABLE IF EXISTS cc_ferias');
};
