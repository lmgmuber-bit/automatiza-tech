<?php
// Uso: AT_WP_LOAD=<wp-load.php del sitio de prueba> php tests/prospeccion/esquema-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
$t = at_pro_tabla();
ok($t === $wpdb->prefix . 'at_prospectos', 'nombre de la tabla con prefijo');
$wpdb->query("DROP TABLE IF EXISTS {$t}");
delete_option('at_pro_db_version');
at_pro_migrar_esquema();
at_pro_migrar_esquema();
$cols = $wpdb->get_col("SHOW COLUMNS FROM {$t}", 0);
$esperadas = ['id','negocio','rubro','comuna','web','dominio','telefono','correo','fuente','senales','servicio_sugerido','estado','canal','token','crm_cliente_id','contactado_at','respondio_at','formulario_at','baja_at','notas','created_at','updated_at'];
ok($cols === $esperadas, 'columnas exactas del spec: ' . implode(',', array_diff($esperadas, $cols)) . '|' . implode(',', array_diff($cols, $esperadas)));
$idx = [];
foreach ($wpdb->get_results("SHOW INDEX FROM {$t}", ARRAY_A) as $i) { $idx[$i['Key_name']] = ['col' => $i['Column_name'], 'unico' => (int) $i['Non_unique'] === 0]; }
ok(isset($idx['token']) && $idx['token']['unico'], 'token único');
ok(isset($idx['dominio']) && $idx['dominio']['unico'], 'dominio único');
ok(isset($idx['estado']) && !$idx['estado']['unico'], 'índice estado');
ok(isset($idx['telefono']) && !$idx['telefono']['unico'], 'índice telefono');
ok(isset($idx['correo']) && !$idx['correo']['unico'], 'índice correo (Calibre H6)');
ok(get_option('at_pro_db_version') === '1', 'at_pro_db_version = 1');
$cnt = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}");
$wpdb->insert($t, ['negocio' => 'Prueba', 'dominio' => null, 'token' => 'AAAAAAAAAAAAAAAA', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
$wpdb->insert($t, ['negocio' => 'Prueba2', 'dominio' => null, 'token' => 'BBBBBBBBBBBBBBBB', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}") === 2, 'dos filas sin dominio caben (NULL permitido)');
ok($wpdb->get_var("SELECT estado FROM {$t} LIMIT 1") === 'por_contactar', 'estado por defecto por_contactar');
at_pro_migrar_esquema();
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}") === 2, 'migrar de nuevo no borra filas');
$wpdb->query("DELETE FROM {$t}");
fin();
