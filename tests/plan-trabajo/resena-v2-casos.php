<?php
/** Regresiones de revisión B. Solo fixtures ficticias y dobles del runner. */
$opcionAnterior = $GLOBALS['opts']['at_resena_whatsapp_webhook'];
$GLOBALS['opts']['at_resena_whatsapp_webhook'] = 'https://ajeno.ejemplo.cl/robar';
$antes = count($GLOBALS['remote']);
at_rg_disparar(2, $p);
ok(count($GLOBALS['remote']) === $antes + 1 && end($GLOBALS['remote']) === 'https://n8n-n8n.kchiba.easypanel.host/webhook/at-resena-whatsapp', 'Vigia-M1 webhook fijo ignora opcion manipulada');
$GLOBALS['opts']['at_resena_whatsapp_webhook'] = $opcionAnterior;
fixture(19); $wpdb->rows[19]->datos = '{roto'; $wpdb->rows[19]->plan_id = 900;
$avisosAntes = count($GLOBALS['mails']);
try { $termino = at_rg_termino_plan(900); at_rg_termino_plan(900); ok($termino === null && count($GLOBALS['mails']) === $avisosAntes + 1, 'Forja-I1 Vigia-B3 Calibre-H1 termino ilegible null aviso unico'); }
catch (Throwable $e) { ok(false, 'Forja-I1 Vigia-B3 Calibre-H1 termino ilegible null aviso unico'); }
$avanceAnterior=['termino'=>['fecha'=>'2026-01-01']];
ok(at_av_leer((object)['id'=>900,'avance'=>json_encode($avanceAnterior)])['termino']===$avanceAnterior['termino'] && count($GLOBALS['mails'])===$avisosAntes+1, 'Forja-I1 at_av_leer real conserva fallback sin inundar');
foreach (['https://www.google.com/maps/../url?q=https://evil.com', 'https://www.google.com/maps/%2e%2e/url?q=https://evil.com', 'https://www.google.com/maps/place/../url', 'https://g.page/../review', 'https://g.page/%2e/review', 'https://www.google.com/maps/place/a\\b', 'https://www.google.com/maps/place/a//b', 'https://www.google.com/maps/place/a/%252e%252e/b', 'https://www.google.com/maps/place/a?url=https://evil.com', 'https://maps.app.goo.gl/abc', 'https://g.co/kgs/abc'] as $url) {
    ok(at_rg_url_google($url) === '', 'Vigia-B1 URL estricta ' . $url);
}
foreach (['https://g.page/r/ejemplo/review', 'https://g.page/ejemplo/review', 'https://search.google.com/local/writereview?placeid=ChIJ123', 'https://www.google.com/maps/place/Caf%C3%A9/@-33.4,-70.6,17z/data=!4m2!3m1!1s0x123:0x456'] as $url) { ok(at_rg_url_google($url) === $url, 'Vigia-B1 URL permitida'); }
$_GET['resena_msg'] = 'Paga ahora en ajeno.ejemplo.cl'; ob_start(); at_rg_render_panel(2); $html = ob_get_clean();
ok(!str_contains($html, 'ajeno.ejemplo.cl'), 'Vigia-B2 Forja-M2 mensaje arbitrario no reflejado');
$_GET['resena_msg'] = 'guardado'; ob_start(); at_rg_render_panel(2); $html = ob_get_clean();
ok(str_contains($html, 'Se conservaron todos los intentos'), 'Vigia-B2 Forja-M2 codigo resuelto en servidor'); unset($_GET['resena_msg']);
ok(at_rg_telefono('56900000000') === '+56900000000' && at_rg_telefono('900000000') === '', 'Forja-M3 normalizacion explicita y nacional sin pais rechazado');
foreach (['56000000000','800000000','0056900000000','90000000','569000000000','abc900000000'] as $tel) { ok(at_rg_telefono($tel) === '', 'Forja-M3 rechaza telefono ambiguo'); }
$telAnterior = $wpdb->clientes[2]['telefono']; $wpdb->clientes[2]['telefono'] = '800000000'; ob_start(); at_rg_render_panel(2); $html = ob_get_clean();
ok(str_contains($html, 'WhatsApp no disponible') && str_contains($html, 'E.164'), 'Forja-M3 panel explica telefono invalido'); $wpdb->clientes[2]['telefono'] = $telAnterior;
$wpdb->clientes[500] = ['id'=>500,'tipo'=>'cliente','telefono'=>'','email'=>'']; $filasAntes = count($wpdb->rows);
ob_start(); at_rg_render_panel(500); $html = ob_get_clean();
ok(count($wpdb->rows) === $filasAntes && str_contains($html, 'name="crm_id" value="500"'), 'Forja-M1 abrir panel no crea filas prepara accion');
$wpdb->clientes[501] = ['id'=>501,'tipo'=>'prospecto']; $filasAntes = count($wpdb->rows); ob_start(); at_rg_render_panel(501); $html = ob_get_clean();
ok($html === '' && count($wpdb->rows) === $filasAntes, 'Forja-M1 prospecto sin panel ni filas');
$ddl = implode("\n", at_rg_sql_esquema());
ok(!preg_match('/bigint unsigned/i', $ddl) && substr_count($ddl, 'bigint(20) unsigned') >= 6, 'Calibre-H2 bigint con ancho estable dbDelta');
ok(str_contains($ddl, 'nota varchar(1000)') && str_contains($ddl, 'origen varchar(50)'), 'Calibre-H5 holgura columnas');
ok(defined('AT_RG_SCHEMA') && AT_RG_SCHEMA === '2', 'Calibre-H6 version numerica esperada 2');
ok(str_contains($ddl, 'abierto tinyint') && str_contains($ddl, 'KEY abierto (abierto)'), 'Calibre-H4 abierto indexado');
ok(str_contains($ddl, 'at_resena_wamids') && str_contains($ddl, 'PRIMARY KEY  (wamid)'), 'Forja-I2 Calibre-H3 indice exacto wamid');
$transAntes = $GLOBALS['trans']; at_rg_whatsapp_estado('wamid.ajeno999','sent');
ok($GLOBALS['trans'] === $transAntes, 'Forja-I2 Vigia-B4 wamid ajeno no escribe transient');
$wpdb->queries=[]; at_rg_pendientes(); $queries=implode("\n", array_column($wpdb->queries,0));
ok(str_contains($queries, 'WHERE abierto=1') && str_contains($queries, 'fecha_termino BETWEEN %s AND %s'), 'Calibre-H4 filtra mantenimiento y rango en SQL');
$datosFuente = file_get_contents($base.'plan-trabajo/resena-datos.php');
ok(str_contains($datosFuente, 'function at_rg_migrar(bool $manual = false)') && str_contains($datosFuente, '!$manual && get_transient'), 'Calibre-H7 migracion manual ignora bloqueo');
$r = at_rg_reclamar(11, ['manual'=>true]);
ok(is_wp_error($r) && str_contains($r->get_error_message(), 'el plan fue reasignado a otro cliente: revisar a mano'), 'Calibre-H8 error propio plan reasignado');
ob_start(); at_rg_render_panel(11); $html = ob_get_clean(); ok(str_contains($html, 'el plan fue reasignado a otro cliente: revisar a mano'), 'Calibre-H8 panel plan reasignado');
fixture(30); $choques=0;
$rival = function ($db, $id) use (&$rival, &$choques) { $choques++; $db->rows[$id]->revision++; if ($choques < 7) { $db->conflicto = $rival; } };
$wpdb->conflicto = $rival; $inicio = hrtime(true);
$cambio = at_rg_cambiar(30, function ($d) { $d['excluido']=true; return $d; });
ok($cambio && $choques === 7 && hrtime(true)-$inicio >= 35000000, 'Calibre-H9 ocho intentos CAS con espera entre choques'); $wpdb->conflicto=null;
$wpdb->queries=[]; at_rg_baja_guardar('ficticio@ejemplo.cl','','correo','resenas','test');
ok(str_contains(implode("\n", array_column($wpdb->queries,0)), 'ON DUPLICATE KEY UPDATE id=id'), 'Calibre-H10 baja idempotente sin VALUES obsoleto');
$token = at_rg_token_baja(2, $p); $cuerpo = '2.' . $p['id'];
ok($token === $cuerpo.'.'.hash_hmac('sha256','at-resena-baja|'.$cuerpo.'|'.$p['destinos']['correo'],wp_salt('auth')), 'Vigia-I1 HMAC dominio resena-baja');
$antes=count($GLOBALS['mails']); at_rg_avisar(2, $p, 'aviso-prueba-html', '<a href="https://ajeno.ejemplo.cl">Texto</a>');
ok(count($GLOBALS['mails']) === $antes+1 && str_contains(end($GLOBALS['mails'])['body'], '&lt;a') && !str_contains(end($GLOBALS['mails'])['body'], '<a'), 'Vigia-I3 detalle escapado aviso HTML');
$r=request(['proyecto'=>2]); $r->p['proyecto']=30;
ok(at_rg_id_request($r) === 2, 'Vigia-I4 proyecto solo URL no cuerpo');
$r=request([]); $r->p['proyecto']=2;
ok(at_rg_id_request($r) === 0, 'Vigia-I4 cuerpo no inventa proyecto');
$fallido=at_rg_intento('fallido','real','panel',false,['correo'=>true,'whatsapp'=>true],at_rg_ahora()); $fallido['correo']['estado']=$fallido['whatsapp']['estado']='fallido';
ok(function_exists('at_rg_consume_cupo') && !at_rg_consume_cupo($fallido) && !at_rg_consume_cupo(array_replace($fallido,['modo'=>'prueba'])), 'Forja-M4 decision Luis devuelve cupo ambos fallidos');
ob_start(); at_rg_render_panel(2); $html=ob_get_clean();
ok(str_contains($html, '20 pruebas') && str_contains($html, 'desconocido a aceptado'), 'Forja-M5 M6 avisos tardio y limite en panel');
foreach (['Docs/METODO_AT/ESTADO-DEL-PROYECTO.md','Docs/superpowers/plans/2026-11-19-resena-google-cierre.md'] as $doc) {
    $texto=file_get_contents(dirname(__DIR__,2).'/'.$doc);
    foreach (['desconocido→aceptado', '20 pruebas', 'reclamar no devuelve datos de WhatsApp', 'UTC', 'crm_historial', 'MySQL 5.7', 'Hostinger'] as $contrato) {
        ok(str_contains($texto,$contrato),'Docs M5 M6 M8 H11 I2 '.$doc.' '.$contrato);
    }
}
$GLOBALS['nonce']=true; $wpdb->clientes[600]=['id'=>600,'tipo'=>'cliente'];
$GLOBALS['plans'][600]=(object)['id'=>600,'crm_cliente_id'=>600,'avance'=>'{}'];
$f=at_rg_proyecto(600); $revisionAntes=$f->revision;
ob_start(); at_rg_render_panel(600,600); ob_end_clean();
ok(at_rg_fila((int)$f->id)->plan_id===null && at_rg_fila((int)$f->id)->revision===$revisionAntes,'Forja-M1 lectura no vincula plan');
$_POST=['proyecto_id'=>(string)$f->id,'crm_id'=>'600','plan_id'=>'600','operacion'=>'termino'];
ok(function_exists('at_rg_panel_proyecto') && at_rg_panel_proyecto()->id===$f->id && (int)at_rg_fila((int)$f->id)->plan_id===600,'Forja-M1 primera accion vincula identidad existente');
$d=fixture(40);
for ($t=0;$t<20;$t++) { $d['intentos'][]=at_rg_intento('prueba-'.$t,'prueba','panel',false,['correo'=>false,'whatsapp'=>false],at_rg_ahora()); }
at_rg_cas(at_rg_fila(40),$d); $antes=count($GLOBALS['mails']); $r=at_rg_reclamar(40,['manual'=>true,'prueba'=>true]);
ok(is_wp_error($r) && $r->get_error_code()==='resena_limite_pruebas' && count($GLOBALS['mails'])===$antes && count(at_rg_datos(at_rg_fila(40))['intentos'])===20,'Forja-M6 vigesima primera prueba bloquea sin borrar ni enviar');
$tardio=at_rg_intento('tardio','real','reloj',false,['correo'=>false,'whatsapp'=>true],at_rg_ahora()); $tardio['whatsapp']['estado']='desconocido';
$d=at_rg_vacio();$d['intentos']=[$tardio];$despues=at_rg_resultado($d,'tardio','real','aceptado','wamid.tardio999','');
ok($despues['intentos'][0]['whatsapp']['estado']==='aceptado' && count($despues['intentos'])===1,'Forja-M5 resultado tardio promueve sin nuevo intento');
