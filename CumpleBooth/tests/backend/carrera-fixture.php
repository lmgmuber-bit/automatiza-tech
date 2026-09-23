<?php
/** Solo para CLI y servidor PHP de pruebas. Nunca subir al webroot. */
if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) { exit(2); }
$carreraRoot = dirname(__DIR__, 2);
$carreraDb = getenv('CARRERA_TEST_DB');
if (!$carreraDb) { throw new RuntimeException('Falta CARRERA_TEST_DB (archivo temporal, no una base real).'); }
putenv('CC_STORAGE_MODE=db');
putenv('CC_PDO_DSN=sqlite:' . $carreraDb);
// Clave efímera exclusiva del proceso de QA. No usa ni copia credenciales reales.
if (!getenv('CC_APP_HMAC_KEY')) { putenv('CC_APP_HMAC_KEY=' . bin2hex(random_bytes(32))); }
putenv('CC_PUBLIC_BASE_URL=http://127.0.0.1:18483');
require_once $carreraRoot . '/public/lib.php';
require_once $carreraRoot . '/public/lib.puntajes.php';
$pdo = cb_pdo();
$pdo->exec('PRAGMA busy_timeout=5000');
if ($pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='cc_sala_carreras'")->fetchColumn() == 0) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS cc_schema_migrations(version TEXT PRIMARY KEY, applied_at TEXT)');
    foreach (['001_initial','002_theme_prompts','003_invitations_and_plan','004_gate_a_corrections',
        '005_theme_prompt_history','006_public_leads','007_event_album','008_event_profiles','010_baby_shower_predictions',
        '014_salas_ayudantes','018_puntajes','021_sala_carreras'] as $m) {
        (require $carreraRoot . '/database/migrations/' . $m . '.php')($pdo);
        $pdo->prepare('INSERT INTO cc_schema_migrations VALUES(?,?)')->execute([$m, gmdate('c')]);
    }
    foreach (['carrera-qa','carrera-qa-dos','carrera-qa-tres','carrera-qa-cuatro','carrera-celular','carrera-tablet','carrera-compacto'] as $slug) {
        $pdo->prepare('INSERT INTO cc_parties(public_slug,birthday_person_name,theme_slug,active,created_at,updated_at) VALUES(?,?,?,1,?,?)')
            ->execute([$slug,'Fiesta de prueba',cb_juegos()['circuito']['tema'],gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
    }
    // Aurora de Cristal usa la misma sala, pero en fiestas de hielo (2026-09-13).
    foreach (['aurora-qa','aurora-qa-dos','aurora-celular','aurora-tablet'] as $slug) {
        $pdo->prepare('INSERT INTO cc_parties(public_slug,birthday_person_name,theme_slug,active,created_at,updated_at) VALUES(?,?,?,1,?,?)')
            ->execute([$slug,'Samantha',cb_juegos()['aurora']['tema'],gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
    }
}
