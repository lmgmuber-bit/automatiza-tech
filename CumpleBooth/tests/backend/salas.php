<?php
/**
 * Pruebas aisladas de las salas de ayudantes ("Tu Cumple en 3D", fase 3).
 * Cubre `public/lib.sala.php` + migración `014_salas_ayudantes` con SQLite
 * temporal. NO prueba el endpoint HTTP `public/sala.php` (tiene su propia
 * prueba de humo aparte). Contrato: app/design/sala-api.md (repo del juego).
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$tmp = sys_get_temp_dir() . '/cumpleclick-salas-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $db = $tmp . '/salas.sqlite';
    if (is_file($db)) { @unlink($db); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=db');
putenv('CC_PDO_DSN=sqlite:' . $tmp . '/salas.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('c', 64));
putenv('CC_PUBLIC_BASE_URL=https://example.test/cumpleclick');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos'); putenv('CC_STATE_DIR=' . $tmp . '/state'); putenv('CC_INVITATION_DIR=' . $tmp . '/invitations');
require dirname(__DIR__, 2) . '/public/lib.php';
require dirname(__DIR__, 2) . '/public/lib.sala.php';

$tests = 0;
function sala_check(bool $condition, string $message): void {
    global $tests;
    $tests++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
}
// cb_save_parties (bloque 12) necesita las columnas que agregan 003–007.
foreach (['001_initial', '002_theme_prompts', '003_invitations_and_plan', '004_gate_a_corrections', '005_theme_prompt_history', '006_public_leads', '007_event_album', '014_salas_ayudantes'] as $migracion) {
    (require dirname(__DIR__, 2) . '/database/migrations/' . $migracion . '.php')(cb_pdo());
}

// ── 1) cb_sala_crear: forma de la respuesta y nada en claro en la BD ───────
$creada = cb_sala_crear('Isidora', '203.0.113.5');
sala_check($creada['ok'] === true, 'cb_sala_crear responde ok');
$alfabetoPattern = '/^[' . preg_quote(CB_SALA_ALFABETO, '/') . ']{5}$/';
sala_check(is_string($creada['codigo']) && preg_match($alfabetoPattern, $creada['codigo']) === 1, 'código de 5 caracteres del alfabeto sin ambigüedades (sin 0/O/1/I)');
sala_check(preg_match('/^[a-f0-9]{32}$/', $creada['anfitrion']) === 1, 'token de anfitrión: 32 hex');
$filaCreada = cb_sala_buscar(cb_pdo(), $creada['codigo']);
sala_check($filaCreada !== null && $filaCreada['anfitrion_hash'] === cb_hash_token($creada['anfitrion']), 'anfitrion_hash guardado es cb_hash_token(token)');
sala_check(strpos(json_encode($filaCreada), $creada['anfitrion']) === false, 'el token de anfitrión en claro no aparece en ninguna columna de la fila');
sala_check(
    strpos((string) $filaCreada['identity_hmac'], '203.0.113.5') === false
    && $filaCreada['identity_hmac'] === cb_hmac('203.0.113.5', 'sala-ip'),
    'identity_hmac no contiene la IP en claro (es cb_hmac de la IP)'
);

// ── 2) Nombre del festejado: vacío, saneado y recorte a 24 ─────────────────
$vacio = cb_sala_crear('', '203.0.113.6');
$filaVacio = cb_sala_buscar(cb_pdo(), $vacio['codigo']);
sala_check($filaVacio['festejado'] === 'Princesa', 'nombre del festejado vacío cae al default "Princesa"');

$sucio = cb_sala_crear('  <b>Tía  Carla</b>  ', '203.0.113.7');
$filaSucio = cb_sala_buscar(cb_pdo(), $sucio['codigo']);
sala_check(
    $filaSucio['festejado'] !== '' && strpos($filaSucio['festejado'], '<') === false && strpos($filaSucio['festejado'], '>') === false,
    'nombre con <b> y espacios queda saneado, sin < ni > y con espacios colapsados'
);

$largo = cb_sala_crear(str_repeat('A', 40), '203.0.113.8');
$filaLargo = cb_sala_buscar(cb_pdo(), $largo['codigo']);
sala_check(mb_strlen($filaLargo['festejado']) === 24, 'nombre del festejado se recorta a 24 caracteres');

// ── 3) cb_sala_unirse ───────────────────────────────────────────────────────
$sala3 = cb_sala_crear('Isidora Tres', '203.0.113.10');
$u1 = cb_sala_unirse(strtolower($sala3['codigo']), 'Invitado Uno', '203.0.113.11');
sala_check($u1['ok'] === true, 'código de sala en minúsculas se normaliza y acepta');

$uInexistente = cb_sala_unirse('IIIII', 'Invitado', '203.0.113.12'); // 'I' nunca sale del alfabeto de generación
sala_check(!$uInexistente['ok'] && $uInexistente['error'] === 'sala_no_existe' && $uInexistente['http'] === 404, 'unirse a sala inexistente responde sala_no_existe (404)');

$uNombreVacio = cb_sala_unirse($sala3['codigo'], '', '203.0.113.13');
sala_check(!$uNombreVacio['ok'] && $uNombreVacio['error'] === 'nombre_invalido' && $uNombreVacio['http'] === 422, 'unirse con nombre vacío responde nombre_invalido (422)');

$uNombreLargo = cb_sala_unirse($sala3['codigo'], str_repeat('N', 40), '203.0.113.14');
sala_check($uNombreLargo['ok'] && mb_strlen($uNombreLargo['nombre']) === 16, 'nombre de ayudante de 40 caracteres se recorta a 16');
$stmtAyudante3 = cb_pdo()->prepare('SELECT * FROM cc_sala_ayudantes WHERE id=?');
$stmtAyudante3->execute([$uNombreLargo['id']]);
$filaAyudante3 = $stmtAyudante3->fetch();
sala_check(
    $filaAyudante3 !== false
    && $filaAyudante3['token_hash'] === cb_hash_token($uNombreLargo['ayudante'])
    && strpos(json_encode($filaAyudante3), $uNombreLargo['ayudante']) === false,
    'el token del ayudante no queda en claro en la BD (solo token_hash)'
);

// ── 4) Cupo: 12 entran, el 13.º rebota ──────────────────────────────────────
$sala4 = cb_sala_crear('Isidora Cupo', '203.0.113.20');
for ($i = 1; $i <= 12; $i++) {
    $join = cb_sala_unirse($sala4['codigo'], 'Invitado ' . $i, '203.0.113.' . (20 + $i));
    sala_check($join['ok'] === true, "ayudante $i de 12 entra dentro del cupo");
}
$join13 = cb_sala_unirse($sala4['codigo'], 'Invitado 13', '203.0.113.99');
sala_check(!$join13['ok'] && $join13['error'] === 'sala_llena' && $join13['http'] === 409, 'el 13.º ayudante rebota con sala_llena (409), cupo=12');

// ── 5) cb_sala_accion: validaciones y enfriamientos por tipo ────────────────
$sala5 = cb_sala_crear('Isidora Accion', '203.0.113.40');
$ayudante5 = cb_sala_unirse($sala5['codigo'], 'Ayudante Cinco', '203.0.113.41');

$accionTipoInvalido = cb_sala_accion($sala5['codigo'], $ayudante5['ayudante'], 'baile');
sala_check(!$accionTipoInvalido['ok'] && $accionTipoInvalido['error'] === 'tipo_invalido' && $accionTipoInvalido['http'] === 422, 'tipo de acción inválido responde tipo_invalido (422)');

$tokenFalso5 = str_repeat('a', 32);
$accionTokenInvalido = cb_sala_accion($sala5['codigo'], $tokenFalso5, 'animo');
sala_check(!$accionTokenInvalido['ok'] && $accionTokenInvalido['error'] === 'ayudante_invalido' && $accionTokenInvalido['http'] === 403, 'token de ayudante inválido responde ayudante_invalido (403)');

$animo1 = cb_sala_accion($sala5['codigo'], $ayudante5['ayudante'], 'animo');
sala_check($animo1['ok'] && $animo1['seq'] === 1 && $animo1['esperas']['animo'] === 3, 'primera "animo": seq=1 y esperas.animo=3');

$animo2 = cb_sala_accion($sala5['codigo'], $ayudante5['ayudante'], 'animo');
sala_check(
    !$animo2['ok'] && $animo2['error'] === 'espera' && $animo2['http'] === 429 && $animo2['espera'] >= 1 && $animo2['espera'] <= 3,
    'segunda "animo" inmediata responde espera (429) con espera entre 1 y 3'
);

$copos1 = cb_sala_accion($sala5['codigo'], $ayudante5['ayudante'], 'copos');
sala_check($copos1['ok'] && $copos1['seq'] === 2, '"copos" inmediatamente después de "animo" sí pasa (enfriamientos por tipo) y avanza seq a 2');

$ayuda1 = cb_sala_accion($sala5['codigo'], $ayudante5['ayudante'], 'ayuda');
sala_check($ayuda1['ok'] && $ayuda1['esperas']['ayuda'] === 20, '"ayuda" registra un enfriamiento de 20s');

// ── 6) Tope por sala: 60 acciones en el último minuto ───────────────────────
$sala6 = cb_sala_crear('Isidora Saturada', '203.0.113.50');
$sala6Id = (int) cb_sala_buscar(cb_pdo(), $sala6['codigo'])['id'];
$ayudante6 = cb_sala_unirse($sala6['codigo'], 'Ayudante Seis', '203.0.113.51');
$ahoraTope = time();
$insertAccion = cb_pdo()->prepare('INSERT INTO cc_sala_acciones (sala_id,seq,ayudante_id,tipo,created_at,created_ts) VALUES (?,?,?,?,?,?)');
for ($i = 1; $i <= 60; $i++) {
    $insertAccion->execute([$sala6Id, $i, (int) $ayudante6['id'], 'animo', gmdate('Y-m-d H:i:s', $ahoraTope), $ahoraTope]);
}
$saturada = cb_sala_accion($sala6['codigo'], $ayudante6['ayudante'], 'animo');
sala_check(!$saturada['ok'] && $saturada['error'] === 'sala_saturada' && $saturada['http'] === 429, 'con 60 acciones en el último minuto, la siguiente responde sala_saturada (429)');

// ── 7) cb_sala_acciones: lectura del anfitrión ──────────────────────────────
$sala7 = cb_sala_crear('Isidora Lista', '203.0.113.60');
$ayudante7 = cb_sala_unirse($sala7['codigo'], 'Ayudante Siete', '203.0.113.61');
$accion7a = cb_sala_accion($sala7['codigo'], $ayudante7['ayudante'], 'animo');
$accion7b = cb_sala_accion($sala7['codigo'], $ayudante7['ayudante'], 'copos');

$lista = cb_sala_acciones($sala7['codigo'], $sala7['anfitrion'], 0);
sala_check($lista['ok'] && count($lista['acciones']) === 2, 'con desde=0 trae las acciones registradas');
sala_check(
    $lista['acciones'][0]['seq'] === $accion7a['seq']
    && $lista['acciones'][0]['tipo'] === 'animo'
    && $lista['acciones'][0]['nombre'] === $ayudante7['nombre']
    && $lista['acciones'][0]['id'] === (int) $ayudante7['id']
    && $lista['acciones'][1]['seq'] === $accion7b['seq']
    && $lista['acciones'][1]['tipo'] === 'copos',
    'las acciones vienen ordenadas por seq, con tipo, nombre del ayudante e id'
);
sala_check($lista['ayudantes'] === 1, 'cb_sala_acciones informa el conteo de ayudantes de la sala');

$listaVacia = cb_sala_acciones($sala7['codigo'], $sala7['anfitrion'], $lista['seq']);
sala_check($listaVacia['ok'] && $listaVacia['acciones'] === [], 'con desde=último seq no trae acciones nuevas');

$listaTokenFalso = cb_sala_acciones($sala7['codigo'], str_repeat('e', 32), 0);
sala_check(!$listaTokenFalso['ok'] && $listaTokenFalso['error'] === 'anfitrion_invalido' && $listaTokenFalso['http'] === 403, 'token de anfitrión falso en cb_sala_acciones responde anfitrion_invalido (403)');

// ── 8) cb_sala_resumen + cb_sala_estado ─────────────────────────────────────
$sala8 = cb_sala_crear('Isidora Resumen', '203.0.113.70');

$resumenSucio = cb_sala_resumen($sala8['codigo'], $sala8['anfitrion'], [
    'nombre' => 'Isidora',
    'con espacio' => 'clave inválida, se descarta',
    'objetivo' => ['no', 'escalar'],
    'copos' => 5,
    'activo' => true,
]);
sala_check($resumenSucio['ok'] === true, 'guardar resumen con claves/valores mixtos responde ok');
$estadoConResumen = cb_sala_estado($sala8['codigo']);
sala_check(
    isset($estadoConResumen['resumen']['nombre'], $estadoConResumen['resumen']['copos'], $estadoConResumen['resumen']['activo'])
    && !array_key_exists('con espacio', $estadoConResumen['resumen'])
    && !array_key_exists('objetivo', $estadoConResumen['resumen']),
    'el resumen solo guarda claves [a-z_]{1,24} con valores escalares (clave con espacio y valor array se descartan)'
);

$resumenTextoLargo = cb_sala_resumen($sala8['codigo'], $sala8['anfitrion'], ['mision' => str_repeat('x', 700)]);
sala_check($resumenTextoLargo['ok'] === true, 'guardar resumen con texto largo responde ok');
$estadoTextoLargo = cb_sala_estado($sala8['codigo']);
sala_check(mb_strlen($estadoTextoLargo['resumen']['mision']) === 160, 'un string de 700 caracteres en el resumen se recorta a 160');

$resumenEnorme = [
    'campo_uno' => str_repeat('a', 160),
    'campo_dos' => str_repeat('b', 160),
    'campo_tres' => str_repeat('c', 160),
    'campo_cuatro' => str_repeat('d', 160),
];
$resumenRechazado = cb_sala_resumen($sala8['codigo'], $sala8['anfitrion'], $resumenEnorme);
sala_check(!$resumenRechazado['ok'] && $resumenRechazado['error'] === 'resumen_invalido' && $resumenRechazado['http'] === 422, 'un resumen cuyo JSON supera 600 bytes responde resumen_invalido (422)');

$estadoSinToken = cb_sala_estado($sala8['codigo']);
sala_check(
    $estadoSinToken['tu_nombre'] === null && $estadoSinToken['esperas'] === ['animo' => 0, 'copos' => 0, 'ayuda' => 0],
    'cb_sala_estado sin token: esperas en 0 para los tres tipos y tu_nombre null'
);

$ayudante8 = cb_sala_unirse($sala8['codigo'], 'Ayudante Ocho', '203.0.113.71');
cb_pdo()->prepare('UPDATE cc_sala_ayudantes SET last_seen_at=? WHERE id=?')->execute(['2000-01-01 00:00:00', $ayudante8['id']]);
$estadoConToken = cb_sala_estado($sala8['codigo'], $ayudante8['ayudante']);
sala_check($estadoConToken['tu_nombre'] === $ayudante8['nombre'], 'cb_sala_estado con token devuelve tu_nombre');
$stmtLastSeen = cb_pdo()->prepare('SELECT last_seen_at FROM cc_sala_ayudantes WHERE id=?');
$stmtLastSeen->execute([$ayudante8['id']]);
sala_check((string) $stmtLastSeen->fetchColumn() > '2000-01-01 00:00:00', 'cb_sala_estado con token actualiza last_seen_at del ayudante');

// ── 9) cb_sala_cerrar ────────────────────────────────────────────────────────
$sala9 = cb_sala_crear('Isidora Cierre', '203.0.113.80');
$cierre = cb_sala_cerrar($sala9['codigo'], $sala9['anfitrion']);
sala_check($cierre['ok'] === true, 'cb_sala_cerrar responde ok');

$estadoCerrada = cb_sala_estado($sala9['codigo']);
sala_check($estadoCerrada['ok'] && $estadoCerrada['estado'] === 'cerrada', 'después de cerrar, cb_sala_estado reporta estado=cerrada');

$unirseCerrada = cb_sala_unirse($sala9['codigo'], 'Invitado Tarde', '203.0.113.81');
sala_check(!$unirseCerrada['ok'] && $unirseCerrada['error'] === 'sala_cerrada' && $unirseCerrada['http'] === 409, 'unirse a una sala cerrada responde sala_cerrada (409)');

$accionCerrada = cb_sala_accion($sala9['codigo'], str_repeat('f', 32), 'animo');
sala_check(!$accionCerrada['ok'] && $accionCerrada['error'] === 'sala_cerrada' && $accionCerrada['http'] === 409, 'una acción en sala cerrada responde sala_cerrada (409), antes de validar el ayudante');

// ── 10) Vencimiento: sala vieja se reporta cerrada y luego se limpia ────────
$sala10a = cb_sala_crear('Isidora Vencida', '203.0.113.90');
cb_pdo()->prepare('UPDATE cc_salas SET expires_at=? WHERE codigo=?')->execute([gmdate('Y-m-d H:i:s', time() - 3600), $sala10a['codigo']]);
$estadoVencida = cb_sala_estado($sala10a['codigo']);
sala_check($estadoVencida['ok'] && $estadoVencida['estado'] === 'cerrada', 'una sala con expires_at en el pasado se reporta cerrada sin necesitar limpieza');

$sala10b = cb_sala_crear('Isidora Borrar', '203.0.113.91');
$sala10bId = (int) cb_sala_buscar(cb_pdo(), $sala10b['codigo'])['id'];
$ayudante10b = cb_sala_unirse($sala10b['codigo'], 'Ayudante Diez', '203.0.113.92');
cb_sala_accion($sala10b['codigo'], $ayudante10b['ayudante'], 'animo');
cb_pdo()->prepare('UPDATE cc_salas SET expires_at=? WHERE id=?')->execute([gmdate('Y-m-d H:i:s', time() - 20 * 3600), $sala10bId]);

$countSql = static function (string $table, int $salaOrId, bool $porId = false) use ($sala10bId): int {
    $columna = $porId ? 'id' : 'sala_id';
    $valor = $porId ? $sala10bId : $sala10bId;
    $stmt = cb_pdo()->prepare("SELECT COUNT(*) FROM $table WHERE $columna=?");
    $stmt->execute([$valor]);
    return (int) $stmt->fetchColumn();
};
sala_check(
    $countSql('cc_salas', $sala10bId, true) === 1
    && $countSql('cc_sala_ayudantes', $sala10bId) === 1
    && $countSql('cc_sala_acciones', $sala10bId) === 1,
    'antes de limpiar: la sala vencida hace 20h, su ayudante y su acción existen'
);

cb_sala_limpiar(cb_pdo(), true);

sala_check(
    $countSql('cc_salas', $sala10bId, true) === 0
    && $countSql('cc_sala_ayudantes', $sala10bId) === 0
    && $countSql('cc_sala_acciones', $sala10bId) === 0,
    'cb_sala_limpiar(forzar=true) borra la sala vencida hace 20h junto con sus ayudantes y acciones'
);

// ── 11) cb_sala_codigo_valido ────────────────────────────────────────────────
sala_check(cb_sala_codigo_valido('abcde') === 'ABCDE', 'código en minúsculas se normaliza a mayúsculas');
sala_check(cb_sala_codigo_valido('ABCD') === null, 'código de 4 caracteres se rechaza');
sala_check(cb_sala_codigo_valido('ABCDEF') === null, 'código de 6 caracteres se rechaza');
sala_check(cb_sala_codigo_valido('ABC0O') === null, 'código con dígito fuera de 2-9 se rechaza');
sala_check(cb_sala_codigo_valido(123) === null, 'un entero no es un código válido');
sala_check(cb_sala_codigo_valido(null) === null, 'null no es un código válido');
sala_check(cb_sala_codigo_valido(['ABCDE']) === null, 'un array no es un código válido');

// ── 12) cb_sala_fotos: fotos del kiosco para el mundo del juego, con PIN ─────
sala_check(is_array($creada['hosts']), 'cb_sala_crear devuelve la lista de IPs LAN del servidor (puede ser vacía)');
putenv('CC_PUBLIC_BASE_URL=https://example.test/cumpleclick');
$fiestas = ['parties' => [
    'fiesta-pin' => ['nombre' => 'Isidora', 'tema' => 'hielo', 'fecha' => '2026-09-20', 'activa' => true, 'invitados' => [['name' => 'Mateo', 'g' => 'm']],
        'frameBox' => ['x' => .3, 'y' => .3, 'w' => .4, 'h' => .3], 'galeriaPin' => '1234', 'creada' => '2026-09-06 10:00:00'],
    'fiesta-sin-pin' => ['nombre' => 'Tomás', 'tema' => 'hielo', 'fecha' => '2026-09-21', 'activa' => true, 'invitados' => [],
        'frameBox' => ['x' => .3, 'y' => .3, 'w' => .4, 'h' => .3], 'creada' => '2026-09-06 10:00:00'],
]];
sala_check(cb_save_parties($fiestas), 'fiestas de prueba guardadas');
$idFiestaPin = cb_party_db_id('fiesta-pin');
sala_check(is_int($idFiestaPin), 'la fiesta con PIN tiene id en la BD');
$insFoto = cb_pdo()->prepare('INSERT INTO cc_photos (party_id,access_token,storage_key,original_name,byte_size,width,height,sha256,created_at,deleted_at) VALUES (?,?,?,?,?,?,?,?,?,NULL)');
$insFoto->execute([$idFiestaPin, str_repeat('a', 32), 'fiesta-pin/2026/09/' . str_repeat('a', 32) . '.png', 'vieja.png', 1000, 1080, 1920, str_repeat('0', 64), '2026-09-06 10:00:00']);
$insFoto->execute([$idFiestaPin, str_repeat('b', 32), 'fiesta-pin/2026/09/' . str_repeat('b', 32) . '.png', 'nueva.png', 1000, 1080, 1920, str_repeat('1', 64), '2026-09-06 11:00:00']);
$sala12 = cb_sala_crear('Isidora', '203.0.113.12');
$fotosMal = cb_sala_fotos($sala12['codigo'], $sala12['anfitrion'], 'fiesta-pin', '0000');
sala_check(!$fotosMal['ok'] && $fotosMal['error'] === 'pin_invalido' && $fotosMal['http'] === 403, 'PIN incorrecto → pin_invalido 403');
$fotosSinPin = cb_sala_fotos($sala12['codigo'], $sala12['anfitrion'], 'fiesta-sin-pin', '1234');
sala_check(!$fotosSinPin['ok'] && $fotosSinPin['error'] === 'sin_pin' && $fotosSinPin['http'] === 409, 'fiesta sin PIN de galería → sin_pin 409');
$fotosNoExiste = cb_sala_fotos($sala12['codigo'], $sala12['anfitrion'], 'no-existe', '1234');
sala_check(!$fotosNoExiste['ok'] && $fotosNoExiste['error'] === 'fiesta_no_existe' && $fotosNoExiste['http'] === 404, 'fiesta inexistente → 404');
$fotosToken = cb_sala_fotos($sala12['codigo'], str_repeat('f', 32), 'fiesta-pin', '1234');
sala_check(!$fotosToken['ok'] && $fotosToken['error'] === 'anfitrion_invalido', 'token de anfitrión falso → anfitrion_invalido');
$fotosOk = cb_sala_fotos($sala12['codigo'], $sala12['anfitrion'], 'fiesta-pin', '1234');
sala_check($fotosOk['ok'] === true && $fotosOk['total'] === 2 && count($fotosOk['fotos']) === 2, 'PIN correcto → 2 fotos');
sala_check($fotosOk['fotos'][0]['nombre'] === 'nueva.png' && $fotosOk['fotos'][1]['nombre'] === 'vieja.png', 'las fotos vienen de la más nueva a la más vieja');
sala_check($fotosOk['fotos'][0]['ver'] === 'ver.php?t=' . str_repeat('b', 32) . '&download=inline' && $fotosOk['fotos'][0]['w'] === 1080, 'cada foto trae su ruta ver.php relativa y sus medidas');
sala_check(strpos(json_encode($fotosOk), 'storage_key') === false && strpos(json_encode($fotosOk), 'fiesta-pin/2026') === false, 'no se filtran rutas de almacenamiento');
$fotosMax = cb_sala_fotos($sala12['codigo'], $sala12['anfitrion'], 'fiesta-pin', '1234', 1);
sala_check(count($fotosMax['fotos']) === 1 && $fotosMax['total'] === 2, 'max=1 devuelve una foto pero total sigue en 2');

fwrite(STDOUT, "OK $tests checks salas\n");
