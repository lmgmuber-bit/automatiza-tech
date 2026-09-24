<?php
/** Valores de formularios: fecha ISO existente y hora de reloj de 24 horas. */
function cb_fecha_valida(string $value): bool
{
    if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts) !== 1) {
        return false;
    }
    return (int) $parts[1] >= 1000 && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
}
function cb_hora_valida(string $value): bool
{
    return preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $value) === 1;
}
