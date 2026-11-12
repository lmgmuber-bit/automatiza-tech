<?php
/** Rastro · Prospección: funciones puras (sin WordPress ni base de datos). */
if (!defined('ABSPATH')) { exit; }

function at_pro_estados(): array {
	return ['por_contactar', 'contactado', 'respondio', 'formulario', 'descartado', 'baja'];
}

function at_pro_fuentes(): array {
	return ['lead_scout', 'sellbird', 'manual', 'instagram'];
}

/** Dominio normalizado: minúsculas, sin esquema, sin www, sin puerto ni ruta. '' si no hay host. */
function at_pro_dominio(string $url): string {
	$u = mb_strtolower(trim($url));
	$u = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $u);
	$u = (string) preg_split('#[/?\#]#', $u, 2)[0];
	if (strpos($u, '@') !== false) { $u = substr($u, strrpos($u, '@') + 1); }
	$u = (string) preg_replace('/:\d*$/', '', $u);
	$u = (string) preg_replace('/^www\./', '', $u);
	$u = rtrim($u, '.');
	if ($u === '' || preg_match('/\s/u', $u) || strpos($u, '.') === false) { return ''; }
	return $u;
}

/** Teléfono solo con dígitos; 9 dígitos que empiezan con 9 llevan el 56. Igual criterio que at_pt_telefono_wa. */
function at_pro_telefono(string $t): string {
	$n = (string) preg_replace('/\D/', '', $t);
	if (strlen($n) === 9 && $n[0] === '9') { $n = '56' . $n; }
	return (strlen($n) < 10 || strlen($n) > 15) ? '' : $n;
}

/** 16 caracteres hexadecimales en minúscula: la collation de la columna no distingue mayúsculas. */
function at_pro_token(): string {
	return bin2hex(random_bytes(8));
}

function at_pro_token_valido(string $t): bool {
	return (bool) preg_match('/^[0-9a-f]{16}$/', $t);
}

/** El estado `baja` es final; `descartado` se puede reabrir; no se retrocede desde `formulario`. */
function at_pro_transicion_valida(string $de, string $a): bool {
	$mapa = [
		'por_contactar' => ['contactado', 'respondio', 'formulario', 'descartado', 'baja'],
		'contactado' => ['respondio', 'formulario', 'descartado', 'baja'],
		'respondio' => ['formulario', 'descartado', 'baja'],
		'formulario' => ['descartado', 'baja'],
		'descartado' => ['por_contactar', 'contactado', 'baja'],
		'baja' => [],
	];
	return isset($mapa[$de]) && in_array($a, $mapa[$de], true);
}

/** Campo de la tabla al que corresponde una cabecera de CSV; '' si no se reconoce. */
function at_pro_columna_csv(string $cabecera): string {
	$c = (string) preg_replace('/^\xEF\xBB\xBF/', '', $cabecera);
	$c = mb_strtolower(trim($c));
	$alias = [
		'negocio' => ['nombre', 'name', 'negocio', 'empresa', 'company'],
		'web' => ['website', 'web', 'sitio', 'url'],
		'telefono' => ['telefono', 'teléfono', 'phone'],
		'correo' => ['email', 'correo', 'e-mail'],
		'rubro' => ['rubro', 'categoria', 'categoría', 'category'],
		'comuna' => ['comuna', 'ciudad', 'city'],
		'senales' => ['senales', 'señales', 'signals', 'motivo'],
	];
	foreach ($alias as $campo => $lista) {
		if (in_array($c, $lista, true)) { return $campo; }
	}
	return '';
}

/** Texto base que Luis copia o manda por WhatsApp. Sin precios ni promesas de resultados; siempre con el enlace de baja. */
function at_pro_mensaje(array $p, string $url_form, string $url_baja): string {
	$negocio = trim((string) ($p['negocio'] ?? ''));
	$senal = trim((string) ($p['senales'] ?? ''));
	$saludo = $negocio !== '' ? "Hola, te escribe Luis de AutomatizaTech. Estuve mirando {$negocio}" : 'Hola, te escribe Luis de AutomatizaTech. Estuve mirando tu negocio';
	$linea_senal = $senal !== '' ? " y vi que {$senal}." : '.';
	return $saludo . $linea_senal . "\n\n"
		. "En AutomatizaTech ayudamos a negocios como el tuyo a ordenar su atención y sus reservas con automatizaciones simples.\n\n"
		. "Si te interesa, puedes agendar un diagnóstico sin costo aquí: {$url_form}\n\n"
		. "Si no quieres recibir más mensajes: {$url_baja}";
}
