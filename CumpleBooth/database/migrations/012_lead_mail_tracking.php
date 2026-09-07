<?php
/**
 * 012: registro de los correos del formulario público, sobre `cc_leads`.
 *
 * Tres columnas, y ninguna es decorativa:
 *
 *   confirmation_sent_at — cuándo salió la confirmación al cliente.
 *   notified_at          — cuándo salió el aviso interno.
 *   mail_error           — por qué NO salió, si no salió.
 *
 * El motivo de guardar esto es que el envío falla en silencio a propósito: si
 * el SMTP no contesta, el lead igual se guarda y al visitante se le responde
 * que todo salió bien, porque su solicitud SÍ llegó y no tiene sentido
 * mostrarle un error por un problema nuestro. Pero entonces, sin registro, un
 * buzón mal configurado deja a todo el mundo sin confirmación y no se entera
 * nadie hasta que un cliente reclama. Con estas columnas, el admin puede
 * mostrar "no se envió la confirmación" al lado de la solicitud, y el error
 * concreto queda a mano para arreglarlo.
 *
 * `mail_error` guarda el mensaje del servidor SMTP, que es diagnóstico y no un
 * dato de la persona; no lleva credenciales porque el enviador nunca las
 * incluye en el texto del error.
 *
 * Aditiva e idempotente: no toca los leads que ya existen (quedan en NULL, que
 * se lee como "de antes de que hubiera correo").
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';

    $hasColumn = static function (PDO $pdo, string $table, string $column) use ($mysql): bool {
        try {
            if ($mysql) {
                $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
                $stmt->execute([$table, $column]);
                return $stmt->fetch() !== false;
            }
            $stmt = $pdo->prepare('PRAGMA table_info(' . $table . ')');
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        } catch (Throwable $e) {
            return false;
        }
    };

    foreach ([
        'confirmation_sent_at' => "$timestamp NULL",
        'notified_at' => "$timestamp NULL",
        'mail_error' => 'VARCHAR(255) NULL',
    ] as $columna => $tipo) {
        if (!$hasColumn($pdo, 'cc_leads', $columna)) {
            $pdo->exec("ALTER TABLE cc_leads ADD COLUMN $columna $tipo");
        }
    }
};
