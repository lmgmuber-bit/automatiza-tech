<?php
/**
 * Usuarios del backoffice: superadministrador y operadores por fiesta (2026-09-13).
 *
 * `cc_admin_users` es la tabla maestra de quienes entran al admin con correo y contraseña.
 * `cc_admin_user_parties` dice qué fiestas ve cada uno. Los módulos permitidos van como JSON en
 * la misma fila: son pocos y cambian con el producto, una tabla aparte no sumaba nada.
 * La clave maestra de siempre no vive acá: sigue en la configuración del servidor.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $options = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_admin_users (
        id $id,
        email VARCHAR(190) NOT NULL UNIQUE,
        nombre VARCHAR(80) NOT NULL DEFAULT '',
        password_hash VARCHAR(255) NOT NULL,
        rol VARCHAR(16) NOT NULL DEFAULT 'operador',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        debe_cambiar TINYINT(1) NOT NULL DEFAULT 1,
        modulos TEXT NOT NULL,
        creado_por BIGINT UNSIGNED NULL,
        ultimo_acceso_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )$options");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_admin_user_parties (
        user_id BIGINT UNSIGNED NOT NULL,
        party_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (user_id, party_id),
        CONSTRAINT fk_admin_user_parties_user FOREIGN KEY (user_id) REFERENCES cc_admin_users(id) ON DELETE CASCADE,
        CONSTRAINT fk_admin_user_parties_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE CASCADE
    )$options");
};
