<?php
/**
 * Catálogo de planes: la única fuente de los precios.
 *
 * Hasta ahora los precios vivían escritos a mano en el HTML del sitio, así que cambiar uno
 * significaba editar el sitio y acordarse de que el mismo número aparece en otros lados.
 * Acá viven una sola vez, se editan desde el admin, los lee el sitio público y de acá se
 * toma el precio al cargar una fiesta.
 *
 * El precio con promoción **no se guarda**: se calcula como `precio_normal` menos el
 * porcentaje de la promoción, igual que el descuento por fiesta. Guardar los dos números
 * es la forma segura de terminar con un sitio que dice una cosa y un comprobante que dice
 * otra.
 *
 * El archivo es JSON y no una tabla a propósito: lo lee también el sitio público, que vive
 * fuera de la aplicación y no comparte su conexión a la base.
 */

/** Ruta del catálogo. Vive junto a `marca.json`, que es del mismo tipo de dato. */
function cb_planes_ruta(): string
{
    return __DIR__ . '/data/planes.json';
}

/** Valores de respaldo: si el archivo falta o está roto, el sitio sigue mostrando algo coherente. */
function cb_planes_por_defecto(): array
{
    return [
        'promo' => ['activa' => false, 'texto' => 'Precios de lanzamiento', 'porcentaje' => 0],
        'tematica_a_medida' => 25000,
        'planes' => [],
    ];
}

/**
 * Catálogo listo para usar: cada plan trae además `precio` (el que se cobra hoy) y
 * `precio_antes` (el tachado, solo cuando hay promoción vigente).
 */
function cb_planes(): array
{
    $ruta = cb_planes_ruta();
    $crudo = is_file($ruta) ? json_decode((string) @file_get_contents($ruta), true) : null;
    if (!is_array($crudo)) {
        return cb_planes_por_defecto();
    }

    $promo = is_array($crudo['promo'] ?? null) ? $crudo['promo'] : [];
    $porcentaje = (float) ($promo['porcentaje'] ?? 0);
    $activa = !empty($promo['activa']) && $porcentaje > 0;

    $planes = [];
    foreach ((array) ($crudo['planes'] ?? []) as $p) {
        if (!is_array($p)) { continue; }
        $normal = (int) ($p['precio_normal'] ?? 0);
        $precio = $activa ? (int) round($normal * (100 - $porcentaje) / 100) : $normal;
        $planes[] = [
            'slug' => (string) ($p['slug'] ?? ''),
            'nombre' => (string) ($p['nombre'] ?? ''),
            'precio_normal' => $normal,
            'precio' => $precio,
            // Solo hay precio tachado si de verdad se está cobrando menos.
            'precio_antes' => $activa && $precio < $normal ? $normal : null,
            'destacado' => !empty($p['destacado']),
            'etiqueta' => (string) ($p['etiqueta'] ?? ''),
            // Cómo se ve la tarjeta en el sitio. No se edita desde el admin —es diseño, no
            // precio— pero viaja con el plan para que el sitio no tenga que adivinarlo.
            'clase' => (string) ($p['clase'] ?? ''),
            'badge_clase' => (string) ($p['badge_clase'] ?? ''),
            'emoji' => (string) ($p['emoji'] ?? ''),
            'incluye' => array_values(array_filter(array_map(
                static fn($l) => trim((string) $l),
                (array) ($p['incluye'] ?? [])
            ), static fn($l) => $l !== '')),
        ];
    }

    return [
        'promo' => [
            'activa' => $activa,
            'texto' => (string) ($promo['texto'] ?? 'Precios de lanzamiento'),
            'porcentaje' => $porcentaje,
        ],
        'tematica_a_medida' => (int) ($crudo['tematica_a_medida'] ?? 0),
        'planes' => $planes,
    ];
}

/** Un plan por su slug, con los precios ya calculados. `null` si no existe. */
function cb_plan(string $slug): ?array
{
    foreach (cb_planes()['planes'] as $p) {
        if ($p['slug'] === $slug) { return $p; }
    }
    return null;
}

/**
 * Guarda el catálogo. Devuelve `['ok' => bool, 'errors' => string[]]`.
 *
 * Escribe con archivo temporal y `rename`: si el disco se llena o el proceso muere a mitad,
 * `planes.json` queda como estaba y no roto, que es lo que dejaría al sitio sin precios.
 */
function cb_guardar_planes(array $datos): array
{
    $errores = [];
    $porcentaje = (float) ($datos['promo']['porcentaje'] ?? 0);
    if ($porcentaje < 0 || $porcentaje > 100) {
        $errores[] = 'El descuento de la promoción va entre 0 y 100.';
    }

    // Lo que la pantalla no edita se conserva del archivo anterior: si se reconstruyera el
    // plan solo con lo que manda el formulario, cada guardado borraría el diseño de la tarjeta.
    $previos = [];
    if (is_file(cb_planes_ruta())) {
        $crudoPrevio = json_decode((string) @file_get_contents(cb_planes_ruta()), true);
        foreach ((array) (($crudoPrevio['planes'] ?? [])) as $prev) {
            if (is_array($prev) && isset($prev['slug'])) { $previos[(string) $prev['slug']] = $prev; }
        }
    }

    $planes = [];
    foreach ((array) ($datos['planes'] ?? []) as $p) {
        $slug = trim((string) ($p['slug'] ?? ''));
        $nombre = trim((string) ($p['nombre'] ?? ''));
        if ($slug === '' || $nombre === '') { continue; }
        $normal = (int) ($p['precio_normal'] ?? 0);
        if ($normal < 0) {
            $errores[] = 'El precio de ' . $nombre . ' no puede ser negativo.';
            continue;
        }
        $antes = $previos[$slug] ?? [];
        $planes[] = [
            'slug' => $slug,
            'nombre' => mb_substr($nombre, 0, 60),
            'precio_normal' => $normal,
            'destacado' => !empty($p['destacado']),
            'etiqueta' => mb_substr(trim((string) ($p['etiqueta'] ?? '')), 0, 30),
            'clase' => (string) ($antes['clase'] ?? ''),
            'badge_clase' => (string) ($antes['badge_clase'] ?? ''),
            'emoji' => (string) ($antes['emoji'] ?? ''),
            'incluye' => array_values(array_filter(array_map(
                static fn($l) => mb_substr(trim((string) $l), 0, 160),
                preg_split('/\r\n|\r|\n/', (string) ($p['incluye'] ?? '')) ?: []
            ), static fn($l) => $l !== '')),
        ];
    }
    if (!$planes) {
        $errores[] = 'Tiene que quedar al menos un plan con nombre y precio.';
    }
    if ($errores) {
        return ['ok' => false, 'errors' => $errores];
    }

    $anterior = is_file(cb_planes_ruta())
        ? json_decode((string) @file_get_contents(cb_planes_ruta()), true) : null;
    $contenido = [
        '_LEEME' => is_array($anterior) && isset($anterior['_LEEME'])
            ? $anterior['_LEEME']
            : 'Catalogo de planes de CumpleClick. Se edita desde el admin, en la pantalla Planes.',
        'promo' => [
            'activa' => !empty($datos['promo']['activa']),
            'texto' => mb_substr(trim((string) ($datos['promo']['texto'] ?? '')), 0, 60),
            'porcentaje' => round($porcentaje, 2),
        ],
        'tematica_a_medida' => max(0, (int) ($datos['tematica_a_medida'] ?? 0)),
        'planes' => $planes,
    ];

    $json = json_encode($contenido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['ok' => false, 'errors' => ['No se pudo preparar el archivo de planes.']];
    }
    $temporal = cb_planes_ruta() . '.tmp';
    if (@file_put_contents($temporal, $json . "\n", LOCK_EX) === false || !@rename($temporal, cb_planes_ruta())) {
        @unlink($temporal);
        return ['ok' => false, 'errors' => ['No se pudo escribir data/planes.json. Revisa los permisos de la carpeta.']];
    }
    return ['ok' => true, 'errors' => []];
}
