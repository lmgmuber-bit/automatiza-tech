<?php
/** Persistencia aislada de avance. CAS por revisión; identidad serializada por CRM, sin IO externo bajo el candado. */
if (!defined('ABSPATH')) { exit; }
const AT_RG_SCHEMA = '2';
function at_rg_tabla(): string { global $wpdb; return $wpdb->prefix . 'at_resenas'; }
function at_rg_tabla_bajas(): string { global $wpdb; return $wpdb->prefix . 'at_resena_bajas'; }
function at_rg_tabla_wamids(): string { global $wpdb; return $wpdb->prefix . 'at_resena_wamids'; }
/** Rango indexado y descarte SQL de invitados. Sin migración adicional ni lecturas CRM por fila descartada. */
function at_rg_filas_pendientes(string $hasta): ?array {
    global $wpdb;
    // Primera invitación por rango indexado, o invitación tramitada para recordatorio.
    // El plazo del recordatorio y las demás guardas se vuelven a evaluar en elegibilidad/CAS.
    $filtros = []; $parametros = ['2026-11-19', $hasta];
    foreach (['correo','whatsapp'] as $canal) {
        foreach (['aceptado','entregado','leido','en_curso','desconocido'] as $estado) {
            // modo y canal deben pertenecer al MISMO elemento de intentos[].
            $filtros[] = 'JSON_CONTAINS(CASE WHEN JSON_VALID(datos) THEN datos ELSE \'{}\' END, %s, \'$.intentos\')=0';
            $parametros[] = wp_json_encode(['modo'=>'real', $canal=>['estado'=>$estado]]);
        }
    }
    $filas = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . at_rg_tabla() . ' WHERE JSON_VALID(datos)=1 AND ((fecha_termino BETWEEN %s AND %s
        AND ' . implode(' AND ', $filtros) . ') OR (
            JSON_CONTAINS(CASE WHEN JSON_VALID(datos) THEN datos ELSE \'{}\' END, \'{"modo":"real","correo":{"estado":"aceptado"}}\', \'$.intentos\')=1
            OR JSON_CONTAINS(CASE WHEN JSON_VALID(datos) THEN datos ELSE \'{}\' END, \'{"modo":"real","whatsapp":{"estado":"aceptado"}}\', \'$.intentos\')=1
            OR JSON_CONTAINS(CASE WHEN JSON_VALID(datos) THEN datos ELSE \'{}\' END, \'{"modo":"real","whatsapp":{"estado":"entregado"}}\', \'$.intentos\')=1
            OR JSON_CONTAINS(CASE WHEN JSON_VALID(datos) THEN datos ELSE \'{}\' END, \'{"modo":"real","whatsapp":{"estado":"leido"}}\', \'$.intentos\')=1
        ))
        AND JSON_CONTAINS(CASE WHEN JSON_VALID(datos) THEN datos ELSE \'{}\' END, \'{"modo":"real","tipo":"reenvio"}\', \'$.intentos\')=0
        AND COALESCE(JSON_LENGTH(JSON_EXTRACT(CASE WHEN JSON_VALID(datos) THEN datos ELSE \'{}\' END, \'$.resena_recibida\')),0)=0
        AND JSON_CONTAINS_PATH(CASE WHEN JSON_VALID(datos) THEN datos ELSE \'{}\' END, \'one\', \'$.anonimizado_at\')=0
        ORDER BY fecha_termino,id', ...$parametros));
    return $wpdb->last_error === '' && is_array($filas) ? $filas : null;
}
function at_rg_sql_esquema(): array {
    global $wpdb;
    $t = at_rg_tabla(); $b = at_rg_tabla_bajas(); $charset = $wpdb->get_charset_collate();
    return ["CREATE TABLE {$t} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 proyecto_clave varchar(40) NOT NULL,
 crm_id bigint(20) unsigned NOT NULL,
 plan_id bigint(20) unsigned NULL,
 fecha_termino date NULL,
 revision bigint(20) unsigned NOT NULL DEFAULT 0,
 datos longtext NOT NULL,
 abierto tinyint(1) NOT NULL DEFAULT 1,
 updated_at datetime NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY proyecto_clave (proyecto_clave),
 UNIQUE KEY plan_id (plan_id),
 KEY crm_id (crm_id),
 KEY fecha_termino (fecha_termino),
 KEY abierto (abierto)
) ENGINE=InnoDB {$charset};", "CREATE TABLE {$b} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 email varchar(254) NOT NULL DEFAULT '',
 telefono varchar(16) NOT NULL DEFAULT '',
 canal varchar(10) NOT NULL,
 alcance varchar(10) NOT NULL,
 fecha datetime NOT NULL,
 origen varchar(50) NOT NULL,
 nota varchar(1000) NOT NULL DEFAULT '',
 clave char(64) NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY clave (clave),
 KEY email (email(191)),
 KEY telefono (telefono)
) ENGINE=InnoDB {$charset};", 'CREATE TABLE ' . at_rg_tabla_wamids() . " (
 wamid varchar(256) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 resena_id bigint(20) unsigned NOT NULL,
 intento_id char(36) NOT NULL,
 modo varchar(6) NOT NULL,
 PRIMARY KEY  (wamid),
 KEY resena_id (resena_id)
) ENGINE=InnoDB {$charset};"];
}
/** No marca esquema por el retorno de dbDelta: comprueba columnas e índices requeridos. */
function at_rg_migrar(bool $manual = false): bool {
    // admin_init también corre en admin-ajax/admin-post sin sesión. WP-CLI manual no depende del usuario.
    if (!$manual && !current_user_can('manage_options')) { return false; }
    if ((string) get_option('at_resena_schema') === AT_RG_SCHEMA) { return true; }
    if (!$manual && get_transient('at_resena_migrar_intento')) { return false; }
    global $wpdb;
    if (!function_exists('dbDelta')) { require_once ABSPATH . 'wp-admin/includes/upgrade.php'; }
    foreach (at_rg_sql_esquema() as $sql) { dbDelta($sql); }
    $esperadas = [at_rg_tabla() => ['id','proyecto_clave','crm_id','plan_id','fecha_termino','revision','datos','abierto','updated_at'],
        at_rg_tabla_bajas() => ['id','email','telefono','canal','alcance','fecha','origen','nota','clave'],
        at_rg_tabla_wamids() => ['wamid','resena_id','intento_id','modo']];
    $ok = true;
    foreach ($esperadas as $t => $columnas) {
        $actuales = $wpdb->get_col("SHOW COLUMNS FROM {$t}");
        if (array_diff($columnas, (array) $actuales)) { $ok = false; }
    }
    foreach ([at_rg_tabla() => ['proyecto_clave', 'plan_id'], at_rg_tabla_bajas() => ['clave'], at_rg_tabla_wamids() => ['PRIMARY']] as $t => $indices) {
        $rows = $wpdb->get_results("SHOW INDEX FROM {$t}", ARRAY_A);
        foreach ($indices as $indice) {
            $r = array_values(array_filter((array) $rows, fn($r) => $r['Key_name'] === $indice && (int) $r['Non_unique'] === 0));
            if (count($r) !== 1 || $r[0]['Column_name'] !== ($indice === 'PRIMARY' ? 'wamid' : $indice) || $r[0]['Sub_part'] !== null) { $ok = false; }
        }
    }
    $cols = $wpdb->get_results('SHOW COLUMNS FROM ' . at_rg_tabla(), ARRAY_A);
    foreach (['proyecto_clave', 'crm_id', 'datos'] as $campo) {
        $r = array_values(array_filter((array) $cols, fn($c) => $c['Field'] === $campo));
        if (!$r || $r[0]['Null'] !== 'NO') { $ok = false; }
    }
    $bajas_cols = $wpdb->get_results('SHOW COLUMNS FROM ' . at_rg_tabla_bajas(), ARRAY_A);
    foreach (['origen' => 'varchar(50)', 'nota' => 'varchar(1000)'] as $campo => $tipo) {
        $r = array_values(array_filter((array) $bajas_cols, fn($c) => $c['Field'] === $campo));
        if (!$r || strtolower($r[0]['Type']) !== $tipo || $r[0]['Null'] !== 'NO') { $ok = false; }
    }
    $wa_cols = $wpdb->get_results('SHOW FULL COLUMNS FROM ' . at_rg_tabla_wamids(), ARRAY_A);
    $wamid_col = array_values(array_filter((array) $wa_cols, fn($c) => $c['Field'] === 'wamid'));
    if (!$wamid_col || strtolower($wamid_col[0]['Type']) !== 'varchar(256)' || $wamid_col[0]['Collation'] !== 'ascii_bin' || $wamid_col[0]['Null'] !== 'NO') { $ok = false; }
    $indices = $wpdb->get_results('SHOW INDEX FROM ' . at_rg_tabla(), ARRAY_A);
    foreach (['fecha_termino','abierto'] as $campo) {
        $r = array_values(array_filter((array) $indices, fn($i) => $i['Key_name'] === $campo));
        if (count($r) !== 1 || $r[0]['Column_name'] !== $campo || $r[0]['Sub_part'] !== null) { $ok = false; }
    }
    if (!$ok) { set_transient('at_resena_migrar_intento', 1, 300); return false; }
    // Conservado en cualquier fallo de lectura, índice o proyección del backfill.
    set_transient('at_resena_migrar_intento', 1, 300);
    // La proyección se rellena desde el JSON v1; no se reescribe ni elimina el historial original.
    $rows = $wpdb->get_results('SELECT * FROM ' . at_rg_tabla());
    if ($wpdb->last_error !== '' || !is_array($rows)) { return false; }
    foreach ($rows as $f) {
        try { $d = at_rg_datos($f); }
        catch (Throwable $e) { continue; } // abierto=1 por defecto: mantenimiento avisará una vez.
        if (!at_rg_indexar_wamids((int) $f->id, $d)) { return false; }
        if ($wpdb->query($wpdb->prepare('UPDATE ' . at_rg_tabla() . ' SET abierto=%d WHERE id=%d AND revision=%d', at_rg_abierto($d), $f->id, $f->revision)) === false) { return false; }
    }
    delete_transient('at_resena_migrar_intento');
    update_option('at_resena_schema', AT_RG_SCHEMA, false); return true;
}
add_action('admin_init', 'at_rg_migrar', 12);
function at_rg_fila(int $id): ?object {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_rg_tabla() . ' WHERE id=%d', $id)) ?: null;
}
/** Todas las filas para el resumen de envíos; null si la lectura falla. */
function at_rg_todas_filas(): ?array {
    global $wpdb;
    $filas = $wpdb->get_results('SELECT * FROM ' . at_rg_tabla() . ' ORDER BY id');
    return $wpdb->last_error ? null : (is_array($filas) ? $filas : []);
}
function at_rg_datos(object $fila): array {
    $d = json_decode($fila->datos, true);
    if (!is_array($d) || !is_array($d['intentos'] ?? null) || !is_array($d['termino'] ?? null)) {
        throw new RuntimeException('Registro de reseña ilegible; bloqueado para revisión.');
    }
    foreach ($d['intentos'] as $i) {
        if (!is_array($i) || !is_string($i['id'] ?? null) || !in_array($i['modo'] ?? '', ['real','prueba'], true)
            || !in_array($i['tipo'] ?? '', ['invitacion','reenvio'], true) || !is_string($i['creado_at'] ?? null)) {
            throw new RuntimeException('Intento ilegible; requiere revisión.');
        }
        new DateTimeImmutable($i['creado_at']);
        foreach (['correo','whatsapp'] as $canal) {
            $estados = $canal === 'correo' ? ['no_intentado','en_curso','aceptado','fallido','desconocido'] : ['no_intentado','en_curso','aceptado','fallido','desconocido','entregado','leido'];
            if (!is_array($i[$canal] ?? null) || !in_array($i[$canal]['estado'] ?? '', $estados, true) || !is_string($i[$canal]['at'] ?? null)) {
                throw new RuntimeException('Canal ilegible; requiere revisión.');
            }
            new DateTimeImmutable($i[$canal]['at']);
        }
    }
    if (!is_array($d['historial_escrito'] ?? null) || !is_array($d['permiso'] ?? null)) { throw new RuntimeException('Marcas ilegibles; requiere revisión.'); }
    return array_merge(at_rg_vacio(), $d);
}
function at_rg_cas(object $fila, array $d): bool {
    global $wpdb;
    $json = wp_json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || strlen($json) > 1048576) { return false; }
    $fecha = (string) ($d['termino']['fecha'] ?? '');
    return $wpdb->query($wpdb->prepare('UPDATE ' . at_rg_tabla() . " SET datos=%s, fecha_termino=NULLIF(%s,''), abierto=%d, revision=revision+1, updated_at=%s WHERE id=%d AND revision=%d",
        $json, at_rg_fecha($fecha) ? $fecha : '', at_rg_abierto($d), gmdate('Y-m-d H:i:s'), $fila->id, $fila->revision)) === 1;
}
/** Índice exacto y binario. Un wamid nunca se reasigna a otro intento. */
function at_rg_indexar_wamids(int $id, array $d): bool {
    if (!empty($d['anonimizado_at'])) { return false; }
    global $wpdb;
    foreach ($d['intentos'] as $i) {
        $w = (string) ($i['whatsapp']['wamid'] ?? '');
        if ($w === '') { continue; }
        if (!at_cc_wamid_valido($w)) { return false; }
        $t = at_rg_tabla_wamids();
        if ($wpdb->query($wpdb->prepare("INSERT INTO {$t} (wamid,resena_id,intento_id,modo) VALUES (%s,%d,%s,%s) ON DUPLICATE KEY UPDATE wamid=wamid", $w, $id, $i['id'], $i['modo'])) === false) { return false; }
        $r = $wpdb->get_row($wpdb->prepare("SELECT resena_id,intento_id,modo FROM {$t} WHERE wamid=%s", $w));
        if (!$r || (int) $r->resena_id !== $id || $r->intento_id !== $i['id'] || $r->modo !== $i['modo']) { return false; }
    }
    return true;
}
/** Reintento corto de una decisión pura. Nunca reintenta correo/webhook/nota. */
function at_rg_cambiar(int $id, callable $decidir): bool {
    for ($n = 0; $n < 8; $n++) {
        $f = at_rg_fila($id); if (!$f) { return false; }
        try { $antes = at_rg_datos($f); $despues = $decidir($antes); } catch (Throwable $e) { return false; }
        if (!is_array($despues)) { return false; }
        if ($antes === $despues || at_rg_cas($f, $despues)) { return true; }
        if ($n < 7) { usleep(random_int(5000, 40000)); }
    }
    return false;
}
/** CRM sin plan: crm:N. Al vincular el primer plan se conserva la clave crm:N y todas sus marcas.
 * Con más de un plan, la ficha obliga a elegir el proyecto; no crea otro registro implícito.
 */
function at_rg_proyecto(int $crm, int $plan = 0, bool $crear = true): ?object {
    if ($crm < 1 || $plan < 0 || (string) get_option('at_resena_schema') !== AT_RG_SCHEMA) { return null; }
    global $wpdb;
    if ($plan) {
        $p = at_pt_plan($plan);
        if (!$p || (int) $p->crm_cliente_id !== $crm) { return null; }
    }
    $t = at_rg_tabla(); $lock = 'at_rg_identidad_' . $crm;
    if (!$crear) {
        $filas = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE crm_id=%d ORDER BY id", $crm));
        foreach ((array) $filas as $f) { if ($plan && (int) $f->plan_id === $plan) { return $f; } }
        foreach ((array) $filas as $f) { if ($f->plan_id === null) { return $f; } }
        return !$plan && count((array) $filas) === 1 ? $filas[0] : null;
    }
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', $lock)) !== 1) { return null; }
    try {
        $filas = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE crm_id=%d ORDER BY id", $crm));
        if ($filas === null || $wpdb->last_error !== '') { return null; }
        foreach ($filas as $f) { if ($plan && (int) $f->plan_id === $plan) { return $f; } }
        foreach ($filas as $f) {
            if ($f->plan_id === null) {
                if ($plan) {
                    $n = $wpdb->query($wpdb->prepare("UPDATE {$t} SET plan_id=%d, revision=revision+1 WHERE id=%d AND plan_id IS NULL AND IS_USED_LOCK(%s)=CONNECTION_ID()", $plan, $f->id, $lock));
                    if ($n !== 1) { return null; }
                    return at_rg_fila((int) $f->id);
                }
                return $f;
            }
        }
        if (!$plan && count($filas) === 1) { return $filas[0]; }
        if (!$plan && $filas) { return null; }
        $clave = $plan ? 'plan:' . $plan : 'crm:' . $crm;
        $plan_sql = $plan ? (string) $plan : 'NULL';
        $json = wp_json_encode(at_rg_vacio());
        $n = $wpdb->query($wpdb->prepare("INSERT INTO {$t} (proyecto_clave,crm_id,plan_id,datos,updated_at) SELECT %s,%d,{$plan_sql},%s,%s WHERE IS_USED_LOCK(%s)=CONNECTION_ID()",
            $clave, $crm, $json, gmdate('Y-m-d H:i:s'), $lock));
        if ($n !== 1) { return null; }
        return at_rg_fila((int) $wpdb->insert_id);
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
}
/** La tabla propia es canónica. avance.termino se proyecta y se conserva en cada lectura. */
function at_rg_termino_plan(int $plan): ?array {
    if ((string) get_option('at_resena_schema') !== AT_RG_SCHEMA) { return null; }
    global $wpdb;
    $f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_rg_tabla() . ' WHERE plan_id=%d', $plan));
    if (!$f) { return null; }
    try { return at_rg_datos($f)['termino']; }
    catch (Throwable $e) { at_rg_avisar_ilegible((int) $f->id); return null; }
}
function at_rg_reflejar_termino(int $id): bool {
    $f = at_rg_fila($id); if (!$f || !$f->plan_id) { return true; }
    for ($n = 0; $n < 3; $n++) {
        $p = at_pt_plan((int) $f->plan_id); if (!$p) { return false; }
        $a = at_av_leer($p); $a['termino'] = at_rg_datos($f)['termino'];
        if (at_av_guardar_si_igual((int) $p->id, (string) $p->avance, $a)) { return true; }
    }
    return false;
}
