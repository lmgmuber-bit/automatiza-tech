<?php
/**
 * lib.ferias.php — modo feria (AT-CUMPLECLICK-020, 2026-09-26).
 *
 * Una feria cuelga de una fiesta normal (`cc_parties`) por `party_id`. Así el kiosco, las fotos,
 * `ver.php` y el QR siguen funcionando igual, y no se toca el tipo de evento, que es binario
 * (cumpleaños / baby shower) y está repetido en varios lugares de lib.php.
 *
 * Lo propio de la feria vive en dos tablas (migración 026):
 *   - `cc_ferias`: nombre, organizador, lugar, fecha, mesa y los mundos habilitados por modo;
 *   - `cc_feria_fotos`: el número F-### de cada visitante, con su modo, temática y nombre.
 *
 * Decisiones de Luis (26-09): las fotos de feria se guardan 7 días; dos entradas, Niños y Adultos;
 * la galería de la feria es solo del admin (son niños ajenos: nada de galería pública ni Álbum).
 *
 * 🔴 La migración 026 va antes que este código. Aun así, si falta la tabla, todo lo de acá
 * responde "no hay feria" y una fiesta normal sigue funcionando: ver `cb_ferias_listo()`.
 */
require_once __DIR__ . '/lib.php';

const CB_FERIA_MODOS = ['infantil' => 'Niños', 'adulto' => 'Adultos'];
const CB_FERIA_NOMBRE_MAX = 20;
const CB_FERIA_MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/** ¿Existen las tablas? Sin base de datos, o antes de la migración 026, no hay ferias. */
function cb_ferias_listo(): bool
{
    static $listo = null;
    if ($listo !== null) {
        return $listo;
    }
    if (cb_storage_mode() !== 'db') {
        return $listo = false;
    }
    try {
        cb_pdo()->query('SELECT 1 FROM cc_ferias LIMIT 1')->fetchAll();
        cb_pdo()->query('SELECT 1 FROM cc_feria_fotos LIMIT 1')->fetchAll();
        return $listo = true;
    } catch (Throwable $e) {
        return $listo = false;
    }
}

function cb_feria_modo_valido(string $modo): bool
{
    return isset(CB_FERIA_MODOS[$modo]);
}

/**
 * ¿Puede ofrecerse esta temática en ese modo? Las temáticas adultas propias (`"audiencia":
 * "adulto"` en themes.json) solo van en Adultos; el resto puede ir en los dos modos y el admin
 * decide cuáles marca.
 */
function cb_feria_tema_permitido(array $themeData, string $modo): bool
{
    if (!cb_feria_modo_valido($modo)) {
        return false;
    }
    return $modo === 'adulto' || (string) ($themeData['audiencia'] ?? '') !== 'adulto';
}

/** Deja solo temáticas que existen y que valen para ese modo, sin repetir y en el orden dado. */
function cb_feria_limpiar_mundos($slugs, string $modo): array
{
    $themes = cb_load_themes()['themes'] ?? [];
    $out = [];
    foreach ((array) $slugs as $slug) {
        $slug = (string) $slug;
        if (!cb_valid_slug($slug, 1, 40) || !isset($themes[$slug]) || !is_array($themes[$slug])) {
            continue;
        }
        if (cb_feria_tema_permitido($themes[$slug], $modo) && !in_array($slug, $out, true)) {
            $out[] = $slug;
        }
    }
    return $out;
}

function cb_feria_fecha_valida(string $fecha): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $d !== false && $d->format('Y-m-d') === $fecha && $fecha >= '2020-01-01';
}

/** "2026-09-26" → "26 sep 2026". */
function cb_feria_fecha_texto(string $fecha): string
{
    if (!cb_feria_fecha_valida($fecha)) {
        return '';
    }
    [$a, $m, $d] = array_map('intval', explode('-', $fecha));
    return $d . ' ' . CB_FERIA_MESES[$m - 1] . ' ' . $a;
}

/** La línea de recuerdo que va en cada foto: nombre · organizador · fecha (lo que exista). */
function cb_feria_recuerdo(array $feria): string
{
    $partes = array_filter([
        trim((string) ($feria['nombre'] ?? '')),
        trim((string) ($feria['organizador'] ?? '')),
        cb_feria_fecha_texto((string) ($feria['fecha'] ?? '')),
    ], static fn($p) => $p !== '');
    return implode(' · ', $partes);
}

/** Nombre del visitante: primer nombre, sin símbolos, máximo 20 caracteres. Vacío vale. */
function cb_feria_limpiar_nombre(string $nombre): string
{
    $nombre = preg_replace('/[^\pL\pM \'-]+/u', ' ', $nombre) ?? '';
    $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
    return mb_substr($nombre, 0, CB_FERIA_NOMBRE_MAX, 'UTF-8');
}

/** "F-027", "f27", "27" → 27; lo demás → null. */
function cb_feria_parsear_numero(string $texto): ?int
{
    if (!preg_match('/^\s*(?:f\s*-?\s*)?(\d{1,6})\s*$/i', $texto, $m)) {
        return null;
    }
    return (int) $m[1];
}

function cb_feria_etiqueta(int $numero): string
{
    return 'F-' . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
}

/** Fila de la base → arreglo con tipos, mundos decodificados y el slug de su fiesta. */
function cb_feria_normalizar(array $row): array
{
    $mundos = static function ($json): array {
        $lista = json_decode((string) $json, true);
        return is_array($lista) ? array_values(array_map('strval', $lista)) : [];
    };
    return [
        'id' => (int) $row['id'],
        'party_id' => (int) $row['party_id'],
        'slug' => (string) ($row['public_slug'] ?? ''),
        'nombre' => (string) $row['nombre'],
        'organizador' => (string) $row['organizador'],
        'organizador_ig' => (string) $row['organizador_ig'],
        'lugar' => (string) $row['lugar'],
        'fecha' => (string) $row['fecha'],
        'hora_inicio' => $row['hora_inicio'] !== null && $row['hora_inicio'] !== '' ? substr((string) $row['hora_inicio'], 0, 5) : '',
        'mesa' => (string) $row['mesa'],
        'notas' => (string) $row['notas'],
        'mundos' => ['infantil' => $mundos($row['mundos_infantil']), 'adulto' => $mundos($row['mundos_adulto'])],
        'retencion_dias' => max(1, (int) $row['retencion_dias']),
        'max_fotos' => max(1, (int) $row['max_fotos']),
        'activa' => (bool) $row['activa'],
        'duplicada_de' => $row['duplicada_de'] !== null ? (int) $row['duplicada_de'] : null,
        'party_activa' => (bool) ($row['party_active'] ?? false),
        'anonimizada' => !empty($row['anonymized_at']),
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
    ];
}

function cb_feria_select(): string
{
    return 'SELECT f.*, p.public_slug, p.active AS party_active, p.anonymized_at
            FROM cc_ferias f JOIN cc_parties p ON p.id = f.party_id';
}

/** La feria de una fiesta, o null (fiesta normal, sin tablas o slug inválido). */
function cb_feria_de_fiesta(string $partySlug): ?array
{
    if (!cb_valid_public_slug($partySlug) || !cb_ferias_listo()) {
        return null;
    }
    $stmt = cb_pdo()->prepare(cb_feria_select() . ' WHERE p.public_slug = ?');
    $stmt->execute([$partySlug]);
    $row = $stmt->fetch();
    return $row ? cb_feria_normalizar($row) : null;
}

function cb_feria_por_id(int $id): ?array
{
    if ($id < 1 || !cb_ferias_listo()) {
        return null;
    }
    $stmt = cb_pdo()->prepare(cb_feria_select() . ' WHERE f.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? cb_feria_normalizar($row) : null;
}

/**
 * Versión que nunca lanza, para api.php y upload.php: si la consulta falla (tabla a medio
 * migrar, base caída un instante) se registra y la fiesta se atiende como una normal. Una
 * fiesta de cumpleaños no puede caerse por culpa del modo feria.
 */
function cb_feria_de_fiesta_segura(string $partySlug): ?array
{
    try {
        return cb_feria_de_fiesta($partySlug);
    } catch (Throwable $e) {
        error_log('CumpleClick feria: ' . $e->getMessage());
        return null;
    }
}

/** ¿Está abierta al público? La feria y su fiesta tienen que estar activas. */
function cb_feria_abierta(array $feria): bool
{
    return $feria['activa'] && $feria['party_activa'] && !$feria['anonimizada'];
}

/** Mundos que de verdad se pueden ofrecer ahora (una temática borrada de themes.json se cae sola). */
function cb_feria_modos(array $feria): array
{
    return [
        'infantil' => cb_feria_limpiar_mundos($feria['mundos']['infantil'], 'infantil'),
        'adulto' => cb_feria_limpiar_mundos($feria['mundos']['adulto'], 'adulto'),
    ];
}

/** Lo que ve el kiosco y el selector (contrato 4.1 y 4.2 del handoff). */
function cb_feria_publica(array $feria, ?string $modo = null): array
{
    $out = [
        'slug' => $feria['slug'],
        'nombre' => $feria['nombre'],
        'organizador' => $feria['organizador'],
        'organizador_ig' => $feria['organizador_ig'],
        'lugar' => $feria['lugar'],
        'fecha' => $feria['fecha'],
        'fecha_texto' => cb_feria_fecha_texto($feria['fecha']),
        'mesa' => $feria['mesa'],
        'recuerdo' => cb_feria_recuerdo($feria),
        'fondo' => cb_feria_fondo($feria),
    ];
    if ($modo !== null) {
        $out['modo'] = $modo;
    }
    $out['modos'] = cb_feria_modos($feria);
    return $out;
}

/**
 * Fondo de pantalla de una temática para el selector y el kiosco (Luis, 29-09: un evento de Noche de Brujas mostraba
 * el fondo de Fiestas Patrias). Es `fondo-evento.jpg`, 9:16 y con el centro despejado; sin archivo, cadena vacía.
 */
function cb_feria_fondo_mundo(string $slug): string
{
    return is_file(cb_themes_dir() . '/' . $slug . '/fondo-evento.jpg') ? 'themes/' . $slug . '/fondo-evento.jpg' : '';
}

/**
 * Fondo del evento: el de su primera temática con fondo propio, Niños antes que Adultos, en el orden en que se
 * marcaron. Sin ninguna, cadena vacía y el kiosco usa el genérico. Cuando el evento tenga su temática principal
 * como dato (ticket 024), se lee de ahí.
 */
function cb_feria_fondo(array $feria): string
{
    foreach (cb_feria_modos($feria) as $slugs) {
        foreach ($slugs as $slug) {
            $fondo = cb_feria_fondo_mundo($slug);
            if ($fondo !== '') {
                return $fondo;
            }
        }
    }
    return '';
}

/** Imagen de muestra de un mundo para el selector: el banner si existe, si no el fondo de la sala. */
function cb_feria_imagen_mundo(string $slug): string
{
    foreach (['fondo-banner.jpg', 'fondo-sala.jpg'] as $archivo) {
        if (is_file(cb_themes_dir() . '/' . $slug . '/' . $archivo)) {
            return 'themes/' . $slug . '/' . $archivo;
        }
    }
    return '';
}

/** Los mundos de cada modo con nombre, imagen y si tienen personajes (sin personajes = sin ruleta). */
function cb_feria_mundos_publicos(array $feria): array
{
    $themes = cb_load_themes()['themes'] ?? [];
    $out = [];
    foreach (cb_feria_modos($feria) as $modo => $slugs) {
        $out[$modo] = [];
        foreach ($slugs as $slug) {
            $out[$modo][] = [
                'slug' => $slug,
                'nombre' => (string) ($themes[$slug]['nombre'] ?? $slug),
                'imagen' => cb_feria_imagen_mundo($slug),
                'fondo' => cb_feria_fondo_mundo($slug),
                'personajes' => !empty($themes[$slug]['personajes']),
            ];
        }
    }
    return $out;
}

/**
 * api.php con feria: la temática la elige el visitante (`tema` + `modo`) en vez de venir fija en
 * la fiesta. Recibe lo que ya resolvió `cb_resolve_party()` y devuelve la misma forma, más `feria`.
 *
 * En feria no hay minijuegos entre el personaje y la foto (la fila tiene que avanzar), ni lista
 * de invitados, ni marco propio de la fiesta: el marco es el de la temática elegida.
 */
function cb_feria_resolver(array $feria, array $resuelto, string $tema, string $modo): array
{
    $modos = cb_feria_modos($feria);
    if ($modo === '') {
        $modo = $modos['infantil'] ? 'infantil' : 'adulto';
    }
    if (!cb_feria_modo_valido($modo)) {
        return ['ok' => false, 'error' => 'modo_invalido', 'code' => 400];
    }
    if ($tema === '') {
        $tema = $modos[$modo][0] ?? '';
    }
    if ($tema === '' || !in_array($tema, $modos[$modo], true)) {
        return ['ok' => false, 'error' => 'tema_no_habilitado', 'code' => 403];
    }
    $themeData = cb_load_themes()['themes'][$tema];
    $party = $resuelto['party'];
    $party['nombre'] = $feria['nombre'];
    $party['invitados'] = [];
    $party['frameBox'] = cb_normalize_frame_box($themeData['frameBox'] ?? null);
    $party['gallery_enabled'] = false;
    $party['service_plan'] = 'booth';
    // Las temáticas adultas pueden venir sin música (así las aprobó Luis el 26-09): sin archivo, sin
    // música, en vez de dejar que la tablet pida un MP3 que no existe.
    $party['musica'] = $party['musica'] && is_file(cb_themes_dir() . '/' . $tema . '/musica-fondo.mp3');
    // Niños juega el minijuego de su personaje, como en una fiesta (Luis, 26-09, ya en la feria: "no está la
    // opción de juegos"). Adultos no tiene personajes; se deja la lista vacía para que nunca aparezca uno.
    $theme = cb_build_theme_payload($tema, $themeData, $modo === 'infantil' ? null : [], 'booth');
    if ($modo !== 'infantil') {
        // El juego general de la temática no pasa por el filtro de personajes: en Adultos también se apaga.
        $theme['game'] = new stdClass();
    }
    // Fondo de pantalla del kiosco: el de la temática elegida (29-09); sin archivo, el del evento o el genérico.
    $fondoMundo = cb_feria_fondo_mundo($tema);
    if ($fondoMundo !== '') {
        $theme['images']['fondoEvento'] = $fondoMundo;
    }
    // Filtro de la foto final, por temática (hoy solo "bn": el estudio en blanco y negro que la
    // competencia vende a adultos). El kiosco lo aplica al componer; sin el campo, foto a color.
    if (($themeData['filtro'] ?? '') === 'bn') {
        $theme['filtro'] = 'bn';
    }
    // Foto sobre fondo completo (Luis, 26-09: "no necesariamente con marcos, sino con fondos"): el
    // kiosco recorta a las personas y las pone delante de la escena. Se publica solo si la escena
    // está en disco; si el kiosco no sabe recortar, usa el marco de siempre con `frameBox`.
    $escena = (string) ($themeData['fondoEscena'] ?? '');
    if (($themeData['modoFoto'] ?? '') === 'fondo' && preg_match('/\A[a-z0-9][a-z0-9._-]*\.(?:jpe?g|png|webp)\z/i', $escena)
        && is_file(cb_themes_dir() . '/' . $tema . '/' . $escena)) {
        $theme['modoFoto'] = 'fondo';
        $theme['images']['escena'] = 'themes/' . $tema . '/' . $escena;
    } else {
        $theme['modoFoto'] = 'marco';
    }
    // Portada de Revista (04-10): las escenas que el invitado elige en el menú. Solo con foto sobre fondo y solo las que
    // están en disco; con una sola no hay menú.
    $revista = $theme['modoFoto'] === 'fondo' ? cb_feria_revista($tema, $themeData['revista'] ?? null) : null;
    if ($revista !== null) {
        $theme['revista'] = $revista;
    }
    return [
        'ok' => true,
        'party' => $party,
        'theme' => $theme,
        'feria' => cb_feria_publica($feria, $modo),
    ];
}

/**
 * Portada de Revista CLICK (04-10-2026): el título de la revista y sus variantes de fondo, validados. Una variante sin
 * escena en disco se cae sola; el título son letras y números (lo dibuja el kiosco, no viene en la imagen).
 */
function cb_feria_revista(string $tema, $bloque): ?array
{
    if (!is_array($bloque) || !is_array($bloque['variantes'] ?? null)) {
        return null;
    }
    $titulo = strtoupper(trim((string) ($bloque['titulo'] ?? '')));
    if (!preg_match('/\A[A-Z0-9 ]{1,12}\z/', $titulo)) {
        return null;
    }
    $variantes = [];
    foreach ($bloque['variantes'] as $v) {
        if (!is_array($v)) {
            continue;
        }
        $clave = (string) ($v['clave'] ?? '');
        $escena = (string) ($v['escena'] ?? '');
        if (!cb_valid_slug($clave, 1, 30) || !preg_match('/\A[a-z0-9][a-z0-9._-]*\.(?:jpe?g|png|webp)\z/i', $escena)
            || !is_file(cb_themes_dir() . '/' . $tema . '/' . $escena)) {
            continue;
        }
        $limpia = [
            'clave' => $clave,
            'etiqueta' => mb_substr(trim((string) ($v['etiqueta'] ?? $clave)), 0, 40),
            'detalle' => mb_substr(trim((string) ($v['detalle'] ?? '')), 0, 60),
            'emoji' => mb_substr(trim((string) ($v['emoji'] ?? '')), 0, 4),
            'escena' => 'themes/' . $tema . '/' . $escena,
        ];
        if (($v['filtro'] ?? '') === 'bn') {
            $limpia['filtro'] = 'bn';
        }
        foreach (['tinta', 'acento'] as $color) {
            if (preg_match('/\A#[0-9a-f]{6}\z/i', (string) ($v[$color] ?? ''))) {
                $limpia[$color] = (string) $v[$color];
            }
        }
        $variantes[] = $limpia;
        if (count($variantes) >= 4) {
            break;
        }
    }
    return $variantes ? ['titulo' => $titulo, 'variantes' => $variantes] : null;
}

/**
 * Reserva el número F-### de un visitante. El número sale del máximo + 1 y la restricción única
 * (feria_id, numero) decide si dos tablets pidieron al mismo tiempo: la que pierde reintenta.
 * Devuelve el token en claro UNA vez; la base guarda solo su huella.
 */
function cb_feria_reservar_numero(array $feria, string $modo, string $tema, string $nombre): array
{
    if (!cb_feria_modo_valido($modo)) {
        return ['ok' => false, 'error' => 'modo_invalido', 'code' => 400];
    }
    if (!in_array($tema, cb_feria_modos($feria)[$modo], true)) {
        return ['ok' => false, 'error' => 'tema_no_habilitado', 'code' => 403];
    }
    $nombre = cb_feria_limpiar_nombre($nombre);
    $pdo = cb_pdo();
    $maxNumero = $pdo->prepare('SELECT COALESCE(MAX(numero), 0) FROM cc_feria_fotos WHERE feria_id = ?');
    $insert = $pdo->prepare('INSERT INTO cc_feria_fotos (feria_id, numero, reserva, photo_id, modo, theme_slug, nombre, impresiones, created_at, updated_at)
        VALUES (?,?,?,NULL,?,?,?,0,?,?)');
    for ($intento = 0; $intento < 6; $intento++) {
        $maxNumero->execute([$feria['id']]);
        $numero = (int) $maxNumero->fetchColumn() + 1;
        // Tope contra abuso: las reservas sin foto (alguien se arrepintió) no cuentan para el
        // tope de fotos, pero tampoco pueden crecer sin fin.
        if ($numero > $feria['max_fotos'] * 3) {
            return ['ok' => false, 'error' => 'feria_llena', 'code' => 507];
        }
        $token = cb_opaque_token(16);
        $now = gmdate('Y-m-d H:i:s');
        try {
            $insert->execute([$feria['id'], $numero, cb_hash_token($token), $modo, $tema, $nombre, $now, $now]);
            return ['ok' => true, 'numero' => $numero, 'etiqueta' => cb_feria_etiqueta($numero), 'reserva' => $token];
        } catch (PDOException $e) {
            // 23000 = violación de unicidad en MySQL y SQLite: otro pidió el mismo número.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }
    }
    return ['ok' => false, 'error' => 'intenta_de_nuevo', 'code' => 503];
}

/**
 * Liga la foto recién guardada a su número. Solo si el token es de ESTA feria y todavía no tiene
 * foto. Nunca lanza: la foto ya está guardada y eso es lo que importa (contrato 4.4).
 */
function cb_feria_ligar_foto(array $feria, string $reserva, string $photoToken): bool
{
    if (!preg_match('/^[a-f0-9]{32}$/', $reserva)) {
        return false;
    }
    try {
        $pdo = cb_pdo();
        $foto = $pdo->prepare('SELECT id FROM cc_photos WHERE access_token = ? AND party_id = ?');
        $foto->execute([$photoToken, $feria['party_id']]);
        $photoId = $foto->fetchColumn();
        if (!$photoId) {
            return false;
        }
        $upd = $pdo->prepare('UPDATE cc_feria_fotos SET photo_id = ?, updated_at = ? WHERE reserva = ? AND feria_id = ? AND photo_id IS NULL');
        $upd->execute([(int) $photoId, gmdate('Y-m-d H:i:s'), cb_hash_token($reserva), $feria['id']]);
        return $upd->rowCount() === 1;
    } catch (Throwable $e) {
        error_log('CumpleClick feria ligar foto: ' . $e->getMessage());
        return false;
    }
}

// ── Admin ────────────────────────────────────────────────────────────────────

/** Todas las ferias, la más nueva primero, con cuántas fotos e impresiones lleva cada una. */
function cb_ferias_listar(): array
{
    if (!cb_ferias_listo()) {
        return [];
    }
    $rows = cb_pdo()->query(cb_feria_select() . ' ORDER BY f.fecha DESC, f.id DESC')->fetchAll();
    $cuenta = cb_pdo()->prepare('SELECT COUNT(ff.photo_id) AS fotos, COALESCE(SUM(ff.impresiones), 0) AS impresiones
        FROM cc_feria_fotos ff LEFT JOIN cc_photos ph ON ph.id = ff.photo_id
        WHERE ff.feria_id = ? AND ff.photo_id IS NOT NULL AND ph.deleted_at IS NULL');
    $out = [];
    foreach ($rows as $row) {
        $feria = cb_feria_normalizar($row);
        $cuenta->execute([$feria['id']]);
        $c = $cuenta->fetch() ?: [];
        $feria['fotos'] = (int) ($c['fotos'] ?? 0);
        $feria['impresiones'] = (int) ($c['impresiones'] ?? 0);
        $out[] = $feria;
    }
    return $out;
}

/** Valida lo que llega del formulario. Devuelve [datos limpios, errores]. */
function cb_feria_validar(array $in): array
{
    $texto = static fn(string $k, int $max): string => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max, 'UTF-8');
    $d = [
        'nombre' => $texto('nombre', 160),
        'organizador' => $texto('organizador', 160),
        'organizador_ig' => $texto('organizador_ig', 80),
        'lugar' => $texto('lugar', 255),
        'fecha' => $texto('fecha', 10),
        'hora_inicio' => $texto('hora_inicio', 5),
        'mesa' => $texto('mesa', 40),
        'notas' => $texto('notas', 2000),
        'mundos_infantil' => cb_feria_limpiar_mundos($in['mundos_infantil'] ?? [], 'infantil'),
        'mundos_adulto' => cb_feria_limpiar_mundos($in['mundos_adulto'] ?? [], 'adulto'),
        'retencion_dias' => (int) ($in['retencion_dias'] ?? 7),
        'max_fotos' => (int) ($in['max_fotos'] ?? 1000),
        'activa' => !empty($in['activa']),
    ];
    if ($d['organizador_ig'] !== '' && $d['organizador_ig'][0] !== '@') {
        $d['organizador_ig'] = '@' . $d['organizador_ig'];
    }
    $errores = [];
    if ($d['nombre'] === '') {
        $errores[] = 'El nombre de la feria es obligatorio.';
    }
    if (!cb_feria_fecha_valida($d['fecha'])) {
        $errores[] = 'La fecha debe ser un día real (AAAA-MM-DD).';
    }
    if ($d['hora_inicio'] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $d['hora_inicio'])) {
        $errores[] = 'La hora de inicio va como HH:MM.';
    }
    if ($d['organizador_ig'] !== '' && !preg_match('/^@[A-Za-z0-9._]{1,30}$/', $d['organizador_ig'])) {
        $errores[] = 'El Instagram del organizador va como @usuario.';
    }
    if ($d['retencion_dias'] < 1 || $d['retencion_dias'] > 30) {
        $errores[] = 'Las fotos se guardan entre 1 y 30 días.';
    }
    if ($d['max_fotos'] < 10 || $d['max_fotos'] > 5000) {
        $errores[] = 'El tope de fotos va entre 10 y 5.000.';
    }
    if ($d['activa'] && !$d['mundos_infantil'] && !$d['mundos_adulto']) {
        $errores[] = 'Para activar la feria marca al menos un mundo.';
    }
    return [$d, $errores];
}

/**
 * Crea la fiesta que sostiene la feria. Se crea por `cb_save_parties()`, la misma puerta que usa
 * el admin, para no repetir la lista de columnas de `cc_parties` (ver las trampas del manifiesto).
 * La temática de la fiesta es solo un ancla: el visitante elige otra en cada foto.
 */
function cb_feria_crear_fiesta(array $d, string $por): string
{
    require_once __DIR__ . '/lib.acceptance.php';
    $pdo = cb_pdo();
    $ancla = $d['mundos_infantil'][0] ?? $d['mundos_adulto'][0] ?? 'hielo';
    $slug = cb_generate_public_slug($pdo, $d['nombre'], 'feria');
    $parties = cb_load_parties()['parties'];
    $parties[$slug] = [
        'public_slug' => $slug,
        'admin_label' => 'Feria: ' . $d['nombre'],
        'birthday_person_name' => $d['nombre'],
        'nombre' => $d['nombre'],
        'event_type' => 'child_birthday',
        'theme_slug' => $ancla,
        'tema' => $ancla,
        'fecha' => $d['fecha'],
        'activa' => $d['activa'],
        'service_plan' => 'booth',
        'gallery_enabled' => false,
        'invitados' => [],
        'frameBox' => null,
        'creada' => gmdate('Y-m-d H:i:s'),
        'juegos3d' => false,
    ];
    if (!cb_save_parties(['parties' => $parties])) {
        throw new RuntimeException('No se pudo crear la fiesta de la feria.');
    }
    // Es un evento propio de CumpleClick, sin cliente que firme: queda eximido, igual que un demo.
    // Sin esto, editar la fiesta desde la pestaña Fiestas no dejaría guardarla activa.
    $w = cb_waive_acceptance($parties[$slug], 'Feria propia de CumpleClick (modo feria)', $por);
    if (empty($w['ok'])) {
        error_log('CumpleClick feria: la exención no se guardó para ' . $slug);
    }
    return $slug;
}

/** Crea o actualiza una feria. Devuelve la feria guardada. */
function cb_feria_guardar(array $d, ?int $id, string $por, ?int $duplicadaDe = null): array
{
    $pdo = cb_pdo();
    $now = gmdate('Y-m-d H:i:s');
    $valores = [
        $d['nombre'], $d['organizador'], $d['organizador_ig'], $d['lugar'], $d['fecha'],
        $d['hora_inicio'] !== '' ? $d['hora_inicio'] . ':00' : null, $d['mesa'], $d['notas'],
        json_encode(array_values($d['mundos_infantil'])), json_encode(array_values($d['mundos_adulto'])),
        $d['retencion_dias'], $d['max_fotos'], $d['activa'] ? 1 : 0,
    ];
    if ($id === null) {
        $slug = cb_feria_crear_fiesta($d, $por);
        $partyId = cb_party_db_id($slug);
        $pdo->prepare('INSERT INTO cc_ferias (nombre, organizador, organizador_ig, lugar, fecha, hora_inicio, mesa, notas,
                mundos_infantil, mundos_adulto, retencion_dias, max_fotos, activa, party_id, duplicada_de, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(array_merge($valores, [$partyId, $duplicadaDe, $now, $now]));
        $id = (int) $pdo->lastInsertId();
    } else {
        $actual = cb_feria_por_id($id);
        if ($actual === null) {
            throw new DomainException('La feria ya no existe.');
        }
        $pdo->prepare('UPDATE cc_ferias SET nombre=?, organizador=?, organizador_ig=?, lugar=?, fecha=?, hora_inicio=?, mesa=?, notas=?,
                mundos_infantil=?, mundos_adulto=?, retencion_dias=?, max_fotos=?, activa=?, updated_at=? WHERE id=?')
            ->execute(array_merge($valores, [$now, $id]));
        // La fiesta que la sostiene sigue a la feria en lo que se ve y en si está activa. Solo esas
        // columnas: reescribir la fiesta entera no hace falta y arriesgaría pisar otra cosa.
        $ancla = $d['mundos_infantil'][0] ?? $d['mundos_adulto'][0] ?? null;
        $sql = 'UPDATE cc_parties SET admin_label=?, birthday_person_name=?, event_date=?, active=?, updated_at=?'
            . ($ancla !== null ? ', theme_slug=?' : '') . ' WHERE id=? AND anonymized_at IS NULL';
        $params = ['Feria: ' . $d['nombre'], $d['nombre'], $d['fecha'], $d['activa'] ? 1 : 0, $now];
        if ($ancla !== null) {
            $params[] = $ancla;
        }
        $params[] = $actual['party_id'];
        $pdo->prepare($sql)->execute($params);
    }
    return cb_feria_por_id($id);
}

/** Copia la ficha para la próxima feria: mismos mundos y datos, fecha de hoy, inactiva, sin fotos. */
function cb_feria_duplicar(int $id, string $por): array
{
    $origen = cb_feria_por_id($id);
    if ($origen === null) {
        throw new DomainException('La feria ya no existe.');
    }
    $d = [
        'nombre' => mb_substr($origen['nombre'] . ' (copia)', 0, 160, 'UTF-8'),
        'organizador' => $origen['organizador'], 'organizador_ig' => $origen['organizador_ig'],
        'lugar' => $origen['lugar'],
        'fecha' => (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d'),
        'hora_inicio' => $origen['hora_inicio'], 'mesa' => '', 'notas' => $origen['notas'],
        'mundos_infantil' => $origen['mundos']['infantil'], 'mundos_adulto' => $origen['mundos']['adulto'],
        'retencion_dias' => $origen['retencion_dias'], 'max_fotos' => $origen['max_fotos'],
        'activa' => false,
    ];
    return cb_feria_guardar($d, null, $por, $origen['id']);
}

/**
 * Las fotos de una feria para la galería del admin, filtradas y paginadas.
 * Filtros: `q` (nombre o número, "F-027" o "27"), `tema`, `modo`, `desde`/`hasta` (HH:MM, hora de Chile).
 * Son a lo más unas mil filas por feria: se filtran en PHP, así el filtro por hora de Chile
 * no depende de las funciones de fecha de MySQL o SQLite.
 */
function cb_feria_galeria(array $feria, array $filtros = [], int $pagina = 1, int $porPagina = 24): array
{
    $stmt = cb_pdo()->prepare('SELECT ff.id, ff.numero, ff.modo, ff.theme_slug, ff.nombre, ff.impresiones, ff.created_at,
            ph.access_token, ph.created_at AS foto_creada
        FROM cc_feria_fotos ff JOIN cc_photos ph ON ph.id = ff.photo_id
        WHERE ff.feria_id = ? AND ph.deleted_at IS NULL
        ORDER BY ff.numero DESC');
    $stmt->execute([$feria['id']]);
    $zona = new DateTimeZone('America/Santiago');
    $q = trim((string) ($filtros['q'] ?? ''));
    $numero = $q !== '' ? cb_feria_parsear_numero($q) : null;
    $qMin = mb_strtolower($q, 'UTF-8');
    $tema = (string) ($filtros['tema'] ?? '');
    $modo = (string) ($filtros['modo'] ?? '');
    $horaOk = static fn(string $h): bool => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h);
    $desde = $horaOk((string) ($filtros['desde'] ?? '')) ? (string) $filtros['desde'] : '';
    $hasta = $horaOk((string) ($filtros['hasta'] ?? '')) ? (string) $filtros['hasta'] : '';
    $filas = [];
    foreach ($stmt->fetchAll() as $row) {
        $hora = (new DateTimeImmutable((string) $row['foto_creada'], new DateTimeZone('UTC')))->setTimezone($zona)->format('H:i');
        if ($q !== '') {
            $porNumero = $numero !== null && (int) $row['numero'] === $numero;
            $porNombre = $qMin !== '' && mb_strpos(mb_strtolower((string) $row['nombre'], 'UTF-8'), $qMin) !== false;
            if (!$porNumero && !$porNombre) {
                continue;
            }
        }
        if (($tema !== '' && $row['theme_slug'] !== $tema) || ($modo !== '' && $row['modo'] !== $modo)
            || ($desde !== '' && $hora < $desde) || ($hasta !== '' && $hora > $hasta)) {
            continue;
        }
        $filas[] = [
            'id' => (int) $row['id'], 'numero' => (int) $row['numero'], 'etiqueta' => cb_feria_etiqueta((int) $row['numero']),
            'modo' => (string) $row['modo'], 'tema' => (string) $row['theme_slug'], 'nombre' => (string) $row['nombre'],
            'impresiones' => (int) $row['impresiones'], 'hora' => $hora, 'token' => (string) $row['access_token'],
        ];
    }
    $total = count($filas);
    $porPagina = max(1, min(100, $porPagina));
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = max(1, min($paginas, $pagina));
    return [
        'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas,
        'fotos' => array_slice($filas, ($pagina - 1) * $porPagina, $porPagina),
    ];
}

/**
 * Fotos de la feria que quedaron sin número (la reserva falló en la tablet o la foto llegó sin ella). No se pierden:
 * están en la fiesta de la feria como cualquier foto del kiosco; la galería avisa cuántas son y dónde verlas.
 */
function cb_feria_fotos_sin_numero(array $feria): int
{
    $stmt = cb_pdo()->prepare('SELECT COUNT(*) FROM cc_photos ph WHERE ph.party_id = ? AND ph.deleted_at IS NULL
        AND NOT EXISTS (SELECT 1 FROM cc_feria_fotos ff WHERE ff.photo_id = ph.id)');
    $stmt->execute([$feria['party_id']]);
    return (int) $stmt->fetchColumn();
}

/** Suma una impresión a una foto de la feria. Devuelve el total nuevo, o null si no es de esta feria. */
function cb_feria_sumar_impresion(array $feria, int $fotoId, int $copias = 1): ?int
{
    $copias = max(1, min(20, $copias));
    $pdo = cb_pdo();
    $upd = $pdo->prepare('UPDATE cc_feria_fotos SET impresiones = impresiones + ?, updated_at = ? WHERE id = ? AND feria_id = ? AND photo_id IS NOT NULL');
    $upd->execute([$copias, gmdate('Y-m-d H:i:s'), $fotoId, $feria['id']]);
    if ($upd->rowCount() !== 1) {
        return null;
    }
    $sel = $pdo->prepare('SELECT impresiones FROM cc_feria_fotos WHERE id = ?');
    $sel->execute([$fotoId]);
    return (int) $sel->fetchColumn();
}

/**
 * Para retention.php: las fiestas de feria cuyo plazo de fotos ya venció (fecha + retencion_dias).
 * Devuelve filas `id, public_slug` de `cc_parties`, igual que la consulta general de retención.
 */
function cb_ferias_vencidas(int $ahora): array
{
    if (!cb_ferias_listo()) {
        return [];
    }
    $rows = cb_pdo()->query('SELECT p.id, p.public_slug, f.fecha, f.retencion_dias
        FROM cc_ferias f JOIN cc_parties p ON p.id = f.party_id WHERE p.anonymized_at IS NULL')->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $corte = gmdate('Y-m-d', $ahora - max(1, (int) $row['retencion_dias']) * 86400);
        if ((string) $row['fecha'] <= $corte) {
            $out[] = ['id' => (int) $row['id'], 'public_slug' => (string) $row['public_slug']];
        }
    }
    return $out;
}
