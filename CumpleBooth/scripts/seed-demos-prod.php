<?php
declare(strict_types=1);

/**
 * Siembra UNA fiesta demo por cada temática TERMINADA, para que Luis pueda
 * recorrer cada una en producción sin crear nada a mano.
 *
 * "Terminada" se detecta solas, no a mano: una temática entra si TODOS sus
 * personajes tienen `saludo-<personaje>.mp4` en disco. Así, cuando Héroes (u
 * otra) complete sus saludos, la próxima corrida la suma sin tocar este
 * archivo. heroes hoy queda fuera porque tiene las fotos pero no los saludos
 * en video — se vería incompleta.
 *
 * Idempotente: reejecutarlo actualiza las mismas fiestas y no toca ninguna
 * otra (cb_save_parties recibe el arreglo completo, así que se leen las
 * existentes y se re-guardan tal cual).
 *
 * Los slugs/nombres de las primeras 4 son históricos (ya viven en PROD desde
 * antes) y se preservan vía ALIAS_SLUG/ALIAS_NOMBRE para no duplicar filas;
 * cualquier temática nueva usa su propio slug (demo-<slug>-vip) y su nombre
 * de themes.json.
 *
 * Uso:  php scripts/seed-demos-prod.php --apply
 */

require __DIR__ . '/_cli.php';

const ALIAS_SLUG = [
    'hielo'          => 'frozen',
    'familia-canina' => 'bluey',
];
const ALIAS_NOMBRE = [
    'hielo'          => 'Demo Frozen',
    'familia-canina' => 'Demo Bluey',
    'tropical'       => 'Demo Stitch',
    'carreras'       => 'Demo Carreras',
    'kpop'           => 'Demo K-Pop',
];

/** Personajes con saludo-<base>.mp4 real en disco == temática ofrecible. */
function cc_tema_terminado(string $themeSlug, array $themeData): bool
{
    $dir = cb_themes_dir() . '/' . $themeSlug . '/';
    $personajes = $themeData['personajes'] ?? [];
    if (!$personajes) { return false; }
    foreach ($personajes as $p) {
        $img = (string) ($p['img'] ?? '');
        $base = preg_replace('/\.(jpe?g|png)$/i', '', $img);
        if ($base === '' || !is_file($dir . 'saludo-' . $base . '.mp4')) { return false; }
    }
    return true;
}

function cc_build_demos(array $themes): array
{
    $demos = [];
    foreach ($themes as $themeSlug => $themeData) {
        if (!cc_tema_terminado($themeSlug, $themeData)) { continue; }
        $alias = ALIAS_SLUG[$themeSlug] ?? $themeSlug;
        $nombre = ALIAS_NOMBRE[$themeSlug] ?? ('Demo ' . (string) ($themeData['nombre'] ?? $themeSlug));
        $demos['demo-' . $alias . '-vip'] = ['theme' => $themeSlug, 'name' => $nombre];
    }
    return $demos;
}

$themes = cb_load_themes()['themes'];
$DEMOS = cc_build_demos($themes);
if (!$DEMOS) {
    fwrite(STDERR, "Ninguna temática está terminada (todos los personajes con saludo-*.mp4).\n");
    exit(1);
}

const INVITADOS = [
    ['name' => 'Sofía', 'g' => 'f'],
    ['name' => 'Martina', 'g' => 'f'],
    ['name' => 'Valentina', 'g' => 'f'],
    ['name' => 'Isidora', 'g' => 'f'],
    ['name' => 'Emilia', 'g' => 'f'],
    ['name' => 'Mateo', 'g' => 'm'],
    ['name' => 'Benjamín', 'g' => 'm'],
    ['name' => 'Tomás', 'g' => 'm'],
    ['name' => 'Vicente', 'g' => 'm'],
    ['name' => 'Joaquín', 'g' => 'm'],
];

const GALERIA_PIN = '2026';

if (!cc_cli_require_apply()) {
    foreach ($DEMOS as $slug => $d) {
        fwrite(STDOUT, "crearía {$slug} ({$d['theme']}) con " . count(INVITADOS) . " invitados\n");
    }
    exit(0);
}

$data = cb_load_parties();
$parties = $data['parties'] ?? [];
$now = gmdate('Y-m-d H:i:s');

foreach ($DEMOS as $publicSlug => $def) {
    $themeSlug = $def['theme'];
    if (!isset($themes[$themeSlug])) {
        fwrite(STDERR, "Falta la temática {$themeSlug}; no se guardó nada.\n");
        exit(2);
    }
    // No pisar una fiesta real que ya use este slug con otra temática.
    if (isset($parties[$publicSlug])) {
        $prev = (string) ($parties[$publicSlug]['tema'] ?? $parties[$publicSlug]['theme_slug'] ?? '');
        if ($prev !== '' && $prev !== $themeSlug) {
            fwrite(STDERR, "El slug {$publicSlug} ya pertenece a {$prev}; no se guardó nada.\n");
            exit(3);
        }
    }
    $creada = (string) ($parties[$publicSlug]['creada'] ?? $now);
    $parties[$publicSlug] = [
        'public_slug' => $publicSlug,
        'admin_label' => $def['name'],
        'birthday_person_name' => $def['name'],
        'nombre' => $def['name'],
        'theme_slug' => $themeSlug,
        'tema' => $themeSlug,
        'fecha' => '2026-12-31',
        'activa' => true,
        'service_plan' => 'booth',
        'gallery_enabled' => true,
        'galeriaPinHash' => password_hash(GALERIA_PIN, PASSWORD_DEFAULT),
        'galeriaPinHmac' => hash_hmac('sha256', GALERIA_PIN, (string) cb_config('app_hmac_key')),
        'invitados' => INVITADOS,
        'frameBox' => null,
        'creada' => $creada,
    ];
    fwrite(STDOUT, "ok {$publicSlug} ({$themeSlug})\n");
}

if (!cb_save_parties(['parties' => $parties])) {
    fwrite(STDERR, "No se pudieron guardar las demos.\n");
    exit(4);
}
fwrite(STDOUT, "Guardadas " . count($DEMOS) . " demos con " . count(INVITADOS) . " invitados y PIN de galería " . GALERIA_PIN . ".\n");
