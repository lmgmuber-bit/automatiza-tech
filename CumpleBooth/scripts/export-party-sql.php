<?php
declare(strict_types=1);

/**
 * Exporta una o varias fiestas desde la BD local como SQL listo para pegar
 * en phpMyAdmin de PROD. Hostinger no acepta MySQL remoto y SSH está
 * bloqueado, así que este es el camino: sembrar local con
 * seed-demos-prod.php --apply, exportar acá, pegar en PROD.
 *
 * No depende de que los IDs numéricos coincidan entre local y PROD: usa
 * `public_slug` (UNIQUE) para el upsert de cc_parties y una subconsulta por
 * ese slug para resolver el party_id de cc_guests. Reemplaza SIEMPRE los
 * invitados de esa fiesta (DELETE + INSERT) para que quede igual que en local.
 *
 * Uso:
 *   php scripts/export-party-sql.php demo-kpop-vip              > kpop.sql
 *   php scripts/export-party-sql.php demo-kpop-vip demo-frozen-vip > dos.sql
 *   php scripts/export-party-sql.php --all                      > todas-las-demo.sql
 *   (--all = toda fiesta cuyo public_slug empiece con "demo-" y termine en "-vip")
 */

require __DIR__ . '/_cli.php';

$args = array_slice($_SERVER['argv'], 1);
if (!$args) {
    fwrite(STDERR, "Uso: php scripts/export-party-sql.php <slug> [<slug>...] | --all\n");
    exit(1);
}

$pdo = cb_pdo();

if ($args === ['--all']) {
    $stmt = $pdo->query("SELECT public_slug FROM cc_parties WHERE public_slug LIKE 'demo-%-vip' ORDER BY public_slug");
    $slugs = array_column($stmt->fetchAll(), 'public_slug');
    if (!$slugs) {
        fwrite(STDERR, "No hay fiestas demo-*-vip en la BD local. Corre primero seed-demos-prod.php --apply.\n");
        exit(2);
    }
} else {
    $slugs = $args;
}

/**
 * Emite un INSERT resolviendo las claves foraneas por SUBCONSULTA.
 *
 * Los ids autonumericos de local y de PROD no tienen por que coincidir, asi que
 * nunca se copia un id: `party_id` sale de `public_slug` e `invitation_id` sale
 * de `public_token_hash`, que son los dos UNIQUE. La columna `id` de la propia
 * fila se descarta por lo mismo.
 *
 * @param array<string,string> $fks columna => SQL de la subconsulta que la resuelve
 * @param string[] $upsert columnas a refrescar si la fila ya existe; vacio = INSERT IGNORE
 * @param string|null $donde condicion del UPDATE de refresco. Obligatoria si hay
 *        $upsert: es la unica forma de apuntar a ESTA fila y no a todas las de
 *        la fiesta.
 */
function cc_emitir(PDO $pdo, string $tabla, array $fila, array $fks, array $upsert = [], ?string $donde = null): void
{
    if ($upsert && $donde === null) {
        throw new InvalidArgumentException("cc_emitir($tabla): con \$upsert hay que decir sobre que fila corre el UPDATE.");
    }
    unset($fila['id']);
    $cols = [];
    $vals = [];
    foreach ($fila as $col => $valor) {
        $cols[] = $col;
        $vals[] = isset($fks[$col]) ? $fks[$col] : ($valor === null ? 'NULL' : $pdo->quote((string) $valor));
    }
    // Siempre IGNORE, tambien cuando hay $upsert: este SQL esta hecho para
    // pegarse mas de una vez —en los dos ambientes, o de nuevo despues de un
    // arreglo— y con INSERT pelado la segunda pasada moria con "Duplicate entry"
    // en `public_token_hash` a mitad del archivo, dejando la mitad aplicada. El
    // refresco lo hace el UPDATE de abajo, no el INSERT.
    echo "INSERT IGNORE INTO $tabla (" . implode(', ', $cols) . ")\n";
    echo "SELECT " . implode(', ', $vals) . ";\n";
    if ($upsert) {
        // ON DUPLICATE no se puede usar con INSERT ... SELECT sin FROM en todas
        // las versiones, asi que el refresco va como UPDATE aparte.
        $sets = [];
        foreach ($upsert as $col) {
            if (!array_key_exists($col, $fila)) { continue; }
            $sets[] = "$col = " . ($fila[$col] === null ? 'NULL' : $pdo->quote((string) $fila[$col]));
        }
        if ($sets) {
            // La condicion la elige quien llama. Tomar la primera FK apuntaria a
            // TODAS las filas de la fiesta, no a esta: para una invitacion eso
            // significa refrescar cualquier otra invitacion del mismo evento.
            echo "UPDATE $tabla SET " . implode(', ', $sets) . "\n";
            echo "  WHERE $donde;\n";
        }
    }
    echo "\n";
}

$q = static fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v);

echo "-- " . count($slugs) . " fiesta(s): " . implode(', ', $slugs) . "\n";
echo "-- Generado " . gmdate('Y-m-d H:i:s') . " UTC. Pegar completo en la pestana SQL\n";
echo "-- de phpMyAdmin del ambiente que corresponda.\n";
echo "-- OJO: desde 2026-08-29 hay DOS ambientes, con base de datos propia:\n";
echo "--   PROD             cumpleclick.com\n";
echo "--   pre-produccion   automatizatech.cl/cumpleclick\n";
echo "-- Confirma en cual estas parado antes de ejecutar esto.\n\n";

foreach ($slugs as $slug) {
    $stmt = $pdo->prepare('SELECT * FROM cc_parties WHERE public_slug = ?');
    $stmt->execute([$slug]);
    $party = $stmt->fetch();
    if (!$party) {
        fwrite(STDERR, "Aviso: no existe la fiesta '{$slug}' en la BD local, se omite.\n");
        continue;
    }

    $stmt = $pdo->prepare('SELECT * FROM cc_guests WHERE party_id = ? ORDER BY sort_order');
    $stmt->execute([$party['id']]);
    $guests = $stmt->fetchAll();

    echo "-- === {$slug} ({$party['theme_slug']}) — " . count($guests) . " invitados ===\n";

    /* Las columnas salen de la FILA, no de una lista escrita a mano.
     *
     * La lista fija se escribio antes de que existiera el baby shower y por eso
     * NO incluia `event_type`: los dos baby showers se exportaban y entraban en
     * la otra base como `child_birthday`, que es el default de la columna. La
     * fiesta se veia bien en el listado y todo el comportamiento de baby shower
     * —las apuestas, el vocabulario de la invitacion, el juego unico— quedaba
     * apagado sin que nada fallara. Se descubrio recien al ejecutar el SQL
     * contra una base limpia y mirar el resultado.
     *
     * Con las columnas dinamicas, una migracion que agregue una columna nueva
     * viaja sola y no hay que acordarse de venir a tocar este archivo. */
    $columnasParty = $party;
    unset($columnasParty['id']);
    // El PIN de galeria es la unica excepcion, y va aparte mas abajo.
    $columnasParty['gallery_pin_hash'] = null;
    $columnasParty['gallery_pin_hmac'] = null;

    $cols = array_keys($columnasParty);
    $vals = array_map(static fn ($v) => $q($v), array_values($columnasParty));
    // Al refrescar no se toca `public_slug` (es la clave) ni `created_at`.
    $refresca = array_diff($cols, ['public_slug', 'created_at']);

    echo "INSERT INTO cc_parties (" . implode(', ', $cols) . ")\n";
    echo "VALUES (" . implode(', ', $vals) . ")\n";
    echo "ON DUPLICATE KEY UPDATE "
        . implode(', ', array_map(static fn ($c) => "$c=VALUES($c)", $refresca)) . ";\n\n";

    echo "DELETE FROM cc_guests WHERE party_id = (SELECT id FROM cc_parties WHERE public_slug = {$q($slug)});\n\n";

    foreach ($guests as $g) {
        echo "INSERT INTO cc_guests (party_id, name, gender, sort_order, created_at)\n";
        echo "SELECT id, {$q($g['name'])}, {$q($g['gender'])}, {$q($g['sort_order'])}, {$q($g['created_at'])} FROM cc_parties WHERE public_slug = {$q($slug)};\n";
    }
    echo "\n";

    /* ── De aca abajo, lo que un baby shower necesita para existir ───────────
       Una fiesta infantil vive con cc_parties + cc_guests. Un baby shower NO:
       el producto ES la invitacion, la lista de regalos y las apuestas. Con
       solo las dos primeras tablas quedaba una fiesta que en el kiosco anda y
       cuyo enlace de invitacion da 404 — y el 404 recien se descubre cuando
       alguien abre el enlace.

       Todo esto se omite solo si no hay filas, asi que el guion sigue sirviendo
       igual para las infantiles.

       Los tokens SI viajan entre ambientes: `cb_hash_token()` es un sha256
       pelado, sin la clave de la aplicacion, asi que el mismo token abre en
       cualquiera. El PIN de galeria NO —depende de `app_hmac_key`— y por eso
       sale en NULL mas arriba. */
    $fkParty = "(SELECT id FROM cc_parties WHERE public_slug = {$q($slug)})";

    $st = $pdo->prepare('SELECT * FROM cc_event_profiles WHERE party_id = ?');
    $st->execute([$party['id']]);
    if ($fila = $st->fetch()) {
        echo "-- perfil del protagonista\n";
        cc_emitir($pdo, 'cc_event_profiles', $fila, ['party_id' => $fkParty],
            ['is_enabled', 'public_title', 'cta_label', 'intro_style', 'intro_phrase', 'design_variant', 'updated_at'],
            "party_id = $fkParty");
    }

    $st = $pdo->prepare('SELECT * FROM cc_invitations WHERE party_id = ? ORDER BY id');
    $st->execute([$party['id']]);
    foreach ($st->fetchAll() as $invitacion) {
        $fkInv = "(SELECT id FROM cc_invitations WHERE public_token_hash = {$q($invitacion['public_token_hash'])})";
        echo "-- invitacion de {$invitacion['birthday_person_name']} (estado: {$invitacion['status']})\n";
        cc_emitir($pdo, 'cc_invitations', $invitacion, ['party_id' => $fkParty],
            ['status', 'event_date', 'event_time', 'address', 'message', 'published_at', 'updated_at'],
            "public_token_hash = {$q($invitacion['public_token_hash'])}");

        foreach ([
            // Solo el token VIGENTE. Los revocados son basura de resiembras que
            // no abre ninguna pantalla, y no tiene sentido copiarlos a otra base.
            ['cc_invitation_tokens', "AND status = 'active' ORDER BY id", 'acceso vigente de los papas'],
            ['cc_gift_items', 'ORDER BY position, id', 'lista de regalos'],
            ['cc_invitation_outputs', 'ORDER BY id', 'laminas generadas'],
        ] as [$tabla, $orden, $etiqueta]) {
            $sub = $pdo->prepare("SELECT * FROM $tabla WHERE invitation_id = ? $orden");
            $sub->execute([$invitacion['id']]);
            $filas = $sub->fetchAll();
            if (!$filas) { continue; }
            echo "-- $etiqueta (" . count($filas) . ")\n";
            // `cc_gift_items` no tiene mas clave unica que el `id`, asi que
            // INSERT IGNORE no frena nada: pegar el archivo dos veces dejaba la
            // lista de regalos DUPLICADA (12 -> 24 -> 36, medido). Se reemplaza
            // entera, igual que los invitados, que es ademas lo que uno espera
            // al volver a pegar el mismo archivo. Las otras dos tablas si tienen
            // clave natural (`token_hash`, `invitation_id`+`asset_key`) y con
            // IGNORE quedan estables.
            if ($tabla === 'cc_gift_items') {
                echo "DELETE FROM cc_gift_items WHERE invitation_id = $fkInv;\n";
            }
            foreach ($filas as $f) {
                cc_emitir($pdo, $tabla, $f, ['invitation_id' => $fkInv]);
            }
        }
    }

    $st = $pdo->prepare('SELECT * FROM cc_predictions WHERE party_id = ? ORDER BY id');
    $st->execute([$party['id']]);
    $apuestas = $st->fetchAll();
    if ($apuestas) {
        echo "-- apuestas hechas en la cabina (" . count($apuestas) . ")\n";
        foreach ($apuestas as $a) {
            cc_emitir($pdo, 'cc_predictions', $a, ['party_id' => $fkParty]);
        }
    }

}
