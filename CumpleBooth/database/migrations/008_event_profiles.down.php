<?php
/** Reversa de 008. No se ejecuta automáticamente y elimina solo el módulo nuevo. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $pdo->exec('DROP TABLE IF EXISTS cc_event_profile_generations');
    $pdo->exec('DROP TABLE IF EXISTS cc_event_profile_media');
    $pdo->exec('DROP TABLE IF EXISTS cc_event_profile_fields');
    $pdo->exec('DROP TABLE IF EXISTS cc_featured_people');
    $pdo->exec('DROP TABLE IF EXISTS cc_event_profile_sections');
    $pdo->exec('DROP TABLE IF EXISTS cc_event_profiles');

    $hasColumn = false;
    if ($mysql) {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name=? AND column_name=?'
        );
        $stmt->execute(['cc_parties', 'event_type']);
        $hasColumn = $stmt->fetch() !== false;
    } else {
        foreach ($pdo->query('PRAGMA table_info(cc_parties)')->fetchAll() as $row) {
            if (($row['name'] ?? '') === 'event_type') {
                $hasColumn = true;
                break;
            }
        }
    }
    if ($hasColumn) {
        $pdo->exec('ALTER TABLE cc_parties DROP COLUMN event_type');
    }
};
