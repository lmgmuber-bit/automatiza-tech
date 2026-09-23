<?php
if (PHP_SAPI !== 'cli') { exit(2); }
$carreraTemp = tempnam(sys_get_temp_dir(), 'cc-circuito-');
putenv('CARRERA_TEST_DB=' . $carreraTemp);
require __DIR__ . '/carrera-fixture.php';
require_once $carreraRoot . '/public/lib.sala.carrera.php';
$n = 0;
function check_carrera(bool $ok, string $message): void {
    global $n; $n++;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $message); }
}
function entrar_carrera(string $p = 'carrera-qa', int $avatar = 0): array {
    return cb_carrera_entrar(['p'=>$p,'nombre'=>'Piloto de prueba','avatar'=>$avatar,'solicitud'=>bin2hex(random_bytes(16))]);
}
function auth_carrera(array $r, string $p = 'carrera-qa'): array {
    return ['p'=>$p,'codigo'=>$r['codigo'],'ayudante'=>$r['ayudante']];
}
for ($elegido=0;$elegido<6;$elegido++) {
    $equipo=['fase'=>'espera','pilotos'=>[]];
    for ($slot=0;$slot<6;$slot++) { $equipo['pilotos'][]=cb_carrera_ficha($slot,0,$slot===0?123:0,'',$slot===0?$elegido:$slot); }
    cb_carrera_completar_equipo($equipo);
    check_carrera(count(array_unique(array_column($equipo['pilotos'],'avatar')))===6 && $equipo['pilotos'][0]['avatar']===$elegido,'los bots completan seis roles para selección '.$elegido);
}
$equipo['fase']='corriendo';$antesEquipo=$equipo;cb_carrera_completar_equipo($equipo);
check_carrera($equipo===$antesEquipo,'los personajes no cambian durante la carrera');
$nuevo=entrar_carrera('carrera-qa-tres',5);
check_carrera($nuevo['pilotos'][0]['avatar']===5 && count(array_unique(array_column($nuevo['pilotos'],'avatar')))===6,'avatar 5 aceptado por la API y seis roles presentes');
$cambio=cb_carrera_operar('carrera_estado',auth_carrera($nuevo,'carrera-qa-tres')+['avatar'=>4]);
check_carrera($cambio['pilotos'][0]['avatar']===4 && count(array_unique(array_column($cambio['pilotos'],'avatar')))===6,'cambio a avatar 4 actualiza bots en espera');
$r = entrar_carrera();
check_carrera($r['ok'] && $r['anfitrion'] && count($r['pilotos']) === 6, 'solo tiene seis plazas');
$grupo = [$r];
for ($x = 1; $x < 6; $x++) { $grupo[] = entrar_carrera(); }
check_carrera(count(array_unique(array_column($grupo,'codigo'))) === 1, 'seis comparten sala');
check_carrera(count(array_filter(end($grupo)['pilotos'], static fn($j)=>!$j['bot'])) === 6, 'seis humanos');
check_carrera(count(array_unique(array_column(end($grupo)['pilotos'],'avatar'))) === 1, 'personajes duplicados permitidos');
$extra = entrar_carrera();
check_carrera($extra['codigo'] !== $r['codigo'], 'séptimo abre otra sala, nunca ocupa plaza existente');
$bad = cb_carrera_operar('carrera_estado',auth_carrera($r,'carrera-qa-dos'));
check_carrera(!$bad['ok'] && $bad['http'] === 403, 'aislamiento por fiesta');
$bad = cb_carrera_operar('carrera_estado',array_replace(auth_carrera($r),['ayudante'=>str_repeat('0',32)]));
check_carrera(!$bad['ok'] && $bad['http'] === 403, 'token ajeno rechazado');
$bad = cb_carrera_operar('carrera_iniciar',auth_carrera($grupo[1]));
check_carrera(!$bad['ok'] && $bad['http'] === 403, 'solo anfitrión inicia');
check_carrera($r['vueltas']===3 && $r['meta']===2700 && $r['max_ms']===150000,'tres vueltas por defecto');
$bad=cb_carrera_operar('carrera_configurar',auth_carrera($grupo[1])+['vueltas'=>1]);
check_carrera(!$bad['ok'] && $bad['http']===403,'invitado no cambia vueltas');
$bad=cb_carrera_operar('carrera_configurar',auth_carrera($r,'carrera-qa-dos')+['vueltas'=>1]);
check_carrera(!$bad['ok'] && $bad['http']===403,'configuración aislada por fiesta');
foreach([0,6,-1,1.5,'2',true,[],null] as $invalida){
    $bad=cb_carrera_operar('carrera_configurar',auth_carrera($r)+['vueltas'=>$invalida]);
    check_carrera(!$bad['ok'] && $bad['http']===422,'vueltas inválidas rechazadas sin coerción');
}
foreach([1,5,2,4,3] as $vueltas){
    $config=cb_carrera_operar('carrera_configurar',auth_carrera($r)+['vueltas'=>$vueltas]);
    check_carrera($config['vueltas']===$vueltas && $config['meta']===$vueltas*900 && $config['max_ms']===$vueltas*50000,'host configura '.$vueltas.' vueltas');
    $vista=cb_carrera_operar('carrera_estado',auth_carrera($grupo[1]));
    check_carrera($vista['vueltas']===$vueltas,'la sala comparte '.$vueltas.' vueltas');
}
$started = cb_carrera_operar('carrera_iniciar',auth_carrera($r));
check_carrera($started['fase'] === 'cuenta' && $started['inicio'] - $started['ahora'] <= 3500, 'solo arranca en menos de 15 segundos');
$bad=cb_carrera_operar('carrera_configurar',auth_carrera($r)+['vueltas'=>1]);
check_carrera(!$bad['ok'] && $bad['http']===409,'configuración bloqueada al iniciar');
$sala = cb_carrera_leer($pdo,$r['codigo']); $d = cb_carrera_datos($sala);
$now = cb_carrera_ms(); $d['inicio'] = $now-100000; $d['fase']='corriendo';
foreach ($d['pilotos'] as &$j) { $j['last']=$now; } unset($j);
cb_carrera_guardar($pdo,(int)$sala['id'],$d);
$pos = ['p'=>2600,'x'=>0.4,'seq'=>1,'ms'=>100000];
$a = cb_carrera_operar('carrera_estado',auth_carrera($r)+['pos'=>$pos]);
check_carrera($a['pilotos'][0]['p'] === 2600.0, 'progreso plausible aceptado');
$d=cb_carrera_datos(cb_carrera_leer($pdo,$r['codigo']));
$d['pilotos'][0]['peticion']=0;
cb_carrera_guardar($pdo,(int)$sala['id'],$d);
$a=cb_carrera_operar('carrera_estado',auth_carrera($r)+['pos'=>['p'=>2700,'x'=>0,'seq'=>2,'ms'=>100010]]);
check_carrera((float) $a['pilotos'][0]['p'] === 2600.0, 'teletransporte rechazado');
$d=cb_carrera_datos(cb_carrera_leer($pdo,$r['codigo']));
$d['inicio']-=4000; $d['pilotos'][0]['peticion']=0;
cb_carrera_guardar($pdo,(int)$sala['id'],$d);
$a=cb_carrera_operar('carrera_estado',auth_carrera($r)+['pos'=>['p'=>2700,'x'=>0,'seq'=>3,'ms'=>104000]]);
check_carrera($a['pilotos'][0]['fin'] === 104000, 'tiempo reportado validado, no orden de petición');
$score=cb_carrera_operar('carrera_puntaje',auth_carrera($r));
cb_carrera_operar('carrera_puntaje',auth_carrera($r));
check_carrera($score['ok'] && (int)$pdo->query('SELECT COUNT(*) FROM cc_puntajes')->fetchColumn() === 1,'beacon duplicado idempotente');
check_carrera((int)$pdo->query('SELECT puntaje FROM cc_puntajes')->fetchColumn() === 96000,'puntaje calculado en servidor');
$d=cb_carrera_datos(cb_carrera_leer($pdo,$r['codigo']));
$d['pilotos'][1]['last']=$now-5000;
$d['pilotos'][1]['p']=700; $d['pilotos'][1]['humano_p']=700; $d['pilotos'][1]['humano_ms']=30000;
cb_carrera_avanzar($d,$now);
check_carrera($d['pilotos'][1]['bot'] && $d['pilotos'][1]['asistido'],'desconectado queda con piloto automático');
check_carrera(cb_carrera_puntaje($d['pilotos'][1]) === 7000,'avance de bot no infla marca humana');
cb_carrera_avanzar($d,$d['inicio']+150001);
check_carrera($d['fase'] === 'podio','límite de carrera 150 segundos');
$nonce=bin2hex(random_bytes(16)); $i=['p'=>'carrera-qa-dos','nombre'=>'Otro piloto','solicitud'=>$nonce];
$once=cb_carrera_entrar($i); $twice=cb_carrera_entrar($i);
check_carrera($once['yo']===$twice['yo'] && $once['ayudante']===$twice['ayudante'],'reintento de entrada idempotente');
$s=cb_carrera_leer($pdo,$once['codigo']);$d=cb_carrera_datos($s);
cb_carrera_avanzar($d,$d['creada']+45001);
check_carrera($d['fase']==='cuenta','autoinicio sin anfitrión a los 45 segundos');
check_carrera(isset(cb_juegos_de_tema(cb_juegos()['aracnida']['tema'])['circuito']),'registro en el mundo arácnido');
check_carrera(!isset(cb_juegos_de_tema('carreras')['circuito']),'no pertenece al mundo de vehículos');
check_carrera(!isset(cb_juegos_de_tema('heroes')['circuito']),'no confunde la temática del admin');
$sana=cb_sala_crear('Fiesta de ayudantes','prueba-local');
foreach([1,2,3,4,5] as $vueltas){
    $prueba=['fase'=>'corriendo','inicio'=>0,'vueltas'=>$vueltas,'semilla'=>2,'pilotos'=>[]];
    for($slot=0;$slot<6;$slot++)$prueba['pilotos'][]=cb_carrera_ficha($slot,0);
    cb_carrera_avanzar($prueba,$vueltas*50000);
    check_carrera($prueba['fase']==='podio' && min(array_column($prueba['pilotos'],'p'))===$vueltas*900,'bots alcanzan meta en '.$vueltas.' vueltas');
    $humano=cb_carrera_ficha(0,0,1);
    $prueba['fase']='corriendo';
    cb_carrera_posicion($humano,$prueba,['pos'=>['p'=>$vueltas*900,'x'=>0,'ms'=>$vueltas*35000,'seq'=>1]],$vueltas*35000);
    check_carrera($humano['fin']===$vueltas*35000,'meta humana válida en '.$vueltas.' vueltas');
    check_carrera(cb_carrera_puntaje($humano,$vueltas)===95000,'puntaje normalizado '.$vueltas.' vueltas');
    $humano['asistido']=true;$humano['humano_p']=$vueltas*450;
    check_carrera(cb_carrera_puntaje($humano,$vueltas)===13500,'puntaje parcial normalizado '.$vueltas.' vueltas');
}
$antigua=['fase'=>'espera','creada'=>0,'inicio'=>0,'pilotos'=>[]];
cb_carrera_avanzar($antigua,45001);
check_carrera(cb_carrera_meta($antigua)===2700 && $antigua['fase']==='cuenta','sala antigua mantiene tres vueltas en autoinicio');
$antigua['vueltas']=1;$antigua['fase']='espera';cb_carrera_avanzar($antigua,45001);
check_carrera(cb_carrera_meta($antigua)===900 && $antigua['fase']==='cuenta','autoinicio conserva la vuelta elegida');
$amigo=cb_sala_unirse($sana['codigo'],'Amigo','prueba-local');
check_carrera($amigo['ok'] && cb_sala_accion($sana['codigo'],$amigo['ayudante'],'animo')['ok'],'salas originales conservan funcionamiento');
(require $carreraRoot.'/database/migrations/021_sala_carreras.down.php')($pdo);
check_carrera((int)$pdo->query('SELECT COUNT(*) FROM cc_puntajes')->fetchColumn()>0,'rollback conserva puntajes');
(require $carreraRoot.'/database/migrations/021_sala_carreras.php')($pdo);
(require $carreraRoot.'/database/migrations/021_sala_carreras.php')($pdo);
check_carrera(true,'migración repetible después de rollback');
echo json_encode(['ok'=>true,'pruebas'=>$n,'motor'=>'SQLite temporal','produccion'=>false],JSON_UNESCAPED_UNICODE).PHP_EOL;
// Windows libera la conexión al terminar; el archivo temporal no se publica.
