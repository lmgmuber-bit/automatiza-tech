<?php
/**
 * La FOTO GRUPAL: que el kiosco reciba el modo solo cuando de verdad puede usarlo.
 *
 * Lo que se vigila aca no es que "ande": es que APAGUE bien. Este modo se enciende a partir
 * de datos de `themes.json` y de un archivo en disco, y las dos cosas pueden faltar en un
 * despliegue a medias. Si el bloque llegara con un `frameBox` invalido, la foto del grupo
 * saldria pegada a un borde del fondo, y eso en plena fiesta no se arregla.
 *
 * Por eso cada caso malo tiene que devolver null —el boton no aparece— y no un bloque a medias.
 */

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/public/lib.php';

$fallos = 0;
$total = 0;
function ok(string $que, bool $cond): void
{
    global $fallos, $total;
    $total++;
    if (!$cond) {
        $fallos++;
        echo "  FALLA: $que\n";
    }
}

echo "Foto grupal\n";

$json = cb_load_themes();
$temas = $json['themes'] ?? $json;

// ── Las dos temáticas que ya tienen el fondo en disco ────────────────────────
foreach (['hielo', 'spidey'] as $slug) {
    $payload = cb_build_theme_payload($slug, $temas[$slug] ?? []);
    $g = $payload['grupal'] ?? null;
    ok("$slug publica el bloque grupal", is_array($g));
    if (!is_array($g)) {
        continue;
    }
    ok("$slug apunta al fondo de la temática", str_ends_with((string) $g['fondo'], 'fondo-grupal.jpg'));
    $caja = $g['frameBox'];
    ok("$slug: el recuadro entra en el lienzo",
        $caja['x'] >= 0 && $caja['y'] >= 0
        && $caja['x'] + $caja['w'] <= 1 && $caja['y'] + $caja['h'] <= 1);
    // Lo que hace util este modo: el hueco es APAISADO y grande. Un hueco cuadrado o chico
    // convierte a doce personas en una cara, que es justo lo que ya hace el modo de siempre.
    ok("$slug: el hueco es apaisado", $caja['w'] > $caja['h']);
    ok("$slug: el hueco es grande (más del 60% del ancho)", $caja['w'] > 0.60);
    ok("$slug: el titulo no viene vacío", trim((string) $g['titulo']) !== '');
}

// ── Los casos malos tienen que apagar el modo, no publicarlo a medias ────────
$dir = cb_themes_dir() . '/hielo/';
ok('sin bloque en themes.json queda apagado', cb_theme_grupal(null, 'themes/hielo/', $dir) === null);
ok('bloque sin fondo queda apagado', cb_theme_grupal(['frameBox' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.7, 'h' => 0.3]], 'themes/hielo/', $dir) === null);
ok('fondo que no está en disco queda apagado',
    cb_theme_grupal(['fondo' => 'no-existe.jpg', 'frameBox' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.7, 'h' => 0.3]], 'themes/hielo/', $dir) === null);
ok('sin frameBox queda apagado', cb_theme_grupal(['fondo' => 'fondo-grupal.jpg'], 'themes/hielo/', $dir) === null);
ok('frameBox que se sale del lienzo queda apagado',
    cb_theme_grupal(['fondo' => 'fondo-grupal.jpg', 'frameBox' => ['x' => 0.5, 'y' => 0.1, 'w' => 0.8, 'h' => 0.3]], 'themes/hielo/', $dir) === null);
ok('frameBox con un lado en cero queda apagado',
    cb_theme_grupal(['fondo' => 'fondo-grupal.jpg', 'frameBox' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.7, 'h' => 0]], 'themes/hielo/', $dir) === null);
ok('frameBox con texto en vez de número queda apagado',
    cb_theme_grupal(['fondo' => 'fondo-grupal.jpg', 'frameBox' => ['x' => 'a', 'y' => 0.1, 'w' => 0.7, 'h' => 0.3]], 'themes/hielo/', $dir) === null);

// ── Una temática sin el modo no cambia en nada ───────────────────────────────
$sinModo = cb_build_theme_payload('carreras', $temas['carreras'] ?? []);
ok('una temática sin el bloque sigue sin el modo', ($sinModo['grupal'] ?? null) === null);

// ── La galería tiene donde mostrarlas ───────────────────────────────────────
// Esto mira el TEXTO de galeria.php, no su comportamiento: no dice que la pestaña se vea
// bien, solo que existe. Está por un error real: la primera versión separaba las grupales
// del reparto por invitado y armaba la lista, pero no la dibujaba en ninguna parte, así que
// la foto se subía y no aparecía en ningún lado. Peor que no haber tocado nada.
$galeria = (string) file_get_contents(__DIR__ . '/../../public/galeria.php');
ok('la galería reconoce el prefijo grupal-', strpos($galeria, "'grupal-', 7") !== false);
ok('la galería arma la lista de grupales', strpos($galeria, '$grupales') !== false);
ok('la galería tiene la pestaña', strpos($galeria, 'data-vista="grupal"') !== false);
ok('la galería tiene el panel', strpos($galeria, "\$vistas['grupal']") !== false);
ok('las grupales no caen entre los invitados sin nombre',
    strpos($galeria, "\$p['kind'] !== 'grupal'") !== false);

echo $fallos === 0 ? "  $total comprobaciones, todas bien\n" : "  $fallos de $total fallaron\n";
exit($fallos === 0 ? 0 : 1);
