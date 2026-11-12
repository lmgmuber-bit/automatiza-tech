<?php
// Uso: AT_WP_LOAD=<wp-load.php> php tests/prospeccion/crm-avisos-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
// En local, un mu-plugin antepone «[LOCAL] » al asunto; en producción el asunto sale tal cual.
function at_pro_test_asunto($s) { return preg_replace('/^\[LOCAL\] /', '', (string) $s); }
at_pro_migrar_esquema();
$t = at_pro_tabla();
$crm = $wpdb->prefix . 'crm_clientes';
$wpdb->query("DELETE FROM {$t}");
$wpdb->query("DELETE FROM {$crm} WHERE email LIKE '%@prueba.test'");

// Capturas: ningún correo ni petición HTTP sale de verdad.
$correos = [];
$http = [];
add_filter('pre_wp_mail', function ($null, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);
add_filter('pre_http_request', function ($pre, $args, $url) use (&$http) { $http[] = ['url' => $url, 'args' => $args]; return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => '{}', 'headers' => [], 'cookies' => [], 'filename' => null]; }, 10, 3);

$a = at_pro_crear(['negocio' => 'Café Sur', 'web' => 'cafesur.test', 'rubro' => 'Cafetería']);
$p = at_pro_obtener($a);
$persona = ['nombre' => 'Ana Prueba', 'email' => 'Ana@Prueba.test', 'telefono' => '+56955507777'];

// 1. correo nuevo -> prospecto con origen prospeccion
$id = at_pro_crm_prospecto($persona, $p);
$f = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$crm} WHERE id = %d", $id), ARRAY_A);
ok($id > 0 && $f['tipo'] === 'prospecto' && $f['origen'] === 'prospeccion' && $f['estado'] === 'nuevo', 'correo nuevo: fila prospecto con origen prospeccion');
ok($f['empresa'] === 'Café Sur' && $f['nombre'] === 'Ana Prueba' && $f['email'] === 'ana@prueba.test' && $f['telefono'] === '+56955507777' && $f['rubro'] === 'Cafetería', 'empresa, nombre, correo, teléfono y rubro guardados');
ok(at_pro_crm_prospecto($persona, $p) === $id && (int) $wpdb->get_var("SELECT COUNT(*) FROM {$crm} WHERE email = 'ana@prueba.test'") === 1, 'llamarlo dos veces no crea otra fila');

// 2. cliente existente: no se baja ni se pisa el origen
$wpdb->insert($crm, ['tipo' => 'cliente', 'estado' => 'contratado', 'nombre' => 'Cliente Real', 'email' => 'cliente@prueba.test', 'origen' => 'referido']);
$cid = (int) $wpdb->insert_id;
$r = at_pro_crm_prospecto(['nombre' => 'Otro Nombre', 'email' => 'CLIENTE@prueba.test', 'telefono' => ''], $p);
$f = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$crm} WHERE id = %d", $cid), ARRAY_A);
ok($r === $cid && $f['tipo'] === 'cliente' && $f['origen'] === 'referido' && $f['estado'] === 'contratado' && $f['nombre'] === 'Cliente Real', 'cliente existente: mismo id, tipo y origen intactos');
$wpdb->insert($crm, ['tipo' => 'prospecto', 'estado' => 'nuevo', 'nombre' => 'Sin origen', 'email' => 'sinorigen@prueba.test', 'origen' => '']);
$sid = (int) $wpdb->insert_id;
at_pro_crm_prospecto(['nombre' => 'Sin origen', 'email' => 'sinorigen@prueba.test', 'telefono' => ''], $p);
ok($wpdb->get_var($wpdb->prepare("SELECT origen FROM {$crm} WHERE id = %d", $sid)) === 'prospeccion', 'prospecto existente sin origen: se completa con prospeccion');

// 3. at_pro_al_llenar: guarda crm_cliente_id y manda el correo con el asunto exacto
$correos = []; $http = [];
at_pro_cambiar_estado($a, 'contactado', 'whatsapp');
$p = at_pro_obtener($a);
at_pro_al_llenar($p, ['nombre' => 'Beto Prueba', 'email' => 'beto@prueba.test', 'telefono' => '+56955506666']);
ok(count($correos) === 1 && at_pro_test_asunto($correos[0]['subject']) === 'Prospección: Café Sur llenó el formulario', 'un correo con el asunto exacto');
$to = $correos[0]['to'] ?? '';
$to = is_array($to) ? implode(',', $to) : $to;
ok(is_email($to), 'va a un correo válido (en local un mu-plugin puede redirigirlo)');
ok(strpos($correos[0]['message'], 'Café Sur') !== false && strpos($correos[0]['message'], 'Beto Prueba') !== false && strpos($correos[0]['message'], 'beto@prueba.test') !== false && strpos($correos[0]['message'], 'whatsapp') !== false, 'el cuerpo trae negocio, quién y el canal de contacto');
$crm_id = (int) $wpdb->get_var("SELECT id FROM {$crm} WHERE email = 'beto@prueba.test'");
ok($crm_id > 0 && (int) at_pro_obtener($a)['crm_cliente_id'] === $crm_id, 'crm_cliente_id guardado en el prospecto');
ok(strpos($correos[0]['message'], 'automatiza-crm-ficha&id=' . $crm_id) !== false, 'el correo enlaza la ficha del CRM');
ok(count($http) === 0, 'sin las constantes del servidor no sale ninguna petición HTTP (WhatsApp)');

// 4. con las constantes: un solo POST con la cabecera
define('AT_N8N_RASTRO_AVISO', 'https://n8n.prueba.test/webhook/rastro-aviso');
define('AT_RASTRO_AVISO_KEY', 'clave-de-prueba-local');
$correos = []; $http = [];
$b = at_pro_crear(['negocio' => 'Taller', 'web' => 'taller.test']);
at_pro_al_llenar(at_pro_obtener($b), ['nombre' => 'Caro Prueba', 'email' => 'caro@prueba.test', 'telefono' => '']);
ok(count($http) === 1 && $http[0]['url'] === 'https://n8n.prueba.test/webhook/rastro-aviso', 'con las constantes: un solo POST al webhook');
$h = $http[0]['args']['headers'] ?? [];
ok(($h['X-AT-Rastro-Key'] ?? '') === 'clave-de-prueba-local', 'lleva la cabecera X-AT-Rastro-Key');
$cuerpo = json_decode((string) ($http[0]['args']['body'] ?? ''), true);
ok(is_array($cuerpo) && $cuerpo['negocio'] === 'Taller' && $cuerpo['nombre'] === 'Caro Prueba' && array_key_exists('fecha_contacto', $cuerpo) && strpos($cuerpo['crm_url'], 'automatiza-crm-ficha') !== false, 'el cuerpo trae negocio, nombre, fecha de contacto y enlace al CRM');
ok(count($correos) === 1, 'el correo sale igual');

// 5. si el WhatsApp falla, el correo igual sale
$correos = []; $http = [];
add_filter('pre_http_request', fn() => new WP_Error('http_fallo', 'sin red'), 20, 0);
$c = at_pro_crear(['negocio' => 'Falla', 'web' => 'falla.test']);
at_pro_al_llenar(at_pro_obtener($c), ['nombre' => 'Dani Prueba', 'email' => 'dani@prueba.test', 'telefono' => '']);
ok(count($correos) === 1, 'si el webhook falla, el correo sale igual');
remove_all_filters('pre_http_request', 20);

// 6. la acción real: marcar_formulario dispara el aviso (extremo a extremo con lead real)
$correos = []; $http = [];
$leads = $wpdb->prefix . 'automatiza_leads';
$wpdb->insert($leads, ['created_at' => current_time('mysql'), 'name' => 'Eli Prueba', 'email' => 'eli@prueba.test', 'phone' => '+56955505555', 'source' => 'web', 'status' => 'active', 'token' => wp_generate_password(12, false)]);
$d = at_pro_crear(['negocio' => 'Extremo', 'web' => 'extremo.test']);
ok(at_pro_marcar_formulario(at_pro_obtener($d)['token'], 'eli@prueba.test', 'agenda') === true && count($correos) === 1 && at_pro_test_asunto($correos[0]['subject']) === 'Prospección: Extremo llenó el formulario', 'marcar_formulario dispara correo y paso al CRM');
ok((int) at_pro_obtener($d)['crm_cliente_id'] > 0, 'y deja el vínculo con el CRM');

$wpdb->query("DELETE FROM {$t}");
$wpdb->query("DELETE FROM {$crm} WHERE email LIKE '%@prueba.test'");
$wpdb->query("DELETE FROM {$leads} WHERE email LIKE '%@prueba.test'");
fin();
