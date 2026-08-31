<?php
return static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS cc_rate_limits');
    $pdo->exec('DROP TABLE IF EXISTS cc_photos');
    $pdo->exec('DROP TABLE IF EXISTS cc_guests');
    $pdo->exec('DROP TABLE IF EXISTS cc_parties');
};
