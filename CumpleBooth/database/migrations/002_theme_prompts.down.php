<?php
return static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS cc_theme_prompts');
};
