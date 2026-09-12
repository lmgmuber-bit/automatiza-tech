<?php
/**
 * La bitácora de correos de una fiesta y las reglas de reenvío.
 *
 * Lo que hay que vigilar acá es que el panel del admin no mienta: un intento que falló no
 * puede contar como enviado, y los dos correos que rotan enlace tienen que dejar realmente
 * muerto el anterior. Un reenvío que dijera "listo" mientras el papá sigue sin poder abrir
 * nada es peor que no tener el botón.
 *
 * No se manda ningún correo: se prueban el registro, la lectura y la rotación de tokens.
 */

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/public/lib.php';
require_once $raiz . '/public/lib.envios.php';
require_once $raiz . '/public/lib.acceptance.php';

$fallos = 0;
$total = 0;
function ok(string $que, bool $cond): void
{
    global $fallos, $total;
    $total++;
    if (!$cond) { $fallos++; echo "  FALLA: $que\n"; }
}

echo "Bitácora de correos\n";

$slug = 'prueba-envios-' . bin2hex(random_bytes(3));
$pdo = cb_pdo();

// ---------- Los cuatro tipos ----------
$tipos = cb_envio_tipos();
ok('son los cuatro correos de la fiesta', array_keys($tipos) === ['firma', 'manual', 'boleta', 'terminos']);
ok('tres llevan PDF y el de la firma no', !$tipos['firma']['pdf'] && $tipos['manual']['pdf'] && $tipos['boleta']['pdf'] && $tipos['terminos']['pdf']);
ok('los que rotan enlace están marcados', $tipos['firma']['rota_enlace'] && $tipos['terminos']['rota_enlace']
    && !$tipos['manual']['rota_enlace'] && !$tipos['boleta']['rota_enlace']);

// ---------- Una fiesta sin nada enviado ----------
$estado = cb_envios_de_fiesta($slug);
ok('una fiesta sin envíos igual trae las cuatro filas', count($estado) === 4);
ok('y todas dicen que no se ha enviado nada',
    $estado['manual']['ultimo'] === null && $estado['manual']['veces'] === 0 && $estado['manual']['fallidos'] === 0);

// ---------- Registrar ----------
cb_envio_registrar($slug, 'manual', 'papa@ejemplo.cl', true, true, '', ['pin' => '4321', 'invitacion' => 'https://cumpleclick.com/app/x-abc']);
$estado = cb_envios_de_fiesta($slug);
ok('queda registrado el envío', $estado['manual']['ultimo'] !== null && $estado['manual']['veces'] === 1);
ok('con el destinatario', (string) $estado['manual']['ultimo']['destinatario'] === 'papa@ejemplo.cl');
ok('y marcado como que lleva PDF', (int) $estado['manual']['ultimo']['con_pdf'] === 1);
ok('las opciones vuelven para que el reenvío repita lo mismo',
    cb_envio_opciones_previas($estado, 'manual') === ['pin' => '4321', 'invitacion' => 'https://cumpleclick.com/app/x-abc']);

// ---------- Un intento fallido no es un envío ----------
cb_envio_registrar($slug, 'manual', 'papa@ejemplo.cl', true, false, 'SMTP rechazó');
$estado = cb_envios_de_fiesta($slug);
ok('un intento fallido NO cuenta como enviado', $estado['manual']['veces'] === 1);
ok('pero queda contado aparte para poder avisarlo', $estado['manual']['fallidos'] === 1);

// El segundo envío bueno sí suma, y el último es el que manda.
cb_envio_registrar($slug, 'manual', 'mama@ejemplo.cl', true, true);
$estado = cb_envios_de_fiesta($slug);
ok('el reenvío suma y no reemplaza el historial', $estado['manual']['veces'] === 2);
ok('el último es el que se muestra', (string) $estado['manual']['ultimo']['destinatario'] === 'mama@ejemplo.cl');
ok('los otros tipos siguen vacíos', $estado['boleta']['ultimo'] === null && $estado['firma']['ultimo'] === null);

// ---------- Errores claros cuando no hay nada que reenviar ----------
$r = cb_envio_reenviar_firma($slug);
ok('reenviar la firma sin enlace pendiente avisa en vez de fallar en silencio',
    $r['ok'] === false && str_contains($r['mensaje'], 'Aceptaciones'));
$r = cb_envio_reenviar_terminos($slug);
ok('reenviar los Términos sin firma avisa', $r['ok'] === false && str_contains($r['mensaje'], 'firmados'));
$r = cb_envio_manual($slug, []);
ok('el manual de una fiesta que no existe avisa', $r['ok'] === false && str_contains($r['mensaje'], 'no existe'));

// ---------- Rotación de tokens sobre una aceptación de verdad ----------
$fiestas = cb_load_parties()['parties'] ?? [];
$slugReal = '';
foreach ($fiestas as $s => $f) { $slugReal = (string) $s; break; }
if ($slugReal === '') {
    echo "  (sin fiestas en la base: no se prueba la rotación de tokens)\n";
} else {
    $party = $fiestas[$slugReal] + ['public_slug' => $slugReal];
    $creada = cb_create_plan_acceptance($party, [
        'client_name' => 'Prueba Envios', 'client_email' => 'prueba-envios@ejemplo.cl',
        'client_phone' => '', 'expires_days' => 14,
        'summary' => ['plan_name' => 'Plan Premium', 'price_total' => '$49.995', 'event_date' => '2026-09-13'],
    ], 'prueba');
    ok('se creó una aceptación de prueba', !empty($creada['ok']));
    if (!empty($creada['ok'])) {
        $id = (int) $creada['id'];
        $tokenViejo = (string) $creada['token'];
        ok('el enlace original resuelve a esa aceptación',
            (cb_load_acceptance_by_token_hash(cb_hash_token($tokenViejo))['id'] ?? 0) === $id);

        $pendiente = cb_acceptance_pendiente_de_fiesta($slugReal);
        ok('la fiesta muestra la firma como pendiente', ($pendiente['id'] ?? 0) === $id);

        $urlNueva = cb_acceptance_rotar_token($id);
        ok('rotar devuelve una URL de aceptación', is_string($urlNueva) && str_contains((string) $urlNueva, 'aceptar-plan.php?t='));
        // Esto es lo que de verdad importa: el enlace que el papá ya tenía deja de servir.
        ok('el enlace ANTERIOR deja de funcionar', cb_load_acceptance_by_token_hash(cb_hash_token($tokenViejo)) === null);
        parse_str((string) parse_url((string) $urlNueva, PHP_URL_QUERY), $q);
        ok('y el nuevo sí funciona', (cb_load_acceptance_by_token_hash(cb_hash_token((string) ($q['t'] ?? '')))['id'] ?? 0) === $id);

        // Una fila que no está pendiente no se puede rotar: rotar el enlace de una firma ya
        // hecha reabriría un documento cerrado.
        $pdo->prepare('UPDATE cc_plan_acceptances SET status = ? WHERE id = ?')->execute(['revoked', $id]);
        ok('una aceptación revocada no se puede reenviar', cb_acceptance_rotar_token($id) === null);
        ok('ni aparece como pendiente de la fiesta', (cb_acceptance_pendiente_de_fiesta($slugReal)['id'] ?? 0) !== $id);

        $pdo->prepare('DELETE FROM cc_plan_acceptances WHERE id = ?')->execute([$id]);
    }
}

// Limpieza: la bitácora de prueba no tiene por qué quedar en la base.
$pdo->prepare('DELETE FROM cc_envios WHERE party_slug = ?')->execute([$slug]);

echo $fallos === 0
    ? "  $total comprobaciones, todas bien\n"
    : "  $total comprobaciones, $fallos con problemas\n";
exit($fallos === 0 ? 0 : 1);
