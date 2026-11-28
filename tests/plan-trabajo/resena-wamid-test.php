<?php
/** Contrato servidor <= columna. Mutación optativa en memoria; nunca edita producción. */
define('ABSPATH', __DIR__.'/fixture-sin-wordpress/');
function add_action(...$a) {}
$wpdb=new class { public $prefix='fixture_'; function get_charset_collate(){return 'DEFAULT CHARACTER SET utf8mb4';} };
$base=__DIR__.'/../../wp-content/themes/automatiza-tech/inc/';
require $base.'cierre-cliente/puras.php';
require $base.'plan-trabajo/resena-datos.php';
$validador='at_cc_wamid_valido';
if (in_array('--mutar-largo', $argv, true)) {
    $ref=new ReflectionFunction('at_cc_wamid_valido');
    $fuente=implode('',array_slice(file($ref->getFileName()),$ref->getStartLine()-1,$ref->getEndLine()-$ref->getStartLine()+1));
    $mutado=str_replace(['at_cc_wamid_valido','{8,250}'],['at_cc_wamid_valido_mutado','{8,251}'],$fuente);
    if (!str_contains($mutado,'{8,251}')) { throw new RuntimeException('Mutacion no aplicada.'); }
    eval($mutado); $validador='at_cc_wamid_valido_mutado';
}
$ddl=implode("\n",at_rg_sql_esquema());
if (!preg_match('/wamid varchar\((\d+)\)/', $ddl, $m)) { throw new RuntimeException('Falta columna wamid.'); }
$capacidad=(int)$m[1]; $fallas=0; $n=0;
foreach ([$capacidad-1,$capacidad,$capacidad+1,$capacidad+2,512,1024,4096] as $largo) {
    $n++; $acepta=$validador('wamid.'.str_repeat('A',$largo-6));
    if ($acepta!==($largo<=$capacidad)) { $fallas++; echo "FAIL V3-6 validador <= columna wamid: $largo bytes frente a $capacidad\n"; }
}
echo "$n comprobaciones de capacidad wamid, $fallas fallas\n";
exit($fallas?1:0);
