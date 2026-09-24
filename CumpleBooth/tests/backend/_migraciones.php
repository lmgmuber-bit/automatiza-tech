<?php
/**
 * Aplica todas las migraciones sobre la base de la prueba, en orden.
 *
 * Cada prueba traía su propia lista escrita a mano, y esa lista se quedaba atrás cada vez que
 * alguien agregaba una migración. La 019 agregó una columna que el código ya escribía y dejó
 * seis pruebas fallando por tener el esquema viejo, no por un error de verdad: el peor tipo
 * de rojo, porque enseña a ignorar el rojo.
 *
 * Se pueden aplicar todas sin cuidado porque las migraciones están escritas para tolerarlo:
 * crean con `IF NOT EXISTS` y comprueban que la columna no exista antes de agregarla. Si
 * alguna nueva no lo hiciera, esta función la delataría de inmediato.
 */
function cb_test_migrar_todo(PDO $pdo): void
{
    $archivos = glob(dirname(__DIR__, 2) . '/database/migrations/*.php') ?: [];
    sort($archivos);   // 001, 002, ... 020: el orden importa y glob no lo garantiza en todos lados
    foreach ($archivos as $archivo) {
        if (str_ends_with($archivo, '.down.php')) {
            continue;
        }
        (require $archivo)($pdo);
    }
}
