<?php
/**
 * 028: la confirmación del baby shower lleva regalo, equipo y foto de bebé.
 *
 * `cc_rsvps` gana tres columnas, todas NULL para no tocar las confirmaciones
 * que ya existen:
 *   - team           'mama' | 'papa' (se valida en código).
 *   - gift_item_id   el `cc_gift_items.id` que se creó al confirmar.
 *   - baby_photo_key ruta relativa de la foto de bebé (raíz privada de fotos).
 *
 * Aditiva e idempotente; corre igual en MySQL y en SQLite.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    $hasColumn = static function (string $table, string $column) use ($pdo, $mysql): bool {
        if ($mysql) {
            $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $stmt->execute([$table, $column]);
            return $stmt->fetch() !== false;
        }
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    };

    $columnas = [
        'team' => 'VARCHAR(8) NULL',
        'gift_item_id' => $mysql ? 'BIGINT UNSIGNED NULL' : 'INTEGER NULL',
        'baby_photo_key' => 'VARCHAR(190) NULL',
    ];
    foreach ($columnas as $nombre => $definicion) {
        if (!$hasColumn('cc_rsvps', $nombre)) {
            $pdo->exec("ALTER TABLE cc_rsvps ADD COLUMN $nombre $definicion");
        }
    }
};
