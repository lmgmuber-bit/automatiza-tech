<?php
/** Deshace 023: borra los usuarios del backoffice y sus fiestas. La clave maestra no se toca. */
return static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS cc_admin_user_parties');
    $pdo->exec('DROP TABLE IF EXISTS cc_admin_users');
};
