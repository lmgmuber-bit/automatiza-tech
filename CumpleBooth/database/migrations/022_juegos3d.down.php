<?php
return static function (PDO $pdo): void {
    $pdo->exec('ALTER TABLE cc_parties DROP COLUMN games3d_enabled');
};
