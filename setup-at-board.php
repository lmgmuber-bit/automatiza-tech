<?php
require_once __DIR__ . '/at-maintenance-guard.php';

define('WP_USE_THEMES', false);
require_once(dirname(__FILE__) . '/wp-load.php');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (PHP_SAPI !== 'cli' && !current_user_can('manage_options')) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Acceso Denegado</title></head><body>';
    echo '<h1>Acceso denegado</h1><p>Debes ser administrador WP para ejecutar este script.</p>';
    echo '</body></html>';
    exit;
}

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$prefix = $wpdb->prefix;

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Setup AT Board v8</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:900px;margin:20px auto;padding:20px;line-height:1.5}";
echo ".ok{color:#16a34a;font-weight:bold}.err{color:#dc2626;font-weight:bold}.info{color:#1A3A6B}";
echo "table{border-collapse:collapse;width:100%;margin:10px 0}th,td{border:1px solid #ddd;padding:8px;text-align:left}";
echo "th{background:#1A3A6B;color:white}h1{color:#1A3A6B}h2{color:#00A892;margin-top:24px}</style></head><body>";
echo "<h1>Setup AT Board v8 — Tablero Agil AutomatizaTech</h1>";
echo "<p class='info'>Crea las tablas <code>{$prefix}omnichannel_at_board</code> y <code>{$prefix}omnichannel_at_internas</code>,";
echo " mas la migracion inicial de los 6 clientes y 14 internas del seed.</p>";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

$tBoard = $prefix . 'omnichannel_at_board';
$tInt   = $prefix . 'omnichannel_at_internas';

echo "<h2>1. Tabla clientes (at_board)</h2>";
$sqlBoard = "CREATE TABLE {$tBoard} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    at_id VARCHAR(40) NOT NULL,
    nombre VARCHAR(191) NOT NULL,
    contacto VARCHAR(191) NOT NULL DEFAULT '',
    rubro VARCHAR(191) NOT NULL DEFAULT '',
    servicios TEXT DEFAULT NULL,
    paso TINYINT UNSIGNED NOT NULL DEFAULT 1,
    prioridad VARCHAR(3) NOT NULL DEFAULT 'P2',
    estado VARCHAR(12) NOT NULL DEFAULT 'progress',
    estado_label VARCHAR(80) NOT NULL DEFAULT '',
    ultima DATE NOT NULL DEFAULT '1970-01-01',
    notas TEXT DEFAULT NULL,
    fk_lead_id BIGINT UNSIGNED DEFAULT NULL,
    fk_propuesta_id BIGINT UNSIGNED DEFAULT NULL,
    fk_tech_client_id MEDIUMINT UNSIGNED DEFAULT NULL,
    fk_omni_client_id BIGINT UNSIGNED DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY at_id (at_id),
    KEY idx_paso (paso),
    KEY idx_estado (estado),
    KEY idx_prioridad (prioridad),
    KEY fk_lead (fk_lead_id),
    KEY fk_propuesta (fk_propuesta_id),
    KEY fk_tech (fk_tech_client_id),
    KEY fk_omni (fk_omni_client_id)
) $charset_collate;";
dbDelta($sqlBoard);
$existe = $wpdb->get_var("SHOW TABLES LIKE '{$tBoard}'") === $tBoard;
echo $existe ? "<p class='ok'>OK tabla {$tBoard} creada o ya existia.</p>" : "<p class='err'>ERROR al crear {$tBoard}: " . esc_html($wpdb->last_error) . "</p>";

echo "<h2>2. Tabla tareas internas (at_internas)</h2>";
$sqlInt = "CREATE TABLE {$tInt} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    at_id VARCHAR(40) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    asignado_a VARCHAR(60) NOT NULL DEFAULT 'Luis',
    tipo VARCHAR(20) NOT NULL DEFAULT 'ops',
    estado VARCHAR(12) NOT NULL DEFAULT 'backlog',
    prioridad VARCHAR(3) NOT NULL DEFAULT 'P2',
    ultima DATE NOT NULL DEFAULT '1970-01-01',
    notas TEXT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY at_id (at_id),
    KEY idx_estado (estado),
    KEY idx_asig (asignado_a),
    KEY idx_prioridad (prioridad)
) $charset_collate;";
dbDelta($sqlInt);
$existeInt = $wpdb->get_var("SHOW TABLES LIKE '{$tInt}'") === $tInt;
echo $existeInt ? "<p class='ok'>OK tabla {$tInt} creada o ya existia.</p>" : "<p class='err'>ERROR al crear {$tInt}: " . esc_html($wpdb->last_error) . "</p>";

echo "<h2>3. Migracion seed — 6 clientes</h2>";
$seedClientes = array(
    array('AT-CLI-001','KellsCapilar','Kellys Tirado','Estetica capilar','["Chatbot IA WhatsApp","Portal OmniCliente"]',6,'P3','done','Soporte activo','2026-06-12','Cliente en soporte y mejora continua. Bot v8 con Portal API. Pendiente: monitorizar consumo IA (ver ticket interno AT-CONSUMO-IA-001).',null,15,null,1),
    array('AT-CLI-002','Umbria Studio','Emmanuel & Rosa','Fotografia & audiovisual','["Sitio web premium","Backend WP headless","5 subdominios"]',3,'P1','wait','Esperando cliente','2026-06-10','Propuesta por fases definida: $4.400.000 CLP (50/50). Infra Opcion A hibrida. Esperando: aprobacion cliente + cuenta Meta Business + fotos originales.',null,11,null,null),
    array('AT-CLI-003','G&N MobileStore (GyN)','Gabriela Guillen','Accesorios moviles','["Sitio Web","Chatbot IA"]',3,'P2','progress','Propuesta enviada','2026-06-04','Propuesta Gamma generada en panel WP Automatiza Proposals. Pendiente: revision con cliente y avance a Paso 04 Diseno y desarrollo.',null,null,null,null),
    array('AT-CLI-004','Lead Scout (interno AT)','Luis Miguel','Prospeccion B2B (uso interno)','["Google Places API","CSV -> CRM"]',1,'P2','blocked','Bloqueado: API key Google','2026-07-04','Diagnostico hecho, script Python listo. Falta cargar API key Google Places en config.json para arrancar primera corrida.',null,null,null,null),
    array('AT-CLI-005','Comercial Spa/Salon (interno)','Luis Miguel','Pieza demo Veo Flow','["Comercial de pauta 30s"]',5,'P1','progress','Pieza lista - falta subir','2026-07-09','comercial-spa-final.mp4 renderizado (30s, 3 clips Omni Flash). Pendiente: subir a Drive / Meta Ads Manager. 5 variantes restantes por generar.',null,null,null,null),
    array('AT-CLI-006','Reel Diario @automatizatech (interno)','Luis Miguel','Cuenta propia IG','["Pipeline n8n Plan B"]',5,'P1','progress','Plan B n8n activo','2026-07-10','Workflow n8n Plan B activo con schedules habilitados. Dia-3 republicado limpio con media id 17985703272009675 tras fix de caption. Pendiente operativo: mantener prompts Flow con MANDATORY de logo AT y texto Spanish (Chile).',null,null,null,null),
);
echo "<table><tr><th>at_id</th><th>nombre</th><th>paso</th><th>estado</th><th>Resultado</th></tr>";
foreach ($seedClientes as $c) {
    $existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tBoard} WHERE at_id = %s", $c[0]));
    if ($existe) {
        echo "<tr><td>{$c[0]}</td><td>" . esc_html($c[1]) . "</td><td>{$c[4]}</td><td>{$c[7]}</td><td class='info'>ya existia</td></tr>";
        continue;
    }
    $ok = $wpdb->insert($tBoard, array(
        'at_id' => $c[0], 'nombre' => $c[1], 'contacto' => $c[2], 'rubro' => $c[3],
        'servicios' => $c[4], 'paso' => $c[5], 'prioridad' => $c[6], 'estado' => $c[7],
        'estado_label' => $c[8], 'ultima' => $c[9], 'notas' => $c[10],
        'fk_lead_id' => $c[11], 'fk_propuesta_id' => $c[12], 'fk_tech_client_id' => $c[13], 'fk_omni_client_id' => $c[14],
    ), array('%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%d','%d','%d','%d'));
    echo "<tr><td>{$c[0]}</td><td>" . esc_html($c[1]) . "</td><td>{$c[5]}</td><td>{$c[7]}</td><td class='" . ($ok ? 'ok' : 'err') . "'>" . ($ok ? 'insertado' : 'ERROR: ' . esc_html($wpdb->last_error)) . "</td></tr>";
}
echo "</table>";

echo "<h2>4. Migracion seed — 14 tareas internas</h2>";
$seedInt = array(
    array('AT-INT-001','Aprobar tablero v7.2 y subir a prod','Luis','ops','progress','P1','2026-07-10','Probar drag, edicion, dark mode, logos, tabs, escapes. Si OK: subir index.html + 3 logos + .htaccess + .htpasswd a automatizatech.cl/tablero/ y verificar en incognito que pida auth. Seeds reales van en data/ (no se suben a git).'),
    array('AT-INT-002','Configurar provider opencode-go en opencode.json','OpenCode Go','dev','todo','P1','2026-07-10',"Agregar entrada 'opencode-go' separada con API key de Go (GLM-5.2). DeepSeek queda como fallback para tareas chicas. Ruta: C:\\Users\\luis_\\opencode.json"),
    array('AT-INT-003','Prompt maestro Claude Orquestador v1.0','Claude','design','done','P1','2026-07-09','Jerarquia multi-agente, como delegar tickets, evaluar opiniones, manejar tareas directas de Luis. v1.0 entregado. Pendiente v1.1: refinar criterios de escalacion.'),
    array('AT-INT-004','Handoff text Codex (orquestador suplente)','Codex','design','done','P2','2026-07-09','Texto de handoff para cuando Claude se queda sin tokens. Codex toma el rol de orquestador suplente.'),
    array('AT-INT-005','Ledger multi-agente (reemplaza agent-log.md)','OpenCode Go','dev','done','P2','2026-07-10','Schema + state.json + ledger.jsonl inicializados en vault/30-Agent-Protocols/orchestration/. Reemplaza al agent-log.md planeado. Ver docs/AT-ORCH-001-gobierno.md.'),
    array('AT-INT-006','Iterar tablero a v8 con BD real omnichannel','OpenCode Go','dev','backlog','P3','2026-07-10','Reemplazar seeds por fetch a /api-tablero.php (proxy WP + GitHub Issues). Requiere token GitHub fine-grained + decision Opcion A/B de Luis. Ver docs/AT-TAB-003-fuente-verdad.md.'),
    array('AT-INT-007','Pipeline n8n Plan B - control prompts/logo/caption','Luis','ops','progress','P1','2026-07-10','Workflow n8n Plan B activo con schedules habilitados. Dia-3 republicado limpio con media id 17985703272009675. Vigilar que cada prompt Flow incluya MANDATORY de logo AT y texto Spanish (Chile).'),
    array('AT-INT-008','Lead Scout - cargar API key Google Places','Luis','ops','wait','P2','2026-07-04','Script Python listo. Falta cargar API key en config.json para arrancar primera corrida de prospeccion.'),
    array('AT-INT-009','Umbria Studio - esperando fotos + Meta Business','Luis','ops','wait','P1','2026-06-10','Propuesta $4.400.000 CLP (50/50). Esperando aprobacion cliente + cuenta Meta Business + fotos originales. 30 dias sin respuesta.'),
    array('AT-INT-010','Subir comercial-spa-final.mp4 a Drive/Meta Ads','Luis','ops','todo','P1','2026-07-09','comercial-spa-final.mp4 renderizado (30s, 3 clips Omni Flash). 5 variantes restantes por generar.'),
    array('AT-INT-011','Monitorizar consumo IA KellsCapilar','OpenCode Go','dev','backlog','P3','2026-06-12','Cliente en soporte activo. Bot v8 con Portal API. Pendiente: ver ticket interno AT-CONSUMO-IA-001.'),
    array('AT-INT-012','Reunion seguimiento G&N MobileStore','Luis','ops','todo','P2','2026-07-04','Propuesta Gamma enviada 2026-06-04. Pendiente: revision con cliente y avance a Paso 04 Diseno y desarrollo.'),
    array('AT-INT-013','Refinar prompt orquestador v1.1 - criterios escalacion','Claude','design','backlog','P2','2026-07-10','Agregar reglas claras: cuando subdelegar a DeepSeek vs Sonnet, cuando escalar a Luis, cuando OpenCode Go puede opinar vs debe obedecer.'),
    array('AT-INT-014','Documentar convenciones de codigo AT para OpenCode Go','OpenCode Go','docs','review','P2','2026-07-10','Prompt maestro v1.1 incluye rutas prohibidas, estructura de reporte, convenciones. Pendiente: revision de Claude para validar consistencia con orquestador.'),
);
echo "<table><tr><th>at_id</th><th>titulo</th><th>asig</th><th>estado</th><th>Resultado</th></tr>";
foreach ($seedInt as $t) {
    $existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tInt} WHERE at_id = %s", $t[0]));
    if ($existe) {
        echo "<tr><td>{$t[0]}</td><td>" . esc_html($t[1]) . "</td><td>{$t[2]}</td><td>{$t[4]}</td><td class='info'>ya existia</td></tr>";
        continue;
    }
    $ok = $wpdb->insert($tInt, array(
        'at_id' => $t[0], 'titulo' => $t[1], 'asignado_a' => $t[2], 'tipo' => $t[3],
        'estado' => $t[4], 'prioridad' => $t[5], 'ultima' => $t[6], 'notas' => $t[7],
    ), array('%s','%s','%s','%s','%s','%s','%s','%s'));
    echo "<tr><td>{$t[0]}</td><td>" . esc_html($t[1]) . "</td><td>{$t[2]}</td><td>{$t[4]}</td><td class='" . ($ok ? 'ok' : 'err') . "'>" . ($ok ? 'insertada' : 'ERROR: ' . esc_html($wpdb->last_error)) . "</td></tr>";
}
echo "</table>";

$totalB = $wpdb->get_var("SELECT COUNT(*) FROM {$tBoard}");
$totalI = $wpdb->get_var("SELECT COUNT(*) FROM {$tInt}");
echo "<h2>Resumen final</h2>";
echo "<p class='ok'>{$tBoard}: {$totalB} filas</p>";
echo "<p class='ok'>{$tInt}: {$totalI} filas</p>";

echo "<h2>Token API (siguiente paso)</h2>";
echo "<p>Genera un token aleatorio largo y agregalo a <code>wp-config-secrets.php</code>:</p>";
echo "<pre style='background:#f0f0f0;padding:12px;border-radius:6px;overflow-x:auto'>";
$tokenSugerido = bin2hex(random_bytes(24));
echo "define('AT_BOARD_TOKEN', '" . $tokenSugerido . "');";
echo "</pre>";
echo "<p class='info'>Copia ese token. Lo vas a cargar en el tablero ( boton Config / Token ) la primera vez que abras v8.</p>";
echo "<p class='err'>Por seguridad, elimina <code>setup-at-board.php</code> del hosting después de completar y verificar la instalación.</p>";

echo "<hr><p class='ok'><strong>Setup completo.</strong> Ahora sube <code>api-tablero.php</code> al hosting y abre el tablero v8.</p>";
echo "</body></html>";
