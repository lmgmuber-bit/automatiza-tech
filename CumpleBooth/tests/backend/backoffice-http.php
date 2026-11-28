<?php
/** Regresiones de archivado y fechas: HTTP real, SQLite y contraseña efímera. */
if (PHP_SAPI !== 'cli') { exit(2); }
$raiz = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/cumpleclick-backoffice-http-' . bin2hex(random_bytes(4));
mkdir($tmp . '/state', 0770, true);
mkdir($tmp . '/photos', 0770, true);
mkdir($tmp . '/sessions', 0700, true);
$puerto = 18490 + random_int(0, 200);
$base = 'http://127.0.0.1:' . $puerto;
$claveMaestra = bin2hex(random_bytes(24));

$env = array_merge(getenv(), [
    'CC_PDO_USER' => '', 'CC_PDO_PASSWORD' => '',
    'CC_ACCEPTANCE_DIR' => $tmp . '/acceptances', 'CC_EVENT_PROFILE_DIR' => $tmp . '/profiles', 'CC_INVITATION_DIR' => $tmp . '/invitations',
    'CC_STORAGE_MODE' => 'db', 'CC_PDO_DSN' => 'sqlite:' . $tmp . '/test.sqlite',
    'CC_APP_HMAC_KEY' => bin2hex(random_bytes(32)), 'CC_PUBLIC_BASE_URL' => $base,
    'CC_PHOTO_DIR' => $tmp . '/photos', 'CC_STATE_DIR' => $tmp . '/state',
    'CUMPLECLICK_CONFIG_FILE' => $tmp . '/no-config.php', 'CC_AJUSTES_PATH' => $tmp . '/ajustes.json',
    'CC_SMTP_HOST'=>'', 'CC_SMTP_USER'=>'', 'CC_SMTP_PASSWORD'=>'', 'CC_NOTIFY_EMAIL'=>'', 'CC_LEADS_NOTIFY_EMAIL'=>'', 'CC_ADMIN_PASSWORD_HASH' => password_hash($claveMaestra, PASSWORD_DEFAULT),
]);
foreach ($env as $k => $v) { putenv($k . '=' . $v); }
require $raiz . '/public/lib.php';
require_once __DIR__ . '/_migraciones.php';
$pdo = cb_pdo();
cb_test_migrar_todo($pdo);
foreach ([['qa-evento', 'Prueba', 'carreras'], ['qa-ajeno', 'Otra prueba', 'carreras']] as [$slug, $nombre, $tema]) {
    $pdo->prepare('INSERT INTO cc_parties(public_slug,admin_label,birthday_person_name,theme_slug,active,created_at,updated_at) VALUES(?,?,?,?,1,?,?)')
        ->execute([$slug, strtoupper($slug), $nombre, $tema, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')]);
}

$servidor = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $tmp . '/sessions', '-S', '127.0.0.1:' . $puerto, '-t', $raiz . '/public'],
    [0 => ['pipe', 'r'], 1 => ['file', (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'), 'a'], 2 => ['file', (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'), 'a']], $pipes, $raiz . '/public', $env);
register_shutdown_function(static function () use ($servidor, $tmp): void {
    if (is_resource($servidor)) { proc_terminate($servidor); proc_close($servidor); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
for ($i = 0; $i < 50; $i++) {
    $s = @fsockopen('127.0.0.1', $puerto, $errno, $errstr, 0.2);
    if ($s) { fclose($s); break; }
    usleep(100000);
}

$tests = 0;
function h_check(bool $cond, string $msg): void { global $tests; $tests++; if (!$cond) { global $fallos; $fallos++; echo 'FAIL: ' . $msg . PHP_EOL; } }

/** Un "navegador": guarda cookies y hace peticiones sin seguir redirecciones. */
final class Navegador
{
    public array $cookies = [];
    public function __construct(private string $base) {}
    public function pedir(string $metodo, string $ruta, ?array $datos = null): array
    {
        $cabeceras = [];
        if ($this->cookies) {
            $pares = [];
            foreach ($this->cookies as $k => $v) { $pares[] = $k . '=' . $v; }
            $cabeceras[] = 'Cookie: ' . implode('; ', $pares);
        }
        $cuerpo = '';
        if ($datos !== null) {
            $cuerpo = http_build_query($datos);
            $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
            $cabeceras[] = 'Content-Length: ' . strlen($cuerpo);
        }
        $ctx = stream_context_create(['http' => ['method' => $metodo, 'header' => implode("\r\n", $cabeceras),
            'content' => $cuerpo, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 15]]);
        $html = (string) @file_get_contents($this->base . $ruta, false, $ctx);
        $estado = 0;
        $ubicacion = '';
        foreach ($http_response_header ?? [] as $linea) {
            if (preg_match('/^HTTP\/\S+ (\d{3})/', $linea, $m)) { $estado = (int) $m[1]; }
            if (stripos($linea, 'Location:') === 0) { $ubicacion = trim(substr($linea, 9)); }
            if (stripos($linea, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $linea, $m)) {
                if ($m[2] === 'deleted' || $m[2] === '') { unset($this->cookies[$m[1]]); } else { $this->cookies[$m[1]] = $m[2]; }
            }
        }
        return ['estado' => $estado, 'ubicacion' => $ubicacion, 'html' => $html];
    }
    public function csrf(string $html): string
    {
        return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
    }
}


$fallos = 0;
$admin = new Navegador($base);
$r = $admin->pedir('GET', '/admin/maestro.php');
$r = $admin->pedir('POST', '/admin/maestro.php', ['csrf'=>$admin->csrf($r['html']), 'password'=>$claveMaestra]);
h_check($r['estado'] === 302, 'acceso con clave efímera');
$r = $admin->pedir('GET', '/admin/invitations.php?party=qa-evento');
$csrf = $admin->csrf($r['html']);
$pid = (int)$pdo->query("SELECT id FROM cc_parties WHERE public_slug='qa-evento'")->fetchColumn();
$datos = ['party_id'=>$pid,'theme_slug'=>'carreras','birthday_person_name'=>'Prueba de archivo','birthday_person_gender'=>'f',
 'event_date'=>'2028-02-29','event_time'=>'16:30','address'=>'Dirección de prueba','message'=>'Conservar este mensaje','language'=>'es','channel'=>'whatsapp'];
$nueva=cb_create_invitation($datos);$id=(int)$nueva['id'];unset($nueva['token']);
$r=$admin->pedir('GET','/admin/invitations.php?party=qa-evento');
$dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$r['html']);$xp=new DOMXPath($dom);
$forms=$xp->query('//form[.//button[normalize-space(.)="Archivar"]]');
$post=[];
foreach($forms as $form){
 if($xp->query('.//input[@name="invitation_id"]',$form)->item(0)?->getAttribute('value')!==(string)$id)continue;
 foreach($xp->query('.//input[@name]',$form) as $input)$post[$input->getAttribute('name')]=$input->getAttribute('value');
}
h_check(!empty($post), 'encuentra el botón real Archivar');
$before=cb_load_invitation_by_id($id);
$r=$admin->pedir('POST','/admin/invitations.php?party=qa-evento',$post);
$after=cb_load_invitation_by_id($id);
h_check($r['estado']===302 && $after['status']==='archived','archiva desde el formulario real');
foreach(['birthday_person_name','birthday_person_gender','event_date','event_time','address','message','language','channel','prompt_template','expires_at'] as $campo){
 h_check($before[$campo]===$after[$campo], 'archivar conserva '.$campo);
}
$other=cb_create_invitation($datos+[]);$otherId=(int)$other['id'];unset($other['token']);
$admin->pedir('POST','/admin/invitations.php?party=qa-evento',['action'=>'archivar_invitacion','invitation_id'=>$otherId,'csrf'=>'invalido']);
h_check(cb_load_invitation_by_id($otherId)['status']==='draft','CSRF inválido no archiva');
$admin->pedir('POST','/admin/invitations.php?party=qa-ajeno',['action'=>'archivar_invitacion','invitation_id'=>$otherId,'csrf'=>$csrf]);
h_check(cb_load_invitation_by_id($otherId)['status']==='draft','no archiva una invitación de otro evento');

foreach([['2026-02-30','16:30'],['2025-02-29','16:30'],['2026-12-20','24:00'],['2026-12-20','99:99'],['2026-12-20','23:60']] as [$fecha,$hora]){
 $n=(int)$pdo->query('SELECT COUNT(*) FROM cc_invitations')->fetchColumn();
 $r=$admin->pedir('POST','/admin/invitations.php?party=qa-evento',['action'=>'crear_invitacion','csrf'=>$csrf,
  'birthday_person_name'=>'Fecha inválida','event_date'=>$fecha,'event_time'=>$hora,'address'=>'Prueba','prompt_template'=>'']);
 h_check((int)$pdo->query('SELECT COUNT(*) FROM cc_invitations')->fetchColumn()===$n,'alta rechaza fecha/hora imposible '.$fecha.' '.$hora);
}
$baseEdit=['action'=>'actualizar_invitacion','csrf'=>$csrf,'invitation_id'=>$otherId,'birthday_person_name'=>'Editada',
 'event_date'=>'2028-02-29','event_time'=>'23:59','address'=>'Nueva dirección','message'=>'Nuevo texto','status'=>'draft','language'=>'es','channel'=>'whatsapp'];
$r=$admin->pedir('POST','/admin/invitations.php?party=qa-evento',$baseEdit);
h_check($r['estado']===302 && cb_load_invitation_by_id($otherId)['birthday_person_name']==='Editada','edición completa sigue funcionando');
foreach([['event_date','2026-02-30'],['event_time','24:00']] as [$campo,$valor]){
 $edit=$baseEdit;$edit[$campo]=$valor;$edit['birthday_person_name']='No guardar';
 $r=$admin->pedir('POST','/admin/invitations.php?party=qa-evento',$edit);
 h_check($r['estado']===200 && cb_load_invitation_by_id($otherId)['birthday_person_name']==='Editada','edición inválida no guarda parcialmente '.$campo);
}
$n=(int)$pdo->query('SELECT COUNT(*) FROM cc_finanzas')->fetchColumn();
foreach(['2026-02-30','2025-02-29','0000-01-01'] as $fecha){
 $r=$admin->pedir('POST','/admin/finanzas.php',['action'=>'guardar','csrf'=>$csrf,'fecha'=>$fecha,'tipo'=>'egreso','categoria'=>'otro','monto'=>'1500','descripcion'=>'Fecha inválida']);
 h_check((int)$pdo->query('SELECT COUNT(*) FROM cc_finanzas')->fetchColumn()===$n,'Finanzas rechaza '.$fecha);
}
$r=$admin->pedir('POST','/admin/finanzas.php',['action'=>'guardar','csrf'=>$csrf,'fecha'=>'2028-02-29','tipo'=>'egreso','categoria'=>'otro','monto'=>'1500','descripcion'=>'Bisiesto válido']);
h_check($r['estado']===302 && (int)$pdo->query('SELECT COUNT(*) FROM cc_finanzas')->fetchColumn()===$n+1,'Finanzas acepta día bisiesto');
$anon=new Navegador($base);$r=$anon->pedir('POST','/admin/invitations.php?party=qa-evento',$post);
h_check($r['estado']===302 && str_starts_with($r['ubicacion'],'login.php'),'sin sesión no se ejecuta el archivado');

foreach(['2026-02-30','2025-02-29'] as $fecha) {
 $nParties=(int)$pdo->query('SELECT COUNT(*) FROM cc_parties')->fetchColumn();
 $r=$admin->pedir('POST','/admin/index.php',['action'=>'guardar','csrf'=>$csrf,'modo'=>'nueva','admin_label'=>'Fecha imposible','birthday_person_name'=>'Prueba','tema'=>'carreras','fecha'=>$fecha,'service_plan'=>'booth','frame_reset'=>'1']);
 h_check((int)$pdo->query('SELECT COUNT(*) FROM cc_parties')->fetchColumn()===$nParties,'Fiestas rechaza '.$fecha);
}
$nParties=(int)$pdo->query('SELECT COUNT(*) FROM cc_parties')->fetchColumn();
$r=$admin->pedir('POST','/admin/index.php',['action'=>'guardar','csrf'=>$csrf,'modo'=>'nueva','admin_label'=>'Bisiesto válido','birthday_person_name'=>'Prueba','tema'=>'carreras','fecha'=>'2028-02-29','service_plan'=>'booth','frame_reset'=>'1']);
h_check($r['estado']===302 && (int)$pdo->query('SELECT COUNT(*) FROM cc_parties')->fetchColumn()===$nParties+1,'Fiestas acepta día bisiesto con formulario válido');
$album=cb_album_ensure($pid);
$beforeClose=$album['intake_closes_at'];
$r=$admin->pedir('POST','/admin/album.php?party=qa-evento',['action'=>'guardar-recepcion','csrf'=>$csrf,'intake_closes_at'=>'2026-02-30','intake_enabled'=>'1']);
h_check(cb_album_ensure($pid)['intake_closes_at']===$beforeClose,'Álbum rechaza fecha de cierre imposible');
$beforeExpiry=cb_load_invitation_by_id($otherId);
$expiryResult=cb_update_invitation($otherId,['expires_at'=>'2026-02-30','message'=>'No persistir'], 'qa');
h_check(!$expiryResult && cb_load_invitation_by_id($otherId)['message']===$beforeExpiry['message'],'vencimiento inválido no guarda parcialmente');
h_check(!cb_update_invitation($otherId,['event_date'=>'2026-02-30'],'qa'),'lib rechaza fecha inválida al editar');
h_check(!cb_update_invitation($otherId,['event_time'=>'99:99'],'qa'),'lib rechaza hora inválida al editar');
echo "backoffice-http: $tests comprobaciones, $fallos fallos\n";
exit($fallos ? 1 : 0);
