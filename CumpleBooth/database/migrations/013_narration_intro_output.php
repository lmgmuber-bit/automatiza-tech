<?php
/**
 * 013: la narración de inicio como tipo de salida de invitación.
 *
 * El código trata `personalized_narration_intro` como salida de primera clase
 * desde 2026-08 (validación de subida, lector de aprobadas, reproductor en la
 * invitación), pero el ENUM de `cc_invitation_outputs.output_type` se quedó en
 * imagen/video. En MySQL sin modo estricto el INSERT no falla: guarda cadena
 * vacía, la fila queda huérfana y la narración simplemente no suena. Se
 * descubrió con la primera narración real (Samantha, 2026-09-02).
 *
 * Idempotente: si el ENUM ya incluye el valor, no toca nada. En SQLite el
 * tipo es texto libre y no hay nada que migrar.
 */
return static function (PDO $pdo): void {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        return;
    }
    $col = $pdo->query("SHOW COLUMNS FROM cc_invitation_outputs LIKE 'output_type'")->fetch();
    if ($col && strpos((string) $col['Type'], 'personalized_narration_intro') !== false) {
        return;
    }
    $pdo->exec(
        "ALTER TABLE cc_invitation_outputs MODIFY output_type
         ENUM('generic','personalized','personalized_image','personalized_video','personalized_narration_intro')
         NOT NULL DEFAULT 'personalized_image'"
    );
};
