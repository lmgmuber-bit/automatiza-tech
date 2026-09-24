<?php
/**
 * Bitácora de los correos que salen desde el admin.
 *
 * Hasta ahora, mandar un correo dejaba un mensaje en pantalla y nada más: al día siguiente
 * no había forma de saber si el manual de una fiesta ya se había enviado. Con dos fiestas
 * reales se aguanta de memoria; con seis, no.
 *
 * Se guarda **cada envío**, no el último por tipo. Un reenvío es un hecho distinto del
 * primer envío —sobre todo el de la firma, que además anula el enlace anterior— y saber
 * cuántas veces se mandó algo es justo lo que uno quiere cuando el papá dice "no me llegó".
 * El último se calcula al leer.
 *
 * `destinatario` es el correo del papá: dato personal, y por eso esta tabla queda sujeta a
 * los mismos plazos de conservación que el resto de los datos operativos del evento.
 *
 * La fiesta se referencia por `party_slug` y no por `party_id` a propósito: los envíos de
 * firma existen antes de que la ficha tenga cobro cargado, y el slug es lo que comparten
 * todas las pantallas del admin. Los slugs solo se cambian con script, que ya reescribe las
 * tablas que dependen de ellos.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    if ($mysql) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_envios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                party_slug VARCHAR(80) NOT NULL,
                tipo VARCHAR(24) NOT NULL,
                destinatario VARCHAR(190) NOT NULL,
                con_pdf TINYINT(1) NOT NULL DEFAULT 0,
                ok TINYINT(1) NOT NULL DEFAULT 1,
                detalle VARCHAR(255) NOT NULL DEFAULT \'\',
                opciones TEXT NULL,
                enviado_por VARCHAR(60) NOT NULL DEFAULT \'\',
                enviado_at DATETIME NOT NULL,
                INDEX idx_envios_fiesta (party_slug, tipo),
                INDEX idx_envios_fecha (enviado_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cc_envios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            party_slug TEXT NOT NULL,
            tipo TEXT NOT NULL,
            destinatario TEXT NOT NULL,
            con_pdf INTEGER NOT NULL DEFAULT 0,
            ok INTEGER NOT NULL DEFAULT 1,
            detalle TEXT NOT NULL DEFAULT \'\',
            opciones TEXT NULL,
            enviado_por TEXT NOT NULL DEFAULT \'\',
            enviado_at TEXT NOT NULL
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_envios_fiesta ON cc_envios (party_slug, tipo)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_envios_fecha ON cc_envios (enviado_at)');
};
